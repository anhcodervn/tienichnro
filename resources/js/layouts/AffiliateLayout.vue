<script setup lang="ts">
import SecondaryPasswordDialog from '@/components/shared/SecondaryPasswordDialog.vue';
import { clientAffiliateService, type CollaboratorDashboardData } from '@/services/client-affiliate.service';
import { gameServiceSecondaryAuthService } from '@/services/game-service-secondary-auth.service';
import { useUserStore } from '@/stores/user.store';
import { clearGameServiceSecondaryGrant } from '@/utils/game-service-secondary-auth';
import { handleErrorResponse } from '@/utils/response';
import {
    BellRing,
    ChartNoAxesCombined,
    ChevronDown,
    HandCoins,
    History,
    Home,
    LayoutDashboard,
    ListChecks,
    LogOut,
    Menu,
    MessageCircle,
    WalletCards,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

const sidebarOpen = ref(false);
const summary = ref<CollaboratorDashboardData | null>(null);
const userStore = useUserStore();
const route = useRoute();
const ordersMenuOpen = ref(String(route.name ?? '') === 'collaborator.orders');
const secondaryConfigured = ref(false);
const secondaryUnlocking = ref(false);
const secondaryDialogOpen = ref(false);
let refreshTimer: number | null = null;

const routeName = computed(() => String(route.name ?? ''));
const currentOrderStatus = computed(() => String(route.query.status ?? 'pending'));
const orderMenuItems = [
    { status: 'pending', label: 'Đơn đang chờ', dotClass: 'bg-amber-500' },
    { status: 'processing', label: 'Đơn đang làm', dotClass: 'bg-sky-500' },
    { status: 'review', label: 'Đơn chờ duyệt', dotClass: 'bg-violet-500' },
    { status: 'completed', label: 'Đơn hoàn thành', dotClass: 'bg-emerald-500' },
    { status: 'failed', label: 'Đơn thất bại', dotClass: 'bg-rose-500' },
    { status: 'cancelled', label: 'Đơn đã hủy', dotClass: 'bg-slate-400' },
] as const;
const orderStatusCount = (status: (typeof orderMenuItems)[number]['status']): number => {
    if (!summary.value) return 0;

    if (status === 'pending' || status === 'processing' || status === 'review' || status === 'completed') {
        return summary.value.orders[status];
    }

    return 0;
};
const pageTitle = computed(() => {
    const titles: Record<string, string> = {
        'collaborator.dashboard': 'Dashboard công việc',
        'collaborator.orders': 'Quản lý đơn',
        'collaborator.chats': 'Chat đơn đã nhận',
        'collaborator.notifications': 'Thông báo công việc',
        'collaborator.revenue': 'Doanh thu công việc',
        'collaborator.withdrawal': 'Rút tiền công việc',
        'collaborator.wallet-history': 'Lịch sử ví công việc',
    };

    return titles[routeName.value] ?? 'Cộng tác viên';
});
const pageDescription = computed(() => {
    const descriptions: Record<string, string> = {
        'collaborator.dashboard': 'Theo dõi tiến độ đơn dịch vụ và tiền công của bạn',
        'collaborator.orders': 'Nhận đơn, xử lý, gửi duyệt và trao đổi với khách hàng',
        'collaborator.chats': 'Trao đổi với khách hàng và admin trong từng đơn đã nhận',
        'collaborator.notifications': 'Thông tin công việc mới nhất từ quản trị viên',
        'collaborator.revenue': 'Theo dõi tiền công, tiền treo và tiền đã kết toán',
        'collaborator.withdrawal': 'Rút tiền công việc đã được kết toán',
        'collaborator.wallet-history': 'Theo dõi tiền treo, tiền được rút và các khoản thu hồi theo đơn',
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
const stopSummaryRefresh = (): void => {
    if (refreshTimer === null) return;

    window.clearInterval(refreshTimer);
    refreshTimer = null;
};
const startSummaryRefresh = (): void => {
    stopSummaryRefresh();
    void loadSummary();
    refreshTimer = window.setInterval(loadSummary, 15000);
};
const lockSecondarySession = (): void => {
    clearGameServiceSecondaryGrant();
    secondaryDialogOpen.value = true;
};
const checkSecondaryPassword = async (): Promise<void> => {
    try {
        const status = await gameServiceSecondaryAuthService.status();
        secondaryConfigured.value = status.configured;

        if (!status.unlocked) {
            clearGameServiceSecondaryGrant();
        }
        startSummaryRefresh();
    } catch (error) {
        handleErrorResponse(error);
    }
};
const unlockSecondaryPassword = async (password: string): Promise<void> => {
    secondaryUnlocking.value = true;

    try {
        await gameServiceSecondaryAuthService.unlock(password);
        secondaryConfigured.value = true;
        secondaryDialogOpen.value = false;
        window.dispatchEvent(new Event('game-service-secondary-auth:unlocked'));
        startSummaryRefresh();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        secondaryUnlocking.value = false;
    }
};
const receiveSummary = (event: Event): void => {
    const detail = (event as CustomEvent<CollaboratorDashboardData>).detail;
    if (detail) summary.value = detail;
};
const closeSidebar = (): void => {
    sidebarOpen.value = false;
};
watch(routeName, (name) => {
    if (name === 'collaborator.orders') ordersMenuOpen.value = true;
});
const logout = async (): Promise<void> => {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    await fetch('/dang-xuat', { method: 'POST', credentials: 'include', headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } });
    clearGameServiceSecondaryGrant();
    window.location.href = '/dang-nhap';
};

onMounted(() => {
    window.addEventListener('collaborator:summary', receiveSummary);
    window.addEventListener('collaborator:refresh', loadSummary);
    window.addEventListener('game-service-secondary-auth:locked', lockSecondarySession);
    void checkSecondaryPassword();
});
onBeforeUnmount(() => {
    stopSummaryRefresh();
    window.removeEventListener('collaborator:summary', receiveSummary);
    window.removeEventListener('collaborator:refresh', loadSummary);
    window.removeEventListener('game-service-secondary-auth:locked', lockSecondarySession);
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
                <RouterLink to="/dashboard" class="flex items-center gap-3"
                    ><span class="grid size-11 place-items-center rounded-2xl bg-emerald-600 text-white"><HandCoins class="size-6" /></span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">CTV dịch vụ</p>
                        <p class="font-black text-slate-950">Work Center</p>
                    </div></RouterLink
                >
                <button class="p-2 lg:hidden" @click="closeSidebar"><X class="size-5" /></button>
            </header>
            <nav class="grid flex-1 content-start gap-2 overflow-y-auto p-4">
                <RouterLink
                    to="/dashboard"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'collaborator.dashboard' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><LayoutDashboard class="size-5" /> Dashboard</RouterLink
                >
                <div class="grid gap-1">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left font-bold transition"
                        :class="routeName === 'collaborator.orders' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'"
                        :aria-expanded="ordersMenuOpen"
                        aria-controls="collaborator-order-menu"
                        @click="ordersMenuOpen = !ordersMenuOpen"
                    >
                        <ListChecks class="size-5" />
                        <span class="flex-1">Quản lý đơn</span>
                        <span
                            v-if="summary?.orders.pending"
                            class="grid min-w-6 place-items-center rounded-full bg-rose-600 px-1.5 py-0.5 text-xs font-black text-white"
                            >{{ summary.orders.pending > 99 ? '99+' : summary.orders.pending }}</span
                        >
                        <ChevronDown class="size-4 transition-transform" :class="ordersMenuOpen ? 'rotate-180' : ''" />
                    </button>
                    <div v-show="ordersMenuOpen" id="collaborator-order-menu" class="grid gap-1 border-l-2 border-emerald-100 pl-3">
                        <RouterLink
                            v-for="item in orderMenuItems"
                            :key="item.status"
                            :to="{ name: 'collaborator.orders', query: { status: item.status } }"
                            class="flex min-h-10 items-center gap-2 rounded-lg px-3 text-sm font-bold transition"
                            :class="
                                routeName === 'collaborator.orders' && currentOrderStatus === item.status
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'
                            "
                            @click="closeSidebar"
                        >
                            <span class="size-2 shrink-0 rounded-full" :class="item.dotClass"></span>
                            <span class="flex-1">{{ item.label }}</span>
                            <span
                                v-if="orderStatusCount(item.status)"
                                class="min-w-5 rounded-full bg-white px-1.5 py-0.5 text-center text-[11px] font-black text-slate-600 shadow-sm"
                                >{{ orderStatusCount(item.status) > 99 ? '99+' : orderStatusCount(item.status) }}</span
                            >
                        </RouterLink>
                    </div>
                </div>
                <RouterLink
                    to="/dashboard/chat-don"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'collaborator.chats' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><MessageCircle class="size-5" /> Chat đơn đã nhận</RouterLink
                >
                <RouterLink
                    to="/dashboard/thong-bao"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'collaborator.notifications' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><BellRing class="size-5" /><span class="flex-1">Thông báo</span
                    ><span
                        v-if="summary?.unread_announcements"
                        class="grid min-w-6 place-items-center rounded-full bg-rose-600 px-1.5 py-0.5 text-xs font-black text-white"
                        >{{ summary.unread_announcements > 99 ? '99+' : summary.unread_announcements }}</span
                    ></RouterLink
                >
                <RouterLink
                    to="/dashboard/doanh-thu"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'collaborator.revenue' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><ChartNoAxesCombined class="size-5" /> Quản lý doanh thu</RouterLink
                >
                <RouterLink
                    to="/dashboard/rut-tien"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'collaborator.withdrawal' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><WalletCards class="size-5" /> Rút tiền</RouterLink
                >
                <RouterLink
                    to="/dashboard/lich-su-vi"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 font-bold transition"
                    :class="routeName === 'collaborator.wallet-history' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50'"
                    @click="closeSidebar"
                    ><History class="size-5" /> Lịch sử ví</RouterLink
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
        <SecondaryPasswordDialog
            :open="secondaryDialogOpen"
            :configured="secondaryConfigured"
            :loading="secondaryUnlocking"
            :personal="userStore.user?.role === 'ctv'"
            @close="secondaryDialogOpen = false"
            @submit="unlockSecondaryPassword"
        />
    </div>
</template>
