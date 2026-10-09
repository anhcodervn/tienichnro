<?php

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\Potential\Services\PotentialCalculatorService;

beforeEach(function (): void {
    app(ToolAvailabilityService::class)->create([
        'code' => 'admin_potential_tool', 'name' => 'Tính tiềm năng', 'description' => '',
        'url' => '/tinh-tiem-nang', 'is_enabled' => true, 'maintenance_message' => 'Đang cập nhật công thức.',
    ]);
});

test('potential tool is available to guests with all six inputs and a catalog link', function (): void {
    $page = $this->get(route('tools.potential'))->assertOk()->assertSee('data-potential-form', false);
    foreach (['planet', 'hp', 'ki', 'attack', 'armor', 'critical'] as $field) {
        $page->assertSee('name="'.$field.'"', false);
    }
    $this->get(route('home'))->assertOk()->assertSee('href="/tinh-tiem-nang"', false);
});

test('potential tool uses the admin SQL definition with an absolute URL', function (): void {
    app(ToolAvailabilityService::class)->update('admin_potential_tool', [
        'is_enabled' => true, 'maintenance_message' => '', 'url' => route('tools.potential').'/',
    ]);
    $this->get(route('tools.potential'))->assertOk()->assertSee('data-potential-form', false);
    expect(collect(config('tools.items'))->contains('code', 'potential_calculator'))->toBeFalse();
});

test('removing the SQL tool disables the calculation page', function (): void {
    app(ToolAvailabilityService::class)->delete('admin_potential_tool');
    $this->get(route('tools.potential'))->assertOk()->assertDontSee('data-potential-form', false);
});

test('php calculator reproduces the supplied javascript formulas for each planet', function (string $planet, array $expected, int $total): void {
    $this->postJson(route('tools.potential.calculate'), [
        'planet' => $planet, 'hp' => 400, 'ki' => 400, 'attack' => 30, 'armor' => 2, 'critical' => 2,
    ])->assertOk()->assertJsonPath('status', true)->assertJsonPath('data.total', $total)->assertJsonPath('data.breakdown', $expected);
})->with([
    ['earth', ['hp' => 12900, 'ki' => 18600, 'attack' => 36900, 'armor' => 1100000, 'critical' => 300000000], 301168400],
    ['namec', ['hp' => 18600, 'ki' => 12900, 'attack' => 36900, 'armor' => 1100000, 'critical' => 300000000], 301168400],
    ['saiyan', ['hp' => 18600, 'ki' => 18600, 'attack' => 33000, 'armor' => 1100000, 'critical' => 300000000], 301170200],
]);

test('base stats have no spent potential', function (string $planet): void {
    $base = app(PotentialCalculatorService::class)->planets()[$planet];
    $this->postJson(route('tools.potential.calculate'), [
        'planet' => $planet, 'hp' => $base['hp'], 'ki' => $base['ki'], 'attack' => $base['attack'], 'armor' => 0, 'critical' => 0,
    ])->assertOk()->assertJsonPath('data.total', 0)->assertJsonPath('data.level', null);
})->with(['earth', 'namec', 'saiyan']);

test('invalid stats are rejected without a calculation', function (array $changes, array $fields): void {
    $payload = array_replace(['planet' => 'earth', 'hp' => 400, 'ki' => 400, 'attack' => 30, 'armor' => 0, 'critical' => 0], $changes);
    $this->postJson(route('tools.potential.calculate'), $payload)->assertUnprocessable()->assertJsonValidationErrors($fields)->assertJsonMissingPath('data.total');
})->with([
    [['planet' => 'invalid'], ['planet']],
    [['planet' => ['earth']], ['planet']],
    [['hp' => 199, 'ki' => 99, 'attack' => 11, 'armor' => -1, 'critical' => 101], ['hp', 'ki', 'attack', 'armor', 'critical']],
    [['planet' => 'namec', 'ki' => 100], ['ki']],
    [['planet' => 'saiyan', 'attack' => 12], ['attack']],
    [['hp' => 'Infinity', 'ki' => 'abc', 'attack' => 12.5, 'critical' => null], ['hp', 'ki', 'attack', 'critical']],
    [['hp' => 1000000001, 'attack' => 1000001, 'armor' => 1000001], ['hp', 'attack', 'armor']],
]);

test('upper input bounds produce a finite result', function (): void {
    $response = $this->postJson(route('tools.potential.calculate'), ['planet' => 'earth', 'hp' => 1000000000, 'ki' => 1000000000, 'attack' => 1000000, 'armor' => 1000000, 'critical' => 100])->assertOk();
    expect(is_finite((float) $response->json('data.total')))->toBeTrue();
});

test('rank boundaries include the exact one hundred billion mark', function (int $threshold, ?string $before, string $after): void {
    $calculator = app(PotentialCalculatorService::class);
    expect($calculator->level($threshold - 1, 'earth'))->toBe($before)
        ->and($calculator->level($threshold, 'earth'))->toBe($after);
})->with([
    [1200, null, 'Tân Binh'], [340000, 'Tân Binh', 'Vệ Binh'], [1500000, 'Vệ Binh', 'Siêu Nhân Cấp 1'],
    [15000000, 'Siêu Nhân Cấp 1', 'Siêu Nhân Cấp 2'], [150000000, 'Siêu Nhân Cấp 2', 'Siêu Nhân Cấp 3'],
    [1500000000, 'Siêu Nhân Cấp 3', 'Siêu Nhân Cấp 4'], [16700000000, 'Siêu Nhân Cấp 4', 'Thần Trái Đất Cấp 1'],
    [40000000000, 'Thần Trái Đất Cấp 1', 'Thần Trái Đất Cấp 2'], [50000000000, 'Thần Trái Đất Cấp 2', 'Thần Trái Đất Cấp 3'],
    [60000000000, 'Thần Trái Đất Cấp 3', 'Giới Vương Thần Cấp 1'], [70000000000, 'Giới Vương Thần Cấp 1', 'Giới Vương Thần Cấp 2'],
    [80000000000, 'Giới Vương Thần Cấp 2', 'Giới Vương Thần Cấp 3'], [100000000000, 'Giới Vương Thần Cấp 3', 'Thần Hủy Diệt'],
]);

test('maintenance hides the form and blocks calculation', function (): void {
    app(ToolAvailabilityService::class)->update('admin_potential_tool', ['is_enabled' => false, 'maintenance_message' => 'Đang cập nhật công thức.']);
    $this->get(route('tools.potential'))->assertOk()->assertSee('Đang cập nhật công thức.')->assertDontSee('data-potential-form', false);
    $this->postJson(route('tools.potential.calculate'), ['planet' => 'earth', 'hp' => 200, 'ki' => 100, 'attack' => 12, 'armor' => 0, 'critical' => 0])
        ->assertStatus(503)->assertJsonPath('service_maintenance', true);
});
