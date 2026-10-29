<?php

namespace App\Features\Admin\Setting\Requests;

use App\Features\Admin\Seo\Requests\UpsertSeoPostRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateToolSeoRequest extends UpsertSeoPostRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = Arr::except(parent::rules(), ['type', 'service_id', 'seo_category_id', 'slug', 'canonical_url']);
        $rules['is_published'] = ['required', 'boolean'];
        $rules['expected_version'] = ['required', 'integer', 'min:0'];
        $rules['title'] = [Rule::requiredIf($this->boolean('is_published')), 'nullable', 'string', 'max:255'];
        array_unshift($rules['content'], Rule::requiredIf($this->boolean('is_published')));
        foreach (['slug', 'canonical_url', 'type', 'service_id', 'seo_category_id'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'is_published' => 'trạng thái xuất bản', 'expected_version' => 'phiên bản bài SEO'];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['title', 'excerpt', 'cover_image', 'cover_alt', 'seo_title', 'seo_description', 'meta_keywords', 'focus_keyword'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim($this->input($field)) ?: null;
            }
        }
        $this->merge($normalized);
    }
}
