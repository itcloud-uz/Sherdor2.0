<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';

const props = defineProps({
    buildings: {
        type: Array,
        default: () => []
    },
});

// Unique object names list
const uniqueObjectNames = computed(() => {
    if (!props.buildings || props.buildings.length === 0) return [];
    return [...new Set(props.buildings.map(b => b.name).filter(Boolean))];
});

// Selected object name state
const selectedObjectName = ref(props.buildings?.length > 0 ? props.buildings[0].name : '');

// Blocks belonging to the selected object
const availableBlocks = computed(() => {
    if (!props.buildings || props.buildings.length === 0) return [];
    if (!selectedObjectName.value) return props.buildings;
    return props.buildings.filter(b => b.name === selectedObjectName.value);
});

// Selected block (building) ID state
const selectedBuildingId = ref(null);

watch(availableBlocks, (blocks) => {
    if (blocks && blocks.length > 0) {
        if (!selectedBuildingId.value || !blocks.some(b => b.id === selectedBuildingId.value)) {
            selectedBuildingId.value = blocks[0].id;
        }
    } else {
        selectedBuildingId.value = null;
    }
}, { immediate: true });

const selectedBuilding = computed(() => {
    if (!props.buildings) return null;
    return props.buildings.find(b => b.id === selectedBuildingId.value) || props.buildings[0] || null;
});

// Switch block
const selectBlock = (bId) => {
    selectedBuildingId.value = bId;
};

// Next block switcher helper
const goToNextBlock = () => {
    if (!availableBlocks.value || availableBlocks.value.length === 0) return;
    const currentIndex = availableBlocks.value.findIndex(b => b.id === selectedBuildingId.value);
    const nextIndex = (currentIndex + 1) % availableBlocks.value.length;
    selectedBuildingId.value = availableBlocks.value[nextIndex].id;
};

// View mode: 'master_plan' vs 'facade' vs 'floor_plan' vs 'shaxmatka' vs 'gallery'
const viewMode = ref('facade');

// Floor Plan view sub-mode: 'split' (Blueprint + Units side) vs 'cards' (All 3D cards)
const floorPlanDisplayMode = ref('split');

// Active floor & hover states
const activeFloor = ref(null);
const hoveredFloor = ref(null);
const hoveredUnit = ref(null);
const hoveredBlock = ref(null);

// Unit detail modal state
const selectedUnit = ref(null);
const showUnitDetailModal = ref(false);
const unitModalImageMode = ref('3d'); // '3d' or '2d'
const unitModalTab = ref('visual'); // 'visual' or 'details'

// Fullscreen Lightbox
const lightboxImage = ref(null);
const openLightbox = (url) => {
    if (url) lightboxImage.value = url;
};
const closeLightbox = () => {
    lightboxImage.value = null;
};

// Open floor plan
const openFloorPlan = (floor) => {
    activeFloor.value = floor;
    viewMode.value = 'floor_plan';
};

// Open unit modal
const openUnitModal = (unit) => {
    selectedUnit.value = unit;
    unitModalImageMode.value = unit.model_3d_path ? '3d' : '2d';
    unitModalTab.value = 'visual';
    showUnitDetailModal.value = true;
};

// All floors sorted for selected building (e.g. -1, 1, 2, ... 8)
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

// Set default active floor when switching building
watch(sortedFloors, (floors) => {
    if (floors && floors.length > 0) {
        if (!activeFloor.value || !floors.some(f => f.id === activeFloor.value.id)) {
            // Prefer 1-qavat or first floor
            activeFloor.value = floors.find(f => f.floor_number === 1) || floors[0];
        }
    } else {
        activeFloor.value = null;
    }
}, { immediate: true });

// Blueprint URL for active floor
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

// Facade Image URL
const buildingImageUrl = computed(() => {
    if (!selectedBuilding.value) return '/images/clean-building-facade.jpg';
    if (selectedBuilding.value.facade_image) return '/storage/' + selectedBuilding.value.facade_image;
    if (selectedBuilding.value.photo_path) return '/storage/' + selectedBuilding.value.photo_path;
    return '/images/clean-building-facade.jpg';
});

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

// Master Plan Image & Dimensions
const masterPlanImageUrl = computed(() => {
    const found = availableBlocks.value.find(b => b.master_plan_image);
    if (found && found.master_plan_image) {
        return '/storage/' + found.master_plan_image;
    }
    if (selectedBuilding.value?.master_plan_image) {
        return '/storage/' + selectedBuilding.value.master_plan_image;
    }
    return null;
});

const hasMasterPlan = computed(() => !!masterPlanImageUrl.value);
const masterPlanNaturalSize = ref({ width: 1920, height: 1080 });

const loadMasterPlanDimensions = (url) => {
    if (!url) return;
    const img = new Image();
    img.onload = () => {
        if (img.naturalWidth && img.naturalHeight) {
            masterPlanNaturalSize.value = {
                width: img.naturalWidth,
                height: img.naturalHeight
            };
        }
    };
    img.src = url;
};

watch(masterPlanImageUrl, (newUrl) => {
    if (newUrl) loadMasterPlanDimensions(newUrl);
}, { immediate: true });

// Showroom Images Gallery
const showroomImages = computed(() => {
    if (!selectedBuilding.value?.showroom_images) return [];
    if (Array.isArray(selectedBuilding.value.showroom_images)) {
        return selectedBuilding.value.showroom_images;
    }
    try {
        return JSON.parse(selectedBuilding.value.showroom_images) || [];
    } catch(e) {
        return [];
    }
});

const activeShowroomImageIndex = ref(0);

