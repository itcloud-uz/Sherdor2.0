<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';

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

// View mode: 'facade' vs 'floor_plan' vs 'shaxmatka'
const viewMode = ref('facade');
const activeFloor = ref(null);
const hoveredUnit = ref(null);

const openFloorPlan = (floor) => {
    activeFloor.value = floor;
    viewMode.value = 'floor_plan';
};

const activeFloorBlueprintUrl = computed(() => {
    if (!activeFloor.value) return '/images/clean-building-facade.jpg';
    return activeFloor.value.blueprint_path 
        ? `/storage/${activeFloor.value.blueprint_path}` 
        : '/images/clean-building-facade.jpg';
});

const floorBlueprintNaturalSize = ref({ width: 1200, height: 800 });

const loadFloorBlueprintDimensions = (url) => {
    if (!url) return;
    const img = new Image();
    img.onload = () => {
        if (img.naturalWidth && img.naturalHeight) {
            floorBlueprintNaturalSize.value = {
                width: img.naturalWidth,
                height: img.naturalHeight
            };
        }
    };
    img.src = url;
};

watch(activeFloorBlueprintUrl, (newUrl) => {
    loadFloorBlueprintDimensions(newUrl);
}, { immediate: true });

const getUnitSvgShape = (unit) => {
    if (!unit || !unit.svg_polygon) return null;
    try {
        const parsed = typeof unit.svg_polygon === 'object' ? unit.svg_polygon : JSON.parse(unit.svg_polygon);
        const nw = floorBlueprintNaturalSize.value.width;
        const nh = floorBlueprintNaturalSize.value.height;

        if (parsed.norm) {
            if (parsed.type === 'rect') {
                return {
                    type: 'rect',
                    x: Math.round(parsed.norm.x * nw),
                    y: Math.round(parsed.norm.y * nh),
                    width: Math.round(parsed.norm.w * nw),
                    height: Math.round(parsed.norm.h * nh)
                };
            }
            if (parsed.type === 'polygon' && parsed.norm.points) {
                const ptsStr = parsed.norm.points.map(p => `${Math.round(p.x * nw)},${Math.round(p.y * nh)}`).join(' ');
                return { type: 'polygon', points: ptsStr };
            }
        }

        if (parsed.type === 'rect') {
            const natX = parsed.natural?.x ?? parsed.left ?? parsed.x ?? 0;
            const natY = parsed.natural?.y ?? parsed.top ?? parsed.y ?? 0;
            const natW = parsed.natural?.w ?? parsed.width ?? parsed.w ?? nw;
            const natH = parsed.natural?.h ?? parsed.height ?? parsed.h ?? 40;
            return {
                type: 'rect',
                x: Math.round(natX),
                y: Math.round(natY),
                width: Math.round(natW),
                height: Math.round(natH)
            };
        }

        if (parsed.type === 'polygon') {
            const pts = parsed.natural?.points || parsed.points;
            if (pts && pts.length >= 3) {
                const ptsStr = pts.map(p => `${p.x || p[0]},${p.y || p[1]}`).join(' ');
                return { type: 'polygon', points: ptsStr };
            }
        }
    } catch(e) {}
    return null;
};

const getUnitFillColor = (unit, isHovered) => {
    if (isHovered) return 'rgba(59, 130, 246, 0.5)';
    if (unit.status === 'sold') return 'rgba(239, 68, 68, 0.4)';
    if (unit.status === 'reserved' || unit.status === 'booked') return 'rgba(245, 158, 11, 0.4)';
    return 'rgba(16, 185, 129, 0.4)';
};

const getUnitStrokeColor = (unit) => {
    if (unit.status === 'sold') return '#ef4444';
    if (unit.status === 'reserved' || unit.status === 'booked') return '#f59e0b';
    return '#10b981';
};

const onUnitClick = (unit) => {
    if (unit.status === 'available') {
        openUnitModal(unit);
    }
};

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

// 2 Columns distribution for Shaxmatka (Image 2)
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
    if (unit.status === 'available') return 'bg-[#10b981] hover:bg-emerald-600 text-white';
    if (unit.status === 'sold') return 'bg-[#ef4444] hover:bg-red-600 text-white';
    if (unit.status === 'booked' || unit.status === 'reserved') return 'bg-[#f59e0b] hover:bg-amber-600 text-white';
    return 'bg-gray-400 hover:bg-gray-500 text-white';
};

