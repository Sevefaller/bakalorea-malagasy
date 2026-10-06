<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('game:tick', function () {
    \App\Models\Game::where('status','playing')->eachById(function ($game) {
        app(\App\Services\GameEngine::class)->locked($game, fn () => null);
    });
})->purpose('Advance round deadlines on the authoritative server');
Schedule::command('game:tick')->everySecond()->withoutOverlapping();
Artisan::command('game:health', function () {
    \Illuminate\Support\Facades\DB::select('SELECT 1');
    \Illuminate\Support\Facades\Cache::put('healthcheck', true, 5);
    $this->info('ok');
});

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
