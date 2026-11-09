<?php

namespace App\Listeners;

use App\Events\SellingCreated;
use App\Services\Tenants\ReceiptPdfService;
use Illuminate\Support\Facades\Log;

class SaveReceiptPdf
{
    public function __construct(protected ReceiptPdfService $receiptPdfService)
    {
    }

    /**
     * Handle the event.
     */
    public function handle(SellingCreated $event): void
    {
        try {
            $this->receiptPdfService->saveToStorage($event->selling);
        } catch (\Throwable $e) {
            Log::warning('Failed to auto-save receipt PDF for Selling #'.$event->selling->id.': '.$e->getMessage());
        }
    }
}

