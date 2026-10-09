<?php

namespace App\Features\Admin\Setting\Controllers;

use App\Features\Admin\Setting\Requests\StoreServiceRequest;
use App\Features\Admin\Setting\Requests\UpdateServiceRequest;
use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ServiceManagementController extends Controller
{
    public function index(ToolAvailabilityService $services): JsonResponse
    {
        return response()->json(['data' => $services->all()]);
    }

    public function update(UpdateServiceRequest $request, string $code, ToolAvailabilityService $services): JsonResponse
    {
        $services->update($code, $request->validated());

        return response()->json(['data' => $services->find($code), 'message' => 'Đã cập nhật trạng thái dịch vụ.']);
    }

    public function store(StoreServiceRequest $request, ToolAvailabilityService $services): JsonResponse
    {
        $values = $request->validated();
        $services->create($values);

        return response()->json(['data' => $services->find($values['code'])], 201);
    }

    public function destroy(string $code, ToolAvailabilityService $services): Response
    {
        $services->delete($code);

        return response()->noContent();
    }
}
