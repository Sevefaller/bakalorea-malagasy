<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('round_contests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('round_id')->constrained()->cascadeOnDelete();
            $t->foreignId('player_id')->constrained('game_players');
            $t->foreignId('comment_id')->constrained('round_comments')->cascadeOnDelete();
            $t->foreignId('acknowledged_by')->nullable()->constrained('game_players');
            $t->timestamp('acknowledged_at', 3)->nullable();
            $t->unique(['round_id', 'player_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('round_contests'); }
};
