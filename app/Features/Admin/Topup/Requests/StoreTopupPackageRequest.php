<?php

namespace App\Features\Admin\Topup\Requests;

use App\Models\Game;
use App\Models\GlobalTopupPackage;
use App\Models\TopupProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTopupPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'game_id' => ['required', Rule::exists('games', 'id')],
            'game_server_id' => [
                'nullable',
                Rule::exists('game_servers', 'id')->where('game_id', $this->integer('game_id')),
            ],
            'global_topup_package_id' => [
                'nullable',
                Rule::exists(GlobalTopupPackage::class, 'id')->where('status', 'active'),
            ],
            'provider_id' => ['nullable', Rule::exists(TopupProvider::class, 'id')],
            'provider_service_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:255'], 'denomination' => ['nullable', 'integer', 'min:0'],
            'carot_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'reward_x2_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'reward_x3_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'first_topup_reward_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'provider_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'original_price' => ['required', 'integer', 'min:1', 'max:999999999999', 'gte:price'],
            'discount_percent' => ['prohibited'],
            'description' => ['nullable', 'string'], 'bonus_text' => ['nullable', 'string', 'max:255'],
            'min_quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'max_quantity' => ['nullable', 'integer', 'gte:min_quantity', 'max:10'],
            'status' => ['required', Rule::in(['active', 'inactive'])], 'sort_order' => ['required', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $game = Game::query()->find($this->integer('game_id'));
            $globalPackageId = $this->integer('global_topup_package_id');
            $globalPackage = $globalPackageId > 0 ? GlobalTopupPackage::query()->find($globalPackageId) : null;

            if ($game?->package_mode === 'global' && ! $globalPackage instanceof GlobalTopupPackage) {
                $validator->errors()->add('global_topup_package_id', 'Game Global bắt buộc ánh xạ một gói nạp Global.');

                return;
            }

            if (! $game instanceof Game) {
                return;
            }

            if (! $globalPackage instanceof GlobalTopupPackage) {
                if ($this->integer('provider_price') > $this->integer('price')) {
                    $validator->errors()->add('provider_price', 'Giá vốn provider không được lớn hơn giá bán riêng.');
                }

                return;
            }

            if ($this->integer('denomination') !== $globalPackage->denomination) {
                $validator->errors()->add('denomination', 'Mệnh giá của gói game phải trùng với gói Global đã ánh xạ.');
            }

            if ($this->integer('provider_price') > $globalPackage->price) {
                $validator->errors()->add('provider_price', 'Giá vốn provider không được lớn hơn giá bán của gói Global.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'provider_price' => 'giá gốc provider',
            'provider_service_code' => 'mã dịch vụ provider',
            'price' => 'giá bán ra',
            'original_price' => 'giá gốc của gói',
            'carot_amount' => 'thực nhận cơ bản',
            'reward_x2_amount' => 'thực nhận KM X2',
            'reward_x3_amount' => 'thực nhận KM X3',
            'first_topup_reward_amount' => 'thực nhận X2 nạp đầu',
        ];
    }
}
