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
    colors: ['#00F0FF', '#FF003C'],
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.6, opacityTo: 0.05, stops: [0, 90, 100] } },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    xaxis: { categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'], labels: { style: { colors: '#777' } } },
    yaxis: { labels: { style: { colors: '#777' }, formatter: (val) => `$${(val / 1000)}k` } },
    theme: { mode: 'dark' },
    grid: { borderColor: '#ffffff05' },
    legend: { labels: { colors: '#fff' } }
};
const cashFlowSeries = [
    { name: t('income'), data: [15000, 25000, 20000, props.totalRevenue / 10 || 30000, props.totalRevenue / 5 || 40000, props.totalRevenue || 50000] },
    { name: t('expense'), data: [8000, 15000, 10000, props.totalExpenses / 10 || 15000, props.totalExpenses / 5 || 20000, props.totalExpenses || 25000] }
];

// Chart 2: Facility Radar
const radarOptions = {
    chart: { type: 'donut', background: 'transparent' },
    labels: ['Available', 'Reserved', 'Sold'],
    colors: ['#00F0FF', '#FCEE09', '#39FF14'],
    stroke: { width: 0 },
    dataLabels: { enabled: false },
    plotOptions: {
        pie: { donut: { size: '75%', labels: { show: true, name: { color: '#fff' }, value: { color: '#fff', fontSize: '24px' }, total: { show: true, color: '#aaa', label: 'Units' } } } }
    },
    theme: { mode: 'dark' },
    legend: { position: 'bottom', labels: { colors: '#fff' } }
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
    colors: ['#00F0FF', '#39FF14', '#FCEE09', '#FF003C', '#B026FF'],
    stroke: { width: 0 },
    dataLabels: { enabled: false },
    plotOptions: {
        pie: { donut: { size: '75%', labels: { show: true, name: { color: '#fff' }, value: { color: '#fff', fontSize: '24px' }, total: { show: true, color: '#aaa', label: 'Manbalar' } } } }
    },
    theme: { mode: 'dark' },
    legend: { position: 'bottom', labels: { colors: '#fff' } }
}));
const leadSourceSeries = computed(() => Object.values(props.leadsBySource || {}));

// Chart 4: Sales Funnel (Bar)
const leadFunnelOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, background: 'transparent' },
    plotOptions: { bar: { horizontal: true, borderRadius: 4, distributed: true, dataLabels: { position: 'bottom' } } },
    colors: ['#00F0FF', '#FCEE09', '#39FF14'],
    dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'] }, offsetX: 0, dropShadow: { enabled: true } },
    xaxis: { categories: (props.leadsFunnel || []).map(item => item.name), labels: { style: { colors: '#777' } } },
    yaxis: { labels: { style: { colors: '#fff' } } },
    theme: { mode: 'dark' },
    grid: { borderColor: '#ffffff05' },
    legend: { show: false }
}));
const leadFunnelSeries = computed(() => [{ name: 'Lidlar', data: (props.leadsFunnel || []).map(item => item.data[0]) }]);

</script>

