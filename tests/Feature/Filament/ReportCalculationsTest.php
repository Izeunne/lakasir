<?php

use App\Filament\Tenant\Pages\CashierReport as CashierReportPage;
use App\Filament\Tenant\Pages\ProductReport as ProductReportPage;
use App\Filament\Tenant\Pages\PurchasingReport as PurchasingReportPage;
use App\Filament\Tenant\Pages\Report as MainReportPage;
use App\Filament\Tenant\Pages\SellingReport as SellingReportPage;
use App\Models\Tenants\Category;
use App\Models\Tenants\PaymentMethod;
use App\Models\Tenants\Product;
use App\Models\Tenants\Purchasing;
use App\Models\Tenants\Selling;
use App\Models\Tenants\SellingDetail;
use App\Models\Tenants\Stock;
use App\Models\Tenants\Supplier;
use App\Models\Tenants\User;
use App\Services\Tenants\CashierReportService;
use App\Services\Tenants\ProductReportService;
use App\Services\Tenants\PurchasingReportService;
use App\Services\Tenants\SellingReportService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\RefreshDatabaseWithTenant;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabaseWithTenant::class);

test('main report page redirects to selling report', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $user = User::first();
    actingAs($user);

    Livewire::test(MainReportPage::class)
        ->assertRedirect(SellingReportPage::getUrl());
});

