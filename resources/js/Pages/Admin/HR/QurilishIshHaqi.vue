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
    // Legacy localstorage removed. Works are fetched via API.
});

// Update current rows when a brigade is selected
const fetchBrigadeWorks = async (brigadeId) => {
    try {
        const res = await fetch(`/admin/hr/brigades/${brigadeId}/works`);
        if (res.ok) {
            rows.value = await res.json();
        }
    } catch (e) {
        console.error('Failed to fetch brigade works', e);
    }
};

watch(selectedBrigade, (newBrigade) => {
    if (newBrigade) {
        fetchBrigadeWorks(newBrigade.id);
    } else {
        rows.value = [];
    }
});

// Auto-calculate form values
watch(serviceForm, (val) => {
    const m1 = parseFloat(val.measure1) || 0;
    const m2 = parseFloat(val.measure2) || 0;
    const q = parseFloat(val.quantity) || 0;
    const p = parseFloat(val.price) || 0;
    const pay = parseFloat(val.paid_amount) || parseFloat(val.paymentAmount) || 0;

    // G * H = I
    val.total_measure = Number((m1 * m2).toFixed(4));

    // If I > 0, K = I * J (J defaults to 1). Otherwise K = J.
    const activeQty = q > 0 ? q : 1;
    if (val.total_measure > 0) {
        val.total = Number((val.total_measure * activeQty).toFixed(4));
    } else {
        val.total = Number(q.toFixed(4));
    }

    // K * L = M
    val.calculated_sum = Number((val.total * p).toFixed(2));

    // M - O = P
    val.balance = Number((val.calculated_sum - pay).toFixed(2));
}, { deep: true });

const processedRows = computed(() => {
    if (!selectedBrigade.value) return [];
    return rows.value.map(row => {
        let ovqat_amount = 0;
        let paid_amount = 0;
        
        if (row.transactions && row.transactions.length > 0) {
            row.transactions.forEach(t => {
                const amount = parseFloat(t.amount) || 0;
                const desc = (t.description || '').toLowerCase();
                if (desc.includes('ovqat') || desc.includes('boshqa') || desc.includes('oziq')) {
                    ovqat_amount += amount;
                } else {
                    paid_amount += amount;
                }
            });
        }
        
        const total = parseFloat(row.calculated_sum) || 0;
        const qoldiq = total - (paid_amount + ovqat_amount);
        
        return {
            ...row,
            paid_amount,
            ovqat_amount,
            qoldiq
        };
    });
});

const stats = computed(() => {
    const acc = processedRows.value.reduce(
        (acc, row) => {
            acc.totalServices += 1;
            acc.totalCalculatedSum += parseFloat(row.calculated_sum) || 0;
            acc.totalPaid += parseFloat(row.paid_amount) || 0;
            acc.totalOvqat += parseFloat(row.ovqat_amount) || 0;
            acc.totalBalance += parseFloat(row.qoldiq) || 0;
            return acc;
        },
        { totalServices: 0, totalCalculatedSum: 0, totalPaid: 0, totalOvqat: 0, totalBalance: 0 }
    );
    return acc;
});

const globalStats = computed(() => {
    let totalWorksPrice = 0;
    let totalPaid = 0;
    let totalOvqat = 0;
    let totalBalance = 0;

    if (!props.brigades) return { totalWorksPrice, totalPaid, totalOvqat, totalBalance };

    props.brigades.forEach(brigade => {
        let paid = 0;
        let ovqat = 0;
        
        if (brigade.transactions && brigade.transactions.length > 0) {
            brigade.transactions.forEach(t => {
                const amount = parseFloat(t.amount) || 0;
                const desc = (t.description || '').toLowerCase();
                if (desc.includes('ovqat') || desc.includes('boshqa') || desc.includes('oziq')) {
                    ovqat += amount;
                } else {
                    paid += amount;
                }
            });
        }
        
        let worksPrice = 0;
        if (brigade.works && brigade.works.length > 0) {
            brigade.works.forEach(w => {
                worksPrice += parseFloat(w.calculated_sum) || 0;
            });
        }

        totalWorksPrice += worksPrice;
        totalPaid += paid;
        totalOvqat += ovqat;
        totalBalance += (worksPrice - (paid + ovqat));
    });

    return { totalWorksPrice, totalPaid, totalOvqat, totalBalance };
});


