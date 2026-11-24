<?php

namespace App\Services\Tenants;

use App\Models\Tenants\About;
use App\Models\Tenants\CashDrawer;
use App\Models\Tenants\Selling;
use App\Models\Tenants\User;
use Illuminate\Support\Carbon;

class ShiftReportService
{
    /**
     * Get the currently active open cash drawer / shift.
     */
    public function getActiveDrawer(): ?CashDrawer
    {
        return CashDrawer::lastOpened()->first();
    }

    /**
     * Open a new shift / cash drawer.
     */
    public function openShift(float $startingCash = 0, int $userId = 1): CashDrawer
    {
        return CashDrawer::create([
            'cash' => $startingCash,
            'opened_by' => $userId,
        ]);
    }

    /**
     * Close the specified shift / cash drawer.
     */
    public function closeShift(CashDrawer $drawer, int $userId): CashDrawer
    {
        $drawer->update([
            'closed_by' => $userId,
        ]);

        return $drawer->fresh(['openedBy', 'closedBy']);
    }

    /**
     * Generate complete summary data for a shift (Buka Kasir / Tutup Kasir).
     */
    public function getShiftSummary(CashDrawer $drawer): array
    {
        $drawer->loadMissing(['openedBy', 'closedBy']);
        $about = About::first();

        $sellings = Selling::with(['sellingDetails.product', 'paymentMethod'])
            ->where('cash_drawer_id', $drawer->id)
            ->get();

        $startingCash = (float) $drawer->cash;
        $totalTransactions = $sellings->count();
        $totalGrossSales = 0.0;
        $totalDiscounts = 0.0;
        $totalGrandTotal = 0.0;
        $totalCashSales = 0.0;

        $paymentBreakdown = [];
        $soldItemsMap = [];
        $totalQtySold = 0.0;

        foreach ($sellings as $selling) {
            $sellingTotal = (float) ($selling->grand_total_price ?? $selling->total_price ?? 0);
            $sellingDiscount = (float) ($selling->discount_price ?? 0);
            $sellingSubtotal = (float) ($selling->total_price ?? 0);

            $totalGrossSales += $sellingSubtotal;
            $totalDiscounts += $sellingDiscount;
            $totalGrandTotal += $sellingTotal;

            // Payment method breakdown
            $pMethod = $selling->paymentMethod;
            $pMethodName = $pMethod?->name ?? 'Tunai';
            $isCash = (bool) ($pMethod?->is_cash ?? ($pMethodName === 'Tunai' || $pMethodName === 'Cash'));

            if ($isCash) {
                $totalCashSales += $sellingTotal;
            }

            if (! isset($paymentBreakdown[$pMethodName])) {
                $paymentBreakdown[$pMethodName] = [
                    'name' => $pMethodName,
                    'is_cash' => $isCash,
                    'count' => 0,
                    'total' => 0.0,
                ];
            }
            $paymentBreakdown[$pMethodName]['count']++;
            $paymentBreakdown[$pMethodName]['total'] += $sellingTotal;

            // Sold items aggregation
            foreach ($selling->sellingDetails as $detail) {
                $prodId = $detail->product_id;
                $prodName = $detail->product?->name ?? 'Item #' . $prodId;
                $qty = (float) ($detail->qty ?? 1);
                $linePrice = (float) ($detail->price ?? 0);

                $totalQtySold += $qty;

                if (! isset($soldItemsMap[$prodId])) {
                    $soldItemsMap[$prodId] = [
                        'product_id' => $prodId,
                        'name' => $prodName,
                        'qty' => 0.0,
                        'total' => 0.0,
                    ];
                }

                $soldItemsMap[$prodId]['qty'] += $qty;
                $soldItemsMap[$prodId]['total'] += $linePrice;
            }
        }

        // Sort items by quantity sold descending
        $soldItems = array_values($soldItemsMap);
        usort($soldItems, fn ($a, $b) => $b['qty'] <=> $a['qty']);

        $expectedCashInDrawer = $totalCashSales;

        return [
            'shop_name' => $about?->shop_name ?? 'Mataverse POS',
            'shop_location' => $about?->shop_location,
            'drawer_id' => $drawer->id,
            'is_closed' => ! empty($drawer->closed_by),
            'opened_by' => $drawer->openedBy?->name ?? 'Kasir',
            'opened_at' => $drawer->created_at ? Carbon::parse($drawer->created_at)->format('d/m/Y H:i') : '-',
            'closed_by' => $drawer->closedBy?->name ?? (pos_user()?->name ?? auth()->user()?->name ?? 'Kasir'),
            'closed_at' => $drawer->closed_by ? Carbon::parse($drawer->updated_at)->format('d/m/Y H:i') : now()->format('d/m/Y H:i'),
            'starting_cash' => $startingCash,
            'total_cash_sales' => $totalCashSales,
            'expected_cash_in_drawer' => $expectedCashInDrawer,
            'total_transactions' => $totalTransactions,
            'total_gross_sales' => $totalGrossSales,
            'total_discounts' => $totalDiscounts,
            'total_grand_total' => $totalGrandTotal,
            'total_qty_sold' => $totalQtySold,
            'payment_breakdown' => array_values($paymentBreakdown),
            'sold_items' => $soldItems,
        ];
    }
}
