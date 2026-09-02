<?php

namespace App\Features\Topup\Services;

use App\Features\Topup\Contracts\TopupProviderInterface;
use App\Features\Topup\Providers\AccNroVnTopupProvider;
use App\Features\Topup\Providers\ManualTopupProvider;
use App\Features\Topup\Providers\The9pTopupProvider;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use Illuminate\Validation\ValidationException;

class TopupProviderResolver
{
    /** @var array<int, string> */
    public const BALANCE_PROVIDER_SLUGS = ['the9p', 'accnrovn'];

    /** @var array<int, string> */
    public const STATUS_CHECK_PROVIDER_SLUGS = ['the9p', 'accnrovn'];

    public function __construct(
        private readonly ManualTopupProvider $manualProvider,
        private readonly The9pTopupProvider $the9pProvider,
        private readonly AccNroVnTopupProvider $accNroVnProvider,
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

        if ($slug === 'accnrovn') {
            return $this->accNroVnProvider;
        }

        throw ValidationException::withMessages([
            'package_id' => 'Gói nạp này chưa hỗ trợ xử lý tự động. Vui lòng chọn gói khác hoặc liên hệ hỗ trợ.',
        ]);
    }

    public static function supportsBalance(?string $slug): bool
    {
        return in_array($slug, self::BALANCE_PROVIDER_SLUGS, true);
    }

    public static function supportsStatusChecks(?string $slug): bool
    {
        return in_array($slug, self::STATUS_CHECK_PROVIDER_SLUGS, true);
    }

    public function assertAvailable(TopupPackage $package, GameServer $server): void
    {
        $package->loadMissing(['game', 'provider']);
        $provider = $package->provider;

        if (! $provider instanceof TopupProvider) {
            return;
        }

        $this->resolve($provider)->assertConfigured($provider, $package, $server);
    }
}
