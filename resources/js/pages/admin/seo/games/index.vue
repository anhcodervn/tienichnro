<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import Editor from '@/components/shared/Editor/index.vue';
import UploadImage from '@/components/shared/UpladImage/index.vue';
import { adminSeoService } from '@/services/admin-seo.service';
import type { AdminGameSeoSettings, SeoFaqItem, SeoRobotsValue } from '@/types/admin-seo.type';
import { uploadEditorImages } from '@/utils/editor-image-upload';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { CheckCircle2, CircleAlert, ExternalLink, Gamepad2, Plus, Save, Search, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, onMounted, reactive, ref } from 'vue';

type GameSeoForm = {
    meta_title: string;
    meta_description: string;
    meta_keywords: string;
    h1: string;
    article_title: string;
    content: unknown[];
    og_image: string;
    og_image_alt: string;
    canonical_url: string;
    robots: SeoRobotsValue;
    faqs: SeoFaqItem[];
    is_published: boolean;
    breadcrumb_schema: boolean;
    webpage_schema: boolean;
};

const games = ref<AdminGameSeoSettings[]>([]);
const selectedId = ref<number | null>(null);
const search = ref('');
const loading = ref(true);
const saving = ref(false);
const contentEditor = ref<{ flush: () => unknown[] | string } | null>(null);
const form = reactive<GameSeoForm>({
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
    h1: '',
    article_title: '',
    content: [],
    og_image: '',
    og_image_alt: '',
    canonical_url: '',
    robots: 'index,follow',
    faqs: [],
    is_published: false,
    breadcrumb_schema: true,
    webpage_schema: true,
});

const filteredGames = computed(() => {
    const keyword = search.value.trim().toLocaleLowerCase('vi');
    return keyword ? games.value.filter((game) => `${game.name} ${game.slug}`.toLocaleLowerCase('vi').includes(keyword)) : games.value;
});
const selectedGame = computed(() => games.value.find((game) => game.id === selectedId.value) ?? null);
const effectiveTitle = computed(() => form.meta_title.trim() || (selectedGame.value ? `Nạp game ${selectedGame.value.name} nhanh chóng` : ''));
const effectiveDescription = computed(() => form.meta_description.trim() || `Nạp game ${selectedGame.value?.name ?? ''} nhanh chóng, rõ giá.`);
const effectiveCanonical = computed(() => form.canonical_url.trim() || selectedGame.value?.public_url || '');
const effectiveOgImage = computed(() => form.og_image.trim() || selectedGame.value?.fallback_og_image || '');
const seoChecks = computed(() => [
    { label: 'Meta title dài 30–65 ký tự', passed: effectiveTitle.value.length >= 30 && effectiveTitle.value.length <= 65 },
    { label: 'Meta description dài 120–160 ký tự', passed: effectiveDescription.value.length >= 120 && effectiveDescription.value.length <= 160 },
    { label: 'Có keyword chính', passed: form.meta_keywords.trim().length > 0 },
    { label: 'Có đúng một H1 riêng', passed: form.h1.trim().length > 0 },
    { label: 'Có nội dung bài SEO', passed: form.content.length > 0 },
    { label: 'Có ảnh Open Graph', passed: effectiveOgImage.value.length > 0 },
]);

const selectGame = (game: AdminGameSeoSettings): void => {
    selectedId.value = game.id;
    Object.assign(form, {
        meta_title: game.meta_title ?? '',
        meta_description: game.meta_description ?? '',
        meta_keywords: game.meta_keywords ?? '',
        h1: game.h1,
        article_title: game.article_title,
        content: Array.isArray(game.content) ? game.content : [],
        og_image: game.og_image ?? '',
        og_image_alt: game.og_image_alt ?? '',
        canonical_url: game.canonical_url ?? '',
        robots: game.robots,
        faqs: (game.faqs ?? []).map((faq) => ({ ...faq })),
        is_published: game.is_published,
        breadcrumb_schema: game.breadcrumb_schema,
        webpage_schema: game.webpage_schema,
    });
};

