<?php

test('home topup submit requires confirmation with a current order summary', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $form = file_get_contents($projectRoot.'/resources/views/client/components/topup-form.blade.php');
    $home = file_get_contents($projectRoot.'/resources/views/client/home/index.blade.php');
    $clientScript = file_get_contents($projectRoot.'/resources/js/client.js');

    expect($home)
        ->toContain("'showConfirmation' => true")
        ->and($form)
        ->toContain('@if ($showConfirmation)')
        ->toContain('data-topup-confirmation-modal')
        ->toContain('data-confirm-game')
        ->toContain('data-confirm-package')
        ->toContain('data-single-confirm-recipient-label')
        ->toContain('data-bulk-confirm-recipient-label')
        ->toContain('data-confirm-recipient-label')
        ->toContain('data-confirm-recipients')
        ->toContain('data-confirm-total')
        ->toContain('data-topup-confirmation-checkbox')
        ->toContain('data-topup-confirmation-submit disabled')
        ->toContain('Tôi đã kiểm tra kỹ thông tin')
        ->and($clientScript)
        ->toContain('if (confirmationModal && !confirmationGranted)')
        ->toContain('event.preventDefault();')
        ->toContain('populateConfirmationModal();')
        ->toContain("purchaseMode?.value === 'bulk'")
        ->toContain('confirmationSubmit.disabled = !confirmationCheckbox.checked')
        ->toContain('confirmationGranted = true;')
        ->toContain('form.requestSubmit(submitButton)')
        ->toContain('confirmationRecipients.replaceChildren()')
        ->toContain('confirmationRecipientLabel.textContent =')
        ->toContain("recipientLabel.replace(/:\\s*$/, '')")
        ->toContain("activeRecipientInputs.map((input) => input.value.trim()).join(' | ')")
        ->toContain('dataset.bulkConfirmRecipientLabel')
        ->toContain('dataset.singleConfirmRecipientLabel')
        ->toContain('item.textContent =')
        ->not->toContain('confirmationRecipients.innerHTML');
});
