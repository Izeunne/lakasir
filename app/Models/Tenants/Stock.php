<?php

namespace App\Models\Tenants;

use App\Features\ProductExpired;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenants\Setting;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\RawJs;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * @mixin IdeHelperStock
 */
class Stock extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = [
        'total_selling_price',
        'total_initial_price',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchasing(): BelongsTo
    {
        return $this->belongsTo(Purchasing::class);
    }

    public function scopeProduct($query, $product_id)
    {
        return $query->where('product_id', $product_id);
    }

    public function scopeIn($query)
    {
        return $query->where('type', 'in');
    }

    public function scopeOut($query)
    {
        return $query->where('type', 'out');
    }

    public function scopeLatestIn()
    {
        return $this->where('type', 'in')
            ->where('stock', '>', 0)
            ->where('date', '<=', now());
    }

    public function totalSellingPrice(): Attribute
    {
        return Attribute::make(get: fn () => (float) $this->selling_price * (float) $this->init_stock);
    }

    public function totalInitialPrice(): Attribute
    {
        return Attribute::make(get: fn () => (float) $this->initial_price * (float) $this->init_stock);
    }

    public static function form(): array
    {
        return [
            TextInput::make('stock')
                ->translateLabel()
                ->numeric()
                ->required()
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                    $stock = (float) Str::of((string) $state)->replace(',', '')->value();
                    $set('total_initial_price', Str::of((string) $get('initial_price'))->replace(',', '')->toInteger() * $stock);
                    $set('total_selling_price', Str::of((string) $get('selling_price'))->replace(',', '')->toInteger() * $stock);
                })
                ->live(onBlur: true),
            DatePicker::make('expired')
                ->rule('after:now')
                ->visible(Feature::active(ProductExpired::class))
                ->closeOnDateSelection()
                ->native(false)
                ->date(),
            TextInput::make('initial_price')
                ->prefix(Setting::get('currency', 'IDR'))
                ->mask(RawJs::make('$money($input)'))
                ->lte('selling_price')
                ->stripCharacters(',')
                ->numeric()
                ->required()
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                    $stock = (float) Str::of((string) $get('stock'))->replace(',', '')->value();
                    $set('total_initial_price', Str::of((string) $state)->replace(',', '')->toInteger() * $stock);
                })
                ->live(onBlur: true),
            TextInput::make('selling_price')
                ->prefix(Setting::get('currency', 'IDR'))
                ->mask(RawJs::make('$money($input)'))
                ->gte('initial_price')
                ->stripCharacters(',')
                ->numeric()
                ->required()
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                    $stock = (float) Str::of((string) $get('stock'))->replace(',', '')->value();
                    $set('total_selling_price', Str::of((string) $state)->replace(',', '')->toInteger() * $stock);
                })
                ->live(onBlur: true),
        ];
    }
}
