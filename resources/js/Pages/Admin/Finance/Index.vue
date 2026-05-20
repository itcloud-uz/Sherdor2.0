<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, Link, usePage, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';
import BrigadeLedgerModal from '@/Components/Ledger/BrigadeLedgerModal.vue';
import OwnerLedgerModal from '@/Components/Ledger/OwnerLedgerModal.vue';
import GuardLedgerModal from '@/Components/Ledger/GuardLedgerModal.vue';

const page = usePage();

const gpsCoords = ref({ latitude: null, longitude: null });

onMounted(() => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                gpsCoords.value.latitude = pos.coords.latitude;
                gpsCoords.value.longitude = pos.coords.longitude;
            },
            (err) => console.warn('GPS Error: ', err),
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
        );
    }
});

const props = defineProps({
    transactions: Array,
    balance: Object,
    pendingIncomes: Number,
    pendingExpenses: Number,
    contracts: Array,
    financialLiabilities: Array,
    suppliers: Array,
    workers: Array,
    machineries: Array,
    buildings: Array,
    categories: Array,
    clients: Array,
    partners: Array,
    fixedAssets: Array,
    brigades: Array,
    machineOwners: Array
});

const activeTab = ref(0);
const isProcessing = ref(false);

const tabs = [
    { id: 0, name: 'dashboard', icon: '🏦' },
    { id: 1, name: 'action_center', icon: '⚖️' },
    { id: 3, name: 'create_transaction', icon: '💸' },
    { id: 4, name: 'qarzlar', icon: '📉' },
    { id: 5, name: 'Ta\'minotchilar', icon: '🏢' },
    { id: 6, name: 'O\'tkazmalar', icon: '🔄' },
    { id: 7, name: 'Hisobot (Kategoriya)', icon: '📊' }
];

// Split transactions
const legacyFilter = ref('all'); // 'all', 'native', 'legacy'

const isAdmin = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return false;
    const rawRoles = user.roles || [];
    const roles = rawRoles.map(r => typeof r === 'string' ? r : r.name);
    return roles.includes('Super_Admin') || roles.includes('Admin');
});

const filteredTransactions = computed(() => {
    let result = props.transactions || [];
    if (legacyFilter.value === 'native') {
        result = result.filter(t => !t.is_legacy);
    } else if (legacyFilter.value === 'legacy') {
        result = result.filter(t => t.is_legacy);
    }
    return result;
});

const pendingTransactions = computed(() => (filteredTransactions.value || []).filter(t => t.status === 'pending'));
const historicalTransactions = computed(() => (filteredTransactions.value || []).filter(t => t.status !== 'pending'));

const recentIncomes = computed(() => (historicalTransactions.value || []).filter(t => t.type === 'income'));
const recentExpenses = computed(() => (historicalTransactions.value || []).filter(t => t.type === 'expense'));
const totalApprovedExpenses = computed(() => (recentExpenses.value || []).reduce((sum, t) => sum + parseFloat(t.amount || 0), 0));

const incomePage = ref(1);
const expensePage = ref(1);
const itemsPerPage = 15;

const paginatedIncomes = computed(() => {
    const start = (incomePage.value - 1) * itemsPerPage;
    return recentIncomes.value.slice(start, start + itemsPerPage);
});

const paginatedExpenses = computed(() => {
    const start = (expensePage.value - 1) * itemsPerPage;
    return recentExpenses.value.slice(start, start + itemsPerPage);
});

const totalIncomesPages = computed(() => Math.max(1, Math.ceil(recentIncomes.value.length / itemsPerPage)));
const totalExpensesPages = computed(() => Math.max(1, Math.ceil(recentExpenses.value.length / itemsPerPage)));

const totalIncomeAmount = computed(() => recentIncomes.value.reduce((sum, t) => sum + parseFloat(t.amount || 0), 0));
const totalExpenseAmount = computed(() => recentExpenses.value.reduce((sum, t) => sum + parseFloat(t.amount || 0), 0));

import ExpensesBreakdownModal from '@/Components/ExpensesBreakdownModal.vue';
const showExpensesModal = ref(false);

const showEditTransactionModal = ref(false);
const editTransactionForm = ref({
    id: null,
    amount: '',
    account_type: 'main',
    notes: ''
});

function openEditTransactionModal(txn) {
    editTransactionForm.value = {
        id: txn.id,
        amount: txn.amount,
        account_type: txn.account_type || 'main',
        notes: txn.notes || ''
    };
    showEditTransactionModal.value = true;
}

function submitEditTransaction() {
    isProcessing.value = true;
    router.put(route('admin.finance.transactions.update', editTransactionForm.value.id), editTransactionForm.value, {
        onSuccess: () => {
            isProcessing.value = false;
            showEditTransactionModal.value = false;
        },
        onError: () => isProcessing.value = false
    });
}

function deleteTransaction(id) {
    if (confirm("Haqiqatan ham bu tranzaksiyani o'chirmoqchimisiz? (Tasdiqlangan bo'lsa, balansga ta'sir qiladi)")) {
        isProcessing.value = true;
        router.delete(route('admin.finance.transactions.destroy', id), {
            onSuccess: () => isProcessing.value = false,
            onError: () => isProcessing.value = false
        });
    }
}

// Tab 4 Form
const form = ref({
    type: 'income',
    amount: '',
    currency: 'UZS',
    payment_method: 'cash',
    account_type: 'main',
    contract_id: '',
    client_id: '',
    partner_id: '',
    worker_id: '',
    brigade_id: '',
    machinery_id: '',
    machine_owner_id: '',
    building_id: '',
    supplier_id: '',
    fixed_asset_id: '',
    category_id: '',
    category_name: '',
    transaction_date: new Date().toLocaleString('sv').replace(' ', 'T').slice(0,16),
    attachment: null,
    notes: '',
    auto_approve: true,
    latitude: null,
    longitude: null,
    source: 'admin_panel'
});

const transferForm = ref({
    amount: '',
    from_account: 'bank',
    to_account: 'main',
    fee_amount: '',
    contract_id: '',
    refund_request_id: '',
    notes: ''
});

function fillTransferFromRefund(refund) {
    transferForm.value.amount = refund.amount;
    transferForm.value.contract_id = refund.contract_id;
    transferForm.value.refund_request_id = refund.id;
    transferForm.value.from_account = refund.account_type || 'bank';
    transferForm.value.to_account = 'main';
    transferForm.value.notes = 'Kutayotgan qaytim: ' + refund.notes;
}

const isNewCategory = ref(false);

const selectedCategoryName = computed(() => {
    if (isNewCategory.value) return form.value.category_name?.toLowerCase() || '';
    if (!props.categories) return '';
    const cat = props.categories.find(c => c.id === form.value.category_id);
    return cat ? cat.name.toLowerCase() : '';
});

const requiresBrigade = computed(() => selectedCategoryName.value.includes('brigada') || selectedCategoryName.value.includes('yollanma') || selectedCategoryName.value.includes('ishchi'));
const requiresMachineOwner = computed(() => selectedCategoryName.value.includes('texnika') || selectedCategoryName.value.includes('arendator'));
const requiresWorker = computed(() => selectedCategoryName.value.includes('oylik') || selectedCategoryName.value.includes('avans') || selectedCategoryName.value.includes('ishchi'));
const isGuardSelected = computed(() => {
    if (!form.value.worker_id) return false;
    const w = props.workers?.find(x => x.id === form.value.worker_id);
    return w && w.role === 'guard';
});
const requiresMachinery = computed(() => selectedCategoryName.value.includes('texnika'));
const requiresSupplier = computed(() => selectedCategoryName.value.includes('ta\'minotchi') || selectedCategoryName.value.includes('qarz qaytarish'));
const requiresBuilding = computed(() => selectedCategoryName.value.includes('obyekt') || selectedCategoryName.value.includes('qurilish material'));
const requiresClient = computed(() => selectedCategoryName.value.includes('mijoz'));
const requiresPartner = computed(() => selectedCategoryName.value.includes('ta\'sischi') || selectedCategoryName.value.includes('hamkor') || selectedCategoryName.value.includes('partner'));
const requiresFixedAsset = computed(() => selectedCategoryName.value.includes('asosiy vosita'));

const pendingRefunds = computed(() => {
    return (props.transactions || []).filter(t => t.category?.name === 'Kutayotgan Naqdlashtirish' && t.status === 'pending');
});

function handleFileUpload(e) {
    if (e.target.files.length > 0) {
        form.value.attachment = e.target.files[0];
    }
}

const scheduleSearch = ref('');
const showScheduleDropdown = ref(false);

function handleBlur() {
    setTimeout(() => {
        showScheduleDropdown.value = false;
    }, 200);
}

const filteredContracts = computed(() => {
    if (!props.contracts) return [];
    
    let baseContracts = props.contracts || [];
    
    // Mijozga pulni qaytarish uchun faqat bekor qilingan shartnomalar
    if (selectedCategoryName.value === 'mijozga pulni qaytarish' || selectedCategoryName.value === 'mijozga to\'lov qilish' || selectedCategoryName.value === 'client_refund') {
        baseContracts = baseContracts.filter(c => c.status === 'cancelled');
    } else {
        baseContracts = baseContracts.filter(c => c.status !== 'cancelled');
    }

    if (!scheduleSearch.value) return baseContracts;
    
    const query = scheduleSearch.value.toLowerCase();
    return baseContracts.filter(c => {
        const clientName = String(c.client?.first_name + ' ' + c.client?.last_name).toLowerCase();
        const unitNumber = String(c.unit?.unit_number || '').toLowerCase();
        return clientName.includes(query) || unitNumber.includes(query);
    });
});

function selectContract(c) {
    form.value.contract_id = c.id;
    scheduleSearch.value = `${c.client?.first_name} ${c.client?.last_name} (${c.unit?.unit_number})`;
    showScheduleDropdown.value = false;
}

const showOwnerLedger = ref(false);
const showBrigadeLedger = ref(false);
const showOwnerLedgerInline = ref(false);
const showGuardLedgerInline = ref(false);
const showBrigadeLedgerInline = ref(false);
const selectedOwnerId = ref(null);
const selectedBrigadeId = ref(null);

function openOwnerLedgerFromFinance(ownerId) {
    if (!ownerId) return;
    selectedOwnerId.value = ownerId;
    showOwnerLedger.value = true;
}

function openBrigadeLedgerFromFinance(brigadeId) {
    if (!brigadeId) return;
    selectedBrigadeId.value = brigadeId;
    showBrigadeLedger.value = true;
}

function submitTransaction() {
    if (requiresClient.value && !form.value.contract_id) {
        alert("Iltimos, ushbu kategoriya uchun aniq bir Mijoz Shartnomasini tanlang (Qidiruv qatoridan).");
        return;
    }

    isProcessing.value = true;
    form.value.latitude = gpsCoords.value.latitude;
    form.value.longitude = gpsCoords.value.longitude;
    
    // Auto-fill target_type and target_id based on requires flags
    if (requiresPartner.value || form.value.account_type === 'partner') {
        form.value.target_type = 'partner';
        form.value.target_id = form.value.partner_id;
        } else if (requiresMachineOwner.value) {
        form.value.target_type = 'machine_owner';
        form.value.target_id = form.value.machine_owner_id;
    } else if (requiresBrigade.value) {
        form.value.target_type = 'brigade';
        form.value.target_id = form.value.brigade_id;
    } else if (requiresWorker.value) {
        form.value.target_type = 'worker';
        form.value.target_id = form.value.worker_id;
    } else if (requiresSupplier.value) {
        form.value.target_type = 'supplier';
        form.value.target_id = form.value.supplier_id;
    } else if (requiresClient.value) {
        form.value.target_type = 'client';
        form.value.target_id = form.value.client_id;
    }

    router.post(route('admin.finance.transactions.store'), form.value, {
        forceFormData: true,
        onSuccess: () => {
            isProcessing.value = false;
            form.value = { type: 'income', category_id: '', category_name: '', amount: '', currency: 'UZS', payment_method: 'cash', account_type: 'main', contract_id: '', client_id: '', worker_id: '',
    brigade_id: '', partner_id: '', machinery_id: '',
    machine_owner_id: '', building_id: '', supplier_id: '', fixed_asset_id: '', transaction_date: new Date().toLocaleString('sv').replace(' ', 'T').slice(0,16), attachment: null, auto_approve: true, notes: '', latitude: null, longitude: null, source: 'admin_panel', target_type: null, target_id: null };
            activeTab.value = 1; // Go to Action Center
        },
        onError: () => isProcessing.value = false
    });
}

