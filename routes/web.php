<?php

use App\Http\Controllers\Api\IoTController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\CameraController;
use App\Http\Controllers\GrowSettingsController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\BoxSettingController;
use App\Http\Controllers\DiagnosticController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HarvestController;
use App\Http\Controllers\RetentionController;
use App\Http\Controllers\SystemNotificationController;

use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Password reset
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');
});

Route::middleware('auth.any')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/api/csrf-token', function () {
        return response()->json(['token' => csrf_token()]);
    });

    Route::get('/api/grow-settings', [GrowSettingsController::class, 'show']);
    Route::put('/api/grow-settings', [GrowSettingsController::class, 'update']);

    // Harvest & Yield Tracking API
    Route::get('/api/harvest-logs',        [HarvestController::class, 'index']);
    Route::post('/api/harvest-logs',       [HarvestController::class, 'store']);
    Route::delete('/api/harvest-logs/{id}', [HarvestController::class, 'destroy'])->whereNumber('id');

    // Data & CSV Export API
    Route::get('/api/export/sensor-data',     [ExportController::class, 'exportSensorData']);
    Route::get('/api/export/harvest-logs',    [ExportController::class, 'exportHarvestLogs']);
    Route::get('/api/analytics/correlation',   [ExportController::class, 'getCorrelationAnalytics']);

    // Substrate Batches Lifecycle API
    Route::get('/api/batches',                [BatchController::class, 'index']);
    Route::post('/api/batches',               [BatchController::class, 'store']);
    Route::put('/api/batches/{id}',           [BatchController::class, 'update'])->whereNumber('id');
    Route::delete('/api/batches/{id}',        [BatchController::class, 'destroy'])->whereNumber('id');

    // Multi-Box Settings API
    Route::get('/api/box-settings',           [BoxSettingController::class, 'index']);
    Route::post('/api/box-settings',          [BoxSettingController::class, 'update']);
    Route::delete('/api/box-settings/{box_id}', [BoxSettingController::class, 'destroy']);


    // Hardware Diagnostics API
    Route::get('/api/diagnostics/hardware',   [DiagnosticController::class, 'getHardwareMetrics']);

    // Camera Snapshots Gallery API
    Route::post('/api/camera/snapshots',      [CameraController::class, 'captureSnapshot']);
    Route::get('/api/camera/snapshots',       [CameraController::class, 'getSnapshots']);
    Route::delete('/api/camera/snapshots/{id}', [CameraController::class, 'deleteSnapshot'])->whereNumber('id');

    // System Notifications API
    Route::get('/api/notifications',            [SystemNotificationController::class, 'index']);
    Route::post('/api/notifications/{id}/read',  [SystemNotificationController::class, 'markRead'])->whereNumber('id');
    Route::post('/api/notifications/read-all',   [SystemNotificationController::class, 'markAllRead']);

    Route::middleware('admin')->group(function () {
        Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
        Route::patch('/admin/users/{user}/verify', [AdminController::class, 'verify'])->name('admin.users.verify');
        Route::delete('/admin/users/{user}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');

        // Data Retention Policy
        Route::get('/admin/retention',        [RetentionController::class, 'index'])->name('admin.retention.index');
        Route::post('/admin/retention',       [RetentionController::class, 'update'])->name('admin.retention.update');
        Route::post('/admin/retention/prune', [RetentionController::class, 'prune'])->name('admin.retention.prune');
    });

});

// IoT API Routes (CSRF exempt for IoT devices)
Route::prefix('api')->withoutMiddleware(['web'])->group(function () {
    // Tiny health check for ESP32 / firewall testing (GET)
    Route::get('/ping', function () {
        return response()->json(['ok' => true]);
    });

    // Get latest sensor data
    Route::get('/sensor-data/latest', [IoTController::class, 'getLatest']);

    // Get latest reading for every incubator box at once
    Route::get('/sensor-data/boxes/latest', [IoTController::class, 'getBoxesLatest']);

    // Receive sensor data from IoT device (no CSRF required)
    Route::post('/sensor-data', [IoTController::class, 'receiveData']);

    // Control misting system (no CSRF required for API)
    Route::post('/misting/control', [IoTController::class, 'controlMisting']);

    // Get desired misting state (manual command)
    Route::get('/misting/status', [IoTController::class, 'getMistingStatus']);

    // Fan control (mirrors misting_control pattern)
    Route::post('/fan/control', [IoTController::class, 'controlFan']);
    Route::get('/fan/status',   [IoTController::class, 'getFanStatus']);

    // Get sensor data history
    Route::get('/sensor-data/history', [IoTController::class, 'getHistory']);

    // Camera live feed, snapshot frames & hardware control
    Route::get('/camera-stream', [CameraController::class, 'stream'])->name('camera.stream');
    Route::get('/camera/live-frame', [CameraController::class, 'liveFrame'])->name('camera.live_frame');
    Route::get('/camera/status', [CameraController::class, 'status'])->name('camera.status');
    Route::match(['get', 'post'], '/camera/control', [CameraController::class, 'control'])->name('camera.control');
});