// Facade polygon calculation (Auto-generating horizontal bands when null)
const getFloorFacadeShape = (floor, index, totalFloors) => {
    if (!floor) return null;
    const nw = facadeNaturalSize.value.width || 1200;
    const nh = facadeNaturalSize.value.height || 800;

    if (floor.facade_polygon) {
        try {
            const parsed = typeof floor.facade_polygon === 'object' ? floor.facade_polygon : JSON.parse(floor.facade_polygon);

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
    }

    // Smart auto-calculated facade slice across building height
    const count = totalFloors || 9;
    const topMargin = nh * 0.16;
    const usableHeight = nh * 0.68;
    const bandHeight = usableHeight / count;
    
    // Reverse so top floor is at top, bottom floor is at bottom
    const reversedIndex = count - 1 - index;
    const y = topMargin + (reversedIndex * bandHeight);
    const x = nw * 0.16;
    const width = nw * 0.68;

    return {
        type: 'rect',
        x: Math.round(x),
        y: Math.round(y),
        width: Math.round(width),
        height: Math.round(bandHeight)
    };
};

// Unit SVG polygon parsing
const getUnitSvgShape = (unit) => {
    if (!unit || !unit.svg_polygon) return null;
    try {
        const parsed = typeof unit.svg_polygon === 'object' ? unit.svg_polygon : JSON.parse(unit.svg_polygon);
        const nw = floorBlueprintNaturalSize.value.width || 1200;
        const nh = floorBlueprintNaturalSize.value.height || 800;

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

// Master plan polygon
const getBlockMasterPlanShape = (block) => {
    if (!block || !block.master_plan_polygon) return null;
    try {
        const parsed = typeof block.master_plan_polygon === 'object' 
            ? block.master_plan_polygon 
            : JSON.parse(block.master_plan_polygon);
        
        const nw = masterPlanNaturalSize.value.width || 1920;
        const nh = masterPlanNaturalSize.value.height || 1080;

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
            const natW = parsed.natural?.w ?? parsed.width ?? parsed.w ?? 200;
            const natH = parsed.natural?.h ?? parsed.height ?? parsed.h ?? 300;
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

// Colors & Formatting
const getUnitFillColor = (unit, isHovered) => {
    if (isHovered) return 'rgba(249, 115, 22, 0.65)';
    if (unit.status === 'sold') return 'rgba(239, 68, 68, 0.45)';
    if (unit.status === 'reserved' || unit.status === 'booked') return 'rgba(245, 158, 11, 0.45)';
    return 'rgba(16, 185, 129, 0.45)';
};

const getUnitStrokeColor = (unit) => {
    if (unit.status === 'sold') return '#ef4444';
    if (unit.status === 'reserved' || unit.status === 'booked') return '#f59e0b';
    return '#10b981';
};

const formatSoM = (val) => {
    if (!val && val !== 0) return '0 UZS';
    return new Intl.NumberFormat('uz-UZ').format(val) + ' UZS';
};

const getFloorStats = (floor) => {
    if (!floor) return { total: 0, available: 0, reserved: 0, sold: 0 };
    const units = floor.units || [];
    const total = units.length;
    const available = units.filter(u => u.status === 'available').length;
    const reserved = units.filter(u => u.status === 'booked' || u.status === 'reserved').length;
    const sold = units.filter(u => u.status === 'sold').length;
    return { total, available, reserved, sold };
};

const getBuildingLocationString = (b) => {
    if (!b) return "Samarqand viloyati Urgut tumani";
    const parts = [b.city, b.district, b.mahalla, b.street, b.address].filter(Boolean);
    return parts.length > 0 ? parts.join(' ') : "Samarqand viloyati Urgut tumani";
};

// Filter state for Shaxmatka and Floor units list
const filterStatus = ref('');
const filterRooms = ref('');
const searchQuery = ref('');

const filterUnitsList = (units) => {
    if (!units) return [];
    return units.filter(u => {
        if (filterStatus.value && u.status !== filterStatus.value) return false;
        if (filterRooms.value && Number(u.rooms_count) !== Number(filterRooms.value)) return false;
        if (searchQuery.value && !String(u.unit_number).toLowerCase().includes(searchQuery.value.toLowerCase())) return false;
        return true;
    });
};

const activeFloorUnits = computed(() => {
    return filterUnitsList(activeFloor.value?.units || []);
});

// Shaxmatka columns
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

onMounted(() => {
    loadBuildingImageDimensions(buildingImageUrl.value);
    if (masterPlanImageUrl.value) {
        loadMasterPlanDimensions(masterPlanImageUrl.value);
    }
});
</script>

<template>
    <Head title="Shou room - 3D Maket va Xonadonlar - SHER-DOR ERP" />
    
    <!-- FULL SCREEN APP CONTAINER -->
    <div class="fixed inset-0 z-50 flex flex-col w-screen h-screen overflow-hidden select-none bg-[#f8fafc] font-sans">

        <!-- ========================================================================= -->
        <!-- UNIVERSAL TOP NAVIGATION BAR                                             -->
        <!-- ========================================================================= -->
        <header class="h-[58px] bg-white border-b border-slate-200 px-4 md:px-6 flex items-center justify-between shadow-xs shrink-0 z-40 gap-3">
            
            <!-- Left: Back to Dashboard & Object Title -->
            <div class="flex items-center gap-3 shrink-0">
                <Link 
                    :href="route('admin.dashboard')" 
                    class="h-9 px-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition-all flex items-center gap-1.5 cursor-pointer shadow-2xs"
                    title="Boshqaruv paneliga qaytish"
                >
                    <span class="text-sm font-black">←</span>
                    <span class="hidden sm:inline">Panel</span>
                </Link>

                <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

                <!-- Object Name -->
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-orange-500/10 border border-orange-500/20 text-orange-600 flex items-center justify-center font-black text-sm">
                        🏢
                    </span>
                    <div>
                        <h2 class="text-xs md:text-sm font-black text-slate-900 tracking-tight leading-none uppercase">
                            {{ selectedBuilding?.name || 'SHER-DOR TJM' }}
                        </h2>
                        <span class="text-[10px] font-bold text-orange-600 font-mono">
                            {{ selectedBuilding?.block_name || '1-BLOK' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Center: 1-Click Block Selector Tabs (Har bir bo'lim) -->
            <div class="hidden lg:flex items-center gap-1.5 bg-slate-100 p-1 rounded-xl border border-slate-200">
                <button
                    v-for="b in availableBlocks"
                    :key="'tab-b-' + b.id"
                    @click="selectBlock(b.id)"
                    :class="selectedBuildingId === b.id 
                        ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-md shadow-orange-500/20 font-black' 
                        : 'text-slate-600 hover:text-slate-900 hover:bg-white/50 font-bold'"
                    class="h-7 px-3 rounded-lg text-xs transition-all cursor-pointer flex items-center gap-1.5"
                >
                    <span>🏢</span>
                    <span>{{ b.block_name || b.corpus_name || b.name }}</span>
                </button>
            </div>

            <!-- Right: View Mode Switches (3D Maket, Fasad, Qavat, Shaxmatka, Galereya) -->
            <div class="flex items-center gap-1.5 shrink-0">
                <!-- Master plan toggle -->
                <button 
                    v-if="hasMasterPlan"
                    @click="viewMode = 'master_plan'"
                    :class="viewMode === 'master_plan' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="h-8.5 px-3 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 border border-slate-200/80 cursor-pointer"
                    title="Umumiy 3D Maket"
                >
                    <span>🌐</span>
                    <span class="hidden md:inline">3D Maket</span>
                </button>

                <!-- Facade toggle -->
                <button 
                    @click="viewMode = 'facade'"
                    :class="viewMode === 'facade' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="h-8.5 px-3 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 border border-slate-200/80 cursor-pointer"
                    title="Bino 3D Fasadi"
                >
                    <span>🏢</span>
                    <span class="hidden md:inline">3D Bino</span>
                </button>

                <!-- Floor Plan toggle -->
                <button 
                    @click="viewMode = 'floor_plan'"
                    :class="viewMode === 'floor_plan' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="h-8.5 px-3 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 border border-slate-200/80 cursor-pointer"
                    title="Qavat & Xonadonlar Rejasi"
                >
                    <span>📐</span>
                    <span class="hidden md:inline">Qavatlar</span>
                </button>

                <!-- Shaxmatka toggle -->
                <button 
                    @click="viewMode = 'shaxmatka'"
                    :class="viewMode === 'shaxmatka' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="h-8.5 px-3 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 border border-slate-200/80 cursor-pointer"
                    title="Shaxmatka ko'rinishi"
                >
                    <span>📊</span>
                    <span class="hidden md:inline">Shaxmatka</span>
                </button>

                <!-- 3D Showroom Renders Gallery toggle -->
                <button 
                    v-if="showroomImages.length > 0"
                    @click="viewMode = 'gallery'"
                    :class="viewMode === 'gallery' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/20' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    class="h-8.5 px-3 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 border border-slate-200/80 cursor-pointer"
                    title="3D Showroom Galereyasi"
                >
                    <span>🖼️</span>
                    <span class="hidden md:inline">3D Galereya</span>
                </button>
            </div>
        </header>

        <!-- Block Switcher Pills on Mobile/Tablet -->
        <div class="lg:hidden bg-white border-b border-slate-200 px-4 py-2 flex items-center gap-1.5 overflow-x-auto shrink-0 z-30">
            <button
                v-for="b in availableBlocks"
                :key="'mob-b-' + b.id"
                @click="selectBlock(b.id)"
                :class="selectedBuildingId === b.id ? 'bg-orange-500 text-white' : 'bg-slate-100 text-slate-700'"
                class="px-3 py-1 rounded-lg text-xs font-black shrink-0 cursor-pointer"
            >
                {{ b.block_name || b.corpus_name || b.name }}
            </button>
        </div>

        <!-- ========================================================================= -->
        <!-- 0. FULL SCREEN 3D COMPLEX MASTER PLAN (BOSH REJA)                        -->
        <!-- ========================================================================= -->
        <div v-if="viewMode === 'master_plan'" class="flex-1 w-full h-[calc(100vh-58px)] flex items-center justify-center overflow-hidden relative bg-[#f1f5f9]">
            <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                <img 
                    :src="masterPlanImageUrl || buildingImageUrl" 
                    class="w-full h-full object-cover filter blur-3xl opacity-60 scale-110"
                />
                <div class="absolute inset-0 bg-white/20 backdrop-blur-xl"></div>
            </div>

            <div class="relative z-10 w-full h-full p-3 md:p-6 flex items-center justify-center">
                <svg 
                    class="w-full h-full max-h-full block select-none pointer-events-auto filter drop-shadow-2xl rounded-2xl" 
                    :viewBox="`0 0 ${masterPlanNaturalSize.width} ${masterPlanNaturalSize.height}`"
                    preserveAspectRatio="xMidYMid meet"
                >
                    <image 
                        :href="masterPlanImageUrl || buildingImageUrl" 
                        x="0" 
                        y="0" 
                        :width="masterPlanNaturalSize.width" 
                        :height="masterPlanNaturalSize.height" 
                        preserveAspectRatio="xMidYMid meet"
                    />

                    <!-- Interactive Block Polygons on Master Plan -->
                    <g 
                        v-for="block in availableBlocks" 
                        :key="'mp-block-' + block.id"
                        class="cursor-pointer transition-all duration-200"
                        @mouseenter="hoveredBlock = block"
                        @mouseleave="hoveredBlock = null"
                        @click="selectBlock(block.id); viewMode = 'facade'"
                    >
                        <template v-if="getBlockMasterPlanShape(block)">
                            <rect 
                                v-if="getBlockMasterPlanShape(block).type === 'rect'"
                                :x="getBlockMasterPlanShape(block).x"
                                :y="getBlockMasterPlanShape(block).y"
                                :width="getBlockMasterPlanShape(block).width"
                                :height="getBlockMasterPlanShape(block).height"
                                :fill="hoveredBlock?.id === block.id ? 'rgba(249, 115, 22, 0.45)' : 'rgba(255, 255, 255, 0.08)'"
                                :stroke="hoveredBlock?.id === block.id ? '#f97316' : '#ffffff'"
                                :stroke-width="hoveredBlock?.id === block.id ? 4 : 2"
                                class="transition-all duration-200"
                            />
                            <polygon 
                                v-else-if="getBlockMasterPlanShape(block).type === 'polygon'"
                                :points="getBlockMasterPlanShape(block).points"
                                :fill="hoveredBlock?.id === block.id ? 'rgba(249, 115, 22, 0.45)' : 'rgba(255, 255, 255, 0.08)'"
                                :stroke="hoveredBlock?.id === block.id ? '#f97316' : '#ffffff'"
                                :stroke-width="hoveredBlock?.id === block.id ? 4 : 2"
                                class="transition-all duration-200"
                            />
                        </template>
                    </g>
                </svg>

                <!-- Floating Block Card on Hover -->
                <transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 translate-x-6 scale-95"
                    enter-to-class="opacity-100 translate-x-0 scale-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-x-0 scale-100"
                    leave-to-class="opacity-0 translate-x-6 scale-95"
                >
                    <div 
                        v-if="hoveredBlock"
                        @click="selectBlock(hoveredBlock.id); viewMode = 'facade'"
                        class="absolute right-6 md:right-12 top-1/4 z-50 w-80 bg-white/95 backdrop-blur-md shadow-2xl rounded-3xl p-5 text-slate-800 border border-slate-200/90 cursor-pointer pointer-events-auto"
                    >
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h3 class="text-base font-black text-slate-900 tracking-tight">
                                    {{ hoveredBlock.block_name || hoveredBlock.corpus_name || 'Blok' }}
                                </h3>
                                <p class="text-[11px] text-slate-500 font-medium">{{ hoveredBlock.name }}</p>
                            </div>
                            <span class="px-3 py-1 bg-orange-50 text-orange-600 border border-orange-200 rounded-xl text-xs font-black">
                                {{ hoveredBlock.total_floors || 9 }} Qavat
                            </span>
                        </div>
                        <div class="py-3 space-y-2 text-xs font-bold">
                            <div class="flex items-center justify-between bg-slate-50 p-2 rounded-xl">
                                <span class="text-slate-600">Jami xonadonlar:</span>
                                <span class="text-slate-900 font-black">{{ hoveredBlock.total_apartments || 45 }} ta</span>
                            </div>
                            <div class="flex items-center justify-between bg-slate-50 p-2 rounded-xl">
                                <span class="text-slate-600">Holati:</span>
                                <span class="text-amber-600 font-extrabold">{{ hoveredBlock.status || 'Qurilish jarayonida' }}</span>
                            </div>
                        </div>
                        <div class="mt-2 py-2.5 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-xl text-center text-xs font-black shadow-md flex items-center justify-center gap-2">
                            <span>🏢 Bino 3D Fasadini ochish →</span>
                        </div>
                    </div>
                </transition>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 1. FULL SCREEN 3D BINO FASADI (HAR BIR QAVATNI TANLASH)                  -->
        <!-- ========================================================================= -->
        <div v-else-if="viewMode === 'facade'" class="flex-1 w-full h-[calc(100vh-58px)] flex items-center justify-center overflow-hidden relative bg-[#f1f5f9]">
            
            <!-- Ambient Backdrop -->
            <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                <img 
                    :src="buildingImageUrl" 
                    class="w-full h-full object-cover filter blur-3xl opacity-60 scale-110"
                />
                <div class="absolute inset-0 bg-white/20 backdrop-blur-xl"></div>
            </div>

            <!-- Vertical Interactive 3D Floor Elevator (Chap tomondagi Qavatlar Panel) -->
            <div class="absolute left-4 md:left-8 top-1/2 -translate-y-1/2 z-30 flex flex-col gap-1.5 bg-white/90 backdrop-blur-md p-3 rounded-3xl shadow-2xl border border-slate-200/90 max-h-[85vh] overflow-y-auto w-48 sm:w-56">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-2 pb-1 border-b border-slate-100 flex items-center justify-between">
                    <span>QAVATLAR RO'YXATI</span>
                    <span class="text-orange-500 font-mono">{{ sortedFloors.length }} ta</span>
                </div>
                <button
                    v-for="floor in [...sortedFloors].reverse()"
                    :key="'elev-fl-' + floor.id"
                    @mouseenter="hoveredFloor = floor"
                    @mouseleave="hoveredFloor = null"
                    @click="openFloorPlan(floor)"
                    :class="hoveredFloor?.id === floor.id 
                        ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-md shadow-orange-500/25 scale-[1.02]' 
                        : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200/60'"
                    class="px-3 py-2 rounded-xl text-xs font-black transition-all flex items-center justify-between gap-2 cursor-pointer group text-left"
                >
                    <span class="flex items-center gap-1.5 truncate">
                        <span class="text-xs">{{ floor.floor_number < 0 ? '🅿️' : '🏢' }}</span>
                        <span class="truncate">{{ floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat` }}</span>
                    </span>
                    <span 
                        class="text-[10px] px-2 py-0.5 rounded-md font-bold shrink-0" 
                        :class="hoveredFloor?.id === floor.id ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800'"
                    >
                        {{ getFloorStats(floor).available }} ta
                    </span>
                </button>
            </div>

            <!-- Central 3D Facade SVG with Floor Click Targets -->
            <div class="relative z-10 w-full h-full p-3 md:p-6 flex items-center justify-center pl-56 sm:pl-64">
                <svg 
                    class="w-full h-full max-h-full block select-none pointer-events-auto filter drop-shadow-2xl rounded-2xl" 
                    :viewBox="`0 0 ${facadeNaturalSize.width} ${facadeNaturalSize.height}`"
                    preserveAspectRatio="xMidYMid meet"
                >
                    <image 
                        :href="buildingImageUrl" 
                        x="0" 
                        y="0" 
                        :width="facadeNaturalSize.width" 
                        :height="facadeNaturalSize.height" 
                        preserveAspectRatio="xMidYMid meet"
                    />

                    <!-- Interactive Floor Overlays on Facade -->
                    <g 
                        v-for="(floor, idx) in sortedFloors" 
                        :key="'facade-fl-' + floor.id"
                        class="cursor-pointer transition-all duration-200"
                        @mouseenter="hoveredFloor = floor"
                        @mouseleave="hoveredFloor = null"
                        @click="openFloorPlan(floor)"
                    >
                        <template v-if="getFloorFacadeShape(floor, idx, sortedFloors.length)">
                            <rect 
                                v-if="getFloorFacadeShape(floor, idx, sortedFloors.length).type === 'rect'"
                                :x="getFloorFacadeShape(floor, idx, sortedFloors.length).x"
                                :y="getFloorFacadeShape(floor, idx, sortedFloors.length).y"
                                :width="getFloorFacadeShape(floor, idx, sortedFloors.length).width"
                                :height="getFloorFacadeShape(floor, idx, sortedFloors.length).height"
                                :fill="hoveredFloor?.id === floor.id ? 'rgba(249, 115, 22, 0.45)' : 'rgba(255, 255, 255, 0.02)'"
                                :stroke="hoveredFloor?.id === floor.id ? '#f97316' : 'rgba(255, 255, 255, 0.3)'"
                                :stroke-width="hoveredFloor?.id === floor.id ? 3.5 : 1"
                                class="transition-all duration-200"
                            />
                            <polygon 
                                v-else-if="getFloorFacadeShape(floor, idx, sortedFloors.length).type === 'polygon'"
                                :points="getFloorFacadeShape(floor, idx, sortedFloors.length).points"
                                :fill="hoveredFloor?.id === floor.id ? 'rgba(249, 115, 22, 0.45)' : 'rgba(255, 255, 255, 0.02)'"
                                :stroke="hoveredFloor?.id === floor.id ? '#f97316' : 'rgba(255, 255, 255, 0.3)'"
                                :stroke-width="hoveredFloor?.id === floor.id ? 3.5 : 1"
                                class="transition-all duration-200"
                            />
                        </template>
                    </g>
                </svg>

                <!-- Floating Floor Preview Card on Hover -->
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
                        class="absolute right-6 md:right-12 top-1/4 z-50 w-84 bg-white/95 backdrop-blur-md shadow-2xl rounded-3xl p-5 text-slate-800 border border-slate-200/90 cursor-pointer pointer-events-auto"
                    >
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h3 class="text-base font-black text-slate-900 tracking-tight">
                                    {{ selectedBuilding?.block_name || '1-BLOK' }}
                                </h3>
                                <p class="text-[11px] text-slate-500 font-medium">{{ selectedBuilding?.name }}</p>
                            </div>
                            <span class="px-3 py-1 bg-orange-50 text-orange-600 border border-orange-200 rounded-xl text-xs font-black">
                                {{ hoveredFloor.floor_number < 0 ? `${hoveredFloor.floor_number}-Yerto'la` : `${hoveredFloor.floor_number}-Qavat` }}
                            </span>
                        </div>

                        <div class="py-3 space-y-2 text-xs font-bold">
                            <div class="flex items-center justify-between bg-slate-50 p-2 rounded-xl">
                                <span class="text-slate-600">Sotuvda mavjud:</span>
                                <span class="text-emerald-700 font-black">🟢 {{ getFloorStats(hoveredFloor).available }} ta xonadon</span>
                            </div>
                            <div class="flex items-center justify-between bg-slate-50 p-2 rounded-xl">
                                <span class="text-slate-600">Sotilgan:</span>
                                <span class="text-red-700 font-black">🔴 {{ getFloorStats(hoveredFloor).sold }} ta xonadon</span>
                            </div>
                            <div class="flex items-center justify-between bg-slate-50 p-2 rounded-xl" v-if="getFloorStats(hoveredFloor).reserved > 0">
                                <span class="text-slate-600">Band qilingan:</span>
                                <span class="text-amber-700 font-black">🟡 {{ getFloorStats(hoveredFloor).reserved }} ta xonadon</span>
                            </div>
                            <div class="flex items-center justify-between p-2">
                                <span class="text-slate-500">Jami xonadonlar:</span>
                                <span class="text-slate-900 font-black">{{ getFloorStats(hoveredFloor).total }} ta</span>
                            </div>
                        </div>

                        <div class="mt-2 py-2.5 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-xl text-center text-xs font-black shadow-md flex items-center justify-center gap-2">
                            <span>📐 Qavat chizmasi va xonadonlarni ochish →</span>
                        </div>
                    </div>
                </transition>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 2. FULL SCREEN QAVAT & XONADONLAR (3D MODELLAR VA CHIZMA)                -->
        <!-- ========================================================================= -->
        <div v-else-if="viewMode === 'floor_plan'" class="flex-1 flex flex-col w-full h-[calc(100vh-58px)] overflow-hidden bg-[#f1f5f9]">
            
            <!-- Floor Plan Top Bar: Quick Floor Navigation Pills & Mode Switcher -->
            <div class="bg-white border-b border-slate-200 px-4 md:px-6 py-2.5 flex items-center justify-between gap-4 shrink-0 z-30 shadow-xs">
                
                <!-- Floor selector pills: -1, 1, 2, ... 8 -->
                <div class="flex items-center gap-1.5 overflow-x-auto">
                    <button
                        v-for="fl in sortedFloors"
                        :key="'fl-pill-' + fl.id"
                        @click="activeFloor = fl"
                        :class="activeFloor?.id === fl.id 
                            ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-md shadow-orange-500/20 font-black scale-105' 
                            : 'bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold'"
                        class="px-3 py-1.5 rounded-xl text-xs transition-all shrink-0 cursor-pointer flex items-center gap-1"
                    >
                        <span>{{ fl.floor_number < 0 ? '🅿️' : '🏢' }}</span>
                        <span>{{ fl.floor_number < 0 ? `${fl.floor_number}-yerto'la` : `${fl.floor_number}-qavat` }}</span>
                    </button>
                </div>

                <!-- Display mode: Split vs 3D Cards Grid -->
                <div class="flex items-center gap-2 shrink-0">
                    <button 
                        @click="floorPlanDisplayMode = 'split'"
                        :class="floorPlanDisplayMode === 'split' ? 'bg-orange-50 text-orange-600 border-orange-300 font-black' : 'bg-white text-slate-600 border-slate-200 font-bold'"
                        class="px-3 py-1.5 rounded-xl text-xs border transition-all flex items-center gap-1.5 cursor-pointer"
                        title="Chizma va Xonadonlar"
                    >
                        <span>📐</span>
                        <span class="hidden sm:inline">Chizma + Ro'yxat</span>
                    </button>
                    <button 
                        @click="floorPlanDisplayMode = 'cards'"
                        :class="floorPlanDisplayMode === 'cards' ? 'bg-orange-50 text-orange-600 border-orange-300 font-black' : 'bg-white text-slate-600 border-slate-200 font-bold'"
                        class="px-3 py-1.5 rounded-xl text-xs border transition-all flex items-center gap-1.5 cursor-pointer"
                        title="Faqat 3D Kartochkalar"
                    >
                        <span>🏢</span>
                        <span class="hidden sm:inline">3D Kartochkalar</span>
                    </button>
                </div>
            </div>

            <!-- Floor View Area: Split or Grid -->
            <div class="flex-1 w-full h-[calc(100vh-108px)] overflow-hidden flex">
                
                <!-- SPLIT MODE: Left SVG Blueprint (65%) + Right 3D Units List (35%) -->
                <template v-if="floorPlanDisplayMode === 'split'">
                    <!-- Left: Blueprint SVG -->
                    <div class="flex-1 h-full p-4 md:p-6 flex items-center justify-center relative overflow-hidden bg-[#f1f5f9]">
                        <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                            <img 
                                :src="activeFloorBlueprintUrl" 
                                class="w-full h-full object-cover filter blur-3xl opacity-40 scale-110"
                            />
                            <div class="absolute inset-0 bg-white/30 backdrop-blur-xl"></div>
                        </div>

                        <div class="relative z-10 w-full h-full flex items-center justify-center">
                            <svg 
                                class="w-full h-full max-h-full block select-none pointer-events-auto filter drop-shadow-2xl rounded-2xl" 
                                :viewBox="`0 0 ${floorBlueprintNaturalSize.width} ${floorBlueprintNaturalSize.height}`"
                                preserveAspectRatio="xMidYMid meet"
                            >
                                <image 
                                    :href="activeFloorBlueprintUrl" 
                                    x="0" 
                                    y="0" 
                                    :width="floorBlueprintNaturalSize.width" 
                                    :height="floorBlueprintNaturalSize.height" 
                                    preserveAspectRatio="xMidYMid meet"
                                />

                                <!-- Unit SVG Polygons -->
                                <g 
                                    v-for="unit in (activeFloor?.units || [])" 
                                    :key="'unit-svg-' + unit.id"
                                    class="cursor-pointer transition-all duration-200"
                                    @mouseenter="hoveredUnit = unit"
                                    @mouseleave="hoveredUnit = null"
                                    @click="openUnitModal(unit)"
                                >
                                    <template v-if="getUnitSvgShape(unit)">
                                        <rect 
                                            v-if="getUnitSvgShape(unit).type === 'rect'"
                                            :x="getUnitSvgShape(unit).x"
                                            :y="getUnitSvgShape(unit).y"
                                            :width="getUnitSvgShape(unit).width"
                                            :height="getUnitSvgShape(unit).height"
                                            :fill="getUnitFillColor(unit, hoveredUnit?.id === unit.id)"
                                            :stroke="hoveredUnit?.id === unit.id ? '#ffffff' : getUnitStrokeColor(unit)"
                                            :stroke-width="hoveredUnit?.id === unit.id ? 4 : 2"
                                            class="transition-all duration-200"
                                        />
                                        <polygon 
                                            v-else-if="getUnitSvgShape(unit).type === 'polygon'"
                                            :points="getUnitSvgShape(unit).points"
                                            :fill="getUnitFillColor(unit, hoveredUnit?.id === unit.id)"
                                            :stroke="hoveredUnit?.id === unit.id ? '#ffffff' : getUnitStrokeColor(unit)"
                                            :stroke-width="hoveredUnit?.id === unit.id ? 4 : 2"
                                            class="transition-all duration-200"
                                        />
                                    </template>
                                </g>
                            </svg>

                            <!-- Blueprint Hover Tooltip -->
                            <transition
                                enter-active-class="transition duration-150 ease-out"
                                enter-from-class="opacity-0 scale-95"
                                enter-to-class="opacity-100 scale-100"
                                leave-active-class="transition duration-100 ease-in"
                                leave-from-class="opacity-100 scale-100"
                                leave-to-class="opacity-0 scale-95"
                            >
                                <div 
                                    v-if="hoveredUnit"
                                    class="absolute top-4 left-4 z-30 bg-white/95 backdrop-blur-md shadow-xl rounded-2xl p-4 border border-slate-200/90 pointer-events-none w-64"
                                >
                                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                        <h4 class="font-black text-sm text-slate-900">№ {{ hoveredUnit.unit_number }}-xonadon</h4>
                                        <span 
                                            class="text-[10px] font-black px-2 py-0.5 rounded-full"
                                            :class="hoveredUnit.status === 'sold' ? 'bg-red-100 text-red-700' : (hoveredUnit.status === 'reserved' || hoveredUnit.status === 'booked' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700')"
                                        >
                                            {{ hoveredUnit.status === 'sold' ? 'Sotilgan' : (hoveredUnit.status === 'reserved' || hoveredUnit.status === 'booked' ? 'Band' : 'Sotuvda') }}
                                        </span>
                                    </div>
                                    <div class="pt-2 text-xs space-y-1 font-bold text-slate-600">
                                        <div class="flex justify-between"><span>Xonalar:</span><span class="text-slate-900">{{ hoveredUnit.rooms_count }} xona</span></div>
                                        <div class="flex justify-between"><span>Maydoni:</span><span class="text-slate-900">{{ hoveredUnit.area_sqm }} m²</span></div>
                                        <div class="flex justify-between text-orange-600"><span>Narxi:</span><span class="font-black">{{ formatSoM(hoveredUnit.price) }}</span></div>
                                    </div>
                                </div>
                            </transition>
                        </div>
                    </div>

                    <!-- Right: Interactive Units List (35% width) -->
                    <div class="w-80 sm:w-96 md:w-[420px] h-full bg-white border-l border-slate-200 flex flex-col shrink-0 z-20 shadow-lg">
                        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 class="font-black text-sm text-slate-900">
                                    {{ activeFloor?.floor_number < 0 ? `${activeFloor?.floor_number}-yerto'la` : `${activeFloor?.floor_number}-qavat` }} xonadonlari
                                </h3>
                                <p class="text-[11px] text-slate-400 font-medium">Barcha xonadonlar 3D modeli bilan</p>
                            </div>
                            <span class="px-2.5 py-1 bg-orange-50 text-orange-600 font-mono font-black text-xs rounded-xl border border-orange-200">
                                {{ activeFloorUnits.length }} ta
                            </span>
                        </div>

                        <!-- Scrollable Units List -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-3">
                            <div 
                                v-for="unit in activeFloorUnits" 
                                :key="'list-unit-' + unit.id"
                                @click="openUnitModal(unit)"
                                @mouseenter="hoveredUnit = unit"
                                @mouseleave="hoveredUnit = null"
                                class="bg-slate-50 hover:bg-orange-50/40 p-3 rounded-2xl border border-slate-200/80 hover:border-orange-300 transition-all cursor-pointer shadow-xs group"
                            >
                                <div class="flex items-center gap-3">
                                    <!-- 3D Plan Thumbnail -->
                                    <div class="w-18 h-18 rounded-xl bg-white border border-slate-200 p-1 flex items-center justify-center shrink-0 overflow-hidden group-hover:border-orange-400 transition-all">
                                        <img 
                                            v-if="unit.model_3d_path" 
                                            :src="'/storage/' + unit.model_3d_path" 
                                            class="w-full h-full object-contain"
                                            loading="lazy"
                                        />
                                        <img 
                                            v-else-if="unit.blueprint_path" 
                                            :src="'/storage/' + unit.blueprint_path" 
                                            class="w-full h-full object-contain opacity-80"
                                            loading="lazy"
                                        />
                                        <span v-else class="text-xs text-slate-300 font-mono font-bold">3D</span>
                                    </div>

                                    <!-- Unit Details -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1 mb-1">
                                            <span class="font-black text-sm text-slate-900">№ {{ unit.unit_number }}-xonadon</span>
                                            <span 
                                                class="text-[9px] font-black px-2 py-0.5 rounded-md uppercase"
                                                :class="unit.status === 'sold' ? 'bg-red-100 text-red-700' : (unit.status === 'reserved' || unit.status === 'booked' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700')"
                                            >
                                                {{ unit.status === 'sold' ? 'Sotilgan' : (unit.status === 'reserved' || unit.status === 'booked' ? 'Band' : 'Sotuvda') }}
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-3 text-xs font-bold text-slate-500 mb-1.5">
                                            <span>{{ unit.rooms_count }} xona</span>
                                            <span>•</span>
                                            <span>{{ unit.area_sqm }} m²</span>
                                            <span v-if="unit.podyezd">• {{ unit.podyezd }}-podyezd</span>
                                        </div>

                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-black text-orange-600 font-mono">{{ formatSoM(unit.price) }}</span>
                                            <span class="text-[10px] text-slate-400 group-hover:text-orange-600 font-bold transition-all">3D Ko'rish →</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- CARDS MODE: Responsive 3D Cards Grid for whole floor -->
                <template v-else-if="floorPlanDisplayMode === 'cards'">
                    <div class="flex-1 h-full overflow-y-auto p-6 bg-[#f8fafc]">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-5 max-w-7xl mx-auto">
                            <div 
                                v-for="unit in activeFloorUnits" 
                                :key="'card-unit-' + unit.id"
                                @click="openUnitModal(unit)"
                                class="bg-white rounded-3xl border border-slate-200/90 shadow-sm hover:shadow-xl hover:border-orange-300 hover:-translate-y-1 transition-all duration-200 p-4 flex flex-col justify-between cursor-pointer group"
                            >
                                <!-- Top: Status & Number -->
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-8 h-8 rounded-xl bg-orange-500/10 text-orange-600 font-black text-xs flex items-center justify-center font-mono">
                                            {{ unit.unit_number }}
                                        </span>
                                        <span class="font-black text-sm text-slate-900">№ {{ unit.unit_number }}</span>
                                    </div>
                                    <span 
                                        class="text-[10px] font-black px-2.5 py-1 rounded-full uppercase"
                                        :class="unit.status === 'sold' ? 'bg-red-100 text-red-700' : (unit.status === 'reserved' || unit.status === 'booked' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700')"
                                    >
                                        {{ unit.status === 'sold' ? 'Sotilgan' : (unit.status === 'reserved' || unit.status === 'booked' ? 'Band' : 'Sotuvda') }}
                                    </span>
                                </div>

                                <!-- Center: 3D Layout Preview -->
                                <div class="w-full h-48 bg-slate-50 rounded-2xl border border-slate-100 p-2 flex items-center justify-center overflow-hidden mb-3 group-hover:border-orange-200 transition-all">
                                    <img 
                                        v-if="unit.model_3d_path" 
                                        :src="'/storage/' + unit.model_3d_path" 
                                        class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                    <img 
                                        v-else-if="unit.blueprint_path" 
                                        :src="'/storage/' + unit.blueprint_path" 
                                        class="w-full h-full object-contain opacity-80"
                                        loading="lazy"
                                    />
                                    <div v-else class="text-center text-slate-300 font-bold text-xs">
                                        3D Reja
                                    </div>
                                </div>

                                <!-- Bottom: Specs & Price -->
                                <div>
                                    <div class="grid grid-cols-2 gap-2 text-xs font-bold text-slate-600 bg-slate-50 p-2.5 rounded-xl mb-3">
                                        <div><span class="text-slate-400 text-[10px] block">Xonalar</span>{{ unit.rooms_count }} xona</div>
                                        <div><span class="text-slate-400 text-[10px] block">Maydoni</span>{{ unit.area_sqm }} m²</div>
                                    </div>

                                    <div class="flex items-center justify-between pt-1">
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase block">Qiymati</span>
                                            <span class="text-sm font-black text-slate-900">{{ formatSoM(unit.price) }}</span>
                                        </div>
                                        <button class="px-3.5 py-1.5 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-xl text-xs font-black shadow-xs group-hover:shadow-md transition-all">
                                            3D Ko'rish →
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 3. FULL SCREEN BLOK SHAXMATKASI                                          -->
        <!-- ========================================================================= -->
        <div v-else-if="viewMode === 'shaxmatka'" class="flex-1 flex flex-col w-full h-[calc(100vh-58px)] overflow-hidden bg-[#f8fafc]">
            <!-- Shaxmatka Top Toolbar -->
            <div class="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between gap-4 shrink-0 shadow-xs">
                <div class="flex items-center gap-3">
                    <button 
                        @click="viewMode = 'facade'"
                        class="h-8 px-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition-all flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>←</span>
                        <span>Fasadga qaytish</span>
                    </button>
                    <span class="font-black text-sm text-slate-900">
                        {{ selectedBuilding?.block_name || '1-BLOK' }} Shaxmatkasi
                    </span>
                </div>

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
            </div>

            <!-- Shaxmatka 2-Columns Grid Layout -->
            <div class="flex-1 overflow-auto px-8 py-6 bg-[#f8fafc]">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10 gap-y-4 max-w-7xl mx-auto">
                    <!-- Left Column -->
                    <div class="space-y-3">
                        <div 
                            v-for="floor in leftColumnFloors" 
                            :key="'col1-fl-' + floor.id"
                            class="flex items-center gap-3 bg-white p-2.5 rounded-2xl border border-slate-200/80 shadow-xs"
                        >
                            <div class="px-4 py-2 bg-slate-900 text-white font-black text-xs rounded-xl shrink-0 min-w-[110px] text-center">
                                {{ floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat` }}
                            </div>
                            <div class="flex flex-wrap items-center gap-2 flex-1">
                                <button 
                                    v-for="unit in (floor.units || [])" 
                                    :key="'shax-u-' + unit.id"
                                    @click="openUnitModal(unit)"
                                    :class="unit.status === 'sold' ? 'bg-red-500 hover:bg-red-600 text-white' : (unit.status === 'reserved' || unit.status === 'booked' ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-emerald-500 hover:bg-emerald-600 text-white')"
                                    class="px-3 py-2 rounded-xl font-black text-xs shadow-xs transition-all duration-150 hover:scale-105 active:scale-95 shrink-0 min-w-[65px] cursor-pointer"
                                >
                                    {{ unit.unit_number }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-3" v-if="rightColumnFloors.length > 0">
                        <div 
                            v-for="floor in rightColumnFloors" 
                            :key="'col2-fl-' + floor.id"
                            class="flex items-center gap-3 bg-white p-2.5 rounded-2xl border border-slate-200/80 shadow-xs"
                        >
                            <div class="px-4 py-2 bg-slate-900 text-white font-black text-xs rounded-xl shrink-0 min-w-[110px] text-center">
                                {{ floor.floor_number < 0 ? `${floor.floor_number}-yerto'la` : `${floor.floor_number}-qavat` }}
                            </div>
                            <div class="flex flex-wrap items-center gap-2 flex-1">
                                <button 
                                    v-for="unit in (floor.units || [])" 
                                    :key="'shax-ur-' + unit.id"
                                    @click="openUnitModal(unit)"
                                    :class="unit.status === 'sold' ? 'bg-red-500 hover:bg-red-600 text-white' : (unit.status === 'reserved' || unit.status === 'booked' ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-emerald-500 hover:bg-emerald-600 text-white')"
                                    class="px-3 py-2 rounded-xl font-black text-xs shadow-xs transition-all duration-150 hover:scale-105 active:scale-95 shrink-0 min-w-[65px] cursor-pointer"
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
        <!-- 4. FULL SCREEN 3D SHOWROOM RENDERS GALLERY                               -->
        <!-- ========================================================================= -->
        <div v-else-if="viewMode === 'gallery'" class="flex-1 flex flex-col w-full h-[calc(100vh-58px)] overflow-hidden bg-slate-950">
            <div class="flex-1 flex items-center justify-center p-6 relative">
                <img 
                    v-if="showroomImages.length > 0"
                    :src="'/storage/' + showroomImages[activeShowroomImageIndex]" 
                    class="max-w-full max-h-full object-contain rounded-2xl shadow-2xl cursor-zoom-in"
                    @click="openLightbox('/storage/' + showroomImages[activeShowroomImageIndex])"
                />
            </div>

            <!-- Thumbnail Carousel -->
            <div class="h-24 bg-slate-900/90 border-t border-slate-800 px-6 flex items-center justify-center gap-3 overflow-x-auto shrink-0">
                <button
                    v-for="(img, idx) in showroomImages"
                    :key="'gal-thumb-' + idx"
                    @click="activeShowroomImageIndex = idx"
                    :class="activeShowroomImageIndex === idx ? 'border-orange-500 scale-105 shadow-lg' : 'border-slate-700 opacity-60 hover:opacity-100'"
                    class="h-16 w-24 rounded-xl overflow-hidden border-2 transition-all shrink-0 cursor-pointer"
                >
                    <img :src="'/storage/' + img" class="w-full h-full object-cover" />
                </button>
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- XONADON 3D BATAFSIL MODALI (3D MODEL + 2D CHIZMA + PARAMETRLAR)          -->
    <!-- ========================================================================= -->
    <Teleport to="body">
        <div v-if="showUnitDetailModal && selectedUnit" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
            <div class="bg-white rounded-3xl shadow-2xl border border-slate-200/90 w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in duration-150">
                
                <!-- Modal Header -->
                <div class="px-6 py-4 bg-white border-b border-slate-100 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div 
                            class="px-3.5 py-1.5 rounded-xl font-black text-sm border shadow-xs" 
                            :class="selectedUnit.status === 'available' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : (selectedUnit.status === 'sold' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-amber-50 text-amber-700 border-amber-200')"
                        >
                            № {{ selectedUnit.unit_number }}
                        </div>
                        <div>
                            <h3 class="font-black text-base text-slate-900 tracking-tight">
                                {{ selectedUnit.unit_number }}-xonadon ({{ selectedUnit.rooms_count }} xonali)
                            </h3>
                            <p class="text-xs text-slate-500 font-medium">
                                {{ selectedBuilding?.name }} • {{ selectedBuilding?.block_name || '1-BLOK' }} • {{ activeFloor?.floor_number < 0 ? `${activeFloor?.floor_number}-yerto'la` : `${activeFloor?.floor_number}-qavat` }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            @click="showUnitDetailModal = false" 
                            class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 flex items-center justify-center font-bold text-sm transition-all cursor-pointer"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <!-- Modal Body: 2 Columns (Left: 3D/2D Visual, Right: Specifications) -->
                <div class="flex-1 overflow-y-auto p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Left: 3D Reja & 2D Chizma Display -->
                    <div class="flex flex-col gap-3">
                        <!-- Visual Mode Switcher (3D vs 2D) -->
                        <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-xl self-start">
                            <button 
                                v-if="selectedUnit.model_3d_path"
                                @click="unitModalImageMode = '3d'"
                                :class="unitModalImageMode === '3d' ? 'bg-white text-orange-600 shadow-xs font-black' : 'text-slate-600 font-bold'"
                                class="px-3 py-1 rounded-lg text-xs transition-all cursor-pointer flex items-center gap-1.5"
                            >
                                <span>🏢</span>
                                <span>3D Reja</span>
                            </button>
                            <button 
                                v-if="selectedUnit.blueprint_path"
                                @click="unitModalImageMode = '2d'"
                                :class="unitModalImageMode === '2d' ? 'bg-white text-orange-600 shadow-xs font-black' : 'text-slate-600 font-bold'"
                                class="px-3 py-1 rounded-lg text-xs transition-all cursor-pointer flex items-center gap-1.5"
                            >
                                <span>📐</span>
                                <span>2D Chizma</span>
                            </button>
                        </div>

                        <!-- Image Viewer Box -->
                        <div class="w-full h-72 sm:h-80 bg-slate-50 rounded-2xl border border-slate-200 p-2 flex items-center justify-center relative overflow-hidden group">
                            <template v-if="unitModalImageMode === '3d' && selectedUnit.model_3d_path">
                                <img 
                                    :src="'/storage/' + selectedUnit.model_3d_path" 
                                    class="w-full h-full object-contain cursor-zoom-in transition-transform duration-300 group-hover:scale-105"
                                    @click="openLightbox('/storage/' + selectedUnit.model_3d_path)"
                                />
                                <button 
                                    @click="openLightbox('/storage/' + selectedUnit.model_3d_path)"
                                    class="absolute bottom-3 right-3 px-3 py-1.5 bg-white/90 hover:bg-white text-slate-800 text-xs font-black rounded-xl shadow-md border border-slate-200 flex items-center gap-1.5 opacity-90 group-hover:opacity-100 transition-all cursor-pointer"
                                >
                                    <span>🔍</span>
                                    <span>Kattalashtirish</span>
                                </button>
                            </template>

                            <template v-else-if="unitModalImageMode === '2d' && selectedUnit.blueprint_path">
                                <img 
                                    :src="'/storage/' + selectedUnit.blueprint_path" 
                                    class="w-full h-full object-contain cursor-zoom-in transition-transform duration-300 group-hover:scale-105"
                                    @click="openLightbox('/storage/' + selectedUnit.blueprint_path)"
                                />
                                <button 
                                    @click="openLightbox('/storage/' + selectedUnit.blueprint_path)"
                                    class="absolute bottom-3 right-3 px-3 py-1.5 bg-white/90 hover:bg-white text-slate-800 text-xs font-black rounded-xl shadow-md border border-slate-200 flex items-center gap-1.5 opacity-90 group-hover:opacity-100 transition-all cursor-pointer"
                                >
                                    <span>🔍</span>
                                    <span>Kattalashtirish</span>
                                </button>
                            </template>

                            <div v-else class="text-center text-slate-400 font-bold text-xs">
                                Rasm mavjud emas
                            </div>
                        </div>
                    </div>

                    <!-- Right: Parameters & Financial Details -->
                    <div class="flex flex-col justify-between space-y-4">
                        
                        <!-- Status Banner -->
                        <div 
                            class="flex items-center justify-between p-3 rounded-2xl border" 
                            :class="selectedUnit.status === 'available' ? 'bg-emerald-50/70 border-emerald-200 text-emerald-800' : (selectedUnit.status === 'sold' ? 'bg-red-50/70 border-red-200 text-red-800' : 'bg-amber-50/70 border-amber-200 text-amber-800')"
                        >
                            <div class="flex items-center gap-2 font-bold text-xs">
                                <span>{{ selectedUnit.status === 'available' ? '🟢' : (selectedUnit.status === 'sold' ? '🔴' : '🟡') }}</span>
                                <span class="uppercase tracking-wider text-[11px] font-black">Xonadon Holati:</span>
                            </div>
                            <span 
                                class="px-3 py-0.5 rounded-full text-xs font-black bg-white border" 
                                :class="selectedUnit.status === 'available' ? 'text-emerald-700 border-emerald-200' : (selectedUnit.status === 'sold' ? 'text-red-700 border-red-200' : 'text-amber-700 border-amber-200')"
                            >
                                {{ selectedUnit.status === 'available' ? 'Sotuvda mavjud' : (selectedUnit.status === 'sold' ? 'Sotilgan' : 'Band qilingan') }}
                            </span>
                        </div>

                        <!-- 6 Parameters Grid -->
                        <div class="grid grid-cols-2 gap-2.5">
                            <div class="bg-slate-50 p-2.5 rounded-2xl border border-slate-200/80">
                                <span class="text-[10px] font-extrabold text-slate-400 block uppercase tracking-wider">Umumiy Maydon</span>
                                <span class="text-sm font-black text-slate-900 mt-0.5 inline-block">{{ selectedUnit.area_sqm }} m²</span>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-2xl border border-slate-200/80">
                                <span class="text-[10px] font-extrabold text-slate-400 block uppercase tracking-wider">Xonalar Soni</span>
                                <span class="text-sm font-black text-slate-900 mt-0.5 inline-block">{{ selectedUnit.rooms_count }} xona</span>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-2xl border border-slate-200/80">
                                <span class="text-[10px] font-extrabold text-slate-400 block uppercase tracking-wider">Joylashgan Qavat</span>
                                <span class="text-sm font-black text-slate-900 mt-0.5 inline-block">{{ activeFloor?.floor_number < 0 ? `${activeFloor?.floor_number}-yerto'la` : `${activeFloor?.floor_number}-qavat` }}</span>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-2xl border border-slate-200/80">
                                <span class="text-[10px] font-extrabold text-slate-400 block uppercase tracking-wider">Podyezd (Kirish)</span>
                                <span class="text-sm font-black text-slate-900 mt-0.5 inline-block">{{ selectedUnit.podyezd || 1 }}-podyezd</span>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-2xl border border-slate-200/80">
                                <span class="text-[10px] font-extrabold text-slate-400 block uppercase tracking-wider">Balkon</span>
                                <span class="text-sm font-black text-slate-900 mt-0.5 inline-block">{{ selectedUnit.has_balcony ? 'Mavjud' : 'Yo\'q' }}</span>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-2xl border border-slate-200/80">
                                <span class="text-[10px] font-extrabold text-slate-400 block uppercase tracking-wider">Oshxona / Sanuzel</span>
                                <span class="text-sm font-black text-slate-900 mt-0.5 inline-block">{{ selectedUnit.kitchens_count || 1 }} / {{ selectedUnit.bathrooms_count || 1 }}</span>
                            </div>
                        </div>

                        <!-- Price Card -->
                        <div class="bg-gradient-to-br from-orange-50 to-amber-50 border border-orange-200 rounded-2xl p-4 flex items-center justify-between">
                            <div>
                                <span class="text-[11px] font-extrabold text-orange-700 uppercase tracking-wider block">Jami Narxi</span>
                                <span class="text-xl font-black text-slate-900 tracking-tight">{{ formatSoM(selectedUnit.price) }}</span>
                            </div>
                            <div v-if="selectedUnit.area_sqm > 0 && selectedUnit.price > 0" class="text-right">
                                <span class="text-[10px] font-bold text-slate-400 block uppercase">1 m² narxi</span>
                                <span class="text-xs font-black text-orange-600 font-mono">
                                    {{ formatSoM(Math.round(selectedUnit.price / selectedUnit.area_sqm)) }}
                                </span>
                            </div>
                        </div>

                        <!-- Client details if sold/reserved -->
                        <div 
                            v-if="selectedUnit.active_contract || selectedUnit.contracts?.length" 
                            class="bg-slate-50 p-3 rounded-2xl border border-slate-200 text-xs"
                        >
                            <span class="text-[10px] font-black uppercase text-slate-400 block mb-1">Mijoz va Shartnoma:</span>
                            <div class="font-bold text-slate-800">
                                {{ selectedUnit.active_contract?.client?.name || selectedUnit.contracts?.[0]?.client?.name || 'Mijoz' }}
                            </div>
                            <div class="text-slate-500 text-[11px]">
                                Shartnoma № {{ selectedUnit.active_contract?.contract_number || selectedUnit.contracts?.[0]?.contract_number }}
                            </div>
                        </div>

                        <!-- Modal Actions -->
                        <div class="flex items-center gap-3 pt-2">
                            <Link 
                                v-if="selectedUnit.status === 'available'" 
                                :href="route('admin.crm', { unit_id: selectedUnit.id })" 
                                class="flex-1 py-3 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-black text-xs rounded-2xl shadow-lg shadow-orange-500/25 transition-all text-center flex items-center justify-center gap-2 cursor-pointer"
                            >
                                <span>📝</span>
                                <span>Shartnoma tuzish (Sotish)</span>
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
            </div>
        </div>
    </Teleport>

    <!-- Fullscreen Lightbox Modal -->
    <Teleport to="body">
        <div 
            v-if="lightboxImage" 
            class="fixed inset-0 z-[80] bg-black/90 backdrop-blur-md flex items-center justify-center p-4"
            @click="closeLightbox"
        >
            <button 
                @click="closeLightbox" 
                class="absolute top-6 right-6 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center font-bold text-lg transition-all cursor-pointer z-10"
            >
                ✕
            </button>
            <img 
                :src="lightboxImage" 
                class="max-w-full max-h-[92vh] object-contain rounded-2xl shadow-2xl" 
                @click.stop
            />
        </div>
    </Teleport>
</template>
