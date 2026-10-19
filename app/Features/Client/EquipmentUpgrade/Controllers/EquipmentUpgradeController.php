<?php

namespace App\Features\Client\EquipmentUpgrade\Controllers;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\EquipmentUpgrade\Requests\CalculateEquipmentUpgradeRequest;
use App\Features\Client\EquipmentUpgrade\Services\EquipmentUpgradeCalculatorService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class EquipmentUpgradeController extends Controller
{
    public function __construct(private readonly ToolAvailabilityService $tools) {}

    public function index(): View
    {
        return view('pages.tools.equipment-upgrade', ['service' => $this->tool(), 'results' => null, 'inputs' => []]);
    }

    public function calculate(CalculateEquipmentUpgradeRequest $request, EquipmentUpgradeCalculatorService $calculator): View
    {
        $service = $this->tool();
        $inputs = $request->validated();

        return view('pages.tools.equipment-upgrade', [
            'service' => $service,
            'inputs' => $inputs,
            'results' => $service['is_enabled'] ? $calculator->calculate((float) $inputs['current_stat'], (int) $inputs['current_level']) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function tool(): array
    {
        return $this->tools->all()->first(function (array $tool): bool {
            $path = parse_url((string) ($tool['url'] ?? ''), PHP_URL_PATH);

            return is_string($path) && rtrim($path, '/') === '/tinh-chi-so-trang-bi-nang-cap';
        }) ?? ['is_enabled' => true, 'maintenance_message' => ''];
    }
}
