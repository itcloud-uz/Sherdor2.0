<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import Modal from '@/Components/Modal.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const props = defineProps({
    buildings: Array,
    clients: Array,
    partners: Array
});

const selectedBuilding = ref(props.buildings.length > 0 ? props.buildings[0] : null);
const selectedFloor = ref(selectedBuilding.value?.floors?.length > 0 ? selectedBuilding.value.floors[0] : null);
const activeTab = ref('blueprint');

const hoveredUnit = ref(null);
const mouseX = ref(0);
const mouseY = ref(0);

function onUnitHover(unit, event) {
    hoveredUnit.value = unit;
    mouseX.value = event.clientX;
    mouseY.value = event.clientY;
}

function onUnitLeave() {
    hoveredUnit.value = null;
}

// Watch for building changes to select the first floor
import { watch } from 'vue';
watch(selectedBuilding, (newBuilding) => {
    if (newBuilding && newBuilding.floors && newBuilding.floors.length > 0) {
        selectedFloor.value = newBuilding.floors[0];
    } else {
        selectedFloor.value = null;
    }
});

const showSellUnitModal = ref(false);
const isProcessingContract = ref(false);
const currentSellingUnit = ref(null);
const contractForm = ref({
    buyer_type: 'client',
    partner_id: '',
    client_id: '',
    unit_id: '',
    contract_number: '',
    contract_type: 'residential',
    price_per_sqm: '',
    total_amount: '',
    down_payment: '',
    term_months: 36,
    payment_type: 'installment',
    created_at: '',
    actual_paid_amount: '',
    payment_method: 'bank',
    floor: '',
    rooms: ''
});

function openSellUnit(unit) {
    currentSellingUnit.value = unit;
    showAddUnitModal.value = false;
    
    contractForm.value = {
        buyer_type: 'client',
        partner_id: '',
        client_id: '',
        unit_id: unit.id,
        contract_number: '',
        contract_type: unit.type === 'commercial' ? 'commercial' : 'residential',
        price_per_sqm: '',
        total_amount: unit.price || '',
        down_payment: '',
        term_months: 36,
        payment_type: 'installment',
        created_at: '',
        actual_paid_amount: '',
        payment_method: 'bank',
        floor: selectedFloor.value?.floor_number || '',
        rooms: unit.rooms_count || ''
    };
    
    if (unit.price && unit.area_sqm) {
        contractForm.value.price_per_sqm = (Number(unit.price) / Number(unit.area_sqm)).toFixed(2);
    }
    
    showSellUnitModal.value = true;
}

watch(() => contractForm.value.total_amount, (newTotal) => {
    if (currentSellingUnit.value) {
        const area = Number(currentSellingUnit.value.area_sqm) || 0;
        contractForm.value.price_per_sqm = area > 0 ? (Number(newTotal) / area).toFixed(2) : 0;
        
        if (contractForm.value.payment_type === 'full') {
            contractForm.value.down_payment = newTotal;
            contractForm.value.term_months = 1;
        }
    }
});

watch(() => contractForm.value.payment_type, (newType) => {
    if (newType === 'full') {
        contractForm.value.down_payment = contractForm.value.total_amount;
        contractForm.value.term_months = 1;
    } else {
        contractForm.value.down_payment = '';
        contractForm.value.term_months = 36;
    }
});

function submitContract() {
    isProcessingContract.value = true;
    
    // Clear the opposite ID depending on buyer_type
    if (contractForm.value.buyer_type === 'client') {
        contractForm.value.partner_id = null;
    } else {
        contractForm.value.client_id = null;
    }

    router.post(route('admin.contracts.store'), contractForm.value, {
        onSuccess: () => {
            showSellUnitModal.value = false;
            isProcessingContract.value = false;
            // The server will have updated the unit status, so reload or wait for Inertia.
        },
        onError: (errors) => {
            isProcessingContract.value = false;
            alert("Xatolik yuz berdi: " + Object.values(errors).join("\n"));
        }
    });
}

const showAddBuildingModal = ref(false);
const showAddStageModal = ref(false);
const showAddFloorModal = ref(false);
const showAddUnitModal = ref(false);

const buildingForm = useForm({
    name: '',
    city: '',
    district: '',
    mahalla: '',
    street: '',
    total_floors: 1,
    blocks_count: 1,
    entrances_count: 1,
    total_apartments: 0,
    total_shops: 0,
    estimated_budget: 0,
    blueprint: null,
    // Detailed Attributes
    block_name: '',
    corpus_name: '',
    length_m: '',
    width_m: '',
    height_m: '',
    // Auto-generation fields
    units_per_floor: 0,
    average_area: 0,
    average_price: 0,
    commercial_units_first_floor: 0,
});

const stageForm = useForm({
    building_id: null,
    name: '',
    start_date: '',
    end_date: '',
});

const floorForm = useForm({
    building_id: null,
    floor_number: '',
    total_units: 0,
    total_commercial_units: 0,
    blueprint: null,
});

const unitForm = useForm({
    floor_id: null,
    unit_number: '',
    type: 'residential',
    area_sqm: '',
    price: '',
    status: 'available',
    svg_polygon: '',
    // Detailed Attributes
    rooms_count: 1,
    kitchens_count: 1,
    bathrooms_count: 1,
    toilets_count: 1,
    orientation: ''
});

const isEditingBuilding = ref(false);

function openEditBuilding() {
    if (!selectedBuilding.value) return;
    isEditingBuilding.value = true;
    buildingForm.name = selectedBuilding.value.name;
    buildingForm.city = selectedBuilding.value.city || '';
    buildingForm.district = selectedBuilding.value.district || '';
    buildingForm.mahalla = selectedBuilding.value.mahalla || '';
    buildingForm.street = selectedBuilding.value.street || '';
    buildingForm.total_floors = selectedBuilding.value.total_floors;
    buildingForm.blocks_count = selectedBuilding.value.blocks_count;
    buildingForm.entrances_count = selectedBuilding.value.entrances_count;
    buildingForm.total_shops = selectedBuilding.value.total_shops;
    buildingForm.estimated_budget = selectedBuilding.value.estimated_budget;
    buildingForm.blueprint = null;
    buildingForm.block_name = selectedBuilding.value.block_name || '';
    buildingForm.corpus_name = selectedBuilding.value.corpus_name || '';
    buildingForm.length_m = selectedBuilding.value.length_m || '';
    buildingForm.width_m = selectedBuilding.value.width_m || '';
    buildingForm.height_m = selectedBuilding.value.height_m || '';
    buildingForm.units_per_floor = 0;
    buildingForm.average_area = 0;
    buildingForm.average_price = 0;
    buildingForm.commercial_units_first_floor = 0;
    showAddBuildingModal.value = true;
}

function deleteBuilding() {
    if (confirm("Rostdan ham bu binoni o'chirmoqchimisiz? Ushbu amaliyot barcha qavatlar va xonadonlarni o'chirib yuboradi!")) {
        router.delete(route('admin.facility.buildings.destroy', selectedBuilding.value.id), {
            onSuccess: () => {
                selectedBuilding.value = props.buildings.length > 0 ? props.buildings[0] : null;
            }
        });
    }
}

