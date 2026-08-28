<?php

namespace App\Features\Admin\Reporting\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdminReportingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled(['from', 'to'])) {
                return;
            }

            $from = CarbonImmutable::createFromFormat('Y-m-d', $this->string('from')->toString());
            $to = CarbonImmutable::createFromFormat('Y-m-d', $this->string('to')->toString());

            if ($from !== false && $to !== false && $from->diffInDays($to) > 365) {
                $validator->errors()->add('to', 'Khoảng báo cáo không được vượt quá 366 ngày.');
            }
        }];
    }
}
