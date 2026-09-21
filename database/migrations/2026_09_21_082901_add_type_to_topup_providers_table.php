<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('topup_providers', function (Blueprint $table): void {
            $table->string('type', 50)->default('merchant_partner_card')->after('slug')->index();
        });

        DB::table('topup_providers')->where('slug', 'accnrovn')->update(['type' => 'accnro']);
        DB::table('topup_providers')->where('slug', 'manual')->update(['type' => 'manual']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topup_providers', function (Blueprint $table): void {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
