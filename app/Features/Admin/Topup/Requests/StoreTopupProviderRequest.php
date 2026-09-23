<?php

namespace App\Features\Admin\Topup\Requests;

use App\Enums\TopupProviderType;
use App\Models\TopupProvider;
use App\Rules\ValidProviderPayloadFieldMapping;
use App\Rules\ValidTopupProviderConnectionConfig;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTopupProviderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->missing('type')) {
            $this->merge(['type' => match ($this->string('slug')->lower()->toString()) {
                'accnrovn' => TopupProviderType::AccNro->value,
                'manual' => TopupProviderType::Manual->value,
                default => TopupProviderType::MerchantPartnerCard->value,
            }]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $provider = $this->route('topupProvider');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(TopupProvider::class, 'slug')->ignore($provider instanceof TopupProvider ? $provider->id : null),
            ],
            'type' => ['required', Rule::enum(TopupProviderType::class)],
            'connection_config' => ['required', 'array', 'min:1', new ValidTopupProviderConnectionConfig],
            'payload_field_mapping' => ['nullable', 'array', new ValidProviderPayloadFieldMapping],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên provider',
            'slug' => 'slug provider',
            'type' => 'loại kết nối provider',
            'connection_config' => 'cấu hình kết nối',
            'payload_field_mapping' => 'mapping field payload',
        ];
    }
}
