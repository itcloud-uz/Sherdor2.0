<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3';
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

const currentObject = ref(props.buildings?.length > 0 ? props.buildings[0].name : null);
const selectedBuilding = ref(null);
const selectedFloor = ref(null);
const activeTab = ref('blueprint');

// Dynamic Building statistics
const maxRoomsCountForBuilding = computed(() => {
    if (!selectedBuilding.value || !selectedBuilding.value.floors) return 0;
    let max = 0;
    selectedBuilding.value.floors.forEach(f => {
        if (f.units) {
            f.units.forEach(u => {
                if (u.type === 'residential') {
                    const rooms = u.rooms_count || 1;
                    if (rooms > max) max = rooms;
                }
            });
        }
    });
    return max || 1;
});

const buildingRoomStats = computed(() => {
    const stats = {};
    if (!selectedBuilding.value || !selectedBuilding.value.floors) return stats;
    selectedBuilding.value.floors.forEach(f => {
        if (f.units) {
            f.units.forEach(u => {
                if (u.type === 'residential') {
                    const rooms = u.rooms_count || 1;
                    stats[rooms] = (stats[rooms] || 0) + 1;
                }
            });
        }
    });
    return stats;
});

// Dynamic room filters
const availableRoomOptions = computed(() => {
    if (!selectedBuilding.value || !selectedBuilding.value.floors) return [1, 2, 3];
    const set = new Set();
    selectedBuilding.value.floors.forEach(f => {
        if (f.units) {
            f.units.forEach(u => {
                if (u.type === 'residential') {
                    set.add(u.rooms_count || 1);
                }
            });
        }
    });
    return Array.from(set).sort((a, b) => a - b);
});

// Filtering variables
const showFilterModal = ref(false);
const tempFilterRooms = ref([]);
const tempFilterMinArea = ref('');
const tempFilterMaxArea = ref('');
const tempFilterMinPrice = ref('');
const tempFilterMaxPrice = ref('');

const activeFilters = ref({
    rooms: [],
    minArea: null,
    maxArea: null,
    minPrice: null,
    maxPrice: null,
    status: null // 'available', 'sold', or 'reserved'
});

const selectedBuildingUnits = computed(() => {
    if (!selectedBuilding.value || !selectedBuilding.value.floors) return [];
    let list = [];
    selectedBuilding.value.floors.forEach(f => {
        if (f.units) {
            list.push(...f.units);
        }
    });
    return list;
});

const minAvailableArea = computed(() => {
    const units = selectedBuildingUnits.value;
    if (units.length === 0) return 0;
    const val = Math.min(...units.map(u => Number(u.area_sqm) || 0));
    return isFinite(val) ? Math.floor(val) : 0;
});

const maxAvailableArea = computed(() => {
    const units = selectedBuildingUnits.value;
    if (units.length === 0) return 1000;
    const val = Math.max(...units.map(u => Number(u.area_sqm) || 0));
    return isFinite(val) ? Math.ceil(val) : 1000;
});

const minAvailablePrice = computed(() => {
    const units = selectedBuildingUnits.value;
    if (units.length === 0) return 0;
    const val = Math.min(...units.map(u => Number(u.price) || 0));
    return isFinite(val) ? Math.floor(val) : 0;
});

const maxAvailablePrice = computed(() => {
    const units = selectedBuildingUnits.value;
    if (units.length === 0) return 1000000;
    const val = Math.max(...units.map(u => Number(u.price) || 0));
    return isFinite(val) ? Math.ceil(val) : 1000000;
});

const areaLeftPercent = computed(() => {
    const min = minAvailableArea.value;
    const max = maxAvailableArea.value;
    if (max <= min) return 0;
    const val = tempFilterMinArea.value === '' ? min : Number(tempFilterMinArea.value);
    return Math.max(0, Math.min(100, ((val - min) / (max - min)) * 100));
});

const areaRightPercent = computed(() => {
    const min = minAvailableArea.value;
    const max = maxAvailableArea.value;
    if (max <= min) return 100;
    const val = tempFilterMaxArea.value === '' ? max : Number(tempFilterMaxArea.value);
    return Math.max(0, Math.min(100, ((val - min) / (max - min)) * 100));
});

const priceLeftPercent = computed(() => {
    const min = minAvailablePrice.value;
    const max = maxAvailablePrice.value;
    if (max <= min) return 0;
    const val = tempFilterMinPrice.value === '' ? min : Number(tempFilterMinPrice.value);
    return Math.max(0, Math.min(100, ((val - min) / (max - min)) * 100));
});

const priceRightPercent = computed(() => {
    const min = minAvailablePrice.value;
    const max = maxAvailablePrice.value;
    if (max <= min) return 100;
    const val = tempFilterMaxPrice.value === '' ? max : Number(tempFilterMaxPrice.value);
    return Math.max(0, Math.min(100, ((val - min) / (max - min)) * 100));
});

function validateAreaRange(isMin) {
    const minLimit = minAvailableArea.value;
    const maxLimit = maxAvailableArea.value;
    let minVal = tempFilterMinArea.value === '' ? minLimit : Number(tempFilterMinArea.value);
    let maxVal = tempFilterMaxArea.value === '' ? maxLimit : Number(tempFilterMaxArea.value);
    
    if (minVal > maxVal) {
        if (isMin) {
            tempFilterMinArea.value = maxVal;
        } else {
            tempFilterMaxArea.value = minVal;
        }
    }
}

function validatePriceRange(isMin) {
    const minLimit = minAvailablePrice.value;
    const maxLimit = maxAvailablePrice.value;
    let minVal = tempFilterMinPrice.value === '' ? minLimit : Number(tempFilterMinPrice.value);
    let maxVal = tempFilterMaxPrice.value === '' ? maxLimit : Number(tempFilterMaxPrice.value);
    
    if (minVal > maxVal) {
        if (isMin) {
            tempFilterMinPrice.value = maxVal;
        } else {
            tempFilterMaxPrice.value = minVal;
        }
    }
}

const hasActiveFilters = computed(() => {
    return activeFilters.value.rooms.length > 0 ||
           (activeFilters.value.minArea !== null && activeFilters.value.minArea !== '') ||
           (activeFilters.value.maxArea !== null && activeFilters.value.maxArea !== '') ||
           (activeFilters.value.minPrice !== null && activeFilters.value.minPrice !== '') ||
           (activeFilters.value.maxPrice !== null && activeFilters.value.maxPrice !== '') ||
           activeFilters.value.status !== null;
});

function toggleStatusFilter(statusVal) {
    if (activeFilters.value.status === statusVal) {
        activeFilters.value.status = null;
    } else {
        activeFilters.value.status = statusVal;
    }
}

function toggleTempRoom(roomNum) {
    const idx = tempFilterRooms.value.indexOf(roomNum);
    if (idx > -1) {
        tempFilterRooms.value.splice(idx, 1);
    } else {
        tempFilterRooms.value.push(roomNum);
    }
}

function openFilterModal() {
    tempFilterRooms.value = [...activeFilters.value.rooms];
    tempFilterMinArea.value = activeFilters.value.minArea !== null ? activeFilters.value.minArea : minAvailableArea.value;
    tempFilterMaxArea.value = activeFilters.value.maxArea !== null ? activeFilters.value.maxArea : maxAvailableArea.value;
    tempFilterMinPrice.value = activeFilters.value.minPrice !== null ? activeFilters.value.minPrice : minAvailablePrice.value;
    tempFilterMaxPrice.value = activeFilters.value.maxPrice !== null ? activeFilters.value.maxPrice : maxAvailablePrice.value;
    showFilterModal.value = true;
}

function applyFilters() {
    activeFilters.value.rooms = [...tempFilterRooms.value];
    activeFilters.value.minArea = tempFilterMinArea.value;
    activeFilters.value.maxArea = tempFilterMaxArea.value;
    activeFilters.value.minPrice = tempFilterMinPrice.value;
    activeFilters.value.maxPrice = tempFilterMaxPrice.value;
    showFilterModal.value = false;
}

function resetFilters() {
    tempFilterRooms.value = [];
    tempFilterMinArea.value = '';
    tempFilterMaxArea.value = '';
    tempFilterMinPrice.value = '';
    tempFilterMaxPrice.value = '';
    activeFilters.value.status = null;
    applyFilters();
}

const showBlueprintFullscreen = ref(false);
const selectedFloorIdForBlueprint = ref(null);

const selectedFloorForBlueprint = computed(() => {
    if (!selectedFloorIdForBlueprint.value || !selectedBuilding.value || !selectedBuilding.value.floors) return null;
    return selectedBuilding.value.floors.find(f => f.id === selectedFloorIdForBlueprint.value) || null;
});

const currentBlueprintPath = computed(() => {
    if (selectedFloorForBlueprint.value && selectedFloorForBlueprint.value.blueprint_path) {
        return selectedFloorForBlueprint.value.blueprint_path;
    }
    return selectedBuilding.value?.blueprint_path || null;
});

function openBlueprintFullscreen() {
    showBlueprintFullscreen.value = true;
}

function isUnitMatchingFilters(unit) {
    if (!unit) return false;
    
    // Status filter
    if (activeFilters.value.status && unit.status !== activeFilters.value.status) {
        return false;
    }
    
    // Rooms count filter
    if (activeFilters.value.rooms.length > 0) {
        const rooms = unit.rooms_count || 1;
        if (!activeFilters.value.rooms.includes(rooms)) return false;
    }
    
    // Area filter
    if (activeFilters.value.minArea !== null && activeFilters.value.minArea !== '') {
        if (unit.area_sqm < Number(activeFilters.value.minArea)) return false;
    }
    if (activeFilters.value.maxArea !== null && activeFilters.value.maxArea !== '') {
        if (unit.area_sqm > Number(activeFilters.value.maxArea)) return false;
    }
    
    // Price filter
    if (activeFilters.value.minPrice !== null && activeFilters.value.minPrice !== '') {
        if (unit.price < Number(activeFilters.value.minPrice)) return false;
    }
    if (activeFilters.value.maxPrice !== null && activeFilters.value.maxPrice !== '') {
        if (unit.price > Number(activeFilters.value.maxPrice)) return false;
    }
    
    return true;
}

const sortedFloors = computed(() => {
    if (!selectedBuilding.value || !selectedBuilding.value.floors) return [];
    return [...selectedBuilding.value.floors].sort((a, b) => a.floor_number - b.floor_number);
});

