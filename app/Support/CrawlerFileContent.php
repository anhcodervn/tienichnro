<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\TenantSetting;

class CrawlerFileContent
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    public function robots(SettingStore $settingStore): string
    {
        if (! (bool) $settingStore->get('site_active', true)) {
            return "User-agent: *\nDisallow: /\n";
        }

        return $this->robotsForEditing();
    }

    public function ads(): string
    {
        return $this->adsForEditing();
    }

    public function robotsForEditing(): string
    {
        $storedContent = $this->localString('robots_txt');

        return $this->normalize(filled($storedContent) ? $storedContent : $this->defaultRobots());
    }

    public function adsForEditing(): string
    {
        return $this->normalize($this->localString('ads_txt'));
    }

    public function defaultRobots(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /api',
            'Disallow: /tai-khoan',
            'Disallow: /don-hang',
            'Disallow: /dang-nhap',
            'Disallow: /dang-ky',
            '',
            'Sitemap: '.route('sitemap'),
        ]);
    }

    private function localString(string $key): string
    {
        if ($this->tenantContext->isActive() && ! $this->tenantContext->isMain()) {
            return (string) (TenantSetting::query()
                ->where('tenant_id', $this->tenantContext->id())
                ->where('key', $key)
                ->value('value') ?? '');
        }

        return (string) (Setting::query()->where('key', $key)->value('value') ?? '');
    }

    private function normalize(string $content): string
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $content));

        return $normalized === '' ? '' : $normalized."\n";
    }
}
