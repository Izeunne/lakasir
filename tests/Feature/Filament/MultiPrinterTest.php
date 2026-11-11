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
        ->toHaveKey('virtual_simulator');

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

test('printer management page renders and can create default restaurant presets', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $user = User::first();
    actingAs($user);

    Category::create(['name' => 'Makanan Utama']);
    Category::create(['name' => 'Minuman']);

    Livewire::test(PrinterPage::class)
        ->assertOk()
        ->call('createDefaultRestaurantPrinters')
        ->assertSee('Printer Kasir')
        ->assertSee('Printer Bar')
        ->assertSee('Printer Dapur');

    expect(Printer::count())->toBe(3);
});

