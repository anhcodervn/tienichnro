<?php

namespace App\Features\Admin\Setting\Requests;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Support\SafeNavigationUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
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
        $current = app(ToolAvailabilityService::class)->find((string) $this->route('code'));
        $mode = $this->input('icon_type', $current['icon_type'] ?? 'icon');

        return [
            'is_enabled' => ['required', 'boolean'],
            'maintenance_message' => ['required', 'string', 'max:1000'],
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'url' => ['sometimes', 'nullable', 'string', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== null && ! SafeNavigationUrl::passes($value)) {
                    $fail('Liên kết phải là URL http/https hoặc đường dẫn bắt đầu bằng /.');
                }
            }],
            'icon_type' => ['sometimes', 'required', Rule::in(['icon', 'image'])],
            'icon' => [Rule::requiredIf($mode === 'icon' && $this->exists('icon_type')), 'string', 'max:80', 'regex:/\Abx-[a-z0-9-]+\z/'],
            'image_url' => [Rule::requiredIf($mode === 'image' && ($this->exists('icon_type') || $this->exists('image_url'))), 'nullable', 'string', 'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && ! SafeNavigationUrl::passes($value)) {
                        $fail('Ảnh dịch vụ phải là URL http/https hoặc đường dẫn bắt đầu bằng /.');
                    }
                },
            ],
        ];
    }
}