const formattedCurrentDate = computed(() => {
    const days = ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
    const now = new Date();
    const dayName = days[now.getDay()];
    const date = String(now.getDate()).padStart(2, '0');
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const year = now.getFullYear();
    return `${dayName}, ${date}.${month}.${year}`;
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
                // Autoselect the first building if none is selected
                if (!selectedBuilding.value && props.buildings && props.buildings.length > 0) {
                    const firstBuilding = props.buildings[0];
                    selectedBuilding.value = firstBuilding;
                    currentObject.value = firstBuilding.name;
                }
            } else {
                // If no tab is specified in the URL, reset building selection to show main page
                selectedBuilding.value = null;
                currentObject.value = null;
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

watch(selectedBuilding, (newBuilding, oldBuilding) => {
    selectedFloorIdForBlueprint.value = null;
    if (!newBuilding) {
        selectedFloor.value = null;
        return;
    }
    // Only reset floor to first floor if we switched to a different building
    if (!oldBuilding || newBuilding.id !== oldBuilding.id) {
        if (newBuilding.floors && newBuilding.floors.length > 0) {
            selectedFloor.value = newBuilding.floors[0];
        } else {
            selectedFloor.value = null;
        }
    } else {
        // If same building (updated metadata), sync selectedFloor to the updated floor instance
        if (selectedFloor.value && newBuilding.floors) {
            const updatedFloor = newBuilding.floors.find(f => f.id === selectedFloor.value.id);
            if (updatedFloor) {
                selectedFloor.value = updatedFloor;
            } else {
                selectedFloor.value = newBuilding.floors[0] || null;
            }
        }
    }
});

// Reactively update selectedBuilding when props.buildings updates from the server
watch(
    () => props.buildings,
    (newBuildings) => {
        if (selectedBuilding.value && newBuildings) {
            const updated = newBuildings.find(b => b.id === selectedBuilding.value.id);
            if (updated) {
                selectedBuilding.value = updated;
                // If selectedUnit is open, sync it with the fresh data from the building
                if (selectedUnit.value) {
                    let found = null;
                    if (updated.floors) {
                        updated.floors.forEach(f => {
                            if (f.units) {
                                const u = f.units.find(unit => unit.id === selectedUnit.value.id);
                                if (u) found = u;
                            }
                        });
                    }
                    if (found) {
                        selectedUnit.value = found;
                    }
                }
            }
        }
    },
    { deep: true }
);

// Watch total_floors and basement_floors to sync floors_data list
watch(
    [() => buildingForm.total_floors, () => buildingForm.basement_floors],
    ([totalFloors, basementFloors]) => {
        const tFloors = parseInt(totalFloors) || 0;
        const bFloors = parseInt(basementFloors) || 0;

        const floorNumbers = [];
        for (let b = -bFloors; b <= -1; b++) {
            floorNumbers.push(b);
        }
        const aboveGroundCount = tFloors - bFloors;
        for (let a = 1; a <= aboveGroundCount; a++) {
            floorNumbers.push(a);
        }

        const currentData = buildingForm.floors_data || [];
        const currentMap = {};
        currentData.forEach(item => {
            currentMap[item.floor_number] = item;
        });

        buildingForm.floors_data = floorNumbers.map(f => {
            const existing = currentMap[f];
            const isCommercial = (f <= 1);
            if (existing) {
                existing.shops_count = isCommercial ? (existing.shops_count || 0) : 0;
                existing.apartments_count = !isCommercial ? (existing.apartments_count || 0) : 0;
                return existing;
            }
            return {
                floor_number: f,
                shops_count: 0,
                apartments_count: 0
            };
        });
    }
);



// Recalculate total_shops and total_apartments from floors_data sum
watch(
    () => buildingForm.floors_data,
    (floors) => {
        if (!floors) return;
        let sumShops = 0;
        let sumApartments = 0;
        floors.forEach(item => {
            sumShops += parseInt(item.shops_count) || 0;
            sumApartments += parseInt(item.apartments_count) || 0;
        });
        buildingForm.total_shops = sumShops;
        buildingForm.total_apartments = sumApartments;
    },
    { deep: true }
);

function onTotalShopsInput(e) {
    const val = parseInt(e.target.value) || 0;
    if (!buildingForm.floors_data) return;
    const commFloors = buildingForm.floors_data.filter(item => item.floor_number <= 1);
    const C = commFloors.length;
    if (C > 0) {
        const base = Math.floor(val / C);
        let remainder = val % C;
        commFloors.forEach(item => {
            item.shops_count = base + (remainder-- > 0 ? 1 : 0);
        });
    }
}

function onTotalApartmentsInput(e) {
    const val = parseInt(e.target.value) || 0;
    if (!buildingForm.floors_data) return;
    const resFloors = buildingForm.floors_data.filter(item => item.floor_number >= 2);
    const R = resFloors.length;
    if (R > 0) {
        const base = Math.floor(val / R);
        let remainder = val % R;
        resFloors.forEach(item => {
            item.apartments_count = base + (remainder-- > 0 ? 1 : 0);
        });
    }
}



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
            const floorLabel = floorNum < 0 ? `${floorNum} yerto'la` : `${floorNum}-qavat`;
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
                `"${floorLabel}"`,
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
    
    activeRightPanel.value = 'sell';
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
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            showSellUnitModal.value = false;
            isProcessingContract.value = false;
            activeRightPanel.value = 'details';
            selectedUnit.value = null; // Close side panel to force reload details on re-click
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
    shops_per_floor: 0,
    apartments_per_floor: 0,
    estimated_budget: 0,
    blueprint: null,
    model_3d: null,
    basement_floors: 0,
    photo: null,
    is_object_only: 0,
    floors_data: [],
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
    orientation: '',
    podyezd: 1,
});

const isEditingBuilding = ref(false);
const isAddingBlock = ref(false);

const showUploadModelsModal = ref(false);
const uploadModelsForm = useForm({
    building_id: '',
    blueprint: null,
    model_3d: null,
});

function openUploadModelsModal() {
    uploadModelsForm.reset();
    const blocks = groupedObjects.value[currentObject.value] || [];
    if (blocks.length > 0) {
        uploadModelsForm.building_id = blocks[0].id;
    }
    showUploadModelsModal.value = true;
}

function submitUploadModels() {
    if (!uploadModelsForm.building_id) return;
    // Set other form fields if we need to preserve unchanged values, but updateBuilding validation allows just these
    const selectedObj = props.buildings.find(b => b.id === uploadModelsForm.building_id);
    if (selectedObj) {
        uploadModelsForm.name = selectedObj.name;
        uploadModelsForm.total_floors = selectedObj.total_floors;
    }
    uploadModelsForm.transform((data) => ({
        ...data,
        _method: 'put'
    })).post(route('admin.facility.buildings.update', uploadModelsForm.building_id), {
        onSuccess: () => {
            showUploadModelsModal.value = false;
            uploadModelsForm.reset();
        }
    });
}

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
    buildingForm.total_shops = selectedBuilding.value.total_shops || 0;
    buildingForm.total_apartments = selectedBuilding.value.total_apartments || 0;
    buildingForm.shops_per_floor = selectedBuilding.value.shops_per_floor || 0;
    buildingForm.apartments_per_floor = selectedBuilding.value.apartments_per_floor || 0;
    buildingForm.estimated_budget = selectedBuilding.value.estimated_budget;
    buildingForm.blueprint = null;
    buildingForm.model_3d = null;
    buildingForm.photo = null;
    buildingForm.is_object_only = selectedBuilding.value.total_floors === 0 ? 1 : 0;
    buildingForm.basement_floors = selectedBuilding.value.basement_floors || 0;
    buildingForm.block_name = selectedBuilding.value.block_name || '';
    buildingForm.corpus_name = selectedBuilding.value.corpus_name || '';
    buildingForm.length_m = selectedBuilding.value.length_m || '';
    buildingForm.width_m = selectedBuilding.value.width_m || '';
    buildingForm.height_m = selectedBuilding.value.height_m || '';
    buildingForm.units_per_floor = 0;
    buildingForm.average_area = 0;
    buildingForm.average_price = 0;
    buildingForm.commercial_units_first_floor = 0;
    buildingForm.floors_data = (selectedBuilding.value.floors || []).map(f => {
        const isCommercial = (f.floor_number <= 1);
        return {
            floor_number: f.floor_number,
            shops_count: isCommercial ? (f.total_commercial_units || 0) : 0,
            apartments_count: !isCommercial ? (f.total_units || 0) : 0
        };
    });
    showAddBuildingModal.value = true;
}

function openEditObjectContainer(blocksList) {
    const container = (blocksList || []).find(b => b.total_floors === 0);
    if (!container) return;
    selectedBuilding.value = container;
    openEditBuilding();
}

function deleteBuilding() {
    if (confirm("Rostdan ham bu binoni o'chirmoqchimisiz? Ushbu amaliyot barcha qavatlar va xonadonlarni o'chirib yuboradi!")) {
        router.delete(route('admin.facility.buildings.destroy', selectedBuilding.value.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                selectedBuilding.value = null;
            }
        });
    }
}

function submitBuilding() {
    if (isEditingBuilding.value && selectedBuilding.value) {
        // use _method: 'put' for file upload with PUT in laravel
        buildingForm.transform((data) => ({
            ...data,
            is_object_only: 0,
            _method: 'put'
        })).post(route('admin.facility.buildings.update', selectedBuilding.value.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                showAddBuildingModal.value = false;
                isEditingBuilding.value = false;
                buildingForm.reset();
            },
            onError: (errors) => {
                alert("Xatolik yuz berdi:\n" + Object.values(errors).join("\n"));
            }
        });
    } else {
        buildingForm.transform((data) => ({
            ...data,
            is_object_only: isAddingBlock.value ? 0 : 1
        })).post(route('admin.facility.buildings.store'), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                showAddBuildingModal.value = false;
                buildingForm.reset();
                if (props.buildings.length === 1) {
                    selectedBuilding.value = props.buildings[0];
                }
            },
            onError: (errors) => {
                alert("Xatolik yuz berdi:\n" + Object.values(errors).join("\n"));
            }
        });
    }
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
            preserveState: true,
            preserveScroll: true,
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
            preserveState: true,
            preserveScroll: true,
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
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                if (selectedFloor.value?.id === floorId) {
                    selectedFloor.value = selectedBuilding.value?.floors?.length > 0 ? selectedBuilding.value.floors[0] : null;
                }
            }
        });
    }
}

const selectedUnit = ref(null);
const activeRightPanel = ref('details'); // 'details' or 'calculator'
const calcPrice = ref(0);
const calcDownPayment = ref(0);
const calcTermMonths = ref(12);
const calcDiscount = ref(0);
const selectedUnitForFullscreenBlueprint = ref(null);

function selectUnit(unit) {
    selectedUnit.value = unit;
    activeRightPanel.value = 'details';
    
    // Find and set the selectedFloor
    if (selectedBuilding.value?.floors) {
        const floor = selectedBuilding.value.floors.find(f => f.id === unit.floor_id);
        selectedFloor.value = floor;
    }
    
    // Set calculator inputs
    calcPrice.value = unit.price || 0;
    calcDownPayment.value = 0;
    calcTermMonths.value = 12;
    calcDiscount.value = 0;
}

function setDownPaymentPercent(percent) {
    calcDownPayment.value = Math.round(Number(calcPrice.value || 0) * (percent / 100));
}

const calcDownPaymentPercent = computed(() => {
    const price = Number(calcPrice.value) || 0;
    if (price <= 0) return 0;
    const dp = Number(calcDownPayment.value) || 0;
    const pct = (dp / price) * 100;
    return pct % 1 === 0 ? pct.toFixed(0) : pct.toFixed(1);
});

const calculatedTotalAmount = computed(() => {
    return Math.max(0, Number(calcPrice.value || 0) - Number(calcDiscount.value || 0));
});

const calculatedRemainingBalance = computed(() => {
    return Math.max(0, calculatedTotalAmount.value - Number(calcDownPayment.value || 0));
});

const calculatedMonthlyPayment = computed(() => {
    const months = Number(calcTermMonths.value || 1);
    if (months <= 0) return 0;
    return Math.round(calculatedRemainingBalance.value / months);
});

const calculatedGrandTotal = computed(() => {
    return Number(calcDownPayment.value || 0) + calculatedRemainingBalance.value;
});

function formatSoM(val) {
    const num = Math.max(0, Math.round(Number(val) || 0));
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " so'm";
}

