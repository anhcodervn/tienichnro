<?php

namespace App\Features\Client\Profile\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RotateLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('rotate', $this->route('license')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['confirm_rotation' => ['required', 'accepted']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirm_rotation.required' => 'Vui lòng xác nhận trước khi đổi key.',
            'confirm_rotation.accepted' => 'Vui lòng xác nhận trước khi đổi key.',
        ];
    }
}
