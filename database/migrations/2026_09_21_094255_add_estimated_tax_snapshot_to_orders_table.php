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
            $table->boolean('tax_enabled')->nullable()->after('gross_profit');
            $table->string('tax_calculation_type', 20)->nullable()->after('tax_enabled');
            $table->decimal('vat_rate', 7, 4)->nullable()->after('tax_calculation_type');
            $table->decimal('pit_rate', 7, 4)->nullable()->after('vat_rate');
            $table->decimal('estimated_vat', 20, 2)->nullable()->after('pit_rate');
            $table->decimal('estimated_pit', 20, 2)->nullable()->after('estimated_vat');
            $table->decimal('estimated_tax', 20, 2)->nullable()->after('estimated_pit');
            $table->decimal('payment_fee', 20, 2)->nullable()->after('estimated_tax');
            $table->decimal('other_cost', 20, 2)->nullable()->after('payment_fee');
            $table->decimal('net_profit', 20, 2)->nullable()->after('other_cost');
            $table->decimal('profit_margin', 9, 4)->nullable()->after('net_profit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'tax_enabled',
                'tax_calculation_type',
                'vat_rate',
                'pit_rate',
                'estimated_vat',
                'estimated_pit',
                'estimated_tax',
                'payment_fee',
                'other_cost',
                'net_profit',
                'profit_margin',
            ]);
        });
    }
};
