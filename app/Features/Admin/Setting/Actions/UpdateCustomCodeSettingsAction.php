<?php

namespace App\Features\Admin\Setting\Actions;

use App\Support\SettingStore;
use Illuminate\Support\Facades\DB;

class UpdateCustomCodeSettingsAction
{
    public function __construct(private readonly SettingStore $settingStore) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{custom_css: string, custom_css_enabled: bool, custom_js: string, custom_js_enabled: bool}
     */
    public function execute(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            if ($payload !== []) {
                $this->settingStore->putMany($payload);
            }

            /** @var array{custom_css: string, custom_css_enabled: bool, custom_js: string, custom_js_enabled: bool} */
            return $this->settingStore->getMany([
                'custom_css' => '',
                'custom_css_enabled' => false,
                'custom_js' => '',
                'custom_js_enabled' => false,
            ]);
        });
    }
}
