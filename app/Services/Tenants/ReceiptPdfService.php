<?php

namespace App\Services\Tenants;

use App\Models\Tenants\About;
use App\Models\Tenants\Profile;
use App\Models\Tenants\Selling;
use App\Models\Tenants\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Illuminate\Support\Facades\Storage;

class ReceiptPdfService
{
    /**
     * Generate DomPDF instance for the selling receipt.
     */
    public function generate(Selling $selling, ?string $paperWidth = null): DomPdfWrapper
    {
        $selling->loadMissing([
            'sellingDetails.product',
            'paymentMethod',
            'member',
            'user',
            'table',
        ]);

        $paperWidth = $paperWidth ?: Setting::get('printer_paper_width', '58mm');
        if (! in_array($paperWidth, ['58mm', '80mm'], true)) {
            $paperWidth = '58mm';
        }

        $about = About::first();
        $currency = Setting::get('currency', 'IDR');
        $timezone = Profile::get()->timezone ?? config('app.timezone', 'UTC');

        // Dynamic paper dimension:
        // 58mm thermal is approx 164.4 pt wide (58 / 25.4 * 72)
        // 80mm thermal is approx 226.77 pt wide (80 / 25.4 * 72)
        $widthPt = ($paperWidth === '80mm') ? 226.77 : 164.4;

        $itemCount = $selling->sellingDetails->count();
        $variationCount = 0;
        foreach ($selling->sellingDetails as $detail) {
            if (! empty($detail->selected_variations)) {
                $variationCount += count($detail->selected_variations);
            }
        }

        // Base height estimate to provide a seamless continuous receipt look
        $estimatedHeight = 220 + ($itemCount * 38) + ($variationCount * 14);
        if ($selling->note) {
            $estimatedHeight += 35;
        }
        $heightPt = max(280, $estimatedHeight);

        /** @var DomPdfWrapper $pdf */
        $pdf = Pdf::loadView('reports.selling-receipt-pdf', compact(
            'selling',
            'about',
            'currency',
            'timezone',
            'paperWidth'
        ))->setPaper([0, 0, $widthPt, $heightPt], 'portrait');

        return $pdf;
    }

    /**
     * Save receipt PDF to public storage and return its relative path.
     */
    public function saveToStorage(Selling $selling, ?string $paperWidth = null): string
    {
        $paperWidth = $paperWidth ?: Setting::get('printer_paper_width', '58mm');
        $pdf = $this->generate($selling, $paperWidth);
        $filename = "receipts/receipt-{$selling->code}.pdf";

        Storage::disk('public')->put($filename, $pdf->output());

        return $filename;
    }

    /**
     * Get the stored receipt PDF path or generate it if missing.
     */
    public function getOrGeneratePdfPath(Selling $selling): string
    {
        $filename = "receipts/receipt-{$selling->code}.pdf";
        if (! Storage::disk('public')->exists($filename)) {
            $this->saveToStorage($selling);
        }

        return $filename;
    }
}

