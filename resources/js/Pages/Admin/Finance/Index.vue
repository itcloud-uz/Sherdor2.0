<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, Link, usePage, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';
import BrigadeLedgerModal from '@/Components/Ledger/BrigadeLedgerModal.vue';
import OwnerLedgerModal from '@/Components/Ledger/OwnerLedgerModal.vue';
import GuardLedgerModal from '@/Components/Ledger/GuardLedgerModal.vue';
import Modal from '@/Components/Modal.vue';

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
    machineOwners: Array,
    registers: Array,
    counterparties: Array,
    invoices: Array,
    moneyTransfers: Array
});

const activeTab = ref(0);
const isProcessing = ref(false);

// Synchronize activeTab with the URL tab parameter reactively
watch(
    () => page.url,
    (newUrl) => {
        try {
            const url = new URL(newUrl, window.location.origin);
            const tabParam = url.searchParams.get('tab');
            if (tabParam !== null) {
                const tabVal = parseInt(tabParam, 10);
                if (activeTab.value !== tabVal) {
                    activeTab.value = tabVal;
                }
            } else {
                activeTab.value = 0; // Default tab is 0
            }
        } catch (e) {
            console.error('URL parse error in Finance tab watcher', e);
        }
    },
    { immediate: true }
);

// Synchronize URL query parameter with activeTab changes inside the component
watch(activeTab, (newTab) => {
    try {
        const url = new URL(window.location.href);
        const currentTab = url.searchParams.get('tab');
        if (currentTab === null || parseInt(currentTab, 10) !== newTab) {
            router.replace(route('admin.finance', { tab: newTab }), {
                preserveState: true,
                preserveScroll: true
            });
        }
    } catch (e) {
        console.error('URL replace error in Finance activeTab watcher', e);
    }
});

const tabs = [
    { id: 0, name: 'dashboard', icon: '🏦' },
    { id: 1, name: 'action_center', icon: '⚖️' },
    { id: 2, name: 'ledger_history', icon: '📜' },
    { id: 3, name: 'create_transaction', icon: '💸' },
    { id: 4, name: 'qarzlar', icon: '📉' },
    { id: 5, name: 'Ta\'minotchilar', icon: '🏢' },
    { id: 6, name: 'O\'tkazmalar', icon: '🔄' },
    { id: 7, name: 'Hisobot (Kategoriya)', icon: '📊' },
    { id: 8, name: 'Kassalar', icon: '🏦' },
    { id: 9, name: 'Kontragentlar', icon: '👥' },
    { id: 10, name: 'Hisob-fakturalar', icon: '📄' }
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
    account_id: '',
    counterparty_id: '',
    invoice_id: '',
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
    source: 'admin_panel',
    exchange_rate: 1.0,
    converted_amount: ''
});

const transferForm = ref({
    from_account_id: '',
    to_account_id: '',
    sent_amount: '',
    exchange_rate: 1.0,
    description: ''
});

// Watch account selection to default the currency
watch(() => form.value.account_id, (newVal) => {
    if (newVal && props.registers) {
        const reg = props.registers.find(r => r.id === newVal);
        if (reg) {
            form.value.currency = reg.currency;
            if (reg.building_id) {
                form.value.building_id = reg.building_id;
            }
        }
    }
});

// Watch counterparty selection to sync polymorphic fields
watch(() => form.value.counterparty_id, (newVal) => {
    if (newVal && props.counterparties) {
        const c = props.counterparties.find(x => x.id === newVal);
        if (c) {
            form.value.client_id = c.type === 'client' ? c.customable_id : '';
            form.value.supplier_id = c.type === 'supplier' ? c.customable_id : '';
            form.value.worker_id = c.type === 'employee' ? c.customable_id : '';
        }
    }
});

// Modals State and CRUD helpers
const showRegisterModal = ref(false);
const registerForm = ref({
    id: null,
    name: '',
    type: 'cash',
    currency: 'UZS',
    building_id: '',
    balance: 0,
    is_active: true
});
const isEditRegister = ref(false);

function openNewRegisterModal() {
    isEditRegister.value = false;
    registerForm.value = { id: null, name: '', type: 'cash', currency: 'UZS', building_id: '', balance: 0, is_active: true };
    showRegisterModal.value = true;
}

function openEditRegisterModal(reg) {
    isEditRegister.value = true;
    registerForm.value = { ...reg, building_id: reg.building_id || '' };
    showRegisterModal.value = true;
}

