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
    netProfit: Number
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
    items: [],
    attachment: null
});

const expenseItems = ref([
    { name: '', quantity: 1, price: 0, total: 0 }
]);

function addItem() {
    expenseItems.value.push({ name: '', quantity: 1, price: 0, total: 0 });
}

function removeItem(index) {
    if (expenseItems.value.length > 1) {
        expenseItems.value.splice(index, 1);
    }
}

function updateItemTotal(item) {
    item.total = (Number(item.quantity) || 0) * (Number(item.price) || 0);
    expenseForm.amount = expenseItems.value.reduce((sum, i) => sum + i.total, 0);
}

function openExpenseModal(partner) {
    selectedPartner.value = partner;
    expenseForm.reset();
    expenseForm.category = 'Boshqa xarajatlar';
    expenseItems.value = [{ name: '', quantity: 1, price: 0, total: 0 }];
    showExpenseModal.value = true;
}

function handleFileUpload(event) {
    expenseForm.attachment = event.target.files[0];
}

function submitExpense() {
    isProcessing.value = true;
    expenseForm.items = expenseItems.value;
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
const activeLedgerTab = ref('expenses'); // 'expenses', 'advances', 'analytics'
const searchQuery = ref('');

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

async function openAnalytics(partner, defaultTab = 'expenses') {
    selectedPartner.value = partner;
    activeLedgerTab.value = defaultTab;
    searchQuery.value = '';
    showAnalyticsModal.value = true;
    ledgerDateFrom.value = '';
    ledgerDateTo.value = '';
    await fetchLedger();
}

const expandedExpenses = computed(() => {
    let list = [];
    let idx = 1;
    
    // Filter to get sarmoyalar: expense transactions (excluding advances and dividends) plus any incomes
    const sarmoyalar = transactions.value.filter(t => {
        const catName = t.category_display || '';
        return t.type === 'income' || (t.type === 'expense' && catName !== 'Partner Advance' && catName !== 'Divident');
    });

    sarmoyalar.forEach(t => {
        let parsed = null;
        try {
            if (t.notes && (t.notes.startsWith('{') || t.notes.startsWith('['))) {
                parsed = JSON.parse(t.notes);
            }
        } catch (e) {}

        if (parsed && parsed.items && parsed.items.length > 0) {
            parsed.items.forEach(item => {
                list.push({
                    id: t.id,
                    idx: idx++,
                    date: t.created_at,
                    tovar_nomi: item.name || item.tovar_nomi || 'Nomalum',
                    soni: item.quantity || item.soni || 1,
                    narxi: item.price || item.narxi || 0,
                    amount: item.total || item.amount || (item.quantity * item.price) || 0,
                    notes: parsed.notes || '',
                    status: t.status,
                    attachment_path: t.attachment_path
                });
            });
        } else {
            list.push({
                id: t.id,
                idx: idx++,
                date: t.created_at,
                tovar_nomi: t.category_display || 'Sarmoya',
                soni: 1,
                narxi: t.amount,
                amount: t.amount,
                notes: t.notes || '',
                status: t.status,
                attachment_path: t.attachment_path
            });
        }
    });

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        list = list.filter(item => 
            item.tovar_nomi.toLowerCase().includes(query) ||
            (item.notes && item.notes.toLowerCase().includes(query))
        );
    }
    return list;
});

const expandedAdvances = computed(() => {
    let list = [];
    let idx = 1;
    
    // Filter to get olingan pullar: Partner Advance and Divident
    const advances = transactions.value.filter(t => {
        const catName = t.category_display || '';
        return t.type === 'expense' && (catName === 'Partner Advance' || catName === 'Divident');
    });

    advances.forEach(t => {
        let parsed = null;
        try {
            if (t.notes && (t.notes.startsWith('{') || t.notes.startsWith('['))) {
                parsed = JSON.parse(t.notes);
            }
        } catch (e) {}

        if (parsed && parsed.items && parsed.items.length > 0) {
            parsed.items.forEach(item => {
                list.push({
                    id: t.id,
                    idx: idx++,
                    date: t.created_at,
                    tovar_nomi: item.name || item.tovar_nomi || 'Mablag\' olish',
                    soni: item.quantity || item.soni || 1,
                    narxi: item.price || item.narxi || 0,
                    amount: item.total || item.amount || (item.quantity * item.price) || 0,
                    notes: parsed.notes || '',
                    status: t.status,
                    attachment_path: t.attachment_path
                });
            });
        } else {
            list.push({
                id: t.id,
                idx: idx++,
                date: t.created_at,
                tovar_nomi: t.category_display === 'Divident' ? 'Divident (Shaxsiy foyda)' : 'Avans (Kassadan pul olish)',
                soni: 1,
                narxi: t.amount,
                amount: t.amount,
                notes: t.notes || '',
                status: t.status,
                attachment_path: t.attachment_path
            });
        }
    });

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        list = list.filter(item => 
            item.tovar_nomi.toLowerCase().includes(query) ||
            (item.notes && item.notes.toLowerCase().includes(query))
        );
    }
    return list;
});

