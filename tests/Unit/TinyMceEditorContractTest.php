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

test('admin shell loads the complete local tinymce distribution', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $layout = file_get_contents($projectRoot.'/resources/views/app.blade.php');

    expect($layout)
        ->toContain("asset('assets/libs/tinymce/tinymce.min.js')")
        ->not->toContain("asset('assets/libs/tinymce2/tinymce.min.js')");
});

test('tinymce matches the complete local configuration used by the reference project', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $editor = file_get_contents($projectRoot.'/resources/js/components/shared/Editor/index.vue');

    expect($editor)
        ->toContain("language_url: '/assets/libs/tinymce/langs/vi.js'")
        ->toContain("'advlist autolink lists link image charmap print preview anchor'")
        ->toContain("'searchreplace visualblocks code fullscreen'")
        ->toContain("'insertdatetime media table paste code help wordcount'")
        ->toContain("'emoticons hr pagebreak nonbreaking toc'")
        ->toContain("'save autosave directionality textcolor'")
        ->toContain('link image emoticons | table | code fullscreen preview | removeformat');

    expect($projectRoot.'/public/assets/libs/tinymce/tinymce.min.js')->toBeFile()
        ->and($projectRoot.'/public/assets/libs/tinymce/langs/vi.js')->toBeFile()
        ->and($projectRoot.'/public/assets/libs/tinymce/plugins/table/plugin.min.js')->toBeFile()
        ->and($projectRoot.'/public/assets/libs/tinymce/themes/modern/theme.min.js')->toBeFile()
        ->and($projectRoot.'/public/assets/libs/tinymce/skins/lightgray/skin.min.css')->toBeFile();
});

test('tinymce captures all text input and preserves whitespace and html characters', function (): void {
    $editorPath = dirname(__DIR__, 2).'/resources/js/components/shared/Editor/index.vue';
    $source = file_get_contents($editorPath);

    expect($source)
        ->toContain("editor.on('input change keyup undo redo'")
        ->toContain('if (!node.textContent)')
        ->toContain("escapeHtml(item.text ?? '').replace(/\\n/g, '<br>')")
        ->toContain("value.replace(/[&<>\"']/g")
        ->toContain('blocks.length === blockCountBeforeChildren');
});

test('tinymce preserves safe links through its json conversion', function (): void {
    $editorPath = dirname(__DIR__, 2).'/resources/js/components/shared/Editor/index.vue';
    $source = file_get_contents($editorPath);

    expect($source)
        ->toContain("if (tag === 'a')")
        ->toContain('next.href = href')
        ->toContain("target?: '_blank' | '_self'")
        ->toContain('const href = normalizeSafeHref(item.href)')
        ->toContain('rel="noopener noreferrer"')
        ->toContain("href.startsWith('//')")
        ->toContain('/^(?:https?:\\/\\/|mailto:|tel:)/i');
});

test('tinymce uploads pasted and dropped images as optimized webp files', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $editor = file_get_contents($projectRoot.'/resources/js/components/shared/Editor/index.vue');
    $uploader = file_get_contents($projectRoot.'/resources/js/utils/editor-image-upload.ts');

    expect($editor)
        ->toContain("import { uploadEditorImageFile } from '@/utils/editor-image-upload';")
        ->toContain('paste_data_images: true')
        ->toContain('automatic_uploads: true')
        ->toContain("images_file_types: 'jpg,jpeg,png,webp'")
        ->toContain('images_upload_handler: handleImageUpload')
        ->toContain('uploadEditorImageFile(blobInfo.blob(), blobInfo.filename(), progress)')
        ->toContain('hasPendingLocalImages(html)')
        ->toContain('(?:data:image\\/|blob:)');

    expect($uploader)
        ->toContain('export const uploadEditorImageFile = async')
        ->toContain('convertBlobToWebp(sourceBlob)')
        ->toContain("'image/webp'")
        ->toContain('0.82')
        ->toContain('const maxDimension = 1800')
        ->toContain('formData.append')
        ->toContain('onUploadProgress');
});
