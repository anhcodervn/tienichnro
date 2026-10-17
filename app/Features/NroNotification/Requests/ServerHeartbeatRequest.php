<?php

namespace App\Features\NroNotification\Requests;

use App\Models\NroServer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServerHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        $key = config('services.nro_bot.heartbeat_key');

        return is_string($key) && $key !== '' && hash_equals($key, $this->bearerToken() ?? '');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'server_code' => ['required', 'integer', 'between:0,4294967295', Rule::exists(NroServer::class, 'server_code')->where('is_active', true)],
        ];
    }
}
