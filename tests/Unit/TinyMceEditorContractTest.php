<?php

test('tinymce ignores the controlled prop echo from its own input event', function (): void {
    $editorPath = dirname(__DIR__, 2).'/resources/js/components/shared/Editor/index.vue';
    $source = file_get_contents($editorPath);

    expect($source)
        ->toContain('let lastEmittedFingerprint: string | null = null;')
        ->toContain('lastEmittedFingerprint = valueFingerprint(value);')
        ->toContain('if (fingerprint === lastEmittedFingerprint)')
        ->toContain('lastEmittedFingerprint = null;')
        ->and(strpos($source, 'lastEmittedFingerprint = valueFingerprint(value);'))
        ->toBeLessThan(strpos($source, "emit('update:value', value);"));
});
