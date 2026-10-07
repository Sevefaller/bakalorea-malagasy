<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('round_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('round_id')->constrained()->cascadeOnDelete();
            $t->foreignId('player_id')->constrained('game_players');
            $t->string('body', 300);
            $t->timestamp('created_at', 3);
            $t->index(['round_id', 'id']);
        });
    }

    public function down(): void { Schema::dropIfExists('round_comments'); }
};
