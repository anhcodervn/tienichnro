<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import Editor from '@/components/shared/Editor/index.vue';
import UploadImage from '@/components/shared/UpladImage/index.vue';
import { useSystemSetting } from '@/composables/useSystemSetting';
import { adminSettingService } from '@/services/admin-setting.service';
import type {
    BrandingSettingType,
    ContactSettingType,
    DiscordWebhookSettingItemType,
    GeneralSettingType,
    HomepageNoticeSettingType,
    MonitoringSettingType,
    SeoSettingType,
} from '@/types/setting.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { computed, onMounted, ref } from 'vue';

type TabKey = 'general' | 'homepage' | 'branding' | 'contact' | 'seo' | 'monitoring';

const tabs: Array<{ key: TabKey; label: string; description: string }> = [
    {
        key: 'general',
        label: 'Tổng quan',
        description: 'Tên hệ thống, domain và trạng thái vận hành.',
    },
    {
        key: 'homepage',
        label: 'Thông báo trang chủ',
        description: 'Soạn nội dung hiển thị phía trên form nạp game trên trang chủ.',
    },
    {
        key: 'branding',
        label: 'Nhận diện',
        description: 'Logo sáng/tối, favicon, màu sắc và ảnh chia sẻ.',
    },
    {
        key: 'contact',
        label: 'Liên hệ',
        description: 'Các kênh hỗ trợ hiển thị trên website.',
    },
    {
        key: 'seo',
        label: 'SEO & chia sẻ',
        description: 'Metadata, robots và script đo lường.',
    },
    {
        key: 'monitoring',
        label: 'Webhook Discord',
        description: 'Bot cảnh báo vận hành cho đăng ký mới, nạp tiền và đơn nạp game lỗi.',
    },
];

const activeTab = ref<TabKey>('general');
const { fetchSettings: refreshSharedSettings } = useSystemSetting();
const loading = ref(true);
const saving = ref<Record<TabKey, boolean>>({
    general: false,
    homepage: false,
    branding: false,
    contact: false,
    seo: false,
    monitoring: false,
});

const generalForm = ref<GeneralSettingType>({
    site_name: '',
    site_domain: '',
    site_description: '',
    site_active: true,
    allow_register: false,
});

const homepageForm = ref<HomepageNoticeSettingType>({
    home_notice_title: 'Thông báo quan trọng',
    home_notice_content: [],
    home_notice_is_published: true,
});

const brandingForm = ref<BrandingSettingType>({
    light_logo: '',
    dark_logo: '',
    favicon: '',
    og_image: '',
    color_primary: '#0F172A',
    color_accent: '#2563EB',
    color_surface: '#F8FAFC',
});

const contactForm = ref<ContactSettingType>({
    hotline: '',
    support_email: '',
    address: '',
    facebook: '',
    zalo: '',
    youtube: '',
});

const seoForm = ref<SeoSettingType>({
    meta_title: '',
    meta_description: '',
    robots: 'index,follow',
    gtm_id: '',
    meta_pixel_id: '',
    custom_script: '',
});

const monitoringForm = ref<MonitoringSettingType>({
    discord_webhooks: [],
});

const webhookEventOptions = [
    { label: 'Ping kiểm tra', value: 'test_ping' },
    { label: 'Đăng ký mới', value: 'user_registered' },
    { label: 'Nạp tiền thành công', value: 'recharge_success' },
];

const currentTab = computed(() => tabs.find((tab) => tab.key === activeTab.value) ?? tabs[0]);
const siteDomainPreview = computed(() => generalForm.value.site_domain?.trim() || window.location.origin);
const shareTitlePreview = computed(() => seoForm.value.meta_title?.trim() || generalForm.value.site_name?.trim() || 'Tiêu đề website');
const shareDescriptionPreview = computed(
    () => seoForm.value.meta_description?.trim() || generalForm.value.site_description?.trim() || 'Mô tả website sẽ hiển thị ở đây.',
);

