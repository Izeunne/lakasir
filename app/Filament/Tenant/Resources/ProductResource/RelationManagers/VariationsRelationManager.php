<?php

namespace App\Filament\Tenant\Resources\ProductResource\RelationManagers;

use App\Features\ProductVariation as ProductVariationFeature;
use App\Models\Tenants\ProductVariation;
use App\Models\Tenants\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Laravel\Pennant\Feature;

class VariationsRelationManager extends RelationManager
{
    protected static string $relationship = 'variations';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Feature::active(ProductVariationFeature::class);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Nama Variasi / Pertanyaan'))
                    ->placeholder(__('Contoh: Suhu Minuman, Level Pedas, Ukuran Cup'))
                    ->required()
                    ->columnSpan(2),

                Forms\Components\Select::make('type')
                    ->label(__('Tipe Pilihan'))
                    ->options([
                        'single' => __('Pilih Satu (Single Choice)'),
                        'multiple' => __('Bisa Pilih Banyak (Multiple Choice)'),
                    ])
                    ->default('single')
                    ->required()
                    ->columnSpan(1),

                Forms\Components\Toggle::make('is_required')
                    ->label(__('Wajib Dipilih'))
                    ->default(false)
                    ->inline(false)
                    ->columnSpan(1),

                Forms\Components\Repeater::make('options')
                    ->relationship('options')
                    ->label(__('Pilihan Jawaban / Opsi'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Nama Pilihan'))
                            ->placeholder(__('Contoh: Dingin, Panas, Large, Extra Sambal'))
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('price')
                            ->label(__('Tambahan Harga (+Rp)'))
                            ->numeric()
                            ->default(0)
                            ->prefix(Setting::get('currency', 'IDR'))
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_default')
                            ->label(__('Default'))
                            ->default(false)
                            ->inline(false)
                            ->columnSpan(1),
                    ])
                    ->columns(4)
                    ->addActionLabel(__('Tambah Opsi'))
                    ->reorderable()
                    ->collapsible()
                    ->defaultItems(1)
                    ->columnSpanFull()
                    ->itemLabel(fn (array $state): ?string => isset($state['name']) ? $state['name'] . (isset($state['price']) && $state['price'] > 0 ? ' (+' . price_format((float) $state['price']) . ')' : '') : null),
            ])
            ->columns(4);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nama Variasi'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('Tipe Pilihan'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'single' => 'gray',
                        'multiple' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'single' => __('Pilih Satu'),
                        'multiple' => __('Bisa Pilih Banyak'),
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('is_required')
                    ->label(__('Wajib'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('options_summary')
                    ->label(__('Daftar Opsi & Tambahan Harga'))
                    ->state(function (ProductVariation $record): string {
                        return $record->options->map(function ($opt) {
                            $priceText = $opt->price > 0 ? ' (+' . price_format((float) $opt->price) . ')' : '';
                            $defaultText = $opt->is_default ? ' [Default]' : '';
                            return $opt->name . $priceText . $defaultText;
                        })->implode(' • ');
                    })
                    ->wrap(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Tambah Variasi'))
                    ->modalHeading(__('Tambah Variasi Menu'))
                    ->modalWidth('4xl'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading(__('Edit Variasi Menu'))
                    ->modalWidth('4xl'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Variasi Menu');
    }
}

