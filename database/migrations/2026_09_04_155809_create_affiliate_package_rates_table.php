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
        Schema::create('affiliate_package_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topup_package_id')->constrained()->cascadeOnDelete();
            $table->string('commission_type', 20)->default('fixed');
            $table->unsignedBigInteger('fixed_amount')->nullable();
            $table->unsignedInteger('percentage_basis_points')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'topup_package_id'], 'affiliate_rates_tenant_package_unique');
            $table->index(['tenant_id', 'is_active', 'topup_package_id'], 'affiliate_rates_tenant_active_package_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_package_rates');
    }
};
