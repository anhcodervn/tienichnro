<?php

namespace App\Features\Admin\Topup\Requests;

use App\Models\TopupProvider;
use App\Rules\ValidTopupProviderConnectionConfig;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTopupProviderRequest extends FormRequest
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
            'connection_config' => ['required', 'array', 'min:1', new ValidTopupProviderConnectionConfig],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên provider',
            'slug' => 'slug provider',
            'connection_config' => 'cấu hình kết nối',
        ];
    }
}
