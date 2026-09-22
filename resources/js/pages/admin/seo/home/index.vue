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
const form = reactive<AdminHomeSeoSettings>({
    meta_title: '',
    meta_description: '',
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
            ...form,
            meta_title: form.meta_title.trim(),
            meta_description: form.meta_description.trim(),
            h1: form.h1.trim(),
            article_title: form.article_title.trim(),
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
    <div class="grid gap-5">
        <Breadcrumb title="SEO trang chủ" description="Quản lý metadata, nội dung hướng dẫn và FAQ riêng cho trang chủ.">
            <template #actions>
                <a
                    href="/"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center gap-2 rounded-[10px] border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:border-violet-300 hover:text-violet-700"
                >
                    Xem trang chủ <ExternalLink class="h-4 w-4" />
                </a>
            </template>
        </Breadcrumb>

        <div v-if="loading" class="rounded-[14px] border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">Đang tải cấu hình...</div>

        <form v-else class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]" @submit.prevent="save">
            <section class="grid min-w-0 gap-5">
                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-semibold text-slate-950">Thông tin hiển thị và tìm kiếm</h2>
                        <p class="mt-1 text-sm text-slate-500">Trang chủ dùng URL gốc nên không cần nhập slug.</p>
                    </div>
                    <div class="mt-5 grid gap-4">
                        <label class="grid gap-2">
                            <span class="flex justify-between text-sm font-semibold text-slate-700"
                                ><span>Meta title</span><small>{{ form.meta_title.length }}/255</small></span
                            >
                            <input
                                v-model="form.meta_title"
                                maxlength="255"
                                class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                                placeholder="Nạp game Teamobi nhanh chóng, giá tốt"
                            />
                        </label>
                        <label class="grid gap-2">
                            <span class="flex justify-between text-sm font-semibold text-slate-700"
                                ><span>Meta description</span><small>{{ form.meta_description.length }}/320</small></span
                            >
                            <textarea
                                v-model="form.meta_description"
                                rows="3"
                                maxlength="320"
                                class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                                placeholder="Mô tả ngắn hiển thị trên kết quả tìm kiếm"
                            />
                        </label>
                        <label class="grid gap-2">
                            <span class="text-sm font-semibold text-slate-700">Tiêu đề H1 đầu trang</span>
                            <input
                                v-model="form.h1"
                                maxlength="255"
                                class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                                placeholder="Nạp Carot Game Teamobi Nhanh Chóng, Giá Tốt"
                            />
                        </label>
                    </div>
                </article>

                <article class="min-w-0 rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <label class="grid gap-2 border-b border-slate-100 pb-4">
                        <span class="text-sm font-semibold text-slate-700">Tiêu đề bài SEO</span>
                        <input
                            v-model="form.article_title"
                            maxlength="255"
                            class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                            placeholder="Hướng dẫn nạp game tại NapCarot"
                        />
                    </label>
                    <div class="min-w-0 pt-5">
                        <p class="mb-3 text-sm text-slate-500">Soạn nội dung hiển thị trong khối hướng dẫn trên trang chủ. Không chèn thêm H1.</p>
                        <Editor ref="contentEditor" v-model="form.content" />
                    </div>
                </article>

                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">FAQ trang chủ</h2>
                            <p class="mt-1 text-sm text-slate-500">Các câu hỏi này đồng thời tạo schema FAQPage.</p>
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
                            <div class="flex items-center justify-between">
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
                            />
                            <textarea
                                v-model="faq.answer"
                                rows="3"
                                maxlength="2000"
                                class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                                placeholder="Câu trả lời"
                            />
                        </div>
                    </div>
                    <p v-else class="pt-5 text-sm text-slate-500">Chưa có FAQ tùy chỉnh.</p>
                </article>
            </section>

            <aside class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm xl:sticky xl:top-24">
                <h2 class="text-base font-semibold text-slate-950">Xuất bản</h2>
                <label class="mt-4 flex items-center justify-between gap-4 rounded-[10px] border border-slate-200 p-3">
                    <span
                        ><strong class="block text-sm text-slate-800">Dùng SEO tùy chỉnh</strong
                        ><small class="text-slate-500">Tắt để dùng nội dung mặc định.</small></span
                    >
                    <input v-model="form.is_published" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-violet-600" />
                </label>
                <button
                    type="submit"
                    :disabled="saving"
                    class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-[10px] bg-violet-600 px-4 text-sm font-bold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <Save class="h-4 w-4" /> {{ saving ? 'Đang lưu...' : 'Lưu SEO trang chủ' }}
                </button>
            </aside>
        </form>
    </div>
</template>
