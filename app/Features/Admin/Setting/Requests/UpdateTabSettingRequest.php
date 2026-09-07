<?php

namespace App\Features\Admin\Setting\Requests;

use App\Models\User;
use App\Rules\ValidAdsTxt;
use App\Rules\ValidCustomHeadTags;
use App\Rules\ValidHomepageNoticeContent;
use App\Rules\ValidRobotsTxt;
use App\Support\SafeNavigationUrl;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

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
            'popup-notice' => [
                'home_popup_title' => ['required', 'string', 'max:255'],
                'home_popup_content' => [
                    'present',
                    'array',
                    new ValidHomepageNoticeContent,
                    function (string $attribute, mixed $value, Closure $fail): void {
                        if ($this->boolean('home_popup_is_published') && is_array($value) && $value === []) {
                            $fail('Vui lòng nhập nội dung trước khi bật thông báo popup.');
                        }
                    },
                ],
                'home_popup_is_published' => ['required', 'boolean'],
                'home_popup_display_mode' => ['required', Rule::in(['modal', 'popup'])],
                'home_popup_allow_dismiss' => ['required', 'boolean'],
                'home_popup_dismiss_hours' => ['required', 'integer', 'between:1,8760'],
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
            'bio' => [
                'bio_title' => ['required', 'string', 'max:120', 'not_regex:/[\x00-\x1F\x7F]/u'],
                'bio_description' => ['nullable', 'string', 'max:500'],
                'bio_avatar_url' => ['nullable', 'string', 'max:2048', $this->safeBioUrlRule()],
                'bio_links' => ['present', 'array', 'max:20'],
                'bio_links.*' => ['required', 'array:label,url,icon,is_active'],
                'bio_links.*.label' => ['required', 'string', 'max:80', 'not_regex:/[\x00-\x1F\x7F]/u'],
                'bio_links.*.url' => ['required', 'string', 'max:2048', 'distinct:strict', $this->safeBioUrlRule()],
                'bio_links.*.icon' => ['required', 'string', Rule::in($this->bioIconKeys())],
                'bio_links.*.is_active' => ['required', 'boolean'],
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
                'robots' => ['required', Rule::in(['index,follow', 'noindex,follow', 'noindex,nofollow'])],
                'robots_txt' => ['nullable', 'string', 'max:20000', new ValidRobotsTxt],
                'ads_txt' => ['nullable', 'string', 'max:100000', new ValidAdsTxt],
                'gtm_id' => ['nullable', 'string', 'max:100', 'regex:/\AGTM-[A-Z0-9]+\z/'],
                'meta_pixel_id' => ['nullable', 'string', 'max:100', 'regex:/\A[0-9]+\z/'],
                'custom_head_tags' => ['nullable', 'string', 'max:20000', new ValidCustomHeadTags],
                'custom_script' => ['nullable', 'string', 'max:100000'],
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
            'security' => [
                'turnstile_enabled' => ['required', 'boolean'],
                'turnstile_site_key' => ['nullable', 'string', 'max:255', 'required_if:turnstile_enabled,true'],
                'turnstile_secret_key' => ['nullable', 'string', 'max:512'],
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
            'bio_links.max' => 'Chỉ được cấu hình tối đa 20 liên kết bio.',
            'bio_links.*.label.required' => 'Vui lòng nhập tên liên kết bio.',
            'bio_links.*.url.required' => 'Vui lòng nhập URL liên kết bio.',
            'bio_links.*.url.distinct' => 'URL liên kết bio không được trùng nhau.',
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
            'bio_title' => 'tiêu đề trang bio',
            'bio_description' => 'mô tả trang bio',
            'bio_avatar_url' => 'ảnh đại diện trang bio',
            'bio_links' => 'danh sách liên kết bio',
            'bio_links.*.label' => 'tên liên kết bio',
            'bio_links.*.url' => 'URL liên kết bio',
            'bio_links.*.icon' => 'biểu tượng liên kết bio',
            'bio_links.*.is_active' => 'trạng thái liên kết bio',
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
            'robots_txt' => 'nội dung robots.txt',
            'ads_txt' => 'nội dung ads.txt',
            'gtm_id' => 'Google Tag Manager ID',
            'meta_pixel_id' => 'Meta Pixel ID',
            'custom_head_tags' => 'thẻ meta tùy chỉnh',
            'custom_script' => 'script tùy chỉnh',
            'custom_css' => 'CSS tùy chỉnh',
            'custom_css_enabled' => 'trạng thái CSS tùy chỉnh',
            'custom_js' => 'JavaScript tùy chỉnh',
            'custom_js_enabled' => 'trạng thái JavaScript tùy chỉnh',
            'category_ids' => 'danh mục trang chủ',
            'items' => 'danh sách slider',
            'discord_webhooks' => 'danh sách webhook Discord',
            'turnstile_enabled' => 'trạng thái Cloudflare Turnstile',
            'turnstile_site_key' => 'Turnstile Site Key',
            'turnstile_secret_key' => 'Turnstile Secret Key',
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
        if ((string) $this->route('tab') === 'seo') {
            $this->merge([
                'robots_txt' => $this->normalizeTextFile($this->input('robots_txt')),
                'ads_txt' => $this->normalizeTextFile($this->input('ads_txt')),
            ]);

            return;
        }

        if ((string) $this->route('tab') === 'bio') {
            $this->prepareBioSettings();

            return;
        }

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

    private function normalizeTextFile(mixed $value): mixed
    {
        return is_string($value) ? trim(str_replace(["\r\n", "\r"], "\n", $value)) : $value;
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

    private function safeBioUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && $value !== '' && ! SafeNavigationUrl::passes($value)) {
                $fail('Liên kết bio phải là URL http/https hoặc đường dẫn nội bộ bắt đầu bằng /.');
            }
        };
    }

    private function prepareBioSettings(): void
    {
        $links = $this->input('bio_links');

        if (! is_array($links)) {
            return;
        }

        $this->merge([
            'bio_title' => is_string($this->input('bio_title')) ? trim($this->input('bio_title')) : $this->input('bio_title'),
            'bio_description' => is_string($this->input('bio_description')) ? trim($this->input('bio_description')) : $this->input('bio_description'),
            'bio_avatar_url' => is_string($this->input('bio_avatar_url')) ? trim($this->input('bio_avatar_url')) : $this->input('bio_avatar_url'),
            'bio_links' => array_map(static function (mixed $link): mixed {
                if (! is_array($link)) {
                    return $link;
                }

                return [
                    ...$link,
                    'label' => is_string($link['label'] ?? null) ? trim($link['label']) : ($link['label'] ?? null),
                    'url' => is_string($link['url'] ?? null) ? trim($link['url']) : ($link['url'] ?? null),
                    'icon' => $link['icon'] ?? 'link',
                ];
            }, $links),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function bioIconKeys(): array
    {
        return [
            'link',
            'website',
            'facebook',
            'messenger',
            'youtube',
            'discord',
            'telegram',
            'tiktok',
            'instagram',
            'zalo',
            'email',
            'phone',
            'store',
            'community',
        ];
    }
}
