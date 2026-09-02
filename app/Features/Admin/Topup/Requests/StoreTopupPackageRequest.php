<?php

namespace App\Features\Admin\Topup\Requests;

use App\Models\Game;
use App\Models\TopupPackage;
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
            'game_server_id' => ['prohibited'],
            'global_topup_package_id' => ['prohibited'],
            'provider_id' => ['nullable', Rule::exists(TopupProvider::class, 'id')],
            'provider_service_code' => ['prohibited'],
            'name' => ['required', 'string', 'max:255'], 'denomination' => ['nullable', 'integer', 'min:0'],
            'carot_amount' => ['prohibited'],
            'reward_x2_amount' => ['prohibited'],
            'reward_x3_amount' => ['prohibited'],
            'first_topup_reward_amount' => ['prohibited'],
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
            $topupPackage = $this->route('topupPackage');

            if ($topupPackage instanceof TopupPackage && $topupPackage->global_topup_package_id !== null) {
                $validator->errors()->add('game_id', 'Gói này được đồng bộ tự động từ Gói nạp Global và không thể chỉnh sửa thủ công.');

                return;
            }

            $game = Game::query()->find($this->integer('game_id'));

            if (! $game instanceof Game) {
                return;
            }

            if ($game->package_mode === 'global') {
                $validator->errors()->add('game_id', 'Game dùng gói Global được đồng bộ tự động theo mệnh giá. Hãy quản lý tại trang Gói nạp Global.');

                return;
            }

            if ($this->integer('provider_price') > $this->integer('price')) {
                $validator->errors()->add('provider_price', 'Giá vốn provider không được lớn hơn giá bán riêng.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'provider_price' => 'giá gốc provider',
            'price' => 'giá bán ra',
            'original_price' => 'giá gốc của gói',
        ];
    }
}
