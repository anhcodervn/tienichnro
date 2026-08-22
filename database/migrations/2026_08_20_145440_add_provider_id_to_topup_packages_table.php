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
        Schema::table('topup_packages', function (Blueprint $table): void {
            $table->foreignId('provider_id')
                ->nullable()
                ->after('game_server_id')
                ->constrained('topup_providers')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topup_packages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('provider_id');
        });
    }
};
