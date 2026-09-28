<?php

namespace App\Features\Admin\Upload\Requests;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ImportImageUploadRequest extends FormRequest
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
        return [
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'url.required' => 'URL ảnh là bắt buộc.',
            'url.url' => 'URL ảnh phải sử dụng giao thức HTTP hoặc HTTPS.',
            'url.max' => 'URL ảnh không được vượt quá 2048 ký tự.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['url' => 'URL ảnh'];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422, [
            'errors' => $validator->errors()->toArray(),
        ]);
    }
}
