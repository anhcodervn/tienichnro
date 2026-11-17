<?php

namespace App\Features\Client\GamePassword\Services;

use App\Models\GamePasswordOrder;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GamePasswordHistoryCleanupService
{
    /** @return array{deleted: int, has_more: bool} */
    public function clear(int $olderThanDays): array
    {
        $cutoff = now()->subDays($olderThanDays);
        $candidates = $this->eligible($cutoff)->orderBy('id')->limit(1000)->get(['id', 'user_id']);
        $deleted = $this->deleteCandidates($candidates, $cutoff);

        return ['deleted' => $deleted, 'has_more' => $this->eligible($cutoff)->exists()];
    }

    /** @param array<int, int> $ids
     * @return array{deleted: int, skipped: int}
     */
    public function clearSelected(array $ids): array
    {
        $candidates = $this->eligible()->whereIn('id', $ids)->orderBy('id')->limit(1000)->get(['id', 'user_id']);
        $deleted = $this->deleteCandidates($candidates);

        return ['deleted' => $deleted, 'skipped' => count($ids) - $deleted];
    }

    private function deleteCandidates(Collection $candidates, ?CarbonInterface $cutoff = null): int
    {
        $deleted = 0;
        foreach ($candidates->groupBy('user_id') as $userId => $group) {
            $deleted += DB::transaction(function () use ($userId, $group, $cutoff): int {
                if (! User::query()->lockForUpdate()->find($userId)) {
                    return 0;
                }
                $orders = $this->eligible($cutoff)->whereIn('id', $group->pluck('id'))->lockForUpdate()->get(['id', 'user_id', 'request_id']);
                if ($orders->isEmpty()) {
                    return 0;
                }
                DB::table('game_password_pruned_requests')->insertOrIgnore($orders->unique('request_id')->map(fn (GamePasswordOrder $order): array => [
                    'user_id' => $order->user_id, 'request_id' => $order->request_id,
                ])->all());

                return GamePasswordOrder::query()->whereIn('id', $orders->pluck('id'))->delete();
            }, 3);
        }

        return $deleted;
    }

    private function eligible(?CarbonInterface $cutoff = null): Builder
    {
        return GamePasswordOrder::query()->whereIn('status', ['success', 'error'])->whereNull('active_key')
            ->whereNotNull('completed_at')->when($cutoff !== null, fn (Builder $query) => $query->where('completed_at', '<', $cutoff));
    }
}
