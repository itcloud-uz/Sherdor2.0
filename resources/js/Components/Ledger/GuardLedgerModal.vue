<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    guardId: [Number, String],
    inline: { type: Boolean, default: false }
});

const emit = defineEmits(['close']);

const form = ref({
    amount: '',
    discount: '0',
    date: new Date().toISOString().split('T')[0],
    account_type: 'main',
    payment_method: 'cash',
    description: '',
    
    accrual_month: new Date().toISOString().split('T')[0].substring(0, 7), // YYYY-MM
    accrual_amount: '',
    accrual_date: new Date().toISOString().split('T')[0],
    accrual_description: ''
});

const ledgerData = ref({ balance: 0, items: [] });
const guard = ref(null);
const isLoading = ref(false);

watch(() => props.show, (newVal) => {
    if (newVal && props.guardId) {
        fetchData();
    }
});

async function fetchData() {
    isLoading.value = true;
    try {
        const response = await axios.get(route('admin.hr.guard.transactions', props.guardId));
        const data = response.data;
        guard.value = data.guard || { name: 'Qorovul' }; // default fallback
        
        let allItems = [];
        let runningBalance = 0;
        
        if(data.transactions) {
            data.transactions.forEach(t => {
                let isAccrual = t.type === 'salary_accrual';
                let isPayment = t.type !== 'salary_accrual';
                allItems.push({
                    type: 'tx',
                    date: t.date,
                    created_at: t.created_at,
                    is_work: isAccrual,
                    is_payment: isPayment,
                    account_type: 'main',
                    desc: t.description || (isAccrual ? 'Oylik/Qarz' : 'To\'lov'),
                    amount: t.amount,
                    item: t
                });
            });
        }
        
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
        await axios.post(route('admin.hr.guard.transaction.store', props.guardId), payload);
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
    let amt = Number(form.value.accrual_amount) || 0;
    if (amt <= 0) {
        alert("Miqdori kiritilmagan yoki 0");
        return;
    }
    let desc = `Maosh (${form.value.accrual_month})`;
    if (form.value.accrual_description) {
        desc += ` - ${form.value.accrual_description}`;
    }
    
    submitTransaction({ 
        type: 'salary_accrual', 
        amount: amt, 
        date: form.value.accrual_date, 
        description: desc 
    });
    form.value.accrual_amount = '';
    form.value.accrual_description = '';
}
import { exportLedgerToPDF, exportLedgerToExcel } from '@/utils/exportUtils';

function handleExportPDF() {
    const title = `${guard.value?.name || 'Qorovul'} - Qorovul Jurnali`;
    const columns = ["№", "Sana", "Nomi/Izoh", "Summa (Qarz/Oylik)", "Naqd (To'landi)", "Karta", "Boshqa", "Qoldiq"];
    const dataRows = ledgerData.value.items.map((item, index) => [
        index + 1,
        item.date ? new Date(item.date).toISOString().split('T')[0] : '',
        item.is_work ? item.desc : '',
        item.is_work ? item.amount : '',
        item.is_payment && item.account_type === 'main' ? item.amount : '',
        item.is_payment && item.account_type === 'credit' ? item.amount : '',
        item.is_payment && !['main', 'credit'].includes(item.account_type) ? item.amount : '',
        item.running_balance !== undefined ? item.running_balance : ''
    ]);
    exportLedgerToPDF(title, columns, dataRows);
}

function handleExportExcel() {
    const title = `${guard.value?.name || 'Qorovul'} - Qorovul Jurnali`;
    const columns = ["№", "Sana", "Nomi/Izoh", "Summa (Qarz/Oylik)", "Naqd (To'landi)", "Karta", "Boshqa", "Qoldiq"];
    const dataRows = ledgerData.value.items.map((item, index) => [
        index + 1,
        item.date ? new Date(item.date).toISOString().split('T')[0] : '',
        item.is_work ? item.desc : '',
        item.is_work ? item.amount : '',
        item.is_payment && item.account_type === 'main' ? item.amount : '',
        item.is_payment && item.account_type === 'credit' ? item.amount : '',
        item.is_payment && !['main', 'credit'].includes(item.account_type) ? item.amount : '',
        item.running_balance !== undefined ? item.running_balance : ''
    ]);
    exportLedgerToExcel(title, columns, dataRows);
}
</script>

<template>
    <Teleport to="body" :disabled="inline">
        <div v-if="show" :class="inline ? 'w-full mt-4 animate-[fadeIn_0.3s_ease-out]' : 'fixed inset-0 z-[9999] flex items-center justify-center p-4'">
        <div v-if="!inline" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="close"></div>
        <div :class="inline ? 'bg-white border border-gray-200 shadow-sm rounded-xl bg-abyss/40 border border-gray-200 p-6 rounded-2xl w-full flex flex-col shadow-inner' : 'relative bg-white border border-gray-200 shadow-sm rounded-xl bg-abyss/90 border border-gray-200 p-6 rounded-2xl w-[90vw] max-w-[1200px] max-h-[90vh] overflow-y-auto mx-auto flex flex-col shadow-2xl animate-[fadeIn_0.3s_ease-out]'">
            
            <div class="shrink-0 flex justify-between items-center mb-6 relative z-10">
                <div v-if="!inline" class="flex gap-2 w-32">
                    <button @click="handleExportPDF" class="p-2 bg-red-500/20 text-red-400 border border-red-200 rounded-lg hover:bg-red-500 hover:text-gray-900 transition-colors text-xs font-mono tracking-widest flex items-center justify-center shadow-lg" title="PDF formatida yuklab olish">
                        📄 PDF
                    </button>
                    <button @click="handleExportExcel" class="p-2 bg-green-500/20 text-green-400 border border-green-500/30 rounded-lg hover:bg-green-500 hover:text-gray-900 transition-colors text-xs font-mono tracking-widest flex items-center justify-center shadow-lg" title="Excel formatida yuklab olish">
                        📊 EXCEL
                    </button>
                </div>
                <div v-else class="w-32"></div> <!-- Placeholder -->
                <h2 class="text-2xl font-display font-bold text-gray-900 tracking-widest uppercase  flex-1 text-center">
                    {{ guard?.name }} <span class="text-blue-600 opacity-80">- Qorovul Jurnali</span>
                </h2>
                <div class="flex justify-end w-32">
                    <button v-if="!inline" @click="close" class="w-10 h-10 flex items-center justify-center rounded-full bg-white/5 hover:bg-red-50 text-gray-900 hover:text-red-600 transition-colors border border-gray-200 hover:border-red-200">
                        ✕
                    </button>
                    <button v-else @click="close" class="px-4 py-2 bg-red-50 text-red-600 border border-red-200 rounded-lg hover:bg-red-600 hover:text-black transition-colors font-mono text-[10px] uppercase tracking-widest flex items-center gap-2">
                        Yopish
                    </button>
                </div>
            </div>
            
            <div class="flex-1 min-h-[150px] custom-scrollbar overflow-y-auto overflow-x-auto border border-gray-200 rounded-xl bg-gray-900/50 mb-6">
                <table class="w-full text-left text-[13px] font-mono whitespace-nowrap">
                    <thead class="bg-white/5 border-b border-gray-200 text-[11px] uppercase tracking-widest text-gray-500 sticky top-0 z-10 backdrop-blur-sm">
                        <tr>
                            <th class="p-4 border-r border-gray-100 w-16 min-w-[64px] text-center text-gray-500">№</th>
                            <th class="p-4 border-r border-gray-100 w-[130px] min-w-[130px]">Sana</th>
                            <th class="p-4 border-r border-gray-100 text-center bg-white/5" colspan="2">Qarz / Oylik Yozish</th>
                            <th class="p-4 border-r border-gray-100 text-center" colspan="3">To'landi (To'lovlarimiz)</th>
                            <th class="p-4 w-[130px] min-w-[130px] text-right">Qoldiq</th>
                        </tr>
                        <tr class="border-t border-gray-100 text-[11px] bg-gray-900/50">
                            <th class="border-r border-gray-100 p-2"></th>
                            <th class="border-r border-gray-100 p-2"></th>
                            <th class="border-r border-gray-100 p-2 text-amber-600 w-[250px] min-w-[250px]">Nomi / Izoh</th>
                            <th class="border-r border-gray-100 p-2 text-amber-600 w-[120px] min-w-[120px]">Summa</th>
                            <th class="border-r border-gray-100 p-2 text-emerald-600 w-[120px] min-w-[120px]">Naqd</th>
                            <th class="border-r border-gray-100 p-2 text-emerald-600 w-[120px] min-w-[120px]">Karta</th>
                            <th class="border-r border-gray-100 p-2 text-emerald-600 w-[120px] min-w-[120px]">Boshqa</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-gray-900/90">

                        <tr v-for="(item, idx) in ledgerData.items" :key="idx" class="hover:bg-gray-50 transition-colors group">
                            <td class="p-3 border-r border-gray-100 text-center text-gray-500">{{ idx + 1 }}</td>
                            <td class="p-3 border-r border-gray-100">{{ item.date ? new Date(item.date).toISOString().split('T')[0] : '' }}</td>
                            
                            <!-- Qarz / Oylik yozish (is_work) -->
                            <td class="p-3 border-r border-gray-100 text-[13px] whitespace-normal">{{ item.is_work ? item.desc : '' }}</td>
                            <td class="p-3 border-r border-gray-100 text-right font-bold text-amber-600">{{ item.is_work ? item.amount : '' }}</td>
                            
                            <!-- To'lovlar (is_payment) -->
                            <td class="p-3 border-r border-gray-100 text-right font-bold text-emerald-600">{{ item.is_payment && item.account_type === 'main' ? item.amount : '' }}</td>
                            <td class="p-3 border-r border-gray-100 text-right font-bold text-blue-600">{{ item.is_payment && item.account_type === 'credit' ? item.amount : '' }}</td>
                            <td class="p-3 border-r border-gray-100 text-right font-bold text-gray-500">{{ item.is_payment && !['main', 'credit'].includes(item.account_type) ? item.amount : '' }}</td>
                            
                            <td class="p-3 text-right font-bold" :class="item.running_balance > 0 ? 'text-red-600' : (item.running_balance < 0 ? 'text-emerald-600' : 'text-gray-500')">
                                {{ item.running_balance !== undefined ? (item.running_balance > 0 ? '+' : '') + Number(item.running_balance).toLocaleString() : '' }}
                            </td>
                        </tr>
                        <tr v-if="!ledgerData.items || ledgerData.items.length === 0">
                            <td colspan="8" class="p-8 text-center text-gray-500 italic border-dashed border border-gray-100 rounded-xl m-4">Ma'lumot topilmadi</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="shrink-0 flex justify-between items-center bg-gray-900/50 p-4 rounded-xl border border-gray-200 mb-6 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-neon-cyan/5 to-transparent pointer-events-none"></div>
                <span class="text-sm font-mono text-gray-500 uppercase tracking-widest">Joriy Qoldiq:</span>
                <span class="text-3xl font-display font-bold drop-shadow-md" :class="ledgerData.balance < 0 ? 'text-red-600' : 'text-emerald-600'">
                    {{ Number(ledgerData.balance || 0).toLocaleString() }} so'm
                </span>
            </div>

            <!-- TOP FORM: Qarz / Oylik Yozish -->
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl shrink-0 p-6 border-t-4 border-t-neon-red relative overflow-hidden mb-6">
                <div class="absolute inset-0 bg-red-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-[10px] font-mono text-red-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-600 animate-pulse"></span>🔴 OYLIK/AVANS YOZISH (Qarz)
                </h3>
                <form @submit.prevent="submitAccrual" class="grid grid-cols-1 md:grid-cols-5 gap-4 relative z-10">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-gray-500 mb-1">Sana</label>
                        <input v-model="form.accrual_date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900 font-mono text-sm h-10 border border-gray-200 rounded-lg px-3 [color-scheme:dark] outline-none" required />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-gray-500 mb-1">Oy</label>
                        <input v-model="form.accrual_month" type="month" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900 font-mono text-sm h-10 border border-gray-200 rounded-lg px-3 [color-scheme:dark] outline-none" required />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-gray-500 mb-1">Miqdori</label>
                        <input v-model="form.accrual_amount" type="number" step="100" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900 font-mono text-sm h-10 border border-gray-200 rounded-lg px-3 focus:border-red-500 outline-none" required placeholder="0" />
                    </div>
                    <div class="md:col-span-2 flex gap-4 items-end">
                        <div class="flex-grow">
                            <label class="block text-[10px] uppercase tracking-widest font-mono text-gray-500 mb-1">Izoh (Ixtiyoriy)</label>
                            <input v-model="form.accrual_description" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900 font-mono text-sm h-10 border border-gray-200 rounded-lg px-3 focus:border-red-500 outline-none" placeholder="Qo'shimcha izoh..." />
                        </div>
                        <button type="submit" class="h-10 px-4 bg-red-50 border border-red-500 text-red-600 font-mono text-[10px] uppercase tracking-widest rounded-lg hover:bg-red-600 hover:text-black transition-all font-bold whitespace-nowrap shadow-sm">
                            QO'SHISH
                        </button>
                    </div>
                </form>
            </div>

            <!-- BOTTOM FORM: Kassa Amaliyoti -->
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl shrink-0 p-6 border-t-4 border-t-neon-green relative overflow-hidden">
                <div class="absolute inset-0 bg-emerald-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-[10px] font-mono text-emerald-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>🔵 Kassa amaliyoti (To'lov qilish)
                </h3>
                <form @submit.prevent="submitPayment" class="grid grid-cols-1 md:grid-cols-4 gap-4 relative z-10">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-gray-500 mb-1">Hisob</label>
                        <select v-model="form.account_type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900 font-mono text-sm h-10 border border-gray-200 rounded-lg px-3 focus:border-emerald-500 outline-none" required>
                            <option value="main">Kompaniya Hisobi (Main)</option>
                            <option value="bank">Bank Hisobi (Bank)</option>
                            <option value="credit">Kredit Hisobi (Credit)</option>
                            <option value="partner">Ta'sischi Hisobi (Partner)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-gray-500 mb-1">To'lov Usuli</label>
                        <select v-model="form.payment_method" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900 font-mono text-sm h-10 border border-gray-200 rounded-lg px-3 focus:border-emerald-500 outline-none" required>
                            <option value="cash">Naqd Pul</option>
                            <option value="card">Plastik Karta</option>
                            <option value="bank_transfer">Bank O'tkazmasi</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-gray-500 mb-1">Summa (so'm)</label>
                        <input v-model="form.amount" type="number" step="100" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900 font-mono text-sm h-10 border border-gray-200 rounded-lg px-3 focus:border-emerald-500 outline-none" required placeholder="0" />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-gray-500 mb-1" title="Ixtiyoriy">Chegirma</label>
                        <input v-model="form.discount" type="number" step="100" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900 font-mono text-sm h-10 border border-gray-200 rounded-lg px-3 focus:border-emerald-500 outline-none" placeholder="0" />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest font-mono text-gray-500 mb-1">Sana</label>
                        <input v-model="form.date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900 font-mono text-sm h-10 border border-gray-200 rounded-lg px-3 [color-scheme:dark] outline-none" required />
                    </div>
                    <div class="md:col-span-3 flex gap-4 items-end">
                        <div class="flex-grow">
                            <label class="block text-[10px] uppercase tracking-widest font-mono text-gray-500 mb-1">Izoh <span class="text-red-600">*</span></label>
                            <input v-model="form.description" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900 font-mono text-sm h-10 border border-gray-200 rounded-lg px-3 focus:border-emerald-500 outline-none" required placeholder="Nima uchun to'lov qilinmoqda?" />
                        </div>
                        <button type="submit" class="h-10 px-6 bg-emerald-50 border border-emerald-500 text-emerald-600 font-mono text-[10px] uppercase tracking-widest rounded-lg hover:bg-emerald-600 hover:text-black transition-all font-bold whitespace-nowrap shadow-sm">
                            SAQLASH
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </Teleport>
</template>
