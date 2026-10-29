<?php

use App\Features\Admin\Setting\Services\ToolAvailabilityService;

function missingPotentialInputs(): array
{
    return [
        'planet' => 'saiyan',
        'current' => ['hp' => 400000, 'ki' => 264020, 'attack' => 18283, 'armor' => 2945, 'critical' => 4],
        'target' => ['hp' => 575000, 'ki' => 575000, 'attack' => 26000, 'armor' => 2945, 'critical' => 4],
        'current_power' => 40001172643,
    ];
}

test('missing potential page works before catalog registration and is not listed', function (): void {
    $this->get(route('tools.missing-potential'))->assertOk()->assertSee('Tính tiềm năng còn thiếu')
        ->assertSee('name="current[hp]"', false)->assertSee('name="target[critical]"', false);
    $this->get(route('home'))->assertOk()->assertDontSee('/tinh-tiem-nang-con-thieu');
});

test('missing potential reproduces the supplied example and retains inputs', function (): void {
    $inputs = missingPotentialInputs();
    $this->post(route('tools.missing-potential.calculate'), $inputs)->assertOk()
        ->assertViewHas('inputs', $inputs)
        ->assertViewHas('result', [
            'breakdown' => ['hp' => 4274287500.0, 'ki' => 6538354500.0, 'attack' => 17086209700.0, 'armor' => 0.0, 'critical' => 0.0],
            'total' => 27898851700.0, 'remaining' => 27898851700.0, 'target_power' => 67900024343.0,
        ])->assertSee('27.898.851.700')->assertSee('67.900.024.343');
});

test('available potential reduces only the amount still to earn', function (int $available, float $remaining): void {
    $this->post(route('tools.missing-potential.calculate'), [...missingPotentialInputs(), 'available_potential' => $available])
        ->assertOk()->assertViewHas('result', fn (array $result): bool => $result['remaining'] === $remaining
            && $result['total'] === 27898851700.0 && $result['target_power'] === 67900024343.0);
})->with([[1000000000, 26898851700.0], [30000000000, 0.0]]);

test('lower stats do not offset other upgrades and optional power can be omitted', function (): void {
    $inputs = missingPotentialInputs();
    $inputs['target'] = ['hp' => 575000, 'ki' => 100, 'attack' => 15, 'armor' => 0, 'critical' => 0];
    unset($inputs['current_power']);
    $this->post(route('tools.missing-potential.calculate'), $inputs)->assertOk()
        ->assertViewHas('result', fn (array $result): bool => $result['total'] === 4274287500.0 && $result['target_power'] === null);
});

test('unchanged stats need zero potential for every planet', function (string $planet): void {
    $inputs = missingPotentialInputs();
    $inputs['planet'] = $planet;
    $inputs['target'] = $inputs['current'];
    $this->post(route('tools.missing-potential.calculate'), $inputs)->assertOk()
        ->assertViewHas('result', fn (array $result): bool => $result['total'] === 0.0);
})->with(['earth', 'namec', 'saiyan']);

test('missing potential validates malformed and out of range inputs', function (string $field, mixed $value): void {
    $inputs = missingPotentialInputs();
    data_set($inputs, $field, $value);
    $this->postJson(route('tools.missing-potential.calculate'), $inputs)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['planet', ['earth']], ['planet', 'invalid'], ['current', 'invalid'], ['target.hp', -1],
    ['target.hp', 1000000001], ['current.ki', 'Infinity'], ['target.attack', 12], ['target.attack', 15.5],
    ['target.armor', -1], ['target.critical', 101], ['available_potential', -1], ['current_power', []],
]);

test('invalid missing potential form preserves input and displays validation errors', function (): void {
    $inputs = missingPotentialInputs();
    $inputs['target']['hp'] = -1;
    $this->from(route('tools.missing-potential'))->post(route('tools.missing-potential.calculate'), $inputs)
        ->assertRedirect(route('tools.missing-potential'))->assertSessionHasErrors('target.hp')
        ->assertSessionHasInput('current.hp', 400000);
    $this->get(route('tools.missing-potential'))->assertOk()->assertSee('HP mong muốn phải từ 100 trở lên.');
});

test('missing potential respects maintenance after catalog registration', function (): void {
    app(ToolAvailabilityService::class)->create([
        'code' => 'test_missing_potential', 'name' => 'Tính tiềm năng còn thiếu', 'description' => '',
        'url' => route('tools.missing-potential').'/', 'is_enabled' => false, 'maintenance_message' => 'Đang cập nhật công thức.',
    ]);
    $this->get(route('tools.missing-potential'))->assertOk()->assertSee('Đang cập nhật công thức.')
        ->assertDontSee('name="current[hp]"', false);
    $this->post(route('tools.missing-potential.calculate'), missingPotentialInputs())->assertOk()->assertViewHas('result', null);
});
