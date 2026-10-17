<?php

namespace App\Features\NroGameData\Requests;

use App\Features\NroGameData\Services\GameDataCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrowseGameDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(array_keys(GameDataCatalogService::DATASETS))],
            'q' => ['nullable', 'string', 'max:200'],
            'gender' => ['sometimes', 'nullable', Rule::prohibitedIf($this->input('type', 'items') !== 'items'), 'integer', Rule::in([0, 1, 2, 3])],
            'page' => ['sometimes', 'integer', 'between:1,100000'],
        ];
    }
}
