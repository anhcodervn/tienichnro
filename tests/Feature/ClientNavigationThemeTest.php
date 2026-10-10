<?php

use App\Models\ServiceOffering;
use App\Models\User;
use App\Models\Wallet;

beforeEach(function (): void {
    config(['license.services_visible' => true]);
});

test('header shows only the authenticated users current wallet balance beneath the account profile', function (): void {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1250000]);
    Wallet::factory()->create(['balance' => 9999999]);
    $response = $this->actingAs($user)->get('/')->assertOk()->assertDontSee('9.999.999 đ');
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    foreach (['desktop', 'mobile'] as $location) {
        $balance = $xpath->query('//header//summary//*[@data-header-wallet-balance="'.$location.'"]');
        expect($balance->length)->toBe(1)->and(trim($balance->item(0)->textContent))->toBe('1.250.000 đ');
    }
    $menuBalance = $xpath->query('//header//a[@data-header-wallet-balance="menu"]')->item(0);
    expect($menuBalance->getAttribute('href'))->toBe(route('account.wallet'));
    $wallet->update(['balance' => 1200000]);
    $this->get('/')->assertOk()->assertSee('1.200.000 đ')->assertDontSee('1.250.000 đ');
});

test('header shows zero for accounts without wallets without creating one and hides balance for guests', function (): void {
    $this->get('/')->assertOk()->assertDontSee('data-header-wallet-balance', false);
    $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertSee('Số dư ví: 0 đ');
    $this->assertDatabaseCount('wallets', 0);
});

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
    expect($xpath->query('//nav[@data-mobile-bottom-nav]//a')->length)->toBe(5);
    foreach (['Điều hướng chính', 'Điều hướng di động', 'Điều hướng nhanh trên di động'] as $label) {
        $serviceLinks = $xpath->query('//nav[@aria-label="'.$label.'"]//a[@href="'.route('services.index').'"]');
        expect($serviceLinks->length)->toBe(1);
        expect(trim($serviceLinks->item(0)->textContent))->toBe('Dịch vụ');
        expect($serviceLinks->item(0)->hasAttribute('data-client-services-open'))->toBeTrue()
            ->and($serviceLinks->item(0)->getAttribute('aria-controls'))->toBe('client-services-modal')
            ->and($serviceLinks->item(0)->getAttribute('aria-haspopup'))->toBe('dialog')
            ->and($xpath->query('./i[contains(@class, "bx-store")]', $serviceLinks->item(0))->length)->toBe(1);
    }
    $toggle = $xpath->query('//header//button[@data-client-theme-toggle]');
    expect($toggle->length)->toBe(1);
    expect($xpath->query('following-sibling::*[1]', $toggle->item(0))->item(0)->nodeName)->toBe($authenticated ? 'details' : 'a');
})->with([false, true]);

test('services modal uses configured service icons titles and page links', function (string $path): void {
    ServiceOffering::factory()->create(['code' => 'custom_service', 'name' => 'Dịch vụ từ SQL', 'icon' => 'bx-star', 'url' => route('seo.index')]);
    $response = $this->get($path)->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $modal = $xpath->query('//dialog[@id="client-services-modal"]')->item(0);
    expect($modal)->not->toBeNull();
    expect($modal->hasAttribute('open'))->toBeFalse()
        ->and($modal->getAttribute('aria-labelledby'))->toBe('client-services-title')
        ->and($xpath->query('.//h2[@id="client-services-title"]', $modal)->item(0)->textContent)->toBe('Dịch vụ')
        ->and($xpath->query('.//button[@data-client-services-close]', $modal)->length)->toBe(1);
    $links = $xpath->query('.//*[@data-client-services-list]//a', $modal);
    expect($links->length)->toBe(1)
        ->and($links->item(0)->getAttribute('href'))->toBe(route('seo.index'))
        ->and($xpath->query('.//strong', $links->item(0))->item(0)->textContent)->toBe('Dịch vụ từ SQL')
        ->and($xpath->query('.//i[contains(@class, "bx-star")]', $links->item(0))->length)->toBe(1);
    expect($modal->textContent)->not->toContain('Thông báo game');
    $toolsModal = $xpath->query('//dialog[@id="client-tools-modal"]')->item(0);
    expect($toolsModal->textContent)->toContain('Thông báo game')->not->toContain('Dịch vụ từ SQL');
    expect($xpath->query('//*[@data-client-services-open]')->length)->toBe(3)
        ->and($xpath->query('//dialog[@id="client-tools-modal"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-client-tools-open]')->length)->toBe(3);
})->with(['/', '/tin-tuc', '/dich-vu']);

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
