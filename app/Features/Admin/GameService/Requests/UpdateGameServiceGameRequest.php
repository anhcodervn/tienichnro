<?php

namespace App\Features\Admin\GameService\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGameServiceGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'game_services_enabled' => ['required', 'boolean'],
            'provider_service_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
        ];
    }
}
