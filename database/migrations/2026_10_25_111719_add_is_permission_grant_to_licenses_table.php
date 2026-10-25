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
        Schema::table('licenses', function (Blueprint $table): void {
            $table->boolean('is_permission_grant')->default(false);
            $table->index(['user_id', 'is_permission_grant', 'product_id'], 'licenses_permission_grant_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table): void {
            $table->dropIndex('licenses_permission_grant_index');
            $table->dropColumn('is_permission_grant');
        });
    }
};
