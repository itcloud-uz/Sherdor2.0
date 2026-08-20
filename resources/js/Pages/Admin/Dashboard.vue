<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { useI18n } from 'vue-i18n';
import FinancialDetailsModal from '@/Components/FinancialDetailsModal.vue';
import ExpensesBreakdownModal from '@/Components/ExpensesBreakdownModal.vue';

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
    salesByMonth: Array,
    analyticsByBuilding: Array,
    analyticsByMonth: Array,
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
                residential_count: 0,
                commercial_count: 0,
                parking_count: 0,
                sold_units: 0,
                reserved_units: 0,
                available_units: 0,
                total_area: 0,
                residential_area: 0,
                commercial_area: 0,
                parking_area: 0,
                sold_area: 0,
                available_area: 0,
                rooms_distribution: {},
                total_sum: 0,
                sold_sum: 0,
                debt_sum: 0
            };
        }
        groups[name].total_units += b.total_units || 0;
        groups[name].residential_count += b.residential_count || 0;
        groups[name].commercial_count += b.commercial_count || 0;
        groups[name].parking_count += b.parking_count || 0;
        groups[name].sold_units += b.sold_units || 0;
        groups[name].reserved_units += b.reserved_units || 0;
        groups[name].available_units += b.available_units || 0;
        groups[name].total_area += b.total_area || 0;
        groups[name].residential_area += b.residential_area || 0;
        groups[name].commercial_area += b.commercial_area || 0;
        groups[name].parking_area += b.parking_area || 0;
        groups[name].sold_area += b.sold_area || 0;
        groups[name].available_area += b.available_area || 0;
        groups[name].total_sum += Number(b.total_sum) || 0;
        groups[name].sold_sum += Number(b.sold_sum) || 0;
        groups[name].debt_sum += Number(b.debt_sum) || 0;
        if (b.photo_path && !groups[name].photo_path) {
            groups[name].photo_path = b.photo_path;
        }
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
            residential_area: Math.round(g.residential_area * 100) / 100,
            commercial_area: Math.round(g.commercial_area * 100) / 100,
            parking_area: Math.round(g.parking_area * 100) / 100,
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
const showExpensesModal = ref(false);
const showFinancialModal = ref(false);
const activeFinancialCategory = ref('');

const openFinancialDetails = (category) => {
    activeFinancialCategory.value = category;
    showFinancialModal.value = true;
};

// Kirim / Chiqim Analytics
const activeTab = ref('buildings');

const kirimChiqimOptions = computed(() => {
    const isBuildings = activeTab.value === 'buildings';
    const categories = isBuildings 
        ? (props.analyticsByBuilding || []).map(b => b.name || 'Noma\'lum Obyekt')
        : (props.analyticsByMonth || []).map(m => m.label || '');

    return {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            background: 'transparent'
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '45%',
                borderRadius: 6
            }
        },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        colors: ['#22c55e', '#ef4444'],
        xaxis: {
            categories: categories,
            labels: {
                style: { colors: '#6b7280', fontSize: '11px', fontWeight: 500 }
            },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            labels: {
                style: { colors: '#6b7280', fontSize: '11px' },
                formatter: (val) => {
                    if (val >= 1000000000) return (val / 1000000000).toFixed(1).replace('.0', '') + 'B';
                    if (val >= 1000000) return (val / 1000000).toFixed(1).replace('.0', '') + 'M';
                    if (val >= 1000) return (val / 1000).toFixed(1).replace('.0', '') + 'K';
                    return val;
                }
            }
        },
        grid: { borderColor: '#f3f4f6', strokeDashArray: 4 },
        legend: { show: true, position: 'top' },
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: (val) => new Intl.NumberFormat('uz-UZ').format(val) + " so'm"
            }
        }
    };
});

const kirimChiqimSeries = computed(() => {
    const isBuildings = activeTab.value === 'buildings';
    const dataIncome = isBuildings
        ? (props.analyticsByBuilding || []).map(b => b.income || 0)
        : (props.analyticsByMonth || []).map(m => m.income || 0);

    const dataExpense = isBuildings
        ? (props.analyticsByBuilding || []).map(b => b.expense || 0)
        : (props.analyticsByMonth || []).map(m => m.expense || 0);

    return [
        { name: 'Kirim', data: dataIncome },
        { name: 'Chiqim', data: dataExpense }
    ];
});

