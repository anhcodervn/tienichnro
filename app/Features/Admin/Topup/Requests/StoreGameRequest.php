<?php

namespace App\Features\Admin\Topup\Requests;

use App\Features\Topup\Support\RecipientFieldPattern;
use App\Models\Game;
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

        if (is_array($this->input('checkout_fields'))) {
            $this->merge([
                'checkout_fields' => collect($this->input('checkout_fields'))
                    ->map(fn (mixed $field): mixed => is_array($field) ? [
                        ...$field,
                        'type' => $field['type'] ?? 'text',
                        'options' => is_array($field['options'] ?? null)
                            ? collect($field['options'])->map(fn (mixed $option): mixed => is_array($option) ? [
                                ...$option,
                                'value' => is_string($option['value'] ?? null) ? trim($option['value']) : ($option['value'] ?? null),
                                'text' => is_string($option['text'] ?? null) ? trim($option['text']) : ($option['text'] ?? null),
                            ] : $option)->all()
                            : ($field['options'] ?? []),
                        'min' => $field['min'] ?? null,
                        'max' => $field['max'] ?? null,
                        'step' => $field['step'] ?? null,
                    ] : $field)
                    ->all(),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(RecipientFieldPattern $recipientFieldPattern): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('games', 'slug')],
            'short_name' => ['nullable', 'string', 'max:50'],
            'reward_label' => ['required', 'string', 'max:60'],
            'provider_service_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'package_mode' => ['required', Rule::in(['custom', 'global'])],
            'min_quantity' => ['sometimes', 'required', 'integer', 'min:1', 'max:10'],
            'max_quantity' => ['sometimes', 'required', 'integer', 'min:1', 'max:10'],
            'image' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:100000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['required', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'checkout_fields' => ['required', 'array', 'min:1', 'max:6'],
            'checkout_fields.*' => ['required', 'array:key,label,placeholder,required,regex,type,options,min,max,step'],
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
            'checkout_fields.*.type' => ['required', Rule::in(['text', 'number', 'select'])],
            'checkout_fields.*.options' => ['present', 'array', 'max:50', 'prohibited_unless:checkout_fields.*.type,select'],
            'checkout_fields.*.options.*' => ['required', 'array:value,text'],
            'checkout_fields.*.options.*.value' => [
                'required',
                'string',
                'max:191',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && (str_contains($value, '|') || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1)) {
                        $fail('Value không được chứa dấu |, ký tự xuống dòng hoặc ký tự điều khiển.');
                    }
                },
            ],
            'checkout_fields.*.options.*.text' => ['required', 'string', 'max:120'],
            'checkout_fields.*.min' => ['nullable', 'numeric', 'prohibited_unless:checkout_fields.*.type,number'],
            'checkout_fields.*.max' => ['nullable', 'numeric', 'prohibited_unless:checkout_fields.*.type,number'],
            'checkout_fields.*.step' => ['nullable', 'numeric', 'gt:0', 'prohibited_unless:checkout_fields.*.type,number'],
            'checkout_fields.*.regex' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail) use ($recipientFieldPattern): void {
                    if (is_string($value) && $value !== '' && ! $recipientFieldPattern->isValid($value)) {
                        $fail('Regex không hợp lệ. Hãy nhập nội dung pattern và không kèm dấu /.');
                    }
                },
            ],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $game = $this->route('game');
            $minimum = $this->exists('min_quantity')
                ? $this->integer('min_quantity')
                : ($game instanceof Game ? $game->min_quantity : 1);
            $maximum = $this->exists('max_quantity')
                ? $this->integer('max_quantity')
                : ($game instanceof Game ? $game->max_quantity : 10);

            if ($minimum > $maximum) {
                $validator->errors()->add('max_quantity', 'Số lượng tối đa cho mỗi tài khoản phải lớn hơn hoặc bằng số lượng tối thiểu.');
            }

            $fields = $this->input('checkout_fields', []);

            if (is_array($fields) && ! collect($fields)->contains(
                fn (mixed $field): bool => is_array($field)
                    && filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOL),
            )) {
                $validator->errors()->add('checkout_fields', 'Phải có ít nhất một trường bắt buộc.');
            }

            foreach (is_array($fields) ? $fields : [] as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                if (($field['type'] ?? null) === 'select' && empty($field['options'])) {
                    $validator->errors()->add("checkout_fields.{$index}.options", 'Trường select phải có ít nhất một lựa chọn.');
                }

                if (($field['type'] ?? null) === 'select' && is_array($field['options'] ?? null)) {
                    $optionValues = collect($field['options'])
                        ->filter(fn (mixed $option): bool => is_array($option) && is_string($option['value'] ?? null))
                        ->pluck('value');

                    if ($optionValues->count() !== $optionValues->uniqueStrict()->count()) {
                        $validator->errors()->add("checkout_fields.{$index}.options", 'Value của các lựa chọn trong cùng một trường không được trùng nhau.');
                    }
                }

                if (($field['type'] ?? null) === 'number'
                    && is_numeric($field['min'] ?? null)
                    && is_numeric($field['max'] ?? null)
                    && (float) $field['min'] > (float) $field['max']) {
                    $validator->errors()->add("checkout_fields.{$index}.max", 'Giá trị tối đa phải lớn hơn hoặc bằng giá trị tối thiểu.');
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'min_quantity' => 'số lượng tối thiểu cho mỗi tài khoản',
            'max_quantity' => 'số lượng tối đa cho mỗi tài khoản',
        ];
    }
}
