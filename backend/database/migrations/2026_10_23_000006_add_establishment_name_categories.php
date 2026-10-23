<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $categories = [
            ['restaurant_name', 1, 'Anaran’ny trano fisakafoanana', 'Nom de restaurant', 'Restaurant name'],
            ['karaoke_venue_name', 1, 'Anaran’ny toerana fanaovana karaoke', 'Nom de karaoké', 'Karaoke venue name'],
            ['bar_name', 1, 'Anaran’ny bara', 'Nom de bar', 'Bar name'],
            ['cafe_name', 1, 'Anaran’ny trano fisotroana kafe', 'Nom de café', 'Café name'],
            ['hotel_name', 1, 'Anaran’ny hotely', 'Nom d’hôtel', 'Hotel name'],
            ['pizzeria_name', 1, 'Anaran’ny trano fivarotana pizza', 'Nom de pizzeria', 'Pizzeria name'],
            ['bakery_name', 1, 'Anaran’ny trano fivarotana mofo', 'Nom de boulangerie', 'Bakery name'],
            ['fast_food_restaurant_name', 1, 'Anaran’ny trano fisakafoanana haingana', 'Nom de fast-food', 'Fast-food restaurant name'],
            ['supermarket_name', 1, 'Anaran’ny fivarotana lehibe', 'Nom de supermarché', 'Supermarket name'],
            ['shopping_mall_name', 2, 'Anaran’ny foibe fivarotana', 'Nom de centre commercial', 'Shopping mall name'],
            ['nightclub_name', 2, 'Anaran’ny toeram-pandihizana', 'Nom de boîte de nuit', 'Nightclub name'],
            ['cinema_name', 2, 'Anaran’ny trano fijerena sarimihetsika', 'Nom de cinéma', 'Cinema name'],
            ['gym_name', 2, 'Anaran’ny toeram-panatanjahantena', 'Nom de salle de sport', 'Gym name'],
            ['guesthouse_name', 2, 'Anaran’ny trano fandraisam-bahiny', 'Nom de maison d’hôtes', 'Guesthouse name'],
            ['ice_cream_shop_name', 2, 'Anaran’ny trano fivarotana gilasy', 'Nom de glacier', 'Ice cream shop name'],
            ['food_truck_name', 2, 'Anaran’ny fiara fivarotana sakafo', 'Nom de food truck', 'Food truck name'],
            ['tea_room_name', 3, 'Anaran’ny trano fisotroana dite', 'Nom de salon de thé', 'Tea room name'],
            ['spa_name', 3, 'Anaran’ny spa', 'Nom de spa', 'Spa name'],
            ['amusement_park_name', 3, 'Anaran’ny valan-javaboary fialam-boly', 'Nom de parc d’attractions', 'Amusement park name'],
            ['theater_venue_name', 3, 'Anaran’ny trano fanaovana teatra', 'Nom de théâtre', 'Theater venue name'],
        ];

        DB::transaction(function () use ($categories) {
            $position = (int) DB::table('categories')->max('sort_order') + 1;
            $ids = [];
            foreach ($categories as [$code, $difficulty, $mg, $fr, $en]) {
                $id = DB::table('categories')->where('code', $code)->value('id');
                // Preserve any category already created or edited by an administrator.
                if (!$id) {
                    $id = DB::table('categories')->insertGetId(['code' => $code, 'sort_order' => $position++, 'difficulty' => $difficulty]);
                    foreach (['mg' => $mg, 'fr' => $fr, 'en' => $en] as $locale => $name) {
                        DB::table('category_translations')->insert(['category_id' => $id, 'locale' => $locale, 'name' => $name]);
                    }
                }
                $ids[] = $id;
            }

            DB::table('games')->whereIn('status', ['lobby', 'playing'])->orderBy('id')->chunkById(100, function ($games) use ($ids) {
                foreach ($games as $game) {
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
        // Keep categories referenced by played rounds and their history.
    }
};
