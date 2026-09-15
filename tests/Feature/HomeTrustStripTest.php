<?php

use App\Models\Game;
use App\Models\Order;
use App\Models\TopupPackage;
use App\Models\User;

test('home trust strip shows member and game counts with the boosted order total', function (): void {
    User::factory()->count(3)->create();
    $activeGames = Game::factory()->count(2)->create();
    Game::factory()->inactive()->create();
    $package = TopupPackage::factory()->for($activeGames->first())->create();
    Order::factory()->count(4)->create([
        'game_id' => $activeGames->first()->id,
        'topup_package_id' => $package->id,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('aria-label="Thống kê nền tảng"', false)
        ->assertSee('data-home-stat="members"', false)
        ->assertSee('data-home-stat="orders"', false)
        ->assertSee('data-home-stat="games"', false)
        ->assertSeeInOrder(['>3</strong>', '<span>Thành viên</span>'], false)
        ->assertSeeInOrder(['>20</strong>', '<span>Đơn hàng</span>'], false)
        ->assertSeeInOrder(['>2</strong>', '<span>Game</span>'], false);
});

test('home trust strip keeps its three values inline and evenly distributed', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $home = file_get_contents($projectRoot.'/resources/views/client/home/index.blade.php');
    $styles = file_get_contents($projectRoot.'/resources/css/client.css');

    expect($home)
        ->toContain('bx bx-group text-lg')
        ->toContain('bx bx-receipt text-lg')
        ->toContain('bx bx-joystick text-lg')
        ->not->toContain('grid gap-0.5 text-left')
        ->and($styles)
        ->toContain('@apply mt-4 grid grid-cols-3')
        ->toContain('@apply inline-flex min-w-0 items-center justify-center gap-1 overflow-hidden whitespace-nowrap')
        ->toContain('@apply font-extrabold tabular-nums text-slate-900;');
});
