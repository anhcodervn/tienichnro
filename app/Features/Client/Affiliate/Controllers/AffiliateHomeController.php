<?php

namespace App\Features\Client\Affiliate\Controllers;

use App\Features\Client\Affiliate\Services\AffiliateHomeService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateHomeController extends Controller
{
    public function __construct(private readonly AffiliateHomeService $service) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['status' => true, 'data' => $this->service->data($user)]);
    }
}
