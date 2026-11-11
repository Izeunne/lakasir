<?php

namespace App\Filament\Tenant\Widgets;

use App\Features\ProductExpired;
use App\Models\Tenants\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;

class ExpiredProduct extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return feature(ProductExpired::class) && (can('read product stock') || can('read product'));
    }

    public function getTableHeading(): ?string
    {
        return __('Expiring Products');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::nearestExpiredProduct()
                    ->with('category')
                    ->withSum(['stocks as remaining_stock' => function ($q) {
                        $q->where('stock', '>', 0);
                    }], 'stock')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('Product'))
                    ->weight('bold')
                    ->url(fn (Product $product) => '/member/products/' . $product->getKey() . '/edit'),
                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->badge()
                    ->color('gray')
                    ->default('-'),
                TextColumn::make('remaining_stock')
                    ->label(__('Stock'))
                    ->alignEnd()
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state) => number_format((float) $state) . ' pcs'),
                TextColumn::make('expired')
                    ->label(__('Expired'))
                    ->getStateUsing(fn (Product $product) => $product->expired_stock?->expired)
                    ->date('d M Y'),
                TextColumn::make('days_left')
                    ->label(__('Days left'))
                    ->getStateUsing(function (Product $product) {
                        $expiredDate = $product->expired_stock?->expired;
                        if (! $expiredDate) {
                            return '-';
                        }
                        $diff = Carbon::parse($expiredDate)->diffInDays(now(), false);
                        if ($diff > 0) {
                            return __('Expired');
                        }
                        return abs((int) $diff) . ' ' . __('Days');
                    })
                    ->badge()
                    ->color(function ($state) {
                        return $state === __('Expired') ? 'danger' : 'warning';
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No expiring products'))
            ->emptyStateDescription(__('All product stocks are well within their expiration periods.'))
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
