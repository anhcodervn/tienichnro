<?php

namespace App\Features\Client\Api\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\ApiException;
use App\Features\Client\Api\Actions\ResolveTopupPackageAction;
use App\Features\Client\Api\Requests\CreateTopupOrderRequest;
use App\Features\Client\Api\Resources\TopupOrderResource;
use App\Features\Topup\Services\OrderService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TopupOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly ResolveTopupPackageAction $resolveTopupPackage,
    ) {}

    public function store(CreateTopupOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validated();
        $idempotencyKey = (string) $validated['request_id'];
        $existingOrder = Order::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existingOrder instanceof Order) {
            if ($existingOrder->user_id !== $user->id) {
                throw new ApiException('request_id đã được sử dụng.', 409);
            }

            return response()->json([
                'status' => true,
                'data' => (new TopupOrderResource($this->loadOrder($existingOrder)))->resolve($request),
            ]);
        }

        try {
            $package = $this->resolveTopupPackage->handle(
                gameId: (int) $validated['game'],
                denomination: (int) $validated['price'],
            );
            $order = $this->orderService->create(
                payload: [
                    'idempotency_key' => $idempotencyKey,
                    'game_id' => $validated['game'],
                    'server_id' => $validated['server'],
                    'package_id' => $package->id,
                    'api_recipients' => collect($validated['payload'])
                        ->map(fn (array $recipient): array => [
                            'data' => Arr::except($recipient, ['amount']),
                            'quantity' => (int) $recipient['amount'],
                        ])
                        ->all(),
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
            'data' => (new TopupOrderResource($this->loadOrder($order)))->resolve($request),
        ], 201);
    }

    public function show(Request $request, string $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $topupOrder = Order::query()
            ->where('user_id', $user->id)
            ->where('code', Str::upper($order))
            ->first();

        if (! $topupOrder instanceof Order) {
            throw new ApiException('Không tìm thấy đơn nạp.', 404);
        }

        return response()->json([
            'status' => true,
            'data' => (new TopupOrderResource($this->loadOrder($topupOrder)))->resolve($request),
        ]);
    }

    private function loadOrder(Order $order): Order
    {
        return $order->load([
            'game:id,name',
            'server:id,name',
            'package:id,name',
            'recipients:id,order_id,position,recipient_data,quantity,status,failure_reason,completed_at,failed_at',
        ]);
    }
}
