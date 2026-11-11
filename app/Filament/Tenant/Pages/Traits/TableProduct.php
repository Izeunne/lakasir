<?php

namespace App\Filament\Tenant\Pages\Traits;

use App\Models\Tenants\Product;
use App\Models\Tenants\Setting;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\HeaderActionsPosition;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
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
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([15, 25, 50])
            ->columns([
                Stack::make([
                    ImageColumn::make('hero_image')
                        ->translateLabel()
                        ->alignCenter()
                        ->extraAttributes([
                            'class' => 'py-0 w-full',
                        ])
                        ->extraImgAttributes([
                            'class' => 'object-cover w-full h-24 rounded-t-lg',
                        ])
                        ->height(96)
                        ->width('100%'),
                    TextColumn::make('selling_price')
                        ->color('primary')
                        ->money(Setting::get('currency', 'IDR'))
                        ->size('xs')
                        ->weight('extrabold')
                        ->extraAttributes([
                            'class' => 'font-extrabold text-primary-600 dark:text-primary-400 mt-0.5 text-xs',
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
                ])->space(1),
            ])
            ->contentGrid([
                'sm' => 2,
                'md' => 3,
                'lg' => 5,
                'xl' => 5,
                '2xl' => 5,
            ])
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
