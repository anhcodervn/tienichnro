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
        Schema::table('games', function (Blueprint $table): void {
            $table->unsignedInteger('min_quantity')->default(1)->after('package_mode');
            $table->unsignedInteger('max_quantity')->default(10)->after('min_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn(['min_quantity', 'max_quantity']);
        });
    }
};
