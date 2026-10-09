<?php

namespace App\Features\Admin\Setting\Requests;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StoreServiceRequest extends UpdateServiceRequest
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
        return array_replace(parent::rules(), [
            'code' => ['required', 'string', 'max:64', 'regex:/\A[a-z][a-z0-9_-]*\z/', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && app(ToolAvailabilityService::class)->codeExists($value)) {
                    $fail('Mã dịch vụ đã được sử dụng.');
                }
            }],
            'name' => ['required', 'string', 'max:80'],
            'icon_type' => ['required', 'in:icon,image'],
        ]);
    }
}
