<?php

namespace App\Features\Admin\License\Controllers;

use App\Features\Admin\License\Requests\ManageLicenseRequest;
use App\Features\Admin\License\Requests\SaveLicenseCatalogRequest;
use App\Features\Admin\License\Resources\LicenseResource;
use App\Features\Admin\License\Services\LicenseAdminService;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicensePlan;
use App\Models\LicenseProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LicenseController extends Controller
{
    public function __construct(private readonly LicenseAdminService $licenses) {}

    public function products(): JsonResponse
    {
        return response()->json(['data' => LicenseProduct::query()->latest('id')->get()]);
    }

    public function storeProduct(SaveLicenseCatalogRequest $request): JsonResponse
    {
        return response()->json(['data' => LicenseProduct::query()->create($request->validated())], 201);
    }

    public function updateProduct(SaveLicenseCatalogRequest $request, LicenseProduct $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json(['data' => $product->refresh()]);
    }

    public function plans(): JsonResponse
    {
        return response()->json(['data' => LicensePlan::query()->with('product')->latest('id')->get()]);
    }

    public function storePlan(SaveLicenseCatalogRequest $request): JsonResponse
    {
        return response()->json(['data' => LicensePlan::query()->create($request->validated())], 201);
    }

    public function updatePlan(SaveLicenseCatalogRequest $request, LicensePlan $plan): JsonResponse
    {
        if ($plan->product_id !== (int) $request->validated('product_id') && License::query()->where('plan_id', $plan->id)->exists()) {
            throw ValidationException::withMessages(['product_id' => 'Không chuyển sản phẩm của gói đã cấp key.']);
        }
        $plan->update($request->validated());

        return response()->json(['data' => $plan->refresh()]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', License::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:32'], 'status' => ['nullable', 'in:unused,active,suspended,revoked,expired'], 'product_id' => ['nullable', 'integer']]);
        $query = License::query()->with(['product', 'plan', 'sessions' => fn ($query) => $query->where('status', 'active')]);
        if (! empty($filters['search'])) {
            $query->where('key_prefix', 'like', str_replace(['%', '_'], '', $filters['search']).'%');
        }
        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        if (($filters['status'] ?? '') === 'expired') {
            $query->where(fn ($query) => $query->where('status', 'expired')->orWhere(fn ($query) => $query->whereIn('status', ['unused', 'active'])->where('expires_at', '<=', now())));
        } elseif (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
            if (in_array($filters['status'], ['unused', 'active'], true)) {
                $query->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
            }
        }

        return LicenseResource::collection($query->latest('id')->paginate(20));
    }

    public function show(License $license): LicenseResource
    {
        Gate::authorize('update', $license);

        return new LicenseResource($license->load(['product', 'plan', 'devices', 'sessions' => fn ($query) => $query->latest()->limit(50), 'events' => fn ($query) => $query->latest('id')->limit(100)]));
    }

    public function store(ManageLicenseRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->licenses->issue($request->validated(), $request)], 201)->header('Cache-Control', 'no-store, private');
    }

    public function update(ManageLicenseRequest $request, License $license): LicenseResource
    {
        Gate::authorize('update', $license);

        return new LicenseResource($this->licenses->manage($license, $request->validated(), $request)->load(['product', 'plan', 'sessions']));
    }
}