test('selling report calculates gross selling, discounts, and profits accurately', function () {
    $user = User::first();
    actingAs($user);

    $cat = Category::create(['name' => 'Makanan']);
    $productA = Product::create([
        'name' => 'Nasi Goreng',
        'sku' => 'NG-01',
        'category_id' => $cat->id,
        'initial_price' => 15000,
        'selling_price' => 25000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $paymentMethod = PaymentMethod::first() ?? PaymentMethod::create(['name' => 'Cash']);

    // Create a selling with 2 items of productA (50,000 total price, 30,000 cost), item discount 5,000, transaction voucher discount 5,000
    $selling = Selling::create([
        'user_id' => $user->id,
        'code' => 'SELL-TEST-001',
        'date' => now()->format('Y-m-d H:i:s'),
        'payment_method_id' => $paymentMethod->id,
        'total_price' => 50000,
        'total_cost' => 30000,
        'total_qty' => 2,
        'total_discount_per_item' => 5000,
        'discount_price' => 5000,
        'payed_money' => 50000,
        'money_changes' => 10000,
    ]);

    SellingDetail::create([
        'selling_id' => $selling->id,
        'product_id' => $productA->id,
        'qty' => 2,
        'price' => 50000, // gross
        'cost' => 30000,  // cost
        'discount_price' => 5000, // item discount
    ]);

    $service = new SellingReportService();
    $report = $service->generate([
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
    ]);

    expect($report['reports'])->toHaveCount(1);
    $row = $report['reports'][0];

    // Verify row calculation:
    // Selling must be gross: 50,000
    // Discount: 5,000
    // Total after discount (Net Selling): 45,000
    // Gross profit (selling - cost): 50,000 - 30,000 = 20,000
    // Net profit (net_selling - cost): 45,000 - 30,000 = 15,000
    expect(str_replace(',', '', $row['selling']))->toBe('50000')
        ->and(str_replace(',', '', $row['discount_price']))->toBe('5000')
        ->and(str_replace(',', '', $row['total_after_discount']))->toBe('45000')
        ->and(str_replace(',', '', $row['gross_profit']))->toBe('20000')
        ->and(str_replace(',', '', $row['net_profit']))->toBe('15000');

    // Footer calculations:
    // Total gross: 50,000
    // Total cost: 30,000
    // Total discount per item: 5,000
    // Total discount (per selling): 5,000
    // Total net after all discounts: 50,000 - 5,000 - 5,000 = 40,000
    // Total gross profit: 50,000 - 30,000 = 20,000
    // Net profit before selling discount: 45,000 - 30,000 = 15,000
    // Net profit after selling discount: 40,000 - 30,000 = 10,000
    $footer = $report['footer'];
    expect(str_replace(',', '', $footer['total_gross']))->toBe('50000')
        ->and(str_replace(',', '', $footer['total_cost']))->toBe('30000')
        ->and(str_replace(',', '', $footer['total_discount_per_item']))->toBe('5000')
        ->and(str_replace(',', '', $footer['total_discount']))->toBe('5000')
        ->and(str_replace(',', '', $footer['total_net']))->toBe('40000')
        ->and(str_replace(',', '', $footer['total_gross_profit']))->toBe('20000')
        ->and(str_replace(',', '', $footer['total_net_profit_before_discount_selling']))->toBe('15000')
        ->and(str_replace(',', '', $footer['total_net_profit_after_discount_selling']))->toBe('10000');
});

test('product report does not deduct item discounts twice and does not multiply transaction discounts', function () {
    $user = User::first();
    actingAs($user);

    $cat = Category::create(['name' => 'Makanan']);
    $productA = Product::create([
        'name' => 'Burger',
        'sku' => 'BGR-01',
        'category_id' => $cat->id,
        'initial_price' => 10000,
        'selling_price' => 20000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $productB = Product::create([
        'name' => 'Fries',
        'sku' => 'FRS-01',
        'category_id' => $cat->id,
        'initial_price' => 5000,
        'selling_price' => 10000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $paymentMethod = PaymentMethod::first() ?? PaymentMethod::create(['name' => 'Cash']);

    // Single transaction with both Burger and Fries, and a 2,000 transaction discount
    $selling = Selling::create([
        'user_id' => $user->id,
        'code' => 'SELL-PROD-01',
        'date' => now()->format('Y-m-d H:i:s'),
        'payment_method_id' => $paymentMethod->id,
        'total_price' => 30000,
        'total_cost' => 15000,
        'total_qty' => 2,
        'total_discount_per_item' => 3000,
        'discount_price' => 2000, // transaction discount
        'payed_money' => 30000,
        'money_changes' => 5000,
    ]);

    SellingDetail::create([
        'selling_id' => $selling->id,
        'product_id' => $productA->id,
        'qty' => 1,
        'price' => 20000,
        'cost' => 10000,
        'discount_price' => 2000, // Burger item discount
    ]);

    SellingDetail::create([
        'selling_id' => $selling->id,
        'product_id' => $productB->id,
        'qty' => 1,
        'price' => 10000,
        'cost' => 5000,
        'discount_price' => 1000, // Fries item discount
    ]);

    $service = new ProductReportService();
    $report = $service->generate([
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
    ]);

    expect($report['reports'])->toHaveCount(2);

    $burgerRow = collect($report['reports'])->firstWhere('sku', 'BGR-01');
    // Burger: Selling 20,000, Dsc 2,000, Net Selling (total_after_discount) 18,000 (NOT 16,000 double deducted!)
    expect(str_replace(',', '', $burgerRow['selling']))->toBe('20000')
        ->and(str_replace(',', '', $burgerRow['discount_price']))->toBe('2000')
        ->and(str_replace(',', '', $burgerRow['total_after_discount']))->toBe('18000')
        ->and(str_replace(',', '', $burgerRow['gross_profit']))->toBe('10000')
        ->and(str_replace(',', '', $burgerRow['net_profit']))->toBe('8000');

    // Footer:
    // Total discount per transaction MUST BE 2,000 (NOT multiplied by 2 items = 4,000!)
    $footer = $report['footer'];
    expect(str_replace(',', '', $footer['total_discount']))->toBe('2000')
        ->and(str_replace(',', '', $footer['total_all_discount_per_item']))->toBe('3000')
        ->and(str_replace(',', '', $footer['total_gross']))->toBe('30000')
        ->and(str_replace(',', '', $footer['total_cost']))->toBe('15000')
        ->and(str_replace(',', '', $footer['total_net']))->toBe('25000'); // 30,000 - 3,000 - 2,000
});

test('cashier report groups transactions and calculates items correctly', function () {
    $user = User::first();
    actingAs($user);

    $cat = Category::create(['name' => 'Minuman']);
    $product = Product::create([
        'name' => 'Kopi Latte',
        'sku' => 'LATTE-01',
        'category_id' => $cat->id,
        'initial_price' => 8000,
        'selling_price' => 18000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $paymentMethod = PaymentMethod::first() ?? PaymentMethod::create(['name' => 'Cash']);

    $selling = Selling::create([
        'user_id' => $user->id,
        'code' => 'SELL-CASHIER-01',
        'date' => now()->format('Y-m-d H:i:s'),
        'payment_method_id' => $paymentMethod->id,
        'total_price' => 36000,
        'total_cost' => 16000,
        'total_qty' => 2,
        'total_discount_per_item' => 0,
        'discount_price' => 0,
        'payed_money' => 40000,
        'money_changes' => 4000,
    ]);

    SellingDetail::create([
        'selling_id' => $selling->id,
        'product_id' => $product->id,
        'qty' => 2,
        'price' => 36000,
        'cost' => 16000,
        'discount_price' => 0,
    ]);

    $service = new CashierReportService();
    $report = $service->generate([
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
    ]);

    expect($report['reports'])->toHaveCount(1);
    $tx = $report['reports'][0];
    expect($tx['number'])->toBe($selling->fresh()->code)
        ->and(str_replace(',', '', $tx['total']['grand_total']))->toBe('36000');
});

test('purchasing report includes purchases on the selected end date without subDay exclusion', function () {
    $user = User::first();
    actingAs($user);

    $cat = Category::create(['name' => 'Bahan Baku']);

    $supplier = Supplier::create([
        'name' => 'Supplier Utama',
        'address' => 'Jl. Test No. 1',
        'phone_number' => '08123456789',
    ]);

    $product = Product::create([
        'name' => 'Bahan Baku A',
        'sku' => 'BBA-01',
        'category_id' => $cat->id,
        'initial_price' => 10000,
        'selling_price' => 15000,
        'type' => 'product',
        'is_non_stock' => false,
        'show' => true,
        'unit' => 'Kg',
    ]);

    $todayStr = now()->format('Y-m-d');

    $purchasing = Purchasing::create([
        'number' => 'PO-TODAY-001',
        'supplier_id' => $supplier->id,
        'date' => $todayStr,
        'total_initial_price' => 50000,
        'total_selling_price' => 75000,
        'user_id' => $user->id,
    ]);

    Stock::create([
        'product_id' => $product->id,
        'purchasing_id' => $purchasing->id,
        'supplier_id' => $supplier->id,
        'date' => $todayStr,
        'init_stock' => 5,
        'stock' => 5,
        'initial_price' => 10000,
        'total_initial_price' => 50000,
        'selling_price' => 15000,
        'total_selling_price' => 75000,
    ]);

    $service = new PurchasingReportService();

    // Query for TODAY ONLY (start_date == end_date). Previously, subDay() turned end_date into yesterday and returned 0!
    $report = $service->generate([
        'start_date' => $todayStr,
        'end_date' => $todayStr,
    ]);

    expect($report['reports'])->toHaveCount(1);
    $po = $report['reports'][0];
    expect($po['supplier'])->toBe('Supplier Utama')
        ->and(str_replace(',', '', $po['subtotal_total_initial_price']))->toBe('50000')
        ->and(str_replace(',', '', $po['subtotal_total_selling_price']))->toBe('75000')
        ->and(str_replace(',', '', $report['footer']['grand_total_initial_price']))->toBe('50000')
        ->and(str_replace(',', '', $report['footer']['grand_total_selling_price']))->toBe('75000');
});

test('all report pages render in livewire', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $user = User::first();
    actingAs($user);

    Livewire::test(SellingReportPage::class)->assertOk();
    Livewire::test(ProductReportPage::class)->assertOk();
    Livewire::test(CashierReportPage::class)->assertOk();
    Livewire::test(PurchasingReportPage::class)->assertOk();
});

