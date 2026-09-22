<?php

use App\Models\Game;

test('client navigation game picker lists active games in catalog order', function (): void {
    $secondGame = Game::factory()->create([
        'name' => 'Avatar Musik',
        'slug' => 'avatar-musik',
        'short_name' => 'AM',
        'image' => '/storage/uploads/games/avatar-musik.webp',
        'sort_order' => 20,
    ]);
    $firstGame = Game::factory()->create([
        'name' => 'Ngọc Rồng Online',
        'slug' => 'ngoc-rong-online',
        'short_name' => 'NRO',
        'image' => '/storage/uploads/games/ngoc-rong-online.webp',
        'sort_order' => 10,
    ]);
    $inactiveGame = Game::factory()->inactive()->create([
        'name' => 'Game đã đóng',
        'slug' => 'game-da-dong',
        'sort_order' => 1,
    ]);

    $response = $this->get(route('orders.lookup'))
        ->assertOk()
        ->assertSee('id="game-picker-modal"', false)
        ->assertSee('data-game-picker-trigger="desktop"', false)
        ->assertSee('data-game-picker-trigger="mobile-menu"', false)
        ->assertSee('data-game-picker-trigger="mobile-bottom"', false)
        ->assertSee('role="dialog"', false)
        ->assertSee('aria-modal="true"', false)
        ->assertSee('Chọn game muốn nạp')
        ->assertSee('Icon Ngọc Rồng Online')
        ->assertSee('src="/storage/uploads/games/ngoc-rong-online.webp"', false)
        ->assertSee(route('topup.game', ['game' => $firstGame]), false)
        ->assertSee(route('topup.game', ['game' => $secondGame]), false)
        ->assertDontSee($inactiveGame->name)
        ->assertDontSee(route('topup.game', ['game' => $inactiveGame]), false)
        ->assertSeeInOrder([$firstGame->name, $secondGame->name]);

    expect(substr_count($response->getContent(), 'data-game-picker-modal'))->toBe(1)
        ->and(substr_count($response->getContent(), 'data-game-picker-open'))->toBe(3)
        ->and(substr_count($response->getContent(), 'data-game-picker-link'))->toBe(2);
});
