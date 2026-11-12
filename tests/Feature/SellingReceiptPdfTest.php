<?php

use App\Events\SellingCreated;
use App\Listeners\SaveReceiptPdf;
use App\Models\Tenants\PaymentMethod;
use App\Models\Tenants\Product;
use App\Models\Tenants\Selling;
use App\Models\Tenants\SellingDetail;
use App\Models\Tenants\User;
use App\Services\Tenants\ReceiptPdfService;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshDatabaseWithTenant;

use function Pest\Laravel\actingAs;

uses(RefreshDatabaseWithTenant::class);

beforeEach(function () {
    Storage::fake('public');

    $this->product = Product::factory()->create([
        'selling_price' => 25000,
        'initial_price' => 15000,
    ]);

    $this->paymentMethod = PaymentMethod::firstOrCreate(
        ['name' => 'Cash'],
        ['is_cash' => true, 'is_debit' => false, 'is_credit' => false, 'is_wallet' => false]
    );

    $this->selling = Selling::create([
        'code' => 'SELL0099',
        'date' => now()->format('Y-m-d H:i:s'),
        'total_price' => 50000,
        'total_cost' => 20000,
        'total_qty' => 2,
        'grand_total_price' => 50000,
        'payed_money' => 100000,
        'money_changes' => 50000,
        'order_type' => 'dine_in',
        'payment_method_id' => $this->paymentMethod->id,
        'user_id' => User::first()?->id ?? 1,
    ]);

    SellingDetail::create([
        'selling_id' => $this->selling->id,
        'product_id' => $this->product->id,
        'qty' => 2,
        'price' => 50000,
        'discount_price' => 0,
    ]);
});

test('receipt pdf service generates pdf instance and can save to storage', function () {
    $service = app(ReceiptPdfService::class);
    $pdf = $service->generate($this->selling);

    expect($pdf)->toBeInstanceOf(\Barryvdh\DomPDF\PDF::class);

    $path = $service->saveToStorage($this->selling);
    expect($path)->toBe("receipts/receipt-{$this->selling->code}.pdf");
    Storage::disk('public')->assertExists($path);
});

test('selling receipt pdf route streams pdf successfully', function () {
    $user = User::first();

    $response = actingAs($user)->get("/member/sellings/{$this->selling->id}/receipt-pdf");

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    Storage::disk('public')->assertExists("receipts/receipt-{$this->selling->code}.pdf");
});

test('selling receipt pdf route downloads pdf when download flag is present', function () {
    $user = User::first();

    $response = actingAs($user)->get("/member/sellings/{$this->selling->id}/receipt-pdf?download=1");

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain('attachment')->toContain("struk-{$this->selling->code}.pdf");
});

test('save receipt pdf listener auto saves pdf upon selling created event', function () {
    $listener = app(SaveReceiptPdf::class);
    $event = new SellingCreated($this->selling, []);

    $listener->handle($event);

    Storage::disk('public')->assertExists("receipts/receipt-{$this->selling->code}.pdf");
});

test('receipt pdf service supports 80mm paper width and respects printer_paper_width setting', function () {
    $service = app(ReceiptPdfService::class);

    // Explicit 80mm
    $pdf80 = $service->generate($this->selling, '80mm');
    expect($pdf80)->toBeInstanceOf(\Barryvdh\DomPDF\PDF::class);

    // Via setting
    \App\Models\Tenants\Setting::set('printer_paper_width', '80mm');
    $pdfFromSetting = $service->generate($this->selling);
    expect($pdfFromSetting)->toBeInstanceOf(\Barryvdh\DomPDF\PDF::class);

    $user = User::first();
    $response = actingAs($user)->get("/member/sellings/{$this->selling->id}/receipt-pdf?width=80mm");
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('printer settings save paper_width and virtual driver properly', function () {
    \App\Models\Tenants\Setting::set('printer_paper_width', '80mm');
    \App\Models\Tenants\Setting::set('printer_driver', 'virtual');

    expect(\App\Models\Tenants\Setting::get('printer_paper_width'))->toBe('80mm')
        ->and(\App\Models\Tenants\Setting::get('printer_driver'))->toBe('virtual');
});
