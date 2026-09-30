<?php

namespace App\Features\Client\Affiliate\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreGameServiceOrderProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:5000'],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp',
                'max:10240',
                'dimensions:max_width=8000,max_height=8000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Vui lòng nhập nội dung tiến trình.',
            'description.max' => 'Nội dung tiến trình không được vượt quá 5.000 ký tự.',
            'image.image' => 'Tệp đính kèm phải là hình ảnh hợp lệ.',
            'image.mimes' => 'Ảnh tiến trình chỉ hỗ trợ JPG, PNG hoặc WebP.',
            'image.mimetypes' => 'Định dạng ảnh tiến trình không hợp lệ.',
            'image.extensions' => 'Phần mở rộng ảnh tiến trình không hợp lệ.',
            'image.max' => 'Ảnh tiến trình không được vượt quá 10 MB.',
            'image.dimensions' => 'Kích thước ảnh tiến trình không được vượt quá 8.000 × 8.000 px.',
        ];
    }

    public function attributes(): array
    {
        return [
            'description' => 'nội dung tiến trình',
            'image' => 'ảnh tiến trình',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }
}
