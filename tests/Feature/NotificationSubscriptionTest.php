<?php

use App\Features\Client\Wallet\Services\WalletService;
use App\Models\NotificationSubscription;
use App\Models\ServiceOffering;
use App\Models\ServicePackage;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->member = User::factory()->create();
    $this->personal = ServicePackage::factory()->create(['notification_mode' => 'personal']);
    ServiceOffering::query()->where('code', $this->personal->service_code)->update(['url' => '/dich-vu-nhan-thong-bao']);
    app(WalletService::class)->apply($this->member, 100000, 'credit', 'initial', 'Initial balance');
});
function personalSubscriptionPayload(int $packageId, array $extra = []): array
{
    return ['package_id' => $packageId, 'request_id' => (string) Str::uuid(), 'zalo_id' => '001zalo', 'characters' => [['char_name' => 'Character A', 'char_server' => 1]], 'notification_types' => ['BOSS', 'SET_ACTIVATION'], ...$extra];
}
test('subscription checkout debits server price once and snapshots its entitlement', function (): void {
    $payload = personalSubscriptionPayload($this->personal->id, ['price' => 1, 'user_id' => 999]);
    $this->actingAs($this->member)->postJson('/dich-vu-nhan-thong-bao', $payload)->assertCreated()->assertJsonPath('data.price', 50000)->assertJsonPath('data.user_id', $this->member->id);
    $this->postJson('/dich-vu-nhan-thong-bao', $payload)->assertCreated();
    expect(app(WalletService::class)->wallet($this->member)->balance)->toBe(50000);
    $this->assertDatabaseCount('notification_subscriptions', 1);
    $this->assertDatabaseCount('wallet_transactions', 2);
    $this->personal->update(['price' => 99000, 'duration_days' => 1]);
    $subscription = NotificationSubscription::query()->first();
    expect($subscription->expires_at->diffInDays($subscription->starts_at, true))->toBe(30.0)->and($subscription->price)->toBe(50000);
    $this->postJson('/dich-vu-nhan-thong-bao', [...$payload, 'zalo_id' => 'different'])->assertUnprocessable()->assertJsonValidationErrors('request_id');
    $this->postJson('/dich-vu-nhan-thong-bao', personalSubscriptionPayload($this->personal->id))->assertUnprocessable();
});
test('checkout fails without debiting for insufficient funds or unavailable packages', function (): void {
    $this->personal->update(['price' => 100001]);
    $this->actingAs($this->member)->postJson('/dich-vu-nhan-thong-bao', personalSubscriptionPayload($this->personal->id))->assertUnprocessable();
    $this->personal->update(['is_active' => false, 'price' => 50000]);
    $this->postJson('/dich-vu-nhan-thong-bao', personalSubscriptionPayload($this->personal->id))->assertUnprocessable();
    $this->personal->update(['is_active' => true]);
    ServiceOffering::query()->where('code', $this->personal->service_code)->update(['is_enabled' => false]);
    $this->postJson('/dich-vu-nhan-thong-bao', personalSubscriptionPayload($this->personal->id))->assertUnprocessable();
    $this->assertDatabaseCount('notification_subscriptions', 0);
    expect(app(WalletService::class)->wallet($this->member)->balance)->toBe(100000);
});
test('personal configurations reject excessive duplicate or invalid characters and types', function (array $extra, string $key): void {
    $this->actingAs($this->member)->postJson('/dich-vu-nhan-thong-bao', personalSubscriptionPayload($this->personal->id, $extra))->assertUnprocessable()->assertJsonValidationErrors($key);
    $this->assertDatabaseCount('notification_subscriptions', 0);
})->with([
    [['characters' => array_map(fn ($i) => ['char_name' => 'Char'.$i, 'char_server' => 1], range(1, 11))], 'characters'],
    [['characters' => [['char_name' => 'A', 'char_server' => 1], ['char_name' => 'a ', 'char_server' => 1]]], 'characters'],
    [['characters' => [['char_name' => 'A', 'char_server' => 99999]]], 'characters.0.char_server'],
    [['characters' => [['char_name' => ' ', 'char_server' => 1]]], 'characters.0.char_name'],
    [['notification_types' => ['UNKNOWN']], 'notification_types.0'],
    [['zalo_id' => ['a', 'b']], 'zalo_id'],
    [['webhook_url' => 'https://8.8.8.8'], 'webhook_url'],
    [['package_id' => []], 'package_id'],
]);
test('ten characters and same character on different servers are accepted with owner-only updates', function (): void {
    $characters = array_map(fn ($i) => ['char_name' => 'Char'.$i, 'char_server' => 1], range(1, 9));
    $characters[] = ['char_name' => 'Char1', 'char_server' => 2];
    $payload = personalSubscriptionPayload($this->personal->id, ['characters' => $characters]);
    $id = $this->actingAs($this->member)->postJson('/dich-vu-nhan-thong-bao', $payload)->assertCreated()->json('data.id');
    unset($payload['package_id'], $payload['request_id']);
    $this->patchJson('/dich-vu-nhan-thong-bao/'.$id, [...$payload, 'zalo_id' => '002zalo', 'price' => 1])->assertOk()->assertJsonPath('data.zalo_id', '002zalo')->assertJsonPath('data.price', 50000);
    $this->actingAs(User::factory()->create())->patchJson('/dich-vu-nhan-thong-bao/'.$id, $payload)->assertNotFound();
    NotificationSubscription::query()->whereKey($id)->update(['expires_at' => now()->subSecond()]);
    $this->actingAs($this->member)->patchJson('/dich-vu-nhan-thong-bao/'.$id, $payload)->assertUnprocessable();
});
test('webhook packages snapshot lifetime or usage and never expose their signing secret in JSON', function (string $billing, ?int $uses): void {
    $package = ServicePackage::factory()->create(['service_code' => $this->personal->service_code, 'notification_mode' => 'webhook', 'billing_type' => $billing, 'usage_limit' => $uses, 'duration_days' => null]);
    $payload = ['package_id' => $package->id, 'request_id' => (string) Str::uuid(), 'webhook_url' => 'https://8.8.8.8/webhook'];
    $this->actingAs($this->member)->postJson('/dich-vu-nhan-thong-bao', $payload)->assertCreated()->assertJsonPath('data.expires_at', null)->assertJsonPath('data.remaining_uses', $uses)->assertJsonMissingPath('data.webhook_secret');
    $subscription = NotificationSubscription::query()->first();
    expect($subscription->webhook_secret)->toHaveLength(64)->and($subscription->getRawOriginal('webhook_secret'))->not->toBe($subscription->webhook_secret);
    $this->get('/dich-vu-nhan-thong-bao')->assertOk()->assertSee($subscription->webhook_secret);
    $this->actingAs(User::factory()->create())->get('/dich-vu-nhan-thong-bao')->assertOk()->assertDontSee($subscription->webhook_secret);
})->with([['lifetime', null], ['usage', 100]]);
test('guest sees configured pricing but cannot register and no packages are seeded by the page', function (): void {
    $this->get('/dich-vu-nhan-thong-bao')->assertOk()->assertSee($this->personal->name)->assertSee('Đăng nhập');
    $this->postJson('/dich-vu-nhan-thong-bao', personalSubscriptionPayload($this->personal->id))->assertUnauthorized();
    $this->assertDatabaseCount('service_packages', 1);
});

