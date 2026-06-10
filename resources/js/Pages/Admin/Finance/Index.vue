<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, Link, usePage, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import BrigadeLedgerModal from '@/Components/Ledger/BrigadeLedgerModal.vue';
import OwnerLedgerModal from '@/Components/Ledger/OwnerLedgerModal.vue';
import GuardLedgerModal from '@/Components/Ledger/GuardLedgerModal.vue';
import Modal from '@/Components/Modal.vue';
import ExpensesBreakdownModal from '@/Components/ExpensesBreakdownModal.vue';

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
    // Initialize selected register for Tab 8
    if (props.registers && props.registers.length > 0) {
        selectedRegisterId.value = props.registers[0].id;
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
    moneyTransfers: Array,
    rates: Array
});

const activeTab = ref(0);
const isProcessing = ref(false);

// Sub-tabs states
const transferSubTab = ref('transfers'); // 'transfers' or 'exchanges'
const invoiceSubTab = ref('payments'); // 'payments' or 'debts'

// Synchronize activeTab with URL tab parameter
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
                activeTab.value = 0;
            }
        } catch (e) {
            console.error('URL parse error in Finance tab watcher', e);
        }
    },
    { immediate: true }
);

// Synchronize URL query parameter with activeTab changes
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
        console.error('URL replace error in activeTab watcher', e);
    }
});

// Exchange Rates State & Actions
const editingRate = ref(null);
const usdRateInput = ref(12350);
const eurRateInput = ref(16890);

const usdRate = computed(() => {
    const r = props.rates?.find(x => x.code === 'USD');
    return r ? parseFloat(r.rate) : 12350;
});
const eurRate = computed(() => {
    const r = props.rates?.find(x => x.code === 'EUR');
    return r ? parseFloat(r.rate) : 16890;
});

function editRate(code) {
    editingRate.value = code;
    if (code === 'USD') usdRateInput.value = usdRate.value;
    if (code === 'EUR') eurRateInput.value = eurRate.value;
}

function saveRate(code) {
    const rateVal = code === 'USD' ? usdRateInput.value : eurRateInput.value;
    isProcessing.value = true;
    router.post(route('admin.finance.rates.update'), {
        rates: [
            { code: code, rate: rateVal }
        ]
    }, {
        onSuccess: () => {
            editingRate.value = null;
            isProcessing.value = false;
        },
        onError: () => {
            isProcessing.value = false;
        }
    });
}

// Tab 8 selected register logic
const selectedRegisterId = ref(null);
const selectedRegister = computed(() => {
    return props.registers?.find(r => r.id === selectedRegisterId.value) || null;
});

const selectedRegisterTransactions = computed(() => {
    if (!selectedRegisterId.value) return [];
    return (props.transactions || []).filter(t => t.account_id === selectedRegisterId.value && t.status === 'approved');
});

// Selected Register Totals
const selectedRegisterKirimUzs = computed(() => {
    return selectedRegisterTransactions.value
        .filter(t => t.type === 'income' && t.currency === 'UZS')
        .reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
});
const selectedRegisterKirimUsd = computed(() => {
    return selectedRegisterTransactions.value
        .filter(t => t.type === 'income' && t.currency === 'USD')
        .reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
});
const selectedRegisterChiqimUzs = computed(() => {
    return selectedRegisterTransactions.value
        .filter(t => t.type === 'expense' && t.currency === 'UZS')
        .reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
});
const selectedRegisterChiqimUsd = computed(() => {
    return selectedRegisterTransactions.value
        .filter(t => t.type === 'expense' && t.currency === 'USD')
        .reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
});

// Tab 8 register specific search / filter states
const registerSearchQuery = ref('');
const registerDateFrom = ref('');
const registerDateTo = ref('');
const registerTypeFilter = ref('');

const filteredRegisterTransactions = computed(() => {
    let txs = selectedRegisterTransactions.value;
    if (registerSearchQuery.value) {
        const query = registerSearchQuery.value.toLowerCase();
        txs = txs.filter(t => 
            (t.notes || '').toLowerCase().includes(query) ||
            (t.counterparty?.name || '').toLowerCase().includes(query)
        );
    }
    if (registerDateFrom.value) {
        txs = txs.filter(t => t.transaction_date >= registerDateFrom.value);
    }
    if (registerDateTo.value) {
        txs = txs.filter(t => t.transaction_date <= registerDateTo.value);
    }
    if (registerTypeFilter.value) {
        txs = txs.filter(t => t.type === registerTypeFilter.value);
    }
    return txs;
});

// Tab 2 Jurnal Filters & Computeds
const jurnalDateFrom = ref('');
const jurnalDateTo = ref('');
const jurnalAccountId = ref('');
const jurnalTypeFilter = ref('');
const jurnalCounterpartyId = ref('');
const jurnalBuildingId = ref('');
const jurnalSearchQuery = ref('');

const filteredJurnalTransactions = computed(() => {
    let result = (props.transactions || []).filter(t => t.status === 'approved');

    if (jurnalSearchQuery.value) {
        const query = jurnalSearchQuery.value.toLowerCase();
        result = result.filter(t => 
            (t.notes || '').toLowerCase().includes(query) ||
            (t.counterparty?.name || '').toLowerCase().includes(query)
        );
    }
    if (jurnalDateFrom.value) {
        result = result.filter(t => t.transaction_date >= jurnalDateFrom.value);
    }
    if (jurnalDateTo.value) {
        result = result.filter(t => t.transaction_date <= jurnalDateTo.value);
    }
    if (jurnalAccountId.value) {
        result = result.filter(t => t.account_id === parseInt(jurnalAccountId.value));
    }
    if (jurnalTypeFilter.value) {
        result = result.filter(t => t.type === jurnalTypeFilter.value);
    }
    if (jurnalCounterpartyId.value) {
        result = result.filter(t => t.counterparty_id === parseInt(jurnalCounterpartyId.value));
    }
    if (jurnalBuildingId.value) {
        result = result.filter(t => t.building_id === parseInt(jurnalBuildingId.value));
    }

    return result;
});

// Jurnal Summaries
const totalTushumUzs = computed(() => {
    return (props.transactions || [])
        .filter(t => t.type === 'income' && t.status === 'approved' && t.currency === 'UZS')
        .reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
});
const totalTushumUsd = computed(() => {
    return (props.transactions || [])
        .filter(t => t.type === 'income' && t.status === 'approved' && t.currency === 'USD')
        .reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
});
const totalTolovUzs = computed(() => {
    return (props.transactions || [])
        .filter(t => t.type === 'expense' && t.status === 'approved' && t.currency === 'UZS')
        .reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
});
const totalBalansUzs = computed(() => {
    return (props.registers || []).reduce((sum, r) => sum + parseFloat(r.balance_uzs || 0), 0);
});
const totalBalansUsd = computed(() => {
    return (props.registers || []).reduce((sum, r) => sum + parseFloat(r.balance_usd || 0), 0);
});

// Tab 9 Counterparty filter & search
const counterpartySearch = ref('');
const counterpartyTypeFilter = ref('');

const filteredCounterpartiesList = computed(() => {
    if (!props.counterparties) return [];
    let list = props.counterparties;
    
    if (counterpartySearch.value) {
        const query = counterpartySearch.value.toLowerCase();
        list = list.filter(c => 
            (c.name || '').toLowerCase().includes(query) ||
            (c.phone || '').toLowerCase().includes(query) ||
            (c.email || '').toLowerCase().includes(query)
        );
    }
    if (counterpartyTypeFilter.value) {
        list = list.filter(c => c.type === counterpartyTypeFilter.value);
    }
    return list;
});

// Tab 6 money transfer filter & search
const transferSearch = ref('');
const filteredTransfers = computed(() => {
    if (!props.moneyTransfers) return [];
    if (!transferSearch.value) return props.moneyTransfers;
    const q = transferSearch.value.toLowerCase();
    return props.moneyTransfers.filter(t => 
        (t.description || '').toLowerCase().includes(q) ||
        (t.fromAccount?.name || '').toLowerCase().includes(q) ||
        (t.toAccount?.name || '').toLowerCase().includes(q)
    );
});

const totalTransfersCount = computed(() => filteredTransfers.value.length);
const totalTransfersSumUzs = computed(() => {
    return filteredTransfers.value
        .filter(t => t.sent_currency === 'UZS')
        .reduce((sum, t) => sum + parseFloat(t.sent_amount || 0), 0);
});
const activeRegistersCount = computed(() => (props.registers || []).filter(r => r.is_active).length);

// Tab 10 Client Payments & Debts computations
const clientPayments = computed(() => {
    return (props.transactions || [])
        .filter(t => t.counterparty?.type === 'client' && t.status === 'approved');
});

const clientDebts = computed(() => {
    return (props.counterparties || []).filter(c => c.type === 'client');
});

const clientPaymentsSearch = ref('');
const filteredClientPayments = computed(() => {
    if (!clientPayments.value) return [];
    if (!clientPaymentsSearch.value) return clientPayments.value;
    const q = clientPaymentsSearch.value.toLowerCase();
    return clientPayments.value.filter(t => 
        (t.counterparty?.name || '').toLowerCase().includes(q) ||
        (t.notes || '').toLowerCase().includes(q)
    );
});

const clientDebtsSearch = ref('');
const filteredClientDebts = computed(() => {
    if (!clientDebts.value) return [];
    if (!clientDebtsSearch.value) return clientDebts.value;
    const q = clientDebtsSearch.value.toLowerCase();
    return clientDebts.value.filter(c => 
        (c.name || '').toLowerCase().includes(q) ||
        (c.phone || '').toLowerCase().includes(q)
    );
});

// Edit & Delete Transaction actions
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

