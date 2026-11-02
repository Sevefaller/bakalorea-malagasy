<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $categories = [
            ['verb_french', 'Matoanteny amin’ny teny frantsay', 'Verbe en français', 'Verb in French'],
            ['verb_malagasy', 'Matoanteny amin’ny teny malagasy', 'Verbe en malagasy', 'Verb in Malagasy'],
            ['verb_english', 'Matoanteny amin’ny teny anglisy', 'Verbe en anglais', 'Verb in English'],
        ];

        DB::transaction(function () use ($categories) {
            $position = (int) DB::table('categories')->max('sort_order') + 1;
            $ids = [];
            foreach ($categories as [$code, $mg, $fr, $en]) {
                $id = DB::table('categories')->where('code', $code)->value('id');
                if (!$id) {
                    $id = DB::table('categories')->insertGetId(['code' => $code, 'sort_order' => $position++, 'difficulty' => 3]);
                    foreach (['mg' => $mg, 'fr' => $fr, 'en' => $en] as $locale => $name) {
                        DB::table('category_translations')->insert(['category_id' => $id, 'locale' => $locale, 'name' => $name]);
                    }
                }
                $ids[] = $id;
            }

            DB::table('games')->whereIn('status', ['lobby', 'playing'])->orderBy('id')->chunkById(100, function ($games) use ($ids) {
                foreach ($games as $game) {
                    $locked = DB::table('games')->where('id', $game->id)->lockForUpdate()->first();
                    if (!$locked || !in_array($locked->status, ['lobby', 'playing'], true)) continue;
                    $next = (int) DB::table('game_categories')->where('game_id', $game->id)->max('position') + 1;
                    $existing = DB::table('game_categories')->where('game_id', $game->id)->pluck('category_id')->all();
                    foreach (array_diff($ids, $existing) as $id) {
                        DB::table('game_categories')->insert(['game_id' => $game->id, 'category_id' => $id, 'position' => $next++]);
                    }
                }
            });
        });
    }

    public function down(): void {
        // Preserve categories referenced by played rounds and their history.
    }
};
