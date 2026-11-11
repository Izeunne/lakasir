<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Widgets\ExpiredProduct;
use App\Filament\Tenant\Widgets\LowStockAlert;
use App\Filament\Tenant\Widgets\ManagementOverview;
use App\Filament\Tenant\Widgets\PaymentMethodChart;
use App\Filament\Tenant\Widgets\RecentSalesTable;
use App\Filament\Tenant\Widgets\SalesTrendChart;
use App\Filament\Tenant\Widgets\TodaysBestSellingProduct;
use App\Models\Tenants\Profile;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function getColumns(): int | string | array
    {
        return 12;
    }

    public function filtersForm(Form $form): Form
    {
        $timezone = Profile::get()->timezone ?: config('app.timezone', 'UTC');
        $todayStr = now($timezone)->format('Y-m-d');

        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('period')
                            ->label(__('Period'))
                            ->options([
                                'today' => __('Today'),
                                'yesterday' => __('Yesterday'),
                                'last_7_days' => __('Last 7 Days'),
                                'last_30_days' => __('Last 30 Days'),
                                'this_month' => __('This Month'),
                                'custom' => __('Custom Range'),
                            ])
                            ->default('today')
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                $timezone = Profile::get()->timezone ?: config('app.timezone', 'UTC');
                                $now = now($timezone);

                                match ($state) {
                                    'today' => [
                                        $set('startDate', $now->copy()->format('Y-m-d')),
                                        $set('endDate', $now->copy()->format('Y-m-d')),
                                    ],
                                    'yesterday' => [
                                        $set('startDate', $now->copy()->subDay()->format('Y-m-d')),
                                        $set('endDate', $now->copy()->subDay()->format('Y-m-d')),
                                    ],
                                    'last_7_days' => [
                                        $set('startDate', $now->copy()->subDays(6)->format('Y-m-d')),
                                        $set('endDate', $now->copy()->format('Y-m-d')),
                                    ],
                                    'last_30_days' => [
                                        $set('startDate', $now->copy()->subDays(29)->format('Y-m-d')),
                                        $set('endDate', $now->copy()->format('Y-m-d')),
                                    ],
                                    'this_month' => [
                                        $set('startDate', $now->copy()->startOfMonth()->format('Y-m-d')),
                                        $set('endDate', $now->copy()->endOfMonth()->format('Y-m-d')),
                                    ],
                                    default => null,
                                };
                            }),
                        DatePicker::make('startDate')
                            ->label(__('Start Date'))
                            ->default($todayStr)
                            ->native(false)
                            ->closeOnDateSelection(),
                        DatePicker::make('endDate')
                            ->label(__('End Date'))
                            ->default($todayStr)
                            ->native(false)
                            ->closeOnDateSelection(),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 3,
                    ]),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            ManagementOverview::class,
            SalesTrendChart::class,
            PaymentMethodChart::class,
            TodaysBestSellingProduct::class,
            LowStockAlert::class,
            RecentSalesTable::class,
            ExpiredProduct::class,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
