<?php

namespace App\Features\Admin\User\Requests;

use App\Exceptions\ApiException;
use App\Models\GameService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserGameServicePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'game_service_ids' => ['required', 'array'],
            'game_service_ids.*' => ['integer', 'distinct', Rule::exists(GameService::class, 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'game_service_ids.required' => 'Vui lòng gửi danh sách dịch vụ được phép.',
            'game_service_ids.*.exists' => 'Dịch vụ game được chọn không tồn tại.',
        ];
    }

    public function attributes(): array
    {
        return [
            'game_service_ids' => 'danh sách dịch vụ',
            'game_service_ids.*' => 'dịch vụ game',
        ];
    }

    /** @return array{game_service_ids: array<int, int>} */
    public function validated($key = null, $default = null): array
    {
        /** @var array{game_service_ids: array<int, int>} $validated */
        $validated = parent::validated($key, $default);

        return $validated;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }
}
