<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('presence_checks')->default(false);
            $table->unsignedInteger('presence_next_round')->nullable();
        });
    }
    public function down(): void {
        Schema::table('games', fn (Blueprint $table) => $table->dropColumn(['presence_checks','presence_next_round']));
    }
};
