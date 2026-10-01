<script setup lang="ts">
import DataTable from '@/components/shared/DataTable/index.vue';
import Editor from '@/components/shared/Editor/index.vue';
import Modal from '@/components/shared/Modal/index.vue';
import UploadImage from '@/components/shared/UpladImage/index.vue';
import {
    adminGameServiceService,
    type GameServerOption,
    type GameServiceGame,
    type GameServiceItem,
    type GameServicePackage,
    type GameServicePrice,
    type PayloadField,
    type Status,
} from '@/services/admin-game-service.service';
import { uploadEditorImages, uploadEditorImagesInHtml } from '@/utils/editor-image-upload';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Boxes, Gamepad2, Layers3, LoaderCircle, Pencil, Plus, Save, Server, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

type CatalogType = 'games' | 'services' | 'packages';
type PayloadFieldDraft = PayloadField & { options_text: string };
type GameServicePriceDraft = Pick<GameServicePrice, 'price' | 'collaborator_price' | 'quantity_enabled' | 'min_quantity' | 'max_quantity'>;

const props = defineProps<{ catalogType: CatalogType }>();
const loading = ref(false);
const saving = ref(false);
const search = ref('');
const games = ref<GameServiceGame[]>([]);
const services = ref<GameServiceItem[]>([]);
const packages = ref<GameServicePackage[]>([]);
const servers = ref<GameServerOption[]>([]);
const editingId = ref<number | null>(null);
const serviceModalOpen = ref(false);
const packageModalOpen = ref(false);
const packageGameFilter = ref<number | ''>('');
const servicePage = ref(1);
const packagePage = ref(1);
const servicePageSize = 10;
const packagePageSize = 10;
const serviceDescriptionEditor = ref<{ flush: () => unknown[] | string } | null>(null);
const serviceSeoEditor = ref<{ flush: () => unknown[] | string } | null>(null);

const inputClass =
    'min-h-11 w-full rounded-md border-2 border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-950 outline-none transition hover:border-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100';
const notify = (message: string): void => handleSuccessResponse({ data: { status: true, message } });
const rows = computed(() => {
    const keyword = search.value.trim().toLocaleLowerCase('vi');
    const source = props.catalogType === 'games' ? games.value : props.catalogType === 'services' ? services.value : packages.value;
    if (!keyword) return source;
    return source.filter((row) =>
        `${row.name} ${'slug' in row ? row.slug : ''} ${'code' in row ? (row.code ?? '') : ''}`.toLocaleLowerCase('vi').includes(keyword),
    );
});
const gameRows = computed(() => rows.value as GameServiceGame[]);
const serviceRows = computed(() => rows.value as GameServiceItem[]);
const packageRows = computed(() => {
    const source = rows.value as GameServicePackage[];

    if (packageGameFilter.value === '') {
        return source;
    }

    return source.filter((item) => item.service.game_id === Number(packageGameFilter.value));
});
const serviceTotalPages = computed(() => Math.max(1, Math.ceil(serviceRows.value.length / servicePageSize)));
const displayedServiceRows = computed(() => {
    const offset = (servicePage.value - 1) * servicePageSize;

    return serviceRows.value.slice(offset, offset + servicePageSize);
});
const packageTotalPages = computed(() => Math.max(1, Math.ceil(packageRows.value.length / packagePageSize)));
const displayedPackageRows = computed(() => {
    const offset = (packagePage.value - 1) * packagePageSize;

    return packageRows.value.slice(offset, offset + packagePageSize);
});
const serviceColumns = [
    { id: 'background', accessorFn: (service: GameServiceItem) => service.background_image, header: 'Ảnh nền' },
    { id: 'service', accessorFn: (service: GameServiceItem) => service.name, header: 'Dịch vụ' },
    { id: 'servers', accessorFn: (service: GameServiceItem) => service.servers.length, header: 'Máy chủ' },
    { id: 'statistics', accessorFn: (service: GameServiceItem) => service.packages_count, header: 'Gói / đơn' },
    { id: 'status', accessorFn: (service: GameServiceItem) => service.status, header: 'Trạng thái' },
    { id: 'sort_order', accessorFn: (service: GameServiceItem) => service.sort_order, header: 'Thứ tự' },
    { id: 'actions', accessorFn: (service: GameServiceItem) => service.id, header: 'Thao tác' },
];
const changeServicePage = async (page: number): Promise<void> => {
    servicePage.value = Math.min(Math.max(page, 1), serviceTotalPages.value);
};
const packageColumns = [
    { id: 'package', accessorFn: (item: GameServicePackage) => item.name, header: 'Gói dịch vụ' },
    { id: 'prices', accessorFn: (item: GameServicePackage) => item.prices.length, header: 'Giá dịch vụ / CTV' },
    { id: 'status', accessorFn: (item: GameServicePackage) => item.status, header: 'Trạng thái' },
    { id: 'orders', accessorFn: (item: GameServicePackage) => item.orders_count, header: 'Đơn hàng' },
    { id: 'sort_order', accessorFn: (item: GameServicePackage) => item.sort_order, header: 'Thứ tự' },
    { id: 'actions', accessorFn: (item: GameServicePackage) => item.id, header: 'Thao tác' },
];
const changePackagePage = async (page: number): Promise<void> => {
    packagePage.value = Math.min(Math.max(page, 1), packageTotalPages.value);
};
const clearPackageFilters = (): void => {
    packageGameFilter.value = '';
    search.value = '';
};

