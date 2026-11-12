<script setup lang="ts">
import { adminMenuGroups, adminMenuSections } from '@/layouts/admin/sidebar/navigation';
import { ArrowRight, FileText, ScanLine, Users, Wallet } from 'lucide-vue-next';
import { RouterLink } from 'vue-router';

const sections = adminMenuSections.map((section) => ({
    ...section,
    groups: adminMenuGroups.filter((group) => group.section === section.key),
}));
const shortcuts = [
    { label: 'Người dùng', description: 'Tra cứu và quản lý tài khoản', href: '/admin/users', icon: Users },
    { label: 'Ví chính', description: 'Kiểm tra số dư và giao dịch', href: '/admin/wallets', icon: Wallet },
    { label: 'Giải captcha', description: 'Xem ảnh và kết quả xử lý', href: '/admin/captcha/history', icon: ScanLine },
    { label: 'Bài viết', description: 'Quản lý nội dung và SEO', href: '/admin/seo/posts', icon: FileText },
];
</script>

<template>
    <div class="grid gap-8">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Không gian quản lý</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Tổng quan quản trị</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">Chọn công việc cần xử lý hoặc truy cập từng khu vực quản lý bên dưới.</p>
            </div>
            <RouterLink to="/admin/tools" class="admin-secondary-button">Quản lý công cụ<ArrowRight class="h-4 w-4" /></RouterLink>
        </header>
        <section aria-labelledby="admin-shortcuts-title" class="grid gap-3">
            <h2 id="admin-shortcuts-title" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Truy cập nhanh</h2>
            <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
                <RouterLink
                    v-for="item in shortcuts"
                    :key="item.href"
                    :to="item.href"
                    class="ui-focus group flex items-start gap-3 rounded-[10px] border border-slate-200 bg-white p-5 transition-colors hover:border-blue-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-blue-700"
                >
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-slate-100 text-slate-600 group-hover:bg-blue-50 group-hover:text-blue-700 dark:bg-slate-800 dark:text-slate-300 dark:group-hover:bg-blue-950 dark:group-hover:text-blue-300"
                        ><component :is="item.icon" class="h-5 w-5"
                    /></span>
                    <span class="min-w-0 flex-1"
                        ><span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ item.label }}</span
                        ><span class="mt-1 block text-xs leading-5 text-slate-500">{{ item.description }}</span></span
                    >
                    <ArrowRight class="mt-1 h-4 w-4 shrink-0 text-slate-300 group-hover:text-blue-600" />
                </RouterLink>
            </div>
        </section>
        <div class="grid items-start gap-5 xl:grid-cols-2">
            <section
                v-for="section in sections"
                :key="section.key"
                class="overflow-hidden rounded-[10px] border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                :aria-labelledby="'admin-section-' + section.key"
            >
                <header class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 :id="'admin-section-' + section.key" class="text-base font-semibold text-slate-900 dark:text-white">{{ section.label }}</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ section.description }}</p>
                </header>
                <div class="divide-y divide-slate-100 px-5 dark:divide-slate-800">
                    <div v-for="group in section.groups" :key="group.key" class="grid gap-2 py-4">
                        <RouterLink
                            v-if="group.href"
                            :to="group.href"
                            class="ui-focus flex min-h-10 items-center justify-between gap-3 rounded-[10px] text-sm font-medium text-slate-700 hover:text-blue-700 dark:text-slate-200 dark:hover:text-blue-300"
                            ><span class="flex items-center gap-3"
                                ><component :is="group.icon" class="h-4 w-4 text-slate-400" />{{ group.label }}</span
                            ><ArrowRight class="h-4 w-4 text-slate-400"
                        /></RouterLink>
                        <template v-else
                            ><h3 class="flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-200">
                                <component :is="group.icon" class="h-4 w-4 text-slate-400" />{{ group.label }}
                            </h3>
                            <div class="flex flex-wrap gap-2 pl-7">
                                <RouterLink
                                    v-for="child in group.children"
                                    :key="child.href"
                                    :to="child.href"
                                    class="ui-focus inline-flex min-h-9 items-center rounded-[10px] bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-blue-50 hover:text-blue-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-blue-950 dark:hover:text-blue-300"
                                    >{{ child.label }}</RouterLink
                                >
                            </div></template
                        >
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
