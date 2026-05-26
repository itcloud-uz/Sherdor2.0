<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, nextTick } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import FinancialDetailsModal from '@/Components/FinancialDetailsModal.vue';

const { t } = useI18n();
const props = defineProps({
    totalRevenue: [Number, String],
    totalExpenses: [Number, String],
    netBalance: [Number, String],
    creditBalance: [Number, String],
    activeClients: Number,
    unitsStatus: Object,
    recentActivities: Array,
    incomeBySource: Object,
    expensesBySource: Object,
    attendanceStats: Object,
    totalLiabilities: [Number, String],
    activeLiabilities: Array,
    systemAlerts: Array,
    leadsMetrics: Object,
    leadsBySource: Object,
    leadsFunnel: Array,
    lostLeads: Array,
    financialSummary: Object
});

const showLostLeadsModal = ref(false);
import ExpensesBreakdownModal from '@/Components/ExpensesBreakdownModal.vue';
const showExpensesModal = ref(false);

const showFinancialModal = ref(false);
const activeFinancialCategory = ref('');

const openFinancialDetails = (category) => {
    activeFinancialCategory.value = category;
    showFinancialModal.value = true;
};

// Chart 1: Cash Flow Area
const cashFlowOptions = {
    chart: { type: 'area', toolbar: { show: false }, background: 'transparent' },
    colors: ['#3b82f6', '#ef4444'], // blue-500, red-500
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    xaxis: { categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'], labels: { style: { colors: '#6b7280' } } },
    yaxis: { labels: { style: { colors: '#6b7280' }, formatter: (val) => `$${(val / 1000)}k` } },
    theme: { mode: 'light' },
    grid: { borderColor: '#f3f4f6', strokeDashArray: 4 },
    legend: { labels: { colors: '#374151' } }
};
const cashFlowSeries = [
    { name: t('income'), data: [15000, 25000, 20000, props.totalRevenue / 10 || 30000, props.totalRevenue / 5 || 40000, props.totalRevenue || 50000] },
    { name: t('expense'), data: [8000, 15000, 10000, props.totalExpenses / 10 || 15000, props.totalExpenses / 5 || 20000, props.totalExpenses || 25000] }
];

// Chart 2: Facility Radar
const radarOptions = {
    chart: { type: 'donut', background: 'transparent' },
    labels: ['Available', 'Reserved', 'Sold'],
    colors: ['#10b981', '#f59e0b', '#ef4444'], // emerald-500, amber-500, red-500
    stroke: { width: 0 },
    dataLabels: { enabled: false },
    plotOptions: {
        pie: { donut: { size: '75%', labels: { show: true, name: { color: '#374151' }, value: { color: '#111827', fontSize: '24px', fontWeight: 600 }, total: { show: true, color: '#6b7280', label: 'Units' } } } }
    },
    theme: { mode: 'light' },
    legend: { position: 'bottom', labels: { colors: '#374151' } }
};
const radarSeries = computed(() => [
    props.unitsStatus?.available || 0,
    props.unitsStatus?.reserved || 0,
    props.unitsStatus?.sold || 0
]);

// Chart 3: Lead Source Donut
const leadSourceOptions = computed(() => ({
    chart: { type: 'donut', background: 'transparent' },
    labels: Object.keys(props.leadsBySource || {}).map(l => l.charAt(0).toUpperCase() + l.slice(1)),
    colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'], // blue, emerald, amber, red, violet
    stroke: { width: 0 },
    dataLabels: { enabled: false },
    plotOptions: {
        pie: { donut: { size: '75%', labels: { show: true, name: { color: '#374151' }, value: { color: '#111827', fontSize: '24px', fontWeight: 600 }, total: { show: true, color: '#6b7280', label: 'Manbalar' } } } }
    },
    theme: { mode: 'light' },
    legend: { position: 'bottom', labels: { colors: '#374151' } }
}));
const leadSourceSeries = computed(() => Object.values(props.leadsBySource || {}));

// Chart 4: Sales Funnel (Bar)
const leadFunnelOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, background: 'transparent' },
    plotOptions: { bar: { horizontal: true, borderRadius: 4, distributed: true, dataLabels: { position: 'bottom' } } },
    colors: ['#3b82f6', '#10b981', '#f59e0b'],
    dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#111827'] }, offsetX: 0, dropShadow: { enabled: false } },
    xaxis: { categories: (props.leadsFunnel || []).map(item => item.name), labels: { style: { colors: '#6b7280' } } },
    yaxis: { labels: { style: { colors: '#374151' } } },
    theme: { mode: 'light' },
    grid: { borderColor: '#f3f4f6', strokeDashArray: 4 },
    legend: { show: false }
}));
const leadFunnelSeries = computed(() => [{ name: 'Lidlar', data: (props.leadsFunnel || []).map(item => item.data[0]) }]);

