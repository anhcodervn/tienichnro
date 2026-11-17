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
        Schema::table('wallet_recharge_orders', function (Blueprint $table) {
            $table->string('bank_code', 40)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_recharge_orders', function (Blueprint $table) {
            $table->dropColumn('bank_code');
        });
    }
};
