<?php

namespace App\Features\Admin\Seo\Requests;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Rules\ValidSeoPostContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHomeSeoRequest extends FormRequest
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
        $isPublished = $this->boolean('is_published');

        return [
            'meta_title' => [Rule::requiredIf($isPublished), 'nullable', 'string', 'max:255'],
            'meta_description' => [Rule::requiredIf($isPublished), 'nullable', 'string', 'max:320'],
            'h1' => [Rule::requiredIf($isPublished), 'nullable', 'string', 'max:255'],
            'article_title' => [Rule::requiredIf($isPublished), 'nullable', 'string', 'max:255'],
            'content' => [Rule::requiredIf($isPublished), 'nullable', 'array', new ValidSeoPostContent],
            'faqs' => ['nullable', 'array', 'max:20'],
            'faqs.*.question' => ['required', 'string', 'max:255'],
            'faqs.*.answer' => ['required', 'string', 'max:2000'],
            'is_published' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Nội dung bài SEO là bắt buộc khi xuất bản.',
        ];
    }

    public function attributes(): array
    {
        return [
            'meta_title' => 'meta title trang chủ',
            'meta_description' => 'meta description trang chủ',
            'h1' => 'tiêu đề H1 trang chủ',
            'article_title' => 'tiêu đề bài hướng dẫn',
            'content' => 'nội dung bài SEO',
            'faqs' => 'FAQ trang chủ',
            'faqs.*.question' => 'câu hỏi FAQ',
            'faqs.*.answer' => 'câu trả lời FAQ',
            'is_published' => 'trạng thái xuất bản',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['meta_title', 'meta_description', 'h1', 'article_title'] as $field) {
            if (! $this->exists($field) || ! is_string($this->input($field))) {
                continue;
            }

            $value = trim((string) $this->input($field));
            $normalized[$field] = $value === '' ? null : $value;
        }

        $this->merge($normalized);
    }
}
