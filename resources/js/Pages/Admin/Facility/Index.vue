<script setup>
import { ref, computed, onMounted, onUnmounted, watch, onErrorCaptured } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { useI18n } from 'vue-i18n';
import { regionsData } from '@/locations.js';

const { t } = useI18n();
const props = defineProps({
    buildings: Array,
    clients: Array,
    partners: Array,
    registers: Array,
    reportData: Array,
    filters: Object
});

const page = usePage();

const renderError = ref(null);
onErrorCaptured((err, instance, info) => {
    renderError.value = { err: err.toString(), info, stack: err.stack };
    return false; // prevent propagation
});

// Save unique object names to localStorage
watch(() => props.buildings, (newBuildings) => {
    if (typeof window === 'undefined') return;
    try {
        const availableObjectNames = [...new Set((newBuildings || []).map(b => b.name || 'Boshqa'))].filter(Boolean);
        if (availableObjectNames.length > 0) {
            localStorage.setItem('fac_available_objects', JSON.stringify(availableObjectNames));
        }
    } catch (e) {
        console.error('Error saving fac_available_objects', e);
    }
}, { immediate: true });
const isAdmin = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return false;
    const rawRoles = user.roles || [];
    const roles = rawRoles.map(r => r ? (typeof r === 'string' ? r : (r.name || '')) : '');
    return roles.includes('Super_Admin') || roles.includes('Admin');
});

const currentObject = ref(null);
const currentView = ref('objects');
const selectedBuilding = ref(null);
const selectedFloor = ref(null);
const activeTab = ref('blueprint');
const selectedPodyezdFilter = ref(null);

// Unit selection state (must be declared before watchers that reference them)
const selectedUnit = ref(null);
const activeRightPanel = ref('details'); // 'details' or 'calculator'
const calcPrice = ref(0);
const calcPricePerSqm = ref(0);
const calcDownPayment = ref(0);
const calcTermMonths = ref(12);
const calcDiscount = ref(0);
const calcDiscountPerSqm = ref(0);
const selectedUnitForFullscreenBlueprint = ref(null);

function onPriceInput() {
    const area = Number(selectedUnit.value?.area_sqm) || 0;
    calcPricePerSqm.value = area > 0 ? Math.round(calcPrice.value / area) : 0;
}

function onPricePerSqmInput() {
    const area = Number(selectedUnit.value?.area_sqm) || 0;
    calcPrice.value = Math.round(calcPricePerSqm.value * area);
}

function onDiscountInput() {
    const area = Number(selectedUnit.value?.area_sqm) || 0;
    calcDiscountPerSqm.value = area > 0 ? Math.round(calcDiscount.value / area) : 0;
}

function onDiscountPerSqmInput() {
    const area = Number(selectedUnit.value?.area_sqm) || 0;
    calcDiscount.value = Math.round(calcDiscountPerSqm.value * area);
}

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
    calcPricePerSqm.value = unit.area_sqm > 0 ? Math.round(unit.price / unit.area_sqm) : 0;
    calcDownPayment.value = 0;
    calcTermMonths.value = 12;
    calcDiscount.value = 0;
    calcDiscountPerSqm.value = 0;
}

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
const activeViewMode = ref('split');
const zoomScale = ref(1);

function zoomIn() {
    if (zoomScale.value < 3.5) {
        zoomScale.value += 0.25;
    }
}

function zoomOut() {
    if (zoomScale.value > 0.5) {
        zoomScale.value -= 0.25;
    }
}

function zoomReset() {
    zoomScale.value = 1;
}

function shareUnitDetails() {
    if (!selectedUnitForFullscreenBlueprint.value) return;
    const unit = selectedUnitForFullscreenBlueprint.value;
    const floor = selectedFloor.value?.floor_number || '';
    const building = selectedBuilding.value?.name || '';
    const typeLabel = unit.type === 'commercial' ? "Do'kon" : "Xonadon";
    const formattedUnitNumber = (unit.type === 'commercial' && /^\d+$/.test(String(unit.unit_number).trim()) ? 'D-' : '') + unit.unit_number;
    const text = `🏢 ${building}
🔑 ${typeLabel}: № ${formattedUnitNumber}
🚪 Xonalar soni: ${unit.rooms_count || 1} xonali
🏢 Qavat: ${floor}-qavat
📐 Maydoni: ${unit.area_sqm} m²
🧱 Podezd: ${unit.podyezd}-padezd
💰 Narxi: ${formatSoM(unit.price)}
🟢 Holati: ${unit.status === 'available' ? 'Sotuvda' : unit.status === 'reserved' ? 'Band qilingan' : 'Sotilgan'}`;

    navigator.clipboard.writeText(text).then(() => {
        alert(`${typeLabel} ma'lumotlari nusxalandi!`);
    }).catch(err => {
        console.error("Nusxalashda xatolik:", err);
    });
}

