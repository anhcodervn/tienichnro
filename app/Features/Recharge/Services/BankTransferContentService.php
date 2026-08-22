<?php

namespace App\Features\Recharge\Services;

use App\Models\ConfigRecharge;
use App\Models\PaymentTransaction;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class BankTransferContentService
{
    public const DEFAULT_SUFFIX_LENGTH = 8;

    public const MIN_SUFFIX_LENGTH = 6;

    public const MAX_SUFFIX_LENGTH = 8;

    public function generate(ConfigRecharge $config, int $suffixLength = self::DEFAULT_SUFFIX_LENGTH): string
    {
        if ($suffixLength < self::MIN_SUFFIX_LENGTH || $suffixLength > self::MAX_SUFFIX_LENGTH) {
            throw new InvalidArgumentException('Mã đối soát chuyển khoản phải dài từ 6 đến 8 ký tự.');
        }

        $prefix = $this->normalizePrefix((string) $config->transfer_prefix);

        if ($prefix === '') {
            throw new InvalidArgumentException('Tiền tố nội dung chuyển khoản không được để trống.');
        }

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
        $normalizedReference = Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $reference));
        $suffix = Str::substr($normalizedReference, -self::DEFAULT_SUFFIX_LENGTH);

        if (Str::length($suffix) < self::MIN_SUFFIX_LENGTH) {
            $suffix = Str::upper(Str::substr(hash('sha256', (string) $reference), 0, self::DEFAULT_SUFFIX_LENGTH));
        }

        return $this->normalizePrefix($prefix).$suffix;
    }

    public function preview(string $prefix): string
    {
        return $this->normalizePrefix($prefix).'ABC12345';
    }

    public function normalizePrefix(string $prefix): string
    {
        return Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', trim($prefix)));
    }
}
