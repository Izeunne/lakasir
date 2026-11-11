<?php

use App\Filament\Tenant\Pages\Cashier;
use App\Models\Tenants\Category;
use App\Models\Tenants\Product;
use App\Models\Tenants\Stock;
use App\Models\Tenants\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\RefreshDatabaseWithTenant;

use function Pest\Laravel\actingAs;

uses(RefreshDatabaseWithTenant::class);

test('cashier page can be rendered for authenticated user', function () {
    $user = User::first();

    actingAs($user)
        ->get('/member/cashier')
        ->assertOk();
});

test('cashier livewire component renders products with card view', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $user = User::first();
    actingAs($user);

    $category = Category::create([
        'name' => 'Menu Spesial',
    ]);

    $product = Product::create([
        'name' => 'Ayam Penyet Sambal Ijo',
        'selling_price' => 25000,
        'purchase_price' => 15000,
        'category_id' => $category->id,
        'type' => 'product',
        'is_non_stock' => false,
        'show' => true,
    ]);

    Stock::create([
        'product_id' => $product->id,
        'stock' => 30,
        'type' => 'in',
        'is_ready' => 1,
    ]);

    Livewire::test(Cashier::class)
        ->assertOk()
        ->assertSee('Ayam Penyet Sambal Ijo')
        ->assertSee('Menu Spesial')
        ->assertSee(price_format(25000));
});

test('cashier can filter by category', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $user = User::first();
    actingAs($user);

    $cat1 = Category::create(['name' => 'Makanan']);
    $cat2 = Category::create(['name' => 'Minuman']);

    $p1 = Product::create([
        'name' => 'Nasi Bakar',
        'selling_price' => 20000,
        'purchase_price' => 12000,
        'category_id' => $cat1->id,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $p2 = Product::create([
        'name' => 'Es Cincau',
        'selling_price' => 8000,
        'purchase_price' => 4000,
        'category_id' => $cat2->id,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    Livewire::test(Cashier::class)
        ->call('setCategory', $cat1->id)
        ->assertSee('Nasi Bakar')
        ->assertDontSee('Es Cincau')
        ->call('setCategory', $cat2->id)
        ->assertSee('Es Cincau')
        ->assertDontSee('Nasi Bakar')
        ->call('setCategory', null)
        ->assertSee('Nasi Bakar')
        ->assertSee('Es Cincau');
});

