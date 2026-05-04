<script setup>
import OperatorLayout from '@/Layouts/OperatorLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    clients: Array,
    units: Array,
    contracts: Array,
    workers: Array,
    inventory: Array,
    transactions: Array,
    machineries: Array,
    pendingRequests: Array,
    buildings: Array,
});

const activeTab = ref(0); // 0: Dashboard, 1: Sales, 2: Contracts, 3: Payments, 4: HR, 5: Warehouse

// Interactive Map States for Sales Tab
const selectedBuilding = ref(props.buildings?.length > 0 ? props.buildings[0] : null);
const selectedFloor = ref(selectedBuilding.value?.floors?.length > 0 ? selectedBuilding.value.floors[0] : null);

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

watch(selectedBuilding, (newBuilding) => {
    if (newBuilding && newBuilding.floors && newBuilding.floors.length > 0) {
        selectedFloor.value = newBuilding.floors[0];
    } else {
        selectedFloor.value = null;
    }
});

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

function getUnitFillColor(status) {
    if (status === 'available') return 'rgba(57, 255, 20, 0.3)';
    if (status === 'reserved') return 'rgba(252, 238, 9, 0.3)';
    if (status === 'sold') return 'rgba(255, 0, 60, 0.3)';
    return 'rgba(255, 255, 255, 0.1)';
}

function getUnitStrokeColor(status) {
    if (status === 'available') return '#39FF14';
    if (status === 'reserved') return '#FCEE09';
    if (status === 'sold') return '#FF003C';
    return '#ffffff';
}

function getUnitTextColor(status) {
    if (status === 'available') return 'text-neon-green';
    if (status === 'reserved') return 'text-neon-yellow';
    if (status === 'sold') return 'text-neon-magenta';
    return 'text-white';
}

function getUnitBorderColor(status) {
    if (status === 'available') return 'border-neon-green shadow-[0_0_10px_rgba(57,255,20,0.2)]';
    if (status === 'reserved') return 'border-neon-yellow shadow-[0_0_10px_rgba(252,238,9,0.2)]';
    if (status === 'sold') return 'border-neon-magenta shadow-[0_0_10px_rgba(255,0,60,0.2)]';
    return 'border-white/10';
}

// Mock chart data for Dashboard
const areaOptions = {
    chart: { type: 'area', toolbar: { show: false }, background: 'transparent' },
    colors: ['#00F0FF', '#39FF14'],
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.1, stops: [0, 90, 100] } },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    xaxis: { categories: ['Dsh', 'Ssh', 'Chr', 'Pay', 'Jum', 'Sha', 'Yak'], labels: { style: { colors: 'rgba(255,255,255,0.4)' } } },
    yaxis: { labels: { style: { colors: 'rgba(255,255,255,0.4)' } } },
    grid: { borderColor: 'rgba(255,255,255,0.05)' },
    theme: { mode: 'dark' },
    tooltip: { theme: 'dark' }
};

const totalSalesValue = computed(() => {
    return props.contracts?.reduce((sum, contract) => sum + (Number(contract.total_amount) || 0), 0) || 0;
});

const areaSeries = computed(() => {
    // Basic computation based on transactions
    let inc = [0,0,0,0,0,0,0];
    let exp = [0,0,0,0,0,0,0];
    
    if (props.transactions && props.transactions.length > 0) {
        props.transactions.forEach(t => {
            let day = new Date(t.transaction_date).getDay();
            // JS getDay(): 0 = Sun, 1 = Mon ... 6 = Sat
            // We map to 0=Mon, 6=Sun
            let idx = day === 0 ? 6 : day - 1; 
            if (t.type === 'income') inc[idx] += Number(t.amount) || 0;
            if (t.type === 'expense') exp[idx] += Number(t.amount) || 0;
        });
    } else {
        // Fallback mock data
        inc = [31, 40, 28, 51, 42, 109, 100];
        exp = [11, 32, 45, 32, 34, 52, 41];
    }
    
    return [
        { name: 'Kirim (Sotuv/Kassa)', data: inc },
        { name: 'Chiqim (Xarajat/Ombor)', data: exp }
    ];
});

// HR Modals State
const showHrModal = ref(false);
const activeHrModalType = ref(''); // 'yollanma', 'faceid', 'davomat', 'texnika'
const hrForm = ref({});
const isScanning = ref(false);

function openHrModal(type) {
    activeHrModalType.value = type;
    showHrModal.value = true;
    if (type === 'yollanma') {
        hrForm.value = { name: '', phone: '', role: 'builder', salary_type: 'piece_rate', metric_type: 'dona', base_rate: '', building_id: '', floor_id: '', unit_id: '' };
    } else if (type === 'texnika') {
        hrForm.value = { name: '', owner_name: '', machinery_type: 'general', payment_type: 'hourly', license_plate: '', cost_per_hour: '' };
    } else if (type === 'davomat') {
        hrForm.value = { type: 'worker', worker_id: '', machinery_id: '', building_id: '', floor_id: '', unit_id: '', hours_worked: '', volume_done: '', date: new Date().toISOString().split('T')[0] };
    } else if (type === 'faceid') {
        hrForm.value = { worker_id: '' };
        startScanner();
    }
}

function closeHrModal() {
    showHrModal.value = false;
    isScanning.value = false;
    stopScanner();
}

const scannerVideo = ref(null);

function startScanner() {
    isScanning.value = true;
    setTimeout(async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
            if (scannerVideo.value) {
                scannerVideo.value.srcObject = stream;
            }
        } catch (err) {
            console.error("Camera error:", err);
            alert("Kameraga ulanishda xatolik. Ruxsatlarni tekshiring.");
            isScanning.value = false;
        }
    }, 100);
}

function stopScanner() {
    if (scannerVideo.value && scannerVideo.value.srcObject) {
        scannerVideo.value.srcObject.getTracks().forEach(t => t.stop());
        scannerVideo.value.srcObject = null;
    }
}

function submitHrRequest() {
    let payload = { ...hrForm.value };
    
    // For camera inputs (Face ID only now)
    if (activeHrModalType.value === 'faceid' && scannerVideo.value) {
        const canvas = document.createElement('canvas');
        canvas.width = scannerVideo.value.videoWidth || 640;
        canvas.height = scannerVideo.value.videoHeight || 480;
        canvas.getContext('2d').drawImage(scannerVideo.value, 0, 0);
        payload.image = canvas.toDataURL('image/jpeg', 0.8);
    }

    let backendType = activeHrModalType.value;
    if (backendType === 'yollanma') backendType = 'hr_worker';
    else if (backendType === 'texnika') backendType = 'hr_machinery';
    else if (backendType === 'davomat') backendType = 'hr_attendance';
    else if (backendType === 'faceid') backendType = 'hr_biometric';

    router.post(route('operator.requests.store'), {
        type: backendType,
        payload: payload
    }, {
        onSuccess: () => {
            closeHrModal();
        }
    });
}

const showWorkerActionModal = ref(false);
const selectedWorker = ref(null);
const workerActionForm = ref({ type: 'advance', amount: '', notes: '' });

function openWorkerActionModal(worker) {
    selectedWorker.value = worker;
    workerActionForm.value = { type: 'advance', amount: '', notes: '' };
    showWorkerActionModal.value = true;
}

function submitWorkerAction() {
    // For now, let's just use hr_attendance endpoint or a new one.
    // If we want it to go to approvals, we can send type: 'hr_action'
    router.post(route('operator.requests.store'), {
        type: 'hr_action',
        payload: {
            worker_id: selectedWorker.value.id,
            action_type: workerActionForm.value.type,
            amount: workerActionForm.value.amount,
            notes: workerActionForm.value.notes,
            worker_name: selectedWorker.value.name
        }
    }, {
        onSuccess: () => {
            showWorkerActionModal.value = false;
        }
    });
}

const showContractDetailsModal = ref(false);
const selectedContract = ref(null);

function openContractDetailsModal(contract) {
    selectedContract.value = contract;
    showContractDetailsModal.value = true;
}

const showEditContractModal = ref(false);
const editContractForm = ref({
    contract_id: '',
    client_first_name: '',
    client_last_name: '',
    client_phone: '',
    client_passport: '',
    total_amount: '',
    down_payment: '',
    term_months: '',
    payment_type: ''
});

function openEditContractModal(contract) {
    showContractDetailsModal.value = false;
    editContractForm.value = {
        contract_id: contract.id,
        client_first_name: contract.client?.first_name || '',
        client_last_name: contract.client?.last_name || '',
        client_phone: contract.client?.phone || '',
        client_passport: contract.client?.passport_number || '',
        total_amount: contract.total_amount || '',
        down_payment: contract.down_payment || '',
        term_months: contract.term_months || '',
        payment_type: contract.payment_type || 'installment'
    };
    showEditContractModal.value = true;
}

function submitEditContractForm() {
    router.post(route('operator.requests.store'), {
        type: 'edit_contract',
        payload: editContractForm.value
    }, {
        onSuccess: () => {
            showEditContractModal.value = false;
        }
    });
}

// CRM Modals
const showClientModal = ref(false);
const clientForm = ref({ first_name: '', last_name: '', phone: '', passport: '' });
function openClientModal() { showClientModal.value = true; clientForm.value = { first_name: '', last_name: '', phone: '', passport: '' }; }
function submitClientForm() {
    router.post(route('operator.requests.store'), { type: 'new_client', payload: clientForm.value }, { onSuccess: () => showClientModal.value = false });
}

const showBookModal = ref(false);
const bookForm = ref({ unit_id: '', client_id: '', payment_type: 'cash', initial_payment: '' });
function openBookModal(unit) { showBookModal.value = true; bookForm.value = { unit_id: unit.id, client_id: '', payment_type: 'cash', initial_payment: '' }; }
function submitBookForm() {
    router.post(route('operator.requests.store'), { type: 'book_unit', payload: bookForm.value }, { onSuccess: () => showBookModal.value = false });
}

