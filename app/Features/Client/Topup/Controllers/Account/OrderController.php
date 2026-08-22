<?php

namespace App\Features\Client\Topup\Controllers\Account;

use App\Features\Client\Topup\Requests\ListAccountOrderRequest;
use App\Features\Client\Topup\Services\AccountOrderHistoryService;
use App\Features\Topup\Support\OrderRealtimeChannel;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly AccountOrderHistoryService $accountOrderHistoryService,
    ) {}

    public function index(ListAccountOrderRequest $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();

        return view('client.account.orders.index', [
            'orders' => $this->accountOrderHistoryService->paginate($user, $filters),
            'games' => $this->accountOrderHistoryService->filterGames($user),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()?->id, 403);
        $order->load(['game:id,name', 'server:id,name', 'recipients']);

        return view('client.orders.show', [
            'order' => $order,
            'realtimeChannel' => OrderRealtimeChannel::for($order),
        ]);
    }
}
