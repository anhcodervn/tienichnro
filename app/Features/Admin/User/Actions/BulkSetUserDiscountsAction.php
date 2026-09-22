<?php

namespace App\Features\Admin\User\Actions;

use App\Models\GlobalTopupPackage;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\UserGlobalPackagePrice;
use App\Models\UserPackagePrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkSetUserDiscountsAction
{
    /**
     * @param  array{user_ids:array<int, int>,scope:string,discount_percent:float|int}  $payload
     * @return array{users_updated:int,package_rules_upserted:int,global_rules_upserted:int}
     */
    public function handle(array $payload): array
    {
        $userIds = User::query()
            ->whereKey($payload['user_ids'])
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if (count($userIds) !== count($payload['user_ids'])) {
            throw ValidationException::withMessages([
                'user_ids' => 'Danh sách chứa người dùng không tồn tại hoặc không thuộc website hiện tại.',
            ]);
        }

        $packageIds = in_array($payload['scope'], ['packages', 'all'], true)
            ? TopupPackage::query()
                ->whereNull('global_topup_package_id')
                ->active()
                ->whereHas('game', fn (Builder $query) => $query->where('package_mode', 'custom'))
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all()
            : [];
        $globalPackageIds = in_array($payload['scope'], ['global', 'all'], true)
            ? GlobalTopupPackage::query()
                ->where('status', 'active')
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all()
            : [];
        $basisPoints = (int) round((float) $payload['discount_percent'] * 100);
        $now = now();
        $packageRows = collect($userIds)->crossJoin($packageIds)->map(fn (array $ids): array => [
            'user_id' => $ids[0],
            'topup_package_id' => $ids[1],
            'pricing_mode' => UserPackagePrice::MODE_DISCOUNT,
            'discount_basis_points' => $basisPoints,
            'fixed_price' => null,
            'minimum_profit' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $globalRows = collect($userIds)->crossJoin($globalPackageIds)->map(fn (array $ids): array => [
            'user_id' => $ids[0],
            'global_topup_package_id' => $ids[1],
            'pricing_mode' => UserGlobalPackagePrice::MODE_DISCOUNT,
            'discount_basis_points' => $basisPoints,
            'fixed_price' => null,
            'minimum_profit' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::transaction(function () use ($packageRows, $globalRows): void {
            $packageRows->chunk(500)->each(fn ($rows) => UserPackagePrice::query()->upsert(
                $rows->all(),
                ['user_id', 'topup_package_id'],
                ['pricing_mode', 'discount_basis_points', 'fixed_price', 'minimum_profit', 'is_active', 'updated_at'],
            ));
            $globalRows->chunk(500)->each(fn ($rows) => UserGlobalPackagePrice::query()->upsert(
                $rows->all(),
                ['user_id', 'global_topup_package_id'],
                ['pricing_mode', 'discount_basis_points', 'fixed_price', 'minimum_profit', 'is_active', 'updated_at'],
            ));
        }, 3);

        return [
            'users_updated' => count($userIds),
            'package_rules_upserted' => $packageRows->count(),
            'global_rules_upserted' => $globalRows->count(),
        ];
    }
}
