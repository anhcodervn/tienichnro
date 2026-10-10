<?php

namespace App\Providers;

use App\Features\Admin\Setting\Services\ServiceCatalogService;
use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Models\SeoCategory;
use App\Models\User;
use App\Models\Wallet;
use App\Support\CustomHeadTags;
use App\Support\SettingStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SharedViewServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(SettingStore $settingStore, CustomHeadTags $customHeadTags, ToolAvailabilityService $tools, ServiceCatalogService $services): void
    {
        ViewFacade::composer('client.layouts.app', function (View $view) use ($customHeadTags, $settingStore, $tools, $services): void {
            $view->with('clientTools', $tools->all());
            $view->with('clientServices', $services->all());
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
            $viewSettings = $view->getData()['systemSettings'] ?? [];
            $user = auth()->user();
            $view->with('navigationCategories', SeoCategory::query()
                ->where('is_active', true)->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'slug']));

            if ($user instanceof User) {
                $displayName = $user->name ?: $user->email;
                $view->with('clientAccount', [
                    'avatar' => (string) ($user->avatar ?? ''),
                    'email' => (string) $user->email,
                    'initial' => Str::upper(Str::substr($displayName, 0, 1)),
                    'name' => $displayName,
                    'role' => (string) $user->role,
                    'wallet_balance' => (int) (Wallet::query()->where('user_id', $user->id)->value('balance') ?? 0),
                ]);
            }

            $view->with('systemSettings', [
                ...$sharedSettings,
                ...(is_array($viewSettings) ? $viewSettings : []),
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
        });
    }

    private function normalizeGtmId(mixed $value): string
    {
        $gtmId = is_string($value) ? trim($value) : '';

        return preg_match('/\AGTM-[A-Z0-9]+\z/', $gtmId) === 1 ? $gtmId : '';
    }

    /**
     * @return array<int, array{icon: string, url: string}>
     */
    private function normalizeMetaPixelId(mixed $value): string
    {
        $metaPixelId = is_string($value) ? trim($value) : '';

        return preg_match('/\A[0-9]+\z/', $metaPixelId) === 1 ? $metaPixelId : '';
    }
}
