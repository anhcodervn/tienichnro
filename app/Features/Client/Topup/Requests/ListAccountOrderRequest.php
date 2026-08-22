<?php

namespace App\Features\Client\Topup\Requests;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAccountOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'game_id' => ['nullable', 'integer', Rule::exists('games', 'id')],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'order_status' => ['nullable', Rule::enum(OrderStatus::class)],
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when($this->filled('date_to'), ['before_or_equal:date_to']),
            ],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when($this->filled('date_from'), ['after_or_equal:date_from']),
            ],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 25, 50])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'amount_desc', 'amount_asc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.before_or_equal' => 'Ngày bắt đầu phải trước hoặc bằng ngày kết thúc.',
            'date_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }

    public function attributes(): array
    {
        return [
            'q' => 'từ khóa',
            'game_id' => 'game',
            'payment_status' => 'trạng thái thanh toán',
            'order_status' => 'trạng thái đơn',
            'date_from' => 'ngày bắt đầu',
            'date_to' => 'ngày kết thúc',
            'per_page' => 'số dòng mỗi trang',
            'sort' => 'thứ tự',
        ];
    }
}
