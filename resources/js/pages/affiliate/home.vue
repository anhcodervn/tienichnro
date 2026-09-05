<script setup lang="ts">
import { clientAffiliateService, type ClientAffiliateHomeData } from '@/services/client-affiliate.service';
import { handleErrorResponse } from '@/utils/response';
import { sanitizeRichText } from '@/utils/rich-text';
import { ArrowRight, BellRing, HandCoins, LoaderCircle, Pin } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const data = ref<ClientAffiliateHomeData | null>(null);
const loading = ref(true);
const dateTime = (value: string | null): string => (value ? new Date(value).toLocaleString('vi-VN') : '—');

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        data.value = await clientAffiliateService.home();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

onMounted(load);
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-600 via-emerald-700 to-slate-900 p-6 text-white shadow-lg sm:p-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.16em]"
                        ><HandCoins class="size-4" /> Partner Center</span
                    >
                    <h1 class="mt-4 text-3xl font-black sm:text-4xl">Chào mừng bạn trở lại</h1>
                    <p class="mt-3 leading-7 text-emerald-50">
                        Theo dõi thông báo, chính sách và các thông tin quan trọng do quản trị viên gửi tới cộng tác viên.
                    </p>
                </div>
                <RouterLink
                    to="/cong-tac-vien/tong-quan"
                    class="inline-flex min-h-12 w-fit items-center justify-center gap-2 rounded-xl bg-white px-5 font-bold text-emerald-800 shadow-sm transition hover:bg-emerald-50"
                >
                    Xem tổng quan hoa hồng <ArrowRight class="size-5" />
                </RouterLink>
            </div>
        </section>

        <div v-if="loading" class="grid min-h-72 place-items-center rounded-2xl border border-slate-200 bg-white">
            <LoaderCircle class="size-9 animate-spin text-emerald-600" />
        </div>

        <section v-else class="grid gap-4" aria-labelledby="affiliate-announcements-title">
            <header class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-xl bg-emerald-100 text-emerald-700"><BellRing class="size-5" /></span>
                <div>
                    <h2 id="affiliate-announcements-title" class="text-xl font-black text-slate-950">Thông báo từ quản trị viên</h2>
                    <p class="text-sm text-slate-500">Thông báo được ghim luôn hiển thị ở đầu danh sách.</p>
                </div>
            </header>

            <article
                v-for="announcement in data?.announcements"
                :key="announcement.id"
                class="grid gap-3 rounded-2xl border bg-white p-5 shadow-sm sm:p-6"
                :class="announcement.is_pinned ? 'border-amber-300 ring-1 ring-amber-100' : 'border-slate-200'"
            >
                <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <span
                            v-if="announcement.is_pinned"
                            class="mb-2 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800"
                            ><Pin class="size-3.5" /> Thông báo ghim</span
                        >
                        <h3 class="break-words text-lg font-black text-slate-950">{{ announcement.title }}</h3>
                    </div>
                    <time class="shrink-0 text-xs font-semibold text-slate-500">{{ dateTime(announcement.published_at) }}</time>
                </header>
                <div class="article-content min-w-0 break-words" v-html="sanitizeRichText(announcement.content_html)"></div>
            </article>

            <div
                v-if="!data?.announcements.length"
                class="grid min-h-64 place-items-center rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center"
            >
                <div>
                    <BellRing class="mx-auto size-10 text-slate-400" />
                    <p class="mt-3 font-bold text-slate-700">Chưa có thông báo mới</p>
                    <p class="mt-1 text-sm text-slate-500">Các thông tin từ quản trị viên sẽ xuất hiện tại đây.</p>
                </div>
            </div>
        </section>
    </main>
</template>
