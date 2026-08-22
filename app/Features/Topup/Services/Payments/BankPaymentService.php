<?php

namespace App\Features\Topup\Services\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Features\Topup\Jobs\ProcessTopupOrder;
use App\Mail\Orders\PaymentReceivedMail;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BankPaymentService
{
    /** @param array<string, mixed> $payload */
    public function match(array $payload, array $rawPayload = []): ?PaymentTransaction
    {
        $content = trim((string) ($payload['transfer_content'] ?? $payload['transaction_description'] ?? ''));
        $clientOrderCode = trim((string) ($payload['client_order_code'] ?? ''));
        $orderCode = $this->extractOrderCode($content.' '.$clientOrderCode);

        if ($orderCode === null) {
            return null;
        }

        $providerReference = trim((string) ($payload['transaction_id'] ?? ''));

        if ($providerReference === '') {
            throw new ApiException('Giao dịch ngân hàng thiếu mã tham chiếu duy nhất.', 422);
        }

        $amount = (int) str((string) ($payload['amount'] ?? 0))->before('.')->toString();
        $shouldDispatch = false;

        $transaction = DB::transaction(function () use ($payload, $rawPayload, $orderCode, $providerReference, $amount, &$shouldDispatch): PaymentTransaction {
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
                    'content' => $payload['transfer_content'] ?? $payload['transaction_description'] ?? $transaction->content,
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
                    'content' => $payload['transfer_content'] ?? $payload['transaction_description'] ?? null,
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
