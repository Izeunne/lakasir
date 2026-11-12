<?php

namespace App\Services\Tenants;

use App\Models\Tenants\About;
use App\Models\Tenants\Profile;
use App\Models\Tenants\Selling;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class CashierReportService
{
    public function generate(array $data)
    {
        $timezone = Profile::get()->timezone ?? config('app.timezone', 'UTC');
        $about = About::first();
        $startDateLocal = Carbon::parse($data['start_date'] ?? now(), $timezone)->startOfDay();
        $endDateLocal = Carbon::parse($data['end_date'] ?? now(), $timezone)->endOfDay();
        $startDateUtc = $startDateLocal->copy()->setTimezone('UTC');
        $endDateUtc = $endDateLocal->copy()->setTimezone('UTC');

        $sellings = Selling::query()
            ->select('id', 'code', 'user_id', 'created_at', 'date', 'total_price', 'total_cost', 'discount_price')
            ->with(
                'sellingDetails:id,selling_id,product_id,qty,price,cost,discount_price',
                'sellingDetails.product:id,name,initial_price,selling_price',
                'user:id,name,email'
            )
            ->whereBetween('date', [$startDateUtc, $endDateUtc])
            ->orderBy('date', 'desc')
            ->get();

        $header = [
            'shop_name' => $about?->shop_name,
            'shop_location' => $about?->shop_location,
            'business_type' => $about?->business_type,
            'owner_name' => $about?->owner_name,
            'start_date' => $startDateLocal->format('d F Y'),
            'end_date' => $endDateLocal->format('d F Y'),
        ];
        $reports = [];

        $totalCost = 0;
        $totalGross = 0;
        $totalNet = 0;
        $totalGrossProfit = 0;
        $totalDiscount = 0;
        $totalDiscountPerItem = 0;
        $totalNetProfitBeforeDiscountSelling = 0;
        $totalNetProfitAfterDiscountSelling = 0;

        foreach ($sellings as $selling) {
            $totalDiscountPerItemTemp = 0;
            $totalBeforeDiscountPerSelling = 0;
            $totalAfterDiscountPerSelling = 0;
            $totalNetProfitPerSelling = 0;
            $totalGrossProfitPerSelling = 0;
            $totalCostPerSelling = 0;

            $reports[] = [
                'id' => $selling->id,
                'created_at' => Carbon::parse($selling->date ?: $selling->created_at)->setTimezone($timezone)->format('d F Y H:i'),
                'number' => $selling->code,
                'user' => $selling->user?->name ?? $selling->user?->email ?? '-',
                'transaction' => [
                    'items' => $selling->sellingDetails->map(function ($item) use (&$totalDiscountPerItemTemp, &$totalBeforeDiscountPerSelling, &$totalAfterDiscountPerSelling, &$totalNetProfitPerSelling, &$totalGrossProfitPerSelling, &$totalCostPerSelling) {
                        $qty = (float) $item->qty;
                        $linePrice = (float) $item->price;
                        $lineCost = (float) $item->cost;
                        $lineDiscount = (float) ($item->discount_price ?? 0);
                        $lineNet = $linePrice - $lineDiscount;

                        $totalDiscountPerItemTemp += $lineDiscount;
                        $totalBeforeDiscountPerSelling += $linePrice;
                        $totalAfterDiscountPerSelling += $lineNet;
                        $totalNetProfitPerSelling += ($lineNet - $lineCost);
                        $totalGrossProfitPerSelling += ($linePrice - $lineCost);
                        $totalCostPerSelling += $lineCost;

                        $unitPrice = $qty > 0 ? $linePrice / $qty : 0;
                        $unitCost = $qty > 0 ? $lineCost / $qty : 0;

                        return [
                            'product' => $item->product?->name ?? __('Produk Dihapus'),
                            'quantity' => $qty,
                            'product_price' => $this->formatCurrency($unitPrice),
                            'product_cost' => $this->formatCurrency($unitCost),
                            'price' => $this->formatCurrency($linePrice),
                            'cost' => $this->formatCurrency($lineCost),
                            'discount_price' => $this->formatCurrency($lineDiscount),
                            'total_after_discount' => $this->formatCurrency($lineNet),
                            'net_profit' => $this->formatCurrency($lineNet - $lineCost),
                            'gross_profit' => $this->formatCurrency($linePrice - $lineCost),
                        ];
                    }),
                ],
                'total' => [
                    'cost' => $this->formatCurrency($totalCostPerSelling),
                    'discount' => $this->formatCurrency($totalDiscountPerItemTemp),
                    'gross_selling' => $this->formatCurrency($totalBeforeDiscountPerSelling),
                    'net_selling' => $this->formatCurrency($totalAfterDiscountPerSelling),
                    'discount_selling' => $this->formatCurrency((float) ($selling->discount_price ?? 0)),
                    'total_net_profit' => $this->formatCurrency($totalNetProfitPerSelling),
                    'total_gross_profit' => $this->formatCurrency($totalGrossProfitPerSelling),
                    'grand_total' => $this->formatCurrency($totalAfterDiscountPerSelling - (float) ($selling->discount_price ?? 0)),
                ],
            ];

            $totalCost += $totalCostPerSelling;
            $totalDiscount += (float) ($selling->discount_price ?? 0);
            $totalGross += $totalBeforeDiscountPerSelling;
            $totalNet += $totalAfterDiscountPerSelling;
            $totalGrossProfit += $totalGrossProfitPerSelling;
            $totalDiscountPerItem += $totalDiscountPerItemTemp;
            $totalNetProfitBeforeDiscountSelling += $totalNetProfitPerSelling;
            $totalNetProfitAfterDiscountSelling += ($totalNetProfitPerSelling - (float) ($selling->discount_price ?? 0));
        }

        $footer = [
            'total_cost' => $this->formatCurrency($totalCost),
            'total_gross' => $this->formatCurrency($totalGross),
            'total_net' => $this->formatCurrency($totalNet - $totalDiscount),
            'total_discount' => $this->formatCurrency($totalDiscount),
            'total_discount_per_item' => $this->formatCurrency($totalDiscountPerItem),
            'total_gross_profit' => $this->formatCurrency($totalGross - $totalCost),
            'total_net_profit_before_discount_selling' => $this->formatCurrency($totalNet - $totalCost),
            'total_net_profit_after_discount_selling' => $this->formatCurrency($totalNet - $totalDiscount - $totalCost),
        ];

        return [
            'reports' => $reports,
            'footer' => $footer,
            'header' => $header,
        ];
    }

    private function formatCurrency($value)
    {
        return Number::format($value);
    }
}
