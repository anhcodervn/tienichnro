<?php

namespace App\Features\Admin\GameVerification\Controllers;

use App\Features\Admin\GameVerification\Requests\ClearGameVerificationHistoryRequest;
use App\Features\Admin\GameVerification\Requests\UpdateGameVerificationConfigurationRequest;
use App\Features\Client\GameVerification\Requests\GameVerificationHistoryRequest;
use App\Features\Client\GameVerification\Requests\RetryGameVerificationOrderRequest;
use App\Features\Client\GameVerification\Resources\GameVerificationOrderResource;
use App\Features\Client\GameVerification\Services\GameVerificationConfigurationService;
use App\Features\Client\GameVerification\Services\GameVerificationFeedService;
use App\Features\Client\GameVerification\Services\GameVerificationHistoryCleanupService;
use App\Features\Client\GameVerification\Services\GameVerificationOrderService;
use App\Http\Controllers\Controller;
use App\Models\GameVerificationOrder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GameVerificationController extends Controller
{
    public function configuration(GameVerificationHistoryRequest $request, GameVerificationConfigurationService $configuration): JsonResponse
    {
        return response()->json(['data' => $configuration->configuration()]);
    }

    public function configure(UpdateGameVerificationConfigurationRequest $request, GameVerificationConfigurationService $configuration): JsonResponse
    {
        return response()->json(['data' => $configuration->configure($request->integer('price'))]);
    }

    public function history(GameVerificationHistoryRequest $request, GameVerificationOrderService $orders): JsonResponse
    {
        return GameVerificationOrderResource::collection($orders->history($request->validated()))->response();
    }

    public function stream(GameVerificationHistoryRequest $request, GameVerificationFeedService $feed): StreamedResponse
    {
        return $feed->response($request, true);
    }

    public function clear(ClearGameVerificationHistoryRequest $request, GameVerificationHistoryCleanupService $cleanup): JsonResponse
    {
        return response()->json(['data' => $request->has('ids')
            ? $cleanup->clearSelected($request->validated('ids'))
            : $cleanup->clear($request->integer('older_than_days'))]);
    }

    public function retry(RetryGameVerificationOrderRequest $request, GameVerificationOrder $order, GameVerificationOrderService $orders): JsonResponse
    {
        return response()->json(['data' => new GameVerificationOrderResource($orders->retry($request->user(), $order->id, $request->integer('attempt_version')))]);
    }
}
