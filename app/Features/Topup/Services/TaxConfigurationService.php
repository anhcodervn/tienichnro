<?php

namespace App\Features\Topup\Services;

use App\Enums\TaxCalculationType;
use App\Support\SettingStore;
use App\Utils\Site;

class TaxConfigurationService
{
    public function __construct(private readonly SettingStore $settingStore) {}

    /**
     * @return array{enabled: bool, calculation_type: TaxCalculationType, vat_rate: string, pit_rate: string}
     */
    public function current(): array
    {
        if (! Site::isMain()) {
            return [
                'enabled' => false,
                'calculation_type' => TaxCalculationType::Revenue,
                'vat_rate' => '0.0000',
                'pit_rate' => '0.0000',
            ];
        }

        $settings = $this->settingStore->getMany([
            'tax_enabled' => false,
            'tax_calculation_type' => TaxCalculationType::Revenue->value,
            'vat_rate' => '1.0000',
            'pit_rate' => '0.5000',
        ]);

        return [
            'enabled' => (bool) $settings['tax_enabled'],
            'calculation_type' => TaxCalculationType::tryFrom((string) $settings['tax_calculation_type']) ?? TaxCalculationType::Revenue,
            'vat_rate' => $this->normalizeRate($settings['vat_rate']),
            'pit_rate' => $this->normalizeRate($settings['pit_rate']),
        ];
    }

    private function normalizeRate(mixed $rate): string
    {
        if (! is_numeric($rate)) {
            return '0.0000';
        }

        return number_format(min(max((float) $rate, 0), 100), 4, '.', '');
    }
}
