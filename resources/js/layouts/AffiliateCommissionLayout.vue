<script setup lang="ts">
import { clientAffiliateService } from '@/services/client-affiliate.service';
import { useUserStore } from '@/stores/user.store';
import { BadgePercent, BellRing, HandCoins, Home, LayoutDashboard, LogOut, Menu, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

const sidebarOpen = ref(false);
const unreadAnnouncements = ref(0);
const userStore = useUserStore();
const route = useRoute();
let refreshTimer: number | null = null;

const routeName = computed(() => String(route.name ?? ''));
const pageTitle = computed(() => {
    const titles: Record<string, string> = {
        'affiliate.dashboard': 'Dashboard hoa hồng',
        'affiliate.notifications': 'Thông báo Affiliate',
        'affiliate.rates': 'Bảng giá chiết khấu',
    };

    return titles[routeName.value] ?? 'Affiliate Center';
});
const pageDescription = computed(() => {
    const descriptions: Record<string, string> = {
        'affiliate.dashboard': 'Theo dõi giới thiệu, hoa hồng và số dư Affiliate',
        'affiliate.notifications': 'Thông báo chính sách và chương trình dành cho Affiliate',
        'affiliate.rates': 'Mức hoa hồng giới thiệu theo từng gói nạp',
    };

    return descriptions[routeName.value] ?? 'Quản lý hoạt động giới thiệu và hoa hồng';
});

const loadUnreadAnnouncements = async (): Promise<void> => {
    try {
        unreadAnnouncements.value = (await clientAffiliateService.home()).unread_count;
    } catch (error) {
        console.error('Không thể tải số thông báo Affiliate chưa đọc.', error);
    }
};
const closeSidebar = (): void => {
    sidebarOpen.value = false;
};
const logout = async (): Promise<void> => {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    await fetch('/dang-xuat', { method: 'POST', credentials: 'include', headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } });
    window.location.href = '/dang-nhap';
};

onMounted(() => {
    void loadUnreadAnnouncements();
    refreshTimer = window.setInterval(loadUnreadAnnouncements, 30000);
    window.addEventListener('affiliate:refresh', loadUnreadAnnouncements);
});
onBeforeUnmount(() => {
    if (refreshTimer !== null) window.clearInterval(refreshTimer);
    window.removeEventListener('affiliate:refresh', loadUnreadAnnouncements);
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 lg:grid lg:grid-cols-[270px_minmax(0,1fr)]">
        <button v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden" aria-label="Đóng menu" @click="closeSidebar" />
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[270px] flex-col border-r border-slate-200 bg-white transition-transform lg:sticky lg:top-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <header class="flex min-h-20 items-center justify-between border-b border-slate-200 px-5">
                <RouterLink to="/cong-tac-vien" class="flex items-center gap-3"
                    ><span class="grid size-11 place-items-center rounded-2xl bg-violet-600 text-white"><HandCoins class="size-6" /></span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-violet-700">Affiliate</p>
                        <p class="font-black text-slate-950">Commission Center</p>
                    </div></RouterLink
                >
                <button class="p-2 lg:hidden" @click="closeSidebar"><X class="size-5" /></button>
            </header>
            <nav class="grid flex-1 content-start gap-2 p-4">
                <RouterLink
                    to="/cong-tac-vien"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'affiliate.dashboard' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><LayoutDashboard class="size-5" /> Tổng quan hoa hồng</RouterLink
                >
                <RouterLink
                    to="/cong-tac-vien/thong-bao"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'affiliate.notifications' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><BellRing class="size-5" /><span class="flex-1">Thông báo</span
                    ><span
                        v-if="unreadAnnouncements"
                        class="grid min-w-6 place-items-center rounded-full bg-rose-600 px-1.5 py-0.5 text-xs font-black text-white"
                        >{{ unreadAnnouncements > 99 ? '99+' : unreadAnnouncements }}</span
                    ></RouterLink
                >
                <RouterLink
                    to="/cong-tac-vien/bang-gia-chiet-khau"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'affiliate.rates' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><BadgePercent class="size-5" /> Bảng giá chiết khấu</RouterLink
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
                    <p class="font-black text-slate-950">{{ pageTitle }}</p>
                    <p class="text-xs text-slate-500">{{ pageDescription }}</p>
                </div>
            </header>
            <RouterView />
        </section>
    </div>
</template>
