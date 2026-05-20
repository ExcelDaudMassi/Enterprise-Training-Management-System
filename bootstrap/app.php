<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies for ngrok/expose to work properly with HTTPS and correct domains
        $middleware->trustProxies(at: '*');

        // Inertia middleware — wajib untuk semua request web
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Redirect unauthenticated users ke /login saat mengakses route yang di-protect
        $middleware->redirectGuestsTo('/login');

        // Custom alias: 'admin' dan 'user' middleware untuk proteksi halaman sesuai role
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'user'  => \App\Http\Middleware\EnsureUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
