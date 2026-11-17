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

test('saving cart as draft dispatches draft-saved event with printable items and delta tracking', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $user = User::first();
    actingAs($user);

    $catMinuman = Category::create(['name' => 'Minuman']);
    $catMakanan = Category::create(['name' => 'Makanan']);

    $kopi = Product::create([
        'name' => 'Es Kopi Susu',
        'selling_price' => 15000,
        'purchase_price' => 8000,
        'category_id' => $catMinuman->id,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $nasi = Product::create([
        'name' => 'Nasi Goreng',
        'selling_price' => 25000,
        'purchase_price' => 12000,
        'category_id' => $catMakanan->id,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    // Test 1: Add drink only, set order type and save draft
    Livewire::test(Cashier::class)
        ->call('addCart', $kopi)
        ->set('orderType', 'dine_in')
        ->set('draftName', 'Meja 05 - Kopi')
        ->call('saveCartAsDraft')
        ->assertDispatched('draft-saved', function ($event, $params) use ($kopi) {
            $draft = $params['draft'];
            expect($draft['name'])->toBe('Meja 05 - Kopi')
                ->and($draft['order_type'])->toBe('dine_in')
                ->and($draft['items_to_print'])->toHaveCount(1)
                ->and($draft['items_to_print'][0]['product_id'])->toBe($kopi->id)
                ->and($draft['items_to_print'][0]['qty'])->toEqual(1);
            return true;
        });

    $savedDraft = \App\Models\Tenants\CartDraft::first();
    expect($savedDraft)->not->toBeNull()
        ->and($savedDraft->name)->toBe('Meja 05 - Kopi')
        ->and($savedDraft->items[0]['printed_qty'])->toEqual(1);

    // Test 2: Load draft back, add food (Nasi Goreng), save draft again (delta printing)
    Livewire::test(Cashier::class)
        ->call('loadDraft', $savedDraft->id)
        ->call('addCart', $nasi)
        ->call('saveCartAsDraft')
        ->assertDispatched('draft-saved', function ($event, $params) use ($nasi) {
            $draft = $params['draft'];
            // ONLY Nasi Goreng is in items_to_print! Es Kopi Susu is NOT reprinted!
            expect($draft['items_to_print'])->toHaveCount(1)
                ->and($draft['items_to_print'][0]['product_id'])->toBe($nasi->id)
                ->and($draft['items_to_print'][0]['is_additional'])->toBeTrue();
            return true;
        });

    // Test 3: Manual reprint of entire draft
    Livewire::test(Cashier::class)
        ->call('printDraft', $savedDraft->id)
        ->assertDispatched('draft-saved', function ($event, $params) {
            $draft = $params['draft'];
            expect($draft['is_reprint'])->toBeTrue()
                ->and($draft['items_to_print'])->toHaveCount(2);
            return true;
        });
});


