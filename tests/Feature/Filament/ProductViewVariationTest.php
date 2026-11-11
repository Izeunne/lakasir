<?php

use App\Filament\Tenant\Resources\ProductResource\Pages\ViewProduct;
use App\Models\Tenants\Category;
use App\Models\Tenants\Product;
use App\Models\Tenants\ProductVariation;
use App\Models\Tenants\ProductVariationOption;
use App\Models\Tenants\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\RefreshDatabaseWithTenant;

use function Pest\Laravel\actingAs;

uses(RefreshDatabaseWithTenant::class);

test('view product page displays variations tab and content', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $user = User::first();
    actingAs($user);

    $category = Category::create(['name' => 'Minuman']);

    $product = Product::create([
        'name' => 'Es Teh Manis Jumbo',
        'selling_price' => 5000,
        'purchase_price' => 2000,
        'category_id' => $category->id,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $variation = ProductVariation::create([
        'product_id' => $product->id,
        'name' => 'Suhu Minuman',
        'type' => 'single',
        'is_required' => true,
        'sort_order' => 1,
    ]);

    ProductVariationOption::create([
        'product_variation_id' => $variation->id,
        'name' => 'Dingin (Es)',
        'price' => 0,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    ProductVariationOption::create([
        'product_variation_id' => $variation->id,
        'name' => 'Hangat',
        'price' => 0,
        'is_default' => false,
        'sort_order' => 2,
    ]);

    Livewire::test(ViewProduct::class, ['record' => $product->getKey()])
        ->assertOk()
        ->assertSee('Es Teh Manis Jumbo')
        ->assertSee('Suhu Minuman')
        ->assertSee('Dingin (Es)')
        ->assertSee('Hangat');
});

