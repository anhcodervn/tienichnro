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
        Schema::table('game_service_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('collaborator_unit_cost')->nullable()->after('total_amount');
            $table->unsignedBigInteger('collaborator_total_cost')->nullable()->after('collaborator_unit_cost');
            $table->bigInteger('gross_profit')->nullable()->after('collaborator_total_cost');
            $table->boolean('tax_enabled')->nullable()->after('gross_profit');
            $table->string('tax_calculation_type', 20)->nullable()->after('tax_enabled');
            $table->decimal('vat_rate', 7, 4)->nullable()->after('tax_calculation_type');
            $table->decimal('pit_rate', 7, 4)->nullable()->after('vat_rate');
            $table->unsignedBigInteger('estimated_vat')->nullable()->after('pit_rate');
            $table->unsignedBigInteger('estimated_pit')->nullable()->after('estimated_vat');
            $table->unsignedBigInteger('estimated_tax')->nullable()->after('estimated_pit');
            $table->bigInteger('net_profit')->nullable()->after('estimated_tax');
            $table->decimal('profit_margin', 9, 4)->nullable()->after('net_profit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_service_orders', function (Blueprint $table) {
            $table->dropColumn([
                'collaborator_unit_cost',
                'collaborator_total_cost',
                'gross_profit',
                'tax_enabled',
                'tax_calculation_type',
                'vat_rate',
                'pit_rate',
                'estimated_vat',
                'estimated_pit',
                'estimated_tax',
                'net_profit',
                'profit_margin',
            ]);
        });
    }
};
