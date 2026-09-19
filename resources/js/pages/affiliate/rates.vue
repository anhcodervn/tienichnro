<script setup lang="ts">
import { clientAffiliateService, type ClientAffiliateRate, type ClientAffiliateRatesData } from '@/services/client-affiliate.service';
import { handleErrorResponse } from '@/utils/response';
import { BadgePercent, CircleDollarSign, Gamepad2, LoaderCircle, Search } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const data = ref<ClientAffiliateRatesData | null>(null);
const loading = ref(true);
const search = ref('');
const selectedGame = ref('');
const money = (value: number): string => `${new Intl.NumberFormat('vi-VN').format(value)}đ`;
const games = computed(() => [...new Set(data.value?.rates.map((rate) => rate.game).filter(Boolean) ?? [])].sort((a, b) => a.localeCompare(b, 'vi')));
const filteredRates = computed(() => {
    const keyword = search.value.trim().toLocaleLowerCase('vi');

    return (data.value?.rates ?? []).filter((rate) => {
        const matchesGame = !selectedGame.value || rate.game === selectedGame.value;
        const matchesKeyword = !keyword || `${rate.game} ${rate.package}`.toLocaleLowerCase('vi').includes(keyword);

        return matchesGame && matchesKeyword;
    });
});
const rateLabel = (rate: ClientAffiliateRate): string =>
    rate.commission_type === 'fixed' ? `${money(rate.fixed_amount ?? 0)} / lượt` : `${rate.percentage ?? 0}% giá trị đơn`;

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        data.value = await clientAffiliateService.rates();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

onMounted(load);
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-600 via-emerald-700 to-slate-900 p-6 text-white shadow-lg sm:p-8">
            <div class="max-w-3xl">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.16em]">
                    <BadgePercent class="size-4" /> Chính sách hoa hồng
                </span>
                <h1 class="mt-4 text-3xl font-black sm:text-4xl">Bảng giá chiết khấu</h1>
                <p class="mt-3 leading-7 text-emerald-50">
                    Tra cứu số tiền hoa hồng dự kiến bạn nhận được khi một đơn giới thiệu thanh toán và nạp thành công.
                </p>
            </div>
        </section>

        <div v-if="loading" class="grid min-h-80 place-items-center rounded-2xl border border-slate-200 bg-white">
            <LoaderCircle class="size-9 animate-spin text-emerald-600" />
        </div>

        <template v-else-if="data">
            <section class="grid gap-4 sm:grid-cols-2">
                <article class="flex items-center gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                    <span class="grid size-12 place-items-center rounded-2xl bg-emerald-600 text-white"><Gamepad2 class="size-6" /></span>
                    <div><p class="text-sm font-bold text-emerald-800">Game có hoa hồng</p><p class="text-2xl font-black text-emerald-950">{{ games.length }}</p></div>
                </article>
                <article class="flex items-center gap-4 rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                    <span class="grid size-12 place-items-center rounded-2xl bg-blue-600 text-white"><CircleDollarSign class="size-6" /></span>
                    <div><p class="text-sm font-bold text-blue-800">Gói đang áp dụng</p><p class="text-2xl font-black text-blue-950">{{ data.rates.length }}</p></div>
                </article>
            </section>

            <section class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_16rem] sm:p-5">
                <label class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                    <input v-model="search" type="search" placeholder="Tìm theo tên game hoặc gói nạp" class="min-h-12 w-full rounded-xl border-2 border-slate-200 pl-11 pr-4 outline-none focus:border-emerald-500" />
                </label>
                <select v-model="selectedGame" class="min-h-12 rounded-xl border-2 border-slate-200 bg-white px-4 font-bold text-slate-700 outline-none focus:border-emerald-500">
                    <option value="">Tất cả game</option>
                    <option v-for="game in games" :key="game" :value="game">{{ game }}</option>
                </select>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Game / gói nạp</th>
                                <th class="px-5 py-3">Giá tham khảo</th>
                                <th class="px-5 py-3">Mức chiết khấu</th>
                                <th class="px-5 py-3">Hoa hồng dự kiến</th>
                                <th class="px-5 py-3">Áp dụng</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="rate in filteredRates" :key="rate.package_id" class="hover:bg-slate-50/70">
                                <td class="px-5 py-4"><p class="font-black text-slate-900">{{ rate.game }}</p><p class="text-xs text-slate-500">{{ rate.package }}</p></td>
                                <td class="px-5 py-4 font-semibold">{{ money(rate.selling_price) }}</td>
                                <td class="px-5 py-4"><span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-blue-800">{{ rateLabel(rate) }}</span></td>
                                <td class="px-5 py-4"><strong class="text-lg text-emerald-700">{{ money(rate.estimated_commission) }}</strong><p v-if="rate.commission_type === 'percentage'" class="mt-1 text-xs text-slate-500">Ước tính theo giá hiện tại</p></td>
                                <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ rate.source === 'global' ? 'Chính sách Global' : 'Riêng gói' }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!filteredRates.length" class="grid min-h-52 place-items-center p-8 text-center text-slate-500">
                    <div><BadgePercent class="mx-auto size-10 text-slate-300" /><p class="mt-3 font-bold">Không tìm thấy gói phù hợp</p></div>
                </div>
            </section>

            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
                <strong>Lưu ý:</strong> Hoa hồng cố định được tính trên mỗi lượt nạp thành công. Với hoa hồng phần trăm, số tiền thực nhận được tính trên giá trị đơn thanh toán thực tế nên có thể thay đổi khi khách được giảm giá.
            </section>
        </template>
    </main>
</template>
