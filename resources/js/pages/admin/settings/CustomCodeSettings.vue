<script setup lang="ts">
import { adminSettingService } from '@/services/admin-setting.service';
import type { CustomCodeSettingType } from '@/types/setting.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Braces, Code2, LoaderCircle, Paintbrush, Save, ShieldAlert } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, onMounted, ref } from 'vue';

type ValidationErrors = Partial<Record<keyof CustomCodeSettingType, string>>;

type ApiError = {
    response?: {
        status?: number;
        data?: {
            errors?: Record<string, string[]>;
            data?: { errors?: Record<string, string[]> };
        };
    };
};

const MAX_CODE_LENGTH = 100_000;
const loading = ref(true);
const saving = ref(false);
const errors = ref<ValidationErrors>({});
const lastSavedJs = ref('');
const lastSavedJsEnabled = ref(false);
const form = ref<CustomCodeSettingType>({
    custom_css: '',
    custom_css_enabled: false,
    custom_js: '',
    custom_js_enabled: false,
});

const cssLength = computed(() => form.value.custom_css.length);
const jsLength = computed(() => form.value.custom_js.length);

const setForm = (settings: CustomCodeSettingType): void => {
    form.value = {
        custom_css: settings.custom_css ?? '',
        custom_css_enabled: Boolean(settings.custom_css_enabled),
        custom_js: settings.custom_js ?? '',
        custom_js_enabled: Boolean(settings.custom_js_enabled),
    };
    lastSavedJs.value = form.value.custom_js;
    lastSavedJsEnabled.value = form.value.custom_js_enabled;
};

const loadSettings = async (): Promise<void> => {
    try {
        loading.value = true;
        const response = await adminSettingService.getCustomCode();
        setForm(response.settings);
    } catch (error) {
        handleErrorResponse(error as Parameters<typeof handleErrorResponse>[0]);
    } finally {
        loading.value = false;
    }
};

const applyValidationErrors = (error: ApiError): boolean => {
    if (error.response?.status !== 422) {
        return false;
    }

    const responseErrors = error.response.data?.data?.errors ?? error.response.data?.errors ?? {};
    errors.value = Object.fromEntries(
        Object.entries(responseErrors).map(([field, messages]) => [field, messages[0] ?? 'Dữ liệu không hợp lệ.']),
    ) as ValidationErrors;

    return Object.keys(errors.value).length > 0;
};

const confirmJavaScriptChange = async (): Promise<boolean> => {
    const javascriptChanged = form.value.custom_js !== lastSavedJs.value || form.value.custom_js_enabled !== lastSavedJsEnabled.value;

    if (!form.value.custom_js_enabled && !javascriptChanged) {
        return true;
    }

    const result = await Swal.fire({
        icon: 'warning',
        title: 'Xác nhận JavaScript tùy chỉnh',
        text: 'Mã JavaScript có thể ảnh hưởng đến bảo mật và hoạt động của toàn bộ giao diện public. Bạn chắc chắn muốn lưu?',
        showCancelButton: true,
        confirmButtonText: 'Xác nhận lưu',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#4f46e5',
    });

    return result.isConfirmed;
};

const saveSettings = async (): Promise<void> => {
    errors.value = {};

    if (!(await confirmJavaScriptChange())) {
        return;
    }

    try {
        saving.value = true;
        const response = await adminSettingService.updateCustomCode({ ...form.value });
        setForm(response.settings);
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật mã CSS/JavaScript tùy chỉnh.' } });
    } catch (error) {
        if (!applyValidationErrors(error as ApiError)) {
            handleErrorResponse(error as Parameters<typeof handleErrorResponse>[0]);
        }
    } finally {
        saving.value = false;
    }
};

onMounted(loadSettings);
</script>

