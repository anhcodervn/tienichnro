<?php

namespace App\Features\Client\GameImage\Controllers;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\GameImage\Requests\GameImageAccountIndexRequest;
use App\Features\Client\GameImage\Requests\ManageGameImageOrderRequest;
use App\Features\Client\GameImage\Requests\RecaptureGameImageAccountRequest;
use App\Features\Client\GameImage\Requests\StoreGameImageAccountRequest;
use App\Features\Client\GameImage\Requests\StoreGameImageCaptureRequest;
use App\Features\Client\GameImage\Requests\StreamGameImageAccountsRequest;
use App\Features\Client\GameImage\Requests\UpdateGameImageAccountDetailsRequest;
use App\Features\Client\GameImage\Requests\UpdateGameImageLayoutRequest;
use App\Features\Client\GameImage\Resources\GameImageAccountResource;
use App\Features\Client\GameImage\Services\GameImageAccountDetailsService;
use App\Features\Client\GameImage\Services\GameImageAccountFeedService;
use App\Features\Client\GameImage\Services\GameImageAccountListService;
use App\Features\Client\GameImage\Services\GameImageAssetService;
use App\Features\Client\GameImage\Services\GameImageOrderService;
use App\Http\Controllers\Controller;
use App\Models\GameImageAccount;
use App\Models\ManagedServer;
use Generator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GameImageController extends Controller
{
    public function __construct(private readonly ToolAvailabilityService $tools) {}

    public function index(GameImageAccountIndexRequest $request, GameImageOrderService $orders, GameImageAccountListService $lists): View
    {
        $filters = $request->validated();
        $accounts = $request->user() ? $lists->paginate($request->user(), $filters) : null;

        return view('pages.tools.game-image', [
            'service' => $this->tool(),
            'accounts' => $accounts,
            'filters' => $filters,
            'capturePrice' => $orders->configuration()['price'],
            'servers' => ManagedServer::activeOptions('game'),
        ]);
    }

    public function stream(StreamGameImageAccountsRequest $request, GameImageAccountFeedService $feed): StreamedResponse
    {
        $this->ensureEnabled();
        $user = $request->user();
        $filters = $request->validated();
        $lock = null;
        for ($slot = 0; $slot < 2; $slot++) {
            $candidate = Cache::lock('game-image:stream:'.$user->id.':'.$slot, 40);
            if ($candidate->get()) {
                $lock = $candidate;
                break;
            }
        }
        abort_if($lock === null, 429);
        $request->session()->save();

        return response()->eventStream(function () use ($feed, $user, $filters, $lock): Generator {
            echo "retry: 3000\n\n";
            try {
                yield from $feed->events($user, $filters);
            } finally {
                $lock->release();
            }
        }, endStreamWith: null);
    }

    public function store(StoreGameImageAccountRequest $request, GameImageOrderService $orders): RedirectResponse
    {
        $this->ensureEnabled();
        $orders->create($request->user(), $request->validated());

        return to_route('tools.game-image')->with('status', 'Đã thêm tài khoản và trừ phí. Tài khoản đang chờ ghép ảnh.');
    }

    public function recapture(RecaptureGameImageAccountRequest $request, GameImageAccount $account, GameImageOrderService $orders): JsonResponse
    {
        $this->ensureEnabled();
        $orders->recapture($account, $request->integer('capture_version'), $request->integer('price'));

        return response()->json(['message' => 'Đã trừ phí và đưa tài khoản vào hàng chờ chụp lại.']);
    }

    public function control(ManageGameImageOrderRequest $request, GameImageAccount $account, GameImageOrderService $orders): JsonResponse
    {
        $updated = $orders->control($request->user(), $account, $request->validated('action'), $request->integer('capture_version'));

        return response()->json(['data' => ['id' => $updated->id, 'status' => $updated->status, 'capture_version' => $updated->capture_version, 'refunded_amount' => $updated->refunded_amount]]);
    }

    public function details(UpdateGameImageAccountDetailsRequest $request, GameImageAccount $account, GameImageAccountDetailsService $details): JsonResponse
    {
        $this->ensureEnabled();
        $updated = $details->update($account, $request->validated());

        return response()->json(['message' => 'Đã cập nhật thông tin tài khoản.', 'data' => ['id' => $updated->id, 'username' => $updated->username, 'server' => $updated->server, 'capture_version' => $updated->capture_version]]);
    }

    public function edit(Request $request, GameImageAccount $account): View
    {
        abort_unless($account->user_id === $request->user()->id && $request->user()->status === 'active', 404);
        $this->ensureEnabled();

        return view('pages.tools.game-image-edit', ['account' => (new GameImageAccountResource($account))->resolve()]);
    }

    public function update(UpdateGameImageLayoutRequest $request, GameImageAccount $account, GameImageAssetService $assets): JsonResponse
    {
        $this->ensureEnabled();
        $assets->saveLayout($account, $request->layout(), $request->validated('preview'));

        return response()->json(['message' => 'Đã lưu bố cục.', 'data' => new GameImageAccountResource($account)]);
    }

    public function capture(StoreGameImageCaptureRequest $request, GameImageAccount $account, GameImageAssetService $assets): JsonResponse
    {
        $this->ensureEnabled();
        if ($request->hasFile('package')) {
            $assets->storeCaptureArchive($account, $request->file('package'), $request->integer('capture_version', 1), $request->validated('cName'));
        } else {
            $assets->storeCapture($account, $request->file('files'), $request->validated('paths'), $request->integer('capture_version', 1), $request->validated('cName'));
        }

        return response()->json(['message' => 'Đã tải lên bộ dữ liệu ảnh.', 'data' => new GameImageAccountResource($account)]);
    }

    public function downloadData(Request $request, GameImageAccount $account, GameImageAssetService $assets): BinaryFileResponse
    {
        $this->authorizeOwner($request, $account);

        return response()->download($assets->captureZip($account), 'nro-account-'.$account->id.'-data.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    public function preview(Request $request, GameImageAccount $account): BinaryFileResponse
    {
        $this->authorizeOwner($request, $account);
        abort_unless($account->preview_path !== null && Storage::disk('local')->exists($account->preview_path), 404);
        $path = Storage::disk('local')->path($account->preview_path);

        return $request->boolean('download') ? response()->download($path, 'nro-account-'.$account->id.'.png', ['Content-Type' => 'image/png'])
            : response()->file($path, ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function source(Request $request, GameImageAccount $account, string $path, GameImageAssetService $assets): BinaryFileResponse
    {
        $this->authorizeOwner($request, $account);

        return response()->file($assets->sourcePath($account, $path), ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function authorizeOwner(Request $request, GameImageAccount $account): void
    {
        abort_unless($request->user()?->status === 'active' && $account->user_id === $request->user()->id, 404);
    }

    /** @return array<string, mixed> */
    private function tool(): array
    {
        return $this->tools->find('game_image') ?? ['is_enabled' => true, 'maintenance_message' => ''];
    }

    private function ensureEnabled(): void
    {
        abort_unless($this->tool()['is_enabled'], 403, 'Công cụ ghép ảnh đang bảo trì.');
    }
}
