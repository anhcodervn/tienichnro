<?php

test('checkout exposes equal payment method buttons backed by the canonical select', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $topupForm = file_get_contents($projectRoot.'/resources/views/client/components/topup-form.blade.php');
    $script = file_get_contents($projectRoot.'/resources/js/client.js');
    $styles = file_get_contents($projectRoot.'/resources/css/client.css');

    expect($topupForm)
        ->toContain('name="payment_method" data-payment-method')
        ->toContain('aria-hidden="true" tabindex="-1" hidden')
        ->toContain('class="grid grid-cols-2 gap-2" role="radiogroup"')
        ->toContain('data-payment-option="wallet"')
        ->toContain('data-payment-option="bank_transfer"')
        ->toContain('Số dư tài khoản')
        ->toContain('QR thanh toán')
        ->and(substr_count($topupForm, 'class="home-payment-option"'))
        ->toBe(2)
        ->and($script)
        ->toContain("form.querySelectorAll('[data-payment-option]')")
        ->toContain('const syncPaymentButtons = () =>')
        ->toContain("paymentMethod.dispatchEvent(new Event('change', { bubbles: true }))")
        ->toContain("['ArrowLeft', 'ArrowRight', 'Home', 'End']")
        ->and($styles)
        ->toContain('.home-payment-option')
        ->toContain(".home-payment-option[aria-checked='true']");
});
