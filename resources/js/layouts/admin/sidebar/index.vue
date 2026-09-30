<script setup lang="ts">
import { useSystemSetting } from '@/composables/useSystemSetting';
import { adminGameServiceService } from '@/services/admin-game-service.service';
import { useSupportStore } from '@/stores/support.store';
import { useUserStore } from '@/stores/user.store';
import { ChevronRight, ShieldCheck, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { adminMenuGroups, type AdminMenuGroup } from './navigation';

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const route = useRoute();
const supportStore = useSupportStore();
const userStore = useUserStore();
const { settings, fetchSettings } = useSystemSetting();
const sidebarLogo = computed(() => settings.value.dark_logo || settings.value.light_logo);
const isPlatformAdmin = computed(() => userStore.user?.capabilities?.platform_admin === true);
const isMultiSiteActive = computed(() => userStore.user?.capabilities?.multi_site === true);
const reviewOrderCount = ref(0);
let reviewCountTimer: number | undefined;
const visibleMenuGroups = computed(() =>
    adminMenuGroups
        .filter(
            (group) =>
                (!group.platformOnly || isPlatformAdmin.value) &&
                (!group.childOnly || !isPlatformAdmin.value) &&
                (!group.tenancyOnly || isMultiSiteActive.value),
        )
        .map((group) => ({
            ...group,
            children: group.children?.filter(
                (child) =>
                    (!child.platformOnly || isPlatformAdmin.value) &&
                    (!child.childOnly || !isPlatformAdmin.value) &&
                    (!child.tenancyOnly || isMultiSiteActive.value),
            ),
        }))
        .filter((group) => group.href || (group.children?.length ?? 0) > 0),
);

const loadReviewOrderCount = async (): Promise<void> => {
    if (!isPlatformAdmin.value) {
        reviewOrderCount.value = 0;
        return;
    }

    try {
        reviewOrderCount.value = await adminGameServiceService.reviewOrderCount();
    } catch {
        return;
    }
};

const syncReviewOrderCount = (event: Event): void => {
    const count = Number((event as CustomEvent<number>).detail);
    if (Number.isFinite(count) && count >= 0) {
        reviewOrderCount.value = count;
    }
};

onMounted(() => {
    void fetchSettings().catch(() => {});
    void loadReviewOrderCount();
    window.addEventListener('game-service-order-review-count', syncReviewOrderCount);
    reviewCountTimer = window.setInterval(() => void loadReviewOrderCount(), 30_000);
});

onBeforeUnmount(() => {
    window.removeEventListener('game-service-order-review-count', syncReviewOrderCount);
    if (reviewCountTimer !== undefined) {
        window.clearInterval(reviewCountTimer);
    }
});

const findBestMatchingChildHref = (hrefs: string[], currentPath: string): string | null => {
    return (
        [...hrefs].sort((left, right) => right.length - left.length).find((href) => currentPath === href || currentPath.startsWith(`${href}/`)) ??
        null
    );
};

const expandedGroups = reactive<Record<string, boolean>>({
    users: false,
    products: false,
    discounts: false,
    settings: false,
});

const isGroupActive = (group: AdminMenuGroup): boolean => {
    if (group.href) {
        return route.path === group.href;
    }

    const childHrefs = group.children?.map((child) => child.href) ?? [];

    return findBestMatchingChildHref(childHrefs, route.path) !== null;
};

const isChildActive = (href: string): boolean => {
    const menuChildHrefs = visibleMenuGroups.value.flatMap((group) => group.children?.map((child) => child.href) ?? []);

    return findBestMatchingChildHref(menuChildHrefs, route.path) === href;
};

const toggleGroup = (groupKey: string): void => {
    expandedGroups[groupKey] = !expandedGroups[groupKey];
};

const closeSidebarOnMobile = (): void => {
    if (window.innerWidth < 1024) {
        emit('close');
    }
};

watch(
    () => route.path,
    () => {
        for (const group of visibleMenuGroups.value) {
            if (group.children) {
                expandedGroups[group.key] = isGroupActive(group);
            }
        }
    },
    { immediate: true },
);

watch(isPlatformAdmin, (hasPlatformAccess) => {
    if (hasPlatformAccess) {
        void loadReviewOrderCount();
    } else {
        reviewOrderCount.value = 0;
    }
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="-translate-x-full opacity-0"
        enter-to-class="translate-x-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-x-0 opacity-100"
        leave-to-class="-translate-x-full opacity-0"
    >
        <aside
            v-if="props.isOpen"
            class="fixed inset-y-0 left-0 z-50 flex w-[290px] flex-col border-r border-slate-200 bg-white text-slate-700 shadow-[0_24px_80px_rgba(15,23,42,0.14)] lg:sticky lg:top-0 lg:shadow-none"
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                <RouterLink to="/admin" class="flex items-center gap-3" @click="closeSidebarOnMobile">
                    <img
                        v-if="sidebarLogo"
                        :src="sidebarLogo"
                        :alt="settings.site_name || 'Nạp Carot'"
                        class="h-auto w-24 shrink-0 object-contain object-left"
                    />
                    <div
                        v-else
                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-400 text-white shadow-[0_12px_28px_rgba(6,182,212,0.24)]"
                    >
                        <ShieldCheck class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-blue-600">
                            {{ settings.site_name || 'Nạp Carot' }}
                        </p>
                        <h1 class="text-lg font-black tracking-tight text-slate-950">Admin Control</h1>
                    </div>
                </RouterLink>

                <button
                    type="button"
                    class="rounded-2xl border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 lg:hidden"
                    @click="emit('close')"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-4 py-5">
                <div class="mb-4 px-2">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Điều hướng</p>
                </div>

                <div class="space-y-2">
                    <template v-for="group in visibleMenuGroups" :key="group.key">
                        <RouterLink
                            v-if="group.href"
                            :to="group.href"
                            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold transition"
                            :class="
                                isGroupActive(group)
                                    ? 'bg-blue-600 text-white shadow-[0_12px_24px_rgba(37,99,235,0.2)]'
                                    : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'
                            "
                            @click="closeSidebarOnMobile"
                        >
                            <component :is="group.icon" class="h-5 w-5" />
                            <span class="flex-1">{{ group.label }}</span>
                            <span
                                v-if="group.badge === 'support' && supportStore.adminUnread > 0"
                                class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full bg-red-600 px-1.5 text-[11px] font-black tabular-nums text-white ring-2"
                                :class="isGroupActive(group) ? 'ring-white/30' : 'ring-red-100'"
                            >
                                {{ supportStore.adminUnread > 99 ? '99+' : supportStore.adminUnread }}
                            </span>
                        </RouterLink>

                        <div v-else class="rounded-[1.35rem] border border-slate-200 bg-slate-50/80">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-semibold transition"
                                :class="isGroupActive(group) ? 'text-blue-700' : 'text-slate-600 hover:bg-blue-50 hover:text-blue-700'"
                                @click="toggleGroup(group.key)"
                            >
                                <component :is="group.icon" class="h-5 w-5" />
                                <span class="flex-1">{{ group.label }}</span>
                                <span
                                    v-if="group.badge === 'game-service-reviews' && reviewOrderCount > 0"
                                    class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full bg-red-600 px-1.5 text-[11px] font-black tabular-nums text-white ring-2 ring-red-100"
                                    aria-label="Số đơn chờ duyệt hoàn thành"
                                >
                                    {{ reviewOrderCount > 99 ? '99+' : reviewOrderCount }}
                                </span>
                                <ChevronRight
                                    class="h-4 w-4 transition"
                                    :class="expandedGroups[group.key] ? 'rotate-90 text-blue-600' : 'text-slate-400'"
                                />
                            </button>

                            <Transition
                                enter-active-class="transition-all duration-200 ease-out"
                                enter-from-class="max-h-0 opacity-0"
                                enter-to-class="max-h-96 opacity-100"
                                leave-active-class="transition-all duration-150 ease-in"
                                leave-from-class="max-h-96 opacity-100"
                                leave-to-class="max-h-0 opacity-0"
                            >
                                <div v-if="expandedGroups[group.key]" class="overflow-hidden px-3 pb-3">
                                    <RouterLink
                                        v-for="child in group.children"
                                        :key="child.href"
                                        :to="child.href"
                                        class="mt-1 flex items-center gap-3 rounded-2xl px-4 py-2.5 text-sm transition"
                                        :class="
                                            isChildActive(child.href)
                                                ? 'bg-blue-50 font-semibold text-blue-700 ring-1 ring-blue-100'
                                                : 'text-slate-500 hover:bg-white hover:text-blue-700'
                                        "
                                        @click="closeSidebarOnMobile"
                                    >
                                        <span class="bg-current/70 h-1.5 w-1.5 rounded-full" />
                                        <span class="flex-1">{{ child.label }}</span>
                                        <span
                                            v-if="child.badge === 'game-service-reviews' && reviewOrderCount > 0"
                                            class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full bg-red-600 px-1.5 text-[11px] font-black tabular-nums text-white ring-2"
                                            :class="isChildActive(child.href) ? 'ring-blue-100' : 'ring-red-100'"
                                            aria-label="Số đơn chờ duyệt hoàn thành"
                                        >
                                            {{ reviewOrderCount > 99 ? '99+' : reviewOrderCount }}
                                        </span>
                                    </RouterLink>
                                </div>
                            </Transition>
                        </div>
                    </template>
                </div>
            </nav>
        </aside>
    </Transition>
</template>
