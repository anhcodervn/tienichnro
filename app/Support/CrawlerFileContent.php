<?php

namespace App\Support;

use App\Models\Setting;

class CrawlerFileContent
{
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
        $content = filled($storedContent) ? $storedContent : $this->defaultRobots();

        return $this->normalize($this->ensureSitemapDirective($content));
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
        return (string) (Setting::query()->where('key', $key)->value('value') ?? '');
    }

    private function normalize(string $content): string
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $content));

        return $normalized === '' ? '' : $normalized."\n";
    }

    private function ensureSitemapDirective(string $content): string
    {
        $directive = 'Sitemap: '.route('sitemap');
        $lines = preg_split('/\R/u', trim($content)) ?: [];

        if (in_array($directive, $lines, true)) {
            return $content;
        }

        return rtrim($content)."\n\n".$directive;
    }
}
