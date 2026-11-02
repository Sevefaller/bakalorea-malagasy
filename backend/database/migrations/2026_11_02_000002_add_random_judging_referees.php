<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('rounds', fn (Blueprint $table) => $table->foreignId('referee_id')->nullable()->constrained('game_players')->nullOnDelete());
        Schema::table('answers', fn (Blueprint $table) => $table->foreignId('referee_id')->nullable()->constrained('game_players')->nullOnDelete());
    }

    public function down(): void {
        Schema::table('answers', fn (Blueprint $table) => $table->dropConstrainedForeignId('referee_id'));
        Schema::table('rounds', fn (Blueprint $table) => $table->dropConstrainedForeignId('referee_id'));
    }
};
