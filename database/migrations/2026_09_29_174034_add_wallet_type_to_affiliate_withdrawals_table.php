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
        Schema::table('affiliate_withdrawals', function (Blueprint $table) {
            $table->string('wallet_type', 30)->default('affiliate')->after('amount')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliate_withdrawals', function (Blueprint $table) {
            $table->dropIndex(['wallet_type']);
            $table->dropColumn('wallet_type');
        });
    }
};
