<?php

namespace App\Features\NroNotification\Requests;

use App\Models\CodeNotify;
use App\Models\NroServer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveZaloReceiveNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        foreach (['box_zalo_id', 'zalo_id', 'char_name'] as $key) {
            if (is_string($this->input($key))) {
                $value = trim($this->input($key));
                $this->merge([$key => $key === 'box_zalo_id' && $value === '' ? null : $value]);
            }
        }
        if (is_string($this->input('type_receive'))) {
            $codes = array_values(array_unique(array_filter(
                array_map(fn (string $type): string => strtoupper(trim($type)), explode(',', $this->input('type_receive'))),
                fn (string $type): bool => $type !== '',
            )));
            $this->merge(['type_receive' => implode(',', $codes)]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'box_zalo_id' => ['nullable', 'string', 'max:128'],
            'zalo_id' => ['required', 'string', 'max:128'],
            'char_name' => ['required', 'string', 'max:100'],
            'char_server' => ['required', 'integer', 'between:0,4294967295', Rule::exists(NroServer::class, 'server_code')],
            'type_receive' => ['bail', 'required', 'string', 'max:1000', function (string $attribute, mixed $value, Closure $fail): void {
                $codes = explode(',', $value);
                $knownCodes = CodeNotify::query()->whereIn('code', $codes)->pluck('code')->all();
                if (array_diff($codes, $knownCodes) !== []) {
                    $fail('Loại thông báo không tồn tại hoặc đã bị xoá. Vui lòng chọn lại.');
                }
            }],
        ];
    }
}
