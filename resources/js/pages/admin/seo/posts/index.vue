<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import { adminSeoService } from '@/services/admin-seo.service';
import type { AdminSeoPostItem } from '@/types/admin-seo.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { BookOpenText, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';

const loading = ref(false);
const rows = ref<AdminSeoPostItem[]>([]);
const filters = reactive({
    search: '',
    status: '',
});

const filteredRows = computed(() => rows.value);

const fetchPosts = async (): Promise<void> => {
    try {
        loading.value = true;
        const response = await adminSeoService.listPosts({
            search: filters.search || undefined,
            status: filters.status || undefined,
        });
        rows.value = response.posts;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const removePost = async (id: number): Promise<void> => {
    try {
        const response = await adminSeoService.removePost(id);
        handleSuccessResponse(response);
        await fetchPosts();
    } catch (error) {
        handleErrorResponse(error);
    }
};

onMounted(async () => {
    await fetchPosts();
});
</script>

<template>
    <div class="space-y-4">
        <Breadcrumb title="Bài viết SEO" description="Quản trị danh sách bài viết, canonical, điểm SEO và trạng thái index/publish.">
            <template #actions>
                <RouterLink
                    to="/admin/seo/posts/create"
                    class="inline-flex items-center gap-2 rounded-[10px] bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-500"
                >
                    <Plus class="h-4 w-4" />
                    Tạo bài viết
                </RouterLink>
            </template>
        </Breadcrumb>

        <section class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1.4fr)_220px]">
                <label class="flex items-center gap-2 rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                    <Search class="h-4 w-4" />
                    <input
                        v-model="filters.search"
                        type="text"
                        class="w-full border-0 bg-transparent p-0 text-slate-700 outline-none placeholder:text-slate-400"
                        placeholder="Tìm theo tiêu đề, slug hoặc danh mục..."
                        @keyup.enter="fetchPosts"
                    />
                </label>

                <select
                    v-model="filters.status"
                    class="rounded-[10px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 outline-none"
                    @change="fetchPosts"
                >
                    <option value="">Tất cả trạng thái</option>
                    <option value="published">published</option>
                    <option value="draft">draft</option>
                    <option value="scheduled">scheduled</option>
                </select>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full min-w-[1080px]">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Bài viết</th>
                            <th class="px-4 py-3">Loại / danh mục</th>
                            <th class="px-4 py-3">Canonical</th>
                            <th class="px-4 py-3">SEO score</th>
                            <th class="px-4 py-3">Trạng thái</th>
                            <th class="px-4 py-3">Cập nhật</th>
                            <th class="px-4 py-3">Hành động</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">Đang tải bài viết...</td>
                        </tr>
                        <tr v-else-if="filteredRows.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">Chưa có bài viết SEO nào.</td>
                        </tr>
                        <tr v-for="row in filteredRows" :key="row.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <div class="flex items-start gap-3">
                                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-50 text-violet-600">
                                        <BookOpenText class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ row.title }}</p>
                                        <p class="mt-1 font-mono text-xs text-slate-500">{{ row.slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <span class="rounded-full bg-violet-50 px-2 py-1 text-xs font-bold uppercase text-violet-700">{{ row.type }}</span>
                                <p class="mt-2">{{ row.category?.name || '-' }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <a
                                    v-if="row.canonical_url"
                                    :href="row.canonical_url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="text-violet-600 hover:underline"
                                >
                                    {{ row.canonical_url }}
                                </a>
                                <span v-else>-</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-xs font-semibold"
                                    :class="
                                        row.seo_title && row.seo_description && row.canonical_url
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                            : row.seo_title || row.seo_description
                                              ? 'border-sky-200 bg-sky-50 text-sky-700'
                                              : 'border-amber-200 bg-amber-50 text-amber-700'
                                    "
                                >
                                    {{
                                        row.seo_title && row.seo_description && row.canonical_url
                                            ? '90/100'
                                            : row.seo_title || row.seo_description
                                              ? '78/100'
                                              : '60/100'
                                    }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-xs font-semibold"
                                    :class="
                                        row.status === 'published'
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                            : row.status === 'scheduled'
                                              ? 'border-sky-200 bg-sky-50 text-sky-700'
                                              : 'border-slate-200 bg-slate-100 text-slate-700'
                                    "
                                >
                                    {{ row.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                {{ new Date(row.updated_at).toLocaleString('vi-VN') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <RouterLink
                                        :to="`/admin/seo/posts/${row.id}/edit`"
                                        class="inline-flex items-center rounded-[8px] border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                    >
                                        Sửa
                                    </RouterLink>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-[8px] border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
                                        @click="removePost(row.id)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                        Xóa
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
