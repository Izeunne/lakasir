<?php

namespace App\Services;

use App\Models\Tenants\PrintQueue;
use App\Models\Tenants\Selling;
use App\Models\Tenants\Setting;

class PrintBridgeService
{
    /**
     * Push a selling transaction to the print queue for cashier receipt.
     */
    public static function queueReceipt(Selling $selling, int $outletId = 1, string $paperWidth = '58mm', ?string $targetPrinter = null): PrintQueue
    {
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
            ],
        ]);
    }

    /**
     * Push a kitchen order ticket to the print queue.
     */
    public static function queueKitchenTicket(Selling $selling, int $outletId = 1, ?string $stationId = null, string $paperWidth = '80mm', ?string $targetPrinter = null): PrintQueue
    {
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
     * Format Selling model into receipt layout.
     */
    public static function buildReceiptData(Selling $selling, string $paperWidth = '58mm'): array
    {
        $selling->loadMissing(['sellingDetails.product', 'paymentMethod', 'member', 'user']);

        $metaRows = [
            ['label' => 'No', 'value' => (string) ($selling->code ?? ('#' . $selling->id))],
            ['label' => 'Waktu', 'value' => $selling->date ? date('d/m/Y H:i', strtotime($selling->date)) : now()->format('d/m/Y H:i')],
            ['label' => 'Kasir', 'value' => (string) ($selling->user->name ?? 'Kasir')],
        ];

        if (!empty($selling->member?->name)) {
            $metaRows[] = ['label' => 'Pelanggan', 'value' => (string) $selling->member->name];
        }

        if (!empty($selling->order_type_label) && $selling->order_type_label !== '-') {
            $metaRows[] = ['label' => 'Tipe Order', 'value' => (string) $selling->order_type_label];
        }

        $items = [];
        foreach ($selling->sellingDetails as $detail) {
            $productName = $detail->product->name ?? 'Item';
            $qty = (float) $detail->qty;
            $price = (float) ($detail->price_per_unit ?? ($detail->price / max(1, $qty)));
            $subtotal = (float) $detail->total_price;

            $items[] = [
                'name' => $productName,
                'detail_left' => number_format($qty, 0, ',', '.') . 'x ' . number_format($price, 0, ',', '.'),
                'detail_right' => number_format($subtotal, 0, ',', '.'),
                'notes' => $detail->note ?? null,
                'modifiers' => [],
            ];
        }

        $totals = [
            ['label' => 'Subtotal', 'value' => number_format((float) ($selling->total_price ?? 0), 0, ',', '.')],
        ];

        if (!empty($selling->discount_price) && $selling->discount_price > 0) {
            $totals[] = ['label' => 'Diskon', 'value' => '-' . number_format((float) $selling->discount_price, 0, ',', '.')];
        }

        if (!empty($selling->tax) && $selling->tax > 0) {
            $totals[] = ['label' => 'Pajak', 'value' => number_format((float) $selling->tax, 0, ',', '.')];
        }

        $totals[] = [
            'label' => 'TOTAL',
            'value' => number_format((float) ($selling->grand_total_price ?? $selling->total_price ?? 0), 0, ',', '.'),
            'strong' => true,
        ];

        $payments = [];
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

        $footerLines = [
            Setting::get('receipt_footer', 'Terima Kasih Atas Kunjungan Anda'),
            'POS Mataverse (Lakasir)',
        ];

        return [
            'layout' => [
                'meta_rows' => $metaRows,
                'items' => $items,
                'totals' => $totals,
                'payments' => $payments,
                'footer_lines' => array_filter($footerLines),
            ],
            'transaction' => [
                'invoice' => (string) ($selling->code ?? ('#' . $selling->id)),
                'date' => (string) $selling->date,
                'customer' => $selling->member->name ?? null,
                'order_type' => $selling->order_type ?? null,
            ],
        ];
    }

    /**
     * Format Selling model into kitchen ticket payload.
     */
    public static function buildKitchenData(Selling $selling, ?string $stationId = null): array
    {
        $selling->loadMissing(['sellingDetails.product']);

        $items = [];
        foreach ($selling->sellingDetails as $detail) {
            $items[] = [
                'name' => $detail->product->name ?? 'Item',
                'qty' => (float) $detail->qty,
                'notes' => $detail->note ?? null,
            ];
        }

        return [
            'ticket' => [
                'number' => (string) ($selling->code ?? ('#' . $selling->id)),
                'notes' => (string) ($selling->note ?? ''),
                'created_at' => now()->format('H:i'),
                'items' => $items,
            ],
            'transaction' => [
                'invoice' => (string) ($selling->code ?? ('#' . $selling->id)),
                'date' => (string) $selling->date,
                'order_type' => $selling->order_type_label ?? $selling->order_type,
            ],
        ];
    }
}

