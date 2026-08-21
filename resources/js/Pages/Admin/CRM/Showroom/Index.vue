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
const activeTab = ref('overview'); // overview, shaxmatka

const selectBuilding = (id) => {
    selectedBuildingId.value = id;
};

// Shaxmatka logic
const selectedFloor = ref(null);
const selectedUnit = ref(null);

const getUnitColor = (unit) => {
    if (unit.status === 'available') return 'bg-emerald-500 text-white';
    if (unit.status === 'sold') return 'bg-red-500 text-white';
    if (unit.status === 'booked' || unit.status === 'reserved') return 'bg-amber-500 text-white';
    return 'bg-gray-300 text-gray-700';
};

const podyezdsToShow = computed(() => {
    if (!selectedBuilding.value || !selectedBuilding.value.podezes) return [];
    return selectedBuilding.value.podezes;
});

const getSortedFloorsForPodez = (podez) => {
    if (!podez || !podez.floors) return [];
    return [...podez.floors].sort((a, b) => b.floor_number - a.floor_number);
};

const getUnitsByFloorAndPodyezd = (floor, podez) => {
    if (!floor || !floor.units) return [];
    return floor.units.sort((a, b) => {
        const numA = parseInt(a.unit_number.replace(/\D/g, '')) || 0;
        const numB = parseInt(b.unit_number.replace(/\D/g, '')) || 0;
        return numA - numB;
    });
};

const formatSoM = (val) => {
    if (!val) return '0 UZS';
    return new Intl.NumberFormat('uz-UZ').format(val) + ' UZS';
};

// Filters
const searchQuery = ref('');
const filterStatus = ref('');
const filterRooms = ref('');

const matchesFilter = (unit) => {
    if (searchQuery.value && !unit.unit_number.toLowerCase().includes(searchQuery.value.toLowerCase())) return false;
    if (filterStatus.value && unit.status !== filterStatus.value) return false;
    if (filterRooms.value && unit.rooms_count != filterRooms.value) return false;
    return true;
};


// Hover tooltip logic
const hoveredUnit = ref(null);
const mouseX = ref(0);
const mouseY = ref(0);

const onUnitHover = (unit, event) => {
    hoveredUnit.value = unit;
    mouseX.value = event.clientX;
    mouseY.value = event.clientY;
};

const onUnitLeave = () => {
    hoveredUnit.value = null;
};

// Floor Plan Maket Mode Logic
const showroomBlueprintSize = ref({ width: 1200, height: 800 });

const onBlueprintImageLoaded = (e) => {
    if (e.target && e.target.naturalWidth && e.target.naturalHeight) {
        showroomBlueprintSize.value = {
            width: e.target.naturalWidth,
            height: e.target.naturalHeight
        };
    }
};

const availableFloorsForBuilding = computed(() => {
    if (!selectedBuilding.value) return [];
    let list = [];
    if (selectedBuilding.value.floors && selectedBuilding.value.floors.length > 0) {
        list = [...selectedBuilding.value.floors];
    } else if (selectedBuilding.value.podezes) {
        selectedBuilding.value.podezes.forEach(p => {
            if (p.floors) list.push(...p.floors);
        });
    }
    return list.sort((a, b) => a.floor_number - b.floor_number);
});

const selectedFloorForPlanId = ref(null);
watch(availableFloorsForBuilding, (floors) => {
    if (floors.length > 0 && !floors.some(f => f.id === selectedFloorForPlanId.value)) {
        selectedFloorForPlanId.value = floors[0].id;
    }
}, { immediate: true });

const selectedFloorForPlan = computed(() => {
    return availableFloorsForBuilding.value.find(f => f.id === selectedFloorForPlanId.value) || null;
});

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
    if (unit.status === 'available') return 'rgba(16, 185, 129, 0.45)';
    if (unit.status === 'sold') return 'rgba(239, 68, 68, 0.45)';
    if (unit.status === 'booked' || unit.status === 'reserved') return 'rgba(245, 158, 11, 0.45)';
    return 'rgba(156, 163, 175, 0.4)';
};

