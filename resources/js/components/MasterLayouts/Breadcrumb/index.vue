<template>
    <div
        class="flex flex-col gap-2 rounded-[18px] border border-blue-100 bg-white/90 px-5 py-4 shadow-[0_18px_40px_-26px_rgba(37,99,235,0.24)] backdrop-blur-sm"
    >
        <div class="flex items-center gap-2 text-sm text-blue-700/75">
            <template v-for="(item, index) in resolvedItems" :key="`${item.label}-${index}`">
                <template v-if="index > 0">
                    <span class="mr-2 text-blue-200">/</span>
                </template>

                <router-link v-if="item.to && !item.active" :to="item.to" class="transition-colors hover:text-blue-700">
                    {{ item.label }}
                </router-link>

                <span v-else class="font-medium text-slate-950">
                    {{ item.label }}
                </span>
            </template>
        </div>

        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-slate-950">
                    {{ resolvedTitle }}
                </h1>
                <p v-if="description" class="text-sm text-slate-600">
                    {{ description }}
                </p>
            </div>

            <slot name="actions" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

interface BreadcrumbItem {
    label: string;
    to?: string;
    active?: boolean;
}

const route = useRoute();

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        items?: BreadcrumbItem[];
        homeLabel?: string;
    }>(),
    {
        title: '',
        description: '',
        items: () => [],
        homeLabel: 'Trang chủ',
    },
);

const routeLabelMap: Record<string, string> = {
    'admin.dashboard': 'Dashboard',
    'admin.reports.index': 'Báo cáo doanh thu',
    'admin.users.index': 'Người dùng',
    'admin.users.show': 'Chi tiết người dùng',
    'admin.users.wallet-transaction': 'Lịch sử dòng tiền',
    'admin.users.wallet-transaction.show': 'Lịch sử dòng tiền',
    'admin.notifications.index': 'Thông báo',
    'admin.notifications.create': 'Tạo thông báo',
    'admin.notifications.edit': 'Cập nhật thông báo',
    'admin.notifications.history': 'Lịch sử thông báo',
    'admin.mail.index': 'Gửi email',
    'admin.queues.index': 'Quản lý queue',
    'admin.feedbacks.index': 'Liên hệ và góp ý',
    'admin.seo.dashboard': 'Quản trị SEO',
    'admin.seo.categories': 'Danh mục SEO',
    'admin.seo.posts': 'Bài viết SEO',
    'admin.seo.posts.create': 'Tạo bài viết SEO',
    'admin.seo.posts.edit': 'Cập nhật bài viết SEO',
    'admin.seo.sitemaps': 'Sitemap và index',
    'admin.settings.general': 'Cấu hình chung',
    'admin.settings.content': 'Cấu hình nội dung',
    'admin.settings.recharge': 'Cấu hình nạp tiền',
    'admin.error.404': 'Trang quản trị không tồn tại',
    'client.home': 'Trang chủ',
    'client.wallet': 'Ví và nạp tiền',
    'client.api-docs': 'Tài liệu API',
    'client.profile': 'Hồ sơ tài khoản',
    'client.contact': 'Liên hệ và góp ý',
    'client.error.404': 'Trang không tồn tại',
};

const homeRoute = computed<string>(() => {
    const name = route.name?.toString() ?? '';

    if (name.startsWith('admin.')) {
        return '/admin';
    }

    return '/';
});

const autoItems = computed<BreadcrumbItem[]>(() => {
    const matchedRoutes = route.matched.filter((item) => item.name);

    const items = matchedRoutes.map((item, index) => {
        const name = item.name?.toString() || '';
        const label =
            routeLabelMap[name] ||
            item.meta?.title?.toString() ||
            name
                .split('.')
                .pop()
                ?.replace(/-/g, ' ')
                .replace(/\b\w/g, (char) => char.toUpperCase()) ||
            'Trang';

        return {
            label,
            to: item.path.includes(':') ? undefined : item.path,
            active: index === matchedRoutes.length - 1,
        };
    });

    return [
        {
            label: props.homeLabel,
            to: homeRoute.value,
            active: items.length === 0,
        },
        ...items,
    ];
});

const resolvedItems = computed(() => {
    if (props.items.length) {
        return props.items.map((item, index) => ({
            ...item,
            active: item.active ?? index === props.items.length - 1,
        }));
    }

    return autoItems.value;
});

const resolvedTitle = computed(() => {
    if (props.title) {
        return props.title;
    }

    return resolvedItems.value[resolvedItems.value.length - 1]?.label || props.homeLabel;
});

const description = computed(() => props.description);
</script>
