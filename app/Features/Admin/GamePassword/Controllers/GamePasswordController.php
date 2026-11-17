<?php

namespace App\Features\Admin\GamePassword\Controllers;

use App\Features\Admin\GamePassword\Requests\ClearGamePasswordHistoryRequest;
use App\Features\Admin\GamePassword\Requests\ResolveGamePasswordOrderRequest;
use App\Features\Admin\GamePassword\Requests\UpdateGamePasswordConfigurationRequest;
use App\Features\Client\GamePassword\Requests\GamePasswordHistoryRequest;
use App\Features\Client\GamePassword\Requests\RetryGamePasswordOrderRequest;
use App\Features\Client\GamePassword\Resources\GamePasswordOrderResource;
use App\Features\Client\GamePassword\Services\GamePasswordConfigurationService;
use App\Features\Client\GamePassword\Services\GamePasswordFeedService;
use App\Features\Client\GamePassword\Services\GamePasswordHistoryCleanupService;
use App\Features\Client\GamePassword\Services\GamePasswordOrderService;
use App\Http\Controllers\Controller;
use App\Models\GamePasswordOrder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GamePasswordController extends Controller
{
    public function configuration(GamePasswordHistoryRequest $request, GamePasswordConfigurationService $configuration): JsonResponse
    {
        return response()->json(['data' => $configuration->configuration()]);
    }

    public function configure(UpdateGamePasswordConfigurationRequest $request, GamePasswordConfigurationService $configuration): JsonResponse
    {
        return response()->json(['data' => $configuration->configure($request->validated())]);
    }

    public function history(GamePasswordHistoryRequest $request, GamePasswordOrderService $orders): JsonResponse
    {
        return GamePasswordOrderResource::collection($orders->history($request->validated()))->response();
    }

    public function resolve(ResolveGamePasswordOrderRequest $request, GamePasswordOrder $order, GamePasswordOrderService $orders): JsonResponse
    {
        return response()->json(['data' => new GamePasswordOrderResource($orders->finish($order->id, $request->validated('status'), $request->validated('status') === 'error' ? 'ADMIN_REFUND' : null, true))]);
    }

    public function stream(GamePasswordHistoryRequest $request, GamePasswordFeedService $feed): StreamedResponse
    {
        return $feed->response($request, true);
    }

    public function clear(ClearGamePasswordHistoryRequest $request, GamePasswordHistoryCleanupService $cleanup): JsonResponse
    {
        return response()->json(['data' => $request->has('ids')
            ? $cleanup->clearSelected($request->validated('ids'))
            : $cleanup->clear($request->integer('older_than_days'))]);
    }

    public function retry(RetryGamePasswordOrderRequest $request, GamePasswordOrder $order, GamePasswordOrderService $orders): JsonResponse
    {
        return response()->json(['data' => new GamePasswordOrderResource($orders->retry($request->user(), $order->id, $request->integer('attempt_version')))]);
    }
}
