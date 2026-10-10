<?php

use App\Features\Admin\Setting\Services\ServiceCatalogService;
use App\Models\ServiceOffering;
use App\Models\ServicePackage;
use App\Models\User;

beforeEach(function (): void {
    config(['license.services_visible' => true]);
    ServiceOffering::factory()->create(['code' => 'test_service', 'name' => 'Test service']);
});

function packagePayload(array $extra = []): array
{
    return ['service_code' => 'test_service', 'name' => 'Test package', 'description' => 'Package description',
        'price' => 50000, 'billing_type' => 'time', 'duration_days' => 30, 'usage_limit' => null,
        'is_active' => true, 'sort_order' => 1, ...$extra];
}

test('generic admin package configuration excludes notification delivery mode', function (?string $mode): void {
    $id = $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/admin-api/settings/service-packages', packagePayload(['notification_mode' => $mode]))
        ->assertCreated()->assertJsonMissingPath('data.notification_mode')->json('data.id');
    expect(ServicePackage::query()->findOrFail($id)->notification_mode)->toBeNull();
})->with([null, 'personal', 'webhook']);

test('generic package updates preserve existing delivery data without configuring it', function (): void {
    $package = ServicePackage::factory()->create(['service_code' => 'test_service', 'notification_mode' => 'personal']);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->patchJson('/api/admin-api/settings/service-packages/'.$package->id, packagePayload(['notification_mode' => 'unknown']))
        ->assertOk()->assertJsonMissingPath('data.notification_mode');
    expect($package->fresh()->notification_mode)->toBe('personal');
    $this->getJson('/api/admin-api/settings/service-packages')->assertOk()->assertJsonMissingPath('data.0.notification_mode');
});

test('admin can create update sort and delete every package type', function (string $type, ?int $usage, ?int $days): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $payload = packagePayload(['billing_type' => $type, 'usage_limit' => $usage, 'duration_days' => $days]);
    $id = $this->postJson('/api/admin-api/settings/service-packages', $payload)->assertCreated()
        ->assertJsonPath('data.billing_type', $type)->assertJsonPath('data.usage_limit', $usage)->assertJsonPath('data.duration_days', $days)->json('data.id');
    $this->patchJson('/api/admin-api/settings/service-packages/'.$id, [...$payload, 'price' => 100000, 'is_active' => false, 'sort_order' => 3])
        ->assertOk()->assertJsonPath('data.price', 100000)->assertJsonPath('data.is_active', false);
    $this->getJson('/api/admin-api/settings/service-packages')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
    $this->deleteJson('/api/admin-api/settings/service-packages/'.$id)->assertNoContent();
    $this->assertDatabaseMissing('service_packages', ['id' => $id]);
})->with([['usage', 100, null], ['time', null, 30], ['lifetime', null, null]]);

test('changing package type clears incompatible entitlements', function (): void {
    $package = ServicePackage::factory()->create(['service_code' => 'test_service', 'billing_type' => 'usage', 'usage_limit' => 50, 'duration_days' => null]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patchJson('/api/admin-api/settings/service-packages/'.$package->id, packagePayload(['billing_type' => 'lifetime', 'duration_days' => null]))
        ->assertOk()->assertJsonPath('data.usage_limit', null)->assertJsonPath('data.duration_days', null);
    $this->patchJson('/api/admin-api/settings/service-packages/'.$package->id, packagePayload())->assertOk()->assertJsonPath('data.duration_days', 30)->assertJsonPath('data.usage_limit', null);
});

test('package writes reject invalid service price and entitlement combinations', function (array $extra, string $field): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/admin-api/settings/service-packages', packagePayload($extra))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('service_packages', 0);
})->with([
    [['service_code' => 'missing'], 'service_code'], [['price' => -1], 'price'], [['price' => 0.5], 'price'],
    [['name' => ''], 'name'], [['billing_type' => 'invalid'], 'billing_type'],
    [['billing_type' => 'usage', 'usage_limit' => null, 'duration_days' => null], 'usage_limit'],
    [['duration_days' => 0], 'duration_days'], [['billing_type' => 'lifetime', 'duration_days' => 30], 'duration_days'],
    [['usage_limit' => 5], 'usage_limit'],
]);

