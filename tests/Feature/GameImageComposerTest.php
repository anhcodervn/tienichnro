<?php

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\GameImage\Services\GameImageOrderService;
use App\Models\GameImageAccount;
use App\Models\User;
use App\Support\SettingStore;

beforeEach(function (): void {
    app(GameImageOrderService::class)->configure(0);
});

function gameImageLayout(): array
{
    return ['item_ids' => [1, 5, 11, 14, 29], 'show_details' => true, 'positions' => ['equipment' => ['x' => 200, 'y' => 48], 'bag' => ['x' => 400, 'y' => 120], 'featured' => ['x' => 1800, 'y' => 80]]];
}

test('game image catalog is public but adding accounts requires login', function (): void {
    $this->get(route('tools.game-image'))->assertOk()->assertSee('Đăng nhập')->assertDontSee('data-game-image-account-form', false);
    $this->get(route('home'))->assertOk()->assertSee(route('tools.game-image'), false);
    $this->post(route('tools.game-image.store'), [])->assertRedirect(route('login'));
});

test('studio uses the shared site logo for the editor and shared image viewer', function (string $darkLogo, string $expected): void {
    app(SettingStore::class)->putMany(['dark_logo' => $darkLogo, 'light_logo' => '/uploads/branding/light-logo.webp']);
    $account = GameImageAccount::factory()->create();
    $this->actingAs(User::findOrFail($account->user_id))->get(route('tools.game-image.edit', $account))
        ->assertOk()->assertSee('data-logo-url="'.$expected.'"', false)->assertDontSee('NRO Image Studio');
    $url = $this->postJson(route('tools.game-image.share', $account))->assertOk()->json('url');
    $this->get($url)->assertOk()->assertSee('data-logo-url="'.$expected.'"', false);
})->with([
    'dark logo' => ['/uploads/branding/dark-logo.webp', '/uploads/branding/dark-logo.webp'],
    'light fallback' => ['  ', '/uploads/branding/light-logo.webp'],
]);

test('an owner can add an encrypted account and open its Vue editor without exposing the password', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('tools.game-image.store'), ['username' => 'test-account', 'password' => 'never-expose-this-secret', 'server' => 'Vũ trụ 1', 'user_id' => 999])
        ->assertRedirect(route('tools.game-image'));
    $account = GameImageAccount::query()->sole();
    expect($account->user_id)->toBe($user->id)
        ->and($account->password)->toBe('never-expose-this-secret')
        ->and($account->getRawOriginal('password'))->not->toBe('never-expose-this-secret')
        ->and($account->toArray())->not->toHaveKey('password');
    $this->get(route('tools.game-image'))->assertOk()->assertSee('test-account')->assertSee('Điều chỉnh')
        ->assertSee('>Thêm tài khoản</h2>', false)->assertSee('>Quản lý tài khoản</h2>', false)
        ->assertDontSee('1. Thêm tài khoản game')->assertDontSee('2. Tài khoản của bạn')
        ->assertSee(route('tools.game-image.edit', $account), false)->assertDontSee('never-expose-this-secret');
    $this->get(route('tools.game-image.edit', $account))->assertOk()->assertSee('data-game-image-editor', false)
        ->assertSee('images/demo/capture/kztiger', false)
        ->assertViewHas('account', fn (array $data): bool => $data['id'] === $account->id && ! array_key_exists('password', $data))
        ->assertDontSee('never-expose-this-secret');
});

test('account validation preserves safe fields but never flashes the password', function (): void {
    $this->actingAs(User::factory()->create())->from(route('tools.game-image'))
        ->post(route('tools.game-image.store'), ['username' => 'demo', 'password' => 'do-not-flash', 'server' => ''])
        ->assertRedirect(route('tools.game-image'))->assertSessionHasErrors('server')
        ->assertSessionHasInput('username', 'demo')->assertSessionMissing('_old_input.password');
    expect(GameImageAccount::query()->count())->toBe(0);
});

test('the same account and server cannot be duplicated for one owner', function (): void {
    $account = GameImageAccount::factory()->create();
    $this->actingAs(User::findOrFail($account->user_id))->postJson(route('tools.game-image.store'), ['username' => $account->username, 'password' => 'test', 'server' => $account->server])
        ->assertUnprocessable()->assertJsonValidationErrors('username');
    $this->post(route('tools.game-image.store'), ['username' => $account->username, 'password' => 'test', 'server' => 'Vũ trụ 2'])->assertRedirect();
    $this->actingAs(User::factory()->create())->post(route('tools.game-image.store'), ['username' => $account->username, 'password' => 'test', 'server' => $account->server])->assertRedirect();
    expect(GameImageAccount::query()->count())->toBe(3);
});

