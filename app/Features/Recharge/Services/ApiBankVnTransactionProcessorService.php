<?php

namespace App\Features\Recharge\Services;

use App\Exceptions\ApiException;
use App\Features\Client\Wallet\Services\WalletDepositService;
use App\Features\Topup\Services\Payments\BankPaymentService;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ApiBankVnTransactionProcessorService
{
    public function __construct(
        private readonly BankPaymentService $bankPaymentService,
        private readonly WalletDepositService $walletDepositService,
        private readonly BankTransferContentService $bankTransferContentService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rawPayload
     */
    public function process(array $payload, array $rawPayload = []): ?PaymentTransaction
    {
        if (! $this->isIncomingPayment($payload)) {
            return null;
        }

        $transactionId = trim((string) ($payload['transaction_id'] ?? ''));
        $fingerprint = $transactionId !== ''
            ? $transactionId
            : implode('|', [
                (string) ($payload['bank_id'] ?? ''),
                (string) ($payload['client_order_code'] ?? ''),
                (string) ($payload['transfer_content'] ?? $payload['transaction_description'] ?? ''),
                (string) ($payload['amount'] ?? ''),
            ]);

        return Cache::lock('apibankvn:transaction:'.hash('sha256', $fingerprint), 30)
            ->block(10, function () use ($payload, $rawPayload, $transactionId): ?PaymentTransaction {
                if ($transactionId !== '') {
                    $processedTransaction = PaymentTransaction::query()
                        ->where('provider_transaction_id', $transactionId)
                        ->where('status', 'success')
                        ->first();

                    if ($processedTransaction instanceof PaymentTransaction) {
                        $this->ensureSameTransaction($processedTransaction, $payload);

                        return $processedTransaction;
                    }
                }

                return $this->bankPaymentService->match($payload, $rawPayload)
                    ?? $this->walletDepositService->handleApiBankVnCallback($payload, $rawPayload);
            });
    }

    /** @param array<string, mixed> $payload */
    private function isIncomingPayment(array $payload): bool
    {
        $transactionType = Str::lower(trim((string) ($payload['transaction_type'] ?? '')));
        $status = Str::lower(trim((string) ($payload['status'] ?? '')));

        if ($transactionType !== '') {
            return in_array($transactionType, ['credit', 'in', 'incoming', 'deposit'], true);
        }

        return in_array($status, ['paid', 'success', 'completed'], true);
    }

    /** @param array<string, mixed> $payload */
    private function ensureSameTransaction(PaymentTransaction $processedTransaction, array $payload): void
    {
        $incomingAmount = (int) str((string) ($payload['amount'] ?? 0))->before('.')->toString();
        $receivedContent = $this->bankTransferContentService->normalizeContent(
            (string) ($payload['transfer_content'] ?? $payload['transaction_description'] ?? ''),
        );
        $expectedContent = $this->bankTransferContentService->normalizeContent(
            (string) ($processedTransaction->transfer_reference ?: $processedTransaction->content),
        );
        $hasDifferentAmount = $incomingAmount > 0 && $incomingAmount !== (int) $processedTransaction->amount;
        $hasDifferentContent = $receivedContent !== ''
            && $expectedContent !== ''
            && ! str_contains($receivedContent, $expectedContent);

        if ($hasDifferentAmount || $hasDifferentContent) {
            throw new ApiException('Ma giao dich ngan hang da duoc xu ly cho mot yeu cau khac.', 409);
        }
    }
}
