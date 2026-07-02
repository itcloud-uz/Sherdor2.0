<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

import { jsPDF } from 'jspdf';
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

const rows = ref([]); // current selected rows
const draggedIdx = ref(null);
const activeTab = ref('works');

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
    note: ''
};
const workForm = ref({ ...defaultWorkForm });
const isSubmittingWork = ref(false);

// Transaction Modal state
const isTransactionModalOpen = ref(false);
const isSubmittingTransaction = ref(false);
const transactionForm = ref({
    type: 'payment',
    amount: 0,
    description: '',
    date: new Date().toISOString().slice(0, 10),
    payment_method: 'cash',
    account_type: 'main',
    linked_work_type: '',
    linked_work_id: ''
});

const openTransactionModal = (work = null) => {
    if (!selectedOwner.value) return;
    
    // Switch to finance tab if we are paying
    if (activeTab.value !== 'finance') {
        activeTab.value = 'finance';
    }

    let amount = 0;
    let desc = '';
    let linkedType = '';
    let linkedId = '';

    if (work) {
        amount = work.total_price || 0;
        desc = `${work.service_name} xizmati uchun to'lov`;
        linkedType = 'App\\Models\\MachineWork';
        linkedId = work.id;
    }

    transactionForm.value = {
        type: 'payment',
        amount: amount,
        description: desc,
        date: new Date().toISOString().slice(0, 10),
        payment_method: 'cash',
        account_type: 'main',
        linked_work_type: linkedType,
        linked_work_id: linkedId
    };
    isTransactionModalOpen.value = true;
};

const submitTransaction = () => {
    if (!selectedOwner.value) return;
    isSubmittingTransaction.value = true;
    router.post(route('admin.hr.owner.transaction.store', selectedOwner.value.id), transactionForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isTransactionModalOpen.value = false;
        },
        onFinish: () => {
            isSubmittingTransaction.value = false;
        }
    });
};

onMounted(() => {
    // Auto expand and select the first owner if available
    if (props.owners && props.owners.length > 0) {
        expandedOwners.value[props.owners[0].id] = true;
        selectedOwner.value = props.owners[0];
    }
});

// Update current rows when an owner is selected
watch(selectedOwner, (newOwner) => {
    if (newOwner) {
        rows.value = newOwner.works || [];
    } else {
        rows.value = [];
    }
});

watch(() => props.owners, (newOwners) => {
    if (selectedOwner.value) {
        const updatedOwner = newOwners.find(o => o.id === selectedOwner.value.id);
        if (updatedOwner) {
            selectedOwner.value = updatedOwner;
            rows.value = updatedOwner.works || [];
        }
    }
}, { deep: true });

// Auto-calculate form values
watch(workForm, (val) => {
    val.quantity = parseFloat(val.quantity) || 0;
    val.price = parseFloat(val.price) || 0;
    val.totalPrice = val.quantity * val.price;
}, { deep: true });

const processedRows = computed(() => {
    if (!selectedOwner.value) return [];
    return rows.value.map(row => {
        let salyarka_amount = 0;
        let paid_amount = 0;
        
        if (row.transactions && row.transactions.length > 0) {
            row.transactions.forEach(t => {
                const amount = parseFloat(t.amount) || 0;
                const desc = (t.description || '').toLowerCase();
                if (desc.includes('salyarka') || desc.includes('solyarka') || desc.includes('so\'lyarka')) {
                    salyarka_amount += amount;
                } else {
                    paid_amount += amount;
                }
            });
        }
        
        const total = parseFloat(row.total_price) || 0;
        const qoldiq = total - (paid_amount + salyarka_amount);
        
        return {
            ...row,
            paid_amount,
            salyarka_amount,
            qoldiq
        };
    });
});

