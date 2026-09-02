<?php

namespace App\Features\Admin\Topup\Requests;

use App\Models\Game;
use App\Models\TopupProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTopupPackageRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'game_id' => ['nullable', 'integer', Rule::exists(Game::class, 'id')],
            'game_server_id' => ['prohibited'],
            'provider_id' => ['nullable', 'integer', Rule::exists(TopupProvider::class, 'id')],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'gte:min_price'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
