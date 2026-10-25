<?php

namespace App\Features\Client\Profile\Controllers;

use App\Features\Client\Profile\Requests\AccountLicenseRequest;
use App\Features\Client\Profile\Resources\AccountLicenseResource;
use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountLicenseController extends Controller
{
    public function index(AccountLicenseRequest $request): AnonymousResourceCollection
    {
        $licenses = License::query()->where('user_id', $request->user()->id)->with(['product', 'plan']);
        if ($request->filled('product_code')) {
            $licenses->whereHas('product', fn ($query) => $query->where('product_code', $request->validated('product_code')));
        }

        return AccountLicenseResource::collection($licenses->latest('id')->paginate(20));
    }

    public function show(AccountLicenseRequest $request, int $license): AccountLicenseResource
    {
        return new AccountLicenseResource(License::query()->where('user_id', $request->user()->id)
            ->with(['product', 'plan'])->findOrFail($license));
    }
}
