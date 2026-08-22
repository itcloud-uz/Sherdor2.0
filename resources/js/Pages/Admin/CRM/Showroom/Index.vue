<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    buildings: Array,
});

// Unique object names list
const uniqueObjectNames = computed(() => {
    if (!props.buildings) return [];
    return [...new Set(props.buildings.map(b => b.name))];
});

// Selected object name state
const selectedObjectName = ref(props.buildings?.length > 0 ? props.buildings[0].name : '');

// Blocks belonging to the selected object
const availableBlocks = computed(() => {
    if (!selectedObjectName.value || !props.buildings) return [];
    return props.buildings.filter(b => b.name === selectedObjectName.value);
});

// Selected block (building) ID state
const selectedBuildingId = ref(null);

watch(selectedObjectName, (newObjName) => {
    const firstBlock = props.buildings?.find(b => b.name === newObjName);
    if (firstBlock) {
        selectedBuildingId.value = firstBlock.id;
    } else {
        selectedBuildingId.value = null;
    }
}, { immediate: true });

const selectedBuilding = computed(() => props.buildings?.find(b => b.id === selectedBuildingId.value));

// Next block switcher helper
const goToNextBlock = () => {
    if (!availableBlocks.value || availableBlocks.value.length === 0) return;
    const currentIndex = availableBlocks.value.findIndex(b => b.id === selectedBuildingId.value);
    const nextIndex = (currentIndex + 1) % availableBlocks.value.length;
    selectedBuildingId.value = availableBlocks.value[nextIndex].id;
};

// View mode: 'facade' (Image 1 full-screen) vs 'shaxmatka' (Image 3 grid) vs 'floor_plan' (2D layout)
const viewMode = ref('facade');

// All floors sorted for selected building (e.g. 1 to 16)
const sortedFloors = computed(() => {
    if (!selectedBuilding.value) return [];
    let list = [];
    if (selectedBuilding.value.floors && selectedBuilding.value.floors.length > 0) {
        list = [...selectedBuilding.value.floors];
    } else if (selectedBuilding.value.podezes) {
        selectedBuilding.value.podezes.forEach(p => {
            if (p.floors) list.push(...p.floors);
        });
    }
    const unique = [];
    const map = new Map();
    for (const item of list) {
        if (!map.has(item.id)) {
            map.set(item.id, true);
            unique.push(item);
        }
    }
    return unique.sort((a, b) => a.floor_number - b.floor_number);
});

// 2 Columns distribution for Shaxmatka
const leftColumnFloors = computed(() => {
    const floors = [...sortedFloors.value];
    if (floors.length <= 8) return floors;
    const mid = Math.ceil(floors.length / 2);
    return floors.slice(0, mid);
});

const rightColumnFloors = computed(() => {
    const floors = [...sortedFloors.value];
    if (floors.length <= 8) return [];
    const mid = Math.ceil(floors.length / 2);
    return floors.slice(mid);
});

// Selected floor for 2D Floor Plan view
const selectedFloorForPlanId = ref(null);
const selectedFloorForPlan = computed(() => {
    return sortedFloors.value.find(f => f.id === selectedFloorForPlanId.value) || sortedFloors.value[0] || null;
});

const openFloorPlan = (floorId) => {
    selectedFloorForPlanId.value = floorId;
    viewMode.value = 'floor_plan';
};

// Selected unit for detailed modal
const selectedUnit = ref(null);
const showUnitDetailModal = ref(false);

const openUnitModal = (unit) => {
    selectedUnit.value = unit;
    showUnitDetailModal.value = true;
};

// Formatting helpers
const formatSoM = (val) => {
    if (!val && val !== 0) return '0 UZS';
    return new Intl.NumberFormat('uz-UZ').format(val) + ' UZS';
};

const getUnitColorClass = (unit) => {
    if (unit.status === 'available') return 'bg-emerald-500 hover:bg-emerald-600 text-white shadow-emerald-500/30';
    if (unit.status === 'sold') return 'bg-red-500 hover:bg-red-600 text-white shadow-red-500/30';
    if (unit.status === 'booked' || unit.status === 'reserved') return 'bg-amber-500 hover:bg-amber-600 text-white shadow-amber-500/30';
    return 'bg-gray-400 hover:bg-gray-500 text-white';
};

