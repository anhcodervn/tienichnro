<?php

test('game recipient inputs are lowercased while users type', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $script = file_get_contents($projectRoot.'/resources/js/client.js');

    expect($script)
        ->toContain("const recipientInputs = Array.from(form.querySelectorAll('[data-recipient-input]'))")
        ->toContain('const normalizedValue = input.value.toLowerCase()')
        ->toContain('if (event.isComposing) return')
        ->toContain("input.addEventListener('compositionend', () => {")
        ->toContain('input.setSelectionRange(selectionStart, selectionEnd)')
        ->toContain('recipientInputs.forEach(lowercaseRecipientInput)')
        ->toContain('lowercaseRecipientInput(bulkRecipients)');
});
