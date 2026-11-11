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
            PrintBridgeService::queueSellingPrints($event->selling);
        } catch (\Throwable $e) {
            Log::warning('Failed to queue print bridge receipts: ' . $e->getMessage());
        }
    }
}
