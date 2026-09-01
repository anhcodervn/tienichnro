<?php

namespace App\Features\Admin\Topup\Services;

use App\Models\AdminAuditLog;
use App\Models\GlobalTopupPackage;
use App\Models\MemberLevel;
use App\Models\MemberLevelGlobalPackagePrice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GlobalTopupPackageAdminService
{
    /** @return array<string, mixed> */
    public function catalog(): array
    {
        return [
            'global_packages' => GlobalTopupPackage::query()
                ->withCount('packages')
                ->withMin('packages', 'provider_price')
                ->withMax('packages', 'provider_price')
                ->with(['levelPrices' => fn ($query) => $query->orderBy('member_level_id')])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'levels' => MemberLevel::query()
                ->orderBy('rank')
                ->get(['id', 'name', 'rank', 'default_discount_bps', 'minimum_profit', 'status']),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload, User $admin, Request $request): GlobalTopupPackage
    {
        $globalPackage = GlobalTopupPackage::query()->create($payload);
        $this->audit($admin, 'global_topup_package_created', $globalPackage, [], $globalPackage->getAttributes(), $request);

        return $globalPackage;
    }

    /** @param array<string, mixed> $payload */
    public function update(GlobalTopupPackage $globalPackage, array $payload, User $admin, Request $request): GlobalTopupPackage
    {
        $highestProviderPrice = (int) ($globalPackage->packages()->max('provider_price') ?? 0);

        if ((int) $payload['price'] < $highestProviderPrice) {
            throw ValidationException::withMessages([
                'price' => "Giá gói Global phải từ {$highestProviderPrice}đ vì có gói game đang dùng giá vốn này.",
            ]);
        }

        if ((int) $payload['denomination'] !== $globalPackage->denomination && $globalPackage->packages()->exists()) {
            throw ValidationException::withMessages([
                'denomination' => 'Không thể đổi mệnh giá khi gói Global đã được ánh xạ vào gói game.',
            ]);
        }

        if (($payload['status'] ?? null) === 'inactive' && $this->hasActiveGlobalMapping($globalPackage)) {
            throw ValidationException::withMessages([
                'status' => 'Không thể tắt gói Global đang được gói hoạt động của game Global sử dụng.',
            ]);
        }

        $old = $globalPackage->getAttributes();
        $globalPackage->update($payload);
        $this->audit($admin, 'global_topup_package_updated', $globalPackage, $old, $globalPackage->getAttributes(), $request);

        return $globalPackage->refresh();
    }

    public function delete(GlobalTopupPackage $globalPackage, User $admin, Request $request): void
    {
        if ($globalPackage->packages()->exists()) {
            throw ValidationException::withMessages([
                'global_package' => 'Hãy gỡ gói Global khỏi tất cả gói game trước khi xóa.',
            ]);
        }

        $old = $globalPackage->getAttributes();
        $this->audit($admin, 'global_topup_package_deleted', $globalPackage, $old, [], $request);
        $globalPackage->delete();
    }

    /** @param array<string, mixed> $payload */
    public function upsertLevelPrice(
        GlobalTopupPackage $globalPackage,
        MemberLevel $level,
        array $payload,
        User $admin,
        Request $request,
    ): MemberLevelGlobalPackagePrice {
        $payload['discount_basis_points'] = $payload['pricing_mode'] === 'discount' ? $payload['discount_basis_points'] : null;
        $payload['fixed_price'] = $payload['pricing_mode'] === 'fixed' ? $payload['fixed_price'] : null;
        $levelPrice = MemberLevelGlobalPackagePrice::query()->firstOrNew([
            'member_level_id' => $level->id,
            'global_topup_package_id' => $globalPackage->id,
        ]);
        $old = $levelPrice->exists ? $levelPrice->getAttributes() : [];
        $levelPrice->fill($payload)->save();
        $this->audit($admin, 'global_topup_package_level_price_saved', $levelPrice, $old, $levelPrice->getAttributes(), $request);

        return $levelPrice->refresh();
    }

    public function deleteLevelPrice(GlobalTopupPackage $globalPackage, MemberLevel $level, User $admin, Request $request): void
    {
        $levelPrice = MemberLevelGlobalPackagePrice::query()
            ->whereBelongsTo($globalPackage)
            ->whereBelongsTo($level)
            ->first();

        if (! $levelPrice instanceof MemberLevelGlobalPackagePrice) {
            return;
        }

        $old = $levelPrice->getAttributes();
        $this->audit($admin, 'global_topup_package_level_price_deleted', $levelPrice, $old, [], $request);
        $levelPrice->delete();
    }

    private function hasActiveGlobalMapping(GlobalTopupPackage $globalPackage): bool
    {
        return $globalPackage->packages()
            ->where('status', 'active')
            ->whereHas('game', fn ($query) => $query->where('package_mode', 'global')->where('status', 'active'))
            ->exists();
    }

    /** @param array<string, mixed> $old @param array<string, mixed> $new */
    private function audit(User $admin, string $action, Model $subject, array $old, array $new, Request $request): void
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
