<?php

namespace App\Features\Topup\Services;

use App\Models\Game;
use App\Models\SeoPost;
use App\Models\TopupPackage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SeoPricePageService
{
    public function __construct(
        private readonly TopupPackagePricingService $pricingService,
        private readonly GameRewardService $gameRewardService,
    ) {}

    /**
     * @return array{service: ?Game, packages: Collection<int, TopupPackage>, updated_at: ?Carbon}
     */
    public function resolve(SeoPost $post, ?User $user = null): array
    {
        if ($post->type !== 'price' || $post->service_id === null) {
            return $this->emptyResult();
        }

        $service = Game::query()->active()->find($post->service_id);

        if (! $service instanceof Game) {
            return $this->emptyResult();
        }

        $packages = $service->packages()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('denomination')
            ->orderBy('id')
            ->get();

        $this->gameRewardService->applyToPackages($packages);
        $this->pricingService->apply($packages, $user);

        $packages = $packages
            ->filter(fn (TopupPackage $package): bool => (bool) $package->getAttribute('is_price_available'))
            ->values();
        $latestPackage = $packages
            ->sortByDesc(fn (TopupPackage $package): int => $package->updated_at?->getTimestamp() ?? 0)
            ->first();

        return [
            'service' => $service,
            'packages' => $packages,
            'updated_at' => $latestPackage?->updated_at,
        ];
    }

    /** @return array{service: null, packages: Collection<int, TopupPackage>, updated_at: null} */
    private function emptyResult(): array
    {
        return [
            'service' => null,
            'packages' => collect(),
            'updated_at' => null,
        ];
    }
}
