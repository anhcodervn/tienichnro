<?php

namespace App\Features\Client\Api\Requests;

use App\Exceptions\ApiException;
use App\Features\Topup\Services\OrderRecipientService;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTopupOrderRequest extends FormRequest
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
            'game' => ['required', 'integer', Rule::exists('games', 'id')->where('status', 'active')],
            'server' => [
                'required',
                'integer',
                Rule::exists('game_servers', 'id')
                    ->where('game_id', $this->integer('game'))
                    ->where('status', 'active'),
            ],
            'price' => ['required', 'integer', 'min:1'],
            'amount' => ['prohibited'],
            'payload' => ['required', 'array', 'list', 'min:1', 'max:'.OrderRecipientService::MAX_RECIPIENTS],
            'payload.*' => [
                'required',
                'array',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }

                    $recipientFields = array_diff_key($value, ['amount' => true]);

                    if ($recipientFields === [] || count($recipientFields) > 6) {
                        $fail("{$attribute} phải có từ 1 đến 6 trường dữ liệu nhận hàng.");

                        return;
                    }

                    if (collect($recipientFields)->contains(fn (mixed $field): bool => ! is_scalar($field) && $field !== null)) {
                        $fail("{$attribute} chứa dữ liệu không hợp lệ.");
                    }
                },
            ],
            'payload.*.amount' => [
                'required',
                'integer',
                'min:1',
                'max:'.OrderRecipientService::MAX_QUANTITY_PER_RECIPIENT,
            ],
            'payload.*.*' => [
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_scalar($value) && $value !== null) {
                        $fail("{$attribute} chứa dữ liệu không hợp lệ.");
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'request_id.uuid' => 'request_id phải là UUID hợp lệ.',
            'game.exists' => 'Game không tồn tại hoặc đang tạm tắt.',
            'server.exists' => 'Server không tồn tại, đang tạm tắt hoặc không thuộc game đã chọn.',
            'amount.prohibited' => 'Không gửi amount ở cấp ngoài; hãy đặt amount trong từng phần tử payload.',
            'payload.required' => 'Thiếu payload thông tin nhận hàng.',
            'payload.list' => 'payload phải là một danh sách tài khoản nhận.',
            'payload.max' => 'Mỗi đơn chỉ được có tối đa '.OrderRecipientService::MAX_RECIPIENTS.' tài khoản nhận.',
            'payload.*.amount.required' => 'Mỗi tài khoản trong payload phải có amount.',
            'payload.*.amount.max' => 'amount của mỗi tài khoản không được vượt quá '.OrderRecipientService::MAX_QUANTITY_PER_RECIPIENT.'.',
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