function submitBuilding() {
    if (isEditingBuilding.value && selectedBuilding.value) {
        // use _method: 'put' for file upload with PUT in laravel
        buildingForm.transform((data) => ({
            ...data,
            _method: 'put'
        })).post(route('admin.facility.buildings.update', selectedBuilding.value.id), {
            onSuccess: () => {
                showAddBuildingModal.value = false;
                isEditingBuilding.value = false;
                buildingForm.reset();
            }
        });
    } else {
        buildingForm.transform((data) => data).post(route('admin.facility.buildings.store'), {
            onSuccess: () => {
                showAddBuildingModal.value = false;
                buildingForm.reset();
                if (props.buildings.length === 1) {
                    selectedBuilding.value = props.buildings[0];
                }
            }
        });
    }
}

function submitStage() {
    stageForm.building_id = selectedBuilding.value?.id;
    stageForm.post(route('admin.facility.stages.store'), {
        onSuccess: () => {
            showAddStageModal.value = false;
            stageForm.reset();
        }
    });
}

const isEditingFloor = ref(false);
const editingFloorId = ref(null);

function openEditFloor(floor) {
    isEditingFloor.value = true;
    editingFloorId.value = floor.id;
    floorForm.floor_number = floor.floor_number;
    floorForm.total_units = floor.total_units;
    floorForm.total_commercial_units = floor.total_commercial_units || 0;
    floorForm.blueprint = null;
    showAddFloorModal.value = true;
}

function submitFloor() {
    if (isEditingFloor.value && editingFloorId.value) {
        floorForm.transform((data) => ({
            ...data,
            _method: 'put'
        })).post(route('admin.facility.floors.update', editingFloorId.value), {
            onSuccess: () => {
                showAddFloorModal.value = false;
                isEditingFloor.value = false;
                editingFloorId.value = null;
                floorForm.reset();
            }
        });
    } else {
        floorForm.building_id = selectedBuilding.value?.id;
        floorForm.transform((data) => data).post(route('admin.facility.floors.store'), {
            onSuccess: () => {
                showAddFloorModal.value = false;
                floorForm.reset();
                if (selectedBuilding.value && selectedBuilding.value.floors.length > 0) {
                    setTimeout(() => {
                        selectedFloor.value = selectedBuilding.value.floors[selectedBuilding.value.floors.length - 1];
                    }, 100);
                }
            }
        });
    }
}

function deleteFloor(floorId) {
    if (confirm("Rostdan ham bu qavatni o'chirmoqchimisiz? O'chirsangiz barcha biriktirilgan xonadonlar ham o'chib ketadi!")) {
        router.delete(route('admin.facility.floors.destroy', floorId), {
            onSuccess: () => {
                if (selectedFloor.value?.id === floorId) {
                    selectedFloor.value = selectedBuilding.value?.floors?.length > 0 ? selectedBuilding.value.floors[0] : null;
                }
            }
        });
    }
}

const isEditingUnit = ref(false);
const editingUnitId = ref(null);
const showBlueprintModal = ref(false);

function openEditUnit(unit) {
    isEditingUnit.value = true;
    editingUnitId.value = unit.id;
    unitForm.unit_number = unit.unit_number;
    unitForm.type = unit.type;
    unitForm.area_sqm = unit.area_sqm;
    unitForm.price = unit.price;
    unitForm.status = unit.status;
    unitForm.svg_polygon = unit.svg_polygon || '';
    unitForm.rooms_count = unit.rooms_count || 1;
    unitForm.kitchens_count = unit.kitchens_count || 1;
    unitForm.bathrooms_count = unit.bathrooms_count || 1;
    unitForm.toilets_count = unit.toilets_count || 1;
    unitForm.orientation = unit.orientation || '';
    showAddUnitModal.value = true;
}

function submitUnit() {
    if (isEditingUnit.value && editingUnitId.value) {
        unitForm.transform((data) => ({
            ...data,
            _method: 'put'
        })).post(route('admin.facility.units.update', editingUnitId.value), {
            onSuccess: () => {
                showAddUnitModal.value = false;
                isEditingUnit.value = false;
                editingUnitId.value = null;
                unitForm.reset();
            }
        });
    } else {
        unitForm.floor_id = selectedFloor.value?.id;
        unitForm.transform((data) => data).post(route('admin.facility.units.store'), {
            onSuccess: () => {
                showAddUnitModal.value = false;
                unitForm.reset();
            }
        });
    }
}

function deleteUnit(unitId) {
    if (confirm("Rostdan ham bu xonadon/do'konni o'chirmoqchimisiz?")) {
        router.delete(route('admin.facility.units.destroy', unitId), {
            onSuccess: () => {
                showAddUnitModal.value = false;
                isEditingUnit.value = false;
                editingUnitId.value = null;
                unitForm.reset();
            }
        });
    }
}

const residentialUnits = computed(() => selectedFloor.value?.units?.filter(u => u.type === 'residential') || []);
const commercialUnits = computed(() => selectedFloor.value?.units?.filter(u => u.type === 'commercial') || []);

function getFallbackPolygon(unit) {
    const typeArray = unit.type === 'commercial' ? commercialUnits.value : residentialUnits.value;
    const typeIdx = Math.max(0, typeArray.findIndex(u => u.id === unit.id));
    const col = typeIdx % 7;
    const row = Math.floor(typeIdx / 7);
    const baseY = unit.type === 'commercial' ? 320 : 60;
    
    const xStart = 50 + (col * 130);
    const xEnd = 150 + (col * 130);
    const yStart = baseY + (row * 110);
    const yEnd = yStart + 90;
    return `${xStart},${yStart} ${xEnd},${yStart} ${xEnd},${yEnd} ${xStart},${yEnd}`;
}

function getFallbackTextX(unit) {
    const typeArray = unit.type === 'commercial' ? commercialUnits.value : residentialUnits.value;
    const typeIdx = Math.max(0, typeArray.findIndex(u => u.id === unit.id));
    const col = typeIdx % 7;
    return 100 + (col * 130);
}

function getFallbackTextY(unit) {
    const typeArray = unit.type === 'commercial' ? commercialUnits.value : residentialUnits.value;
    const typeIdx = Math.max(0, typeArray.findIndex(u => u.id === unit.id));
    const row = Math.floor(typeIdx / 7);
    const baseY = unit.type === 'commercial' ? 320 : 60;
    return baseY + 50 + (row * 110);
}

function getUnitBorderColor(status) {
    if (status === 'available') return 'border-neon-green shadow-[0_0_10px_rgba(57,255,20,0.2)]';
    if (status === 'reserved') return 'border-neon-yellow shadow-[0_0_10px_rgba(252,238,9,0.2)]';
    if (status === 'sold') return 'border-neon-magenta shadow-[0_0_10px_rgba(255,0,60,0.2)]';
    return 'border-white/10';
}

