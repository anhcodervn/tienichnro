<?php

namespace App\Features\Admin\Setting\Controllers;

use App\Features\Admin\Setting\Requests\SaveServicePackageRequest;
use App\Features\Admin\Setting\Resources\ServicePackageResource;
use App\Http\Controllers\Controller;
use App\Models\ServicePackage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ServicePackageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ServicePackageResource::collection(ServicePackage::query()->orderBy('sort_order')->orderBy('id')->get());
    }

    public function store(SaveServicePackageRequest $request): ServicePackageResource
    {
        return new ServicePackageResource(ServicePackage::query()->create($request->packageValues()));
    }

    public function update(SaveServicePackageRequest $request, ServicePackage $package): ServicePackageResource
    {
        $package->update($request->packageValues());

        return new ServicePackageResource($package->refresh());
    }

    public function destroy(ServicePackage $package): Response
    {
        $package->delete();

        return response()->noContent();
    }
}
