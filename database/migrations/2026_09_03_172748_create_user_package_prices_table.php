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
        Schema::create('user_package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topup_package_id')->constrained()->cascadeOnDelete();
            $table->string('pricing_mode', 20)->default('discount');
            $table->unsignedInteger('discount_basis_points')->nullable();
            $table->unsignedBigInteger('fixed_price')->nullable();
            $table->unsignedBigInteger('minimum_profit')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'topup_package_id']);
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_package_prices');
    }
};
