<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import Editor from '@/components/shared/Editor/index.vue';
import { adminSettingService } from '@/services/admin-setting.service';
import type {
    ContentPageBaseKey,
    ContentPageContentKey,
    ContentPageExcerptKey,
    ContentPagePublishedKey,
    ContentPageSeoDescriptionKey,
    ContentPageSeoTitleKey,
    ContentPageSettingsType,
    ContentPageTabKey,
    ContentPageTitleKey,
} from '@/types/setting.type';
import { uploadEditorImages } from '@/utils/editor-image-upload';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { BookOpenText, CircleHelp, Eye, Flag, Info, Landmark, Mail, Scale, ShieldCheck, ShieldQuestion } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type PageDefinition = {
    key: ContentPageBaseKey;
    label: string;
    subtitle: string;
    description: string;
    slug: string;
    icon: unknown;
    titleKey: ContentPageTitleKey;
    excerptKey: ContentPageExcerptKey;
    contentKey: ContentPageContentKey;
    seoTitleKey: ContentPageSeoTitleKey;
    seoDescriptionKey: ContentPageSeoDescriptionKey;
    publishedKey: ContentPagePublishedKey;
};

const pageDefinitions: PageDefinition[] = [
    {
        key: 'contact_page',
        label: 'Liên hệ',
        subtitle: 'Trang liên hệ và hỗ trợ',
        description: 'Quản lý nội dung trang liên hệ và thông tin hỗ trợ khách hàng.',
        slug: 'lien-he',
        icon: Mail,
        titleKey: 'contact_page_title',
        excerptKey: 'contact_page_excerpt',
        contentKey: 'contact_page_content',
        seoTitleKey: 'contact_page_seo_title',
        seoDescriptionKey: 'contact_page_seo_description',
        publishedKey: 'contact_page_is_published',
    },
    {
        key: 'terms_page',
        label: 'Điều khoản',
        subtitle: 'Điều khoản sử dụng',
        description: 'Quản lý nội dung điều khoản sử dụng dịch vụ và trách nhiệm người dùng.',
        slug: 'dieu-khoan-su-dung',
        icon: Scale,
        titleKey: 'terms_page_title',
        excerptKey: 'terms_page_excerpt',
        contentKey: 'terms_page_content',
        seoTitleKey: 'terms_page_seo_title',
        seoDescriptionKey: 'terms_page_seo_description',
        publishedKey: 'terms_page_is_published',
    },
    {
        key: 'faq_page',
        label: 'FAQ',
        subtitle: 'Câu hỏi thường gặp',
        description: 'Cập nhật các câu hỏi thường gặp cho người dùng dịch vụ nạp game.',
        slug: 'cau-hoi-thuong-gap',
        icon: CircleHelp,
        titleKey: 'faq_page_title',
        excerptKey: 'faq_page_excerpt',
        contentKey: 'faq_page_content',
        seoTitleKey: 'faq_page_seo_title',
        seoDescriptionKey: 'faq_page_seo_description',
        publishedKey: 'faq_page_is_published',
    },
    {
        key: 'privacy_page',
        label: 'Chính sách bảo mật',
        subtitle: 'Bảo mật và quyền riêng tư',
        description: 'Quản lý nội dung chính sách bảo mật, dữ liệu và quyền riêng tư.',
        slug: 'chinh-sach-bao-mat',
        icon: ShieldCheck,
        titleKey: 'privacy_page_title',
        excerptKey: 'privacy_page_excerpt',
        contentKey: 'privacy_page_content',
        seoTitleKey: 'privacy_page_seo_title',
        seoDescriptionKey: 'privacy_page_seo_description',
        publishedKey: 'privacy_page_is_published',
    },
    {
        key: 'about_page',
        label: 'Giới thiệu',
        subtitle: 'Giới thiệu về hệ thống',
        description: 'Trình bày thông tin tổng quan, năng lực và định vị sản phẩm napcarot.vn.',
        slug: 'gioi-thieu',
        icon: Info,
        titleKey: 'about_page_title',
        excerptKey: 'about_page_excerpt',
        contentKey: 'about_page_content',
        seoTitleKey: 'about_page_seo_title',
        seoDescriptionKey: 'about_page_seo_description',
        publishedKey: 'about_page_is_published',
    },
    {
        key: 'api_usage_policy',
        label: 'Chính sách sử dụng',
        subtitle: 'Giới hạn và vận hành API',
        description: 'Quản lý chính sách sử dụng API, quota, xử lý lỗi và các giới hạn dịch vụ.',
        slug: 'chinh-sach-su-dung',
        icon: Landmark,
        titleKey: 'api_usage_policy_title',
        excerptKey: 'api_usage_policy_excerpt',
        contentKey: 'api_usage_policy_content',
        seoTitleKey: 'api_usage_policy_seo_title',
        seoDescriptionKey: 'api_usage_policy_seo_description',
        publishedKey: 'api_usage_policy_is_published',
    },
    {
        key: 'disclaimer',
        label: 'Miễn trừ trách nhiệm',
        subtitle: 'Giới hạn trách nhiệm',
        description: 'Trình bày phạm vi miễn trừ trách nhiệm và cảnh báo vận hành.',
        slug: 'mien-tru-trach-nhiem',
        icon: ShieldQuestion,
        titleKey: 'disclaimer_title',
        excerptKey: 'disclaimer_excerpt',
        contentKey: 'disclaimer_content',
        seoTitleKey: 'disclaimer_seo_title',
        seoDescriptionKey: 'disclaimer_seo_description',
        publishedKey: 'disclaimer_is_published',
    },
    {
        key: 'system_status',
        label: 'Trạng thái hệ thống',
        subtitle: 'Tình trạng vận hành',
        description: 'Cập nhật trạng thái vận hành, bảo trì và sự cố của hệ thống.',
        slug: 'trang-thai-he-thong',
        icon: Flag,
        titleKey: 'system_status_title',
        excerptKey: 'system_status_excerpt',
        contentKey: 'system_status_content',
        seoTitleKey: 'system_status_seo_title',
        seoDescriptionKey: 'system_status_seo_description',
        publishedKey: 'system_status_is_published',
    },
    {
        key: 'system_updates',
        label: 'Cập nhật hệ thống',
        subtitle: 'Lịch sử thay đổi',
        description: 'Quản lý changelog, lịch sử cập nhật và thông báo nâng cấp dịch vụ.',
        slug: 'cap-nhat-he-thong',
        icon: BookOpenText,
        titleKey: 'system_updates_title',
        excerptKey: 'system_updates_excerpt',
        contentKey: 'system_updates_content',
        seoTitleKey: 'system_updates_seo_title',
        seoDescriptionKey: 'system_updates_seo_description',
        publishedKey: 'system_updates_is_published',
    },
];

