<script setup lang="ts">
import { useUserStore } from '@/stores/user.store';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import { Bell, ChevronDown, LogOut, Menu as MenuIcon, Search, Settings, UserCircle2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';

defineProps<{
    pageTitle: string;
}>();

defineEmits<{
    openSidebar: [];
}>();

const userStore = useUserStore();
const searchKeyword = ref('');

const notifications = [
    {
        title: 'New topup order created',
        description: 'Review the latest topup order and payment activity.',
    },
    {
        title: 'Topup order needs attention',
        description: 'Check renewal requests and provider status.',
    },
];

const userInitials = computed(() => {
    const source = userStore.user?.full_name || userStore.user?.username || 'Admin';

    return source
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
});
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/85 shadow-[0_12px_35px_-28px_rgba(15,23,42,0.45)] backdrop-blur-xl">
        <div class="flex flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 xl:px-8">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="ui-focus inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-blue-700 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 lg:hidden"
                    @click="$emit('openSidebar')"
                >
                    <MenuIcon class="h-5 w-5" />
                </button>

                <div class="hidden xl:flex xl:flex-col">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Topup operations</p>
                    <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-950">{{ pageTitle }}</h2>
                </div>
            </div>

            <div class="flex flex-1 items-center justify-end gap-3">
                <label
                    class="hidden min-w-[280px] items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm transition focus-within:border-blue-300 focus-within:ring-2 focus-within:ring-blue-100 md:flex"
                >
                    <Search class="h-4 w-4 text-blue-500" />
                    <input
                        v-model="searchKeyword"
                        type="text"
                        placeholder="Search modules, users, proxies..."
                        class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 outline-none placeholder:text-slate-400"
                    />
                </label>

                <Menu as="div" class="relative">
                    <MenuButton
                        class="ui-focus relative inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-blue-700 shadow-sm transition hover:border-blue-200 hover:bg-blue-50"
                    >
                        <Bell class="h-5 w-5" />
                        <span class="absolute right-2.5 top-2.5 h-2.5 w-2.5 rounded-full bg-cyan-400 ring-2 ring-white" />
                    </MenuButton>

                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="translate-y-2 scale-[0.98] opacity-0"
                        enter-to-class="translate-y-0 scale-100 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="translate-y-0 scale-100 opacity-100"
                        leave-to-class="translate-y-1 scale-[0.98] opacity-0"
                    >
                        <MenuItems
                            class="absolute right-0 mt-3 w-[320px] max-w-[calc(100vw-2rem)] origin-top-right rounded-[1.75rem] border border-slate-200 bg-white p-2 shadow-[0_22px_60px_rgba(15,23,42,0.16)] outline-none"
                        >
                            <div class="flex items-center justify-between px-3 py-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-950">Thông báo</p>
                                    <p class="text-xs text-blue-600">{{ notifications.length }} mục</p>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <MenuItem v-for="notification in notifications" :key="notification.title" v-slot="{ active }">
                                    <button
                                        type="button"
                                        class="w-full rounded-2xl px-3 py-3 text-left transition"
                                        :class="active ? 'bg-blue-50' : ''"
                                    >
                                        <p class="text-sm font-semibold text-slate-900">{{ notification.title }}</p>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ notification.description }}</p>
                                    </button>
                                </MenuItem>
                            </div>
                        </MenuItems>
                    </Transition>
                </Menu>

                <Menu as="div" class="relative">
                    <MenuButton
                        class="ui-focus inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white py-1.5 pl-2 pr-3 shadow-sm transition hover:border-blue-200 hover:bg-blue-50/60"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-cyan-500 text-sm font-bold text-white"
                        >
                            {{ userInitials }}
                        </div>
                        <div class="hidden text-left sm:block">
                            <p class="max-w-[180px] truncate text-sm font-semibold text-slate-900">
                                {{ userStore.displayName || 'Administrator' }}
                            </p>
                            <p class="text-xs text-slate-400">{{ userStore.user?.email || 'admin@example.com' }}</p>
                        </div>
                        <ChevronDown class="h-4 w-4 text-blue-400" />
                    </MenuButton>

                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="translate-y-2 scale-[0.98] opacity-0"
                        enter-to-class="translate-y-0 scale-100 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="translate-y-0 scale-100 opacity-100"
                        leave-to-class="translate-y-1 scale-[0.98] opacity-0"
                    >
                        <MenuItems
                            class="absolute right-0 mt-3 w-64 origin-top-right rounded-[1.75rem] border border-slate-200 bg-white p-2 shadow-[0_22px_60px_rgba(15,23,42,0.16)] outline-none"
                        >
                            <div class="border-b border-slate-100 px-3 py-3">
                                <p class="text-sm font-semibold text-slate-950">{{ userStore.displayName || 'Administrator' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ userStore.user?.email || 'admin@example.com' }}</p>
                            </div>

                            <div class="mt-2 space-y-1">
                                <MenuItem v-slot="{ active }">
                                    <RouterLink
                                        to="/admin/settings/general"
                                        class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition"
                                        :class="active ? 'bg-blue-50 text-blue-950' : 'text-slate-600'"
                                    >
                                        <Settings class="h-4 w-4" />
                                        <span>System settings</span>
                                    </RouterLink>
                                </MenuItem>

                                <MenuItem v-slot="{ active }">
                                    <RouterLink
                                        to="/admin/users"
                                        class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition"
                                        :class="active ? 'bg-blue-50 text-blue-950' : 'text-slate-600'"
                                    >
                                        <UserCircle2 class="h-4 w-4" />
                                        <span>User management</span>
                                    </RouterLink>
                                </MenuItem>

                                <MenuItem v-slot="{ active }">
                                    <RouterLink
                                        to="/"
                                        type="button"
                                        class="flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-left text-sm transition"
                                        :class="active ? 'bg-rose-50 text-rose-700' : 'text-slate-600'"
                                    >
                                        <LogOut class="h-4 w-4" />
                                        <span>Trở về website</span>
                                    </RouterLink>
                                </MenuItem>
                            </div>
                        </MenuItems>
                    </Transition>
                </Menu>
            </div>
        </div>
    </header>
</template>
