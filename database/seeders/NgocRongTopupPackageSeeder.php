<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NgocRongTopupPackageSeeder extends Seeder
{
    private const PROVIDER_DISCOUNT_PERCENT = 19;

    private const SALE_DISCOUNT_PERCENT = 15;

    private const REWARD_TABLE = [
        10000 => [13, 22, 32, 26],
        20000 => [32, 59, 82, 64],
        50000 => [91, 161, 231, 182],
        100000 => [195, 345, 495, 390],
        200000 => [455, 805, 1155, 910],
        500000 => [1430, 2530, 3630, 2860],
        1000000 => [3250, 5750, 8250, 6500],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $game = Game::query()->updateOrCreate(
                ['slug' => 'ngoc-rong-online'],
                [
                    'name' => 'Ngọc Rồng Online',
                    'short_name' => 'NRO',
                    'reward_label' => 'Lượng / Ngọc',
                    'description' => 'Nạp Ngọc Rồng Online nhanh, minh bạch và hỗ trợ tận tâm.',
                    'status' => 'active',
                    'sort_order' => 0,
                ],
            );
            $providerId = TopupProvider::query()->where('slug', 'the9p')->value('id');

            foreach (self::REWARD_TABLE as $denomination => $rewards) {
                $this->upsertPackage($game, $denomination, $rewards, is_numeric($providerId) ? (int) $providerId : null);
            }
        }, 3);
    }

    /** @param array{0: int, 1: int, 2: int, 3: int} $rewards */
    private function upsertPackage(Game $game, int $denomination, array $rewards, ?int $providerId): void
    {
        $name = number_format($denomination, 0, ',', '.').'đ';
        $package = $game->packages()
            ->whereNull('game_server_id')
            ->where('denomination', $denomination)
            ->first()
            ?? $game->packages()
                ->whereNull('game_server_id')
                ->where('name', $name)
                ->first()
            ?? new TopupPackage;

        $package->fill([
            'game_id' => $game->id,
            'game_server_id' => null,
            'provider_id' => $providerId ?? $package->provider_id,
            'provider_service_code' => $providerId !== null ? 'nr' : $package->provider_service_code,
            'name' => $name,
            'denomination' => $denomination,
            'carot_amount' => $rewards[0],
            'reward_x2_amount' => $rewards[1],
            'reward_x3_amount' => $rewards[2],
            'first_topup_reward_amount' => $rewards[3],
            'provider_price' => $this->discountedPrice($denomination, self::PROVIDER_DISCOUNT_PERCENT),
            'original_price' => $denomination,
            'price' => $this->discountedPrice($denomination, self::SALE_DISCOUNT_PERCENT),
            'min_quantity' => 1,
            'max_quantity' => 10,
            'status' => 'active',
            'sort_order' => $denomination,
            'metadata' => [
                'provider_discount_percent' => self::PROVIDER_DISCOUNT_PERCENT,
                'sale_discount_percent' => self::SALE_DISCOUNT_PERCENT,
            ],
        ])->save();
    }

    private function discountedPrice(int $originalPrice, int $discountPercent): int
    {
        return intdiv($originalPrice * (100 - $discountPercent), 100);
    }
}
