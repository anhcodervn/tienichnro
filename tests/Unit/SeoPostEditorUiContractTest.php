<?php

test('seo post editor exposes direct cover upload and clear canonical controls', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $source = file_get_contents($projectRoot.'/resources/js/pages/admin/seo/posts/create/index.vue');
    $types = file_get_contents($projectRoot.'/resources/js/types/admin-seo.type.ts');

    expect($source)
        ->toContain("import UploadImage from '@/components/shared/UpladImage/index.vue';")
        ->toContain('v-model="form.cover_alt"')
        ->toContain(':image-src="form.cover_image"')
        ->toContain('@uploaded="form.cover_image = $event"')
        ->toContain("const canonicalMode = ref<'auto' | 'custom'>('auto')")
        ->toContain('const generatedCanonicalUrl = computed')
        ->toContain('Canonical tự động')
        ->toContain('Canonical tùy chỉnh')
        ->toContain('Xem trước kết quả Google')
        ->toContain('v-model="form.type"')
        ->toContain('v-model="form.service_id"')
        ->toContain('v-model="form.focus_keyword"')
        ->toContain('v-model="form.meta_keywords"')
        ->toContain('meta_keywords: form.meta_keywords?.trim()')
        ->toContain('Không nhập giá vào nội dung')
        ->toContain('Preview URL:')
        ->toContain('v-model="item.question"')
        ->toContain('v-model="item.answer"')
        ->toContain('ref="contentEditor"')
        ->toContain('const latestContent = contentEditor.value?.flush();')
        ->toContain('await nextTick();')
        ->and($types)
        ->toContain('cover_image: string | null')
        ->toContain('cover_image?: string | null')
        ->toContain("export type SeoPageType = 'knowledge' | 'guide' | 'price'")
        ->toContain('service_id?: number | null')
        ->toContain('meta_keywords: string | null')
        ->toContain('meta_keywords?: string')
        ->toContain('faq?: SeoFaqItem[]');
});