test('saving a layout persists positions and preferences and survives reloading', function (): void {
    $account = GameImageAccount::factory()->create();
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => gameImageLayout(), 'username' => 'cannot-change', 'user_id' => 999])
        ->assertOk()->assertJsonPath('data.layout', gameImageLayout())->assertJsonMissingPath('data.password');
    expect($account->fresh()->layout)->toBe(gameImageLayout())->and($account->fresh()->username)->toBe($account->username);
    $this->get(route('tools.game-image.edit', $account))->assertOk()->assertViewHas('account', fn (array $data): bool => $data['layout'] === gameImageLayout());
});

test('numeric strings and false values are normalized before storing layout', function (): void {
    $account = GameImageAccount::factory()->create();
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => ['item_ids' => ['1'], 'show_details' => '0', 'positions' => ['bag' => ['x' => '100', 'y' => '150']]]])
        ->assertOk()->assertJsonPath('data.layout.item_ids.0', 1)->assertJsonPath('data.layout.show_details', false)->assertJsonPath('data.layout.positions.bag.x', 100);
});

test('empty highlights and default positions can be saved', function (): void {
    $account = GameImageAccount::factory()->create();
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => ['item_ids' => [], 'show_details' => false, 'positions' => []]])
        ->assertOk()->assertJsonPath('data.layout.item_ids', []);
});

test('all kztiger items including both NPC chests can be saved in highlight groups', function (): void {
    $account = GameImageAccount::factory()->create();
    $ids = range(1, 85);
    $layout = ['item_ids' => $ids, 'show_details' => true, 'positions' => [],
        'groups' => [['id' => 'featured-1', 'item_ids' => [47, 68, 69, 85], 'show_details' => true, 'columns' => 2]]];
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])
        ->assertOk()->assertJsonPath('data.layout.item_ids', $ids)->assertJsonPath('data.layout.groups.0.item_ids', [47, 68, 69, 85]);
    expect($account->fresh()->layout['groups'][0]['item_ids'])->toBe([47, 68, 69, 85]);
});

test('second row panels preserve positions layers and order when saving', function (): void {
    $account = GameImageAccount::factory()->create();
    $keys = ['npc-50-egg', 'panel-amulets', 'panel-radar'];
    $layout = ['item_ids' => [], 'show_details' => false,
        'positions' => array_fill_keys($keys, ['x' => 20, 'y' => 800]),
        'order' => $keys, 'layers' => array_fill_keys($keys, ['opacity' => 0.8])];
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])
        ->assertOk()->assertJsonPath('data.layout', $layout);
    expect($account->fresh()->layout)->toBe($layout);
});

test('multiple highlight groups persist independently and allow the same item in different groups', function (): void {
    $account = GameImageAccount::factory()->create();
    $layout = gameImageLayout();
    $layout['groups'] = [
        ['id' => 'featured-1', 'name' => 'Trang bị nổi bật', 'item_ids' => [1, 5, 11], 'show_details' => true, 'columns' => 3],
        ['id' => 'featured-2', 'name' => 'Vật phẩm hiếm', 'item_ids' => [1, 29, 46], 'show_details' => true, 'columns' => 1],
    ];
    $layout['positions']['featured-1'] = ['x' => 20, 'y' => 900];
    $layout['positions']['featured-2'] = ['x' => 600, 'y' => 900];
    $layout['positions']['box-84'] = ['x' => 2000, 'y' => 48];
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])
        ->assertOk()->assertJsonPath('data.layout', $layout);
    expect($account->fresh()->layout)->toBe($layout);
    $this->get(route('tools.game-image.edit', $account))->assertOk()->assertViewHas('account', fn (array $data): bool => $data['layout'] === $layout);
    $layout['groups'] = [];
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])->assertOk()->assertJsonPath('data.layout.groups', []);
});