const exportToPDF = () => {
    if (!selectedBrigade.value) return;
    if (processedRows.value.length === 0) {
        alert("Yuklab olish uchun xizmatlar mavjud emas!");
        return;
    }

    try {
        const doc = new jsPDF('landscape');
        doc.text(`${selectedBrigade.value.name} - Bajarilgan Xizmatlar Hisoboti`, 14, 15);
    
    const tableData = processedRows.value.map((row, index) => [
        index + 1,
        row.work_date || row.date || '-',
        row.work_name || row.name || '-',
        row.quantity,
        new Intl.NumberFormat('uz-UZ').format(row.price || 0),
        new Intl.NumberFormat('uz-UZ').format(row.calculated_sum || 0),
        new Intl.NumberFormat('uz-UZ').format(row.paid_amount || 0),
        new Intl.NumberFormat('uz-UZ').format(row.ovqat_amount || 0),
        new Intl.NumberFormat('uz-UZ').format(row.qoldiq || 0),
        row.note || ''
    ]);

    tableData.push([
        '', 'JAMI', '', '', '',
        new Intl.NumberFormat('uz-UZ').format(stats.value.totalCalculatedSum),
        new Intl.NumberFormat('uz-UZ').format(stats.value.totalPaid),
        new Intl.NumberFormat('uz-UZ').format(stats.value.totalOvqat),
        new Intl.NumberFormat('uz-UZ').format(stats.value.totalBalance),
        ''
    ]);

    autoTable(doc, {
        startY: 25,
        head: [['№', 'Sana', 'Xizmat nomi', 'Soni', 'Narxi', 'Jami narxi', 'To\'landi', 'Oziq-ovqat/Boshqa', 'Qoldiq', 'Izoh']],
        body: tableData,
        theme: 'grid',
        styles: { fontSize: 8 },
        headStyles: { fillColor: [79, 70, 229], textColor: [255, 255, 255] },
        willDrawCell: function(data) {
            if (data.row.index === tableData.length - 1) {
                doc.setFont(undefined, 'bold');
            }
        }
    });

        doc.save(`${selectedBrigade.value.name.replace(/\s+/g, '_')}_Hisobot.pdf`);
    } catch (error) {
        console.error("PDF yaratishda xatolik:", error);
        alert("PDF yaratishda xatolik yuz berdi.");
    }
};

