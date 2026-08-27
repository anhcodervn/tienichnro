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
use App\Models\User;
use App\Models\Wallet;
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
                );
                $package = $quote['package'];
                $package->loadMissing('provider');
                $this->providerResolver->assertAvailable($package, $quote['server']);
                $walletBalance = $user instanceof User && $requestedPaymentMethod === PaymentMethod::Wallet
                    ? Wallet::query()
                        ->where('user_id', $user->id)
                        ->where('type', Wallet::TYPE_MAIN)
                        ->lockForUpdate()
                        ->value('balance')
                    : null;
                $canPayWithWallet = $walletBalance !== null && (int) $walletBalance >= $quote['total_amount'];

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

                $order = Order::query()->create([
                    'idempotency_key' => $idempotencyKey,
                    'user_id' => $user?->id,
                    'email' => $email,
                    'normalized_email' => $normalizedEmail,
                    'game_id' => $package->game_id,
                    'game_server_id' => $serverId,
                    'topup_package_id' => $package->id,
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
                    'subtotal' => $quote['subtotal'],
                    'discount_amount' => $quote['discount_amount'],
                    'total_amount' => $quote['total_amount'],
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentMethod === PaymentMethod::Wallet ? PaymentStatus::Paid : PaymentStatus::Pending,
                    'paid_at' => $paymentMethod === PaymentMethod::Wallet ? now() : null,
                    'customer_ip' => $ip,
                    'user_agent' => $userAgent,
                    'metadata' => [
                        'package' => [
                            'original_price' => $package->original_price,
                            'discount_percent' => $package->discount_percent,
                            'bonus_text' => $package->bonus_text,
                        ],
                        'provider' => [
                            'slug' => $package->provider?->slug ?? 'manual',
                            'service_code' => $package->provider_service_code,
                        ],
                    ],
                ]);

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
