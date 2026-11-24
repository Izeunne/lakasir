<?php

namespace App\Listeners;

use App\Events\SellingCreated;
use App\Services\PrintBridgeService;
use Illuminate\Support\Facades\Log;

class QueuePrintBridgeReceipt
{
    /**
     * Handle the event.
     */
    public function handle(SellingCreated $event): void
    {
        try {
            $skipKitchenPrint = (bool) ($event->data['skip_kitchen_print'] ?? false);
            $outletId = (int) ($event->selling->outlet_id ?? $event->data['outlet_id'] ?? 1);
            PrintBridgeService::queueSellingPrints($event->selling, $outletId, $skipKitchenPrint);
        } catch (\Throwable $e) {
            Log::warning('Failed to queue print bridge receipts: ' . $e->getMessage());
        }
    }
}
