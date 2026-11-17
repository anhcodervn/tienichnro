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
        Schema::table('game_password_orders', function (Blueprint $table): void {
            $table->unsignedInteger('attempt_version')->default(1);
            $table->boolean('verify_before_change')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_password_orders', function (Blueprint $table): void {
            $table->dropColumn(['attempt_version', 'verify_before_change']);
        });
    }
};
