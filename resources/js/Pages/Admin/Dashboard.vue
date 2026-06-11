<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, nextTick, watch } from 'vue';
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
    financialSummary: Object,
    buildingsSummary: Array,
    startDate: String,
    endDate: String
});


const selectedBuildingName = ref('');
const startDate = ref(props.startDate || '');
const endDate = ref(props.endDate || '');

watch([startDate, endDate], () => {
    router.reload({
        data: {
            start_date: startDate.value || null,
            end_date: endDate.value || null
        },
        preserveState: true,
        preserveScroll: true
    });
});

const clearDates = () => {
    startDate.value = '';
    endDate.value = '';
};

const uniqueBuildingNames = computed(() => {
    if (!props.buildingsSummary) return [];
    const names = props.buildingsSummary.map(b => b.name || 'Noma\'lum Obyekt');
    return [...new Set(names)];
});

const groupedBuildingsSummary = computed(() => {
    if (!props.buildingsSummary) return [];
    const groups = {};
    props.buildingsSummary.forEach(b => {
        const name = b.name || 'Noma\'lum Obyekt';
        if (!groups[name]) {
            groups[name] = {
                id: b.id,
                name: name,
                photo_path: b.photo_path,
                total_units: 0,
                sold_units: 0,
                reserved_units: 0,
                available_units: 0,
                total_area: 0,
                sold_area: 0,
                available_area: 0,
                rooms_distribution: {},
                total_sum: 0,
                sold_sum: 0,
                debt_sum: 0
            };
        }
        groups[name].total_units += b.total_units || 0;
        groups[name].sold_units += b.sold_units || 0;
        groups[name].reserved_units += b.reserved_units || 0;
        groups[name].available_units += b.available_units || 0;
        groups[name].total_area += b.total_area || 0;
        groups[name].sold_area += b.sold_area || 0;
        groups[name].available_area += b.available_area || 0;
        groups[name].total_sum += Number(b.total_sum) || 0;
        groups[name].sold_sum += Number(b.sold_sum) || 0;
        groups[name].debt_sum += Number(b.debt_sum) || 0;
        if (b.photo_path && !groups[name].photo_path) {
            groups[name].photo_path = b.photo_path;
        }
        // merge rooms distribution
        if (b.rooms_distribution && Array.isArray(b.rooms_distribution)) {
            b.rooms_distribution.forEach(rd => {
                const r = rd.rooms || 0;
                groups[name].rooms_distribution[r] = (groups[name].rooms_distribution[r] || 0) + (rd.count || 0);
            });
        }
    });
    
    return Object.values(groups).map(g => {
        const roomsArr = Object.entries(g.rooms_distribution)
            .map(([rooms, count]) => ({ rooms: parseInt(rooms), count }))
            .sort((a, b) => a.rooms - b.rooms);
            
        return {
            ...g,
            total_area: Math.round(g.total_area * 100) / 100,
            sold_area: Math.round(g.sold_area * 100) / 100,
            available_area: Math.round(g.available_area * 100) / 100,
            rooms_distribution: roomsArr
        };
    });
});

