<?php

namespace App\Features\Admin\GameService\Requests;

use App\Models\GameService;
use App\Models\GameServicePackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGameServicePackageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('prices'))) {
            return;
        }

        $package = $this->route('gameServicePackage');
        $priceCode = $package instanceof GameServicePackage
            ? (string) ($package->prices()->value('code') ?? 'default')
            : 'default';

        $this->merge([
            'prices' => collect($this->input('prices'))->map(function (mixed $price) use ($priceCode): mixed {
                if (! is_array($price)) {
                    return $price;
                }

                $quantityEnabled = filter_var($price['quantity_enabled'] ?? false, FILTER_VALIDATE_BOOL);

                return [
                    ...$price,
                    'label' => trim((string) $this->input('name')),
                    'code' => $priceCode,
                    'quantity_enabled' => $quantityEnabled,
                    'min_quantity' => $quantityEnabled ? ($price['min_quantity'] ?? 1) : 1,
                    'max_quantity' => $quantityEnabled ? ($price['max_quantity'] ?? 1) : 1,
                    'status' => $this->input('status'),
                    'sort_order' => 0,
                ];
            })->all(),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $package = $this->route('gameServicePackage');
        $packageId = $package instanceof GameServicePackage ? $package->id : null;

        return [
            'game_service_id' => ['required', 'integer', Rule::exists(GameService::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique(GameServicePackage::class, 'code')->where('game_service_id', $this->integer('game_service_id'))->ignore($packageId)],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['required', 'integer', 'min:0'],
            'prices' => ['required', 'array', 'size:1'],
            'prices.*' => ['required', 'array:label,code,price,collaborator_price,quantity_enabled,min_quantity,max_quantity,status,sort_order'],
            'prices.*.label' => ['required', 'string', 'max:255'],
            'prices.*.code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', 'distinct'],
            'prices.*.price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'prices.*.collaborator_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'prices.*.quantity_enabled' => ['required', 'boolean'],
            'prices.*.min_quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'prices.*.max_quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'prices.*.status' => ['required', Rule::in(['active', 'inactive'])],
            'prices.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('prices', []) as $index => $price) {
                if (! is_array($price)) {
                    continue;
                }

                if (($price['quantity_enabled'] ?? false) && (int) ($price['min_quantity'] ?? 0) > (int) ($price['max_quantity'] ?? 0)) {
                    $validator->errors()->add("prices.{$index}.max_quantity", 'Số lượng tối đa phải lớn hơn hoặc bằng số lượng tối thiểu.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'prices.size' => 'Mỗi gói dịch vụ chỉ được có một mức giá.',
        ];
    }
}