function exportToExcel(type) {
    const list = type === 'expenses' ? expandedExpenses.value : expandedAdvances.value;
    if (list.length === 0) return;

    const title = type === 'expenses' 
        ? `${selectedPartner.value?.user?.name} tomonidan qilingan xarajatlar` 
        : `${selectedPartner.value?.user?.name} tomonidan olingan pul mablag'lari`;

    const headers = ['No', 'Sana', 'Tovar nomi / Maqsad', 'Soni', 'Narxi (so\'m)', 'Jami so\'mma (so\'m)', 'Izoh', 'Holati'];
    
    const rows = list.map(item => [
        item.idx,
        formatDate(item.date).split(' ')[0],
        item.tovar_nomi,
        item.soni,
        item.narxi,
        item.amount,
        `"${(item.notes || '').replace(/"/g, '""')}"`,
        item.status === 'approved' ? 'Tasdiqlangan' : item.status === 'pending' ? 'Kutilmoqda' : 'Rad etilgan'
    ]);

    const totalSum = list.reduce((sum, item) => sum + Number(item.amount), 0);
    rows.push(['', '', 'JAMI', '', '', totalSum, '', '']);

    const csvContent = "data:text/csv;charset=utf-8,\uFEFF" + 
        [title, '', headers.join(','), ...rows.map(r => r.join(','))].join('\n');
        
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `${title.replace(/\s+/g, '_')}_${new Date().getTime()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function exportLedgerCSV() {
    if (transactions.value.length === 0) return;
    const headers = ['Sana', 'Tur', 'Kategoriya', 'Summa', 'Izoh'];
    const rows = transactions.value.map(t => {
        const type = t.type === 'income' ? 'Kirim' : 'Chiqim';
        return [
            formatDate(t.created_at),
            type,
            t.category_display || '-',
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
        const cat = t.category_display || 'Boshqa';
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
        <div class="bg-slate-50 min-h-screen p-4 md:p-8 rounded-2xl animate-[fadeIn_0.5s_ease-out]">
            <!-- Sleek Header Card -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 bg-white border border-slate-100 p-6 rounded-2xl shadow-sm gap-4">
                <div>
                    <h3 class="text-lg font-display font-black text-slate-800 tracking-wide uppercase">Ta'sischilar Ro'yxati</h3>
                    <p class="text-xs text-slate-400 font-medium mt-1">Barcha ta'sischilar va ularning joriy moliyaviy hisobotlari</p>
                </div>
                <button @click="showAddPartnerModal = true" class="px-5 py-2.5 bg-blue-600 text-white text-xs font-mono font-bold rounded-xl hover:bg-blue-700 transition-all shadow-md shadow-blue-600/10 flex items-center gap-2 active:scale-95">
                    + TA'SISCHI QO'SHISH
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
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 animate-[fadeIn_0.4s_ease-out]">
                <div v-for="partner in partners" :key="partner.id" class="bg-white border border-slate-200/80 shadow-[0_5px_20px_rgba(0,0,0,0.05)] rounded-2xl overflow-hidden hover:shadow-[0_15px_35px_rgba(0,0,0,0.08)] hover:-translate-y-1 transition-all duration-300">
                    <!-- Partner Premium Dark/Gold Header -->
                    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-[#fce205] border-b border-amber-500/20 px-6 py-5 flex justify-between items-center relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-br from-amber-500/10 to-transparent rounded-bl-full pointer-events-none"></div>
                        <div class="flex items-center gap-3 z-10">
                            <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-[#fce205] font-black text-sm">
                                {{ partner.user?.name ? partner.user.name.charAt(0) : 'P' }}
                            </div>
                            <span class="font-display font-black text-base tracking-wide uppercase">{{ partner.user?.name || 'TA\'SISCHI' }}</span>
                        </div>
                        <div class="flex gap-2 z-10">
                            <button @click="openEditPartnerModal(partner)" class="p-1.5 bg-white/10 hover:bg-amber-500 text-white hover:text-black rounded-lg transition-all" title="Tahrirlash">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </button>
                            <button @click="deletePartner(partner.id)" class="p-1.5 bg-white/10 hover:bg-rose-600 text-white hover:text-white rounded-lg transition-all" title="O'chirish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Modern Premium Grid -->
                    <div class="p-6 grid grid-cols-3 gap-4 bg-slate-50/50 border-b border-slate-100">
                        <!-- Card 1: Sarmoya -->
                        <div @click="openAnalytics(partner, 'expenses')" class="bg-white border border-slate-200/80 rounded-2xl p-4 text-center cursor-pointer hover:shadow-lg hover:border-amber-300 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between min-h-[140px] group/card shadow-sm">
                            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto mb-3 group-hover/card:bg-amber-500 group-hover/card:text-white transition-colors duration-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Jami Sarmoya</span>
                                <span class="block text-sm font-black text-slate-800 font-mono leading-none">{{ formatCurrency(partner.total_sarmoya) }}</span>
                            </div>
                            <span class="text-[8px] text-slate-400 mt-2 block group-hover/card:text-amber-600 font-bold transition-colors">Ko'rish &rarr;</span>
                        </div>

                        <!-- Card 2: Olingan -->
                        <div @click="openAnalytics(partner, 'advances')" class="bg-white border border-slate-200/80 rounded-2xl p-4 text-center cursor-pointer hover:shadow-lg hover:border-emerald-300 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between min-h-[140px] group/card shadow-sm">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3 group-hover/card:bg-emerald-500 group-hover/card:text-white transition-colors duration-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Olingan pul</span>
                                <span class="block text-sm font-black text-slate-800 font-mono leading-none">{{ formatCurrency(partner.total_olingan) }}</span>
                            </div>
                            <span class="text-[8px] text-slate-400 mt-2 block group-hover/card:text-emerald-600 font-bold transition-colors">Ko'rish &rarr;</span>
                        </div>

                        <!-- Card 3: Ulush -->
                        <div @click="openAnalytics(partner, 'analytics')" class="bg-white border border-slate-200/80 rounded-2xl p-4 text-center cursor-pointer hover:shadow-lg hover:border-blue-300 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between min-h-[140px] group/card shadow-sm">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto mb-3 group-hover/card:bg-blue-500 group-hover/card:text-white transition-colors duration-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" /></svg>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Ulush {{ Number(partner.investment_share_percentage) }}%</span>
                                <span class="block text-sm font-black text-blue-700 font-mono leading-none">{{ formatCurrency(partner.calculated_share) }}</span>
                            </div>
                            <span class="text-[8px] text-slate-400 mt-2 block group-hover/card:text-blue-600 font-bold transition-colors">Hisobot &rarr;</span>
                        </div>
                    </div>

                    <!-- Footer Action Buttons -->
                    <div class="bg-slate-100 border-t border-slate-200/60 px-6 py-4 flex gap-4">
                        <button @click="openAdvanceModal(partner)" class="flex-1 py-2.5 bg-blue-600 text-white rounded-xl text-[10px] font-bold uppercase tracking-wider hover:bg-blue-700 hover:shadow-md hover:shadow-blue-500/20 transition-all active:scale-95 flex items-center justify-center gap-1.5">
                            💸 Pul So'rash
                        </button>
                        <button @click="openExpenseModal(partner)" class="flex-1 py-2.5 bg-emerald-500 text-white rounded-xl text-[10px] font-bold uppercase tracking-wider hover:bg-emerald-600 hover:shadow-md hover:shadow-emerald-500/20 transition-all active:scale-95 flex items-center justify-center gap-1.5">
                            📥 Chek Yuklash
                        </button>
                        <button @click="openAnalytics(partner, 'analytics')" class="flex-1 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl text-[10px] font-bold uppercase tracking-wider hover:bg-slate-50 hover:border-slate-300 transition-all active:scale-95 flex items-center justify-center gap-1.5">
                            📊 Analitika
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
        <Modal :show="showExpenseModal && selectedPartner !== null" @close="showExpenseModal = false" maxWidth="2xl">
            <div class="p-6">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-red-600 text-white rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-sm z-50" @click="showExpenseModal = false">✖</button>
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-gray-200 pb-4 text-emerald-600 mt-2">Xarajatni Qabul Qilish (Chek)</h2>
                <p class="text-xs font-mono text-gray-500 mb-4">Ushbu chek Moliya markazida tasdiqlangach hisobdorlik balansi qoldig'idan o'chiriladi.</p>
                
                <form @submit.prevent="submitExpense" class="space-y-4 font-mono text-xs">
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

                    <!-- Dynamic Itemized Inputs -->
                    <div class="border-t border-b border-gray-100 py-4 my-4 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-slate-700 text-xs">Tovar/Xizmatlar ro'yxati</span>
                            <button type="button" @click="addItem" class="px-2.5 py-1 bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white rounded text-[10px] font-bold uppercase transition-all shadow-sm">
                                + Tovar qo'shish
                            </button>
                        </div>

                        <div v-for="(item, index) in expenseItems" :key="index" class="grid grid-cols-12 gap-2 items-center border border-slate-100 p-2.5 rounded-lg bg-slate-50/50">
                            <div class="col-span-5">
                                <input v-model="item.name" type="text" placeholder="Tovar nomi (masalan: Shlang)" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm text-xs w-full py-1 px-2" required>
                            </div>
                            <div class="col-span-2">
                                <input v-model="item.quantity" type="number" step="any" min="0.01" placeholder="Soni" @input="updateItemTotal(item)" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm text-xs w-full py-1 px-2 text-center" required>
                            </div>
                            <div class="col-span-2">
                                <input v-model="item.price" type="number" min="0" placeholder="Narxi" @input="updateItemTotal(item)" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm text-xs w-full py-1 px-2 text-right" required>
                            </div>
                            <div class="col-span-2 text-right text-[10px] font-bold text-slate-800 font-mono">
                                {{ formatCurrency(item.total) }}
                            </div>
                            <div class="col-span-1 text-center">
                                <button type="button" @click="removeItem(index)" :disabled="expenseItems.length === 1" class="text-rose-500 hover:text-rose-700 disabled:opacity-30 text-base font-bold">
                                    &times;
                                </button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-gray-500 mb-1">Jami Xarajat Summasi (UZS)</label>
                        <input :value="formatCurrency(expenseForm.amount)" type="text" class="bg-slate-100 border border-gray-300 text-slate-800 rounded-md shadow-sm w-full text-lg font-bold font-mono text-right" disabled>
                        <p v-if="expenseForm.errors?.amount" class="text-red-600 text-[10px] mt-1">{{ expenseForm.errors.amount }}</p>
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

        <Modal :show="showAnalyticsModal && selectedPartner !== null" @close="showAnalyticsModal = false" maxWidth="7xl">
            <div class="p-6 flex flex-col max-h-[90vh]">
                <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
                    <div>
                        <h2 class="text-2xl font-display font-bold uppercase tracking-widest text-slate-800">{{ selectedPartner.user?.name }} — Tafsilotli Hisobot</h2>
                        <p class="text-[10px] font-mono text-gray-500 mt-1">Barcha sarmoyalar, avanslar va tahliliy hisobotlar</p>
                    </div>
                    <button @click="showAnalyticsModal = false" class="text-gray-900 hover:text-red-600 text-3xl leading-none">&times;</button>
                </div>

                <div v-if="analyticsLoading" class="flex justify-center items-center py-20">
                    <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                </div>

                <div v-else class="overflow-y-auto custom-scrollbar flex-1">
                    <!-- Premium breakdown summary matching the edited and dynamic investments -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 border border-slate-200 rounded-2xl overflow-hidden mb-8 text-center font-sans text-xs bg-white shadow-sm">
                        <div class="border-r border-slate-100 p-4">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Kiritilgan Sarmoya (Baza)</div>
                            <div class="font-mono font-black text-slate-800 text-sm">{{ formatCurrency(selectedPartner.total_invested) }} UZS</div>
                        </div>
                        <div class="border-r border-slate-100 p-4">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Aylanmadan Xarajatlar (Uskuna/Mulk)</div>
                            <div class="font-mono font-black text-slate-800 text-sm">{{ formatCurrency(selectedPartner.dynamic_sarmoya || 0) }} UZS</div>
                        </div>
                        <div class="border-r border-slate-100 p-4 bg-amber-50/30">
                            <div class="text-[10px] font-bold text-amber-600 uppercase tracking-widest mb-1.5">Jami Qilingan Sarmoya</div>
                            <div class="font-mono font-black text-amber-700 text-base leading-none">{{ formatCurrency(selectedPartner.total_sarmoya) }} UZS</div>
                        </div>
                        <div class="border-r border-slate-100 p-4">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Jami olingan so'mma</div>
                            <div class="font-mono font-black text-slate-800 text-sm">{{ formatCurrency(selectedPartner.total_olingan) }} UZS</div>
                        </div>
                        <div class="p-4 bg-blue-50/30">
                            <div class="text-[10px] font-bold text-blue-600 uppercase tracking-widest mb-1.5">Hisoblangan ulush</div>
                            <div class="font-mono font-black text-blue-700 text-sm">{{ formatCurrency(selectedPartner.calculated_share) }} UZS</div>
                        </div>
                    </div>

                    <!-- Tabs to switch between Expenses, Advances, and Charts -->
                    <div class="flex gap-4 mb-6 border-b border-gray-100 pb-px">
                        <button 
                            @click="activeLedgerTab = 'expenses'"
                            class="pb-3 px-2 text-xs font-mono font-bold tracking-widest transition-all relative"
                            :class="activeLedgerTab === 'expenses' ? 'text-blue-600' : 'text-gray-500 hover:text-gray-900'"
                        >
                            💰 QILINGAN SARMOYALAR ({{ expandedExpenses.length }})
                            <div v-if="activeLedgerTab === 'expenses'" class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 shadow-sm"></div>
                        </button>
                        <button 
                            @click="activeLedgerTab = 'advances'"
                            class="pb-3 px-2 text-xs font-mono font-bold tracking-widest transition-all relative"
                            :class="activeLedgerTab === 'advances' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-900'"
                        >
                            💸 OLINGAN PULLAR / AVANSLAR ({{ expandedAdvances.length }})
                            <div v-if="activeLedgerTab === 'advances'" class="absolute bottom-0 left-0 w-full h-0.5 bg-emerald-600 shadow-sm"></div>
                        </button>
                        <button 
                            @click="activeLedgerTab = 'analytics'"
                            class="pb-3 px-2 text-xs font-mono font-bold tracking-widest transition-all relative"
                            :class="activeLedgerTab === 'analytics' ? 'text-amber-500' : 'text-gray-500 hover:text-gray-900'"
                        >
                            📊 GRAFIK TAHLIL
                            <div v-if="activeLedgerTab === 'analytics'" class="absolute bottom-0 left-0 w-full h-0.5 bg-amber-500 shadow-sm"></div>
                        </button>
                    </div>

                    <!-- LEDGER TABLES -->
                    <div v-if="activeLedgerTab !== 'analytics'" class="space-y-4">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100 mb-4">
                            <div class="flex items-center gap-2 flex-1 max-w-md">
                                <span class="text-gray-400">🔍</span>
                                <input 
                                    v-model="searchQuery" 
                                    type="text" 
                                    placeholder="Tovar nomi yoki izoh bo'yicha qidirish..." 
                                    class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full py-1.5"
                                >
                            </div>
                            <div class="flex flex-wrap gap-2 items-center">
                                <input type="date" v-model="ledgerDateFrom" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-32">
                                <span class="text-gray-400 font-bold">-</span>
                                <input type="date" v-model="ledgerDateTo" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-32">
                                <button @click="fetchLedger" class="px-4 py-1.5 bg-blue-50 border border-blue-200 text-blue-600 hover:bg-blue-600 hover:text-white rounded text-xs font-mono font-bold uppercase transition-all shadow-sm">Filtr</button>
                                <button @click="exportToExcel(activeLedgerTab)" class="px-4 py-1.5 bg-emerald-50 border border-emerald-200 text-emerald-600 hover:bg-emerald-600 hover:text-white rounded text-xs font-mono font-bold uppercase transition-all shadow-sm flex items-center gap-1.5">
                                    📥 Excel Yuklash
                                </button>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-200 shadow-sm rounded-xl overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left font-mono text-xs">
                                    <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-xs border-b border-slate-200">
                                        <tr>
                                            <th class="p-3 border-r border-slate-200 text-center w-12">No</th>
                                            <th class="p-3 border-r border-slate-200 text-center w-24">Sana</th>
                                            <th class="p-3 border-r border-slate-200">Tovar nomi / Maqsad</th>
                                            <th class="p-3 border-r border-slate-200 text-center w-20">Soni</th>
                                            <th class="p-3 border-r border-slate-200 text-right w-32">Narxi (so'm)</th>
                                            <th class="p-3 border-r border-slate-200 text-right w-36">Jami so'mma</th>
                                            <th class="p-3 border-r border-slate-200">Izoh</th>
                                            <th class="p-3 border-r border-slate-200 text-center w-24">Holati</th>
                                            <th class="p-3 text-center w-16" v-if="activeLedgerTab === 'expenses'">Chek</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="item in (activeLedgerTab === 'expenses' ? expandedExpenses : expandedAdvances)" :key="item.id + '_' + item.idx" class="hover:bg-slate-50/60 transition-colors">
                                            <td class="p-3 border-r border-slate-200 text-center text-slate-500 font-bold">{{ item.idx }}</td>
                                            <td class="p-3 border-r border-slate-200 text-center text-slate-600">{{ formatDate(item.date).split(' ')[0] }}</td>
                                            <td class="p-3 border-r border-slate-200 text-slate-900 font-sans font-semibold">{{ item.tovar_nomi }}</td>
                                            <td class="p-3 border-r border-slate-200 text-center font-bold text-slate-800">{{ item.soni }}</td>
                                            <td class="p-3 border-r border-slate-200 text-right text-slate-600">{{ formatCurrency(item.narxi) }}</td>
                                            <td class="p-3 border-r border-slate-200 text-right font-black text-slate-900">{{ formatCurrency(item.amount) }} UZS</td>
                                            <td class="p-3 border-r border-slate-200 text-slate-500 font-sans max-w-xs truncate" :title="item.notes">{{ item.notes || '-' }}</td>
                                            <td class="p-3 border-r border-slate-200 text-center">
                                                <span 
                                                    class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider"
                                                    :class="{
                                                        'bg-emerald-50 text-emerald-600 border border-emerald-200': item.status === 'approved',
                                                        'bg-amber-50 text-amber-600 border border-amber-200': item.status === 'pending',
                                                        'bg-rose-50 text-rose-600 border border-rose-200': item.status === 'rejected',
                                                    }"
                                                >
                                                    {{ item.status === 'approved' ? 'Tasdiqlangan' : item.status === 'pending' ? 'Kutilmoqda' : 'Rad etilgan' }}
                                                </span>
                                            </td>
                                            <td class="p-3 text-center" v-if="activeLedgerTab === 'expenses'">
                                                <a v-if="item.attachment_path" :href="'/storage/' + item.attachment_path" target="_blank" class="text-blue-500 hover:text-blue-700 font-sans font-bold hover:underline">Chek</a>
                                                <span v-else class="text-slate-300">-</span>
                                            </td>
                                        </tr>
                                        <tr v-if="(activeLedgerTab === 'expenses' ? expandedExpenses : expandedAdvances).length === 0">
                                            <td :colspan="activeLedgerTab === 'expenses' ? 9 : 8" class="p-12 text-center text-slate-400 italic">Ma'lumot topilmadi.</td>
                                        </tr>
                                        <!-- JAMI (Total sum) row matching Image 2 -->
                                        <tr class="bg-slate-50 font-bold border-t border-slate-300">
                                            <td class="p-3 text-center border-r border-slate-200" colspan="2">JAMI</td>
                                            <td class="p-3 border-r border-slate-200 text-slate-800" colspan="3">Barcha ro'yxat bo'yicha summa</td>
                                            <td class="p-3 text-right text-base font-black text-slate-900 border-r border-slate-200">
                                                {{ formatCurrency((activeLedgerTab === 'expenses' ? expandedExpenses : expandedAdvances).reduce((sum, item) => sum + Number(item.amount), 0)) }} UZS
                                            </td>
                                            <td class="p-3" :colspan="activeLedgerTab === 'expenses' ? 3 : 2"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ANALYTICS CHARTS TAB -->
                    <div v-else class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
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
                    </div>
                </div>
            </div>
        </Modal>

    </AdminLayout>
</template>
