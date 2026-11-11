<?php

use App\Filament\Tenant\Pages\Dashboard;
use App\Filament\Tenant\Widgets\LowStockAlert;
use App\Filament\Tenant\Widgets\ManagementOverview;
use App\Filament\Tenant\Widgets\PaymentMethodChart;
use App\Filament\Tenant\Widgets\RecentSalesTable;
use App\Filament\Tenant\Widgets\SalesTrendChart;
use App\Filament\Tenant\Widgets\TodaysBestSellingProduct;
use App\Models\Tenants\Category;
use App\Models\Tenants\PaymentMethod;
use App\Models\Tenants\Product;
use App\Models\Tenants\Selling;
use App\Models\Tenants\SellingDetail;
use App\Models\Tenants\Stock;
use App\Models\Tenants\User;
use Livewire\Livewire;
use Tests\RefreshDatabaseWithTenant;

use function Pest\Laravel\actingAs;

uses(RefreshDatabaseWithTenant::class);

test('dashboard page can be rendered for authenticated user', function () {
    $user = User::first();

    actingAs($user)
        ->get('/member')
        ->assertOk();
});

test('dashboard component contains all management widgets', function () {
    $dashboard = new Dashboard();
    $widgets = $dashboard->getWidgets();

    expect($widgets)->toContain(ManagementOverview::class)
        ->toContain(SalesTrendChart::class)
        ->toContain(PaymentMethodChart::class)
        ->toContain(TodaysBestSellingProduct::class)
        ->toContain(LowStockAlert::class)
        ->toContain(RecentSalesTable::class);
});

test('management widgets can be rendered with livewire without exceptions', function () {
    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('tenant'));
    $user = User::first();

    actingAs($user);

    // Create category and product
    $category = Category::create(['name' => 'Beverage']);
    $product = Product::create([
        'name' => 'Iced Latte',
        'category_id' => $category->id,
        'initial_price' => 10000,
        'selling_price' => 25000,
        'is_non_stock' => false,
    ]);

    // Create stock
    Stock::create([
        'product_id' => $product->id,
        'stock' => 3,
        'type' => 'in',
        'is_ready' => 1,
    ]);

    $paymentMethod = PaymentMethod::first() ?? PaymentMethod::create([
        'name' => 'Cash',
        'is_cash' => true,
    ]);

    // Create selling and detail
    $selling = Selling::create([
        'date' => now(),
        'code' => 'INV-TEST-001',
        'user_id' => $user->id,
        'payment_method_id' => $paymentMethod->id,
        'total_price' => 25000,
        'total_cost' => 10000,
        'discount_price' => 0,
        'total_discount_per_item' => 0,
        'tax_price' => 0,
        'payed_money' => 30000,
        'money_changes' => 5000,
        'total_qty' => 1,
        'is_paid' => true,
    ]);

    SellingDetail::create([
        'selling_id' => $selling->id,
        'product_id' => $product->id,
        'qty' => 1,
        'price' => 25000,
        'cost' => 10000,
        'discount_price' => 0,
    ]);

    Livewire::test(ManagementOverview::class)->assertSuccessful();
    Livewire::test(SalesTrendChart::class)->assertSuccessful();
    Livewire::test(PaymentMethodChart::class)->assertSuccessful();
    Livewire::test(TodaysBestSellingProduct::class)->assertSuccessful();
    Livewire::test(LowStockAlert::class)->assertSuccessful();
    Livewire::test(RecentSalesTable::class)->assertSuccessful();
});
