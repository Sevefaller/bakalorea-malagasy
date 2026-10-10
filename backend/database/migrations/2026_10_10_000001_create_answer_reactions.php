<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('answer_reactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('answer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('player_id')->constrained('game_players')->cascadeOnDelete();
            $t->string('reaction', 10);
            $t->unique(['answer_id', 'player_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('answer_reactions'); }
};
