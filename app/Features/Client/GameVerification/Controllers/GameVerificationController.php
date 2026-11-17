<?php

namespace App\Features\Client\GameVerification\Controllers;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\GamePassword\Services\GamePasswordCaptchaProfileService;
use App\Features\Client\GameVerification\Requests\ExportGameVerificationRequest;
use App\Features\Client\GameVerification\Requests\GameVerificationHistoryRequest;
use App\Features\Client\GameVerification\Requests\RetryGameVerificationOrderRequest;
use App\Features\Client\GameVerification\Requests\StoreGameVerificationOrdersRequest;
use App\Features\Client\GameVerification\Resources\GameVerificationOrderResource;
use App\Features\Client\GameVerification\Services\GameVerificationConfigurationService;
use App\Features\Client\GameVerification\Services\GameVerificationFeedService;
use App\Features\Client\GameVerification\Services\GameVerificationOrderService;
use App\Features\NroNotification\Services\NotificationReadCacheService;
use App\Http\Controllers\Controller;
use App\Models\GameVerificationOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GameVerificationController extends Controller
{
    public function index(GameVerificationHistoryRequest $request, GameVerificationOrderService $orders, GameVerificationConfigurationService $configuration, ToolAvailabilityService $tools, NotificationReadCacheService $readCache, GamePasswordCaptchaProfileService $profiles): View
    {
        return view('pages.tools.game-verification', [...$this->historyData($request, $orders),
            'configuration' => $configuration->configuration(), 'service' => $tools->find('account_verification'),
            'captchaProfile' => $profiles->snapshot($request->user()),
            'servers' => $readCache->catalog()['servers']]);
    }

    public function store(StoreGameVerificationOrdersRequest $request, GameVerificationOrderService $orders, GamePasswordCaptchaProfileService $profiles): RedirectResponse
    {
        $orders->create($request->user(), $request->validated('entries'), $request->validated('request_id'), $request->integer('price'), $request->filled('captcha_api_key') ? [
            'key' => $request->validated('captcha_api_key'), 'secret' => $request->validated('captcha_api_secret'),
        ] : $profiles->credentials($request->user()));

        return to_route('tools.game-verification')->with('status', 'Queued');
    }

    public function history(GameVerificationHistoryRequest $request, GameVerificationOrderService $orders): JsonResponse
    {
        abort_unless($request->user()?->status === 'active', 403);

        return response()->json(['html' => view('pages.tools.partials.game-verification-history', $this->historyData($request, $orders))->render()]);
    }

    public function retry(RetryGameVerificationOrderRequest $request, GameVerificationOrder $order, GameVerificationOrderService $orders, GamePasswordCaptchaProfileService $profiles): RedirectResponse|JsonResponse
    {
        $queued = $orders->retry($request->user(), $order->id, $request->integer('attempt_version'), $request->filled('captcha_api_key') ? [
            'key' => $request->validated('captcha_api_key'), 'secret' => $request->validated('captcha_api_secret'),
        ] : $profiles->credentials($request->user()));
        if ($request->expectsJson()) {
            return response()->json(['data' => ['id' => $queued->id, 'status' => $queued->status, 'attempt_version' => $queued->attempt_version]]);
        }

        return to_route('tools.game-verification')->with('status', 'Queued');
    }

    public function stream(GameVerificationHistoryRequest $request, GameVerificationFeedService $feed): StreamedResponse
    {
        return $feed->response($request);
    }

    public function export(ExportGameVerificationRequest $request): JsonResponse
    {
        $ids = $request->validated('ids');
        $orders = GameVerificationOrder::query()->where('user_id', $request->user()->id)->whereIn('id', $ids)->get(['id', 'username', 'server_code', 'status', 'sms_syntax', 'old_password'])->keyBy('id');
        abort_unless($orders->count() === count($ids), 403);
        $items = [];
        foreach ($ids as $id) {
            $order = $orders->get($id);
            abort_unless($order->status === 'success' && filled($order->sms_syntax), 409);
            if ($request->validated('format') === 'password') {
                $line = $order->username.'|'.$order->old_password.'|'.$order->server_code.'|'.$request->validated('new_password');
                $items[] = ['id' => $order->id, 'username' => $order->username, 'line' => $line];
            } else {
                $items[] = ['id' => $order->id, 'username' => $order->username, 'syntax' => $order->sms_syntax];
            }
        }

        return response()->json(['data' => $items]);
    }

    private function historyData(GameVerificationHistoryRequest $request, GameVerificationOrderService $orders): array
    {
        $filters = $request->validated();
        $history = $request->user() ? $orders->history($filters, $request->user()->id)->withPath(route('tools.game-verification'))->appends(Arr::except($filters, ['page'])) : null;

        return ['orders' => $history, 'filters' => $filters, 'statuses' => GameVerificationOrderResource::STATUSES, 'progressLabels' => GameVerificationOrderResource::PROGRESS];
    }
}
