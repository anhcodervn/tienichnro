<script setup lang="ts">
import { adminGlobalRewardService, type GlobalRewardCatalog, type GlobalRewardGame } from '@/services/admin-global-reward.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { ArrowLeft, Check, Coins, Copy, CreditCard, Gamepad2, Gift, LoaderCircle, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type ReceiveDraft = {
    code: string;
    label: string;
    base_amount: string | number;
    reward_x2_amount: string | number;
    reward_x3_amount: string | number;
    first_topup_reward_amount: string | number;
};
type PackageDraft = { receives: ReceiveDraft[] };

const catalog = ref<GlobalRewardCatalog>({ games: [] });
const selectedGameId = ref<number | null>(null);
const selectedDenomination = ref<number | null>(null);
const loading = ref(false);
const saving = ref(false);
const drafts = reactive<Record<number, PackageDraft>>({});
const fieldClass =
    'min-h-10 w-full rounded-md border-2 border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-950 outline-none transition hover:border-slate-400 focus:border-cyan-500 focus:bg-white focus:ring-4 focus:ring-cyan-100';

const selectedGame = computed(() => catalog.value.games.find((game) => game.id === selectedGameId.value) ?? null);
const selectedPackage = computed(() => {
    const denomination = selectedDenomination.value;
    if (denomination === null || denomination < 1) return null;

    return (
        selectedGame.value?.denominations.find((item) => item.denomination === denomination) ?? {
            name: `Mệnh giá ${money(denomination)}`,
            denomination,
            status: 'inactive' as const,
        }
    );
});
const hasSetting = (game: GlobalRewardGame, denomination: number): boolean => game.reward_settings.some((item) => item.denomination === denomination);
const receive = (code = '', label = ''): ReceiveDraft => ({
    code,
    label,
    base_amount: '',
    reward_x2_amount: '',
    reward_x3_amount: '',
    first_topup_reward_amount: '',
});
const suggestedReceives = (game: GlobalRewardGame): ReceiveDraft[] => {
    const identity = `${game.slug} ${game.name}`.toLocaleLowerCase('vi-VN');
    if (identity.includes('avatar-mus') || identity.includes('avatar musik')) return [receive('GM', 'Gem mở'), receive('GK', 'Gem khóa')];
    if (identity.includes('ngoc-rong') || identity.includes('ngọc rồng') || identity.includes('chú bé rồng')) return [receive('NX', 'Ngọc xanh')];
    if (identity.includes('hai-tac') || identity.includes('hải tặc')) return [receive('EXTOL', 'Extol'), receive('RUBY', 'Ruby')];
    if (identity.includes('army-2') || identity.includes('army 2')) return [receive('LUONG', 'Lượng'), receive('XU', 'Xu')];
    if (identity.includes('avatar-bum') || identity.includes('avatar bùm')) return [receive('GEM', 'Gem'), receive('VANG', 'Vàng')];
    if (identity.includes('hiep-si') || identity.includes('hiệp sĩ') || identity.includes('son-thuy') || identity.includes('sơn thủy')) {
        return [receive('NGOC', 'Ngọc')];
    }
    if (
        identity.includes('ninja') ||
        identity.includes('avatar') ||
        identity.includes('ngu-long') ||
        identity.includes('ngũ long') ||
        identity.includes('khi-phach') ||
        identity.includes('khí phách')
    ) {
        return [receive('LUONG', 'Lượng')];
    }
    return [receive()];
};
const hydrateDrafts = (): void => {
    for (const denomination of Object.keys(drafts)) {
        delete drafts[Number(denomination)];
    }

    const game = selectedGame.value;
    if (!game) return;

    for (const denomination of game.denominations) {
        const setting = game.reward_settings.find((item) => item.denomination === denomination.denomination);
        drafts[denomination.denomination] = {
            receives: setting
                ? setting.receives.map((item) => ({
                      code: item.code,
                      label: item.label,
                      base_amount: item.base_amount,
                      reward_x2_amount: item.reward_x2_amount ?? '',
                      reward_x3_amount: item.reward_x3_amount ?? '',
                      first_topup_reward_amount: item.first_topup_reward_amount ?? '',
                  }))
                : suggestedReceives(game),
        };
    }
};
const ensureSelectedDraft = (): void => {
    const game = selectedGame.value;
    const denomination = selectedDenomination.value;
    if (!game || denomination === null || denomination < 1 || drafts[denomination]) return;

    const setting = game.reward_settings.find((item) => item.denomination === denomination);
    drafts[denomination] = {
        receives: setting
            ? setting.receives.map((item) => ({
                  code: item.code,
                  label: item.label,
                  base_amount: item.base_amount,
                  reward_x2_amount: item.reward_x2_amount ?? '',
                  reward_x3_amount: item.reward_x3_amount ?? '',
                  first_topup_reward_amount: item.first_topup_reward_amount ?? '',
              }))
            : suggestedReceives(game),
    };
};
const nullableNumber = (value: string | number): number | null => (value === '' ? null : Number(value));
const money = (value: number): string => `${new Intl.NumberFormat('vi-VN').format(value)}đ`;
const notify = (message: string): void => handleSuccessResponse({ data: { status: true, message } });

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        catalog.value = await adminGlobalRewardService.catalog();
        if (!catalog.value.games.some((game) => game.id === selectedGameId.value)) {
            selectedGameId.value = catalog.value.games[0]?.id ?? null;
        }
        if (!selectedGame.value?.denominations.some((denomination) => denomination.denomination === selectedDenomination.value)) {
            selectedDenomination.value = selectedGame.value?.denominations[0]?.denomination ?? null;
        }
        hydrateDrafts();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const save = async (): Promise<void> => {
    if (!selectedGame.value || !selectedPackage.value) return;
    const denomination = selectedPackage.value.denomination;
    const draft = drafts[denomination];
    const hasInvalidReceive = draft.receives.some(
        (item) => item.code.trim() === '' || item.label.trim() === '' || item.base_amount === '' || Number.isNaN(Number(item.base_amount)),
    );
    if (hasInvalidReceive) {
        handleErrorResponse(new Error('Hãy nhập đủ mã đơn vị, tên đơn vị và lượng nhận cơ bản.'));
        return;
    }
    saving.value = true;
    try {
        const packages = [
            {
                denomination,
                receives: drafts[denomination].receives.map((item) => ({
                    code: item.code.trim().toUpperCase(),
                    label: item.label.trim(),
                    base_amount: Number(item.base_amount),
                    reward_x2_amount: nullableNumber(item.reward_x2_amount),
                    reward_x3_amount: nullableNumber(item.reward_x3_amount),
                    first_topup_reward_amount: nullableNumber(item.first_topup_reward_amount),
                })),
            },
        ];
        await adminGlobalRewardService.updateGame(selectedGame.value.id, packages);
        notify(`Đã lưu ${money(denomination)} cho ${selectedGame.value.name}.`);
        await load();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

const addReceive = (denomination: number): void => {
    drafts[denomination].receives.push(receive());
};
const removeReceive = (denomination: number, index: number): void => {
    if (drafts[denomination].receives.length > 1) drafts[denomination].receives.splice(index, 1);
};
const copyX2ToX3 = (): void => {
    if (!selectedPackage.value) return;
    for (const item of drafts[selectedPackage.value.denomination].receives) {
        if (item.reward_x2_amount !== '') item.reward_x3_amount = item.reward_x2_amount;
    }
    notify(`Đã chép X2 sang X3 cho mệnh giá ${money(selectedPackage.value.denomination)}.`);
};

watch(selectedGameId, () => {
    if (!selectedGame.value?.denominations.some((denomination) => denomination.denomination === selectedDenomination.value)) {
        selectedDenomination.value = selectedGame.value?.denominations[0]?.denomination ?? null;
    }
    hydrateDrafts();
    ensureSelectedDraft();
});
watch(selectedDenomination, ensureSelectedDraft);
onMounted(load);
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <header class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-cyan-700">Thiết lập theo từng bước</p>
                <h1 class="mt-1 flex items-center gap-2 text-2xl font-black text-slate-950"><Gift class="h-6 w-6" /> Bảng thực nhận game</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Áp dụng cho tất cả game, không phụ thuộc bảng giá riêng hay Global. Hệ thống đối chiếu bằng game + mệnh giá thẻ thực.
                </p>
            </div>
            <RouterLink
                to="/admin/topup/packages"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border-2 border-slate-300 bg-white px-4 text-sm font-bold text-slate-800 transition hover:border-cyan-400 hover:text-cyan-700"
            >
                <ArrowLeft class="h-4 w-4" /> Quản lý gói nạp
            </RouterLink>
        </header>

        <div v-if="loading" class="grid min-h-64 place-items-center rounded-xl border border-slate-200 bg-white">
            <LoaderCircle class="h-8 w-8 animate-spin text-slate-400" />
        </div>

        <template v-else-if="catalog.games.length">
            <section class="grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm lg:grid-cols-4">
                <div class="flex items-center gap-3 rounded-lg bg-cyan-50 p-3 text-cyan-800">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-cyan-700 text-sm font-black text-white">1</span>
                    <span class="text-sm font-bold">Chọn game</span>
                </div>
                <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 text-slate-700">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-slate-200 text-sm font-black">2</span>
                    <span class="text-sm font-bold">Mệnh giá thẻ</span>
                </div>
                <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 text-slate-700">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-slate-200 text-sm font-black">3</span>
                    <span class="text-sm font-bold">Đơn vị & số lượng</span>
                </div>
                <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 text-slate-700">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-slate-200 text-sm font-black">4</span>
                    <span class="text-sm font-bold">Lưu mệnh giá</span>
                </div>
            </section>

            <section class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <header class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-lg bg-cyan-100 text-cyan-700"><Gamepad2 class="h-5 w-5" /></span>
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-cyan-700">Bước 1</p>
                        <h2 class="font-black text-slate-950">Chọn game cần cấu hình</h2>
                    </div>
                </header>
                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <button
                        v-for="game in catalog.games"
                        :key="game.id"
                        type="button"
                        class="flex min-h-14 items-center justify-between gap-3 rounded-lg border-2 px-4 py-3 text-left transition"
                        :class="
                            selectedGameId === game.id
                                ? 'border-cyan-600 bg-cyan-50 text-cyan-950 ring-4 ring-cyan-100'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-cyan-300'
                        "
                        @click="selectedGameId = game.id"
                    >
                        <div>
                            <p class="font-black">{{ game.name }}</p>
                            <p class="text-xs opacity-70">{{ game.status === 'active' ? 'Đang hoạt động' : 'Tạm tắt' }}</p>
                        </div>
                        <Check v-if="selectedGameId === game.id" class="h-5 w-5 shrink-0" />
                    </button>
                </div>
            </section>

            <template v-if="selectedGame">
                <section
                    class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.55fr)]"
                >
                    <div class="grid gap-3">
                        <header class="flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-lg bg-violet-100 text-violet-700"
                                ><CreditCard class="h-5 w-5"
                            /></span>
                            <div>
                                <p class="text-xs font-black uppercase tracking-wider text-violet-700">Bước 2</p>
                                <h2 class="font-black text-slate-950">Chọn mệnh giá thực của thẻ</h2>
                            </div>
                        </header>
                        <label class="grid gap-1 text-sm font-bold">
                            Mệnh giá thẻ
                            <input
                                v-model.number="selectedDenomination"
                                min="1"
                                step="1000"
                                type="number"
                                placeholder="Ví dụ: 10000, 20000, 50000"
                                :class="fieldClass"
                            />
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="denomination in selectedGame.denominations"
                                :key="`quick-${denomination.denomination}`"
                                type="button"
                                class="rounded-lg border-2 px-3 py-2 text-sm font-black transition"
                                :class="
                                    selectedDenomination === denomination.denomination
                                        ? 'border-violet-600 bg-violet-50 text-violet-800'
                                        : 'border-slate-200 text-slate-600 hover:border-violet-300'
                                "
                                @click="selectedDenomination = denomination.denomination"
                            >
                                {{ money(denomination.denomination) }}
                            </button>
                        </div>
                        <p v-if="selectedGame.denominations.length === 0" class="text-xs text-slate-500">
                            Game chưa có mệnh giá gợi ý. Bạn vẫn có thể nhập trực tiếp mệnh giá thẻ ở ô trên.
                        </p>
                    </div>

                    <div v-if="selectedPackage" class="grid content-start gap-3 rounded-lg border-2 border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase text-slate-500">Mốc đang nhập</p>
                                <p class="text-xl font-black text-slate-950">{{ money(selectedPackage.denomination) }}</p>
                                <p class="text-xs text-slate-500">{{ selectedPackage.name }}</p>
                            </div>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-bold"
                                :class="
                                    hasSetting(selectedGame, selectedPackage.denomination)
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-amber-100 text-amber-700'
                                "
                            >
                                {{ hasSetting(selectedGame, selectedPackage.denomination) ? 'Đã lưu' : 'Chưa lưu' }}
                            </span>
                        </div>
                    </div>
                </section>

                <section v-if="selectedPackage" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-lg bg-amber-100 text-amber-700"><Coins class="h-5 w-5" /></span>
                            <div>
                                <p class="text-xs font-black uppercase tracking-wider text-amber-700">Bước 3</p>
                                <h2 class="font-black text-slate-950">Nhập đơn vị và lượng nhận được</h2>
                                <p class="text-xs text-slate-500">Ví dụ: Ngọc xanh; hoặc hai đơn vị GM và GK.</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border-2 border-cyan-300 px-4 text-sm font-bold text-cyan-800 transition hover:bg-cyan-50"
                            @click="copyX2ToX3"
                        >
                            <Copy class="h-4 w-4" /> Chép X2 sang X3
                        </button>
                    </header>

                    <div
                        v-for="(item, index) in drafts[selectedPackage.denomination].receives"
                        :key="`${selectedPackage.denomination}-${index}`"
                        class="grid gap-3 rounded-lg border-2 border-slate-200 bg-slate-50 p-3 xl:grid-cols-[7rem_minmax(10rem,1.4fr)_repeat(4,minmax(7rem,1fr))_2.5rem] xl:items-end"
                    >
                        <label class="grid gap-1 text-xs font-bold">
                            Mã đơn vị
                            <input v-model.trim="item.code" required maxlength="20" placeholder="NX" :class="[fieldClass, 'font-mono uppercase']" />
                        </label>
                        <label class="grid gap-1 text-xs font-bold">
                            Đơn vị nhận
                            <input v-model.trim="item.label" required maxlength="60" placeholder="Ngọc xanh" :class="fieldClass" />
                        </label>
                        <label class="grid gap-1 text-xs font-bold">
                            Nhận cơ bản
                            <input v-model="item.base_amount" required min="0" type="number" placeholder="0" :class="fieldClass" />
                        </label>
                        <label class="grid gap-1 text-xs font-bold">
                            Khuyến mãi X2
                            <input v-model="item.reward_x2_amount" min="0" type="number" placeholder="Để trống" :class="fieldClass" />
                        </label>
                        <label class="grid gap-1 text-xs font-bold">
                            Khuyến mãi X3
                            <input v-model="item.reward_x3_amount" min="0" type="number" placeholder="Để trống" :class="fieldClass" />
                        </label>
                        <label class="grid gap-1 text-xs font-bold">
                            Nạp lần đầu
                            <input v-model="item.first_topup_reward_amount" min="0" type="number" placeholder="Để trống" :class="fieldClass" />
                        </label>
                        <button
                            type="button"
                            class="inline-flex size-10 items-center justify-center rounded-lg border-2 border-rose-200 text-rose-600 transition hover:bg-rose-50 disabled:opacity-40"
                            :disabled="drafts[selectedPackage.denomination].receives.length <= 1"
                            aria-label="Xóa đơn vị thực nhận"
                            @click="removeReceive(selectedPackage.denomination, index)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border-2 border-dashed border-cyan-300 px-3 text-sm font-bold text-cyan-800 transition hover:bg-cyan-50 disabled:opacity-40"
                        :disabled="drafts[selectedPackage.denomination].receives.length >= 5"
                        @click="addReceive(selectedPackage.denomination)"
                    >
                        <Plus class="h-4 w-4" /> Thêm đơn vị nhận cho mệnh giá {{ money(selectedPackage.denomination) }}
                    </button>
                </section>

                <section
                    v-if="selectedPackage"
                    class="sticky bottom-3 z-10 flex flex-col gap-3 rounded-xl border border-cyan-200 bg-white/95 p-4 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-cyan-700">Bước 4 · Kiểm tra và lưu</p>
                        <p class="font-bold text-slate-950">{{ selectedGame.name }} · {{ money(selectedPackage.denomination) }}</p>
                        <p class="text-xs text-slate-500">Các mệnh giá khác không bị thay đổi.</p>
                    </div>
                    <button
                        :disabled="saving"
                        type="button"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-cyan-700 px-6 text-sm font-black text-white transition hover:bg-cyan-800 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="save"
                    >
                        <LoaderCircle v-if="saving" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        {{ saving ? 'Đang lưu...' : `Lưu mệnh giá ${money(selectedPackage.denomination)}` }}
                    </button>
                </section>
            </template>
        </template>

        <section v-else class="rounded-xl border-2 border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">
            Chưa có game để cấu hình bảng thực nhận.
        </section>
    </main>
</template>
