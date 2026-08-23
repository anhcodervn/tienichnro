<?php

namespace App\Features\Admin\Setting\Requests;

use App\Models\User;
use App\Rules\ValidHomepageNoticeContent;
use App\Support\SafeNavigationUrl;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateTabSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role === 'admin';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return match ((string) $this->route('tab')) {
            'general' => [
                'site_name' => ['required', 'string', 'max:190'],
                'site_domain' => ['nullable', 'string', 'max:190'],
                'site_description' => ['nullable', 'string', 'max:2000'],
                'site_active' => ['required', 'boolean'],
                'allow_register' => ['required', 'boolean'],
            ],
            'homepage' => [
                'home_notice_title' => ['required', 'string', 'max:255'],
                'home_notice_content' => ['present', 'array', new ValidHomepageNoticeContent],
                'home_notice_is_published' => ['required', 'boolean'],
            ],
            'service-articles' => [
                'game_service_enabled' => ['required', 'boolean'],
                'game_service_items' => [
                    'present',
                    'array',
                    'max:20',
                    function (string $attribute, mixed $value, Closure $fail): void {
                        if ($this->boolean('game_service_enabled') && is_array($value) && $value === []) {
                            $fail('Vui lòng thêm ít nhất một dịch vụ game trước khi bật hiển thị.');
                        }
                    },
                ],
                'game_service_items.*' => ['required', 'array:label,url'],
                'game_service_items.*.label' => ['required', 'string', 'max:80', 'not_regex:/[\x00-\x1F\x7F]/u'],
                'game_service_items.*.url' => ['required', 'string', 'max:2048', 'distinct:strict', $this->safeServiceUrlRule()],
            ],
            'branding' => [
                'light_logo' => ['nullable', 'string', 'max:2048'],
                'dark_logo' => ['nullable', 'string', 'max:2048'],
                'favicon' => ['nullable', 'string', 'max:2048'],
                'og_image' => ['nullable', 'string', 'max:2048'],
                'color_primary' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6})$/'],
                'color_accent' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6})$/'],
                'color_surface' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6})$/'],
            ],
            'contact' => [
                'hotline' => ['nullable', 'string', 'max:30'],
                'support_email' => ['nullable', 'email', 'max:190'],
                'address' => ['nullable', 'string', 'max:500'],
                'facebook' => ['nullable', 'string', 'max:255'],
                'zalo' => ['nullable', 'string', 'max:255'],
                'youtube' => ['nullable', 'string', 'max:255'],
            ],
            'seo' => [
                'meta_title' => ['nullable', 'string', 'max:255'],
                'meta_description' => ['nullable', 'string', 'max:1000'],
                'robots' => ['nullable', 'string', 'max:100'],
                'gtm_id' => ['nullable', 'string', 'max:100'],
                'meta_pixel_id' => ['nullable', 'string', 'max:100'],
            ],
            'custom-code' => [
                'custom_css' => ['sometimes', 'nullable', 'string', 'max:100000'],
                'custom_css_enabled' => ['sometimes', 'boolean'],
                'custom_js' => ['sometimes', 'nullable', 'string', 'max:100000'],
                'custom_js_enabled' => ['sometimes', 'boolean'],
            ],
            'content-pages' => $this->contentPageRules(),
            'home-category' => [
                'category_ids' => ['nullable', 'array'],
                'category_ids.*' => ['integer'],
            ],
            'slider-images' => [
                'items' => ['nullable', 'array'],
            ],
            'monitoring' => [
                'discord_webhooks' => ['nullable', 'array'],
                'discord_webhooks.*.name' => ['required', 'string', 'max:120'],
                'discord_webhooks.*.url' => ['required', 'url', 'max:2048'],
                'discord_webhooks.*.is_active' => ['required', 'boolean'],
                'discord_webhooks.*.events' => ['nullable', 'array'],
                'discord_webhooks.*.events.*' => ['string', 'in:test_ping,user_registered,recharge_success'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function contentPageRules(): array
    {
        $pages = [
            'contact_page',
            'terms_page',
            'faq_page',
            'privacy_page',
            'about_page',
            'refund_policy',
            'payment_policy',
            'api_usage_policy',
            'disclaimer',
            'system_status',
            'system_updates',
        ];

        $rules = [];

        foreach ($pages as $page) {
            $rules["{$page}_title"] = ['required', 'string', 'max:255'];
            $rules["{$page}_excerpt"] = ['nullable', 'string', 'max:1000'];
            $rules["{$page}_content"] = ['nullable', 'array'];
            $rules["{$page}_seo_title"] = ['nullable', 'string', 'max:255'];
            $rules["{$page}_seo_description"] = ['nullable', 'string', 'max:1000'];
            $rules["{$page}_is_published"] = ['required', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_name.required' => 'Vui lòng nhập tên hệ thống.',
            'support_email.email' => 'Email hỗ trợ không đúng định dạng.',
            'color_primary.regex' => 'Màu chính phải đúng mã HEX.',
            'color_accent.regex' => 'Màu nhấn phải đúng mã HEX.',
            'color_surface.regex' => 'Màu nền phải đúng mã HEX.',
            'game_service_items.max' => 'Chỉ được cấu hình tối đa 20 dịch vụ game.',
            'game_service_items.*.label.required' => 'Vui lòng nhập tên dịch vụ.',
            'game_service_items.*.label.max' => 'Tên dịch vụ không được vượt quá 80 ký tự.',
            'game_service_items.*.url.required' => 'Vui lòng nhập liên kết dịch vụ.',
            'game_service_items.*.url.distinct' => 'Liên kết dịch vụ không được trùng nhau.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'site_name' => 'tên hệ thống',
            'site_domain' => 'domain website',
            'site_description' => 'mô tả hệ thống',
            'site_active' => 'trạng thái website',
            'allow_register' => 'trạng thái đăng ký',
            'game_service_enabled' => 'trạng thái dịch vụ game',
            'game_service_items' => 'danh sách dịch vụ game',
            'game_service_items.*.label' => 'tên dịch vụ',
            'game_service_items.*.url' => 'liên kết dịch vụ',
            'light_logo' => 'logo nền tối',
            'dark_logo' => 'logo nền sáng',
            'favicon' => 'favicon',
            'og_image' => 'ảnh chia sẻ',
            'hotline' => 'hotline',
            'support_email' => 'email hỗ trợ',
            'address' => 'địa chỉ',
            'facebook' => 'liên kết Facebook',
            'zalo' => 'liên kết Zalo',
            'youtube' => 'liên kết YouTube',
            'meta_title' => 'meta title',
            'meta_description' => 'meta description',
            'robots' => 'robots',
            'gtm_id' => 'Google Tag Manager ID',
            'meta_pixel_id' => 'Meta Pixel ID',
            'custom_css' => 'CSS tùy chỉnh',
            'custom_css_enabled' => 'trạng thái CSS tùy chỉnh',
            'custom_js' => 'JavaScript tùy chỉnh',
            'custom_js_enabled' => 'trạng thái JavaScript tùy chỉnh',
            'category_ids' => 'danh mục trang chủ',
            'items' => 'danh sách slider',
            'discord_webhooks' => 'danh sách webhook Discord',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
            'data' => [
                'errors' => $validator->errors(),
            ],
        ], 422));
    }

    protected function prepareForValidation(): void
    {
        if ((string) $this->route('tab') !== 'service-articles' || ! $this->exists('game_service_items')) {
            return;
        }

        $items = $this->input('game_service_items');

        if (! is_array($items)) {
            return;
        }

        $this->merge([
            'game_service_items' => array_map(static function (mixed $item): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                return [
                    ...$item,
                    'label' => is_string($item['label'] ?? null) ? trim($item['label']) : ($item['label'] ?? null),
                    'url' => is_string($item['url'] ?? null) ? trim($item['url']) : ($item['url'] ?? null),
                ];
            }, $items),
        ]);
    }

    private function safeServiceUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! SafeNavigationUrl::passes($value)) {
                $fail('Liên kết dịch vụ game phải là URL http/https hoặc đường dẫn nội bộ bắt đầu bằng /.');
            }
        };
    }
}
