<?php

namespace App\Features\Client\Api\Requests;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTopupTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->status === 'active';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'game_id' => ['required', 'integer', Rule::exists('games', 'id')->where('status', 'active')],
            'server_id' => [
                'required',
                'integer',
                Rule::exists('game_servers', 'id')
                    ->where('game_id', $this->integer('game_id'))
                    ->where('status', 'active'),
            ],
            'package_id' => ['required', 'integer', Rule::exists('topup_packages', 'id')->where('status', 'active')],
            'recipients' => ['required', 'array', 'min:1', 'max:100'],
            'recipients.*' => ['required', 'array:data,quantity'],
            'recipients.*.data' => ['required', 'array', 'min:1', 'max:6'],
            'recipients.*.data.*' => ['nullable', 'string', 'max:191'],
            'recipients.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'request_id.uuid' => 'request_id phải là UUID hợp lệ.',
            'recipients.required' => 'Cần ít nhất một tài khoản nhận.',
            'recipients.max' => 'Mỗi task chỉ được có tối đa 100 tài khoản nhận.',
            'recipients.*.data.required' => 'Thiếu thông tin tài khoản nhận.',
            'recipients.*.quantity.required' => 'Thiếu số lượng cho tài khoản nhận.',
        ];
    }

    public function attributes(): array
    {
        return [
            'game_id' => 'game_id',
            'server_id' => 'server_id',
            'package_id' => 'package_id',
            'recipients' => 'recipients',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }

    protected function failedAuthorization(): void
    {
        throw new ApiException('Tài khoản không thể sử dụng API.', 403);
    }
}
