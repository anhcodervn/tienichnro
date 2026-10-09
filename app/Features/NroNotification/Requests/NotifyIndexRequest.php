<?php

namespace App\Features\NroNotification\Requests;

use App\Models\CodeNotify;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotifyIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'between:1,100'],
            'q' => ['nullable', 'string', 'max:200'],
            'server_code' => ['nullable', 'integer', 'exists:servers,server_code'],
            'server_id' => ['nullable', 'integer', 'exists:servers,id'], 'boss_id' => ['nullable', 'integer', 'exists:bosses,id'],
            'code' => ['nullable', 'string', 'exists:code_notifies,code'],
            'state' => ['nullable', Rule::in(['living', 'respawning', 'history'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'], 'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }

    /** @return array<string, mixed> */
    public function publicFilters(): array
    {
        $filters = $this->validated();
        $filters['limit'] = $filters['limit'] ?? $filters['per_page'] ?? 10;
        unset($filters['per_page']);
        if (empty($filters['code']) && (! empty($filters['boss_id']) || ! empty($filters['state']))) {
            $filters['code'] = CodeNotify::query()->where('system_key', 'BOSS')->value('code') ?? 'BOSS';
        }
        $additional = isset($filters['code']) ? (CodeNotify::query()->where('code', $filters['code'])->first()?->additional_filters ?? []) : [];
        if (! in_array('boss', $additional, true)) {
            unset($filters['boss_id']);
        }
        if (! in_array('state', $additional, true)) {
            unset($filters['state']);
        }

        return $filters;
    }
}
