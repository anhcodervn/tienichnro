<?php

namespace App\Features\Admin\GameService\Requests;

use App\Models\GameServiceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(GameServiceOrder::STATUSES)],
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
