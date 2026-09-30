<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('game_service_orders')
            ->select(['id', 'payload'])
            ->chunkById(100, function (Collection $orders): void {
                foreach ($orders as $order) {
                    $payload = (string) $order->payload;
                    json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

                    DB::table('game_service_orders')
                        ->where('id', $order->id)
                        ->update(['payload' => Crypt::encryptString($payload)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('game_service_orders')
            ->select(['id', 'payload'])
            ->chunkById(100, function (Collection $orders): void {
                foreach ($orders as $order) {
                    $payload = Crypt::decryptString((string) $order->payload);
                    json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

                    DB::table('game_service_orders')
                        ->where('id', $order->id)
                        ->update(['payload' => $payload]);
                }
            });
    }
};
