<?php
namespace Tests\Feature;

use App\Models\{Game, GamePlayer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Hash};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('admin.username', 'AdminTest');
        config()->set('admin.password_hash', Hash::make('test-only-password'));
        Cache::flush();
    }

    public function test_admin_login_protects_dashboard_and_logout_revokes_session(): void {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
        $this->postJson('/api/admin/login',['username'=>'AdminTest','password'=>'incorrect'])->assertUnauthorized();

        $game=Game::create(['code'=>'ABC123','name'=>'Test room','status'=>'playing']);
        $game->players()->create(['nickname'=>'Lova','nickname_key'=>'lova','locale'=>'mg','session_id'=>(string)Str::uuid(),'last_seen_at'=>now()]);
        $response=$this->postJson('/api/admin/login',['username'=>'AdminTest','password'=>'test-only-password'])->assertOk();
        $cookie=collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName()===config('admin.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->withCredentials();
        $this->withUnencryptedCookie(config('admin.cookie'),$cookie->getValue())->getJson('/api/admin/dashboard')->assertOk()
            ->assertJsonPath('totals.games',1)
            ->assertJsonPath('totals.active_games',1)
            ->assertJsonPath('totals.online_players',1)
            ->assertJsonPath('games.0.code','ABC123');
        $this->withUnencryptedCookie(config('admin.cookie'),$cookie->getValue())->postJson('/api/admin/logout')->assertOk();
        $this->withUnencryptedCookie(config('admin.cookie'),$cookie->getValue())->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    public function test_admin_can_delete_finished_game_and_all_its_history_only_with_csrf(): void {
        $finished = Game::create(['code'=>'FIN123','name'=>'Finished','status'=>'finished']);
        $player = $finished->players()->create(['nickname'=>'Lova','nickname_key'=>'lova','locale'=>'mg','session_id'=>(string)Str::uuid()]);
        $player->createToken('game')->plainTextToken;
        $category = DB::table('categories')->value('id');
        $round = $finished->rounds()->create(['category_id'=>$category,'number'=>1,'letter'=>'A','status'=>'finished','started_at'=>now(),'answer_deadline'=>now()]);
        $answerId = DB::table('answers')->insertGetId(['round_id'=>$round->id,'player_id'=>$player->id,'answer'=>'Aina','normalized'=>'aina']);
        DB::table('votes')->insert(['answer_id'=>$answerId,'voter_player_id'=>$player->id,'vote'=>'valid']);
        DB::table('round_comments')->insert(['round_id'=>$round->id,'player_id'=>$player->id,'body'=>'Salama','created_at'=>now()]);
        DB::table('anti_cheat_events')->insert(['round_id'=>$round->id,'player_id'=>$player->id,'event_type'=>'blur','started_at'=>now()]);
        $active = Game::create(['code'=>'ACT123','name'=>'Active','status'=>'playing']);

        $this->deleteJson('/api/admin/games/'.$finished->id)->assertUnauthorized();
        $login = $this->postJson('/api/admin/login',['username'=>'AdminTest','password'=>'test-only-password'])->assertOk();
        $cookie = collect($login->headers->getCookies())->first(fn ($item) => $item->getName() === config('admin.cookie'));
        $csrf = $login->json('csrf');
        $this->withCredentials();
        $this->withUnencryptedCookie(config('admin.cookie'), $cookie->getValue())->deleteJson('/api/admin/games/'.$finished->id)->assertForbidden();
        $this->withHeader('X-Admin-CSRF', $csrf)->deleteJson('/api/admin/games/'.$active->id)->assertStatus(409);
        $this->getJson('/api/admin/dashboard?filter=finished')->assertJsonCount(1, 'games')->assertJsonPath('games.0.code','FIN123');
        $this->deleteJson('/api/admin/games/'.$finished->id)->assertOk()->assertJsonPath('deleted', 1);
        foreach (['games','game_players','rounds','answers','votes','round_comments','anti_cheat_events','personal_access_tokens'] as $table) {
            $this->assertSame($table === 'games' ? 1 : 0, DB::table($table)->count(), $table);
        }
        $this->assertSame('ACT123', Game::first()->code);
    }

    public function test_admin_can_purge_finished_games_in_batches(): void {
        foreach (range(1, 51) as $i) Game::create(['code'=>sprintf('F%05d',$i),'name'=>'Finished','status'=>'finished']);
        Game::create(['code'=>'ACT123','name'=>'Active','status'=>'playing']);
        $login = $this->postJson('/api/admin/login',['username'=>'AdminTest','password'=>'test-only-password'])->assertOk();
        $cookie = collect($login->headers->getCookies())->first(fn ($item) => $item->getName() === config('admin.cookie'));
        $this->withCredentials();
        $this->withUnencryptedCookie(config('admin.cookie'), $cookie->getValue())->withHeader('X-Admin-CSRF', $login->json('csrf'));
        $this->deleteJson('/api/admin/games/finished')->assertOk()->assertJsonPath('deleted',50)->assertJsonPath('remaining',1);
        $this->deleteJson('/api/admin/games/finished')->assertOk()->assertJsonPath('deleted',1)->assertJsonPath('remaining',0);
        $this->assertSame(1, Game::count());
    }

    public function test_lobby_and_playing_over_three_hours_are_deletable_and_players_are_private(): void {
        $category = DB::table('categories')->value('id');
        $lobby = Game::create(['code'=>'LOB123','name'=>'Lobby','status'=>'lobby']);
        $recent = Game::create(['code'=>'NEW123','name'=>'Recent','status'=>'playing']);
        $old = Game::create(['code'=>'OLD123','name'=>'Old','status'=>'playing']);
        foreach ([$recent, $old] as $game) {
            $started = $game->is($old) ? now()->subHours(3)->subMinute() : now()->subHours(2);
            $game->rounds()->create(['category_id'=>$category,'number'=>1,'letter'=>'A','status'=>'answering','started_at'=>$started,'answer_deadline'=>$started->copy()->addSeconds(15)]);
        }
        $lobby->players()->create(['nickname'=>'Miora','nickname_key'=>'miora','locale'=>'mg','session_id'=>(string)Str::uuid(),'score'=>12,'last_seen_at'=>now()]);
        $lobby->players()->create(['nickname'=>'Lova','nickname_key'=>'lova','locale'=>'mg','session_id'=>(string)Str::uuid(),'score'=>5,'left_at'=>now()]);
        $this->getJson('/api/admin/games/'.$lobby->id.'/players')->assertUnauthorized();
        $login = $this->postJson('/api/admin/login',['username'=>'AdminTest','password'=>'test-only-password'])->assertOk();
        $cookie = collect($login->headers->getCookies())->first(fn ($item) => $item->getName() === config('admin.cookie'));
        $this->withCredentials()->withUnencryptedCookie(config('admin.cookie'), $cookie->getValue())->withHeader('X-Admin-CSRF', $login->json('csrf'));
        $this->getJson('/api/admin/games/'.$lobby->id.'/players')->assertOk()
            ->assertJsonPath('players.0.nickname','Miora')->assertJsonPath('players.0.online',true)
            ->assertJsonPath('players.1.left',true)->assertDontSee('session_id');
        $games = collect($this->getJson('/api/admin/dashboard')->assertOk()->json('games'))->keyBy('code');
        $this->assertTrue($games['LOB123']['can_delete']);
        $this->assertFalse($games['NEW123']['can_delete']);
        $this->assertTrue($games['OLD123']['can_delete']);
        $this->getJson('/api/admin/dashboard?filter=lobby')->assertJsonCount(1, 'games')->assertJsonPath('games.0.code', 'LOB123');
        $this->getJson('/api/admin/dashboard?filter=stale')->assertJsonCount(1, 'games')->assertJsonPath('games.0.code', 'OLD123');
        $this->deleteJson('/api/admin/games/'.$recent->id)->assertStatus(409);
        $this->deleteJson('/api/admin/games/'.$lobby->id)->assertOk();
        $this->deleteJson('/api/admin/games/'.$old->id)->assertOk();
        $this->assertSame(['NEW123'], Game::pluck('code')->all());
        $this->assertSame(0, GamePlayer::count());
    }
    public function test_admin_history_is_paginated_by_filter(): void {
        foreach (range(1, 25) as $i) Game::create(['code'=>sprintf('P%05d',$i),'name'=>'Finished','status'=>'finished']);
        Game::create(['code'=>'ACT123','name'=>'Active','status'=>'playing']);
        $login = $this->postJson('/api/admin/login',['username'=>'AdminTest','password'=>'test-only-password'])->assertOk();
        $cookie = collect($login->headers->getCookies())->first(fn ($item) => $item->getName() === config('admin.cookie'));
        $this->withCredentials()->withUnencryptedCookie(config('admin.cookie'), $cookie->getValue());
        $this->getJson('/api/admin/dashboard?filter=finished&page=1')->assertOk()->assertJsonCount(20,'games')
            ->assertJsonPath('pagination.pages',2)->assertJsonPath('pagination.total',25);
        $this->getJson('/api/admin/dashboard?filter=finished&page=2')->assertOk()->assertJsonCount(5,'games')
            ->assertJsonPath('pagination.page',2);
        $this->getJson('/api/admin/dashboard?page=0')->assertUnprocessable();
    }

    public function test_finished_retention_is_configurable_and_does_not_remove_active_games(): void {
        $old = Game::create(['code'=>'OLD123','name'=>'Old finished','status'=>'finished']);
        $new = Game::create(['code'=>'NEW123','name'=>'New finished','status'=>'finished']);
        $active = Game::create(['code'=>'ACT123','name'=>'Old active','status'=>'playing']);
        foreach ([$old,$active] as $game) DB::table('games')->where('id',$game->id)->update(['updated_at'=>now()->subDays(31)]);
        DB::table('games')->where('id',$new->id)->update(['updated_at'=>now()->subDays(29)]);
        config()->set('admin.finished_retention_days',0);
        $this->assertSame(0, Artisan::call('game:prune-finished'));
        $this->assertSame(3, Game::count());
        config()->set('admin.finished_retention_days',30);
        $this->assertSame(0, Artisan::call('game:prune-finished'));
        $this->assertEqualsCanonicalizing(['NEW123','ACT123'], Game::pluck('code')->all());
    }

    public function test_admin_can_change_retention_with_csrf_and_scheduler_uses_saved_value(): void {
        $old = Game::create(['code'=>'OLD123','name'=>'Old finished','status'=>'finished']);
        DB::table('games')->where('id',$old->id)->update(['updated_at'=>now()->subDays(8)]);
        $this->putJson('/api/admin/retention',['days'=>7])->assertUnauthorized();
        $login = $this->postJson('/api/admin/login',['username'=>'AdminTest','password'=>'test-only-password'])->assertOk();
        $cookie = collect($login->headers->getCookies())->first(fn ($item) => $item->getName() === config('admin.cookie'));
        $this->withCredentials()->withUnencryptedCookie(config('admin.cookie'), $cookie->getValue());
        $this->putJson('/api/admin/retention',['days'=>7])->assertForbidden();
        $this->withHeader('X-Admin-CSRF', $login->json('csrf'));
        $this->putJson('/api/admin/retention',['days'=>-1])->assertUnprocessable();
        $this->putJson('/api/admin/retention',['days'=>7])->assertOk()->assertJsonPath('finished_retention_days',7);
        $this->getJson('/api/admin/dashboard')->assertJsonPath('finished_retention_days',7);
        $this->assertSame(0, Artisan::call('game:prune-finished'));
        $this->assertSame(0, Game::count());
    }
}
