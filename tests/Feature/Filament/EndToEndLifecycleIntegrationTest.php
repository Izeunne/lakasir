<?php

use App\Events\RecalculateEvent;
use App\Filament\Tenant\Pages\Cashier;
use App\Models\Tenants\Category;
use App\Models\Tenants\Member;
use App\Models\Tenants\PaymentMethod;
use App\Models\Tenants\PriceUnit;
use App\Models\Tenants\Printer;
use App\Models\Tenants\PrintQueue;
use App\Models\Tenants\Product;
use App\Models\Tenants\ProductVariation;
use App\Models\Tenants\ProductVariationOption;
use App\Models\Tenants\Purchasing;
use App\Models\Tenants\Selling;
use App\Models\Tenants\SellingDetail;
use App\Models\Tenants\Stock;
use App\Models\Tenants\Supplier;
use App\Models\Tenants\Table;
use App\Models\Tenants\User;
use App\Models\Tenants\Voucher;
use App\Services\Tenants\CashierReportService;
use App\Services\Tenants\ProductReportService;
use App\Services\Tenants\PurchasingReportService;
use App\Services\Tenants\SellingReportService;
use App\Services\Tenants\SellingService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\RefreshDatabaseWithTenant;

use function Pest\Laravel\actingAs;

uses(RefreshDatabaseWithTenant::class);

