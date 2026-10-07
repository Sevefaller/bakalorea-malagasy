<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $categories = [
            ['animal', 'Biby', 'Animal', 'Animal'],
            ['bird', 'Vorona', 'Oiseau', 'Bird'],
            ['fish', 'Trondro', 'Poisson', 'Fish'],
            ['vegetable', 'Legioma', 'Légume', 'Vegetable'],
            ['food', 'Sakafo', 'Nourriture / plat', 'Food / dish'],
            ['drink', 'Zava-pisotro', 'Boisson', 'Drink'],
            ['household_item', 'Fitaovana ao an-trano', 'Objet de la maison', 'Household item'],
            ['furniture', 'Fanaka', 'Meuble', 'Furniture'],
            ['clothing', 'Fitafiana', 'Vêtement', 'Clothing'],
            ['footwear', 'Kiraro', 'Chaussure', 'Footwear'],
            ['profession', 'Asa / Taranja asa', 'Métier', 'Profession'],
            ['sport', 'Fanatanjahantena', 'Sport', 'Sport'],
            ['vehicle', 'Fiara', 'Véhicule', 'Vehicle'],
            ['car_brand', 'Marika fiara', 'Marque de voiture', 'Car brand'],
            ['country', 'Firenena', 'Pays', 'Country'],
            ['capital_city', 'Renivohitra', 'Capitale', 'Capital city'],
            ['city', 'Tanàna', 'Ville', 'City'],
            ['island', 'Nosy', 'Île', 'Island'],
            ['mountain', 'Tendrombohitra', 'Montagne', 'Mountain'],
            ['river', 'Renirano', 'Fleuve / rivière', 'River'],
            ['sea', 'Ranomasina', 'Mer / océan', 'Sea / ocean'],
            ['color', 'Loko', 'Couleur', 'Color'],
            ['school_item', 'Zavatra ao an-tsekoly', 'Objet scolaire', 'School item'],
            ['kitchen_item', 'Zavatra ao an-dakozia', 'Objet de cuisine', 'Kitchen item'],
            ['bedroom_item', 'Zavatra ao amin’ny efitra fatoriana', 'Objet de chambre', 'Bedroom item'],
            ['office_item', 'Zavatra ao amin’ny birao', 'Objet de bureau', 'Office item'],
            ['electronic_device', 'Fitaovana elektronika', 'Appareil électronique', 'Electronic device'],
            ['phone_brand', 'Marika finday', 'Marque de téléphone', 'Phone brand'],
            ['movie', 'Sarimihetsika', 'Film', 'Movie'],
            ['actor', 'Mpilalao sarimihetsika', 'Acteur / actrice', 'Actor / actress'],
            ['singer', 'Mpihira', 'Chanteur / chanteuse', 'Singer'],
            ['music_group', 'Tarika mozika', 'Groupe de musique', 'Music group'],
            ['song', 'Hira', 'Chanson', 'Song'],
            ['book', 'Boky', 'Livre', 'Book'],
            ['writer', 'Mpanoratra', 'Écrivain', 'Writer'],
            ['cartoon', 'Tantara an-tsary', 'Dessin animé', 'Cartoon'],
            ['adjective', 'Toetra / adjectif', 'Adjectif', 'Adjective'],
            ['verb', 'Matoanteny', 'Verbe', 'Verb'],
            ['round_thing', 'Zavatra boribory', 'Chose ronde', 'Round thing'],
            ['big_thing', 'Zavatra lehibe', 'Grande chose', 'Big thing'],
            ['small_thing', 'Zavatra kely', 'Petite chose', 'Small thing'],
            ['street_thing', 'Zavatra hita eny an-dalana', 'Chose qu’on trouve dans la rue', 'Thing found in the street'],
            ['school_thing', 'Zavatra hita any an-tsekoly', 'Chose qu’on trouve à l’école', 'Thing found at school'],
            ['market_thing', 'Zavatra hita eny an-tsena', 'Chose qu’on trouve au marché', 'Thing found at a market'],
            ['beach_thing', 'Zavatra hita any amoron-dranomasina', 'Chose qu’on trouve à la plage', 'Thing found at the beach'],
            ['sea_animal', 'Biby an-dranomasina', 'Animal marin', 'Sea animal'],
            ['domestic_animal', 'Biby fiompy', 'Animal domestique', 'Pet / domestic animal'],
            ['wild_animal', 'Biby dia', 'Animal sauvage', 'Wild animal'],
            ['sweet_thing', 'Zavatra mamy', 'Chose sucrée', 'Sweet thing'],
            ['cold_thing', 'Zavatra mangatsiaka', 'Chose froide', 'Cold thing'],
            ['hot_thing', 'Zavatra mafana', 'Chose chaude', 'Hot thing'],
            ['fragrant_thing', 'Zavatra manitra', 'Chose qui sent bon', 'Nice-smelling thing'],
            ['gift_idea', 'Zavatra mety ho fanomezana', 'Idée de cadeau', 'Gift idea'],
        ];

        $position = (int) DB::table('categories')->max('sort_order') + 1;
        foreach ($categories as [$code, $mg, $fr, $en]) {
            $id = DB::table('categories')->insertGetId(['code' => $code, 'sort_order' => $position++]);
            foreach (['mg' => $mg, 'fr' => $fr, 'en' => $en] as $locale => $name) {
                DB::table('category_translations')->insert(['category_id' => $id, 'locale' => $locale, 'name' => $name]);
            }
        }

        // Rooms created before this migration should receive the new categories too.
        foreach (DB::table('games')->pluck('id') as $gameId) {
            $next = (int) DB::table('game_categories')->where('game_id', $gameId)->max('position') + 1;
            foreach (DB::table('categories')->where('sort_order', '>=', 8)->orderBy('sort_order')->pluck('id') as $categoryId) {
                DB::table('game_categories')->insert(['game_id' => $gameId, 'category_id' => $categoryId, 'position' => $next++]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('categories')->where('sort_order', '>=', 8)->pluck('id');
        DB::table('game_categories')->whereIn('category_id', $ids)->delete();
        DB::table('categories')->whereIn('id', $ids)->delete();
    }
};