test('notification page shows only packages attached to its configured service in admin order', function (): void {
    $this->personal->update(['name' => 'Configured personal plan', 'description' => 'Custom admin description', 'price' => 75000, 'duration_days' => 45, 'sort_order' => 5]);
    $webhook = ServicePackage::factory()->create(['service_code' => $this->personal->service_code, 'name' => 'Configured webhook lifetime', 'notification_mode' => 'webhook', 'billing_type' => 'lifetime', 'duration_days' => null, 'sort_order' => 1]);
    $unrelated = ServicePackage::factory()->create(['name' => 'Other service package', 'notification_mode' => 'personal']);
    ServicePackage::factory()->create(['service_code' => $this->personal->service_code, 'name' => 'Disabled plan', 'notification_mode' => 'personal', 'is_active' => false]);
    ServicePackage::factory()->create(['service_code' => $this->personal->service_code, 'name' => 'Unspecified delivery plan', 'notification_mode' => null]);
    $this->get('/dich-vu-nhan-thong-bao')->assertOk()->assertSeeInOrder([$webhook->name, $this->personal->name])
        ->assertSee('Custom admin description')->assertSee('75.000')->assertSee('45 ngày')->assertSee('Vĩnh viễn')
        ->assertDontSee('Other service package')->assertDontSee('Disabled plan')->assertSee('Unspecified delivery plan');
    $this->actingAs($this->member)->postJson('/dich-vu-nhan-thong-bao', personalSubscriptionPayload($unrelated->id))->assertUnprocessable()->assertJsonValidationErrors('package_id');
    expect(app(WalletService::class)->wallet($this->member)->balance)->toBe(100000);
});

