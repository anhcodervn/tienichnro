<?php

namespace App\Features\Topup\Services\Payments;

use App\Enums\PaymentMethod;
use App\Features\Recharge\Services\ApiBankVnPartnerService;
use App\Features\Recharge\Services\RechargeConfigService;
use App\Features\Reporting\Services\TopupDiscordReporterService;
use App\Models\ConfigRecharge;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OrderBankPaymentService
{
    public function __construct(
        private readonly RechargeConfigService $rechargeConfigService,
        private readonly ApiBankVnPartnerService $apiBankVnPartnerService,
        private readonly TopupDiscordReporterService $discordReporter,
    ) {}

    public function prepare(Order $order): ?PaymentTransaction
    {
        if ($order->payment_method !== PaymentMethod::BankTransfer) {
            return null;
        }

        return Cache::lock("topup-order-payment:{$order->id}", 30)->block(5, function () use ($order): ?PaymentTransaction {
            $existing = $this->findForOrder($order);

            if ($existing instanceof PaymentTransaction) {
                return $existing;
            }

            $config = $this->rechargeConfigService->current();

            if (! $config instanceof ConfigRecharge) {
                return null;
            }

            return $this->rechargeConfigService->isApiBankVnProvider($config)
                ? $this->createApiBankVnRequest($order, $config)
                : $this->createLocalRequest($order, $config);
        });
    }

    public function findForOrder(Order $order): ?PaymentTransaction
    {
        return $order->paymentTransactions()
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function paymentDetails(Order $order): ?array
    {
        $paymentTransaction = $this->findForOrder($order) ?? $this->prepare($order);

        if (! $paymentTransaction instanceof PaymentTransaction) {
            return null;
        }

        $raw = is_array($paymentTransaction->raw_data) ? $paymentTransaction->raw_data : [];

        return [
            'id' => $paymentTransaction->id,
            'code' => $order->code,
            'status' => $this->publicStatus($paymentTransaction->status),
            'bank_name' => $paymentTransaction->bank_code,
            'account_name' => $raw['account_name'] ?? null,
            'account_number' => $paymentTransaction->account_number,
            'amount' => (int) $paymentTransaction->amount,
            'content' => $paymentTransaction->content,
            'qr_url' => $raw['qr_url'] ?? null,
            'expires_at' => $raw['expires_at'] ?? null,
        ];
    }

    private function createApiBankVnRequest(Order $order, ConfigRecharge $config): PaymentTransaction
    {
        $transferContent = $this->transferContent($order);

        try {
            $partnerOrder = $this->apiBankVnPartnerService->createRechargeOrder(
                config: $config,
                amount: (float) $order->total_amount,
                clientOrderCode: $order->code,
                transferContent: $transferContent,
            );
        } catch (Throwable $exception) {
            Log::warning('Unable to create the remote bank payment request for a topup order.', [
                'order_id' => $order->id,
                'exception' => $exception::class,
            ]);
            $this->discordReporter->paymentGatewayFallback($order);

            return $this->createLocalRequest($order, $config, true);
        }

        $qrUrl = (string) ($partnerOrder['qr_url'] ?? $partnerOrder['qr_code_url'] ?? '');

        if ($qrUrl === '') {
            $qrUrl = $this->rechargeConfigService->buildQrUrlForTransfer(
                config: $config,
                amount: $order->total_amount,
                userId: $order->user_id ?? $order->code,
                transferContent: $transferContent,
            );
        }

        return PaymentTransaction::query()->create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'bank_code' => (string) ($partnerOrder['bank_name'] ?? $config->bank_name),
            'account_number' => (string) ($partnerOrder['account_number'] ?? $config->account_number),
            'transaction_code' => $order->code,
            'amount' => $order->total_amount,
            'content' => $transferContent,
            'status' => $this->localStatus((string) ($partnerOrder['status'] ?? 'pending')),
            'raw_data' => [
                'provider' => 'apibankvn_api',
                'recharge_config_id' => $config->id,
                'account_name' => $partnerOrder['account_name'] ?? $config->account_name,
                'qr_url' => $qrUrl,
                'expires_at' => $partnerOrder['expires_at'] ?? now()->addHour()->toISOString(),
                'remote_order_code' => $partnerOrder['order_code'] ?? null,
                'remote_status' => $partnerOrder['status'] ?? null,
                'client_order_code' => $partnerOrder['client_order_code'] ?? $order->code,
            ],
        ]);
    }

    private function createLocalRequest(Order $order, ConfigRecharge $config, bool $gatewayUnavailable = false): PaymentTransaction
    {
        $transferContent = $this->transferContent($order);
        $qrUrl = $this->rechargeConfigService->buildQrUrlForTransfer(
            config: $config,
            amount: $order->total_amount,
            userId: $order->user_id ?? $order->code,
            transferContent: $transferContent,
        );

        return PaymentTransaction::query()->create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'bank_code' => $config->bank_name,
            'account_number' => $config->account_number,
            'transaction_code' => $order->code,
            'amount' => $order->total_amount,
            'content' => $transferContent,
            'status' => 'pending',
            'raw_data' => [
                'provider' => $this->rechargeConfigService->isApiBankVnProvider($config) ? 'apibankvn_api' : 'manual',
                'recharge_config_id' => $config->id,
                'account_name' => $config->account_name,
                'qr_url' => $qrUrl,
                'expires_at' => now()->addHour()->toISOString(),
                'gateway_unavailable' => $gatewayUnavailable,
                'client_order_code' => $order->code,
            ],
        ]);
    }

    private function transferContent(Order $order): string
    {
        return 'NAP '.$order->code;
    }

    private function localStatus(string $status): string
    {
        return match (Str::lower(trim($status))) {
            'processing' => 'matched',
            'paid' => 'success',
            'failed' => 'failed',
            'cancelled', 'canceled', 'expired' => 'cancelled',
            default => 'pending',
        };
    }

    private function publicStatus(string $status): string
    {
        return match ($status) {
            'matched' => 'processing',
            'success' => 'paid',
            'cancelled' => 'cancelled',
            default => $status,
        };
    }
}