// Maker/Checker action queues
const pendingTransactions = computed(() => (props.transactions || []).filter(t => t.status === 'pending'));
const historicalTransactions = computed(() => (props.transactions || []).filter(t => t.status !== 'pending'));

const recentIncomes = computed(() => (historicalTransactions.value || []).filter(t => t.type === 'income'));
const recentExpenses = computed(() => (historicalTransactions.value || []).filter(t => t.type === 'expense'));
const totalApprovedExpenses = computed(() => (recentExpenses.value || []).reduce((sum, t) => sum + parseFloat(t.amount || 0), 0));

const incomePage = ref(1);
const expensePage = ref(1);
const itemsPerPage = 10;

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

const showExpensesModal = ref(false);

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
    transaction_date: new Date().toLocaleString('sv').replace(' ', 'T').slice(0, 16),
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

// Registers Modal & CRUD helpers
const showRegisterModal = ref(false);
const registerForm = ref({
    id: null,
    name: '',
    type: 'cash',
    initial_balance_uzs: 0,
    initial_balance_usd: 0,
    initial_balance_eur: 0,
    currency: 'UZS',
    building_id: '',
    is_active: true,
    bank_name: '',
    account_number: '',
    mfo_code: ''
});
const isEditRegister = ref(false);

function openNewRegisterModal() {
    isEditRegister.value = false;
    registerForm.value = { 
        id: null, name: '', type: 'cash', 
        initial_balance_uzs: 0, initial_balance_usd: 0, initial_balance_eur: 0,
        currency: 'UZS', building_id: '', is_active: true,
        bank_name: '', account_number: '', mfo_code: ''
    };
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

// Counterparties Modal & CRUD helpers
const showCounterpartyModal = ref(false);
const counterpartyForm = ref({
    id: null,
    name: '',
    type: 'client',
    phone: '',
    email: '',
    balance: 0,
    account_number: '',
    bank_name: '',
    inn: '',
    is_active: true
});
const isEditCounterparty = ref(false);

function openNewCounterpartyModal() {
    isEditCounterparty.value = false;
    counterpartyForm.value = { id: null, name: '', type: 'client', phone: '', email: '', balance: 0, account_number: '', bank_name: '', inn: '', is_active: true };
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

// Invoices CRUD & Modals
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

// Invoice dynamic filters for Tab 10
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
        if (invoiceFilters.value.counterparty_id && inv.counterparty_id !== parseInt(invoiceFilters.value.counterparty_id)) return false;
        if (invoiceFilters.value.building_id && inv.building_id !== parseInt(invoiceFilters.value.building_id)) return false;
        if (invoiceFilters.value.start_date && inv.created_at < invoiceFilters.value.start_date) return false;
        if (invoiceFilters.value.end_date && inv.created_at > invoiceFilters.value.end_date) return false;
        return true;
    });
});
const invoiceExportUrl = computed(() => route('admin.finance.invoices.export.xlsx', invoiceFilters.value));

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

// Search Contract for client transactions
const scheduleSearch = ref('');
const showScheduleDropdown = ref(false);

const filteredContracts = computed(() => {
    if (!props.contracts) return [];
    let baseContracts = props.contracts || [];
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
                type: 'income', category_id: '', category_name: '', amount: '', currency: 'UZS', payment_method: 'cash', 
                account_id: '', counterparty_id: '', invoice_id: '', contract_id: '', client_id: '', worker_id: '',
                brigade_id: '', partner_id: '', machinery_id: '', machine_owner_id: '', building_id: '', supplier_id: '', 
                fixed_asset_id: '', transaction_date: new Date().toLocaleString('sv').replace(' ', 'T').slice(0, 16), 
                attachment: null, auto_approve: true, notes: '', latitude: null, longitude: null, source: 'admin_panel', 
                target_type: null, target_id: null, exchange_rate: 1.0, converted_amount: ''
            };
            activeTab.value = 1;
        },
        onError: () => isProcessing.value = false
    });
}

function submitTransfer() {
    isProcessing.value = true;
    router.post(route('admin.finance.internal_transfer'), transferForm.value, {
        onSuccess: () => {
            isProcessing.value = false;
            transferForm.value = { from_account_id: '', to_account_id: '', sent_amount: '', exchange_rate: 1.0, description: '' };
            activeTab.value = 6;
        },
        onError: () => isProcessing.value = false
    });
}

// Contract Approve Details
const showContractApproveModal = ref(false);
const selectedContractTxn = ref(null);
const contractApproveForm = ref({ applied_amount: 0, refund_amount: 0 });

watch(() => contractApproveForm.value.applied_amount, (newVal) => {
    if (selectedContractTxn.value) {
        contractApproveForm.value.refund_amount = Math.max(0, selectedContractTxn.value.amount - newVal);
    }
});

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

// Receipt Modal details
const showReceiptModal = ref(false);
const receiptData = ref(null);
function openReceipt(transaction) {
    receiptData.value = transaction;
    showReceiptModal.value = true;
}

// Qarzlar (Liabilities) CRUD
const showLiabilityModal = ref(false);
const isEditMode = ref(false);
const editingLiabilityId = ref(null);
const liabilityForm = ref({
    type: 'bank_loan', creditor_name: '', total_amount: '', interest_rate: '', 
    term_months: 12, start_date: new Date().toISOString().split('T')[0], 
    end_date: '', monthly_payment: '', description: '', status: 'active', fund_account: 'main'
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
        type: liability.type, creditor_name: liability.creditor_name, total_amount: liability.total_amount,
        interest_rate: liability.interest_rate, term_months: liability.term_months, start_date: liability.start_date,
        end_date: liability.end_date, monthly_payment: liability.monthly_payment, description: liability.description,
        status: liability.status, fund_account: ''
    };
    showLiabilityModal.value = true;
}

function submitLiability() {
    isProcessing.value = true;
    if (isEditMode.value) {
        router.put(route('admin.finance.liabilities.update', editingLiabilityId.value), liabilityForm.value, {
            onSuccess: () => { isProcessing.value = false; showLiabilityModal.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.finance.liabilities.store'), liabilityForm.value, {
            onSuccess: () => { isProcessing.value = false; showLiabilityModal.value = false; },
            onError: () => isProcessing.value = false
        });
    }
}

// Payment Schedule calculations
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
            m = r > 0 ? (p * (r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1)) : (p / n);
        }
        let currentDate = new Date(liability.start_date);
        for (let i = 1; i <= n; i++) {
            let interest = balance * r;
            let principal = m - interest;
            if (i === n) {
                principal = balance;
                m = principal + interest;
            }
            balance -= principal;
            currentDate.setMonth(currentDate.getMonth() + 1);
            generatedSchedule.value.push({
                month: i, date: currentDate.toISOString().split('T')[0],
                payment: m, principal: principal, interest: interest, balance: Math.max(0, balance)
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

// Liability payments
const showPaymentModal = ref(false);
const selectedLiability = ref(null);
const paymentForm = ref({ amount: '', payment_date: new Date().toISOString().split('T')[0], notes: '', account_type: 'main' });

function openPaymentModal(liability) {
    selectedLiability.value = liability;
    paymentForm.value = { amount: liability.monthly_payment || '', payment_date: new Date().toISOString().split('T')[0], notes: '', account_type: 'main' };
    showPaymentModal.value = true;
}

function submitPayment() {
    isProcessing.value = true;
    router.post(route('admin.finance.liabilities.payments.store', selectedLiability.value.id), paymentForm.value, {
        onSuccess: () => { isProcessing.value = false; showPaymentModal.value = false; },
        onError: () => isProcessing.value = false
    });
}

// Supplier payments
const showSupplierPaymentModal = ref(false);
const selectedSupplier = ref(null);
const supplierPaymentForm = ref({ supplier_id: '', amount: '', account_type: 'main', notes: '' });

function openSupplierPaymentModal(supplier) {
    selectedSupplier.value = supplier;
    supplierPaymentForm.value = { supplier_id: supplier.id, amount: Math.max(0, supplier.balance), account_type: 'main', notes: '' };
    showSupplierPaymentModal.value = true;
}

function submitSupplierPayment() {
    isProcessing.value = true;
    router.post(route('admin.finance.suppliers.pay'), supplierPaymentForm.value, {
        onSuccess: () => { isProcessing.value = false; showSupplierPaymentModal.value = false; },
        onError: () => isProcessing.value = false
    });
}

// Edit supplier
const showEditSupplierModal = ref(false);
const editSupplierForm = useForm({ id: '', name: '', company_name: '', balance: 0 });

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
        onSuccess: () => { isProcessing.value = false; showEditSupplierModal.value = false; },
        onError: () => isProcessing.value = false
    });
}

function deleteSupplier(id) {
    if (confirm("Ta'minotchini o'chirishni tasdiqlaysizmi?")) {
        router.delete(route('admin.warehouse.suppliers.destroy', id));
    }
}

