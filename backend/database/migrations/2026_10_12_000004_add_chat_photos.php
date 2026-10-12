<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('round_comments', function (Blueprint $table) {
            $table->text('image_data')->nullable();
            $table->string('image_mime',32)->nullable();
        });
    }
    public function down(): void {
        Schema::table('round_comments', fn (Blueprint $table) => $table->dropColumn(['image_data','image_mime']));
    }
};
