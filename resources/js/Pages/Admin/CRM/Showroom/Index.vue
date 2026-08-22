<script setup>
import { Head, usePage, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SellerLayout from '@/Layouts/SellerLayout.vue';

const props = defineProps({
    buildings: Array,
});

const page = usePage();

const isSellerOrOperator = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return false;
    const rawRoles = user.roles || [];
    const roles = rawRoles.map(r => typeof r === 'string' ? r : r.name);
    return roles.includes('Sotuvchi') || roles.includes('Operator');
});

const currentLayout = computed(() => isSellerOrOperator.value ? SellerLayout : AdminLayout);

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

// Watch object name changes to set selectedBuildingId to the first block
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

// View mode: 'facade' (2-rasm), 'shaxmatka' (3-rasm), 'floor_plan' (2D qavat)
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
    // Remove duplicates by id if any
    const unique = [];
    const map = new Map();
    for (const item of list) {
        if(!map.has(item.id)){
            map.set(item.id, true);
            unique.push(item);
        }
    }
    return unique.sort((a, b) => a.floor_number - b.floor_number);
});

// 2 Columns distribution for Shaxmatka (Image 3)
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

// Selected unit for detailed drawer/modal
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

// Facade Image & Dynamic Dimensions
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

// Blueprint Natural Size for Floor Plan View
const showroomBlueprintSize = ref({ width: 1200, height: 800 });
const onBlueprintImageLoaded = (e) => {
    if (e.target && e.target.naturalWidth && e.target.naturalHeight) {
        showroomBlueprintSize.value = {
            width: e.target.naturalWidth,
            height: e.target.naturalHeight
        };
    }
};

// Interactive Floor Hovering on Facade (Image 2)
const hoveredFloor = ref(null);
const tooltipPosition = ref({ x: 0, y: 0, percentX: 85, percentY: 20 });

const onFloorHover = (floor, event) => {
    hoveredFloor.value = floor;
    if (event && event.currentTarget) {
        const rect = event.currentTarget.getBoundingClientRect();
        tooltipPosition.value = {
            x: rect.right,
            y: rect.top + rect.height / 2
        };
    }
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

    // Smart Fallback Auto-band across building height
    const w = facadeNaturalSize.value.width;
    const h = facadeNaturalSize.value.height;
    const total = Math.max(totalFloors, 1);
    const bandHeight = (h * 0.78) / total;
    const topMargin = h * 0.10;
    const floorIndexFromTop = total - (index + 1);
    const y = topMargin + (floorIndexFromTop * bandHeight);

    return {
        type: 'rect',
        x: Math.round(w * 0.15),
        y: Math.round(y),
        width: Math.round(w * 0.70),
        height: Math.round(bandHeight * 0.92)
    };
};

// Floor statistics for Tooltip (Image 2)
const getFloorStats = (floor) => {
    if (!floor) return { total: 0, available: 0, reserved: 0, sold: 0, minPrice: 0, maxPrice: 0 };
    const units = floor.units || [];
    const total = units.length;
    const available = units.filter(u => u.status === 'available').length;
    const reserved = units.filter(u => u.status === 'booked' || u.status === 'reserved').length;
    const sold = units.filter(u => u.status === 'sold').length;
    
    const prices = units.map(u => Number(u.price) || 0).filter(p => p > 0);
    const minPrice = prices.length > 0 ? Math.min(...prices) : 0;
    const maxPrice = prices.length > 0 ? Math.max(...prices) : 0;

    return { total, available, reserved, sold, minPrice, maxPrice };
};

