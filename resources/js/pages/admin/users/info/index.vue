<script setup lang="ts">
import { adminUserService, type AdminUserDetailResponse } from '@/services/admin-user.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
const route = useRoute();
const user = ref<AdminUserDetailResponse | null>(null);
const busy = ref(false);
const load = async (): Promise<void> => {
    try {
        user.value = await adminUserService.show(String(route.params.user_id));
    } catch (error) {
        handleErrorResponse(error);
    }
};
const toggleStatus = async (): Promise<void> => {
    if (!user.value) return;
    busy.value = true;
    try {
        await adminUserService.updateStatus(user.value.id, user.value.status === 'active' ? 'blocked' : 'active');
        await load();
        handleSuccessResponse('Đã cập nhật trạng thái tài khoản.');
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        busy.value = false;
    }
};
onMounted(load);
</script>
<template>
    <section v-if="user" class="rounded-xl border border-slate-200 bg-white p-6">
        <h1 class="text-2xl font-bold">{{ user.name || user.username }}</h1>
        <dl class="my-6 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-slate-500">Email</dt>
                <dd>{{ user.email }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Vai trò</dt>
                <dd>{{ user.role === 'admin' ? 'Quản trị viên' : 'Thành viên' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Trạng thái</dt>
                <dd>{{ user.status }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Đăng nhập gần nhất</dt>
                <dd>{{ user.last_login_at || 'Chưa đăng nhập' }}</dd>
            </div>
        </dl>
        <button v-if="user.role !== 'admin'" class="rounded-lg bg-slate-900 px-4 py-3 text-white" :disabled="busy" @click="toggleStatus">
            {{ user.status === 'active' ? 'Khóa tài khoản' : 'Mở tài khoản' }}
        </button>
    </section>
</template>
