<?php

namespace App\Features\Client\GameService\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\GameServiceOrder;
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
}
