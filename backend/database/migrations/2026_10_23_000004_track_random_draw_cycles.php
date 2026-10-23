<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void {
        Schema::table('games', fn (Blueprint $table) => $table->json('used_categories')->nullable());

        // Resume existing rooms from their history, including older rooms that
        // allowed repeats or received additional categories partway through play.
        DB::table('games')->orderBy('id')->chunkById(100, function ($games) {
            foreach ($games as $game) {
                $categories = DB::table('game_categories')->where('game_id', $game->id)->pluck('category_id')->all();
                $letters = array_unique(str_split($game->letters));
                $usedCategories = [];
                $usedLetters = [];
                foreach (DB::table('rounds')->where('game_id', $game->id)->orderBy('number')->cursor() as $round) {
                    if (in_array($round->category_id, $categories)) {
                        if (!array_diff($categories, $usedCategories)) $usedCategories = [];
                        $usedCategories[] = $round->category_id;
                        $usedCategories = array_values(array_unique($usedCategories));
                    }
                    if (in_array($round->letter, $letters)) {
                        if (!array_diff($letters, $usedLetters)) $usedLetters = [];
                        $usedLetters[] = $round->letter;
                        $usedLetters = array_values(array_unique($usedLetters));
                    }
                }
                DB::table('games')->where('id', $game->id)->update([
                    'used_categories' => json_encode($usedCategories, JSON_THROW_ON_ERROR),
                    'used_letters' => json_encode($usedLetters, JSON_THROW_ON_ERROR),
                    'no_repeat' => true,
                ]);
            }
        });
    }

    public function down(): void {
        Schema::table('games', fn (Blueprint $table) => $table->dropColumn('used_categories'));
    }
};
