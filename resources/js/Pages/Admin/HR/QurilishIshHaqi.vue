<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import * as XLSX from 'xlsx-js-style';

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
    const count = Math.max(4, currentBuilding.value?.blocks_count || 1);
    return Array.from({ length: count }, (_, i) => String(i + 1));
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
const editingBrigadeId = ref(null);
const newBrigadeForm = ref({
    name: '',
    foreman_name: '',
    phone: ''
});
const isSubmitting = ref(false);

const openAddBrigadeModal = () => {
    editingBrigadeId.value = null;
    newBrigadeForm.value = { name: '', foreman_name: '', phone: '' };
    isAddBrigadeModalOpen.value = true;
};

const editBrigade = (brigade) => {
    editingBrigadeId.value = brigade.id;
    newBrigadeForm.value = { 
        name: brigade.name, 
        foreman_name: brigade.foreman_name, 
        phone: brigade.phone 
    };
    isAddBrigadeModalOpen.value = true;
};

const deleteBrigade = (id) => {
    if (confirm("Jamoani rostdan ham o'chirasizmi?")) {
        router.delete(route('admin.hr.brigade.destroy', id), {
            preserveScroll: true
        });
    }
};

const submitBrigade = () => {
    isSubmitting.value = true;
    const url = editingBrigadeId.value 
        ? route('admin.hr.brigade.update', editingBrigadeId.value) 
        : route('admin.hr.brigade.store');
    
    const method = editingBrigadeId.value ? 'put' : 'post';

    router[method](url, newBrigadeForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isAddBrigadeModalOpen.value = false;
            newBrigadeForm.value = { name: '', foreman_name: '', phone: '' };
            editingBrigadeId.value = null;
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
    const m1 = parseFloat(val.measure1) || 0;
    const m2 = parseFloat(val.measure2) || 0;
    const q = parseFloat(val.quantity) || 0;
    const p = parseFloat(val.price) || 0;
    const pay = parseFloat(val.paymentAmount) || 0;

    // G * H = I
    val.totalMeasure = Number((m1 * m2).toFixed(4));

    // If I > 0, K = I * J (J defaults to 1). Otherwise K = J.
    const activeQty = q > 0 ? q : 1;
    if (val.totalMeasure > 0) {
        val.total = Number((val.totalMeasure * activeQty).toFixed(4));
    } else {
        val.total = Number(q.toFixed(4));
    }

    // K * L = M
    val.calculatedSum = Number((val.total * p).toFixed(2));

    // M - O = P
    val.balance = Number((val.calculatedSum - pay).toFixed(2));
}, { deep: true });

