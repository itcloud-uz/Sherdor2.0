<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    machineries: {
        type: Array,
        default: () => []
    }
});

const selectedMachinery = ref(null);

const machineriesData = ref({}); // { machineryId: [rows] }
const rows = ref([]); // current selected rows
const draggedIdx = ref(null);

// Modal state
const isWorkModalOpen = ref(false);
const editingWorkId = ref(null);
const defaultWorkForm = {
    id: null,
    date: '',
    serviceName: '',
    quantity: 1,
    price: 0,
    totalPrice: 0,
    paid: 0,
    fuel: 0,
    balance: 0,
    comment: ''
};
const workForm = ref({ ...defaultWorkForm });

onMounted(() => {
    const localData = localStorage.getItem('texnikalar_ishlar');
    if (localData) {
        try {
            machineriesData.value = JSON.parse(localData);
        } catch (e) {
            console.error(e);
        }
    }
});

// Update current rows when a machinery is selected
watch(selectedMachinery, (newMachinery) => {
    if (newMachinery) {
        if (!machineriesData.value[newMachinery.id]) {
            machineriesData.value[newMachinery.id] = [];
        }
        rows.value = machineriesData.value[newMachinery.id];
    } else {
        rows.value = [];
    }
});

// Watch rows to update machineriesData and LocalStorage
watch(rows, (newVal) => {
    if (selectedMachinery.value) {
        machineriesData.value[selectedMachinery.value.id] = newVal;
        localStorage.setItem('texnikalar_ishlar', JSON.stringify(machineriesData.value));
    }
}, { deep: true });

// Auto-calculate form values
watch(workForm, (val) => {
    val.quantity = parseFloat(val.quantity) || 0;
    val.price = parseFloat(val.price) || 0;
    val.paid = parseFloat(val.paid) || 0;
    val.fuel = parseFloat(val.fuel) || 0;

    val.totalPrice = val.quantity * val.price;
    // Balance calculation: Jami narxi - To'landi - Salarka = Qoldiq
    val.balance = val.totalPrice - val.paid - val.fuel;
}, { deep: true });

const stats = computed(() => {
    return rows.value.reduce(
        (acc, row) => {
            acc.totalPrice += row.totalPrice || 0;
            acc.totalPaid += row.paid || 0;
            acc.totalFuel += row.fuel || 0;
            acc.totalBalance += row.balance || 0;
            return acc;
        },
        { totalPrice: 0, totalPaid: 0, totalFuel: 0, totalBalance: 0 }
    );
});

const formatMoney = (val) => {
    return new Intl.NumberFormat('uz-UZ').format(val || 0);
};

const openAddWorkModal = () => {
    if (!selectedMachinery.value) return;
    const today = new Date().toISOString().slice(0, 10);
    workForm.value = { ...defaultWorkForm, id: Date.now().toString(), date: today };
    editingWorkId.value = null;
    isWorkModalOpen.value = true;
};

const openEditWorkModal = (row) => {
    workForm.value = { ...row };
    editingWorkId.value = row.id;
    isWorkModalOpen.value = true;
};

