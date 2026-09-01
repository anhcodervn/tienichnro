<?php

namespace App\Features\Admin\MemberLevel\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberLevelRequest extends FormRequest
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
        $levelId = $this->route('memberLevel')?->getKey();

        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('member_levels', 'code')->ignore($levelId)],
            'name' => ['required', 'string', 'max:100'],
            'rank' => ['required', 'integer', 'min:0', 'max:65535', Rule::unique('member_levels', 'rank')->ignore($levelId)],
            'lifetime_threshold' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'maintenance_amount' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'maintenance_days' => ['required', 'integer', 'min:1', 'max:365'],
            'default_discount_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'minimum_profit' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['required', 'string', 'max:50', 'alpha_dash'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    public function messages(): array
    {
        return ['color.regex' => 'Màu level phải có dạng #RRGGBB.'];
    }

    public function attributes(): array
    {
        return [];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }
}
