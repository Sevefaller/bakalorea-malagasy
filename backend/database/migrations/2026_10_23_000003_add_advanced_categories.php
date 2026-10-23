<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $categories = [
            ['branch_of_science', 3, 'Sampan’ny siansa', 'Discipline scientifique', 'Scientific discipline'],
            ['mathematical_concept', 3, 'Hevitra matematika', 'Notion mathématique', 'Mathematical concept'],
            ['physics_concept', 3, 'Hevitra fizika', 'Notion de physique', 'Physics concept'],
            ['chemistry_concept', 3, 'Hevitra simika', 'Notion de chimie', 'Chemistry concept'],
            ['biology_term', 3, 'Voambolana biolojika', 'Terme de biologie', 'Biology term'],
            ['geological_term', 3, 'Voambolana momba ny haibolantany', 'Terme de géologie', 'Geology term'],
            ['astronomical_object', 3, 'Zavatra eny amin’ny habakabaka', 'Objet astronomique', 'Astronomical object'],
            ['constellation', 3, 'Antokon-kintana', 'Constellation', 'Constellation'],
            ['space_equipment', 3, 'Fitaovana ampiasaina eny amin’ny habakabaka', 'Équipement spatial', 'Space equipment'],
            ['scientific_instrument', 3, 'Fitaovana siantifika', 'Instrument scientifique', 'Scientific instrument'],
            ['laboratory_item', 3, 'Zavatra hita ao amin’ny laboratoara', 'Objet de laboratoire', 'Laboratory item'],
            ['measurement_device', 3, 'Fitaovam-pandrefesana', 'Appareil de mesure', 'Measuring device'],
            ['anatomical_organ', 3, 'Taova', 'Organe du corps', 'Body organ'],
            ['human_bone', 3, 'Taolana ao amin’ny vatan’olombelona', 'Os du corps humain', 'Human bone'],
            ['muscle', 3, 'Hozatra', 'Muscle', 'Muscle'],
            ['medical_specialty', 3, 'Sehatra manokana amin’ny fitsaboana', 'Spécialité médicale', 'Medical specialty'],
            ['medical_instrument', 3, 'Fitaovam-pitsaboana', 'Instrument médical', 'Medical instrument'],
            ['microorganism', 3, 'Zavamananaina bitika', 'Micro-organisme', 'Microorganism'],
            ['cell_component', 3, 'Singa ao amin’ny sela', 'Composant d’une cellule', 'Cell component'],
            ['ecosystem', 3, 'Rafitra iainan’ny zavamananaina sy ny manodidina azy', 'Écosystème', 'Ecosystem'],
            ['rock_type', 3, 'Karazana vatolampy', 'Type de roche', 'Rock type'],
            ['mineral', 3, 'Mineraly', 'Minéral', 'Mineral'],
            ['geological_process', 3, 'Fizotran-javatra ara-jeolojika', 'Processus géologique', 'Geological process'],
            ['cloud_type', 3, 'Karazana rahona', 'Type de nuage', 'Cloud type'],
            ['named_wind', 3, 'Anaran-drivotra', 'Vent portant un nom', 'Named wind'],
            ['marine_mammal', 3, 'Biby mampinono an-dranomasina', 'Mammifère marin', 'Marine mammal'],
            ['bird_of_prey', 3, 'Vorona mpihaza', 'Rapace', 'Bird of prey'],
            ['amphibian', 3, 'Biby amfibiana', 'Amphibien', 'Amphibian'],
            ['arachnid', 3, 'Biby ao amin’ny sokajin’ny hala', 'Arachnide', 'Arachnid'],
            ['mollusk', 3, 'Biby moloska', 'Mollusque', 'Mollusk'],
            ['crustacean', 3, 'Biby krustasea', 'Crustacé', 'Crustacean'],
            ['pollinating_animal', 3, 'Biby mpamindra vovobony', 'Animal pollinisateur', 'Pollinating animal'],
            ['medicinal_plant', 3, 'Zavamaniry fanao fanafody', 'Plante médicinale', 'Medicinal plant'],
            ['aquatic_plant', 3, 'Zavamaniry anaty rano', 'Plante aquatique', 'Aquatic plant'],
            ['plant_family', 3, 'Fianakavian-javamaniry', 'Famille de plantes', 'Plant family'],
            ['tree_part', 3, 'Ampahan’ny hazo', 'Partie d’un arbre', 'Part of a tree'],
            ['extinct_animal', 3, 'Biby efa lany tamingana', 'Animal disparu', 'Extinct animal'],
            ['dinosaur', 3, 'Dinôzôro', 'Dinosaure', 'Dinosaur'],
            ['archaeological_site', 3, 'Toerana fikarohana arkeolojika', 'Site archéologique', 'Archaeological site'],
            ['ancient_civilization', 3, 'Sivilizasiona fahiny', 'Civilisation antique', 'Ancient civilization'],
            ['historical_empire', 3, 'Empira teo amin’ny tantara', 'Empire historique', 'Historical empire'],
            ['historical_event', 3, 'Zava-nitranga teo amin’ny tantara', 'Événement historique', 'Historical event'],
            ['historical_treaty', 3, 'Fifanarahana teo amin’ny tantara', 'Traité historique', 'Historical treaty'],
            ['explorer', 3, 'Mpikaroka tany', 'Explorateur ou exploratrice', 'Explorer'],
            ['philosopher', 3, 'Filôzôfa', 'Philosophe', 'Philosopher'],
            ['architectural_style', 3, 'Fomba maritrano', 'Style architectural', 'Architectural style'],
            ['architectural_element', 3, 'Singa amin’ny maritrano', 'Élément architectural', 'Architectural element'],
            ['art_movement', 3, 'Firehana ara-javakanto', 'Courant artistique', 'Art movement'],
            ['famous_painting', 3, 'Sary hosodoko malaza', 'Tableau célèbre', 'Famous painting'],
            ['famous_sculpture', 3, 'Sary sokitra malaza', 'Sculpture célèbre', 'Famous sculpture'],
            ['literary_genre', 3, 'Karazan-dahatsoratra literatiora', 'Genre littéraire', 'Literary genre'],
            ['poetic_form', 3, 'Endrika tononkalo', 'Forme poétique', 'Poetic form'],
            ['rhetorical_figure', 3, 'Sarin-teny', 'Figure de style', 'Figure of speech'],
            ['grammatical_term', 3, 'Voambolana momba ny fitsipi-pitenenana', 'Terme de grammaire', 'Grammar term'],
            ['musical_genre', 3, 'Karazana mozika', 'Genre musical', 'Musical genre'],
            ['musical_technique', 3, 'Teknika amin’ny mozika', 'Technique musicale', 'Musical technique'],
            ['orchestral_instrument', 3, 'Zavamaneno ao amin’ny orkesitra', 'Instrument d’orchestre', 'Orchestral instrument'],
            ['programming_language', 3, 'Fiteny fandaharana informatika', 'Langage de programmation', 'Programming language'],
            ['computer_component', 3, 'Singa ao amin’ny solosaina', 'Composant informatique', 'Computer component'],
            ['network_term', 3, 'Voambolana momba ny tambajotra informatika', 'Terme de réseau informatique', 'Computer networking term'],
            ['file_format', 3, 'Endrika rakitra informatika', 'Format de fichier', 'File format'],
            ['cybersecurity_term', 3, 'Voambolana momba ny fiarovana informatika', 'Terme de cybersécurité', 'Cybersecurity term'],
            ['language_family', 3, 'Fianakaviam-piteny', 'Famille de langues', 'Language family'],
            ['writing_system', 3, 'Rafitra fanoratana', 'Système d’écriture', 'Writing system'],
            ['currency', 3, 'Vola ampiasain’ny firenena', 'Monnaie d’un pays', 'Currency'],
            ['economic_concept', 3, 'Hevitra ara-toekarena', 'Notion économique', 'Economic concept'],
            ['legal_term', 3, 'Voambolana ara-dalàna', 'Terme juridique', 'Legal term'],
            ['navigation_instrument', 3, 'Fitaovana fitarihan-dalana', 'Instrument de navigation', 'Navigation instrument'],
            ['nautical_term', 3, 'Voambolana momba ny fitetezana an-dranomasina', 'Terme de navigation maritime', 'Nautical term'],
            ['textile_technique', 3, 'Teknika amin’ny fanamboarana lamba', 'Technique textile', 'Textile technique'],
            ['engineering_field', 3, 'Sehatry ny injeniera', 'Branche de l’ingénierie', 'Engineering field'],
            ['mechanical_component', 3, 'Singa mekanika', 'Pièce mécanique', 'Mechanical component'],
            ['electronic_component', 3, 'Singa elektronika', 'Composant électronique', 'Electronic component'],
            ['renewable_energy_technology', 3, 'Teknolojia momba ny angovo azo havaozina', 'Technologie d’énergie renouvelable', 'Renewable energy technology'],
            ['agricultural_technique', 3, 'Teknika fambolena', 'Technique agricole', 'Agricultural technique'],
            ['food_preservation_method', 3, 'Fomba fitehirizana sakafo', 'Méthode de conservation des aliments', 'Food preservation method'],
            ['cartography_term', 3, 'Voambolana momba ny fanaovana sarintany', 'Terme de cartographie', 'Cartography term'],
            ['photography_term', 3, 'Voambolana momba ny fakana sary', 'Terme de photographie', 'Photography term'],
            ['cinema_technique', 3, 'Teknika amin’ny sarimihetsika', 'Technique de cinéma', 'Filmmaking technique'],
            ['mythological_creature', 3, 'Zavaboary ao amin’ny angano', 'Créature mythologique', 'Mythological creature'],
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
