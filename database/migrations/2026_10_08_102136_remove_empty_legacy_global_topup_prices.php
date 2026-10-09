<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_global_prices') && DB::table('user_global_prices')->exists()) {
            throw new RuntimeException('Cannot remove user_global_prices: archive its existing data before retiring Global Topup prices.');
        }

        Schema::dropIfExists('user_global_prices');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('user_global_prices')) {
            return;
        }

        Schema::create('user_global_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('discount_basis_points')->default(0);
            $table->unsignedBigInteger('minimum_profit')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
