<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';

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
    clients: Array
});

const activeTab = ref(0);
const isProcessing = ref(false);

const tabs = [
    { id: 0, name: 'dashboard', icon: '🏦' },
    { id: 1, name: 'action_center', icon: '⚖️' },
    { id: 3, name: 'create_transaction', icon: '💸' },
    { id: 4, name: 'qarzlar', icon: '📉' },
    { id: 5, name: 'Ta\'minotchilar', icon: '🏢' },
    { id: 6, name: 'O\'tkazmalar', icon: '🔄' }
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
    let result = props.transactions;
    if (legacyFilter.value === 'native') {
        result = result.filter(t => !t.is_legacy);
    } else if (legacyFilter.value === 'legacy') {
        result = result.filter(t => t.is_legacy);
    }
    return result;
});

const pendingTransactions = computed(() => filteredTransactions.value.filter(t => t.status === 'pending'));
const historicalTransactions = computed(() => filteredTransactions.value.filter(t => t.status !== 'pending'));

const recentIncomes = computed(() => historicalTransactions.value.filter(t => t.type === 'income'));
const recentExpenses = computed(() => historicalTransactions.value.filter(t => t.type === 'expense'));
const totalApprovedExpenses = computed(() => recentExpenses.value.reduce((sum, t) => sum + parseFloat(t.amount || 0), 0));

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
    machinery_id: '',
    building_id: '',
    supplier_id: '',
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

const requiresWorker = computed(() => selectedCategoryName.value.includes('oylik') || selectedCategoryName.value.includes('avans') || selectedCategoryName.value.includes('ishchi'));
const requiresMachinery = computed(() => selectedCategoryName.value.includes('texnika'));
const requiresSupplier = computed(() => selectedCategoryName.value.includes('ta\'minotchi') || selectedCategoryName.value.includes('qarz qaytarish'));
const requiresBuilding = computed(() => selectedCategoryName.value.includes('obyekt') || selectedCategoryName.value.includes('qurilish material'));
const requiresClient = computed(() => selectedCategoryName.value.includes('mijoz'));
const requiresPartner = computed(() => selectedCategoryName.value.includes('ta\'sischi') || selectedCategoryName.value.includes('hamkor') || selectedCategoryName.value.includes('partner'));

