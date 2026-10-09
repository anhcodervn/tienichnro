<?php

namespace App\Features\Client\Potential\Requests;

use App\Exceptions\ApiException;
use App\Features\Client\Potential\Services\PotentialCalculatorService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculatePotentialRequest extends FormRequest
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
        $planets = app(PotentialCalculatorService::class)->planets();
        $planet = $this->input('planet');
        $base = $planets[is_string($planet) ? $planet : ''] ?? $planets['earth'];

        return [
            'planet' => ['required', 'string', Rule::in(array_keys($planets))],
            'hp' => ['required', 'integer', 'min:'.$base['hp'], 'max:1000000000'],
            'ki' => ['required', 'integer', 'min:'.$base['ki'], 'max:1000000000'],
            'attack' => ['required', 'integer', 'min:'.$base['attack'], 'max:1000000'],
            'armor' => ['required', 'integer', 'min:0', 'max:1000000'],
            'critical' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.', 'integer' => ':attribute phải là số nguyên.',
            'min' => ':attribute phải từ :min trở lên.', 'max' => ':attribute không được vượt quá :max.',
            'planet.in' => 'Vui lòng chọn hành tinh hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return ['planet' => 'hành tinh', 'hp' => 'HP gốc', 'ki' => 'KI gốc', 'attack' => 'sức đánh gốc', 'armor' => 'giáp gốc', 'critical' => 'chí mạng'];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422, ['errors' => $validator->errors()->toArray()]);
    }
}
