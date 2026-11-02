<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $categories = [
            ['horned_animal', 'Biby misy tandroka', 'Animal avec des cornes', 'Animal with horns'],
            ['social_animal', 'Biby miaina anaty vondrona', 'Animal qui vit en groupe', 'Animal that lives in a group'],
            ['peelable_fruit', 'Voankazo voasana alohan’ny hanina', 'Fruit qu’on épluche', 'Fruit people peel'],
            ['hot_food', 'Sakafo hanina mafana', 'Aliment qu’on mange chaud', 'Food eaten hot'],
            ['pocket_item', 'Zavatra hita ao am-paosy', 'Chose qu’on trouve dans une poche', 'Something found in a pocket'],
            ['measuring_object', 'Fitaovana ampiasaina handrefesana', 'Objet qu’on utilise pour mesurer', 'Object used for measuring'],
            ['beach_packing_item', 'Zavatra entina eny amoron-dranomasina', 'Chose qu’on emporte à la plage', 'Something you take to the beach'],
            ['night_job', 'Asa atao amin’ny alina', 'Métier où l’on travaille la nuit', 'Job where people work at night'],
            ['helping_profession', 'Asa anampiana ny hafa', 'Métier où l’on aide les autres', 'Job where people help others'],
            ['powered_character', 'Olona ao anaty tantara manana hery manokana', 'Personnage qui possède des pouvoirs', 'Character with special powers'],
            ['singer_instrumentalist', 'Mpihira mitendry zavamaneno', 'Chanteur ou chanteuse qui joue d’un instrument', 'Singer who plays an instrument'],
            ['child_gift', 'Zavatra omena ny ankizy ho fanomezana', 'Objet qu’on offre à un enfant', 'Object given to a child as a gift'],
            ['hotel_item', 'Zavatra hita ao amin’ny hotely', 'Chose qu’on trouve dans un hôtel', 'Something found in a hotel'],
            ['five_letter_malagasy_word', 'Teny malagasy misy litera dimy', 'Mot malagasy de cinq lettres', 'Five-letter Malagasy word'],
            ['five_letter_english_word', 'Teny anglisy misy litera dimy', 'Mot anglais de cinq lettres', 'Five-letter English word'],
            ['jumping_animal', 'Biby mitsambikina', 'Animal qui saute', 'Animal that jumps'],
            ['legless_animal', 'Biby tsy manan-tongotra', 'Animal sans pattes', 'Animal without legs'],
            ['spoon_food', 'Sakafo hanina amin’ny sotro', 'Aliment qu’on mange avec une cuillère', 'Food eaten with a spoon'],
            ['canned_food', 'Sakafo anaty boatin-kapoaka', 'Aliment vendu en conserve', 'Food sold in a can'],
            ['rice_dish', 'Sakafo vita amin’ny vary', 'Plat à base de riz', 'Rice-based dish'],
            ['screen_object', 'Zavatra misy efijery', 'Objet qui possède un écran', 'Object with a screen'],
            ['scissors_cut_thing', 'Zavatra tapahina amin’ny hety', 'Chose qu’on coupe avec des ciseaux', 'Something cut with scissors'],
            ['hair_accessory', 'Kojakoja fikarakarana volo', 'Accessoire de coiffure', 'Hair accessory'],
            ['travel_job', 'Asa ilana fivezivezena', 'Métier où l’on voyage', 'Job that involves travelling'],
            ['bakery_item', 'Zavatra hita ao amin’ny fivarotam-mofo', 'Objet qu’on trouve dans une boulangerie', 'Object found in a bakery'],
            ['flying_character', 'Olona ao anaty tantara afaka manidina', 'Personnage qui sait voler', 'Character who can fly'],
            ['singer_stage_name', 'Mpihira mampiasa anarana an-tsehatra', 'Chanteur ou chanteuse utilisant un pseudonyme', 'Singer who uses a stage name'],
            ['shelled_animal', 'Biby misy akorany', 'Animal avec une carapace', 'Animal with a shell'],
            ['burrowing_animal', 'Biby mihady lavaka hipetrahana', 'Animal qui creuse un terrier', 'Animal that digs a burrow'],
            ['steamed_food', 'Sakafo andrahoina amin’ny etona', 'Aliment cuit à la vapeur', 'Steamed food'],
            ['rechargeable_object', 'Zavatra azo averina fahanana herinaratra', 'Objet qu’on peut recharger', 'Object you can recharge'],
            ['zippered_object', 'Zavatra misy zip', 'Objet avec une fermeture éclair', 'Object with a zipper'],
            ['toothed_object', 'Zavatra misy nify', 'Objet qui possède des dents', 'Object with teeth'],
            ['outdoor_job', 'Asa atao eny an-kalamanjana', 'Métier pratiqué en extérieur', 'Job done outdoors'],
            ['gloved_job', 'Asa anaovana fonon-tanana', 'Métier où l’on porte des gants', 'Job where people wear gloves'],
            ['video_game_character', 'Olona na biby ao anaty lalao video', 'Personnage de jeu vidéo', 'Video game character'],
            ['sports_club', 'Anaran’ny klioba fanatanjahantena', 'Nom de club sportif', 'Sports club name'],
            ['bicycle_accessory', 'Kojakojan’ny bisikileta', 'Accessoire de vélo', 'Bicycle accessory'],
            ['four_letter_french_word', 'Teny frantsay misy litera efatra', 'Mot français de quatre lettres', 'Four-letter French word'],
            ['big_eared_animal', 'Biby lehibe sofina', 'Animal avec de grandes oreilles', 'Animal with big ears'],
            ['stone_fruit', 'Voankazo misy voa mafy', 'Fruit qui contient un noyau', 'Fruit with a pit'],
            ['sandwich_ingredient', 'Zavatra atao ao anaty sandwich', 'Ingrédient qu’on met dans un sandwich', 'Sandwich ingredient'],
            ['pizza_topping', 'Zavatra atao eo ambonin’ny pizza', 'Garniture de pizza', 'Pizza topping'],
            ['frozen_food', 'Sakafo tehirizina ao anaty vata fampangatsiahana mandry', 'Aliment qu’on conserve au congélateur', 'Food kept in a freezer'],
            ['writing_object', 'Zavatra ampiasaina hanoratana', 'Objet qu’on utilise pour écrire', 'Object used for writing'],
            ['sun_protection_item', 'Fitaovana miaro amin’ny masoandro', 'Objet qui protège du soleil', 'Object that protects from the sun'],
            ['pocketed_clothing', 'Fitafiana misy paosy', 'Vêtement avec des poches', 'Clothing with pockets'],
            ['public_transport', 'Fitaterana iombonana', 'Moyen de transport collectif', 'Form of public transport'],
            ['quiet_place', 'Toerana tokony hitenenana moramora', 'Lieu où l’on doit parler doucement', 'Place where people should speak quietly'],
            ['newspaper_magazine', 'Anaran-gazety na gazetiboky', 'Nom de journal ou de magazine', 'Newspaper or magazine title'],
            ['fairy_tale_character', 'Olona na biby ao anaty angano', 'Personnage de conte', 'Fairy-tale character'],
        ];

        DB::transaction(function () use ($categories) {
            // Reuse the existing noise category instead of adding a duplicate.
            DB::table('categories')->where('code', 'noisy_thing')->update(['difficulty' => 3]);
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
