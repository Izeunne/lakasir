<?php

namespace App\Listeners;

use App\Events\SellingCreated;
use App\Services\PosNotificationService;
use Illuminate\Support\Facades\Log;

class SendPosNotificationOnSelling
{
    /**
     * Handle the event.
     */
    public function handle(SellingCreated $event): void
    {
        try {
            PosNotificationService::notifyOrderCreated($event->selling);
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch POS notification for selling: ' . $e->getMessage());
        }
    }
}

