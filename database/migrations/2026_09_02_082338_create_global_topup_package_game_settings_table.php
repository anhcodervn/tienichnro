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
        Schema::create('global_topup_package_game_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_topup_package_id')
                ->constrained(indexName: 'global_package_game_package_fk')
                ->cascadeOnDelete();
            $table->foreignId('game_id')
                ->constrained(indexName: 'global_package_game_game_fk')
                ->cascadeOnDelete();
            $table->string('provider_service_code', 100)->nullable();
            $table->json('receives');
            $table->timestamps();

            $table->unique(['global_topup_package_id', 'game_id'], 'global_package_game_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_topup_package_game_settings');
    }
};
