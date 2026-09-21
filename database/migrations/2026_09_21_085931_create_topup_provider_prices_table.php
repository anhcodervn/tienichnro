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
        Schema::create('topup_provider_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topup_provider_id')->constrained('topup_providers')->cascadeOnDelete();
            $table->foreignId('topup_package_id')->nullable()->constrained('topup_packages')->cascadeOnDelete();
            $table->foreignId('global_topup_package_id')->nullable()->constrained('global_topup_packages')->cascadeOnDelete();
            $table->unsignedBigInteger('price');
            $table->timestamps();

            $table->unique(['topup_provider_id', 'topup_package_id'], 'provider_topup_price_unique');
            $table->unique(['topup_provider_id', 'global_topup_package_id'], 'provider_global_price_unique');
        });

        $now = now();
        $rows = DB::table('topup_packages')
            ->whereNotNull('provider_id')
            ->where('provider_price', '>', 0)
            ->get(['id', 'provider_id', 'provider_price'])
            ->map(fn (object $package): array => [
                'topup_provider_id' => $package->provider_id,
                'topup_package_id' => $package->id,
                'global_topup_package_id' => null,
                'price' => $package->provider_price,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        $globalRows = DB::table('global_topup_packages')
            ->whereNotNull('provider_id')
            ->where('provider_price', '>', 0)
            ->get(['id', 'provider_id', 'provider_price'])
            ->map(fn (object $package): array => [
                'topup_provider_id' => $package->provider_id,
                'topup_package_id' => null,
                'global_topup_package_id' => $package->id,
                'price' => $package->provider_price,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('topup_provider_prices')->insert($rows->concat($globalRows)->all());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topup_provider_prices');
    }
};
