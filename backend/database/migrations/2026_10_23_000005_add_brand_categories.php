<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $categories = [
            ['clothing_brand', 2, 'Marika akanjo', 'Marque de vêtements', 'Clothing brand'],
            ['shoe_brand', 2, 'Marika kiraro', 'Marque de chaussures', 'Shoe brand'],
            ['sports_brand', 2, 'Marika fitaovana sy fitafy fanatanjahantena', 'Marque de sport', 'Sports brand'],
            ['luxury_brand', 3, 'Marika entana lafo vidy', 'Marque de luxe', 'Luxury brand'],
            ['watch_brand', 3, 'Marika famantaranandro', 'Marque de montres', 'Watch brand'],
            ['jewelry_brand', 3, 'Marika firavaka', 'Marque de bijoux', 'Jewelry brand'],
            ['perfume_brand', 2, 'Marika ranomanitra', 'Marque de parfums', 'Perfume brand'],
            ['cosmetics_brand', 2, 'Marika vokatra fanatsarana tarehy', 'Marque de cosmétiques', 'Cosmetics brand'],
            ['skincare_brand', 2, 'Marika vokatra fikarakarana hoditra', 'Marque de soins de la peau', 'Skincare brand'],
            ['haircare_brand', 2, 'Marika vokatra fikarakarana volo', 'Marque de produits capillaires', 'Haircare brand'],
            ['electronics_brand', 2, 'Marika fitaovana elektronika', 'Marque d’électronique', 'Electronics brand'],
            ['computer_brand', 2, 'Marika solosaina', 'Marque d’ordinateurs', 'Computer brand'],
            ['home_appliance_brand', 2, 'Marika fitaovana elektrika an-tokantrano', 'Marque d’électroménager', 'Home appliance brand'],
            ['audio_brand', 3, 'Marika fitaovana fanamafisam-peo', 'Marque de matériel audio', 'Audio equipment brand'],
            ['camera_brand', 3, 'Marika fakantsary', 'Marque d’appareils photo', 'Camera brand'],
            ['bicycle_brand', 3, 'Marika bisikileta', 'Marque de vélos', 'Bicycle brand'],
            ['motorcycle_brand', 2, 'Marika môtô', 'Marque de motos', 'Motorcycle brand'],
            ['tire_brand', 3, 'Marika kodiarana', 'Marque de pneus', 'Tire brand'],
            ['tool_brand', 3, 'Marika fitaovana fiasana', 'Marque d’outillage', 'Tool brand'],
            ['furniture_brand', 3, 'Marika fanaka', 'Marque de meubles', 'Furniture brand'],
            ['food_brand', 2, 'Marika sakafo', 'Marque alimentaire', 'Food brand'],
            ['drink_brand', 2, 'Marika zava-pisotro', 'Marque de boissons', 'Drink brand'],
            ['chocolate_brand', 2, 'Marika sôkôla', 'Marque de chocolat', 'Chocolate brand'],
            ['dairy_brand', 2, 'Marika ronono sy vokatra avy amin’ny ronono', 'Marque de produits laitiers', 'Dairy brand'],
            ['bottled_water_brand', 2, 'Marika rano anaty tavoahangy', 'Marque d’eau en bouteille', 'Bottled water brand'],
            ['stationery_brand', 2, 'Marika fitaovam-panoratana', 'Marque de papeterie', 'Stationery brand'],
            ['toy_brand', 2, 'Marika kilalao', 'Marque de jouets', 'Toy brand'],
            ['cleaning_brand', 2, 'Marika vokatra fanadiovana', 'Marque de produits ménagers', 'Cleaning product brand'],
            ['coffee_brand', 2, 'Marika kafe', 'Marque de café', 'Coffee brand'],
            ['luggage_brand', 3, 'Marika valizy sy kitapo fitsangatsanganana', 'Marque de bagages', 'Luggage brand'],
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
