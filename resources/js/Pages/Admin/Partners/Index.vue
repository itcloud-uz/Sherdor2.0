<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    partners: Array,
    users: Array,
    companyNetWorth: Number,
    netProfit: Number,
    fixedAssets: Array
});

const activeTab = ref('partners'); // 'partners' or 'assets'

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab')) {
        activeTab.value = urlParams.get('tab');
    }
});

watch(() => window.location.search, (search) => {
    const urlParams = new URLSearchParams(search);
    if (urlParams.get('tab')) {
        activeTab.value = urlParams.get('tab');
    }
});

const isProcessing = ref(false);

// Add Partner Modal
const showAddPartnerModal = ref(false);
const partnerForm = useForm({
    user_id: '',
    investment_share_percentage: '',
    total_invested: ''
});

function submitPartner() {
    isProcessing.value = true;
    partnerForm.post(route('admin.partners.store'), {
        onSuccess: () => {
            showAddPartnerModal.value = false;
            partnerForm.reset();
            isProcessing.value = false;
        },
        onError: () => isProcessing.value = false
    });
}

// Edit Partner Modal
const showEditPartnerModal = ref(false);
const editPartnerForm = useForm({
    id: null,
    investment_share_percentage: '',
    total_invested: '',
    accountable_balance: ''
});

function openEditPartnerModal(partner) {
    editPartnerForm.id = partner.id;
    editPartnerForm.investment_share_percentage = partner.investment_share_percentage;
    editPartnerForm.total_invested = partner.total_invested;
    editPartnerForm.accountable_balance = partner.accountable_balance;
    showEditPartnerModal.value = true;
}

function submitEditPartner() {
    if (!editPartnerForm.id) return;
    isProcessing.value = true;
    editPartnerForm.put(route('admin.partners.update', editPartnerForm.id), {
        onSuccess: () => {
            showEditPartnerModal.value = false;
            editPartnerForm.reset();
            isProcessing.value = false;
        },
        onError: () => isProcessing.value = false
    });
}

function deletePartner(partnerId) {
    if (confirm("Ushbu ta'sischini o'chirishga ishonchingiz komilmi? Barcha bog'liq ma'lumotlar saqlab qolinadi yoki xatolik berishi mumkin.")) {
        isProcessing.value = true;
        router.delete(route('admin.partners.destroy', partnerId), {
            onFinish: () => isProcessing.value = false
        });
    }
}

// Advance Modal
const showAdvanceModal = ref(false);
const selectedPartner = ref(null);
const advanceForm = useForm({
    amount: '',
    notes: ''
});

function openAdvanceModal(partner) {
    selectedPartner.value = partner;
    advanceForm.reset();
    showAdvanceModal.value = true;
}

function submitAdvance() {
    isProcessing.value = true;
    advanceForm.post(route('admin.partners.advance', selectedPartner.value.id), {
        onSuccess: () => {
            showAdvanceModal.value = false;
            isProcessing.value = false;
        },
        onError: () => isProcessing.value = false
    });
}

// Expense Modal
const showExpenseModal = ref(false);
const expenseForm = useForm({
    amount: '',
    category: 'Boshqa xarajatlar',
    notes: '',
    attachment: null
});

function openExpenseModal(partner) {
    selectedPartner.value = partner;
    expenseForm.reset();
    expenseForm.category = 'Boshqa xarajatlar';
    showExpenseModal.value = true;
}

function handleFileUpload(event) {
    expenseForm.attachment = event.target.files[0];
}

function submitExpense() {
    isProcessing.value = true;
    // We use forceFormData to support file uploads in Inertia
    expenseForm.post(route('admin.partners.expense', selectedPartner.value.id), {
        forceFormData: true,
        onSuccess: () => {
            showExpenseModal.value = false;
            isProcessing.value = false;
        },
        onError: () => isProcessing.value = false
    });
}

// Analytics Modal
const showAnalyticsModal = ref(false);
const transactions = ref([]);
const analyticsLoading = ref(false);

const ledgerDateFrom = ref('');
const ledgerDateTo = ref('');

async function fetchLedger() {
    if (!selectedPartner.value) return;
    analyticsLoading.value = true;
    try {
        const response = await axios.get(route('admin.partners.ledger', selectedPartner.value.id), {
            params: {
                from_date: ledgerDateFrom.value,
                to_date: ledgerDateTo.value
            }
        });
        transactions.value = response.data;
    } catch (e) {
        console.error("Ledger fetch failed", e);
    } finally {
        analyticsLoading.value = false;
    }
}