function getUnitFillColor(status) {
    if (status === 'available') return 'rgba(57, 255, 20, 0.3)';
    if (status === 'reserved') return 'rgba(252, 238, 9, 0.3)';
    if (status === 'sold') return 'rgba(255, 0, 60, 0.3)';
    return 'rgba(255, 255, 255, 0.1)';
}

function getUnitStrokeColor(status) {
    if (status === 'available') return '#39FF14';
    if (status === 'reserved') return '#FCEE09';
    if (status === 'sold') return '#FF003C';
    return '#ffffff';
}

function getUnitTextColor(status) {
    if (status === 'available') return 'text-neon-green';
    if (status === 'reserved') return 'text-neon-yellow';
    if (status === 'sold') return 'text-neon-magenta';
    return 'text-white';
}

// Chart Options for Analytics
const chartOptions = computed(() => {
    return {
        chart: { type: 'donut', background: 'transparent', foreColor: '#fff' },
        theme: { mode: 'dark' },
        labels: ['Available', 'Reserved', 'Sold'],
        colors: ['#39FF14', '#FCEE09', '#FF003C'],
        stroke: { show: true, colors: '#000000', width: 2 },
        dataLabels: { enabled: true, style: { fontFamily: 'monospace' } },
        legend: { position: 'bottom', fontFamily: 'monospace' }
    };
});

const chartSeries = computed(() => {
    if (!selectedBuilding.value) return [0, 0, 0];
    let available = 0;
    let reserved = 0;
    let sold = 0;
    
    selectedBuilding.value.floors.forEach(floor => {
        floor.units.forEach(unit => {
            if (unit.status === 'available') available++;
            if (unit.status === 'reserved') reserved++;
            if (unit.status === 'sold') sold++;
        });
    });
    
    return [available, reserved, sold];
});

const progressOptions = computed(() => {
    return {
        chart: { type: 'bar', height: 300, background: 'transparent', foreColor: '#fff', toolbar: { show: false } },
        theme: { mode: 'dark' },
        colors: ['#00F0FF'],
        plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
        dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'], fontFamily: 'monospace' }, formatter: function (val, opt) { return val + "%" }, offsetX: 0 },
        xaxis: { categories: selectedBuilding.value?.stages?.map(s => s.name) || [], max: 100 }
    };
});

const progressSeries = computed(() => {
    return [{
        name: 'Progress',
        data: selectedBuilding.value?.stages?.map(s => s.progress_percentage) || []
    }];
});
</script>

