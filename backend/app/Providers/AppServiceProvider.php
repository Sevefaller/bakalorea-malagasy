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
        $playerKey = fn (Request $request) => 'player:'.($request->user()?->getAuthIdentifier() ?? $request->ip());
        RateLimiter::for('game-requests', fn (Request $request) => Limit::perMinute(240)->by($playerKey($request)));
        RateLimiter::for('game-answers', fn (Request $request) => Limit::perMinute(120)->by($playerKey($request)));
        RateLimiter::for('round-comments', fn (Request $request) => Limit::perMinute(30)->by($playerKey($request)));
    }
}
