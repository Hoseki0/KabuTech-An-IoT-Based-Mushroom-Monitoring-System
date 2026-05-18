<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust ngrok (and other reverse proxies) so Laravel uses correct URL/scheme
        $middleware->trustProxies(at: '*');

        // Redirect unauthenticated guests to login
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Redirect already-authenticated users — admins go to admin panel, users go to dashboard
        $middleware->redirectUsersTo(function () {
            if (\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
                return route('admin.index');
            }
            return '/';
        });

        $middleware->alias([
            'admin'    => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'auth.any' => \App\Http\Middleware\AuthenticateAnyGuard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // When the CSRF token expires (419), redirect to login gracefully
        // instead of showing the "Page Expired" error page.
        $exceptions->render(function (TokenMismatchException $e, $request) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your session expired. Please log in again.']);
        });
    })->create();
