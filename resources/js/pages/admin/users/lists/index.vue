<script setup lang="ts">
import { adminUserService, type AdminUserListItem } from '@/services/admin-user.service';
import { handleErrorResponse } from '@/utils/response';
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';

const users = ref<AdminUserListItem[]>([]);
const search = ref('');
const loading = ref(false);
const page = ref(1);
const lastPage = ref(1);
const load = async (): Promise<void> => {
    loading.value = true;
    try {
        const response = await adminUserService.list({ search: search.value, page: page.value, per_page: 15 });
        users.value = response.data;
        lastPage.value = response.meta.last_page;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};
const searchUsers = (): void => {
    page.value = 1;
    void load();
};
const changePage = (value: number): void => {
    page.value = value;
    void load();
};
onMounted(load);
</script>

<template>
    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h1 class="text-2xl font-bold">Người dùng</h1>
        <form class="my-5 flex gap-3" @submit.prevent="searchUsers">
            <input
                v-model="search"
                class="min-w-0 flex-1 rounded-lg border p-3"
                placeholder="Tên, email hoặc tài khoản"
                aria-label="Tìm người dùng"
            />
            <button class="rounded-lg bg-emerald-700 px-4 text-white" :disabled="loading">Tìm kiếm</button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th class="p-3">Tài khoản</th>
                        <th class="p-3">Email</th>
                        <th class="p-3">Vai trò</th>
                        <th class="p-3">Trạng thái</th>
                        <th class="p-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id" class="border-t">
                        <td class="p-3">{{ user.name || user.username }}</td>
                        <td class="p-3">{{ user.email }}</td>
                        <td class="p-3">{{ user.role === 'admin' ? 'Quản trị viên' : 'Thành viên' }}</td>
                        <td class="p-3">{{ user.status }}</td>
                        <td class="p-3"><RouterLink class="text-emerald-700" :to="`/admin/users/${user.id}`">Chi tiết</RouterLink></td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!loading && !users.length" class="p-5 text-slate-500">Chưa có người dùng phù hợp.</p>
        </div>
        <div class="mt-5 flex items-center gap-4">
            <button :disabled="loading || page === 1" @click="changePage(page - 1)">Trang trước</button>
            <span>{{ page }} / {{ lastPage }}</span>
            <button :disabled="loading || page >= lastPage" @click="changePage(page + 1)">Trang sau</button>
        </div>
    </section>
</template>