test('notification page accepts absolute configured URLs and removes packages when their service changes page', function (): void {
    ServiceOffering::query()->where('code', $this->personal->service_code)->update(['url' => route('notification-subscriptions.index').'/']);
    $this->get('/dich-vu-nhan-thong-bao')->assertOk()->assertSee($this->personal->name);
    ServiceOffering::query()->where('code', $this->personal->service_code)->update(['url' => '/another-service']);
    $this->get('/dich-vu-nhan-thong-bao')->assertOk()->assertDontSee($this->personal->name)->assertSee('Hiện chưa có gói');
    $this->actingAs($this->member)->postJson('/dich-vu-nhan-thong-bao', personalSubscriptionPayload($this->personal->id))->assertUnprocessable();
    $this->assertDatabaseCount('notification_subscriptions', 0);
});

test('generic admin changes to existing notification packages remain reflected in the notification page', function (): void {
    $payload = ['service_code' => $this->personal->service_code, 'name' => 'Admin configured 200 events', 'description' => 'Description from admin', 'price' => 25000, 'billing_type' => 'usage', 'usage_limit' => 200, 'duration_days' => null, 'is_active' => true, 'sort_order' => 0];
    $this->actingAs(User::factory()->create(['role' => 'admin']))->patchJson('/api/admin-api/settings/service-packages/'.$this->personal->id, $payload)->assertOk();
    $this->get('/dich-vu-nhan-thong-bao')->assertOk()->assertSee('Admin configured 200 events')->assertSee('Description from admin')->assertSee('25.000')->assertSee('200 lượt');
    $this->assertDatabaseCount('service_packages', 1);
});

test('checkout renders three steps and payload inputs from the services configured schema', function (): void {
    ServiceOffering::query()->where('code', $this->personal->service_code)->update(['payload_fields' => [
        ['name' => 'contact_email', 'label' => 'Email nhận hỗ trợ', 'type' => 'email', 'required' => true],
        ['name' => 'region', 'label' => 'Khu vực', 'type' => 'select', 'required' => true, 'options' => [['value' => 'north', 'label' => 'Miền Bắc']]],
    ]]);
    $response = $this->actingAs($this->member)->get('/dich-vu-nhan-thong-bao')->assertOk()
        ->assertSeeInOrder(['Bước 1: Chọn gói dịch vụ', 'Bước 2: Điền thông tin dịch vụ', 'Bước 3: Thanh toán'])
        ->assertSee('Email nhận hỗ trợ')->assertSee('Miền Bắc')->assertSee('data-service-field="contact_email"', false);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//form[@data-checkout-wizard]//*[@data-checkout-step]')->length)->toBe(3)
        ->and($xpath->query('//*[@data-checkout-payload and @hidden]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-checkout-payment and not(@hidden)]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-checkout-payment]//button[@data-payment-submit and @disabled]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-payment-summary and @hidden]')->length)->toBe(1)
        ->and($xpath->query('//form[@data-checkout-wizard]//button[@type="submit" and @disabled]')->length)->toBe(1);
});

