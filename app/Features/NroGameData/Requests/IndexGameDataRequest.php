<?php

namespace App\Features\NroGameData\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexGameDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'after_id' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'between:1,500'],
            'view' => ['sometimes', Rule::in(['records', 'hashes'])],
            'ids' => ['sometimes', 'array', 'list', 'max:500'],
            'ids.*' => ['required', 'integer', 'between:0,4294967295', 'distinct'],
            'class_id' => ['sometimes', Rule::prohibitedIf($this->route('dataset') !== 'classes-and-skills'), 'integer', 'between:0,4294967295'],
        ];
    }
}
