<?php

use App\Models\ServiceOffering;
use App\Models\User;

function payloadField(array $extra = []): array
{
    return ['name' => 'username', 'label' => 'Tên tài khoản', 'type' => 'text', 'required' => true, 'placeholder' => 'Nhập tài khoản', 'options' => [], ...$extra];
}

function payloadOfferingBody(array $extra = []): array
{
    return ['code' => 'payload_service', 'name' => 'Payload service', 'description' => null, 'url' => '/tin-tuc',
        'icon_type' => 'icon', 'icon' => 'bx-store', 'image_url' => null, 'is_enabled' => true,
        'maintenance_message' => 'Maintenance', 'sort_order' => 1, ...$extra];
}

test('every payload field type persists in SQL and admin responses', function (string $type): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $fields = [payloadField(['type' => $type, 'required' => false, 'options' => $type === 'select' ? [['value' => '1', 'label' => 'Server 1']] : []])];
    $this->postJson('/api/admin-api/settings/service-catalog', payloadOfferingBody(['payload_fields' => $fields]))
        ->assertCreated()->assertJsonPath('data.payload_fields', $fields);
    expect(ServiceOffering::query()->where('code', 'payload_service')->firstOrFail()->payload_fields)->toBe($fields);
    $this->getJson('/api/admin-api/settings/service-catalog')->assertOk()->assertJsonPath('data.0.payload_fields', $fields);
})->with(['text', 'textarea', 'password', 'email', 'number', 'boolean', 'select']);

test('editing reorders payload fields omission preserves them and empty list clears configuration', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $fields = [payloadField(), payloadField(['name' => 'password', 'label' => 'Mật khẩu', 'type' => 'password'])];
    ServiceOffering::factory()->create(['code' => 'payload_service', 'payload_fields' => $fields]);
    $this->patchJson('/api/admin-api/settings/service-catalog/payload_service', payloadOfferingBody())
        ->assertOk()->assertJsonPath('data.payload_fields', $fields);
    $reordered = array_reverse($fields);
    $this->patchJson('/api/admin-api/settings/service-catalog/payload_service', payloadOfferingBody(['payload_fields' => $reordered]))
        ->assertOk()->assertJsonPath('data.payload_fields', $reordered);
    $this->patchJson('/api/admin-api/settings/service-catalog/payload_service', payloadOfferingBody(['payload_fields' => []]))
        ->assertOk()->assertJsonPath('data.payload_fields', []);
    expect(ServiceOffering::query()->where('code', 'payload_service')->firstOrFail()->payload_fields)->toBe([]);
});

test('legacy services expose an empty payload configuration', function (): void {
    ServiceOffering::factory()->create(['code' => 'payload_service']);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin-api/settings/service-catalog')
        ->assertOk()->assertJsonPath('data.0.payload_fields', []);
});

test('invalid payload schemas cannot be persisted', function (mixed $fields, string $key): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/admin-api/settings/service-catalog', payloadOfferingBody(['payload_fields' => $fields]))
        ->assertUnprocessable()->assertJsonValidationErrors($key);
    $this->assertDatabaseCount('service_offerings', 0);
})->with([
    'not list' => ['invalid', 'payload_fields'],
    'null' => [null, 'payload_fields'],
    'associative' => [['field' => payloadField()], 'payload_fields'],
    'invalid field' => [['invalid'], 'payload_fields.0'],
    'duplicate names' => [[payloadField(), payloadField()], 'payload_fields.0.name'],
    'invalid name' => [[payloadField(['name' => 'user.name'])], 'payload_fields.0.name'],
    'reserved name' => [[payloadField(['name' => 'constructor'])], 'payload_fields.0.name'],
    'empty label' => [[payloadField(['label' => ''])], 'payload_fields.0.label'],
    'invalid type' => [[payloadField(['type' => 'script'])], 'payload_fields.0.type'],
    'invalid required' => [[payloadField(['required' => 'yes'])], 'payload_fields.0.required'],
    'long placeholder' => [[payloadField(['placeholder' => str_repeat('x', 201)])], 'payload_fields.0.placeholder'],
    'select without options' => [[payloadField(['type' => 'select'])], 'payload_fields.0.options'],
    'non select options' => [[payloadField(['options' => [['value' => '1', 'label' => 'One']]])], 'payload_fields.0.options'],
    'select duplicated value' => [[payloadField(['type' => 'select', 'options' => [['value' => '1', 'label' => 'One'], ['value' => '1', 'label' => 'Another']]])], 'payload_fields.0.options'],
    'select empty label' => [[payloadField(['type' => 'select', 'options' => [['value' => '1', 'label' => '']]])], 'payload_fields.0.options.0.label'],
    'unknown field key' => [[payloadField(['value' => 'password-data'])], 'payload_fields.0'],
    'unknown option key' => [[payloadField(['type' => 'select', 'options' => [['value' => '1', 'label' => 'One', 'extra' => 'bad']]])], 'payload_fields.0.options.0'],
    'too many fields' => [array_map(fn (int $index): array => payloadField(['name' => 'field'.$index]), range(1, 51)), 'payload_fields'],
]);

test('separate select fields can reuse values and failed updates preserve saved configuration', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $first = payloadField(['type' => 'select', 'options' => [['value' => '1', 'label' => 'One']]]);
    $fields = [$first, [...$first, 'name' => 'server']];
    ServiceOffering::factory()->create(['code' => 'payload_service', 'payload_fields' => $fields]);
    $this->patchJson('/api/admin-api/settings/service-catalog/payload_service', payloadOfferingBody(['payload_fields' => $fields]))
        ->assertOk()->assertJsonPath('data.payload_fields', $fields);
    $this->patchJson('/api/admin-api/settings/service-catalog/payload_service', payloadOfferingBody(['payload_fields' => [payloadField(['type' => 'invalid'])]]))
        ->assertUnprocessable();
    expect(ServiceOffering::query()->where('code', 'payload_service')->firstOrFail()->payload_fields)->toBe($fields);
});

test('admin payload form offers adding deleting reordering and editing field definitions', function (): void {
    $page = file_get_contents(resource_path('js/pages/admin/services/index.vue'));
    expect($page)->toContain('Payload nhận vào', 'v-model="field.name"', 'v-model="field.label"', 'v-model="field.type"', 'v-model="field.required"',
        'v-model="field.placeholder"', 'v-model="option.value"', 'v-model="option.label"', 'addPayloadField', 'movePayloadField', 'draft.payload_fields.splice(index, 1)');
    $client = file_get_contents(resource_path('js/services/admin-service-catalog.service.ts'));
    expect($client)->toContain('payload_fields: service.payload_fields');
});
