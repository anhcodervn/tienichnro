<?php

use App\Models\License;
use App\Models\LicensePlan;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->member = User::factory()->create(['role' => User::ROLE_USER]);
    $this->plan = LicensePlan::factory()->create();
    $this->actingAs($this->admin);
});

test('a user can receive one license per tool but not another plan for the same tool', function (): void {
    $data = ['plan_id' => $this->plan->id, 'user_id' => $this->member->id, 'quantity' => 1];
    $first = $this->postJson('/api/admin-api/license/keys', $data)->assertCreated()->json('data.0.id');
    $this->postJson('/api/admin-api/license/keys', $data)->assertUnprocessable()->assertJsonValidationErrors('user_id');
    $secondPlan = LicensePlan::factory()->create(['product_id' => $this->plan->product_id]);
    $this->postJson('/api/admin-api/license/keys', [...$data, 'plan_id' => $secondPlan->id])->assertUnprocessable();
    $otherToolPlan = LicensePlan::factory()->create();
    $this->postJson('/api/admin-api/license/keys', [...$data, 'plan_id' => $otherToolPlan->id])->assertCreated();
    expect(License::query()->where('user_id', $this->member->id)->count())->toBe(2);
    expect(License::findOrFail($first)->status)->toBe('unused');
});

test('a batch for one user and one tool fails atomically', function (): void {
    $this->postJson('/api/admin-api/license/keys', ['plan_id' => $this->plan->id, 'user_id' => $this->member->id, 'quantity' => 2])
        ->assertUnprocessable()->assertJsonValidationErrors('user_id');
    $this->assertDatabaseCount('licenses', 0);
    $this->assertDatabaseCount('license_events', 0);
});

test('admins can own multiple licenses for the same tool including batch issuance', function (): void {
    $data = ['plan_id' => $this->plan->id, 'user_id' => $this->admin->id, 'quantity' => 3];
    $this->postJson('/api/admin-api/license/keys', $data)->assertCreated()->assertJsonCount(3, 'data');
    $this->postJson('/api/admin-api/license/keys', [...$data, 'quantity' => 1])->assertCreated();
    expect(License::query()->where('user_id', $this->admin->id)->where('product_id', $this->plan->product_id)->count())->toBe(4);
});

test('only revoked history releases a user license slot', function (string $status): void {
    $existing = License::factory()->create(['plan_id' => $this->plan->id, 'user_id' => $this->member->id, 'status' => $status]);
    $response = $this->postJson('/api/admin-api/license/keys', ['plan_id' => $this->plan->id, 'user_id' => $this->member->id, 'quantity' => 1]);
    if ($status === 'revoked') {
        $response->assertCreated();
        $this->assertDatabaseCount('licenses', 2);
    } else {
        $response->assertUnprocessable();
        $this->assertDatabaseCount('licenses', 1);
    }
    expect($existing->fresh()->status)->toBe($status);
})->with(['unused', 'active', 'expired', 'suspended', 'revoked']);

test('reassigning a license respects the destination users per tool limit', function (): void {
    $existing = License::factory()->create(['plan_id' => $this->plan->id, 'user_id' => $this->member->id]);
    $unassigned = License::factory()->create(['plan_id' => $this->plan->id, 'user_id' => null]);
    $data = ['action' => 'edit', 'user_id' => $this->member->id, 'duration_days' => $unassigned->duration_days,
        'expires_at' => null, 'transfer_cooldown' => 1800, 'reason' => 'Assign license'];
    $this->patchJson('/api/admin-api/license/keys/'.$unassigned->id, $data)->assertUnprocessable()->assertJsonValidationErrors('user_id');
    expect($unassigned->fresh()->user_id)->toBeNull();
    $this->patchJson('/api/admin-api/license/keys/'.$existing->id, $data)->assertOk();
    $this->patchJson('/api/admin-api/license/keys/'.$unassigned->id, [...$data, 'user_id' => $this->admin->id])->assertOk();
    expect($unassigned->fresh()->user_id)->toBe($this->admin->id);
});

test('granting tool permissions reuses an existing user license while admins can have an additional managed license', function (string $role): void {
    $this->member->update(['role' => $role]);
    $existing = License::factory()->create(['user_id' => $this->member->id, 'plan_id' => $this->plan->id, 'status' => 'active', 'encrypted_key' => 'EXISTING-KEY']);
    $this->patchJson('/api/admin-api/users/'.$this->member->id.'/tools', ['product_ids' => [$this->plan->product_id]])->assertOk();
    expect(License::query()->where('user_id', $this->member->id)->count())->toBe($role === User::ROLE_USER ? 1 : 2)
        ->and($existing->fresh()->encrypted_key)->toBe('EXISTING-KEY');
    $this->artisan('licenses:sync-tool-permissions')->assertSuccessful();
    expect(License::query()->where('user_id', $this->member->id)->count())->toBe($role === User::ROLE_USER ? 1 : 2);
})->with([User::ROLE_USER, User::ROLE_ADMIN]);

test('manual issuance cannot duplicate an automatically granted user license', function (): void {
    $this->patchJson('/api/admin-api/users/'.$this->member->id.'/tools', ['product_ids' => [$this->plan->product_id]])->assertOk();
    $this->postJson('/api/admin-api/license/keys', ['plan_id' => $this->plan->id, 'user_id' => $this->member->id, 'quantity' => 1])->assertUnprocessable();
    $this->assertDatabaseCount('licenses', 1);
});
