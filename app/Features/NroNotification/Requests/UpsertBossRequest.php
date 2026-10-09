<?php

namespace App\Features\NroNotification\Requests;

use App\Features\NroNotification\Services\NotificationTypeClassifierService;
use App\Models\Boss;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertBossRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $boss = $this->route('boss');

        return [
            'code' => ['required', 'string', 'max:64', 'regex:/\A[A-Za-z0-9_-]+\z/', Rule::unique('bosses', 'code')->ignore($boss instanceof Boss ? $boss : null)],
            'name' => ['required', 'string', 'max:100'],
            'game_names' => ['sometimes', 'array', 'min:1', 'max:50'],
            'game_names.*' => ['required', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'required', 'integer', 'between:0,2147483647'],
            'respawn_seconds' => ['nullable', 'integer', 'between:0,31536000'], 'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('game_names')) {
            return;
        }
        $names = $this->input('game_names');
        if (is_string($names)) {
            $names = explode(',', $names);
        }
        if (is_array($names)) {
            $cleaned = [];
            $seen = [];
            foreach ($names as $name) {
                if (! is_string($name)) {
                    $cleaned[] = $name;

                    continue;
                }
                $key = NotificationTypeClassifierService::normalizeText($name);
                if ($key !== '' && ! isset($seen[$key])) {
                    $cleaned[] = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
                    $seen[$key] = true;
                }
            }
            $names = $cleaned;
        }
        $this->merge(['game_names' => $names]);
    }
}