const exportToExcel = () => {
    if (!selectedBrigade.value) return;
    if (processedRows.value.length === 0) {
        alert("Yuklab olish uchun xizmatlar mavjud emas!");
        return;
    }
    
    try {
        const headers = ['№', 'Sana', 'Xizmat nomi', 'Soni', 'Narxi', 'Jami narxi', 'To\'landi', 'Oziq-ovqat/Boshqa', 'Qoldiq', 'Izoh'];
    
    const data = processedRows.value.map((row, index) => [
        index + 1,
        row.work_date || row.date || '-',
        row.work_name || row.name || '-',
        row.quantity,
        row.price || 0,
        row.calculated_sum || 0,
        row.paid_amount || 0,
        row.ovqat_amount || 0,
        row.qoldiq || 0,
        row.note || ''
    ]);
    
    data.push([
        '', 'JAMI:', '', '', '', 
        stats.value.totalCalculatedSum, 
        stats.value.totalPaid, 
        stats.value.totalOvqat, 
        stats.value.totalBalance,
        ''
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
        {wch: 5}, {wch: 12}, {wch: 25}, {wch: 10}, {wch: 15}, 
        {wch: 18}, {wch: 15}, {wch: 20}, {wch: 15}, {wch: 20}
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
                <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                    <h1 class="text-2xl font-bold text-gray-800">QURILISH ISH HAQI</h1>
                    <div v-if="!selectedBrigade" class="flex gap-2">
                        <button @click="openAddBrigadeModal" class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow-sm hover:bg-blue-700 transition font-medium flex items-center text-sm">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Jamoa Qo'shish
                        </button>
                    </div>
                </div>

                <div v-if="!selectedBrigade">
                    <!-- Global Statistics Dashboard -->
                    <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Jami Bajarilgan Ishlar</h3>
                            <p class="text-2xl font-black text-gray-900 mt-2">{{ formatMoney(globalStats.totalWorksPrice) }} <span class="text-sm text-gray-500 font-normal">UZS</span></p>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 border-b-4 border-b-green-500">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Jami To'langan</h3>
                            <p class="text-2xl font-black text-green-600 mt-2">{{ formatMoney(globalStats.totalPaid) }} <span class="text-sm text-green-600/70 font-normal">UZS</span></p>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 border-b-4 border-b-orange-500">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Oziq-ovqat/Boshqa</h3>
                            <p class="text-2xl font-black text-orange-500 mt-2">{{ formatMoney(globalStats.totalOvqat) }} <span class="text-sm text-orange-500/70 font-normal">UZS</span></p>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5" :class="globalStats.totalBalance >= 0 ? 'border-b-4 border-b-green-500' : 'border-b-4 border-b-red-500'">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Umumiy Qoldiq</h3>
                            <p class="text-2xl font-black mt-2" :class="globalStats.totalBalance >= 0 ? 'text-green-600' : 'text-red-600'">{{ formatMoney(globalStats.totalBalance) }} <span class="text-sm font-normal opacity-70">UZS</span></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <div v-if="props.brigades.length === 0" class="col-span-full text-center py-12 text-gray-500 bg-white rounded-xl shadow-sm border border-gray-200">
                            Jamoalar topilmadi.
                        </div>
                        <div 
                            v-for="brigade in props.brigades" 
                            :key="brigade.id" 
                            @click="selectedBrigade = brigade"
                            class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 cursor-pointer hover:shadow-md hover:border-indigo-300 transition-all flex flex-col justify-between group"
                        >
                            <div>
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-lg font-bold text-gray-900 line-clamp-2 group-hover:text-indigo-600 transition-colors">{{ brigade.name }}</h3>
                                    <div class="flex space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button @click.stop="editBrigade(brigade)" class="p-1 text-blue-500 hover:bg-blue-50 rounded" title="Tahrirlash">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                        <button @click.stop="deleteBrigade(brigade.id)" class="p-1 text-red-500 hover:bg-red-50 rounded" title="O'chirish">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-500 mb-4 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    {{ brigade.foreman_name || 'Ism kiritilmagan' }}
                                </p>
                            </div>
                            <div class="pt-4 border-t border-gray-100 mt-2 space-y-2">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500">Holat:</span>
                                    <span :class="brigade.balance >= 0 ? 'text-green-600' : 'text-red-600'" class="font-bold text-base bg-gray-50 px-2 py-1 rounded-md">
                                        {{ formatMoney(brigade.balance) }} UZS
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detail View -->
                <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col flex-1 overflow-hidden min-h-[calc(100vh-160px)]">
                    <!-- Brigade Header -->
                    <div class="px-6 py-4 border-b border-gray-200 bg-indigo-50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 flex-shrink-0">
                        <div class="flex items-center gap-4">
                            <button @click="selectedBrigade = null" class="p-2 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 text-gray-600 transition" title="Orqaga">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            </button>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">{{ selectedBrigade.name }}</h2>
                                <p class="text-sm text-gray-700 mt-0.5">Brigadir: <span class="font-semibold">{{ selectedBrigade.foreman_name || 'Kiritilmagan' }}</span></p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <div class="bg-white rounded-lg px-4 py-2 border border-indigo-200 shadow-sm text-center">
                                <div class="text-[10px] text-gray-500 uppercase font-bold">Jami Xizmatlar</div>
                                <div class="text-sm font-semibold text-gray-800">{{ stats.totalServices || 0 }} ta</div>
                            </div>
                            <div class="bg-white rounded-lg px-4 py-2 border border-indigo-200 shadow-sm text-center">
                                <div class="text-[10px] text-gray-500 uppercase font-bold">Joriy Balans</div>
                                <div class="text-sm font-bold" :class="selectedBrigade.balance >= 0 ? 'text-green-600' : 'text-red-600'">{{ formatMoney(selectedBrigade.balance) }} UZS</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 flex flex-col flex-1 overflow-hidden">
                        <!-- Action Bar -->
                        <div class="flex justify-between items-center mb-4 flex-shrink-0">
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

                        <!-- Table Container -->
                        <div class="overflow-auto rounded-xl border border-gray-200 flex-1 custom-scrollbar shadow-sm">
                            <table class="w-full text-sm text-left text-gray-700 min-w-[1200px]">
                                <thead class="sticky top-0 z-10 bg-gray-100 text-gray-600 uppercase text-[11px] font-bold shadow-sm">
                                    <tr>
                                        <th class="px-4 py-4 text-center w-12 rounded-tl-lg">№</th>
                                        <th class="px-4 py-4 whitespace-nowrap">Sana</th>
                                        <th class="px-4 py-4 whitespace-nowrap">Xizmat nomi</th>
                                        <th class="px-4 py-4 text-center whitespace-nowrap">Soni</th>
                                        <th class="px-4 py-4 text-right whitespace-nowrap">Narxi</th>
                                        <th class="px-4 py-4 text-right whitespace-nowrap">Jami narxi</th>
                                        <th class="px-4 py-4 text-right whitespace-nowrap">To'landi</th>
                                        <th class="px-4 py-4 text-right whitespace-nowrap">Oziq-ovqat/Boshqa</th>
                                        <th class="px-4 py-4 text-right whitespace-nowrap">Qoldiq</th>
                                        <th class="px-4 py-4 whitespace-nowrap">Izoh</th>
                                        <th class="px-4 py-4 text-center w-16 rounded-tr-lg whitespace-nowrap">Amallar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-if="processedRows.length === 0">
                                        <td colspan="11" class="px-6 py-12 text-center text-gray-400 text-sm">
                                            Ushbu jamoa uchun xizmatlar mavjud emas. Excel jadvalini yaratish uchun "+ Yangi Xizmat" tugmasini bosing.
                                        </td>
                                    </tr>
                                    <tr 
                                        v-for="(row, index) in processedRows" 
                                        :key="row.id"
                                        class="hover:bg-indigo-50/50 transition-colors"
                                    >
                                        <td class="px-4 py-3 text-center text-gray-500 font-medium">{{ index + 1 }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ row.work_date || row.date }}</td>
                                        <td class="px-4 py-3 text-gray-700 font-semibold">{{ row.work_name || row.name }}</td>
                                        <td class="px-4 py-3 text-center font-bold text-gray-900">{{ parseFloat(row.quantity) }}</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">{{ formatMoney(row.price) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-gray-900 whitespace-nowrap bg-indigo-50/20">{{ formatMoney(row.calculated_sum) }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-green-700 whitespace-nowrap bg-green-50/30">{{ formatMoney(row.paid_amount) }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-orange-600 whitespace-nowrap bg-orange-50/30">{{ formatMoney(row.ovqat_amount) }}</td>
                                        <td class="px-4 py-3 text-right font-bold whitespace-nowrap bg-gray-50/80" :class="row.qoldiq === 0 ? 'text-gray-400' : 'text-red-600'">{{ formatMoney(row.qoldiq) }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-500 max-w-[200px] truncate" :title="row.note">{{ row.note }}</td>
                                        
                                        <td class="px-4 py-3 text-center space-x-1 whitespace-nowrap">
                                            <button @click="openEditServiceModal(row)" class="text-indigo-600 hover:text-indigo-900 p-1.5 rounded-md hover:bg-indigo-50 transition" title="Tahrirlash">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                            </button>
                                            <button @click="deleteRow(row.id)" class="text-red-500 hover:text-red-700 p-1.5 rounded-md hover:bg-red-50 transition" title="O'chirish">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="sticky bottom-0 z-10 border-t-2 border-gray-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                                    <tr class="font-black text-gray-900 bg-gray-100">
                                        <td colspan="5" class="px-4 py-4 text-right uppercase tracking-wider text-sm">Jami</td>
                                        <td class="px-4 py-4 text-right whitespace-nowrap">{{ formatMoney(stats.totalCalculatedSum) }}</td>
                                        <td class="px-4 py-4 text-right whitespace-nowrap text-green-700">{{ formatMoney(stats.totalPaid) }}</td>
                                        <td class="px-4 py-4 text-right whitespace-nowrap text-orange-600">{{ formatMoney(stats.totalOvqat) }}</td>
                                        <td class="px-4 py-4 text-right whitespace-nowrap text-red-600">{{ formatMoney(stats.totalBalance) }}</td>
                                        <td colspan="2" class="px-4 py-4 bg-gray-100"></td>
                                    </tr>
                                </tfoot>
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
                            <input v-model="serviceForm.work_name" type="text" class="w-full border-gray-300 rounded-lg text-base p-2.5" placeholder="Masalan: Beton katlavat 2 blok" />
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
                                <span class="font-bold text-gray-800">{{ serviceForm.total_measure }}</span>
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
                                    {{ new Intl.NumberFormat('uz-UZ').format(serviceForm.calculated_sum) }} so'm
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-green-700 mb-2">To'lov so'mmasi (O)</label>
                                <input v-model.number="serviceForm.paid_amount" type="number" min="0" class="w-full border-gray-300 rounded-lg text-lg p-3 font-medium focus:border-green-500 focus:ring-green-500" />
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">To'lov sanasi (N)</label>
                                <input v-model="serviceForm.work_date" type="date" class="w-full border-gray-300 rounded-lg text-lg p-3 font-medium focus:border-indigo-500 focus:ring-indigo-500" />
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
                <button @click="saveService" :disabled="!serviceForm.work_name" class="px-8 py-3 text-base font-bold text-white bg-indigo-600 border border-transparent rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-md">
                    Xizmatni Saqlash
                </button>
            </div>
        </div>

        <!-- Transaction Modal -->
        <div v-if="isTransactionModalOpen" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900 bg-opacity-50">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Moliya bo'limiga to'lov/xarajat qo'shish</h3>
                    <button @click="isTransactionModalOpen = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form @submit.prevent="submitTransaction" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sana *</label>
                            <input v-model="transactionForm.date" type="date" class="w-full border-gray-300 rounded-lg text-sm" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tranzaksiya turi *</label>
                            <select v-model="transactionForm.type" class="w-full border-gray-300 rounded-lg text-sm" required>
                                <option value="payment">To'lov / Xarajat</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="transactionForm.linked_work_id" class="bg-indigo-50 border border-indigo-100 p-3 rounded-lg text-xs text-indigo-800 flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Ushbu to'lov tanlangan ish/xizmat uchun qilinmoqda. Narxi avtomatik tarzda shu ishning qoldig'idan ayiriladi.</span>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">So'mma (UZS) *</label>
                        <input v-model.number="transactionForm.amount" type="number" min="0" class="w-full border-gray-300 rounded-lg text-lg p-2.5 bg-blue-50 focus:border-blue-500 focus:ring-blue-500" required />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To'lov turi</label>
                        <select v-model="transactionForm.payment_method" class="w-full border-gray-300 rounded-lg text-sm">
                            <option value="cash">Naqd</option>
                            <option value="card">Karta (Terminal)</option>
                            <option value="bank_transfer">Pul o'tkazish (Perekisleniye)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tavsif (Masalan: Avans, yo'l kira)</label>
                        <textarea v-model="transactionForm.description" rows="2" class="w-full border-gray-300 rounded-lg text-sm" placeholder="To'lov maqsadi haqida..."></textarea>
                    </div>

                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                        <button type="button" @click="isTransactionModalOpen = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">Bekor qilish</button>
                        <button type="submit" :disabled="isSubmittingTransaction" class="px-5 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition shadow-sm disabled:opacity-50">Saqlash</button>
                    </div>
                </form>
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
