<?php

namespace App\Features\Admin\Topup\Requests;

use App\Features\Topup\Support\RecipientFieldPattern;
use Illuminate\Validation\Rule;

class UpdateGameRequest extends StoreGameRequest
{
    public function rules(RecipientFieldPattern $recipientFieldPattern): array
    {
        return [...parent::rules($recipientFieldPattern), 'slug' => ['required', 'string', 'max:255', Rule::unique('games', 'slug')->ignore($this->route('game'))]];
    }
}
