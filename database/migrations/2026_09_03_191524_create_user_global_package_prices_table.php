<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_global_package_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('global_topup_package_id')->constrained()->cascadeOnDelete();
            $table->string('pricing_mode', 20)->default('discount');
            $table->unsignedInteger('discount_basis_points')->nullable();
            $table->unsignedBigInteger('fixed_price')->nullable();
            $table->unsignedBigInteger('minimum_profit')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'global_topup_package_id'], 'user_global_package_prices_unique');
            $table->index(['user_id', 'is_active'], 'user_global_package_prices_active_index');
        });

        $this->copyLegacyGlobalPrices();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_global_package_prices');
    }

    private function copyLegacyGlobalPrices(): void
    {
        if (! Schema::hasTable('user_global_prices')) {
            return;
        }

        $globalPackageIds = DB::table('global_topup_packages')->pluck('id');

        if ($globalPackageIds->isEmpty()) {
            return;
        }

        DB::table('user_global_prices')
            ->orderBy('id')
            ->chunkById(100, function ($legacyPrices) use ($globalPackageIds): void {
                $rows = collect($legacyPrices)->flatMap(fn (object $legacyPrice) => $globalPackageIds->map(fn (mixed $globalPackageId): array => [
                    'user_id' => $legacyPrice->user_id,
                    'global_topup_package_id' => $globalPackageId,
                    'pricing_mode' => 'discount',
                    'discount_basis_points' => $legacyPrice->discount_basis_points,
                    'fixed_price' => null,
                    'minimum_profit' => $legacyPrice->minimum_profit,
                    'is_active' => $legacyPrice->is_active,
                    'created_at' => $legacyPrice->created_at,
                    'updated_at' => $legacyPrice->updated_at,
                ]));

                DB::table('user_global_package_prices')->insertOrIgnore($rows->all());
            });
    }
};