function submitTransfer() {
    isProcessing.value = true;
    router.post(route('admin.finance.internal_transfer'), transferForm.value, {
        onSuccess: () => {
            isProcessing.value = false;
            transferForm.value = { amount: '', from_account: 'bank', to_account: 'main', fee_amount: '', notes: '' };
            activeTab.value = 2; // Go to Ledger History
        },
        onError: () => isProcessing.value = false
    });
}

function approve(id) {
    const txn = (props.transactions || []).find(t => t.id === id);
    if (txn && txn.contract_id && txn.type === 'income') {
        selectedContractTxn.value = txn;
        contractApproveForm.value.applied_amount = txn.amount;
        contractApproveForm.value.refund_amount = 0;
        showContractApproveModal.value = true;
        return;
    }

    isProcessing.value = true;
    router.post(route('admin.finance.transactions.approve', id), {}, {
        onSuccess: () => isProcessing.value = false,
        onError: () => isProcessing.value = false
    });
}

const showContractApproveModal = ref(false);
const selectedContractTxn = ref(null);
const contractApproveForm = ref({
    applied_amount: 0,
    refund_amount: 0
});

watch(() => contractApproveForm.value.applied_amount, (newVal) => {
    if (selectedContractTxn.value) {
        contractApproveForm.value.refund_amount = Math.max(0, selectedContractTxn.value.amount - newVal);
    }
});

function submitContractApprove() {
    isProcessing.value = true;
    router.post(route('admin.finance.transactions.approve', selectedContractTxn.value.id), contractApproveForm.value, {
        onSuccess: () => {
            isProcessing.value = false;
            showContractApproveModal.value = false;
        },
        onError: () => isProcessing.value = false
    });
}

function reject(id) {
    isProcessing.value = true;
    router.post(route('admin.finance.transactions.reject', id), {}, {
        onSuccess: () => isProcessing.value = false,
        onError: () => isProcessing.value = false
    });
}

// Receipt Modal
const showReceiptModal = ref(false);
const receiptData = ref(null);

function openReceipt(transaction) {
    receiptData.value = transaction;
    showReceiptModal.value = true;
}

// Qarzlar (Liabilities) Logic
const showLiabilityModal = ref(false);
const isEditMode = ref(false);
const editingLiabilityId = ref(null);
const liabilityForm = ref({
    type: 'bank_loan',
    creditor_name: '',
    total_amount: '',
    interest_rate: '',
    term_months: 12,
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    monthly_payment: '',
    description: '',
    status: 'active',
    fund_account: 'main'
});

function openNewLiabilityModal() {
    isEditMode.value = false;
    editingLiabilityId.value = null;
    liabilityForm.value = {
        type: 'bank_loan', creditor_name: '', total_amount: '', interest_rate: '', 
        term_months: 12, start_date: new Date().toISOString().split('T')[0], 
        end_date: '', monthly_payment: '', description: '', status: 'active', fund_account: 'main'
    };
    showLiabilityModal.value = true;
}

function openEditLiability(liability) {
    isEditMode.value = true;
    editingLiabilityId.value = liability.id;
    liabilityForm.value = {
        type: liability.type,
        creditor_name: liability.creditor_name,
        total_amount: liability.total_amount,
        interest_rate: liability.interest_rate,
        term_months: liability.term_months,
        start_date: liability.start_date,
        end_date: liability.end_date,
        monthly_payment: liability.monthly_payment,
        description: liability.description,
        status: liability.status,
        fund_account: '' // Not applicable for edits
    };
    showLiabilityModal.value = true;
}

function submitLiability() {
    isProcessing.value = true;
    if (isEditMode.value) {
        router.put(route('admin.finance.liabilities.update', editingLiabilityId.value), liabilityForm.value, {
            onSuccess: () => {
                isProcessing.value = false;
                showLiabilityModal.value = false;
            },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.finance.liabilities.store'), liabilityForm.value, {
            onSuccess: () => {
                isProcessing.value = false;
                showLiabilityModal.value = false;
            },
            onError: () => isProcessing.value = false
        });
    }
}

// Payment Schedule Modal
const showScheduleModal = ref(false);
const selectedLiabilityForSchedule = ref(null);
const generatedSchedule = ref([]);

