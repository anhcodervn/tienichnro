<script setup lang="ts">
import Breadcrumb from "@/components/MasterLayouts/Breadcrumb/index.vue";
import Editor from "@/components/shared/Editor/index.vue";
import { adminSeoService } from "@/services/admin-seo.service";
import type { AdminSeoPostPayload, SeoPostStatus, SeoRobotsValue } from "@/types/admin-seo.type";
import { uploadEditorImages } from "@/utils/editor-image-upload";
import { handleErrorResponse, handleSuccessResponse } from "@/utils/response";
import { Eye, Save } from "lucide-vue-next";
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();
const saving = ref(false);
const loading = ref(false);
const categories = ref<Array<{ id: number; name: string }>>([]);
const editingId = computed(() => {
    const raw = route.params.seo_post_id;
    return raw ? Number(raw) : null;
});

const form = reactive<AdminSeoPostPayload>({
    title: "Hướng dẫn tạo đơn nạp game đầu tiên trên Nạp Carot",
    slug: "huong-dan-nap-carot-teamobi",
    seo_category_id: null,
    excerpt: "Bài viết hướng dẫn tạo đơn nạp game, gửi payload và theo dõi kết quả trả về trong Nạp Carot.",
    content: [
        {
            type: "paragraph",
            children: [
                {
                    text: "Bài viết này hướng dẫn luồng chọn game, server, gói nạp, thanh toán và theo dõi kết quả.",
                },
            ],
        },
    ],
    seo_title: "Hướng dẫn tạo đơn nạp game đầu tiên | Nạp Carot",
    seo_description: "Từng bước tạo đơn nạp game, tích hợp API, theo dõi task và nhận kết quả trong Nạp Carot.",
    canonical_url: "https://napcarot.vn/tin-tuc/huong-dan-nap-carot-teamobi",
    robots: "index,follow",
    focus_keyword: "nạp game teamobi",
    cover_alt: "Minh họa quy trình tạo đơn nạp game trên Nạp Carot",
    article_schema: true,
    breadcrumb_schema: true,
    status: "draft",
    published_at: null,
    scheduled_at: null,
});

const seoScore = computed(() => {
    let score = 58;

    if (form.title.trim().length >= 30) score += 8;
    if ((form.seo_description ?? "").trim().length >= 70) score += 8;
    if ((form.canonical_url ?? "").trim()) score += 8;
    if ((form.focus_keyword ?? "").trim()) score += 6;
    if ((form.cover_alt ?? "").trim()) score += 5;
    if (Array.isArray(form.content) && form.content.length > 0) score += 7;
    if (form.article_schema) score += 4;
    if (form.breadcrumb_schema) score += 4;

    return Math.min(score, 100);
});

const pageTitle = computed(() => (editingId.value ? "Cập nhật bài viết SEO" : "Tạo bài viết SEO"));

const fetchMeta = async (): Promise<void> => {
    const response = await adminSeoService.listPosts();
    categories.value = response.categories;

    if (!editingId.value && response.categories.length > 0 && !form.seo_category_id) {
        form.seo_category_id = response.categories[0].id;
    }
};

const fetchPost = async (): Promise<void> => {
    if (!editingId.value) {
        return;
    }

    const post = await adminSeoService.getPost(editingId.value);

    form.title = post.title;
    form.slug = post.slug;
    form.seo_category_id = post.seo_category_id;
    form.excerpt = post.excerpt ?? "";
    form.content = Array.isArray(post.content) ? post.content : [];
    form.seo_title = post.seo_title ?? "";
    form.seo_description = post.seo_description ?? "";
    form.canonical_url = post.canonical_url ?? "";
    form.robots = post.robots as SeoRobotsValue;
    form.focus_keyword = post.focus_keyword ?? "";
    form.cover_alt = post.cover_alt ?? "";
    form.article_schema = post.article_schema;
    form.breadcrumb_schema = post.breadcrumb_schema;
    form.status = post.status as SeoPostStatus;
    form.published_at = post.published_at;
    form.scheduled_at = post.scheduled_at;
};

