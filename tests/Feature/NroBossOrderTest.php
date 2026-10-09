<?php

use App\Models\Boss;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

test('boss order defaults to zero and updates persist without resetting on unrelated edits', function (): void {
    $payload = ['code' => 'BROLY', 'name' => 'Broly', 'is_active' => true];
    $id = $this->postJson('/api/admin-api/nro/bosses', $payload)->assertCreated()->assertJsonPath('data.sort_order', 0)->json('data.id');
    $this->patchJson('/api/admin-api/nro/bosses/'.$id, $payload + ['sort_order' => 20])->assertOk()->assertJsonPath('data.sort_order', 20);
    $this->patchJson('/api/admin-api/nro/bosses/'.$id, $payload)->assertOk()->assertJsonPath('data.sort_order', 20);
    $this->patchJson('/api/admin-api/nro/bosses/'.$id, $payload + ['sort_order' => 0])->assertOk()->assertJsonPath('data.sort_order', 0);
    $this->patchJson('/api/admin-api/nro/bosses/'.$id, $payload + ['sort_order' => 2147483647])->assertOk()->assertJsonPath('data.sort_order', 2147483647);
    $this->assertDatabaseHas('bosses', ['id' => $id, 'sort_order' => 2147483647]);
    expect(file_get_contents(resource_path('js/pages/admin/nro/bosses/index.vue')))->toContain('v-model.number="draft.sort_order"', 'Thứ tự bộ lọc', 'left.sort_order - right.sort_order', 'colspan="6"');
});

test('client filters and admin list follow order then name then id and updates reorder both', function (): void {
    $later = Boss::factory()->create(['name' => 'A later', 'sort_order' => 20]);
    $first = Boss::factory()->create(['name' => 'Z priority', 'sort_order' => 1]);
    $tieFirst = Boss::factory()->create(['name' => 'A tie', 'sort_order' => 10]);
    $tieSecond = Boss::factory()->create(['name' => 'A tie', 'sort_order' => 10]);
    $tieLast = Boss::factory()->create(['name' => 'B tie', 'sort_order' => 10]);
    $inactive = Boss::factory()->create(['name' => 'Inactive', 'sort_order' => 0, 'is_active' => false]);
    $deleted = Boss::factory()->create(['name' => 'Deleted', 'sort_order' => 0]);
    $deleted->delete();
    $expected = [$first->id, $tieFirst->id, $tieSecond->id, $tieLast->id, $later->id];
    $this->getJson('/api/admin-api/nro/bosses')->assertOk()->assertJsonPath('data', fn (array $data): bool => array_column($data, 'id') === [$inactive->id, ...$expected]);
    $this->get('/thong-bao-game?code=BOSS')->assertOk()->assertViewHas('bosses', fn ($bosses): bool => $bosses->modelKeys() === $expected);
    $this->patchJson('/api/admin-api/nro/bosses/'.$later->id, ['name' => $later->name, 'code' => $later->code, 'is_active' => true, 'sort_order' => 0])->assertOk();
    $expected = [$later->id, $first->id, $tieFirst->id, $tieSecond->id, $tieLast->id];
    $this->get('/thong-bao-game?code=BOSS')->assertOk()->assertViewHas('bosses', fn ($bosses): bool => $bosses->modelKeys() === $expected);
    $this->getJson('/api/admin-api/nro/bosses')->assertOk()->assertJsonPath('data', fn (array $data): bool => array_column($data, 'id') === [$later->id, $inactive->id, ...array_slice($expected, 1)]);
});

test('invalid boss orders are rejected on create and update', function (mixed $order): void {
    $boss = Boss::factory()->create(['sort_order' => 10]);
    $payload = ['name' => 'Boss', 'code' => 'NEW_BOSS', 'is_active' => true, 'sort_order' => $order];
    $this->postJson('/api/admin-api/nro/bosses', $payload)->assertUnprocessable()->assertJsonValidationErrors('sort_order');
    $payload['code'] = $boss->code;
    $this->patchJson('/api/admin-api/nro/bosses/'.$boss->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('sort_order');
    expect($boss->fresh()->sort_order)->toBe(10);
})->with([-1, 2147483648, 1.5, 'abc', null, '']);