const createEmptyForm = (): ContentPageSettingsType => ({
    contact_page_title: 'Liên hệ',
    contact_page_excerpt: '',
    contact_page_content: [],
    contact_page_seo_title: '',
    contact_page_seo_description: '',
    contact_page_is_published: true,
    terms_page_title: 'Điều khoản sử dụng',
    terms_page_excerpt: '',
    terms_page_content: [],
    terms_page_seo_title: '',
    terms_page_seo_description: '',
    terms_page_is_published: true,
    faq_page_title: 'Câu hỏi thường gặp',
    faq_page_excerpt: '',
    faq_page_content: [],
    faq_page_seo_title: '',
    faq_page_seo_description: '',
    faq_page_is_published: true,
    privacy_page_title: 'Chính sách bảo mật',
    privacy_page_excerpt: '',
    privacy_page_content: [],
    privacy_page_seo_title: '',
    privacy_page_seo_description: '',
    privacy_page_is_published: true,
    about_page_title: 'Giới thiệu',
    about_page_excerpt: '',
    about_page_content: [],
    about_page_seo_title: '',
    about_page_seo_description: '',
    about_page_is_published: true,
    refund_policy_title: 'Chính sách hoàn tiền',
    refund_policy_excerpt: '',
    refund_policy_content: [],
    refund_policy_seo_title: '',
    refund_policy_seo_description: '',
    refund_policy_is_published: true,
    payment_policy_title: 'Chính sách thanh toán',
    payment_policy_excerpt: '',
    payment_policy_content: [],
    payment_policy_seo_title: '',
    payment_policy_seo_description: '',
    payment_policy_is_published: true,
    api_usage_policy_title: 'Chính sách sử dụng',
    api_usage_policy_excerpt: '',
    api_usage_policy_content: [],
    api_usage_policy_seo_title: '',
    api_usage_policy_seo_description: '',
    api_usage_policy_is_published: true,
    disclaimer_title: 'Miễn trừ trách nhiệm',
    disclaimer_excerpt: '',
    disclaimer_content: [],
    disclaimer_seo_title: '',
    disclaimer_seo_description: '',
    disclaimer_is_published: true,
    system_status_title: 'Trạng thái hệ thống',
    system_status_excerpt: '',
    system_status_content: [],
    system_status_seo_title: '',
    system_status_seo_description: '',
    system_status_is_published: true,
    system_updates_title: 'Cập nhật hệ thống',
    system_updates_excerpt: '',
    system_updates_content: [],
    system_updates_seo_title: '',
    system_updates_seo_description: '',
    system_updates_is_published: true,
});

