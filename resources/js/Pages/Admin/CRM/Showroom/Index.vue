<script setup>
import { Head, usePage, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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

const selectedBuildingId = ref(props.buildings?.length > 0 ? props.buildings[0].id : null);
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
</script>

<template>
    <Head title="Shou room" />
    <component :is="currentLayout">
        <template #header>
            Shou room
        </template>

        <div class="h-full flex flex-col gap-6">
            <!-- Buildings Tabs -->
            <div class="flex items-center gap-4 border-b border-gray-200 pb-2 overflow-x-auto">
                <button
                    v-for="building in buildings"
                    :key="building.id"
                    @click="selectBuilding(building.id)"
                    class="px-4 py-2 font-bold transition-colors whitespace-nowrap"
                    :class="selectedBuildingId === building.id ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'"
                >
                    {{ building.name }}
                </button>
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
                </div>

                <!-- Content Area -->
                <div class="flex-1 overflow-auto p-6">
                    <!-- Overview Tab (Image 3) -->
                    <div v-if="activeTab === 'overview'" class="flex flex-col lg:flex-row gap-8">
                        <div class="flex-1 space-y-6">
                            <div class="relative w-full h-80 bg-gray-100 rounded-2xl overflow-hidden border border-gray-200">
                                <img v-if="selectedBuilding.images && selectedBuilding.images.length > 0" :src="'/storage/' + selectedBuilding.images[0]" class="w-full h-full object-cover" />
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
                                <img v-if="selectedBuilding.plan_image" :src="'/storage/' + selectedBuilding.plan_image" class="w-full h-full object-contain p-4" />
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
                                                         :class="[getUnitColor(unit), selectedUnit?.id === unit.id ? 'ring-4 ring-blue-500 ring-offset-2 scale-110 z-10' : 'hover:scale-105']"
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
