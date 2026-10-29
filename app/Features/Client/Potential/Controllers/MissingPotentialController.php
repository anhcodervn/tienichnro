<?php

namespace App\Features\Client\Potential\Controllers;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\Potential\Requests\CalculateMissingPotentialRequest;
use App\Features\Client\Potential\Services\PotentialCalculatorService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class MissingPotentialController extends Controller
{
    public function __construct(private readonly ToolAvailabilityService $tools) {}

    public function index(PotentialCalculatorService $calculator): View
    {
        return view('pages.tools.missing-potential', ['planets' => $calculator->planets(), 'service' => $this->tool(), 'inputs' => [], 'result' => null]);
    }

    public function calculate(CalculateMissingPotentialRequest $request, PotentialCalculatorService $calculator): View
    {
        $service = $this->tool();
        $inputs = $request->validated();

        return view('pages.tools.missing-potential', [
            'planets' => $calculator->planets(), 'service' => $service, 'inputs' => $inputs,
            'result' => $service['is_enabled'] ? $calculator->calculateMissing($inputs) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function tool(): array
    {
        return $this->tools->all()->first(function (array $tool): bool {
            $path = parse_url((string) ($tool['url'] ?? ''), PHP_URL_PATH);

            return is_string($path) && rtrim($path, '/') === '/tinh-tiem-nang-con-thieu';
        }) ?? ['is_enabled' => true, 'maintenance_message' => ''];
    }
}