const loading = ref(true);
const saving = ref(false);
const activeTab = ref<ContentPageTabKey>('contact_page');
const editorKey = ref(0);
const saveState = ref<'draft' | 'saved'>('draft');
const lastSavedAt = ref<string>('');
const form = ref<ContentPageSettingsType>(createEmptyForm());

const normalizeSettings = (settings?: Partial<ContentPageSettingsType>): ContentPageSettingsType => ({
    ...createEmptyForm(),
    ...settings,
    contact_page_content: Array.isArray(settings?.contact_page_content) ? settings.contact_page_content : [],
    terms_page_content: Array.isArray(settings?.terms_page_content) ? settings.terms_page_content : [],
    faq_page_content: Array.isArray(settings?.faq_page_content) ? settings.faq_page_content : [],
    privacy_page_content: Array.isArray(settings?.privacy_page_content) ? settings.privacy_page_content : [],
    about_page_content: Array.isArray(settings?.about_page_content) ? settings.about_page_content : [],
    refund_policy_content: Array.isArray(settings?.refund_policy_content) ? settings.refund_policy_content : [],
    payment_policy_content: Array.isArray(settings?.payment_policy_content) ? settings.payment_policy_content : [],
    api_usage_policy_content: Array.isArray(settings?.api_usage_policy_content) ? settings.api_usage_policy_content : [],
    disclaimer_content: Array.isArray(settings?.disclaimer_content) ? settings.disclaimer_content : [],
    system_status_content: Array.isArray(settings?.system_status_content) ? settings.system_status_content : [],
    system_updates_content: Array.isArray(settings?.system_updates_content) ? settings.system_updates_content : [],
});

const currentPage = computed(() => pageDefinitions.find((page) => page.key === activeTab.value) ?? pageDefinitions[0]);

const setField = (key: keyof ContentPageSettingsType, value: unknown): void => {
    form.value = {
        ...form.value,
        [key]: value,
    };
    saveState.value = 'draft';
};

const titleValue = computed({
    get: () => String(form.value[currentPage.value.titleKey] ?? ''),
    set: (value: string) => setField(currentPage.value.titleKey, value),
});

const excerptValue = computed({
    get: () => String(form.value[currentPage.value.excerptKey] ?? ''),
    set: (value: string) => setField(currentPage.value.excerptKey, value),
});

const seoTitleValue = computed({
    get: () => String(form.value[currentPage.value.seoTitleKey] ?? ''),
    set: (value: string) => setField(currentPage.value.seoTitleKey, value),
});

const seoDescriptionValue = computed({
    get: () => String(form.value[currentPage.value.seoDescriptionKey] ?? ''),
    set: (value: string) => setField(currentPage.value.seoDescriptionKey, value),
});

const isPublishedValue = computed({
    get: () => Boolean(form.value[currentPage.value.publishedKey] ?? true),
    set: (value: boolean) => setField(currentPage.value.publishedKey, value),
});

const currentContent = computed(() => {
    const value = form.value[currentPage.value.contentKey];
    return Array.isArray(value) ? value : [];
});

const contentStats = computed(() =>
    pageDefinitions.map((page) => ({
        ...page,
        blocks: Array.isArray(form.value[page.contentKey]) ? form.value[page.contentKey].length : 0,
        published: Boolean(form.value[page.publishedKey] ?? true),
    })),
);