const selectedUnitBlueprint = computed(() => {
    if (!selectedUnit.value || !selectedBuilding.value || !selectedBuilding.value.floors) return '/modern_apartment_render.png';
    const fl = selectedBuilding.value.floors.find(f => f.id === selectedUnit.value.floor_id);
    return fl?.blueprint_path ? `/storage/${fl.blueprint_path}` : '/modern_apartment_render.png';
});

const fullscreenBlueprintSrc = computed(() => {
    if (selectedUnitForFullscreenBlueprint.value) {
        const fl = selectedBuilding.value?.floors?.find(f => f.id === selectedUnitForFullscreenBlueprint.value.floor_id);
        return fl?.blueprint_path ? `/storage/${fl.blueprint_path}` : '/modern_apartment_render.png';
    }
    return currentBlueprintPath.value ? `/storage/${currentBlueprintPath.value}` : '/modern_apartment_render.png';
});

function openBlueprintFullscreenForUnit() {
    if (selectedUnit.value) {
        selectedUnitForFullscreenBlueprint.value = selectedUnit.value;
        showBlueprintFullscreen.value = true;
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
    unitForm.podyezd = unit.podyezd || 1;
    activeRightPanel.value = 'edit_unit';
}

function cancelEditUnit() {
    isEditingUnit.value = false;
    editingUnitId.value = null;
    unitForm.reset();
    activeRightPanel.value = 'details';
}

function submitUnit() {
    if (isEditingUnit.value && editingUnitId.value) {
        unitForm.transform((data) => ({
            ...data,
            _method: 'put'
        })).post(route('admin.facility.units.update', editingUnitId.value), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                showAddUnitModal.value = false;
                isEditingUnit.value = false;
                editingUnitId.value = null;
                unitForm.reset();
                activeRightPanel.value = 'details';
            }
        });
    } else {
        unitForm.floor_id = selectedFloor.value?.id;
        unitForm.transform((data) => data).post(route('admin.facility.units.store'), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                showAddUnitModal.value = false;
                unitForm.reset();
                activeRightPanel.value = 'details';
            }
        });
    }
}

