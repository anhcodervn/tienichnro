<script setup lang="ts">
import api from '@/config/axios';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { isAxiosError } from 'axios';
import { onMounted, ref } from 'vue';

type Provider = {
    id: number;
    name: string;
    driver: string;
    endpoint: string;
    priority: number;
    timeout: number;
    is_enabled: boolean;
    has_api_key: boolean;
};
const rows = ref<Provider[]>([]);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const saving = ref(false);
const opened = ref(false);
const editing = ref<Provider | null>(null);
const deleting = ref<Provider | null>(null);
const error = ref('');
const notice = ref('');
const defaults = () => ({
    name: '',
    driver: 'nro_giaicaptcha',
    endpoint: 'https://nro.giaicaptcha.vn/solve',
    priority: 10,
    timeout: 15,
    is_enabled: false,
    api_key: '',
    clear_api_key: false,
});
const form = ref(defaults());
const providerOptions: Record<string, { label: string; endpoint: string }> = {
    nro_giaicaptcha: { label: 'nro.giaicaptcha', endpoint: 'https://nro.giaicaptcha.vn/solve' },
    plusdino: { label: 'PlusDino', endpoint: 'http://captcha.plusdino.com/SolveCaptcha/API.php' },
    phamgiang: { label: 'api.phamgiang.net', endpoint: 'https://api.phamgiang.net/captcha/nro' },
};
const fail = (err: unknown) => {
    error.value = isAxiosError(err)
        ? Object.values(err.response?.data?.errors || {})
              .flat()
              .join(' ') ||
          err.response?.data?.message ||
          'Không thể xử lý yêu cầu.'
        : 'Không thể xử lý yêu cầu.';
};
const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get('/api/admin-api/captcha/providers', { params: { page: page.value } });
        rows.value = data.data;
        lastPage.value = data.last_page;
    } catch (err) {
        fail(err);
    } finally {
        loading.value = false;
    }
};
const open = (provider: Provider | null = null) => {
    editing.value = provider;
    form.value = provider ? { ...defaults(), ...provider, api_key: '' } : defaults();
    error.value = '';
    opened.value = true;
};
const changeDriver = () => {
    form.value.endpoint = providerOptions[form.value.driver].endpoint;
};
const selectPrimary = async (provider: Provider) => {
    if (saving.value) return;
    saving.value = true;
    error.value = '';
    try {
        await api.put('/api/admin-api/captcha/providers/' + provider.id, { ...provider, is_enabled: true, api_key: '' });
        notice.value = 'Đã chọn ' + provider.name + ' làm nguồn giải chính.';
        page.value = 1;
        await load();
    } catch (err) {
        fail(err);
    } finally {
        saving.value = false;
    }
};
const save = async () => {
    if (saving.value) return;
    saving.value = true;
    error.value = '';
    try {
        if (editing.value) await api.put('/api/admin-api/captcha/providers/' + editing.value.id, form.value);
        else await api.post('/api/admin-api/captcha/providers', form.value);
        opened.value = false;
        form.value = defaults();
        notice.value = 'Đã lưu cấu hình nguồn.';
        await load();
    } catch (err) {
        fail(err);
    } finally {
        saving.value = false;
    }
};
const remove = async () => {
    if (!deleting.value || saving.value) return;
    saving.value = true;
    try {
        await api.delete('/api/admin-api/captcha/providers/' + deleting.value.id);
        deleting.value = null;
        if (rows.value.length === 1 && page.value > 1) page.value--;
        notice.value = 'Đã xóa nguồn. Lịch sử giải vẫn được giữ lại.';
        await load();
    } catch (err) {
        fail(err);
    } finally {
        saving.value = false;
    }
};
onMounted(load);
</script>

