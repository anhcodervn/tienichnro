<?php

use App\Features\Affiliate\Events\AffiliateDashboardUpdated;
use App\Models\AffiliateProfile;
use App\Models\GameServiceOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Support\GameServicePayloadCipher;
use App\Support\GameServiceSecondaryAuth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    Event::fake([AffiliateDashboardUpdated::class]);
    config()->set('cache.default', 'array');
    config()->set('services.game_service_secondary_auth.password', 'shared-secondary-secret');
    config()->set('services.game_service_secondary_auth.ttl_minutes', 30);
    Cache::flush();
});

test('admin unlocks customer order payload with the shared secondary password', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $order = GameServiceOrder::factory()->create([
        'payload' => ['account' => 'customer-account', 'password' => 'customer-password'],
    ]);
    $storedPayload = (string) DB::table('game_service_orders')->where('id', $order->id)->value('payload');

    expect($storedPayload)
        ->not->toContain('customer-account')
        ->not->toContain('customer-password')
        ->and(json_decode($storedPayload, true))->toBeNull();
    expect($order->toArray())->not->toHaveKey('payload');

    $this->actingAs($admin)
        ->getJson('/api/client/affiliate/game-service-secondary-auth')
        ->assertSuccessful()
        ->assertJsonPath('data.configured', true)
        ->assertJsonPath('data.unlocked', false);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/game-service-orders?search='.$order->code)
        ->assertSuccessful()
        ->assertJsonPath('data.data.0.payload', [])
        ->assertJsonPath('data.data.0.payload_locked', true)
        ->assertJsonMissing(['account' => 'customer-account']);

    $this->actingAs($admin)
        ->getJson("/api/admin-api/game-service-orders/{$order->code}/payload")
        ->assertStatus(423)
        ->assertJsonPath('code', 'SECONDARY_PASSWORD_REQUIRED');

    $this->actingAs($admin)
        ->postJson('/api/client/affiliate/game-service-secondary-auth', ['password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $unlock = $this->actingAs($admin)
        ->postJson('/api/client/affiliate/game-service-secondary-auth', ['password' => 'shared-secondary-secret'])
        ->assertSuccessful()
        ->assertJsonStructure(['data' => ['token', 'expires_at', 'expires_in_minutes']]);

    $headers = [GameServiceSecondaryAuth::HEADER_NAME => $unlock->json('data.token')];

    $this->actingAs($admin)
        ->getJson('/api/client/affiliate/game-service-secondary-auth', $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.unlocked', true);

    $this->actingAs($admin)
        ->getJson("/api/admin-api/game-service-orders/{$order->code}/payload", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.payload.account', 'customer-account')
        ->assertJsonPath('data.payload.password', 'customer-password')
        ->assertHeader('Cache-Control', 'no-store, private');

    config()->set('services.game_service_secondary_auth.password', 'rotated-secondary-secret');

    $this->actingAs($admin)
        ->getJson("/api/admin-api/game-service-orders/{$order->code}/payload", $headers)
        ->assertStatus(423);
});

test('existing plaintext payloads are encrypted by the data migration', function (): void {
    $order = GameServiceOrder::factory()->create();
    $legacyPayload = json_encode([
        'account' => 'legacy-account',
        'password' => 'legacy-password',
    ], JSON_THROW_ON_ERROR);
    DB::table('game_service_orders')->where('id', $order->id)->update(['payload' => $legacyPayload]);

    $migration = require database_path('migrations/2026_09_30_091048_encrypt_existing_game_service_order_payloads.php');
    $migration->up();

    $storedPayload = (string) DB::table('game_service_orders')->where('id', $order->id)->value('payload');

    expect($storedPayload)
        ->not->toBe($legacyPayload)
        ->not->toContain('legacy-account')
        ->not->toContain('legacy-password')
        ->and(app(GameServicePayloadCipher::class)->decrypt((string) $order->refresh()->getRawOriginal('payload')))->toBe([
            'account' => 'legacy-account',
            'password' => 'legacy-password',
        ]);
});

test('collaborator must unlock before viewing assigned payload or performing protected work', function (): void {
    $tenant = Tenant::query()->where('is_main', true)->firstOrFail();
    $collaborator = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_COLLABORATOR,
    ]);
    $collaborator->forceFill(['game_service_secondary_password' => 'collaborator-secondary-123'])->save();
    AffiliateProfile::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $collaborator->id,
        'status' => 'active',
    ]);
    $order = GameServiceOrder::factory()->create([
        'collaborator_id' => $collaborator->id,
        'status' => 'pending',
        'payload' => ['account' => 'assigned-account', 'password' => 'assigned-password'],
    ]);
    $foreignOrder = GameServiceOrder::factory()->create([
        'status' => 'pending',
        'payload' => ['account' => 'foreign-account'],
    ]);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-orders?search='.$order->code)
        ->assertSuccessful()
        ->assertJsonPath('data.data.0.payload', [])
        ->assertJsonPath('data.data.0.payload_locked', true);

    $this->actingAs($collaborator)
        ->getJson("/api/client/affiliate/game-service-orders/{$order->code}/payload")
        ->assertStatus(423);

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$order->code}/start")
        ->assertStatus(423);

    $this->actingAs($collaborator)
        ->postJson('/api/client/affiliate/game-service-withdrawals', [])
        ->assertStatus(423);

    $this->actingAs($collaborator)
        ->postJson('/api/client/affiliate/game-service-secondary-auth', ['password' => 'shared-secondary-secret'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $unlock = $this->actingAs($collaborator)
        ->postJson('/api/client/affiliate/game-service-secondary-auth', ['password' => 'collaborator-secondary-123'])
        ->assertSuccessful();
    $headers = [GameServiceSecondaryAuth::HEADER_NAME => $unlock->json('data.token')];

    $this->actingAs($collaborator)
        ->getJson("/api/client/affiliate/game-service-orders/{$order->code}/payload", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.payload.account', 'assigned-account')
        ->assertJsonPath('data.payload.password', 'assigned-password');

    $this->actingAs($collaborator)
        ->getJson("/api/client/affiliate/game-service-orders/{$foreignOrder->code}/payload", $headers)
        ->assertForbidden();

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$order->code}/start", [], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.payload', [])
        ->assertJsonPath('data.payload_locked', true);

    $this->actingAs($collaborator)
        ->postJson('/api/client/affiliate/game-service-withdrawals', [], $headers)
        ->assertUnprocessable();
});

