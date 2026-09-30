<?php

namespace App\Features\Admin\GameService\Controllers;

use App\Features\Admin\GameService\Requests\ApproveGameServiceOrderCompletionRequest;
use App\Features\Admin\GameService\Requests\ListGameServiceOrderRequest;
use App\Features\Admin\GameService\Requests\RefundGameServiceOrderRequest;
use App\Features\Admin\GameService\Requests\UpdateGameServiceOrderRequest;
use App\Features\Admin\GameService\Resources\GameServiceOrderResource;
use App\Features\Admin\GameService\Services\GameServiceAdminService;
use App\Features\Admin\GameService\Services\GameServiceOrderProgressService;
use App\Http\Controllers\Controller;
use App\Models\GameServiceOrder;
use App\Models\User;
use App\Support\GameServicePayloadCipher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameServiceOrderController extends Controller
{
    public function __construct(
        private readonly GameServiceAdminService $service,
        private readonly GameServicePayloadCipher $payloadCipher,
        private readonly GameServiceOrderProgressService $progressService,
    ) {}

    public function index(ListGameServiceOrderRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $orders = GameServiceOrderResource::collection($this->service->orders($filters))->response()->getData(true);
        $orders['summary'] = $this->service->orderSettlement($filters);

        return response()->json(['status' => true, 'data' => $orders]);
    }

    public function reviewCount(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => ['count' => $this->service->reviewOrderCount()],
        ]);
    }

    public function show(GameServiceOrder $gameServiceOrder): GameServiceOrderResource
    {
        return GameServiceOrderResource::make($gameServiceOrder->load('collaborator:id,username,full_name'));
    }

    public function payload(GameServiceOrder $gameServiceOrder): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => ['payload' => $this->payloadCipher->decrypt((string) $gameServiceOrder->getRawOriginal('payload'))],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function progress(GameServiceOrder $gameServiceOrder, Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => ['progress' => $this->progressService->timeline($this->admin($request), $gameServiceOrder)],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(UpdateGameServiceOrderRequest $request, GameServiceOrder $gameServiceOrder): GameServiceOrderResource
    {
        return GameServiceOrderResource::make($this->service->updateOrder($gameServiceOrder, $request->validated(), $this->admin($request), $request));
    }

    public function approveCompletion(
        ApproveGameServiceOrderCompletionRequest $request,
        GameServiceOrder $gameServiceOrder,
    ): GameServiceOrderResource {
        return GameServiceOrderResource::make(
            $this->service->approveOrderCompletion($gameServiceOrder, $request->validated(), $this->admin($request), $request),
        );
    }

    public function refund(
        RefundGameServiceOrderRequest $request,
        GameServiceOrder $gameServiceOrder,
    ): GameServiceOrderResource {
        return GameServiceOrderResource::make(
            $this->service->refundOrder($gameServiceOrder, $request->validated(), $this->admin($request), $request),
        );
    }

    private function admin(Request $request): User
    {
        /** @var User $admin */
        $admin = $request->user();

        return $admin;
    }
}
