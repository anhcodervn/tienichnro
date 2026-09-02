<?php

namespace App\Features\Admin\Topup\Requests;

use App\Exceptions\ApiException;
use App\Models\Game;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator as ValidationValidator;

class UpdateGlobalTopupRewardRequest extends FormRequest
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
        return [
            'packages' => ['required', 'array', 'min:1'],
            'packages.*' => ['required', 'array:denomination,receives'],
            'packages.*.denomination' => [
                'required', 'integer', 'min:1', 'distinct',
            ],
            'packages.*.receives' => ['required', 'array', 'min:1', 'max:5'],
            'packages.*.receives.*' => [
                'required',
                'array:code,label,base_amount,reward_x2_amount,reward_x3_amount,first_topup_reward_amount',
            ],
            'packages.*.receives.*.code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'packages.*.receives.*.label' => ['required', 'string', 'max:60'],
            'packages.*.receives.*.base_amount' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'packages.*.receives.*.reward_x2_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'packages.*.receives.*.reward_x3_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'packages.*.receives.*.first_topup_reward_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    /** @return array<int, callable(ValidationValidator): void> */
    public function after(): array
    {
        return [function (ValidationValidator $validator): void {
            $game = $this->route('game');

            if (! $game instanceof Game) {
                $validator->errors()->add('game', 'Game không tồn tại.');

                return;
            }

            $submittedPackages = collect($this->input('packages', []));
            foreach ($submittedPackages as $index => $submittedPackage) {
                if (! is_array($submittedPackage)) {
                    continue;
                }

                $codes = collect($submittedPackage['receives'] ?? [])
                    ->pluck('code')
                    ->filter()
                    ->map(fn (mixed $code): string => mb_strtoupper((string) $code));

                if ($codes->duplicates()->isNotEmpty()) {
                    $validator->errors()->add("packages.{$index}.receives", 'Mã loại thực nhận trong cùng mệnh giá không được trùng nhau.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ApiException($validator->errors()->first(), 422);
    }
}
