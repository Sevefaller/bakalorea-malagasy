<?php
namespace App\Services;

use App\Models\{Game,GamePlayer,Round,Answer};
use App\Events\GameChanged;
use Illuminate\Support\Facades\{DB,Cache,Log};
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GameEngine {
    public static function normalize(string $value): string { return mb_strtolower(preg_replace('/\s+/u', ' ', trim(Str::ascii($value)))); }

    public function locked(Game $game, callable $action): mixed {
        return Cache::lock('game:'.$game->id, 15)->block(5, fn () => DB::transaction(function () use ($game,$action) {
            $locked = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            $this->advance($locked);
            return $action($locked);
        }));
    }

    public function notify(Game $game, string $reason): void {
        try { GameChanged::dispatch($game->code, $reason); } catch (\Throwable $e) { Log::warning('Broadcast unavailable', ['game_id'=>$game->id,'error'=>$e->getMessage()]); }
    }

    public function advance(Game $game): void {
        $round = $game->rounds()->latest('number')->first();
        if ($round && $round->status === 'answering' && now()->gte($round->answer_deadline)) {
            $round->update(['status'=>'judging']);
            foreach ($round->answers()->get() as $answer) {
                $reason = $answer->normalized === '' ? 'empty' : (!str_starts_with($answer->normalized, strtolower($round->letter)) ? 'letter' : null);
                if ($game->anti_cheat_mode === 'strict' && $answer->flagged) $reason = 'strict';
                $answer->update(['is_locked'=>true, 'invalid_reason'=>$reason]);
            }
            foreach (DB::table('anti_cheat_events')->where('round_id',$round->id)->whereNull('ended_at')->get() as $event) {
                DB::table('anti_cheat_events')->where('id',$event->id)->update(['ended_at'=>$round->answer_deadline,'duration_ms'=>max(0,(int)\Carbon\Carbon::parse($event->started_at)->diffInMilliseconds($round->answer_deadline))]);
            }
            DB::afterCommit(fn () => $this->notify($game, 'RoundStopped'));
        }
        $host = $game->players()->find($game->host_id);
        if (!$host || $host->left_at || !$host->last_seen_at || $host->last_seen_at->lt(now()->subSeconds(45))) {
            $next = $game->players()->whereNull('left_at')->where('last_seen_at','>=',now()->subSeconds(45))->orderBy('id')->first();
            if ($next && $next->id !== $game->host_id) $game->update(['host_id'=>$next->id]);
        }
    }

    public function start(Game $game, GamePlayer $player): void {
        Cache::lock('round-selection', 15)->block(5, fn () => $this->locked($game, function (Game $game) use ($player) {
            abort_unless($game->host_id === $player->id, 403, 'host_only');
            abort_unless(in_array($game->status,['lobby','playing']), 409, 'game_finished');
            $previous = $game->rounds()->latest('number')->first();
            abort_if($previous && $previous->status !== 'finished', 409, 'round_active');
            $players = $game->players()->whereNull('left_at')->where('last_seen_at','>=',now()->subSeconds(45))->get();
            abort_if($players->count() < 2, 422, 'need_players');
            $letters = str_split($game->letters);
            $used = $game->used_letters ?? [];
            $available = $game->no_repeat ? array_values(array_diff($letters,$used)) : $letters;
            if (!$available) { $available = $letters; $used = []; }
            $number = ($previous?->number ?? 0) + 1;
            $categories = DB::table('game_categories')->where('game_id',$game->id)->orderBy('position')->pluck('category_id')->all();
            abort_if(!$categories, 409, 'no_categories');
            $cycleStart = intdiv($number - 1, count($categories)) * count($categories) + 1;
            $usedCategories = $game->rounds()->where('number','>=',$cycleStart)->pluck('category_id')->all();
            $availableCategories = array_values(array_diff($categories, $usedCategories));
            if (!$availableCategories) $availableCategories = $categories;
            // Favor pairs absent from recent rounds, including rounds in other rooms.
            // The random choice remains uniform among equally eligible pairs.
            $recent = DB::table('rounds')->orderByDesc('id')->limit(100)->get(['category_id', 'letter']);
            $pairsByAge = [];
            foreach ($availableCategories as $categoryId) {
                foreach ($available as $candidateLetter) {
                    $age = $recent->search(fn ($round) => $round->category_id === $categoryId && $round->letter === $candidateLetter);
                    $pairsByAge[$age === false ? 100 : $age][] = [$categoryId, $candidateLetter];
                }
            }
            $oldest = max(array_keys($pairsByAge));
            [$category, $letter] = $pairsByAge[$oldest][random_int(0, count($pairsByAge[$oldest]) - 1)];
            $start = now()->addSeconds(3);
            $round = $game->rounds()->create(['number'=>$number,'category_id'=>$category,'letter'=>$letter,'started_at'=>$start,'answer_deadline'=>$start->copy()->addSeconds($game->answer_duration)]);
            foreach ($players as $p) $round->answers()->create(['player_id'=>$p->id]);
            $game->update(['status'=>'playing','used_letters'=>array_merge($used,[$letter])]);
        }));
        $this->notify($game,'RoundStarted');
    }

    public function answer(Round $round, GamePlayer $player, string $text, int $revision): void {
        $this->locked($round->game, function () use ($round,$player,$text,$revision) {
            $round->refresh();
            if ($round->status !== 'answering' || now()->gte($round->answer_deadline)) { Log::notice('Late answer rejected',['round_id'=>$round->id,'player_id'=>$player->id]); abort(409,'answer_closed'); }
            abort_if(now()->lt($round->started_at),409,'not_started');
            $answer = $round->answers()->where('player_id',$player->id)->firstOrFail();
            if ($revision > $answer->revision) $answer->update(['answer'=>trim($text),'normalized'=>self::normalize($text),'revision'=>$revision,'last_saved_at'=>now()]);
        });
    }

    public function vote(Answer $answer, GamePlayer $player, string $vote): void {
        $this->locked($answer->round->game, function () use ($answer,$player,$vote) {
            $answer->refresh(); $round = $answer->round()->first();
            abort_unless($round->status === 'judging',409,'not_judging');
            abort_if($answer->player_id === $player->id,403,'self_vote');
            abort_unless($round->answers()->where('player_id',$player->id)->exists(),403,'not_participant');
            abort_if($answer->invalid_reason,422,'invalid_answer');
            DB::table('votes')->updateOrInsert(['answer_id'=>$answer->id,'voter_player_id'=>$player->id],['vote'=>$vote]);
            $answer->update(['tie_decision'=>null]);
        });
        $this->notify($answer->round->game,'VoteUpdated');
    }

    public function react(Answer $answer, GamePlayer $player, ?string $reaction): void {
        $this->locked($answer->round->game, function () use ($answer,$player,$reaction) {
            $round = $answer->round()->first();
            abort_unless($round->status === 'judging',409,'not_judging');
            $key = ['answer_id'=>$answer->id,'player_id'=>$player->id];
            if ($reaction === null) DB::table('answer_reactions')->where($key)->delete();
            else DB::table('answer_reactions')->updateOrInsert($key,['reaction'=>$reaction]);
        });
        $this->notify($answer->round->game,'ReactionUpdated');
    }

    public function comment(Round $round, GamePlayer $player, string $body, string $clientId): array {
        $result = $this->locked($round->game, function () use ($round,$player,$body,$clientId) {
            $round->refresh();
            $hasClientId = Schema::hasColumn('round_comments', 'client_id');
            if ($hasClientId) {
                $existing = DB::table('round_comments')->where('round_id',$round->id)->where('player_id',$player->id)->where('client_id',$clientId)->first();
                if ($existing) return ['id'=>$existing->id,'created'=>false];
            }
            abort_unless($round->status === 'judging',409,'not_judging');
            $values = ['round_id'=>$round->id,'player_id'=>$player->id,'body'=>$body,'created_at'=>now()];
            if ($hasClientId) $values['client_id'] = $clientId;
            $id = DB::table('round_comments')->insertGetId($values);
            return ['id'=>$id,'created'=>true];
        });
        if ($result['created']) $this->notify($round->game,'CommentAdded');
        return $result;
    }

    public function referee(Game $game, Answer $answer): ?int {
        if ($answer->player_id !== $game->host_id) return $game->host_id;
        return GamePlayer::whereIn('id',$answer->round->answers()->pluck('player_id'))->where('id','!=',$answer->player_id)->whereNull('left_at')->where('last_seen_at','>=',now()->subSeconds(45))->orderBy('id')->value('id');
    }

    public function tally(Answer $answer): array {
        $votes = DB::table('votes')->where('answer_id',$answer->id)->get();
        $yes = $votes->where('vote','valid')->count(); $no = $votes->where('vote','invalid')->count();
        return ['valid'=>$yes,'invalid'=>$no,'uncertain'=>$votes->where('vote','uncertain')->count(),'count'=>$votes->count(),'tied'=>$yes === $no,'result'=>$yes > $no];
    }

    public function ready(Round $round): bool {
        if (now()->gte($round->answer_deadline->copy()->addSeconds(30))) return true;
        $answers = $round->answers()->get();
        $active = GamePlayer::whereIn('id',$answers->pluck('player_id'))->whereNull('left_at')->where('last_seen_at','>=',now()->subSeconds(45))->pluck('id');
        foreach ($answers as $a) if (!$a->invalid_reason) {
            $expected = $active->reject(fn ($id) => $id === $a->player_id);
            $received = DB::table('votes')->where('answer_id',$a->id)->pluck('voter_player_id');
            if ($expected->diff($received)->isNotEmpty()) return false;
        }
        return true;
    }

    public function decide(Answer $answer, GamePlayer $player, bool $decision): void {
        $this->locked($answer->round->game, function (Game $game) use ($answer,$player,$decision) {
            $answer->refresh(); $round = $answer->round()->first();
            abort_unless($round->status === 'judging' && $this->ready($round),409,'votes_pending');
            abort_unless($this->referee($game,$answer) === $player->id,403,'referee_only');
            abort_unless(!$answer->invalid_reason && $this->tally($answer)['tied'],422,'not_tied');
            $answer->update(['tie_decision'=>$decision]);
        });
        $this->notify($answer->round->game,'VoteUpdated');
    }

    public function finish(Round $round, GamePlayer $player): void {
        $this->locked($round->game, function (Game $game) use ($round,$player) {
            abort_unless($game->host_id === $player->id,403,'host_only');
            $round->refresh();
            abort_unless($round->status === 'judging',409,'not_judging');
            abort_unless($this->ready($round),409,'votes_pending');
            $answers = $round->answers()->get();
            foreach ($answers as $a) {
                $t = $this->tally($a);
                // An absent referee cannot block a remaining player indefinitely.
                // The host never gains points by judging their own answer.
                if (!$a->invalid_reason && $t['tied'] && $this->referee($game,$a) === null && now()->gte($round->answer_deadline->copy()->addSeconds(30))) $a->tie_decision = false;
                abort_if(!$a->invalid_reason && $t['tied'] && $a->tie_decision === null,409,'ties_pending');
                $a->verdict = !$a->invalid_reason && ($t['tied'] ? $a->tie_decision : $t['result']);
            }
            $counts = $answers->where('verdict',true)->countBy('normalized');
            foreach ($answers as $a) {
                $a->points_awarded = $a->verdict ? (($counts[$a->normalized] ?? 0)>1 ? $game->duplicate_points : $game->unique_points) : 0;
                $a->save(); GamePlayer::whereKey($a->player_id)->increment('score',$a->points_awarded);
            }
            $round->update(['status'=>'finished','finished_at'=>now()]);
            $ranking = $game->players()->orderByDesc('score')->get();
            $best = $ranking->first();
            if ($best->score >= $game->target_score && $ranking->where('score',$best->score)->count() === 1) $game->update(['status'=>'finished','winner_id'=>$best->id]);
        });
        $this->notify($round->game,'RoundFinished');
    }

    public function antiCheat(Round $round, GamePlayer $player, string $type): void {
        $this->locked($round->game, function () use ($round,$player,$type) {
            $round->refresh();
            abort_unless($round->status === 'answering' && now()->gte($round->started_at) && now()->lt($round->answer_deadline),409,'answer_closed');
            $answer = $round->answers()->where('player_id',$player->id)->firstOrFail();
            $events = DB::table('anti_cheat_events')->where('round_id',$round->id)->where('player_id',$player->id)->whereNull('ended_at');
            if ($type === 'away') {
                if (!$events->exists()) DB::table('anti_cheat_events')->insert(['round_id'=>$round->id,'player_id'=>$player->id,'event_type'=>'away','started_at'=>now()]);
                $answer->update(['flagged'=>true]);
            } else {
                foreach ($events->get() as $event) $events->where('id',$event->id)->update(['ended_at'=>now(),'duration_ms'=>max(0,(int)\Carbon\Carbon::parse($event->started_at)->diffInMilliseconds(now()))]);
            }
        });
    }

    public function snapshot(Game $game, GamePlayer $player): array {
        return $this->locked($game, function (Game $game) use ($player) {
            $players = $game->players()->orderBy('id')->get();
            $round = $game->rounds()->latest('number')->first();
            $data = ['id'=>$game->id,'code'=>$game->code,'name'=>$game->name,'status'=>$game->status,'host_id'=>$game->host_id,'winner_id'=>$game->winner_id,'target_score'=>$game->target_score,'answer_duration'=>$game->answer_duration,'anti_cheat_mode'=>$game->anti_cheat_mode,'unique_points'=>$game->unique_points,'duplicate_points'=>$game->duplicate_points,'letters'=>$game->letters,'no_repeat'=>$game->no_repeat,'me_id'=>$player->id,'server_now'=>now()->getTimestampMs(),'players'=>$players->map(fn ($p) => ['id'=>$p->id,'nickname'=>$p->nickname,'score'=>$p->score,'left'=>(bool)$p->left_at,'online'=>!$p->left_at && $p->last_seen_at?->gte(now()->subSeconds(45))])->values(),'round'=>null];
            if ($round) {
                $own = $round->answers()->where('player_id',$player->id)->first();
                $data['round'] = ['id'=>$round->id,'number'=>$round->number,'category'=>DB::table('categories')->where('id',$round->category_id)->value('code'),'letter'=>$round->letter,'status'=>$round->status,'started_at'=>$round->started_at->getTimestampMs(),'answer_deadline'=>$round->answer_deadline->getTimestampMs(),'own_answer'=>$own?->answer ?? '','own_revision'=>$own?->revision ?? 0,'participating'=>(bool)$own,'ready'=>$round->status === 'judging' && $this->ready($round)];
                if ($round->status === 'judging') {
                    $columns = ['id','player_id','body','created_at'];
                    $columns[] = Schema::hasColumn('round_comments', 'client_id') ? 'client_id' : DB::raw('NULL AS client_id');
                    $data['round']['comments'] = DB::table('round_comments')->where('round_id',$round->id)->orderByDesc('id')->limit(100)->get($columns)->reverse()->values();
                }
                if ($round->status !== 'answering') $data['round']['answers'] = $round->answers()->get()->map(function ($a) use ($game,$player) {
                    $referee = $this->referee($game,$a);
                    $decision = $a->tie_decision;
                    if ($referee === null && now()->gte($a->round->answer_deadline->copy()->addSeconds(30))) $decision = false;
                    return ['id'=>$a->id,'player_id'=>$a->player_id,'answer'=>$a->answer,'flagged'=>$game->anti_cheat_mode !== 'soft' && $a->flagged,'invalid_reason'=>$a->invalid_reason,'verdict'=>$a->verdict,'points'=>$a->points_awarded,'tie_decision'=>$decision,'referee_id'=>$referee,'votes'=>$this->tally($a),'reactions'=>DB::table('answer_reactions')->where('answer_id',$a->id)->get()->countBy('reaction')->all(),'my_reaction'=>DB::table('answer_reactions')->where('answer_id',$a->id)->where('player_id',$player->id)->value('reaction'),'my_vote'=>DB::table('votes')->where('answer_id',$a->id)->where('voter_player_id',$player->id)->value('vote')];
                })->values();
            }
            $data['history'] = $game->rounds()->where('status','finished')->orderByDesc('number')->limit(50)->get()->map(fn ($r) => ['number'=>$r->number,'letter'=>$r->letter,'category'=>DB::table('categories')->where('id',$r->category_id)->value('code'),'answers'=>$r->answers()->get(['player_id','answer','points_awarded','verdict'])]);
            return $data;
        });
    }
}
