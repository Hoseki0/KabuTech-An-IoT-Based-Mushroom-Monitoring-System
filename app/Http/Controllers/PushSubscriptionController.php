<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function vapidPublicKey(): JsonResponse
    {
        $key = trim((string) config('iot.vapid.public_key', ''));
        return response()->json(['publicKey' => $key]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'         => 'required|url|max:512',
            'keys.p256dh'      => 'required|string|max:255',
            'keys.auth'        => 'required|string|max:255',
            'contentEncoding'  => 'nullable|string|max:32',
        ]);

        PushSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'public_key'       => $data['keys']['p256dh'],
                'auth_token'       => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent'       => substr((string) $request->userAgent(), 0, 255),
            ]
        );

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => 'required|string|max:512',
        ]);

        PushSubscription::query()->where('endpoint', $data['endpoint'])->delete();

        return response()->json(['success' => true]);
    }

    public function sendTest(): JsonResponse
    {
        $result = WebPushService::sendToAll(
            "Fiona's Mushroom Farm",
            'Web Push notifications are active! You will receive grow room alerts here.',
            'test'
        );

        return response()->json([
            'success' => ($result['sent'] ?? 0) > 0,
            'sent'    => $result['sent'] ?? 0,
            'failed'  => $result['failed'] ?? 0,
            'reason'  => $result['reason'] ?? null,
        ]);
    }
}

