<?php

namespace App\Features\Client\GameImage\Requests;

use App\Models\GameImageAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecaptureGameImageAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $this->user()?->status === 'active' && $account instanceof GameImageAccount
            && $account->user_id === $this->user()->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'capture_version' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
        ];
    }
}
