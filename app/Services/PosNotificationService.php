<?php

namespace App\Services;

use App\Models\Tenants\PosNotification;
use App\Models\Tenants\Selling;

class PosNotificationService
{
    /**
     * Dispatch a notification to the POS app.
     *
     * @param string $title
     * @param string $message
     * @param array<string, mixed> $options
     * @return PosNotification
     */
    public static function send(string $title, string $message, array $options = []): PosNotification
    {
        return PosNotification::create([
            'outlet_id' => $options['outlet_id'] ?? 1,
            'device_id' => $options['device_id'] ?? null,
            'title' => $title,
            'message' => $message,
            'type' => $options['type'] ?? 'order',
            'sound' => $options['sound'] ?? 'default',
            'data' => $options['data'] ?? [],
            'status' => 'pending',
        ]);
    }

    /**
     * Dispatch a notification when a new order/sale is completed.
     */
    public static function notifyOrderCreated(Selling $selling, int $outletId = 1): PosNotification
    {
        $selling->loadMissing(['sellingDetails.product', 'paymentMethod']);
        $invoice = $selling->invoice ?? ('#' . $selling->id);
        $totalFormatted = 'Rp ' . number_format($selling->grand_total_price ?? 0, 0, ',', '.');
        $itemCount = $selling->sellingDetails ? (int) $selling->sellingDetails->sum('qty') : 1;

        $title = '🔔 Pesanan Baru: ' . $invoice;
        $message = "Total: {$totalFormatted} ({$itemCount} item)";

        return self::send($title, $message, [
            'outlet_id' => $outletId,
            'type' => 'order',
            'sound' => 'order_chime',
            'data' => [
                'selling_id' => $selling->id,
                'invoice' => $invoice,
                'grand_total' => $selling->grand_total_price,
                'payment_method' => $selling->paymentMethod?->name ?? 'Tunai',
                'items_count' => $itemCount,
            ],
        ]);
    }

    /**
     * Dispatch an alert notification (e.g. low stock, cashier alert, announcement).
     */
    public static function notifyAlert(string $title, string $message, string $type = 'alert', int $outletId = 1): PosNotification
    {
        return self::send($title, $message, [
            'outlet_id' => $outletId,
            'type' => $type,
            'sound' => 'alert',
            'data' => [
                'triggered_at' => now()->toIso8601String(),
            ],
        ]);
    }
}