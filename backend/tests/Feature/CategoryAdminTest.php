<?php
namespace Tests\Feature;

use App\Models\Game;
use App\Services\GameEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Cache, DB, Hash};
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryAdminTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('admin.username', 'CategoryAdmin');
        config()->set('admin.password_hash', Hash::make('test-only-password'));
        Cache::flush();
    }

    private function login(): void {
        $login = $this->postJson('/api/admin/login', ['username' => 'CategoryAdmin', 'password' => 'test-only-password'])->assertOk();
        $cookie = collect($login->headers->getCookies())->first(fn ($item) => $item->getName() === config('admin.cookie'));
        $this->withCredentials()->withUnencryptedCookie(config('admin.cookie'), $cookie->getValue())->withHeader('X-Admin-CSRF', $login->json('csrf'));
    }

    private function category(string $code = 'musical_instrument'): array {
        return ['code' => $code, 'difficulty' => 2, 'names' => ['mg' => 'Zavamaneno', 'fr' => 'Instrument de musique', 'en' => 'Musical instrument']];
    }

    private function upload(string $body, string $name = 'categories.json', bool $preview = true) {
        return $this->post('/api/admin/categories/import', [
            'file' => UploadedFile::fake()->createWithContent($name, $body), 'preview' => $preview ? '1' : '0',
        ], ['Accept' => 'application/json']);
    }

    public function test_category_admin_requires_session_and_csrf_for_all_writes(): void {
        $id = DB::table('categories')->value('id');
        $this->getJson('/api/admin/categories')->assertUnauthorized();
        $this->get('/api/admin/categories/template')->assertUnauthorized();
        $this->postJson('/api/admin/categories', $this->category())->assertUnauthorized();
        $this->putJson('/api/admin/categories/'.$id, $this->category())->assertUnauthorized();
        $this->upload(json_encode([$this->category()]))->assertUnauthorized();
        $this->login();
        $this->withHeader('X-Admin-CSRF', 'wrong');
        $this->postJson('/api/admin/categories', $this->category())->assertForbidden();
        $this->putJson('/api/admin/categories/'.$id, $this->category())->assertForbidden();
        $this->upload(json_encode([$this->category()]))->assertForbidden();
    }

    public function test_create_translation_and_difficulty_updates_are_used_in_game(): void {
        $open = Game::create(['code' => 'CAT123', 'name' => 'Open', 'status' => 'playing', 'target_score' => 100]);
        $finished = Game::create(['code' => 'FIN123', 'name' => 'Finished', 'status' => 'finished']);
        $host = $open->players()->create(['nickname' => 'Lova', 'nickname_key' => 'lova', 'locale' => 'fr', 'session_id' => (string) Str::uuid(), 'last_seen_at' => now(), 'score' => 80]);
        $open->update(['host_id' => $host->id]);
        $open->players()->create(['nickname' => 'Aina', 'nickname_key' => 'aina', 'locale' => 'mg', 'session_id' => (string) Str::uuid(), 'last_seen_at' => now()]);
        $old = DB::table('categories')->where('code', 'malagasy_monarch')->value('id');
        DB::table('game_categories')->insert(['game_id' => $open->id, 'category_id' => $old, 'position' => 0]);
        $this->login();
        $this->getJson('/api/admin/categories')->assertOk();
        $this->postJson('/api/admin/categories', $this->category())->assertCreated();
        $new = DB::table('categories')->where('code', 'musical_instrument')->value('id');
        $this->assertDatabaseHas('game_categories', ['game_id' => $open->id, 'category_id' => $new]);
        $this->assertDatabaseMissing('game_categories', ['game_id' => $finished->id, 'category_id' => $new]);
        $row = $this->category(); $row['difficulty'] = 3; $row['names']['fr'] = 'Instrument musical';
        $this->putJson('/api/admin/categories/'.$new, $row)->assertOk();
        $this->putJson('/api/admin/categories/'.$old, ['difficulty' => 1, 'names' => ['mg' => 'Mpanjaka', 'fr' => 'Monarque', 'en' => 'Monarch']])->assertOk();
        // The config still marks malagasy_monarch as level 3; the database must win.
        $engine = app(GameEngine::class);
        $engine->start($open, $host);
        $this->assertEquals($new, $open->rounds()->first()->category_id);
        $snapshot = $engine->snapshot($open, $host);
        $this->assertSame('Instrument musical', $snapshot['category_names']['musical_instrument']['fr']);
        $catalog = collect($this->getJson('/api/categories')->assertOk()->json('categories'))->keyBy('code');
        $this->assertSame(3, $catalog['musical_instrument']['difficulty']);
        $this->assertSame('Zavamaneno', $catalog['musical_instrument']['names']['mg']);
        $room = $this->postJson('/api/games', ['nickname' => 'Miora', 'locale' => 'fr', 'session_id' => (string) Str::uuid(), 'name' => 'New room', 'target_score' => 100, 'answer_duration' => 15, 'letters' => 'AB', 'no_repeat' => true, 'unique_points' => 10, 'duplicate_points' => 5])->assertCreated()->json();
        $newRoom = Game::where('code', $room['code'])->first();
        $this->assertDatabaseHas('game_categories', ['game_id' => $newRoom->id, 'category_id' => $new]);
        $this->postJson('/api/admin/categories', $this->category())->assertUnprocessable();
        $this->putJson('/api/admin/categories/999999', $row)->assertNotFound();
    }

    public function test_json_preview_does_not_write_and_import_is_atomic(): void {
        $this->login();
        $before = DB::table('categories')->count();
        $rows = [$this->category(), $this->category('scientist')];
        $this->upload(json_encode($rows))->assertOk()->assertJsonCount(2, 'categories');
        $this->assertSame($before, DB::table('categories')->count());
        $bad = $rows; $bad[1]['names']['mg'] = '';
        $this->upload(json_encode($bad), preview: false)->assertUnprocessable();
        $this->assertSame($before, DB::table('categories')->count());
        $this->upload(json_encode($rows), preview: false)->assertCreated()->assertJsonPath('created', 2);
        $this->assertSame($before + 2, DB::table('categories')->count());
        $this->upload(json_encode($rows), preview: false)->assertUnprocessable();
        $this->assertSame($before + 2, DB::table('categories')->count());
    }

    public function test_csv_templates_round_trip_and_semicolon_csv_is_supported(): void {
        $this->login();
        $template = $this->get('/api/admin/categories/template?format=csv')->assertOk()->getContent();
        $this->upload($template, 'template.csv')->assertOk()->assertJsonPath('categories.0.names.fr', 'Instrument de musique');
        $json = $this->get('/api/admin/categories/template?format=json')->assertOk()->getContent();
        $this->upload($json)->assertOk()->assertJsonCount(2, 'categories');
        $csv = "code;difficulty;mg;fr;en\nnew_theme;2;Lohahevitra;\"Thème, avec virgule\";Theme\n";
        $this->upload($csv, 'categories.csv', false)->assertCreated();
        $this->assertDatabaseHas('category_translations', ['locale' => 'fr', 'name' => 'Thème, avec virgule']);
    }

    public function test_invalid_imports_and_codes_are_rejected_without_writes(): void {
        $this->login();
        $before = DB::table('categories')->count();
        $this->upload('{broken')->assertUnprocessable();
        $this->upload('{}')->assertUnprocessable();
        $this->upload('[]')->assertUnprocessable();
        $this->upload('code,fr,en', 'bad.csv')->assertUnprocessable();
        $this->upload("code,difficulty,mg,fr,en\nx,2,nom\n", 'bad.csv')->assertUnprocessable();
        $this->upload(json_encode([$this->category(), $this->category()]))->assertUnprocessable();
        $this->upload(json_encode(array_map(fn ($i) => $this->category('theme_'.$i), range(1, 201))))->assertUnprocessable();
        $this->upload(json_encode([$this->category()]), 'bad.txt')->assertUnprocessable();
        foreach (['bad.code', '__proto__', 'UpperCase'] as $code) $this->postJson('/api/admin/categories', $this->category($code))->assertUnprocessable();
        $row = $this->category(); $row['difficulty'] = 4;
        $this->postJson('/api/admin/categories', $row)->assertUnprocessable();
        $this->assertSame($before, DB::table('categories')->count());
    }

    public function test_migration_preserves_initial_levels(): void {
        foreach (config('category_difficulty') as $level => $codes) {
            $this->assertSame(count($codes), DB::table('categories')->whereIn('code', $codes)->where('difficulty', $level)->count());
        }
        $this->assertDatabaseHas('categories', ['code' => 'fruit', 'difficulty' => 1]);
    }
}
