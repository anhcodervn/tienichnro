<?php

namespace App\Features\NroNotification\Requests;

use App\Features\NroNotification\Services\NotificationTypeClassifierService;
use App\Models\CodeNotify;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationTypeRequest extends FormRequest
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
        if (! $this->exists('keywords')) {
            return;
        }
        $keywords = $this->input('keywords');
        if (is_string($keywords)) {
            $keywords = explode(',', $keywords);
        }
        if ($keywords === null) {
            $keywords = [];
        }
        if (is_array($keywords)) {
            $cleaned = [];
            $seen = [];
            foreach ($keywords as $keyword) {
                if (! is_string($keyword)) {
                    $cleaned[] = $keyword;

                    continue;
                }
                $key = NotificationTypeClassifierService::normalizeText($keyword);
                if ($key !== '' && ! isset($seen[$key])) {
                    $cleaned[] = trim(preg_replace('/\s+/u', ' ', $keyword) ?? $keyword);
                    $seen[$key] = true;
                }
            }
            $keywords = $cleaned;
        }
        $this->merge(['keywords' => $keywords]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->route('notificationType');

        return ['name' => ['required', 'string', 'max:100'], 'type_id' => ['required', 'integer', 'exists:type_notifies,id'],
            'code' => ['sometimes', 'required', 'string', 'max:64', 'regex:/\A[A-Z][A-Z0-9_-]*\z/', Rule::notIn(['BOSS_APPEAR', 'BOSS_DIE']), Rule::unique('code_notifies', 'code')->ignore($type instanceof CodeNotify ? $type : null)],
            'system_key' => ['missing'], 'deleted_at' => ['missing'],
            'keywords' => ['sometimes', 'array', 'max:50'],
            'keywords.*' => ['required', 'string', 'max:100'],
            'additional_filters' => ['sometimes', 'array', 'max:2'],
            'additional_filters.*' => ['required', 'string', 'distinct', Rule::in(['boss', 'state'])]];
    }

    public function messages(): array
    {
        return ['code.unique' => 'Mã loại thông báo đã được sử dụng.'];
    }

    public function attributes(): array
    {
        return ['name' => 'tên loại thông báo', 'type_id' => 'nhóm thông báo'];
    }
}
