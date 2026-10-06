<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('categories', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->unsignedInteger('sort_order'); });
        Schema::create('category_translations', function (Blueprint $t) { $t->id(); $t->foreignId('category_id')->constrained()->cascadeOnDelete(); $t->string('locale', 2); $t->string('name'); $t->unique(['category_id','locale']); });
        $categories = [
            ['male_name','Anarana lahy','Prénom masculin','Boy’s name'], ['female_name','Anarana vavy','Prénom féminin','Girl’s name'],
            ['plant','Anarana zavamaniry','Plante','Plant'], ['fruit','Voankazo','Fruit','Fruit'],
            ['malagasy_artist','Artiste gasy','Artiste malgache','Malagasy artist'], ['international_artist','Artiste vazaha','Artiste international','International artist'],
            ['malagasy_place','Toerana gasy','Lieu à Madagascar','Place in Madagascar'], ['international_place','Toerana any ivelany','Lieu à l’étranger','Place abroad'],
        ];
        foreach ($categories as $i => $c) { $id = DB::table('categories')->insertGetId(['code'=>$c[0], 'sort_order'=>$i]); foreach (['mg','fr','en'] as $j=>$locale) DB::table('category_translations')->insert(['category_id'=>$id,'locale'=>$locale,'name'=>$c[$j+1]]); }
        Schema::create('games', function (Blueprint $t) { $t->id(); $t->string('code',6)->unique(); $t->string('name',60); $t->unsignedBigInteger('host_id')->nullable(); $t->string('status')->default('lobby'); $t->unsignedInteger('target_score')->default(200); $t->unsignedInteger('answer_duration')->default(15); $t->unsignedInteger('unique_points')->default(10); $t->unsignedInteger('duplicate_points')->default(5); $t->string('anti_cheat_mode')->default('normal'); $t->string('letters')->default('ABDEFGHIJKLMNOPRSTV'); $t->boolean('no_repeat')->default(true); $t->json('used_letters')->nullable(); $t->unsignedBigInteger('winner_id')->nullable(); $t->timestamps(); });
        Schema::create('game_players', function (Blueprint $t) { $t->id(); $t->foreignId('game_id')->constrained()->cascadeOnDelete(); $t->string('nickname',24); $t->string('nickname_key',80); $t->string('locale',2)->default('fr'); $t->string('session_id',64); $t->unsignedInteger('score')->default(0); $t->timestamp('last_seen_at')->nullable(); $t->timestamp('left_at')->nullable(); $t->timestamps(); $t->unique(['game_id','nickname_key']); });
        Schema::create('game_categories', function (Blueprint $t) { $t->id(); $t->foreignId('game_id')->constrained()->cascadeOnDelete(); $t->foreignId('category_id')->constrained(); $t->unsignedInteger('position'); $t->unique(['game_id','position']); });
        Schema::create('rounds', function (Blueprint $t) { $t->id(); $t->foreignId('game_id')->constrained()->cascadeOnDelete(); $t->foreignId('category_id')->constrained(); $t->unsignedInteger('number'); $t->string('letter',1); $t->string('status')->default('answering'); $t->timestamp('started_at',3); $t->timestamp('answer_deadline',3); $t->timestamp('finished_at',3)->nullable(); $t->timestamps(); $t->unique(['game_id','number']); });
        Schema::create('answers', function (Blueprint $t) { $t->id(); $t->foreignId('round_id')->constrained()->cascadeOnDelete(); $t->foreignId('player_id')->constrained('game_players'); $t->string('answer',120)->default(''); $t->string('normalized',120)->default(''); $t->unsignedInteger('revision')->default(0); $t->timestamp('last_saved_at',3)->nullable(); $t->boolean('is_locked')->default(false); $t->boolean('flagged')->default(false); $t->string('invalid_reason')->nullable(); $t->boolean('verdict')->nullable(); $t->boolean('tie_decision')->nullable(); $t->unsignedInteger('points_awarded')->default(0); $t->unique(['round_id','player_id']); });
        Schema::create('votes', function (Blueprint $t) { $t->id(); $t->foreignId('answer_id')->constrained()->cascadeOnDelete(); $t->foreignId('voter_player_id')->constrained('game_players'); $t->string('vote'); $t->unique(['answer_id','voter_player_id']); });
        Schema::create('anti_cheat_events', function (Blueprint $t) { $t->id(); $t->foreignId('round_id')->constrained()->cascadeOnDelete(); $t->foreignId('player_id')->constrained('game_players'); $t->string('event_type'); $t->timestamp('started_at',3); $t->timestamp('ended_at',3)->nullable(); $t->unsignedInteger('duration_ms')->default(0); });
        Schema::create('personal_access_tokens', function (Blueprint $t) { $t->id(); $t->morphs('tokenable'); $t->text('name'); $t->string('token',64)->unique(); $t->text('abilities')->nullable(); $t->timestamp('last_used_at')->nullable(); $t->timestamp('expires_at')->nullable()->index(); $t->timestamps(); });
    }
    public function down(): void { foreach (['personal_access_tokens','anti_cheat_events','votes','answers','rounds','game_categories','game_players','games','category_translations','categories'] as $table) Schema::dropIfExists($table); }
};
