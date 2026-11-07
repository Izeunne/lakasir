<?php

use App\Livewire\Forms\Auth\RegisterTenantForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'pages/welcome');

Route::view('/offline', 'offline');

Route::get('/serviceworker.js', function () {
    return response()->file(public_path('serviceworker.js'))
        ->header('Content-Type', 'application/javascript');
});

Route::get('/auth/register', RegisterTenantForm::class)
    ->name('auth.register');

Route::get('/print-client.html', function (Request $request) {
    $baseUrl = $request->getSchemeAndHttpHost();
    $token = config('services.print_bridge.token', env('PRINT_BRIDGE_TOKEN', 'lakasir-print-token-2026'));
    $outletId = (int) $request->query('outlet_id', 1);
    $type = (string) $request->query('type', 'cashier');
    $autoStart = (int) $request->query('autostart', 1);

    $fullClientUrl = "{$baseUrl}/print-client.html?base_url={$baseUrl}&token={$token}&outlet_id={$outletId}&type={$type}&autostart={$autoStart}";

    return view('print-bridge-client', compact('baseUrl', 'token', 'outletId', 'type', 'autoStart', 'fullClientUrl'));
});

Route::middleware([
    'web',
])
    ->prefix('admin')
    ->group(function () {
        //
    });