const serviceForm = reactive({
    game_id: '' as number | '',
    name: '',
    slug: '',
    code: '',
    description: '',
    background_image: '',
    server_ids: [] as number[],
    payload_fields: [] as PayloadFieldDraft[],
    seo_content: [] as unknown[],
    faqs: [] as Array<{ question: string; answer: string }>,
    status: 'active' as Status,
    sort_order: 0,
});

const packageForm = reactive({
    game_id: '' as number | '',
    game_service_id: '' as number | '',
    name: '',
    code: '',
    description: '',
    status: 'active' as Status,
    sort_order: 0,
    prices: [] as GameServicePriceDraft[],
});

const availableServers = computed(() => servers.value.filter((server) => server.game_id === Number(serviceForm.game_id)));
const availablePackageServices = computed(() => services.value.filter((service) => service.game_id === Number(packageForm.game_id)));
const money = (value: number | null): string => `${new Intl.NumberFormat('vi-VN').format(value ?? 0)}đ`;
const pageMeta = computed(
    () =>
        ({
            games: {
                eyebrow: 'Dịch vụ game',
                title: 'Quản lý game',
                description: 'Bật game nhận dịch vụ và dùng trực tiếp name, slug, code từ danh mục nạp game.',
                icon: Gamepad2,
            },
            services: {
                eyebrow: 'Dịch vụ game',
                title: 'Quản lý dịch vụ',
                description: 'Cấu hình payload đầu vào và các máy chủ được nhận cho từng dịch vụ.',
                icon: Layers3,
            },
            packages: {
                eyebrow: 'Dịch vụ game',
                title: 'Gói dịch vụ',
                description: 'Mỗi dịch vụ có nhiều gói, mỗi gói có một giá dịch vụ và một giá trả cộng tác viên.',
                icon: Boxes,
            },
        })[props.catalogType],
);

const blankPayloadField = (): PayloadFieldDraft => ({
    key: '',
    label: '',
    placeholder: '',
    required: true,
    regex: '',
    type: 'text',
    options: [],
    options_text: '',
    min: null,
    max: null,
    step: null,
});
const blankPrice = (): GameServicePriceDraft => ({
    price: 0,
    collaborator_price: 0,
    quantity_enabled: false,
    min_quantity: 1,
    max_quantity: 1,
});

const resetForm = (): void => {
    editingId.value = null;
    Object.assign(serviceForm, {
        game_id: games.value.find((game) => game.game_services_enabled)?.id ?? '',
        name: '',
        slug: '',
        code: '',
        description: '',
        background_image: '',
        server_ids: [],
        payload_fields: [blankPayloadField()],
        seo_content: [],
        faqs: [],
        status: 'active',
        sort_order: services.value.length,
    });
    Object.assign(packageForm, {
        game_id: '',
        game_service_id: '',
        name: '',
        code: '',
        description: '',
        status: 'active',
        sort_order: packages.value.length,
        prices: [blankPrice()],
    });
};

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        const [gameResponse, serviceResponse, packageResponse, serverResponse] = await Promise.all([
            adminGameServiceService.games({ per_page: 100 }),
            adminGameServiceService.services({ per_page: 100 }),
            adminGameServiceService.packages({ per_page: 100 }),
            adminGameServiceService.servers({ per_page: 100 }),
        ]);
        games.value = gameResponse.data.data.data;
        services.value = serviceResponse.data.data.data;
        packages.value = packageResponse.data.data.data;
        servers.value = serverResponse.data.data.data;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const saveGame = async (game: GameServiceGame): Promise<void> => {
    try {
        await adminGameServiceService.updateGame(game.id, {
            game_services_enabled: game.game_services_enabled,
            provider_service_code: game.code || null,
        });
        notify(`Đã cập nhật cấu hình dịch vụ cho ${game.name}.`);
        await load();
    } catch (error) {
        handleErrorResponse(error);
    }
};

const editService = (service: GameServiceItem): void => {
    editingId.value = service.id;
    Object.assign(serviceForm, {
        game_id: service.game_id,
        name: service.name,
        slug: service.slug,
        code: service.code,
        description: service.description ?? '',
        background_image: service.background_image ?? '',
        server_ids: [...service.server_ids],
        payload_fields: service.payload_fields.map((field) => ({
            ...field,
            options_text: field.options.map((option) => `${option.value}|${option.text}`).join('\n'),
        })),
        seo_content: Array.isArray(service.seo_content) ? service.seo_content : [],
        faqs: (service.faqs ?? []).map((faq) => ({ ...faq })),
        status: service.status,
        sort_order: service.sort_order,
    });
    serviceModalOpen.value = true;
};

