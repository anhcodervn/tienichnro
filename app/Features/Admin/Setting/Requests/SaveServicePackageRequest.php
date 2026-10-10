<?php

namespace App\Features\Admin\Setting\Requests;

use App\Features\Admin\Setting\Services\ServiceCatalogService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveServicePackageRequest extends FormRequest
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
        return [
            'service_code' => ['required', 'string', 'max:64', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || app(ServiceCatalogService::class)->find($value) === null) {
                    $fail('Dịch vụ không tồn tại hoặc đã bị xoá.');
                }
            }],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'billing_type' => ['required', 'in:usage,time,lifetime'],
            'notification_mode' => ['exclude'],
            'usage_limit' => ['nullable', 'required_if:billing_type,usage', 'prohibited_unless:billing_type,usage', 'integer', 'min:1', 'max:1000000000'],
            'duration_days' => ['nullable', 'required_if:billing_type,time', 'prohibited_unless:billing_type,time', 'integer', 'min:1', 'max:36500'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function packageValues(): array
    {
        return [...$this->validated(),
            'usage_limit' => $this->input('billing_type') === 'usage' ? (int) $this->validated('usage_limit') : null,
            'duration_days' => $this->input('billing_type') === 'time' ? (int) $this->validated('duration_days') : null];
    }
}
