<?php

namespace App\Features\Topup\Services\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Features\Topup\Jobs\ProcessTopupOrder;
use App\Mail\Orders\PaymentReceivedMail;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BankPaymentService
{
    /** @param array<string, mixed> $payload */
    public function match(array $payload, array $rawPayload = []): ?PaymentTransaction
    {
        $transferContent = trim((string) ($payload['transfer_content'] ?? ''));
        $transactionDescription = trim((string) ($payload['transaction_description'] ?? ''));
        $content = Str::upper($transferContent !== '' ? $transferContent : $transactionDescription);
        $clientOrderCode = trim((string) ($payload['client_order_code'] ?? ''));
        $amount = (int) str((string) ($payload['amount'] ?? 0))->before('.')->toString();
        $orderCode = $this->resolveOrderCode($content, $clientOrderCode, $amount);

        if ($orderCode === null) {
            return null;
        }

        $providerReference = trim((string) ($payload['transaction_id'] ?? ''));

        if ($providerReference === '') {
            throw new ApiException('Giao dịch ngân hàng thiếu mã tham chiếu duy nhất.', 422);
        }

        $shouldDispatch = false;

        $transaction = DB::transaction(function () use ($payload, $rawPayload, $orderCode, $providerReference, $amount, $content, &$shouldDispatch): PaymentTransaction {
            $existing = PaymentTransaction::query()
                ->where('provider_transaction_id', $providerReference)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof PaymentTransaction) {
                return $existing;
            }

            $order = Order::query()->where('code', $orderCode)->lockForUpdate()->first();

            if (! $order instanceof Order) {
                throw new ApiException('Không tìm thấy đơn hàng phù hợp với nội dung chuyển khoản.', 404);
            }

            if ($amount !== (int) $order->total_amount) {
                throw new ApiException('Số tiền chuyển khoản không khớp tổng tiền đơn hàng.', 422);
            }

            $transaction = PaymentTransaction::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($transaction instanceof PaymentTransaction) {
                $rawData = is_array($transaction->raw_data) ? $transaction->raw_data : [];
                $rawData['callback_payload'] = $this->sanitize($rawPayload);

                $transaction->forceFill([
                    'bank_code' => $payload['bank_name'] ?? $transaction->bank_code,
                    'account_number' => $payload['account_number'] ?? $transaction->account_number,
                    'provider_transaction_id' => $providerReference,
                    'content' => $transaction->content ?: ($content !== '' ? $content : null),
                    'raw_data' => $rawData,
                    'status' => 'success',
                ])->save();
            } else {
                $transaction = PaymentTransaction::query()->create([
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'bank_code' => $payload['bank_name'] ?? null,
                    'account_number' => $payload['account_number'] ?? null,
                    'transaction_code' => $providerReference,
                    'provider_transaction_id' => $providerReference,
                    'amount' => $amount,
                    'content' => $content !== '' ? $content : null,
                    'raw_data' => ['provider' => 'apibankvn_api', 'callback_payload' => $this->sanitize($rawPayload)],
                    'status' => 'success',
                ]);
            }

            if ($order->payment_status === PaymentStatus::Pending) {
                $order->forceFill(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()])->save();
                $shouldDispatch = true;
            }

            return $transaction;
        }, 3);

        if ($shouldDispatch) {
            $order = $transaction->order()->firstOrFail();
            Mail::to($order->email)->queue(new PaymentReceivedMail($order));
            ProcessTopupOrder::dispatch($order->id)->afterCommit();
        }

        return $transaction;
    }

    private function resolveOrderCode(string $content, string $clientOrderCode, int $amount): ?string
    {
        $normalizedContent = Str::upper(trim($content));

        if ($normalizedContent === '') {
            return $this->extractOrderCode($clientOrderCode);
        }

        $transaction = PaymentTransaction::query()
            ->with('order:id,code')
            ->whereNotNull('order_id')
            ->where(function (Builder $query) use ($normalizedContent): void {
                $query->where('transfer_reference', $normalizedContent)
                    ->orWhere('content', $normalizedContent);
            })
            ->latest('id')
            ->first();

        if ($transaction?->order instanceof Order) {
            return $transaction->order->code;
        }

        /** @var Collection<int, PaymentTransaction> $candidates */
        $candidates = PaymentTransaction::query()
            ->with('order:id,code')
            ->whereNotNull('order_id')
            ->whereIn('status', ['pending', 'matched'])
            ->when($amount > 0, fn (Builder $query) => $query->where('amount', $amount))
            ->latest('id')
            ->limit(100)
            ->get();

        $matches = $candidates->filter(function (PaymentTransaction $candidate) use ($normalizedContent): bool {
            $reference = Str::upper(trim((string) ($candidate->transfer_reference ?: $candidate->content)));

            return $reference !== '' && str_contains($normalizedContent, $reference);
        });

        if ($matches->count() !== 1) {
            return null;
        }

        $transaction = $matches->first();

        return $transaction?->order instanceof Order ? $transaction->order->code : null;
    }

    private function extractOrderCode(string $content): ?string
    {
        preg_match('/\bTOP\d{6}[A-Z0-9]{6}\b/i', $content, $matches);

        return isset($matches[0]) ? strtoupper($matches[0]) : null;
    }

    /** @param array<string, mixed> $payload */
    private function sanitize(array $payload): array
    {
        unset($payload['api_key'], $payload['api_secret'], $payload['webhook_secret'], $payload['sign']);

        return $payload;
    }
}
