<?php

namespace App\Features\NroNotification\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class DeleteZaloReceiveNotificationRequest extends SaveZaloReceiveNotificationRequest
{
    public function authorize(): bool
    {
        $key = config('services.zalo_bot.registration_key');

        return is_string($key) && $key !== '' && hash_equals($key, $this->bearerToken() ?? '');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['type_receive']);

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('char_name'))) {
            $this->merge(['char_name' => mb_strtolower(trim($this->input('char_name')), 'UTF-8')]);
        }

        parent::prepareForValidation();
    }
}
