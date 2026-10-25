<?php

namespace App\Features\Admin\License\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadClientGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:32', 'regex:/\A\d+\.\d+\.\d+(?:\.\d+)?\z/'],
            'zip' => ['required', 'file', 'mimes:zip', 'extensions:zip', 'max:102400'],
        ];
    }
}
