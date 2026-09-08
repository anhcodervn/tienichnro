<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->whereNotNull('topup_id')->update(['topup_id' => null]);

        DB::table('orders')
            ->where('order_status', 'completed')
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $orders): void {
                $orderIds = $orders->pluck('id');
                $recipientsByOrder = DB::table('order_recipients')
                    ->whereIn('order_id', $orderIds)
                    ->select(['order_id', 'position', 'quantity', 'provider_response'])
                    ->orderBy('position')
                    ->get()
                    ->groupBy('order_id');

                foreach ($orders as $order) {
                    $recipients = $recipientsByOrder->get($order->id, collect());

                    if ($recipients->count() !== 1 || (int) $recipients->first()->quantity !== 1) {
                        continue;
                    }

                    $topupId = $this->providerTopupId($recipients->first()->provider_response);
                    if ($topupId === null) {
                        continue;
                    }

                    DB::table('orders')
                        ->where('id', $order->id)
                        ->whereNull('topup_id')
                        ->update(['topup_id' => $topupId]);
                }
            });
    }

    /**
     * Provider identifiers cannot be converted back to generated ULIDs.
     */
    public function down(): void {}

    private function providerTopupId(mixed $providerResponse): ?string
    {
        if (is_string($providerResponse)) {
            $providerResponse = json_decode($providerResponse, true);
        }

        if (! is_array($providerResponse)) {
            return null;
        }

        $value = data_get($providerResponse, 'items.1.response.provider_topup_id')
            ?? data_get($providerResponse, 'items.1.last_status_check.response.body.data.topup_id')
            ?? data_get($providerResponse, 'items.1.submission.response.body.data.topup_id');

        if (! is_scalar($value)) {
            return null;
        }

        $topupId = trim((string) $value);

        return $topupId !== '' ? mb_substr($topupId, 0, 100) : null;
    }
};
