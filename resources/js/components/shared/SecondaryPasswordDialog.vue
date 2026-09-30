<script setup lang="ts">
import { KeyRound, LoaderCircle, LockKeyhole, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        configured: boolean;
        loading?: boolean;
        blocking?: boolean;
        personal?: boolean;
    }>(),
    {
        loading: false,
        blocking: false,
        personal: false,
    },
);

const emit = defineEmits<{
    submit: [password: string];
    close: [];
}>();

const password = ref('');

watch(
    () => props.open,
    (open) => {
        if (!open) password.value = '';
    },
);

const submit = (): void => {
    if (password.value === '' || props.loading) return;
    emit('submit', password.value);
};
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-[150] grid place-items-center bg-slate-950/65 p-4 backdrop-blur-sm" @click.self="!blocking && emit('close')">
        <section class="w-full max-w-md overflow-hidden rounded-[11px] border border-slate-200 bg-white shadow-2xl" role="dialog" aria-modal="true">
            <header class="flex items-start justify-between gap-4 border-b border-slate-200 bg-slate-50 px-5 py-4">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-[9px] bg-indigo-100 text-indigo-700">
                        <LockKeyhole class="size-5" />
                    </span>
                    <div>
                        <h2 class="font-black text-slate-950">Xác thực mật khẩu C2</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Bảo vệ thông tin tài khoản khách hàng và thao tác tài chính.</p>
                    </div>
                </div>
                <button
                    v-if="!blocking"
                    type="button"
                    class="grid size-9 place-items-center rounded-[8px] border border-slate-200 bg-white text-slate-500 hover:text-slate-950"
                    aria-label="Đóng"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </button>
            </header>

            <form v-if="configured" class="grid gap-4 p-5" @submit.prevent="submit">
                <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                    <span>Mật khẩu C2</span>
                    <input
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        autofocus
                        required
                        maxlength="255"
                        class="min-h-12 rounded-[9px] border-2 border-slate-300 bg-slate-50 px-3 outline-none transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                        placeholder="Nhập mật khẩu C2"
                    />
                </label>
                <button
                    type="submit"
                    :disabled="loading || password === ''"
                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[9px] bg-indigo-600 px-4 font-bold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <LoaderCircle v-if="loading" class="size-4 animate-spin" />
                    <KeyRound v-else class="size-4" />
                    {{ loading ? 'Đang xác thực...' : 'Mở khóa phiên làm việc' }}
                </button>
                <p class="text-center text-xs leading-5 text-slate-500">Phiên mở khóa có thời hạn và sẽ tự khóa lại khi hết hạn.</p>
            </form>

            <div v-else class="grid gap-4 p-5">
                <div class="rounded-[9px] border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800">
                    <template v-if="personal">Tài khoản CTV chưa được quản trị viên cấu hình mật khẩu C2 riêng.</template>
                    <template v-else>Hệ thống chưa cấu hình mật khẩu C2 global dành cho admin. Vui lòng kiểm tra cấu hình máy chủ.</template>
                </div>
                <a v-if="blocking" href="/" class="inline-flex min-h-11 items-center justify-center rounded-[9px] bg-slate-950 px-4 font-bold text-white">
                    Về trang chính
                </a>
            </div>
        </section>
    </div>
</template>
