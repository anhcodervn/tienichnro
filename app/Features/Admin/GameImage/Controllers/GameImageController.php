<?php

namespace App\Features\Admin\GameImage\Controllers;

use App\Features\Admin\GameImage\Requests\GameImageHistoryRequest;
use App\Features\Admin\GameImage\Requests\UpdateGameImageConfigurationRequest;
use App\Features\Admin\GameImage\Requests\UpdateGameImageStatusRequest;
use App\Features\Admin\GameImage\Resources\GameImageHistoryResource;
use App\Features\Client\GameImage\Resources\GameImageAccountResource;
use App\Features\Client\GameImage\Services\GameImageOrderService;
use App\Http\Controllers\Controller;
use App\Models\GameImageAccount;
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
            ->select(['id', 'user_id', 'username', 'server', 'status', 'capture_version', 'error_code', 'error_message', 'charged_amount', 'refunded_at', 'created_at', 'processing_at', 'completed_at'])
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['q'] ?? null), fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->where('username', 'like', '%'.$filters['q'].'%')->orWhere('server', 'like', '%'.$filters['q'].'%')
                    ->orWhereHas('user', fn ($users) => $users->where('username', 'like', '%'.$filters['q'].'%'));
            }))->latest('id')->paginate(20)->withQueryString();

        return GameImageHistoryResource::collection($accounts)->response();
    }

    public function status(UpdateGameImageStatusRequest $request, GameImageAccount $account, GameImageOrderService $orders): JsonResponse
    {
        return response()->json(['data' => new GameImageAccountResource($orders->transition($account, $request->validated('status'), $request->validated('error_message'), $request->integer('capture_version', 1), null, $request->validated('error_code')))]);
    }
}
