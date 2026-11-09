<?php

namespace App\Http\Controllers;

use App\Models\Tenants\Selling;
use App\Services\Tenants\ReceiptPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SellingReceiptPdfController extends Controller
{
    /**
     * Generate, stream or download the PDF receipt for a selling record.
     */
    public function generate(Request $request, Selling $selling, ReceiptPdfService $receiptPdfService): Response
    {
        $paperWidth = $request->query('width', '58mm');
        if (! in_array($paperWidth, ['58mm', '80mm'], true)) {
            $paperWidth = '58mm';
        }

        // Generate and archive a copy to storage
        $receiptPdfService->saveToStorage($selling, $paperWidth);

        $pdf = $receiptPdfService->generate($selling, $paperWidth);

        $filename = "struk-{$selling->code}.pdf";

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}

