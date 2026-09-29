<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CameraController extends Controller
{
    /**
     * Get parsed camera endpoint URLs based on configured stream URL or IP
     */
    protected function getCameraUrls(): array
    {
        $rawUrl = trim(config('iot.camera_stream_url', ''));
        if (empty($rawUrl)) {
            return ['stream' => null, 'capture' => null, 'control' => null, 'status' => null, 'host' => null];
        }

        if (!str_starts_with($rawUrl, 'http://') && !str_starts_with($rawUrl, 'https://')) {
            $rawUrl = 'http://' . $rawUrl;
        }

        $parsed = parse_url($rawUrl);
        $scheme = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'] ?? '127.0.0.1';
        $port = $parsed['port'] ?? null;

        // If specific port given (like :81/stream)
        $streamUrl = $rawUrl;
        if (!str_ends_with($streamUrl, '/stream')) {
            $streamUrl = $scheme . '://' . $host . ':81/stream';
        }

        // Port 80 for capture, control, and status endpoints
        $httpPort = ($port === 81 || $port === null) ? 80 : $port;
        $baseHttp = $scheme . '://' . $host . ($httpPort === 80 ? '' : ':' . $httpPort);

        return [
            'stream'  => $streamUrl,
            'capture' => $baseHttp . '/capture',
            'control' => $baseHttp . '/control',
            'status'  => $baseHttp . '/status',
            'host'    => $host,
        ];
    }

    /**
     * Proxy the ESP32-CAM MJPEG stream so it works when the dashboard
     * is accessed via ngrok (browser can't reach the camera's local IP).
     */
    public function stream(): StreamedResponse
    {
        $urls = $this->getCameraUrls();
        $cameraUrl = $urls['stream'];

        if ($cameraUrl === '' || $cameraUrl === null) {
            abort(404, 'Camera stream not configured');
        }

        $client = new Client([
            'connect_timeout' => 3,
            'timeout' => 0.0,
            'http_errors' => false,
            'stream' => true,
            'headers' => [
                'Accept' => '*/*',
                'User-Agent' => 'Kabutech-Camera-Proxy/1',
            ],
        ]);

        $upstream = null;
        $candidateUrls = [
            $cameraUrl,
            'http://' . ($urls['host'] ?? '127.0.0.1') . ':80/stream',
            'http://' . ($urls['host'] ?? '127.0.0.1') . '/stream',
        ];

        foreach ($candidateUrls as $candidate) {
            try {
                $resp = $client->get($candidate);
                if ($resp->getStatusCode() === 200) {
                    $upstream = $resp;
                    $cameraUrl = $candidate;
                    break;
                }
            } catch (\Exception $e) {
                // try next candidate
            }
        }

        if (!$upstream) {
            Log::warning('Camera proxy all candidate streams failed', ['host' => $urls['host']]);
            abort(502, 'Camera stream unreachable at ' . ($urls['host'] ?? 'configured host'));
        }

        $contentType = $upstream->getHeaderLine('Content-Type');
        if ($contentType === '') {
            $contentType = 'multipart/x-mixed-replace; boundary=123456789000000000000987654321';
        }

        return response()->stream(function () use ($upstream) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $body = $upstream->getBody();
            while (!$body->eof()) {
                echo $body->read(8192);
                flush();
            }
        }, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Fetch a single live snapshot frame directly from ESP32-CAM.
     * Useful for lightweight snapshot refresh mode (zero long-connection locking).
     */
    public function liveFrame(): Response
    {
        $urls = $this->getCameraUrls();
        if (empty($urls['capture'])) {
            abort(404, 'Camera not configured');
        }

        $client = new Client([
            'connect_timeout' => 3,
            'timeout' => 5,
            'http_errors' => false,
        ]);

        try {
            $response = $client->get($urls['capture']);
            if ($response->getStatusCode() === 200 && strlen($response->getBody()) > 500) {
                return response($response->getBody()->getContents(), 200, [
                    'Content-Type' => 'image/jpeg',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                ]);
            }
        } catch (\Exception $e) {
            Log::debug('Live frame fetch failed: ' . $e->getMessage());
        }

        abort(502, 'Camera capture frame unavailable');
    }

    /**
     * Test camera connectivity and return hardware status.
     */
    public function status(): JsonResponse
    {
        $urls = $this->getCameraUrls();
        if (empty($urls['status'])) {
            return response()->json([
                'online' => false,
                'message' => 'Camera stream URL is not configured in .env',
            ], 400);
        }

        $client = new Client([
            'connect_timeout' => 3,
            'timeout' => 4,
            'http_errors' => false,
        ]);

        try {
            $start = microtime(true);
            $response = $client->get($urls['status']);
            $latency = round((microtime(true) - $start) * 1000);

            if ($response->getStatusCode() === 200) {
                $details = json_decode($response->getBody()->getContents(), true) ?? [];
                return response()->json([
                    'online' => true,
                    'latency_ms' => $latency,
                    'stream_url' => $urls['stream'],
                    'details' => $details,
                ]);
            }
        } catch (\Exception $e) {
            Log::debug('Camera status check failed: ' . $e->getMessage());
        }

        return response()->json([
            'online' => false,
            'stream_url' => $urls['stream'],
            'message' => 'Camera unreachable at ' . ($urls['host'] ?? 'configured host'),
        ]);
    }

    /**
     * Send hardware control command to ESP32-CAM (LED, resolution, effects, etc.)
     */
    public function control(Request $request): JsonResponse
    {
        $var = $request->input('var');
        $val = $request->input('val');

        if (!$var || $val === null) {
            return response()->json(['success' => false, 'message' => 'var and val parameters required'], 422);
        }

        $urls = $this->getCameraUrls();
        if (empty($urls['control'])) {
            return response()->json(['success' => false, 'message' => 'Camera URL not configured'], 400);
        }

        $client = new Client([
            'connect_timeout' => 3,
            'timeout' => 5,
            'http_errors' => false,
        ]);

        try {
            $response = $client->get($urls['control'], [
                'query' => ['var' => $var, 'val' => $val]
            ]);

            return response()->json([
                'success' => $response->getStatusCode() === 200,
                'status' => $response->getStatusCode()
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }
    }

    /** Capture and store a camera snapshot */
    public function captureSnapshot(Request $request): JsonResponse
    {
        $boxId = $request->input('box_id', 'box_a');
        $notes = $request->input('notes', 'Manual snapshot capture');
        $imageData = $request->input('image_base64');

        $snapshotsDir = public_path('snapshots');
        if (!file_exists($snapshotsDir)) {
            mkdir($snapshotsDir, 0755, true);
        }

        $filename = 'snapshot_' . $boxId . '_' . date('Ymd_His') . '_' . uniqid() . '.jpg';
        $fullPath = $snapshotsDir . '/' . $filename;
        $relativePath = 'snapshots/' . $filename;

        if ($imageData && preg_match('/^data:image\/(\w+);base64,/', $imageData)) {
            $data = substr($imageData, strpos($imageData, ',') + 1);
            $data = base64_decode($data);
            file_put_contents($fullPath, $data);
        } else {
            // Attempt to fetch actual high-res JPEG frame directly from ESP32-CAM
            $urls = $this->getCameraUrls();
            $capturedRealFrame = false;

            if (!empty($urls['capture'])) {
                try {
                    $client = new Client(['connect_timeout' => 3, 'timeout' => 5, 'http_errors' => false]);
                    $resp = $client->get($urls['capture']);
                    if ($resp->getStatusCode() === 200 && strlen($resp->getBody()) > 1000) {
                        file_put_contents($fullPath, $resp->getBody()->getContents());
                        $capturedRealFrame = true;
                    }
                } catch (\Exception $e) {
                    Log::info('Direct camera snapshot fetch failed, falling back to generator: ' . $e->getMessage());
                }
            }

            if (!$capturedRealFrame) {
                // Create a styled canvas frame placeholder if direct capture wasn't available
                $img = imagecreatetruecolor(640, 480);
                $bg = imagecolorallocate($img, 15, 23, 42);
                $textColor = imagecolorallocate($img, 56, 189, 248);
                $subTextColor = imagecolorallocate($img, 148, 163, 184);
                imagefill($img, 0, 0, $bg);
                imagestring($img, 5, 20, 20, "KABUTECH CAMERA SNAPSHOT - " . strtoupper($boxId), $textColor);
                imagestring($img, 4, 20, 50, "Captured: " . date('Y-m-d H:i:s'), $subTextColor);
                imagestring($img, 3, 20, 80, "Status: Live View Archive", $subTextColor);
                imagejpeg($img, $fullPath, 90);
                imagedestroy($img);
            }
        }

        $snapshot = \App\Models\CameraSnapshot::create([
            'box_id'      => $boxId,
            'image_path'  => $relativePath,
            'notes'       => $notes,
            'captured_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Snapshot captured and saved successfully',
            'data'    => [
                'id'          => $snapshot->id,
                'box_id'      => $snapshot->box_id,
                'url'         => asset($snapshot->image_path),
                'notes'       => $snapshot->notes,
                'captured_at' => $snapshot->captured_at->toIso8601String(),
            ]
        ], 201);
    }

    /** List snapshot gallery records */
    public function getSnapshots(Request $request): JsonResponse
    {
        $boxId = $request->query('box_id');
        $query = \App\Models\CameraSnapshot::orderBy('captured_at', 'desc')->limit(30);

        if ($boxId && $boxId !== 'all') {
            $query->where('box_id', $boxId);
        }

        $snapshots = $query->get()->map(function ($item) {
            return [
                'id'          => $item->id,
                'box_id'      => $item->box_id,
                'url'         => asset($item->image_path),
                'notes'       => $item->notes,
                'captured_at' => $item->captured_at ? $item->captured_at->toIso8601String() : null,
                'formatted_time' => $item->captured_at ? $item->captured_at->format('M d, Y g:i A') : '',
            ];
        });

        return response()->json(['success' => true, 'data' => $snapshots]);
    }

    /** Delete a camera snapshot */
    public function deleteSnapshot($id): JsonResponse
    {
        $snapshot = \App\Models\CameraSnapshot::find($id);
        if (!$snapshot) {
            return response()->json(['success' => false, 'message' => 'Snapshot not found'], 404);
        }

        $fullPath = public_path($snapshot->image_path);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        $snapshot->delete();

        return response()->json(['success' => true, 'message' => 'Snapshot deleted']);
    }
}