const stats = computed(() => {
    const res = processedRows.value.reduce(
        (acc, row) => {
            acc.totalPrice += parseFloat(row.total_price) || 0;
            acc.totalPaid += parseFloat(row.paid_amount) || 0;
            acc.totalSalyarka += parseFloat(row.salyarka_amount) || 0;
            acc.totalBalance += parseFloat(row.qoldiq) || 0;
            return acc;
        },
        { totalPrice: 0, totalPaid: 0, totalSalyarka: 0, totalBalance: 0 }
    );
    return res;
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
    workForm.value = { 
        ...row,
        serviceName: row.service_name,
        totalPrice: row.total_price
    };
    editingWorkId.value = row.id;
    isWorkModalOpen.value = true;
};

const saveWork = () => {
    isSubmittingWork.value = true;
    
    const payload = {
        machine_owner_id: selectedOwner.value.id,
        machinery_id: workForm.value.machinery_id,
        date: workForm.value.date,
        service_name: workForm.value.serviceName,
        quantity: workForm.value.quantity,
        price: workForm.value.price,
        total_price: workForm.value.totalPrice,
        note: workForm.value.note
    };

    if (editingWorkId.value) {
        router.put(route('admin.hr.machine_works.update', editingWorkId.value), payload, {
            preserveScroll: true,
            onSuccess: () => {
                isWorkModalOpen.value = false;
            },
            onFinish: () => {
                isSubmittingWork.value = false;
            }
        });
    } else {
        router.post(route('admin.hr.machine_works.store'), payload, {
            preserveScroll: true,
            onSuccess: () => {
                isWorkModalOpen.value = false;
            },
            onFinish: () => {
                isSubmittingWork.value = false;
            }
        });
    }
};

const deleteRow = (id) => {
    if (confirm("Rostdan ham o'chirasizmi?")) {
        router.delete(route('admin.hr.machine_works.destroy', id), {
            preserveScroll: true
        });
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
    if (rows.value.length === 0) {
        alert("Yuklab olish uchun ma'lumot yo'q!");
        return;
    }
    
    try {
        const doc = new jsPDF();
        doc.text(`${selectedOwner.value.name} - Bajarilgan Ishlar Hisoboti`, 14, 15);
    
    const tableData = processedRows.value.map((row, index) => [
        index + 1,
        row.date,
        row.service_name,
        row.quantity,
        formatMoney(row.price),
        formatMoney(row.total_price),
        formatMoney(row.paid_amount),
        formatMoney(row.salyarka_amount),
        formatMoney(row.qoldiq),
        row.note
    ]);

    tableData.push([
        '', 'JAMI', '', '', '',
        formatMoney(stats.value.totalPrice),
        formatMoney(stats.value.totalPaid),
        formatMoney(stats.value.totalSalyarka),
        formatMoney(stats.value.totalBalance),
        ''
    ]);

    autoTable(doc, {
        startY: 25,
        head: [['№', 'Sana', 'Xizmat nomi', 'Soni', 'Narxi', 'Jami narxi', 'To\'landi', 'Salyarka olib berdik', 'Qoldiq', 'Izox']],
        body: tableData,
        theme: 'grid',
        styles: { fontSize: 8 },
        headStyles: { fillColor: [253, 224, 71], textColor: [0, 0, 0] },
        willDrawCell: function(data) {
            if (data.row.index === tableData.length - 1) {
                doc.setFont(undefined, 'bold');
            }
        }
    });

        doc.save(`${selectedOwner.value.name.replace(/\s+/g, '_')}_Hisobot.pdf`);
    } catch (error) {
        console.error("PDF yaratishda xatolik:", error);
        alert("PDF yaratishda xatolik yuz berdi. Dasturchiga murojaat qiling.");
    }
};

const exportToExcel = () => {
    if (!selectedOwner.value) return;
    if (rows.value.length === 0) {
        alert("Yuklab olish uchun ma'lumot yo'q!");
        return;
    }
    
    try {
        const headers = ['№', 'Sana', 'Xizmat nomi', 'Soni', 'Narxi', 'Jami narxi', 'To\'landi', 'Salyarka olib berdik', 'Qoldiq', 'Izox'];
    
    const data = processedRows.value.map((row, index) => [
        index + 1,
        row.date,
        row.service_name,
        row.quantity,
        row.price,
        row.total_price,
        row.paid_amount,
        row.salyarka_amount,
        row.qoldiq,
        row.note
    ]);
    
    data.push([
        '', 'JAMI:', '', '', '', 
        stats.value.totalPrice, 
        stats.value.totalPaid,
        stats.value.totalSalyarka,
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
        {wch: 12}, {wch: 15}, {wch: 20}
    ];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Hisobot");
        XLSX.writeFile(wb, `${selectedOwner.value.name.replace(/\s+/g, '_')}_Hisobot.xlsx`);
    } catch (error) {
        console.error("Excel yaratishda xatolik:", error);
        alert("Excel yaratishda xatolik yuz berdi. Dasturchiga murojaat qiling.");
    }
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

                <div v-if="!selectedOwner" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div v-if="props.owners.length === 0" class="col-span-full text-center py-12 text-gray-500 bg-white rounded-xl shadow-sm border border-gray-200">
                        Texnika egalari topilmadi.
                    </div>
                    <div 
                        v-for="owner in props.owners" 
                        :key="owner.id" 
                        @click="selectedOwner = owner"
                        class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 cursor-pointer hover:shadow-md hover:border-indigo-300 transition-all flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-lg font-bold text-gray-900 line-clamp-2">{{ owner.name }}</h3>
                                <div class="bg-gray-100 text-gray-600 text-xs font-semibold px-2 py-1 rounded-full whitespace-nowrap">
                                    {{ owner.machineries?.length || 0 }} texnika
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 mb-4 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                {{ owner.phone || 'Tel yo\'q' }}
                            </p>
                        </div>
                        <div class="pt-4 border-t border-gray-100 mt-2 space-y-2">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500">Holat:</span>
                                <span :class="owner.balance >= 0 ? 'text-green-600' : 'text-red-600'" class="font-bold">
                                    {{ formatMoney(owner.balance) }} UZS
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detail View -->
                <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col flex-1 overflow-hidden min-h-[calc(100vh-160px)]">
                    <!-- Owner Header -->
                    <div class="px-6 py-4 border-b border-gray-200 bg-amber-50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 flex-shrink-0">
                        <div class="flex items-center gap-4">
                            <button @click="selectedOwner = null" class="p-2 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 text-gray-600 transition" title="Orqaga">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            </button>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Texnik ta'minotchi: {{ selectedOwner.name }}</h2>
                                <p class="text-sm text-gray-700 mt-0.5">Telefon: <span class="font-semibold">{{ selectedOwner.phone || 'Tel yo\'q' }}</span></p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <div class="bg-white rounded-lg px-4 py-2 border border-amber-200 shadow-sm text-center">
                                <div class="text-[10px] text-gray-500 uppercase font-bold">Jami Texnikalar</div>
                                <div class="text-sm font-semibold text-gray-800">{{ selectedOwner.machineries?.length || 0 }} ta</div>
                            </div>
                            <div class="bg-white rounded-lg px-4 py-2 border border-amber-200 shadow-sm text-center">
                                <div class="text-[10px] text-gray-500 uppercase font-bold">Joriy Balans</div>
                                <div class="text-sm font-bold" :class="selectedOwner.balance >= 0 ? 'text-green-600' : 'text-red-600'">{{ formatMoney(selectedOwner.balance) }} UZS</div>
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
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium flex items-center shadow-sm text-sm"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                Bajarilgan Ish Qo'shish
                            </button>
                        </div>

                        <!-- Table Container -->
                        <div class="overflow-auto rounded-lg border border-gray-200 flex-1 custom-scrollbar">
                            <table class="w-full text-sm text-left text-gray-700 min-w-[1000px]">
                                <thead class="sticky top-0 z-10 bg-gray-50 text-gray-600 uppercase text-[11px] font-bold shadow-sm">
                                    <tr>
                                        <th class="px-3 py-3 text-center w-12 rounded-tl-lg">№</th>
                                        <th class="px-3 py-3">Sana</th>
                                        <th class="px-3 py-3">Xizmat nomi</th>
                                        <th class="px-3 py-3 text-center">Soni (soat, reys)</th>
                                        <th class="px-3 py-3 text-right">Narxi</th>
                                        <th class="px-3 py-3 text-right">Jami narxi</th>
                                        <th class="px-3 py-3 text-right">To'landi</th>
                                        <th class="px-3 py-3 text-right">Salyarka olib berdik</th>
                                        <th class="px-3 py-3 text-right">Qoldiq</th>
                                        <th class="px-3 py-3">Izoh</th>
                                        <th class="px-3 py-3 text-center w-16 rounded-tr-lg">Amallar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-if="processedRows.length === 0">
                                        <td colspan="11" class="px-6 py-12 text-center text-gray-400 text-sm">
                                            Ushbu texnik ta'minotchi uchun bajarilgan ishlar kiritilmagan.
                                        </td>
                                    </tr>
                                    <tr 
                                        v-for="(row, index) in processedRows" 
                                        :key="row.id"
                                        class="hover:bg-blue-50/50 transition-colors"
                                    >
                                        <td class="px-3 py-3 text-center text-gray-500 font-medium">{{ index + 1 }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ row.date }}</td>
                                        <td class="px-3 py-3 text-gray-700 font-semibold">{{ row.service_name }}</td>
                                        <td class="px-3 py-3 text-center font-bold text-gray-900">{{ parseFloat(row.quantity) }}</td>
                                        <td class="px-3 py-3 text-right whitespace-nowrap">{{ formatMoney(row.price) }}</td>
                                        <td class="px-3 py-3 text-right font-black text-gray-900 whitespace-nowrap bg-blue-50/30">{{ formatMoney(row.total_price) }}</td>
                                        <td class="px-3 py-3 text-right font-bold text-green-700 whitespace-nowrap bg-green-50/20">{{ formatMoney(row.paid_amount) }}</td>
                                        <td class="px-3 py-3 text-right font-bold text-orange-600 whitespace-nowrap bg-orange-50/20">{{ formatMoney(row.salyarka_amount) }}</td>
                                        <td class="px-3 py-3 text-right font-bold whitespace-nowrap bg-gray-50" :class="row.qoldiq === 0 ? 'text-gray-400' : 'text-red-600'">{{ formatMoney(row.qoldiq) }}</td>
                                        <td class="px-3 py-3 text-xs text-gray-500 max-w-[150px] truncate" :title="row.note">{{ row.note }}</td>
                                        
                                        <td class="px-2 py-3 text-center space-x-1 whitespace-nowrap">
                                            <button @click="openEditWorkModal(row)" class="text-indigo-600 hover:text-indigo-900 p-1.5 rounded-md hover:bg-indigo-50 transition" title="Tahrirlash">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                            </button>
                                            <button @click="deleteRow(row.id)" class="text-red-500 hover:text-red-700 p-1.5 rounded-md hover:bg-red-50 transition" title="O'chirish">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <!-- Footer / Totals -->
                                <tfoot class="sticky bottom-0 z-10 border-t-2 border-gray-300">
                                    <tr class="font-black text-gray-900 bg-gray-100">
                                        <td colspan="5" class="px-3 py-4 text-right uppercase tracking-wider text-sm">Jami</td>
                                        <td class="px-3 py-4 text-right whitespace-nowrap text-base">{{ formatMoney(stats.totalPrice) }}</td>
                                        <td class="px-3 py-4 text-right whitespace-nowrap text-base text-green-700">{{ formatMoney(stats.totalPaid) }}</td>
                                        <td class="px-3 py-4 text-right whitespace-nowrap text-base text-orange-600">{{ formatMoney(stats.totalSalyarka) }}</td>
                                        <td class="px-3 py-4 text-right whitespace-nowrap text-base text-red-600">{{ formatMoney(stats.totalBalance) }}</td>
                                        <td colspan="2" class="px-3 py-4"></td>
                                    </tr>
                                </tfoot>
                            </table>
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
                            <textarea v-model="workForm.note" rows="3" class="w-full border-gray-300 rounded-lg text-base p-3 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Qo'shimcha ma'lumotlar yoki izohlarni yozing..."></textarea>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tavsif (Masalan: Avans, Salarka, Yo'l kira)</label>
                        <textarea v-model="transactionForm.description" rows="2" class="w-full border-gray-300 rounded-lg text-sm" placeholder="To'lov maqsadi haqida..."></textarea>
                    </div>

                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                        <button type="button" @click="isTransactionModalOpen = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">Bekor qilish</button>
                        <button type="submit" :disabled="isSubmittingTransaction" class="px-5 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition shadow-sm disabled:opacity-50">Saqlash</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Owner Modal -->
        <div v-if="isAddOwnerModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900 bg-opacity-50">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900">{{ editingOwnerId ? 'Texnika Egasini tahrirlash' : 'Yangi Texnika Egasi (Texnik ta\'minotchi) qo\'shish' }}</h3>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Texnika Egasi (Texnik ta'minotchi) *</label>
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
