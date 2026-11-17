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
        Schema::table('users', function (Blueprint $table) {
            $table->text('game_password_captcha_key')->nullable();
            $table->text('game_password_captcha_secret')->nullable();
            $table->json('game_password_captcha_profile')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['game_password_captcha_key', 'game_password_captcha_secret', 'game_password_captcha_profile']);
        });
    }
};
