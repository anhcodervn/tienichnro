<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Header from './admin/header/index.vue';
import Sidebar from './admin/sidebar/index.vue';
import { resolveAdminNavigation, resolveAdminPageTitle } from './admin/sidebar/navigation';

const route = useRoute();
const isSidebarOpen = ref(false);
const isMobile = ref(false);
const currentPageTitle = computed(() => resolveAdminPageTitle(route.path));
const currentSection = computed(() => resolveAdminNavigation(route.path)?.group.label ?? 'Quản trị');
let viewport: MediaQueryList | undefined;
let previousOverflow = '';

const syncViewport = (): void => {
    isMobile.value = !viewport?.matches;
    isSidebarOpen.value = !!viewport?.matches;
};
const closeSidebar = (): void => {
    if (isMobile.value) isSidebarOpen.value = false;
};
const closeOnEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') closeSidebar();
};
watch(() => route.fullPath, closeSidebar);
watch([isSidebarOpen, isMobile], ([open, mobile]) => {
    document.body.style.overflow = open && mobile ? 'hidden' : previousOverflow;
});
onMounted(() => {
    previousOverflow = document.body.style.overflow;
    document.body.classList.add('admin-ui');
    viewport = window.matchMedia('(min-width: 1024px)');
    syncViewport();
    viewport.addEventListener('change', syncViewport);
    window.addEventListener('keydown', closeOnEscape);
});
onUnmounted(() => {
    document.body.classList.remove('admin-ui');
    document.body.style.overflow = previousOverflow;
    viewport?.removeEventListener('change', syncViewport);
    window.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
    <div class="admin-shell min-h-screen bg-slate-100 text-slate-800 dark:bg-slate-950 dark:text-slate-200">
        <a href="#admin-main" class="admin-skip-link">Đến nội dung chính</a>
        <div v-if="isSidebarOpen && isMobile" class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" aria-hidden="true" @click="closeSidebar" />
        <Sidebar :is-open="isSidebarOpen" :is-mobile="isMobile" @close="closeSidebar" />
        <div class="min-w-0 lg:pl-[264px]">
            <Header
                :page-title="currentPageTitle"
                :section-title="currentSection"
                :sidebar-open="isSidebarOpen"
                @open-sidebar="isSidebarOpen = true"
            />
            <main id="admin-main" tabindex="-1" class="min-w-0 px-4 py-6 sm:px-6 xl:px-8">
                <div class="mx-auto w-full min-w-0 max-w-[1800px]">
                    <div
                        data-admin-page-surface
                        class="min-w-0 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6"
                    >
                        <router-view />
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
