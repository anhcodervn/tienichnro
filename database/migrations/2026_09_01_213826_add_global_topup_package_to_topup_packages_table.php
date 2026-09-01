<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topup_packages', function (Blueprint $table): void {
            $table->foreignId('global_topup_package_id')
                ->nullable()
                ->after('game_server_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('topup_packages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('global_topup_package_id');
        });
    }
};
