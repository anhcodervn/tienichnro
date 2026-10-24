<?php

namespace App\Features\NroNotification\Services;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Models\Boss;
use App\Models\CodeNotify;
use App\Models\NroServer;
use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NotificationReadCacheService
{
    public function __construct(private readonly ToolAvailabilityService $tools) {}

    public function store(): Repository
    {
        $name = config('services.nro_notifications.cache_store') ?: config('cache.default');
        if (! config('services.nro_notifications.cache_store') && config('cache.stores.'.$name.'.driver') === 'database') {
            $name = 'file';
        }

        return Cache::store($name);
    }

    /** @param array<string, mixed> $filters */
    public function remember(string $scope, array $filters, Closure $load, ?int $ttl = null): mixed
    {
        $store = $this->store();
        ksort($filters);
        $generation = $store->get('nro:read:'.$scope.':generation', 'initial');
        $key = 'nro:read:v2:'.$scope.':'.$generation.':'.hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));
        $cached = $store->get($key);
        if ($cached !== null) {
            return $cached;
        }
        $resolve = function () use ($store, $key, $load, $ttl, $scope, $generation): mixed {
            $value = $ttl === null ? $store->rememberForever($key, $load) : $store->remember($key, max(1, $ttl), $load);
            if ($ttl === null) {
                $store->lock('nro:read:'.$scope.':registry-lock', 10)->block(3, function () use ($store, $key, $scope, $generation): void {
                    if ($store->get('nro:read:'.$scope.':generation', 'initial') !== $generation) {
                        $store->forget($key);

                        return;
                    }
                    $keys = $store->get('nro:read:'.$scope.':keys', []);
                    $keys[$key] = true;
                    $store->forever('nro:read:'.$scope.':keys', $keys);
                });
            }

            return $value;
        };
        try {
            return $store->lock($key.':lock', 10)->block(3, $resolve);
        } catch (LockTimeoutException) {
            return $resolve();
        }
    }

    public function invalidate(string $scope): void
    {
        $store = $this->store();
        $store->lock('nro:read:'.$scope.':registry-lock', 10)->block(3, function () use ($store, $scope): void {
            $store->forever('nro:read:'.$scope.':generation', (string) Str::uuid());
            foreach (array_keys($store->get('nro:read:'.$scope.':keys', [])) as $key) {
                $store->forget($key);
            }
            $store->forget('nro:read:'.$scope.':keys');
        });
    }

    public function catalogTtl(): int
    {
        return max(1, (int) config('services.nro_notifications.catalog_cache_ttl_seconds', 60));
    }

    /** @return array<string, mixed> */
    public function catalog(): array
    {
        return $this->remember('catalog', ['kind' => 'page-options'], fn (): array => [
            'notificationTypes' => CodeNotify::query()->orderBy('id')->get(),
            'servers' => NroServer::query()->where('is_active', true)->orderBy('sort_order')->orderBy('server_code')->get(),
            'bosses' => Boss::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->orderBy('id')->get(),
        ], $this->catalogTtl());
    }

    /** @return array<string, array<int, int|string>> */
    public function validationOptions(): array
    {
        return $this->remember('catalog', ['kind' => 'validation'], function (): array {
            $servers = NroServer::query()->get(['id', 'server_code']);

            return ['server_ids' => $servers->modelKeys(), 'server_codes' => $servers->pluck('server_code')->all(),
                'boss_ids' => Boss::withTrashed()->pluck('id')->all(), 'codes' => CodeNotify::withTrashed()->pluck('code')->all()];
        }, $this->catalogTtl());
    }

    /** @return array<string, mixed> */
    public function availability(): array
    {
        return $this->remember('availability', [], fn (): array => $this->tools->interaction('game_notifications'), $this->catalogTtl());
    }
}
