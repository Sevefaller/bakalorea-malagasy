<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $categories = [
            ['pastry', 2, 'Mofomamy', 'Pâtisserie', 'Pastry'],
            ['soup', 2, 'Lasopy', 'Soupe', 'Soup'],
            ['cheese', 3, 'Fromazy', 'Fromage', 'Cheese'],
            ['aromatic_herb', 2, 'Anana manitra', 'Herbe aromatique', 'Aromatic herb'],
            ['seafood', 2, 'Hazan-dranomasina', 'Fruit de mer', 'Seafood'],
            ['cooking_method', 2, 'Fomba fahandroana', 'Mode de cuisson', 'Cooking method'],
            ['fabric', 2, 'Lamba', 'Tissu', 'Fabric'],
            ['building_material', 2, 'Akora fanorenana', 'Matériau de construction', 'Building material'],
            ['container', 1, 'Zavatra fitoerana', 'Récipient', 'Container'],
            ['cleaning_product', 1, 'Vokatra fanadiovana', 'Produit de nettoyage', 'Cleaning product'],
            ['personal_care_item', 1, 'Zavatra ampiasaina hikarakarana vatana', 'Produit ou accessoire de toilette', 'Personal care item'],
            ['shop_type', 1, 'Karazana fivarotana', 'Type de magasin', 'Type of shop'],
            ['public_building', 1, 'Tranom-panjakana na trano ho an’ny daholobe', 'Bâtiment public', 'Public building'],
            ['emergency_item', 2, 'Fitaovana vonjy taitra', 'Objet utile en cas d’urgence', 'Emergency item'],
            ['farm_tool', 2, 'Fitaovam-pambolena', 'Outil agricole', 'Farm tool'],
            ['farm_product', 1, 'Vokatra avy amin’ny fambolena na fiompiana', 'Produit agricole', 'Farm product'],
            ['weather_phenomenon', 2, 'Trangan’ny toetrandro', 'Phénomène météorologique', 'Weather phenomenon'],
            ['natural_landscape', 2, 'Karazana tontolo voajanahary', 'Paysage naturel', 'Natural landscape'],
            ['energy_source', 3, 'Loharanon’angovo', 'Source d’énergie', 'Energy source'],
            ['geometric_shape', 2, 'Endrika jeometrika', 'Forme géométrique', 'Geometric shape'],
            ['mammal', 2, 'Biby mampinono', 'Mammifère', 'Mammal'],
            ['nocturnal_animal', 3, 'Biby mivoaka amin’ny alina', 'Animal nocturne', 'Nocturnal animal'],
            ['freshwater_animal', 2, 'Biby an-dranomamy', 'Animal d’eau douce', 'Freshwater animal'],
            ['climbing_plant', 3, 'Zavamaniry mandady na mihanika', 'Plante grimpante', 'Climbing plant'],
            ['water_sport', 2, 'Fanatanjahantena anaty rano na ambony rano', 'Sport aquatique', 'Water sport'],
            ['team_sport', 2, 'Fanatanjahantena iarahan’ny ekipa', 'Sport collectif', 'Team sport'],
            ['combat_sport', 3, 'Fanatanjahantena miady', 'Sport de combat', 'Combat sport'],
            ['magic_item', 2, 'Zavatra majika ao anaty tantara', 'Objet magique dans une fiction', 'Magical object in fiction'],
            ['fairy_tale', 3, 'Angano', 'Conte merveilleux', 'Fairy tale'],
            ['fictional_villain', 2, 'Olon-dratsy ao anaty tantara', 'Méchant de fiction', 'Fictional villain'],
            ['comic_book', 3, 'Boky tantara an-tsary', 'Bande dessinée', 'Comic book'],
            ['party_game', 2, 'Lalao atao mandritra ny fety', 'Jeu à faire en soirée', 'Party game'],
            ['picnic_item', 1, 'Zavatra entina mitsangantsangana hisakafo', 'Objet à emporter en pique-nique', 'Picnic item'],
            ['lost_thing', 1, 'Zavatra very matetika', 'Chose qu’on perd souvent', 'Something often lost'],
            ['glowing_thing', 2, 'Zavatra mamirapiratra ao anaty haizina', 'Chose qui brille dans le noir', 'Something that glows in the dark'],
            ['flying_thing', 1, 'Zavatra manidina', 'Chose qui vole dans les airs', 'Something that flies'],
            ['stretchy_thing', 2, 'Zavatra azo ahenjana', 'Chose extensible', 'Something stretchy'],
            ['paired_thing', 2, 'Zavatra mandeha tsiroaroa', 'Chose qui va par paire', 'Something that comes in pairs'],
            ['lucky_charm', 3, 'Zavatra heverina fa mitondra vintana', 'Porte-bonheur', 'Lucky charm'],
            ['celebration_decoration', 1, 'Haingon-trano amin’ny fety', 'Décoration de fête', 'Party decoration'],
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
