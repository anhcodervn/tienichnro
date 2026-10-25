<?php

use App\Features\Admin\License\Services\LicenseAdminService;
use App\Features\License\Services\LicenseSessionService;
use App\Models\License;
use App\Models\LicenseDevice;
use App\Models\LicenseEvent;
use App\Models\LicensePlan;
use App\Models\LicenseProduct;
use App\Models\LicenseSession;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->member = User::factory()->create(['role' => 'user', 'status' => 'active']);
    $this->product = LicenseProduct::factory()->create();
    $this->url = '/api/admin-api/users/'.$this->member->id.'/tools';
});

test('granting tool access creates a private perpetual license in dashboard and account API', function (): void {
    $this->actingAs($this->admin)->patchJson($this->url, ['product_ids' => [$this->product->id]])->assertOk();
    $license = License::query()->where('user_id', $this->member->id)->sole();
    expect($license->is_permission_grant)->toBeTrue()->and($license->duration_days)->toBeNull()
        ->and($license->expires_at)->toBeNull()->and($license->activated_at)->toBeNull()
        ->and($license->status)->toBe('unused')->and($license->encrypted_key)->not->toBeEmpty()
        ->and($license->key_hash)->toBe(LicenseSessionService::hashKey($license->encrypted_key));
    $this->getJson($this->url)->assertDontSee($license->encrypted_key);
    $this->actingAs($this->member)->get(route('account.profile.dashboard'))->assertOk()->assertSee($this->product->name)->assertSee($license->encrypted_key);
    auth()->forgetGuards();
    $this->withToken($this->member->createToken('launcher')->plainTextToken)->getJson('/api/account/licenses?product_code='.$this->product->product_code)
        ->assertOk()->assertJsonPath('data.0.key', $license->encrypted_key)->assertJsonPath('data.0.can_activate', true)
        ->assertJsonPath('data.0.duration_days', null);
    expect(LicenseEvent::query()->where('license_id', $license->id)->where('event', 'tool_permission_granted')->exists())->toBeTrue();
});

test('repeated permission saves preserve the key activation and administrator restrictions', function (string $status): void {
    $this->actingAs($this->admin)->patchJson($this->url, ['product_ids' => [$this->product->id]])->assertOk();
    $license = License::query()->sole();
    $license->update(['status' => $status, 'activated_at' => $status === 'active' ? now() : null]);
    $key = $license->encrypted_key;
    $this->patchJson($this->url, ['product_ids' => [$this->product->id]])->assertOk();
    $this->assertDatabaseCount('licenses', 1);
    $this->assertDatabaseCount('license_plans', 1);
    expect($license->fresh()->status)->toBe($status)->and($license->fresh()->encrypted_key)->toBe($key);
})->with(['unused', 'active', 'suspended', 'revoked']);

test('removing tool rights revokes only its managed licenses and sessions', function (): void {
    $second = LicenseProduct::factory()->create();
    $this->actingAs($this->admin)->patchJson($this->url, ['product_ids' => [$this->product->id, $second->id]])->assertOk();
    $license = License::query()->where('product_id', $this->product->id)->sole();
    $device = LicenseDevice::factory()->create(['license_id' => $license->id]);
    $license->update(['status' => 'active', 'generation' => 1, 'current_device_uuid' => $device->device_uuid]);
    $session = LicenseSession::factory()->create(['license_id' => $license->id, 'device_id' => $device->id]);
    $manual = License::factory()->create(['product_id' => $this->product->id, 'user_id' => $this->member->id, 'status' => 'active']);
    $other = License::factory()->create(['product_id' => $this->product->id, 'user_id' => User::factory()->create()->id, 'is_permission_grant' => true]);
    $this->patchJson($this->url, ['product_ids' => [$second->id]])->assertOk();
    expect($license->fresh()->status)->toBe('revoked')->and($license->fresh()->generation)->toBe(2)
        ->and($session->fresh()->status)->toBe('revoked')->and($device->fresh()->status)->toBe('inactive')
        ->and($manual->fresh()->status)->toBe('active')->and($other->fresh()->status)->toBe('unused');
    expect(License::query()->where('product_id', $second->id)->sole()->status)->toBe('unused');
});

