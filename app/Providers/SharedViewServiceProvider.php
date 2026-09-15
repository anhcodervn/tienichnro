<?php

namespace App\Providers;

use App\Models\AffiliateProgram;
use App\Models\User;
use App\Support\CustomHeadTags;
use App\Support\SafeNavigationUrl;
use App\Support\SettingStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SharedViewServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(SettingStore $settingStore, CustomHeadTags $customHeadTags): void
    {
        ViewFacade::composer('client.layouts.app', function (View $view) use ($customHeadTags, $settingStore): void {
            $storedSettings = $settingStore->getMany([
                'site_name' => config('app.name', 'Nạp Carot'),
                'site_domain' => '',
                'site_description' => '',
                'meta_title' => '',
                'meta_description' => '',
                'robots' => 'index,follow',
                'light_logo' => '',
                'dark_logo' => '',
                'favicon' => '',
                'og_image' => '',
                'color_primary' => '#0F172A',
                'color_accent' => '#2563EB',
                'color_surface' => '#F8FAFC',
                'game_service_enabled' => false,
                'game_service_items' => [],
                'game_service_url' => '',
                'footer_game_links' => [],
                'gtm_id' => '',
                'meta_pixel_id' => '',
                'custom_head_tags' => '',
                'custom_script' => '',
                'custom_css' => '',
                'custom_css_enabled' => false,
                'custom_js' => '',
                'custom_js_enabled' => false,
            ]);
            $sharedSettings = Arr::except($storedSettings, [
                'custom_css',
                'custom_css_enabled',
                'custom_js',
                'custom_js_enabled',
                'custom_head_tags',
                'custom_script',
            ]);
            $gameServiceItems = $this->normalizeNavigationItems($storedSettings['game_service_items']);
            $footerGameLinks = $this->normalizeNavigationItems($storedSettings['footer_game_links']);

            if ($gameServiceItems === [] && SafeNavigationUrl::passes($storedSettings['game_service_url'])) {
                $gameServiceItems = [[
                    'label' => 'Dịch vụ game',
                    'url' => $storedSettings['game_service_url'],
                ]];
            }

            $sharedSettings['game_service_items'] = $gameServiceItems;
            $viewSettings = $view->getData()['systemSettings'] ?? [];
            $user = auth()->user();

            if ($user instanceof User) {
                $displayName = $user->name ?: $user->email;
                $walletBalance = $user->relationLoaded('wallet')
                    ? $user->wallet?->balance
                    : $user->wallet()->value('balance');

                $view->with('clientAccount', [
                    'avatar' => (string) ($user->avatar ?? ''),
                    'balance' => (string) ($walletBalance ?? '0'),
                    'email' => (string) $user->email,
                    'initial' => Str::upper(Str::substr($displayName, 0, 1)),
                    'name' => $displayName,
                    'role' => (string) $user->role,
                ]);
            }

            $view->with('systemSettings', [
                ...$sharedSettings,
                ...(is_array($viewSettings) ? $viewSettings : []),
                'game_service_items' => $gameServiceItems,
                'footer_game_links' => $footerGameLinks,
            ]);
            $view->with('customCodeAssets', [
                'css' => $storedSettings['custom_css_enabled'] === true && $storedSettings['custom_css'] !== '',
                'js' => $storedSettings['custom_js_enabled'] === true && $storedSettings['custom_js'] !== '',
            ]);
            $view->with('clientTracking', [
                'gtm_id' => $this->normalizeGtmId($storedSettings['gtm_id']),
                'meta_pixel_id' => $this->normalizeMetaPixelId($storedSettings['meta_pixel_id']),
            ]);
            $view->with('inlineSeoCode', [
                'head' => $customHeadTags->sanitize($storedSettings['custom_head_tags']),
                'script' => is_string($storedSettings['custom_script']) ? $storedSettings['custom_script'] : '',
            ]);
            $view->with('affiliateEnabled', Schema::hasTable('affiliate_programs')
                && AffiliateProgram::query()->where('is_enabled', true)->exists());
        });
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function normalizeNavigationItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(function (mixed $item): bool {
                if (! is_array($item)) {
                    return false;
                }

                $label = $item['label'] ?? null;

                return is_string($label)
                    && trim($label) !== ''
                    && mb_strlen(trim($label)) <= 80
                    && preg_match('/[\x00-\x1F\x7F]/u', $label) !== 1
                    && SafeNavigationUrl::passes($item['url'] ?? null);
            })
            ->take(20)
            ->map(fn (array $item): array => [
                'label' => trim($item['label']),
                'url' => trim($item['url']),
            ])
            ->values()
            ->all();
    }

    private function normalizeGtmId(mixed $value): string
    {
        $gtmId = is_string($value) ? trim($value) : '';

        return preg_match('/\AGTM-[A-Z0-9]+\z/', $gtmId) === 1 ? $gtmId : '';
    }

    private function normalizeMetaPixelId(mixed $value): string
    {
        $metaPixelId = is_string($value) ? trim($value) : '';

        return preg_match('/\A[0-9]+\z/', $metaPixelId) === 1 ? $metaPixelId : '';
    }
}
