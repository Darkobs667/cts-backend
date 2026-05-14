<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['admin' => \App\Http\Middleware\CheckAdmin::class]);

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->booted(function () {
        // Max 3 inscriptions par IP par heure
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(3)->by($request->ip())->response(function () {
                return response()->json([
                    'error' => 'Trop de tentatives d\'inscription. Réessayez dans 1 heure.'
                ], 429);
            });
        });

        // Max 5 tentatives de connexion par IP par minute
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip())->response(function () {
                    return response()->json([
                        'error' => 'Trop de tentatives de connexion. Réessayez dans 1 minute.'
                    ], 429);
                }),
                // Blocage supplémentaire par email : 5 tentatives par heure
                Limit::perHour(5)->by($request->input('email'))->response(function () {
                    return response()->json([
                        'error' => 'Compte temporairement bloqué suite à trop de tentatives. Réessayez dans 1 heure.'
                    ], 429);
                }),
            ];
        });
    })->create();