test('service payload is validated against admin schema stored encrypted and hidden from responses', function (): void {
    ServiceOffering::query()->where('code', $this->personal->service_code)->update(['payload_fields' => [
        ['name' => 'secret', 'label' => 'Mã xác thực', 'type' => 'password', 'required' => true],
        ['name' => 'region', 'label' => 'Khu vực', 'type' => 'select', 'required' => true, 'options' => [['value' => 'north', 'label' => 'Miền Bắc']]],
        ['name' => 'enabled', 'label' => 'Bật', 'type' => 'boolean', 'required' => true],
    ]]);
    $payload = personalSubscriptionPayload($this->personal->id);
    $this->actingAs($this->member)->postJson('/dich-vu-nhan-thong-bao', $payload)->assertUnprocessable()->assertJsonValidationErrors('service_payload.secret');
    $this->postJson('/dich-vu-nhan-thong-bao', [...$payload, 'service_payload' => ['secret' => 'secret-value', 'region' => 'invalid', 'enabled' => false]])->assertUnprocessable()->assertJsonValidationErrors('service_payload.region');
    $this->postJson('/dich-vu-nhan-thong-bao', [...$payload, 'service_payload' => ['secret' => 'secret-value', 'region' => 'north', 'enabled' => false, 'admin' => true]])->assertUnprocessable()->assertJsonValidationErrors('service_payload');
    expect(app(WalletService::class)->wallet($this->member)->balance)->toBe(100000);
    $values = ['secret' => 'secret-value', 'region' => 'north', 'enabled' => false];
    $this->postJson('/dich-vu-nhan-thong-bao', [...$payload, 'service_payload' => $values])->assertCreated()->assertJsonMissingPath('data.service_payload')->assertDontSee('secret-value');
    $subscription = NotificationSubscription::query()->first();
    expect($subscription->service_payload)->toBe($values)->and($subscription->getRawOriginal('service_payload'))->not->toContain('secret-value');
    $this->postJson('/dich-vu-nhan-thong-bao', [...$payload, 'service_payload' => $values])->assertCreated();
    expect(app(WalletService::class)->wallet($this->member)->balance)->toBe(50000);
    $this->get('/dich-vu-nhan-thong-bao')->assertOk()->assertDontSee('secret-value');
});

test('slug configured in admin connects generic packages payload and wallet checkout without notification modes', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $service = ServiceOffering::query()->where('code', $this->personal->service_code)->firstOrFail();
    $service->update(['page_slug' => 'another-page']);
    $this->get('/dich-vu-nhan-thong-bao')->assertOk()->assertDontSee($this->personal->name);
    $this->actingAs($admin)->patchJson('/api/admin-api/settings/service-catalog/'.$service->code, [
        ...$service->only(['name', 'description', 'sort_order', 'icon_type', 'icon', 'image_url', 'is_enabled', 'maintenance_message']),
        'page_slug' => 'dich-vu-nhan-thong-bao', 'url' => null,
        'payload_fields' => [['name' => 'character', 'label' => 'Nhân vật theo dõi', 'type' => 'text', 'required' => true]],
    ])->assertOk()->assertJsonPath('data.page_slug', 'dich-vu-nhan-thong-bao')->assertJsonPath('data.url', '/dich-vu-nhan-thong-bao');
    $id = $this->postJson('/api/admin-api/settings/service-packages', [
        'service_code' => $service->code, 'name' => 'Generic plan from admin', 'price' => 25000, 'billing_type' => 'lifetime',
        'duration_days' => null, 'usage_limit' => null, 'is_active' => true, 'sort_order' => 1,
    ])->assertCreated()->json('data.id');
    $this->actingAs($this->member)->get('/dich-vu-nhan-thong-bao')->assertOk()->assertSee('Generic plan from admin')->assertSee('Nhân vật theo dõi')->assertSee('data-mode="service"', false);
    $payload = ['package_id' => $id, 'request_id' => (string) Str::uuid()];
    $this->postJson('/dich-vu-nhan-thong-bao', $payload)->assertUnprocessable()->assertJsonValidationErrors('service_payload.character');
    $payload['service_payload'] = ['character' => 'Player 1'];
    $this->postJson('/dich-vu-nhan-thong-bao', $payload)->assertCreated()->assertJsonPath('data.mode', 'service')->assertJsonPath('data.expires_at', null);
    $this->postJson('/dich-vu-nhan-thong-bao', $payload)->assertCreated();
    expect(app(WalletService::class)->wallet($this->member)->balance)->toBe(75000);
    $this->get('/dich-vu-nhan-thong-bao')->assertOk();
});