// Parse unit 2D floor plan shape
const parseUnitShape = (unit) => {
    if (!unit || !unit.svg_polygon) return null;
    try {
        const data = typeof unit.svg_polygon === 'object' ? unit.svg_polygon : JSON.parse(unit.svg_polygon);
        if (data.type === 'rect') {
            return {
                type: 'rect',
                x: data.left || 0,
                y: data.top || 0,
                width: data.width || 100,
                height: data.height || 80,
                centerX: (data.left || 0) + (data.width || 100) / 2,
                centerY: (data.top || 0) + (data.height || 80) / 2
            };
        }
        if (data.type === 'polygon' && data.points && data.points.length >= 3) {
            const ptsStr = data.points.map(p => `${p.x},${p.y}`).join(' ');
            const avgX = data.points.reduce((s, p) => s + p.x, 0) / data.points.length;
            const avgY = data.points.reduce((s, p) => s + p.y, 0) / data.points.length;
            return {
                type: 'polygon',
                points: ptsStr,
                centerX: avgX,
                centerY: avgY
            };
        }
    } catch(e) {
        return null;
    }
    return null;
};

const getSvgFillColor = (unit) => {
    if (unit.status === 'available') return 'rgba(16, 185, 129, 0.55)';
    if (unit.status === 'sold') return 'rgba(239, 68, 68, 0.55)';
    if (unit.status === 'booked' || unit.status === 'reserved') return 'rgba(245, 158, 11, 0.55)';
    return 'rgba(156, 163, 175, 0.45)';
};

