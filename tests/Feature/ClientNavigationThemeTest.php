<?php

use App\Models\User;

test('client navigation removes notifications and places theme before account controls', function (bool $authenticated): void {
    if ($authenticated) {
        $this->actingAs(User::factory()->create());
    }
    $response = $this->get('/')->assertOk()->assertSee('data-client-theme-init', false);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    foreach (['Điều hướng chính', 'Điều hướng di động', 'Điều hướng nhanh trên di động'] as $label) {
        foreach ($xpath->query('//nav[@aria-label="'.$label.'"]//a') as $link) {
            expect($link->getAttribute('href'))->not->toBe(route('nro.notifies.page'));
        }
    }
    expect($xpath->query('//nav[@data-mobile-bottom-nav]//a')->length)->toBe(4);
    $toggle = $xpath->query('//header//button[@data-client-theme-toggle]');
    expect($toggle->length)->toBe(1);
    expect($xpath->query('following-sibling::*[1]', $toggle->item(0))->item(0)->nodeName)->toBe($authenticated ? 'details' : 'a');
})->with([false, true]);