function submitRegister() {
    isProcessing.value = true;
    if (isEditRegister.value) {
        router.put(route('admin.finance.registers.update', registerForm.value.id), registerForm.value, {
            onSuccess: () => { isProcessing.value = false; showRegisterModal.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.finance.registers.store'), registerForm.value, {
            onSuccess: () => { isProcessing.value = false; showRegisterModal.value = false; },
            onError: () => isProcessing.value = false
        });
    }
}

function deleteRegister(id) {
    if (confirm("Ushbu kassani o'chirishni tasdiqlaysizmi?")) {
        router.delete(route('admin.finance.registers.destroy', id));
    }
}

const counterpartySearch = ref('');

const filteredCounterpartiesList = computed(() => {
    if (!props.counterparties) return [];
    const query = counterpartySearch.value.toLowerCase();
    if (!query) return props.counterparties;
    return props.counterparties.filter(c => 
        (c.name || '').toLowerCase().includes(query) ||
        (c.type || '').toLowerCase().includes(query) ||
        (c.phone || '').toLowerCase().includes(query) ||
        (c.email || '').toLowerCase().includes(query)
    );
});

const invoiceFilters = ref({
    start_date: '',
    end_date: '',
    status: '',
    counterparty_id: '',
    building_id: ''
});

const filteredInvoicesList = computed(() => {
    if (!props.invoices) return [];
    return props.invoices.filter(inv => {
        if (invoiceFilters.value.status && inv.status !== invoiceFilters.value.status) return false;
        if (invoiceFilters.value.counterparty_id && inv.counterparty_id && inv.counterparty_id !== parseInt(invoiceFilters.value.counterparty_id)) return false;
        if (invoiceFilters.value.building_id && inv.building_id && inv.building_id !== parseInt(invoiceFilters.value.building_id)) return false;
        if (invoiceFilters.value.start_date) {
            const invDate = new Date(inv.created_at);
            const startDate = new Date(invoiceFilters.value.start_date);
            startDate.setHours(0,0,0,0);
            if (invDate < startDate) return false;
        }
        if (invoiceFilters.value.end_date) {
            const invDate = new Date(inv.created_at);
            const endDate = new Date(invoiceFilters.value.end_date);
            endDate.setHours(23,59,59,999);
            if (invDate > endDate) return false;
        }
        return true;
    });
});

const invoiceExportUrl = computed(() => {
    return route('admin.finance.invoices.export.xlsx', invoiceFilters.value);
});

const showCounterpartyModal = ref(false);
const counterpartyForm = ref({
    id: null,
    name: '',
    type: 'client',
    phone: '',
    email: '',
    balance: 0
});
const isEditCounterparty = ref(false);

function openNewCounterpartyModal() {
    isEditCounterparty.value = false;
    counterpartyForm.value = { id: null, name: '', type: 'client', phone: '', email: '', balance: 0 };
    showCounterpartyModal.value = true;
}

function openEditCounterpartyModal(c) {
    isEditCounterparty.value = true;
    counterpartyForm.value = { ...c };
    showCounterpartyModal.value = true;
}

function submitCounterparty() {
    isProcessing.value = true;
    if (isEditCounterparty.value) {
        router.put(route('admin.finance.counterparties.update', counterpartyForm.value.id), counterpartyForm.value, {
            onSuccess: () => { isProcessing.value = false; showCounterpartyModal.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.finance.counterparties.store'), counterpartyForm.value, {
            onSuccess: () => { isProcessing.value = false; showCounterpartyModal.value = false; },
            onError: () => isProcessing.value = false
        });
    }
}

function deleteCounterparty(id) {
    if (confirm("Ushbu kontragentni o'chirishni tasdiqlaysizmi?")) {
        router.delete(route('admin.finance.counterparties.destroy', id));
    }
}

const showInvoiceModal = ref(false);
const invoiceForm = ref({
    id: null,
    invoice_number: '',
    counterparty_id: '',
    building_id: '',
    amount: '',
    currency: 'UZS',
    due_date: new Date().toISOString().split('T')[0],
    type: 'monthly_schedule',
    description: ''
});
const isEditInvoice = ref(false);

function openNewInvoiceModal() {
    isEditInvoice.value = false;
    invoiceForm.value = {
        id: null,
        invoice_number: 'INV-' + new Date().getFullYear() + '-' + Math.floor(1000 + Math.random() * 9000),
        counterparty_id: '',
        building_id: '',
        amount: '',
        currency: 'UZS',
        due_date: new Date().toISOString().split('T')[0],
        type: 'monthly_schedule',
        description: ''
    };
    showInvoiceModal.value = true;
}

function openEditInvoiceModal(inv) {
    isEditInvoice.value = true;
    invoiceForm.value = { ...inv, building_id: inv.building_id || '' };
    showInvoiceModal.value = true;
}

function submitInvoice() {
    isProcessing.value = true;
    if (isEditInvoice.value) {
        router.put(route('admin.finance.invoices.update', invoiceForm.value.id), invoiceForm.value, {
            onSuccess: () => { isProcessing.value = false; showInvoiceModal.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.finance.invoices.store'), invoiceForm.value, {
            onSuccess: () => { isProcessing.value = false; showInvoiceModal.value = false; },
            onError: () => isProcessing.value = false
        });
    }
}

function deleteInvoice(id) {
    if (confirm("Ushbu hisob-fakturani o'chirishni tasdiqlaysizmi?")) {
        router.delete(route('admin.finance.invoices.destroy', id));
    }
}

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
            form.value = { 
                type: 'income', 
                category_id: '', 
                category_name: '', 
                amount: '', 
                currency: 'UZS', 
                payment_method: 'cash', 
                account_id: '', 
                counterparty_id: '', 
                invoice_id: '', 
                contract_id: '', 
                client_id: '', 
                worker_id: '',
                brigade_id: '', 
                partner_id: '', 
                machinery_id: '',
                machine_owner_id: '', 
                building_id: '', 
                supplier_id: '', 
                fixed_asset_id: '', 
                transaction_date: new Date().toLocaleString('sv').replace(' ', 'T').slice(0,16), 
                attachment: null, 
                auto_approve: true, 
                notes: '', 
                latitude: null, 
                longitude: null, 
                source: 'admin_panel', 
                target_type: null, 
                target_id: null,
                exchange_rate: 1.0,
                converted_amount: ''
            };
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
            transferForm.value = { 
                from_account_id: '', 
                to_account_id: '', 
                sent_amount: '', 
                exchange_rate: 1.0, 
                description: '' 
            };
            activeTab.value = 0; // Go to Dashbod
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
            

            <!-- Content Area -->
            <div class="w-full overflow-x-hidden">

        <!-- TAB 1: Main Dashboard -->
        <div v-if="activeTab === 0" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- KASSA (CASH) -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border-t-4 border-t-neon-green relative overflow-hidden group">
                    <div class="absolute inset-0 bg-emerald-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-sm font-mono text-gray-500">{{ $t('total_balance') }} (Kassa)</p>
                    <h2 class="text-3xl font-display font-bold text-gray-900 mt-2 drop-shadow-sm">
                        {{ $currency(balance?.total_balance || 0) }}
                    </h2>
                </div>
                
                <!-- BANK ACCOUNT (CREDIT) -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border-t-4 border-t-neon-yellow relative overflow-hidden group">
                    <div class="absolute inset-0 bg-amber-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-sm font-mono text-gray-500 uppercase tracking-widest">Bank Hisobi</p>
                    <h2 class="text-3xl font-display font-bold text-gray-900 mt-2 drop-shadow-sm">
                        {{ $currency(balance?.credit_balance || 0) }}
                    </h2>
                </div>
                
                <!-- PENDING INCOMES -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border-t-4 border-t-neon-cyan relative overflow-hidden group">
                    <div class="absolute inset-0 bg-blue-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-sm font-mono text-gray-500">{{ $t('pending_incomes') }}</p>
                    <h2 class="text-3xl font-display font-bold text-gray-900 mt-2 drop-shadow-sm">
                        + {{ $currency(pendingIncomes || 0) }}
                    </h2>
                </div>

                <!-- PENDING EXPENSES -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border-t-4 border-t-neon-red relative overflow-hidden group">
                    <div class="absolute inset-0 bg-red-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-sm font-mono text-gray-500">{{ $t('pending_expenses') }}</p>
                    <h2 class="text-3xl font-display font-bold text-gray-900 mt-2 drop-shadow-sm">
                        - {{ $currency(pendingExpenses || 0) }}
                    </h2>
                </div>
                
                <div @click="showExpensesModal = true" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border-t-4 border-neon-purple relative overflow-hidden group cursor-pointer hover:bg-neon-purple/5 transition-colors border-gray-100 hover:border-neon-purple/30">
                    <div class="absolute inset-0 bg-neon-purple/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-[10px] font-mono text-gray-500 uppercase tracking-widest flex justify-between">
                        <span>Jami Tasdiqlangan Chiqimlar</span>
                        <span class="text-neon-purple underline">batafsil</span>
                    </p>
                    <h2 class="text-3xl font-display font-bold text-gray-900 mt-2 drop-shadow-sm">
                        - {{ $currency(totalApprovedExpenses || 0) }}
                    </h2>
                </div>
            </div>

            <!-- Transaction Lists -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Incomes -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 md:p-6 rounded-2xl border border-blue-200 h-auto max-h-[500px] flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-display text-gray-900 uppercase tracking-widest flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-blue-600"></span>{{ $t('incomes_from') }}</h3>
                        <!-- LEGACY FILTERS -->
                        <div class="flex bg-gray-50 border border-gray-200 rounded-lg p-1">
                            <button @click="legacyFilter = 'all'" :class="['px-2 py-1 text-[8px] font-mono uppercase rounded transition-all', legacyFilter === 'all' ? ' text-gray-900' : 'text-gray-500 hover:text-gray-900']">{{ $t('all') }}</button>
                            <button @click="legacyFilter = 'native'" :class="['px-2 py-1 text-[8px] font-mono uppercase rounded transition-all', legacyFilter === 'native' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'text-gray-500 hover:text-gray-900']">{{ $t('new_native') }}</button>
                            <button @click="legacyFilter = 'legacy'" :class="['px-2 py-1 text-[8px] font-mono uppercase rounded transition-all', legacyFilter === 'legacy' ? 'bg-neon-purple/20 text-neon-purple border border-neon-purple/30' : 'text-gray-500 hover:text-gray-900']">{{ $t('imported') }}</button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto custom-scrollbar flex flex-col">
                        <table class="w-full text-left text-[12px] font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                    <th class="p-3">Sana</th>
                                    <th class="p-3">Izoh</th>
                                    <th class="p-3">Kategoriya</th>
                                    <th class="p-3">Hisob</th>
                                    <th class="p-3 text-right">Summa</th>
                                    <th class="p-3 text-center">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="txn in paginatedIncomes" :key="txn.id" class="hover: transition-colors group relative bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                    <td class="p-3">
                                        <span v-if="txn.is_legacy" class="mr-1 px-1 py-0.5 rounded text-[8px] font-bold bg-neon-purple/20 text-neon-purple border border-neon-purple/50 uppercase">Import</span>
                                        {{ new Date(txn.transaction_date || txn.updated_at).toLocaleDateString() }}
                                    </td>
                                    <td class="p-3 whitespace-normal max-w-xs">{{ txn.notes || (txn.payment_schedule_id ? 'Shartnoma to\'lovi' : '-') }}</td>
                                    <td class="p-3">{{ txn.category?.name || '-' }}</td>
                                    <td class="p-3">
                                        <span class="text-[10px] uppercase font-bold px-2 py-1 rounded" :class="txn.account_type === 'credit' ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600'">
                                            {{ txn.account_type === 'credit' ? 'KREDIT' : 'MAIN' }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right font-bold text-blue-600 whitespace-nowrap">+ {{ $currency(txn.amount) }}</td>
                                    <td class="p-3 text-center">
                                        <div class="flex justify-center gap-2">
                                            <button v-if="isAdmin" @click="openEditTransactionModal(txn)" class="text-[10px] uppercase text-blue-400 hover:text-blue-300 bg-blue-400/10 px-2 py-1 rounded">Tahrir</button>
                                            <button v-if="isAdmin" @click="deleteTransaction(txn.id)" class="bg-red-50 text-red-700 border border-red-200 rounded-md px-2 py-1 text-xs font-medium">O'chir</button>
                                            <a :href="route('admin.finance.transactions.export.pdf', txn.id)" target="_blank" class="text-[10px] uppercase text-gray-500 hover:text-gray-900 px-2 py-1 rounded">Yuklash</a>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="!paginatedIncomes.length">
                                    <td colspan="6" class="p-4 text-center text-gray-500 text-sm">Kirimlar mavjud emas</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-auto border-t border-gray-200 pt-4 pb-2">
                            <div class="flex justify-between items-center px-4 font-mono">
                                <div class="font-bold text-gray-900 text-sm">JAMI: <span class="text-blue-600">+ {{ $currency(totalIncomeAmount) }}</span></div>
                                <div class="flex items-center gap-4 text-xs">
                                    <button @click="incomePage > 1 && incomePage--" :disabled="incomePage === 1" class="text-gray-900 hover:text-blue-600 disabled:opacity-30">◄</button>
                                    <span>{{ incomePage }} / {{ totalIncomesPages }}</span>
                                    <button @click="incomePage < totalIncomesPages && incomePage++" :disabled="incomePage === totalIncomesPages" class="text-gray-900 hover:text-blue-600 disabled:opacity-30">►</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expenses -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 md:p-6 rounded-2xl border border-red-200 h-auto max-h-[500px] flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-display text-gray-900 uppercase tracking-widest flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-red-600"></span>{{ $t('expenses_to') }}</h3>
                        
                        <div class="flex items-center gap-4">
                            <button @click="showExpensesModal = true" class="px-2 py-1 text-[10px] font-mono uppercase bg-neon-purple/20 text-neon-purple border border-neon-purple/50 rounded hover:bg-neon-purple hover:text-gray-900 transition-colors flex items-center gap-1 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 0l-2 2a1 1 0 101.414 1.414L8 10.414l1.293 1.293a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                                Analitika
                            </button>
                            <!-- LEGACY FILTERS -->
                            <div class="flex bg-gray-50 border border-gray-200 rounded-lg p-1">
                                <button @click="legacyFilter = 'all'" :class="['px-2 py-1 text-[8px] font-mono uppercase rounded transition-all', legacyFilter === 'all' ? ' text-gray-900' : 'text-gray-500 hover:text-gray-900']">{{ $t('all') }}</button>
                                <button @click="legacyFilter = 'native'" :class="['px-2 py-1 text-[8px] font-mono uppercase rounded transition-all', legacyFilter === 'native' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'text-gray-500 hover:text-gray-900']">{{ $t('new_native') }}</button>
                                <button @click="legacyFilter = 'legacy'" :class="['px-2 py-1 text-[8px] font-mono uppercase rounded transition-all', legacyFilter === 'legacy' ? 'bg-neon-purple/20 text-neon-purple border border-neon-purple/30' : 'text-gray-500 hover:text-gray-900']">{{ $t('imported') }}</button>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto custom-scrollbar flex flex-col">
                        <table class="w-full text-left text-[12px] font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
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
                                <tr v-for="txn in paginatedExpenses" :key="txn.id" class="hover: transition-colors group relative bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                    <td class="p-3">
                                        <span v-if="txn.is_legacy" class="mr-1 px-1 py-0.5 rounded text-[8px] font-bold bg-neon-purple/20 text-neon-purple border border-neon-purple/50 uppercase">Import</span>
                                        {{ new Date(txn.transaction_date || txn.updated_at).toLocaleDateString() }}
                                    </td>
                                    <td class="p-3 whitespace-normal max-w-xs">{{ txn.notes || '-' }}</td>
                                    <td class="p-3">{{ txn.category?.name || '-' }}</td>
                                    <td class="p-3">
                                        <span class="text-[10px] uppercase font-bold px-2 py-1 rounded" :class="txn.account_type === 'credit' ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600'">
                                            {{ txn.account_type === 'credit' ? 'KREDIT' : 'MAIN' }}
                                        </span>
                                    </td>
                                    <td class="p-3 uppercase text-[10px]">{{ txn.payment_method || 'cash' }}</td>
                                    <td class="p-3 text-right font-bold text-red-600 whitespace-nowrap">- {{ $currency(txn.amount) }}</td>
                                    <td class="p-3 text-center">
                                        <div class="flex justify-center gap-2">
                                            <button v-if="isAdmin" @click="openEditTransactionModal(txn)" class="text-[10px] uppercase text-blue-400 hover:text-blue-300 bg-blue-400/10 px-2 py-1 rounded">Tahrir</button>
                                            <button v-if="isAdmin" @click="deleteTransaction(txn.id)" class="bg-red-50 text-red-700 border border-red-200 rounded-md px-2 py-1 text-xs font-medium">O'chir</button>
                                            <a :href="route('admin.finance.transactions.export.pdf', txn.id)" target="_blank" class="text-[10px] uppercase text-gray-500 hover:text-gray-900 px-2 py-1 rounded">Yuklash</a>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="!paginatedExpenses.length">
                                    <td colspan="7" class="p-4 text-center text-gray-500 text-sm">Chiqimlar mavjud emas</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-auto border-t border-gray-200 pt-4 pb-2">
                            <div class="flex justify-between items-center px-4 font-mono">
                                <div class="font-bold text-gray-900 text-sm">JAMI: <span class="text-red-600">- {{ $currency(totalExpenseAmount) }}</span></div>
                                <div class="flex items-center gap-4 text-xs">
                                    <button @click="expensePage > 1 && expensePage--" :disabled="expensePage === 1" class="text-gray-900 hover:text-red-600 disabled:opacity-30">◄</button>
                                    <span>{{ expensePage }} / {{ totalExpensesPages }}</span>
                                    <button @click="expensePage < totalExpensesPages && expensePage++" :disabled="expensePage === totalExpensesPages" class="text-gray-900 hover:text-red-600 disabled:opacity-30">►</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Transaction Modal -->
            <Modal :show="showEditTransactionModal" @close="showEditTransactionModal = false" maxWidth="md">
                <div class="p-6">
                    <button @click="showEditTransactionModal = false" class="absolute top-4 right-4 text-gray-900/50 hover:text-gray-900">✕</button>
                    <h3 class="text-xl font-display text-gray-900 mb-6">{{ $t('edit_transaction') }}</h3>
                    
                    <form @submit.prevent="submitEditTransaction" class="space-y-4">
                        <div>
                            <label class="block text-gray-500 mb-1 text-sm">{{ $t('amount') }}</label>
                            <input v-model="editTransactionForm.amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                        </div>
                        <div>
                            <label class="block text-gray-500 mb-1 text-sm">{{ $t('account') }}</label>
                            <select v-model="editTransactionForm.account_type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                                <option value="main">{{ $t('main_account') }}</option>
                                <option value="credit">{{ $t('credit_account') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-500 mb-1 text-sm">{{ $t('notes_direction') }}</label>
                            <textarea v-model="editTransactionForm.notes" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" rows="3"></textarea>
                        </div>
                        
                        <div class="flex justify-end pt-4 gap-4">
                            <button type="button" @click="showEditTransactionModal = false" class="px-6 py-2 text-gray-500 hover:text-gray-900">{{ $t('cancel') }}</button>
                            <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-blue-600 text-white rounded font-bold hover:shadow-sm disabled:opacity-50">{{ $t('save') }}</button>
                        </div>
                    </form>
                </div>
            </Modal>
        </div>

        <!-- TAB 2: Action Center (Maker/Checker) -->
        <div v-if="activeTab === 1" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-amber-200 shadow-sm">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-display font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-amber-500 animate-pulse"></span>
                        {{ $t('maker_checker_queue') }}
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="txn in pendingTransactions" :key="txn.id" class="p-5 rounded-2xl bg-gray-50 border border-gray-200 hover:border-amber-200 transition-all flex flex-col gap-4 relative overflow-hidden group">
                        <div class="absolute inset-0 bg-amber-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        
                        <div class="flex justify-between items-start z-10">
                            <span class="px-2 py-1 rounded text-[10px] uppercase tracking-wider" :class="txn.type === 'income' ? 'bg-blue-50 text-blue-600' : 'bg-red-50 text-red-600'">
                                {{ txn.type === 'income' ? 'Kirim' : 'Chiqim' }}
                            </span>
                            <span class="text-xs text-gray-500">{{ new Date(txn.transaction_date || txn.updated_at).toLocaleDateString() }}</span>
                        </div>
                        
                        <div class="z-10 flex items-center gap-3">
                            <h3 class="text-2xl font-bold font-display" :class="txn.type === 'income' ? 'text-blue-600' : 'text-red-600'">
                                {{ txn.type === 'income' ? '+' : '-' }} {{ $currency(txn.amount) }}
                            </h3>
                            <span v-if="txn.is_legacy" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-neon-purple/20 text-neon-purple border border-neon-purple/50 uppercase shadow-sm">{{ $t('imported') }}</span>
                        </div>
                        <div class="z-10">
                            <p class="text-[10px] uppercase font-bold mt-1" :class="txn.account_type === 'credit' ? 'text-amber-600' : 'text-emerald-600'">
                                Hisob: {{ txn.account_type === 'credit' ? 'KREDIT (KASSA)' : 'MAIN (KOMPANIYA)' }}
                            </p>
                        </div>
                        
                        <div class="p-3 rounded-lg text-xs font-mono text-gray-900/80 z-10">
                            <p class="mb-1"><span class="text-gray-500">{{ $t('notes_label') }}</span> {{ txn.notes || 'Kiritilmagan' }}</p>
                            <p v-if="txn.payment_schedule_id" class="mb-1"><span class="text-gray-500">{{ $t('client_label') }}</span> {{ txn.payment_schedule?.contract?.client?.first_name }} {{ txn.payment_schedule?.contract?.client?.last_name }}</p>
                            <p><span class="text-gray-500">{{ $t('maker_label') }}</span> <span class="text-gray-900 font-bold">{{ txn.creator?.name }}</span></p>
                            
                            <div class="mt-2 pt-2 border-t border-gray-200">
                                <p class="text-[10px] uppercase font-bold tracking-widest flex items-center gap-1"
                                   :class="{ 'text-blue-400': txn.source === 'telegram', 'text-neon-purple': txn.source === 'admin_panel' || !txn.source, 'text-amber-600': txn.source === 'partner_app' }">
                                    <span v-if="txn.source === 'telegram'">{{ $t('source_telegram') }}</span>
                                    <span v-else-if="txn.source === 'partner_app'">{{ $t('source_partner_app') }}</span>
                                    <span v-else>{{ $t('source_admin_panel') }}</span>
                                </p>
                                
                                <p v-if="txn.latitude && txn.longitude" class="mt-1">
                                    <a :href="'https://maps.google.com/?q=' + txn.latitude + ',' + txn.longitude" target="_blank" class="text-[10px] text-blue-600 hover:text-gray-900 flex items-center gap-1 transition-colors">{{ $t('exact_gps_location') }}</a>
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex flex-col gap-2 mt-auto z-10 pt-2 border-t border-gray-200">
                            <!-- Bog'lanish Button -->
                            <a :href="'tel:' + (txn.creator?.phone || '+998900000000')" class="w-full text-center px-4 py-2 text-xs uppercase tracking-widest bg-blue-500/20 border border-blue-500 text-blue-400 hover:bg-blue-500 hover:text-gray-900 transition-all rounded">
                                📞 Bog'lanish {{ txn.creator?.phone ? '('+txn.creator.phone+')' : '' }}
                            </a>
                            
                            <div class="flex gap-2">
                                <button v-if="isAdmin" @click="approve(txn.id)" :disabled="isProcessing" class="flex-1 px-4 py-2 text-xs uppercase tracking-widest bg-emerald-50 border border-emerald-500 text-emerald-600 hover:bg-emerald-600 hover:text-black hover:shadow-sm transition-all rounded disabled:opacity-50">{{ $t('approve_check') }}</button>
                                <button v-if="isAdmin" @click="reject(txn.id)" :disabled="isProcessing" class="flex-1 px-4 py-2 text-xs uppercase tracking-widest bg-red-50 border border-red-500 text-red-600 hover:bg-red-600 hover:text-gray-900 hover:shadow-sm transition-all rounded disabled:opacity-50">{{ $t('reject_cross') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="pendingTransactions.length === 0" class="py-12 text-center text-gray-500 font-mono text-sm border-dashed border border-gray-200 rounded-xl mt-4 w-full">
                    {{ $t('queue_empty') }}
                </div>
            </div>
        </div>

        <!-- TAB 3: Ledger History -->
        <div v-if="activeTab === 2" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-100 animate-[fadeIn_0.5s_ease-out]">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-display font-bold text-gray-900">{{ $t('ledger_history') }}</h2>
                <a :href="route('admin.finance.transactions.export.xlsx')" target="_blank" class="px-4 py-2 bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-2">
                    <span>📊 Excel (CSV) Yuklash</span>
                </a>
            </div>
            <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left text-sm font-mono">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                    <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
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
                    <tr v-for="txn in historicalTransactions" :key="txn.id" class="border-b border-gray-100 hover: transition-colors bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                        <td class="py-4 text-gray-500">{{ new Date(txn.transaction_date || txn.updated_at).toLocaleDateString() }}</td>
                        <td class="py-4">
                            <span class="px-2 py-1 rounded text-[10px] uppercase tracking-wider" :class="txn.type === 'income' ? 'bg-blue-50 text-blue-600' : 'bg-red-50 text-red-600'">
                                {{ txn.type }}
                            </span>
                        </td>
                        <td class="py-4 text-gray-900">{{ $currency(txn.amount) }}</td>
                        <td class="py-4">
                            <span class="text-[10px] uppercase font-bold" :class="txn.account_type === 'credit' ? 'text-amber-600' : 'text-emerald-600'">
                                {{ txn.account_type === 'credit' ? 'KREDIT' : 'MAIN' }}
                            </span>
                        </td>
                        <td class="py-4">
                            <span class="text-xs" :class="txn.status === 'approved' ? 'text-emerald-600' : 'text-red-600'">
                                {{ txn.status }}
                            </span>
                        </td>
                        <td class="py-4 text-gray-500 text-xs">{{ txn.approver?.name || 'N/A' }}</td>
                        <td class="py-4 text-right">
                            <button v-if="txn.status === 'approved'" @click="openReceipt(txn)" class="text-blue-600 hover:text-gray-900 hover:underline decoration-neon-cyan transition-all text-xs uppercase tracking-widest">
                                🖨️ {{ $t('view_receipt') }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- TAB 4: Create Transaction -->
        <div v-if="activeTab === 3" class="w-full max-w-[780px] mx-auto bg-white border border-gray-200 shadow-sm rounded-xl p-8 rounded-2xl border border-blue-200 shadow-sm animate-[fadeIn_0.5s_ease-out]">
            <h2 class="text-2xl font-display font-bold text-gray-900 mb-2">{{ $t('log_transaction') }}</h2>
            <p class="text-xs font-mono text-gray-500 mb-8">{{ $t('log_transaction_desc') }}</p>

            <form @submit.prevent="submitTransaction" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- TYPE (Full width) -->
                <div class="col-span-1 md:col-span-2 flex gap-4">
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" v-model="form.type" value="income" class="hidden peer">
                        <div class="text-center p-4 rounded-xl border border-gray-200 peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-600 text-gray-500 transition-all">
                            📥 {{ $t('income') }}
                        </div>
                    </label>
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" v-model="form.type" value="expense" class="hidden peer">
                        <div class="text-center p-4 rounded-xl border border-gray-200 peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-600 text-gray-500 transition-all">
                            📤 {{ $t('expense') }}
                        </div>
                    </label>
                </div>

                <!-- CASH REGISTER (Half width) -->
                <div>
                    <label class="block text-xs font-mono text-gray-500 mb-1">Kassa / Bank (Account) *</label>
                    <select v-model="form.account_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-sm font-bold text-gray-900 bg-gray-50" required>
                        <option value="" disabled>-- Kassani tanlang --</option>
                        <option v-for="r in registers.filter(x => x.is_active)" :key="r.id" :value="r.id">
                            {{ r.name }} ({{ r.currency }}) - Balans: {{ $currency(r.balance) }} {{ r.currency }}
                        </option>
                    </select>
                </div>

                <!-- CATEGORY (Half width) -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="block text-xs font-mono text-gray-500">Kategoriya</label>
                        <button type="button" @click="isNewCategory = !isNewCategory" class="text-[10px] uppercase font-bold text-blue-600 hover:text-gray-900 transition-colors border border-blue-200 px-2 py-1 rounded">
                            {{ isNewCategory ? 'Ro\'yxatdan tanlash' : '+ Yangi Qo\'shish' }}
                        </button>
                    </div>
                    
                    <div v-if="isNewCategory">
                        <input v-model="form.category_name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-gray-900" placeholder="Yangi kategoriya nomini kiriting" required>
                    </div>
                    <div v-else>
                        <select v-model="form.category_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-gray-900 bg-gray-50" required>
                            <option value="" disabled>-- Kategoriya tanlang --</option>
                            <option v-for="cat in categories.filter(c => c.type === form.type || c.type === 'both')" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- COUNTERPARTY (Half width) -->
                <div>
                    <label class="block text-xs font-mono text-gray-500 mb-1">Kontragent (Mijoz, Ta'minotchi, Xodim)</label>
                    <select v-model="form.counterparty_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-sm text-gray-900 bg-gray-50">
                        <option value="">-- Kontragentni tanlang (Ixtiyoriy) --</option>
                        <option v-for="c in counterparties" :key="c.id" :value="c.id">
                            [{{ c.type.toUpperCase() }}] {{ c.name }}
                        </option>
                    </select>
                </div>

                <!-- AMOUNT (Half width) -->
                <div v-show="!requiresMachineOwner && !requiresBrigade">
                    <label class="block text-xs font-mono text-gray-500 mb-1">Summa (Kassa valyutasida: {{ form.currency || 'UZS' }})</label>
                    <input v-model="form.amount" type="number" step="0.01" :required="!requiresMachineOwner && !requiresBrigade" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-xl font-bold font-mono tracking-wider text-blue-600" placeholder="0">
                </div>

                <!-- INVOICE SELECT (Half width) -->
                <div v-if="form.counterparty_id && invoices.filter(i => i.counterparty_id === form.counterparty_id && i.status !== 'paid').length > 0">
                    <label class="block text-xs font-mono text-gray-500 mb-1">Hisob-faktura (Invoice) bog'lash</label>
                    <select v-model="form.invoice_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-sm text-gray-900 bg-gray-50">
                        <option value="">-- Hisob-faktura bog'lash (Ixtiyoriy) --</option>
                        <option v-for="inv in invoices.filter(i => i.counterparty_id === form.counterparty_id && i.status !== 'paid')" :key="inv.id" :value="inv.id">
                            {{ inv.invoice_number }} (Qoldiq: {{ $currency(inv.amount - inv.paid_amount) }} {{ inv.currency }})
                        </option>
                    </select>
                </div>

                <!-- EXCHANGE RATE & CONVERTED AMOUNT (Half width each, shown when currency is not UZS) -->
                <div v-if="form.currency && form.currency !== 'UZS'">
                    <label class="block text-xs font-mono text-gray-500 mb-1">Valyuta kursi (Exchange Rate) *</label>
                    <input v-model="form.exchange_rate" type="number" step="0.0001" required class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-sm font-bold font-mono text-amber-600 bg-gray-50" placeholder="12700.00">
                </div>
                <div v-if="form.currency && form.currency !== 'UZS'">
                    <label class="block text-xs font-mono text-gray-500 mb-1">Konvertatsiya qilingan summa (UZS) *</label>
                    <input :value="form.amount * form.exchange_rate" type="number" readonly class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-sm font-bold font-mono text-emerald-600 bg-gray-50" placeholder="UZS ekvivalenti">
                </div>

                <!-- DYNAMIC FIELDS (Full width) -->
                <div v-if="requiresWorker" class="col-span-1 md:col-span-2">
                    <label class="block text-xs font-mono text-gray-500 mb-1">Ishchini Tanlang (Neyron bog'lama)</label>
                    <select v-model="form.worker_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-gray-900 bg-gray-50" required>
                        <option value="" disabled>-- Tanlang --</option>
                        <option v-for="w in workers" :key="w.id" :value="w.id">{{ w.name }} ({{ w.role }})</option>
                    </select>

                    <div v-if="form.worker_id && isGuardSelected" class="mt-6 border-t border-blue-200 pt-4">
                        <button type="button" @click.prevent="showGuardLedgerInline = true" class="w-full h-12 bg-blue-50 border border-blue-500 text-blue-600 hover:bg-blue-600 hover:text-black font-bold uppercase tracking-widest text-xs rounded transition-colors flex items-center justify-center gap-2">
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

                <div v-if="requiresBrigade" class="col-span-1 md:col-span-2 p-6 bg-blue-50 border border-blue-200 rounded-xl">
                    <h3 class="text-blue-600 font-bold uppercase tracking-widest mb-4 flex items-center gap-2">
                        👷 BRIGADA MA'LUMOTLARI
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-mono mb-1 text-blue-600">Brigada</label>
                            <select v-model="form.brigade_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-gray-900 bg-gray-50 border-blue-200">
                                <option value="">-- Tanlang --</option>
                                <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        
                        <div v-if="form.brigade_id" class="mt-6 border-t border-blue-200 pt-4">
                            <button type="button" @click.prevent="showBrigadeLedgerInline = true" class="w-full h-12 bg-blue-50 border border-blue-500 text-blue-600 hover:bg-blue-600 hover:text-black font-bold uppercase tracking-widest text-xs rounded transition-colors flex items-center justify-center gap-2">
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
                    <label class="block text-xs font-mono text-gray-500 mb-1 text-amber-600">Ta'sischini Tanlang (Hamkor)</label>
                    <select v-model="form.partner_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-gray-900 bg-gray-50 border-amber-200" :required="requiresPartner || form.account_type === 'partner'">
                        <option value="" disabled>-- Tanlang --</option>
                        <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.user?.name }} (Ulush: {{ p.investment_share_percentage }}%)</option>
                    </select>
                </div>

                <div v-if="requiresFixedAsset" class="col-span-1 md:col-span-2">
                    <label class="block text-xs font-mono text-gray-500 mb-1 text-blue-600">Asosiy Vosita Tanlang (Texnika/Uskuna)</label>
                    <select v-model="form.fixed_asset_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-gray-900 bg-gray-50 border-blue-200" required>
                        <option value="" disabled>-- Tanlang --</option>
                        <option v-for="f in fixedAssets" :key="f.id" :value="f.id">{{ f.name }} ({{ f.type }})</option>
                    </select>
                </div>

                <div v-if="requiresMachineOwner" class="col-span-1 md:col-span-2 p-6 bg-amber-50 border border-amber-200 rounded-xl">
                    <h3 class="text-amber-600 font-bold uppercase tracking-widest mb-4 flex items-center gap-2">
                        🔧 TEXNIKA TO'LOVI MA'LUMOTLARI
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-mono mb-1 text-amber-600">Texnika Egasi</label>
                            <select v-model="form.machine_owner_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-gray-900 bg-gray-50 border-amber-200">
                                <option value="">-- Tanlang --</option>
                                <option v-for="o in machineOwners" :key="o.id" :value="o.id">{{ o.name }}</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-mono mb-1 text-amber-600">Texnika</label>
                            <select v-model="form.machinery_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-gray-900 bg-gray-50 border-amber-200">
                                <option value="" disabled>-- Tanlang --</option>
                                <option v-for="m in machineries.filter(x => !form.machine_owner_id || x.machine_owner_id === form.machine_owner_id)" :key="m.id" :value="m.id">{{ m.name }} ({{ m.license_plate }})</option>
                            </select>
                        </div>

                        <div v-if="form.machine_owner_id" class="mt-6 border-t border-amber-200 pt-4">
                            <button type="button" @click.prevent="showOwnerLedgerInline = true" class="w-full h-12 bg-amber-50 border border-amber-500 text-amber-600 hover:bg-amber-500 hover:text-black font-bold uppercase tracking-widest text-xs rounded transition-colors flex items-center justify-center gap-2">
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
                    <label class="block text-xs font-mono text-gray-500 mb-1">Ta'minotchini Tanlang (Neyron bog'lama)</label>
                    <select v-model="form.supplier_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-gray-900 bg-gray-50" required>
                        <option value="" disabled>-- Tanlang --</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.company_name || s.name }}</option>
                    </select>
                </div>

                <div v-if="requiresBuilding" class="col-span-1 md:col-span-2">
                    <label class="block text-xs font-mono text-gray-500 mb-1">Bino/Obyektni Tanlang (Neyron bog'lama)</label>
                    <select v-model="form.building_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-gray-900 bg-gray-50" required>
                        <option value="" disabled>-- Tanlang --</option>
                        <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>



                <!-- PAYMENT METHOD (Half width) -->
                <div v-show="!requiresMachineOwner && !requiresBrigade">
                    <label class="block text-xs font-mono text-gray-500 mb-1">{{ $t('payment_method') }}</label>
                    <select v-model="form.payment_method" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-gray-900 bg-gray-50">
                        <option value="cash">{{ $t('cash') }}</option>
                        <option value="card">{{ $t('card') }}</option>
                        <option value="bank_transfer">{{ $t('bank_transfer') }}</option>
                    </select>
                </div>

                <!-- DATE (Half width) -->
                <div v-show="!requiresMachineOwner && !requiresBrigade">
                    <label class="block text-xs font-mono text-gray-500 mb-1">Sana va Vaqt</label>
                    <input v-model="form.transaction_date" type="datetime-local" :required="!requiresMachineOwner && !requiresBrigade" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-gray-900 bg-gray-50 [color-scheme:dark]">
                </div>

                <!-- FILE (Half width) -->
                <div>
                    <label class="block text-xs font-mono text-gray-500 mb-1">Hujjat / Chek biriktirish (Ixtiyoriy)</label>
                    <input type="file" @change="handleFileUpload" accept="image/*,.pdf" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-50 pt-[10px]">
                </div>

                <!-- CONTRACT (Full width conditionally) -->
                <div class="col-span-1 md:col-span-2 relative" v-if="form.type === 'income' || requiresClient">
                    <label class="block text-xs font-mono mb-1" :class="requiresClient ? 'text-blue-600 font-bold' : 'text-gray-500'">
                        Qaysi Shartnomaga <span v-if="requiresClient">* (Qidirib tanlang)</span><span v-else>(Ixtiyoriy)</span>
                    </label>
                    <div class="relative">
                        <input 
                            v-model="scheduleSearch" 
                            @focus="showScheduleDropdown = true" 
                            @blur="handleBlur"
                            type="text" 
                            class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-12 text-gray-900 bg-gray-50" 
                            placeholder="Ism yoki Kvartira bo'yicha qidiring..."
                        >
                        <button v-if="form.contract_id" @click.prevent="form.contract_id=''; scheduleSearch=''" class="absolute right-3 top-1/2 -translate-y-1/2 text-red-600 text-xl leading-none">&times;</button>
                    </div>
                    
                    <div v-if="showScheduleDropdown && filteredContracts.length > 0" class="absolute z-50 w-full mt-1 bg-[#111] border border-gray-300 rounded-xl max-h-60 overflow-y-auto custom-scrollbar">
                        <div 
                            v-for="c in filteredContracts" 
                            :key="c.id" 
                            @mousedown.prevent="selectContract(c)"
                            class="p-3 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-0 transition-colors"
                        >
                            <div class="flex justify-between items-center text-sm">
                                <span class="font-bold text-gray-900">{{ c.client?.first_name }} {{ c.client?.last_name }}</span>
                                <span class="text-blue-600">{{ $currency(c.total_amount) }}</span>
                            </div>
                            <div class="text-xs text-gray-500 flex justify-between mt-1 font-mono">
                                <span>Uy: {{ c.unit?.unit_number || 'N/A' }}</span>
                                <span v-if="c.status === 'cancelled'" class="text-[10px] text-red-600 bg-red-50 px-1 rounded uppercase">Bekor qilingan</span>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="showScheduleDropdown && scheduleSearch" class="absolute z-50 w-full mt-1 bg-[#111] border border-gray-300 rounded-xl p-4 text-center text-sm text-gray-500">
                        Natija topilmadi
                    </div>
                </div>

                <!-- NOTES (Full width) -->
                <div class="col-span-1 md:col-span-2">
                    <label class="block text-xs font-mono text-gray-500 mb-1">Izohlar</label>
                    <textarea v-model="form.notes" rows="3" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900 bg-gray-50 pt-3" placeholder="Maqsad va tafsilotlar..."></textarea>
                </div>

                <!-- AUTO APPROVE (Full width) -->
                <div class="col-span-1 md:col-span-2 flex items-center justify-between p-4 rounded-xl border border-gray-200 bg-gray-50">
                    <span class="text-sm font-mono text-gray-900">Avtomatik tasdiqlash</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="form.auto_approve" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>

                <!-- SUBMIT (Full width) -->
                <button type="submit" :disabled="isProcessing" class="col-span-1 md:col-span-2 w-full h-12 text-sm bg-blue-50 border border-blue-500 text-blue-600 rounded-xl hover:bg-blue-600 hover:text-black transition-all font-mono uppercase tracking-widest font-bold">
                    <span v-if="isProcessing">Yuklanmoqda...</span>
                    <span v-else>{{ form.auto_approve ? "Tasdiqlash va Saqlash" : "Tasdiqlashga Yuborish" }}</span>
                </button>
            </form>
        </div>

        <!-- TAB 5: Bank Krediti va Taminotchilar qarzida (Financial Liabilities) -->
        <div v-if="activeTab === 4" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-display font-bold text-gray-900 mb-2">{{ $t('qarzlar') || 'Kreditlar va Qarzlar' }}</h2>
                <button @click="openNewLiabilityModal" class="px-4 py-2 bg-blue-50 border border-blue-500 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-black font-mono text-sm tracking-widest transition-all">
                    + YANGI QARZ QO'SHISH
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Active Liabilities List -->
                <div v-for="liability in financialLiabilities" :key="liability.id" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-200 relative overflow-hidden group">
                    <div class="absolute right-0 top-0 p-4 flex gap-2">
                        <button @click="openEditLiability(liability)" class="px-3 py-1 rounded hover:bg-white/20 text-gray-900 text-[10px] uppercase tracking-widest font-mono transition-all">
                            ✏️ Tahrirlash
                        </button>
                        <button @click="deleteLiability(liability.id)" class="px-3 py-1 rounded bg-red-500/10 hover:bg-red-500/20 text-red-400 hover:text-red-300 text-[10px] uppercase tracking-widest font-mono transition-all" title="O'chirish">
                            🗑️
                        </button>
                        <span class="px-3 py-1 rounded text-[10px] uppercase tracking-widest font-mono" :class="liability.status === 'active' ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600'">
                            {{ liability.status }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-2xl">{{ liability.type === 'bank_loan' ? '🏦' : '🏭' }}</span>
                        <div>
                            <h3 class="text-lg text-gray-900 font-bold">{{ liability.creditor_name }}</h3>
                            <p class="text-[10px] font-mono text-gray-500 uppercase">{{ liability.type }}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-[10px] font-mono text-gray-500 uppercase mb-1">Umumiy Qarz</p>
                            <p class="text-xl text-gray-900 font-bold">{{ $currency(liability.total_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-mono text-gray-500 uppercase mb-1">Oylik To'lov</p>
                            <p class="text-lg text-blue-600">{{ liability.monthly_payment ? $currency(liability.monthly_payment) : 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-mono text-gray-500 uppercase mb-1">Foiz</p>
                            <p class="text-sm text-gray-900">{{ liability.interest_rate ? liability.interest_rate + '%' : '0%' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-mono text-gray-500 uppercase mb-1">Muddat</p>
                            <p class="text-sm text-gray-900">{{ liability.term_months }} oy <span v-if="liability.end_date">({{ new Date(liability.end_date).toLocaleDateString() }}gacha)</span></p>
                        </div>
                    </div>

                    <!-- Payment Progress -->
                    <div class="mb-4">
                        <div class="flex justify-between text-[10px] font-mono text-gray-500 mb-1">
                            <span>To'langan: {{ $currency((liability.payments || []).filter(p => p.status === 'paid').reduce((a, b) => a + Number(b.amount), 0)) }}</span>
                            <span>Qoldiq: {{ $currency(liability.total_amount - (liability.payments || []).filter(p => p.status === 'paid').reduce((a, b) => a + Number(b.amount), 0)) }}</span>
                        </div>
                        <div class="w-full rounded-full h-1.5">
                            <div class="bg-emerald-600 h-1.5 rounded-full" :style="{ width: Math.min(100, ((liability.payments || []).filter(p => p.status === 'paid').reduce((a, b) => a + Number(b.amount), 0) / (liability.total_amount || 1)) * 100) + '%' }"></div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <div class="flex gap-2">
                            <button v-if="liability.status === 'active'" @click="openPaymentModal(liability)" class="flex-1 py-2 bg-emerald-50 border border-emerald-200 text-emerald-600 rounded text-[10px] font-bold font-mono uppercase hover:bg-emerald-600 hover:text-black transition-all">
                                To'lov Qilish
                            </button>
                            <button @click="openScheduleModal(liability)" class="flex-1 py-2 bg-blue-50 border border-blue-200 text-blue-600 rounded text-[10px] font-bold font-mono uppercase hover:bg-blue-600 hover:text-black transition-all">
                                📅 To'lov Grafigi
                            </button>
                        </div>

                        <!-- Export Buttons -->
                        <div class="flex justify-between gap-2 mt-2 border-t border-gray-200 pt-4">
                            <a :href="route('admin.finance.liabilities.export.pdf', liability.id)" target="_blank" class="flex-1 py-1.5 text-center bg-red-500/10 border border-red-200 text-red-400 rounded text-[10px] font-mono uppercase hover:bg-red-500 hover:text-gray-900 transition-all">
                                📄 PDF
                            </a>
                            <a :href="route('admin.finance.liabilities.export.docx', liability.id)" target="_blank" class="flex-1 py-1.5 text-center bg-blue-500/10 border border-blue-200 text-blue-400 rounded text-[10px] font-mono uppercase hover:bg-blue-500 hover:text-gray-900 transition-all">
                                📝 Word
                            </a>
                            <a :href="route('admin.finance.liabilities.export.xlsx', liability.id)" target="_blank" class="flex-1 py-1.5 text-center bg-green-500/10 border border-green-500/30 text-green-400 rounded text-[10px] font-mono uppercase hover:bg-green-500 hover:text-gray-900 transition-all">
                                📊 Excel
                            </a>
                        </div>
                    </div>
                </div>
                
                <div v-if="!financialLiabilities || financialLiabilities.length === 0" class="col-span-full py-12 text-center text-gray-500 font-mono text-sm border-dashed border border-gray-200 rounded-xl">
                    Hozircha qarzlar va kreditlar mavjud emas.
                </div>
            </div>
        </div>

        <!-- TAB 5: Bank Krediti va Taminotchilar qarzida (Financial Liabilities) -->
        <div v-if="activeTab === 5" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-display font-bold text-gray-900 mb-2">Ta'minotchilar (Qarzlar Ledger)</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="s in suppliers" :key="s.id" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-200 hover:border-amber-200 transition-all group">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 font-display">{{ s.company_name || s.name }}</h3>
                            <p class="text-[10px] font-mono text-gray-500 uppercase mt-1">Vakil: {{ s.name }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="openEditSupplierModal(s)" class="text-blue-600 hover:scale-110 transition-transform" title="Tahrirlash">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </button>
                            <button @click="deleteSupplier(s.id)" class="text-red-600 hover:scale-110 transition-transform" title="O'chirish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="pt-4 border-t border-gray-200 flex justify-between items-center mb-6">
                        <span class="text-[10px] font-mono text-gray-500 uppercase">Joriy Balans</span>
                        <div class="text-right">
                            <span class="block text-xl font-bold font-mono" :class="s.balance > 0 ? 'text-red-600' : (s.balance < 0 ? 'text-emerald-600' : 'text-gray-900')">
                                {{ $currency(Math.abs(s.balance)) }}
                            </span>
                            <span class="text-[10px] font-mono uppercase tracking-widest" :class="s.balance > 0 ? 'text-red-600/70' : (s.balance < 0 ? 'text-emerald-600/70' : 'text-gray-500')">
                                {{ s.balance > 0 ? 'Qarzimiz bor' : (s.balance < 0 ? 'Haqimiz bor' : 'Nol') }}
                            </span>
                        </div>
                    </div>

                    <button v-if="s.balance > 0" @click="openSupplierPaymentModal(s)" class="w-full py-3 bg-emerald-50 border border-emerald-500 text-emerald-600 font-bold font-mono text-sm uppercase tracking-widest rounded-xl hover:bg-emerald-600 hover:text-black transition-all">{{ $t('pay_liability') }}</button>
                    <button v-else-if="s.balance < 0" class="w-full py-3 border border-gray-200 text-gray-900 font-bold font-mono text-sm uppercase tracking-widest rounded-xl cursor-not-allowed opacity-50">{{ $t('withdraw_money') }}</button>
                </div>
                
                <div v-if="!suppliers || suppliers.length === 0" class="col-span-full py-12 text-center text-gray-500 font-mono text-sm border-dashed border border-gray-200 rounded-xl">{{ $t('no_suppliers') }}</div>
            </div>
        </div>

            <!-- TAB 6: Ichki O'tkazma / Naqdlashtirish -->
            <div v-if="activeTab === 6" class="w-full space-y-6 animate-[fadeIn_0.5s_ease-out]">
                
                <!-- KUTAYOTGAN SO'ROVLAR -->
                <div v-if="pendingRefunds && pendingRefunds.length > 0" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-amber-200 shadow-sm">
                    <h3 class="text-lg font-display font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>{{ $t('pending_withdrawals') }}</h3>
                    <div class="space-y-3">
                        <div v-for="refund in pendingRefunds" :key="refund.id" class="flex justify-between items-center bg-gray-50 p-4 rounded-xl border border-gray-100 hover:border-amber-200 transition-all">
                            <div>
                                <p class="text-sm font-bold text-gray-900 font-mono">Shartnoma #{{ refund.contract_id }}</p>
                                <p class="text-[10px] text-gray-500 mt-1 uppercase tracking-widest">{{ refund.notes }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-amber-600 font-bold font-mono">{{ $currency(refund.amount) }}</span>
                                <button @click="fillTransferFromRefund(refund)" class="px-4 py-2 bg-amber-50 text-amber-600 border border-amber-500 rounded font-mono text-xs uppercase tracking-widest hover:bg-amber-500 hover:text-black transition-all">{{ $t('accept') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 md:p-8 rounded-2xl border border-blue-200 shadow-sm">
                    <h2 class="text-2xl font-display font-bold text-gray-900 mb-2">{{ $t('internal_transfer_withdrawal') }}</h2>
                    <p class="text-xs font-mono text-gray-500 mb-8">Bu modul orqali siz mijozlarning hisob raqamga tushirgan pullarini boshqa hisoblarga (Naqd kassa) komissiyasiz yoki komissiya ushlab o'tkazishingiz mumkin.</p>

                    <form @submit.prevent="submitTransfer" class="space-y-6">
                        <div class="flex items-center gap-4 py-4">
                            <div class="flex-1">
                                <label class="block text-xs font-mono text-gray-500 uppercase mb-2">Chiqish Kassasi (From Account) *</label>
                                <select v-model="transferForm.from_account_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-50 text-gray-900 font-bold h-12" required>
                                    <option value="" disabled>-- Kassani tanlang --</option>
                                    <option v-for="r in registers.filter(x => x.is_active)" :key="r.id" :value="r.id">
                                        {{ r.name }} ({{ r.currency }}) - Balans: {{ $currency(r.balance) }} {{ r.currency }}
                                    </option>
                                </select>
                            </div>

                            <div class="w-12 h-12 rounded-full border border-gray-300 flex items-center justify-center text-xl mt-6 text-blue-600 shrink-0 animate-pulse">
                                🔄
                            </div>

                            <div class="flex-1">
                                <label class="block text-xs font-mono text-gray-500 uppercase mb-2">Kirish Kassasi (To Account) *</label>
                                <select v-model="transferForm.to_account_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-50 text-gray-900 font-bold h-12" required>
                                    <option value="" disabled>-- Kassani tanlang --</option>
                                    <option v-for="r in registers.filter(x => x.is_active)" :key="r.id" :value="r.id">
                                        {{ r.name }} ({{ r.currency }}) - Balans: {{ $currency(r.balance) }} {{ r.currency }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                            <div>
                                <label class="block text-xs font-mono text-gray-500 uppercase mb-2">O'tkazma Summasi *</label>
                                <input v-model="transferForm.sent_amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-3xl font-display text-gray-900 font-bold h-16 bg-gray-50" placeholder="0.00" required>
                            </div>

                            <div>
                                <label class="block text-xs font-mono text-gray-500 uppercase mb-2">Konvertatsiya Kursi *</label>
                                <input v-model="transferForm.exchange_rate" type="number" step="0.0001" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-2xl font-display text-amber-600 font-bold h-16 bg-gray-50" placeholder="1.0000" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-mono text-gray-500 uppercase mb-2">Tafsilotlar (Izoh)</label>
                            <textarea v-model="transferForm.description" rows="3" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-50 text-gray-900" placeholder="Masalan: Konvertatsiya USD -> UZS, yoki Ichki kassa to'ldirish"></textarea>
                        </div>

                        <!-- O'tkazma hisob-kitobi xulosasi -->
                        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 border border-blue-200 bg-blue-50 rounded-xl">
                            <h3 class="text-xs font-mono text-gray-500 uppercase mb-2">Xulosa</h3>
                            <div class="space-y-1 font-mono text-sm">
                                <div class="flex justify-between text-red-600">
                                    <span>Chiqadigan summa:</span>
                                    <span>{{ transferForm.sent_amount ? transferForm.sent_amount : 0 }} {{ props.registers?.find(r => r.id === transferForm.from_account_id)?.currency || '' }}</span>
                                </div>
                                <div class="flex justify-between text-amber-600">
                                    <span>Konvertatsiya kursi:</span>
                                    <span>{{ transferForm.exchange_rate }}</span>
                                </div>
                                <div class="flex justify-between border-t border-gray-200 pt-1 mt-1 text-emerald-600 font-bold text-base">
                                    <span>Kiradigan summa:</span>
                                    <span>{{ transferForm.sent_amount * transferForm.exchange_rate }} {{ props.registers?.find(r => r.id === transferForm.to_account_id)?.currency || '' }}</span>
                                </div>
                            </div>
                        </div>

                    <div class="flex justify-end pt-6 border-t border-gray-200">
                        <button type="submit" :disabled="isProcessing" class="px-8 py-3 bg-blue-600 text-white font-bold font-mono uppercase tracking-widest rounded-xl hover:shadow-sm transition-all disabled:opacity-50 text-sm">
                            {{ isProcessing ? 'O\'tkazilmoqda...' : 'Tasdiqlash va O\'tkazish' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB 7: Kategoriya Hisoboti -->
            <div v-if="activeTab === 7" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-display font-bold text-gray-900 mb-2">Hisobotlar (Kategoriya bo'yicha)</h2>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div v-for="cat in categoriesSummary" :key="cat.name" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-200 relative overflow-hidden group">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-xl font-bold text-gray-900 font-display uppercase tracking-widest">{{ cat.name }}</h3>
                            <div class="flex gap-2">
                                <a :href="route('admin.finance.transactions.export.category.excel', { category: cat.name })" target="_blank" class="bg-green-50 text-green-700 border border-green-200 rounded-md px-2 py-1 text-xs font-medium">EXCEL</a>
                                <a :href="route('admin.finance.transactions.export.category.pdf', { category: cat.name })" target="_blank" class="bg-red-50 text-red-700 border border-red-200 rounded-md px-2 py-1 text-xs font-medium">PDF</a>
                            </div>
                        </div>
                        <div class="space-y-2 font-mono text-sm">
                            <div class="flex justify-between text-blue-600" v-if="cat.total_income > 0">
                                <span>Kirim (Jami):</span>
                                <span>{{ $currency(cat.total_income) }}</span>
                            </div>
                            <div class="flex justify-between text-red-600" v-if="cat.total_expense > 0">
                                <span>Chiqim (Jami):</span>
                                <span>{{ $currency(cat.total_expense) }}</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-2 pt-2 border-t border-gray-200">
                                Tranzaksiyalar soni: {{ cat.transactions.length }} ta
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="categoriesSummary.length === 0" class="py-12 text-center text-gray-500 font-mono text-sm border-dashed border border-gray-200 rounded-xl">
                    Hozircha hisobot mavjud emas.
                </div>
            </div>

            <!-- TAB 8: Kassalar va Banklar -->
            <div v-if="activeTab === 8" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
                <div class="flex justify-between items-center mb-6 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div>
                        <h2 class="text-2xl font-display font-bold text-gray-900">Kassalar va Bank hisoblari</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1">Real vaqt rejimida kassalar va bank hisoblaridagi qoldiqlar.</p>
                    </div>
                    <button @click="openNewRegisterModal" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold font-mono text-sm uppercase tracking-widest transition-all shadow-sm flex items-center gap-2">
                        <span>+ Yangi Kassa</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="reg in registers" :key="reg.id" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl hover:border-blue-200 transition-all group relative overflow-hidden flex flex-col justify-between">
                        <div class="absolute inset-x-0 top-0 h-1" :class="reg.type === 'bank' ? 'bg-amber-500' : 'bg-emerald-500'"></div>
                        
                        <div>
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono uppercase tracking-wider" :class="reg.type === 'bank' ? 'bg-amber-50 text-amber-600 border border-amber-200' : 'bg-emerald-50 text-emerald-600 border border-emerald-200'">
                                        {{ reg.type === 'bank' ? 'Bank Hisobi' : 'Naqd Kassa' }}
                                    </span>
                                    <h3 class="text-xl font-bold text-gray-900 font-display mt-2 group-hover:text-blue-600 transition-colors">{{ reg.name }}</h3>
                                </div>
                                <span class="px-2 py-1 rounded-md text-xs font-mono font-bold" :class="reg.is_active ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'">
                                    {{ reg.is_active ? 'Faol' : 'Nofaol' }}
                                </span>
                            </div>

                            <div class="py-4 border-y border-gray-100 my-4 flex justify-between items-center">
                                <span class="text-sm font-mono text-gray-500 uppercase">Joriy Balans</span>
                                <div class="text-right">
                                    <span class="block text-2xl font-bold font-mono text-gray-900">
                                        {{ $currency(reg.balance) }} {{ reg.currency }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="reg.building" class="mb-4 text-xs font-mono text-gray-600 flex items-center gap-1.5">
                                <span>🏢 Obyekt:</span>
                                <span class="font-bold text-gray-900">{{ reg.building.name }}</span>
                            </div>
                            <div v-else class="mb-4 text-xs font-mono text-gray-400">
                                🏢 Obyekt: Biriktirilmagan (Umumiy kassa)
                            </div>
                        </div>

                        <div class="flex gap-2 mt-4 pt-4 border-t border-gray-150">
                            <button @click="openEditRegisterModal(reg)" class="flex-1 py-2 bg-blue-50 hover:bg-blue-100 border border-blue-200 text-blue-600 rounded-lg text-xs font-bold uppercase tracking-wider transition-all">
                                Tahrirlash
                            </button>
                            <button @click="deleteRegister(reg.id)" class="px-3 py-2 bg-red-50 hover:bg-red-100 border border-red-200 text-red-600 rounded-lg text-xs font-bold transition-all" title="O'chirish">
                                ✕
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!registers || registers.length === 0" class="py-12 text-center text-gray-500 font-mono text-sm border-dashed border border-gray-200 rounded-xl">
                    Kassalar mavjud emas.
                </div>
            </div>

            <!-- TAB 9: Kontragentlar -->
            <div v-if="activeTab === 9" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div>
                        <h2 class="text-2xl font-display font-bold text-gray-900">Kontragentlar</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1">Mijozlar, ta'minotchilar va xodimlar ro'yxati hamda ularning o'zaro hisob-kitob balansi.</p>
                    </div>
                    <div class="flex gap-3 w-full sm:w-auto">
                        <input v-model="counterpartySearch" type="text" placeholder="Qidiruv..." class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-4 py-2 text-sm w-full sm:w-64">
                        <button @click="openNewCounterpartyModal" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold font-mono text-sm uppercase tracking-widest transition-all shadow-sm flex items-center gap-2 shrink-0">
                            <span>+ Kontragent</span>
                        </button>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden rounded-2xl border border-gray-150">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium border-b border-gray-200">
                                <tr>
                                    <th class="p-4">Nomi / F.I.O</th>
                                    <th class="p-4">Turi</th>
                                    <th class="p-4">Telefon</th>
                                    <th class="p-4">Email</th>
                                    <th class="p-4 text-right">Balans</th>
                                    <th class="p-4 text-center">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="c in filteredCounterpartiesList" :key="c.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 font-bold text-gray-900">{{ c.name }}</td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider" 
                                              :class="c.type === 'client' ? 'bg-blue-50 text-blue-600 border border-blue-200' : (c.type === 'supplier' ? 'bg-amber-50 text-amber-600 border border-amber-200' : 'bg-purple-50 text-purple-600 border border-purple-200')">
                                            {{ c.type === 'client' ? 'Mijoz' : (c.type === 'supplier' ? 'Ta\'minotchi' : 'Xodim') }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-600">{{ c.phone || '-' }}</td>
                                    <td class="p-4 text-gray-600">{{ c.email || '-' }}</td>
                                    <td class="p-4 text-right font-bold" :class="c.balance > 0 ? 'text-emerald-600' : (c.balance < 0 ? 'text-red-600' : 'text-gray-900')">
                                        {{ $currency(c.balance) }} UZS
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex justify-center gap-2">
                                            <button @click="openEditCounterpartyModal(c)" class="text-xs uppercase text-blue-600 hover:text-blue-800 bg-blue-50 border border-blue-100 px-2 py-1 rounded-md font-bold transition-all">Tahrirlash</button>
                                            <button @click="deleteCounterparty(c.id)" class="text-xs uppercase text-red-600 hover:text-red-800 bg-red-50 border border-red-100 px-2 py-1 rounded-md font-bold transition-all">O'chirish</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredCounterpartiesList.length === 0">
                                    <td colspan="6" class="p-8 text-center text-gray-500 text-sm">Kontragentlar topilmadi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 10: Hisob-fakturalar -->
            <div v-if="activeTab === 10" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div>
                        <h2 class="text-2xl font-display font-bold text-gray-900">Hisob-fakturalar (Invoices)</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1">Manual yoki to'lov grafiklaridan shakllangan schyot-fakturalarni boshqarish va to'lovlarini nazorat qilish.</p>
                    </div>
                    <div class="flex gap-3 w-full sm:w-auto">
                        <a :href="invoiceExportUrl" target="_blank" class="px-5 py-2.5 bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 rounded-xl font-bold font-mono text-sm uppercase tracking-widest transition-all shadow-sm flex items-center gap-2">
                            <span>📊 Excel (CSV) yuklash</span>
                        </a>
                        <button @click="openNewInvoiceModal" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold font-mono text-sm uppercase tracking-widest transition-all shadow-sm flex items-center gap-2 shrink-0">
                            <span>+ Yangi Schyot</span>
                        </button>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-150 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Boshlanish sanasi</label>
                        <input v-model="invoiceFilters.start_date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs">
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Tugash sanasi</label>
                        <input v-model="invoiceFilters.end_date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs">
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Kontragent</label>
                        <select v-model="invoiceFilters.counterparty_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs bg-gray-50">
                            <option value="">-- Barchasi --</option>
                            <option v-for="c in counterparties" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Obyekt</label>
                        <select v-model="invoiceFilters.building_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs bg-gray-50">
                            <option value="">-- Barchasi --</option>
                            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Holati</label>
                        <select v-model="invoiceFilters.status" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs bg-gray-50">
                            <option value="">-- Barchasi --</option>
                            <option value="new">Yangi (To'lanmagan)</option>
                            <option value="partially_paid">Kisman To'langan</option>
                            <option value="paid">To'langan</option>
                            <option value="cancelled">Bekor qilingan</option>
                        </select>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden rounded-2xl border border-gray-150">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium border-b border-gray-200">
                                <tr>
                                    <th class="p-4">Faktura raqami</th>
                                    <th class="p-4">Kontragent</th>
                                    <th class="p-4">Obyekt</th>
                                    <th class="p-4 text-right">Summa</th>
                                    <th class="p-4 text-right">To'langan</th>
                                    <th class="p-4">Progress</th>
                                    <th class="p-4">Muddati</th>
                                    <th class="p-4">Holati</th>
                                    <th class="p-4 text-center">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="inv in filteredInvoicesList" :key="inv.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 font-bold text-gray-900">
                                        {{ inv.invoice_number }}
                                        <span class="block text-[9px] text-gray-400 font-normal">Turi: {{ inv.type }}</span>
                                    </td>
                                    <td class="p-4">
                                        <span v-if="inv.counterparty" class="font-bold text-gray-800">{{ inv.counterparty.name }}</span>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="p-4 text-gray-700">{{ inv.building?.name || 'Umumiy' }}</td>
                                    <td class="p-4 text-right font-bold text-gray-900">{{ $currency(inv.amount) }} {{ inv.currency }}</td>
                                    <td class="p-4 text-right font-bold text-emerald-600">{{ $currency(inv.paid_amount) }} {{ inv.currency }}</td>
                                    <td class="p-4">
                                        <div class="w-24 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-emerald-500 h-1.5 rounded-full" :style="{ width: Math.min(100, (inv.paid_amount / inv.amount) * 100) + '%' }"></div>
                                        </div>
                                        <span class="text-[9px] text-gray-500 font-bold block mt-1">{{ Math.round((inv.paid_amount / inv.amount) * 100) }}%</span>
                                    </td>
                                    <td class="p-4 text-gray-600">{{ new Date(inv.due_date).toLocaleDateString() }}</td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider" 
                                              :class="inv.status === 'paid' ? 'bg-green-50 text-green-700 border border-green-200' : (inv.status === 'partially_paid' ? 'bg-amber-50 text-amber-600 border border-amber-200' : (inv.status === 'cancelled' ? 'bg-gray-50 text-gray-400 border border-gray-200' : 'bg-blue-50 text-blue-600 border border-blue-200'))">
                                            {{ inv.status === 'paid' ? 'To\'langan' : (inv.status === 'partially_paid' ? 'Kisman' : (inv.status === 'cancelled' ? 'Bekor qilingan' : 'Yangi')) }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex justify-center gap-2">
                                            <button @click="openEditInvoiceModal(inv)" class="text-xs uppercase text-blue-600 hover:text-blue-800 bg-blue-50 border border-blue-100 px-2 py-1 rounded-md font-bold transition-all">Tahrirlash</button>
                                            <button @click="deleteInvoice(inv.id)" class="text-xs uppercase text-red-600 hover:text-red-800 bg-red-50 border border-red-100 px-2 py-1 rounded-md font-bold transition-all" :disabled="inv.paid_amount > 0" :class="inv.paid_amount > 0 ? 'opacity-50 cursor-not-allowed' : ''">O'chirish</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredInvoicesList.length === 0">
                                    <td colspan="9" class="p-8 text-center text-gray-500 text-sm">Hisob-fakturalar topilmadi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Edit Supplier Modal -->
        <Modal :show="showEditSupplierModal" @close="showEditSupplierModal = false" maxWidth="md">
            <div class="p-6">
                <button @click="showEditSupplierModal = false" class="absolute top-4 right-4 text-gray-900/50 hover:text-gray-900">✕</button>
                <h3 class="text-xl font-display font-bold text-gray-900 mb-6 uppercase tracking-widest">Tahrirlash: {{ editSupplierForm.company_name || editSupplierForm.name }}</h3>
                
                <form @submit.prevent="submitEditSupplier" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Kompaniya nomi</label>
                        <input v-model="editSupplierForm.company_name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-sm font-bold text-gray-900" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Vakil F.I.O</label>
                        <input v-model="editSupplierForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-sm text-gray-900" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Joriy Balans (UZS)</label>
                        <input v-model="editSupplierForm.balance" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-lg font-bold font-mono text-blue-600" required>
                        <p class="text-[10px] text-amber-600 mt-1 font-mono">Diqqat: Balansni qo'lda o'zgartirish faqat to'lov bekor qilinganda yoki boshqa zarurat tug'ilganda amalga oshirilishi kerak!</p>
                    </div>
                    
                    <div class="pt-4 border-t border-gray-200 mt-6">
                        <button type="submit" :disabled="isProcessing" class="w-full py-3 bg-blue-50 border border-blue-500 text-blue-600 font-bold font-mono text-sm uppercase tracking-widest rounded-xl hover:bg-blue-600 hover:text-black transition-all">Saqlash</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Supplier Payment Modal -->
        <Modal :show="showSupplierPaymentModal" @close="showSupplierPaymentModal = false" maxWidth="md">
            <div class="p-6">
                <button @click="showSupplierPaymentModal = false" class="absolute top-4 right-4 text-gray-900/50 hover:text-gray-900">✕</button>
                <h3 class="text-xl font-display font-bold text-gray-900 mb-6 uppercase tracking-widest">To'lov: {{ selectedSupplier?.company_name || selectedSupplier?.name }}</h3>
                
                <form @submit.prevent="submitSupplierPayment" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('payment_amount_uzs') }}</label>
                        <input v-model="supplierPaymentForm.amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-2xl font-bold font-mono text-emerald-600" required>
                        <p class="text-[10px] text-gray-500 mt-1 font-mono">Qarz: {{ $currency(selectedSupplier?.balance || 0) }}</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('which_account_withdraw') }}</label>
                        <select v-model="supplierPaymentForm.account_type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-lg text-gray-900" required>
                            <option value="main">{{ $t('main_cash') }}</option>
                            <option value="bank">{{ $t('bank_account_label') }}</option>
                            <option value="credit">{{ $t('credit_cash') }}</option>
                            <option value="debt">{{ $t('liability_cash') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('notes') }}</label>
                        <textarea v-model="supplierPaymentForm.notes" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" rows="3" placeholder="Sement uchun to'lov..."></textarea>
                    </div>
                    
                    <div class="flex justify-end pt-4 gap-4 border-t border-gray-200 mt-4">
                        <button type="button" @click="showSupplierPaymentModal = false" class="px-6 py-2 text-gray-500 hover:text-gray-900 font-mono text-sm">{{ $t('cancel_button') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-emerald-600 text-white rounded font-bold font-mono text-sm hover:shadow-sm transition-all disabled:opacity-50 uppercase tracking-widest">
                            {{ isProcessing ? 'To\'lanmoqda...' : 'To\'lovni Tasdiqlash' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- RECEIPT MODAL -->
        <Modal :show="showReceiptModal && receiptData !== null" @close="showReceiptModal = false" maxWidth="md">
            <div class="p-6">
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
                    <a :href="`/admin/finance/transactions/${receiptData.id}/print`" target="_blank" class="px-4 py-2 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 font-mono uppercase text-center flex items-center justify-center">{{ $t('print_button') }}</a>
                </div>
            </div>
        </Modal>

        <!-- CONTRACT APPROVE MODAL -->
        <Modal :show="showContractApproveModal" @close="showContractApproveModal = false" maxWidth="lg">
            <div class="p-6">
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-gray-200 pb-4">{{ $t('contract_payment_distribution') }}</h2>
                <div class="mb-6 bg-gray-50/50 p-4 rounded-xl border border-gray-100 text-sm font-mono text-gray-500">
                    <p>Kiritilgan summa: <strong class="text-blue-600 text-lg">{{ $currency(selectedContractTxn?.amount) }}</strong></p>
                </div>
                <form @submit.prevent="submitContractApprove" class="space-y-6 font-mono text-xs">
                    <div>
                        <label class="block text-gray-500 mb-2 uppercase">Shartnoma Qarziga Yo'naltirish</label>
                        <input type="number" step="0.01" v-model="contractApproveForm.applied_amount" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xl font-bold text-gray-900 h-12" required max="1000000000000">
                        <p class="text-[10px] text-gray-500 mt-2">Bu summa mijozning to'lov grafigidagi qarzini yopishga ketadi.</p>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-2 uppercase">Qaytimga (Naqdlashtirishga) Ajratish</label>
                        <input type="number" step="0.01" v-model="contractApproveForm.refund_amount" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xl font-bold text-amber-600 h-12 bg-gray-50" readonly>
                        <p class="text-[10px] text-gray-500 mt-2">Bu summa "O'tkazmalar" bo'limiga kutayotgan so'rov bo'lib tushadi.</p>
                    </div>
                    <div class="flex justify-end gap-4 mt-8 pt-6 border-t border-gray-200">
                        <button type="button" @click="showContractApproveModal = false" class="px-6 py-2 text-gray-500 hover:text-gray-900 font-mono uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-blue-600 text-white rounded font-bold uppercase tracking-widest hover:shadow-sm transition-all disabled:opacity-50">
                            {{ isProcessing ? 'Yuklanmoqda...' : 'Tasdiqlash' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- NEW LIABILITY MODAL -->
        <Modal :show="showLiabilityModal" @close="showLiabilityModal = false" maxWidth="lg">
            <div class="p-6">
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-gray-200 pb-4">{{ $t('add_new_liability') }}</h2>
                <form @submit.prevent="submitLiability" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-gray-500 mb-1">{{ $t('type_label') }}</label>
                        <select v-model="liabilityForm.type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                            <option value="bank_loan">{{ $t('bank_credit') }}</option>
                            <option value="supplier_debt">{{ $t('supplier_debt') }}</option>
                            <option value="partner_debt">{{ $t('partner_debt') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1">{{ $t('creditor_name') }}</label>
                        <input v-model="liabilityForm.creditor_name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1 text-amber-600">{{ $t('liability_receive_account') }}</label>
                        <select v-model="liabilityForm.fund_account" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                            <option value="">-- Hisobga tushmasin --</option>
                            <option value="main">{{ $t('main_account') }}</option>
                            <option value="credit">{{ $t('credit_account') }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-500 mb-1">{{ $t('total_amount') }}</label>
                            <input v-model="liabilityForm.total_amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                        </div>
                        <div>
                            <label class="block text-gray-500 mb-1">{{ $t('annual_percentage') }}</label>
                            <input v-model="liabilityForm.interest_rate" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" placeholder="Masalan: 24">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-500 mb-1">{{ $t('term_months') }}</label>
                            <input v-model="liabilityForm.term_months" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                        </div>
                        <div>
                            <label class="block text-gray-500 mb-1">{{ $t('monthly_payment') }}</label>
                            <input v-model="liabilityForm.monthly_payment" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-500 mb-1">{{ $t('start_date') }}</label>
                            <input v-model="liabilityForm.start_date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                        </div>
                        <div>
                            <label class="block text-gray-500 mb-1">{{ $t('end_date') }}</label>
                            <input v-model="liabilityForm.end_date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                        </div>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1">{{ $t('notes') }}</label>
                        <textarea v-model="liabilityForm.description" rows="2" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full"></textarea>
                    </div>
                    <div class="flex justify-end gap-4 pt-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="showLiabilityModal = false" class="px-4 py-2 hover:bg-gray-100 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-blue-600 text-white font-bold font-mono text-xs rounded hover:bg-blue-700 transition-colors">{{ $t('save') }}</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- PAYMENT MODAL -->
        <Modal :show="showPaymentModal && selectedLiability !== null" @close="showPaymentModal = false" maxWidth="sm">
            <div class="p-6">
                <h2 class="text-xl font-display font-bold uppercase tracking-widest mb-6 border-b border-gray-200 pb-4">{{ $t('liability_payment') }}</h2>
                <div class="mb-6 font-mono text-xs">
                    <p class="text-gray-500 mb-1">{{ $t('creditor_label') }}<span class="text-gray-900">{{ selectedLiability.creditor_name }}</span></p>
                    <p class="text-gray-500">{{ $t('balance_remaining_label') }}<span class="text-emerald-600 text-sm font-bold">{{ $currency(selectedLiability.total_amount - (selectedLiability.payments || []).filter(p => p.status === 'paid').reduce((a, b) => a + Number(b.amount), 0)) }}</span></p>
                </div>
                <form @submit.prevent="submitPayment" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-gray-500 mb-1">{{ $t('payment_amount_label') }}</label>
                        <input v-model="paymentForm.amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-lg" required>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1">{{ $t('which_account_pay') }}</label>
                        <select v-model="paymentForm.account_type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                            <option value="main">{{ $t('main_account') }}</option>
                            <option value="credit">{{ $t('credit_account') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1">{{ $t('date_label') }}</label>
                        <input v-model="paymentForm.payment_date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                    </div>
                    <div>
                        <label class="block text-gray-500 mb-1">{{ $t('notes') }}</label>
                        <textarea v-model="paymentForm.notes" rows="2" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full"></textarea>
                    </div>
                    <div class="flex justify-end gap-4 pt-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="showPaymentModal = false" class="px-4 py-2 hover:bg-gray-100 rounded uppercase">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-emerald-50 border border-emerald-500 text-emerald-600 hover:bg-emerald-600 hover:text-black rounded uppercase">{{ $t('pay_button') }}</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- SCHEDULE MODAL -->
        <Modal :show="showScheduleModal && selectedLiabilityForSchedule !== null" @close="showScheduleModal = false" maxWidth="2xl">
            <div class="p-6 flex flex-col max-h-[90vh]">
                <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
                    <div>
                        <h2 class="text-xl font-display font-bold uppercase tracking-widest text-blue-600">{{ $t('payment_schedule_label') }}</h2>
                        <p class="text-[10px] font-mono text-gray-500 mt-1">{{ selectedLiabilityForSchedule.creditor_name }} ({{ selectedLiabilityForSchedule.term_months }} oy, {{ selectedLiabilityForSchedule.interest_rate || 0 }}%)</p>
                    </div>
                    <button @click="showScheduleModal = false" class="text-gray-900 hover:text-red-600">✖</button>
                </div>
                
                <div class="flex-1 overflow-y-auto custom-scrollbar border border-gray-100 rounded-xl">
                    <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left font-mono text-xs">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                            <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                <th class="p-3">#</th>
                                <th class="p-3">{{ $t('date_label') }}</th>
                                <th class="p-3">{{ $t('main_debt') }}</th>
                                <th class="p-3">Foiz</th>
                                <th class="p-3">{{ $t('total_payment_label') }}</th>
                                <th class="p-3">{{ $t('balance_remaining') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="row in generatedSchedule" :key="row.month" class="hover: transition-colors bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                <td class="p-3 text-gray-500">{{ row.month }}</td>
                                <td class="p-3">{{ row.date }}</td>
                                <td class="p-3 text-gray-900">{{ $currency(row.principal) }}</td>
                                <td class="p-3 text-amber-600">{{ $currency(row.interest) }}</td>
                                <td class="p-3 text-emerald-600 font-bold">{{ $currency(row.payment) }}</td>
                                <td class="p-3 text-gray-500">{{ $currency(row.balance) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-200 text-right">
                    <button @click="showScheduleModal = false" class="px-6 py-2 bg-blue-50 border border-blue-500 text-blue-600 hover:bg-blue-600 hover:text-black rounded uppercase font-mono text-xs font-bold transition-all">{{ $t('close_button') }}</button>
                </div>
            </div>
        </Modal>
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

        <!-- CASH REGISTER CRUD MODAL -->
        <Modal :show="showRegisterModal" @close="showRegisterModal = false" maxWidth="md">
            <div class="p-6">
                <button @click="showRegisterModal = false" class="absolute top-4 right-4 text-gray-900/50 hover:text-gray-900">✕</button>
                <h3 class="text-xl font-display font-bold text-gray-900 mb-6 uppercase tracking-widest">
                    {{ isEditRegister ? 'Kassani tahrirlash' : 'Yangi kassa qo\'shish' }}
                </h3>
                
                <form @submit.prevent="submitRegister" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Kassa nomi *</label>
                        <input v-model="registerForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-sm font-bold" required>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Turi *</label>
                            <select v-model="registerForm.type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                                <option value="cash">Naqd (Cash)</option>
                                <option value="bank">Bank hisobi</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Valyuta *</label>
                            <select v-model="registerForm.currency" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                                <option value="UZS">UZS</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Bino / Obyekt (Ixtiyoriy lock)</label>
                        <select v-model="registerForm.building_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                            <option value="">-- Obyektga bog'lamaslik --</option>
                            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        <p class="text-[9px] text-gray-400 mt-1">Ushbu kassa tanlansa, faqat tanlangan obyekt uchun tranzaksiyalarga ruxsat beriladi.</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Balans *</label>
                        <input v-model="registerForm.balance" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full font-bold text-sm text-blue-600" required>
                    </div>

                    <div v-if="isEditRegister" class="flex items-center gap-2 py-2">
                        <input type="checkbox" id="register_active" v-model="registerForm.is_active" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <label for="register_active" class="text-sm text-gray-900 font-bold">Faol holatda (Active)</label>
                    </div>
                    
                    <div class="flex justify-end pt-4 gap-4 border-t border-gray-200 mt-4">
                        <button type="button" @click="showRegisterModal = false" class="px-6 py-2 text-gray-500 hover:text-gray-900">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-blue-600 text-white rounded font-bold hover:shadow-sm transition-all disabled:opacity-50 uppercase tracking-widest">
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- COUNTERPARTY CRUD MODAL -->
        <Modal :show="showCounterpartyModal" @close="showCounterpartyModal = false" maxWidth="md">
            <div class="p-6">
                <button @click="showCounterpartyModal = false" class="absolute top-4 right-4 text-gray-900/50 hover:text-gray-900">✕</button>
                <h3 class="text-xl font-display font-bold text-gray-900 mb-6 uppercase tracking-widest">
                    {{ isEditCounterparty ? 'Kontragentni tahrirlash' : 'Yangi kontragent qo\'shish' }}
                </h3>
                
                <form @submit.prevent="submitCounterparty" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">F.I.O / Kompaniya nomi *</label>
                        <input v-model="counterpartyForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-sm font-bold" required>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Turi *</label>
                        <select v-model="counterpartyForm.type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full font-bold" required>
                            <option value="client">Mijoz (Client)</option>
                            <option value="supplier">Ta'minotchi (Supplier)</option>
                            <option value="employee">Xodim (Employee)</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Telefon raqami</label>
                            <input v-model="counterpartyForm.phone" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Email</label>
                            <input v-model="counterpartyForm.email" type="email" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Dastlabki Balans (UZS) *</label>
                        <input v-model="counterpartyForm.balance" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full font-bold text-sm text-blue-600" required>
                        <p class="text-[9px] text-gray-400 mt-1">Mijoz uchun: musbat balans = oldindan to'lov (haqi bor), manfiy = qarzi bor. Ta'minotchi uchun teskari.</p>
                    </div>
                    
                    <div class="flex justify-end pt-4 gap-4 border-t border-gray-200 mt-4">
                        <button type="button" @click="showCounterpartyModal = false" class="px-6 py-2 text-gray-500 hover:text-gray-900">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-blue-600 text-white rounded font-bold hover:shadow-sm transition-all disabled:opacity-50 uppercase tracking-widest">
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- INVOICE CRUD MODAL -->
        <Modal :show="showInvoiceModal" @close="showInvoiceModal = false" maxWidth="lg">
            <div class="p-6">
                <button @click="showInvoiceModal = false" class="absolute top-4 right-4 text-gray-900/50 hover:text-gray-900">✕</button>
                <h3 class="text-xl font-display font-bold text-gray-900 mb-6 uppercase tracking-widest">
                    {{ isEditInvoice ? 'Hisob-fakturani tahrirlash' : 'Yangi hisob-faktura (Invoice) yaratish' }}
                </h3>
                
                <form @submit.prevent="submitInvoice" class="space-y-4 font-mono text-xs">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Faktura raqami *</label>
                            <input v-model="invoiceForm.invoice_number" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-sm font-bold" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Faktura turi *</label>
                            <select v-model="invoiceForm.type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                                <option value="monthly_schedule">Shartnoma oylik to'lovi (Monthly Schedule)</option>
                                <option value="manual_expense">Qo'lda xarajat schyoti (Manual Expense)</option>
                                <option value="procurement">Ta'minot / Xarid schyoti (Procurement)</option>
                                <option value="loan">Qarz / Kredit schyoti (Loan)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Kontragent *</label>
                            <select v-model="invoiceForm.counterparty_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-sm font-bold bg-gray-50" required>
                                <option value="" disabled>-- Kontragent tanlang --</option>
                                <option v-for="c in counterparties" :key="c.id" :value="c.id">
                                    [{{ c.type.toUpperCase() }}] {{ c.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Obyekt (Ixtiyoriy)</label>
                            <select v-model="invoiceForm.building_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-50">
                                <option value="">-- Obyektga bog'lamaslik --</option>
                                <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Jami summa *</label>
                            <input v-model="invoiceForm.amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-lg font-bold text-blue-600" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Valyuta *</label>
                            <select v-model="invoiceForm.currency" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                                <option value="UZS">UZS</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="isEditInvoice" class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">To'langan summa *</label>
                            <input v-model="invoiceForm.paid_amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-lg font-bold text-emerald-600" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Faktura holati *</label>
                            <select v-model="invoiceForm.status" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                                <option value="new">Yangi (To'lanmagan)</option>
                                <option value="partially_paid">Kisman to'langan</option>
                                <option value="paid">To'liq to'langan</option>
                                <option value="cancelled">Bekor qilingan</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Muddati (Due Date) *</label>
                        <input v-model="invoiceForm.due_date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                    </div>

                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Tafsilotlar (Izoh)</label>
                        <textarea v-model="invoiceForm.description" rows="2" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full"></textarea>
                    </div>

                    <div class="flex justify-end pt-4 gap-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="showInvoiceModal = false" class="px-6 py-2 text-gray-500 hover:text-gray-900">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-blue-600 text-white rounded font-bold hover:shadow-sm transition-all disabled:opacity-50 uppercase tracking-widest">
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
