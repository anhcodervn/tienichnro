<?php

test('recipient regex is configured in admin and validated in both checkout modes', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $adminCatalog = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/catalog/index.vue');
    $form = file_get_contents($projectRoot.'/resources/views/client/components/topup-form.blade.php');
    $script = file_get_contents($projectRoot.'/resources/js/client.js');

    expect($adminCatalog)
        ->toContain('regex: string')
        ->toContain('v-model.trim="field.regex"')
        ->toContain('Regex kiểm tra định dạng')
        ->toContain('regex: field.regex?.trim() || null')
        ->and($form)
        ->toContain('data-validation-regex="{{ $field[\'regex\'] }}"')
        ->toContain('data-recipient-format-error')
        ->toContain('data-bulk-fields=')
        ->and($script)
        ->toContain("new RegExp(source, 'u')")
        ->toContain('validateRecipientInput(input)')
        ->toContain('Dòng ${index + 1}, cột ${fieldIndex + 1} (${field.label}): không đúng định dạng.')
        ->toContain('bulkRecipients?.setCustomValidity(formatMessage)');
});
