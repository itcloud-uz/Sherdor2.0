<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import * as XLSX from 'xlsx-js-style';

const props = defineProps({
    machineries: {
        type: Array,
        default: () => []
    },
    owners: {
        type: Array,
        default: () => []
    }
});

const selectedOwner = ref(null);
const expandedOwners = ref({}); // { ownerId: boolean }

const ownerData = ref({}); // { ownerId: [rows] }
const rows = ref([]); // current selected rows
const draggedIdx = ref(null);

// Owner Modal state
const isAddOwnerModalOpen = ref(false);
const editingOwnerId = ref(null);
const newOwnerForm = ref({
    name: '',
    phone: '',
    balance: 0
});
const isSubmittingOwner = ref(false);

const openAddOwnerModal = () => {
    editingOwnerId.value = null;
    newOwnerForm.value = { name: '', phone: '', balance: 0 };
    isAddOwnerModalOpen.value = true;
};

const editOwner = (owner) => {
    editingOwnerId.value = owner.id;
    newOwnerForm.value = { 
        name: owner.name, 
        phone: owner.phone, 
        balance: owner.balance 
    };
    isAddOwnerModalOpen.value = true;
};

const deleteOwner = (id) => {
    if (confirm("Egasi va unga tegishli barcha texnikalar o'chib ketadi. Rostdan ham o'chirasizmi?")) {
        router.delete(route('admin.hr.machine_owner.destroy', id), {
            preserveScroll: true
        });
    }
};

const submitOwner = () => {
    isSubmittingOwner.value = true;
    const url = editingOwnerId.value 
        ? route('admin.hr.machine_owner.update', editingOwnerId.value) 
        : route('admin.hr.machine_owner.store');
    
    const method = editingOwnerId.value ? 'put' : 'post';

    router[method](url, newOwnerForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isAddOwnerModalOpen.value = false;
            newOwnerForm.value = { name: '', phone: '', balance: 0 };
            editingOwnerId.value = null;
        },
        onFinish: () => {
            isSubmittingOwner.value = false;
        }
    });
};

// Machinery Modal state
const isAddMachineryModalOpen = ref(false);
const editingMachineryId = ref(null);
const newMachineryForm = ref({
    name: '',
    machine_owner_id: '',
    license_plate: '',
    machinery_type: '',
    driver_name: '',
    payment_type: 'Naqd'
});
const isSubmittingMachinery = ref(false);

const openAddMachineryModal = (ownerId = '') => {
    editingMachineryId.value = null;
    newMachineryForm.value = {
        name: '',
        machine_owner_id: ownerId,
        license_plate: '',
        machinery_type: '',
        driver_name: '',
        payment_type: 'Naqd'
    };
    isAddMachineryModalOpen.value = true;
};

const editMachinery = (machinery) => {
    editingMachineryId.value = machinery.id;
    newMachineryForm.value = {
        name: machinery.name,
        machine_owner_id: machinery.machine_owner_id,
        license_plate: machinery.license_plate,
        machinery_type: machinery.machinery_type,
        driver_name: machinery.driver_name,
        payment_type: machinery.payment_type || 'Naqd'
    };
    isAddMachineryModalOpen.value = true;
};

const deleteMachinery = (id) => {
    if (confirm("Ushbu texnikani rostdan ham o'chirasizmi?")) {
        router.delete(route('admin.hr.machinery.destroy', id), {
            preserveScroll: true
        });
    }
};

const submitMachinery = () => {
    isSubmittingMachinery.value = true;
    const url = editingMachineryId.value 
        ? route('admin.hr.machinery.update', editingMachineryId.value) 
        : route('admin.hr.machinery.store');
    
    // As per route list, hr.machinery.update is POST but typically requires PUT method override.
    // However in route list it shows `POST admin/hr/machinery/{machinery} .. admin.hr.machinery.update`
    // Wait, let's just use POST since the route list says POST
    router.post(url, newMachineryForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isAddMachineryModalOpen.value = false;
            editingMachineryId.value = null;
        },
        onFinish: () => {
            isSubmittingMachinery.value = false;
        }
    });
};

