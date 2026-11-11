<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenants\MobilePrinter;
use App\Models\Tenants\Printer;
use App\Models\Tenants\PrintQueue;
use App\Services\PrintBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintQueueController extends Controller
{
    private function validateToken(Request $request): void
    {
        $expectedToken = config('services.print_bridge.token', env('PRINT_BRIDGE_TOKEN', 'lakasir-print-token-2026'));
        $receivedToken = $request->query('token') ?? $request->header('X-Bridge-Token') ?? $request->bearerToken();

        if (empty($expectedToken) || !hash_equals((string) $expectedToken, (string) $receivedToken)) {
            abort(401, 'Token print queue tidak valid.');
        }
    }

    /**
     * Get list of mobile-connected printers and configured printer stations (GET /api/print-queue/printers).
     */
    public function printers(Request $request): JsonResponse
    {
        $this->validateToken($request);

        $outletId = (int) $request->query('outlet_id', 1);

        $mobilePrinters = MobilePrinter::where('outlet_id', $outletId)
            ->orderByDesc('is_connected')
            ->orderBy('device_name')
            ->get();

        $stations = Printer::orderBy('id')->get()->map(function (Printer $printer) {
            return [
                'id' => $printer->id,
                'name' => $printer->name,
                'device_id' => $printer->device_id,
                'device_name' => $printer->device_name,
                'connection_type' => $printer->connection_type,
                'paper_width' => $printer->paper_width,
                'print_all_categories' => $printer->print_all_categories,
                'category_ids' => $printer->category_ids ?? [],
                'category_names' => $printer->category_names,
                'show_price' => $printer->show_price,
                'copies' => $printer->copies,
                'is_active' => $printer->is_active,
            ];
        });

        return response()->json([
            'success' => true,
            'mobile_printers' => $mobilePrinters,
            'stations' => $stations,
        ]);
    }

    /**
     * Sync connected printers from the mobile application (POST /api/print-queue/printers/sync).
     */
    public function syncPrinters(Request $request): JsonResponse
    {
        $this->validateToken($request);

        $outletId = (int) $request->input('outlet_id', 1);
        $deviceModel = $request->input('device_model');
        $printers = (array) $request->input('printers', []);
        $markOthersDisconnected = (bool) $request->input('replace', true);

        $connectedPrinters = PrintBridgeService::syncMobilePrinters(
            $printers,
            $outletId,
            $deviceModel ? (string) $deviceModel : null,
            $markOthersDisconnected
        );

        return response()->json([
            'success' => true,
            'message' => 'Daftar printer dari aplikasi mobile berhasil disinkronkan.',
            'connected_count' => $connectedPrinters->count(),
            'mobile_printers' => $connectedPrinters,
        ]);
    }

    /**
     * Poll jobs from queue (GET /api/print-queue/{queueType}).
     */
    public function index(Request $request, string $queueType): JsonResponse
    {
        $this->validateToken($request);

        $outletId = $request->query('outlet_id');
        $stationId = $request->query('station_id');
        $deviceId = $request->query('device_id');
        $targetPrinter = $request->query('target_printer');

        // Optional heartbeat sync if mobile passes connected_printers during polling
        if ($request->filled('connected_printers')) {
            $rawPrinters = $request->input('connected_printers');
            if (is_string($rawPrinters)) {
                $rawPrinters = json_decode($rawPrinters, true) ?: [];
            }
            if (is_array($rawPrinters)) {
                PrintBridgeService::syncMobilePrinters(
                    $rawPrinters,
                    (int) ($outletId ?: 1),
                    $request->query('device_model'),
                    true
                );
            }
        }

        $jobs = PrintQueue::query()
            ->where('status', 'queued')
            ->when($queueType !== 'all', fn ($q) => $q->where('queue_type', $queueType))
            ->when(!empty($outletId), fn ($q) => $q->where('outlet_id', (int) $outletId))
            ->when(!empty($stationId), fn ($q) => $q->where('station_id', (string) $stationId))
            ->when(!empty($targetPrinter), fn ($q) => $q->where('target_printer', (string) $targetPrinter))
            ->when(!empty($deviceId), fn ($q) => $q->where(fn ($sq) => $sq->whereNull('device_id')->orWhere('device_id', (string) $deviceId)))
            ->orderBy('id', 'asc')
            ->limit(20)
            ->get();

        $formatted = $jobs->map(function (PrintQueue $job) {
            return [
                'id' => (string) $job->id,
                'queue_type' => $job->queue_type,
                'type' => $job->type,
                'station_id' => $job->station_id,
                'target_printer' => $job->target_printer,
                'paper_width' => $job->paper_width,
                'show_price' => $job->payload['show_price'] ?? ($job->type === 'receipt'),
                'layout' => $job->layout,
                'transaction' => $job->transaction,
                'ticket' => $job->ticket,
                'payload' => $job->payload ?? ['lines' => []],
                'raw_base64' => $job->raw_base64,
            ];
        });

        return response()->json([
            'success' => true,
            'jobs' => $formatted,
            'count' => $formatted->count(),
        ]);
    }

    /**
     * Mark job as completed (POST /api/print-queue/{id}/done).
     */
    public function markDone(Request $request, string|int $id): JsonResponse
    {
        $this->validateToken($request);

        $job = PrintQueue::find($id);
        if ($job) {
            $job->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Job marked as completed.',
        ]);
    }

    /**
     * Mark job as failed (POST /api/print-queue/{id}/fail).
     */
    public function markFailed(Request $request, string|int $id): JsonResponse
    {
        $this->validateToken($request);

        $reason = (string) ($request->input('reason') ?? 'Unknown error');
        $job = PrintQueue::find($id);
        if ($job) {
            $job->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => mb_substr($reason, 0, 500),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Job marked as failed.',
        ]);
    }

    /**
     * Helper to inject test print job (POST /api/print-queue/test).
     */
    public function test(Request $request): JsonResponse
    {
        $this->validateToken($request);
        $outletId = (int) ($request->input('outlet_id', 1));
        $printerId = $request->input('printer_id');

        if ($printerId) {
            $printer = Printer::find($printerId);
            if ($printer) {
                $job = PrintBridgeService::queuePrinterTest($printer, $outletId);

                return response()->json([
                    'success' => true,
                    'message' => "Test print untuk [{$printer->name}] berhasil dikirim ke antrean.",
                    'job_id' => $job->id,
                    'target_printer' => $printer->device_id,
                    'show_price' => $printer->show_price,
                ]);
            }
        }

        $type = (string) ($request->input('type', 'cashier'));
        $targetPrinter = $request->input('target_printer');

        $job = PrintBridgeService::queueTestJob($outletId, $type, $targetPrinter);

        return response()->json([
            'success' => true,
            'message' => 'Test job queued successfully',
            'job_id' => $job->id,
            'target_printer' => $targetPrinter,
        ]);
    }
}