test('end-to-end master data setup supports stockable, non-stock, price units, and variations', function () {
    $user = User::first();
    actingAs($user);

    // 1. Categories
    $catMakanan = Category::create(['name' => 'Makanan Utama']);
    $catMinuman = Category::create(['name' => 'Minuman Dingin']);

    // 2. Stockable Product
    $prodStock = Product::create([
        'name' => 'Kopi Bubuk Robusta 250g',
        'sku' => 'KPB-250',
        'category_id' => $catMinuman->id,
        'initial_price' => 12000,
        'selling_price' => 25000,
        'type' => 'product',
        'is_non_stock' => false,
        'show' => true,
        'unit' => 'Bks',
    ]);

    // Initial stock
    Stock::create([
        'product_id' => $prodStock->id,
        'stock' => 50,
        'init_stock' => 50,
        'initial_price' => 12000,
        'total_initial_price' => 600000,
        'selling_price' => 25000,
        'total_selling_price' => 1250000,
        'type' => 'in',
        'is_ready' => 1,
        'date' => now()->format('Y-m-d'),
    ]);

    RecalculateEvent::dispatch(collect([$prodStock]), []);
    expect($prodStock->fresh()->stock)->toEqual(50);

    // 3. Non-Stock / Made-to-order Product
    $prodNonStock = Product::create([
        'name' => 'Nasi Goreng Spesial Telur',
        'sku' => 'NGS-01',
        'category_id' => $catMakanan->id,
        'initial_price' => 15000,
        'selling_price' => 30000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
        'unit' => 'Porsi',
    ]);

    expect($prodNonStock->is_non_stock)->toBeTrue();

    // 4. Product with Price Units (Multi-UOM: Pcs vs Dus)
    $prodMultiUom = Product::create([
        'name' => 'Air Mineral 600ml',
        'sku' => 'AM-600',
        'category_id' => $catMinuman->id,
        'initial_price' => 2000,
        'selling_price' => 4000,
        'type' => 'product',
        'is_non_stock' => false,
        'show' => true,
        'unit' => 'Botol',
    ]);

    Stock::create([
        'product_id' => $prodMultiUom->id,
        'stock' => 120,
        'init_stock' => 120,
        'initial_price' => 2000,
        'total_initial_price' => 240000,
        'selling_price' => 4000,
        'total_selling_price' => 480000,
        'type' => 'in',
        'is_ready' => 1,
        'date' => now()->format('Y-m-d'),
    ]);

    RecalculateEvent::dispatch(collect([$prodMultiUom]), []);

    $priceUnitDus = PriceUnit::create([
        'product_id' => $prodMultiUom->id,
        'name' => 'Dus (24 Botol)',
        'stock' => 24, // 1 Dus = 24 Botol
        'selling_price' => 80000,
    ]);

    expect($prodMultiUom->fresh()->stock)->toEqual(120)
        ->and($priceUnitDus->stock)->toEqual(24);

    // 5. Product with Variations (Required & Optional)
    $prodVariations = Product::create([
        'name' => 'Mie Goreng Seafood Spesial',
        'sku' => 'MGS-01',
        'category_id' => $catMakanan->id,
        'initial_price' => 16000,
        'selling_price' => 28000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $varPedas = ProductVariation::create([
        'product_id' => $prodVariations->id,
        'name' => 'Level Pedas',
        'type' => 'single',
        'is_required' => true,
    ]);

    $optSedang = ProductVariationOption::create([
        'product_variation_id' => $varPedas->id,
        'name' => 'Pedas Sedang',
        'price_adjustment' => 0,
    ]);

    $optGila = ProductVariationOption::create([
        'product_variation_id' => $varPedas->id,
        'name' => 'Pedas Gila (+Rp 3.000)',
        'price_adjustment' => 3000,
    ]);

    expect($prodVariations->variations)->toHaveCount(1)
        ->and($prodVariations->variations->first()->options)->toHaveCount(2);
});

test('purchasing increases inventory stock and logs accurately into purchasing report', function () {
    $user = User::first();
    actingAs($user);

    $cat = Category::create(['name' => 'Bahan Baku']);
    $supplier = Supplier::create([
        'name' => 'PT Sumber Pangan Sejahtera',
        'address' => 'Kawasan Industri Blok B',
        'phone_number' => '081122334455',
    ]);

    $product = Product::create([
        'name' => 'Beras Pandan Wangi 5kg',
        'sku' => 'BPW-05',
        'category_id' => $cat->id,
        'initial_price' => 60000,
        'selling_price' => 85000,
        'type' => 'product',
        'is_non_stock' => false,
        'show' => true,
        'unit' => 'Karung',
    ]);

    // Initial stock = 10
    Stock::create([
        'product_id' => $product->id,
        'stock' => 10,
        'init_stock' => 10,
        'initial_price' => 60000,
        'total_initial_price' => 600000,
        'selling_price' => 85000,
        'total_selling_price' => 850000,
        'type' => 'in',
        'is_ready' => 1,
        'date' => now()->format('Y-m-d'),
    ]);

    RecalculateEvent::dispatch(collect([$product]), []);
    expect($product->fresh()->stock)->toEqual(10);

    // Purchasing 15 additional sacks
    $todayStr = now()->format('Y-m-d');
    $purchasing = Purchasing::create([
        'number' => 'PO-BPW-001',
        'supplier_id' => $supplier->id,
        'date' => $todayStr,
        'total_initial_price' => 900000, // 15 * 60,000
        'total_selling_price' => 1275000, // 15 * 85,000
        'user_id' => $user->id,
    ]);

    $newStock = Stock::create([
        'product_id' => $product->id,
        'purchasing_id' => $purchasing->id,
        'supplier_id' => $supplier->id,
        'date' => $todayStr,
        'init_stock' => 15,
        'stock' => 15,
        'initial_price' => 60000,
        'total_initial_price' => 900000,
        'selling_price' => 85000,
        'total_selling_price' => 1275000,
        'type' => 'in',
        'is_ready' => 1,
    ]);

    RecalculateEvent::dispatch(collect([$product]), []);

    // Total stock should now be 25
    expect($product->fresh()->stock)->toEqual(25);

    // Verify Purchasing Report
    $service = new PurchasingReportService();
    $report = $service->generate([
        'start_date' => $todayStr,
        'end_date' => $todayStr,
    ]);

    expect($report['reports'])->toHaveCount(1);
    $poData = $report['reports'][0];
    expect($poData['supplier'])->toBe('PT Sumber Pangan Sejahtera')
        ->and(str_replace(',', '', $poData['subtotal_total_initial_price']))->toBe('900000')
        ->and(str_replace(',', '', $poData['subtotal_total_selling_price']))->toBe('1275000');
});

test('cashier checkout handles mixed stock types, price units, discounts, multi-printers and deducts inventory', function () {
    $user = User::first();
    actingAs($user);

    $catMakanan = Category::create(['name' => 'Makanan Utama']);
    $catMinuman = Category::create(['name' => 'Minuman']);

    // Setup Printers: Kasir (all), Dapur (makanan), Bar (minuman)
    Printer::create([
        'name' => 'Printer Kasir',
        'driver' => 'bluetooth',
        'device_id' => 'BT-KASIR',
        'connection_type' => 'bluetooth',
        'paper_width' => '58mm',
        'print_all_categories' => true,
        'category_ids' => [],
        'show_price' => true,
        'is_active' => true,
    ]);

    Printer::create([
        'name' => 'Printer Dapur',
        'driver' => 'lan',
        'device_id' => 'LAN-DAPUR',
        'connection_type' => 'lan',
        'paper_width' => '80mm',
        'print_all_categories' => false,
        'category_ids' => [$catMakanan->id],
        'show_price' => false,
        'is_active' => true,
    ]);

    Printer::create([
        'name' => 'Printer Bar',
        'driver' => 'lan',
        'device_id' => 'LAN-BAR',
        'connection_type' => 'lan',
        'paper_width' => '58mm',
        'print_all_categories' => false,
        'category_ids' => [$catMinuman->id],
        'show_price' => false,
        'is_active' => true,
    ]);

    // Product 1: Stockable (Teh Kotak) -> 50 in stock
    $tehKotak = Product::create([
        'name' => 'Teh Kotak 200ml',
        'sku' => 'TK-200',
        'category_id' => $catMinuman->id,
        'initial_price' => 3000,
        'selling_price' => 6000,
        'type' => 'product',
        'is_non_stock' => false,
        'show' => true,
    ]);
    Stock::create([
        'product_id' => $tehKotak->id,
        'stock' => 50,
        'init_stock' => 50,
        'initial_price' => 3000,
        'type' => 'in',
        'is_ready' => 1,
        'date' => now()->format('Y-m-d'),
    ]);
    RecalculateEvent::dispatch(collect([$tehKotak]), []);

    // Product 2: Non-Stock (Ayam Bakar Madu)
    $ayamBakar = Product::create([
        'name' => 'Ayam Bakar Madu',
        'sku' => 'ABM-01',
        'category_id' => $catMakanan->id,
        'initial_price' => 18000,
        'selling_price' => 32000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    // Product 3: Multi-Unit UOM (Soda Can: 1 Pcs = 8,000; 1 Pak 6 pcs = 42,000)
    $soda = Product::create([
        'name' => 'Soda Kaleng 330ml',
        'sku' => 'SDK-330',
        'category_id' => $catMinuman->id,
        'initial_price' => 5000,
        'selling_price' => 8000,
        'type' => 'product',
        'is_non_stock' => false,
        'show' => true,
    ]);
    Stock::create([
        'product_id' => $soda->id,
        'stock' => 60,
        'init_stock' => 60,
        'initial_price' => 5000,
        'type' => 'in',
        'is_ready' => 1,
        'date' => now()->format('Y-m-d'),
    ]);
    RecalculateEvent::dispatch(collect([$soda]), []);

    $priceUnitPak = PriceUnit::create([
        'product_id' => $soda->id,
        'name' => 'Pak (6 Kaleng)',
        'stock' => 6, // 1 Pak = 6 Kaleng
        'selling_price' => 42000,
    ]);

    // Voucher: Rp 10.000 flat discount for order >= Rp 50.000
    $voucher = Voucher::create([
        'code' => 'DISC10K',
        'type' => 'flat',
        'nominal' => 10000,
        'minimal_buying' => 50000,
        'start_date' => now()->subDay()->format('Y-m-d'),
        'expired' => now()->addDays(5)->format('Y-m-d'),
        'kuota' => 10,
    ]);

    $table = Table::create(['number' => 'Meja VIP 01']);
    $member = Member::create(['name' => 'Budi Santoso', 'code' => 'MBR-001', 'phone_number' => '081299990001']);
    $paymentMethod = PaymentMethod::first() ?? PaymentMethod::create(['name' => 'Cash']);

    // Perform Checkout via SellingService
    $sellingService = app(SellingService::class);

    $checkoutPayload = [
        'user_id' => $user->id,
        'member_id' => $member->id,
        'table_id' => $table->id,
        'order_type' => 'dine_in',
        'payment_method_id' => $paymentMethod->id,
        'voucher' => 'DISC10K',
        'payed_money' => 100000,
        'friend_price' => false,
        'tax' => 10, // 10% tax
        'products' => [
            // 2 Teh Kotak @ 6,000 = 12,000 with 2,000 item discount
            [
                'product_id' => $tehKotak->id,
                'qty' => 2,
                'price' => 12000,
                'discount_price' => 2000,
            ],
            // 1 Ayam Bakar @ 32,000
            [
                'product_id' => $ayamBakar->id,
                'qty' => 1,
                'price' => 32000,
                'discount_price' => 0,
            ],
            // 1 Pak Soda (Price Unit: 6 kaleng) @ 42,000
            [
                'product_id' => $soda->id,
                'qty' => 1,
                'price_unit_id' => $priceUnitPak->id,
                'discount_price' => 0,
            ],
        ],
    ];

    $mapped = array_merge($checkoutPayload, $sellingService->mapProductRequest($checkoutPayload));
    $selling = $sellingService->create($mapped);

    // 1. Verify Selling Calculations:
    // Subtotal before tax: 12,000 + 32,000 + 42,000 = 86,000
    // Tax 10%: 8,600 -> Total with tax: 94,600
    // Item discount: 2,000
    // Voucher discount: 10,000
    // Total payable: 94,600 - 2,000 - 10,000 = 82,600
    // Money changes: 100,000 - 82,600 = 17,400
    expect($selling->total_price)->toEqual(94600)
        ->and($selling->tax_price)->toEqual(8600)
        ->and($selling->total_discount_per_item)->toEqual(2000)
        ->and($selling->discount_price)->toEqual(10000)
        ->and($selling->money_changes)->toEqual(17400);

    // 2. Verify Inventory Deductions:
    // Teh Kotak: 50 - 2 = 48
    expect($tehKotak->fresh()->stock)->toEqual(48);

    // Soda: 60 - 6 (1 Pak) = 54
    expect($soda->fresh()->stock)->toEqual(54);

    // Ayam Bakar (non-stock): unaffected
    expect($ayamBakar->fresh()->is_non_stock)->toBeTruthy();

    // 3. Verify Voucher Quota: reduced from 10 to 9
    expect($voucher->fresh()->kuota)->toBe(9);

    // 4. Verify Multi-Printer Queues Created:
    // Kasir, Bar, and Dapur all received print jobs!
    $queues = PrintQueue::where('selling_id', $selling->id)->get();
    expect($queues)->toHaveCount(3);

    $kasirQueue = $queues->firstWhere('target_printer', 'BT-KASIR');
    $barQueue = $queues->firstWhere('target_printer', 'LAN-BAR');
    $dapurQueue = $queues->firstWhere('target_printer', 'LAN-DAPUR');

    expect($kasirQueue)->not->toBeNull()
        ->and($kasirQueue->payload['show_price'])->toBeTrue()
        ->and($barQueue)->not->toBeNull()
        ->and($barQueue->payload['show_price'])->toBeFalse()
        ->and($dapurQueue)->not->toBeNull()
        ->and($dapurQueue->payload['show_price'])->toBeFalse();

    // Bar queue only contains Minuman items (Teh Kotak and Soda), NOT Ayam Bakar!
    $barItems = collect($barQueue->ticket['items'])->pluck('name')->toArray();
    expect($barItems)->toContain('Teh Kotak 200ml')
        ->and($barItems)->not->toContain('Ayam Bakar Madu');

    // Dapur queue only contains Makanan items (Ayam Bakar Madu)
    $dapurItems = collect($dapurQueue->ticket['items'])->pluck('name')->toArray();
    expect($dapurItems)->toContain('Ayam Bakar Madu')
        ->and($dapurItems)->not->toContain('Teh Kotak 200ml');
});

test('worst case: validation halts checkout when stock is insufficient', function () {
    $user = User::first();
    actingAs($user);

    $cat = Category::create(['name' => 'Snack']);
    $snack = Product::create([
        'name' => 'Keripik Singkong Balado',
        'sku' => 'KSB-01',
        'category_id' => $cat->id,
        'initial_price' => 5000,
        'selling_price' => 10000,
        'type' => 'product',
        'is_non_stock' => false,
        'show' => true,
    ]);

    // Stock only 3
    Stock::create([
        'product_id' => $snack->id,
        'stock' => 3,
        'init_stock' => 3,
        'type' => 'in',
        'is_ready' => 1,
        'date' => now()->format('Y-m-d'),
    ]);
    RecalculateEvent::dispatch(collect([$snack]), []);

    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    // Front-line defense: Livewire Cashier prevents adding more than 3 to cart
    Livewire::test(Cashier::class)
        ->call('addCart', $snack, ['amount' => 5])
        ->assertNotified(__('Stock is out'));

    // Backend defense: API endpoint validation strictly rejects order exceeding stock
    actingAs($user)->postJson('/api/transaction/selling', [
        'payed_money' => 50000,
        'friend_price' => false,
        'products' => [
            ['product_id' => $snack->id, 'qty' => 5],
        ],
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['products.0.qty']);

    // Stock remains untouched at 3
    expect($snack->fresh()->stock)->toEqual(3);
});

test('worst case: expired or below minimum spending vouchers are rejected', function () {
    $user = User::first();
    actingAs($user);

    // Expired voucher
    $expiredVoucher = Voucher::create([
        'code' => 'EXPIRED2020',
        'type' => 'flat',
        'nominal' => 5000,
        'minimal_buying' => 10000,
        'start_date' => '2020-01-01',
        'expired' => '2020-12-31',
        'kuota' => 10,
    ]);

    // High minimum spending voucher
    $highMinVoucher = Voucher::create([
        'code' => 'MIN100K',
        'type' => 'flat',
        'nominal' => 20000,
        'minimal_buying' => 100000,
        'start_date' => now()->subDay()->format('Y-m-d'),
        'expired' => now()->addDays(5)->format('Y-m-d'),
        'kuota' => 10,
    ]);

    $voucherService = new \App\Services\VoucherService();

    // Test 1: Expired voucher application fails
    $appliedExpired = $voucherService->applyable('EXPIRED2020', 50000);
    expect($appliedExpired)->toBeNull();

    // Test 2: Spending below minimum fails
    $appliedBelowMin = $voucherService->applyable('MIN100K', 40000);
    expect($appliedBelowMin)->toBeNull();

    // Test 3: Spending meeting minimum succeeds
    $appliedValid = $voucherService->applyable('MIN100K', 150000);
    expect($appliedValid)->not->toBeNull()
        ->and($appliedValid->calculate())->toEqual(20000);
});

test('worst case: credit/debt payment without member fails validation', function () {
    $user = User::first();
    actingAs($user);

    $cat = Category::create(['name' => 'Makanan']);
    $product = Product::create([
        'name' => 'Sop Ayam',
        'category_id' => $cat->id,
        'selling_price' => 20000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $creditMethod = PaymentMethod::create([
        'name' => 'Kasbon / Hutang',
        'is_cash' => false,
        'is_credit' => true,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    Livewire::test(Cashier::class)
        ->call('addCart', $product, ['amount' => 1])
        ->call('setOrderType', 'take_away')
        ->set('cartDetail.payment_method_id', $creditMethod->id)
        ->set('cartDetail.member_id', null) // NO MEMBER
        ->call('proceedThePayment', app(SellingService::class))
        ->assertHasErrors(['member_id']);
});

test('worst case: cash payment with insufficient payed_money fails validation', function () {
    $user = User::first();
    actingAs($user);

    $cat = Category::create(['name' => 'Makanan']);
    $product = Product::create([
        'name' => 'Nasi Rames',
        'category_id' => $cat->id,
        'selling_price' => 25000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $cashMethod = PaymentMethod::where('name', 'Cash')->first() ?? PaymentMethod::create(['name' => 'Cash', 'is_cash' => true]);

    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    Livewire::test(Cashier::class)
        ->call('addCart', $product, ['amount' => 1])
        ->call('setOrderType', 'dine_in')
        ->set('cartDetail.payment_method_id', $cashMethod->id)
        ->set('cartDetail.payed_money', 10000) // 10,000 is less than 25,000
        ->call('proceedThePayment', app(SellingService::class))
        ->assertHasErrors(['payed_money']);
});

test('full reconciliation: reports remain consistent and resilient even if products are deleted after transaction', function () {
    $user = User::first();
    actingAs($user);

    $cat = Category::create(['name' => 'Seasonal Products']);
    $productToKeep = Product::create([
        'name' => 'Es Jeruk Segar',
        'sku' => 'EJS-01',
        'category_id' => $cat->id,
        'initial_price' => 4000,
        'selling_price' => 10000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $productToDelete = Product::create([
        'name' => 'Menu Spesial Terbatas',
        'sku' => 'MST-99',
        'category_id' => $cat->id,
        'initial_price' => 20000,
        'selling_price' => 50000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $paymentMethod = PaymentMethod::first() ?? PaymentMethod::create(['name' => 'Cash']);

    $selling = Selling::create([
        'user_id' => $user->id,
        'code' => 'SELL-RECON-001',
        'date' => now()->format('Y-m-d H:i:s'),
        'payment_method_id' => $paymentMethod->id,
        'total_price' => 60000,
        'total_cost' => 24000,
        'total_qty' => 2,
        'total_discount_per_item' => 0,
        'discount_price' => 0,
        'payed_money' => 60000,
        'money_changes' => 0,
    ]);

    SellingDetail::create([
        'selling_id' => $selling->id,
        'product_id' => $productToKeep->id,
        'qty' => 1,
        'price' => 10000,
        'cost' => 4000,
        'discount_price' => 0,
    ]);

    SellingDetail::create([
        'selling_id' => $selling->id,
        'product_id' => $productToDelete->id,
        'qty' => 1,
        'price' => 50000,
        'cost' => 20000,
        'discount_price' => 0,
    ]);

    // Simulating worst-case data maintenance: Product is deleted from database
    $productToDelete->delete();

    $todayStr = now()->format('Y-m-d');

    // 1. Selling Report must not crash, and must gracefully handle the deleted product
    $sellingReportService = new SellingReportService();
    $sellingReport = $sellingReportService->generate([
        'start_date' => $todayStr,
        'end_date' => $todayStr,
    ]);

    expect($sellingReport['reports'])->toHaveCount(2);
    $deletedRow = collect($sellingReport['reports'])->firstWhere('code', $selling->code);
    expect($deletedRow)->not->toBeNull()
        ->and(str_replace(',', '', $sellingReport['footer']['total_gross']))->toBe('60000')
        ->and(str_replace(',', '', $sellingReport['footer']['total_cost']))->toBe('24000')
        ->and(str_replace(',', '', $sellingReport['footer']['total_gross_profit']))->toBe('36000');

    // 2. Cashier Report must not crash and must match selling total
    $cashierReportService = new CashierReportService();
    $cashierReport = $cashierReportService->generate([
        'start_date' => $todayStr,
        'end_date' => $todayStr,
    ]);

    expect($cashierReport['reports'])->toHaveCount(1);
    $tx = $cashierReport['reports'][0];
    expect(str_replace(',', '', $tx['total']['grand_total']))->toBe('60000');
});
