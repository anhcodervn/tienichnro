<?php

namespace App\Features\NroNotification\Services;

use App\Models\Notify;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class NotificationRetentionService
{
    /**
     * @return array{deleted: array<string, int>, total_deleted: int, cutoffs: array<string, string>, processed_at: string}
     */
    public function prune(): array
    {
        $now = CarbonImmutable::now();
        $cutoffs = [
            'BOSS' => $now->subHours(24),
            'SET_ACTIVATION' => $now->subDays(60),
            'MAINTENANCE' => $now->subDays(7),
            'CRYSTAL_UPGRADE' => $now->subMonthsNoOverflow(12),
            'ITEM_UPGRADE' => $now->subMonthsNoOverflow(12),
            'GOD_ITEM' => $now->subMonthNoOverflow(),
            'PERMANENT_ITEM' => $now->subMonthNoOverflow(),
            'OTHER' => $now->subMonthNoOverflow(),
        ];
        $deleted = [];
        foreach ($cutoffs as $code => $cutoff) {
            $query = Notify::query()->where('time_start', '<=', $cutoff->format('Y-m-d H:i:s.u'));
            if ($code === 'BOSS') {
                $query->where(fn (Builder $bosses): Builder => $bosses->where('is_boss', true)
                    ->orWhereNotNull('boss_id')->orWhereNotNull('boss_name')
                    ->orWhereHas('code', fn (Builder $types): Builder => $this->type($types, 'BOSS')));
            } else {
                $query->where('is_boss', false)->whereNull('boss_id')->whereNull('boss_name')
                    ->whereHas('code', fn (Builder $types): Builder => $this->type($types, $code));
            }
            $deleted[$code] = $query->delete();
        }

        return ['deleted' => $deleted, 'total_deleted' => array_sum($deleted),
            'cutoffs' => array_map(fn (CarbonImmutable $cutoff): string => $cutoff->toIso8601String(), $cutoffs),
            'processed_at' => $now->toIso8601String()];
    }

    private function type(Builder $query, string $code): Builder
    {
        return $query->where(fn (Builder $types): Builder => $types->where('system_key', $code)
            ->orWhere(fn (Builder $legacy): Builder => $legacy->whereNull('system_key')->where('code', $code)));
    }
}
