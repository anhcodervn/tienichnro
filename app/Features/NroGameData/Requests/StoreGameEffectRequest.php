<?php

namespace App\Features\NroGameData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameEffectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sheet' => ['required', 'file', 'mimes:png', 'extensions:png', 'max:10240', 'dimensions:max_width=2048,max_height=2048'],
            'animation' => ['required_without:binary', 'nullable', 'file', 'extensions:json', 'max:1024'],
            'binary' => ['required_without:animation', 'nullable', 'file', 'extensions:bin', 'max:1024'],
            'tick_rate' => ['sometimes', 'integer', 'between:1,120'],
        ];
    }
}
