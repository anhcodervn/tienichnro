<?php

namespace App\Features\Admin\Topup\Requests;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreGameImageRequest extends FormRequest
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
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp',
                'max:10240',
                'dimensions:max_width=8000,max_height=8000',
            ],
            'name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image.required' => 'Ảnh game là bắt buộc.',
            'image.extensions' => 'Ảnh game phải có định dạng JPG, PNG hoặc WebP.',
            'image.max' => 'Ảnh game không được lớn hơn 10 MB.',
            'name.max' => 'Tên ảnh không được vượt quá 120 ký tự.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'image' => 'ảnh game',
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