// Categories report summary
const categoriesSummary = computed(() => {
    if (!props.transactions) return [];
    const approvedTxns = props.transactions.filter(t => t.status === 'approved');
    const groups = {};
    approvedTxns.forEach(t => {
        const catName = t.category?.name || 'Noma\'lum';
        if (!groups[catName]) {
            groups[catName] = { name: catName, total_income: 0, total_expense: 0, transactions: [] };
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

// Format money values
function formatMoney(amount, currency = 'UZS') {
    if (amount === undefined || amount === null) return '0.00 ' + currency;
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + ' ' + currency;
}
</script>

<template>
    <Head :title="$t('finance_core')" />
    <AdminLayout>
        <div class="space-y-6">
            <!-- TAB 0: Boshqaruv Paneli (Dashboard) -->
            <div v-if="activeTab === 0" class="space-y-6">
                <!-- Summary Metrics (Screenshot 2 Style but for dashboard) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Jami tushumlar -->
                    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center font-bold">✓</div>
                            <span class="text-sm font-semibold text-gray-500">Jami tushumlar</span>
                        </div>
                        <div class="space-y-1">
                            <h2 class="text-2xl font-bold font-display text-emerald-600">
                                {{ formatMoney(totalTushumUsd, 'USD') }}
                            </h2>
                            <h2 class="text-2xl font-bold font-display text-emerald-600">
                                {{ formatMoney(totalTushumUzs, 'UZS') }}
                            </h2>
                        </div>
                    </div>
                    <!-- Jami to'lovlar -->
                    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-full bg-red-50 text-red-500 flex items-center justify-center font-bold">✕</div>
                            <span class="text-sm font-semibold text-gray-500">Jami to'lovlar</span>
                        </div>
                        <div class="space-y-1">
                            <h2 class="text-2xl font-bold font-display text-red-600">
                                {{ formatMoney(totalTolovUzs, 'UZS') }}
                            </h2>
                            <p class="text-xs text-gray-400 font-mono">Barcha tasdiqlangan xarajatlar summasi</p>
                        </div>
                    </div>
                    <!-- Balans -->
                    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center font-bold">🏦</div>
                            <span class="text-sm font-semibold text-gray-500">Balans</span>
                        </div>
                        <div class="space-y-1">
                            <h2 class="text-2xl font-bold font-display text-orange-500">
                                {{ formatMoney(totalBalansUsd, 'USD') }}
                            </h2>
                            <h2 class="text-2xl font-bold font-display text-orange-500">
                                {{ formatMoney(totalBalansUzs, 'UZS') }}
                            </h2>
                        </div>
                    </div>
                </div>

                <!-- Kirimlar & Chiqimlar Tables Side by Side -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Incomes Card -->
                    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col h-[500px]">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span> Kirimlar (Qayerdan)
                        </h3>
                        <div class="flex-1 overflow-y-auto">
                            <table class="w-full text-left text-xs font-mono">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="p-3">Sana</th>
                                        <th class="p-3">Izoh</th>
                                        <th class="p-3">Kategoriya</th>
                                        <th class="p-3 text-right">Summa</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="txn in paginatedIncomes" :key="txn.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="p-3 text-gray-500">{{ new Date(txn.transaction_date).toLocaleDateString() }}</td>
                                        <td class="p-3 truncate max-w-xs">{{ txn.notes || '-' }}</td>
                                        <td class="p-3">{{ txn.category?.name || '-' }}</td>
                                        <td class="p-3 text-right font-bold text-emerald-600">+ {{ formatMoney(txn.amount, txn.currency) }}</td>
                                    </tr>
                                    <tr v-if="!paginatedIncomes.length">
                                        <td colspan="4" class="p-4 text-center text-gray-400">Kirimlar mavjud emas</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-gray-100 pt-4 flex justify-between items-center text-xs mt-auto">
                            <div class="font-bold text-gray-900">Jami Kirimlar: <span class="text-emerald-600">{{ formatMoney(totalIncomeAmount, 'UZS') }}</span></div>
                            <div class="flex items-center gap-3">
                                <button @click="incomePage > 1 && incomePage--" :disabled="incomePage === 1" class="disabled:opacity-30 p-1">◄</button>
                                <span>{{ incomePage }} / {{ totalIncomesPages }}</span>
                                <button @click="incomePage < totalIncomesPages && incomePage++" :disabled="incomePage === totalIncomesPages" class="disabled:opacity-30 p-1">►</button>
                            </div>
                        </div>
                    </div>

                    <!-- Expenses Card -->
                    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col h-[500px]">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-600"></span> Chiqimlar (Qayerga)
                        </h3>
                        <div class="flex-1 overflow-y-auto">
                            <table class="w-full text-left text-xs font-mono">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="p-3">Sana</th>
                                        <th class="p-3">Izoh</th>
                                        <th class="p-3">Kategoriya</th>
                                        <th class="p-3 text-right">Summa</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="txn in paginatedExpenses" :key="txn.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="p-3 text-gray-500">{{ new Date(txn.transaction_date).toLocaleDateString() }}</td>
                                        <td class="p-3 truncate max-w-xs">{{ txn.notes || '-' }}</td>
                                        <td class="p-3">{{ txn.category?.name || '-' }}</td>
                                        <td class="p-3 text-right font-bold text-red-600">- {{ formatMoney(txn.amount, txn.currency) }}</td>
                                    </tr>
                                    <tr v-if="!paginatedExpenses.length">
                                        <td colspan="4" class="p-4 text-center text-gray-400">Chiqimlar mavjud emas</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-gray-100 pt-4 flex justify-between items-center text-xs mt-auto">
                            <div class="font-bold text-gray-900">Jami Chiqimlar: <span class="text-red-600">- {{ formatMoney(totalExpenseAmount, 'UZS') }}</span></div>
                            <div class="flex items-center gap-3">
                                <button @click="expensePage > 1 && expensePage--" :disabled="expensePage === 1" class="disabled:opacity-30 p-1">◄</button>
                                <span>{{ expensePage }} / {{ totalExpensesPages }}</span>
                                <button @click="expensePage < totalExpensesPages && expensePage++" :disabled="expensePage === totalExpensesPages" class="disabled:opacity-30 p-1">►</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 1: Maker/Checker Queue (Harakatlar Markazi) -->
            <div v-if="activeTab === 1" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
                <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-amber-500 animate-pulse"></span>
                        Tasdiqlash Kutilayotgan Tranzaksiyalar
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="txn in pendingTransactions" :key="txn.id" class="p-5 rounded-2xl bg-gray-50 border border-gray-200 hover:border-amber-300 transition-all flex flex-col gap-4 relative overflow-hidden group">
                            <div class="flex justify-between items-start">
                                <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider" :class="txn.type === 'income' ? 'bg-blue-50 text-blue-600' : 'bg-red-50 text-red-600'">
                                    {{ txn.type === 'income' ? 'Kirim' : 'Chiqim' }}
                                </span>
                                <span class="text-xs text-gray-500 font-mono">{{ new Date(txn.transaction_date).toLocaleDateString() }}</span>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold font-display" :class="txn.type === 'income' ? 'text-blue-600' : 'text-red-600'">
                                    {{ txn.type === 'income' ? '+' : '-' }} {{ formatMoney(txn.amount, txn.currency) }}
                                </h3>
                                <p class="text-[10px] uppercase font-bold text-gray-400 mt-1">Hisob: {{ txn.cashRegister?.name || 'Asosiy' }}</p>
                            </div>
                            <div class="p-3 bg-white border border-gray-150 rounded-xl text-xs font-mono space-y-1">
                                <p><span class="text-gray-400">Izoh:</span> {{ txn.notes || 'Kiritilmagan' }}</p>
                                <p v-if="txn.counterparty"><span class="text-gray-400">Kontragent:</span> {{ txn.counterparty.name }}</p>
                                <p><span class="text-gray-400">Yaratuvchi:</span> {{ txn.creator?.name }}</p>
                            </div>
                            <div class="flex flex-col gap-2 mt-auto pt-2 border-t border-gray-100">
                                <a :href="'tel:' + (txn.creator?.phone || '')" class="w-full text-center py-2 text-xs font-bold uppercase tracking-wider bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition-all rounded-xl">
                                    📞 Bog'lanish
                                </a>
                                <div class="flex gap-2">
                                    <button v-if="isAdmin" @click="approve(txn.id)" :disabled="isProcessing" class="flex-1 py-2 text-xs font-bold uppercase bg-emerald-50 border border-emerald-500 text-emerald-600 hover:bg-emerald-600 hover:text-white transition-all rounded-xl">Tasdiqlash</button>
                                    <button v-if="isAdmin" @click="reject(txn.id)" :disabled="isProcessing" class="flex-1 py-2 text-xs font-bold uppercase bg-red-50 border border-red-500 text-red-600 hover:bg-red-600 hover:text-white transition-all rounded-xl">Rad Etish</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!pendingTransactions.length" class="py-12 text-center text-gray-400 font-mono text-sm border-dashed border border-gray-200 rounded-xl mt-4">
                        Tasdiqlash kutilayotgan so'rovlar mavjud emas.
                    </div>
                </div>
            </div>

            <!-- TAB 2: Tranzaksiyalar (Jurnal) - Screenshot 2 Style -->
            <div v-if="activeTab === 2" class="space-y-6">
                <!-- Title & Actions Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div>
                        <h2 class="text-xl font-bold font-display text-gray-900">Tranzaksiyalar</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1">Jami {{ filteredJurnalTransactions.length }} ta tranzaksiya</p>
                    </div>
                    <div class="flex gap-3 w-full sm:w-auto">
                        <input v-model="jurnalSearchQuery" type="text" placeholder="Qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-4 py-2 text-sm w-full sm:w-64">
                        <a :href="route('admin.finance.transactions.export.xlsx')" target="_blank" class="px-5 py-2.5 border border-emerald-500 text-emerald-600 hover:bg-emerald-50 rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-2">
                            <span>📊 Excel</span>
                        </a>
                        <button @click="activeTab = 3; form.type = 'income'" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-2 shrink-0">
                            <span>+ Kirim</span>
                        </button>
                        <button @click="activeTab = 3; form.type = 'expense'" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-2 shrink-0">
                            <span>- Chiqim</span>
                        </button>
                    </div>
                </div>

                <!-- Currency Exchange Rates row -->
                <div class="bg-white border border-gray-150 p-4 rounded-2xl shadow-sm flex items-center gap-4 flex-wrap">
                    <!-- UZS -->
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 rounded-lg border border-gray-200">
                        <span class="text-xs font-bold text-orange-500">UZS</span>
                        <span class="text-xs text-gray-500">Asosiy valyuta</span>
                        <button class="text-gray-400 hover:text-orange-600">✏️</button>
                    </div>
                    <!-- USD -->
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 rounded-lg border border-gray-200">
                        <span class="text-xs font-bold text-orange-500">USD</span>
                        <span class="text-xs text-gray-700">1 USD =</span>
                        <input v-if="editingRate === 'USD'" v-model="usdRateInput" type="number" class="w-24 px-2 py-0.5 text-xs border border-orange-300 rounded" @keydown.enter="saveRate('USD')">
                        <span v-else class="text-xs font-bold text-gray-900">{{ usdRate.toLocaleString() }} UZS</span>
                        <button @click="editingRate === 'USD' ? saveRate('USD') : editRate('USD')" class="text-gray-400 hover:text-orange-500">
                            {{ editingRate === 'USD' ? '💾' : '✏️' }}
                        </button>
                    </div>
                    <!-- EUR -->
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 rounded-lg border border-gray-200">
                        <span class="text-xs font-bold text-orange-500">EUR</span>
                        <span class="text-xs text-gray-700">1 EUR =</span>
                        <input v-if="editingRate === 'EUR'" v-model="eurRateInput" type="number" class="w-24 px-2 py-0.5 text-xs border border-orange-300 rounded" @keydown.enter="saveRate('EUR')">
                        <span v-else class="text-xs font-bold text-gray-900">{{ eurRate.toLocaleString() }} UZS</span>
                        <button @click="editingRate === 'EUR' ? saveRate('EUR') : editRate('EUR')" class="text-gray-400 hover:text-orange-500">
                            {{ editingRate === 'EUR' ? '💾' : '✏️' }}
                        </button>
                    </div>
                </div>

                <!-- Summaries Cards (Jami tushumlar, Jami to'lovlar, Balans) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Jami tushumlar -->
                    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center font-bold">✓</div>
                            <span class="text-sm font-semibold text-gray-500">Jami tushumlar</span>
                        </div>
                        <div class="space-y-1">
                            <h2 class="text-xl font-bold font-display text-emerald-600">
                                {{ formatMoney(totalTushumUsd, 'USD') }}
                            </h2>
                            <h2 class="text-xl font-bold font-display text-emerald-600">
                                {{ formatMoney(totalTushumUzs, 'UZS') }}
                            </h2>
                        </div>
                    </div>
                    <!-- Jami to'lovlar -->
                    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-full bg-red-50 text-red-500 flex items-center justify-center font-bold">✕</div>
                            <span class="text-sm font-semibold text-gray-500">Jami to'lovlar</span>
                        </div>
                        <div class="space-y-1">
                            <h2 class="text-xl font-bold font-display text-red-600">
                                {{ formatMoney(totalTolovUzs, 'UZS') }}
                            </h2>
                            <p class="text-xs text-gray-400 font-mono">Tasdiqlangan chiqimlar</p>
                        </div>
                    </div>
                    <!-- Balans -->
                    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center font-bold">🏦</div>
                            <span class="text-sm font-semibold text-gray-500">Balans</span>
                        </div>
                        <div class="space-y-1">
                            <h2 class="text-xl font-bold font-display text-orange-500">
                                {{ formatMoney(totalBalansUsd, 'USD') }}
                            </h2>
                            <h2 class="text-xl font-bold font-display text-orange-500">
                                {{ formatMoney(totalBalansUzs, 'UZS') }}
                            </h2>
                        </div>
                    </div>
                </div>

                <!-- Multi-Level Filter Row -->
                <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Sanadan</label>
                        <input v-model="jurnalDateFrom" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs">
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Sanagacha</label>
                        <input v-model="jurnalDateTo" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs">
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Kassa / Bank</label>
                        <select v-model="jurnalAccountId" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs">
                            <option value="">Barchasi</option>
                            <option v-for="r in registers" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Turi</label>
                        <select v-model="jurnalTypeFilter" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs">
                            <option value="">Barchasi</option>
                            <option value="income">Kirim</option>
                            <option value="expense">Chiqim</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Kontragent</label>
                        <select v-model="jurnalCounterpartyId" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs">
                            <option value="">Barchasi</option>
                            <option v-for="c in counterparties" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Bino / Obyekt</label>
                        <select v-model="jurnalBuildingId" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs">
                            <option value="">Barchasi</option>
                            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Transaction Log Table -->
                <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                                <tr>
                                    <th class="p-4">Hujjat raqami</th>
                                    <th class="p-4">Sana</th>
                                    <th class="p-4">Kontragent</th>
                                    <th class="p-4">Obyekt</th>
                                    <th class="p-4 text-right">Summa</th>
                                    <th class="p-4">Tranzaksiya turi</th>
                                    <th class="p-4 text-center">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="t in filteredJurnalTransactions" :key="t.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 font-bold text-gray-900">#{{ t.id }}</td>
                                    <td class="p-4 text-gray-500">{{ new Date(t.transaction_date).toLocaleDateString() }}</td>
                                    <td class="p-4 font-bold text-gray-800">{{ t.counterparty?.name || '-' }}</td>
                                    <td class="p-4 text-gray-700">{{ t.contract?.unit?.building?.name || 'Nuovo' }}</td>
                                    <td class="p-4 text-right font-bold" :class="t.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                                        {{ t.type === 'income' ? '+' : '-' }} {{ formatMoney(t.amount, t.currency) }}
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border border-gray-200 bg-gray-50">
                                            {{ t.category?.name || 'Mijoz to\'lovi' }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex justify-center gap-2">
                                            <!-- View -->
                                            <button @click="openReceipt(t)" class="p-1 text-gray-500 hover:text-blue-600 border border-gray-200 rounded-md hover:bg-blue-50 transition-all" title="Ko'rish">
                                                👁️
                                            </button>
                                            <!-- Edit -->
                                            <button v-if="isAdmin" @click="openEditTransactionModal(t)" class="p-1 text-gray-500 hover:text-amber-600 border border-gray-200 rounded-md hover:bg-amber-50 transition-all" title="Tahrirlash">
                                                ✏️
                                            </button>
                                            <!-- Delete -->
                                            <button v-if="isAdmin" @click="deleteTransaction(t.id)" class="p-1 text-red-600 hover:text-white border border-red-200 hover:bg-red-600 rounded-md transition-all" title="O'chirish">
                                                🗑️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!filteredJurnalTransactions.length">
                                    <td colspan="7" class="p-8 text-center text-gray-400">Tranzaksiyalar topilmadi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: Create Transaction Form -->
            <div v-if="activeTab === 3" class="max-w-[800px] mx-auto bg-white border border-gray-150 p-8 rounded-2xl shadow-sm">
                <h2 class="text-xl font-bold font-display text-gray-900 mb-2">Tranzaksiya Kiritish</h2>
                <p class="text-xs font-mono text-gray-500 mb-6">Moliya markaziga yangi kirim yoki chiqim operatsiyasini yozish.</p>

                <form @submit.prevent="submitTransaction" class="space-y-6 font-mono text-xs">
                    <!-- Type Selector -->
                    <div class="flex gap-4">
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" v-model="form.type" value="income" class="hidden peer">
                            <div class="text-center p-4 rounded-xl border border-gray-200 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:text-emerald-600 text-gray-500 font-bold transition-all">
                                📥 KIRIM (INFLOW)
                            </div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" v-model="form.type" value="expense" class="hidden peer">
                            <div class="text-center p-4 rounded-xl border border-gray-200 peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-600 text-gray-500 font-bold transition-all">
                                📤 CHIQIM (OUTFLOW)
                            </div>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Account selection -->
                        <div>
                            <label class="block text-gray-500 mb-1">KASSA / BANK HISOBI *</label>
                            <select v-model="form.account_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-10" required>
                                <option value="" disabled>-- Tanlang --</option>
                                <option v-for="r in registers.filter(x => x.is_active)" :key="r.id" :value="r.id">
                                    {{ r.name }} ({{ r.currency }}) - Balans: {{ r.balance_uzs.toLocaleString() }} UZS
                                </option>
                            </select>
                        </div>

                        <!-- Category selection -->
                        <div>
                            <label class="block text-gray-500 mb-1">KATEGORIYA *</label>
                            <select v-model="form.category_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-10" required>
                                <option value="" disabled>-- Tanlang --</option>
                                <option v-for="c in categories.filter(x => x.type === form.type || x.type === 'both')" :key="c.id" :value="c.id">
                                    {{ c.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Counterparty selection -->
                        <div>
                            <label class="block text-gray-500 mb-1">KONTRAGENT (IXTIYORIY)</label>
                            <select v-model="form.counterparty_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-10">
                                <option value="">-- Tanlang --</option>
                                <option v-for="c in counterparties" :key="c.id" :value="c.id">
                                    [{{ c.type.toUpperCase() }}] {{ c.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Amount input -->
                        <div>
                            <label class="block text-gray-500 mb-1">SUMMA *</label>
                            <input v-model="form.amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-10 font-bold" required placeholder="0.00">
                        </div>
                    </div>

                    <!-- Client Contract Search -->
                    <div v-if="requiresClient" class="border border-blue-150 p-4 rounded-xl bg-blue-50/30">
                        <label class="block text-gray-600 mb-1 font-bold">MIJOZ SHARTNOMASINI BOG'LASH *</label>
                        <div class="relative">
                            <input v-model="scheduleSearch" @focus="showScheduleDropdown = true" type="text" placeholder="Mijoz nomi yoki xona raqami bo'yicha qidiruv..." class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-10">
                            <div v-show="showScheduleDropdown && filteredContracts.length" class="absolute z-50 left-0 right-0 bg-white border border-gray-200 rounded-xl shadow-lg mt-1 max-h-48 overflow-y-auto">
                                <div v-for="c in filteredContracts" :key="c.id" @click="selectContract(c)" class="p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 flex justify-between">
                                    <span>{{ c.client?.first_name }} {{ c.client?.last_name }}</span>
                                    <span class="font-bold text-blue-600">{{ c.unit?.unit_number }} - xona</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date & Notes -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-500 mb-1">SANASI</label>
                            <input v-model="form.transaction_date" type="datetime-local" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-10">
                        </div>
                        <div>
                            <label class="block text-gray-500 mb-1">IZOH</label>
                            <input v-model="form.notes" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-10" placeholder="Tranzaksiya tafsilotlari...">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                        <button type="button" @click="activeTab = 2" class="px-5 py-2 border border-gray-300 rounded-lg text-gray-600 font-bold">Bekor Qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold font-mono uppercase tracking-widest transition-all">Saqlash</button>
                    </div>
                </form>
            </div>

            <!-- TAB 4: Qarzlar & Kreditlar (Liabilities) -->
            <div v-if="activeTab === 4" class="space-y-6">
                <div class="flex justify-between items-center bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div>
                        <h2 class="text-xl font-bold font-display text-gray-900">Qarzlar va Kreditlar</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1">Kompaniyaning tashqi moliyaviy majburiyatlari.</p>
                    </div>
                    <button @click="openNewLiabilityModal" class="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm">
                        + Yangi Majburiyat
                    </button>
                </div>

                <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-left text-sm font-mono">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                            <tr>
                                <th class="p-4">Kreditor</th>
                                <th class="p-4">Turi</th>
                                <th class="p-4 text-right">Umumiy Summa</th>
                                <th class="p-4 text-right">Oylik To'lov</th>
                                <th class="p-4">Muddati</th>
                                <th class="p-4">Holat</th>
                                <th class="p-4 text-center">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="l in financialLiabilities" :key="l.id" class="hover:bg-gray-50 transition-colors">
                                <td class="p-4 font-bold text-gray-900">{{ l.creditor_name }}</td>
                                <td class="p-4 uppercase text-[10px]">{{ l.type }}</td>
                                <td class="p-4 text-right font-bold text-red-600">{{ formatMoney(l.total_amount) }}</td>
                                <td class="p-4 text-right font-bold text-gray-900">{{ formatMoney(l.monthly_payment) }}</td>
                                <td class="p-4 text-gray-500">{{ l.term_months }} oy</td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold" :class="l.status === 'active' ? 'bg-green-50 text-green-700' : 'bg-gray-50 text-gray-500'">
                                        {{ l.status }}
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button @click="openScheduleModal(l)" class="px-2.5 py-1 text-[10px] uppercase font-bold border border-blue-500 text-blue-600 bg-blue-50/30 rounded hover:bg-blue-600 hover:text-white transition-all">Grafik</button>
                                        <button @click="openPaymentModal(l)" class="px-2.5 py-1 text-[10px] uppercase font-bold border border-emerald-500 text-emerald-600 bg-emerald-50/30 rounded hover:bg-emerald-600 hover:text-white transition-all">To'lov</button>
                                        <button v-if="isAdmin" @click="deleteLiability(l.id)" class="px-2 py-1 text-xs border border-red-500 text-red-600 hover:bg-red-600 hover:text-white rounded-md transition-all">✖</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!financialLiabilities.length">
                                <td colspan="7" class="p-8 text-center text-gray-400">Majburiyatlar mavjud emas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 5: Ta'minotchilar (Suppliers) -->
            <div v-if="activeTab === 5" class="space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <h2 class="text-xl font-bold font-display text-gray-900">Ta'minotchilar Qoldiqlari</h2>
                    <p class="text-xs text-gray-500 font-mono mt-1">Material yetkazib beruvchilar va ulardan bo'lgan hisob-kitoblar.</p>
                </div>

                <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-left text-sm font-mono">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                            <tr>
                                <th class="p-4">Kompaniya nomi</th>
                                <th class="p-4">Vakil</th>
                                <th class="p-4 text-right">Balans (UZS)</th>
                                <th class="p-4 text-center">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="s in suppliers" :key="s.id" class="hover:bg-gray-50 transition-colors">
                                <td class="p-4 font-bold text-gray-900">{{ s.company_name }}</td>
                                <td class="p-4 text-gray-700">{{ s.name }}</td>
                                <td class="p-4 text-right font-bold" :class="s.balance > 0 ? 'text-red-600' : 'text-emerald-600'">
                                    {{ formatMoney(s.balance) }}
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button @click="openSupplierPaymentModal(s)" class="px-3 py-1 bg-emerald-50 border border-emerald-500 text-emerald-600 font-bold rounded-lg text-xs hover:bg-emerald-600 hover:text-white transition-all">To'lov qilish</button>
                                        <button @click="openEditSupplierModal(s)" class="px-3 py-1 bg-blue-50 border border-blue-500 text-blue-600 font-bold rounded-lg text-xs hover:bg-blue-600 hover:text-white transition-all">Tahrirlash</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!suppliers.length">
                                <td colspan="4" class="p-8 text-center text-gray-400">Ta'minotchilar topilmadi.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 6: Pul O'tkazmalari - Screenshot 4 Style -->
            <div v-if="activeTab === 6" class="space-y-6">
                <!-- Title & Filter row -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div>
                        <h2 class="text-xl font-bold font-display text-gray-900">Pul o'tkazmalari</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1">Bank hisobidan pul o'tkazmalari ro'yxati</p>
                    </div>
                    <div class="flex gap-3 w-full sm:w-auto">
                        <button class="px-4 py-2 border border-gray-300 text-gray-600 rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-1.5">
                            🔍 Filtr
                        </button>
                        <button @click="openNewRegisterModal" class="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm shrink-0">
                            + Pul o'tkazmasi qo'shish
                        </button>
                    </div>
                </div>

                <!-- Sub tabs for transfers -->
                <div class="flex border-b border-gray-200">
                    <button @click="transferSubTab = 'transfers'" class="px-6 py-3 font-bold text-xs uppercase tracking-widest border-b-2 transition-all" :class="transferSubTab === 'transfers' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-400'">
                        Pul o'tkazmalari
                    </button>
                    <button @click="transferSubTab = 'exchanges'" class="px-6 py-3 font-bold text-xs uppercase tracking-widest border-b-2 transition-all" :class="transferSubTab === 'exchanges' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-400'">
                        Valuta ayirboshlashlar
                    </button>
                </div>

                <!-- Summaries Cards (Jami o'tkazmalar, Jami summa (USD), Faol kassalar) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Jami o'tkazmalar</span>
                        <h2 class="text-2xl font-bold font-display text-gray-900 mt-2">{{ totalTransfersCount }}</h2>
                    </div>
                    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Jami summa (USD)</span>
                        <h2 class="text-2xl font-bold font-display text-emerald-600 mt-2">{{ totalTransfersSumUzs.toLocaleString() }} UZS</h2>
                    </div>
                    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Faol kassalar</span>
                        <h2 class="text-2xl font-bold font-display text-gray-900 mt-2">{{ activeRegistersCount }}</h2>
                    </div>
                </div>

                <!-- Transfer card list (Screenshot 4 layout) -->
                <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm space-y-4">
                    <div v-for="t in filteredTransfers" :key="t.id" class="p-4 rounded-xl bg-gray-50 border border-gray-200 flex items-center justify-between flex-wrap gap-4 font-mono text-sm hover:border-orange-300 transition-all">
                        <!-- Left Details -->
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-orange-50 text-orange-500 flex items-center justify-center font-bold">💳</div>
                            <div>
                                <span class="font-bold text-gray-900">#{{ t.id }}</span>
                                <span class="ml-2 px-2 py-0.5 rounded text-[10px] font-bold bg-gray-200 text-gray-700 uppercase">{{ t.sent_currency }}</span>
                            </div>
                        </div>
                        <!-- Flow arrow details -->
                        <div class="flex items-center gap-3 px-4 py-1.5 bg-white border border-gray-150 rounded-lg shadow-sm">
                            <span class="font-bold text-gray-700">{{ t.from_account?.name || 'Naqd Kassa' }}</span>
                            <span class="text-orange-500 font-bold">→</span>
                            <span class="font-bold text-gray-700">{{ t.to_account?.name || 'Aloqa Bank Hisobi' }}</span>
                        </div>
                        <!-- Right Price and Date -->
                        <div class="text-right">
                            <span class="block text-emerald-600 font-bold text-lg">{{ formatMoney(t.sent_amount, t.sent_currency) }}</span>
                            <span class="text-xs text-gray-400 flex items-center gap-1 justify-end mt-1">
                                📅 {{ new Date(t.created_at).toLocaleString() }}
                            </span>
                        </div>
                    </div>
                    <div v-if="!filteredTransfers.length" class="py-12 text-center text-gray-400">Pul o'tkazmalari topilmadi.</div>
                </div>
            </div>

            <!-- TAB 7: Kategoriya Hisoboti -->
            <div v-if="activeTab === 7" class="space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <h2 class="text-xl font-bold font-display text-gray-900">Hisobotlar (Kategoriya bo'yicha)</h2>
                    <p class="text-xs text-gray-500 font-mono mt-1">Kirim va chiqimlar kategoriyalar tasnifida.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="cat in categoriesSummary" :key="cat.name" class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm relative overflow-hidden group">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-widest">{{ cat.name }}</h3>
                            <div class="flex gap-2">
                                <a :href="route('admin.finance.transactions.export.category.excel', { category: cat.name })" target="_blank" class="bg-green-50 text-green-700 border border-green-200 rounded-md px-2 py-0.5 text-[10px] font-medium">EXCEL</a>
                                <a :href="route('admin.finance.transactions.export.category.pdf', { category: cat.name })" target="_blank" class="bg-red-50 text-red-700 border border-red-200 rounded-md px-2 py-0.5 text-[10px] font-medium">PDF</a>
                            </div>
                        </div>
                        <div class="space-y-2 font-mono text-xs text-gray-700">
                            <div class="flex justify-between text-blue-600" v-if="cat.total_income > 0">
                                <span>Kirim:</span>
                                <span>{{ formatMoney(cat.total_income) }}</span>
                            </div>
                            <div class="flex justify-between text-red-600" v-if="cat.total_expense > 0">
                                <span>Chiqim:</span>
                                <span>{{ formatMoney(cat.total_expense) }}</span>
                            </div>
                            <div class="text-[10px] text-gray-400 mt-2 pt-2 border-t border-gray-100 flex justify-between">
                                <span>Tranzaksiyalar soni:</span>
                                <span class="font-bold">{{ cat.transactions.length }} ta</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 8: Kassalar va Banklar - Screenshot 1 Style -->
            <div v-if="activeTab === 8" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Left registers sidebar column -->
                <div class="lg:col-span-3 bg-white border border-gray-150 p-6 rounded-2xl shadow-sm space-y-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Kassalar ro'yxati</h3>
                        <button @click="openNewRegisterModal" class="w-8 h-8 rounded-lg bg-orange-500 hover:bg-orange-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                            +
                        </button>
                    </div>
                    <!-- Search bar -->
                    <input v-model="registerSearchQuery" type="text" placeholder="Qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-3 py-1.5 text-xs w-full">
                    
                    <!-- Registers Cards List -->
                    <div class="space-y-3 max-h-[600px] overflow-y-auto pr-1">
                        <div v-for="r in registers.filter(x => x.name.toLowerCase().includes(registerSearchQuery.toLowerCase()))" :key="r.id" @click="selectedRegisterId = r.id" class="p-4 rounded-2xl border cursor-pointer transition-all flex flex-col justify-between h-32 relative group" :class="selectedRegisterId === r.id ? 'bg-gradient-to-br from-orange-500 to-amber-500 text-white border-transparent shadow-lg' : 'bg-gray-50 border-gray-200 text-gray-900 hover:bg-gray-100'">
                            <div class="flex justify-between items-start">
                                <span class="text-sm font-bold font-display uppercase tracking-wide truncate max-w-[80%]">{{ r.name }}</span>
                                <!-- dot menu -->
                                <button @click.stop="openEditRegisterModal(r)" class="text-gray-400 group-hover:text-gray-700" :class="selectedRegisterId === r.id ? 'text-white/80 group-hover:text-white' : ''">
                                    ⋮
                                </button>
                            </div>
                            <!-- Balances (screenshot style UZS and USD both on card) -->
                            <div class="font-mono text-xs mt-4 space-y-0.5">
                                <div class="flex justify-between">
                                    <span>USD:</span>
                                    <span class="font-bold">{{ r.balance_usd.toLocaleString() }} USD</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>UZS:</span>
                                    <span class="font-bold">{{ r.balance_uzs.toLocaleString() }} UZS</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right detailed register panel column -->
                <div class="lg:col-span-9 space-y-6">
                    <div v-if="selectedRegister" class="space-y-6">
                        <!-- Details box -->
                        <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm grid grid-cols-1 md:grid-cols-2 gap-4 font-mono text-xs">
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Hisob nomi:</span>
                                    <span class="font-bold text-gray-900 text-right">{{ selectedRegister.name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Bank nomi:</span>
                                    <span class="font-bold text-gray-900 text-right">{{ selectedRegister.bank_name || '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Holati:</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 uppercase border border-green-200">
                                        {{ selectedRegister.is_active ? 'Ish holatida' : 'Nofaol' }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Valyuta:</span>
                                    <div class="flex gap-1">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-orange-50 text-orange-600 border border-orange-200 uppercase">USD</span>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-orange-50 text-orange-600 border border-orange-200 uppercase">UZS</span>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Hisob raqami:</span>
                                    <span class="font-bold text-gray-900 text-right">{{ selectedRegister.account_number || '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">MFO kodi:</span>
                                    <span class="font-bold text-gray-900 text-right">{{ selectedRegister.mfo_code || '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Bino:</span>
                                    <span class="font-bold text-gray-900 text-right">{{ selectedRegister.building?.name || 'Nuovo' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Kirim & Chiqim totals row (screenshot style) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Kirim -->
                            <div class="bg-[#f2fcf6] border border-[#d2f3df] p-6 rounded-2xl shadow-sm flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-sm">↑</div>
                                    <span class="text-sm font-bold text-emerald-800">Kirim</span>
                                </div>
                                <div class="text-right font-mono">
                                    <span class="block text-emerald-600 font-bold text-sm">USD: {{ formatMoney(selectedRegisterKirimUsd, 'USD') }}</span>
                                    <span class="block text-emerald-600 font-bold text-sm">UZS: {{ formatMoney(selectedRegisterKirimUzs, 'UZS') }}</span>
                                </div>
                            </div>
                            <!-- Chiqim -->
                            <div class="bg-[#fdf3f3] border border-[#fbd4d4] p-6 rounded-2xl shadow-sm flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-red-500 text-white flex items-center justify-center font-bold text-sm">↓</div>
                                    <span class="text-sm font-bold text-red-800">Chiqim</span>
                                </div>
                                <div class="text-right font-mono">
                                    <span class="block text-red-600 font-bold text-sm">UZS: {{ formatMoney(selectedRegisterChiqimUzs, 'UZS') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons row (screenshot style) -->
                        <div class="flex gap-3">
                            <button @click="activeTab = 3; form.type = 'income'; form.account_id = selectedRegister.id" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-2">
                                <span>+ Kirim</span>
                            </button>
                            <button @click="activeTab = 3; form.type = 'expense'; form.account_id = selectedRegister.id" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-2">
                                <span>+ Chiqim</span>
                            </button>
                            <button @click="activeTab = 6; transferForm.from_account_id = selectedRegister.id" class="px-5 py-2.5 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-2">
                                <span>+ O'tkazma</span>
                            </button>
                        </div>

                        <!-- Register Filters row (screenshot style) -->
                        <div class="bg-white border border-gray-150 p-4 rounded-2xl shadow-sm flex items-center gap-4 flex-wrap">
                            <div class="flex items-center gap-2 text-xs">
                                <input v-model="registerDateFrom" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs py-1 px-2">
                                <span class="text-gray-400">→</span>
                                <input v-model="registerDateTo" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs py-1 px-2">
                            </div>
                            <input v-model="registerSearchQuery" type="text" placeholder="Barcha tranzaksiya..." class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-3 py-1.5 text-xs w-48">
                            <select v-model="registerTypeFilter" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs py-1.5 px-2">
                                <option value="">Barcha turlar</option>
                                <option value="income">Kirim</option>
                                <option value="expense">Chiqim</option>
                            </select>
                        </div>

                        <!-- Register Specific Transactions Table -->
                        <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-hidden">
                            <table class="w-full text-left text-xs font-mono">
                                <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                                    <tr>
                                        <th class="p-4">Hujjat raqami</th>
                                        <th class="p-4">Sana</th>
                                        <th class="p-4">Kontragent</th>
                                        <th class="p-4">Obyekt</th>
                                        <th class="p-4 text-right">Tushum</th>
                                        <th class="p-4">Tranzaksiya turi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="t in filteredRegisterTransactions" :key="t.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="p-4 font-bold text-gray-900">#{{ t.id }}</td>
                                        <td class="p-4 text-gray-500">{{ new Date(t.transaction_date).toLocaleString() }}</td>
                                        <td class="p-4 font-bold text-gray-800">{{ t.counterparty?.name || '-' }}</td>
                                        <td class="p-4 text-gray-600">{{ t.contract?.unit?.building?.name || 'Nuovo' }}</td>
                                        <td class="p-4 text-right font-bold" :class="t.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                                            {{ t.type === 'income' ? '+' : '-' }} {{ formatMoney(t.amount, t.currency) }}
                                        </td>
                                        <td class="p-4">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border border-gray-200 bg-gray-50">
                                                {{ t.category?.name || 'Mijoz to\'lovi' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!filteredRegisterTransactions.length">
                                        <td colspan="6" class="p-8 text-center text-gray-400">Tranzaksiyalar topilmadi.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 9: Kontragentlar - Screenshot 3 Style -->
            <div v-if="activeTab === 9" class="space-y-6">
                <!-- Header Actions row -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div>
                        <h2 class="text-xl font-bold font-display text-gray-900">Kontragentlar</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1">Mijozlar, ta'minotchilar va boshqa kontragentlar</p>
                    </div>
                    <div class="flex gap-3 w-full sm:w-auto">
                        <input v-model="counterpartySearch" type="text" placeholder="Qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-4 py-2 text-sm w-full sm:w-64">
                        <select v-model="counterpartyTypeFilter" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs py-2 px-3">
                            <option value="">Tranzaksiya turi</option>
                            <option value="client">Mijoz to'lovi</option>
                            <option value="supplier">Ta'minotchi</option>
                            <option value="employee">Xodim</option>
                        </select>
                        <button @click="openNewCounterpartyModal" class="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm shrink-0">
                            + Yangi kontragent yaratish
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-hidden">
                    <table class="w-full text-left text-sm font-mono">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                            <tr>
                                <th class="p-4 w-12">№</th>
                                <th class="p-4">Kontragent nomi</th>
                                <th class="p-4">Tranzaksiya turi</th>
                                <th class="p-4">Holat</th>
                                <th class="p-4">Hisob raqami</th>
                                <th class="p-4">Bank</th>
                                <th class="p-4">INN</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(c, idx) in filteredCounterpartiesList" :key="c.id" class="hover:bg-gray-50 transition-colors">
                                <td class="p-4 text-gray-400 font-bold">{{ idx + 1 }}</td>
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <!-- Initials Avatar -->
                                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-sm">
                                            {{ c.name.charAt(0) }}
                                        </div>
                                        <div>
                                            <span class="block font-bold text-gray-900">{{ c.name }}</span>
                                            <span class="text-xs text-gray-400">{{ c.phone || '-' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold border border-gray-200 bg-gray-50">
                                        {{ c.type === 'client' ? 'Mijoz to\'lovi' : (c.type === 'employee' ? 'Xodim Oylik maoshi' : 'Ta\'minotchi') }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold" :class="c.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400'">
                                        {{ c.is_active ? 'Faol' : 'Faol emas' }}
                                    </span>
                                </td>
                                <td class="p-4 text-gray-600">{{ c.account_number || '-' }}</td>
                                <td class="p-4 text-gray-600">{{ c.bank_name || '-' }}</td>
                                <td class="p-4 text-gray-600">{{ c.inn || '-' }}</td>
                            </tr>
                            <tr v-if="!filteredCounterpartiesList.length">
                                <td colspan="7" class="p-8 text-center text-gray-400">Kontragentlar topilmadi.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 10: Hisob-fakturalar & Mijoz to'lovlari - Screenshot 5 Style -->
            <div v-if="activeTab === 10" class="space-y-6">
                <!-- Sub tabs -->
                <div class="flex border-b border-gray-200 bg-white px-6 pt-4 rounded-t-2xl border-x border-t border-gray-150">
                    <button @click="invoiceSubTab = 'payments'" class="px-6 py-3 font-bold text-xs uppercase tracking-widest border-b-2 transition-all" :class="invoiceSubTab === 'payments' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-400'">
                        Mijoz to'lovlari
                    </button>
                    <button @click="invoiceSubTab = 'debts'" class="px-6 py-3 font-bold text-xs uppercase tracking-widest border-b-2 transition-all" :class="invoiceSubTab === 'debts' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-400'">
                        Mijozdan qarzdorlik
                    </button>
                </div>

                <!-- Main Payments Sub-Tab -->
                <div v-if="invoiceSubTab === 'payments'" class="space-y-6 bg-white p-6 rounded-b-2xl border-x border-b border-gray-150 shadow-sm">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <input v-model="clientPaymentsSearch" type="text" placeholder="Qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-4 py-2 text-sm w-full sm:w-64">
                        <button @click="activeTab = 3; form.type = 'income'" class="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm">
                            + To'lov qilish
                        </button>
                    </div>

                    <div class="overflow-x-auto border border-gray-150 rounded-2xl">
                        <table class="w-full text-left text-sm font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                                <tr>
                                    <th class="p-4 w-12">№</th>
                                    <th class="p-4">Ism</th>
                                    <th class="p-4 text-right">Summa</th>
                                    <th class="p-4">To'lov sanasi</th>
                                    <th class="p-4">To'lov turi</th>
                                    <th class="p-4">Hujjat raqami</th>
                                    <th class="p-4 text-center">To'lov kvitantsiyasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(t, idx) in filteredClientPayments" :key="t.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 text-gray-400 font-bold">{{ idx + 1 }}</td>
                                    <td class="p-4 font-bold text-gray-900">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center font-bold text-sm">
                                                {{ t.counterparty?.name.charAt(0) || 'M' }}
                                            </div>
                                            <span>{{ t.counterparty?.name || '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-right font-bold text-red-600">{{ formatMoney(t.amount, t.currency) }}</td>
                                    <td class="p-4 text-gray-500">{{ new Date(t.transaction_date).toLocaleString() }}</td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border border-gray-200 bg-gray-50">
                                            {{ t.category?.name || 'Mijoz to\'lovi' }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-700 font-bold">#{{ t.id }}</td>
                                    <td class="p-4 text-center">
                                        <button @click="openReceipt(t)" class="text-orange-500 hover:text-orange-700 font-bold hover:underline inline-flex items-center gap-1.5">
                                            📥 Yuklab olish
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!filteredClientPayments.length">
                                    <td colspan="7" class="p-8 text-center text-gray-400">To'lovlar topilmadi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Debts Sub-Tab -->
                <div v-if="invoiceSubTab === 'debts'" class="space-y-6 bg-white p-6 rounded-b-2xl border-x border-b border-gray-150 shadow-sm">
                    <div class="flex">
                        <input v-model="clientDebtsSearch" type="text" placeholder="Qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-4 py-2 text-sm w-full sm:w-64">
                    </div>
                    <div class="overflow-x-auto border border-gray-150 rounded-2xl">
                        <table class="w-full text-left text-sm font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                                <tr>
                                    <th class="p-4 w-12">№</th>
                                    <th class="p-4">Ism</th>
                                    <th class="p-4">Telefon</th>
                                    <th class="p-4 text-right">Qarzdorlik (UZS)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(c, idx) in filteredClientDebts" :key="c.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 text-gray-400 font-bold">{{ idx + 1 }}</td>
                                    <td class="p-4 font-bold text-gray-900">{{ c.name }}</td>
                                    <td class="p-4 text-gray-600">{{ c.phone || '-' }}</td>
                                    <td class="p-4 text-right font-bold text-red-600">{{ formatMoney(c.balance) }}</td>
                                </tr>
                                <tr v-if="!filteredClientDebts.length">
                                    <td colspan="4" class="p-8 text-center text-gray-400">Qarzdorliklar topilmadi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODALS & PORTALS -->

        <!-- RECEIPT VIEW MODAL -->
        <Modal :show="showReceiptModal && receiptData !== null" @close="showReceiptModal = false" maxWidth="md">
            <div class="p-6">
                <div class="text-center mb-6">
                    <h2 class="text-xl font-bold uppercase tracking-widest text-gray-900">SHERDOR ERP</h2>
                    <p class="text-[10px] font-mono text-gray-500 uppercase mt-1">To'lov kvitantsiyasi (Receipt)</p>
                </div>
                
                <div class="space-y-2.5 text-xs font-mono border-y border-dashed border-gray-300 py-4 mb-6 text-gray-700">
                    <div class="flex justify-between">
                        <span>Tranzaksiya ID:</span>
                        <span class="font-bold">#{{ receiptData.id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Sana:</span>
                        <span>{{ new Date(receiptData.transaction_date || receiptData.updated_at).toLocaleString() }}</span>
                    </div>
                    <div class="flex justify-between" v-if="receiptData.counterparty">
                        <span>Foydalanuvchi/Mijoz:</span>
                        <span class="font-bold text-right">{{ receiptData.counterparty.name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Kassa/Bank:</span>
                        <span>{{ receiptData.cashRegister?.name || 'Asosiy' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>To'lov usuli:</span>
                        <span class="uppercase font-bold">{{ receiptData.payment_method }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Operatsiya turi:</span>
                        <span class="uppercase font-bold" :class="receiptData.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                            {{ receiptData.type === 'income' ? 'Kirim' : 'Chiqim' }}
                        </span>
                    </div>
                </div>

                <div class="flex justify-between items-end mb-6 bg-gray-50 p-4 rounded-xl border border-gray-150">
                    <span class="text-xs font-bold text-gray-500">JAMI TO'LOV:</span>
                    <span class="text-2xl font-bold font-display text-gray-900">{{ formatMoney(receiptData.amount, receiptData.currency) }}</span>
                </div>

                <div class="flex flex-col items-center border-t border-dashed border-gray-300 pt-6">
                    <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' + encodeURIComponent(route('public.receipt.verify', receiptData.receipt_hash || 'TXN'))" width="100" height="100" alt="QR Code" class="mb-2">
                    <p class="text-[9px] font-mono text-gray-400 tracking-wider uppercase mt-1">{{ receiptData.receipt_hash || 'TXN-SHERDOR-APPROVED' }}</p>
                </div>

                <div class="mt-8 flex justify-center gap-3">
                    <button @click="showReceiptModal = false" class="px-5 py-2 text-xs border border-gray-300 rounded-lg hover:bg-gray-50 font-bold uppercase">Yopish</button>
                    <a :href="`/admin/finance/transactions/${receiptData.id}/print`" target="_blank" class="px-5 py-2 text-xs bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-bold uppercase text-center flex items-center justify-center">Chop etish</a>
                </div>
            </div>
        </Modal>

        <!-- KASSA ADD/EDIT MODAL -->
        <Modal :show="showRegisterModal" @close="showRegisterModal = false" maxWidth="md">
            <div class="p-6">
                <button @click="showRegisterModal = false" class="absolute top-4 right-4 text-gray-500">✕</button>
                <h3 class="text-lg font-bold text-gray-900 mb-6 uppercase tracking-wider">
                    {{ isEditRegister ? 'Kassani tahrirlash' : 'Yangi kassa qo\'shish' }}
                </h3>
                
                <form @submit.prevent="submitRegister" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Kassa nomi *</label>
                        <input v-model="registerForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Turi *</label>
                            <select v-model="registerForm.type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                                <option value="cash">Naqd (Cash)</option>
                                <option value="bank">Bank hisobi</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Valyuta *</label>
                            <select v-model="registerForm.currency" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                                <option value="UZS">UZS</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Initial Balans UZS</label>
                            <input v-model="registerForm.initial_balance_uzs" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Initial Balans USD</label>
                            <input v-model="registerForm.initial_balance_usd" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Initial Balans EUR</label>
                            <input v-model="registerForm.initial_balance_eur" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Bino / Obyekt</label>
                        <select v-model="registerForm.building_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                            <option value="">-- Umumiy kassa --</option>
                            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Bank Nomi</label>
                            <input v-model="registerForm.bank_name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">MFO Kodi</label>
                            <input v-model="registerForm.mfo_code" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Hisob raqami</label>
                        <input v-model="registerForm.account_number" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                    </div>

                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                        <button type="button" @click="showRegisterModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-600 font-bold">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-bold">Saqlash</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- EDIT TRANSACTION MODAL -->
        <Modal :show="showEditTransactionModal" @close="showEditTransactionModal = false" maxWidth="md">
            <div class="p-6">
                <button @click="showEditTransactionModal = false" class="absolute top-4 right-4 text-gray-500">✕</button>
                <h3 class="text-lg font-bold text-gray-900 mb-6 uppercase tracking-wider">Tranzaksiyani tahrirlash</h3>
                
                <form @submit.prevent="submitEditTransaction" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Summa *</label>
                        <input v-model="editTransactionForm.amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Izoh</label>
                        <textarea v-model="editTransactionForm.notes" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" rows="3"></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                        <button type="button" @click="showEditTransactionModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-600 font-bold">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-bold">Saqlash</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- KONTRAgent ADD/EDIT MODAL -->
        <Modal :show="showCounterpartyModal" @close="showCounterpartyModal = false" maxWidth="md">
            <div class="p-6">
                <button @click="showCounterpartyModal = false" class="absolute top-4 right-4 text-gray-500">✕</button>
                <h3 class="text-lg font-bold text-gray-900 mb-6 uppercase tracking-wider">
                    {{ isEditCounterparty ? 'Kontragentni tahrirlash' : 'Yangi kontragent qo\'shish' }}
                </h3>
                
                <form @submit.prevent="submitCounterparty" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">F.I.O / Nomi *</label>
                        <input v-model="counterpartyForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Telefon</label>
                            <input v-model="counterpartyForm.phone" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Email</label>
                            <input v-model="counterpartyForm.email" type="email" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Turi *</label>
                            <select v-model="counterpartyForm.type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                                <option value="client">Mijoz (Client)</option>
                                <option value="supplier">Ta'minotchi (Supplier)</option>
                                <option value="employee">Xodim (Employee)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Dastlabki Balans</label>
                            <input v-model="counterpartyForm.balance" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Hisob raqami</label>
                            <input v-model="counterpartyForm.account_number" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Bank Nomi</label>
                            <input v-model="counterpartyForm.bank_name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">INN</label>
                        <input v-model="counterpartyForm.inn" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                    </div>

                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                        <button type="button" @click="showCounterpartyModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-600 font-bold">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-bold">Saqlash</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- QARZLAR DETAILS MODAL -->
        <Modal :show="showLiabilityModal" @close="showLiabilityModal = false" maxWidth="md">
            <div class="p-6">
                <button @click="showLiabilityModal = false" class="absolute top-4 right-4 text-gray-500">✕</button>
                <h3 class="text-lg font-bold text-gray-900 mb-6 uppercase tracking-wider">
                    {{ isEditMode ? 'Qarzni tahrirlash' : 'Yangi Qarz/Kredit qo\'shish' }}
                </h3>
                
                <form @submit.prevent="submitLiability" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Kreditor nomi *</label>
                        <input v-model="liabilityForm.creditor_name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Turi *</label>
                            <select v-model="liabilityForm.type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                                <option value="bank_loan">Bank loan</option>
                                <option value="supplier_debt">Supplier debt</option>
                                <option value="partner_debt">Partner debt</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Qabul qiluvchi hisob *</label>
                            <select v-model="liabilityForm.fund_account" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                                <option value="main">Main (Asosiy)</option>
                                <option value="credit">Credit (Kredit)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Umumiy Summa *</label>
                            <input v-model="liabilityForm.total_amount" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Foiz stavkasi (%)</label>
                            <input v-model="liabilityForm.interest_rate" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Muddati (oy) *</label>
                            <input v-model="liabilityForm.term_months" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Oylik to'lov</label>
                            <input v-model="liabilityForm.monthly_payment" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Boshlanish sanasi *</label>
                            <input v-model="liabilityForm.start_date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Izoh</label>
                            <input v-model="liabilityForm.description" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                        <button type="button" @click="showLiabilityModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-600 font-bold">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-bold">Saqlash</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- QARZLAR SCHEDULE MODAL -->
        <Modal :show="showScheduleModal" @close="showScheduleModal = false" maxWidth="lg">
            <div class="p-6">
                <button @click="showScheduleModal = false" class="absolute top-4 right-4 text-gray-500">✕</button>
                <h3 class="text-lg font-bold text-gray-900 mb-6 uppercase tracking-wider">To'lovlar grafigi</h3>
                
                <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-xl">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="p-3">Oy</th>
                                <th class="p-3">Sana</th>
                                <th class="p-3 text-right">To'lov</th>
                                <th class="p-3 text-right">Asosiy qarz</th>
                                <th class="p-3 text-right">Qoldiq</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in generatedSchedule" :key="row.month" class="hover:bg-gray-50 transition-colors">
                                <td class="p-3 text-gray-400 font-bold">{{ row.month }}</td>
                                <td class="p-3">{{ row.date }}</td>
                                <td class="p-3 text-right font-bold text-emerald-600">{{ formatMoney(row.payment) }}</td>
                                <td class="p-3 text-right text-gray-700">{{ formatMoney(row.principal) }}</td>
                                <td class="p-3 text-right text-gray-500">{{ formatMoney(row.balance) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Modal>

        <!-- QARZLAR TO'LOV MODAL -->
        <Modal :show="showPaymentModal" @close="showPaymentModal = false" maxWidth="sm">
            <div class="p-6">
                <button @click="showPaymentModal = false" class="absolute top-4 right-4 text-gray-500">✕</button>
                <h3 class="text-lg font-bold text-gray-900 mb-6 uppercase tracking-wider">Qarz to'lovi</h3>
                
                <form @submit.prevent="submitPayment" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Summa *</label>
                        <input v-model="paymentForm.amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Hisob turi *</label>
                        <select v-model="paymentForm.account_type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                            <option value="main">Main (Asosiy)</option>
                            <option value="credit">Credit (Kredit)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Sana *</label>
                        <input v-model="paymentForm.payment_date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Izoh</label>
                        <input v-model="paymentForm.notes" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                    </div>
                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                        <button type="button" @click="showPaymentModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-600 font-bold">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2 bg-emerald-600 text-white rounded-lg font-bold">To'lash</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- SUPPLIER PAYMENT MODAL -->
        <Modal :show="showSupplierPaymentModal" @close="showSupplierPaymentModal = false" maxWidth="sm">
            <div class="p-6">
                <button @click="showSupplierPaymentModal = false" class="absolute top-4 right-4 text-gray-500">✕</button>
                <h3 class="text-lg font-bold text-gray-900 mb-6 uppercase tracking-wider">Ta'minotchiga to'lov</h3>
                
                <form @submit.prevent="submitSupplierPayment" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">To'lov Summasi *</label>
                        <input v-model="supplierPaymentForm.amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Hisob turi *</label>
                        <select v-model="supplierPaymentForm.account_type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                            <option value="main">Main (Naqd kassa)</option>
                            <option value="bank">Bank hisobi</option>
                            <option value="credit">Credit (Kredit)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Izoh</label>
                        <input v-model="supplierPaymentForm.notes" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                    </div>
                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                        <button type="button" @click="showSupplierPaymentModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-600 font-bold">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2 bg-emerald-600 text-white rounded-lg font-bold">To'lash</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- SUPPLIER EDIT MODAL -->
        <Modal :show="showEditSupplierModal" @close="showEditSupplierModal = false" maxWidth="md">
            <div class="p-6">
                <button @click="showEditSupplierModal = false" class="absolute top-4 right-4 text-gray-500">✕</button>
                <h3 class="text-lg font-bold text-gray-900 mb-6 uppercase tracking-wider">Ta'minotchi tahrirlash</h3>
                
                <form @submit.prevent="submitEditSupplier" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Kompaniya nomi *</label>
                        <input v-model="editSupplierForm.company_name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Vakil nomi *</label>
                        <input v-model="editSupplierForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Joriy balans (qarz) *</label>
                        <input v-model="editSupplierForm.balance" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                    </div>
                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                        <button type="button" @click="showEditSupplierModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-600 font-bold">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-bold">Saqlash</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- CONTRACT APPROVE MODAL -->
        <Modal :show="showContractApproveModal" @close="showContractApproveModal = false" maxWidth="md">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-6 uppercase tracking-wider">Mijoz to'lovini taqsimlash</h3>
                <div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono text-gray-500">
                    Kiritilgan summa: <strong class="text-emerald-600 text-sm">{{ formatMoney(selectedContractTxn?.amount, selectedContractTxn?.currency) }}</strong>
                </div>
                <form @submit.prevent="submitContractApprove" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-gray-400 mb-1 uppercase">Shartnoma Qarziga Yo'naltirish *</label>
                        <input v-model="contractApproveForm.applied_amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-1 uppercase">Qaytimga (Naqdlashtirishga) Ajratish</label>
                        <input :value="contractApproveForm.refund_amount" type="number" readonly class="bg-gray-100 border border-gray-200 text-gray-500 rounded-md w-full h-9 font-bold">
                    </div>
                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                        <button type="button" @click="showContractApproveModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-600 font-bold">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2 bg-emerald-600 text-white rounded-lg font-bold">Tasdiqlash</button>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>
