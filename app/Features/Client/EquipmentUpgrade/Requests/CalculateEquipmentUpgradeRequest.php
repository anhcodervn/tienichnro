<?php

namespace App\Features\Client\EquipmentUpgrade\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateEquipmentUpgradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'current_stat' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'current_level' => ['required', 'integer', 'between:0,7'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_stat.required' => 'Vui lòng nhập chỉ số hiện tại.',
            'current_stat.numeric' => 'Chỉ số hiện tại phải là số.',
            'current_stat.min' => 'Chỉ số hiện tại không được âm.',
            'current_stat.max' => 'Chỉ số hiện tại không được vượt quá 1.000.000.000.',
            'current_level.required' => 'Vui lòng chọn cấp độ hiện tại.',
            'current_level.integer' => 'Cấp độ hiện tại phải là số nguyên.',
            'current_level.between' => 'Cấp độ hiện tại phải từ +0 đến +7.',
        ];
    }
}
