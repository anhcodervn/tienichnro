<?php

namespace App\Features\Tenant\Controllers;

use App\Features\Tenant\Requests\IndexTenantRequest;
use App\Features\Tenant\Requests\StoreTenantRequest;
use App\Features\Tenant\Requests\UpdateTenantPackagePriceRequest;
use App\Features\Tenant\Requests\UpdateTenantRequest;
use App\Features\Tenant\Services\TenantAdminService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TopupPackage;
use App\Utils\Site;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function __construct(private readonly TenantAdminService $service) {}

    public function index(IndexTenantRequest $request): JsonResponse
    {
        $sites = $this->service->paginate($request->validated());

        return response()->json(['status' => true, 'data' => [
            'sites' => $sites->items(),
            'meta' => [
                'current_page' => $sites->currentPage(),
                'last_page' => $sites->lastPage(),
                'per_page' => $sites->perPage(),
                'total' => $sites->total(),
                'from' => $sites->firstItem(),
                'to' => $sites->lastItem(),
            ],
        ]]);
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->service->create($request->validated());

        return response()->json(['status' => true, 'message' => 'Đã tạo website đại lý.', 'data' => ['site' => $this->service->serialize($tenant)]], 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json(['status' => true, 'data' => ['site' => $this->service->serialize($tenant->load(['domains', 'billingUser']))]]);
    }

    public function update(Tenant $tenant, UpdateTenantRequest $request): JsonResponse
    {
        $tenant = $this->service->update($tenant, $request->validated());

        return response()->json(['status' => true, 'message' => 'Đã cập nhật website.', 'data' => ['site' => $this->service->serialize($tenant)]]);
    }

    public function current(): JsonResponse
    {
        $tenant = Site::mySite();
        abort_unless($tenant instanceof Tenant, 404);

        return response()->json(['status' => true, 'data' => ['site' => $this->service->serialize($tenant->load(['domains', 'billingUser']))]]);
    }

    public function prices(): JsonResponse
    {
        $tenant = Site::mySite();
        abort_unless($tenant instanceof Tenant, 404);

        return response()->json(['status' => true, 'data' => ['prices' => $this->service->prices($tenant)]]);
    }

    public function updatePrice(TopupPackage $topupPackage, UpdateTenantPackagePriceRequest $request): JsonResponse
    {
        $tenant = Site::mySite();
        abort_unless($tenant instanceof Tenant && ! $tenant->is_main, 422, 'Bảng giá website chính được quản lý trong danh mục gói nạp.');
        $this->service->updatePrice($tenant, $topupPackage, $request->validated());

        return response()->json(['status' => true, 'message' => 'Đã cập nhật giá bán.', 'data' => ['prices' => $this->service->prices($tenant)]]);
    }
}
