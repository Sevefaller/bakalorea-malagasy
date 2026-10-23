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

    public function test_strict_rounds_keep_the_selected_duration_and_accept_answers_without_presence_checks(): void {
        foreach ([10, 15, 30] as $duration) {
            $this->game->update(['anti_cheat_mode'=>'strict','answer_duration'=>$duration]);
            $round=$this->startRound();
            $this->assertEquals($duration * 1000, $round->started_at->diffInMilliseconds($round->answer_deadline));
            $this->travelTo($round->started_at->copy()->addSeconds(9));
            $this->submit($round,$this->host,$round->letter.'ina')->assertOk();
            $snapshot=$this->getJson('/api/games/'.$this->code,$this->host)->assertOk();
            $snapshot->assertJsonMissingPath('round.presence');
            $this->stopRound($round);
            $this->assertNull($round->answers()->where('player_id',$this->game->host_id)->first()->invalid_reason);
            $round->update(['status'=>'finished']);
        }
    }

    public function test_legacy_presence_settings_do_not_pause_rounds(): void {
        $this->game->update(['anti_cheat_mode'=>'strict','presence_checks'=>true,'presence_next_round'=>1]);
        $round=$this->startRound();
        $this->assertNull($round->presence_at);
        $this->assertEquals(15000,$round->started_at->diffInMilliseconds($round->answer_deadline));
        $this->travelTo($round->started_at->copy()->addSeconds(9));
        $this->submit($round,$this->host,$round->letter.'ina')->assertOk();
        $this->getJson('/api/games/'.$this->code,$this->host)->assertOk()
            ->assertJsonMissingPath('round.presence')
            ->assertJsonMissingPath('presence_missing');
    }
    public function test_new_categories_are_translated_and_attached_to_existing_rooms_without_duplicates(): void {
        $catalog=json_decode(file_get_contents(base_path('../frontend/src/additionalCategories.json')),true,512,JSON_THROW_ON_ERROR);
        // This test reruns the original cultural expansion only.
        $catalog=array_slice($catalog,0,94);
        $this->assertCount(94,$catalog);
        $codes=array_column($catalog,'code');
        $ids=DB::table('categories')->whereIn('code',$codes)->pluck('id');
        $this->assertCount(94,$ids);
        foreach (['mg','fr','en'] as $locale) {
            $this->assertSame(94,DB::table('category_translations')->whereIn('category_id',$ids)->where('locale',$locale)->count());
        }
        DB::table('game_categories')->where('game_id',$this->game->id)->whereIn('category_id',$ids)->delete();
        $migration=require database_path('migrations/2026_10_12_000003_add_cultural_and_fun_categories.php');
        $migration->up();
        $migration->up();
        $this->assertSame(94,DB::table('game_categories')->where('game_id',$this->game->id)->whereIn('category_id',$ids)->count());
        $this->assertSame(1,DB::table('categories')->where('code','gift_idea')->count());
    }

    public function test_chat_photos_are_private_and_upload_retries_do_not_duplicate_messages(): void {
        $round=$this->startRound();
        $this->stopRound($round);
        $bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1sAAAAASUVORK5CYII=');
        $file=\Illuminate\Http\UploadedFile::fake()->createWithContent('photo.png',$bytes);
        $clientId=(string)Str::uuid();
        $payload=['body'=>'','client_id'=>$clientId,'image'=>$file];
        $url='/api/rounds/'.$round->id.'/comments';
        $id=$this->post($url,$payload,[...$this->host,'Accept'=>'application/json'])->assertCreated()->json('id');
        $this->post($url,$payload,[...$this->host,'Accept'=>'application/json'])->assertOk()->assertJsonPath('id',$id);
        $this->assertDatabaseCount('round_comments',1);
        $snapshot=$this->getJson('/api/games/'.$this->code,$this->guest)->assertOk();
        $this->assertTrue((bool)$snapshot->json('round.comments.0.has_image'));
        $this->assertArrayNotHasKey('image_data',$snapshot->json('round.comments.0'));
        $photo=$this->get('/api/comments/'.$id.'/image',$this->guest)->assertOk();
        $this->assertSame($bytes,$photo->getContent());
        $photo->assertHeader('Content-Type','image/png');
        $this->get('/api/comments/'.$id.'/image',['Accept'=>'application/json'])->assertUnauthorized();
        $session=(string)Str::uuid();
        $other=$this->postJson('/api/games',['nickname'=>'Other','locale'=>'fr','session_id'=>$session,'name'=>'Other room','target_score'=>20,'answer_duration'=>15,'anti_cheat_mode'=>'normal','letters'=>'AB','no_repeat'=>true,'unique_points'=>10,'duplicate_points'=>5])->assertCreated()->json();
        $this->get('/api/comments/'.$id.'/image',['Authorization'=>'Bearer '.$other['token'],'X-Session-ID'=>$session,'Accept'=>'application/json'])->assertForbidden();
        $bad=\Illuminate\Http\UploadedFile::fake()->createWithContent('bad.svg','<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $this->post($url,['image'=>$bad],[...$this->host,'Accept'=>'application/json'])->assertUnprocessable();
        $large=\Illuminate\Http\UploadedFile::fake()->createWithContent('large.png',$bytes.str_repeat('x',1024*1024));
        $this->post($url,['image'=>$large],[...$this->host,'Accept'=>'application/json'])->assertUnprocessable();
        $round->update(['status'=>'finished']);
        $this->post($url,['image'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('photo.png',$bytes)],[...$this->host,'Accept'=>'application/json'])->assertStatus(409);
    }

    public function test_reactions_are_shared_replaceable_and_removable_without_affecting_votes(): void {
        $round=$this->startRound();
        $this->submit($round,$this->host,$round->letter.'ina')->assertOk();
        $this->submit($round,$this->guest,$round->letter.'ilo')->assertOk();
        $answer=$round->answers()->first();
        $url='/api/answers/'.$answer->id.'/reactions';
        $this->postJson($url,['reaction'=>'haha'],$this->guest)->assertStatus(409);
        $this->stopRound($round);
        $this->postJson($url,['reaction'=>'haha'],$this->guest)->assertOk();
        $this->postJson($url,['reaction'=>'haha'],$this->guest)->assertOk();
        $guestId=$this->game->players()->where('nickname','Mialy')->value('id');
        $this->getJson('/api/games/'.$this->code,$this->host)->assertOk()->assertJsonPath('round.answers.0.reaction_players.haha',[$guestId]);
        $this->getJson('/api/games/'.$this->code,$this->host)->assertJsonPath('round.answers.0.reactions.haha',1)->assertJsonPath('round.answers.0.my_reaction',null);
        $this->getJson('/api/games/'.$this->code,$this->guest)->assertJsonPath('round.answers.0.my_reaction','haha');
        $this->postJson($url,['reaction'=>'love'],$this->guest)->assertOk();
        $changed=$this->getJson('/api/games/'.$this->code,$this->host)->assertOk()->assertJsonPath('round.answers.0.reaction_players.love',[$guestId]);
        $this->assertArrayNotHasKey('haha',$changed->json('round.answers.0.reaction_players'));
        $this->assertDatabaseCount('answer_reactions',1);
        $this->assertDatabaseHas('answer_reactions',['answer_id'=>$answer->id,'reaction'=>'love']);
        $this->assertDatabaseCount('votes',0);
        $this->assertFalse(app(GameEngine::class)->ready($round));
        $this->postJson($url,['reaction'=>'unknown'],$this->guest)->assertUnprocessable();
        $this->postJson($url,['reaction'=>null],$this->guest)->assertOk();
        $this->getJson('/api/games/'.$this->code,$this->host)->assertOk()->assertJsonPath('round.answers.0.reaction_players',[]);
        $this->assertDatabaseCount('answer_reactions',0);
        $round->update(['status'=>'finished']);
        $this->postJson($url,['reaction'=>'like'],$this->guest)->assertStatus(409);
    }

    public function test_reactions_from_another_game_are_forbidden(): void {
        $round=$this->startRound();
        $this->stopRound($round);
        $session=(string)Str::uuid();
        $other=$this->postJson('/api/games',['nickname'=>'Other','locale'=>'fr','session_id'=>$session,'name'=>'Other game','target_score'=>20,'answer_duration'=>15,'anti_cheat_mode'=>'normal','letters'=>'AB','no_repeat'=>true,'unique_points'=>10,'duplicate_points'=>5])->assertCreated()->json();
        $this->postJson('/api/answers/'.$round->answers()->first()->id.'/reactions',['reaction'=>'haha'],['Authorization'=>'Bearer '.$other['token'],'X-Session-ID'=>$session])->assertForbidden();
        $this->assertDatabaseCount('answer_reactions',0);
    }

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
    public function test_categories_become_harder_as_the_leader_approaches_the_target(): void {
        $pool=DB::table('categories')->whereIn('code',['fruit','spice','malagasy_monarch'])->pluck('id','code');
        DB::table('game_categories')->where('game_id',$this->game->id)->whereNotIn('category_id',$pool->values())->delete();
        $this->game->update(['target_score'=>100]);
        $this->game->players()->whereKey($this->game->host_id)->update(['score'=>80]);
        $hard=$this->startRound();
        $this->assertEquals($pool['malagasy_monarch'],$hard->category_id);
        $this->stopRound($hard); $hard->update(['status'=>'finished']);
        // When the hardest category is exhausted, use the hardest remaining one.
        $medium=$this->startRound();
        $this->assertEquals($pool['spice'],$medium->category_id);
        $this->stopRound($medium); $medium->update(['status'=>'finished']);
        $easy=$this->startRound();
        $this->assertEquals($pool['fruit'],$easy->category_id);
    }

    public function test_halfway_progress_excludes_easy_categories(): void {
        $pool=DB::table('categories')->whereIn('code',['fruit','spice'])->pluck('id','code');
        DB::table('game_categories')->where('game_id',$this->game->id)->whereNotIn('category_id',$pool->values())->delete();
        $this->game->update(['target_score'=>100]);
        $this->game->players()->whereKey($this->game->host_id)->update(['score'=>50]);
        $this->assertEquals($pool['spice'],$this->startRound()->category_id);
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
    private function reservePairInOtherRoom(int $categoryId, string $letter): Round {
        $other=Game::create(['code'=>Str::upper(Str::random(6)),'name'=>'Reserved room','status'=>'playing']);
        return $other->rounds()->create(['number'=>1,'category_id'=>$categoryId,'letter'=>$letter,'status'=>'answering','started_at'=>now(),'answer_deadline'=>now()->addSeconds(15)]);
    }

    public function test_active_pair_in_another_room_is_excluded_from_the_draw(): void {
        $category=DB::table('categories')->where('code','furniture')->value('id');
        DB::table('game_categories')->where('game_id',$this->game->id)->where('category_id','<>',$category)->delete();
        $this->game->update(['letters'=>'AI']);
        $this->reservePairInOtherRoom($category,'I');
        $round=$this->startRound();
        $this->assertEquals($category,$round->category_id);
        $this->assertSame('A',$round->letter);
    }

    public function test_draw_uses_an_available_category_when_preferred_difficulty_is_busy(): void {
        $pool=DB::table('categories')->whereIn('code',['fruit','malagasy_monarch'])->pluck('id','code');
        DB::table('game_categories')->where('game_id',$this->game->id)->whereNotIn('category_id',$pool->values())->delete();
        $this->game->update(['target_score'=>100]);
        $this->game->players()->whereKey($this->game->host_id)->update(['score'=>80]);
        $this->reservePairInOtherRoom($pool['malagasy_monarch'],'A');
        $this->reservePairInOtherRoom($pool['malagasy_monarch'],'B');
        $this->assertEquals($pool['fruit'],$this->startRound()->category_id);
    }

    public function test_busy_pair_remains_reserved_past_recent_history_until_judging_finishes(): void {
        $category=DB::table('categories')->where('code','furniture')->value('id');
        DB::table('game_categories')->where('game_id',$this->game->id)->where('category_id','<>',$category)->delete();
        $this->game->update(['letters'=>'AI','used_letters'=>['A']]);
        $reserved=$this->reservePairInOtherRoom($category,'I');
        // Push the active pair out of the last 100 rounds used for recency ranking.
        for ($i=2; $i<=102; $i++) {
            $reserved->game->rounds()->create(['number'=>$i,'category_id'=>$category,'letter'=>'A','status'=>'finished','started_at'=>now(),'answer_deadline'=>now()]);
        }
        foreach (['answering','judging'] as $status) {
            $reserved->update(['status'=>$status]);
            $this->postJson('/api/games/'.$this->game->id.'/start',[],$this->host)
                ->assertStatus(409)->assertJsonPath('message','draw_pairs_busy');
            $this->assertSame(0,$this->game->rounds()->count());
            $this->assertSame(['A'],$this->game->fresh()->used_letters);
            $this->assertNull($this->game->fresh()->used_categories);
        }
        $reserved->update(['status'=>'finished']);
        $round=$this->startRound();
        $this->assertSame('I',$round->letter);
        $this->assertEquals($category,$round->category_id);
    }

    public function test_finished_room_does_not_reserve_pairs(): void {
        $category=DB::table('categories')->where('code','furniture')->value('id');
        DB::table('game_categories')->where('game_id',$this->game->id)->where('category_id','<>',$category)->delete();
        $this->game->update(['letters'=>'AI','used_letters'=>['A']]);
        $reserved=$this->reservePairInOtherRoom($category,'I');
        $reserved->game->update(['status'=>'finished']);
        $this->assertSame('I',$this->startRound()->letter);
    }

    public function test_letters_and_categories_change_even_when_their_cycles_restart(): void {
        $pool = DB::table('game_categories')->where('game_id', $this->game->id)->orderBy('position')->limit(2)->pluck('category_id')->all();
        DB::table('game_categories')->where('game_id', $this->game->id)->whereNotIn('category_id', $pool)->delete();
        $previous = null;
        for ($i = 0; $i < 8; $i++) {
            $round = $this->startRound();
            if ($previous) {
                $this->assertNotSame($previous->letter, $round->letter);
                $this->assertNotSame($previous->category_id, $round->category_id);
            }
            $previous = $round;
            $round->update(['status'=>'finished']);
        }
    }
    public function test_letter_cycles_are_complete_even_for_legacy_repeat_settings(): void {
        $this->game->update(['no_repeat'=>false,'letters'=>'ABCD']);
        $previous = null;
        $selected = [];
        for ($i = 0; $i < 12; $i++) {
            $round = $this->startRound();
            if ($previous) $this->assertNotSame($previous->letter, $round->letter);
            $selected[] = $round->letter;
            $previous = $round;
            $round->update(['status'=>'finished']);
        }
        foreach (array_chunk($selected,4) as $cycle) $this->assertEqualsCanonicalizing(str_split('ABCD'),$cycle);
        $this->assertTrue($this->game->fresh()->no_repeat);
    }

    public function test_letter_and_category_cycles_run_independently_across_multiple_cycles(): void {
        $pool=DB::table('categories')->whereIn('code',['fruit','male_name','female_name'])->pluck('id')->all();
        DB::table('game_categories')->where('game_id',$this->game->id)->whereNotIn('category_id',$pool)->delete();
        $this->game->update(['letters'=>'ABCDE']);
        $letters=[]; $categories=[]; $previous=null;
        for ($i=0; $i<15; $i++) {
            $this->game->players()->update(['last_seen_at'=>now()]);
            $round=$this->startRound();
            $letters[]=$round->letter; $categories[]=$round->category_id;
            if ($previous) {
                $this->assertNotSame($previous->letter,$round->letter);
                $this->assertNotSame($previous->category_id,$round->category_id);
            }
            $previous=$round;
            $round->update(['status'=>'finished']);
        }
        foreach (array_chunk($letters,5) as $cycle) $this->assertEqualsCanonicalizing(str_split('ABCDE'),$cycle);
        foreach (array_chunk($categories,3) as $cycle) $this->assertEqualsCanonicalizing($pool,$cycle);
    }

    public function test_categories_added_mid_cycle_are_drawn_before_any_repeat(): void {
        $pool=DB::table('categories')->whereIn('code',['fruit','male_name','female_name'])->pluck('id')->all();
        DB::table('game_categories')->where('game_id',$this->game->id)->whereNotIn('category_id',$pool)->delete();
        for ($i=0; $i<3; $i++) $this->startRound()->update(['status'=>'finished']);
        $first=$this->startRound();
        $first->update(['status'=>'finished']);
        $new=DB::table('categories')->where('code','plant')->value('id');
        DB::table('game_categories')->insert(['game_id'=>$this->game->id,'category_id'=>$new,'position'=>999]);
        $cycle=[$first->category_id];
        for ($i=0; $i<3; $i++) {
            $round=$this->startRound();
            $cycle[]=$round->category_id;
            $round->update(['status'=>'finished']);
        }
        $this->assertEqualsCanonicalizing([...$pool,$new],$cycle);
        $this->assertCount(4,$this->game->fresh()->used_categories);
        $this->startRound();
        $this->assertCount(1,$this->game->fresh()->used_categories);
    }

    public function test_difficulty_changes_do_not_skip_remaining_categories_in_a_cycle(): void {
        $pool=DB::table('categories')->whereIn('code',['fruit','spice','malagasy_monarch'])->pluck('id','code');
        DB::table('game_categories')->where('game_id',$this->game->id)->whereNotIn('category_id',$pool->values())->delete();
        $this->game->update(['target_score'=>100,'letters'=>'ABCD']);
        for ($cycle=0; $cycle<2; $cycle++) {
            $selected=[];
            foreach ([80,50,80] as $score) {
                $this->game->players()->whereKey($this->game->host_id)->update(['score'=>$score]);
                $round=$this->startRound();
                $selected[]=$round->category_id;
                $round->update(['status'=>'finished']);
            }
            $this->assertSame([$pool['malagasy_monarch'],$pool['spice'],$pool['fruit']],$selected);
        }
    }

    public function test_cycle_migration_recovers_existing_history_and_newly_added_categories(): void {
        $pool=DB::table('categories')->whereIn('code',['fruit','male_name','female_name'])->pluck('id')->all();
        DB::table('game_categories')->where('game_id',$this->game->id)->whereNotIn('category_id',$pool)->delete();
        $this->game->update(['letters'=>'ABC','no_repeat'=>false]);
        foreach ([0,1,2,0,1] as $index=>$categoryIndex) {
            $this->game->rounds()->create(['number'=>$index+1,'category_id'=>$pool[$categoryIndex],'letter'=>['A','A','B','C','A'][$index],'status'=>'finished','started_at'=>now(),'answer_deadline'=>now()]);
        }
        $new=DB::table('categories')->where('code','plant')->value('id');
        DB::table('game_categories')->insert(['game_id'=>$this->game->id,'category_id'=>$new,'position'=>999]);
        $migration=require database_path('migrations/2026_10_23_000004_track_random_draw_cycles.php');
        $migration->down();
        $migration->up();
        $restored=$this->game->fresh();
        $this->assertEqualsCanonicalizing($pool,$restored->used_categories);
        $this->assertSame(['A'],$restored->used_letters);
        $this->assertTrue($restored->no_repeat);
        $round=$this->startRound();
        $this->assertEquals($new,$round->category_id);
        $this->assertContains($round->letter,['B','C']);
    }
    public function test_a_single_category_remains_playable_across_rounds(): void {
        $category = DB::table('game_categories')->where('game_id', $this->game->id)->value('category_id');
        DB::table('game_categories')->where('game_id', $this->game->id)->where('category_id', '!=', $category)->delete();
        for ($i = 0; $i < 3; $i++) {
            $round = $this->startRound();
            $this->assertEquals($category, $round->category_id);
            $round->update(['status'=>'finished']);
        }
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
    public function test_game_creation_defaults_to_strict_mode(): void {
        $response=$this->postJson('/api/games',['nickname'=>'Strict host','locale'=>'fr','session_id'=>(string)Str::uuid(),'name'=>'Strict room','target_score'=>20,'answer_duration'=>15,'letters'=>'AB','no_repeat'=>true,'unique_points'=>10,'duplicate_points'=>5])->assertCreated();
        $this->assertSame('strict',Game::where('code',$response->json('code'))->firstOrFail()->anti_cheat_mode);
        $this->assertFalse((bool)Game::where('code',$response->json('code'))->firstOrFail()->presence_checks);
    }

    public function test_presence_checks_remain_disabled_for_all_creation_settings(): void {
        foreach ([['strict',15,false,false],['strict',15,null,false],['strict',15,true,false],['strict',10,null,false],['strict',30,null,false],['normal',15,true,false],['soft',15,null,false]] as [$mode,$duration,$setting,$expected]) {
            $payload=['nickname'=>'Host','locale'=>'fr','session_id'=>(string)Str::uuid(),'name'=>'Room','target_score'=>20,'answer_duration'=>$duration,'anti_cheat_mode'=>$mode,'letters'=>'AB','no_repeat'=>true,'unique_points'=>10,'duplicate_points'=>5];
            if ($setting !== null) $payload['presence_checks']=$setting;
            $response=$this->postJson('/api/games',$payload)->assertCreated();
            $this->assertSame($expected,(bool)Game::where('code',$response->json('code'))->firstOrFail()->presence_checks);
        }
    }

    public function test_away_reports_are_deduplicated_until_the_player_returns(): void {
        $round=$this->startRound();
        foreach (['away','away','back','away'] as $event) {
            $this->postJson('/api/anti-cheat/events',['round_id'=>$round->id,'event'=>$event],$this->host)->assertOk();
        }
        $snapshot=$this->getJson('/api/games/'.$this->code,$this->guest)->assertOk();
        $this->assertSame(2,(int)$snapshot->json('anti_cheat_alerts.0.count'));
        $snapshot->assertJsonPath('anti_cheat_alerts.0.event_type','away');
    }

    public function test_paste_is_shared_without_revealing_answers_and_invalidates_in_strict_mode(): void {
        $this->game->update(['anti_cheat_mode'=>'strict']);
        $round=$this->startRound();
        $this->submit($round,$this->host,$round->letter.'ina')->assertOk();
        $this->postJson('/api/anti-cheat/events',['round_id'=>$round->id,'event'=>'paste'],$this->host)->assertOk();
        $snapshot=$this->getJson('/api/games/'.$this->code,$this->guest)->assertOk();
        $snapshot->assertJsonPath('anti_cheat_alerts.0.player_id',$this->game->host_id);
        $snapshot->assertJsonPath('anti_cheat_alerts.0.event_type','paste');
        $this->assertArrayNotHasKey('answers',$snapshot->json('round'));
        $this->stopRound($round);
        $this->assertSame('strict',$round->answers()->where('player_id',$this->game->host_id)->first()->invalid_reason);
        $this->postJson('/api/anti-cheat/events',['round_id'=>$round->id,'event'=>'paste'],$this->host)->assertStatus(409);
    }

    public function test_soft_mode_hides_alerts_and_does_not_invalidate_pasted_answer(): void {
        $this->game->update(['anti_cheat_mode'=>'soft']);
        $round=$this->startRound();
        $this->submit($round,$this->host,$round->letter.'ina')->assertOk();
        $this->postJson('/api/anti-cheat/events',['round_id'=>$round->id,'event'=>'paste'],$this->host)->assertOk();
        $this->getJson('/api/games/'.$this->code,$this->guest)->assertOk()->assertJsonPath('anti_cheat_alerts',[]);
        $this->stopRound($round);
        $this->assertNull($round->answers()->where('player_id',$this->game->host_id)->first()->invalid_reason);
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
