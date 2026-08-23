<?php

namespace App\Rules;

use App\Support\SafeNavigationUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidSeoPostContent implements ValidationRule
{
    private const MAX_BYTES = 2_000_000;

    private const MAX_NODES = 5000;

    private int $nodeCount = 0;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('Nội dung bài viết không hợp lệ.');

            return;
        }

        $encoded = json_encode($value);

        if (! is_string($encoded) || strlen($encoded) > self::MAX_BYTES) {
            $fail('Nội dung bài viết vượt quá dung lượng cho phép.');

            return;
        }

        $this->nodeCount = 0;

        if (! $this->containsOnlySafeImages($value)) {
            $fail('Nội dung bài viết chứa ảnh chưa được upload hoặc URL ảnh không an toàn.');
        }
    }

    private function containsOnlySafeImages(array $nodes, int $depth = 0): bool
    {
        if ($depth > 20) {
            return false;
        }

        foreach ($nodes as $node) {
            $this->nodeCount++;

            if ($this->nodeCount > self::MAX_NODES) {
                return false;
            }

            if (! is_array($node)) {
                continue;
            }

            if (array_key_exists('src', $node) && ! SafeNavigationUrl::passes($node['src'])) {
                return false;
            }

            foreach (['children', 'items'] as $childKey) {
                if (isset($node[$childKey]) && is_array($node[$childKey]) && ! $this->containsOnlySafeImages($node[$childKey], $depth + 1)) {
                    return false;
                }
            }
        }

        return true;
    }
}
