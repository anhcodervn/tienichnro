<?php

namespace App\Features\Admin\Topup\Requests;

use App\Models\Game;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGameRequest extends StoreGameRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'slug' => ['required', 'string', 'max:255', Rule::unique('games', 'slug')->ignore($this->route('game'))]];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($this->input('package_mode') !== 'global') {
                    return;
                }

                /** @var Game|null $game */
                $game = $this->route('game');
                if (! $game instanceof Game) {
                    return;
                }

                $hasInvalidPackage = $game->packages()
                    ->where('status', 'active')
                    ->with('globalTopupPackage:id,denomination,price,status')
                    ->get(['id', 'global_topup_package_id', 'denomination', 'provider_price'])
                    ->contains(fn ($package): bool => $package->globalTopupPackage === null
                        || $package->globalTopupPackage->status !== 'active'
                        || $package->globalTopupPackage->denomination !== $package->denomination
                        || (int) $package->provider_price > $package->globalTopupPackage->price);

                if ($hasInvalidPackage) {
                    $validator->errors()->add(
                        'package_mode',
                        'Hãy ánh xạ đủ gói Global hợp lệ cho tất cả gói đang hoạt động trước khi bật chế độ Global.',
                    );
                }
            },
        ];
    }
}
