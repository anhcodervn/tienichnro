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
        DB::table('orders')
            ->select(['id', 'quantity'])
            ->where('purchase_mode', 'single')
            ->orderBy('id')
            ->chunkById(500, function ($orders): void {
                foreach ($orders as $order) {
                    DB::table('order_recipients')
                        ->where('order_id', $order->id)
                        ->update(['quantity' => $order->quantity]);
                }
            });
    }

    /**
     * The following schema rollback removes this derived column.
     */
    public function down(): void {}
};
