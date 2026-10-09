<?php

namespace App\Features\NroNotification\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngestNotifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
        if (! $this->filled('occurred_at') && $this->filled('time_start')) {
            $this->merge(['occurred_at' => $this->input('time_start')]);
        }
        if ($this->isJson()) {
            $raw = json_decode($this->getContent(), true);
            if (is_array($raw) && isset($raw['content']) && is_string($raw['content'])) {
                $this->merge(['content' => $raw['content']]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'server_id' => ['required_without:server_code', 'nullable', 'integer', Rule::exists('servers', 'id')->where('is_active', true)],
            'server_code' => ['required_without:server_id', 'nullable', 'integer', Rule::exists('servers', 'server_code')->where('is_active', true)],
            'content' => ['required', 'string', 'max:10000'],
            'occurred_at' => ['required', 'date'],
            'event_id' => ['nullable', 'string', 'max:128'],
            'boss_code' => ['nullable', 'string', 'max:64'],
            'code_id' => ['nullable', 'integer', Rule::exists('code_notifies', 'id')->whereNull('deleted_at')],
            'code' => ['nullable', 'string', 'max:64', Rule::when(! in_array($this->input('code'), ['BOSS_APPEAR', 'BOSS_DIE'], true), Rule::exists('code_notifies', 'code')->whereNull('deleted_at'))],
            'char_name' => ['nullable', 'string', 'max:100'],
            'map_name' => ['nullable', 'string', 'max:150'],
            'map_id' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'zone' => ['nullable', 'integer', 'between:0,65535'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:occurred_at'],
            'metadata' => ['nullable', 'array', 'max:50'],
        ];
    }
}
