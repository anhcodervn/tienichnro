<?php

namespace App\Features\Client\GameImage\Controllers;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\GameImage\Requests\ClaimGameImageCaptureRequest;
use App\Features\Client\GameImage\Requests\GameImageToolPendingRequest;
use App\Features\Client\GameImage\Requests\UpdateGameImageToolStatusRequest;
use App\Features\Client\GameImage\Requests\UploadGameImageToolCaptureRequest;
use App\Features\Client\GameImage\Resources\GameImageToolAccountResource;
use App\Features\Client\GameImage\Services\GameImageAssetService;
use App\Features\Client\GameImage\Services\GameImageCredentialService;
use App\Features\Client\GameImage\Services\GameImageOrderService;
use App\Features\Client\GameImage\Services\GameImageToolService;
use App\Http\Controllers\Controller;
use App\Models\GameImageAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GameImageToolController extends Controller
{
    public function __construct(private readonly ToolAvailabilityService $tools) {}

    public function index(GameImageToolPendingRequest $request): JsonResponse
    {
        $this->ensureEnabled();
        $accounts = GameImageAccount::query()->whereIn('status', ['pending', 'processing'])
            ->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status))
            ->whereHas('user', fn ($users) => $users->where('status', 'active'))
            ->select(['id', 'username', 'server', 'status', 'capture_version', 'cname', 'error_code', 'error_message', 'processing_at', 'completed_at', 'refunded_at', 'capture_disk', 'capture_directory', 'created_at', 'updated_at'])
            ->oldest('updated_at')->orderBy('id')->paginate($request->integer('per_page', 20))->withQueryString();

        return GameImageToolAccountResource::collection($accounts)->response();
    }

    public function show(GameImageToolPendingRequest $request, GameImageAccount $account): GameImageToolAccountResource
    {
        return new GameImageToolAccountResource($account);
    }

    public function claim(ClaimGameImageCaptureRequest $request, GameImageAccount $account, GameImageToolService $tool, GameImageCredentialService $credentials): GameImageToolAccountResource
    {
        $this->ensureEnabled();

        return DB::transaction(function () use ($request, $account, $tool, $credentials): GameImageToolAccountResource {
            $claimed = $tool->claim($account, $request->integer('capture_version'), $request->validated('claim_token'), $request->validated('public_key'));

            return (new GameImageToolAccountResource($claimed))->withEncryptedPassword($credentials->encrypt($claimed, $request->validated('claim_token')));
        }, 3);
    }

    public function status(UpdateGameImageToolStatusRequest $request, GameImageAccount $account, GameImageOrderService $orders, GameImageToolService $tool): GameImageToolAccountResource
    {
        if ($request->validated('status') === 'pending') {
            return new GameImageToolAccountResource($tool->release($account, $request->integer('capture_version'), $request->validated('claim_token')));
        }

        return new GameImageToolAccountResource($orders->transition($account, $request->validated('status'), $request->validated('error_message'), $request->integer('capture_version'), $request->validated('claim_token'), $request->validated('error_code')));
    }

    public function upload(UploadGameImageToolCaptureRequest $request, GameImageAccount $account, GameImageAssetService $assets, GameImageToolService $tool): GameImageToolAccountResource
    {
        $tool->assertClaim($account, $request->integer('capture_version'), $request->validated('claim_token'));
        $assets->storeCaptureArchive($account, $request->file('package'), $request->integer('capture_version'), $request->validated('cName'), $request->validated('claim_token'), true);

        return new GameImageToolAccountResource($account);
    }

    private function ensureEnabled(): void
    {
        abort_unless($this->tools->find('game_image')['is_enabled'] ?? true, 503, 'Game image capture is unavailable.');
    }
}
