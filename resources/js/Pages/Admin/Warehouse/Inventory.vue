<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    inventory: Array,
    transactions: Array,
});

const formatMoney = (amount) => {
    return new Intl.NumberFormat('uz-UZ', { style: 'currency', currency: 'UZS' }).format(amount);
};
</script>

<template>
    <Head title="Ombor Qoldiqlari" />
    <AdminLayout>
        <div class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <div class="flex justify-between items-center mb-8">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.warehouse.index')" class="w-10 h-10 flex items-center justify-center rounded-xl border border-gray-200 hover:bg-gray-100 text-gray-900 transition-all">
                        ←
                    </Link>
                    <h1 class="text-3xl font-display font-bold text-gray-900 tracking-widest uppercase shadow-neon-cyan/50 flex items-center gap-3 hidden">
                        <span class="text-blue-600">📊</span> Ombor Qoldiqlari
                    </h1>
                </div>
            </div>

            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left text-sm font-mono border-collapse">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                            <tr class="border-b border-gray-200 text-[10px] text-gray-500 uppercase tracking-widest /50 bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                <th class="p-4 font-medium rounded-tl-xl">Kategoriya</th>
                                <th class="p-4 font-medium">Mahsulot Nomi</th>
                                <th class="p-4 font-medium text-right">{{ $t('balance_remaining') }}</th>
                                <th class="p-4 font-medium text-right">O'rtacha Narx</th>
                                <th class="p-4 font-medium text-right rounded-tr-xl">Umumiy Qiymat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in inventory" :key="item.id" class="border-b border-gray-100 hover: transition-colors group bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                <td class="p-4 text-xs">
                                    <span class="px-2 py-1 border border-gray-200 rounded uppercase text-gray-600">{{ item.category }}</span>
                                </td>
                                <td class="p-4 text-gray-900 font-bold">{{ item.name }}</td>
                                <td class="p-4 text-right">
                                    <span class="text-lg font-bold" :class="item.quantity < 10 ? 'text-red-600' : 'text-blue-600'">{{ item.quantity }}</span>
                                    <span class="text-[10px] ml-1 text-gray-500">{{ item.unit }}</span>
                                </td>
                                <td class="p-4 text-right text-gray-600">{{ formatMoney(item.average_cost) }}</td>
                                <td class="p-4 text-right text-emerald-600 font-bold">{{ formatMoney(item.quantity * item.average_cost) }}</td>
                            </tr>
                            <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="inventory.length === 0">
                                <td colspan="5" class="p-12 text-center text-gray-500 text-xs border border-dashed border-gray-200 rounded-xl">Omborda mahsulotlar yo'q</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
