<?php

use App\Http\Controllers\Api\IoTController;
use App\Http\Controllers\AdminController;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\CameraController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GrowSettingsController;
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
        if (\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
            return redirect()->route('admin.index');
        }
        return view('dashboard');
    })->name('dashboard');

    Route::get('/api/camera-stream', [CameraController::class, 'stream'])->name('camera.stream');

    Route::get('/api/csrf-token', function () {
        return response()->json(['token' => csrf_token()]);
    });

    Route::get('/api/grow-settings', [GrowSettingsController::class, 'show']);
    Route::put('/api/grow-settings', [GrowSettingsController::class, 'update']);
    Route::get('/api/notifications', [SystemNotificationController::class, 'index']);
    Route::post('/api/notifications/{id}/read', [SystemNotificationController::class, 'markRead'])->whereNumber('id');
    Route::post('/api/notifications/read-all', [SystemNotificationController::class, 'markAllRead']);

    Route::middleware('admin')->group(function () {
        Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
        Route::patch('/admin/users/{user}/verify', [AdminController::class, 'verify'])->name('admin.users.verify');
        Route::delete('/admin/users/{user}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
        Route::get('/admin/feedback', [AdminController::class, 'feedbacks'])->name('admin.feedback.index');
        Route::delete('/admin/feedback/{feedback}', [AdminController::class, 'destroyFeedback'])->name('admin.feedback.destroy');


    });

    Route::get('/feedback', fn () => redirect()->route('dashboard'));
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
});

// IoT API Routes (CSRF exempt for IoT devices)
Route::prefix('api')->withoutMiddleware(['web'])->group(function () {
    // Tiny health check for ESP32 / firewall testing (GET)
    Route::get('/ping', function () {
        return response()->json(['ok' => true]);
    });

    // Get latest sensor data
    Route::get('/sensor-data/latest', [IoTController::class, 'getLatest']);

    // Receive sensor data from IoT device (no CSRF required)
    Route::post('/sensor-data', [IoTController::class, 'receiveData']);

    // Control misting system (no CSRF required for API)
    Route::post('/misting/control', [IoTController::class, 'controlMisting']);

    // Get desired misting state (manual command)
    Route::get('/misting/status', [IoTController::class, 'getMistingStatus']);

    // Get sensor data history
    Route::get('/sensor-data/history', [IoTController::class, 'getHistory']);
});
