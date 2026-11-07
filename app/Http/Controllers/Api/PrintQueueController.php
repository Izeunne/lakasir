<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
     * Poll jobs from queue (GET /api/print-queue/{queueType}).
     */
    public function index(Request $request, string $queueType): JsonResponse
    {
        $this->validateToken($request);

        $outletId = $request->query('outlet_id');
        $stationId = $request->query('station_id');
        $deviceId = $request->query('device_id');

        $jobs = PrintQueue::query()
            ->where('status', 'queued')
            ->where('queue_type', $queueType)
            ->when(!empty($outletId), fn ($q) => $q->where('outlet_id', (int) $outletId))
            ->when($queueType === 'kitchen' && !empty($stationId), fn ($q) => $q->where('station_id', (string) $stationId))
            ->when(!empty($deviceId), fn ($q) => $q->where(fn ($sq) => $sq->whereNull('device_id')->orWhere('device_id', (string) $deviceId)))
            ->orderBy('id', 'asc')
            ->limit(10)
            ->get();

        $formatted = $jobs->map(function (PrintQueue $job) {
            return [
                'id' => (string) $job->id,
                'type' => $job->type,
                'paper_width' => $job->paper_width,
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
        $type = (string) ($request->input('type', 'cashier'));

        $job = PrintBridgeService::queueTestJob($outletId, $type);

        return response()->json([
            'success' => true,
            'message' => 'Test job queued successfully',
            'job_id' => $job->id,
        ]);
    }
}
