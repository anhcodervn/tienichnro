<?php

namespace App\Features\Admin\Affiliate\Requests;

use App\Exceptions\ApiException;
use App\Rules\ValidAffiliateAnnouncementContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SaveAffiliateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'array', new ValidAffiliateAnnouncementContent],
            'is_pinned' => ['required', 'boolean'],
            'is_published' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }
}
