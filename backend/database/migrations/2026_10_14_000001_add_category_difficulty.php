<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedTinyInteger('difficulty')->default(1);
        });
        foreach (config('category_difficulty', []) as $level => $codes) {
            DB::table('categories')->whereIn('code', $codes)->update(['difficulty' => (int) $level]);
        }
    }

    public function down(): void {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('difficulty'));
    }
};
