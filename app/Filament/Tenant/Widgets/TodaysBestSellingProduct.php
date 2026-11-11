<?php

namespace App\Filament\Tenant\Widgets;

use App\Filament\Tenant\Widgets\Traits\HasDashboardDateFilter;
use App\Models\Tenants\SellingDetail;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TodaysBestSellingProduct extends BaseWidget
{
    use HasDashboardDateFilter, InteractsWithPageFilters;

    protected int|string|array $columnSpan = [
        'md' => 6,
        'xl' => 6,
    ];

    public static function canView(): bool
    {
        return can('read sales overview') || can('read product');
    }

    public function getTableHeading(): ?string
    {
        return __('Top Selling Products');
    }

    public function table(Table $table): Table
    {
        $dates = $this->getFilterDateRange();
        $startUtc = $dates['startUtc'];
        $endUtc = $dates['endUtc'];

        $bestSellingProduct = SellingDetail::query()
            ->select(
                'selling_details.product_id',
                DB::raw('MIN(selling_details.id) as id'),
                DB::raw('SUM(selling_details.qty) as total_qty'),
                DB::raw('SUM(selling_details.price - COALESCE(selling_details.discount_price, 0)) as total_turnover')
            )
            ->whereHas('selling', function ($query) use ($startUtc, $endUtc) {
                $query->isPaid()->whereBetween('date', [$startUtc, $endUtc]);
            })
            ->groupBy('selling_details.product_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->with(['product.category']);

        return $table
            ->query($bestSellingProduct)
            ->columns([
                TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->weight('bold')
                    ->searchable(false)
                    ->url(fn (SellingDetail $record) => $record->product_id ? '/member/products/' . $record->product_id . '/edit' : null),
                TextColumn::make('product.category.name')
                    ->label(__('Category'))
                    ->badge()
                    ->color('gray')
                    ->default('-'),
                TextColumn::make('total_qty')
                    ->label(__('Sold'))
                    ->alignEnd()
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => number_format((float) $state) . ' pcs'),
                TextColumn::make('total_turnover')
                    ->label(__('Turnover'))
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => price_format((float) $state)),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No sales data'))
            ->emptyStateDescription(__('No products sold in the selected period.'));
    }
}
