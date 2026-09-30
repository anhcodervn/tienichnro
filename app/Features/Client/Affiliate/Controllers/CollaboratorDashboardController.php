<?php

namespace App\Features\Client\Affiliate\Controllers;

use App\Features\Admin\GameService\Services\GameServiceOrderProgressService;
use App\Features\Affiliate\Services\AffiliateWalletService;
use App\Features\Client\Affiliate\Requests\CompleteGameServiceOrderRequest;
use App\Features\Client\Affiliate\Requests\StoreAffiliateWithdrawalRequest;
use App\Features\Client\Affiliate\Requests\StoreGameServiceOrderProgressRequest;
use App\Features\Client\Affiliate\Requests\UpdateAffiliatePayoutRequest;
use App\Features\Client\Affiliate\Services\CollaboratorDashboardService;
use App\Http\Controllers\Controller;
use App\Models\AffiliateAnnouncement;
use App\Models\GameServiceOrder;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollaboratorDashboardController extends Controller
{
    public function __construct(
        private readonly CollaboratorDashboardService $service,
        private readonly AffiliateWalletService $walletService,
        private readonly GameServiceOrderProgressService $progressService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->overview($this->user($request))]);
    }

    public function finance(Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->financialData($this->user($request))]);
    }

    public function walletHistory(Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->walletHistory($this->user($request))]);
    }

    public function announcements(Request $request): JsonResponse
    {
        return response()->json(['status' => true, 'data' => $this->service->announcements($this->user($request))]);
    }

    public function readAnnouncement(AffiliateAnnouncement $affiliateAnnouncement, Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Đã đánh dấu thông báo công việc là đã đọc.',
            'data' => ['unread_count' => $this->service->readAnnouncement($this->user($request), $affiliateAnnouncement)],
        ]);
    }

    public function updatePayout(UpdateAffiliatePayoutRequest $request): JsonResponse
    {
        $profile = $this->service->updatePayout($this->user($request), $request->validated());

        return response()->json(['status' => true, 'message' => 'Đã cập nhật tài khoản nhận tiền công.', 'data' => ['bank_name' => $profile->bank_name]]);
    }

    public function withdraw(StoreAffiliateWithdrawalRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $withdrawal = $this->walletService->requestWithdrawal(
            $this->user($request),
            (int) $payload['amount'],
            $payload['idempotency_key'],
            Wallet::TYPE_COLLABORATOR,
        );

        return response()->json([
            'status' => true,
            'message' => 'Đã gửi yêu cầu rút tiền công.',
            'data' => $withdrawal->only(['id', 'amount', 'status', 'created_at']),
        ], 201);
    }

    public function start(GameServiceOrder $gameServiceOrder, Request $request): JsonResponse
    {
        $user = $this->user($request);
        $order = $this->service->startOrder($user, $gameServiceOrder);

        return response()->json(['status' => true, 'message' => 'Đã nhận xử lý đơn.', 'data' => $this->service->orderData($order, $user)]);
    }

    public function preview(GameServiceOrder $gameServiceOrder, Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->service->orderPreview($this->user($request), $gameServiceOrder),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function progress(GameServiceOrder $gameServiceOrder, Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => ['progress' => $this->progressService->timeline($this->user($request), $gameServiceOrder)],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function storeProgress(
        GameServiceOrder $gameServiceOrder,
        StoreGameServiceOrderProgressRequest $request,
    ): JsonResponse {
        $user = $this->user($request);
        $progress = $this->progressService->storeProgress(
            $user,
            $gameServiceOrder,
            $request->string('description')->toString(),
            $request->file('image'),
        );

        return response()->json([
            'status' => true,
            'message' => 'Đã cập nhật tiến trình đơn.',
            'data' => ['progress' => $this->progressService->progressPayload($progress)],
        ], 201);
    }

    public function submit(GameServiceOrder $gameServiceOrder, CompleteGameServiceOrderRequest $request): JsonResponse
    {
        $user = $this->user($request);
        $image = $request->file('image');
        abort_unless($image !== null, 422, 'Phải có ảnh xác minh mới có thể báo hoàn thành.');
        $order = $this->progressService->complete(
            $user,
            $gameServiceOrder,
            $request->string('description')->toString(),
            $image,
        );

        return response()->json([
            'status' => true,
            'message' => 'Đã gửi báo cáo hoàn thành cho admin duyệt.',
            'data' => [
                'order' => $this->service->orderData($order, $user),
                'progress' => $this->progressService->timeline($user, $order),
            ],
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
