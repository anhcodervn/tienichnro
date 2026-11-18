<?php

namespace App\Features\Admin\GameImage\Controllers;

use App\Features\Admin\GameImage\Requests\GameImageHistoryRequest;
use App\Features\Admin\GameImage\Requests\ManageGameImageAccountRequest;
use App\Features\Admin\GameImage\Requests\UpdateGameImageConfigurationRequest;
use App\Features\Admin\GameImage\Requests\UpdateGameImageStatusRequest;
use App\Features\Admin\GameImage\Resources\GameImageHistoryResource;
use App\Features\Admin\GameImage\Services\GameImageAccountManagementService;
use App\Features\Client\GameImage\Resources\GameImageAccountResource;
use App\Features\Client\GameImage\Services\GameImageOrderService;
use App\Http\Controllers\Controller;
use App\Models\GameImageAccount;
use App\Models\ManagedServer;
use Illuminate\Http\JsonResponse;

class GameImageController extends Controller
{
    public function configuration(GameImageOrderService $orders): JsonResponse
    {
        return response()->json(['data' => $orders->configuration()]);
    }

    public function configure(UpdateGameImageConfigurationRequest $request, GameImageOrderService $orders): JsonResponse
    {
        return response()->json(['data' => $orders->configure($request->integer('price')), 'message' => 'Đã lưu cấu hình ghép ảnh.']);
    }

    public function history(GameImageHistoryRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $accounts = GameImageAccount::query()->with('user:id,username')
            ->select(['id', 'user_id', 'username', 'server', 'status', 'capture_version', 'error_code', 'error_message', 'charged_amount', 'refunded_amount', 'refunded_at', 'created_at', 'processing_at', 'completed_at'])
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['q'] ?? null), fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->where('username', 'like', '%'.$filters['q'].'%')->orWhere('server', 'like', '%'.$filters['q'].'%')
                    ->orWhereHas('user', fn ($users) => $users->where('username', 'like', '%'.$filters['q'].'%'));
            }))->latest('id')->paginate(20)->withQueryString();

        return GameImageHistoryResource::collection($accounts)->additional(['servers' => ManagedServer::activeOptions('game')->map(fn ($server): array => ['code' => (string) $server->server_code, 'name' => $server->name])])->response();
    }

    public function update(ManageGameImageAccountRequest $request, GameImageAccount $account, GameImageAccountManagementService $management): JsonResponse
    {
        $updated = $management->update($account, $request->validated());

        return response()->json(['data' => new GameImageHistoryResource($updated->load('user:id,username')), 'message' => 'Đã cập nhật tài khoản ghép ảnh.']);
    }

    public function destroy(ManageGameImageAccountRequest $request, GameImageAccount $account, GameImageAccountManagementService $management, GameImageOrderService $orders): JsonResponse
    {
        $management->delete($account, $request->integer('capture_version'), $orders);

        return response()->json(['message' => 'Đã xoá tài khoản, ảnh và log. Lượt chụp chưa hoàn tất được hoàn phí.']);
    }

    public function status(UpdateGameImageStatusRequest $request, GameImageAccount $account, GameImageOrderService $orders): JsonResponse
    {
        return response()->json(['data' => new GameImageAccountResource($orders->transition($account, $request->validated('status'), $request->validated('error_message'), $request->integer('capture_version', 1), null, $request->validated('error_code')))]);
    }
}
