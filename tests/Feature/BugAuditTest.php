<?php

use App\Models\Tenants\Category;
use App\Models\Tenants\Product;
use App\Models\Tenants\SecureInitialPrice;
use App\Models\Tenants\Setting;
use App\Models\Tenants\User;
use Tests\RefreshDatabaseWithTenant;

use function Pest\Laravel\actingAs;

uses(RefreshDatabaseWithTenant::class);

test('AUDIT: register-fcm-token allows privilege escalation to owner', function () {
    $user = User::factory()->create(['email' => 'cashier-audit@example.com']);
    expect((bool) $user->is_owner)->toBeFalse();

    actingAs($user, 'sanctum')
        ->postJson('/api/register-fcm-token', ['is_owner' => true, 'fcm_token' => 'abc'])
        ->assertOk();

    expect((bool) $user->fresh()->is_owner)->toBeTrue();
});

test('AUDIT: user without role can still update product through Gate::after', function () {
    $user = User::factory()->create(['email' => 'norole-audit@example.com']);
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    expect($user->can('update', $product))->toBeFalse();
});

test('AUDIT: secure initial price verify accepts login password instead of PIN', function () {
    Setting::set('secure_initial_price_enabled', true);
    $owner = User::first();
    $owner->update(['password' => bcrypt('login-password')]);

    SecureInitialPrice::create([
        'user_id' => $owner->id,
        'password' => bcrypt('the-real-pin'),
    ]);

    actingAs($owner, 'sanctum')
        ->postJson('/api/setting/secure-initial-price/verify', ['password' => 'login-password'])
        ->assertOk();

    // the real PIN must NOT work if the bug exists (it checks login password)
    actingAs($owner, 'sanctum')
        ->postJson('/api/setting/secure-initial-price/verify', ['password' => 'the-real-pin'])
        ->assertStatus(403);
});

test('AUDIT: grand total excludes tax although it was added to total price', function () {
    $selling = new \App\Models\Tenants\Selling([
        'total_price' => 22000,
        'tax' => 10,
        'tax_price' => 2000,
        'total_discount_per_item' => 0,
        'discount_price' => 0,
    ]);

    // customer is charged 22000 (tax included) but the receipt "TOTAL" shows 20000
    expect((float) $selling->grand_total_price)->toBe(22000.0);
});