const pendingRefunds = computed(() => {
    return props.transactions.filter(t => t.category?.name === 'Kutayotgan Naqdlashtirish' && t.status === 'pending');
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
    
    let baseContracts = props.contracts;
    
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

function submitTransaction() {
    if (requiresClient.value && !form.value.contract_id) {
        alert("Iltimos, ushbu kategoriya uchun aniq bir Mijoz Shartnomasini tanlang (Qidiruv qatoridan).");
        return;
    }

    isProcessing.value = true;
    form.value.latitude = gpsCoords.value.latitude;
    form.value.longitude = gpsCoords.value.longitude;
    
    // Auto-fill target_type and target_id based on requires flags
    if (requiresPartner.value) {
        form.value.target_type = 'partner';
        form.value.target_id = form.value.partner_id;
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
            form.value = { type: 'income', category_id: '', category_name: '', amount: '', currency: 'UZS', payment_method: 'cash', account_type: 'main', contract_id: '', client_id: '', worker_id: '', partner_id: '', machinery_id: '', building_id: '', supplier_id: '', transaction_date: new Date().toLocaleString('sv').replace(' ', 'T').slice(0,16), attachment: null, auto_approve: true, notes: '', latitude: null, longitude: null, source: 'admin_panel', target_type: null, target_id: null };
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
    const txn = props.transactions.find(t => t.id === id);
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
</script>

<template>
    <Head :title="$t('finance_core')" />
    <AdminLayout>
        
        <div class="mb-8">
            <h1 class="text-3xl font-display font-bold text-white tracking-widest uppercase shadow-neon-green/50 text-shadow-glow flex items-center gap-3">
                <span class="text-4xl drop-shadow-[0_0_15px_rgba(57,255,20,0.6)]">🏦</span> {{ $t('finance_core') }}
            </h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            
            <!-- Sidebar Navigation -->
            <div class="space-y-4">
                <button 
                    v-for="tab in tabs" :key="tab.id"
                    @click="activeTab = tab.id"
                    class="w-full glass-card p-4 rounded-2xl flex items-center gap-4 transition-all group"
                    :class="activeTab === tab.id ? 'border-neon-primary bg-white/5' : 'border-white/5 hover:border-white/20 hover:bg-black/40'"
                >
                    <div class="w-10 h-10 rounded-xl bg-black/50 border flex items-center justify-center transition-colors shrink-0"
                         :class="activeTab === tab.id ? 'border-neon-primary text-neon-primary' : 'border-white/10 group-hover:border-white/30 text-white'">
                        <span class="text-xl">{{ tab.icon }}</span>
                    </div>
                    <div class="text-left">
                        <h3 class="font-display font-bold uppercase tracking-widest text-sm transition-colors"
                            :class="activeTab === tab.id ? 'text-neon-primary' : 'text-white'">
                            {{ $t(tab.name) }}
                        </h3>
                    </div>
                </button>
                
                <!-- Unified Finance Sub-Navigation (Tasdiqlar, Operatsiyalar Tarixi, Cheklar) -->
                <div class="pt-4 border-t border-white/10 space-y-4">
                    <Link :href="route('admin.approvals')" class="w-full glass-card p-4 rounded-2xl flex items-center gap-4 transition-all group border-white/5 hover:border-neon-yellow/50 hover:bg-neon-yellow/5">
                        <div class="w-10 h-10 rounded-xl bg-black/50 border border-white/10 group-hover:border-neon-yellow flex items-center justify-center text-white group-hover:text-neon-yellow shrink-0 transition-colors">
                            <span class="text-xl">✅</span>
                        </div>
                        <div class="text-left">
                            <h3 class="font-display font-bold uppercase tracking-widest text-sm text-text-muted group-hover:text-neon-yellow transition-colors">
                                {{ $t('Tasdiqlar') || 'Tasdiqlar' }}
                            </h3>
                        </div>
                    </Link>

                    <button 
                        @click="activeTab = 2"
                        class="w-full glass-card p-4 rounded-2xl flex items-center gap-4 transition-all group"
                        :class="activeTab === 2 ? 'border-white bg-white/5' : 'border-white/5 hover:border-white/50 hover:bg-white/5'"
                    >
                        <div class="w-10 h-10 rounded-xl bg-black/50 border flex items-center justify-center transition-colors shrink-0"
                             :class="activeTab === 2 ? 'border-white text-white' : 'border-white/10 group-hover:border-white text-white'">
                            <span class="text-xl">📜</span>
                        </div>
                        <div class="text-left">
                            <h3 class="font-display font-bold uppercase tracking-widest text-sm transition-colors"
                                :class="activeTab === 2 ? 'text-white' : 'text-text-muted group-hover:text-white'">
                                {{ $t('ledger_history') }}
                            </h3>
                        </div>
                    </button>

                    <Link :href="route('admin.receipts')" class="w-full glass-card p-4 rounded-2xl flex items-center gap-4 transition-all group border-white/5 hover:border-neon-cyan/50 hover:bg-neon-cyan/5">
                        <div class="w-10 h-10 rounded-xl bg-black/50 border border-white/10 group-hover:border-neon-cyan flex items-center justify-center text-white group-hover:text-neon-cyan shrink-0 transition-colors">
                            <span class="text-xl">🧾</span>
                        </div>
                        <div class="text-left">
                            <h3 class="font-display font-bold uppercase tracking-widest text-sm text-text-muted group-hover:text-neon-cyan transition-colors">
                                {{ $t('Cheklar') || 'Cheklar' }}
                            </h3>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Content Area -->
            <div class="lg:col-span-3">

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
            <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Incomes -->
                <div class="glass-panel p-6 rounded-2xl border border-neon-cyan/20 h-[500px] flex flex-col">
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
                    <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-4">
                        <div v-for="txn in recentIncomes" :key="txn.id" class="p-4 rounded-xl bg-black/40 border border-white/5 hover:border-white/20 transition-all flex flex-col gap-2 relative">
                            <span v-if="txn.is_legacy" class="absolute top-2 left-2 px-1 py-0.5 rounded text-[8px] font-bold bg-neon-purple/20 text-neon-purple border border-neon-purple/50 uppercase shadow-[0_0_5px_rgba(176,38,255,0.4)]">{{ $t('imported') }}</span>
                            <div class="flex justify-between items-start mt-2">
                                <span class="text-xs text-text-muted">{{ new Date(txn.transaction_date || txn.updated_at).toLocaleDateString() }}</span>
                                <span class="text-lg font-bold text-neon-cyan">+ {{ $currency(txn.amount) }}</span>
                            </div>
                            <div class="text-sm text-white">
                                {{ txn.notes || (txn.payment_schedule_id ? 'Shartnoma to\'lovi' : 'Noma\'lum') }}
                            </div>
                            <div class="flex justify-between items-center mt-2 border-t border-white/10 pt-2">
                                <span class="text-[10px] uppercase font-bold px-2 py-1 rounded" :class="txn.account_type === 'credit' ? 'bg-neon-yellow/20 text-neon-yellow' : 'bg-neon-green/20 text-neon-green'">
                                    {{ txn.account_type === 'credit' ? 'KREDIT' : 'MAIN' }}
                                </span>
                                <div class="flex gap-2">
                                    <button v-if="isAdmin" @click="openEditTransactionModal(txn)" class="text-xs text-blue-400 hover:text-blue-300">{{ $t('edit') }}</button>
                                    <button v-if="isAdmin" @click="deleteTransaction(txn.id)" class="text-xs text-red-400 hover:text-red-300">{{ $t('delete') }}</button>
                                    <a :href="route('admin.finance.transactions.export.pdf', txn.id)" target="_blank" class="text-xs text-gray-400 hover:text-white">{{ $t('download') }}</a>
                                </div>
                            </div>
                        </div>
                        <div v-if="!recentIncomes.length" class="text-center text-text-muted text-sm py-4">Kirimlar mavjud emas</div>
                    </div>
                </div>

                <!-- Expenses -->
                <div class="glass-panel p-6 rounded-2xl border border-neon-red/20 h-[500px] flex flex-col">
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
                    <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-4">
                        <div v-for="txn in recentExpenses" :key="txn.id" class="p-4 rounded-xl bg-black/40 border border-white/5 hover:border-white/20 transition-all flex flex-col gap-2 relative">
                            <span v-if="txn.is_legacy" class="absolute top-2 left-2 px-1 py-0.5 rounded text-[8px] font-bold bg-neon-purple/20 text-neon-purple border border-neon-purple/50 uppercase shadow-[0_0_5px_rgba(176,38,255,0.4)]">{{ $t('imported') }}</span>
                            <div class="flex justify-between items-start mt-2">
                                <span class="text-xs text-text-muted">{{ new Date(txn.transaction_date || txn.updated_at).toLocaleDateString() }}</span>
                                <span class="text-lg font-bold text-neon-red">- {{ $currency(txn.amount) }}</span>
                            </div>
                            <div class="text-sm text-white">
                                {{ txn.notes || 'Noma\'lum xarajat' }}
                            </div>
                            <div class="flex justify-between items-center mt-2 border-t border-white/10 pt-2">
                                <span class="text-[10px] uppercase font-bold px-2 py-1 rounded" :class="txn.account_type === 'credit' ? 'bg-neon-yellow/20 text-neon-yellow' : 'bg-neon-green/20 text-neon-green'">
                                    {{ txn.account_type === 'credit' ? 'KREDIT' : 'MAIN' }}
                                </span>
                                <div class="flex gap-2">
                                    <button v-if="isAdmin" @click="openEditTransactionModal(txn)" class="text-xs text-blue-400 hover:text-blue-300">{{ $t('edit') }}</button>
                                    <button v-if="isAdmin" @click="deleteTransaction(txn.id)" class="text-xs text-red-400 hover:text-red-300">{{ $t('delete') }}</button>
                                    <a :href="route('admin.finance.transactions.export.pdf', txn.id)" target="_blank" class="text-xs text-gray-400 hover:text-white">{{ $t('download') }}</a>
                                </div>
                            </div>
                        </div>
                        <div v-if="!recentExpenses.length" class="text-center text-text-muted text-sm py-4">Chiqimlar mavjud emas</div>
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
        <div v-if="activeTab === 3" class="max-w-4xl mx-auto glass-panel p-4 md:p-8 rounded-2xl border border-neon-cyan/30 shadow-[0_0_30px_rgba(0,240,255,0.1)] animate-[fadeIn_0.5s_ease-out]">
            <h2 class="text-2xl font-display font-bold text-white mb-2">{{ $t('log_transaction') }}</h2>
            <p class="text-xs font-mono text-text-muted mb-8">{{ $t('log_transaction_desc') }}</p>

            <form @submit.prevent="submitTransaction" class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 gap-8">
                <!-- Chap Ustun (Asosiy) -->
                <div class="space-y-6">
                    <div class="flex gap-4">
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

                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-xs font-mono text-text-muted">Kategoriya</label>
                            <button type="button" @click="isNewCategory = !isNewCategory" class="text-[10px] uppercase font-bold text-neon-cyan hover:text-white transition-colors border border-neon-cyan/50 px-2 py-1 rounded">
                                {{ isNewCategory ? 'Ro\'yxatdan tanlash' : '+ Yangi Qo\'shish' }}
                            </button>
                        </div>
                        
                        <div v-if="isNewCategory">
                            <input v-model="form.category_name" type="text" class="glass-input w-full text-white" placeholder="Yangi kategoriya nomini kiriting" required>
                        </div>
                        <div v-else>
                            <select v-model="form.category_id" class="glass-input w-full text-white bg-black/50" required>
                                <option value="" disabled>-- Kategoriya tanlang --</option>
                                <option v-for="cat in categories.filter(c => c.type === form.type || c.type === 'both')" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div v-if="requiresWorker">
                        <label class="block text-xs font-mono text-text-muted mb-1">Ishchini Tanlang (Neyron bog'lama)</label>
                        <select v-model="form.worker_id" class="glass-input w-full text-white bg-black/50" required>
                            <option value="" disabled>-- Tanlang --</option>
                            <option v-for="w in workers" :key="w.id" :value="w.id">{{ w.name }} ({{ w.role }})</option>
                        </select>
                    </div>
                    
                    <div v-if="requiresPartner">
                        <label class="block text-xs font-mono text-text-muted mb-1 text-neon-yellow">Ta'sischini Tanlang (Hamkor)</label>
                        <select v-model="form.partner_id" class="glass-input w-full text-white bg-black/50 border-neon-yellow/50" required>
                            <option value="" disabled>-- Tanlang --</option>
                            <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.user?.name }} (Ulush: {{ p.investment_share_percentage }}%)</option>
                        </select>
                    </div>

                    <div v-if="requiresMachinery">
                        <label class="block text-xs font-mono text-text-muted mb-1">Texnikani Tanlang (Neyron bog'lama)</label>
                        <select v-model="form.machinery_id" class="glass-input w-full text-white bg-black/50" required>
                            <option value="" disabled>-- Tanlang --</option>
                            <option v-for="m in machineries" :key="m.id" :value="m.id">{{ m.name }} ({{ m.license_plate }})</option>
                        </select>
                    </div>

                    <div v-if="requiresSupplier">
                        <label class="block text-xs font-mono text-text-muted mb-1">Ta'minotchini Tanlang (Neyron bog'lama)</label>
                        <select v-model="form.supplier_id" class="glass-input w-full text-white bg-black/50" required>
                            <option value="" disabled>-- Tanlang --</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.company_name || s.name }}</option>
                        </select>
                    </div>

                    <div v-if="requiresBuilding">
                        <label class="block text-xs font-mono text-text-muted mb-1">Bino/Obyektni Tanlang (Neyron bog'lama)</label>
                        <select v-model="form.building_id" class="glass-input w-full text-white bg-black/50" required>
                            <option value="" disabled>-- Tanlang --</option>
                            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-text-muted mb-1">{{ $t('amount') }} (UZS)</label>
                        <input v-model="form.amount" type="number" required class="glass-input w-full text-3xl font-bold font-mono tracking-wider text-neon-cyan" placeholder="0">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-text-muted mb-1">Hisob (Account)</label>
                        <select v-model="form.account_type" class="glass-input w-full text-lg font-bold text-white bg-black/50">
                            <option value="main">{{ $t('main_account') }}</option>
                            <option value="credit">Kredit Hisobi (Credit)</option>
                        </select>
                    </div>
                </div>

                <!-- O'ng Ustun (Qo'shimcha) -->
                <div class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-1">{{ $t('payment_method') }}</label>
                            <select v-model="form.payment_method" class="glass-input w-full text-white bg-black/50">
                                <option value="cash">{{ $t('cash') }}</option>
                                <option value="card">{{ $t('card') }}</option>
                                <option value="bank_transfer">{{ $t('bank_transfer') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-text-muted mb-1">Tranzaksiya Sanasi va Vaqti</label>
                            <input v-model="form.transaction_date" type="datetime-local" required class="glass-input w-full text-white bg-black/50 [color-scheme:dark]">
                        </div>
                    </div>

                    <div class="relative" v-if="form.type === 'income' || requiresClient">
                        <label class="block text-xs font-mono mb-1" :class="requiresClient ? 'text-neon-cyan font-bold' : 'text-text-muted'">
                            Qaysi Shartnomaga <span v-if="requiresClient">* (Qidirib tanlang)</span><span v-else>(Ixtiyoriy)</span>
                        </label>
                        <div class="relative">
                            <input 
                                v-model="scheduleSearch" 
                                @focus="showScheduleDropdown = true" 
                                @blur="handleBlur"
                                type="text" 
                                class="glass-input w-full text-white bg-black/50" 
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

                    <div>
                        <label class="block text-xs font-mono text-text-muted mb-1">Hujjat / Chek biriktirish (Ixtiyoriy)</label>
                        <input type="file" @change="handleFileUpload" accept="image/*,.pdf" class="glass-input w-full text-sm text-text-muted file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-neon-cyan/20 file:text-neon-cyan hover:file:bg-neon-cyan/30">
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-text-muted mb-1">Izohlar</label>
                        <textarea v-model="form.notes" rows="3" class="glass-input w-full text-white bg-black/50" placeholder="Maqsad va tafsilotlar..."></textarea>
                    </div>

                    <div class="flex items-center justify-between p-4 rounded-xl border border-white/10 bg-black/30">
                        <span class="text-sm font-mono text-white">Avtomatik tasdiqlash</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="form.auto_approve" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-neon-green"></div>
                        </label>
                    </div>

                    <button type="submit" :disabled="isProcessing" class="w-full py-4 text-sm bg-neon-cyan/20 border border-neon-cyan text-neon-cyan rounded-xl hover:bg-neon-cyan hover:text-black transition-all font-mono uppercase tracking-widest font-bold">
                        <span v-if="isProcessing">Yuklanmoqda...</span>
                        <span v-else>{{ form.auto_approve ? "Tasdiqlash va Saqlash" : "Tasdiqlashga Yuborish" }}</span>
                    </button>
                </div>
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
            <div v-if="activeTab === 6" class="max-w-4xl mx-auto space-y-6 animate-[fadeIn_0.5s_ease-out]">
                
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

    </AdminLayout>
</template>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
