<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    ownerId: [Number, String] // Changed from owner to ownerId
});

const emit = defineEmits(['close', 'exportPdf']);

const form = ref({
    amount: '',
    date: new Date().toISOString().split('T')[0],
    description: '',
    accrual_amount: '',
    accrual_date: new Date().toISOString().split('T')[0],
    accrual_description: ''
});

const showInlineRow = ref(false);
const inlineForm = ref({
    date: new Date().toISOString().split('T')[0],
    name: '',
    qty: '',
    price: '',
    cash: '',
    card: '',
    other: ''
});
const isSubmitting = ref(false);

const ledgerData = ref({ balance: 0, items: [] });
const owner = ref(null);
const isLoading = ref(false);

watch(() => props.show, (newVal) => {
    if (newVal && props.ownerId) {
        fetchData();
    }
});

async function fetchData() {
    isLoading.value = true;
    try {
        const response = await axios.get(route('admin.hr.owner.ledger', props.ownerId));
        const data = response.data;
        owner.value = data.owner;
        
        let allItems = [];
        let runningBalance = 0;

        if(data.logs) {
            data.logs.forEach(l => {
                allItems.push({
                    type: 'log',
                    date: l.date,
                    created_at: l.created_at,
                    is_work: true,
                    desc: l.description || (l.building ? `Obyekt: ${l.building.name}` : 'Ish'),
                    qty: l.hours_worked || l.volume_done,
                    amount: l.calculated_cost,
                    item: l
                });
            });
        }
        if(data.transactions) {
            data.transactions.forEach(t => {
                allItems.push({
                    type: 'tx',
                    date: t.date,
                    created_at: t.created_at,
                    is_payment: true,
                    account_type: t.account_type || 'main',
                    amount: t.amount,
                    item: t
                });
            });
        }
        
        if (data.owner_transactions) {
            data.owner_transactions.forEach(t => {
                let isWork = t.type === 'salary_accrual';
                let isPayment = t.type !== 'salary_accrual';
                allItems.push({
                    type: 'owner_tx',
                    date: t.date,
                    created_at: t.created_at,
                    is_work: isWork,
                    is_payment: isPayment,
                    account_type: 'main',
                    desc: t.description || (isWork ? 'Qarz yozish' : 'To\'lov'),
                    qty: '',
                    amount: t.amount,
                    item: t
                });
            });
        }
        
        // Sort chronologically ascending to calculate running balance
        allItems.sort((a, b) => {
            let d1 = new Date(a.date);
            let d2 = new Date(b.date);
            if (d1.getTime() !== d2.getTime()) return d1 - d2;
            return new Date(a.created_at) - new Date(b.created_at);
        });

        allItems.forEach(item => {
            if (item.is_work) runningBalance += Number(item.amount);
            if (item.is_payment) runningBalance -= Number(item.amount);
            item.running_balance = runningBalance;
        });
        
        // Sort descending for display
        allItems.reverse();

        ledgerData.value = {
            balance: data.balance,
            items: allItems
        };
    } catch (e) {
        console.error(e);
        alert("Ma'lumotlarni yuklashda xatolik");
    } finally {
        isLoading.value = false;
    }
}

function close() {
    emit('close');
}

async function submitTransaction(payload) {
    try {
        await axios.post(route('admin.hr.machine-owners.ledger.store', props.ownerId), payload);
        fetchData();
    } catch(e) {
        console.error(e);
        alert("Tranzaksiyani saqlashda xatolik!");
    }
}

function submitPayment() {
    submitTransaction({ type: 'payment', amount: form.value.amount, date: form.value.date, description: form.value.description });
    form.value.amount = '';
    form.value.description = '';
}

function submitAccrual() {
    submitTransaction({ type: 'salary_accrual', amount: form.value.accrual_amount, date: form.value.accrual_date, description: form.value.accrual_description });
    form.value.accrual_amount = '';
    form.value.accrual_description = '';
}