const seoChecklist = computed(() => {
    const seoTitleLength = seoTitleValue.value.trim().length;
    const seoDescriptionLength = seoDescriptionValue.value.trim().length;
    const contentBlockCount = currentContent.value.length;

    return [
        {
            label: 'SEO title hợp lệ',
            passed: seoTitleLength >= 20 && seoTitleLength <= 60,
            value: `${seoTitleLength}/60 ký tự`,
        },
        {
            label: 'SEO description hợp lệ',
            passed: seoDescriptionLength >= 60 && seoDescriptionLength <= 160,
            value: `${seoDescriptionLength}/160 ký tự`,
        },
        {
            label: 'Có nội dung chính',
            passed: contentBlockCount > 0,
            value: `${contentBlockCount} khối`,
        },
    ];
});

const seoScore = computed(() => {
    const passed = seoChecklist.value.filter((item) => item.passed).length;
    return Math.round((passed / seoChecklist.value.length) * 100);
});

const formattedLastSavedAt = computed(() => {
    if (lastSavedAt.value === '') {
        return 'Chưa có dữ liệu';
    }

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(lastSavedAt.value));
});

const previewUrl = computed(() => `/${currentPage.value.slug}`);

const openPreview = (): void => {
    window.open(previewUrl.value, '_blank', 'noopener,noreferrer');
};

