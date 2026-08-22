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
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('purchase_mode', 20)->default('single')->after('topup_package_id');
            $table->json('checkout_fields_snapshot')->nullable()->after('purchase_mode');
            $table->string('game_account')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('game_account')->nullable(false)->change();
            $table->dropColumn(['purchase_mode', 'checkout_fields_snapshot']);
        });
    }
};
