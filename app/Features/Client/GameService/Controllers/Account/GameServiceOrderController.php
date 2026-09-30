<?php

namespace App\Features\Client\GameService\Controllers\Account;

use App\Features\Client\GameService\Requests\CancelGameServiceOrderRequest;
use App\Features\Client\GameService\Services\GameServiceOrderService;
use App\Http\Controllers\Controller;
use App\Models\GameServiceOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameServiceOrderController extends Controller
{
    public function __invoke(Request $request): View
    {
        $orders = GameServiceOrder::query()
            ->where('user_id', $request->user()->id)
            ->select([
                'id', 'code', 'game_name', 'service_name', 'package_name', 'server_name',
                'quantity', 'total_amount', 'status', 'created_at',
            ])
            ->latest('id')
            ->paginate(15);

        return view('client.account.game-service-orders.index', ['orders' => $orders]);
    }

    public function destroy(
        CancelGameServiceOrderRequest $request,
        GameServiceOrder $gameServiceOrder,
        GameServiceOrderService $orderService,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $result = $orderService->cancelPending($gameServiceOrder, $user);
        $message = "Đã hủy đơn {$result['order']->code}.";

        if ($result['refunded_amount'] > 0) {
            $amount = number_format($result['refunded_amount'], 0, ',', '.');
            $message = "Đã hủy đơn {$result['order']->code} và hoàn {$amount}đ vào số dư NapCarot.";
        }

        return to_route('account.game-service-orders.index')->with('success', $message);
    }
}
