<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import { adminSeoService } from '@/services/admin-seo.service';
import type { AdminSeoCategoryItem, AdminSeoCategoryPayload } from '@/types/admin-seo.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { FolderTree, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, onMounted, reactive, ref } from 'vue';

const loading = ref(false);
const categories = ref<AdminSeoCategoryItem[]>([]);
const editingId = ref<number | null>(null);
const deletingId = ref<number | null>(null);

const filters = reactive({
    search: '',
});

const draft = reactive<AdminSeoCategoryPayload>({
    name: '',
    slug: '',
    seo_title: '',
    seo_description: '',
    robots: 'index,follow',
    is_active: true,
    sort_order: 0,
});

const filteredCategories = computed(() => categories.value);

const resetDraft = (): void => {
    editingId.value = null;
    draft.name = '';
    draft.slug = '';
    draft.seo_title = '';
    draft.seo_description = '';
    draft.robots = 'index,follow';
    draft.is_active = true;
    draft.sort_order = 0;
};

const fetchCategories = async (): Promise<void> => {
    try {
        loading.value = true;
        categories.value = await adminSeoService.listCategories({
            search: filters.search || undefined,
        });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const submitCategory = async (): Promise<void> => {
    try {
        if (editingId.value) {
            const response = await adminSeoService.updateCategory(editingId.value, draft);
            handleSuccessResponse(response);
        } else {
            const response = await adminSeoService.createCategory(draft);
            handleSuccessResponse(response);
        }

        resetDraft();
        await fetchCategories();
    } catch (error) {
        handleErrorResponse(error);
    }
};

const editCategory = (category: AdminSeoCategoryItem): void => {
    editingId.value = category.id;
    draft.name = category.name;
    draft.slug = category.slug;
    draft.seo_title = category.seo_title ?? '';
    draft.seo_description = category.seo_description ?? '';
    draft.robots = category.robots;
    draft.is_active = category.is_active;
    draft.sort_order = category.sort_order;
};

const removeCategory = async (id: number): Promise<void> => {
    const category = categories.value.find((item) => item.id === id);
    const postCount = Number(category?.posts_count ?? 0);
    const confirmation = await Swal.fire({
        icon: 'warning',
        title: 'Xóa danh mục SEO?',
        text:
            postCount > 0
                ? `Danh mục ${category?.name ?? 'này'} đang có ${postCount} bài viết. Các bài sẽ được giữ lại và chuyển về trạng thái chưa phân loại.`
                : `Danh mục ${category?.name ?? 'này'} sẽ bị xóa.`,
        showCancelButton: true,
        confirmButtonText: 'Xóa danh mục',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#e11d48',
    });

    if (!confirmation.isConfirmed) {
        return;
    }

    try {
        deletingId.value = id;
        const response = await adminSeoService.removeCategory(id);
        handleSuccessResponse(response);

        if (editingId.value === id) {
            resetDraft();
        }

        await fetchCategories();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        deletingId.value = null;
    }
};

onMounted(async () => {
    await fetchCategories();
});
</script>

<template>
    <div class="space-y-4">
        <Breadcrumb title="Danh mục SEO" description="Quản lý taxonomy, slug, robots và metadata của các nhóm nội dung sẽ được index." />

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_380px]">
            <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Danh sách danh mục</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Danh mục nên có slug sạch, title riêng và tránh trùng lặp intent giữa các cluster nội dung.
                        </p>
                    </div>

                    <label class="flex items-center gap-2 rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                        <Search class="h-4 w-4" />
                        <input
                            v-model="filters.search"
                            type="text"
                            class="w-full border-0 bg-transparent p-0 text-slate-700 outline-none placeholder:text-slate-400"
                            placeholder="Tìm theo tên, slug, title..."
                            @keyup.enter="fetchCategories"
                        />
                    </label>
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[820px]">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Danh mục</th>
                                <th class="px-4 py-3">Slug</th>
                                <th class="px-4 py-3">SEO title</th>
                                <th class="px-4 py-3">Robots</th>
                                <th class="px-4 py-3">Bài viết</th>
                                <th class="px-4 py-3">Hành động</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="loading">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">Đang tải danh mục...</td>
                            </tr>
                            <tr v-else-if="filteredCategories.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">Chưa có danh mục SEO nào.</td>
                            </tr>
                            <tr v-for="item in filteredCategories" :key="item.id" class="hover:bg-slate-50/70">
                                <td class="px-4 py-3">
                                    <div class="flex items-start gap-3">
                                        <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-50 text-violet-600">
                                            <FolderTree class="h-4 w-4" />
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-900">{{ item.name }}</p>
                                            <p class="mt-1 line-clamp-2 text-xs text-slate-500">
                                                {{ item.seo_description || 'Chưa có mô tả ngắn cho danh mục này.' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono text-sm text-slate-600">{{ item.slug }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ item.seo_title || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full border px-2.5 py-1 text-xs font-semibold"
                                        :class="
                                            item.robots === 'index,follow'
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                : 'border-amber-200 bg-amber-50 text-amber-700'
                                        "
                                    >
                                        {{ item.robots }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ item.posts_count ?? 0 }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-[8px] border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                            @click="editCategory(item)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                            Sửa
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="deletingId === item.id"
                                            class="inline-flex items-center gap-1 rounded-[8px] border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                                            @click="removeCategory(item.id)"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                            {{ deletingId === item.id ? 'Đang xóa...' : 'Xóa' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <aside class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2">
                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-50 text-violet-600">
                        <Plus class="h-4 w-4" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950">
                            {{ editingId ? 'Cập nhật danh mục' : 'Tạo danh mục mới' }}
                        </h3>
                        <p class="text-sm text-slate-500">Dùng cho cluster nội dung hoặc chủ đề cần index riêng.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Tên danh mục</label>
                        <input
                            v-model="draft.name"
                            type="text"
                            class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">Slug</label>
                        <input
                            v-model="draft.slug"
                            type="text"
                            class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 font-mono text-sm outline-none focus:border-violet-300"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">SEO title</label>
                        <input
                            v-model="draft.seo_title"
                            type="text"
                            class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">SEO description</label>
                        <textarea
                            v-model="draft.seo_description"
                            rows="4"
                            class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300"
                        />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-700">Robots</label>
                            <select
                                v-model="draft.robots"
                                class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300"
                            >
                                <option value="index,follow">index,follow</option>
                                <option value="noindex,follow">noindex,follow</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Thứ tự</label>
                            <input
                                v-model.number="draft.sort_order"
                                type="number"
                                min="0"
                                class="mt-2 w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-300"
                            />
                        </div>
                    </div>

                    <label class="flex items-center gap-3 rounded-[10px] border border-slate-200 px-3 py-3 text-sm text-slate-700">
                        <input
                            v-model="draft.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                        />
                        Danh mục đang hoạt động
                    </label>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="inline-flex flex-1 items-center justify-center rounded-[10px] bg-violet-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-violet-500"
                            @click="submitCategory"
                        >
                            {{ editingId ? 'Lưu thay đổi' : 'Thêm danh mục' }}
                        </button>
                        <button
                            v-if="editingId"
                            type="button"
                            class="inline-flex items-center justify-center rounded-[10px] border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="resetDraft"
                        >
                            Hủy
                        </button>
                    </div>
                </div>
            </aside>
        </section>
    </div>
</template>