const showContractModal = ref(false);
const contractForm = ref({ client_id: '', unit_id: '', contract_type: 'residential', price_per_sqm: '', total_amount: '', down_payment: '', term_months: 36, payment_type: 'installment' });
function openContractModal(unit = null) { 
    showContractModal.value = true; 
    contractForm.value = { 
        client_id: '', 
        unit_id: unit ? unit.id : '', 
        contract_type: 'residential',
        price_per_sqm: unit ? unit.price : '',
        total_amount: unit ? (unit.price * unit.area_sqm) : '', 
        down_payment: '',
        term_months: 36,
        payment_type: 'installment'
    }; 
}
function submitContractForm() {
    router.post(route('operator.requests.store'), { type: 'new_contract', payload: contractForm.value }, { onSuccess: () => showContractModal.value = false });
}

const selectedUnit = computed(() => {
    if (!contractForm.value.unit_id) return null;
    return props.units.find(u => u.id === contractForm.value.unit_id);
});

// Unit Action Modal
const showUnitActionModal = ref(false);
const activeActionUnit = ref(null);

function openUnitActionModal(unit) {
    if (unit.status !== 'available' && unit.status !== 'booked') return;
    activeActionUnit.value = unit;
    showUnitActionModal.value = true;
}

function closeUnitActionModal() {
    showUnitActionModal.value = false;
    activeActionUnit.value = null;
}

function handleUnitAction(action) {
    if (action === 'book') {
        openBookModal(activeActionUnit.value);
    } else if (action === 'contract') {
        openContractModal(activeActionUnit.value);
    }
    closeUnitActionModal();
}

watch(() => contractForm.value.unit_id, (newUnitId) => {
    if (newUnitId) {
        const u = props.units.find(unit => unit.id === newUnitId);
        if (u) {
            contractForm.value.price_per_sqm = Number(u.price) || 0;
            const totalPrice = contractForm.value.price_per_sqm * (Number(u.area_sqm) || 0);
            contractForm.value.total_amount = totalPrice;
            if (contractForm.value.payment_type === 'full') {
                contractForm.value.down_payment = totalPrice;
                contractForm.value.term_months = 1;
            } else {
                contractForm.value.down_payment = totalPrice * 0.3; // Default 30%
            }
        }
    }
});
watch(() => contractForm.value.price_per_sqm, (newPrice) => {
    if (contractForm.value.unit_id) {
        const u = props.units.find(unit => unit.id === contractForm.value.unit_id);
        if (u) {
            const totalPrice = (Number(newPrice) || 0) * (Number(u.area_sqm) || 0);
            contractForm.value.total_amount = totalPrice;
            if (contractForm.value.payment_type === 'full') {
                contractForm.value.down_payment = totalPrice;
            } else {
                contractForm.value.down_payment = totalPrice * 0.3;
            }
        }
    }
});
watch(() => contractForm.value.payment_type, (newType) => {
    if (newType === 'full') {
        contractForm.value.down_payment = contractForm.value.total_amount;
        contractForm.value.term_months = 1;
    }
});

const showPaymentModal = ref(false);
const paymentForm = ref({ contract_id: '', amount: '', payment_method: 'cash', comment: '' });
function openPaymentModal(contractId = '') { showPaymentModal.value = true; paymentForm.value = { contract_id: contractId, amount: '', payment_method: 'cash', comment: '' }; }
function submitPaymentForm() {
    router.post(route('operator.requests.store'), { type: 'new_payment', payload: paymentForm.value }, { onSuccess: () => showPaymentModal.value = false });
}

// Expense (Chiqim) Modal State
const showExpenseModal = ref(false);
const expenseForm = ref({ amount: '', category: '', description: '' });

function openExpenseModal() {
    showExpenseModal.value = true;
    expenseForm.value = { amount: '', category: '', description: '' };
}

function closeExpenseModal() {
    showExpenseModal.value = false;
}

function submitExpenseForm() {
    router.post(route('operator.requests.store'), { 
        type: 'finance_expense', 
        payload: expenseForm.value 
    }, { 
        onSuccess: () => closeExpenseModal() 
    });
}

const paymentSearchQuery = ref('');
const filteredContractsForPayment = computed(() => {
    if (!paymentSearchQuery.value) return props.contracts;
    const query = paymentSearchQuery.value.toLowerCase();
    return props.contracts.filter(c => {
        const name = `${c.client?.first_name} ${c.client?.last_name}`.toLowerCase();
        const passport = c.client?.passport_number?.toLowerCase() || '';
        const pinfl = c.client?.pinfl?.toLowerCase() || '';
        const unit = c.unit?.unit_number?.toString().toLowerCase() || '';
        return name.includes(query) || passport.includes(query) || pinfl.includes(query) || unit.includes(query);
    });
});

// Warehouse Modal State
const showWarehouseModal = ref(false);
const warehouseForm = ref({
    action: 'kirim',
    name: '',
    category: '',
    quantity: '',
    unit: 'kg',
    cost: '',
    attachment_type: 'qr', // 'qr' yoki 'photo'
    invoice_photo: null
});

function openWarehouseModal() {
    showWarehouseModal.value = true;
    warehouseForm.value = {
        action: 'kirim',
        name: '',
        category: '',
        quantity: '',
        unit: 'kg',
        cost: '',
        attachment_type: 'qr',
        invoice_photo: null
    };
}

function closeWarehouseModal() {
    showWarehouseModal.value = false;
}

function handlePhotoUpload(event) {
    warehouseForm.value.invoice_photo = event.target.files[0];
}

function submitWarehouseRequest() {
    const formData = new FormData();
    formData.append('type', 'warehouse_' + warehouseForm.value.action);
    
    // Server payload array sifatida emas, balki string yoki individual field sifatida o'qiydi 
    // chunki PHP da form-data da nested array larni fayl bilan birga jo'natish biroz murakkab bo'lishi mumkin.
    // Shuning uchun payload ni json qilib jo'natamiz, backend decode qiladi
    
    const payloadData = {
        action: warehouseForm.value.action,
        name: warehouseForm.value.name,
        category: warehouseForm.value.category,
        quantity: warehouseForm.value.quantity,
        unit: warehouseForm.value.unit,
        attachment_type: warehouseForm.value.attachment_type
    };
    
    formData.append('payload', JSON.stringify(payloadData));

    if (warehouseForm.value.attachment_type === 'photo' && warehouseForm.value.invoice_photo) {
        formData.append('invoice_photo', warehouseForm.value.invoice_photo);
    }

    router.post(route('operator.requests.store'), formData, {
        forceFormData: true,
        onSuccess: () => {
            closeWarehouseModal();
        }
    });
}

</script>

