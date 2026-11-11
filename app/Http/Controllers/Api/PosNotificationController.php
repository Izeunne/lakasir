<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenants\PosNotification;
use App\Services\PosNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosNotificationController extends Controller
{
    private function validateToken(Request $request): void
    {
        $expectedToken = config('services.print_bridge.token', env('PRINT_BRIDGE_TOKEN', 'lakasir-print-token-2026'));
        $receivedToken = $request->query('token') ?? $request->header('X-Bridge-Token') ?? $request->bearerToken();

        if (empty($expectedToken) || !hash_equals((string) $expectedToken, (string) $receivedToken)) {
            abort(401, 'Token notifikasi tidak valid.');
        }
    }

    /**
     * Poll pending notifications (GET /api/pos-notifications).
     */
    public function index(Request $request): JsonResponse
    {
        $this->validateToken($request);

        $outletId = $request->query('outlet_id');
        $deviceId = $request->query('device_id');
        $afterId = $request->query('after_id');

        $notifications = PosNotification::query()
            ->where('status', 'pending')
            ->when(!empty($outletId), fn ($q) => $q->where(fn ($sq) => $sq->whereNull('outlet_id')->orWhere('outlet_id', (int) $outletId)))
            ->when(!empty($deviceId), fn ($q) => $q->where(fn ($sq) => $sq->whereNull('device_id')->orWhere('device_id', (string) $deviceId)))
            ->when(!empty($afterId), fn ($q) => $q->where('id', '>', (int) $afterId))
            ->orderBy('id', 'asc')
            ->limit(10)
            ->get();

        $formatted = $notifications->map(function (PosNotification $notif) {
            return [
                'id' => (int) $notif->id,
                'title' => $notif->title,
                'message' => $notif->message,
                'type' => $notif->type,
                'sound' => $notif->sound,
                'data' => $notif->data ?? [],
                'created_at' => $notif->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'notifications' => $formatted,
            'count' => $formatted->count(),
        ]);
    }

    /**
     * Mark notification as read/delivered (POST /api/pos-notifications/{id}/read).
     */
    public function markRead(Request $request, int|string $id): JsonResponse
    {
        $this->validateToken($request);

        $notif = PosNotification::find($id);
        if ($notif) {
            $notif->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Send test notification from backend (POST /api/pos-notifications/test).
     */
    public function sendTest(Request $request): JsonResponse
    {
        $this->validateToken($request);

        $title = (string) ($request->input('title') ?? '🔔 Notifikasi Lakasir POS');
        $message = (string) ($request->input('message') ?? 'Tes notifikasi realtime dari backend berhasil diterima!');
        $outletId = (int) ($request->input('outlet_id') ?? 1);
        $type = (string) ($request->input('type') ?? 'info');

        $notif = PosNotificationService::send($title, $message, [
            'outlet_id' => $outletId,
            'type' => $type,
            'data' => [
                'sent_from' => 'backend-api',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification created successfully.',
            'notification' => $notif,
        ]);
    }

    /**
     * Send custom notification from backend (POST /api/pos-notifications/send).
     */
    public function send(Request $request): JsonResponse
    {
        $this->validateToken($request);

        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'outlet_id' => 'nullable|integer',
            'device_id' => 'nullable|string',
            'type' => 'nullable|string',
            'sound' => 'nullable|string',
            'data' => 'nullable|array',
        ]);

        $notif = PosNotificationService::send(
            $request->input('title'),
            $request->input('message'),
            $request->all()
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification dispatched.',
            'notification' => $notif,
        ]);
    }
}

