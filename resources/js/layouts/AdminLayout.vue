<script setup lang="ts">
import { useSupportStore } from '@/stores/support.store';
import { useUserStore } from '@/stores/user.store';
import { computed, onBeforeUnmount, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Header from './admin/header/index.vue';
import Sidebar from './admin/sidebar/index.vue';
import { resolveAdminPageTitle } from './admin/sidebar/navigation';

const route = useRoute();
const isSidebarOpen = ref(false);
const userStore = useUserStore();
const supportStore = useSupportStore();
const isSupportRoute = computed(() => route.path === '/admin/support');

const currentPageTitle = computed(() => {
    return resolveAdminPageTitle(route.path);
});

const syncSidebarWithViewport = (): void => {
    isSidebarOpen.value = window.innerWidth >= 1024;
};

onMounted(() => {
    syncSidebarWithViewport();
    window.addEventListener('resize', syncSidebarWithViewport);
});

onUnmounted(() => {
    window.removeEventListener('resize', syncSidebarWithViewport);
});

watch(
    () => userStore.user?.id ?? null,
    (userId) => {
        if (userId !== null) {
            void supportStore.start('admin', userId);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (supportStore.context === 'admin') {
        supportStore.stop();
    }
});
</script>

<template>
    <div class="min-h-screen bg-[radial-gradient(circle_at_top_right,_#dff6ff_0%,_#f1f6fd_32%,_#f8fafc_72%)] text-slate-800">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isSidebarOpen" class="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-sm lg:hidden" @click="isSidebarOpen = false" />
        </Transition>

        <div class="flex min-h-screen">
            <Sidebar :is-open="isSidebarOpen" @close="isSidebarOpen = false" />

            <div class="min-w-0 flex-1">
                <Header :page-title="currentPageTitle" @open-sidebar="isSidebarOpen = true" />

                <main :class="isSupportRoute ? 'px-3 py-3 sm:px-4' : 'px-4 pb-8 pt-6 sm:px-6 xl:px-8'">
                    <div class="mx-auto w-full min-w-0 max-w-[1200px]">
                        <router-view />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
