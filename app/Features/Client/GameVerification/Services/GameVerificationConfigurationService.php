<?php

namespace App\Features\Client\GameVerification\Services;

use App\Support\SettingStore;

class GameVerificationConfigurationService
{
    public function __construct(private readonly SettingStore $settings) {}

    public function configuration(): array
    {
        $price = $this->settings->get('game_verification.price');

        return ['price' => $price === null ? null : (int) $price];
    }

    public function configure(int $price): array
    {
        $this->settings->putMany(['game_verification.price' => $price]);

        return $this->configuration();
    }
}
