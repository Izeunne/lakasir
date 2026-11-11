<?php

namespace App\Filament\Tenant\Widgets;

use App\Filament\Tenant\Widgets\Traits\HasDashboardDateFilter;
use App\Models\Tenants\Selling;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class SalesTrendChart extends ChartWidget
{
    use HasDashboardDateFilter, InteractsWithPageFilters;

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = [
        'md' => 8,
        'xl' => 8,
    ];

    public static function canView(): bool
    {
        return can('read revenue overview') || can('read sales overview');
    }

    public function getHeading(): ?string
    {
        return __('Sales & Profit Trend');
    }

    public function getDescription(): ?string
    {
        return __('Daily sales turnover and net profit performance');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $dates = $this->getFilterDateRange();

        // If single day is selected, display past 7 days to give meaningful trend context
        if ($dates['diffInDays'] <= 1) {
            $trendStart = $dates['end']->copy()->subDays(6)->startOfDay();
            $trendEnd = $dates['end']->copy()->endOfDay();
        } else {
            $trendStart = $dates['start']->copy()->startOfDay();
            $trendEnd = $dates['end']->copy()->endOfDay();
        }

        $records = Selling::query()
            ->isPaid()
            ->whereBetween('date', [
                $trendStart->copy()->setTimezone('UTC'),
                $trendEnd->copy()->setTimezone('UTC'),
            ])
            ->select(
                DB::raw('DATE(date) as sale_date'),
                DB::raw('SUM(total_price - COALESCE(discount_price, 0) - COALESCE(total_discount_per_item, 0)) as daily_revenue'),
                DB::raw('SUM((total_price - COALESCE(discount_price, 0) - COALESCE(total_discount_per_item, 0)) - COALESCE(total_cost, 0)) as daily_profit')
            )
            ->groupBy('sale_date')
            ->get()
            ->keyBy('sale_date');

        $labels = [];
        $revenues = [];
        $profits = [];

        $current = $trendStart->copy();
        while ($current->lte($trendEnd)) {
            $dateKey = $current->format('Y-m-d');
            $labels[] = $current->isoFormat('DD MMM');

            $row = $records->get($dateKey);
            $revenues[] = (float) ($row->daily_revenue ?? 0);
            $profits[] = (float) ($row->daily_profit ?? 0);

            $current->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => __('Total revenue'),
                    'data' => $revenues,
                    'borderColor' => '#FF6600',
                    'backgroundColor' => 'rgba(255, 102, 0, 0.08)',
                    'fill' => true,
                    'tension' => 0.3,
                    'borderWidth' => 2,
                    'pointRadius' => 3,
                ],
                [
                    'label' => __('Net profit'),
                    'data' => $profits,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.08)',
                    'fill' => true,
                    'tension' => 0.3,
                    'borderWidth' => 2,
                    'pointRadius' => 3,
                ],
            ],
            'labels' => $labels,
        ];
    }
}

