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
        Schema::create('game_service_package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_service_package_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('code', 100);
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('original_price')->nullable();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedInteger('max_quantity')->default(1);
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['game_service_package_id', 'code']);
            $table->index(['game_service_package_id', 'status', 'sort_order'], 'game_service_price_status_sort_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_service_package_prices');
    }
};
