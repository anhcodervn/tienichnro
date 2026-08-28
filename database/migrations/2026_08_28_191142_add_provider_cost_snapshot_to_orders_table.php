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
            $table->decimal('sale_unit_price', 20, 2)->nullable()->after('unit_price');
            $table->decimal('provider_unit_cost', 20, 2)->nullable()->after('total_amount');
            $table->decimal('provider_total_cost', 20, 2)->nullable()->after('provider_unit_cost');
            $table->decimal('gross_profit', 20, 2)->nullable()->after('provider_total_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'sale_unit_price',
                'provider_unit_cost',
                'provider_total_cost',
                'gross_profit',
            ]);
        });
    }
};
