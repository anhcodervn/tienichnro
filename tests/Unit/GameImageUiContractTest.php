<?php

test('game catalog exposes the dedicated 512 webp image uploader', function (): void {
    $catalog = file_get_contents(resource_path('js/pages/admin/topup/catalog/index.vue'));
    $uploader = file_get_contents(resource_path('js/components/shared/UpladImage/index.vue'));

    expect($catalog)
        ->toContain("import UploadImage from '@/components/shared/UpladImage/index.vue';")
        ->toContain('upload-url="/api/admin-api/games/image"')
        ->toContain('Trình duyệt tự crop giữa và chuyển WebP đúng 512×512 trước khi tải lên.')
        ->toContain(':square-size="512"')
        ->toContain('@uploaded="form.image = $event"')
        ->toContain('v-if="row.image"')
        ->not->toContain('SEO trang nạp game')
        ->not->toContain('v-model.trim="form.seo_title"')
        ->not->toContain('v-model="form.seo_description"')
        ->not->toContain('v-model="form.content"');

    expect($uploader)
        ->toContain('uploadUrl?: string;')
        ->toContain('squareSize?: number;')
        ->toContain('const convertToSquareWebp = (selectedFile: File, size: number): Promise<File> =>')
        ->toContain('context.drawImage(image, sourceX, sourceY, cropSize, cropSize, 0, 0, size, size)')
        ->toContain("blob.type !== 'image/webp'")
        ->toContain('uploadFile = await convertToSquareWebp(file.value, props.squareSize);')
        ->toContain("props.uploadUrl ?? '/api/uploads/image'");
});