test('invalid highlight groups are rejected', function (array $groups, string $error): void {
    $account = GameImageAccount::factory()->create();
    $layout = [...gameImageLayout(), 'groups' => $groups];
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])
        ->assertUnprocessable()->assertJsonValidationErrors($error);
    expect($account->fresh()->layout)->toBeNull();
})->with([
    [[['id' => 'featured-1', 'name' => str_repeat('a', 61), 'item_ids' => [1], 'show_details' => true, 'columns' => 2]], 'layout.groups.0.name'],
    [[['id' => 'featured-1', 'name' => ['invalid'], 'item_ids' => [1], 'show_details' => true, 'columns' => 2]], 'layout.groups.0.name'],
    [[['id' => 'featured-1', 'item_ids' => [1, 1], 'show_details' => true, 'columns' => 2]], 'layout.groups.0.item_ids'],
    [[['id' => 'featured-1', 'item_ids' => [86], 'show_details' => true, 'columns' => 2]], 'layout.groups.0.item_ids.0'],
    [[['id' => 'featured-1', 'item_ids' => ['bad'], 'show_details' => true, 'columns' => 2]], 'layout.groups.0.item_ids.0'],
    [[['id' => 'featured-1', 'item_ids' => [1], 'show_details' => true, 'columns' => 0]], 'layout.groups.0.columns'],
    [[['id' => 'featured-1', 'item_ids' => [1], 'show_details' => true, 'columns' => 7]], 'layout.groups.0.columns'],
    [[['id' => 'featured-11', 'item_ids' => [1], 'show_details' => true, 'columns' => 2]], 'layout.groups.0.id'],
    [[['id' => 'featured-1', 'item_ids' => [1], 'show_details' => true, 'columns' => 2, 'extra' => true]], 'layout.groups.0'],
    [[['id' => 'featured-1', 'item_ids' => [1], 'show_details' => true, 'columns' => 2], ['id' => 'featured-1', 'item_ids' => [5], 'show_details' => true, 'columns' => 2]], 'layout.groups.0.id'],
    [array_fill(0, 11, ['id' => 'featured-1', 'item_ids' => [1], 'show_details' => true, 'columns' => 2]), 'layout.groups'],
]);

test('another user cannot list edit or update someone elses account', function (): void {
    $account = GameImageAccount::factory()->create(['username' => 'private-game-account']);
    $this->actingAs(User::factory()->create())->get(route('tools.game-image'))->assertOk()->assertDontSee('private-game-account');
    $this->get(route('tools.game-image.edit', $account))->assertNotFound();
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => gameImageLayout()])->assertForbidden();
    expect($account->fresh()->layout)->toBeNull();
});

test('text blocks and their positions persist and can be removed without changing account credentials', function (): void {
    $account = GameImageAccount::factory()->create();
    $layout = [...gameImageLayout(), 'texts' => [
        ['id' => 'text-1', 'content' => "Bán tài khoản\nLiên hệ demo", 'font_size' => 24, 'color' => '#ff0000', 'bold' => true, 'width' => 360],
        ['id' => 'text-2', 'content' => '<b>Chữ thường</b>', 'font_size' => 18, 'color' => '#123456', 'bold' => false, 'width' => 300],
    ]];
    $layout['positions']['text-1'] = ['x' => 200, 'y' => 1000];
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])
        ->assertOk()->assertJsonPath('data.layout', $layout);
    expect($account->fresh()->layout)->toBe($layout)->and($account->fresh()->password)->toBe($account->password);
    $this->get(route('tools.game-image.edit', $account))->assertOk()->assertViewHas('account', fn (array $data): bool => $data['layout'] === $layout);
    $layout['texts'] = [];
    unset($layout['positions']['text-1']);
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])->assertOk()->assertJsonPath('data.layout.texts', []);
});

test('text block validation rejects invalid content and styles', function (string $field, mixed $value): void {
    $account = GameImageAccount::factory()->create();
    $text = ['id' => 'text-1', 'content' => 'Demo', 'font_size' => 24, 'color' => '#123456', 'bold' => true, 'width' => 360];
    $text[$field] = $value;
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => [...gameImageLayout(), 'texts' => [$text]]])
        ->assertUnprocessable()->assertJsonValidationErrors('layout.texts.0.'.$field);
    expect($account->fresh()->layout)->toBeNull();
})->with([
    ['id', 'text-11'], ['content', str_repeat('a', 1001)], ['content', ['invalid']],
    ['font_size', 11], ['font_size', 73], ['font_size', 'bad'], ['color', 'red'], ['color', '#123'],
    ['bold', 'bad'], ['width', 119], ['width', 1001],
]);

test('text blocks require unique identifiers and support empty text', function (): void {
    $account = GameImageAccount::factory()->create();
    $text = ['id' => 'text-1', 'content' => '', 'font_size' => 24, 'color' => '#123456', 'bold' => false, 'width' => 360];
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => [...gameImageLayout(), 'texts' => [$text, $text]]])
        ->assertUnprocessable()->assertJsonValidationErrors('layout.texts.0.id');
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => [...gameImageLayout(), 'texts' => array_fill(0, 11, $text)]])
        ->assertUnprocessable()->assertJsonValidationErrors('layout.texts');
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => [...gameImageLayout(), 'texts' => [$text]]])
        ->assertOk()->assertJsonPath('data.layout.texts.0.content', '');
});

test('guests cannot access the editor or update a layout', function (): void {
    $account = GameImageAccount::factory()->create();
    $this->get(route('tools.game-image.edit', $account))->assertRedirect(route('login'));
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => gameImageLayout()])->assertUnauthorized();
});

