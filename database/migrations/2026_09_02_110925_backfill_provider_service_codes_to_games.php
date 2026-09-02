<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('games')
            ->select('id')
            ->orderBy('id')
            ->eachById(function (object $game): void {
                $serviceCodes = DB::table('topup_packages')
                    ->where('game_id', $game->id)
                    ->whereNotNull('provider_service_code')
                    ->where('provider_service_code', '!=', '')
                    ->distinct()
                    ->pluck('provider_service_code')
                    ->map(fn (mixed $serviceCode): string => trim((string) $serviceCode))
                    ->filter()
                    ->unique()
                    ->values();

                if ($serviceCodes->count() === 1) {
                    DB::table('games')
                        ->where('id', $game->id)
                        ->update(['provider_service_code' => $serviceCodes->first()]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
