<?php

namespace App\Services;

use App\Models\Tenants\MobilePrinter;
use App\Models\Tenants\Printer;
use App\Models\Tenants\PrintQueue;
use App\Models\Tenants\Selling;
use App\Models\Tenants\Setting;
use Illuminate\Support\Collection;

class PrintBridgeService
{
    /**
     * Sync connected printers reported by the mobile app.
     */
    public static function syncMobilePrinters(
        array $printers,
        int $outletId = 1,
        ?string $deviceModel = null,
        bool $markOthersDisconnected = true
    ): Collection {
        $activeDeviceIds = [];

        foreach ($printers as $item) {
            $deviceId = trim((string) ($item['device_id'] ?? $item['id'] ?? $item['address'] ?? ''));
            if ($deviceId === '') {
                continue;
            }

            $activeDeviceIds[] = $deviceId;
            $deviceName = trim((string) ($item['device_name'] ?? $item['name'] ?? $deviceId));
            $connectionType = strtolower((string) ($item['connection_type'] ?? $item['type'] ?? 'bluetooth'));
            $address = $item['address'] ?? $deviceId;
            $rawPaperWidth = $item['paper_width'] ?? '58mm';
            $paperWidth = in_array($rawPaperWidth, ['58mm', '80mm'], true)
                ? $rawPaperWidth
                : '58mm';
            $isConnected = isset($item['is_connected']) ? (bool) $item['is_connected'] : true;

            $mobilePrinter = MobilePrinter::updateOrCreate(
                [
                    'outlet_id' => $outletId,
                    'device_id' => $deviceId,
                ],
                [
                    'device_name' => $deviceName,
                    'connection_type' => $connectionType,
                    'address' => $address,
                    'paper_width' => $paperWidth,
                    'device_model' => $deviceModel,
                    'is_connected' => $isConnected,
                    'last_seen_at' => now(),
                ]
            );

            // Update device_name on any linked Printer station
            Printer::where('device_id', $deviceId)->update([
                'device_name' => $mobilePrinter->device_name,
                'connection_type' => $mobilePrinter->connection_type,
            ]);
        }

        if ($markOthersDisconnected) {
            MobilePrinter::where('outlet_id', $outletId)
                ->when(!empty($activeDeviceIds), fn ($q) => $q->whereNotIn('device_id', $activeDeviceIds))
                ->update(['is_connected' => false]);
        }

        return MobilePrinter::where('outlet_id', $outletId)
            ->where('is_connected', true)
            ->orderBy('device_name')
            ->get();
    }

    /**
     * Route a Selling transaction to all active configured printers based on category & price rules.
     *
     * @return array<int, PrintQueue>
     */
    public static function queueSellingPrints(Selling $selling, int $outletId = 1): array
    {
        $selling->loadMissing(['sellingDetails.product.category', 'paymentMethod', 'member', 'user', 'table']);

        $activePrinters = Printer::active()->orderBy('id')->get();

        // Fallback if no printers configured yet
        if ($activePrinters->isEmpty()) {
            $paperWidth = Setting::get('printer_paper_width', '58mm');

            return [self::queueReceipt($selling, $outletId, $paperWidth)];
        }

        $queuedJobs = [];

        foreach ($activePrinters as $printer) {
            $filteredDetails = $selling->sellingDetails->filter(function ($detail) use ($printer) {
                $categoryId = $detail->product?->category_id;

                return $printer->appliesToCategory($categoryId ? (int) $categoryId : null);
            })->values();

            // Skip if this order has no items matching this printer's assigned categories
            if ($filteredDetails->isEmpty()) {
                continue;
            }

            $copies = max(1, min(5, (int) ($printer->copies ?? 1)));
            $paperWidth = $printer->paper_width ?: '58mm';
            $targetPrinter = $printer->device_id;

            for ($i = 0; $i < $copies; $i++) {
                if ($printer->show_price) {
                    $payloadData = self::buildFilteredReceiptData($selling, $filteredDetails, $printer);

                    $queuedJobs[] = PrintQueue::create([
                        'outlet_id' => $outletId,
                        'queue_type' => 'cashier',
                        'station_id' => (string) $printer->id,
                        'target_printer' => $targetPrinter,
                        'selling_id' => $selling->id,
                        'status' => 'queued',
                        'type' => 'receipt',
                        'paper_width' => $paperWidth,
                        'layout' => $payloadData['layout'],
                        'transaction' => $payloadData['transaction'],
                        'payload' => [
                            'printer_id' => $printer->id,
                            'printer_name' => $printer->name,
                            'target_printer' => $targetPrinter,
                            'paper_width' => $paperWidth,
                            'show_price' => true,
                            'copy' => $i + 1,
                        ],
                    ]);
                } else {
                    $payloadData = self::buildFilteredCheckerData($selling, $filteredDetails, $printer);

                    $queuedJobs[] = PrintQueue::create([
                        'outlet_id' => $outletId,
                        'queue_type' => 'kitchen',
                        'station_id' => (string) $printer->id,
                        'target_printer' => $targetPrinter,
                        'selling_id' => $selling->id,
                        'status' => 'queued',
                        'type' => 'kitchen_ticket',
                        'paper_width' => $paperWidth,
                        'layout' => $payloadData['layout'],
                        'ticket' => $payloadData['ticket'],
                        'transaction' => $payloadData['transaction'],
                        'payload' => [
                            'printer_id' => $printer->id,
                            'printer_name' => $printer->name,
                            'target_printer' => $targetPrinter,
                            'paper_width' => $paperWidth,
                            'show_price' => false,
                            'copy' => $i + 1,
                        ],
                    ]);
                }
            }
        }

        return $queuedJobs;
    }

