<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    buildings: {
        type: Array,
        default: () => []
    },
    brigades: {
        type: Array,
        default: () => []
    }
});

const uniqueBuildings = computed(() => {
    const unique = [];
    const map = new Map();
    for (const item of props.buildings) {
        if (!map.has(item.name)) {
            map.set(item.name, true);
            unique.push(item);
        }
    }
    return unique;
});

const BUILDINGS = computed(() => uniqueBuildings.value.map(b => b.name));

const currentBuilding = computed(() => {
    return uniqueBuildings.value.find(b => b.name === serviceForm.value.building) || null;
});

const BLOCKS = computed(() => {
    if (!currentBuilding.value || !currentBuilding.value.blocks_count) return ['1', '2', '3', '4', '5'];
    return Array.from({ length: currentBuilding.value.blocks_count }, (_, i) => String(i + 1));
});

const FLOORS = computed(() => {
    if (!currentBuilding.value || !currentBuilding.value.total_floors) return ['-2', '-1', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
    const total = currentBuilding.value.total_floors;
    const basements = currentBuilding.value.basement_floors || 0;
    
    let arr = [];
    for(let i = -basements; i <= total; i++) {
        arr.push(String(i));
    }
    return arr;
});

const UNITS = ['kub', 'mt', 'dona', 'kv.m', 'soat', 'reys'];

const selectedBrigade = ref(null);
const brigadeTab = ref('xizmatlar'); // 'ishchilar', 'xizmatlar'

const servicesData = ref({}); // { brigadeId: [rows] }
const rows = ref([]); // current selected rows
const draggedIdx = ref(null);

// Brigade Modal state
const isAddBrigadeModalOpen = ref(false);
const newBrigadeForm = ref({
    name: '',
    foreman_name: '',
    phone: ''
});
const isSubmitting = ref(false);

const submitBrigade = () => {
    isSubmitting.value = true;
    router.post(route('admin.hr.brigade.store'), newBrigadeForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isAddBrigadeModalOpen.value = false;
            newBrigadeForm.value = { name: '', foreman_name: '', phone: '' };
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};

// Service Modal state
const isServiceModalOpen = ref(false);
const editingServiceId = ref(null);
const defaultServiceForm = {
    id: null,
    name: '',
    building: '',
    block: '',
    floor: '',
    unit: '',
    measure1: 0,
    measure2: 0,
    totalMeasure: 0,
    quantity: 1,
    total: 0,
    price: 0,
    calculatedSum: 0,
    paymentDate: '',
    paymentAmount: 0,
    balance: 0,
};
const serviceForm = ref({ ...defaultServiceForm });

const formatMoney = (val) => {
    return new Intl.NumberFormat('uz-UZ').format(val || 0) + " so'm";
};

onMounted(() => {
    const localData = localStorage.getItem('qurilish_ish_haqi_xizmatlar');
    if (localData) {
        try {
            servicesData.value = JSON.parse(localData);
        } catch (e) {
            console.error(e);
        }
    }
});

// Update current rows when a brigade is selected
watch(selectedBrigade, (newBrigade) => {
    if (newBrigade) {
        if (!servicesData.value[newBrigade.id]) {
            servicesData.value[newBrigade.id] = [];
        }
        rows.value = servicesData.value[newBrigade.id];
    } else {
        rows.value = [];
    }
});

// Watch rows to update servicesData and LocalStorage
watch(rows, (newVal) => {
    if (selectedBrigade.value) {
        servicesData.value[selectedBrigade.value.id] = newVal;
        localStorage.setItem('qurilish_ish_haqi_xizmatlar', JSON.stringify(servicesData.value));
    }
}, { deep: true });

// Auto-calculate form values
watch(serviceForm, (val) => {
    val.measure1 = parseFloat(val.measure1) || 0;
    val.measure2 = parseFloat(val.measure2) || 0;
    val.quantity = parseFloat(val.quantity) || 0;
    val.price = parseFloat(val.price) || 0;
    val.paymentAmount = parseFloat(val.paymentAmount) || 0;

    val.totalMeasure = val.measure1 + val.measure2;
    val.total = val.totalMeasure * val.quantity;
    val.calculatedSum = val.total * val.price;
    val.balance = val.calculatedSum - val.paymentAmount;
}, { deep: true });

const stats = computed(() => {
    return rows.value.reduce(
        (acc, row) => {
            acc.totalServices += 1;
            acc.totalCalculatedSum += row.calculatedSum || 0;
            acc.totalPaid += row.paymentAmount || 0;
            acc.totalBalance += row.balance || 0;
            return acc;
        },
        { totalServices: 0, totalCalculatedSum: 0, totalPaid: 0, totalBalance: 0 }
    );
});


const openAddServiceModal = () => {
    if (!selectedBrigade.value) return;
    serviceForm.value = { ...defaultServiceForm, id: Date.now().toString() };
    editingServiceId.value = null;
    isServiceModalOpen.value = true;
};

const openEditServiceModal = (row) => {
    serviceForm.value = { ...row };
    editingServiceId.value = row.id;
    isServiceModalOpen.value = true;
};

const saveService = () => {
    if (editingServiceId.value) {
        const index = rows.value.findIndex(r => r.id === editingServiceId.value);
        if (index > -1) {
            rows.value[index] = { ...serviceForm.value };
        }
    } else {
        rows.value.push({ ...serviceForm.value });
    }
    isServiceModalOpen.value = false;
};

const deleteRow = (id) => {
    if (confirm("Rostdan ham o'chirasizmi?")) {
        rows.value = rows.value.filter(r => r.id !== id);
    }
};

const handleDragStart = (e, index) => {
    draggedIdx.value = index;
    e.dataTransfer.effectAllowed = 'move';
};

const handleDragOver = (e, index) => {
    e.preventDefault();
    if (draggedIdx.value === null || draggedIdx.value === index) return;
    
    const dragRow = rows.value[draggedIdx.value];
    rows.value.splice(draggedIdx.value, 1);
    rows.value.splice(index, 0, dragRow);
    draggedIdx.value = index;
};

const handleDragEnd = () => {
    draggedIdx.value = null;
};
</script>

<template>
    <AdminLayout>
        <Head title="Qurilish Ish Haqi" />

        <div class="p-6 bg-gray-50 min-h-screen">
            <div class="max-w-screen-2xl mx-auto">
                
                <!-- Main Header -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-800">QURILISH ISH HAQI</h1>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    
                    <!-- Left Sidebar: List of Brigades -->
                    <div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div class="flex justify-between items-center mb-4 border-b pb-2">
                            <h2 class="text-md font-semibold text-gray-800">Jamoalar ro'yxati</h2>
                            <button 
                                @click="isAddBrigadeModalOpen = true"
                                class="text-xs bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-2 py-1 rounded font-medium transition"
                            >
                                + Qo'shish
                            </button>
                        </div>

                        <div v-if="props.brigades.length === 0" class="text-gray-500 text-sm text-center py-4">
                            Jamoalar topilmadi.
                        </div>
                        <div class="space-y-2 max-h-[70vh] overflow-y-auto pr-1">
                            <button 
                                v-for="brigade in props.brigades" 
                                :key="brigade.id"
                                @click="selectedBrigade = brigade"
                                class="w-full text-left px-4 py-3 rounded-lg border transition-colors flex justify-between items-center"
                                :class="selectedBrigade?.id === brigade.id ? 'bg-indigo-50 border-indigo-200 text-indigo-700' : 'bg-white border-gray-200 hover:bg-gray-50 text-gray-700'"
                            >
                                <span class="font-medium truncate">{{ brigade.name }}</span>
                                <span class="text-xs px-2 py-1 bg-white rounded-full border shadow-sm flex-shrink-0">{{ brigade.workers?.length || 0 }} ishchi</span>
                            </button>
                        </div>
                    </div>

                    <!-- Right Panel: Brigade Details -->
                    <div class="lg:col-span-3">
                        <div v-if="!selectedBrigade" class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 flex flex-col items-center justify-center h-full text-center text-gray-500">
                            <svg class="w-16 h-16 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            <span>Chap tomondagi ro'yxatdan birorta jamoani tanlang.</span>
                        </div>

                        <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <!-- Brigade Header -->
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-800">{{ selectedBrigade.name }}</h2>
                                    <p class="text-sm text-gray-500 mt-1">Brigadir: <span class="font-semibold text-gray-700">{{ selectedBrigade.foreman_name }}</span></p>
                                </div>
                                <div class="flex bg-gray-100 rounded-lg p-1 border border-gray-200">
                                    <button 
                                        @click="brigadeTab = 'ishchilar'" 
                                        :class="brigadeTab === 'ishchilar' ? 'bg-white text-gray-800 shadow-sm font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                        class="px-4 py-1.5 rounded-md text-sm transition-all"
                                    >
                                        Ishchilar
                                    </button>
                                    <button 
                                        @click="brigadeTab = 'xizmatlar'" 
                                        :class="brigadeTab === 'xizmatlar' ? 'bg-white text-gray-800 shadow-sm font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                        class="px-4 py-1.5 rounded-md text-sm transition-all"
                                    >
                                        Xizmatlar jadvali
                                    </button>
                                </div>
                            </div>

                            <!-- ISHCHILAR TAB -->
                            <div v-if="brigadeTab === 'ishchilar'" class="p-6">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm text-left text-gray-500">
                                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3">№</th>
                                                <th class="px-4 py-3">F.I.SH.</th>
                                                <th class="px-4 py-3">Kasbi (Roli)</th>
                                                <th class="px-4 py-3">Telefon</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="!selectedBrigade.workers || selectedBrigade.workers.length === 0">
                                                <td colspan="4" class="px-6 py-10 text-center text-gray-400">Bu jamoada ishchilar yo'q. Ishchilarni Xodimlar bo'limidan qo'shishingiz mumkin.</td>
                                            </tr>
                                            <tr v-for="(worker, idx) in selectedBrigade.workers" :key="worker.id" class="border-b hover:bg-gray-50">
                                                <td class="px-4 py-3">{{ idx + 1 }}</td>
                                                <td class="px-4 py-3 font-medium text-gray-900">{{ worker.name }}</td>
                                                <td class="px-4 py-3">{{ worker.role }}</td>
                                                <td class="px-4 py-3">{{ worker.phone || '-' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- XIZMATLAR TAB -->
                            <div v-if="brigadeTab === 'xizmatlar'" class="p-6">
                                <!-- Statistics Cards -->
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100 flex flex-col">
                                        <span class="text-[11px] text-gray-500 uppercase tracking-wider font-semibold">Xizmatlar</span>
                                        <span class="text-xl font-bold text-gray-800 mt-1">{{ stats.totalServices }} ta</span>
                                    </div>
                                    <div class="bg-blue-50/50 p-3 rounded-lg border border-blue-100 flex flex-col">
                                        <span class="text-[11px] text-blue-500 uppercase tracking-wider font-semibold">Hisoblangan</span>
                                        <span class="text-xl font-bold text-blue-700 mt-1">{{ formatMoney(stats.totalCalculatedSum) }}</span>
                                    </div>
                                    <div class="bg-green-50/50 p-3 rounded-lg border border-green-100 flex flex-col">
                                        <span class="text-[11px] text-green-600 uppercase tracking-wider font-semibold">To'langan</span>
                                        <span class="text-xl font-bold text-green-700 mt-1">{{ formatMoney(stats.totalPaid) }}</span>
                                    </div>
                                    <div class="bg-red-50/50 p-3 rounded-lg border border-red-100 flex flex-col">
                                        <span class="text-[11px] text-red-500 uppercase tracking-wider font-semibold">Qoldiq</span>
                                        <span class="text-xl font-bold text-red-600 mt-1">{{ formatMoney(stats.totalBalance) }}</span>
                                    </div>
                                </div>

                                <!-- Action Bar -->
                                <div class="flex justify-end mb-4">
                                    <button
                                        @click="openAddServiceModal"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium flex items-center shadow-sm text-sm"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        Yangi Xizmat
                                    </button>
                                </div>

                                <!-- Clean Table Container -->
                                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                    <table class="w-full text-sm text-left text-gray-500">
                                        <thead class="text-[10px] text-gray-700 uppercase bg-gray-50">
                                            <tr>
                                                <th class="px-2 py-2 w-8"></th>
                                                <th class="px-2 py-2">№</th>
                                                <th class="px-2 py-2">Xizmat nomi</th>
                                                <th class="px-2 py-2">Obyekt/Blok/Qavat</th>
                                                <th class="px-2 py-2">O'lchov & Jami</th>
                                                <th class="px-2 py-2">Narx (UZS)</th>
                                                <th class="px-2 py-2">Hisoblangan</th>
                                                <th class="px-2 py-2">To'lov & Sana</th>
                                                <th class="px-2 py-2">Qoldiq</th>
                                                <th class="px-2 py-2 text-center">Amallar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="rows.length === 0">
                                                <td colspan="10" class="px-6 py-8 text-center text-gray-400 text-xs">
                                                    Ushbu jamoa uchun xizmatlar mavjud emas.
                                                </td>
                                            </tr>
                                            <tr 
                                                v-for="(row, index) in rows" 
                                                :key="row.id"
                                                class="border-b hover:bg-gray-50 transition-colors"
                                                :class="draggedIdx === index ? 'opacity-50 bg-gray-100' : ''"
                                                draggable="true"
                                                @dragstart="handleDragStart($event, index)"
                                                @dragover="handleDragOver($event, index)"
                                                @dragend="handleDragEnd"
                                            >
                                                <td class="px-2 py-2 text-gray-400 cursor-move">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"></path></svg>
                                                </td>
                                                <td class="px-2 py-3 font-medium text-gray-900 text-xs">{{ index + 1 }}</td>
                                                
                                                <td class="px-2 py-3 font-medium text-gray-800 text-xs">{{ row.name || '-' }}</td>

                                                <td class="px-2 py-3 text-xs">
                                                    <div class="flex flex-col">
                                                        <span class="text-gray-800">{{ row.building || '-' }}</span>
                                                        <span class="text-[10px] text-gray-400">B: {{ row.block || '-' }}, Q: {{ row.floor || '-' }}</span>
                                                    </div>
                                                </td>

                                                <td class="px-2 py-3 text-xs">
                                                    <div class="flex flex-col">
                                                        <span class="font-semibold text-gray-700">{{ row.totalMeasure }} {{ row.unit }} x {{ row.quantity }}</span>
                                                        <span class="text-[10px] text-gray-400">Jami dona: {{ row.total }}</span>
                                                    </div>
                                                </td>

                                                <td class="px-2 py-3 text-gray-900 font-medium text-xs">{{ new Intl.NumberFormat('uz-UZ').format(row.price) }}</td>

                                                <td class="px-2 py-3 font-bold text-blue-600 bg-blue-50/30 text-xs rounded">
                                                    {{ new Intl.NumberFormat('uz-UZ').format(row.calculatedSum) }}
                                                </td>

                                                <td class="px-2 py-3 text-xs">
                                                    <div class="flex flex-col">
                                                        <span class="text-green-600 font-medium">{{ new Intl.NumberFormat('uz-UZ').format(row.paymentAmount) }}</span>
                                                        <span class="text-[10px] text-gray-400">{{ row.paymentDate || '-' }}</span>
                                                    </div>
                                                </td>

                                                <td class="px-2 py-3 font-bold text-red-600 bg-red-50/30 text-xs rounded">
                                                    {{ new Intl.NumberFormat('uz-UZ').format(row.balance) }}
                                                </td>

                                                <td class="px-2 py-3 text-center space-x-2">
                                                    <button @click="openEditServiceModal(row)" class="text-indigo-600 hover:text-indigo-800 bg-indigo-50 p-1.5 rounded transition" title="Tahrirlash">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                    </button>
                                                    <button @click="deleteRow(row.id)" class="text-red-600 hover:text-red-800 bg-red-50 p-1.5 rounded transition" title="O'chirish">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Add Brigade Modal -->
        <div v-if="isAddBrigadeModalOpen" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-[60] p-4">
            <div class="bg-white rounded-xl shadow-lg max-w-md w-full overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800">Yangi Jamoa qo'shish</h3>
                    <button @click="isAddBrigadeModalOpen = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jamoa nomi *</label>
                            <input v-model="newBrigadeForm.name" type="text" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="Masalan: Elbek jamoasi" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Brigadir ism-sharifi *</label>
                            <input v-model="newBrigadeForm.foreman_name" type="text" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="Masalan: Elbek Tuychiyev" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telefon raqami</label>
                            <input v-model="newBrigadeForm.phone" type="text" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="+998 90 123 45 67">
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                    <button @click="isAddBrigadeModalOpen = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Bekor qilish
                    </button>
                    <button @click="submitBrigade" :disabled="isSubmitting || !newBrigadeForm.name || !newBrigadeForm.foreman_name" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span v-if="isSubmitting">Saqlanmoqda...</span>
                        <span v-else>Saqlash</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Service Form Modal -->
        <div v-if="isServiceModalOpen" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-[60] p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800">{{ editingServiceId ? 'Xizmatni tahrirlash' : 'Yangi xizmat kiritish' }}</h3>
                    <button @click="isServiceModalOpen = false" class="text-gray-400 hover:text-gray-600 bg-white rounded-full p-1 border">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                        
                        <!-- 1. Asosiy ma'lumotlar -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-700 border-b pb-1">Asosiy ma'lumotlar</h4>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Xizmat nomi <span class="text-red-500">*</span></label>
                                <input v-model="serviceForm.name" type="text" class="w-full border-gray-300 rounded-lg text-sm" placeholder="Masalan: Beton katlavat 2 blok" />
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Obyekt</label>
                                    <select v-model="serviceForm.building" class="w-full border-gray-300 rounded-lg text-sm">
                                        <option value="">Tanlang</option>
                                        <option v-for="b in BUILDINGS" :key="b" :value="b">{{ b }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Blok</label>
                                    <select v-model="serviceForm.block" class="w-full border-gray-300 rounded-lg text-sm">
                                        <option value="">-</option>
                                        <option v-for="b in BLOCKS" :key="b" :value="b">{{ b }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Qavat</label>
                                    <select v-model="serviceForm.floor" class="w-full border-gray-300 rounded-lg text-sm">
                                        <option value="">-</option>
                                        <option v-for="b in FLOORS" :key="b" :value="b">{{ b }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- 2. O'lchovlar -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-700 border-b pb-1">O'lchov & Miqdor</h4>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div class="col-span-2 sm:col-span-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">O'lchov birligi</label>
                                    <select v-model="serviceForm.unit" class="w-full border-gray-300 rounded-lg text-sm">
                                        <option value="">-</option>
                                        <option v-for="b in UNITS" :key="b" :value="b">{{ b }}</option>
                                    </select>
                                </div>
                                <div class="col-span-2 sm:col-span-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Shundan necha dona</label>
                                    <input v-model.number="serviceForm.quantity" type="number" min="0" class="w-full border-gray-300 rounded-lg text-sm bg-blue-50" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">O'lcham 1</label>
                                    <input v-model.number="serviceForm.measure1" type="number" min="0" class="w-full border-gray-300 rounded-lg text-sm" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">O'lcham 2</label>
                                    <input v-model.number="serviceForm.measure2" type="number" min="0" class="w-full border-gray-300 rounded-lg text-sm" />
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center bg-gray-100 p-3 rounded-lg border">
                                <span class="text-sm font-medium text-gray-600">Jami O'lcham & Dona:</span>
                                <span class="text-lg font-bold text-gray-800">{{ serviceForm.totalMeasure }} x {{ serviceForm.quantity }} = {{ serviceForm.total }}</span>
                            </div>
                        </div>

                        <!-- 3. Moliya & Hisob-kitob -->
                        <div class="space-y-4 md:col-span-2">
                            <h4 class="font-semibold text-gray-700 border-b pb-1">Moliya (Hisob-kitoblar)</h4>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Birlik Narxi (UZS)</label>
                                    <input v-model.number="serviceForm.price" type="number" min="0" class="w-full border-gray-300 rounded-lg text-sm" />
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-blue-700 mb-1">Hisoblangan Summa</label>
                                    <div class="w-full bg-blue-50 border border-blue-200 rounded-lg p-2 text-sm font-bold text-blue-800 flex items-center justify-end h-[38px]">
                                        {{ new Intl.NumberFormat('uz-UZ').format(serviceForm.calculatedSum) }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">To'lov sanasi</label>
                                    <input v-model="serviceForm.paymentDate" type="date" class="w-full border-gray-300 rounded-lg text-sm" />
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-green-700 mb-1">To'langan summa</label>
                                    <input v-model.number="serviceForm.paymentAmount" type="number" min="0" class="w-full border-green-300 bg-green-50 rounded-lg text-sm text-green-900 font-semibold" />
                                </div>
                            </div>

                            <div class="flex justify-end mt-2">
                                <div class="bg-red-50 border border-red-200 px-6 py-3 rounded-lg flex gap-4 items-center shadow-inner">
                                    <span class="text-sm font-medium text-red-600">Qoldiq:</span>
                                    <span class="text-2xl font-bold text-red-700">{{ new Intl.NumberFormat('uz-UZ').format(serviceForm.balance) }} so'm</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                    <button @click="isServiceModalOpen = false" class="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Bekor qilish
                    </button>
                    <button @click="saveService" :disabled="!serviceForm.name" class="px-5 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        Saqlash
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1; 
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #c1c1c1; 
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8; 
}
</style>
