<?php

namespace App\Features\Client\GameImage\Controllers;

use App\Features\Client\GameImage\Requests\GameImageCaptureLogIndexRequest;
use App\Features\Client\GameImage\Requests\StoreGameImageCaptureLogsRequest;
use App\Features\Client\GameImage\Resources\GameImageCaptureLogResource;
use App\Features\Client\GameImage\Services\GameImageCaptureLogService;
use App\Http\Controllers\Controller;
use App\Models\GameImageAccount;
use App\Models\GameImageCaptureLog;
use Illuminate\Http\JsonResponse;

class GameImageCaptureLogController extends Controller
{
    public function store(StoreGameImageCaptureLogsRequest $request, GameImageAccount $account, GameImageCaptureLogService $logs): JsonResponse
    {
        $result = $logs->append($account, $request->integer('capture_version'), $request->validated('claim_token'), $request->validated('logs'));

        return response()->json(['data' => ['account_id' => $account->id, 'capture_version' => $request->integer('capture_version'), ...$result]], 200)
            ->header('Cache-Control', 'no-store, private');
    }

    public function index(GameImageCaptureLogIndexRequest $request, GameImageAccount $account): JsonResponse
    {
        $version = $request->integer('capture_version', $account->capture_version);
        $after = $request->integer('after_id', 0);
        $limit = $request->integer('per_page', 100);
        $logs = GameImageCaptureLog::query()->where('account_id', $account->id)->where('capture_version', $version)
            ->where('id', '>', $after)->orderBy('id')->limit($limit + 1)->get();
        $data = $logs->take($limit);

        return response()->json(['data' => GameImageCaptureLogResource::collection($data),
            'meta' => ['account_id' => $account->id, 'capture_version' => $version, 'next_after_id' => $data->last()?->id ?? $after, 'has_more' => $logs->count() > $limit]])->header('Cache-Control', 'no-store, private');
    }
}
