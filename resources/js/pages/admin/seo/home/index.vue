<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import Editor from '@/components/shared/Editor/index.vue';
import { adminSeoService } from '@/services/admin-seo.service';
import type { AdminHomeSeoSettings } from '@/types/admin-seo.type';
import { uploadEditorImages } from '@/utils/editor-image-upload';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { ExternalLink, Plus, Save, Trash2 } from 'lucide-vue-next';
import { nextTick, onMounted, reactive, ref } from 'vue';

const loading = ref(true);
const saving = ref(false);
const contentEditor = ref<{ flush: () => unknown[] | string } | null>(null);
const homeUrl = `${window.location.origin}/`;
const inputClass =
    'min-h-9 w-full rounded-[5px] border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100';
const cardClass = 'min-w-0 rounded-[5px] border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900';
const form = reactive<AdminHomeSeoSettings>({
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
    h1: '',
    article_title: '',
    content: [],
    faqs: [],
    is_published: false,
});

const loadSettings = async (): Promise<void> => {
    loading.value = true;

    try {
        Object.assign(form, await adminSeoService.getHomeSeo());
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const addFaq = (): void => {
    if (form.faqs.length < 20) {
        form.faqs.push({ question: '', answer: '' });
    }
};

const removeFaq = (index: number): void => {
    form.faqs.splice(index, 1);
};

const save = async (): Promise<void> => {
    saving.value = true;

    try {
        const latestContent = contentEditor.value?.flush();
        if (Array.isArray(latestContent)) {
            form.content = latestContent;
        }
        await nextTick();

        form.content = await uploadEditorImages(form.content);
        const response = await adminSeoService.updateHomeSeo({
            meta_title: form.meta_title.trim(),
            meta_description: form.meta_description.trim(),
            meta_keywords: form.meta_keywords.trim(),
            content: form.content,
            is_published: form.is_published,
            faqs: form.faqs
                .map((item) => ({ question: item.question.trim(), answer: item.answer.trim() }))
                .filter((item) => item.question || item.answer),
        });

        Object.assign(form, response.data.data);
        handleSuccessResponse(response);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

onMounted(loadSettings);
</script>

<template>
    <div class="grid gap-4 text-slate-900 dark:text-slate-100">
        <Breadcrumb title="SEO trang chủ" description="Chỉnh tiêu đề, mô tả, bài viết và FAQ của trang chủ.">
            <template #actions>
                <a
                    href="/"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center gap-2 rounded-[5px] border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:text-violet-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                    >Xem trang chủ <ExternalLink class="h-3.5 w-3.5"
                /></a>
            </template>
        </Breadcrumb>
        <p v-if="loading" role="status" :class="cardClass">Đang tải cấu hình…</p>
        <form v-else class="grid min-w-0 items-start gap-4 lg:grid-cols-[minmax(0,2.2fr)_minmax(16rem,1fr)]" @submit.prevent="save">
            <article :class="[cardClass, 'lg:col-span-2']" data-seo-editor-context>
                <div class="grid items-center gap-2 sm:grid-cols-2">
                    <div>
                        <h2 class="text-sm font-semibold">Cấu hình SEO trang chủ</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Nhập tiêu đề và mô tả một lần, dùng chung cho Google và phần đầu trang chủ.
                        </p>
                    </div>
                    <div class="text-xs leading-5 text-slate-500">
                        <a :href="homeUrl" target="_blank" rel="noreferrer" class="break-all font-medium text-violet-600">{{ homeUrl }}</a>
                        <p>Trang chủ sử dụng URL gốc, không cần nhập slug.</p>
                    </div>
                </div>
            </article>
            <section class="grid min-w-0 gap-4" aria-label="Soạn bài SEO trang chủ">
                <article :class="cardClass">
                    <h2 class="mb-3 text-sm font-bold">Thông tin bài viết</h2>
                    <div class="grid gap-3">
                        <label class="grid gap-1.5 text-xs font-semibold"
                            ><span class="flex justify-between gap-2"
                                ><span>Tiêu đề trang chủ</span
                                ><small class="font-normal text-slate-400">{{ form.meta_title.length }}/255</small></span
                            ><input v-model="form.meta_title" :class="inputClass" maxlength="255" placeholder="Tiêu đề trên Google và đầu trang chủ"
                        /></label>
                        <label class="grid gap-1.5 text-xs font-semibold"
                            ><span class="flex justify-between gap-2"
                                ><span>Mô tả ngắn</span><small class="font-normal text-slate-400">{{ form.meta_description.length }}/320</small></span
                            ><textarea
                                v-model="form.meta_description"
                                :class="inputClass"
                                rows="3"
                                maxlength="320"
                                placeholder="Mô tả trên Google và bên dưới tiêu đề đầu trang"
                            ></textarea>
                        </label>
                        <label class="grid gap-1.5 text-xs font-semibold"
                            >Từ khóa (không bắt buộc)<input
                                v-model="form.meta_keywords"
                                :class="inputClass"
                                maxlength="1000"
                                placeholder="Ngọc Rồng Online, tiện ích NRO…"
                            /><small class="font-normal leading-5 text-slate-400">Phân cách các từ khóa bằng dấu phẩy.</small></label
                        >
                    </div>
                </article>
                <article :class="cardClass">
                    <h2 class="text-sm font-bold">Nội dung bài viết</h2>
                    <p class="mb-3 mt-1 text-xs leading-5 text-slate-500">
                        Bài viết nằm bên dưới các công cụ. Viết nội dung tại đây, dùng H2, H3 để chia mục; không cần thêm H1.
                    </p>
                    <Editor ref="contentEditor" v-model="form.content" :height="520" />
                </article>
                <article :class="cardClass">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="text-sm font-bold">Câu hỏi thường gặp (FAQ)</h2>
                            <p class="mt-1 text-xs text-slate-500">Tối đa 20 câu hỏi; đồng thời tạo schema FAQPage.</p>
                        </div>
                        <button
                            type="button"
                            :disabled="form.faqs.length >= 20"
                            class="inline-flex min-h-9 items-center gap-1 rounded-[5px] border border-violet-200 px-3 text-xs font-semibold text-violet-700 hover:bg-violet-50 disabled:opacity-50"
                            @click="addFaq"
                        >
                            <Plus class="h-3.5 w-3.5" />Thêm câu hỏi
                        </button>
                    </div>
                    <div v-if="form.faqs.length" class="mt-3 grid gap-3">
                        <div
                            v-for="(faq, index) in form.faqs"
                            :key="index"
                            class="grid gap-2 rounded-[5px] border border-slate-200 bg-slate-50/50 p-3 dark:border-slate-700 dark:bg-slate-800/50"
                        >
                            <div class="flex items-center justify-between">
                                <strong class="text-xs text-slate-500">FAQ {{ index + 1 }}</strong
                                ><button
                                    type="button"
                                    class="inline-flex min-h-8 items-center gap-1 text-xs text-rose-600"
                                    aria-label="Xóa FAQ"
                                    @click="removeFaq(index)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />Xóa
                                </button>
                            </div>
                            <label class="grid gap-1.5 text-xs">Câu hỏi<input v-model="faq.question" :class="inputClass" maxlength="255" /></label>
                            <label class="grid gap-1.5 text-xs"
                                >Câu trả lời<textarea v-model="faq.answer" :class="inputClass" rows="2" maxlength="2000"></textarea>
                            </label>
                        </div>
                    </div>
                    <p v-else class="mt-3 text-xs text-slate-400">Chưa có FAQ tùy chỉnh.</p>
                </article>
            </section>
            <aside class="grid min-w-0 gap-4" aria-label="Xem trước và xuất bản SEO">
                <article :class="cardClass">
                    <h2 class="mb-3 text-sm font-bold">Hiển thị trên Google</h2>
                    <div class="mb-3 border-b border-slate-100 pb-3 dark:border-slate-700" aria-label="Xem trước kết quả Google">
                        <p class="break-all text-xs text-slate-400">{{ homeUrl }}</p>
                        <p class="mt-1 break-words text-base leading-6 text-blue-700 dark:text-blue-400">
                            {{ form.meta_title || 'Tiêu đề trang chủ' }}
                        </p>
                        <p class="mt-1 break-words text-xs leading-5 text-slate-500">
                            {{ form.meta_description || 'Mô tả trang chủ trên kết quả tìm kiếm.' }}
                        </p>
                    </div>
                    <label class="grid gap-1.5 text-xs font-semibold">Canonical URL<input :value="homeUrl" readonly :class="inputClass" /></label>
                    <label class="my-3 flex items-start gap-2"
                        ><input v-model="form.is_published" type="checkbox" class="mt-0.5 rounded border-slate-300 text-violet-600" /><span
                            class="text-xs leading-5"
                            ><strong class="block font-semibold">Hiển thị bài viết và FAQ</strong
                            ><span class="text-slate-500">Tắt để ẩn bài viết và FAQ. Tiêu đề, mô tả vẫn được áp dụng.</span></span
                        ></label
                    >
                    <button
                        type="submit"
                        :disabled="saving"
                        class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-[5px] bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                    >
                        <Save class="h-4 w-4" />{{ saving ? 'Đang lưu…' : 'Lưu SEO trang chủ' }}
                    </button>
                </article>
            </aside>
        </form>
    </div>
</template>
