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
        ->withSession(['pos_user_id' => $user->id])
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
        ->assertSet('posCategories', function ($categories) use ($cat1, $cat2) {
            $ids = collect($categories)->pluck('id')->all();
            return in_array($cat1->id, $ids) && in_array($cat2->id, $ids);
        })
        ->assertSet('posProducts', function ($products) use ($p1, $p2, $cat1, $cat2) {
            $collection = collect($products);
            $prod1 = $collection->firstWhere('id', $p1->id);
            $prod2 = $collection->firstWhere('id', $p2->id);
            return $prod1 && $prod1['category_id'] === $cat1->id
                && $prod2 && $prod2['category_id'] === $cat2->id;
        });
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

test('saveFrontendDraft for Simpan dan Bayar prints kitchen once and skips duplicate print on reload', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $user = User::first();
    actingAs($user);

    $catMakanan = Category::create(['name' => 'Makanan']);
    $mie = Product::create([
        'name' => 'Mie Goreng',
        'selling_price' => 18000,
        'purchase_price' => 9000,
        'category_id' => $catMakanan->id,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $payload = [
        'active_draft_id' => null,
        'draft_name' => 'Meja 01',
        'order_type' => 'dine_in',
        'member_id' => null,
        'table_id' => null,
        'voucher' => null,
        'discount_price' => 0,
        'note' => null,
        'total_price' => 18000,
        'items' => [
            [
                'product_id' => $mie->id,
                'qty' => 1,
                'price' => 18000,
                'discount_price' => 0,
                'price_unit_id' => null,
                'selected_variations' => [],
                'note' => null,
            ],
        ],
    ];

    // Step 1: Simpan dan Bayar (clearCartAfter = false) -> creates draft & prints to kitchen
    Livewire::test(Cashier::class)
        ->call('saveFrontendDraft', $payload, false)
        ->assertDispatched('draft-saved');

    $draft = \App\Models\Tenants\CartDraft::first();
    expect($draft)->not->toBeNull()
        ->and($draft->is_kitchen_printed)->toBeTrue()
        ->and($draft->kitchen_printed_at)->not->toBeNull();

    // Step 2: Load draft back and click Simpan dan Bayar again without new items -> MUST NOT print again
    $payload['active_draft_id'] = $draft->id;
    Livewire::test(Cashier::class)
        ->call('saveFrontendDraft', $payload, false)
        ->assertNotDispatched('draft-saved');

    // Step 3: Unprinted draft (is_kitchen_printed = false) -> MUST print when Simpan dan Bayar is clicked
    $draft->update(['is_kitchen_printed' => false, 'kitchen_printed_at' => null]);
    Livewire::test(Cashier::class)
        ->call('saveFrontendDraft', $payload, false)
        ->assertDispatched('draft-saved');

    expect($draft->fresh()->is_kitchen_printed)->toBeTrue();
});


