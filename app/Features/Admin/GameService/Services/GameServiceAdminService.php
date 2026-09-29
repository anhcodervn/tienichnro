<?php

namespace App\Features\Admin\GameService\Services;

use App\Models\AdminAuditLog;
use App\Models\Game;
use App\Models\GameService;
use App\Models\GameServiceOrder;
use App\Models\GameServicePackage;
use App\Models\GameServicePackagePrice;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GameServiceAdminService
{
    /** @param array<string, mixed> $filters */
    public function games(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Game::query()
            ->withCount(['servers', 'gameServices'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('provider_service_code', 'like', "%{$search}%")))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage($filters));
    }

    /** @param array<string, mixed> $payload */
    public function updateGame(Game $game, array $payload, User $admin, Request $request): Game
    {
        $old = Arr::only($game->getAttributes(), ['provider_service_code', 'game_services_enabled']);
        $game->fill($payload)->save();
        $this->audit($admin, 'game_service_game_updated', $game, $old, Arr::only($game->fresh()->getAttributes(), array_keys($old)), $request);

        return $game->refresh();
    }

    /** @param array<string, mixed> $filters */
    public function services(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return GameService::query()
            ->with(['game:id,name,slug,provider_service_code', 'servers:id,game_id,name,code'])
            ->withCount(['packages', 'orders'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->when(filled($filters['game_id'] ?? null), fn (Builder $query) => $query->where('game_id', $filters['game_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage($filters));
    }

    /** @param array<string, mixed> $payload */
    public function createService(array $payload, User $admin, Request $request): GameService
    {
        return DB::transaction(function () use ($payload, $admin, $request): GameService {
            $serverIds = Arr::pull($payload, 'server_ids', []);
            $service = GameService::query()->create($payload);
            $service->servers()->sync($serverIds);
            $this->audit($admin, 'created', $service, [], $service->getAttributes(), $request);

            return $this->loadService($service);
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function updateService(GameService $service, array $payload, User $admin, Request $request): GameService
    {
        return DB::transaction(function () use ($service, $payload, $admin, $request): GameService {
            $locked = GameService::query()->lockForUpdate()->findOrFail($service->id);
            $old = $locked->getAttributes();
            $serverIds = Arr::pull($payload, 'server_ids', []);
            $locked->fill($payload)->save();
            $locked->servers()->sync($serverIds);
            $this->audit($admin, 'updated', $locked, $old, $locked->fresh()->getAttributes(), $request);

            return $this->loadService($locked);
        }, 3);
    }

    public function deleteService(GameService $service, User $admin, Request $request): void
    {
        DB::transaction(function () use ($service, $admin, $request): void {
            $locked = GameService::query()->lockForUpdate()->findOrFail($service->id);

            if ($locked->orders()->exists()) {
                throw ValidationException::withMessages(['service' => 'Dịch vụ đã có đơn hàng. Hãy chuyển sang Tạm tắt để giữ lịch sử.']);
            }

            $old = $locked->getAttributes();
            $this->audit($admin, 'deleted', $locked, $old, [], $request);
            $locked->delete();
        }, 3);
    }

    /** @param array<string, mixed> $filters */
    public function packages(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return GameServicePackage::query()
            ->with(['service:id,game_id,name', 'service.game:id,name', 'prices'])
            ->withCount('orders')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->when(filled($filters['game_service_id'] ?? null), fn (Builder $query) => $query->where('game_service_id', $filters['game_service_id']))
            ->when(filled($filters['game_id'] ?? null), fn (Builder $query) => $query->whereHas('service', fn (Builder $serviceQuery) => $serviceQuery->where('game_id', $filters['game_id'])))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage($filters));
    }

    /** @param array<string, mixed> $payload */
    public function createPackage(array $payload, User $admin, Request $request): GameServicePackage
    {
        return DB::transaction(function () use ($payload, $admin, $request): GameServicePackage {
            $prices = Arr::pull($payload, 'prices', []);
            $package = GameServicePackage::query()->create($payload);
            $this->syncPrices($package, $prices);
            $this->audit($admin, 'created', $package, [], $package->getAttributes(), $request);

            return $this->loadPackage($package);
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function updatePackage(GameServicePackage $package, array $payload, User $admin, Request $request): GameServicePackage
    {
        return DB::transaction(function () use ($package, $payload, $admin, $request): GameServicePackage {
            $locked = GameServicePackage::query()->lockForUpdate()->findOrFail($package->id);
            $old = $locked->getAttributes();
            $prices = Arr::pull($payload, 'prices', []);
            $locked->fill($payload)->save();
            $this->syncPrices($locked, $prices);
            $this->audit($admin, 'updated', $locked, $old, $locked->fresh()->getAttributes(), $request);

            return $this->loadPackage($locked);
        }, 3);
    }

    public function deletePackage(GameServicePackage $package, User $admin, Request $request): void
    {
        DB::transaction(function () use ($package, $admin, $request): void {
            $locked = GameServicePackage::query()->lockForUpdate()->findOrFail($package->id);

            if ($locked->orders()->exists()) {
                throw ValidationException::withMessages(['package' => 'Gói dịch vụ đã có đơn hàng. Hãy chuyển sang Tạm tắt để giữ lịch sử.']);
            }

            $old = $locked->getAttributes();
            $this->audit($admin, 'deleted', $locked, $old, [], $request);
            $locked->delete();
        }, 3);
    }

    /** @param array<string, mixed> $filters */
    public function orders(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return GameServiceOrder::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                ->where('code', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('service_name', 'like', "%{$search}%")
                ->orWhere('package_name', 'like', "%{$search}%")))
            ->when(filled($filters['game_id'] ?? null), fn (Builder $query) => $query->where('game_id', $filters['game_id']))
            ->when(filled($filters['game_service_id'] ?? null), fn (Builder $query) => $query->where('game_service_id', $filters['game_service_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->latest()
            ->paginate($this->perPage($filters));
    }

    /** @param array<string, mixed> $payload */
    public function updateOrder(GameServiceOrder $order, array $payload, User $admin, Request $request): GameServiceOrder
    {
        return DB::transaction(function () use ($order, $payload, $admin, $request): GameServiceOrder {
            $locked = GameServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            $old = Arr::only($locked->getAttributes(), ['status', 'admin_note', 'processing_at', 'completed_at']);
            $status = (string) $payload['status'];

            $payload['processing_at'] = $status === 'processing' ? ($locked->processing_at ?? now()) : $locked->processing_at;
            $payload['completed_at'] = $status === 'completed' ? ($locked->completed_at ?? now()) : null;
            $locked->fill($payload)->save();
            $this->audit($admin, 'game_service_order_updated', $locked, $old, Arr::only($locked->fresh()->getAttributes(), array_keys($old)), $request);

            return $locked->refresh();
        }, 3);
    }

    /** @param array<int, array<string, mixed>> $prices */
    private function syncPrices(GameServicePackage $package, array $prices): void
    {
        $codes = collect($prices)->pluck('code')->all();
        $pricesToDelete = $package->prices()->whereNotIn('code', $codes)->get();

        if ($pricesToDelete->contains(fn (GameServicePackagePrice $price): bool => $price->orders()->exists())) {
            throw ValidationException::withMessages(['prices' => 'Không thể xóa mức giá đã phát sinh đơn hàng.']);
        }

        $package->prices()->whereNotIn('code', $codes)->delete();

        foreach ($prices as $price) {
            $package->prices()->updateOrCreate(['code' => $price['code']], $price);
        }
    }

    private function loadService(GameService $service): GameService
    {
        return $service->refresh()->load(['game:id,name,slug,provider_service_code', 'servers:id,game_id,name,code'])->loadCount(['packages', 'orders']);
    }

    private function loadPackage(GameServicePackage $package): GameServicePackage
    {
        return $package->refresh()->load(['service:id,game_id,name', 'service.game:id,name', 'prices'])->loadCount('orders');
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

    /** @param array<string, mixed> $filters */
    private function perPage(array $filters): int
    {
        return min(max((int) ($filters['per_page'] ?? 20), 1), 100);
    }
}
