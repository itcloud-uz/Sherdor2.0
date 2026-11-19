<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    branding: Object,
    promoSettings: Object,
    availableUnits: {
        type: Array,
        default: () => []
    },
    buildings: {
        type: Array,
        default: () => []
    }
});

// Navigation Active View Mode ('catalog' | 'apply')
const activeView = ref('catalog');

// Search & Filter States
const selectedType = ref('all'); // 'all' | 'residential' | 'commercial'
const selectedBlock = ref('all'); // 'all' | '1 BLOK' | '2 BLOK' ...
const selectedRooms = ref('all'); // 'all' | '1' | '2' | '3' | '4'
const selectedFloorRange = ref('all'); // 'all' | 'low' | 'mid' | 'high'
const searchQuery = ref('');
const sortBy = ref('default');

// Modal States for 3D & Blueprint Inspection
const selectedUnit = ref(null);
const activeDetailTab = ref('3d'); // '3d' | 'blueprint' | 'calc'
const isImageModalOpen = ref(false);
const modalPreviewImage = ref('');

// Installment Calculator in Modal & Checkout
const paymentType = ref('installment'); // 'installment' | 'full'
const downPaymentPercent = ref(30); // 20 | 30 | 50
const paymentMonths = ref(36); // 12 | 24 | 36 | 48

// Format money helper
const formatPrice = (val) => {
    if (!val || isNaN(val)) return '0';
    return Math.round(val).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
};

// Available unique blocks from units
const availableBlocks = computed(() => {
    const blocks = new Set();
    props.availableUnits.forEach(u => {
        if (u.block_name) blocks.add(u.block_name);
    });
    return Array.from(blocks).sort();
});

// Lead Submission Form
const uzbekistanRegions = [
    'Toshkent shahri', 'Toshkent viloyati', 'Samarqand viloyati', 'Andijon viloyati',
    'Buxoro viloyati', 'Farg\'ona viloyati', 'Jizzax viloyati', 'Xorazm viloyati',
    'Namangan viloyati', 'Navoiy viloyati', 'Qashqadaryo viloyati', 'Qoraqalpog\'iston Respublikasi',
    'Sirdaryo viloyati', 'Surxondaryo viloyati'
];

const form = ref({
    full_name: '',
    phone: '',
    region: 'Samarqand viloyati',
    district: 'Urgut tumani',
    comment: '',
    selected_unit_id: null,
    source: 'public_catalog',
    payment_type: 'installment',
    down_payment_percent: 30,
    term_months: 36
});

const isSubmitting = ref(false);
const successMessage = ref('');
const submittedLeadInfo = ref(null);
const errorMessage = ref('');

// Filtered Units Computed
const filteredUnits = computed(() => {
    let units = [...props.availableUnits];

    // Filter by type (residential vs commercial)
    if (selectedType.value !== 'all') {
        units = units.filter(u => u.type === selectedType.value);
    }

    // Filter by block
    if (selectedBlock.value !== 'all') {
        units = units.filter(u => u.block_name === selectedBlock.value);
    }

    // Filter by rooms
    if (selectedRooms.value !== 'all') {
        const r = parseInt(selectedRooms.value);
        if (r === 4) {
            units = units.filter(u => u.rooms >= 4);
        } else {
            units = units.filter(u => u.rooms === r);
        }
    }

    // Filter by floor
    if (selectedFloorRange.value === 'low') {
        units = units.filter(u => parseInt(u.floor_level) <= 3);
    } else if (selectedFloorRange.value === 'mid') {
        units = units.filter(u => parseInt(u.floor_level) >= 4 && parseInt(u.floor_level) <= 6);
    } else if (selectedFloorRange.value === 'high') {
        units = units.filter(u => parseInt(u.floor_level) >= 7);
    }

    // Filter by search
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        units = units.filter(u => 
            (u.building_name && u.building_name.toLowerCase().includes(q)) ||
            (u.unit_number && u.unit_number.toString().toLowerCase().includes(q)) ||
            (u.block_name && u.block_name.toLowerCase().includes(q)) ||
            (u.area && u.area.toString().includes(q))
        );
    }

    // Sort
    if (sortBy.value === 'price_asc') {
        units.sort((a, b) => a.price - b.price);
    } else if (sortBy.value === 'price_desc') {
        units.sort((a, b) => b.price - a.price);
    } else if (sortBy.value === 'area_asc') {
        units.sort((a, b) => a.area - b.area);
    } else if (sortBy.value === 'area_desc') {
        units.sort((a, b) => b.area - a.area);
    } else if (sortBy.value === 'floor_asc') {
        units.sort((a, b) => a.floor_level - b.floor_level);
    }

    return units;
});

// Currently selected unit object for checkout summary
const activeSelectedUnit = computed(() => {
    if (!form.value.selected_unit_id) return null;
    return props.availableUnits.find(u => u.id === form.value.selected_unit_id) || null;
});

// Calculator calculations for selected unit
const calcDownPayment = computed(() => {
    const unit = selectedUnit.value || activeSelectedUnit.value;
    if (!unit || !unit.price) return 0;
    return (unit.price * downPaymentPercent.value) / 100;
});

const calcBalance = computed(() => {
    const unit = selectedUnit.value || activeSelectedUnit.value;
    if (!unit || !unit.price) return 0;
    return unit.price - calcDownPayment.value;
});

const calcMonthlyPayment = computed(() => {
    if (!calcBalance.value || !paymentMonths.value) return 0;
    return Math.round(calcBalance.value / paymentMonths.value);
});

// Open 3D & Blueprint Modal
const openUnitDetails = (unit) => {
    selectedUnit.value = unit;
    if (unit.model_3d_path) {
        activeDetailTab.value = '3d';
    } else if (unit.blueprint_path) {
        activeDetailTab.value = 'blueprint';
    } else {
        activeDetailTab.value = 'calc';
    }
};

