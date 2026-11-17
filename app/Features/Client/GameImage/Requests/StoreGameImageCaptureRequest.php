<?php

namespace App\Features\Client\GameImage\Requests;

use App\Models\GameImageAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameImageCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $this->user()?->status === 'active' && $account instanceof GameImageAccount
            && ($account->user_id === $this->user()->id || $this->user()->role === 'admin');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'capture_version' => ['sometimes', 'integer', 'min:1'],
            'cName' => ['nullable', 'string', 'max:100'],
            'package' => ['required_without:files', 'file', 'mimes:zip', 'max:32768'],
            'files' => ['required_without:package', Rule::prohibitedIf($this->hasFile('package')), 'array', 'list', 'min:8', 'max:300'],
            'files.*' => ['required', 'file', 'max:8192', 'mimetypes:image/png,application/json,text/plain'],
            'paths' => ['required_with:files', Rule::prohibitedIf($this->hasFile('package')), 'array', 'list', 'size:'.count((array) $this->file('files', []))],
            'paths.*' => ['required', 'string', 'distinct', 'max:160', 'regex:/\A(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9_-]+\.(?:png|json|txt)\z/'],
        ];
    }
}
