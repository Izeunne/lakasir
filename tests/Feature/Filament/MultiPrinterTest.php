<?php

use App\Filament\Tenant\Pages\Printer as PrinterPage;
use App\Models\Tenants\Category;
use App\Models\Tenants\MobilePrinter;
use App\Models\Tenants\PaymentMethod;
use App\Models\Tenants\Printer;
use App\Models\Tenants\PrintQueue;
use App\Models\Tenants\Product;
use App\Models\Tenants\Selling;
use App\Models\Tenants\SellingDetail;
use App\Models\Tenants\User;
use App\Services\PrintBridgeService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\RefreshDatabaseWithTenant;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabaseWithTenant::class);

test('mobile app can sync connected printers and only connected printers appear as options', function () {
    $token = config('services.print_bridge.token', env('PRINT_BRIDGE_TOKEN', 'lakasir-print-token-2026'));

    $response = postJson('/api/print-queue/printers/sync?token=' . $token, [
        'outlet_id' => 1,
        'device_model' => 'Samsung Tab POS',
        'printers' => [
            [
                'device_id' => 'BT-001',
                'device_name' => 'Printer Kasir Bluetooth',
                'connection_type' => 'bluetooth',
                'address' => 'AA:BB:CC:11:22:33',
                'paper_width' => '58mm',
                'is_connected' => true,
            ],
            [
                'device_id' => 'LAN-002',
                'device_name' => 'Printer Dapur LAN',
                'connection_type' => 'lan',
                'address' => '192.168.1.50:9100',
                'paper_width' => '80mm',
                'is_connected' => true,
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('connected_count', 2);

    expect(MobilePrinter::where('is_connected', true)->count())->toBe(2);

    $options = PrinterPage::getConnectedMobilePrinterOptions();
    expect($options)->toHaveKey('BT-001')
        ->toHaveKey('LAN-002')
        ->not->toHaveKey('virtual_simulator');

    // Now sync again with only 1 connected printer; the other should automatically mark disconnected
    postJson('/api/print-queue/printers/sync?token=' . $token, [
        'outlet_id' => 1,
        'printers' => [
            [
                'device_id' => 'BT-001',
                'device_name' => 'Printer Kasir Bluetooth',
                'connection_type' => 'bluetooth',
                'is_connected' => true,
            ],
        ],
    ])->assertOk();

    $updatedOptions = PrinterPage::getConnectedMobilePrinterOptions();
    expect($updatedOptions)->toHaveKey('BT-001')
        ->not->toHaveKey('LAN-002');
});

test('multi-printer routes order items by category and respects show_price flag', function () {
    $user = User::first();
    actingAs($user);

    $catMakanan = Category::create(['name' => 'Makanan Utama']);
    $catMinuman = Category::create(['name' => 'Minuman']);

    $nasiGoreng = Product::create([
        'name' => 'Nasi Goreng Spesial',
        'category_id' => $catMakanan->id,
        'selling_price' => 30000,
        'initial_price' => 18000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $esTeh = Product::create([
        'name' => 'Es Teh Manis',
        'category_id' => $catMinuman->id,
        'selling_price' => 8000,
        'initial_price' => 3000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    // Configure 3 printers: Kasir (All + Price), Bar (Minuman + No Price), Dapur (Makanan + No Price)
    $printerKasir = Printer::create([
        'name' => 'Printer Kasir',
        'driver' => 'bluetooth',
        'device_id' => 'BT-KASIR',
        'device_name' => 'BT Kasir',
        'connection_type' => 'bluetooth',
        'paper_width' => '58mm',
        'print_all_categories' => true,
        'category_ids' => [],
        'show_price' => true,
        'copies' => 1,
        'is_active' => true,
    ]);

    $printerBar = Printer::create([
        'name' => 'Printer Bar',
        'driver' => 'lan',
        'device_id' => 'LAN-BAR',
        'device_name' => 'LAN Bar',
        'connection_type' => 'lan',
        'paper_width' => '58mm',
        'print_all_categories' => false,
        'category_ids' => [$catMinuman->id],
        'show_price' => false,
        'copies' => 1,
        'is_active' => true,
    ]);

    $printerDapur = Printer::create([
        'name' => 'Printer Dapur',
        'driver' => 'lan',
        'device_id' => 'LAN-DAPUR',
        'device_name' => 'LAN Dapur',
        'connection_type' => 'lan',
        'paper_width' => '80mm',
        'print_all_categories' => false,
        'category_ids' => [$catMakanan->id],
        'show_price' => false,
        'copies' => 1,
        'is_active' => true,
    ]);

    $paymentMethod = PaymentMethod::first() ?? PaymentMethod::create(['name' => 'Cash']);

    // Case 1: Order with both Makanan and Minuman -> all 3 printers receive their specific jobs
    $selling = Selling::create([
        'user_id' => $user->id,
        'code' => 'INV-MULTI-001',
        'date' => now()->toDateTimeString(),
        'payment_method_id' => $paymentMethod->id,
        'total_price' => 38000,
        'grand_total_price' => 38000,
        'payed_money' => 50000,
        'money_changes' => 12000,
    ]);

    SellingDetail::create([
        'selling_id' => $selling->id,
        'product_id' => $nasiGoreng->id,
        'qty' => 1,
        'price' => 30000,
        'cost' => 18000,
        'note' => 'Pedas sedang',
    ]);

    SellingDetail::create([
        'selling_id' => $selling->id,
        'product_id' => $esTeh->id,
        'qty' => 2,
        'price' => 16000,
        'cost' => 6000,
        'note' => 'Less ice',
    ]);

    PrintQueue::query()->delete();
    $jobs = PrintBridgeService::queueSellingPrints($selling);

    expect($jobs)->toHaveCount(3);

    $kasirJob = collect($jobs)->firstWhere('target_printer', 'BT-KASIR');
    $barJob = collect($jobs)->firstWhere('target_printer', 'LAN-BAR');
    $dapurJob = collect($jobs)->firstWhere('target_printer', 'LAN-DAPUR');

    // Verify Kasir Job: has both items and shows price
    expect($kasirJob)->not->toBeNull()
        ->and($kasirJob->type)->toBe('receipt')
        ->and($kasirJob->payload['show_price'])->toBeTrue()
        ->and($kasirJob->layout['items'])->toHaveCount(2)
        ->and($kasirJob->layout['items'][0]['detail_right'])->toBe('30.000');

    // Verify Bar Job: has ONLY Es Teh Manis and NO prices
    expect($barJob)->not->toBeNull()
        ->and($barJob->type)->toBe('kitchen_ticket')
        ->and($barJob->payload['show_price'])->toBeFalse()
        ->and($barJob->ticket['items'])->toHaveCount(1)
        ->and($barJob->ticket['items'][0]['name'])->toBe('Es Teh Manis')
        ->and($barJob->layout['items'][0]['detail_right'])->toBe('');

    // Verify Dapur Job: has ONLY Nasi Goreng Spesial and NO prices
    expect($dapurJob)->not->toBeNull()
        ->and($dapurJob->type)->toBe('kitchen_ticket')
        ->and($dapurJob->payload['show_price'])->toBeFalse()
        ->and($dapurJob->ticket['items'])->toHaveCount(1)
        ->and($dapurJob->ticket['items'][0]['name'])->toBe('Nasi Goreng Spesial')
        ->and($dapurJob->layout['items'][0]['detail_right'])->toBe('');

    // Case 2: Order with ONLY Makanan (no drinks) -> Bar printer must be skipped automatically!
    $sellingFoodOnly = Selling::create([
        'user_id' => $user->id,
        'code' => 'INV-FOOD-ONLY',
        'date' => now()->toDateTimeString(),
        'payment_method_id' => $paymentMethod->id,
        'total_price' => 30000,
        'grand_total_price' => 30000,
        'payed_money' => 30000,
        'money_changes' => 0,
    ]);

    SellingDetail::create([
        'selling_id' => $sellingFoodOnly->id,
        'product_id' => $nasiGoreng->id,
        'qty' => 1,
        'price' => 30000,
        'cost' => 18000,
    ]);

    $foodOnlyJobs = PrintBridgeService::queueSellingPrints($sellingFoodOnly);

    expect($foodOnlyJobs)->toHaveCount(2);
    expect(collect($foodOnlyJobs)->pluck('target_printer')->toArray())
        ->toContain('BT-KASIR')
        ->toContain('LAN-DAPUR')
        ->not->toContain('LAN-BAR');
});

test('printer management page renders and displays configured printers', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $user = User::first();
    actingAs($user);

    Printer::create([
        'name' => 'Printer Kasir Utama',
        'driver' => 'bluetooth',
        'device_id' => 'BT-TEST-01',
        'device_name' => 'Thermal Test',
        'connection_type' => 'bluetooth',
        'paper_width' => '58mm',
        'print_all_categories' => true,
        'category_ids' => [],
        'show_price' => true,
        'is_active' => true,
    ]);

    Livewire::test(PrinterPage::class)
        ->assertOk()
        ->assertSee('Printer Kasir Utama')
        ->assertDontSee('Buat Preset: Kasir + Bar + Dapur');

    expect(Printer::count())->toBeGreaterThanOrEqual(1);
});

test('cashier checkout automatically generates multi-printer print queue jobs', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $user = User::first();
    actingAs($user);

    $catMakanan = Category::create(['name' => 'Makanan Utama']);
    $catMinuman = Category::create(['name' => 'Minuman']);

    $food = Product::create([
        'name' => 'Bebek Goreng Sambal Korek',
        'category_id' => $catMakanan->id,
        'selling_price' => 42000,
        'initial_price' => 25000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $drink = Product::create([
        'name' => 'Es Jeruk Peras',
        'category_id' => $catMinuman->id,
        'selling_price' => 10000,
        'initial_price' => 4000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    Printer::create([
        'name' => 'Printer Kasir',
        'driver' => 'bluetooth',
        'device_id' => 'BT-KASIR',
        'paper_width' => '58mm',
        'print_all_categories' => true,
        'category_ids' => [],
        'show_price' => true,
        'is_active' => true,
    ]);

    Printer::create([
        'name' => 'Printer Bar',
        'driver' => 'lan',
        'device_id' => 'LAN-BAR',
        'paper_width' => '58mm',
        'print_all_categories' => false,
        'category_ids' => [$catMinuman->id],
        'show_price' => false,
        'is_active' => true,
    ]);

    Printer::create([
        'name' => 'Printer Dapur',
        'driver' => 'lan',
        'device_id' => 'LAN-DAPUR',
        'paper_width' => '80mm',
        'print_all_categories' => false,
        'category_ids' => [$catMakanan->id],
        'show_price' => false,
        'is_active' => true,
    ]);

    $paymentMethod = PaymentMethod::first() ?? PaymentMethod::create(['name' => 'Cash', 'is_cash' => true]);

    PrintQueue::query()->delete();

    // Add items to cart in Cashier Livewire component and checkout
    Livewire::test(\App\Filament\Tenant\Pages\Cashier::class)
        ->call('addCart', $food)
        ->call('addCart', $drink)
        ->set('orderType', 'Dine In')
        ->set('cartDetail.payment_method_id', $paymentMethod->id)
        ->set('cartDetail.payed_money', 60000)
        ->call('proceedThePayment')
        ->assertDispatched('selling-created');

    // Verify print_queue has 3 distinct jobs: Kasir, Bar, and Dapur
    $queuedJobs = PrintQueue::where('status', 'queued')->get();
    expect($queuedJobs)->toHaveCount(3);

    $kasirJob = $queuedJobs->firstWhere('target_printer', 'BT-KASIR');
    $barJob = $queuedJobs->firstWhere('target_printer', 'LAN-BAR');
    $dapurJob = $queuedJobs->firstWhere('target_printer', 'LAN-DAPUR');

    expect($kasirJob)->not->toBeNull()
        ->and($kasirJob->payload['show_price'])->toBeTrue()
        ->and($kasirJob->layout['items'])->toHaveCount(2);

    expect($barJob)->not->toBeNull()
        ->and($barJob->payload['show_price'])->toBeFalse()
        ->and($barJob->ticket['items'])->toHaveCount(1)
        ->and($barJob->ticket['items'][0]['name'])->toBe('Es Jeruk Peras');

    expect($dapurJob)->not->toBeNull()
        ->and($dapurJob->payload['show_price'])->toBeFalse()
        ->and($dapurJob->ticket['items'])->toHaveCount(1)
        ->and($dapurJob->ticket['items'][0]['name'])->toBe('Bebek Goreng Sambal Korek');
});

test('cashier printer with copies=2 prints customer copy and merchant copy, and show_header suppresses company header', function () {
    $user = User::first();
    actingAs($user);

    \App\Models\Tenants\About::updateOrCreate(['id' => 1], [
        'shop_name' => 'Resto Berkah Utama',
        'shop_location' => 'Jl. Merdeka No. 10',
        'phone' => '081299998888',
        'email' => 'contact@resto.com',
        'website' => 'https://resto.com',
        'npwp' => '01.234.567.8-901.000',
    ]);

    Printer::query()->delete();
    PrintQueue::query()->delete();

    // 1 Printer dengan copies = 2 (1 customer copy, 1 merchant copy)
    $printerKasir = Printer::create([
        'name' => 'Printer Kasir 2 Rangkap',
        'driver' => 'bluetooth',
        'device_id' => 'BT-DUAL-COPY',
        'paper_width' => '58mm',
        'print_all_categories' => true,
        'category_ids' => [],
        'show_price' => true,
        'show_header' => true,
        'copies' => 2,
        'is_active' => true,
    ]);

    $category = Category::create(['name' => 'Makanan']);
    $product = Product::create([
        'name' => 'Paket Ayam Geprek',
        'category_id' => $category->id,
        'selling_price' => 25000,
        'initial_price' => 15000,
        'type' => 'product',
        'is_non_stock' => true,
        'show' => true,
    ]);

    $paymentMethod = PaymentMethod::first() ?? PaymentMethod::create(['name' => 'Cash', 'is_cash' => true]);

    $selling = Selling::create([
        'user_id' => $user->id,
        'code' => 'INV-COPY-TEST',
        'date' => now()->toDateTimeString(),
        'payment_method_id' => $paymentMethod->id,
        'total_price' => 25000,
        'grand_total_price' => 25000,
        'payed_money' => 25000,
        'money_changes' => 0,
    ]);

    SellingDetail::create([
        'selling_id' => $selling->id,
        'product_id' => $product->id,
        'qty' => 1,
        'price' => 25000,
        'cost' => 15000,
    ]);

    $jobs = PrintBridgeService::queueSellingPrints($selling);

    // Harus menghasilkan 2 jobs karena copies = 2
    expect($jobs)->toHaveCount(2);

    $custCopy = $jobs[0];
    $storeCopy = $jobs[1];

    // Lembar 1 (Customer Copy): ada identitas perusahaan lengkap
    expect($custCopy->payload['copy'])->toBe(1)
        ->and($custCopy->layout['company'])->not->toBeNull()
        ->and($custCopy->layout['company']['name'])->toBe('Resto Berkah Utama')
        ->and($custCopy->layout['company']['phone'])->toBe('081299998888');

    // Lembar 2 (Salinan Toko): ditandai untuk disimpan dan tidak menduplikasi header logo/alamat
    expect($storeCopy->payload['copy'])->toBe(2)
        ->and($storeCopy->layout['company'])->toBeNull()
        ->and($storeCopy->layout['meta_rows'][0]['value'])->toBe('*** SALINAN TOKO / UNTUK DISIMPAN ***');

    // Uji jika show_header = false (misal printer backup kasir atau stasiun)
    $printerKasir->update(['show_header' => false, 'copies' => 1]);
    $noHeaderData = PrintBridgeService::buildFilteredReceiptData($selling, $selling->sellingDetails, $printerKasir);

    expect($noHeaderData['layout']['company'])->toBeNull();
});

