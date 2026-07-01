<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    buildings: {
        type: Array,
        default: () => []
    }
});

const BUILDINGS = computed(() => props.buildings.map(b => b.name) || ['Sherdon TJM', 'Yangi Zamon', 'Mega Tower']);
const UNITS = ['kub', 'mt', 'dona', 'kv.m'];
const BLOCKS = ['1', '2', '3', '4', '5'];
const FLOORS = ['-2', '-1', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];

const rows = ref([]);
const editingId = ref(null);
const draggedIdx = ref(null);

onMounted(() => {
    const localData = localStorage.getItem('qurilish_ish_haqi');
    if (localData) {
        try {
            rows.value = JSON.parse(localData);
        } catch (e) {
            console.error(e);
        }
    }
});

watch(rows, (newVal) => {
    localStorage.setItem('qurilish_ish_haqi', JSON.stringify(newVal));
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

const formatMoney = (val) => {
    return new Intl.NumberFormat('uz-UZ').format(val || 0) + " so'm";
};

const addRow = () => {
    const newRow = {
        id: Date.now().toString(),
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
    rows.value.push(newRow);
    editingId.value = newRow.id;
};

const updateRow = (id, field, value) => {
    const rowIndex = rows.value.findIndex(r => r.id === id);
    if (rowIndex > -1) {
        const row = rows.value[rowIndex];
        row[field] = value;
        
        row.measure1 = parseFloat(row.measure1) || 0;
        row.measure2 = parseFloat(row.measure2) || 0;
        row.quantity = parseFloat(row.quantity) || 0;
        row.price = parseFloat(row.price) || 0;
        row.paymentAmount = parseFloat(row.paymentAmount) || 0;

        row.totalMeasure = row.measure1 + row.measure2;
        row.total = row.totalMeasure * row.quantity;
        row.calculatedSum = row.total * row.price;
        row.balance = row.calculatedSum - row.paymentAmount;
    }
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
                <h1 class="text-2xl font-bold text-gray-800 mb-6">QURILISH ISH HAQI</h1>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col">
                        <span class="text-sm text-gray-500 font-medium">Umumiy xizmatlar soni</span>
                        <span class="text-2xl font-bold text-gray-800 mt-1">{{ stats.totalServices }} ta</span>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col">
                        <span class="text-sm text-gray-500 font-medium">Jami hisoblangan summa</span>
                        <span class="text-2xl font-bold text-blue-600 mt-1">{{ formatMoney(stats.totalCalculatedSum) }}</span>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col">
                        <span class="text-sm text-gray-500 font-medium">Jami to'langan summa</span>
                        <span class="text-2xl font-bold text-green-600 mt-1">{{ formatMoney(stats.totalPaid) }}</span>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col">
                        <span class="text-sm text-gray-500 font-medium">Qoldiq summa</span>
                        <span class="text-2xl font-bold text-red-600 mt-1">{{ formatMoney(stats.totalBalance) }}</span>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-700">Xizmatlar ro'yxati</h2>
                    <button
                        @click="addRow"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium flex items-center shadow-sm"
                    >
                        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Yangi Xizmat
                    </button>
                </div>

                <!-- Table Container -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 w-10"></th>
                                <th class="px-3 py-3">№</th>
                                <th class="px-3 py-3 min-w-[200px]">Xizmat nomi</th>
                                <th class="px-3 py-3 min-w-[150px]">Qurilish obekti</th>
                                <th class="px-3 py-3">Blok</th>
                                <th class="px-3 py-3">Qavat</th>
                                <th class="px-3 py-3">O'lchov</th>
                                <th class="px-3 py-3 w-20">O'lchami 1</th>
                                <th class="px-3 py-3 w-20">O'lchami 2</th>
                                <th class="px-3 py-3 w-24">Jami o'lchami</th>
                                <th class="px-3 py-3 w-20">Dona</th>
                                <th class="px-3 py-3 w-24">Jami</th>
                                <th class="px-3 py-3 min-w-[120px]">Narxi (UZS)</th>
                                <th class="px-3 py-3 min-w-[150px]">Hisoblangan</th>
                                <th class="px-3 py-3 min-w-[130px]">To'lov sanasi</th>
                                <th class="px-3 py-3 min-w-[150px]">To'lov so'mmasi</th>
                                <th class="px-3 py-3 min-w-[150px]">Qoldiq</th>
                                <th class="px-3 py-3 min-w-[100px] text-center">Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td colspan="18" class="px-6 py-10 text-center text-gray-400">
                                    Hozircha xizmatlar mavjud emas. Yangi xizmat qo'shish uchun tugmani bosing.
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
                                <td class="px-3 py-3 text-gray-400 cursor-move">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"></path></svg>
                                </td>
                                <td class="px-3 py-3 font-medium text-gray-900">{{ index + 1 }}</td>
                                
                                <td class="px-3 py-3">
                                    <input v-if="editingId === row.id" type="text" v-model="row.name" @input="updateRow(row.id, 'name', $event.target.value)" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border" placeholder="Masalan: Beton" />
                                    <span v-else :class="!row.name ? 'text-red-400 text-xs' : 'text-gray-700 font-medium'">{{ row.name || 'Kiritilmagan' }}</span>
                                </td>

                                <td class="px-3 py-3">
                                    <select v-if="editingId === row.id" v-model="row.building" @change="updateRow(row.id, 'building', $event.target.value)" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border">
                                        <option value="">Tanlang</option>
                                        <option v-for="b in BUILDINGS" :key="b" :value="b">{{ b }}</option>
                                    </select>
                                    <span v-else :class="!row.building ? 'text-red-400 text-xs' : ''">{{ row.building || 'Tanlanmagan' }}</span>
                                </td>

                                <td class="px-3 py-3">
                                    <select v-if="editingId === row.id" v-model="row.block" @change="updateRow(row.id, 'block', $event.target.value)" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border">
                                        <option value="">-</option>
                                        <option v-for="b in BLOCKS" :key="b" :value="b">{{ b }}</option>
                                    </select>
                                    <span v-else>{{ row.block || '-' }}</span>
                                </td>

                                <td class="px-3 py-3">
                                    <select v-if="editingId === row.id" v-model="row.floor" @change="updateRow(row.id, 'floor', $event.target.value)" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border">
                                        <option value="">-</option>
                                        <option v-for="b in FLOORS" :key="b" :value="b">{{ b }}</option>
                                    </select>
                                    <span v-else>{{ row.floor || '-' }}</span>
                                </td>

                                <td class="px-3 py-3">
                                    <select v-if="editingId === row.id" v-model="row.unit" @change="updateRow(row.id, 'unit', $event.target.value)" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border">
                                        <option value="">-</option>
                                        <option v-for="b in UNITS" :key="b" :value="b">{{ b }}</option>
                                    </select>
                                    <span v-else :class="!row.unit ? 'text-red-400 text-xs' : ''">{{ row.unit || 'Tanlanmagan' }}</span>
                                </td>

                                <td class="px-3 py-3">
                                    <input v-if="editingId === row.id" type="number" min="0" v-model.number="row.measure1" @input="updateRow(row.id, 'measure1', $event.target.value)" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border text-right" />
                                    <span v-else>{{ row.measure1 }}</span>
                                </td>

                                <td class="px-3 py-3">
                                    <input v-if="editingId === row.id" type="number" min="0" v-model.number="row.measure2" @input="updateRow(row.id, 'measure2', $event.target.value)" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border text-right" />
                                    <span v-else>{{ row.measure2 }}</span>
                                </td>

                                <td class="px-3 py-3 font-semibold text-gray-800 bg-gray-50/50">{{ row.totalMeasure }}</td>

                                <td class="px-3 py-3">
                                    <input v-if="editingId === row.id" type="number" min="0" v-model.number="row.quantity" @input="updateRow(row.id, 'quantity', $event.target.value)" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border text-right" />
                                    <span v-else>{{ row.quantity }}</span>
                                </td>

                                <td class="px-3 py-3 font-bold text-gray-900 bg-gray-50/50">{{ row.total }}</td>

                                <td class="px-3 py-3">
                                    <input v-if="editingId === row.id" type="number" min="0" v-model.number="row.price" @input="updateRow(row.id, 'price', $event.target.value)" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border text-right" />
                                    <span v-else class="text-gray-900 font-medium">{{ new Intl.NumberFormat('uz-UZ').format(row.price) }}</span>
                                </td>

                                <td class="px-3 py-3 font-bold text-blue-600 bg-blue-50/30">
                                    {{ formatMoney(row.calculatedSum) }}
                                </td>

                                <td class="px-3 py-3">
                                    <input v-if="editingId === row.id" type="date" v-model="row.paymentDate" @input="updateRow(row.id, 'paymentDate', $event.target.value)" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border" />
                                    <span v-else>{{ row.paymentDate || '-' }}</span>
                                </td>

                                <td class="px-3 py-3">
                                    <input v-if="editingId === row.id" type="number" min="0" v-model.number="row.paymentAmount" @input="updateRow(row.id, 'paymentAmount', $event.target.value)" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1 border text-right" />
                                    <span v-else class="text-green-600 font-medium">{{ new Intl.NumberFormat('uz-UZ').format(row.paymentAmount) }}</span>
                                </td>

                                <td class="px-3 py-3 font-bold text-red-600 bg-red-50/30">
                                    {{ formatMoney(row.balance) }}
                                </td>

                                <td class="px-3 py-3 text-center space-x-2">
                                    <button v-if="editingId === row.id" @click="editingId = null" class="text-green-600 hover:text-green-800 bg-green-50 p-1.5 rounded-md transition" title="Saqlash">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </button>
                                    <button v-else @click="editingId = row.id" class="text-indigo-600 hover:text-indigo-800 bg-indigo-50 p-1.5 rounded-md transition" title="Tahrirlash">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                    <button @click="deleteRow(row.id)" class="text-red-600 hover:text-red-800 bg-red-50 p-1.5 rounded-md transition" title="O'chirish">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