const getSvgStrokeColor = (unit) => {
    if (unit.status === 'available') return '#10b981';
    if (unit.status === 'sold') return '#ef4444';
    if (unit.status === 'booked' || unit.status === 'reserved') return '#f59e0b';
    return '#9ca3af';
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
    <component :is="currentLayout">
        <template #header>
            <div class="flex items-center justify-between w-full">
                <span class="font-extrabold text-xl text-gray-800 tracking-tight">Shou room</span>
            </div>
        </template>

        <div class="h-full flex flex-col gap-5">
            <!-- Top Selector Bar (Object and Block Selector) -->
            <div class="flex flex-wrap items-center justify-between gap-4 bg-white p-4 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex flex-wrap items-center gap-6">
                    <div class="flex items-center gap-3">
                        <label class="text-xs font-extrabold text-gray-500 uppercase tracking-wider whitespace-nowrap">Obyekt tanlang:</label>
                        <select v-model="selectedObjectName" class="border-gray-200 rounded-xl text-sm font-bold text-gray-800 focus:ring-blue-500 focus:border-blue-500 px-4 py-2.5 min-w-[220px] shadow-sm">
                            <option v-for="name in uniqueObjectNames" :key="name" :value="name">
                                {{ name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center gap-3" v-if="selectedObjectName">
                        <label class="text-xs font-extrabold text-gray-500 uppercase tracking-wider whitespace-nowrap">Blok tanlang:</label>
                        <select v-model="selectedBuildingId" class="border-gray-200 rounded-xl text-sm font-bold text-gray-800 focus:ring-blue-500 focus:border-blue-500 px-4 py-2.5 min-w-[200px] shadow-sm">
                            <option v-for="building in availableBlocks" :key="building.id" :value="building.id">
                                {{ building.block_name || building.corpus_name || building.name || 'Asosiy Blok' }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Navigation Tabs / Mode Controls -->
                <div class="flex items-center gap-2 bg-gray-100 p-1.5 rounded-xl border border-gray-200">
                    <button
                        @click="viewMode = 'facade'"
                        class="px-4 py-2 rounded-lg text-xs font-extrabold transition-all flex items-center gap-1.5"
                        :class="viewMode === 'facade' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    >
                        <span>🏢</span> 3D Bino Fasadi
                    </button>
                    <button
                        @click="viewMode = 'shaxmatka'"
                        class="px-4 py-2 rounded-lg text-xs font-extrabold transition-all flex items-center gap-1.5"
                        :class="viewMode === 'shaxmatka' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    >
                        <span>📊</span> Blok Shaxmatkasi
                    </button>
                    <button
                        @click="viewMode = 'floor_plan'"
                        class="px-4 py-2 rounded-lg text-xs font-extrabold transition-all flex items-center gap-1.5"
                        :class="viewMode === 'floor_plan' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    >
                        <span>📐</span> Qavat Chizmasi (2D)
                    </button>
                </div>
            </div>

            <!-- Main Interactive Container -->
            <div v-if="selectedBuilding" class="flex-1 flex flex-col min-h-0 bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden relative">

                <!-- ========================================================================= -->
                <!-- SCREEN 1: 3D BINO FASADI VA QAVAT TANLASH (IMAGE 2)                        -->
                <!-- ========================================================================= -->
                <div v-if="viewMode === 'facade'" class="flex-1 flex flex-col items-center justify-center p-6 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 relative overflow-hidden select-none">
                    
                    <!-- Background Glow & Stars effect -->
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(59,130,246,0.08)_0,transparent_70%)] pointer-events-none"></div>

                    <!-- Building Badge Top-Left -->
                    <div class="absolute top-6 left-6 z-20 flex items-center gap-3">
                        <div class="px-5 py-2.5 bg-white/10 backdrop-blur-md rounded-2xl border border-white/20 shadow-xl text-white">
                            <span class="text-xs font-bold text-blue-300 uppercase tracking-widest block">Tanlangan Blok</span>
                            <span class="text-xl font-extrabold tracking-tight">{{ selectedBuilding.block_name || selectedBuilding.corpus_name || selectedBuilding.name }}</span>
                        </div>
                    </div>

                    <!-- Top-Right Actions -->
                    <div class="absolute top-6 right-6 z-20 flex items-center gap-3">
                        <Link :href="route('admin.facility.floor_plan_editor', { building_id: selectedBuilding.id })" class="px-4 py-2.5 bg-blue-600/90 hover:bg-blue-600 text-white text-xs font-bold rounded-xl border border-blue-400/30 backdrop-blur-md transition-all shadow-lg flex items-center gap-2">
                            <span>✏️</span> Fasad / Maketni Tahrirlash
                        </Link>
                    </div>

                    <!-- Building 3D Canvas Stage with SVG Floor Overlays -->
                    <div class="relative max-h-[78vh] flex items-center justify-center">
                        <div class="relative inline-block max-w-full max-h-[78vh] rounded-2xl overflow-hidden shadow-2xl border border-white/10">
                            <!-- Building 3D Facade Image -->
                            <img 
                                ref="facadeImageRef"
                                :src="selectedBuilding.facade_image ? '/storage/' + selectedBuilding.facade_image : (selectedBuilding.photo_path ? '/storage/' + selectedBuilding.photo_path : (selectedBuilding.showroom_images?.[0] ? '/storage/' + selectedBuilding.showroom_images[0] : '/images/default-building.png'))"
                                @load="onFacadeImageLoaded"
                                class="block max-h-[78vh] w-auto h-auto object-contain transition-all duration-300"
                                :class="hoveredFloor ? 'brightness-95' : 'brightness-100'"
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
                                    class="cursor-pointer transition-all duration-300 group"
                                    @mouseenter="(e) => onFloorHover(floor, e)"
                                    @mouseleave="onFloorLeave"
                                    @click="openFloorPlan(floor.id)"
                                >
                                    <template v-if="getFloorFacadeShape(floor, idx, sortedFloors.length)">
                                        <!-- Rectangle Floor Band -->
                                        <rect 
                                            v-if="getFloorFacadeShape(floor, idx, sortedFloors.length).type === 'rect'"
                                            :x="getFloorFacadeShape(floor, idx, sortedFloors.length).x"
                                            :y="getFloorFacadeShape(floor, idx, sortedFloors.length).y"
                                            :width="getFloorFacadeShape(floor, idx, sortedFloors.length).width"
                                            :height="getFloorFacadeShape(floor, idx, sortedFloors.length).height"
                                            :fill="hoveredFloor?.id === floor.id ? 'rgba(59, 130, 246, 0.40)' : 'rgba(255, 255, 255, 0.04)'"
                                            :stroke="hoveredFloor?.id === floor.id ? '#60a5fa' : 'rgba(255, 255, 255, 0.20)'"
                                            :stroke-width="hoveredFloor?.id === floor.id ? 2.5 : 1"
                                            stroke-dasharray="4 2"
                                            rx="4"
                                            class="transition-all duration-300"
                                        />

                                        <!-- Polygon Floor Band -->
                                        <polygon 
                                            v-else-if="getFloorFacadeShape(floor, idx, sortedFloors.length).type === 'polygon'"
                                            :points="getFloorFacadeShape(floor, idx, sortedFloors.length).points"
                                            :fill="hoveredFloor?.id === floor.id ? 'rgba(59, 130, 246, 0.40)' : 'rgba(255, 255, 255, 0.04)'"
                                            :stroke="hoveredFloor?.id === floor.id ? '#60a5fa' : 'rgba(255, 255, 255, 0.20)'"
                                            :stroke-width="hoveredFloor?.id === floor.id ? 2.5 : 1"
                                            stroke-dasharray="4 2"
                                            class="transition-all duration-300"
                                        />
                                    </template>
                                </g>
                            </svg>
                        </div>

                        <!-- Modern Floating Card with Angled Connector (Exact matching Image 2) -->
                        <transition
                            enter-active-class="transition duration-200 ease-out"
                            enter-from-class="opacity-0 translate-x-4 scale-95"
                            enter-to-class="opacity-100 translate-x-0 scale-100"
                            leave-active-class="transition duration-150 ease-in"
                            leave-from-class="opacity-100 translate-x-0 scale-100"
                            leave-to-class="opacity-0 translate-x-4 scale-95"
                        >
                            <div 
                                v-if="hoveredFloor"
                                class="absolute right-4 md:right-12 top-1/4 z-40 w-80 bg-white/95 backdrop-blur-xl border border-white/40 shadow-2xl rounded-2xl p-5 text-gray-800 pointer-events-auto"
                            >
                                <!-- Angled Green Connector Line (matching image 2) -->
                                <div class="absolute -left-12 top-8 w-12 h-0.5 bg-gradient-to-r from-emerald-400 to-emerald-500 hidden md:block">
                                    <div class="absolute -left-1.5 -top-1 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-400/40"></div>
                                </div>

                                <!-- Card Header -->
                                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                    <div>
                                        <h3 class="text-base font-extrabold text-gray-900 tracking-tight flex items-center gap-2">
                                            {{ selectedBuilding.block_name || selectedBuilding.corpus_name || 'Blok' }}
                                        </h3>
                                        <span class="text-xs font-bold text-blue-600">
                                            {{ hoveredFloor.floor_number < 0 ? `${hoveredFloor.floor_number}-yerto'la` : `${hoveredFloor.floor_number}-qavat` }}
                                        </span>
                                    </div>
                                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-lg text-xs font-extrabold">
                                        {{ getFloorStats(hoveredFloor).available }} ta bo'sh
                                    </span>
                                </div>

                                <!-- Card Body Stats -->
                                <div class="py-3 space-y-2 text-xs font-semibold">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-500">Xonadonlar soni:</span>
                                        <span class="font-extrabold text-gray-800">{{ getFloorStats(hoveredFloor).total }} ta</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-500">Sotuvda mavjud:</span>
                                        <span class="font-extrabold text-emerald-600">{{ getFloorStats(hoveredFloor).available }} ta</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-500">Bron qilingan:</span>
                                        <span class="font-extrabold text-amber-600">{{ getFloorStats(hoveredFloor).reserved }} ta</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-500">Sotilgan:</span>
                                        <span class="font-extrabold text-red-600">{{ getFloorStats(hoveredFloor).sold }} ta</span>
                                    </div>
                                    <div class="flex justify-between items-center pt-2 border-t border-gray-100" v-if="getFloorStats(hoveredFloor).minPrice > 0">
                                        <span class="text-gray-500">Narx oralig'i:</span>
                                        <span class="font-extrabold text-blue-600">{{ formatSoM(getFloorStats(hoveredFloor).minPrice) }}</span>
                                    </div>
                                </div>

                                <!-- Card Action Button (Image 2 requirement) -->
                                <div class="pt-3 space-y-2">
                                    <button 
                                        @click="viewMode = 'shaxmatka'"
                                        class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold text-xs rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-1.5"
                                    >
                                        <span>Blok Shaxmatkasiga o'tish</span>
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </button>

                                    <button 
                                        @click="openFloorPlan(hoveredFloor.id)"
                                        class="w-full py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 font-bold text-xs rounded-xl border border-gray-200 transition-all flex items-center justify-center gap-1.5"
                                    >
                                        <span>📐 Qavat Chizmasini Ko'rish</span>
                                    </button>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <!-- Bottom Hint Bar -->
                    <div class="absolute bottom-4 z-20 px-6 py-2 bg-black/40 backdrop-blur-md rounded-full border border-white/10 text-white/80 text-xs font-semibold flex items-center gap-2 shadow-lg">
                        <span>💡</span> Qavat ma'lumotlarini ko'rish uchun bino fasadidagi qavatlar ustiga sichqonchani olib boring yoki bosing.
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- SCREEN 2: INTERAKTIV BLOK SHAXMATKASI (IMAGE 3)                           -->
                <!-- ========================================================================= -->
                <div v-else-if="viewMode === 'shaxmatka'" class="flex-1 flex flex-col relative overflow-hidden bg-slate-900 select-none">
                    
                    <!-- Background Facade with soft dimming -->
                    <div class="absolute inset-0 z-0">
                        <img 
                            :src="selectedBuilding.facade_image ? '/storage/' + selectedBuilding.facade_image : (selectedBuilding.photo_path ? '/storage/' + selectedBuilding.photo_path : (selectedBuilding.showroom_images?.[0] ? '/storage/' + selectedBuilding.showroom_images[0] : '/images/default-building.png'))"
                            class="w-full h-full object-cover opacity-20 filter blur-xs"
                        />
                        <div class="absolute inset-0 bg-gradient-to-b from-slate-900/90 via-slate-900/80 to-slate-900/95"></div>
                    </div>

                    <!-- Top Control Navigation Bar (Image 3) -->
                    <div class="relative z-10 flex flex-wrap items-center justify-between gap-4 px-6 py-4 bg-white/10 backdrop-blur-md border-b border-white/10">
                        <!-- Left Navigation -->
                        <div class="flex items-center gap-3">
                            <button 
                                @click="viewMode = 'facade'"
                                class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl border border-white/20 transition-all flex items-center gap-1.5 shadow-sm"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                <span>ortga qaytish</span>
                            </button>

                            <!-- Filter Button -->
                            <div class="relative">
                                <button 
                                    @click="showFilterDropdown = !showFilterDropdown"
                                    class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-extrabold rounded-xl shadow-md transition-all flex items-center gap-1.5"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                    <span>Filter</span>
                                </button>

                                <!-- Filter Dropdown -->
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

                            <!-- Clear Filter -->
                            <button 
                                v-if="searchQuery || filterStatus || filterRooms"
                                @click="resetFilters"
                                class="text-xs font-bold text-white/70 hover:text-white flex items-center gap-1"
                            >
                                <span>✕</span> Filterni tozalash
                            </button>
                        </div>

                        <!-- Status Indicator Legends (Image 3) -->
                        <div class="flex items-center gap-6 text-xs font-bold text-white">
                            <div class="flex items-center gap-2">
                                <span class="w-3.5 h-3.5 rounded-full bg-emerald-500 block shadow-sm shadow-emerald-500/50"></span>
                                <span>Sotuvda</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-3.5 h-3.5 rounded-full bg-red-500 block shadow-sm shadow-red-500/50"></span>
                                <span>Sotilgan</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-3.5 h-3.5 rounded-full bg-amber-500 block shadow-sm shadow-amber-500/50"></span>
                                <span>Bron qilingan</span>
                            </div>
                        </div>

                        <!-- Next Block Switcher -->
                        <div class="flex items-center gap-3">
                            <button 
                                @click="goToNextBlock"
                                class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl border border-white/20 transition-all flex items-center gap-1.5 shadow-sm"
                            >
                                <span>Keyingi blok</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Main Shaxmatka Content (Image 3 Grid Layout) -->
                    <div class="relative z-10 flex-1 overflow-auto p-8">
                        <!-- Large Orange Block Header Badge (Image 3) -->
                        <div class="mb-8">
                            <div class="inline-flex items-center px-8 py-3.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-3xl font-black text-2xl tracking-wide shadow-2xl border border-amber-400/40">
                                {{ selectedBuilding.block_name || selectedBuilding.corpus_name || 'A-1 blok' }}
                            </div>
                        </div>

                        <!-- 2-Columns Floors & Units Grid (Image 3) -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-16 gap-y-4 max-w-7xl">
                            
                            <!-- Left Column (Floors 1 to 8) -->
                            <div class="space-y-4">
                                <div 
                                    v-for="floor in leftColumnFloors" 
                                    :key="'col1-fl-' + floor.id"
                                    class="flex items-center gap-4 bg-white/5 hover:bg-white/10 backdrop-blur-sm p-2 rounded-2xl border border-white/10 transition-all duration-200"
                                >
                                    <!-- Orange Floor Pill Button -->
                                    <button 
                                        @click="openFloorPlan(floor.id)"
                                        title="Ushbu qavatning 2D chizmasini ko'rish"
                                        class="px-6 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-extrabold text-sm rounded-xl shadow-lg shrink-0 min-w-[130px] text-center transition-transform hover:scale-105 active:scale-95"
                                    >
                                        {{ floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat` }}
                                    </button>

                                    <!-- Unit Badges Row -->
                                    <div class="flex flex-wrap items-center gap-2 flex-1">
                                        <button 
                                            v-for="unit in filterUnitsList(floor.units)" 
                                            :key="'unit-btn-' + unit.id"
                                            @click="openUnitModal(unit)"
                                            :class="getUnitColorClass(unit)"
                                            class="px-3.5 py-2.5 rounded-xl font-black text-xs tracking-wider shadow-md transition-all duration-150 hover:scale-110 active:scale-95 flex items-center justify-center shrink-0 min-w-[76px]"
                                        >
                                            {{ unit.unit_number }}
                                        </button>
                                        <span v-if="!floor.units || floor.units.length === 0" class="text-white/40 text-xs font-semibold italic">
                                            Xonadonlar mavjud emas
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column (Floors 9 to 16) -->
                            <div class="space-y-4" v-if="rightColumnFloors.length > 0">
                                <div 
                                    v-for="floor in rightColumnFloors" 
                                    :key="'col2-fl-' + floor.id"
                                    class="flex items-center gap-4 bg-white/5 hover:bg-white/10 backdrop-blur-sm p-2 rounded-2xl border border-white/10 transition-all duration-200"
                                >
                                    <!-- Orange Floor Pill Button -->
                                    <button 
                                        @click="openFloorPlan(floor.id)"
                                        title="Ushbu qavatning 2D chizmasini ko'rish"
                                        class="px-6 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-extrabold text-sm rounded-xl shadow-lg shrink-0 min-w-[130px] text-center transition-transform hover:scale-105 active:scale-95"
                                    >
                                        {{ floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat` }}
                                    </button>

                                    <!-- Unit Badges Row -->
                                    <div class="flex flex-wrap items-center gap-2 flex-1">
                                        <button 
                                            v-for="unit in filterUnitsList(floor.units)" 
                                            :key="'unit-btn-r-' + unit.id"
                                            @click="openUnitModal(unit)"
                                            :class="getUnitColorClass(unit)"
                                            class="px-3.5 py-2.5 rounded-xl font-black text-xs tracking-wider shadow-md transition-all duration-150 hover:scale-110 active:scale-95 flex items-center justify-center shrink-0 min-w-[76px]"
                                        >
                                            {{ unit.unit_number }}
                                        </button>
                                        <span v-if="!floor.units || floor.units.length === 0" class="text-white/40 text-xs font-semibold italic">
                                            Xonadonlar mavjud emas
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- SCREEN 3: 2D QAVAT CHIZMASI (FLOOR PLAN BLUEPRINT)                         -->
                <!-- ========================================================================= -->
                <div v-else-if="viewMode === 'floor_plan'" class="flex-1 flex flex-col overflow-hidden bg-slate-950 p-6 select-none">
                    
                    <!-- Top Controls Bar for Floor Plan -->
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-4 bg-white/10 backdrop-blur-md p-3.5 rounded-2xl border border-white/10 text-white">
                        <div class="flex items-center gap-3">
                            <button 
                                @click="viewMode = 'shaxmatka'"
                                class="px-3.5 py-1.5 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl border border-white/20 transition-all flex items-center gap-1"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                <span>Shaxmatkaga qaytish</span>
                            </button>

                            <!-- Floor Selector Pills -->
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

                        <!-- Legend & Edit Link -->
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-4 text-xs font-bold">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded-full bg-emerald-500 block"></span>
                                    <span>Sotuvda</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded-full bg-amber-500 block"></span>
                                    <span>Band</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded-full bg-red-500 block"></span>
                                    <span>Sotilgan</span>
                                </div>
                            </div>

                            <Link :href="route('admin.facility.floor_plan_editor', { building_id: selectedBuilding.id, floor_id: selectedFloorForPlanId })" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow transition-all flex items-center gap-1">
                                <span>✏️</span> Chizmani Tahrirlash
                            </Link>
                        </div>
                    </div>

                    <!-- 2D SVG Interactive Blueprint Viewport -->
                    <div class="flex-1 flex gap-6 overflow-hidden">
                        <div class="flex-1 bg-slate-900 rounded-2xl border border-slate-800 overflow-auto relative flex items-center justify-center p-4">
                            <div class="relative inline-block max-w-full max-h-full" v-if="selectedFloorForPlan?.blueprint_path || selectedBuilding.blueprint_path">
                                <img 
                                    :src="'/storage/' + (selectedFloorForPlan?.blueprint_path || selectedBuilding.blueprint_path)" 
                                    @load="onBlueprintImageLoaded"
                                    class="block max-w-full max-h-[72vh] w-auto h-auto object-contain pointer-events-none select-none opacity-95 rounded-xl shadow-2xl"
                                />

                                <!-- SVG Unit Overlays -->
                                <svg 
                                    class="absolute inset-0 w-full h-full pointer-events-auto" 
                                    :viewBox="`0 0 ${showroomBlueprintSize.width} ${showroomBlueprintSize.height}`"
                                    preserveAspectRatio="none"
                                >
                                    <g v-for="unit in (selectedFloorForPlan?.units || [])" :key="'plan-unit-' + unit.id">
                                        <template v-if="parseUnitShape(unit)">
                                            <!-- Rectangle Unit -->
                                            <g v-if="parseUnitShape(unit).type === 'rect'"
                                               class="cursor-pointer transition-transform duration-200"
                                               @click="openUnitModal(unit)"
                                            >
                                                <rect 
                                                    :x="parseUnitShape(unit).x" 
                                                    :y="parseUnitShape(unit).y" 
                                                    :width="parseUnitShape(unit).width" 
                                                    :height="parseUnitShape(unit).height"
                                                    :fill="getSvgFillColor(unit)"
                                                    :stroke="selectedUnit?.id === unit.id ? '#3b82f6' : getSvgStrokeColor(unit)"
                                                    :stroke-width="selectedUnit?.id === unit.id ? 4 : 2.5"
                                                    rx="4"
                                                    class="hover:opacity-90 transition-opacity"
                                                />
                                                <text 
                                                    :x="parseUnitShape(unit).centerX" 
                                                    :y="parseUnitShape(unit).centerY" 
                                                    fill="#ffffff" 
                                                    font-size="13" 
                                                    font-weight="bold" 
                                                    text-anchor="middle" 
                                                    dominant-baseline="central"
                                                    class="pointer-events-none select-none drop-shadow"
                                                >
                                                    {{ unit.unit_number }}
                                                </text>
                                            </g>

                                            <!-- Polygon Unit -->
                                            <g v-else-if="parseUnitShape(unit).type === 'polygon'"
                                               class="cursor-pointer transition-transform duration-200"
                                               @click="openUnitModal(unit)"
                                            >
                                                <polygon 
                                                    :points="parseUnitShape(unit).points"
                                                    :fill="getSvgFillColor(unit)"
                                                    :stroke="selectedUnit?.id === unit.id ? '#3b82f6' : getSvgStrokeColor(unit)"
                                                    :stroke-width="selectedUnit?.id === unit.id ? 4 : 2.5"
                                                    class="hover:opacity-90 transition-opacity"
                                                />
                                                <text 
                                                    :x="parseUnitShape(unit).centerX" 
                                                    :y="parseUnitShape(unit).centerY" 
                                                    fill="#ffffff" 
                                                    font-size="13" 
                                                    font-weight="bold" 
                                                    text-anchor="middle" 
                                                    dominant-baseline="central"
                                                    class="pointer-events-none select-none drop-shadow"
                                                >
                                                    {{ unit.unit_number }}
                                                </text>
                                            </g>
                                        </template>
                                    </g>
                                </svg>
                            </div>
                            <div v-else class="text-slate-500 font-bold text-sm">
                                Ushbu qavat uchun chizma yuklanmagan
                            </div>
                        </div>

                        <!-- Right Unit Details Sidebar on Floor Plan -->
                        <div class="w-80 bg-white rounded-2xl border border-gray-200 overflow-hidden flex flex-col p-5 text-gray-800" v-if="selectedUnit">
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div>
                                    <span class="text-xs font-bold text-gray-400">Xonadon</span>
                                    <h3 class="text-lg font-extrabold text-gray-900">№ {{ selectedUnit.unit_number }}</h3>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-extrabold uppercase text-white" :class="getUnitBadgeBg(selectedUnit)">
                                    {{ selectedUnit.status }}
                                </span>
                            </div>

                            <div class="py-4 space-y-3 flex-1 text-xs font-semibold">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Qavat:</span>
                                    <span class="font-extrabold text-gray-900">{{ selectedFloorForPlan?.floor_number }}-qavat</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Xonalar soni:</span>
                                    <span class="font-extrabold text-gray-900">{{ selectedUnit.rooms_count || 1 }} xona</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Maydoni:</span>
                                    <span class="font-extrabold text-gray-900">{{ selectedUnit.area_sqm }} m²</span>
                                </div>
                                <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                                    <span class="text-gray-500">Umumiy narxi:</span>
                                    <span class="font-extrabold text-blue-600 text-sm">{{ formatSoM(selectedUnit.price) }}</span>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-gray-100">
                                <Link 
                                    v-if="selectedUnit.status === 'available'" 
                                    :href="route('admin.crm', { unit_id: selectedUnit.id })" 
                                    class="block w-full text-center py-3 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-xl shadow-lg transition-all"
                                >
                                    Sotish (Shartnoma tuzish)
                                </Link>
                                <div v-else class="text-center py-3 bg-gray-100 text-gray-400 font-bold rounded-xl text-xs">
                                    Bu xonadon sotuvda emas
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div v-else class="flex-1 flex items-center justify-center text-gray-400 font-bold bg-white rounded-3xl border border-gray-100">
                Binolar topilmadi
            </div>
        </div>

        <!-- Unit Detailed Modal (Opens from Shaxmatka or 2D floor plan) -->
        <Teleport to="body">
            <div v-if="showUnitDetailModal && selectedUnit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                    <!-- Modal Header -->
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

                    <!-- Modal Body Details -->
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

                        <!-- Price Box -->
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

                    <!-- Modal Actions -->
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
    </component>
</template>
