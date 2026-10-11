<?php

namespace App\Features\Admin\License\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManageLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->routeIs('admin-api.license.keys.store')) {
            return ['plan_id' => ['required', 'integer', 'exists:license_plans,id'], 'user_id' => ['nullable', 'integer', 'exists:users,id'], 'quantity' => ['required', 'integer', 'min:1', 'max:100']];
        }

        $reason = ['reason' => ['required', 'string', 'min:3', 'max:255']];
        if ($this->routeIs('admin-api.license.keys.destroy')) {
            return $reason;
        }
        if ($this->input('action') === 'edit') {
            return [...$reason, 'action' => ['required', 'in:edit'],
                'user_id' => ['present', 'nullable', 'integer', 'exists:users,id'],
                'duration_days' => ['present', 'nullable', 'integer', 'min:1', 'max:36500'],
                'expires_at' => ['present', 'nullable', 'date'],
                'transfer_cooldown' => ['required', 'integer', 'min:0', 'max:604800']];
        }

        return [...$reason, 'action' => ['required', 'in:suspend,resume,revoke,extend,reset-device,revoke-session,transfer,rotate'], 'days' => ['required_if:action,extend', 'nullable', 'integer', 'min:1', 'max:36500'], 'device_uuid' => ['required_if:action,transfer', 'nullable', 'uuid']];
    }
}
