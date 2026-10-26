<?php

namespace App\Features\NroGameData\Requests;

class PublicIndexGameDataRequest extends IndexGameDataRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
