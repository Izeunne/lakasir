<?php

namespace App\Services\Tenants;

use App\Models\Tenants\About;
use App\Models\Tenants\Product;
use App\Models\Tenants\Profile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class ProductReportService
{
    public function generate(array $data)
    {
        $timezone = Profile::get()->timezone ?? config('app.timezone', 'UTC');
        $about = About::first();
        $startDateLocal = Carbon::parse($data['start_date'] ?? now(), $timezone)->startOfDay();
        $endDateLocal = Carbon::parse($data['end_date'] ?? now(), $timezone)->endOfDay();
        $startDateUtc = $startDateLocal->copy()->setTimezone('UTC');
        $endDateUtc = $endDateLocal->copy()->setTimezone('UTC');

        $products = Product::query()
            ->with([
                'sellingDetails' => function ($builder) use ($startDateUtc, $endDateUtc) {
                    $builder->whereHas('selling', function ($query) use ($startDateUtc, $endDateUtc) {
                        $query->whereBetween('date', [$startDateUtc, $endDateUtc]);
                    })->with('selling:id,code,discount_price,date');
                },
            ])
            ->whereHas('sellingDetails.selling', function ($query) use ($startDateUtc, $endDateUtc) {
                $query->whereBetween('date', [$startDateUtc, $endDateUtc]);
            })
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
        $totalAllDiscountPerItem = 0;

        /** @var Product $product */
        foreach ($products as $product) {
            $sellingDetails = $product->sellingDetails;

            $totalQtyPerSelling = (float) $sellingDetails->sum('qty');
            $totalCostPerSelling = (float) $sellingDetails->sum('cost');
            $totalDiscountPerItem = (float) $sellingDetails->sum('discount_price');
            $totalBeforeDiscountPerSelling = (float) $sellingDetails->sum('price');
            $totalAfterDiscountPerSelling = $totalBeforeDiscountPerSelling - $totalDiscountPerItem;
            $totalGrossProfitPerSelling = $totalBeforeDiscountPerSelling - $totalCostPerSelling;
            $totalNetProfitPerSelling = $totalAfterDiscountPerSelling - $totalCostPerSelling;

            $avgSellingPrice = $totalQtyPerSelling > 0 ? $totalBeforeDiscountPerSelling / $totalQtyPerSelling : 0;
            $avgCostPrice = $totalQtyPerSelling > 0 ? $totalCostPerSelling / $totalQtyPerSelling : 0;

            $reports[] = [
                'code' => $product->sellingDetails->first()?->selling?->code ?? '-',
                'sku' => $product->sku ?? '-',
                'name' => $product->name,
                'selling_price' => $this->formatCurrency($avgSellingPrice),
                'selling' => $this->formatCurrency($totalBeforeDiscountPerSelling),
                'discount_price' => $this->formatCurrency($totalDiscountPerItem),
                'initial_price' => $this->formatCurrency($avgCostPrice),
                'qty' => $totalQtyPerSelling,
                'cost' => $totalCostPerSelling,
                'total_after_discount' => $this->formatCurrency($totalAfterDiscountPerSelling),
                'net_profit' => $this->formatCurrency($totalNetProfitPerSelling),
                'gross_profit' => $this->formatCurrency($totalGrossProfitPerSelling),
            ];

            $totalCost += $totalCostPerSelling;
            $totalGross += $totalBeforeDiscountPerSelling;
            $totalNet += $totalAfterDiscountPerSelling;
            $totalGrossProfit += $totalGrossProfitPerSelling;
            $totalAllDiscountPerItem += $totalDiscountPerItem;
            $totalQty += $totalQtyPerSelling;
        }

        // Calculate unique transaction discounts for sellings containing these products
        $sellingIds = $products->flatMap(fn ($p) => $p->sellingDetails->pluck('selling_id'))->unique()->filter();
        $totalDiscount = (float) \App\Models\Tenants\Selling::whereIn('id', $sellingIds)->sum('discount_price');

        $footer = [
            'total_cost' => $this->formatCurrency($totalCost),
            'total_gross' => $this->formatCurrency($totalGross),
            'total_net' => $this->formatCurrency($totalNet - $totalDiscount),
            'total_discount' => $this->formatCurrency($totalDiscount),
            'total_discount_per_item' => $this->formatCurrency($totalAllDiscountPerItem),
            'total_all_discount_per_item' => $this->formatCurrency($totalAllDiscountPerItem),
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
