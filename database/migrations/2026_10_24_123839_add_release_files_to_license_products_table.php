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
        Schema::table('license_products', function (Blueprint $table) {
            $table->string('latest_version', 32)->nullable();
            foreach (['tool', 'update'] as $kind) {
                $table->string($kind.'_zip_path')->nullable();
                $table->string($kind.'_zip_name')->nullable();
                $table->unsignedBigInteger($kind.'_zip_size')->nullable();
                $table->string($kind.'_zip_sha256', 64)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('license_products', function (Blueprint $table) {
            $table->dropColumn(['latest_version', 'tool_zip_path', 'tool_zip_name', 'tool_zip_size', 'tool_zip_sha256', 'update_zip_path', 'update_zip_name', 'update_zip_size', 'update_zip_sha256']);
        });
    }
};
