<?php
namespace App\Services;

use App\Models\Game;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Cache, DB};

class GameCleanup {
    public function canDelete(Game $game, ?string $startedAt = null): bool {
        if (in_array($game->status, ['lobby', 'finished'], true)) return true;
        if ($game->status !== 'playing') return false;
        $startedAt ??= $game->rounds()->oldest('started_at')->value('started_at');
        return $startedAt !== null && Carbon::parse($startedAt)->lt(now()->subHours(3));
    }

    public function remove(Game $game, ?Carbon $finishedBefore = null): bool {
        return Cache::lock('game:'.$game->id, 15)->block(5, function () use ($game, $finishedBefore) {
            return DB::transaction(function () use ($game, $finishedBefore) {
                $locked = Game::whereKey($game->id)->lockForUpdate()->first();
                if (!$locked) return false;
                if ($finishedBefore) {
                    if ($locked->status !== 'finished' || !$locked->updated_at->lt($finishedBefore)) return false;
                } else abort_unless($this->canDelete($locked), 409, 'game_not_deletable');
                foreach ($locked->players()->get() as $player) $player->tokens()->delete();
                // Database cascades remove answers, votes, comments, and anti-cheat events.
                $locked->rounds()->delete();
                $locked->players()->delete();
                $locked->delete();
                return true;
            });
        });
    }
}