test('package management is protected against guests and ordinary members', function (): void {
    $package = ServicePackage::factory()->create(['service_code' => 'test_service']);
    foreach ([['GET', '/api/admin-api/settings/service-packages'], ['POST', '/api/admin-api/settings/service-packages'],
        ['PATCH', '/api/admin-api/settings/service-packages/'.$package->id], ['DELETE', '/api/admin-api/settings/service-packages/'.$package->id]] as [$method, $uri]) {
        $this->json($method, $uri, packagePayload())->assertUnauthorized();
    }
    $this->actingAs(User::factory()->create(['role' => 'user']));
    foreach ([['GET', '/api/admin-api/settings/service-packages'], ['POST', '/api/admin-api/settings/service-packages'],
        ['PATCH', '/api/admin-api/settings/service-packages/'.$package->id], ['DELETE', '/api/admin-api/settings/service-packages/'.$package->id]] as [$method, $uri]) {
        $this->json($method, $uri, packagePayload())->assertForbidden();
    }
    $this->assertDatabaseCount('service_packages', 1);
});

test('public catalog sorts packages hides inactive and removed services and escapes content', function (): void {
    $late = ServicePackage::factory()->create(['service_code' => 'test_service', 'name' => 'Late package', 'sort_order' => 20]);
    $early = ServicePackage::factory()->create(['service_code' => 'test_service', 'name' => '<script>bad()</script>', 'billing_type' => 'lifetime', 'duration_days' => null, 'sort_order' => 1]);
    ServicePackage::factory()->create(['service_code' => 'test_service', 'name' => 'Inactive secret', 'is_active' => false]);
    ServicePackage::factory()->create(['service_code' => 'removed_service', 'name' => 'Orphan secret']);
    $page = $this->get('/dich-vu')->assertOk()->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertSee('Vĩnh viễn')
        ->assertDontSee('Inactive secret')->assertDontSee('Orphan secret')->assertDontSee('<script>bad()</script>', false);
    expect(strpos($page->getContent(), 'data-package-id="'.$early->id.'"'))->toBeLessThan(strpos($page->getContent(), 'data-package-id="'.$late->id.'"'));
    app(ServiceCatalogService::class)->update('test_service', ['is_enabled' => false, 'maintenance_message' => 'Service under maintenance']);
    $this->get('/dich-vu')->assertOk()->assertSee('Service under maintenance')->assertDontSee('Late package');
    app(ServiceCatalogService::class)->delete('test_service');
    $this->get('/dich-vu')->assertOk()->assertDontSee('Test service')->assertDontSee('Late package');
    $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/admin-api/settings/service-packages', packagePayload())
        ->assertUnprocessable()->assertJsonValidationErrors('service_code');
});

test('package list uses stable display order and unknown ids cannot be updated or deleted', function (): void {
    $last = ServicePackage::factory()->create(['service_code' => 'test_service', 'sort_order' => 10]);
    $first = ServicePackage::factory()->create(['service_code' => 'test_service', 'sort_order' => 1]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin-api/settings/service-packages')
        ->assertOk()->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.1.id', $last->id);
    $this->patchJson('/api/admin-api/settings/service-packages/999999', packagePayload())->assertNotFound();
    $this->deleteJson('/api/admin-api/settings/service-packages/999999')->assertNotFound();
});

test('public catalog rejects unsafe configured links and marks service navigation active', function (): void {
    ServiceOffering::query()->where('code', 'test_service')->update(['url' => 'javascript:alert(1)']);
    $response = $this->get('/dich-vu')->assertOk()->assertDontSee('href="javascript:', false);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//header//nav//a[@href="'.route('services.index').'" and @aria-current="page"]')->length)->toBe(1);
});