const stats = computed(() => {
    const acc = rows.value.reduce(
        (acc, row) => {
            acc.totalServices += 1;
            acc.totalCalculatedSum += row.calculatedSum || 0;
            acc.totalPaid += row.paymentAmount || 0;
            return acc;
        },
        { totalServices: 0, totalCalculatedSum: 0, totalPaid: 0, totalBalance: 0 }
    );

    let financePaid = 0;
    if (selectedBrigade.value && selectedBrigade.value.transactions) {
        selectedBrigade.value.transactions.forEach(txn => {
            if (txn.type === 'payment') {
                financePaid += parseFloat(txn.amount);
            } else if (txn.type === 'payment_received') {
                financePaid -= parseFloat(txn.amount);
            }
        });
    }

    acc.totalPaid += financePaid;
    acc.totalBalance = acc.totalCalculatedSum - acc.totalPaid;

    return acc;
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

const exportToPDF = () => {
    if (!selectedBrigade.value) return;
    if (rows.value.length === 0) {
        alert("Yuklab olish uchun xizmatlar mavjud emas!");
        return;
    }

    try {
        const doc = new jsPDF('landscape');
        doc.text(`${selectedBrigade.value.name} - Bajarilgan Xizmatlar Hisoboti`, 14, 15);
    
    const tableData = rows.value.map((row, index) => [
        index + 1,
        row.name || '-',
        row.building || '-',
        row.block || '-',
        row.floor || '-',
        row.unit || '-',
        row.measure1,
        row.measure2,
        row.totalMeasure,
        row.quantity,
        row.total,
        new Intl.NumberFormat('uz-UZ').format(row.price || 0),
        new Intl.NumberFormat('uz-UZ').format(row.calculatedSum || 0),
        row.paymentDate || '-',
        new Intl.NumberFormat('uz-UZ').format(row.paymentAmount || 0),
        new Intl.NumberFormat('uz-UZ').format(row.balance || 0)
    ]);

    autoTable(doc, {
        startY: 25,
        head: [['№', 'Xizmat', 'Obyekt', 'Blok', 'Qavat', 'Birlik', 'O\'lchami 1', 'O\'lchami 2', 'Jami O\'lchami', 'Dona', 'Jami', 'Narxi', 'Hisoblangan', 'Sana', 'To\'landi', 'Qoldiq']],
        body: tableData,
        theme: 'grid',
        styles: { fontSize: 7 },
        headStyles: { fillColor: [79, 70, 229], textColor: [255, 255, 255] }
    });

        doc.save(`${selectedBrigade.value.name.replace(/\s+/g, '_')}_Hisobot.pdf`);
    } catch (error) {
        console.error("PDF yaratishda xatolik:", error);
        alert("PDF yaratishda xatolik yuz berdi.");
    }
};

const exportToExcel = () => {
    if (!selectedBrigade.value) return;
    if (rows.value.length === 0) {
        alert("Yuklab olish uchun xizmatlar mavjud emas!");
        return;
    }
    
    try {
        const headers = ['№', 'Xizmat nomi', 'Qurilish obyekti', 'Blok', 'Qavat', 'O\'lchov birligi', 'O\'lchami 1', 'O\'lchami 2', 'Jami o\'lchami', 'Soni (dona)', 'Jami', 'Narxi', 'Hisoblangan so\'mmasi', 'To\'lov sanasi', 'To\'langan', 'Qoldiq'];
    
    const data = rows.value.map((row, index) => [
        index + 1,
        row.name || '-',
        row.building || '-',
        row.block || '-',
        row.floor || '-',
        row.unit || '-',
        row.measure1,
        row.measure2,
        row.totalMeasure,
        row.quantity,
        row.total,
        row.price || 0,
        row.calculatedSum || 0,
        row.paymentDate || '-',
        row.paymentAmount || 0,
        row.balance || 0
    ]);
    
    data.push([
        '', '', '', '', '', '', '', '', '', '', 'JAMI:', '', 
        stats.value.totalCalculatedSum, 
        '', 
        stats.value.totalPaid, 
        stats.value.totalBalance
    ]);
    
    const ws = XLSX.utils.aoa_to_sheet([headers, ...data]);
    
    const range = XLSX.utils.decode_range(ws['!ref']);
    for (let R = range.s.r; R <= range.e.r; ++R) {
        for (let C = range.s.c; C <= range.e.c; ++C) {
            const cellAddress = XLSX.utils.encode_cell({c: C, r: R});
            if (!ws[cellAddress]) continue;
            ws[cellAddress].s = {
                border: {
                    top: {style: 'thin', color: {rgb: "000000"}},
                    bottom: {style: 'thin', color: {rgb: "000000"}},
                    left: {style: 'thin', color: {rgb: "000000"}},
                    right: {style: 'thin', color: {rgb: "000000"}}
                },
                font: {
                    bold: R === 0 || R === range.e.r
                }
            };
        }
    }
    
    ws['!cols'] = [
        {wch: 5}, {wch: 25}, {wch: 20}, {wch: 8}, {wch: 8}, {wch: 15}, 
        {wch: 10}, {wch: 10}, {wch: 12}, {wch: 10}, {wch: 12}, 
        {wch: 15}, {wch: 18}, {wch: 12}, {wch: 15}, {wch: 15}
    ];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Xizmatlar");
        XLSX.writeFile(wb, `${selectedBrigade.value.name.replace(/\s+/g, '_')}_Hisobot.xlsx`);
    } catch (error) {
        console.error("Excel yaratishda xatolik:", error);
        alert("Excel yaratishda xatolik yuz berdi.");
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
                                @click="openAddBrigadeModal"
                                class="text-xs bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-2 py-1 rounded font-medium transition"
                            >
                                + Qo'shish
                            </button>
                        </div>

                        <div v-if="props.brigades.length === 0" class="text-gray-500 text-sm text-center py-4">
                            Jamoalar topilmadi.
                        </div>
                        <div class="space-y-2 max-h-[70vh] overflow-y-auto pr-1">
                            <div 
                                v-for="brigade in props.brigades" 
                                :key="brigade.id"
                                class="group relative w-full text-left px-4 py-3 rounded-lg border transition-colors"
                                :class="selectedBrigade?.id === brigade.id ? 'bg-indigo-50 border-indigo-200 text-indigo-700' : 'bg-white border-gray-200 hover:bg-gray-50 text-gray-700'"
                            >
                                <div class="cursor-pointer" @click="selectedBrigade = brigade">
                                    <span class="font-medium truncate block pr-12">{{ brigade.name }}</span>
                                    <span class="text-xs text-gray-500 flex justify-between mt-0.5">
                                        <span>{{ brigade.foreman_name }}</span>
                                        <span :class="brigade.balance >= 0 ? 'text-green-600' : 'text-red-600'" class="font-semibold">{{ formatMoney(brigade.balance) }}</span>
                                    </span>
                                </div>
                                <div class="absolute right-2 top-2 hidden group-hover:flex space-x-1">
                                    <button @click.stop="editBrigade(brigade)" class="p-1.5 text-blue-500 hover:bg-blue-100 rounded-md transition" title="Tahrirlash">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                    <button @click.stop="deleteBrigade(brigade.id)" class="p-1.5 text-red-500 hover:bg-red-100 rounded-md transition" title="O'chirish">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </div>
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
                                <div class="flex flex-col items-end">
                                    <span class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Moliya Qoldig'i</span>
                                    <span class="text-xl font-bold" :class="selectedBrigade.balance >= 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ formatMoney(selectedBrigade.balance) }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Tabs -->
                            <div class="border-b border-gray-200">
                                <nav class="flex -mb-px px-6 space-x-6" aria-label="Tabs">
                                    <button 
                                        @click="brigadeTab = 'xizmatlar'"
                                        :class="[brigadeTab === 'xizmatlar' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center']"
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                        Xizmatlar (Excel)
                                    </button>
                                    <button 
                                        @click="brigadeTab = 'tranzaksiyalar'"
                                        :class="[brigadeTab === 'tranzaksiyalar' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center']"
                                    >
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Moliya Tranzaksiyalari
                                    </button>
                                </nav>
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
                                <div class="flex justify-between items-center mb-4">
                                    <div class="flex gap-2">
                                        <button @click="exportToPDF" class="px-3 py-2 bg-red-50 text-red-600 border border-red-200 rounded-lg hover:bg-red-100 transition flex items-center text-sm font-medium shadow-sm">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            PDF Yuklab Olish
                                        </button>
                                        <button @click="exportToExcel" class="px-3 py-2 bg-green-50 text-green-700 border border-green-200 rounded-lg hover:bg-green-100 transition flex items-center text-sm font-medium shadow-sm">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            Excel Yuklab Olish
                                        </button>
                                    </div>
                                    <button
                                        @click="openAddServiceModal"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium flex items-center shadow-sm text-sm"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        Yangi Xizmat
                                    </button>
                                </div>

                                <!-- Beautiful Excel-Style Grid Table Container -->
                                <div class="overflow-x-auto border border-gray-300 rounded-xl shadow-sm bg-white">
                                    <table class="w-full text-xs text-left text-gray-600 border-collapse">
                                        <thead class="bg-gray-100/80 border-b border-gray-300 text-gray-700 font-bold uppercase text-[10px] tracking-wider select-none sticky top-0">
                                            <tr>
                                                <th class="border border-gray-200 px-2 py-3 w-8 text-center bg-gray-100"></th>
                                                <th class="border border-gray-200 px-3 py-3 w-12 text-center bg-gray-100">№</th>
                                                <th class="border border-gray-200 px-4 py-3 min-w-[200px]">Xizmat nomi</th>
                                                <th class="border border-gray-200 px-4 py-3 min-w-[120px]">Qurilish obyekti</th>
                                                <th class="border border-gray-200 px-2 py-3 w-16 text-center">Blok</th>
                                                <th class="border border-gray-200 px-2 py-3 w-16 text-center">Qavat</th>
                                                <th class="border border-gray-200 px-3 py-3 w-24 text-center">O'lchov birligi</th>
                                                <th class="border border-gray-200 px-3 py-3 w-20 text-right">O'lchami 1 (G)</th>
                                                <th class="border border-gray-200 px-3 py-3 w-20 text-right">O'lchami 2 (H)</th>
                                                <th class="border border-gray-200 px-3 py-3 w-24 text-right bg-blue-50/50">Jami o'lchami (I)</th>
                                                <th class="border border-gray-200 px-3 py-3 w-24 text-center">Shundan necha dona (J)</th>
                                                <th class="border border-gray-200 px-3 py-3 w-24 text-right bg-blue-50/50">Jami (K)</th>
                                                <th class="border border-gray-200 px-4 py-3 w-32 text-right">Narxi (L)</th>
                                                <th class="border border-gray-200 px-4 py-3 w-36 text-right bg-indigo-50/50 text-indigo-900 font-extrabold">Hisoblangan so'mmasi (M)</th>
                                                <th class="border border-gray-200 px-3 py-3 w-28 text-center">To'lov sanasi (N)</th>
                                                <th class="border border-gray-200 px-4 py-3 w-32 text-right text-green-700">To'lov so'mmasi (O)</th>
                                                <th class="border border-gray-200 px-4 py-3 w-32 text-right bg-red-50/30 text-red-700 font-bold">Qoldiq (P)</th>
                                                <th class="border border-gray-200 px-3 py-3 w-28 text-center bg-gray-100">Amallar</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-if="rows.length === 0">
                                                <td colspan="18" class="px-6 py-12 text-center text-gray-400 text-sm bg-gray-50/50">
                                                    Ushbu jamoa uchun xizmatlar mavjud emas. Excel jadvalini yaratish uchun "+ Yangi Xizmat" tugmasini bosing.
                                                </td>
                                            </tr>
                                            <tr 
                                                v-for="(row, index) in rows" 
                                                :key="row.id"
                                                class="hover:bg-indigo-50/20 transition-colors"
                                                :class="draggedIdx === index ? 'opacity-50 bg-gray-100' : ''"
                                                draggable="true"
                                                @dragstart="handleDragStart($event, index)"
                                                @dragover="handleDragOver($event, index)"
                                                @dragend="handleDragEnd"
                                            >
                                                <td class="border border-gray-200 px-2 py-2 text-center text-gray-400 cursor-move hover:text-indigo-600 bg-gray-50/50">
                                                    <svg class="w-3.5 h-3.5 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"></path></svg>
                                                </td>
                                                <td class="border border-gray-200 px-3 py-2.5 text-center font-semibold text-gray-500 bg-gray-50/50">{{ index + 1 }}</td>
                                                <td class="border border-gray-200 px-4 py-2.5 font-bold text-gray-800">{{ row.name || '-' }}</td>
                                                <td class="border border-gray-200 px-4 py-2.5 font-medium text-gray-600">{{ row.building || '-' }}</td>
                                                <td class="border border-gray-200 px-2 py-2.5 text-center font-semibold text-gray-700 bg-gray-50/30">{{ row.block || '-' }}</td>
                                                <td class="border border-gray-200 px-2 py-2.5 text-center font-semibold text-gray-700 bg-gray-50/30">{{ row.floor || '-' }}</td>
                                                <td class="border border-gray-200 px-3 py-2.5 text-center font-semibold text-gray-500">{{ row.unit || '-' }}</td>
                                                <td class="border border-gray-200 px-3 py-2.5 text-right font-medium text-gray-800">{{ row.measure1 }}</td>
                                                <td class="border border-gray-200 px-3 py-2.5 text-right font-medium text-gray-800">{{ row.measure2 }}</td>
                                                <td class="border border-gray-200 px-3 py-2.5 text-right font-bold text-indigo-700 bg-blue-50/20">{{ row.totalMeasure }}</td>
                                                <td class="border border-gray-200 px-3 py-2.5 text-center font-medium text-gray-800">{{ row.quantity }}</td>
                                                <td class="border border-gray-200 px-3 py-2.5 text-right font-bold text-indigo-700 bg-blue-50/20">{{ row.total }}</td>
                                                <td class="border border-gray-200 px-4 py-2.5 text-right font-bold text-gray-900">{{ new Intl.NumberFormat('uz-UZ').format(row.price || 0) }}</td>
                                                <td class="border border-gray-200 px-4 py-2.5 text-right font-black text-indigo-800 bg-indigo-50/30">{{ new Intl.NumberFormat('uz-UZ').format(row.calculatedSum || 0) }}</td>
                                                <td class="border border-gray-200 px-3 py-2.5 text-center font-semibold text-gray-500">{{ row.paymentDate || '-' }}</td>
                                                <td class="border border-gray-200 px-4 py-2.5 text-right font-bold text-green-700 bg-green-50/20">{{ new Intl.NumberFormat('uz-UZ').format(row.paymentAmount || 0) }}</td>
                                                <td class="border border-gray-200 px-4 py-2.5 text-right font-black text-red-700 bg-red-50/20">{{ new Intl.NumberFormat('uz-UZ').format(row.balance || 0) }}</td>
                                                <td class="border border-gray-200 px-3 py-2.5 text-center space-x-1.5 bg-gray-50/30">
                                                    <button @click="openEditServiceModal(row)" class="text-indigo-600 hover:text-indigo-800 hover:bg-indigo-100 p-1.5 rounded-lg border border-indigo-200 bg-white transition shadow-sm" title="Tahrirlash">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                    </button>
                                                    <button @click="deleteRow(row.id)" class="text-red-600 hover:text-red-800 hover:bg-red-100 p-1.5 rounded-lg border border-red-200 bg-white transition shadow-sm" title="O'chirish">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- TRANZAKSIYALAR TAB -->
                            <div v-else-if="brigadeTab === 'tranzaksiyalar'" class="p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-bold text-gray-800">Moliya Bo'limidan O'tkazmalar</h3>
                                </div>
                                <div v-if="!selectedBrigade.transactions || selectedBrigade.transactions.length === 0" class="text-center py-12 bg-gray-50 border border-gray-200 rounded-lg">
                                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <p class="text-gray-500">Moliya bo'limidan ushbu jamoaga hech qanday tranzaksiya amalga oshirilmagan.</p>
                                </div>
                                <div v-else class="overflow-x-auto border border-gray-200 rounded-lg">
                                    <table class="w-full text-sm text-left text-gray-600">
                                        <thead class="bg-gray-50 text-gray-700 font-semibold border-b">
                                            <tr>
                                                <th class="px-4 py-3">Sana</th>
                                                <th class="px-4 py-3">Turi</th>
                                                <th class="px-4 py-3">Izoh</th>
                                                <th class="px-4 py-3 text-right">Summa</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-for="txn in selectedBrigade.transactions" :key="txn.id" class="hover:bg-gray-50">
                                                <td class="px-4 py-3 whitespace-nowrap">{{ new Date(txn.date || txn.created_at).toLocaleDateString('uz-UZ') }}</td>
                                                <td class="px-4 py-3">
                                                    <span :class="txn.type === 'payment' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'" class="px-2 py-1 text-xs font-medium rounded-full">
                                                        {{ txn.type === 'payment' ? 'To\'lov (Chiqim)' : (txn.type === 'payment_received' ? 'Kirim' : txn.type) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">{{ txn.description || '-' }}</td>
                                                <td class="px-4 py-3 text-right font-bold" :class="txn.type === 'payment' ? 'text-red-600' : 'text-green-600'">
                                                    {{ txn.type === 'payment' ? '-' : '+' }}{{ formatMoney(txn.amount) }}
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
                    <h3 class="text-lg font-bold text-gray-800">{{ editingBrigadeId ? 'Jamoani tahrirlash' : 'Yangi Jamoa qo\'shish' }}</h3>
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
        <div v-if="isServiceModalOpen" class="fixed inset-0 bg-white z-[60] flex flex-col h-screen w-screen overflow-hidden">
            <div class="px-8 py-5 border-b border-gray-200 flex justify-between items-center bg-gray-50 shadow-sm">
                <h3 class="text-2xl font-bold text-gray-800">{{ editingServiceId ? 'Xizmatni tahrirlash' : 'Yangi xizmat kiritish' }}</h3>
                <button @click="isServiceModalOpen = false" class="text-gray-500 hover:text-red-600 bg-white rounded-full p-2 border hover:bg-red-50 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="p-8 overflow-y-auto custom-scrollbar flex-1 bg-gray-50/30">
                <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-10">
                    
                    <!-- 1. Asosiy ma'lumotlar -->
                    <div class="space-y-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                        <h4 class="text-lg font-bold text-indigo-900 border-b pb-3">Asosiy ma'lumotlar</h4>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Xizmat nomi <span class="text-red-500">*</span></label>
                            <input v-model="serviceForm.name" type="text" class="w-full border-gray-300 rounded-lg text-base p-2.5" placeholder="Masalan: Beton katlavat 2 blok" />
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Obyekt</label>
                                <select v-model="serviceForm.building" class="w-full border-gray-300 rounded-lg text-base p-2.5">
                                    <option value="">Tanlang</option>
                                    <option v-for="b in BUILDINGS" :key="b" :value="b">{{ b }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Blok</label>
                                <select v-model="serviceForm.block" class="w-full border-gray-300 rounded-lg text-base p-2.5">
                                    <option value="">-</option>
                                    <option v-for="b in BLOCKS" :key="b" :value="b">{{ b }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Qavat</label>
                                <select v-model="serviceForm.floor" class="w-full border-gray-300 rounded-lg text-base p-2.5">
                                    <option value="">-</option>
                                    <option v-for="b in FLOORS" :key="b" :value="b">{{ b }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 2. O'lchovlar -->
                    <div class="space-y-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                        <h4 class="text-lg font-bold text-indigo-900 border-b pb-3">O'lchov & Miqdor</h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">O'lchov birligi</label>
                                <select v-model="serviceForm.unit" class="w-full border-gray-300 rounded-lg text-base p-2.5">
                                    <option value="">-</option>
                                    <option v-for="b in UNITS" :key="b" :value="b">{{ b }}</option>
                                </select>
                            </div>
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Shundan necha dona (J)</label>
                                <input v-model.number="serviceForm.quantity" type="number" min="0" class="w-full border-gray-300 rounded-lg text-base p-2.5 bg-blue-50/50" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">O'lchami 1 (G)</label>
                                <input v-model.number="serviceForm.measure1" type="number" min="0" step="any" class="w-full border-gray-300 rounded-lg text-base p-2.5" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">O'lchami 2 (H)</label>
                                <input v-model.number="serviceForm.measure2" type="number" min="0" step="any" class="w-full border-gray-300 rounded-lg text-base p-2.5" />
                            </div>
                        </div>
                        
                        <div class="flex flex-col gap-2 bg-gray-50 p-4 rounded-xl border border-gray-200">
                            <div class="flex justify-between items-center text-sm font-medium text-gray-600">
                                <span>Jami o'lchami (I = G * H):</span>
                                <span class="font-bold text-gray-800">{{ serviceForm.totalMeasure }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm font-medium text-gray-600 border-t pt-2 mt-1">
                                <span>Jami (K = I * J):</span>
                                <span class="text-xl font-extrabold text-indigo-900">{{ serviceForm.total }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Moliya & Hisob-kitob -->
                    <div class="space-y-6 md:col-span-2 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                        <h4 class="text-lg font-bold text-indigo-900 border-b pb-3">Moliya & Hisob-kitob</h4>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Narxi (L)</label>
                                <input v-model.number="serviceForm.price" type="number" min="0" class="w-full border-gray-300 rounded-lg text-lg p-3 font-medium focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-indigo-900 mb-2">Hisoblangan so'mmasi (M = K * L)</label>
                                <div class="w-full bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-lg font-black text-indigo-800 flex items-center justify-end h-[52px] shadow-inner">
                                    {{ new Intl.NumberFormat('uz-UZ').format(serviceForm.calculatedSum) }} so'm
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-green-700 mb-2">To'lov so'mmasi (O)</label>
                                <input v-model.number="serviceForm.paymentAmount" type="number" min="0" class="w-full border-gray-300 rounded-lg text-lg p-3 font-medium focus:border-green-500 focus:ring-green-500" />
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">To'lov sanasi (N)</label>
                                <input v-model="serviceForm.paymentDate" type="date" class="w-full border-gray-300 rounded-lg text-lg p-3 font-medium focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                        </div>

                        <div class="flex justify-between items-center bg-red-50 p-4 rounded-xl border border-red-100 mt-4">
                            <span class="text-base font-semibold text-red-700">Qoldiq (P = M - O):</span>
                            <span class="text-2xl font-black text-red-800">{{ new Intl.NumberFormat('uz-UZ').format(serviceForm.balance) }} so'm</span>
                        </div>
                    </div>

                </div>
            </div>

            <div class="px-8 py-5 bg-white border-t border-gray-200 flex justify-end gap-4 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                <button @click="isServiceModalOpen = false" class="px-8 py-3 text-base font-bold text-gray-700 bg-white border-2 border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
                    Bekor qilish
                </button>
                <button @click="saveService" :disabled="!serviceForm.name" class="px-8 py-3 text-base font-bold text-white bg-indigo-600 border border-transparent rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-md">
                    Xizmatni Saqlash
                </button>
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
