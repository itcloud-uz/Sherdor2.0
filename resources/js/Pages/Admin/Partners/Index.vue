<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    partners: Array,
    users: Array,
    companyNetWorth: Number,
    netProfit: Number,
    fixedAssets: Array
});

const activeTab = ref('partners'); // 'partners' or 'assets'

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

async function openAnalytics(partner) {
    selectedPartner.value = partner;
    showAnalyticsModal.value = true;
    analyticsLoading.value = true;
    try {
        const response = await axios.get(route('admin.partners.ledger', partner.id));
        transactions.value = response.data;
    } catch (e) {
        console.error("Ledger fetch failed", e);
    } finally {
        analyticsLoading.value = false;
    }
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
        <div class="glass-panel p-4 md:p-8 rounded-2xl animate-[fadeIn_0.5s_ease-out]">
            <div class="flex justify-between items-center mb-6 border-b border-white/10 pb-4">
                <div>
                    <h2 class="text-2xl font-display text-white font-bold uppercase tracking-widest">Ta'sischilar Paneli</h2>
                    <p class="text-[10px] font-mono text-text-muted mt-1">Sarmoyadorlarni va kompaniya aktivlarini boshqarish</p>
                </div>
                <div class="flex gap-4">
                    <button v-if="activeTab === 'partners'" @click="showAddPartnerModal = true" class="px-4 py-2 bg-neon-cyan/10 border border-neon-cyan/50 text-neon-cyan text-xs font-mono font-bold rounded hover:bg-neon-cyan hover:text-black transition-all shadow-[0_0_10px_rgba(0,240,255,0.2)]">
                        + TA'SISCHI QO'SHISH
                    </button>
                    <button v-if="activeTab === 'assets'" @click="openAddAssetModal" class="px-4 py-2 bg-neon-green/10 border border-neon-green/50 text-neon-green text-xs font-mono font-bold rounded hover:bg-neon-green hover:text-black transition-all shadow-[0_0_10px_rgba(57,255,20,0.2)]">
                        + ASSOSIY VOSITA QO'SHISH
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-4 mb-8 border-b border-white/5 pb-px">
                <button 
                    @click="activeTab = 'partners'"
                    class="pb-4 px-2 text-xs font-mono font-bold tracking-widest transition-all relative"
                    :class="activeTab === 'partners' ? 'text-neon-cyan' : 'text-text-muted hover:text-white'"
                >
                    TA'SISCHILAR
                    <div v-if="activeTab === 'partners'" class="absolute bottom-0 left-0 w-full h-0.5 bg-neon-cyan shadow-[0_0_10px_#00f0ff]"></div>
                </button>
                <button 
                    @click="activeTab = 'assets'"
                    class="pb-4 px-2 text-xs font-mono font-bold tracking-widest transition-all relative"
                    :class="activeTab === 'assets' ? 'text-neon-green' : 'text-text-muted hover:text-white'"
                >
                    ASSOSIY VOSITALAR
                    <div v-if="activeTab === 'assets'" class="absolute bottom-0 left-0 w-full h-0.5 bg-neon-green shadow-[0_0_10px_#39ff14]"></div>
                </button>
            </div>

            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                    <p class="text-[10px] font-mono text-text-muted uppercase mb-1">Kompaniyaning jami qiymati</p>
                    <p class="text-3xl font-display font-bold text-white">{{ formatCurrency(companyNetWorth) }} <span class="text-sm">so'm</span></p>
                </div>
                <div class="bg-white/5 border border-neon-green/30 rounded-xl p-6 relative overflow-hidden">
                    <div class="absolute right-0 top-0 w-24 h-24 bg-neon-green/10 rounded-full blur-xl -translate-y-1/2 translate-x-1/2"></div>
                    <p class="text-[10px] font-mono text-text-muted uppercase mb-1">Kompaniyaning Sof Foydasi (Net Profit)</p>
                    <p class="text-3xl font-display font-bold text-neon-green">{{ formatCurrency(netProfit) }} <span class="text-sm">so'm</span></p>
                </div>
                <div class="bg-white/5 border border-neon-red/30 rounded-xl p-6 relative overflow-hidden">
                    <div class="absolute right-0 top-0 w-24 h-24 bg-neon-red/10 rounded-full blur-xl -translate-y-1/2 translate-x-1/2"></div>
                    <p class="text-[10px] font-mono text-text-muted uppercase mb-1">Ta'sischilardagi jami hisobdorlik (Qarz)</p>
                    <p class="text-3xl font-display font-bold text-neon-red">{{ formatCurrency(partners.reduce((sum, p) => sum + Number(p.accountable_balance), 0)) }} <span class="text-sm">so'm</span></p>
                </div>
            </div>

            <!-- Partners View -->
            <div v-if="activeTab === 'partners'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 animate-[fadeIn_0.3s_ease-out]">
                <div v-for="partner in partners" :key="partner.id" class="glass-card p-6 border border-white/10 hover:border-neon-cyan/50 transition-all group rounded-2xl relative">
                    <!-- ... existing partner card content ... -->
                    <div class="absolute top-4 right-4 text-right flex flex-col items-end gap-2">
                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click="openEditPartnerModal(partner)" class="text-neon-cyan hover:text-white" title="Tahrirlash">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </button>
                            <button @click="deletePartner(partner.id)" class="text-neon-red hover:text-white" title="O'chirish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                        <div>
                            <p class="text-[10px] font-mono text-text-muted uppercase mb-1">Ulush</p>
                            <p class="text-xl font-display font-bold text-neon-cyan">{{ Number(partner.investment_share_percentage) }}%</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 rounded-full bg-black/50 border border-neon-cyan/30 flex items-center justify-center text-neon-cyan font-bold font-display text-lg group-hover:shadow-[0_0_15px_rgba(0,240,255,0.5)] transition-all">
                            {{ partner.user?.name.charAt(0) || 'P' }}
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white leading-none">{{ partner.user?.name || $t('unknown') }}</h3>
                            <p class="text-[10px] text-text-muted font-mono mt-1">{{ partner.user?.email }}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4 pt-4 border-t border-white/5">
                        <div>
                            <span class="block text-[10px] font-mono text-text-muted uppercase">Jami Sarmoya</span>
                            <span class="text-sm font-mono text-white">{{ formatCurrency(partner.total_invested) }}</span>
                        </div>
                        <div class="text-right">
                            <span class="block text-[10px] font-mono text-neon-red uppercase">Bo'ynidagi qarz</span>
                            <span class="text-sm font-mono text-neon-red font-bold">{{ formatCurrency(partner.accountable_balance) }}</span>
                        </div>
                    </div>
                    
                    <div class="mb-6 p-3 bg-neon-green/5 border border-neon-green/20 rounded-xl text-center">
                        <span class="block text-[10px] font-mono text-text-muted uppercase">Soffoydadan Olinishi Mumkin (Taxminiy)</span>
                        <span class="text-lg font-display font-bold text-neon-green">{{ formatCurrency((netProfit * Number(partner.investment_share_percentage)) / 100) }} UZS</span>
                    </div>

                    <div class="flex gap-2">
                        <button @click="openAdvanceModal(partner)" class="flex-1 py-2 bg-neon-cyan/10 border border-neon-cyan/30 text-neon-cyan rounded text-[10px] font-bold font-mono uppercase hover:bg-neon-cyan hover:text-black transition-all">
                            Pul So'rash
                        </button>
                        <button @click="openExpenseModal(partner)" class="flex-1 py-2 bg-neon-green/10 border border-neon-green/30 text-neon-green rounded text-[10px] font-bold font-mono uppercase hover:bg-neon-green hover:text-black transition-all">
                            Chek Yuklash
                        </button>
                        <button @click="openAnalytics(partner)" class="flex-1 py-2 bg-white/5 border border-white/20 text-white rounded text-[10px] font-bold font-mono uppercase hover:bg-white hover:text-black transition-all">
                            Hisobot
                        </button>
                    </div>
                </div>

                <div v-if="partners.length === 0" class="col-span-full text-center py-16 border border-dashed border-white/10 rounded-2xl text-text-muted font-mono text-sm">
                    Tizimda ta'sischilar yo'q.
                </div>
            </div>

            <!-- Fixed Assets View -->
            <div v-if="activeTab === 'assets'" class="animate-[fadeIn_0.3s_ease-out]">
                <div class="glass-card border border-white/10 rounded-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left font-mono text-xs">
                            <thead class="bg-black/50 text-text-muted uppercase">
                                <tr>
                                    <th class="p-4 border-b border-white/10">Nomi</th>
                                    <th class="p-4 border-b border-white/10">Turi</th>
                                    <th class="p-4 border-b border-white/10">Sotib olingan</th>
                                    <th class="p-4 border-b border-white/10">Narxi</th>
                                    <th class="p-4 border-b border-white/10">Xizmat muddati</th>
                                    <th class="p-4 border-b border-white/10">Holati</th>
                                    <th class="p-4 border-b border-white/10 text-right">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="asset in fixedAssets" :key="asset.id" class="hover:bg-white/5 transition-colors">
                                    <td class="p-4">
                                        <div class="font-bold text-white">{{ asset.name }}</div>
                                        <div class="text-[9px] text-text-muted truncate max-w-[200px]">{{ asset.description || 'Izoh yo\'q' }}</div>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded-full bg-white/5 text-white border border-white/10 text-[9px]">
                                            {{ 
                                                asset.type === 'construction_equipment' ? 'Qurilish vositasi' : 
                                                asset.type === 'office_furniture' ? 'Ofis buyumi' : 
                                                asset.type === 'light_structure' ? 'Yengil konsturuksiya' : asset.type 
                                            }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-text-muted">
                                        {{ formatDate(asset.purchase_date).split(' ')[0] }}
                                    </td>
                                    <td class="p-4 font-bold text-white">
                                        {{ formatCurrency(asset.purchase_cost) }} UZS
                                    </td>
                                    <td class="p-4">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex justify-between text-[9px]">
                                                <span>{{ calculateServiceMonths(asset.purchase_date) }} oy</span>
                                                <span class="text-text-muted">Max: {{ asset.service_life_months }} oy</span>
                                            </div>
                                            <div class="w-24 h-1 bg-white/10 rounded-full overflow-hidden">
                                                <div 
                                                    class="h-full rounded-full transition-all"
                                                    :class="calculateServiceMonths(asset.purchase_date) >= asset.service_life_months ? 'bg-neon-red shadow-[0_0_5px_#ff003c]' : 'bg-neon-green shadow-[0_0_5px_#39ff14]'"
                                                    :style="{ width: Math.min((calculateServiceMonths(asset.purchase_date) / asset.service_life_months) * 100, 100) + '%' }"
                                                ></div>
                                            </div>
                                            <div v-if="calculateServiceMonths(asset.purchase_date) >= asset.service_life_months" class="text-[8px] text-neon-red animate-pulse">XIZMAT MUDDATI TUGAGAN</div>
                                            <div v-else class="text-[8px] text-neon-green">XIZMAT QILYAPTI</div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span 
                                            class="px-2 py-0.5 rounded text-[9px] font-bold"
                                            :class="{
                                                'bg-neon-green/10 text-neon-green border border-neon-green/30': asset.status === 'active',
                                                'bg-neon-red/10 text-neon-red border border-neon-red/30': asset.status === 'sold',
                                                'bg-white/10 text-white border border-white/30': asset.status === 'retired',
                                            }"
                                        >
                                            {{ asset.status.toUpperCase() }}
                                        </span>
                                        <div v-if="asset.partner" class="text-[8px] text-text-muted mt-1">Hamkor: {{ asset.partner.user?.name }}</div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button @click="openEditAssetModal(asset)" class="text-neon-cyan hover:scale-110 transition-transform">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </button>
                                            <button @click="deleteAsset(asset.id)" class="text-neon-red hover:scale-110 transition-transform">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="fixedAssets.length === 0">
                                    <td colspan="7" class="p-12 text-center text-text-muted font-mono italic">Assosiy vositalar ro'yxati bo'sh.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Partner Modal -->
        <div v-if="showAddPartnerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="relative bg-[#111] text-white p-4 md:p-8 rounded-2xl border border-white/20 w-full max-w-sm shadow-[0_0_50px_rgba(255,255,255,0.1)]">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-neon-red text-white rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-[0_0_15px_rgba(255,0,0,0.5)] z-50" @click="showAddPartnerModal = false">✖</button>
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-white/10 pb-4">Yangi Ta'sischi</h2>
                <form @submit.prevent="submitPartner" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-text-muted mb-1">Foydalanuvchi (User)</label>
                        <select v-model="partnerForm.user_id" required class="glass-input w-full bg-black/50">
                            <option value="">Tanlang...</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} ({{ user.email }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1">Ulush Foizi (%)</label>
                        <input v-model="partnerForm.investment_share_percentage" type="number" step="0.01" min="0" max="100" class="glass-input w-full">
                        <p class="text-[9px] text-text-muted mt-1">Majburiy emas. Agar foydadan sherik bo'lmasa 0 kiriting.</p>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1">Kiritilgan Sarmoya (UZS)</label>
                        <input v-model="partnerForm.total_invested" type="number" min="0" class="glass-input w-full" required>
                    </div>
                    <div class="flex justify-end gap-4 pt-4 border-t border-white/10 mt-6">
                        <button type="button" @click="showAddPartnerModal = false" class="px-4 py-2 hover:bg-white/10 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan hover:bg-neon-cyan hover:text-black rounded uppercase">Qo'shish</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Partner Modal -->
        <div v-if="showEditPartnerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="relative bg-[#111] text-white p-4 md:p-8 rounded-2xl border border-white/20 w-full max-w-sm shadow-[0_0_50px_rgba(255,255,255,0.1)]">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-neon-red text-white rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-[0_0_15px_rgba(255,0,0,0.5)] z-50" @click="showEditPartnerModal = false">✖</button>
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-white/10 pb-4">Ta'sischi Tahrirlash</h2>
                <form @submit.prevent="submitEditPartner" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-text-muted mb-1">Ulush Foizi (%)</label>
                        <input v-model="editPartnerForm.investment_share_percentage" type="number" step="0.01" min="0" max="100" class="glass-input w-full">
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1">Kiritilgan Sarmoya (UZS)</label>
                        <input v-model="editPartnerForm.total_invested" type="number" min="0" class="glass-input w-full" required>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1">Bo'ynidagi qarz / Hisobdorlik (UZS)</label>
                        <input v-model="editPartnerForm.accountable_balance" type="number" class="glass-input w-full text-neon-red" required>
                        <p class="text-[9px] text-text-muted mt-1">Diqqat: Ushbu qiymatni qo'lda o'zgartirish moliyaviy xatolikka olib kelishi mumkin.</p>
                    </div>
                    <div class="flex justify-end gap-4 pt-4 border-t border-white/10 mt-6">
                        <button type="button" @click="showEditPartnerModal = false" class="px-4 py-2 hover:bg-white/10 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan hover:bg-neon-cyan hover:text-black rounded uppercase">{{ $t('save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Advance Modal -->
        <div v-if="showAdvanceModal && selectedPartner" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="relative bg-[#111] text-white p-4 md:p-8 rounded-2xl border border-neon-cyan/50 w-full max-w-sm shadow-[0_0_50px_rgba(0,240,255,0.1)]">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-neon-red text-white rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-[0_0_15px_rgba(255,0,0,0.5)] z-50" @click="showAdvanceModal = false">✖</button>
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-white/10 pb-4 text-neon-cyan">Kassadan Pul So'rash</h2>
                <p class="text-xs font-mono text-text-muted mb-4">Ushbu so'rov darhol kassaga ta'sir qilmaydi. U Moliya markazida ko'rib chiqilib **Tasdiqlangandan so'nggina** pul ajratiladi.</p>
                <form @submit.prevent="submitAdvance" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-text-muted mb-1">Summa (UZS)</label>
                        <input v-model="advanceForm.amount" type="number" min="1" class="glass-input w-full text-lg" required>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1">Maqsad (Izoh)</label>
                        <textarea v-model="advanceForm.notes" rows="2" class="glass-input w-full" placeholder="Masalan: Uy xarajatlari uchun yoki Avans"></textarea>
                    </div>
                    <div class="flex justify-end gap-4 pt-4 border-t border-white/10 mt-6">
                        <button type="button" @click="showAdvanceModal = false" class="px-4 py-2 hover:bg-white/10 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan hover:bg-neon-cyan hover:text-black rounded uppercase">So'rov yuborish</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Expense Modal -->
        <div v-if="showExpenseModal && selectedPartner" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="relative bg-[#111] text-white p-4 md:p-8 rounded-2xl border border-neon-green/50 w-full max-w-sm shadow-[0_0_50px_rgba(57,255,20,0.1)]">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-neon-red text-white rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-[0_0_15px_rgba(255,0,0,0.5)] z-50" @click="showExpenseModal = false">✖</button>
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-white/10 pb-4 text-neon-green mt-2">Xarajatni Qabul Qilish (Chek)</h2>
                <p class="text-xs font-mono text-text-muted mb-4">Ushbu chek Moliya markazida tasdiqlangach hisobdorlik balansi qoldig'idan o'chiriladi.</p>
                
                <form @submit.prevent="submitExpense" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-text-muted mb-1">Xarajat Summasi (UZS)</label>
                        <input v-model="expenseForm.amount" type="number" min="1" class="glass-input w-full text-lg" required>
                        <p v-if="expenseForm.errors?.amount" class="text-neon-red text-[10px] mt-1">{{ expenseForm.errors.amount }}</p>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1">Kategoriya</label>
                        <select v-model="expenseForm.category" class="glass-input w-full bg-black/50" required>
                            <option value="Ovqatlanish">Ovqatlanish (Restoran)</option>
                            <option value="Reklama va Marketing">Reklama va Marketing</option>
                            <option value="Transport va Yoqilg'i">Transport va Yoqilg'i</option>
                            <option value="Divident">Divident (Shaxsiy cho'ntakka)</option>
                            <option value="Boshqa xarajatlar">Boshqa xarajatlar</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1">Izoh (Nimaga ishlatildi?)</label>
                        <textarea v-model="expenseForm.notes" rows="2" class="glass-input w-full" placeholder="Batafsil ma'lumot..."></textarea>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1">Chek Rasmi (Majburiy)</label>
                        <input type="file" @change="handleFileUpload" accept="image/*,.pdf" class="w-full text-xs text-text-muted file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-neon-green/20 file:text-neon-green hover:file:bg-neon-green hover:file:text-black cursor-pointer" required>
                        <p v-if="expenseForm.errors?.attachment" class="text-neon-red text-[10px] mt-1">{{ expenseForm.errors.attachment }}</p>
                    </div>
                    <div class="flex justify-end gap-4 pt-4 border-t border-white/10 mt-6">
                        <button type="button" @click="showExpenseModal = false" class="px-4 py-2 hover:bg-white/10 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-neon-green/20 border border-neon-green text-neon-green hover:bg-neon-green hover:text-black rounded uppercase">Tasdiqqa Yuborish</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Analytics Modal -->
        <div v-if="showAnalyticsModal && selectedPartner" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-[#111] text-white p-6 md:p-4 md:p-8 rounded-2xl border border-white/20 w-full max-w-6xl max-h-[90vh] overflow-y-auto custom-scrollbar shadow-[0_0_100px_rgba(255,255,255,0.05)]">
                <div class="flex justify-between items-center mb-6 border-b border-white/10 pb-4">
                    <div>
                        <h2 class="text-2xl font-display font-bold uppercase tracking-widest">{{ selectedPartner.user?.name }} — Hisobot va Analitika</h2>
                        <p class="text-[10px] font-mono text-text-muted mt-1">Barcha avanslar va xarajatlarning batafsil auditi</p>
                    </div>
                    <button @click="showAnalyticsModal = false" class="text-white hover:text-neon-red text-3xl leading-none">&times;</button>
                </div>

                <div v-if="analyticsLoading" class="flex justify-center items-center py-20">
                    <div class="w-8 h-8 border-4 border-neon-cyan border-t-transparent rounded-full animate-spin"></div>
                </div>

                <div v-else>
                    <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                        <!-- Pie Chart -->
                        <div class="glass-card p-6 border border-white/10 rounded-xl">
                            <h3 class="text-sm font-mono text-text-muted uppercase mb-4 text-center">Xarajatlar Toifasi (Donut)</h3>
                            <div v-if="pieChartSeries.length > 0">
                                <VueApexCharts type="donut" height="250" :options="pieChartOptions" :series="pieChartSeries"></VueApexCharts>
                            </div>
                            <div v-else class="text-center text-xs font-mono text-text-muted py-10">
                                Hech qanday xarajat kiritilmagan
                            </div>
                        </div>

                        <!-- Bar Chart -->
                        <div class="glass-card p-6 border border-white/10 rounded-xl lg:col-span-2">
                            <h3 class="text-sm font-mono text-text-muted uppercase mb-4 text-center">Oylik Sarf va Avans Tahlili</h3>
                            <VueApexCharts type="bar" height="250" :options="barChartOptions" :series="barChartSeries"></VueApexCharts>
                        </div>
                    </div>

                    <!-- Ledger Table -->
                    <div class="glass-card border border-white/10 rounded-xl overflow-hidden">
                        <div class="p-4 border-b border-white/10 bg-white/5">
                            <h3 class="text-sm font-mono text-white uppercase tracking-widest">To'liq Tranzaksiyalar Tarixi</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left font-mono text-xs">
                                <thead class="bg-black/50 text-text-muted uppercase">
                                    <tr>
                                        <th class="p-3">{{ $t('date_label') }}</th>
                                        <th class="p-3">Tur</th>
                                        <th class="p-3">Kategoriya</th>
                                        <th class="p-3 text-right">Summa (UZS)</th>
                                        <th class="p-3">{{ $t('notes') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="t in transactions" :key="t.id" class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                        <td class="p-3">{{ formatDate(t.created_at) }}</td>
                                        <td class="p-3">
                                            <span v-if="t.notes && t.notes.includes('Avans')" class="text-neon-cyan px-2 py-1 bg-neon-cyan/10 rounded">AVANS (OLDI)</span>
                                            <span v-else class="text-neon-red px-2 py-1 bg-neon-red/10 rounded">XARAJAT (CHEK)</span>
                                        </td>
                                        <td class="p-3">{{ t.category || '-' }}</td>
                                        <td class="p-3 text-right font-bold" :class="(t.notes && t.notes.includes('Avans')) ? 'text-neon-cyan' : 'text-neon-red'">
                                            {{ formatCurrency(t.amount) }}
                                        </td>
                                        <td class="p-3 text-text-muted max-w-xs truncate" :title="t.notes">{{ t.notes || '-' }}</td>
                                    </tr>
                                    <tr v-if="transactions.length === 0">
                                        <td colspan="5" class="p-6 text-center text-text-muted">Ma'lumot topilmadi.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Asset Modal -->
        <div v-if="showAssetModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="relative bg-[#111] text-white p-4 md:p-8 rounded-2xl border border-white/20 w-full max-w-lg shadow-[0_0_50px_rgba(255,255,255,0.1)] max-h-[90vh] overflow-y-auto custom-scrollbar">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-neon-red text-white rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-[0_0_15px_rgba(255,0,0,0.5)] z-50" @click="showAssetModal = false">✖</button>
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-white/10 pb-4">
                    {{ isEditingAsset ? 'Assosiy Vosita Tahrirlash' : 'Yangi Assosiy Vosita' }}
                </h2>
                
                <form @submit.prevent="submitAsset" class="space-y-4 font-mono text-xs">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-text-muted mb-1">Nomi / Model</label>
                            <input v-model="assetForm.name" type="text" class="glass-input w-full" placeholder="Masalan: Beton qorgich, Ofis stoli..." required>
                        </div>
                        
                        <div>
                            <label class="block text-text-muted mb-1">Turi</label>
                            <select v-model="assetForm.type" class="glass-input w-full bg-black/50" required>
                                <option value="construction_equipment">Qurilish vositasi</option>
                                <option value="office_furniture">Ofis buyumi</option>
                                <option value="light_structure">Yengil konsturuksiya</option>
                                <option value="other">Boshqa</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-text-muted mb-1">Holati</label>
                            <select v-model="assetForm.status" class="glass-input w-full bg-black/50" required>
                                <option value="active">Ishlatilmoqda (Aktiv)</option>
                                <option value="sold">Sotilgan</option>
                                <option value="retired">Ishdan chiqqan</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-text-muted mb-1">Sotib olingan sana</label>
                            <input v-model="assetForm.purchase_date" type="date" class="glass-input w-full" required>
                        </div>

                        <div>
                            <label class="block text-text-muted mb-1">Sotib olish narxi (UZS)</label>
                            <input v-model="assetForm.purchase_cost" type="number" step="0.01" class="glass-input w-full" required>
                        </div>

                        <div>
                            <label class="block text-text-muted mb-1">Xizmat muddati (oylarda)</label>
                            <input v-model="assetForm.service_life_months" type="number" class="glass-input w-full" required>
                        </div>

                        <div>
                            <label class="block text-text-muted mb-1">Mas'ul Hamkor (Taqsimot)</label>
                            <select v-model="assetForm.partner_id" class="glass-input w-full bg-black/50">
                                <option value="">Kompaniya (Umumiy)</option>
                                <option v-for="partner in partners" :key="partner.id" :value="partner.id">{{ partner.user?.name }}</option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-text-muted mb-1">Qisqacha izoh</label>
                            <textarea v-model="assetForm.description" rows="3" class="glass-input w-full" placeholder="Texnik holati yoki boshqa ma'lumotlar..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 pt-4 border-t border-white/10 mt-6">
                        <button type="button" @click="showAssetModal = false" class="px-4 py-2 hover:bg-white/10 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-neon-green/20 border border-neon-green text-neon-green hover:bg-neon-green hover:text-black rounded uppercase">
                            {{ isEditingAsset ? 'Yangilash' : 'Saqlash' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
