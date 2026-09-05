<?php

namespace App\Rules;

use App\Support\SafeNavigationUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidAffiliateAnnouncementContent implements ValidationRule
{
    private const MAX_BYTES = 1_000_000;

    private const MAX_NODES = 2000;

    private int $nodeCount = 0;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $encoded = is_array($value) ? json_encode($value) : false;

        if (! is_string($encoded) || $value === [] || strlen($encoded) > self::MAX_BYTES) {
            $fail('Nội dung thông báo không hợp lệ hoặc vượt quá dung lượng cho phép.');

            return;
        }

        $this->nodeCount = 0;

        if (! $this->isValidBlockCollection($value)) {
            $fail('Nội dung thông báo chứa định dạng, liên kết hoặc hình ảnh không an toàn.');
        }
    }

    private function isValidBlockCollection(mixed $nodes, int $depth = 0): bool
    {
        if (! is_array($nodes) || $depth > 15) {
            return false;
        }

        foreach ($nodes as $node) {
            $this->nodeCount++;

            if ($this->nodeCount > self::MAX_NODES || ! is_array($node) || ! $this->isValidBlock($node, $depth)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $node */
    private function isValidBlock(array $node, int $depth): bool
    {
        return match ($node['type'] ?? null) {
            'paragraph' => $this->hasOnlyKeys($node, ['type', 'children']) && $this->isValidInlineCollection($node['children'] ?? null),
            'heading' => $this->hasOnlyKeys($node, ['type', 'level', 'children'])
                && is_int($node['level'] ?? null) && $node['level'] >= 1 && $node['level'] <= 6
                && $this->isValidInlineCollection($node['children'] ?? null),
            'image' => $this->hasOnlyKeys($node, ['type', 'src', 'alt', 'width', 'height']) && $this->isValidImage($node),
            'list' => $this->hasOnlyKeys($node, ['type', 'ordered', 'items'])
                && is_bool($node['ordered'] ?? null) && is_array($node['items'] ?? null)
                && collect($node['items'])->every(fn (mixed $item): bool => $this->isValidInlineCollection($item)),
            'container' => $this->hasOnlyKeys($node, ['type', 'tag', 'children'])
                && in_array($node['tag'] ?? null, ['article', 'div', 'section'], true)
                && $this->isValidBlockCollection($node['children'] ?? null, $depth + 1),
            default => false,
        };
    }

    /** @param array<string, mixed> $node */
    private function isValidImage(array $node): bool
    {
        if (! SafeNavigationUrl::passes($node['src'] ?? null) || ! is_string($node['alt'] ?? null) || mb_strlen($node['alt']) > 500) {
            return false;
        }

        foreach (['width', 'height'] as $dimension) {
            if (array_key_exists($dimension, $node) && $node[$dimension] !== null
                && (! is_int($node[$dimension]) || $node[$dimension] < 1 || $node[$dimension] > 10000)) {
                return false;
            }
        }

        return true;
    }

    private function isValidInlineCollection(mixed $nodes): bool
    {
        return is_array($nodes) && count($nodes) <= self::MAX_NODES
            && collect($nodes)->every(fn (mixed $node): bool => is_array($node) && $this->isValidInline($node));
    }

    /** @param array<string, mixed> $node */
    private function isValidInline(array $node): bool
    {
        if (! $this->hasOnlyKeys($node, ['text', 'bold', 'italic', 'underline', 'strike', 'color', 'background', 'href', 'target'])
            || ! is_string($node['text'] ?? null) || mb_strlen($node['text']) > 10000) {
            return false;
        }

        foreach (['bold', 'italic', 'underline', 'strike'] as $format) {
            if (array_key_exists($format, $node) && ! is_bool($node[$format])) {
                return false;
            }
        }

        foreach (['color', 'background'] as $color) {
            if (array_key_exists($color, $node) && ! $this->isSafeColor($node[$color])) {
                return false;
            }
        }

        if (array_key_exists('href', $node) && ! $this->isSafeHref($node['href'])) {
            return false;
        }

        return ! array_key_exists('target', $node)
            || (array_key_exists('href', $node) && in_array($node['target'], ['_blank', '_self'], true));
    }

    private function isSafeHref(mixed $value): bool
    {
        if (! is_string($value) || $value === '' || strlen($value) > 2048 || trim($value) !== $value
            || preg_match('/[\x00-\x20\x7F\\\\]/', $value) === 1 || str_starts_with($value, '//')) {
            return false;
        }

        return str_starts_with($value, '/') || str_starts_with($value, '#')
            || preg_match('/^(?:https?:\/\/|mailto:|tel:)/i', $value) === 1
            || preg_match('/^(?:www\.)?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(?::\d{1,5})?(?:[\/?#].*)?$/i', $value) === 1;
    }

    private function isSafeColor(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^(?:#[0-9a-fA-F]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\))$/', $value) === 1;
    }

    /** @param array<string, mixed> $node @param array<int, string> $allowedKeys */
    private function hasOnlyKeys(array $node, array $allowedKeys): bool
    {
        return array_diff(array_keys($node), $allowedKeys) === [];
    }
}
