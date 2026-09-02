<?php

namespace App\Features\Admin\Topup\Controllers;

use App\Features\Admin\Topup\Requests\UpdateGlobalTopupRewardRequest;
use App\Features\Admin\Topup\Services\GlobalTopupRewardAdminService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalTopupRewardController extends Controller
{
    public function __construct(private readonly GlobalTopupRewardAdminService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(ApiResponse::success(data: $this->service->catalog()));
    }

    public function update(UpdateGlobalTopupRewardRequest $request, Game $game): JsonResponse
    {
        $this->service->updateGame($game, $request->validated('packages'), $this->admin($request), $request);

        return response()->json(ApiResponse::success('Đã lưu bảng thực nhận Global.'));
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