<template>
    <Head title="Operator Action Hub" />
    <OperatorLayout>
        
        <!-- UNIVERSAL DOCK NAVIGATION -->
        <div class="fixed bottom-4 sm:bottom-8 left-1/2 -translate-x-1/2 z-50 w-[95%] max-w-2xl px-4 py-3 glass-panel border border-white/10 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] backdrop-blur-xl">
            <div class="flex items-center justify-between relative">
                
                <!-- Left Side -->
                <div class="flex gap-2 sm:gap-4 pl-2">
                    <button @click="activeTab = 0" class="flex flex-col items-center justify-center transition-all" :class="activeTab === 0 ? 'text-neon-cyan drop-shadow-[0_0_10px_rgba(0,240,255,0.8)]' : 'text-text-muted hover:text-white'">
                        <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span class="text-[8px] font-mono uppercase">Asosiy</span>
                    </button>
                    
                    <button @click="activeTab = 1" class="flex flex-col items-center justify-center transition-all" :class="activeTab === 1 ? 'text-neon-yellow drop-shadow-[0_0_10px_rgba(252,238,9,0.8)]' : 'text-text-muted hover:text-white'">
                        <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span class="text-[8px] font-mono uppercase">Sotuv</span>
                    </button>

                    <button @click="activeTab = 2" class="flex flex-col items-center justify-center transition-all" :class="activeTab === 2 ? 'text-neon-magenta drop-shadow-[0_0_10px_rgba(255,0,60,0.8)]' : 'text-text-muted hover:text-white'">
                        <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span class="text-[8px] font-mono uppercase">Hujjat</span>
                    </button>
                </div>

                <!-- Center FAB (Quick Add) -->
                <button class="absolute left-1/2 -top-6 -translate-x-1/2 w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-gradient-to-tr from-neon-cyan to-neon-green text-black flex items-center justify-center shadow-[0_10px_25px_rgba(0,240,255,0.6)] border-4 border-[#111111] hover:scale-105 transition-transform group">
                    <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    
                    <!-- Hover Quick Menu -->
                    <div class="absolute bottom-[120%] left-1/2 -translate-x-1/2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all flex flex-col gap-2">
                        <button @click="activeTab = 3; openPaymentModal()" class="bg-black/80 border border-white/20 text-white text-xs px-3 py-1.5 rounded-lg whitespace-nowrap hover:bg-neon-cyan hover:text-black hover:border-neon-cyan transition-colors">Yangi To'lov</button>
                        <button @click="activeTab = 5; warehouseForm.action = 'kirim'; warehouseForm.attachment_type = 'qr'; showWarehouseModal = true" class="bg-black/80 border border-white/20 text-white text-xs px-3 py-1.5 rounded-lg whitespace-nowrap hover:bg-neon-green hover:text-black hover:border-neon-green transition-colors">Qabul qilish (QR)</button>
                    </div>
                </button>

                <!-- Right Side -->
                <div class="flex gap-2 sm:gap-4 pr-2">
                    <button @click="activeTab = 3" class="flex flex-col items-center justify-center transition-all" :class="activeTab === 3 ? 'text-neon-green drop-shadow-[0_0_10px_rgba(57,255,20,0.8)]' : 'text-text-muted hover:text-white'">
                        <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-[8px] font-mono uppercase">Kassa</span>
                    </button>
                    
                    <button @click="activeTab = 4" class="flex flex-col items-center justify-center transition-all" :class="activeTab === 4 ? 'text-neon-red drop-shadow-[0_0_10px_rgba(255,0,0,0.8)]' : 'text-text-muted hover:text-white'">
                        <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span class="text-[8px] font-mono uppercase">Kadrlar</span>
                    </button>

                    <button @click="activeTab = 5" class="flex flex-col items-center justify-center transition-all" :class="activeTab === 5 ? 'text-neon-cyan drop-shadow-[0_0_10px_rgba(0,240,255,0.8)]' : 'text-text-muted hover:text-white'">
                        <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <span class="text-[8px] font-mono uppercase">Ombor</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- TAB 0: DASHBOARD -->
        <div v-if="activeTab === 0" class="space-y-6 animate-[fadeIn_0.5s_ease-out] pb-24">
            <h2 class="text-xl font-display font-bold text-white tracking-widest uppercase">Tezkor Holat</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="glass-card p-4 rounded-xl border border-white/5">
                    <p class="text-[10px] font-mono text-text-muted uppercase">Sotilgan Uylar</p>
                    <p class="text-2xl font-bold text-neon-cyan mt-1">{{ contracts.length }} <span class="text-xs text-white">ta</span></p>
                </div>
                <div class="glass-card p-4 rounded-xl border border-white/5">
                    <p class="text-[10px] font-mono text-text-muted uppercase">Mijozlar</p>
                    <p class="text-2xl font-bold text-neon-yellow mt-1">{{ clients.length }} <span class="text-xs text-white">ta</span></p>
                </div>
                <div class="glass-card p-4 rounded-xl border border-white/5">
                    <p class="text-[10px] font-mono text-text-muted uppercase">Jami Sotuv Miqdori</p>
                    <p class="text-lg font-bold text-neon-green mt-2 truncate">{{ $currency(totalSalesValue) }}</p>
                </div>
                <div class="glass-card p-4 rounded-xl border border-white/5">
                    <p class="text-[10px] font-mono text-text-muted uppercase">Ishchilar / Ustalar</p>
                    <p class="text-2xl font-bold text-neon-magenta mt-1">{{ workers.length }} <span class="text-xs text-white">nafar</span></p>
                </div>
            </div>

            <div class="glass-panel p-6 rounded-2xl border border-white/5">
                <h3 class="text-sm font-mono text-text-muted uppercase tracking-widest mb-4">Moliyaviy va Moddiy oqim</h3>
                <VueApexCharts type="area" height="250" :options="areaOptions" :series="areaSeries" />
            </div>
        </div>

        <!-- TAB 1: SOTUV VA MIJOZLAR (Interactive Blueprints) -->
        <div v-if="activeTab === 1" class="space-y-6 animate-[fadeIn_0.5s_ease-out] pb-24 h-[calc(100vh-8rem)]">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-display font-bold text-white tracking-widest uppercase">Sotuv Chizmalari</h2>
                <button @click="openClientModal" class="bg-white/10 text-white text-xs px-4 py-2 rounded border border-white/20 hover:bg-white/20">Yangi Mijoz</button>
            </div>
            
            <div class="flex flex-col md:flex-row gap-6 h-full">
                <!-- Left Side: Building List -->
                <div class="w-full md:w-1/4 flex flex-row md:flex-col gap-4 overflow-x-auto md:overflow-y-auto pb-2 md:pb-0 pr-0 md:pr-2 custom-scrollbar shrink-0">
                    <div 
                        v-for="building in buildings" 
                        :key="building.id"
                        @click="selectedBuilding = building"
                        :class="[
                            'glass-card p-4 cursor-pointer transition-all duration-300 border hover:border-neon-cyan/50 shrink-0',
                            selectedBuilding?.id === building.id ? 'border-neon-cyan bg-neon-cyan/5 shadow-[0_0_15px_rgba(0,240,255,0.2)]' : 'border-white/10'
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <div :class="['w-10 h-10 rounded-lg flex items-center justify-center border transition-all', selectedBuilding?.id === building.id ? 'bg-neon-cyan/20 border-neon-cyan text-neon-cyan shadow-[0_0_10px_rgba(0,240,255,0.5)]' : 'bg-black/50 border-white/10 text-white/50']">
                                <span class="text-xl">🏗️</span>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-white">{{ building.name }}</h4>
                                <div class="flex items-center gap-2 mt-1">
                                    <span :class="['w-2 h-2 rounded-full', building.status === 'active' ? 'bg-neon-cyan animate-pulse shadow-[0_0_5px_#00F0FF]' : (building.status === 'completed' ? 'bg-neon-green' : 'bg-neon-yellow')]"></span>
                                    <p class="text-[10px] text-text-muted font-mono uppercase tracking-wider">{{ building.status === 'active' ? 'Faol' : (building.status === 'completed' ? 'Tugallangan' : 'Loyihalash') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="buildings.length === 0" class="glass-card p-6 text-center text-text-muted text-sm font-mono border-dashed border-white/20 whitespace-nowrap min-w-[200px] flex-1">
                        Binolar mavjud emas
                    </div>
                </div>

                <!-- Right Side: Blueprint Center -->
                <div class="flex-1 flex flex-col glass-panel rounded-2xl overflow-hidden relative w-full h-[60vh] md:h-auto">
                    <div v-if="selectedBuilding" class="flex flex-col h-full">
                        
                        <!-- Header -->
                        <div class="p-6 border-b border-white/10 flex flex-col justify-between items-start gap-2 relative overflow-hidden bg-black/40">
                            <div class="absolute -right-20 -top-20 w-64 h-64 bg-neon-cyan/5 blur-[100px] rounded-full pointer-events-none"></div>
                            <h2 class="text-2xl font-display font-bold text-white drop-shadow-[0_0_10px_rgba(255,255,255,0.2)]">
                                {{ selectedBuilding.name }} <span class="text-neon-cyan">Chizmalari</span>
                            </h2>
                            <p class="text-xs text-text-muted font-mono flex flex-wrap items-center gap-2">
                                <span>📍 {{ [selectedBuilding.city, selectedBuilding.district, selectedBuilding.mahalla, selectedBuilding.street].filter(Boolean).join(', ') || 'Manzil kiritilmagan' }}</span>
                                <span v-if="selectedBuilding.block_name || selectedBuilding.corpus_name" class="text-white">| 🏢 {{ [selectedBuilding.block_name, selectedBuilding.corpus_name].filter(Boolean).join(' - ') }}</span>
                                <span class="text-neon-yellow">| 📏 {{ selectedBuilding.total_floors }} qavat</span>
                            </p>
                        </div>

                        <!-- Content Area -->
                        <div class="flex-1 p-6 flex flex-col relative overflow-hidden">
                            <!-- Floor Selector -->
                            <div class="flex justify-between items-center mb-4">
                                <div class="flex gap-2 bg-black/40 p-1 rounded-lg border border-white/10 overflow-x-auto custom-scrollbar max-w-full">
                                    <button 
                                        v-for="floor in selectedBuilding.floors" 
                                        :key="floor.id"
                                        @click="selectedFloor = floor"
                                        :class="['px-4 py-2 text-xs font-mono font-bold uppercase rounded whitespace-nowrap transition-all', selectedFloor?.id === floor.id ? 'bg-neon-cyan text-black shadow-[0_0_10px_rgba(0,240,255,0.4)]' : 'text-text-muted hover:bg-white/5']"
                                    >
                                        {{ floor.floor_number }}-QAVAT
                                    </button>
                                    <button v-if="!selectedBuilding.floors.length" class="px-4 py-2 text-xs font-mono text-text-muted italic">
                                        Qavatlar mavjud emas
                                    </button>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4">
                                <div class="flex gap-2 text-[10px] sm:gap-4 font-mono bg-black/40 p-2 rounded-lg inline-flex flex-wrap border border-white/10">
                                    <span class="flex items-center gap-1 sm:gap-2"><span class="w-2 h-2 sm:w-3 sm:h-3 rounded-sm bg-neon-green/30 border border-neon-green shadow-[0_0_5px_#39FF14]"></span> Bo'sh</span>
                                    <span class="flex items-center gap-1 sm:gap-2"><span class="w-2 h-2 sm:w-3 sm:h-3 rounded-sm bg-neon-yellow/30 border border-neon-yellow shadow-[0_0_5px_#FCEE09]"></span> Band qilingan</span>
                                    <span class="flex items-center gap-1 sm:gap-2"><span class="w-2 h-2 sm:w-3 sm:h-3 rounded-sm bg-neon-magenta/30 border border-neon-magenta shadow-[0_0_5px_#FF003C]"></span> Sotilgan</span>
                                </div>
                                <div class="text-[10px] sm:text-xs font-mono text-neon-cyan animate-pulse whitespace-nowrap">
                                    Xonadon ustiga bosib sotuvni boshlang
                                </div>
                            </div>
                            
                            <div class="flex-1 relative border border-white/10 rounded-xl overflow-hidden bg-black/50 group flex items-start p-2 sm:p-6">
                                <!-- Blueprint Mode (SVG) -->
                                <svg v-if="selectedFloor && selectedFloor.blueprint_path" class="w-full h-full absolute inset-0 z-10" viewBox="0 0 1000 600" preserveAspectRatio="xMidYMid meet">
                                    <g v-for="(unit, idx) in selectedFloor.units" :key="'u-'+unit.id" class="cursor-pointer transition-all duration-300 hover:brightness-150" @mousemove="(e) => onUnitHover(unit, e)" @mouseleave="onUnitLeave" @click="unit.status === 'available' || unit.status === 'booked' ? openUnitActionModal(unit) : null">
                                        <polygon v-if="unit.svg_polygon" :points="unit.svg_polygon" :fill="getUnitFillColor(unit.status)" :stroke="getUnitStrokeColor(unit.status)" stroke-width="2" />
                                        <polygon v-else :points="getFallbackPolygon(unit)" :fill="getUnitFillColor(unit.status)" :stroke="getUnitStrokeColor(unit.status)" stroke-width="2" />
                                        <text v-if="!unit.svg_polygon" :x="getFallbackTextX(unit)" :y="getFallbackTextY(unit)" text-anchor="middle" fill="white" class="font-mono text-sm font-bold pointer-events-none drop-shadow-md">{{ unit.unit_number }}</text>
                                    </g>
                                </svg>
                                
                                <!-- Fallback Mode (HTML Grid) -->
                                <div v-else-if="selectedFloor && !selectedFloor.blueprint_path" class="w-full h-full flex flex-col gap-6 overflow-y-auto overflow-x-hidden custom-scrollbar relative z-20">
                                    <div v-if="residentialUnits.length" class="flex flex-col sm:flex-row items-stretch min-h-[100px]">
                                        <div class="w-full sm:w-32 shrink-0 sm:border-r-2 border-white/10 sm:pr-6 flex flex-row sm:flex-col items-center justify-start sm:items-end sm:justify-center py-2 gap-2 border-b-2 sm:border-b-0 mb-4 sm:mb-0">
                                            <span class="text-2xl drop-shadow-[0_0_10px_rgba(0,240,255,0.5)]">🏠</span>
                                            <h3 class="text-neon-cyan font-display text-sm tracking-widest uppercase text-left sm:text-right">Xonadonlar</h3>
                                        </div>
                                        <div class="flex-1 sm:pl-6 pb-2">
                                            <div class="flex flex-wrap gap-4">
                                                <div v-for="unit in residentialUnits" :key="'res-'+unit.id" 
                                                     class="w-16 h-16 sm:w-20 sm:h-20 flex items-center justify-center border-2 cursor-pointer transition-all hover:scale-110 hover:brightness-125 rounded shadow-lg"
                                                     :class="getUnitBorderColor(unit.status)"
                                                     :style="{ backgroundColor: getUnitFillColor(unit.status) }"
                                                     @mousemove="(e) => onUnitHover(unit, e)"
                                                     @mouseleave="onUnitLeave"
                                                     @click="unit.status === 'available' || unit.status === 'booked' ? openUnitActionModal(unit) : null">
                                                    <span class="font-mono text-xs sm:text-sm font-bold pointer-events-none" :class="getUnitTextColor(unit.status)">{{ unit.unit_number }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div v-if="residentialUnits.length && commercialUnits.length" class="flex">
                                        <div class="w-32 shrink-0 border-r-2 border-white/10"></div>
                                        <div class="flex-1 pl-6 flex items-center">
                                            <div class="h-px w-full bg-white/10 border-b border-dashed border-white/20"></div>
                                        </div>
                                    </div>

                                    <div v-if="commercialUnits.length" class="flex flex-col sm:flex-row items-stretch min-h-[100px]">
                                        <div class="w-full sm:w-32 shrink-0 sm:border-r-2 border-white/10 sm:pr-6 flex flex-row sm:flex-col items-center justify-start sm:items-end sm:justify-center py-2 gap-2 border-b-2 sm:border-b-0 mb-4 sm:mb-0">
                                            <span class="text-2xl drop-shadow-[0_0_10px_rgba(255,0,60,0.5)]">🏬</span>
                                            <h3 class="text-neon-magenta font-display text-sm tracking-widest uppercase text-left sm:text-right">Do'konlar</h3>
                                        </div>
                                        <div class="flex-1 sm:pl-6 pb-2">
                                            <div class="flex flex-wrap gap-4">
                                                <div v-for="unit in commercialUnits" :key="'com-'+unit.id" 
                                                     class="w-16 h-16 sm:w-20 sm:h-20 flex items-center justify-center border-2 cursor-pointer transition-all hover:scale-110 hover:brightness-125 rounded shadow-lg"
                                                     :class="getUnitBorderColor(unit.status)"
                                                     :style="{ backgroundColor: getUnitFillColor(unit.status) }"
                                                     @mousemove="(e) => onUnitHover(unit, e)"
                                                     @mouseleave="onUnitLeave"
                                                     @click="unit.status === 'available' || unit.status === 'booked' ? openUnitActionModal(unit) : null">
                                                    <span class="font-mono text-xs sm:text-sm font-bold pointer-events-none" :class="getUnitTextColor(unit.status)">{{ unit.unit_number }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="selectedFloor && !selectedFloor.units?.length" class="absolute inset-0 m-auto h-max w-max z-30 text-neon-cyan font-mono text-sm border border-dashed border-neon-cyan/50 p-8 rounded-xl bg-black/80 backdrop-blur-md">
                                    [ QAVAT UCHUN XONADONLAR YO'Q ]
                                </div>
                            </div>
                            
                            <!-- Unit Tooltip -->
                            <Teleport to="body">
                                <div v-if="hoveredUnit" 
                                     class="fixed z-50 pointer-events-none bg-black/90 backdrop-blur-md border border-white/20 p-4 rounded-xl shadow-[0_0_20px_rgba(0,0,0,0.8)]"
                                     :style="{ top: (mouseY + 15) + 'px', left: (mouseX + 15) + 'px' }">
                                    <h4 class="text-neon-cyan font-display text-lg mb-2 flex items-center gap-2">
                                        {{ hoveredUnit.unit_number }} 
                                        <span class="text-[10px] font-mono bg-white/10 px-2 py-0.5 rounded text-white">{{ hoveredUnit.type === 'commercial' ? "Do'kon" : 'Xonadon' }}</span>
                                    </h4>
                                    <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-xs font-mono">
                                        <span class="text-text-muted">Maydoni:</span>
                                        <span class="text-white text-right">{{ hoveredUnit.area_sqm }} m²</span>
                                        
                                        <span class="text-text-muted">Xonalar:</span>
                                        <span class="text-white text-right">{{ hoveredUnit.rooms_count || 1 }} xona</span>
                                        
                                        <span class="text-text-muted">Joylashuv:</span>
                                        <span class="text-white text-right">{{ hoveredUnit.orientation || 'Noma\'lum' }}</span>
                                        
                                        <span class="text-text-muted">Narxi:</span>
                                        <span class="text-neon-green text-right">{{ $currency(hoveredUnit.price * hoveredUnit.area_sqm) }}</span>
                                        
                                        <span class="text-text-muted">Status:</span>
                                        <span :class="['text-right', getUnitTextColor(hoveredUnit.status)]">{{ hoveredUnit.status === 'available' ? 'Bo\'sh' : (hoveredUnit.status === 'reserved' ? 'Band' : 'Sotilgan') }}</span>
                                    </div>
                                    <p v-if="hoveredUnit.status === 'available' || hoveredUnit.status === 'booked'" class="text-[10px] text-text-muted mt-3 pt-2 border-t border-white/10 text-center animate-pulse">Sotish uchun bosing</p>
                                </div>
                            </Teleport>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: SHARTNOMALAR -->
        <div v-if="activeTab === 2" class="space-y-6 animate-[fadeIn_0.5s_ease-out] pb-24">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-display font-bold text-white tracking-widest uppercase">Shartnomalar</h2>
                <button @click="openContractModal" class="bg-neon-magenta text-white text-xs px-4 py-2 rounded hover:shadow-[0_0_15px_#FF003C] transition-all">Shartnoma tuzish</button>
            </div>

            <div class="glass-panel p-6 rounded-2xl border border-white/5">
                <div class="space-y-3">
                    <div v-for="contract in contracts" :key="contract.id" class="glass-card p-4 rounded-xl border border-white/5 flex justify-between items-center hover:bg-white/5 transition-colors cursor-pointer" @click="openContractDetailsModal(contract)">
                        <div>
                            <p class="text-sm font-bold text-white">{{ contract.client?.first_name }} {{ contract.client?.last_name }}</p>
                            <p class="text-xs font-mono text-text-muted">Unit: {{ contract.unit?.unit_number }} | {{ $currency(contract.total_amount) }}</p>
                        </div>
                        <span class="text-[10px] font-mono uppercase bg-white/10 px-2 py-1 rounded text-white">{{ contract.status }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: TO'LOVLAR -->
        <div v-if="activeTab === 3" class="space-y-6 animate-[fadeIn_0.5s_ease-out] pb-24">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-display font-bold text-white tracking-widest uppercase">To'lovlar Kassasi</h2>
                <div class="flex gap-2">
                    <button @click="openExpenseModal" class="bg-neon-red/10 border border-neon-red text-neon-red px-4 py-2 rounded hover:bg-neon-red hover:text-white transition-colors text-xs font-bold shadow-[0_0_10px_rgba(255,0,0,0.2)]">Xarajat (Chiqim)</button>
                    <button @click="openPaymentModal()" class="bg-neon-green text-black px-4 py-2 rounded hover:shadow-[0_0_15px_#39FF14] text-xs font-bold">Yangi to'lov qo'shish</button>
                </div>
            </div>

            <div class="glass-panel p-6 rounded-2xl border border-white/5">
                <input v-model="paymentSearchQuery" type="text" placeholder="Mijoz ismi, pasport seriyasi yoki uy raqami orqali qidiring..." class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white font-medium focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-colors mb-6">
                
                <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-2">
                    <div v-for="contract in filteredContractsForPayment" :key="contract.id" class="glass-card p-4 rounded-xl border border-white/5 flex flex-col md:flex-row justify-between items-center gap-4 hover:border-white/20 transition-colors">
                        <div>
                            <p class="text-sm font-bold text-white">{{ contract.client?.first_name }} {{ contract.client?.last_name }} <span class="text-[10px] text-text-muted ml-2">Pasport: {{ contract.client?.passport_number || 'N/A' }}</span></p>
                            <p class="text-xs font-mono text-neon-cyan mt-1">Unit #{{ contract.unit?.unit_number }} <span class="text-text-muted mx-2">|</span> Umumiy Summa: {{ $currency(contract.total_amount) }}</p>
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="text-[10px] font-mono uppercase px-2 py-1 rounded mb-2" :class="contract.status === 'active' ? 'bg-neon-green/20 text-neon-green' : 'bg-white/10 text-white'">{{ contract.status }}</span>
                            <button @click="openPaymentModal(contract.id)" class="text-xs border border-neon-green text-neon-green px-4 py-1.5 rounded hover:bg-neon-green hover:text-black transition-colors">To'lov qabul qilish</button>
                        </div>
                    </div>
                    <div v-if="filteredContractsForPayment.length === 0" class="text-center py-8 text-text-muted text-sm">
                        Hech narsa topilmadi...
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4: XODIMLAR VA HR -->
        <div v-if="activeTab === 4" class="space-y-6 animate-[fadeIn_0.5s_ease-out] pb-24">
            <h2 class="text-xl font-display font-bold text-white tracking-widest uppercase">HR va Ish Haqi</h2>

            <!-- HR Quick Actions (Blocks) -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <button @click="openHrModal('yollanma')" class="glass-card p-4 rounded-xl border border-neon-cyan/20 hover:border-neon-cyan hover:shadow-[0_0_15px_#00F0FF] transition-all flex flex-col items-center justify-center gap-3 group">
                    <div class="w-12 h-12 rounded-full bg-neon-cyan/10 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">👷</div>
                    <span class="text-xs font-mono font-bold text-white uppercase text-center">Yollanma Ishchilar</span>
                </button>
                <button @click="openHrModal('faceid')" class="glass-card p-4 rounded-xl border border-neon-magenta/20 hover:border-neon-magenta hover:shadow-[0_0_15px_#FF003C] transition-all flex flex-col items-center justify-center gap-3 group">
                    <div class="w-12 h-12 rounded-full bg-neon-magenta/10 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">👁️</div>
                    <span class="text-xs font-mono font-bold text-white uppercase text-center">Face-ID Markazi</span>
                </button>
                <button @click="openHrModal('davomat')" class="glass-card p-4 rounded-xl border border-neon-yellow/20 hover:border-neon-yellow hover:shadow-[0_0_15px_#FCEE09] transition-all flex flex-col items-center justify-center gap-3 group">
                    <div class="w-12 h-12 rounded-full bg-neon-yellow/10 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">⏱️</div>
                    <span class="text-xs font-mono font-bold text-white uppercase text-center">Davomat Jurnali</span>
                </button>
                <button @click="openHrModal('texnika')" class="glass-card p-4 rounded-xl border border-[#FF9900]/20 hover:border-[#FF9900] hover:shadow-[0_0_15px_#FF9900] transition-all flex flex-col items-center justify-center gap-3 group">
                    <div class="w-12 h-12 rounded-full bg-[#FF9900]/10 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">🚜</div>
                    <span class="text-xs font-mono font-bold text-white uppercase text-center">Og'ir Texnika</span>
                </button>
            </div>

            <!-- Tabel va Jurnallar -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Workers List -->
                <div class="glass-panel p-6 rounded-2xl border border-white/5">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-sm font-mono text-text-muted uppercase tracking-widest flex items-center gap-2">
                            <span>👷</span> Yollanma Ishchilar
                        </h3>
                    </div>
                    
                    <div class="space-y-3 max-h-[400px] overflow-y-auto custom-scrollbar pr-2">
                        <!-- PENDING WORKERS (from ApprovalRequests) -->
                        <div v-for="req in pendingRequests?.filter(r => r.type === 'yollanma')" :key="'req_'+req.id" class="glass-card p-4 rounded-xl border border-white/5 flex items-center justify-between opacity-70">
                            <div class="flex items-center gap-4">
                                <div class="relative w-10 h-10 rounded-full bg-white/10 overflow-hidden border border-white/20 flex items-center justify-center">
                                    <span class="text-lg">👷</span>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white">{{ req.payload?.name || 'Noma\'lum' }}</p>
                                    <p class="text-[10px] font-mono text-text-muted uppercase">{{ req.payload?.role || 'Yangi Ishchi' }}</p>
                                </div>
                            </div>
                            <span class="text-[9px] px-2 py-1 bg-neon-yellow/20 text-neon-yellow rounded uppercase font-mono border border-neon-yellow/30">Kutilmoqda</span>
                        </div>

                        <!-- ACTIVE WORKERS -->
                        <div v-for="worker in workers" :key="worker.id" class="glass-card p-4 rounded-xl border border-white/5 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="relative w-10 h-10 rounded-full bg-white/10 overflow-hidden border border-white/20 flex items-center justify-center">
                                    <img v-if="worker.photo_path" :src="'/storage/' + worker.photo_path" class="w-full h-full object-cover">
                                    <span v-else class="text-lg">👷</span>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white">{{ worker.name }}</p>
                                    <p class="text-[10px] font-mono text-text-muted uppercase">{{ worker.role || 'Yollanma Ishchi' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-[9px] px-2 py-1 bg-neon-green/20 text-neon-green rounded uppercase font-mono border border-neon-green/30">Faol</span>
                                <button @click="openWorkerActionModal(worker)" class="text-xs bg-white/10 hover:bg-white/20 text-white px-3 py-1.5 rounded transition-colors">Boshqarish</button>
                            </div>
                        </div>
                        <div v-if="workers?.length === 0 && !pendingRequests?.find(r => r.type === 'yollanma')" class="text-center py-8 border border-dashed border-white/10 rounded-xl">
                            <p class="text-xs font-mono text-text-muted uppercase">Ishchilar ro'yxati bo'sh</p>
                        </div>
                    </div>
                </div>

                <!-- Machinery List -->
                <div class="glass-panel p-6 rounded-2xl border border-white/5">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-sm font-mono text-text-muted uppercase tracking-widest flex items-center gap-2">
                            <span>🚜</span> Og'ir Texnikalar
                        </h3>
                    </div>
                    
                    <div class="space-y-3 max-h-[400px] overflow-y-auto custom-scrollbar pr-2">
                        <!-- PENDING MACHINERIES -->
                        <div v-for="req in pendingRequests?.filter(r => r.type === 'texnika')" :key="'req_m_'+req.id" class="glass-card p-4 rounded-xl border border-white/5 flex items-center justify-between opacity-70">
                            <div class="flex items-center gap-4">
                                <div class="relative w-10 h-10 rounded-full bg-[#FF9900]/10 border border-[#FF9900]/30 flex items-center justify-center">
                                    <span class="text-lg">🚜</span>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white">{{ req.payload?.name || 'Noma\'lum' }}</p>
                                    <p class="text-[10px] font-mono text-text-muted uppercase">{{ req.payload?.license_plate || 'Raqamsiz' }}</p>
                                </div>
                            </div>
                            <span class="text-[9px] px-2 py-1 bg-neon-yellow/20 text-neon-yellow rounded uppercase font-mono border border-neon-yellow/30">Kutilmoqda</span>
                        </div>

                        <!-- ACTIVE MACHINERIES -->
                        <div v-for="machinery in machineries" :key="machinery.id" class="glass-card p-4 rounded-xl border border-white/5 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="relative w-10 h-10 rounded-full bg-[#FF9900]/10 border border-[#FF9900]/30 flex items-center justify-center">
                                    <span class="text-lg">🚜</span>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white">{{ machinery.name }}</p>
                                    <p class="text-[10px] font-mono text-text-muted uppercase">{{ machinery.license_plate || 'Raqamsiz' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-[9px] px-2 py-1 bg-neon-green/20 text-neon-green rounded uppercase font-mono border border-neon-green/30">Faol</span>
                            </div>
                        </div>
                        <div v-if="machineries?.length === 0 && !pendingRequests?.find(r => r.type === 'texnika')" class="text-center py-8 border border-dashed border-white/10 rounded-xl">
                            <p class="text-xs font-mono text-text-muted uppercase">Texnikalar ro'yxati bo'sh</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 5: OMBORXONA -->
        <div v-if="activeTab === 5" class="space-y-6 animate-[fadeIn_0.5s_ease-out] pb-24">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-display font-bold text-white tracking-widest uppercase">Omborxona</h2>
                <div class="flex gap-2">
                    <button @click="openWarehouseModal" class="bg-neon-cyan text-black font-bold text-xs px-3 py-2 rounded shadow-[0_0_10px_#00F0FF] hover:shadow-[0_0_20px_#00F0FF] transition-all">Kirim / Chiqim</button>
                </div>
            </div>

            <div class="glass-panel rounded-2xl border border-white/5 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-white/10 bg-white/5">
                            <th class="p-4 text-xs font-mono text-text-muted uppercase">Nomi</th>
                            <th class="p-4 text-xs font-mono text-text-muted uppercase">Qoldiq</th>
                            <th class="p-4 text-xs font-mono text-text-muted uppercase">Kategoriya</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in inventory" :key="item.id" class="border-b border-white/5 hover:bg-white/5">
                            <td class="p-4 text-sm text-white font-bold">{{ item.name }}</td>
                            <td class="p-4 text-sm text-neon-yellow">{{ item.quantity }} {{ item.unit }}</td>
                            <td class="p-4 text-xs font-mono text-text-muted">{{ item.category }}</td>
                        </tr>
                        <tr v-if="inventory.length === 0">
                            <td colspan="3" class="p-8 text-center text-text-muted font-mono text-sm">Ombor bo'sh</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- HR MODAL -->
        <div v-if="showHrModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
            <div class="glass-panel w-full max-w-md p-6 rounded-2xl border border-neon-cyan/50 shadow-[0_0_30px_rgba(0,240,255,0.15)] relative">
                <button @click="closeHrModal" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
                
                <h2 class="text-xl font-display font-bold text-white mb-4 uppercase">
                    <span v-if="activeHrModalType === 'yollanma'">Yangi Ishchi Qo'shish</span>
                    <span v-else-if="activeHrModalType === 'faceid'">Face-ID Skaner</span>
                    <span v-else-if="activeHrModalType === 'davomat'">Davomat Skaneri</span>
                    <span v-else-if="activeHrModalType === 'texnika'">Og'ir Texnika Kiritish</span>
                </h2>

                <div v-if="activeHrModalType === 'yollanma'" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">F.I.SH</label>
                        <input v-model="hrForm.name" type="text" class="glass-input w-full" placeholder="Masalan: Aliyev Vali">
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Kasbi (Vazifasi)</label>
                        <input v-model="hrForm.role" type="text" class="glass-input w-full" placeholder="Suvaqchi / G'isht teruvchi">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Ish haqi turi</label>
                            <select v-model="hrForm.type" class="glass-input w-full bg-black/50 text-white">
                                <option value="fixed">Oylik (Fixed)</option>
                                <option value="piece_rate">Baybay (Hajmga)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">O'lchov Birligi</label>
                            <select v-if="hrForm.type === 'piece_rate'" v-model="hrForm.metric_type" class="glass-input w-full bg-black/50 text-white">
                                <option value="kvadrat">1 Kvadrat (kv.m)</option>
                                <option value="dona">1 Dona</option>
                                <option value="kub">1 Kub (m³)</option>
                                <option value="reys">1 Reys</option>
                                <option value="soat">1 Soat</option>
                            </select>
                            <input v-else type="text" disabled class="glass-input w-full bg-black/20 opacity-50 cursor-not-allowed" placeholder="-">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Stavka Summasi ($ yoki so'm)</label>
                        <input v-model="hrForm.rate" type="number" class="glass-input w-full" placeholder="Masalan: 10000">
                    </div>
                </div>

                <div v-if="activeHrModalType === 'texnika'" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Texnika Nomi / Yo'nalishi</label>
                            <input v-model="hrForm.name" type="text" class="glass-input w-full" placeholder="Masalan: Ekskavator">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Modeli / Rusumi</label>
                            <input v-model="hrForm.model" type="text" class="glass-input w-full" placeholder="Kamaz, Samsung...">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Egasi (Arendator)</label>
                            <input v-model="hrForm.owner_name" type="text" class="glass-input w-full" placeholder="Ism yoki Kompaniya">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Davlat Raqami</label>
                            <input v-model="hrForm.license_plate" type="text" class="glass-input w-full" placeholder="01 A 123 AA">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">To'lov Turi</label>
                            <select v-model="hrForm.payment_type" class="glass-input w-full bg-black/50 text-white">
                                <option value="hourly">Soatbay (Hourly)</option>
                                <option value="volume">Hajmga (Volume)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Narxi (Soatiga/Hajmiga)</label>
                            <input v-model="hrForm.rate" type="number" class="glass-input w-full" placeholder="Summa">
                        </div>
                    </div>
                </div>

                <div v-if="activeHrModalType === 'faceid'" class="py-6 flex flex-col items-center space-y-4">
                    <div class="w-full mb-4">
                        <label class="block text-[10px] font-mono text-text-muted uppercase mb-1 text-left">Skaner qilinayotgan Xodim</label>
                        <select v-model="hrForm.worker_id" class="glass-input w-full bg-black/50 text-white">
                            <option value="">Tanlang</option>
                            <option v-for="w in workers" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div class="relative w-48 h-48 sm:w-64 sm:h-64 border-2 border-dashed border-neon-cyan/50 rounded-2xl flex items-center justify-center overflow-hidden bg-black/50">
                        <video ref="scannerVideo" autoplay playsinline class="w-full h-full object-cover"></video>
                        <div v-if="isScanning && !scannerVideo?.srcObject" class="absolute inset-0 bg-neon-cyan/20 animate-pulse"></div>
                        <div v-if="isScanning" class="absolute top-0 left-0 w-full h-1 bg-neon-cyan shadow-[0_0_10px_#00F0FF] animate-[scan_2s_ease-in-out_infinite]"></div>
                        <span v-if="!isScanning && !scannerVideo?.srcObject" class="text-4xl opacity-50">👤</span>
                    </div>
                    <p class="text-[10px] font-mono text-neon-cyan uppercase tracking-widest text-center px-4">
                        Kamera orqali yuzni ko'rsating va "Keldi" qilib yozing
                    </p>
                </div>

                <div v-if="activeHrModalType === 'davomat'" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Kim uchun (Turi)</label>
                            <select v-model="hrForm.type" class="glass-input w-full bg-black/50 text-white">
                                <option value="worker">Ishchi / Usta</option>
                                <option value="machinery">Texnika</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Shaxs / Texnika</label>
                            <select v-if="hrForm.type === 'worker'" v-model="hrForm.worker_id" class="glass-input w-full bg-black/50 text-white">
                                <option value="">Tanlang</option>
                                <option v-for="w in workers" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                            <select v-if="hrForm.type === 'machinery'" v-model="hrForm.machinery_id" class="glass-input w-full bg-black/50 text-white">
                                <option value="">Tanlang</option>
                                <option v-for="m in machineries" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Bino / Obyekt</label>
                            <select v-model="hrForm.building_id" class="glass-input w-full bg-black/50 text-white">
                                <option value="">Tanlang</option>
                                <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Sana</label>
                            <input v-model="hrForm.date" type="date" class="glass-input w-full bg-black/50 text-white">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Ishlagan Soati (Agar soatbay bo'lsa)</label>
                            <input v-model="hrForm.hours_worked" type="number" class="glass-input w-full" placeholder="Masalan: 8">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Bajarilgan Hajm (Agar hajmbay bo'lsa)</label>
                            <input v-model="hrForm.volume_done" type="number" class="glass-input w-full" placeholder="Masalan: 25.5">
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button @click="closeHrModal" class="px-4 py-2 text-sm font-mono text-text-muted hover:text-white transition-colors">Yopish</button>
                    <button v-if="activeHrModalType !== 'faceid' || !isScanning" @click="submitHrRequest" class="px-6 py-2 text-sm font-bold font-mono bg-neon-cyan text-black rounded hover:shadow-[0_0_15px_#00F0FF] transition-all">
                        {{ activeHrModalType === 'faceid' ? "Avtomat 'Keldi' qilish" : "Tasdiqqa Yuborish" }}
                    </button>
                </div>
            </div>
        </div>

        <!-- WAREHOUSE MODAL -->
        <div v-if="showWarehouseModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
            <div class="glass-panel w-full max-w-md p-6 rounded-2xl border border-neon-cyan/50 shadow-[0_0_30px_rgba(0,240,255,0.15)] relative">
                <button @click="closeWarehouseModal" class="absolute top-4 right-4 text-text-muted hover:text-white bg-black/50 w-8 h-8 rounded-full flex items-center justify-center border border-white/10 hover:bg-neon-red/20 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                
                <h3 class="text-lg font-display font-bold text-white uppercase tracking-widest mb-6 flex items-center gap-3">
                    <span class="text-2xl drop-shadow-[0_0_10px_rgba(0,240,255,0.8)]">📦</span> 
                    Omborxona Amaliyoti
                </h3>
                
                <form @submit.prevent="submitWarehouseRequest" class="space-y-4">
                    <!-- Action Type -->
                    <div class="flex gap-4">
                        <button type="button" @click="warehouseForm.action = 'kirim'" class="flex-1 py-3 rounded-xl border flex flex-col items-center gap-1 transition-all" :class="warehouseForm.action === 'kirim' ? 'border-neon-green bg-neon-green/10 shadow-[0_0_15px_rgba(57,255,20,0.2)]' : 'border-white/10 bg-black/40 text-text-muted hover:border-white/30'">
                            <svg class="w-6 h-6" :class="warehouseForm.action === 'kirim' ? 'text-neon-green' : 'currentColor'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                            <span class="font-mono text-xs uppercase" :class="warehouseForm.action === 'kirim' ? 'text-neon-green font-bold' : ''">Kirim qilish</span>
                        </button>
                        <button type="button" @click="warehouseForm.action = 'chiqim'" class="flex-1 py-3 rounded-xl border flex flex-col items-center gap-1 transition-all" :class="warehouseForm.action === 'chiqim' ? 'border-neon-red bg-neon-red/10 shadow-[0_0_15px_rgba(255,0,0,0.2)]' : 'border-white/10 bg-black/40 text-text-muted hover:border-white/30'">
                            <svg class="w-6 h-6" :class="warehouseForm.action === 'chiqim' ? 'text-neon-red' : 'currentColor'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/></svg>
                            <span class="font-mono text-xs uppercase" :class="warehouseForm.action === 'chiqim' ? 'text-neon-red font-bold' : ''">Chiqim qilish</span>
                        </button>
                    </div>

                    <!-- Item Details -->
                    <div class="space-y-4 mt-6">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Mahsulot Nomi</label>
                            <input v-model="warehouseForm.name" required type="text" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-2.5 text-white font-medium focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-colors" placeholder="Masalan: Sement M400">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Kategoriya</label>
                                <input v-model="warehouseForm.category" required type="text" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-2.5 text-white font-medium focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-colors" placeholder="Qurilish">
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Miqdori</label>
                                <div class="flex">
                                    <input v-model="warehouseForm.quantity" required type="number" step="0.01" class="w-2/3 bg-black/50 border border-white/10 rounded-l-xl px-4 py-2.5 text-white font-medium focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-colors border-r-0" placeholder="0">
                                    <select v-model="warehouseForm.unit" class="w-1/3 bg-black/50 border border-white/10 rounded-r-xl px-2 py-2.5 text-white font-medium focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-colors text-xs appearance-none">
                                        <option value="kg">kg</option>
                                        <option value="tonna">tonna</option>
                                        <option value="dona">dona</option>
                                        <option value="m">metr</option>
                                        <option value="m2">kv.m</option>
                                        <option value="m3">kub.m</option>
                                        <option value="litr">litr</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="warehouseForm.action === 'kirim'" class="mt-4">
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Umumiy Xarajat (Kassa uchun)</label>
                            <input v-model="warehouseForm.cost" type="number" step="0.01" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-2.5 text-white font-medium focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-colors" placeholder="Kiritilgan mahsulotlarning umumiy summasi (agar xarid qilingan bo'lsa)">
                        </div>
                    </div>

                    <!-- Attachment Options -->
                    <div class="mt-6 p-4 border border-white/10 rounded-xl bg-black/30">
                        <label class="block text-[10px] font-mono text-text-muted uppercase mb-3">Hujjat Asosi (Tasdiq)</label>
                        <div class="flex gap-4 mb-4">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="radio" v-model="warehouseForm.attachment_type" value="qr" class="text-neon-cyan focus:ring-neon-cyan bg-black border-white/20">
                                <span class="text-sm font-mono text-white group-hover:text-neon-cyan transition-colors">QR Chek</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="radio" v-model="warehouseForm.attachment_type" value="photo" class="text-neon-cyan focus:ring-neon-cyan bg-black border-white/20">
                                <span class="text-sm font-mono text-white group-hover:text-neon-cyan transition-colors">Shot Faktura Rasmi</span>
                            </label>
                        </div>
                        
                        <div v-if="warehouseForm.attachment_type === 'qr'" class="flex items-center justify-center p-6 border-2 border-dashed border-white/10 rounded-xl bg-black/50">
                            <button type="button" class="text-xs bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                QR Kodni Skanerlash
                            </button>
                        </div>
                        
                        <div v-if="warehouseForm.attachment_type === 'photo'" class="relative">
                            <input type="file" @change="handlePhotoUpload" accept="image/*" capture="environment" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" required>
                            <div class="flex flex-col items-center justify-center p-6 border-2 border-dashed border-neon-cyan/30 rounded-xl bg-neon-cyan/5 hover:bg-neon-cyan/10 transition-colors">
                                <svg v-if="!warehouseForm.invoice_photo" class="w-8 h-8 text-neon-cyan mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <svg v-else class="w-8 h-8 text-neon-green mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span v-if="!warehouseForm.invoice_photo" class="text-xs font-bold text-white text-center">Faktura rasmini olish uchun shu yerni bosing</span>
                                <span v-else class="text-xs font-bold text-neon-green text-center">Rasm yuklandi: {{ warehouseForm.invoice_photo.name }}</span>
                                <span v-if="!warehouseForm.invoice_photo" class="text-[9px] font-mono text-text-muted uppercase mt-1">(Telefon kamerasini ochadi)</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-gradient-to-r from-neon-cyan to-neon-purple text-white font-bold py-3.5 rounded-xl shadow-[0_0_15px_rgba(0,240,255,0.4)] hover:shadow-[0_0_25px_rgba(0,240,255,0.6)] hover:scale-[1.02] transition-all flex justify-center items-center gap-2 mt-2">
                        Adminga So'rov Yuborish
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </OperatorLayout>

    <!-- NEW MODALS -->
    <!-- Client Modal -->
    <div v-if="showClientModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
        <div class="glass-panel w-full max-w-md p-6 rounded-2xl border border-white/20 shadow-2xl relative">
            <button @click="showClientModal = false" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
            <h2 class="text-xl font-display font-bold text-white mb-4 uppercase">Yangi Mijoz</h2>
            <form @submit.prevent="submitClientForm" class="space-y-4">
                <input v-model="clientForm.first_name" required type="text" class="glass-input w-full" placeholder="Ism">
                <input v-model="clientForm.last_name" required type="text" class="glass-input w-full" placeholder="Familiya">
                <input v-model="clientForm.phone" required type="text" class="glass-input w-full" placeholder="Telefon">
                <input v-model="clientForm.passport" required type="text" class="glass-input w-full" placeholder="Pasport / JSHSHIR">
                <button type="submit" class="w-full bg-white/20 text-white font-bold py-2 rounded hover:bg-white/30 transition-colors">Saqlash</button>
            </form>
        </div>
    </div>

    <!-- Book Modal -->
    <div v-if="showBookModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
        <div class="glass-panel w-full max-w-md p-6 rounded-2xl border border-neon-cyan/50 shadow-[0_0_30px_rgba(0,240,255,0.15)] relative">
            <button @click="showBookModal = false" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
            <h2 class="text-xl font-display font-bold text-white mb-4 uppercase">Uyni Band Qilish</h2>
            <form @submit.prevent="submitBookForm" class="space-y-4">
                <select v-model="bookForm.client_id" required class="glass-input w-full bg-black/50 text-white">
                    <option value="" disabled>Mijozni tanlang</option>
                    <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.first_name }} {{ client.last_name }}</option>
                </select>
                <select v-model="bookForm.payment_type" required class="glass-input w-full bg-black/50 text-white">
                    <option value="cash">Naqd To'lov (100%)</option>
                    <option value="installment">Muddatli To'lov (Rassrochka)</option>
                </select>
                <input v-model="bookForm.initial_payment" required type="number" class="glass-input w-full" placeholder="Dastlabki to'lov summasi">
                <button type="submit" class="w-full bg-neon-cyan text-black font-bold py-2 rounded hover:shadow-[0_0_15px_#00F0FF] transition-colors">Band Qilish</button>
            </form>
        </div>
    </div>

    <!-- Contract Modal -->
    <div v-if="showContractModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
        <div class="glass-panel w-full max-w-md p-6 rounded-2xl border border-neon-magenta/50 shadow-[0_0_30px_rgba(255,0,60,0.15)] relative">
            <button @click="showContractModal = false" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
            <h2 class="text-xl font-display font-bold text-white mb-6 uppercase tracking-widest text-shadow-glow shadow-neon-magenta/50">
                Shartnoma Tuzish
            </h2>
            <form @submit.prevent="submitContractForm" class="space-y-4">
                <div>
                    <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Mijoz</label>
                    <select v-model="contractForm.client_id" class="glass-input w-full bg-black/50" required>
                        <option value="" disabled>Mijozni tanlang</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.first_name }} {{ c.last_name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Obyektni tanlang</label>
                    <select v-model="contractForm.unit_id" class="glass-input w-full bg-black/50" required>
                        <option value="" disabled>Uyni tanlang</option>
                        <option v-for="u in units" :key="u.id" :value="u.id">XONADON {{ u.unit_number }} - {{ u.floor?.building?.name }}</option>
                    </select>
                </div>

                <!-- Mulk Turi Selection -->
                <div>
                    <label class="block text-[10px] font-mono text-text-muted mb-2 uppercase">Mulk turi (Shartnoma shabloni)</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="contractForm.contract_type" value="residential" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                            <span class="text-xs text-white">Uy-joy (Kvartira)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="contractForm.contract_type" value="commercial" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                            <span class="text-xs text-white">Savdo do'koni</span>
                        </label>
                    </div>
                </div>

                <!-- Dynamic Unit Details -->
                <div v-if="selectedUnit" class="grid grid-cols-2 gap-4 p-3 border border-white/10 rounded-xl bg-black/30">
                    <div>
                        <p class="text-[10px] font-mono text-text-muted uppercase">Obyekt (Blok)</p>
                        <p class="text-xs text-white font-bold">{{ selectedUnit.floor?.building?.name || 'Noma\'lum' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-mono text-text-muted uppercase">Qavat</p>
                        <p class="text-xs text-white font-bold">{{ selectedUnit.floor?.number || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-mono text-text-muted uppercase">Xonalar soni</p>
                        <p class="text-xs text-white font-bold">{{ selectedUnit.rooms || 0 }} xona</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-mono text-text-muted uppercase">Umumiy maydoni</p>
                        <p class="text-xs text-white font-bold">{{ selectedUnit.area_sqm || 0 }} kv.m</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4" v-if="selectedUnit">
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Kvadrat metri narxi</label>
                        <input v-model="contractForm.price_per_sqm" type="number" step="any" class="glass-input w-full" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Umumiy narxi (QQS bilan)</label>
                        <input v-model="contractForm.total_amount" type="number" step="any" class="glass-input w-full" required>
                    </div>
                </div>

                <!-- Payment Type Selection -->
                <div>
                    <label class="block text-[10px] font-mono text-text-muted mb-2 uppercase">To'lov turi</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="contractForm.payment_type" value="full" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                            <span class="text-xs text-white">Bir martalik umumiy to'lov</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="contractForm.payment_type" value="installment" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                            <span class="text-xs text-white">Bo'lib to'lash</span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div v-if="contractForm.payment_type === 'installment'">
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Boshlang'ich to'lov</label>
                        <input v-model="contractForm.down_payment" type="number" step="any" class="glass-input w-full" required>
                    </div>
                    <div v-if="contractForm.payment_type === 'installment'">
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Muddat (Oy)</label>
                        <input v-model="contractForm.term_months" type="number" class="glass-input w-full" required>
                    </div>
                </div>

                <div class="flex justify-end gap-4 mt-8">
                    <button type="button" @click="showContractModal = false" class="px-4 py-2 text-xs font-mono uppercase tracking-widest text-text-muted hover:text-white transition-colors">Bekor qilish</button>
                    <button type="submit" class="px-6 py-2 bg-neon-green text-black font-bold uppercase tracking-widest rounded hover:shadow-[0_0_15px_#39FF14] transition-all">Savdo Hujjatini Yaratish</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Modal -->
    <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
        <div class="glass-panel w-full max-w-md p-6 rounded-2xl border border-neon-green/50 shadow-[0_0_30px_rgba(57,255,20,0.15)] relative">
            <button @click="showPaymentModal = false" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
            <h2 class="text-xl font-display font-bold text-white mb-4 uppercase">Yangi To'lov</h2>
            <form @submit.prevent="submitPaymentForm" class="space-y-4">
                <select v-model="paymentForm.contract_id" required class="glass-input w-full bg-black/50 text-white">
                    <option value="" disabled>Shartnomani tanlang</option>
                    <option v-for="contract in contracts" :key="contract.id" :value="contract.id">{{ contract.client?.first_name }} - Unit #{{ contract.unit?.unit_number }}</option>
                </select>
                <input v-model="paymentForm.amount" required type="number" class="glass-input w-full" placeholder="To'lov summasi">
                <select v-model="paymentForm.payment_method" required class="glass-input w-full bg-black/50 text-white">
                    <option value="cash">Naqd</option>
                    <option value="card">Karta (Terminal / Click)</option>
                    <option value="bank">Bank o'tkazmasi</option>
                </select>
                <textarea v-model="paymentForm.comment" class="glass-input w-full h-20" placeholder="Izoh (ixtiyoriy)"></textarea>
                <button type="submit" class="w-full bg-neon-green text-black font-bold py-2 rounded hover:shadow-[0_0_15px_#39FF14] transition-colors">To'lovni Yuborish</button>
            </form>
        </div>
    </div>

    <!-- EXPENSE (CHIQIM) MODAL -->
    <div v-if="showExpenseModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
        <div class="glass-panel w-full max-w-md p-6 rounded-2xl border border-neon-red/50 shadow-[0_0_30px_rgba(255,0,0,0.15)] relative">
            <button @click="closeExpenseModal" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
            
            <h2 class="text-xl font-display font-bold text-neon-red mb-4 uppercase">Kassa Chiqimi (Xarajat)</h2>
            <p class="text-xs text-text-muted mb-4 font-mono">Ushbu so'rov tasdiqlash uchun Adminga yuboriladi.</p>
            
            <form @submit.prevent="submitExpenseForm" class="space-y-4">
                <div>
                    <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Chiqim Summasi (UZS)</label>
                    <input v-model="expenseForm.amount" type="number" required min="0" class="glass-input w-full" placeholder="Masalan: 100000">
                </div>

                <div>
                    <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Kategoriya / Yo'nalish</label>
                    <select v-model="expenseForm.category" required class="glass-input w-full bg-black/50 text-white">
                        <option value="">Tanlang</option>
                        <option value="Xo'jalik">Xo'jalik mollari</option>
                        <option value="Transport">Yo'lkira / Transport</option>
                        <option value="Ovqatlanish">Ovqatlanish</option>
                        <option value="Boshqa">Boshqa xarajatlar</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Izoh (Batafsil maqsad)</label>
                    <textarea v-model="expenseForm.description" rows="3" class="glass-input w-full" placeholder="Aynan nima xarid qilindi?"></textarea>
                </div>

                <div class="pt-4 flex gap-3 justify-end">
                    <button type="button" @click="closeExpenseModal" class="px-4 py-2 text-sm font-mono text-text-muted hover:text-white">Bekor qilish</button>
                    <button type="submit" class="bg-neon-red text-white font-bold text-sm px-6 py-2 rounded hover:shadow-[0_0_15px_#FF003C] transition-all">Tasdiqqa Yuborish</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Unit Action Modal -->
    <div v-if="showUnitActionModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
        <div class="glass-panel w-full max-w-sm p-6 rounded-2xl border border-neon-cyan/50 shadow-[0_0_30px_rgba(0,240,255,0.15)] relative">
            <button @click="closeUnitActionModal" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
            
            <div class="text-center mb-6">
                <span class="text-4xl drop-shadow-[0_0_15px_rgba(0,240,255,0.5)] block mb-2">{{ activeActionUnit?.type === 'commercial' ? '🏬' : '🏠' }}</span>
                <h3 class="text-xl font-display font-bold text-white uppercase tracking-widest">{{ activeActionUnit?.unit_number }} - {{ activeActionUnit?.type === 'commercial' ? "Do'kon" : 'Xonadon' }}</h3>
                <p class="text-xs font-mono text-neon-green mt-1">Holati: {{ activeActionUnit?.status === 'available' ? 'Bo\'sh' : 'Band qilingan' }}</p>
            </div>
            
            <div class="space-y-3">
                <button @click="handleUnitAction('contract')" class="w-full py-3 bg-neon-cyan text-black font-bold uppercase tracking-widest text-sm rounded-xl hover:shadow-[0_0_15px_#00F0FF] transition-all flex items-center justify-center gap-2">
                    <span>📄</span> Shartnoma Tuzish
                </button>
                <button v-if="activeActionUnit?.status !== 'booked'" @click="handleUnitAction('book')" class="w-full py-3 bg-neon-yellow/10 border border-neon-yellow text-neon-yellow font-bold uppercase tracking-widest text-sm rounded-xl hover:bg-neon-yellow hover:text-black transition-all flex items-center justify-center gap-2">
                    <span>🔒</span> Band Qilish (Bron)
                </button>
            </div>
            
            <button @click="closeUnitActionModal" class="w-full mt-4 py-2 text-xs font-mono text-text-muted hover:text-white transition-colors">Bekor Qilish</button>
        </div>
    </div>

    <!-- Contract Details Modal -->
    <div v-if="showContractDetailsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
        <div class="glass-panel w-full max-w-md p-6 rounded-2xl border border-neon-magenta/50 shadow-[0_0_30px_rgba(255,0,60,0.15)] relative">
            <button @click="showContractDetailsModal = false" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
            
            <div class="flex justify-between items-start mb-4">
                <h2 class="text-xl font-display font-bold text-white uppercase">Shartnoma Tafsiloti</h2>
                <button @click="openEditContractModal(selectedContract)" class="bg-white/10 text-white hover:bg-white/20 px-3 py-1 text-xs rounded transition-colors mr-6">Tahrirlash</button>
            </div>
            
            <div v-if="selectedContract" class="space-y-4">
                <div class="bg-black/50 p-4 rounded-xl border border-white/10">
                    <p class="text-[10px] font-mono text-text-muted uppercase mb-1">Mijoz</p>
                    <p class="text-sm font-bold text-white">{{ selectedContract.client?.first_name }} {{ selectedContract.client?.last_name }}</p>
                    <p class="text-xs text-text-muted mt-1">{{ selectedContract.client?.phone }} | {{ selectedContract.client?.passport_number }}</p>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-black/50 p-4 rounded-xl border border-white/10">
                        <p class="text-[10px] font-mono text-text-muted uppercase mb-1">Obyekt</p>
                        <p class="text-sm font-bold text-neon-cyan">Unit #{{ selectedContract.unit?.unit_number }}</p>
                    </div>
                    <div class="bg-black/50 p-4 rounded-xl border border-white/10">
                        <p class="text-[10px] font-mono text-text-muted uppercase mb-1">Umumiy Qiymat</p>
                        <p class="text-sm font-bold text-neon-green">{{ $currency(selectedContract.total_amount) }}</p>
                    </div>
                </div>

                <div v-if="selectedContract.payment_schedules && selectedContract.payment_schedules.length > 0" class="mt-4">
                    <p class="text-[10px] font-mono text-text-muted uppercase mb-2">To'lovlar Grafiki</p>
                    <div class="max-h-40 overflow-y-auto pr-2 custom-scrollbar space-y-2">
                        <div v-for="sch in selectedContract.payment_schedules" :key="sch.id" class="flex justify-between text-xs p-2 rounded bg-white/5 border border-white/10">
                            <span class="text-white">{{ new Date(sch.due_date).toLocaleDateString() }}</span>
                            <span class="font-mono" :class="sch.status === 'paid' ? 'text-neon-green' : 'text-neon-yellow'">{{ $currency(sch.amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Contract Modal -->
    <div v-if="showEditContractModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
        <div class="glass-panel w-full max-w-lg p-6 rounded-2xl border border-neon-cyan/50 shadow-[0_0_30px_rgba(0,240,255,0.15)] relative max-h-[90vh] overflow-y-auto custom-scrollbar">
            <button @click="showEditContractModal = false" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
            
            <h2 class="text-xl font-display font-bold text-neon-cyan mb-2 uppercase">Shartnomani O'zgartirish</h2>
            <p class="text-xs text-text-muted font-mono mb-4">O'zgarishlar Adminga tasdiqlash uchun yuboriladi.</p>
            
            <form @submit.prevent="submitEditContractForm" class="space-y-4">
                <!-- Client Info -->
                <div class="p-4 bg-black/40 rounded-xl border border-white/10 space-y-3">
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-2">Mijoz Ma'lumotlari</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Ism</label>
                            <input v-model="editContractForm.client_first_name" type="text" class="glass-input w-full p-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Familiya</label>
                            <input v-model="editContractForm.client_last_name" type="text" class="glass-input w-full p-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Telefon</label>
                            <input v-model="editContractForm.client_phone" type="text" class="glass-input w-full p-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Pasport</label>
                            <input v-model="editContractForm.client_passport" type="text" class="glass-input w-full p-2 text-sm" required>
                        </div>
                    </div>
                </div>

                <!-- Financial Info -->
                <div class="p-4 bg-black/40 rounded-xl border border-white/10 space-y-3">
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-2">Moliya / To'lov</h3>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Umumiy Qiymat</label>
                        <input v-model="editContractForm.total_amount" type="number" class="glass-input w-full p-2 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">To'lov Turi</label>
                        <select v-model="editContractForm.payment_type" class="glass-input w-full bg-black/50 text-white p-2 text-sm">
                            <option value="full">To'liq (Bir martalik)</option>
                            <option value="installment">Bo'lib To'lash</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3" v-if="editContractForm.payment_type === 'installment'">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Boshlang'ich To'lov</label>
                            <input v-model="editContractForm.down_payment" type="number" class="glass-input w-full p-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Muddat (Oy)</label>
                            <input v-model="editContractForm.term_months" type="number" class="glass-input w-full p-2 text-sm" required>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-neon-cyan text-black font-bold py-3 rounded-xl hover:shadow-[0_0_15px_#00F0FF] transition-all">Tasdiqqa Yuborish</button>
            </form>
        </div>
    </div>

    <!-- Worker Action Modal -->
    <div v-if="showWorkerActionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md">
        <div class="glass-panel w-full max-w-sm p-6 rounded-2xl border border-white/20 shadow-[0_0_30px_rgba(255,255,255,0.1)] relative">
            <button @click="showWorkerActionModal = false" class="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
            
            <h2 class="text-xl font-display font-bold text-white mb-4 uppercase">Ishchi Boshqaruvi</h2>
            <p class="text-sm text-neon-cyan font-bold mb-4">👷 {{ selectedWorker?.name }}</p>
            
            <form @submit.prevent="submitWorkerAction" class="space-y-4">
                <div>
                    <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Harakat Turi</label>
                    <select v-model="workerActionForm.type" class="glass-input w-full bg-black/50 text-white">
                        <option value="advance">Avans So'rash</option>
                        <option value="bonus">Mukofot Yozish</option>
                        <option value="fire">Ishdan Bo'shatish</option>
                    </select>
                </div>
                
                <div v-if="workerActionForm.type === 'advance' || workerActionForm.type === 'bonus'">
                    <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Summa</label>
                    <input v-model="workerActionForm.amount" type="number" class="glass-input w-full" placeholder="Summani kiriting" required>
                </div>
                
                <div>
                    <label class="block text-[10px] font-mono text-text-muted uppercase mb-1">Asos/Izoh</label>
                    <textarea v-model="workerActionForm.notes" class="glass-input w-full h-20" placeholder="Izoh yozing..."></textarea>
                </div>
                
                <button type="submit" class="w-full bg-white text-black font-bold py-2 rounded hover:shadow-[0_0_15px_rgba(255,255,255,0.5)] transition-colors">Yuborish</button>
            </form>
        </div>
    </div>
</template>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