// Chart 3: Lead Source Donut
const leadSourceOptions = computed(() => ({
    chart: { type: 'donut', background: 'transparent' },
    labels: Object.keys(props.leadsBySource || {}).map(l => l.charAt(0).toUpperCase() + l.slice(1)),
    colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
    stroke: { width: 0 },
    dataLabels: { enabled: false },
    plotOptions: {
        pie: { donut: { size: '70%', labels: { show: true, name: { color: '#374151', fontSize: '13px' }, value: { color: '#111827', fontSize: '20px', fontWeight: 700 }, total: { show: true, color: '#6b7280', label: 'Jami Manbalar' } } } }
    },
    theme: { mode: 'light' },
    legend: { position: 'bottom', labels: { colors: '#374151' } }
}));
const leadSourceSeries = computed(() => Object.values(props.leadsBySource || {}));

// Chart 4: Sales Funnel (Bar)
const leadFunnelOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, background: 'transparent' },
    plotOptions: { bar: { horizontal: true, borderRadius: 6, distributed: true, dataLabels: { position: 'bottom' } } },
    colors: ['#3b82f6', '#10b981', '#f59e0b'],
    dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#111827'], fontSize: '12px', fontWeight: 600 }, offsetX: 10, dropShadow: { enabled: false } },
    xaxis: { categories: (props.leadsFunnel || []).map(item => item.name), labels: { style: { colors: '#6b7280' } } },
    yaxis: { labels: { style: { colors: '#374151', fontSize: '12px', fontWeight: 600 } } },
    theme: { mode: 'light' },
    grid: { borderColor: '#f3f4f6', strokeDashArray: 4 },
    legend: { show: false }
}));
const leadFunnelSeries = computed(() => [{ name: 'Lidlar', data: (props.leadsFunnel || []).map(item => item.data[0]) }]);

// Chart 5: Monthly Sales (Bar Chart)
const monthlySalesOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, background: 'transparent' },
    plotOptions: { bar: { horizontal: false, borderRadius: 6, columnWidth: '45%', distributed: false, dataLabels: { position: 'top' } } },
    colors: ['#f97316'],
    dataLabels: { 
        enabled: true, 
        formatter: (val) => val > 0 ? val : '',
        style: { colors: ['#475569'], fontSize: '11px', fontWeight: 600 },
        offsetY: -18
    },
    xaxis: { 
        categories: (props.salesByMonth || []).map(item => item.label), 
        labels: { style: { colors: '#6b7280', fontSize: '11px' } } 
    },
    yaxis: { 
        labels: { 
            style: { colors: '#6b7280' },
            formatter: (val) => Math.round(val)
        } 
    },
    theme: { mode: 'light' },
    grid: { borderColor: '#f3f4f6', strokeDashArray: 4 },
    tooltip: {
        y: { formatter: (val) => `${val} ta xonadon sotilgan` }
    }
}));

const monthlySalesSeries = computed(() => [{
    name: 'Sotuvlar soni',
    data: (props.salesByMonth || []).map(item => item.count)
}]);
</script>

