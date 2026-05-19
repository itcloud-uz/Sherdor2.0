<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    ownerId: [Number, String],
    inline: { type: Boolean, default: false }
});

const emit = defineEmits(['close', 'exportPdf']);

const form = ref({
    amount: '',
    discount: '0',
    date: new Date().toISOString().split('T')[0],
    account_type: 'main',
    payment_method: 'cash',
    description: '',
    
    accrual_name: '',
    accrual_qty: '',
    accrual_price: '',
    accrual_date: new Date().toISOString().split('T')[0],
    accrual_description: ''
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
        const response = await axios.get(route('admin.hr.owner.transactions', props.ownerId));
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
        await axios.post(route('admin.hr.owner.transaction.store', props.ownerId), payload);
        fetchData();
    } catch(e) {
        console.error(e);
        alert("Tranzaksiyani saqlashda xatolik!");
    }
}

function submitPayment() {
    if (!form.value.description) {
        alert("Izoh kiritish majburiy!");
        return;
    }
    submitTransaction({ 
        type: 'payment', 
        amount: form.value.amount, 
        discount: form.value.discount || 0,
        account_type: form.value.account_type,
        payment_method: form.value.payment_method,
        date: form.value.date, 
        description: form.value.description 
    });
    form.value.amount = '';
    form.value.discount = '0';
    form.value.description = '';
}

function submitAccrual() {
    let q = Number(form.value.accrual_qty) || 0;
    let p = Number(form.value.accrual_price) || 0;
    let total = q * p;
    if (total <= 0) {
        alert("Soni va narxi kiritilmagan yoki 0");
        return;
    }
    let desc = form.value.accrual_name ? `${form.value.accrual_name} (${q} x ${p})` : `Bajarilgan ish (${q} x ${p})`;
    if (form.value.accrual_description) {
        desc += ` - ${form.value.accrual_description}`;
    }
    
    submitTransaction({ 
        type: 'salary_accrual', 
        amount: total, 
        date: form.value.accrual_date, 
        description: desc 
    });
    form.value.accrual_name = '';
    form.value.accrual_qty = '';
    form.value.accrual_price = '';
    form.value.accrual_description = '';
}


</script>

