<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_categories_are_available_at_level_three_in_all_languages(): void
    {
        $catalog = collect($this->getJson('/api/categories')->assertOk()->json('categories'))->keyBy('code');
        $start = DB::table('categories')->where('code', 'communication_method')->value('sort_order');
        $end = DB::table('categories')->where('code', 'tattoo_artist')->value('sort_order');
        $added = DB::table('categories')->whereBetween('sort_order', [$start, $end])->get();
        $this->assertCount(61, $added);
        foreach ($added as $category) {
            $this->assertSame(3, $catalog[$category->code]['difficulty']);
            foreach (['mg', 'fr', 'en'] as $locale) {
                $this->assertNotEmpty($catalog[$category->code]['names'][$locale]);
            }
        }
        $this->assertSame('Objet à roulettes ou à roues', $catalog['wheeled_object']['names']['fr']);
        $this->assertSame('Zavatra vita amin’ny hazo', $catalog['wooden_object']['names']['mg']);
        $this->assertSame('Family activity', $catalog['family_activity']['names']['en']);
        $this->assertSame('Chose piquante', $catalog['prickly_thing']['names']['fr']);
        $this->assertSame('Zavatra masira', $catalog['salty_thing']['names']['mg']);
        $this->assertSame('Something slippery', $catalog['slippery_thing']['names']['en']);
        $this->assertSame('Nom de série', $catalog['tv_series']['names']['fr']);
        $this->assertSame('Anaran’ny onjam-peo', $catalog['radio_station']['names']['mg']);
        $this->assertSame('TV series character', $catalog['tv_series_character']['names']['en']);
        $this->assertSame('Nom de page Facebook', $catalog['facebook_page']['names']['fr']);
        $this->assertSame('Anaran’ny fantsona YouTube', $catalog['youtube_channel']['names']['mg']);
        $this->assertSame('YouTube channel name', $catalog['youtube_channel']['names']['en']);
        $this->assertSame('Acteur chauve', $catalog['bald_actor']['names']['fr']);
        $this->assertSame('Mpilalao sarimihetsika misy volombava', $catalog['bearded_actor']['names']['mg']);
        $this->assertSame('Actor or actress with glasses', $catalog['actor_with_glasses']['names']['en']);
        $this->assertSame('Acteur ou actrice avec des tatouages', $catalog['tattooed_actor']['names']['fr']);
        $this->assertSame('Mpanakanto misy tombokavatsa', $catalog['tattooed_artist']['names']['mg']);
        $this->assertSame('Tattoo artist', $catalog['tattoo_artist']['names']['en']);
        $this->assertFalse($catalog->has('tiktok_account'));
        $this->assertFalse($catalog->has('instagram_account'));
        $this->assertSame(1, $catalog['nap_interruption']['difficulty']);
        $this->assertSame(2, $catalog['robot_chore']['difficulty']);
    }

    public function test_standard_expansion_preserves_admin_edits_and_extends_open_rooms_once(): void
    {
        $id = DB::table('categories')->where('code', 'wooden_object')->value('id');
        DB::table('categories')->where('id', $id)->update(['difficulty' => 2]);
        DB::table('category_translations')->where('category_id', $id)->where('locale', 'fr')->update(['name' => 'Objet en bois personnalisé']);
        $open = [];
        foreach (['lobby', 'playing'] as $i => $status) {
            $game = Game::create(['code' => 'STD00'.$i, 'name' => 'Open', 'status' => $status, 'used_categories' => [$id], 'used_letters' => ['A']]);
            DB::table('game_categories')->insert(['game_id' => $game->id, 'category_id' => $id, 'position' => 4]);
            $open[] = $game;
        }
        $finished = Game::create(['code' => 'STDDON', 'name' => 'Finished', 'status' => 'finished']);
        $before = DB::table('categories')->count();
        $migration = require database_path('migrations/2026_11_02_000003_add_standard_level_three_categories.php');
        $migration->up();
        $migration->up();
        $this->assertSame($before, DB::table('categories')->count());
        foreach ($open as $game) {
            $this->assertSame(61, DB::table('game_categories')->where('game_id', $game->id)->count());
            $this->assertSame(61, DB::table('game_categories')->where('game_id', $game->id)->distinct()->count('position'));
            $this->assertSame([$id], $game->fresh()->used_categories);
            $this->assertSame(['A'], $game->fresh()->used_letters);
            $this->assertDatabaseHas('game_categories', ['game_id' => $game->id, 'category_id' => $id, 'position' => 4]);
        }
        $this->assertSame(0, DB::table('game_categories')->where('game_id', $finished->id)->count());
        $this->assertDatabaseHas('categories', ['id' => $id, 'difficulty' => 2]);
        $this->assertDatabaseHas('category_translations', ['category_id' => $id, 'locale' => 'fr', 'name' => 'Objet en bois personnalisé']);
    }

    public function test_fun_everyday_categories_are_translated_and_accessible(): void
    {
        $catalog = collect($this->getJson('/api/categories')->assertOk()->json('categories'))->keyBy('code');
        $start = DB::table('categories')->where('code', 'snack_to_share')->value('sort_order');
        $end = DB::table('categories')->where('code', 'imaginary_pet')->value('sort_order');
        $added = DB::table('categories')->whereBetween('sort_order', [$start, $end])->get();
        $this->assertCount(30, $added);
        foreach ($added as $category) {
            $this->assertContains($catalog[$category->code]['difficulty'], [1, 2]);
            foreach (['mg', 'fr', 'en'] as $locale) {
                $this->assertNotEmpty($catalog[$category->code]['names'][$locale]);
            }
        }
        $this->assertSame('Chose qui peut interrompre une sieste', $catalog['nap_interruption']['names']['fr']);
        $this->assertSame('Hery manokana tiana hananana', $catalog['superpower_wish']['names']['mg']);
        $this->assertSame('Object used as a pretend microphone', $catalog['pretend_microphone']['names']['en']);
    }

    public function test_fun_expansion_extends_open_rooms_once_without_resetting_cycles_or_admin_edits(): void
    {
        $id = DB::table('categories')->where('code', 'nap_interruption')->value('id');
        DB::table('categories')->where('id', $id)->update(['difficulty' => 2]);
        DB::table('category_translations')->where('category_id', $id)->where('locale', 'fr')->update(['name' => 'Sieste personnalisée']);
        $open = [];
        foreach (['lobby', 'playing'] as $i => $status) {
            $game = Game::create(['code' => 'FUN00'.$i, 'name' => 'Open', 'status' => $status, 'used_categories' => [$id], 'used_letters' => ['A']]);
            DB::table('game_categories')->insert(['game_id' => $game->id, 'category_id' => $id, 'position' => 4]);
            $open[] = $game;
        }
        $finished = Game::create(['code' => 'FUNDON', 'name' => 'Finished', 'status' => 'finished']);
        $before = DB::table('categories')->count();
        $migration = require database_path('migrations/2026_11_02_000001_add_fun_everyday_categories.php');
        $migration->up();
        $migration->up();
        $this->assertSame($before, DB::table('categories')->count());
        foreach ($open as $game) {
            $this->assertSame(30, DB::table('game_categories')->where('game_id', $game->id)->count());
            $this->assertSame(30, DB::table('game_categories')->where('game_id', $game->id)->distinct()->count('position'));
            $this->assertSame([$id], $game->fresh()->used_categories);
            $this->assertSame(['A'], $game->fresh()->used_letters);
            $this->assertDatabaseHas('game_categories', ['game_id' => $game->id, 'category_id' => $id, 'position' => 4]);
        }
        $this->assertSame(0, DB::table('game_categories')->where('game_id', $finished->id)->count());
        $this->assertDatabaseHas('categories', ['id' => $id, 'difficulty' => 2]);
        $this->assertDatabaseHas('category_translations', ['category_id' => $id, 'locale' => 'fr', 'name' => 'Sieste personnalisée']);
    }

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
        $this->assertFalse($catalog->has('tea_room_name'));

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
        $this->assertFalse($catalog->has('watch_brand'));
        $this->assertSame(2, $catalog['clothing_brand']['difficulty']);

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
        foreach ($advanced as $category) {
            $this->assertFalse($catalog->has($category->code));
        }

        $open = Game::create(['code' => 'HARD12', 'name' => 'Open', 'status' => 'lobby']);
        $finished = Game::create(['code' => 'DONE12', 'name' => 'Finished', 'status' => 'finished']);
        $id = DB::table('categories')->where('code', 'constellation')->value('id');
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
        $this->assertFalse($catalog->has('gemstone'));
        $this->assertFalse($catalog->has('chemical_element'));
        $this->assertSame(1, $catalog['school_subject']['difficulty']);
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
