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
    }
});

// Navigation Active View Mode (Separated for Mobile & Desktop)
const activeView = ref('catalog'); // 'catalog' | 'apply'

// Search & Filter States
const selectedRooms = ref('all');
const selectedFloorRange = ref('all');
const searchQuery = ref('');
const sortBy = ref('default');

// Modal States for 3D & Blueprint Inspection
const selectedUnit = ref(null);
const blueprintTheme = ref('light'); // 'light' (oq qog'oz) | 'dark' (muhandislik ko'k)
const activeDetailTab = ref('3d'); // '3d' | 'blueprint' | 'calc'

// Installment Calculator in Modal
const downPaymentPercent = ref(20);
const paymentMonths = ref(36);

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
    region: '',
    district: '',
    comment: '',
    selected_unit_id: null,
    source: 'public_catalog',
});

const isSubmitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

// Filtered Units Computed
const filteredUnits = computed(() => {
    let units = [...props.availableUnits];

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
        units = units.filter(u => parseInt(u.floor_level) <= 5);
    } else if (selectedFloorRange.value === 'mid') {
        units = units.filter(u => parseInt(u.floor_level) >= 6 && parseInt(u.floor_level) <= 10);
    } else if (selectedFloorRange.value === 'high') {
        units = units.filter(u => parseInt(u.floor_level) >= 11);
    }

    // Filter by search
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        units = units.filter(u => 
            (u.building_name && u.building_name.toLowerCase().includes(q)) ||
            (u.unit_number && u.unit_number.toString().includes(q)) ||
            (u.block_name && u.block_name.toLowerCase().includes(q))
        );
    }

    // Sort
    if (sortBy.value === 'area_asc') {
        units.sort((a, b) => a.area - b.area);
    } else if (sortBy.value === 'area_desc') {
        units.sort((a, b) => b.area - a.area);
    } else if (sortBy.value === 'floor_asc') {
        units.sort((a, b) => a.floor_level - b.floor_level);
    }

    return units;
});

// Open 3D & Blueprint Modal
const openUnitDetails = (unit) => {
    selectedUnit.value = unit;
    activeDetailTab.value = '3d';
};

