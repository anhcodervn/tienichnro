<?php

namespace App\Features\Admin\Topup\Requests;

use App\Rules\ValidTopupProviderConnectionConfig;

class UpdateTopupProviderRequest extends StoreTopupProviderRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'connection_config' => ['sometimes', 'array', 'min:1', new ValidTopupProviderConnectionConfig],
        ];
    }
}
