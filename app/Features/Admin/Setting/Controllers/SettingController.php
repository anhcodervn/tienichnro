<?php

namespace App\Features\Admin\Setting\Controllers;

use App\Features\Admin\Setting\Requests\UpdateOptionSettingRequest;
use App\Features\Admin\Setting\Requests\UpdateSystemSettingRequest;
use App\Features\Admin\Setting\Requests\UpdateTabSettingRequest;
use App\Http\Controllers\Controller;
use App\Support\SettingStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class SettingController extends Controller
{
    protected const SYSTEM_TAB = 'system';

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
                'gtm_id' => '',
                'meta_pixel_id' => '',
                'custom_script' => '',
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
                'gtm_id' => 'gtm_id',
                'meta_pixel_id' => 'meta_pixel_id',
                'custom_script' => 'custom_script',
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

        return [
            ...$this->defaultSystem(),
            ...$general,
            ...$branding,
            ...$contact,
            ...$seo,
        ];
    }

    public function show(string $tab, SettingStore $settingStore): JsonResponse
    {
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

        $settings = $this->readTab($settingStore, $this->tabDefaults()[$tab], $this->tabStorageMap()[$tab]);

        return response()->json([
            'status' => true,
            'data' => [
                'tab' => $tab,
                'settings' => $settings,
            ],
        ]);
    }

    public function update(string $tab, UpdateTabSettingRequest $request, SettingStore $settingStore): JsonResponse
    {
        abort_if($tab === self::SYSTEM_TAB || $tab === 'options', 404);
        abort_if(! $this->tabExists($tab), 404);

        $validated = $request->validated();
        $storageMap = Arr::only($this->tabStorageMap()[$tab], array_keys($validated));
        $this->writeTab($settingStore, $validated, $storageMap);
        $settings = $this->readTab($settingStore, $this->tabDefaults()[$tab], $this->tabStorageMap()[$tab]);

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
}
