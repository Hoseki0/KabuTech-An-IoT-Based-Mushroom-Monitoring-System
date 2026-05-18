<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $remember = (bool) ($credentials['remember'] ?? false);
        $email    = $credentials['email'];
        $password = $credentials['password'];

        // ── 1. Try admin guard first ──────────────────────────────────────
        if (Auth::guard('admin')->attempt(['email' => $email, 'password' => $password], $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('admin.index'));
        }

        // ── 2. Try regular user guard ─────────────────────────────────────
        if (Auth::guard('web')->attempt(['email' => $email, 'password' => $password], $remember)) {
            $request->session()->regenerate();

            $user = Auth::guard('web')->user();

            // Block unverified users
            if ($user && ! $user->isVerified()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Your account is pending admin approval. Please wait for verification.',
                ])->onlyInput('email');
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'These credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email', 'unique:admins,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'password'    => Hash::make($validated['password']),
            'is_verified' => false,
        ]);

        return redirect()->route('login')
            ->with('status', 'Account created! Please wait for an admin to approve your account before logging in.');
    }

    public function logout(Request $request)
    {
        // Log out whichever guard is active
        Auth::guard('admin')->logout();
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