const loadGames = async (): Promise<void> => {
    loading.value = true;
    try {
        const response = await adminSeoService.gameSeoSettings();
        games.value = response.games;
        if (games.value.length) selectGame(games.value[0]);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const addFaq = (): void => {
    if (form.faqs.length < 20) form.faqs.push({ question: '', answer: '' });
};

const removeFaq = (index: number): void => {
    form.faqs.splice(index, 1);
};

const save = async (): Promise<void> => {
    if (!selectedGame.value) return;

    saving.value = true;
    try {
        const latestContent = contentEditor.value?.flush();
        if (Array.isArray(latestContent)) form.content = latestContent;
        await nextTick();
        form.content = await uploadEditorImages(form.content);

        const response = await adminSeoService.updateGameSeo(selectedGame.value.id, {
            meta_title: form.meta_title.trim() || null,
            meta_description: form.meta_description.trim() || null,
            meta_keywords: form.meta_keywords.trim() || null,
            h1: form.h1.trim() || null,
            article_title: form.article_title.trim() || null,
            content: form.content,
            og_image: form.og_image.trim() || null,
            og_image_alt: form.og_image_alt.trim() || null,
            canonical_url: form.canonical_url.trim() || null,
            robots: form.robots,
            faqs: form.faqs.map((faq) => ({ question: faq.question.trim(), answer: faq.answer.trim() })).filter((faq) => faq.question || faq.answer),
            is_published: form.is_published,
            breadcrumb_schema: form.breadcrumb_schema,
            webpage_schema: form.webpage_schema,
        });
        Object.assign(selectedGame.value, response.data.data);
        selectGame(selectedGame.value);
        handleSuccessResponse(response);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

onMounted(loadGames);
</script>

<template>
    <div class="grid gap-5">
        <Breadcrumb title="SEO từng game" description="Metadata, nội dung TinyMCE, Open Graph, canonical, schema và FAQ cho từng trang nạp game." />

        <div v-if="loading" class="rounded-[14px] border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
            Đang tải danh sách game...
        </div>
        <div v-else-if="!games.length" class="rounded-[14px] border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
            Chưa có game để cấu hình SEO.
        </div>

        <div v-else class="grid items-start gap-5 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <aside class="rounded-[14px] border border-slate-200 bg-white p-4 shadow-sm lg:sticky lg:top-24">
                <label class="relative block"
                    ><Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" /><input
                        v-model="search"
                        class="min-h-11 w-full rounded-[10px] border border-slate-200 pl-9 pr-3 text-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                        placeholder="Tìm tên hoặc slug game"
                /></label>
                <div class="mt-3 grid max-h-[65vh] gap-2 overflow-y-auto">
                    <button
                        v-for="game in filteredGames"
                        :key="game.id"
                        type="button"
                        class="flex items-center gap-3 rounded-[10px] border p-3 text-left transition"
                        :class="selectedId === game.id ? 'border-violet-300 bg-violet-50' : 'border-slate-200 hover:border-violet-200'"
                        @click="selectGame(game)"
                    >
                        <img v-if="game.image" :src="game.image" :alt="game.name" class="h-10 w-10 shrink-0 rounded-[8px] object-cover" /><span
                            v-else
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-[8px] bg-slate-100 text-slate-500"
                            ><Gamepad2 class="h-5 w-5"
                        /></span>
                        <span class="min-w-0 flex-1"
                            ><strong class="block truncate text-sm text-slate-900">{{ game.name }}</strong
                            ><small class="block truncate text-slate-500">{{ game.slug }}</small></span
                        ><span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="game.is_published ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                    </button>
                </div>
            </aside>

            <form v-if="selectedGame" class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]" @submit.prevent="save">
                <section class="grid min-w-0 gap-5">
                    <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-violet-700">
                                    {{ selectedGame.status === 'active' ? 'Game đang hoạt động' : 'Game đang tắt' }}
                                </p>
                                <h2 class="mt-1 text-xl font-bold text-slate-950">{{ selectedGame.name }}</h2>
                                <p class="mt-1 text-sm text-slate-500">/nap-game-{{ selectedGame.slug }}</p>
                            </div>
                            <a
                                :href="selectedGame.public_url"
                                target="_blank"
                                rel="noreferrer"
                                class="inline-flex items-center gap-2 rounded-[8px] border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:border-violet-300 hover:text-violet-700"
                                >Xem trang <ExternalLink class="h-4 w-4"
                            /></a>
                        </div>
                        <div class="mt-5 grid gap-4">
                            <label class="grid gap-2"
                                ><span class="flex justify-between text-sm font-semibold text-slate-700"
                                    ><span>Meta title</span><small>{{ form.meta_title.length }}/255</small></span
                                ><input
                                    v-model="form.meta_title"
                                    maxlength="255"
                                    class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                                    :placeholder="`Nạp game ${selectedGame.name} nhanh chóng`"
                            /></label>
                            <label class="grid gap-2"
                                ><span class="flex justify-between text-sm font-semibold text-slate-700"
                                    ><span>Meta description</span><small>{{ form.meta_description.length }}/320</small></span
                                ><textarea
                                    v-model="form.meta_description"
                                    maxlength="320"
                                    rows="4"
                                    class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                                    placeholder="Mô tả ngắn hiển thị trên kết quả tìm kiếm"
                                />
                            </label>
                            <label class="grid gap-2"
                                ><span class="flex justify-between text-sm font-semibold text-slate-700"
                                    ><span>Keywords SEO</span><small>{{ form.meta_keywords.length }}/1000</small></span
                                ><textarea
                                    v-model="form.meta_keywords"
                                    maxlength="1000"
                                    rows="2"
                                    class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                                    :placeholder="`nạp ${selectedGame.name}, nạp game ${selectedGame.name}, bảng giá ${selectedGame.name}`"
                                /><small class="text-slate-500">Phân cách từ khóa bằng dấu phẩy.</small></label
                            >
                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="grid gap-2"
                                    ><span class="text-sm font-semibold text-slate-700">H1 trang nạp</span
                                    ><input
                                        v-model="form.h1"
                                        maxlength="255"
                                        class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400" /></label
                                ><label class="grid gap-2"
                                    ><span class="text-sm font-semibold text-slate-700">Tiêu đề bài SEO</span
                                    ><input
                                        v-model="form.article_title"
                                        maxlength="255"
                                        class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                                /></label>
                            </div>
                        </div>
                    </article>

                    <article class="min-w-0 rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-950">Nội dung bài SEO bằng TinyMCE</h2>
                        <p class="mt-1 text-sm text-slate-500">Có thể định dạng, chèn liên kết và tải ảnh trực tiếp. Không chèn thêm H1 trong bài.</p>
                        <div class="min-w-0 pt-4"><Editor ref="contentEditor" v-model="form.content" :height="560" /></div>
                    </article>

                    <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-950">FAQ của trang game</h2>
                                <p class="mt-1 text-sm text-slate-500">FAQ hiển thị trên trang và tự tạo FAQPage schema.</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-[8px] border border-violet-200 px-3 py-2 text-xs font-semibold text-violet-700 hover:bg-violet-50"
                                @click="addFaq"
                            >
                                <Plus class="h-4 w-4" /> Thêm FAQ
                            </button>
                        </div>
                        <div v-if="form.faqs.length" class="grid gap-4 pt-5">
                            <div v-for="(faq, index) in form.faqs" :key="index" class="grid gap-3 rounded-[10px] border border-slate-200 p-4">
                                <div class="flex justify-between">
                                    <strong class="text-sm text-slate-700">FAQ {{ index + 1 }}</strong
                                    ><button type="button" class="text-rose-600" aria-label="Xóa FAQ" @click="removeFaq(index)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                                <input
                                    v-model="faq.question"
                                    maxlength="255"
                                    class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                                    placeholder="Câu hỏi"
                                /><textarea
                                    v-model="faq.answer"
                                    maxlength="2000"
                                    rows="3"
                                    class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                                    placeholder="Câu trả lời"
                                />
                            </div>
                        </div>
                        <p v-else class="pt-5 text-sm text-slate-500">Chưa có FAQ riêng cho game này.</p>
                    </article>
                </section>

                <aside class="grid gap-5 xl:sticky xl:top-24">
                    <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-semibold text-slate-950">Xuất bản</h2>
                        <label class="mt-4 flex items-center justify-between gap-3 rounded-[10px] border border-slate-200 p-3"
                            ><span
                                ><strong class="block text-sm text-slate-800">Áp dụng SEO tùy chỉnh</strong
                                ><small class="text-slate-500">Tắt để dùng nội dung mặc định.</small></span
                            ><input v-model="form.is_published" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-violet-600" /></label
                        ><button
                            type="submit"
                            :disabled="saving"
                            class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-[10px] bg-violet-600 px-4 text-sm font-bold text-white hover:bg-violet-700 disabled:opacity-60"
                        >
                            <Save class="h-4 w-4" /> {{ saving ? 'Đang lưu...' : 'Lưu SEO game' }}
                        </button>
                    </article>

                    <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-semibold text-slate-950">Ảnh Open Graph</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Khuyến nghị 1200×630. Nếu bỏ trống sẽ lấy ảnh OG trong cấu hình trang chủ.
                        </p>
                        <div class="mt-4">
                            <UploadImage
                                :image-src="form.og_image || effectiveOgImage"
                                :name-image="`${selectedGame.slug}-og`"
                                @uploaded="form.og_image = $event"
                            />
                        </div>
                        <button v-if="form.og_image" type="button" class="mt-2 text-xs font-semibold text-rose-600" @click="form.og_image = ''">
                            Dùng lại ảnh mặc định</button
                        ><label class="mt-4 grid gap-2"
                            ><span class="text-sm font-semibold text-slate-700">Alt ảnh</span
                            ><input
                                v-model="form.og_image_alt"
                                maxlength="255"
                                class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                        /></label>
                    </article>

                    <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-semibold text-slate-950">Canonical và robots</h2>
                        <div class="mt-4 grid gap-4">
                            <label class="grid gap-2"
                                ><span class="text-sm font-semibold text-slate-700">Canonical tùy chỉnh</span
                                ><input
                                    v-model="form.canonical_url"
                                    type="url"
                                    maxlength="2048"
                                    class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                                    :placeholder="selectedGame.public_url"
                                /><small class="break-all text-slate-500">Hiện tại: {{ effectiveCanonical }}</small></label
                            ><label class="grid gap-2"
                                ><span class="text-sm font-semibold text-slate-700">Robots</span
                                ><select
                                    v-model="form.robots"
                                    class="rounded-[10px] border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                                >
                                    <option value="index,follow">Index, follow</option>
                                    <option value="noindex,follow">Noindex, follow</option>
                                </select></label
                            ><label class="flex items-center justify-between gap-3 rounded-[10px] border border-slate-200 p-3"
                                ><span class="text-sm font-semibold text-slate-700">Breadcrumb schema</span
                                ><input
                                    v-model="form.breadcrumb_schema"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-violet-600" /></label
                            ><label class="flex items-center justify-between gap-3 rounded-[10px] border border-slate-200 p-3"
                                ><span class="text-sm font-semibold text-slate-700">WebPage schema</span
                                ><input v-model="form.webpage_schema" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-violet-600"
                            /></label>
                        </div>
                    </article>

                    <article class="overflow-hidden rounded-[14px] border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-5 py-4">
                            <h2 class="text-base font-semibold text-slate-950">Xem trước Google</h2>
                        </div>
                        <div class="p-5">
                            <p class="truncate text-xs text-emerald-700">{{ effectiveCanonical }}</p>
                            <p class="mt-1 line-clamp-2 text-xl font-medium leading-7 text-[#1a0dab]">{{ effectiveTitle }}</p>
                            <p class="mt-1 line-clamp-3 text-sm leading-5 text-slate-600">{{ effectiveDescription }}</p>
                        </div>
                    </article>

                    <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex justify-between">
                            <h2 class="text-base font-semibold text-slate-950">Checklist SEO</h2>
                            <strong class="text-sm text-violet-700"
                                >{{ seoChecks.filter((item) => item.passed).length }}/{{ seoChecks.length }}</strong
                            >
                        </div>
                        <ul class="mt-4 grid gap-2">
                            <li
                                v-for="item in seoChecks"
                                :key="item.label"
                                class="flex items-start gap-2 text-xs leading-5"
                                :class="item.passed ? 'text-emerald-700' : 'text-slate-500'"
                            >
                                <CheckCircle2 v-if="item.passed" class="mt-0.5 h-4 w-4 shrink-0" /><CircleAlert
                                    v-else
                                    class="mt-0.5 h-4 w-4 shrink-0 text-amber-500"
                                />{{ item.label }}
                            </li>
                        </ul>
                    </article>
                </aside>
            </form>
        </div>
    </div>
</template>
