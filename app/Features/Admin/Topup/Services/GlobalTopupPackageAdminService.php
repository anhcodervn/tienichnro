<?php

namespace App\Features\Admin\Topup\Services;

use App\Features\Topup\Services\GlobalTopupPackageSyncService;
use App\Models\AdminAuditLog;
use App\Models\GlobalTopupPackage;
use App\Models\TopupProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GlobalTopupPackageAdminService
{
    public function __construct(private readonly GlobalTopupPackageSyncService $syncService) {}

    /** @return array<string, mixed> */
    public function catalog(): array
    {
        return [
            'global_packages' => GlobalTopupPackage::query()
                ->with('provider:id,name,slug')
                ->withCount('packages')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'providers' => TopupProvider::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload, User $admin, Request $request): GlobalTopupPackage
    {
        return DB::transaction(function () use ($payload, $admin, $request): GlobalTopupPackage {
            $payload['original_price'] = (int) $payload['denomination'];
            $payload['metadata'] = [
                ...($payload['metadata'] ?? []),
                'requires_game_rewards' => true,
            ];
            $globalPackage = GlobalTopupPackage::query()->create($payload);
            $this->syncService->sync($globalPackage);
            $this->audit($admin, 'global_topup_package_created', $globalPackage, [], $globalPackage->getAttributes(), $request);

            return $globalPackage->load('provider:id,name,slug');
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function update(GlobalTopupPackage $globalPackage, array $payload, User $admin, Request $request): GlobalTopupPackage
    {
        if ((int) $payload['denomination'] !== $globalPackage->denomination && $globalPackage->packages()->exists()) {
            throw ValidationException::withMessages([
                'denomination' => 'Không thể đổi mệnh giá khi gói Global đã được đồng bộ vào game.',
            ]);
        }

        return DB::transaction(function () use ($globalPackage, $payload, $admin, $request): GlobalTopupPackage {
            $old = $globalPackage->getAttributes();
            $payload['original_price'] = (int) $payload['denomination'];
            $payload['metadata'] = [
                ...($globalPackage->metadata ?? []),
                ...($payload['metadata'] ?? []),
            ];
            $globalPackage->update($payload);
            $this->syncService->sync($globalPackage->refresh());
            $this->audit($admin, 'global_topup_package_updated', $globalPackage, $old, $globalPackage->getAttributes(), $request);

            return $globalPackage->refresh()->load('provider:id,name,slug');
        }, 3);
    }

    public function delete(GlobalTopupPackage $globalPackage, User $admin, Request $request): void
    {
        if ($globalPackage->packages()->whereHas('orders')->exists()) {
            throw ValidationException::withMessages([
                'global_package' => 'Không thể xóa gói Global đã phát sinh đơn hàng. Hãy chuyển sang Tạm tắt.',
            ]);
        }

        DB::transaction(function () use ($globalPackage, $admin, $request): void {
            $old = $globalPackage->getAttributes();
            $globalPackage->packages()->delete();
            $this->audit($admin, 'global_topup_package_deleted', $globalPackage, $old, [], $request);
            $globalPackage->delete();
        }, 3);
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
