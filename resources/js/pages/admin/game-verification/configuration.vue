<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import ToolSeoEditor from '@/pages/admin/tools/ToolSeoEditor.vue';
import { adminGameVerificationService } from '@/services/admin-game-verification.service';
import { adminServiceManagement } from '@/services/admin-service-management.service';
import type { ToolSeoSettings } from '@/types/tool-seo.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { onMounted, ref } from 'vue';

const price = ref<number | null>(null);
const tab = ref('configuration');
const seo = ref<ToolSeoSettings | null>(null);
const seoLoading = ref(false);
const seoBusy = ref(false);
const seoError = ref(false);
async function openSeo() {
    if (saving.value || seoBusy.value || seoLoading.value) return;
    tab.value = 'seo';
    if (seo.value) return;
    seoLoading.value = true;
    seoError.value = false;
    try {
        seo.value = (await adminServiceManagement.get('account_verification')).seo;
    } catch (error) {
        seoError.value = true;
        handleErrorResponse(error);
    } finally {
        seoLoading.value = false;
    }
}

const loading = ref(true);
const saving = ref(false);
const errorMessage = ref('');
const load = async () => {
    try {
        price.value = (await adminGameVerificationService.configuration()).price;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};
const save = async () => {
    if (saving.value) return;
    if (typeof price.value !== 'number' || !Number.isInteger(price.value) || price.value < 0 || price.value > 100000000) {
        errorMessage.value = 'Nhập phí từ 0 đến 100.000.000 đồng.';
        return;
    }
    saving.value = true;
    errorMessage.value = '';
    try {
        price.value = (await adminGameVerificationService.configure(price.value)).price;
        handleSuccessResponse({ data: { status: true, message: 'Đã lưu cấu hình xác minh tài khoản.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};
onMounted(load);
</script>

<template>
    <div class="grid gap-4 text-slate-900 dark:text-slate-100">
        <Breadcrumb title="Cấu hình xác minh tài khoản" description="Thiết lập phí lấy cú pháp SMS cho mỗi tài khoản game." />
        <div class="flex flex-wrap gap-2" role="tablist" aria-label="Cấu hình xác minh tài khoản">
            <button
                id="image-configuration-tab"
                type="button"
                role="tab"
                aria-controls="image-configuration-panel"
                :aria-selected="tab === 'configuration'"
                :disabled="saving || seoBusy || seoLoading"
                class="min-h-10 rounded-[5px] border px-4 py-2 text-sm font-semibold disabled:opacity-50"
                :class="
                    tab === 'configuration'
                        ? 'border-emerald-700 bg-emerald-700 text-white'
                        : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900'
                "
                @click="tab = 'configuration'"
            >
                Giá dịch vụ
            </button>
            <button
                id="image-seo-tab"
                type="button"
                role="tab"
                aria-controls="image-seo-panel"
                :aria-selected="tab === 'seo'"
                :disabled="saving || seoBusy || seoLoading"
                class="min-h-10 rounded-[5px] border px-4 py-2 text-sm font-semibold disabled:opacity-50"
                :class="
                    tab === 'seo'
                        ? 'border-emerald-700 bg-emerald-700 text-white'
                        : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900'
                "
                @click="openSeo"
            >
                Bài SEO
            </button>
        </div>
        <section id="image-configuration-panel" v-show="tab === 'configuration'" role="tabpanel" aria-labelledby="image-configuration-tab">
            <p v-if="loading" role="status" class="text-sm text-slate-500">Đang tải cấu hình…</p>
            <form
                v-else
                class="grid gap-5 rounded-[5px] border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                @submit.prevent="save"
            >
                <label class="grid gap-2 text-sm font-semibold"
                    >Phí lấy cú pháp (đ / tài khoản)
                    <input
                        v-model.number="price"
                        type="number"
                        min="0"
                        max="100000000"
                        step="1"
                        required
                        placeholder="Nhập mức phí"
                        class="min-h-11 w-full rounded-[5px] border border-slate-300 bg-white px-3 dark:border-slate-600 dark:bg-slate-800"
                        :disabled="saving"
                    />
                    <span class="text-xs font-normal leading-5 text-slate-500"
                        >0 đồng là miễn phí. Khi chưa thiết lập giá, người dùng chưa thể thêm tài khoản.</span
                    >
                </label>
                <div class="rounded-[5px] bg-emerald-50 p-4 text-sm leading-7 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">
                    <p>Thêm tài khoản → trừ phí một lần và tạo task lấy cú pháp SMS trong nền.</p>
                    <p>Đã lấy cú pháp chưa có nghĩa là SMS đã gửi hoặc tài khoản đã xác minh.</p>
                    <p>Đơn lỗi hoặc pending quá 30 phút được chạy lại miễn phí. Cookie chỉ nằm trong RAM.</p>
                    <p>Đổi giá chỉ áp dụng cho tài khoản thêm sau khi lưu cấu hình.</p>
                </div>
                <p v-if="errorMessage" role="alert" class="text-sm text-rose-600">{{ errorMessage }}</p>
                <div>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="min-h-10 rounded-[5px] bg-emerald-600 px-5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {{ saving ? 'Đang lưu…' : 'Lưu cấu hình' }}
                    </button>
                </div>
            </form>
        </section>
        <section id="image-seo-panel" v-show="tab === 'seo'" role="tabpanel" aria-labelledby="image-seo-tab">
            <p v-if="seoLoading" role="status" class="text-sm text-slate-500">Đang tải bài SEO…</p>
            <div v-if="seoError" class="grid gap-3 rounded-[5px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                <p>Chưa tải được bài SEO xác minh tài khoản.</p>
                <button type="button" class="w-fit font-semibold underline" @click="openSeo">Thử lại</button>
            </div>
            <ToolSeoEditor v-if="seo" v-model="seo" code="account_verification" @busy="seoBusy = $event" />
        </section>
    </div>
</template>
