<?php

use App\Features\Topup\Services\TopupProviderHttpClientFactory;
use Tests\TestCase;

uses(TestCase::class);

test('provider http client applies a normalized proxy to every request it creates', function (): void {
    $request = (new TopupProviderHttpClientFactory)->make(
        connectTimeout: 5,
        timeout: 20,
        proxyUrl: '  http://proxy-user:proxy-pass@proxy.example:8080  ',
    );

    expect($request->getOptions())->toMatchArray([
        'connect_timeout' => 5,
        'timeout' => 20,
        'proxy' => 'http://proxy-user:proxy-pass@proxy.example:8080',
    ]);
});

test('provider http client omits the proxy option when no proxy is configured', function (): void {
    $request = (new TopupProviderHttpClientFactory)->make(5, 20, '');

    expect($request->getOptions())->not->toHaveKey('proxy');
});

test('provider proxy validation accepts supported urls', function (string $proxy): void {
    expect(TopupProviderHttpClientFactory::isValidProxyUrl($proxy))->toBeTrue();
})->with([
    'authenticated http' => 'http://user:pass@proxy.example:8080',
    'https' => 'https://proxy.example:8443',
    'socks5' => 'socks5://127.0.0.1:1080',
    'socks5 hostname resolution' => 'socks5h://proxy.example:1080',
]);

test('provider proxy validation rejects unsafe or malformed urls', function (string $proxy): void {
    expect(TopupProviderHttpClientFactory::isValidProxyUrl($proxy))->toBeFalse();
})->with([
    'unsupported scheme' => 'file://proxy.example:8080',
    'missing host' => 'http://:8080',
    'missing port' => 'http://proxy.example',
    'path' => 'http://proxy.example:8080/admin',
    'query' => 'http://proxy.example:8080/?token=secret',
    'line break' => "http://proxy.example:8080\nX-Test: value",
]);
