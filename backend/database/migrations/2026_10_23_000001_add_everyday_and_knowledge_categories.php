<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $categories = [
            ['school_subject', 1, 'Taranja ianarana', 'Matière scolaire', 'School subject'],
            ['body_part', 1, 'Faritra amin’ny vatana', 'Partie du corps', 'Body part'],
            ['hand_tool', 1, 'Fitaovana fiasana tanana', 'Outil à main', 'Hand tool'],
            ['jewelry', 2, 'Firavaka', 'Bijou', 'Jewelry'],
            ['clothing_accessory', 1, 'Kojakoja fitafy', 'Accessoire vestimentaire', 'Clothing accessory'],
            ['dessert', 1, 'Tsindrin-tsakafo', 'Dessert', 'Dessert'],
            ['breakfast_food', 1, 'Sakafo maraina', 'Aliment du petit-déjeuner', 'Breakfast food'],
            ['sauce', 2, 'Saosy', 'Sauce', 'Sauce'],
            ['cereal_grain', 2, 'Voamadinika', 'Céréale', 'Cereal grain'],
            ['sports_equipment', 1, 'Fitaovana fanatanjahantena', 'Équipement sportif', 'Sports equipment'],
            ['board_game', 2, 'Lalao an-databatra', 'Jeu de société', 'Board game'],
            ['video_game', 2, 'Lalao video', 'Jeu vidéo', 'Video game'],
            ['fictional_character', 2, 'Olona ao anaty tantara', 'Personnage de fiction', 'Fictional character'],
            ['superhero', 2, 'Mahery fo manana hery manokana', 'Super-héros ou super-héroïne', 'Superhero'],
            ['dance_style', 3, 'Karazana dihy', 'Style de danse', 'Dance style'],
            ['garden_item', 1, 'Zavatra hita ao an-jaridaina', 'Chose qu’on trouve dans un jardin', 'Something found in a garden'],
            ['airport_thing', 2, 'Zavatra hita eny amin’ny seranam-piaramanidina', 'Chose qu’on trouve dans un aéroport', 'Something found at an airport'],
            ['bathroom_item', 1, 'Zavatra hita ao amin’ny efitra fandroana', 'Objet de salle de bains', 'Bathroom item'],
            ['camping_item', 2, 'Fitaovana entina mitoby', 'Objet à emporter en camping', 'Camping item'],
            ['floating_thing', 2, 'Zavatra mitsingevana', 'Chose qui flotte', 'Something that floats'],
            ['sticky_thing', 2, 'Zavatra miraikitra', 'Chose collante', 'Something sticky'],
            ['noisy_thing', 1, 'Zavatra mitabataba', 'Chose bruyante', 'Something noisy'],
            ['transparent_thing', 2, 'Zavatra mangarahara', 'Chose transparente', 'Something transparent'],
            ['recyclable_thing', 2, 'Zavatra azo ahodina indray', 'Chose recyclable', 'Something recyclable'],
            ['childhood_memory', 2, 'Zavatra mampahatsiahy ny fahazazana', 'Chose qui rappelle l’enfance', 'Something that reminds you of childhood'],
            ['rainy_day_activity', 2, 'Zavatra atao rehefa avy ny orana', 'Activité à faire un jour de pluie', 'Rainy day activity'],
            ['gemstone', 3, 'Vatosoa', 'Pierre précieuse ou fine', 'Gemstone'],
            ['metal', 3, 'Metaly', 'Métal', 'Metal'],
            ['chemical_element', 3, 'Singa simika', 'Élément chimique', 'Chemical element'],
            ['unit_of_measurement', 3, 'Ventim-pandrefesana', 'Unité de mesure', 'Unit of measurement'],
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
