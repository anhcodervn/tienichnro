<?php

namespace App\Features\Client\Potential\Requests;

use App\Features\Client\Potential\Services\PotentialCalculatorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateMissingPotentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $planets = app(PotentialCalculatorService::class)->planets();
        $planet = $this->input('planet');
        $base = $planets[is_string($planet) ? $planet : ''] ?? $planets['earth'];
        $rules = [
            'planet' => ['required', 'string', Rule::in(array_keys($planets))],
            'available_potential' => ['nullable', 'integer', 'min:0', 'max:9007199254740991'],
            'current_power' => ['nullable', 'integer', 'min:0', 'max:9007199254740991'],
        ];
        foreach (['current', 'target'] as $group) {
            $rules[$group] = ['required', 'array:hp,ki,attack,armor,critical'];
            foreach (['hp' => 1000000000, 'ki' => 1000000000, 'attack' => 1000000, 'armor' => 1000000, 'critical' => 100] as $field => $maximum) {
                $rules[$group.'.'.$field] = ['required', 'integer', 'min:'.($base[$field] ?? 0), 'max:'.$maximum];
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.', 'integer' => ':attribute phải là số nguyên.',
            'min' => ':attribute phải từ :min trở lên.', 'max' => ':attribute không được vượt quá :max.',
            'planet.in' => 'Vui lòng chọn hành tinh hợp lệ.', 'string' => ':attribute không hợp lệ.', 'array' => ':attribute không hợp lệ.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $attributes = ['planet' => 'hành tinh', 'available_potential' => 'tiềm năng đang có', 'current_power' => 'sức mạnh hiện tại'];
        foreach (['current' => 'hiện tại', 'target' => 'mong muốn'] as $group => $label) {
            $attributes[$group] = 'chỉ số '.$label;
            foreach (['hp' => 'HP', 'ki' => 'KI', 'attack' => 'sức đánh', 'armor' => 'giáp', 'critical' => 'chí mạng'] as $field => $name) {
                $attributes[$group.'.'.$field] = $name.' '.$label;
            }
        }

        return $attributes;
    }
}
