<?php

namespace App\Features\Admin\Setting\Controllers;

use App\Features\Admin\Setting\Requests\SaveServiceOfferingRequest;
use App\Features\Admin\Setting\Services\ServiceCatalogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ServiceCatalogController extends Controller
{
    public function index(ServiceCatalogService $services): JsonResponse
    {
        return response()->json(['data' => $services->all()]);
    }

    public function store(SaveServiceOfferingRequest $request, ServiceCatalogService $services): JsonResponse
    {
        $values = $request->validated();
        $services->create($values);

        return response()->json(['data' => $services->find($values['code'])], 201);
    }

    public function update(SaveServiceOfferingRequest $request, string $code, ServiceCatalogService $services): JsonResponse
    {
        $services->update($code, $request->validated());

        return response()->json(['data' => $services->find($code)]);
    }

    public function destroy(string $code, ServiceCatalogService $services): Response
    {
        $services->delete($code);

        return response()->noContent();
    }
}
