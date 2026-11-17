<?php

namespace App\Features\Client\GameVerification\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportGameVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return ['ids' => ['required', 'array', 'list', 'min:1', 'max:100'], 'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'format' => ['required', Rule::in(['sms', 'password'])],
            'new_password' => ['nullable', 'required_if:format,password', 'string', 'min:6', 'max:200', 'regex:/^[^\r\n|]+$/']];
    }
}
