<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_establishment_names_are_available_in_all_three_languages_and_levels(): void
    {
        $catalog = collect($this->getJson('/api/categories')->assertOk()->json('categories'))->keyBy('code');
        $this->assertSame('Nom de restaurant', $catalog['restaurant_name']['names']['fr']);
        $this->assertSame('Anaran’ny bara', $catalog['bar_name']['names']['mg']);
        $this->assertSame('Karaoke venue name', $catalog['karaoke_venue_name']['names']['en']);
        $this->assertSame(1, $catalog['restaurant_name']['difficulty']);
        $this->assertSame(1, $catalog['karaoke_venue_name']['difficulty']);
        $this->assertSame(1, $catalog['bar_name']['difficulty']);
        $this->assertSame(2, $catalog['nightclub_name']['difficulty']);
        $this->assertSame(3, $catalog['tea_room_name']['difficulty']);

        $open = Game::create(['code' => 'PLACE1', 'name' => 'Open', 'status' => 'playing']);
        $before = DB::table('categories')->count();
        $migration = require database_path('migrations/2026_10_23_000006_add_establishment_name_categories.php');
        $migration->up();
        $migration->up();
        $this->assertSame($before, DB::table('categories')->count());
        $this->assertSame(20, DB::table('game_categories')->where('game_id', $open->id)->count());
        $this->assertSame(20, DB::table('game_categories')->where('game_id', $open->id)->distinct()->count('category_id'));
    }

    public function test_brand_expansion_is_translated_and_preserves_admin_changes_on_rerun(): void
    {
        $catalog = collect($this->getJson('/api/categories')->assertOk()->json('categories'))->keyBy('code');
        $this->assertSame('Marque de vêtements', $catalog['clothing_brand']['names']['fr']);
        $this->assertSame('Marika sôkôla', $catalog['chocolate_brand']['names']['mg']);
        $this->assertSame('Watch brand', $catalog['watch_brand']['names']['en']);
        $this->assertSame(2, $catalog['clothing_brand']['difficulty']);
        $this->assertSame(3, $catalog['watch_brand']['difficulty']);

        $open = Game::create(['code' => 'BRAND1', 'name' => 'Open', 'status' => 'lobby']);
        $finished = Game::create(['code' => 'BRAND2', 'name' => 'Finished', 'status' => 'finished']);
        $id = $catalog['clothing_brand']['id'];
        DB::table('categories')->where('id', $id)->update(['difficulty' => 1]);
        DB::table('category_translations')->where('category_id', $id)->where('locale', 'fr')->update(['name' => 'Marque de vêtements personnalisée']);
        DB::table('game_categories')->insert(['game_id' => $open->id, 'category_id' => $id, 'position' => 0]);
        $before = DB::table('categories')->count();
        $migration = require database_path('migrations/2026_10_23_000005_add_brand_categories.php');
        $migration->up();
        $migration->up();
        $this->assertSame($before, DB::table('categories')->count());
        $this->assertSame(30, DB::table('game_categories')->where('game_id', $open->id)->count());
        $this->assertSame(30, DB::table('game_categories')->where('game_id', $open->id)->distinct()->count('position'));
        $this->assertSame(0, DB::table('game_categories')->where('game_id', $finished->id)->count());
        $this->assertDatabaseHas('categories', ['id' => $id, 'difficulty' => 1]);
        $this->assertDatabaseHas('category_translations', ['category_id' => $id, 'locale' => 'fr', 'name' => 'Marque de vêtements personnalisée']);
    }

    public function test_advanced_expansion_is_level_three_translated_and_safe_to_repeat(): void
    {
        $lastPreviousPosition = DB::table('categories')->where('code', 'celebration_decoration')->value('sort_order');
        $lastAdvancedPosition = DB::table('categories')->where('code', 'mythological_creature')->value('sort_order');
        $advanced = DB::table('categories')->where('sort_order', '>', $lastPreviousPosition)->where('sort_order', '<=', $lastAdvancedPosition)->get();
        $this->assertCount(80, $advanced);
        $this->assertTrue($advanced->every(fn ($category) => (int) $category->difficulty === 3));
        foreach (['mg', 'fr', 'en'] as $locale) {
            $this->assertSame(80, DB::table('category_translations')->whereIn('category_id', $advanced->pluck('id'))->where('locale', $locale)->where('name', '<>', '')->count());
        }
        $catalog = collect($this->getJson('/api/categories')->assertOk()->json('categories'))->keyBy('code');
        $this->assertSame('Constellation', $catalog['constellation']['names']['fr']);
        $this->assertSame('Sarin-teny', $catalog['rhetorical_figure']['names']['mg']);
        $this->assertSame(3, $catalog['programming_language']['difficulty']);

        $open = Game::create(['code' => 'HARD12', 'name' => 'Open', 'status' => 'lobby']);
        $finished = Game::create(['code' => 'DONE12', 'name' => 'Finished', 'status' => 'finished']);
        $id = $catalog['constellation']['id'];
        DB::table('categories')->where('id', $id)->update(['difficulty' => 2]);
        DB::table('category_translations')->where('category_id', $id)->where('locale', 'fr')->update(['name' => 'Constellation personnalisée']);
        DB::table('game_categories')->insert(['game_id' => $open->id, 'category_id' => $id, 'position' => 0]);
        $before = DB::table('categories')->count();
        $migration = require database_path('migrations/2026_10_23_000003_add_advanced_categories.php');
        $migration->up();
        $migration->up();
        $this->assertSame($before, DB::table('categories')->count());
        $this->assertSame(80, DB::table('game_categories')->where('game_id', $open->id)->count());
        $this->assertSame(80, DB::table('game_categories')->where('game_id', $open->id)->distinct()->count('position'));
        $this->assertSame(0, DB::table('game_categories')->where('game_id', $finished->id)->count());
        $this->assertDatabaseHas('categories', ['id' => $id, 'difficulty' => 2]);
        $this->assertDatabaseHas('category_translations', ['category_id' => $id, 'locale' => 'fr', 'name' => 'Constellation personnalisée']);
    }

    public function test_expansion_is_translated_and_available_in_public_catalog(): void
    {
        $catalog = collect($this->getJson('/api/categories')->assertOk()->json('categories'))->keyBy('code');
        $this->assertSame('Matière scolaire', $catalog['school_subject']['names']['fr']);
        $this->assertSame('Vatosoa', $catalog['gemstone']['names']['mg']);
        $this->assertSame('Chemical element', $catalog['chemical_element']['names']['en']);
        $this->assertSame(1, $catalog['school_subject']['difficulty']);
        $this->assertSame(3, $catalog['chemical_element']['difficulty']);
        $this->assertSame('Pâtisserie', $catalog['pastry']['names']['fr']);
        $this->assertSame('Biby mampinono', $catalog['mammal']['names']['mg']);
        $this->assertSame('Party decoration', $catalog['celebration_decoration']['names']['en']);
        $this->assertSame(1, $catalog['container']['difficulty']);
        $this->assertSame(3, $catalog['energy_source']['difficulty']);
    }

    public function test_second_expansion_can_be_repeated_without_changing_history_or_admin_edits(): void
    {
        $open = Game::create(['code' => 'NEW123', 'name' => 'Playing', 'status' => 'playing']);
        $finished = Game::create(['code' => 'OLD123', 'name' => 'Finished', 'status' => 'finished']);
        $id = DB::table('categories')->where('code', 'pastry')->value('id');
        DB::table('categories')->where('id', $id)->update(['difficulty' => 3]);
        DB::table('category_translations')->where('category_id', $id)->where('locale', 'fr')->update(['name' => 'Pâtisserie personnalisée']);
        DB::table('game_categories')->insert(['game_id' => $open->id, 'category_id' => $id, 'position' => 0]);
        $before = DB::table('categories')->count();
        $migration = require database_path('migrations/2026_10_23_000002_add_food_science_and_imagination_categories.php');
        $migration->up();
        $migration->up();

        $this->assertSame($before, DB::table('categories')->count());
        $this->assertSame(40, DB::table('game_categories')->where('game_id', $open->id)->count());
        $this->assertSame(40, DB::table('game_categories')->where('game_id', $open->id)->distinct()->count('position'));
        $this->assertSame(0, DB::table('game_categories')->where('game_id', $finished->id)->count());
        $this->assertDatabaseHas('categories', ['id' => $id, 'difficulty' => 3]);
        $this->assertDatabaseHas('category_translations', ['category_id' => $id, 'locale' => 'fr', 'name' => 'Pâtisserie personnalisée']);
    }

    public function test_rerun_preserves_admin_changes_and_only_attaches_to_open_rooms(): void
    {
        $open = Game::create(['code' => 'EXP123', 'name' => 'Open', 'status' => 'lobby']);
        $finished = Game::create(['code' => 'END123', 'name' => 'Finished', 'status' => 'finished']);
        $id = DB::table('categories')->where('code', 'school_subject')->value('id');
        DB::table('categories')->where('id', $id)->update(['difficulty' => 2]);
        DB::table('category_translations')->where('category_id', $id)->where('locale', 'fr')->update(['name' => 'Matière personnalisée']);
        DB::table('game_categories')->insert(['game_id' => $open->id, 'category_id' => $id, 'position' => 0]);
        $before = DB::table('categories')->count();
        $migration = require database_path('migrations/2026_10_23_000001_add_everyday_and_knowledge_categories.php');
        $migration->up();
        $migration->up();

        $this->assertSame($before, DB::table('categories')->count());
        $this->assertSame(30, DB::table('game_categories')->where('game_id', $open->id)->count());
        $this->assertSame(30, DB::table('game_categories')->where('game_id', $open->id)->distinct()->count('position'));
        $this->assertSame(0, DB::table('game_categories')->where('game_id', $finished->id)->count());
        $this->assertDatabaseHas('categories', ['id' => $id, 'difficulty' => 2]);
        $this->assertDatabaseHas('category_translations', ['category_id' => $id, 'locale' => 'fr', 'name' => 'Matière personnalisée']);
    }
}
