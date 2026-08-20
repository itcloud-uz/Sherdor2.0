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
const activeView = ref('catalog'); // 'catalog' | 'apply' | 'calculator'

// Search & Filter States
const selectedRooms = ref('all');
const selectedFloorRange = ref('all');
const searchQuery = ref('');
const sortBy = ref('default');

// Modal States for 3D & Blueprint Inspection
const selectedUnit = ref(null);
const blueprintTheme = ref('dark'); // 'dark' | 'light' (oq fon)
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
    <Head title="Xonadonlar Katalogi & 3D Ko'rinish - IQS ERP" />
    
    <div class="min-h-screen bg-[#07070c] text-white font-sans overflow-x-hidden relative selection:bg-amber-500 selection:text-black">
        
        <!-- Ambient Glowing Background Elements -->
        <div class="fixed top-[-10%] left-[10%] w-[500px] h-[500px] rounded-full bg-cyan-500/10 blur-[150px] pointer-events-none"></div>
        <div class="fixed bottom-[-10%] right-[10%] w-[600px] h-[600px] rounded-full bg-amber-500/10 blur-[160px] pointer-events-none"></div>
        <div class="fixed top-[40%] right-[20%] w-[400px] h-[400px] rounded-full bg-purple-600/10 blur-[140px] pointer-events-none"></div>

        <!-- Sticky Header with Navigation -->
        <header class="sticky top-0 z-40 backdrop-blur-xl bg-[#07070c]/85 border-b border-white/10 shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                
                <!-- Logo & Brand -->
                <Link :href="route('landing')" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-500 to-cyan-400 flex items-center justify-center p-0.5 shadow-[0_0_20px_rgba(245,158,11,0.3)] group-hover:scale-105 transition-transform">
                        <div class="w-full h-full bg-[#0d0d14] rounded-[14px] flex items-center justify-center font-black text-lg text-amber-400">
                            IQS
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xl font-black tracking-tight text-white flex items-center gap-1.5">
                            {{ $page.props.branding?.app_name || 'IQS Xonadonlar' }}
                        </span>
                        <span class="text-[10px] text-gray-400 font-semibold tracking-wider uppercase">Onlayn Tanlash va 3D Reja</span>
                    </div>
                </Link>

                <!-- Desktop / Tablet View Mode Tabs -->
                <div class="hidden sm:flex items-center p-1 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md">
                    <button 
                        @click="activeView = 'catalog'"
                        :class="activeView === 'catalog' ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-black font-extrabold shadow-md' : 'text-gray-300 hover:text-white'"
                        class="px-5 py-2 rounded-xl text-xs sm:text-sm transition-all flex items-center gap-2"
                    >
                        <span>🏢</span> Xonadonlar Katalogi
                    </button>

                    <button 
                        @click="activeView = 'apply'"
                        :class="activeView === 'apply' ? 'bg-gradient-to-r from-cyan-500 to-blue-600 text-black font-extrabold shadow-md' : 'text-gray-300 hover:text-white'"
                        class="px-5 py-2 rounded-xl text-xs sm:text-sm transition-all flex items-center gap-2"
                    >
                        <span>📝</span> Ariza Qoldirish
                    </button>
                </div>

                <!-- Back to Landing Link -->
                <Link :href="route('landing')" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 text-xs sm:text-sm font-semibold transition-all flex items-center gap-1.5">
                    <span>←</span> Bosh sahifa
                </Link>
            </div>

            <!-- Mobile View Switcher Tab Bar -->
            <div class="sm:hidden flex border-t border-white/10 bg-black/40">
                <button 
                    @click="activeView = 'catalog'"
                    :class="activeView === 'catalog' ? 'text-amber-400 border-b-2 border-amber-400 font-bold bg-amber-500/10' : 'text-gray-400'"
                    class="flex-1 py-3 text-xs text-center transition-all flex items-center justify-center gap-1.5"
                >
                    <span>🏢</span> Xonadonlar ({{ filteredUnits.length }})
                </button>
                <button 
                    @click="activeView = 'apply'"
                    :class="activeView === 'apply' ? 'text-cyan-400 border-b-2 border-cyan-400 font-bold bg-cyan-500/10' : 'text-gray-400'"
                    class="flex-1 py-3 text-xs text-center transition-all flex items-center justify-center gap-1.5"
                >
                    <span>📝</span> Ariza qoldirish
                </button>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12 relative z-10">

            <!-- ========================================================= -->
            <!-- TAB 1: XONADONLAR KATALOGI (APARTMENT CATALOG & 3D) -->
            <!-- ========================================================= -->
            <div v-show="activeView === 'catalog'" class="space-y-8">
                
                <!-- Hero Header Banner -->
                <div class="p-6 md:p-10 rounded-3xl bg-gradient-to-r from-amber-500/10 via-purple-600/10 to-cyan-500/10 border border-white/10 shadow-2xl relative overflow-hidden">
                    <div class="max-w-3xl relative z-10">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs font-bold uppercase tracking-wider mb-4">
                            ✨ Sotuvdagi Rasmiy Xonadonlar
                        </div>
                        <h1 class="text-3xl sm:text-5xl font-black tracking-tight text-white mb-3">
                            O'zingizga Mos Xonadonni <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-cyan-400">3D Rejada Tanlang!</span>
                        </h1>
                        <p class="text-gray-300 text-sm sm:text-base leading-relaxed">
                            Barcha mavjud xonadonlarning 3D loyihasi, arxitektura chizmasi, xonalar kvadraturasi va qulay bo'lib to'lash shartlari bilan to'liq tanishing.
                        </p>
                    </div>
                </div>

                <!-- Filters & Search Toolbar -->
                <div class="p-4 sm:p-6 rounded-2xl bg-[#0e0e17] border border-white/10 shadow-lg space-y-4">
                    
                    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                        
                        <!-- Rooms Filter Buttons -->
                        <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto w-full md:w-auto pb-2 md:pb-0">
                            <span class="text-xs text-gray-400 font-semibold uppercase mr-1 hidden sm:inline">Xonalar:</span>
                            <button 
                                v-for="r in ['all', '1', '2', '3', '4']" 
                                :key="r"
                                @click="selectedRooms = r"
                                :class="selectedRooms === r ? 'bg-amber-500 text-black font-extrabold shadow-[0_0_15px_rgba(245,158,11,0.4)]' : 'bg-white/5 text-gray-300 hover:bg-white/10 border border-white/10'"
                                class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all whitespace-nowrap"
                            >
                                {{ r === 'all' ? 'Barchasi' : (r === '4' ? '4+ xona' : r + ' xona') }}
                            </button>
                        </div>

                        <!-- Search Bar -->
                        <div class="w-full md:w-72 relative">
                            <input 
                                v-model="searchQuery" 
                                type="text" 
                                placeholder="Xonadon №, bino yoki blok..." 
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-black/40 border border-white/15 text-xs sm:text-sm text-white placeholder-gray-500 focus:border-amber-400 focus:outline-none"
                            />
                            <span class="absolute left-3.5 top-2.5 text-gray-500 text-sm">🔍</span>
                        </div>

                    </div>

                    <!-- Secondary Filters: Floor & Sort -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-white/5 text-xs text-gray-400">
                        <div class="flex items-center gap-2">
                            <span>Qavat:</span>
                            <select v-model="selectedFloorRange" class="bg-black/60 border border-white/10 rounded-lg px-2.5 py-1 text-white focus:border-amber-400 focus:outline-none">
                                <option value="all">Barcha qavatlar</option>
                                <option value="low">1 - 5 qavat</option>
                                <option value="mid">6 - 10 qavat</option>
                                <option value="high">11+ qavat</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <span>Saralash:</span>
                            <select v-model="sortBy" class="bg-black/60 border border-white/10 rounded-lg px-2.5 py-1 text-white focus:border-amber-400 focus:outline-none">
                                <option value="default">Odatiy</option>
                                <option value="area_asc">Maydoni (kichikdan kattaga)</option>
                                <option value="area_desc">Maydoni (kattadan kichikka)</option>
                                <option value="floor_asc">Qavati bo'yicha</option>
                            </select>
                        </div>

                        <div class="text-amber-400 font-semibold font-mono">
                            Mavjud: {{ filteredUnits.length }} ta variant
                        </div>
                    </div>

                </div>

                <!-- Apartment Cards Grid -->
                <div v-if="filteredUnits.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    <div 
                        v-for="unit in filteredUnits" 
                        :key="unit.id" 
                        class="p-5 sm:p-6 rounded-3xl bg-[#0f0f18] border border-white/10 hover:border-amber-400/50 hover:shadow-[0_0_30px_rgba(245,158,11,0.2)] transition-all duration-300 flex flex-col justify-between group relative overflow-hidden"
                    >
                        <!-- Top Badges -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 text-[10px] font-bold uppercase tracking-wider">
                                    ● Sotuvda Mavjud
                                </span>
                                <span class="px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-300 border border-amber-500/30 text-[10px] font-bold font-mono">
                                    {{ unit.block_name }}-blok
                                </span>
                            </div>
                            <span class="text-xs font-mono text-gray-400">{{ unit.floor_level }}-qavat</span>
                        </div>

                        <!-- 3D Plan Isometric Thumbnail Preview -->
                        <div 
                            @click="openUnitDetails(unit)"
                            class="w-full h-44 rounded-2xl bg-gradient-to-b from-white/[0.04] to-black/60 border border-white/10 p-4 mb-5 flex flex-col items-center justify-center cursor-pointer group-hover:border-amber-400/40 transition-all relative overflow-hidden"
                        >
                            <!-- Visual Architectural Layout Render Mockup -->
                            <div class="w-full h-full relative flex items-center justify-center">
                                <svg viewBox="0 0 240 140" class="w-full h-full drop-shadow-[0_10px_20px_rgba(0,0,0,0.8)]">
                                    <!-- Blueprint walls -->
                                    <rect x="20" y="20" width="200" height="100" rx="8" fill="#141422" stroke="#f59e0b" stroke-width="2" stroke-dasharray="2 1" />
                                    <!-- Living room -->
                                    <rect x="25" y="25" width="110" height="90" rx="4" fill="#1f1f33" stroke="#8b5cf6" stroke-width="1.5" />
                                    <text x="80" y="65" fill="#ffffff" font-size="10" font-weight="bold" text-anchor="middle">Mehmonxona</text>
                                    <text x="80" y="80" fill="#a78bfa" font-size="9" text-anchor="middle">{{ (unit.area * 0.42).toFixed(1) }} m²</text>
                                    
                                    <!-- Bedroom -->
                                    <rect x="140" y="25" width="75" height="50" rx="4" fill="#1c2438" stroke="#06b6d4" stroke-width="1.5" />
                                    <text x="177" y="50" fill="#ffffff" font-size="9" font-weight="bold" text-anchor="middle">Yotoqxona</text>
                                    <text x="177" y="62" fill="#67e8f9" font-size="8" text-anchor="middle">{{ (unit.area * 0.33).toFixed(1) }} m²</text>
                                    
                                    <!-- Kitchen & Bath -->
                                    <rect x="140" y="80" width="75" height="35" rx="4" fill="#242138" stroke="#10b981" stroke-width="1.5" />
                                    <text x="177" y="98" fill="#ffffff" font-size="8" font-weight="bold" text-anchor="middle">Oshxona & Sanuzel</text>
                                    <text x="177" y="108" fill="#6ee7b7" font-size="7" text-anchor="middle">{{ (unit.area * 0.25).toFixed(1) }} m²</text>
                                </svg>
                            </div>

                            <div class="absolute bottom-2 right-2 px-2.5 py-1 rounded-lg bg-black/80 backdrop-blur-md text-[10px] font-bold text-amber-300 border border-amber-500/30 flex items-center gap-1">
                                <span>📐</span> 3D Rejani Ko'rish
                            </div>
                        </div>

                        <!-- Apartment Specs -->
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-black text-white flex items-center gap-2">
                                    №{{ unit.unit_number }} Xonadon
                                </h3>
                                <span class="text-amber-400 font-extrabold text-lg">{{ unit.area }} m²</span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs text-gray-300">
                                <div class="p-2 rounded-xl bg-white/[0.03] border border-white/5 flex items-center gap-2">
                                    <span>🛋️</span> {{ unit.rooms }} xonali
                                </div>
                                <div class="p-2 rounded-xl bg-white/[0.03] border border-white/5 flex items-center gap-2">
                                    <span>🏢</span> {{ unit.floor_level }}-qavat
                                </div>
                                <div class="p-2 rounded-xl bg-white/[0.03] border border-white/5 flex items-center gap-2">
                                    <span>🧭</span> {{ unit.orientation }}
                                </div>
                                <div class="p-2 rounded-xl bg-white/[0.03] border border-white/5 flex items-center gap-2">
                                    <span>🌅</span> {{ unit.has_balcony ? 'Balkonli' : 'Balkonsiz' }}
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="grid grid-cols-2 gap-3 pt-4 border-t border-white/10">
                            <button 
                                @click="openUnitDetails(unit)"
                                class="py-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 text-white font-bold text-xs transition-all flex items-center justify-center gap-1.5"
                            >
                                <span>🔍</span> 3D Ko'rish
                            </button>

                            <button 
                                @click="selectAndApply(unit)"
                                class="py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-black font-extrabold text-xs shadow-md transition-all flex items-center justify-center gap-1"
                            >
                                <span>✍️</span> Xarid Qilish
                            </button>
                        </div>

                    </div>

                </div>

                <!-- Empty State -->
                <div v-else class="p-12 rounded-3xl bg-[#0e0e17] border border-white/10 text-center space-y-4">
                    <div class="text-4xl">🔍</div>
                    <h3 class="text-xl font-bold text-white">Mos xonadon topilmadi</h3>
                    <p class="text-gray-400 text-sm max-w-md mx-auto">Filtrlarni o'zgartirib ko'ring yoki barcha xonadonlarni ko'rish uchun filtrlarni bekor qiling.</p>
                    <button @click="selectedRooms = 'all'; selectedFloorRange = 'all'; searchQuery = '';" class="px-6 py-2 rounded-xl bg-amber-500 text-black font-bold text-xs">
                        Filtrlarni Tozalash
                    </button>
                </div>

            </div>

            <!-- ========================================================= -->
            <!-- TAB 2: ARIZA QOLDIRISH & MAXSUS TAKLIF (LEAD FORM) -->
            <!-- ========================================================= -->
            <div v-show="activeView === 'apply'" class="max-w-2xl mx-auto">
                
                <div class="p-6 sm:p-10 rounded-3xl bg-[#0e0e17] border border-cyan-500/30 shadow-[0_0_50px_rgba(6,182,212,0.15)] relative overflow-hidden">
                    
                    <div class="absolute -top-24 -right-24 w-48 h-48 bg-cyan-500/20 rounded-full blur-[80px] pointer-events-none"></div>

                    <!-- Form Header -->
                    <div class="text-center mb-8 relative z-10">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 text-xs font-bold uppercase tracking-wider mb-3">
                            📝 Maxsus Taklif & Shartnoma
                        </div>
                        <h2 class="text-2xl sm:text-4xl font-black text-white mb-2">
                            Xonadon Bo'yicha Ariza Qoldiring
                        </h2>
                        <p class="text-gray-400 text-xs sm:text-sm">
                            Mutaxassislarimiz 15 daqiqa ichida siz bilan bog'lanib, eng qulay chegirma va bo'lib to'lash shartlarini taqdim etishadi.
                        </p>
                    </div>

                    <!-- Success State -->
                    <div v-if="successMessage" class="p-8 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-center space-y-4">
                        <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 text-3xl mx-auto flex items-center justify-center">
                            ✓
                        </div>
                        <h3 class="text-2xl font-bold text-white">Arizangiz Qabul Qilindi!</h3>
                        <p class="text-emerald-300 text-sm leading-relaxed">{{ successMessage }}</p>
                        <button @click="successMessage = ''; activeView = 'catalog';" class="px-6 py-2.5 rounded-xl bg-amber-500 text-black font-bold text-xs uppercase tracking-wider">
                            Katalogga qaytish
                        </button>
                    </div>

                    <!-- Application Form -->
                    <form v-else @submit.prevent="submitLead" class="space-y-4 sm:space-y-5 relative z-10">
                        
                        <div v-if="errorMessage" class="p-3.5 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 text-xs font-medium">
                            {{ errorMessage }}
                        </div>

                        <!-- Full Name -->
                        <div>
                            <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">Ism va Familiyangiz *</label>
                            <input 
                                v-model="form.full_name" 
                                required 
                                type="text" 
                                placeholder="Masalan: Azizbek Rahimov"
                                class="w-full px-4 py-3.5 rounded-xl bg-black/50 border border-white/15 text-white placeholder-gray-500 text-sm focus:border-cyan-400 focus:outline-none focus:ring-1 focus:ring-cyan-400"
                            />
                        </div>

                        <!-- Phone Number -->
                        <div>
                            <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">Telefon Raqamingiz *</label>
                            <input 
                                v-model="form.phone" 
                                required 
                                type="tel" 
                                placeholder="+998 90 123 45 67"
                                class="w-full px-4 py-3.5 rounded-xl bg-black/50 border border-white/15 text-white placeholder-gray-500 text-sm focus:border-cyan-400 focus:outline-none focus:ring-1 focus:ring-cyan-400 font-mono"
                            />
                        </div>

                        <!-- Region and District -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">Viloyat</label>
                                <select 
                                    v-model="form.region" 
                                    class="w-full px-4 py-3.5 rounded-xl bg-black/50 border border-white/15 text-white text-sm focus:border-cyan-400 focus:outline-none"
                                >
                                    <option value="" disabled>Viloyatni tanlang</option>
                                    <option v-for="r in uzbekistanRegions" :key="r" :value="r">{{ r }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">Tuman / Shahar</label>
                                <input 
                                    v-model="form.district" 
                                    type="text" 
                                    placeholder="Tumaningiz"
                                    class="w-full px-4 py-3.5 rounded-xl bg-black/50 border border-white/15 text-white placeholder-gray-500 text-sm focus:border-cyan-400 focus:outline-none"
                                />
                            </div>
                        </div>

                        <!-- Comment / Selected Unit Spec -->
                        <div>
                            <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">Qiziqayotgan Xonadoningiz yoki Izoh</label>
                            <textarea 
                                v-model="form.comment" 
                                rows="3" 
                                placeholder="Qaysi blok, qavat yoki to'lov rejasi sizga ma'qul..."
                                class="w-full px-4 py-3 rounded-xl bg-black/50 border border-white/15 text-white placeholder-gray-500 text-sm focus:border-cyan-400 focus:outline-none resize-none"
                            ></textarea>
                        </div>

                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            :disabled="isSubmitting"
                            class="w-full py-4 rounded-xl bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-600 text-black font-extrabold text-sm sm:text-base uppercase tracking-wider shadow-[0_0_30px_rgba(6,182,212,0.4)] hover:scale-[1.02] transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            <span v-if="isSubmitting" class="w-5 h-5 border-2 border-black border-t-transparent rounded-full animate-spin"></span>
                            {{ isSubmitting ? 'Yuborilmoqda...' : 'Arizani Yuborish & Konsultatsiya Olish' }}
                        </button>

                    </form>

                </div>

            </div>

        </main>

        <!-- ========================================================= -->
        <!-- ULTRA 3D & BLUEPRINT ARCHITECTURAL INSPECTOR MODAL -->
        <!-- ========================================================= -->
        <div v-if="selectedUnit" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/85 backdrop-blur-xl overflow-y-auto">
            
            <div class="relative w-full max-w-4xl rounded-3xl bg-[#0d0d16] border border-white/20 shadow-[0_0_60px_rgba(245,158,11,0.25)] flex flex-col max-h-[92vh] overflow-hidden">
                
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between bg-white/[0.02]">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-400 flex items-center justify-center font-black">
                            3D
                        </div>
                        <div>
                            <h3 class="text-lg sm:text-xl font-bold text-white flex items-center gap-2">
                                №{{ selectedUnit.unit_number }} Xonadon — {{ selectedUnit.area }} m²
                            </h3>
                            <p class="text-xs text-gray-400">{{ selectedUnit.building_name }} • {{ selectedUnit.block_name }}-blok • {{ selectedUnit.floor_level }}-qavat</p>
                        </div>
                    </div>

                    <button @click="selectedUnit = null" class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-lg transition-colors">
                        ✕
                    </button>
                </div>

                <!-- Modal Body with Scroll -->
                <div class="p-6 overflow-y-auto space-y-6 flex-1">
                    
                    <!-- View Mode Toolbar inside Modal -->
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        
                        <div class="flex items-center p-1 rounded-xl bg-white/5 border border-white/10">
                            <button 
                                @click="activeDetailTab = '3d'"
                                :class="activeDetailTab === '3d' ? 'bg-amber-500 text-black font-extrabold' : 'text-gray-400 hover:text-white'"
                                class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-all"
                            >
                                🎮 3D Reja
                            </button>
                            <button 
                                @click="activeDetailTab = 'blueprint'"
                                :class="activeDetailTab === 'blueprint' ? 'bg-cyan-500 text-black font-extrabold' : 'text-gray-400 hover:text-white'"
                                class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-all"
                            >
                                📐 Arxitektura Chizmasi
                            </button>
                            <button 
                                @click="activeDetailTab = 'calc'"
                                :class="activeDetailTab === 'calc' ? 'bg-purple-500 text-white font-extrabold' : 'text-gray-400 hover:text-white'"
                                class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-all"
                            >
                                🧮 To'lov Kalkulyatori
                            </button>
                        </div>

                        <!-- Light / Dark Blueprint Background Switcher (User requested oq fon / ultra effect) -->
                        <div v-if="activeDetailTab === 'blueprint'" class="flex items-center gap-2 text-xs">
                            <span class="text-gray-400">Fon rejimi:</span>
                            <button 
                                @click="blueprintTheme = 'light'"
                                :class="blueprintTheme === 'light' ? 'bg-white text-black font-bold' : 'bg-white/10 text-gray-400'"
                                class="px-3 py-1 rounded-lg border border-white/20 transition-all"
                            >
                                ☀️ Oq Qog'oz Reja
                            </button>
                            <button 
                                @click="blueprintTheme = 'dark'"
                                :class="blueprintTheme === 'dark' ? 'bg-amber-500 text-black font-bold' : 'bg-white/10 text-gray-400'"
                                class="px-3 py-1 rounded-lg border border-white/20 transition-all"
                            >
                                🌙 Cyber Neon Reja
                            </button>
                        </div>

                    </div>

                    <!-- 3D VIEW SCREEN -->
                    <div v-show="activeDetailTab === '3d'" class="w-full h-80 sm:h-96 rounded-2xl bg-[#05050a] border border-amber-500/30 p-6 flex flex-col items-center justify-center relative overflow-hidden shadow-inner">
                        
                        <!-- Isometric 3D Model Visual Representation -->
                        <div class="relative w-full h-full flex items-center justify-center">
                            <svg viewBox="0 0 500 280" class="w-full h-full drop-shadow-[0_20px_30px_rgba(0,0,0,0.9)] animate-pulse">
                                <!-- 3D Isometric Base Floor -->
                                <polygon points="250,20 460,110 250,220 40,110" fill="#18182b" stroke="#f59e0b" stroke-width="3" />
                                
                                <!-- Living room 3D Zone -->
                                <polygon points="250,50 360,98 250,165 140,98" fill="#2a2254" stroke="#8b5cf6" stroke-width="2" />
                                <text x="250" y="105" fill="#ffffff" font-size="14" font-weight="bold" text-anchor="middle">🛋️ Mehmonxona ({{ (selectedUnit.area * 0.42).toFixed(1) }} m²)</text>
                                
                                <!-- Bedroom 3D Zone -->
                                <polygon points="140,98 250,165 180,200 70,125" fill="#1b2a4a" stroke="#06b6d4" stroke-width="2" />
                                <text x="160" y="160" fill="#ffffff" font-size="12" font-weight="bold" text-anchor="middle">🛏️ Yotoqxona</text>
                                
                                <!-- Kitchen & Bathroom 3D Zone -->
                                <polygon points="360,98 430,125 320,200 250,165" fill="#243d2c" stroke="#10b981" stroke-width="2" />
                                <text x="340" y="160" fill="#ffffff" font-size="12" font-weight="bold" text-anchor="middle">🍳 Oshxona & Sanuzel</text>
                            </svg>
                        </div>

                        <div class="absolute top-4 left-4 px-3 py-1 rounded-full bg-black/80 border border-amber-500/40 text-amber-400 text-xs font-mono">
                            ⚡ 3D Isometric View • Real Rejalashtirish
                        </div>
                    </div>

                    <!-- ARCHITECTURAL BLUEPRINT (OQ FON & QORONGI REJALAR) -->
                    <div 
                        v-show="activeDetailTab === 'blueprint'"
                        :class="blueprintTheme === 'light' ? 'bg-[#f8fafc] text-gray-900 border-gray-300 shadow-xl' : 'bg-[#080811] text-white border-cyan-500/40 shadow-inner'"
                        class="w-full p-6 rounded-2xl border transition-all duration-300 flex flex-col"
                    >
                        <div class="flex items-center justify-between mb-4 pb-3 border-b" :class="blueprintTheme === 'light' ? 'border-gray-200' : 'border-white/10'">
                            <span class="text-xs font-bold uppercase font-mono tracking-widest" :class="blueprintTheme === 'light' ? 'text-blue-700' : 'text-cyan-400'">
                                📐 Muhandislik Chizmasi va Xonalar Bo'linishi
                            </span>
                            <span class="text-xs font-mono font-bold" :class="blueprintTheme === 'light' ? 'text-gray-600' : 'text-gray-400'">
                                Masshtab 1:100 • Standart SNiP
                            </span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                            <div class="p-3 rounded-xl border" :class="blueprintTheme === 'light' ? 'bg-white border-gray-200' : 'bg-white/5 border-white/10'">
                                <div class="text-[10px] uppercase font-bold text-gray-400">Mehmonxona</div>
                                <div class="text-sm font-black">{{ (selectedUnit.area * 0.42).toFixed(1) }} m²</div>
                            </div>
                            <div class="p-3 rounded-xl border" :class="blueprintTheme === 'light' ? 'bg-white border-gray-200' : 'bg-white/5 border-white/10'">
                                <div class="text-[10px] uppercase font-bold text-gray-400">Yotoqxona</div>
                                <div class="text-sm font-black">{{ (selectedUnit.area * 0.33).toFixed(1) }} m²</div>
                            </div>
                            <div class="p-3 rounded-xl border" :class="blueprintTheme === 'light' ? 'bg-white border-gray-200' : 'bg-white/5 border-white/10'">
                                <div class="text-[10px] uppercase font-bold text-gray-400">Oshxona</div>
                                <div class="text-sm font-black">{{ (selectedUnit.area * 0.16).toFixed(1) }} m²</div>
                            </div>
                            <div class="p-3 rounded-xl border" :class="blueprintTheme === 'light' ? 'bg-white border-gray-200' : 'bg-white/5 border-white/10'">
                                <div class="text-[10px] uppercase font-bold text-gray-400">Sanuzel / Balkon</div>
                                <div class="text-sm font-black">{{ (selectedUnit.area * 0.09).toFixed(1) }} m²</div>
                            </div>
                        </div>

                        <!-- Vector Blueprint Render -->
                        <div class="w-full h-64 p-4 rounded-xl flex items-center justify-center" :class="blueprintTheme === 'light' ? 'bg-white border-2 border-dashed border-blue-400' : 'bg-black/60 border border-white/10'">
                            <svg viewBox="0 0 400 200" class="w-full h-full">
                                <rect x="10" y="10" width="380" height="180" fill="none" :stroke="blueprintTheme === 'light' ? '#2563eb' : '#06b6d4'" stroke-width="3" />
                                <line x1="220" y1="10" x2="220" y2="190" :stroke="blueprintTheme === 'light' ? '#2563eb' : '#06b6d4'" stroke-width="2" />
                                <line x1="220" y1="100" x2="390" y2="100" :stroke="blueprintTheme === 'light' ? '#2563eb' : '#06b6d4'" stroke-width="2" />
                                <text x="110" y="100" :fill="blueprintTheme === 'light' ? '#1e3a8a' : '#ffffff'" font-size="14" font-weight="bold" text-anchor="middle">Mehmonxona (Zal)</text>
                                <text x="305" y="60" :fill="blueprintTheme === 'light' ? '#1e3a8a' : '#ffffff'" font-size="12" font-weight="bold" text-anchor="middle">Yotoqxona</text>
                                <text x="305" y="150" :fill="blueprintTheme === 'light' ? '#1e3a8a' : '#ffffff'" font-size="12" font-weight="bold" text-anchor="middle">Oshxona</text>
                            </svg>
                        </div>
                    </div>

                    <!-- INSTALLMENT CALCULATOR (BO'LIB TO'LASH) -->
                    <div v-show="activeDetailTab === 'calc'" class="p-6 rounded-2xl bg-[#0a0a14] border border-purple-500/30 space-y-6">
                        <h4 class="text-base font-bold text-white flex items-center gap-2">
                            <span>🧮</span> Moslashuvchan Bo'lib To'lash Hisoblagichi
                        </h4>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1 font-semibold">Dastlabki to'lov miqdori (%):</label>
                                <div class="flex gap-2">
                                    <button 
                                        v-for="p in [20, 30, 50]" 
                                        :key="p"
                                        @click="downPaymentPercent = p"
                                        :class="downPaymentPercent === p ? 'bg-purple-600 text-white font-bold' : 'bg-white/5 text-gray-400'"
                                        class="flex-1 py-2 rounded-xl text-xs border border-white/10"
                                    >
                                        {{ p }}%
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs text-gray-400 mb-1 font-semibold">To'lov muddati (oy):</label>
                                <div class="flex gap-2">
                                    <button 
                                        v-for="m in [12, 24, 36, 48]" 
                                        :key="m"
                                        @click="paymentMonths = m"
                                        :class="paymentMonths === m ? 'bg-purple-600 text-white font-bold' : 'bg-white/5 text-gray-400'"
                                        class="flex-1 py-2 rounded-xl text-xs border border-white/10"
                                    >
                                        {{ m }} oy
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-purple-950/30 border border-purple-500/20 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-300">
                            <div>
                                <span class="text-gray-400 block">Dastlabki to'lov: {{ downPaymentPercent }}%</span>
                                <span class="text-sm font-bold text-amber-300">Imtiyozli 0% ustamasiz bo'lib to'lash</span>
                            </div>
                            <div class="text-right">
                                <span class="text-gray-400 block">Oylik to'lov:</span>
                                <span class="text-base font-black text-emerald-400">Shartnoma asosida teng taqsimot</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-3 bg-white/[0.02]">
                    <div class="text-xs text-gray-400">
                        Tanlangan: <strong class="text-white">{{ selectedUnit.rooms }} xona, {{ selectedUnit.area }} m²</strong>
                    </div>

                    <button 
                        @click="selectAndApply(selectedUnit)"
                        class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-black font-extrabold text-sm shadow-[0_0_20px_rgba(245,158,11,0.5)] transition-all flex items-center justify-center gap-2"
                    >
                        <span>✍️</span> Ushbu Xonadonga Ariza Qoldirish
                    </button>
                </div>

            </div>

        </div>

    </div>
</template>