    /**
     * Push a selling transaction to the print queue for cashier receipt (legacy/default).
     */
    public static function queueReceipt(
        Selling $selling,
        int $outletId = 1,
        string $paperWidth = '58mm',
        ?string $targetPrinter = null
    ): PrintQueue {
        $payloadData = self::buildReceiptData($selling, $paperWidth);

        return PrintQueue::create([
            'outlet_id' => $outletId,
            'queue_type' => 'cashier',
            'target_printer' => $targetPrinter,
            'selling_id' => $selling->id,
            'status' => 'queued',
            'type' => 'receipt',
            'paper_width' => $paperWidth,
            'layout' => $payloadData['layout'],
            'transaction' => $payloadData['transaction'],
            'payload' => [
                'target_printer' => $targetPrinter,
                'paper_width' => $paperWidth,
                'show_price' => true,
            ],
        ]);
    }

    /**
     * Push a kitchen order ticket to the print queue.
     */
    public static function queueKitchenTicket(
        Selling $selling,
        int $outletId = 1,
        ?string $stationId = null,
        string $paperWidth = '80mm',
        ?string $targetPrinter = null
    ): PrintQueue {
        $payloadData = self::buildKitchenData($selling, $stationId);

        return PrintQueue::create([
            'outlet_id' => $outletId,
            'queue_type' => 'kitchen',
            'target_printer' => $targetPrinter,
            'station_id' => $stationId,
            'selling_id' => $selling->id,
            'status' => 'queued',
            'type' => 'kitchen_ticket',
            'paper_width' => $paperWidth,
            'ticket' => $payloadData['ticket'],
            'transaction' => $payloadData['transaction'],
            'payload' => [
                'target_printer' => $targetPrinter,
                'paper_width' => $paperWidth,
                'show_price' => false,
            ],
        ]);
    }

