<?php

namespace App\Features\Topup\Services;

use App\Enums\TopupProviderType;
use App\Features\Topup\Contracts\TopupProviderInterface;
use App\Features\Topup\Providers\AccNroVnTopupProvider;
use App\Features\Topup\Providers\ManualTopupProvider;
use App\Features\Topup\Providers\MerchantPartnerCardTopupProvider;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use Illuminate\Validation\ValidationException;

class TopupProviderResolver
{
    /** @var array<int, string> */
    public const BALANCE_PROVIDER_TYPES = [
        TopupProviderType::MerchantPartnerCard->value,
        TopupProviderType::AccNro->value,
    ];

    /** @var array<int, string> */
    public const STATUS_CHECK_PROVIDER_TYPES = [
        TopupProviderType::MerchantPartnerCard->value,
        TopupProviderType::AccNro->value,
    ];

    public function __construct(
        private readonly ManualTopupProvider $manualProvider,
        private readonly MerchantPartnerCardTopupProvider $merchantPartnerCardProvider,
        private readonly AccNroVnTopupProvider $accNroVnProvider,
    ) {}

    public function resolve(?TopupProvider $provider): TopupProviderInterface
    {
        return $this->resolveType($provider?->type?->value, $provider?->slug);
    }

    public function resolveForOrder(Order $order): TopupProviderInterface
    {
        return $this->resolveType(
            (string) data_get($order->metadata, 'provider.type', $order->provider?->type?->value),
            (string) data_get($order->metadata, 'provider.slug', $order->provider?->slug),
        );
    }

    private function resolveType(?string $type, ?string $legacySlug = null): TopupProviderInterface
    {
        $resolvedType = match ($legacySlug) {
            'accnrovn' => TopupProviderType::AccNro->value,
            'manual' => TopupProviderType::Manual->value,
            default => $type ?: match ($legacySlug) {
                'the9p' => TopupProviderType::MerchantPartnerCard->value,
                default => $legacySlug,
            },
        };

        if (blank($resolvedType) || $resolvedType === TopupProviderType::Manual->value) {
            return $this->manualProvider;
        }

        if ($resolvedType === TopupProviderType::MerchantPartnerCard->value) {
            return $this->merchantPartnerCardProvider;
        }

        if ($resolvedType === TopupProviderType::AccNro->value) {
            return $this->accNroVnProvider;
        }

        throw ValidationException::withMessages([
            'package_id' => 'Gói nạp này chưa hỗ trợ xử lý tự động. Vui lòng chọn gói khác hoặc liên hệ hỗ trợ.',
        ]);
    }

    public static function supportsBalance(?string $typeOrLegacySlug): bool
    {
        return in_array($typeOrLegacySlug, [...self::BALANCE_PROVIDER_TYPES, 'the9p', 'accnrovn'], true);
    }

    public static function supportsStatusChecks(?string $typeOrLegacySlug): bool
    {
        return in_array($typeOrLegacySlug, [...self::STATUS_CHECK_PROVIDER_TYPES, 'the9p', 'accnrovn'], true);
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
