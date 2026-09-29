<script setup lang="ts">
import { clientAffiliateService, type CollaboratorDashboardData } from '@/services/client-affiliate.service';
import { useUserStore } from '@/stores/user.store';
import { BellRing, ChartNoAxesCombined, HandCoins, Home, LayoutDashboard, ListChecks, LogOut, Menu, WalletCards, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

const sidebarOpen = ref(false);
const summary = ref<CollaboratorDashboardData | null>(null);
const userStore = useUserStore();
const route = useRoute();
let refreshTimer: number | null = null;

const routeName = computed(() => String(route.name ?? ''));
const pageTitle = computed(() => {
    const titles: Record<string, string> = {
        'affiliate.collaborator.dashboard': 'Dashboard cộng tác viên',
        'affiliate.game-service-orders': 'Quản lý đơn',
        'affiliate.notifications': 'Thông báo',
        'affiliate.revenue': 'Quản lý doanh thu',
        'affiliate.withdrawal': 'Rút tiền',
        'affiliate.dashboard': 'Tổng quan Affiliate',
        'affiliate.rates': 'Bảng giá chiết khấu',
    };

    return titles[routeName.value] ?? 'Cộng tác viên';
});
const pageDescription = computed(() => {
    const descriptions: Record<string, string> = {
        'affiliate.collaborator.dashboard': 'Theo dõi tiến độ đơn dịch vụ và thu nhập của bạn',
        'affiliate.game-service-orders': 'Nhận đơn, xử lý, gửi duyệt và trao đổi với khách hàng',
        'affiliate.notifications': 'Thông tin và cập nhật mới nhất từ quản trị viên',
        'affiliate.revenue': 'Theo dõi doanh thu, tiền treo và tiền đã kết toán',
        'affiliate.withdrawal': 'Tạo và theo dõi yêu cầu rút tiền về tài khoản',
    };

    return descriptions[routeName.value] ?? 'Trung tâm quản lý dành cho cộng tác viên';
});

const loadSummary = async (): Promise<void> => {
    try {
        summary.value = await clientAffiliateService.collaboratorDashboard();
    } catch (error) {
        console.error('Không thể tải thống kê cộng tác viên.', error);
    }
};
const receiveSummary = (event: Event): void => {
    const detail = (event as CustomEvent<CollaboratorDashboardData>).detail;
    if (detail) summary.value = detail;
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
    void loadSummary();
    refreshTimer = window.setInterval(loadSummary, 15000);
    window.addEventListener('collaborator:summary', receiveSummary);
    window.addEventListener('collaborator:refresh', loadSummary);
});
onBeforeUnmount(() => {
    if (refreshTimer !== null) window.clearInterval(refreshTimer);
    window.removeEventListener('collaborator:summary', receiveSummary);
    window.removeEventListener('collaborator:refresh', loadSummary);
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
                    ><span class="grid size-11 place-items-center rounded-2xl bg-emerald-600 text-white"><HandCoins class="size-6" /></span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">CTV dịch vụ</p>
                        <p class="font-black text-slate-950">Partner Center</p>
                    </div></RouterLink
                >
                <button class="p-2 lg:hidden" @click="closeSidebar"><X class="size-5" /></button>
            </header>
            <nav class="grid flex-1 content-start gap-2 p-4">
                <RouterLink
                    to="/cong-tac-vien"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'affiliate.collaborator.dashboard' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><LayoutDashboard class="size-5" /> Dashboard</RouterLink
                >
                <RouterLink
                    to="/cong-tac-vien/don-dich-vu"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'affiliate.game-service-orders' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><ListChecks class="size-5" /><span class="flex-1">Quản lý đơn</span
                    ><span
                        v-if="summary?.orders.pending"
                        class="grid min-w-6 place-items-center rounded-full bg-rose-600 px-1.5 py-0.5 text-xs font-black text-white"
                        >{{ summary.orders.pending > 99 ? '99+' : summary.orders.pending }}</span
                    ></RouterLink
                >
                <RouterLink
                    to="/cong-tac-vien/thong-bao"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'affiliate.notifications' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><BellRing class="size-5" /><span class="flex-1">Thông báo</span
                    ><span
                        v-if="summary?.unread_announcements"
                        class="grid min-w-6 place-items-center rounded-full bg-rose-600 px-1.5 py-0.5 text-xs font-black text-white"
                        >{{ summary.unread_announcements > 99 ? '99+' : summary.unread_announcements }}</span
                    ></RouterLink
                >
                <RouterLink
                    to="/cong-tac-vien/doanh-thu"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'affiliate.revenue' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><ChartNoAxesCombined class="size-5" /> Quản lý doanh thu</RouterLink
                >
                <RouterLink
                    to="/cong-tac-vien/rut-tien"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'affiliate.withdrawal' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><WalletCards class="size-5" /> Rút tiền</RouterLink
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
