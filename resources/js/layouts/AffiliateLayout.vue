<script setup lang="ts">
import { useUserStore } from '@/stores/user.store';
import { HandCoins, Home, LogOut, Menu, WalletCards, X } from 'lucide-vue-next';
import { ref } from 'vue';

const sidebarOpen = ref(false);
const userStore = useUserStore();

const logout = async (): Promise<void> => {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    await fetch('/dang-xuat', { method: 'POST', credentials: 'include', headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } });
    window.location.href = '/dang-nhap';
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 lg:grid lg:grid-cols-[270px_minmax(0,1fr)]">
        <button v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden" aria-label="Đóng menu" @click="sidebarOpen = false" />
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[270px] flex-col border-r border-slate-200 bg-white transition-transform lg:sticky lg:top-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <header class="flex min-h-20 items-center justify-between border-b border-slate-200 px-5">
                <RouterLink to="/cong-tac-vien" class="flex items-center gap-3"
                    ><span class="grid size-11 place-items-center rounded-2xl bg-emerald-600 text-white"><HandCoins class="size-6" /></span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Affiliate</p>
                        <p class="font-black text-slate-950">Partner Center</p>
                    </div></RouterLink
                >
                <button class="p-2 lg:hidden" @click="sidebarOpen = false"><X class="size-5" /></button>
            </header>
            <nav class="grid flex-1 content-start gap-2 p-4">
                <RouterLink to="/cong-tac-vien" class="flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3 font-bold text-emerald-700"
                    ><WalletCards class="size-5" /> Tổng quan hoa hồng</RouterLink
                >
                <a href="/" class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold text-slate-600 hover:bg-slate-50"
                    ><Home class="size-5" /> Về trang chính</a
                >
            </nav>
            <div class="border-t border-slate-200 p-4">
                <p class="truncate px-2 text-sm font-bold">{{ userStore.displayName }}</p>
                <button
                    type="button"
                    class="mt-3 flex w-full items-center gap-3 rounded-xl px-4 py-3 font-bold text-rose-600 hover:bg-rose-50"
                    @click="logout"
                >
                    <LogOut class="size-5" /> Đăng xuất
                </button>
            </div>
        </aside>
        <section class="min-w-0">
            <header class="sticky top-0 z-30 flex min-h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur lg:px-6">
                <button class="rounded-xl border border-slate-200 p-2 lg:hidden" @click="sidebarOpen = true"><Menu class="size-5" /></button>
                <div>
                    <p class="font-black text-slate-950">Dashboard cộng tác viên</p>
                    <p class="text-xs text-slate-500">Hoa hồng được duyệt sau 7 ngày an toàn</p>
                </div>
            </header>
            <RouterView />
        </section>
    </div>
</template>
