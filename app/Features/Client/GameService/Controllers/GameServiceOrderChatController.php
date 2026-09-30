<?php

namespace App\Features\Client\GameService\Controllers;

use App\Features\Admin\GameService\Requests\SendGameServiceOrderMessageRequest;
use App\Features\Admin\GameService\Services\GameServiceOrderChatService;
use App\Features\Admin\GameService\Services\GameServiceOrderProgressService;
use App\Http\Controllers\Controller;
use App\Models\GameServiceOrder;
use App\Models\GameServiceOrderProgress;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GameServiceOrderChatController extends Controller
{
    public function __construct(
        private readonly GameServiceOrderChatService $chatService,
        private readonly GameServiceOrderProgressService $progressService,
    ) {}

    public function page(GameServiceOrder $gameServiceOrder, Request $request): View
    {
        $this->chatService->thread($gameServiceOrder, $this->user($request));
        $gameServiceOrder->loadMissing('game:id,image');

        return view('client.account.game-service-orders.chat', [
            'order' => $gameServiceOrder,
            'gameIcon' => $gameServiceOrder->game?->image,
        ]);
    }

    public function show(GameServiceOrder $gameServiceOrder, Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->chatService->thread($gameServiceOrder, $this->user($request))]);
    }

    public function progress(GameServiceOrder $gameServiceOrder, Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => ['progress' => $this->progressService->timeline($this->user($request), $gameServiceOrder)],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function image(
        GameServiceOrder $gameServiceOrder,
        GameServiceOrderProgress $gameServiceOrderProgress,
        Request $request,
    ): StreamedResponse {
        return $this->progressService->image(
            $this->user($request),
            $gameServiceOrder,
            $gameServiceOrderProgress,
        );
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
