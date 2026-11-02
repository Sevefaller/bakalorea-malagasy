<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $categories = [
            ['uniform_profession', 'Asa anaovana fanamiana', 'Métier où l’on porte un uniforme', 'Job where people wear a uniform'],
            ['ball_sport', 'Fanatanjahantena ampiasana baolina', 'Sport qui se joue avec un ballon', 'Sport played with a ball'],
            ['egg_laying_animal', 'Biby manatody', 'Animal qui pond des œufs', 'Animal that lays eggs'],
            ['raw_food', 'Zavatra hanina manta', 'Chose qu’on mange crue', 'Something eaten raw'],
            ['car_interior_item', 'Zavatra hita ao anaty fiara', 'Objet qu’on trouve dans une voiture', 'Object found inside a car'],
            ['pharmacy_item', 'Zavatra vidiana eny amin’ny fivarotam-panafody', 'Chose qu’on achète à la pharmacie', 'Something bought at a pharmacy'],
            ['masked_character', 'Olona ao anaty tantara manao saron-tava', 'Personnage qui porte un masque', 'Character who wears a mask'],
            ['horror_movie', 'Sarimihetsika mampatahotra', 'Film qui fait peur', 'Scary movie'],
            ['dance_song', 'Hira mampandihy', 'Chanson qui fait danser', 'Song that makes people dance'],
            ['cleaning_item', 'Fitaovana ampiasaina hanadiovana', 'Objet qu’on utilise pour nettoyer', 'Object used for cleaning'],
            ['five_letter_french_word', 'Teny frantsay misy litera dimy', 'Mot français de cinq lettres', 'Five-letter French word'],
            ['adjective_french', 'Mpamaritra amin’ny teny frantsay', 'Adjectif en français', 'Adjective in French'],
            ['adjective_malagasy', 'Mpamaritra amin’ny teny malagasy', 'Adjectif en malagasy', 'Adjective in Malagasy'],
            ['adjective_english', 'Mpamaritra amin’ny teny anglisy', 'Adjectif en anglais', 'Adjective in English'],
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
