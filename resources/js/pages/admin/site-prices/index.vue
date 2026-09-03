<script setup lang="ts">
import { adminTenantService } from '@/services/admin-tenant.service';
import { BadgeDollarSign, LoaderCircle, Save } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const prices = ref<any[]>([]);
const loading = ref(true);
const savingId = ref<number | null>(null);
const message = ref('');
const formControlClass =
    'min-h-10 rounded-xl border-2 border-slate-300 bg-slate-50 px-3 py-2 text-slate-950 outline-none transition hover:border-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500';
const money = (value: number): string => `${Number(value || 0).toLocaleString('vi-VN')}đ`;

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        prices.value = (await adminTenantService.prices()).data.data.prices;
    } finally {
        loading.value = false;
    }
};

const save = async (item: any): Promise<void> => {
    savingId.value = item.package_id;
    message.value = '';
    try {
        const response = await adminTenantService.updatePrice(item.package_id, {
            pricing_mode: item.pricing_mode,
            fixed_price: item.fixed_price,
            markup_amount: item.markup_amount,
            markup_percentage: item.markup_percentage,
            is_active: item.is_active,
        });
        prices.value = response.data.data.prices;
        message.value = response.data.message;
    } finally {
        savingId.value = null;
    }
};

onMounted(load);
</script>

<template>
    <section class="space-y-6">
        <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"
                    ><BadgeDollarSign class="h-6 w-6"
                /></span>
                <div>
                    <p class="text-sm font-semibold text-emerald-600">Giá riêng website</p>
                    <h1 class="text-2xl font-black text-slate-950">Bảng giá bán</h1>
                </div>
            </div>
            <p class="mt-3 text-sm text-slate-500">
                Giá vốn tự động lấy theo giá gói tại NapCarot. Giá bán của website con được cộng lãi độc lập.
            </p>
        </header>
        <p v-if="message" class="rounded-xl bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ message }}</p>
        <div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div v-if="loading" class="p-10 text-center text-slate-500">Đang tính giá...</div>
            <table v-else class="w-full min-w-[980px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="p-4">Game / gói</th>
                        <th class="p-4">Mệnh giá</th>
                        <th class="p-4">Giá vốn</th>
                        <th class="p-4">Cách tính</th>
                        <th class="p-4">Giá trị</th>
                        <th class="p-4">Giá bán</th>
                        <th class="p-4">Lợi nhuận</th>
                        <th class="p-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="item in prices" :key="item.package_id">
                        <td class="p-4">
                            <strong>{{ item.game }}</strong>
                            <p class="text-xs text-slate-500">{{ item.package }}</p>
                        </td>
                        <td class="p-4 font-bold">{{ money(item.denomination) }}</td>
                        <td class="p-4 font-bold text-blue-700">{{ money(item.cost_price) }}</td>
                        <td class="p-4">
                            <select v-model="item.pricing_mode" :class="[formControlClass, 'min-w-44']">
                                <option value="markup_amount">Cộng số tiền</option>
                                <option value="markup_percentage">Cộng phần trăm</option>
                                <option value="fixed">Giá cố định</option>
                            </select>
                        </td>
                        <td class="p-4">
                            <input
                                v-if="item.pricing_mode === 'fixed'"
                                v-model.number="item.fixed_price"
                                type="number"
                                min="0"
                                :class="[formControlClass, 'w-36']"
                            /><input
                                v-else-if="item.pricing_mode === 'markup_percentage'"
                                v-model.number="item.markup_percentage"
                                type="number"
                                min="0"
                                step="0.01"
                                :class="[formControlClass, 'w-32']"
                            /><input
                                v-else
                                v-model.number="item.markup_amount"
                                type="number"
                                min="0"
                                :class="[formControlClass, 'w-36']"
                            />
                        </td>
                        <td class="p-4 font-black">{{ money(item.selling_price) }}</td>
                        <td class="p-4 font-bold" :class="item.profit >= 0 ? 'text-emerald-600' : 'text-rose-600'">{{ money(item.profit) }}</td>
                        <td class="p-4">
                            <button
                                :disabled="savingId === item.package_id"
                                class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 font-bold text-white disabled:opacity-60"
                                @click="save(item)"
                            >
                                <LoaderCircle v-if="savingId === item.package_id" class="h-4 w-4 animate-spin" /><Save v-else class="h-4 w-4" />Lưu
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
