<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\{Game,GamePlayer,Round,Answer};
use App\Services\GameEngine;

class GameTest extends TestCase {
    use RefreshDatabase;
    private array $host;
    private array $guest;
    private string $code;
    private Game $game;
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null) {
        $this->app['auth']->forgetGuards();
        return parent::call($method,$uri,$parameters,$cookies,$files,$server,$content);
    }
    protected function setUp(): void {
        parent::setUp();
        $session=(string)Str::uuid();
        $r=$this->postJson('/api/games',['nickname'=>'Lova','locale'=>'mg','session_id'=>$session,'name'=>'Fianakaviana','target_score'=>20,'answer_duration'=>15,'anti_cheat_mode'=>'normal','letters'=>'AB','no_repeat'=>true,'unique_points'=>10,'duplicate_points'=>5])->assertCreated()->json();
        $this->code=$r['code']; $this->host=['Authorization'=>'Bearer '.$r['token'],'X-Session-ID'=>$session];
        $this->game=Game::where('code',$this->code)->firstOrFail();
        $session=(string)Str::uuid();
        $r=$this->postJson('/api/games/'.$this->code.'/join',['nickname'=>'Mialy','locale'=>'fr','session_id'=>$session])->assertCreated()->json();
        $this->guest=['Authorization'=>'Bearer '.$r['token'],'X-Session-ID'=>$session];
    }
    private function startRound(): Round {
        $this->postJson('/api/games/'.$this->game->id.'/start',[],$this->host)->assertOk();
        $round=$this->game->rounds()->latest('number')->first();
        $this->travelTo($round->started_at->copy()->addSecond());
        return $round;
    }
    private function stopRound(Round $round): void {
        $this->travelTo($round->answer_deadline->copy()->addSecond());
        $this->getJson('/api/games/'.$this->code,$this->host)->assertOk();
    }
    private function submit(Round $round,array $headers,string $text,int $revision=1) { return $this->patchJson('/api/rounds/'.$round->id.'/answer',['answer'=>$text,'revision'=>$revision],$headers); }

    public function test_answers_are_private_before_stop_and_revealed_after(): void {
        $round=$this->startRound(); $this->submit($round,$this->host,$round->letter.'lina')->assertOk();
        $json=$this->getJson('/api/games/'.$this->code,$this->guest)->assertOk()->json();
        $this->assertArrayNotHasKey('answers',$json['round']); $this->assertSame('',$json['round']['own_answer']);
        $this->assertStringNotContainsString('session_id',json_encode($json));
        $this->stopRound($round);
        $this->getJson('/api/games/'.$this->code,$this->guest)->assertJsonPath('round.answers.0.answer',$round->letter.'lina');
    }
    public function test_late_and_early_answers_are_rejected(): void {
        $this->postJson('/api/games/'.$this->game->id.'/start',[],$this->host)->assertOk(); $round=$this->game->rounds()->first();
        $this->submit($round,$this->host,'Early')->assertStatus(409);
        $this->travelTo($round->started_at->copy()->addSecond());
        $this->submit($round,$this->host,$round->letter.'ina')->assertOk();
        $this->travelTo($round->answer_deadline);
        $this->submit($round,$this->host,'Late',2)->assertStatus(409);
        $this->assertSame($round->letter.'ina',$round->answers()->first()->answer);
    }
    public function test_stale_autosaves_do_not_overwrite_newer_answers(): void {
        $round=$this->startRound(); $this->submit($round,$this->host,'New',2)->assertOk(); $this->submit($round,$this->host,'Old',1)->assertOk();
        $this->assertSame('New',$round->answers()->first()->answer);
    }
    public function test_only_host_starts_and_double_start_creates_one_round(): void {
        $this->postJson('/api/games/'.$this->game->id.'/start',[],$this->guest)->assertForbidden(); $this->startRound();
        $this->postJson('/api/games/'.$this->game->id.'/start',[],$this->host)->assertStatus(409);
        $this->assertSame(1,$this->game->rounds()->count());
    }
    public function test_categories_do_not_repeat_before_the_random_pool_is_exhausted(): void {
        $pool = DB::table('game_categories')->where('game_id',$this->game->id)->orderBy('position')->limit(3)->pluck('category_id')->all();
        DB::table('game_categories')->where('game_id',$this->game->id)->whereNotIn('category_id',$pool)->delete();
        $selected = [];
        for ($i=0; $i<4; $i++) {
            $round = $this->startRound();
            $selected[] = $round->category_id;
            $round->update(['status'=>'finished']);
        }
        $this->assertEqualsCanonicalizing($pool, array_slice($selected,0,3));
        $this->assertContains($selected[3], $pool);
    }
    public function test_recent_category_letter_pair_is_avoided_across_games(): void {
        $first = $this->startRound();
        $session = (string) Str::uuid();
        $created = $this->postJson('/api/games', ['nickname'=>'Other host','locale'=>'fr','session_id'=>$session,'name'=>'Second room','target_score'=>20,'answer_duration'=>15,'anti_cheat_mode'=>'normal','letters'=>'AB','no_repeat'=>true,'unique_points'=>10,'duplicate_points'=>5])->assertCreated()->json();
        $other = Game::where('code', $created['code'])->firstOrFail();
        $headers = ['Authorization'=>'Bearer '.$created['token'],'X-Session-ID'=>$session];
        $this->postJson('/api/games/'.$created['code'].'/join', ['nickname'=>'Other guest','locale'=>'fr','session_id'=>(string) Str::uuid()])->assertCreated();
        $this->postJson('/api/games/'.$other->id.'/start', [], $headers)->assertOk();
        $second = $other->rounds()->firstOrFail();
        $this->assertNotSame([$first->category_id, $first->letter], [$second->category_id, $second->letter]);
    }
    public function test_pair_pool_is_exhausted_before_a_pair_repeats(): void {
        $pool = DB::table('game_categories')->where('game_id', $this->game->id)->orderBy('position')->limit(2)->pluck('category_id')->all();
        DB::table('game_categories')->where('game_id', $this->game->id)->whereNotIn('category_id', $pool)->delete();
        $pairs = [];
        for ($i = 0; $i < 4; $i++) {
            $round = $this->startRound();
            $pairs[] = $round->category_id.':'.$round->letter;
            $round->update(['status'=>'finished']);
        }
        $this->assertCount(4, array_unique($pairs));
    }
    public function test_votes_before_stop_and_self_votes_are_forbidden(): void {
        $round=$this->startRound(); $this->submit($round,$this->host,$round->letter.'ina'); $a=$round->answers()->first();
        $this->postJson('/api/answers/'.$a->id.'/votes',['vote'=>'valid'],$this->guest)->assertStatus(409);
        $this->stopRound($round);
        $this->postJson('/api/answers/'.$a->id.'/votes',['vote'=>'valid'],$this->host)->assertForbidden();
        $this->postJson('/api/answers/'.$a->id.'/decision',['valid'=>true],$this->host)->assertStatus(409);
    }
    public function test_duplicates_are_normalized_and_scores_are_idempotent(): void {
        $round=$this->startRound(); $this->submit($round,$this->host,$round->letter.'  René'); $this->submit($round,$this->guest,strtolower($round->letter).' rene'); $this->stopRound($round);
        $answers=$round->answers()->orderBy('id')->get();
        $this->postJson('/api/answers/'.$answers[0]->id.'/votes',['vote'=>'valid'],$this->guest)->assertOk();
        $this->postJson('/api/answers/'.$answers[1]->id.'/votes',['vote'=>'valid'],$this->host)->assertOk();
        $this->postJson('/api/rounds/'.$round->id.'/finish-judging',[],$this->host)->assertOk();
        $this->assertSame([5,5],$this->game->players()->orderBy('id')->pluck('score')->all());
        $this->postJson('/api/rounds/'.$round->id.'/finish-judging',[],$this->host)->assertStatus(409);
        $this->assertSame([5,5],$this->game->players()->orderBy('id')->pluck('score')->all());
    }
    public function test_strict_mode_invalidates_flagged_answer(): void {
        $this->game->update(['anti_cheat_mode'=>'strict']); $round=$this->startRound(); $this->submit($round,$this->host,$round->letter.'ina');
        $this->postJson('/api/anti-cheat/events',['round_id'=>$round->id,'event'=>'away'],$this->host)->assertOk();
        $this->travel(2)->seconds(); $this->postJson('/api/anti-cheat/events',['round_id'=>$round->id,'event'=>'back'],$this->host)->assertOk();
        $this->stopRound($round);
        $this->assertSame('strict',$round->answers()->first()->invalid_reason);
        $this->assertGreaterThanOrEqual(2000,DB::table('anti_cheat_events')->value('duration_ms'));
    }
    public function test_ties_require_other_player_for_hosts_answer(): void {
        $round=$this->startRound(); $this->submit($round,$this->host,$round->letter.'ina'); $this->submit($round,$this->guest,$round->letter.'ob'); $this->stopRound($round);
        $a=$round->answers()->orderBy('id')->get();
        $this->postJson('/api/answers/'.$a[0]->id.'/votes',['vote'=>'uncertain'],$this->guest)->assertOk();
        $this->postJson('/api/answers/'.$a[1]->id.'/votes',['vote'=>'uncertain'],$this->host)->assertOk();
        $this->postJson('/api/rounds/'.$round->id.'/finish-judging',[],$this->host)->assertStatus(409);
        $this->postJson('/api/answers/'.$a[0]->id.'/decision',['valid'=>true],$this->host)->assertForbidden();
        $this->postJson('/api/answers/'.$a[0]->id.'/decision',['valid'=>true],$this->guest)->assertOk();
        $this->postJson('/api/answers/'.$a[1]->id.'/decision',['valid'=>false],$this->host)->assertOk();
        $this->postJson('/api/rounds/'.$round->id.'/finish-judging',[],$this->host)->assertOk();
        $this->assertSame([10,0],$this->game->players()->orderBy('id')->pluck('score')->all());
    }
    public function test_target_score_tie_continues_then_unique_leader_wins(): void {
        $this->game->update(['target_score'=>10]);
        $used=[];
        for($i=0;$i<2;$i++) {
            $round=$this->startRound(); $used[]=$round->letter;
            $this->submit($round,$this->host,$round->letter.'ina'); if($i===0) $this->submit($round,$this->guest,$round->letter.'ob');
            $this->stopRound($round); $a=$round->answers()->orderBy('id')->get();
            $this->postJson('/api/answers/'.$a[0]->id.'/votes',['vote'=>'valid'],$this->guest)->assertOk();
            if($i===0) $this->postJson('/api/answers/'.$a[1]->id.'/votes',['vote'=>'valid'],$this->host)->assertOk();
            $this->postJson('/api/rounds/'.$round->id.'/finish-judging',[],$this->host)->assertOk();
            $this->assertSame($i===0?'playing':'finished',$this->game->fresh()->status);
        }
        $this->assertNotSame($used[0],$used[1]); $this->assertSame($this->game->host_id,$this->game->fresh()->winner_id);
    }
    public function test_room_access_session_replacement_and_join_validation(): void {
        $this->getJson('/api/games/'.$this->code)->assertUnauthorized();
        $this->postJson('/api/games/XXXXXX/join',['nickname'=>'Else','locale'=>'en','session_id'=>(string)Str::uuid()])->assertNotFound();
        $this->postJson('/api/games/'.$this->code.'/join',['nickname'=>'lova','locale'=>'en','session_id'=>(string)Str::uuid()])->assertStatus(422);
        $new=(string)Str::uuid(); $this->postJson('/api/session/claim',['session_id'=>$new],$this->guest)->assertOk();
        $this->getJson('/api/games/'.$this->code,$this->guest)->assertStatus(409);
        $this->getJson('/api/games/'.$this->code,[...$this->guest,'X-Session-ID'=>$new])->assertOk();
    }
    public function test_host_transfers_on_disconnect(): void {
        GamePlayer::find($this->game->host_id)->update(['last_seen_at'=>now()->subSeconds(50)]);
        $r=$this->getJson('/api/games/'.$this->code,$this->guest)->assertOk()->json();
        $this->assertSame($r['me_id'],$r['host_id']);
    }
    public function test_absent_referee_cannot_block_or_award_hosts_own_answer(): void {
        $round=$this->startRound(); $this->submit($round,$this->host,$round->letter.'ina')->assertOk();
        $this->stopRound($round);
        $this->travel(31)->seconds();
        $this->getJson('/api/games/'.$this->code,$this->host)->assertOk()->assertJsonPath('round.answers.0.tie_decision',false);
        $this->postJson('/api/rounds/'.$round->id.'/finish-judging',[],$this->host)->assertOk();
        $this->assertSame(0,$this->game->players()->sum('score'));
    }
    public function test_room_scales_to_eight_players_and_uses_same_letter(): void {
        $headers=[$this->host,$this->guest];
        for($i=2;$i<8;$i++) { $s=(string)Str::uuid(); $r=$this->postJson('/api/games/'.$this->code.'/join',['nickname'=>'Joueur '.$i,'locale'=>'en','session_id'=>$s])->assertCreated()->json(); $headers[]=['Authorization'=>'Bearer '.$r['token'],'X-Session-ID'=>$s]; }
        $round=$this->startRound();
        foreach($headers as $h) $this->getJson('/api/games/'.$this->code,$h)->assertOk()->assertJsonCount(8,'players')->assertJsonPath('round.letter',$round->letter);
    }
    public function test_judging_comments_are_shared_only_during_judging(): void {
        $round=$this->startRound();
        $this->postJson('/api/rounds/'.$round->id.'/comments',['body'=>'Too early'],$this->host)->assertStatus(409);
        $this->getJson('/api/games/'.$this->code,$this->guest)->assertOk()->assertJsonMissingPath('round.comments');
        $this->stopRound($round);
        $this->postJson('/api/rounds/'.$round->id.'/comments',['body'=>'  Ekena ve?  '],$this->host)->assertCreated();
        $this->getJson('/api/games/'.$this->code,$this->guest)->assertOk()
            ->assertJsonPath('round.comments.0.body','Ekena ve?')
            ->assertJsonPath('round.comments.0.player_id',$this->game->host_id);
        $this->postJson('/api/rounds/'.$round->id.'/comments',['body'=>'   '],$this->guest)->assertStatus(422);
        $this->postJson('/api/rounds/'.$round->id.'/comments',['body'=>str_repeat('a',301)],$this->guest)->assertStatus(422);
        $round->update(['status'=>'finished']);
        $this->postJson('/api/rounds/'.$round->id.'/comments',['body'=>'Too late'],$this->guest)->assertStatus(409);
        $this->getJson('/api/games/'.$this->code,$this->guest)->assertOk()->assertJsonMissingPath('round.comments');
    }
    public function test_chat_works_before_client_id_migration_is_applied(): void {
        Schema::table('round_comments', function (Blueprint $table) {
            $table->dropUnique(['round_id', 'player_id', 'client_id']);
            $table->dropColumn('client_id');
        });
        $round = $this->startRound();
        $this->stopRound($round);
        $clientId = (string) Str::uuid();
        $this->postJson('/api/rounds/'.$round->id.'/comments', ['body'=>'Salama','client_id'=>$clientId], $this->host)->assertCreated();
        $this->getJson('/api/games/'.$this->code, $this->guest)->assertOk()
            ->assertJsonPath('round.comments.0.body', 'Salama')
            ->assertJsonPath('round.comments.0.client_id', null);
    }
    public function test_comment_retry_keeps_one_message_even_after_judging_ends(): void {
        $round = $this->startRound();
        $this->stopRound($round);
        $clientId = (string) Str::uuid();
        $url = '/api/rounds/'.$round->id.'/comments';
        $first = $this->postJson($url, ['body'=>'Salama','client_id'=>$clientId], $this->host)->assertCreated();
        $this->postJson($url, ['body'=>'Salama','client_id'=>$clientId], $this->host)->assertOk()->assertJsonPath('id', $first->json('id'));
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('round_comments')->where('round_id', $round->id)->count());
        $this->getJson('/api/games/'.$this->code, $this->guest)->assertJsonPath('round.comments.0.client_id', $clientId);
        $round->update(['status'=>'finished']);
        $this->postJson($url, ['body'=>'Salama','client_id'=>$clientId], $this->host)->assertOk();
        $this->postJson($url, ['body'=>'Another','client_id'=>(string)Str::uuid()], $this->host)->assertStatus(409);
    }
    public function test_comment_limit_is_per_player_on_shared_network(): void {
        $round = $this->startRound();
        $this->stopRound($round);
        $url = '/api/rounds/'.$round->id.'/comments';
        for ($i=0; $i<30; $i++) $this->postJson($url, ['body'=>'Message '.$i], $this->host)->assertCreated();
        $this->postJson($url, ['body'=>'Too many'], $this->host)->assertStatus(429);
        $this->postJson($url, ['body'=>'Another player'], $this->guest)->assertCreated();
    }
    public function test_room_polling_does_not_exhaust_the_comment_limit(): void {
        $round=$this->startRound();
        $this->stopRound($round);
        for ($i=0; $i<35; $i++) $this->getJson('/api/games/'.$this->code,$this->host)->assertOk();
        $this->postJson('/api/rounds/'.$round->id.'/comments',['body'=>'Still here'],$this->host)->assertCreated();
    }
    public function test_new_player_can_join_an_active_game_and_play_next_round(): void {
        $round=$this->startRound();
        $session=(string)Str::uuid();
        $joined=$this->postJson('/api/games/'.$this->code.'/join',['nickname'=>'Fara','locale'=>'mg','session_id'=>$session])->assertCreated()->json();
        $headers=['Authorization'=>'Bearer '.$joined['token'],'X-Session-ID'=>$session];
        $this->getJson('/api/games/'.$this->code,$headers)->assertOk()
            ->assertJsonCount(3,'players')->assertJsonPath('round.participating',false);
        $round->update(['status'=>'finished']);
        $this->postJson('/api/games/'.$this->game->id.'/start',[],$this->host)->assertOk();
        $this->getJson('/api/games/'.$this->code,$headers)->assertOk()->assertJsonPath('round.participating',true);
    }
    public function test_player_can_leave_and_rejoin_with_their_score(): void {
        $guest=GamePlayer::where('game_id',$this->game->id)->where('nickname','Mialy')->firstOrFail();
        $guest->update(['score'=>15]);
        $this->postJson('/api/games/'.$this->game->id.'/leave',[],$this->guest)->assertOk();
        $this->getJson('/api/games/'.$this->code,$this->guest)->assertForbidden();
        $this->getJson('/api/games/'.$this->code,$this->host)->assertOk()->assertJsonPath('players.1.left',true);
        $this->postJson('/api/session/claim',['session_id'=>$this->guest['X-Session-ID']],$this->guest)->assertOk();
        $this->getJson('/api/games/'.$this->code,$this->guest)->assertOk()
            ->assertJsonPath('players.1.left',false)->assertJsonPath('players.1.score',15);
    }
}
