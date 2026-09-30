<?php

namespace App\Features\Admin\GameService\Requests;

use App\Models\Game;
use App\Models\GameService;
use App\Models\GameServiceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListGameServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'game_id' => ['nullable', 'integer', Rule::exists(Game::class, 'id')],
            'game_service_id' => ['nullable', 'integer', Rule::exists(GameService::class, 'id')],
            'status' => ['nullable', Rule::in(GameServiceOrder::STATUSES)],
            'exclude_status' => ['nullable', Rule::in(GameServiceOrder::STATUSES)],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