function downloadActiveImage() {
    const activeSrc = activeViewMode.value === '3d' && fullscreen3DModelSrc.value 
        ? fullscreen3DModelSrc.value 
        : fullscreenBlueprintSrc.value;
    
    if (!activeSrc) return;
    
    const link = document.createElement('a');
    link.href = activeSrc;
    link.download = `xonadon-${selectedUnitForFullscreenBlueprint.value?.unit_number || ''}-${activeViewMode.value}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function sellFromBlueprint() {
    showBlueprintFullscreen.value = false;
    openSellUnit(selectedUnitForFullscreenBlueprint.value);
}

const showBuildingPhotoFullscreen = ref(false);
const selectedFloorIdForBlueprint = ref(null);

const buildingPhotoFullscreenSrc = computed(() => {
    if (!selectedBuilding.value) return '/modern_apartment_render.png';
    return selectedBuilding.value.photo_path 
        ? `/storage/${selectedBuilding.value.photo_path}` 
        : getObjectPhoto(groupedObjects.value[currentObject.value] || []);
});

function openBuildingPhotoFullscreen() {
    showBuildingPhotoFullscreen.value = true;
}

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
    selectedUnitForFullscreenBlueprint.value = null;
    activeViewMode.value = '2d';
    zoomScale.value = 1;
    showBlueprintFullscreen.value = true;
}

function isUnitMatchingFilters(unit) {
    if (!unit) return false;
    
    // 1. Search Query Match
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase().trim();
        const numStr = String(unit.unit_number || '').toLowerCase();
        const statusStr = String(unit.status || '').toLowerCase();
        const roomsStr = (unit.rooms_count ? `${unit.rooms_count} xona` : '').toLowerCase();
        
        let statusUz = '';
        if (unit.status === 'available') statusUz = "bo'sh";
        else if (unit.status === 'sold') statusUz = 'sotilgan';
        else if (unit.status === 'reserved') statusUz = 'band';

        const match = numStr.includes(query) || 
                      statusStr.includes(query) || 
                      statusUz.includes(query) || 
                      roomsStr.includes(query) ||
                      String(unit.rooms_count || '').includes(query);
                      
        if (!match) return false;
    }
    
    // 2. Status filter (from global search/filter panel)
    if (selectedStatusFilter.value && unit.status !== selectedStatusFilter.value) {
        return false;
    }
    
    // 3. Rooms filter (from global search/filter panel)
    if (selectedRoomsFilter.value) {
        const roomsVal = Number(selectedRoomsFilter.value);
        const uRooms = unit.rooms_count || 1;
        const matchRooms = roomsVal === 5 ? uRooms >= 5 : uRooms === roomsVal;
        if (!matchRooms) return false;
    }

    // 4. Status filter (from chessboard local filters)
    if (activeFilters.value.status && unit.status !== activeFilters.value.status) {
        return false;
    }
    
    // 5. Rooms count filter (from chessboard local filters)
    if (activeFilters.value.rooms.length > 0) {
        const rooms = unit.rooms_count || 1;
        if (!activeFilters.value.rooms.includes(rooms)) return false;
    }
    
    // 6. Area filter (from chessboard local filters)
    if (activeFilters.value.minArea !== null && activeFilters.value.minArea !== '') {
        if (unit.area_sqm < Number(activeFilters.value.minArea)) return false;
    }
    if (activeFilters.value.maxArea !== null && activeFilters.value.maxArea !== '') {
        if (unit.area_sqm > Number(activeFilters.value.maxArea)) return false;
    }
    
    // 7. Price filter (from chessboard local filters)
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

const podyezdsToShow = computed(() => {
    if (!selectedBuilding.value) return [];
    if (selectedBuilding.value.podezes && selectedBuilding.value.podezes.length > 0) {
        let all = [...selectedBuilding.value.podezes].sort((a, b) => a.podez_number - b.podez_number);
        if (selectedPodyezdFilter.value) {
            all = all.filter(p => p.podez_number === selectedPodyezdFilter.value);
        }
        return all;
    }
    
    const count = selectedBuilding.value.entrances_count || 1;
    let allNumbers = Array.from({ length: count }, (_, i) => i + 1);
    if (selectedPodyezdFilter.value && allNumbers.includes(selectedPodyezdFilter.value)) {
        allNumbers = [selectedPodyezdFilter.value];
    }
    return allNumbers.map(num => ({
        id: 'legacy-' + num,
        podez_number: num,
        podez_name: num + '-podyezd',
        podez_type: 'Staircase',
        total_floors: selectedBuilding.value.total_floors
    }));
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

// Safe localStorage helpers to prevent crashes in private mode or strict security browsers
function safeGetItem(key) {
    try {
        return typeof window !== 'undefined' ? localStorage.getItem(key) : null;
    } catch (e) {
        return null;
    }
}

function safeSetItem(key, value) {
    try {
        if (typeof window !== 'undefined') localStorage.setItem(key, value);
    } catch (e) {}
}

function safeRemoveItem(key) {
    try {
        if (typeof window !== 'undefined') localStorage.removeItem(key);
    } catch (e) {}
}

// Simple debounce helper function
function debounce(fn, delay) {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
}

// Synchronize activeTab and view/object parameters with the URL reactively
watch(
    () => page.url,
    (newUrl) => {
        if (typeof window === 'undefined') return;
        try {
            const url = new URL(newUrl, window.location.origin);
            const tabParam = url.searchParams.get('tab');
            if (tabParam !== null) {
                if (activeTab.value !== tabParam) {
                    activeTab.value = tabParam;
                }
            }

            const viewParam = url.searchParams.get('view');
            const objectParam = url.searchParams.get('object');
            const buildingIdParam = url.searchParams.get('building_id');
            const podyezdParam = url.searchParams.get('podyezd');

            if (buildingIdParam) {
                const bId = Number(buildingIdParam);
                const foundB = (props.buildings || []).find(b => b.id === bId);
                if (foundB) {
                    selectedBuilding.value = foundB;
                    currentObject.value = foundB.name;
                    currentView.value = 'blocks';
                }
            } else {
                if (viewParam) {
                    currentView.value = viewParam;
                } else {
                    currentView.value = currentObject.value ? 'object_detail' : 'objects';
                }

                if (viewParam === 'objects') {
                    currentObject.value = null;
                    selectedBuilding.value = null;
                } else if (viewParam === 'object_detail') {
                    if (objectParam) {
                        currentObject.value = objectParam;
                    } else if (!currentObject.value) {
                        currentObject.value = (page.props.branding?.app_name || 'SHER-DOR') + ' TJM';
                    }
                    selectedBuilding.value = null;
                } else if (viewParam === 'blocks') {
                    if (objectParam) {
                        currentObject.value = objectParam;
                    } else {
                        currentObject.value = null;
                    }
                }
            }

            if (podyezdParam) {
                selectedPodyezdFilter.value = Number(podyezdParam);
            } else {
                selectedPodyezdFilter.value = null;
            }
        } catch (e) {
            console.error('URL parse error in Facility tab/view watcher', e);
        }
    },
    { immediate: true }
);

// Synchronize URL query parameter with activeTab changes inside the component
watch(activeTab, (newTab) => {
    if (typeof window === 'undefined') return;
    try {
        const url = new URL(window.location.href);
        const currentTab = url.searchParams.get('tab');
        if (currentTab === null || currentTab !== newTab) {
            url.searchParams.set('tab', newTab);
            window.history.replaceState(
                { ...window.history.state },
                '',
                url.pathname + url.search
            );
        }
    } catch (e) {
        console.error('URL replace error in Facility activeTab watcher', e);
    }
});

// Synchronize URL query parameters with currentObject, selectedBuilding, and selectedPodyezdFilter changes inside the component
watch([currentObject, selectedBuilding, selectedPodyezdFilter], ([newObj, newBuilding, newPodyezd]) => {
    if (typeof window === 'undefined') return;
    try {
        const url = new URL(window.location.href);
        const currentViewParam = url.searchParams.get('view');
        const currentObjectParam = url.searchParams.get('object');
        const currentBuildingIdParam = url.searchParams.get('building_id');
        const currentPodyezdParam = url.searchParams.get('podyezd');
        
        let targetView = null;
        let targetObject = null;
        let targetBuildingId = null;
        let targetPodyezd = null;
        
        if (currentView.value === 'report') {
            targetView = 'report';
        } else if (newObj === null) {
            if (currentView.value === 'blocks') {
                targetView = 'blocks';
            } else {
                targetView = 'objects';
            }
        } else if (newBuilding === null) {
            targetView = 'object_detail';
            targetObject = newObj;
        } else {
            targetView = 'blocks';
            targetObject = newObj;
            targetBuildingId = String(newBuilding.id);
        }

        if (newPodyezd) {
            targetPodyezd = String(newPodyezd);
        }
        
        if (
            currentViewParam !== targetView || 
            currentObjectParam !== targetObject ||
            currentBuildingIdParam !== targetBuildingId ||
            currentPodyezdParam !== targetPodyezd
        ) {
            if (targetView) {
                url.searchParams.set('view', targetView);
            } else {
                url.searchParams.delete('view');
            }
            
            if (targetObject) {
                url.searchParams.set('object', targetObject);
            } else {
                url.searchParams.delete('object');
            }

            if (targetBuildingId) {
                url.searchParams.set('building_id', targetBuildingId);
            } else {
                url.searchParams.delete('building_id');
            }

            if (targetPodyezd) {
                url.searchParams.set('podyezd', targetPodyezd);
            } else {
                url.searchParams.delete('podyezd');
            }
            
            window.history.replaceState(
                { ...window.history.state },
                '',
                url.pathname + url.search
            );
        }
    } catch (e) {
        console.error('URL replace error in Facility view sync watcher', e);
    }
});

// Save selections in localStorage to survive F5/refresh and form updates in a unified watcher
watch([currentObject, selectedBuilding, selectedFloor, selectedUnit], ([newObj, newBuilding, newFloor, newUnit], [oldObj, oldBuilding, oldFloor, oldUnit]) => {
    if (typeof window === 'undefined') return;
    
    if (newObj !== oldObj) {
        if (newObj) {
            safeSetItem('fac_current_object', newObj);
        } else {
            safeRemoveItem('fac_current_object');
        }
    }
    
    if (newBuilding !== oldBuilding) {
        if (newBuilding) {
            safeSetItem('fac_building_id', newBuilding.id);
        } else {
            safeRemoveItem('fac_building_id');
            safeRemoveItem('fac_floor_id');
            safeRemoveItem('fac_unit_id');
        }
    }
    
    if (newFloor !== oldFloor) {
        if (newFloor) {
            safeSetItem('fac_floor_id', newFloor.id);
        } else {
            safeRemoveItem('fac_floor_id');
        }
    }
    
    if (newUnit !== oldUnit) {
        if (newUnit) {
            safeSetItem('fac_unit_id', newUnit.id);
        } else {
            safeRemoveItem('fac_unit_id');
        }
    }
});

// Restore selections on initial mount
onMounted(() => {
    if (typeof window === 'undefined') return;
    
    try {
        const url = new URL(window.location.href);
        const viewParam = url.searchParams.get('view');
        
        // Only skip restoring if the view is specifically 'report' or 'objects' (layout not shown)
        if (viewParam === 'report' || viewParam === 'objects') {
            return;
        }

        const savedObject = safeGetItem('fac_current_object');
        const savedBuildingId = safeGetItem('fac_building_id');
        const savedFloorId = safeGetItem('fac_floor_id');
        const savedUnitId = safeGetItem('fac_unit_id');
        
        if (savedObject) {
            currentObject.value = savedObject;
        }
        
        // Try to read building_id from URL query first, fallback to savedBuildingId
        const urlBuildingId = url.searchParams.get('building_id');
        const activeBuildingId = urlBuildingId ? Number(urlBuildingId) : (savedBuildingId ? Number(savedBuildingId) : null);

        if (props.buildings && Array.isArray(props.buildings) && props.buildings.length > 0) {
            if (activeBuildingId) {
                const foundB = props.buildings.find(b => b.id === activeBuildingId);
                if (foundB) {
                    selectedBuilding.value = foundB;
                    
                    if (savedFloorId) {
                        const fId = Number(savedFloorId);
                        const foundF = foundB.floors?.find(f => f.id === fId);
                        if (foundF) {
                            selectedFloor.value = foundF;
                            
                            if (savedUnitId && foundF.units && Array.isArray(foundF.units)) {
                                const uId = Number(savedUnitId);
                                const foundU = foundF.units.find(u => u.id === uId);
                                if (foundU && typeof selectUnit === 'function') {
                                    // Use nextTick / timeout to allow DOM layout before select
                                    setTimeout(() => {
                                        selectUnit(foundU);
                                    }, 100);
                                }
                            }
                        }
                    }
                }
            }
        }
    } catch (e) {
        console.error("Error restoring facility state on mount:", e);
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

// Watch for building changes to select the first floor, while respecting localStorage parameters on initial load
watch(selectedBuilding, (newBuilding, oldBuilding) => {
    selectedFloorIdForBlueprint.value = null;
    if (!newBuilding) {
        selectedFloor.value = null;
        selectedUnit.value = null;
        return;
    }
    
    // Only reset floor/unit if we switched to a different building
    if (!oldBuilding || newBuilding.id !== oldBuilding.id) {
        const savedFloorId = safeGetItem('fac_floor_id');
        const savedUnitId = safeGetItem('fac_unit_id');
        
        if (savedFloorId && newBuilding.floors) {
            const foundF = newBuilding.floors.find(f => f.id === Number(savedFloorId));
            if (foundF) {
                selectedFloor.value = foundF;
                
                if (savedUnitId && foundF.units) {
                    const foundU = foundF.units.find(u => u.id === Number(savedUnitId));
                    if (foundU) {
                        selectUnit(foundU);
                    }
                }
                return;
            }
        }
        
        if (newBuilding.floors && newBuilding.floors.length > 0) {
            selectedFloor.value = newBuilding.floors[0];
        } else {
            selectedFloor.value = null;
        }
        selectedUnit.value = null;
    } else {
        // If same building (updated metadata), sync selectedFloor/selectedUnit to the updated instances
        if (selectedFloor.value && newBuilding.floors) {
            const updatedFloor = newBuilding.floors.find(f => f.id === selectedFloor.value.id);
            if (updatedFloor) {
                selectedFloor.value = updatedFloor;
                if (selectedUnit.value && updatedFloor.units) {
                    const updatedUnit = updatedFloor.units.find(u => u.id === selectedUnit.value.id);
                    if (updatedUnit) {
                        selectedUnit.value = updatedUnit;
                    }
                }
            } else {
                selectedFloor.value = newBuilding.floors[0] || null;
                selectedUnit.value = null;
            }
        }
    }
});

// Reactively update selectedBuilding when props.buildings updates from the server (shallow watch without deep: true)
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
                        for (const f of updated.floors) {
                            if (f.units) {
                                const u = f.units.find(unit => unit.id === selectedUnit.value.id);
                                if (u) {
                                    found = u;
                                    break;
                                }
                            }
                        }
                    }
                    if (found) {
                        selectedUnit.value = found;
                    }
                }
            }
        }
    }
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



// Recalculate total_shops and total_apartments from floors_data sum with a 300ms debounce
watch(
    () => buildingForm.floors_data,
    debounce((floors) => {
        if (!floors) return;
        let sumShops = 0;
        let sumApartments = 0;
        floors.forEach(item => {
            sumShops += parseInt(item.shops_count) || 0;
            sumApartments += parseInt(item.apartments_count) || 0;
        });
        buildingForm.total_shops = sumShops;
        buildingForm.total_apartments = sumApartments;
    }, 300),
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



const searchQuery = ref('');
const reportSearchQuery = ref('');
const startMonth = ref(props.filters?.start_month || '');
const endMonth = ref(props.filters?.end_month || '');
const showExcelPreviewModal = ref(false);

const filteredReportData = computed(() => {
    if (!props.reportData) return [];
    const query = reportSearchQuery.value.toLowerCase().trim();
    if (!query) return props.reportData;
    return props.reportData.filter(row => (row.name || '').toLowerCase().includes(query));
});

function fetchReportWithDates() {
    router.get(route('admin.facility'), { preserveScroll: true, preserveState: true,
        view: 'report',
        start_month: startMonth.value,
        end_month: endMonth.value
    }, {
        preserveState: true,
        preserveScroll: true
    });
}

watch([startMonth, endMonth], () => {
    fetchReportWithDates();
});
const selectedCity = ref('');
const selectedDistrict = ref('');
const selectedRoomsFilter = ref('');
const selectedStatusFilter = ref('');

const cities = computed(() => {
    if (!props.buildings) return [];
    return [...new Set(props.buildings.map(b => b.city).filter(Boolean))].sort();
});

const districts = computed(() => {
    if (!props.buildings) return [];
    let filtered = props.buildings;
    if (selectedCity.value) {
        filtered = filtered.filter(b => b.city === selectedCity.value);
    }
    return [...new Set(filtered.map(b => b.district).filter(Boolean))].sort();
});

const filteredBuildings = computed(() => {
    if (!props.buildings) return [];
    return props.buildings.filter(b => {
        // 1. Search Query
        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase().trim();
            const nameMatch = (b.name || '').toLowerCase().includes(query);
            const cityMatch = (b.city || '').toLowerCase().includes(query);
            const districtMatch = (b.district || '').toLowerCase().includes(query);
            const blockMatch = (b.block_name || '').toLowerCase().includes(query) || (b.corpus_name || '').toLowerCase().includes(query);
            
            // Check inside units (apartments) for unit_number, status, rooms_count
            const unitMatch = b.floors?.some(f => f.units?.some(u => {
                const numStr = String(u.unit_number || '').toLowerCase();
                const statusStr = String(u.status || '').toLowerCase();
                const roomsStr = (u.rooms_count ? `${u.rooms_count} xona` : '').toLowerCase();
                
                // Status translations in Uzbek
                let statusUz = '';
                if (u.status === 'available') statusUz = "bo'sh";
                else if (u.status === 'sold') statusUz = 'sotilgan';
                else if (u.status === 'reserved') statusUz = 'band';

                return numStr.includes(query) || 
                       statusStr.includes(query) || 
                       statusUz.includes(query) || 
                       roomsStr.includes(query) ||
                       String(u.rooms_count || '').includes(query);
            })) || false;

            if (!nameMatch && !cityMatch && !districtMatch && !blockMatch && !unitMatch) {
                return false;
            }
        }

        // 2. City Filter
        if (!currentObject.value && selectedCity.value && b.city !== selectedCity.value) {
            return false;
        }

        // 3. District Filter
        if (!currentObject.value && selectedDistrict.value && b.district !== selectedDistrict.value) {
            return false;
        }

        // 4. Rooms Filter
        if (selectedRoomsFilter.value) {
            const roomsVal = Number(selectedRoomsFilter.value);
            const hasMatchingRooms = b.floors?.some(f => f.units?.some(u => {
                const uRooms = u.rooms_count || 1;
                return roomsVal === 5 ? uRooms >= 5 : uRooms === roomsVal;
            })) || false;
            if (!hasMatchingRooms) return false;
        }

        // 5. Status Filter
        if (selectedStatusFilter.value) {
            const hasMatchingStatus = b.floors?.some(f => f.units?.some(u => u.status === selectedStatusFilter.value)) || false;
            if (!hasMatchingStatus) return false;
        }

        return true;
    });
});

const expandedObjects = ref({});
const groupedObjects = computed(() => {
    const groups = {};
    filteredBuildings.value.forEach(b => {
        const objName = b.name || 'Boshqa';
        if (!groups[objName]) {
            groups[objName] = [];
        }
        groups[objName].push(b);
    });
    return groups;
});

const showShowroomModal = ref(false);
const activeShowroomImageIndex = ref(0);
const showroomManageMode = ref(false);
const isUploadingShowroom = ref(false);
const showroomUploadForm = useForm({
    images: []
});

const currentObjectContainer = computed(() => {
    const list = groupedObjects.value[currentObject.value] || [];
    let container = list.find(b => b.total_floors === 0);
    if (!container && list.length > 0) {
        container = list[0];
    }
    return container;
});

function openShowroomModal() {
    showShowroomModal.value = true;
    activeShowroomImageIndex.value = 0;
    showroomManageMode.value = false;
}

function handleShowroomImagesChange(e) {
    showroomUploadForm.images = Array.from(e.target.files);
}

function submitShowroomImages() {
    if (!showroomUploadForm.images.length) return;
    isUploadingShowroom.value = true;
    
    showroomUploadForm.post(route('admin.facility.buildings.showroom-images.store', currentObjectContainer.value.id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            showroomUploadForm.reset();
            isUploadingShowroom.value = false;
            showroomManageMode.value = false;
            activeShowroomImageIndex.value = 0;
        },
        onError: (err) => {
            isUploadingShowroom.value = false;
            alert('Rasmlar yuklashda xatolik yuz berdi: ' + Object.values(err).join(', '));
        }
    });
}

function deleteShowroomImage(path) {
    if (confirm("Ushbu showroom rasmini o'chirishni xohlaysizmi?")) {
        router.delete(route('admin.facility.buildings.showroom-images.destroy', currentObjectContainer.value.id), {
            data: { image_path: path },
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                activeShowroomImageIndex.value = 0;
            }
        });
    }
}

const handleKeyDown = (e) => {
    if (!showShowroomModal.value) return;
    if (showroomManageMode.value) return;

    if (e.key === 'Escape') {
        showShowroomModal.value = false;
    } else if (e.key === 'ArrowRight' || e.key === 'Right') {
        const imgs = currentObjectContainer.value?.showroom_images || [];
        if (imgs.length > 1) {
            activeShowroomImageIndex.value = (activeShowroomImageIndex.value + 1) % imgs.length;
        }
    } else if (e.key === 'ArrowLeft' || e.key === 'Left') {
        const imgs = currentObjectContainer.value?.showroom_images || [];
        if (imgs.length > 1) {
            activeShowroomImageIndex.value = (activeShowroomImageIndex.value - 1 + imgs.length) % imgs.length;
        }
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});

function toggleObject(objName) {
    expandedObjects.value[objName] = !expandedObjects.value[objName];
}

function openAddBlock(objName) {
    // Legacy function, replaced by Link
}

function getObjectContainerId(objName) {
    const list = groupedObjects.value[objName] || [];
    const container = list.find(b => b.total_floors === 0);
    return container ? container.id : (list[0] ? list[0].id : 0);
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
            const unitNumber = (unit.type === 'commercial' && /^\d+$/.test(String(unit.unit_number).trim()) ? 'D-' : '') + unit.unit_number;
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

// Wizard and payment state variables
const sellStep = ref(1);
const installmentSubtype = ref('term'); // 'term' | 'mortgage'
const installmentCalculationType = ref('schedule'); // 'schedule' | 'manual'
const basePricePerSqm = ref(0);
const discountPerSqm = ref(0);
const initialDownPaymentPaid = ref('');
const initialInstallmentPaid = ref('');

const contractForm = ref({
    buyer_type: 'client',
    partner_id: '',
    client_id: '',
    employee_id: '',
    unit_id: '',
    contract_number: '',
    contract_type: 'residential',
    price_per_sqm: '',
    total_amount: '',
    down_payment: '',
    is_down_payment_split: false,
    down_payment_split_months: 1,
    term_months: 12,
    payment_type: 'installment',
    created_at: '',
    actual_paid_amount: '',
    payment_method: 'bank',
    account_id: '',
    floor: '',
    rooms: '',
    custom_monthly_amount: ''
});

const usersOptions = computed(() => {
    return (props.users || []).map(u => ({
        value: u.id,
        label: u.name
    }));
});

const downPaymentPercent = computed(() => {
    const total = Number(contractForm.value.total_amount) || 0;
    if (total <= 0) return 0;
    return Math.round((Number(contractForm.value.down_payment) / total) * 100);
});

const totalDiscount = computed(() => {
    const area = Number(currentSellingUnit.value?.area_sqm) || 0;
    return (Number(discountPerSqm.value) || 0) * area;
});

function openSellUnit(unit) {
    currentSellingUnit.value = unit;
    showAddUnitModal.value = false;
    sellStep.value = 1;
    installmentSubtype.value = 'term';
    installmentCalculationType.value = 'schedule';
    discountPerSqm.value = 0;
    initialDownPaymentPaid.value = '';
    initialInstallmentPaid.value = '';
    
    contractForm.value = {
        buyer_type: 'client',
        partner_id: '',
        client_id: '',
        employee_id: '',
        unit_id: unit.id,
        contract_number: '',
        contract_type: unit.type === 'commercial' ? 'commercial' : 'residential',
        price_per_sqm: '',
        total_amount: unit.price || '',
        down_payment: '',
        is_down_payment_split: false,
        down_payment_split_months: 1,
        term_months: 12,
        payment_type: 'installment',
        created_at: new Date().toISOString().split('T')[0], // Default to today's date
        actual_paid_amount: '',
        payment_method: 'bank',
        account_id: '',
        floor: selectedFloor.value?.floor_number || '',
        rooms: unit.rooms_count || '',
        custom_monthly_amount: ''
    };
    
    if (unit.price && unit.area_sqm) {
        basePricePerSqm.value = Number((Number(unit.price) / Number(unit.area_sqm)).toFixed(2));
        contractForm.value.price_per_sqm = basePricePerSqm.value;
    } else {
        basePricePerSqm.value = 0;
        contractForm.value.price_per_sqm = '';
    }
    
    activeRightPanel.value = 'sell';
}

// Watch actual price_per_sqm changes to update total_amount
watch(() => contractForm.value.price_per_sqm, (newPrice) => {
    if (currentSellingUnit.value) {
        const area = Number(currentSellingUnit.value.area_sqm) || 0;
        const total = Number(newPrice) * area;
        if (Math.abs(Number(contractForm.value.total_amount) - total) > 1) {
            contractForm.value.total_amount = total.toFixed(2);
        }
    }
});

// Watch total_amount changes to update price_per_sqm
watch(() => contractForm.value.total_amount, (newTotal) => {
    if (currentSellingUnit.value) {
        const area = Number(currentSellingUnit.value.area_sqm) || 0;
        if (area > 0) {
            const price = Number(newTotal) / area;
            if (Math.abs(Number(contractForm.value.price_per_sqm) - price) > 0.01) {
                contractForm.value.price_per_sqm = price.toFixed(2);
                basePricePerSqm.value = Number((price + discountPerSqm.value).toFixed(2));
            }
        }
        
        if (contractForm.value.payment_type === 'full') {
            contractForm.value.down_payment = newTotal;
            contractForm.value.term_months = 1;
        }
    }
});

// Watch basePricePerSqm and discountPerSqm changes to update final price_per_sqm
watch([basePricePerSqm, discountPerSqm], ([newBase, newDiscount]) => {
    if (currentSellingUnit.value) {
        const actualPrice = (Number(newBase) || 0) - (Number(newDiscount) || 0);
        contractForm.value.price_per_sqm = actualPrice.toFixed(2);
    }
});

// Watch initial down payment and installment paid to sum up actual_paid_amount
watch([initialDownPaymentPaid, initialInstallmentPaid], ([newDown, newInst]) => {
    contractForm.value.actual_paid_amount = (Number(newDown) || 0) + (Number(newInst) || 0);
});

watch(() => contractForm.value.payment_type, (newType) => {
    if (newType === 'full') {
        contractForm.value.down_payment = contractForm.value.total_amount;
        contractForm.value.term_months = 1;
        installmentCalculationType.value = 'schedule';
        contractForm.value.custom_monthly_amount = '';
    } else {
        contractForm.value.down_payment = '';
        contractForm.value.term_months = 12;
    }
});

watch(() => contractForm.value.account_id, (newAccountId) => {
    if (newAccountId && props.registers) {
        const reg = props.registers.find(r => r.id === newAccountId);
        if (reg) {
            if (reg.type === 'bank') {
                contractForm.value.payment_method = 'bank';
            } else if (reg.type === 'cash') {
                contractForm.value.payment_method = 'main';
            } else {
                contractForm.value.payment_method = 'credit';
            }
        }
    }
}, { immediate: true });

watch(() => contractForm.value.actual_paid_amount, (newAmount) => {
    if (Number(newAmount) > 0 && !contractForm.value.account_id) {
        if (props.registers && props.registers.length > 0) {
            contractForm.value.account_id = props.registers[0].id;
        }
    }
});

const formattedCalculatedMonthlyPayment = computed(() => {
    if (contractForm.value.payment_type === 'full') return 0;
    const total = Number(contractForm.value.total_amount) || 0;
    const down = Number(contractForm.value.down_payment) || 0;
    const remaining = total - down;
    const months = Number(contractForm.value.term_months) || 1;
    return Math.round(remaining / months);
});

const formattedCustomMonthlyPayment = computed(() => {
    return Number(contractForm.value.custom_monthly_amount) || 0;
});

function nextSellStep() {
    if (sellStep.value === 1) {
        if (!contractForm.value.created_at || !contractForm.value.contract_number) {
            alert("Iltimos, shartnoma sanasi va raqamini kiriting!");
            return;
        }
        if (contractForm.value.buyer_type === 'client' && !contractForm.value.client_id) {
            alert("Iltimos, mijozni tanlang!");
            return;
        }
        if (contractForm.value.buyer_type === 'partner' && !contractForm.value.partner_id) {
            alert("Iltimos, ta'sischini tanlang!");
            return;
        }
        if (contractForm.value.buyer_type === 'employee' && !contractForm.value.employee_id) {
            alert("Iltimos, xodimni tanlang!");
            return;
        }
        if (!contractForm.value.total_amount || !contractForm.value.price_per_sqm) {
            alert("Iltimos, narx ma'lumotlarini to'ldiring!");
            return;
        }
        sellStep.value = 2;
    } else if (sellStep.value === 2) {
        if (contractForm.value.payment_type === 'installment' && installmentCalculationType.value === 'manual' && !contractForm.value.custom_monthly_amount) {
            alert("Iltimos, oylik to'lov miqdorini kiriting!");
            return;
        }
        sellStep.value = 3;
    }
}

function prevSellStep() {
    if (sellStep.value > 1) {
        sellStep.value--;
    }
}

function submitContract() {
    isProcessingContract.value = true;
    
    // Clear the opposite ID depending on buyer_type
    if (contractForm.value.buyer_type === 'client') {
        contractForm.value.partner_id = null;
        contractForm.value.employee_id = null;
    } else if (contractForm.value.buyer_type === 'partner') {
        contractForm.value.client_id = null;
        contractForm.value.employee_id = null;
    } else if (contractForm.value.buyer_type === 'employee') {
        contractForm.value.client_id = null;
        contractForm.value.partner_id = null;
    }

    // Reset custom_monthly_amount if calculation type is not manual or payment type is full
    if (contractForm.value.payment_type !== 'installment' || installmentCalculationType.value !== 'manual') {
        contractForm.value.custom_monthly_amount = '';
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

const showUnifiedSellModal = ref(false);
const isProcessingUnified = ref(false);
const currentStep = ref(1);

function nextStep() {
    if (currentStep.value === 1) {
        if (!quickClientForm.value.first_name || !quickClientForm.value.last_name || !quickClientForm.value.phone) {
            alert("Iltimos, barcha majburiy maydonlarni to'ldiring (Ism, Familiya, Telefon)!");
            return;
        }
        currentStep.value = 2;
    } else if (currentStep.value === 2) {
        if (!quickClientForm.value.source) {
            alert("Iltimos, 'Biz haqimizda qayerdan bildingiz?' maydonini to'ldiring!");
            return;
        }
        currentStep.value = 3;
    }
}

function prevStep() {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
}
const quickClientForm = ref({
    first_name: '',
    last_name: '',
    middle_name: '',
    passport_series: '',
    pinfl: '',
    phone: '+998',
    address: '',
    passport_issued_date: '',
    passport_issued_by: '',
    created_at: new Date().toISOString().split('T')[0],
    birth_date: '',
    phone_2: '+998',
    gender: 'erkak',
    region: '',
    district: '',
    passport_photo: null,
    passport_photo_back: null,
    profile_photo: null,
    source: '',
    contract_language: 'O\'zbek tili',
    contract_id: ''
});

const availableQuickDistricts = computed(() => {
    let r = quickClientForm.value.region;
    if (!r) return [];
    r = r.toLowerCase();
    let foundRegion = regionsData[r];
    if (!foundRegion) {
        foundRegion = Object.values(regionsData).find(
            item => item.name.toLowerCase() === r || item.name.toLowerCase().replace(/\s+sh\.?$/, '') === r
        );
    }
    if (!foundRegion) return [];
    return Object.entries(foundRegion.districts).map(([key, value]) => ({
        id: key,
        name: value.name
    }));
});

const availableQuickNeighborhoods = computed(() => {
    let r = quickClientForm.value.region;
    if (!r) return [];
    r = r.toLowerCase();
    let foundRegion = regionsData[r];
    if (!foundRegion) {
        foundRegion = Object.values(regionsData).find(
            item => item.name.toLowerCase() === r || item.name.toLowerCase().replace(/\s+sh\.?$/, '') === r
        );
    }
    if (!foundRegion) return [];

    let d = quickClientForm.value.district;
    if (!d) return [];
    d = d.toLowerCase();

    let foundDistrict = foundRegion.districts[d];
    if (!foundDistrict) {
        foundDistrict = Object.values(foundRegion.districts).find(
            item => item.name.toLowerCase() === d || item.name.toLowerCase().replace(/\s+tumani$/, '') === d
        );
    }

    if (!foundDistrict) return [];
    return foundDistrict.neighborhoods || [];
});

const loadedScripts = {};
function loadScript(src) {
    if (loadedScripts[src]) return loadedScripts[src];
    loadedScripts[src] = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => resolve();
        script.onerror = (err) => reject(err);
        document.head.appendChild(script);
    });
    return loadedScripts[src];
}

async function initFaceDetector() {
    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/tracking.js/1.1.3/tracking-min.js');
    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/tracking.js/1.1.3/data/face-min.js');
}

async function detectAndCropFace(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = async (e) => {
            const img = new Image();
            img.src = e.target.result;
            img.onload = async () => {
                try {
                    await initFaceDetector();
                    
                    const tracker = new window.tracking.ObjectTracker('face');
                    tracker.setInitialScale(4);
                    tracker.setStepSize(2);
                    tracker.setEdgesDensity(0.1);
                    
                    const tempImg = document.createElement('img');
                    tempImg.src = img.src;
                    tempImg.style.position = 'absolute';
                    tempImg.style.left = '-9999px';
                    tempImg.style.top = '-9999px';
                    document.body.appendChild(tempImg);
                    
                    tempImg.onload = () => {
                        const task = window.tracking.track(tempImg, tracker);
                        tracker.once('track', (event) => {
                            task.stop();
                            document.body.removeChild(tempImg);
                            
                            if (event.data && event.data.length > 0) {
                                const face = event.data[0];
                                const padX = face.width * 0.3;
                                const padY = face.height * 0.3;
                                
                                const cropX = Math.max(0, face.x - padX);
                                const cropY = Math.max(0, face.y - padY * 1.5);
                                const cropW = Math.min(img.width - cropX, face.width + padX * 2);
                                const cropH = Math.min(img.height - cropY, face.height + padY * 3);
                                
                                const canvas = document.createElement('canvas');
                                canvas.width = 160;
                                canvas.height = 160;
                                const ctx = canvas.getContext('2d');
                                ctx.drawImage(img, cropX, cropY, cropW, cropH, 0, 0, 160, 160);
                                
                                canvas.toBlob((blob) => {
                                    if (blob) {
                                        const croppedFile = new File([blob], 'face_' + file.name, { type: 'image/jpeg' });
                                        resolve(croppedFile);
                                    } else {
                                        resolve(null);
                                    }
                                }, 'image/jpeg', 0.9);
                            } else {
                                resolve(null);
                            }
                        });
                    };
                } catch (err) {
                    console.error("Face detection initialization failed:", err);
                    resolve(null);
                }
            };
        };
        reader.readAsDataURL(file);
    });
}

function getProfilePreviewUrl(file) {
    if (!file) return '';
    if (typeof file === 'string') return '/storage/' + file;
    return URL.createObjectURL(file);
}

async function onQuickPassportPhotoChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    quickClientForm.value.passport_photo = file;
    
    if (file.type && file.type.startsWith('image/')) {
        const croppedFace = await detectAndCropFace(file);
        if (croppedFace) {
            quickClientForm.value.profile_photo = croppedFace;
        }
    }
}

function onQuickRegionChange() {
    quickClientForm.value.district = '';
    quickClientForm.value.address = '';
}

function onQuickDistrictChange() {
    quickClientForm.value.address = '';
}

function openQuickClientAdd() {
    currentStep.value = 1;
    quickClientForm.value = {
        first_name: '',
        last_name: '',
        middle_name: '',
        passport_series: '',
        pinfl: '',
        phone: '+998',
        address: '',
        passport_issued_date: '',
        passport_issued_by: '',
        created_at: new Date().toISOString().split('T')[0],
        birth_date: '',
        phone_2: '+998',
        gender: 'erkak',
        region: '',
        district: '',
        passport_photo: null,
        passport_photo_back: null,
        profile_photo: null,
        source: '',
        contract_language: 'O\'zbek tili',
        contract_id: contractForm.value.contract_number || ''
    };
    showUnifiedSellModal.value = true;
}

function submitUnifiedSell() {
    isProcessingUnified.value = true;
    
    // Validate first name and last name
    if (!quickClientForm.value.first_name || !quickClientForm.value.last_name) {
        alert("Iltimos, mijoz familiyasi va ismini kiriting.");
        isProcessingUnified.value = false;
        return;
    }

    // Set contract ID to contractForm value if empty
    if (!quickClientForm.value.contract_id) {
        quickClientForm.value.contract_id = contractForm.value.contract_number;
    }
    
    // First, save the client via axios with FormData to support files
    const formData = new FormData();
    Object.entries(quickClientForm.value).forEach(([key, value]) => {
        if (value !== null && value !== undefined) {
            formData.append(key, value);
        }
    });

    axios.post(route('admin.crm.clients.store'), formData, { preserveScroll: true, preserveState: true,
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    })
        .then(response => {
            if (response.data && response.data.status === 'success') {
                const newClientId = response.data.client.id;
                
                // Now, prepare the contract form data
                const finalContractData = {
                    buyer_type: 'client',
                    client_id: newClientId,
                    partner_id: null,
                    unit_id: currentSellingUnit.value.id,
                    contract_number: contractForm.value.contract_number || quickClientForm.value.contract_id,
                    contract_type: currentSellingUnit.value.type === 'commercial' ? 'commercial' : 'residential',
                    price_per_sqm: contractForm.value.price_per_sqm,
                    total_amount: contractForm.value.total_amount,
                    down_payment: contractForm.value.down_payment || contractForm.value.total_amount,
                    term_months: contractForm.value.payment_type === 'full' ? 1 : contractForm.value.term_months,
                    payment_type: contractForm.value.payment_type,
                    created_at: contractForm.value.created_at || quickClientForm.value.created_at,
                    actual_paid_amount: contractForm.value.actual_paid_amount || (contractForm.value.payment_type === 'full' ? contractForm.value.total_amount : contractForm.value.down_payment),
                    payment_method: 'bank',
                    floor: selectedFloor.value?.floor_number || '',
                    rooms: currentSellingUnit.value.rooms_count || '',
                    is_down_payment_split: contractForm.value.is_down_payment_split || false,
                    down_payment_split_months: contractForm.value.is_down_payment_split ? (contractForm.value.down_payment_split_months || 1) : 1
                };

                // Submit contract using Inertia router so that page reloads and state resets cleanly!
                router.post(route('admin.contracts.store'), finalContractData, { preserveScroll: true, preserveState: true,
                    onSuccess: () => {
                        showUnifiedSellModal.value = false;
                        isProcessingUnified.value = false;
                        activeRightPanel.value = 'details';
                        selectedUnit.value = null; // Close side panel
                    },
                    onError: (errors) => {
                        isProcessingUnified.value = false;
                        alert("Shartnoma yaratishda xatolik: " + Object.values(errors).join("\n"));
                    }
                });
            } else {
                isProcessingUnified.value = false;
                alert("Mijoz yaratishda xatolik yuz berdi.");
            }
        })
        .catch(error => {
            isProcessingUnified.value = false;
            const msg = error.response?.data?.message || error.message || "Ulanishda xatolik.";
            alert("Mijozni saqlashda xatolik: " + msg);
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
    podezes: [],
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
    podez_id: null,
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
    has_balcony: false,
    blueprint: null,
    model_3d: null,
});

const isEditingBuilding = ref(false);
const isEditingObject = ref(false);
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
    })).post(route('admin.facility.buildings.update', uploadModelsForm.building_id), { preserveScroll: true, preserveState: true,
        onSuccess: () => {
            showUploadModelsModal.value = false;
            uploadModelsForm.reset();
        }
    });
}

function openEditBuilding(isObject = false) {
    if (isObject && typeof isObject === 'object') {
        isObject = false;
    }
    if (!selectedBuilding.value) return;
    isEditingBuilding.value = true;
    isEditingObject.value = isObject;
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
    buildingForm.is_object_only = (selectedBuilding.value.total_floors === 0 || isObject) ? 1 : 0;
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
    buildingForm.podezes = (selectedBuilding.value.podezes || []).map(p => ({
        id: p.id,
        name: p.podez_name,
        number: p.podez_number,
        floors: p.total_floors,
        type: p.podez_type || 'Staircase',
        apartments_per_floor: p.apartments_per_floor || 0,
        commercial_per_floor: p.commercial_per_floor || 0,
    }));
    if (buildingForm.podezes.length === 0) {
        buildingForm.podezes = [
            { id: 'temp_' + Date.now(), name: 'P-1', number: 1, floors: 5, type: 'Staircase', apartments_per_floor: 0, commercial_per_floor: 0 }
        ];
    }
    showAddBuildingModal.value = true;
}

function addPodezToForm() {
    const nextNum = buildingForm.podezes.length + 1;
    buildingForm.podezes.push({
        id: 'temp_' + Date.now() + '_' + Math.random(),
        name: 'P-' + nextNum,
        number: nextNum,
        floors: 5,
        type: 'Staircase',
        apartments_per_floor: 0,
        commercial_per_floor: 0,
    });
}

function removePodezFromForm(podezId) {
    if (buildingForm.podezes.length > 1) {
        const existingPodez = (selectedBuilding.value?.podezes || []).find(p => p.id === podezId);
        if (existingPodez) {
            let unitsCount = 0;
            if (existingPodez.floors) {
                existingPodez.floors.forEach(f => {
                    if (f.units) unitsCount += f.units.length;
                });
            }
            if (unitsCount > 0) {
                alert("Bu podezda kvartiralar bor! Avval kvartiralarni o'chiring.");
                return;
            }
        }
        buildingForm.podezes = buildingForm.podezes.filter(p => p.id !== podezId);
    }
}

function openEditObjectContainer(blocksList) {
    let container = (blocksList || []).find(b => b.total_floors === 0);
    if (!container && blocksList && blocksList.length > 0) {
        container = blocksList[0];
    }
    if (!container) return;
    selectedBuilding.value = container;
    openEditBuilding(true);
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

function deleteObject(blocksList, name) {
    let container = (blocksList || []).find(b => b.total_floors === 0);
    if (!container && blocksList && blocksList.length > 0) {
        container = blocksList[0];
    }
    if (!container) return;
    
    if (confirm(`Rostdan ham "${name}" obyektini to'liq o'chirib yubormoqchimisiz?\nUshbu amaliyot uning tarkibidagi barcha bloklar, qavatlar va xonadonlarni butunlay o'chirib tashlaydi!`)) {
        router.delete(route('admin.facility.objects.destroy', container.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                if (currentObject.value === name) {
                    currentObject.value = null;
                }
            }
        });
    }
}

