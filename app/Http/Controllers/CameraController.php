<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CameraController extends Controller
{
    /**
     * Proxy the ESP32-CAM MJPEG stream so it works when the dashboard
     * is accessed via ngrok (browser can't reach the camera's local IP).
     */
    public function stream(): StreamedResponse
    {
        $cameraUrl = config('iot.camera_stream_url', '');

        if ($cameraUrl === '' || $cameraUrl === null) {
            abort(404, 'Camera stream not configured');
        }

        $client = new Client([
            'connect_timeout' => 8,
            'timeout' => 0.0,
            'http_errors' => false,
            'stream' => true,
            'headers' => [
                'Accept' => '*/*',
                'User-Agent' => 'Kabutech-Camera-Proxy/1',
            ],
        ]);

        try {
            $upstream = $client->get($cameraUrl);
        } catch (ConnectException $e) {
            Log::warning('Camera proxy connect failed', [
                'camera_url' => $cameraUrl,
                'error' => $e->getMessage(),
            ]);
            abort(502, config('app.debug') ? ('Camera unreachable: '.$e->getMessage()) : 'Camera unreachable');
        } catch (GuzzleException $e) {
            Log::warning('Camera proxy request failed', [
                'camera_url' => $cameraUrl,
                'error' => $e->getMessage(),
            ]);
            abort(502, config('app.debug') ? ('Camera unreachable: '.$e->getMessage()) : 'Camera unreachable');
        }

        $code = $upstream->getStatusCode();
        if ($code < 200 || $code >= 300) {
            Log::warning('Camera proxy bad status', [
                'camera_url' => $cameraUrl,
                'status' => $code,
                'content_type' => $upstream->getHeaderLine('Content-Type'),
            ]);
            abort(502, config('app.debug') ? ('Camera unreachable: upstream HTTP '.$code) : 'Camera unreachable');
        }

        $contentType = $upstream->getHeaderLine('Content-Type');
        if ($contentType === '') {
            $contentType = 'multipart/x-mixed-replace; boundary=frame';
        }

        return response()->stream(function () use ($upstream) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $body = $upstream->getBody();
            while (! $body->eof()) {
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
}
