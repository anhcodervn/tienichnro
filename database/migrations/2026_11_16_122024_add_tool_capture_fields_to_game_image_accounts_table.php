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
            $table->string('capture_disk')->default('local');
            $table->string('capture_claim_hash', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_image_accounts', function (Blueprint $table) {
            $table->dropColumn(['capture_disk', 'capture_claim_hash']);
        });
    }
};
