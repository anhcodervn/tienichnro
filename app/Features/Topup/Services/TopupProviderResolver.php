<?php

namespace App\Features\Topup\Services;

use App\Features\Topup\Contracts\TopupProviderInterface;
use App\Features\Topup\Providers\ManualTopupProvider;
use App\Features\Topup\Providers\The9pTopupProvider;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use Illuminate\Validation\ValidationException;

class TopupProviderResolver
{
    public function __construct(
        private readonly ManualTopupProvider $manualProvider,
        private readonly The9pTopupProvider $the9pProvider,
    ) {}

    public function resolve(?TopupProvider $provider): TopupProviderInterface
    {
        return $this->resolveSlug($provider?->slug);
    }

    public function resolveForOrder(Order $order): TopupProviderInterface
    {
        return $this->resolveSlug(
            (string) data_get($order->metadata, 'provider.slug', $order->provider?->slug),
        );
    }

    private function resolveSlug(?string $slug): TopupProviderInterface
    {
        if (blank($slug) || $slug === 'manual') {
            return $this->manualProvider;
        }

        if ($slug === 'the9p') {
            return $this->the9pProvider;
        }

        throw ValidationException::withMessages([
            'package_id' => 'Gói nạp này chưa hỗ trợ xử lý tự động. Vui lòng chọn gói khác hoặc liên hệ hỗ trợ.',
        ]);
    }

    public function assertAvailable(TopupPackage $package, GameServer $server): void
    {
        $provider = $package->provider;

        if (! $provider instanceof TopupProvider) {
            return;
        }

        $this->resolve($provider)->assertConfigured($provider, $package, $server);
    }
}
