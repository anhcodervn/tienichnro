<?php

namespace App\Features\Admin\Setting\Controllers;

use App\Http\Controllers\Controller;
use App\Support\SettingStore;
use Illuminate\Http\Response;

class SiteCustomAssetController extends Controller
{
    public function css(SettingStore $settingStore): Response
    {
        return $this->assetResponse(
            $this->enabledCode($settingStore, 'custom_css', 'custom_css_enabled'),
            'text/css; charset=UTF-8',
        );
    }

    public function javascript(SettingStore $settingStore): Response
    {
        return $this->assetResponse(
            $this->enabledCode($settingStore, 'custom_js', 'custom_js_enabled'),
            'application/javascript; charset=UTF-8',
        );
    }

    private function enabledCode(SettingStore $settingStore, string $codeKey, string $enabledKey): string
    {
        $settings = $settingStore->getMany([
            $codeKey => '',
            $enabledKey => false,
        ]);
        $code = is_string($settings[$codeKey]) ? $settings[$codeKey] : '';

        return $settings[$enabledKey] === true && $code !== '' ? $code : '';
    }

    private function assetResponse(string $content, string $contentType): Response
    {
        return response($content, 200, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