test('admin sets a private secondary password for each collaborator and rotating it revokes old grants', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
    $otherCollaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
    $regularUser = User::factory()->create(['role' => User::ROLE_USER]);
    $order = GameServiceOrder::factory()->create([
        'collaborator_id' => $collaborator->id,
        'payload' => ['account' => 'private-account'],
    ]);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-secondary-auth')
        ->assertSuccessful()
        ->assertJsonPath('data.configured', false);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/users/{$regularUser->id}/game-service-secondary-password", [
            'password' => 'regular-secret-123',
            'password_confirmation' => 'regular-secret-123',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user');

    $this->actingAs($regularUser)
        ->putJson("/api/admin-api/users/{$collaborator->id}/game-service-secondary-password", [
            'password' => 'collaborator-secret-123',
            'password_confirmation' => 'collaborator-secret-123',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->putJson("/api/admin-api/users/{$collaborator->id}/game-service-secondary-password", [
            'password' => 'collaborator-secret-123',
            'password_confirmation' => 'collaborator-secret-123',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.has_game_service_secondary_password', true)
        ->assertJsonMissingPath('data.game_service_secondary_password');

    $storedPassword = (string) DB::table('users')->where('id', $collaborator->id)->value('game_service_secondary_password');
    expect($storedPassword)
        ->not->toBe('collaborator-secret-123')
        ->and(Hash::check('collaborator-secret-123', $storedPassword))->toBeTrue();

    $this->actingAs($admin)
        ->getJson("/api/admin-api/users/{$collaborator->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.has_game_service_secondary_password', true)
        ->assertJsonMissingPath('data.game_service_secondary_password');

    $collaborator->refresh();
    $oldGrant = $this->actingAs($collaborator)
        ->postJson('/api/client/affiliate/game-service-secondary-auth', ['password' => 'collaborator-secret-123'])
        ->assertSuccessful()
        ->json('data.token');
    $oldHeaders = [GameServiceSecondaryAuth::HEADER_NAME => $oldGrant];

    $this->actingAs($collaborator)
        ->getJson("/api/client/affiliate/game-service-orders/{$order->code}/payload", $oldHeaders)
        ->assertSuccessful();

    $this->actingAs($admin)
        ->putJson("/api/admin-api/users/{$collaborator->id}/game-service-secondary-password", [
            'password' => 'rotated-secret-456',
            'password_confirmation' => 'rotated-secret-456',
        ])
        ->assertSuccessful();

    $collaborator->refresh();
    $this->actingAs($collaborator)
        ->getJson("/api/client/affiliate/game-service-orders/{$order->code}/payload", $oldHeaders)
        ->assertStatus(423);

    $this->actingAs($collaborator)
        ->postJson('/api/client/affiliate/game-service-secondary-auth', ['password' => 'collaborator-secret-123'])
        ->assertUnprocessable();

    $this->actingAs($collaborator)
        ->postJson('/api/client/affiliate/game-service-secondary-auth', ['password' => 'rotated-secret-456'])
        ->assertSuccessful();

    $otherCollaborator->forceFill(['game_service_secondary_password' => 'other-secret-789'])->save();

    $this->actingAs($otherCollaborator)
        ->postJson('/api/client/affiliate/game-service-secondary-auth', ['password' => 'rotated-secret-456'])
        ->assertUnprocessable();

    $this->actingAs($otherCollaborator)
        ->postJson('/api/client/affiliate/game-service-secondary-auth', ['password' => 'other-secret-789'])
        ->assertSuccessful();
});

test('secondary password protection fails closed when the environment value is missing', function (): void {
    config()->set('services.game_service_secondary_auth.password', null);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $order = GameServiceOrder::factory()->create();

    $this->actingAs($admin)
        ->getJson('/api/client/affiliate/game-service-secondary-auth')
        ->assertSuccessful()
        ->assertJsonPath('data.configured', false)
        ->assertJsonPath('data.unlocked', false);

    $this->actingAs($admin)
        ->postJson('/api/client/affiliate/game-service-secondary-auth', ['password' => 'anything'])
        ->assertStatus(503);

    $this->actingAs($admin)
        ->getJson("/api/admin-api/game-service-orders/{$order->code}/payload")
        ->assertStatus(503)
        ->assertJsonPath('code', 'SECONDARY_PASSWORD_NOT_CONFIGURED');
});
