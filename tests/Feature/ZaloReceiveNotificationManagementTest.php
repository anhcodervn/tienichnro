<?php

use App\Models\CodeNotify;
use App\Models\NroServer;
use App\Models\User;
use App\Models\ZaloReceiveNotification;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
});

function zaloReceiverPayload(array $extra = []): array
{
    return ['box_zalo_id' => '000box1', 'zalo_id' => '000receiver1', 'char_name' => 'NhânVật1', 'char_server' => 1, 'type_receive' => 'BOSS,OTHER', ...$extra];
}

test('admin can create list update and delete direct and box Zalo subscriptions', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $id = $this->postJson('/api/admin-api/nro/zalo-receivers', zaloReceiverPayload(['type_receive' => ' boss, other, BOSS, ,', 'char_name' => ' NhânVật1 ']))
        ->assertCreated()->assertJsonPath('data.type_receive', 'BOSS,OTHER')->assertJsonPath('data.char_name', 'NhânVật1')->assertJsonPath('data.char_server', 1)
        ->assertJsonPath('data.zalo_id', '000receiver1')->assertJsonPath('data.box_zalo_id', '000box1')->json('data.id');
    $this->postJson('/api/admin-api/nro/zalo-receivers', zaloReceiverPayload(['box_zalo_id' => '', 'char_name' => 'NhânVật2']))
        ->assertCreated()->assertJsonPath('data.box_zalo_id', null);
    $this->getJson('/api/admin-api/nro/zalo-receivers')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.char_name', 'NhânVật2');
    $this->patchJson('/api/admin-api/nro/zalo-receivers/'.$id, zaloReceiverPayload(['char_server' => 2, 'box_zalo_id' => null, 'type_receive' => 'set_activation', 'char_name' => 'NhânVật3', 'id' => 99999]))
        ->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.type_receive', 'SET_ACTIVATION')->assertJsonPath('data.box_zalo_id', null)->assertJsonPath('data.char_server', 2);
    $this->assertDatabaseHas('zalo_receive_notifications', ['id' => $id, 'char_name' => 'NhânVật3', 'type_receive' => 'SET_ACTIVATION']);
    $this->deleteJson('/api/admin-api/nro/zalo-receivers/'.$id)->assertNoContent();
    $this->assertDatabaseMissing('zalo_receive_notifications', ['id' => $id]);
});

test('Zalo subscription management is restricted to administrators', function (): void {
    $receiver = ZaloReceiveNotification::factory()->create();
    $requests = [['GET', '/api/admin-api/nro/zalo-receivers'], ['POST', '/api/admin-api/nro/zalo-receivers'],
        ['PATCH', '/api/admin-api/nro/zalo-receivers/'.$receiver->id], ['DELETE', '/api/admin-api/nro/zalo-receivers/'.$receiver->id]];
    foreach ($requests as [$method, $uri]) {
        $this->json($method, $uri, zaloReceiverPayload())->assertUnauthorized();
    }
    $this->actingAs(User::factory()->create(['role' => 'user']));
    foreach ($requests as [$method, $uri]) {
        $this->json($method, $uri, zaloReceiverPayload())->assertForbidden();
    }
    $this->assertDatabaseCount('zalo_receive_notifications', 1);
});

test('invalid Zalo subscription fields are rejected without writing data', function (array $extra, string $key): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/admin-api/nro/zalo-receivers', zaloReceiverPayload($extra))
        ->assertUnprocessable()->assertJsonValidationErrors($key);
    $this->assertDatabaseCount('zalo_receive_notifications', 0);
})->with([
    [['zalo_id' => ''], 'zalo_id'], [['zalo_id' => 123], 'zalo_id'], [['zalo_id' => str_repeat('1', 129)], 'zalo_id'],
    [['box_zalo_id' => []], 'box_zalo_id'], [['box_zalo_id' => str_repeat('1', 129)], 'box_zalo_id'],
    [['char_name' => '   '], 'char_name'], [['char_name' => str_repeat('x', 101)], 'char_name'],
    [['type_receive' => ' , , '], 'type_receive'], [['type_receive' => ['BOSS']], 'type_receive'],
    [['type_receive' => 'BOSS,UNKNOWN'], 'type_receive'], [['type_receive' => 'BOSS_EXTRA'], 'type_receive'],
    [['type_receive' => str_repeat('A', 1001)], 'type_receive'],
    [['char_server' => null], 'char_server'], [['char_server' => -1], 'char_server'], [['char_server' => 1.5], 'char_server'],
    [['char_server' => 99999], 'char_server'], [['char_server' => 'invalid'], 'char_server'],
]);

test('deleted notification types cannot be selected and failed updates retain existing data', function (): void {
    $receiver = ZaloReceiveNotification::factory()->create(['type_receive' => 'BOSS']);
    CodeNotify::query()->where('code', 'OTHER')->firstOrFail()->delete();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->patchJson('/api/admin-api/nro/zalo-receivers/'.$receiver->id, zaloReceiverPayload(['type_receive' => 'OTHER']))
        ->assertUnprocessable()->assertJsonValidationErrors('type_receive');
    expect($receiver->refresh()->type_receive)->toBe('BOSS');
});

test('missing Zalo registrations return not found and omitted box retains its value', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patchJson('/api/admin-api/nro/zalo-receivers/999999', zaloReceiverPayload())->assertNotFound();
    $this->deleteJson('/api/admin-api/nro/zalo-receivers/999999')->assertNotFound();
    $receiver = ZaloReceiveNotification::factory()->create(['box_zalo_id' => '000box']);
    $payload = zaloReceiverPayload();
    unset($payload['box_zalo_id']);
    $this->patchJson('/api/admin-api/nro/zalo-receivers/'.$receiver->id, $payload)->assertOk()->assertJsonPath('data.box_zalo_id', '000box');
});

test('Zalo admin UI provides shared table modal editing notification selection and deletion confirmation', function (): void {
    $page = file_get_contents(resource_path('js/pages/admin/nro/zalo-receivers/index.vue'));
    expect($page)->toContain('<DataTable', '<Dialog', 'v-model="draft.char_name"', 'v-model="draft.zalo_id"', 'v-model="draft.box_zalo_id"', 'v-model="selectedTypes"', 'showCancelButton: true', 'nroNotificationService.notificationTypes()', 'adminZaloReceivers.save', 'adminZaloReceivers.remove');
    expect($page)->toContain('v-model="draft.char_server"', ':value="server.server_code"', 'nroNotificationService.servers()');
});

test('legacy subscriptions require choosing a server on edit and server codes are independent of database ids', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $receiver = ZaloReceiveNotification::factory()->create(['char_server' => null]);
    $this->getJson('/api/admin-api/nro/zalo-receivers')->assertOk()->assertJsonPath('data.0.char_server', null);
    $payload = zaloReceiverPayload();
    unset($payload['char_server']);
    $this->patchJson('/api/admin-api/nro/zalo-receivers/'.$receiver->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('char_server');
    NroServer::query()->create(['server_code' => 0, 'name' => 'Server zero', 'code' => 'zero', 'sort_order' => 0, 'is_active' => true]);
    $this->patchJson('/api/admin-api/nro/zalo-receivers/'.$receiver->id, zaloReceiverPayload(['char_server' => 0]))->assertOk()->assertJsonPath('data.char_server', 0);
});
