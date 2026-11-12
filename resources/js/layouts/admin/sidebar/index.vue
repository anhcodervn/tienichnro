<script setup lang="ts">
import { useSystemSetting } from '@/composables/useSystemSetting';
import { ChevronDown, ExternalLink, Search, ShieldCheck, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { adminMenuSections, filterAdminMenu, resolveAdminNavigation, type AdminMenuGroup } from './navigation';

const props = defineProps<{ isOpen: boolean; isMobile: boolean }>();
const emit = defineEmits<{ close: [] }>();
const route = useRoute();
const { settings, fetchSettings } = useSystemSetting();
const sidebarLogo = computed(() => settings.value.dark_logo || settings.value.light_logo);
const query = ref('');
const panel = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const expandedGroup = ref<string | null>(null);
let returnFocus: HTMLElement | null = null;
const visibleGroups = computed(() => filterAdminMenu(query.value));
const sections = computed(() =>
    adminMenuSections
        .map((section) => ({
            ...section,
            groups: visibleGroups.value.filter((group) => group.section === section.key),
        }))
        .filter((section) => section.groups.length),
);
const active = computed(() => resolveAdminNavigation(route.path));
const isGroupActive = (group: AdminMenuGroup): boolean => active.value?.group.key === group.key;
const isExpanded = (key: string): boolean => !!query.value.trim() || expandedGroup.value === key;
const closeOnMobile = (): void => {
    if (props.isMobile) emit('close');
};
const trapFocus = (event: KeyboardEvent): void => {
    if (!props.isMobile || event.key !== 'Tab') return;
    const elements = panel.value?.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), input:not([disabled])');
    const focusable = Array.from(elements ?? []).filter((element) => element.getClientRects().length);
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
};
watch(
    () => route.path,
    () => {
        expandedGroup.value = active.value?.group.children ? active.value.group.key : null;
        query.value = '';
    },
    { immediate: true },
);
watch(
    () => props.isOpen,
    async (open) => {
        if (!props.isMobile) return;
        if (open) {
            returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            await nextTick();
            searchInput.value?.focus();
        } else {
            returnFocus?.focus();
        }
    },
);
onMounted(() => {
    void fetchSettings().catch(() => {});
});
</script>

<template>
    <aside
        v-if="isOpen"
        id="admin-sidebar"
        ref="panel"
        :role="isMobile ? 'dialog' : undefined"
        :aria-modal="isMobile ? true : undefined"
        aria-label="Điều hướng quản trị"
        class="fixed inset-y-0 left-0 z-50 flex h-dvh w-[264px] max-w-[calc(100vw-32px)] flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
        @keydown="trapFocus"
    >
        <div class="flex h-20 shrink-0 items-center justify-between gap-2 border-b border-slate-100 px-5 dark:border-slate-800">
            <RouterLink to="/admin" class="ui-focus flex min-w-0 items-center gap-3 rounded-[10px]" @click="closeOnMobile">
                <img
                    v-if="sidebarLogo"
                    :src="sidebarLogo"
                    :alt="settings.site_name || 'Nạp Carot'"
                    class="h-auto w-24 shrink-0 object-contain object-left"
                />
                <span v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-blue-600 text-white"
                    ><ShieldCheck class="h-5 w-5"
                /></span>
                <span v-if="!sidebarLogo" class="min-w-0"
                    ><span class="block truncate text-sm font-bold text-slate-900 dark:text-white">{{ settings.site_name || 'Nạp Carot' }}</span
                    ><span class="mt-0.5 block text-xs text-slate-500">Trang quản trị</span></span
                >
            </RouterLink>
            <button
                v-if="isMobile"
                type="button"
                aria-label="Đóng menu"
                class="ui-focus flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                @click="emit('close')"
            >
                <X class="h-5 w-5" />
            </button>
        </div>
        <div class="px-4 pb-1 pt-4">
            <label class="relative block"
                ><span class="sr-only">Tìm mục quản lý</span><Search class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" /><input
                    ref="searchInput"
                    v-model="query"
                    type="search"
                    placeholder="Tìm mục quản lý…"
                    class="h-10 w-full rounded-[10px] border border-slate-200 bg-slate-50 pl-9 pr-3 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            /></label>
        </div>
        <nav class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pb-6 pt-3" aria-label="Menu chính">
            <RouterLink
                v-if="visibleGroups.some((group) => group.key === 'overview')"
                to="/admin"
                class="admin-nav-item mb-5"
                :class="{ 'admin-nav-active': active?.href === '/admin' }"
                :aria-current="active?.href === '/admin' ? 'page' : undefined"
                @click="closeOnMobile"
            >
                <component :is="visibleGroups.find((group) => group.key === 'overview')?.icon" class="h-[18px] w-[18px] shrink-0" /><span
                    >Tổng quan</span
                >
            </RouterLink>
            <div class="grid gap-6">
                <section v-for="section in sections" :key="section.key">
                    <h2 class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {{ section.label }}
                    </h2>
                    <div class="grid gap-1">
                        <template v-for="group in section.groups" :key="group.key">
                            <RouterLink
                                v-if="group.href"
                                :to="group.href"
                                class="admin-nav-item"
                                :class="{ 'admin-nav-active': isGroupActive(group) }"
                                :aria-current="isGroupActive(group) ? 'page' : undefined"
                                @click="closeOnMobile"
                            >
                                <component :is="group.icon" class="h-[18px] w-[18px] shrink-0" /><span class="flex-1">{{ group.label }}</span>
                            </RouterLink>
                            <div v-else>
                                <button
                                    type="button"
                                    class="admin-nav-item w-full text-left"
                                    :class="{ 'text-blue-700 dark:text-blue-300': isGroupActive(group) }"
                                    :aria-expanded="isExpanded(group.key)"
                                    :aria-controls="'admin-nav-' + group.key"
                                    @click="expandedGroup = expandedGroup === group.key ? null : group.key"
                                >
                                    <component :is="group.icon" class="h-[18px] w-[18px] shrink-0" /><span class="flex-1">{{ group.label }}</span
                                    ><ChevronDown
                                        class="h-4 w-4 text-slate-400 transition-transform"
                                        :class="{ 'rotate-180': isExpanded(group.key) }"
                                    />
                                </button>
                                <div
                                    v-if="isExpanded(group.key)"
                                    :id="'admin-nav-' + group.key"
                                    class="ml-5 mt-1 grid gap-1 border-l border-slate-200 pl-3 dark:border-slate-700"
                                >
                                    <RouterLink
                                        v-for="child in group.children"
                                        :key="child.href"
                                        :to="child.href"
                                        class="admin-nav-item min-h-9 py-2 text-[13px]"
                                        :class="{ 'admin-nav-active': active?.href === child.href }"
                                        :aria-current="active?.href === child.href ? 'page' : undefined"
                                        @click="closeOnMobile"
                                        >{{ child.label }}</RouterLink
                                    >
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
            </div>
            <p v-if="!visibleGroups.length" role="status" class="px-3 py-8 text-center text-sm leading-6 text-slate-500">
                Không tìm thấy mục quản lý.<br />Thử từ khóa khác.
            </p>
        </nav>
        <div class="shrink-0 border-t border-slate-100 p-3 dark:border-slate-800">
            <a href="/" class="admin-nav-item text-slate-500"><ExternalLink class="h-4 w-4" /><span>Trở về website</span></a>
        </div>
    </aside>
</template>
