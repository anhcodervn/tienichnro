<?php

namespace App\Features\NroNotification\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreNotificationTypeRequest extends UpdateNotificationTypeRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_replace(parent::rules(), ['code' => ['required', 'string', 'max:64', 'regex:/\A[A-Z][A-Z0-9_-]*\z/', Rule::notIn(['BOSS_APPEAR', 'BOSS_DIE']), Rule::unique('code_notifies', 'code')]]);
    }

    public function messages(): array
    {
        return ['code.unique' => 'Mã loại thông báo đã được sử dụng.', 'code.not_in' => 'Mã này được dành riêng cho thông báo Boss.', 'code.regex' => 'Mã phải bắt đầu bằng chữ và chỉ chứa chữ in hoa, số, gạch dưới hoặc gạch ngang.'];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['code' => 'mã loại thông báo'];
    }
}
