<?php

namespace App\Features\Admin\Seo\Requests;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Rules\ValidSeoPostContent;
use App\Support\SafeNavigationUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSeoPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role === 'admin';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $postId = $this->route('seoPost')?->id ?? $this->route('seoPost');

        return [
            'type' => ['required', Rule::in(['knowledge', 'guide', 'price'])],
            'service_id' => [
                Rule::requiredIf($this->input('type') === 'price'),
                'nullable',
                'integer',
                'exists:games,id',
                Rule::prohibitedIf($this->input('type') !== 'price'),
            ],
            'seo_category_id' => [
                Rule::requiredIf(in_array($this->input('status'), ['published', 'scheduled'], true)),
                'nullable',
                'exists:seo_categories,id',
            ],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('seo_posts', 'slug')->ignore($postId),
            ],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'array', new ValidSeoPostContent],
            'faq' => ['nullable', 'array', 'max:20'],
            'faq.*.question' => ['required', 'string', 'max:255'],
            'faq.*.answer' => ['required', 'string', 'max:2000'],
            'cover_image' => ['nullable', 'string', 'max:2048', $this->safeCoverImageRule()],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'canonical_url' => ['nullable', 'string', 'max:2048', $this->absoluteHttpUrlRule()],
            'robots' => ['required', Rule::in(['index,follow', 'noindex,follow'])],
            'focus_keyword' => ['nullable', 'string', 'max:255'],
            'cover_alt' => [Rule::requiredIf($this->filled('cover_image')), 'nullable', 'string', 'max:255'],
            'article_schema' => ['sometimes', 'boolean'],
            'breadcrumb_schema' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published', 'scheduled'])],
            'published_at' => ['nullable', 'date'],
            'scheduled_at' => [Rule::requiredIf($this->input('status') === 'scheduled'), 'nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug chỉ được chứa chữ thường, số và dấu gạch nối.',
            'service_id.required' => 'Page bảng giá phải được liên kết với một dịch vụ.',
            'service_id.prohibited' => 'Chỉ page bảng giá mới được liên kết với dịch vụ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'loại page SEO',
            'service_id' => 'dịch vụ',
            'seo_category_id' => 'danh mục SEO',
            'title' => 'tiêu đề bài viết',
            'slug' => 'slug',
            'excerpt' => 'mô tả ngắn',
            'content' => 'nội dung chính',
            'faq' => 'câu hỏi thường gặp',
            'faq.*.question' => 'câu hỏi FAQ',
            'faq.*.answer' => 'câu trả lời FAQ',
            'cover_image' => 'ảnh đại diện',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'canonical_url' => 'canonical URL',
            'robots' => 'robots',
            'focus_keyword' => 'focus keyword',
            'cover_alt' => 'alt text ảnh đại diện',
            'article_schema' => 'schema bài viết',
            'breadcrumb_schema' => 'schema breadcrumb',
            'status' => 'trạng thái',
            'published_at' => 'thời gian publish',
            'scheduled_at' => 'thời gian hẹn lịch',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'type' => $this->input('type', 'knowledge'),
        ];

        foreach (['title', 'slug', 'excerpt', 'cover_image', 'cover_alt', 'seo_title', 'seo_description', 'canonical_url', 'focus_keyword'] as $field) {
            if (! $this->exists($field) || ! is_string($this->input($field))) {
                continue;
            }

            $value = trim((string) $this->input($field));
            $normalized[$field] = $value === '' ? null : $value;
        }

        $this->merge($normalized);
    }

    private function safeCoverImageRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && ! SafeNavigationUrl::passes($value)) {
                $fail('Ảnh đại diện phải là URL http/https hoặc đường dẫn nội bộ hợp lệ.');
            }
        };
    }

    private function absoluteHttpUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }

            $scheme = parse_url((string) $value, PHP_URL_SCHEME);
            $isValid = filter_var($value, FILTER_VALIDATE_URL) !== false
                && is_string($scheme)
                && in_array(strtolower($scheme), ['http', 'https'], true);

            if (! $isValid) {
                $fail('Canonical URL phải là địa chỉ đầy đủ bắt đầu bằng http:// hoặc https://.');
            }
        };
    }
}
