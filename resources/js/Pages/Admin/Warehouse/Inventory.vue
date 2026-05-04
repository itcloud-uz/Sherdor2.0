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
                    <Link :href="route('admin.warehouse.index')" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-white transition-all">
                        ←
                    </Link>
                    <h1 class="text-3xl font-display font-bold text-white tracking-widest uppercase shadow-neon-cyan/50 text-shadow-glow flex items-center gap-3">
                        <span class="text-neon-cyan">📊</span> Ombor Qoldiqlari
                    </h1>
                </div>
            </div>

            <div class="glass-panel p-6 rounded-2xl border border-white/5">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm font-mono border-collapse">
                        <thead>
                            <tr class="border-b border-white/10 text-[10px] text-text-muted uppercase tracking-widest bg-black/40">
                                <th class="p-4 font-medium rounded-tl-xl">Kategoriya</th>
                                <th class="p-4 font-medium">Mahsulot Nomi</th>
                                <th class="p-4 font-medium text-right">Qoldiq</th>
                                <th class="p-4 font-medium text-right">O'rtacha Narx</th>
                                <th class="p-4 font-medium text-right rounded-tr-xl">Umumiy Qiymat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in inventory" :key="item.id" class="border-b border-white/5 hover:bg-white/5 transition-colors group">
                                <td class="p-4 text-xs">
                                    <span class="px-2 py-1 bg-white/5 border border-white/10 rounded uppercase text-text-secondary">{{ item.category }}</span>
                                </td>
                                <td class="p-4 text-white font-bold">{{ item.name }}</td>
                                <td class="p-4 text-right">
                                    <span class="text-lg font-bold" :class="item.quantity < 10 ? 'text-neon-red' : 'text-neon-cyan'">{{ item.quantity }}</span>
                                    <span class="text-[10px] ml-1 text-text-muted">{{ item.unit }}</span>
                                </td>
                                <td class="p-4 text-right text-text-secondary">{{ formatMoney(item.average_cost) }}</td>
                                <td class="p-4 text-right text-neon-green font-bold">{{ formatMoney(item.quantity * item.average_cost) }}</td>
                            </tr>
                            <tr v-if="inventory.length === 0">
                                <td colspan="5" class="p-12 text-center text-text-muted text-xs border border-dashed border-white/10 rounded-xl">Omborda mahsulotlar yo'q</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
