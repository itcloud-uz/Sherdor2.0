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
const activeTab = ref('works'); // works, machineries, finance

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

const isSalyarkaMode = ref(false);

const openTransactionModal = (work = null, type = 'payment') => {
    if (!selectedOwner.value) return;
    
    // Switch to finance tab if we are paying
    if (activeTab.value !== 'finance') {
        activeTab.value = 'finance';
    }

    isSalyarkaMode.value = (type === 'salyarka');
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
        machinery_id: '',
        description: desc,
        note: '',
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
    
    const payload = { ...transactionForm.value };
    let desc = payload.description || '';

    if (isSalyarkaMode.value) {
        let prefix = "Salyarka";
        if (payload.machinery_id) {
            const m = selectedOwner.value.machineries?.find(x => x.id === payload.machinery_id);
            if (m) {
                prefix += ` (${m.name}${m.driver_name ? ' - ' + m.driver_name : ''})`;
            }
        }
        desc = desc ? `${prefix}: ${desc}` : prefix;
    }

    if (payload.note && payload.note.trim()) {
        desc += ` [Izoh: ${payload.note.trim()}]`;
    }

    payload.description = desc;
    delete payload.note;

    router.post(route('admin.hr.owner.transaction.store', selectedOwner.value.id), payload, {
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
    // We intentionally do not auto-select the first owner
    // so the user can see the grid of all owners on page load.
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

const ownersWithStats = computed(() => {
    if (!props.owners) return [];
    return props.owners.map(owner => {
        let paid = 0;
        let salyarka = 0;
        
        if (owner.transactions && owner.transactions.length > 0) {
            owner.transactions.forEach(t => {
                const amount = parseFloat(t.amount) || 0;
                const desc = (t.description || '').toLowerCase();
                if (desc.includes('salyarka') || desc.includes('solyarka') || desc.includes('so\'lyarka')) {
                    salyarka += amount;
                } else {
                    paid += amount;
                }
            });
        }
        
        let worksPrice = 0;
        if (owner.works && owner.works.length > 0) {
            owner.works.forEach(w => {
                worksPrice += parseFloat(w.total_price) || 0;
            });
        }

        const balance = worksPrice - (paid + salyarka);

        return {
            ...owner,
            totalWorksPrice: worksPrice,
            totalPaid: paid,
            totalSalyarka: salyarka,
            calculatedBalance: balance
        };
    });
});

const globalStats = computed(() => {
    let totalWorksPrice = 0;
    let totalPaid = 0;
    let totalSalyarka = 0;
    let totalBalance = 0;

    if (!props.owners) return { totalWorksPrice, totalPaid, totalSalyarka, totalBalance };

    props.owners.forEach(owner => {
        let paid = 0;
        let salyarka = 0;
        
        if (owner.transactions && owner.transactions.length > 0) {
            owner.transactions.forEach(t => {
                const amount = parseFloat(t.amount) || 0;
                const desc = (t.description || '').toLowerCase();
                if (desc.includes('salyarka') || desc.includes('solyarka') || desc.includes('so\'lyarka')) {
                    salyarka += amount;
                } else {
                    paid += amount;
                }
            });
        }
        
        let worksPrice = 0;
        if (owner.works && owner.works.length > 0) {
            owner.works.forEach(w => {
                worksPrice += parseFloat(w.total_price) || 0;
            });
        }

        totalWorksPrice += worksPrice;
        totalPaid += paid;
        totalSalyarka += salyarka;
        totalBalance += (worksPrice - (paid + salyarka));
    });

    return { totalWorksPrice, totalPaid, totalSalyarka, totalBalance };
});

const formatMoney = (val) => {
    return new Intl.NumberFormat('uz-UZ').format(val || 0);
};

const formatTxnDate = (val) => {
    if (!val) return '—';
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val.trim())) {
        const [y, m, d] = val.trim().split('-');
        return `${d}.${m}.${y}`;
    }
    const d = new Date(val);
    if (isNaN(d.getTime())) return val;
    return d.toLocaleString('ru-RU', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
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

const getTxnTargetAndNote = (txn) => {
    if (!txn) return { target: 'Izohsiz to\'lov', note: '' };
    const raw = (txn.description || txn.notes || '').trim();
    if (!raw) {
        return { target: 'Izohsiz to\'lov', note: '' };
    }
    
    const match = raw.match(/^(.*?)\s*\[[Ii]zoh:\s*(.*?)\]$/s);
    if (match) {
        return {
            target: match[1].trim() || 'Izohsiz to\'lov',
            note: match[2].trim()
        };
    }
    
    return {
        target: raw,
        note: ''
    };
};

const exportFinanceToExcel = () => {
    if (!selectedOwner.value) return;
    if (!selectedOwner.value.transactions || selectedOwner.value.transactions.length === 0) {
        alert("Yuklab olish uchun ma'lumot yo'q!");
        return;
    }
    
    try {
        const headers = ['№', 'Sana', 'To\'lov maqsadi', 'Izoh', 'Turi', 'Summa (UZS)'];
        let totalAmount = 0;
        
        const data = selectedOwner.value.transactions.map((txn, index) => {
            const amount = parseFloat(txn.amount) || 0;
            totalAmount += amount;
            
            let typeStr = "Kirim";
            const desc = (txn.description || '').toLowerCase();
            if (desc.includes('salyarka') || desc.includes('solyarka') || desc.includes('so\'lyarka')) {
                typeStr = "Salyarka";
            } else if (txn.type === 'payment') {
                typeStr = "To'lov qilindi";
            }
            
            const parsed = getTxnTargetAndNote(txn);
            
            return [
                index + 1,
                formatTxnDate(txn.date || txn.created_at),
                parsed.target,
                parsed.note,
                typeStr,
                amount
            ];
        });
        
        data.push([
            '', 'JAMI:', '', '', '', totalAmount
        ]);
        
        const ws = XLSX.utils.aoa_to_sheet([headers, ...data]);
        
        const range = XLSX.utils.decode_range(ws['!ref']);
        for (let R = range.s.r; R <= range.e.r; ++R) {
            for (let C = range.s.c; C <= range.e.c; ++C) {
                const cellAddress = XLSX.utils.encode_cell({c: C, r: R});
                if (!ws[cellAddress]) continue;
                
                const isHeader = (R === 0);
                const isFooter = (R === range.e.r);
                
                ws[cellAddress].s = {
                    border: {
                        top: {style: 'thin', color: {rgb: "000000"}},
                        bottom: {style: 'thin', color: {rgb: "000000"}},
                        left: {style: 'thin', color: {rgb: "000000"}},
                        right: {style: 'thin', color: {rgb: "000000"}}
                    },
                    font: {
                        bold: isHeader || isFooter,
                        color: isHeader ? {rgb: "000000"} : (isFooter ? {rgb: "000000"} : {rgb: "333333"})
                    },
                    fill: isHeader ? {fgColor: {rgb: "F3F4F6"}} : (isFooter ? {fgColor: {rgb: "F9FAFB"}} : undefined),
                    alignment: {
                        vertical: 'center',
                        horizontal: C === 0 || C === 1 || C === 4 ? 'center' : (C === 5 ? 'right' : 'left')
                    }
                };
                if (C === 5 && typeof ws[cellAddress].v === 'number') {
                    ws[cellAddress].z = '#,##0';
                }
            }
        }
        
        ws['!cols'] = [
            {wch: 6}, {wch: 16}, {wch: 35}, {wch: 25}, {wch: 18}, {wch: 22}
        ];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Moliya_Tranzaksiyalar");
        const fileName = `${selectedOwner.value.name.replace(/\s+/g, '_')}_Moliya_Tranzaksiyalar.xlsx`;
        XLSX.writeFile(wb, fileName);
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
                <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                    <h1 class="text-2xl font-bold text-gray-800">TEXNIKALAR HISOBI</h1>
                    <div v-if="!selectedOwner" class="flex gap-2">
                        <button @click="openAddOwnerModal" class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow-sm hover:bg-blue-700 transition font-medium flex items-center text-sm">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Texnika Egasi Qo'shish
                        </button>
                    </div>
                </div>

                <div v-if="!selectedOwner">
                    <!-- Global Statistics Dashboard -->
                    <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Jami Bajarilgan Ishlar</h3>
                            <p class="text-2xl font-black text-gray-900 mt-2">{{ formatMoney(globalStats.totalWorksPrice) }} <span class="text-sm text-gray-500 font-normal">UZS</span></p>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 border-b-4 border-b-green-500">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Jami To'langan (Xarajat)</h3>
                            <p class="text-2xl font-black text-green-600 mt-2">{{ formatMoney(globalStats.totalPaid) }} <span class="text-sm text-green-600/70 font-normal">UZS</span></p>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 border-b-4 border-b-orange-500">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Salyarka Olib Berilgan</h3>
                            <p class="text-2xl font-black text-orange-500 mt-2">{{ formatMoney(globalStats.totalSalyarka) }} <span class="text-sm text-orange-500/70 font-normal">UZS</span></p>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5" :class="globalStats.totalBalance >= 0 ? 'border-b-4 border-b-green-500' : 'border-b-4 border-b-red-500'">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Umumiy Qoldiq</h3>
                            <p class="text-2xl font-black mt-2" :class="globalStats.totalBalance >= 0 ? 'text-green-600' : 'text-red-600'">{{ formatMoney(globalStats.totalBalance) }} <span class="text-sm font-normal opacity-70">UZS</span></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <div v-if="ownersWithStats.length === 0" class="col-span-full text-center py-12 text-gray-500 bg-white rounded-xl shadow-sm border border-gray-200">
                            Texnika egalari topilmadi.
                        </div>
                        <div 
                            v-for="owner in ownersWithStats" 
                            :key="owner.id" 
                            @click="selectedOwner = owner"
                            class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 cursor-pointer hover:shadow-md hover:border-indigo-300 transition-all flex flex-col justify-between group"
                        >
                            <div>
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-lg font-bold text-gray-900 line-clamp-2 group-hover:text-indigo-600 transition-colors">{{ owner.name }}</h3>
                                    <div class="bg-gray-100 text-gray-600 text-xs font-semibold px-2.5 py-1 rounded-full whitespace-nowrap">
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
                                    <span class="text-gray-500">Qarzdorlik (Kompaniya):</span>
                                    <span class="font-bold text-red-600">
                                        {{ owner.calculatedBalance > 0 ? formatMoney(owner.calculatedBalance) : 0 }} UZS
                                    </span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500">To'langan summa:</span>
                                    <span class="font-bold text-green-600">
                                        {{ formatMoney(owner.totalPaid) }} UZS
                                    </span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500">Balans (Jami ishlar):</span>
                                    <span class="font-bold text-gray-800">
                                        {{ formatMoney(owner.totalWorksPrice) }} UZS
                                    </span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500">Qarzdorlik (Texnika):</span>
                                    <span class="font-bold text-orange-600">
                                        {{ owner.calculatedBalance < 0 ? formatMoney(Math.abs(owner.calculatedBalance)) : 0 }} UZS
                                    </span>
                                </div>
                            </div>
                            <!-- Edit/Delete Actions -->
                            <div class="mt-4 flex gap-2 pt-3 border-t border-gray-100">
                                <button @click.stop="editOwner(owner)" class="flex-1 px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-md hover:bg-indigo-100 transition text-sm font-medium">Tahrirlash</button>
                                <button @click.stop="deleteOwner(owner.id)" class="flex-1 px-3 py-1.5 bg-red-50 text-red-600 rounded-md hover:bg-red-100 transition text-sm font-medium">O'chirish</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detail View -->
                <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col flex-1 overflow-hidden min-h-[calc(100vh-160px)]">
                    <!-- Owner Header -->
                    <div class="px-6 py-4 border-b border-gray-200 bg-indigo-50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 flex-shrink-0">
                        <div class="flex items-center gap-4">
                            <button @click="selectedOwner = null" class="p-2 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 text-gray-600 transition" title="Orqaga">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            </button>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">{{ selectedOwner.name }}</h2>
                                <p class="text-sm text-gray-700 mt-0.5">Telefon: <span class="font-semibold">{{ selectedOwner.phone || 'Kiritilmagan' }}</span></p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <div class="bg-white rounded-lg px-4 py-2 border border-indigo-200 shadow-sm text-center">
                                <div class="text-[10px] text-gray-500 uppercase font-bold">Jami Xizmatlar</div>
                                <div class="text-sm font-semibold text-gray-800">{{ stats.totalServices || 0 }} ta</div>
                            </div>
                            <div class="bg-white rounded-lg px-4 py-2 border border-indigo-200 shadow-sm text-center">
                                <div class="text-[10px] text-gray-500 uppercase font-bold">Joriy Balans</div>
                                <div class="text-sm font-bold" :class="selectedOwner.balance >= 0 ? 'text-green-600' : 'text-red-600'">{{ formatMoney(selectedOwner.balance) }} UZS</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="px-6 border-b border-gray-200 bg-white pt-3 flex gap-6 flex-shrink-0">
                        <button @click="activeTab = 'works'" :class="activeTab === 'works' ? 'border-b-2 border-indigo-600 text-indigo-600 font-bold' : 'text-gray-500 hover:text-gray-700'" class="pb-3 font-medium text-sm transition-colors">Bajarilgan Ishlar</button>
                        <button @click="activeTab = 'machineries'" :class="activeTab === 'machineries' ? 'border-b-2 border-indigo-600 text-indigo-600 font-bold' : 'text-gray-500 hover:text-gray-700'" class="pb-3 font-medium text-sm transition-colors">Texnikalar Ro'yxati</button>
                        <button @click="activeTab = 'finance'" :class="activeTab === 'finance' ? 'border-b-2 border-indigo-600 text-indigo-600 font-bold' : 'text-gray-500 hover:text-gray-700'" class="pb-3 font-medium text-sm transition-colors">Moliya (Tranzaksiyalar)</button>
                    </div>

                    <div v-if="activeTab === 'works'" class="p-6 flex flex-col flex-1 overflow-hidden">
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
                        <div class="overflow-auto rounded-xl border border-gray-200 flex-1 custom-scrollbar shadow-sm">
                            <table class="w-full text-sm text-left text-gray-700 min-w-[1200px]">
                                <thead class="sticky top-0 z-10 bg-gray-100 text-gray-600 uppercase text-[11px] font-bold shadow-sm">
                                    <tr>
                                        <th class="px-4 py-4 text-center w-12 rounded-tl-lg">№</th>
                                        <th class="px-4 py-4 whitespace-nowrap">Sana</th>
                                        <th class="px-4 py-4 whitespace-nowrap">Xizmat nomi</th>
                                        <th class="px-4 py-4 text-center whitespace-nowrap">Soni (soat, reys)</th>
                                        <th class="px-4 py-4 text-right whitespace-nowrap">Narxi</th>
                                        <th class="px-4 py-4 text-right whitespace-nowrap">Jami narxi</th>
                                        <th class="px-4 py-4 text-right whitespace-nowrap">To'landi</th>
                                        <th class="px-4 py-4 text-right whitespace-nowrap">Salyarka olib berdik</th>
                                        <th class="px-4 py-4 text-right whitespace-nowrap">Qoldiq</th>
                                        <th class="px-4 py-4 whitespace-nowrap">Izoh</th>
                                        <th class="px-4 py-4 text-center w-16 rounded-tr-lg whitespace-nowrap sticky right-0 bg-gray-100 shadow-[-4px_0_6px_-1px_rgba(0,0,0,0.05)] z-20">Amallar</th>
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
                                        <td class="px-4 py-3 text-center text-gray-500 font-medium">{{ index + 1 }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ row.date }}</td>
                                        <td class="px-4 py-3 text-gray-700 font-semibold">{{ row.service_name }}</td>
                                        <td class="px-4 py-3 text-center font-bold text-gray-900">{{ parseFloat(row.quantity) }}</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">{{ formatMoney(row.price) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-gray-900 whitespace-nowrap bg-blue-50/20">{{ formatMoney(row.total_price) }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-green-700 whitespace-nowrap bg-green-50/30">{{ formatMoney(row.paid_amount) }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-orange-600 whitespace-nowrap bg-orange-50/30">{{ formatMoney(row.salyarka_amount) }}</td>
                                        <td class="px-4 py-3 text-right font-bold whitespace-nowrap bg-gray-50/80" :class="row.qoldiq === 0 ? 'text-gray-400' : 'text-red-600'">{{ formatMoney(row.qoldiq) }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-500 max-w-[200px] truncate" :title="row.note">{{ row.note }}</td>
                                        
                                        <td class="px-4 py-3 text-center space-x-2 whitespace-nowrap sticky right-0 bg-white shadow-[-4px_0_6px_-1px_rgba(0,0,0,0.05)] z-10">
                                            <button @click="openEditWorkModal(row)" class="text-indigo-600 hover:text-indigo-900 p-1.5 rounded-md hover:bg-indigo-50 transition" title="Tahrirlash">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                            </button>
                                            <button @click="deleteRow(row.id)" class="text-red-500 hover:text-red-700 p-1.5 rounded-md hover:bg-red-50 transition" title="O'chirish">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="sticky bottom-0 z-10 border-t-2 border-gray-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                                    <tr class="font-black text-gray-900 bg-gray-100">
                                        <td colspan="5" class="px-4 py-4 text-right uppercase tracking-wider text-sm">Jami</td>
                                        <td class="px-4 py-4 text-right whitespace-nowrap">{{ formatMoney(stats.totalPrice) }}</td>
                                        <td class="px-4 py-4 text-right whitespace-nowrap text-green-700">{{ formatMoney(stats.totalPaid) }}</td>
                                        <td class="px-4 py-4 text-right whitespace-nowrap text-orange-600">{{ formatMoney(stats.totalSalyarka) }}</td>
                                        <td class="px-4 py-4 text-right whitespace-nowrap text-red-600">{{ formatMoney(stats.totalBalance) }}</td>
                                        <td class="px-4 py-4 bg-gray-100"></td>
                                        <td class="px-4 py-4 bg-gray-100 sticky right-0 shadow-[-4px_0_6px_-1px_rgba(0,0,0,0.05)] z-10"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Machineries Tab -->
                    <div v-else-if="activeTab === 'machineries'" class="p-6 flex flex-col flex-1 overflow-hidden bg-gray-50/50">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-bold text-gray-800">Ushbu ta'minotchiga tegishli texnikalar</h3>
                            <button @click="openAddMachineryModal(selectedOwner.id)" class="px-4 py-2 bg-orange-500 text-white rounded-lg shadow-sm hover:bg-orange-600 transition font-medium flex items-center text-sm">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Yangi Texnika Qo'shish
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                            <div v-if="!selectedOwner.machineries || selectedOwner.machineries.length === 0" class="col-span-full py-12 text-center text-gray-500 bg-white rounded-xl border border-gray-200">
                                Ushbu egasiga tegishli texnikalar kiritilmagan.
                            </div>
                            <div v-for="machinery in selectedOwner.machineries" :key="machinery.id" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-bold text-gray-900 text-lg">{{ machinery.name }}</h4>
                                        <p class="text-sm text-gray-500 mt-1 flex items-center gap-1">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                            {{ machinery.driver_name || 'Haydovchi belgilanmagan' }}
                                        </p>
                                    </div>
                                    <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                    </div>
                                </div>
                                <div class="mt-4 pt-3 border-t border-gray-100 flex gap-2">
                                    <button @click="deleteMachinery(machinery.id)" class="text-sm font-medium text-red-600 hover:text-red-800 transition">Texnikani O'chirish</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Finance Tab -->
                    <div v-else-if="activeTab === 'finance'" class="p-6 flex flex-col flex-1 overflow-hidden bg-gray-50/50">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-bold text-gray-800">Moliya va Tranzaksiyalar</h3>
                            <div class="flex gap-2">
                                <button @click="exportFinanceToExcel" class="px-3 py-2 bg-green-50 text-green-700 border border-green-200 rounded-lg hover:bg-green-100 transition flex items-center text-sm font-medium shadow-sm">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    Excel Yuklab Olish
                                </button>
                                <button @click="openTransactionModal(null, 'salyarka')" class="px-4 py-2 bg-orange-600 text-white rounded-lg shadow-sm hover:bg-orange-700 transition font-medium flex items-center text-sm">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                    Salyarka Berish
                                </button>
                            </div>
                        </div>

                        <div class="overflow-auto rounded-xl border border-gray-200 bg-white flex-1 custom-scrollbar shadow-sm">
                            <table class="w-full text-sm text-left text-gray-700 min-w-[900px]">
                                <thead class="sticky top-0 bg-gray-100 text-gray-600 uppercase text-[11px] font-bold shadow-sm z-10">
                                    <tr>
                                        <th class="px-4 py-4 text-center w-12 rounded-tl-lg">№</th>
                                        <th class="px-4 py-4 whitespace-nowrap">Sana</th>
                                        <th class="px-4 py-4 whitespace-nowrap">To'lov maqsadi</th>
                                        <th class="px-4 py-4 whitespace-nowrap">Izoh</th>
                                        <th class="px-4 py-4 text-center whitespace-nowrap">Turi</th>
                                        <th class="px-4 py-4 text-right whitespace-nowrap rounded-tr-lg">Summa (UZS)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-if="!selectedOwner.transactions || selectedOwner.transactions.length === 0">
                                        <td colspan="6" class="px-6 py-12 text-center text-gray-400 text-sm">Hali hech qanday to'lov qilinmagan.</td>
                                    </tr>
                                    <tr v-for="(txn, index) in selectedOwner.transactions" :key="txn.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3 text-center text-gray-500">{{ index + 1 }}</td>
                                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ formatTxnDate(txn.date || txn.created_at) }}</td>
                                        <td class="px-4 py-3 text-gray-900 font-medium">{{ getTxnTargetAndNote(txn).target }}</td>
                                        <td class="px-4 py-3 text-gray-600 text-xs">{{ getTxnTargetAndNote(txn).note || '—' }}</td>
                                        <td class="px-4 py-3 text-center whitespace-nowrap">
                                            <span v-if="txn.description && (txn.description.toLowerCase().includes('salyarka') || txn.description.toLowerCase().includes('solyarka'))" class="px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800">Salyarka</span>
                                            <span v-else-if="txn.type === 'payment'" class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">To'lov qilindi</span>
                                            <span v-else class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Kirim</span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 whitespace-nowrap">{{ formatMoney(txn.amount) }}</td>
                                    </tr>
                                </tbody>
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

                    <div v-if="isSalyarkaMode">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Qaysi texnikaga berildi? *</label>
                        <select v-model="transactionForm.machinery_id" class="w-full border-gray-300 rounded-lg text-sm" required>
                            <option value="">Texnikani tanlang</option>
                            <option v-for="machinery in selectedOwner?.machineries || []" :key="machinery.id" :value="machinery.id">
                                {{ machinery.name }} ({{ machinery.driver_name || 'Haydovchisiz' }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To'lov maqsadi</label>
                        <input v-model="transactionForm.description" type="text" class="w-full border-gray-300 rounded-lg text-sm" placeholder="Masalan: Moliya: Ilxom aka pagruzchik" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Izoh (Alohida)</label>
                        <input v-model="transactionForm.note" type="text" class="w-full border-gray-300 rounded-lg text-sm" placeholder="Masalan: 500$" />
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
