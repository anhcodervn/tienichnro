<?php

namespace App\Features\Admin\Setting\Requests;

use App\Models\ServiceOffering;
use App\Support\SafeNavigationUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveServiceOfferingRequest extends FormRequest
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
        $mode = $this->input('icon_type', 'icon');
        $safeUrl = function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && ! SafeNavigationUrl::passes($value)) {
                $fail('Liên kết phải là URL http/https hoặc đường dẫn bắt đầu bằng /.');
            }
        };

        return [
            'code' => $this->isMethod('POST')
                ? ['required', 'string', 'max:64', 'regex:/\A[a-z][a-z0-9_-]*\z/', Rule::unique(ServiceOffering::class, 'code')]
                : ['exclude'],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
            'url' => ['nullable', 'string', 'max:2048', $safeUrl],
            'page_slug' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'icon_type' => ['required', Rule::in(['icon', 'image'])],
            'icon' => ['required', 'string', 'max:80', 'regex:/\Abx-[a-z0-9-]+\z/'],
            'image_url' => [Rule::requiredIf($mode === 'image'), 'nullable', 'string', 'max:2048', $safeUrl],
            'is_enabled' => ['required', 'boolean'],
            'maintenance_message' => ['required', 'string', 'max:1000'],
            'payload_fields' => ['sometimes', 'array', 'list', 'max:50'],
            'payload_fields.*' => ['required', 'array:name,label,type,required,placeholder,options'],
            'payload_fields.*.name' => ['required', 'string', 'max:64', 'regex:/\A[a-zA-Z][a-zA-Z0-9_]*\z/', 'distinct:strict', Rule::notIn(['constructor', 'prototype'])],
            'payload_fields.*.label' => ['required', 'string', 'max:120'],
            'payload_fields.*.type' => ['required', Rule::in(['text', 'textarea', 'password', 'email', 'number', 'boolean', 'select'])],
            'payload_fields.*.required' => ['required', 'boolean'],
            'payload_fields.*.placeholder' => ['sometimes', 'nullable', 'string', 'max:200'],
            'payload_fields.*.options' => ['required_if:payload_fields.*.type,select', 'prohibited_unless:payload_fields.*.type,select', 'array', 'list', 'max:100'],
            'payload_fields.*.options.*' => ['required', 'array:value,label'],
            'payload_fields.*.options.*.value' => ['required', 'string', 'max:120'],
            'payload_fields.*.options.*.label' => ['required', 'string', 'max:120'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $fields = $this->input('payload_fields', []);
            if (! is_array($fields)) {
                return;
            }
            foreach ($fields as $index => $field) {
                if (! is_array($field) || ! is_array($field['options'] ?? null)) {
                    continue;
                }
                $values = [];
                foreach ($field['options'] as $option) {
                    if (! is_array($option) || ! is_string($option['value'] ?? null)) {
                        continue;
                    }
                    if (in_array($option['value'], $values, true)) {
                        $validator->errors()->add("payload_fields.$index.options", 'Giá trị lựa chọn không được trùng nhau trong cùng một trường.');
                        break;
                    }
                    $values[] = $option['value'];
                }
            }
        }];
    }
}
