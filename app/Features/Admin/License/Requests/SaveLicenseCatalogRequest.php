<?php

namespace App\Features\Admin\License\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLicenseCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['name' => ['required', 'string', 'max:120'], 'is_active' => ['required', 'boolean'], 'max_active_devices' => ['required', 'integer', 'in:1'], 'transfer_cooldown' => ['required', 'integer', 'min:0', 'max:604800']];
        if ($this->routeIs('admin-api.license.plans.*')) {
            return [...$rules, 'product_id' => ['required', 'integer', Rule::exists('license_products', 'id')], 'duration_days' => ['present', 'nullable', 'integer', 'min:1', 'max:36500'], 'price' => ['required', 'integer', 'min:0', 'max:100000000000']];
        }

        return [...$rules, 'product_code' => ['required', 'string', 'max:64', 'regex:/\A[A-Z0-9_]+\z/', Rule::unique('license_products', 'product_code')->ignore($this->route('product')?->id)], 'description' => ['nullable', 'string', 'max:2000'], 'minimum_version' => ['required', 'string', 'max:32', 'regex:/\A\d+\.\d+\.\d+(?:\.\d+)?\z/'], 'heartbeat_interval' => ['required', 'integer', 'min:5', 'max:300'], 'lease_duration' => ['required', 'integer', 'gt:heartbeat_interval', 'max:900'], 'offline_grace' => ['required', 'integer', 'in:0']];
    }
}
