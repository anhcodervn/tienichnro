<?php

namespace App\Features\Client\Affiliate\Controllers;

use App\Features\Admin\GameService\Requests\SendGameServiceOrderMessageRequest;
use App\Features\Admin\GameService\Services\GameServiceOrderChatService;
use App\Features\Client\Affiliate\Requests\ListAssignedGameServiceOrdersRequest;
use App\Features\Client\Affiliate\Services\CollaboratorDashboardService;
use App\Http\Controllers\Controller;
use App\Models\GameServiceOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameServiceOrderChatController extends Controller
{
    public function __construct(
        private readonly GameServiceOrderChatService $chatService,
        private readonly CollaboratorDashboardService $dashboardService,
    ) {}

    public function index(ListAssignedGameServiceOrdersRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->dashboardService->orders($this->user($request), $request->validated())]);
    }

    public function chats(Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->chatService->collaboratorOrders($this->user($request))]);
    }

    public function show(GameServiceOrder $gameServiceOrder, Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->chatService->thread($gameServiceOrder, $this->user($request))]);
    }

    public function payload(GameServiceOrder $gameServiceOrder, Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => ['payload' => $this->dashboardService->payload($this->user($request), $gameServiceOrder)],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(GameServiceOrder $gameServiceOrder, SendGameServiceOrderMessageRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->chatService->send(
            $gameServiceOrder,
            $this->user($request),
            $request->string('message')->toString(),
        )], 201);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
