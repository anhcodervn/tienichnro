<?php

namespace App\Features\Recharge\Services;

use App\Models\ConfigRecharge;
use App\Models\PaymentTransaction;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class BankTransferContentService
{
    public const TOTAL_LENGTH = 10;

    public const MAX_PREFIX_LENGTH = 4;

    public function generate(ConfigRecharge $config): string
    {
        $prefix = $this->normalizePrefix((string) $config->transfer_prefix);
        $suffixLength = $this->suffixLengthFor($prefix);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $content = $prefix.Str::upper(Str::random($suffixLength));

            $alreadyExists = PaymentTransaction::query()
                ->where(function ($query) use ($content): void {
                    $query->where('transfer_reference', $content)
                        ->orWhere('content', $content);
                })
                ->exists();

            if (! $alreadyExists) {
                return $content;
            }
        }

        throw new RuntimeException('Không thể tạo nội dung chuyển khoản duy nhất.');
    }

    public function fromReference(string $prefix, int|string $reference): string
    {
        $normalizedPrefix = $this->normalizePrefix($prefix);
        $suffixLength = $this->suffixLengthFor($normalizedPrefix);
        $normalizedReference = Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $reference));
        $suffix = Str::substr($normalizedReference, -$suffixLength);

        if (Str::length($suffix) < $suffixLength) {
            $suffix = Str::upper(Str::substr(hash('sha256', (string) $reference), 0, $suffixLength));
        }

        return $normalizedPrefix.$suffix;
    }

    public function preview(string $prefix): string
    {
        $normalizedPrefix = $this->normalizePrefix($prefix);
        $suffixLength = $this->suffixLengthFor($normalizedPrefix);

        return $normalizedPrefix.Str::substr('ABC1234567', 0, $suffixLength);
    }

    public function normalizePrefix(string $prefix): string
    {
        return Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', trim($prefix)));
    }

    public function normalizeContent(string $content): string
    {
        return Str::upper((string) preg_replace('/[\p{Z}\s]+/u', '', $content));
    }

    private function suffixLengthFor(string $prefix): int
    {
        $prefixLength = Str::length($prefix);

        if ($prefixLength === 0) {
            throw new InvalidArgumentException('Tiền tố nội dung chuyển khoản không được để trống.');
        }

        if ($prefixLength > self::MAX_PREFIX_LENGTH) {
            throw new InvalidArgumentException('Tiền tố nội dung chuyển khoản không được dài quá 4 ký tự.');
        }

        return self::TOTAL_LENGTH - $prefixLength;
    }
}
