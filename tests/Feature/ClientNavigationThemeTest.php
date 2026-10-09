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

test('shared client header displays the study notice without repeating it for screen readers', function (bool $authenticated): void {
    if ($authenticated) {
        $this->actingAs(User::factory()->create());
    }
    $response = $this->get('/')->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $marquee = $xpath->query('//header/div[@data-client-header-marquee]');
    expect($marquee->length)->toBe(1);
    expect($marquee->item(0)->getAttribute('tabindex'))->toBe('0');
    $notice = 'Website phục vụ mục đích học tập và nghiên cứu.';
    expect(trim($xpath->query('./p', $marquee->item(0))->item(0)->textContent))->toBe($notice);
    $copies = $xpath->query('./div[@aria-hidden="true"]/span', $marquee->item(0));
    expect($copies->length)->toBe(2);
    foreach ($copies as $copy) {
        expect(trim($copy->textContent))->toBe($notice);
    }
    expect($xpath->query('//marquee')->length)->toBe(0);
})->with([false, true]);
