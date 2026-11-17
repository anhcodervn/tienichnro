<?php

namespace App\Features\Client\GameImage\Requests;

class StreamGameImageAccountsRequest extends GameImageAccountIndexRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }
}
