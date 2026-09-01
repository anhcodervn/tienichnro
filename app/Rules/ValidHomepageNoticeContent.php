<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidHomepageNoticeContent implements ValidationRule
{
    private const MAX_BYTES = 65535;

    private const MAX_NODES = 50;

    private const MAX_INLINE_NODES = 100;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || count($value) > self::MAX_NODES) {
            $fail('Nội dung thông báo không hợp lệ hoặc có quá nhiều khối.');

            return;
        }

        $encoded = json_encode($value);

        if (! is_string($encoded) || strlen($encoded) > self::MAX_BYTES) {
            $fail('Nội dung thông báo vượt quá dung lượng cho phép.');

            return;
        }

        foreach ($value as $node) {
            if (! $this->isValidNode($node)) {
                $fail('Nội dung thông báo chứa định dạng không được hỗ trợ.');

                return;
            }
        }
    }

    private function isValidNode(mixed $node): bool
    {
        if (! is_array($node) || array_diff(array_keys($node), ['type', 'level', 'children', 'ordered', 'items']) !== []) {
            return false;
        }

        return match ($node['type'] ?? null) {
            'heading' => isset($node['level'])
                && is_int($node['level'])
                && $node['level'] >= 1
                && $node['level'] <= 6
                && $this->isValidInlineCollection($node['children'] ?? null),
            'paragraph' => $this->isValidInlineCollection($node['children'] ?? null),
            'list' => is_bool($node['ordered'] ?? null)
                && is_array($node['items'] ?? null)
                && count($node['items']) <= self::MAX_NODES
                && collect($node['items'])->every(fn (mixed $item): bool => $this->isValidInlineCollection($item)),
            default => false,
        };
    }

    private function isValidInlineCollection(mixed $children): bool
    {
        return is_array($children)
            && count($children) <= self::MAX_INLINE_NODES
            && collect($children)->every(fn (mixed $child): bool => $this->isValidInlineNode($child));
    }

    private function isValidInlineNode(mixed $node): bool
    {
        if (! is_array($node) || array_diff(array_keys($node), ['text', 'bold', 'italic', 'underline', 'strike', 'color', 'background', 'href', 'target']) !== []) {
            return false;
        }

        if (! is_string($node['text'] ?? null) || mb_strlen($node['text']) > 4000) {
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

        if (array_key_exists('target', $node)) {
            if (! array_key_exists('href', $node) || ! in_array($node['target'], ['_blank', '_self'], true)) {
                return false;
            }
        }

        return true;
    }

    private function isSafeHref(mixed $value): bool
    {
        if (! is_string($value) || $value === '' || strlen($value) > 2048 || trim($value) !== $value) {
            return false;
        }

        if (preg_match('/[\x00-\x20\x7F\\\\]/', $value) === 1 || str_starts_with($value, '//')) {
            return false;
        }

        if (str_starts_with($value, '/') || str_starts_with($value, '#')) {
            return true;
        }

        return preg_match('/^(?:https?:\/\/|mailto:|tel:)/i', $value) === 1
            || preg_match('/^(?:www\.)?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(?::\d{1,5})?(?:[\/?#].*)?$/i', $value) === 1;
    }

    private function isSafeColor(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^(?:#[0-9a-fA-F]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\))$/', $value) === 1;
    }
}
