<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('orders')
            ->where('order_status', 'completed')
            ->whereNull('topup_id')
            ->select('id')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $order): void {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->whereNull('topup_id')
                    ->update(['topup_id' => (string) Str::ulid()]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')->whereNotNull('topup_id')->update(['topup_id' => null]);
    }
};
