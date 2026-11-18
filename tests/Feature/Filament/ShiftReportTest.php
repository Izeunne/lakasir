<?php

use App\Filament\Tenant\Pages\Cashier;
use App\Models\Tenants\CashDrawer;
use App\Models\Tenants\Category;
use App\Models\Tenants\PaymentMethod;
use App\Models\Tenants\Product;
use App\Models\Tenants\Selling;
use App\Models\Tenants\SellingDetail;
use App\Models\Tenants\User;
use App\Services\Tenants\ShiftReportService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\RefreshDatabaseWithTenant;

use function Pest\Laravel\actingAs;

uses(RefreshDatabaseWithTenant::class);

test('shift report service correctly aggregates sold items and cash drawer totals', function () {
    $user = User::first();
    $service = app(ShiftReportService::class);

    // 1. Open shift with 200,000 starting cash
    $drawer = $service->openShift(200000, $user->id);
    expect($drawer->cash)->toEqual(200000)
        ->and($drawer->opened_by)->toBe($user->id)
        ->and($drawer->closed_by)->toBeNull();

    // 2. Create products and transactions linked to this drawer
    $cat = Category::create(['name' => 'F&B']);
    $kopi = Product::create([
        'name' => 'Kopi Susu Gula Aren',
        'selling_price' => 20000,
        'purchase_price' => 8000,
        'category_id' => $cat->id,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $toast = Product::create([
        'name' => 'Roti Bakar Keju',
        'selling_price' => 15000,
        'purchase_price' => 6000,
        'category_id' => $cat->id,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $cashMethod = PaymentMethod::firstOrCreate(
        ['name' => 'Cash'],
        ['is_cash' => true, 'is_debit' => false, 'is_credit' => false, 'is_wallet' => false]
    );

    // Transaction 1: 3x Kopi (Cash)
    $selling1 = Selling::create([
        'user_id' => $user->id,
        'cash_drawer_id' => $drawer->id,
        'payment_method_id' => $cashMethod->id,
        'code' => 'SELL0001',
        'date' => now(),
        'total_price' => 60000,
        'grand_total_price' => 60000,
        'payed_money' => 100000,
        'money_changes' => 40000,
    ]);
    SellingDetail::create([
        'selling_id' => $selling1->id,
        'product_id' => $kopi->id,
        'qty' => 3,
        'price' => 60000,
        'cost' => 24000,
    ]);

    // Transaction 2: 2x Kopi + 2x Toast (Cash)
    $selling2 = Selling::create([
        'user_id' => $user->id,
        'cash_drawer_id' => $drawer->id,
        'payment_method_id' => $cashMethod->id,
        'code' => 'SELL0002',
        'date' => now(),
        'total_price' => 70000,
        'grand_total_price' => 70000,
        'payed_money' => 70000,
        'money_changes' => 0,
    ]);
    SellingDetail::create([
        'selling_id' => $selling2->id,
        'product_id' => $kopi->id,
        'qty' => 2,
        'price' => 40000,
        'cost' => 16000,
    ]);
    SellingDetail::create([
        'selling_id' => $selling2->id,
        'product_id' => $toast->id,
        'qty' => 2,
        'price' => 30000,
        'cost' => 12000,
    ]);

    // 3. Generate summary
    $summary = $service->getShiftSummary($drawer);

    expect($summary['starting_cash'])->toEqual(200000)
        ->and($summary['total_transactions'])->toBe(2)
        ->and($summary['total_grand_total'])->toEqual(130000)
        ->and($summary['total_cash_sales'])->toEqual(130000)
        ->and($summary['expected_cash_in_drawer'])->toEqual(330000) // 200k starting + 130k cash
        ->and($summary['total_qty_sold'])->toEqual(7.0) // 5 kopi + 2 toast
        ->and($summary['sold_items'])->toHaveCount(2)
        ->and($summary['sold_items'][0]['name'])->toBe('Kopi Susu Gula Aren')
        ->and($summary['sold_items'][0]['qty'])->toEqual(5.0)
        ->and($summary['sold_items'][0]['total'])->toEqual(100000.0)
        ->and($summary['sold_items'][1]['name'])->toBe('Roti Bakar Keju')
        ->and($summary['sold_items'][1]['qty'])->toEqual(2.0)
        ->and($summary['sold_items'][1]['total'])->toEqual(30000.0);

    // 4. Close shift
    $closed = $service->closeShift($drawer, $user->id);
    expect($closed->closed_by)->toBe($user->id)
        ->and($service->getActiveDrawer())->toBeNull();
});

test('pos cashier can open shift and close shift with printable events', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $user = User::first();
    actingAs($user);

    // 1. Open shift via Livewire
    Livewire::withQueryParams(['pos_user_id' => $user->id])
        ->test(Cashier::class)
        ->set('startingCashInput', 150000)
        ->call('submitOpenShift')
        ->assertDispatched('open-shift-printed', function ($event, $params) {
            $slip = $params['slip'];
            expect($slip['starting_cash'])->toEqual(150000)
                ->and($slip['type'])->toBe('open_shift');
            return true;
        });

    $activeDrawer = CashDrawer::lastOpened()->first();
    expect($activeDrawer)->not->toBeNull()
        ->and($activeDrawer->cash)->toEqual(150000);

    // 2. Open close shift modal (populates activeShiftSummary)
    Livewire::test(Cashier::class)
        ->call('openCloseShiftModal')
        ->assertSet('activeShiftSummary.starting_cash', 150000)
        ->assertDispatched('open-modal', id: 'close-shift-modal');

    // 3. Confirm close shift
    Livewire::test(Cashier::class)
        ->call('submitCloseShift')
        ->assertDispatched('close-shift-printed', function ($event, $params) {
            $report = $params['report'];
            expect($report['starting_cash'])->toEqual(150000);
            return true;
        });

    expect(CashDrawer::lastOpened()->first())->toBeNull();
});
