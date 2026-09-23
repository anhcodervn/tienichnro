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
        Schema::table('topup_providers', function (Blueprint $table): void {
            $table->json('payload_field_mapping')->nullable()->after('connection_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topup_providers', function (Blueprint $table): void {
            $table->dropColumn('payload_field_mapping');
        });
    }
};