const loadData = async (): Promise<void> => {
    try {
        loading.value = true;

        const [general, homepage, branding, contact, seo, monitoring] = await Promise.all([
            adminSettingService.getGeneral(),
            adminSettingService.getHomepage(),
            adminSettingService.getBranding(),
            adminSettingService.getContact(),
            adminSettingService.getSeo(),
            adminSettingService.getMonitoring(),
        ]);

        generalForm.value = { ...generalForm.value, ...general.settings };
        homepageForm.value = {
            ...homepageForm.value,
            ...homepage.settings,
            home_notice_content: Array.isArray(homepage.settings.home_notice_content) ? homepage.settings.home_notice_content : [],
        };
        brandingForm.value = { ...brandingForm.value, ...branding.settings };
        contactForm.value = { ...contactForm.value, ...contact.settings };
        seoForm.value = { ...seoForm.value, ...seo.settings };
        monitoringForm.value = {
            discord_webhooks: Array.isArray(monitoring.settings.discord_webhooks)
                ? monitoring.settings.discord_webhooks.map((item) => ({
                      name: String(item.name ?? ''),
                      url: String(item.url ?? ''),
                      is_active: Boolean(item.is_active ?? true),
                      events: Array.isArray(item.events) ? item.events.map((event) => String(event)) : [],
                  }))
                : [],
        };
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const withSaving = async (tab: TabKey, callback: () => Promise<void>): Promise<void> => {
    try {
        saving.value[tab] = true;
        await callback();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value[tab] = false;
    }
};

const saveGeneral = async (): Promise<void> => {
    await withSaving('general', async () => {
        const response = await adminSettingService.updateGeneral(generalForm.value);
        generalForm.value = { ...generalForm.value, ...response.settings };
        void refreshSharedSettings(true).catch(() => {});
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật cấu hình tổng quan.' } });
    });
};

const saveHomepage = async (): Promise<void> => {
    await withSaving('homepage', async () => {
        const response = await adminSettingService.updateHomepage(homepageForm.value);
        homepageForm.value = {
            ...homepageForm.value,
            ...response.settings,
            home_notice_content: Array.isArray(response.settings.home_notice_content) ? response.settings.home_notice_content : [],
        };
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật thông báo trang chủ.' } });
    });
};

const saveBranding = async (): Promise<void> => {
    await withSaving('branding', async () => {
        const response = await adminSettingService.updateBranding(brandingForm.value);
        brandingForm.value = { ...brandingForm.value, ...response.settings };
        void refreshSharedSettings(true).catch(() => {});
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật nhận diện thương hiệu.' } });
    });
};

const saveContact = async (): Promise<void> => {
    await withSaving('contact', async () => {
        const response = await adminSettingService.updateContact(contactForm.value);
        contactForm.value = { ...contactForm.value, ...response.settings };
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật thông tin liên hệ.' } });
    });
};

const saveSeo = async (): Promise<void> => {
    await withSaving('seo', async () => {
        const response = await adminSettingService.updateSeo(seoForm.value);
        seoForm.value = { ...seoForm.value, ...response.settings };
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật cấu hình SEO.' } });
    });
};

const createWebhook = (): DiscordWebhookSettingItemType => ({
    name: '',
    url: '',
    is_active: true,
    events: ['test_ping', 'recharge_success'],
});

const addWebhook = (): void => {
    monitoringForm.value.discord_webhooks.push(createWebhook());
};

const removeWebhook = (index: number): void => {
    monitoringForm.value.discord_webhooks.splice(index, 1);
};

const toggleWebhookEvent = (webhook: DiscordWebhookSettingItemType, event: string): void => {
    const exists = webhook.events.includes(event);
    webhook.events = exists ? webhook.events.filter((item) => item !== event) : [...webhook.events, event];
};

const saveMonitoring = async (): Promise<void> => {
    await withSaving('monitoring', async () => {
        const payload: MonitoringSettingType = {
            discord_webhooks: monitoringForm.value.discord_webhooks.map((item) => ({
                name: item.name.trim(),
                url: item.url.trim(),
                is_active: item.is_active,
                events: item.events,
            })),
        };

        const response = await adminSettingService.updateMonitoring(payload);
        monitoringForm.value = {
            discord_webhooks: Array.isArray(response.settings.discord_webhooks) ? response.settings.discord_webhooks : [],
        };
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật webhook Discord giám sát.' } });
    });
};

onMounted(async () => {
    await loadData();
});
</script>

<template>
    <div class="space-y-4">
        <Breadcrumb
            title="Cấu hình chung"
            description="Quản lý thông tin vận hành, nhận diện thương hiệu, liên hệ và SEO của hệ thống napcarot.vn."
        />

        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">{{ currentTab.label }}</h2>
                        <p class="text-sm text-slate-500">{{ currentTab.description }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="rounded-[10px] border px-3 py-2 text-sm font-medium transition"
                            :class="
                                activeTab === tab.key
                                    ? 'border-indigo-600 bg-indigo-600 text-white'
                                    : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300 hover:text-slate-900'
                            "
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="space-y-3 px-4 py-6">
                <div class="h-16 animate-pulse rounded-[10px] bg-slate-100"></div>
                <div class="h-40 animate-pulse rounded-[10px] bg-slate-100"></div>
            </div>

            <div v-else class="p-4">
                <div v-show="activeTab === 'general'" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                    <article class="rounded-[10px] border border-slate-200 bg-white p-4">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Thông tin vận hành</h3>
                                <p class="text-sm text-slate-500">Tên hiển thị, domain chính và trạng thái hệ thống.</p>
                            </div>

                            <button
                                type="button"
                                class="rounded-[10px] bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                                :disabled="saving.general"
                                @click="saveGeneral"
                            >
                                {{ saving.general ? 'Đang lưu...' : 'Lưu tổng quan' }}
                            </button>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="space-y-1 md:col-span-2">
                                <span class="text-xs font-semibold text-slate-600">Tên website</span>
                                <input
                                    v-model="generalForm.site_name"
                                    type="text"
                                    class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                />
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-slate-600">Domain</span>
                                <input
                                    v-model="generalForm.site_domain"
                                    type="text"
                                    class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                    placeholder="https://domain-cua-ban.com"
                                />
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-slate-600">Mô tả ngắn</span>
                                <input
                                    v-model="generalForm.site_description"
                                    type="text"
                                    class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                />
                            </label>

                            <label
                                class="flex items-center justify-between rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700"
                            >
                                <span>Website đang hoạt động</span>
                                <input v-model="generalForm.site_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                            </label>

                            <label
                                class="flex items-center justify-between rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700"
                            >
                                <span>Cho phép đăng ký tài khoản</span>
                                <input v-model="generalForm.allow_register" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                            </label>
                        </div>
                    </article>

                    <aside class="rounded-[10px] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Preview domain</p>
                        <div class="mt-3 rounded-[10px] border border-slate-200 bg-white p-4">
                            <p class="text-sm font-semibold text-slate-900">{{ generalForm.site_name || 'Tên website' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ siteDomainPreview }}</p>
                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                {{
                                    generalForm.site_description ||
                                    'Mô tả website sẽ hiển thị tại đây để người dùng nhận diện nhanh nội dung hệ thống.'
                                }}
                            </p>
                        </div>
                    </aside>
                </div>

                <div v-show="activeTab === 'homepage'" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                    <article class="rounded-[10px] border border-slate-200 bg-white p-4">
                        <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Thông báo trang chủ</h3>
                                <p class="text-sm text-slate-500">Nội dung này xuất hiện phía trên khu vực mua Carot.</p>
                            </div>

                            <button
                                type="button"
                                class="rounded-[10px] bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                                :disabled="saving.homepage"
                                @click="saveHomepage"
                            >
                                {{ saving.homepage ? 'Đang lưu...' : 'Lưu thông báo' }}
                            </button>
                        </div>

                        <div class="grid gap-4 pt-4">
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-slate-600">Tiêu đề thông báo</span>
                                <input
                                    v-model="homepageForm.home_notice_title"
                                    type="text"
                                    maxlength="255"
                                    class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                    placeholder="Thông báo quan trọng"
                                />
                            </label>

                            <div class="space-y-1">
                                <span class="text-xs font-semibold text-slate-600">Nội dung thông báo</span>
                                <div class="overflow-hidden rounded-[10px] border border-slate-200 p-2">
                                    <Editor v-model="homepageForm.home_notice_content" :allow-images="false" :debounce="0" />
                                </div>
                            </div>

                            <label
                                class="flex items-center justify-between gap-3 rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700"
                            >
                                <span>
                                    <span class="block font-semibold text-slate-900">Hiển thị trên trang chủ</span>
                                    <span class="mt-1 block text-xs text-slate-500">Tắt để ẩn thông báo nhưng vẫn giữ nội dung đã soạn.</span>
                                </span>
                                <input v-model="homepageForm.home_notice_is_published" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                            </label>
                        </div>
                    </article>

                    <aside class="rounded-[10px] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Vị trí hiển thị</p>
                        <div class="mt-3 rounded-[10px] border border-slate-200 bg-white p-4">
                            <p class="text-sm font-semibold text-slate-900">{{ homepageForm.home_notice_title || 'Thông báo quan trọng' }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                Thông báo được render an toàn ngay phía trên form nạp game trên trang chủ.
                            </p>
                            <span
                                class="mt-3 inline-flex rounded-[5px] px-2 py-1 text-xs font-semibold"
                                :class="homepageForm.home_notice_is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                            >
                                {{ homepageForm.home_notice_is_published ? 'Đang hiển thị' : 'Đang ẩn' }}
                            </span>
                        </div>
                    </aside>
                </div>

                <div v-show="activeTab === 'branding'" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <div class="space-y-4">
                        <article class="rounded-[10px] border border-slate-200 bg-white p-4">
                            <div class="mb-4 flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">Hình ảnh thương hiệu</h3>
                                    <p class="text-sm text-slate-500">
                                        Logo dùng trên nền tối, nền sáng, favicon và ảnh preview khi chia sẻ website.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="rounded-[10px] bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                                    :disabled="saving.branding"
                                    @click="saveBranding"
                                >
                                    {{ saving.branding ? 'Đang lưu...' : 'Lưu nhận diện' }}
                                </button>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="rounded-[10px] border border-slate-200 p-3">
                                    <p class="text-sm font-medium text-slate-700">Logo nền tối</p>
                                    <div class="mt-3 flex h-28 items-center justify-center rounded-[10px] bg-slate-900">
                                        <img
                                            v-if="brandingForm.light_logo"
                                            :src="brandingForm.light_logo"
                                            alt="logo-light"
                                            class="h-full w-full object-contain"
                                        />
                                        <span v-else class="text-xs text-slate-400">320 × 96</span>
                                    </div>
                                    <div class="mt-3">
                                        <UploadImage
                                            :image-src="brandingForm.light_logo"
                                            :accept="['image/png', 'image/jpeg', 'image/webp']"
                                            :compress="false"
                                            @uploaded="(url: string) => (brandingForm.light_logo = url)"
                                        />
                                    </div>
                                </div>

                                <div class="rounded-[10px] border border-slate-200 p-3">
                                    <p class="text-sm font-medium text-slate-700">Logo nền sáng</p>
                                    <div class="mt-3 flex h-28 items-center justify-center rounded-[10px] bg-slate-50">
                                        <img
                                            v-if="brandingForm.dark_logo"
                                            :src="brandingForm.dark_logo"
                                            alt="logo-dark"
                                            class="h-full w-full object-contain"
                                        />
                                        <span v-else class="text-xs text-slate-400">320 × 96</span>
                                    </div>
                                    <div class="mt-3">
                                        <UploadImage
                                            :image-src="brandingForm.dark_logo"
                                            :accept="['image/png', 'image/jpeg', 'image/webp']"
                                            :compress="false"
                                            @uploaded="(url: string) => (brandingForm.dark_logo = url)"
                                        />
                                    </div>
                                </div>

                                <div class="rounded-[10px] border border-slate-200 p-3">
                                    <p class="text-sm font-medium text-slate-700">Favicon</p>
                                    <div class="mt-3 flex h-28 items-center justify-center rounded-[10px] bg-slate-50">
                                        <img v-if="brandingForm.favicon" :src="brandingForm.favicon" alt="favicon" class="h-16 w-16 object-contain" />
                                        <span v-else class="text-xs text-slate-400">64 × 64</span>
                                    </div>
                                    <div class="mt-3">
                                        <UploadImage
                                            :image-src="brandingForm.favicon"
                                            :accept="['image/png', 'image/jpeg', 'image/webp']"
                                            :compress="false"
                                            @uploaded="(url: string) => (brandingForm.favicon = url)"
                                        />
                                    </div>
                                </div>

                                <div class="rounded-[10px] border border-slate-200 p-3 md:col-span-3">
                                    <p class="text-sm font-medium text-slate-700">Ảnh chia sẻ mặc định</p>
                                    <div class="mt-3 flex h-28 items-center justify-center overflow-hidden rounded-[10px] bg-slate-50">
                                        <img
                                            v-if="brandingForm.og_image"
                                            :src="brandingForm.og_image"
                                            alt="og-image"
                                            class="h-full w-full object-cover"
                                        />
                                        <span v-else class="text-xs text-slate-400">1200 × 630</span>
                                    </div>
                                    <div class="mt-3">
                                        <UploadImage
                                            :image-src="brandingForm.og_image"
                                            :accept="['image/png', 'image/jpeg', 'image/webp']"
                                            :compress="false"
                                            @uploaded="(url: string) => (brandingForm.og_image = url)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-[10px] border border-slate-200 bg-white p-4">
                            <h3 class="mb-4 text-sm font-semibold text-slate-900">Màu thương hiệu</h3>
                            <div class="grid gap-3 md:grid-cols-3">
                                <label class="space-y-2 rounded-[10px] border border-slate-200 p-3">
                                    <span class="text-xs font-semibold text-slate-600">Màu chính</span>
                                    <input
                                        v-model="brandingForm.color_primary"
                                        type="color"
                                        class="h-10 w-full rounded-[10px] border border-slate-200 bg-white p-1"
                                    />
                                    <input
                                        v-model="brandingForm.color_primary"
                                        type="text"
                                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                    />
                                </label>

                                <label class="space-y-2 rounded-[10px] border border-slate-200 p-3">
                                    <span class="text-xs font-semibold text-slate-600">Màu nhấn</span>
                                    <input
                                        v-model="brandingForm.color_accent"
                                        type="color"
                                        class="h-10 w-full rounded-[10px] border border-slate-200 bg-white p-1"
                                    />
                                    <input
                                        v-model="brandingForm.color_accent"
                                        type="text"
                                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                    />
                                </label>

                                <label class="space-y-2 rounded-[10px] border border-slate-200 p-3">
                                    <span class="text-xs font-semibold text-slate-600">Màu nền</span>
                                    <input
                                        v-model="brandingForm.color_surface"
                                        type="color"
                                        class="h-10 w-full rounded-[10px] border border-slate-200 bg-white p-1"
                                    />
                                    <input
                                        v-model="brandingForm.color_surface"
                                        type="text"
                                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                    />
                                </label>
                            </div>
                        </article>
                    </div>

                    <aside class="rounded-[10px] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Preview chia sẻ</p>
                        <div class="mt-3 overflow-hidden rounded-[10px] border border-slate-200 bg-white">
                            <div class="h-36 bg-slate-100">
                                <img
                                    v-if="brandingForm.og_image"
                                    :src="brandingForm.og_image"
                                    alt="share-preview"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                            <div class="space-y-2 p-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-[10px] border border-slate-200 bg-white"
                                    >
                                        <img
                                            v-if="brandingForm.favicon"
                                            :src="brandingForm.favicon"
                                            alt="favicon-preview"
                                            class="h-6 w-6 object-contain"
                                        />
                                        <span v-else class="text-xs font-semibold text-slate-500">ICO</span>
                                    </div>
                                    <p class="text-xs text-slate-500">{{ siteDomainPreview }}</p>
                                </div>
                                <p class="text-sm font-semibold text-slate-900">{{ shareTitlePreview }}</p>
                                <p class="text-sm leading-6 text-slate-600">{{ shareDescriptionPreview }}</p>
                            </div>
                        </div>
                    </aside>
                </div>

                <div v-show="activeTab === 'contact'" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                    <article class="rounded-[10px] border border-slate-200 bg-white p-4">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Thông tin hỗ trợ</h3>
                                <p class="text-sm text-slate-500">Các kênh liên hệ hiển thị trên landing page và footer.</p>
                            </div>

                            <button
                                type="button"
                                class="rounded-[10px] bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                                :disabled="saving.contact"
                                @click="saveContact"
                            >
                                {{ saving.contact ? 'Đang lưu...' : 'Lưu liên hệ' }}
                            </button>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-slate-600">Hotline</span>
                                <input
                                    v-model="contactForm.hotline"
                                    type="text"
                                    class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                />
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-slate-600">Email hỗ trợ</span>
                                <input
                                    v-model="contactForm.support_email"
                                    type="email"
                                    class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                />
                            </label>
                            <label class="space-y-1 md:col-span-2">
                                <span class="text-xs font-semibold text-slate-600">Địa chỉ</span>
                                <textarea
                                    v-model="contactForm.address"
                                    rows="3"
                                    class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                />
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-slate-600">Facebook</span>
                                <input
                                    v-model="contactForm.facebook"
                                    type="text"
                                    class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                />
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-slate-600">Zalo</span>
                                <input
                                    v-model="contactForm.zalo"
                                    type="text"
                                    class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                />
                            </label>
                            <label class="space-y-1 md:col-span-2">
                                <span class="text-xs font-semibold text-slate-600">YouTube</span>
                                <input
                                    v-model="contactForm.youtube"
                                    type="text"
                                    class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                />
                            </label>
                        </div>
                    </article>

                    <aside class="rounded-[10px] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Preview footer</p>
                        <div class="mt-3 rounded-[10px] border border-slate-200 bg-white p-4 text-sm text-slate-600">
                            <p class="font-semibold text-slate-900">{{ contactForm.hotline || 'Hotline' }}</p>
                            <p class="mt-1">{{ contactForm.support_email || 'support@example.com' }}</p>
                            <p class="mt-3 leading-6">{{ contactForm.address || 'Địa chỉ hỗ trợ sẽ hiển thị ở đây.' }}</p>
                        </div>
                    </aside>
                </div>

                <div v-show="activeTab === 'seo'" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <div class="space-y-4">
                        <article class="rounded-[10px] border border-slate-200 bg-white p-4">
                            <div class="mb-4 flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">SEO mặc định</h3>
                                    <p class="text-sm text-slate-500">Metadata cho landing page và các trang public chưa có cấu hình riêng.</p>
                                </div>

                                <button
                                    type="button"
                                    class="rounded-[10px] bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                                    :disabled="saving.seo"
                                    @click="saveSeo"
                                >
                                    {{ saving.seo ? 'Đang lưu...' : 'Lưu SEO' }}
                                </button>
                            </div>

                            <div class="grid gap-3">
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-slate-600">Meta title</span>
                                    <input
                                        v-model="seoForm.meta_title"
                                        type="text"
                                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                    />
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-slate-600">Meta description</span>
                                    <textarea
                                        v-model="seoForm.meta_description"
                                        rows="4"
                                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                    />
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-slate-600">Robots</span>
                                    <input
                                        v-model="seoForm.robots"
                                        type="text"
                                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                    />
                                </label>
                            </div>
                        </article>

                        <article class="rounded-[10px] border border-slate-200 bg-white p-4">
                            <h3 class="mb-4 text-sm font-semibold text-slate-900">Tracking & script</h3>
                            <div class="grid gap-3">
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-slate-600">Google Tag Manager ID</span>
                                    <input
                                        v-model="seoForm.gtm_id"
                                        type="text"
                                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                    />
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-slate-600">Meta Pixel ID</span>
                                    <input
                                        v-model="seoForm.meta_pixel_id"
                                        type="text"
                                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                    />
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-slate-600">Custom script</span>
                                    <textarea
                                        v-model="seoForm.custom_script"
                                        rows="6"
                                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 font-mono text-xs outline-none focus:border-indigo-400"
                                        placeholder="<script>...</script>"
                                    />
                                </label>
                            </div>
                        </article>
                    </div>

                    <aside class="rounded-[10px] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Khi chia sẻ link</p>
                        <div class="mt-3 rounded-[10px] border border-slate-200 bg-white p-4">
                            <p class="text-xs text-slate-500">{{ siteDomainPreview }}</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ shareTitlePreview }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ shareDescriptionPreview }}</p>
                            <div class="mt-4 rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                Robots: {{ seoForm.robots || 'index,follow' }}
                            </div>
                        </div>
                    </aside>
                </div>

                <div v-show="activeTab === 'monitoring'" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <article class="rounded-[10px] border border-slate-200 bg-white p-4">
                        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Webhook Discord giám sát</h3>
                                <p class="text-sm text-slate-500">Thêm nhiều bot Discord để theo dõi các sự kiện vận hành quan trọng của hệ thống.</p>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="rounded-[10px] border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300"
                                    @click="addWebhook"
                                >
                                    Thêm webhook
                                </button>
                                <button
                                    type="button"
                                    class="rounded-[10px] bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                                    :disabled="saving.monitoring"
                                    @click="saveMonitoring"
                                >
                                    {{ saving.monitoring ? 'Đang lưu...' : 'Lưu webhook' }}
                                </button>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="(webhook, index) in monitoringForm.discord_webhooks"
                                :key="index"
                                class="rounded-[14px] border border-slate-200 bg-slate-50 p-4"
                            >
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div class="grid flex-1 gap-3 md:grid-cols-2">
                                        <label class="space-y-1">
                                            <span class="text-xs font-semibold text-slate-600">Tên webhook</span>
                                            <input
                                                v-model="webhook.name"
                                                type="text"
                                                class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                                placeholder="Ví dụ: Bot vận hành chính"
                                            />
                                        </label>
                                        <label class="space-y-1 md:col-span-2">
                                            <span class="text-xs font-semibold text-slate-600">Discord webhook URL</span>
                                            <input
                                                v-model="webhook.url"
                                                type="text"
                                                class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                                placeholder="https://discord.com/api/webhooks/..."
                                            />
                                        </label>
                                    </div>

                                    <button
                                        type="button"
                                        class="rounded-[10px] border border-rose-200 bg-white px-3 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-50"
                                        @click="removeWebhook(index)"
                                    >
                                        Xoá
                                    </button>
                                </div>

                                <div
                                    class="mt-4 flex items-center justify-between rounded-[10px] border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700"
                                >
                                    <span>Bật webhook này</span>
                                    <input v-model="webhook.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                                </div>

                                <div class="mt-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Sự kiện lắng nghe</p>
                                    <div class="mt-3 grid gap-2 md:grid-cols-2">
                                        <label
                                            v-for="event in webhookEventOptions"
                                            :key="event.value"
                                            class="flex items-center justify-between rounded-[10px] border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700"
                                        >
                                            <span>{{ event.label }}</span>
                                            <input
                                                :checked="webhook.events.includes(event.value)"
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300"
                                                @change="toggleWebhookEvent(webhook, event.value)"
                                            />
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="monitoringForm.discord_webhooks.length === 0"
                                class="rounded-[14px] border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                            >
                                Chưa có webhook nào. Thêm bot Discord để nhận cảnh báo đăng ký mới, nạp tiền và đơn nạp game lỗi.
                            </div>
                        </div>
                    </article>

                    <aside class="rounded-[10px] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Gợi ý vận hành</p>
                        <div class="mt-3 space-y-3 rounded-[10px] border border-slate-200 bg-white p-4 text-sm text-slate-600">
                            <p>Tạo ít nhất 2 bot riêng: một bot cho giao dịch nạp tiền, một bot cho cảnh báo đơn nạp game lỗi.</p>
                            <p>
                                Bật sự kiện <span class="font-semibold text-slate-900">test_ping</span> để kiểm tra kết nối trước khi đưa vào dùng
                                thật.
                            </p>
                            <p>
                                Trang <span class="font-semibold text-slate-900">Báo cáo vận hành</span> có nút gửi test nhanh cho từng webhook sau
                                khi lưu.
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</template>
