<?php
namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryExamplesTest extends TestCase {
    use RefreshDatabase;

    public function test_all_active_categories_have_an_example_in_each_language(): void {
        $catalog = $this->getJson('/api/categories')->assertOk()->json('categories');
        $this->assertCount(447,$catalog);
        foreach ($catalog as $category) {
            foreach (['mg','fr','en'] as $locale) {
                $this->assertNotEmpty(trim($category['examples'][$locale] ?? ''), $category['code'].':'.$locale);
                $this->assertLessThanOrEqual(255,mb_strlen($category['examples'][$locale]));
            }
        }
        $byCode = collect($catalog)->keyBy('code');
        $this->assertSame('Valise',$byCode['handled_object']['examples']['fr']);
        $this->assertSame('Banana',$byCode['fruit']['examples']['en']);
        $this->assertSame('Akondro',$byCode['fruit']['examples']['mg']);
        // Categories tied to a language retain an example in that language.
        $this->assertSame('Table',$byCode['five_letter_french_word']['examples']['mg']);
        $this->assertSame('Trano',$byCode['five_letter_malagasy_word']['examples']['en']);
        $this->assertSame('Apple',$byCode['five_letter_english_word']['examples']['fr']);
    }

    public function test_migration_extends_open_rooms_once_without_overwriting_admin_edits(): void {
        $definitions = json_decode(file_get_contents(resource_path('data/everyday_categories_2026_11_24.json')),true);
        $this->assertCount(30,$definitions);
        $id = DB::table('categories')->where('code','handled_object')->value('id');
        DB::table('categories')->where('id',$id)->update(['difficulty'=>3]);
        DB::table('category_translations')->where('category_id',$id)->where('locale','fr')->update(['name'=>'Objet personnalisé','example'=>'Exemple personnalisé']);
        $open = Game::create(['code'=>'EXAM01','name'=>'Open','status'=>'playing','used_categories'=>[$id],'used_letters'=>['A']]);
        $finished = Game::create(['code'=>'EXAM02','name'=>'Finished','status'=>'finished']);
        DB::table('game_categories')->insert(['game_id'=>$open->id,'category_id'=>$id,'position'=>8]);
        $migration = require database_path('migrations/2026_11_24_000001_add_categories_and_examples.php');
        $before = DB::table('categories')->count();
        $migration->up(); $migration->up();
        $this->assertSame($before,DB::table('categories')->count());
        $this->assertSame(30,DB::table('game_categories')->where('game_id',$open->id)->count());
        $this->assertSame(30,DB::table('game_categories')->where('game_id',$open->id)->distinct()->count('position'));
        $this->assertSame(0,DB::table('game_categories')->where('game_id',$finished->id)->count());
        $this->assertSame([$id],$open->fresh()->used_categories);
        $this->assertSame(['A'],$open->fresh()->used_letters);
        $this->assertDatabaseHas('category_translations',['category_id'=>$id,'locale'=>'fr','name'=>'Objet personnalisé','example'=>'Exemple personnalisé']);
        $this->assertDatabaseHas('categories',['id'=>$id,'difficulty'=>3]);
    }
}
