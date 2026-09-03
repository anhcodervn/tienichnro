<?php

namespace App\Features\Topup\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Features\Client\Wallet\Services\WalletService;
use App\Features\Topup\Jobs\ProcessTopupOrder;
use App\Features\Topup\Services\Payments\OrderBankPaymentService;
use App\Mail\Orders\OrderCreatedMail;
use App\Models\Game;
use App\Models\Order;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly OrderPricingService $pricingService,
        private readonly OrderRecipientService $recipientService,
        private readonly WalletService $walletService,
        private readonly TopupProviderResolver $providerResolver,
        private readonly OrderBankPaymentService $orderBankPaymentService,
    ) {}

    /** @param array<string, mixed> $payload */
    public function create(
        array $payload,
        ?User $authenticatedUser,
        ?string $ip,
        ?string $userAgent,
        bool $allowWalletFallback = true,
    ): Order {
        $idempotencyKey = (string) $payload['idempotency_key'];
        $email = $this->resolveCustomerEmail($payload, $authenticatedUser);

        if ($existing = Order::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return $this->ensureSameCustomer($existing, $authenticatedUser, $email);
        }

        $normalizedEmail = Str::lower($email);
        $requestedPaymentMethod = PaymentMethod::from((string) $payload['payment_method']);
        $serverId = (int) $payload['server_id'];
        $tenant = app(TenantContext::class)->current();

        if ($tenant instanceof Tenant && ! $tenant->is_main && (! $authenticatedUser instanceof User || $requestedPaymentMethod !== PaymentMethod::Wallet)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Website đại lý hiện chỉ nhận đơn từ thành viên thanh toán bằng số dư.',
            ]);
        }

        try {
            $order = DB::transaction(function () use ($payload, $authenticatedUser, $idempotencyKey, $email, $normalizedEmail, $requestedPaymentMethod, $serverId, $ip, $userAgent, $allowWalletFallback): Order {
                $user = $authenticatedUser instanceof User
                    ? User::query()->lockForUpdate()->findOrFail($authenticatedUser->id)
                    : null;

                $game = Game::query()->active()->lockForUpdate()->find((int) $payload['game_id']);

                if (! $game instanceof Game) {
                    throw ValidationException::withMessages(['game_id' => 'Game không tồn tại hoặc đang tạm tắt.']);
                }

                $recipientData = $this->recipientService->resolve($game, $payload);

                $quote = $this->pricingService->quote(
                    gameId: (int) $payload['game_id'],
                    serverId: $serverId,
                    packageId: (int) $payload['package_id'],
                    quantity: $recipientData['quantity'],
                    lock: true,
                    quantityField: $recipientData['quantity_field'],
                    recipientQuantities: array_column($recipientData['recipients'], 'quantity'),
                    user: $user,
                );
                $package = $quote['package'];
                $package->loadMissing(['game', 'provider']);
                $this->providerResolver->assertAvailable($package, $quote['server']);
                $walletBalance = $user instanceof User && $requestedPaymentMethod === PaymentMethod::Wallet
                    ? Wallet::query()
                        ->where('user_id', $user->id)
                        ->where('type', Wallet::TYPE_MAIN)
                        ->lockForUpdate()
                        ->value('balance')
                    : null;
                $canPayWithWallet = $walletBalance !== null && (int) $walletBalance >= $quote['total_amount'];

                $tenant = app(TenantContext::class)->current();
                $billingUser = $tenant instanceof Tenant && ! $tenant->is_main
                    ? User::query()->withoutGlobalScope(TenantScope::class)->lockForUpdate()->find($tenant->billing_user_id)
                    : null;
                $billingWalletBalance = $billingUser instanceof User
                    ? Wallet::query()->withoutGlobalScope(TenantScope::class)
                        ->where('user_id', $billingUser->id)
                        ->where('type', Wallet::TYPE_MAIN)
                        ->lockForUpdate()
                        ->value('balance')
                    : null;

                if ($tenant instanceof Tenant && ! $tenant->is_main
                    && (! $billingUser instanceof User || $billingWalletBalance === null || (int) $billingWalletBalance < $quote['tenant_cost_total'])) {
                    throw ValidationException::withMessages([
                        'site' => 'Tài khoản thanh toán NapCarot của website không đủ số dư để tạo đơn.',
                    ]);
                }

                if ($tenant instanceof Tenant && ! $tenant->is_main && ! $canPayWithWallet) {
                    throw new ApiException(
                        'Số dư thành viên không đủ để thanh toán đơn hàng trên website đại lý.',
                        422,
                        ['data' => [
                            'balance' => (int) ($walletBalance ?? 0),
                            'required_amount' => $quote['total_amount'],
                            'currency' => 'VND',
                        ]],
                    );
                }

                if ($requestedPaymentMethod === PaymentMethod::Wallet && ! $canPayWithWallet && ! $allowWalletFallback) {
                    throw new ApiException(
                        'Số dư ví không đủ để tạo đơn nạp.',
                        422,
                        ['data' => [
                            'balance' => (int) ($walletBalance ?? 0),
                            'required_amount' => $quote['total_amount'],
                            'currency' => 'VND',
                        ]],
                    );
                }

                $paymentMethod = $requestedPaymentMethod === PaymentMethod::Wallet && $canPayWithWallet
                    ? PaymentMethod::Wallet
                    : PaymentMethod::BankTransfer;

                $orderAttributes = [
                    'idempotency_key' => $idempotencyKey,
                    'user_id' => $user?->id,
                    'email' => $email,
                    'normalized_email' => $normalizedEmail,
                    'game_id' => $package->game_id,
                    'game_server_id' => $serverId,
                    'topup_package_id' => $package->id,
                    'package_source' => $quote['package_source'],
                    'global_topup_package_id' => $quote['global_topup_package_id'],
                    'global_topup_package_name' => $quote['global_topup_package_name'],
                    'topup_provider_id' => $package->provider_id,
                    'purchase_mode' => $recipientData['mode'],
                    'checkout_fields_snapshot' => $recipientData['fields'],
                    'game_account' => $recipientData['game_account'],
                    'game_character' => $recipientData['game_character'],
                    'quantity' => $recipientData['quantity'],
                    'package_name' => $package->name,
                    'denomination' => $package->denomination,
                    'carot_amount' => $package->carot_amount,
                    'unit_price' => $quote['unit_price'],
                    'sale_unit_price' => $quote['sale_unit_price'],
                    'retail_unit_price' => $quote['retail_unit_price'],
                    'subtotal' => $quote['subtotal'],
                    'discount_amount' => $quote['discount_amount'],
                    'total_amount' => $quote['total_amount'],
                    'provider_unit_cost' => $quote['provider_unit_cost'],
                    'provider_total_cost' => $quote['provider_total_cost'],
                    'gross_profit' => $quote['gross_profit'],
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentMethod === PaymentMethod::Wallet ? PaymentStatus::Paid : PaymentStatus::Pending,
                    'paid_at' => $paymentMethod === PaymentMethod::Wallet ? now() : null,
                    'customer_ip' => $ip,
                    'user_agent' => $userAgent,
                    'metadata' => [
                        'package' => [
                            'original_price' => $quote['unit_price'],
                            'package_source' => $quote['package_source'],
                            'bonus_text' => $package->bonus_text,
                            'receives' => $package->rewardItems($package->game?->reward_label),
                        ],
                        'provider' => [
                            'slug' => $package->provider?->slug ?? 'manual',
                            'service_code' => $package->providerServiceCode(),
                            'server_code' => $quote['server']->code,
                        ],
                    ],
                ];

                if (app(TenantContext::class)->isActive()) {
                    $orderAttributes = [
                        ...$orderAttributes,
                        'tenant_id' => $tenant?->id,
                        'billing_user_id' => $billingUser?->id,
                        'tenant_cost_unit_price' => $quote['tenant_cost_unit_price'],
                        'tenant_cost_total' => $quote['tenant_cost_total'],
                        'tenant_profit' => $quote['tenant_profit'],
                    ];
                }

                $order = Order::query()->create($orderAttributes);

                $order->recipients()->createMany(
                    collect($recipientData['recipients'])
                        ->values()
                        ->map(fn (array $recipient, int $index): array => [
                            'position' => $index + 1,
                            'recipient_data' => $recipient['data'],
                            'quantity' => $recipient['quantity'],
                        ])
                        ->all(),
                );

                if ($paymentMethod === PaymentMethod::Wallet && $user instanceof User) {
                    $this->walletService->debit(
                        user: $user,
                        amount: $quote['total_amount'],
                        referenceType: Order::class,
                        referenceId: $order->id,
                        description: "Thanh toán đơn nạp game {$order->code}",
                        idempotencyKey: $idempotencyKey,
                    );

                    if ($billingUser instanceof User) {
                        $this->walletService->debit(
                            user: $billingUser,
                            amount: $quote['tenant_cost_total'],
                            referenceType: Order::class,
                            referenceId: $order->id,
                            description: "Giá vốn website {$tenant?->name} cho đơn {$order->code}",
                            idempotencyKey: "tenant-billing:{$idempotencyKey}",
                        );
                    }
                }

                return $order;
            }, 3);
        } catch (QueryException $exception) {
            $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();

            if (! $existing instanceof Order) {
                throw $exception;
            }

            return $this->ensureSameCustomer($existing, $authenticatedUser, $email);
        }

        Mail::to($order->email)->queue(new OrderCreatedMail($order));

        if ($order->payment_method === PaymentMethod::Wallet) {
            ProcessTopupOrder::dispatch($order->id)->afterCommit();
        } else {
            $this->orderBankPaymentService->prepare($order);
        }

        return $order->load(['game:id,name,slug', 'server:id,name', 'package:id,name']);
    }

    /** @param array<string, mixed> $payload */
    private function resolveCustomerEmail(array $payload, ?User $authenticatedUser): string
    {
        $email = trim((string) ($authenticatedUser?->email ?? $payload['email'] ?? ''));

        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'Tài khoản của bạn chưa có email. Vui lòng cập nhật email tài khoản trước khi tạo đơn.',
            ]);
        }

        return $email;
    }

    private function ensureSameCustomer(Order $order, ?User $user, string $email): Order
    {
        $matchesCustomer = $order->user_id !== null
            ? $user instanceof User && $order->user_id === $user->id
            : hash_equals($order->normalized_email, Str::lower(trim($email)));

        if (! $matchesCustomer) {
            throw ValidationException::withMessages(['idempotency_key' => 'Mã chống trùng không hợp lệ. Vui lòng tải lại trang.']);
        }

        return $order->load(['game:id,name,slug', 'server:id,name', 'package:id,name']);
    }
}
