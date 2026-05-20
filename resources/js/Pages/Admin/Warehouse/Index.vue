<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    stats: Object,
    recent_transactions: Array,
});

const formatMoney = (amount) => {
    return new Intl.NumberFormat('uz-UZ', { style: 'currency', currency: 'UZS' }).format(amount);
};
</script>

<template>
    <Head title="Taminotchilar Markazi" />
    <AdminLayout>
        <div class="space-y-6 animate-[fadeIn_0.5s_ease-out]">

            <!-- Navigation Links -->
            <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">
                <Link :href="route('admin.warehouse.inventory')" class="glass-card p-6 rounded-2xl border border-white/10 hover:border-neon-cyan group text-center transition-all">
                    <div class="w-16 h-16 mx-auto bg-neon-cyan/10 rounded-full flex items-center justify-center text-3xl group-hover:scale-110 transition-transform mb-4">
                        📊
                    </div>
                    <h3 class="text-lg font-bold text-white mb-1 uppercase tracking-widest font-display">Ombor Qoldiqlari</h3>
                    <p class="text-[10px] font-mono text-text-muted uppercase">Barcha mahsulotlar ro'yxati</p>
                </Link>

                <Link :href="route('admin.warehouse.suppliers')" class="glass-card p-6 rounded-2xl border border-white/10 hover:border-neon-yellow group text-center transition-all">
                    <div class="w-16 h-16 mx-auto bg-neon-yellow/10 rounded-full flex items-center justify-center text-3xl group-hover:scale-110 transition-transform mb-4">
                        🏢
                    </div>
                    <h3 class="text-lg font-bold text-white mb-1 uppercase tracking-widest font-display">{{ $t('suppliers') }}</h3>
                    <p class="text-[10px] font-mono text-text-muted uppercase">Kompaniyalar va Ledger</p>
                </Link>

                <Link :href="route('admin.warehouse.orders')" class="glass-card p-6 rounded-2xl border border-white/10 hover:border-neon-magenta group text-center transition-all">
                    <div class="w-16 h-16 mx-auto bg-neon-magenta/10 rounded-full flex items-center justify-center text-3xl group-hover:scale-110 transition-transform mb-4">
                        🛒
                    </div>
                    <h3 class="text-lg font-bold text-white mb-1 uppercase tracking-widest font-display">Buyurtmalar (PO)</h3>
                    <p class="text-[10px] font-mono text-text-muted uppercase">Yangi xaridlar va qabullar</p>
                </Link>

            </div>

            <!-- Recent Transactions -->
            <div class="glass-panel p-6 rounded-2xl border border-white/5 mt-8">
                <h3 class="text-lg font-bold text-white uppercase tracking-widest font-display mb-6 flex items-center gap-2">
                    <span class="text-neon-cyan">⚡</span> So'nggi Harakatlar (Kirim/Chiqim)
                </h3>
                
                <div class="overflow-x-auto">
                    <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left text-sm font-mono">
                        <thead>
                            <tr class="border-b border-white/10 text-[10px] text-text-muted uppercase tracking-widest">
                                <th class="py-3 font-medium">{{ $t('date_label') }}</th>
                                <th class="py-3 font-medium">Tur</th>
                                <th class="py-3 font-medium">Mahsulot</th>
                                <th class="py-3 font-medium text-right">Miqdor</th>
                                <th class="py-3 font-medium">Xodim</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in recent_transactions" :key="t.id" class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="py-3 text-text-secondary">{{ new Date(t.created_at).toLocaleString() }}</td>
                                <td class="py-3">
                                    <span v-if="t.type === 'in'" class="px-2 py-0.5 rounded text-[10px] uppercase tracking-widest border bg-neon-green/20 text-neon-green border-neon-green/30">{{ $t('income') }}</span>
                                    <span v-else class="px-2 py-0.5 rounded text-[10px] uppercase tracking-widest border bg-neon-red/20 text-neon-red border-neon-red/30">{{ $t('expense') }}</span>
                                </td>
                                <td class="py-3 text-white font-bold">{{ t.item?.name }}</td>
                                <td class="py-3 text-right text-neon-cyan font-bold">{{ t.type === 'in' ? '+' : '-' }}{{ t.quantity }} {{ t.item?.unit }}</td>
                                <td class="py-3 text-text-secondary">{{ t.user?.name || 'Tizim' }}</td>
                            </tr>
                            <tr v-if="recent_transactions.length === 0">
                                <td colspan="5" class="py-8 text-center text-text-muted text-xs border border-dashed border-white/10 rounded-xl">Harakatlar yo'q</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
