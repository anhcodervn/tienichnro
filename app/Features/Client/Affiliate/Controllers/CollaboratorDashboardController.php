<?php

namespace App\Features\Client\Affiliate\Controllers;

use App\Features\Client\Affiliate\Services\CollaboratorDashboardService;
use App\Http\Controllers\Controller;
use App\Models\AffiliateAnnouncement;
use App\Models\GameServiceOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollaboratorDashboardController extends Controller
{
    public function __construct(private readonly CollaboratorDashboardService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->overview($this->user($request))]);
    }

    public function start(GameServiceOrder $gameServiceOrder, Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'message' => 'Đã nhận xử lý đơn.', 'data' => $this->service->startOrder($this->user($request), $gameServiceOrder)]);
    }

    public function submit(GameServiceOrder $gameServiceOrder, Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'message' => 'Đã gửi đơn cho admin duyệt.', 'data' => $this->service->submitOrder($this->user($request), $gameServiceOrder)]);
    }

    public function readAnnouncement(AffiliateAnnouncement $affiliateAnnouncement, Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => ['unread_count' => $this->service->readAnnouncement($this->user($request), $affiliateAnnouncement)]]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