// Work Modal state
const isWorkModalOpen = ref(false);
const editingWorkId = ref(null);
const defaultWorkForm = {
    id: null,
    machinery_id: '',
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
    const localData = localStorage.getItem('texnikalar_ishlar_owner');
    if (localData) {
        try {
            ownerData.value = JSON.parse(localData);
        } catch (e) {
            console.error(e);
        }
    }
    
    // Auto expand and select the first owner if available
    if (props.owners && props.owners.length > 0) {
        expandedOwners.value[props.owners[0].id] = true;
        selectedOwner.value = props.owners[0];
    }
});

// Update current rows when an owner is selected
watch(selectedOwner, (newOwner) => {
    if (newOwner) {
        if (!ownerData.value[newOwner.id]) {
            ownerData.value[newOwner.id] = [];
        }
        rows.value = ownerData.value[newOwner.id];
    } else {
        rows.value = [];
    }
});

// Watch rows to update ownerData and LocalStorage
watch(rows, (newVal) => {
    if (selectedOwner.value) {
        ownerData.value[selectedOwner.value.id] = newVal;
        localStorage.setItem('texnikalar_ishlar_owner', JSON.stringify(ownerData.value));
    }
}, { deep: true });

// Auto-calculate form values
watch(workForm, (val) => {
    val.quantity = parseFloat(val.quantity) || 0;
    val.price = parseFloat(val.price) || 0;
    val.paid = parseFloat(val.paid) || 0;
    val.fuel = parseFloat(val.fuel) || 0;

    val.totalPrice = val.quantity * val.price;
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
    if (!selectedOwner.value) return;
    const today = new Date().toISOString().slice(0, 10);
    workForm.value = { ...defaultWorkForm, id: Date.now().toString(), date: today };
    if (selectedOwner.value.machineries && selectedOwner.value.machineries.length > 0) {
        workForm.value.machinery_id = selectedOwner.value.machineries[0].id;
    }
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

const toggleOwner = (owner) => {
    expandedOwners.value[owner.id] = !expandedOwners.value[owner.id];
    selectedOwner.value = owner;
};

const exportToPDF = () => {
    if (!selectedOwner.value) return;
    const doc = new jsPDF();
    doc.text(`${selectedOwner.value.name} - Bajarilgan Ishlar Hisoboti`, 14, 15);
    
    const tableData = rows.value.map((row, index) => [
        index + 1,
        row.date,
        selectedOwner.value.machineries?.find(m => m.id === row.machinery_id)?.name || '-',
        row.serviceName,
        row.quantity,
        formatMoney(row.price),
        formatMoney(row.totalPrice),
        formatMoney(row.paid),
        formatMoney(row.fuel),
        formatMoney(row.balance),
        row.comment
    ]);

    autoTable(doc, {
        startY: 25,
        head: [['№', 'Sana', 'Texnika', 'Xizmat nomi', 'Soni', 'Narxi', 'Jami narxi', 'To\'landi', 'Salarka', 'Qoldiq', 'Izox']],
        body: tableData,
        theme: 'grid',
        styles: { fontSize: 8 },
        headStyles: { fillColor: [253, 224, 71], textColor: [0, 0, 0] }
    });

    doc.save(`${selectedOwner.value.name.replace(/\s+/g, '_')}_Hisobot.pdf`);
};

const exportToExcel = () => {
    if (!selectedOwner.value) return;
    
    const headers = ['№', 'Sana', 'Texnika', 'Xizmat nomi', 'Soni', 'Narxi', 'Jami narxi', 'To\'landi', 'Salarka', 'Qoldiq', 'Izox'];
    
    const data = rows.value.map((row, index) => [
        index + 1,
        row.date,
        selectedOwner.value.machineries?.find(m => m.id === row.machinery_id)?.name || '-',
        row.serviceName,
        row.quantity,
        row.price,
        row.totalPrice,
        row.paid,
        row.fuel,
        row.balance,
        row.comment
    ]);
    
    data.push([
        '', '', '', 'JAMI:', '', '', 
        stats.value.totalPrice, 
        stats.value.totalPaid, 
        stats.value.totalFuel, 
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
    
    // Add some styling widths
    ws['!cols'] = [
        {wch: 5}, {wch: 12}, {wch: 15}, {wch: 20}, {wch: 10}, 
        {wch: 12}, {wch: 15}, {wch: 12}, {wch: 12}, {wch: 15}, {wch: 20}
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Hisobot");
    XLSX.writeFile(wb, `${selectedOwner.value.name.replace(/\s+/g, '_')}_Hisobot.xlsx`);
};
</script>

<template>
    <AdminLayout>
        <Head title="Texnikalar" />

        <div class="p-6 bg-gray-50 min-h-screen">
            <div class="max-w-screen-2xl mx-auto">
                
                <!-- Main Header -->
                <div class="mb-6 flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-800">TEXNIKALAR HISOBI</h1>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    
                    <!-- Left Sidebar: Owners and Machineries -->
                    <div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col h-[calc(100vh-140px)]">
                        <div class="flex flex-col gap-3 mb-4 border-b pb-4">
                            <h2 class="text-md font-semibold text-gray-800">Texnika Egalari (Sohibkarlar)</h2>
                            <div class="flex gap-2">
                                <button @click="openAddOwnerModal" class="flex-1 px-3 py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-sm font-medium transition flex items-center justify-center border border-blue-200">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    Egasi Qo'shish
                                </button>
                                <button @click="openAddMachineryModal('')" class="flex-1 px-3 py-2 bg-orange-50 text-orange-700 hover:bg-orange-100 rounded-lg text-sm font-medium transition flex items-center justify-center border border-orange-200">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    Texnika Qo'shish
                                </button>
                            </div>
                        </div>

                        <div v-if="props.owners.length === 0" class="text-gray-500 text-sm text-center py-4 flex-1">
                            Texnika egalari topilmadi.
                        </div>
                        <div v-else class="space-y-3 overflow-y-auto pr-2 custom-scrollbar flex-1">
                            <div v-for="owner in props.owners" :key="owner.id" class="border border-gray-200 rounded-lg bg-gray-50 overflow-hidden shadow-sm">
                                <div 
                                    @click="toggleOwner(owner)" 
                                    class="group relative px-4 py-3 flex justify-between items-center cursor-pointer transition border-b border-transparent"
                                    :class="selectedOwner?.id === owner.id ? 'bg-orange-50 border-orange-200' : 'bg-white hover:bg-gray-50'"
                                >
                                    <div>
                                        <h3 class="font-bold text-gray-800 pr-12">{{ owner.name }}</h3>
                                        <p class="text-xs text-gray-500">{{ owner.phone || 'Tel yo\'q' }}</p>
                                    </div>
                                    <div class="flex items-center text-gray-400 group-hover:opacity-0 transition-opacity">
                                        <span class="text-xs mr-2 font-medium bg-gray-100 px-2 py-1 rounded-full">{{ owner.machineries?.length || 0 }} texnika</span>
                                        <svg class="w-5 h-5 transition-transform" :class="expandedOwners[owner.id] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </div>
                                    <div class="absolute right-2 top-3 hidden group-hover:flex space-x-1">
                                        <button @click.stop="editOwner(owner)" class="p-1.5 text-blue-500 hover:bg-blue-100 rounded-md transition" title="Tahrirlash">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                        <button @click.stop="deleteOwner(owner.id)" class="p-1.5 text-red-500 hover:bg-red-100 rounded-md transition" title="O'chirish">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <div v-show="expandedOwners[owner.id]" class="p-2 border-t border-gray-200 bg-gray-50 space-y-2">
                                    <div v-if="!owner.machineries || owner.machineries.length === 0" class="text-xs text-center text-gray-500 py-2">
                                        Ushbu egasining texnikalari yo'q
                                    </div>
                                    <div 
                                        v-for="machinery in owner.machineries" 
                                        :key="machinery.id"
                                        class="group relative w-full text-left px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 flex justify-between items-center"
                                    >
                                        <div class="overflow-hidden pr-2 w-full">
                                            <span class="font-semibold text-sm truncate block pr-12">{{ machinery.name }}</span>
                                            <span class="text-[10px] text-gray-500">{{ machinery.driver_name || 'Haydovchisiz' }}</span>
                                        </div>
                                        <div class="absolute right-2 top-2 hidden group-hover:flex space-x-1">
                                            <button @click.stop="editMachinery(machinery)" class="p-1 text-blue-500 hover:bg-blue-100 rounded transition" title="Tahrirlash">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                            </button>
                                            <button @click.stop="deleteMachinery(machinery.id)" class="p-1 text-red-500 hover:bg-red-100 rounded transition" title="O'chirish">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Panel: Machinery Details & Work Log -->
                    <div class="lg:col-span-3 h-[calc(100vh-140px)] flex flex-col">
                        <div v-if="!selectedOwner" class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 flex flex-col items-center justify-center flex-1 text-center text-gray-500">
                            <svg class="w-20 h-20 mb-4 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                            <span class="text-lg">Chap tomondagi ro'yxatdan birorta texnika egasini tanlang.</span>
                        </div>

                        <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col flex-1 overflow-hidden">
                            <!-- Owner Header -->
                            <div class="px-6 py-4 border-b border-gray-200 bg-amber-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 flex-shrink-0">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900">Sohibkar: {{ selectedOwner.name }}</h2>
                                    <p class="text-sm text-gray-700 mt-1">Telefon: <span class="font-semibold">{{ selectedOwner.phone || 'Tel yo\'q' }}</span></p>
                                </div>
                                <div class="flex gap-2">
                                    <div class="bg-white rounded-lg p-2 border border-amber-200 shadow-sm text-center">
                                        <div class="text-[10px] text-gray-500 uppercase font-bold">Jami Texnikalar</div>
                                        <div class="text-sm font-semibold text-gray-800">{{ selectedOwner.machineries?.length || 0 }} ta</div>
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
                                        @click="openAddWorkModal"
                                        class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-medium flex items-center shadow-sm text-sm"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        Bajarilgan Ish Qo'shish
                                    </button>
                                </div>

                                <!-- Table Container -->
                                <div class="overflow-auto border border-yellow-400 flex-1 custom-scrollbar">
                                    <table class="w-full text-sm text-left text-gray-900 border-collapse min-w-[800px]">
                                        <thead class="sticky top-0 z-10 shadow-sm">
                                            <!-- Top Header -->
                                            <tr>
                                                <th rowspan="2" class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center w-8 font-bold">№</th>
                                                <th rowspan="2" class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center font-bold">Sana</th>
                                                <th rowspan="2" class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center font-bold">Texnika</th>
                                                <th colspan="4" class="border border-gray-400 bg-gray-200 px-2 py-1 text-center font-bold">BAJARILGAN ISHLAR</th>
                                                <th colspan="2" class="border border-gray-400 bg-blue-200 px-2 py-1 text-center font-bold">To'landi</th>
                                                <th rowspan="2" class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center font-bold">Qoldiq</th>
                                                <th rowspan="2" class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center font-bold">Izox</th>
                                                <th rowspan="2" class="border border-gray-400 bg-gray-100 px-2 py-2 text-center w-16 font-bold">Amallar</th>
                                            </tr>
                                            <!-- Sub Header -->
                                            <tr>
                                                <th class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center font-bold">Xizmat nomi</th>
                                                <th class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center font-bold">Soni (soat, reysi)</th>
                                                <th class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center font-bold">Narxi</th>
                                                <th class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center font-bold">Jami narxi</th>
                                                <th class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center font-bold">To'landi</th>
                                                <th class="border border-gray-400 bg-yellow-300 px-2 py-2 text-center font-bold">Salarka olib berdik</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white">
                                            <tr v-if="rows.length === 0">
                                                <td colspan="12" class="border border-gray-300 px-6 py-12 text-center text-gray-500 text-sm">
                                                    Ushbu sohibkar uchun bajarilgan ishlar kiritilmagan.
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
                                                
                                                <td class="border border-gray-400 px-2 py-1 text-right text-green-700 whitespace-nowrap font-medium">{{ row.paid ? formatMoney(row.paid) : '' }}</td>
                                                <td class="border border-gray-400 px-2 py-1 text-right text-blue-700 whitespace-nowrap font-medium">{{ row.fuel ? formatMoney(row.fuel) : '' }}</td>
                                                
                                                <td class="border border-gray-400 px-2 py-1 text-right font-bold whitespace-nowrap" :class="row.balance > 0 ? 'text-gray-900' : 'text-red-600'">
                                                    {{ row.balance < 0 ? '-' : '' }} {{ formatMoney(Math.abs(row.balance)) }}
                                                </td>
                                                <td class="border border-gray-400 px-2 py-1 text-xs text-gray-600 max-w-[150px] truncate" :title="row.comment">{{ row.comment }}</td>
                                                
                                                <td class="border border-gray-400 px-1 py-1 text-center space-x-1">
                                                    <button @click="openEditWorkModal(row)" class="text-blue-600 hover:text-blue-800 p-1 rounded hover:bg-blue-50 transition" title="Tahrirlash">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                    </button>
                                                    <button @click="deleteRow(row.id)" class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50 transition" title="O'chirish">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <!-- Footer / Totals -->
                                        <tfoot class="sticky bottom-0 z-10 shadow-[0_-2px_4px_rgba(0,0,0,0.05)]">
                                            <tr class="bg-gray-50 font-bold text-gray-900">
                                                <td colspan="5" class="border border-gray-400 px-2 py-3 text-center uppercase tracking-wider">Jami</td>
                                                <td class="border border-gray-400 px-2 py-3 text-right whitespace-nowrap text-base">{{ formatMoney(stats.totalPrice) }}</td>
                                                <td class="border border-gray-400 px-2 py-3 text-right text-green-700 whitespace-nowrap text-base">{{ formatMoney(stats.totalPaid) }}</td>
                                                <td class="border border-gray-400 px-2 py-3 text-right text-blue-700 whitespace-nowrap text-base">{{ formatMoney(stats.totalFuel) }}</td>
                                                <td class="border border-gray-400 px-2 py-3 text-right whitespace-nowrap text-base" :class="stats.totalBalance > 0 ? 'text-gray-900' : 'text-red-600'">
                                                    {{ stats.totalBalance < 0 ? '-' : '' }} {{ formatMoney(Math.abs(stats.totalBalance)) }}
                                                </td>
                                                <td colspan="2" class="border border-gray-400 px-2 py-3 bg-gray-100"></td>
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

        <!-- FULL-SCREEN WORK MODAL -->
        <div v-if="isWorkModalOpen" class="fixed inset-0 bg-white z-[60] flex flex-col h-screen w-screen overflow-hidden">
            <div class="px-8 py-5 border-b border-gray-200 flex justify-between items-center bg-gray-50 shadow-sm">
                <h3 class="text-2xl font-bold text-gray-800">{{ editingWorkId ? 'Bajarilgan ishni tahrirlash' : 'Yangi bajarilgan ish kiritish' }}</h3>
                <button @click="isWorkModalOpen = false" class="text-gray-500 hover:text-red-600 bg-white rounded-full p-2 border hover:bg-red-50 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="p-8 overflow-y-auto custom-scrollbar flex-1 bg-gray-50/30">
                <div class="max-w-3xl mx-auto space-y-6">
                    
                    <!-- 1. Bajarilgan Ish -->
                    <div class="space-y-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                        <h4 class="text-lg font-bold text-indigo-900 border-b pb-3">Ish haqida ma'lumotlar</h4>
                        
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Sana <span class="text-red-500">*</span></label>
                                <input v-model="workForm.date" type="date" class="w-full border-gray-300 rounded-lg text-base p-2.5 focus:border-indigo-500 focus:ring-indigo-500" required />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Texnika (Haydovchi) <span class="text-red-500">*</span></label>
                                <select v-model="workForm.machinery_id" class="w-full border-gray-300 rounded-lg text-base p-2.5 focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="" disabled>-- Texnikani tanlang --</option>
                                    <option v-for="machinery in selectedOwner?.machineries || []" :key="machinery.id" :value="machinery.id">
                                        {{ machinery.name }} ({{ machinery.driver_name || 'Haydovchisiz' }})
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Xizmat nomi <span class="text-red-500">*</span></label>
                            <input v-model="workForm.serviceName" type="text" class="w-full border-gray-300 rounded-lg text-base p-2.5 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Masalan: Shebenka qumli, Ankelbens" required />
                        </div>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Soni (soat, reys) <span class="text-red-500">*</span></label>
                                <input v-model.number="workForm.quantity" type="number" min="0" class="w-full border-gray-300 rounded-lg text-lg p-2.5 bg-blue-50 focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Narxi (UZS) <span class="text-red-500">*</span></label>
                                <input v-model.number="workForm.price" type="number" min="0" class="w-full border-gray-300 rounded-lg text-lg p-2.5 font-medium focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Izox (Agar mavjud bo'lsa)</label>
                            <textarea v-model="workForm.comment" rows="3" class="w-full border-gray-300 rounded-lg text-base p-3 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Qo'shimcha ma'lumotlar yoki izohlarni yozing..."></textarea>
                        </div>
                        
                        <div class="flex justify-between items-center bg-yellow-50 p-4 rounded-xl border border-yellow-200 shadow-inner mt-4">
                            <span class="text-base font-bold text-gray-700 uppercase">Jami narxi:</span>
                            <span class="text-3xl font-black text-gray-900">{{ formatMoney(workForm.totalPrice) }} so'm</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-8 py-5 bg-white border-t border-gray-200 flex justify-end gap-4 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                <button @click="isWorkModalOpen = false" class="px-8 py-3 text-base font-bold text-gray-700 bg-white border-2 border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
                    Bekor qilish
                </button>
                <button @click="saveWork" :disabled="!workForm.date || !workForm.serviceName || !workForm.machinery_id" class="px-8 py-3 text-base font-bold text-white bg-orange-600 border border-transparent rounded-xl hover:bg-orange-700 disabled:opacity-50 transition-colors shadow-md">
                    Bajarilgan Ishni Saqlash
                </button>
            </div>
        </div>

        <!-- Add Owner Modal -->
        <div v-if="isAddOwnerModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-50">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900">{{ editingOwnerId ? 'Texnika Egasini tahrirlash' : 'Yangi Texnika Egasi (Sohibkar) qo\'shish' }}</h3>
                </div>
                <form @submit.prevent="submitOwner" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ismi/Kompaniya nomi *</label>
                        <input v-model="newOwnerForm.name" type="text" class="w-full border-gray-300 rounded-lg text-sm" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Telefon raqami</label>
                        <input v-model="newOwnerForm.phone" type="text" class="w-full border-gray-300 rounded-lg text-sm" />
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="isAddOwnerModalOpen = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Bekor qilish</button>
                        <button type="submit" :disabled="isSubmittingOwner" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">Saqlash</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Machinery Modal -->
        <div v-if="isAddMachineryModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-50">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900">{{ editingMachineryId ? 'Texnikani tahrirlash' : 'Yangi Texnika qo\'shish' }}</h3>
                </div>
                <form @submit.prevent="submitMachinery" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Texnika Egasi (Sohibkar) *</label>
                        <select v-model="newMachineryForm.machine_owner_id" class="w-full border-gray-300 rounded-lg text-sm" required>
                            <option value="">Egasi tanlang</option>
                            <option v-for="owner in props.owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Texnika Nomi *</label>
                        <input v-model="newMachineryForm.name" type="text" class="w-full border-gray-300 rounded-lg text-sm" placeholder="Masalan: Kamaz, Ekskavator" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Haydovchi ismi</label>
                        <input v-model="newMachineryForm.driver_name" type="text" class="w-full border-gray-300 rounded-lg text-sm" />
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="isAddMachineryModalOpen = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Bekor qilish</button>
                        <button type="submit" :disabled="isSubmittingMachinery" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">Saqlash</button>
                    </div>
                </form>
            </div>
        </div>

    </AdminLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
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
