<?php

namespace App\Features\Admin\Topup\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGameRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('package_mode')) {
            $this->merge(['package_mode' => 'custom']);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('games', 'slug')],
            'short_name' => ['nullable', 'string', 'max:50'],
            'reward_label' => ['required', 'string', 'max:60'],
            'provider_service_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'package_mode' => ['required', Rule::in(['custom', 'global'])],
            'image' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'], 'content' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['required', 'integer', 'min:0'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'], 'metadata' => ['nullable', 'array'],
            'checkout_fields' => ['required', 'array', 'min:1', 'max:6'],
            'checkout_fields.*' => ['required', 'array:key,label,placeholder,required'],
            'checkout_fields.*.key' => [
                'required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct',
                Rule::notIn([
                    'game_id', 'server_id', 'package_id', 'quantity', 'single_quantity', 'amount',
                    'recipient_fields', 'bulk_recipients', 'email', 'payment_method', 'purchase_mode',
                ]),
            ],
            'checkout_fields.*.label' => ['required', 'string', 'max:80'],
            'checkout_fields.*.placeholder' => ['nullable', 'string', 'max:120'],
            'checkout_fields.*.required' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $fields = $this->input('checkout_fields', []);

            if (is_array($fields) && ! collect($fields)->contains(
                fn (mixed $field): bool => is_array($field)
                    && filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOL),
            )) {
                $validator->errors()->add('checkout_fields', 'Phải có ít nhất một trường bắt buộc.');
            }
        }];
    }
}