<template>
    <Head :title="$t('global_dashboard')" />
    
    <AdminLayout>
        <!-- Crisp Contained Dashboard Container -->
        <div class="max-w-[1600px] mx-auto space-y-6 pb-12">

            <!-- ========================================== -->
            <!-- 1. BINO STATISTIKASI (OBJECT SUMMARY CARDS) -->
            <!-- ========================================== -->
            <div class="bg-white rounded-3xl border border-gray-200/80 p-6 sm:p-7 shadow-xs">
                
                <!-- Section Header with Filters -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-6 border-b border-gray-100 mb-6">
                    <div>
                        <h3 class="text-xl sm:text-2xl font-extrabold text-gray-900 tracking-tight">Bino statistikasi</h3>
                        <p class="text-xs text-gray-500 font-medium mt-0.5">Barcha binolarning umumiy va toifalar bo'yicha ko'rsatkichlari</p>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Object Selector Dropdown -->
                        <div class="relative">
                            <select 
                                v-model="selectedBuildingName" 
                                class="rounded-xl border border-gray-200 pl-3.5 pr-8 py-2 text-xs bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500 font-semibold cursor-pointer shadow-2xs"
                            >
                                <option value="">Barcha Obyektlar</option>
                                <option v-for="name in uniqueBuildingNames" :key="name" :value="name">{{ name }}</option>
                            </select>
                            <div class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </div>
                        </div>

                        <!-- Date Range Filters -->
                        <div class="flex items-center gap-2 bg-gray-50 p-1 rounded-xl border border-gray-200">
                            <div class="flex items-center gap-1.5 px-2 py-1 bg-white rounded-lg border border-gray-200/60 shadow-2xs">
                                <span class="text-[10px] uppercase font-bold text-gray-400">Dan:</span>
                                <input type="date" v-model="startDate" class="text-xs border-none p-0 focus:ring-0 text-gray-700 font-semibold cursor-pointer" />
                            </div>
                            <span class="text-gray-400 font-bold text-xs">→</span>
                            <div class="flex items-center gap-1.5 px-2 py-1 bg-white rounded-lg border border-gray-200/60 shadow-2xs">
                                <span class="text-[10px] uppercase font-bold text-gray-400">Gacha:</span>
                                <input type="date" v-model="endDate" class="text-xs border-none p-0 focus:ring-0 text-gray-700 font-semibold cursor-pointer" />
                            </div>
                            <button 
                                v-if="startDate || endDate" 
                                @click="clearDates" 
                                class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg bg-white border border-gray-200 transition-colors"
                                title="Filtrni tozalash"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Minimalist Responsive Building Cards Grid -->
                <div v-if="filteredBuildingsSummary && filteredBuildingsSummary.length > 0" class="space-y-4 w-full">
                    <div 
                        v-for="building in filteredBuildingsSummary" 
                        :key="building.id" 
                        class="bg-white rounded-2xl border border-gray-100 p-5 sm:p-6 shadow-xs hover:border-gray-200 transition-all space-y-5"
                    >
                        <!-- Top Header Row: Name, Progress, Total Value -->
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-gray-100">
                            
                            <!-- Left: Building Info -->
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-600 text-xl font-bold shrink-0">
                                    🏢
                                </div>
                                <div>
                                    <div class="flex items-center gap-2.5">
                                        <h4 class="text-gray-900 font-extrabold text-lg tracking-tight">{{ building.name }}</h4>
                                        <span class="px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 font-bold text-xs">
                                            {{ building.total_units }} ta obyekt
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-400 font-medium">Umumiy bino ko'rsatkichlari</p>
                                </div>
                            </div>

                            <!-- Center: Minimalist Progress -->
                            <div class="lg:w-72">
                                <div class="flex justify-between items-center text-xs font-semibold mb-1">
                                    <span class="text-gray-400">Sotuv darajasi</span>
                                    <span class="text-gray-800 font-bold">
                                        {{ building.sold_units }} / {{ building.total_units }}
                                        <span class="text-amber-500 font-extrabold ml-1">({{ (building.total_units > 0 ? (building.sold_units / building.total_units * 100) : 0).toFixed(1) }}%)</span>
                                    </span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                                    <div class="bg-amber-500 h-2 rounded-full transition-all duration-500" :style="{ width: (building.total_units > 0 ? (building.sold_units / building.total_units * 100) : 0) + '%' }"></div>
                                </div>
                            </div>

                            <!-- Right: Total Value Summary -->
                            <div class="text-left lg:text-right">
                                <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider block">Umumiy Qiymat</span>
                                <span class="text-base font-black text-gray-900 font-mono">{{ $currency(building.total_sum) }}</span>
                            </div>

                        </div>

                        <!-- 4-Column Minimalist Metrics Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-1">
                            
                            <!-- Col 1: Obyektlar Tarkibi -->
                            <div class="bg-gray-50/70 p-3.5 rounded-xl space-y-1.5 text-xs">
                                <span class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider block mb-1">Obyektlar Tarkibi</span>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium">🏠 Turar-joy:</span>
                                    <span class="text-gray-900 font-bold">{{ building.residential_count || 0 }} ta</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium">🏬 Do'kon:</span>
                                    <span class="text-gray-900 font-bold">{{ building.commercial_count || 0 }} ta</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium">🚗 Parking:</span>
                                    <span class="text-gray-900 font-bold">{{ building.parking_count || 0 }} ta</span>
                                </div>
                            </div>

                            <!-- Col 2: Xonalar Taqsimoti -->
                            <div class="bg-gray-50/70 p-3.5 rounded-xl space-y-1.5 text-xs">
                                <span class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider block mb-1">Xonalar Taqsimoti</span>
                                <div v-if="building.rooms_distribution && building.rooms_distribution.length > 0" class="space-y-1">
                                    <div v-for="rd in building.rooms_distribution" :key="rd.rooms" class="flex justify-between items-center">
                                        <span class="text-gray-600 font-medium">{{ rd.rooms }}-xonali:</span>
                                        <span class="text-gray-900 font-bold">{{ rd.count }} ta</span>
                                    </div>
                                </div>
                                <div v-else class="text-gray-400 font-medium py-1">
                                    Ma'lumot mavjud emas
                                </div>
                            </div>

                            <!-- Col 3: Sotuv Holati -->
                            <div class="bg-gray-50/70 p-3.5 rounded-xl space-y-1.5 text-xs">
                                <span class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider block mb-1">Sotuv Holati</span>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium">Sotuvda mavjud:</span>
                                    <span class="text-gray-900 font-bold">{{ building.available_units }} ta</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium">Sotilgan:</span>
                                    <span class="text-emerald-600 font-bold">{{ building.sold_units }} ta</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium">Bron qilingan:</span>
                                    <span class="text-amber-600 font-bold">{{ building.reserved_units }} ta</span>
                                </div>
                            </div>

                            <!-- Col 4: Maydon (m2) & Moliyaviy -->
                            <div class="bg-gray-50/70 p-3.5 rounded-xl space-y-1.5 text-xs">
                                <span class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider block mb-1">Maydon Ko'rsatkichlari</span>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium">Jami maydon:</span>
                                    <span class="text-gray-900 font-bold">{{ building.total_area }} m²</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium">Mavjud maydon:</span>
                                    <span class="text-gray-900 font-bold">{{ building.available_area }} m²</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600 font-medium">Sotilgan maydon:</span>
                                    <span class="text-emerald-600 font-bold">{{ building.sold_area }} m²</span>
                                </div>
                            </div>

                        </div>

                        <!-- Minimalist Footer: Sotilgan & Qarzdorlik -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-gray-100 text-xs font-mono">
                            <div class="flex items-center gap-6">
                                <span class="text-gray-500">
                                    Sotilgan summa: <strong class="text-emerald-600 font-bold">{{ $currency(building.sold_sum) }}</strong>
                                </span>
                                <span class="text-gray-500">
                                    Qarzdorlik: <strong class="text-red-500 font-bold">{{ $currency(building.debt_sum) }}</strong>
                                </span>
                            </div>
                            <div class="text-gray-400 text-[11px]">
                                Oxirgi yangilanish: Bugun
                            </div>
                        </div>

                    </div>
                </div>

                <div v-else class="text-center py-12 text-gray-400 font-medium text-sm">
                    Binolar bo'yicha ma'lumot topilmadi
                </div>

            </div>

            <!-- ========================================== -->
            <!-- 2. ANALITIKA & OYLIK SOTUVLAR (2-COL GRID) -->
            <!-- ========================================== -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Kirim / Chiqim Analytics Card -->
                <div class="bg-white p-6 rounded-3xl border border-gray-200/80 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-lg font-bold text-gray-800 tracking-tight">Kirim / Chiqim</h4>
                                <p class="text-xs text-gray-400">Moliyaviy tushum va xarajatlar taqqoslovi</p>
                            </div>

                            <!-- Tab Toggle -->
                            <div class="flex items-center bg-gray-100 p-1 rounded-xl">
                                <button 
                                    @click="activeTab = 'buildings'" 
                                    :class="activeTab === 'buildings' ? 'bg-white text-gray-900 font-bold shadow-2xs' : 'text-gray-500 hover:text-gray-800'" 
                                    class="px-3 py-1 rounded-lg text-xs transition-all cursor-pointer"
                                >
                                    Obyektlar
                                </button>
                                <button 
                                    @click="activeTab = 'months'" 
                                    :class="activeTab === 'months' ? 'bg-white text-gray-900 font-bold shadow-2xs' : 'text-gray-500 hover:text-gray-800'" 
                                    class="px-3 py-1 rounded-lg text-xs transition-all cursor-pointer"
                                >
                                    Oylar
                                </button>
                            </div>
                        </div>

                        <div class="h-[290px] w-full">
                            <VueApexCharts type="bar" height="100%" width="100%" :options="kirimChiqimOptions" :series="kirimChiqimSeries" />
                        </div>
                    </div>
                </div>

                <!-- Oylik Sotuvlar Statistikasi -->
                <div class="bg-white p-6 rounded-3xl border border-gray-200/80 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="mb-4">
                            <h4 class="text-lg font-bold text-gray-800 tracking-tight flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span> Oylik Sotuvlar Statistikasi
                            </h4>
                            <p class="text-xs text-gray-400">Oxirgi 12 oy davomida sotilgan xonadonlar soni</p>
                        </div>

                        <div class="h-[290px] w-full">
                            <VueApexCharts type="bar" height="100%" width="100%" :options="monthlySalesOptions" :series="monthlySalesSeries" />
                        </div>
                    </div>
                </div>

            </div>

            <!-- ========================================== -->
            <!-- 3. MARKETING VA LIDLAR TAHLILI (2-COL GRID) -->
            <!-- ========================================== -->
            <div class="bg-white rounded-3xl border border-gray-200/80 p-6 sm:p-7 shadow-xs space-y-6">
                
                <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 tracking-tight flex items-center gap-2.5">
                            <span class="w-3 h-3 rounded-full bg-blue-500"></span> {{ $t('sales_funnel_traffic') }}
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">Mijozlar oqimi va sotuv voronkasi dinamikasi</p>
                    </div>
                </div>

                <!-- Lead Stat Cards (3 Columns) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-blue-50/60 p-4 border border-blue-200 rounded-2xl shadow-2xs">
                        <p class="text-[11px] font-bold text-blue-700 uppercase tracking-wider">{{ $t('total_leads') }}</p>
                        <h2 class="text-2xl font-black text-gray-900 mt-1">
                            {{ leadsMetrics?.total || 0 }}
                        </h2>
                    </div>
                    <div class="bg-emerald-50/60 p-4 border border-emerald-200 rounded-2xl shadow-2xs">
                        <p class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider">{{ $t('qualified') }}</p>
                        <h2 class="text-2xl font-black text-gray-900 mt-1">
                            {{ leadsMetrics?.qualified || 0 }}
                        </h2>
                    </div>
                    <div @click="showLostLeadsModal = true" class="bg-red-50/60 p-4 border border-red-200 rounded-2xl shadow-2xs cursor-pointer hover:bg-red-100/70 transition-colors">
                        <div class="flex justify-between items-center">
                            <p class="text-[11px] font-bold text-red-700 uppercase tracking-wider">{{ $t('lost_leads_reasons') }}</p>
                            <span class="text-xs text-red-500 font-bold">Ko'rish ↗</span>
                        </div>
                        <h2 class="text-2xl font-black text-gray-900 mt-1">
                            {{ leadsMetrics?.lost || 0 }}
                        </h2>
                    </div>
                </div>

                <!-- 2-Column Grid: Donut + Funnel -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pt-2">
                    
                    <!-- Traffic Source Donut -->
                    <div class="bg-gray-50/60 p-5 rounded-2xl border border-gray-200/70 flex flex-col justify-between">
                        <h4 class="text-xs font-extrabold text-gray-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span> {{ $t('leads_source') }}
                        </h4>
                        <div class="h-[280px] w-full flex items-center justify-center">
                            <VueApexCharts type="donut" width="100%" height="100%" :options="leadSourceOptions" :series="leadSourceSeries" />
                        </div>
                    </div>

                    <!-- Sales Funnel Bar Chart -->
                    <div class="bg-gray-50/60 p-5 rounded-2xl border border-gray-200/70 flex flex-col justify-between">
                        <h4 class="text-xs font-extrabold text-gray-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span> {{ $t('sales_funnel') }}
                        </h4>
                        <div class="h-[280px] w-full">
                            <VueApexCharts type="bar" height="100%" width="100%" :options="leadFunnelOptions" :series="leadFunnelSeries" />
                        </div>
                    </div>

                </div>

            </div>

            <!-- MODAL: LOST LEADS -->
            <div v-if="showLostLeadsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs transition-all">
                <div class="bg-white w-full max-w-2xl rounded-3xl overflow-hidden flex flex-col max-h-[80vh] shadow-2xl border border-gray-100">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <h2 class="text-xl font-bold text-gray-900 uppercase tracking-wide">{{ $t('lost_leads') }}</h2>
                        <button @click="showLostLeadsModal = false" class="p-1.5 rounded-xl hover:bg-gray-200 text-gray-500 hover:text-gray-700 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-white">
                        <div v-if="!lostLeads || lostLeads.length === 0" class="text-center py-8 text-gray-500 font-medium text-sm">
                            {{ $t('no_lost_leads_found') }}
                        </div>
                        <div v-else class="space-y-3">
                            <div v-for="lead in lostLeads" :key="lead.id" class="p-4 border border-gray-200 rounded-2xl bg-white shadow-2xs hover:border-red-300 transition-colors">
                                <div class="flex justify-between items-start mb-1.5">
                                    <h3 class="text-gray-900 font-bold text-base">{{ lead.full_name }}</h3>
                                    <span class="text-xs text-gray-400 font-mono">{{ new Date(lead.updated_at).toLocaleDateString() }}</span>
                                </div>
                                <p class="text-blue-600 font-mono text-xs mb-3">{{ lead.phone }}</p>
                                <div class="bg-red-50 border border-red-100 p-3 rounded-xl">
                                    <p class="text-[10px] text-red-600 font-bold uppercase tracking-wider mb-0.5">{{ $t('reason_lost') }}:</p>
                                    <p class="text-xs text-gray-800">{{ lead.reason || 'Sabab ko\'rsatilmagan' }}</p>
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
    background: rgba(156, 163, 175, 0.5);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(107, 114, 128, 0.8);
}
</style>
