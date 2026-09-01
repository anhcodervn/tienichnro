<?php

namespace App\Features\Admin\MemberLevel\Services;

use App\Features\MemberLevel\Services\MemberLevelService;
use App\Models\AdminAuditLog;
use App\Models\Game;
use App\Models\MemberLevel;
use App\Models\MemberLevelPackagePrice;
use App\Models\TopupPackage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class MemberLevelAdminService
{
    public function __construct(private readonly MemberLevelService $memberLevelService) {}

    /** @return array<string, mixed> */
    public function catalog(): array
    {
        return [
            'levels' => MemberLevel::query()
                ->with(['packagePrices' => fn ($query) => $query->orderBy('topup_package_id')])
                ->orderBy('rank')
                ->get(),
            'games' => Game::query()
                ->select(['id', 'name'])
                ->where('package_mode', 'custom')
                ->with(['packages' => fn ($query) => $query
                    ->select(['id', 'game_id', 'name', 'price', 'provider_price', 'status'])
                    ->orderBy('sort_order')
                    ->orderBy('id')])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload, User $admin, Request $request): MemberLevel
    {
        $this->assertThresholdOrder($payload);
        $level = MemberLevel::query()->create($payload);
        $this->audit($admin, 'member_level_created', $level, [], $level->getAttributes(), $request);

        return $level;
    }

    /** @param array<string, mixed> $payload */
    public function update(MemberLevel $level, array $payload, User $admin, Request $request): MemberLevel
    {
        $this->assertThresholdOrder($payload, $level);
        $old = $level->getAttributes();
        $level->update($payload);
        $this->audit($admin, 'member_level_updated', $level, $old, $level->getAttributes(), $request);

        return $level->refresh();
    }

    public function disable(MemberLevel $level, User $admin, Request $request): MemberLevel
    {
        return $this->update($level, [...$level->only($level->getFillable()), 'status' => 'inactive'], $admin, $request);
    }

    /** @param array<string, mixed> $payload */
    public function upsertPackagePrice(MemberLevel $level, TopupPackage $package, array $payload, User $admin, Request $request): MemberLevelPackagePrice
    {
        $payload['discount_basis_points'] = $payload['pricing_mode'] === 'discount' ? $payload['discount_basis_points'] : null;
        $payload['fixed_price'] = $payload['pricing_mode'] === 'fixed' ? $payload['fixed_price'] : null;
        $override = MemberLevelPackagePrice::query()->firstOrNew([
            'member_level_id' => $level->id,
            'topup_package_id' => $package->id,
        ]);
        $old = $override->exists ? $override->getAttributes() : [];
        $override->fill($payload)->save();
        $this->audit($admin, 'member_level_package_price_saved', $override, $old, $override->getAttributes(), $request);

        return $override->refresh();
    }

    public function destroyPackagePrice(MemberLevel $level, TopupPackage $package, User $admin, Request $request): void
    {
        $override = MemberLevelPackagePrice::query()
            ->whereBelongsTo($level)
            ->whereBelongsTo($package, 'topupPackage')
            ->first();

        if (! $override instanceof MemberLevelPackagePrice) {
            return;
        }
        $old = $override->getAttributes();
        $this->audit($admin, 'member_level_package_price_deleted', $override, $old, [], $request);
        $override->delete();
    }

    /** @param array<string, mixed> $payload */
    public function assignUser(User $user, array $payload, User $admin): array
    {
        $level = filled($payload['member_level_id'] ?? null)
            ? MemberLevel::query()->findOrFail((int) $payload['member_level_id'])
            : null;
        $expiresAt = filled($payload['expires_at'] ?? null) ? Carbon::parse($payload['expires_at']) : null;
        $this->memberLevelService->assignManualLevel($user, $level, $expiresAt, $admin, $payload['reason'] ?? null);

        return $this->memberLevelService->status($user, false) ?? [];
    }

    /** @param array<string, mixed> $payload */
    private function assertThresholdOrder(array $payload, ?MemberLevel $except = null): void
    {
        $lowerThreshold = MemberLevel::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->where('rank', '<', $payload['rank'])
            ->max('lifetime_threshold');
        $upperThreshold = MemberLevel::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->where('rank', '>', $payload['rank'])
            ->min('lifetime_threshold');

        if (($lowerThreshold !== null && $payload['lifetime_threshold'] <= (int) $lowerThreshold)
            || ($upperThreshold !== null && $payload['lifetime_threshold'] >= (int) $upperThreshold)) {
            throw ValidationException::withMessages([
                'lifetime_threshold' => 'Mốc mở khóa phải tăng dần theo thứ tự level.',
            ]);
        }
    }

    /** @param array<string, mixed> $old @param array<string, mixed> $new */
    private function audit(User $admin, string $action, $subject, array $old, array $new, Request $request): void
    {
        AdminAuditLog::query()->create([
            'admin_id' => $admin->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
