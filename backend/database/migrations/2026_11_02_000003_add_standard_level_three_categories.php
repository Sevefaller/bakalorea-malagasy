<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $categories = [
            ['communication_method', 'Fomba ifandraisana', 'Moyen de communication', 'Way to communicate'],
            ['workplace', 'Toerana iasana', 'Lieu de travail', 'Place of work'],
            ['outdoor_game', 'Lalao atao eny an-kalamanjana', 'Jeu de plein air', 'Outdoor game'],
            ['wooden_object', 'Zavatra vita amin’ny hazo', 'Objet en bois', 'Wooden object'],
            ['plastic_object', 'Zavatra vita amin’ny plastika', 'Objet en plastique', 'Plastic object'],
            ['paper_object', 'Zavatra vita amin’ny taratasy', 'Objet en papier', 'Paper object'],
            ['metal_object', 'Zavatra vita amin’ny metaly', 'Objet en métal', 'Metal object'],
            ['waiting_place', 'Toerana fiandrasana', 'Lieu où l’on attend', 'Place where people wait'],
            ['eating_place', 'Toerana fisakafoanana', 'Endroit où manger', 'Place to eat'],
            ['family_activity', 'Zavatra atao miaraka amin’ny fianakaviana', 'Activité à faire en famille', 'Family activity'],
            ['borrowed_object', 'Zavatra azo indramina', 'Objet qu’on peut emprunter', 'Object you can borrow'],
            ['collectible_thing', 'Zavatra azo angonina', 'Chose qu’on collectionne', 'Something people collect'],
            ['repairable_thing', 'Zavatra azo amboarina rehefa simba', 'Chose qu’on peut réparer', 'Something you can repair'],
            ['battery_powered_object', 'Zavatra mandeha amin’ny pila', 'Objet qui fonctionne à piles', 'Battery-powered object'],
            ['lockable_thing', 'Zavatra azo hidiana amin’ny lakile', 'Chose qu’on ferme à clé', 'Something you lock with a key'],
            ['inflatable_thing', 'Zavatra azo tsofina rivotra', 'Chose qu’on peut gonfler', 'Something you can inflate'],
            ['melting_thing', 'Zavatra mitsonika rehefa mafana', 'Chose qui fond à la chaleur', 'Something that melts in the heat'],
            ['wheeled_object', 'Zavatra misy kodiarana', 'Objet à roulettes ou à roues', 'Object with wheels'],
            ['foldable_thing', 'Zavatra azo aforitra', 'Chose qu’on peut plier', 'Something you can fold'],
            ['hanging_thing', 'Zavatra azo ahantona', 'Chose qu’on peut suspendre', 'Something you can hang up'],
            ['prickly_thing', 'Zavatra manindrona na masiaka', 'Chose piquante', 'Something prickly or spicy'],
            ['sour_thing', 'Zavatra marikivy', 'Chose acide au goût', 'Something sour-tasting'],
            ['bitter_thing', 'Zavatra mangidy', 'Chose amère', 'Something bitter'],
            ['salty_thing', 'Zavatra masira', 'Chose salée', 'Something salty'],
            ['soft_thing', 'Zavatra malefaka rehefa kasihina', 'Chose douce au toucher', 'Something soft to the touch'],
            ['rough_thing', 'Zavatra marokoroko', 'Chose rugueuse', 'Something rough'],
            ['smooth_thing', 'Zavatra malama rehefa kasihina', 'Chose lisse', 'Something smooth'],
            ['sharp_thing', 'Zavatra maranitra afaka manapaka', 'Chose coupante', 'Something that can cut'],
            ['heavy_thing', 'Zavatra mavesatra', 'Chose lourde', 'Something heavy'],
            ['lightweight_thing', 'Zavatra maivana', 'Chose légère', 'Something lightweight'],
            ['slippery_thing', 'Zavatra mampibolisatra', 'Chose glissante', 'Something slippery'],
            ['wet_thing', 'Zavatra lena', 'Chose mouillée', 'Something wet'],
            ['dry_thing', 'Zavatra maina', 'Chose sèche', 'Something dry'],
            ['dusty_thing', 'Zavatra feno vovoka', 'Chose poussiéreuse', 'Something dusty'],
            ['foamy_thing', 'Zavatra misy roatra', 'Chose qui mousse', 'Something foamy'],
            ['reflective_thing', 'Zavatra mamerina hazavana', 'Chose qui reflète la lumière', 'Something that reflects light'],
            ['striped_thing', 'Zavatra misy tsipika', 'Chose à rayures', 'Something striped'],
            ['spotted_thing', 'Zavatra misy pentina', 'Chose à pois ou à taches', 'Something dotted or spotted'],
            ['square_thing', 'Zavatra miendrika efamira', 'Chose carrée', 'Something square'],
            ['triangular_thing', 'Zavatra miendrika telozoro', 'Chose triangulaire', 'Something triangular'],
            ['tv_series', 'Anaran-tsarimihetsika mitohy', 'Nom de série', 'TV series title'],
            ['tv_show', 'Anaran’ny fandaharana amin’ny fahitalavitra', 'Nom d’émission de télévision', 'TV show title'],
            ['tv_series_character', 'Olona ao anaty sarimihetsika mitohy', 'Personnage de série', 'TV series character'],
            ['cartoon_character', 'Olona na biby ao anaty tantara an-tsary', 'Personnage de dessin animé', 'Cartoon character'],
            ['movie_character', 'Olona ao anaty sarimihetsika', 'Personnage de film', 'Movie character'],
            ['tv_presenter', 'Mpanolotra fandaharana amin’ny fahitalavitra', 'Présentateur ou présentatrice télé', 'TV presenter'],
            ['radio_station', 'Anaran’ny onjam-peo', 'Nom de station de radio', 'Radio station name'],
            ['tv_channel', 'Anaran’ny fahitalavitra', 'Nom de chaîne de télévision', 'TV channel name'],
            ['online_content_creator', 'Mpamorona votoaty an-tserasera', 'Créateur ou créatrice de contenu sur Internet', 'Online content creator'],
            ['fictional_place', 'Toerana foronina ao anaty tantara', 'Lieu imaginaire dans une fiction', 'Fictional place'],
            ['facebook_page', 'Anaran’ny pejy Facebook', 'Nom de page Facebook', 'Facebook page name'],
            ['facebook_group', 'Anaran’ny vondrona Facebook', 'Nom de groupe Facebook', 'Facebook group name'],
            ['youtube_channel', 'Anaran’ny fantsona YouTube', 'Nom de chaîne YouTube', 'YouTube channel name'],
            ['bald_actor', 'Mpilalao sarimihetsika sola', 'Acteur chauve', 'Bald actor'],
            ['bearded_actor', 'Mpilalao sarimihetsika misy volombava', 'Acteur barbu', 'Bearded actor'],
            ['actor_with_glasses', 'Mpilalao sarimihetsika manao solomaso', 'Acteur ou actrice avec des lunettes', 'Actor or actress with glasses'],
            ['action_movie_actor', 'Mpilalao sarimihetsika misy ady sy fifanenjehana', 'Acteur ou actrice de films d’action', 'Action movie actor or actress'],
            ['comedy_movie_actor', 'Mpilalao sarimihetsika mampihomehy', 'Acteur ou actrice de comédies', 'Comedy movie actor or actress'],
            ['tattooed_actor', 'Mpilalao sarimihetsika misy tombokavatsa', 'Acteur ou actrice avec des tatouages', 'Actor or actress with tattoos'],
            ['tattooed_artist', 'Mpanakanto misy tombokavatsa', 'Artiste avec des tatouages', 'Artist with tattoos'],
            ['tattoo_artist', 'Mpanao tombokavatsa', 'Tatoueur ou tatoueuse', 'Tattoo artist'],
        ];

        DB::transaction(function () use ($categories) {
            $position = (int) DB::table('categories')->max('sort_order') + 1;
            $ids = [];
            foreach ($categories as [$code, $mg, $fr, $en]) {
                $id = DB::table('categories')->where('code', $code)->value('id');
                // Preserve any category already created or edited by an administrator.
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
