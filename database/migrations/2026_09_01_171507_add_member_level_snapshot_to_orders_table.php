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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('member_level_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('member_level_name')->nullable()->after('member_level_id');
            $table->string('member_level_pricing_mode', 20)->nullable()->after('member_level_name');
            $table->unsignedSmallInteger('member_level_discount_bps')->default(0)->after('member_level_pricing_mode');
            $table->decimal('retail_unit_price', 20, 2)->nullable()->after('sale_unit_price');
            $table->decimal('member_level_discount_amount', 20, 2)->default(0)->after('discount_amount');
            $table->index(['user_id', 'payment_status', 'order_status', 'id'], 'orders_member_level_sync_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_member_level_sync_index');
            $table->dropConstrainedForeignId('member_level_id');
            $table->dropColumn([
                'member_level_name',
                'member_level_pricing_mode',
                'member_level_discount_bps',
                'retail_unit_price',
                'member_level_discount_amount',
            ]);
        });
    }
};