const getSvgStrokeColor = (unit) => {
    if (unit.status === 'available') return '#10b981';
    if (unit.status === 'sold') return '#ef4444';
    if (unit.status === 'booked' || unit.status === 'reserved') return '#f59e0b';
    return '#9ca3af';
};
</script>

<template>
    <Head title="Shou room" />
    <component :is="currentLayout">
        <template #header>
            Shou room
        </template>

        <div class="h-full flex flex-col gap-6">
            <!-- Buildings Tabs & Filters (Object and Block Selector) -->
            <div class="flex flex-col md:flex-row gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100 w-full">
                <div class="flex flex-col md:flex-row gap-4 items-center w-full md:w-auto">
                    <div class="flex items-center gap-3">
                        <label class="text-sm font-bold text-gray-700 whitespace-nowrap">Obyekt tanlang:</label>
                        <select v-model="selectedObjectName" class="border-gray-200 rounded-lg text-sm font-bold text-gray-800 focus:ring-blue-500 focus:border-blue-500 w-full md:w-64">
                            <option v-for="name in uniqueObjectNames" :key="name" :value="name">
                                {{ name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3" v-if="selectedObjectName">
                        <label class="text-sm font-bold text-gray-700 whitespace-nowrap">Blok tanlang:</label>
                        <select v-model="selectedBuildingId" class="border-gray-200 rounded-lg text-sm font-bold text-gray-800 focus:ring-blue-500 focus:border-blue-500 w-full md:w-64">
                            <option v-for="building in availableBlocks" :key="building.id" :value="building.id">
                                {{ building.block_name || building.corpus_name || 'Asosiy Blok' }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div v-if="selectedBuilding" class="flex-1 flex flex-col min-h-0 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Modes Tabs -->
                <div class="flex items-center gap-6 px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <button
                        @click="activeTab = 'overview'"
                        class="text-[14px] font-bold px-4 py-2 rounded-lg transition-all"
                        :class="activeTab === 'overview' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-500 hover:bg-gray-200/50'"
                    >
                        Bino ma'lumotlari
                    </button>
                    <button
                        @click="activeTab = 'shaxmatka'"
                        class="text-[14px] font-bold px-4 py-2 rounded-lg transition-all"
                        :class="activeTab === 'shaxmatka' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-500 hover:bg-gray-200/50'"
                    >
                        Shaxmatka
                    </button>
                    <button
                        @click="activeTab = 'floor_plan'"
                        class="text-[14px] font-bold px-4 py-2 rounded-lg transition-all flex items-center gap-1.5"
                        :class="activeTab === 'floor_plan' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-500 hover:bg-gray-200/50'"
                    >
                        <span>📐</span> 3D / 2D Qavat Maketi
                    </button>
                </div>

                <!-- Content Area -->
                <div class="flex-1 overflow-auto p-6">
                    <!-- Overview Tab (Image 3) -->
                    <div v-if="activeTab === 'overview'" class="flex flex-col lg:flex-row gap-8">
                        <div class="flex-1 space-y-6">
                            <div class="relative w-full h-80 bg-gray-100 rounded-2xl overflow-hidden border border-gray-200">
                                <img v-if="selectedBuilding.photo_path" :src="'/storage/' + selectedBuilding.photo_path" class="w-full h-full object-cover" />
                                <img v-else-if="selectedBuilding.showroom_images && selectedBuilding.showroom_images.length > 0" :src="'/storage/' + selectedBuilding.showroom_images[0]" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-400 font-bold text-2xl">
                                    {{ selectedBuilding.name }}
                                </div>
                                <div class="absolute bottom-4 right-4 flex items-center gap-2">
                                    <button class="px-4 py-2 bg-white/90 backdrop-blur-sm text-gray-700 font-bold text-sm rounded-lg hover:bg-white shadow-sm transition-all">Tahrirlash</button>
                                    <button class="px-4 py-2 bg-red-500/90 backdrop-blur-sm text-white font-bold text-sm rounded-lg hover:bg-red-600 shadow-sm transition-all">O'chirish</button>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 rounded-xl p-6 border border-gray-100">
                                <h3 class="text-sm font-extrabold text-gray-800 uppercase tracking-widest mb-4">Bino haqidagi ma'lumot</h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div>
                                        <div class="text-xs text-gray-500 font-bold mb-1">Qavatlar</div>
                                        <div class="text-lg font-extrabold text-gray-900">{{ selectedBuilding.floors_count || selectedBuilding.floors?.length || 0 }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 font-bold mb-1">Podezdlar</div>
                                        <div class="text-lg font-extrabold text-gray-900">{{ selectedBuilding.podezes?.length || 0 }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 font-bold mb-1">Kvartiralar</div>
                                        <div class="text-lg font-extrabold text-gray-900">{{ selectedBuilding.units?.length || 0 }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 font-bold mb-1">Yerto'lalar</div>
                                        <div class="text-lg font-extrabold text-gray-900">0</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="w-full lg:w-96 flex flex-col">
                            <h3 class="text-sm font-extrabold text-gray-800 uppercase tracking-widest mb-4">2D Chizma</h3>
                            <div class="flex-1 bg-gray-100 rounded-2xl border border-gray-200 overflow-hidden flex items-center justify-center min-h-[300px]">
                                <img v-if="selectedBuilding.blueprint_path" :src="'/storage/' + selectedBuilding.blueprint_path" class="w-full h-full object-contain p-4" />
                                <img v-else-if="selectedBuilding.plan_image" :src="'/storage/' + selectedBuilding.plan_image" class="w-full h-full object-contain p-4" />
                                <div v-else class="text-gray-400 font-bold">Chizma yuklanmagan</div>
                            </div>
                        </div>
                    </div>

                    <!-- Shaxmatka Tab (Advanced) -->
                    <div v-else-if="activeTab === 'shaxmatka'" class="flex h-full gap-6">
                        <div class="flex-1 flex flex-col overflow-hidden">
                            
                            <!-- Legend -->
                            <div class="flex items-center gap-6 mb-6">
                                <div class="flex items-center gap-2">
                                    <span class="w-4 h-4 rounded bg-emerald-500 block shadow-sm"></span>
                                    <span class="text-sm font-bold text-gray-700">Sotuvda</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-4 h-4 rounded bg-red-500 block shadow-sm"></span>
                                    <span class="text-sm font-bold text-gray-700">Sotilgan</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-4 h-4 rounded bg-amber-500 block shadow-sm"></span>
                                    <span class="text-sm font-bold text-gray-700">Bron qilingan</span>
                                </div>
                            </div>

                            <!-- Filters Area -->
                            <div class="flex flex-wrap gap-4 mb-6 bg-white p-4 rounded-xl border border-gray-200">
                                <div class="flex-1 min-w-[200px]">
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Qidiruv</label>
                                    <input type="text" v-model="searchQuery" placeholder="Xonadon raqami..." class="w-full border-gray-200 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div class="w-48">
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Holati</label>
                                    <select v-model="filterStatus" class="w-full border-gray-200 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Barchasi</option>
                                        <option value="available">Sotuvda</option>
                                        <option value="sold">Sotilgan</option>
                                        <option value="booked">Bron qilingan</option>
                                    </select>
                                </div>
                                <div class="w-48">
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Xonalar soni</label>
                                    <select v-model="filterRooms" class="w-full border-gray-200 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Barchasi</option>
                                        <option value="1">1 xona</option>
                                        <option value="2">2 xona</option>
                                        <option value="3">3 xona</option>
                                        <option value="4">4 xona</option>
                                        <option value="5">5+ xona</option>
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <button @click="searchQuery = ''; filterStatus = ''; filterRooms = ''" class="px-4 py-2 bg-gray-100 text-gray-600 hover:bg-gray-200 font-bold text-sm rounded-lg transition-colors">
                                        Tozalash
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 overflow-auto bg-gray-50 border border-gray-200 rounded-2xl p-6">
                                <div v-if="selectedBuilding.floors?.length || selectedBuilding.podezes?.length" class="flex flex-row flex-wrap gap-8 w-full p-1">
                                    <div v-for="p in podyezdsToShow" :key="'podyezd-'+p.id" class="flex flex-col flex-1 min-w-[320px] pb-4">
                                        <h4 class="text-sm font-bold text-gray-800 font-sans mb-6 text-center border-b border-gray-200 pb-2">
                                            {{ p.podez_name }}
                                        </h4>
                                        <div class="space-y-4">
                                            <div v-for="floor in getSortedFloorsForPodez(p)" :key="'floor-'+p.id+'-'+floor.id" class="flex items-center gap-4">
                                                <div class="w-24 shrink-0 text-right">
                                                    <span class="text-[11px] font-sans font-bold text-blue-600 uppercase tracking-wider">
                                                        {{ floor.floor_number < 0 ? `${floor.floor_number} YERTO'LA` : `${floor.floor_number}-QAVAT` }}
                                                    </span>
                                                </div>

                                                <div class="flex gap-2 items-center flex-wrap flex-1">
                                                    <div v-for="unit in getUnitsByFloorAndPodyezd(floor, p)" :key="'unit-'+unit.id"
                                                         class="w-12 h-12 flex items-center justify-center cursor-pointer rounded-xl shadow-sm transition-all duration-300 hover:-translate-y-1"
                                                         :class="[
                                                            getUnitColor(unit), 
                                                            selectedUnit?.id === unit.id ? 'ring-4 ring-blue-500 ring-offset-2 scale-110 z-10' : 'hover:scale-105',
                                                            !matchesFilter(unit) ? 'opacity-20 grayscale pointer-events-none' : ''
                                                         ]"
                                                         @mousemove="(e) => onUnitHover(unit, e)"
                                                         @mouseleave="onUnitLeave"
                                                         @click="selectedUnit = unit">
                                                        <span class="font-sans text-sm font-bold pointer-events-none z-10 text-white">
                                                            {{ unit.unit_number }}
                                                        </span>
                                                    </div>
                                                    <div v-if="!getUnitsByFloorAndPodyezd(floor, p).length" class="text-xs font-sans text-gray-400 italic">
                                                        Xonadonlar yo'q
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-10 text-gray-500 font-bold">
                                    Bu binoda xonadonlar mavjud emas.
                                </div>
                            </div>
                        </div>

                        <!-- Unit Details Sidebar -->
                        <div class="w-80 bg-gray-50 border border-gray-200 rounded-2xl overflow-hidden flex flex-col" v-if="selectedUnit">
                            <div class="h-48 bg-gray-200 relative">
                                <img v-if="selectedUnit.images && selectedUnit.images.length > 0" :src="'/storage/' + selectedUnit.images[0]" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-400 font-bold">Rasm yo'q</div>
                                <div class="absolute top-4 left-4 px-3 py-1 bg-white/90 backdrop-blur-sm rounded text-xs font-bold text-gray-800 shadow-sm">
                                    № {{ selectedUnit.unit_number }}
                                </div>
                            </div>
                            <div class="p-6 space-y-4 flex-1 flex flex-col">
                                <h3 class="text-sm font-extrabold text-gray-800 uppercase tracking-widest border-b border-gray-200 pb-2">Uy haqida ma'lumot</h3>
                                
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-bold">Holati</span>
                                    <span class="px-2 py-1 rounded text-xs font-bold uppercase" :class="getUnitColor(selectedUnit)">{{ selectedUnit.status }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-bold">Qavat</span>
                                    <span class="font-bold text-gray-800">{{ selectedBuilding.floors.find(f => f.id === selectedUnit.floor_id)?.floor_number }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-bold">Xonalar</span>
                                    <span class="font-bold text-gray-800">{{ selectedUnit.rooms_count }} xona</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-bold">Maydoni</span>
                                    <span class="font-bold text-gray-800">{{ selectedUnit.area_sqm }} m²</span>
                                </div>
                                <div class="flex justify-between items-center text-sm pt-2 border-t border-gray-200">
                                    <span class="text-gray-800 font-extrabold">Umumiy narxi</span>
                                    <span class="font-extrabold text-blue-600 text-base">{{ selectedUnit.price?.toLocaleString() }} UZS</span>
                                </div>
                                <div class="mt-auto pt-4">
                                    <Link v-if="selectedUnit.status === 'available'" :href="route('admin.crm', { unit_id: selectedUnit.id })" class="block w-full text-center px-4 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-sm">
                                        Sotish (Shartnoma tuzish)
                                    </Link>
                                    <div v-else class="text-center px-4 py-3 bg-gray-100 text-gray-400 font-bold rounded-xl cursor-not-allowed">
                                        Bu xonadon sotuvda emas
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="w-80 border-2 border-dashed border-gray-200 rounded-2xl flex items-center justify-center text-gray-400 font-bold text-sm text-center p-6 bg-gray-50">
                            Kvartira haqida ma'lumot ko'rish uchun shaxmatkadan tanlang
                        </div>
                    </div>

                    <!-- Floor Plan (3D/2D Maket) Tab -->
                    <div v-else-if="activeTab === 'floor_plan'" class="flex h-full gap-6">
                        <div class="flex-1 flex flex-col overflow-hidden">
                            <!-- Floor Switcher & Controls -->
                            <div class="flex flex-wrap items-center justify-between gap-4 mb-4 bg-gray-50 p-3.5 rounded-xl border border-gray-200">
                                <div class="flex items-center gap-2 overflow-x-auto pb-1 max-w-full">
                                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Qavat:</span>
                                    <button 
                                        v-for="floor in availableFloorsForBuilding" 
                                        :key="'fl-btn-' + floor.id"
                                        @click="selectedFloorForPlanId = floor.id"
                                        :class="selectedFloorForPlanId === floor.id ? 'bg-blue-600 text-white font-bold shadow-sm' : 'bg-white text-gray-700 hover:bg-gray-200 border border-gray-200'"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all shrink-0"
                                    >
                                        {{ floor.floor_number < 0 ? `${floor.floor_number} yerto'la` : `${floor.floor_number}-qavat` }}
                                    </button>
                                </div>

                                <div class="flex items-center gap-4">
                                    <!-- Legend -->
                                    <div class="flex items-center gap-4 text-xs font-bold">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-3 h-3 rounded-full bg-emerald-500 block"></span>
                                            <span class="text-gray-700">Sotuvda</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-3 h-3 rounded-full bg-amber-500 block"></span>
                                            <span class="text-gray-700">Band</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-3 h-3 rounded-full bg-red-500 block"></span>
                                            <span class="text-gray-700">Sotilgan</span>
                                        </div>
                                    </div>

                                    <Link :href="route('admin.facility.floor_plan_editor', { building_id: selectedBuilding.id, floor_id: selectedFloorForPlanId })" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-bold rounded-lg border border-blue-200 transition-all flex items-center gap-1">
                                        <span>✏️</span> Maketni Tahrirlash
                                    </Link>
                                </div>
                            </div>

                            <!-- Interactive Floor Plan SVG Stage -->
                            <div class="flex-1 bg-slate-900 rounded-2xl border border-slate-700 overflow-auto relative flex items-center justify-center min-h-[480px] p-4">
                                <div class="relative inline-block max-w-full max-h-full" v-if="selectedFloorForPlan?.blueprint_path || selectedBuilding.blueprint_path">
                                    <img 
                                        :src="'/storage/' + (selectedFloorForPlan?.blueprint_path || selectedBuilding.blueprint_path)" 
                                        @load="onBlueprintImageLoaded"
                                        class="block max-w-full max-h-[75vh] w-auto h-auto object-contain pointer-events-none select-none opacity-95 rounded-lg shadow-2xl"
                                    />

                                    <!-- SVG Interactive Unit Overlays matching natural image coordinates -->
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
                                                   @mousemove="(e) => onUnitHover(unit, e)"
                                                   @mouseleave="onUnitLeave"
                                                   @click="selectedUnit = unit"
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
                                                   @mousemove="(e) => onUnitHover(unit, e)"
                                                   @mouseleave="onUnitLeave"
                                                   @click="selectedUnit = unit"
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
                        </div>

                        <!-- Unit Details Sidebar -->
                        <div class="w-80 bg-gray-50 border border-gray-200 rounded-2xl overflow-hidden flex flex-col" v-if="selectedUnit">
                            <div class="h-44 bg-gray-200 relative">
                                <img v-if="selectedUnit.images && selectedUnit.images.length > 0" :src="'/storage/' + selectedUnit.images[0]" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-400 font-bold">Rasm yo'q</div>
                                <div class="absolute top-4 left-4 px-3 py-1 bg-white/90 backdrop-blur-sm rounded text-xs font-bold text-gray-800 shadow-sm">
                                    № {{ selectedUnit.unit_number }}
                                </div>
                            </div>
                            <div class="p-5 space-y-3.5 flex-1 flex flex-col">
                                <h3 class="text-sm font-extrabold text-gray-800 uppercase tracking-widest border-b border-gray-200 pb-2">Uy haqida ma'lumot</h3>
                                
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-bold">Holati</span>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase" :class="getUnitColor(selectedUnit)">{{ selectedUnit.status }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-bold">Qavat</span>
                                    <span class="font-bold text-gray-800">{{ selectedBuilding.floors.find(f => f.id === selectedUnit.floor_id)?.floor_number }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-bold">Xonalar</span>
                                    <span class="font-bold text-gray-800">{{ selectedUnit.rooms_count }} xona</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-bold">Maydoni</span>
                                    <span class="font-bold text-gray-800">{{ selectedUnit.area_sqm }} m²</span>
                                </div>
                                <div class="flex justify-between items-center text-sm pt-2 border-t border-gray-200">
                                    <span class="text-gray-800 font-extrabold">Umumiy narxi</span>
                                    <span class="font-extrabold text-blue-600 text-base">{{ selectedUnit.price?.toLocaleString() }} UZS</span>
                                </div>
                                <div class="mt-auto pt-3">
                                    <Link v-if="selectedUnit.status === 'available'" :href="route('admin.crm', { unit_id: selectedUnit.id })" class="block w-full text-center px-4 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-sm">
                                        Sotish (Shartnoma tuzish)
                                    </Link>
                                    <div v-else class="text-center px-4 py-3 bg-gray-100 text-gray-400 font-bold rounded-xl cursor-not-allowed">
                                        Bu xonadon sotuvda emas
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="w-80 border-2 border-dashed border-gray-200 rounded-2xl flex items-center justify-center text-gray-400 font-bold text-sm text-center p-6 bg-gray-50">
                            Xonadon haqida to'liq ma'lumot olish uchun qavat chizmasidan xonadonni bosing
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="flex-1 flex items-center justify-center text-gray-500 font-bold bg-white rounded-2xl border border-gray-100">
                Binolar topilmadi
            </div>
        </div>

        <!-- Unit Tooltip -->
        <Teleport to="body">
            <div v-if="hoveredUnit" 
                 class="fixed z-50 pointer-events-none bg-white border border-gray-200 p-4 rounded-xl shadow-lg"
                 :style="{ top: (mouseY + 15) + 'px', left: (mouseX + 15) + 'px' }">
                <h4 class="text-blue-600 font-display text-lg mb-2 flex items-center gap-2 font-sans font-bold">
                    {{ hoveredUnit.unit_number }} 
                    <span class="text-[10px] font-sans px-2 py-0.5 rounded text-gray-900 bg-gray-100">{{ hoveredUnit.type === 'commercial' ? "Do'kon" : 'Xonadon' }}</span>
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-xs font-sans">
                    <span class="text-gray-500">Maydoni:</span>
                    <span class="text-gray-900 text-right font-bold">{{ hoveredUnit.area_sqm }} m²</span>
                    
                    <span class="text-gray-500">Xonalar:</span>
                    <span class="text-gray-900 text-right font-bold">{{ hoveredUnit.rooms_count || 1 }} xona</span>
                    
                    <span class="text-gray-500">Narxi:</span>
                    <span class="text-emerald-600 text-right font-bold">{{ formatSoM(hoveredUnit.price) }}</span>
                    
                    <span class="text-gray-500">Status:</span>
                    <span class="text-right font-bold uppercase" :class="getUnitColor(hoveredUnit).replace('bg-', 'text-').replace('text-white', '')">{{ hoveredUnit.status }}</span>
                </div>
            </div>
        </Teleport>
    </component>
</template>
