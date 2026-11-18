<?php

use App\Filament\Tenant\Pages\Cashier;
use App\Filament\Tenant\Pages\Dashboard;
use App\Models\Tenants\Role;
use App\Models\Tenants\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\RefreshDatabaseWithTenant;

use function Pest\Laravel\actingAs;

uses(RefreshDatabaseWithTenant::class);

test('unauthenticated or regular access redirects to cashier directly instead of portal', function () {
    $user = User::first();

    actingAs($user)
        ->get('/member')
        ->assertRedirect(Cashier::getUrl());
});

test('cashier pos is initially locked if no pos session exists', function () {
    $user = User::first();
    actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    Livewire::test(Cashier::class)
        ->assertSet('isPosLocked', true)
        ->assertSee('Pilih Akun Kasir')
        ->assertSee('PIN Kasir');
});

test('cashier can unlock pos with valid pin', function () {
    $user = User::first();
    $user->update(['pin' => '1234']);
    actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    Livewire::test(Cashier::class)
        ->assertSet('isPosLocked', true)
        ->set('selectedCashierId', $user->id)
        ->set('posPin', '9999')
        ->call('unlockPos')
        ->assertSet('isPosLocked', true)
        ->assertSet('posPinError', 'PIN kasir tidak sesuai.')
        ->set('posPin', '1234')
        ->call('unlockPos')
        ->assertSet('isPosLocked', false)
        ->assertSet('posPinError', null);

    expect(session('pos_user_id'))->toBe($user->id);
});

test('pos has admin panel button and requires admin pin to unlock', function () {
    $admin = User::first();
    $admin->update(['pin' => '8888']);
    // Make sure user is owner or admin
    $admin->update(['is_owner' => true]);

    actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    // 1. Trying to access admin dashboard without admin_unlocked redirects to cashier
    actingAs($admin)
        ->get(Dashboard::getUrl())
        ->assertRedirect(Cashier::getUrl(['open_admin_modal' => 1]));

    // 2. In cashier, opening admin modal and submitting pin unlocks admin panel
    Livewire::withQueryParams(['open_admin_modal' => 1])
        ->test(Cashier::class)
        ->assertSet('showAdminModal', true)
        ->set('adminPin', '1111')
        ->call('submitAdminPin')
        ->assertSet('showAdminModal', true)
        ->assertSet('adminPinError', 'PIN Admin tidak sesuai.')
        ->set('adminPin', '8888')
        ->call('submitAdminPin')
        ->assertRedirect(Dashboard::getUrl());

    expect(session('admin_unlocked'))->toBeTrue();

    // 3. Once unlocked, dashboard is accessible
    actingAs($admin)
        ->withSession(['admin_unlocked' => true])
        ->get(Dashboard::getUrl())
        ->assertOk();
});

test('cashier can lock screen again with lockPos', function () {
    $user = User::first();
    $user->update(['pin' => '1234']);
    actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    session(['pos_user_id' => $user->id]);

    Livewire::test(Cashier::class)
        ->assertSet('isPosLocked', false)
        ->call('lockPos')
        ->assertSet('isPosLocked', true);

    expect(session('pos_user_id'))->toBeNull();
});

test('opening and closing shift requires admin pin when admin pin configured', function () {
    $admin = User::first();
    $admin->update(['pin' => '8888', 'is_owner' => true]);
    actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    // 1. Calling submitOpenShift prompts for admin pin
    $component = Livewire::test(Cashier::class)
        ->call('submitOpenShift')
        ->assertSet('showAdminModal', true)
        ->assertSet('adminPinAction', 'open_shift')
        ->set('adminPin', '8888')
        ->call('submitAdminPin')
        ->assertSet('showAdminModal', false)
        ->assertDispatched('open-shift-printed');

    $activeDrawer = \App\Models\Tenants\CashDrawer::lastOpened()->first();
    expect($activeDrawer)->not->toBeNull();

    // 2. Calling submitCloseShift prompts for admin pin
    $component->call('submitCloseShift')
        ->assertSet('showAdminModal', true)
        ->assertSet('adminPinAction', 'close_shift')
        ->set('adminPin', '8888')
        ->call('submitAdminPin')
        ->assertSet('showAdminModal', false)
        ->assertDispatched('close-shift-printed');

    expect(\App\Models\Tenants\CashDrawer::lastOpened()->first())->toBeNull();
});
