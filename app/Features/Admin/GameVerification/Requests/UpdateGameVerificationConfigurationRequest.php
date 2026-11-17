<?php

namespace App\Features\Admin\GameVerification\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGameVerificationConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active' && $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return ['price' => ['required', 'integer', 'between:0,100000000']];
    }
}
