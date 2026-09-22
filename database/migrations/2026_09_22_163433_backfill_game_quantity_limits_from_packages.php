<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumns('games', ['min_quantity', 'max_quantity'])) {
            return;
        }

        DB::table('topup_packages')
            ->select('game_id')
            ->selectRaw('MAX(min_quantity) as effective_min_quantity')
            ->selectRaw('MIN(COALESCE(max_quantity, 10)) as effective_max_quantity')
            ->where('status', 'active')
            ->groupBy('game_id')
            ->orderBy('game_id')
            ->each(function (object $limits): void {
                $minimum = (int) $limits->effective_min_quantity;
                $maximum = (int) $limits->effective_max_quantity;

                if ($minimum < 1 || $maximum > 10 || $minimum > $maximum) {
                    throw new RuntimeException("Không thể chuyển giới hạn số lượng của game {$limits->game_id}: khoảng {$minimum}–{$maximum} không hợp lệ.");
                }

                DB::table('games')
                    ->where('id', $limits->game_id)
                    ->update([
                        'min_quantity' => $minimum,
                        'max_quantity' => $maximum,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Legacy package columns remain intact for rollback compatibility.
    }
};
