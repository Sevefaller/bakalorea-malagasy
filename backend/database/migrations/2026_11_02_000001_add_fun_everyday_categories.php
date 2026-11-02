<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $categories = [
            ['snack_to_share', 1, 'Tsakitsaky azo zaraina amin’ny namana', 'Goûter à partager entre amis', 'Snack to share with friends'],
            ['food_eaten_with_hands', 1, 'Sakafo hanina amin’ny tanana', 'Aliment qu’on mange avec les doigts', 'Food eaten with your hands'],
            ['messy_food', 1, 'Sakafo mandoto rehefa hanina', 'Aliment qui salit quand on le mange', 'Messy food to eat'],
            ['crunchy_food', 1, 'Sakafo mikarepoka rehefa tsakoina', 'Aliment qui croustille', 'Crunchy food'],
            ['clothing_stain_cause', 1, 'Zavatra mety handoto akanjo', 'Chose qui peut tacher un vêtement', 'Something that can stain clothes'],
            ['food_burnt_in_kitchen', 1, 'Sakafo mety ho may rehefa andrahoina', 'Aliment qu’on peut faire brûler en cuisinant', 'Food you might burn while cooking'],
            ['pet_mischief', 2, 'Hadalana ataon’ny biby fiompy', 'Bêtise faite par un animal de compagnie', 'Mischief a pet might get up to'],
            ['pet_toy', 1, 'Zavatra ilalaovan’ny biby fiompy', 'Jouet pour un animal de compagnie', 'Toy for a pet'],
            ['funny_pet_name', 2, 'Anarana mampihomehy ho an’ny biby fiompy', 'Nom amusant pour un animal de compagnie', 'Funny name for a pet'],
            ['unexpected_door_visitor', 1, 'Olona mety handondona eo am-baravarana', 'Personne qui peut sonner à la porte', 'Someone who might knock at the door'],
            ['nap_interruption', 1, 'Zavatra mety hanapaka torimaso atoandro', 'Chose qui peut interrompre une sieste', 'Something that can interrupt a nap'],
            ['morning_first_action', 1, 'Zavatra atao vao mifoha maraina', 'Chose qu’on fait au réveil', 'Something you do when you wake up'],
            ['before_guests_arrive', 2, 'Zavatra atao alohan’ny hahatongavan’ny vahiny', 'Chose à faire avant l’arrivée des invités', 'Something to do before guests arrive'],
            ['power_cut_activity', 2, 'Zavatra atao rehefa tapaka ny jiro', 'Activité à faire pendant une coupure de courant', 'Activity during a power cut'],
            ['rain_shelter', 1, 'Toerana azo ialofana rehefa avy ny orana', 'Endroit où s’abriter de la pluie', 'Place to shelter from the rain'],
            ['forgotten_in_pocket', 1, 'Zavatra hadino ao am-paosy', 'Chose oubliée dans une poche', 'Something forgotten in a pocket'],
            ['suitcase_surprise', 2, 'Zavatra tsy ampoizina hita ao anaty valizy', 'Chose surprenante à trouver dans une valise', 'Surprising thing to find in a suitcase'],
            ['school_break_activity', 1, 'Zavatra atao mandritra ny fakan-drivotra any an-tsekoly', 'Activité pendant la récréation', 'School break activity'],
            ['queue_activity', 2, 'Zavatra atao eo am-piandrasana filaharana', 'Chose qu’on fait en attendant dans une file', 'Something to do while waiting in a queue'],
            ['dance_floor_item', 1, 'Zavatra hita eny amin’ny toerana fandihizana', 'Chose qu’on trouve sur une piste de danse', 'Something found on a dance floor'],
            ['birthday_wish', 2, 'Faniriana atao amin’ny fitsingerenan’ny andro nahaterahana', 'Souhait pour un anniversaire', 'Birthday wish'],
            ['party_costume', 2, 'Fitafiana atao hanaovana sarin-tava amin’ny fety', 'Idée de déguisement pour une fête', 'Costume idea for a party'],
            ['pretend_microphone', 2, 'Zavatra azo atao mikrô rehefa mody mihira', 'Objet utilisé comme micro pour faire semblant de chanter', 'Object used as a pretend microphone'],
            ['improvised_drum', 2, 'Zavatra azo kapohina toy ny amponga', 'Objet qu’on peut utiliser comme tambour', 'Object you can use as a drum'],
            ['unusual_hat', 2, 'Zavatra azo atao satroka mampihomehy', 'Objet qu’on peut porter comme chapeau amusant', 'Object you can wear as a funny hat'],
            ['superpower_wish', 2, 'Hery manokana tiana hananana', 'Super-pouvoir qu’on aimerait avoir', 'Superpower you would like to have'],
            ['robot_chore', 2, 'Asa ao an-trano tiana hampanaovina robot', 'Tâche ménagère qu’on confierait à un robot', 'Household chore you would give a robot'],
            ['giant_object', 2, 'Zavatra hampihomehy raha lasa goavam-be', 'Objet qui serait amusant en version géante', 'Object that would be funny if it were giant'],
            ['miniature_object', 2, 'Zavatra hampihomehy raha lasa bitika', 'Objet qui serait amusant en version miniature', 'Object that would be funny in miniature'],
            ['imaginary_pet', 2, 'Biby foronina tiana hompiana', 'Animal imaginaire qu’on aimerait adopter', 'Imaginary animal you would like as a pet'],
        ];

        DB::transaction(function () use ($categories) {
            $position = (int) DB::table('categories')->max('sort_order') + 1;
            $ids = [];
            foreach ($categories as [$code, $difficulty, $mg, $fr, $en]) {
                $id = DB::table('categories')->where('code', $code)->value('id');
                // Keep categories and translations already edited by an administrator.
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
