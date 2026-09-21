<?php

namespace App\Features\Admin\AuditLog\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminAuditLogIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'admin_id' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', Rule::in(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'status_code' => ['nullable', 'integer', 'between:100,599'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => trim((string) $this->input('search', '')) ?: null,
            'action' => trim((string) $this->input('action', '')) ?: null,
            'method' => strtoupper(trim((string) $this->input('method', ''))) ?: null,
            'admin_id' => $this->filled('admin_id') ? (int) $this->input('admin_id') : null,
            'status_code' => $this->filled('status_code') ? (int) $this->input('status_code') : null,
            'per_page' => (int) $this->input('per_page', 20),
            'page' => (int) $this->input('page', 1),
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }
}
