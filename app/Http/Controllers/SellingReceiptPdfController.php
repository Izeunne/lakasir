<?php

namespace App\Http\Controllers;

use App\Models\Tenants\About;
use App\Models\Tenants\Printer;
use App\Models\Tenants\Selling;
use App\Models\Tenants\Setting;
use App\Services\Tenants\ReceiptPdfService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SellingReceiptPdfController extends Controller
{
    /**
     * Generate, stream or download the PDF receipt for a selling record.
     */
    public function generate(Request $request, Selling $selling, ReceiptPdfService $receiptPdfService): Response
    {
        $defaultWidth = Setting::get('printer_paper_width', '58mm');
        $paperWidth = $request->query('width', $defaultWidth);
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

    /**
     * Display a live visual preview of all receipt designs (Customer, Merchant Copy, Kitchen Checker, Bar Checker).
     */
    public function previewAllReceipts(Selling $selling): View
    {
        $selling->load([
            'sellingDetails.product.category',
            'member',
            'user',
            'paymentMethod',
            'table',
        ]);

        $about = About::first();
        $printers = Printer::active()->orderBy('id')->get();

        return view('reports.preview-all-receipts', compact('selling', 'about', 'printers'));
    }
}

