<?php

namespace App\Features\Client\GameImage\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameImageAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:100', Rule::unique('game_image_accounts')->where(fn ($query) => $query->where('user_id', $this->user()->id)->where('server', $this->input('server')))],
            'password' => ['required', 'string', 'max:200'],
            'server' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['username.unique' => 'Tài khoản này trên server đã có trong danh sách.', 'username.required' => 'Nhập tài khoản game.', 'password.required' => 'Nhập mật khẩu game.', 'server.required' => 'Nhập server game.'];
    }
}
