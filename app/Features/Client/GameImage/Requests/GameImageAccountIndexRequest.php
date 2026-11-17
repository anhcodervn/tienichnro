<?php

namespace App\Features\Client\GameImage\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GameImageAccountIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'processing', 'success', 'error', 'paused', 'cancelled'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