<template>
    <section class="grid gap-5 text-slate-900 dark:text-slate-100">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Cấu hình nguồn giải captcha</h1>
                <p class="mt-1 text-sm text-slate-500">Chọn duy nhất một nguồn chính để xử lý các lượt giải captcha.</p>
            </div>
            <button type="button" class="rounded-[10px] bg-emerald-700 px-4 py-2 text-white" @click="open()">Thêm nguồn</button>
        </div>
        <p class="rounded-[10px] border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
            Mỗi lượt giải chỉ gọi nguồn chính. Chọn nguồn mới sẽ thay thế nguồn đang dùng. Nguồn lỗi sẽ trả lỗi, không tự chuyển sang nguồn khác và
            không trừ token. Mỗi lượt giải thành công trừ 1 token.
        </p>
        <p v-if="error" role="alert" class="text-rose-600">{{ error }}</p>
        <p v-if="notice" role="status" class="text-emerald-600">{{ notice }}</p>
        <div class="overflow-x-auto rounded-[10px] border bg-white dark:border-slate-700 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="p-4">Nguồn giải</th>
                        <th class="p-4">Kết nối</th>
                        <th class="p-4">Trạng thái</th>
                        <th class="p-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id" class="border-t dark:border-slate-700">
                        <td class="p-4">
                            <p class="font-semibold">{{ row.name }}</p>
                            <p class="mt-1 text-slate-500">{{ providerOptions[row.driver]?.label || row.driver }}</p>
                        </td>
                        <td class="p-4">
                            <p class="max-w-md break-all">{{ row.endpoint }}</p>
                            <p class="mt-1 text-slate-500">
                                Timeout {{ row.timeout }} giây · {{ row.has_api_key ? 'Đã lưu khóa API' : 'Chưa có khóa API' }}
                            </p>
                        </td>
                        <td class="p-4">
                            <span
                                class="rounded-[10px] px-3 py-1"
                                :class="
                                    row.is_enabled
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                "
                                >{{ row.is_enabled ? 'Nguồn chính' : 'Chưa chọn' }}</span
                            >
                        </td>
                        <td class="p-4">
                            <div class="flex gap-2">
                                <button
                                    v-if="!row.is_enabled"
                                    type="button"
                                    :disabled="saving"
                                    class="rounded-[10px] border px-3 py-2 text-emerald-700 disabled:opacity-50"
                                    @click="selectPrimary(row)"
                                >
                                    Đặt làm nguồn chính
                                </button>
                                <button type="button" class="rounded-[10px] border px-3 py-2" @click="open(row)">Chỉnh sửa</button
                                ><button
                                    type="button"
                                    class="rounded-[10px] border px-3 py-2 text-rose-600"
                                    @click="
                                        deleting = row;
                                        error = '';
                                    "
                                >
                                    Xóa
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="loading || !rows.length">
                        <td colspan="4" class="p-5 text-center text-slate-500">
                            {{ loading ? 'Đang tải…' : 'Chưa có nguồn giải. Thêm nguồn để bắt đầu.' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="flex items-center gap-3">
            <button
                type="button"
                :disabled="page <= 1 || loading"
                class="rounded-[10px] border px-3 py-2 disabled:opacity-40"
                @click="
                    page--;
                    load();
                "
            >
                Trước</button
            ><span>{{ page }} / {{ lastPage }}</span
            ><button
                type="button"
                :disabled="page >= lastPage || loading"
                class="rounded-[10px] border px-3 py-2 disabled:opacity-40"
                @click="
                    page++;
                    load();
                "
            >
                Sau
            </button>
        </div>
        <Dialog :open="opened" class="relative z-50" @close="!saving && (opened = false)">
            <div class="fixed inset-0 bg-black/50" aria-hidden="true"></div>
            <div class="fixed inset-0 overflow-y-auto p-5">
                <div class="flex min-h-full items-center justify-center">
                    <DialogPanel class="w-full max-w-xl rounded-[10px] bg-white p-6 text-slate-900 dark:bg-slate-900 dark:text-slate-100">
                        <DialogTitle class="text-xl font-bold">{{ editing ? 'Chỉnh sửa nguồn' : 'Thêm nguồn giải' }}</DialogTitle>
                        <form class="mt-5 grid gap-4" @submit.prevent="save">
                            <label class="grid gap-1 text-sm"
                                >Tên nguồn<input v-model="form.name" required maxlength="80" class="rounded-[10px] border p-2 dark:bg-slate-800"
                            /></label>
                            <label class="grid gap-1 text-sm"
                                >Provider<select v-model="form.driver" class="rounded-[10px] border p-2 dark:bg-slate-800" @change="changeDriver">
                                    <option value="nro_giaicaptcha">nro.giaicaptcha (JSON)</option>
                                    <option value="plusdino">PlusDino (form)</option>
                                    <option value="phamgiang">api.phamgiang.net (form-data)</option>
                                </select></label
                            >
                            <label class="grid gap-1 text-sm"
                                >URL giải<input
                                    v-model="form.endpoint"
                                    type="url"
                                    required
                                    maxlength="2048"
                                    class="rounded-[10px] border p-2 dark:bg-slate-800"
                                /><span class="text-xs text-slate-500">Nhập URL không chứa token. Khóa được nhập ở ô riêng bên dưới.</span></label
                            >
                            <label class="grid gap-1 text-sm"
                                >{{ form.driver === 'nro_giaicaptcha' ? 'Khóa API (Bearer, nếu có)' : 'Token ' + providerOptions[form.driver].label
                                }}<input
                                    v-model="form.api_key"
                                    type="password"
                                    autocomplete="new-password"
                                    maxlength="2048"
                                    :disabled="form.clear_api_key"
                                    :placeholder="editing?.has_api_key ? 'Đã lưu khóa — để trống để giữ nguyên' : 'Nhập khóa API'"
                                    class="rounded-[10px] border p-2 dark:bg-slate-800"
                            /></label>
                            <label v-if="editing?.has_api_key" class="flex items-center gap-2 text-sm"
                                ><input v-model="form.clear_api_key" type="checkbox" />Xóa khóa API đã lưu</label
                            >
                            <div class="grid grid-cols-2 gap-4">
                                <label class="grid gap-1 text-sm"
                                    >Timeout (giây)<input
                                        v-model.number="form.timeout"
                                        type="number"
                                        min="1"
                                        max="30"
                                        required
                                        class="rounded-[10px] border p-2 dark:bg-slate-800"
                                /></label>
                            </div>
                            <label class="flex items-center gap-2 text-sm"
                                ><input v-model="form.is_enabled" type="checkbox" />Dùng làm nguồn chính (thay thế nguồn hiện tại)</label
                            >
                            <p v-if="error" role="alert" class="text-sm text-rose-600">{{ error }}</p>
                            <div class="flex gap-3">
                                <button
                                    type="submit"
                                    :disabled="saving"
                                    class="rounded-[10px] bg-emerald-700 px-4 py-2 text-white disabled:opacity-50"
                                >
                                    {{ saving ? 'Đang lưu…' : 'Lưu nguồn' }}</button
                                ><button type="button" :disabled="saving" class="rounded-[10px] border px-4 py-2" @click="opened = false">
                                    Đóng
                                </button>
                            </div>
                        </form>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
        <Dialog :open="!!deleting" class="relative z-50" @close="!saving && (deleting = null)">
            <div class="fixed inset-0 bg-black/50" aria-hidden="true"></div>
            <div class="fixed inset-0 flex items-center justify-center p-5">
                <DialogPanel class="grid w-full max-w-md gap-4 rounded-[10px] bg-white p-6 text-slate-900 dark:bg-slate-900 dark:text-slate-100"
                    ><DialogTitle class="text-xl font-bold">Xóa nguồn {{ deleting?.name }}?</DialogTitle>
                    <p>Lịch sử các lượt giải qua nguồn này vẫn được giữ lại.</p>
                    <p v-if="error" role="alert" class="text-rose-600">{{ error }}</p>
                    <div class="flex gap-3">
                        <button type="button" :disabled="saving" class="rounded-[10px] bg-rose-700 px-4 py-2 text-white" @click="remove">
                            Xóa nguồn</button
                        ><button type="button" :disabled="saving" class="rounded-[10px] border px-4 py-2" @click="deleting = null">Hủy</button>
                    </div></DialogPanel
                >
            </div>
        </Dialog>
    </section>
</template>
