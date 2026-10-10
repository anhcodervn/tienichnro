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
        Schema::table('license_devices', function (Blueprint $table): void {
            $table->string('client_version', 32)->default('1.0.0');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('license_devices', function (Blueprint $table): void {
            $table->dropColumn('client_version');
        });
    }
};
