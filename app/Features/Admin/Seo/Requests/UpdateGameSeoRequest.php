<?php

namespace App\Features\Admin\Seo\Requests;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Rules\ValidSeoPostContent;
use App\Support\SafeNavigationUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameSeoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->role === 'admin';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'meta_keywords' => ['nullable', 'string', 'max:1000'],
            'h1' => ['nullable', 'string', 'max:255'],
            'article_title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'array', new ValidSeoPostContent],
            'og_image' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && ! SafeNavigationUrl::passes($value)) {
                    $fail('Ảnh Open Graph phải là URL hoặc đường dẫn ảnh hợp lệ.');
                }
            }],
            'og_image_alt' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $scheme = is_string($value) ? parse_url($value, PHP_URL_SCHEME) : null;
                if (is_string($value) && (filter_var($value, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) $scheme), ['http', 'https'], true))) {
                    $fail('Canonical phải là URL đầy đủ bắt đầu bằng http:// hoặc https://.');
                }
            }],
            'robots' => ['sometimes', 'required', Rule::in(['index,follow', 'noindex,follow'])],
            'faqs' => ['nullable', 'array', 'max:20'],
            'faqs.*.question' => ['required', 'string', 'max:255'],
            'faqs.*.answer' => ['required', 'string', 'max:2000'],
            'is_published' => ['sometimes', 'required', 'boolean'],
            'breadcrumb_schema' => ['sometimes', 'required', 'boolean'],
            'webpage_schema' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'meta_title.max' => 'Tiêu đề SEO không được vượt quá 255 ký tự.',
            'meta_description.max' => 'Mô tả SEO không được vượt quá 320 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'meta_title' => 'tiêu đề SEO',
            'meta_description' => 'mô tả SEO',
            'meta_keywords' => 'từ khóa SEO',
            'h1' => 'tiêu đề H1',
            'article_title' => 'tiêu đề bài SEO',
            'content' => 'bài SEO game',
            'og_image' => 'ảnh Open Graph',
            'og_image_alt' => 'mô tả ảnh Open Graph',
            'canonical_url' => 'canonical URL',
            'robots' => 'robots',
            'faqs' => 'FAQ',
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->containsHeadingOne($this->input('content', []))) {
                $validator->errors()->add('content', 'Bài SEO không được chứa H1 vì trang đã có một tiêu đề H1 riêng.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['meta_title', 'meta_description', 'meta_keywords', 'h1', 'article_title', 'og_image', 'og_image_alt', 'canonical_url'] as $field) {
            if (! $this->exists($field) || ! is_string($this->input($field))) {
                continue;
            }

            $value = trim((string) $this->input($field));
            $normalized[$field] = $value === '' ? null : $value;
        }

        $this->merge($normalized);
    }

    private function containsHeadingOne(mixed $nodes): bool
    {
        if (! is_array($nodes)) {
            return false;
        }

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (($node['type'] ?? null) === 'heading' && (int) ($node['level'] ?? 0) === 1) {
                return true;
            }

            if ($this->containsHeadingOne($node['children'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }
}