<template>
    <Head :title="$t('facility_command_center') || 'Facility Command Center'" />
    <AdminLayout>
        <div class="flex flex-col lg:flex-row gap-4 lg:gap-6 h-auto lg:h-[calc(100vh-8rem)] min-h-[500px]">
            <!-- Left Side: Building List -->
            <div class="w-full lg:w-1/4 flex flex-col gap-4">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="text-lg font-display text-white">{{ $t('buildings') }}</h3>
                    <button @click="showAddBuildingModal = true" class="px-3 py-1 bg-neon-cyan/10 border border-neon-cyan/50 text-neon-cyan text-[10px] font-mono rounded hover:bg-neon-cyan hover:text-black transition-all shadow-[0_0_10px_rgba(0,240,255,0.2)]">
                        + {{ $t('add') }}
                    </button>
                </div>
                <div 
                    v-for="building in buildings" 
                    :key="building.id"
                    @click="selectedBuilding = building"
                    :class="[
                        'glass-card p-4 cursor-pointer transition-all duration-300 border hover:border-neon-cyan/50',
                        selectedBuilding?.id === building.id ? 'border-neon-cyan bg-neon-cyan/5 shadow-[0_0_15px_rgba(0,240,255,0.2)]' : 'border-white/10'
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <div :class="['w-10 h-10 rounded-lg flex items-center justify-center border transition-all', selectedBuilding?.id === building.id ? 'bg-neon-cyan/20 border-neon-cyan text-neon-cyan shadow-[0_0_10px_rgba(0,240,255,0.5)]' : 'bg-black/50 border-white/10 text-white/50']">
                            <span class="text-xl">🏗️</span>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-white">{{ building.name }}</h4>
                            <div class="flex items-center gap-2 mt-1">
                                <span :class="['w-2 h-2 rounded-full', building.status === 'active' ? 'bg-neon-cyan animate-pulse shadow-[0_0_5px_#00F0FF]' : (building.status === 'completed' ? 'bg-neon-green' : 'bg-neon-yellow')]"></span>
                                <p class="text-[10px] text-text-muted font-mono uppercase tracking-wider">{{ $t(building.status) || building.status }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="buildings.length === 0" class="glass-card p-6 text-center text-text-muted text-sm font-mono border-dashed border-white/20">
                    {{ $t('no_buildings_found') }}
                </div>
            </div>

            <!-- Right Side: Main Command Center -->
            <div class="w-full lg:flex-1 flex flex-col glass-panel rounded-2xl overflow-hidden min-h-[600px] lg:min-h-0">
                <div v-if="selectedBuilding" class="flex flex-col h-full">
                    
                    <!-- Header -->
                    <div class="p-6 border-b border-white/10 flex flex-col md:flex-row justify-between items-start gap-4 relative overflow-hidden">
                        <div class="absolute -right-20 -top-20 w-64 h-64 bg-neon-cyan/10 blur-[100px] rounded-full pointer-events-none"></div>
                        <div class="flex-1">
                            <h2 class="text-3xl font-display font-bold text-white drop-shadow-[0_0_10px_rgba(255,255,255,0.2)]">
                                {{ selectedBuilding.name }} <span class="text-neon-cyan">{{ $t('command_center') }}</span>
                            </h2>
                            <p class="text-xs text-text-muted font-mono mt-2 flex flex-wrap items-center gap-2">
                                <span>📍 {{ [selectedBuilding.city, selectedBuilding.district, selectedBuilding.mahalla, selectedBuilding.street].filter(Boolean).join(', ') || 'Manzil kiritilmagan' }}</span>
                                <span v-if="selectedBuilding.block_name || selectedBuilding.corpus_name" class="text-white">| 🏢 {{ [selectedBuilding.block_name, selectedBuilding.corpus_name].filter(Boolean).join(' - ') }}</span>
                                <span class="text-neon-yellow">| 📏 {{ selectedBuilding.total_floors }} {{ $t('floors') }}</span>
                                <span v-if="selectedBuilding.length_m || selectedBuilding.width_m || selectedBuilding.height_m" class="text-neon-green">
                                    | 📐 U: {{ selectedBuilding.length_m || 0 }}m × K: {{ selectedBuilding.width_m || 0 }}m × B: {{ selectedBuilding.height_m || 0 }}m
                                </span>
                            </p>
                        </div>
                        
                        <div class="flex flex-col items-end gap-3 shrink-0">
                            <div class="flex items-center gap-2">
                                <button @click="openEditBuilding" class="px-3 py-1 bg-white/5 border border-white/20 text-white text-[10px] font-mono rounded hover:bg-white hover:text-black transition-all flex items-center gap-1">
                                    ✏️ Tahrirlash
                                </button>
                                <button @click="deleteBuilding" class="px-3 py-1 bg-neon-magenta/10 border border-neon-magenta/50 text-neon-magenta text-[10px] font-mono rounded hover:bg-neon-magenta hover:text-black transition-all shadow-[0_0_10px_rgba(255,0,60,0.2)]">
                                    🗑️ O'chirish
                                </button>
                            </div>
                        
                        <!-- Tabs -->
                        <div class="flex flex-wrap gap-2 mt-4 md:mt-0">
                            <button @click="activeTab = 'blueprint'" :class="['px-4 py-2 text-xs font-mono font-bold uppercase tracking-wider transition-all rounded border', activeTab === 'blueprint' ? 'text-black bg-neon-cyan border-neon-cyan shadow-[0_0_15px_rgba(0,240,255,0.5)]' : 'text-text-muted border-white/10 hover:border-white/30']">🗺️ {{ $t('blueprint') }}</button>
                            <button @click="activeTab = 'progress'" :class="['px-4 py-2 text-xs font-mono font-bold uppercase tracking-wider transition-all rounded border', activeTab === 'progress' ? 'text-black bg-neon-yellow border-neon-yellow shadow-[0_0_15px_rgba(252,238,9,0.5)]' : 'text-text-muted border-white/10 hover:border-white/30']">🏗️ {{ $t('progress') }}</button>
                            <button @click="activeTab = 'finances'" :class="['px-4 py-2 text-xs font-mono font-bold uppercase tracking-wider transition-all rounded border', activeTab === 'finances' ? 'text-black bg-neon-green border-neon-green shadow-[0_0_15px_rgba(57,255,20,0.5)]' : 'text-text-muted border-white/10 hover:border-white/30']">💰 {{ $t('finances') }}</button>
                            <button @click="activeTab = 'analytics'" :class="['px-4 py-2 text-xs font-mono font-bold uppercase tracking-wider transition-all rounded border', activeTab === 'analytics' ? 'text-black bg-neon-purple border-neon-purple shadow-[0_0_15px_rgba(176,38,255,0.5)]' : 'text-text-muted border-white/10 hover:border-white/30']">📊 {{ $t('analytics') }}</button>
                        </div>
                        </div>
                    </div>

                    <!-- Content Area -->
                    <div class="flex-1 p-6 overflow-y-auto">
                        
                        <!-- TAB 1: Interactive Blueprint -->
                        <div v-if="activeTab === 'blueprint'" class="h-full flex flex-col animate-fade-in relative">
                            <!-- Header Actions -->
                            <div class="flex justify-end items-center gap-4 mb-4 w-full">
                                <button @click="showAddFloorModal = true; isEditingFloor = false" class="px-3 py-1.5 bg-white/5 border border-white/20 text-white text-[10px] font-mono rounded hover:bg-white hover:text-black transition-all">
                                    + QAVAT
                                </button>
                            </div>

                            <div class="flex gap-4 text-[10px] font-mono mb-4 bg-black/40 p-2 rounded-lg inline-flex w-max border border-white/10">
                                <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-neon-green/30 border border-neon-green shadow-[0_0_5px_#39FF14]"></span> {{ $t('available') }}</span>
                                <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-neon-yellow/30 border border-neon-yellow shadow-[0_0_5px_#FCEE09]"></span> {{ $t('reserved') }}</span>
                                <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-neon-magenta/30 border border-neon-magenta shadow-[0_0_5px_#FF003C]"></span> {{ $t('sold') }}</span>
                            </div>
                            
                            <!-- Floors List -->
                            <div class="flex-1 overflow-y-auto custom-scrollbar flex flex-col gap-6 pb-6">
                                <div v-for="floor in selectedBuilding.floors" :key="floor.id" class="flex flex-col xl:flex-row items-stretch border border-white/10 rounded-xl bg-black/50 overflow-hidden relative group">
                                    
                                    <!-- Floor Header (Left Side) -->
                                    <div class="w-full xl:w-56 bg-white/5 border-b xl:border-b-0 xl:border-r border-white/10 p-4 flex flex-row xl:flex-col items-center xl:items-start justify-between xl:justify-center gap-4 shrink-0 relative transition-colors hover:bg-white/10">
                                        <div class="flex items-center gap-3 xl:flex-col xl:items-start xl:gap-1">
                                            <h3 class="text-2xl font-display font-bold text-white">{{ floor.floor_number }}-qavat</h3>
                                            <p class="text-[10px] font-mono text-text-muted bg-black/40 px-2 py-0.5 rounded">{{ floor.units?.length || 0 }} ta joy</p>
                                        </div>
                                        
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <button @click="openEditFloor(floor)" class="px-2 py-1 bg-white/5 border border-white/20 text-white text-[10px] font-mono rounded hover:bg-white hover:text-black transition-all" title="Qavatni tahrirlash">
                                                ✏️
                                            </button>
                                            <button @click="selectedFloor = floor; showAddUnitModal = true; isEditingUnit = false" class="px-2 py-1 bg-neon-cyan/10 border border-neon-cyan/30 text-neon-cyan text-[10px] font-mono rounded hover:bg-neon-cyan hover:text-black transition-all" title="Xonadon qo'shish">
                                                + Joy
                                            </button>
                                            <button @click="deleteFloor(floor.id)" class="px-2 py-1 bg-neon-magenta/10 border border-neon-magenta/30 text-neon-magenta text-[10px] font-mono rounded hover:bg-neon-magenta hover:text-white transition-all" title="Qavatni o'chirish">
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Floor Units (Right Side) -->
                                    <div class="flex-1 p-4 overflow-x-auto custom-scrollbar flex items-center bg-black/20">
                                        <!-- Interactive SVG Blueprint Mode for Floor -->
                                        <div v-if="floor.blueprint_path" class="w-full h-full relative min-w-[800px] min-h-[300px] bg-black/40 rounded-lg border border-white/5">
                                            <svg class="w-full h-full absolute inset-0 z-10" viewBox="0 0 1000 600" preserveAspectRatio="xMidYMid meet">
                                                <g v-for="(unit, idx) in floor.units" :key="'u-'+unit.id" class="cursor-pointer transition-all duration-300 hover:brightness-150" @mousemove="(e) => onUnitHover(unit, e)" @mouseleave="onUnitLeave" @click="openEditUnit(unit)">
                                                    <polygon v-if="unit.svg_polygon" :points="unit.svg_polygon" :fill="getUnitFillColor(unit.status)" :stroke="getUnitStrokeColor(unit.status)" stroke-width="2" />
                                                    <polygon v-else :points="getFallbackPolygon(unit)" :fill="getUnitFillColor(unit.status)" :stroke="getUnitStrokeColor(unit.status)" stroke-width="2" />
                                                    <text v-if="!unit.svg_polygon" :x="getFallbackTextX(unit)" :y="getFallbackTextY(unit)" text-anchor="middle" fill="white" class="font-mono text-sm font-bold pointer-events-none drop-shadow-md">{{ unit.unit_number }}</text>
                                                </g>
                                            </svg>
                                        </div>
                                        
                                        <!-- Fallback Grid Mode -->
                                        <div v-else class="flex gap-4 min-h-[80px]">
                                            <div v-for="unit in floor.units" :key="'grid-'+unit.id"
                                                 class="w-16 h-16 sm:w-20 sm:h-20 flex flex-col items-center justify-center border-2 cursor-pointer transition-all hover:scale-110 hover:brightness-125 rounded-xl shadow-lg shrink-0 relative"
                                                 :class="getUnitBorderColor(unit.status)"
                                                 :style="{ backgroundColor: getUnitFillColor(unit.status) }"
                                                 @mousemove="(e) => onUnitHover(unit, e)"
                                                 @mouseleave="onUnitLeave"
                                                 @click="openEditUnit(unit)">
                                                <span class="font-mono text-xs sm:text-sm font-bold pointer-events-none z-10" :class="getUnitTextColor(unit.status)">
                                                    {{ unit.unit_number }}
                                                </span>
                                                <span v-if="unit.type === 'commercial'" class="absolute bottom-1 right-1 text-[10px] opacity-50">🏬</span>
                                            </div>
                                            <div v-if="!floor.units?.length" class="text-xs font-mono text-text-muted italic flex items-center h-full px-4 border border-dashed border-white/10 rounded-xl">
                                                Xonadonlar qo'shilmagan
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div v-if="!selectedBuilding.floors?.length" class="text-center py-10 border border-dashed border-white/20 rounded-xl text-text-muted font-mono text-sm bg-black/30">
                                    Qavatlar mavjud emas. Yuqoridagi "+ QAVAT" tugmasi orqali qo'shing.
                                </div>
                            </div>
                            
                            <!-- Unit Tooltip -->
                            <Teleport to="body">
                                <div v-if="hoveredUnit" 
                                     class="fixed z-50 pointer-events-none bg-black/90 backdrop-blur-md border border-white/20 p-4 rounded-xl shadow-[0_0_20px_rgba(0,0,0,0.8)]"
                                     :style="{ top: (mouseY + 15) + 'px', left: (mouseX + 15) + 'px' }">
                                    <h4 class="text-neon-cyan font-display text-lg mb-2 flex items-center gap-2">
                                        {{ hoveredUnit.unit_number }} 
                                        <span class="text-[10px] font-mono bg-white/10 px-2 py-0.5 rounded text-white">{{ hoveredUnit.type === 'commercial' ? "Do'kon" : 'Xonadon' }}</span>
                                    </h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-xs font-mono">
                                        <span class="text-text-muted">Maydoni:</span>
                                        <span class="text-white text-right">{{ hoveredUnit.area_sqm }} m²</span>
                                        
                                        <span class="text-text-muted">Xonalar:</span>
                                        <span class="text-white text-right">{{ hoveredUnit.rooms_count || 1 }} xona</span>
                                        
                                        <span class="text-text-muted">Joylashuv:</span>
                                        <span class="text-white text-right">{{ hoveredUnit.orientation || 'Noma\'lum' }}</span>
                                        
                                        <span class="text-text-muted">Narxi:</span>
                                        <span class="text-neon-green text-right">{{ $currency(hoveredUnit.price) }}</span>
                                        
                                        <span class="text-text-muted">Status:</span>
                                        <span :class="['text-right', getUnitTextColor(hoveredUnit.status)]">{{ $t(hoveredUnit.status) || hoveredUnit.status }}</span>
                                    </div>
                                </div>
                            </Teleport>
                        </div>

                        <!-- TAB 2: Construction Progress -->
                        <div v-if="activeTab === 'progress'" class="animate-fade-in space-y-6">
                            <div class="flex justify-between items-center">
                                <h3 class="text-sm font-mono text-neon-yellow uppercase tracking-widest">{{ $t('construction_timeline') }}</h3>
                                <button @click="showAddStageModal = true" class="px-3 py-1 bg-white/5 border border-white/20 text-white text-[10px] font-mono rounded hover:bg-white hover:text-black transition-all">
                                    + {{ $t('add_stage') }}
                                </button>
                            </div>

                            <div v-if="selectedBuilding.stages?.length" class="space-y-4">
                                <div v-for="stage in selectedBuilding.stages" :key="stage.id" class="glass-card p-4 border border-white/10 relative overflow-hidden">
                                    <!-- Progress Background Bar -->
                                    <div class="absolute left-0 top-0 bottom-0 bg-neon-cyan/10" :style="{ width: stage.progress_percentage + '%' }"></div>
                                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-neon-cyan shadow-[0_0_10px_#00F0FF]" :style="{ left: stage.progress_percentage + '%' }"></div>
                                    
                                    <div class="relative z-10 flex justify-between items-center">
                                        <div>
                                            <h4 class="text-white font-bold text-lg">{{ stage.name }}</h4>
                                            <p class="text-[10px] text-text-muted font-mono uppercase">{{ stage.start_date || $t('tbd') }} {{ $t('to') }} {{ stage.end_date || $t('tbd') }}</p>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <input type="range" v-model="stage.progress_percentage" class="w-32 accent-neon-cyan cursor-pointer" min="0" max="100">
                                            <span class="text-xl font-display font-bold text-neon-cyan w-12 text-right">{{ stage.progress_percentage }}%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-16 border border-dashed border-white/10 rounded-2xl text-text-muted font-mono text-sm">
                                {{ $t('no_stages_defined') }}
                            </div>
                        </div>

                        <!-- TAB 3: Estimates vs Expenses -->
                        <div v-if="activeTab === 'finances'" class="animate-fade-in">
                            <h3 class="text-sm font-mono text-neon-green uppercase tracking-widest mb-6">{{ $t('financial_summary') }}</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
                                <div class="glass-card p-6 border border-neon-cyan/30 bg-neon-cyan/5">
                                    <h4 class="text-xs font-mono text-text-muted uppercase mb-2">{{ $t('estimated_budget') }}</h4>
                                    <p class="text-4xl font-display font-bold text-neon-cyan tracking-tight drop-shadow-[0_0_10px_rgba(0,240,255,0.3)]">
                                        {{ $currency(selectedBuilding.estimated_budget) }}
                                    </p>
                                </div>
                                <div :class="['glass-card p-6 border', selectedBuilding.actual_expenses > selectedBuilding.estimated_budget ? 'border-neon-magenta/50 bg-neon-magenta/10' : 'border-neon-green/30 bg-neon-green/5']">
                                    <h4 class="text-xs font-mono text-text-muted uppercase mb-2">{{ $t('actual_expenses') }}</h4>
                                    <p :class="['text-4xl font-display font-bold tracking-tight', selectedBuilding.actual_expenses > selectedBuilding.estimated_budget ? 'text-neon-magenta drop-shadow-[0_0_10px_rgba(255,0,60,0.5)] animate-pulse' : 'text-neon-green drop-shadow-[0_0_10px_rgba(57,255,20,0.3)]']">
                                        {{ $currency(selectedBuilding.actual_expenses) }}
                                    </p>
                                    <div v-if="selectedBuilding.actual_expenses > selectedBuilding.estimated_budget" class="absolute top-4 right-4 text-xl">⚠️</div>
                                </div>
                            </div>

                            <div class="glass-card p-6 border border-white/10">
                                <h4 class="text-sm font-display text-white mb-4">{{ $t('budget_health') }}</h4>
                                <div class="h-4 bg-black rounded-full overflow-hidden flex border border-white/20">
                                    <div class="h-full bg-neon-green shadow-[0_0_10px_#39FF14]" :style="{ width: Math.min(100, (selectedBuilding.actual_expenses / (selectedBuilding.estimated_budget || 1)) * 100) + '%' }"></div>
                                    <div v-if="selectedBuilding.actual_expenses > selectedBuilding.estimated_budget" class="h-full bg-neon-magenta shadow-[0_0_10px_#FF003C] animate-pulse" :style="{ width: ((selectedBuilding.actual_expenses - selectedBuilding.estimated_budget) / selectedBuilding.estimated_budget) * 100 + '%' }"></div>
                                </div>
                                <div class="flex justify-between mt-2 text-[10px] font-mono text-text-muted">
                                    <span>$0</span>
                                    <span>{{ $t('target') }}: {{ $currency(selectedBuilding.estimated_budget) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: Analytics -->
                        <div v-if="activeTab === 'analytics'" class="animate-fade-in grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="glass-card p-6 border border-white/10">
                                <h3 class="text-sm font-mono text-neon-purple uppercase tracking-widest mb-6">{{ $t('unit_distribution') }}</h3>
                                <VueApexCharts type="donut" height="300" :options="chartOptions" :series="chartSeries" />
                            </div>
                            <div class="glass-card p-6 border border-white/10">
                                <h3 class="text-sm font-mono text-neon-cyan uppercase tracking-widest mb-6">{{ $t('construction_velocity') }}</h3>
                                <VueApexCharts type="bar" height="300" :options="progressOptions" :series="progressSeries" />
                            </div>
                        </div>

                    </div>
                </div>
                
                <div v-else class="h-full flex items-center justify-center text-text-muted font-mono text-sm text-center px-4">
                    {{ $t('select_building_command_center') }}
                </div>
            </div>
        </div>

        <!-- Add/Edit Building Modal -->
        <Modal :show="showAddBuildingModal" @close="showAddBuildingModal = false; isEditingBuilding = false">
            <div class="glass-panel p-6 border border-neon-cyan/30 bg-[#05050A] w-[600px] max-w-[95vw] overflow-y-auto max-h-[90vh] custom-scrollbar">
                <h2 class="text-2xl font-display text-neon-cyan mb-4">{{ isEditingBuilding ? 'Binoni tahrirlash' : $t('add_new_building') }}</h2>
                <form @submit.prevent="submitBuilding" class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono text-text-muted mb-1">{{ $t('building_name') }}</label>
                        <input v-model="buildingForm.name" type="text" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-all" required>
                    </div>

                    <!-- Location Fields -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Shahar</label>
                            <input v-model="buildingForm.city" type="text" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Tuman</label>
                            <input v-model="buildingForm.district" type="text" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Mahalla</label>
                            <input v-model="buildingForm.mahalla" type="text" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Ko'cha (Mo'ljal)</label>
                            <input v-model="buildingForm.street" type="text" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all">
                        </div>
                    </div>

                    <!-- Block and Corpus Fields -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Blok Nomi</label>
                            <input v-model="buildingForm.block_name" type="text" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all" placeholder="Masalan: A Blok">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Korpus Nomi</label>
                            <input v-model="buildingForm.corpus_name" type="text" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all" placeholder="Masalan: 3-Korpus">
                        </div>
                    </div>

                    <!-- Building Details -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('total_floors') }}</label>
                            <input v-model="buildingForm.total_floors" type="number" min="1" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Bloklar (Korpus)</label>
                            <input v-model="buildingForm.blocks_count" type="number" min="1" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Podyezdlar</label>
                            <input v-model="buildingForm.entrances_count" type="number" min="1" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Jami Kvartiralar</label>
                            <input v-model="buildingForm.total_apartments" type="number" min="0" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Jami Do'konlar</label>
                            <input v-model="buildingForm.total_shops" type="number" min="0" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all">
                        </div>
                    </div>

                    <!-- Building Dimensions -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Uzunligi (m)</label>
                            <input v-model="buildingForm.length_m" type="number" min="0" step="0.01" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all" placeholder="Masalan: 45.5">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Kengligi (m)</label>
                            <input v-model="buildingForm.width_m" type="number" min="0" step="0.01" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all" placeholder="Masalan: 20">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Balandligi (m)</label>
                            <input v-model="buildingForm.height_m" type="number" min="0" step="0.01" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-cyan transition-all" placeholder="Masalan: 32">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('estimated_budget_usd') }}</label>
                        <input v-model="buildingForm.estimated_budget" type="number" min="0" step="0.01" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-all" required>
                    </div>

                    <!-- Auto-Generation Fields (Only when creating) -->
                    <div v-if="!isEditingBuilding" class="p-4 border border-neon-yellow/30 rounded-lg bg-neon-yellow/5">
                        <h4 class="text-xs font-mono text-neon-yellow uppercase mb-3 font-bold tracking-widest">Qavat va Xonalarni Avtomatlashtirilgan Generatsiyasi</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Har qavatdagi xonadonlar (dona)</label>
                                <input v-model="buildingForm.units_per_floor" type="number" min="0" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-yellow transition-all" placeholder="Masalan: 4">
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">O'rtacha maydoni (kv.m)</label>
                                <input v-model="buildingForm.average_area" type="number" min="0" step="0.01" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-yellow transition-all" placeholder="Masalan: 65">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">O'rtacha narxi (UZS)</label>
                                <input v-model="buildingForm.average_price" type="number" min="0" step="0.01" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-yellow transition-all" placeholder="Masalan: 500000000">
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">1-qavatdagi Do'konlar soni</label>
                                <input v-model="buildingForm.commercial_units_first_floor" type="number" min="0" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white text-sm focus:border-neon-yellow transition-all" placeholder="Masalan: 3">
                            </div>
                        </div>
                        <p class="text-[10px] text-text-muted font-mono mt-3 italic">Eslatma: Agar kiritilsa, bino qo'shilganida barcha qavat va xonalar tayyor holatda (A1-01, D1-01 formatida) tizimga yuklanadi.</p>
                    </div>

                    <!-- Blueprint Upload -->
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Chizma yuklash (PDF/JPG)</label>
                        <input type="file" @input="buildingForm.blueprint = $event.target.files[0]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-text-muted file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-[10px] file:font-mono file:uppercase file:tracking-widest file:bg-white/10 file:text-white hover:file:bg-white/20 cursor-pointer border border-white/10 rounded bg-black/50">
                        <p v-if="buildingForm.errors.blueprint" class="text-xs text-neon-red mt-1">{{ buildingForm.errors.blueprint }}</p>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showAddBuildingModal = false; isEditingBuilding = false" class="px-4 py-2 text-xs font-mono text-text-muted hover:text-white transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="buildingForm.processing" class="px-4 py-2 bg-neon-cyan text-black font-bold font-mono text-xs rounded shadow-[0_0_10px_rgba(0,240,255,0.3)] hover:bg-white transition-colors disabled:opacity-50">
                            {{ isEditingBuilding ? 'Saqlash' : $t('create_building') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Add Stage Modal -->
        <Modal :show="showAddStageModal" @close="showAddStageModal = false">
            <div class="glass-panel p-6 border border-neon-yellow/30 bg-[#05050A]">
                <h2 class="text-2xl font-display text-neon-yellow mb-4">{{ $t('add_construction_stage') }}</h2>
                <form @submit.prevent="submitStage" class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono text-text-muted mb-1">{{ $t('stage_name') }}</label>
                        <input v-model="stageForm.name" type="text" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-yellow focus:ring-1 focus:ring-neon-yellow transition-all" required :placeholder="$t('stage_name_placeholder')">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-1">{{ $t('start_date') }}</label>
                            <input v-model="stageForm.start_date" type="date" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-yellow focus:ring-1 focus:ring-neon-yellow transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-1">{{ $t('end_date') }}</label>
                            <input v-model="stageForm.end_date" type="date" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-yellow focus:ring-1 focus:ring-neon-yellow transition-all">
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showAddStageModal = false" class="px-4 py-2 text-xs font-mono text-text-muted hover:text-white transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="stageForm.processing" class="px-4 py-2 bg-neon-yellow text-black font-bold font-mono text-xs rounded shadow-[0_0_10px_rgba(252,238,9,0.3)] hover:bg-white transition-colors disabled:opacity-50">{{ $t('btn_add_stage') }}</button>
                    </div>
                </form>
            </div>
        </Modal>
        <!-- Add Floor Modal -->
        <Modal :show="showAddFloorModal" @close="showAddFloorModal = false">
            <div class="glass-panel p-6 border border-white/30 bg-[#05050A]">
                <h2 class="text-2xl font-display text-white mb-4">{{ isEditingFloor ? "Qavatni Tahrirlash" : "Qavat Qo'shish" }}</h2>
                <form @submit.prevent="submitFloor" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-1">Qavat Raqami</label>
                            <input v-model="floorForm.floor_number" type="number" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-white focus:ring-1 focus:ring-white transition-all" required placeholder="Masalan: 1, 2, -1">
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-1">Xonadonlar soni</label>
                            <input v-model="floorForm.total_units" type="number" min="0" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-white focus:ring-1 focus:ring-white transition-all" required placeholder="Masalan: 12">
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-1">Do'konlar soni (Ixtiyoriy)</label>
                            <input v-model="floorForm.total_commercial_units" type="number" min="0" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-white focus:ring-1 focus:ring-white transition-all" placeholder="Masalan: 4">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-text-muted mb-1">Qavat Chizmasi (PDF/JPG)</label>
                        <input type="file" @input="floorForm.blueprint = $event.target.files[0]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-text-muted file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-[10px] file:font-mono file:uppercase file:tracking-widest file:bg-white/10 file:text-white hover:file:bg-white/20 cursor-pointer border border-white/10 rounded bg-black/50">
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" v-if="isEditingFloor" @click="deleteFloor(editingFloorId)" class="mr-auto px-4 py-2 bg-neon-magenta/10 text-neon-magenta font-mono text-xs rounded hover:bg-neon-magenta hover:text-black transition-colors">{{ $t('delete') }}</button>
                        <button type="button" @click="showAddFloorModal = false" class="px-4 py-2 text-xs font-mono text-text-muted hover:text-white transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="floorForm.processing" class="px-4 py-2 bg-white text-black font-bold font-mono text-xs rounded hover:bg-white/80 transition-colors disabled:opacity-50">{{ isEditingFloor ? "Saqlash" : "Qo'shish" }}</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Add Unit Modal -->
        <Modal :show="showAddUnitModal" @close="showAddUnitModal = false">
            <div class="glass-panel p-6 border border-neon-cyan/30 bg-[#05050A]">
                <h2 class="text-2xl font-display text-neon-cyan mb-4">{{ isEditingUnit ? "Xonadonni Tahrirlash" : "Xonadon / Do'kon Qo'shish" }}</h2>
                <form @submit.prevent="submitUnit" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-1">Raqami (Masalan: A501)</label>
                            <input v-model="unitForm.unit_number" type="text" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-cyan transition-all" required>
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-1">{{ $t('type_label') }}</label>
                            <select v-model="unitForm.type" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-cyan transition-all" required>
                                <option value="residential">Turar joy (Xonadon)</option>
                                <option value="commercial">Tijorat (Do'kon)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-1">Maydoni (kv.m)</label>
                            <input v-model="unitForm.area_sqm" type="number" step="0.01" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-cyan transition-all" required>
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-1">Jami Narxi (USD/UZS)</label>
                            <input v-model="unitForm.price" type="number" step="0.01" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-cyan transition-all" required>
                        </div>
                    </div>

                    <!-- Detailed Attributes -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Xonalar</label>
                            <input v-model="unitForm.rooms_count" type="number" min="1" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-cyan transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Oshxona</label>
                            <input v-model="unitForm.kitchens_count" type="number" min="0" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-cyan transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Hammom</label>
                            <input v-model="unitForm.bathrooms_count" type="number" min="0" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-cyan transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Hojatxona</label>
                            <input v-model="unitForm.toilets_count" type="number" min="0" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-cyan transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-1">Status</label>
                            <select v-model="unitForm.status" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-cyan transition-all" required>
                                <option value="available">Bo'sh</option>
                                <option value="reserved">Band qilingan</option>
                                <option value="sold">Sotilgan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Joylashuvi (Orientation)</label>
                            <input v-model="unitForm.orientation" type="text" list="orientations" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-cyan transition-all" placeholder="Masalan: Burchak, O'rtada">
                            <datalist id="orientations">
                                <option value="Burchak"></option>
                                <option value="O'rtada"></option>
                                <option value="Hovliga qaragan"></option>
                                <option value="Kun chiqish taraf"></option>
                                <option value="Kun botish taraf"></option>
                                <option value="Ko'chaga qaragan"></option>
                            </datalist>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-text-muted mb-1">Poligon Koordinatalari (Ixtiyoriy, chizma ustida shakl chizish uchun)</label>
                        <input v-model="unitForm.svg_polygon" type="text" placeholder="e.g. 100,100 200,100 200,200 100,200" class="w-full bg-black/50 border border-white/10 rounded px-3 py-2 text-white focus:border-neon-cyan transition-all text-xs font-mono text-text-muted">
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" v-if="isEditingUnit" @click="deleteUnit(editingUnitId)" class="mr-auto px-4 py-2 bg-neon-magenta/10 text-neon-magenta font-mono text-xs rounded hover:bg-neon-magenta hover:text-black transition-colors">{{ $t('delete') }}</button>
                        <button type="button" v-if="isEditingUnit && unitForm.status !== 'sold'" @click="openSellUnit(selectedFloor.units.find(u => u.id === editingUnitId))" class="px-4 py-2 bg-neon-green/20 text-neon-green border border-neon-green font-bold font-mono text-xs rounded hover:bg-neon-green hover:text-black transition-colors shadow-[0_0_10px_rgba(57,255,20,0.3)]">
                            Sotish
                        </button>
                        <button type="button" @click="showAddUnitModal = false" class="px-4 py-2 text-xs font-mono text-text-muted hover:text-white transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="unitForm.processing" class="px-4 py-2 bg-neon-cyan text-black font-bold font-mono text-xs rounded hover:bg-white transition-colors shadow-[0_0_10px_rgba(0,240,255,0.3)] disabled:opacity-50">{{ isEditingUnit ? "Saqlash" : "Qo'shish" }}</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Blueprint Viewer Modal -->
        <Modal :show="showBlueprintModal" @close="showBlueprintModal = false" maxWidth="4xl">
            <div class="glass-panel p-6 border border-neon-purple/30 bg-[#05050A]">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-display text-neon-purple">Qavat Chizmasi</h2>
                    <button @click="showBlueprintModal = false" class="text-white hover:text-neon-magenta transition-colors">
                        ✕
                    </button>
                </div>
                <div class="w-full max-h-[80vh] overflow-auto flex items-center justify-center bg-black/50 rounded-xl border border-white/10">
                    <img v-if="selectedFloor?.blueprint_path" :src="`/storage/${selectedFloor.blueprint_path}`" class="max-w-full object-contain" />
                </div>
            </div>
        </Modal>

        <!-- Sell Unit Modal (Create Contract) -->
        <div v-if="showSellUnitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showSellUnitModal = false"></div>
            <div class="relative glass-panel p-6 rounded-2xl w-full max-w-lg border border-neon-green/30 max-h-[90vh] overflow-y-auto custom-scrollbar">
                <h2 class="text-xl font-bold text-white mb-6 uppercase tracking-widest text-shadow-glow shadow-neon-green/50">
                    {{ $t('add') }} {{ $t('contract') }}
                </h2>
                <form @submit.prevent="submitContract" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('contract_date_optional') }}</label>
                            <input v-model="contractForm.created_at" type="date" class="glass-input w-full text-neon-green" title="O'tmish sanada shartnoma tuzish uchun">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('contract_number_optional') }}</label>
                            <input v-model="contractForm.contract_number" type="text" class="glass-input w-full text-neon-green" placeholder="Masalan: SH-001">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-2 uppercase">Xaridor Turi</label>
                        <div class="flex gap-4 mb-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.buyer_type" value="client" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                                <span class="text-xs text-white">Mijoz (Client)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.buyer_type" value="partner" class="text-neon-yellow focus:ring-neon-yellow bg-black/50 border-white/20">
                                <span class="text-xs text-white text-neon-yellow">Ta'sischi Xaridi (Partner)</span>
                            </label>
                        </div>
                        
                        <!-- Client Select -->
                        <select v-if="contractForm.buyer_type === 'client'" v-model="contractForm.client_id" class="glass-input w-full bg-black/50" :required="contractForm.buyer_type === 'client'">
                            <option value="" disabled>{{ $t('select_client') }}</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.first_name }} {{ c.last_name }}</option>
                        </select>
                        
                        <!-- Partner Select -->
                        <select v-if="contractForm.buyer_type === 'partner'" v-model="contractForm.partner_id" class="glass-input w-full bg-black/50 border-neon-yellow/50" :required="contractForm.buyer_type === 'partner'">
                            <option value="" disabled>Ta'sischini tanlang</option>
                            <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.user?.name }} (Ulush: {{ p.investment_share_percentage }}%)</option>
                        </select>
                    </div>

                    <div v-if="currentSellingUnit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-3 border border-white/10 rounded-xl bg-black/30">
                        <div class="col-span-1 sm:col-span-2">
                            <p class="text-[10px] font-mono text-text-muted uppercase">{{ $t('object_block') }}</p>
                            <p class="text-xs text-white font-bold">{{ selectedBuilding?.name || 'Noma\'lum' }} (Xonadon: {{ currentSellingUnit.unit_number }}, Maydon: {{ currentSellingUnit.area_sqm }} kv.m)</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('floor') }}</label>
                            <input v-model="contractForm.floor" type="text" class="glass-input w-full text-white" placeholder="Qavatni kiriting">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('number_of_rooms') }}</label>
                            <input v-model="contractForm.rooms" type="number" class="glass-input w-full text-white" placeholder="Xonalar sonini kiriting">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" v-if="currentSellingUnit">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('total_price_with_vat') }}</label>
                            <input v-model="contractForm.total_amount" type="number" step="any" class="glass-input w-full text-neon-green font-bold text-lg" required placeholder="Umumiy summani kiriting">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('price_per_sqm_label') }} (Avtomatik)</label>
                            <input v-model="contractForm.price_per_sqm" type="number" step="any" class="glass-input w-full bg-black/50 opacity-70 cursor-not-allowed" readonly tabindex="-1">
                        </div>
                    </div>

                    <!-- Payment Type Selection -->
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-2 uppercase">{{ $t('payment_type') }}</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.payment_type" value="full" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                                <span class="text-xs text-white">{{ $t('one_time_full_payment') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.payment_type" value="installment" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                                <span class="text-xs text-white">{{ $t('installment_payment') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div v-if="contractForm.payment_type === 'installment'">
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('down_payment') }}</label>
                            <input v-model="contractForm.down_payment" type="number" step="any" class="glass-input w-full" required>
                        </div>
                        <div v-if="contractForm.payment_type === 'installment'">
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('term_months') }}</label>
                            <input v-model="contractForm.term_months" type="number" class="glass-input w-full" required>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-white/10 mt-4">
                        <h3 class="text-sm font-bold text-neon-cyan uppercase mb-4">Hozirgi Vaqtda To'lov Qabul Qilish (Ixtiyoriy)</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Haqiqatda qilingan to'lov</label>
                                <input v-model="contractForm.actual_paid_amount" type="number" step="0.01" class="glass-input w-full text-neon-yellow" placeholder="Masalan: 600000000">
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">To'lov Usuli</label>
                                <select v-model="contractForm.payment_method" class="glass-input w-full bg-black/50">
                                    <option value="bank">{{ $t('bank_account') }}</option>
                                    <option value="main">{{ $t('cash_account') }}</option>
                                    <option value="credit">💳 Plastik / Terminal</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 mt-8">
                        <button type="button" @click="showSellUnitModal = false" class="px-4 py-2 text-xs font-mono uppercase tracking-widest text-text-muted hover:text-white transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessingContract" class="px-6 py-2 bg-neon-green text-black font-bold uppercase tracking-widest rounded hover:shadow-[0_0_15px_#39FF14] transition-all disabled:opacity-50">{{ $t('create_pipeline') }}</button>
                    </div>
                </form>
            </div>
        </div>

    </AdminLayout>
</template>