const filteredBuildingsSummary = computed(() => {
    let list = groupedBuildingsSummary.value;
    if (selectedBuildingName.value) {
        list = list.filter(b => b.name === selectedBuildingName.value);
    }
    return list;
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
    <div class="bg-slate-50 min-h-screen p-4 md:p-6 lg:p-8 space-y-8">



        <!-- OBYEKTLAR KARTASI (BINO STATISTIKASI) -->
        <div class="mb-8 bg-white/50 backdrop-blur-sm p-6 rounded-3xl border border-gray-100 shadow-sm">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-8">
                <div>
                    <h3 class="text-2xl font-bold text-gray-800 tracking-tight">Bino statistikasi</h3>
                    <p class="text-xs text-gray-400 mt-1 font-medium">Barcha binolarning umumiy ko'rinishi</p>
                </div>
                
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Object Selector Dropdown -->
                    <div class="relative">
                        <select 
                            v-model="selectedBuildingName" 
                            class="rounded-full border border-gray-200 pl-4 pr-10 py-2 text-xs bg-white text-gray-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 shadow-sm appearance-none cursor-pointer font-medium"
                        >
                            <option value="">Obyektni tanlang</option>
                            <option v-for="name in uniqueBuildingNames" :key="name" :value="name">{{ name }}</option>
                        </select>
                        <div class="absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
                        </div>
                    </div>

                    <!-- Date Range selector (Redesigned) -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Start Date Input -->
                        <div class="relative flex items-center bg-white border border-gray-200 rounded-2xl px-4 py-1.5 shadow-sm text-gray-500 hover:border-amber-500 focus-within:ring-2 focus-within:ring-amber-500/20 focus-within:border-amber-500 transition-all duration-300">
                            <span class="text-[9px] uppercase font-bold text-gray-400 absolute -top-2 left-4 bg-white px-1">Sana (Dan)</span>
                            <input 
                                type="date" 
                                v-model="startDate" 
                                class="text-xs bg-transparent border-none outline-none text-gray-700 focus:ring-0 w-28 p-0 cursor-pointer font-semibold" 
                            />
                        </div>

                        <span class="text-gray-300 font-bold text-xs">→</span>

                        <!-- End Date Input -->
                        <div class="relative flex items-center bg-white border border-gray-200 rounded-2xl px-4 py-1.5 shadow-sm text-gray-500 hover:border-amber-500 focus-within:ring-2 focus-within:ring-amber-500/20 focus-within:border-amber-500 transition-all duration-300">
                            <span class="text-[9px] uppercase font-bold text-gray-400 absolute -top-2 left-4 bg-white px-1">Sana (Gacham)</span>
                            <input 
                                type="date" 
                                v-model="endDate" 
                                class="text-xs bg-transparent border-none outline-none text-gray-700 focus:ring-0 w-28 p-0 cursor-pointer font-semibold" 
                            />
                        </div>
                        
                        <!-- Clear Button -->
                        <button 
                            v-if="startDate || endDate" 
                            @click="clearDates" 
                            class="p-2 text-gray-400 hover:text-red-500 rounded-2xl bg-white border border-gray-200 hover:border-red-200 transition-colors shadow-sm"
                            title="Filtrni tozalash"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Horizontal Flex Scrollable Row -->
            <div class="flex overflow-x-auto gap-6 pb-4 scrollbar-thin snap-x">
                <div 
                    v-for="building in filteredBuildingsSummary" 
                    :key="building.id" 
                    class="w-[340px] md:w-[360px] shrink-0 bg-white rounded-3xl border border-gray-100 p-6 flex flex-col gap-5 shadow-sm hover:shadow-md transition-all duration-300 snap-start"
                >
                    <!-- Building Card Header -->
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl overflow-hidden bg-gray-50 border border-gray-100 shrink-0 shadow-sm">
                            <img v-if="building.photo_path" :src="'/storage/' + building.photo_path" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full bg-gradient-to-tr from-amber-500/10 to-yellow-500/5 flex items-center justify-center text-amber-500">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.33l-7.5-5-7.5 5V21" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <h4 class="text-gray-900 font-bold text-lg tracking-tight leading-tight">{{ building.name }}</h4>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div>
                        <div class="flex justify-between text-[10px] font-bold uppercase tracking-wider mb-2">
                            <span class="text-gray-400">Sotilgan</span>
                            <span class="text-amber-500">
                                {{ building.sold_units }} 
                                <span class="font-semibold">{{ (building.total_units > 0 ? (building.sold_units / building.total_units * 100) : 0).toFixed(2) }}%</span>
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-amber-500 h-2 rounded-full transition-all duration-500" :style="{ width: (building.total_units > 0 ? (building.sold_units / building.total_units * 100) : 0) + '%' }"></div>
                        </div>
                    </div>

                    <!-- XONALAR section -->
                    <div class="space-y-2">
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">XONALAR</p>
                        
                        <div class="flex justify-between text-xs border-b border-gray-50 pb-1.5">
                            <span class="text-gray-500">Bron qilingan</span>
                            <span class="text-gray-900 font-semibold">
                                {{ building.reserved_units }} 
                                <span class="text-amber-500 ml-1 font-bold">{{ (building.total_units > 0 ? (building.reserved_units / building.total_units * 100) : 0).toFixed(2) }}%</span>
                            </span>
                        </div>
                        
                        <div class="flex justify-between text-xs border-b border-gray-50 pb-1.5">
                            <span class="text-gray-500">Sotuvda mavjud</span>
                            <span class="text-gray-900 font-semibold">
                                {{ building.available_units }}
                                <span class="text-amber-500 ml-1 font-bold">{{ (building.total_units > 0 ? (building.available_units / building.total_units * 100) : 0).toFixed(2) }}%</span>
                            </span>
                        </div>
                        
                        <div class="flex justify-between text-xs pt-0.5">
                            <span class="text-gray-500">Jami</span>
                            <span class="text-gray-900 font-bold text-sm">{{ building.total_units }}</span>
                        </div>
                    </div>

                    <!-- MAYDON (M2) section -->
                    <div class="space-y-2">
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">MAYDON (M²)</p>
                        
                        <div class="flex justify-between text-xs border-b border-gray-50 pb-1.5">
                            <span class="text-gray-500">Sotilgan</span>
                            <span class="text-gray-900 font-semibold">
                                {{ building.sold_area }}
                                <span class="text-amber-500 ml-1 font-bold">{{ ((building.sold_area + building.available_area) > 0 ? (building.sold_area / (building.sold_area + building.available_area) * 100) : 0).toFixed(2) }}%</span>
                            </span>
                        </div>
                        
                        <div class="flex justify-between text-xs pt-0.5">
                            <span class="text-gray-500">Sotuvda mavjud</span>
                            <span class="text-gray-900 font-semibold">
                                {{ building.available_area }}
                                <span class="text-amber-500 ml-1 font-bold">{{ ((building.sold_area + building.available_area) > 0 ? (building.available_area / (building.sold_area + building.available_area) * 100) : 0).toFixed(2) }}%</span>
                            </span>
                        </div>
                    </div>

                    <!-- XONALAR TAQSIMOTI section -->
                    <div class="space-y-2">
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">XONALAR TAQSIMOTI</p>
                        
                        <div v-if="building.rooms_distribution && building.rooms_distribution.length > 0" class="flex flex-wrap gap-2 pt-1">
                            <div v-for="rd in building.rooms_distribution" :key="rd.rooms" class="flex-1 min-w-[70px] bg-gray-50/50 border border-gray-100 rounded-xl p-2 text-center shadow-sm">
                                <p class="text-sm font-bold text-gray-800 leading-none mb-1">{{ rd.count }}</p>
                                <p class="text-[10px] text-gray-400 font-medium leading-none">{{ rd.rooms }}-xonali</p>
                            </div>
                        </div>
                        <div v-else class="text-center py-2 text-gray-400 font-medium text-xs">
                            -
                        </div>
                    </div>

                    <!-- Bottom finances block -->
                    <div class="mt-auto pt-4 border-t border-gray-100 space-y-1.5 font-mono text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400 uppercase text-[10px] font-bold">Sotilgan</span>
                            <span class="text-emerald-500 font-bold text-sm">{{ $currency(building.sold_sum) }}</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400 uppercase text-[10px] font-bold">Qarzdorlik</span>
                            <span class="text-red-500 font-bold text-sm">{{ $currency(building.debt_sum) }}</span>
                        </div>
                        
                        <div class="flex justify-between items-center pt-1">
                            <span class="text-gray-500 uppercase text-[10px] font-bold">Umumiy summa</span>
                            <span class="text-gray-900 font-bold text-sm font-display">{{ $currency(building.total_sum) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECONDARY DETAILS GRID -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- 1. Faol Mijozlar -->
            <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm relative overflow-hidden group">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-blue-50 text-blue-500 rounded-2xl">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Faol Mijozlar</p>
                        <h3 class="text-xl font-bold text-gray-900 mt-0.5">{{ activeClients || 0 }} nafar</h3>
                    </div>
                </div>
            </div>

            <!-- 2. Bugungi Xodimlar (Davomat) -->
            <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm relative overflow-hidden group">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-indigo-50 text-indigo-500 rounded-2xl">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Xodimlar (Davomat Bugun)</p>
                        <div class="flex items-center gap-4 mt-0.5">
                            <div class="flex items-baseline gap-1">
                                <span class="text-xl font-bold text-emerald-600">{{ attendanceStats?.present || 0 }}</span>
                                <span class="text-xs text-gray-400 font-medium">kelgan</span>
                            </div>
                            <span class="text-gray-300 font-light">|</span>
                            <div class="flex items-baseline gap-1">
                                <span class="text-xl font-bold text-rose-600">{{ attendanceStats?.absent || 0 }}</span>
                                <span class="text-xs text-gray-400 font-medium">kelmagan</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-8">
            <!-- WIDGET 3: Financial Wave Chart -->
            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm h-[400px]">
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
        <div v-if="showLostLeadsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500/50 transition-all">
            <div class="bg-white w-full max-w-2xl rounded-2xl overflow-hidden flex flex-col max-h-[80vh]">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <h2 class="text-xl font-display font-bold text-gray-900 uppercase tracking-wide">{{ $t('lost_leads') }}</h2>
                    <button @click="showLostLeadsModal = false" class="text-gray-500 hover:text-gray-600 transition-colors">
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
        </div>
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
