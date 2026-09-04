<?php

namespace App\Features\Admin\Affiliate\Controllers;

use App\Features\Admin\Affiliate\Requests\AffiliateIndexRequest;
use App\Features\Admin\Affiliate\Services\AdminAffiliateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AffiliateController extends Controller
{
    public function __construct(private readonly AdminAffiliateService $service) {}

    public function index(AffiliateIndexRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->overview($request->validated())]);
    }
}
