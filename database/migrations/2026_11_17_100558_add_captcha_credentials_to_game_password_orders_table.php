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
        Schema::table('game_password_orders', function (Blueprint $table) {
            $table->text('captcha_api_key')->nullable();
            $table->text('captcha_api_secret')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_password_orders', function (Blueprint $table) {
            $table->dropColumn(['captcha_api_key', 'captcha_api_secret']);
        });
    }
};
