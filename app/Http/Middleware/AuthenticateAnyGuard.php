<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that passes if EITHER the 'web' (user) guard OR the 'admin' guard
 * has an active session. This replaces the default 'auth' middleware on routes
 * that should be accessible by both regular users and administrators.
 */
class AuthenticateAnyGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check() || Auth::guard('admin')->check()) {
            return $next($request);
        }

        return redirect()->route('login');
    }
}
