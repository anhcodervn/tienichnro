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
            $table->foreignId('affiliate_referrer_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('affiliate_attribution_source', 30)->nullable()->after('affiliate_referrer_id');
            $table->string('affiliate_referral_code', 32)->nullable()->after('affiliate_attribution_source');
            $table->timestamp('affiliate_attributed_at')->nullable()->after('affiliate_referral_code');

            $table->index(
                ['tenant_id', 'affiliate_referrer_id', 'created_at'],
                'orders_tenant_affiliate_referrer_created_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_tenant_affiliate_referrer_created_index');
            $table->dropConstrainedForeignId('affiliate_referrer_id');
            $table->dropColumn([
                'affiliate_attribution_source',
                'affiliate_referral_code',
                'affiliate_attributed_at',
            ]);
        });
    }
};
