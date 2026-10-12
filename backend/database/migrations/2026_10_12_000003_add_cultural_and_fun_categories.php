<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        $categories = [
            ['malagasy_dish', 'Sakafo malagasy', 'Plat malgache', 'Malagasy dish'],
            ['malagasy_dessert', 'Tsindrin-tsakafo malagasy', 'Dessert malgache', 'Malagasy dessert'],
            ['traditional_malagasy_food', 'Sakafo nentim-paharazana', 'Plat traditionnel malgache', 'Traditional Malagasy food'],
            ['malagasy_monarch', 'Mpanjaka malagasy', 'Roi ou reine de Madagascar', 'Malagasy king or queen'],
            ['malagasy_historical_figure', 'Olona malaza teo amin\'ny tantaran\'i Madagasikara', 'Personnage historique malgache', 'Malagasy historical figure'],
            ['madagascar_region', 'Faritra eto Madagasikara', 'Région de Madagascar', 'Region of Madagascar'],
            ['madagascar_district', 'Distrika eto Madagasikara', 'District de Madagascar', 'District of Madagascar'],
            ['madagascar_commune', 'Kaominina eto Madagasikara', 'Commune de Madagascar', 'Commune in Madagascar'],
            ['madagascar_national_park', 'Valan-javaboary eto Madagasikara', 'Parc national de Madagascar', 'National park in Madagascar'],
            ['malagasy_monument', 'Tsangambato eto Madagasikara', 'Monument malgache', 'Malagasy monument'],
            ['malagasy_company', 'Orinasa malagasy', 'Entreprise malgache', 'Malagasy company'],
            ['malagasy_brand', 'Marika malagasy', 'Marque malgache', 'Malagasy brand'],
            ['malagasy_proverb', 'Ohabolana malagasy', 'Proverbe malgache', 'Malagasy proverb'],
            ['malagasy_expression', 'Fitenenana malagasy', 'Expression malgache', 'Malagasy expression'],
            ['traditional_malagasy_game', 'Lalao nentim-paharazana', 'Jeu traditionnel malgache', 'Traditional Malagasy game'],
            ['traditional_malagasy_instrument', 'Zavamaneno malagasy', 'Instrument traditionnel malgache', 'Traditional Malagasy instrument'],
            ['malagasy_custom', 'Fomba amam-panao malagasy', 'Coutume malgache', 'Malagasy custom'],
            ['traditional_malagasy_festival', 'Fety nentim-paharazana', 'Fête traditionnelle malgache', 'Traditional Malagasy festival'],
            ['traditional_malagasy_object', 'Fitaovana nentim-paharazana', 'Objet traditionnel malgache', 'Traditional Malagasy object'],
            ['malagasy_historical_site', 'Toerana manan-tantara eto Madagasikara', 'Site historique malgache', 'Malagasy historical site'],
            ['malagasy_song', 'Hira malagasy', 'Chanson malgache', 'Malagasy song'],
            ['malagasy_music_group', 'Tarika malagasy', 'Groupe musical malgache', 'Malagasy music group'],
            ['malagasy_movie', 'Sarimihetsika malagasy', 'Film malgache', 'Malagasy movie'],
            ['malagasy_actor', 'Mpilalao sarimihetsika malagasy', 'Acteur ou actrice malgache', 'Malagasy actor or actress'],
            ['malagasy_comedian', 'Mpanao hatsikana malagasy', 'Humoriste malgache', 'Malagasy comedian'],
            ['malagasy_writer', 'Mpanoratra malagasy', 'Écrivain malgache', 'Malagasy writer'],
            ['traditional_malagasy_sport', 'Fanatanjahantena nentim-paharazana', 'Sport traditionnel malgache', 'Traditional Malagasy sport'],
            ['madagascar_ethnic_group', 'Foko eto Madagasikara', 'Groupe ethnique de Madagascar', 'Ethnic group in Madagascar'],
            ['malagasy_dance', 'Dihy malagasy', 'Danse malgache', 'Malagasy dance'],
            ['malagasy_handicraft', 'Asa tanana malagasy', 'Artisanat malgache', 'Malagasy handicraft'],
            ['scary_thing', 'Zavatra mampatahotra', 'Chose qui fait peur', 'Something scary'],
            ['funny_thing', 'Zavatra mampihomehy', 'Chose qui fait rire', 'Something funny'],
            ['embarrassing_thing', 'Zavatra mahamenatra', 'Chose embarrassante', 'Something embarrassing'],
            ['annoying_thing', 'Zavatra mahasosotra', 'Chose énervante', 'Something annoying'],
            ['smelly_thing', 'Zavatra maimbo', 'Chose qui sent mauvais', 'Something smelly'],
            ['weird_thing', 'Zavatra hafahafa', 'Chose bizarre', 'Something weird'],
            ['happy_thing', 'Zavatra mampifaly', 'Chose qui rend heureux', 'Something that makes you happy'],
            ['sleepy_thing', 'Zavatra mampatory', 'Chose qui donne sommeil', 'Something that makes you sleepy'],
            ['wake_up_thing', 'Zavatra mampifoha', 'Chose qui réveille', 'Something that wakes you up'],
            ['argument_cause', 'Zavatra mety hahatonga fifamaliana', 'Chose qui provoque une dispute', 'Something that causes an argument'],
            ['forgotten_thing', 'Zavatra adino matetika', 'Chose qu\'on oublie souvent', 'Something people often forget'],
            ['secret_activity', 'Zavatra atao an-tsokosoko', 'Chose qu\'on fait en cachette', 'Something done secretly'],
            ['alone_activity', 'Zavatra atao rehefa irery', 'Chose qu\'on fait quand on est seul', 'Something done alone'],
            ['bored_activity', 'Zavatra atao rehefa leo', 'Chose qu\'on fait quand on s\'ennuie', 'Something done when bored'],
            ['angry_activity', 'Zavatra atao rehefa tezitra', 'Chose qu\'on fait quand on est en colère', 'Something done when angry'],
            ['happy_activity', 'Zavatra atao rehefa faly', 'Chose qu’on fait quand on est heureux', 'Something done when happy'],
            ['never_lent_thing', 'Zavatra tsy ampindramina', 'Chose qu\'on ne prête jamais', 'Something you never lend'],
            ['thing_to_keep', 'Zavatra tsy tiana ho very', 'Chose qu\'on ne veut pas perdre', 'Something you don\'t want to lose'],
            ['unnecessary_purchase', 'Zavatra vidiana nefa tsy ilaina', 'Achat inutile', 'Unnecessary purchase'],
            ['inedible_thing', 'Zavatra tsy azo hanina', 'Chose non comestible', 'Something inedible'],
            ['fridge_thing', 'Zavatra hita ao anaty vata fampangatsiahana', 'Chose qu\'on trouve dans un frigo', 'Something found in a fridge'],
            ['under_bed_thing', 'Zavatra hita ao ambanin\'ny fandriana', 'Chose qu\'on trouve sous un lit', 'Something found under a bed'],
            ['bag_thing', 'Zavatra hita ao anaty kitapo', 'Chose qu\'on trouve dans un sac', 'Something found in a bag'],
            ['wedding_thing', 'Zavatra hita any amin\'ny mariazy', 'Chose qu\'on trouve à un mariage', 'Something found at a wedding'],
            ['church_thing', 'Zavatra hita any am-piangonana', 'Chose qu\'on trouve à l\'église', 'Something found in a church'],
            ['hospital_thing', 'Zavatra hita any amin\'ny hopitaly', 'Chose qu\'on trouve à l\'hôpital', 'Something found in a hospital'],
            ['garage_thing', 'Zavatra hita any amin\'ny garazy', 'Chose qu\'on trouve dans un garage', 'Something found in a garage'],
            ['workplace_thing', 'Zavatra hita eny amin\'ny toeram-piasana', 'Chose qu\'on trouve au travail', 'Something found at work'],
            ['travel_item', 'Zavatra entina rehefa mandeha lavitra', 'Chose à emporter en voyage', 'Something to bring on a trip'],
            ['rain_item', 'Zavatra ampiasaina rehefa avy ny orana', 'Chose utilisée quand il pleut', 'Something used when it rains'],
            ['darkness_item', 'Zavatra ampiasaina rehefa maizina', 'Chose utilisée dans le noir', 'Something used in the dark'],
            ['weekend_activity', 'Zavatra atao amin\'ny faran\'ny herinandro', 'Activité du week-end', 'Weekend activity'],
            ['breakdown_thing', 'Zavatra mety ho simba', 'Chose qui peut tomber en panne', 'Something that can break down'],
            ['breakable_thing', 'Zavatra mety ho tapaka', 'Chose qui peut se casser', 'Something that can snap'],
            ['late_excuse', 'Fialan-tsiny rehefa tara', 'Excuse pour arriver en retard', 'Excuse for being late'],
            ['school_absence_excuse', 'Fialan-tsiny tsy handehanana mianatra', 'Excuse pour manquer l\'école', 'Excuse for missing school'],
            ['work_absence_excuse', 'Fialan-tsiny tsy handehanana miasa', 'Excuse pour ne pas aller travailler', 'Excuse for missing work'],
            ['parents_annoyance', 'Zavatra mampatezitra ray aman-dreny', 'Chose qui énerve les parents', 'Something that annoys parents'],
            ['hidden_from_parents', 'Zavatra afenina amin\'ny ray aman-dreny', 'Chose qu\'on cache à ses parents', 'Something hidden from parents'],
            ['baby_laugh_thing', 'Zavatra mampihomehy zazakely', 'Chose qui fait rire un bébé', 'Something that makes a baby laugh'],
            ['forbidden_at_school', 'Zavatra tsy tokony hatao any am-pianarana', 'Chose interdite à l\'école', 'Something forbidden at school'],
            ['not_said_to_teacher', 'Zavatra tsy tokony holazaina amin\'ny mpampianatra', 'Chose à ne pas dire à un professeur', 'Something not to say to a teacher'],
            ['unwanted_gift', 'Fanomezana tsy tiana horaisina', 'Cadeau qu\'on ne veut pas recevoir', 'Unwanted gift'],
            ['vacation_spoiler', 'Zavatra mety hanimba fialan-tsasatra', 'Chose qui gâche les vacances', 'Something that ruins a vacation'],
            ['offline_activity', 'Zavatra atao rehefa tsy misy internet', 'Chose à faire sans internet', 'Something to do without internet'],
            ['sibling_argument', 'Zavatra mampiady mpiray tampo', 'Chose qui fait disputer frères et sœurs', 'Something siblings argue about'],
            ['not_said_at_wedding', 'Zavatra tsy tokony holazaina amin\'ny mariazy', 'Chose à ne pas dire à un mariage', 'Something not to say at a wedding'],
            ['cry_thing', 'Zavatra mampitomany', 'Chose qui fait pleurer', 'Something that makes you cry'],
            ['job_loss_cause', 'Zavatra mety hampiala amin\'ny asa', 'Chose qui peut faire perdre son emploi', 'Something that can get you fired'],
            ['insect', 'Bibikely', 'Insecte', 'Insect'],
            ['reptile', 'Biby mandady', 'Reptile', 'Reptile'],
            ['flower', 'Voninkazo', 'Fleur', 'Flower'],
            ['tree', 'Hazo', 'Arbre', 'Tree'],
            ['fruit_tree', 'Hazo fihinam-boa', 'Arbre fruitier', 'Fruit tree'],
            ['spice', 'Zava-manitra', 'Épice', 'Spice'],
            ['lake', 'Farihy', 'Lac', 'Lake'],
            ['waterfall', 'Riandrano', 'Cascade', 'Waterfall'],
            ['desert', 'Efitra', 'Désert', 'Desert'],
            ['forest', 'Ala', 'Forêt', 'Forest'],
            ['volcano', 'Volkano', 'Volcan', 'Volcano'],
            ['tourist_attraction', 'Toerana fizahan-tany', 'Site touristique', 'Tourist attraction'],
            ['famous_monument', 'Tsangambato malaza', 'Monument célèbre', 'Famous monument'],
            ['world_language', 'Fiteny eran-tany', 'Langue du monde', 'World language'],
            ['invention', 'Zavatra noforonina', 'Invention', 'Invention'],
        ];
        $position = (int) DB::table('categories')->max('sort_order') + 1;
        $ids = [];
        foreach ($categories as [$code,$mg,$fr,$en]) {
            $id = DB::table('categories')->where('code',$code)->value('id');
            if (!$id) $id = DB::table('categories')->insertGetId(['code'=>$code,'sort_order'=>$position++]);
            foreach (['mg'=>$mg,'fr'=>$fr,'en'=>$en] as $locale=>$name) {
                DB::table('category_translations')->updateOrInsert(['category_id'=>$id,'locale'=>$locale],['name'=>$name]);
            }
            $ids[] = $id;
        }
        DB::table('games')->orderBy('id')->chunkById(100, function ($games) use ($ids) {
            foreach ($games as $game) {
                $next = (int) DB::table('game_categories')->where('game_id',$game->id)->max('position') + 1;
                $existing = DB::table('game_categories')->where('game_id',$game->id)->pluck('category_id')->all();
                foreach (array_diff($ids,$existing) as $id) {
                    DB::table('game_categories')->insert(['game_id'=>$game->id,'category_id'=>$id,'position'=>$next++]);
                }
            }
        });
    }
    public function down(): void {
        // Preserve categories referenced by played rounds and their history.
    }
};