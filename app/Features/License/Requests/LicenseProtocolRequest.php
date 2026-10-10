<?php

namespace App\Features\License\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LicenseProtocolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        if ($this->routeIs('license.challenge')) {
            return [];
        }
        $rules = [
            'product_code' => ['required', 'string', 'max:64'], 'device_uuid' => ['required', 'uuid'],
            'timestamp' => ['required', 'integer'], 'nonce' => ['required', 'string', 'min:32', 'max:128', 'regex:/\A[A-Za-z0-9_-]+\z/'],
        ];
        if ($this->routeIs('license.activate', 'license.transfer')) {
            if ($this->routeIs('license.transfer')) {
                $rules['owner_password'] = ['required', 'string', 'current_password:sanctum'];
            }

            return [...$rules, 'license_key' => ['required', 'string', 'max:100', 'regex:/\A[A-Fa-f0-9-]+\z/'],
                'device_name' => ['required', 'string', 'max:120'], 'public_key' => ['required', 'string', 'max:4096'],
                'hwid_hash' => ['nullable', 'string', 'size:64', 'regex:/\A[a-fA-F0-9]+\z/'],
                'client_version' => ['required', 'string', 'max:32', 'regex:/\A\d+\.\d+\.\d+(?:\.\d+)?\z/'], 'challenge_id' => ['required', 'uuid']];
        }

        return [...$rules, 'session_id' => ['required', 'uuid'], 'generation' => ['required', 'integer', 'min:1']];
    }
}