    /**
     * Queue a test job for a specific configured Printer station.
     */
    public static function queuePrinterTest(Printer $printer, int $outletId = 1): PrintQueue
    {
        $now = now()->format('d/m/Y H:i:s');
        $categoriesLabel = implode(', ', $printer->category_names);
        $paperWidth = $printer->paper_width ?: '58mm';

        if ($printer->show_price) {
            return PrintQueue::create([
                'outlet_id' => $outletId,
                'queue_type' => 'cashier',
                'station_id' => (string) $printer->id,
                'target_printer' => $printer->device_id,
                'status' => 'queued',
                'type' => 'receipt',
                'paper_width' => $paperWidth,
                'layout' => [
                    'header' => $printer->header ?: $printer->name,
                    'meta_rows' => [
                        ['label' => 'Station', 'value' => $printer->name],
                        ['label' => 'Perangkat', 'value' => $printer->device_name ?: ($printer->device_id ?: 'Default')],
                        ['label' => 'Kategori', 'value' => $categoriesLabel],
                        ['label' => 'Tampil Harga', 'value' => 'YA (Struk Harga)'],
                        ['label' => 'Waktu', 'value' => $now],
                    ],
                    'items' => [
                        [
                            'name' => 'Contoh Menu Test',
                            'detail_left' => '2x 25.000',
                            'detail_right' => '50.000',
                            'notes' => 'Test cetak dengan harga',
                        ],
                    ],
                    'totals' => [
                        ['label' => 'TOTAL', 'value' => '50.000', 'strong' => true],
                    ],
                    'payments' => [
                        ['label' => 'Tunai', 'value' => '50.000'],
                    ],
                    'footer_lines' => array_filter([
                        $printer->footer ?: 'Test Print Multi-Printer Berhasil!',
                    ]),
                ],
                'transaction' => [
                    'invoice' => 'TEST-' . time(),
                    'date' => $now,
                ],
                'payload' => [
                    'printer_id' => $printer->id,
                    'printer_name' => $printer->name,
                    'target_printer' => $printer->device_id,
                    'paper_width' => $paperWidth,
                    'show_price' => true,
                ],
            ]);
        }

        // Checker / Kitchen / Bar Ticket Test (Without Prices)
        return PrintQueue::create([
            'outlet_id' => $outletId,
            'queue_type' => 'kitchen',
            'station_id' => (string) $printer->id,
            'target_printer' => $printer->device_id,
            'status' => 'queued',
            'type' => 'kitchen_ticket',
            'paper_width' => $paperWidth,
            'layout' => [
                'header' => '*** ' . strtoupper($printer->name) . ' ***',
                'meta_rows' => [
                    ['label' => 'Station', 'value' => $printer->name],
                    ['label' => 'Kategori', 'value' => $categoriesLabel],
                    ['label' => 'Mode Nota', 'value' => 'CHECKER (Tanpa Harga)'],
                    ['label' => 'Waktu', 'value' => $now],
                ],
                'items' => [
                    [
                        'name' => '2x  Contoh Menu ' . ($printer->category_names[0] ?? 'Pesanan'),
                        'detail_left' => 'Qty: 2',
                        'detail_right' => '',
                        'notes' => 'Catatan: Pedas / Less Ice',
                    ],
                ],
                'totals' => [
                    ['label' => 'Total Qty', 'value' => '2 Item', 'strong' => true],
                ],
                'payments' => [],
                'footer_lines' => array_filter([
                    $printer->footer ?: '--- Nota Pesanan ' . $printer->name . ' ---',
                ]),
            ],
            'ticket' => [
                'station_name' => $printer->name,
                'number' => 'TEST-' . time(),
                'created_at' => now()->format('H:i'),
                'items' => [
                    [
                        'name' => 'Contoh Menu ' . ($printer->category_names[0] ?? 'Pesanan'),
                        'qty' => 2,
                        'notes' => 'Catatan: Pedas / Less Ice',
                    ],
                ],
            ],
            'transaction' => [
                'invoice' => 'TEST-' . time(),
                'date' => $now,
            ],
            'payload' => [
                'printer_id' => $printer->id,
                'printer_name' => $printer->name,
                'target_printer' => $printer->device_id,
                'paper_width' => $paperWidth,
                'show_price' => false,
            ],
        ]);
    }

    /**
     * Push a manual test print job to verify connectivity.
     */
    public static function queueTestJob(int $outletId = 1, string $type = 'cashier', ?string $targetPrinter = null): PrintQueue
    {
        $now = now()->format('d/m/Y H:i:s');

        return PrintQueue::create([
            'outlet_id' => $outletId,
            'queue_type' => $type,
            'target_printer' => $targetPrinter,
            'status' => 'queued',
            'type' => 'receipt',
            'paper_width' => '58mm',
            'layout' => [
                'meta_rows' => [
                    ['label' => 'Test', 'value' => 'POS Mataverse x Lakasir'],
                    ['label' => 'Target Printer', 'value' => $targetPrinter ?: 'Default (Auto)'],
                    ['label' => 'Waktu', 'value' => $now],
                    ['label' => 'Status', 'value' => 'Koneksi Berhasil'],
                ],
                'items' => [
                    [
                        'name' => 'Multi-Printer Routing Test',
                        'detail_left' => '1x',
                        'detail_right' => 'OK',
                        'notes' => 'Target: ' . ($targetPrinter ?: 'Default Printer'),
                    ],
                ],
                'totals' => [
                    ['label' => 'Status Queue', 'value' => 'AKTIF', 'strong' => true],
                    ['label' => 'Route Target', 'value' => $targetPrinter ?: 'Default'],
                ],
                'payments' => [],
                'footer_lines' => [
                    'Multi-Printer Routing Berhasil!',
                    'Lakasir POS Web Print Bridge',
                ],
            ],
            'transaction' => [
                'invoice' => 'TEST-' . time(),
                'date' => $now,
            ],
        ]);
    }

