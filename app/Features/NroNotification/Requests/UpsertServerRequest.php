<?php

namespace App\Features\NroNotification\Requests;

use App\Models\NroServer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $server = $this->route('server');
        $presence = $server instanceof NroServer ? 'sometimes' : 'required';

        return [
            'code' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_-]+$/', Rule::unique('servers', 'code')->ignore($server instanceof NroServer ? $server : null)],
            'sort_order' => ['sometimes', 'required', 'integer', 'between:0,4294967295'],
            'server_code' => [$presence, 'required', 'integer', 'between:0,4294967295', Rule::unique('servers', 'server_code')->ignore($server instanceof NroServer ? $server : null)],
            'name' => [$presence, 'required', 'string', 'max:100'],
            'is_active' => [$presence, 'required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['server_code.unique' => 'Mã server đã được sử dụng.', 'server_code.between' => 'Mã server phải từ 0 đến 4294967295.'];
    }

    public function attributes(): array
    {
        return ['server_code' => 'mã server', 'name' => 'tên server', 'is_active' => 'trạng thái'];
    }
}