function submitBuilding() {
    if (isEditingBuilding.value && selectedBuilding.value) {
        // use _method: 'put' for file upload with PUT in laravel
        buildingForm.transform((data) => ({
            ...data,
            is_object_only: isEditingObject.value ? 1 : 0,
            _method: 'put'
        })).post(route('admin.facility.buildings.update', selectedBuilding.value.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                showAddBuildingModal.value = false;
                isEditingBuilding.value = false;
                isEditingObject.value = false;
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

// selectedUnit, activeRightPanel, calc refs, selectUnit moved to top of script to avoid temporal dead zone

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

function printCalculatorDetails() {
    if (!selectedUnit.value) return;

    const unit = selectedUnit.value;
    const building = selectedBuilding.value;
    
    const cleanText = (txt) => (txt || '').replace(/sher-?dor(\s*tjm)?/gi, '').trim();
    const cleanedBuildingName = cleanText(building?.name) || 'Turar joy majmuasi';
    const rawBlock = unit.block_name || unit.corpus_name || building?.block_name || building?.corpus_name || 'Asosiy Blok';
    const cleanedBlockName = cleanText(rawBlock) || 'Asosiy Blok';
    
    const logoSrc = page.props.branding?.logo_path ? `/storage/${page.props.branding.logo_path}` : '';
    const buildingPhoto = building?.photo_path ? `/storage/${building.photo_path}` : '/modern_apartment_render.png';
    const showDiscountBadge = (Number(calcDiscount.value) || 0) > 0;

    const printWindow = window.open('', '_blank');
    if (!printWindow) {
        alert("Pop-up blokerlangan! Iltimos, printer sahifasini ochish uchun pop-uplarga ruxsat bering.");
        return;
    }
    
    printWindow.document.open();
    printWindow.document.write(`
        <html>
        <head>
            <title>MA'LUMOTNOMA</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap');

                @page {
                    size: A4;
                    margin: 0;
                }

                * {
                    box-sizing: border-box;
                }

                body {
                    font-family: 'Inter', sans-serif;
                    color: #333;
                    margin: 0;
                    padding: 0;
                    background-color: #fff;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                .print-container {
                    width: 210mm;
                    height: 297mm;
                    position: relative;
                    background: #fff;
                    overflow: hidden;
                }

                /* Diagonal crop containers */
                .clip-top-left-bg {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 130mm;
                    height: 130mm;
                    background: #333;
                    clip-path: polygon(0 0, 100% 0, 68% 100%, 0 100%);
                    z-index: 1;
                }

                .clip-top-left-img {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 128mm;
                    height: 128mm;
                    background: #fff;
                    clip-path: polygon(0 0, 100% 0, 68% 100%, 0 100%);
                    z-index: 2;
                    overflow: hidden;
                }

                .clip-top-left-img img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }

                .clip-bottom-right-bg {
                    position: absolute;
                    bottom: 0;
                    right: 0;
                    width: 130mm;
                    height: 130mm;
                    background: #333;
                    clip-path: polygon(32% 0, 100% 0, 100% 100%, 0 100%);
                    z-index: 1;
                }

                .clip-bottom-right-img {
                    position: absolute;
                    bottom: 0;
                    right: 0;
                    width: 128mm;
                    height: 128mm;
                    background: #fff;
                    clip-path: polygon(32% 0, 100% 0, 100% 100%, 0 100%);
                    z-index: 2;
                    overflow: hidden;
                }

                .clip-bottom-right-img img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }

                /* Logo placement */
                .logo-container {
                    position: absolute;
                    top: 15mm;
                    left: 12mm;
                    z-index: 10;
                    background: rgba(255, 255, 255, 0.9);
                    padding: 6px 12px;
                    border-radius: 6px;
                    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
                }

                .logo-fallback {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                }

                .logo-fallback-icon {
                    width: 20px;
                    height: 20px;
                    color: #333;
                }

                .logo-fallback-text {
                    font-family: 'Montserrat', sans-serif;
                    font-size: 11px;
                    font-weight: 800;
                    color: #333;
                    letter-spacing: 0.5px;
                }

                /* Text Quadrants */
                .top-right-content {
                    position: absolute;
                    top: 15mm;
                    right: 12mm;
                    width: 85mm;
                    z-index: 10;
                }

                .bottom-left-content {
                    position: absolute;
                    bottom: 15mm;
                    left: 12mm;
                    width: 85mm;
                    z-index: 10;
                }

                /* Text Styles */
                .title-xona {
                    font-family: 'Montserrat', sans-serif;
                    font-size: 56px;
                    font-weight: 800;
                    color: #333;
                    text-align: right;
                    line-height: 0.9;
                    margin-bottom: 25px;
                    text-transform: uppercase;
                }

                .title-xona span {
                    font-size: 78px;
                    color: #000;
                    font-weight: 900;
                }

                .section-title {
                    font-family: 'Montserrat', sans-serif;
                    font-size: 15px;
                    font-weight: 800;
                    color: #000;
                    border-bottom: 1.5px solid #333;
                    padding-bottom: 4px;
                    margin-bottom: 12px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                .bullet-list {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                }

                .bullet-item {
                    position: relative;
                    padding-left: 14px;
                    margin-bottom: 2px;
                    font-size: 11.5px;
                    color: #333;
                    line-height: 1.4;
                }

                .bullet-item::before {
                    content: '•';
                    position: absolute;
                    left: 0;
                    color: #333;
                    font-weight: bold;
                }

                .bullet-item-val {
                    position: relative;
                    padding-left: 14px;
                    margin-bottom: 8px;
                    font-size: 12px;
                    font-weight: 700;
                    color: #000;
                    line-height: 1.4;
                }

                .bullet-item-val::before {
                    content: '•';
                    position: absolute;
                    left: 0;
                    color: #333;
                    font-weight: bold;
                }

                /* Starburst Badge */
                .discount-badge-container {
                    position: absolute;
                    top: 148.5mm;
                    left: 105mm;
                    transform: translate(-50%, -50%);
                    width: 120px;
                    height: 120px;
                    z-index: 20;
                }

                .badge-point {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: #333;
                    border-radius: 4px;
                }

                .badge-point.p1 { transform: rotate(0deg); }
                .badge-point.p2 { transform: rotate(22.5deg); }
                .badge-point.p3 { transform: rotate(45deg); }
                .badge-point.p4 { transform: rotate(67.5deg); }

                .badge-inner {
                    position: absolute;
                    top: 6%;
                    left: 6%;
                    width: 88%;
                    height: 88%;
                    background: #333;
                    border-radius: 50%;
                    border: 1.5px dashed #ffffff;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 5;
                }

                .badge-text {
                    font-family: 'Montserrat', sans-serif;
                    color: #ffffff;
                    font-size: 12px;
                    font-weight: 900;
                    letter-spacing: 0.5px;
                    transform: rotate(-10deg);
                    text-transform: uppercase;
                }

                /* Contact Styles */
                .contact-title {
                    font-family: 'Montserrat', sans-serif;
                    font-size: 14px;
                    font-weight: 800;
                    color: #000;
                    margin-top: 20px;
                    margin-bottom: 12px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                .phone-list {
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                }

                .phone-row {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .phone-icon-circle {
                    width: 26px;
                    height: 26px;
                    background: #333;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #fff;
                }

                .phone-icon-svg {
                    width: 12px;
                    height: 12px;
                    fill: currentColor;
                }

                .phone-number {
                    font-family: 'Inter', sans-serif;
                    font-size: 13px;
                    font-weight: 700;
                    color: #000;
                }

                @media print {
                    body {
                        background: #ffffff;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .print-container {
                        width: 210mm;
                        height: 297mm;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .clip-top-left-bg, .clip-bottom-right-bg, .phone-icon-circle, .badge-point, .badge-inner {
                        background: #333 !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-container">
                <!-- Top Left Image (Diagonal) -->
                <div class="clip-top-left-bg"></div>
                <div class="clip-top-left-img">
                    <img src="${buildingPhoto}" alt="Building Cover" />
                </div>

                <!-- Logo -->
                <div class="logo-container">
                    ${logoSrc ? `
                        <img src="${logoSrc}" style="max-height: 40px; max-width: 120px; object-fit: contain;" alt="Logo" />
                    ` : `
                        <div class="logo-fallback">
                            <svg class="logo-fallback-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                            </svg>
                            <span class="logo-fallback-text">TIJORAT TAKLIFI</span>
                        </div>
                    `}
                </div>

                <!-- Top Right Content -->
                <div class="top-right-content">
                    <div class="title-xona"><span>${unit.rooms_count || 1}</span> XONA</div>
                    
                    <div class="section-title">${unit.type === 'commercial' ? "Do'kon" : "Xonadon"} ma'lumotlari</div>
                    <ul class="bullet-list" style="margin-bottom: 25px;">
                        <li class="bullet-item">Blok / Korpus: <strong>${cleanedBlockName}</strong></li>
                        <li class="bullet-item">Qavat: <strong>${selectedFloor.value?.floor_number < 0 ? selectedFloor.value?.floor_number + ' yerto\'la' : selectedFloor.value?.floor_number + '-qavat'}</strong></li>
                        <li class="bullet-item">${unit.type === 'commercial' ? "Do'kon" : "Xonadon"} raqami: <strong>№ ${(unit.type === 'commercial' && /^\d+$/.test(String(unit.unit_number).trim()) ? 'D-' : '') + unit.unit_number}</strong></li>
                        <li class="bullet-item">Umumiy maydoni: <strong>${unit.area_sqm} kv.m</strong></li>
                        <li class="bullet-item">Xonalar soni: <strong>${unit.rooms_count || 1} xonali</strong></li>
                    </ul>

                    <div class="section-title">To'lov shartlari</div>
                    <ul class="bullet-list">
                        <li class="bullet-item">Boshlang'ich ${unit.type === 'commercial' ? "do'kon" : "uy"} narxi</li>
                        <li class="bullet-item-val">${formatSoM(calcPrice.value)}</li>
                        <li class="bullet-item">Kvadrat metr narxi</li>
                        <li class="bullet-item-val">${formatSoM(calcPricePerSqm.value)} / kv.m</li>
                        <li class="bullet-item">Chegirma (kv.m uchun)</li>
                        <li class="bullet-item-val">${formatSoM(calcDiscountPerSqm.value)}</li>
                        <li class="bullet-item">Jami chegirma summasi</li>
                        <li class="bullet-item-val">${formatSoM(calcDiscount.value)}</li>
                    </ul>
                </div>

                <!-- Starburst Badge -->
                ${showDiscountBadge ? `
                <div class="discount-badge-container">
                    <div class="badge-point p1"></div>
                    <div class="badge-point p2"></div>
                    <div class="badge-point p3"></div>
                    <div class="badge-point p4"></div>
                    <div class="badge-inner">
                        <div class="badge-text">CHEGIRMA</div>
                    </div>
                </div>
                ` : ''}

                <!-- Bottom Left Content -->
                <div class="bottom-left-content">
                    <div class="section-title">Yakuniy shartnoma qiymati</div>
                    <ul class="bullet-list" style="margin-bottom: 25px;">
                        <li class="bullet-item-val" style="font-size: 14px; margin-bottom: 6px; color: #000;">${formatSoM(calculatedTotalAmount.value)}</li>
                        <li class="bullet-item">Boshlang'ich to'lov summasi</li>
                        <li class="bullet-item-val">${formatSoM(calcDownPayment.value)} (${calcDownPaymentPercent.value}%)</li>
                        <li class="bullet-item">Muddatli to'lovga qoldiq summa</li>
                        <li class="bullet-item-val">${formatSoM(calculatedRemainingBalance.value)}</li>
                        <li class="bullet-item">Bo'lib to'lash muddati</li>
                        <li class="bullet-item-val">${calcTermMonths.value || 12} oy</li>
                    </ul>

                    <div class="contact-title">BOG'LANISH UCHUN</div>
                    <div class="phone-list">
                        <div class="phone-row">
                            <span class="phone-icon-circle">
                                <svg class="phone-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                            </span>
                            <span class="phone-number">+998 90 450 54 55</span>
                        </div>
                        <div class="phone-row">
                            <span class="phone-icon-circle">
                                <svg class="phone-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                            </span>
                            <span class="phone-number">+998 93 573 29 29</span>
                        </div>
                        <div class="phone-row">
                            <span class="phone-icon-circle">
                                <svg class="phone-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                            </span>
                            <span class="phone-number">+998 91 708 44 44</span>
                        </div>
                    </div>
                </div>

                <!-- Bottom Right Image (Diagonal) -->
                <div class="clip-bottom-right-bg"></div>
                <div class="clip-bottom-right-img">
                    <img src="${selectedUnitDisplayImage.value}" alt="Unit 3D Layout" />
                </div>
            </div>

            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                    }, 500);
                }
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

const selectedUnitBlueprint = computed(() => {
    if (!selectedUnit.value || !selectedBuilding.value || !selectedBuilding.value.floors) return '/modern_apartment_render.png';
    const fl = selectedBuilding.value.floors.find(f => f.id === selectedUnit.value.floor_id);
    return fl?.blueprint_path ? `/storage/${fl.blueprint_path}` : '/modern_apartment_render.png';
});

const selectedUnitDisplayImage = computed(() => {
    if (selectedUnit.value?.model_3d_path) {
        return `/storage/${selectedUnit.value.model_3d_path}`;
    }
    return selectedUnitBlueprint.value;
});

const fullscreenBlueprintSrc = computed(() => {
    if (selectedUnitForFullscreenBlueprint.value) {
        if (selectedUnitForFullscreenBlueprint.value.blueprint_path) {
            return `/storage/${selectedUnitForFullscreenBlueprint.value.blueprint_path}`;
        }
        const fl = selectedBuilding.value?.floors?.find(f => f.id === selectedUnitForFullscreenBlueprint.value.floor_id);
        return fl?.blueprint_path ? `/storage/${fl.blueprint_path}` : '/modern_apartment_render.png';
    }
    return currentBlueprintPath.value ? `/storage/${currentBlueprintPath.value}` : '/modern_apartment_render.png';
});

const fullscreen3DModelSrc = computed(() => {
    if (selectedUnitForFullscreenBlueprint.value && selectedUnitForFullscreenBlueprint.value.model_3d_path) {
        return `/storage/${selectedUnitForFullscreenBlueprint.value.model_3d_path}`;
    }
    return null;
});

function openBlueprintFullscreenForUnit() {
    if (selectedUnit.value) {
        selectedUnitForFullscreenBlueprint.value = selectedUnit.value;
        activeViewMode.value = selectedUnit.value.model_3d_path ? 'split' : '2d';
        zoomScale.value = 1;
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
    unitForm.podez_id = unit.podez_id || null;
    unitForm.has_balcony = unit.has_balcony ? true : false;
    unitForm.blueprint = null;
    unitForm.model_3d = null;
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

function getUnitsByFloorAndPodyezd(floor, p) {
    if (!floor || !floor.units) return [];
    
    let pNum = typeof p === 'object' ? p.podez_number : p;
    let pId = typeof p === 'object' ? p.id : null;
    
    let units = floor.units;
    
    if (pId && !String(pId).startsWith('legacy-')) {
        units = units.filter(u => u.podez_id === pId || (u.podyezd || 1) === pNum);
    } else {
        units = units.filter(u => (u.podyezd || 1) === pNum);
    }
    
    return [...units].sort((a, b) => {
        const numA = String(a.unit_number).replace(/\D/g, '');
        const numB = String(b.unit_number).replace(/\D/g, '');
        return (Number(numA) || 0) - (Number(numB) || 0);
    });
}

function getSortedFloorsForPodez(podez) {
    if (!podez) return [];
    let floors = [];
    if (selectedBuilding.value && selectedBuilding.value.floors) {
        if (podez.id && !String(podez.id).startsWith('legacy-')) {
            floors = selectedBuilding.value.floors.filter(f => f.podez_id === podez.id);
        } else {
            floors = selectedBuilding.value.floors;
        }
    }
    return [...floors].sort((a, b) => a.floor_number - b.floor_number);
}

function getFloorStatsText(floor) {
    if (!floor) return '';
    if (floor.units && floor.units.length > 0) {
        const commercialCount = floor.units.filter(u => u.type === 'commercial').length;
        const residentialCount = floor.units.filter(u => u.type === 'residential').length;
        
        if (commercialCount > 0 && residentialCount > 0) {
            return `${commercialCount} ta do'kon, ${residentialCount} ta uy`;
        } else if (commercialCount > 0) {
            return `${commercialCount} ta do'kon`;
        } else if (residentialCount > 0) {
            return `${residentialCount} ta uy`;
        }
    }
    
    // Fallback to static config
    if (floor.floor_number <= 1) {
        return `${floor.total_commercial_units || 0} ta do'kon`;
    } else {
        return `${floor.total_units || 0} ta uy`;
    }
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
    if (isEditingBuilding.value) {
        if (isEditingObject.value) return 'OBYEKTNI TAHRIRLASH';
        return 'BINONI TAHRIRLASH';
    }
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
    <div v-if="renderError" class="p-8 bg-red-100 text-red-900 min-h-screen">
        <h1 class="text-3xl font-bold mb-4">Vue Render Error</h1>
        <p class="font-mono text-sm mb-2"><strong>Error:</strong> {{ renderError.err }}</p>
        <p class="font-mono text-sm mb-2"><strong>Info:</strong> {{ renderError.info }}</p>
        <pre class="bg-black text-green-400 p-4 rounded overflow-auto text-xs">{{ renderError.stack }}</pre>
    </div>
    <div v-else>
    <Head :title="$t('facility_command_center') || 'Facility Command Center'" />
    <AdminLayout>
        <div v-if="!showUnifiedSellModal">
            <!-- Dynamic Breadcrumbs -->
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6 font-sans">
            <button @click="currentObject = null; selectedBuilding = null; currentView = 'objects'" class="hover:text-blue-600 transition-colors flex items-center gap-1.5 font-medium">
                🏠 Asosiy
            </button>
            <span class="text-gray-300">/</span>
            <button @click="currentObject = null; selectedBuilding = null; currentView = 'objects'" class="hover:text-blue-600 transition-colors font-medium">
                Obyektlar
            </button>
            <template v-if="currentObject === null && currentView === 'blocks'">
                <span class="text-gray-300">/</span>
                <span class="text-gray-800 font-semibold">Barcha Bloklar</span>
            </template>
            <template v-if="currentView === 'report'">
                <span class="text-gray-300">/</span>
                <span class="text-gray-800 font-semibold">Obyektlar Hisoboti</span>
            </template>
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
            
            <!-- VIEW 1: Objects Page (If currentObject is null and currentView is objects) -->
            <div v-if="currentObject === null && currentView === 'objects'" class="space-y-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">Mavjud Obyektlar</h2>
                    <Link :href="route('facility.buildings.create')" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-xl shadow-sm transition-all inline-block">
                        + Yangi Obyekt
                    </Link>
                </div>

                <!-- Qidiruv va Filtrlar paneli -->
                <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex flex-col xl:flex-row xl:items-center gap-4">
                    <!-- Qidiruv -->
                    <div class="flex-1 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="searchQuery" 
                            type="text" 
                            placeholder="Nomi, shahar, tuman, blok, xonadon raqami, xonalar soni yoki holati bo'yicha..." 
                            class="pl-10 pr-4 py-2.5 w-full rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <!-- Shahar/Viloyat Filtr -->
                        <div class="w-full sm:w-44 relative">
                            <select 
                                v-model="selectedCity" 
                                @change="selectedDistrict = ''"
                                class="w-full py-2.5 pl-3 pr-8 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer bg-white"
                            >
                                <option value="">Barchasi (Viloyat)</option>
                                <option v-for="city in cities" :key="city" :value="city">{{ city }}</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </div>

                        <!-- Tuman Filtr -->
                        <div class="w-full sm:w-44 relative">
                            <select 
                                v-model="selectedDistrict" 
                                class="w-full py-2.5 pl-3 pr-8 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer bg-white"
                            >
                                <option value="">Barchasi (Tuman)</option>
                                <option v-for="district in districts" :key="district" :value="district">{{ district }}</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </div>

                        <!-- Xonalar soni Filtr -->
                        <div class="w-full sm:w-40 relative">
                            <select 
                                v-model="selectedRoomsFilter" 
                                class="w-full py-2.5 pl-3 pr-8 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer bg-white"
                            >
                                <option value="">Barcha Xonalar</option>
                                <option value="1">1 xonali</option>
                                <option value="2">2 xonali</option>
                                <option value="3">3 xonali</option>
                                <option value="4">4 xonali</option>
                                <option value="5">5+ xonali</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </div>

                        <!-- Xonadon Holati Filtr -->
                        <div class="w-full sm:w-40 relative">
                            <select 
                                v-model="selectedStatusFilter" 
                                class="w-full py-2.5 pl-3 pr-8 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer bg-white"
                            >
                                <option value="">Barcha Holatlar</option>
                                <option value="available">Sotuvda (Bo'sh)</option>
                                <option value="reserved">Bron qilingan</option>
                                <option value="sold">Sotilgan</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </div>

                        <!-- Tozalash tugmasi -->
                        <button 
                            v-if="searchQuery || selectedCity || selectedDistrict || selectedRoomsFilter || selectedStatusFilter"
                            @click="searchQuery = ''; selectedCity = ''; selectedDistrict = ''; selectedRoomsFilter = ''; selectedStatusFilter = '';"
                            class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition-all flex items-center gap-1.5 justify-center"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Tozalash
                        </button>
                    </div>
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
                            <div class="flex items-center gap-2">
                                <button @click.stop="openEditObjectContainer(blocks)" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg shadow-sm transition-all">
                                    Tahrirlash
                                </button>
                                <button @click.stop="deleteObject(blocks, objName)" class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-xs font-bold rounded-lg shadow-sm transition-all">
                                    O'chirish
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="props.buildings.length === 0" class="col-span-full bg-white border border-dashed border-gray-300 rounded-2xl p-12 text-center text-gray-500">
                        Hech qanday obyekt topilmadi. Yuqoridagi "+ Yangi Obyekt" orqali obyekt kiritishingiz mumkin.
                    </div>
                    <div v-else-if="Object.keys(groupedObjects).length === 0" class="col-span-full bg-white border border-dashed border-gray-300 rounded-2xl p-12 text-center text-gray-500">
                        Qidiruv va filtrlar bo'yicha hech qanday obyekt topilmadi.
                    </div>
                </div>
            </div>

            <!-- VIEW 1.5: Flat Blocks Page (If currentObject is null and currentView is blocks) -->
            <div v-else-if="currentObject === null && currentView === 'blocks'" class="space-y-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">Barcha Obyektlar Bloklari / Korpuslari</h2>
                </div>

                <!-- Qidiruv va Filtrlar paneli -->
                <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex flex-col xl:flex-row xl:items-center gap-4">
                    <!-- Qidiruv -->
                    <div class="flex-1 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="searchQuery" 
                            type="text" 
                            placeholder="Nomi, shahar, tuman, blok, xonadon raqami, xonalar soni yoki holati bo'yicha..." 
                            class="pl-10 pr-4 py-2.5 w-full rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <!-- Shahar/Viloyat Filtr -->
                        <div class="w-full sm:w-44 relative">
                            <select 
                                v-model="selectedCity" 
                                @change="selectedDistrict = ''"
                                class="w-full py-2.5 pl-3 pr-8 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer bg-white"
                            >
                                <option value="">Barchasi (Viloyat)</option>
                                <option v-for="city in cities" :key="city" :value="city">{{ city }}</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </div>

                        <!-- Tuman Filtr -->
                        <div class="w-full sm:w-44 relative">
                            <select 
                                v-model="selectedDistrict" 
                                class="w-full py-2.5 pl-3 pr-8 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer bg-white"
                            >
                                <option value="">Barchasi (Tuman)</option>
                                <option v-for="district in districts" :key="district" :value="district">{{ district }}</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </div>

                        <!-- Xonalar soni Filtr -->
                        <div class="w-full sm:w-40 relative">
                            <select 
                                v-model="selectedRoomsFilter" 
                                class="w-full py-2.5 pl-3 pr-8 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer bg-white"
                            >
                                <option value="">Barcha Xonalar</option>
                                <option value="1">1 xonali</option>
                                <option value="2">2 xonali</option>
                                <option value="3">3 xonali</option>
                                <option value="4">4 xonali</option>
                                <option value="5">5+ xonali</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </div>

                        <!-- Xonadon Holati Filtr -->
                        <div class="w-full sm:w-40 relative">
                            <select 
                                v-model="selectedStatusFilter" 
                                class="w-full py-2.5 pl-3 pr-8 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer bg-white"
                            >
                                <option value="">Barcha Holatlar</option>
                                <option value="available">Sotuvda (Bo'sh)</option>
                                <option value="reserved">Bron qilingan</option>
                                <option value="sold">Sotilgan</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </div>

                        <!-- Tozalash tugmasi -->
                        <button 
                            v-if="searchQuery || selectedCity || selectedDistrict || selectedRoomsFilter || selectedStatusFilter"
                            @click="searchQuery = ''; selectedCity = ''; selectedDistrict = ''; selectedRoomsFilter = ''; selectedStatusFilter = '';"
                            class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition-all flex items-center gap-1.5 justify-center"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Tozalash
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div v-for="building in (filteredBuildings || []).filter(b => b.total_floors > 0)" :key="building.id" 
                         @click="currentObject = building.name; selectedBuilding = building"
                         class="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden cursor-pointer hover:shadow-md transition-all group relative">
                        <div class="h-40 overflow-hidden bg-gray-100 relative">
                            <img :src="building.photo_path ? `/storage/${building.photo_path}` : getObjectPhoto(groupedObjects[building.name] || [])" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                            <div class="absolute bottom-3 left-3 text-white">
                                <span class="text-[10px] font-bold uppercase tracking-wider bg-blue-600/90 px-2 py-0.5 rounded-full block w-max mb-1 shadow-sm">{{ building.name }}</span>
                                <h4 class="font-bold uppercase tracking-wider text-sm">{{ building.block_name || building.corpus_name || 'Asosiy Blok' }}</h4>
                            </div>
                        </div>
                        <div class="p-3 flex justify-between items-center bg-gray-50 border-t border-gray-100">
                            <span class="text-xs text-gray-500 font-medium">Qavatlar: {{ building.total_floors }}</span>
                            <Link :href="route('facility.buildings.edit', building.id)" class="px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded shadow-sm transition-all inline-block">
                                Tahrirlash
                            </Link>
                        </div>
                    </div>
                </div>

                <div v-if="props.buildings.filter(b => b.total_floors > 0).length === 0" class="bg-white border border-dashed border-gray-300 rounded-2xl p-12 text-center text-gray-500">
                    Hozirda hech qanday blok mavjud emas.
                </div>
                <div v-else-if="(filteredBuildings || []).filter(b => b.total_floors > 0).length === 0" class="bg-white border border-dashed border-gray-300 rounded-2xl p-12 text-center text-gray-500">
                    Qidiruv va filtrlar bo'yicha hech qanday blok topilmadi.
                </div>
            </div>

            <!-- VIEW 1.6: Obyektlar Hisoboti Page -->
            <div v-else-if="currentView === 'report'" class="space-y-6">
                <!-- Sarlavha paneli -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <button 
                            @click="currentObject = null; selectedBuilding = null; currentView = 'objects'" 
                            class="p-2 bg-white border border-gray-200 rounded-full hover:bg-gray-50 transition-all shadow-sm"
                            title="Orqaga"
                        >
                            <svg class="h-4 w-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                        <h2 class="text-2xl font-bold text-gray-900">Obyektlar</h2>
                    </div>

                    <!-- Filtrlar va Excel -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Qidirish -->
                        <div class="relative w-full sm:w-64">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input 
                                v-model="reportSearchQuery" 
                                type="text" 
                                placeholder="Qidirish..." 
                                class="pl-9 pr-4 py-2 w-full rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400"
                            />
                        </div>

                        <!-- Oyni Tanlang diapazoni (Redesigned) -->
                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Start Month Input -->
                            <div class="relative flex items-center bg-white border border-gray-200 rounded-2xl px-4 py-1.5 shadow-sm text-gray-500 hover:border-amber-500 focus-within:ring-2 focus-within:ring-amber-500/20 focus-within:border-amber-500 transition-all duration-300">
                                <span class="text-[9px] uppercase font-bold text-gray-400 absolute -top-2 left-4 bg-white px-1">Sana (Dan)</span>
                                <input 
                                    type="month" 
                                    v-model="startMonth" 
                                    class="text-xs bg-transparent border-none outline-none text-gray-700 focus:ring-0 w-28 p-0 cursor-pointer font-semibold" 
                                />
                            </div>

                            <span class="text-gray-300 font-bold text-xs">→</span>

                            <!-- End Month Input -->
                            <div class="relative flex items-center bg-white border border-gray-200 rounded-2xl px-4 py-1.5 shadow-sm text-gray-500 hover:border-amber-500 focus-within:ring-2 focus-within:ring-amber-500/20 focus-within:border-amber-500 transition-all duration-300">
                                <span class="text-[9px] uppercase font-bold text-gray-400 absolute -top-2 left-4 bg-white px-1">Sana (Gacham)</span>
                                <input 
                                    type="month" 
                                    v-model="endMonth" 
                                    class="text-xs bg-transparent border-none outline-none text-gray-700 focus:ring-0 w-28 p-0 cursor-pointer font-semibold" 
                                />
                            </div>

                            <!-- Clear Button -->
                            <button 
                                v-if="startMonth || endMonth" 
                                @click="startMonth = ''; endMonth = '';" 
                                class="p-2 text-gray-400 hover:text-red-500 rounded-2xl bg-white border border-gray-200 hover:border-red-200 transition-colors shadow-sm"
                                title="Filtrni tozalash"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>

                        <!-- Excel Tugmasi -->
                        <button 
                            @click="showExcelPreviewModal = true" 
                            class="px-4 py-2 border border-emerald-600 bg-white hover:bg-emerald-50 text-emerald-700 font-bold text-xs rounded-xl shadow-sm transition-all flex items-center gap-1.5"
                        >
                            <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Excel
                        </button>
                    </div>
                </div>

                <!-- Jurnal/Hisobot jadvali -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50/70 border-b border-gray-100 font-sans">
                                <tr>
                                    <th scope="col" class="px-6 py-4 w-12 text-center">№</th>
                                    <th scope="col" class="px-6 py-4">Nomi</th>
                                    <th scope="col" class="px-6 py-4 text-center">Xonadonlar soni</th>
                                    <th scope="col" class="px-6 py-4 text-center">Sotilgan xonadonlar</th>
                                    <th scope="col" class="px-6 py-4 text-center">Sotuvdagi xonadonlar</th>
                                    <th scope="col" class="px-6 py-4 text-right">Sotilgan uylar narxi</th>
                                    <th scope="col" class="px-6 py-4 text-right">Kutilayotgan to'lovlar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-sans">
                                <tr 
                                    v-for="(row, idx) in filteredReportData" 
                                    :key="'rep-'+idx"
                                    class="bg-white hover:bg-gray-50/50 transition-colors"
                                >
                                    <td class="px-6 py-4 text-center font-medium text-gray-900 w-12">{{ idx + 1 }}</td>
                                    <td class="px-6 py-4 font-semibold text-gray-900">{{ row.name }}</td>
                                    <td class="px-6 py-4 text-center text-gray-700 font-mono">{{ row.max_apartments }}</td>
                                    <td class="px-6 py-4 text-center text-gray-700 font-mono">{{ row.sold_apartments }}</td>
                                    <td class="px-6 py-4 text-center text-gray-700 font-mono">{{ row.available_apartments }}</td>
                                    <td class="px-6 py-4 text-right text-emerald-600 font-bold font-mono">
                                        {{ formatSoM(row.sold_apartments_price) }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-red-500 font-bold font-mono">
                                        {{ formatSoM(row.debt_price) }}
                                    </td>
                                </tr>
                                <tr v-if="filteredReportData.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 italic bg-gray-50/30">
                                        Hisobot bo'yicha hech qanday ma'lumot topilmadi.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
                                <button @click="openShowroomModal" class="px-3 py-1.5 bg-white/15 hover:bg-white/25 border border-white/10 text-white text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5">
                                    👁️ Showroom
                                </button>
                                <button @click="openUploadModelsModal()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-all shadow-sm flex items-center gap-1">
                                    📁 Modellar yuklash
                                </button>
                                <Link :href="route('facility.buildings.edit', getObjectContainerId(currentObject))" class="px-3 py-1.5 bg-indigo-600 hover:indigo-700 text-white text-xs font-bold rounded-lg transition-all shadow-sm flex items-center gap-1 inline-block">
                                    ✏️ Obyektni tahrirlash
                                </Link>
                                <Link :href="route('facility.buildings.create', { object_id: getObjectContainerId(currentObject) })" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg transition-all shadow-sm inline-block">
                                    + Yangi Blok
                                </Link>
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

                <!-- Qidiruv va Filtrlar paneli (Obyekt ichidagi bloklar uchun) -->
                <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex flex-col md:flex-row md:items-center gap-4">
                    <!-- Qidiruv -->
                    <div class="flex-1 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="searchQuery" 
                            type="text" 
                            placeholder="Blok nomi, xonadon raqami, xonalar soni yoki holati bo'yicha..." 
                            class="pl-10 pr-4 py-2.5 w-full rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400"
                        />
                    </div>

                    <!-- Xonalar soni Filtr -->
                    <div class="w-full md:w-48 relative">
                        <select 
                            v-model="selectedRoomsFilter" 
                            class="w-full py-2.5 pl-3 pr-8 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer bg-white"
                        >
                            <option value="">Barcha Xonalar</option>
                            <option value="1">1 xonali</option>
                            <option value="2">2 xonali</option>
                            <option value="3">3 xonali</option>
                            <option value="4">4 xonali</option>
                            <option value="5">5+ xonali</option>
                        </select>
                        <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </div>

                    <!-- Xonadon Holati Filtr -->
                    <div class="w-full md:w-48 relative">
                        <select 
                            v-model="selectedStatusFilter" 
                            class="w-full py-2.5 pl-3 pr-8 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer bg-white"
                        >
                            <option value="">Barcha Holatlar</option>
                            <option value="available">Sotuvda (Bo'sh)</option>
                            <option value="reserved">Bron qilingan</option>
                            <option value="sold">Sotilgan</option>
                        </select>
                        <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </div>

                    <!-- Tozalash tugmasi -->
                    <button 
                        v-if="searchQuery || selectedRoomsFilter || selectedStatusFilter"
                        @click="searchQuery = ''; selectedRoomsFilter = ''; selectedStatusFilter = '';"
                        class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition-all flex items-center gap-1.5 justify-center"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Tozalash
                    </button>
                </div>

                <!-- Grid of Blocks under this Object -->
                <div class="space-y-4 mt-6">
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
                                <Link :href="route('facility.buildings.edit', building.id)" class="px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded shadow-sm transition-all inline-block">
                                    Tahrirlash
                                </Link>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="props.buildings.filter(b => b.name === currentObject && b.total_floors > 0).length === 0" class="bg-white border border-dashed border-gray-300 rounded-2xl p-12 text-center text-gray-500">
                        Ushbu obyektda hali hech qanday blok qo'shilmagan. Yuqoridagi "+ Yangi Blok" orqali blok kiritishingiz mumkin.
                    </div>
                    <div v-else-if="(groupedObjects[currentObject] || []).filter(b => b.total_floors > 0).length === 0" class="bg-white border border-dashed border-gray-300 rounded-2xl p-12 text-center text-gray-500">
                        Qidiruv va filtrlar bo'yicha hech qanday blok topilmadi.
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
                            <div @click="openBuildingPhotoFullscreen" class="h-[280px] md:h-[320px] overflow-hidden bg-gray-100 rounded-xl relative shadow-inner cursor-pointer group">
                                <img :src="selectedBuilding.photo_path ? `/storage/${selectedBuilding.photo_path}` : getObjectPhoto(groupedObjects[currentObject] || [])" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                                <div class="absolute bottom-4 left-4 text-white">
                                    <h3 class="text-xl font-bold uppercase tracking-wider">{{ selectedBuilding.block_name || selectedBuilding.corpus_name || '1 Bino' }}</h3>
                                </div>
                                <!-- Magnifier overlay -->
                                <div class="absolute inset-0 bg-black/10 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-all duration-300">
                                    <span class="bg-black/60 text-white px-3 py-1.5 rounded-xl text-xs font-semibold backdrop-blur-sm flex items-center gap-1.5 shadow-md">
                                        🔍 Kattalashtirish
                                    </span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button @click="openEditBuilding(false)" class="flex-1 py-2 border border-gray-200 text-gray-700 bg-white text-xs font-sans rounded-xl hover:bg-gray-50 transition-all font-bold text-center">
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
                                    <h4 class="text-sm font-bold font-sans text-gray-800 flex items-center gap-2">
                                        <span>Xonadonlar Shaxmatkasi</span>
                                        <span v-if="selectedPodyezdFilter" class="px-2 py-0.5 bg-blue-50 border border-blue-200 text-blue-600 rounded text-xs flex items-center gap-1 font-mono">
                                            {{ selectedPodyezdFilter }}-podyezd
                                            <button @click="selectedPodyezdFilter = null" class="hover:text-red-500 font-bold ml-1 text-sm select-none" title="Filtrni tozalash">×</button>
                                        </span>
                                    </h4>
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
                                     <div v-if="selectedBuilding.floors?.length || selectedBuilding.podezes?.length" class="flex flex-row gap-8 min-w-max p-1">
                                         <div v-for="p in podyezdsToShow" :key="'podyezd-'+p.id" class="flex flex-col min-w-[320px] max-w-[480px] overflow-x-auto pb-4 scrollbar-thin">
                                            <h4 class="text-sm font-medium text-gray-800 font-sans mb-6 text-center">
                                                {{ p.podez_name }}
                                            </h4>
                                            <div class="space-y-4">
                                                <div v-for="floor in getSortedFloorsForPodez(p)" :key="'floor-'+p.id+'-'+floor.id" class="flex items-center gap-4 group/floor-row">
                                                    <div class="w-24 flex flex-col shrink-0 justify-center">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-[10px] font-sans font-bold text-amber-600 uppercase tracking-wider">{{ floor.floor_number < 0 ? `${floor.floor_number} YERTO'LA` : `${floor.floor_number}-QAVAT` }}</span>
                                                            <div class="flex gap-1.5 opacity-0 group-hover/floor-row:opacity-100 transition-opacity">
                                                                <button @click="openEditFloor(floor)" class="text-[10px] hover:scale-110 transition-transform" title="Tahrirlash">✏️</button>
                                                                <button @click="selectedFloor = floor; unitForm.podez_id = p.id; unitForm.podyezd = p.podez_number; showAddUnitModal = true; isEditingUnit = false" class="text-[10px] hover:scale-110 font-bold text-blue-600 transition-transform" title="Xonadon qo'shish">+</button>
                                                            </div>
                                                        </div>
                                                        <span class="text-[9px] font-sans text-amber-600 font-semibold mt-0.5">
                                                            {{ getFloorStatsText(floor) }}
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
                                                                {{ (unit.type === 'commercial' && /^\d+$/.test(String(unit.unit_number).trim()) ? 'D-' : '') + unit.unit_number }}
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
                                            {{ (hoveredUnit.type === 'commercial' && /^\d+$/.test(String(hoveredUnit.unit_number).trim()) ? 'D-' : '') + hoveredUnit.unit_number }} 
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
                                    <div class="h-60 flex items-center justify-center bg-gray-50/50 border border-gray-100 rounded-2xl p-3 mb-6 relative group overflow-hidden">
                                        <img :src="selectedUnitDisplayImage" 
                                             class="h-full max-w-full object-contain cursor-pointer transition-all duration-500 group-hover:scale-102"
                                             @click="openBlueprintFullscreenForUnit" />
                                        
                                        <!-- Magnifying Glass Icon Overlay -->
                                        <div class="absolute top-3 right-3 bg-white/80 dark:bg-zinc-800/80 backdrop-blur-sm p-1.5 rounded-lg border border-gray-100 dark:border-zinc-700 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none shadow-sm">
                                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                    </div>

                                    <h4 class="text-[#a8a29e] text-xl font-medium mb-6 font-sans">
                                        {{ selectedUnit.type === 'commercial' ? "Do'kon haqida ma'lumot" : "Uy haqida ma'lumot" }}
                                    </h4>
                                    <div class="space-y-4 text-base font-sans text-gray-700">
                                        <div>
                                            <span class="text-gray-500">{{ selectedUnit.type === 'commercial' ? "Do'kon raqami:" : "Xonadon:" }}</span>
                                            <strong class="text-gray-900 font-bold ml-1">{{ (selectedUnit.type === 'commercial' && /^\d+$/.test(String(selectedUnit.unit_number).trim()) ? 'D-' : '') + selectedUnit.unit_number }}</strong>
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
                                            <span class="text-gray-500">Balkon:</span>
                                            <strong class="text-gray-900 font-bold ml-1">
                                                {{ selectedUnit.has_balcony ? 'Balkon bor' : 'Balkon yo\'q' }}
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
                                        Sotish
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

                                    <div class="space-y-4 font-sans text-sm">
                                        <!-- Xonadon Maydoni -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-1">{{ selectedUnit?.type === 'commercial' ? "Do'kon" : "Xonadon" }} maydoni (kv.m)</label>
                                            <div class="bg-slate-100 border border-slate-200 text-slate-700 rounded-xl px-4 py-3 font-semibold font-sans">
                                                {{ selectedUnit?.area_sqm || 0 }} kv.m
                                            </div>
                                        </div>

                                        <!-- Kvadrat Metr Narxi & Uy Narxi -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">1 kv.m narxi</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                    <input type="number" v-model.number="calcPricePerSqm" @input="onPricePerSqmInput" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans font-semibold" />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">{{ selectedUnit?.type === 'commercial' ? "Do'kon" : "Uy" }} narxi (Jami)</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                    <input type="number" v-model.number="calcPrice" @input="onPriceInput" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans font-semibold" />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Boshlang'ich to'lov -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-1">
                                                Boshlang'ich to'lov <span v-if="calcDownPaymentPercent > 0" class="text-amber-500 font-bold ml-1">({{ calcDownPaymentPercent }}%)</span>
                                            </label>
                                            <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                <input type="number" v-model.number="calcDownPayment" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans font-semibold" />
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
                                                <input type="number" v-model.number="calcTermMonths" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans font-semibold" />
                                            </div>
                                        </div>

                                        <!-- Chegirmalar -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Chegirma (1 kv.m uchun)</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                    <input type="number" v-model.number="calcDiscountPerSqm" @input="onDiscountPerSqmInput" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans font-semibold" />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Jami chegirma</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-3">
                                                    <input type="number" v-model.number="calcDiscount" @input="onDiscountInput" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans font-semibold" />
                                                </div>
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
                                            <span class="text-gray-900 font-bold whitespace-nowrap">{{ formatSoM(calcPrice) }}</span>
                                        </div>
                                        
                                        <!-- Chegirma -->
                                        <div class="flex items-center justify-between">
                                            <span class="whitespace-nowrap">Chegirma umumiy</span>
                                            <div class="flex-grow border-b border-dotted border-gray-300 mx-2"></div>
                                            <span class="text-gray-900 font-bold whitespace-nowrap">{{ formatSoM(calcDiscount) }}</span>
                                        </div>

                                        <!-- Jami summa -->
                                        <div class="flex items-center justify-between font-semibold">
                                            <span class="whitespace-nowrap text-gray-800 font-bold">Jami summa (chegirma bilan)</span>
                                            <div class="flex-grow border-b border-dotted border-gray-300 mx-2"></div>
                                            <span class="text-gray-900 font-bold whitespace-nowrap">{{ formatSoM(calculatedTotalAmount) }}</span>
                                        </div>

                                        <!-- Boshlang'ich to'lov -->
                                        <div class="flex items-center justify-between">
                                            <span class="whitespace-nowrap">Boshlang'ich to'lov</span>
                                            <div class="flex-grow border-b border-dotted border-gray-300 mx-2"></div>
                                            <span class="text-gray-900 font-bold whitespace-nowrap">{{ formatSoM(calcDownPayment) }}</span>
                                        </div>

                                        <!-- Qoldiq summa -->
                                        <div class="flex items-center justify-between">
                                            <span class="whitespace-nowrap">Qoldiq summa</span>
                                            <div class="flex-grow border-b border-dotted border-gray-300 mx-2"></div>
                                            <span class="text-gray-900 font-bold whitespace-nowrap">{{ formatSoM(calculatedRemainingBalance) }}</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Print Button -->
                                    <button type="button" @click="printCalculatorDetails" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md transition-all flex items-center justify-center gap-2 mt-4 font-sans text-sm">
                                        🖨️ Chop etish (Taqdimot)
                                    </button>
                                </div>
                            </div>

                            <!-- PANEL 3: Sell (Sotish) -->
                            <form v-else-if="activeRightPanel === 'sell'" @submit.prevent="submitContract" class="space-y-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <!-- Stepper Progress Header -->
                                    <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-4">
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="activeRightPanel = 'details'" class="text-gray-400 hover:text-gray-600 transition-colors text-2xl font-light">
                                                ✕
                                            </button>
                                            <h3 class="text-xl font-bold font-sans text-gray-800">Sotish</h3>
                                        </div>
                                        
                                        <!-- Stepper Dots -->
                                        <div class="flex items-center gap-1">
                                            <div class="flex items-center gap-1">
                                                <div class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold font-sans" 
                                                     :class="sellStep === 1 ? 'bg-[#f2a900] text-white ring-2 ring-amber-100' : 'bg-gray-200 text-gray-500'">
                                                    1
                                                </div>
                                                <span v-if="sellStep === 1" class="text-[10px] font-semibold text-gray-700 font-sans">Mijoz</span>
                                            </div>
                                            <div class="w-2 h-0.5 bg-gray-200"></div>
                                            <div class="flex items-center gap-1">
                                                <div class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold font-sans" 
                                                     :class="sellStep === 2 ? 'bg-[#f2a900] text-white ring-2 ring-amber-100' : 'bg-gray-200 text-gray-500'">
                                                    2
                                                </div>
                                                <span v-if="sellStep === 2" class="text-[10px] font-semibold text-gray-700 font-sans">To'lov turi</span>
                                            </div>
                                            <div class="w-2 h-0.5 bg-gray-200"></div>
                                            <div class="flex items-center gap-1">
                                                <div class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold font-sans" 
                                                     :class="sellStep === 3 ? 'bg-[#f2a900] text-white ring-2 ring-amber-100' : 'bg-gray-200 text-gray-500'">
                                                    3
                                                </div>
                                                <span v-if="sellStep === 3" class="text-[10px] font-semibold text-gray-700 font-sans">Ma'lumotlar</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- STEP 1: Mijoz ma'lumotlari -->
                                    <div v-show="sellStep === 1" class="space-y-4 font-sans">
                                        <!-- Shartnoma sanasi & Shartnoma raqami -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-500 mb-1">Shartnoma sanasi</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-2.5">
                                                    <input type="date" v-model="contractForm.created_at" class="bg-transparent border-0 text-gray-800 text-xs outline-none focus:ring-0 w-full p-0 font-sans" required />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-500 mb-1">Shartnoma raqami</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-2.5">
                                                    <input type="text" v-model="contractForm.contract_number" placeholder="Masalan: SH-001" class="bg-transparent border-0 text-gray-800 text-xs outline-none focus:ring-0 w-full p-0 font-sans" required />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Buyer Type Selection -->
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-500 mb-2">Xaridor</label>
                                            <div class="flex gap-4 mb-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="radio" v-model="contractForm.buyer_type" value="client" class="text-[#f2a900] focus:ring-[#f2a900] bg-white border-gray-300">
                                                    <span class="text-xs text-gray-800 font-bold font-sans">Mijoz</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="radio" v-model="contractForm.buyer_type" value="partner" class="text-[#f2a900] focus:ring-[#f2a900] bg-white border-gray-300">
                                                    <span class="text-xs text-gray-800 font-bold font-sans">Ta'sischi</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="radio" v-model="contractForm.buyer_type" value="employee" class="text-[#f2a900] focus:ring-[#f2a900] bg-white border-gray-300">
                                                    <span class="text-xs text-gray-800 font-bold font-sans">Xodimlar</span>
                                                </label>
                                            </div>
                                            
                                            <!-- Client / Partner / Employee Dropdown -->
                                            <div class="relative">
                                                <div v-if="contractForm.buyer_type === 'client'" class="flex items-center gap-2">
                                                    <SearchableSelect 
                                                        v-model="contractForm.client_id" 
                                                        :options="clientsOptions" 
                                                        placeholder="Mijozni tanlang" 
                                                        :required="contractForm.buyer_type === 'client'"
                                                        class="flex-1"
                                                    />
                                                    <button 
                                                        type="button" 
                                                        @click="openQuickClientAdd" 
                                                        class="px-4 py-2.5 bg-blue-50 border border-blue-200 hover:bg-blue-100 text-blue-600 font-bold text-xs rounded-xl transition-all shadow-sm shrink-0 whitespace-nowrap"
                                                    >
                                                        + Yangi mijoz
                                                    </button>
                                                </div>
                                                <SearchableSelect 
                                                    v-if="contractForm.buyer_type === 'partner'" 
                                                    v-model="contractForm.partner_id" 
                                                    :options="partnersOptions" 
                                                    placeholder="Ta'sischini tanlang" 
                                                    :required="contractForm.buyer_type === 'partner'"
                                                    class="w-full"
                                                />
                                                <SearchableSelect 
                                                    v-if="contractForm.buyer_type === 'employee'" 
                                                    v-model="contractForm.employee_id" 
                                                    :options="usersOptions" 
                                                    placeholder="Xodimni tanlang" 
                                                    :required="contractForm.buyer_type === 'employee'"
                                                    class="w-full"
                                                />
                                            </div>
                                        </div>

                                        <!-- Pricing inputs: price_per_sqm & total_amount -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-500 mb-1">1 kv.m narxi</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-2.5">
                                                    <input type="number" v-model.number="basePricePerSqm" class="bg-transparent border-0 text-gray-800 text-xs outline-none focus:ring-0 w-full p-0 font-sans" required />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-500 mb-1">Uy narxi (Jami)</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-2.5">
                                                    <input type="number" v-model.number="contractForm.total_amount" class="bg-transparent border-0 text-gray-800 text-xs outline-none focus:ring-0 w-full p-0 font-sans font-bold" required />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Down Payment & Progress Bar -->
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-500 mb-1">Boshlang'ich to'lov</label>
                                            <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-2.5">
                                                <input type="number" v-model.number="contractForm.down_payment" class="bg-transparent border-0 text-gray-800 text-xs outline-none focus:ring-0 w-full p-0 font-sans font-bold" required />
                                            </div>
                                            
                                            <!-- Down Payment Progress -->
                                            <div class="flex items-center gap-3 mt-2">
                                                <div class="flex-1 bg-gray-150 rounded-full h-2">
                                                    <div class="bg-[#e67e22] h-2 rounded-full transition-all duration-300" :style="{ width: downPaymentPercent + '%' }"></div>
                                                </div>
                                                <span class="text-xs font-bold text-[#e67e22] font-sans shrink-0">{{ downPaymentPercent }}%</span>
                                            </div>
                                        </div>

                                        <!-- Installment Term (Months) -->
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-500 mb-1">Bo'lib to'lash muddati (oy)</label>
                                            <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-2.5">
                                                <input type="number" v-model.number="contractForm.term_months" class="bg-transparent border-0 text-gray-800 text-xs outline-none focus:ring-0 w-full p-0 font-sans" required />
                                            </div>
                                        </div>

                                        <!-- Discount per Sqm & Total Discount -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-500 mb-1">Chegirma (1 kv.m uchun)</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-4 py-2.5">
                                                    <input type="number" v-model.number="discountPerSqm" class="bg-transparent border-0 text-gray-800 text-xs outline-none focus:ring-0 w-full p-0 font-sans" />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-500 mb-1">Jami chegirma</label>
                                                <div class="relative flex items-center bg-[#f1f5f9] border border-gray-200 rounded-xl px-4 py-2.5 opacity-85">
                                                    <input type="number" :value="totalDiscount" readonly tabindex="-1" class="bg-transparent border-0 text-gray-500 text-xs outline-none focus:ring-0 w-full p-0 font-sans cursor-not-allowed" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- STEP 2: To'lov turi -->
                                    <div v-show="sellStep === 2" class="space-y-6 font-sans">
                                        <!-- Main Payment Type Options -->
                                        <div class="space-y-3">
                                            <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl border border-gray-150 hover:bg-gray-50/50 transition-colors">
                                                <input type="radio" v-model="contractForm.payment_type" value="full" class="text-[#f2a900] focus:ring-[#f2a900] bg-white border-gray-300">
                                                <span class="text-xs font-bold text-gray-800 font-sans">Bir martalik umumiy to'lov</span>
                                            </label>
                                            
                                            <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl border border-gray-150 hover:bg-gray-50/50 transition-colors">
                                                <input type="radio" v-model="contractForm.payment_type" value="installment" class="text-[#f2a900] focus:ring-[#f2a900] bg-white border-gray-300">
                                                <span class="text-xs font-bold text-gray-800 font-sans">Bo'lib to'lash</span>
                                            </label>
                                        </div>
                                        
                                        <!-- Installment Sub-block Options -->
                                        <div v-if="contractForm.payment_type === 'installment'" class="pl-6 border-l-2 border-amber-100/50 space-y-5">
                                            <!-- Subtype 1: Muddatli to'lov Asosida -->
                                            <div class="space-y-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="radio" v-model="installmentSubtype" value="term" class="text-[#f2a900] focus:ring-[#f2a900] bg-white border-gray-300">
                                                    <span class="text-xs font-bold text-gray-800 font-sans">Muddatli to'lov Asosida</span>
                                                </label>
                                                
                                                <div v-if="installmentSubtype === 'term'" class="pl-5 space-y-2">
                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                        <input type="radio" v-model="installmentCalculationType" value="schedule" class="text-[#f2a900] focus:ring-[#f2a900] bg-white border-gray-300">
                                                        <span class="text-[11px] font-semibold text-gray-700 font-sans">Grafik Asosida</span>
                                                    </label>
                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                        <input type="radio" v-model="installmentCalculationType" value="manual" class="text-[#f2a900] focus:ring-[#f2a900] bg-white border-gray-300">
                                                        <span class="text-[11px] font-semibold text-gray-700 font-sans">Qo'lda kiritish</span>
                                                    </label>
                                                    
                                                    <div v-if="installmentCalculationType === 'manual'" class="pt-1 max-w-[220px]">
                                                        <label class="block text-[9px] font-bold text-gray-500 mb-0.5">Oylik To'lov miqdori</label>
                                                        <div class="flex items-center bg-[#f8fafc] border border-gray-200 rounded-lg px-3 py-2">
                                                            <input type="number" v-model.number="contractForm.custom_monthly_amount" class="bg-transparent border-0 text-gray-800 text-xs outline-none focus:ring-0 w-full p-0 font-sans font-bold" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Subtype 2: Ipoteka Kiriditi Asosida -->
                                            <div class="space-y-3">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="radio" v-model="installmentSubtype" value="mortgage" class="text-[#f2a900] focus:ring-[#f2a900] bg-white border-gray-300">
                                                    <span class="text-xs font-bold text-gray-800 font-sans">Ipoteka Kiriditi Asosida</span>
                                                </label>
                                                
                                                <div v-if="installmentSubtype === 'mortgage'" class="pl-5 space-y-2">
                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                        <input type="radio" v-model="installmentCalculationType" value="schedule" class="text-[#f2a900] focus:ring-[#f2a900] bg-white border-gray-300">
                                                        <span class="text-[11px] font-semibold text-gray-700 font-sans">Grafik Asosida</span>
                                                    </label>
                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                        <input type="radio" v-model="installmentCalculationType" value="manual" class="text-[#f2a900] focus:ring-[#f2a900] bg-white border-gray-300">
                                                        <span class="text-[11px] font-semibold text-gray-700 font-sans">Qo'lda kiritish</span>
                                                    </label>
                                                    
                                                    <div v-if="installmentCalculationType === 'manual'" class="pt-1 max-w-[220px]">
                                                        <label class="block text-[9px] font-bold text-gray-500 mb-0.5">Oylik To'lov miqdori</label>
                                                        <div class="flex items-center bg-[#f8fafc] border border-gray-200 rounded-lg px-3 py-2">
                                                            <input type="number" v-model.number="contractForm.custom_monthly_amount" class="bg-transparent border-0 text-gray-800 text-xs outline-none focus:ring-0 w-full p-0 font-sans font-bold" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- STEP 3: To'lov ma'lumotlari -->
                                    <div v-show="sellStep === 3" class="space-y-4 font-sans">
                                        <!-- Text Summary details -->
                                        <div class="space-y-2 text-xs text-gray-700 bg-gray-50/50 p-3 rounded-xl border border-gray-100">
                                            <div class="flex justify-between items-center">
                                                <span class="font-semibold text-gray-500">to'lov Muddat :</span>
                                                <span class="font-bold text-gray-950">{{ contractForm.term_months }} oy</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="font-semibold text-gray-500">To'lov turi :</span>
                                                <span class="font-bold text-gray-950">
                                                    {{ contractForm.payment_type === 'full' ? 'Bir martalik to\'lov' : 'Bo\'lib to\'lash' }}
                                                </span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="font-semibold text-gray-500">Grafik bo'yicha to'lov :</span>
                                                <span class="font-bold text-gray-950">{{ formatSoM(formattedCalculatedMonthlyPayment) }}</span>
                                            </div>
                                            <div v-if="installmentCalculationType === 'manual'" class="flex justify-between items-center">
                                                <span class="font-semibold text-gray-500">Qo'lda kiritilgan to'lov :</span>
                                                <span class="font-bold text-gray-950">{{ formatSoM(formattedCustomMonthlyPayment) }}</span>
                                            </div>
                                        </div>

                                        <!-- TO'LOV QABUL QILISH SECTION -->
                                        <div class="space-y-3 pt-1">
                                            <h4 class="text-xs font-bold text-[#10b981] uppercase tracking-wider">To'lov qabul qilish</h4>
                                            
                                            <!-- Acceptance Inputs -->
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-[10px] font-semibold text-gray-500 mb-1">1-Boshlang'ich to'lov</label>
                                                    <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-3 py-2">
                                                        <input type="number" v-model.number="initialDownPaymentPaid" class="bg-transparent border-0 text-gray-800 text-xs outline-none focus:ring-0 w-full p-0 font-sans" placeholder="Ixtiyoriy" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-semibold text-gray-500 mb-1">1-Oylik To'lov miqdori</label>
                                                    <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-3 py-2">
                                                        <input type="number" v-model.number="initialInstallmentPaid" class="bg-transparent border-0 text-gray-800 text-xs outline-none focus:ring-0 w-full p-0 font-sans" placeholder="Ixtiyoriy" />
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Account and Payment Method -->
                                            <div v-if="Number(contractForm.actual_paid_amount) > 0" class="space-y-3 pt-1">
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-500 mb-1">Kassaga yo'naltirish</label>
                                                        <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-3 py-2">
                                                            <select v-model="contractForm.account_id" class="bg-transparent border-0 text-gray-800 text-xs outline-none focus:ring-0 w-full p-0 font-sans bg-white" :required="Number(contractForm.actual_paid_amount) > 0">
                                                                <option value="" disabled>Kassani tanlang</option>
                                                                <option v-for="reg in registers" :key="reg.id" :value="reg.id">
                                                                    {{ reg.name }} ({{ reg.currency }})
                                                                </option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-500 mb-2">To'lov usuli</label>
                                                        <div class="flex gap-4 items-center h-8">
                                                            <label class="flex items-center gap-1.5 cursor-pointer">
                                                                <input type="radio" v-model="contractForm.payment_method" value="main" class="text-amber-500 focus:ring-amber-500 bg-white border-gray-300">
                                                                <span class="text-xs text-gray-800">Naqd</span>
                                                            </label>
                                                            <label class="flex items-center gap-1.5 cursor-pointer">
                                                                <input type="radio" v-model="contractForm.payment_method" value="bank" class="text-amber-500 focus:ring-amber-500 bg-white border-gray-300">
                                                                <span class="text-xs text-gray-800">Bank</span>
                                                            </label>
                                                            <label class="flex items-center gap-1.5 cursor-pointer">
                                                                <input type="radio" v-model="contractForm.payment_method" value="credit" class="text-amber-500 focus:ring-amber-500 bg-white border-gray-300">
                                                                <span class="text-xs text-gray-800">Nasiya</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- RIGHT/BOTTOM SUMMARY CARD -->
                                        <div class="bg-gray-50 border border-gray-150 rounded-2xl p-4 space-y-3">
                                            <div>
                                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wide">Oylik to'lov</span>
                                                <div class="text-xl font-black text-gray-900 mt-0.5">
                                                    {{ formatSoM(installmentCalculationType === 'manual' && contractForm.custom_monthly_amount ? contractForm.custom_monthly_amount : formattedCalculatedMonthlyPayment) }}
                                                </div>
                                            </div>
                                            <div class="h-px bg-gray-200"></div>
                                            <div class="space-y-1.5 text-[11px] font-sans">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-gray-500">Umumiy summa</span>
                                                    <span class="font-bold text-gray-850">
                                                        {{ formatSoM((Number(basePricePerSqm) || 0) * (Number(currentSellingUnit?.area_sqm) || 0)) }}
                                                    </span>
                                                </div>
                                                <div class="flex justify-between items-center text-emerald-600">
                                                    <span>Chegirma umumiy</span>
                                                    <span class="font-bold">- {{ formatSoM(totalDiscount) }}</span>
                                                </div>
                                                <div class="flex justify-between items-center text-gray-900 font-bold">
                                                    <span>Jami summa (chegirma bilan)</span>
                                                    <span>{{ formatSoM(contractForm.total_amount) }}</span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-gray-500">Boshlang'ich to'lov</span>
                                                    <span class="font-bold text-gray-850">{{ formatSoM(contractForm.down_payment) }}</span>
                                                </div>
                                                <div class="flex justify-between items-center text-[#e67e22] font-bold">
                                                    <span>Qoldiq summa</span>
                                                    <span>{{ formatSoM(Number(contractForm.total_amount) - Number(contractForm.down_payment)) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex justify-end gap-3 pt-4 border-t border-gray-150 mt-6 shrink-0">
                                    <button type="button" @click="sellStep > 1 ? prevSellStep() : (activeRightPanel = 'details')"
                                            class="px-6 py-2.5 text-xs font-bold text-gray-800 bg-white border border-gray-300 rounded-full hover:bg-gray-50 transition-all min-w-[120px]">
                                        Bekor qilish
                                    </button>
                                    
                                    <button v-if="sellStep < 3" type="button" @click="nextSellStep"
                                            class="px-6 py-2.5 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-full transition-all min-w-[120px] shadow-sm">
                                        Davom etish
                                    </button>
                                    
                                    <button v-else type="submit" :disabled="isProcessingContract"
                                            class="px-6 py-2.5 bg-[#10b981] hover:bg-emerald-600 text-white text-xs font-bold rounded-full shadow-sm transition-all min-w-[120px] disabled:opacity-50">
                                        {{ isProcessingContract ? 'Yaratilmoqda...' : 'SAVDO HUNISINI YARATISH' }}
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
                                        <div v-if="unitForm.type === 'residential'" class="grid grid-cols-4 gap-2">
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

                                        <!-- Status & Podyezd -->
                                        <div class="grid grid-cols-2 gap-4">
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
                                        </div>

                                        <!-- Joylashuvi & Balkon -->
                                        <div class="grid gap-4" :class="unitForm.type === 'residential' ? 'grid-cols-2' : 'grid-cols-1'">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Joylashuvi</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-2.5 py-3">
                                                    <select v-model="unitForm.orientation" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans bg-white">
                                                        <option value="">Tanlang (Ixtiyoriy)</option>
                                                        <option value="Burchak">Burchak</option>
                                                        <option value="O'rtada">O'rtada</option>
                                                        <option value="Hovliga qaragan">Hovliga qaragan</option>
                                                        <option value="Kun chiqish taraf">Kun chiqish taraf</option>
                                                        <option value="Kun botish taraf">Kun botish taraf</option>
                                                        <option value="Ko'chaga qaragan">Ko'chaga qaragan</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div v-if="unitForm.type === 'residential'">
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Balkon</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-2.5 py-3">
                                                    <select v-model="unitForm.has_balcony" class="bg-transparent border-0 text-gray-800 text-sm outline-none focus:ring-0 w-full p-0 font-sans bg-white">
                                                        <option :value="false">Balkon yo'q</option>
                                                        <option :value="true">Balkon bor</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 2D Chizma & 3D Render -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">2D Chizma (Layout)</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-3 py-2.5">
                                                    <input type="file" @change="e => unitForm.blueprint = e.target.files[0]" class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 w-full" />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">3D Render (Surat)</label>
                                                <div class="relative flex items-center bg-[#f8fafc] border border-gray-200 rounded-xl px-3 py-2.5">
                                                    <input type="file" @change="e => unitForm.model_3d = e.target.files[0]" class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 w-full" />
                                                </div>
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

        <!-- EXCEL PREVIEW MODAL -->
        <Modal :show="showExcelPreviewModal" @close="showExcelPreviewModal = false" maxWidth="4xl">
            <div class="p-6 font-sans">
                <div class="flex justify-between items-center pb-4 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900">Excel Yuklab Olish - Oynani ko'rish</h3>
                    <button @click="showExcelPreviewModal = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">×</button>
                </div>

                <!-- Report Table Preview -->
                <div class="my-6 border border-gray-200 rounded-xl overflow-hidden shadow-inner bg-gray-50/30 p-2">
                    <div class="overflow-x-auto max-h-[350px]">
                        <table class="w-full text-xs text-left border-collapse border border-gray-300 bg-white">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700 uppercase">
                                    <th class="border border-gray-300 p-2 text-center">A (№)</th>
                                    <th class="border border-gray-300 p-2">B (Nomi)</th>
                                    <th class="border border-gray-300 p-2 text-center">C (Xonadonlar soni)</th>
                                    <th class="border border-gray-300 p-2 text-center">D (Sotilgan xonadonlar)</th>
                                    <th class="border border-gray-300 p-2 text-center">E (Sotuvdagi xonadonlar)</th>
                                    <th class="border border-gray-300 p-2 text-right">F (Sotilgan uylar narxi)</th>
                                    <th class="border border-gray-300 p-2 text-right">G (Kutilayotgan to'lovlar)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, idx) in filteredReportData" :key="'prev-'+idx" class="hover:bg-gray-50">
                                    <td class="border border-gray-300 p-2 text-center font-mono">{{ idx + 1 }}</td>
                                    <td class="border border-gray-300 p-2 font-semibold">{{ row.name }}</td>
                                    <td class="border border-gray-300 p-2 text-center font-mono">{{ row.max_apartments }}</td>
                                    <td class="border border-gray-300 p-2 text-center font-mono">{{ row.sold_apartments }}</td>
                                    <td class="border border-gray-300 p-2 text-center font-mono">{{ row.available_apartments }}</td>
                                    <td class="border border-gray-300 p-2 text-right text-emerald-600 font-bold font-mono">{{ formatSoM(row.sold_apartments_price) }}</td>
                                    <td class="border border-gray-300 p-2 text-right text-red-500 font-bold font-mono">{{ formatSoM(row.debt_price) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal Actions/Footer -->
                <div class="flex justify-end items-center gap-3 pt-4 border-t border-gray-100">
                    <button 
                        @click="showExcelPreviewModal = false" 
                        class="px-4 py-2 border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-xl transition-all"
                    >
                        Bekor qilish
                    </button>
                    <a 
                        :href="route('admin.facility.report.export', { start_month: startMonth, end_month: endMonth })"
                        @click="showExcelPreviewModal = false"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all flex items-center gap-1.5"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Yuklab olish
                    </a>
                </div>
            </div>
        </Modal>

        <!-- Add/Edit Building Modal -->

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
                    <div v-if="unitForm.type === 'residential'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" :class="unitForm.type === 'residential' ? 'lg:grid-cols-4' : 'lg:grid-cols-3'">
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Status</label>
                            <select v-model="unitForm.status" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all" required>
                                <option value="available">Bo'sh</option>
                                <option value="reserved">Band qilingan</option>
                                <option value="sold">Sotilgan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Podyezd</label>
                            <input v-model="unitForm.podyezd" type="number" min="1" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all" required>
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Joylashuvi</label>
                            <select v-model="unitForm.orientation" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all">
                                <option value="">Tanlang (Ixtiyoriy)</option>
                                <option value="Burchak">Burchak</option>
                                <option value="O'rtada">O'rtada</option>
                                <option value="Hovliga qaragan">Hovliga qaragan</option>
                                <option value="Kun chiqish taraf">Kun chiqish taraf</option>
                                <option value="Kun botish taraf">Kun botish taraf</option>
                                <option value="Ko'chaga qaragan">Ko'chaga qaragan</option>
                            </select>
                        </div>
                        <div v-if="unitForm.type === 'residential'">
                            <label class="block text-xs font-mono text-gray-500 mb-1">Balkon</label>
                            <select v-model="unitForm.has_balcony" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all">
                                <option :value="false">Balkon yo'q</option>
                                <option :value="true">Balkon bor</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">2D Chizma (Layout)</label>
                            <input type="file" @change="e => unitForm.blueprint = e.target.files[0]" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all text-xs" />
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">3D Render (Surat)</label>
                            <input type="file" @change="e => unitForm.model_3d = e.target.files[0]" class="w-full bg-white border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-blue-500 transition-all text-xs" />
                        </div>
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

        <!-- Blueprint Fullscreen Page -->
        <div v-if="showBlueprintFullscreen" class="fixed inset-0 z-[100] bg-slate-50 text-slate-800 font-sans flex flex-col select-none overflow-y-auto w-full h-full animate-[fadeIn_0.3s_ease-out]">
            <!-- Header -->
            <div class="px-6 py-4 bg-white border-b border-gray-200 flex justify-between items-center shrink-0 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">📐</span>
                    <div>
                        <h2 class="text-base md:text-lg font-bold uppercase tracking-wider text-gray-900 font-display">
                            {{ selectedUnitForFullscreenBlueprint ? `${selectedUnitForFullscreenBlueprint.type === 'commercial' ? "Do'kon" : "Xonadon"} № ${selectedUnitForFullscreenBlueprint.unit_number} Tafsiloti` : 'Loyiha chizmasi' }}
                        </h2>
                        <p class="text-[10px] md:text-xs text-gray-500 font-medium">Bino: {{ selectedBuilding?.name || 'N/A' }} / Qavat: {{ selectedFloor?.floor_number }}</p>
                    </div>
                </div>
                <!-- Close Button -->
                <button @click="showBlueprintFullscreen = false" class="px-4 py-2 bg-white hover:bg-gray-100 text-gray-700 hover:text-gray-900 text-xs font-bold rounded-xl transition-all border border-gray-200 shadow-sm flex items-center gap-1.5">
                    ✕ Chiqish
                </button>
            </div>
            
            <!-- Body Area -->
            <div class="flex-1 p-6 md:p-8">
                <div class="max-w-7xl mx-auto w-full h-full flex flex-col">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 flex-1 min-h-0">
                        <!-- Left Column (Visual Viewer - 70%) -->
                        <div class="lg:col-span-2 bg-white border border-gray-200 rounded-3xl p-6 relative flex flex-col items-center justify-center min-h-[450px] lg:min-h-[580px] overflow-hidden group shadow-sm">
                            <!-- Main Display Image -->
                            <div class="flex-1 w-full flex items-center justify-center overflow-hidden relative">
                                <img :src="activeViewMode === '3d' && fullscreen3DModelSrc ? fullscreen3DModelSrc : fullscreenBlueprintSrc" 
                                     class="max-h-[60vh] max-w-full object-contain rounded-xl transition-all duration-300 select-none pointer-events-none"
                                     :style="{ transform: 'scale(' + zoomScale + ')', transformOrigin: 'center center' }" />
                            </div>

                            <!-- Floating Vertical Control Bar -->
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 flex flex-col gap-2 bg-slate-900/80 backdrop-blur-md p-2 rounded-2xl border border-slate-700 shadow-lg text-white z-30">
                                <button @click="zoomIn" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-white/20 transition-all font-bold text-lg" title="Kattalashtirish">+</button>
                                <button @click="zoomOut" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-white/20 transition-all font-bold text-lg" title="Kichiklashtirish">-</button>
                                <button @click="zoomReset" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-white/20 transition-all text-sm" title="Dastlabki holatga qaytarish">🔄</button>
                            </div>

                            <!-- Thumbnails Row -->
                            <div class="mt-4 flex gap-4 justify-center shrink-0 border-t border-gray-100 pt-4 w-full">
                                <!-- Thumbnail 1: 2D Plan -->
                                <button type="button" 
                                        @click="activeViewMode = '2d'"
                                        class="flex flex-col items-center gap-1.5 p-1.5 rounded-xl border-2 transition-all hover:scale-105 bg-gray-50"
                                        :class="activeViewMode === '2d' ? 'border-amber-500 scale-102 shadow-sm' : 'border-gray-200 opacity-60 hover:opacity-100'">
                                    <div class="w-16 h-12 rounded-lg overflow-hidden flex items-center justify-center bg-white border border-gray-100">
                                        <img :src="fullscreenBlueprintSrc" class="max-h-full max-w-full object-contain" />
                                    </div>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-gray-500 font-mono">2D Chizma</span>
                                </button>
                                
                                <!-- Thumbnail 2: 3D Render (only if available) -->
                                <button v-if="fullscreen3DModelSrc" 
                                        type="button" 
                                        @click="activeViewMode = '3d'"
                                        class="flex flex-col items-center gap-1.5 p-1.5 rounded-xl border-2 transition-all hover:scale-105 bg-gray-50"
                                        :class="activeViewMode === '3d' ? 'border-amber-500 scale-102 shadow-sm' : 'border-gray-200 opacity-60 hover:opacity-100'">
                                    <div class="w-16 h-12 rounded-lg overflow-hidden flex items-center justify-center bg-white border border-gray-100">
                                        <img :src="fullscreen3DModelSrc" class="max-h-full max-w-full object-contain" />
                                    </div>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-gray-500 font-mono">3D Render</span>
                                </button>
                            </div>
                        </div>

                        <!-- Right Column (Details & Actions - 30%) -->
                        <div class="flex flex-col justify-between">
                            <div>
                                <!-- Floor Plan Mini-map Card -->
                                <div class="bg-white border border-gray-200 rounded-3xl p-4 shadow-sm relative group overflow-hidden">
                                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest font-sans mb-3">Qavat Xaritasi</h4>
                                    <div class="w-full h-36 bg-gray-50 rounded-2xl overflow-hidden flex items-center justify-center p-2 relative border border-gray-100">
                                        <img v-if="selectedFloor?.blueprint_path" :src="`/storage/${selectedFloor.blueprint_path}`" class="h-full w-full object-contain opacity-80" />
                                        <div v-else class="text-xs text-gray-400 font-mono">Xarita yuklanmagan</div>
                                        
                                        <!-- Highlight Overlay if svg_polygon exists -->
                                        <svg v-if="selectedUnitForFullscreenBlueprint?.svg_polygon" 
                                             class="absolute inset-0 w-full h-full pointer-events-none" 
                                             viewBox="0 0 1000 450" 
                                             preserveAspectRatio="xMidYMid meet">
                                            <polygon :points="selectedUnitForFullscreenBlueprint.svg_polygon" 
                                                     fill="#f59e0b" 
                                                     fill-opacity="0.4" 
                                                     stroke="#d97706" 
                                                     stroke-width="5" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Specs Grid -->
                                <div class="grid grid-cols-2 gap-4 mt-6">
                                    <!-- 1. Xonadon raqami -->
                                    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-lg text-amber-500 shrink-0">🔑</div>
                                        <div>
                                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">{{ selectedUnitForFullscreenBlueprint?.type === 'commercial' ? "Do'kon" : "Xonadon" }}</span>
                                            <strong class="text-sm font-bold text-gray-900">№ {{ selectedUnitForFullscreenBlueprint?.unit_number }}</strong>
                                        </div>
                                    </div>
                                    <!-- 2. Rooms count -->
                                    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-lg text-blue-500 shrink-0">🚪</div>
                                        <div>
                                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Xonalar</span>
                                            <strong class="text-sm font-bold text-gray-900">{{ selectedUnitForFullscreenBlueprint?.rooms_count || 1 }} xonali</strong>
                                        </div>
                                    </div>
                                    <!-- 3. Floor -->
                                    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center text-lg text-purple-500 shrink-0">🏢</div>
                                        <div>
                                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Qavat</span>
                                            <strong class="text-sm font-bold text-gray-900">{{ selectedFloor?.floor_number }}-qavat</strong>
                                        </div>
                                    </div>
                                    <!-- 4. Area -->
                                    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-lg text-emerald-500 shrink-0">📐</div>
                                        <div>
                                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Maydoni</span>
                                            <strong class="text-sm font-bold text-gray-900">{{ selectedUnitForFullscreenBlueprint?.area_sqm }} m²</strong>
                                        </div>
                                    </div>
                                    <!-- 5. Entry (Podezd) -->
                                    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-lg text-indigo-500 shrink-0">🧱</div>
                                        <div>
                                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Podezd</span>
                                            <strong class="text-sm font-bold text-gray-900">{{ selectedUnitForFullscreenBlueprint?.podyezd }}-padezd</strong>
                                        </div>
                                    </div>
                                    <!-- 6. Status -->
                                    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0" 
                                             :class="{
                                                 'bg-green-50 text-green-500': selectedUnitForFullscreenBlueprint?.status === 'available',
                                                 'bg-yellow-50 text-yellow-500': selectedUnitForFullscreenBlueprint?.status === 'reserved',
                                                 'bg-red-50 text-red-500': selectedUnitForFullscreenBlueprint?.status === 'sold'
                                             }">
                                             {{ selectedUnitForFullscreenBlueprint?.status === 'available' ? '🟢' : selectedUnitForFullscreenBlueprint?.status === 'reserved' ? '🟡' : '🔴' }}
                                        </div>
                                        <div>
                                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Holati</span>
                                            <strong class="text-xs font-bold" 
                                                    :class="{
                                                        'text-green-600': selectedUnitForFullscreenBlueprint?.status === 'available',
                                                        'text-yellow-600': selectedUnitForFullscreenBlueprint?.status === 'reserved',
                                                        'text-red-600': selectedUnitForFullscreenBlueprint?.status === 'sold'
                                                    }">
                                                {{ $t(selectedUnitForFullscreenBlueprint?.status) || selectedUnitForFullscreenBlueprint?.status }}
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="mt-6 space-y-4">
                                <!-- Row 1: Main Download Button -->
                                <button @click="downloadActiveImage" 
                                        class="w-full py-3.5 bg-amber-500 hover:bg-amber-600 text-white font-bold font-sans rounded-2xl text-base shadow-sm transition-all text-center flex items-center justify-center gap-2">
                                    📥 Chizmani Yuklab Olish
                                </button>
                                
                                <!-- Row 2: Share and Sell buttons side-by-side -->
                                <div class="flex gap-4">
                                    <button @click="shareUnitDetails" 
                                            class="flex-1 py-3.5 border border-gray-200 hover:border-amber-500 hover:bg-amber-50 text-gray-700 hover:text-amber-700 font-bold font-sans rounded-2xl text-sm shadow-sm transition-all text-center flex items-center justify-center gap-2">
                                        🔗 Ulashish
                                    </button>
                                    <button v-if="selectedUnitForFullscreenBlueprint?.status !== 'sold'" 
                                            @click="sellFromBlueprint" 
                                            class="flex-1 py-3.5 bg-amber-600 hover:bg-amber-700 text-white font-bold font-sans rounded-2xl text-sm shadow-sm transition-all text-center flex items-center justify-center gap-2">
                                        🛒 Sotish
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Building Photo Fullscreen Modal -->
        <Modal :show="showBuildingPhotoFullscreen" @close="showBuildingPhotoFullscreen = false" maxWidth="4xl">
            <div class="p-6 relative bg-white dark:bg-zinc-900 rounded-2xl font-sans">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ selectedBuilding?.block_name || selectedBuilding?.corpus_name || 'Bino' }} - Surat / Render
                    </h3>
                    <button @click="showBuildingPhotoFullscreen = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex items-center justify-center bg-gray-100 dark:bg-zinc-950 rounded-xl overflow-auto p-4 min-h-[400px] max-h-[80vh]">
                    <img :src="buildingPhotoFullscreenSrc" 
                         class="max-h-full max-w-full object-contain rounded-lg shadow-sm" />
                </div>
            </div>
        </Modal>
        </div>

        <!-- INLINE SUBPAGE: UNIFIED CLIENT CREATION & SELL -->
        <div v-if="showUnifiedSellModal" class="bg-white p-8 rounded-3xl w-full shadow-md border border-gray-150 transition-all font-sans my-4">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-1.5 h-6 bg-[#f2a900] rounded-full"></span>
                    Mijoz qo'shish va shartnoma yaratish
                </h2>
                <button type="button" @click="showUnifiedSellModal = false" class="text-gray-400 hover:text-gray-600 transition-colors flex items-center gap-1 text-xs font-bold uppercase tracking-wider bg-white border border-gray-200 px-3 py-1.5 rounded-full shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Orqaga
                </button>
            </div>

            <!-- Stepper Progress Header -->
            <div class="mb-8 max-w-4xl mx-auto">
                <div class="flex items-center justify-between">
                    <!-- Step 1: Mijoz ma'lumotlari -->
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300"
                             :class="currentStep > 1 ? 'bg-[#f2a900] text-white' : (currentStep === 1 ? 'bg-[#f2a900] text-white ring-4 ring-amber-100' : 'bg-gray-100 text-gray-400')">
                            <span v-if="currentStep > 1">✓</span>
                            <span v-else>1</span>
                        </div>
                        <span class="text-xs font-semibold" :class="currentStep === 1 ? 'text-gray-900 font-bold' : 'text-gray-400'">Mijoz ma'lumotlari</span>
                    </div>

                    <!-- Divider Line 1-2 -->
                    <div class="flex-1 h-0.5 mx-4 transition-all duration-500"
                         :class="currentStep > 1 ? 'bg-[#f2a900]' : 'bg-gray-100'"></div>

                    <!-- Step 2: Shartnoma va xonadon -->
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300"
                             :class="currentStep > 2 ? 'bg-[#f2a900] text-white' : (currentStep === 2 ? 'bg-[#f2a900] text-white ring-4 ring-amber-100' : 'bg-gray-100 text-gray-400')">
                            <span v-if="currentStep > 2">✓</span>
                            <span v-else>2</span>
                        </div>
                        <span class="text-xs font-semibold" :class="currentStep === 2 ? 'text-gray-900 font-bold' : 'text-gray-400'">Shartnoma va xonadon</span>
                    </div>

                    <!-- Divider Line 2-3 -->
                    <div class="flex-1 h-0.5 mx-4 transition-all duration-500"
                         :class="currentStep > 2 ? 'bg-[#f2a900]' : 'bg-gray-100'"></div>

                    <!-- Step 3: To'lov ma'lumotlari -->
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300"
                             :class="currentStep === 3 ? 'bg-[#f2a900] text-white ring-4 ring-amber-100' : 'bg-gray-100 text-gray-400'">
                            3
                        </div>
                        <span class="text-xs font-semibold" :class="currentStep === 3 ? 'text-gray-900 font-bold' : 'text-gray-400'">To'lov ma'lumotlari</span>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submitUnifiedSell" class="space-y-6 text-left">
                <!-- STEP 1: Mijoz ma'lumotlari -->
                <div v-show="currentStep === 1" class="space-y-6">
                    <div>
                        <h3 class="text-xs font-bold text-gray-800 mb-4 flex items-center gap-2 uppercase tracking-wider">
                            <span class="w-1.5 h-4 bg-amber-500 rounded-full"></span>
                            1. Mijoz ma'lumotlari
                        </h3>
                        <!-- Row 1: Familiya, Ism, Otasining ismi -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Familiya</label>
                                <input v-model="quickClientForm.last_name" type="text" placeholder="Familiyasini kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400" :required="currentStep === 1">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Ism</label>
                                <input v-model="quickClientForm.first_name" type="text" placeholder="Ismini kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400" :required="currentStep === 1">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Otasining ismi</label>
                                <input v-model="quickClientForm.middle_name" type="text" placeholder="Otasining ismini kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                            </div>
                        </div>

                        <!-- Row 2: Passport raqami, Berilgan vaqti, Kim tomonidan berilgan -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mt-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Passport raqami</label>
                                <input v-model="quickClientForm.passport_series" type="text" placeholder="AA1234567" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Berilgan vaqti</label>
                                <input v-model="quickClientForm.passport_issued_date" type="date" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Kim tomonidan berilgan</label>
                                <input v-model="quickClientForm.passport_issued_by" type="text" placeholder="Kim tomonidan berilgan" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                            </div>
                        </div>

                        <!-- Row 3: Viloyat, Tuman / Shahar, Mahalla, ko'cha nomi -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mt-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Viloyat</label>
                                <div class="relative">
                                    <select v-model="quickClientForm.region" @change="onQuickRegionChange" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer pr-10">
                                        <option value="">Tanlang</option>
                                        <option v-for="(regionObj, regionKey) in regionsData" :key="regionKey" :value="regionObj.name">
                                            {{ regionObj.name }}
                                        </option>
                                    </select>
                                    <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Tuman / Shahar</label>
                                <div class="relative">
                                    <select v-model="quickClientForm.district" @change="onQuickDistrictChange" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer pr-10">
                                        <option value="">Tanlang</option>
                                        <option v-for="districtObj in availableQuickDistricts" :key="districtObj.id" :value="districtObj.name">
                                            {{ districtObj.name }}
                                        </option>
                                    </select>
                                    <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Mahalla, ko'cha nomi</label>
                                <input v-model="quickClientForm.address" type="text" list="facilityNeighborhoods" placeholder="Manzilni kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                                <datalist id="facilityNeighborhoods">
                                    <option v-for="neighborhood in availableQuickNeighborhoods" :key="neighborhood" :value="neighborhood"></option>
                                </datalist>
                            </div>
                        </div>

                        <!-- Row 4: JSHSHIR, Telefon raqam, Telefon raqam -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mt-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">JSHSHIR</label>
                                <input v-model="quickClientForm.pinfl" type="text" placeholder="JSHSHIR ni kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Telefon raqam</label>
                                <div class="relative">
                                    <input v-model="quickClientForm.phone" type="text" placeholder="+998" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all pr-10" :required="currentStep === 1">
                                    <button type="button" @click="quickClientForm.phone = '+998'" class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Telefon raqam</label>
                                <div class="relative">
                                    <input v-model="quickClientForm.phone_2" type="text" placeholder="+998" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all pr-10">
                                    <button type="button" @click="quickClientForm.phone_2 = '+998'" class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Row 5: Pasport fayli -->
                        <div class="mt-4">
                            <label class="block text-xs font-semibold text-gray-700 mb-2">Pasport fayli (Rasm yoki PDF)</label>
                            <label class="w-full h-24 border border-dashed border-gray-300 hover:border-[#f2a900] rounded-2xl flex flex-col justify-center items-center gap-1 cursor-pointer transition-all bg-white relative overflow-hidden shadow-sm">
                                <template v-if="!quickClientForm.passport_photo">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span class="text-[10px] text-gray-500 font-medium">Faylni tanlang</span>
                                </template>
                                <template v-else>
                                    <svg class="w-6 h-6 text-emerald-500 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-[9px] text-emerald-600 font-bold px-1 truncate max-w-full" :title="typeof quickClientForm.passport_photo === 'string' ? quickClientForm.passport_photo : quickClientForm.passport_photo.name">
                                        {{ typeof quickClientForm.passport_photo === 'string' ? 'Mavjud pasport fayli' : quickClientForm.passport_photo.name }}
                                    </span>
                                </template>
                                <input type="file" class="hidden" accept="image/*,application/pdf" @change="onQuickPassportPhotoChange">
                            </label>
                            <!-- Face Preview -->
                            <div v-if="quickClientForm.profile_photo" class="mt-2 flex items-center gap-2">
                                <div class="w-10 h-10 rounded-full overflow-hidden border border-[#f2a900] shrink-0">
                                    <img :src="getProfilePreviewUrl(quickClientForm.profile_photo)" class="w-full h-full object-cover" />
                                </div>
                                <span class="text-[9px] text-gray-500 font-semibold leading-tight">Yuzingiz aniqlandi va profil rasmi yaratildi!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Shartnoma va xonadon -->
                <div v-show="currentStep === 2" class="space-y-6">
                    <div>
                        <h3 class="text-xs font-bold text-gray-800 mb-4 flex items-center gap-2 uppercase tracking-wider">
                            <span class="w-1.5 h-4 bg-[#f2a900] rounded-full"></span>
                            2. Shartnoma va xonadon
                        </h3>
                        <!-- Row 1: Source, Language, Contract ID -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5"><span class="text-red-500">*</span> Biz haqimizda qayerdan bildingiz?</label>
                                <div class="relative">
                                    <select v-model="quickClientForm.source" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all appearance-none cursor-pointer pr-10" :required="currentStep === 2">
                                        <option value="" disabled>Tanlang</option>
                                        <option value="Website">Website</option>
                                        <option value="Telegram">Telegram</option>
                                        <option value="Instagram">Instagram</option>
                                        <option value="Tanish bilish">Tanish bilish</option>
                                    </select>
                                    <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5"><span class="text-red-500">*</span> Shartnoma tili</label>
                                <div class="relative">
                                    <select v-model="quickClientForm.contract_language" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all appearance-none cursor-pointer pr-10" :required="currentStep === 2">
                                        <option value="O'zbek tili">O'zbek tili</option>
                                        <option value="Rus tili">Rus tili</option>
                                    </select>
                                    <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Shartnoma id</label>
                                <div class="relative">
                                    <input v-model="quickClientForm.contract_id" type="text" placeholder="Shartnoma kodi kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all placeholder:text-gray-400">
                                    <span class="absolute right-4 top-3.5 text-gray-400">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Tanlang Xonadon (Read only) -->
                        <div class="mt-6">
                            <label class="block text-xs font-semibold text-gray-700 mb-2">Tanlangan {{ currentSellingUnit?.type === 'commercial' ? "do'kon" : "xonadon" }} ma'lumotlari</label>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 bg-gray-50 p-5 rounded-2xl border border-gray-200 text-xs">
                                <div>
                                    <span class="text-gray-500 block mb-1">{{ currentSellingUnit?.type === 'commercial' ? "Do'kon raqami:" : "Xonadon:" }}</span>
                                    <strong class="text-gray-900 text-sm font-bold">{{ currentSellingUnit?.unit_number || 'N/A' }}</strong>
                                </div>
                                <div>
                                    <span class="text-gray-500 block mb-1">Bino / Podezd:</span>
                                    <strong class="text-gray-900 text-sm font-bold">{{ selectedFloor?.building?.name || 'N/A' }} / {{ currentSellingUnit?.podyezd || '1' }}-podezd</strong>
                                </div>
                                <div>
                                    <span class="text-gray-500 block mb-1">Qavat:</span>
                                    <strong class="text-gray-900 text-sm font-bold">{{ selectedFloor?.floor_number }}</strong>
                                </div>
                                <div>
                                    <span class="text-gray-500 block mb-1">Xonalar soni:</span>
                                    <strong class="text-gray-900 text-sm font-bold">{{ currentSellingUnit?.rooms_count || '1' }} xona</strong>
                                </div>
                                <div>
                                    <span class="text-gray-500 block mb-1">Maydoni:</span>
                                    <strong class="text-gray-900 text-sm font-bold">{{ currentSellingUnit?.area_sqm || 'N/A' }} m²</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: To'lov ma'lumotlari -->
                <div v-show="currentStep === 3" class="space-y-6">
                    <div>
                        <h3 class="text-xs font-bold text-gray-800 mb-4 flex items-center gap-2 uppercase tracking-wider">
                            <span class="w-1.5 h-4 bg-blue-500 rounded-full"></span>
                            3. To'lov ma'lumotlari
                        </h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Umumiy narxi (QQS bilan)</label>
                                <input v-model.number="contractForm.total_amount" type="number" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Kvadrat metri narxi</label>
                                <input v-model="contractForm.price_per_sqm" type="number" readonly tabindex="-1" class="w-full bg-gray-100 border border-gray-200 text-gray-500 text-xs rounded-xl px-4 py-3 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-4">To'lov turi</label>
                                <div class="flex items-center gap-4 text-xs text-gray-800 pt-1">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="contractForm.payment_type" value="full" class="w-4 h-4 text-[#f2a900] border-gray-300 focus:ring-[#f2a900]">
                                        <span>Bir martalik to'lov</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="contractForm.payment_type" value="installment" class="w-4 h-4 text-[#f2a900] border-gray-300 focus:ring-[#f2a900]">
                                        <span>Bo'lib to'lash</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mt-4" v-if="contractForm.payment_type === 'installment'">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Boshlang'ich to'lov</label>
                                <input v-model.number="contractForm.down_payment" type="number" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Muddat (oy)</label>
                                <input v-model.number="contractForm.term_months" type="number" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                            </div>
                        </div>

                        <!-- Boshlang'ich to'lovni bo'lib to'lash (Split down payment option) -->
                        <div class="space-y-3 p-4 bg-amber-50/50 border border-amber-100 rounded-xl mt-4" v-if="contractForm.payment_type === 'installment' && Number(contractForm.down_payment) > 0">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="contractForm.is_down_payment_split" class="text-amber-500 rounded border-gray-300 focus:ring-amber-500">
                                <span class="text-xs font-semibold text-gray-800">Boshlang'ich to'lovni ham bo'lib to'lash</span>
                            </label>
                            
                            <div v-if="contractForm.is_down_payment_split" class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center pt-2">
                                <div>
                                    <label class="text-xs font-semibold text-gray-700 mb-1.5 block">Boshlang'ich to'lov muddati (oy)</label>
                                    <input type="number" v-model.number="contractForm.down_payment_split_months" min="1" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all font-bold" :required="contractForm.is_down_payment_split" />
                                </div>
                            </div>
                            <div v-if="contractForm.is_down_payment_split" class="text-xs text-amber-700 font-sans">
                                * Boshlang'ich to'lov har oy <strong>{{ formatSoM(Math.round(Number(contractForm.down_payment || 0) / Number(contractForm.down_payment_split_months || 1))) }}</strong>dan, jami <strong>{{ contractForm.down_payment_split_months || 1 }} oy</strong> davomida to'lanadi. Qolgan summa esa undan keyin <strong>{{ contractForm.term_months }} oy</strong> davomida bo'lib to'lanadi.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-center gap-4 pt-6 border-t border-gray-200">
                    <button v-if="currentStep > 1" type="button" @click="prevStep" class="px-8 py-3 text-xs font-bold text-gray-800 bg-white border border-gray-300 rounded-full hover:bg-gray-50 transition-all min-w-[140px]">
                        Orqaga
                    </button>
                    <button v-else type="button" @click="showUnifiedSellModal = false" class="px-8 py-3 text-xs font-bold text-gray-800 bg-white border border-gray-300 rounded-full hover:bg-gray-50 transition-all min-w-[140px]">
                        Bekor qilish
                    </button>
                    
                    <button v-if="currentStep < 3" type="button" @click="nextStep" class="px-8 py-3 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-full transition-all min-w-[140px] shadow-sm">
                        Davom etish
                    </button>
                    <button v-else type="submit" :disabled="isProcessingUnified" class="px-8 py-3 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-full transition-all min-w-[140px] shadow-sm disabled:opacity-50 flex items-center justify-center gap-2">
                        <span v-if="isProcessingUnified" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        Saqlash
                    </button>
                </div>
            </form>
        </div>

        <!-- 👁️ Showroom Fullscreen Modal -->
        <div v-if="showShowroomModal" class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-md flex flex-col select-none animate-[fadeIn_0.3s_ease-out]">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-white/10 flex justify-between items-center text-white shrink-0 bg-black/40">
                <div class="flex items-center gap-3">
                    <span class="text-xl">👁️</span>
                    <div>
                        <h2 class="text-base font-bold uppercase tracking-wider font-display">{{ currentObject }} showroomi</h2>
                        <p class="text-[10px] text-gray-400 font-medium">Barcha 3D chizmalar va showroom rasmlari galereyasi</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Manage Button -->
                    <button @click="showroomManageMode = !showroomManageMode" class="px-4 py-2 border text-xs font-bold rounded-xl transition-all flex items-center gap-1.5"
                        :class="showroomManageMode ? 'bg-amber-500 hover:bg-amber-600 border-transparent text-white' : 'bg-white/10 hover:bg-white/20 border-white/20 text-white'">
                        {{ showroomManageMode ? '👁️ Galereya' : '⚙️ Rasmlarni boshqarish' }}
                    </button>
                    <!-- Close Button -->
                    <button @click="showShowroomModal = false" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all border border-white/10">
                        ✕
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-hidden relative flex flex-col items-center justify-center p-6 text-white">
                <!-- VIEW MODE: Gallery -->
                <div v-if="!showroomManageMode" class="w-full h-full flex flex-col justify-between">
                    <!-- Image Display Area -->
                    <div class="flex-1 flex items-center justify-center relative group min-h-0">
                        <template v-if="currentObjectContainer?.showroom_images?.length">
                            <!-- Left Arrow -->
                            <button v-if="currentObjectContainer.showroom_images.length > 1" 
                                @click="activeShowroomImageIndex = (activeShowroomImageIndex - 1 + currentObjectContainer.showroom_images.length) % currentObjectContainer.showroom_images.length"
                                class="absolute left-4 w-12 h-12 rounded-full bg-black/40 hover:bg-black/75 text-white flex items-center justify-center border border-white/10 text-xl font-bold transition-all z-10">
                                ‹
                            </button>

                            <!-- Active Image -->
                            <div class="max-w-full max-h-full flex items-center justify-center relative p-4">
                                <img :src="'/storage/' + currentObjectContainer.showroom_images[activeShowroomImageIndex]" 
                                     class="max-w-full max-h-[70vh] object-contain rounded-xl shadow-2xl border border-white/5 animate-[scaleIn_0.3s_ease-out]" />
                                
                                <!-- Floating Download Button -->
                                <div class="absolute bottom-6 right-6">
                                    <a :href="'/storage/' + currentObjectContainer.showroom_images[activeShowroomImageIndex]" 
                                       download 
                                       class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-1.5 uppercase font-mono tracking-wider">
                                        📥 Yuklab olish
                                    </a>
                                </div>
                            </div>

                            <!-- Right Arrow -->
                            <button v-if="currentObjectContainer.showroom_images.length > 1" 
                                @click="activeShowroomImageIndex = (activeShowroomImageIndex + 1) % currentObjectContainer.showroom_images.length"
                                class="absolute right-4 w-12 h-12 rounded-full bg-black/40 hover:bg-black/75 text-white flex items-center justify-center border border-white/10 text-xl font-bold transition-all z-10">
                                ›
                            </button>
                        </template>

                        <!-- Empty State -->
                        <div v-else class="text-center space-y-4 max-w-sm">
                            <div class="w-16 h-16 bg-white/5 rounded-full flex items-center justify-center text-4xl mx-auto border border-white/10">
                                🖼️
                            </div>
                            <h3 class="text-lg font-bold text-gray-200">Showroom rasmlari yuklanmagan</h3>
                            <p class="text-xs text-gray-400 leading-relaxed">Ushbu obyekt uchun hali showroom yoki 3D rasmlari kiritilmagan. Yuqoridagi "Rasmlarni boshqarish" tugmasi orqali rasm yuklashingiz mumkin.</p>
                        </div>
                    </div>

                    <!-- Bottom Indicator Carousel / Thumbnails -->
                    <div v-if="currentObjectContainer?.showroom_images?.length" class="h-24 border-t border-white/5 bg-black/20 shrink-0 flex items-center justify-center gap-2 overflow-x-auto px-6 py-2">
                        <div v-for="(img, idx) in currentObjectContainer.showroom_images" :key="img"
                             @click="activeShowroomImageIndex = idx"
                             class="w-20 h-16 rounded-lg overflow-hidden border-2 cursor-pointer transition-all shrink-0 relative group"
                             :class="activeShowroomImageIndex === idx ? 'border-amber-500 scale-105 shadow-md' : 'border-transparent opacity-50 hover:opacity-100'">
                            <img :src="'/storage/' + img" class="w-full h-full object-cover" />
                            <div class="absolute inset-0 bg-black/20 group-hover:bg-transparent transition-colors"></div>
                        </div>
                    </div>
                </div>

                <!-- EDIT/MANAGE MODE: Upload & Remove -->
                <div v-else class="w-full h-full flex flex-col md:flex-row gap-6 overflow-hidden animate-[fadeIn_0.3s_ease-out]">
                    <!-- Left: Upload Form Panel -->
                    <div class="w-full md:w-80 bg-white/5 border border-white/10 p-5 rounded-2xl flex flex-col gap-4 shrink-0">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-amber-500 font-mono">Yangi rasmlar yuklash</h3>
                        
                        <!-- Drag & Drop / File Selector -->
                        <div class="border-2 border-dashed border-white/20 rounded-xl p-6 text-center hover:border-amber-500 transition-colors relative cursor-pointer group">
                            <input type="file" multiple accept="image/*" id="showroom-file-input" @change="handleShowroomImagesChange" class="absolute inset-0 opacity-0 cursor-pointer" />
                            <div class="space-y-2">
                                <span class="text-3xl block group-hover:scale-110 transition-transform">➕</span>
                                <span class="text-xs font-bold text-gray-300 block">Rasmlarni tanlang</span>
                                <span class="text-[10px] text-gray-500 block">JPEG, PNG, WEBP (Maks: 10MB)</span>
                            </div>
                        </div>

                        <!-- Selected Files List -->
                        <div v-if="showroomUploadForm.images.length" class="flex-1 overflow-y-auto space-y-2 max-h-48 pr-1">
                            <div v-for="(file, idx) in showroomUploadForm.images" :key="idx" class="flex items-center justify-between p-2 bg-white/5 rounded-lg border border-white/10 text-xs">
                                <span class="truncate font-mono pr-2 max-w-[180px]">{{ file.name }}</span>
                                <button type="button" @click="showroomUploadForm.images.splice(idx, 1)" class="text-red-400 hover:text-red-500 font-bold">✕</button>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="button" 
                                @click="submitShowroomImages" 
                                :disabled="isUploadingShowroom || !showroomUploadForm.images.length"
                                class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-2 uppercase tracking-wider disabled:opacity-50 mt-auto">
                            <span v-if="isUploadingShowroom" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            📤 Yuklash ({{ showroomUploadForm.images.length }})
                        </button>
                    </div>

                    <!-- Right: Existing Images Grid -->
                    <div class="flex-1 bg-white/5 border border-white/10 p-5 rounded-2xl flex flex-col gap-4 overflow-hidden">
                        <div class="flex justify-between items-center shrink-0">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-300 font-mono">Mavjud Showroom rasmlari ({{ currentObjectContainer?.showroom_images?.length || 0 }} ta)</h3>
                        </div>

                        <!-- Grid -->
                        <div class="flex-1 overflow-y-auto pr-1">
                            <div v-if="currentObjectContainer?.showroom_images?.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                                <div v-for="img in currentObjectContainer.showroom_images" :key="img" class="aspect-video rounded-xl overflow-hidden border border-white/10 relative group">
                                    <img :src="'/storage/' + img" class="w-full h-full object-cover" />
                                    <!-- Delete Button Overlay -->
                                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-all">
                                        <button @click="deleteShowroomImage(img)" class="w-10 h-10 rounded-full bg-red-600/80 hover:bg-red-600 text-white flex items-center justify-center border border-red-500/20 text-sm shadow-md transition-all">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- Empty State -->
                            <div v-else class="h-full flex flex-col items-center justify-center text-center p-8 text-gray-500">
                                <span class="text-4xl block mb-2">🖼️</span>
                                <span class="text-xs">Yuklangan rasmlar topilmadi.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </AdminLayout>
    </div>
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

<style>
dialog.facility-modal-override .theme-modal {
    background-color: #ffffff !important;
    color: #0f172a !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08) !important;
}

dialog.facility-modal-override .theme-modal h1,
dialog.facility-modal-override .theme-modal h2,
dialog.facility-modal-override .theme-modal h3,
dialog.facility-modal-override .theme-modal h4,
dialog.facility-modal-override .theme-modal p,
dialog.facility-modal-override .theme-modal span,
dialog.facility-modal-override .theme-modal label,
dialog.facility-modal-override .theme-modal th,
dialog.facility-modal-override .theme-modal td {
    color: #0f172a !important;
}

dialog.facility-modal-override .theme-modal label {
    color: #475569 !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    letter-spacing: 0.05em !important;
}

dialog.facility-modal-override .theme-modal input,
dialog.facility-modal-override .theme-modal select,
dialog.facility-modal-override .theme-modal textarea {
    background-color: #f8fafc !important;
    color: #0f172a !important;
    border-color: #e2e8f0 !important;
    border-radius: 0.75rem !important; /* rounded-xl */
    font-size: 0.75rem !important;
    transition: all 0.2s ease-in-out !important;
}

dialog.facility-modal-override .theme-modal input:hover,
dialog.facility-modal-override .theme-modal select:hover,
dialog.facility-modal-override .theme-modal textarea:hover {
    border-color: #cbd5e1 !important; /* slate-300 */
}

dialog.facility-modal-override .theme-modal input:focus,
dialog.facility-modal-override .theme-modal select:focus,
dialog.facility-modal-override .theme-modal textarea:focus {
    background-color: #ffffff !important;
    border-color: #f97316 !important; /* orange-500 */
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15) !important;
    outline: none !important;
}

dialog.facility-modal-override .theme-modal select option {
    background-color: #ffffff !important;
    color: #0f172a !important;
}

dialog.facility-modal-override .theme-modal .bg-gray-50,
dialog.facility-modal-override .theme-modal .bg-slate-50 {
    background-color: #f8fafc !important;
    border-color: #e2e8f0 !important;
}

dialog.facility-modal-override .theme-modal .btn-cancel {
    background-color: #ffffff !important;
    border-color: #e2e8f0 !important;
    color: #475569 !important;
    transition: all 0.2s ease-in-out !important;
}

dialog.facility-modal-override .theme-modal .btn-cancel:hover {
    background-color: #f8fafc !important;
    border-color: #cbd5e1 !important;
    color: #1e293b !important;
}

dialog.facility-modal-override .theme-modal .btn-save {
    background-color: #f97316 !important; /* orange-500 */
    color: #ffffff !important;
    border: none !important;
    transition: all 0.2s ease-in-out !important;
}

dialog.facility-modal-override .theme-modal .btn-save:hover {
    background-color: #ea580c !important; /* orange-600 */
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2) !important;
}
</style>

