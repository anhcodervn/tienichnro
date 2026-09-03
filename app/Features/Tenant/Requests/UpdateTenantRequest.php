<?php

namespace App\Features\Tenant\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('slug')) {
            $payload['slug'] = strtolower(trim($this->string('slug')->toString()));
        }

        if ($this->has('domain')) {
            $payload['domain'] = rtrim(strtolower(trim($this->string('domain')->toString())), '.');
        }

        $this->merge($payload);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant = $this->route('tenant');
        $tenantId = $tenant?->id;
        $primaryDomainId = $tenant?->domains()->where('is_primary', true)->value('id');
        $protectMainSite = Rule::prohibitedIf($tenant?->is_main === true);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'slug' => ['sometimes', $protectMainSite, 'required', 'alpha_dash', 'max:80', Rule::unique('tenants', 'slug')->ignore($tenantId)],
            'domain' => [
                'sometimes', $protectMainSite, 'required', 'string', 'max:190',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
                Rule::unique('tenant_domains', 'domain')->ignore($primaryDomainId),
            ],
            'billing_user_id' => ['sometimes', $protectMainSite, 'required', 'integer', Rule::exists('users', 'id')],
            'status' => ['sometimes', 'required', Rule::in(['active', 'suspended'])],
            'allow_below_cost' => ['sometimes', 'boolean'],
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

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422, ['errors' => $validator->errors()->toArray()]);
    }
}
