<?php

namespace App\Features\Client\Api\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\ApiException;
use App\Features\Client\Api\Requests\CreateTopupTaskRequest;
use App\Features\Client\Api\Resources\TopupTaskResource;
use App\Features\Topup\Services\OrderService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TopupTaskController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function store(CreateTopupTaskRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->status === 'active', 403);

        $validated = $request->validated();
        $idempotencyKey = (string) $validated['request_id'];
        $isExistingTask = Order::query()->where('idempotency_key', $idempotencyKey)->exists();

        try {
            $order = $this->orderService->create(
                payload: [
                    'idempotency_key' => $idempotencyKey,
                    'game_id' => $validated['game_id'],
                    'server_id' => $validated['server_id'],
                    'package_id' => $validated['package_id'],
                    'api_recipients' => $validated['recipients'],
                    'payment_method' => PaymentMethod::Wallet->value,
                ],
                authenticatedUser: $user,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
                allowWalletFallback: false,
            );
        } catch (ValidationException $exception) {
            $status = $exception->validator->errors()->has('idempotency_key') ? 409 : 422;

            throw new ApiException($exception->validator->errors()->first(), $status, previous: $exception);
        }

        return response()->json([
            'status' => true,
            'data' => (new TopupTaskResource($this->loadTask($order)))->resolve($request),
        ], $isExistingTask ? 200 : 201);
    }

    public function show(Request $request, string $task): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if ($user->status !== 'active') {
            throw new ApiException('Tài khoản không thể sử dụng API.', 403);
        }

        $order = Order::query()
            ->where('user_id', $user->id)
            ->where('code', Str::upper($task))
            ->first();

        if (! $order instanceof Order) {
            throw new ApiException('Không tìm thấy task.', 404);
        }

        return response()->json([
            'status' => true,
            'data' => (new TopupTaskResource($this->loadTask($order)))->resolve($request),
        ]);
    }

    private function loadTask(Order $order): Order
    {
        return $order->load([
            'game:id,name',
            'server:id,name',
            'package:id,name',
            'recipients:id,order_id,position,recipient_data,quantity,status,failure_reason,completed_at,failed_at',
        ]);
    }
}