<template>
    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-4">
            <article class="rounded-[10px] border border-slate-200 bg-white p-4">
                <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <Braces class="h-5 w-5" aria-hidden="true" />
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Mã CSS/JavaScript tùy chỉnh</h3>
                            <p class="text-sm leading-6 text-slate-500">
                                Chỉ áp dụng cho giao diện public, không chạy trong trang quản trị và trang đăng nhập.
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-[10px] bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="loading || saving"
                        @click="saveSettings"
                    >
                        <LoaderCircle v-if="saving" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        <Save v-else class="h-4 w-4" aria-hidden="true" />
                        {{ saving ? 'Đang lưu...' : 'Lưu mã tùy chỉnh' }}
                    </button>
                </div>

                <div v-if="loading" class="grid gap-3 pt-4" aria-live="polite">
                    <div class="h-14 animate-pulse rounded-[10px] bg-slate-100"></div>
                    <div class="h-56 animate-pulse rounded-[10px] bg-slate-100"></div>
                    <div class="h-14 animate-pulse rounded-[10px] bg-slate-100"></div>
                    <div class="h-56 animate-pulse rounded-[10px] bg-slate-100"></div>
                </div>

                <div v-else class="grid gap-6 pt-4">
                    <fieldset class="grid gap-3">
                        <legend class="sr-only">CSS tùy chỉnh</legend>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <Paintbrush class="h-4 w-4 text-sky-600" aria-hidden="true" />
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-900">CSS tùy chỉnh</h4>
                                    <p class="text-xs text-slate-500">Được tải sau stylesheet chính để có thể ghi đè giao diện.</p>
                                </div>
                            </div>
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700">
                                <input v-model="form.custom_css_enabled" type="checkbox" class="peer sr-only" :disabled="saving" />
                                <span
                                    class="relative h-6 w-11 rounded-full bg-slate-300 transition after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition peer-checked:bg-indigo-600 peer-checked:after:translate-x-5 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 peer-focus-visible:ring-offset-2"
                                ></span>
                                Bật CSS tùy chỉnh
                            </label>
                        </div>
                        <textarea
                            id="custom-css"
                            v-model="form.custom_css"
                            rows="12"
                            :maxlength="MAX_CODE_LENGTH"
                            :aria-invalid="Boolean(errors.custom_css)"
                            aria-describedby="custom-css-help custom-css-error"
                            class="min-h-64 w-full resize-y rounded-[10px] border bg-slate-950 px-4 py-3 font-mono text-sm leading-6 text-slate-100 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20"
                            :class="errors.custom_css ? 'border-rose-400' : 'border-slate-700'"
                            placeholder=".site-header { background: #0f172a; }"
                            spellcheck="false"
                            :disabled="saving"
                        ></textarea>
                        <div id="custom-css-help" class="flex items-center justify-between gap-3 text-xs text-slate-500">
                            <span>Chỉ nhập mã CSS thuần, không nhập thẻ &lt;style&gt;.</span>
                            <span class="font-mono tabular-nums" :class="cssLength >= MAX_CODE_LENGTH ? 'text-rose-600' : ''"
                                >{{ cssLength.toLocaleString('vi-VN') }} / 100.000</span
                            >
                        </div>
                        <p v-if="errors.custom_css" id="custom-css-error" class="text-xs font-semibold text-rose-600">{{ errors.custom_css }}</p>
                    </fieldset>

                    <fieldset class="grid gap-3 border-t border-slate-100 pt-6">
                        <legend class="sr-only">JavaScript tùy chỉnh</legend>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <Code2 class="h-4 w-4 text-amber-600" aria-hidden="true" />
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-900">JavaScript tùy chỉnh</h4>
                                    <p class="text-xs text-slate-500">Được tải gần cuối trang, sau bundle JavaScript chính.</p>
                                </div>
                            </div>
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700">
                                <input v-model="form.custom_js_enabled" type="checkbox" class="peer sr-only" :disabled="saving" />
                                <span
                                    class="relative h-6 w-11 rounded-full bg-slate-300 transition after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition peer-checked:bg-amber-500 peer-checked:after:translate-x-5 peer-focus-visible:ring-2 peer-focus-visible:ring-amber-500 peer-focus-visible:ring-offset-2"
                                ></span>
                                Bật JavaScript tùy chỉnh
                            </label>
                        </div>
                        <textarea
                            id="custom-js"
                            v-model="form.custom_js"
                            rows="12"
                            :maxlength="MAX_CODE_LENGTH"
                            :aria-invalid="Boolean(errors.custom_js)"
                            aria-describedby="custom-js-help custom-js-error"
                            class="min-h-64 w-full resize-y rounded-[10px] border bg-slate-950 px-4 py-3 font-mono text-sm leading-6 text-slate-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/20"
                            :class="errors.custom_js ? 'border-rose-400' : 'border-slate-700'"
                            placeholder="document.addEventListener('DOMContentLoaded', () => { ... });"
                            spellcheck="false"
                            :disabled="saving"
                        ></textarea>
                        <div id="custom-js-help" class="flex items-center justify-between gap-3 text-xs text-slate-500">
                            <span>Chỉ nhập mã JavaScript thuần, không nhập thẻ &lt;script&gt;.</span>
                            <span class="font-mono tabular-nums" :class="jsLength >= MAX_CODE_LENGTH ? 'text-rose-600' : ''"
                                >{{ jsLength.toLocaleString('vi-VN') }} / 100.000</span
                            >
                        </div>
                        <p v-if="errors.custom_js" id="custom-js-error" class="text-xs font-semibold text-rose-600">{{ errors.custom_js }}</p>
                    </fieldset>
                </div>
            </article>
        </div>

        <aside class="space-y-4">
            <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-amber-950">
                <div class="flex items-start gap-3">
                    <ShieldAlert class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" aria-hidden="true" />
                    <div>
                        <h3 class="text-sm font-bold">Cảnh báo bảo mật</h3>
                        <p class="mt-2 text-sm leading-6 text-amber-800">
                            JavaScript tùy chỉnh là mã tin cậy có toàn quyền trên giao diện public. Mã lỗi có thể làm hỏng chức năng hoặc làm lộ dữ
                            liệu người dùng.
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-[10px] border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Phạm vi áp dụng</p>
                <ul class="mt-3 grid gap-2 text-sm leading-6 text-slate-600">
                    <li>• Trang public và khu vực tài khoản khách hàng.</li>
                    <li>• Không áp dụng cho admin và màn hình đăng nhập.</li>
                    <li>• Tắt công tắc để ngừng tải mà không xóa nội dung.</li>
                    <li>• Xóa toàn bộ textarea rồi lưu để xóa mã.</li>
                </ul>
            </div>
        </aside>
    </section>
</template>
