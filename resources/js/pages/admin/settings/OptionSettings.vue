<template>
    <div class="space-y-4">
        <Breadcrumb title="Nội dung pháp lý" description="Quản lý điều khoản, chính sách bảo mật và chính sách hoàn tiền dùng chung cho toàn hệ thống." />

        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Nội dung chung toàn website</h2>
                    <p class="text-sm text-slate-500">Các văn bản này được dùng cho nhóm trang pháp lý và thông tin công khai của napcarot.vn.</p>
                </div>

                <button
                    class="rounded-[10px] bg-slate-900 px-4 py-2 text-sm text-white disabled:cursor-not-allowed disabled:bg-slate-300"
                    :disabled="isSaving || !isRootDomain"
                    @click="submitForm"
                >
                    {{ !isRootDomain ? "Chỉ main domain được chỉnh sửa" : isSaving ? "Đang lưu" : "Lưu nội dung" }}
                </button>
            </div>

            <div class="space-y-4 p-4">
                <div v-if="!isRootDomain" class="rounded-[10px] border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                    Đây là cấu hình global. Domain hiện tại chỉ có quyền đọc nội dung, không thể chỉnh sửa.
                </div>

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div class="space-y-4">
                        <div class="border-b border-slate-200 px-1 pb-1">
                            <div class="no-scrollbar flex gap-2 overflow-x-auto">
                                <button
                                    v-for="tab in tabs"
                                    :key="tab.key"
                                    type="button"
                                    class="min-w-fit rounded-[10px] border px-4 py-3 text-left transition-all"
                                    :class="
                                        activeTab === tab.key
                                            ? 'border-slate-900 bg-slate-900 text-white'
                                            : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300 hover:bg-slate-100 hover:text-slate-900'
                                    "
                                    @click="activeTab = tab.key"
                                >
                                    <p class="text-sm font-semibold">{{ tab.label }}</p>
                                    <p class="mt-1 text-xs" :class="activeTab === tab.key ? 'text-slate-300' : 'text-slate-500'">
                                        {{ tab.description }}
                                    </p>
                                </button>
                            </div>
                        </div>

                        <div class="rounded-[10px] border border-slate-200 bg-slate-50 px-4 py-3">
                            <h3 class="text-sm font-semibold text-slate-900">{{ activeConfig.label }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ activeConfig.help }}</p>
                        </div>

                        <div class="rounded-[10px] border border-slate-200 p-2">
                            <Editor
                                :key="editorKey"
                                :value="formData[activeTab]"
                                @update:value="(value: unknown[]) => handleContentChange(activeTab, value)"
                            />
                        </div>
                    </div>

                    <aside class="space-y-3">
                        <div class="rounded-[10px] border border-slate-200 bg-white p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Gợi ý</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">Viết rõ, ngắn và dễ hiểu</p>
                            <div class="mt-3 rounded-[10px] border border-sky-100 bg-sky-50 px-3 py-3 text-sm text-slate-600">
                                Ưu tiên mô tả rõ quyền lợi, trách nhiệm, trường hợp ngoại lệ và cách người dùng liên hệ hỗ trợ khi cần.
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <div v-for="tab in tabs" :key="`${tab.key}-count`" class="rounded-[10px] border border-slate-200 bg-white px-4 py-3">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">{{ tab.shortLabel }}</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">{{ formData[tab.key].length }}</p>
                                <p class="text-sm text-slate-500">khối nội dung</p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import Breadcrumb from "@/components/MasterLayouts/Breadcrumb/index.vue";
import Editor from "@/components/shared/Editor/index.vue";
import { adminSettingService } from "@/services/admin-setting.service";
import type { OptionSettingType } from "@/types/setting.type";
import { uploadEditorImages } from "@/utils/editor-image-upload";
import { handleErrorResponse, handleSuccessResponse } from "@/utils/response";
import { computed, onMounted, ref } from "vue";

type OptionTabKey = "terms_of_use" | "privacy_policy" | "refund_policy";

const isSaving = ref(false);
const isRootDomain = ref(Boolean((window as Window & { APP_IS_ROOT_DOMAIN?: boolean }).APP_IS_ROOT_DOMAIN));
const editorKey = ref(0);
const activeTab = ref<OptionTabKey>("terms_of_use");

const tabs: Array<{
    key: OptionTabKey;
    label: string;
    shortLabel: string;
    description: string;
    help: string;
}> = [
    {
        key: "terms_of_use",
        label: "Điều khoản sử dụng",
        shortLabel: "Điều khoản",
        description: "Quy định chung khi người dùng truy cập và sử dụng website.",
        help: "Mô tả điều kiện sử dụng, trách nhiệm người dùng và phạm vi áp dụng dịch vụ.",
    },
    {
        key: "privacy_policy",
        label: "Chính sách bảo mật",
        shortLabel: "Bảo mật",
        description: "Cam kết xử lý, lưu trữ và bảo vệ dữ liệu người dùng.",
        help: "Trình bày cách thu thập dữ liệu, mục đích sử dụng và quyền riêng tư.",
    },
    {
        key: "refund_policy",
        label: "Chính sách hoàn tiền",
        shortLabel: "Hoàn tiền",
        description: "Điều kiện, phạm vi và quy trình hoàn tiền cho khách hàng.",
        help: "Nêu rõ trường hợp được hoàn tiền, thời gian xử lý và các ngoại lệ.",
    },
];

const formData = ref<Required<OptionSettingType>>({
    terms_of_use: [],
    privacy_policy: [],
    refund_policy: [],
});

const activeConfig = computed(() => tabs.find((item) => item.key === activeTab.value) ?? tabs[0]);

const loadData = async (): Promise<void> => {
    try {
        const data = await adminSettingService.getOptions();
        formData.value = {
            terms_of_use: Array.isArray(data.settings.terms_of_use) ? data.settings.terms_of_use : [],
            privacy_policy: Array.isArray(data.settings.privacy_policy) ? data.settings.privacy_policy : [],
            refund_policy: Array.isArray(data.settings.refund_policy) ? data.settings.refund_policy : [],
        };
        editorKey.value += 1;
    } catch (err) {
        handleErrorResponse(err);
    }
};

const handleContentChange = (field: OptionTabKey, value: unknown[]): void => {
    formData.value = {
        ...formData.value,
        [field]: Array.isArray(value) ? value : [],
    };
};

const submitForm = async (): Promise<void> => {
    if (!isRootDomain.value) {
        return;
    }

    try {
        isSaving.value = true;

        const res = await adminSettingService.updateOptions({
            terms_of_use: await uploadEditorImages(formData.value.terms_of_use),
            privacy_policy: await uploadEditorImages(formData.value.privacy_policy),
            refund_policy: await uploadEditorImages(formData.value.refund_policy),
        });

        formData.value = {
            terms_of_use: Array.isArray(res.settings.terms_of_use) ? res.settings.terms_of_use : [],
            privacy_policy: Array.isArray(res.settings.privacy_policy) ? res.settings.privacy_policy : [],
            refund_policy: Array.isArray(res.settings.refund_policy) ? res.settings.refund_policy : [],
        };

        editorKey.value += 1;
        handleSuccessResponse({ data: { status: true, message: "Cập nhật nội dung pháp lý thành công" } });
    } catch (err) {
        handleErrorResponse(err);
    } finally {
        isSaving.value = false;
    }
};

onMounted(loadData);
</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}

.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