function openScheduleModal(liability) {
    selectedLiabilityForSchedule.value = liability;
    generatedSchedule.value = [];
    
    let p = parseFloat(liability.total_amount);
    let n = parseInt(liability.term_months);
    let r_annual = parseFloat(liability.interest_rate || 0);
    let m = parseFloat(liability.monthly_payment);
    
    if (p && n) {
        let balance = p;
        let r = (r_annual / 100) / 12;
        
        if (!m) {
            if (r > 0) {
                m = p * (r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
            } else {
                m = p / n;
            }
        }
        
        let currentDate = new Date(liability.start_date);
        
        for (let i = 1; i <= n; i++) {
            let interest = balance * r;
            let principal = m - interest;
            
            // For the last month, adjust to exact remaining balance
            if (i === n) {
                principal = balance;
                m = principal + interest;
            }
            
            balance -= principal;
            
            currentDate.setMonth(currentDate.getMonth() + 1);
            
            generatedSchedule.value.push({
                month: i,
                date: currentDate.toISOString().split('T')[0],
                payment: m,
                principal: principal,
                interest: interest,
                balance: Math.max(0, balance)
            });
        }
    }
    
    showScheduleModal.value = true;
}

function deleteLiability(id) {
    if (confirm("Rostdan ham ushbu qarzni o'chirmoqchimisiz? Bu amalni ortga qaytarib bo'lmaydi.")) {
        isProcessing.value = true;
        router.delete(route('admin.finance.liabilities.destroy', id), {
            onSuccess: () => isProcessing.value = false,
            onError: () => isProcessing.value = false
        });
    }
}

const showPaymentModal = ref(false);
const selectedLiability = ref(null);
const paymentForm = ref({
    amount: '',
    payment_date: new Date().toISOString().split('T')[0],
    notes: '',
    account_type: 'main'
});

function openPaymentModal(liability) {
    selectedLiability.value = liability;
    paymentForm.value.amount = liability.monthly_payment || '';
    paymentForm.value.account_type = 'main';
    showPaymentModal.value = true;
}

function submitPayment() {
    isProcessing.value = true;
    router.post(route('admin.finance.liabilities.payments.store', selectedLiability.value.id), paymentForm.value, {
        onSuccess: () => {
            isProcessing.value = false;
            showPaymentModal.value = false;
            paymentForm.value = { amount: '', payment_date: new Date().toISOString().split('T')[0], notes: '', account_type: 'main' };
        },
        onError: () => isProcessing.value = false
    });
}

// Supplier Payments
const showSupplierPaymentModal = ref(false);
const selectedSupplier = ref(null);
const supplierPaymentForm = ref({
    supplier_id: '',
    amount: '',
    account_type: 'main',
    notes: ''
});

function openSupplierPaymentModal(supplier) {
    selectedSupplier.value = supplier;
    supplierPaymentForm.value = {
        supplier_id: supplier.id,
        amount: Math.max(0, supplier.balance),
        account_type: 'main',
        notes: ''
    };
    showSupplierPaymentModal.value = true;
}

function submitSupplierPayment() {
    isProcessing.value = true;
    router.post(route('admin.finance.suppliers.pay'), supplierPaymentForm.value, {
        onSuccess: () => {
            isProcessing.value = false;
            showSupplierPaymentModal.value = false;
        },
        onError: () => isProcessing.value = false
    });
}

// Edit Supplier (specifically balance)
const showEditSupplierModal = ref(false);
const editSupplierForm = useForm({
    id: '',
    name: '',
    company_name: '',
    balance: 0
});

function openEditSupplierModal(supplier) {
    editSupplierForm.id = supplier.id;
    editSupplierForm.name = supplier.name;
    editSupplierForm.company_name = supplier.company_name;
    editSupplierForm.balance = supplier.balance;
    showEditSupplierModal.value = true;
}

function submitEditSupplier() {
    isProcessing.value = true;
    editSupplierForm.put(route('admin.warehouse.suppliers.update', editSupplierForm.id), {
        onSuccess: () => {
            isProcessing.value = false;
            showEditSupplierModal.value = false;
        },
        onError: () => isProcessing.value = false
    });
}

function deleteSupplier(id) {
    if (confirm("Ta'minotchini o'chirishni tasdiqlaysizmi?")) {
        router.delete(route('admin.warehouse.suppliers.destroy', id));
    }
}
const categoriesSummary = computed(() => {
    if (!props.transactions) return [];
    
    const approvedTxns = props.transactions.filter(t => t.status === 'approved');
    const groups = {};
    
    approvedTxns.forEach(t => {
        const catName = t.category?.name || 'Noma\'lum';
        if (!groups[catName]) {
            groups[catName] = {
                name: catName,
                total_income: 0,
                total_expense: 0,
                transactions: []
            };
        }
        
        if (t.type === 'income') {
            groups[catName].total_income += parseFloat(t.amount);
        } else if (t.type === 'expense') {
            groups[catName].total_expense += parseFloat(t.amount);
        }
        
        groups[catName].transactions.push(t);
    });
    
    return Object.values(groups).sort((a, b) => b.transactions.length - a.transactions.length);
});

</script>

<template>
    <Head :title="$t('finance_core')" />
    <AdminLayout>
        


        <div class="flex flex-col gap-4">
            
            <!-- Horizontal Navigation Panel -->
            <div class="flex flex-wrap items-center gap-2 p-2 bg-black/30 border border-white/5 rounded-xl shadow-[0_4px_20px_rgba(0,0,0,0.5)]">
                <button 
                    v-for="tab in tabs" :key="tab.id"
                    @click="activeTab = tab.id"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg font-mono text-xs uppercase tracking-widest transition-all whitespace-nowrap"
                    :class="activeTab === tab.id ? 'bg-neon-yellow/20 text-neon-yellow border border-neon-yellow/30 shadow-[0_0_15px_rgba(252,238,9,0.2)]' : 'border border-transparent text-text-muted hover:text-white hover:bg-white/5'"
                >
                    <span class="text-base">{{ tab.icon }}</span> {{ $t(tab.name) }}
                </button>
                
                <div class="w-px h-6 bg-white/20 mx-1 hidden sm:block"></div>
                
                <Link :href="route('admin.approvals')" class="flex items-center gap-2 px-4 py-2 rounded-lg font-mono text-xs uppercase tracking-widest text-text-muted hover:text-neon-yellow hover:bg-white/5 border border-transparent transition-all whitespace-nowrap">
                    <span class="text-base">✅</span> Tasdiqlar
                </Link>
                
                <button 
                    @click="activeTab = 2"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg font-mono text-xs uppercase tracking-widest transition-all whitespace-nowrap"
                    :class="activeTab === 2 ? 'bg-neon-yellow/20 text-neon-yellow border border-neon-yellow/30 shadow-[0_0_15px_rgba(252,238,9,0.2)]' : 'border border-transparent text-text-muted hover:text-white hover:bg-white/5'"
                >
                    <span class="text-base">📜</span> Jurnal
                </button>
                
                <Link :href="route('admin.receipts')" class="flex items-center gap-2 px-4 py-2 rounded-lg font-mono text-xs uppercase tracking-widest text-text-muted hover:text-neon-cyan hover:bg-white/5 border border-transparent transition-all whitespace-nowrap">
                    <span class="text-base">🧾</span> Cheklar
                </Link>
            </div>

            <!-- Content Area -->
            <div class="w-full overflow-x-hidden">

        <!-- TAB 1: Main Dashboard -->
        <div v-if="activeTab === 0" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- KASSA (CASH) -->
                <div class="glass-card p-6 border-t-4 border-t-neon-green relative overflow-hidden group">
                    <div class="absolute inset-0 bg-neon-green/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-sm font-mono text-text-muted">{{ $t('total_balance') }} (Kassa)</p>
                    <h2 class="text-3xl font-display font-bold text-white mt-2 drop-shadow-[0_0_10px_rgba(57,255,20,0.3)]">
                        {{ $currency(balance?.total_balance || 0) }}
                    </h2>
                </div>
                
                <!-- BANK ACCOUNT (CREDIT) -->
                <div class="glass-card p-6 border-t-4 border-t-neon-yellow relative overflow-hidden group">
                    <div class="absolute inset-0 bg-neon-yellow/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-sm font-mono text-text-muted uppercase tracking-widest">Bank Hisobi</p>
                    <h2 class="text-3xl font-display font-bold text-white mt-2 drop-shadow-[0_0_10px_rgba(252,238,9,0.3)]">
                        {{ $currency(balance?.credit_balance || 0) }}
                    </h2>
                </div>
                
                <!-- PENDING INCOMES -->
                <div class="glass-card p-6 border-t-4 border-t-neon-cyan relative overflow-hidden group">
                    <div class="absolute inset-0 bg-neon-cyan/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-sm font-mono text-text-muted">{{ $t('pending_incomes') }}</p>
                    <h2 class="text-3xl font-display font-bold text-white mt-2 drop-shadow-[0_0_10px_rgba(0,240,255,0.3)]">
                        + {{ $currency(pendingIncomes || 0) }}
                    </h2>
                </div>

                <!-- PENDING EXPENSES -->
                <div class="glass-card p-6 border-t-4 border-t-neon-red relative overflow-hidden group">
                    <div class="absolute inset-0 bg-neon-red/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-sm font-mono text-text-muted">{{ $t('pending_expenses') }}</p>
                    <h2 class="text-3xl font-display font-bold text-white mt-2 drop-shadow-[0_0_10px_rgba(255,0,0,0.3)]">
                        - {{ $currency(pendingExpenses || 0) }}
                    </h2>
                </div>
                
                <div @click="showExpensesModal = true" class="glass-card p-6 border-t-4 border-neon-purple relative overflow-hidden group cursor-pointer hover:bg-neon-purple/5 transition-colors border-white/5 hover:border-neon-purple/30">
                    <div class="absolute inset-0 bg-neon-purple/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-[10px] font-mono text-text-muted uppercase tracking-widest flex justify-between">
                        <span>Jami Tasdiqlangan Chiqimlar</span>
                        <span class="text-neon-purple underline">batafsil</span>
                    </p>
                    <h2 class="text-3xl font-display font-bold text-white mt-2 drop-shadow-[0_0_10px_rgba(176,38,255,0.3)]">
                        - {{ $currency(totalApprovedExpenses || 0) }}
                    </h2>
                </div>
            </div>

            <!-- Transaction Lists -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Incomes -->
                <div class="glass-panel p-4 md:p-6 rounded-2xl border border-neon-cyan/20 h-auto max-h-[500px] flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-display text-white uppercase tracking-widest flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-neon-cyan"></span>{{ $t('incomes_from') }}</h3>
                        <!-- LEGACY FILTERS -->
                        <div class="flex bg-black/40 border border-white/10 rounded-lg p-1">
                            <button @click="legacyFilter = 'all'" :class="['px-2 py-1 text-[8px] font-mono uppercase rounded transition-all', legacyFilter === 'all' ? 'bg-white/20 text-white' : 'text-text-muted hover:text-white']">{{ $t('all') }}</button>
                            <button @click="legacyFilter = 'native'" :class="['px-2 py-1 text-[8px] font-mono uppercase rounded transition-all', legacyFilter === 'native' ? 'bg-neon-green/20 text-neon-green border border-neon-green/30' : 'text-text-muted hover:text-white']">{{ $t('new_native') }}</button>
                            <button @click="legacyFilter = 'legacy'" :class="['px-2 py-1 text-[8px] font-mono uppercase rounded transition-all', legacyFilter === 'legacy' ? 'bg-neon-purple/20 text-neon-purple border border-neon-purple/30' : 'text-text-muted hover:text-white']">{{ $t('imported') }}</button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto custom-scrollbar flex flex-col">
                        <table class="w-full text-left text-[12px] font-mono">
                            <thead class="bg-white/5 border-b border-white/10 text-text-muted sticky top-0 z-10 backdrop-blur-md">
                                <tr>
                                    <th class="p-3">Sana</th>
                                    <th class="p-3">Izoh</th>
                                    <th class="p-3">Kategoriya</th>
                                    <th class="p-3">Hisob</th>
                                    <th class="p-3 text-right">Summa</th>
                                    <th class="p-3 text-center">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="txn in paginatedIncomes" :key="txn.id" class="hover:bg-white/5 transition-colors group relative">
                                    <td class="p-3">
                                        <span v-if="txn.is_legacy" class="mr-1 px-1 py-0.5 rounded text-[8px] font-bold bg-neon-purple/20 text-neon-purple border border-neon-purple/50 uppercase">Import</span>
                                        {{ new Date(txn.transaction_date || txn.updated_at).toLocaleDateString() }}
                                    </td>
                                    <td class="p-3 whitespace-normal max-w-xs">{{ txn.notes || (txn.payment_schedule_id ? 'Shartnoma to\'lovi' : '-') }}</td>
                                    <td class="p-3">{{ txn.category?.name || '-' }}</td>
                                    <td class="p-3">
                                        <span class="text-[10px] uppercase font-bold px-2 py-1 rounded" :class="txn.account_type === 'credit' ? 'bg-neon-yellow/20 text-neon-yellow' : 'bg-neon-green/20 text-neon-green'">
                                            {{ txn.account_type === 'credit' ? 'KREDIT' : 'MAIN' }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right font-bold text-neon-cyan whitespace-nowrap">+ {{ $currency(txn.amount) }}</td>
                                    <td class="p-3 text-center">
                                        <div class="flex justify-center gap-2">
                                            <button v-if="isAdmin" @click="openEditTransactionModal(txn)" class="text-[10px] uppercase text-blue-400 hover:text-blue-300 bg-blue-400/10 px-2 py-1 rounded">Tahrir</button>
                                            <button v-if="isAdmin" @click="deleteTransaction(txn.id)" class="text-[10px] uppercase text-red-400 hover:text-red-300 bg-red-400/10 px-2 py-1 rounded">O'chir</button>
                                            <a :href="route('admin.finance.transactions.export.pdf', txn.id)" target="_blank" class="text-[10px] uppercase text-gray-400 hover:text-white bg-white/10 px-2 py-1 rounded">Yuklash</a>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!paginatedIncomes.length">
                                    <td colspan="6" class="p-4 text-center text-text-muted text-sm">Kirimlar mavjud emas</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-auto border-t border-white/10 pt-4 pb-2">
                            <div class="flex justify-between items-center px-4 font-mono">
                                <div class="font-bold text-white text-sm">JAMI: <span class="text-neon-cyan">+ {{ $currency(totalIncomeAmount) }}</span></div>
                                <div class="flex items-center gap-4 text-xs">
                                    <button @click="incomePage > 1 && incomePage--" :disabled="incomePage === 1" class="text-white hover:text-neon-cyan disabled:opacity-30">◄</button>
                                    <span>{{ incomePage }} / {{ totalIncomesPages }}</span>
                                    <button @click="incomePage < totalIncomesPages && incomePage++" :disabled="incomePage === totalIncomesPages" class="text-white hover:text-neon-cyan disabled:opacity-30">►</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expenses -->
                <div class="glass-panel p-4 md:p-6 rounded-2xl border border-neon-red/20 h-auto max-h-[500px] flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-display text-white uppercase tracking-widest flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-neon-red"></span>{{ $t('expenses_to') }}</h3>
                        
                        <div class="flex items-center gap-4">
                            <button @click="showExpensesModal = true" class="px-2 py-1 text-[10px] font-mono uppercase bg-neon-purple/20 text-neon-purple border border-neon-purple/50 rounded hover:bg-neon-purple hover:text-white transition-colors flex items-center gap-1 shadow-[0_0_5px_rgba(176,38,255,0.4)]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 0l-2 2a1 1 0 101.414 1.414L8 10.414l1.293 1.293a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                                Analitika
                            </button>
                            <!-- LEGACY FILTERS -->
                            <div class="flex bg-black/40 border border-white/10 rounded-lg p-1">
                                <button @click="legacyFilter = 'all'" :class="['px-2 py-1 text-[8px] font-mono uppercase rounded transition-all', legacyFilter === 'all' ? 'bg-white/20 text-white' : 'text-text-muted hover:text-white']">{{ $t('all') }}</button>
                                <button @click="legacyFilter = 'native'" :class="['px-2 py-1 text-[8px] font-mono uppercase rounded transition-all', legacyFilter === 'native' ? 'bg-neon-green/20 text-neon-green border border-neon-green/30' : 'text-text-muted hover:text-white']">{{ $t('new_native') }}</button>
                                <button @click="legacyFilter = 'legacy'" :class="['px-2 py-1 text-[8px] font-mono uppercase rounded transition-all', legacyFilter === 'legacy' ? 'bg-neon-purple/20 text-neon-purple border border-neon-purple/30' : 'text-text-muted hover:text-white']">{{ $t('imported') }}</button>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto custom-scrollbar flex flex-col">
                        <table class="w-full text-left text-[12px] font-mono">
                            <thead class="bg-white/5 border-b border-white/10 text-text-muted sticky top-0 z-10 backdrop-blur-md">
                                <tr>
                                    <th class="p-3">Sana</th>
                                    <th class="p-3">Izoh</th>
                                    <th class="p-3">Kategoriya</th>
                                    <th class="p-3">Hisob</th>
                                    <th class="p-3">To'lov Usuli</th>
                                    <th class="p-3 text-right">Summa</th>
                                    <th class="p-3 text-center">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="txn in paginatedExpenses" :key="txn.id" class="hover:bg-white/5 transition-colors group relative">
                                    <td class="p-3">
                                        <span v-if="txn.is_legacy" class="mr-1 px-1 py-0.5 rounded text-[8px] font-bold bg-neon-purple/20 text-neon-purple border border-neon-purple/50 uppercase">Import</span>
                                        {{ new Date(txn.transaction_date || txn.updated_at).toLocaleDateString() }}
                                    </td>
                                    <td class="p-3 whitespace-normal max-w-xs">{{ txn.notes || '-' }}</td>
                                    <td class="p-3">{{ txn.category?.name || '-' }}</td>
                                    <td class="p-3">
                                        <span class="text-[10px] uppercase font-bold px-2 py-1 rounded" :class="txn.account_type === 'credit' ? 'bg-neon-yellow/20 text-neon-yellow' : 'bg-neon-green/20 text-neon-green'">
                                            {{ txn.account_type === 'credit' ? 'KREDIT' : 'MAIN' }}
                                        </span>
                                    </td>
                                    <td class="p-3 uppercase text-[10px]">{{ txn.payment_method || 'cash' }}</td>
                                    <td class="p-3 text-right font-bold text-neon-red whitespace-nowrap">- {{ $currency(txn.amount) }}</td>
                                    <td class="p-3 text-center">
                                        <div class="flex justify-center gap-2">
                                            <button v-if="isAdmin" @click="openEditTransactionModal(txn)" class="text-[10px] uppercase text-blue-400 hover:text-blue-300 bg-blue-400/10 px-2 py-1 rounded">Tahrir</button>
                                            <button v-if="isAdmin" @click="deleteTransaction(txn.id)" class="text-[10px] uppercase text-red-400 hover:text-red-300 bg-red-400/10 px-2 py-1 rounded">O'chir</button>
                                            <a :href="route('admin.finance.transactions.export.pdf', txn.id)" target="_blank" class="text-[10px] uppercase text-gray-400 hover:text-white bg-white/10 px-2 py-1 rounded">Yuklash</a>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!paginatedExpenses.length">
                                    <td colspan="7" class="p-4 text-center text-text-muted text-sm">Chiqimlar mavjud emas</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-auto border-t border-white/10 pt-4 pb-2">
                            <div class="flex justify-between items-center px-4 font-mono">
                                <div class="font-bold text-white text-sm">JAMI: <span class="text-neon-red">- {{ $currency(totalExpenseAmount) }}</span></div>
                                <div class="flex items-center gap-4 text-xs">
                                    <button @click="expensePage > 1 && expensePage--" :disabled="expensePage === 1" class="text-white hover:text-neon-red disabled:opacity-30">◄</button>
                                    <span>{{ expensePage }} / {{ totalExpensesPages }}</span>
                                    <button @click="expensePage < totalExpensesPages && expensePage++" :disabled="expensePage === totalExpensesPages" class="text-white hover:text-neon-red disabled:opacity-30">►</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Transaction Modal -->
            <div v-if="showEditTransactionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
                <div class="glass-panel max-w-md w-full p-6 rounded-2xl relative border border-white/10">
                    <button @click="showEditTransactionModal = false" class="absolute top-4 right-4 text-white/50 hover:text-white">✕</button>
                    <h3 class="text-xl font-display text-white mb-6">{{ $t('edit_transaction') }}</h3>
                    
                    <form @submit.prevent="submitEditTransaction" class="space-y-4">
                        <div>
                            <label class="block text-text-muted mb-1 text-sm">{{ $t('amount') }}</label>
                            <input v-model="editTransactionForm.amount" type="number" step="0.01" class="glass-input w-full" required>
                        </div>
                        <div>
                            <label class="block text-text-muted mb-1 text-sm">{{ $t('account') }}</label>
                            <select v-model="editTransactionForm.account_type" class="glass-input w-full" required>
                                <option value="main">{{ $t('main_account') }}</option>
                                <option value="credit">{{ $t('credit_account') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-text-muted mb-1 text-sm">{{ $t('notes_direction') }}</label>
                            <textarea v-model="editTransactionForm.notes" class="glass-input w-full" rows="3"></textarea>
                        </div>
                        
                        <div class="flex justify-end pt-4 gap-4">
                            <button type="button" @click="showEditTransactionModal = false" class="px-6 py-2 text-text-muted hover:text-white">{{ $t('cancel') }}</button>
                            <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-white text-black rounded font-bold hover:bg-gray-200 disabled:opacity-50">{{ $t('save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 2: Action Center (Maker/Checker) -->
        <div v-if="activeTab === 1" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <div class="glass-panel p-6 rounded-2xl border border-neon-yellow/30 shadow-[0_0_20px_rgba(252,238,9,0.1)]">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-display font-bold text-white flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-neon-yellow animate-pulse"></span>
                        {{ $t('maker_checker_queue') }}
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="txn in pendingTransactions" :key="txn.id" class="p-5 rounded-2xl bg-black/40 border border-white/10 hover:border-neon-yellow/50 transition-all flex flex-col gap-4 relative overflow-hidden group">
                        <div class="absolute inset-0 bg-neon-yellow/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        
                        <div class="flex justify-between items-start z-10">
                            <span class="px-2 py-1 rounded text-[10px] uppercase tracking-wider" :class="txn.type === 'income' ? 'bg-neon-cyan/20 text-neon-cyan' : 'bg-neon-red/20 text-neon-red'">
                                {{ txn.type === 'income' ? 'Kirim' : 'Chiqim' }}
                            </span>
                            <span class="text-xs text-text-muted">{{ new Date(txn.transaction_date || txn.updated_at).toLocaleDateString() }}</span>
                        </div>
                        
                        <div class="z-10 flex items-center gap-3">
                            <h3 class="text-2xl font-bold font-display" :class="txn.type === 'income' ? 'text-neon-cyan' : 'text-neon-red'">
                                {{ txn.type === 'income' ? '+' : '-' }} {{ $currency(txn.amount) }}
                            </h3>
                            <span v-if="txn.is_legacy" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-neon-purple/20 text-neon-purple border border-neon-purple/50 uppercase shadow-[0_0_5px_rgba(176,38,255,0.4)]">{{ $t('imported') }}</span>
                        </div>
                        <div class="z-10">
                            <p class="text-[10px] uppercase font-bold mt-1" :class="txn.account_type === 'credit' ? 'text-neon-yellow' : 'text-neon-green'">
                                Hisob: {{ txn.account_type === 'credit' ? 'KREDIT (KASSA)' : 'MAIN (KOMPANIYA)' }}
                            </p>
                        </div>
                        
                        <div class="bg-white/5 p-3 rounded-lg text-xs font-mono text-white/80 z-10">
                            <p class="mb-1"><span class="text-text-muted">{{ $t('notes_label') }}</span> {{ txn.notes || 'Kiritilmagan' }}</p>
                            <p v-if="txn.payment_schedule_id" class="mb-1"><span class="text-text-muted">{{ $t('client_label') }}</span> {{ txn.payment_schedule?.contract?.client?.first_name }} {{ txn.payment_schedule?.contract?.client?.last_name }}</p>
                            <p><span class="text-text-muted">{{ $t('maker_label') }}</span> <span class="text-white font-bold">{{ txn.creator?.name }}</span></p>
                            
                            <div class="mt-2 pt-2 border-t border-white/10">
                                <p class="text-[10px] uppercase font-bold tracking-widest flex items-center gap-1"
                                   :class="{
                                       'text-blue-400': txn.source === 'telegram',
                                       'text-neon-purple': txn.source === 'admin_panel' || !txn.source,
                                       'text-neon-yellow': txn.source === 'partner_app'
                                   }">
                                    <span v-if="txn.source === 'telegram'">{{ $t('source_telegram') }}</span>
                                    <span v-else-if="txn.source === 'partner_app'">{{ $t('source_partner_app') }}</span>
                                    <span v-else>{{ $t('source_admin_panel') }}</span>
                                </p>
                                
                                <p v-if="txn.latitude && txn.longitude" class="mt-1">
                                    <a :href="'https://maps.google.com/?q=' + txn.latitude + ',' + txn.longitude" target="_blank" class="text-[10px] text-neon-cyan hover:text-white flex items-center gap-1 transition-colors">{{ $t('exact_gps_location') }}</a>
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex flex-col gap-2 mt-auto z-10 pt-2 border-t border-white/10">
                            <!-- Bog'lanish Button -->
                            <a :href="'tel:' + (txn.creator?.phone || '+998900000000')" class="w-full text-center px-4 py-2 text-xs uppercase tracking-widest bg-blue-500/20 border border-blue-500 text-blue-400 hover:bg-blue-500 hover:text-white transition-all rounded">
                                📞 Bog'lanish {{ txn.creator?.phone ? '('+txn.creator.phone+')' : '' }}
                            </a>
                            
                            <div class="flex gap-2">
                                <button v-if="isAdmin" @click="approve(txn.id)" :disabled="isProcessing" class="flex-1 px-4 py-2 text-xs uppercase tracking-widest bg-neon-green/20 border border-neon-green text-neon-green hover:bg-neon-green hover:text-black hover:shadow-[0_0_15px_#39FF14] transition-all rounded disabled:opacity-50">{{ $t('approve_check') }}</button>
                                <button v-if="isAdmin" @click="reject(txn.id)" :disabled="isProcessing" class="flex-1 px-4 py-2 text-xs uppercase tracking-widest bg-neon-red/20 border border-neon-red text-neon-red hover:bg-neon-red hover:text-white hover:shadow-[0_0_15px_#FF0000] transition-all rounded disabled:opacity-50">{{ $t('reject_cross') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="pendingTransactions.length === 0" class="py-12 text-center text-text-muted font-mono text-sm border-dashed border border-white/10 rounded-xl mt-4 w-full">
                    {{ $t('queue_empty') }}
                </div>
            </div>
        </div>

        <!-- TAB 3: Ledger History -->
        <div v-if="activeTab === 2" class="glass-panel p-6 rounded-2xl border border-white/5 animate-[fadeIn_0.5s_ease-out]">
            <h2 class="text-xl font-display font-bold text-white mb-6">{{ $t('ledger_history') }}</h2>
            <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left text-sm font-mono">
                <thead class="text-text-muted border-b border-white/10 uppercase tracking-widest text-[10px]">
                    <tr>
                        <th class="pb-3">{{ $t('date') }}</th>
                        <th class="pb-3">{{ $t('type') }}</th>
                        <th class="pb-3">{{ $t('amount') }}</th>
                        <th class="pb-3">{{ $t('account') }}</th>
                        <th class="pb-3">{{ $t('status') }}</th>
                        <th class="pb-3">{{ $t('checker') }}</th>
                        <th class="pb-3 text-right">{{ $t('receipt') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="txn in historicalTransactions" :key="txn.id" class="border-b border-white/5 hover:bg-white/5 transition-colors">
                        <td class="py-4 text-text-muted">{{ new Date(txn.transaction_date || txn.updated_at).toLocaleDateString() }}</td>
                        <td class="py-4">
                            <span class="px-2 py-1 rounded text-[10px] uppercase tracking-wider" :class="txn.type === 'income' ? 'bg-neon-cyan/20 text-neon-cyan' : 'bg-neon-red/20 text-neon-red'">
                                {{ txn.type }}
                            </span>
                        </td>
                        <td class="py-4 text-white">{{ $currency(txn.amount) }}</td>
                        <td class="py-4">
                            <span class="text-[10px] uppercase font-bold" :class="txn.account_type === 'credit' ? 'text-neon-yellow' : 'text-neon-green'">
                                {{ txn.account_type === 'credit' ? 'KREDIT' : 'MAIN' }}
                            </span>
                        </td>
                        <td class="py-4">
                            <span class="text-xs" :class="txn.status === 'approved' ? 'text-neon-green' : 'text-neon-red'">
                                {{ txn.status }}
                            </span>
                        </td>
                        <td class="py-4 text-text-muted text-xs">{{ txn.approver?.name || 'N/A' }}</td>
                        <td class="py-4 text-right">
                            <button v-if="txn.status === 'approved'" @click="openReceipt(txn)" class="text-neon-cyan hover:text-white hover:underline decoration-neon-cyan transition-all text-xs uppercase tracking-widest">
                                🖨️ {{ $t('view_receipt') }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- TAB 4: Create Transaction -->
        <div v-if="activeTab === 3" class="w-full max-w-[780px] mx-auto glass-panel p-8 rounded-2xl border border-neon-cyan/30 shadow-[0_0_30px_rgba(0,240,255,0.1)] animate-[fadeIn_0.5s_ease-out]">
            <h2 class="text-2xl font-display font-bold text-white mb-2">{{ $t('log_transaction') }}</h2>
            <p class="text-xs font-mono text-text-muted mb-8">{{ $t('log_transaction_desc') }}</p>

            <form @submit.prevent="submitTransaction" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- TYPE (Full width) -->
                <div class="col-span-1 md:col-span-2 flex gap-4">
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" v-model="form.type" value="income" class="hidden peer">
                        <div class="text-center p-4 rounded-xl border border-white/10 peer-checked:border-neon-cyan peer-checked:bg-neon-cyan/10 peer-checked:text-neon-cyan text-text-muted transition-all">
                            📥 {{ $t('income') }}
                        </div>
                    </label>
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" v-model="form.type" value="expense" class="hidden peer">
                        <div class="text-center p-4 rounded-xl border border-white/10 peer-checked:border-neon-red peer-checked:bg-neon-red/10 peer-checked:text-neon-red text-text-muted transition-all">
                            📤 {{ $t('expense') }}
                        </div>
                    </label>
                </div>

                <!-- CATEGORY (Half width) -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="block text-xs font-mono text-text-muted">Kategoriya</label>
                        <button type="button" @click="isNewCategory = !isNewCategory" class="text-[10px] uppercase font-bold text-neon-cyan hover:text-white transition-colors border border-neon-cyan/50 px-2 py-1 rounded">
                            {{ isNewCategory ? 'Ro\'yxatdan tanlash' : '+ Yangi Qo\'shish' }}
                        </button>
                    </div>
                    
                    <div v-if="isNewCategory">
                        <input v-model="form.category_name" type="text" class="glass-input w-full h-12 text-white" placeholder="Yangi kategoriya nomini kiriting" required>
                    </div>
                    <div v-else>
                        <select v-model="form.category_id" class="glass-input w-full h-12 text-white bg-black/50" required>
                            <option value="" disabled>-- Kategoriya tanlang --</option>
                            <option v-for="cat in categories.filter(c => c.type === form.type || c.type === 'both')" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- AMOUNT (Half width) -->
                <div v-show="!requiresMachineOwner && !requiresBrigade">
                    <label class="block text-xs font-mono text-text-muted mb-1">{{ $t('amount') }} (UZS)</label>
                    <input v-model="form.amount" type="number" :required="!requiresMachineOwner && !requiresBrigade" class="glass-input w-full h-12 text-xl font-bold font-mono tracking-wider text-neon-cyan" placeholder="0">
                </div>

                <!-- DYNAMIC FIELDS (Full width) -->
                <div v-if="requiresWorker" class="col-span-1 md:col-span-2">
                    <label class="block text-xs font-mono text-text-muted mb-1">Ishchini Tanlang (Neyron bog'lama)</label>
                    <select v-model="form.worker_id" class="glass-input w-full h-12 text-white bg-black/50" required>
                        <option value="" disabled>-- Tanlang --</option>
                        <option v-for="w in workers" :key="w.id" :value="w.id">{{ w.name }} ({{ w.role }})</option>
                    </select>

                    <div v-if="form.worker_id && isGuardSelected" class="mt-6 border-t border-neon-cyan/20 pt-4">
                        <button type="button" @click.prevent="showGuardLedgerInline = true" class="w-full h-12 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan hover:bg-neon-cyan hover:text-black font-bold uppercase tracking-widest text-xs rounded transition-colors flex items-center justify-center gap-2">
                            💡 QOROVUL JURNALI
                        </button>
                        
                        <GuardLedgerModal 
                            :show="showGuardLedgerInline" 
                            :inline="false" 
                            :guardId="form.worker_id" 
                            @close="showGuardLedgerInline = false" 
                        />
                    </div>
                </div>

                <div v-if="requiresBrigade" class="col-span-1 md:col-span-2 p-6 bg-neon-cyan/5 border border-neon-cyan/30 rounded-xl">
                    <h3 class="text-neon-cyan font-bold uppercase tracking-widest mb-4 flex items-center gap-2">
                        👷 BRIGADA MA'LUMOTLARI
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-mono mb-1 text-neon-cyan">Brigada</label>
                            <select v-model="form.brigade_id" class="glass-input w-full h-12 text-white bg-black/50 border-neon-cyan/50">
                                <option value="">-- Tanlang --</option>
                                <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        
                        <div v-if="form.brigade_id" class="mt-6 border-t border-neon-cyan/20 pt-4">
                            <button type="button" @click.prevent="showBrigadeLedgerInline = true" class="w-full h-12 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan hover:bg-neon-cyan hover:text-black font-bold uppercase tracking-widest text-xs rounded transition-colors flex items-center justify-center gap-2">
                                💡 BRIGADA JURNALI
                            </button>
                            
                            <BrigadeLedgerModal 
                                :show="showBrigadeLedgerInline" 
                                :inline="false" 
                                :brigadeId="form.brigade_id" 
                                @close="showBrigadeLedgerInline = false" 
                            />
                        </div>
                    </div>
                </div>

                <div v-if="requiresPartner || form.account_type === 'partner'" class="col-span-1 md:col-span-2">
                    <label class="block text-xs font-mono text-text-muted mb-1 text-neon-yellow">Ta'sischini Tanlang (Hamkor)</label>
                    <select v-model="form.partner_id" class="glass-input w-full h-12 text-white bg-black/50 border-neon-yellow/50" :required="requiresPartner || form.account_type === 'partner'">
                        <option value="" disabled>-- Tanlang --</option>
                        <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.user?.name }} (Ulush: {{ p.investment_share_percentage }}%)</option>
                    </select>
                </div>

                <div v-if="requiresFixedAsset" class="col-span-1 md:col-span-2">
                    <label class="block text-xs font-mono text-text-muted mb-1 text-neon-cyan">Asosiy Vosita Tanlang (Texnika/Uskuna)</label>
                    <select v-model="form.fixed_asset_id" class="glass-input w-full h-12 text-white bg-black/50 border-neon-cyan/50" required>
                        <option value="" disabled>-- Tanlang --</option>
                        <option v-for="f in fixedAssets" :key="f.id" :value="f.id">{{ f.name }} ({{ f.type }})</option>
                    </select>
                </div>

                <div v-if="requiresMachineOwner" class="col-span-1 md:col-span-2 p-6 bg-neon-yellow/5 border border-neon-yellow/30 rounded-xl">
                    <h3 class="text-neon-yellow font-bold uppercase tracking-widest mb-4 flex items-center gap-2">
                        🔧 TEXNIKA TO'LOVI MA'LUMOTLARI
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-mono mb-1 text-neon-yellow">Texnika Egasi</label>
                            <select v-model="form.machine_owner_id" class="glass-input w-full h-12 text-white bg-black/50 border-neon-yellow/50">
                                <option value="">-- Tanlang --</option>
                                <option v-for="o in machineOwners" :key="o.id" :value="o.id">{{ o.name }}</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-mono mb-1 text-neon-yellow">Texnika</label>
                            <select v-model="form.machinery_id" class="glass-input w-full h-12 text-white bg-black/50 border-neon-yellow/50">
                                <option value="" disabled>-- Tanlang --</option>
                                <option v-for="m in machineries.filter(x => !form.machine_owner_id || x.machine_owner_id === form.machine_owner_id)" :key="m.id" :value="m.id">{{ m.name }} ({{ m.license_plate }})</option>
                            </select>
                        </div>

                        <div v-if="form.machine_owner_id" class="mt-6 border-t border-neon-yellow/20 pt-4">
                            <button type="button" @click.prevent="showOwnerLedgerInline = true" class="w-full h-12 bg-neon-yellow/20 border border-neon-yellow text-neon-yellow hover:bg-neon-yellow hover:text-black font-bold uppercase tracking-widest text-xs rounded transition-colors flex items-center justify-center gap-2">
                                💡 TEXNIKA EGASI JURNALI
                            </button>
                            
                            <OwnerLedgerModal 
                                :show="showOwnerLedgerInline" 
                                :inline="false" 
                                :ownerId="form.machine_owner_id" 
                                @close="showOwnerLedgerInline = false" 
                            />
                        </div>
                    </div>
                </div>

                <div v-if="requiresSupplier" class="col-span-1 md:col-span-2">
                    <label class="block text-xs font-mono text-text-muted mb-1">Ta'minotchini Tanlang (Neyron bog'lama)</label>
                    <select v-model="form.supplier_id" class="glass-input w-full h-12 text-white bg-black/50" required>
                        <option value="" disabled>-- Tanlang --</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.company_name || s.name }}</option>
                    </select>
                </div>

                <div v-if="requiresBuilding" class="col-span-1 md:col-span-2">
                    <label class="block text-xs font-mono text-text-muted mb-1">Bino/Obyektni Tanlang (Neyron bog'lama)</label>
                    <select v-model="form.building_id" class="glass-input w-full h-12 text-white bg-black/50" required>
                        <option value="" disabled>-- Tanlang --</option>
                        <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>

                <!-- ACCOUNT (Half width) -->
                <div v-show="!requiresMachineOwner && !requiresBrigade">
                    <label class="block text-xs font-mono text-text-muted mb-1">Hisob (Account)</label>
                    <select v-model="form.account_type" class="glass-input w-full h-12 text-base font-bold text-white bg-black/50">
                        <option value="main">{{ $t('main_account') }}</option>
                        <option value="bank">Bank Hisobi (Bank)</option>
                        <option value="credit">Kredit Hisobi (Credit)</option>
                        <option value="partner">Ta'sischi Hisobi (Partner)</option>
                    </select>
                </div>

                <!-- PAYMENT METHOD (Half width) -->
                <div v-show="!requiresMachineOwner && !requiresBrigade">
                    <label class="block text-xs font-mono text-text-muted mb-1">{{ $t('payment_method') }}</label>
                    <select v-model="form.payment_method" class="glass-input w-full h-12 text-white bg-black/50">
                        <option value="cash">{{ $t('cash') }}</option>
                        <option value="card">{{ $t('card') }}</option>
                        <option value="bank_transfer">{{ $t('bank_transfer') }}</option>
                    </select>
                </div>

                <!-- DATE (Half width) -->
                <div v-show="!requiresMachineOwner && !requiresBrigade">
                    <label class="block text-xs font-mono text-text-muted mb-1">Sana va Vaqt</label>
                    <input v-model="form.transaction_date" type="datetime-local" :required="!requiresMachineOwner && !requiresBrigade" class="glass-input w-full h-12 text-white bg-black/50 [color-scheme:dark]">
                </div>

                <!-- FILE (Half width) -->
                <div>
                    <label class="block text-xs font-mono text-text-muted mb-1">Hujjat / Chek biriktirish (Ixtiyoriy)</label>
                    <input type="file" @change="handleFileUpload" accept="image/*,.pdf" class="glass-input w-full h-12 text-sm text-text-muted file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-neon-cyan/20 file:text-neon-cyan hover:file:bg-neon-cyan/30 pt-[10px]">
                </div>

                <!-- CONTRACT (Full width conditionally) -->
                <div class="col-span-1 md:col-span-2 relative" v-if="form.type === 'income' || requiresClient">
                    <label class="block text-xs font-mono mb-1" :class="requiresClient ? 'text-neon-cyan font-bold' : 'text-text-muted'">
                        Qaysi Shartnomaga <span v-if="requiresClient">* (Qidirib tanlang)</span><span v-else>(Ixtiyoriy)</span>
                    </label>
                    <div class="relative">
                        <input 
                            v-model="scheduleSearch" 
                            @focus="showScheduleDropdown = true" 
                            @blur="handleBlur"
                            type="text" 
                            class="glass-input w-full h-12 text-white bg-black/50" 
                            placeholder="Ism yoki Kvartira bo'yicha qidiring..."
                        >
                        <button v-if="form.contract_id" @click.prevent="form.contract_id=''; scheduleSearch=''" class="absolute right-3 top-1/2 -translate-y-1/2 text-neon-red text-xl leading-none">&times;</button>
                    </div>
                    
                    <div v-if="showScheduleDropdown && filteredContracts.length > 0" class="absolute z-50 w-full mt-1 bg-[#111] border border-white/20 rounded-xl shadow-2xl max-h-60 overflow-y-auto custom-scrollbar">
                        <div 
                            v-for="c in filteredContracts" 
                            :key="c.id" 
                            @mousedown.prevent="selectContract(c)"
                            class="p-3 hover:bg-neon-cyan/20 cursor-pointer border-b border-white/5 last:border-0 transition-colors"
                        >
                            <div class="flex justify-between items-center text-sm">
                                <span class="font-bold text-white">{{ c.client?.first_name }} {{ c.client?.last_name }}</span>
                                <span class="text-neon-cyan">{{ $currency(c.total_amount) }}</span>
                            </div>
                            <div class="text-xs text-text-muted flex justify-between mt-1 font-mono">
                                <span>Uy: {{ c.unit?.unit_number || 'N/A' }}</span>
                                <span v-if="c.status === 'cancelled'" class="text-[10px] text-neon-red bg-neon-red/10 px-1 rounded uppercase">Bekor qilingan</span>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="showScheduleDropdown && scheduleSearch" class="absolute z-50 w-full mt-1 bg-[#111] border border-white/20 rounded-xl p-4 text-center text-sm text-text-muted">
                        Natija topilmadi
                    </div>
                </div>

                <!-- NOTES (Full width) -->
                <div class="col-span-1 md:col-span-2">
                    <label class="block text-xs font-mono text-text-muted mb-1">Izohlar</label>
                    <textarea v-model="form.notes" rows="3" class="glass-input w-full text-white bg-black/50 pt-3" placeholder="Maqsad va tafsilotlar..."></textarea>
                </div>

                <!-- AUTO APPROVE (Full width) -->
                <div class="col-span-1 md:col-span-2 flex items-center justify-between p-4 rounded-xl border border-white/10 bg-black/30">
                    <span class="text-sm font-mono text-white">Avtomatik tasdiqlash</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="form.auto_approve" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-neon-green"></div>
                    </label>
                </div>

                <!-- SUBMIT (Full width) -->
                <button type="submit" :disabled="isProcessing" class="col-span-1 md:col-span-2 w-full h-12 text-sm bg-neon-cyan/20 border border-neon-cyan text-neon-cyan rounded-xl hover:bg-neon-cyan hover:text-black transition-all font-mono uppercase tracking-widest font-bold">
                    <span v-if="isProcessing">Yuklanmoqda...</span>
                    <span v-else>{{ form.auto_approve ? "Tasdiqlash va Saqlash" : "Tasdiqlashga Yuborish" }}</span>
                </button>
            </form>
        </div>

        <!-- TAB 5: Bank Krediti va Taminotchilar qarzida (Financial Liabilities) -->
        <div v-if="activeTab === 4" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-display font-bold text-white mb-2">{{ $t('qarzlar') || 'Kreditlar va Qarzlar' }}</h2>
                <button @click="openNewLiabilityModal" class="px-4 py-2 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan rounded-lg hover:bg-neon-cyan hover:text-black font-mono text-sm tracking-widest transition-all">
                    + YANGI QARZ QO'SHISH
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Active Liabilities List -->
                <div v-for="liability in financialLiabilities" :key="liability.id" class="glass-panel p-6 rounded-2xl border border-white/10 relative overflow-hidden group">
                    <div class="absolute right-0 top-0 p-4 flex gap-2">
                        <button @click="openEditLiability(liability)" class="px-3 py-1 rounded bg-white/10 hover:bg-white/20 text-white text-[10px] uppercase tracking-widest font-mono transition-all">
                            ✏️ Tahrirlash
                        </button>
                        <button @click="deleteLiability(liability.id)" class="px-3 py-1 rounded bg-red-500/10 hover:bg-red-500/20 text-red-400 hover:text-red-300 text-[10px] uppercase tracking-widest font-mono transition-all" title="O'chirish">
                            🗑️
                        </button>
                        <span class="px-3 py-1 rounded text-[10px] uppercase tracking-widest font-mono" :class="liability.status === 'active' ? 'bg-neon-yellow/20 text-neon-yellow' : 'bg-neon-green/20 text-neon-green'">
                            {{ liability.status }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-2xl">{{ liability.type === 'bank_loan' ? '🏦' : '🏭' }}</span>
                        <div>
                            <h3 class="text-lg text-white font-bold">{{ liability.creditor_name }}</h3>
                            <p class="text-[10px] font-mono text-text-muted uppercase">{{ liability.type }}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-[10px] font-mono text-text-muted uppercase mb-1">Umumiy Qarz</p>
                            <p class="text-xl text-white font-bold">{{ $currency(liability.total_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-mono text-text-muted uppercase mb-1">Oylik To'lov</p>
                            <p class="text-lg text-neon-cyan">{{ liability.monthly_payment ? $currency(liability.monthly_payment) : 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-mono text-text-muted uppercase mb-1">Foiz</p>
                            <p class="text-sm text-white">{{ liability.interest_rate ? liability.interest_rate + '%' : '0%' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-mono text-text-muted uppercase mb-1">Muddat</p>
                            <p class="text-sm text-white">{{ liability.term_months }} oy <span v-if="liability.end_date">({{ new Date(liability.end_date).toLocaleDateString() }}gacha)</span></p>
                        </div>
                    </div>

                    <!-- Payment Progress -->
                    <div class="mb-4">
                        <div class="flex justify-between text-[10px] font-mono text-text-muted mb-1">
                            <span>To'langan: {{ $currency((liability.payments || []).filter(p => p.status === 'paid').reduce((a, b) => a + Number(b.amount), 0)) }}</span>
                            <span>Qoldiq: {{ $currency(liability.total_amount - (liability.payments || []).filter(p => p.status === 'paid').reduce((a, b) => a + Number(b.amount), 0)) }}</span>
                        </div>
                        <div class="w-full bg-white/5 rounded-full h-1.5">
                            <div class="bg-neon-green h-1.5 rounded-full" :style="{ width: Math.min(100, ((liability.payments || []).filter(p => p.status === 'paid').reduce((a, b) => a + Number(b.amount), 0) / (liability.total_amount || 1)) * 100) + '%' }"></div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <div class="flex gap-2">
                            <button v-if="liability.status === 'active'" @click="openPaymentModal(liability)" class="flex-1 py-2 bg-neon-green/20 border border-neon-green/30 text-neon-green rounded text-[10px] font-bold font-mono uppercase hover:bg-neon-green hover:text-black transition-all">
                                To'lov Qilish
                            </button>
                            <button @click="openScheduleModal(liability)" class="flex-1 py-2 bg-neon-cyan/10 border border-neon-cyan/30 text-neon-cyan rounded text-[10px] font-bold font-mono uppercase hover:bg-neon-cyan hover:text-black transition-all">
                                📅 To'lov Grafigi
                            </button>
                        </div>

                        <!-- Export Buttons -->
                        <div class="flex justify-between gap-2 mt-2 border-t border-white/10 pt-4">
                            <a :href="route('admin.finance.liabilities.export.pdf', liability.id)" target="_blank" class="flex-1 py-1.5 text-center bg-red-500/10 border border-red-500/30 text-red-400 rounded text-[10px] font-mono uppercase hover:bg-red-500 hover:text-white transition-all">
                                📄 PDF
                            </a>
                            <a :href="route('admin.finance.liabilities.export.docx', liability.id)" target="_blank" class="flex-1 py-1.5 text-center bg-blue-500/10 border border-blue-500/30 text-blue-400 rounded text-[10px] font-mono uppercase hover:bg-blue-500 hover:text-white transition-all">
                                📝 Word
                            </a>
                            <a :href="route('admin.finance.liabilities.export.xlsx', liability.id)" target="_blank" class="flex-1 py-1.5 text-center bg-green-500/10 border border-green-500/30 text-green-400 rounded text-[10px] font-mono uppercase hover:bg-green-500 hover:text-white transition-all">
                                📊 Excel
                            </a>
                        </div>
                    </div>
                </div>
                
                <div v-if="!financialLiabilities || financialLiabilities.length === 0" class="col-span-full py-12 text-center text-text-muted font-mono text-sm border-dashed border border-white/10 rounded-xl">
                    Hozircha qarzlar va kreditlar mavjud emas.
                </div>
            </div>
        </div>

        <!-- TAB 5: Bank Krediti va Taminotchilar qarzida (Financial Liabilities) -->
        <div v-if="activeTab === 5" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-display font-bold text-white mb-2">Ta'minotchilar (Qarzlar Ledger)</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="s in suppliers" :key="s.id" class="glass-panel p-6 rounded-2xl border border-white/10 hover:border-neon-yellow/50 transition-all group">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-white font-display">{{ s.company_name || s.name }}</h3>
                            <p class="text-[10px] font-mono text-text-muted uppercase mt-1">Vakil: {{ s.name }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="openEditSupplierModal(s)" class="text-neon-cyan hover:scale-110 transition-transform" title="Tahrirlash">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </button>
                            <button @click="deleteSupplier(s.id)" class="text-neon-red hover:scale-110 transition-transform" title="O'chirish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="pt-4 border-t border-white/10 flex justify-between items-center mb-6">
                        <span class="text-[10px] font-mono text-text-muted uppercase">Joriy Balans</span>
                        <div class="text-right">
                            <span class="block text-xl font-bold font-mono" :class="s.balance > 0 ? 'text-neon-red' : (s.balance < 0 ? 'text-neon-green' : 'text-white')">
                                {{ $currency(Math.abs(s.balance)) }}
                            </span>
                            <span class="text-[10px] font-mono uppercase tracking-widest" :class="s.balance > 0 ? 'text-neon-red/70' : (s.balance < 0 ? 'text-neon-green/70' : 'text-text-muted')">
                                {{ s.balance > 0 ? 'Qarzimiz bor' : (s.balance < 0 ? 'Haqimiz bor' : 'Nol') }}
                            </span>
                        </div>
                    </div>

                    <button v-if="s.balance > 0" @click="openSupplierPaymentModal(s)" class="w-full py-3 bg-neon-green/20 border border-neon-green text-neon-green font-bold font-mono text-sm uppercase tracking-widest rounded-xl hover:bg-neon-green hover:text-black transition-all">{{ $t('pay_liability') }}</button>
                    <button v-else-if="s.balance < 0" class="w-full py-3 bg-white/5 border border-white/10 text-white font-bold font-mono text-sm uppercase tracking-widest rounded-xl cursor-not-allowed opacity-50">{{ $t('withdraw_money') }}</button>
                </div>
                
                <div v-if="!suppliers || suppliers.length === 0" class="col-span-full py-12 text-center text-text-muted font-mono text-sm border-dashed border border-white/10 rounded-xl">{{ $t('no_suppliers') }}</div>
            </div>
        </div>

            <!-- TAB 6: Ichki O'tkazma / Naqdlashtirish -->
            <div v-if="activeTab === 6" class="w-full space-y-6 animate-[fadeIn_0.5s_ease-out]">
                
                <!-- KUTAYOTGAN SO'ROVLAR -->
                <div v-if="pendingRefunds && pendingRefunds.length > 0" class="glass-panel p-6 rounded-2xl border border-neon-yellow/30 shadow-[0_0_20px_rgba(252,238,9,0.1)]">
                    <h3 class="text-lg font-display font-bold text-white mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-neon-yellow animate-pulse"></span>{{ $t('pending_withdrawals') }}</h3>
                    <div class="space-y-3">
                        <div v-for="refund in pendingRefunds" :key="refund.id" class="flex justify-between items-center bg-black/40 p-4 rounded-xl border border-white/5 hover:border-neon-yellow/50 transition-all">
                            <div>
                                <p class="text-sm font-bold text-white font-mono">Shartnoma #{{ refund.contract_id }}</p>
                                <p class="text-[10px] text-text-muted mt-1 uppercase tracking-widest">{{ refund.notes }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-neon-yellow font-bold font-mono">{{ $currency(refund.amount) }}</span>
                                <button @click="fillTransferFromRefund(refund)" class="px-4 py-2 bg-neon-yellow/20 text-neon-yellow border border-neon-yellow rounded font-mono text-xs uppercase tracking-widest hover:bg-neon-yellow hover:text-black transition-all">{{ $t('accept') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="glass-panel p-4 md:p-8 rounded-2xl border border-neon-cyan/30 shadow-[0_0_30px_rgba(0,240,255,0.1)]">
                    <h2 class="text-2xl font-display font-bold text-white mb-2">{{ $t('internal_transfer_withdrawal') }}</h2>
                    <p class="text-xs font-mono text-text-muted mb-8">Bu modul orqali siz mijozlarning hisob raqamga tushirgan pullarini boshqa hisoblarga (Naqd kassa) komissiyasiz yoki komissiya ushlab o'tkazishingiz mumkin.</p>

                    <form @submit.prevent="submitTransfer" class="space-y-6">
                    <div>
                        <label class="block text-xs font-mono text-text-muted uppercase mb-2">{{ $t('link_client_contract') }}</label>
                        <select v-model="transferForm.contract_id" class="glass-input w-full bg-black/50 text-white font-bold h-12">
                            <option value="">Hech qaysi (Faqat Ichki O'tkazma)</option>
                            <option v-for="c in contracts" :key="c.id" :value="c.id">
                                {{ c.client?.first_name }} {{ c.client?.last_name }} - {{ c.unit?.unit_number }} (Shartnoma #{{ c.id }})
                            </option>
                        </select>
                        <p class="text-[10px] text-text-muted mt-1 font-mono">Agar mijoz tanlansa, kassadan yechilgan pul (99 mln) mijozning hisobidan (qaytim sifatida) yechiladi va balans to'g'rilanadi.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-xs font-mono text-text-muted uppercase mb-2">{{ $t('transfer_amount') }}</label>
                            <input v-model="transferForm.amount" type="number" step="0.01" class="glass-input w-full text-3xl font-display text-white font-bold h-16 bg-black/50" placeholder="0.00" required>
                        </div>

                        <div>
                            <label class="block text-xs font-mono text-text-muted uppercase mb-2">{{ $t('commission_fee') }}</label>
                            <input v-model="transferForm.fee_amount" type="number" step="0.01" class="glass-input w-full text-2xl font-display text-neon-yellow font-bold h-16 bg-black/50" placeholder="Masalan: 100000">
                        </div>
                    </div>

                    <div class="flex items-center gap-4 py-4">
                        <div class="flex-1">
                            <label class="block text-xs font-mono text-text-muted uppercase mb-2">{{ $t('from_account') }}</label>
                            <select v-model="transferForm.from_account" class="glass-input w-full bg-black/50 text-white font-bold h-12" required>
                                <option value="bank">{{ $t('bank_account') }}</option>
                                <option value="main">{{ $t('cash_account') }}</option>
                                <option value="credit">{{ $t('card_account') }}</option>
                            </select>
                        </div>

                        <div class="w-12 h-12 rounded-full border border-white/20 flex items-center justify-center text-xl mt-6 text-neon-cyan shrink-0 animate-pulse">
                            🔄
                        </div>

                        <div class="flex-1">
                            <label class="block text-xs font-mono text-text-muted uppercase mb-2">{{ $t('to_account') }}</label>
                            <select v-model="transferForm.to_account" class="glass-input w-full bg-black/50 text-white font-bold h-12" required>
                                <option value="main">{{ $t('cash_account') }}</option>
                                <option value="bank">{{ $t('bank_account') }}</option>
                                <option value="credit">{{ $t('card_account') }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-text-muted uppercase mb-2">{{ $t('notes') }}</label>
                        <textarea v-model="transferForm.notes" rows="3" class="glass-input w-full bg-black/50 text-white" placeholder="Masalan: Falonchi uchun qaytim (Naqdlashtirish)"></textarea>
                    </div>

                    <!-- O'tkazma hisob-kitobi xulosasi -->
                    <div class="glass-card p-4 border border-neon-cyan/20 bg-neon-cyan/5 rounded-xl">
                        <h3 class="text-xs font-mono text-text-muted uppercase mb-2">{{ $t('transfer_summary') }}</h3>
                        <div class="space-y-1 font-mono text-sm">
                            <div class="flex justify-between text-neon-red">
                                <span>Jami yechiladigan summa:</span>
                                <span>{{ transferForm.amount ? $currency(transferForm.amount) : '0 UZS' }}</span>
                            </div>
                            <div class="flex justify-between text-neon-yellow">
                                <span>Komissiya (Sizga qoladigan foyda):</span>
                                <span>{{ transferForm.fee_amount ? $currency(transferForm.fee_amount) : '0 UZS' }}</span>
                            </div>
                            <div class="flex justify-between border-t border-white/10 pt-1 mt-1 text-neon-green font-bold text-base">
                                <span>Manzilga borib tushadigan sof summa:</span>
                                <span>{{ $currency((transferForm.amount || 0) - (transferForm.fee_amount || 0)) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-6 border-t border-white/10">
                        <button type="submit" :disabled="isProcessing" class="px-8 py-3 bg-neon-cyan text-black font-bold font-mono uppercase tracking-widest rounded-xl hover:shadow-[0_0_20px_#00F0FF] transition-all disabled:opacity-50 text-sm">
                            {{ isProcessing ? 'O\'tkazilmoqda...' : 'Tasdiqlash va O\'tkazish' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB 7: Kategoriya Hisoboti -->
            <div v-if="activeTab === 7" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-display font-bold text-white mb-2">Hisobotlar (Kategoriya bo'yicha)</h2>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div v-for="cat in categoriesSummary" :key="cat.name" class="glass-panel p-6 rounded-2xl border border-white/10 relative overflow-hidden group">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-xl font-bold text-white font-display uppercase tracking-widest">{{ cat.name }}</h3>
                            <div class="flex gap-2">
                                <a :href="route('admin.finance.transactions.export.category.excel', { category: cat.name })" target="_blank" class="px-2 py-1 bg-green-500/20 text-green-400 border border-green-500/50 rounded text-[10px] font-mono hover:bg-green-500 hover:text-white transition-colors">EXCEL</a>
                                <a :href="route('admin.finance.transactions.export.category.pdf', { category: cat.name })" target="_blank" class="px-2 py-1 bg-red-500/20 text-red-400 border border-red-500/50 rounded text-[10px] font-mono hover:bg-red-500 hover:text-white transition-colors">PDF</a>
                            </div>
                        </div>
                        <div class="space-y-2 font-mono text-sm">
                            <div class="flex justify-between text-neon-cyan" v-if="cat.total_income > 0">
                                <span>Kirim (Jami):</span>
                                <span>{{ $currency(cat.total_income) }}</span>
                            </div>
                            <div class="flex justify-between text-neon-red" v-if="cat.total_expense > 0">
                                <span>Chiqim (Jami):</span>
                                <span>{{ $currency(cat.total_expense) }}</span>
                            </div>
                            <div class="text-xs text-text-muted mt-2 pt-2 border-t border-white/10">
                                Tranzaksiyalar soni: {{ cat.transactions.length }} ta
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="categoriesSummary.length === 0" class="py-12 text-center text-text-muted font-mono text-sm border-dashed border border-white/10 rounded-xl">
                    Hozircha hisobot mavjud emas.
                </div>
            </div>
        </div>
    </div>

        <!-- Edit Supplier Modal -->
        <div v-if="showEditSupplierModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="glass-panel max-w-md w-full p-6 rounded-2xl relative border border-neon-cyan/50 shadow-[0_0_30px_rgba(0,240,255,0.15)]">
                <button @click="showEditSupplierModal = false" class="absolute top-4 right-4 text-white/50 hover:text-white">✕</button>
                <h3 class="text-xl font-display font-bold text-white mb-6 uppercase tracking-widest">Tahrirlash: {{ editSupplierForm.company_name || editSupplierForm.name }}</h3>
                
                <form @submit.prevent="submitEditSupplier" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Kompaniya nomi</label>
                        <input v-model="editSupplierForm.company_name" type="text" class="glass-input w-full text-sm font-bold text-white" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Vakil F.I.O</label>
                        <input v-model="editSupplierForm.name" type="text" class="glass-input w-full text-sm text-white" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Joriy Balans (UZS)</label>
                        <input v-model="editSupplierForm.balance" type="number" step="0.01" class="glass-input w-full text-lg font-bold font-mono text-neon-cyan" required>
                        <p class="text-[10px] text-neon-yellow mt-1 font-mono">Diqqat: Balansni qo'lda o'zgartirish faqat to'lov bekor qilinganda yoki boshqa zarurat tug'ilganda amalga oshirilishi kerak!</p>
                    </div>
                    
                    <div class="pt-4 border-t border-white/10 mt-6">
                        <button type="submit" :disabled="isProcessing" class="w-full py-3 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan font-bold font-mono text-sm uppercase tracking-widest rounded-xl hover:bg-neon-cyan hover:text-black transition-all">Saqlash</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Supplier Payment Modal -->
        <div v-if="showSupplierPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="glass-panel max-w-md w-full p-6 rounded-2xl relative border border-neon-green/50 shadow-[0_0_30px_rgba(57,255,20,0.15)]">
                <button @click="showSupplierPaymentModal = false" class="absolute top-4 right-4 text-white/50 hover:text-white">✕</button>
                <h3 class="text-xl font-display font-bold text-white mb-6 uppercase tracking-widest">To'lov: {{ selectedSupplier?.company_name || selectedSupplier?.name }}</h3>
                
                <form @submit.prevent="submitSupplierPayment" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('payment_amount_uzs') }}</label>
                        <input v-model="supplierPaymentForm.amount" type="number" step="0.01" class="glass-input w-full text-2xl font-bold font-mono text-neon-green" required>
                        <p class="text-[10px] text-text-muted mt-1 font-mono">Qarz: {{ $currency(selectedSupplier?.balance || 0) }}</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('which_account_withdraw') }}</label>
                        <select v-model="supplierPaymentForm.account_type" class="glass-input w-full text-lg text-white" required>
                            <option value="main">{{ $t('main_cash') }}</option>
                            <option value="bank">{{ $t('bank_account_label') }}</option>
                            <option value="credit">{{ $t('credit_cash') }}</option>
                            <option value="debt">{{ $t('liability_cash') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('notes') }}</label>
                        <textarea v-model="supplierPaymentForm.notes" class="glass-input w-full" rows="3" placeholder="Sement uchun to'lov..."></textarea>
                    </div>
                    
                    <div class="flex justify-end pt-4 gap-4 border-t border-white/10 mt-4">
                        <button type="button" @click="showSupplierPaymentModal = false" class="px-6 py-2 text-text-muted hover:text-white font-mono text-sm">{{ $t('cancel_button') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-neon-green text-black rounded font-bold font-mono text-sm hover:shadow-[0_0_15px_#39FF14] transition-all disabled:opacity-50 uppercase tracking-widest">
                            {{ isProcessing ? 'To\'lanmoqda...' : 'To\'lovni Tasdiqlash' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- RECEIPT MODAL -->
        <div v-if="showReceiptModal && receiptData" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-white text-black p-4 md:p-8 rounded border border-gray-300 w-full max-w-sm shadow-[0_0_50px_rgba(255,255,255,0.2)]">
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-bold font-display uppercase tracking-widest">{{ $t('sher_dor') }}</h2>
                    <p class="text-xs font-mono text-gray-500">{{ $t('receipt') }}</p>
                </div>
                
                <div class="space-y-2 text-sm font-mono border-y border-dashed border-gray-300 py-4 mb-6">
                    <div class="flex justify-between"><span>{{ $t('date') }}:</span> <span>{{ new Date(receiptData.updated_at).toLocaleDateString() }}</span></div>
                    <div class="flex justify-between" v-if="receiptData.contract?.client || receiptData.paymentSchedule?.contract?.client">
                        <span>{{ $t('client_label') }}</span> 
                        <span class="font-bold text-right">
                            {{ (receiptData.contract || receiptData.paymentSchedule?.contract).client.first_name }} 
                            {{ (receiptData.contract || receiptData.paymentSchedule?.contract).client.last_name }}
                        </span>
                    </div>
                    <div class="flex justify-between" v-if="receiptData.contract?.unit || receiptData.paymentSchedule?.contract?.unit">
                        <span>{{ $t('object_label') }}</span> 
                        <span class="font-bold text-right">
                            {{ (receiptData.contract || receiptData.paymentSchedule?.contract).unit.unit_number }}
                        </span>
                    </div>
                    <div class="flex justify-between"><span>{{ $t('type') }}:</span> <span class="uppercase">{{ receiptData.payment_method }}</span></div>
                    <div class="flex justify-between"><span>{{ $t('maker') }}:</span> <span>{{ receiptData.creator?.name }}</span></div>
                    <div class="flex justify-between"><span>{{ $t('checker') }}:</span> <span>{{ receiptData.approver?.name }}</span></div>
                </div>

                <div class="flex justify-between items-end mb-6">
                    <span class="text-sm font-mono font-bold">{{ $t('total') }}:</span>
                    <span class="text-3xl font-bold">{{ $currency(receiptData.amount) }}</span>
                </div>

                <div class="flex flex-col items-center border-t border-dashed border-gray-300 pt-6">
                    <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(route('public.receipt.verify', receiptData.receipt_hash))" width="100" height="100" alt="QR Code" class="mb-2">
                    <p class="text-[10px] font-mono mt-2 tracking-widest">{{ receiptData.receipt_hash }}</p>
                </div>

                <div class="mt-8 flex justify-center gap-4">
                    <button @click="showReceiptModal = false" class="px-4 py-2 text-xs border border-gray-400 rounded hover:bg-gray-100 font-mono uppercase">{{ $t('cancel') }}</button>
                    <a :href="`/admin/finance/transactions/${receiptData.id}/print`" target="_blank" class="px-4 py-2 text-xs bg-black text-white rounded hover:bg-gray-800 font-mono uppercase text-center flex items-center">{{ $t('print_button') }}</a>
                </div>
            </div>
        </div>

        <!-- CONTRACT APPROVE MODAL -->
        <div v-if="showContractApproveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-[#111] text-white p-4 md:p-8 rounded-2xl border border-neon-cyan/50 w-full max-w-lg shadow-[0_0_50px_rgba(0,240,255,0.1)]">
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-white/10 pb-4">{{ $t('contract_payment_distribution') }}</h2>
                <div class="mb-6 bg-black/50 p-4 rounded-xl border border-white/5 text-sm font-mono text-text-muted">
                    <p>Kiritilgan summa: <strong class="text-neon-cyan text-lg">{{ $currency(selectedContractTxn?.amount) }}</strong></p>
                </div>
                <form @submit.prevent="submitContractApprove" class="space-y-6 font-mono text-xs">
                    <div>
                        <label class="block text-text-muted mb-2 uppercase">Shartnoma Qarziga Yo'naltirish</label>
                        <input type="number" step="0.01" v-model="contractApproveForm.applied_amount" class="glass-input w-full text-xl font-bold text-white h-12" required max="1000000000000">
                        <p class="text-[10px] text-text-muted mt-2">Bu summa mijozning to'lov grafigidagi qarzini yopishga ketadi.</p>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-2 uppercase">Qaytimga (Naqdlashtirishga) Ajratish</label>
                        <input type="number" step="0.01" v-model="contractApproveForm.refund_amount" class="glass-input w-full text-xl font-bold text-neon-yellow h-12 bg-black/50" readonly>
                        <p class="text-[10px] text-text-muted mt-2">Bu summa "O'tkazmalar" bo'limiga kutayotgan so'rov bo'lib tushadi.</p>
                    </div>
                    <div class="flex justify-end gap-4 mt-8 pt-6 border-t border-white/10">
                        <button type="button" @click="showContractApproveModal = false" class="px-6 py-2 text-text-muted hover:text-white font-mono uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-neon-cyan text-black rounded font-bold uppercase tracking-widest hover:shadow-[0_0_15px_#00F0FF] transition-all disabled:opacity-50">
                            {{ isProcessing ? 'Yuklanmoqda...' : 'Tasdiqlash' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- NEW LIABILITY MODAL -->
        <div v-if="showLiabilityModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-[#111] text-white p-4 md:p-8 rounded-2xl border border-neon-cyan/50 w-full max-w-lg shadow-[0_0_50px_rgba(0,240,255,0.1)]">
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-white/10 pb-4">{{ $t('add_new_liability') }}</h2>
                <form @submit.prevent="submitLiability" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-text-muted mb-1">{{ $t('type_label') }}</label>
                        <select v-model="liabilityForm.type" class="glass-input w-full">
                            <option value="bank_loan">{{ $t('bank_credit') }}</option>
                            <option value="supplier_debt">{{ $t('supplier_debt') }}</option>
                            <option value="partner_debt">{{ $t('partner_debt') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1">{{ $t('creditor_name') }}</label>
                        <input v-model="liabilityForm.creditor_name" type="text" class="glass-input w-full" required>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1 text-neon-yellow">{{ $t('liability_receive_account') }}</label>
                        <select v-model="liabilityForm.fund_account" class="glass-input w-full">
                            <option value="">-- Hisobga tushmasin --</option>
                            <option value="main">{{ $t('main_account') }}</option>
                            <option value="credit">{{ $t('credit_account') }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-text-muted mb-1">{{ $t('total_amount') }}</label>
                            <input v-model="liabilityForm.total_amount" type="number" step="0.01" class="glass-input w-full" required>
                        </div>
                        <div>
                            <label class="block text-text-muted mb-1">{{ $t('annual_percentage') }}</label>
                            <input v-model="liabilityForm.interest_rate" type="number" step="0.01" class="glass-input w-full" placeholder="Masalan: 24">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-text-muted mb-1">{{ $t('term_months') }}</label>
                            <input v-model="liabilityForm.term_months" type="number" class="glass-input w-full" required>
                        </div>
                        <div>
                            <label class="block text-text-muted mb-1">{{ $t('monthly_payment') }}</label>
                            <input v-model="liabilityForm.monthly_payment" type="number" step="0.01" class="glass-input w-full">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-text-muted mb-1">{{ $t('start_date') }}</label>
                            <input v-model="liabilityForm.start_date" type="date" class="glass-input w-full" required>
                        </div>
                        <div>
                            <label class="block text-text-muted mb-1">{{ $t('end_date') }}</label>
                            <input v-model="liabilityForm.end_date" type="date" class="glass-input w-full">
                        </div>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1">{{ $t('notes') }}</label>
                        <textarea v-model="liabilityForm.description" rows="2" class="glass-input w-full"></textarea>
                    </div>
                    <div class="flex justify-end gap-4 pt-4 border-t border-white/10 mt-6">
                        <button type="button" @click="showLiabilityModal = false" class="px-4 py-2 hover:bg-white/10 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan hover:bg-neon-cyan hover:text-black rounded uppercase">{{ $t('save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- PAYMENT MODAL -->
        <div v-if="showPaymentModal && selectedLiability" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-[#111] text-white p-4 md:p-8 rounded-2xl border border-neon-green/50 w-full max-w-sm shadow-[0_0_50px_rgba(57,255,20,0.1)]">
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-white/10 pb-4">{{ $t('liability_payment') }}</h2>
                <div class="mb-6 font-mono text-xs">
                    <p class="text-text-muted mb-1">{{ $t('creditor_label') }}<span class="text-white">{{ selectedLiability.creditor_name }}</span></p>
                    <p class="text-text-muted">{{ $t('balance_remaining_label') }}<span class="text-neon-green text-sm font-bold">{{ $currency(selectedLiability.total_amount - (selectedLiability.payments || []).filter(p => p.status === 'paid').reduce((a, b) => a + Number(b.amount), 0)) }}</span></p>
                </div>
                <form @submit.prevent="submitPayment" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-text-muted mb-1">{{ $t('payment_amount_label') }}</label>
                        <input v-model="paymentForm.amount" type="number" step="0.01" class="glass-input w-full text-lg" required>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1">{{ $t('which_account_pay') }}</label>
                        <select v-model="paymentForm.account_type" class="glass-input w-full" required>
                            <option value="main">{{ $t('main_account') }}</option>
                            <option value="credit">{{ $t('credit_account') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1">{{ $t('date_label') }}</label>
                        <input v-model="paymentForm.payment_date" type="date" class="glass-input w-full" required>
                    </div>
                    <div>
                        <label class="block text-text-muted mb-1">{{ $t('notes') }}</label>
                        <textarea v-model="paymentForm.notes" rows="2" class="glass-input w-full"></textarea>
                    </div>
                    <div class="flex justify-end gap-4 pt-4 border-t border-white/10 mt-6">
                        <button type="button" @click="showPaymentModal = false" class="px-4 py-2 hover:bg-white/10 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-neon-green/20 border border-neon-green text-neon-green hover:bg-neon-green hover:text-black rounded uppercase">{{ $t('pay_button') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- SCHEDULE MODAL -->
        <div v-if="showScheduleModal && selectedLiabilityForSchedule" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-[#111] text-white p-6 rounded-2xl border border-neon-cyan/50 w-full max-w-2xl shadow-[0_0_50px_rgba(0,240,255,0.1)] flex flex-col max-h-[90vh]">
                <div class="flex justify-between items-center mb-6 border-b border-white/10 pb-4">
                    <div>
                        <h2 class="text-xl font-display font-bold uppercase tracking-widest text-neon-cyan">{{ $t('payment_schedule_label') }}</h2>
                        <p class="text-[10px] font-mono text-text-muted mt-1">{{ selectedLiabilityForSchedule.creditor_name }} ({{ selectedLiabilityForSchedule.term_months }} oy, {{ selectedLiabilityForSchedule.interest_rate || 0 }}%)</p>
                    </div>
                    <button @click="showScheduleModal = false" class="text-white hover:text-neon-red">✖</button>
                </div>
                
                <div class="flex-1 overflow-y-auto custom-scrollbar border border-white/5 rounded-xl">
                    <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left font-mono text-xs">
                        <thead class="bg-white/5 sticky top-0 z-10 text-[10px] uppercase text-text-muted">
                            <tr>
                                <th class="p-3">#</th>
                                <th class="p-3">{{ $t('date_label') }}</th>
                                <th class="p-3">{{ $t('main_debt') }}</th>
                                <th class="p-3">Foiz</th>
                                <th class="p-3">{{ $t('total_payment_label') }}</th>
                                <th class="p-3">{{ $t('balance_remaining') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="row in generatedSchedule" :key="row.month" class="hover:bg-white/5 transition-colors">
                                <td class="p-3 text-text-muted">{{ row.month }}</td>
                                <td class="p-3">{{ row.date }}</td>
                                <td class="p-3 text-white">{{ $currency(row.principal) }}</td>
                                <td class="p-3 text-neon-yellow">{{ $currency(row.interest) }}</td>
                                <td class="p-3 text-neon-green font-bold">{{ $currency(row.payment) }}</td>
                                <td class="p-3 text-text-muted">{{ $currency(row.balance) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 pt-4 border-t border-white/10 text-right">
                    <button @click="showScheduleModal = false" class="px-6 py-2 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan hover:bg-neon-cyan hover:text-black rounded uppercase font-mono text-xs font-bold transition-all">{{ $t('close_button') }}</button>
                </div>
            </div>
    </div>
        </div>

        <ExpensesBreakdownModal :show="showExpensesModal" @close="showExpensesModal = false" />

        <BrigadeLedgerModal 
            :show="showBrigadeLedger" 
            :brigade-id="selectedBrigadeId" 
            @close="showBrigadeLedger = false" 
        />
        <OwnerLedgerModal 
            :show="showOwnerLedger" 
            :owner-id="selectedOwnerId" 
            @close="showOwnerLedger = false" 
        />
    </AdminLayout>
</template>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
