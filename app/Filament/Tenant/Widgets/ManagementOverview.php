<?php

namespace App\Filament\Tenant\Widgets;

use App\Features\Receivable as ReceivableFeature;
use App\Filament\Tenant\Widgets\Traits\HasDashboardDateFilter;
use App\Models\Tenants\Product;
use App\Models\Tenants\Receivable;
use App\Models\Tenants\Selling;
use App\Models\Tenants\Setting;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ManagementOverview extends BaseWidget
{
    use HasDashboardDateFilter, InteractsWithPageFilters;

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return can('read revenue overview') || can('read sales overview');
    }

    protected function getStats(): array
    {
        $dates = $this->getFilterDateRange();
        $startUtc = $dates['startUtc'];
        $endUtc = $dates['endUtc'];
        $prevStartUtc = $dates['prevStartUtc'];
        $prevEndUtc = $dates['prevEndUtc'];

        // Current period metrics
        $currentMetrics = $this->calculatePeriodMetrics($startUtc, $endUtc);
        // Previous period metrics for comparison
        $prevMetrics = $this->calculatePeriodMetrics($prevStartUtc, $prevEndUtc);

        // Daily trend sparklines (last 7 days or points in period)
        $sparklines = $this->calculateSparklines($dates);

        $revenueTrend = $this->getTrendData($prevMetrics['revenue'], $currentMetrics['revenue']);
        $salesTrend = $this->getTrendData($prevMetrics['sales_count'], $currentMetrics['sales_count']);

        $aov = $currentMetrics['sales_count'] > 0
            ? $currentMetrics['revenue'] / $currentMetrics['sales_count']
            : 0;

        $stats = [];

        // 1. Total Penjualan / Omset
        if (can('read revenue overview')) {
            $stats[] = Stat::make(__('Total revenue'), price_format($currentMetrics['revenue']))
                ->description($revenueTrend['percentage'] . '% ' . $revenueTrend['trend'] . ' ' . __('vs previous period'))
                ->descriptionIcon($revenueTrend['icon'])
                ->chart($sparklines['revenue'])
                ->color($revenueTrend['color']);
        }

        // 2. Laba Bersih
        if (can('read revenue overview')) {
            $profitMargin = $currentMetrics['revenue'] > 0
                ? round(($currentMetrics['net_profit'] / $currentMetrics['revenue']) * 100)
                : 0;

            $stats[] = Stat::make(__('Net profit'), price_format($currentMetrics['net_profit']))
                ->description($profitMargin . '% margin')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($sparklines['profit'])
                ->color('success');
        }

        // 3. Total Transaksi
        if (can('read sales overview')) {
            $stats[] = Stat::make(__('Total Transactions'), number_format($currentMetrics['sales_count']))
                ->description($salesTrend['percentage'] . '% ' . $salesTrend['trend'] . ' ' . __('vs previous period'))
                ->descriptionIcon($salesTrend['icon'])
                ->chart($sparklines['sales_count'])
                ->color($salesTrend['color']);
        }

        // 4. Rata-rata Transaksi (AOV)
        if (can('read sales overview')) {
            $stats[] = Stat::make(__('Average Order Value'), price_format($aov))
                ->description(__('Average per transaction'))
                ->descriptionIcon('heroicon-m-calculator')
                ->color('primary');
        }

        // 5. Total Diskon
        if (can('read sales overview')) {
            $stats[] = Stat::make(__('Total discount'), price_format($currentMetrics['total_discount']))
                ->description(__('Total promo & item discounts'))
                ->descriptionIcon('heroicon-m-tag')
                ->color('warning');
        }

        // 6. Alert Stok Menipis / Piutang
        if (feature(ReceivableFeature::class)) {
            $restReceivable = (float) Receivable::query()->sum('rest_receivable');
            $stats[] = Stat::make(__('Total rest receivable'), price_format($restReceivable))
                ->description(__('Unpaid receivables balance'))
                ->descriptionIcon('heroicon-m-document-text')
                ->color($restReceivable > 0 ? 'danger' : 'success');
        } else {
            $minStock = (int) Setting::get('minimum_stock_nofication', 0);
            $lowStockCount = Product::query()
                ->where('is_non_stock', false)
                ->whereRaw('(select coalesce(sum(stocks.stock), 0) from stocks where products.id = stocks.product_id and is_ready = 1) <= ?', [$minStock])
                ->count();

            $stats[] = Stat::make(__('Low Stock Alert'), $lowStockCount . ' ' . __('Products'))
                ->description($lowStockCount > 0 ? __('Needs immediate restocking') : __('All stocks sufficient'))
                ->descriptionIcon($lowStockCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($lowStockCount > 0 ? 'danger' : 'success');
        }

        return array_values(array_filter($stats));
    }

    private function calculatePeriodMetrics($startUtc, $endUtc): array
    {
        $data = Selling::query()
            ->isPaid()
            ->whereBetween('date', [$startUtc, $endUtc])
            ->select(
                DB::raw('COUNT(id) as sales_count'),
                DB::raw('SUM(total_price) as total_price'),
                DB::raw('SUM(COALESCE(discount_price, 0)) as discount_price'),
                DB::raw('SUM(COALESCE(total_discount_per_item, 0)) as total_discount_per_item'),
                DB::raw('SUM(COALESCE(tax_price, 0)) as tax_price'),
                DB::raw('SUM(COALESCE(total_cost, 0)) as total_cost')
            )
            ->first();

        $grossSales = (float) ($data->total_price ?? 0);
        $discountPrice = (float) ($data->discount_price ?? 0);
        $itemDiscount = (float) ($data->total_discount_per_item ?? 0);
        $totalDiscount = $discountPrice + $itemDiscount;
        $totalCost = (float) ($data->total_cost ?? 0);

        // Revenue (Net Sales after discounts)
        $revenue = max(0, $grossSales - $totalDiscount);
        // Net Profit = Revenue - HPP (Cost of Goods Sold)
        $netProfit = $revenue - $totalCost;

        return [
            'sales_count' => (int) ($data->sales_count ?? 0),
            'revenue' => $revenue,
            'net_profit' => $netProfit,
            'total_discount' => $totalDiscount,
            'total_cost' => $totalCost,
        ];
    }

    private function calculateSparklines(array $dates): array
    {
        $start = $dates['start']->copy();
        $end = $dates['end']->copy();
        $timezone = $dates['timezone'];

        // If range is single day (e.g. Today), show sparkline for past 7 days leading to today
        if ($dates['diffInDays'] <= 1) {
            $sparkStart = $end->copy()->subDays(6)->startOfDay();
            $sparkEnd = $end->copy()->endOfDay();
        } else {
            $sparkStart = $start->copy()->startOfDay();
            $sparkEnd = $end->copy()->endOfDay();
        }

        $records = Selling::query()
            ->isPaid()
            ->whereBetween('date', [
                $sparkStart->copy()->setTimezone('UTC'),
                $sparkEnd->copy()->setTimezone('UTC'),
            ])
            ->select(
                DB::raw('DATE(date) as sale_date'),
                DB::raw('SUM(total_price - COALESCE(discount_price, 0) - COALESCE(total_discount_per_item, 0)) as daily_revenue'),
                DB::raw('SUM((total_price - COALESCE(discount_price, 0) - COALESCE(total_discount_per_item, 0)) - COALESCE(total_cost, 0)) as daily_profit'),
                DB::raw('COUNT(id) as daily_count')
            )
            ->groupBy('sale_date')
            ->get()
            ->keyBy('sale_date');

        $revenues = [];
        $profits = [];
        $counts = [];

        $current = $sparkStart->copy();
        while ($current->lte($sparkEnd)) {
            $dateKey = $current->format('Y-m-d');
            $row = $records->get($dateKey);

            $revenues[] = (float) ($row->daily_revenue ?? 0);
            $profits[] = (float) ($row->daily_profit ?? 0);
            $counts[] = (int) ($row->daily_count ?? 0);

            $current->addDay();
        }

        return [
            'revenue' => $revenues,
            'profit' => $profits,
            'sales_count' => $counts,
        ];
    }

    private function getTrendData(float|int $previous, float|int $current): array
    {
        if ($previous == 0 && $current == 0) {
            return [
                'trend' => __('sideway'),
                'percentage' => 0,
                'color' => 'gray',
                'icon' => 'heroicon-m-minus',
            ];
        }

        if ($previous == 0 && $current > 0) {
            return [
                'trend' => __('increase'),
                'percentage' => 100,
                'color' => 'success',
                'icon' => 'heroicon-m-arrow-trending-up',
            ];
        }

        $percentage = round((($current - $previous) / ($previous ?: 1)) * 100);

        if ($percentage > 0) {
            return [
                'trend' => __('increase'),
                'percentage' => $percentage,
                'color' => 'success',
                'icon' => 'heroicon-m-arrow-trending-up',
            ];
        }

        if ($percentage < 0) {
            return [
                'trend' => __('decrease'),
                'percentage' => abs($percentage),
                'color' => 'danger',
                'icon' => 'heroicon-m-arrow-trending-down',
            ];
        }

        return [
            'trend' => __('sideway'),
            'percentage' => 0,
            'color' => 'warning',
            'icon' => 'heroicon-m-minus',
        ];
    }
}

