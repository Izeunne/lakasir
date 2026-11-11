<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Tenants\Product;
use App\Models\Tenants\Setting;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockAlert extends BaseWidget
{
    protected int|string|array $columnSpan = [
        'md' => 6,
        'xl' => 6,
    ];

    public static function canView(): bool
    {
        return can('read product stock') || can('read product');
    }

    public function getTableHeading(): ?string
    {
        return __('Low Stock & Out of Stock Alerts');
    }

    public function table(Table $table): Table
    {
        $minStock = (int) Setting::get('minimum_stock_nofication', 0);

        $query = Product::query()
            ->where('is_non_stock', false)
            ->withSum('stocks as current_stock', 'stock')
            ->whereRaw('(select coalesce(sum(stocks.stock), 0) from stocks where products.id = stocks.product_id and is_ready = 1) <= ?', [$minStock])
            ->with('category')
            ->orderByRaw('(select coalesce(sum(stocks.stock), 0) from stocks where products.id = stocks.product_id and is_ready = 1) asc')
            ->limit(10);

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('name')
                    ->label(__('Product'))
                    ->weight('bold')
                    ->url(fn (Product $record) => '/member/products/' . $record->id . '/edit'),
                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->badge()
                    ->color('gray')
                    ->default('-'),
                TextColumn::make('current_stock')
                    ->label(__('Stock'))
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => ((int) $state) <= 0 ? 'danger' : 'warning')
                    ->formatStateUsing(function ($state) {
                        $stock = (int) $state;
                        return $stock <= 0
                            ? __('Out of Stock') . ' (0)'
                            : __('Low Stock') . " ({$stock})";
                    }),
                TextColumn::make('selling_price')
                    ->label(__('Selling price'))
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => price_format((float) $state)),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('All stocks sufficient'))
            ->emptyStateDescription(__('No products currently running low or out of stock.'))
            ->emptyStateIcon('heroicon-o-check-badge');
    }
}