const loadData = async (): Promise<void> => {
    try {
        loading.value = true;
        const response = await adminSettingService.getContentPages();
        form.value = normalizeSettings(response.settings);
        editorKey.value += 1;
        lastSavedAt.value = new Date().toISOString();
        saveState.value = 'saved';
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const saveContentPages = async (): Promise<void> => {
    try {
        saving.value = true;
        const payload: ContentPageSettingsType = {
            ...form.value,
            contact_page_content: await uploadEditorImages(form.value.contact_page_content),
            terms_page_content: await uploadEditorImages(form.value.terms_page_content),
            faq_page_content: await uploadEditorImages(form.value.faq_page_content),
            privacy_page_content: await uploadEditorImages(form.value.privacy_page_content),
            about_page_content: await uploadEditorImages(form.value.about_page_content),
            refund_policy_content: await uploadEditorImages(form.value.refund_policy_content),
            payment_policy_content: await uploadEditorImages(form.value.payment_policy_content),
            api_usage_policy_content: await uploadEditorImages(form.value.api_usage_policy_content),
            disclaimer_content: await uploadEditorImages(form.value.disclaimer_content),
            system_status_content: await uploadEditorImages(form.value.system_status_content),
            system_updates_content: await uploadEditorImages(form.value.system_updates_content),
        };

        const response = await adminSettingService.updateContentPages(payload);
        form.value = normalizeSettings(response.settings);
        editorKey.value += 1;
        saveState.value = 'saved';
        lastSavedAt.value = new Date().toISOString();

        handleSuccessResponse({
            data: {
                status: true,
                message: 'Đã cập nhật nội dung trang.',
            },
        });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

const updateContent = (value: unknown[]): void => {
    setField(currentPage.value.contentKey, Array.isArray(value) ? value : []);
};

onMounted(async () => {
    await loadData();
});
</script>

<template>
    <div class="space-y-5">
        <Breadcrumb title="Cấu hình nội dung" description="Dashboard / Cấu hình nội dung" />

        <section class="space-y-5">
            <div
                class="flex flex-col gap-4 rounded-[12px] border border-slate-200 bg-white px-5 py-5 shadow-sm xl:flex-row xl:items-start xl:justify-between"
            >
                <div class="max-w-3xl">
                    <p class="text-sm font-medium uppercase tracking-[0.14em] text-indigo-500">Dashboard</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Cấu hình nội dung</h1>
                    <p class="mt-3 text-sm leading-7 text-slate-500 sm:text-base">
                        Quản lý nội dung liên hệ, điều khoản, FAQ, chính sách và giới thiệu. Giữ thông tin luôn cập nhật và nhất quán cho người dùng.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold"
                        :class="
                            saveState === 'saved'
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : 'border-amber-200 bg-amber-50 text-amber-700'
                        "
                    >
                        <span class="inline-block h-2.5 w-2.5 rounded-full" :class="saveState === 'saved' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                        {{ saveState === 'saved' ? 'Đã lưu nháp' : 'Có thay đổi chưa lưu' }}
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-[10px] border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50"
                        @click="openPreview"
                    >
                        <Eye class="h-4 w-4" />
                        Xem trước
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-[10px] bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="saving || loading"
                        @click="saveContentPages"
                    >
                        {{ saving ? 'Đang lưu...' : 'Lưu nội dung' }}
                    </button>
                </div>
            </div>

            <div v-if="loading" class="grid gap-4 xl:grid-cols-[280px_minmax(0,1fr)_280px]">
                <div class="space-y-3">
                    <div v-for="skeleton in 6" :key="`nav-${skeleton}`" class="h-24 animate-pulse rounded-[12px] bg-slate-100"></div>
                </div>
                <div class="space-y-4">
                    <div class="h-36 animate-pulse rounded-[12px] bg-slate-100"></div>
                    <div class="h-[420px] animate-pulse rounded-[12px] bg-slate-100"></div>
                </div>
                <div class="space-y-4">
                    <div class="h-72 animate-pulse rounded-[12px] bg-slate-100"></div>
                    <div class="h-72 animate-pulse rounded-[12px] bg-slate-100"></div>
                </div>
            </div>

            <div v-else class="grid gap-4 xl:grid-cols-[280px_minmax(0,1fr)_280px]">
                <aside class="space-y-3">
                    <button
                        v-for="page in contentStats"
                        :key="page.key"
                        type="button"
                        class="group flex w-full items-center gap-3 rounded-[14px] border px-4 py-4 text-left transition"
                        :class="
                            activeTab === page.key
                                ? 'border-indigo-200 bg-gradient-to-r from-indigo-600 to-violet-500 text-white shadow-[0_16px_34px_rgba(79,70,229,0.22)]'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:shadow-sm'
                        "
                        @click="activeTab = page.key"
                    >
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                            :class="activeTab === page.key ? 'bg-white/20 text-white' : 'bg-slate-50 text-slate-700'"
                        >
                            <component :is="page.icon" class="h-5 w-5" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="text-base font-semibold">{{ page.label }}</p>
                            <p class="mt-1 truncate text-sm" :class="activeTab === page.key ? 'text-indigo-100' : 'text-slate-500'">
                                {{ page.subtitle }}
                            </p>
                        </div>

                        <div class="text-right">
                            <p class="text-xs font-medium" :class="activeTab === page.key ? 'text-indigo-100' : 'text-slate-400'">
                                {{ page.blocks }} khối
                            </p>
                            <p
                                class="mt-1 text-xs font-semibold"
                                :class="page.published ? 'text-emerald-200' : activeTab === page.key ? 'text-indigo-100' : 'text-slate-400'"
                            >
                                {{ page.published ? 'Công khai' : 'Ẩn' }}
                            </p>
                        </div>
                    </button>
                </aside>

                <article class="space-y-4 rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-slate-950">{{ currentPage.label }}</h2>
                        <p class="mt-2 text-sm leading-7 text-slate-500">{{ currentPage.description }}</p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="space-y-2">
                            <span class="text-sm font-semibold text-slate-900">Tiêu đề trang</span>
                            <input
                                v-model="titleValue"
                                type="text"
                                class="w-full rounded-[10px] border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-500"
                                placeholder="Nhập tiêu đề trang"
                            />
                        </label>

                        <label class="space-y-2">
                            <span class="text-sm font-semibold text-slate-900">Slug / định danh</span>
                            <input
                                :value="currentPage.slug"
                                type="text"
                                disabled
                                class="w-full rounded-[10px] border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500 outline-none"
                            />
                        </label>
                    </div>

                    <label class="space-y-2">
                        <span class="text-sm font-semibold text-slate-900">Mô tả ngắn</span>
                        <div class="rounded-[10px] border border-slate-200 px-4 py-3">
                            <textarea
                                v-model="excerptValue"
                                rows="3"
                                maxlength="160"
                                class="w-full resize-none border-0 p-0 text-sm leading-7 text-slate-700 outline-none"
                                placeholder="Mô tả ngắn gọn cho trang này"
                            ></textarea>
                            <div class="mt-2 text-right text-xs text-slate-400">{{ excerptValue.length }}/160</div>
                        </div>
                    </label>

                    <div class="space-y-2">
                        <span class="text-sm font-semibold text-slate-900">Nội dung chính</span>
                        <div class="rounded-[10px] border border-slate-200 p-2">
                            <Editor
                                :key="`${editorKey}-${activeTab}`"
                                :value="currentContent"
                                @update:value="(value: unknown[]) => updateContent(value)"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4">
                        <label class="space-y-2">
                            <span class="text-sm font-semibold text-slate-900">SEO title</span>
                            <input
                                v-model="seoTitleValue"
                                type="text"
                                maxlength="60"
                                class="w-full rounded-[10px] border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-500"
                                placeholder="Ví dụ: Liên hệ - Hỗ trợ khách hàng 24/7"
                            />
                        </label>

                        <label class="space-y-2">
                            <span class="text-sm font-semibold text-slate-900">SEO description</span>
                            <div class="rounded-[10px] border border-slate-200 px-4 py-3">
                                <textarea
                                    v-model="seoDescriptionValue"
                                    rows="3"
                                    maxlength="160"
                                    class="w-full resize-none border-0 p-0 text-sm leading-7 text-slate-700 outline-none"
                                    placeholder="Mô tả dùng cho SEO và social share"
                                ></textarea>
                                <div class="mt-2 text-right text-xs text-slate-400">{{ seoDescriptionValue.length }}/160</div>
                            </div>
                        </label>
                    </div>

                    <label class="flex items-center gap-3 rounded-[10px] border border-slate-200 bg-slate-50 px-4 py-3">
                        <input
                            v-model="isPublishedValue"
                            type="checkbox"
                            class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Trạng thái hiển thị</p>
                            <p class="text-sm text-slate-500">Bật để công khai trang này trên website.</p>
                        </div>
                    </label>
                </article>

                <aside class="space-y-4">
                    <div class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-xl font-semibold text-slate-950">Thông tin xuất bản</h3>
                        <div class="mt-5 space-y-5 text-sm text-slate-600">
                            <div>
                                <p class="text-xs uppercase tracking-[0.14em] text-slate-400">Cập nhật lần cuối</p>
                                <p class="mt-2 text-base font-semibold text-slate-900">{{ formattedLastSavedAt }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.14em] text-slate-400">Slug công khai</p>
                                <p class="mt-2 text-base font-semibold text-slate-900">/{{ currentPage.slug }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.14em] text-slate-400">Trạng thái</p>
                                <span
                                    class="mt-2 inline-flex rounded-full px-3 py-1 text-sm font-semibold"
                                    :class="isPublishedValue ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ isPublishedValue ? 'Đang hiển thị' : 'Đang ẩn' }}
                                </span>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 transition hover:text-indigo-700"
                                @click="openPreview"
                            >
                                <Eye class="h-4 w-4" />
                                Xem trước trang
                            </button>
                        </div>
                    </div>

                    <div class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-xl font-semibold text-slate-950">Gợi ý SEO</h3>
                        <div class="mt-5 flex items-center gap-4">
                            <div
                                class="flex h-20 w-20 items-center justify-center rounded-full border-4 border-indigo-500/20 text-lg font-bold text-indigo-600"
                            >
                                {{ seoScore }}/100
                            </div>
                            <div>
                                <p class="text-xl font-semibold text-emerald-600">
                                    {{ seoScore >= 67 ? 'Tốt' : seoScore >= 34 ? 'Trung bình' : 'Cần cải thiện' }}
                                </p>
                                <p class="mt-1 text-sm leading-6 text-slate-500">Nội dung được đánh giá dựa trên metadata và nội dung chính.</p>
                            </div>
                        </div>

                        <div class="mt-5 space-y-3">
                            <div
                                v-for="item in seoChecklist"
                                :key="item.label"
                                class="flex items-start justify-between gap-3 rounded-[10px] border border-slate-200 px-3 py-3"
                            >
                                <div class="flex items-start gap-2">
                                    <span
                                        class="mt-0.5 inline-block h-2.5 w-2.5 rounded-full"
                                        :class="item.passed ? 'bg-emerald-500' : 'bg-slate-300'"
                                    ></span>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">{{ item.label }}</p>
                                        <p class="text-xs text-slate-500">{{ item.value }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="mt-5 inline-flex w-full items-center justify-center rounded-[10px] border border-slate-200 px-4 py-3 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50"
                            @click="openPreview"
                        >
                            Xem phân tích chi tiết
                        </button>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</template>
