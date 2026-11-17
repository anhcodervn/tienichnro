<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $timezone = config('app.timezone');
        DB::table('wallet_recharge_orders')->whereNotNull('expires_at')->whereColumn('expires_at', '<', 'created_at')
            ->select(['id', 'expires_at', 'created_at'])->orderBy('id')->chunkById(100, function ($orders) use ($timezone): void {
                foreach ($orders as $order) {
                    $corrected = CarbonImmutable::parse($order->expires_at, 'UTC')->setTimezone($timezone);
                    if ($corrected->lessThanOrEqualTo(CarbonImmutable::parse($order->created_at, $timezone))) {
                        continue;
                    }
                    DB::table('wallet_recharge_orders')->where('id', $order->id)->where('expires_at', $order->expires_at)
                        ->update(['expires_at' => $corrected->format('Y-m-d H:i:s')]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Corrected deadlines must not revert to the incorrectly stored UTC wall time.
    }
};
