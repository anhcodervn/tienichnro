<?php

namespace App\Features\Admin\Topup\Requests;

use Illuminate\Validation\Rule;

class UpdateGameRequest extends StoreGameRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'slug' => ['required', 'string', 'max:255', Rule::unique('games', 'slug')->ignore($this->route('game'))]];
    }
}
