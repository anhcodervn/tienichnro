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
        Schema::table('game_service_package_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('collaborator_price')->default(0)->after('price');
            $table->boolean('quantity_enabled')->default(false)->after('original_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_service_package_prices', function (Blueprint $table) {
            $table->dropColumn(['collaborator_price', 'quantity_enabled']);
        });
    }
};