function deleteUnit(unitId) {
    if (confirm("Rostdan ham bu xonadon/do'konni o'chirmoqchimisiz?")) {
        router.delete(route('admin.facility.units.destroy', unitId), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                showAddUnitModal.value = false;
                isEditingUnit.value = false;
                editingUnitId.value = null;
                unitForm.reset();
                selectedUnit.value = null; // Close side panel
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
    if (status === 'available') return 'border-green-600 shadow-sm';
    if (status === 'reserved') return 'border-yellow-600 shadow-sm';
    if (status === 'sold') return 'border-red-600 shadow-sm';
    return 'border-gray-300';
}

function getUnitFillColor(status) {
    if (status === 'available') return '#22c55e'; // Green-500
    if (status === 'reserved') return '#eab308'; // Yellow-500
    if (status === 'sold') return '#ef4444'; // Red-500
    return '#f3f4f6';
}

function getUnitStrokeColor(status) {
    if (status === 'available') return '#16a34a'; // Green-600
    if (status === 'reserved') return '#ca8a04'; // Yellow-600
    if (status === 'sold') return '#dc2626'; // Red-600
    return '#e5e7eb';
}

function getUnitTextColor(status) {
    if (status === 'available') return 'text-green-600 font-bold';
    if (status === 'reserved') return 'text-yellow-600 font-bold';
    if (status === 'sold') return 'text-red-600 font-bold';
    return 'text-gray-900';
}

function getUnitsByFloorAndPodyezd(floor, podyezdNumber) {
    if (!floor || !floor.units) return [];
    return floor.units.filter(u => (u.podyezd || 1) === podyezdNumber).sort((a, b) => {
        const numA = String(a.unit_number).replace(/\D/g, '');
        const numB = String(b.unit_number).replace(/\D/g, '');
        return (Number(numA) || 0) - (Number(numB) || 0);
    });
}

function getObjectPhoto(blocks) {
    if (!blocks || !blocks.length) return '/modern_apartment_render.png';
    const withPhoto = blocks.find(b => b.photo_path);
    if (withPhoto) {
        return `/storage/${withPhoto.photo_path}`;
    }
    return '/modern_apartment_render.png';
}

const modalTitle = computed(() => {
    if (isAddingBlock.value) return 'YANGI BLOK QO\'SHISH';
    if (isEditingBuilding.value) return 'BINONI TAHRIRLASH';
    return 'YANGI OBYEKT QO\'SHISH';
});

const submitButtonLabel = computed(() => {
    if (isAddingBlock.value) return 'Blok Qo\'shish';
    if (isEditingBuilding.value) return 'Saqlash';
    return 'Bino Yaratish';
});

function handleFileChange(formRef, field, event, maxSizeMb) {
    const file = event.target.files[0];
    if (!file) {
        formRef[field] = null;
        return;
    }
    const sizeMb = file.size / (1024 * 1024);
    if (sizeMb > maxSizeMb) {
        alert(`Fayl hajmi juda katta! Maksimal ruxsat etilgan hajm: ${maxSizeMb} MB. (Siz tanlagan fayl: ${sizeMb.toFixed(2)} MB)\n\nIltimos, fayl hajmini kamaytiring yoki serverning Nginx (client_max_body_size) sozlamalarini oshiring.`);
        event.target.value = ''; // reset file input element
        formRef[field] = null;
    } else {
        formRef[field] = file;
    }
}
</script>

<template>
    <Head :title="$t('facility_command_center') || 'Facility Command Center'" />
    <AdminLayout>
        <!-- Dynamic Breadcrumbs -->
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6 font-sans">
            <button @click="currentObject = null; selectedBuilding = null" class="hover:text-blue-600 transition-colors flex items-center gap-1.5 font-medium">
                🏠 Asosiy
            </button>
            <span class="text-gray-300">/</span>
            <button @click="currentObject = null; selectedBuilding = null" class="hover:text-blue-600 transition-colors font-medium">
                Obyektlar
            </button>
            <template v-if="currentObject">
                <span class="text-gray-300">/</span>
                <button @click="selectedBuilding = null" class="hover:text-blue-600 transition-colors font-medium">
                    {{ currentObject }}
                </button>
            </template>
            <template v-if="selectedBuilding">
                <span class="text-gray-300">/</span>
                <span class="text-gray-400">Bloklar</span>
                <span class="text-gray-300">/</span>
                <span class="text-gray-800 font-semibold">{{ selectedBuilding.block_name || selectedBuilding.corpus_name || 'Asosiy Blok' }}</span>
            </template>
        </nav>

        <div class="w-full flex flex-col min-h-[600px] font-sans">
            
            <!-- VIEW 1: Objects Page (If currentObject is null) -->
            <div v-if="currentObject === null" class="space-y-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">Mavjud Obyektlar</h2>
                    <button @click="showAddBuildingModal = true; isEditingBuilding = false; isAddingBlock = false; buildingForm.reset();" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-xl shadow-sm transition-all">
                        + Yangi Obyekt
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="(blocks, objName) in groupedObjects" :key="objName" 
                         @click="currentObject = objName; selectedBuilding = null"
                         class="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden cursor-pointer hover:shadow-md transition-all group relative">
                        <div class="h-48 overflow-hidden bg-gray-100 relative">
                            <img :src="getObjectPhoto(blocks)" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>
                            <div class="absolute bottom-4 left-4 text-white">
                                <h3 class="text-lg font-bold uppercase tracking-wide">{{ objName }}</h3>
                                <p class="text-xs opacity-90 font-mono">{{ blocks.filter(b => b.total_floors > 0).length }} ta blok / korpus</p>
                            </div>
                        </div>
                        <div class="p-4 flex justify-between items-center bg-gray-50 border-t border-gray-100">
                            <span class="text-xs font-semibold text-blue-600 group-hover:underline">Boshqarish →</span>
                        </div>
                    </div>
                    
                    <div v-if="Object.keys(groupedObjects).length === 0" class="col-span-full bg-white border border-dashed border-gray-300 rounded-2xl p-12 text-center text-gray-500">
                        Hech qanday obyekt topilmadi. Yuqoridagi "+ Yangi Obyekt" orqali obyekt kiritishingiz mumkin.
                    </div>
                </div>
            </div>

            <!-- VIEW 2: Object Detail View (3rd screenshot) -->
            <div v-else-if="currentObject !== null && selectedBuilding === null" class="space-y-6">
                <!-- Large banner with photo, showroom toggle, and Bino qo'shish button -->
                <div class="relative h-64 rounded-2xl overflow-hidden shadow-sm">
                    <img :src="getObjectPhoto(groupedObjects[currentObject] || [])" class="w-full h-full object-cover" />
                    <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/40 to-transparent"></div>
                    <div class="absolute inset-0 p-8 flex flex-col justify-between text-white">
                        <div class="flex justify-between items-start w-full">
                            <div></div>
                            <!-- Controls on the right -->
                            <div class="flex items-center gap-3 bg-black/30 p-2 rounded-xl backdrop-blur-sm border border-white/10">
                                <div class="flex items-center gap-2 text-xs font-bold px-2">
                                    <span class="opacity-75">2d</span>
                                    <div class="w-10 h-5 bg-amber-500 rounded-full p-0.5 cursor-pointer relative transition-colors">
                                        <div class="w-4 h-4 bg-white rounded-full absolute right-0.5 shadow-md"></div>
                                    </div>
                                    <span>3d</span>
                                </div>
                                <button class="px-3 py-1.5 bg-white/15 hover:bg-white/25 border border-white/10 text-white text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5">
                                    👁️ Showroom
                                </button>
                                <button @click="openUploadModelsModal()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-all shadow-sm flex items-center gap-1">
                                    📁 Modellar yuklash
                                </button>
                                <button @click="openEditObjectContainer(groupedObjects[currentObject])" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition-all shadow-sm flex items-center gap-1">
                                    ✏️ Obyektni tahrirlash
                                </button>
                                <button @click="openAddBlock(currentObject)" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg transition-all shadow-sm">
                                    + Yangi Blok
                                </button>
                            </div>
                        </div>
                        <div>
                            <h1 class="text-4xl font-extrabold tracking-tight">{{ currentObject }}</h1>
                            <span class="inline-flex items-center gap-1.5 bg-emerald-500 text-white text-xs font-bold px-3 py-1 rounded-full mt-2 shadow-sm">
                                <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                                Ish holatida
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Grid of Blocks under this Object -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-gray-800 uppercase tracking-wider">Bloklar / Korpuslar</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div v-for="building in (groupedObjects[currentObject] || []).filter(b => b.total_floors > 0)" :key="building.id" 
                             @click="selectedBuilding = building"
                             class="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden cursor-pointer hover:shadow-md transition-all group relative">
                            <div class="h-40 overflow-hidden bg-gray-100 relative">
                                <img :src="building.photo_path ? `/storage/${building.photo_path}` : getObjectPhoto(groupedObjects[currentObject] || [])" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                                <div class="absolute bottom-3 left-3 text-white">
                                    <h4 class="font-bold uppercase tracking-wider text-sm">{{ building.block_name || building.corpus_name || 'Asosiy Blok' }}</h4>
                                </div>
                            </div>
                            <div class="p-3 flex justify-between items-center bg-gray-50 border-t border-gray-100">
                                <span class="text-xs text-gray-500 font-medium">Qavatlar: {{ building.total_floors }}</span>
                                <button @click.stop="selectedBuilding = building; openEditBuilding();" class="px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded shadow-sm transition-all">
                                    Tahrirlash
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="(groupedObjects[currentObject] || []).filter(b => b.total_floors > 0).length === 0" class="bg-white border border-dashed border-gray-300 rounded-2xl p-12 text-center text-gray-500">
                        Ushbu obyektda hali hech qanday blok qo'shilmagan. Yuqoridagi "+ Yangi Blok" orqali blok kiritishingiz mumkin.
                    </div>
                </div>
            </div>

            <!-- VIEW 3: Block Detail View with Chessboard (4th screenshot) -->
            <div v-else-if="selectedBuilding !== null" class="space-y-8">
                
                <!-- Top details section: Building details card + 2D blueprint panel -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left: Building Card & Stats Table side-by-side -->
                    <div class="lg:col-span-2 bg-white border border-gray-200 shadow-sm rounded-2xl p-6 flex flex-col md:flex-row gap-6">
                        <!-- Building image on the left -->
                        <div class="w-full md:w-3/5 flex flex-col gap-4">
                            <div class="h-[280px] md:h-[320px] overflow-hidden bg-gray-100 rounded-xl relative shadow-inner">
                                <img :src="selectedBuilding.photo_path ? `/storage/${selectedBuilding.photo_path}` : getObjectPhoto(groupedObjects[currentObject] || [])" class="w-full h-full object-cover" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                                <div class="absolute bottom-4 left-4 text-white">
                                    <h3 class="text-xl font-bold uppercase tracking-wider">{{ selectedBuilding.block_name || selectedBuilding.corpus_name || '1 Bino' }}</h3>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button @click="openEditBuilding" class="flex-1 py-2 border border-gray-200 text-gray-700 bg-white text-xs font-sans rounded-xl hover:bg-gray-50 transition-all font-bold text-center">
                                    ✏️ Tahrirlash
                                </button>
                                <button v-if="isAdmin" @click="deleteBuilding" class="py-2 px-3 bg-red-50 text-red-600 border border-red-200 text-xs font-sans rounded-xl hover:bg-red-600 hover:text-white transition-all font-bold">
                                    🗑️ O'chirish
                                </button>
                            </div>
                        </div>

                        <!-- Building stats list on the right -->
                        <div class="w-full md:w-2/5 flex flex-col justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4 font-sans">Bino haqidagi ma'lumot</h3>
                                <div class="space-y-2 text-sm font-sans">
                                    <div class="flex justify-between border-b border-gray-100 py-1.5">
                                        <span class="text-gray-500">Qavatlar</span>
                                        <span class="text-gray-900 font-bold">{{ selectedBuilding.total_floors }}</span>
                                    </div>
                                    <div v-if="selectedBuilding.basement_floors > 0" class="flex justify-between border-b border-gray-100 py-1.5">
                                        <span class="text-gray-500">Yerto'lalar</span>
                                        <span class="text-gray-900 font-bold">{{ selectedBuilding.basement_floors }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-gray-100 py-1.5">
                                        <span class="text-gray-500">Podezdlar</span>
                                        <span class="text-gray-900 font-bold">{{ selectedBuilding.entrances_count || 1 }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-gray-100 py-1.5">
                                        <span class="text-gray-500">Kvartiralar</span>
                                        <span class="text-gray-900 font-bold">{{ selectedBuilding.total_apartments || 0 }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-gray-100 py-1.5">
                                        <span class="text-gray-500">Do'konlar</span>
                                        <span class="text-gray-900 font-bold">{{ selectedBuilding.total_shops || 0 }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-gray-100 py-1.5">
                                        <span class="text-gray-500">1-xonali</span>
                                        <span class="text-gray-900 font-bold">{{ buildingRoomStats[1] || 0 }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-gray-100 py-1.5">
                                        <span class="text-gray-500">2-xonali</span>
                                        <span class="text-gray-900 font-bold">{{ buildingRoomStats[2] || 0 }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-gray-100 py-1.5">
                                        <span class="text-gray-500">3-xonali</span>
                                        <span class="text-gray-900 font-bold">{{ buildingRoomStats[3] || 0 }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-gray-100 py-1.5">
                                        <span class="text-gray-500">4-xonali</span>
                                        <span class="text-gray-900 font-bold">{{ buildingRoomStats[4] || 0 }}</span>
                                    </div>
                                    <div v-for="(count, rooms) in buildingRoomStats" :key="rooms">
                                        <div v-if="rooms != 1 && rooms != 2 && rooms != 3 && rooms != 4" class="flex justify-between border-b border-gray-100 py-1.5">
                                            <span class="text-gray-500">{{ rooms }}-xonali</span>
                                            <span class="text-gray-900 font-bold">{{ count }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: 2D Blueprint Panel -->
                    <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 flex flex-col justify-between relative min-h-[280px]">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest font-sans">2D Chizma</h3>
                            <div class="flex items-center gap-2">
                                <select v-if="selectedBuilding.floors?.length" 
                                        v-model="selectedFloorIdForBlueprint" 
                                        class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg px-2.5 py-1 focus:ring-amber-500 focus:border-amber-500 font-sans">
                                    <option :value="null">Bino Chizmasi</option>
                                    <option v-for="floor in sortedFloors" :key="'opt-'+floor.id" :value="floor.id">
                                        {{ floor.floor_number < 0 ? `${floor.floor_number} yerto'la` : `${floor.floor_number}-qavat` }}
                                    </option>
                                </select>
                                <button @click="openBlueprintFullscreen" class="text-gray-400 hover:text-amber-500 hover:scale-110 transition-all" title="Kattalashtirish">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex-1 flex items-center justify-center overflow-hidden bg-gray-50 rounded-xl relative border border-gray-100 p-2 min-h-[180px]">
                            <img :src="currentBlueprintPath ? `/storage/${currentBlueprintPath}` : '/modern_apartment_render.png'" 
                                 class="max-h-full max-w-full object-contain cursor-pointer hover:scale-102 transition-transform duration-300"
                                 @click="openBlueprintFullscreen" />
                        </div>
                    </div>
                </div>

                <!-- Horizontal Filter & Status Legend Row -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <button @click="openFilterModal" class="px-5 py-2.5 bg-[#f59e0b] hover:bg-amber-600 text-white font-bold font-sans text-sm rounded-full shadow-sm transition-all flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            Filter
                        </button>
                        <button v-if="hasActiveFilters" @click="resetFilters" class="px-4 py-2.5 border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 font-bold font-sans text-sm rounded-full shadow-sm transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                            <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Filterni tozalash
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-6 text-sm font-sans">
                        <button @click="toggleStatusFilter('available')" 
                                :class="['flex items-center gap-3 font-medium transition-all p-1.5 rounded-lg border', 
                                         activeFilters.status === 'available' ? 'bg-emerald-50 border-emerald-200 text-emerald-800 scale-102 font-bold' : 'border-transparent text-gray-700 hover:bg-gray-50']">
                            <span class="w-4 h-4 rounded-full bg-[#00c853] block shadow-sm shrink-0"></span> Sotuvda
                        </button>
                        <button @click="toggleStatusFilter('sold')" 
                                :class="['flex items-center gap-3 font-medium transition-all p-1.5 rounded-lg border', 
                                         activeFilters.status === 'sold' ? 'bg-red-50 border-red-200 text-red-800 scale-102 font-bold' : 'border-transparent text-gray-700 hover:bg-gray-50']">
                            <span class="w-4 h-4 rounded-full bg-[#ef4444] block shadow-sm shrink-0"></span> Sotilgan
                        </button>
                        <button @click="toggleStatusFilter('reserved')" 
                                :class="['flex items-center gap-3 font-medium transition-all p-1.5 rounded-lg border', 
                                         activeFilters.status === 'reserved' ? 'bg-amber-50 border-amber-200 text-amber-800 scale-102 font-bold' : 'border-transparent text-gray-700 hover:bg-gray-50']">
                            <span class="w-4 h-4 rounded-full bg-[#ffd600] block shadow-sm shrink-0"></span> Bron qilingan
                        </button>
                    </div>
                </div>

                <!-- Tabs and Tab Contents -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
                    <!-- Tab selectors -->
                    <div class="flex border-b border-gray-100 mb-6 gap-6 text-sm font-sans overflow-x-auto pb-1">
                        <button @click="activeTab = 'blueprint'" :class="[activeTab === 'blueprint' ? 'border-amber-500 text-amber-600 font-bold border-b-2 pb-3' : 'text-gray-500 hover:text-gray-700 pb-3']">
                            Shaxmatka
                        </button>
                        <button @click="activeTab = 'search'" :class="[activeTab === 'search' ? 'border-amber-500 text-amber-600 font-bold border-b-2 pb-3' : 'text-gray-500 hover:text-gray-700 pb-3']">
                            Qidiruv
                        </button>
                    </div>

                    <!-- Tab Content Area -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Left: Tab Contents (Chessboard / Search) -->
                        <div :class="selectedUnit ? 'lg:col-span-2' : 'lg:col-span-3'">
                            <!-- Tab 1: Blueprint / Chessboard -->
                            <div v-if="activeTab === 'blueprint'" class="animate-fade-in">
                                <div class="flex justify-between items-center gap-4 mb-6">
                                    <h4 class="text-sm font-bold font-sans text-gray-800">Xonadonlar Shaxmatkasi</h4>
                                    <div class="flex items-center gap-2">
                                        <button @click="downloadUnitsCSV" class="px-3 py-1.5 bg-emerald-50 border border-emerald-200 text-emerald-600 text-xs font-sans rounded hover:bg-emerald-600 hover:text-white transition-all flex items-center gap-2 shadow-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                            EXCEL YUKLAB OLISH
                                        </button>
                                        <button @click="showAddFloorModal = true; isEditingFloor = false" class="px-3 py-1.5 border border-gray-300 text-gray-700 text-xs font-sans rounded hover:bg-gray-50 hover:text-black transition-all">
                                            + QAVAT
                                        </button>
                                    </div>
                                </div>

                                <!-- Chessboard Grid -->
                                <div class="overflow-x-auto pb-4">
                                    <div v-if="selectedBuilding.floors?.length" class="flex flex-row gap-8 min-w-max p-1">
                                        <div v-for="p in selectedBuilding.entrances_count || 1" :key="'podyezd-'+p" class="flex flex-col min-w-[320px] max-w-[480px] overflow-x-auto pb-4 scrollbar-thin">
                                            <h4 class="text-sm font-medium text-gray-800 font-sans mb-6 text-center">
                                                {{ p }}-padezd
                                            </h4>
                                            <div class="space-y-4">
                                                <div v-for="floor in sortedFloors" :key="'floor-'+p+'-'+floor.id" class="flex items-center gap-4 group/floor-row">
                                                    <div class="w-24 flex flex-col shrink-0 justify-center">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-[10px] font-sans font-bold text-amber-600 uppercase tracking-wider">{{ floor.floor_number < 0 ? `${floor.floor_number} YERTO'LA` : `${floor.floor_number}-QAVAT` }}</span>
                                                            <div class="flex gap-1.5 opacity-0 group-hover/floor-row:opacity-100 transition-opacity">
                                                                <button @click="openEditFloor(floor)" class="text-[10px] hover:scale-110 transition-transform" title="Tahrirlash">✏️</button>
                                                                <button @click="selectedFloor = floor; unitForm.podyezd = p; showAddUnitModal = true; isEditingUnit = false" class="text-[10px] hover:scale-110 font-bold text-blue-600 transition-transform" title="Xonadon qo'shish">+</button>
                                                            </div>
                                                        </div>
                                                        <span class="text-[9px] font-sans text-amber-600 font-semibold mt-0.5">
                                                            <template v-if="floor.floor_number <= 1">
                                                                {{ floor.total_commercial_units || 0 }} ta do'kon
                                                            </template>
                                                            <template v-else>
                                                                {{ floor.total_units || 0 }} ta uy
                                                            </template>
                                                        </span>
                                                    </div>

                                                    <div class="flex gap-2 items-center">
                                                        <div v-for="unit in getUnitsByFloorAndPodyezd(floor, p)" :key="'unit-'+unit.id"
                                                             class="unit-box w-12 h-12 flex flex-col items-center justify-center cursor-pointer rounded-lg shadow-sm relative shrink-0 transition-all duration-300"
                                                             :class="['status-' + unit.status, { 'opacity-15 pointer-events-none scale-95': !isUnitMatchingFilters(unit), 'ring-4 ring-amber-500 scale-105 z-20': selectedUnit && selectedUnit.id === unit.id }]"
                                                             @mousemove="(e) => onUnitHover(unit, e)"
                                                             @mouseleave="onUnitLeave"
                                                             @click="selectUnit(unit)">
                                                            <span class="font-sans text-xs font-bold pointer-events-none z-10 text-white">
                                                                {{ (unit.type === 'commercial' && !String(unit.unit_number).startsWith('D') ? 'D-' : '') + unit.unit_number }}
                                                            </span>
                                                        </div>
                                                        <div v-if="!getUnitsByFloorAndPodyezd(floor, p).length" class="text-[10px] font-sans text-gray-300 italic">
                                                            -
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-center py-10 border border-dashed border-gray-300 rounded-xl text-gray-500 font-sans text-sm bg-gray-50">
                                        Qavatlar mavjud emas. Yuqoridagi "+ QAVAT" tugmasi orqali qo'shing.
                                    </div>
                                </div>

                                <!-- Unit Tooltip -->
                                <Teleport to="body">
                                    <div v-if="hoveredUnit" 
                                         class="fixed z-50 pointer-events-none bg-white border border-gray-200 p-4 rounded-xl shadow-lg"
                                         :style="{ top: (mouseY + 15) + 'px', left: (mouseX + 15) + 'px' }">
                                        <h4 class="text-blue-600 font-display text-lg mb-2 flex items-center gap-2 font-sans font-bold">
                                            {{ (hoveredUnit.type === 'commercial' && !String(hoveredUnit.unit_number).startsWith('D') ? 'D-' : '') + hoveredUnit.unit_number }} 
                                            <span class="text-[10px] font-sans px-2 py-0.5 rounded text-gray-900 bg-gray-100">{{ hoveredUnit.type === 'commercial' ? "Do'kon" : 'Xonadon' }}</span>
                                        </h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-xs font-sans">
                                            <span class="text-gray-500">Maydoni:</span>
                                            <span class="text-gray-900 text-right font-bold">{{ hoveredUnit.area_sqm }} m²</span>
                                            
                                            <span class="text-gray-500">Xonalar:</span>
                                            <span class="text-gray-900 text-right font-bold">{{ hoveredUnit.rooms_count || 1 }} xona</span>
                                            
                                            <span class="text-gray-500">Joylashuv:</span>
                                            <span class="text-gray-900 text-right font-bold">{{ hoveredUnit.orientation || 'Noma\'lum' }}</span>
                                            
                                            <span class="text-gray-500">Narxi:</span>
                                            <span class="text-emerald-600 text-right font-bold">{{ formatSoM(hoveredUnit.price) }}</span>
                                            
                                            <span class="text-gray-500">Status:</span>
                                            <span :class="['text-right font-bold', getUnitTextColor(hoveredUnit.status)]">{{ $t(hoveredUnit.status) || hoveredUnit.status }}</span>
                                        </div>
                                    </div>
                                </Teleport>
                            </div>

                            <!-- TAB: Search -->
                            <div v-if="activeTab === 'search'" class="animate-fade-in space-y-6">
                                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6">
                                    <h3 class="text-sm font-sans font-bold text-gray-900 uppercase tracking-widest mb-4">Obyekt va Xonadonlarni Qidirish</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                        <div>
                                            <label class="block text-[10px] font-sans font-bold text-gray-500 mb-1 uppercase">Obyekt (Bino)</label>
                                            <select v-model="searchFilters.building_id" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all text-sm">
                                                <option value="">Barchasi</option>
                                                <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }} - {{ b.block_name }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-sans font-bold text-gray-500 mb-1 uppercase">Turi</label>
                                            <select v-model="searchFilters.type" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all text-sm">
                                                <option value="">Barchasi</option>
                                                <option value="residential">Turar joy (Uy)</option>
                                                <option value="commercial">Tijorat (Do'kon)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-sans font-bold text-gray-500 mb-1 uppercase">Holati</label>
                                            <select v-model="searchFilters.status" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all text-sm">
                                                <option value="">Barchasi</option>
                                                <option value="available">Bo'sh</option>
                                                <option value="reserved">Band qilingan</option>
                                                <option value="sold">Sotilgan</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button @click="performSearch" class="w-full py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold font-sans text-xs rounded-lg transition-colors shadow-sm">
                                        Qidirish
                                    </button>
                                </div>
                            
                                <!-- Results -->
                                <div v-if="searchResults !== null" class="bg-white border border-gray-200 shadow-sm rounded-xl p-4">
                                    <h4 class="text-xs font-sans font-bold text-gray-500 uppercase mb-4">Natijalar: {{ searchResults.length }} ta topildi</h4>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm font-sans">
                                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                                <tr class="border-b border-gray-200 text-[10px] text-gray-500 uppercase tracking-widest">
                                                    <th class="py-3 px-2">Bino / Qavat</th>
                                                    <th class="py-3 px-2">Raqam</th>
                                                    <th class="py-3 px-2">Turi</th>
                                                    <th class="py-3 px-2">Holati</th>
                                                    <th class="py-3 px-2">Maydon</th>
                                                    <th class="py-3 px-2 text-right">Narxi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="unit in searchResults" :key="unit.id" 
                                                    class="border-b border-gray-100 hover:bg-gray-50 transition-colors cursor-pointer"
                                                    :class="{ 'bg-amber-50/50 font-semibold': selectedUnit && selectedUnit.id === unit.id }"
                                                    @click="selectUnit(unit)">
                                                    <td class="py-3 px-2 text-gray-900">{{ unit.building_name }} / {{ unit.floor_number < 0 ? `${unit.floor_number} yerto'la` : `${unit.floor_number}-qavat` }}</td>
                                                    <td class="py-3 px-2 font-bold text-gray-900">{{ unit.unit_number }}</td>
                                                    <td class="py-3 px-2">{{ unit.type === 'commercial' ? "Do'kon" : 'Uy' }}</td>
                                                    <td class="py-3 px-2" :class="getUnitTextColor(unit.status)">{{ $t(unit.status) || unit.status }}</td>
                                                    <td class="py-3 px-2">{{ unit.area_sqm }} m²</td>
                                                    <td class="py-3 px-2 text-emerald-600 text-right font-bold">{{ $currency(unit.price) }}</td>
                                                </tr>
                                                <tr v-if="searchResults.length === 0">
                                                    <td colspan="6" class="py-8 text-center text-gray-500 text-xs border border-dashed border-gray-200 rounded-xl">Hech narsa topilmadi</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Unit Tafsilot / Calculator Panel -->
                        <div v-if="selectedUnit" class="lg:col-span-1 bg-white border border-gray-200 shadow-sm rounded-2xl p-6 relative flex flex-col justify-between min-h-[480px]">
                            <!-- PANEL 1: Details (Tafsilot) -->
                            <div v-if="activeRightPanel === 'details'" class="space-y-6 flex-1 flex flex-col justify-between">
                                <div>
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-2xl font-bold font-sans text-gray-800">Tafsilot</h3>
                                        <button @click="selectedUnit = null" class="text-gray-400 hover:text-gray-600 transition-colors text-3xl font-light">
                                            ✕
                                        </button>
                                    </div>

                                    <!-- Blueprint / Layout Image -->
                                    <div class="flex items-center justify-center overflow-hidden bg-white p-2 min-h-[180px] max-h-[260px] mb-6">
                                        <img :src="selectedUnitBlueprint" 
                                             class="max-h-full max-w-full object-contain cursor-pointer hover:scale-102 transition-transform duration-300"
                                             @click="openBlueprintFullscreenForUnit" />
                                    </div>

                                    <h4 class="text-[#a8a29e] text-xl font-medium mb-6 font-sans">
                                        {{ selectedUnit.type === 'commercial' ? "Do'kon haqida ma'lumot" : "Uy haqida ma'lumot" }}
                                    </h4>
                                    <div class="space-y-4 text-base font-sans text-gray-700">
                                        <div>
                                            <span class="text-gray-500">Xonadon:</span>
                                            <strong class="text-gray-900 font-bold ml-1">{{ (selectedUnit.type === 'commercial' && !String(selectedUnit.unit_number).startsWith('D') ? 'D-' : '') + selectedUnit.unit_number }}</strong>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Podezd:</span>
                                            <strong class="text-gray-900 font-bold ml-1">{{ selectedUnit.podyezd }}-padezd</strong>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Qavat:</span>
                                            <strong class="text-gray-900 font-bold ml-1">{{ selectedFloor?.floor_number }}</strong>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Tafsilot:</span>
                                            <strong class="text-gray-900 font-bold ml-1">{{ selectedUnit.orientation || '' }}</strong>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Xonalar soni:</span>
                                            <strong class="text-gray-900 font-bold ml-1">{{ selectedUnit.rooms_count || 1 }}</strong>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Maydoni:</span>
                                            <strong class="text-gray-900 font-bold ml-1">{{ selectedUnit.area_sqm }} m²</strong>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Terrasa:</span>
                                            <strong class="text-gray-900 font-bold ml-1">
                                                {{ selectedUnit.orientation?.toLowerCase().includes('terrasa') ? 'Terrasa mavjud' : 'Terrasa mavjud emas' }}
                                            </strong>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Narx:</span>
                                            <strong class="text-gray-900 font-bold ml-1">{{ formatSoM(selectedUnit.price) }}</strong>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions Buttons -->
                                <div class="flex gap-4 justify-end mt-6">
                                    <button @click="activeRightPanel = 'calculator'" class="px-6 py-2.5 bg-[#f59e0b] hover:bg-amber-600 text-white font-bold font-sans rounded-full text-base shadow-sm transition-all text-center">
                                        Hisoblash
                                    </button>
                                    <button v-if="selectedUnit.status !== 'sold'" @click="openSellUnit(selectedUnit)" class="px-6 py-2.5 bg-[#f59e0b] hover:bg-amber-600 text-white font-bold font-sans rounded-full text-base shadow-sm transition-all text-center">
                                        sotish
                                    </button>
                                    <button @click="openEditUnit(selectedUnit)" class="px-6 py-2.5 bg-[#f59e0b] hover:bg-amber-600 text-white font-bold font-sans rounded-full text-base shadow-sm transition-all text-center">
                                        Tahrirlash
                                    </button>
                                </div>
                            </div>

                            <!-- PANEL 2: Calculator (Kalkulyator) -->
                            <div v-else-if="activeRightPanel === 'calculator'" class="space-y-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center gap-3 mb-6">
                                        <button @click="activeRightPanel = 'details'" class="text-gray-400 hover:text-gray-600 transition-colors text-3xl font-light">
                                            ✕
                                        </button>
                                        <h3 class="text-2xl font-bold font-sans text-gray-800">Kalkulyator</h3>
                                    </div>

                                    <div class="space-y-4 font-sans">
                                        <!-- Uy narxi -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-1">Uy narxi</label>
                                            <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                <input type="number" v-model.number="calcPrice" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans" />
                                                <span class="text-gray-400">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Boshlang'ich to'lov -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-1">
                                                Boshlang'ich to'lov <span v-if="calcDownPaymentPercent > 0" class="text-amber-500 font-bold ml-1">({{ calcDownPaymentPercent }}%)</span>
                                            </label>
                                            <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                <input type="number" v-model.number="calcDownPayment" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans" />
                                            </div>
                                            <div class="grid grid-cols-5 gap-2 mt-2">
                                                <button v-for="pct in [10, 20, 30, 40, 50, 60, 70, 80, 90, 100]" :key="pct" type="button" @click="setDownPaymentPercent(pct)" class="px-2 py-1.5 border border-gray-200 text-gray-500 rounded-lg text-xs bg-white hover:bg-gray-50 shadow-sm transition-all font-sans font-medium text-center">
                                                    {{ pct }}%
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Bo'lib to'lash muddati (oy) -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-1">Bo'lib to'lash muddati (oy)</label>
                                            <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                <input type="number" v-model.number="calcTermMonths" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans" />
                                            </div>
                                        </div>

                                        <!-- Chegirma -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-1">Chegirma</label>
                                            <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                <input type="number" v-model.number="calcDiscount" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans" />
                                                <button type="button" @click="calcDiscount = 0" class="text-red-500 mx-2 hover:scale-110 transition-transform" title="Tozalash">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                    </svg>
                                                </button>
                                                <span class="text-gray-400">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Calculation Results Card -->
                                <div class="bg-[#f8fafc] border border-gray-200 rounded-2xl p-6 space-y-4">
                                    <div>
                                        <span class="text-gray-500 text-sm font-sans block mb-1">Oylik to'lov</span>
                                        <span class="text-3xl font-bold text-gray-900 font-sans">{{ formatSoM(calculatedMonthlyPayment) }}</span>
                                    </div>
                                    
                                    <div class="space-y-3 pt-2 text-sm font-sans text-gray-600">
                                        <!-- Umumiy summa -->
                                        <div class="flex items-center justify-between">
                                            <span class="whitespace-nowrap">Umumiy summa</span>
                                            <div class="flex-grow border-b border-dotted border-gray-300 mx-2"></div>
                                            <span class="text-gray-900 font-bold whitespace-nowrap">{{ formatSoM(calculatedTotalAmount) }}</span>
                                        </div>
                                        
                                        <!-- Qoldiq summa -->
                                        <div class="flex items-center justify-between">
                                            <span class="whitespace-nowrap">Qoldiq summa</span>
                                            <div class="flex-grow border-b border-dotted border-gray-300 mx-2"></div>
                                            <span class="text-gray-900 font-bold whitespace-nowrap">{{ formatSoM(calculatedRemainingBalance) }}</span>
                                        </div>

                                        <!-- Boshlang'ich to'lov -->
                                        <div class="flex items-center justify-between">
                                            <span class="whitespace-nowrap">Boshlang'ich to'lov</span>
                                            <div class="flex-grow border-b border-dotted border-gray-300 mx-2"></div>
                                            <span class="text-gray-900 font-bold whitespace-nowrap">{{ formatSoM(calcDownPayment) }}</span>
                                        </div>
                                        
                                        <!-- Chegirma -->
                                        <div class="flex items-center justify-between">
                                            <span class="whitespace-nowrap">Chegirma</span>
                                            <div class="flex-grow border-b border-dotted border-gray-300 mx-2"></div>
                                            <span class="text-gray-900 font-bold whitespace-nowrap">{{ formatSoM(calcDiscount) }}</span>
                                        </div>
                                        
                                        <!-- Jami (boshlang'ich to'lov bilan) -->
                                        <div class="flex items-center justify-between font-semibold pt-1 border-t border-dashed border-gray-200">
                                            <span class="whitespace-nowrap text-gray-800">Jami (boshlang'ich to'lov bilan)</span>
                                            <div class="flex-grow border-b border-dotted border-gray-300 mx-2"></div>
                                            <span class="text-gray-900 font-bold whitespace-nowrap">{{ formatSoM(calculatedGrandTotal) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PANEL 3: Sell (Sotish) -->
                            <form v-else-if="activeRightPanel === 'sell'" @submit.prevent="submitContract" class="space-y-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center gap-3 mb-6">
                                        <button type="button" @click="activeRightPanel = 'details'" class="text-gray-400 hover:text-gray-600 transition-colors text-3xl font-light">
                                            ✕
                                        </button>
                                        <h3 class="text-2xl font-bold font-sans text-gray-800">Sotish</h3>
                                    </div>

                                    <div class="space-y-4 font-sans">
                                        <!-- Shartnoma sanasi & Shartnoma raqami -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Shartnoma sanasi</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                    <input type="date" v-model="contractForm.created_at" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans" />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Shartnoma raqami</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                    <input type="text" v-model="contractForm.contract_number" placeholder="Masalan: SH-001" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans" />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Buyer Type selection -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-2">Xaridor</label>
                                            <div class="flex gap-6 mb-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="radio" v-model="contractForm.buyer_type" value="client" class="text-amber-500 focus:ring-amber-500 bg-white border-gray-300">
                                                    <span class="text-sm text-gray-905 font-medium">Mijoz</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="radio" v-model="contractForm.buyer_type" value="partner" class="text-amber-500 focus:ring-amber-500 bg-white border-gray-300">
                                                    <span class="text-sm text-gray-905 font-medium">Ta'sischi</span>
                                                </label>
                                            </div>
                                            
                                            <!-- Client / Partner Searchable select -->
                                            <div class="relative">
                                                <SearchableSelect 
                                                    v-if="contractForm.buyer_type === 'client'" 
                                                    v-model="contractForm.client_id" 
                                                    :options="clientsOptions" 
                                                    placeholder="Mijozni tanlang" 
                                                    :required="contractForm.buyer_type === 'client'"
                                                    class="w-full"
                                                />
                                                <SearchableSelect 
                                                    v-if="contractForm.buyer_type === 'partner'" 
                                                    v-model="contractForm.partner_id" 
                                                    :options="partnersOptions" 
                                                    placeholder="Ta'sischini tanlang" 
                                                    :required="contractForm.buyer_type === 'partner'"
                                                    class="w-full"
                                                />
                                            </div>
                                        </div>

                                        <!-- Umumiy narx & Kvadrat metr narxi (ReadOnly) -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Umumiy narxi (QQS bilan)</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                    <input type="number" v-model.number="contractForm.total_amount" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans" required />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Kvadrat metri narxi</label>
                                                <div class="relative flex items-center bg-[#f1f5f9] border border-gray-200 rounded-xl px-4 py-3 opacity-70">
                                                    <input type="number" v-model="contractForm.price_per_sqm" readonly tabindex="-1" class="bg-transparent border-0 text-gray-500 text-sm outline-none focus:ring-0 w-full p-0 font-sans cursor-not-allowed" />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Payment Type -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-2">To'lov turi</label>
                                            <div class="flex gap-6">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="radio" v-model="contractForm.payment_type" value="full" class="text-amber-500 focus:ring-amber-500 bg-white border-gray-300">
                                                    <span class="text-sm text-gray-905 font-medium">Bir martalik umumiy to'lov</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="radio" v-model="contractForm.payment_type" value="installment" class="text-amber-500 focus:ring-amber-500 bg-white border-gray-300">
                                                    <span class="text-sm text-gray-905 font-medium">Bo'lib to'lash</span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Boshlang'ich to'lov & Muddat (oy) -->
                                        <div class="grid grid-cols-2 gap-4" v-if="contractForm.payment_type === 'installment'">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Boshlang'ich to'lov</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                    <input type="number" v-model.number="contractForm.down_payment" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans" required />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Muddat (oy)</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                    <input type="number" v-model.number="contractForm.term_months" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans" required />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex gap-3 justify-end mt-6">
                                    <button type="button" @click="activeRightPanel = 'details'" class="px-6 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold font-sans rounded-full text-base transition-all text-center">
                                        Bekor qilish
                                    </button>
                                    <button type="submit" :disabled="isProcessingContract" class="px-6 py-2.5 bg-[#10b981] hover:bg-emerald-600 text-white font-bold font-sans rounded-full text-base shadow-sm transition-all text-center disabled:opacity-50">
                                        SAVDO HUNISINI YARATISH
                                    </button>
                                </div>
                            </form>

                            <!-- PANEL 4: Edit Unit (Tahrirlash) -->
                            <form v-else-if="activeRightPanel === 'edit_unit'" @submit.prevent="submitUnit" class="space-y-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center gap-3 mb-6">
                                        <button type="button" @click="cancelEditUnit" class="text-gray-400 hover:text-gray-600 transition-colors text-3xl font-light">
                                            ✕
                                        </button>
                                        <h3 class="text-2xl font-bold font-sans text-gray-800">Tahrirlash</h3>
                                    </div>

                                    <div class="space-y-4 font-sans">
                                        <!-- Raqami & Turi -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Raqami (Masalan: A501)</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                    <input type="text" v-model="unitForm.unit_number" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans" required />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Turi</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                    <select v-model="unitForm.type" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans bg-white">
                                                        <option value="residential">Turar joy (Xonadon)</option>
                                                        <option value="commercial">Tijorat (Do'kon)</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Maydoni & Jami Narxi -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Maydoni (kv.m)</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                    <input type="number" step="0.01" v-model.number="unitForm.area_sqm" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans" required />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Jami Narxi</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                    <input type="number" step="0.01" v-model.number="unitForm.price" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans" required />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Xonalar & Oshxona & Hammom & Hojatxona -->
                                        <div class="grid grid-cols-4 gap-2">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Xonalar</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-2 py-2">
                                                    <input type="number" min="1" v-model.number="unitForm.rooms_count" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans text-center" />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Oshxona</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-2 py-2">
                                                    <input type="number" min="0" v-model.number="unitForm.kitchens_count" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans text-center" />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Hammom</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-2 py-2">
                                                    <input type="number" min="0" v-model.number="unitForm.bathrooms_count" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans text-center" />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Hojatxona</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-2 py-2">
                                                    <input type="number" min="0" v-model.number="unitForm.toilets_count" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans text-center" />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Status & Podyezd & Joylashuvi -->
                                        <div class="grid grid-cols-3 gap-2">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-2.5 py-3">
                                                    <select v-model="unitForm.status" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans bg-white">
                                                        <option value="available">Bo'sh</option>
                                                        <option value="reserved">Band qilingan</option>
                                                        <option value="sold">Sotilgan</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Podyezd</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-2.5 py-3">
                                                    <input type="number" min="1" v-model.number="unitForm.podyezd" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans text-center" required />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Joylashuvi</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-2.5 py-3">
                                                    <input type="text" v-model="unitForm.orientation" list="orientations" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans" placeholder="Masalan: Burchak" />
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
                                        </div>

                                        <!-- Poligon -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-1">Poligon Koordinatalari (Ixtiyoriy)</label>
                                            <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                <input type="text" v-model="unitForm.svg_polygon" placeholder="e.g. 100,100 200,100..." class="bg-transparent border-0 text-gray-800 text-xs outline-none focus:ring-0 w-full p-0 font-sans font-mono" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-between items-center mt-6">
                                    <div>
                                        <button type="button" v-if="isEditingUnit && isAdmin" @click="deleteUnit(editingUnitId)" class="px-4 py-2 bg-red-50 hover:bg-red-600 text-red-600 hover:text-white font-bold font-sans rounded-full text-xs transition-all">
                                            O'chirish
                                        </button>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" v-if="isEditingUnit && unitForm.status !== 'sold'" @click="openSellUnit(selectedUnit)" class="px-4 py-2 bg-emerald-50 hover:bg-emerald-600 text-emerald-600 hover:text-white font-bold font-sans rounded-full text-xs transition-all">
                                            Sotish
                                        </button>
                                        <button type="button" @click="cancelEditUnit" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold font-sans rounded-full text-xs transition-all text-center">
                                            Bekor qilish
                                        </button>
                                        <button type="submit" :disabled="unitForm.processing" class="px-4 py-2 bg-[#f59e0b] hover:bg-amber-600 text-white font-bold font-sans rounded-full text-xs shadow-sm transition-all text-center disabled:opacity-50">
                                            Saqlash
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Add/Edit Building Modal -->
        <Modal :show="showAddBuildingModal" @close="showAddBuildingModal = false; isEditingBuilding = false">
            <div class="p-6 w-[600px] max-w-[95vw] overflow-y-auto max-h-[90vh] custom-scrollbar bg-[#1e293b] text-white rounded-xl">
                <h2 class="text-xl font-bold uppercase tracking-wider text-white mb-6">{{ modalTitle }}</h2>
                <form @submit.prevent="submitBuilding" class="space-y-4">
                    
                    <!-- YANGI OBYEKT QO'SHISH FORM -->
                    <div v-if="!isAddingBlock && !isEditingBuilding" class="space-y-4">
                        <!-- FOTO -->
                        <div>
                            <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">FOTO</label>
                            <input type="file" @change="handleFileChange(buildingForm, 'photo', $event, 100)" accept="image/*" class="w-full bg-[#0f172a] border border-[#334155] text-gray-300 text-xs rounded-lg block p-2 file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600 cursor-pointer">
                        </div>

                        <!-- OBYEKT NOMI -->
                        <div>
                            <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">OBYEKT NOMI</label>
                            <input v-model="buildingForm.name" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all" required>
                        </div>

                        <!-- Location Fields -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">VILOYAT</label>
                                <input v-model="buildingForm.district" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">TUMAN/SHAHAR</label>
                                <input v-model="buildingForm.city" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">MAHALLA</label>
                                <input v-model="buildingForm.mahalla" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">KO'CHA</label>
                                <input v-model="buildingForm.street" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                            </div>
                        </div>

                        <!-- Files Section -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">2D CHIZMA</label>
                                <input type="file" @change="handleFileChange(buildingForm, 'blueprint', $event, 250)" class="w-full bg-[#0f172a] border border-[#334155] text-gray-300 text-xs rounded-lg block p-2 file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600 cursor-pointer">
                            </div>
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">3D Model (png, jpg, pdf)</label>
                                <input type="file" @change="handleFileChange(buildingForm, 'model_3d', $event, 1000)" class="w-full bg-[#0f172a] border border-[#334155] text-gray-300 text-xs rounded-lg block p-2 file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600 cursor-pointer">
                            </div>
                        </div>
                    </div>

                    <!-- YANGI BLOK QO'SHISH FORM -->
                    <div v-else-if="isAddingBlock && !isEditingBuilding" class="space-y-4">
                        <!-- ROW 1: BLOK NOMI & PODEZ NOMI -->
                        <!-- ROW 1: BLOK NOMI & KORPUS NOMI -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">BLOK NOMI</label>
                                <input v-model="buildingForm.block_name" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all" placeholder="Masalan: A Blok" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">KORPUS NOMI</label>
                                <input v-model="buildingForm.corpus_name" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all" placeholder="Masalan: 3-Korpus">
                            </div>
                        </div>

                        <!-- ROW 2: QAVATLAR SONI & YERTO'LA NECHA QAVATNI TASHKIL QILADI -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">QAVATLAR SONI</label>
                                <input v-model="buildingForm.total_floors" type="number" min="1" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase text-wrap">YERTO'LA NECHA QAVATNI TASHKIL QILADI</label>
                                <input v-model="buildingForm.basement_floors" type="number" min="0" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                            </div>
                        </div>

                        <!-- ROW 3: JAMI DO'KONLAR & JAMI KVARTIRALAR -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase text-wrap">JAMI DO'KONLAR</label>
                                <input v-model="buildingForm.total_shops" @input="onTotalShopsInput" type="number" min="0" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase text-wrap">JAMI KVARTIRALAR</label>
                                <input v-model="buildingForm.total_apartments" @input="onTotalApartmentsInput" type="number" min="0" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                            </div>
                        </div>

                        <!-- QAVATLAR BO'YICHA SOZLAMALAR -->
                        <div v-if="buildingForm.floors_data && buildingForm.floors_data.length > 0" class="bg-[#0f172a] p-3 rounded-lg border border-[#334155] space-y-2.5">
                            <h4 class="text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase tracking-wider">QAVATLAR BO'YICHA DO'KONLAR VA UYLAR SONI</h4>
                            <div class="max-h-[160px] overflow-y-auto custom-scrollbar space-y-2 pr-1">
                                <div v-for="item in buildingForm.floors_data" :key="item.floor_number" class="flex items-center justify-between bg-[#1e293b] p-2 rounded border border-[#334155] gap-3">
                                    <span class="text-xs font-semibold text-gray-300 font-sans">
                                        {{ item.floor_number < 0 ? `${item.floor_number} yerto'la` : `${item.floor_number}-qavat` }}
                                    </span>
                                    <div class="flex gap-2 items-center">
                                        <!-- Shops input (only show for commercial floors) -->
                                        <div v-if="item.floor_number <= 1" class="flex items-center gap-1.5">
                                            <span class="text-[9px] text-gray-400 font-bold uppercase">Do'kon:</span>
                                            <input v-model.number="item.shops_count" type="number" min="0" class="w-16 bg-[#0f172a] border border-[#334155] text-white text-xs rounded px-2 py-1 outline-none text-center focus:border-blue-500">
                                        </div>
                                        <!-- Apartments input (only show for residential floors) -->
                                        <div v-if="item.floor_number >= 2" class="flex items-center gap-1.5">
                                            <span class="text-[9px] text-gray-400 font-bold uppercase">Uy:</span>
                                            <input v-model.number="item.apartments_count" type="number" min="0" class="w-16 bg-[#0f172a] border border-[#334155] text-white text-xs rounded px-2 py-1 outline-none text-center focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ROW 5: 2D CHIZMA & 3D MODEL (PNG, JPG, PDF) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">2D CHIZMA</label>
                                <input type="file" @change="handleFileChange(buildingForm, 'blueprint', $event, 250)" class="w-full bg-[#0f172a] border border-[#334155] text-gray-300 text-xs rounded-lg block p-2 file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600 cursor-pointer">
                            </div>
                            <div>
                                <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">3D MODEL (PNG, JPG, PDF)</label>
                                <input type="file" @change="handleFileChange(buildingForm, 'model_3d', $event, 1000)" class="w-full bg-[#0f172a] border border-[#334155] text-gray-300 text-xs rounded-lg block p-2 file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600 cursor-pointer">
                            </div>
                        </div>
                    </div>

                    <!-- BINONI TAHRIRLASH FORM -->
                    <div v-if="isEditingBuilding && selectedBuilding" class="space-y-4">
                        
                        <!-- 1. TAHRIRLANAYOTGAN NAYSA OBJECT CONTAINER BO'LSA (total_floors === 0) -->
                        <div v-if="selectedBuilding.total_floors === 0" class="space-y-4">
                            <!-- Obyekt Nomi & Foto -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">Obyekt Nomi</label>
                                    <input v-model="buildingForm.name" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">Foto</label>
                                    <input type="file" @change="handleFileChange(buildingForm, 'photo', $event, 100)" accept="image/*" class="w-full bg-[#0f172a] border border-[#334155] text-gray-300 text-xs rounded-lg block p-2 file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600 cursor-pointer">
                                </div>
                            </div>

                            <!-- Location Fields -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">Viloyat</label>
                                    <input v-model="buildingForm.district" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">Tuman/Shahar</label>
                                    <input v-model="buildingForm.city" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">Mahalla</label>
                                    <input v-model="buildingForm.mahalla" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">Ko'cha</label>
                                    <input v-model="buildingForm.street" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                                </div>
                            </div>

                            <!-- Files Section -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">2D Chizma</label>
                                    <input type="file" @change="handleFileChange(buildingForm, 'blueprint', $event, 250)" class="w-full bg-[#0f172a] border border-[#334155] text-gray-300 text-xs rounded-lg block p-2 file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600 cursor-pointer">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">3D Model (png, jpg, pdf)</label>
                                    <input type="file" @change="handleFileChange(buildingForm, 'model_3d', $event, 1000)" class="w-full bg-[#0f172a] border border-[#334155] text-gray-300 text-xs rounded-lg block p-2 file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600 cursor-pointer">
                                </div>
                            </div>
                        </div>

                        <!-- 2. TAHRIRLANAYOTGAN NAYSA BLOK BO'LSA (total_floors > 0) -->
                        <div v-else class="space-y-4">
                            <!-- ROW 1: BLOK NOMI & KORPUS NOMI -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">Blok Nomi</label>
                                    <input v-model="buildingForm.block_name" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all" placeholder="Masalan: A Blok" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">Korpus Nomi</label>
                                    <input v-model="buildingForm.corpus_name" type="text" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all" placeholder="Masalan: 3-Korpus">
                                </div>
                            </div>

                            <!-- ROW 2: QAVATLAR SONI & YERTO'LA NECHA QAVATNI TASHKIL QILADI -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">Qavatlar soni</label>
                                    <input v-model="buildingForm.total_floors" type="number" min="1" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase text-wrap">Yerto'la necha qavatni tashkil qiladi</label>
                                    <input v-model="buildingForm.basement_floors" type="number" min="0" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                                </div>
                            </div>

                            <!-- ROW 3: JAMI DO'KONLAR & JAMI KVARTIRALAR -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase text-wrap">Jami Do'konlar</label>
                                    <input v-model="buildingForm.total_shops" @input="onTotalShopsInput" type="number" min="0" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase text-wrap">Jami Kvartiralar</label>
                                    <input v-model="buildingForm.total_apartments" @input="onTotalApartmentsInput" type="number" min="0" class="w-full bg-[#0f172a] border border-[#334155] text-white placeholder-gray-500 text-sm rounded-lg shadow-inner focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-all">
                                </div>
                            </div>

                            <!-- QAVATLAR BO'YICHA SOZLAMALAR -->
                            <div v-if="buildingForm.floors_data && buildingForm.floors_data.length > 0" class="bg-[#0f172a] p-3 rounded-lg border border-[#334155] space-y-2.5">
                                <h4 class="text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase tracking-wider">QAVATLAR BO'YICHA DO'KONLAR VA UYLAR SONI</h4>
                                <div class="max-h-[160px] overflow-y-auto custom-scrollbar space-y-2 pr-1">
                                    <div v-for="item in buildingForm.floors_data" :key="item.floor_number" class="flex items-center justify-between bg-[#1e293b] p-2 rounded border border-[#334155] gap-3">
                                        <span class="text-xs font-semibold text-gray-300 font-sans">
                                            {{ item.floor_number < 0 ? `${item.floor_number} yerto'la` : `${item.floor_number}-qavat` }}
                                        </span>
                                        <div class="flex gap-2 items-center">
                                            <!-- Shops input (only show for commercial floors) -->
                                            <div v-if="item.floor_number <= 1" class="flex items-center gap-1.5">
                                                <span class="text-[9px] text-gray-400 font-bold uppercase">Do'kon:</span>
                                                <input v-model.number="item.shops_count" type="number" min="0" class="w-16 bg-[#0f172a] border border-[#334155] text-white text-xs rounded px-2 py-1 outline-none text-center focus:border-blue-500">
                                            </div>
                                            <!-- Apartments input (only show for residential floors) -->
                                            <div v-if="item.floor_number >= 2" class="flex items-center gap-1.5">
                                                <span class="text-[9px] text-gray-400 font-bold uppercase">Uy:</span>
                                                <input v-model.number="item.apartments_count" type="number" min="0" class="w-16 bg-[#0f172a] border border-[#334155] text-white text-xs rounded px-2 py-1 outline-none text-center focus:border-blue-500">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ROW 5: 2D CHIZMA & 3D MODEL (PNG, JPG, PDF) -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">2D Chizma</label>
                                    <input type="file" @change="handleFileChange(buildingForm, 'blueprint', $event, 250)" class="w-full bg-[#0f172a] border border-[#334155] text-gray-300 text-xs rounded-lg block p-2 file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600 cursor-pointer">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-sans font-bold text-gray-400 mb-1 uppercase">3D Model (png, jpg, pdf)</label>
                                    <input type="file" @change="handleFileChange(buildingForm, 'model_3d', $event, 1000)" class="w-full bg-[#0f172a] border border-[#334155] text-gray-300 text-xs rounded-lg block p-2 file:mr-3 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600 cursor-pointer">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end items-center gap-3 mt-6">
                        <button type="button" @click="showAddBuildingModal = false; isEditingBuilding = false" class="px-4 py-2 text-xs font-semibold text-gray-400 hover:text-white transition-colors">Bekor Qilish</button>
                        <button type="submit" :disabled="buildingForm.processing" class="px-5 py-2.5 bg-blue-600 text-white font-bold text-xs rounded-lg shadow-md hover:bg-blue-700 transition-colors disabled:opacity-50">
                            {{ submitButtonLabel }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Upload Models Modal -->
        <Modal :show="showUploadModelsModal" @close="showUploadModelsModal = false">
            <div class="p-6 w-[500px] max-w-[95vw]">
                <h2 class="text-xl font-bold text-gray-900 mb-6">2D Chizma va 3D Model Yuklash</h2>
                <form @submit.prevent="submitUploadModels" class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1">Blok / Korpusni tanlang</label>
                        <select v-model="uploadModelsForm.building_id" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            <option v-for="b in (groupedObjects[currentObject] || [])" :key="b.id" :value="b.id">
                                {{ b.block_name || b.corpus_name || 'Blok' }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1">2D Chizma (PDF, JPG, PNG)</label>
                        <input type="file" @change="handleFileChange(uploadModelsForm, 'blueprint', $event, 250)" class="bg-white border border-gray-300 text-gray-900 text-xs rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1">3D Model (png, jpg, pdf, gltf, obj, glb)</label>
                        <input type="file" @change="handleFileChange(uploadModelsForm, 'model_3d', $event, 1000)" class="bg-white border border-gray-300 text-gray-900 text-xs rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2">
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showUploadModelsModal = false" class="px-4 py-2 text-xs font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="uploadModelsForm.processing" class="px-4 py-2 bg-blue-600 text-white font-bold font-mono text-xs rounded shadow-sm hover:bg-white transition-colors disabled:opacity-50">
                            Yuklash
                        </button>
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
                        <input type="file" @change="handleFileChange(floorForm, 'blueprint', $event, 250)" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-[10px] file:font-mono file:uppercase file:tracking-widest file:bg-gray-100 file:text-gray-900 hover:file:bg-white/20 cursor-pointer border border-gray-200 rounded bg-white">
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

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Status</label>
                            <select v-model="unitForm.status" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all" required>
                                <option value="available">Bo'sh</option>
                                <option value="reserved">Band qilingan</option>
                                <option value="sold">Sotilgan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Podyezd</label>
                            <input v-model="unitForm.podyezd" type="number" min="1" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all" required>
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



        <!-- Filter Modal -->
        <Modal :show="showFilterModal" @close="showFilterModal = false">
            <div class="p-6 w-full max-w-md font-sans">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-900">Filter</h2>
                    <button @click="showFilterModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-6">
                    <!-- Option 1: Xonalar soni -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Xonalar soni</label>
                        <div class="flex gap-3">
                            <button v-for="room in availableRoomOptions" :key="room"
                                    type="button"
                                    @click="toggleTempRoom(room)"
                                    :class="[
                                        tempFilterRooms.includes(room)
                                            ? 'bg-amber-500 text-white font-bold border-amber-500'
                                             : 'bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-zinc-700 border-transparent',
                                        'w-12 h-10 rounded-xl border flex items-center justify-center text-sm transition-all shadow-sm'
                                    ]">
                                {{ room }}
                            </button>
                        </div>
                    </div>

                    <!-- Option 2: Maydoni -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Maydoni (m²)</label>
                        <!-- Functional range slider bar -->
                        <div class="relative h-1 bg-amber-100 dark:bg-zinc-800 rounded-full my-6">
                            <div class="absolute h-full bg-amber-500 rounded-full" :style="{ left: areaLeftPercent + '%', right: (100 - areaRightPercent) + '%' }"></div>
                            <input type="range" 
                                   :min="minAvailableArea" 
                                   :max="maxAvailableArea" 
                                   v-model="tempFilterMinArea" 
                                   @input="validateAreaRange(true)" 
                                   class="absolute w-full h-1 bg-transparent appearance-none pointer-events-none cursor-pointer range-slider-input" 
                                   :style="{ zIndex: Number(tempFilterMinArea) > (minAvailableArea + maxAvailableArea) / 2 ? 12 : 10, marginTop: '-2px' }">
                            <input type="range" 
                                   :min="minAvailableArea" 
                                   :max="maxAvailableArea" 
                                   v-model="tempFilterMaxArea" 
                                   @input="validateAreaRange(false)" 
                                   class="absolute w-full h-1 bg-transparent appearance-none pointer-events-none cursor-pointer range-slider-input" 
                                   :style="{ zIndex: Number(tempFilterMinArea) > (minAvailableArea + maxAvailableArea) / 2 ? 10 : 12, marginTop: '-2px' }">
                        </div>
                        <div class="flex items-center gap-3">
                            <input v-model="tempFilterMinArea" type="number" placeholder="Min" class="w-1/2 bg-white border border-gray-200 rounded-lg px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-sm">
                            <span class="text-gray-400">—</span>
                            <input v-model="tempFilterMaxArea" type="number" placeholder="Max" class="w-1/2 bg-white border border-gray-200 rounded-lg px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-sm">
                        </div>
                    </div>

                    <!-- Option 3: Narxi -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Narx</label>
                        <!-- Functional range slider bar -->
                        <div class="relative h-1 bg-amber-100 dark:bg-zinc-800 rounded-full my-6">
                            <div class="absolute h-full bg-amber-500 rounded-full" :style="{ left: priceLeftPercent + '%', right: (100 - priceRightPercent) + '%' }"></div>
                            <input type="range" 
                                   :min="minAvailablePrice" 
                                   :max="maxAvailablePrice" 
                                   v-model="tempFilterMinPrice" 
                                   @input="validatePriceRange(true)" 
                                   class="absolute w-full h-1 bg-transparent appearance-none pointer-events-none cursor-pointer range-slider-input" 
                                   :style="{ zIndex: Number(tempFilterMinPrice) > (minAvailablePrice + maxAvailablePrice) / 2 ? 12 : 10, marginTop: '-2px' }">
                            <input type="range" 
                                   :min="minAvailablePrice" 
                                   :max="maxAvailablePrice" 
                                   v-model="tempFilterMaxPrice" 
                                   @input="validatePriceRange(false)" 
                                   class="absolute w-full h-1 bg-transparent appearance-none pointer-events-none cursor-pointer range-slider-input" 
                                   :style="{ zIndex: Number(tempFilterMinPrice) > (minAvailablePrice + maxAvailablePrice) / 2 ? 10 : 12, marginTop: '-2px' }">
                        </div>
                        <div class="flex items-center gap-3">
                            <input v-model="tempFilterMinPrice" type="number" placeholder="Min" class="w-1/2 bg-white border border-gray-200 rounded-lg px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-sm">
                            <span class="text-gray-400">—</span>
                            <input v-model="tempFilterMaxPrice" type="number" placeholder="Max" class="w-1/2 bg-white border border-gray-200 rounded-lg px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-sm">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-8">
                    <button type="button" @click="showFilterModal = false" class="px-5 py-2.5 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-bold text-xs rounded-xl hover:bg-gray-50 dark:hover:bg-zinc-800 transition-all text-center">
                        Yopish
                    </button>
                    <button type="button" @click="applyFilters" class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-xl transition-all shadow-sm">
                        Filterlash
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Blueprint Fullscreen Modal -->
        <Modal :show="showBlueprintFullscreen" @close="showBlueprintFullscreen = false" maxWidth="4xl">
            <div class="p-6 relative bg-white dark:bg-zinc-900 rounded-2xl font-sans">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">2D Chizma (Bino/Qavat Chizmasi)</h3>
                    <button @click="showBlueprintFullscreen = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex items-center justify-center bg-gray-100 dark:bg-zinc-950 rounded-xl overflow-auto p-4 min-h-[400px] max-h-[80vh]">
                    <img :src="currentBlueprintPath ? `/storage/${currentBlueprintPath}` : '/modern_apartment_render.png'" 
                         class="max-h-full max-w-full object-contain" />
                </div>
            </div>
        </Modal>

    </AdminLayout>
</template>

<style scoped>
.range-slider-input {
    outline: none;
}
.range-slider-input::-webkit-slider-thumb {
    pointer-events: auto;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #fff;
    border: 2.5px solid #f59e0b;
    cursor: pointer;
    -webkit-appearance: none;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
.range-slider-input::-moz-range-thumb {
    pointer-events: auto;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #fff;
    border: 2.5px solid #f59e0b;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
.unit-box.status-available {
    background-color: #00c853;
    border-bottom: 4px solid #009624;
}
.unit-box.status-available:hover {
    background-color: #00e676;
}
.unit-box.status-reserved {
    background-color: #ffd600;
    border-bottom: 4px solid #d89b00;
}
.unit-box.status-reserved:hover {
    background-color: #ffea00;
}
.unit-box.status-sold {
    background-color: #ef4444;
    border-bottom: 4px solid #c83030;
}
.unit-box.status-sold:hover {
    background-color: #f87171;
}
</style>
