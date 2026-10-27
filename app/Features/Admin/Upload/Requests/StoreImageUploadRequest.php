<?php

namespace App\Features\Admin\Upload\Requests;

use App\Exceptions\ApiException;
use App\Features\Admin\Upload\Services\Base64ImageUploadService;
use App\Features\NroGameData\Services\GameDataAssetService;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role === 'admin';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'bail',
                'required_without_all:base64,url',
                'prohibits:base64,url',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp',
                'max:10240',
                'dimensions:max_width=8000,max_height=8000',
            ],
            'base64' => ['bail', 'required_without_all:image,url', 'prohibits:image,url', 'nullable', 'string', 'max:'.Base64ImageUploadService::MAX_ENCODED_LENGTH],
            'url' => ['bail', 'required_without_all:image,base64', 'prohibits:image,base64', 'nullable', 'string', 'max:2048', 'url:http,https'],
            'category' => ['nullable', 'required_with:icon_id,variant', Rule::in(GameDataAssetService::CATEGORIES)],
            'icon_id' => ['nullable', 'required_with:category,variant', 'integer', 'min:0', 'max:4294967295'],
            'variant' => ['nullable', Rule::in(GameDataAssetService::VARIANTS)],
            'name' => [
                'nullable',
                'string',
                'max:120',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required_without_all' => 'Cần gửi một trong ba nguồn ảnh: image, base64 hoặc url.',
            'prohibits' => 'Chỉ được gửi một nguồn ảnh: image, base64 hoặc url.',
            'base64.max' => 'Ảnh base64 không được vượt quá 10 MB sau giải mã.',
            'image.extensions' => 'Ảnh tải lên phải có định dạng JPG, PNG hoặc WebP.',
            'name.max' => 'Tên ảnh không được vượt quá 120 ký tự.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'image' => 'ảnh tải lên',
            'base64' => 'ảnh base64',
            'url' => 'URL ảnh',
            'name' => 'tên ảnh',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422, [
            'errors' => $validator->errors()->toArray(),
        ]);
    }
}
