<?php

namespace App\Features\Client\Potential\Controllers;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\Potential\Requests\CalculatePotentialRequest;
use App\Features\Client\Potential\Services\PotentialCalculatorService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class PotentialController extends Controller
{
    public function __construct(private readonly ToolAvailabilityService $tools) {}

    public function index(PotentialCalculatorService $calculator): View
    {
        return view('pages.tools.potential', ['planets' => $calculator->planets(), 'service' => $this->tool()]);
    }

    public function calculate(CalculatePotentialRequest $request, PotentialCalculatorService $calculator): JsonResponse
    {
        $tool = $this->tool();
        if (! $tool['is_enabled']) {
            return response()->json(['message' => $tool['maintenance_message'], 'service_maintenance' => true], 503);
        }

        return response()->json(['status' => true, 'data' => $calculator->calculate($request->validated())]);
    }

    /** @return array<string, mixed> */
    private function tool(): array
    {
        return $this->tools->all()->first(function (array $tool): bool {
            $path = parse_url((string) ($tool['url'] ?? ''), PHP_URL_PATH);

            return is_string($path) && rtrim($path, '/') === '/tinh-tiem-nang';
        }) ?? ['is_enabled' => false, 'maintenance_message' => 'Công cụ chưa được bật trong danh sách dịch vụ.'];
    }
}
