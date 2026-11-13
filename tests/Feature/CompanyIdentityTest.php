<?php

use App\Models\Tenants\About;
use App\Models\Tenants\PaymentMethod;
use App\Models\Tenants\Printer;
use App\Models\Tenants\Product;
use App\Models\Tenants\Selling;
use App\Models\Tenants\SellingDetail;
use App\Models\Tenants\User;
use App\Services\PrintBridgeService;
use App\Services\Tenants\AboutService;
use App\Services\Tenants\ReceiptPdfService;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshDatabaseWithTenant;

uses(RefreshDatabaseWithTenant::class);

beforeEach(function () {
    Storage::fake('public');

    $this->aboutService = app(AboutService::class);

    $this->aboutService->createOrUpdate([
        'shop_name' => 'PT Maju Bersama POS',
        'shop_location' => 'Jl. Jenderal Sudirman No. 88, Jakarta Pusat',
        'phone' => '081234567890',
        'email' => 'contact@majubersama.com',
        'website' => 'https://www.majubersama.com',
        'npwp' => '01.234.567.8-012.000',
        'business_type' => 'retail',
    ]);

    $this->product = Product::factory()->create([
        'selling_price' => 30000,
        'initial_price' => 20000,
    ]);

    $this->paymentMethod = PaymentMethod::firstOrCreate(
        ['name' => 'Cash'],
        ['is_cash' => true, 'is_debit' => false, 'is_credit' => false, 'is_wallet' => false]
    );

    $this->selling = Selling::create([
        'code' => 'TRX-CMP-001',
        'date' => now()->format('Y-m-d H:i:s'),
        'total_price' => 30000,
        'total_cost' => 20000,
        'total_qty' => 1,
        'grand_total_price' => 30000,
        'payed_money' => 50000,
        'money_changes' => 20000,
        'order_type' => 'take_away',
        'payment_method_id' => $this->paymentMethod->id,
        'user_id' => User::first()?->id ?? 1,
    ]);

    SellingDetail::create([
        'selling_id' => $this->selling->id,
        'product_id' => $this->product->id,
        'qty' => 1,
        'price' => 30000,
        'discount_price' => 0,
    ]);
});

test('company identity is stored and retrieved properly from abouts table', function () {
    $about = About::first();

    expect($about)->not->toBeNull();
    expect($about->shop_name)->toBe('PT Maju Bersama POS');
    expect($about->shop_location)->toBe('Jl. Jenderal Sudirman No. 88, Jakarta Pusat');
    expect($about->phone)->toBe('081234567890');
    expect($about->email)->toBe('contact@majubersama.com');
    expect($about->website)->toBe('https://www.majubersama.com');
    expect($about->npwp)->toBe('01.234.567.8-012.000');
});

test('about resource transforms company identity fields accurately', function () {
    $about = About::first();
    $resource = (new \App\Http\Resources\AboutResource($about))->toArray(request());

    expect($resource['shop_name'])->toBe('PT Maju Bersama POS');
    expect($resource['company_name'])->toBe('PT Maju Bersama POS');
    expect($resource['shop_location'])->toBe('Jl. Jenderal Sudirman No. 88, Jakarta Pusat');
    expect($resource['address'])->toBe('Jl. Jenderal Sudirman No. 88, Jakarta Pusat');
    expect($resource['phone'])->toBe('081234567890');
    expect($resource['email'])->toBe('contact@majubersama.com');
    expect($resource['website'])->toBe('https://www.majubersama.com');
    expect($resource['npwp'])->toBe('01.234.567.8-012.000');
});

test('receipt pdf service renders company identity in receipt view', function () {
    $pdfService = app(ReceiptPdfService::class);
    $pdf = $pdfService->generate($this->selling);

    $html = $pdf->getDomPDF()->getCanvas()->get_cpdf(); // ensure generated without error
    expect($pdf)->toBeInstanceOf(\Barryvdh\DomPDF\PDF::class);

    // Verify view rendering directly
    $about = About::first();
    $renderedView = view('reports.selling-receipt-pdf', [
        'selling' => $this->selling,
        'about' => $about,
        'currency' => 'IDR',
        'timezone' => 'Asia/Jakarta',
        'paperWidth' => '58mm',
    ])->render();

    expect($renderedView)->toContain('PT Maju Bersama POS');
    expect($renderedView)->toContain('Jl. Jenderal Sudirman No. 88, Jakarta Pusat');
    expect($renderedView)->toContain('Telp: 081234567890');
    expect($renderedView)->toContain('Email: contact@majubersama.com');
    expect($renderedView)->toContain('Web: https://www.majubersama.com');
    expect($renderedView)->toContain('NPWP: 01.234.567.8-012.000');
});

test('print bridge service layout contains company identity and centered header rows', function () {
    $printer = new Printer([
        'name' => 'Kasir Utama',
        'paper_width' => '58mm',
        'print_all_categories' => true,
        'show_price' => true,
    ]);

    $data = PrintBridgeService::buildFilteredReceiptData($this->selling, $this->selling->sellingDetails, $printer);

    expect($data['layout'])->toHaveKey('company');
    expect($data['layout']['company']['name'])->toBe('PT Maju Bersama POS');
    expect($data['layout']['company']['phone'])->toBe('081234567890');
    expect($data['layout']['company']['email'])->toBe('contact@majubersama.com');
    expect($data['layout']['company']['website'])->toBe('https://www.majubersama.com');
    expect($data['layout']['company']['npwp'])->toBe('01.234.567.8-012.000');

    // Header rows in metaRows should have blank labels for centering
    $metaValues = array_column($data['layout']['meta_rows'], 'value');
    expect($metaValues)->toContain('PT MAJU BERSAMA POS');
    expect($metaValues)->toContain('Jl. Jenderal Sudirman No. 88, Jakarta Pusat');
    expect($metaValues)->toContain('Telp: 081234567890');
    expect($metaValues)->toContain('Email: contact@majubersama.com');
    expect($metaValues)->toContain('https://www.majubersama.com');
    expect($metaValues)->toContain('NPWP: 01.234.567.8-012.000');
});

test('general setting page mounts without synthesizer error and handles company identity', function () {
    $user = User::first();
    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('tenant'));

    \Livewire\Livewire::actingAs($user)
        ->test(\App\Filament\Tenant\Pages\GeneralSetting::class)
        ->assertSuccessful()
        ->assertSet('about.photo', null)
        ->assertSet('about.shop_name', 'PT Maju Bersama POS')
        ->assertSet('about.phone', '081234567890')
        ->assertSet('about.email', 'contact@majubersama.com')
        ->assertSet('about.website', 'https://www.majubersama.com')
        ->assertSet('about.npwp', '01.234.567.8-012.000');
});

