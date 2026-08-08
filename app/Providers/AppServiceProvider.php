<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(3)->by($request->ip()));
        RateLimiter::for('refresh', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('vote', fn (Request $request) => Limit::perMinute(5)
            ->by((string) optional($request->user('api'))->id ?: $request->ip()));
        RateLimiter::for('application', fn (Request $request) => Limit::perHour(3)
            ->by((string) optional($request->user('api'))->id ?: $request->ip()));
        RateLimiter::for('upload', fn (Request $request) => Limit::perMinute(6)
            ->by((string) optional($request->user('api'))->id ?: $request->ip()));
        RateLimiter::for('admin-write', fn (Request $request) => Limit::perMinute(30)
            ->by((string) optional($request->user('api'))->id ?: $request->ip()));
    }
}
