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
import ExpensesBreakdownModal from '@/Components/ExpensesBreakdownModal.vue';
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
        dataLabels: {
            enabled: false
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        colors: ['#22c55e', '#ef4444'], // Green for Kirim (income), Red for Chiqim (expense)
        xaxis: {
            categories: categories,
            labels: {
                style: {
                    colors: '#6b7280',
                    fontSize: '11px',
                    fontWeight: 500
                }
            },
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
            }
        },
        yaxis: {
            labels: {
                style: {
                    colors: '#6b7280',
                    fontSize: '11px'
                },
                formatter: (val) => {
                    if (val >= 1000000000) {
                        return (val / 1000000000).toFixed(1).replace('.0', '') + 'B';
                    }
                    if (val >= 1000000) {
                        return (val / 1000000).toFixed(1).replace('.0', '') + 'M';
                    }
                    if (val >= 1000) {
                        return (val / 1000).toFixed(1).replace('.0', '') + 'K';
                    }
                    return val;
                }
            }
        },
        grid: {
            borderColor: '#f3f4f6',
            strokeDashArray: 4,
            xaxis: {
                lines: {
                    show: false
                }
            }
        },
        legend: {
            show: false
        },
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: (val) => {
                    return new Intl.NumberFormat('uz-UZ').format(val) + " so'm";
                }
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
        {
            name: 'Kirim',
            data: dataIncome
        },
        {
            name: 'Chiqim',
            data: dataExpense
        }
    ];
});

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

