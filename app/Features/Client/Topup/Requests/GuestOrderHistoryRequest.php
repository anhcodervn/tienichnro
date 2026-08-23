<?php

namespace App\Features\Client\Topup\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class GuestOrderHistoryRequest extends FormRequest
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
            'codes' => ['required', 'array', 'max:30'],
            'codes.*' => ['required', 'string', 'distinct:strict', 'regex:/^TOP\d{6}[A-Z0-9]{6}$/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'codes.required' => 'Danh sách mã đơn là bắt buộc.',
            'codes.max' => 'Chỉ có thể tải tối đa 30 đơn mỗi lần.',
            'codes.*.regex' => 'Danh sách có mã đơn không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'codes' => 'danh sách mã đơn',
            'codes.*' => 'mã đơn',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }
}
