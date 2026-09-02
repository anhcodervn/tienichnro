<?php

use App\Models\Game;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use Database\Seeders\NgocRongTopupPackageSeeder;

test('ngoc rong price list uses nineteen percent provider cost and fifteen percent sale discount', function (): void {
    $provider = TopupProvider::factory()->create(['name' => 'the9p', 'slug' => 'the9p']);
    $game = Game::factory()->create([
        'name' => 'Ngọc Rồng Online cũ',
        'slug' => 'ngoc-rong-online',
    ]);
    $legacyPackage = TopupPackage::factory()->for($game)->create([
        'game_server_id' => null,
        'provider_id' => null,
        'name' => '10.000đ',
        'denomination' => null,
        'carot_amount' => 1,
        'provider_price' => 8100,
        'original_price' => 10000,
        'price' => 8500,
        'sort_order' => 0,
    ]);

    $this->seed(NgocRongTopupPackageSeeder::class);
    $this->seed(NgocRongTopupPackageSeeder::class);

    $game->refresh();
    $packages = $game->packages()
        ->whereNull('game_server_id')
        ->whereIn('denomination', [10000, 20000, 50000, 100000, 200000, 500000, 1000000])
        ->orderBy('denomination')
        ->get();

    expect($game->name)->toBe('Ngọc Rồng Online')
        ->and($game->reward_label)->toBe('Lượng / Ngọc')
        ->and($packages)->toHaveCount(7)
        ->and($packages->first()->id)->toBe($legacyPackage->id)
        ->and($packages->pluck('provider_id')->unique()->values()->all())->toBe([$provider->id])
        ->and($game->provider_service_code)->toBe('nr');

    $expectedPackages = [
        10000 => [8100, 8500, 13, 22, 32, 26],
        20000 => [16200, 17000, 32, 59, 82, 64],
        50000 => [40500, 42500, 91, 161, 231, 182],
        100000 => [81000, 85000, 195, 345, 495, 390],
        200000 => [162000, 170000, 455, 805, 1155, 910],
        500000 => [405000, 425000, 1430, 2530, 3630, 2860],
        1000000 => [810000, 850000, 3250, 5750, 8250, 6500],
    ];

    foreach ($expectedPackages as $denomination => [$providerPrice, $salePrice, $baseReward, $x2Reward, $x3Reward, $firstTopupReward]) {
        $package = $packages->firstWhere('denomination', $denomination);

        expect($package)->not->toBeNull()
            ->and((int) $package->provider_price)->toBe($providerPrice)
            ->and((int) $package->original_price)->toBe($denomination)
            ->and((int) $package->price)->toBe($salePrice)
            ->and($package->carot_amount)->toBe($baseReward)
            ->and($package->reward_x2_amount)->toBe($x2Reward)
            ->and($package->reward_x3_amount)->toBe($x3Reward)
            ->and($package->first_topup_reward_amount)->toBe($firstTopupReward)
            ->and($package->discount_percent)->toBe('15.00')
            ->and($package->name)->toBe(number_format($denomination, 0, ',', '.').'đ');
    }
});
