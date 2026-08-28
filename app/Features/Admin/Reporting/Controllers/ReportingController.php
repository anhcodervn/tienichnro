<?php

namespace App\Features\Admin\Reporting\Controllers;

use App\Features\Admin\Reporting\Requests\AdminReportingRequest;
use App\Features\Reporting\Services\TopupReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ReportingController extends Controller
{
    public function __construct(private readonly TopupReportService $reportService) {}

    public function topup(AdminReportingRequest $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->reportService->report(
                $request->filled('from') ? $request->string('from')->toString() : null,
                $request->filled('to') ? $request->string('to')->toString() : null,
            ),
        ]);
    }
}
