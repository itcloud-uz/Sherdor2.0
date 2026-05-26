<script setup>
import PartnerLayout from '@/Layouts/PartnerLayout.vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    partner: Object,
    requests: Array,
    transactions: Array,
    companyNetWorth: [Number, String]
});

const activeTab = ref(0);
const isProcessing = ref(false);
const showAddModal = ref(false);

const showQRScanner = () => {
    alert('QR Scanner tez orada ishga tushadi');
};

const tabs = [
    { id: 0, name: 'Umumiy Holat', icon: '📊' },
    { id: 1, name: 'Tasdiqlashlar', icon: '🤝' },
    { id: 2, name: 'Tranzaksiyalar va Cheklar', icon: '🧾' },
    { id: 3, name: 'dividend_history', icon: '💰' }
];

const txForm = useForm({
    type: 'expense',
    amount: '',
    category: 'Xarid',
    notes: '',
    attachment: null
});

function submitTransaction() {
    isProcessing.value = true;
    txForm.post(route('partner.transaction.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isProcessing.value = false;
            txForm.reset();
            alert("Tranzaksiya yuborildi va tasdiqlash kutilmoqda!");
        },
        onError: () => isProcessing.value = false
    });
}

const totalInvested = computed(() => Number(props.partner?.total_invested || 0));
const sharePercentage = computed(() => Number(props.partner?.investment_share_percentage || 0));
const expectedDividend = computed(() => (Number(props.companyNetWorth || 0) * (sharePercentage.value / 100)));

// Chart Options for Tab 1 (Income / Expense Area Chart)
const transactionsHistory = computed(() => {
    if (!props.transactions) return [];
    return [...props.transactions].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
});

const areaSeries = computed(() => {
    let incomeData = [];
    let expenseData = [];
    let dates = [];
    
    const grouped = {};
    transactionsHistory.value.forEach(tx => {
        const dateStr = new Date(tx.created_at).toLocaleDateString('uz-UZ');
        if (!grouped[dateStr]) grouped[dateStr] = { income: 0, expense: 0 };
        if (tx.type === 'income') grouped[dateStr].income += Number(tx.amount);
        else grouped[dateStr].expense += Number(tx.amount);
    });

    for (const [date, data] of Object.entries(grouped)) {
        dates.push(date);
        incomeData.push(data.income);
        expenseData.push(data.expense);
    }
    
    if (dates.length === 0) {
        return [
            { name: 'Kirim', data: [0, 0, 0, 0] },
            { name: 'Chiqim', data: [0, 0, 0, 0] }
        ];
    }

    return [
        { name: 'Kirim', data: incomeData },
        { name: 'Chiqim', data: expenseData }
    ];
});

