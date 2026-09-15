<?php

namespace App\Features\Admin\Setting\Controllers;

use App\Features\Admin\Setting\Actions\UpdateCustomCodeSettingsAction;
use App\Features\Admin\Setting\Requests\UpdateOptionSettingRequest;
use App\Features\Admin\Setting\Requests\UpdateSystemSettingRequest;
use App\Features\Admin\Setting\Requests\UpdateTabSettingRequest;
use App\Http\Controllers\Controller;
use App\Support\CrawlerFileContent;
use App\Support\SettingStore;
use App\Utils\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    protected const SYSTEM_TAB = 'system';

    public function __construct(
        protected CrawlerFileContent $crawlerFileContent,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function tabDefaults(): array
    {
        return [
            'general' => [
                'site_name' => '',
                'site_domain' => '',
                'site_description' => '',
                'site_active' => true,
                'allow_register' => false,
            ],
            'homepage' => [
                'home_notice_title' => 'Thông báo quan trọng',
                'home_notice_content' => [],
                'home_notice_is_published' => true,
            ],
            'popup-notice' => [
                'home_popup_title' => 'Thông báo',
                'home_popup_content' => [],
                'home_popup_is_published' => false,
                'home_popup_display_mode' => 'modal',
                'home_popup_allow_dismiss' => false,
                'home_popup_dismiss_hours' => 24,
            ],
            'service-articles' => [
                'game_service_enabled' => false,
                'game_service_items' => [],
                'footer_game_links' => [],
            ],
            'bio' => [
                'bio_title' => '',
                'bio_description' => '',
                'bio_avatar_url' => '',
                'bio_links' => [],
            ],
            'branding' => [
                'light_logo' => '',
                'dark_logo' => '',
                'favicon' => '',
                'og_image' => '',
                'color_primary' => '#0F172A',
                'color_accent' => '#2563EB',
                'color_surface' => '#F8FAFC',
            ],
            'contact' => [
                'hotline' => '',
                'support_email' => '',
                'address' => '',
                'facebook' => '',
                'zalo' => '',
                'youtube' => '',
            ],
            'seo' => [
                'meta_title' => '',
                'meta_description' => '',
                'robots' => 'index,follow',
                'robots_txt' => $this->crawlerFileContent->defaultRobots(),
                'ads_txt' => '',
                'gtm_id' => '',
                'meta_pixel_id' => '',
                'custom_head_tags' => '',
                'custom_script' => '',
            ],
            'custom-code' => [
                'custom_css' => '',
                'custom_css_enabled' => false,
                'custom_js' => '',
                'custom_js_enabled' => false,
            ],
            'options' => [
                'terms_of_use' => [],
                'privacy_policy' => [],
                'refund_policy' => [],
            ],
            'content-pages' => [
                'contact_page_title' => 'Liên hệ',
                'contact_page_excerpt' => '',
                'contact_page_content' => [],
                'contact_page_seo_title' => '',
                'contact_page_seo_description' => '',
                'contact_page_is_published' => true,
                'terms_page_title' => 'Điều khoản sử dụng',
                'terms_page_excerpt' => '',
                'terms_page_content' => [],
                'terms_page_seo_title' => '',
                'terms_page_seo_description' => '',
                'terms_page_is_published' => true,
                'faq_page_title' => 'Câu hỏi thường gặp',
                'faq_page_excerpt' => '',
                'faq_page_content' => [],
                'faq_page_seo_title' => '',
                'faq_page_seo_description' => '',
                'faq_page_is_published' => true,
                'privacy_page_title' => 'Chính sách bảo mật',
                'privacy_page_excerpt' => '',
                'privacy_page_content' => [],
                'privacy_page_seo_title' => '',
                'privacy_page_seo_description' => '',
                'privacy_page_is_published' => true,
                'about_page_title' => 'Giới thiệu',
                'about_page_excerpt' => '',
                'about_page_content' => [],
                'about_page_seo_title' => '',
                'about_page_seo_description' => '',
                'about_page_is_published' => true,
                'refund_policy_title' => 'Chính sách hoàn tiền',
                'refund_policy_excerpt' => '',
                'refund_policy_content' => [],
                'refund_policy_seo_title' => '',
                'refund_policy_seo_description' => '',
                'refund_policy_is_published' => true,
                'payment_policy_title' => 'Chính sách thanh toán',
                'payment_policy_excerpt' => '',
                'payment_policy_content' => [],
                'payment_policy_seo_title' => '',
                'payment_policy_seo_description' => '',
                'payment_policy_is_published' => true,
                'api_usage_policy_title' => 'Chính sách sử dụng dịch vụ',
                'api_usage_policy_excerpt' => '',
                'api_usage_policy_content' => [],
                'api_usage_policy_seo_title' => '',
                'api_usage_policy_seo_description' => '',
                'api_usage_policy_is_published' => true,
                'disclaimer_title' => 'Miễn trừ trách nhiệm',
                'disclaimer_excerpt' => '',
                'disclaimer_content' => [],
                'disclaimer_seo_title' => '',
                'disclaimer_seo_description' => '',
                'disclaimer_is_published' => true,
                'system_status_title' => 'Trạng thái hệ thống',
                'system_status_excerpt' => '',
                'system_status_content' => [],
                'system_status_seo_title' => '',
                'system_status_seo_description' => '',
                'system_status_is_published' => true,
                'system_updates_title' => 'Cập nhật hệ thống',
                'system_updates_excerpt' => '',
                'system_updates_content' => [],
                'system_updates_seo_title' => '',
                'system_updates_seo_description' => '',
                'system_updates_is_published' => true,
            ],
            'home-category' => [
                'category_ids' => [],
            ],
            'slider-images' => [
                'items' => [],
            ],
            'monitoring' => [
                'discord_webhooks' => [],
            ],
            'security' => [
                'turnstile_enabled' => false,
                'turnstile_site_key' => '',
                'turnstile_secret_key' => '',
                'turnstile_secret_configured' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultSystem(): array
    {
        return [
            ...$this->tabDefaults()['general'],
            ...$this->tabDefaults()['branding'],
            ...$this->tabDefaults()['contact'],
            ...$this->tabDefaults()['seo'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function tabStorageMap(): array
    {
        return [
            'general' => [
                'site_name' => 'site_name',
                'site_domain' => 'site_domain',
                'site_description' => 'site_description',
                'site_active' => 'site_active',
                'allow_register' => 'allow_register',
            ],
            'homepage' => [
                'home_notice_title' => 'home_notice_title',
                'home_notice_content' => 'home_notice_content',
                'home_notice_is_published' => 'home_notice_is_published',
            ],
            'popup-notice' => [
                'home_popup_title' => 'home_popup_title',
                'home_popup_content' => 'home_popup_content',
                'home_popup_is_published' => 'home_popup_is_published',
                'home_popup_display_mode' => 'home_popup_display_mode',
                'home_popup_allow_dismiss' => 'home_popup_allow_dismiss',
                'home_popup_dismiss_hours' => 'home_popup_dismiss_hours',
            ],
            'service-articles' => [
                'game_service_enabled' => 'game_service_enabled',
                'game_service_items' => 'game_service_items',
                'footer_game_links' => 'footer_game_links',
            ],
            'bio' => [
                'bio_title' => 'bio_title',
                'bio_description' => 'bio_description',
                'bio_avatar_url' => 'bio_avatar_url',
                'bio_links' => 'bio_links',
            ],
            'branding' => [
                'light_logo' => 'light_logo',
                'dark_logo' => 'dark_logo',
                'favicon' => 'favicon',
                'og_image' => 'og_image',
                'color_primary' => 'color_primary',
                'color_accent' => 'color_accent',
                'color_surface' => 'color_surface',
            ],
            'contact' => [
                'hotline' => 'hotline',
                'support_email' => 'support_email',
                'address' => 'address',
                'facebook' => 'facebook',
                'zalo' => 'zalo',
                'youtube' => 'youtube',
            ],
            'seo' => [
                'meta_title' => 'meta_title',
                'meta_description' => 'meta_description',
                'robots' => 'robots',
                'robots_txt' => 'robots_txt',
                'ads_txt' => 'ads_txt',
                'gtm_id' => 'gtm_id',
                'meta_pixel_id' => 'meta_pixel_id',
                'custom_head_tags' => 'custom_head_tags',
                'custom_script' => 'custom_script',
            ],
            'custom-code' => [
                'custom_css' => 'custom_css',
                'custom_css_enabled' => 'custom_css_enabled',
                'custom_js' => 'custom_js',
                'custom_js_enabled' => 'custom_js_enabled',
            ],
            'options' => [
                'terms_of_use' => 'terms_of_use',
                'privacy_policy' => 'privacy_policy',
                'refund_policy' => 'refund_policy',
            ],
            'content-pages' => collect($this->tabDefaults()['content-pages'])
                ->mapWithKeys(fn (mixed $value, string $field) => [$field => $field])
                ->all(),
            'home-category' => [
                'category_ids' => 'home_category_ids',
            ],
            'slider-images' => [
                'items' => 'home_slider_items',
            ],
            'monitoring' => [
                'discord_webhooks' => 'discord_webhooks',
            ],
            'security' => [
                'turnstile_enabled' => 'turnstile_enabled',
                'turnstile_site_key' => 'turnstile_site_key',
                'turnstile_secret_key' => 'turnstile_secret_key',
            ],
        ];
    }

    protected function tabExists(string $tab): bool
    {
        return array_key_exists($tab, $this->tabDefaults());
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, string>  $storageMap
     * @return array<string, mixed>
     */
    protected function readTab(SettingStore $settingStore, array $defaults, array $storageMap): array
    {
        $storedValues = $settingStore->getMany(
            collect($storageMap)->mapWithKeys(fn (string $storageKey, string $field) => [$storageKey => $defaults[$field]])->all(),
        );

        $resolved = [];

        foreach ($storageMap as $field => $storageKey) {
            $resolved[$field] = $storedValues[$storageKey] ?? $defaults[$field];
        }

        return [
            ...$defaults,
            ...$resolved,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $storageMap
     */
    protected function writeTab(SettingStore $settingStore, array $payload, array $storageMap): void
    {
        $settingStore->putMany(
            collect($storageMap)->mapWithKeys(fn (string $storageKey, string $field) => [$storageKey => $payload[$field] ?? null])->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function systemSettings(SettingStore $settingStore): array
    {
        $general = $this->readTab($settingStore, $this->tabDefaults()['general'], $this->tabStorageMap()['general']);
        $branding = $this->readTab($settingStore, $this->tabDefaults()['branding'], $this->tabStorageMap()['branding']);
        $contact = $this->readTab($settingStore, $this->tabDefaults()['contact'], $this->tabStorageMap()['contact']);
        $seo = $this->readTab($settingStore, $this->tabDefaults()['seo'], $this->tabStorageMap()['seo']);

        return $this->withLocalCrawlerFileSettings([
            ...$this->defaultSystem(),
            ...$general,
            ...$branding,
            ...$contact,
            ...$seo,
        ]);
    }

    public function show(string $tab, SettingStore $settingStore): JsonResponse
    {
        $this->assertTabAllowed($tab);
        if ($tab === self::SYSTEM_TAB) {
            return response()->json([
                'status' => true,
                'data' => [
                    'tab' => $tab,
                    'settings' => $this->systemSettings($settingStore),
                ],
            ]);
        }

        abort_if(! $this->tabExists($tab), 404);

        if ($tab === 'security') {
            return response()->json([
                'status' => true,
                'data' => [
                    'tab' => $tab,
                    'settings' => $this->securitySettings($settingStore),
                ],
            ]);
        }

        $settings = $this->readTab($settingStore, $this->tabDefaults()[$tab], $this->tabStorageMap()[$tab]);
        if ($tab === 'seo') {
            $settings = $this->withLocalCrawlerFileSettings($settings);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'tab' => $tab,
                'settings' => $settings,
            ],
        ]);
    }

    public function update(
        string $tab,
        UpdateTabSettingRequest $request,
        SettingStore $settingStore,
        UpdateCustomCodeSettingsAction $updateCustomCodeSettings,
    ): JsonResponse {
        $this->assertTabAllowed($tab);
        abort_if($tab === self::SYSTEM_TAB || $tab === 'options', 404);
        abort_if(! $this->tabExists($tab), 404);

        $validated = $request->validated();

        if ($tab === 'custom-code') {
            $settings = $updateCustomCodeSettings->execute($request->user(), $validated, $request);

            return response()->json([
                'status' => true,
                'message' => 'Cập nhật mã tùy chỉnh thành công.',
                'data' => [
                    'tab' => $tab,
                    'settings' => $settings,
                ],
            ]);
        }

        if ($tab === 'security') {
            $secretKey = trim((string) ($validated['turnstile_secret_key'] ?? ''));

            if ((bool) $validated['turnstile_enabled'] && $secretKey === '' && $settingStore->getString('turnstile_secret_key') === '') {
                throw ValidationException::withMessages([
                    'turnstile_secret_key' => 'Vui lòng nhập Secret Key trước khi bật Cloudflare Turnstile.',
                ]);
            }

            $settingStore->putMany([
                'turnstile_enabled' => (bool) $validated['turnstile_enabled'],
                'turnstile_site_key' => trim((string) ($validated['turnstile_site_key'] ?? '')),
            ]);

            if ($secretKey !== '') {
                $settingStore->putEncryptedString('turnstile_secret_key', $secretKey);
            }

            return response()->json([
                'status' => true,
                'message' => 'Cập nhật Cloudflare Turnstile thành công.',
                'data' => [
                    'tab' => $tab,
                    'settings' => $this->securitySettings($settingStore),
                ],
            ]);
        }

        $storageMap = Arr::only($this->tabStorageMap()[$tab], array_keys($validated));
        $this->writeTab($settingStore, $validated, $storageMap);
        $settings = $this->readTab($settingStore, $this->tabDefaults()[$tab], $this->tabStorageMap()[$tab]);
        if ($tab === 'seo') {
            $settings = $this->withLocalCrawlerFileSettings($settings);
        }

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật cấu hình thành công.',
            'data' => [
                'tab' => $tab,
                'settings' => $settings,
            ],
        ]);
    }

    public function updateSystem(UpdateSystemSettingRequest $request, SettingStore $settingStore): JsonResponse
    {
        $payload = $request->validated();

        foreach (['general', 'branding', 'contact', 'seo'] as $tab) {
            $tabPayload = Arr::only($payload, array_keys($this->tabDefaults()[$tab]));
            $storageMap = Arr::only($this->tabStorageMap()[$tab], array_keys($tabPayload));
            $this->writeTab($settingStore, $tabPayload, $storageMap);
        }

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật cấu hình hệ thống thành công.',
            'data' => [
                'tab' => self::SYSTEM_TAB,
                'settings' => $this->systemSettings($settingStore),
            ],
        ]);
    }

    public function updateOptions(UpdateOptionSettingRequest $request, SettingStore $settingStore): JsonResponse
    {
        $payload = [
            ...$this->tabDefaults()['options'],
            ...Arr::only($request->validated(), ['terms_of_use', 'privacy_policy', 'refund_policy']),
        ];

        $this->writeTab($settingStore, $payload, $this->tabStorageMap()['options']);

        return response()->json([
            'status' => true,
            'message' => 'Cập nhật nội dung pháp lý thành công.',
            'data' => [
                'tab' => 'options',
                'settings' => $payload,
            ],
        ]);
    }

    /**
     * @return array{turnstile_enabled: bool, turnstile_site_key: string, turnstile_secret_key: string, turnstile_secret_configured: bool}
     */
    private function securitySettings(SettingStore $settingStore): array
    {
        $secretKey = $settingStore->getString('turnstile_secret_key');

        return [
            'turnstile_enabled' => (bool) $settingStore->get('turnstile_enabled', false),
            'turnstile_site_key' => $settingStore->getString('turnstile_site_key'),
            'turnstile_secret_key' => '',
            'turnstile_secret_configured' => $secretKey !== '',
        ];
    }

    private function assertTabAllowed(string $tab): void
    {
        if (Site::isMain()) {
            return;
        }

        abort_unless(in_array($tab, [
            'system', 'general', 'homepage', 'popup-notice', 'service-articles', 'bio', 'branding',
            'contact', 'seo', 'options', 'content-pages', 'slider-images',
        ], true), 403, 'Website đại lý không được thay đổi cấu hình hệ thống này.');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function withLocalCrawlerFileSettings(array $settings): array
    {
        return [
            ...$settings,
            'robots_txt' => $this->crawlerFileContent->robotsForEditing(),
            'ads_txt' => $this->crawlerFileContent->adsForEditing(),
        ];
    }
}