async function saveInlineForm() {
    isSubmitting.value = true;
    try {
        let workTotal = (Number(inlineForm.value.qty) || 0) * (Number(inlineForm.value.price) || 0);
        
        if (workTotal > 0) {
            await axios.post(route('admin.hr.machine-owners.ledger.store', props.ownerId), {
                type: 'salary_accrual',
                amount: workTotal,
                date: inlineForm.value.date,
                description: inlineForm.value.name ? `${inlineForm.value.name} (${inlineForm.value.qty} x ${inlineForm.value.price})` : `Bajarilgan ish (${inlineForm.value.qty} x ${inlineForm.value.price})`
            });
        }
        if (Number(inlineForm.value.cash) > 0) {
             await axios.post(route('admin.hr.machine-owners.ledger.store', props.ownerId), {
                type: 'payment',
                amount: inlineForm.value.cash,
                date: inlineForm.value.date,
                description: `Naqd: ${inlineForm.value.name || "To'lov"}`
             });
        }
        if (Number(inlineForm.value.card) > 0) {
             await axios.post(route('admin.hr.machine-owners.ledger.store', props.ownerId), {
                type: 'payment',
                amount: inlineForm.value.card,
                date: inlineForm.value.date,
                description: `Karta: ${inlineForm.value.name || "To'lov"}`
             });
        }
        if (Number(inlineForm.value.other) > 0) {
             await axios.post(route('admin.hr.machine-owners.ledger.store', props.ownerId), {
                type: 'payment',
                amount: inlineForm.value.other,
                date: inlineForm.value.date,
                description: `Boshqa: ${inlineForm.value.name || "To'lov"}`
             });
        }
        
        await fetchData(); // refresh the table
        showInlineRow.value = false;
        // reset form
        inlineForm.value = {
            date: new Date().toISOString().split('T')[0],
            name: '',
            qty: '',
            price: '',
            cash: '',
            card: '',
            other: ''
        };
    } catch(e) {
        console.error(e);
        alert("Xatolik!");
    } finally {
        isSubmitting.value = false;
    }
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
                            <th class="p-4 border-r border-white/5 w-16 text-center">
                                <button @click="showInlineRow = !showInlineRow" class="px-2 py-1 bg-neon-green/20 text-neon-green border border-neon-green/30 rounded text-[10px] hover:bg-neon-green hover:text-black transition-colors" title="Qator qo'shish">➕</button>
                            </th>
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
                        <tr v-if="showInlineRow" class="bg-neon-yellow/10 border-b border-neon-yellow/30">
                            <td class="p-2 border-r border-white/5 text-center text-neon-yellow">➕</td>
                            <td class="p-2 border-r border-white/5">
                                <input type="date" v-model="inlineForm.date" class="w-full bg-black/50 text-white text-[10px] border border-white/10 rounded px-1 py-1 outline-none focus:border-neon-yellow [color-scheme:dark]" />
                            </td>
                            <td class="p-2 border-r border-white/5">
                                <input type="text" v-model="inlineForm.name" placeholder="Nomi" class="w-24 bg-black/50 text-white text-[10px] border border-white/10 rounded px-1 py-1 outline-none focus:border-neon-yellow" />
                            </td>
                            <td class="p-2 border-r border-white/5">
                                <input type="number" v-model="inlineForm.qty" placeholder="0" class="w-12 bg-black/50 text-white text-[10px] border border-white/10 rounded px-1 py-1 outline-none focus:border-neon-yellow text-center" />
                            </td>
                            <td class="p-2 border-r border-white/5">
                                <input type="number" v-model="inlineForm.price" placeholder="0" step="100" class="w-16 bg-black/50 text-white text-[10px] border border-white/10 rounded px-1 py-1 outline-none focus:border-neon-yellow text-right" />
                            </td>
                            <td class="p-2 border-r border-white/5">
                                <input type="number" v-model="inlineForm.cash" placeholder="0" step="100" class="w-16 bg-black/50 text-white text-[10px] border border-white/10 rounded px-1 py-1 outline-none focus:border-neon-green text-right" />
                            </td>
                            <td class="p-2 border-r border-white/5">
                                <input type="number" v-model="inlineForm.card" placeholder="0" step="100" class="w-16 bg-black/50 text-white text-[10px] border border-white/10 rounded px-1 py-1 outline-none focus:border-neon-green text-right" />
                            </td>
                            <td class="p-2 border-r border-white/5">
                                <input type="number" v-model="inlineForm.other" placeholder="0" step="100" class="w-16 bg-black/50 text-white text-[10px] border border-white/10 rounded px-1 py-1 outline-none focus:border-neon-green text-right" />
                            </td>
                            <td class="p-2 text-center flex flex-col gap-1 items-end">
                                <div class="text-[9px] text-neon-yellow font-bold" title="Bajarilgan ish summasi">
                                    B: {{ ((Number(inlineForm.qty) || 0) * (Number(inlineForm.price) || 0)).toLocaleString() }}
                                </div>
                                <div class="text-[9px] text-neon-green font-bold" title="To'langan summa">
                                    T: {{ (Number(inlineForm.cash) + Number(inlineForm.card) + Number(inlineForm.other)).toLocaleString() }}
                                </div>
                                <div class="text-[9px] font-bold" :class="((Number(inlineForm.qty) || 0) * (Number(inlineForm.price) || 0)) - (Number(inlineForm.cash) + Number(inlineForm.card) + Number(inlineForm.other)) > 0 ? 'text-neon-red' : 'text-neon-cyan'" title="Qoldiq">
                                    Q: {{ (((Number(inlineForm.qty) || 0) * (Number(inlineForm.price) || 0)) - (Number(inlineForm.cash) + Number(inlineForm.card) + Number(inlineForm.other))).toLocaleString() }}
                                </div>
                                <div class="flex gap-1 mt-1 justify-end">
                                    <button @click="saveInlineForm" :disabled="isSubmitting" class="px-2 py-1 bg-neon-green/20 text-neon-green border border-neon-green/30 rounded text-[9px] hover:bg-neon-green hover:text-black">✅ Saqlash</button>
                                    <button @click="showInlineRow = false" :disabled="isSubmitting" class="px-2 py-1 bg-neon-red/20 text-neon-red border border-neon-red/30 rounded text-[9px] hover:bg-neon-red hover:text-black">❌</button>
                                </div>
                            </td>
                        </tr>
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

            <!-- TOP FORM: Bajarilgan Ish Kiritish -->
            <div class="glass-card p-6 border-t-4 border-t-neon-red relative overflow-hidden mb-6">
                <div class="absolute inset-0 bg-neon-red/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-[10px] font-mono text-neon-red uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-neon-red animate-pulse"></span>Bajarilgan Ish Kiritish (Qarz Yozish)
                </h3>
                <form @submit.prevent="submitAccrual" class="grid grid-cols-1 md:grid-cols-4 gap-4 relative z-10">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Summa (so'm)</label>
                        <input v-model="form.accrual_amount" type="number" step="1000" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 focus:border-neon-red outline-none" required placeholder="Masalan: 1000000" />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Sana</label>
                        <input v-model="form.accrual_date" type="date" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 [color-scheme:dark] outline-none" required />
                    </div>
                    <div class="md:col-span-2 flex gap-4 items-end">
                        <div class="flex-grow">
                            <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Izoh (Ixtiyoriy)</label>
                            <input v-model="form.accrual_description" type="text" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 focus:border-neon-red outline-none" placeholder="Masalan: Qazish ishlari uchun..." />
                        </div>
                        <button type="submit" class="h-10 px-6 bg-neon-red/20 border border-neon-red text-neon-red font-mono text-[10px] uppercase tracking-widest rounded-lg hover:bg-neon-red hover:text-black transition-all font-bold whitespace-nowrap shadow-[0_0_10px_rgba(255,51,102,0.2)]">
                            Qo'shish
                        </button>
                    </div>
                </form>
            </div>

            <div class="glass-card p-6 border-t-4 border-t-neon-yellow relative overflow-hidden">
                <div class="absolute inset-0 bg-neon-yellow/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-[10px] font-mono text-neon-yellow uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-neon-yellow animate-pulse"></span>Kassa amaliyoti (To'lov qilish)
                </h3>
                <form @submit.prevent="submitPayment" class="grid grid-cols-1 md:grid-cols-4 gap-4 relative z-10">
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