test('invalid layouts are rejected without changing saved data', function (array $layout, string $error): void {
    $account = GameImageAccount::factory()->create();
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])
        ->assertUnprocessable()->assertJsonValidationErrors($error);
    expect($account->fresh()->layout)->toBeNull();
})->with([
    [['item_ids' => [86], 'show_details' => true, 'positions' => []], 'layout.item_ids.0'],
    [['item_ids' => [1, 1], 'show_details' => true, 'positions' => []], 'layout.item_ids.0'],
    [['item_ids' => [], 'show_details' => true, 'positions' => ['bag' => ['x' => -1, 'y' => 48]]], 'layout.positions.bag.x'],
    [['item_ids' => [], 'show_details' => true, 'positions' => ['bag' => ['x' => 10]]], 'layout.positions.bag.y'],
    [['item_ids' => [], 'show_details' => true, 'positions' => ['bag' => []]], 'layout.positions.bag'],
    [['item_ids' => [], 'show_details' => true, 'positions' => ['bag' => ['x' => 6001, 'y' => 6001]]], 'layout.positions.bag.x'],
    [['item_ids' => [], 'show_details' => true, 'positions' => ['foreign' => ['x' => 1, 'y' => 48]]], 'layout.positions'],
    [['item_ids' => [], 'show_details' => true, 'positions' => [], 'password' => 'injected'], 'layout'],
]);

test('maintenance hides the form and blocks mutations and editing', function (): void {
    $account = GameImageAccount::factory()->create();
    app(ToolAvailabilityService::class)->update('game_image', ['is_enabled' => false, 'maintenance_message' => 'Đang cập nhật ghép ảnh.']);
    $this->actingAs(User::findOrFail($account->user_id))->get(route('tools.game-image'))->assertOk()->assertSee('Đang cập nhật ghép ảnh.')->assertDontSee('data-game-image-account-form', false);
    $this->post(route('tools.game-image.store'), ['username' => 'other', 'password' => 'secret', 'server' => '1'])->assertForbidden();
    $this->get(route('tools.game-image.edit', $account))->assertForbidden();
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => gameImageLayout()])->assertForbidden();
});

test('pet and magic tree panel positions and layer controls persist', function (): void {
    $account = GameImageAccount::factory()->create();
    $layout = [...gameImageLayout(),
        'positions' => ['panel-pet' => ['x' => 80, 'y' => 900], 'npc-4-magic-tree' => ['x' => 300, 'y' => 900]],
        'order' => ['panel-pet', 'npc-4-magic-tree'],
        'layers' => ['panel-pet' => ['scale' => 1.5], 'npc-4-magic-tree' => ['hidden' => true]],
    ];
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])
        ->assertOk()->assertJsonPath('data.layout.positions.panel-pet.x', 80)
        ->assertJsonPath('data.layout.positions.npc-4-magic-tree.y', 900)
        ->assertJsonPath('data.layout.layers.npc-4-magic-tree.hidden', true);
    expect($account->fresh()->layout)->toBe($layout);
});

test('autosave persists independent image scales and a custom background size', function (): void {
    $account = GameImageAccount::factory()->create();
    $layout = [...gameImageLayout(),
        'layers' => ['bag' => ['scale' => 1, 'scale_x' => 1.5, 'scale_y' => 0.5]],
        'document' => ['cname' => 'Hero', 'background' => '#ffffff', 'width' => 800, 'height' => 400],
    ];
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])->assertOk()
        ->assertJsonPath('data.layout.layers.bag.scale_x', 1.5)->assertJsonPath('data.layout.layers.bag.scale_y', 0.5)
        ->assertJsonPath('data.layout.document.width', 800)->assertJsonPath('data.layout.document.height', 400);
    expect($account->fresh()->layout)->toEqual($layout);
    $layout['document']['width'] = 6000;
    $layout['document']['height'] = 6000;
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])->assertUnprocessable()->assertJsonValidationErrors('layout.document.width');
    unset($layout['document']['height']);
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])->assertUnprocessable()->assertJsonValidationErrors('layout.document.height');
    $layout['document']['height'] = 400;
    $layout['layers']['bag']['scale_x'] = -1;
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])->assertUnprocessable()->assertJsonValidationErrors('layout.layers.bag.scale_x');
});

test('positions across the full background survive saving and reloading', function (): void {
    $account = GameImageAccount::factory()->create();
    $layout = gameImageLayout();
    $layout['positions']['bag'] = ['x' => 6000, 'y' => 6000];
    $this->actingAs(User::findOrFail($account->user_id))->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])->assertOk()->assertJsonPath('data.layout.positions.bag.x', 6000)->assertJsonPath('data.layout.positions.bag.y', 6000);
    expect($account->fresh()->layout['positions']['bag'])->toBe(['x' => 6000, 'y' => 6000]);
});
