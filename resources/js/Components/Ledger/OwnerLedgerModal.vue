<script setup>
import { ref } from 'vue';

const props = defineProps({
    show: Boolean,
    owner: Object,
    ledgerData: {
        type: Object,
        default: () => ({ balance: 0, items: [] })
    }
});

const emit = defineEmits(['close', 'submitTransaction', 'exportPdf']);

const form = ref({
    amount: '',
    date: new Date().toISOString().split('T')[0],
    description: ''
});

function close() {
    emit('close');
}

function submit() {
    emit('submitTransaction', { ...form.value, target_id: props.owner?.id });
    form.value.amount = '';
    form.value.description = '';
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="close"></div>
        <div class="relative glass-panel bg-abyss/90 border border-white/10 p-6 rounded-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col shadow-2xl animate-[fadeIn_0.3s_ease-out]">
            
            <div class="flex justify-between items-center mb-6 relative z-10">
                <button @click="$emit('exportPdf', owner?.id)" class="px-4 py-2 bg-neon-yellow/20 text-neon-yellow border border-neon-yellow/30 rounded-lg hover:bg-neon-yellow hover:text-black transition-colors font-mono text-[10px] uppercase tracking-widest flex items-center gap-2">
                    ⬇ Yuklab Olish (Excel)
                </button>
                <h2 class="text-2xl font-display font-bold text-white tracking-widest uppercase text-shadow-glow">
                    {{ owner?.name }} <span class="text-neon-yellow opacity-80">- Texnikalar Jurnali</span>
                </h2>
                <button @click="close" class="w-10 h-10 flex items-center justify-center rounded-full bg-white/5 hover:bg-neon-red/20 text-white hover:text-neon-red transition-colors border border-white/10 hover:border-neon-red/50">
                    ✕
                </button>
            </div>
            
            <div class="flex-1 overflow-y-auto custom-scrollbar border border-white/10 rounded-xl bg-black/40 mb-6">
                <table class="w-full text-left text-sm font-mono whitespace-nowrap">
                    <thead class="bg-white/5 border-b border-white/10 text-[10px] uppercase tracking-widest text-text-muted sticky top-0 z-10 backdrop-blur-md">
                        <tr>
                            <th class="p-4 border-r border-white/5 w-16 text-center">№</th>
                            <th class="p-4 border-r border-white/5 w-32">Sana</th>
                            <th class="p-4 border-r border-white/5 text-center bg-white/5" colspan="3">Bajarilgan Ishlar (Qarzlarimiz)</th>
                            <th class="p-4 border-r border-white/5 text-center" colspan="3">To'landi (To'lovlarimiz)</th>
                            <th class="p-4 w-32 text-right">Qoldiq</th>
                        </tr>
                        <tr class="border-t border-white/5 text-[9px] bg-black/20">
                            <th class="border-r border-white/5 p-2"></th>
                            <th class="border-r border-white/5 p-2"></th>
                            <th class="border-r border-white/5 p-2 text-neon-yellow">Nomi</th>
                            <th class="border-r border-white/5 p-2 text-neon-yellow">Soni</th>
                            <th class="border-r border-white/5 p-2 text-neon-yellow">Narxi</th>
                            <th class="border-r border-white/5 p-2 text-neon-green">Naqd</th>
                            <th class="border-r border-white/5 p-2 text-neon-green">Karta</th>
                            <th class="border-r border-white/5 p-2 text-neon-green">Boshqa</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-white/90">
                        <tr v-for="(item, idx) in ledgerData.items" :key="idx" class="hover:bg-white/5 transition-colors group">
                            <td class="p-3 border-r border-white/5 text-center text-text-muted">{{ idx + 1 }}</td>
                            <td class="p-3 border-r border-white/5">{{ item.date ? new Date(item.date).toISOString().split('T')[0] : '' }}</td>
                            
                            <!-- Bajarilgan ish (Agar transaction_type qarz (expense) bo'lsa) -->
                            <td class="p-3 border-r border-white/5 text-xs">{{ item.is_work ? item.desc : '' }}</td>
                            <td class="p-3 border-r border-white/5 text-center">{{ item.is_work ? item.qty : '' }}</td>
                            <td class="p-3 border-r border-white/5 text-right font-bold text-neon-yellow">{{ item.is_work ? item.amount : '' }}</td>
                            
                            <!-- To'lovlar (Agar transaction_type tolov bo'lsa) -->
                            <td class="p-3 border-r border-white/5 text-right font-bold text-neon-green">{{ item.is_payment && item.account_type === 'main' ? item.amount : '' }}</td>
                            <td class="p-3 border-r border-white/5 text-right font-bold text-neon-cyan">{{ item.is_payment && item.account_type === 'credit' ? item.amount : '' }}</td>
                            <td class="p-3 border-r border-white/5 text-right font-bold text-text-muted">{{ item.is_payment && !['main', 'credit'].includes(item.account_type) ? item.amount : '' }}</td>
                            
                            <td class="p-3 text-right font-bold" :class="item.running_balance > 0 ? 'text-neon-red' : (item.running_balance < 0 ? 'text-neon-green' : 'text-text-muted')">
                                {{ item.running_balance !== undefined ? (item.running_balance > 0 ? '+' : '') + Number(item.running_balance).toLocaleString() : '' }}
                            </td>
                        </tr>
                        <tr v-if="!ledgerData.items || ledgerData.items.length === 0">
                            <td colspan="9" class="p-8 text-center text-text-muted italic border-dashed border border-white/5 rounded-xl m-4">Ma'lumot topilmadi</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-between items-center bg-black/60 p-4 rounded-xl border border-white/10 mb-6 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-neon-yellow/5 to-transparent pointer-events-none"></div>
                <span class="text-sm font-mono text-text-muted uppercase tracking-widest">Joriy Qoldiq (Qarzimiz):</span>
                <span class="text-3xl font-display font-bold drop-shadow-md" :class="ledgerData.balance > 0 ? 'text-neon-red' : (ledgerData.balance < 0 ? 'text-neon-green' : 'text-white')">
                    {{ Number(ledgerData.balance || 0).toLocaleString() }} so'm
                </span>
            </div>

            <div class="glass-card p-6 border-t-4 border-t-neon-yellow relative overflow-hidden">
                <div class="absolute inset-0 bg-neon-yellow/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-[10px] font-mono text-neon-yellow uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-neon-yellow animate-pulse"></span>Kassa amaliyoti (To'lov qilish)
                </h3>
                <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-4 gap-4 relative z-10">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Summa (so'm)</label>
                        <input v-model="form.amount" type="number" step="1000" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 focus:border-neon-yellow outline-none" required placeholder="Masalan: 1000000" />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Sana</label>
                        <input v-model="form.date" type="date" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 [color-scheme:dark] outline-none" required />
                    </div>
                    <div class="md:col-span-2 flex gap-4 items-end">
                        <div class="flex-grow">
                            <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Izoh (Ixtiyoriy)</label>
                            <input v-model="form.description" type="text" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 focus:border-neon-yellow outline-none" placeholder="Masalan: Ekskavator to'lovi..." />
                        </div>
                        <button type="submit" class="h-10 px-6 bg-neon-yellow/20 border border-neon-yellow text-neon-yellow font-mono text-[10px] uppercase tracking-widest rounded-lg hover:bg-neon-yellow hover:text-black transition-all font-bold whitespace-nowrap shadow-[0_0_10px_rgba(252,238,9,0.2)]">
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
            
        </div>
    </div>
</template>