async function openAnalytics(partner) {
    selectedPartner.value = partner;
    showAnalyticsModal.value = true;
    ledgerDateFrom.value = '';
    ledgerDateTo.value = '';
    await fetchLedger();
}

function exportLedgerCSV() {
    if (transactions.value.length === 0) return;
    const headers = ['Sana', 'Tur', 'Kategoriya', 'Summa', 'Izoh'];
    const rows = transactions.value.map(t => {
        const type = t.type === 'income' ? 'Kirim' : 'Chiqim';
        return [
            formatDate(t.created_at),
            type,
            t.category || '-',
            t.amount,
            `"${(t.notes || '-').replace(/"/g, '""')}"`
        ].join(',');
    });
    
    const csvContent = "data:text/csv;charset=utf-8,\uFEFF" + [headers.join(','), ...rows].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `Tasischi_Hisoboti_${selectedPartner.value?.user?.name}_${new Date().getTime()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Asset Management
const showAssetModal = ref(false);
const isEditingAsset = ref(false);
const currentAssetId = ref(null);
const assetForm = useForm({
    name: '',
    type: 'construction_equipment',
    purchase_date: '',
    purchase_cost: '',
    service_life_months: 60,
    status: 'active',
    description: '',
    partner_id: ''
});

function openAddAssetModal() {
    isEditingAsset.value = false;
    currentAssetId.value = null;
    assetForm.reset();
    assetForm.purchase_date = new Date().toISOString().split('T')[0];
    showAssetModal.value = true;
}

function openEditAssetModal(asset) {
    isEditingAsset.value = true;
    currentAssetId.value = asset.id;
    assetForm.name = asset.name;
    assetForm.type = asset.type;
    assetForm.purchase_date = asset.purchase_date.substring(0, 10);
    assetForm.purchase_cost = asset.purchase_cost;
    assetForm.service_life_months = asset.service_life_months;
    assetForm.status = asset.status;
    assetForm.description = asset.description;
    assetForm.partner_id = asset.partner_id || '';
    showAssetModal.value = true;
}

function submitAsset() {
    isProcessing.value = true;
    if (isEditingAsset.value) {
        assetForm.put(route('admin.partners.assets.update', currentAssetId.value), {
            onSuccess: () => {
                showAssetModal.value = false;
                isProcessing.value = false;
            },
            onError: () => isProcessing.value = false
        });
    } else {
        assetForm.post(route('admin.partners.assets.store'), {
            onSuccess: () => {
                showAssetModal.value = false;
                isProcessing.value = false;
            },
            onError: () => isProcessing.value = false
        });
    }
}

function deleteAsset(id) {
    if (confirm("Ushbu assosiy vositani o'chirishga ishonchingiz komilmi?")) {
        router.delete(route('admin.partners.assets.destroy', id));
    }
}

function calculateServiceMonths(purchaseDate) {
    const start = new Date(purchaseDate);
    const now = new Date();
    return (now.getFullYear() - start.getFullYear()) * 12 + (now.getMonth() - start.getMonth());
}

// Charts Data Computations
const pieChartOptions = computed(() => {
    return {
        chart: { type: 'donut', background: 'transparent' },
        theme: { mode: 'dark' },
        labels: categoryStats.value.map(c => c.name),
        colors: ['#00f0ff', '#39ff14', '#ff003c', '#fce205', '#bf00ff'],
        stroke: { show: true, colors: '#111', width: 2 },
        dataLabels: { enabled: false },
        tooltip: { theme: 'dark' },
        legend: { position: 'bottom', labels: { colors: '#fff' } }
    };
});

const pieChartSeries = computed(() => {
    return categoryStats.value.map(c => c.value);
});

const categoryStats = computed(() => {
    const expenses = transactions.value.filter(t => t.notes && t.notes.includes('xarajati'));
    const map = {};
    expenses.forEach(t => {
        const cat = t.category || 'Boshqa';
        map[cat] = (map[cat] || 0) + Number(t.amount);
    });
    return Object.keys(map).map(k => ({ name: k, value: map[k] }));
});

const barChartOptions = computed(() => {
    return {
        chart: { type: 'bar', background: 'transparent', toolbar: { show: false } },
        theme: { mode: 'dark' },
        colors: ['#00f0ff', '#ff003c'],
        plotOptions: { bar: { horizontal: false, columnWidth: '55%', borderRadius: 4 } },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: { categories: Object.keys(monthlyStats.value), labels: { style: { colors: '#888' } } },
        yaxis: { labels: { style: { colors: '#888' }, formatter: val => formatCurrency(val) } },
        tooltip: { theme: 'dark' }
    };
});

const barChartSeries = computed(() => {
    const keys = Object.keys(monthlyStats.value);
    const advanceData = keys.map(k => monthlyStats.value[k].advances);
    const expenseData = keys.map(k => monthlyStats.value[k].expenses);
    return [
        { name: 'Olingan Avanslar', data: advanceData },
        { name: 'Qilingan Xarajatlar', data: expenseData }
    ];
});

const monthlyStats = computed(() => {
    const map = {};
    for (let i=5; i>=0; i--) {
        const d = new Date();
        d.setMonth(d.getMonth() - i);
        map[`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`] = { advances: 0, expenses: 0 };
    }
    
    transactions.value.forEach(t => {
        const dateStr = t.created_at.substring(0, 7);
        if (!map[dateStr]) map[dateStr] = { advances: 0, expenses: 0 };
        
        if (t.notes && t.notes.includes('Avans')) {
            map[dateStr].advances += Number(t.amount);
        } else {
            map[dateStr].expenses += Number(t.amount);
        }
    });
    return map;
});


function formatCurrency(val) {
    return new Intl.NumberFormat('uz-UZ').format(val || 0);
}

function formatDate(dateString) {
    if (!dateString) return '';
    const d = new Date(dateString);
    return `${d.getDate().toString().padStart(2,'0')}.${(d.getMonth()+1).toString().padStart(2,'0')}.${d.getFullYear()} ${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`;
}
</script>

<template>
    <Head title="Partner Hub" />
    <AdminLayout>
        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 md:p-8 rounded-2xl animate-[fadeIn_0.5s_ease-out]">
            <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
                <div>
                    <h2 class="text-2xl font-display text-gray-900 font-bold uppercase tracking-widest">Ta'sischilar Paneli</h2>
                    <p class="text-[10px] font-mono text-gray-500 mt-1">Sarmoyadorlarni va kompaniya aktivlarini boshqarish</p>
                </div>
                <div class="flex gap-4">
                    <button v-if="activeTab === 'partners'" @click="showAddPartnerModal = true" class="px-4 py-2 bg-blue-50 border border-blue-200 text-blue-600 text-xs font-mono font-bold rounded hover:bg-blue-600 hover:text-black transition-all shadow-sm">
                        + TA'SISCHI QO'SHISH
                    </button>
                    <button v-if="activeTab === 'assets'" @click="openAddAssetModal" class="px-4 py-2 bg-emerald-50 border border-emerald-200 text-emerald-600 text-xs font-mono font-bold rounded hover:bg-emerald-600 hover:text-black transition-all shadow-sm">
                        + ASSOSIY VOSITA QO'SHISH
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-4 mb-8 border-b border-gray-100 pb-px">
                <button 
                    @click="activeTab = 'partners'"
                    class="pb-4 px-2 text-xs font-mono font-bold tracking-widest transition-all relative"
                    :class="activeTab === 'partners' ? 'text-blue-600' : 'text-gray-500 hover:text-gray-900'"
                >
                    TA'SISCHILAR
                    <div v-if="activeTab === 'partners'" class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 shadow-sm"></div>
                </button>
                <button 
                    @click="activeTab = 'assets'"
                    class="pb-4 px-2 text-xs font-mono font-bold tracking-widest transition-all relative"
                    :class="activeTab === 'assets' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-900'"
                >
                    ASSOSIY VOSITALAR
                    <div v-if="activeTab === 'assets'" class="absolute bottom-0 left-0 w-full h-0.5 bg-emerald-600 shadow-sm"></div>
                </button>
            </div>

            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="relative overflow-hidden bg-white/60 backdrop-blur-xl border border-gray-100 shadow-sm rounded-2xl p-6 hover:shadow-md transition-shadow duration-300">
                    <div class="absolute -right-6 -top-6 w-32 h-32 bg-blue-100/50 rounded-full blur-2xl"></div>
                    <div class="relative z-10">
                        <p class="text-[10px] font-medium text-slate-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                            Kompaniyaning jami qiymati
                        </p>
                        <p class="text-4xl font-display font-bold text-slate-800 tracking-tight">{{ formatCurrency(companyNetWorth) }} <span class="text-sm font-medium text-slate-400">UZS</span></p>
                    </div>
                </div>
                
                <div class="relative overflow-hidden bg-emerald-50/30 backdrop-blur-xl border border-emerald-100 shadow-sm rounded-2xl p-6 hover:shadow-md transition-shadow duration-300">
                    <div class="absolute -right-6 -top-6 w-32 h-32 bg-emerald-200/40 rounded-full blur-2xl"></div>
                    <div class="relative z-10">
                        <p class="text-[10px] font-medium text-emerald-600/80 uppercase tracking-widest mb-2 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Sof Foyda (Net Profit)
                        </p>
                        <p class="text-4xl font-display font-bold text-emerald-700 tracking-tight">{{ formatCurrency(netProfit) }} <span class="text-sm font-medium text-emerald-500/70">UZS</span></p>
                    </div>
                </div>
                
                <div class="relative overflow-hidden bg-rose-50/30 backdrop-blur-xl border border-rose-100 shadow-sm rounded-2xl p-6 hover:shadow-md transition-shadow duration-300">
                    <div class="absolute -right-6 -top-6 w-32 h-32 bg-rose-200/40 rounded-full blur-2xl"></div>
                    <div class="relative z-10">
                        <p class="text-[10px] font-medium text-rose-600/80 uppercase tracking-widest mb-2 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                            Jami Hisobdorlik (Qarz)
                        </p>
                        <p class="text-4xl font-display font-bold text-rose-700 tracking-tight">{{ formatCurrency(partners.reduce((sum, p) => sum + Number(p.accountable_balance), 0)) }} <span class="text-sm font-medium text-rose-500/70">UZS</span></p>
                    </div>
                </div>
            </div>

            <!-- Partners View -->
            <div v-if="activeTab === 'partners'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 animate-[fadeIn_0.4s_ease-out]">
                <div v-for="partner in partners" :key="partner.id" class="relative overflow-hidden bg-white border border-slate-200/60 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] hover:-translate-y-1 transition-all duration-300 group rounded-2xl p-6">
                    <div class="absolute top-0 right-0 w-40 h-40 bg-gradient-to-br from-blue-50/50 to-transparent rounded-bl-full -z-10 group-hover:scale-110 transition-transform duration-500"></div>
                    
                    <div class="absolute top-4 right-4 text-right flex flex-col items-end gap-2 z-10">
                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity translate-x-2 group-hover:translate-x-0 duration-300">
                            <button @click="openEditPartnerModal(partner)" class="p-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition-colors" title="Tahrirlash">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </button>
                            <button @click="deletePartner(partner.id)" class="p-1.5 bg-rose-50 text-rose-600 rounded-lg hover:bg-rose-600 hover:text-white transition-colors" title="O'chirish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                        <div class="mt-2">
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Ulush</p>
                            <p class="text-2xl font-display font-black text-blue-600 tracking-tight">{{ Number(partner.investment_share_percentage) }}%</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 mb-6 z-10 relative">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold font-display text-xl shadow-lg shadow-blue-500/20 group-hover:shadow-blue-500/40 transition-shadow">
                            {{ partner.user?.name.charAt(0) || 'P' }}
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-800 leading-tight mb-1">{{ partner.user?.name || $t('unknown') }}</h3>
                            <p class="text-xs text-slate-500">{{ partner.user?.email }}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5 pt-5 border-t border-slate-100">
                        <div>
                            <span class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Jami Sarmoya</span>
                            <span class="text-[15px] font-medium text-slate-800">{{ formatCurrency(partner.total_invested) }}</span>
                        </div>
                        <div class="text-right">
                            <span class="block text-[10px] font-semibold text-rose-400 uppercase tracking-wider mb-1">Bo'ynidagi Qarz</span>
                            <span class="text-[15px] font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-md">{{ formatCurrency(partner.accountable_balance) }}</span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2 mb-5">
                        <div class="p-3 bg-slate-50/50 border border-slate-100 rounded-xl text-center cursor-pointer hover:bg-white hover:shadow-sm hover:border-blue-200 transition-all group/stat" @click="openAnalytics(partner)">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1">Kassa Tushumi</span>
                            <span class="text-[13px] font-bold text-slate-700 group-hover/stat:text-blue-600 transition-colors">{{ formatCurrency(partner.kassa_tushumi) }}</span>
                        </div>
                        <div class="p-3 bg-slate-50/50 border border-slate-100 rounded-xl text-center cursor-pointer hover:bg-white hover:shadow-sm hover:border-rose-200 transition-all group/stat" @click="openAnalytics(partner)">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1">Kassa Chiqimi</span>
                            <span class="text-[13px] font-bold text-slate-700 group-hover/stat:text-rose-600 transition-colors">{{ formatCurrency(partner.kassa_chiqimi) }}</span>
                        </div>
                        <div class="p-3 bg-slate-50/50 border border-slate-100 rounded-xl text-center cursor-pointer hover:bg-white hover:shadow-sm transition-all">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1">Balans</span>
                            <span class="text-[13px] font-bold text-slate-800">{{ formatCurrency(partner.accountable_balance) }}</span>
                        </div>
                    </div>
                    
                    <div class="mb-6 p-4 bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-100/50 rounded-xl text-center">
                        <span class="block text-[10px] font-bold text-emerald-600/70 uppercase tracking-wider mb-1.5">Soffoydadan Olinishi Mumkin (Taxminiy)</span>
                        <span class="text-xl font-display font-black text-emerald-600">{{ formatCurrency((netProfit * Number(partner.investment_share_percentage)) / 100) }} UZS</span>
                    </div>

                    <div class="flex gap-3">
                        <button @click="openAdvanceModal(partner)" class="flex-1 py-2.5 bg-blue-600 text-white rounded-lg text-[11px] font-bold uppercase tracking-wider hover:bg-blue-700 hover:shadow-md hover:shadow-blue-500/20 transition-all active:scale-95">
                            Pul So'rash
                        </button>
                        <button @click="openExpenseModal(partner)" class="flex-1 py-2.5 bg-emerald-500 text-white rounded-lg text-[11px] font-bold uppercase tracking-wider hover:bg-emerald-600 hover:shadow-md hover:shadow-emerald-500/20 transition-all active:scale-95">
                            Chek Yuklash
                        </button>
                        <button @click="openAnalytics(partner)" class="flex-1 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-lg text-[11px] font-bold uppercase tracking-wider hover:bg-slate-50 hover:border-slate-300 transition-all active:scale-95">
                            Hisobot
                        </button>
                    </div>
                </div>

                <div v-if="partners.length === 0" class="col-span-full flex flex-col items-center justify-center py-20 border-2 border-dashed border-slate-200 bg-slate-50/50 rounded-3xl">
                    <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-sm mb-4">
                        <span class="text-2xl">🤝</span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-1">Ta'sischilar yo'q</h3>
                    <p class="text-sm text-slate-500">Tizimga hali hech qanday ta'sischi qo'shilmagan.</p>
                </div>
            </div>

            <!-- Fixed Assets View -->
            <div v-if="activeTab === 'assets'" class="animate-[fadeIn_0.3s_ease-out]">
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl border border-gray-200 rounded-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left font-mono text-xs">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                    <th class="p-4 border-b border-gray-200">Nomi</th>
                                    <th class="p-4 border-b border-gray-200">Turi</th>
                                    <th class="p-4 border-b border-gray-200">Sotib olingan</th>
                                    <th class="p-4 border-b border-gray-200">Narxi</th>
                                    <th class="p-4 border-b border-gray-200">Xizmat muddati</th>
                                    <th class="p-4 border-b border-gray-200">Holati</th>
                                    <th class="p-4 border-b border-gray-200 text-right">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="asset in fixedAssets" :key="asset.id" class="hover: transition-colors bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                    <td class="p-4">
                                        <div class="font-bold text-gray-900">{{ asset.name }}</div>
                                        <div class="text-[9px] text-gray-500 truncate max-w-[200px]">{{ asset.description || 'Izoh yo\'q' }}</div>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded-full text-gray-900 border border-gray-200 text-[9px]">
                                            {{ 
                                                asset.type === 'construction_equipment' ? 'Qurilish vositasi' : 
                                                asset.type === 'office_furniture' ? 'Ofis buyumi' : 
                                                asset.type === 'light_structure' ? 'Yengil konsturuksiya' : asset.type 
                                            }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-500">
                                        {{ formatDate(asset.purchase_date).split(' ')[0] }}
                                    </td>
                                    <td class="p-4 font-bold text-gray-900">
                                        {{ formatCurrency(asset.purchase_cost) }} UZS
                                    </td>
                                    <td class="p-4">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex justify-between text-[9px]">
                                                <span>{{ calculateServiceMonths(asset.purchase_date) }} oy</span>
                                                <span class="text-gray-500">Max: {{ asset.service_life_months }} oy</span>
                                            </div>
                                            <div class="w-24 h-1 rounded-full overflow-hidden">
                                                <div 
                                                    class="h-full rounded-full transition-all"
                                                    :class="calculateServiceMonths(asset.purchase_date) >= asset.service_life_months ? 'bg-red-600 shadow-sm' : 'bg-emerald-600 shadow-sm'"
                                                    :style="{ width: Math.min((calculateServiceMonths(asset.purchase_date) / asset.service_life_months) * 100, 100) + '%' }"
                                                ></div>
                                            </div>
                                            <div v-if="calculateServiceMonths(asset.purchase_date) >= asset.service_life_months" class="text-[8px] text-red-600 animate-pulse">XIZMAT MUDDATI TUGAGAN</div>
                                            <div v-else class="text-[8px] text-emerald-600">XIZMAT QILYAPTI</div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span 
                                            class="px-2 py-0.5 rounded text-[9px] font-bold"
                                            :class="{ 'bg-emerald-50 text-emerald-600 border border-emerald-200': asset.status === 'active', 'bg-red-50 text-red-600 border border-red-200': asset.status === 'sold', ' text-gray-900 border border-white/30': asset.status === 'retired', }"
                                        >
                                            {{ asset.status.toUpperCase() }}
                                        </span>
                                        <div v-if="asset.partner" class="text-[8px] text-gray-500 mt-1">Hamkor: {{ asset.partner.user?.name }}</div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button @click="openEditAssetModal(asset)" class="text-blue-600 hover:scale-110 transition-transform">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </button>
                                            <button @click="deleteAsset(asset.id)" class="text-red-600 hover:scale-110 transition-transform">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="fixedAssets.length === 0">
                                    <td colspan="7" class="p-12 text-center text-gray-500 font-mono italic">Assosiy vositalar ro'yxati bo'sh.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Partner Modal -->
        <Modal :show="showAddPartnerModal" @close="showAddPartnerModal = false" maxWidth="sm">
            <div class="p-6">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-red-600 text-white rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-sm z-50" @click="showAddPartnerModal = false">✖</button>
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-gray-200 pb-4">Yangi Ta'sischi</h2>
                <form @submit.prevent="submitPartner" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-gray-500 mb-1">Foydalanuvchi (User)</label>
                        <select v-model="partnerForm.user_id" required class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                            <option value="">Tanlang...</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} ({{ user.email }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1">Ulush Foizi (%)</label>
                        <input v-model="partnerForm.investment_share_percentage" type="number" step="0.01" min="0" max="100" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                        <p class="text-[9px] text-gray-500 mt-1">Majburiy emas. Agar foydadan sherik bo'lmasa 0 kiriting.</p>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1">Kiritilgan Sarmoya (UZS)</label>
                        <input v-model="partnerForm.total_invested" type="number" min="0" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                    </div>
                    <div class="flex justify-end gap-4 pt-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="showAddPartnerModal = false" class="px-4 py-2 hover:bg-gray-100 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded uppercase">Qo'shish</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Edit Partner Modal -->
        <Modal :show="showEditPartnerModal" @close="showEditPartnerModal = false" maxWidth="sm">
            <div class="p-6">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-red-600 text-white rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-sm z-50" @click="showEditPartnerModal = false">✖</button>
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-gray-200 pb-4">Ta'sischi Tahrirlash</h2>
                <form @submit.prevent="submitEditPartner" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-gray-500 mb-1">Ulush Foizi (%)</label>
                        <input v-model="editPartnerForm.investment_share_percentage" type="number" step="0.01" min="0" max="100" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1">Kiritilgan Sarmoya (UZS)</label>
                        <input v-model="editPartnerForm.total_invested" type="number" min="0" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1">Bo'ynidagi qarz / Hisobdorlik (UZS)</label>
                        <input v-model="editPartnerForm.accountable_balance" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-red-600" required>
                        <p class="text-[9px] text-gray-500 mt-1">Diqqat: Ushbu qiymatni qo'lda o'zgartirish moliyaviy xatolikka olib kelishi mumkin.</p>
                    </div>
                    <div class="flex justify-end gap-4 pt-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="showEditPartnerModal = false" class="px-4 py-2 hover:bg-gray-100 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded uppercase">{{ $t('save') }}</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Advance Modal -->
        <Modal :show="showAdvanceModal && selectedPartner !== null" @close="showAdvanceModal = false" maxWidth="sm">
            <div class="p-6">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-red-600 text-white rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-sm z-50" @click="showAdvanceModal = false">✖</button>
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-gray-200 pb-4 text-blue-600">Kassadan Pul So'rash</h2>
                <p class="text-xs font-mono text-gray-500 mb-4">Ushbu so'rov darhol kassaga ta'sir qilmaydi. U Moliya markazida ko'rib chiqilib **Tasdiqlangandan so'nggina** pul ajratiladi.</p>
                <form @submit.prevent="submitAdvance" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-gray-500 mb-1">Summa (UZS)</label>
                        <input v-model="advanceForm.amount" type="number" min="1" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-lg" required>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1">Maqsad (Izoh)</label>
                        <textarea v-model="advanceForm.notes" rows="2" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" placeholder="Masalan: Uy xarajatlari uchun yoki Avans"></textarea>
                    </div>
                    <div class="flex justify-end gap-4 pt-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="showAdvanceModal = false" class="px-4 py-2 hover:bg-gray-100 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded uppercase">So'rov yuborish</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Expense Modal -->
        <Modal :show="showExpenseModal && selectedPartner !== null" @close="showExpenseModal = false" maxWidth="sm">
            <div class="p-6">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-red-600 text-white rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-sm z-50" @click="showExpenseModal = false">✖</button>
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-gray-200 pb-4 text-emerald-600 mt-2">Xarajatni Qabul Qilish (Chek)</h2>
                <p class="text-xs font-mono text-gray-500 mb-4">Ushbu chek Moliya markazida tasdiqlangach hisobdorlik balansi qoldig'idan o'chiriladi.</p>
                
                <form @submit.prevent="submitExpense" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-gray-500 mb-1">Xarajat Summasi (UZS)</label>
                        <input v-model="expenseForm.amount" type="number" min="1" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-lg" required>
                        <p v-if="expenseForm.errors?.amount" class="text-red-600 text-[10px] mt-1">{{ expenseForm.errors.amount }}</p>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1">Kategoriya</label>
                        <select v-model="expenseForm.category" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                            <option value="Ovqatlanish">Ovqatlanish (Restoran)</option>
                            <option value="Reklama va Marketing">Reklama va Marketing</option>
                            <option value="Transport va Yoqilg'i">Transport va Yoqilg'i</option>
                            <option value="Divident">Divident (Shaxsiy cho'ntakka)</option>
                            <option value="Boshqa xarajatlar">Boshqa xarajatlar</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1">Izoh (Nimaga ishlatildi?)</label>
                        <textarea v-model="expenseForm.notes" rows="2" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" placeholder="Batafsil ma'lumot..."></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1">Chek Rasmi (Majburiy)</label>
                        <input type="file" @change="handleFileUpload" accept="image/*,.pdf" class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-600 hover:file:bg-emerald-600 hover:file:text-black cursor-pointer" required>
                        <p v-if="expenseForm.errors?.attachment" class="text-red-600 text-[10px] mt-1">{{ expenseForm.errors.attachment }}</p>
                    </div>
                    <div class="flex justify-end gap-4 pt-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="showExpenseModal = false" class="px-4 py-2 hover:bg-gray-100 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-emerald-600 text-white rounded uppercase">Tasdiqqa Yuborish</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showAnalyticsModal && selectedPartner !== null" @close="showAnalyticsModal = false" maxWidth="6xl">
            <div class="p-6 flex flex-col max-h-[90vh]">
                <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
                    <div>
                        <h2 class="text-2xl font-display font-bold uppercase tracking-widest">{{ selectedPartner.user?.name }} — Hisobot va Analitika</h2>
                        <p class="text-[10px] font-mono text-gray-500 mt-1">Barcha avanslar va xarajatlarning batafsil auditi</p>
                    </div>
                    <button @click="showAnalyticsModal = false" class="text-gray-900 hover:text-red-600 text-3xl leading-none">&times;</button>
                </div>

                <div v-if="analyticsLoading" class="flex justify-center items-center py-20">
                    <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                </div>

                <div v-else class="overflow-y-auto custom-scrollbar flex-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-gray-200 rounded-xl">
                            <h3 class="text-sm font-mono text-gray-500 uppercase mb-4 text-center">Xarajatlar Toifasi (Donut)</h3>
                            <div v-if="pieChartSeries.length > 0">
                                <VueApexCharts type="donut" height="250" :options="pieChartOptions" :series="pieChartSeries"></VueApexCharts>
                            </div>
                            <div v-else class="text-center text-xs font-mono text-gray-500 py-10">
                                Hech qanday xarajat kiritilmagan
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-gray-200 rounded-xl lg:col-span-2">
                            <h3 class="text-sm font-mono text-gray-500 uppercase mb-4 text-center">Oylik Sarf va Avans Tahlili</h3>
                            <VueApexCharts type="bar" height="250" :options="barChartOptions" :series="barChartSeries"></VueApexCharts>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 shadow-sm rounded-xl border border-gray-200 rounded-xl overflow-hidden">
                        <div class="p-4 border-b border-gray-200 flex flex-col md:flex-row justify-between items-center gap-4">
                            <h3 class="text-sm font-mono text-gray-900 uppercase tracking-widest">To'liq Tranzaksiyalar Tarixi</h3>
                            <div class="flex flex-wrap gap-2 items-center">
                                <input type="date" v-model="ledgerDateFrom" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-32 [color-scheme:dark]">
                                <span class="text-gray-500">-</span>
                                <input type="date" v-model="ledgerDateTo" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-32 [color-scheme:dark]">
                                <button @click="fetchLedger" class="px-3 py-1.5 bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-600 hover:text-black rounded text-xs font-bold uppercase transition-colors">Filtr</button>
                                <button @click="exportLedgerCSV" class="px-3 py-1.5 bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-600 hover:text-black rounded text-xs font-bold uppercase transition-colors flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                    Yuklab olish (CSV)
                                </button>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left font-mono text-xs">
                                <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                    <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                        <th class="p-3">Sana</th>
                                        <th class="p-3">Tur</th>
                                        <th class="p-3">Kategoriya</th>
                                        <th class="p-3 text-right">Summa (so'm)</th>
                                        <th class="p-3">Izoh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="t in transactions" :key="t.id" class="border-b border-gray-100 hover: transition-colors bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                        <td class="p-3 text-gray-500 whitespace-nowrap">{{ formatDate(t.created_at) }}</td>
                                        <td class="p-3">
                                            <span :class="t.type === 'income' ? 'text-emerald-600 bg-emerald-50' : 'text-red-600 bg-red-50'" class="px-2 py-0.5 rounded text-[10px] uppercase font-bold">
                                                {{ t.type === 'income' ? 'Kirim' : 'Chiqim' }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-gray-900">{{ t.category || '-' }}</td>
                                        <td class="p-3 text-right font-bold" :class="t.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                                            {{ t.type === 'income' ? '+' : '-' }}{{ formatCurrency(t.amount) }}
                                        </td>
                                        <td class="p-3 text-gray-500 max-w-xs truncate" :title="t.notes">{{ t.notes || '-' }}</td>
                                    </tr>
                                    <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="transactions.length === 0">
                                        <td colspan="5" class="p-6 text-center text-gray-500">Ma'lumot topilmadi.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal :show="showAssetModal" @close="showAssetModal = false" maxWidth="lg">
            <div class="p-6 max-h-[90vh] overflow-y-auto custom-scrollbar">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-red-600 text-white rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-sm z-50" @click="showAssetModal = false">✖</button>
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-gray-200 pb-4">
                    {{ isEditingAsset ? 'Assosiy Vosita Tahrirlash' : 'Yangi Assosiy Vosita' }}
                </h2>
                
                <form @submit.prevent="submitAsset" class="space-y-4 font-mono text-xs">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-gray-500 mb-1">Nomi / Model</label>
                            <input v-model="assetForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" placeholder="Masalan: Beton qorgich, Ofis stoli..." required>
                        </div>
                        
                        <div>
                            <label class="block text-gray-500 mb-1">Turi</label>
                            <select v-model="assetForm.type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                                <option value="construction_equipment">Qurilish vositasi</option>
                                <option value="office_furniture">Ofis buyumi</option>
                                <option value="light_structure">Yengil konsturuksiya</option>
                                <option value="other">Boshqa</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-500 mb-1">Holati</label>
                            <select v-model="assetForm.status" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                                <option value="active">Ishlatilmoqda (Aktiv)</option>
                                <option value="sold">Sotilgan</option>
                                <option value="retired">Ishdan chiqqan</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-500 mb-1">Sotib olingan sana</label>
                            <input v-model="assetForm.purchase_date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                        </div>

                        <div>
                            <label class="block text-gray-500 mb-1">Sotib olish narxi (UZS)</label>
                            <input v-model="assetForm.purchase_cost" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                        </div>

                        <div>
                            <label class="block text-gray-500 mb-1">Xizmat muddati (oylarda)</label>
                            <input v-model="assetForm.service_life_months" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                        </div>

                        <div>
                            <label class="block text-gray-500 mb-1">Mas'ul Hamkor (Taqsimot)</label>
                            <select v-model="assetForm.partner_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                                <option value="">Kompaniya (Umumiy)</option>
                                <option v-for="partner in partners" :key="partner.id" :value="partner.id">{{ partner.user?.name }}</option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-gray-500 mb-1">Qisqacha izoh</label>
                            <textarea v-model="assetForm.description" rows="3" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" placeholder="Texnik holati yoki boshqa ma'lumotlar..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 pt-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="showAssetModal = false" class="px-4 py-2 hover:bg-gray-100 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded uppercase">
                            {{ isEditingAsset ? 'Yangilash' : 'Saqlash' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>
