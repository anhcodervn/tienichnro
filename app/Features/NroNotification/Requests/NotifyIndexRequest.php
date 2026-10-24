<?php

namespace App\Features\NroNotification\Requests;

use App\Features\NroNotification\Services\NotificationReadCacheService;
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
        $options = app(NotificationReadCacheService::class)->validationOptions();

        return [
            'limit' => ['nullable', 'integer', 'between:1,100'],
            'q' => ['nullable', 'string', 'max:200'],
            'server_code' => ['nullable', 'integer', Rule::in($options['server_codes'])],
            'server_id' => ['nullable', 'integer', Rule::in($options['server_ids'])], 'boss_id' => ['nullable', 'integer', Rule::in($options['boss_ids'])],
            'code' => ['nullable', 'string', Rule::in($options['codes'])],
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
        $types = app(NotificationReadCacheService::class)->catalog()['notificationTypes'];
        if (empty($filters['code']) && (! empty($filters['boss_id']) || ! empty($filters['state']))) {
            $filters['code'] = $types->firstWhere('system_key', 'BOSS')?->code ?? 'BOSS';
        }
        $additional = isset($filters['code']) ? ($types->firstWhere('code', $filters['code'])?->additional_filters ?? []) : [];
        if (! in_array('boss', $additional, true)) {
            unset($filters['boss_id']);
        }
        if (! in_array('state', $additional, true)) {
            unset($filters['state']);
        }

        return $filters;
    }
}
