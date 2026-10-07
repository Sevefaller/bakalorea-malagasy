<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('round_comments', function (Blueprint $table) {
            $table->uuid('client_id')->nullable();
            $table->unique(['round_id', 'player_id', 'client_id']);
        });
    }

    public function down(): void {
        Schema::table('round_comments', function (Blueprint $table) {
            $table->dropUnique(['round_id', 'player_id', 'client_id']);
            $table->dropColumn('client_id');
        });
    }
};
