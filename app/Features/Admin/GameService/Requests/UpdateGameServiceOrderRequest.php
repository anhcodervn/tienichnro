<?php

namespace App\Features\Admin\GameService\Requests;

use App\Models\GameServiceOrder;
use App\Models\User;
use Closure;
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
            'collaborator_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && ! User::query()
                        ->whereKey((int) $value)
                        ->where('role', User::ROLE_COLLABORATOR)
                        ->where('status', 'active')
                        ->exists()) {
                        $fail('CTV được chọn không hoạt động hoặc không hợp lệ.');
                    }
                },
            ],
        ];
    }
}
