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

        // Dynamic height calculation to provide a seamless single-page continuous receipt look
        $height = 70; // Base margin & store name
        if (! empty($about?->photo)) {
            $height += 50;
        }
        if (! empty($about?->shop_location)) {
            $height += 16;
        }
        if (! empty($about?->phone) || ! empty($about?->npwp)) {
            $height += 12;
        }
        if (! empty($about?->website) || ! empty($about?->email)) {
            $height += 12;
        }

        // Meta info (Invoice, date, cashier, table, member, payment)
        $height += 55;
        if ($selling->table) {
            $height += 12;
        }
        if ($selling->member) {
            $height += 12;
        }

        // Items & sub-items
        foreach ($selling->sellingDetails as $detail) {
            $height += 20; // Item name & price row
            if (! empty($detail->note)) {
                $height += 11;
            }
            if (! empty($detail->selected_variations)) {
                $height += count($detail->selected_variations) * 11;
            }
            if (! empty($detail->discount_price) && $detail->discount_price > 0) {
                $height += 11;
            }
        }

        // Totals, payment, changes
        $height += 70;
        if ($selling->note) {
            $height += 20;
        }

        // Footer & safety buffer
        $height += 45;

        $heightPt = max(240, $height);

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