// Chart 5: Monthly Sales (Bar Chart)
const monthlySalesOptions = computed(() => ({
    chart: { 
        type: 'bar', 
        toolbar: { show: false }, 
        background: 'transparent' 
    },
    plotOptions: { 
        bar: { 
            horizontal: false, 
            borderRadius: 6, 
            columnWidth: '50%',
            distributed: false,
            dataLabels: { position: 'top' } 
        } 
    },
    colors: ['#f97316'], // orange-500
    dataLabels: { 
        enabled: true, 
        formatter: (val) => val > 0 ? val : '',
        style: { colors: ['#475569'], fontSize: '10px', fontWeight: 600 },
        offsetY: -20
    },
    xaxis: { 
        categories: (props.salesByMonth || []).map(item => item.label), 
        labels: { style: { colors: '#6b7280', fontSize: '10px' } } 
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
        y: {
            formatter: (val) => `${val} ta xonadon sotilgan`
        }
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
            
            <!-- Full-Width Responsive Building Cards Grid -->
            <div class="grid grid-cols-1 xl:grid-cols-2 2xl:grid-cols-2 gap-6 w-full">
                <div 
                    v-for="building in filteredBuildingsSummary" 
                    :key="building.id" 
                    class="w-full bg-white rounded-3xl border border-gray-100 p-6 sm:p-7 flex flex-col justify-between shadow-sm hover:shadow-md transition-all duration-300"
                >
                    <!-- Top Header & Progress -->
                    <div>
                        <div class="flex items-center justify-between gap-4 mb-4">
                            <div class="flex items-center gap-3.5">
                                <div class="w-13 h-13 rounded-2xl overflow-hidden bg-gray-50 border border-gray-100 shrink-0 shadow-sm flex items-center justify-center">
                                    <img v-if="building.photo_path" :src="'/storage/' + building.photo_path" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full bg-gradient-to-tr from-amber-500/10 to-yellow-500/5 flex items-center justify-center text-amber-500">
                                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.33l-7.5-5-7.5 5V21" />
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-gray-900 font-extrabold text-xl tracking-tight leading-tight">{{ building.name }}</h4>
                                    <p class="text-xs text-gray-500 font-medium mt-0.5">Majmua umumiy ko'rsatkichlari</p>
                                </div>
                            </div>

                            <span class="px-3.5 py-1.5 rounded-full bg-amber-50 border border-amber-200 text-amber-800 font-extrabold text-xs">
                                {{ building.total_units }} ta obyekt
                            </span>
                        </div>

                        <!-- Progress bar -->
                        <div class="mb-5 bg-gray-50/60 p-3 rounded-2xl border border-gray-100">
                            <div class="flex justify-between text-xs font-bold uppercase tracking-wider mb-1.5">
                                <span class="text-gray-500">Sotuv darajasi</span>
                                <span class="text-amber-600 font-extrabold">
                                    {{ building.sold_units }} / {{ building.total_units }} ta
                                    <span class="ml-1 text-xs">({{ (building.total_units > 0 ? (building.sold_units / building.total_units * 100) : 0).toFixed(1) }}%)</span>
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                <div class="bg-gradient-to-r from-amber-400 to-amber-500 h-2.5 rounded-full transition-all duration-500" :style="{ width: (building.total_units > 0 ? (building.sold_units / building.total_units * 100) : 0) + '%' }"></div>
                            </div>
                        </div>

                        <!-- 2-Column Internal Stats Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                            
                            <!-- LEFT COLUMN: Obyektlar Tarkibi & Xonalar Sotuv Holati -->
                            <div class="space-y-4">
                                <!-- Obyektlar Tarkibi -->
                                <div class="bg-slate-50/90 p-3.5 rounded-2xl border border-gray-100 space-y-2">
                                    <p class="text-xs text-gray-500 font-extrabold uppercase tracking-wider">Obyektlar Tarkibi</p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="bg-white p-2.5 rounded-xl border border-gray-100 text-center shadow-2xs">
                                            <span class="text-base">🏠</span>
                                            <p class="text-sm font-black text-gray-900 leading-tight mt-0.5">{{ building.residential_count || 0 }}</p>
                                            <p class="text-[11px] text-gray-500 font-semibold">Turar-joy</p>
                                        </div>
                                        <div class="bg-white p-2.5 rounded-xl border border-gray-100 text-center shadow-2xs">
                                            <span class="text-base">🏬</span>
                                            <p class="text-sm font-black text-gray-900 leading-tight mt-0.5">{{ building.commercial_count || 0 }}</p>
                                            <p class="text-[11px] text-gray-500 font-semibold">Do'kon</p>
                                        </div>
                                        <div class="bg-white p-2.5 rounded-xl border border-gray-100 text-center shadow-2xs">
                                            <span class="text-base">🚗</span>
                                            <p class="text-sm font-black text-gray-900 leading-tight mt-0.5">{{ building.parking_count || 0 }}</p>
                                            <p class="text-[11px] text-gray-500 font-semibold">Parking</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Xonalar Sotuv Holati -->
                                <div class="bg-white p-3.5 rounded-2xl border border-gray-100 space-y-2">
                                    <p class="text-xs text-gray-500 font-extrabold uppercase tracking-wider">Sotuv Holati (Xonalar)</p>
                                    
                                    <div class="flex justify-between items-center text-xs border-b border-gray-50 pb-1.5">
                                        <span class="text-gray-600 font-medium">Sotilgan:</span>
                                        <span class="text-gray-900 font-bold">
                                            {{ building.sold_units }} 
                                            <span class="text-amber-500 font-bold ml-1">({{ (building.total_units > 0 ? (building.sold_units / building.total_units * 100) : 0).toFixed(1) }}%)</span>
                                        </span>
                                    </div>

                                    <div class="flex justify-between items-center text-xs border-b border-gray-50 pb-1.5">
                                        <span class="text-gray-600 font-medium">Bron qilingan:</span>
                                        <span class="text-gray-900 font-bold">
                                            {{ building.reserved_units }} 
                                            <span class="text-amber-500 font-bold ml-1">({{ (building.total_units > 0 ? (building.reserved_units / building.total_units * 100) : 0).toFixed(1) }}%)</span>
                                        </span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center text-xs border-b border-gray-50 pb-1.5">
                                        <span class="text-gray-600 font-medium">Sotuvda mavjud:</span>
                                        <span class="text-gray-900 font-bold">
                                            {{ building.available_units }}
                                            <span class="text-amber-500 font-bold ml-1">({{ (building.total_units > 0 ? (building.available_units / building.total_units * 100) : 0).toFixed(1) }}%)</span>
                                        </span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center text-xs pt-1">
                                        <span class="text-gray-800 font-bold">Jami:</span>
                                        <span class="text-gray-900 font-black text-sm">{{ building.total_units }} ta</span>
                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN: Maydon & Xonalar Taqsimoti -->
                            <div class="space-y-4">
                                <!-- Maydon (m2) -->
                                <div class="bg-white p-3.5 rounded-2xl border border-gray-100 space-y-2">
                                    <p class="text-xs text-gray-500 font-extrabold uppercase tracking-wider">Maydon Taqsimoti (M²)</p>
                                    
                                    <div class="flex justify-between items-center text-xs border-b border-gray-50 pb-1.5">
                                        <span class="text-gray-600 font-medium">Sotilgan maydon:</span>
                                        <span class="text-gray-900 font-bold">{{ building.sold_area }} m²</span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center text-xs border-b border-gray-50 pb-1.5">
                                        <span class="text-gray-600 font-medium">Mavjud maydon:</span>
                                        <span class="text-gray-900 font-bold">{{ building.available_area }} m²</span>
                                    </div>

                                    <div class="flex justify-between items-center text-xs pt-1">
                                        <span class="text-gray-800 font-bold">Jami maydon:</span>
                                        <span class="text-gray-900 font-black text-sm">{{ building.total_area }} m²</span>
                                    </div>
                                </div>

                                <!-- Xonalar Taqsimoti (1, 2, 3 xonali) -->
                                <div class="bg-slate-50/90 p-3.5 rounded-2xl border border-gray-100 space-y-2">
                                    <p class="text-xs text-gray-500 font-extrabold uppercase tracking-wider">Xonalar Soni Bo'yicha</p>
                                    
                                    <div v-if="building.rooms_distribution && building.rooms_distribution.length > 0" class="flex flex-wrap gap-2 pt-0.5">
                                        <div v-for="rd in building.rooms_distribution" :key="rd.rooms" class="flex-1 min-w-[75px] bg-white border border-gray-100 rounded-xl p-2 text-center shadow-2xs">
                                            <p class="text-base font-black text-gray-900 leading-none mb-1">{{ rd.count }}</p>
                                            <p class="text-[11px] text-gray-500 font-bold leading-none">{{ rd.rooms }}-xonali</p>
                                        </div>
                                    </div>
                                    <div v-else class="text-center py-2 text-gray-400 font-medium text-xs">
                                        Ma'lumot mavjud emas
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Bottom Financial Banner -->
                    <div class="pt-4 border-t border-gray-100 bg-gradient-to-r from-gray-50/80 via-slate-50/50 to-gray-50/80 p-4 rounded-2xl grid grid-cols-1 sm:grid-cols-3 gap-3 text-center sm:text-left">
                        <div>
                            <span class="text-gray-500 uppercase text-[11px] font-bold block mb-0.5">Sotilgan Summa</span>
                            <span class="text-emerald-600 font-black text-sm sm:text-base font-mono">{{ $currency(building.sold_sum) }}</span>
                        </div>
                        
                        <div>
                            <span class="text-gray-500 uppercase text-[11px] font-bold block mb-0.5">Qarzdorlik</span>
                            <span class="text-red-500 font-black text-sm sm:text-base font-mono">{{ $currency(building.debt_sum) }}</span>
                        </div>
                        
                        <div class="sm:text-right">
                            <span class="text-gray-500 uppercase text-[11px] font-bold block mb-0.5">Umumiy Qiymat</span>
                            <span class="text-gray-900 font-black text-sm sm:text-base font-mono">{{ $currency(building.total_sum) }}</span>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ANALITIKA SECTION -->
        <div class="mt-8 mb-8">
            <h3 class="text-2xl font-bold text-gray-900 tracking-tight">Analitika</h3>
            <p class="text-xs text-gray-400 mt-1 font-medium">Binolaringizning umumiy moliyaviy ko'rsatkichlari</p>
            
            <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm mt-6 flex flex-col">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-lg font-bold text-gray-800 tracking-tight">Kirim / Chiqim</h4>
                </div>
                
                <!-- Tab headers -->
                <div class="flex items-center gap-6 border-b border-gray-100 mb-6">
                    <button 
                        @click="activeTab = 'buildings'" 
                        :class="activeTab === 'buildings' ? 'text-gray-900 border-amber-500 font-bold' : 'text-gray-400 border-transparent hover:text-gray-600'" 
                        class="pb-3 text-sm font-semibold border-b-2 transition-all duration-200"
                    >
                        Obyektlar bo'yicha
                    </button>
                    <button 
                        @click="activeTab = 'months'" 
                        :class="activeTab === 'months' ? 'text-gray-900 border-amber-500 font-bold' : 'text-gray-400 border-transparent hover:text-gray-600'" 
                        class="pb-3 text-sm font-semibold border-b-2 transition-all duration-200"
                    >
                        Oylar bo'yicha
                    </button>
                </div>
                
                <!-- Chart -->
                <div class="h-[350px] w-full">
                    <VueApexCharts type="bar" height="100%" :options="kirimChiqimOptions" :series="kirimChiqimSeries" />
                </div>
            </div>
        </div>

        <!-- OYLIK SOTUVLAR STATISTIKASI -->
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm h-[400px] flex flex-col mb-8">
            <h3 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-4 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-orange-500"></span> Oylik sotuvlar statistikasi
            </h3>
            <div class="flex-1 min-h-0 w-full">
                <VueApexCharts type="bar" height="100%" width="100%" :options="monthlySalesOptions" :series="monthlySalesSeries" />
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
