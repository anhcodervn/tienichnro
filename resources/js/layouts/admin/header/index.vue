<script setup lang="ts">
import { useUserStore } from '@/stores/user.store';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import { ChevronDown, ChevronRight, ExternalLink, Menu as MenuIcon, Settings } from 'lucide-vue-next';
import { computed } from 'vue';
import { RouterLink } from 'vue-router';

defineProps<{ pageTitle: string; sectionTitle: string; sidebarOpen: boolean }>();
defineEmits<{ openSidebar: [] }>();
const userStore = useUserStore();
const userInitials = computed(() =>
    (userStore.user?.full_name || userStore.user?.username || 'Admin')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join(''),
);
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="flex min-h-20 items-center justify-between gap-3 px-4 sm:px-6 xl:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    aria-label="Mở menu quản trị"
                    aria-controls="admin-sidebar"
                    :aria-expanded="sidebarOpen"
                    class="ui-focus flex h-11 w-11 shrink-0 items-center justify-center rounded-[10px] border border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300 lg:hidden"
                    @click="$emit('openSidebar')"
                >
                    <MenuIcon class="h-5 w-5" />
                </button>
                <nav aria-label="Vị trí hiện tại" class="flex min-w-0 items-center gap-2 text-sm">
                    <RouterLink to="/admin" class="ui-focus hidden rounded-[10px] text-slate-500 hover:text-blue-700 sm:block">Quản trị</RouterLink>
                    <ChevronRight class="hidden h-3.5 w-3.5 shrink-0 text-slate-300 sm:block" />
                    <span v-if="sectionTitle !== pageTitle" class="hidden items-center gap-2 text-slate-500 xl:inline-flex"
                        >{{ sectionTitle }}<ChevronRight class="h-3.5 w-3.5 text-slate-300"
                    /></span>
                    <span aria-current="page" class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ pageTitle }}</span>
                </nav>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <a
                    href="/"
                    target="_blank"
                    rel="noopener"
                    class="ui-focus hidden min-h-10 items-center gap-2 rounded-[10px] px-3 text-sm text-slate-500 hover:bg-slate-50 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white sm:inline-flex"
                    ><ExternalLink class="h-4 w-4" />Xem website</a
                >
                <span class="hidden h-7 w-px bg-slate-200 dark:bg-slate-700 sm:block" aria-hidden="true"></span>
                <Menu as="div" class="relative">
                    <MenuButton
                        class="ui-focus flex min-h-11 items-center gap-2 rounded-[10px] p-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800"
                    >
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-[10px] bg-slate-100 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            >{{ userInitials }}</span
                        >
                        <span class="hidden max-w-36 sm:block"
                            ><span class="block truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{
                                userStore.displayName || 'Admin'
                            }}</span
                            ><span class="block text-xs text-slate-500">Quản trị viên</span></span
                        >
                        <ChevronDown class="h-4 w-4 text-slate-400" />
                    </MenuButton>
                    <MenuItems
                        class="absolute right-0 mt-2 w-64 rounded-[10px] border border-slate-200 bg-white p-1.5 shadow-lg focus:outline-none dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="mb-1 border-b border-slate-100 px-3 py-3 dark:border-slate-800">
                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ userStore.displayName || 'Admin' }}</p>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ userStore.user?.email }}</p>
                        </div>
                        <MenuItem v-slot="{ active }"
                            ><RouterLink
                                to="/admin/settings/general"
                                class="flex min-h-11 items-center gap-3 rounded-[10px] px-3 text-sm text-slate-600 dark:text-slate-300"
                                :class="{ 'bg-slate-100 dark:bg-slate-800': active }"
                                ><Settings class="h-4 w-4" />Cấu hình website</RouterLink
                            ></MenuItem
                        >
                        <MenuItem v-slot="{ active }"
                            ><a
                                href="/"
                                class="flex min-h-11 items-center gap-3 rounded-[10px] px-3 text-sm text-slate-600 dark:text-slate-300"
                                :class="{ 'bg-slate-100 dark:bg-slate-800': active }"
                                ><ExternalLink class="h-4 w-4" />Trở về website</a
                            ></MenuItem
                        >
                    </MenuItems>
                </Menu>
            </div>
        </div>
    </header>
</template>
