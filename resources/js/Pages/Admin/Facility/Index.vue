<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import Modal from '@/Components/Modal.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const props = defineProps({
    buildings: Array,
    clients: Array,
    partners: Array
});

const page = usePage();
const isAdmin = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return false;
    const rawRoles = user.roles || [];
    const roles = rawRoles.map(r => typeof r === 'string' ? r : r.name);
    return roles.includes('Super_Admin') || roles.includes('Admin');
});

const selectedBuilding = ref(props.buildings?.length > 0 ? props.buildings[0] : null);
const selectedFloor = ref(selectedBuilding.value?.floors?.length > 0 ? selectedBuilding.value.floors[0] : null);
const activeTab = ref('blueprint');

const sortedFloors = computed(() => {
    if (!selectedBuilding.value || !selectedBuilding.value.floors) return [];
    return [...selectedBuilding.value.floors].sort((a, b) => b.floor_number - a.floor_number);
});

const maxUnitsCount = computed(() => {
    if (!selectedBuilding.value || !selectedBuilding.value.floors) return 1;
    const counts = selectedBuilding.value.floors.map(f => f.units?.length || 0);
    return Math.max(...counts, 1);
});

function getSortedUnits(units) {
    if (!units) return [];
    return [...units].sort((a, b) => {
        const numA = String(a.unit_number).replace(/\D/g, '');
        const numB = String(b.unit_number).replace(/\D/g, '');
        return (Number(numA) || 0) - (Number(numB) || 0);
    });
}

// Synchronize activeTab with the URL tab parameter reactively
watch(
    () => page.url,
    (newUrl) => {
        try {
            const url = new URL(newUrl, window.location.origin);
            const tabParam = url.searchParams.get('tab');
            if (tabParam !== null) {
                if (activeTab.value !== tabParam) {
                    activeTab.value = tabParam;
                }
            } else {
                activeTab.value = 'blueprint'; // Default tab is 'blueprint'
            }
        } catch (e) {
            console.error('URL parse error in Facility tab watcher', e);
        }
    },
    { immediate: true }
);

// Synchronize URL query parameter with activeTab changes inside the component
watch(activeTab, (newTab) => {
    try {
        const url = new URL(window.location.href);
        const currentTab = url.searchParams.get('tab');
        if (currentTab === null || currentTab !== newTab) {
            router.replace(route('admin.facility', { tab: newTab }), {
                preserveState: true,
                preserveScroll: true
            });
        }
    } catch (e) {
        console.error('URL replace error in Facility activeTab watcher', e);
    }
});

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

watch(selectedBuilding, (newBuilding) => {
    if (newBuilding && newBuilding.floors && newBuilding.floors.length > 0) {
        selectedFloor.value = newBuilding.floors[0];
    } else {
        selectedFloor.value = null;
    }
});


const expandedObjects = ref({});
const groupedObjects = computed(() => {
    const groups = {};
    if (!props.buildings) return groups;
    props.buildings.forEach(b => {
        const objName = b.name || 'Boshqa';
        if (!groups[objName]) {
            groups[objName] = [];
        }
        groups[objName].push(b);
    });
    return groups;
});

function toggleObject(objName) {
    expandedObjects.value[objName] = !expandedObjects.value[objName];
}

function openAddBlock(objName) {
    showAddBuildingModal.value = true;
    isEditingBuilding.value = false;
    isAddingBlock.value = true;
    setTimeout(() => {
        buildingForm.reset();
        buildingForm.name = objName;
        // Find the first building in this group to copy its location details
        const existing = props.buildings.find(b => b.name === objName);
        if (existing) {
            buildingForm.city = existing.city || '';
            buildingForm.district = existing.district || '';
            buildingForm.mahalla = existing.mahalla || '';
            buildingForm.street = existing.street || '';
        }
    }, 100);
}

const searchFilters = ref({
    building_id: '',
    type: '',
    status: ''
});

const searchResults = ref(null);

function performSearch() {
    let results = [];
    
    const buildingsToSearch = searchFilters.value.building_id 
        ? props.buildings.filter(b => b.id === searchFilters.value.building_id)
        : props.buildings;
        
    buildingsToSearch.forEach(b => {
        if (b.floors) {
            b.floors.forEach(f => {
                if (f.units) {
                    f.units.forEach(u => {
                        let match = true;
                        if (searchFilters.value.type && u.type !== searchFilters.value.type) match = false;
                        if (searchFilters.value.status && u.status !== searchFilters.value.status) match = false;
                        
                        if (match) {
                            results.push({
                                ...u,
                                building_name: b.name,
                                floor_number: f.floor_number
                            });
                        }
                    });
                }
            });
        }
    });
    
    searchResults.value = results;
}