    /**
     * Build receipt data with prices for a specific printer and filtered selling details.
     */
    public static function buildFilteredReceiptData(Selling $selling, Collection $details, Printer $printer): array
    {
        $metaRows = [
            ['label' => 'No', 'value' => (string) ($selling->code ?? ('#' . $selling->id))],
            ['label' => 'Waktu', 'value' => $selling->date ? date('d/m/Y H:i', strtotime($selling->date)) : now()->format('d/m/Y H:i')],
            ['label' => 'Kasir', 'value' => (string) ($selling->user->name ?? 'Kasir')],
        ];

        if (!empty($selling->order_type_label) && $selling->order_type_label !== '-') {
            $metaRows[] = ['label' => 'Kategori Pesanan', 'value' => (string) $selling->order_type_label];
        }

        if (!empty($selling->table?->number)) {
            $metaRows[] = ['label' => 'Meja', 'value' => (string) $selling->table->number];
        }

        if (!empty($selling->member?->name)) {
            $metaRows[] = ['label' => 'Pelanggan', 'value' => (string) $selling->member->name];
        }

        $items = [];
        $filteredSubtotal = 0;

        foreach ($details as $detail) {
            $productName = $detail->product->name ?? 'Item';
            $qty = (float) $detail->qty;
            $lineTotal = (float) ($detail->total_price ?? $detail->price ?? 0);
            $unitPrice = $qty > 0 ? ($lineTotal / $qty) : $lineTotal;
            $filteredSubtotal += $lineTotal;

            $modifiers = [];
            if (!empty($detail->selected_variations) && is_array($detail->selected_variations)) {
                foreach ($detail->selected_variations as $v) {
                    $extra = (!empty($v['price']) && $v['price'] > 0)
                        ? ' (+' . number_format((float) $v['price'], 0, ',', '.') . ')'
                        : '';
                    $modifiers[] = ($v['variation_name'] ?? '') . ': ' . ($v['option_name'] ?? '') . $extra;
                }
            }

            $items[] = [
                'name' => $productName,
                'qty' => $qty,
                'detail_left' => number_format($qty, 0, ',', '.') . 'x ' . number_format($unitPrice, 0, ',', '.'),
                'detail_right' => number_format($lineTotal, 0, ',', '.'),
                'notes' => $detail->note ?? null,
                'modifiers' => $modifiers,
            ];
        }

        $totals = [];
        if ($printer->print_all_categories) {
            $totals[] = [
                'label' => 'Subtotal',
                'value' => number_format((float) ($selling->total_price ?? $filteredSubtotal), 0, ',', '.'),
            ];

            if (!empty($selling->discount_price) && $selling->discount_price > 0) {
                $totals[] = [
                    'label' => 'Diskon',
                    'value' => '-' . number_format((float) $selling->discount_price, 0, ',', '.'),
                ];
            }

            if (!empty($selling->tax) && $selling->tax > 0) {
                $totals[] = [
                    'label' => 'Pajak (' . $selling->tax . '%)',
                    'value' => number_format((float) ($selling->tax_price ?? 0), 0, ',', '.'),
                ];
            }

            $totals[] = [
                'label' => 'TOTAL',
                'value' => number_format((float) ($selling->grand_total_price ?? $selling->total_price ?? $filteredSubtotal), 0, ',', '.'),
                'strong' => true,
            ];
        } else {
            $totals[] = [
                'label' => 'TOTAL (' . $printer->name . ')',
                'value' => number_format($filteredSubtotal, 0, ',', '.'),
                'strong' => true,
            ];
        }

        $payments = [];
        if ($printer->print_all_categories) {
            $paymentName = $selling->paymentMethod->name ?? 'Tunai';
            $payed = (float) ($selling->payed_money ?? $selling->total_price ?? 0);
            $change = (float) ($selling->money_changes ?? 0);

            $payments[] = [
                'label' => $paymentName,
                'value' => number_format($payed, 0, ',', '.'),
            ];

            if ($change > 0) {
                $payments[] = [
                    'label' => 'Kembali',
                    'value' => number_format($change, 0, ',', '.'),
                    'strong' => true,
                ];
            }
        }

        $footerLines = array_filter([
            $printer->footer ?: Setting::get('receipt_footer', 'Terima Kasih Atas Kunjungan Anda'),
        ]);

        return [
            'layout' => [
                'header' => $printer->header,
                'station_name' => $printer->name,
                'show_price' => true,
                'meta_rows' => $metaRows,
                'items' => $items,
                'totals' => $totals,
                'payments' => $payments,
                'footer_lines' => array_values($footerLines),
            ],
            'transaction' => [
                'invoice' => (string) ($selling->code ?? ('#' . $selling->id)),
                'date' => (string) $selling->date,
                'customer' => $selling->member->name ?? null,
                'order_type' => $selling->order_type_label ?? $selling->order_type ?? null,
            ],
        ];
    }