const areaOptions = computed(() => {
    let dates = [];
    if (transactionsHistory.value.length === 0) {
        dates = ['Mon', 'Tue', 'Wed', 'Thu'];
    } else {
        const grouped = {};
        transactionsHistory.value.forEach(tx => {
            grouped[new Date(tx.created_at).toLocaleDateString('uz-UZ')] = true;
        });
        dates = Object.keys(grouped);
    }

    return {
        chart: { type: 'area', background: 'transparent', toolbar: { show: false } },
        colors: ['#39FF14', '#FF003C'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        xaxis: { 
            categories: dates,
            labels: { style: { colors: '#888' } }
        },
        yaxis: { labels: { style: { colors: '#888' } } },
        grid: { borderColor: 'rgba(255,255,255,0.05)' },
        theme: { mode: 'dark' },
        tooltip: { theme: 'dark' }
    };
});

function vote(voteId, action) {
    isProcessing.value = true;
    router.post(route('admin.partner.vote', voteId), { vote: action }, {
        onSuccess: () => isProcessing.value = false,
        onError: () => isProcessing.value = false
    });
}
</script>

<template>
    <Head :title="$t('partner_hub')" />
    
    <PartnerLayout>
        <div class="flex flex-col gap-4 md:gap-6 mb-4 md:mb-8">
            <h1 class="text-xl md:text-3xl font-display font-bold text-white tracking-widest uppercase shadow-neon-magenta/50 text-shadow-glow flex items-center gap-2 md:gap-3">
                <span class="text-3xl md:text-4xl drop-shadow-[0_0_15px_rgba(176,38,255,0.6)]">🤝</span> {{ $t('partner_hub') }}
            </h1>
        </div>

        <!-- UNIVERSAL BOTTOM NAVIGATION (Curved App Style Dock) -->
        <div class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full md:w-[600px] z-50 h-20 bg-transparent drop-shadow-[0_-10px_20px_rgba(0,0,0,0.5)]">
            <!-- Curved SVG Background -->
            <svg class="absolute bottom-0 w-full h-[70px]" viewBox="0 0 375 70" preserveAspectRatio="none" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 20C0 8.95431 8.95431 0 20 0H132.5C141.5 0 149.5 5.5 152.5 14C158.5 31 171.5 40 187.5 40C203.5 40 216.5 31 222.5 14C225.5 5.5 233.5 0 242.5 0H355C366.046 0 375 8.95431 375 20V70H0V20Z" fill="#111111" stroke="rgba(255,255,255,0.1)" stroke-width="1"/>
            </svg>

            <!-- Nav Items -->
            <div class="absolute bottom-0 w-full h-[70px] flex justify-between items-center px-4 pb-2">
                
                <!-- Left Side -->
                <div class="flex gap-3 sm:gap-6 pl-2">
                    <button @click="activeTab = 0" class="flex flex-col items-center justify-center transition-all" :class="activeTab === 0 ? 'text-neon-cyan drop-shadow-[0_0_10px_rgba(0,240,255,0.8)]' : 'text-text-muted hover:text-white'">
                        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span class="text-[8px] font-mono uppercase tracking-wider">Asosiy</span>
                    </button>
                    
                    <button @click="activeTab = 1" class="flex flex-col items-center justify-center transition-all" :class="activeTab === 1 ? 'text-neon-red drop-shadow-[0_0_10px_rgba(255,0,0,0.8)]' : 'text-text-muted hover:text-white'">
                        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <span class="text-[8px] font-mono uppercase tracking-wider">Tasdiq</span>
                    </button>
                    
                    <button @click="showQRScanner" class="flex flex-col items-center justify-center transition-all text-text-muted hover:text-white">
                        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        <span class="text-[8px] font-mono uppercase tracking-wider">Skaner</span>
                    </button>
                </div>

                <!-- Center FAB -->
                <button @click="showAddModal = true" class="absolute left-1/2 -top-6 -translate-x-1/2 w-16 h-16 rounded-full bg-gradient-to-tr from-neon-magenta to-neon-red text-white flex items-center justify-center shadow-[0_10px_25px_rgba(255,0,60,0.6)] border-4 border-[#111111] hover:scale-105 transition-transform">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </button>

                <!-- Right Side -->
                <div class="flex gap-3 sm:gap-6 pr-2">
                    <button @click="activeTab = 2" class="flex flex-col items-center justify-center transition-all" :class="activeTab === 2 ? 'text-neon-yellow drop-shadow-[0_0_10px_rgba(252,238,9,0.8)]' : 'text-text-muted hover:text-white'">
                        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span class="text-[8px] font-mono uppercase tracking-wider">Tarix</span>
                    </button>
                    
                    <button @click="activeTab = 3" class="flex flex-col items-center justify-center transition-all" :class="activeTab === 3 ? 'text-neon-green drop-shadow-[0_0_10px_rgba(57,255,20,0.8)]' : 'text-text-muted hover:text-white'">
                        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                        <span class="text-[8px] font-mono uppercase tracking-wider">Foyda</span>
                    </button>

                    <button @click="activeTab = 4" class="flex flex-col items-center justify-center transition-all" :class="activeTab === 4 ? 'text-neon-cyan drop-shadow-[0_0_10px_rgba(0,240,255,0.8)]' : 'text-text-muted hover:text-white'">
                        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span class="text-[8px] font-mono uppercase tracking-wider">{{ $t('receipts') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- TAB 1: Executive Overview -->
        <div v-if="activeTab === 0" class="space-y-6 animate-[fadeIn_0.5s_ease-out] pb-24 md:pb-0">
            <!-- Full width chart: Income / Expenses -->
            <div class="glass-panel p-6 rounded-2xl border border-white/5 w-full">
                <h3 class="text-sm font-mono text-text-muted uppercase tracking-widest mb-2">Kirim va Chiqimlar</h3>
                <VueApexCharts type="area" height="250" :options="areaOptions" :series="areaSeries" />
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 gap-4 md:gap-6">
                <!-- 1. Total Investment -->
                <div class="glass-card p-6 border-l-4 border-l-neon-magenta relative overflow-hidden group flex flex-col justify-center">
                    <div class="absolute inset-0 bg-neon-magenta/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-xs font-mono text-text-muted uppercase tracking-widest">{{ $t('my_total_investment') }}</p>
                    <h2 class="text-2xl md:text-3xl font-display font-bold text-white mt-2 drop-shadow-[0_0_15px_rgba(176,38,255,0.4)]">
                        {{ $currency(totalInvested) }}
                    </h2>
                </div>

                <!-- 2. Company Net Worth -->
                <div class="glass-card p-6 border-l-4 border-l-neon-cyan relative overflow-hidden group flex flex-col justify-center">
                    <div class="absolute inset-0 bg-neon-cyan/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-xs font-mono text-text-muted uppercase tracking-widest">{{ $t('company_net_worth') }}</p>
                    <h2 class="text-2xl md:text-3xl font-display font-bold text-white mt-2 drop-shadow-[0_0_10px_rgba(0,240,255,0.3)] truncate">
                        {{ $currency(companyNetWorth) }}
                    </h2>
                </div>

                <!-- 3. Expected Dividend -->
                <div class="glass-card p-6 border-l-4 border-l-neon-green relative overflow-hidden group flex flex-col justify-center">
                    <div class="absolute inset-0 bg-neon-green/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-xs font-mono text-text-muted uppercase tracking-widest">{{ $t('expected_dividend') }}</p>
                    <h2 class="text-2xl md:text-3xl font-display font-bold text-white mt-2 drop-shadow-[0_0_10px_rgba(57,255,20,0.3)] truncate">
                        {{ $currency(expectedDividend) }}
                    </h2>
                </div>

                <!-- 4. Ownership Share -->
                <div class="glass-card p-6 border-l-4 border-l-[#B026FF] relative overflow-hidden group flex flex-col justify-center">
                    <div class="absolute inset-0 bg-[#B026FF]/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-xs font-mono text-text-muted uppercase tracking-widest">{{ $t('ownership_share') }}</p>
                    <h2 class="text-2xl md:text-3xl font-display font-bold text-white mt-2 drop-shadow-[0_0_10px_rgba(176,38,255,0.3)]">
                        {{ sharePercentage }}%
                    </h2>
                </div>
            </div>
        </div>

        <!-- TAB 2: Consensus Action Center -->
        <div v-if="activeTab === 1" class="space-y-6 animate-[fadeIn_0.5s_ease-out] pb-24 md:pb-0">
            <div class="glass-panel p-6 rounded-2xl border border-neon-red/30 shadow-[0_0_30px_rgba(255,0,0,0.1)]">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-display font-bold text-white flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-neon-red animate-pulse shadow-[0_0_10px_#FF0000]"></span>
                        {{ $t('multi_sig_queue') }}
                    </h2>
                    <p class="text-xs font-mono text-text-muted">{{ $t('high_value_warning') }}</p>
                </div>

                <div class="space-y-4">
                    <div v-for="req in requests" :key="req.id" class="glass-card p-6 border border-white/10 rounded-xl relative overflow-hidden">
                        
                        <!-- Glow effect -->
                        <div class="absolute -right-20 -top-20 w-64 h-64 bg-neon-red/5 rounded-full blur-[80px]"></div>

                        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 relative z-10">
                            
                            <!-- Expense Info -->
                            <div class="flex-1">
                                <span class="px-2 py-1 bg-neon-red/20 text-neon-red text-[10px] font-mono tracking-widest uppercase rounded mb-3 inline-block">
                                    {{ $t('high_value_expense') }}
                                </span>
                                <h3 class="text-3xl font-display font-bold text-white mb-2">
                                    {{ $currency(req.transaction?.amount) }}
                                </h3>
                                <p class="text-sm font-mono text-text-muted">
                                    <span class="text-white">{{ $t('maker') }}:</span> {{ req.transaction?.creator?.name }}
                                    <span class="mx-2">|</span>
                                    <span class="text-white">{{ $t('date') }}:</span> {{ new Date(req.created_at).toLocaleDateString() }}
                                </p>
                                <p v-if="req.transaction?.notes" class="text-xs font-mono text-gray-400 mt-2 bg-black/30 p-2 rounded border border-white/5 inline-block">
                                    "{{ req.transaction.notes }}"
                                </p>
                            </div>

                            <!-- Partner Votes Telemetry -->
                            <div class="flex-1 bg-black/40 p-4 rounded-xl border border-white/5">
                                <h4 class="text-[10px] font-mono text-text-muted tracking-widest uppercase mb-3">{{ $t('partner_consensus') }}</h4>
                                <div class="space-y-2">
                                    <div v-for="v in req.votes" :key="v.id" class="flex justify-between items-center text-xs font-mono">
                                        <span class="text-white">{{ v.partner?.user?.name }}</span>
                                        <span v-if="v.vote === 'pending'" class="text-neon-yellow animate-pulse">⏳ {{ $t('pending') }}</span>
                                        <span v-else-if="v.vote === 'approved'" class="text-neon-green shadow-neon-green">✔️ {{ $t('approved') }}</span>
                                        <span v-else class="text-neon-red">❌ {{ $t('rejected') }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-col gap-3 min-w-[200px]" v-if="req.votes.find(v => v.partner_id === partner?.id)?.vote === 'pending'">
                                <button @click="vote(req.votes.find(v => v.partner_id === partner?.id)?.id, 'approved')" :disabled="isProcessing" class="px-6 py-4 text-sm font-mono font-bold uppercase tracking-widest bg-neon-cyan/10 border border-neon-cyan text-neon-cyan hover:bg-neon-cyan hover:text-black hover:shadow-[0_0_25px_#00F0FF] transition-all rounded-xl disabled:opacity-50 flex items-center justify-center gap-2">
                                    ⚡ {{ $t('approve_expense') }}
                                </button>
                                <button @click="vote(req.votes.find(v => v.partner_id === partner?.id)?.id, 'rejected')" :disabled="isProcessing" class="px-6 py-4 text-sm font-mono font-bold uppercase tracking-widest bg-neon-red/10 border border-neon-red text-neon-red hover:bg-neon-red hover:text-white hover:shadow-[0_0_25px_#FF0000] transition-all rounded-xl disabled:opacity-50 flex items-center justify-center gap-2">
                                    🛑 {{ $t('reject_expense') }}
                                </button>
                            </div>
                            <div class="flex flex-col gap-3 min-w-[200px]" v-else>
                                <div class="px-6 py-4 text-sm font-mono text-center tracking-widest bg-white/5 border border-white/10 text-white rounded-xl">
                                    {{ $t('vote_cast') }}
                                </div>
                            </div>

                        </div>
                    </div>

                    <div v-if="requests.length === 0" class="py-16 text-center text-text-muted font-mono text-sm border-dashed border border-white/10 rounded-xl">
                        {{ $t('no_pending_consensus') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- ADD TRANSACTION MODAL -->
        <div v-if="showAddModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div @click="showAddModal = false" class="absolute inset-0 bg-gray-500/50 "></div>
            
            <!-- Modal Content -->
            <div class="glass-panel p-6 rounded-2xl border border-white/10 w-full max-w-md relative z-10 animate-[fadeIn_0.3s_ease-out]">
                <button @click="showAddModal = false" class="absolute top-4 right-4 text-white/50 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <h3 class="text-xl font-display font-bold text-white mb-6">Yangi Tranzaksiya / Chek</h3>
                <form @submit.prevent="submitTransaction(); showAddModal = false;" class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono text-text-muted uppercase mb-1">{{ $t('type_label') }}</label>
                        <select v-model="txForm.type" class="w-full bg-gray-500/50 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-magenta focus:ring-1 focus:ring-neon-magenta transition-all">
                            <option value="expense">Chiqim (Xarajat)</option>
                            <option value="income">Kirim (Tushum)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-text-muted uppercase mb-1">Summa (UZS)</label>
                        <input type="number" v-model="txForm.amount" required class="w-full bg-gray-500/50 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-magenta focus:ring-1 focus:ring-neon-magenta transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-text-muted uppercase mb-1">Kategoriya</label>
                        <input type="text" v-model="txForm.category" required class="w-full bg-gray-500/50 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-magenta focus:ring-1 focus:ring-neon-magenta transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-text-muted uppercase mb-1">Izoh / Maqsad</label>
                        <textarea v-model="txForm.notes" rows="2" class="w-full bg-gray-500/50 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-magenta focus:ring-1 focus:ring-neon-magenta transition-all"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-text-muted uppercase mb-1">Chek nusxasi (Fayl)</label>
                        <input type="file" @change="e => txForm.attachment = e.target.files[0]" class="w-full bg-gray-500/50 border border-white/10 rounded-xl p-3 text-white font-mono text-xs text-text-muted file:bg-white/10 file:border-0 file:rounded file:text-white file:px-3 file:py-1 file:mr-3 hover:file:bg-white/20">
                    </div>
                    
                    <button type="submit" :disabled="isProcessing" class="w-full mt-6 bg-gradient-to-r from-neon-magenta to-neon-red text-white py-4 rounded-xl font-display font-bold tracking-widest uppercase hover:shadow-[0_0_20px_rgba(255,0,60,0.5)] transition-all">
                        Tasdiqqa Yuborish
                    </button>
                </form>
            </div>
        </div>

        <!-- TAB 3: Transactions & Receipts -->
        <div v-if="activeTab === 2" class="space-y-6 animate-[fadeIn_0.5s_ease-out] pb-24 md:pb-0">
            <div class="grid grid-cols-1 gap-6">
                <!-- Transaction List -->
                <div class="glass-panel p-6 rounded-2xl border border-white/5 flex flex-col h-full max-h-[600px]">
                    <h3 class="text-lg font-display text-white mb-4">So'nggi Tranzaksiyalar</h3>
                    
                    <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-3">
                        <div v-for="tx in transactions" :key="'tx-'+tx.id" class="glass-card p-4 rounded-xl border border-white/5 flex justify-between items-center group hover:border-white/20 transition-all">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-500/50 border border-white/10 shrink-0"
                                     :class="tx.type === 'income' ? 'text-neon-green shadow-[0_0_10px_rgba(57,255,20,0.2)]' : 'text-neon-red shadow-[0_0_10px_rgba(255,0,60,0.2)]'">
                                    {{ tx.type === 'income' ? '↓' : '↑' }}
                                </div>
                                <div class="overflow-hidden">
                                    <p class="text-white font-bold text-sm truncate">{{ tx.category }}</p>
                                    <p class="text-[10px] sm:text-xs font-mono text-text-muted truncate">{{ new Date(tx.created_at).toLocaleString() }} • Kiritdi: {{ tx.creator?.name || 'Tizim' }}</p>
                                    <p v-if="tx.notes" class="text-[10px] font-mono text-gray-500 mt-1 truncate">{{ tx.notes }}</p>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm sm:text-lg font-display font-bold" :class="tx.type === 'income' ? 'text-neon-green' : 'text-neon-red'">
                                    {{ tx.type === 'income' ? '+' : '-' }}{{ $currency(tx.amount) }}
                                </p>
                                <span class="text-[9px] font-mono px-2 py-0.5 rounded uppercase" 
                                      :class="tx.status === 'approved' ? 'bg-neon-green/20 text-neon-green' : (tx.status === 'pending' ? 'bg-neon-yellow/20 text-neon-yellow' : 'bg-neon-red/20 text-neon-red')">
                                    {{ tx.status }}
                                </span>
                            </div>
                        </div>
                        
                        <div v-if="!transactions || transactions.length === 0" class="text-center py-10 text-text-muted font-mono text-sm border-dashed border border-white/10 rounded-xl">
                            Tranzaksiyalar mavjud emas
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4: Dividend History -->
        <div v-if="activeTab === 3" class="glass-panel p-6 rounded-2xl border border-white/5 animate-[fadeIn_0.5s_ease-out] pb-24 md:pb-0">
            <h2 class="text-xl font-display font-bold text-white mb-6">{{ $t('dividend_history') }}</h2>
            <div class="py-16 text-center text-text-muted font-mono text-sm border-dashed border border-white/10 rounded-xl">
                {{ $t('no_dividends_yet') }}
            </div>
        </div>

        <!-- TAB 5: Receipts (Cheklar) -->
        <div v-if="activeTab === 4" class="space-y-6 animate-[fadeIn_0.5s_ease-out] pb-24 md:pb-0">
            <div class="grid grid-cols-1 gap-6">
                <!-- Receipts List -->
                <div class="glass-panel p-6 rounded-2xl border border-white/5 flex flex-col h-full max-h-[600px]">
                    <h3 class="text-lg font-display text-white mb-4">Yuklangan Cheklar (Shaffoflik)</h3>
                    
                    <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-3">
                        <div v-for="tx in transactions.filter(t => t.attachment_path)" :key="'rec-'+tx.id" class="glass-card p-4 rounded-xl border border-white/5 flex flex-col md:flex-row md:items-center md:justify-between gap-4 group hover:border-white/20 transition-all">
                            <div class="flex justify-between items-start md:items-center md:gap-8 flex-1">
                                <div>
                                    <p class="text-white font-bold text-sm">{{ tx.category }}</p>
                                    <p class="text-xs font-mono text-text-muted">{{ new Date(tx.created_at).toLocaleString() }}</p>
                                </div>
                                <p class="text-sm font-display font-bold text-white">{{ $currency(tx.amount) }}</p>
                            </div>
                            
                            <a :href="'/storage/' + tx.attachment_path" target="_blank" class="flex items-center justify-center gap-2 px-4 py-2 bg-white/5 hover:bg-white/10 rounded-lg text-xs font-mono text-neon-cyan border border-neon-cyan/20 transition-all shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Chekni ko'rish
                            </a>
                        </div>
                        
                        <div v-if="!transactions || transactions.filter(t => t.attachment_path).length === 0" class="text-center py-10 text-text-muted font-mono text-sm border-dashed border border-white/10 rounded-xl">
                            Cheklar mavjud emas
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </PartnerLayout>
</template>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