const openCreateServiceModal = (): void => {
    resetForm();
    serviceModalOpen.value = true;
};

const saveService = async (): Promise<void> => {
    saving.value = true;
    try {
        const latestDescription = serviceDescriptionEditor.value?.flush();
        if (typeof latestDescription === 'string') {
            serviceForm.description = await uploadEditorImagesInHtml(latestDescription);
        }
        const latestSeoContent = serviceSeoEditor.value?.flush();
        if (Array.isArray(latestSeoContent)) serviceForm.seo_content = latestSeoContent;
        await nextTick();
        serviceForm.seo_content = await uploadEditorImages(serviceForm.seo_content);
        await adminGameServiceService.saveService(editingId.value, {
            ...serviceForm,
            game_id: Number(serviceForm.game_id),
            sort_order: Number(serviceForm.sort_order),
            payload_fields: serviceForm.payload_fields.map(({ options_text, ...field }) => ({
                ...field,
                min: field.type === 'number' && field.min !== null ? Number(field.min) : null,
                max: field.type === 'number' && field.max !== null ? Number(field.max) : null,
                step: field.type === 'number' && field.step !== null ? Number(field.step) : null,
                options:
                    field.type === 'select'
                        ? options_text
                              .split('\n')
                              .map((line) => line.split('|'))
                              .filter(([value, text]) => value?.trim() && text?.trim())
                              .map(([value, text]) => ({ value: value.trim(), text: text.trim() }))
                        : [],
            })),
            seo_content: serviceForm.seo_content,
            faqs: serviceForm.faqs
                .map((faq) => ({ question: faq.question.trim(), answer: faq.answer.trim() }))
                .filter((faq) => faq.question || faq.answer),
        });
        notify(editingId.value ? 'Đã cập nhật dịch vụ.' : 'Đã tạo dịch vụ.');
        await load();
        serviceModalOpen.value = false;
        resetForm();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

const editPackage = (item: GameServicePackage): void => {
    editingId.value = item.id;
    Object.assign(packageForm, {
        game_id: item.service.game_id,
        game_service_id: item.game_service_id,
        name: item.name,
        code: item.code,
        description: item.description ?? '',
        status: item.status,
        sort_order: item.sort_order,
        prices: item.prices[0]
            ? [
                  {
                      price: item.prices[0].price,
                      collaborator_price: item.prices[0].collaborator_price,
                      quantity_enabled: item.prices[0].quantity_enabled,
                      min_quantity: item.prices[0].min_quantity,
                      max_quantity: item.prices[0].max_quantity,
                  },
              ]
            : [blankPrice()],
    });
    packageModalOpen.value = true;
};

const openCreatePackageModal = (): void => {
    resetForm();
    packageModalOpen.value = true;
};

const savePackage = async (): Promise<void> => {
    saving.value = true;
    try {
        await adminGameServiceService.savePackage(editingId.value, {
            game_service_id: Number(packageForm.game_service_id),
            name: packageForm.name,
            code: packageForm.code,
            description: packageForm.description,
            status: packageForm.status,
            sort_order: Number(packageForm.sort_order),
            prices: packageForm.prices.slice(0, 1).map((price) => ({
                price: Number(price.price),
                collaborator_price: Number(price.collaborator_price),
                quantity_enabled: price.quantity_enabled,
                min_quantity: price.quantity_enabled ? Number(price.min_quantity) : 1,
                max_quantity: price.quantity_enabled ? Number(price.max_quantity) : 1,
            })),
        });
        notify(editingId.value ? 'Đã cập nhật gói dịch vụ.' : 'Đã tạo gói dịch vụ.');
        await load();
        packageModalOpen.value = false;
        resetForm();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

const removeService = async (item: GameServiceItem): Promise<void> => {
    if (!window.confirm(`Xóa dịch vụ “${item.name}”?`)) return;
    try {
        await adminGameServiceService.deleteService(item.id);
        notify('Đã xóa dịch vụ.');
        await load();
        resetForm();
    } catch (error) {
        handleErrorResponse(error);
    }
};

const removePackage = async (item: GameServicePackage): Promise<void> => {
    if (!window.confirm(`Xóa gói “${item.name}”?`)) return;
    try {
        await adminGameServiceService.deletePackage(item.id);
        notify('Đã xóa gói dịch vụ.');
        await load();
        resetForm();
    } catch (error) {
        handleErrorResponse(error);
    }
};

watch(
    () => props.catalogType,
    () => {
        servicePage.value = 1;
        packagePage.value = 1;
        packageGameFilter.value = '';
        resetForm();
    },
);
watch(search, () => {
    servicePage.value = 1;
    packagePage.value = 1;
});
watch(packageGameFilter, () => {
    packagePage.value = 1;
});
watch(
    () => serviceForm.game_id,
    () => {
        serviceForm.server_ids = serviceForm.server_ids.filter((id) => availableServers.value.some((server) => server.id === id));
    },
);
watch(
    () => packageForm.game_id,
    () => {
        if (!availablePackageServices.value.some((service) => service.id === Number(packageForm.game_service_id))) {
            packageForm.game_service_id = '';
        }
    },
);

onMounted(async () => {
    await load();
    resetForm();
});
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <header class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-indigo-100 text-indigo-700"
                    ><component :is="pageMeta.icon" class="size-6"
                /></span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-indigo-600">{{ pageMeta.eyebrow }}</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">{{ pageMeta.title }}</h1>
                    <p class="mt-1 text-sm leading-6 text-slate-500">{{ pageMeta.description }}</p>
                </div>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input v-if="catalogType !== 'packages'" v-model.trim="search" :class="inputClass" placeholder="Tìm theo tên hoặc mã..." />
                <button
                    v-if="catalogType !== 'games'"
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-slate-950 px-4 text-sm font-bold text-white"
                    @click="catalogType === 'services' ? openCreateServiceModal() : openCreatePackageModal()"
                >
                    <Plus class="size-4" /> {{ catalogType === 'services' ? 'Thêm dịch vụ' : 'Thêm gói' }}
                </button>
            </div>
        </header>

        <div v-if="loading" class="grid min-h-64 place-items-center rounded-lg border border-slate-200 bg-white">
            <LoaderCircle class="size-8 animate-spin text-slate-400" />
        </div>

        <section v-else-if="catalogType === 'games'" class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
            <article v-for="game in gameRows" :key="game.id" class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <img v-if="game.image" :src="game.image" :alt="game.name" class="size-12 rounded-md border border-slate-200 object-cover" />
                    <span v-else class="grid size-12 place-items-center rounded-md bg-slate-100"><Gamepad2 class="size-6 text-slate-500" /></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate font-black text-slate-950">{{ game.name }}</h2>
                        <p class="truncate text-sm text-slate-500">/{{ game.slug }}</p>
                    </div>
                    <span
                        class="rounded-full px-2 py-1 text-xs font-bold"
                        :class="game.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                        >{{ game.status === 'active' ? 'Đang mở' : 'Tạm tắt' }}</span
                    >
                </div>
                <label class="grid gap-1 text-sm font-bold"
                    >Code game<input v-model.trim="game.code" :class="[inputClass, 'font-mono']" placeholder="provider_service_code"
                /></label>
                <label class="flex items-center justify-between gap-4 rounded-md border border-slate-200 p-3 text-sm font-bold">
                    <span
                        ><span class="block">Nhận dịch vụ game</span
                        ><small class="font-normal text-slate-500"
                            >{{ game.servers_count }} máy chủ · {{ game.game_services_count }} dịch vụ</small
                        ></span
                    >
                    <input
                        v-model="game.game_services_enabled"
                        type="checkbox"
                        class="size-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                </label>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-indigo-600 px-4 text-sm font-bold text-white"
                    @click="saveGame(game)"
                >
                    <Save class="size-4" /> Lưu cấu hình
                </button>
            </article>
        </section>

        <section v-else-if="catalogType === 'services'">
            <Modal v-model="serviceModalOpen" panel-class="max-w-5xl">
                <template #header>
                    <div class="border-b border-slate-200 px-5 py-4 pr-16 sm:px-6">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-indigo-600">
                            {{ editingId ? 'Chỉnh sửa dịch vụ' : 'Dịch vụ mới' }}
                        </p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">{{ editingId ? `Cập nhật ${serviceForm.name}` : 'Thêm dịch vụ game' }}</h2>
                    </div>
                </template>
                <form id="game-service-form" class="grid gap-4 p-5 sm:p-6" @submit.prevent="saveService">
                    <label class="grid gap-1 text-sm font-bold"
                        >Game<select v-model="serviceForm.game_id" required :class="inputClass">
                            <option value="">Chọn game</option>
                            <option v-for="game in games.filter((item) => item.game_services_enabled)" :key="game.id" :value="game.id">
                                {{ game.name }} · {{ game.code || 'chưa có code' }}
                            </option>
                        </select></label
                    >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm font-bold">Tên<input v-model.trim="serviceForm.name" required :class="inputClass" /></label
                        ><label class="grid gap-1 text-sm font-bold"
                            >Mã dịch vụ<input v-model.trim="serviceForm.code" required :class="[inputClass, 'font-mono']"
                        /></label>
                    </div>
                    <label class="grid gap-1 text-sm font-bold"
                        >Slug<input v-model.trim="serviceForm.slug" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" :class="[inputClass, 'font-mono']"
                    /></label>
                    <fieldset class="grid min-w-0 gap-3 rounded-md border border-slate-200 p-3">
                        <div>
                            <legend class="text-sm font-black text-slate-900">Mô tả dịch vụ</legend>
                            <p class="mt-1 text-xs font-normal leading-5 text-slate-500">
                                Nội dung hiển thị tại phần mô tả dịch vụ. Có thể định dạng tiêu đề, màu sắc, danh sách, bảng và hình ảnh.
                            </p>
                        </div>
                        <div class="min-w-0">
                            <Editor ref="serviceDescriptionEditor" v-model="serviceForm.description" format="html" :debounce="0" :height="360" />
                        </div>
                    </fieldset>
                    <fieldset class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <legend class="text-sm font-black text-slate-900">Ảnh nền dịch vụ</legend>
                                <p class="mt-1 text-xs font-normal leading-5 text-slate-500">
                                    Mỗi dịch vụ dùng một ảnh nền riêng. Nên chọn ảnh ngang JPG, PNG hoặc WebP.
                                </p>
                            </div>
                            <button
                                v-if="serviceForm.background_image"
                                type="button"
                                class="shrink-0 text-xs font-bold text-rose-600 hover:text-rose-700"
                                @click="serviceForm.background_image = ''"
                            >
                                Xóa ảnh
                            </button>
                        </div>
                        <UploadImage
                            :accept="['image/jpeg', 'image/png', 'image/webp']"
                            :compress="true"
                            :image-src="serviceForm.background_image"
                            :name-image="serviceForm.slug || serviceForm.name || 'game-service-background'"
                            @uploaded="serviceForm.background_image = $event"
                        />
                        <p v-if="!serviceForm.background_image" class="text-xs font-bold text-rose-600">
                            Vui lòng tải ảnh nền trước khi lưu dịch vụ.
                        </p>
                    </fieldset>
                    <fieldset class="grid gap-2 rounded-md border border-slate-200 p-3">
                        <legend class="px-1 text-sm font-black">Máy chủ nhận dịch vụ</legend>
                        <label v-for="server in availableServers" :key="server.id" class="flex items-center gap-2 text-sm"
                            ><input
                                v-model="serviceForm.server_ids"
                                :value="server.id"
                                type="checkbox"
                                class="size-4 rounded border-slate-300 text-indigo-600"
                            />{{ server.name }} <code class="text-xs text-slate-400">{{ server.code }}</code></label
                        >
                        <p v-if="!availableServers.length" class="text-xs text-slate-500">
                            <Server class="mr-1 inline size-4" />Game chưa có máy chủ.
                        </p>
                    </fieldset>
                    <fieldset class="grid gap-3 rounded-md border border-indigo-200 bg-indigo-50/40 p-3">
                        <div class="flex items-center justify-between">
                            <legend class="text-sm font-black text-indigo-950">Payload nhận vào</legend>
                            <button
                                type="button"
                                class="text-sm font-bold text-indigo-700"
                                @click="serviceForm.payload_fields.push(blankPayloadField())"
                            >
                                <Plus class="inline size-4" /> Trường
                            </button>
                        </div>
                        <article
                            v-for="(field, index) in serviceForm.payload_fields"
                            :key="index"
                            class="grid gap-2 rounded-md border border-indigo-200 bg-white p-3"
                        >
                            <div class="grid gap-2 sm:grid-cols-2">
                                <input v-model.trim="field.key" required :class="[inputClass, 'font-mono']" placeholder="key" /><input
                                    v-model.trim="field.label"
                                    required
                                    :class="inputClass"
                                    placeholder="Nhãn hiển thị"
                                />
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <select v-model="field.type" :class="inputClass">
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="password">Password</option>
                                    <option value="select">Select</option></select
                                ><label class="flex items-center gap-2 rounded-md border border-slate-200 px-3 text-sm font-bold"
                                    ><input v-model="field.required" type="checkbox" /> Bắt buộc</label
                                >
                            </div>
                            <input v-model.trim="field.placeholder" :class="inputClass" placeholder="Placeholder" /><input
                                v-model.trim="field.regex"
                                :class="[inputClass, 'font-mono']"
                                placeholder="Regex, không kèm dấu /"
                            /><textarea
                                v-if="field.type === 'select'"
                                v-model="field.options_text"
                                required
                                rows="3"
                                :class="[inputClass, 'font-mono']"
                                placeholder="value|Nhãn&#10;value_2|Nhãn 2"
                            ></textarea>
                            <div v-if="field.type === 'number'" class="grid grid-cols-3 gap-2">
                                <input v-model.number="field.min" type="number" :class="inputClass" placeholder="Min" /><input
                                    v-model.number="field.max"
                                    type="number"
                                    :class="inputClass"
                                    placeholder="Max"
                                /><input v-model.number="field.step" type="number" min="0.0001" step="any" :class="inputClass" placeholder="Step" />
                            </div>
                            <button
                                v-if="serviceForm.payload_fields.length > 1"
                                type="button"
                                class="justify-self-end text-sm font-bold text-rose-600"
                                @click="serviceForm.payload_fields.splice(index, 1)"
                            >
                                <Trash2 class="inline size-4" /> Xóa trường
                            </button>
                        </article>
                    </fieldset>
                    <fieldset class="grid min-w-0 gap-3 rounded-md border border-slate-200 p-3">
                        <div>
                            <legend class="text-sm font-black text-slate-900">Bài SEO dịch vụ</legend>
                            <p class="mt-1 text-xs font-normal text-slate-500">
                                Nội dung hiển thị phía dưới lịch sử đơn. Ảnh trong bài sẽ tự tải lên hệ thống.
                            </p>
                        </div>
                        <div class="min-w-0">
                            <Editor ref="serviceSeoEditor" v-model="serviceForm.seo_content" :height="420" />
                        </div>
                    </fieldset>
                    <fieldset class="grid gap-3 rounded-md border border-slate-200 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <legend class="text-sm font-black text-slate-900">Câu hỏi thường gặp</legend>
                                <p class="mt-1 text-xs font-normal text-slate-500">Tối đa 20 câu hỏi riêng cho dịch vụ.</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1 text-sm font-bold text-indigo-700"
                                :disabled="serviceForm.faqs.length >= 20"
                                @click="serviceForm.faqs.push({ question: '', answer: '' })"
                            >
                                <Plus class="size-4" /> Thêm FAQ
                            </button>
                        </div>
                        <article v-for="(faq, index) in serviceForm.faqs" :key="index" class="grid gap-2 rounded-md bg-slate-50 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <strong class="text-xs uppercase tracking-wide text-slate-500">FAQ {{ index + 1 }}</strong>
                                <button type="button" class="text-rose-600" aria-label="Xóa FAQ" @click="serviceForm.faqs.splice(index, 1)">
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                            <input v-model="faq.question" maxlength="255" required :class="inputClass" placeholder="Câu hỏi" />
                            <textarea
                                v-model="faq.answer"
                                maxlength="2000"
                                rows="3"
                                required
                                :class="inputClass"
                                placeholder="Câu trả lời"
                            ></textarea>
                        </article>
                        <p v-if="!serviceForm.faqs.length" class="text-xs text-slate-500">Chưa có FAQ cho dịch vụ này.</p>
                    </fieldset>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-1 text-sm font-bold"
                            >Trạng thái<select v-model="serviceForm.status" :class="inputClass">
                                <option value="active">Hoạt động</option>
                                <option value="inactive">Tạm tắt</option>
                            </select></label
                        ><label class="grid gap-1 text-sm font-bold"
                            >Thứ tự<input v-model.number="serviceForm.sort_order" min="0" type="number" :class="inputClass"
                        /></label>
                    </div>
                </form>
                <template #footer>
                    <div
                        class="flex w-full flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6"
                    >
                        <button
                            type="button"
                            class="min-h-11 rounded-lg border-2 border-slate-300 bg-white px-5 text-sm font-bold text-slate-700 hover:bg-slate-100"
                            :disabled="saving"
                            @click="serviceModalOpen = false"
                        >
                            Hủy
                        </button>
                        <button
                            form="game-service-form"
                            type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 text-sm font-bold text-white hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-60"
                            :disabled="saving"
                        >
                            <LoaderCircle v-if="saving" class="size-4 animate-spin" />
                            <Save v-else class="size-4" />
                            {{ saving ? 'Đang lưu...' : editingId ? 'Lưu thay đổi' : 'Thêm dịch vụ' }}
                        </button>
                    </div>
                </template>
            </Modal>

            <DataTable
                :data="displayedServiceRows"
                :columns="serviceColumns"
                :loading="false"
                :current-page="servicePage"
                :total-pages="serviceTotalPages"
                :go-to-page="changeServicePage"
                class-custom="min-w-[1040px]"
                empty-text="Không tìm thấy dịch vụ phù hợp."
            >
                <template #background="{ row }">
                    <div class="h-12 w-24 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                        <img
                            v-if="row.background_image"
                            :src="row.background_image"
                            :alt="`Ảnh nền ${row.name}`"
                            class="h-full w-full object-cover"
                        />
                    </div>
                </template>
                <template #service="{ row }">
                    <div class="min-w-[210px]">
                        <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">{{ row.game.name }}</p>
                        <strong class="mt-1 block text-slate-950">{{ row.name }}</strong>
                        <p class="mt-0.5 font-mono text-xs text-slate-500">{{ row.code }} · /{{ row.slug }}</p>
                    </div>
                </template>
                <template #servers="{ row }">
                    <div class="flex min-w-[180px] flex-wrap gap-1.5">
                        <span
                            v-for="server in row.servers"
                            :key="server.id"
                            class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600"
                        >
                            {{ server.name }}
                        </span>
                    </div>
                </template>
                <template #statistics="{ row }">
                    <div class="min-w-[90px] text-xs text-slate-600">
                        <p>
                            <strong class="text-slate-950">{{ row.packages_count }}</strong> gói
                        </p>
                        <p class="mt-1">
                            <strong class="text-slate-950">{{ row.orders_count }}</strong> đơn
                        </p>
                    </div>
                </template>
                <template #status="{ row }">
                    <span
                        class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold"
                        :class="row.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                    >
                        {{ row.status === 'active' ? 'Hoạt động' : 'Tạm tắt' }}
                    </span>
                </template>
                <template #sort_order="{ row }">
                    <span class="font-mono text-xs font-bold text-slate-600">#{{ row.sort_order }}</span>
                </template>
                <template #actions="{ row }">
                    <div class="flex min-w-[150px] justify-end gap-2">
                        <button
                            type="button"
                            class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-indigo-200 px-3 text-xs font-bold text-indigo-700 hover:bg-indigo-50"
                            @click="editService(row)"
                        >
                            <Pencil class="size-3.5" /> Sửa
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-rose-200 px-3 text-xs font-bold text-rose-600 hover:bg-rose-50"
                            @click="removeService(row)"
                        >
                            <Trash2 class="size-3.5" /> Xóa
                        </button>
                    </div>
                </template>
            </DataTable>
        </section>

        <section v-else>
            <Modal v-model="packageModalOpen" panel-class="max-w-5xl">
                <template #header>
                    <div class="border-b border-slate-200 px-5 py-4 pr-16 sm:px-6">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-amber-600">
                            {{ editingId ? 'Chỉnh sửa gói dịch vụ' : 'Gói dịch vụ mới' }}
                        </p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">{{ editingId ? `Cập nhật ${packageForm.name}` : 'Thêm gói dịch vụ' }}</h2>
                    </div>
                </template>
                <form id="game-service-package-form" class="grid gap-4 p-5 sm:p-6" @submit.prevent="savePackage">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm font-bold"
                            >Game<select v-model="packageForm.game_id" required :class="inputClass">
                                <option value="">Chọn game</option>
                                <option v-for="game in games.filter((item) => item.game_services_enabled)" :key="game.id" :value="game.id">
                                    {{ game.name }}
                                </option>
                            </select></label
                        ><label class="grid gap-1 text-sm font-bold"
                            >Dịch vụ<select
                                v-model="packageForm.game_service_id"
                                required
                                :disabled="!packageForm.game_id"
                                :class="[inputClass, 'disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500']"
                            >
                                <option value="">{{ packageForm.game_id ? 'Chọn dịch vụ' : 'Chọn game trước' }}</option>
                                <option v-for="service in availablePackageServices" :key="service.id" :value="service.id">
                                    {{ service.name }}
                                </option>
                            </select></label
                        >
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm font-bold"
                            >Tên gói<input v-model.trim="packageForm.name" required :class="inputClass" /></label
                        ><label class="grid gap-1 text-sm font-bold"
                            >Mã gói<input v-model.trim="packageForm.code" required :class="[inputClass, 'font-mono']"
                        /></label>
                    </div>
                    <label class="grid gap-1 text-sm font-bold"
                        >Mô tả<textarea v-model="packageForm.description" rows="2" :class="inputClass"></textarea>
                    </label>
                    <fieldset class="grid gap-3 rounded-md border border-amber-200 bg-amber-50/40 p-3">
                        <legend class="text-sm font-black text-amber-950">Cấu hình giá</legend>
                        <article
                            v-for="(price, priceIndex) in packageForm.prices.slice(0, 1)"
                            :key="priceIndex"
                            class="grid gap-2 rounded-md border border-amber-200 bg-white p-3"
                        >
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label class="grid gap-1 text-xs font-bold"
                                    >Giá dịch vụ<input v-model.number="price.price" required min="0" type="number" :class="inputClass" /></label
                                ><label class="grid gap-1 text-xs font-bold"
                                    >Giá trả CTV<input v-model.number="price.collaborator_price" required min="0" type="number" :class="inputClass"
                                /></label>
                            </div>
                            <label
                                class="flex items-center justify-between gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm font-bold"
                            >
                                <span>
                                    <span class="block">Cho phép nhập số lượng</span>
                                    <small class="font-normal text-slate-500">Tắt thì đơn hàng luôn có số lượng bằng 1.</small>
                                </span>
                                <input
                                    v-model="price.quantity_enabled"
                                    type="checkbox"
                                    class="size-5 rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                                />
                            </label>
                            <div v-if="price.quantity_enabled" class="grid grid-cols-2 gap-2">
                                <label class="grid gap-1 text-xs font-bold"
                                    >SL tối thiểu<input
                                        v-model.number="price.min_quantity"
                                        required
                                        min="1"
                                        type="number"
                                        :class="inputClass" /></label
                                ><label class="grid gap-1 text-xs font-bold"
                                    >SL tối đa<input v-model.number="price.max_quantity" required min="1" type="number" :class="inputClass"
                                /></label>
                            </div>
                        </article>
                    </fieldset>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-1 text-sm font-bold"
                            >Trạng thái<select v-model="packageForm.status" :class="inputClass">
                                <option value="active">Hoạt động</option>
                                <option value="inactive">Tạm tắt</option>
                            </select></label
                        ><label class="grid gap-1 text-sm font-bold"
                            >Thứ tự<input v-model.number="packageForm.sort_order" min="0" type="number" :class="inputClass"
                        /></label>
                    </div>
                </form>
                <template #footer>
                    <div
                        class="flex w-full flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6"
                    >
                        <button
                            type="button"
                            class="min-h-11 rounded-lg border-2 border-slate-300 bg-white px-5 text-sm font-bold text-slate-700 hover:bg-slate-100"
                            :disabled="saving"
                            @click="packageModalOpen = false"
                        >
                            Hủy
                        </button>
                        <button
                            form="game-service-package-form"
                            type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-amber-600 px-5 text-sm font-bold text-white hover:bg-amber-700 disabled:cursor-wait disabled:opacity-60"
                            :disabled="saving"
                        >
                            <LoaderCircle v-if="saving" class="size-4 animate-spin" />
                            <Save v-else class="size-4" />
                            {{ saving ? 'Đang lưu...' : editingId ? 'Lưu thay đổi' : 'Thêm gói' }}
                        </button>
                    </div>
                </template>
            </Modal>

            <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[1fr_auto] lg:items-end">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                        <span>Lọc gói theo game</span>
                        <select v-model="packageGameFilter" :class="inputClass">
                            <option value="">Tất cả game</option>
                            <option v-for="game in games" :key="game.id" :value="game.id">
                                {{ game.name }}
                            </option>
                        </select>
                    </label>

                    <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                        <span>Tìm kiếm gói</span>
                        <input v-model.trim="search" :class="inputClass" placeholder="Nhập tên hoặc mã gói..." />
                    </label>
                </div>

                <div class="flex min-h-11 items-center justify-between gap-3 lg:justify-end">
                    <span class="text-sm text-slate-500">Hiển thị {{ packageRows.length }} gói</span>
                    <button
                        v-if="packageGameFilter !== '' || search"
                        type="button"
                        class="min-h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-100"
                        @click="clearPackageFilters"
                    >
                        Xóa lọc
                    </button>
                </div>
            </div>

            <DataTable
                :data="displayedPackageRows"
                :columns="packageColumns"
                :loading="false"
                :current-page="packagePage"
                :total-pages="packageTotalPages"
                :go-to-page="changePackagePage"
                class-custom="min-w-[1080px]"
                empty-text="Không tìm thấy gói dịch vụ phù hợp."
            >
                <template #package="{ row }">
                    <div class="min-w-[220px]">
                        <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">{{ row.service.game_name }} · {{ row.service.name }}</p>
                        <strong class="mt-1 block text-slate-950">{{ row.name }}</strong>
                        <p class="mt-0.5 font-mono text-xs text-slate-500">{{ row.code }}</p>
                    </div>
                </template>
                <template #prices="{ row }">
                    <div class="grid min-w-[250px] gap-2">
                        <div
                            v-for="price in row.prices.slice(0, 1)"
                            :key="price.id ?? row.id"
                            class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-bold text-slate-600">Giá dịch vụ</span>
                                <strong class="text-sm text-emerald-700">{{ money(price.price) }}</strong>
                            </div>
                            <p class="mt-1 text-xs text-amber-700">CTV {{ money(price.collaborator_price) }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">
                                {{ price.quantity_enabled ? `SL ${price.min_quantity}–${price.max_quantity}` : 'SL cố định: 1' }}
                            </p>
                        </div>
                    </div>
                </template>
                <template #status="{ row }">
                    <span
                        class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold"
                        :class="row.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                    >
                        {{ row.status === 'active' ? 'Hoạt động' : 'Tạm tắt' }}
                    </span>
                </template>
                <template #orders="{ row }">
                    <span class="whitespace-nowrap text-sm font-bold text-slate-700">{{ row.orders_count }} đơn</span>
                </template>
                <template #sort_order="{ row }">
                    <span class="font-mono text-xs font-bold text-slate-600">#{{ row.sort_order }}</span>
                </template>
                <template #actions="{ row }">
                    <div class="flex min-w-[150px] justify-end gap-2">
                        <button
                            type="button"
                            class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-indigo-200 px-3 text-xs font-bold text-indigo-700 hover:bg-indigo-50"
                            @click="editPackage(row)"
                        >
                            <Pencil class="size-3.5" /> Sửa
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-rose-200 px-3 text-xs font-bold text-rose-600 hover:bg-rose-50"
                            @click="removePackage(row)"
                        >
                            <Trash2 class="size-3.5" /> Xóa
                        </button>
                    </div>
                </template>
            </DataTable>
        </section>

        <p
            v-if="!loading && catalogType === 'games' && rows.length === 0"
            class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500"
        >
            Không có dữ liệu phù hợp.
        </p>
    </main>
</template>
