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
        Schema::table('global_topup_packages', function (Blueprint $table): void {
            $table->foreignId('provider_id')
                ->nullable()
                ->after('id')
                ->constrained('topup_providers')
                ->restrictOnDelete();
            $table->json('provider_service_codes')->nullable()->after('provider_id');
            $table->unsignedBigInteger('carot_amount')->nullable()->after('denomination');
            $table->unsignedBigInteger('reward_x2_amount')->nullable()->after('carot_amount');
            $table->unsignedBigInteger('reward_x3_amount')->nullable()->after('reward_x2_amount');
            $table->unsignedBigInteger('first_topup_reward_amount')->nullable()->after('reward_x3_amount');
            $table->unsignedBigInteger('provider_price')->default(0)->after('first_topup_reward_amount');
            $table->string('bonus_text')->nullable()->after('description');
            $table->unsignedInteger('min_quantity')->default(1)->after('bonus_text');
            $table->unsignedInteger('max_quantity')->nullable()->after('min_quantity');
            $table->json('metadata')->nullable()->after('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_topup_packages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('provider_id');
            $table->dropColumn([
                'provider_service_codes',
                'carot_amount',
                'reward_x2_amount',
                'reward_x3_amount',
                'first_topup_reward_amount',
                'provider_price',
                'bonus_text',
                'min_quantity',
                'max_quantity',
                'metadata',
            ]);
        });
    }
};
