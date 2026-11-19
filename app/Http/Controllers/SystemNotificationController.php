<?php

namespace App\Http\Controllers;

use App\Models\SystemNotification;
use App\Services\DeviceHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SystemNotificationController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            if (Cache::add('device_health:last_poll_check', true, 60)) {
                app(DeviceHealthService::class)->check(includeCamera: false);
            }
        } catch (\Throwable $e) {
            // Never break notification polling if a health check fails
        }

        $items = SystemNotification::query()
            ->orderByDesc('created_at')
            ->limit(40)
            ->get()
            ->map(fn (SystemNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        $unread = SystemNotification::query()->whereNull('read_at')->count();

        return response()->json([
            'unread_count' => $unread,
            'notifications' => $items,
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $n = SystemNotification::query()->findOrFail($id);
        $n->read_at = now();
        $n->save();

        return response()->json(['success' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        SystemNotification::query()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
