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
Artisan::command('game:prune-finished', function () {
    $days = app(\App\Services\RetentionPolicy::class)->days();
    if ($days === 0) { $this->info('Automatic cleanup disabled.'); return; }
    $cutoff = now()->subDays($days);
    $deleted = 0;
    \App\Models\Game::where('status', 'finished')->where('updated_at', '<', $cutoff)
        ->chunkById(50, function ($games) use ($cutoff, &$deleted) {
            foreach ($games as $game) {
                try {
                    if (app(\App\Services\GameCleanup::class)->remove($game, $cutoff)) $deleted++;
                } catch (\Throwable $error) {
                    \Illuminate\Support\Facades\Log::warning('Finished game cleanup failed', ['game_id' => $game->id, 'error' => $error->getMessage()]);
                }
            }
        });
    $this->info("Removed {$deleted} finished games.");
})->purpose('Remove finished games older than the configured retention period');
Schedule::command('game:prune-finished')->dailyAt('03:30')->withoutOverlapping();
Artisan::command('game:health', function () {
    \Illuminate\Support\Facades\DB::select('SELECT 1');
    \Illuminate\Support\Facades\Cache::put('healthcheck', true, 5);
    $this->info('ok');
});

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