</script>

<template>
    <Head :title="$t('global_dashboard')" />
    
    <AdminLayout>


        <!-- WIDGET 1: Top Financial Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- 1. Cash Department -->
            <div @click="openFinancialDetails('cash')" class="bg-white p-6 border-t-4 border-emerald-500 rounded-xl shadow-sm relative overflow-hidden group cursor-pointer hover:shadow-md transition-shadow duration-300">
                <div class="absolute inset-0 bg-emerald-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4 flex items-center gap-2 relative z-10">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> KASSA
                </h3>
                <div class="space-y-3 font-mono text-xs relative z-10">
                    <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                        <span class="text-gray-500">Kassa tushum:</span>
                        <span class="text-emerald-600 font-bold text-sm">{{ $currency(financialSummary?.cash?.income || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                        <span class="text-gray-500">Kassa chiqim:</span>
                        <span class="text-red-600 font-bold text-sm">{{ $currency(financialSummary?.cash?.expense || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-gray-500 font-semibold tracking-wider">Balans:</span>
                        <span class="text-gray-900 font-display font-bold text-lg">{{ $currency(financialSummary?.cash?.balance || 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- 2. Bank Account -->
            <div @click="openFinancialDetails('bank')" class="bg-white p-6 border-t-4 border-blue-500 rounded-xl shadow-sm relative overflow-hidden group cursor-pointer hover:shadow-md transition-shadow duration-300">
                <div class="absolute inset-0 bg-blue-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4 flex items-center gap-2 relative z-10">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> HISOB RAQAM
                </h3>
                <div class="space-y-3 font-mono text-xs relative z-10">
                    <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                        <span class="text-gray-500">Hisob raqam tushum:</span>
                        <span class="text-emerald-600 font-bold text-sm">{{ $currency(financialSummary?.bank?.income || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                        <span class="text-gray-500">Hisob raqam chiqim:</span>
                        <span class="text-red-600 font-bold text-sm">{{ $currency(financialSummary?.bank?.expense || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-gray-500 font-semibold tracking-wider">Balans:</span>
                        <span class="text-gray-900 font-display font-bold text-lg">{{ $currency(financialSummary?.bank?.balance || 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- 3. Credit -->
            <div @click="openFinancialDetails('credit')" class="bg-white p-6 border-t-4 border-amber-500 rounded-xl shadow-sm relative overflow-hidden group cursor-pointer hover:shadow-md transition-shadow duration-300">
                <div class="absolute inset-0 bg-amber-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4 flex items-center gap-2 relative z-10">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span> KREDIT
                </h3>
                <div class="space-y-3 font-mono text-xs relative z-10">
                    <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                        <span class="text-gray-500">Kredit tushum:</span>
                        <span class="text-emerald-600 font-bold text-sm">{{ $currency(financialSummary?.credit?.income || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                        <span class="text-gray-500">Kredit chiqim:</span>
                        <span class="text-red-600 font-bold text-sm">{{ $currency(financialSummary?.credit?.expense || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-gray-500 font-semibold tracking-wider">Balans:</span>
                        <span class="text-gray-900 font-display font-bold text-lg">{{ $currency(financialSummary?.credit?.balance || 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- 4. Overall -->
            <div @click="openFinancialDetails('overall')" class="bg-white p-6 border-t-4 border-indigo-500 rounded-xl shadow-sm relative overflow-hidden group cursor-pointer hover:shadow-md transition-shadow duration-300">
                <div class="absolute inset-0 bg-indigo-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4 flex items-center gap-2 relative z-10">
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span> UMUMIY
                </h3>
                <div class="space-y-3 font-mono text-xs relative z-10">
                    <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                        <span class="text-gray-500">Umumiy tushum:</span>
                        <span class="text-emerald-600 font-bold text-sm">{{ $currency(financialSummary?.overall?.income || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                        <span class="text-gray-500">Umumiy chiqim:</span>
                        <span class="text-red-600 font-bold text-sm">{{ $currency(financialSummary?.overall?.expense || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-gray-500 font-semibold tracking-wider">Balans:</span>
                        <span class="text-gray-900 font-display font-bold text-lg">{{ $currency(financialSummary?.overall?.balance || 0) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
            <div class="bg-white p-4 border-l-4 border-red-500 rounded-xl shadow-sm relative overflow-hidden">
                <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">{{ $t('total_debts') }}</p>
                <h2 class="text-xl font-display font-bold text-gray-900 mt-1">
                    {{ $currency(totalLiabilities || 0) }}
                </h2>
            </div>
            <div class="bg-white p-4 border-l-4 border-indigo-500 rounded-xl shadow-sm relative overflow-hidden flex flex-col justify-center">
                <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">{{ $t('hr_workers_today') }}</p>
                <h2 class="text-xl font-display font-bold text-gray-900 mt-1 flex gap-4">
                    <span class="text-emerald-600" title="Kelganlar">{{ attendanceStats?.present || 0 }}</span>
                    <span class="text-gray-400">/</span>
                    <span class="text-red-600" title="Kelmaganlar">{{ attendanceStats?.absent || 0 }}</span>
                </h2>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
            <!-- WIDGET 2: Financial Breakdown (Moved UP for visibility) -->
            <div @click="openFinancialDetails('financial_flow')" class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm h-[400px] flex flex-col cursor-pointer hover:shadow-md transition-shadow group">
                <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span> {{ $t('financial_flow_title') }}
                </h3>
                <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-6 text-xs font-mono">
                    <div>
                        <p class="text-blue-600 mb-2 font-bold flex justify-between"><span>{{ $t('income_sources') }}</span> <span>{{ $currency(totalRevenue) }}</span></p>
                        <div v-for="(amount, source) in incomeBySource" :key="source" class="flex justify-between border-b border-gray-100 py-1.5">
                            <span class="text-gray-600 uppercase">{{ source }}</span>
                            <span class="text-gray-900 font-semibold">{{ $currency(amount) }}</span>
                        </div>
                    </div>
                    <div @click.stop="showExpensesModal = true" class="cursor-pointer group hover:bg-gray-50 p-2 -mx-2 rounded-lg transition-colors border border-transparent hover:border-gray-200">
                        <p class="text-red-600 mb-2 font-bold flex justify-between"><span>{{ $t('expense_destinations') }} <span class="text-[10px] text-gray-400 ml-2 font-normal">(batafsil ko'rish)</span></span> <span>{{ $currency(totalExpenses) }}</span></p>
                        <div v-for="(amount, source) in expensesBySource" :key="source" class="flex justify-between border-b border-gray-100 py-1.5">
                            <span class="text-gray-600 uppercase">{{ source }}</span>
                            <span class="text-gray-900 font-semibold">{{ $currency(amount) }}</span>
                        </div>
                    </div>
                    <div v-if="activeLiabilities && activeLiabilities.length > 0">
                        <p class="text-amber-600 mb-2 font-bold flex justify-between"><span>{{ $t('active_debts_unpaid') }}</span> <span>{{ $currency(totalLiabilities) }}</span></p>
                        <div v-for="liability in activeLiabilities" :key="liability.name" class="flex justify-between border-b border-gray-100 py-1.5">
                            <span class="text-gray-600 uppercase">{{ liability.name }}</span>
                            <span class="text-gray-900 font-semibold">{{ $currency(liability.remaining) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WIDGET 3: Financial Wave Chart -->
            <div class="lg:col-span-2 bg-white p-6 rounded-xl border border-gray-200 shadow-sm h-[400px]">
                <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> {{ $t('cash_flow') }}
                </h3>
                <div class="h-80">
                    <VueApexCharts type="area" height="100%" :options="cashFlowOptions" :series="cashFlowSeries" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Facility Radar moved to bottom row -->
            <div class="flex flex-col gap-4">
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col items-center flex-1 h-96">
                    <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4 w-full flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span> {{ $t('facility_radar') }}
                    </h3>
                    <div class="w-full flex-1 flex items-center justify-center">
                        <VueApexCharts type="donut" width="100%" :options="radarOptions" :series="radarSeries" />
                    </div>
                </div>
            </div>

            <!-- Smart Notification Panel -->
            <div class="lg:col-span-1 bg-white rounded-xl border border-amber-200 shadow-sm overflow-hidden flex flex-col h-96 relative group">
                <div class="bg-amber-50 px-4 py-3 border-b border-amber-100 flex justify-between items-center z-10">
                    <span class="text-[10px] font-semibold text-amber-700 uppercase tracking-wider flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span> {{ $t('system_alerts') }}
                    </span>
                    <span class="text-[10px] font-medium text-amber-600">{{ $t('live') }}</span>
                </div>

                <div class="flex-1 p-6 overflow-y-auto custom-scrollbar space-y-3 z-10">
                    <div v-for="(alert, index) in systemAlerts" :key="index" class="p-3 rounded-lg border flex items-start gap-3 bg-white" :class="alert.type === 'warning' ? 'border-red-200 text-red-700 bg-red-50' : 'border-blue-200 text-blue-700 bg-blue-50'">
                        <span class="mt-0.5">{{ alert.type === 'warning' ? '⚠️' : 'ℹ️' }}</span>
                        <p class="text-xs font-medium">{{ alert.message }}</p>
                    </div>
                    <div v-if="!systemAlerts || systemAlerts.length === 0" class="text-center py-8 text-xs font-medium text-emerald-600">
                        {{ $t('all_systems_good') }}
                    </div>
                </div>
            </div>

            <!-- Recent Activities Sidebar -->
            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col h-96">
                <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-6 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-gray-400"></span> {{ $t('recent_activities') }}
                </h3>
                <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-3">
                    <div v-for="act in recentActivities" :key="act.id" class="p-4 rounded-lg bg-gray-50 border border-gray-100 hover:border-gray-300 transition-colors cursor-pointer group">
                        <div class="flex justify-between items-start mb-2">
                            <span class="px-2 py-1 rounded text-[10px] font-semibold uppercase tracking-wider" :class="act.type === 'income' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700'">
                                {{ act.type }}
                            </span>
                            <span class="text-[10px] text-gray-500 font-mono">{{ new Date(act.created_at).toLocaleDateString() }}</span>
                        </div>
                        <p class="text-sm text-gray-900 font-bold mb-1">{{ $currency(act.amount) }}</p>
                        <p class="text-xs text-gray-500 truncate">
                            {{ act.notes || act.paymentSchedule?.contract?.client?.first_name || 'System Generated' }}
                        </p>
                    </div>
                    <div v-if="recentActivities.length === 0" class="text-center py-8 text-sm font-medium text-gray-500">
                        {{ $t('no_recent_activities') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- NEW SECTION: MARKETING & LEAD ANALYTICS -->
        <div class="mt-12 mb-8">
            <h3 class="text-xl font-bold text-gray-900 mb-6 uppercase tracking-wide flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-blue-500"></span> {{ $t('sales_funnel_traffic') }}
            </h3>
            
            <!-- Lead Metrics Grid -->
            <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                <div class="bg-white p-5 border-l-4 border-blue-500 rounded-xl shadow-sm relative overflow-hidden group">
                    <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">{{ $t('total_leads') }}</p>
                    <h2 class="text-3xl font-display font-bold text-gray-900 mt-2">
                        {{ leadsMetrics?.total || 0 }}
                    </h2>
                </div>
                <div class="bg-white p-5 border-l-4 border-emerald-500 rounded-xl shadow-sm relative overflow-hidden group">
                    <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">{{ $t('qualified') }}</p>
                    <h2 class="text-3xl font-display font-bold text-gray-900 mt-2">
                        {{ leadsMetrics?.qualified || 0 }}
                    </h2>
                </div>
                <div @click="showLostLeadsModal = true" class="bg-white p-5 border-l-4 border-red-500 rounded-xl shadow-sm relative overflow-hidden group cursor-pointer hover:bg-red-50 transition-colors">
                    <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">{{ $t('lost_leads_reasons') }}</p>
                    <h2 class="text-3xl font-display font-bold text-gray-900 mt-2">
                        {{ leadsMetrics?.lost || 0 }}
                    </h2>
                </div>
            </div>

            <!-- Marketing Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 gap-8">
                <!-- Traffic Source Donut -->
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col items-center h-[400px]">
                    <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4 w-full flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span> {{ $t('leads_source') }}
                    </h3>
                    <div class="w-full flex-1 flex items-center justify-center">
                        <VueApexCharts type="donut" width="100%" height="300" :options="leadSourceOptions" :series="leadSourceSeries" />
                    </div>
                </div>

                <!-- Sales Funnel Bar Chart -->
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm h-[400px]">
                    <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> {{ $t('sales_funnel') }}
                    </h3>
                    <div class="h-80 w-full pt-4">
                        <VueApexCharts type="bar" height="100%" width="100%" :options="leadFunnelOptions" :series="leadFunnelSeries" />
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: LOST LEADS -->
        <div v-if="showLostLeadsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 transition-all">
            <div class="bg-white w-full max-w-2xl rounded-2xl overflow-hidden flex flex-col max-h-[80vh]">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h2 class="text-xl font-display font-bold text-gray-900 uppercase tracking-wide">{{ $t('lost_leads') }}</h2>
                    <button @click="showLostLeadsModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-white">
                    <div v-if="!lostLeads || lostLeads.length === 0" class="text-center text-gray-500 font-medium text-sm">
                        {{ $t('no_lost_leads_found') }}
                    </div>
                    <div v-else class="space-y-4">
                        <div v-for="lead in lostLeads" :key="lead.id" class="p-5 border border-gray-200 rounded-xl bg-white shadow-sm hover:border-red-300 transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-gray-900 font-bold text-lg">{{ lead.full_name }}</h3>
                                <span class="text-xs text-gray-500 font-mono">{{ new Date(lead.updated_at).toLocaleDateString() }}</span>
                            </div>
                            <p class="text-blue-600 font-mono text-sm mb-4">{{ lead.phone }}</p>
                            <div class="bg-red-50 border border-red-100 p-4 rounded-lg">
                                <p class="text-[10px] text-red-600 font-bold uppercase tracking-wider mb-1">{{ $t('reason_lost') }}:</p>
                                <p class="text-sm text-gray-800">{{ lead.reason || 'Sabab ko\'rsatilmagan' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ExpensesBreakdownModal :show="showExpensesModal" @close="showExpensesModal = false" />

        <FinancialDetailsModal 
            :show="showFinancialModal" 
            :category="activeFinancialCategory" 
            @close="showFinancialModal = false" 
        />
    </AdminLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(156, 163, 175, 0.5); /* gray-400 */
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(107, 114, 128, 0.8); /* gray-500 */
}
</style>