// Select Unit and Proceed to Apply Tab
const selectAndApply = (unit) => {
    form.value.selected_unit_id = unit.id;
    form.value.comment = `Tanlangan: ${unit.building_name} (${unit.block_name}-blok), ${unit.floor_level}-qavat, №${unit.unit_number} xonadon (${unit.rooms} xona, ${unit.area} m²).`;
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
        successMessage.value = props.promoSettings?.promo_success_message || response.data.message || 'Arizangiz muvaffaqiyatli qabul qilindi!';
        
        if (props.promoSettings?.fb_pixel_id && window.fbq) {
            window.fbq('track', 'Lead');
        }
        
        form.value.full_name = '';
        form.value.phone = '';
        form.value.region = '';
        form.value.district = '';
        form.value.comment = '';
        form.value.selected_unit_id = null;
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
    <Head title="Xonadonlar Katalogi & 3D Ko'rinish - Sher-Dor" />
    
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
                        <span class="text-[10px] text-slate-500 font-semibold tracking-wider uppercase">Onlayn Tanlash va 3D Reja</span>
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
                    </button>

                    <button 
                        @click="activeView = 'apply'"
                        :class="activeView === 'apply' ? 'bg-[#0f172a] text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                        class="px-5 py-2 rounded-lg text-xs sm:text-sm transition-all flex items-center gap-2"
                    >
                        <span>📝</span> Ariza Qoldirish
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
                    <span>🏢</span> Xonadonlar ({{ filteredUnits.length }})
                </button>
                <button 
                    @click="activeView = 'apply'"
                    :class="activeView === 'apply' ? 'text-[#0f172a] border-b-2 border-[#0f172a] font-bold bg-slate-50' : 'text-slate-600'"
                    class="flex-1 py-3 text-xs text-center transition-all flex items-center justify-center gap-1.5"
                >
                    <span>📝</span> Ariza qoldirish
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
                <div class="p-6 md:p-10 rounded-3xl bg-white border border-slate-200 shadow-sm relative overflow-hidden">
                    <div class="max-w-3xl relative z-10">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-orange-50 border border-orange-200 text-orange-600 text-xs font-bold uppercase tracking-wider mb-3">
                            <span class="w-2 h-2 rounded-full bg-orange-500"></span> Sotuvdagi Rasmiy Xonadonlar
                        </div>
                        <h1 class="text-3xl sm:text-4xl md:text-5xl font-black tracking-tight text-[#0f172a] mb-3">
                            O'zingizga Mos Xonadonni <span class="text-orange-500">3D Rejada Tanlang!</span>
                        </h1>
                        <p class="text-slate-600 text-sm sm:text-base leading-relaxed">
                            Barcha mavjud xonadonlarning 3D loyihasi, arxitektura chizmasi, xonalar kvadraturasi va qulay bo'lib to'lash shartlari bilan to'liq tanishing.
                        </p>
                    </div>
                </div>

                <!-- Filters & Search Toolbar -->
                <div class="p-4 sm:p-6 rounded-2xl bg-white border border-slate-200 shadow-sm space-y-4">
                    
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
                                {{ r === 'all' ? 'Barchasi' : (r === '4' ? '4+ xona' : r + ' xona') }}
                            </button>
                        </div>

                        <!-- Search Bar -->
                        <div class="w-full md:w-80 relative">
                            <input 
                                v-model="searchQuery" 
                                type="text" 
                                placeholder="Xonadon №, bino yoki blok..." 
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 placeholder-slate-400 focus:border-orange-500 focus:bg-white focus:outline-none transition-all"
                            />
                            <span class="absolute left-3.5 top-2.5 text-slate-400 text-sm">🔍</span>
                        </div>

                    </div>

                    <!-- Secondary Filters: Floor & Sort -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 text-xs text-slate-600">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">Qavat:</span>
                            <select v-model="selectedFloorRange" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-slate-800 font-medium focus:border-orange-500 focus:bg-white focus:outline-none">
                                <option value="all">Barcha qavatlar</option>
                                <option value="low">1 - 5 qavat</option>
                                <option value="mid">6 - 10 qavat</option>
                                <option value="high">11+ qavat</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="font-medium">Saralash:</span>
                            <select v-model="sortBy" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-slate-800 font-medium focus:border-orange-500 focus:bg-white focus:outline-none">
                                <option value="default">Odatiy</option>
                                <option value="area_asc">Maydoni (kichikdan kattaga)</option>
                                <option value="area_desc">Maydoni (kattadan kichikka)</option>
                                <option value="floor_asc">Qavati bo'yicha</option>
                            </select>
                        </div>

                        <div class="text-[#0f172a] font-bold">
                            Mavjud: <span class="text-orange-600 font-mono">{{ filteredUnits.length }}</span> ta variant
                        </div>
                    </div>

                </div>

                <!-- Apartment Cards Grid -->
                <div v-if="filteredUnits.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    <div 
                        v-for="unit in filteredUnits" 
                        :key="unit.id" 
                        class="p-5 sm:p-6 rounded-3xl bg-white border border-slate-200 hover:border-orange-400 hover:shadow-lg transition-all duration-300 flex flex-col justify-between group"
                    >
                        <!-- Top Badges -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Sotuvda Mavjud
                                </span>
                                <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200 text-[10px] font-bold font-mono">
                                    {{ unit.block_name }}-blok
                                </span>
                            </div>
                            <span class="text-xs font-mono font-semibold text-slate-500">{{ unit.floor_level }}-qavat</span>
                        </div>

                        <!-- 3D Plan Isometric Thumbnail Preview -->
                        <div 
                            @click="openUnitDetails(unit)"
                            class="w-full h-44 rounded-2xl bg-slate-50 border border-slate-200 p-4 mb-5 flex flex-col items-center justify-center cursor-pointer group-hover:border-orange-300 transition-all relative overflow-hidden"
                        >
                            <!-- Visual Architectural Layout Render Mockup -->
                            <div class="w-full h-full relative flex items-center justify-center">
                                <svg viewBox="0 0 240 140" class="w-full h-full drop-shadow-sm">
                                    <!-- Blueprint walls -->
                                    <rect x="20" y="20" width="200" height="100" rx="8" fill="#f8fafc" stroke="#0f172a" stroke-width="2" stroke-dasharray="3 2" />
                                    <!-- Living room -->
                                    <rect x="25" y="25" width="110" height="90" rx="4" fill="#ffffff" stroke="#f97316" stroke-width="1.5" />
                                    <text x="80" y="65" fill="#0f172a" font-size="10" font-weight="bold" text-anchor="middle">Mehmonxona</text>
                                    <text x="80" y="80" fill="#ea580c" font-size="9" font-weight="600" text-anchor="middle">{{ (unit.area * 0.42).toFixed(1) }} m²</text>
                                    
                                    <!-- Bedroom -->
                                    <rect x="140" y="25" width="75" height="50" rx="4" fill="#ffffff" stroke="#0f172a" stroke-width="1.5" />
                                    <text x="177" y="50" fill="#0f172a" font-size="9" font-weight="bold" text-anchor="middle">Yotoqxona</text>
                                    <text x="177" y="62" fill="#64748b" font-size="8" font-weight="600" text-anchor="middle">{{ (unit.area * 0.33).toFixed(1) }} m²</text>
                                    
                                    <!-- Kitchen & Bath -->
                                    <rect x="140" y="80" width="75" height="35" rx="4" fill="#ffffff" stroke="#0f172a" stroke-width="1.5" />
                                    <text x="177" y="98" fill="#0f172a" font-size="8" font-weight="bold" text-anchor="middle">Oshxona & Sanuzel</text>
                                    <text x="177" y="108" fill="#64748b" font-size="7" font-weight="600" text-anchor="middle">{{ (unit.area * 0.25).toFixed(1) }} m²</text>
                                </svg>
                            </div>

                            <div class="absolute bottom-2 right-2 px-2.5 py-1 rounded-lg bg-white/95 backdrop-blur-xs text-[10px] font-bold text-slate-800 border border-slate-200 shadow-xs flex items-center gap-1 group-hover:text-orange-600 transition-colors">
                                <span>📐</span> 3D Rejani Ko'rish
                            </div>
                        </div>

                        <!-- Apartment Specs -->
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-black text-[#0f172a] flex items-center gap-2">
                                    №{{ unit.unit_number }} Xonadon
                                </h3>
                                <span class="text-orange-600 font-extrabold text-lg">{{ unit.area }} m²</span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs text-slate-700">
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2 font-medium">
                                    <span>🛋️</span> {{ unit.rooms }} xonali
                                </div>
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2 font-medium">
                                    <span>🏢</span> {{ unit.floor_level }}-qavat
                                </div>
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2 font-medium">
                                    <span>🧭</span> {{ unit.orientation }}
                                </div>
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2 font-medium">
                                    <span>🌅</span> {{ unit.has_balcony ? 'Balkonli' : 'Balkonsiz' }}
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="grid grid-cols-2 gap-3 pt-4 border-t border-slate-100">
                            <button 
                                @click="openUnitDetails(unit)"
                                class="py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition-all flex items-center justify-center gap-1.5"
                            >
                                <span>🔍</span> 3D Ko'rish
                            </button>

                            <button 
                                @click="selectAndApply(unit)"
                                class="py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-extrabold text-xs shadow-xs transition-all flex items-center justify-center gap-1"
                            >
                                <span>✍️</span> Xarid Qilish
                            </button>
                        </div>

                    </div>

                </div>

                <!-- Empty State -->
                <div v-else class="p-12 rounded-3xl bg-white border border-slate-200 text-center space-y-4 shadow-sm">
                    <div class="text-4xl">🔍</div>
                    <h3 class="text-xl font-bold text-[#0f172a]">Mos xonadon topilmadi</h3>
                    <p class="text-slate-500 text-sm max-w-md mx-auto">Filtrlarni o'zgartirib ko'ring yoki barcha xonadonlarni ko'rish uchun filtrlarni tozalang.</p>
                    <button @click="selectedRooms = 'all'; selectedFloorRange = 'all'; searchQuery = '';" class="px-6 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs shadow-xs transition-colors">
                        Filtrlarni Tozalash
                    </button>
                </div>

            </div>

            <!-- ========================================================= -->
            <!-- TAB 2: ARIZA QOLDIRISH & MAXSUS TAKLIF (LEAD FORM) -->
            <!-- ========================================================= -->
            <div v-show="activeView === 'apply'" class="max-w-2xl mx-auto">
                
                <div class="p-6 sm:p-10 rounded-3xl bg-white border border-slate-200 shadow-lg relative">
                    
                    <!-- Form Header -->
                    <div class="text-center mb-8 relative z-10">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-orange-50 border border-orange-200 text-orange-600 text-xs font-bold uppercase tracking-wider mb-3">
                            <span>📝</span> Maxsus Taklif & Shartnoma
                        </div>
                        <h2 class="text-2xl sm:text-4xl font-black text-[#0f172a] mb-2 tracking-tight">
                            Xonadon Bo'yicha Ariza Qoldiring
                        </h2>
                        <p class="text-slate-600 text-xs sm:text-sm">
                            Mutaxassislarimiz 15 daqiqa ichida siz bilan bog'lanib, eng qulay chegirma va bo'lib to'lash shartlarini taqdim etishadi.
                        </p>
                    </div>

                    <!-- Success State -->
                    <div v-if="successMessage" class="p-8 rounded-2xl bg-emerald-50 border border-emerald-200 text-center space-y-4">
                        <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 text-3xl mx-auto flex items-center justify-center font-bold">
                            ✓
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900">Arizangiz Qabul Qilindi!</h3>
                        <p class="text-emerald-800 text-sm leading-relaxed">{{ successMessage }}</p>
                        <button @click="successMessage = ''; activeView = 'catalog';" class="px-6 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs uppercase tracking-wider shadow-sm transition-colors">
                            Katalogga qaytish
                        </button>
                    </div>

                    <!-- Application Form -->
                    <form v-else @submit.prevent="submitLead" class="space-y-4 sm:space-y-5 relative z-10">
                        
                        <div v-if="errorMessage" class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold">
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
                                    <option value="" disabled>Viloyatni tanlang</option>
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

                        <!-- Comment / Selected Unit Spec -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Qiziqayotgan Xonadoningiz yoki Izoh</label>
                            <textarea 
                                v-model="form.comment" 
                                rows="3" 
                                placeholder="Qaysi blok, qavat yoki to'lov rejasi sizga ma'qul..."
                                class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 text-sm focus:border-orange-500 focus:bg-white focus:outline-none resize-none transition-all"
                            ></textarea>
                        </div>

                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            :disabled="isSubmitting"
                            class="w-full py-4 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-extrabold text-sm sm:text-base uppercase tracking-wider shadow-md shadow-orange-500/20 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            <span v-if="isSubmitting" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            {{ isSubmitting ? 'Yuborilmoqda...' : 'Arizani Yuborish & Konsultatsiya Olish' }}
                        </button>

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
                                №{{ selectedUnit.unit_number }} Xonadon — {{ selectedUnit.area }} m²
                            </h3>
                            <p class="text-xs text-slate-500">{{ selectedUnit.building_name }} • {{ selectedUnit.block_name }}-blok • {{ selectedUnit.floor_level }}-qavat</p>
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
                                class="px-4 py-1.5 rounded-lg text-xs transition-all"
                            >
                                🏢 3D Reja
                            </button>
                            <button 
                                @click="activeDetailTab = 'blueprint'"
                                :class="activeDetailTab === 'blueprint' ? 'bg-[#0f172a] text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                                class="px-4 py-1.5 rounded-lg text-xs transition-all"
                            >
                                📐 Arxitektura Chizmasi
                            </button>
                            <button 
                                @click="activeDetailTab = 'calc'"
                                :class="activeDetailTab === 'calc' ? 'bg-slate-800 text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                                class="px-4 py-1.5 rounded-lg text-xs transition-all"
                            >
                                🧮 To'lov Kalkulyatori
                            </button>
                        </div>

                    </div>

                    <!-- 3D VIEW SCREEN -->
                    <div v-show="activeDetailTab === '3d'" class="w-full h-80 sm:h-96 rounded-2xl bg-[#0f172a] border border-slate-300 p-6 flex flex-col items-center justify-center relative overflow-hidden shadow-inner">
                        
                        <!-- Isometric 3D Model Visual Representation -->
                        <div class="relative w-full h-full flex items-center justify-center">
                            <svg viewBox="0 0 500 280" class="w-full h-full drop-shadow-lg">
                                <!-- 3D Isometric Base Floor -->
                                <polygon points="250,20 460,110 250,220 40,110" fill="#1e293b" stroke="#f97316" stroke-width="2" />
                                
                                <!-- Living room 3D Zone -->
                                <polygon points="250,50 360,98 250,165 140,98" fill="#334155" stroke="#ea580c" stroke-width="2" />
                                <text x="250" y="105" fill="#ffffff" font-size="14" font-weight="bold" text-anchor="middle">🛋️ Mehmonxona ({{ (selectedUnit.area * 0.42).toFixed(1) }} m²)</text>
                                
                                <!-- Bedroom 3D Zone -->
                                <polygon points="140,98 250,165 180,200 70,125" fill="#1e293b" stroke="#38bdf8" stroke-width="1.5" />
                                <text x="160" y="160" fill="#ffffff" font-size="12" font-weight="bold" text-anchor="middle">🛏️ Yotoqxona</text>
                                
                                <!-- Kitchen & Bathroom 3D Zone -->
                                <polygon points="360,98 430,125 320,200 250,165" fill="#1e293b" stroke="#34d399" stroke-width="1.5" />
                                <text x="340" y="160" fill="#ffffff" font-size="12" font-weight="bold" text-anchor="middle">🍳 Oshxona & Sanuzel</text>
                            </svg>
                        </div>

                        <div class="absolute top-4 left-4 px-3 py-1 rounded-full bg-slate-900/90 border border-slate-700 text-orange-400 text-xs font-mono font-medium">
                            ⚡ 3D Isometric View • Real Rejalashtirish
                        </div>
                    </div>

                    <!-- ARCHITECTURAL BLUEPRINT (OQ QOG'OZ ARXITEKTURA CHIZMASI) -->
                    <div 
                        v-show="activeDetailTab === 'blueprint'"
                        class="w-full p-6 rounded-2xl bg-white border border-slate-200 shadow-sm flex flex-col"
                    >
                        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-200">
                            <span class="text-xs font-bold uppercase font-mono tracking-widest text-[#0f172a]">
                                📐 Muhandislik Chizmasi va Xonalar Bo'linishi
                            </span>
                            <span class="text-xs font-mono font-bold text-slate-500">
                                Masshtab 1:100 • Standart SNiP
                            </span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
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

                        <!-- Vector Blueprint Render -->
                        <div class="w-full h-64 p-4 rounded-xl bg-slate-50 border-2 border-dashed border-slate-300 flex items-center justify-center">
                            <svg viewBox="0 0 400 200" class="w-full h-full">
                                <rect x="10" y="10" width="380" height="180" fill="#ffffff" stroke="#0f172a" stroke-width="2.5" />
                                <line x1="220" y1="10" x2="220" y2="190" stroke="#0f172a" stroke-width="2" />
                                <line x1="220" y1="100" x2="390" y2="100" stroke="#0f172a" stroke-width="2" />
                                <text x="110" y="100" fill="#0f172a" font-size="14" font-weight="bold" text-anchor="middle">Mehmonxona (Zal)</text>
                                <text x="305" y="60" fill="#0f172a" font-size="12" font-weight="bold" text-anchor="middle">Yotoqxona</text>
                                <text x="305" y="150" fill="#0f172a" font-size="12" font-weight="bold" text-anchor="middle">Oshxona</text>
                            </svg>
                        </div>
                    </div>

                    <!-- INSTALLMENT CALCULATOR (BO'LIB TO'LASH) -->
                    <div v-show="activeDetailTab === 'calc'" class="p-6 rounded-2xl bg-slate-50 border border-slate-200 space-y-6">
                        <h4 class="text-base font-bold text-[#0f172a] flex items-center gap-2">
                            <span>🧮</span> Moslashuvchan Bo'lib To'lash Hisoblagichi
                        </h4>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

                        <div class="p-4 rounded-xl bg-white border border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-700 shadow-xs">
                            <div>
                                <span class="text-slate-500 block">Dastlabki to'lov: {{ downPaymentPercent }}%</span>
                                <span class="text-sm font-bold text-orange-600">Imtiyozli 0% ustamasiz bo'lib to'lash</span>
                            </div>
                            <div class="text-right">
                                <span class="text-slate-500 block">Oylik to'lov:</span>
                                <span class="text-base font-black text-emerald-600">Shartnoma asosida teng taqsimot</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50">
                    <div class="text-xs text-slate-600">
                        Tanlangan: <strong class="text-[#0f172a] font-bold">{{ selectedUnit.rooms }} xona, {{ selectedUnit.area }} m²</strong>
                    </div>

                    <button 
                        @click="selectAndApply(selectedUnit)"
                        class="w-full sm:w-auto px-8 py-3 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-extrabold text-sm shadow-md shadow-orange-500/20 transition-all flex items-center justify-center gap-2"
                    >
                        <span>✍️</span> Ushbu Xonadonga Ariza Qoldirish
                    </button>
                </div>

            </div>

        </div>

    </div>
</template>
