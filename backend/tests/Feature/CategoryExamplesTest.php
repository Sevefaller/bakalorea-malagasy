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
        $this->assertCount(482,$catalog);
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

    public function test_latest_selection_adds_exactly_nine_topics_and_preserves_existing_rooms(): void {
        $rows = json_decode(file_get_contents(resource_path('data/selected_categories_2026_11_24.json')),true);
        $this->assertSame(['indian_ocean_island','mediterranean_country','aquatic_bird','venomous_animal','root_vegetable','edible_nut','construction_trade','legal_profession','rail_transport'],array_column($rows,0));
        $catalog = collect($this->getJson('/api/categories')->assertOk()->json('categories'))->keyBy('code');
        foreach ($rows as [$code,$difficulty,$fr,$mg,$en,$exFr,$exMg,$exEn]) {
            $this->assertSame(['fr'=>$fr,'mg'=>$mg,'en'=>$en],$catalog[$code]['names']);
            $this->assertSame(['fr'=>$exFr,'mg'=>$exMg,'en'=>$exEn],$catalog[$code]['examples']);
            $this->assertSame($difficulty,$catalog[$code]['difficulty']);
        }
        $id = $catalog['rail_transport']['id'];
        $game = Game::create(['code'=>'SEL001','name'=>'Open','status'=>'playing','used_categories'=>[$id],'used_letters'=>['T']]);
        $finished = Game::create(['code'=>'SEL002','name'=>'Finished','status'=>'finished']);
        DB::table('game_categories')->insert(['game_id'=>$game->id,'category_id'=>$id,'position'=>3]);
        DB::table('category_translations')->where('category_id',$id)->where('locale','fr')->update(['name'=>'Transport personnalisé','example'=>'Train']);
        $count = DB::table('categories')->count();
        $migration = require database_path('migrations/2026_11_24_000004_add_selected_knowledge_categories.php');
        $migration->up(); $migration->up();
        $this->assertSame($count,DB::table('categories')->count());
        $this->assertSame(9,DB::table('game_categories')->where('game_id',$game->id)->count());
        $this->assertSame(9,DB::table('game_categories')->where('game_id',$game->id)->distinct()->count('position'));
        $this->assertSame(0,DB::table('game_categories')->where('game_id',$finished->id)->count());
        $this->assertSame([$id],$game->fresh()->used_categories);
        $this->assertSame(['T'],$game->fresh()->used_letters);
        $this->assertDatabaseHas('category_translations',['category_id'=>$id,'locale'=>'fr','name'=>'Transport personnalisé','example'=>'Train']);
    }

    public function test_extra_knowledge_selection_has_twelve_translated_topics_without_excluded_categories(): void {
        $rows = json_decode(file_get_contents(resource_path('data/knowledge_categories_extra_2026_11_24.json')),true);
        $this->assertCount(12,$rows);
        $catalog = collect($this->getJson('/api/categories')->assertOk()->json('categories'))->keyBy('code');
        foreach ($rows as [$code,$difficulty,$fr,$mg,$en,$exFr,$exMg,$exEn]) {
            $this->assertSame(['fr'=>$fr,'mg'=>$mg,'en'=>$en],$catalog[$code]['names']);
            $this->assertSame(['fr'=>$exFr,'mg'=>$exMg,'en'=>$exEn],$catalog[$code]['examples']);
            $this->assertSame($difficulty,$catalog[$code]['difficulty']);
        }
        foreach (['Capitale africaine','Pays où le portugais est une langue officielle','Agrume'] as $name) {
            $this->assertFalse($catalog->contains(fn ($row) => $row['names']['fr'] === $name));
        }
        $id = $catalog['european_country']['id'];
        $game = Game::create(['code'=>'EXTRA1','name'=>'Open','status'=>'playing','used_categories'=>[$id],'used_letters'=>['B']]);
        $finished = Game::create(['code'=>'EXTRA2','name'=>'Finished','status'=>'finished']);
        DB::table('game_categories')->insert(['game_id'=>$game->id,'category_id'=>$id,'position'=>4]);
        DB::table('category_translations')->where('category_id',$id)->where('locale','fr')->update(['name'=>'Personnalisé','example'=>'Italie']);
        $before = DB::table('categories')->count();
        $migration = require database_path('migrations/2026_11_24_000003_add_more_knowledge_categories.php');
        $migration->up(); $migration->up();
        $this->assertSame($before,DB::table('categories')->count());
        $this->assertSame(12,DB::table('game_categories')->where('game_id',$game->id)->count());
        $this->assertSame(12,DB::table('game_categories')->where('game_id',$game->id)->distinct()->count('position'));
        $this->assertSame(0,DB::table('game_categories')->where('game_id',$finished->id)->count());
        $this->assertSame([$id],$game->fresh()->used_categories);
        $this->assertSame(['B'],$game->fresh()->used_letters);
        $this->assertDatabaseHas('category_translations',['category_id'=>$id,'locale'=>'fr','name'=>'Personnalisé','example'=>'Italie']);
    }

    public function test_selected_knowledge_categories_extend_rooms_once_and_exclude_unselected_topics(): void {
        $rows = json_decode(file_get_contents(resource_path('data/knowledge_categories_2026_11_24.json')),true);
        $this->assertCount(14,$rows);
        $catalog = collect($this->getJson('/api/categories')->assertOk()->json('categories'))->keyBy('code');
        foreach ($rows as [$code,$difficulty,$fr,$mg,$en,$exFr,$exMg,$exEn]) {
            $this->assertSame(['fr'=>$fr,'mg'=>$mg,'en'=>$en],$catalog[$code]['names']);
            $this->assertSame(['fr'=>$exFr,'mg'=>$exMg,'en'=>$exEn],$catalog[$code]['examples']);
            $this->assertSame($difficulty,$catalog[$code]['difficulty']);
        }
        foreach (['Auteur de bande dessinée','Peintre célèbre','Compositeur de musique classique','Instrument à percussion'] as $name) {
            $this->assertFalse($catalog->contains(fn ($row) => $row['names']['fr'] === $name));
        }
        foreach (['plant_eating_animal','fruit_tree'] as $code) $this->assertSame(1,DB::table('categories')->where('code',$code)->count());
        $id = $catalog['african_country']['id'];
        $open = Game::create(['code'=>'KNOW01','name'=>'Open','status'=>'lobby','used_categories'=>[$id],'used_letters'=>['A']]);
        $finished = Game::create(['code'=>'KNOW02','name'=>'Finished','status'=>'finished']);
        DB::table('game_categories')->insert(['game_id'=>$open->id,'category_id'=>$id,'position'=>5]);
        DB::table('category_translations')->where('category_id',$id)->where('locale','fr')->update(['name'=>'Nom personnalisé','example'=>'Exemple personnalisé']);
        $count = DB::table('categories')->count();
        $migration = require database_path('migrations/2026_11_24_000002_add_general_knowledge_categories.php');
        $migration->up(); $migration->up();
        $this->assertSame($count,DB::table('categories')->count());
        $this->assertSame(14,DB::table('game_categories')->where('game_id',$open->id)->count());
        $this->assertSame(14,DB::table('game_categories')->where('game_id',$open->id)->distinct()->count('position'));
        $this->assertSame(0,DB::table('game_categories')->where('game_id',$finished->id)->count());
        $this->assertSame([$id],$open->fresh()->used_categories);
        $this->assertSame(['A'],$open->fresh()->used_letters);
        $this->assertDatabaseHas('category_translations',['category_id'=>$id,'locale'=>'fr','name'=>'Nom personnalisé','example'=>'Exemple personnalisé']);
    }
}
