<?php

use App\Models\Tenants\PosNotification;
use App\Services\PosNotificationService;
use Illuminate\Support\Facades\Artisan;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

test('can dispatch notification using PosNotificationService', function () {
    $notif = PosNotificationService::send('Test Title', 'Test Message', [
        'outlet_id' => 1,
        'type' => 'alert',
    ]);

    expect($notif)->toBeInstanceOf(PosNotification::class)
        ->and($notif->title)->toBe('Test Title')
        ->and($notif->message)->toBe('Test Message')
        ->and($notif->status)->toBe('pending');
});

test('can poll pending notifications via API', function () {
    $token = config('services.print_bridge.token', env('PRINT_BRIDGE_TOKEN', 'lakasir-print-token-2026'));

    PosNotification::create([
        'outlet_id' => 1,
        'title' => 'API Poll Test',
        'message' => 'Pending notif',
        'status' => 'pending',
    ]);

    $response = getJson("/api/pos-notifications?token={$token}&outlet_id=1");

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    $notifications = $response->json('notifications');
    expect(count($notifications))->toBeGreaterThanOrEqual(1);
});

test('can mark notification as read via API', function () {
    $token = config('services.print_bridge.token', env('PRINT_BRIDGE_TOKEN', 'lakasir-print-token-2026'));

    $notif = PosNotification::create([
        'outlet_id' => 1,
        'title' => 'Read Test',
        'message' => 'To be read',
        'status' => 'pending',
    ]);

    $response = postJson("/api/pos-notifications/{$notif->id}/read?token={$token}");

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($notif->fresh()->status)->toBe('read')
        ->and($notif->fresh()->read_at)->not->toBeNull();
});

test('can send notification via pos:notify artisan command', function () {
    $exitCode = Artisan::call('pos:notify', [
        'title' => 'Artisan Test',
        'message' => 'Test body message',
        '--outlet' => 1,
    ]);

    expect($exitCode)->toBe(0);

    $latest = PosNotification::latest('id')->first();
    expect($latest->title)->toBe('Artisan Test');
});

