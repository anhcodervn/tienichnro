<?php

namespace App\Features\Admin\Topup\Services;

use App\Features\Topup\Services\GlobalTopupPackageSyncService;
use App\Models\AdminAuditLog;
use App\Models\Game;
use App\Models\GlobalTopupPackage;
use App\Models\GlobalTopupPackageGameSetting;
use App\Models\TopupPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GlobalTopupRewardAdminService
{
    public function __construct(private readonly GlobalTopupPackageSyncService $syncService) {}

    /** @return array<string, mixed> */
    public function catalog(): array
    {
        return [
            'games' => Game::query()
                ->with([
                    'globalPackageSettings:id,game_id,denomination,receives',
                    'packages' => fn ($query) => $query
                        ->whereNotNull('denomination')
                        ->select([
                            'id', 'game_id', 'global_topup_package_id', 'name', 'denomination', 'status', 'sort_order',
                        ]),
                ])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'slug', 'status'])
                ->map(fn (Game $game): array => $this->gamePayload($game)),
        ];
    }

    /** @param array<int, array<string, mixed>> $packageSettings */
    public function updateGame(Game $game, array $packageSettings, User $admin, Request $request): void
    {
        DB::transaction(function () use ($game, $packageSettings, $admin, $request): void {
            foreach ($packageSettings as $packageSetting) {
                $denomination = (int) $packageSetting['denomination'];
                $topupPackages = TopupPackage::query()
                    ->with('globalTopupPackage')
                    ->whereBelongsTo($game)
                    ->where('denomination', $denomination)
                    ->lockForUpdate()
                    ->get();
                $receives = collect($packageSetting['receives'])
                    ->map(fn (array $receive): array => [
                        ...$receive,
                        'code' => mb_strtoupper($receive['code']),
                    ])
                    ->values()
                    ->all();

                $setting = GlobalTopupPackageGameSetting::query()->firstOrNew([
                    'game_id' => $game->id,
                    'denomination' => $denomination,
                ]);
                $old = $setting->exists ? $setting->getAttributes() : [];
                $setting->fill([
                    'receives' => $receives,
                ])->save();

                $this->syncCustomPackages($topupPackages, $receives);
                $topupPackages
                    ->pluck('globalTopupPackage')
                    ->filter(fn (mixed $package): bool => $package instanceof GlobalTopupPackage)
                    ->unique('id')
                    ->each(fn (GlobalTopupPackage $package) => $this->syncService->syncPackageForGame($package, $game));
                $this->audit($admin, $setting, $old, $setting->getAttributes(), $request);
            }
        }, 3);
    }

    /** @return array<string, mixed> */
    private function gamePayload(Game $game): array
    {
        $denominations = $game->packages
            ->groupBy('denomination')
            ->map(function ($packages, int|string $denomination): array {
                /** @var TopupPackage $representative */
                $representative = $packages->firstWhere('global_topup_package_id', '!=', null) ?? $packages->first();

                return [
                    'name' => $representative->name,
                    'denomination' => (int) $denomination,
                    'status' => $packages->contains('status', 'active') ? 'active' : 'inactive',
                ];
            })
            ->keyBy('denomination');

        $game->globalPackageSettings->each(function (GlobalTopupPackageGameSetting $setting) use ($denominations): void {
            if (! $denominations->has($setting->denomination)) {
                $denominations->put($setting->denomination, [
                    'name' => 'Mệnh giá '.number_format($setting->denomination, 0, ',', '.').'đ',
                    'denomination' => $setting->denomination,
                    'status' => 'inactive',
                ]);
            }
        });

        return [
            'id' => $game->id,
            'name' => $game->name,
            'slug' => $game->slug,
            'status' => $game->status,
            'reward_settings' => $game->globalPackageSettings->values(),
            'denominations' => $denominations->sortBy('denomination')->values(),
        ];
    }

    /** @param Collection<int, TopupPackage> $packages @param array<int, array<string, mixed>> $receives */
    private function syncCustomPackages($packages, array $receives): void
    {
        $primaryReceive = $receives[0] ?? [];

        $packages
            ->whereNull('global_topup_package_id')
            ->each(function (TopupPackage $package) use ($primaryReceive, $receives): void {
                $package->fill([
                    'carot_amount' => data_get($primaryReceive, 'base_amount'),
                    'reward_x2_amount' => data_get($primaryReceive, 'reward_x2_amount'),
                    'reward_x3_amount' => data_get($primaryReceive, 'reward_x3_amount'),
                    'first_topup_reward_amount' => data_get($primaryReceive, 'first_topup_reward_amount'),
                    'metadata' => [
                        ...($package->metadata ?? []),
                        'global_receives' => $receives,
                    ],
                ])->save();
            });
    }

    /** @param array<string, mixed> $old @param array<string, mixed> $new */
    private function audit(
        User $admin,
        GlobalTopupPackageGameSetting $setting,
        array $old,
        array $new,
        Request $request,
    ): void {
        AdminAuditLog::query()->create([
            'admin_id' => $admin->id,
            'action' => 'global_topup_reward_saved',
            'subject_type' => $setting::class,
            'subject_id' => $setting->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
