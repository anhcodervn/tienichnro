<?php

namespace App\Features\Client\Topup\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isAuthenticated = $this->user() !== null;

        return [
            'idempotency_key' => ['required', 'uuid'],
            'game_id' => ['required', 'integer', Rule::exists('games', 'id')],
            'server_id' => [
                'required',
                'integer',
                Rule::exists('game_servers', 'id')
                    ->where('game_id', $this->integer('game_id'))
                    ->where('status', 'active'),
            ],
            'package_id' => ['required', 'integer', Rule::exists('topup_packages', 'id')],
            'purchase_mode' => ['required', Rule::in(['single', 'bulk'])],
            'single_quantity' => ['exclude_unless:purchase_mode,single', 'required', 'integer', 'min:1', 'max:10'],
            'recipient_fields' => ['exclude_unless:purchase_mode,single', 'required', 'array', 'min:1', 'max:6'],
            'recipient_fields.*' => ['nullable', 'string', 'max:191'],
            'bulk_recipients' => ['exclude_unless:purchase_mode,bulk', 'required', 'string', 'max:25000'],
            'email' => [
                Rule::excludeIf($isAuthenticated),
                Rule::requiredIf(! $isAuthenticated),
                'email:rfc',
                'max:255',
            ],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'cf-turnstile-response' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
