<?php

namespace App\Features\Admin\License\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SaveLicenseCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->isMethod('DELETE')) {
            return [];
        }

        $rules = ['name' => ['required', 'string', 'max:120'], 'is_active' => ['required', 'boolean'], 'max_active_devices' => ['required', 'integer', 'in:1'], 'transfer_cooldown' => ['required', 'integer', 'min:0', 'max:604800']];
        if ($this->routeIs('admin-api.license.plans.*')) {
            return [...$rules, 'product_id' => ['required', 'integer', Rule::exists('license_products', 'id')], 'duration_days' => ['present', 'nullable', 'integer', 'min:1', 'max:36500'], 'price' => ['required', 'integer', 'min:0', 'max:100000000000']];
        }

        return [...$rules,
            'image_url' => ['bail', 'sometimes', 'nullable', 'string', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
                if (! preg_match('~\A/storage/uploads/[A-Za-z0-9/_-]+\.(?:jpe?g|png|webp)\z~', $value)) {
                    $fail('Vui lòng chọn ảnh đã tải lên máy chủ.');

                    return;
                }
                $path = Storage::disk('public')->path(substr($value, strlen('/storage/')));
                $root = realpath(Storage::disk('public')->path('uploads'));
                $resolved = realpath($path);
                if (! $root || ! $resolved || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) || ! is_file($resolved)) {
                    $fail('Ảnh không còn tồn tại. Vui lòng tải lại.');

                    return;
                }
                $image = @getimagesize($resolved);
                if (! $image || $image[0] !== $image[1] || ! in_array($image[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                    $fail('Ảnh bản mod phải là JPG, PNG hoặc WebP có tỷ lệ vuông 1:1.');
                }
            }],
            'latest_version' => ['nullable', 'string', 'max:32', 'regex:/\A\d+\.\d+\.\d+(?:\.\d+)?\z/', 'required_with:tool_zip,update_zip',
                Rule::requiredIf(fn (): bool => $this->exists('latest_version') && ($this->route('product')?->tool_zip_path || $this->route('product')?->update_zip_path)),
            ],
            'tool_zip' => ['nullable', 'file', 'mimes:zip', 'extensions:zip', 'max:102400'],
            'update_zip' => ['nullable', 'file', 'mimes:zip', 'extensions:zip', 'max:102400'],
            'product_code' => ['required', 'string', 'max:64', 'regex:/\A[A-Z0-9_]+\z/', Rule::unique('license_products', 'product_code')->ignore($this->route('product')?->id)], 'description' => ['nullable', 'string', 'max:2000'], 'minimum_version' => ['required', 'string', 'max:32', 'regex:/\A\d+\.\d+\.\d+(?:\.\d+)?\z/'], 'heartbeat_interval' => ['required', 'integer', 'min:5', 'max:300'], 'lease_duration' => ['required', 'integer', 'gt:heartbeat_interval', 'max:900'], 'offline_grace' => ['required', 'integer', 'in:0']];
    }
}
