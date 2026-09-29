<?php

namespace App\Features\Client\GameService\Controllers;

use App\Features\Client\GameService\Requests\StoreGameServiceOrderRequest;
use App\Features\Client\GameService\Services\GameServiceOrderService;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class GameServiceOrderController extends Controller
{
    public function __construct(private readonly GameServiceOrderService $orderService) {}

    public function store(StoreGameServiceOrderRequest $request, Game $game, GameService $gameService): RedirectResponse
    {
        $user = $request->user();
        $order = $this->orderService->create($game, $gameService, $request->validated(), $user instanceof User ? $user : null);

        return to_route('game-services.service', ['game' => $game, 'gameService' => $gameService])
            ->with('success', "Đã tạo đơn {$order->code}. Đơn đang chờ cộng tác viên tiếp nhận.");
    }
}