const getUnitBadgeBg = (unit) => {
    if (unit.status === 'available') return 'bg-[#10b981]';
    if (unit.status === 'sold') return 'bg-[#ef4444]';
    if (unit.status === 'booked' || unit.status === 'reserved') return 'bg-[#f59e0b]';
    return 'bg-gray-400';
};

// Clean Facade Image URL
const buildingImageUrl = computed(() => {
    if (!selectedBuilding.value) return '/images/clean-building-facade.jpg';
    if (selectedBuilding.value.facade_image) return '/storage/' + selectedBuilding.value.facade_image;
    if (selectedBuilding.value.photo_path) return '/storage/' + selectedBuilding.value.photo_path;
    return '/images/clean-building-facade.jpg';
});

// Facade Image & Dimensions
const facadeImageRef = ref(null);
const facadeNaturalSize = ref({ width: 1200, height: 800 });

const loadBuildingImageDimensions = (url) => {
    if (!url) return;
    const img = new Image();
    img.onload = () => {
        if (img.naturalWidth && img.naturalHeight) {
            facadeNaturalSize.value = {
                width: img.naturalWidth,
                height: img.naturalHeight
            };
        }
    };
    img.src = url;
};

watch(buildingImageUrl, (newUrl) => {
    loadBuildingImageDimensions(newUrl);
}, { immediate: true });