<template>
    <Teleport to="body" :disabled="inline">
        <div v-if="show" :class="inline ? 'w-full mt-4 animate-[fadeIn_0.3s_ease-out]' : 'fixed inset-0 z-[9999] flex items-center justify-center p-4'">
        <div v-if="!inline" class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="close"></div>
        <div :class="inline ? 'glass-panel bg-abyss/40 border border-white/10 p-6 rounded-2xl w-full flex flex-col shadow-inner' : 'relative glass-panel bg-abyss/90 border border-white/10 p-6 rounded-2xl w-[90vw] max-w-[1200px] max-h-[90vh] overflow-y-auto mx-auto flex flex-col shadow-2xl animate-[fadeIn_0.3s_ease-out]'">
            
            <div class="flex justify-between items-center mb-6 relative z-10">
                <button @click="$emit('exportPdf', owner?.id)" class="px-4 py-2 bg-neon-yellow/20 text-neon-yellow border border-neon-yellow/30 rounded-lg hover:bg-neon-yellow hover:text-black transition-colors font-mono text-[10px] uppercase tracking-widest flex items-center gap-2">
                    ⬇ Yuklab Olish (Excel)
                </button>
                <h2 class="text-2xl font-display font-bold text-white tracking-widest uppercase text-shadow-glow">
                    {{ owner?.name }} <span class="text-neon-yellow opacity-80">- Texnikalar Jurnali</span>
                </h2>
                <button v-if="!inline" @click="close" class="w-10 h-10 flex items-center justify-center rounded-full bg-white/5 hover:bg-neon-red/20 text-white hover:text-neon-red transition-colors border border-white/10 hover:border-neon-red/50">
                    ✕
                </button>
                <button v-else @click="close" class="px-4 py-2 bg-neon-red/20 text-neon-red border border-neon-red/30 rounded-lg hover:bg-neon-red hover:text-black transition-colors font-mono text-[10px] uppercase tracking-widest flex items-center gap-2">
                    Yopish
                </button>
            </div>
            
            <div class="flex-1 custom-scrollbar overflow-x-auto border border-white/10 rounded-xl bg-black/40 mb-6">
                <table class="w-full text-left text-[13px] font-mono whitespace-nowrap">
                    <thead class="bg-white/5 border-b border-white/10 text-[11px] uppercase tracking-widest text-text-muted sticky top-0 z-10 backdrop-blur-md">
                        <tr>
                            <th class="p-4 border-r border-white/5 w-16 min-w-[64px] text-center text-text-muted">№</th>
                            <th class="p-4 border-r border-white/5 w-[130px] min-w-[130px]">Sana</th>
                            <th class="p-4 border-r border-white/5 text-center bg-white/5" colspan="3">Bajarilgan Ishlar (Qarzlarimiz)</th>
                            <th class="p-4 border-r border-white/5 text-center" colspan="3">To'landi (To'lovlarimiz)</th>
                            <th class="p-4 w-[130px] min-w-[130px] text-right">Qoldiq</th>
                        </tr>
                        <tr class="border-t border-white/5 text-[11px] bg-black/20">
                            <th class="border-r border-white/5 p-2"></th>
                            <th class="border-r border-white/5 p-2"></th>
                            <th class="border-r border-white/5 p-2 text-neon-yellow w-[180px] min-w-[180px]">Nomi</th>
                            <th class="border-r border-white/5 p-2 text-neon-yellow w-[80px] min-w-[80px]">Soni</th>
                            <th class="border-r border-white/5 p-2 text-neon-yellow w-[120px] min-w-[120px]">Narxi</th>
                            <th class="border-r border-white/5 p-2 text-neon-green w-[120px] min-w-[120px]">Naqd</th>
                            <th class="border-r border-white/5 p-2 text-neon-green w-[120px] min-w-[120px]">Karta</th>
                            <th class="border-r border-white/5 p-2 text-neon-green w-[120px] min-w-[120px]">Boshqa</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-white/90">

                        <tr v-for="(item, idx) in ledgerData.items" :key="idx" class="hover:bg-white/5 transition-colors group">
                            <td class="p-3 border-r border-white/5 text-center text-text-muted">{{ idx + 1 }}</td>
                            <td class="p-3 border-r border-white/5">{{ item.date ? new Date(item.date).toISOString().split('T')[0] : '' }}</td>
                            
                            <!-- Bajarilgan ish (Agar transaction_type qarz (expense) bo'lsa) -->
                            <td class="p-3 border-r border-white/5 text-[13px] whitespace-normal">{{ item.is_work ? item.desc : '' }}</td>
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
                    <span class="w-1.5 h-1.5 rounded-full bg-neon-red animate-pulse"></span>🔴 Bajarilgan Ish Kiritish (Qarz Yozish)
                </h3>
                <form @submit.prevent="submitAccrual" class="grid grid-cols-1 md:grid-cols-6 gap-4 relative z-10">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Sana</label>
                        <input v-model="form.accrual_date" type="date" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 [color-scheme:dark] outline-none" required />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Nomi</label>
                        <input v-model="form.accrual_name" type="text" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 focus:border-neon-red outline-none" required placeholder="Nomi" />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Soni</label>
                        <input v-model="form.accrual_qty" type="number" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 focus:border-neon-red outline-none" required placeholder="0" />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Narxi</label>
                        <input v-model="form.accrual_price" type="number" step="100" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 focus:border-neon-red outline-none" required placeholder="0" />
                    </div>
                    <div class="md:col-span-2 flex gap-4 items-end">
                        <div class="flex-grow">
                            <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Izoh (Ixtiyoriy)</label>
                            <input v-model="form.accrual_description" type="text" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 focus:border-neon-red outline-none" placeholder="Qo'shimcha izoh..." />
                        </div>
                        <div class="flex flex-col gap-1 items-end">
                            <span class="text-[10px] text-text-muted font-mono uppercase">Jami:</span>
                            <span class="text-sm font-bold text-neon-red whitespace-nowrap">{{ ((Number(form.accrual_qty)||0) * (Number(form.accrual_price)||0)).toLocaleString() }}</span>
                        </div>
                        <button type="submit" class="h-10 px-4 bg-neon-red/20 border border-neon-red text-neon-red font-mono text-[10px] uppercase tracking-widest rounded-lg hover:bg-neon-red hover:text-black transition-all font-bold whitespace-nowrap shadow-[0_0_10px_rgba(255,51,102,0.2)]">
                            Qo'shish
                        </button>
                    </div>
                </form>
            </div>

            <!-- BOTTOM FORM: Kassa Amaliyoti -->
            <div class="glass-card p-6 border-t-4 border-t-neon-green relative overflow-hidden">
                <div class="absolute inset-0 bg-neon-green/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-[10px] font-mono text-neon-green uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-neon-green animate-pulse"></span>🔵 Kassa amaliyoti (To'lov qilish)
                </h3>
                <form @submit.prevent="submitPayment" class="grid grid-cols-1 md:grid-cols-4 gap-4 relative z-10">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Hisob</label>
                        <select v-model="form.account_type" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 focus:border-neon-green outline-none" required>
                            <option value="main">Kompaniya Hisobi (Main)</option>
                            <option value="bank">Bank Hisobi (Bank)</option>
                            <option value="credit">Kredit Hisobi (Credit)</option>
                            <option value="partner">Ta'sischi Hisobi (Partner)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">To'lov Usuli</label>
                        <select v-model="form.payment_method" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 focus:border-neon-green outline-none" required>
                            <option value="cash">Naqd Pul</option>
                            <option value="card">Plastik Karta</option>
                            <option value="bank_transfer">Bank O'tkazmasi</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Summa (so'm)</label>
                        <input v-model="form.amount" type="number" step="100" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 focus:border-neon-green outline-none" required placeholder="0" />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1" title="Ixtiyoriy">Texnika Xarajati / Chegirma</label>
                        <input v-model="form.discount" type="number" step="100" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 focus:border-neon-green outline-none" placeholder="0" />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Sana</label>
                        <input v-model="form.date" type="date" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 [color-scheme:dark] outline-none" required />
                    </div>
                    <div class="md:col-span-3 flex gap-4 items-end">
                        <div class="flex-grow">
                            <label class="block text-[10px] uppercase tracking-widest font-mono text-text-muted mb-1">Izoh / Maqsad <span class="text-neon-red">*</span></label>
                            <input v-model="form.description" type="text" class="glass-input w-full bg-black/50 text-white font-mono text-sm h-10 border border-white/10 rounded-lg px-3 focus:border-neon-green outline-none" required placeholder="Nima uchun to'lov qilinmoqda?" />
                        </div>
                        <div class="flex flex-col gap-1 items-end">
                            <span class="text-[10px] text-text-muted font-mono uppercase">Jami yechiladi:</span>
                            <span class="text-sm font-bold text-neon-green whitespace-nowrap">{{ (Number(form.amount || 0) + Number(form.discount || 0)).toLocaleString() }}</span>
                        </div>
                        <button type="submit" class="h-10 px-6 bg-neon-green/20 border border-neon-green text-neon-green font-mono text-[10px] uppercase tracking-widest rounded-lg hover:bg-neon-green hover:text-black transition-all font-bold whitespace-nowrap shadow-[0_0_10px_rgba(0,255,100,0.2)]">
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </Teleport>
</template>
