<?php

use App\Http\Middleware\EnsureTopupIsAvailable;
use App\Models\Game;
use App\Models\TopupPackage;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

test('only admins can manage maintenance settings', function (): void {
    $this->getJson('/api/admin-api/settings/maintenance')->assertUnauthorized();

    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/admin-api/settings/maintenance')->assertForbidden();
});

test('admin can update maintenance settings', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/settings/maintenance')
        ->assertOk()
        ->assertJsonPath('data.settings.site_active', true)
        ->assertJsonPath('data.settings.topup_maintenance_enabled', false);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/maintenance', [
            'site_active' => true,
            'topup_maintenance_enabled' => true,
            'topup_maintenance_message' => 'Cổng nạp tạm nghỉ đến 22:00.',
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.topup_maintenance_enabled', true)
        ->assertJsonPath('data.settings.topup_maintenance_message', 'Cổng nạp tạm nghỉ đến 22:00.');
});

test('topup maintenance requires a message when enabled', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/maintenance', [
            'site_active' => true,
            'topup_maintenance_enabled' => true,
            'topup_maintenance_message' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonStructure(['data' => ['errors' => ['topup_maintenance_message']]]);
});

test('website maintenance redirects public pages and keeps admin login available', function (): void {
    app(SettingStore::class)->putMany(['site_active' => false]);

    $this->get('/tin-tuc')->assertRedirect(route('maintenance'));
    $this->get(route('maintenance'))
        ->assertStatus(503)
        ->assertSee('Bảo trì hệ thống');
    $this->get('/dang-nhap')->assertOk();
});

test('maintenance page redirects home after website maintenance is disabled', function (): void {
    app(SettingStore::class)->putMany(['site_active' => true]);

    $this->get(route('maintenance'))->assertRedirect(route('home'));
});

test('website maintenance also redirects an authenticated admin on public pages', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    app(SettingStore::class)->putMany(['site_active' => false]);

    $this->actingAs($admin)->get('/tin-tuc')->assertRedirect(route('maintenance'));
    $this->actingAs($admin)->get('/admin')->assertOk();
});

test('topup maintenance replaces packages with custom text and blocks web checkout', function (): void {
    app(SettingStore::class)->putMany([
        'topup_maintenance_enabled' => true,
        'topup_maintenance_message' => 'Bảo trì cổng nạp đến 22:00.',
    ]);

    $game = Game::factory()->create([
        'name' => 'Game bảo trì',
        'slug' => 'game-bao-tri',
        'status' => 'active',
    ]);
    TopupPackage::factory()->for($game)->create([
        'name' => 'Gói không được hiển thị',
        'status' => 'active',
    ]);

    $this->get(route('topup.game', $game))
        ->assertOk()
        ->assertSee('data-topup-maintenance', false)
        ->assertSee('Bảo trì cổng nạp đến 22:00.')
        ->assertDontSee('data-package-button', false)
        ->assertDontSee('Gói không được hiển thị');

    $this->from(route('topup.game', $game))
        ->post(route('checkout.store'), [])
        ->assertRedirect(route('topup.game', $game))
        ->assertSessionHasErrors('topup');
});

test('topup maintenance blocks api order creation with the custom message', function (): void {
    app(SettingStore::class)->putMany([
        'topup_maintenance_enabled' => true,
        'topup_maintenance_message' => 'API nạp game đang bảo trì.',
    ]);

    $request = Request::create('/api/v1/orders', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
    $response = app(EnsureTopupIsAvailable::class)->handle(
        $request,
        fn () => response()->json(['status' => true]),
    );
    $route = Route::getRoutes()->getByName('api.v1.orders.store');

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getData(true)['message'])->toBe('API nạp game đang bảo trì.')
        ->and($route?->gatherMiddleware())->toContain('topup.available');
});