// Select Unit and Proceed to Apply Tab
const selectAndApply = (unit) => {
    form.value.selected_unit_id = unit.id;
    form.value.payment_type = paymentType.value;
    form.value.down_payment_percent = downPaymentPercent.value;
    form.value.term_months = paymentMonths.value;
    
    const unitLabel = unit.type === 'commercial' ? `№${unit.unit_number}-do'kon` : `№${unit.unit_number}-xonadon (${unit.rooms} xona)`;
    const pTypeStr = paymentType.value === 'full' ? "100% to'liq to'lov" : `Bo'lib to'lash (${paymentMonths.value} oy, boshlang'ich ${downPaymentPercent.value}%)`;
    
    form.value.comment = `Tanlangan: ${unit.building_name} (${unit.block_name}), ${unit.floor_level}-qavat, ${unitLabel}, ${unit.area} m². Umumiy narx: ${formatPrice(unit.price)} so'm. To'lov rejasi: ${pTypeStr}.`;
    
    selectedUnit.value = null;
    activeView.value = 'apply';
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// Submit Lead
const submitLead = async () => {
    isSubmitting.value = true;
    errorMessage.value = '';
    
    try {
        const response = await axios.post(route('api.leads.store'), form.value);
        submittedLeadInfo.value = {
            unit: activeSelectedUnit.value,
            fullName: form.value.full_name,
            phone: form.value.phone,
            paymentType: form.value.payment_type,
            monthly: calcMonthlyPayment.value,
            downPayment: calcDownPayment.value,
        };
        successMessage.value = props.promoSettings?.promo_success_message || response.data.message || 'Arizangiz muvaffaqiyatli qabul qilindi!';
        
        if (props.promoSettings?.fb_pixel_id && window.fbq) {
            window.fbq('track', 'Lead');
        }
        
        form.value.full_name = '';
        form.value.phone = '';
        form.value.comment = '';
    } catch (error) {
        if (error.response?.data?.errors) {
            errorMessage.value = Object.values(error.response.data.errors).flat().join('\n');
        } else {
            errorMessage.value = error.response?.data?.message || 'Xatolik yuz berdi. Iltimos keyinroq urinib ko\'ring.';
        }
    } finally {
        isSubmitting.value = false;
    }
};

const openImagePreview = (src) => {
    modalPreviewImage.value = src;
    isImageModalOpen.value = true;
};

onMounted(() => {
    if (props.promoSettings?.fb_pixel_id) {
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        
        fbq('init', props.promoSettings.fb_pixel_id);
        fbq('track', 'PageView');
    }
});
</script>

<template>
    <Head title="Xonadonlar Katalogi, 3D Ko'rinish & Xarid - Sher-Dor" />
    
    <div class="min-h-screen bg-[#f8fafc] text-slate-800 font-sans selection:bg-orange-500 selection:text-white">
        
        <!-- Sticky Header with Navigation -->
        <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                
                <!-- Logo & Brand -->
                <Link :href="route('landing')" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-xl bg-[#0f172a] flex items-center justify-center p-0.5 shadow-sm group-hover:scale-105 transition-transform">
                        <span class="font-black text-sm text-orange-500">IQS</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xl font-black tracking-tight text-[#0f172a] flex items-center gap-1.5">
                            {{ $page.props.branding?.app_name || 'Sher-Dor' }}
                        </span>
                        <span class="text-[10px] text-slate-500 font-semibold tracking-wider uppercase">Onlayn Tanlash, 3D Reja va Xarid</span>
                    </div>
                </Link>

                <!-- Desktop / Tablet View Mode Tabs -->
                <div class="hidden sm:flex items-center p-1 rounded-xl bg-slate-100 border border-slate-200">
                    <button 
                        @click="activeView = 'catalog'"
                        :class="activeView === 'catalog' ? 'bg-orange-500 text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                        class="px-5 py-2 rounded-lg text-xs sm:text-sm transition-all flex items-center gap-2"
                    >
                        <span>🏢</span> Xonadonlar Katalogi
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeView === 'catalog' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
                            {{ availableUnits.length }}
                        </span>
                    </button>

                    <button 
                        @click="activeView = 'apply'"
                        :class="activeView === 'apply' ? 'bg-[#0f172a] text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                        class="px-5 py-2 rounded-lg text-xs sm:text-sm transition-all flex items-center gap-2"
                    >
                        <span>📝</span> Ariza & Xarid Qilish
                        <span v-if="activeSelectedUnit" class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    </button>
                </div>

                <!-- Back to Landing Link -->
                <Link :href="route('landing')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-700 text-xs sm:text-sm font-semibold transition-all flex items-center gap-1.5">
                    <span>←</span> Bosh sahifa
                </Link>
            </div>

            <!-- Mobile View Switcher Tab Bar -->
            <div class="sm:hidden flex border-t border-slate-200 bg-white">
                <button 
                    @click="activeView = 'catalog'"
                    :class="activeView === 'catalog' ? 'text-orange-600 border-b-2 border-orange-500 font-bold bg-orange-50/50' : 'text-slate-600'"
                    class="flex-1 py-3 text-xs text-center transition-all flex items-center justify-center gap-1.5"
                >
                    <span>🏢</span> Katalog ({{ filteredUnits.length }})
                </button>
                <button 
                    @click="activeView = 'apply'"
                    :class="activeView === 'apply' ? 'text-[#0f172a] border-b-2 border-[#0f172a] font-bold bg-slate-50' : 'text-slate-600'"
                    class="flex-1 py-3 text-xs text-center transition-all flex items-center justify-center gap-1.5"
                >
                    <span>📝</span> Ariza & Xarid
                    <span v-if="activeSelectedUnit" class="w-2 h-2 rounded-full bg-orange-500"></span>
                </button>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-10">

            <!-- ========================================================= -->
            <!-- TAB 1: XONADONLAR KATALOGI (APARTMENT CATALOG & 3D) -->
            <!-- ========================================================= -->
            <div v-show="activeView === 'catalog'" class="space-y-6 md:space-y-8">
                
                <!-- Hero Header Banner -->
                <div class="p-6 md:p-10 rounded-3xl bg-white border border-slate-200 shadow-sm relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="max-w-2xl relative z-10">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-orange-50 border border-orange-200 text-orange-600 text-xs font-bold uppercase tracking-wider mb-3">
                            <span class="w-2 h-2 rounded-full bg-orange-500"></span> Sotuvdagi Rasmiy Xonadonlar va Do'konlar
                        </div>
                        <h1 class="text-3xl sm:text-4xl md:text-5xl font-black tracking-tight text-[#0f172a] mb-3">
                            O'zingizga Mos Xonadonni <span class="text-orange-500">3D Rejada Tanlang!</span>
                        </h1>
                        <p class="text-slate-600 text-sm sm:text-base leading-relaxed">
                            Barcha mavjud xonadon va do'konlarning 3D loyihasi, arxitektura chizmasi, xonalar kvadraturasi va qulay bo'lib to'lash shartlari bilan to'liq tanishing.
                        </p>
                    </div>

                    <!-- Quick Stats Pill Card -->
                    <div class="grid grid-cols-2 gap-3 bg-slate-50 border border-slate-200 p-4 rounded-2xl shrink-0">
                        <div class="text-center p-2 bg-white rounded-xl border border-slate-100">
                            <span class="text-2xl font-black text-orange-600">{{ availableUnits.length }}</span>
                            <span class="block text-[11px] font-bold text-slate-500 uppercase mt-0.5">Sotuvda mavjud</span>
                        </div>
                        <div class="text-center p-2 bg-white rounded-xl border border-slate-100">
                            <span class="text-2xl font-black text-[#0f172a]">{{ availableBlocks.length }}</span>
                            <span class="block text-[11px] font-bold text-slate-500 uppercase mt-0.5">Turar-joy Bloki</span>
                        </div>
                    </div>
                </div>

                <!-- Comprehensive Filters & Search Toolbar -->
                <div class="p-4 sm:p-6 rounded-2xl bg-white border border-slate-200 shadow-sm space-y-4">
                    
                    <!-- Row 1: Property Type & Block Selector -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        
                        <!-- Type: Residential vs Commercial -->
                        <div class="flex items-center p-1 rounded-xl bg-slate-100 border border-slate-200 text-xs font-bold">
                            <button 
                                @click="selectedType = 'all'"
                                :class="selectedType === 'all' ? 'bg-white text-[#0f172a] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-3.5 py-1.5 rounded-lg transition-all"
                            >
                                Barchasi
                            </button>
                            <button 
                                @click="selectedType = 'residential'"
                                :class="selectedType === 'residential' ? 'bg-orange-500 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-1"
                            >
                                <span>🏢</span> Xonadonlar (Uylar)
                            </button>
                            <button 
                                @click="selectedType = 'commercial'"
                                :class="selectedType === 'commercial' ? 'bg-[#0f172a] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-1"
                            >
                                <span>🏬</span> Tijorat / Do'konlar
                            </button>
                        </div>

                        <!-- Blocks Filter Pills -->
                        <div class="flex items-center gap-1.5 overflow-x-auto text-xs">
                            <span class="font-bold text-slate-500 uppercase mr-1">Blok:</span>
                            <button 
                                @click="selectedBlock = 'all'"
                                :class="selectedBlock === 'all' ? 'bg-[#0f172a] text-white font-bold' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                class="px-3 py-1.5 rounded-lg font-medium transition-all"
                            >
                                Barchasi
                            </button>
                            <button 
                                v-for="b in availableBlocks" 
                                :key="b"
                                @click="selectedBlock = b"
                                :class="selectedBlock === b ? 'bg-orange-500 text-white font-bold' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                class="px-3 py-1.5 rounded-lg font-medium transition-all"
                            >
                                {{ b }}
                            </button>
                        </div>

                    </div>

                    <!-- Row 2: Rooms Buttons & Search Bar -->
                    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                        
                        <!-- Rooms Filter Buttons -->
                        <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto w-full md:w-auto pb-1 md:pb-0">
                            <span class="text-xs text-slate-500 font-bold uppercase tracking-wider mr-1 hidden sm:inline">Xonalar:</span>
                            <button 
                                v-for="r in ['all', '1', '2', '3', '4']" 
                                :key="r"
                                @click="selectedRooms = r"
                                :class="selectedRooms === r ? 'bg-orange-500 text-white font-bold shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200 font-medium'"
                                class="px-3.5 py-2 rounded-xl text-xs sm:text-sm transition-all whitespace-nowrap"
                            >
                                {{ r === 'all' ? 'Barcha xonalar' : (r === '4' ? '4+ xona' : r + ' xona') }}
                            </button>
                        </div>

                        <!-- Search Bar -->
                        <div class="w-full md:w-80 relative">
                            <input 
                                v-model="searchQuery" 
                                type="text" 
                                placeholder="Xonadon №, blok yoki maydon..." 
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 placeholder-slate-400 focus:border-orange-500 focus:bg-white focus:outline-none transition-all"
                            />
                            <span class="absolute left-3.5 top-2.5 text-slate-400 text-sm">🔍</span>
                        </div>

                    </div>

                    <!-- Row 3: Floor, Sort & Count -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 text-xs text-slate-600">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">Qavat:</span>
                            <select v-model="selectedFloorRange" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-slate-800 font-medium focus:border-orange-500 focus:bg-white focus:outline-none">
                                <option value="all">Barcha qavatlar</option>
                                <option value="low">1 - 3 qavat</option>
                                <option value="mid">4 - 6 qavat</option>
                                <option value="high">7+ qavat</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="font-medium">Saralash:</span>
                            <select v-model="sortBy" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-slate-800 font-medium focus:border-orange-500 focus:bg-white focus:outline-none">
                                <option value="default">Odatiy (Tartib raqami)</option>
                                <option value="price_asc">Narxi (arzondan qimmatga)</option>
                                <option value="price_desc">Narxi (qimmatdan arzonga)</option>
                                <option value="area_asc">Maydoni (kichikdan kattaga)</option>
                                <option value="area_desc">Maydoni (kattadan kichikka)</option>
                                <option value="floor_asc">Qavati bo'yicha</option>
                            </select>
                        </div>

                        <div class="text-[#0f172a] font-bold">
                            Mavjud variantlar: <span class="text-orange-600 font-mono text-sm">{{ filteredUnits.length }}</span> ta
                        </div>
                    </div>

                </div>

                <!-- Apartment & Commercial Cards Grid -->
                <div v-if="filteredUnits.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    <div 
                        v-for="unit in filteredUnits" 
                        :key="unit.id" 
                        class="p-5 sm:p-6 rounded-3xl bg-white border border-slate-200 hover:border-orange-400 hover:shadow-xl transition-all duration-300 flex flex-col justify-between group"
                    >
                        <div>
                            <!-- Top Badges -->
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Sotuvda Mavjud
                                    </span>
                                    <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200 text-[10px] font-bold font-mono">
                                        {{ unit.block_name }}
                                    </span>
                                    <span v-if="unit.type === 'commercial'" class="px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 border border-purple-200 text-[10px] font-bold">
                                        Do'kon
                                    </span>
                                </div>
                                <span class="text-xs font-mono font-semibold text-slate-500">{{ unit.floor_level }}-qavat</span>
                            </div>

                            <!-- Real 3D Model / Blueprint Thumbnail Preview -->
                            <div 
                                @click="openUnitDetails(unit)"
                                class="w-full h-48 rounded-2xl bg-slate-50 border border-slate-200 p-2 mb-4 flex items-center justify-center cursor-pointer group-hover:border-orange-400 transition-all relative overflow-hidden group/img"
                            >
                                <!-- Real 3D Model Image -->
                                <img 
                                    v-if="unit.model_3d_path" 
                                    :src="unit.model_3d_path" 
                                    alt="3D Reja" 
                                    class="w-full h-full object-contain rounded-xl group-hover/img:scale-105 transition-transform duration-300"
                                    loading="lazy"
                                />

                                <!-- Fallback to Blueprint Image if 3D is not set -->
                                <img 
                                    v-else-if="unit.blueprint_path" 
                                    :src="unit.blueprint_path" 
                                    alt="Arxitektura chizmasi" 
                                    class="w-full h-full object-contain rounded-xl group-hover/img:scale-105 transition-transform duration-300"
                                    loading="lazy"
                                />

                                <!-- Clean SVG Floor Plan Placeholder if no files -->
                                <div v-else class="w-full h-full flex flex-col items-center justify-center text-slate-400 p-4">
                                    <svg viewBox="0 0 200 120" class="w-28 h-20 text-slate-300 stroke-current" fill="none" stroke-width="2">
                                        <rect x="10" y="10" width="180" height="100" rx="6" stroke-dasharray="4 2" />
                                        <line x1="100" y1="10" x2="100" y2="110" />
                                        <line x1="100" y1="60" x2="190" y2="60" />
                                    </svg>
                                    <span class="text-[11px] font-semibold text-slate-500 mt-1">Standart Loyiha Rejasi</span>
                                </div>

                                <!-- Overlay Badge -->
                                <div class="absolute bottom-2 right-2 px-2.5 py-1 rounded-lg bg-white/95 backdrop-blur-xs text-[10px] font-bold text-slate-800 border border-slate-200 shadow-xs flex items-center gap-1 group-hover/img:text-orange-600 transition-colors">
                                    <span>🔍</span> 3D & Chizmani Ko'rish
                                </div>

                                <div v-if="unit.model_3d_path" class="absolute top-2 left-2 px-2 py-0.5 rounded-md bg-orange-500 text-white text-[9px] font-extrabold uppercase tracking-wider">
                                    3D Model
                                </div>
                            </div>

                            <!-- Title & Square Meters -->
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h3 class="text-xl font-black text-[#0f172a] flex items-center gap-1.5">
                                        №{{ unit.unit_number }} {{ unit.type === 'commercial' ? "Do'kon" : "Xonadon" }}
                                    </h3>
                                    <span class="text-xs text-slate-500 font-medium">{{ unit.building_name }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-orange-600 font-black text-xl block leading-tight">{{ unit.area }} m²</span>
                                    <span class="text-[11px] text-slate-400 font-semibold font-mono">{{ unit.type === 'commercial' ? 'Tijorat maydoni' : unit.rooms + ' xona' }}</span>
                                </div>
                            </div>

                            <!-- Specs Grid -->
                            <div class="grid grid-cols-2 gap-2 text-xs text-slate-700 mb-4">
                                <div class="p-2 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2 font-medium">
                                    <span>🛋️</span> {{ unit.type === 'commercial' ? "Savdo maydoni" : unit.rooms + " xonali" }}
                                </div>
                                <div class="p-2 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2 font-medium">
                                    <span>🏢</span> {{ unit.floor_level }}-qavat
                                </div>
                                <div class="p-2 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2 font-medium">
                                    <span>🧭</span> {{ unit.orientation }}
                                </div>
                                <div class="p-2 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2 font-medium">
                                    <span>🌅</span> {{ unit.has_balcony ? 'Balkonli' : 'Balkonsiz' }}
                                </div>
                            </div>
                        </div>

                        <!-- Price & Action Section -->
                        <div class="pt-4 border-t border-slate-100">
                            
                            <!-- Price breakdown -->
                            <div class="mb-4 bg-orange-50/60 border border-orange-100 p-3 rounded-2xl flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] font-bold uppercase text-slate-500 tracking-wider block">Umumiy Qiymati</span>
                                    <span class="text-lg font-black text-[#0f172a]">{{ formatPrice(unit.price) }} <small class="text-xs font-semibold text-slate-600">so'm</small></span>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] font-bold text-slate-500 block">1 m² uchun:</span>
                                    <span class="text-xs font-extrabold text-orange-600 font-mono">{{ formatPrice(unit.price_per_sqm) }} so'm</span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="grid grid-cols-2 gap-2.5">
                                <button 
                                    @click="openUnitDetails(unit)"
                                    class="py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition-all flex items-center justify-center gap-1.5"
                                >
                                    <span>🔍</span> 3D & Reja
                                </button>

                                <button 
                                    @click="selectAndApply(unit)"
                                    class="py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-extrabold text-xs shadow-md shadow-orange-500/20 transition-all flex items-center justify-center gap-1"
                                >
                                    <span>✍️</span> Xarid Qilish
                                </button>
                            </div>

                        </div>

                    </div>

                </div>

                <!-- Empty State -->
                <div v-else class="p-12 rounded-3xl bg-white border border-slate-200 text-center space-y-4 shadow-sm">
                    <div class="text-5xl">🔍</div>
                    <h3 class="text-xl font-bold text-[#0f172a]">Mos xonadon yoki do'kon topilmadi</h3>
                    <p class="text-slate-500 text-sm max-w-md mx-auto">Filtrlarni o'zgartirib ko'ring yoki barcha xonadonlarni ko'rish uchun filtrlarni tozalang.</p>
                    <button @click="selectedType = 'all'; selectedBlock = 'all'; selectedRooms = 'all'; selectedFloorRange = 'all'; searchQuery = '';" class="px-6 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs shadow-xs transition-colors">
                        Barcha filtrlarni tozalash
                    </button>
                </div>

            </div>

            <!-- ========================================================= -->
            <!-- TAB 2: ARIZA QOLDIRISH & XARID JARAYONI (CHECKOUT LEAD FORM) -->
            <!-- ========================================================= -->
            <div v-show="activeView === 'apply'" class="max-w-3xl mx-auto space-y-6">
                
                <!-- Selected Unit Summary Card (If client came from catalog) -->
                <div v-if="activeSelectedUnit" class="p-5 sm:p-6 rounded-3xl bg-white border-2 border-orange-500/50 shadow-md flex flex-col sm:flex-row items-center gap-6">
                    
                    <div class="w-full sm:w-44 h-36 bg-slate-50 rounded-2xl border border-slate-200 p-2 flex items-center justify-center overflow-hidden shrink-0">
                        <img 
                            v-if="activeSelectedUnit.model_3d_path" 
                            :src="activeSelectedUnit.model_3d_path" 
                            alt="Selected 3D" 
                            class="w-full h-full object-contain cursor-pointer"
                            @click="openImagePreview(activeSelectedUnit.model_3d_path)"
                        />
                        <img 
                            v-else-if="activeSelectedUnit.blueprint_path" 
                            :src="activeSelectedUnit.blueprint_path" 
                            alt="Selected Blueprint" 
                            class="w-full h-full object-contain cursor-pointer"
                            @click="openImagePreview(activeSelectedUnit.blueprint_path)"
                        />
                        <div v-else class="text-slate-400 font-bold text-xs text-center">
                            📐 Standart Reja
                        </div>
                    </div>

                    <div class="flex-1 w-full space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-0.5 rounded-full bg-orange-100 text-orange-700 text-xs font-bold uppercase">
                                Tanlangan Mulk
                            </span>
                            <button @click="form.selected_unit_id = null; form.comment = '';" class="text-xs text-red-500 hover:underline font-semibold">
                                O'zgartirish ✕
                            </button>
                        </div>
                        
                        <h3 class="text-xl font-black text-[#0f172a]">
                            №{{ activeSelectedUnit.unit_number }} {{ activeSelectedUnit.type === 'commercial' ? "Do'kon" : "Xonadon" }} — {{ activeSelectedUnit.area }} m²
                        </h3>
                        <p class="text-xs text-slate-500 font-medium">
                            {{ activeSelectedUnit.building_name }} • {{ activeSelectedUnit.block_name }} • {{ activeSelectedUnit.floor_level }}-qavat • {{ activeSelectedUnit.rooms }} xonali
                        </p>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Qiymati:</span>
                                <span class="text-lg font-black text-orange-600">{{ formatPrice(activeSelectedUnit.price) }} so'm</span>
                            </div>
                            <button @click="openUnitDetails(activeSelectedUnit)" class="text-xs font-bold text-[#0f172a] hover:underline flex items-center gap-1">
                                🔍 3D & Rejani ko'rish
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Form Box -->
                <div class="p-6 sm:p-10 rounded-3xl bg-white border border-slate-200 shadow-lg relative">
                    
                    <!-- Form Header -->
                    <div class="text-center mb-8 relative z-10">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-orange-50 border border-orange-200 text-orange-600 text-xs font-bold uppercase tracking-wider mb-3">
                            <span>📝</span> Xarid & Rasmiylashtirish
                        </div>
                        <h2 class="text-2xl sm:text-4xl font-black text-[#0f172a] mb-2 tracking-tight">
                            Xonadonni Band Qilish va Xarid Qilish
                        </h2>
                        <p class="text-slate-600 text-xs sm:text-sm max-w-xl mx-auto">
                            Ma'lumotlaringizni qoldiring, mutaxassisimiz 15 daqiqa ichida siz bilan bog'lanib, shartnomani rasmiylashtiradi va eng qulay to'lov shartlarini kelishadi.
                        </p>
                    </div>

                    <!-- Success State -->
                    <div v-if="successMessage" class="p-8 rounded-2xl bg-emerald-50 border border-emerald-200 text-center space-y-4">
                        <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 text-3xl mx-auto flex items-center justify-center font-bold">
                            ✓
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900">Arizangiz Qabul Qilindi!</h3>
                        <p class="text-emerald-800 text-sm leading-relaxed max-w-md mx-auto">{{ successMessage }}</p>
                        
                        <div v-if="submittedLeadInfo?.unit" class="p-4 bg-white rounded-xl border border-emerald-200 text-xs text-left max-w-md mx-auto space-y-1">
                            <div class="font-bold text-[#0f172a]">Tanlangan mulk: №{{ submittedLeadInfo.unit.unit_number }} ({{ submittedLeadInfo.unit.block_name }})</div>
                            <div class="text-slate-600">Mijoz: {{ submittedLeadInfo.fullName }} ({{ submittedLeadInfo.phone }})</div>
                            <div class="text-slate-600">To'lov shakli: {{ submittedLeadInfo.paymentType === 'full' ? '100% to\'liq to\'lov' : 'Bo\'lib to\'lash' }}</div>
                        </div>

                        <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-3">
                            <button @click="successMessage = ''; activeView = 'catalog';" class="px-6 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs uppercase tracking-wider shadow-sm transition-colors">
                                Katalogga qaytish
                            </button>
                            <a v-if="$page.props.promoSettings?.phone" :href="'tel:' + $page.props.promoSettings.phone" class="px-6 py-2.5 rounded-xl bg-[#0f172a] text-white font-bold text-xs uppercase tracking-wider shadow-sm transition-colors flex items-center gap-1.5">
                                <span>📞</span> Savdo bo'limiga qo'ng'iroq
                            </a>
                        </div>
                    </div>

                    <!-- Application Form -->
                    <form v-else @submit.prevent="submitLead" class="space-y-5 relative z-10">
                        
                        <div v-if="errorMessage" class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold whitespace-pre-line">
                            {{ errorMessage }}
                        </div>

                        <!-- Full Name -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Ism va Familiyangiz *</label>
                            <input 
                                v-model="form.full_name" 
                                required 
                                type="text" 
                                placeholder="Masalan: Azizbek Rahimov"
                                class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 text-sm focus:border-orange-500 focus:bg-white focus:outline-none transition-all"
                            />
                        </div>

                        <!-- Phone Number -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Telefon Raqamingiz *</label>
                            <input 
                                v-model="form.phone" 
                                required 
                                type="tel" 
                                placeholder="+998 90 123 45 67"
                                class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 text-sm focus:border-orange-500 focus:bg-white focus:outline-none font-mono transition-all"
                            />
                        </div>

                        <!-- Region and District -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Viloyat</label>
                                <select 
                                    v-model="form.region" 
                                    class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 text-sm focus:border-orange-500 focus:bg-white focus:outline-none font-medium transition-all"
                                >
                                    <option v-for="r in uzbekistanRegions" :key="r" :value="r">{{ r }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tuman / Shahar</label>
                                <input 
                                    v-model="form.district" 
                                    type="text" 
                                    placeholder="Tumaningiz"
                                    class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 text-sm focus:border-orange-500 focus:bg-white focus:outline-none transition-all"
                                />
                            </div>
                        </div>

                        <!-- Payment Plan Selection -->
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                            <label class="block text-xs font-bold text-[#0f172a] uppercase tracking-wider">Xarid va To'lov Turi:</label>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <button 
                                    type="button" 
                                    @click="paymentType = 'full'; form.payment_type = 'full'"
                                    :class="paymentType === 'full' ? 'bg-[#0f172a] text-white font-bold border-[#0f172a] shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                                    class="p-3 rounded-xl border text-xs text-left transition-all"
                                >
                                    <span class="block font-extrabold text-sm mb-0.5">💰 100% To'liq To'lov</span>
                                    <span class="text-[11px] opacity-80">Maksimal chegirma va tezkor rasmiylashtirish</span>
                                </button>

                                <button 
                                    type="button" 
                                    @click="paymentType = 'installment'; form.payment_type = 'installment'"
                                    :class="paymentType === 'installment' ? 'bg-orange-500 text-white font-bold border-orange-500 shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                                    class="p-3 rounded-xl border text-xs text-left transition-all"
                                >
                                    <span class="block font-extrabold text-sm mb-0.5">🗓️ Bo'lib To'lash</span>
                                    <span class="text-[11px] opacity-80">36 oygacha 0% foizsiz muddatli to'lov</span>
                                </button>
                            </div>

                            <!-- Installment params preview if chosen -->
                            <div v-if="paymentType === 'installment' && activeSelectedUnit" class="pt-3 border-t border-slate-200 grid grid-cols-3 gap-2 text-center text-xs">
                                <div class="bg-white p-2 rounded-lg border border-slate-200">
                                    <span class="text-[10px] text-slate-400 block">Boshlang'ich:</span>
                                    <span class="font-bold text-orange-600">{{ formatPrice(calcDownPayment) }} so'm</span>
                                </div>
                                <div class="bg-white p-2 rounded-lg border border-slate-200">
                                    <span class="text-[10px] text-slate-400 block">Muddat:</span>
                                    <span class="font-bold text-slate-800">{{ paymentMonths }} oy</span>
                                </div>
                                <div class="bg-white p-2 rounded-lg border border-slate-200">
                                    <span class="text-[10px] text-slate-400 block">Oylik to'lov:</span>
                                    <span class="font-bold text-emerald-600">{{ formatPrice(calcMonthlyPayment) }} so'm</span>
                                </div>
                            </div>
                        </div>

                        <!-- Comment / Additional requests -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Qo'shimcha istaklar yoki Izoh</label>
                            <textarea 
                                v-model="form.comment" 
                                rows="3" 
                                placeholder="Qo'shimcha savollar yoki qulay to'lov jadvali bo'yicha takliflaringiz..."
                                class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 text-sm focus:border-orange-500 focus:bg-white focus:outline-none resize-none transition-all"
                            ></textarea>
                        </div>

                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            :disabled="isSubmitting"
                            class="w-full py-4 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-black text-sm sm:text-base uppercase tracking-wider shadow-lg shadow-orange-500/25 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            <span v-if="isSubmitting" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            {{ isSubmitting ? 'Rasmiylashtirilmoqda...' : 'Arizani Yuborish & Xaridni Tasdiqlash' }}
                        </button>

                        <p class="text-[11px] text-center text-slate-400">
                            🔒 Ma'lumotlaringiz xavfsiz saqlanadi va uchinchi shaxslarga berilmaydi.
                        </p>

                    </form>

                </div>

            </div>

        </main>

        <!-- ========================================================= -->
        <!-- ULTRA 3D & BLUEPRINT ARCHITECTURAL INSPECTOR MODAL -->
        <!-- ========================================================= -->
        <div v-if="selectedUnit" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
            
            <div class="relative w-full max-w-4xl rounded-3xl bg-white border border-slate-200 shadow-2xl flex flex-col max-h-[92vh] overflow-hidden text-slate-900">
                
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-orange-500 text-white flex items-center justify-center font-black text-sm shadow-xs">
                            3D
                        </div>
                        <div>
                            <h3 class="text-lg sm:text-xl font-bold text-[#0f172a] flex items-center gap-2">
                                №{{ selectedUnit.unit_number }} {{ selectedUnit.type === 'commercial' ? "Do'kon" : "Xonadon" }} — {{ selectedUnit.area }} m²
                            </h3>
                            <p class="text-xs text-slate-500">{{ selectedUnit.building_name }} • {{ selectedUnit.block_name }} • {{ selectedUnit.floor_level }}-qavat</p>
                        </div>
                    </div>

                    <button @click="selectedUnit = null" class="w-9 h-9 rounded-full bg-slate-200 hover:bg-slate-300 text-slate-700 flex items-center justify-center text-sm font-bold transition-colors">
                        ✕
                    </button>
                </div>

                <!-- Modal Body with Scroll -->
                <div class="p-6 overflow-y-auto space-y-6 flex-1">
                    
                    <!-- View Mode Toolbar inside Modal -->
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        
                        <div class="flex items-center p-1 rounded-xl bg-slate-100 border border-slate-200">
                            <button 
                                @click="activeDetailTab = '3d'"
                                :class="activeDetailTab === '3d' ? 'bg-orange-500 text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                                class="px-4 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5"
                            >
                                <span>🏢</span> 3D Ko'rinish
                            </button>
                            <button 
                                @click="activeDetailTab = 'blueprint'"
                                :class="activeDetailTab === 'blueprint' ? 'bg-[#0f172a] text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                                class="px-4 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5"
                            >
                                <span>📐</span> Arxitektura Chizmasi
                            </button>
                            <button 
                                @click="activeDetailTab = 'calc'"
                                :class="activeDetailTab === 'calc' ? 'bg-emerald-600 text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                                class="px-4 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5"
                            >
                                <span>🧮</span> Bo'lib To'lash Kalkulyatori
                            </button>
                        </div>

                        <!-- Price display in modal -->
                        <div class="text-right">
                            <span class="text-xs text-slate-400 block">Umumiy narxi:</span>
                            <span class="text-lg font-black text-orange-600">{{ formatPrice(selectedUnit.price) }} so'm</span>
                        </div>

                    </div>

                    <!-- TAB 1: REAL 3D VIEW SCREEN -->
                    <div v-show="activeDetailTab === '3d'" class="w-full min-h-[300px] sm:min-h-[400px] rounded-2xl bg-slate-50 border border-slate-200 p-4 flex flex-col items-center justify-center relative overflow-hidden group">
                        
                        <!-- High Resolution 3D Image -->
                        <div v-if="selectedUnit.model_3d_path" class="w-full h-full flex items-center justify-center">
                            <img 
                                :src="selectedUnit.model_3d_path" 
                                alt="3D Model" 
                                class="max-h-[55vh] max-w-full object-contain rounded-xl shadow-sm cursor-zoom-in hover:scale-105 transition-transform duration-300"
                                @click="openImagePreview(selectedUnit.model_3d_path)"
                            />
                        </div>

                        <!-- If no 3D image, fallback to blueprint image if available -->
                        <div v-else-if="selectedUnit.blueprint_path" class="w-full h-full flex flex-col items-center justify-center">
                            <img 
                                :src="selectedUnit.blueprint_path" 
                                alt="Arxitektura chizmasi" 
                                class="max-h-[55vh] max-w-full object-contain rounded-xl shadow-sm cursor-zoom-in"
                                @click="openImagePreview(selectedUnit.blueprint_path)"
                            />
                            <p class="text-xs text-slate-500 mt-2 font-medium">Ushbu xonadon uchun muhandislik chizmasi ko'rsatilmoqda</p>
                        </div>

                        <!-- Fallback Vector -->
                        <div v-else class="text-center p-8">
                            <div class="text-4xl mb-2">🏢</div>
                            <h4 class="font-bold text-slate-700">3D Rejalashtirish Tayyorlanmoqda</h4>
                            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Ushbu xonadon parametrlari: {{ selectedUnit.rooms }} xona, {{ selectedUnit.area }} m². Tafsilotlarni savdo bo'limidan olishingiz mumkin.</p>
                        </div>

                        <div class="absolute top-4 left-4 px-3 py-1 rounded-full bg-slate-900/80 text-white text-xs font-mono font-medium flex items-center gap-1.5">
                            <span>⚡</span> Real 3D Loyiha
                        </div>

                        <div v-if="selectedUnit.model_3d_path" class="absolute bottom-3 right-3">
                            <button @click="openImagePreview(selectedUnit.model_3d_path)" class="px-3 py-1.5 rounded-lg bg-white/90 border border-slate-200 text-xs font-bold text-slate-800 shadow-sm hover:bg-white flex items-center gap-1">
                                <span>🔍</span> Kattalashtirish
                            </button>
                        </div>
                    </div>

                    <!-- TAB 2: ARCHITECTURAL BLUEPRINT SCREEN -->
                    <div v-show="activeDetailTab === 'blueprint'" class="w-full p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col">
                        
                        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-200">
                            <span class="text-xs font-bold uppercase font-mono tracking-widest text-[#0f172a]">
                                📐 Muhandislik Chizmasi va Xonalar Bo'linishi
                            </span>
                            <span class="text-xs font-mono font-bold text-slate-500">
                                Masshtab 1:100 • Standart SNiP
                            </span>
                        </div>

                        <!-- Real Blueprint Image if Available -->
                        <div v-if="selectedUnit.blueprint_path" class="w-full min-h-[300px] flex items-center justify-center p-4 bg-slate-50 rounded-2xl border border-slate-200 mb-4">
                            <img 
                                :src="selectedUnit.blueprint_path" 
                                alt="Blueprint" 
                                class="max-h-[55vh] max-w-full object-contain rounded-xl shadow-xs cursor-zoom-in hover:scale-105 transition-transform duration-300"
                                @click="openImagePreview(selectedUnit.blueprint_path)"
                            />
                        </div>

                        <!-- Room measurements breakdown -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="text-[10px] uppercase font-bold text-slate-500">Mehmonxona</div>
                                <div class="text-sm font-black text-[#0f172a]">{{ (selectedUnit.area * 0.42).toFixed(1) }} m²</div>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="text-[10px] uppercase font-bold text-slate-500">Yotoqxona</div>
                                <div class="text-sm font-black text-[#0f172a]">{{ (selectedUnit.area * 0.33).toFixed(1) }} m²</div>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="text-[10px] uppercase font-bold text-slate-500">Oshxona</div>
                                <div class="text-sm font-black text-[#0f172a]">{{ (selectedUnit.area * 0.16).toFixed(1) }} m²</div>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="text-[10px] uppercase font-bold text-slate-500">Sanuzel / Balkon</div>
                                <div class="text-sm font-black text-[#0f172a]">{{ (selectedUnit.area * 0.09).toFixed(1) }} m²</div>
                            </div>
                        </div>

                    </div>

                    <!-- TAB 3: INSTALLMENT CALCULATOR (BO'LIB TO'LASH) -->
                    <div v-show="activeDetailTab === 'calc'" class="p-6 rounded-2xl bg-slate-50 border border-slate-200 space-y-6">
                        
                        <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                            <h4 class="text-base font-bold text-[#0f172a] flex items-center gap-2">
                                <span>🧮</span> Moslashuvchan Bo'lib To'lash Hisoblagichi
                            </h4>
                            <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-bold">
                                0% Ustamasiz
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Down Payment Options -->
                            <div>
                                <label class="block text-xs text-slate-600 mb-1.5 font-bold">Dastlabki to'lov miqdori (%):</label>
                                <div class="flex gap-2">
                                    <button 
                                        v-for="p in [20, 30, 50]" 
                                        :key="p"
                                        @click="downPaymentPercent = p"
                                        :class="downPaymentPercent === p ? 'bg-orange-500 text-white font-bold' : 'bg-white text-slate-700 hover:bg-slate-100 font-medium'"
                                        class="flex-1 py-2.5 rounded-xl text-xs border border-slate-200 transition-colors"
                                    >
                                        {{ p }}%
                                    </button>
                                </div>
                            </div>

                            <!-- Term Months Options -->
                            <div>
                                <label class="block text-xs text-slate-600 mb-1.5 font-bold">To'lov muddati (oy):</label>
                                <div class="flex gap-2">
                                    <button 
                                        v-for="m in [12, 24, 36, 48]" 
                                        :key="m"
                                        @click="paymentMonths = m"
                                        :class="paymentMonths === m ? 'bg-[#0f172a] text-white font-bold' : 'bg-white text-slate-700 hover:bg-slate-100 font-medium'"
                                        class="flex-1 py-2.5 rounded-xl text-xs border border-slate-200 transition-colors"
                                    >
                                        {{ m }} oy
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Live Calculation Breakdown Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="p-4 rounded-xl bg-white border border-slate-200">
                                <span class="text-slate-500 text-xs block mb-1">Boshlang'ich to'lov ({{ downPaymentPercent }}%):</span>
                                <span class="text-lg font-black text-orange-600">{{ formatPrice(calcDownPayment) }} so'm</span>
                            </div>

                            <div class="p-4 rounded-xl bg-white border border-slate-200">
                                <span class="text-slate-500 text-xs block mb-1">Qoldiq summa:</span>
                                <span class="text-lg font-black text-slate-800">{{ formatPrice(calcBalance) }} so'm</span>
                            </div>

                            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200">
                                <span class="text-emerald-800 text-xs block mb-1 font-bold">Oylik to'lov ({{ paymentMonths }} oy):</span>
                                <span class="text-xl font-black text-emerald-700">{{ formatPrice(calcMonthlyPayment) }} so'm</span>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-blue-50 border border-blue-100 text-blue-900 text-xs flex items-center gap-2">
                            <span>ℹ️</span> To'lov grafigi shartnoma asosida teng taqsimlanadi. Qo'shimcha komissiya yoki foizlar undirilmaydi.
                        </div>

                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50">
                    <div class="text-xs text-slate-600">
                        Tanlangan: <strong class="text-[#0f172a] font-bold">{{ selectedUnit.rooms }} xona, {{ selectedUnit.area }} m²</strong> • <span class="text-orange-600 font-bold">{{ formatPrice(selectedUnit.price) }} so'm</span>
                    </div>

                    <button 
                        @click="selectAndApply(selectedUnit)"
                        class="w-full sm:w-auto px-8 py-3 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-extrabold text-sm shadow-md shadow-orange-500/20 transition-all flex items-center justify-center gap-2"
                    >
                        <span>✍️</span> Ushbu Xonadonga Ariza / Xarid Qilish
                    </button>
                </div>

            </div>

        </div>

        <!-- Fullscreen Image Preview Lightbox -->
        <div v-if="isImageModalOpen" class="fixed inset-0 z-60 bg-black/90 backdrop-blur-md flex items-center justify-center p-4">
            <button @click="isImageModalOpen = false" class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/20 hover:bg-white/40 text-white flex items-center justify-center font-bold text-lg">
                ✕
            </button>
            <img :src="modalPreviewImage" alt="Preview" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl" />
        </div>

    </div>
</template>
