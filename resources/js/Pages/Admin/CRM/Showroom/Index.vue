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
    if (unit.status === 'available') return 'bg-green-500 text-white';
    if (unit.status === 'sold') return 'bg-red-500 text-white';
    if (unit.status === 'booked') return 'bg-yellow-500 text-white';
    return 'bg-gray-300 text-gray-700';
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

                    <!-- Shaxmatka Tab (Image 4) -->
                    <div v-else-if="activeTab === 'shaxmatka'" class="flex h-full gap-6">
                        <div class="flex-1 flex flex-col gap-6 overflow-hidden">
                            <div class="flex items-center gap-4 text-xs font-bold uppercase tracking-wider text-gray-500">
                                <div class="flex items-center gap-2">
                                    <div class="w-4 h-4 rounded bg-green-500"></div> Sotuvda
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-4 h-4 rounded bg-red-500"></div> Sotilgan
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-4 h-4 rounded bg-yellow-500"></div> Bron qilingan
                                </div>
                            </div>

                            <div class="flex-1 overflow-auto border border-gray-200 rounded-2xl bg-gray-50 p-6 flex flex-col-reverse gap-4">
                                <!-- Floors and Units -->
                                <div v-for="floor in selectedBuilding.floors" :key="floor.id" class="flex gap-4 items-center">
                                    <div class="w-24 text-sm font-bold text-gray-600 text-right">{{ floor.name || floor.number + '-QAVAT' }}</div>
                                    <div class="flex gap-2 flex-wrap flex-1">
                                        <button
                                            v-for="unit in floor.units"
                                            :key="unit.id"
                                            @click="selectedUnit = unit"
                                            class="w-12 h-12 flex items-center justify-center rounded-xl font-bold text-sm shadow-sm transition-transform hover:scale-105"
                                            :class="[getUnitColor(unit), selectedUnit?.id === unit.id ? 'ring-4 ring-blue-500 ring-offset-2' : '']"
                                        >
                                            {{ unit.number }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Unit Details Sidebar -->
                        <div class="w-80 bg-gray-50 border border-gray-200 rounded-2xl overflow-hidden flex flex-col" v-if="selectedUnit">
                            <div class="h-48 bg-gray-200 relative">
                                <img v-if="selectedUnit.images && selectedUnit.images.length > 0" :src="'/storage/' + selectedUnit.images[0]" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-400 font-bold">Rasm yo'q</div>
                                <div class="absolute top-4 left-4 px-3 py-1 bg-white/90 backdrop-blur-sm rounded text-xs font-bold text-gray-800 shadow-sm">
                                    № {{ selectedUnit.number }}
                                </div>
                            </div>
                            <div class="p-6 space-y-4">
                                <h3 class="text-sm font-extrabold text-gray-800 uppercase tracking-widest border-b border-gray-200 pb-2">Uy haqida ma'lumot</h3>
                                
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-bold">Holati</span>
                                    <span class="px-2 py-1 rounded text-xs font-bold uppercase" :class="getUnitColor(selectedUnit)">{{ selectedUnit.status }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-bold">Qavat</span>
                                    <span class="font-bold text-gray-800">{{ selectedBuilding.floors.find(f => f.id === selectedUnit.floor_id)?.number }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-bold">Xonalar</span>
                                    <span class="font-bold text-gray-800">{{ selectedUnit.rooms }} xona</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-bold">Maydoni</span>
                                    <span class="font-bold text-gray-800">{{ selectedUnit.area }} m²</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 font-bold">Narxi (1m²)</span>
                                    <span class="font-bold text-gray-800">{{ selectedUnit.price_per_m2?.toLocaleString() }} UZS</span>
                                </div>
                                <div class="flex justify-between items-center text-sm pt-2 border-t border-gray-200">
                                    <span class="text-gray-800 font-extrabold">Umumiy narxi</span>
                                    <span class="font-extrabold text-blue-600 text-base">{{ selectedUnit.total_price?.toLocaleString() }} UZS</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="w-80 border-2 border-dashed border-gray-200 rounded-2xl flex items-center justify-center text-gray-400 font-bold text-sm text-center p-6 bg-gray-50">
                            Kvartira haqida ma'lumot ko'rish uchun shaxmatkadan tanlang
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="flex-1 flex items-center justify-center text-gray-500 font-bold bg-white rounded-2xl border border-gray-100">
                Binolar topilmadi
            </div>
        </div>
    </component>
</template>
