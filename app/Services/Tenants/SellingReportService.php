<?php

namespace App\Services\Tenants;

use App\Models\Tenants\About;
use App\Models\Tenants\Profile;
use App\Models\Tenants\Selling;
use App\Models\Tenants\SellingDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class SellingReportService
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
            ->select()
            ->with(
                'sellingDetails:id,selling_id,product_id,qty,price,cost,discount_price',
                'sellingDetails.product:id,name,initial_price,selling_price,sku',
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

        $totalQty = 0;
        $totalCost = 0;
        $totalGross = 0;
        $totalNet = 0;
        $totalGrossProfit = 0;
        $totalDiscount = 0;
        $totalDiscountPerItem = 0;
        $totalNetProfitBeforeDiscountSelling = 0;
        $totalNetProfitAfterDiscountSelling = 0;

        /** @var Selling $selling */
        foreach ($sellings as $selling) {
            $totalDiscountPerItemTemp = 0;
            $totalBeforeDiscountPerSelling = 0;
            $totalAfterDiscountPerSelling = 0;
            $totalNetProfitPerSelling = 0;
            $totalGrossProfitPerSelling = 0;
            $totalCostPerSelling = 0;
            $totalQtyPerSelling = 0;

            /** @var SellingDetail $detail */
            foreach ($selling->sellingDetails as $detail) {
                $qty = (float) $detail->qty;
                $linePrice = (float) $detail->price;
                $lineCost = (float) $detail->cost;
                $lineDiscount = (float) ($detail->discount_price ?? 0);
                $lineNet = $linePrice - $lineDiscount;

                $totalDiscountPerItemTemp += $lineDiscount;
                $totalBeforeDiscountPerSelling += $linePrice;
                $totalAfterDiscountPerSelling += $lineNet;
                $totalNetProfitPerSelling += ($lineNet - $lineCost);
                $totalGrossProfitPerSelling += ($linePrice - $lineCost);
                $totalCostPerSelling += $lineCost;
                $totalQtyPerSelling += $qty;

                $unitPrice = $qty > 0 ? $linePrice / $qty : 0;
                $unitCost = $qty > 0 ? $lineCost / $qty : 0;

                $reports[] = [
                    'code' => $selling->code,
                    'sku' => $detail->product?->sku ?? '-',
                    'name' => $detail->product?->name ?? __('Produk Dihapus'),
                    'selling_price' => $this->formatCurrency($unitPrice),
                    'selling' => $this->formatCurrency($linePrice),
                    'discount_price' => $this->formatCurrency($lineDiscount),
                    'initial_price' => $this->formatCurrency($unitCost),
                    'qty' => $qty,
                    'cost' => $lineCost,
                    'total_after_discount' => $this->formatCurrency($lineNet),
                    'net_profit' => $this->formatCurrency($lineNet - $lineCost),
                    'gross_profit' => $this->formatCurrency($linePrice - $lineCost),
                ];
            }

            $totalCost += $totalCostPerSelling;
            $totalDiscount += (float) ($selling->discount_price ?? 0);
            $totalGross += $totalBeforeDiscountPerSelling;
            $totalNet += $totalAfterDiscountPerSelling;
            $totalNetProfitBeforeDiscountSelling += $totalNetProfitPerSelling;
            $totalNetProfitAfterDiscountSelling += ($totalNetProfitPerSelling - (float) ($selling->discount_price ?? 0));
            $totalGrossProfit += $totalGrossProfitPerSelling;
            $totalDiscountPerItem += $totalDiscountPerItemTemp;
            $totalQty += $totalQtyPerSelling;
        }

        $footer = [
            'total_cost' => $this->formatCurrency($totalCost),
            'total_gross' => $this->formatCurrency($totalGross),
            'total_net' => $this->formatCurrency($totalNet - $totalDiscount),
            'total_net_price_after_discount_per_item' => $this->formatCurrency($totalNet),
            'total_net_price_after_discount_selling' => $this->formatCurrency($totalNet - $totalDiscount),
            'total_discount' => $this->formatCurrency($totalDiscount),
            'total_discount_per_item' => $this->formatCurrency($totalDiscountPerItem),
            'total_gross_profit' => $this->formatCurrency($totalGross - $totalCost),
            'total_net_profit_before_discount_selling' => $this->formatCurrency($totalNet - $totalCost),
            'total_net_profit_after_discount_selling' => $this->formatCurrency($totalNet - $totalDiscount - $totalCost),
            'total_qty' => $totalQty,
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
