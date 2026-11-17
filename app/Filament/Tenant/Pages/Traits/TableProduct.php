<?php

namespace App\Filament\Tenant\Pages\Traits;

use App\Models\Tenants\Product;
use App\Models\Tenants\Setting;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\HeaderActionsPosition;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;

trait TableProduct
{
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->with(['variations.options', 'category', 'stocks'])
                    ->when($this->selectedCategoryId, function ($query) {
                        $query->where('category_id', $this->selectedCategoryId);
                    })
                    ->where(function ($query) {
                        $query->where('type', 'product')
                            ->where(function ($query) {
                                $query->whereHas('stocks', function ($query) {
                                    $query->where('is_ready', 1)
                                        ->where('type', 'in')
                                        ->where('stock', '>', 0);
                                })
                                ->orWhere('is_non_stock', true);
                            })
                        ->orWhere('type', 'service');
                    })
                    ->where('show', true)
            )
            ->defaultPaginationPageOption(24)
            ->paginationPageOptions([16, 24, 48])
            ->columns([
                Stack::make([
                    ViewColumn::make('hero_image')
                        ->view('filament.tenant.pages.cashier.product-image')
                        ->extraAttributes([
                            'class' => 'w-full block',
                        ]),
                    TextColumn::make('selling_price')
                        ->color('primary')
                        ->money(Setting::get('currency', 'IDR'))
                        ->size('xs')
                        ->weight('extrabold')
                        ->extraAttributes([
                            'class' => 'font-extrabold text-primary-600 dark:text-primary-400 mt-1 text-xs',
                        ]),
                    TextColumn::make('name')
                        ->size('xs')
                        ->weight('bold')
                        ->searchable(query: function ($query, string $search): void {
                            $query->where('sku', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhereHas('barcodes', function ($query) use ($search) {
                                    $query->where('code', 'like', "%{$search}%")
                                        ->where('is_active', true);
                                });
                        })
                        ->extraAttributes([
                            'class' => 'font-bold line-clamp-2 min-h-[2rem] text-gray-900 dark:text-white leading-tight text-xs sm:text-sm',
                        ]),
                    TextColumn::make('stock')
                        ->hidden(function (Product $product) {
                            return $product->is_non_stock;
                        })
                        ->size('xs')
                        ->color(function (Product $product) {
                            if ($product->is_non_stock) {
                                return 'gray';
                            }
                            return $product->stock < Setting::get('minimum_stock_nofication', 10)
                                ? 'danger'
                                : 'success';
                        })
                        ->formatStateUsing(fn (Product $product) => __('Stock').': '.$product->stock_calculate),
                ])
                ->extraAttributes([
                    'class' => 'w-full !items-stretch',
                ])
                ->space(1),
            ])
            ->contentGrid(match ($this->viewMode ?? 'grid') {
                'list' => [
                    'default' => 1,
                    'sm' => 1,
                    'md' => 1,
                    'lg' => 1,
                    'xl' => 1,
                    '2xl' => 1,
                ],
                'compact' => [
                    'default' => 2,
                    'sm' => 3,
                    'md' => 4,
                    'lg' => 5,
                    'xl' => 5,
                    '2xl' => 6,
                ],
                default => [
                    'default' => 2,
                    'sm' => 2,
                    'md' => 3,
                    'lg' => 4,
                    'xl' => 4,
                    '2xl' => 4,
                ],
            })
            ->headerActionsPosition(HeaderActionsPosition::Bottom)
            ->searchPlaceholder(__('Search (SKU, name, barcode)'))
            ->recordAction('insert_amount')
            ->recordClasses(function (Product $product): ?string {
                $inCart = isset($this->cartItems) && $this->cartItems && $this->cartItems->contains('product_id', $product->id);

                return $inCart
                    ? 'product-ordered cursor-pointer select-none transition-all duration-150'
                    : 'cursor-pointer select-none transition-all duration-150';
            })
            ->actions([
                Action::make('insert_amount')
                    ->translateLabel()
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->color('primary')
                    ->extraAttributes([
                        'class' => 'mr-auto',
                    ])
                    ->action(fn (Product $product) => $this->addCart($product))
                    ->hiddenLabel(),
                Action::make('cart')
                    ->label(function (Product $product) {
                        $totalQty = isset($this->cartItems) && $this->cartItems ? $this->cartItems->where('product_id', $product->id)->sum('qty') : null;

                        return $totalQty ? (string) $totalQty : '';
                    })
                    ->color('primary')
                    ->icon('heroicon-s-shopping-bag')
                    ->button()
                    ->action(fn (Product $product) => $this->addCart($product))
                    ->hidden(function (Product $product) {
                        return ! (isset($this->cartItems) && $this->cartItems && $this->cartItems->contains('product_id', $product->id));
                    }),
            ]);
    }
}
