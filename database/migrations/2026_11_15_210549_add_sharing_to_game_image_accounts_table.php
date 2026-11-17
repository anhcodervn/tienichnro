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
            $table->string('share_token', 64)->nullable();
            $table->json('shared_layout')->nullable();
            $table->timestamp('share_expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_image_accounts', function (Blueprint $table) {
            $table->dropColumn(['share_token', 'shared_layout', 'share_expires_at']);
        });
    }
};
