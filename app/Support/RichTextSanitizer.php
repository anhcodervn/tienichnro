<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class RichTextSanitizer
{
    /** @var array<int, string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'span',
        'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'hr',
        'a', 'img', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
    ];

    /** @var array<int, string> */
    private const REMOVED_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math'];

    /** @var array<int, string> */
    private const STYLE_PROPERTIES = [
        'color', 'background-color', 'text-align', 'font-size', 'font-family',
        'font-weight', 'font-style', 'text-decoration', 'padding-left',
    ];

    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div data-rich-text-root="true">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        if (! $loaded) {
            return '';
        }

        $root = (new DOMXPath($document))->query('//*[@data-rich-text-root="true"]')?->item(0);

        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->sanitizeChildren($root);

        return collect(iterator_to_array($root->childNodes))
            ->map(fn (DOMNode $node): string => $document->saveHTML($node) ?: '')
            ->implode('');
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE) {
                $parent->removeChild($node);

                continue;
            }

            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::REMOVED_WITH_CONTENT, true)) {
                $parent->removeChild($node);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->sanitizeChildren($node);
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeAttributes($node, $tag);

            if ($tag === 'img' && ! $node->hasAttribute('src')) {
                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeChildren($node);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $isAllowed = $name === 'style'
                || ($tag === 'a' && in_array($name, ['href', 'target', 'rel', 'title'], true))
                || ($tag === 'img' && in_array($name, ['src', 'alt', 'width', 'height'], true))
                || (in_array($tag, ['th', 'td'], true) && in_array($name, ['colspan', 'rowspan', 'scope'], true));

            if (! $isAllowed || str_starts_with($name, 'on')) {
                $element->removeAttributeNode($attribute);
            }
        }

        if ($element->hasAttribute('style')) {
            $style = $this->sanitizeStyle($element->getAttribute('style'));
            $style === '' ? $element->removeAttribute('style') : $element->setAttribute('style', $style);
        }

        if ($tag === 'a') {
            $href = $this->safeHref($element->getAttribute('href'));
            $href === null ? $element->removeAttribute('href') : $element->setAttribute('href', $href);

            if ($element->getAttribute('target') === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');
            } elseif ($element->getAttribute('target') !== '_self') {
                $element->removeAttribute('target');
                $element->removeAttribute('rel');
            }
        }

        if ($tag === 'img') {
            $src = $this->safeImageSource($element->getAttribute('src'));
            $src === null ? $element->removeAttribute('src') : $element->setAttribute('src', $src);

            foreach (['width', 'height'] as $dimension) {
                $value = $element->getAttribute($dimension);

                if ($element->hasAttribute($dimension) && (preg_match('/^[1-9][0-9]{0,3}$/', $value) !== 1 || (int) $value > 8000)) {
                    $element->removeAttribute($dimension);
                }
            }
        }

        foreach (['colspan', 'rowspan'] as $attribute) {
            if ($element->hasAttribute($attribute) && preg_match('/^[1-9][0-9]?$/', $element->getAttribute($attribute)) !== 1) {
                $element->removeAttribute($attribute);
            }
        }

        if ($element->hasAttribute('scope') && ! in_array($element->getAttribute('scope'), ['row', 'col'], true)) {
            $element->removeAttribute('scope');
        }
    }

    private function sanitizeStyle(string $style): string
    {
        return collect(explode(';', $style))
            ->map(function (string $declaration): ?string {
                [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
                $property = strtolower(trim($property));
                $value = trim($value);

                if (! in_array($property, self::STYLE_PROPERTIES, true) || ! $this->isSafeStyleValue($property, $value)) {
                    return null;
                }

                return $property.': '.$value;
            })
            ->filter()
            ->implode('; ');
    }

    private function isSafeStyleValue(string $property, string $value): bool
    {
        if ($value === '' || strlen($value) > 120 || preg_match('/(?:url|expression|javascript|@import|var\s*\()/i', $value) === 1) {
            return false;
        }

        return match ($property) {
            'color', 'background-color' => preg_match('/^(?:#[0-9a-f]{3,8}|rgba?\([0-9.,%\s]+\)|[a-z]{1,30})$/i', $value) === 1,
            'text-align' => in_array(strtolower($value), ['left', 'center', 'right', 'justify'], true),
            'font-size', 'padding-left' => preg_match('/^(?:0|[0-9]{1,3}(?:\.[0-9]{1,2})?(?:px|pt|em|rem|%))$/i', $value) === 1,
            'font-family' => preg_match('/^[a-z0-9 ,\-"\']+$/i', $value) === 1,
            'font-weight' => preg_match('/^(?:normal|bold|[1-9]00)$/i', $value) === 1,
            'font-style' => preg_match('/^(?:normal|italic|oblique)$/i', $value) === 1,
            'text-decoration' => preg_match('/^(?:none|underline|line-through)(?:\s+(?:underline|line-through))*$/i', $value) === 1,
            default => false,
        };
    }

    private function safeHref(string $href): ?string
    {
        $href = trim($href);

        if ($href === '' || strlen($href) > 2048 || preg_match('/[\x00-\x20\x7F\\\\]/', $href) === 1 || str_starts_with($href, '//')) {
            return null;
        }

        if (str_starts_with($href, '/') || str_starts_with($href, '#') || preg_match('/^(?:https?:\/\/|mailto:|tel:)/i', $href) === 1) {
            return $href;
        }

        return null;
    }

    private function safeImageSource(string $src): ?string
    {
        $src = trim($src);

        return SafeImageSource::isRootRelative($src) ? $src : null;
    }
}
