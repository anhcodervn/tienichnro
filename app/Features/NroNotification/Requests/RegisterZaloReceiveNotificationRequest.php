<?php

namespace App\Features\NroNotification\Requests;

use App\Models\CodeNotify;

class RegisterZaloReceiveNotificationRequest extends SaveZaloReceiveNotificationRequest
{
    public function authorize(): bool
    {
        $key = config('services.zalo_bot.registration_key');

        return is_string($key) && $key !== '' && hash_equals($key, $this->bearerToken() ?? '');
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('type_receive')) {
            $this->merge(['type_receive' => CodeNotify::query()->whereIn('system_key', ['SET_ACTIVATION', 'GOD_ITEM'])->orderBy('id')->pluck('code')->implode(',')]);
        }
        if (is_string($this->input('char_name'))) {
            $this->merge(['char_name' => mb_strtolower(trim($this->input('char_name')), 'UTF-8')]);
        }
        parent::prepareForValidation();
    }
}
