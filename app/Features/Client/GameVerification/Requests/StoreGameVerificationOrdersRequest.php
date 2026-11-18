<?php

namespace App\Features\Client\GameVerification\Requests;

use App\Features\Client\GamePassword\Services\GamePasswordCaptchaProfileService;
use App\Models\ManagedServer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreGameVerificationOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    protected function prepareForValidation(): void
    {
        $entries = [];
        if ($this->boolean('bulk')) {
            $text = $this->input('accounts_text');
            if (! is_string($text) || strlen($text) > 100000) {
                throw ValidationException::withMessages(['accounts_text' => 'Nhập tối đa 100 tài khoản mỗi lần gửi.']);
            }
            foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $parts = explode('|', $line);
                if (count($parts) !== 3 || count($entries) >= 100) {
                    throw ValidationException::withMessages(['accounts_text' => 'Dòng '.(count($entries) + 1).' không đúng dạng tk|mk|sv hoặc vượt quá 100 dòng.']);
                }
                $entries[] = ['username' => trim($parts[0]), 'old_password' => $parts[1], 'server_code' => $this->serverCode(trim($parts[2]))];
            }
        } else {
            $username = $this->input('username');
            $server = $this->input('server_code');
            $entries[] = ['username' => is_string($username) ? trim($username) : $username, 'old_password' => $this->input('old_password'),
                'server_code' => $this->serverCode(is_scalar($server) ? (string) $server : '')];
        }
        $this->merge(['entries' => $entries]);
    }

    private function serverCode(string $value): int
    {
        return preg_match('/^(?:sv)?([1-9][0-9]*)$/i', $value, $matches) ? (int) $matches[1] : 0;
    }

    public function rules(): array
    {
        $keyPresence = app(GamePasswordCaptchaProfileService::class)->hasCredentials($this->user()) ? 'nullable' : 'required';

        return ['request_id' => ['required', 'uuid'], 'price' => ['required', 'integer', 'between:0,100000000'],
            'captcha_api_key' => [$keyPresence, 'required_with:captcha_api_secret', 'string', 'max:512', 'regex:/^[\x21-\x7E]+$/'],
            'captcha_api_secret' => [$keyPresence, 'required_with:captcha_api_key', 'string', 'max:512', 'regex:/^[\x21-\x7E]+$/'],
            'bulk' => ['sometimes', 'boolean'], 'entries' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'entries.*.username' => ['required', 'string', 'max:100', 'regex:/^[^\s|]+$/u'],
            'entries.*.old_password' => ['required', 'string', 'max:200', 'regex:/^[^\r\n|]+$/'],
            'entries.*.server_code' => ['required', 'integer', Rule::exists(ManagedServer::class, 'server_code')->where('type', 'web')->where('is_active', true)]];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $keys = [];
            foreach ($this->input('entries') as $index => $entry) {
                $key = mb_strtolower($entry['username']).'|'.$entry['server_code'];
                if (in_array($key, $keys, true)) {
                    $validator->errors()->add('entries.'.$index.'.username', 'Dòng '.($index + 1).' trùng tài khoản và server.');
                }
                $keys[] = $key;
            }
        }];
    }
}
