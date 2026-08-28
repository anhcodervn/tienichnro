<?php

namespace App\Features\Recharge\Services;

use App\Models\ConfigRecharge;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ApiBankVnTransactionPollingService
{
    public function __construct(
        private readonly ApiBankVnPartnerService $partnerService,
        private readonly ApiBankVnTransactionProcessorService $transactionProcessor,
    ) {}

    /**
     * @return array{configs: int, fetched: int, matched: int, ignored: int, failed: int}
     */
    public function poll(
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        int $limit = 20,
        bool $forceRefresh = true,
    ): array {
        $result = [
            'configs' => 0,
            'fetched' => 0,
            'matched' => 0,
            'ignored' => 0,
            'failed' => 0,
        ];

        ConfigRecharge::query()
            ->where('provider', 'apibankvn_api')
            ->where('is_active', true)
            ->whereNotNull('api_bank_id')
            ->whereNotNull('api_key')
            ->whereNotNull('api_secret')
            ->orderBy('id')
            ->each(function (ConfigRecharge $config) use ($startDate, $endDate, $limit, $forceRefresh, &$result): void {
                $result['configs']++;

                try {
                    $transactions = $this->partnerService->fetchTransactions(
                        $config,
                        $startDate,
                        $endDate,
                        $limit,
                        $forceRefresh,
                    );
                } catch (Throwable $exception) {
                    $result['failed']++;
                    Log::warning('Khong the lay giao dich APIBankVN.', [
                        'recharge_config_id' => $config->id,
                        'bank_id' => $config->api_bank_id,
                        'exception' => $exception::class,
                    ]);

                    return;
                }

                $result['fetched'] += count($transactions);

                foreach ($transactions as $transaction) {
                    $payload = $this->normalizeTransaction($transaction);

                    if ($payload === null) {
                        $result['ignored']++;

                        continue;
                    }

                    try {
                        $paymentTransaction = $this->transactionProcessor->process($payload, $transaction);

                        $result[$paymentTransaction === null ? 'ignored' : 'matched']++;
                    } catch (Throwable $exception) {
                        $result['failed']++;
                        Log::warning('Khong the doi soat mot giao dich APIBankVN.', [
                            'recharge_config_id' => $config->id,
                            'transaction_fingerprint' => hash('sha256', (string) ($payload['transaction_id'] ?? '')),
                            'exception' => $exception::class,
                        ]);
                    }
                }
            });

        return $result;
    }

    /**
     * @param  array<string, mixed>  $transaction
     * @return array<string, mixed>|null
     */
    private function normalizeTransaction(array $transaction): ?array
    {
        $rawData = is_array($transaction['raw_data'] ?? null) ? $transaction['raw_data'] : [];
        $nestedRawData = is_array($rawData['raw_data'] ?? null) ? $rawData['raw_data'] : [];
        $source = [...$nestedRawData, ...$rawData, ...$transaction];
        $transactionType = Str::lower(trim((string) $this->first($source, [
            'transaction_type', 'type', 'direction', 'transactionType',
        ])));
        $creditAmount = (float) $this->first($source, ['credit_amount', 'creditAmount'], 0);
        $debitAmount = (float) $this->first($source, ['debit_amount', 'debitAmount'], 0);
        $isExplicitCredit = in_array($transactionType, ['credit', 'in', 'incoming', 'deposit'], true);

        if ($debitAmount > 0 || (! $isExplicitCredit && $creditAmount <= 0)) {
            return null;
        }

        $transactionId = trim((string) $this->first($source, [
            'transaction_id', 'transactionId', 'id', 'reference_id', 'reference', 'transaction_code',
        ]));
        $amount = (float) $this->first($source, [
            'credit_amount', 'creditAmount', 'amount', 'transaction_amount', 'transactionAmount',
        ], 0);
        $description = trim((string) $this->first($source, [
            'transfer_content', 'transferContent', 'description', 'transaction_description',
            'transactionDescription', 'content', 'remark', 'addInfo',
        ]));

        if ($transactionId === '' || $amount <= 0 || $description === '') {
            return null;
        }

        return [
            'transaction_id' => $transactionId,
            'transfer_content' => $description,
            'transaction_description' => $description,
            'transaction_time' => $this->first($source, ['transaction_time', 'transactionTime', 'transaction_date', 'transactionDate', 'date']),
            'transaction_type' => $transactionType !== '' ? $transactionType : 'credit',
            'status' => 'paid',
            'amount' => $amount,
            'bank_name' => $this->first($source, ['bank_name', 'bankName', 'bank_code', 'bankCode']),
            'account_number' => $this->first($source, ['account_number', 'accountNumber', 'account_no', 'accountNo']),
            'bank_account_id' => $this->first($source, ['bank_account_id', 'bankAccountId']),
            'paid_at' => $this->first($source, ['paid_at', 'paidAt', 'transaction_time', 'transactionTime', 'transaction_date', 'transactionDate']),
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $keys
     */
    private function first(array $source, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (Arr::exists($source, $key) && $source[$key] !== null && $source[$key] !== '') {
                return $source[$key];
            }
        }

        return $default;
    }
}