    /**
     * Build checker / kitchen / bar ticket data WITHOUT prices for a specific printer and filtered details.
     */
    public static function buildFilteredCheckerData(Selling $selling, Collection $details, Printer $printer): array
    {
        $metaRows = [
            ['label' => 'Station', 'value' => strtoupper($printer->name)],
            ['label' => 'No. Pesanan', 'value' => (string) ($selling->code ?? ('#' . $selling->id))],
            ['label' => 'Waktu', 'value' => $selling->date ? date('d/m/Y H:i', strtotime($selling->date)) : now()->format('d/m/Y H:i')],
            ['label' => 'Kasir', 'value' => (string) ($selling->user->name ?? 'Kasir')],
        ];

        if (!empty($selling->order_type_label) && $selling->order_type_label !== '-') {
            $metaRows[] = ['label' => 'Kategori Pesanan', 'value' => strtoupper((string) $selling->order_type_label)];
        }

        if (!empty($selling->table?->number)) {
            $metaRows[] = ['label' => 'Meja', 'value' => (string) $selling->table->number];
        }

        if (!empty($selling->member?->name)) {
            $metaRows[] = ['label' => 'Pelanggan', 'value' => (string) $selling->member->name];
        }

        $layoutItems = [];
        $ticketItems = [];
        $totalQty = 0;

        foreach ($details as $detail) {
            $productName = $detail->product->name ?? 'Item';
            $qty = (float) $detail->qty;
            $totalQty += $qty;

            $modifiers = [];
            if (!empty($detail->selected_variations) && is_array($detail->selected_variations)) {
                foreach ($detail->selected_variations as $v) {
                    // Notice: NO price shown on kitchen/bar checker ticket!
                    $modifiers[] = ($v['variation_name'] ?? '') . ': ' . ($v['option_name'] ?? '');
                }
            }

            $layoutItems[] = [
                'name' => number_format($qty, 0, ',', '.') . 'x  ' . $productName,
                'qty' => $qty,
                'detail_left' => '',
                'detail_right' => '',
                'notes' => $detail->note ?? null,
                'modifiers' => $modifiers,
            ];

            $ticketItems[] = [
                'name' => $productName,
                'qty' => $qty,
                'modifiers' => $modifiers,
                'notes' => $detail->note ?? null,
            ];
        }

        return [
            'layout' => [
                'header' => $printer->header ?: ('*** ' . strtoupper($printer->name) . ' ***'),
                'station_name' => $printer->name,
                'show_price' => false,
                'meta_rows' => $metaRows,
                'items' => $layoutItems,
                'totals' => [
                    [
                        'label' => 'TOTAL ITEM',
                        'value' => number_format($totalQty, 0, ',', '.') . ' Porsi/Item',
                        'strong' => true,
                    ],
                ],
                'payments' => [],
                'footer_lines' => array_values(array_filter([
                    $printer->footer ?: ('--- ' . strtoupper($printer->name) . ' CHECKER ---'),
                ])),
            ],
            'ticket' => [
                'station_name' => $printer->name,
                'number' => (string) ($selling->code ?? ('#' . $selling->id)),
                'order_type' => $selling->order_type_label ?? $selling->order_type ?? null,
                'table' => $selling->table?->number ?? null,
                'notes' => (string) ($selling->note ?? ''),
                'created_at' => now()->format('H:i'),
                'items' => $ticketItems,
            ],
            'transaction' => [
                'invoice' => (string) ($selling->code ?? ('#' . $selling->id)),
                'date' => (string) $selling->date,
                'order_type' => $selling->order_type_label ?? $selling->order_type ?? null,
            ],
        ];
    }

    /**
     * Format Selling model into receipt layout.
     */
    public static function buildReceiptData(Selling $selling, string $paperWidth = '58mm'): array
    {
        $selling->loadMissing(['sellingDetails.product', 'paymentMethod', 'member', 'user']);

        $defaultPrinter = new Printer([
            'name' => 'Kasir',
            'paper_width' => $paperWidth,
            'print_all_categories' => true,
            'show_price' => true,
        ]);

        return self::buildFilteredReceiptData($selling, $selling->sellingDetails, $defaultPrinter);
    }

    /**
     * Format Selling model into kitchen ticket payload.
     */
    public static function buildKitchenData(Selling $selling, ?string $stationId = null): array
    {
        $selling->loadMissing(['sellingDetails.product']);

        $defaultPrinter = new Printer([
            'name' => $stationId ?: 'Dapur',
            'paper_width' => '80mm',
            'print_all_categories' => true,
            'show_price' => false,
        ]);

        return self::buildFilteredCheckerData($selling, $selling->sellingDetails, $defaultPrinter);
    }
}
