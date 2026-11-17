<?php

namespace App\Features\Client\GamePassword\Controllers;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\GamePassword\Requests\GamePasswordHistoryRequest;
use App\Features\Client\GamePassword\Requests\RetryGamePasswordOrderRequest;
use App\Features\Client\GamePassword\Requests\SaveGamePasswordCaptchaRequest;
use App\Features\Client\GamePassword\Requests\StoreGamePasswordOrdersRequest;
use App\Features\Client\GamePassword\Resources\GamePasswordOrderResource;
use App\Features\Client\GamePassword\Services\GamePasswordCaptchaProfileService;
use App\Features\Client\GamePassword\Services\GamePasswordConfigurationService;
use App\Features\Client\GamePassword\Services\GamePasswordFeedService;
use App\Features\Client\GamePassword\Services\GamePasswordOrderService;
use App\Features\NroNotification\Services\NotificationReadCacheService;
use App\Http\Controllers\Controller;
use App\Models\GamePasswordOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GamePasswordController extends Controller
{
    public function index(GamePasswordHistoryRequest $request, GamePasswordOrderService $orders, GamePasswordConfigurationService $configuration, ToolAvailabilityService $tools, NotificationReadCacheService $readCache, GamePasswordCaptchaProfileService $profiles): View
    {
        return view('pages.tools.game-password', [...$this->historyData($request, $orders),
            'configuration' => $configuration->configuration(), 'service' => $tools->find('game_password'),
            'captchaProfile' => $profiles->snapshot($request->user()),
            'servers' => $readCache->catalog()['servers']]);
    }

    public function store(StoreGamePasswordOrdersRequest $request, GamePasswordOrderService $orders, GamePasswordCaptchaProfileService $profiles): RedirectResponse
    {
        $orders->create($request->user(), $request->validated('entries'), $request->validated('request_id'), $request->integer('price'), $request->filled('captcha_api_key') ? [
            'key' => $request->validated('captcha_api_key'), 'secret' => $request->validated('captcha_api_secret'),
        ] : $profiles->credentials($request->user()));

        return to_route('tools.game-password')->with('status', 'Queued');
    }

    public function history(GamePasswordHistoryRequest $request, GamePasswordOrderService $orders): JsonResponse
    {
        abort_unless($request->user()?->status === 'active', 403);

        return response()->json(['html' => view('pages.tools.partials.game-password-history', $this->historyData($request, $orders))->render()]);
    }

    public function retry(RetryGamePasswordOrderRequest $request, GamePasswordOrder $order, GamePasswordOrderService $orders, GamePasswordCaptchaProfileService $profiles): RedirectResponse|JsonResponse
    {
        $queued = $orders->retry($request->user(), $order->id, $request->integer('attempt_version'), $request->filled('captcha_api_key') ? [
            'key' => $request->validated('captcha_api_key'), 'secret' => $request->validated('captcha_api_secret'),
        ] : $profiles->credentials($request->user()));
        if ($request->expectsJson()) {
            return response()->json(['data' => ['id' => $queued->id, 'status' => $queued->status, 'attempt_version' => $queued->attempt_version]]);
        }

        return to_route('tools.game-password')->with('status', 'Queued');
    }

    public function stream(GamePasswordHistoryRequest $request, GamePasswordFeedService $feed): StreamedResponse
    {
        return $feed->response($request);
    }

    public function saveCaptcha(SaveGamePasswordCaptchaRequest $request, GamePasswordCaptchaProfileService $profiles): JsonResponse
    {
        return response()->json(['data' => $profiles->save($request->user(), [
            'key' => $request->validated('captcha_api_key'), 'secret' => $request->validated('captcha_api_secret'),
        ])]);
    }

    public function refreshCaptcha(SaveGamePasswordCaptchaRequest $request, GamePasswordCaptchaProfileService $profiles): JsonResponse
    {
        return response()->json(['data' => $profiles->refresh($request->user())]);
    }

    private function historyData(GamePasswordHistoryRequest $request, GamePasswordOrderService $orders): array
    {
        $filters = $request->validated();
        $history = $request->user() ? $orders->history($filters, $request->user()->id)->withPath(route('tools.game-password'))->appends(Arr::except($filters, ['page'])) : null;

        return ['orders' => $history, 'filters' => $filters, 'statuses' => GamePasswordOrderResource::STATUSES, 'progressLabels' => GamePasswordOrderResource::PROGRESS];
    }
}
