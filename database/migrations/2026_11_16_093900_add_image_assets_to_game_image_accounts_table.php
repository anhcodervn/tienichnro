<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_image_accounts', function (Blueprint $table) {
            $table->string('preview_path')->nullable();
            $table->string('capture_directory')->nullable();
            $table->json('capture_files')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_image_accounts', function (Blueprint $table) {
            $table->dropColumn(['preview_path', 'capture_directory', 'capture_files']);
        });
    }
};
