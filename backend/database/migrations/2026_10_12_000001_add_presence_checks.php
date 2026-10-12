<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('rounds', function (Blueprint $table) {
            $table->timestamp('presence_at', 6)->nullable();
        });
        Schema::table('answers', function (Blueprint $table) {
            $table->string('presence_code', 2)->nullable();
            $table->boolean('presence_confirmed')->default(false);
        });
    }
    public function down(): void {
        Schema::table('answers', fn (Blueprint $table) => $table->dropColumn(['presence_code', 'presence_confirmed']));
        Schema::table('rounds', fn (Blueprint $table) => $table->dropColumn('presence_at'));
    }
};
