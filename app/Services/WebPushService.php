<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

final class WebPushService
{
    public static function sendToAll(string $title, string $body, string $type = 'alert'): array
    {
        $publicKey  = trim((string) config('iot.vapid.public_key', ''));
        $privateKey = trim((string) config('iot.vapid.private_key', ''));
        $subject    = trim((string) config('iot.vapid.subject', 'mailto:admin@example.com'));

        if ($publicKey === '' || $privateKey === '') {
            return ['sent' => 0, 'failed' => 0, 'reason' => 'VAPID keys not configured'];
        }

        $subscriptions = PushSubscription::query()->get();
        if ($subscriptions->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'reason' => 'No subscribed devices'];
        }

        self::ensureOpenSslConf();

        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => $subject,
                'publicKey'  => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'type'  => $type,
            'url'   => '/',
        ]);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->public_key,
                    'authToken'       => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding ?: 'aes128gcm',
                ]),
                $payload
            );
        }

        $sent = 0;
        $failed = 0;

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            if ($report->isSuccess()) {
                $sent++;
            } else {
                $failed++;
                $reason = $report->getReason();
                Log::warning('WebPush delivery failed', ['endpoint' => $endpoint, 'reason' => $reason]);
                // Remove expired or unsubscribed endpoints automatically
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::query()->where('endpoint', $endpoint)->delete();
                }
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    private static function ensureOpenSslConf(): void
    {
        if (getenv('OPENSSL_CONF')) {
            return;
        }
        $candidates = [
            'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
            'C:\\xampp\\apache\\conf\\openssl.cnf',
            'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                putenv('OPENSSL_CONF=' . $path);
                return;
            }
        }
    }
}

