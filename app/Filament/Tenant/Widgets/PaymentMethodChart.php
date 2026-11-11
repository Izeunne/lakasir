<?php

namespace App\Filament\Tenant\Widgets;

use App\Filament\Tenant\Widgets\Traits\HasDashboardDateFilter;
use App\Models\Tenants\Selling;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class PaymentMethodChart extends ChartWidget
{
    use HasDashboardDateFilter, InteractsWithPageFilters;

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = [
        'md' => 4,
        'xl' => 4,
    ];

    public static function canView(): bool
    {
        return can('read sales overview') || can('read selling');
    }

    public function getHeading(): ?string
    {
        return __('Payment Methods');
    }

    public function getDescription(): ?string
    {
        return __('Sales breakdown by payment method');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $dates = $this->getFilterDateRange();
        $startUtc = $dates['startUtc'];
        $endUtc = $dates['endUtc'];

        $methods = Selling::query()
            ->isPaid()
            ->whereBetween('date', [$startUtc, $endUtc])
            ->leftJoin('payment_methods', 'sellings.payment_method_id', '=', 'payment_methods.id')
            ->select(
                DB::raw("COALESCE(payment_methods.name, 'Tunai') as method_name"),
                DB::raw('COUNT(sellings.id) as total_count'),
                DB::raw('SUM(sellings.total_price - COALESCE(sellings.discount_price, 0) - COALESCE(sellings.total_discount_per_item, 0)) as total_amount')
            )
            ->groupBy('method_name')
            ->get();

        if ($methods->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'data' => [1],
                        'backgroundColor' => ['#E5E7EB'],
                    ],
                ],
                'labels' => [__('No sales data')],
            ];
        }

        $labels = [];
        $data = [];
        $colors = [
            '#FF6600', // Primary orange
            '#10B981', // Emerald green
            '#3B82F6', // Blue
            '#8B5CF6', // Purple
            '#F59E0B', // Amber
            '#EC4899', // Pink
            '#06B6D4', // Cyan
            '#64748B', // Slate
        ];

        foreach ($methods as $row) {
            $labels[] = $row->method_name . ' (' . $row->total_count . ')';
            $data[] = (float) $row->total_amount;
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                ],
            ],
            'labels' => $labels,
        ];
    }
}
