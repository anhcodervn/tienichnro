<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import ToolSeoEditor from '@/pages/admin/tools/ToolSeoEditor.vue';
import { adminGamePasswordService, type PasswordConfiguration } from '@/services/admin-game-password.service';
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
        seo.value = (await adminServiceManagement.get('game_password')).seo;
    } catch (error) {
        seoError.value = true;
        handleErrorResponse(error);
    } finally {
        seoLoading.value = false;
    }
}
function applyConfiguration(config: PasswordConfiguration) {
    price.value = config.price;
}
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref('');
async function load() {
    try {
        const config = await adminGamePasswordService.configuration();
        applyConfiguration(config);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
}
async function save() {
    if (saving.value) return;
    if (price.value === null || !Number.isInteger(price.value) || price.value < 0 || price.value > 100000000) {
        errorMessage.value = 'Nhập giá từ 0 đến 100.000.000 đồng.';
        return;
    }
    saving.value = true;
    errorMessage.value = '';
    try {
        const config = await adminGamePasswordService.configure({
            price: price.value,
        });
        applyConfiguration(config);
        handleSuccessResponse({ data: { status: true, message: 'Đã lưu cấu hình đổi mật khẩu.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
}
onMounted(load);
</script>
<template>
    <div class="grid gap-4 text-slate-900 dark:text-slate-100">
        <Breadcrumb title="Cấu hình đổi mật khẩu game" description="Thiết lập giá đổi mật khẩu cho mỗi tài khoản." />
        <div class="flex flex-wrap gap-2" role="tablist" aria-label="Cấu hình đổi mật khẩu">
            <button
                id="password-configuration-tab"
                type="button"
                role="tab"
                aria-controls="password-configuration-panel"
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
                id="password-seo-tab"
                type="button"
                role="tab"
                aria-controls="password-seo-panel"
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
        <section id="password-configuration-panel" v-show="tab === 'configuration'" role="tabpanel" aria-labelledby="password-configuration-tab">
            <p v-if="loading" role="status" class="text-sm text-slate-500">Đang tải cấu hình…</p>
            <form
                v-else
                class="grid gap-5 rounded-[5px] border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                @submit.prevent="save"
            >
                <label class="grid gap-2 text-sm font-semibold"
                    >Giá đổi mật khẩu (đ / tài khoản)<input
                        v-model.number="price"
                        type="number"
                        min="0"
                        max="100000000"
                        required
                        :disabled="saving"
                        class="min-h-11 rounded-[5px] border border-slate-300 bg-white px-3 dark:border-slate-600 dark:bg-slate-800"
                    /><span class="text-xs font-normal text-slate-500"
                        >Giá 0 là miễn phí. Người dùng cần nhập API Key và API Secret riêng ở form client.</span
                    ></label
                >
                <div class="rounded-[5px] bg-emerald-50 p-4 text-sm leading-7 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">
                    <p>Yêu cầu được xử lý qua queue riêng. Cookie chỉ nằm trong RAM và được dọn sau mỗi tài khoản.</p>
                    <p>Thêm tài khoản sẽ trừ phí một lần và tạo task đổi mật khẩu ngay.</p>
                    <p>Đơn lỗi hoặc pending quá 30 phút được chạy lại miễn phí. Không tự hoàn phí khi lỗi, không tự chạy lại đơn đang xử lý.</p>
                </div>
                <p v-if="errorMessage" role="alert" class="text-sm text-rose-600">{{ errorMessage }}</p>
                <div>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="min-h-10 rounded-[5px] bg-emerald-600 px-5 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {{ saving ? 'Đang lưu…' : 'Lưu cấu hình' }}
                    </button>
                </div>
            </form>
        </section>
        <section id="password-seo-panel" v-show="tab === 'seo'" role="tabpanel" aria-labelledby="password-seo-tab">
            <p v-if="seoLoading" role="status" class="text-sm text-slate-500">Đang tải bài SEO…</p>
            <div v-if="seoError" class="grid gap-3 rounded-[5px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                <p>Chưa tải được bài SEO đổi mật khẩu.</p>
                <button type="button" class="w-fit font-semibold underline" @click="openSeo">Thử lại</button>
            </div>
            <ToolSeoEditor v-if="seo" v-model="seo" code="game_password" @busy="seoBusy = $event" />
        </section>
    </div>
</template>