const getUnitBadgeBg = (unit) => {
    if (unit.status === 'available') return 'bg-emerald-500';
    if (unit.status === 'sold') return 'bg-red-500';
    if (unit.status === 'booked' || unit.status === 'reserved') return 'bg-amber-500';
    return 'bg-gray-400';
};

// Reliable Facade Image URL
const buildingImageUrl = computed(() => {
    if (!selectedBuilding.value) return '/images/default-building.jpg';
    if (selectedBuilding.value.facade_image) return '/storage/' + selectedBuilding.value.facade_image;
    if (selectedBuilding.value.photo_path) return '/storage/' + selectedBuilding.value.photo_path;
    if (selectedBuilding.value.showroom_images && selectedBuilding.value.showroom_images.length > 0) return '/storage/' + selectedBuilding.value.showroom_images[0];
    return '/images/default-building.jpg';
});

// Facade Image & Dimensions
const facadeImageRef = ref(null);
const facadeNaturalSize = ref({ width: 1200, height: 800 });

const onFacadeImageLoaded = (e) => {
    if (e.target && e.target.naturalWidth && e.target.naturalHeight) {
        facadeNaturalSize.value = {
            width: e.target.naturalWidth,
            height: e.target.naturalHeight
        };
    }
};

// Floor Plan Dimensions
const showroomBlueprintSize = ref({ width: 1200, height: 800 });
const onBlueprintImageLoaded = (e) => {
    if (e.target && e.target.naturalWidth && e.target.naturalHeight) {
        showroomBlueprintSize.value = {
            width: e.target.naturalWidth,
            height: e.target.naturalHeight
        };
    }
};

// Interactive Floor Hovering on Facade
const hoveredFloor = ref(null);

const onFloorHover = (floor) => {
    hoveredFloor.value = floor;
};

const onFloorLeave = () => {
    hoveredFloor.value = null;
};

// Parse facade polygon or calculate auto horizontal band
const getFloorFacadeShape = (floor, index, totalFloors) => {
    if (floor.facade_polygon) {
        try {
            const parsed = typeof floor.facade_polygon === 'object' ? floor.facade_polygon : JSON.parse(floor.facade_polygon);
            if (parsed.type === 'rect') {
                return {
                    type: 'rect',
                    x: parsed.left || 0,
                    y: parsed.top || 0,
                    width: parsed.width || facadeNaturalSize.value.width,
                    height: parsed.height || 40
                };
            }
            if (parsed.type === 'polygon' && parsed.points && parsed.points.length >= 3) {
                const ptsStr = parsed.points.map(p => `${p.x},${p.y}`).join(' ');
                return {
                    type: 'polygon',
                    points: ptsStr
                };
            }
        } catch(e) {}
    }

    // Auto-band fallback
    const w = facadeNaturalSize.value.width;
    const h = facadeNaturalSize.value.height;
    const total = Math.max(totalFloors, 1);
    const bandHeight = (h * 0.80) / total;
    const topMargin = h * 0.08;
    const floorIndexFromTop = total - (index + 1);
    const y = topMargin + (floorIndexFromTop * bandHeight);

    return {
        type: 'rect',
        x: Math.round(w * 0.12),
        y: Math.round(y),
        width: Math.round(w * 0.76),
        height: Math.round(bandHeight * 0.94)
    };
};

// Floor statistics for Card (Image 1)
const getFloorStats = (floor) => {
    if (!floor) return { total: 0, available: 0, reserved: 0, sold: 0 };
    const units = floor.units || [];
    const total = units.length;
    const available = units.filter(u => u.status === 'available').length;
    const reserved = units.filter(u => u.status === 'booked' || u.status === 'reserved').length;
    const sold = units.filter(u => u.status === 'sold').length;
    return { total, available, reserved, sold };
};

// Location string helper
const getBuildingLocationString = (b) => {
    if (!b) return "Toshkent viloyati, Zangiota tumani";
    const parts = [b.city, b.district, b.mahalla, b.street, b.address].filter(Boolean);
    return parts.length > 0 ? parts.join(', ') : "Toshkent viloyati, Zangiota tumani";
};

