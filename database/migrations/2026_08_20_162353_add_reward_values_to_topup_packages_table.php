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
        Schema::table('topup_packages', function (Blueprint $table) {
            $table->unsignedBigInteger('reward_x2_amount')->nullable()->after('carot_amount');
            $table->unsignedBigInteger('reward_x3_amount')->nullable()->after('reward_x2_amount');
            $table->unsignedBigInteger('first_topup_reward_amount')->nullable()->after('reward_x3_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topup_packages', function (Blueprint $table) {
            $table->dropColumn([
                'reward_x2_amount',
                'reward_x3_amount',
                'first_topup_reward_amount',
            ]);
        });
    }
};