<template>
    <Head :title="$t('global_dashboard')" />
    
    <AdminLayout>
        <div class="mb-8 flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-display font-bold text-white tracking-widest uppercase shadow-white/50 text-shadow-glow flex items-center gap-3">
                    <span class="text-4xl drop-shadow-[0_0_15px_rgba(255,255,255,0.6)]">🌌</span> {{ $t('global_dashboard') }}
                </h1>
                <p class="text-xs font-mono text-text-muted uppercase tracking-widest mt-2">{{ $t('dashboard_title_center') }}</p>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-mono text-neon-green animate-pulse">{{ $t('system_optimal') }}</p>
            </div>
        </div>

        <!-- WIDGET 1: Top Financial Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- 1. Cash Department -->
            <div @click="openFinancialDetails('cash')" class="glass-card p-6 border-t-4 border-neon-green relative overflow-hidden group cursor-pointer hover:scale-[1.02] transition-transform duration-300">
                <div class="absolute inset-0 bg-neon-green/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-sm font-display text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-neon-green animate-pulse"></span> KASSA
                </h3>
                <div class="space-y-3 font-mono text-xs">
                    <div class="flex justify-between items-center border-b border-white/5 pb-2">
                        <span class="text-text-muted">Kassa tushum:</span>
                        <span class="text-neon-green font-bold text-sm">{{ $currency(financialSummary?.cash?.income || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-white/5 pb-2">
                        <span class="text-text-muted">Kassa chiqim:</span>
                        <span class="text-neon-red font-bold text-sm">{{ $currency(financialSummary?.cash?.expense || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-text-muted uppercase tracking-widest">Balans:</span>
                        <span class="text-white font-display font-bold text-lg drop-shadow-[0_0_10px_rgba(255,255,255,0.3)]">{{ $currency(financialSummary?.cash?.balance || 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- 2. Bank Account -->
            <div @click="openFinancialDetails('bank')" class="glass-card p-6 border-t-4 border-neon-cyan relative overflow-hidden group cursor-pointer hover:scale-[1.02] transition-transform duration-300">
                <div class="absolute inset-0 bg-neon-cyan/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-sm font-display text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-neon-cyan animate-pulse"></span> HISOB RAQAM
                </h3>
                <div class="space-y-3 font-mono text-xs">
                    <div class="flex justify-between items-center border-b border-white/5 pb-2">
                        <span class="text-text-muted">Hisob raqam tushum:</span>
                        <span class="text-neon-green font-bold text-sm">{{ $currency(financialSummary?.bank?.income || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-white/5 pb-2">
                        <span class="text-text-muted">Hisob raqam chiqim:</span>
                        <span class="text-neon-red font-bold text-sm">{{ $currency(financialSummary?.bank?.expense || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-text-muted uppercase tracking-widest">Balans:</span>
                        <span class="text-white font-display font-bold text-lg drop-shadow-[0_0_10px_rgba(255,255,255,0.3)]">{{ $currency(financialSummary?.bank?.balance || 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- 3. Credit -->
            <div @click="openFinancialDetails('credit')" class="glass-card p-6 border-t-4 border-neon-yellow relative overflow-hidden group cursor-pointer hover:scale-[1.02] transition-transform duration-300">
                <div class="absolute inset-0 bg-neon-yellow/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-sm font-display text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-neon-yellow animate-pulse"></span> KREDIT
                </h3>
                <div class="space-y-3 font-mono text-xs">
                    <div class="flex justify-between items-center border-b border-white/5 pb-2">
                        <span class="text-text-muted">Kredit tushum:</span>
                        <span class="text-neon-green font-bold text-sm">{{ $currency(financialSummary?.credit?.income || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-white/5 pb-2">
                        <span class="text-text-muted">Kredit chiqim:</span>
                        <span class="text-neon-red font-bold text-sm">{{ $currency(financialSummary?.credit?.expense || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-text-muted uppercase tracking-widest">Balans:</span>
                        <span class="text-white font-display font-bold text-lg drop-shadow-[0_0_10px_rgba(255,255,255,0.3)]">{{ $currency(financialSummary?.credit?.balance || 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- 4. Overall -->
            <div @click="openFinancialDetails('overall')" class="glass-card p-6 border-t-4 border-neon-magenta relative overflow-hidden group cursor-pointer hover:scale-[1.02] transition-transform duration-300">
                <div class="absolute inset-0 bg-neon-magenta/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-sm font-display text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-neon-magenta animate-pulse"></span> UMUMIY
                </h3>
                <div class="space-y-3 font-mono text-xs">
                    <div class="flex justify-between items-center border-b border-white/5 pb-2">
                        <span class="text-text-muted">Umumiy tushum:</span>
                        <span class="text-neon-green font-bold text-sm">{{ $currency(financialSummary?.overall?.income || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-white/5 pb-2">
                        <span class="text-text-muted">Umumiy chiqim:</span>
                        <span class="text-neon-red font-bold text-sm">{{ $currency(financialSummary?.overall?.expense || 0) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-text-muted uppercase tracking-widest">Balans:</span>
                        <span class="text-white font-display font-bold text-lg drop-shadow-[0_0_10px_rgba(255,255,255,0.3)]">{{ $currency(financialSummary?.overall?.balance || 0) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
            <div class="glass-card p-4 border-l-4 border-neon-red relative overflow-hidden group">
                <div class="absolute inset-0 bg-neon-red/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <p class="text-[10px] font-mono text-text-muted uppercase tracking-widest">{{ $t('total_debts') }}</p>
                <h2 class="text-xl font-display font-bold text-white mt-1 drop-shadow-[0_0_15px_rgba(255,0,0,0.4)]">
                    {{ $currency(totalLiabilities || 0) }}
                </h2>
            </div>
            <div class="glass-card p-4 border-l-4 border-neon-magenta relative overflow-hidden group flex flex-col justify-center">
                <div class="absolute inset-0 bg-neon-magenta/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <p class="text-[10px] font-mono text-text-muted uppercase tracking-widest">{{ $t('hr_workers_today') }}</p>
                <h2 class="text-xl font-display font-bold text-white mt-1 drop-shadow-[0_0_15px_rgba(176,38,255,0.4)] flex gap-4">
                    <span class="text-neon-green" title="Kelganlar">{{ attendanceStats?.present || 0 }}</span>
                    <span class="text-text-muted">/</span>
                    <span class="text-neon-red" title="Kelmaganlar">{{ attendanceStats?.absent || 0 }}</span>
                </h2>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
            <!-- WIDGET 2: Financial Breakdown (Moved UP for visibility) -->
            <div @click="openFinancialDetails('financial_flow')" class="glass-panel p-6 rounded-2xl border border-white/5 h-[400px] flex flex-col cursor-pointer hover:border-white/20 transition-colors group">
                <h3 class="text-sm font-display text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-neon-magenta"></span> {{ $t('financial_flow_title') }}
                </h3>
                <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-6 text-xs font-mono">
                    <div>
                        <p class="text-neon-cyan mb-2 font-bold flex justify-between"><span>{{ $t('income_sources') }}</span> <span>{{ $currency(totalRevenue) }}</span></p>
                        <div v-for="(amount, source) in incomeBySource" :key="source" class="flex justify-between border-b border-white/5 py-1">
                            <span class="text-text-muted uppercase">{{ source }}</span>
                            <span class="text-white">{{ $currency(amount) }}</span>
                        </div>
                    </div>
                    <div @click="showExpensesModal = true" class="cursor-pointer group hover:bg-white/5 p-2 -mx-2 rounded transition-colors border border-transparent hover:border-white/10">
                        <p class="text-neon-red mb-2 font-bold flex justify-between group-hover:text-white transition-colors"><span>{{ $t('expense_destinations') }} <span class="text-[10px] text-text-muted ml-2">(batafsil ko'rish)</span></span> <span>{{ $currency(totalExpenses) }}</span></p>
                        <div v-for="(amount, source) in expensesBySource" :key="source" class="flex justify-between border-b border-white/5 py-1">
                            <span class="text-text-muted uppercase">{{ source }}</span>
                            <span class="text-white">{{ $currency(amount) }}</span>
                        </div>
                    </div>
                    <div v-if="activeLiabilities && activeLiabilities.length > 0">
                        <p class="text-neon-yellow mb-2 font-bold flex justify-between"><span>{{ $t('active_debts_unpaid') }}</span> <span>{{ $currency(totalLiabilities) }}</span></p>
                        <div v-for="liability in activeLiabilities" :key="liability.name" class="flex justify-between border-b border-white/5 py-1">
                            <span class="text-text-muted uppercase">{{ liability.name }}</span>
                            <span class="text-white">{{ $currency(liability.remaining) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WIDGET 3: Financial Wave Chart -->
            <div class="lg:col-span-2 glass-panel p-6 rounded-2xl border border-white/5 h-[400px]">
                <h3 class="text-sm font-display text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-neon-cyan"></span> {{ $t('cash_flow') }}
                </h3>
                <div class="h-80">
                    <VueApexCharts type="area" height="100%" :options="cashFlowOptions" :series="cashFlowSeries" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Facility Radar moved to bottom row -->
            <div class="flex flex-col gap-4">
                <div class="glass-panel p-6 rounded-2xl border border-white/5 flex flex-col items-center flex-1 h-96">
                    <h3 class="text-sm font-display text-white uppercase tracking-widest mb-4 w-full flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-neon-yellow"></span> {{ $t('facility_radar') }}
                    </h3>
                    <div class="w-full flex-1 flex items-center justify-center">
                        <VueApexCharts type="donut" width="100%" :options="radarOptions" :series="radarSeries" />
                    </div>
                </div>
            </div>

            <!-- Smart Notification Panel -->
            <div class="lg:col-span-1 glass-panel rounded-2xl border border-neon-yellow/30 shadow-[0_0_20px_rgba(252,238,9,0.05)] overflow-hidden flex flex-col h-96 relative group">
                <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-neon-yellow/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-1000 pointer-events-none"></div>
                
                <div class="bg-black/80 px-4 py-2 border-b border-neon-yellow/20 flex justify-between items-center z-10">
                    <span class="text-[10px] font-mono text-neon-yellow uppercase tracking-widest flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-neon-yellow animate-pulse"></span> {{ $t('system_alerts') }}
                    </span>
                    <span class="text-[10px] font-mono text-gray-500">{{ $t('live') }}</span>
                </div>

                <div class="flex-1 p-6 overflow-y-auto custom-scrollbar space-y-4 z-10">
                    <div v-for="(alert, index) in systemAlerts" :key="index" class="p-3 rounded-lg border flex items-start gap-3 bg-black/50" :class="alert.type === 'warning' ? 'border-neon-red/30 text-neon-red' : 'border-neon-cyan/30 text-neon-cyan'">
                        <span class="mt-0.5">{{ alert.type === 'warning' ? '⚠️' : 'ℹ️' }}</span>
                        <p class="text-xs font-mono">{{ alert.message }}</p>
                    </div>
                    <div v-if="!systemAlerts || systemAlerts.length === 0" class="text-center py-8 text-xs font-mono text-neon-green/50">
                        {{ $t('all_systems_good') }}
                    </div>
                </div>
            </div>

            <!-- Recent Activities Sidebar -->
            <div class="glass-panel p-6 rounded-2xl border border-white/5 overflow-hidden flex flex-col h-96">
                <h3 class="text-sm font-display text-white uppercase tracking-widest mb-6 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-white"></span> {{ $t('recent_activities') }}
                </h3>
                <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-4">
                    <div v-for="act in recentActivities" :key="act.id" class="p-4 rounded-xl bg-black/40 border border-white/5 hover:border-white/20 transition-all cursor-pointer group">
                        <div class="flex justify-between items-start mb-2">
                            <span class="px-2 py-1 rounded text-[8px] uppercase tracking-widest" :class="act.type === 'income' ? 'bg-neon-cyan/20 text-neon-cyan' : 'bg-neon-red/20 text-neon-red'">
                                {{ act.type }}
                            </span>
                            <span class="text-[10px] text-gray-500 font-mono">{{ new Date(act.created_at).toLocaleDateString() }}</span>
                        </div>
                        <p class="text-sm text-white font-bold mb-1">{{ $currency(act.amount) }}</p>
                        <p class="text-[10px] font-mono text-text-muted truncate">
                            {{ act.notes || act.paymentSchedule?.contract?.client?.first_name || 'System Generated' }}
                        </p>
                    </div>
                    <div v-if="recentActivities.length === 0" class="text-center py-8 text-xs font-mono text-gray-500">
                        {{ $t('no_recent_activities') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- NEW SECTION: MARKETING & LEAD ANALYTICS -->
        <div class="mt-12 mb-8">
            <h3 class="text-xl font-bold text-white mb-6 drop-shadow-[0_0_15px_rgba(255,255,255,0.4)] uppercase tracking-widest flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-neon-cyan animate-pulse"></span> {{ $t('sales_funnel_traffic') }}
            </h3>
            
            <!-- Lead Metrics Grid -->
            <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                <div class="glass-card p-5 border-l-4 border-neon-cyan relative overflow-hidden group">
                    <div class="absolute inset-0 bg-neon-cyan/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-[10px] font-mono text-text-muted uppercase tracking-widest">{{ $t('total_leads') }}</p>
                    <h2 class="text-3xl font-display font-bold text-white mt-2 drop-shadow-[0_0_15px_rgba(0,240,255,0.4)]">
                        {{ leadsMetrics?.total || 0 }}
                    </h2>
                </div>
                <div class="glass-card p-5 border-l-4 border-neon-green relative overflow-hidden group">
                    <div class="absolute inset-0 bg-neon-green/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-[10px] font-mono text-text-muted uppercase tracking-widest">{{ $t('qualified') }}</p>
                    <h2 class="text-3xl font-display font-bold text-white mt-2 drop-shadow-[0_0_15px_rgba(57,255,20,0.4)]">
                        {{ leadsMetrics?.qualified || 0 }}
                    </h2>
                </div>
                <div @click="showLostLeadsModal = true" class="glass-card p-5 border-l-4 border-neon-red relative overflow-hidden group cursor-pointer hover:bg-neon-red/10 transition-colors">
                    <div class="absolute inset-0 bg-neon-red/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-[10px] font-mono text-text-muted uppercase tracking-widest">{{ $t('lost_leads_reasons') }}</p>
                    <h2 class="text-3xl font-display font-bold text-white mt-2 drop-shadow-[0_0_15px_rgba(255,0,0,0.4)]">
                        {{ leadsMetrics?.lost || 0 }}
                    </h2>
                </div>
            </div>

            <!-- Marketing Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 gap-8">
                <!-- Traffic Source Donut -->
                <div class="glass-panel p-6 rounded-2xl border border-white/5 flex flex-col items-center h-[400px]">
                    <h3 class="text-sm font-display text-white uppercase tracking-widest mb-4 w-full flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-neon-cyan"></span> {{ $t('leads_source') }}
                    </h3>
                    <div class="w-full flex-1 flex items-center justify-center">
                        <VueApexCharts type="donut" width="100%" height="300" :options="leadSourceOptions" :series="leadSourceSeries" />
                    </div>
                </div>

                <!-- Sales Funnel Bar Chart -->
                <div class="glass-panel p-6 rounded-2xl border border-white/5 h-[400px]">
                    <h3 class="text-sm font-display text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-neon-green"></span> {{ $t('sales_funnel') }}
                    </h3>
                    <div class="h-80 w-full pt-4">
                        <VueApexCharts type="bar" height="100%" width="100%" :options="leadFunnelOptions" :series="leadFunnelSeries" />
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: LOST LEADS -->
        <div v-if="showLostLeadsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md transition-all">
            <div class="glass-panel w-full max-w-2xl rounded-2xl border border-neon-red/30 shadow-[0_0_50px_rgba(255,0,0,0.1)] overflow-hidden flex flex-col max-h-[80vh]">
                <div class="p-6 border-b border-white/10 flex justify-between items-center bg-black/40">
                    <h2 class="text-xl font-display font-bold text-white uppercase tracking-widest text-shadow-glow text-neon-red">{{ $t('lost_leads') }}</h2>
                    <button @click="showLostLeadsModal = false" class="text-text-muted hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
                    <div v-if="!lostLeads || lostLeads.length === 0" class="text-center text-text-muted font-mono text-sm">
                        {{ $t('no_lost_leads_found') }}
                    </div>
                    <div v-else class="space-y-4">
                        <div v-for="lead in lostLeads" :key="lead.id" class="p-4 border border-white/10 rounded-xl bg-black/30 hover:border-neon-red/30 transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-white font-bold">{{ lead.full_name }}</h3>
                                <span class="text-xs text-text-muted font-mono">{{ new Date(lead.updated_at).toLocaleDateString() }}</span>
                            </div>
                            <p class="text-neon-cyan font-mono text-xs mb-3">{{ lead.phone }}</p>
                            <div class="bg-neon-red/10 border border-neon-red/20 p-3 rounded-lg">
                                <p class="text-[10px] text-neon-red uppercase tracking-widest mb-1">{{ $t('reason_lost') }}:</p>
                                <p class="text-sm text-white">{{ lead.reason || 'Sabab ko\'rsatilmagan' }}</p>
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
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
}
</style>
