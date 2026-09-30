<?php

namespace App\Features\Client\Affiliate\Requests;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CompleteGameServiceOrderRequest extends FormRequest
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
                'required',
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
            'description.required' => 'Vui lòng nhập mô tả kết quả hoàn thành.',
            'description.max' => 'Mô tả hoàn thành không được vượt quá 5.000 ký tự.',
            'image.required' => 'Phải có ảnh xác minh mới có thể báo hoàn thành.',
            'image.image' => 'Ảnh xác minh không hợp lệ.',
            'image.mimes' => 'Ảnh xác minh chỉ hỗ trợ JPG, PNG hoặc WebP.',
            'image.mimetypes' => 'Định dạng ảnh xác minh không hợp lệ.',
            'image.extensions' => 'Phần mở rộng ảnh xác minh không hợp lệ.',
            'image.max' => 'Ảnh xác minh không được vượt quá 10 MB.',
            'image.dimensions' => 'Kích thước ảnh xác minh không được vượt quá 8.000 × 8.000 px.',
        ];
    }

    public function attributes(): array
    {
        return [
            'description' => 'mô tả hoàn thành',
            'image' => 'ảnh xác minh',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }
}