test('granting again after removal creates a new key and keeps the old key revoked', function (): void {
    $this->actingAs($this->admin)->patchJson($this->url, ['product_ids' => [$this->product->id]])->assertOk();
    $old = License::query()->sole();
    $this->patchJson($this->url, ['product_ids' => []])->assertOk();
    $this->patchJson($this->url, ['product_ids' => [$this->product->id]])->assertOk();
    $this->assertDatabaseCount('licenses', 2);
    $new = License::query()->latest('id')->firstOrFail();
    expect($old->fresh()->status)->toBe('revoked')->and($new->status)->toBe('unused')
        ->and($new->encrypted_key)->not->toBe($old->encrypted_key);
});

test('saving legacy permissions reuses the existing user license without changing its restrictions', function (): void {
    $this->member->permittedTools()->attach($this->product);
    $manual = License::factory()->create(['user_id' => $this->member->id, 'product_id' => $this->product->id, 'status' => 'suspended']);
    $this->actingAs($this->admin)->patchJson($this->url, ['product_ids' => [$this->product->id]])->assertOk();
    expect(License::query()->where('is_permission_grant', true)->count())->toBe(0)
        ->and($manual->fresh()->status)->toBe('suspended');
});

test('disabled products receive a managed key that cannot activate until the product is enabled', function (): void {
    $this->product->update(['is_active' => false]);
    $this->actingAs($this->admin)->patchJson($this->url, ['product_ids' => [$this->product->id]])->assertOk();
    $license = License::query()->sole();
    $this->actingAs($this->member)->getJson('/api/account/licenses')->assertOk()->assertJsonPath('data.0.key', null)->assertJsonPath('data.0.can_activate', false);
    $this->product->update(['is_active' => true]);
    $this->getJson('/api/account/licenses')->assertOk()->assertJsonPath('data.0.key', $license->encrypted_key)->assertJsonPath('data.0.can_activate', true);
});

test('permissions licenses plans and events roll back together when issuing fails', function (): void {
    $this->mock(LicenseAdminService::class, function ($mock): void {
        $mock->shouldReceive('issueForToolPermission')->once()->andThrow(new RuntimeException('Issue failed'));
    });
    $this->actingAs($this->admin)->patchJson($this->url, ['product_ids' => [$this->product->id]])->assertServerError();
    $this->assertDatabaseCount('license_product_user', 0);
    $this->assertDatabaseCount('licenses', 0);
    $this->assertDatabaseCount('license_plans', 0);
    $this->assertDatabaseCount('license_events', 0);
});

test('managed perpetual plan is isolated from ordinary license issuance', function (): void {
    $paid = LicensePlan::factory()->create(['product_id' => $this->product->id, 'duration_days' => 30]);
    $this->actingAs($this->admin)->patchJson($this->url, ['product_ids' => [$this->product->id]])->assertOk();
    $managed = License::query()->sole();
    expect($managed->plan_id)->not->toBe($paid->id)->and($managed->plan->is_active)->toBeFalse();
    $this->postJson('/api/admin-api/license/keys', ['plan_id' => $paid->id, 'quantity' => 1, 'user_id' => $this->admin->id])->assertCreated();
    expect(License::query()->where('is_permission_grant', false)->sole()->duration_days)->toBe(30);
});

test('sync command backfills existing permissions once and exposes them in the dashboard', function (): void {
    $this->member->permittedTools()->attach($this->product);
    $this->artisan('licenses:sync-tool-permissions')->expectsOutput('Synced 1 accounts.')->assertSuccessful();
    $license = License::query()->sole();
    $this->artisan('licenses:sync-tool-permissions')->assertSuccessful();
    $this->assertDatabaseCount('licenses', 1);
    $this->assertDatabaseCount('license_plans', 1);
    $this->actingAs($this->member)->get(route('account.profile.dashboard'))->assertOk()->assertSee($license->encrypted_key);
    $this->member->permittedTools()->detach();
    $this->artisan('licenses:sync-tool-permissions')->assertSuccessful();
    expect($license->fresh()->status)->toBe('revoked');
});
