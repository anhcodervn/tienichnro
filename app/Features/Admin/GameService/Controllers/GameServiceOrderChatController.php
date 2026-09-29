<?php

namespace App\Features\Admin\GameService\Controllers;

use App\Features\Admin\GameService\Requests\SendGameServiceOrderMessageRequest;
use App\Features\Admin\GameService\Services\GameServiceOrderChatService;
use App\Http\Controllers\Controller;
use App\Models\GameServiceOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameServiceOrderChatController extends Controller
{
    public function __construct(private readonly GameServiceOrderChatService $chatService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->chatService->adminThreads($request->only(['search', 'page', 'per_page']))]);
    }

    public function show(GameServiceOrder $gameServiceOrder, Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->chatService->thread($gameServiceOrder, $this->user($request))]);
    }

    public function store(GameServiceOrder $gameServiceOrder, SendGameServiceOrderMessageRequest $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->chatService->send(
            $gameServiceOrder,
            $this->user($request),
            $request->string('message')->toString(),
        )], 201);
    }

    public function collaborators(): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->chatService->collaborators()]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
