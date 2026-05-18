<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    // ── 1. Show the "Forgot Password" form ────────────────────────────────
    public function showForgotForm(): View
    {
        return view('auth.forgot-password');
    }

    // ── 2. Send the reset-link email ───────────────────────────────────────
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->first();

        // Always return the same message to prevent email enumeration
        if (! $user) {
            return back()->with('status', 'If that email exists in our system, a reset link has been sent.');
        }

        // Generate a secure token and store/overwrite in the DB
        $token = Str::random(64);

        DB::table('password_reset_tokens')->upsert(
            [
                'email'      => $user->email,
                'token'      => Hash::make($token),
                'created_at' => now(),
            ],
            ['email'],          // unique key
            ['token', 'created_at']
        );

        // Build the reset URL
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        // Send the email
        Mail::send('auth.emails.reset-password', [
            'user'     => $user,
            'resetUrl' => $resetUrl,
            'expiry'   => 60, // minutes
        ], function ($message) use ($user) {
            $message->to($user->email, $user->name)
                    ->subject('Reset Your KABUTECH Password');
        });

        return back()->with('status', 'If that email exists in our system, a reset link has been sent.');
    }

    // ── 3. Show the "Reset Password" form ────────────────────────────────
    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    // ── 4. Actually reset the password ───────────────────────────────────
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Find the token record
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return back()->withErrors(['email' => 'This password reset link is invalid.']);
        }

        // Check expiry (60 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return back()->withErrors(['email' => 'This password reset link has expired. Please request a new one.']);
        }

        // Verify token
        if (! Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'This password reset link is invalid.']);
        }

        // Find the user and update password
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No account found for this email.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Clean up the token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('login')
            ->with('status', 'Your password has been reset successfully. You can now sign in.');
    }
}