const saveWork = () => {
    if (editingWorkId.value) {
        const index = rows.value.findIndex(r => r.id === editingWorkId.value);
        if (index > -1) {
            rows.value[index] = { ...workForm.value };
        }
    } else {
        rows.value.push({ ...workForm.value });
    }
    isWorkModalOpen.value = false;
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
        <Head title="Texnikalar" />

        <div class="p-6 bg-gray-50 min-h-screen">
            <div class="max-w-screen-2xl mx-auto">
                
                <!-- Main Header -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-800">TEXNIKALAR HISOBI</h1>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    
                    <!-- Left Sidebar: List of Machineries -->
                    <div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                        <div class="flex justify-between items-center mb-4 border-b pb-2">
                            <h2 class="text-md font-semibold text-gray-800">Texnikalar ro'yxati</h2>
                        </div>

                        <div v-if="props.machineries.length === 0" class="text-gray-500 text-sm text-center py-4">
                            Texnikalar topilmadi.
                        </div>
                        <div class="space-y-2 max-h-[70vh] overflow-y-auto pr-1">
                            <button 
                                v-for="machinery in props.machineries" 
                                :key="machinery.id"
                                @click="selectedMachinery = machinery"
                                class="w-full text-left px-4 py-3 rounded-lg border transition-colors flex justify-between items-center"
                                :class="selectedMachinery?.id === machinery.id ? 'bg-orange-50 border-orange-200 text-orange-700' : 'bg-white border-gray-200 hover:bg-gray-50 text-gray-700'"
                            >
                                <div>
                                    <span class="font-medium truncate block">{{ machinery.name }}</span>
                                    <span class="text-xs text-gray-500">{{ machinery.license_plate }}</span>
                                </div>
                                <span class="text-[10px] uppercase font-semibold px-2 py-1 bg-white rounded-full border shadow-sm flex-shrink-0 text-gray-500">{{ machinery.machinery_type || 'Texnika' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Right Panel: Machinery Details & Work Log -->
                    <div class="lg:col-span-3">
                        <div v-if="!selectedMachinery" class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 flex flex-col items-center justify-center h-full text-center text-gray-500">
                            <svg class="w-16 h-16 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                            <span>Chap tomondagi ro'yxatdan birorta texnikani tanlang.</span>
                        </div>

                        <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <!-- Machinery Header -->
                            <div class="px-6 py-4 border-b border-gray-200 bg-amber-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900">{{ selectedMachinery.name }} ({{ selectedMachinery.license_plate }})</h2>
                                    <p class="text-sm text-gray-700 mt-1">Egasi (Sohibkari): <span class="font-semibold">{{ selectedMachinery.owner?.name || 'Kiritilmagan' }}</span></p>
                                </div>
                                <div class="flex gap-2">
                                    <div class="bg-white rounded-lg p-2 border border-amber-200 shadow-sm text-center">
                                        <div class="text-[10px] text-gray-500 uppercase font-bold">Haydovchi</div>
                                        <div class="text-sm font-semibold text-gray-800">{{ selectedMachinery.driver_name || '-' }}</div>
                                    </div>
                                    <div class="bg-white rounded-lg p-2 border border-amber-200 shadow-sm text-center">
                                        <div class="text-[10px] text-gray-500 uppercase font-bold">To'lov Turi</div>
                                        <div class="text-sm font-semibold text-gray-800">{{ selectedMachinery.payment_type || '-' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6">
                                <!-- Action Bar -->
                                <div class="flex justify-end mb-4">
                                    <button
                                        @click="openAddWorkModal"
                                        class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-medium flex items-center shadow-sm text-sm"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        Bajarilgan Ish Qo'shish
                                    </button>
                                </div>

                                <!-- Table Container -->
                                <div class="overflow-x-auto border border-yellow-400">
                                    <table class="w-full text-sm text-left text-gray-900 border-collapse">
                                        <thead>
                                            <!-- Top Header -->
                                            <tr>
                                                <th rowspan="2" class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center w-8">№</th>
                                                <th rowspan="2" class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center">Sana</th>
                                                <th colspan="4" class="border border-gray-400 bg-gray-200 px-2 py-1 text-center font-bold">BAJARILGAN ISHLAR</th>
                                                <th colspan="2" class="border border-gray-400 bg-blue-200 px-2 py-1 text-center font-bold">To'landi</th>
                                                <th rowspan="2" class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center">Qoldiq</th>
                                                <th rowspan="2" class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center">Izox</th>
                                                <th rowspan="2" class="border border-gray-400 bg-gray-100 px-2 py-2 text-center w-16">Amallar</th>
                                            </tr>
                                            <!-- Sub Header -->
                                            <tr>
                                                <th class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center">Xizmat nomi</th>
                                                <th class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center">Soni (soat, reysi)</th>
                                                <th class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center">Narxi</th>
                                                <th class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center">Jami narxi</th>
                                                <th class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center">To'landi</th>
                                                <th class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center">Salarka olib berdik</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white">
                                            <tr v-if="rows.length === 0">
                                                <td colspan="11" class="border border-gray-300 px-6 py-8 text-center text-gray-500 text-xs">
                                                    Ushbu texnika uchun bajarilgan ishlar mavjud emas.
                                                </td>
                                            </tr>
                                            <tr 
                                                v-for="(row, index) in rows" 
                                                :key="row.id"
                                                class="hover:bg-yellow-50 transition-colors"
                                                :class="draggedIdx === index ? 'opacity-50 bg-gray-100' : ''"
                                                draggable="true"
                                                @dragstart="handleDragStart($event, index)"
                                                @dragover="handleDragOver($event, index)"
                                                @dragend="handleDragEnd"
                                            >
                                                <td class="border border-gray-400 px-2 py-1 text-center font-bold">{{ index + 1 }}</td>
                                                <td class="border border-gray-400 px-2 py-1 text-center whitespace-nowrap">{{ row.date }}</td>
                                                <td class="border border-gray-400 px-2 py-1">{{ row.serviceName }}</td>
                                                <td class="border border-gray-400 px-2 py-1 text-center">{{ row.quantity }}</td>
                                                <td class="border border-gray-400 px-2 py-1 text-right whitespace-nowrap">{{ formatMoney(row.price) }}</td>
                                                <td class="border border-gray-400 px-2 py-1 text-right font-medium whitespace-nowrap">{{ formatMoney(row.totalPrice) }}</td>
                                                
                                                <td class="border border-gray-400 px-2 py-1 text-right text-green-700 whitespace-nowrap">{{ row.paid ? formatMoney(row.paid) : '' }}</td>
                                                <td class="border border-gray-400 px-2 py-1 text-right text-blue-700 whitespace-nowrap">{{ row.fuel ? formatMoney(row.fuel) : '' }}</td>
                                                
                                                <td class="border border-gray-400 px-2 py-1 text-right font-bold whitespace-nowrap" :class="row.balance > 0 ? 'text-gray-900' : 'text-red-600'">
                                                    {{ row.balance < 0 ? '-' : '' }} {{ formatMoney(Math.abs(row.balance)) }}
                                                </td>
                                                <td class="border border-gray-400 px-2 py-1 text-xs text-gray-600 max-w-[150px] truncate" :title="row.comment">{{ row.comment }}</td>
                                                
                                                <td class="border border-gray-400 px-1 py-1 text-center space-x-1">
                                                    <button @click="openEditWorkModal(row)" class="text-blue-600 hover:text-blue-800 p-0.5 rounded transition" title="Tahrirlash">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                    </button>
                                                    <button @click="deleteRow(row.id)" class="text-red-600 hover:text-red-800 p-0.5 rounded transition" title="O'chirish">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <!-- Footer / Totals -->
                                        <tfoot>
                                            <tr class="bg-gray-50 font-bold text-gray-900">
                                                <td colspan="5" class="border border-gray-400 px-2 py-2 text-center uppercase tracking-wider">Jami</td>
                                                <td class="border border-gray-400 px-2 py-2 text-right whitespace-nowrap">{{ formatMoney(stats.totalPrice) }}</td>
                                                <td class="border border-gray-400 px-2 py-2 text-right text-green-700 whitespace-nowrap">{{ formatMoney(stats.totalPaid) }}</td>
                                                <td class="border border-gray-400 px-2 py-2 text-right text-blue-700 whitespace-nowrap">{{ formatMoney(stats.totalFuel) }}</td>
                                                <td class="border border-gray-400 px-2 py-2 text-right whitespace-nowrap" :class="stats.totalBalance > 0 ? 'text-gray-900' : 'text-red-600'">
                                                    {{ stats.totalBalance < 0 ? '-' : '' }} {{ formatMoney(Math.abs(stats.totalBalance)) }}
                                                </td>
                                                <td colspan="2" class="border border-gray-400 px-2 py-2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Add/Edit Work Modal -->
        <div v-if="isWorkModalOpen" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-[60] p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800">{{ editingWorkId ? 'Ishni tahrirlash' : 'Bajarilgan ish qo\'shish' }}</h3>
                    <button @click="isWorkModalOpen = false" class="text-gray-400 hover:text-gray-600 bg-white rounded-full p-1 border">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                        
                        <!-- 1. Bajarilgan Ish -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-700 border-b pb-1">Ish haqida</h4>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sana <span class="text-red-500">*</span></label>
                                <input v-model="workForm.date" type="date" class="w-full border-gray-300 rounded-lg text-sm" required />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Xizmat nomi <span class="text-red-500">*</span></label>
                                <input v-model="workForm.serviceName" type="text" class="w-full border-gray-300 rounded-lg text-sm" placeholder="Masalan: Shebenka qumli, Ankelbens" required />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Soni (soat, reys) <span class="text-red-500">*</span></label>
                                    <input v-model.number="workForm.quantity" type="number" min="0" class="w-full border-gray-300 rounded-lg text-sm" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Narxi (UZS) <span class="text-red-500">*</span></label>
                                    <input v-model.number="workForm.price" type="number" min="0" class="w-full border-gray-300 rounded-lg text-sm" />
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                                <span class="text-sm font-medium text-gray-700">Jami narxi:</span>
                                <span class="text-lg font-bold text-gray-900">{{ formatMoney(workForm.totalPrice) }} so'm</span>
                            </div>
                        </div>

                        <!-- 2. To'lov va Qoldiq -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-700 border-b pb-1">Moliya</h4>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-green-700 mb-1">To'landi (Avans/To'lov)</label>
                                    <input v-model.number="workForm.paid" type="number" min="0" class="w-full border-green-300 bg-green-50 rounded-lg text-sm text-green-900" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-blue-700 mb-1">Salarka olib berdik</label>
                                    <input v-model.number="workForm.fuel" type="number" min="0" class="w-full border-blue-300 bg-blue-50 rounded-lg text-sm text-blue-900" />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Izox</label>
                                <textarea v-model="workForm.comment" rows="2" class="w-full border-gray-300 rounded-lg text-sm" placeholder="Qo'shimcha ma'lumotlar..."></textarea>
                            </div>
                            
                            <div class="flex justify-end mt-4">
                                <div class="w-full bg-red-50 border border-red-200 px-4 py-3 rounded-lg flex justify-between items-center shadow-inner">
                                    <span class="text-sm font-medium text-red-600">Qoldiq:</span>
                                    <span class="text-xl font-bold" :class="workForm.balance > 0 ? 'text-gray-900' : 'text-red-700'">
                                        {{ workForm.balance < 0 ? '-' : '' }} {{ formatMoney(Math.abs(workForm.balance)) }} so'm
                                    </span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                    <button @click="isWorkModalOpen = false" class="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Bekor qilish
                    </button>
                    <button @click="saveWork" :disabled="!workForm.date || !workForm.serviceName" class="px-5 py-2 text-sm font-medium text-white bg-orange-500 border border-transparent rounded-lg hover:bg-orange-600 disabled:opacity-50">
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
