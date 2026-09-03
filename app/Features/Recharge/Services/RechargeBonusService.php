<?php

namespace App\Features\Recharge\Services;

use App\Models\RechargeBonusTier;
use Illuminate\Database\Eloquent\Collection;

class RechargeBonusService
{
    /** @return Collection<int, RechargeBonusTier> */
    public function all(): Collection
    {
        return RechargeBonusTier::query()
            ->orderBy('minimum_amount')
            ->get();
    }

    /** @return Collection<int, RechargeBonusTier> */
    public function active(): Collection
    {
        return RechargeBonusTier::query()
            ->active()
            ->orderBy('minimum_amount')
            ->get();
    }

    public function resolve(int $amount): ?RechargeBonusTier
    {
        return RechargeBonusTier::query()
            ->active()
            ->where('minimum_amount', '<=', $amount)
            ->orderByDesc('minimum_amount')
            ->first();
    }

    /** @return array{tier_id:?int,minimum_amount:?int,bonus_basis_points:int,bonus_percent:float,bonus_amount:int,credited_amount:int} */
    public function calculate(int $amount): array
    {
        $tier = $this->resolve($amount);
        $bonusBasisPoints = $tier?->bonus_basis_points ?? 0;
        $bonusAmount = intdiv($amount * $bonusBasisPoints, 10_000);

        return [
            'tier_id' => $tier?->id,
            'minimum_amount' => $tier?->minimum_amount,
            'bonus_basis_points' => $bonusBasisPoints,
            'bonus_percent' => (float) ($bonusBasisPoints / 100),
            'bonus_amount' => $bonusAmount,
            'credited_amount' => $amount + $bonusAmount,
        ];
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload): RechargeBonusTier
    {
        return RechargeBonusTier::query()->create($this->attributes($payload));
    }

    /** @param array<string, mixed> $payload */
    public function update(RechargeBonusTier $tier, array $payload): RechargeBonusTier
    {
        $tier->update($this->attributes($payload));

        return $tier->refresh();
    }

    public function delete(RechargeBonusTier $tier): void
    {
        $tier->delete();
    }

    /** @return array<string, int|bool> */
    public function serialize(RechargeBonusTier $tier): array
    {
        return [
            'id' => $tier->id,
            'minimum_amount' => $tier->minimum_amount,
            'bonus_basis_points' => $tier->bonus_basis_points,
            'bonus_percent' => (float) ($tier->bonus_basis_points / 100),
            'is_active' => $tier->is_active,
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, int|bool> */
    private function attributes(array $payload): array
    {
        return [
            'minimum_amount' => (int) $payload['minimum_amount'],
            'bonus_basis_points' => (int) round((float) $payload['bonus_percent'] * 100),
            'is_active' => (bool) $payload['is_active'],
        ];
    }
}
