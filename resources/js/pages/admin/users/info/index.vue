<script setup lang="ts">
import {
    adminUserService,
    type AdminUserDetailResponse,
    type AdminUserProfilePayload,
    type AdminUserTool,
    type AdminUserWalletTransaction,
} from '@/services/admin-user.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { isAxiosError } from 'axios';
import Swal from 'sweetalert2';
import { computed, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

const route = useRoute();
const user = ref<AdminUserDetailResponse | null>(null);
const loading = ref(false);
const failed = ref(false);
const busy = ref(false);
const errors = ref<string[]>([]);
const profile = reactive<AdminUserProfilePayload>({ username: '', email: '', full_name: null, phone: null, avatar: null });
const wallet = reactive({ direction: 'credit' as 'credit' | 'debit', amount: 0, description: '', request_id: '' });
const password = reactive({ password: '', password_confirmation: '' });
const tools = ref<AdminUserTool[]>([]);
const selectedTools = ref<number[]>([]);
const toolsLoading = ref(false);
const toolsFailed = ref(false);
const toolsLoadedFor = ref('');
const toolSearch = ref('');
const filteredTools = computed(() =>
    tools.value.filter((item) => `${item.name} ${item.product_code}`.toLowerCase().includes(toolSearch.value.toLowerCase())),
);
let toolRequest = 0;
const transactions = ref<AdminUserWalletTransaction[]>([]);
const historyLoading = ref(false);
const historyFailed = ref(false);
const historyPage = ref(1);
const historyLastPage = ref(1);
const tab = computed(() => (['wallet', 'password', 'tools'].includes(String(route.query.tab)) ? String(route.query.tab) : 'profile'));
const tabs = [
    { key: 'profile', label: 'Thông tin user' },
    { key: 'wallet', label: 'Cộng / trừ tiền' },
    { key: 'password', label: 'Đổi mật khẩu' },
    { key: 'tools', label: 'Quyền tool' },
];
const inputClass =
    'min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const buttonClass = 'min-h-11 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50';
const money = (value: number): string => `${Number(value).toLocaleString('vi-VN')} đ`;
const date = (value: string | null): string => (value ? new Date(value).toLocaleString('vi-VN') : 'Chưa có');
const success = (message: string): void => {
    handleSuccessResponse({ data: { status: true, message } });
};
const reportError = (error: unknown): void => {
    if (isAxiosError<{ errors?: Record<string, string[]>; message?: string }>(error)) {
        errors.value = error.response?.data.errors
            ? Object.values(error.response.data.errors).flat()
            : [error.response?.data.message || 'Không thể thực hiện thao tác.'];
    } else {
        handleErrorResponse({ message: error instanceof Error ? error.message : 'Không thể thực hiện thao tác.' });
    }
};
const historyLoad = async (page = 1): Promise<void> => {
    const userId = String(route.params.user_id);
    historyLoading.value = true;
    historyFailed.value = false;
    try {
        const result = await adminUserService.walletHistory(userId, page);
        if (String(route.params.user_id) !== userId) return;
        transactions.value = result.data;
        historyPage.value = result.current_page;
        historyLastPage.value = result.last_page;
    } catch {
        historyFailed.value = true;
    } finally {
        historyLoading.value = false;
    }
};
const load = async (): Promise<void> => {
    const userId = String(route.params.user_id);
    loading.value = true;
    failed.value = false;
    user.value = null;
    toolRequest++;
    toolsLoadedFor.value = '';
    toolsLoading.value = false;
    tools.value = [];
    selectedTools.value = [];
    toolSearch.value = '';
    try {
        const result = await adminUserService.show(userId);
        if (String(route.params.user_id) !== userId) return;
        user.value = result;
        Object.assign(profile, {
            username: result.username || '',
            email: result.email || '',
            full_name: result.full_name,
            phone: result.phone,
            avatar: result.avatar,
        });
        if (tab.value === 'wallet') void historyLoad();
        if (tab.value === 'tools') await loadTools();
    } catch (error) {
        failed.value = true;
        handleErrorResponse({ message: error instanceof Error ? error.message : 'Không tải được thông tin người dùng.' });
    } finally {
        loading.value = false;
    }
};
const loadTools = async (): Promise<void> => {
    const userId = String(route.params.user_id);
    const request = ++toolRequest;
    toolsLoading.value = true;
    toolsFailed.value = false;
    toolsLoadedFor.value = '';
    try {
        const result = await adminUserService.tools(userId);
        if (request !== toolRequest || String(route.params.user_id) !== userId) return;
        tools.value = result.tools;
        selectedTools.value = [...result.product_ids];
        toolsLoadedFor.value = userId;
    } catch {
        if (request === toolRequest) toolsFailed.value = true;
    } finally {
        if (request === toolRequest) toolsLoading.value = false;
    }
};
const saveTools = async (): Promise<void> => {
    if (!user.value || busy.value || toolsLoading.value || toolsFailed.value || toolsLoadedFor.value !== String(user.value.id)) return;
    const userId = user.value.id;
    busy.value = true;
    errors.value = [];
    try {
        await adminUserService.updateTools(userId, [...selectedTools.value]);
        if (user.value?.id === userId) success('Đã cập nhật quyền sử dụng tool.');
    } catch (error) {
        reportError(error);
    } finally {
        busy.value = false;
    }
};
const saveProfile = async (): Promise<void> => {
    if (!user.value || busy.value) return;
    busy.value = true;
    errors.value = [];
    try {
        user.value = await adminUserService.update(user.value.id, profile);
        success('Đã cập nhật thông tin người dùng.');
    } catch (error) {
        reportError(error);
    } finally {
        busy.value = false;
    }
};
const adjustWallet = async (): Promise<void> => {
    if (!user.value || busy.value) return;
    busy.value = true;
    errors.value = [];
    try {
        const confirmation = await Swal.fire({
            title: 'Xác nhận điều chỉnh số dư',
            text: `${wallet.direction === 'credit' ? 'Cộng' : 'Trừ'} ${money(wallet.amount)} cho ${user.value.username} (#${user.value.id}).`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Xác nhận',
            cancelButtonText: 'Hủy',
            focusCancel: true,
        });
        if (!confirmation.isConfirmed) return;
        wallet.request_id ||= crypto.randomUUID();
        const result = await adminUserService.adjustWallet(user.value.id, { ...wallet });
        user.value.wallet_balance = result.balance;
        Object.assign(wallet, { amount: 0, description: '', request_id: '' });
        success('Đã điều chỉnh số dư và ghi lịch sử giao dịch.');
        await historyLoad();
    } catch (error) {
        reportError(error);
    } finally {
        busy.value = false;
    }
};
const resetPassword = async (): Promise<void> => {
    if (!user.value || busy.value) return;
    busy.value = true;
    errors.value = [];
    try {
        const confirmation = await Swal.fire({
            title: 'Đặt lại mật khẩu?',
            text: `Cập nhật mật khẩu cho ${user.value.username} (#${user.value.id}) và thu hồi các phiên đăng nhập hiện có.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Đặt lại mật khẩu',
            cancelButtonText: 'Hủy',
            focusCancel: true,
        });
        if (!confirmation.isConfirmed) return;
        await adminUserService.resetPassword(user.value.id, { ...password });
        Object.assign(password, { password: '', password_confirmation: '' });
        success('Đã đặt lại mật khẩu người dùng.');
    } catch (error) {
        reportError(error);
    } finally {
        busy.value = false;
    }
};
const toggleStatus = async (): Promise<void> => {
    if (!user.value || busy.value) return;
    busy.value = true;
    errors.value = [];
    try {
        const next = user.value.status === 'active' ? 'blocked' : 'active';
        await adminUserService.updateStatus(user.value.id, next);
        user.value.status = next;
        success('Đã cập nhật trạng thái tài khoản.');
    } catch (error) {
        reportError(error);
    } finally {
        busy.value = false;
    }
};
watch(
    () => [wallet.direction, wallet.amount, wallet.description],
    () => {
        wallet.request_id = '';
    },
);
watch(
    () => [route.params.user_id, route.query.tab, route.query.direction],
    () => {
        errors.value = [];
        Object.assign(password, { password: '', password_confirmation: '' });
        wallet.direction = route.query.direction === 'debit' ? 'debit' : 'credit';
        wallet.request_id = '';
        if (user.value && tab.value === 'wallet') void historyLoad();
        if (user.value && tab.value === 'tools') void loadTools();
    },
    { immediate: true },
);
watch(
    () => route.params.user_id,
    () => {
        Object.assign(wallet, { amount: 0, description: '', request_id: '' });
        transactions.value = [];
        historyPage.value = 1;
        historyLastPage.value = 1;
        void load();
    },
    { immediate: true },
);
</script>

<template>
    <section class="grid gap-4 text-slate-900 dark:text-slate-100">
        <RouterLink class="w-fit text-sm font-semibold text-emerald-700 dark:text-emerald-400" to="/admin/users">← Danh sách người dùng</RouterLink>
        <p v-if="loading" role="status" class="p-6">Đang tải thông tin người dùng…</p>
        <button v-else-if="failed" type="button" class="min-h-11 text-rose-600" @click="load">Tải thất bại. Bấm để thử lại.</button>
        <template v-else-if="user">
            <header
                class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
            >
                <div>
                    <p class="text-xs text-slate-500">User #{{ user.id }} · {{ user.username }}</p>
                    <h1 class="mt-1 text-xl font-bold">{{ user.name || user.username }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ user.email }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Số dư ví</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ money(user.wallet_balance) }}</p>
                </div>
            </header>
            <nav class="flex gap-2 overflow-x-auto" aria-label="Thao tác người dùng">
                <RouterLink
                    v-for="item in tabs"
                    :key="item.key"
                    :to="{ path: `/admin/users/${user.id}`, query: { tab: item.key } }"
                    :aria-current="tab === item.key ? 'page' : undefined"
                    class="inline-flex min-h-11 shrink-0 items-center rounded-lg px-4 text-sm font-semibold"
                    :class="tab === item.key ? 'bg-emerald-700 text-white' : 'bg-white text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                    >{{ item.label }}</RouterLink
                >
            </nav>
            <ul
                v-if="errors.length"
                role="alert"
                class="grid gap-1 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-300"
            >
                <li v-for="message in errors" :key="message">{{ message }}</li>
            </ul>
            <div v-if="tab === 'profile'" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <form class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900" @submit.prevent="saveProfile">
                    <h2 class="mb-4 font-bold">Thông tin user</h2>
                    <fieldset :disabled="busy" class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2 text-sm"
                            >Tên đăng nhập<input
                                v-model="profile.username"
                                :class="inputClass"
                                required
                                minlength="3"
                                maxlength="32"
                                autocomplete="off"
                        /></label>
                        <label class="grid gap-2 text-sm">Họ và tên<input v-model="profile.full_name" :class="inputClass" maxlength="255" /></label>
                        <label class="grid gap-2 text-sm"
                            >Email<input v-model="profile.email" type="email" :class="inputClass" required maxlength="255"
                        /></label>
                        <label class="grid gap-2 text-sm"
                            >Số điện thoại<input v-model="profile.phone" type="tel" :class="inputClass" maxlength="30"
                        /></label>
                        <label class="grid gap-2 text-sm sm:col-span-2"
                            >URL ảnh đại diện<input v-model="profile.avatar" type="url" :class="inputClass" maxlength="255"
                        /></label>
                        <p class="text-xs text-slate-500 sm:col-span-2">Nếu đổi email, tài khoản cần xác minh lại địa chỉ email mới.</p>
                        <button type="submit" :class="buttonClass">{{ busy ? 'Đang lưu…' : 'Lưu thông tin' }}</button>
                    </fieldset>
                </form>
                <aside
                    class="grid content-start gap-4 rounded-xl border border-slate-200 bg-white p-5 text-sm dark:border-slate-700 dark:bg-slate-900"
                >
                    <div>
                        <p class="text-slate-500">Vai trò</p>
                        <p>{{ user.role === 'admin' ? 'Quản trị viên' : 'Thành viên' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">Trạng thái</p>
                        <p>{{ user.status === 'active' ? 'Hoạt động' : user.status === 'blocked' ? 'Đã khóa' : 'Tạm ngưng' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">Xác minh email</p>
                        <p>{{ user.email_verified_at ? date(user.email_verified_at) : 'Chưa xác minh' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">Ngày đăng ký</p>
                        <p>{{ date(user.created_at) }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">Đăng nhập gần nhất</p>
                        <p>{{ date(user.last_login_at) }}</p>
                        <p class="text-xs text-slate-500">{{ user.last_login_ip }}</p>
                    </div>
                    <button
                        v-if="user.role !== 'admin'"
                        type="button"
                        class="min-h-11 rounded-lg border border-slate-300 px-4 disabled:opacity-50 dark:border-slate-600"
                        :disabled="busy"
                        @click="toggleStatus"
                    >
                        {{ user.status === 'active' ? 'Khóa tài khoản' : 'Mở tài khoản' }}
                    </button>
                </aside>
            </div>
            <template v-else-if="tab === 'wallet'">
                <form class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900" @submit.prevent="adjustWallet">
                    <h2 class="mb-4 font-bold">Cộng / trừ tiền</h2>
                    <fieldset :disabled="busy" class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2 text-sm"
                            >Thao tác<select v-model="wallet.direction" :class="inputClass">
                                <option value="credit">Cộng tiền</option>
                                <option value="debit">Trừ tiền</option>
                            </select></label
                        >
                        <label class="grid gap-2 text-sm"
                            >Số tiền (đ)<input
                                v-model.number="wallet.amount"
                                type="number"
                                min="1"
                                max="100000000000"
                                step="1"
                                required
                                :class="inputClass"
                        /></label>
                        <label class="grid gap-2 text-sm sm:col-span-2"
                            >Lý do điều chỉnh<textarea v-model="wallet.description" :class="inputClass" rows="2" required maxlength="500"></textarea>
                        </label>
                        <button type="submit" :class="buttonClass">{{ busy ? 'Đang xử lý…' : 'Xác nhận điều chỉnh' }}</button>
                    </fieldset>
                </form>
                <section
                    class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                    aria-label="Lịch sử giao dịch ví"
                >
                    <h2 class="mb-4 font-bold">Lịch sử giao dịch ví</h2>
                    <p v-if="historyLoading" role="status" class="text-sm text-slate-500">Đang tải giao dịch…</p>
                    <button v-else-if="historyFailed" type="button" class="min-h-11 text-sm text-rose-600" @click="historyLoad(historyPage)">
                        Không tải được giao dịch. Thử lại.
                    </button>
                    <template v-else>
                        <div class="grid gap-3">
                            <article
                                v-for="item in transactions"
                                :key="item.id"
                                class="grid gap-1 rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-700"
                            >
                                <strong :class="item.direction === 'credit' ? 'text-emerald-600' : 'text-rose-600'"
                                    >{{ item.direction === 'credit' ? '+' : '-' }}{{ money(item.amount) }}</strong
                                >
                                <p>{{ item.description }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ money(item.balance_before) }} → {{ money(item.balance_after) }} · {{ date(item.created_at) }} · Admin #{{
                                        item.actor_id ?? 'Hệ thống'
                                    }}
                                </p>
                            </article>
                            <p v-if="!transactions.length" class="text-sm text-slate-500">Chưa có giao dịch.</p>
                        </div>
                        <div v-if="historyLastPage > 1" class="mt-4 flex items-center gap-3 text-sm">
                            <button
                                type="button"
                                class="min-h-11 disabled:opacity-40"
                                :disabled="historyPage <= 1"
                                @click="historyLoad(historyPage - 1)"
                            >
                                Trước</button
                            ><span>{{ historyPage }} / {{ historyLastPage }}</span
                            ><button
                                type="button"
                                class="min-h-11 disabled:opacity-40"
                                :disabled="historyPage >= historyLastPage"
                                @click="historyLoad(historyPage + 1)"
                            >
                                Sau
                            </button>
                        </div>
                    </template>
                </section>
            </template>
            <form
                v-else-if="tab === 'tools'"
                class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                @submit.prevent="saveTools"
            >
                <div>
                    <h2 class="font-bold">Cấp quyền sử dụng tool</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Chọn tool rồi lưu để tự cấp license vĩnh viễn trong dashboard của user. Bỏ chọn sẽ thu hồi license tự cấp và ngắt phiên đang
                        dùng. Tool đang tắt sẽ không xuất hiện trong API của user. Lưu lại quyền cũ để bổ sung license còn thiếu. Role user có tối đa
                        một license cho mỗi tool; role admin không giới hạn. License hiện có được giữ lại khi cấp quyền.
                    </p>
                </div>
                <p v-if="toolsLoading" role="status" class="text-sm text-slate-500">Đang tải danh sách tool…</p>
                <button v-else-if="toolsFailed" type="button" class="min-h-11 text-left text-sm text-rose-600" :disabled="busy" @click="loadTools">
                    Không tải được quyền tool. Bấm để thử lại.
                </button>
                <template v-else>
                    <label class="grid gap-2 text-sm"
                        >Tìm tool<input v-model="toolSearch" type="search" :class="inputClass" placeholder="Tên hoặc mã tool"
                    /></label>
                    <fieldset :disabled="busy" class="grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="tool in filteredTools"
                            :key="tool.id"
                            class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                        >
                            <input v-model="selectedTools" type="checkbox" :value="tool.id" class="mt-1 h-4 w-4 rounded" />
                            <img v-if="tool.image_url" :src="tool.image_url" alt="" class="h-12 w-12 shrink-0 rounded-lg object-cover" />
                            <span class="grid min-w-0 gap-1 text-sm">
                                <strong class="break-words">{{ tool.name }}</strong>
                                <span class="break-all text-xs text-slate-500 dark:text-slate-400"
                                    >{{ tool.product_code }} · {{ tool.latest_version || 'Chưa có version' }}</span
                                >
                                <span v-if="!tool.is_active" class="text-xs text-amber-700 dark:text-amber-400">Tool đang tắt</span>
                            </span>
                        </label>
                    </fieldset>
                    <p v-if="!filteredTools.length" class="text-sm text-slate-500">
                        {{ tools.length ? 'Không tìm thấy tool phù hợp.' : 'Chưa có sản phẩm/tool. Hãy tạo trong phần Sản phẩm / tool.' }}
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" :class="buttonClass" :disabled="busy || toolsLoadedFor !== String(user.id)">
                            {{ busy ? 'Đang lưu…' : 'Lưu quyền tool' }}
                        </button>
                        <span class="text-sm text-slate-500 dark:text-slate-400">Đã chọn {{ selectedTools.length }} tool</span>
                    </div>
                </template>
            </form>
            <form
                v-else-if="tab === 'password'"
                class="max-w-xl rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                @submit.prevent="resetPassword"
            >
                <h2 class="mb-2 font-bold">Đổi mật khẩu</h2>
                <p class="mb-4 text-sm text-slate-500">
                    Đặt mật khẩu mới cho tài khoản. Các phiên đăng nhập và token hiện có sẽ bị thu hồi; user nhận email thông báo, không kèm mật khẩu.
                </p>
                <fieldset :disabled="busy" class="grid gap-4">
                    <label class="grid gap-2 text-sm"
                        >Mật khẩu mới<input
                            v-model="password.password"
                            type="password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            :class="inputClass"
                    /></label>
                    <label class="grid gap-2 text-sm"
                        >Xác nhận mật khẩu mới<input
                            v-model="password.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            :class="inputClass"
                    /></label>
                    <p class="text-xs text-slate-500">Ít nhất 8 ký tự, có chữ thường, chữ hoa và số.</p>
                    <button type="submit" :class="buttonClass">{{ busy ? 'Đang xử lý…' : 'Đặt lại mật khẩu' }}</button>
                </fieldset>
            </form>
        </template>
    </section>
</template>
