<?php

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\EquipmentUpgrade\Services\EquipmentUpgradeCalculatorService;

beforeEach(function (): void {
    app(ToolAvailabilityService::class)->create([
        'code' => 'admin_equipment_tool', 'name' => 'Tính chỉ số trang bị nâng cấp NRO', 'description' => '',
        'url' => '/tinh-chi-so-trang-bi-nang-cap', 'is_enabled' => true, 'maintenance_message' => 'Đang cập nhật chỉ số.',
    ]);
});

test('equipment calculator uses an admin definition with any code and absolute URL', function (): void {
    app(ToolAvailabilityService::class)->update('admin_equipment_tool', [
        'is_enabled' => true, 'maintenance_message' => '', 'url' => route('tools.equipment-upgrade').'/',
    ]);
    $this->get(route('tools.equipment-upgrade'))->assertOk()->assertSee('name="current_stat"', false);
    expect(collect(config('tools.items'))->contains('code', 'equipment_upgrade_calculator'))->toBeFalse();
});

test('equipment page works independently before its catalog entry is added in admin', function (): void {
    app(ToolAvailabilityService::class)->delete('admin_equipment_tool');
    $this->get(route('home'))->assertOk()->assertDontSee('/tinh-chi-so-trang-bi-nang-cap');
    $this->get(route('tools.equipment-upgrade'))->assertOk()->assertSee('name="current_stat"', false)
        ->assertDontSee('data-service-maintenance', false);
    $this->post(route('tools.equipment-upgrade.calculate'), ['current_stat' => 100, 'current_level' => 0])
        ->assertOk()->assertViewHas('results', fn (array $results): bool => count($results) === 8)
        ->assertSee('Kết quả chỉ số trang bị');
});

test('equipment calculator page is public and linked from the tool catalog', function (): void {
    $this->get(route('tools.equipment-upgrade'))->assertOk()
        ->assertSee('Tính chỉ số trang bị nâng cấp NRO')
        ->assertSee('name="current_stat"', false)
        ->assertSee('name="current_level"', false)
        ->assertDontSee('caculator()')->assertDontSee('scriptRound');
    $this->get(route('home'))->assertOk()->assertSee('href="/tinh-chi-so-trang-bi-nang-cap"', false);
});

test('equipment calculator matches original script output at every current level', function (int $level, array $expected): void {
    $this->post(route('tools.equipment-upgrade.calculate'), ['current_stat' => '100.5', 'current_level' => $level])
        ->assertOk()->assertSee('Kết quả chỉ số trang bị')->assertSee('Hiện tại')
        ->assertViewHas('inputs', ['current_stat' => '100.5', 'current_level' => $level])
        ->assertViewHas('results', function (array $results) use ($level, $expected): bool {
            expect($results)->toHaveCount(8);
            foreach ($expected as $index => $value) {
                expect($results[$index]['value'])->toEqualWithDelta($value, 0.0000001)
                    ->and($results[$index]['is_current'])->toBe($index === $level);
            }

            return true;
        });
})->with([
    [0, [100.5, 111.1, 122.1, 134.2, 147.4, 161.7, 178.2, 195.8]],
    [1, [92, 100.5, 111.1, 122.1, 134.2, 147.4, 161.7, 178.2]],
    [2, [84, 92, 100.5, 111.1, 122.1, 134.2, 147.4, 161.7]],
    [3, [76, 84, 92, 100.5, 111.1, 122.1, 134.2, 147.4]],
    [4, [69, 76, 84, 92, 100.5, 111.1, 122.1, 134.2]],
    [5, [63, 69, 76, 84, 92, 100.5, 111.1, 122.1]],
    [6, [57, 63, 69, 76, 84, 92, 100.5, 111.1]],
    [7, [52, 57, 63, 69, 76, 84, 92, 100.5]],
]);

test('script rounding preserves negative halfway behavior during reverse calculation', function (): void {
    $results = app(EquipmentUpgradeCalculatorService::class)->calculate(5, 1);
    expect($results[0]['value'])->toBe(5.0)
        ->and($results[2]['value'])->toBe(5.5)
        ->and($results[2]['rounded'])->toBe(6);
});

test('equipment input bounds give finite results', function (float $stat): void {
    $results = app(EquipmentUpgradeCalculatorService::class)->calculate($stat, 0);
    foreach ($results as $result) {
        expect(is_finite($result['value']))->toBeTrue();
    }
})->with([0.0, 1000000000.0]);

test('equipment calculator rejects invalid input', function (array $payload, array $errors): void {
    $this->postJson(route('tools.equipment-upgrade.calculate'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($errors);
})->with([
    [[], ['current_stat', 'current_level']],
    [['current_stat' => -1, 'current_level' => -1], ['current_stat', 'current_level']],
    [['current_stat' => 1000000001, 'current_level' => 8], ['current_stat', 'current_level']],
    [['current_stat' => 'Infinity', 'current_level' => 1.5], ['current_stat', 'current_level']],
    [['current_stat' => [100], 'current_level' => [1]], ['current_stat', 'current_level']],
]);

test('invalid form submissions return to the form and preserve input', function (): void {
    $this->from(route('tools.equipment-upgrade'))
        ->post(route('tools.equipment-upgrade.calculate'), ['current_stat' => -1, 'current_level' => 3])
        ->assertRedirect(route('tools.equipment-upgrade'))
        ->assertSessionHasErrors('current_stat')->assertSessionHasInput('current_level', 3);
    $this->get(route('tools.equipment-upgrade'))->assertOk()->assertSee('Chỉ số hiện tại không được âm.');
});

test('maintenance hides the equipment form and prevents results', function (): void {
    app(ToolAvailabilityService::class)->update('admin_equipment_tool', [
        'is_enabled' => false, 'maintenance_message' => 'Đang cập nhật chỉ số.',
    ]);
    $this->get(route('tools.equipment-upgrade'))->assertOk()->assertSee('Đang cập nhật chỉ số.')
        ->assertDontSee('name="current_stat"', false);
    $this->post(route('tools.equipment-upgrade.calculate'), ['current_stat' => 100, 'current_level' => 0])
        ->assertOk()->assertViewHas('results', null)->assertSee('Đang cập nhật chỉ số.');
});

test('equipment calculation requests are rate limited', function (): void {
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $this->post(route('tools.equipment-upgrade.calculate'), ['current_stat' => 100, 'current_level' => 0])->assertOk();
    }
    $this->post(route('tools.equipment-upgrade.calculate'), ['current_stat' => 100, 'current_level' => 0])->assertStatus(429);
});