const handleSave = async (): Promise<void> => {
    saving.value = true;

    try {
        form.content = await uploadEditorImages(form.content ?? []);

        const payload: AdminSeoPostPayload = {
            ...form,
            content: form.content ?? [],
        };

        const response = editingId.value
            ? await adminSeoService.updatePost(editingId.value, payload)
            : await adminSeoService.createPost(payload);

        handleSuccessResponse(response);

        if (!editingId.value) {
            await router.push("/admin/seo/posts");
        }
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

onMounted(async () => {
    try {
        loading.value = true;
        await fetchMeta();
        await fetchPost();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="space-y-4">
        <Breadcrumb :title="pageTitle" description="Soạn bài với title, canonical, robots, schema và preview metadata trước khi publish.">
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="form.canonical_url"
                        :href="form.canonical_url"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-2 rounded-[10px] border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        <Eye class="h-4 w-4" />
                        Xem canonical
                    </a>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-[10px] bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-500"
                        :disabled="saving || loading"
                        @click="handleSave"
                    >
                        <Save class="h-4 w-4" />
                        {{ saving ? "Đang lưu..." : "Lưu bài viết" }}
                    </button>
                </div>
            </template>
        </Breadcrumb>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_360px]">
            <section class="space-y-4">
                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Thông tin bài viết</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-slate-700">Tiêu đề bài viết</label>
                            <input v-model="form.title" type="text" class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Slug</label>
                            <input v-model="form.slug" type="text" class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 font-mono text-sm outline-none focus:border-violet-300" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Danh mục</label>
                            <select v-model="form.seo_category_id" class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300">
                                <option :value="null">Không gắn danh mục</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-slate-700">Mô tả ngắn</label>
                            <textarea v-model="form.excerpt" rows="3" class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300" />
                        </div>
                    </div>
                </article>

                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Nội dung chính</h2>
                    <p class="mt-1 text-sm text-slate-500">Nội dung nên có heading rõ ràng, bám đúng search intent và tránh lặp ý giữa nhiều bài viết.</p>
                    <div class="mt-5">
                        <Editor v-model="form.content" />
                    </div>
                </article>
            </section>

            <aside class="space-y-4">
                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Metadata SEO</h2>
                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="text-sm font-medium text-slate-700">SEO title</label>
                            <input v-model="form.seo_title" type="text" class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">SEO description</label>
                            <textarea v-model="form.seo_description" rows="4" class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Canonical URL</label>
                            <input v-model="form.canonical_url" type="url" class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300" />
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-slate-700">Robots</label>
                                <select v-model="form.robots" class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300">
                                    <option value="index,follow">index,follow</option>
                                    <option value="noindex,follow">noindex,follow</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700">Trạng thái</label>
                                <select v-model="form.status" class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300">
                                    <option value="draft">draft</option>
                                    <option value="published">published</option>
                                    <option value="scheduled">scheduled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Tín hiệu rich result</h2>
                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="text-sm font-medium text-slate-700">Focus keyword</label>
                            <input v-model="form.focus_keyword" type="text" class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Alt text ảnh đại diện</label>
                            <input v-model="form.cover_alt" type="text" class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300" />
                        </div>

                        <label class="flex items-center justify-between rounded-[12px] border border-slate-200 px-4 py-3">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Article schema</span>
                                <span class="mt-1 block text-xs text-slate-500">BlogPosting hoặc Article cho bài viết public.</span>
                            </span>
                            <input v-model="form.article_schema" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500" />
                        </label>

                        <label class="flex items-center justify-between rounded-[12px] border border-slate-200 px-4 py-3">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Breadcrumb schema</span>
                                <span class="mt-1 block text-xs text-slate-500">Dùng cho phân cấp danh mục và URL bài viết.</span>
                            </span>
                            <input v-model="form.breadcrumb_schema" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500" />
                        </label>
                    </div>
                </article>

                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Đánh giá nhanh</h2>
                    <div class="mt-5 rounded-[14px] border border-violet-100 bg-violet-50 p-4">
                        <p class="text-sm font-medium text-violet-700">SEO score dự kiến</p>
                        <p class="mt-2 text-4xl font-bold text-violet-900">{{ seoScore }}/100</p>
                    </div>

                    <ul class="mt-5 space-y-2 text-sm leading-6 text-slate-600">
                        <li>Title hiện tại: {{ form.title.length }} ký tự</li>
                        <li>Description hiện tại: {{ (form.seo_description ?? "").length }} ký tự</li>
                        <li>Canonical: {{ form.canonical_url ? "Đã có" : "Chưa khai báo" }}</li>
                        <li>Schema bài viết: {{ form.article_schema ? "Bật" : "Tắt" }}</li>
                    </ul>
                </article>
            </aside>
        </div>
    </div>
</template>