// Filter State for Shaxmatka
const showFilterDropdown = ref(false);
const filterStatus = ref('');
const filterRooms = ref('');
const searchQuery = ref('');

const resetFilters = () => {
    filterStatus.value = '';
    filterRooms.value = '';
    searchQuery.value = '';
};

const filterUnitsList = (units) => {
    if (!units) return [];
    return units.filter(u => {
        if (filterStatus.value && u.status !== filterStatus.value) return false;
        if (filterRooms.value && Number(u.rooms_count) !== Number(filterRooms.value)) return false;
        if (searchQuery.value && !String(u.unit_number).toLowerCase().includes(searchQuery.value.toLowerCase())) return false;
        return true;
    });
};
</script>

<template>
    <Head title="Shou room - 3D Maket" />
    
    <!-- 100% FULL SCREEN STANDALONE APP (No admin sidebar wrapper) -->
    <div class="fixed inset-0 z-50 flex flex-col w-screen h-screen overflow-hidden select-none bg-slate-900 font-sans">

        <!-- ========================================================================= -->
        <!-- 1. FULL SCREEN 3D BINO FASADI (EXACT REPLICA OF USER IMAGE 1)             -->
        <!-- ========================================================================= -->
        <div v-if="viewMode === 'facade'" class="flex-1 flex flex-col relative w-full h-full overflow-hidden bg-slate-950">
            
            <!-- Top Translucent Navigation Bar (Image 1) -->
            <div class="absolute top-0 left-0 right-0 z-40 flex items-center justify-between px-8 py-4 bg-slate-900/60 backdrop-blur-md border-b border-white/10 text-white">
                
                <!-- Left: Ortga Qaytish (Returns to Dashboard / Admin Panel Image 2) -->
                <Link 
                    :href="route('admin.dashboard')" 
                    class="px-4 py-2 rounded-xl text-xs font-bold text-white/90 hover:text-white bg-white/10 hover:bg-white/20 border border-white/20 transition-all flex items-center gap-2 shadow-sm"
                >
                    <span class="text-sm">←</span>
                    <span>ortga qaytish</span>
                </Link>

                <!-- Center: Blok Shaxmatkasiga o'tish Button -->
                <button 
                    @click="viewMode = 'shaxmatka'"
                    class="px-6 py-2.5 bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 hover:from-amber-600 hover:to-orange-600 text-white text-xs font-black rounded-xl shadow-lg transition-all flex items-center gap-2 hover:scale-105 active:scale-95"
                >
                    <span>📊</span>
                    <span>Blok Shaxmatkasiga o'tish</span>
                </button>

                <!-- Right: Object/Block Switcher & Next Block Button -->
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 bg-black/40 px-3 py-1.5 rounded-xl border border-white/15">
                        <select v-model="selectedObjectName" class="bg-transparent border-0 text-xs font-bold text-white focus:ring-0 cursor-pointer py-0 pl-1 pr-6">
                            <option v-for="name in uniqueObjectNames" :key="name" :value="name" class="bg-slate-900 text-white">{{ name }}</option>
                        </select>
                        <span class="text-white/40">|</span>
                        <select v-model="selectedBuildingId" class="bg-transparent border-0 text-xs font-bold text-amber-400 focus:ring-0 cursor-pointer py-0 pl-1 pr-6">
                            <option v-for="b in availableBlocks" :key="b.id" :value="b.id" class="bg-slate-900 text-white">
                                {{ b.block_name || b.corpus_name || b.name }}
                            </option>
                        </select>
                    </div>

                    <button 
                        @click="goToNextBlock"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-white/90 hover:text-white bg-white/10 hover:bg-white/20 border border-white/20 transition-all flex items-center gap-1.5 shadow-sm"
                    >
                        <span>Keyingi blok</span>
                        <span class="text-sm">→</span>
                    </button>
                </div>
            </div>

            <!-- Main 3D Building Facade Viewport -->
            <div class="flex-1 w-full h-full flex items-center justify-center pt-16 pb-4 px-4 overflow-hidden relative">
                <div class="relative max-h-[92vh] max-w-full flex items-center justify-center" v-if="selectedBuilding">
                    
                    <!-- Building 3D Image -->
                    <div class="relative inline-block max-w-full max-h-[92vh] rounded-2xl overflow-hidden shadow-2xl">
                        <img 
                            ref="facadeImageRef"
                            :src="buildingImageUrl"
                            @error="(e) => e.target.src = '/images/default-building.jpg'"
                            @load="onFacadeImageLoaded"
                            class="block max-h-[92vh] w-auto h-auto object-contain transition-all duration-300"
                        />

                        <!-- Interactive Floor SVG Layer -->
                        <svg 
                            class="absolute inset-0 w-full h-full pointer-events-auto" 
                            :viewBox="`0 0 ${facadeNaturalSize.width} ${facadeNaturalSize.height}`"
                            preserveAspectRatio="none"
                        >
                            <g 
                                v-for="(floor, idx) in sortedFloors" 
                                :key="'facade-fl-' + floor.id"
                                class="cursor-pointer transition-all duration-200"
                                @mouseenter="onFloorHover(floor)"
                                @mouseleave="onFloorLeave"
                                @click="viewMode = 'shaxmatka'"
                            >
                                <template v-if="getFloorFacadeShape(floor, idx, sortedFloors.length)">
                                    <rect 
                                        v-if="getFloorFacadeShape(floor, idx, sortedFloors.length).type === 'rect'"
                                        :x="getFloorFacadeShape(floor, idx, sortedFloors.length).x"
                                        :y="getFloorFacadeShape(floor, idx, sortedFloors.length).y"
                                        :width="getFloorFacadeShape(floor, idx, sortedFloors.length).width"
                                        :height="getFloorFacadeShape(floor, idx, sortedFloors.length).height"
                                        :fill="hoveredFloor?.id === floor.id ? 'rgba(100, 116, 139, 0.60)' : 'rgba(255, 255, 255, 0.01)'"
                                        :stroke="hoveredFloor?.id === floor.id ? '#94a3b8' : 'transparent'"
                                        :stroke-width="hoveredFloor?.id === floor.id ? 2 : 0"
                                        class="transition-all duration-200"
                                    />

                                    <polygon 
                                        v-else-if="getFloorFacadeShape(floor, idx, sortedFloors.length).type === 'polygon'"
                                        :points="getFloorFacadeShape(floor, idx, sortedFloors.length).points"
                                        :fill="hoveredFloor?.id === floor.id ? 'rgba(100, 116, 139, 0.60)' : 'rgba(255, 255, 255, 0.01)'"
                                        :stroke="hoveredFloor?.id === floor.id ? '#94a3b8' : 'transparent'"
                                        :stroke-width="hoveredFloor?.id === floor.id ? 2 : 0"
                                        class="transition-all duration-200"
                                    />
                                </template>
                            </g>
                        </svg>
                    </div>

                    <!-- EXACT CARD & GREEN CONNECTOR LINE (IMAGE 1) -->
                    <transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 translate-x-6 scale-95"
                        enter-to-class="opacity-100 translate-x-0 scale-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100 translate-x-0 scale-100"
                        leave-to-class="opacity-0 translate-x-6 scale-95"
                    >
                        <div 
                            v-if="hoveredFloor"
                            class="absolute right-4 md:right-12 top-1/4 z-50 w-84 bg-white/95 backdrop-blur-2xl border border-white/60 shadow-2xl rounded-3xl p-6 text-gray-800 pointer-events-auto"
                        >
                            <!-- Green Angled Pointer Line (Exact Image 1) -->
                            <div class="absolute -left-16 top-6 w-16 h-1 bg-[#10b981] hidden md:block">
                                <div class="absolute -left-2 -top-1.5 w-4 h-4 rounded-full bg-[#10b981] ring-4 ring-emerald-400/40"></div>
                            </div>

                            <!-- Header: Blok Name & Qavat Badge -->
                            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                                <h3 class="text-xl font-black text-[#1e40af] tracking-tight">
                                    {{ selectedBuilding.block_name || selectedBuilding.corpus_name || 'A-1 Blok' }}
                                </h3>
                                <span class="px-3 py-1 bg-emerald-100/80 text-emerald-700 rounded-xl text-xs font-black tracking-wide border border-emerald-300">
                                    {{ hoveredFloor.floor_number < 0 ? `${hoveredFloor.floor_number}-Qavat` : `${hoveredFloor.floor_number}-Qavat` }}
                                </span>
                            </div>

                            <!-- Rows matching Image 1 exact content -->
                            <div class="py-4 space-y-3 text-xs font-bold">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 font-extrabold">Sotuvda mavjud :</span>
                                    <span class="text-emerald-600 font-black">{{ getFloorStats(hoveredFloor).available }} xonadon</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 font-extrabold">Sotilgan :</span>
                                    <span class="text-red-600 font-black">{{ getFloorStats(hoveredFloor).sold }} xonadon</span>
                                </div>
                                <div class="flex items-start justify-between gap-4">
                                    <span class="text-gray-600 font-extrabold shrink-0">Joylashuv :</span>
                                    <span class="text-[#2563eb] font-extrabold text-right">{{ getBuildingLocationString(selectedBuilding) }}</span>
                                </div>
                                <div class="flex items-center justify-between pt-1">
                                    <span class="text-gray-600 font-extrabold">Statusi :</span>
                                    <span class="px-2.5 py-1 bg-amber-50 text-amber-600 rounded-lg font-black border border-amber-200">
                                        {{ selectedBuilding.status || 'Qurilish jarayonida' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Action button -->
                            <div class="pt-2">
                                <button 
                                    @click="viewMode = 'shaxmatka'"
                                    class="w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold text-xs rounded-2xl shadow-xl transition-all flex items-center justify-center gap-2"
                                >
                                    <span>Shaxmatkaga o'tish</span>
                                    <span>→</span>
                                </button>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 2. FULL SCREEN BLOK SHAXMATKASI (EXACT REPLICA OF USER IMAGE 3)           -->
        <!-- ========================================================================= -->
        <div v-else-if="viewMode === 'shaxmatka'" class="flex-1 flex flex-col relative w-full h-full overflow-hidden bg-slate-900">
            
            <!-- Background: Crisp Building Render -->
            <div class="absolute inset-0 z-0">
                <img 
                    :src="buildingImageUrl"
                    @error="(e) => e.target.src = '/images/default-building.jpg'"
                    class="w-full h-full object-cover object-center opacity-70"
                />
                <div class="absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]"></div>
            </div>

            <!-- Top Header Bar (Exact Image 3) -->
            <div class="relative z-20 flex flex-wrap items-center justify-between gap-4 px-8 py-3.5 bg-slate-900/70 backdrop-blur-md border-b border-white/10 text-white">
                <div class="flex items-center gap-4">
                    <button 
                        @click="viewMode = 'facade'"
                        class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl border border-white/20 transition-all flex items-center gap-1.5 shadow-sm"
                    >
                        <span>←</span> ortga qaytish
                    </button>

                    <!-- Filter Button -->
                    <div class="relative">
                        <button 
                            @click="showFilterDropdown = !showFilterDropdown"
                            class="px-5 py-2 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white text-xs font-extrabold rounded-xl shadow-md transition-all flex items-center gap-1.5"
                        >
                            <span>⚡</span> Filter
                        </button>

                        <div v-if="showFilterDropdown" class="absolute left-0 mt-2 w-72 bg-white rounded-2xl shadow-2xl border border-gray-200 p-4 z-50 text-gray-800 space-y-3">
                            <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                                <h4 class="font-extrabold text-xs uppercase tracking-wider text-gray-600">Qidiruv va Filter</h4>
                                <button @click="showFilterDropdown = false" class="text-gray-400 hover:text-gray-700">✕</button>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 block mb-1">Xonadon raqami:</label>
                                <input v-model="searchQuery" placeholder="Masalan: A-1-D1..." class="w-full text-xs font-bold rounded-lg border-gray-300 p-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 block mb-1">Holati:</label>
                                <select v-model="filterStatus" class="w-full text-xs font-bold rounded-lg border-gray-300 p-2 focus:ring-blue-500">
                                    <option value="">Barchasi</option>
                                    <option value="available">Sotuvda</option>
                                    <option value="reserved">Bron qilingan</option>
                                    <option value="sold">Sotilgan</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 block mb-1">Xonalar soni:</label>
                                <select v-model="filterRooms" class="w-full text-xs font-bold rounded-lg border-gray-300 p-2 focus:ring-blue-500">
                                    <option value="">Barchasi</option>
                                    <option value="1">1 xona</option>
                                    <option value="2">2 xona</option>
                                    <option value="3">3 xona</option>
                                    <option value="4">4+ xona</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <button 
                        v-if="searchQuery || filterStatus || filterRooms"
                        @click="resetFilters"
                        class="text-xs font-bold text-white/80 hover:text-white flex items-center gap-1"
                    >
                        <span>✕</span> Filterni tozalash
                    </button>
                </div>

                <!-- Status Legends (Image 3) -->
                <div class="flex items-center gap-6 text-xs font-black text-white">
                    <div class="flex items-center gap-2">
                        <span class="w-3.5 h-3.5 rounded-full bg-emerald-500 block shadow"></span>
                        <span>Sotuvda</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3.5 h-3.5 rounded-full bg-red-500 block shadow"></span>
                        <span>Sotilgan</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3.5 h-3.5 rounded-full bg-amber-500 block shadow"></span>
                        <span>Bron qilingan</span>
                    </div>
                </div>

                <!-- Next Block Button -->
                <div class="flex items-center gap-3">
                    <button 
                        @click="goToNextBlock"
                        class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl border border-white/20 transition-all flex items-center gap-1.5 shadow-sm"
                    >
                        <span>Keyingi blok</span>
                        <span>→</span>
                    </button>
                </div>
            </div>

            <!-- Shaxmatka 2-Columns Grid Layout -->
            <div class="relative z-10 flex-1 overflow-auto p-8 lg:p-12">
                <!-- Large Orange Block Header Pill (Image 3: "A-1 blok") -->
                <div class="mb-8">
                    <div class="inline-flex items-center px-10 py-3.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-3xl font-black text-3xl tracking-wide shadow-2xl border border-amber-300/40">
                        {{ selectedBuilding.block_name || selectedBuilding.corpus_name || 'A-1 blok' }}
                    </div>
                </div>

                <!-- 2-Columns Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16 gap-y-5 max-w-7xl">
                    <!-- Left Column (Floors 1 to 8) -->
                    <div class="space-y-4">
                        <div 
                            v-for="floor in leftColumnFloors" 
                            :key="'col1-fl-' + floor.id"
                            class="flex items-center gap-4 bg-slate-900/70 hover:bg-slate-900/85 backdrop-blur-md p-2.5 rounded-2xl border border-white/15 transition-all shadow-lg"
                        >
                            <!-- Orange Floor Pill Button (Image 3) -->
                            <button 
                                @click="openFloorPlan(floor.id)"
                                title="Qavat chizmasini ko'rish"
                                class="px-6 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-black text-sm rounded-xl shadow-md shrink-0 min-w-[130px] text-center transition-transform hover:scale-105 active:scale-95"
                            >
                                {{ floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat` }}
                            </button>

                            <!-- Unit Badges Row -->
                            <div class="flex flex-wrap items-center gap-2.5 flex-1">
                                <button 
                                    v-for="unit in filterUnitsList(floor.units)" 
                                    :key="'unit-btn-' + unit.id"
                                    @click="openUnitModal(unit)"
                                    :class="getUnitColorClass(unit)"
                                    class="px-4 py-2.5 rounded-xl font-black text-xs tracking-wider shadow-md transition-all duration-150 hover:scale-110 active:scale-95 flex items-center justify-center shrink-0 min-w-[76px]"
                                >
                                    {{ unit.unit_number }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column (Floors 9 to 16) -->
                    <div class="space-y-4" v-if="rightColumnFloors.length > 0">
                        <div 
                            v-for="floor in rightColumnFloors" 
                            :key="'col2-fl-' + floor.id"
                            class="flex items-center gap-4 bg-slate-900/70 hover:bg-slate-900/85 backdrop-blur-md p-2.5 rounded-2xl border border-white/15 transition-all shadow-lg"
                        >
                            <!-- Orange Floor Pill Button (Image 3) -->
                            <button 
                                @click="openFloorPlan(floor.id)"
                                title="Qavat chizmasini ko'rish"
                                class="px-6 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-black text-sm rounded-xl shadow-md shrink-0 min-w-[130px] text-center transition-transform hover:scale-105 active:scale-95"
                            >
                                {{ floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat` }}
                            </button>

                            <!-- Unit Badges Row -->
                            <div class="flex flex-wrap items-center gap-2.5 flex-1">
                                <button 
                                    v-for="unit in filterUnitsList(floor.units)" 
                                    :key="'unit-btn-r-' + unit.id"
                                    @click="openUnitModal(unit)"
                                    :class="getUnitColorClass(unit)"
                                    class="px-4 py-2.5 rounded-xl font-black text-xs tracking-wider shadow-md transition-all duration-150 hover:scale-110 active:scale-95 flex items-center justify-center shrink-0 min-w-[76px]"
                                >
                                    {{ unit.unit_number }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 3. 2D QAVAT CHIZMASI                                                      -->
        <!-- ========================================================================= -->
        <div v-else-if="viewMode === 'floor_plan'" class="flex-1 flex flex-col w-full h-full overflow-hidden bg-slate-950 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4 bg-white/10 backdrop-blur-md p-3.5 rounded-2xl border border-white/10 text-white">
                <div class="flex items-center gap-3">
                    <button 
                        @click="viewMode = 'shaxmatka'"
                        class="px-3.5 py-1.5 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl border border-white/20 transition-all flex items-center gap-1"
                    >
                        <span>←</span> Shaxmatkaga qaytish
                    </button>

                    <div class="flex items-center gap-2 overflow-x-auto max-w-xl pb-1">
                        <button 
                            v-for="floor in sortedFloors" 
                            :key="'fl-plan-btn-' + floor.id"
                            @click="selectedFloorForPlanId = floor.id"
                            :class="selectedFloorForPlanId === floor.id ? 'bg-amber-500 text-white font-extrabold shadow-lg' : 'bg-white/10 text-white/80 hover:bg-white/20'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 border border-white/10"
                        >
                            {{ floor.floor_number < 0 ? `${floor.floor_number} yerto'la` : `${floor.floor_number}-qavat` }}
                        </button>
                    </div>
                </div>

                <Link :href="route('admin.facility.floor_plan_editor', { building_id: selectedBuilding.id, floor_id: selectedFloorForPlanId })" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow transition-all flex items-center gap-1">
                    <span>✏️</span> Chizmani Tahrirlash
                </Link>
            </div>

            <div class="flex-1 bg-slate-900 rounded-2xl border border-slate-800 overflow-auto relative flex items-center justify-center p-4">
                <div class="relative inline-block max-w-full max-h-full" v-if="selectedFloorForPlan?.blueprint_path || selectedBuilding.blueprint_path">
                    <img 
                        :src="'/storage/' + (selectedFloorForPlan?.blueprint_path || selectedBuilding.blueprint_path)" 
                        @load="onBlueprintImageLoaded"
                        class="block max-w-full max-h-[72vh] w-auto h-auto object-contain pointer-events-none select-none opacity-95 rounded-xl shadow-2xl"
                    />

                    <svg 
                        class="absolute inset-0 w-full h-full pointer-events-auto" 
                        :viewBox="`0 0 ${showroomBlueprintSize.width} ${showroomBlueprintSize.height}`"
                        preserveAspectRatio="none"
                    >
                        <g v-for="unit in (selectedFloorForPlan?.units || [])" :key="'plan-unit-' + unit.id">
                            <template v-if="unit.svg_polygon">
                                <rect 
                                    v-if="JSON.parse(unit.svg_polygon).type === 'rect'"
                                    :x="JSON.parse(unit.svg_polygon).left" 
                                    :y="JSON.parse(unit.svg_polygon).top" 
                                    :width="JSON.parse(unit.svg_polygon).width" 
                                    :height="JSON.parse(unit.svg_polygon).height"
                                    :fill="unit.status === 'available' ? 'rgba(16,185,129,0.5)' : (unit.status === 'sold' ? 'rgba(239,68,68,0.5)' : 'rgba(245,158,11,0.5)')"
                                    :stroke="unit.status === 'available' ? '#10b981' : (unit.status === 'sold' ? '#ef4444' : '#f59e0b')"
                                    stroke-width="2.5"
                                    rx="4"
                                    @click="openUnitModal(unit)"
                                    class="cursor-pointer hover:opacity-90 transition-all"
                                />
                            </template>
                        </g>
                    </svg>
                </div>
            </div>
        </div>

    </div>

    <!-- Unit Detailed Modal -->
    <Teleport to="body">
        <div v-if="showUnitDetailModal && selectedUnit" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="bg-gradient-to-r from-slate-900 to-slate-800 p-6 text-white flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-black text-lg text-white shadow-lg" :class="getUnitBadgeBg(selectedUnit)">
                            {{ selectedUnit.unit_number }}
                        </div>
                        <div>
                            <h3 class="font-extrabold text-base tracking-tight">Xonadon № {{ selectedUnit.unit_number }}</h3>
                            <span class="text-xs text-slate-400">{{ selectedBuilding?.name }} - {{ selectedBuilding?.block_name || 'Blok' }}</span>
                        </div>
                    </div>
                    <button @click="showUnitDetailModal = false" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all">
                        ✕
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-3.5 rounded-2xl border border-gray-100">
                            <span class="text-[11px] font-bold text-gray-400 block uppercase">Holati</span>
                            <span class="text-xs font-black uppercase mt-1 inline-block" :class="selectedUnit.status === 'available' ? 'text-emerald-600' : (selectedUnit.status === 'sold' ? 'text-red-600' : 'text-amber-600')">
                                {{ selectedUnit.status === 'available' ? 'Sotuvda' : (selectedUnit.status === 'sold' ? 'Sotilgan' : 'Bron qilingan') }}
                            </span>
                        </div>
                        <div class="bg-gray-50 p-3.5 rounded-2xl border border-gray-100">
                            <span class="text-[11px] font-bold text-gray-400 block uppercase">Maydoni</span>
                            <span class="text-sm font-black text-gray-800 mt-1 inline-block">{{ selectedUnit.area_sqm }} m²</span>
                        </div>
                        <div class="bg-gray-50 p-3.5 rounded-2xl border border-gray-100">
                            <span class="text-[11px] font-bold text-gray-400 block uppercase">Xonalar</span>
                            <span class="text-sm font-black text-gray-800 mt-1 inline-block">{{ selectedUnit.rooms_count || 1 }} xona</span>
                        </div>
                        <div class="bg-gray-50 p-3.5 rounded-2xl border border-gray-100">
                            <span class="text-[11px] font-bold text-gray-400 block uppercase">Podyezd</span>
                            <span class="text-sm font-black text-gray-800 mt-1 inline-block">{{ selectedUnit.podyezd || 1 }}-podyezd</span>
                        </div>
                    </div>

                    <div class="bg-blue-50/70 border border-blue-100 rounded-2xl p-4 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-blue-500 uppercase tracking-wider block">Umumiy Narxi</span>
                            <span class="text-lg font-black text-blue-700 tracking-tight">{{ formatSoM(selectedUnit.price) }}</span>
                        </div>
                        <div v-if="selectedUnit.area_sqm > 0 && selectedUnit.price > 0" class="text-right">
                            <span class="text-[10px] font-bold text-gray-400 block">1 m² narxi</span>
                            <span class="text-xs font-extrabold text-gray-700">{{ formatSoM(Math.round(selectedUnit.price / selectedUnit.area_sqm)) }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-6 pt-0 flex items-center gap-3">
                    <Link 
                        v-if="selectedUnit.status === 'available'" 
                        :href="route('admin.crm', { unit_id: selectedUnit.id })" 
                        class="flex-1 py-3.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold text-xs rounded-2xl shadow-xl hover:shadow-2xl transition-all text-center flex items-center justify-center gap-2"
                    >
                        <span>📝 Sotish (Shartnoma tuzish)</span>
                    </Link>
                    <button 
                        @click="showUnitDetailModal = false"
                        class="px-5 py-3.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs rounded-2xl transition-all"
                    >
                        Yopish
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
