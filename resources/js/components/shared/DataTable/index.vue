<template>
    <div class="flex flex-col gap-3">
        <div class="w-full overflow-x-auto rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm" :class="props.classCustom">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            v-for="header in table.getHeaderGroups()[0].headers"
                            :key="header.id"
                            class="whitespace-nowrap px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            {{ header.column.columnDef.header }}
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">
                    <tr v-if="props.loading">
                        <td :colspan="columns.length" class="px-5 py-14 text-center text-slate-500">
                            <span class="inline-flex items-center gap-2 font-semibold">
                                <span
                                    class="h-5 w-5 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"
                                    aria-hidden="true"
                                ></span>
                                Đang tải dữ liệu...
                            </span>
                        </td>
                    </tr>
                    <template v-else>
                        <tr v-for="row in table.getRowModel().rows" :key="row.id" class="transition hover:bg-slate-50/80">
                            <td v-for="cell in row.getVisibleCells()" :key="cell.id" class="px-5 py-3 text-gray-700">
                                <slot :name="cell.column.id" :row="row.original" :value="cell.getValue()">
                                    {{ cell.getValue() }}
                                </slot>
                            </td>
                        </tr>
                    </template>

                    <tr v-if="!props.loading && !table.getRowModel().rows.length">
                        <td :colspan="columns.length" class="px-5 py-14 text-center text-gray-400">
                            {{ props.emptyText ?? 'Không có dữ liệu phù hợp.' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="props.totalPages > 1">
            <Paginate :current-page="props.currentPage" :total-pages="props.totalPages" :go-to-page="props.goToPage" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import Paginate from './PaginateComponent.vue';

const props = defineProps<{
    data: any[];
    columns: any[];
    goToPage: (page: number) => Promise<void>;
    totalPages: number;
    currentPage: number;
    loading: boolean;
    classCustom?: string;
    emptyText?: string;
}>();

const table = useVueTable({
    get data() {
        return props.data;
    },
    get columns() {
        return props.columns;
    },
    getCoreRowModel: getCoreRowModel(),
});
</script>
