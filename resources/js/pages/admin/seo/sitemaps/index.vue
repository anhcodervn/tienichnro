<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import { adminSeoService } from '@/services/admin-seo.service';
import type { AdminSeoSitemapEntry } from '@/types/admin-seo.type';
import { handleErrorResponse } from '@/utils/response';
import { CheckCircle2, Globe, Send } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { seoReferences } from '../data';

const loading = ref(false);
const entries = ref<AdminSeoSitemapEntry[]>([]);

const fetchSitemaps = async (): Promise<void> => {
    try {
        loading.value = true;
        entries.value = await adminSeoService.sitemaps();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await fetchSitemaps();
});
</script>

<template>
    <div class="space-y-4">
        <Breadcrumb
            title="Sitemap & indexation"
            description="Theo dõi các tệp sitemap, cấu trúc URL được index và checklist submit Search Console."
        />

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_380px]">
            <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Danh sách sitemap</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Theo Google Search Central, sitemap nên chỉ chứa URL canonical quan trọng và được cập nhật khi nội dung đổi lớn.
                </p>

                <div class="mt-5 grid gap-3">
                    <div v-if="loading" class="rounded-[14px] border border-slate-200 bg-slate-50/70 p-4 text-sm text-slate-500">
                        Đang tải sitemap...
                    </div>
                    <div v-for="entry in entries" v-else :key="entry.path" class="rounded-[14px] border border-slate-200 bg-slate-50/70 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-slate-900">{{ entry.title }}</p>
                                <p class="mt-1 font-mono text-sm text-violet-600">{{ entry.path }}</p>
                                <p class="mt-3 text-sm leading-6 text-slate-600">{{ entry.description }}</p>
                            </div>
                            <span class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">
                                {{ entry.included_count }}
                            </span>
                        </div>
                    </div>
                </div>
            </article>

            <aside class="space-y-4">
                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Checklist submit</h2>
                    <div class="mt-5 space-y-3">
                        <div class="flex gap-3 rounded-[12px] border border-slate-200 bg-slate-50/80 px-4 py-3">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                            <p class="text-sm leading-6 text-slate-600">Sitemap chỉ chứa URL public, index/follow và trùng với canonical.</p>
                        </div>
                        <div class="flex gap-3 rounded-[12px] border border-slate-200 bg-slate-50/80 px-4 py-3">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                            <p class="text-sm leading-6 text-slate-600">URL noindex, redirect hoặc trùng lặp không nên xuất hiện trong sitemap.</p>
                        </div>
                        <div class="flex gap-3 rounded-[12px] border border-slate-200 bg-slate-50/80 px-4 py-3">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                            <p class="text-sm leading-6 text-slate-600">
                                Sau khi publish thêm nhiều bài, nên submit lại sitemap trong Search Console.
                            </p>
                        </div>
                    </div>
                </article>

                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Nguồn tham chiếu</h2>
                    <div class="mt-5 space-y-3">
                        <a
                            v-for="reference in seoReferences.slice(0, 2)"
                            :key="reference.url"
                            :href="reference.url"
                            target="_blank"
                            rel="noreferrer"
                            class="block rounded-[12px] border border-slate-200 bg-slate-50/80 p-4 transition hover:border-violet-200 hover:bg-violet-50/60"
                        >
                            <div class="flex items-start gap-3">
                                <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-violet-50 text-violet-600">
                                    <Globe class="h-4 w-4" />
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-900">{{ reference.label }}</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ reference.summary }}</p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <a
                        href="https://search.google.com/search-console"
                        target="_blank"
                        rel="noreferrer"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-[10px] bg-violet-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-violet-500"
                    >
                        <Send class="h-4 w-4" />
                        Mở Search Console
                    </a>
                </article>
            </aside>
        </section>
    </div>
</template>