function downloadUnitsCSV() {
    if (!selectedBuilding.value || !selectedBuilding.value.floors) return;
    
    const headers = ['Bino nomi', 'Qavat', 'Raqami', 'Turi', 'Maydoni (m2)', 'Xonalar', 'Narxi', 'Statusi'];
    let csvContent = "\uFEFF" + headers.join(';') + "\r\n";
    
    const sortedFloors = [...selectedBuilding.value.floors].sort((a, b) => a.floor_number - b.floor_number);
    
    sortedFloors.forEach(floor => {
        if (!floor.units) return;
        
        const sortedUnits = [...floor.units].sort((a, b) => {
            const numA = String(a.unit_number).replace(/\D/g, '');
            const numB = String(b.unit_number).replace(/\D/g, '');
            return (Number(numA) || 0) - (Number(numB) || 0);
        });
        
        sortedUnits.forEach(unit => {
            const buildingName = selectedBuilding.value.name;
            const floorNum = floor.floor_number;
            const unitType = unit.type === 'commercial' ? "Do'kon" : 'Xonadon';
            const unitNumber = (unit.type === 'commercial' && !String(unit.unit_number).startsWith('D') ? 'D-' : '') + unit.unit_number;
            const area = unit.area_sqm || 0;
            const rooms = unit.rooms_count || 1;
            const price = unit.price || 0;
            
            let statusLabel = unit.status;
            if (statusLabel === 'available') statusLabel = "Bo'sh";
            else if (statusLabel === 'reserved') statusLabel = 'Band qilingan';
            else if (statusLabel === 'sold') statusLabel = 'Sotilgan';
            
            const row = [
                `"${buildingName}"`,
                `"${floorNum}-qavat"`,
                `"${unitNumber}"`,
                `"${unitType}"`,
                `"${area}"`,
                `"${rooms}"`,
                `"${price}"`,
                `"${statusLabel}"`
            ];
            
            csvContent += row.join(';') + "\r\n";
        });
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", `${selectedBuilding.value.name}_obyektlar_holati.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

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

const clientsOptions = computed(() => {
    return props.clients?.map(c => ({
        label: `${c.first_name} ${c.last_name || ''}`.trim(),
        value: c.id
    })) || [];
});

const partnersOptions = computed(() => {
    return props.partners?.map(p => ({
        label: `${p.user?.name} (Ulush: ${p.investment_share_percentage}%)`,
        value: p.id
    })) || [];
});

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
const isAddingBlock = ref(false);

function openEditBuilding() {
    if (!selectedBuilding.value) return;
    isEditingBuilding.value = true;
    isAddingBlock.value = false;
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
    if (status === 'available') return 'border-green-200 shadow-sm';
    if (status === 'reserved') return 'border-orange-200 shadow-sm';
    if (status === 'sold') return 'border-red-200 shadow-sm';
    return 'border-gray-200';
}

function getUnitFillColor(status) {
    if (status === 'available') return 'var(--unit-available-bg, #e8f5e9)';
    if (status === 'reserved') return 'var(--unit-reserved-bg, #fff3e0)';
    if (status === 'sold') return 'var(--unit-sold-bg, #ffebee)';
    return 'var(--glass-input-bg, #f3f4f6)';
}

function getUnitStrokeColor(status) {
    if (status === 'available') return 'var(--unit-available-border, #a5d6a7)';
    if (status === 'reserved') return 'var(--unit-reserved-border, #ffcc80)';
    if (status === 'sold') return 'var(--unit-sold-border, #ffcdd2)';
    return 'var(--glass-input-border, #e5e7eb)';
}

function getUnitTextColor(status) {
    if (status === 'available') return 'var(--unit-available-text, #2e7d32)';
    if (status === 'reserved') return 'var(--unit-reserved-text, #ef6c00)';
    if (status === 'sold') return 'var(--unit-sold-text, #c62828)';
    return 'var(--body-text-color, #111827)';
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
                    <h3 class="text-lg font-display text-gray-900 font-bold uppercase tracking-wider">Obyektlar</h3>
                    <button @click="showAddBuildingModal = true; isEditingBuilding = false; isAddingBlock = false; buildingForm.reset();" class="px-3 py-1.5 bg-white border border-gray-200 shadow-sm text-gray-700 text-xs font-mono rounded hover:bg-gray-50 transition-all">
                        + OBYEKT
                    </button>
                </div>
                
                <div v-for="(blocks, objName) in groupedObjects" :key="objName" class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden transition-all">
                    <div @click="toggleObject(objName)" class="p-4 flex items-center justify-between cursor-pointer hover:bg-gray-50 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <span class="text-blue-600">🏢</span>
                            <h4 class="text-sm font-bold text-gray-900 uppercase tracking-widest">{{ objName }}</h4>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click.stop="openAddBlock(objName)" class="w-6 h-6 flex items-center justify-center bg-gray-50 border border-gray-200 text-gray-500 rounded hover:bg-blue-50 hover:text-blue-600 transition-all" title="Blok qo'shish">+</button>
                            <span class="text-gray-400 transform transition-transform" :class="{'rotate-180': expandedObjects[objName]}">▼</span>
                        </div>
                    </div>
                    
                    <div v-show="expandedObjects[objName] !== false" class="p-2 flex flex-col gap-2 bg-gray-50">
                        <div 
                            v-for="building in blocks" 
                            :key="building.id"
                            @click="selectedBuilding = building"
                            :class="[ 'bg-white border shadow-sm rounded-lg p-3 cursor-pointer transition-all hover:border-blue-300', selectedBuilding?.id === building.id ? 'border-blue-500 ring-1 ring-blue-500' : 'border-gray-200' ]"
                        >
                            <div class="flex justify-between items-center">
                                <h5 class="text-xs font-bold text-gray-900">{{ building.block_name || building.corpus_name || 'Asosiy Blok' }}</h5>
                                <div class="flex items-center gap-1">
                                    <span :class="['w-2 h-2 rounded-full', building.status === 'active' ? 'bg-blue-500' : (building.status === 'completed' ? 'bg-green-500' : 'bg-yellow-500')]"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="Object.keys(groupedObjects).length === 0" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 text-center text-gray-500 text-sm font-mono border-dashed">
                    Hech narsa topilmadi
                </div>
            </div>

            <!-- Right Side: Main Command Center -->
            <div class="w-full lg:flex-1 flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl rounded-2xl overflow-hidden min-h-[600px] lg:min-h-0">
                <div v-if="selectedBuilding" class="flex flex-col h-full">
                    
                    <!-- Header -->
                    <div class="p-6 border-b border-gray-200 flex flex-col md:flex-row justify-between items-start gap-4 relative overflow-hidden">
                        <div class="absolute -right-20 -top-20 w-64 h-64 bg-blue-50 blur-[100px] rounded-full pointer-events-none"></div>
                        <div class="flex-1">
                            <h2 class="text-3xl font-display font-bold text-gray-900 drop-shadow-sm">
                                {{ selectedBuilding.name }} <span class="text-blue-600">{{ $t('command_center') }}</span>
                            </h2>
                            <div class="mt-4 overflow-x-auto border border-gray-200/20 rounded-xl bg-gray-50 p-4">
                                <table class="w-full text-left text-xs font-mono text-gray-700">
                                    <tbody>
                                        <tr class="border-b border-gray-200/10">
                                            <td class="py-2 pr-4 text-gray-400 font-bold uppercase tracking-wider text-[10px]">📍 Manzil:</td>
                                            <td class="py-2 pr-6 font-bold text-gray-900">{{ [selectedBuilding.city, selectedBuilding.district, selectedBuilding.mahalla, selectedBuilding.street].filter(Boolean).join(', ') || 'Manzil kiritilmagan' }}</td>
                                            <td class="py-2 pr-4 text-gray-400 font-bold uppercase tracking-wider text-[10px]">🏢 Blok / Korpus:</td>
                                            <td class="py-2 font-bold text-gray-900">{{ [selectedBuilding.block_name, selectedBuilding.corpus_name].filter(Boolean).join(' - ') || 'Kiritilmagan' }}</td>
                                        </tr>
                                        <tr class="border-b border-gray-200/10">
                                            <td class="py-2 pr-4 text-gray-400 font-bold uppercase tracking-wider text-[10px]">📏 Qavatlar:</td>
                                            <td class="py-2 pr-6 font-bold text-amber-600">{{ selectedBuilding.total_floors }} qavat</td>
                                            <td class="py-2 pr-4 text-gray-400 font-bold uppercase tracking-wider text-[10px]">🚪 Podyezdlar / Bloklar:</td>
                                            <td class="py-2 font-bold text-gray-900">{{ selectedBuilding.entrances_count || 1 }} ta podyezd / {{ selectedBuilding.blocks_count || 1 }} ta blok</td>
                                        </tr>
                                        <tr class="border-b border-gray-200/10">
                                            <td class="py-2 pr-4 text-gray-400 font-bold uppercase tracking-wider text-[10px]">🏠 Jami Uylar:</td>
                                            <td class="py-2 pr-6 font-bold text-blue-600">{{ selectedBuilding.total_apartments || 0 }} ta</td>
                                            <td class="py-2 pr-4 text-gray-400 font-bold uppercase tracking-wider text-[10px]">🏬 Jami Do'konlar:</td>
                                            <td class="py-2 font-bold text-indigo-600">{{ selectedBuilding.total_shops || 0 }} ta</td>
                                        </tr>
                                        <tr>
                                            <td class="py-2 pr-4 text-gray-400 font-bold uppercase tracking-wider text-[10px]">📐 O'lchamlar (U×K×B):</td>
                                            <td class="py-2 pr-6 font-bold text-emerald-600">{{ selectedBuilding.length_m || 0 }}m × {{ selectedBuilding.width_m || 0 }}m × {{ selectedBuilding.height_m || 0 }}m</td>
                                            <td class="py-2 pr-4 text-gray-400 font-bold uppercase tracking-wider text-[10px]">💰 Smeta byudjeti:</td>
                                            <td class="py-2 font-bold text-emerald-600">{{ $currency(selectedBuilding.estimated_budget) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="flex flex-col items-end gap-3 shrink-0">
                            <div class="flex items-center gap-2">
                                <button @click="openEditBuilding" class="px-3 py-1 border border-gray-300 text-gray-900 text-[10px] font-mono rounded hover:bg-white hover:text-black transition-all flex items-center gap-1">
                                    ✏️ Tahrirlash
                                </button>
                                <button v-if="isAdmin" @click="deleteBuilding" class="px-3 py-1 bg-indigo-50 border border-neon-magenta/50 text-indigo-600 text-[10px] font-mono rounded hover:bg-indigo-600 hover:text-black transition-all shadow-sm">
                                    🗑️ O'chirish
                                </button>
                            </div>
                        

                        </div>
                    </div>

                    <!-- Content Area -->
                    <div class="flex-1 p-6 overflow-y-auto">
                        
                        <!-- TAB 1: Interactive Blueprint -->
                        <div v-if="activeTab === 'blueprint'" class="h-full flex flex-col animate-fade-in relative">
                            <!-- Header Actions -->
                            <div class="flex justify-end items-center gap-4 mb-4 w-full">
                                <button @click="downloadUnitsCSV" class="px-3 py-1.5 bg-emerald-50 border border-emerald-200 text-emerald-600 text-[10px] font-mono rounded hover:bg-emerald-600 hover:text-black transition-all flex items-center gap-2 shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    EXCEL YUKLAB OLISH
                                </button>
                                <button @click="showAddFloorModal = true; isEditingFloor = false" class="px-3 py-1.5 border border-gray-300 text-gray-900 text-[10px] font-mono rounded hover:bg-white hover:text-black transition-all">
                                    + QAVAT
                                </button>
                            </div>

                            <div class="flex gap-4 text-[10px] font-mono mb-4 bg-gray-50 p-2 rounded-lg inline-flex w-max border border-gray-200">
                                <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-green-100 border border-green-300 shadow-sm"></span> {{ $t('available') }}</span>
                                <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-yellow-100 border border-yellow-300 shadow-sm"></span> {{ $t('reserved') }}</span>
                                <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-red-100 border border-red-300 shadow-sm"></span> {{ $t('sold') }}</span>
                            </div>
                            
                            <div class="flex-1 overflow-y-auto custom-scrollbar flex flex-col gap-4 pb-6">
                                <div v-for="floor in sortedFloors" :key="floor.id" class="flex flex-col xl:flex-row items-stretch border border-gray-200 bg-white rounded-lg shadow-sm relative group overflow-hidden">
                                    
                                    <!-- Floor Header (Left Side) -->
                                    <div class="w-full xl:w-48 bg-white border-b xl:border-b-0 xl:border-r border-gray-200 p-4 py-6 flex flex-row xl:flex-col items-center xl:items-start justify-between xl:justify-center gap-4 shrink-0 transition-colors hover:bg-gray-100">
                                        <div class="flex items-center gap-3">
                                            <h3 class="text-2xl font-display font-bold text-gray-900 whitespace-nowrap">{{ floor.floor_number }}-qavat</h3>
                                            <div class="flex gap-1">
                                                <span v-if="floor.units?.some(u => u.type === 'commercial')" class="text-xl drop-shadow-sm" title="Do'konlar">🏬</span>
                                                <span v-if="floor.units?.some(u => u.type === 'residential')" class="text-xl drop-shadow-sm" title="Xonadonlar">🏠</span>
                                            </div>
                                        </div>
                                        
                                        <div class="flex flex-wrap gap-2 mt-2 opacity-50 group-hover:opacity-100 transition-opacity">
                                            <button @click="openEditFloor(floor)" class="w-7 h-7 flex items-center justify-center border border-gray-300 text-gray-900 text-xs rounded hover:bg-white hover:text-black transition-all" title="Qavatni tahrirlash">
                                                ✏️
                                            </button>
                                            <button @click="selectedFloor = floor; showAddUnitModal = true; isEditingUnit = false" class="w-7 h-7 flex items-center justify-center bg-blue-50 border border-blue-200 text-blue-600 text-xs rounded hover:bg-blue-600 hover:text-black transition-all" title="Xonadon qo'shish">
                                                +
                                            </button>
                                            <button v-if="isAdmin" @click="deleteFloor(floor.id)" class="w-7 h-7 flex items-center justify-center bg-indigo-50 border border-neon-magenta/30 text-indigo-600 text-xs rounded hover:bg-indigo-600 hover:text-gray-900 transition-all" title="Qavatni o'chirish">
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Floor Units (Right Side) -->
                                    <div class="flex-1 p-4 pb-6 overflow-x-auto custom-scrollbar flex items-center min-h-[140px]">
                                        <!-- Interactive SVG Blueprint Mode for Floor -->
                                        <div v-if="floor.blueprint_path" class="w-full h-full relative min-w-[800px] min-h-[300px] bg-gray-50 rounded-lg border border-gray-200">
                                            <svg class="w-full h-full absolute inset-0 z-10" viewBox="0 0 1000 600" preserveAspectRatio="xMidYMid meet">
                                                <g v-for="(unit, idx) in getSortedUnits(floor.units)" :key="'u-'+unit.id" class="cursor-pointer transition-all duration-300 hover:brightness-110" @mousemove="(e) => onUnitHover(unit, e)" @mouseleave="onUnitLeave" @click="openEditUnit(unit)">
                                                    <polygon v-if="unit.svg_polygon" :points="unit.svg_polygon" :fill="getUnitFillColor(unit.status)" :stroke="getUnitStrokeColor(unit.status)" stroke-width="2" />
                                                    <polygon v-else :points="getFallbackPolygon(unit)" :fill="getUnitFillColor(unit.status)" :stroke="getUnitStrokeColor(unit.status)" stroke-width="2" />
                                                    <text v-if="!unit.svg_polygon" :x="getFallbackTextX(unit)" :y="getFallbackTextY(unit)" text-anchor="middle" :fill="getUnitTextColor(unit.status)" class="font-mono text-sm font-bold pointer-events-none drop-shadow-sm">{{ unit.unit_number }}</text>
                                                </g>
                                            </svg>
                                        </div>
                                        
                                        <!-- Fallback Grid Mode -->
                                        <div v-else class="grid gap-4" :style="{ gridTemplateColumns: 'repeat(' + maxUnitsCount + ', minmax(7rem, 1fr))' }">
                                            <div v-for="unit in getSortedUnits(floor.units)" :key="'grid-'+unit.id"
                                                 class="w-24 h-16 sm:w-28 sm:h-20 flex items-center justify-center border-2 cursor-pointer transition-transform hover:-translate-y-1 rounded-md shadow-lg shrink-0 relative"
                                                 :class="getUnitBorderColor(unit.status)"
                                                 :style="{ backgroundColor: getUnitFillColor(unit.status) }"
                                                 @mousemove="(e) => onUnitHover(unit, e)"
                                                 @mouseleave="onUnitLeave"
                                                 @click="openEditUnit(unit)">
                                                <span class="font-mono text-sm sm:text-base font-bold pointer-events-none z-10" :class="getUnitTextColor(unit.status)">
                                                    {{ (unit.type === 'commercial' && !String(unit.unit_number).startsWith('D')) ? 'D-' : '' }}{{ unit.unit_number }}
                                                </span>
                                            </div>
                                            <div v-if="!floor.units?.length" class="col-span-full text-xs font-mono text-gray-500 italic py-4 border border-dashed border-gray-200 rounded-md text-center">
                                                Xonadonlar yo'q
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div v-if="!selectedBuilding.floors?.length" class="text-center py-10 border border-dashed border-gray-300 rounded-xl text-gray-500 font-mono text-sm bg-gray-50">
                                    Qavatlar mavjud emas. Yuqoridagi "+ QAVAT" tugmasi orqali qo'shing.
                                </div>
                            </div>
                            
                            <!-- Unit Tooltip -->
                            <Teleport to="body">
                                <div v-if="hoveredUnit" 
                                     class="fixed z-50 pointer-events-none bg-white border border-gray-200 p-4 rounded-xl shadow-lg"
                                     :style="{ top: (mouseY + 15) + 'px', left: (mouseX + 15) + 'px' }">
                                    <h4 class="text-blue-600 font-display text-lg mb-2 flex items-center gap-2">
                                        {{ hoveredUnit.unit_number }} 
                                        <span class="text-[10px] font-mono px-2 py-0.5 rounded text-gray-900">{{ hoveredUnit.type === 'commercial' ? "Do'kon" : 'Xonadon' }}</span>
                                    </h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-xs font-mono">
                                        <span class="text-gray-500">Maydoni:</span>
                                        <span class="text-gray-900 text-right">{{ hoveredUnit.area_sqm }} m²</span>
                                        
                                        <span class="text-gray-500">Xonalar:</span>
                                        <span class="text-gray-900 text-right">{{ hoveredUnit.rooms_count || 1 }} xona</span>
                                        
                                        <span class="text-gray-500">Joylashuv:</span>
                                        <span class="text-gray-900 text-right">{{ hoveredUnit.orientation || 'Noma\'lum' }}</span>
                                        
                                        <span class="text-gray-500">Narxi:</span>
                                        <span class="text-emerald-600 text-right">{{ $currency(hoveredUnit.price) }}</span>
                                        
                                        <span class="text-gray-500">Status:</span>
                                        <span :class="['text-right', getUnitTextColor(hoveredUnit.status)]">{{ $t(hoveredUnit.status) || hoveredUnit.status }}</span>
                                    </div>
                                </div>
                            </Teleport>
                        </div>
                        
                        <!-- TAB: Search -->
                        <div v-if="activeTab === 'search'" class="animate-fade-in space-y-6">
                            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-gray-200">
                                <h3 class="text-sm font-mono text-gray-900 uppercase tracking-widest mb-4">Obyekt va Xonadonlarni Qidirish</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Obyekt (Bino)</label>
                                        <select v-model="searchFilters.building_id" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-white focus:ring-1 focus:ring-white transition-all text-sm">
                                            <option value="">Barchasi</option>
                                            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Turi</label>
                                        <select v-model="searchFilters.type" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-white focus:ring-1 focus:ring-white transition-all text-sm">
                                            <option value="">Barchasi</option>
                                            <option value="residential">Turar joy (Uy)</option>
                                            <option value="commercial">Tijorat (Do'kon)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Holati</label>
                                        <select v-model="searchFilters.status" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-white focus:ring-1 focus:ring-white transition-all text-sm">
                                            <option value="">Barchasi</option>
                                            <option value="available">Bo'sh</option>
                                            <option value="reserved">Band qilingan</option>
                                            <option value="sold">Sotilgan</option>
                                        </select>
                                    </div>
                                </div>
                                <button @click="performSearch" class="w-full py-2 bg-white text-black font-bold font-mono text-xs rounded hover:bg-gray-200 transition-colors shadow-sm">
                                    Qidirish
                                </button>
                            </div>
                        
                            <!-- Results -->
                            <div v-if="searchResults !== null" class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 border border-gray-200">
                                <h4 class="text-xs font-mono text-gray-500 uppercase mb-4">Natijalar: {{ searchResults.length }} ta topildi</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm font-mono">
                                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                            <tr class="border-b border-gray-200 text-[10px] text-gray-500 uppercase tracking-widest bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                                <th class="py-3 px-2">Bino / Qavat</th>
                                                <th class="py-3 px-2">Raqam</th>
                                                <th class="py-3 px-2">Turi</th>
                                                <th class="py-3 px-2">Holati</th>
                                                <th class="py-3 px-2">Maydon</th>
                                                <th class="py-3 px-2 text-right">Narxi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="unit in searchResults" :key="unit.id" class="border-b border-gray-100 hover: bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                                <td class="py-3 px-2 text-gray-900">{{ unit.building_name }} / {{ unit.floor_number }}-qavat</td>
                                                <td class="py-3 px-2 font-bold text-gray-900">{{ unit.unit_number }}</td>
                                                <td class="py-3 px-2">{{ unit.type === 'commercial' ? "Do'kon" : 'Uy' }}</td>
                                                <td class="py-3 px-2" :class="getUnitTextColor(unit.status)">{{ $t(unit.status) || unit.status }}</td>
                                                <td class="py-3 px-2">{{ unit.area_sqm }} m²</td>
                                                <td class="py-3 px-2 text-emerald-600 text-right">{{ $currency(unit.price) }}</td>
                                            </tr>
                                            <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="searchResults.length === 0">
                                                <td colspan="6" class="py-8 text-center text-gray-500 text-xs border border-dashed border-gray-200 rounded-xl">Hech narsa topilmadi</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: Construction Progress -->
                        <div v-if="activeTab === 'progress'" class="animate-fade-in space-y-6">
                            <div class="flex justify-between items-center">
                                <h3 class="text-sm font-mono text-amber-600 uppercase tracking-widest">{{ $t('construction_timeline') }}</h3>
                                <button @click="showAddStageModal = true" class="px-3 py-1 border border-gray-300 text-gray-900 text-[10px] font-mono rounded hover:bg-white hover:text-black transition-all">
                                    + {{ $t('add_stage') }}
                                </button>
                            </div>

                            <div v-if="selectedBuilding.stages?.length" class="space-y-4">
                                <div v-for="stage in selectedBuilding.stages" :key="stage.id" class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 border border-gray-200 relative overflow-hidden">
                                    <!-- Progress Background Bar -->
                                    <div class="absolute left-0 top-0 bottom-0 bg-blue-50" :style="{ width: stage.progress_percentage + '%' }"></div>
                                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-blue-600 shadow-sm" :style="{ left: stage.progress_percentage + '%' }"></div>
                                    
                                    <div class="relative z-10 flex justify-between items-center">
                                        <div>
                                            <h4 class="text-gray-900 font-bold text-lg">{{ stage.name }}</h4>
                                            <p class="text-[10px] text-gray-500 font-mono uppercase">{{ stage.start_date || $t('tbd') }} {{ $t('to') }} {{ stage.end_date || $t('tbd') }}</p>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <input type="range" v-model="stage.progress_percentage" class="w-32 accent-neon-cyan cursor-pointer" min="0" max="100">
                                            <span class="text-xl font-display font-bold text-blue-600 w-12 text-right">{{ stage.progress_percentage }}%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-16 border border-dashed border-gray-200 rounded-2xl text-gray-500 font-mono text-sm">
                                {{ $t('no_stages_defined') }}
                            </div>
                        </div>

                        <!-- TAB 3: Estimates vs Expenses -->
                        <div v-if="activeTab === 'finances'" class="animate-fade-in">
                            <h3 class="text-sm font-mono text-emerald-600 uppercase tracking-widest mb-6">{{ $t('financial_summary') }}</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
                                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-blue-200 bg-blue-50">
                                    <h4 class="text-xs font-mono text-gray-500 uppercase mb-2">{{ $t('estimated_budget') }}</h4>
                                    <p class="text-4xl font-display font-bold text-blue-600 tracking-tight drop-shadow-sm">
                                        {{ $currency(selectedBuilding.estimated_budget) }}
                                    </p>
                                </div>
                                <div :class="['bg-white border border-gray-200 shadow-sm rounded-xl p-6 border', selectedBuilding.actual_expenses > selectedBuilding.estimated_budget ? 'border-neon-magenta/50 bg-indigo-50' : 'border-emerald-200 bg-emerald-50']">
                                    <h4 class="text-xs font-mono text-gray-500 uppercase mb-2">{{ $t('actual_expenses') }}</h4>
                                    <p :class="['text-4xl font-display font-bold tracking-tight', selectedBuilding.actual_expenses > selectedBuilding.estimated_budget ? 'text-indigo-600 drop-shadow-sm animate-pulse' : 'text-emerald-600 drop-shadow-sm']">
                                        {{ $currency(selectedBuilding.actual_expenses) }}
                                    </p>
                                    <div v-if="selectedBuilding.actual_expenses > selectedBuilding.estimated_budget" class="absolute top-4 right-4 text-xl">⚠️</div>
                                </div>
                            </div>

                            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-gray-200">
                                <h4 class="text-sm font-display text-gray-900 mb-4">{{ $t('budget_health') }}</h4>
                                <div class="h-4 bg-black rounded-full overflow-hidden flex border border-gray-300">
                                    <div class="h-full bg-emerald-600 shadow-sm" :style="{ width: Math.min(100, (selectedBuilding.actual_expenses / (selectedBuilding.estimated_budget || 1)) * 100) + '%' }"></div>
                                    <div v-if="selectedBuilding.actual_expenses > selectedBuilding.estimated_budget" class="h-full bg-indigo-600 shadow-sm animate-pulse" :style="{ width: ((selectedBuilding.actual_expenses - selectedBuilding.estimated_budget) / selectedBuilding.estimated_budget) * 100 + '%' }"></div>
                                </div>
                                <div class="flex justify-between mt-2 text-[10px] font-mono text-gray-500">
                                    <span>$0</span>
                                    <span>{{ $t('target') }}: {{ $currency(selectedBuilding.estimated_budget) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: Analytics -->
                        <div v-if="activeTab === 'analytics'" class="animate-fade-in grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-gray-200">
                                <h3 class="text-sm font-mono text-neon-purple uppercase tracking-widest mb-6">{{ $t('unit_distribution') }}</h3>
                                <VueApexCharts type="donut" height="300" :options="chartOptions" :series="chartSeries" />
                            </div>
                            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-gray-200">
                                <h3 class="text-sm font-mono text-blue-600 uppercase tracking-widest mb-6">{{ $t('construction_velocity') }}</h3>
                                <VueApexCharts type="bar" height="300" :options="progressOptions" :series="progressSeries" />
                            </div>
                        </div>

                    </div>
                </div>
                
                <div v-else class="h-full flex items-center justify-center text-gray-500 font-mono text-sm text-center px-4">
                    {{ $t('select_building_command_center') }}
                </div>
            </div>
        </div>

        <!-- Add/Edit Building Modal -->
        <Modal :show="showAddBuildingModal" @close="showAddBuildingModal = false; isEditingBuilding = false">
            <div class="p-6 w-[600px] max-w-[95vw] overflow-y-auto max-h-[90vh] custom-scrollbar">
                <h2 class="text-xl font-bold text-gray-900 mb-6">{{ isAddingBlock ? 'Yangi Blok Qo\'shish' : (isEditingBuilding ? 'Binoni tahrirlash' : $t('add_new_building')) }}</h2>
                <form @submit.prevent="submitBuilding" class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1">Obyekt Nomi</label>
                        <input v-model="buildingForm.name" type="text" :readonly="isAddingBlock" :class="[isAddingBlock ? 'bg-gray-100 cursor-not-allowed' : 'bg-white', 'border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5']" required>
                    </div>

                    <!-- Location Fields -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Shahar</label>
                            <input v-model="buildingForm.city" type="text" :readonly="isAddingBlock" :class="[isAddingBlock ? 'bg-gray-100 cursor-not-allowed' : 'bg-white', 'border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5']">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Tuman</label>
                            <input v-model="buildingForm.district" type="text" :readonly="isAddingBlock" :class="[isAddingBlock ? 'bg-gray-100 cursor-not-allowed' : 'bg-white', 'border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5']">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Mahalla</label>
                            <input v-model="buildingForm.mahalla" type="text" :readonly="isAddingBlock" :class="[isAddingBlock ? 'bg-gray-100 cursor-not-allowed' : 'bg-white', 'border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5']">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Ko'cha (Mo'ljal)</label>
                            <input v-model="buildingForm.street" type="text" :readonly="isAddingBlock" :class="[isAddingBlock ? 'bg-gray-100 cursor-not-allowed' : 'bg-white', 'border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5']">
                        </div>
                    </div>

                    <!-- Block and Corpus Fields -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Blok Nomi</label>
                            <input v-model="buildingForm.block_name" type="text" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Masalan: A Blok">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Korpus Nomi</label>
                            <input v-model="buildingForm.corpus_name" type="text" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Masalan: 3-Korpus">
                        </div>
                    </div>

                    <!-- Building Details -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('total_floors') }}</label>
                            <input v-model="buildingForm.total_floors" type="number" min="1" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Bloklar (Korpus)</label>
                            <input v-model="buildingForm.blocks_count" type="number" min="1" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Podyezdlar</label>
                            <input v-model="buildingForm.entrances_count" type="number" min="1" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Jami Kvartiralar</label>
                            <input v-model="buildingForm.total_apartments" type="number" min="0" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Jami Do'konlar</label>
                            <input v-model="buildingForm.total_shops" type="number" min="0" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showAddBuildingModal = false; isEditingBuilding = false" class="px-4 py-2 text-xs font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="buildingForm.processing" class="px-4 py-2 bg-blue-600 text-white font-bold font-mono text-xs rounded shadow-sm hover:bg-white transition-colors disabled:opacity-50">
                            {{ isAddingBlock ? 'Blok Qo\'shish' : (isEditingBuilding ? 'Saqlash' : $t('create_building')) }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Add Stage Modal -->
        <Modal :show="showAddStageModal" @close="showAddStageModal = false">
            <div class="p-6">
                <h2 class="text-2xl font-display text-amber-600 mb-4">{{ $t('add_construction_stage') }}</h2>
                <form @submit.prevent="submitStage" class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1">{{ $t('stage_name') }}</label>
                        <input v-model="stageForm.name" type="text" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-neon-yellow transition-all" required :placeholder="$t('stage_name_placeholder')">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">{{ $t('start_date') }}</label>
                            <input v-model="stageForm.start_date" type="date" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-neon-yellow transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">{{ $t('end_date') }}</label>
                            <input v-model="stageForm.end_date" type="date" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-neon-yellow transition-all">
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showAddStageModal = false" class="px-4 py-2 text-xs font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="stageForm.processing" class="px-4 py-2 bg-amber-500 text-white font-bold font-mono text-xs rounded shadow-sm hover:bg-white transition-colors disabled:opacity-50">{{ $t('btn_add_stage') }}</button>
                    </div>
                </form>
            </div>
        </Modal>
        <!-- Add Floor Modal -->
        <Modal :show="showAddFloorModal" @close="showAddFloorModal = false">
            <div class="p-6">
                <h2 class="text-2xl font-display text-gray-900 mb-4">{{ isEditingFloor ? "Qavatni Tahrirlash" : "Qavat Qo'shish" }}</h2>
                <form @submit.prevent="submitFloor" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Qavat Raqami</label>
                            <input v-model="floorForm.floor_number" type="number" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-white focus:ring-1 focus:ring-white transition-all" required placeholder="Masalan: 1, 2, -1">
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Xonadonlar soni</label>
                            <input v-model="floorForm.total_units" type="number" min="0" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-white focus:ring-1 focus:ring-white transition-all" required placeholder="Masalan: 12">
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Do'konlar soni (Ixtiyoriy)</label>
                            <input v-model="floorForm.total_commercial_units" type="number" min="0" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-white focus:ring-1 focus:ring-white transition-all" placeholder="Masalan: 4">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1">Qavat Chizmasi (PDF/JPG)</label>
                        <input type="file" @input="floorForm.blueprint = $event.target.files[0]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-[10px] file:font-mono file:uppercase file:tracking-widest file:bg-gray-100 file:text-gray-900 hover:file:bg-white/20 cursor-pointer border border-gray-200 rounded bg-white">
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" v-if="isEditingFloor && isAdmin" @click="deleteFloor(editingFloorId)" class="mr-auto px-4 py-2 bg-indigo-50 text-indigo-600 font-mono text-xs rounded hover:bg-indigo-600 hover:text-black transition-colors">{{ $t('delete') }}</button>
                        <button type="button" @click="showAddFloorModal = false" class="px-4 py-2 text-xs font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="floorForm.processing" class="px-4 py-2 bg-white text-black font-bold font-mono text-xs rounded hover:bg-white/80 transition-colors disabled:opacity-50">{{ isEditingFloor ? "Saqlash" : "Qo'shish" }}</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Add Unit Modal -->
        <Modal :show="showAddUnitModal" @close="showAddUnitModal = false">
            <div class="p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6">{{ isEditingUnit ? "Xonadonni Tahrirlash" : "Xonadon / Do'kon Qo'shish" }}</h2>
                <form @submit.prevent="submitUnit" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Raqami (Masalan: A501)</label>
                            <input v-model="unitForm.unit_number" type="text" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all" required>
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">{{ $t('type_label') }}</label>
                            <select v-model="unitForm.type" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all" required>
                                <option value="residential">Turar joy (Xonadon)</option>
                                <option value="commercial">Tijorat (Do'kon)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Maydoni (kv.m)</label>
                            <input v-model="unitForm.area_sqm" type="number" step="0.01" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all" required>
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Jami Narxi (USD/UZS)</label>
                            <input v-model="unitForm.price" type="number" step="0.01" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all" required>
                        </div>
                    </div>

                    <!-- Detailed Attributes -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Xonalar</label>
                            <input v-model="unitForm.rooms_count" type="number" min="1" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Oshxona</label>
                            <input v-model="unitForm.kitchens_count" type="number" min="0" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Hammom</label>
                            <input v-model="unitForm.bathrooms_count" type="number" min="0" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Hojatxona</label>
                            <input v-model="unitForm.toilets_count" type="number" min="0" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Status</label>
                            <select v-model="unitForm.status" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all" required>
                                <option value="available">Bo'sh</option>
                                <option value="reserved">Band qilingan</option>
                                <option value="sold">Sotilgan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Joylashuvi (Orientation)</label>
                            <input v-model="unitForm.orientation" type="text" list="orientations" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all" placeholder="Masalan: Burchak, O'rtada">
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
                        <label class="block text-xs font-mono text-gray-500 mb-1">Poligon Koordinatalari (Ixtiyoriy, chizma ustida shakl chizish uchun)</label>
                        <input v-model="unitForm.svg_polygon" type="text" placeholder="e.g. 100,100 200,100 200,200 100,200" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all text-xs font-mono text-gray-500">
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" v-if="isEditingUnit && isAdmin" @click="deleteUnit(editingUnitId)" class="mr-auto px-4 py-2 bg-indigo-50 text-indigo-600 font-mono text-xs rounded hover:bg-indigo-600 hover:text-black transition-colors">{{ $t('delete') }}</button>
                        <button type="button" v-if="isEditingUnit && unitForm.status !== 'sold'" @click="openSellUnit(selectedFloor.units.find(u => u.id === editingUnitId))" class="px-4 py-2 bg-emerald-50 text-emerald-600 border border-emerald-500 font-bold font-mono text-xs rounded hover:bg-emerald-600 hover:text-black transition-colors shadow-sm">
                            Sotish
                        </button>
                        <button type="button" @click="showAddUnitModal = false" class="px-4 py-2 text-xs font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="unitForm.processing" class="px-4 py-2 bg-blue-600 text-white font-bold font-mono text-xs rounded hover:bg-white transition-colors shadow-sm disabled:opacity-50">{{ isEditingUnit ? "Saqlash" : "Qo'shish" }}</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Blueprint Viewer Modal -->
        <Modal :show="showBlueprintModal" @close="showBlueprintModal = false" maxWidth="4xl">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-display text-neon-purple">Qavat Chizmasi</h2>
                    <button @click="showBlueprintModal = false" class="text-gray-900 hover:text-indigo-600 transition-colors">
                        ✕
                    </button>
                </div>
                <div class="w-full max-h-[80vh] overflow-auto flex items-center justify-center bg-white rounded-xl border border-gray-200">
                    <img v-if="selectedFloor?.blueprint_path" :src="`/storage/${selectedFloor.blueprint_path}`" class="max-w-full object-contain" />
                </div>
            </div>
        </Modal>

        <!-- Sell Unit Modal (Create Contract) -->
        <Modal :show="showSellUnitModal" @close="showSellUnitModal = false">
            <div class="p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto custom-scrollbar">
                <h2 class="text-xl font-bold text-gray-900 mb-6 uppercase tracking-widest shadow-neon-green/50">
                    {{ $t('add') }} {{ $t('contract') }}
                </h2>
                <form @submit.prevent="submitContract" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('contract_date_optional') }}</label>
                            <input v-model="contractForm.created_at" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-emerald-600" title="O'tmish sanada shartnoma tuzish uchun">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('contract_number_optional') }}</label>
                            <input v-model="contractForm.contract_number" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-emerald-600" placeholder="Masalan: SH-001">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase">Xaridor Turi</label>
                        <div class="flex gap-4 mb-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.buyer_type" value="client" class="text-blue-600 focus:ring-neon-cyan bg-white border-gray-300">
                                <span class="text-xs text-gray-900">Mijoz (Client)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.buyer_type" value="partner" class="text-amber-600 focus:ring-neon-yellow bg-white border-gray-300">
                                <span class="text-xs text-gray-900 text-amber-600">Ta'sischi Xaridi (Partner)</span>
                            </label>
                        </div>
                        
                        <!-- Client Select -->
                        <SearchableSelect 
                            v-if="contractForm.buyer_type === 'client'" 
                            v-model="contractForm.client_id" 
                            :options="clientsOptions" 
                            placeholder="Mijozni tanlang" 
                            :required="contractForm.buyer_type === 'client'"
                        />
                        
                        <!-- Partner Select -->
                        <SearchableSelect 
                            v-if="contractForm.buyer_type === 'partner'" 
                            v-model="contractForm.partner_id" 
                            :options="partnersOptions" 
                            placeholder="Ta'sischini tanlang" 
                            :required="contractForm.buyer_type === 'partner'"
                        />
                    </div>

                    <div v-if="currentSellingUnit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-3 border border-gray-200 rounded-xl bg-white">
                        <div class="col-span-1 sm:col-span-2">
                            <p class="text-[10px] font-mono text-gray-500 uppercase">{{ $t('object_block') }}</p>
                            <p class="text-xs text-gray-900 font-bold">{{ selectedBuilding?.name || 'Noma\'lum' }} (Xonadon: {{ currentSellingUnit.unit_number }}, Maydon: {{ currentSellingUnit.area_sqm }} kv.m)</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('floor') }}</label>
                            <input v-model="contractForm.floor" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900" placeholder="Qavatni kiriting">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('number_of_rooms') }}</label>
                            <input v-model="contractForm.rooms" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900" placeholder="Xonalar sonini kiriting">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" v-if="currentSellingUnit">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('total_price_with_vat') }}</label>
                            <input v-model="contractForm.total_amount" type="number" step="any" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-emerald-600 font-bold text-lg" required placeholder="Umumiy summani kiriting">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('price_per_sqm_label') }} (Avtomatik)</label>
                            <input v-model="contractForm.price_per_sqm" type="number" step="any" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-white opacity-70 cursor-not-allowed" readonly tabindex="-1">
                        </div>
                    </div>

                    <!-- Payment Type Selection -->
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase">{{ $t('payment_type') }}</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.payment_type" value="full" class="text-blue-600 focus:ring-neon-cyan bg-white border-gray-300">
                                <span class="text-xs text-gray-900">{{ $t('one_time_full_payment') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.payment_type" value="installment" class="text-blue-600 focus:ring-neon-cyan bg-white border-gray-300">
                                <span class="text-xs text-gray-900">{{ $t('installment_payment') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div v-if="contractForm.payment_type === 'installment'">
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('down_payment') }}</label>
                            <input v-model="contractForm.down_payment" type="number" step="any" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                        </div>
                        <div v-if="contractForm.payment_type === 'installment'">
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('term_months') }}</label>
                            <input v-model="contractForm.term_months" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-200 mt-4">
                        <h3 class="text-sm font-bold text-blue-600 uppercase mb-4">Hozirgi Vaqtda To'lov Qabul Qilish (Ixtiyoriy)</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Haqiqatda qilingan to'lov</label>
                                <input v-model="contractForm.actual_paid_amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-amber-600" placeholder="Masalan: 600000000">
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">To'lov Usuli</label>
                                <select v-model="contractForm.payment_method" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-white">
                                    <option value="bank">{{ $t('bank_account') }}</option>
                                    <option value="main">{{ $t('cash_account') }}</option>
                                    <option value="credit">💳 Plastik / Terminal</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 mt-8">
                        <button type="button" @click="showSellUnitModal = false" class="px-4 py-2 text-xs font-mono uppercase tracking-widest text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessingContract" class="px-6 py-2 bg-emerald-600 text-white font-bold uppercase tracking-widest rounded hover:shadow-sm transition-all disabled:opacity-50">{{ $t('create_pipeline') }}</button>
                    </div>
                </form>
            </div>
        </Modal>

    </AdminLayout>
</template>