onMounted(() => {
    loadBuildingImageDimensions(buildingImageUrl.value);
});

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
            const nw = facadeNaturalSize.value.width || 1200;
            const nh = facadeNaturalSize.value.height || 800;

            if (parsed.norm) {
                if (parsed.type === 'rect') {
                    return {
                        type: 'rect',
                        x: Math.round(parsed.norm.x * nw),
                        y: Math.round(parsed.norm.y * nh),
                        width: Math.round(parsed.norm.w * nw),
                        height: Math.round(parsed.norm.h * nh)
                    };
                }
                if (parsed.type === 'polygon' && parsed.norm.points) {
                    const ptsStr = parsed.norm.points.map(p => `${Math.round(p.x * nw)},${Math.round(p.y * nh)}`).join(' ');
                    return { type: 'polygon', points: ptsStr };
                }
            }

            if (parsed.type === 'rect') {
                const natX = parsed.natural?.x ?? parsed.left ?? parsed.x ?? 0;
                const natY = parsed.natural?.y ?? parsed.top ?? parsed.y ?? 0;
                const natW = parsed.natural?.w ?? parsed.width ?? parsed.w ?? nw;
                const natH = parsed.natural?.h ?? parsed.height ?? parsed.h ?? 40;

                return {
                    type: 'rect',
                    x: Math.round(natX),
                    y: Math.round(natY),
                    width: Math.round(natW),
                    height: Math.round(natH)
                };
            }

            if (parsed.type === 'polygon') {
                const pts = parsed.natural?.points || parsed.points;
                if (pts && pts.length >= 3) {
                    const ptsStr = pts.map(p => `${p.x || p[0]},${p.y || p[1]}`).join(' ');
                    return {
                        type: 'polygon',
                        points: ptsStr
                    };
                }
            }
        } catch(e) {}
    }

    // If floor has no polygon drawn in editor, do not render a phantom shape
    return null;
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
    if (!b) return "Toshkent viloyati zangi ota tumani";
    const parts = [b.city, b.district, b.mahalla, b.street, b.address].filter(Boolean);
    return parts.length > 0 ? parts.join(' ') : "Toshkent viloyati zangi ota tumani";
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
    <Head title="Shou room - 3D Maket - IQS ERP" />
    
    <!-- 100% FULL SCREEN STANDALONE APP (Clean White & Orange Studio) -->
    <div class="fixed inset-0 z-50 flex flex-col w-screen h-screen overflow-hidden select-none bg-[#f8fafc] font-sans">

        <!-- ========================================================================= -->
        <!-- 1. FULL SCREEN 3D BINO FASADI (FASAD KO'RINISHI)                         -->
        <!-- ========================================================================= -->
        <div v-if="viewMode === 'facade'" class="flex-1 flex flex-col w-full h-full overflow-hidden bg-[#f8fafc]">
            
            <!-- Clean Unified Top Bar (White & Orange Minimalism) -->
            <header class="h-[54px] bg-white border-b border-slate-200 px-6 flex items-center justify-between shadow-xs shrink-0 z-30">
                <!-- Left: Ortga Qaytish (Returns to CRM Dashboard) -->
                <Link 
                    :href="route('admin.dashboard')" 
                    class="h-8 px-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition-all flex items-center gap-1.5 cursor-pointer"
                >
                    <span class="text-sm">←</span>
                    <span>Boshqaruv paneliga qaytish</span>
                </Link>

                <!-- Center: Building & Block Switcher -->
                <div class="flex items-center gap-3">
                    <span class="text-xs font-black text-slate-900 tracking-tight flex items-center gap-1.5">
                        <span class="text-orange-500">🏢</span> {{ selectedBuilding.name || 'Baraka Residence' }} ({{ selectedBuilding.block_name || selectedBuilding.corpus_name || 'A-1 Blok' }})
                    </span>
                    <button 
                        @click="viewMode = 'shaxmatka'"
                        class="h-8 px-3.5 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-black text-xs rounded-xl shadow-md shadow-orange-500/20 transition-all flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>📊</span>
                        <span>Blok Shaxmatkasiga o'tish</span>
                    </button>
                </div>

                <!-- Right: Next block -->
                <div class="flex items-center gap-2">
                    <button 
                        @click="goToNextBlock"
                        class="h-8 px-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition-all flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>Keyingi blok</span>
                        <span class="text-sm">→</span>
                    </button>
                </div>
            </header>

            <!-- Full-Screen 3D Building Viewport with generous padding (No image clipping, pure light background) -->
            <div class="flex-1 w-full h-[calc(100vh-54px)] p-3 md:p-5 flex items-center justify-center overflow-hidden relative bg-[#f8fafc]">
                <div class="relative w-full h-full flex items-center justify-center">
                    <svg 
                        class="w-full h-full max-h-full block select-none pointer-events-auto filter drop-shadow-xl" 
                        :viewBox="`0 0 ${facadeNaturalSize.width} ${facadeNaturalSize.height}`"
                        preserveAspectRatio="xMidYMid meet"
                    >
                        <!-- Background Facade Image inside SVG for 100% Guaranteed Lock -->
                        <image 
                            :href="buildingImageUrl" 
                            x="0" 
                            y="0" 
                            :width="facadeNaturalSize.width" 
                            :height="facadeNaturalSize.height" 
                            preserveAspectRatio="xMidYMid meet"
                        />

                        <!-- Interactive Floor Overlays -->
                        <g 
                            v-for="(floor, idx) in sortedFloors" 
                            :key="'facade-fl-' + floor.id"
                            class="cursor-pointer transition-all duration-200"
                            @mouseenter="onFloorHover(floor)"
                            @mouseleave="onFloorLeave"
                            @click="openFloorPlan(floor)"
                        >
                            <template v-if="getFloorFacadeShape(floor, idx, sortedFloors.length)">
                                <rect 
                                    v-if="getFloorFacadeShape(floor, idx, sortedFloors.length).type === 'rect'"
                                    :x="getFloorFacadeShape(floor, idx, sortedFloors.length).x"
                                    :y="getFloorFacadeShape(floor, idx, sortedFloors.length).y"
                                    :width="getFloorFacadeShape(floor, idx, sortedFloors.length).width"
                                    :height="getFloorFacadeShape(floor, idx, sortedFloors.length).height"
                                    :fill="hoveredFloor?.id === floor.id ? 'rgba(249, 115, 22, 0.40)' : 'rgba(255, 255, 255, 0.01)'"
                                    :stroke="hoveredFloor?.id === floor.id ? '#f97316' : 'transparent'"
                                    :stroke-width="hoveredFloor?.id === floor.id ? 3 : 0"
                                    class="transition-all duration-200"
                                />

                                <polygon 
                                    v-else-if="getFloorFacadeShape(floor, idx, sortedFloors.length).type === 'polygon'"
                                    :points="getFloorFacadeShape(floor, idx, sortedFloors.length).points"
                                    :fill="hoveredFloor?.id === floor.id ? 'rgba(249, 115, 22, 0.40)' : 'rgba(255, 255, 255, 0.01)'"
                                    :stroke="hoveredFloor?.id === floor.id ? '#f97316' : 'transparent'"
                                    :stroke-width="hoveredFloor?.id === floor.id ? 3 : 0"
                                    class="transition-all duration-200"
                                />
                            </template>
                        </g>
                    </svg>

                    <!-- Clean Modern Floor Card on Hover (White & Orange Minimalism) -->
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
                            @click="openFloorPlan(hoveredFloor)"
                            class="absolute right-6 md:right-12 top-1/4 z-50 w-84 bg-white shadow-2xl rounded-3xl p-5 text-slate-800 border border-slate-200/90 cursor-pointer pointer-events-auto animate-in fade-in zoom-in duration-150"
                        >
                            <!-- Header: Blok Name & Qavat Badge -->
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <div>
                                    <h3 class="text-base font-black text-slate-900 tracking-tight">
                                        {{ selectedBuilding.block_name || selectedBuilding.corpus_name || 'A-1 Blok' }}
                                    </h3>
                                    <p class="text-[11px] text-slate-500 font-medium">{{ selectedBuilding?.name }}</p>
                                </div>
                                <span class="px-3 py-1 bg-orange-50 text-orange-600 border border-orange-200 rounded-xl text-xs font-black">
                                    {{ hoveredFloor.floor_number < 0 ? `${hoveredFloor.floor_number}-Yerto'la` : `${hoveredFloor.floor_number}-Qavat` }}
                                </span>
                            </div>

                            <!-- Rows matching floor stats -->
                            <div class="py-3 space-y-2 text-xs font-bold">
                                <div class="flex items-center justify-between bg-slate-50 p-2 rounded-xl border border-slate-100">
                                    <span class="text-slate-600">Sotuvda mavjud:</span>
                                    <span class="text-emerald-700 font-black">🟢 {{ getFloorStats(hoveredFloor).available || 5 }} ta xonadon</span>
                                </div>
                                <div class="flex items-center justify-between bg-slate-50 p-2 rounded-xl border border-slate-100">
                                    <span class="text-slate-600">Sotilgan:</span>
                                    <span class="text-red-700 font-black">🔴 {{ getFloorStats(hoveredFloor).sold || 3 }} ta xonadon</span>
                                </div>
                                <div class="flex items-start justify-between gap-4 p-2">
                                    <span class="text-slate-500 shrink-0">Joylashuv:</span>
                                    <span class="text-slate-800 font-extrabold text-right text-[11px]">{{ getBuildingLocationString(selectedBuilding) }}</span>
                                </div>
                                <div class="flex items-center justify-between p-2">
                                    <span class="text-slate-500">Statusi:</span>
                                    <span class="text-amber-600 font-extrabold">
                                        {{ selectedBuilding.status || 'Qurilish jarayonida' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Click action button -->
                            <div class="mt-2 py-2.5 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-xl text-center text-xs font-black shadow-md shadow-orange-500/20 flex items-center justify-center gap-2">
                                <span>📐</span>
                                <span>Qavat chizmasini ochish →</span>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 2. FULL SCREEN 2D FLOOR PLAN & UNITS (QAVAT CHIZMASI)                    -->
        <!-- ========================================================================= -->
        <div v-else-if="viewMode === 'floor_plan'" class="flex-1 flex flex-col w-full h-full overflow-hidden bg-[#f8fafc]">
            <!-- Clean Unified Top Bar (Floor Plan) -->
            <header class="h-[54px] bg-white border-b border-slate-200 px-6 flex items-center justify-between shadow-xs shrink-0 z-30">
                <button 
                    @click="viewMode = 'facade'" 
                    class="h-8 px-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition-all flex items-center gap-1.5 cursor-pointer"
                >
                    <span class="text-sm">←</span>
                    <span>Bino fasadiga qaytish</span>
                </button>

                <div class="flex items-center gap-2">
                    <span class="font-black text-xs text-slate-900 flex items-center gap-1.5">
                        <span class="text-orange-500">🏢</span> {{ selectedBuilding?.name }} ({{ selectedBuilding?.block_name || 'A-1 Blok' }}) 
                        <span class="text-slate-400">›</span> 
                        <span class="text-amber-500">📐</span> {{ activeFloor?.floor_number < 0 ? `${activeFloor?.floor_number}-yerto'la` : `${activeFloor?.floor_number}-qavat` }} Chizmasi
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <button 
                        @click="viewMode = 'shaxmatka'"
                        class="h-8 px-3.5 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white text-xs font-black rounded-xl shadow-md shadow-orange-500/20 transition-all flex items-center gap-1 cursor-pointer"
                    >
                        <span>📊</span>
                        <span>Blok Shaxmatkasiga o'tish →</span>
                    </button>
                </div>
            </header>

            <!-- Blueprint & Interactive Unit Polygons SVG Viewport with generous padding (Pure light background) -->
            <div class="flex-1 w-full h-[calc(100vh-54px)] p-3 md:p-5 flex items-center justify-center overflow-hidden relative bg-[#f8fafc]">
                <div class="relative w-full h-full flex items-center justify-center">
                    <svg 
                        class="w-full h-full max-h-full block select-none pointer-events-auto filter drop-shadow-xl" 
                        :viewBox="`0 0 ${floorBlueprintNaturalSize.width} ${floorBlueprintNaturalSize.height}`"
                        preserveAspectRatio="xMidYMid meet"
                    >
                        <!-- Background Floor Blueprint Image -->
                        <image 
                            :href="activeFloorBlueprintUrl" 
                            x="0" 
                            y="0" 
                            :width="floorBlueprintNaturalSize.width" 
                            :height="floorBlueprintNaturalSize.height" 
                            preserveAspectRatio="xMidYMid meet"
                        />

                        <!-- Interactive Unit Polygons -->
                        <g 
                            v-for="unit in (activeFloor?.units || [])" 
                            :key="'unit-svg-' + unit.id"
                            class="cursor-pointer transition-all duration-200"
                            @mouseenter="hoveredUnit = unit"
                            @mouseleave="hoveredUnit = null"
                            @click="onUnitClick(unit)"
                        >
                            <template v-if="getUnitSvgShape(unit)">
                                <rect 
                                    v-if="getUnitSvgShape(unit).type === 'rect'"
                                    :x="getUnitSvgShape(unit).x"
                                    :y="getUnitSvgShape(unit).y"
                                    :width="getUnitSvgShape(unit).width"
                                    :height="getUnitSvgShape(unit).height"
                                    :fill="getUnitFillColor(unit, hoveredUnit?.id === unit.id)"
                                    :stroke="hoveredUnit?.id === unit.id ? '#FFFFFF' : getUnitStrokeColor(unit)"
                                    :stroke-width="hoveredUnit?.id === unit.id ? 3.5 : 2"
                                    class="transition-all duration-200"
                                />

                                <polygon 
                                    v-else-if="getUnitSvgShape(unit).type === 'polygon'"
                                    :points="getUnitSvgShape(unit).points"
                                    :fill="getUnitFillColor(unit, hoveredUnit?.id === unit.id)"
                                    :stroke="hoveredUnit?.id === unit.id ? '#FFFFFF' : getUnitStrokeColor(unit)"
                                    :stroke-width="hoveredUnit?.id === unit.id ? 3.5 : 2"
                                    class="transition-all duration-200"
                                />
                            </template>
                        </g>
                    </svg>

                    <!-- Unit Tooltip Card on Hover (White & Orange Minimalism) -->
                    <transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 translate-x-6 scale-95"
                        enter-to-class="opacity-100 translate-x-0 scale-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100 translate-x-0 scale-100"
                        leave-to-class="opacity-0 translate-x-6 scale-95"
                    >
                        <div 
                            v-if="hoveredUnit"
                            class="absolute right-6 md:right-12 top-1/4 z-50 w-84 bg-white shadow-2xl rounded-3xl p-5 text-slate-800 border border-slate-200/90 pointer-events-auto animate-in fade-in zoom-in duration-150"
                        >
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <div>
                                    <h3 class="text-base font-black text-slate-900">
                                        {{ hoveredUnit.unit_number }}-xonadon
                                    </h3>
                                    <p class="text-[11px] text-slate-500 font-medium">{{ selectedBuilding?.name }}</p>
                                </div>
                                <span 
                                    class="px-2.5 py-0.5 rounded-full text-xs font-black uppercase"
                                    :class="hoveredUnit.status === 'sold' ? 'bg-red-100 text-red-700' : (hoveredUnit.status === 'reserved' || hoveredUnit.status === 'booked' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800')"
                                >
                                    {{ hoveredUnit.status === 'sold' ? '🔴 Sotilgan' : (hoveredUnit.status === 'reserved' || hoveredUnit.status === 'booked' ? '🟡 Band' : '🟢 Sotuvda') }}
                                </span>
                            </div>

                            <div class="py-3 space-y-2 text-xs font-bold">
                                <div class="flex items-center justify-between bg-slate-50 p-2 rounded-xl border border-slate-100">
                                    <span class="text-slate-500">Qavat:</span>
                                    <span class="text-slate-800 font-black">{{ activeFloor?.floor_number }}-qavat</span>
                                </div>
                                <div class="flex items-center justify-between bg-slate-50 p-2 rounded-xl border border-slate-100">
                                    <span class="text-slate-500">Xonalar soni:</span>
                                    <span class="text-slate-800 font-black">{{ hoveredUnit.rooms_count || 1 }}-xona</span>
                                </div>
                                <div class="flex items-center justify-between bg-slate-50 p-2 rounded-xl border border-slate-100">
                                    <span class="text-slate-500">Umumiy maydoni:</span>
                                    <span class="text-slate-800 font-black">{{ hoveredUnit.area_sqm }} m²</span>
                                </div>
                                <div class="flex items-center justify-between bg-orange-50/80 p-2.5 rounded-xl border border-orange-200/80">
                                    <span class="text-orange-700">Umumiy narxi:</span>
                                    <span class="text-slate-900 font-black text-sm">{{ formatSoM(hoveredUnit.price) }}</span>
                                </div>
                            </div>

                            <button
                                v-if="hoveredUnit.status === 'available'"
                                @click="openUnitModal(hoveredUnit)"
                                class="w-full mt-2 py-2.5 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-black text-xs rounded-xl shadow-lg shadow-orange-500/25 transition-all flex items-center justify-center gap-2 cursor-pointer"
                            >
                                <span>📝</span>
                                <span>Shartnoma / Bron Qilish</span>
                            </button>
                        </div>
                    </transition>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 3. FULL SCREEN BLOK SHAXMATKASI (SHAXMATKA KO'RINISHI)                   -->
        <!-- ========================================================================= -->
        <div v-else-if="viewMode === 'shaxmatka'" class="flex-1 flex flex-col w-full h-full overflow-hidden bg-[#f8fafc]">
            
            <!-- Top Header Bar (White & Orange Minimalism) -->
            <header class="h-[54px] bg-white border-b border-slate-200 px-6 flex items-center justify-between shadow-xs shrink-0 z-30">
                <div class="flex items-center gap-4">
                    <button 
                        @click="viewMode = 'facade'"
                        class="h-8 px-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition-all flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>←</span>
                        <span>Fasadga qaytish</span>
                    </button>

                    <!-- Filter Button -->
                    <div class="relative">
                        <button 
                            @click="showFilterDropdown = !showFilterDropdown"
                            class="h-8 px-3.5 bg-orange-50 hover:bg-orange-100 text-orange-600 border border-orange-200 text-xs font-bold rounded-xl shadow-xs transition-all flex items-center gap-1.5 cursor-pointer"
                        >
                            <span>⚡</span>
                            <span>Filter</span>
                        </button>

                        <div v-if="showFilterDropdown" class="absolute left-0 mt-2 w-72 bg-white rounded-2xl shadow-2xl border border-slate-200 p-4 z-50 text-slate-800 space-y-3">
                            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                                <h4 class="font-extrabold text-xs uppercase tracking-wider text-slate-600">Qidiruv va Filter</h4>
                                <button @click="showFilterDropdown = false" class="text-slate-400 hover:text-slate-700 font-bold">✕</button>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-600 block mb-1">Xonadon raqami:</label>
                                <input v-model="searchQuery" placeholder="Masalan: A-1-1..." class="w-full text-xs font-bold rounded-xl border-slate-200 p-2 focus:ring-2 focus:ring-orange-500" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-600 block mb-1">Holati:</label>
                                <select v-model="filterStatus" class="w-full text-xs font-bold rounded-xl border-slate-200 p-2 focus:ring-2 focus:ring-orange-500">
                                    <option value="">Barchasi</option>
                                    <option value="available">Sotuvda</option>
                                    <option value="reserved">Bron qilingan</option>
                                    <option value="sold">Sotilgan</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-600 block mb-1">Xonalar soni:</label>
                                <select v-model="filterRooms" class="w-full text-xs font-bold rounded-xl border-slate-200 p-2 focus:ring-2 focus:ring-orange-500">
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
                        class="text-xs font-bold text-slate-500 hover:text-slate-800 flex items-center gap-1 cursor-pointer"
                    >
                        <span>✕</span> Filterni tozalash
                    </button>
                </div>

                <!-- Status Legends -->
                <div class="flex items-center gap-6 text-xs font-bold text-slate-700">
                    <div class="flex items-center gap-2">
                        <span class="w-3.5 h-3.5 rounded-full bg-emerald-500 block shadow-xs"></span>
                        <span>Sotuvda</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3.5 h-3.5 rounded-full bg-red-500 block shadow-xs"></span>
                        <span>Sotilgan</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3.5 h-3.5 rounded-full bg-amber-500 block shadow-xs"></span>
                        <span>Bron qilingan</span>
                    </div>
                </div>

                <!-- Next Block Button -->
                <div class="flex items-center gap-3">
                    <button 
                        @click="goToNextBlock"
                        class="h-8 px-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition-all flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>Keyingi blok</span>
                        <span class="text-sm">→</span>
                    </button>
                </div>
            </header>

            <!-- Shaxmatka 2-Columns Grid Layout -->
            <div class="flex-1 overflow-auto px-8 py-6 bg-[#f8fafc]">
                <!-- Clean Orange Block Header Pill -->
                <div class="mb-6">
                    <div class="inline-flex items-center px-6 py-2 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-2xl font-black text-xl tracking-tight shadow-md shadow-orange-500/20">
                        {{ selectedBuilding.block_name || selectedBuilding.corpus_name || 'A-1 blok' }}
                    </div>
                </div>

                <!-- 2-Columns Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10 gap-y-4 max-w-7xl">
                    <!-- Left Column (Floors 1 to 8) -->
                    <div class="space-y-3">
                        <div 
                            v-for="floor in leftColumnFloors" 
                            :key="'col1-fl-' + floor.id"
                            class="flex items-center gap-3 bg-white p-2 rounded-2xl border border-slate-200/80 shadow-xs"
                        >
                            <!-- Orange Floor Pill Button -->
                            <div class="px-4 py-2 bg-slate-900 text-white font-black text-xs rounded-xl shadow-xs shrink-0 min-w-[110px] text-center">
                                {{ floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat` }}
                            </div>

                            <!-- Unit Badges Row -->
                            <div class="flex flex-wrap items-center gap-2 flex-1">
                                <button 
                                    v-for="unit in filterUnitsList(floor.units)" 
                                    :key="'unit-btn-' + unit.id"
                                    @click="openUnitModal(unit)"
                                    :class="getUnitColorClass(unit)"
                                    class="px-3 py-2 rounded-xl font-black text-xs shadow-xs transition-all duration-150 hover:scale-105 active:scale-95 flex items-center justify-center shrink-0 min-w-[65px] cursor-pointer"
                                >
                                    {{ unit.unit_number }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column (Floors 9 to 16) -->
                    <div class="space-y-3" v-if="rightColumnFloors.length > 0">
                        <div 
                            v-for="floor in rightColumnFloors" 
                            :key="'col2-fl-' + floor.id"
                            class="flex items-center gap-3 bg-white p-2 rounded-2xl border border-slate-200/80 shadow-xs"
                        >
                            <!-- Orange Floor Pill Button -->
                            <div class="px-4 py-2 bg-slate-900 text-white font-black text-xs rounded-xl shadow-xs shrink-0 min-w-[110px] text-center">
                                {{ floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat` }}
                            </div>

                            <!-- Unit Badges Row -->
                            <div class="flex flex-wrap items-center gap-2 flex-1">
                                <button 
                                    v-for="unit in filterUnitsList(floor.units)" 
                                    :key="'unit-btn-r-' + unit.id"
                                    @click="openUnitModal(unit)"
                                    :class="getUnitColorClass(unit)"
                                    class="px-3 py-2 rounded-xl font-black text-xs shadow-xs transition-all duration-150 hover:scale-105 active:scale-95 flex items-center justify-center shrink-0 min-w-[65px] cursor-pointer"
                                >
                                    {{ unit.unit_number }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Unit Detailed Modal (Clean Minimalist White & Orange Design) -->
    <Teleport to="body">
        <div v-if="showUnitDetailModal && selectedUnit" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
            <div class="bg-white rounded-3xl shadow-2xl border border-slate-200/90 w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-150">
                <!-- Header -->
                <div class="px-6 py-5 bg-white border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="px-3 py-1.5 rounded-xl font-black text-sm border shadow-xs" :class="selectedUnit.status === 'available' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : (selectedUnit.status === 'sold' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-amber-50 text-amber-700 border-amber-200')">
                            {{ selectedUnit.unit_number }}
                        </div>
                        <div>
                            <h3 class="font-black text-base text-slate-900 tracking-tight">Xonadon № {{ selectedUnit.unit_number }}</h3>
                            <p class="text-xs text-slate-500 font-medium">{{ selectedBuilding?.name }} • {{ selectedBuilding?.block_name || 'A-1 Blok' }}</p>
                        </div>
                    </div>
                    <button 
                        @click="showUnitDetailModal = false" 
                        class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 flex items-center justify-center font-bold text-sm transition-all cursor-pointer"
                    >
                        ✕
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-4">
                    <!-- Status Banner -->
                    <div class="flex items-center justify-between p-3 rounded-2xl border" :class="selectedUnit.status === 'available' ? 'bg-emerald-50/70 border-emerald-200 text-emerald-800' : (selectedUnit.status === 'sold' ? 'bg-red-50/70 border-red-200 text-red-800' : 'bg-amber-50/70 border-amber-200 text-amber-800')">
                        <div class="flex items-center gap-2 font-bold text-xs">
                            <span>{{ selectedUnit.status === 'available' ? '🟢' : (selectedUnit.status === 'sold' ? '🔴' : '🟡') }}</span>
                            <span class="uppercase tracking-wider text-[11px] font-black">Xonadon Holati</span>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-white border" :class="selectedUnit.status === 'available' ? 'text-emerald-700 border-emerald-200' : (selectedUnit.status === 'sold' ? 'text-red-700 border-red-200' : 'text-amber-700 border-amber-200')">
                            {{ selectedUnit.status === 'available' ? 'Sotuvda mavjud' : (selectedUnit.status === 'sold' ? 'Sotilgan' : 'Band qilingan') }}
                        </span>
                    </div>

                    <!-- 4 Info Cards -->
                    <div class="grid grid-cols-2 gap-2.5">
                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200/80">
                            <span class="text-[10px] font-extrabold text-slate-400 block uppercase tracking-wider">Umumiy Maydon</span>
                            <span class="text-sm font-black text-slate-900 mt-0.5 inline-block">{{ selectedUnit.area_sqm }} m²</span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200/80">
                            <span class="text-[10px] font-extrabold text-slate-400 block uppercase tracking-wider">Xonalar Soni</span>
                            <span class="text-sm font-black text-slate-900 mt-0.5 inline-block">{{ selectedUnit.rooms_count || 1 }} xona</span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200/80">
                            <span class="text-[10px] font-extrabold text-slate-400 block uppercase tracking-wider">Podyezd (Kirish)</span>
                            <span class="text-sm font-black text-slate-900 mt-0.5 inline-block">{{ selectedUnit.podyezd || 1 }}-podyezd</span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200/80">
                            <span class="text-[10px] font-extrabold text-slate-400 block uppercase tracking-wider">Joylashgan Qavat</span>
                            <span class="text-sm font-black text-slate-900 mt-0.5 inline-block">{{ activeFloor?.floor_number < 0 ? `${activeFloor?.floor_number}-yerto'la` : `${activeFloor?.floor_number}-qavat` }}</span>
                        </div>
                    </div>

                    <!-- Price Card -->
                    <div class="bg-gradient-to-br from-orange-50/80 to-amber-50/80 border border-orange-200/80 rounded-2xl p-4 flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-extrabold text-orange-700 uppercase tracking-wider block">Umumiy Qiymati</span>
                            <span class="text-xl font-black text-slate-900 tracking-tight">{{ formatSoM(selectedUnit.price) }}</span>
                        </div>
                        <div v-if="selectedUnit.area_sqm > 0 && selectedUnit.price > 0" class="text-right">
                            <span class="text-[10px] font-bold text-slate-400 block uppercase">1 m² narxi</span>
                            <span class="text-xs font-black text-orange-600 font-mono">{{ formatSoM(Math.round(selectedUnit.price / selectedUnit.area_sqm)) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div class="p-6 pt-0 flex items-center gap-3">
                    <Link 
                        v-if="selectedUnit.status === 'available'" 
                        :href="route('admin.crm', { unit_id: selectedUnit.id })" 
                        class="flex-1 py-3 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-black text-xs rounded-2xl shadow-lg shadow-orange-500/25 transition-all text-center flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span>📝</span>
                        <span>Sotish (Shartnoma tuzish)</span>
                    </Link>
                    <button 
                        @click="showUnitDetailModal = false"
                        class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-2xl transition-all cursor-pointer"
                    >
                        Yopish
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
