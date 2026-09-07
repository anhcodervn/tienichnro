<?php

namespace App\Support;

use DOMDocument;
use DOMElement;

class CustomHeadTags
{
    public function sanitize(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorMode = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><!DOCTYPE html><html><head>'.$value.'</head><body></body></html>',
            LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorMode);

        if ($loaded === false) {
            return '';
        }

        $head = $document->getElementsByTagName('head')->item(0);
        $body = $document->getElementsByTagName('body')->item(0);

        if (! $head instanceof DOMElement || ! $body instanceof DOMElement || $this->hasMeaningfulChildren($body)) {
            return '';
        }

        $tags = [];

        foreach ($head->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) === '') {
                continue;
            }

            if (! $node instanceof DOMElement || strtolower($node->tagName) !== 'meta') {
                return '';
            }

            $tag = $this->sanitizeMetaTag($node);

            if ($tag === null) {
                return '';
            }

            $tags[] = $tag;
        }

        return $tags !== [] && count($tags) <= 20 ? implode("\n", $tags) : '';
    }

    private function hasMeaningfulChildren(DOMElement $element): bool
    {
        foreach ($element->childNodes as $node) {
            if ($node->nodeType !== XML_TEXT_NODE || trim($node->textContent) !== '') {
                return true;
            }
        }

        return false;
    }

    private function sanitizeMetaTag(DOMElement $element): ?string
    {
        $attributes = [];

        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->name);

            if (! in_array($name, ['name', 'property', 'content'], true)) {
                return null;
            }

            $attributes[$name] = $attribute->value;
        }

        $identifierAttributes = array_values(array_intersect(['name', 'property'], array_keys($attributes)));

        if (count($identifierAttributes) !== 1 || ! array_key_exists('content', $attributes)) {
            return null;
        }

        $identifierName = $identifierAttributes[0];
        $identifierValue = trim($attributes[$identifierName]);

        if ($identifierValue === '' || preg_match('/[\x00-\x1F\x7F]/u', $identifierValue.$attributes['content']) === 1) {
            return null;
        }

        return sprintf(
            '<meta %s="%s" content="%s">',
            $identifierName,
            $this->escapeAttribute($identifierValue),
            $this->escapeAttribute($attributes['content']),
        );
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
