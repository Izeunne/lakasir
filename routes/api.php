<?php

use App\Http\Controllers\Api\PosNotificationController;
use App\Http\Controllers\Api\PrintQueueController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'domain'], function () {
    Route::post('/register', RegisteredUserController::class)
        ->name('register');
});

Route::get('/test', function () {
    return response()->json([
        'message' => 'Lakasir API Online!',
        'time' => now()->toIso8601String(),
    ]);
});

// POS Realtime Notifications
Route::prefix('pos-notifications')->group(function () {
    Route::get('/', [PosNotificationController::class, 'index']);
    Route::post('/send', [PosNotificationController::class, 'send']);
    Route::post('/test', [PosNotificationController::class, 'sendTest']);
    Route::post('/{id}/read', [PosNotificationController::class, 'markRead']);
});

// POS Mataverse Print Bridge Routes
Route::prefix('print-queue')->group(function () {
    Route::get('/printers', [PrintQueueController::class, 'printers']);
    Route::post('/printers/sync', [PrintQueueController::class, 'syncPrinters']);
    Route::get('/{queueType}', [PrintQueueController::class, 'index'])->where('queueType', 'cashier|kitchen|all');
    Route::post('/{id}/done', [PrintQueueController::class, 'markDone']);
    Route::post('/{id}/fail', [PrintQueueController::class, 'markFailed']);
    Route::post('/test', [PrintQueueController::class, 'test']);
});
