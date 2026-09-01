<?php

use App\Support\EditorContentRenderer;

test('editor content renderer keeps safe links and nested text formatting', function (): void {
    $html = app(EditorContentRenderer::class)->renderNodes([
        [
            'type' => 'paragraph',
            'children' => [
                [
                    'text' => 'Mở ưu đãi',
                    'bold' => true,
                    'color' => '#0f766e',
                    'href' => 'https://example.com/deal?a=1&b=2',
                    'target' => '_blank',
                ],
            ],
        ],
    ])->toHtml();

    expect($html)->toBe(
        '<p><a href="https://example.com/deal?a=1&amp;b=2" target="_blank" rel="noopener noreferrer"><span style="color:#0f766e"><strong>Mở ưu đãi</strong></span></a></p>'
    );
});

test('editor content renderer never renders an unsafe href', function (string $href): void {
    $html = app(EditorContentRenderer::class)->renderNodes([
        [
            'type' => 'paragraph',
            'children' => [['text' => 'Không an toàn', 'href' => $href, 'target' => '_blank']],
        ],
    ])->toHtml();

    expect($html)
        ->toBe('<p>Không an toàn</p>')
        ->not->toContain('<a ');
})->with([
    'javascript scheme' => 'javascript:alert(1)',
    'data scheme' => 'data:text/html,<script>alert(1)</script>',
    'vbscript scheme' => 'vbscript:msgbox(1)',
    'protocol relative url' => '//example.com/phishing',
    'backslash protocol relative url' => '/\\example.com/phishing',
    'whitespace obfuscation' => "java\nscript:alert(1)",
]);

test('editor content renderer normalizes a bare domain to an https link', function (): void {
    $html = app(EditorContentRenderer::class)->renderNodes([
        [
            'type' => 'paragraph',
            'children' => [[
                'text' => 'Tham gia cộng đồng',
                'href' => 'facebook.com/napcarot',
                'target' => '_blank',
            ]],
        ],
    ])->toHtml();

    expect($html)->toBe(
        '<p><a href="https://facebook.com/napcarot" target="_blank" rel="noopener noreferrer">Tham gia cộng đồng</a></p>'
    );
});
