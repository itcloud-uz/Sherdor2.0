<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SellerLayout from '@/Layouts/SellerLayout.vue';
import { Head, router, Link, usePage, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import BrigadeLedgerModal from '@/Components/Ledger/BrigadeLedgerModal.vue';
import OwnerLedgerModal from '@/Components/Ledger/OwnerLedgerModal.vue';
import GuardLedgerModal from '@/Components/Ledger/GuardLedgerModal.vue';
import Modal from '@/Components/Modal.vue';
import ExpensesBreakdownModal from '@/Components/ExpensesBreakdownModal.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const page = usePage();
watch(() => page.props.flash, (flash) => {
    if (flash && flash.success) {
        alert(flash.success);
    }
    if (flash && flash.error) {
        alert(flash.error);
    }
    if (flash && flash.message) {
        alert(flash.message);
    }
}, { deep: true });
const gpsCoords = ref({ latitude: null, longitude: null });

const CurrentLayout = computed(() => {
    const roles = page.props.auth.user?.roles || [];
    const roleNames = roles.map(r => typeof r === 'string' ? r : r.name);
    if (roleNames.includes('Sotuvchi') || roleNames.includes('Operator')) {
        return SellerLayout;
    }
    return AdminLayout;
});

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
    users: Array,
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
    allCategories: Array,
    clients: Array,
    partners: Array,
    fixedAssets: Array,
    brigades: Array,
    machineOwners: Array,
    registers: Array,
    counterparties: Array,
    invoices: Array,
    moneyTransfers: Array,
    rates: Array,
    deletedTransactions: Array,
    financeActions: Array,
    deletedCounterparties: Array,
    imports: Array
});

const activeTab = ref(0);

const sortedTransactions = computed(() => {
    if (!props.transactions) return [];
    return [...props.transactions].sort((a, b) => {
        const dateA = a.transaction_date ? new Date(a.transaction_date) : new Date(a.created_at || 0);
        const dateB = b.transaction_date ? new Date(b.transaction_date) : new Date(b.created_at || 0);
        if (dateB - dateA !== 0) {
            return dateB - dateA;
        }
        return b.id - a.id;
    });
});
const isProcessing = ref(false);
const showQuickTransactionModal = ref(false);
const showQuickTransferModal = ref(false);
const transferError = ref('');
const isEditingTransfer = ref(false);
const editingTransferId = ref(null);
const showViewTransferModal = ref(false);
const viewingTransfer = ref(null);

// Transaction Import refs & methods
// Transaction Import refs & methods
const showImportModal = ref(false);
const isImporting = ref(false);
const importStep = ref(1); // 1 = Upload, 2 = Preview & Map
const importPreviewRows = ref([]);
const importErrors = ref([]);
const importCounterparties = ref([]);
const importCategories = ref([]);
const importFileName = ref('');

const importForm = ref({
    account_id: '',
    building_id: '',
    file: null,
    comment: ''
});
const fileInputRef = ref(null);

function openImportModal() {
    importForm.value = {
        account_id: props.registers && props.registers.length > 0 ? props.registers[0].id : '',
        building_id: props.buildings && props.buildings.length > 0 ? props.buildings[0].id : '',
        file: null,
        comment: ''
    };
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
    importStep.value = 1;
    importPreviewRows.value = [];
    importErrors.value = [];
    importCounterparties.value = [];
    importCategories.value = [];
    importFileName.value = '';
    showImportModal.value = true;
}

function handleImportFileChange(e) {
    importForm.value.file = e.target.files[0];
}

function submitImport() {
    if (!importForm.value.file) {
        alert('Iltimos, Excel faylini tanlang.');
        return;
    }
    if (!importForm.value.account_id) {
        alert('Iltimos, hisobni tanlang.');
        return;
    }
    if (!importForm.value.building_id) {
        alert('Iltimos, qurilish obyektini tanlang.');
        return;
    }

    isImporting.value = true;

    const formData = new FormData();
    formData.append('file', importForm.value.file);

    axios.post(route('admin.finance.transactions.import.preview'), formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    }).then(res => {
        if (res.data.success) {
            importPreviewRows.value = res.data.rows;
            importErrors.value = res.data.errors || [];
            importCounterparties.value = res.data.counterparties || [];
            importCategories.value = res.data.categories || [];
            importFileName.value = res.data.file_name;
            importStep.value = 2;
        } else {
            alert(res.data.message || 'Xatolik yuz berdi.');
        }
    }).catch(err => {
        console.error(err);
        alert(err.response?.data?.message || 'Faylni o\'qishda xatolik yuz berdi.');
    }).finally(() => {
        isImporting.value = false;
    });
}

function saveImportMappedData() {
    isImporting.value = true;
    router.post(route('admin.finance.transactions.import.save'), {
        account_id: importForm.value.account_id,
        building_id: importForm.value.building_id,
        file_name: importFileName.value,
        comment: importForm.value.comment || '',
        rows: importPreviewRows.value
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showImportModal.value = false;
            importStep.value = 1;
            importPreviewRows.value = [];
            importForm.value = { account_id: '', building_id: '', file: null, comment: '' };
        },
        onError: (errors) => {
            alert(Object.values(errors).join('\n'));
        },
        onFinish: () => {
            isImporting.value = false;
        }
    });
}

function backToImportStep1() {
    importStep.value = 1;
}

function confirmRollbackImport(importId) {
    if (confirm('Haqiqatan ham ushbu importni bekor qilmoqchimisiz? Undan yaratilgan barcha tranzaksiyalar butunlay o\'chiriladi!')) {
        isImporting.value = true;
        router.delete(route('admin.finance.transactions.import.rollback', importId), {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isImporting.value = false;
            }
        });
    }
}

function getCpTypeName(type) {
    const types = {
        employee: 'Xodim',
        supplier: 'Ta\'minotchi',
        partner: 'Hamkor',
        client: 'Mijoz',
        brigade: 'Brigada',
        machine_owner: 'Texnika egasi',
        user: 'Foydalanuvchi'
    };
    return types[type] || type;
}

function updateRowType(row) {
    if (!row.category_id) {
        row.type = 'expense';
        return;
    }
    const cat = importCategories.value.find(c => c.id === row.category_id);
    if (cat) {
        row.type = cat.type;
        
        // Reset counterparty if it's no longer valid for the selected category
        if (row.counterparty_id) {
            const validCps = getFilteredCounterparties(row);
            const isValid = validCps.some(cp => cp.id === row.counterparty_id);
            if (!isValid) {
                row.counterparty_id = null;
            }
        }
    } else {
        row.type = 'expense';
    }
}

function getFilteredCounterparties(row) {
    if (!row.category_id) {
        return importCounterparties.value;
    }
    const cat = importCategories.value.find(c => c.id === row.category_id);
    if (!cat) {
        return importCounterparties.value;
    }
    
    let targetType = cat.target_type;
    if (!targetType) {
        const catNameLower = cat.name.toLowerCase();
        if (catNameLower.includes('texnika') || catNameLower.includes('mashina')) {
            targetType = 'machine_owner';
        } else if (catNameLower.includes('ishchi') || catNameLower.includes('oylik') || catNameLower.includes('avans') || catNameLower.includes('ish haqi')) {
            targetType = 'worker';
        } else if (catNameLower.includes('hamkor')) {
            targetType = 'partner';
        } else if (catNameLower.includes('mijoz') || catNameLower.includes('shartnoma')) {
            targetType = 'client';
        } else if (catNameLower.includes('ta\'minot') || catNameLower.includes('taminot') || catNameLower.includes('material')) {
            targetType = 'supplier';
        } else if (catNameLower.includes('brigada')) {
            targetType = 'brigade';
        }
    }

    if (!targetType) {
        return importCounterparties.value;
    }
    
    // Safe case-insensitive helper to compare customable types ignoring backslashes
    const matchType = (val, targetName) => {
        if (!val) return false;
        const cleanVal = val.replace(/\\/g, '').toLowerCase();
        const cleanTarget = targetName.replace(/\\/g, '').toLowerCase();
        return cleanVal.endsWith(cleanTarget);
    };

    return importCounterparties.value.filter(cp => {
        if (targetType === 'machine_owner') {
            return matchType(cp.customable_type, 'MachineOwner');
        }
        if (targetType === 'worker') {
            return matchType(cp.customable_type, 'Worker');
        }
        if (targetType === 'partner') {
            return matchType(cp.customable_type, 'Partner');
        }
        if (targetType === 'client') {
            return matchType(cp.customable_type, 'Client');
        }
        if (targetType === 'supplier') {
            return matchType(cp.customable_type, 'Supplier') || (!cp.customable_type && cp.type === 'supplier');
        }
        if (targetType === 'brigade') {
            return matchType(cp.customable_type, 'Brigade');
        }
        return true;
    });
}

const selectedDeletedTransactions = ref([]);

const isAllDeletedTransactionsSelected = computed(() => {
    return props.deletedTransactions && props.deletedTransactions.length > 0 && selectedDeletedTransactions.value.length === props.deletedTransactions.length;
});

function toggleAllDeletedTransactions() {
    if (isAllDeletedTransactionsSelected.value) {
        selectedDeletedTransactions.value = [];
    } else {
        selectedDeletedTransactions.value = props.deletedTransactions.map(t => t.id);
    }
}

function bulkRestoreDeletedTransactions() {
    if (selectedDeletedTransactions.value.length === 0) return;
    if (confirm(`Haqiqatan ham tanlangan ${selectedDeletedTransactions.value.length} ta tranzaksiyani ortga qaytarmoqchimisiz?`)) {
        isProcessing.value = true;
        router.post(route('admin.finance.transactions.bulk_restore'), { preserveScroll: true, preserveState: true,
            ids: selectedDeletedTransactions.value
        }, {
            onSuccess: () => {
                isProcessing.value = false;
                selectedDeletedTransactions.value = [];
            },
            onError: () => isProcessing.value = false
        });
    }
}

function bulkForceDeleteTransactions() {
    if (selectedDeletedTransactions.value.length === 0) return;
    if (confirm(`Haqiqatan ham tanlangan ${selectedDeletedTransactions.value.length} ta tranzaksiyani butunlay o'chirib tashlamoqchimisiz? Uni qaytarib bo'lmaydi!`)) {
        isProcessing.value = true;
        router.delete(route('admin.finance.transactions.bulk_force_destroy'), { preserveScroll: true, preserveState: true,
            data: {
                ids: selectedDeletedTransactions.value
            },
            onSuccess: () => {
                isProcessing.value = false;
                selectedDeletedTransactions.value = [];
            },
            onError: () => isProcessing.value = false
        });
    }
}

// Client search states
const clientSearchQuery = ref('');
const clientPaymentType = ref('monthly');
const selectedUnitId = ref('');
const selectedBuildingName = ref('');
const selectedBlockId = ref('');
const showClientDropdown = ref(false);

const isAdmin = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return false;
    const rawRoles = user.roles || [];
    const roles = rawRoles.map(r => (typeof r === 'string' ? r : r.name || '').toLowerCase());
    return roles.includes('admin') || roles.includes('super-admin') || roles.includes('super_admin');
});

const hasPerm = (perm) => {
    const user = page.props.auth?.user;
    if (!user) return false;
    if (isAdmin.value) return true;
    const permissions = user.permissions || [];
    // Also map role permissions or assume inertia passes all user permissions in user.permissions array
    // Wait, spatie passes them in `user.permissions` usually if configured, or we can check roles.
    // Partner has full access, so:
    const roles = user.roles ? user.roles.map(r => (typeof r === 'string' ? r : r.name || '').toLowerCase()) : [];
    if (roles.includes('partner')) {
        if (['edit_transaction', 'delete_transaction', 'create_transaction', 'access finance'].includes(perm)) return true;
    }
    return permissions.includes(perm) || permissions.some(p => p.name === perm);
};

const requestTransactionEdit = (t, action) => {
    const actionText = action === 'edit' ? 'tahrirlash' : "o'chirish";
    const reason = prompt(`Iltimos, nima uchun ushbu tranzaksiyani ${actionText} kerakligini yozing:`);
    if (!reason) return;
    router.post(route('admin.finance.transaction_requests.store'), {
        transaction_id: t.id,
        action: action,
        reason: reason
    }, { preserveScroll: true, preserveState: true });
};

// Tab 11: Reports States
const reportSubTab = ref('overview');
const reportJournalSearch = ref('');
const reportJournalDateFrom = ref('');
const reportJournalDateTo = ref('');
const reportJournalAccountId = ref('');
const reportJournalType = ref('');
const reportJournalCategoryId = ref('');

const pendingSearchQuery = ref('');
const liabilitySearchQuery = ref('');
const supplierSearchQuery = ref('');
const categorySearchQuery = ref('');
const reportBuildingsSearchQuery = ref('');
const reportDebtsSearchQuery = ref('');
const telegramSearchQuery = ref('');

// Kirim/Chiqim states & helpers
const kirimChiqimSearch = ref('');
const kirimChiqimDateFrom = ref('');
const kirimChiqimDateTo = ref('');
const kirimChiqimTableTab = ref('all');
const kirimChiqimRegisterId = ref('');

function formatMoneyKirimChiqim(amount) {
    const parsed = parseFloat(amount);
    const val = isNaN(parsed) ? 0 : Math.round(parsed);
    return new Intl.NumberFormat('en-US').format(val) + " so'm";
}

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    try {
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '';
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${day}.${month}.${year} ${hours}:${minutes}`;
    } catch (e) {
        return '';
    }
}

const getTransactionSourceName = (t) => {
    if (!t) return '-';

    // 1. Direct counterparty relation
    if (t.counterparty && t.counterparty.name) {
        return t.counterparty.name;
    }

    // 2. Direct brigade relation
    if (t.brigade && t.brigade.name) {
        return t.brigade.name;
    }

    // 3. Direct worker relation
    if (t.worker && t.worker.name) {
        return t.worker.name;
    }

    // 4. Direct supplier relation
    if (t.supplier) {
        const sName = t.supplier.company_name || t.supplier.name;
        if (sName) return sName;
    }

    // 5. Direct client relation (or contract client)
    if (t.client) {
        const c = t.client;
        const name = [c.first_name, c.last_name, c.middle_name].filter(Boolean).join(' ');
        if (name) return name;
    }
    if (t.contract && t.contract.client) {
        const c = t.contract.client;
        const name = [c.first_name, c.last_name, c.middle_name].filter(Boolean).join(' ');
        if (name) return name;
    }

    // 6. Direct partner relation
    if (t.partner) {
        const pName = t.partner.name || t.partner.user?.name;
        if (pName) return pName;
    }

    // 7. Check target_type / target_id in props options
    if (t.target_type && t.target_id) {
        const tType = String(t.target_type).toLowerCase();
        if (tType.includes('brigade')) {
            const b = props.brigades?.find(x => x.id == t.target_id);
            if (b && b.name) return b.name;
        }
        if (tType.includes('worker')) {
            const w = props.workers?.find(x => x.id == t.target_id);
            if (w && w.name) return w.name;
        }
        if (tType.includes('supplier')) {
            const s = props.suppliers?.find(x => x.id == t.target_id);
            if (s) return s.company_name || s.name;
        }
        if (tType.includes('counterparty')) {
            const cp = props.counterparties?.find(x => x.id == t.target_id);
            if (cp && cp.name) return cp.name;
        }
        if (tType.includes('partner')) {
            const p = props.partners?.find(x => x.id == t.target_id);
            if (p) return p.name || p.user?.name;
        }
        if (tType.includes('client')) {
            const c = props.clients?.find(x => x.id == t.target_id);
            if (c) {
                const name = [c.first_name, c.last_name, c.middle_name].filter(Boolean).join(' ');
                if (name) return name;
            }
        }
    }

    // 8. Check brigade_id, worker_id, supplier_id, counterparty_id in props options
    if (t.brigade_id && props.brigades) {
        const b = props.brigades.find(x => x.id == t.brigade_id);
        if (b && b.name) return b.name;
    }
    if (t.worker_id && props.workers) {
        const w = props.workers.find(x => x.id == t.worker_id);
        if (w && w.name) return w.name;
    }
    if (t.supplier_id && props.suppliers) {
        const s = props.suppliers.find(x => x.id == t.supplier_id);
        if (s) return s.company_name || s.name;
    }
    if (t.counterparty_id && props.counterparties) {
        const cp = props.counterparties.find(x => x.id == t.counterparty_id);
        if (cp && cp.name) return cp.name;
    }

    // 9. Money transfer
    const transfer = t.money_transfer || t.moneyTransfer;
    if (transfer) {
        if (t.type === 'income') {
            const fromName = transfer.from_account?.name || transfer.fromAccount?.name;
            return fromName ? `O'tkazma (${fromName} kassasidan)` : `O'tkazma (Kassadan)`;
        } else if (t.type === 'expense') {
            const toName = transfer.to_account?.name || transfer.toAccount?.name;
            return toName ? `O'tkazma (${toName} kassasiga)` : `O'tkazma (Kassaga)`;
        }
    }

    // 10. Fallback to notes or description if non-empty
    if (t.notes) {
        if (t.notes.includes("kassasidan") || t.notes.includes("kassasiga")) {
            return t.notes;
        }
        return t.notes;
    }
    if (t.description) {
        return t.description;
    }

    if (t.category_display === "Ichki O'tkazma" || (t.category && t.category.name === "Ichki O'tkazma")) {
        return `Ichki o'tkazma`;
    }

    return '-';
};

const filteredKirimChiqimTransactions = computed(() => {
    let result = sortedTransactions.value.filter(t => t && t.status === 'approved');
    if (kirimChiqimDateFrom.value) {
        result = result.filter(t => {
            const dateStr = t.transaction_date || t.created_at;
            if (!dateStr) return false;
            const dateOnly = typeof dateStr === 'string' ? dateStr.split('T')[0] : new Date(dateStr).toISOString().split('T')[0];
            return dateOnly >= kirimChiqimDateFrom.value;
        });
    }
    if (kirimChiqimDateTo.value) {
        result = result.filter(t => {
            const dateStr = t.transaction_date || t.created_at;
            if (!dateStr) return false;
            const dateOnly = typeof dateStr === 'string' ? dateStr.split('T')[0] : new Date(dateStr).toISOString().split('T')[0];
            return dateOnly <= kirimChiqimDateTo.value;
        });
    }
    if (kirimChiqimRegisterId.value) {
        result = result.filter(t => t.account_id === parseInt(kirimChiqimRegisterId.value));
    }
    if (kirimChiqimSearch.value) {
        const query = kirimChiqimSearch.value.toLowerCase();
        result = result.filter(t => 
            (t.notes || '').toLowerCase().includes(query) ||
            (t.cashRegister?.name || t.cash_register?.name || '').toLowerCase().includes(query) ||
            (t.creator?.name || '').toLowerCase().includes(query) ||
            (t.creator?.phone || '').toLowerCase().includes(query)
        );
    }
    return result;
});

const kirimChiqimTotalIncome = computed(() => {
    return filteredKirimChiqimTransactions.value
        .filter(t => t.type === 'income')
        .reduce((sum, t) => {
            const parsed = parseFloat(t.amount);
            return sum + (isNaN(parsed) ? 0 : parsed);
        }, 0);
});

const kirimChiqimTotalExpense = computed(() => {
    return filteredKirimChiqimTransactions.value
        .filter(t => t.type === 'expense')
        .reduce((sum, t) => {
            const parsed = parseFloat(t.amount);
            return sum + (isNaN(parsed) ? 0 : parsed);
        }, 0);
});

const kirimChiqimBalance = computed(() => {
    return kirimChiqimTotalIncome.value - kirimChiqimTotalExpense.value;
});

const displayedKirimChiqimTransactions = computed(() => {
    let list = filteredKirimChiqimTransactions.value;
    if (kirimChiqimTableTab.value === 'income') {
        list = list.filter(t => t.type === 'income');
    } else if (kirimChiqimTableTab.value === 'expense') {
        list = list.filter(t => t.type === 'expense');
    }
    return list;
});

// Kirim-Chiqim client-side pagination
const kirimChiqimCurrentPage = ref(1);
const kirimChiqimPerPage = ref(50);
const paginatedKirimChiqimTransactions = computed(() => {
    const start = (kirimChiqimCurrentPage.value - 1) * kirimChiqimPerPage.value;
    return displayedKirimChiqimTransactions.value.slice(start, start + kirimChiqimPerPage.value);
});
const totalKirimChiqimPages = computed(() => Math.max(1, Math.ceil(displayedKirimChiqimTransactions.value.length / kirimChiqimPerPage.value)));
const visibleKirimChiqimPages = computed(() => {
    const current = kirimChiqimCurrentPage.value;
    const last = totalKirimChiqimPages.value;
    const delta = 2;
    const left = current - delta;
    const right = current + delta + 1;
    const range = [];
    const rangeWithDots = [];
    let l;

    for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= left && i < right)) {
            range.push(i);
        }
    }

    for (let i of range) {
        if (l) {
            if (i - l === 2) {
                rangeWithDots.push(l + 1);
            } else if (i - l > 2) {
                rangeWithDots.push('...');
            }
        }
        rangeWithDots.push(i);
        l = i;
    }

    return rangeWithDots;
});

// Reset page on filter changes
watch([kirimChiqimDateFrom, kirimChiqimDateTo, kirimChiqimRegisterId, kirimChiqimSearch, kirimChiqimTableTab], () => {
    kirimChiqimCurrentPage.value = 1;
});

const displayedKirimChiqimIncomeSum = computed(() => {
    return displayedKirimChiqimTransactions.value
        .filter(t => t.type === 'income')
        .reduce((sum, t) => {
            const parsed = parseFloat(t.amount);
            return sum + (isNaN(parsed) ? 0 : parsed);
        }, 0);
});

const displayedKirimChiqimExpenseSum = computed(() => {
    return displayedKirimChiqimTransactions.value
        .filter(t => t.type === 'expense')
        .reduce((sum, t) => {
            const parsed = parseFloat(t.amount);
            return sum + (isNaN(parsed) ? 0 : parsed);
        }, 0);
});

const displayedKirimChiqimTotalSum = computed(() => {
    return displayedKirimChiqimIncomeSum.value - displayedKirimChiqimExpenseSum.value;
});

function exportKirimChiqimToExcel() {
    const headers = ['№', 'Yaratilgan sana', 'Kassa', 'Turi', 'Summa', 'Izoh', 'Xodim'];
    const rows = displayedKirimChiqimTransactions.value.map((t, idx) => {
        const parsedAmount = parseFloat(t.amount);
        const amountVal = isNaN(parsedAmount) ? 0 : Math.round(parsedAmount);
        return [
            idx + 1,
            formatDateTime(t.transaction_date || t.created_at),
            t.cashRegister?.name || t.cash_register?.name || 'Asosiy',
            t.type === 'income' ? 'Kirim' : 'Chiqim',
            amountVal,
            t.notes || '',
            t.creator?.phone || t.creator?.name || ''
        ];
    });

    // Add empty row for spacing
    rows.push([]);

    // Add summary rows based on the active tab
    if (kirimChiqimTableTab.value === 'all') {
        rows.push(['', '', '', 'Jami Kirim', displayedKirimChiqimIncomeSum.value, '', '']);
        rows.push(['', '', '', 'Jami Chiqim', -displayedKirimChiqimExpenseSum.value, '', '']);
        rows.push(['', '', '', 'Sof Balans', displayedKirimChiqimTotalSum.value, '', '']);
    } else if (kirimChiqimTableTab.value === 'income') {
        rows.push(['', '', '', 'Jami Kirim', displayedKirimChiqimIncomeSum.value, '', '']);
    } else if (kirimChiqimTableTab.value === 'expense') {
        rows.push(['', '', '', 'Jami Chiqim', -displayedKirimChiqimExpenseSum.value, '', '']);
    }

    const csvContent = [
        headers.join(';'),
        ...rows.map(row => row.map(val => `"${String(val !== undefined && val !== null ? val : '').replace(/"/g, '""')}"`).join(';'))
    ].join('\r\n');

    const blob = new Blob(["\ufeff" + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", `kirim_chiqim_hisoboti_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

const reportFilteredTransactions = computed(() => {
    let result = sortedTransactions.value.filter(t => t.status === 'approved');
    if (reportJournalSearch.value) {
        const query = reportJournalSearch.value.toLowerCase();
        result = result.filter(t => 
            (t.notes || '').toLowerCase().includes(query) ||
            (t.description || '').toLowerCase().includes(query) ||
            getTransactionSourceName(t).toLowerCase().includes(query)
        );
    }
    if (reportJournalDateFrom.value) {
        result = result.filter(t => t.transaction_date >= reportJournalDateFrom.value);
    }
    if (reportJournalDateTo.value) {
        result = result.filter(t => t.transaction_date <= reportJournalDateTo.value);
    }
    if (reportJournalAccountId.value) {
        result = result.filter(t => t.account_id === parseInt(reportJournalAccountId.value));
    }
    if (reportJournalType.value) {
        result = result.filter(t => t.type === reportJournalType.value);
    }
    if (reportJournalCategoryId.value) {
        result = result.filter(t => t.category_id === parseInt(reportJournalCategoryId.value));
    }
    return result;
});

// Tab 7 Report client-side pagination
const reportJournalCurrentPage = ref(1);
const reportJournalPerPage = ref(50);
const paginatedReportFilteredTransactions = computed(() => {
    const start = (reportJournalCurrentPage.value - 1) * reportJournalPerPage.value;
    return reportFilteredTransactions.value.slice(start, start + reportJournalPerPage.value);
});
const totalReportJournalPages = computed(() => Math.max(1, Math.ceil(reportFilteredTransactions.value.length / reportJournalPerPage.value)));
const visibleReportJournalPages = computed(() => {
    const current = reportJournalCurrentPage.value;
    const last = totalReportJournalPages.value;
    const delta = 2;
    const left = current - delta;
    const right = current + delta + 1;
    const range = [];
    const rangeWithDots = [];
    let l;

    for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= left && i < right)) {
            range.push(i);
        }
    }

    for (let i of range) {
        if (l) {
            if (i - l === 2) {
                rangeWithDots.push(l + 1);
            } else if (i - l > 2) {
                rangeWithDots.push('...');
            }
        }
        rangeWithDots.push(i);
        l = i;
    }

    return rangeWithDots;
});

// Reset page on filter changes
watch([reportJournalSearch, reportJournalDateFrom, reportJournalDateTo, reportJournalAccountId, reportJournalType, reportJournalCategoryId], () => {
    reportJournalCurrentPage.value = 1;
});

const reportOverviewStats = computed(() => {
    const totalBalances = (props.registers || []).filter(r => !r.parent_id).reduce((sum, r) => sum + parseFloat(r.balance || r.balance_uzs || 0), 0);
    const totalReceivables = (props.counterparties || []).filter(c => c.type === 'client').reduce((sum, c) => sum + parseFloat(c.balance || 0), 0);
    const totalPayables = 
        (props.suppliers || []).reduce((sum, s) => sum + Math.max(0, parseFloat(s.balance || 0)), 0) +
        (props.workers || []).reduce((sum, w) => sum + Math.max(0, parseFloat(w.balance || 0)), 0) +
        (props.financialLiabilities || []).filter(l => l.status === 'active').reduce((sum, l) => sum + parseFloat(l.total_amount || 0), 0);
    const netCapital = totalBalances + totalReceivables - totalPayables;
    return { totalBalances, totalReceivables, totalPayables, netCapital };
});

const kassaReports = computed(() => {
    const regs = (props.registers || []).filter(r => !r.parent_id);
    
    const items = regs.map(r => {
        const income = parseFloat(r.total_income_uzs || 0);
        const expense = parseFloat(r.total_expense_uzs || 0);
        const balance = parseFloat(r.balance_uzs || 0);
        return {
            id: r.id,
            name: r.name,
            income,
            expense,
            balance
        };
    });

    const totalIncome = items.reduce((sum, item) => sum + item.income, 0);
    const totalExpense = items.reduce((sum, item) => sum + item.expense, 0);
    const totalBalance = items.reduce((sum, item) => sum + item.balance, 0);

    return {
        items,
        totalIncome,
        totalExpense,
        totalBalance
    };
});

const reportBuildingsSummary = computed(() => {
    if (!props.buildings) return [];
    
    const summaryMap = {};
    
    props.buildings.forEach(b => {
        const name = (b.name || '').trim();
        const txs = sortedTransactions.value.filter(t => t.building_id === b.id && t.status === 'approved');
        const incomes = txs.filter(t => t.type === 'income').reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
        const expenses = txs.filter(t => t.type === 'expense').reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
        const netFlow = incomes - expenses;
        
        const buildingInvoices = (props.invoices || []).filter(inv => inv.building_id === b.id);
        const totalInvoiced = buildingInvoices.reduce((sum, inv) => sum + parseFloat(inv.amount || 0), 0);
        const paidInvoiced = buildingInvoices.reduce((sum, inv) => sum + parseFloat(inv.paid_amount || 0), 0);
        const unpaidInvoiced = totalInvoiced - paidInvoiced;
        
        if (!summaryMap[name]) {
            summaryMap[name] = {
                id: b.id, // Using first encountered building id as key
                name: name,
                incomes: 0,
                expenses: 0,
                netFlow: 0,
                totalInvoiced: 0,
                unpaidInvoiced: 0
            };
        }
        
        summaryMap[name].incomes += incomes;
        summaryMap[name].expenses += expenses;
        summaryMap[name].netFlow += netFlow;
        summaryMap[name].totalInvoiced += totalInvoiced;
        summaryMap[name].unpaidInvoiced += unpaidInvoiced;
    });
    
    let list = Object.values(summaryMap);
    
    if (reportBuildingsSearchQuery.value) {
        const q = reportBuildingsSearchQuery.value.toLowerCase().trim();
        list = list.filter(b => b.name && b.name.toLowerCase().includes(q));
    }
    return list;
});

const filteredReportDebtors = computed(() => {
    let list = (props.counterparties || []).filter(x => x.type === 'client' && parseFloat(x.balance || 0) < 0);
    if (reportDebtsSearchQuery.value) {
        const q = reportDebtsSearchQuery.value.toLowerCase().trim();
        list = list.filter(c => 
            (c.name && c.name.toLowerCase().includes(q)) || 
            (c.phone && c.phone.includes(q))
        );
    }
    return list;
});

const filteredReportCreditors = computed(() => {
    let list = (props.counterparties || []).filter(x => x.type === 'client' && parseFloat(x.balance || 0) > 0);
    if (reportDebtsSearchQuery.value) {
        const q = reportDebtsSearchQuery.value.toLowerCase().trim();
        list = list.filter(c => 
            (c.name && c.name.toLowerCase().includes(q)) || 
            (c.phone && c.phone.includes(q))
        );
    }
    return list;
});

const filteredReportSupplierPayables = computed(() => {
    let list = (props.suppliers || []).filter(x => parseFloat(x.balance || 0) > 0);
    if (reportDebtsSearchQuery.value) {
        const q = reportDebtsSearchQuery.value.toLowerCase().trim();
        list = list.filter(s => 
            (s.company_name && s.company_name.toLowerCase().includes(q)) || 
            (s.name && s.name.toLowerCase().includes(q))
        );
    }
    return list;
});

const filteredReportEmployeePayables = computed(() => {
    let list = (props.workers || []).filter(x => parseFloat(x.balance || 0) > 0);
    if (reportDebtsSearchQuery.value) {
        const q = reportDebtsSearchQuery.value.toLowerCase().trim();
        list = list.filter(w => 
            (w.name && w.name.toLowerCase().includes(q)) || 
            (w.role && w.role.toLowerCase().includes(q))
        );
    }
    return list;
});

function exportReportToCsv() {
    const headers = ['Sana', 'Kategoriya', 'Kassa/Hisob', 'Kontragent', 'Turi', 'Summa (UZS)', 'Izoh'];
    const rows = reportFilteredTransactions.value.map(t => [
        new Date(t.transaction_date).toLocaleDateString(),
        t.category_display || 'Noma\'lum',
        t.cashRegister?.name || t.cash_register?.name || 'Asosiy',
        getTransactionSourceName(t),
        t.type === 'income' ? 'Kirim' : 'Chiqim',
        t.amount,
        t.notes || ''
    ]);

    const csvContent = [
        headers.join(','),
        ...rows.map(e => e.map(val => `"${String(val).replace(/"/g, '""')}"`).join(','))
    ].join('\n');

    const blob = new Blob(["\ufeff" + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", `hisobot_jurnali_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

const selectedInvoiceIds = ref([]);
const invoiceSearchQuery = ref('');
const invoiceCurrencyFilter = ref('');
const showInvoiceSelectorModal = ref(false);
const documentNumberInput = ref('');
const isEditingDocNumber = ref(false);
const extraNotesInput = ref('');
const fileInput = ref(null);

const nextDocNumber = computed(() => {
    if (!props.transactions || props.transactions.length === 0) return 1;
    return Math.max(...props.transactions.map(t => t.id)) + 1;
});

const selectedCounterpartyObj = computed(() => {
    if (!form.value.counterparty_id) return null;
    return props.counterparties?.find(c => c.id === form.value.counterparty_id) || null;
});

const availableInvoices = computed(() => {
    if (!form.value.counterparty_id) return [];
    
    let items = [];
    
    // 1. Add actual invoices
    if (props.invoices) {
        props.invoices.forEach(inv => {
            if (inv.counterparty_id === form.value.counterparty_id && inv.status !== 'paid') {
                items.push({
                    id: inv.id,
                    invoice_number: inv.invoice_number,
                    due_date: inv.due_date,
                    status: inv.status,
                    amount: inv.amount,
                    paid_amount: inv.paid_amount || 0,
                    currency: inv.currency
                });
            }
        });
    }
    
    // 2. Add client contract payment schedules as pseudo-invoices
    if (requiresClient.value && form.value.contract_id && contractSchedules.value) {
        contractSchedules.value.forEach(s => {
            items.push({
                id: 'sched_' + s.id,
                invoice_number: `To'lov Rejasi (Muddati: ${s.due_date ? new Date(s.due_date).toLocaleDateString() : 'Noma\'lum'})`,
                due_date: s.due_date,
                status: s.status === 'partial' ? 'qisman' : 'yangi',
                amount: s.expected_amount,
                paid_amount: s.paid_amount || 0,
                currency: form.value.currency || 'UZS'
            });
        });
    }
    
    // Apply search query filter
    if (invoiceSearchQuery.value) {
        const q = invoiceSearchQuery.value.toLowerCase();
        items = items.filter(item => item.invoice_number.toLowerCase().includes(q));
    }
    
    // Apply currency filter
    if (invoiceCurrencyFilter.value) {
        items = items.filter(item => item.currency === invoiceCurrencyFilter.value);
    }
    
    return items;
});

const availableInvoicesCurrencies = computed(() => {
    return [...new Set(availableInvoices.value.map(item => item.currency))];
});

const isAllInvoicesSelected = computed({
    get() {
        if (availableInvoices.value.length === 0) return false;
        return availableInvoices.value.every(inv => selectedInvoiceIds.value.includes(inv.id));
    },
    set(val) {
        if (val) {
            availableInvoices.value.forEach(inv => {
                if (!selectedInvoiceIds.value.includes(inv.id)) {
                    selectedInvoiceIds.value.push(inv.id);
                }
            });
        } else {
            availableInvoices.value.forEach(inv => {
                const idx = selectedInvoiceIds.value.indexOf(inv.id);
                if (idx > -1) {
                    selectedInvoiceIds.value.splice(idx, 1);
                }
            });
        }
    }
});

function confirmInvoiceSelection() {
    if (selectedInvoiceIds.value.length > 0) {
        let totalUnpaid = 0;
        selectedInvoiceIds.value.forEach(id => {
            const inv = availableInvoices.value.find(i => i.id === id);
            if (inv) {
                const unpaid = parseFloat(inv.amount) - parseFloat(inv.paid_amount || 0);
                totalUnpaid += unpaid;
            }
        });
        form.value.amount = totalUnpaid.toFixed(2);

        // If the selected invoice is a schedule, set the payment_schedule_id
        const firstId = selectedInvoiceIds.value[0];
        if (typeof firstId === 'string' && firstId.startsWith('sched_')) {
            form.value.payment_schedule_id = parseInt(firstId.replace('sched_', ''));
        } else {
            form.value.payment_schedule_id = '';
        }
    }
    showInvoiceSelectorModal.value = false;
}

// Sub-tabs states
const transferSubTab = ref('transfers'); // 'transfers' or 'exchanges'
const invoiceSubTab = ref('payments'); // 'payments' or 'debts'

// Synchronize activeTab and subTab with URL parameters
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
            const subParam = url.searchParams.get('sub');
            if (subParam !== null) {
                if (activeTab.value === 10) {
                    invoiceSubTab.value = subParam;
                }
            }
        } catch (e) {
            console.error('URL parse error in Finance tab watcher', e);
        }
    },
    { immediate: true }
);

// Synchronize URL query parameters with activeTab and subTab changes
watch([activeTab, invoiceSubTab], ([newTab, newSub]) => {
    try {
        const url = new URL(window.location.href);
        const currentTab = url.searchParams.get('tab');
        const currentSub = url.searchParams.get('sub');
        
        let subVal = null;
        if (newTab === 10) {
            subVal = newSub;
        }
        
        const isTabDiff = currentTab === null || parseInt(currentTab, 10) !== newTab;
        const isSubDiff = currentSub !== subVal;
        
        if (isTabDiff || isSubDiff) {
            url.searchParams.set('tab', newTab);
            if (subVal) {
                url.searchParams.set('sub', subVal);
            } else {
                url.searchParams.delete('sub');
            }
            window.history.replaceState({}, '', url.pathname + url.search);
        }
    } catch (e) {
        console.error('URL replace error in activeTab watcher', e);
    }
});
// Instant sidebar tab switching listener
const handleSwitchTabEvent = (e) => {
    if (e && e.detail !== undefined) {
        activeTab.value = e.detail;
    }
};

onMounted(() => {
    window.addEventListener('switch-finance-tab', handleSwitchTabEvent);
});

onUnmounted(() => {
    window.removeEventListener('switch-finance-tab', handleSwitchTabEvent);
});

// Exchange rates state and actions removed since only UZS exists

// Tab 8 selected register logic
const selectedRegisterId = ref(null);
const selectedRegister = computed(() => {
    return props.registers?.find(r => r.id === selectedRegisterId.value) || null;
});

const selectedRegisterTransactions = computed(() => {
    if (!selectedRegisterId.value) return [];
    const childIds = (props.registers || [])
        .filter(r => r.parent_id === selectedRegisterId.value && r.is_active)
        .map(r => r.id);
    const targetIds = [selectedRegisterId.value, ...childIds];
    return sortedTransactions.value.filter(t => targetIds.includes(t.account_id) && t.status === 'approved');
});

const isCreditType = computed(() => {
    if (registerForm.value.type === 'credit') return true;
    if (hasSchedule.value) return true;
    if (registerForm.value.parent_id) {
        const parent = props.registers?.find(r => r.id === registerForm.value.parent_id);
        return parent && parent.type === 'credit';
    }
    return false;
});

// Selected Register Totals
const selectedRegisterKirimUzs = computed(() => {
    if (selectedRegister.value && !registerSearchQuery.value && !registerDateFrom.value && !registerDateTo.value && !registerTypeFilter.value) {
        return parseFloat(selectedRegister.value.total_income_uzs || 0);
    }
    return filteredRegisterTransactions.value
        .filter(t => t.type === 'income' && (t.currency === 'UZS' || !t.currency))
        .reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
});
const selectedRegisterChiqimUzs = computed(() => {
    if (selectedRegister.value && !registerSearchQuery.value && !registerDateFrom.value && !registerDateTo.value && !registerTypeFilter.value) {
        return parseFloat(selectedRegister.value.total_expense_uzs || 0);
    }
    return filteredRegisterTransactions.value
        .filter(t => t.type === 'expense' && (t.currency === 'UZS' || !t.currency))
        .reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
});

const parentCreditTotalAmountUzs = computed(() => {
    if (!selectedRegister.value || selectedRegister.value.type !== 'credit') return 0;
    return (props.registers || [])
        .filter(r => r.parent_id === selectedRegister.value.id && r.currency === 'UZS')
        .reduce((sum, r) => sum + parseFloat(r.total_amount || 0), 0);
});


const getRegisterName = (id) => {
    const r = props.registers?.find(x => x.id === id);
    return r ? r.name : '';
};

const isTransferFromLocked = ref(false);
const getRegisterNameWithCurrency = (id) => {
    const r = props.registers?.find(x => x.id === id);
    return r ? `${r.name}` : '';
};

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

// Register specific client-side pagination
const registerDetailsCurrentPage = ref(1);
const registerDetailsPerPage = ref(50);
const paginatedRegisterTransactions = computed(() => {
    const start = (registerDetailsCurrentPage.value - 1) * registerDetailsPerPage.value;
    return filteredRegisterTransactions.value.slice(start, start + registerDetailsPerPage.value);
});
const totalRegisterDetailsPages = computed(() => Math.max(1, Math.ceil(filteredRegisterTransactions.value.length / registerDetailsPerPage.value)));
const visibleRegisterDetailsPages = computed(() => {
    const current = registerDetailsCurrentPage.value;
    const last = totalRegisterDetailsPages.value;
    const delta = 2;
    const left = current - delta;
    const right = current + delta + 1;
    const range = [];
    const rangeWithDots = [];
    let l;

    for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= left && i < right)) {
            range.push(i);
        }
    }

    for (let i of range) {
        if (l) {
            if (i - l === 2) {
                rangeWithDots.push(l + 1);
            } else if (i - l > 2) {
                rangeWithDots.push('...');
            }
        }
        rangeWithDots.push(i);
        l = i;
    }

    return rangeWithDots;
});

// Reset page on filter changes or selection change
watch([registerSearchQuery, registerDateFrom, registerDateTo, registerTypeFilter, selectedRegisterId], () => {
    registerDetailsCurrentPage.value = 1;
});

// --- QARZ KASSALAR & DEBT LEDGER LOGIC (3-rasm & 4-rasm) ---
const showTakeDebtModal = ref(false);
const showRepayDebtModal = ref(false);
const showDebtLedgerModal = ref(false);
const selectedDebtRegister = ref(null);

const debtLedgerBuildingFilter = ref('');
const debtLedgerStartDate = ref('');
const debtLedgerEndDate = ref('');

const takeDebtForm = useForm({
    name: '',
    type: 'credit',
    parent_id: '',
    start_date: new Date().toISOString().substring(0, 10),
    total_amount: '',
    building_id: '',
    description: '',
    is_active: true
});

const repayDebtForm = useForm({
    from_account_id: '',
    to_account_id: '',
    sent_amount: '',
    transaction_date: new Date().toISOString().substring(0, 10),
    building_id: '',
    notes: ''
});

const debtRegistersSummary = computed(() => {
    const parentQarzReg = props.registers?.find(r => r.name.toLowerCase().includes('qarz') && !r.parent_id);
    const parentId = parentQarzReg ? parentQarzReg.id : null;
    
    const list = (props.registers || []).filter(r => {
        return r.type === 'credit' || (parentId && r.parent_id === parentId) || r.name.toLowerCase().includes('qarz');
    });

    return list.map(reg => {
        const transfers = (props.transfers || []).filter(t => t.from_account_id === reg.id || t.to_account_id === reg.id);
        
        let takenSum = 0;
        let repaidSum = 0;

        transfers.forEach(t => {
            const amt = Number(t.sent_amount || t.amount || 0);
            if (t.from_account_id === reg.id) {
                takenSum += amt;
            } else if (t.to_account_id === reg.id) {
                repaidSum += amt;
            }
        });

        if (!takenSum && reg.total_amount) {
            takenSum = Number(reg.total_amount);
        }
        if (!takenSum && reg.initial_balance_uzs) {
            takenSum = Number(reg.initial_balance_uzs);
        }

        const qoldiqRaw = takenSum - repaidSum;

        return {
            ...reg,
            takenSum,
            repaidSum,
            qoldiqRaw,
            buildingName: reg.building?.name || 'SHER-DOR TJM',
            transfers
        };
    });
});

const totalDebtTakenSum = computed(() => {
    return debtRegistersSummary.value.reduce((sum, r) => sum + r.takenSum, 0);
});

const totalDebtRepaidSum = computed(() => {
    return debtRegistersSummary.value.reduce((sum, r) => sum + r.repaidSum, 0);
});

const totalNetDebtQoldiq = computed(() => {
    return totalDebtTakenSum.value - totalDebtRepaidSum.value;
});

function openTakeDebtModal() {
    takeDebtForm.reset();
    takeDebtForm.type = 'credit';
    takeDebtForm.start_date = new Date().toISOString().substring(0, 10);
    showTakeDebtModal.value = true;
}

function submitTakeDebt() {
    takeDebtForm.post(route('admin.finance.registers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showTakeDebtModal.value = false;
        }
    });
}

function openRepayDebtModal(presetRegister = null) {
    repayDebtForm.reset();
    if (presetRegister) {
        repayDebtForm.to_account_id = presetRegister.id;
    }
    repayDebtForm.transaction_date = new Date().toISOString().substring(0, 10);
    showRepayDebtModal.value = true;
}

function submitRepayDebt() {
    repayDebtForm.post(route('admin.finance.internal_transfer'), {
        preserveScroll: true,
        onSuccess: () => {
            showRepayDebtModal.value = false;
        }
    });
}

function openDebtLedgerModal(reg) {
    selectedDebtRegister.value = reg;
    debtLedgerBuildingFilter.value = '';
    debtLedgerStartDate.value = '';
    debtLedgerEndDate.value = '';
    showDebtLedgerModal.value = true;
}

const filteredDebtLedgerRows = computed(() => {
    if (!selectedDebtRegister.value) return [];
    const regId = selectedDebtRegister.value.id;
    
    let list = (props.transfers || []).filter(t => t.from_account_id === regId || t.to_account_id === regId);
    
    if (debtLedgerBuildingFilter.value) {
        list = list.filter(t => t.building_id == debtLedgerBuildingFilter.value);
    }
    if (debtLedgerStartDate.value) {
        list = list.filter(t => (t.transfer_date || t.created_at) >= debtLedgerStartDate.value);
    }
    if (debtLedgerEndDate.value) {
        list = list.filter(t => (t.transfer_date || t.created_at) <= debtLedgerEndDate.value);
    }

    return list.map(t => {
        const isDebtTaken = (t.from_account_id === regId);
        const amt = Number(t.sent_amount || t.amount || 0);
        
        return {
            id: t.id,
            formattedDate: t.transfer_date ? new Date(t.transfer_date).toLocaleDateString('ru-RU') : new Date(t.created_at).toLocaleDateString('ru-RU'),
            takenAmount: isDebtTaken ? amt : 0,
            repaidAmount: !isDebtTaken ? amt : 0,
            takenKassa: isDebtTaken ? (t.to_account?.name || t.toAccount?.name || 'Kassa') : '',
            repaidKassa: !isDebtTaken ? (t.from_account?.name || t.fromAccount?.name || 'Korxona Balansi') : '',
            description: t.description || t.notes || ''
        };
    });
});

const debtLedgerTotalTaken = computed(() => {
    return filteredDebtLedgerRows.value.reduce((sum, r) => sum + r.takenAmount, 0);
});

const debtLedgerTotalRepaid = computed(() => {
    return filteredDebtLedgerRows.value.reduce((sum, r) => sum + r.repaidAmount, 0);
});

const debtLedgerNetQoldiq = computed(() => {
    return debtLedgerTotalTaken.value - debtLedgerTotalRepaid.value;
});

// Tab 2 Jurnal Filters & Computeds
const jurnalDateFrom = ref('');
const jurnalDateTo = ref('');
const jurnalAccountId = ref('');
const jurnalTypeFilter = ref('');
const jurnalCounterpartyId = ref('');
const jurnalBuildingId = ref('');
const jurnalSearchQuery = ref('');

const counterpartyFilterOptions = computed(() => {
    const list = [{ id: '', name: 'Barchasi' }];
    if (props.counterparties && Array.isArray(props.counterparties)) {
        list.push(...props.counterparties);
    }
    return list;
});

const filteredJurnalTransactions = computed(() => {
    let result = sortedTransactions.value.filter(t => t.status === 'approved');

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

// Jurnal client-side pagination
const jurnalCurrentPage = ref(1);
const jurnalPerPage = ref(50);
const paginatedJurnalTransactions = computed(() => {
    const start = (jurnalCurrentPage.value - 1) * jurnalPerPage.value;
    return filteredJurnalTransactions.value.slice(start, start + jurnalPerPage.value);
});
const totalJurnalPages = computed(() => Math.max(1, Math.ceil(filteredJurnalTransactions.value.length / jurnalPerPage.value)));
const visibleJurnalPages = computed(() => {
    const current = jurnalCurrentPage.value;
    const last = totalJurnalPages.value;
    const delta = 2;
    const left = current - delta;
    const right = current + delta + 1;
    const range = [];
    const rangeWithDots = [];
    let l;

    for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= left && i < right)) {
            range.push(i);
        }
    }

    for (let i of range) {
        if (l) {
            if (i - l === 2) {
                rangeWithDots.push(l + 1);
            } else if (i - l > 2) {
                rangeWithDots.push('...');
            }
        }
        rangeWithDots.push(i);
        l = i;
    }

    return rangeWithDots;
});

// Reset page on filter changes
watch([jurnalDateFrom, jurnalDateTo, jurnalAccountId, jurnalTypeFilter, jurnalCounterpartyId, jurnalBuildingId, jurnalSearchQuery], () => {
    jurnalCurrentPage.value = 1;
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
    return (props.registers || []).filter(r => !r.parent_id).reduce((sum, r) => sum + parseFloat(r.balance_uzs || 0), 0);
});
const totalBalansUsd = computed(() => 0);

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
const showTransferFilter = ref(false);
const transferDateFrom = ref('');
const transferDateTo = ref('');

const filteredTransfers = computed(() => {
    if (!props.moneyTransfers) return [];
    
    let list = props.moneyTransfers;
    
    // 2. Filter by search query
    if (transferSearch.value) {
        const q = transferSearch.value.toLowerCase();
        list = list.filter(t => 
            (t.description || '').toLowerCase().includes(q) ||
            (t.from_account?.name || '').toLowerCase().includes(q) ||
            (t.to_account?.name || '').toLowerCase().includes(q)
        );
    }
    
    // 3. Filter by date range
    if (transferDateFrom.value) {
        list = list.filter(t => t.created_at >= transferDateFrom.value);
    }
    if (transferDateTo.value) {
        list = list.filter(t => t.created_at <= transferDateTo.value + 'T23:59:59');
    }
    
    return list;
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
    return sortedTransactions.value
        .filter(t => t.counterparty?.type === 'client' && t.status === 'approved');
});

const clientDebts = computed(() => {
    return (props.counterparties || [])
        .filter(c => c.type === 'client' && Number(c.balance || 0) !== 0)
        .map(c => ({
            ...c,
            debt_amount: Math.abs(Number(c.balance))
        }));
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
const editFileInput = ref(null);
const editTransactionForm = ref({
    id: null,
    amount: '',
    notes: '', // To'lov maqsadi
    extra_notes: '', // Izoh
    attachment: null, // Chek fayli
    category_id: '',
    building_id: '',
    account_id: '',
    payment_method: 'cash',
    transaction_date: '',
    counterparty_id: '',
    partner_id: '',
    worker_id: '',
    brigade_id: '',
    machinery_id: '',
    supplier_id: '',
    fixed_asset_id: '',
    exchange_rate: 1.0,
    converted_amount: '',
    type: 'expense'
});

function handleEditFileUpload(e) {
    const file = e.target.files[0];
    if (file) {
        editTransactionForm.value.attachment = file;
    }
}

function openEditTransactionModal(txn) {
    if (txn.money_transfer_id) {
        const transfer = (props.moneyTransfers || []).find(mt => mt.id === txn.money_transfer_id) || txn.moneyTransfer || txn.money_transfer;
        if (transfer) {
            openEditTransferModal(transfer);
            return;
        }
    }

    let paymentPurpose = txn.notes || '';
    let extraNotes = txn.description || '';
    if (!extraNotes && paymentPurpose.includes('\n[Izoh]: ')) {
        const parts = paymentPurpose.split('\n[Izoh]: ');
        paymentPurpose = parts[0];
        extraNotes = parts[1];
    }

    let txDate = '';
    if (txn.transaction_date) {
        txDate = String(txn.transaction_date).slice(0, 10);
    } else if (txn.created_at) {
        txDate = String(txn.created_at).slice(0, 10);
    }

    editTransactionForm.value = {
        id: txn.id,
        amount: txn.amount,
        notes: paymentPurpose,
        extra_notes: extraNotes,
        attachment: null,
        category_id: txn.category_id || '',
        building_id: txn.building_id || '',
        account_id: txn.account_id || '',
        payment_method: txn.payment_method || 'cash',
        transaction_date: txDate,
        counterparty_id: txn.counterparty_id || '',
        partner_id: txn.partner_id || '',
        worker_id: txn.worker_id || '',
        brigade_id: txn.brigade_id || '',
        machinery_id: txn.machinery_id || '',
        supplier_id: txn.supplier_id || '',
        fixed_asset_id: txn.fixed_asset_id || '',
        exchange_rate: txn.exchange_rate || 1.0,
        converted_amount: txn.converted_amount || '',
        type: txn.type || 'expense'
    };
    showEditTransactionModal.value = true;
}

function submitEditTransaction() {
    isProcessing.value = true;

    // Create form data payload with Laravel method spoofing
    const payload = {
        ...editTransactionForm.value,
        description: editTransactionForm.value.extra_notes || '',
        _method: 'put'
    };
    delete payload.extra_notes;

    router.post(route('admin.finance.transactions.update', editTransactionForm.value.id), payload, {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
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
        router.delete(route('admin.finance.transactions.destroy', id), { preserveScroll: true, preserveState: true,
            onSuccess: () => isProcessing.value = false,
            onError: () => isProcessing.value = false
        });
    }
}

function restoreTransaction(id) {
    if (confirm("Ushbu tranzaksiyani ortga qaytarmoqchimisiz (dastlabki holatiga)? Balanslar qaytadan hisoblanadi.")) {
        isProcessing.value = true;
        router.post(route('admin.finance.transactions.restore', id), {}, { preserveScroll: true, preserveState: true,
            onSuccess: () => isProcessing.value = false,
            onError: () => isProcessing.value = false
        });
    }
}

function forceDeleteTransaction(id) {
    if (confirm("Haqiqatan ham bu tranzaksiyani butunlay o'chirib tashlamoqchimisiz? Uni qaytarib bo'lmaydi!")) {
        isProcessing.value = true;
        router.delete(route('admin.finance.transactions.force_destroy', id), { preserveScroll: true, preserveState: true,
            onSuccess: () => isProcessing.value = false,
            onError: () => isProcessing.value = false
        });
    }
}

// Maker/Checker action queues
const pendingTransactions = computed(() => {
    let list = sortedTransactions.value.filter(t => t.status === 'pending');
    if (pendingSearchQuery.value) {
        const q = pendingSearchQuery.value.toLowerCase().trim();
        list = list.filter(t => 
            (t.notes && t.notes.toLowerCase().includes(q)) ||
            (t.counterparty?.name && t.counterparty.name.toLowerCase().includes(q)) ||
            (t.creator?.name && t.creator.name.toLowerCase().includes(q)) ||
            (t.cashRegister?.name && t.cashRegister.name.toLowerCase().includes(q)) ||
            (t.amount && t.amount.toString().includes(q))
        );
    }
    return list;
});
const historicalTransactions = computed(() => sortedTransactions.value.filter(t => t.status !== 'pending'));

const filteredFinancialLiabilities = computed(() => {
    let list = props.financialLiabilities || [];
    if (liabilitySearchQuery.value) {
        const q = liabilitySearchQuery.value.toLowerCase().trim();
        list = list.filter(l => 
            (l.creditor_name && l.creditor_name.toLowerCase().includes(q)) ||
            (l.type && l.type.toLowerCase().includes(q)) ||
            (l.description && l.description.toLowerCase().includes(q)) ||
            (l.total_amount && l.total_amount.toString().includes(q))
        );
    }
    return list;
});

const filteredSuppliers = computed(() => {
    let list = props.suppliers || [];
    if (supplierSearchQuery.value) {
        const q = supplierSearchQuery.value.toLowerCase().trim();
        list = list.filter(s => 
            (s.company_name && s.company_name.toLowerCase().includes(q)) ||
            (s.name && s.name.toLowerCase().includes(q))
        );
    }
    return list;
});

const filteredTelegramTransactions = computed(() => {
    let list = sortedTransactions.value.filter(x => x.source === 'telegram');
    if (telegramSearchQuery.value) {
        const q = telegramSearchQuery.value.toLowerCase().trim();
        list = list.filter(t => 
            (t.notes && t.notes.toLowerCase().includes(q)) ||
            (t.creator?.name && t.creator.name.toLowerCase().includes(q)) ||
            (t.cashRegister?.name && t.cashRegister.name.toLowerCase().includes(q)) ||
            (t.category_display && t.category_display.toLowerCase().includes(q)) ||
            (t.amount && t.amount.toString().includes(q)) ||
            (t.status && t.status.toLowerCase().includes(q))
        );
    }
    return list;
});

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
    transaction_date: new Date().toLocaleString('sv').slice(0, 10),
    attachment: null,
    notes: '',
    description: '',
    auto_approve: true,
    latitude: null,
    longitude: null,
    source: 'admin_panel',
    exchange_rate: 1.0,
    converted_amount: '',
    payment_schedule_id: '',
    linked_work_type: '',
    linked_work_id: ''
});

const transferForm = ref({
    from_account_id: '',
    to_account_id: '',
    sent_amount: '',
    exchange_rate: 1.0,
    transaction_date: new Date().toLocaleString('sv').slice(0, 10),
    notes: '',
    description: ''
});



const selectedParentRegisterId = ref('');
const selectedSubRegisterId = ref('');

const parentRegisters = computed(() => {
    return (props.registers || []).filter(r => !r.parent_id && r.is_active);
});

const subRegisters = computed(() => {
    if (!selectedParentRegisterId.value) return [];
    return (props.registers || []).filter(r => r.parent_id === selectedParentRegisterId.value && r.is_active);
});

function handleParentRegisterChange() {
    if (subRegisters.value.length > 0) {
        selectedSubRegisterId.value = '';
        form.value.account_id = ''; // user must select sub kassa
    } else {
        selectedSubRegisterId.value = '';
        form.value.account_id = selectedParentRegisterId.value;
    }
}

function handleSubRegisterChange() {
    form.value.account_id = selectedSubRegisterId.value || selectedParentRegisterId.value;
}

const showCounterpartyField = computed(() => {
    return requiresClient.value || requiresSupplier.value || requiresPartner.value;
});

// Watch individual IDs to automatically set counterparty_id
watch(() => form.value.partner_id, (newVal) => {
    if (newVal && props.counterparties) {
        const c = props.counterparties.find(x => (x.type === 'partner' || x.type === 'shareholder') && x.customable_id == newVal);
        if (c) {
            form.value.counterparty_id = c.id;
        } else {
            const cAlt = props.counterparties.find(x => x.customable_id == newVal && x.customable_type?.includes('Partner'));
            if (cAlt) form.value.counterparty_id = cAlt.id;
        }
    }
});

watch(() => form.value.worker_id, (newVal) => {
    fetchUnpaidWorks('worker', newVal);
    if (newVal && props.counterparties) {
        const c = props.counterparties.find(x => x.type === 'employee' && x.customable_id == newVal && !x.customable_type?.includes('User'));
        if (c) {
            form.value.counterparty_id = c.id;
        }
    }
});

watch(() => editTransactionForm.value?.counterparty_id, (newVal) => {
    if (newVal && props.counterparties) {
        const c = props.counterparties.find(x => x.id == newVal);
        if (c) {
            editTransactionForm.value.client_id = c.type === 'client' ? c.customable_id : '';
            editTransactionForm.value.supplier_id = c.type === 'supplier' ? c.customable_id : '';
            editTransactionForm.value.worker_id = (c.type === 'employee' && !c.customable_type?.includes('User')) ? c.customable_id : '';
        }
    }
});

watch(() => form.value.supplier_id, (newVal) => {
    if (newVal && props.counterparties) {
        const c = props.counterparties.find(x => x.type === 'supplier' && x.customable_id == newVal);
        if (c) {
            form.value.counterparty_id = c.id;
        }
    }
});

const unpaidWorks = ref([]);
const isFetchingWorks = ref(false);

const fetchUnpaidWorks = async (type, id) => {
    unpaidWorks.value = [];
    form.value.linked_work_type = '';
    form.value.linked_work_id = '';
    
    if (!id) return;
    
    isFetchingWorks.value = true;
    try {
        const response = await fetch(`/admin/hr/target/${type}/${id}/unpaid-works`);
        if (response.ok) {
            unpaidWorks.value = await response.json();
        }
    } catch (e) {
        console.error('Failed to fetch unpaid works', e);
    } finally {
        isFetchingWorks.value = false;
    }
};

watch(() => form.value.brigade_id, (newVal) => {
    fetchUnpaidWorks('brigade', newVal);
});

watch(() => form.value.machine_owner_id, (newVal) => {
    fetchUnpaidWorks('machine_owner', newVal);
});

watch(() => form.value.client_id, (newVal) => {
    if (newVal && props.counterparties) {
        const c = props.counterparties.find(x => x.type === 'client' && x.customable_id == newVal);
        if (c) {
            form.value.counterparty_id = c.id;
        }
    }
});

// Watch account selection to default the currency
watch(() => form.value.account_id, (newVal) => {
    if (newVal && props.registers) {
        const reg = props.registers.find(r => r.id == newVal);
        if (reg) {
            form.value.currency = reg.currency;
            if (reg.building_id) {
                form.value.building_id = reg.building_id;
            }
        }
    }
});

// Watch contract selection to automatically select the building and unit
watch(() => form.value.contract_id, (newVal) => {
    if (newVal && props.contracts) {
        const c = props.contracts.find(x => x.id == newVal);
        if (c) {
            const buildingId = c.unit?.floor?.building?.id || c.unit?.floor?.building_id || c.building_id;
            if (buildingId) {
                form.value.building_id = buildingId;
            }
            if (c.unit_id) {
                selectedUnitId.value = c.unit_id;
            }
        }
    } else {
        selectedUnitId.value = '';
    }
});

// Watch unit selection to automatically sync contract, building and client
watch(() => selectedUnitId.value, (newVal) => {
    if (newVal && props.contracts) {
        const c = props.contracts.find(x => x.unit_id == newVal);
        if (c) {
            form.value.contract_id = c.id;
            const buildingId = c.unit?.floor?.building?.id || c.unit?.floor?.building_id || c.building_id;
            if (buildingId) {
                form.value.building_id = buildingId;
            }
            if (c.client_id) {
                form.value.client_id = c.client_id;
                const cp = props.counterparties?.find(x => x.type === 'client' && x.customable_id == c.client_id);
                if (cp) {
                    form.value.counterparty_id = cp.id;
                    clientSearchQuery.value = cp.name;
                } else if (c.client) {
                    clientSearchQuery.value = (c.client.first_name + ' ' + (c.client.last_name || '')).trim();
                }
            }
        }
    }
});

// Watch building selection to automatically sync selectedBuildingName and selectedBlockId
watch(() => form.value.building_id, (newVal) => {
    if (newVal && props.buildings) {
        const b = props.buildings.find(x => x.id == newVal);
        if (b) {
            selectedBuildingName.value = b.name;
            selectedBlockId.value = b.id;
        }
    } else {
        selectedBuildingName.value = '';
        selectedBlockId.value = '';
    }
});

// Watch counterparty selection to sync polymorphic fields and reset sub-selections
watch(() => form.value.counterparty_id, (newVal) => {
    selectedInvoiceIds.value = [];

    if (newVal && props.counterparties) {
        const c = props.counterparties.find(x => x.id == newVal);
        if (c) {
            form.value.client_id = c.type === 'client' ? c.customable_id : '';
            form.value.supplier_id = c.type === 'supplier' ? c.customable_id : '';
            form.value.worker_id = (c.type === 'employee' && !c.customable_type?.includes('User')) ? c.customable_id : '';
            if (c.type === 'client') {
                clientSearchQuery.value = c.name;
                
                // Keep selected contract if it already belongs to this client
                if (form.value.contract_id && props.contracts) {
                    const currentContract = props.contracts.find(x => x.id == form.value.contract_id);
                    if (!currentContract || currentContract.client_id != c.customable_id) {
                        form.value.contract_id = '';
                        form.value.payment_schedule_id = '';
                    }
                } else if (props.contracts) {
                    const clientContractsList = props.contracts.filter(x => x.client_id == c.customable_id);
                    if (clientContractsList.length === 1) {
                        form.value.contract_id = clientContractsList[0].id;
                    }
                }
            } else {
                form.value.contract_id = '';
                form.value.payment_schedule_id = '';
            }
        }
    } else if (!newVal) {
        form.value.contract_id = '';
        form.value.payment_schedule_id = '';
        form.value.client_id = '';
        form.value.supplier_id = '';
        form.value.worker_id = '';
        clientSearchQuery.value = '';
    }
});

// Registers Modal & CRUD helpers
const showRegisterModal = ref(false);
const hasSchedule = ref(false);
const isSubKassaCreation = ref(false);
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
    mfo_code: '',
    balance: 0,
    parent_id: '',
    creditor_name: '',
    total_amount: '',
    interest_rate: '',
    term_months: '',
    grace_period_months: '',
    start_date: '',
    end_date: '',
    monthly_payment: '',
    description: ''
});
const isEditRegister = ref(false);

const activeRegisterMenuId = ref(null);
function toggleRegisterMenu(id) {
    activeRegisterMenuId.value = activeRegisterMenuId.value === id ? null : id;
}
function triggerEdit(r) {
    activeRegisterMenuId.value = null;
    openEditRegisterModal(r);
}
function triggerDelete(id) {
    activeRegisterMenuId.value = null;
    deleteRegister(id);
}

// Close the menu when clicking anywhere else
if (typeof window !== 'undefined') {
    window.addEventListener('click', () => {
        activeRegisterMenuId.value = null;
    });
}

function openNewRegisterModal(parentId = '') {
    // If parentId is a PointerEvent / MouseEvent, ignore it and treat as empty string
    const parentIdVal = (parentId && typeof parentId !== 'object') ? parentId : '';
    
    isEditRegister.value = false;
    hasSchedule.value = false;
    isSubKassaCreation.value = !!parentIdVal;
    registerForm.value = { 
        id: null, name: '', type: 'cash', 
        initial_balance_uzs: 0, initial_balance_usd: 0, initial_balance_eur: 0,
        currency: 'UZS', building_id: '', is_active: true,
        bank_name: '', account_number: '', mfo_code: '', balance: 0,
        parent_id: parentIdVal || '', creditor_name: '', total_amount: '', interest_rate: '',
        term_months: '', grace_period_months: '', start_date: '', end_date: '', monthly_payment: '', description: ''
    };
    showRegisterModal.value = true;
}

function openEditRegisterModal(reg) {
    isEditRegister.value = true;
    hasSchedule.value = !!(reg.total_amount || reg.interest_rate || reg.monthly_payment || reg.creditor_name);
    isSubKassaCreation.value = !!reg.parent_id;
    registerForm.value = { 
        ...reg, 
        building_id: reg.building_id || '',
        parent_id: reg.parent_id || '',
        creditor_name: reg.creditor_name || '',
        total_amount: reg.total_amount || '',
        interest_rate: reg.interest_rate || '',
        term_months: reg.term_months || '',
        grace_period_months: reg.grace_period_months || '',
        start_date: reg.start_date || '',
        end_date: reg.end_date || '',
        monthly_payment: reg.monthly_payment || '',
        description: reg.description || ''
    };
    showRegisterModal.value = true;
}

function submitRegister() {
    isProcessing.value = true;
    if (isEditRegister.value) {
        router.put(route('admin.finance.registers.update', registerForm.value.id), registerForm.value, { preserveScroll: true, preserveState: true,
            onSuccess: () => { isProcessing.value = false; showRegisterModal.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.finance.registers.store'), registerForm.value, { preserveScroll: true, preserveState: true,
            onSuccess: () => { isProcessing.value = false; showRegisterModal.value = false; },
            onError: () => isProcessing.value = false
        });
    }
}

function deleteRegister(id) {
    if (confirm("Ushbu kassani o'chirishni tasdiqlaysizmi?")) {
        router.delete(route('admin.finance.registers.destroy', id), {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.error) {
                    alert(errors.error);
                }
            }
        });
    }
}

function handleDeleteFromModal() {
    if (registerForm.value.id) {
        if (confirm("Ushbu kassani o'chirishni tasdiqlaysizmi?")) {
            router.delete(route('admin.finance.registers.destroy', registerForm.value.id), {
                preserveScroll: true,
                onSuccess: () => {
                    showRegisterModal.value = false;
                },
                onError: (errors) => {
                    if (errors.error) {
                        alert(errors.error);
                    }
                }
            });
        }
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
        router.put(route('admin.finance.counterparties.update', counterpartyForm.value.id), counterpartyForm.value, { preserveScroll: true, preserveState: true,
            onSuccess: () => { isProcessing.value = false; showCounterpartyModal.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.finance.counterparties.store'), counterpartyForm.value, { preserveScroll: true, preserveState: true,
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

function restoreCounterparty(id) {
    if (confirm("Ushbu kontragentni savatchadan qayta tiklamoqchimisiz?")) {
        isProcessing.value = true;
        router.post(route('admin.finance.counterparties.restore', id), {}, { preserveScroll: true, preserveState: true,
            onSuccess: () => isProcessing.value = false,
            onError: () => isProcessing.value = false
        });
    }
}

function forceDeleteCounterparty(id) {
    if (confirm("Haqiqatan ham bu kontragentni butunlay o'chirib tashlamoqchimisiz? Uni qaytarib bo'lmaydi!")) {
        isProcessing.value = true;
        router.delete(route('admin.finance.counterparties.force_destroy', id), { preserveScroll: true, preserveState: true,
            onSuccess: () => isProcessing.value = false,
            onError: () => isProcessing.value = false
        });
    }
}

const selectedActions = ref([]);
const actionSearchQuery = ref('');
const actionTypeFilter = ref('');
const actionUserFilter = ref('');
const actionDateFilter = ref('');

const filteredFinanceActions = computed(() => {
    let list = props.financeActions || [];

    if (actionTypeFilter.value) {
        list = list.filter(act => act.action === actionTypeFilter.value);
    }

    if (actionUserFilter.value) {
        list = list.filter(act => act.user_id == actionUserFilter.value);
    }

    if (actionDateFilter.value) {
        list = list.filter(act => {
            const d = new Date(act.created_at);
            const actDate = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            return actDate === actionDateFilter.value;
        });
    }

    if (actionSearchQuery.value) {
        const q = actionSearchQuery.value.toLowerCase();
        list = list.filter(act => 
            (act.user?.name || '').toLowerCase().includes(q) ||
            String(act.transaction_id || '').toLowerCase().includes(q) ||
            (act.notes || '').toLowerCase().includes(q) ||
            (act.action || '').toLowerCase().includes(q)
        );
    }

    return list;
});

const isAllSelected = computed(() => {
    const list = filteredFinanceActions.value;
    return list.length > 0 && selectedActions.value.length === list.length;
});

function toggleSelectAll() {
    if (isAllSelected.value) {
        selectedActions.value = [];
    } else {
        selectedActions.value = filteredFinanceActions.value.map(act => act.id);
    }
}

function bulkDestroyActions() {
    if (selectedActions.value.length === 0) return;
    if (confirm(`Haqiqatan ham tanlangan ${selectedActions.value.length} ta harakatlar jurnali yozuvini butunlay o'chirib tashlamoqchimisiz?`)) {
        isProcessing.value = true;
        router.post(route('admin.finance.actions.bulk_destroy'), { preserveScroll: true, preserveState: true,
            ids: selectedActions.value
        }, {
            onSuccess: () => {
                selectedActions.value = [];
                isProcessing.value = false;
            },
            onError: () => isProcessing.value = false
        });
    }
}

function exportActionsToCsv() {
    const headers = ['No', 'Sana', 'Foydalanuvchi', 'Amal turi', 'Tranzaksiya ID', 'Summa', 'Izoh'];
    const rows = filteredFinanceActions.value.map((act, idx) => {
        const actionLabel = act.action === 'create' ? 'Qo\'shildi' : 
                            (act.action === 'update' ? 'Tahrirlandi' : 
                            (act.action === 'delete' ? 'O\'chirildi' : 
                            (act.action === 'restore' ? 'Qayta tiklandi' : 'Butunlay o\'chirildi')));
        const amountLabel = act.amount > 0 ? (act.transaction_type === 'income' ? '+' : '-') + ' ' + act.amount : '-';
        return [
            idx + 1,
            new Date(act.created_at).toLocaleString(),
            act.user?.name || '-',
            actionLabel,
            act.transaction_id || '-',
            amountLabel,
            act.notes || ''
        ];
    });

    const csvContent = [
        headers.join(','),
        ...rows.map(e => e.map(val => `"${String(val).replace(/"/g, '""')}"`).join(','))
    ].join('\n');

    const blob = new Blob(["\ufeff" + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", `harakatlar_jurnali_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
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
        router.put(route('admin.finance.invoices.update', invoiceForm.value.id), invoiceForm.value, { preserveScroll: true, preserveState: true,
            onSuccess: () => { isProcessing.value = false; showInvoiceModal.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.finance.invoices.store'), invoiceForm.value, { preserveScroll: true, preserveState: true,
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

const uniqueBuildings = computed(() => {
    if (!props.buildings) return [];
    const seenIds = new Set();
    const seenNames = new Set();
    return props.buildings.filter(b => {
        const nameLower = b.name.trim().toLowerCase();
        if (seenIds.has(b.id) || seenNames.has(nameLower)) {
            return false;
        }
        seenIds.add(b.id);
        seenNames.add(nameLower);
        return true;
    });
});

const clientContracts = computed(() => {
    if (!form.value.client_id || !props.contracts) return [];
    let baseContracts = props.contracts.filter(c => c.client_id === form.value.client_id);
    if (selectedCategoryName.value === 'mijozga pulni qaytarish' || selectedCategoryName.value === 'mijozga to\'lov qilish' || selectedCategoryName.value === 'client_refund') {
        return baseContracts.filter(c => c.status === 'cancelled');
    } else {
        return baseContracts.filter(c => c.status !== 'cancelled');
    }
});

const contractSchedules = computed(() => {
    if (!form.value.contract_id || !props.contracts) return [];
    const contract = props.contracts.find(c => c.id === form.value.contract_id);
    if (!contract) return [];
    const schedules = contract.payment_schedules || contract.paymentSchedules || [];
    return schedules.filter(s => s.status !== 'paid');
});

const selectedRegisterHasBuilding = computed(() => {
    if (!form.value.account_id || !props.registers) return false;
    const reg = props.registers.find(r => r.id === form.value.account_id);
    return !!(reg && reg.building_id);
});

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
const transactionsExcelExportUrl = computed(() => {
    const params = {};
    if (jurnalDateFrom.value) params.start_date = jurnalDateFrom.value;
    if (jurnalDateTo.value) params.end_date = jurnalDateTo.value;
    if (jurnalAccountId.value) params.account_id = jurnalAccountId.value;
    if (jurnalTypeFilter.value) params.type = jurnalTypeFilter.value;
    if (jurnalCounterpartyId.value) params.counterparty_id = jurnalCounterpartyId.value;
    if (jurnalBuildingId.value) params.building_id = jurnalBuildingId.value;
    if (jurnalSearchQuery.value) params.search = jurnalSearchQuery.value;
    return route('admin.finance.transactions.export.xlsx', params);
});

const isNewCategory = ref(false);
const selectedCategoryName = computed(() => {
    if (isNewCategory.value) return form.value.category_name?.toLowerCase() || '';
    if (!props.categories) return '';
    const cat = props.categories.find(c => c.id === form.value.category_id);
    return cat ? cat.name.toLowerCase() : '';
});

const selectedCategory = computed(() => {
    if (!props.categories) return null;
    return props.categories.find(c => c.id === form.value.category_id) || null;
});

const requiresBrigade = computed(() => selectedCategory.value?.target_type === 'brigade');
const requiresMachineOwner = computed(() => selectedCategory.value?.target_type === 'machine_owner');
const requiresWorker = computed(() => selectedCategory.value?.target_type === 'worker');
const isGuardSelected = computed(() => {
    if (!form.value.worker_id) return false;
    const w = props.workers?.find(x => x.id === form.value.worker_id);
    return w && w.role === 'guard';
});
const requiresMachinery = computed(() => selectedCategory.value?.target_type === 'machine_owner');
const requiresSupplier = computed(() => selectedCategory.value?.target_type === 'supplier');
const requiresBuilding = computed(() => {
    return selectedCategory.value?.target_type === 'supplier' || selectedCategoryName.value.includes('obyekt');
});
const requiresClient = computed(() => {
    return selectedCategory.value?.target_type === 'client' || clientPaymentType.value === 'initial';
});
const requiresPartner = computed(() => selectedCategory.value?.target_type === 'partner');
const requiresFixedAsset = computed(() => selectedCategory.value?.target_type === 'fixed_asset');

// Edit Transaction specific computed properties
const editSelectedCategory = computed(() => {
    if (!props.categories) return null;
    return props.categories.find(c => c.id === editTransactionForm.value.category_id) || null;
});
const editRequiresSupplier = computed(() => editSelectedCategory.value?.target_type === 'supplier');
const editRequiresPartner = computed(() => editSelectedCategory.value?.target_type === 'partner');
const editRequiresWorker = computed(() => editSelectedCategory.value?.target_type === 'worker');
const editRequiresBrigade = computed(() => editSelectedCategory.value?.target_type === 'brigade');
const editRequiresMachineOwner = computed(() => editSelectedCategory.value?.target_type === 'machine_owner');
const editRequiresClient = computed(() => editSelectedCategory.value?.target_type === 'client');
const editRequiresBuilding = computed(() => {
    return editSelectedCategory.value?.target_type === 'supplier' || 
           ((editSelectedCategory.value?.name || '').toLowerCase().includes('obyekt'));
});

const uniqueBuildingNames = computed(() => {
    if (!props.buildings) return [];
    const names = [];
    const seen = new Set();
    props.buildings.forEach(b => {
        if (b.name && !seen.has(b.name)) {
            seen.add(b.name);
            names.push(b.name);
        }
    });
    return names;
});

const availableBlocks = computed(() => {
    if (!selectedBuildingName.value || !props.buildings) return [];
    return props.buildings
        .filter(b => b.name === selectedBuildingName.value)
        .map(b => ({
            id: b.id,
            name: b.block_name || b.corpus_name || 'Asosiy blok'
        }));
});

const handleBuildingNameChange = () => {
    selectedBlockId.value = '';
    form.value.building_id = '';
    selectedUnitId.value = '';
    form.value.contract_id = '';
    form.value.counterparty_id = '';
    clientSearchQuery.value = '';
    
    // Auto-select the first block since selecting the object name is enough
    if (availableBlocks.value.length > 0) {
        selectedBlockId.value = availableBlocks.value[0].id;
        handleBlockIdChange();
    }
};

const handleBlockIdChange = () => {
    form.value.building_id = selectedBlockId.value;
    selectedUnitId.value = '';
    form.value.contract_id = '';
    form.value.counterparty_id = '';
    clientSearchQuery.value = '';
};

// Client search and payment type selector state
const availableUnits = computed(() => {
    if (!props.contracts) return [];
    let list = props.contracts;

    // Filter by building if selected
    if (form.value.building_id) {
        list = list.filter(c => {
            const bId = c.unit?.floor?.building?.id || c.unit?.floor?.building_id || c.unit?.building_id || c.building_id;
            return !bId || bId == form.value.building_id;
        });
    }

    // Filter by client if selected
    if (form.value.client_id) {
        list = list.filter(c => c.client_id == form.value.client_id);
    }

    // Map to unique units
    const units = [];
    const seenIds = new Set();
    list.forEach(c => {
        if (c.unit && !seenIds.has(c.unit.id)) {
            seenIds.add(c.unit.id);
            units.push({
                id: c.unit.id,
                unit_number: c.unit.unit_number || c.unit.number,
                building_name: c.unit.floor?.building?.name || c.building?.name || ''
            });
        }
    });

    return units.sort((a, b) => String(a.unit_number).localeCompare(String(b.unit_number), undefined, {numeric: true}));
});

const filteredClientsList = computed(() => {
    let list = props.counterparties?.filter(x => x.type === 'client') || [];

    // Include clients from props.clients if missing from counterparties
    if (props.clients && props.clients.length) {
        props.clients.forEach(cl => {
            const exists = list.some(x => x.customable_id == cl.id);
            if (!exists) {
                list.push({
                    id: 'client_' + cl.id,
                    customable_id: cl.id,
                    type: 'client',
                    name: (cl.first_name + ' ' + (cl.last_name || '') + ' ' + (cl.middle_name || '')).trim(),
                    phone: cl.phone || ''
                });
            }
        });
    }

    // Search query active -> filter by name or phone
    if (clientSearchQuery.value && clientSearchQuery.value.trim()) {
        const q = clientSearchQuery.value.toLowerCase().trim();
        const selected = list.find(x => x.id === form.value.counterparty_id);
        if (selected && selected.name.toLowerCase().trim() === q) return list;
        return list.filter(c => (c.name || '').toLowerCase().includes(q) || (c.phone || '').toLowerCase().includes(q));
    }

    // Filter by selected unit if set
    if (selectedUnitId.value && props.contracts) {
        const unitContract = props.contracts.find(c => c.unit_id == selectedUnitId.value);
        if (unitContract && unitContract.client_id) {
            const matched = list.filter(x => x.customable_id == unitContract.client_id);
            if (matched.length > 0) return matched;
        }
    }

    // Filter by building if set
    if (form.value.building_id && props.contracts) {
        const bClients = new Set();
        props.contracts.forEach(c => {
            const bId = c.unit?.floor?.building?.id || c.unit?.floor?.building_id || c.building_id;
            if (!bId || bId == form.value.building_id) {
                if (c.client_id) bClients.add(c.client_id);
            }
        });
        if (bClients.size > 0) {
            const filteredByBuilding = list.filter(x => bClients.has(x.customable_id));
            if (filteredByBuilding.length > 0) return filteredByBuilding;
        }
    }

    return list;
});

const closeClientDropdown = () => {
    setTimeout(() => {
        showClientDropdown.value = false;
    }, 200);
};

const selectClientForQuickTransaction = (c) => {
    form.value.counterparty_id = c.id;
    clientSearchQuery.value = c.name;
    showClientDropdown.value = false;
};

const selectedClientName = computed(() => {
    if (!form.value.counterparty_id) return '';
    const c = props.counterparties?.find(x => x.id === form.value.counterparty_id);
    return c ? c.name : '';
});

const handlePaymentTypeChange = () => {
    if (clientPaymentType.value === 'initial') {
        form.value.notes = "Boshlang'ich to'lov";
        form.value.payment_schedule_id = '';
        const existingCat = props.categories?.find(c => c.name.toLowerCase() === "boshlang'ich to'lov" || c.name.toLowerCase() === "boshlang'ich to’lov");
        if (existingCat) {
            isNewCategory.value = false;
            form.value.category_id = existingCat.id;
            form.value.category_name = existingCat.name;
        } else {
            // Find general "Mijoz To'lovi (Shartnoma)" as fallback category so category_id doesn't become empty!
            const fallbackCat = props.categories?.find(c => c.name.toLowerCase().includes("mijoz to'lovi") || c.name.toLowerCase().includes("mijoz to’lovi") || c.name.toLowerCase().includes("client"));
            if (fallbackCat) {
                isNewCategory.value = false;
                form.value.category_id = fallbackCat.id;
                form.value.category_name = fallbackCat.name;
            } else {
                isNewCategory.value = true;
                form.value.category_id = '';
                form.value.category_name = "Boshlang'ich to'lov";
            }
        }
    } else {
        form.value.notes = "Oylik to'lov";
        const existingCat = props.categories?.find(c => c.name.toLowerCase().includes("mijoz to'lovi") || c.name.toLowerCase().includes("mijoz to’lovi") || c.name.toLowerCase().includes("client"));
        if (existingCat) {
            isNewCategory.value = false;
            form.value.category_id = existingCat.id;
            form.value.category_name = existingCat.name;
        } else {
            isNewCategory.value = true;
            form.value.category_id = '';
            form.value.category_name = "Mijoz To'lovi (Shartnoma)";
        }
    }
};

watch(() => form.value.category_id, (newVal) => {
    if (newVal) {
        const cat = props.categories?.find(c => c.id === newVal);
        if (cat) {
            const name = cat.name.toLowerCase();
            if (name === "boshlang'ich to'lov" || name === "boshlang'ich to’lov") {
                clientPaymentType.value = 'initial';
                form.value.notes = "Boshlang'ich to'lov";
            } else if (name.includes("mijoz") || name.includes("client")) {
                // Keep current client payment type if already set to initial or monthly
                if (clientPaymentType.value !== 'initial' && clientPaymentType.value !== 'monthly') {
                    clientPaymentType.value = 'monthly';
                    form.value.notes = "Oylik to'lov";
                }
            } else {
                clientPaymentType.value = '';
            }
        }
    } else {
        clientPaymentType.value = '';
    }
});

const pendingRefunds = computed(() => {
    return sortedTransactions.value.filter(t => t.category_display === 'Kutayotgan Naqdlashtirish' && t.status === 'pending');
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
    
    if (requiresClient.value) {
        form.value.notes = clientPaymentType.value === 'initial' ? "Boshlang'ich to'lov" : "Oylik to'lov";
    }
    
    // Izohlar (extra notes) — alohida maydon, To'lov maqsadi bilan aralashtirilmaydi
    form.value.description = extraNotesInput.value || '';

    // Add selected invoices detail to notes and set primary invoice_id / payment_schedule_id
    if (selectedInvoiceIds.value.length > 0) {
        const firstId = selectedInvoiceIds.value[0];
        if (typeof firstId === 'string' && firstId.startsWith('sched_')) {
            form.value.payment_schedule_id = parseInt(firstId.replace('sched_', ''));
            form.value.invoice_id = null;
            
            const invoiceCodes = selectedInvoiceIds.value.map(id => {
                const schedId = parseInt(id.replace('sched_', ''));
                const s = contractSchedules.value.find(x => x.id === schedId);
                return s ? `To'lov Rejasi (Muddati: ${s.due_date ? new Date(s.due_date).toLocaleDateString() : 'Noma\'lum'})` : `#${id}`;
            });
            const invoiceNotes = `Bog'langan To'lov Rejalari: ${invoiceCodes.join(', ')}`;
            form.value.notes = form.value.notes ? `${form.value.notes} (${invoiceNotes})` : invoiceNotes;
        } else {
            form.value.invoice_id = firstId; // Set primary invoice ID
            form.value.payment_schedule_id = null;
            const invoiceCodes = selectedInvoiceIds.value.map(id => {
                const inv = props.invoices?.find(i => i.id === id);
                return inv ? inv.invoice_number : `#${id}`;
            });
            const invoiceNotes = `Fakturalar: ${invoiceCodes.join(', ')}`;
            form.value.notes = form.value.notes ? `${form.value.notes} (${invoiceNotes})` : invoiceNotes;
        }
    } else {
        // If no invoice selector item was selected, but we chose from the inline list directly:
        // payment_schedule_id is already set on form.value.payment_schedule_id. We set invoice_id to null.
        form.value.invoice_id = null;
    }

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
        const c = props.counterparties.find(x => x.id == form.value.counterparty_id);
        if (c && c.customable_type?.includes('User')) {
            form.value.target_type = 'user';
            form.value.target_id = c.customable_id;
        } else {
            form.value.target_type = 'worker';
            form.value.target_id = form.value.worker_id;
        }
    } else if (requiresSupplier.value) {
        form.value.target_type = 'supplier';
        form.value.target_id = form.value.supplier_id;
    } else if (requiresClient.value) {
        form.value.target_type = 'client';
        form.value.target_id = form.value.client_id;
    }

    router.post(route('admin.finance.transactions.store'), form.value, { preserveScroll: true, preserveState: true,
        forceFormData: true,
        onSuccess: () => {
            isProcessing.value = false;
            showQuickTransactionModal.value = false;
            form.value = { 
                type: 'income', category_id: '', category_name: '', amount: '', currency: 'UZS', payment_method: 'cash', 
                account_id: '', counterparty_id: '', invoice_id: '', contract_id: '', client_id: '', worker_id: '',
                brigade_id: '', partner_id: '', machinery_id: '', machine_owner_id: '', building_id: '', supplier_id: '', 
                fixed_asset_id: '', transaction_date: new Date().toLocaleString('sv').slice(0, 10), 
                attachment: null, auto_approve: true, notes: '', latitude: null, longitude: null, source: 'admin_panel', 
                target_type: null, target_id: null, exchange_rate: 1.0, converted_amount: '', payment_schedule_id: ''
            };
            selectedInvoiceIds.value = [];
            extraNotesInput.value = '';
            selectedUnitId.value = '';
            selectedBuildingName.value = '';
            selectedBlockId.value = '';
            documentNumberInput.value = '';
            clientSearchQuery.value = '';
            clientPaymentType.value = 'monthly';
            isEditingDocNumber.value = false;
            selectedParentRegisterId.value = '';
            selectedSubRegisterId.value = '';
        },
        onError: (errors) => {
            isProcessing.value = false;
            if (errors && Object.keys(errors).length > 0) {
                alert("Xatolik yuz berdi:\n" + Object.values(errors).flat().join("\n"));
            } else {
                alert("Tranzaksiyani saqlashda xatolik yuz berdi. Iltimos, barcha majburiy maydonlar to'ldirilganligini tekshiring.");
            }
        },
        onFinish: () => {
            isProcessing.value = false;
        }
    });
}

function submitTransfer() {
    isProcessing.value = true;
    transferError.value = '';
    
    if (!transferForm.value.from_account_id || !transferForm.value.to_account_id) {
        transferError.value = "Iltimos, chiqish va kirish kassalarini tanlang.";
        isProcessing.value = false;
        return;
    }
    if (transferForm.value.from_account_id == transferForm.value.to_account_id) {
        transferError.value = "Chiqish va kirish kassalari bir xil bo'lishi mumkin emas.";
        isProcessing.value = false;
        return;
    }
    if (!transferForm.value.sent_amount || parseFloat(transferForm.value.sent_amount) <= 0) {
        transferError.value = "Iltimos, to'g'ri o'tkazma summasini kiriting.";
        isProcessing.value = false;
        return;
    }

    if (isEditingTransfer.value) {
        router.put(route('admin.finance.internal_transfer.update', editingTransferId.value), transferForm.value, { 
            preserveScroll: true, 
            preserveState: true,
            onSuccess: () => {
                isProcessing.value = false;
                showQuickTransferModal.value = false;
                const today = new Date().toLocaleString('sv').slice(0, 10);
                transferForm.value = { from_account_id: '', to_account_id: '', sent_amount: '', exchange_rate: 1.0, transaction_date: today, notes: '', description: '' };
                isEditingTransfer.value = false;
                editingTransferId.value = null;
                transferError.value = '';
                activeTab.value = 6;
            },
            onError: (errs) => {
                isProcessing.value = false;
                const msg = (errs && (errs.error || errs.from_account_id || errs.to_account_id || errs.sent_amount || Object.values(errs).flat().join('\n'))) || "Xatolik yuz berdi";
                transferError.value = msg;
                alert("Xatolik: " + msg);
            }
        });
    } else {
        router.post(route('admin.finance.internal_transfer'), transferForm.value, { 
            preserveScroll: true, 
            preserveState: true,
            onSuccess: () => {
                isProcessing.value = false;
                showQuickTransferModal.value = false;
                const today = new Date().toLocaleString('sv').slice(0, 10);
                transferForm.value = { from_account_id: '', to_account_id: '', sent_amount: '', exchange_rate: 1.0, transaction_date: today, notes: '', description: '' };
                transferError.value = '';
                activeTab.value = 6;
            },
            onError: (errs) => {
                isProcessing.value = false;
                const msg = (errs && (errs.error || errs.from_account_id || errs.to_account_id || errs.sent_amount || Object.values(errs).flat().join('\n'))) || "Xatolik yuz berdi";
                transferError.value = msg;
                alert("Xatolik: " + msg);
            }
        });
    }
}

function openEditTransferModal(t) {
    if (!t) return;

    isEditingTransfer.value = true;
    editingTransferId.value = t.id;
    isTransferFromLocked.value = false;
    transferError.value = '';

    let transferTxDate = '';
    if (t.transaction_date) {
        transferTxDate = String(t.transaction_date).slice(0, 10);
    } else if (t.transactions && t.transactions.length > 0 && t.transactions[0].transaction_date) {
        transferTxDate = String(t.transactions[0].transaction_date).slice(0, 10);
    } else if (t.created_at) {
        transferTxDate = String(t.created_at).slice(0, 10);
    } else {
        transferTxDate = new Date().toLocaleString('sv').slice(0, 10);
    }

    let notesVal = t.notes || '';
    let descVal = t.description || '';

    if (!notesVal && descVal) {
        if (descVal.includes(" - ")) {
            const parts = descVal.split(" - ");
            notesVal = parts[0];
            descVal = parts.slice(1).join(" - ");
        } else {
            notesVal = descVal;
        }
    }

    transferForm.value = {
        from_account_id: t.from_account_id || t.fromAccount?.id || (t.from_account ? t.from_account.id : ''),
        to_account_id: t.to_account_id || t.toAccount?.id || (t.to_account ? t.to_account.id : ''),
        sent_amount: parseFloat(t.sent_amount || t.amount || 0),
        exchange_rate: parseFloat(t.exchange_rate || 1.0),
        transaction_date: transferTxDate,
        notes: notesVal,
        description: descVal
    };

    showQuickTransferModal.value = true;
}

function deleteTransferItem(id) {
    if (confirm("Ushbu pul o'tkazmasini o'chirishni tasdiqlaysizmi? Kassalar balanslari avtomatik qaytariladi.")) {
        isProcessing.value = true;
        router.delete(route('admin.finance.internal_transfer.destroy', id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isProcessing.value = false;
                activeTab.value = 6;
            },
            onError: () => isProcessing.value = false
        });
    }
}

function viewTransferDetails(t) {
    viewingTransfer.value = t;
    showViewTransferModal.value = true;
}

function openQuickTransaction(type) {
    form.value = { 
        type: type, category_id: '', category_name: '', amount: '', currency: selectedRegister.value?.currency || 'UZS', payment_method: 'cash', 
        account_id: selectedRegister.value?.id || '', counterparty_id: '', invoice_id: '', contract_id: '', client_id: '', worker_id: '',
        brigade_id: '', partner_id: '', machinery_id: '', machine_owner_id: '', building_id: selectedRegister.value?.building_id || '', supplier_id: '', 
        fixed_asset_id: '', transaction_date: new Date().toLocaleString('sv').slice(0, 10), 
        attachment: null, auto_approve: true, notes: '', latitude: gpsCoords.value.latitude, longitude: gpsCoords.value.longitude, source: 'admin_panel', 
        target_type: null, target_id: null, exchange_rate: 1.0, converted_amount: '', payment_schedule_id: ''
    };
    const activeRegister = selectedRegister.value;
    if (activeRegister) {
        if (activeRegister.parent_id) {
            selectedParentRegisterId.value = activeRegister.parent_id;
            selectedSubRegisterId.value = activeRegister.id;
            form.value.account_id = activeRegister.id;
        } else {
            selectedParentRegisterId.value = activeRegister.id;
            const children = (props.registers || []).filter(r => r.parent_id === activeRegister.id && r.is_active);
            if (children.length > 0) {
                selectedSubRegisterId.value = '';
                form.value.account_id = ''; // user must select sub-register
            } else {
                selectedSubRegisterId.value = '';
                form.value.account_id = activeRegister.id;
            }
        }
    } else {
        selectedParentRegisterId.value = '';
        selectedSubRegisterId.value = '';
        form.value.account_id = '';
    }

    if (form.value.building_id && props.buildings) {
        const b = props.buildings.find(x => x.id == form.value.building_id);
        if (b) {
            selectedBuildingName.value = b.name;
            selectedBlockId.value = b.id;
        } else {
            selectedBuildingName.value = '';
            selectedBlockId.value = '';
        }
    } else {
        selectedBuildingName.value = '';
        selectedBlockId.value = '';
    }

    selectedInvoiceIds.value = [];
    invoiceSearchQuery.value = '';
    invoiceCurrencyFilter.value = '';
    clientSearchQuery.value = '';
    clientPaymentType.value = 'monthly';
    selectedUnitId.value = '';
    documentNumberInput.value = String(nextDocNumber.value);
    isEditingDocNumber.value = false;
    extraNotesInput.value = '';
    showQuickTransactionModal.value = true;
}

function openQuickTransfer(fromRegister = null) {
    isEditingTransfer.value = false;
    editingTransferId.value = null;
    const today = new Date().toLocaleString('sv').slice(0, 10);
    const activeReg = fromRegister || (activeTab.value !== 6 ? selectedRegister.value : null);
    if (activeReg) {
        isTransferFromLocked.value = true;
        transferForm.value = { 
            from_account_id: activeReg.id, 
            to_account_id: '', 
            sent_amount: '', 
            exchange_rate: 1.0, 
            transaction_date: today,
            notes: '',
            description: '' 
        };
    } else {
        isTransferFromLocked.value = false;
        transferForm.value = { 
            from_account_id: '', 
            to_account_id: '', 
            sent_amount: '', 
            exchange_rate: 1.0, 
            transaction_date: today,
            notes: '',
            description: '' 
        };
    }
    showQuickTransferModal.value = true;
}

// Contract Approve Details
const showContractApproveModal = ref(false);
const selectedContractTxn = ref(null);
const contractApproveForm = ref({ applied_amount: 0, refund_amount: 0, account_id: '' });

watch(() => contractApproveForm.value.applied_amount, (newVal) => {
    if (selectedContractTxn.value) {
        const diff = Math.max(0, selectedContractTxn.value.amount - Number(newVal));
        if (contractApproveForm.value.refund_amount !== diff) {
            contractApproveForm.value.refund_amount = diff;
        }
    }
});

watch(() => contractApproveForm.value.refund_amount, (newVal) => {
    if (selectedContractTxn.value) {
        const diff = Math.max(0, selectedContractTxn.value.amount - Number(newVal));
        if (contractApproveForm.value.applied_amount !== diff) {
            contractApproveForm.value.applied_amount = diff;
        }
    }
});

const showApproveModal = ref(false);
const selectedApproveTxn = ref(null);
const approveForm = ref({ account_id: '' });

function approve(id) {
    const txn = (props.transactions || []).find(t => t.id === id);
    if (!txn) return;
    if (txn.contract_id && txn.type === 'income') {
        selectedContractTxn.value = txn;
        contractApproveForm.value.applied_amount = txn.amount;
        contractApproveForm.value.refund_amount = 0;
        contractApproveForm.value.account_id = txn.account_id || '';
        showContractApproveModal.value = true;
        return;
    }
    selectedApproveTxn.value = txn;
    approveForm.value.account_id = txn.account_id || '';
    showApproveModal.value = true;
}

function submitApprove() {
    isProcessing.value = true;
    router.post(route('admin.finance.transactions.approve', selectedApproveTxn.value.id), { preserveScroll: true, preserveState: true,
        account_id: approveForm.value.account_id
    }, {
        onSuccess: () => {
            isProcessing.value = false;
            showApproveModal.value = false;
        },
        onError: () => isProcessing.value = false
    });
}

function submitContractApprove() {
    isProcessing.value = true;
    router.post(route('admin.finance.transactions.approve', selectedContractTxn.value.id), { preserveScroll: true, preserveState: true,
        applied_amount: contractApproveForm.value.applied_amount,
        refund_amount: contractApproveForm.value.refund_amount,
        account_id: contractApproveForm.value.account_id
    }, {
        onSuccess: () => {
            isProcessing.value = false;
            showContractApproveModal.value = false;
        },
        onError: () => isProcessing.value = false
    });
}

function getRegisterBuildingName(accountId) {
    if (!accountId || !props.registers) return null;
    const reg = props.registers.find(r => r.id === accountId);
    return reg && reg.building ? reg.building.name : null;
}



function reject(id) {
    isProcessing.value = true;
    router.post(route('admin.finance.transactions.reject', id), {}, { preserveScroll: true, preserveState: true,
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
    term_months: 12, grace_period_months: '', start_date: new Date().toISOString().split('T')[0], 
    end_date: '', monthly_payment: '', description: '', status: 'active', fund_account: 'main'
});

function openNewLiabilityModal() {
    isEditMode.value = false;
    editingLiabilityId.value = null;
    liabilityForm.value = {
        type: 'bank_loan', creditor_name: '', total_amount: '', interest_rate: '', 
        term_months: 12, grace_period_months: '', start_date: new Date().toISOString().split('T')[0], 
        end_date: '', monthly_payment: '', description: '', status: 'active', fund_account: 'main'
    };
    showLiabilityModal.value = true;
}

function openEditLiability(liability) {
    isEditMode.value = true;
    editingLiabilityId.value = liability.id;
    liabilityForm.value = {
        type: liability.type, creditor_name: liability.creditor_name, total_amount: liability.total_amount,
        interest_rate: liability.interest_rate, term_months: liability.term_months,
        grace_period_months: liability.grace_period_months || '', start_date: liability.start_date,
        end_date: liability.end_date, monthly_payment: liability.monthly_payment, description: liability.description,
        status: liability.status, fund_account: ''
    };
    showLiabilityModal.value = true;
}

function submitLiability() {
    isProcessing.value = true;
    if (isEditMode.value) {
        router.put(route('admin.finance.liabilities.update', editingLiabilityId.value), liabilityForm.value, { preserveScroll: true, preserveState: true,
            onSuccess: () => { isProcessing.value = false; showLiabilityModal.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.finance.liabilities.store'), liabilityForm.value, { preserveScroll: true, preserveState: true,
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
    let gp = parseInt(liability.grace_period_months || 0);
    let m = parseFloat(liability.monthly_payment);
    
    if (p && n) {
        let balance = p;
        let r = (r_annual / 100) / 12;
        
        // remaining period after grace period
        let n_remaining = n - gp;
        let m_after = m;
        if (!m_after) {
            if (n_remaining > 0) {
                m_after = r > 0 ? (p * (r * Math.pow(1 + r, n_remaining)) / (Math.pow(1 + r, n_remaining) - 1)) : (p / n_remaining);
            } else {
                m_after = 0;
            }
        }
        
        let currentDate = new Date(liability.start_date);
        for (let i = 1; i <= n; i++) {
            let interest = balance * r;
            let principal = 0;
            let payment = 0;
            
            if (i <= gp) {
                // Grace period: only pay interest
                principal = 0;
                payment = interest;
            } else {
                // After grace period
                payment = m_after;
                principal = payment - interest;
                if (i === n) {
                    principal = balance;
                    payment = principal + interest;
                }
            }
            
            balance -= principal;
            currentDate.setMonth(currentDate.getMonth() + 1);
            generatedSchedule.value.push({
                month: i, date: currentDate.toISOString().split('T')[0],
                payment: payment, principal: principal, interest: interest, balance: Math.max(0, balance)
            });
        }
    }
    showScheduleModal.value = true;
}

function downloadScheduleExcel() {
    if (!selectedLiabilityForSchedule.value || generatedSchedule.value.length === 0) return;
    
    const title = selectedLiabilityForSchedule.value.name || selectedLiabilityForSchedule.value.creditor_name || selectedLiabilityForSchedule.value.creditor || 'Kredit';
    const csvContent = [];
    
    // Add UTF-8 header metadata
    csvContent.push(`To'lovlar grafigi - ${title}`);
    csvContent.push(`Jami summa:;${selectedLiabilityForSchedule.value.total_amount};UZS`);
    csvContent.push(`Muddati:;${selectedLiabilityForSchedule.value.term_months};oy`);
    csvContent.push(`Foiz stavkasi:;${selectedLiabilityForSchedule.value.interest_rate || 0};%`);
    csvContent.push(``); // Empty spacer
    
    // Header columns
    csvContent.push(`Oy;To'lov Sanasi;Oylik To'lov (UZS);Asosiy Qarz (UZS);Foiz To'lovi (UZS);Qoldiq Asosiy Qarz (UZS)`);
    
    // Schedule rows
    generatedSchedule.value.forEach(row => {
        csvContent.push(`${row.month};${row.date};${Math.round(row.payment)};${Math.round(row.principal)};${Math.round(row.interest)};${Math.round(row.balance)}`);
    });

    // Total row
    const totalPayment = generatedSchedule.value.reduce((sum, r) => sum + parseFloat(r.payment || 0), 0);
    const totalPrincipal = generatedSchedule.value.reduce((sum, r) => sum + parseFloat(r.principal || 0), 0);
    const totalInterest = generatedSchedule.value.reduce((sum, r) => sum + parseFloat(r.interest || 0), 0);
    csvContent.push(`JAMI;;${Math.round(totalPayment)};${Math.round(totalPrincipal)};${Math.round(totalInterest)};`);
    
    const csvString = csvContent.join('\n');
    const blob = new Blob(["\ufeff" + csvString], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", `tolov_grafigi_${title.toLowerCase().replace(/[^a-z0-9]/g, '_')}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function deleteLiability(id) {
    if (confirm("Rostdan ham ushbu qarzni o'chirmoqchimisiz? Bu amalni ortga qaytarib bo'lmaydi.")) {
        isProcessing.value = true;
        router.delete(route('admin.finance.liabilities.destroy', id), { preserveScroll: true, preserveState: true,
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
    router.post(route('admin.finance.liabilities.payments.store', selectedLiability.value.id), paymentForm.value, { preserveScroll: true, preserveState: true,
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
    router.post(route('admin.finance.suppliers.pay'), supplierPaymentForm.value, { preserveScroll: true, preserveState: true,
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
    editSupplierForm.put(route('admin.warehouse.suppliers.update', editSupplierForm.id), { preserveScroll: true, preserveState: true,
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
    if (!sortedTransactions.value) return [];
    const approvedTxns = sortedTransactions.value.filter(t => t.status === 'approved');
    const groups = {};
    approvedTxns.forEach(t => {
        const catName = t.category_display || 'Noma\'lum';
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
    let list = Object.values(groups).sort((a, b) => b.transactions.length - a.transactions.length);
    if (categorySearchQuery.value) {
        const q = categorySearchQuery.value.toLowerCase().trim();
        list = list.filter(c => c.name.toLowerCase().includes(q));
    }
    return list;
});

function selectScheduleAmount(s) {
    selectedInvoiceIds.value = [];
    if (form.value.payment_schedule_id === s.id) {
        form.value.payment_schedule_id = '';
        form.value.amount = '';
    } else {
        form.value.payment_schedule_id = s.id;
        const remaining = Number(s.expected_amount) - Number(s.paid_amount || 0);
        form.value.amount = remaining > 0 ? remaining : 0;
    }
}

// Format money values

function formatSpaces(val) {
    if (!val && val !== 0) return '';
    let str = val.toString().replace(/\s/g, '');
    let parts = str.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return parts.join('.');
}

function unformatSpaces(val) {
    if (!val) return '';
    return val.toString().replace(/\s/g, '');
}

function formatMoney(amount, currency = 'UZS') {
    const val = parseFloat(amount);
    if (isNaN(val)) return '0.00 ' + currency;
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val) + ' ' + currency;
}

function getCounterpartyStatus(c) {
    if (c.type === 'client') {
        const hasPendingTx = c.transactions && c.transactions.some(t => t.status === 'pending');
        if (hasPendingTx) {
            return { text: 'To\'lov kutilmoqda (Jarayonda)', class: 'bg-amber-50 text-amber-700 border border-amber-200' };
        }

        const hasPendingContract = c.customable?.contracts && c.customable.contracts.some(con => con.status === 'pending' || con.status === 'draft');
        if (hasPendingContract) {
            return { text: 'Shartnoma kutilmoqda', class: 'bg-indigo-50 text-indigo-700 border border-indigo-200' };
        }

        const hasRejectedTx = c.transactions && c.transactions.some(t => t.status === 'rejected');
        if (hasRejectedTx) {
            return { text: 'To\'lov rad etilgan', class: 'bg-rose-50 text-rose-700 border border-rose-200' };
        }

        const balance = parseFloat(c.balance || 0);
        if (balance < 0) {
            return { text: 'Qarzdor', class: 'bg-red-50 text-red-700 border border-red-200' };
        }
        if (balance > 0) {
            return { text: 'Kompaniya qarzdor (Ortiqcha to\'lov)', class: 'bg-emerald-50 text-emerald-700 border border-emerald-200' };
        }

        const hasActiveContract = c.customable?.contracts && c.customable.contracts.some(con => con.status === 'active' || con.status === 'completed');
        if (hasActiveContract) {
            return { text: 'Uy sotib olgan', class: 'bg-blue-50 text-blue-700 border border-blue-200' };
        }

        return { text: 'Yangi mijoz', class: 'bg-gray-50 text-gray-700 border border-gray-200' };
    } else {
        const balance = parseFloat(c.balance || 0);
        if (balance < 0) {
            return { text: 'Qarzdor', class: 'bg-red-50 text-red-700 border border-red-200' };
        }
        if (balance > 0) {
            return { text: 'Ortiqcha haq', class: 'bg-emerald-50 text-emerald-700 border border-emerald-200' };
        }
        return { text: c.is_active ? 'Faol' : 'Faol emas', class: c.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400' };
    }
}

// Categories Management (Tab 14)
const showCategoryModal = ref(false);
const isEditCategory = ref(false);
const categoryForm = useForm({
    id: null,
    name: '',
    type: 'income',
    target_type: '',
    is_active: true
});

const onlyActiveCategories = ref(true);
const filteredAllCategories = computed(() => {
    if (!props.allCategories) return [];
    if (onlyActiveCategories.value) {
        return props.allCategories.filter(c => c.is_active);
    }
    return props.allCategories;
});

function openAddCategoryModal() {
    isEditCategory.value = false;
    categoryForm.reset();
    showCategoryModal.value = true;
}

function openEditCategoryModal(category) {
    isEditCategory.value = true;
    categoryForm.id = category.id;
    categoryForm.name = category.name;
    categoryForm.type = category.type;
    categoryForm.target_type = category.target_type || '';
    categoryForm.is_active = category.is_active !== undefined ? category.is_active : true;
    showCategoryModal.value = true;
}

function submitCategory() {
    if (isEditCategory.value) {
        categoryForm.put(route('admin.finance.categories.update', categoryForm.id), { preserveScroll: true, preserveState: true,
            onSuccess: () => { showCategoryModal.value = false; categoryForm.reset(); }
        });
    } else {
        categoryForm.post(route('admin.finance.categories.store'), { preserveScroll: true, preserveState: true,
            onSuccess: () => { showCategoryModal.value = false; categoryForm.reset(); }
        });
    }
}

function deleteCategory(id) {
    if (confirm("Kategoriyani o'chirishni yoki passiv holatga o'tkazishni tasdiqlaysizmi?")) {
        router.delete(route('admin.finance.categories.destroy', id));
    }
}

</script>

<template>
    <Head :title="$t('finance_core')" />
    <component :is="CurrentLayout">
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
                                        <td class="p-3 truncate max-w-xs" :title="txn.notes + (txn.description ? '\n[Izoh]: ' + txn.description : '')">
                                             {{ txn.notes || '-' }}
                                             <div v-if="txn.description" class="text-[9px] text-gray-400 font-sans italic mt-0.5 truncate">{{ txn.description }}</div>
                                        </td>
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
                                        <td class="p-3 truncate max-w-xs" :title="txn.notes + (txn.description ? '\n[Izoh]: ' + txn.description : '')">
                                             {{ txn.notes || '-' }}
                                             <div v-if="txn.description" class="text-[9px] text-gray-400 font-sans italic mt-0.5 truncate">{{ txn.description }}</div>
                                        </td>
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
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-amber-500 animate-pulse"></span>
                            Tasdiqlash Kutilayotgan Tranzaksiyalar
                        </h2>
                        <div class="relative w-full sm:w-64">
                            <span class="absolute left-3 top-2.5 text-gray-400 text-xs">🔍</span>
                            <input v-model="pendingSearchQuery" type="text" placeholder="Qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl pl-8 pr-3 py-1.5 text-xs w-full focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>

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
                                <p><span class="text-gray-400">To'lov maqsadi:</span> {{ txn.notes || 'Kiritilmagan' }}</p>
                                <p v-if="txn.description"><span class="text-gray-400">Izoh:</span> {{ txn.description }}</p>
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
                    <div class="flex items-center gap-6">
                        <div>
                            <h2 class="text-xl font-bold font-display text-gray-900">Tranzaksiyalar</h2>
                            <p class="text-xs text-gray-500 font-mono mt-1">Jami {{ filteredJurnalTransactions.length }} ta tranzaksiya</p>
                        </div>
                        <button @click="openImportModal" class="flex items-center gap-2 px-3 py-2 border border-gray-250 hover:bg-gray-50 text-gray-700 rounded-xl font-bold transition-all text-xs font-mono shadow-sm shrink-0">
                            <span class="text-sm font-bold text-gray-700">Import</span>
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2zM12 5v6m0 0l-2-2m2 2l2-2"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="flex gap-3 w-full sm:w-auto">
                        <input v-model="jurnalSearchQuery" type="text" placeholder="Qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-4 py-2 text-sm w-full sm:w-64">
                        <a :href="transactionsExcelExportUrl" target="_blank" class="px-5 py-2.5 border border-emerald-500 text-emerald-600 hover:bg-emerald-50 rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-2">
                            <span>📊 Excel</span>
                        </a>
                        <button @click="openQuickTransaction('income')" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-2 shrink-0">
                            <span>+ Kirim</span>
                        </button>
                        <button @click="openQuickTransaction('expense')" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-2 shrink-0">
                            <span>- Chiqim</span>
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
                        <SearchableSelect 
                            v-model="jurnalCounterpartyId" 
                            :options="counterpartyFilterOptions" 
                            labelKey="name" 
                            valueKey="id" 
                            placeholder="Barchasi" 
                        />
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Bino / Obyekt</label>
                        <select v-model="jurnalBuildingId" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs">
                            <option value="">Barchasi</option>
                            <option v-for="b in uniqueBuildings" :key="b.id" :value="b.id">{{ b.name }}</option>
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
                                <tr v-for="t in paginatedJurnalTransactions" :key="t.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 font-bold text-gray-900">#{{ t.id }}</td>
                                    <td class="p-4 text-gray-500">{{ new Date(t.transaction_date).toLocaleDateString() }}</td>
                                    <td class="p-4 font-bold text-gray-800">{{ getTransactionSourceName(t) }}</td>
                                    <td class="p-4 text-gray-700">{{ t.building?.name || t.contract?.unit?.building?.name || '-' }}</td>
                                    <td class="p-4 text-right font-bold" :class="t.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                                        {{ t.type === 'income' ? '+' : '-' }} {{ formatMoney(t.amount, t.currency) }}
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border border-gray-200 bg-gray-50">
                                            {{ t.category_display || 'Mijoz to\'lovi' }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex justify-center gap-2">
                                            <!-- View -->
                                            <button @click="openReceipt(t)" class="p-1 text-gray-500 hover:text-blue-600 border border-gray-200 rounded-md hover:bg-blue-50 transition-all" title="Ko'rish">
                                                👁️
                                            </button>
                                            <!-- Edit -->
                                            <button v-if="hasPerm('edit_transaction')" @click="openEditTransactionModal(t)" class="p-1 text-gray-500 hover:text-amber-600 border border-gray-200 rounded-md hover:bg-amber-50 transition-all" title="Tahrirlash">
                                                ✏️
                                            </button>
                                            <button v-if="!hasPerm('edit_transaction') && t.status !== 'rejected'" @click="requestTransactionEdit(t, 'edit')" class="p-1 text-blue-500 hover:text-blue-700 border border-blue-200 rounded-md hover:bg-blue-50 transition-all" title="Adminga murojaat (Tahrirlash)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                            </button>
                                            <!-- Delete -->
                                            <button v-if="hasPerm('delete_transaction')" @click="deleteTransaction(t.id)" class="p-1 text-red-600 hover:text-white border border-red-200 hover:bg-red-600 rounded-md transition-all" title="O'chirish">
                                                🗑️
                                            </button>
                                            <button v-if="!hasPerm('delete_transaction') && t.status !== 'rejected'" @click="requestTransactionEdit(t, 'delete')" class="p-1 text-orange-500 hover:text-orange-700 border border-orange-200 rounded-md hover:bg-orange-50 transition-all" title="Adminga murojaat (O\'chirish)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
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
                    
                    <!-- Modern Pagination Controller -->
                    <div v-if="filteredJurnalTransactions.length > jurnalPerPage" class="flex flex-col sm:flex-row justify-between items-center gap-4 p-4 border-t border-slate-100 font-sans text-xs bg-slate-50/50">
                        <div class="text-slate-500 font-mono">
                            Jami <span class="font-bold text-slate-800">{{ filteredJurnalTransactions.length }}</span> tadan 
                            <span class="font-bold text-slate-800">{{ (jurnalCurrentPage - 1) * jurnalPerPage + 1 }}</span>-
                            <span class="font-bold text-slate-800">{{ Math.min(jurnalCurrentPage * jurnalPerPage, filteredJurnalTransactions.length) }}</span>-tranzaksiyalar ko'rsatilmoqda
                        </div>
                        <div class="flex items-center gap-1.5">
                            <!-- Prev Button -->
                            <button 
                                @click="jurnalCurrentPage = Math.max(1, jurnalCurrentPage - 1)" 
                                :disabled="jurnalCurrentPage === 1"
                                class="px-2.5 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 rounded-lg transition-colors font-bold disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                ◀ Oldingi
                            </button>
                            
                            <!-- Page numbers -->
                            <template v-for="p in visibleJurnalPages" :key="p">
                                <span v-if="p === '...'" class="text-slate-400 font-bold px-1.5 select-none">...</span>
                                <button 
                                    v-else
                                    @click="jurnalCurrentPage = p"
                                    class="w-7 h-7 rounded-lg font-bold transition-all border text-center flex items-center justify-center"
                                    :class="jurnalCurrentPage === p 
                                        ? 'bg-blue-600 border-blue-600 text-white shadow-sm' 
                                        : 'bg-white hover:bg-slate-50 border-slate-200 text-slate-600'"
                                >
                                    {{ p }}
                                </button>
                            </template>
                            
                            <!-- Next Button -->
                            <button 
                                @click="jurnalCurrentPage = Math.min(totalJurnalPages, jurnalCurrentPage + 1)" 
                                :disabled="jurnalCurrentPage === totalJurnalPages"
                                class="px-2.5 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 rounded-lg transition-colors font-bold disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Keyingi ▶
                            </button>
                        </div>
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
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-500 mb-1">HISOBNI TANLASH *</label>
                                <select v-model="selectedParentRegisterId" @change="handleParentRegisterChange" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-10" required>
                                    <option value="" disabled>Hisobni tanlang</option>
                                    <option v-for="r in parentRegisters" :key="r.id" :value="r.id">
                                        {{ r.name }} (Balans: {{ formatMoney(r.currency === 'USD' ? r.balance_usd : (r.currency === 'EUR' ? r.balance_eur : r.balance_uzs), r.currency) }})
                                    </option>
                                </select>
                            </div>

                            <!-- Sub-kassa (Optional/Conditional) -->
                            <div v-if="subRegisters.length > 0">
                                <label class="block text-gray-500 mb-1">SUB KASSA TANLASH *</label>
                                <select v-model="selectedSubRegisterId" @change="handleSubRegisterChange" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-10" required>
                                    <option value="" disabled>Sub kassa tanlang</option>
                                    <option v-for="r in subRegisters" :key="r.id" :value="r.id">
                                        {{ r.name }} (Balans: {{ formatMoney(r.currency === 'USD' ? r.balance_usd : (r.currency === 'EUR' ? r.balance_eur : r.balance_uzs), r.currency) }})
                                    </option>
                                </select>
                            </div>
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
                        <div v-if="showCounterpartyField">
                            <label class="block text-gray-500 mb-1">KONTRAGENT (KONKRET KATEGORIYA UCHUN) *</label>
                            <select v-model="form.counterparty_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-10" required>
                                <option value="">-- Tanlang --</option>
                                <option v-for="c in counterparties.filter(x => (requiresClient && x.type === 'client') || (requiresSupplier && x.type === 'supplier') || (requiresPartner && (x.type === 'partner' || x.type === 'shareholder')))" :key="c.id" :value="c.id">
                                    [{{ c.type.toUpperCase() }}] {{ c.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Amount input -->
                        <div>
                            <label class="block text-gray-500 mb-1">SUMMA *</label>
                            <input :value="formatSpaces(form.amount)" @input="form.amount = unformatSpaces($event.target.value)" type="text" inputmode="decimal" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-10 font-bold" required placeholder="0.00">
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
                            <label class="block text-gray-500 mb-1">SANA *</label>
                            <input v-model="form.transaction_date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-10" required>
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
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div>
                        <h2 class="text-xl font-bold font-display text-gray-900">Qarzlar va Kreditlar</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1 font-semibold">Kompaniyaning tashqi moliyaviy majburiyatlari.</p>
                    </div>
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <div class="relative w-full sm:w-64">
                            <span class="absolute left-3 top-2.5 text-gray-400 text-xs">🔍</span>
                            <input v-model="liabilitySearchQuery" type="text" placeholder="Kreditor yoki turni qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl pl-8 pr-3 py-1.5 text-xs w-full focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <button @click="openNewLiabilityModal" class="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm shrink-0">
                            + Yangi Majburiyat
                        </button>
                    </div>
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
                            <tr v-for="l in filteredFinancialLiabilities" :key="l.id" class="hover:bg-gray-50 transition-colors">
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
                            <tr v-if="!filteredFinancialLiabilities.length">
                                <td colspan="7" class="p-8 text-center text-gray-400">Majburiyatlar mavjud emas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 5: Ta'minotchilar (Suppliers) -->
            <div v-if="activeTab === 5" class="space-y-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div>
                        <h2 class="text-xl font-bold font-display text-gray-900">Ta'minotchilar Qoldiqlari</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1 font-semibold">Material yetkazib beruvchilar va ulardan bo'lgan hisob-kitoblar.</p>
                    </div>
                    <div class="relative w-full sm:w-64">
                        <span class="absolute left-3 top-2.5 text-gray-400 text-xs">🔍</span>
                        <input v-model="supplierSearchQuery" type="text" placeholder="Ta'minotchini qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl pl-8 pr-3 py-1.5 text-xs w-full focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>
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
                            <tr v-for="s in filteredSuppliers" :key="s.id" class="hover:bg-gray-50 transition-colors">
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
                            <tr v-if="!filteredSuppliers.length">
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
                        <button @click="showTransferFilter = !showTransferFilter" class="px-4 py-2 border border-gray-300 text-gray-600 rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-1.5" :class="showTransferFilter ? 'bg-orange-50 text-orange-600 border-orange-200' : ''">
                            🔍 Filtr
                        </button>
                        <button @click="openQuickTransfer()" class="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm shrink-0">
                            + Pul o'tkazmasi qo'shish
                        </button>
                    </div>
                </div>

                <!-- Transfer Filters row (toggled) -->
                <div v-if="showTransferFilter" class="bg-white border border-gray-150 p-4 rounded-2xl shadow-sm flex items-center gap-4 flex-wrap">
                    <div class="flex items-center gap-2 text-xs">
                        <input v-model="transferDateFrom" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs py-1 px-2">
                        <span class="text-gray-400">→</span>
                        <input v-model="transferDateTo" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs py-1 px-2">
                    </div>
                    <input v-model="transferSearch" type="text" placeholder="Qidirish (Kassa, izoh)..." class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-3 py-1.5 text-xs w-64">
                </div>



                <!-- Summaries Cards (Jami o'tkazmalar, Jami summa (USD), Faol kassalar) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Jami o'tkazmalar</span>
                        <h2 class="text-2xl font-bold font-display text-gray-900 mt-2">{{ totalTransfersCount }}</h2>
                    </div>
                    <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Jami summa</span>
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
                            </div>
                        </div>
                        <!-- Flow arrow details -->
                        <div class="flex items-center gap-3 px-4 py-1.5 bg-white border border-gray-150 rounded-lg shadow-sm">
                            <span class="font-bold text-gray-700">{{ t.from_account?.name || 'Naqd Kassa' }}</span>
                            <span class="text-orange-500 font-bold">→</span>
                            <span class="font-bold text-gray-700">{{ t.to_account?.name || 'Aloqa Bank Hisobi' }}</span>
                        </div>
                        <!-- Right Price, Date, and Actions -->
                        <div class="flex items-center gap-4 ml-auto">
                            <!-- View, Edit, Delete Actions -->
                            <div class="flex gap-2">
                                <button @click="viewTransferDetails(t)" class="p-1.5 text-gray-500 hover:text-blue-600 border border-gray-200 rounded-lg hover:bg-blue-50 bg-white shadow-sm transition-all" title="Ko'rish">
                                    👁️
                                </button>
                                <button v-if="hasPerm('edit_transaction')" @click="openEditTransferModal(t)" class="p-1.5 text-gray-500 hover:text-amber-600 border border-gray-200 rounded-lg hover:bg-amber-50 bg-white shadow-sm transition-all" title="Tahrirlash">
                                    ✏️
                                </button>
                                <button v-if="hasPerm('delete_transaction')" @click="deleteTransferItem(t.id)" class="p-1.5 text-red-600 hover:text-white border border-red-200 hover:bg-red-600 rounded-lg bg-white shadow-sm transition-all" title="O'chirish">
                                    🗑️
                                </button>
                            </div>
                            <!-- Price & Date details -->
                            <div class="text-right min-w-[120px]">
                                <span class="block text-emerald-600 font-bold text-lg">{{ formatMoney(t.sent_amount, t.sent_currency) }}</span>
                                <span class="text-xs text-gray-400 flex items-center gap-1 justify-end mt-1">
                                    📅 {{ new Date(t.created_at).toLocaleString() }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-if="!filteredTransfers.length" class="py-12 text-center text-gray-400">Pul o'tkazmalari topilmadi.</div>
                </div>
            </div>

            <!-- TAB 7: Kategoriya Hisoboti -->
            <div v-if="activeTab === 7" class="space-y-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div>
                        <h2 class="text-xl font-bold font-display text-gray-900">Hisobotlar (Kategoriya bo'yicha)</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1 font-semibold">Kirim va chiqimlar kategoriyalar tasnifida.</p>
                    </div>
                    <div class="relative w-full sm:w-64">
                        <span class="absolute left-3 top-2.5 text-gray-400 text-xs">🔍</span>
                        <input v-model="categorySearchQuery" type="text" placeholder="Kategoriyani qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl pl-8 pr-3 py-1.5 text-xs w-full focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>
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
                        <button @click="openNewRegisterModal()" class="w-8 h-8 rounded-lg bg-orange-500 hover:bg-orange-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                            +
                        </button>
                    </div>
                    <!-- Search bar -->
                    <input v-model="registerSearchQuery" type="text" placeholder="Qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-3 py-1.5 text-xs w-full">
                    
                    <!-- Registers Cards List -->
                    <div class="space-y-3 max-h-[600px] overflow-y-auto pr-1">
                        <div v-for="r in registers.filter(x => !x.parent_id && x.name.toLowerCase().includes(registerSearchQuery.toLowerCase()))" :key="r.id" @click="selectedRegisterId = r.id" class="p-4 rounded-2xl border cursor-pointer transition-all flex flex-col justify-between h-32 relative group" :class="selectedRegisterId === r.id ? 'bg-gradient-to-br from-orange-500 to-amber-500 text-white border-transparent shadow-lg' : 'bg-gray-50 border-gray-200 text-gray-900 hover:bg-gray-100'">
                            <div class="flex justify-between items-start">
                                <span class="text-sm font-bold font-display uppercase tracking-wide truncate max-w-[80%]">{{ r.name }}</span>
                                <!-- dot menu -->
                                <div class="relative">
                                    <button @click.stop="toggleRegisterMenu(r.id)" class="text-gray-400 group-hover:text-gray-700 p-1 rounded hover:bg-gray-200/20" :class="selectedRegisterId === r.id ? 'text-white/80 group-hover:text-white' : ''">
                                        ⋮
                                    </button>
                                    <!-- Dropdown Menu -->
                                    <div v-if="activeRegisterMenuId === r.id" class="absolute right-0 mt-1 w-32 bg-white border border-gray-200 rounded-xl shadow-lg z-50 p-1 text-gray-700 font-sans">
                                        <button @click.stop="triggerEdit(r)" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-100 rounded-lg flex items-center gap-2">
                                            ✏️ Tahrirlash
                                        </button>
                                        <button @click.stop="triggerDelete(r.id)" class="w-full text-left px-3 py-1.5 text-xs hover:bg-red-50 text-red-600 rounded-lg flex items-center gap-2">
                                            🗑️ O'chirish
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- Balances (UZS only) -->
                            <div class="font-mono text-xs mt-4 space-y-0.5">
                                <div class="flex justify-between">
                                    <span>Balans:</span>
                                    <span class="font-bold">{{ r.balance_uzs.toLocaleString() }} UZS</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right detailed register panel -->
                <div class="lg:col-span-9 space-y-6">
                    <div v-if="selectedRegister" class="space-y-6">
                        <!-- Details box for Parent Credit Register -->
                        <div v-if="selectedRegister.type === 'credit' && !selectedRegister.parent_id" class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm grid grid-cols-1 md:grid-cols-2 gap-6 font-mono text-xs">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between pb-1 border-b border-gray-100">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-purple-600 animate-pulse"></span>
                                        <span class="font-bold text-purple-700 uppercase tracking-wider text-[10px]">ASOSIY KREDIT GURUHI</span>
                                    </div>
                                    <button v-if="selectedRegister.total_amount" @click.stop="openScheduleModal(selectedRegister)" class="px-2.5 py-0.5 text-[10px] uppercase font-bold border border-blue-500 text-blue-600 bg-blue-50/30 rounded hover:bg-blue-600 hover:text-white transition-all font-sans">Grafik</button>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Guruh nomi:</span>
                                    <span class="font-bold text-gray-900 text-right">{{ selectedRegister.name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Tegishli bino:</span>
                                    <span class="font-bold text-gray-900 text-right">{{ selectedRegister.building?.name || 'Barcha binolar' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Holati:</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 uppercase border border-green-200">
                                        {{ selectedRegister.is_active ? 'Ish holatida' : 'Nofaol' }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Ichki kassalar soni:</span>
                                    <span class="font-bold text-purple-700 bg-purple-50 px-2 py-0.5 rounded border border-purple-200">
                                        {{ registers.filter(x => x.parent_id === selectedRegister.id).length }} ta sub-kassa
                                    </span>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center gap-2 pb-1 border-b border-gray-100">
                                    <span class="font-bold text-gray-500 uppercase tracking-wider text-[10px]">UMUMIY LOYIHA SUMMASI</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Umumiy Kredit:</span>
                                    <span class="font-bold text-gray-900 font-mono text-sm">{{ formatMoney(parentCreditTotalAmountUzs, 'UZS') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Details box for Sub-Register Credit -->
                        <div v-else-if="selectedRegister.type === 'credit' && selectedRegister.parent_id" class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm space-y-4 font-mono text-xs">
                            <div class="flex justify-between items-center border-b border-gray-100 pb-2.5">
                                <button @click="selectedRegisterId = selectedRegister.parent_id" class="text-xs font-bold text-orange-500 hover:text-orange-600 flex items-center gap-1 font-sans transition-all">
                                    ← Ortga qaytish: {{ getRegisterName(selectedRegister.parent_id) }}
                                </button>
                                <div class="flex items-center gap-2">
                                    <button v-if="selectedRegister.total_amount" @click.stop="openScheduleModal(selectedRegister)" class="px-2.5 py-0.5 text-[10px] uppercase font-bold border border-blue-500 text-blue-600 bg-blue-50/30 rounded hover:bg-blue-600 hover:text-white transition-all font-sans">Grafik</button>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200 uppercase">SUB-KASSA (KREDIT)</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Kassa nomi:</span>
                                        <span class="font-bold text-gray-900 text-right">{{ selectedRegister.name }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Kreditor (Bank/Tashkilot):</span>
                                        <span class="font-bold text-gray-900 text-right">{{ selectedRegister.creditor_name || '-' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Kredit summasi:</span>
                                        <span class="font-bold text-gray-900 text-right font-mono text-sm text-purple-700">{{ formatMoney(selectedRegister.total_amount, selectedRegister.currency) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Foiz stavkasi:</span>
                                        <span class="font-bold text-gray-900 text-right">{{ selectedRegister.interest_rate || 0 }}%</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Kredit muddati:</span>
                                        <span class="font-bold text-gray-900 text-right">{{ selectedRegister.term_months || 0 }} oy</span>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Oylik to'lov:</span>
                                        <span class="font-bold text-gray-900 text-right font-mono text-emerald-600">{{ formatMoney(selectedRegister.monthly_payment, selectedRegister.currency) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Kredit davri:</span>
                                        <span class="font-bold text-gray-900 text-right">{{ selectedRegister.start_date || '-' }} dan {{ selectedRegister.end_date || '-' }} gacha</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Bino (Obyekt):</span>
                                        <span class="font-bold text-gray-900 text-right">{{ selectedRegister.building?.name || '-' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Izoh:</span>
                                        <span class="font-bold text-gray-900 text-right truncate max-w-[200px]" :title="selectedRegister.description">{{ selectedRegister.description || '-' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center pt-1">
                                        <span class="text-gray-400">Holati:</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 uppercase border border-green-200">
                                            {{ selectedRegister.is_active ? 'Ish holatida' : 'Nofaol' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Details box for Standard Registers -->
                        <div v-else class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm space-y-4 font-mono text-xs">
                            <!-- Back Button for standard sub-register -->
                            <div v-if="selectedRegister.parent_id" class="flex justify-between items-center border-b border-gray-100 pb-2.5">
                                <button @click="selectedRegisterId = selectedRegister.parent_id" class="text-xs font-bold text-orange-500 hover:text-orange-600 flex items-center gap-1 font-sans transition-all">
                                    ← Ortga qaytish: {{ getRegisterName(selectedRegister.parent_id) }}
                                </button>
                                <div class="flex items-center gap-2">
                                    <button v-if="selectedRegister.total_amount" @click.stop="openScheduleModal(selectedRegister)" class="px-2.5 py-0.5 text-[10px] uppercase font-bold border border-blue-500 text-blue-600 bg-blue-50/30 rounded hover:bg-blue-600 hover:text-white transition-all font-sans">Grafik</button>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-50 text-gray-500 border border-gray-200 uppercase">SUB-KASSA</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Hisob nomi:</span>
                                        <span class="font-bold text-gray-900 text-right">{{ selectedRegister.name }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Bank nomi:</span>
                                        <span class="font-bold text-gray-900 text-right">{{ selectedRegister.bank_name || '-' }}</span>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Bino:</span>
                                        <span class="font-bold text-gray-900 text-right">{{ selectedRegister.building?.name || '-' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-400">Holati:</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 uppercase border border-green-200">
                                            {{ selectedRegister.is_active ? 'Ish holatida' : 'Nofaol' }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center pt-1 border-t border-gray-100">
                                        <span class="text-gray-400">Joriy Balans:</span>
                                        <span class="font-bold text-sm font-mono" :class="parseFloat(selectedRegister.balance_uzs || 0) < 0 ? 'text-red-600' : 'text-emerald-600'">
                                            {{ formatMoney(selectedRegister.balance_uzs || 0, 'UZS') }}
                                        </span>
                                    </div>
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
                                    <span class="block text-emerald-600 font-bold text-lg">{{ formatMoney(selectedRegisterKirimUzs, 'UZS') }}</span>
                                </div>
                            </div>
                            <!-- Chiqim -->
                            <div class="bg-[#fdf3f3] border border-[#fbd4d4] p-6 rounded-2xl shadow-sm flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-red-500 text-white flex items-center justify-center font-bold text-sm">↓</div>
                                    <span class="text-sm font-bold text-red-800">Chiqim</span>
                                </div>
                                <div class="text-right font-mono">
                                    <span class="block text-red-600 font-bold text-lg">{{ formatMoney(selectedRegisterChiqimUzs, 'UZS') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Sub-registers list (always visible for root registers) -->
                        <div v-if="!selectedRegister.parent_id" class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm space-y-4">
                            <div class="flex justify-between items-center">
                                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Ichki kassalar (Sub-kassalar)</h3>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] font-bold">Jami: {{ registers.filter(x => x.parent_id === selectedRegister.id).length }} ta</span>
                                    <button @click.stop="openNewRegisterModal(selectedRegister.id)" class="px-3 py-1 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                                        + Ichki kassa qo'shish
                                    </button>
                                </div>
                            </div>
                            <div v-if="registers.filter(x => x.parent_id === selectedRegister.id).length > 0" class="overflow-x-auto border border-gray-100 rounded-xl">
                                <table class="w-full text-left text-xs font-mono">
                                    <thead class="bg-slate-50 text-gray-500 font-bold border-b border-gray-100 uppercase text-[10px]">
                                        <tr>
                                            <th class="p-3">Kassa nomi</th>
                                            <th class="p-3">Turi</th>
                                            <th class="p-3">Kreditor (Agar bor bo'lsa)</th>
                                            <th class="p-3 text-right">Kredit Summasi</th>
                                            <th class="p-3 text-right">Balans</th>
                                            <th class="p-3 text-center">Amallar</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="sub in registers.filter(x => x.parent_id === selectedRegister.id)" :key="sub.id" @click="selectedRegisterId = sub.id" class="hover:bg-slate-100/70 cursor-pointer transition-colors">
                                            <td class="p-3 font-bold text-gray-900">{{ sub.name }}</td>
                                            <td class="p-3 uppercase text-[9px]">
                                                <span class="px-1.5 py-0.5 rounded font-bold" :class="sub.type === 'credit' ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-slate-100 text-slate-700'">
                                                    {{ sub.type }}
                                                </span>
                                            </td>
                                            <td class="p-3 text-gray-500">{{ sub.creditor_name || '-' }}</td>
                                            <td class="p-3 text-right font-bold text-purple-700">
                                                {{ (sub.type === 'credit' || sub.total_amount) ? formatMoney(sub.total_amount, sub.currency) : '-' }}
                                            </td>
                                            <td class="p-3 text-right font-bold text-gray-900">{{ formatMoney(sub.balance_uzs, 'UZS') }}</td>
                                            <td class="p-3 text-center">
                                                <div class="flex justify-center gap-2">
                                                    <button v-if="sub.type === 'credit' || sub.total_amount" @click.stop="openScheduleModal(sub)" class="px-2.5 py-1 text-[9px] uppercase font-bold border border-blue-500 text-blue-600 bg-blue-50/30 rounded hover:bg-blue-600 hover:text-white transition-all">Grafik</button>
                                                    <button @click.stop="triggerEdit(sub)" class="px-2.5 py-1 text-[9px] uppercase font-bold border border-gray-300 text-gray-700 bg-gray-50 rounded hover:bg-gray-200 transition-all" title="Tahrirlash">✏️</button>
                                                    <button @click.stop="triggerDelete(sub.id)" class="px-2.5 py-1 text-[9px] uppercase font-bold border border-red-300 text-red-600 bg-red-50/30 rounded hover:bg-red-600 hover:text-white transition-all" title="O'chirish">🗑️</button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="text-center py-6 border border-dashed border-gray-200 rounded-xl bg-gray-50/30">
                                <p class="text-gray-400 text-xs mb-3">Ushbu kassada hozircha ichki kassalar yo'q.</p>
                                <button @click.stop="openNewRegisterModal(selectedRegister.id)" class="px-4 py-1.5 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                                    + Birinchi ichki kassani qo'shish
                                </button>
                            </div>
                        </div>

                        <!-- Action Buttons row (screenshot style) -->
                        <div class="flex gap-3">
                            <button @click="openQuickTransaction('income')" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-2">
                                <span>+ KIRIM</span>
                            </button>
                            <button @click="openQuickTransaction('expense')" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-2">
                                <span>+ CHIQIM</span>
                            </button>
                            <button @click="openQuickTransfer(selectedRegister)" class="px-5 py-2.5 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-2">
                                <span>+ O'TKAZMA</span>
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
                                        <th class="p-4 text-center">Amallar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="t in paginatedRegisterTransactions" :key="t.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="p-4 font-bold text-gray-900">#{{ t.id }}</td>
                                        <td class="p-4 text-gray-500">{{ new Date(t.transaction_date).toLocaleString() }}</td>
                                        <td class="p-4 font-bold text-gray-800">{{ getTransactionSourceName(t) }}</td>
                                        <td class="p-4 text-gray-600">{{ t.building?.name || t.contract?.unit?.building?.name || '-' }}</td>
                                        <td class="p-4 text-right font-bold" :class="t.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                                            {{ t.type === 'income' ? '+' : '-' }} {{ formatMoney(t.amount, t.currency) }}
                                        </td>
                                        <td class="p-4">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border border-gray-200 bg-gray-50">
                                                {{ t.category_display || 'Mijoz to\'lovi' }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button @click="openReceipt(t)" class="px-2.5 py-1 text-[11px] bg-orange-50 text-orange-600 border border-orange-200 rounded-lg font-bold hover:bg-orange-500 hover:text-white transition-all" title="Kvitansiya">
                                                    📥
                                                </button>
                                                <button @click="openEditTransactionModal(t)" class="px-2.5 py-1 text-[11px] bg-blue-50 text-blue-600 border border-blue-200 rounded-lg font-bold hover:bg-blue-500 hover:text-white transition-all" title="Tahrirlash">
                                                    ✏️
                                                </button>
                                                <button @click="deleteTransaction(t.id)" class="px-2.5 py-1 text-[11px] bg-red-50 text-red-600 border border-red-200 rounded-lg font-bold hover:bg-red-500 hover:text-white transition-all" title="O'chirish">
                                                    🗑️
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!filteredRegisterTransactions.length">
                                        <td colspan="7" class="p-8 text-center text-gray-400">Tranzaksiyalar topilmadi.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Controller for Kassalar Details -->
                        <div v-if="filteredRegisterTransactions.length > registerDetailsPerPage" class="flex flex-col sm:flex-row justify-between items-center gap-4 p-4 border border-t-0 border-gray-150 rounded-b-2xl font-sans text-xs bg-slate-50/50">
                            <div class="text-slate-500 font-mono">
                                Jami <span class="font-bold text-slate-800">{{ filteredRegisterTransactions.length }}</span> tadan 
                                <span class="font-bold text-slate-800">{{ (registerDetailsCurrentPage - 1) * registerDetailsPerPage + 1 }}</span>-
                                <span class="font-bold text-slate-800">{{ Math.min(registerDetailsCurrentPage * registerDetailsPerPage, filteredRegisterTransactions.length) }}</span>-tranzaksiyalar ko'rsatilmoqda
                            </div>
                            <div class="flex items-center gap-1.5">
                                <!-- Prev Button -->
                                <button 
                                    @click="registerDetailsCurrentPage = Math.max(1, registerDetailsCurrentPage - 1)" 
                                    :disabled="registerDetailsCurrentPage === 1"
                                    class="px-2.5 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 rounded-lg transition-colors font-bold disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    ◀ Oldingi
                                </button>
                                
                                <!-- Page numbers -->
                                <template v-for="p in visibleRegisterDetailsPages" :key="p">
                                    <span v-if="p === '...'" class="text-slate-400 font-bold px-1.5 select-none">...</span>
                                    <button 
                                        v-else
                                        @click="registerDetailsCurrentPage = p"
                                        class="w-7 h-7 rounded-lg font-bold transition-all border text-center flex items-center justify-center"
                                        :class="registerDetailsCurrentPage === p 
                                            ? 'bg-blue-600 border-blue-600 text-white shadow-sm' 
                                            : 'bg-white hover:bg-slate-50 border-slate-200 text-slate-600'"
                                    >
                                        {{ p }}
                                    </button>
                                </template>
                                
                                <!-- Next Button -->
                                <button 
                                    @click="registerDetailsCurrentPage = Math.min(totalRegisterDetailsPages, registerDetailsCurrentPage + 1)" 
                                    :disabled="registerDetailsCurrentPage === totalRegisterDetailsPages"
                                    class="px-2.5 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 rounded-lg transition-colors font-bold disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Keyingi ▶
                                </button>
                            </div>
                        </div>
                    </div>
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
                        <button @click="openQuickTransaction('income')" class="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold font-mono text-xs uppercase tracking-widest transition-all shadow-sm">
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
                                    <th class="p-4 text-center">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(t, idx) in filteredClientPayments" :key="t.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 text-gray-400 font-bold">{{ idx + 1 }}</td>
                                    <td class="p-4 font-bold text-gray-900">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center font-bold text-sm">
                                                {{ getTransactionSourceName(t).charAt(0) || 'M' }}
                                            </div>
                                            <span>{{ getTransactionSourceName(t) }}</span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-right font-bold text-red-600">{{ formatMoney(t.amount, t.currency) }}</td>
                                    <td class="p-4 text-gray-500">{{ new Date(t.transaction_date).toLocaleString() }}</td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border border-gray-200 bg-gray-50">
                                            {{ t.category_display || 'Mijoz to\'lovi' }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-700 font-bold">#{{ t.id }}</td>
                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button @click="openReceipt(t)" class="px-2.5 py-1 text-[11px] bg-orange-50 text-orange-600 border border-orange-200 rounded-lg font-bold hover:bg-orange-500 hover:text-white transition-all">
                                                📥 Yuklab olish
                                            </button>
                                            <button @click="openEditTransactionModal(t)" class="px-2.5 py-1 text-[11px] bg-blue-50 text-blue-600 border border-blue-200 rounded-lg font-bold hover:bg-blue-500 hover:text-white transition-all">
                                                ✏️ Tahrirlash
                                            </button>
                                            <button @click="deleteTransaction(t.id)" class="px-2.5 py-1 text-[11px] bg-red-50 text-red-600 border border-red-200 rounded-lg font-bold hover:bg-red-500 hover:text-white transition-all">
                                                🗑️ O'chirish
                                            </button>
                                        </div>
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
                                    <th class="p-4 text-center">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(c, idx) in filteredClientDebts" :key="c.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 text-gray-400 font-bold">{{ idx + 1 }}</td>
                                    <td class="p-4 font-bold text-gray-900">
                                        <div class="flex items-center gap-2">
                                            <span>{{ c.name }}</span>
                                            <span v-if="c.balance > 0" class="px-2 py-0.5 text-[10px] bg-emerald-50 text-emerald-600 border border-emerald-200 rounded font-bold uppercase tracking-wider">Mijozga qaytarish</span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-600">{{ c.phone || '-' }}</td>
                                    <td class="p-4 text-right font-bold" :class="c.balance > 0 ? 'text-emerald-600' : 'text-red-600'">
                                        {{ c.balance > 0 ? 'Qaytariladigan: ' : '' }}{{ formatMoney(c.debt_amount) }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button @click="openEditCounterpartyModal(c)" class="px-2.5 py-1 text-[11px] bg-blue-50 text-blue-600 border border-blue-200 rounded-lg font-bold hover:bg-blue-500 hover:text-white transition-all">
                                                ✏️ Tahrirlash
                                            </button>
                                            <button @click="deleteCounterparty(c.id)" class="px-2.5 py-1 text-[11px] bg-red-50 text-red-600 border border-red-200 rounded-lg font-bold hover:bg-red-500 hover:text-white transition-all">
                                                🗑️ O'chirish
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!filteredClientDebts.length">
                                    <td colspan="5" class="p-8 text-center text-gray-400">Qarzdorliklar topilmadi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 11: Moliya Hisobotlari (Reports Tab) -->
            <div v-if="activeTab === 11" class="space-y-6 animate-[fadeIn_0.4s_ease-out]">
                <!-- Header Card -->
                <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold font-display text-gray-900 flex items-center gap-2">
                            <span>📊</span> Moliyaviy Hisobotlar paneli
                        </h2>
                        <p class="text-xs text-gray-500 font-mono mt-1 font-semibold">Barcha bo'limlar hisob-kitoblarining umumiy va batafsil ko'rsatkichlari tahlili.</p>
                    </div>
                </div>

                <!-- Inner Sub-Tabs Menu -->
                <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                    <button @click="reportSubTab = 'overview'" class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-xl transition-all"
                        :class="reportSubTab === 'overview' ? 'bg-orange-500 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'">
                        Kassalar hisobotlari
                    </button>
                    <button @click="reportSubTab = 'kirim_chiqim'" class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-xl transition-all"
                        :class="reportSubTab === 'kirim_chiqim' ? 'bg-orange-500 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'">
                        Kirim/Chiqim hisoboti
                    </button>
                    <button @click="reportSubTab = 'buildings'" class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-xl transition-all"
                        :class="reportSubTab === 'buildings' ? 'bg-orange-500 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'">
                        Obyektlar
                    </button>
                    <button @click="reportSubTab = 'categories'" class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-xl transition-all"
                        :class="reportSubTab === 'categories' ? 'bg-orange-500 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'">
                        Kategoriyalar
                    </button>
                    <button @click="reportSubTab = 'accounts'" class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-xl transition-all"
                        :class="reportSubTab === 'accounts' ? 'bg-orange-500 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'">
                        Kassa & Hamkorlar
                    </button>
                    <button @click="reportSubTab = 'debts'" class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-xl transition-all"
                        :class="reportSubTab === 'debts' ? 'bg-orange-500 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'">
                        Qarzdorliklar
                    </button>
                    <button @click="reportSubTab = 'journal'" class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-xl transition-all"
                        :class="reportSubTab === 'journal' ? 'bg-orange-500 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'">
                        Batafsil Jurnal
                    </button>
                </div>

                <!-- SUB-TAB CONTENT blocks -->

                <!-- SUB-TAB: Kirim/Chiqim Hisobotlari -->
                <div v-if="reportSubTab === 'kirim_chiqim'" class="space-y-6">
                    <!-- Header Filters Card -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <button @click="reportSubTab = 'overview'" class="p-2 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-xl text-gray-600 transition-all" title="Orqaga">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <div>
                                <h2 class="text-xl font-bold font-display text-gray-900">Kirim/Chiqim hisobotlari</h2>
                                <p class="text-xs text-gray-500 font-semibold mt-1">Kassa operatsiyalari va moliyaviy hisobotlar</p>
                            </div>
                        </div>
                    </div>

                    <!-- Metrics cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Jami kirim -->
                        <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all flex justify-between items-center">
                            <div>
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Jami kirim</span>
                                <h2 class="text-2xl font-black text-emerald-600 mt-2 font-mono">
                                    + {{ formatMoneyKirimChiqim(kirimChiqimTotalIncome) }}
                                </h2>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-black text-lg shadow-sm">
                                ↑
                            </div>
                        </div>

                        <!-- Jami chiqim -->
                        <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all flex justify-between items-center">
                            <div>
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Jami chiqim</span>
                                <h2 class="text-2xl font-black text-red-600 mt-2 font-mono">
                                    - {{ formatMoneyKirimChiqim(kirimChiqimTotalExpense) }}
                                </h2>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center font-black text-lg shadow-sm">
                                ↓
                            </div>
                        </div>

                        <!-- Balans -->
                        <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all flex justify-between items-center">
                            <div>
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Balans</span>
                                <h2 class="text-2xl font-black text-blue-600 mt-2 font-mono">
                                    {{ formatMoneyKirimChiqim(kirimChiqimBalance) }}
                                </h2>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-black text-lg shadow-sm">
                                $
                            </div>
                        </div>
                    </div>

                    <!-- Inner tab menu & Unified Table Card -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-6">
                        <!-- Table Inner Tabs & Filters -->
                        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 border-b border-gray-100 pb-3">
                            <!-- Left: Table Inner Tabs (Barcha, Kirim, Chiqim) -->
                            <div class="flex gap-4">
                                <button @click="kirimChiqimTableTab = 'all'" class="text-sm font-bold pb-2 transition-all border-b-2"
                                    :class="kirimChiqimTableTab === 'all' ? 'border-amber-500 text-amber-600 font-bold' : 'border-transparent text-gray-400 hover:text-gray-600'">
                                    Barcha
                                </button>
                                <button @click="kirimChiqimTableTab = 'income'" class="text-sm font-bold pb-2 transition-all border-b-2"
                                    :class="kirimChiqimTableTab === 'income' ? 'border-amber-500 text-amber-600 font-bold' : 'border-transparent text-gray-400 hover:text-gray-600'">
                                    Kirim
                                </button>
                                <button @click="kirimChiqimTableTab = 'expense'" class="text-sm font-bold pb-2 transition-all border-b-2"
                                    :class="kirimChiqimTableTab === 'expense' ? 'border-amber-500 text-amber-600 font-bold' : 'border-transparent text-gray-400 hover:text-gray-600'">
                                    Chiqim
                                </button>
                            </div>

                            <!-- Right: Search, Date Range, Kassa filter, Excel Button -->
                            <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">
                                <!-- Search -->
                                <div class="relative w-full sm:w-48">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    </span>
                                    <input v-model="kirimChiqimSearch" type="text" placeholder="Qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-9 pr-4 py-1.5 text-xs w-full">
                                </div>

                                <!-- Date Range -->
                                <div class="flex items-center gap-1.5 w-full sm:w-auto">
                                    <input v-model="kirimChiqimDateFrom" type="date" placeholder="Sanadan" class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-2.5 py-1.5 text-xs w-full sm:w-32">
                                    <span class="text-gray-400 font-bold text-xs">→</span>
                                    <input v-model="kirimChiqimDateTo" type="date" placeholder="Sanagacha" class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-2.5 py-1.5 text-xs w-full sm:w-32">
                                </div>

                                <!-- Kassa Dropdown Filter -->
                                <div class="relative w-full sm:w-40">
                                    <select v-model="kirimChiqimRegisterId" class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-2 py-1.5 text-xs w-full">
                                        <option value="">Barcha kassalar</option>
                                        <option v-for="r in registers" :key="r.id" :value="r.id">{{ r.name }}</option>
                                    </select>
                                </div>

                                <!-- Excel Button -->
                                <button @click="exportKirimChiqimToExcel" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold font-mono uppercase tracking-wider transition-all shadow-sm flex items-center gap-1.5 justify-center w-full sm:w-auto">
                                    📊 Excel ga yuklash
                                </button>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto border border-gray-150 rounded-2xl">
                            <table class="w-full text-left text-xs font-sans">
                                <thead class="bg-gray-50 text-gray-500 uppercase text-[10px] border-b border-gray-200">
                                    <tr>
                                        <th class="p-3 w-12 text-center font-bold">№</th>
                                        <th class="p-3">Yaratilgan sana</th>
                                        <th class="p-3">Kassa</th>
                                        <th class="p-3">Turi</th>
                                        <th class="p-3 text-right">Summa</th>
                                        <th class="p-3">Izoh</th>
                                        <th class="p-3">Xodim</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(t, idx) in paginatedKirimChiqimTransactions" :key="t.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="p-3 text-gray-400 font-bold text-center">{{ idx + 1 + (kirimChiqimCurrentPage - 1) * kirimChiqimPerPage }}</td>
                                        <td class="p-3 text-gray-600">{{ formatDateTime(t.transaction_date || t.created_at) }}</td>
                                        <td class="p-3 font-bold text-gray-800">{{ t.cashRegister?.name || t.cash_register?.name || 'Asosiy' }}</td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border font-mono uppercase tracking-wider" 
                                                :class="t.type === 'income' ? 'bg-emerald-50 text-emerald-700 border-emerald-150' : 'bg-red-50 text-red-700 border-red-150'">
                                                {{ t.type === 'income' ? 'Kirim' : 'Chiqim' }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-right font-black font-mono" :class="t.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                                            {{ formatMoneyKirimChiqim(t.amount) }}
                                        </td>
                                        <td class="p-3 text-gray-700 max-w-xs truncate" :title="t.notes + (t.description ? '\n[Izoh]: ' + t.description : '')">
                                             {{ t.notes || '—' }}
                                             <div v-if="t.description" class="text-[10px] text-gray-400 font-sans italic mt-0.5 truncate">{{ t.description }}</div>
                                         </td>
                                        <td class="p-3 text-gray-600 font-mono">{{ t.creator?.phone || t.creator?.name || '—' }}</td>
                                    </tr>
                                    <tr v-if="!displayedKirimChiqimTransactions.length">
                                        <td colspan="7" class="p-8 text-center text-gray-400">Tranzaksiyalar topilmadi.</td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="displayedKirimChiqimTransactions.length" class="bg-gray-50 border-t border-gray-200 text-xs font-bold text-gray-700 font-mono">
                                    <tr>
                                        <td colspan="4" class="p-3 text-right">Jami:</td>
                                        <td class="p-3 text-right text-gray-900 whitespace-nowrap">
                                            <div v-if="kirimChiqimTableTab === 'all'">
                                                <div class="text-emerald-600 font-black">+{{ formatMoneyKirimChiqim(displayedKirimChiqimIncomeSum) }}</div>
                                                <div class="text-red-600 font-black">-{{ formatMoneyKirimChiqim(displayedKirimChiqimExpenseSum) }}</div>
                                                <div class="text-blue-600 border-t border-gray-300 mt-1 pt-1 font-black">Balans: {{ formatMoneyKirimChiqim(displayedKirimChiqimTotalSum) }}</div>
                                            </div>
                                            <div v-else-if="kirimChiqimTableTab === 'income'" class="text-emerald-600 font-black">
                                                +{{ formatMoneyKirimChiqim(displayedKirimChiqimIncomeSum) }}
                                            </div>
                                            <div v-else-if="kirimChiqimTableTab === 'expense'" class="text-red-600 font-black">
                                                -{{ formatMoneyKirimChiqim(displayedKirimChiqimExpenseSum) }}
                                            </div>
                                        </td>
                                        <td colspan="2" class="p-3"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Modern Pagination Controller for Kirim-Chiqim -->
                    <div v-if="displayedKirimChiqimTransactions.length > kirimChiqimPerPage" class="flex flex-col sm:flex-row justify-between items-center gap-4 p-4 border border-t-0 border-gray-150 rounded-b-2xl font-sans text-xs bg-slate-50/50">
                        <div class="text-slate-500 font-mono">
                            Jami <span class="font-bold text-slate-800">{{ displayedKirimChiqimTransactions.length }}</span> tadan 
                            <span class="font-bold text-slate-800">{{ (kirimChiqimCurrentPage - 1) * kirimChiqimPerPage + 1 }}</span>-
                            <span class="font-bold text-slate-800">{{ Math.min(kirimChiqimCurrentPage * kirimChiqimPerPage, displayedKirimChiqimTransactions.length) }}</span>-tranzaksiyalar ko'rsatilmoqda
                        </div>
                        <div class="flex items-center gap-1.5">
                            <!-- Prev Button -->
                            <button 
                                @click="kirimChiqimCurrentPage = Math.max(1, kirimChiqimCurrentPage - 1)" 
                                :disabled="kirimChiqimCurrentPage === 1"
                                class="px-2.5 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 rounded-lg transition-colors font-bold disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                ◀ Oldingi
                            </button>
                            
                            <!-- Page numbers -->
                            <template v-for="p in visibleKirimChiqimPages" :key="p">
                                <span v-if="p === '...'" class="text-slate-400 font-bold px-1.5 select-none">...</span>
                                <button 
                                    v-else
                                    @click="kirimChiqimCurrentPage = p"
                                    class="w-7 h-7 rounded-lg font-bold transition-all border text-center flex items-center justify-center"
                                    :class="kirimChiqimCurrentPage === p 
                                        ? 'bg-blue-600 border-blue-600 text-white shadow-sm' 
                                        : 'bg-white hover:bg-slate-50 border-slate-200 text-slate-600'"
                                >
                                    {{ p }}
                                </button>
                            </template>
                            
                            <!-- Next Button -->
                            <button 
                                @click="kirimChiqimCurrentPage = Math.min(totalKirimChiqimPages, kirimChiqimCurrentPage + 1)" 
                                :disabled="kirimChiqimCurrentPage === totalKirimChiqimPages"
                                class="px-2.5 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 rounded-lg transition-colors font-bold disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Keyingi ▶
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SUB-TAB CONTENT blocks -->

                <!-- SUB-TAB 1: Overview Dashboard -->
                <div v-if="reportSubTab === 'overview'" class="space-y-6">
                    
                    <!-- KASSA HISOBOTLARI (Image 1 Style) -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-6">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 font-mono border-b border-gray-100 pb-3 flex items-center gap-2">
                            <span>💵</span> KASSA HISOBOTLARI
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start">
                            <!-- Column 1: kirim -->
                            <div class="flex flex-col items-center space-y-4">
                                <div class="w-full border-2 border-emerald-500 bg-emerald-50/10 rounded-xl p-4 text-center shadow-sm">
                                    <span class="block text-xs font-bold uppercase tracking-wider text-emerald-800">Umumiy kirim</span>
                                    <span class="text-lg font-black text-emerald-600 mt-1 block font-mono">{{ formatMoney(kassaReports.totalIncome) }}</span>
                                </div>
                                <div class="text-emerald-500">
                                    <svg class="w-5 h-5 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                    </svg>
                                </div>
                                <div class="w-full border border-gray-200 rounded-xl p-4 space-y-3 bg-white shadow-sm font-sans text-xs">
                                    <div v-for="item in kassaReports.items" :key="item.id" class="flex justify-between items-center py-1 border-b border-gray-50 last:border-0">
                                        <span class="text-gray-600 font-semibold">{{ item.name }} kirimi</span>
                                        <span class="font-bold text-gray-900 font-mono">{{ formatMoney(item.income) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Column 2: chiqim -->
                            <div class="flex flex-col items-center space-y-4">
                                <div class="w-full border-2 border-red-500 bg-red-50/10 rounded-xl p-4 text-center shadow-sm">
                                    <span class="block text-xs font-bold uppercase tracking-wider text-red-800">Umumiy chiqim</span>
                                    <span class="text-lg font-black text-red-600 mt-1 block font-mono">{{ formatMoney(kassaReports.totalExpense) }}</span>
                                </div>
                                <div class="text-red-400">
                                    <svg class="w-5 h-5 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                    </svg>
                                </div>
                                <div class="w-full border border-gray-200 rounded-xl p-4 space-y-3 bg-white shadow-sm font-sans text-xs">
                                    <div v-for="item in kassaReports.items" :key="item.id" class="flex justify-between items-center py-1 border-b border-gray-50 last:border-0">
                                        <span class="text-gray-600 font-semibold">{{ item.name }} chiqimi</span>
                                        <span class="font-bold text-gray-900 font-mono">{{ formatMoney(item.expense) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Column 3: balans -->
                            <div class="flex flex-col items-center space-y-4">
                                <div class="w-full border-2 border-blue-500 bg-blue-50/10 rounded-xl p-4 text-center shadow-sm">
                                    <span class="block text-xs font-bold uppercase tracking-wider text-blue-800">Balans</span>
                                    <span class="text-lg font-black text-blue-600 mt-1 block font-mono">{{ formatMoney(kassaReports.totalBalance) }}</span>
                                </div>
                                <div class="text-blue-500">
                                    <svg class="w-5 h-5 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                    </svg>
                                </div>
                                <div class="w-full border border-gray-200 rounded-xl p-4 space-y-3 bg-white shadow-sm font-sans text-xs">
                                    <div v-for="item in kassaReports.items" :key="item.id" class="flex justify-between items-center py-1 border-b border-gray-50 last:border-0">
                                        <span class="text-gray-600 font-semibold">{{ item.name }} qoldig'i</span>
                                        <span class="font-bold text-gray-900 font-mono">{{ formatMoney(item.balance) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Incomes vs Expenses by Categories Summary -->
                        <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-4">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 font-mono">Eng ko'p ishlatilgan kategoriyalar</h3>
                            <div class="space-y-3">
                                <div v-for="cat in categoriesSummary.slice(0, 6)" :key="cat.name" class="space-y-1">
                                    <div class="flex justify-between text-xs font-mono text-gray-700">
                                        <span class="font-bold">{{ cat.name }}</span>
                                        <span>
                                            Kirim: <span class="text-emerald-600 font-bold">{{ formatMoney(cat.total_income) }}</span> / 
                                            Chiqim: <span class="text-red-600 font-bold">{{ formatMoney(cat.total_expense) }}</span>
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden flex">
                                        <div class="bg-emerald-500 h-full" :style="{ width: (cat.total_income / (cat.total_income + cat.total_expense || 1) * 100) + '%' }"></div>
                                        <div class="bg-red-500 h-full" :style="{ width: (cat.total_expense / (cat.total_income + cat.total_expense || 1) * 100) + '%' }"></div>
                                    </div>
                                </div>
                                <div v-if="!categoriesSummary.length" class="text-xs text-gray-400 text-center py-6">Kategoriyalar tahlili uchun tranzaksiyalar mavjud emas.</div>
                            </div>
                        </div>

                        <!-- Building Overview Stats -->
                        <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-4">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 font-mono">Obyektlar bo'yicha aylanma</h3>
                            <div class="overflow-x-auto border border-gray-100 rounded-xl">
                                <table class="w-full text-left text-xs font-mono">
                                    <thead class="bg-gray-50 text-gray-500 border-b border-gray-100">
                                        <tr>
                                            <th class="p-3">Bino</th>
                                            <th class="p-3 text-right">Kirim</th>
                                            <th class="p-3 text-right">Chiqim</th>
                                            <th class="p-3 text-right">Sof oqim</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="b in reportBuildingsSummary.slice(0, 5)" :key="b.id" class="hover:bg-gray-50">
                                            <td class="p-3 font-bold text-gray-900">{{ b.name }}</td>
                                            <td class="p-3 text-right text-emerald-600 font-bold">{{ formatMoney(b.incomes) }}</td>
                                            <td class="p-3 text-right text-red-600 font-bold">{{ formatMoney(b.expenses) }}</td>
                                            <td class="p-3 text-right font-bold" :class="b.netFlow >= 0 ? 'text-gray-900' : 'text-red-700'">
                                                {{ formatMoney(b.netFlow) }}
                                            </td>
                                        </tr>
                                        <tr v-if="!reportBuildingsSummary.length">
                                            <td colspan="4" class="p-6 text-center text-gray-400">Obyektlar topilmadi.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SUB-TAB 2: Buildings Detail Tab -->
                <div v-if="reportSubTab === 'buildings'" class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-gray-100 pb-3">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 font-mono">Obyektlar bo'yicha to'liq tafsilotlar</h3>
                        <div class="relative w-full sm:w-64">
                            <span class="absolute left-3 top-2.5 text-gray-400 text-xs">🔍</span>
                            <input v-model="reportBuildingsSearchQuery" type="text" placeholder="Obyektni qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl pl-8 pr-3 py-1.5 text-xs w-full focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                    </div>
                    <div class="overflow-x-auto border border-gray-150 rounded-2xl">
                        <table class="w-full text-left text-xs font-mono">
                            <thead class="bg-gray-50 text-gray-500 border-b border-gray-200">
                                <tr>
                                    <th class="p-4">Obyekt nomi</th>
                                    <th class="p-4 text-right">Kirimlar summasi</th>
                                    <th class="p-4 text-right">Xarajatlar summasi</th>
                                    <th class="p-4 text-right">Sof balans</th>
                                    <th class="p-4 text-right">Faktura qilingan</th>
                                    <th class="p-4 text-right">Faktura qarzdorligi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="b in reportBuildingsSummary" :key="b.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 font-bold text-gray-900">{{ b.name }}</td>
                                    <td class="p-4 text-right text-emerald-600 font-bold">{{ formatMoney(b.incomes) }}</td>
                                    <td class="p-4 text-right text-red-600 font-bold">{{ formatMoney(b.expenses) }}</td>
                                    <td class="p-4 text-right font-bold" :class="b.netFlow >= 0 ? 'text-gray-900' : 'text-red-700'">
                                        {{ formatMoney(b.netFlow) }}
                                    </td>
                                    <td class="p-4 text-right text-blue-600">{{ formatMoney(b.totalInvoiced) }}</td>
                                    <td class="p-4 text-right font-bold" :class="b.unpaidInvoiced > 0 ? 'text-red-600' : 'text-emerald-600'">
                                        {{ formatMoney(b.unpaidInvoiced) }}
                                    </td>
                                </tr>
                                <tr v-if="!reportBuildingsSummary.length">
                                    <td colspan="6" class="p-8 text-center text-gray-400">Binolar topilmadi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SUB-TAB 3: Categories Detail Tab -->
                <div v-if="reportSubTab === 'categories'" class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-gray-100 pb-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 font-mono">Barcha tranzaksiya kategoriyalari bo'yicha tahlil</h3>
                        <div class="relative w-full sm:w-64">
                            <span class="absolute left-3 top-2.5 text-gray-400 text-xs">🔍</span>
                            <input v-model="categorySearchQuery" type="text" placeholder="Kategoriyani qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl pl-8 pr-3 py-1.5 text-xs w-full focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Kirim toifalar -->
                        <div class="space-y-4 border border-gray-100 p-4 rounded-xl bg-white">
                            <h4 class="text-xs font-bold text-emerald-600 uppercase font-mono tracking-widest border-b pb-2">Kirim Kategoriyalari</h4>
                            <div class="space-y-3">
                                <template v-for="cat in categoriesSummary" :key="cat.name + '-income'">
                                    <div v-if="cat.total_income > 0" class="flex justify-between items-center text-xs font-mono">
                                        <span class="font-bold text-gray-800">{{ cat.name }}</span>
                                        <span class="text-emerald-600 font-bold">{{ formatMoney(cat.total_income) }}</span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Chiqim toifalar -->
                        <div class="space-y-4 border border-gray-100 p-4 rounded-xl bg-white">
                            <h4 class="text-xs font-bold text-red-600 uppercase font-mono tracking-widest border-b pb-2">Chiqim Kategoriyalari</h4>
                            <div class="space-y-3">
                                <template v-for="cat in categoriesSummary" :key="cat.name + '-expense'">
                                    <div v-if="cat.total_expense > 0" class="flex justify-between items-center text-xs font-mono">
                                        <span class="font-bold text-gray-800">{{ cat.name }}</span>
                                        <span class="text-red-600 font-bold">{{ formatMoney(cat.total_expense) }}</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SUB-TAB 4: Kassalar & Hamkorlar -->
                <div v-if="reportSubTab === 'accounts'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Kassalar accounts -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 font-mono">Kassalar va Hisob raqamlar</h3>
                        <div class="overflow-x-auto border border-gray-100 rounded-xl">
                            <table class="w-full text-left text-xs font-mono">
                                <thead class="bg-gray-50 text-gray-500 border-b border-gray-100">
                                    <tr>
                                        <th class="p-3">Kassa nomi</th>
                                        <th class="p-3">Turi</th>
                                        <th class="p-3 text-right">Balans</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="r in registers" :key="r.id" class="hover:bg-gray-50">
                                        <td class="p-3 font-bold text-gray-900">{{ r.name }}</td>
                                        <td class="p-3 uppercase text-[10px]">{{ r.type }}</td>
                                        <td class="p-3 text-right font-bold text-gray-900">{{ formatMoney(r.balance || r.balance_uzs || 0) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Hamkorlar (Partners shares) -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 font-mono">Ta'sischilar/Hamkorlar Hisobi</h3>
                        <div class="overflow-x-auto border border-gray-100 rounded-xl">
                            <table class="w-full text-left text-xs font-mono">
                                <thead class="bg-gray-50 text-gray-500 border-b border-gray-100">
                                    <tr>
                                        <th class="p-3">F.I.SH</th>
                                        <th class="p-3 text-right">Kiritgan sarmoya</th>
                                        <th class="p-3 text-right">Olingan pul</th>
                                        <th class="p-3 text-right">Joriy hisob</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="p in partners" :key="p.id" class="hover:bg-gray-50">
                                        <td class="p-3 font-bold text-gray-900">{{ p.user?.name || p.name }}</td>
                                        <td class="p-3 text-right text-emerald-600 font-bold">{{ formatMoney(p.invested_amount) }}</td>
                                        <td class="p-3 text-right text-red-600 font-bold">{{ formatMoney(p.withdrawn_amount) }}</td>
                                        <td class="p-3 text-right font-bold text-gray-900">{{ formatMoney(p.accountable_balance) }}</td>
                                    </tr>
                                    <tr v-if="!partners || !partners.length">
                                        <td colspan="4" class="p-6 text-center text-gray-400">Hamkorlar topilmadi.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- SUB-TAB 5: Debts (Detailed lists) -->
                <div v-if="reportSubTab === 'debts'" class="space-y-6">
                    <!-- QARZ KASSALAR (QARZ BERUVCHILAR) SECTION (3-rasm) -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-4">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-gray-100 pb-4">
                            <div>
                                <h3 class="text-sm font-bold uppercase tracking-wider text-purple-700 font-mono flex items-center gap-2">
                                    <span>💳</span> Qarz Kassalar (Qarz Beruvchilar Hisob-Kitobi)
                                </h3>
                                <p class="text-xs text-gray-500 font-mono mt-0.5">Olingan va qaytarilgan qarzlar bo'yicha umumiy nazorat jadvali</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="openTakeDebtModal" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition-all shadow-sm flex items-center gap-1.5 font-mono">
                                    + Qarz Olish
                                </button>
                                <button @click="openRepayDebtModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition-all shadow-sm flex items-center gap-1.5 font-mono">
                                    + Qarzni Qaytarish
                                </button>
                            </div>
                        </div>

                        <!-- Summary Table (3-rasm style) -->
                        <div class="overflow-x-auto border border-gray-200 rounded-xl">
                            <table class="w-full text-left text-xs font-mono border-collapse">
                                <thead class="bg-gray-50 text-gray-700 font-bold border-b border-gray-200 uppercase text-[11px]">
                                    <tr>
                                        <th class="p-3.5 border-r border-gray-200 w-10 text-center">№</th>
                                        <th class="p-3.5 border-r border-gray-200">Qarz beruvchi FISH</th>
                                        <th class="p-3.5 border-r border-gray-200">Bino / Obyekt</th>
                                        <th class="p-3.5 border-r border-gray-200 text-right">Olingan summa</th>
                                        <th class="p-3.5 border-r border-gray-200 text-right">Qaytarilgan summa</th>
                                        <th class="p-3.5 border-r border-gray-200 text-right">
                                            Qoldiq
                                            <span class="block text-[9px] font-normal text-gray-400 font-sans lowercase">(qarz qizil, ortiqcha yashil)</span>
                                        </th>
                                        <th class="p-3.5 border-r border-gray-200">Izoh</th>
                                        <th class="p-3.5 text-center">Amallar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 text-gray-800">
                                    <tr v-for="(q, idx) in debtRegistersSummary" :key="q.id" @click="openDebtLedgerModal(q)" class="hover:bg-purple-50/30 cursor-pointer transition-colors">
                                        <td class="p-3 border-r border-gray-200 text-center font-bold text-gray-500">{{ idx + 1 }}</td>
                                        <td class="p-3 border-r border-gray-200 font-bold text-gray-900">{{ q.name }}</td>
                                        <td class="p-3 border-r border-gray-200 text-gray-600">{{ q.buildingName }}</td>
                                        <td class="p-3 border-r border-gray-200 text-right font-bold text-gray-900">{{ formatMoney(q.takenSum) }}</td>
                                        <td class="p-3 border-r border-gray-200 text-right font-bold text-gray-900">{{ formatMoney(q.repaidSum) }}</td>
                                        <td class="p-3 border-r border-gray-200 text-right font-bold text-sm" :class="q.qoldiqRaw > 0 ? 'text-red-600' : (q.qoldiqRaw < 0 ? 'text-emerald-600' : 'text-gray-400')">
                                            <span v-if="q.qoldiqRaw > 0">- {{ formatMoney(q.qoldiqRaw) }}</span>
                                            <span v-else-if="q.qoldiqRaw < 0">{{ formatMoney(Math.abs(q.qoldiqRaw)) }}</span>
                                            <span v-else>-</span>
                                        </td>
                                        <td class="p-3 border-r border-gray-200 text-gray-500 text-[11px] truncate max-w-[200px]">{{ q.description || '-' }}</td>
                                        <td class="p-3 text-center" @click.stop>
                                            <div class="flex justify-center gap-1.5">
                                                <button @click="openDebtLedgerModal(q)" class="px-2 py-1 text-[10px] bg-purple-50 text-purple-700 border border-purple-200 rounded font-bold hover:bg-purple-600 hover:text-white transition-all">
                                                    📊 Hisob-kitob
                                                </button>
                                                <button @click="openRepayDebtModal(q)" class="px-2 py-1 text-[10px] bg-emerald-50 text-emerald-700 border border-emerald-200 rounded font-bold hover:bg-emerald-600 hover:text-white transition-all">
                                                    + Qaytarish
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!debtRegistersSummary.length">
                                        <td colspan="8" class="p-8 text-center text-gray-400">Qarz kassalar topilmadi. "+ Qarz Olish" tugmasi orqali yangi qarz kassa qo'shing.</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-100 font-bold border-t-2 border-gray-300 text-xs">
                                    <tr>
                                        <td colspan="3" class="p-3.5 text-right font-bold uppercase text-gray-900">Jami:</td>
                                        <td class="p-3.5 text-right text-gray-900 font-bold">{{ formatMoney(totalDebtTakenSum) }}</td>
                                        <td class="p-3.5 text-right text-gray-900 font-bold">{{ formatMoney(totalDebtRepaidSum) }}</td>
                                        <td class="p-3.5 text-right font-bold text-sm" :class="totalNetDebtQoldiq > 0 ? 'text-red-600' : (totalNetDebtQoldiq < 0 ? 'text-emerald-600' : 'text-gray-900')">
                                            <span v-if="totalNetDebtQoldiq > 0">- {{ formatMoney(totalNetDebtQoldiq) }}</span>
                                            <span v-else-if="totalNetDebtQoldiq < 0">{{ formatMoney(Math.abs(totalNetDebtQoldiq)) }}</span>
                                            <span v-else>-</span>
                                        </td>
                                        <td colspan="2" class="p-3.5"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Client Debts -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 font-mono">Mijozlarimizdan kelishi kerak bo'lgan to'lovlar (Debitorlik)</h3>
                        <div class="overflow-x-auto border border-gray-150 rounded-2xl">
                            <table class="w-full text-left text-xs font-mono">
                                <thead class="bg-gray-50 text-gray-500 border-b border-gray-200">
                                    <tr>
                                        <th class="p-4">Mijoz</th>
                                        <th class="p-4">Telefon</th>
                                        <th class="p-4 text-right">Qarzdorlik</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="c in filteredReportDebtors" :key="c.id" class="hover:bg-gray-50">
                                        <td class="p-4 font-bold text-gray-900">{{ c.name }}</td>
                                        <td class="p-4 text-gray-600">{{ c.phone || '-' }}</td>
                                        <td class="p-4 text-right font-bold text-red-600">{{ formatMoney(Math.abs(c.balance)) }}</td>
                                    </tr>
                                    <tr v-if="!filteredReportDebtors.length">
                                        <td colspan="3" class="p-4 text-center text-gray-400">Mijozlardan qarzdorlik yo'q.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Overpayments Table -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-emerald-650 font-mono">Mijozlarga qaytarilishi kerak bo'lgan ortiqcha to'lovlar (Kreditorlik)</h3>
                        <div class="overflow-x-auto border border-gray-150 rounded-2xl">
                            <table class="w-full text-left text-xs font-mono">
                                <thead class="bg-gray-50 text-emerald-700 border-b border-gray-200">
                                    <tr>
                                        <th class="p-4">Mijoz</th>
                                        <th class="p-4">Telefon</th>
                                        <th class="p-4 text-right">Ortiqcha To'lov</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="c in filteredReportCreditors" :key="c.id" class="hover:bg-emerald-50/20">
                                        <td class="p-4 font-bold text-slate-900">{{ c.name }}</td>
                                        <td class="p-4 text-gray-600">{{ c.phone || '-' }}</td>
                                        <td class="p-4 text-right font-bold text-emerald-600">{{ formatMoney(c.balance) }}</td>
                                    </tr>
                                    <tr v-if="!filteredReportCreditors.length">
                                        <td colspan="3" class="p-4 text-center text-gray-400">Ortiqcha to'lovlar yo'q.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Supplier & Worker Payables -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Suppliers balance -->
                        <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-4">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 font-mono">Ta'minotchilarga bo'lgan qarzlar</h3>
                            <div class="overflow-x-auto border border-gray-100 rounded-xl">
                                <table class="w-full text-left text-xs font-mono">
                                    <thead class="bg-gray-50 text-gray-500 border-b border-gray-100">
                                        <tr>
                                            <th class="p-3">Ta'minotchi</th>
                                            <th class="p-3 text-right">Qarz summasi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="s in filteredReportSupplierPayables" :key="s.id" class="hover:bg-gray-50">
                                            <td class="p-3 font-bold text-gray-900">{{ s.company_name }} ({{ s.name }})</td>
                                            <td class="p-3 text-right font-bold text-red-600">{{ formatMoney(s.balance) }}</td>
                                        </tr>
                                        <tr v-if="!filteredReportSupplierPayables.length">
                                            <td colspan="2" class="p-4 text-center text-gray-400">Qarzdorlik yo'q.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Workers wage balance -->
                        <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-4">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 font-mono">Ishchilarga to'lanishi kerak oyliklar</h3>
                            <div class="overflow-x-auto border border-gray-100 rounded-xl">
                                <table class="w-full text-left text-xs font-mono">
                                    <thead class="bg-gray-50 text-gray-500 border-b border-gray-100">
                                        <tr>
                                            <th class="p-3">Xodim</th>
                                            <th class="p-3 text-right">Oylik qarzi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="w in filteredReportEmployeePayables" :key="w.id" class="hover:bg-gray-50">
                                            <td class="p-3 font-bold text-gray-900">{{ w.name }} ({{ w.role || 'Xodim' }})</td>
                                            <td class="p-3 text-right font-bold text-red-600">{{ formatMoney(w.balance) }}</td>
                                        </tr>
                                        <tr v-if="!filteredReportEmployeePayables.length">
                                            <td colspan="2" class="p-4 text-center text-gray-400">Xodimlardan qarz yo'q.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SUB-TAB 6: Detailed Journal -->
                <div v-if="reportSubTab === 'journal'" class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm space-y-6">
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 border-b border-gray-100 pb-4">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-900 font-mono">Hisobot tranzaksiyalari jurnali</h3>
                        <button @click="exportReportToCsv" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold font-mono uppercase tracking-wider transition-all">
                            📥 CSV yuklab olish
                        </button>
                    </div>

                    <!-- On-page filters -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <!-- Search text -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-400 uppercase font-mono">Qidirish</label>
                            <input v-model="reportJournalSearch" type="text" placeholder="Izoh yoki kontragent..." class="w-full bg-white border border-gray-300 rounded-lg text-xs px-3 py-2 text-gray-900 focus:ring-1 focus:ring-orange-500 focus:border-orange-500">
                        </div>

                        <!-- Date From -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-400 uppercase font-mono">Sanadan</label>
                            <input v-model="reportJournalDateFrom" type="date" class="w-full bg-white border border-gray-300 rounded-lg text-xs px-3 py-2 text-gray-900 focus:ring-1 focus:ring-orange-500 focus:border-orange-500">
                        </div>

                        <!-- Date To -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-400 uppercase font-mono">Sanagacha</label>
                            <input v-model="reportJournalDateTo" type="date" class="w-full bg-white border border-gray-300 rounded-lg text-xs px-3 py-2 text-gray-900 focus:ring-1 focus:ring-orange-500 focus:border-orange-500">
                        </div>

                        <!-- Cashbox selection -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-400 uppercase font-mono">Kassa/Hisob</label>
                            <select v-model="reportJournalAccountId" class="w-full bg-white border border-gray-300 rounded-lg text-xs px-2 py-2 text-gray-900 focus:ring-1 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">Barchasi</option>
                                <option v-for="r in registers" :key="r.id" :value="r.id">{{ r.name }}</option>
                            </select>
                        </div>

                        <!-- Type selector -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-400 uppercase font-mono">Turi</label>
                            <select v-model="reportJournalType" class="w-full bg-white border border-gray-300 rounded-lg text-xs px-2 py-2 text-gray-900 focus:ring-1 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">Barchasi</option>
                                <option value="income">Kirim (Income)</option>
                                <option value="expense">Chiqim (Expense)</option>
                            </select>
                        </div>

                        <!-- Category selector -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-400 uppercase font-mono">Kategoriya</label>
                            <select v-model="reportJournalCategoryId" class="w-full bg-white border border-gray-300 rounded-lg text-xs px-2 py-2 text-gray-900 focus:ring-1 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">Barchasi</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Journal Table -->
                    <div class="overflow-x-auto border border-gray-150 rounded-2xl">
                        <table class="w-full text-left text-xs font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-[10px] border-b border-gray-200">
                                <tr>
                                    <th class="p-3">Sana</th>
                                    <th class="p-3">Kategoriya</th>
                                    <th class="p-3">Kassa/Hisob</th>
                                    <th class="p-3">Kontragent</th>
                                    <th class="p-3 text-right">Summa (UZS)</th>
                                    <th class="p-3">Turi</th>
                                    <th class="p-3">Izoh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="t in paginatedReportFilteredTransactions" :key="t.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-3 text-gray-500">{{ new Date(t.transaction_date).toLocaleDateString() }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border border-gray-200 bg-gray-50">
                                            {{ t.category_display || 'Kategoriyasiz' }}
                                        </span>
                                    </td>
                                    <td class="p-3 font-bold text-gray-900">{{ t.cashRegister?.name || t.cash_register?.name || 'Asosiy' }}</td>
                                    <td class="p-3 text-gray-700 font-semibold">{{ getTransactionSourceName(t) }}</td>
                                    <td class="p-3 text-right font-bold" :class="t.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                                        {{ formatMoney(t.amount, t.currency) }}
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold font-mono uppercase tracking-wider" :class="t.type === 'income' ? 'bg-emerald-50 text-emerald-700 border border-emerald-150' : 'bg-red-50 text-red-700 border border-red-150'">
                                            {{ t.type === 'income' ? 'Kirim' : 'Chiqim' }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-gray-500 truncate max-w-xs" :title="t.notes + (t.description ? '\n[Izoh]: ' + t.description : '')">
                                        {{ t.notes || '-' }}
                                        <div v-if="t.description" class="text-[9px] text-gray-400 font-sans italic mt-0.5 truncate">{{ t.description }}</div>
                                    </td>
                                </tr>
                                <tr v-if="!reportFilteredTransactions.length">
                                    <td colspan="7" class="p-8 text-center text-gray-400">Tranzaksiyalar topilmadi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Modern Pagination Controller for Tab 7 (Category Reports) -->
                    <div v-if="reportFilteredTransactions.length > reportJournalPerPage" class="flex flex-col sm:flex-row justify-between items-center gap-4 p-4 border border-t-0 border-gray-150 rounded-b-2xl font-sans text-xs bg-slate-50/50">
                        <div class="text-slate-500 font-mono">
                            Jami <span class="font-bold text-slate-800">{{ reportFilteredTransactions.length }}</span> tadan 
                            <span class="font-bold text-slate-800">{{ (reportJournalCurrentPage - 1) * reportJournalPerPage + 1 }}</span>-
                            <span class="font-bold text-slate-800">{{ Math.min(reportJournalCurrentPage * reportJournalPerPage, reportFilteredTransactions.length) }}</span>-tranzaksiyalar ko'rsatilmoqda
                        </div>
                        <div class="flex items-center gap-1.5">
                            <!-- Prev Button -->
                            <button 
                                @click="reportJournalCurrentPage = Math.max(1, reportJournalCurrentPage - 1)" 
                                :disabled="reportJournalCurrentPage === 1"
                                class="px-2.5 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 rounded-lg transition-colors font-bold disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                ◀ Oldingi
                            </button>
                            
                            <!-- Page numbers -->
                            <template v-for="p in visibleReportJournalPages" :key="p">
                                <span v-if="p === '...'" class="text-slate-400 font-bold px-1.5 select-none">...</span>
                                <button 
                                    v-else
                                    @click="reportJournalCurrentPage = p"
                                    class="w-7 h-7 rounded-lg font-bold transition-all border text-center flex items-center justify-center"
                                    :class="reportJournalCurrentPage === p 
                                        ? 'bg-blue-600 border-blue-600 text-white shadow-sm' 
                                        : 'bg-white hover:bg-slate-50 border-slate-200 text-slate-600'"
                                >
                                    {{ p }}
                                </button>
                            </template>
                            
                            <!-- Next Button -->
                            <button 
                                @click="reportJournalCurrentPage = Math.min(totalReportJournalPages, reportJournalCurrentPage + 1)" 
                                :disabled="reportJournalCurrentPage === totalReportJournalPages"
                                class="px-2.5 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 rounded-lg transition-colors font-bold disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Keyingi ▶
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 12: Botdan kiritilgan kirimlar -->
            <div v-if="activeTab === 12" class="space-y-6">
                <!-- Title & Actions Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div>
                        <h2 class="text-xl font-bold font-display text-gray-900">Botdan kiritilgan kirimlar</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1 font-semibold">Telegram Bot orqali kiritilgan barcha kirim va chiqim operatsiyalari ro'yxati</p>
                    </div>
                    <div class="relative w-full sm:w-64">
                        <span class="absolute left-3 top-2.5 text-gray-400 text-xs">🔍</span>
                        <input v-model="telegramSearchQuery" type="text" placeholder="Xodim, kategoriya yoki izohni qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl pl-8 pr-3 py-1.5 text-xs w-full focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>
                </div>

                <!-- Bot Incomes Table -->
                <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                                <tr>
                                    <th class="p-4">ID</th>
                                    <th class="p-4">Sana</th>
                                    <th class="p-4">Kassa / Hisob</th>
                                    <th class="p-4">Kategoriya</th>
                                    <th class="p-4 text-right">Summa</th>
                                    <th class="p-4">Kiritgan xodim</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4 text-center">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="t in filteredTelegramTransactions" :key="t.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 font-bold text-gray-900">#{{ t.id }}</td>
                                    <td class="p-4 text-gray-500">{{ new Date(t.transaction_date || t.created_at).toLocaleDateString() }}</td>
                                    <td class="p-4 text-gray-750 font-bold">{{ t.cashRegister?.name || t.cash_register?.name || 'Asosiy' }}</td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border border-gray-200 bg-gray-50">
                                            {{ t.category_display || 'Telegram Bot' }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right font-bold" :class="t.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                                        {{ t.type === 'income' ? '+' : '-' }} {{ formatMoney(t.amount, t.currency) }}
                                    </td>
                                    <td class="p-4 text-gray-700 font-semibold">{{ t.creator?.name || '-' }}</td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border" 
                                            :class="t.status === 'approved' ? 'bg-emerald-50 text-emerald-700 border-emerald-150' : (t.status === 'pending' ? 'bg-amber-50 text-amber-700 border-amber-150' : 'bg-red-50 text-red-700 border-red-150')">
                                            {{ t.status === 'approved' ? 'Tasdiqlangan' : (t.status === 'pending' ? 'Kutilmoqda' : 'Rad etilgan') }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex justify-center gap-2">
                                            <!-- View -->
                                            <button @click="openReceipt(t)" class="p-1 text-gray-500 hover:text-blue-600 border border-gray-200 rounded-md hover:bg-blue-50 transition-all" title="Ko'rish">
                                                👁️
                                            </button>
                                            <!-- Approve (if pending) -->
                                            <button v-if="t.status === 'pending' && isAdmin" @click="approve(t.id)" :disabled="isProcessing" class="px-2.5 py-1 text-[10px] font-bold uppercase bg-emerald-50 border border-emerald-500 text-emerald-600 hover:bg-emerald-600 hover:text-white transition-all rounded-lg" title="Tasdiqlash">
                                                Tasdiqlash
                                            </button>
                                            <!-- Reject (if pending) -->
                                            <button v-if="t.status === 'pending' && isAdmin" @click="reject(t.id)" :disabled="isProcessing" class="px-2.5 py-1 text-[10px] font-bold uppercase bg-red-50 border border-red-500 text-red-600 hover:bg-red-600 hover:text-white transition-all rounded-lg" title="Rad Etish">
                                                Rad Etish
                                            </button>
                                            <!-- Edit -->
                                            <button v-if="hasPerm('edit_transaction')" @click="openEditTransactionModal(t)" class="p-1 text-gray-500 hover:text-amber-600 border border-gray-200 rounded-md hover:bg-amber-50 transition-all" title="Tahrirlash">
                                                ✏️
                                            </button>
                                            <button v-if="!hasPerm('edit_transaction') && t.status !== 'rejected'" @click="requestTransactionEdit(t, 'edit')" class="p-1 text-blue-500 hover:text-blue-700 border border-blue-200 rounded-md hover:bg-blue-50 transition-all" title="Adminga murojaat (Tahrirlash)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                            </button>
                                            <!-- Delete -->
                                            <button v-if="hasPerm('delete_transaction')" @click="deleteTransaction(t.id)" class="p-1 text-red-600 hover:text-white border border-red-200 hover:bg-red-600 rounded-md transition-all" title="O'chirish">
                                                🗑️
                                            </button>
                                            <button v-if="!hasPerm('delete_transaction') && t.status !== 'rejected'" @click="requestTransactionEdit(t, 'delete')" class="p-1 text-orange-500 hover:text-orange-700 border border-orange-200 rounded-md hover:bg-orange-50 transition-all" title="Adminga murojaat (O\'chirish)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!filteredTelegramTransactions.length">
                                    <td colspan="8" class="p-8 text-center text-gray-400">Bot orqali kiritilgan tranzaksiyalar topilmadi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 13: Harakatlar va Savatcha -->
            <div v-if="activeTab === 13" class="space-y-6 animate-[fadeIn_0.4s_ease-out] min-h-[calc(100vh-180px)] flex flex-col">
                <!-- Title -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div>
                        <h2 class="text-xl font-bold font-display text-gray-900">Moliya Bo'limi Harakatlari</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1 font-semibold">Tranzaksiyalar bo'yicha amalga oshirilgan barcha harakatlar jurnali va o'chirilgan tranzaksiyalar savatchasi</p>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Total Actions Card -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-150 shadow-sm flex items-center justify-between hover:shadow-md transition-all">
                        <div>
                            <span class="text-xs text-gray-400 font-mono font-bold uppercase tracking-wider">Jami amallar</span>
                            <h4 class="text-2xl font-bold text-gray-900 mt-1 font-mono">{{ props.financeActions?.length || 0 }}</h4>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg font-bold">
                            📊
                        </div>
                    </div>

                    <!-- Created Card -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-150 shadow-sm flex items-center justify-between hover:shadow-md transition-all">
                        <div>
                            <span class="text-xs text-gray-400 font-mono font-bold uppercase tracking-wider">Kirim qilinganlar</span>
                            <h4 class="text-2xl font-bold text-emerald-600 mt-1 font-mono">
                                {{ (props.financeActions || []).filter(a => a.action === 'create').length }}
                            </h4>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                            ➕
                        </div>
                    </div>

                    <!-- Updated Card -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-150 shadow-sm flex items-center justify-between hover:shadow-md transition-all">
                        <div>
                            <span class="text-xs text-gray-400 font-mono font-bold uppercase tracking-wider">Tahrirlanganlar</span>
                            <h4 class="text-2xl font-bold text-blue-600 mt-1 font-mono">
                                {{ (props.financeActions || []).filter(a => a.action === 'update').length }}
                            </h4>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg font-bold">
                            📝
                        </div>
                    </div>

                    <!-- Deleted Card -->
                    <div class="bg-white p-5 rounded-2xl border border-gray-150 shadow-sm flex items-center justify-between hover:shadow-md transition-all">
                        <div>
                            <span class="text-xs text-gray-400 font-mono font-bold uppercase tracking-wider">O'chirilganlar</span>
                            <h4 class="text-2xl font-bold text-red-600 mt-1 font-mono">
                                {{ (props.financeActions || []).filter(a => a.action === 'delete').length }}
                            </h4>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-lg font-bold">
                            🗑️
                        </div>
                    </div>
                </div>

                <!-- Part 1: Action Logs Table -->
                <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-hidden p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900 font-display">Harakatlar Jurnali</h3>
                        <button v-if="selectedActions.length > 0" @click="bulkDestroyActions" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all flex items-center gap-1">
                            🗑️ Tanlanganlarni tozalash ({{ selectedActions.length }})
                        </button>
                    </div>

                    <!-- Filter & Search Controls -->
                    <div class="flex flex-col lg:flex-row gap-4 justify-between items-stretch lg:items-center bg-gray-50/50 p-4 rounded-xl border border-gray-150">
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 flex-1">
                            <!-- Search -->
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-gray-400 text-xs">🔍</span>
                                <input v-model="actionSearchQuery" type="text" placeholder="Izlash (ID, foydalanuvchi, izoh)..." class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl pl-8 pr-3 py-2 text-xs shadow-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            
                            <!-- Action Type Filter -->
                            <select v-model="actionTypeFilter" class="bg-white border border-gray-300 text-gray-900 rounded-xl px-3 py-2 text-xs shadow-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                <option value="">-- Amal turi (Barchasi) --</option>
                                <option value="create">Qo'shildi (Create)</option>
                                <option value="update">Tahrirlandi (Update)</option>
                                <option value="delete">O'chirildi (Delete)</option>
                                <option value="restore">Qayta tiklandi (Restore)</option>
                                <option value="permanent_delete">Butunlay o'chirildi</option>
                            </select>

                            <!-- User Filter -->
                            <select v-model="actionUserFilter" class="bg-white border border-gray-300 text-gray-900 rounded-xl px-3 py-2 text-xs shadow-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                <option value="">-- Foydalanuvchi (Barchasi) --</option>
                                <option v-for="u in props.users" :key="u.id" :value="u.id">
                                    {{ u.name }}
                                </option>
                            </select>

                            <!-- Date Filter -->
                            <input v-model="actionDateFilter" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-xl px-3 py-2 text-xs shadow-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500 font-mono">
                        </div>

                        <!-- Export Button -->
                        <div class="flex items-center gap-2">
                            <button @click="exportActionsToCsv" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all flex items-center gap-1.5 uppercase font-mono">
                                📥 CSV Yuklash
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto border border-gray-150 rounded-xl">
                        <table class="w-full text-left text-sm font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                                <tr>
                                    <th class="p-4 w-12 text-center">
                                        <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="rounded border-gray-300 text-amber-500 focus:ring-amber-500 bg-white">
                                    </th>
                                    <th class="p-4 w-12">№</th>
                                    <th class="p-4">Sana</th>
                                    <th class="p-4">Foydalanuvchi</th>
                                    <th class="p-4">Amal turi</th>
                                    <th class="p-4">Tranzaksiya ID</th>
                                    <th class="p-4 text-right">Summa</th>
                                    <th class="p-4">Izoh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(act, idx) in filteredFinanceActions" :key="act.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 text-center">
                                        <input type="checkbox" :value="act.id" v-model="selectedActions" class="rounded border-gray-300 text-amber-500 focus:ring-amber-500 bg-white">
                                    </td>
                                    <td class="p-4 text-gray-400 font-bold">{{ idx + 1 }}</td>
                                    <td class="p-4 text-gray-500">{{ new Date(act.created_at).toLocaleString() }}</td>
                                    <td class="p-4 font-bold text-gray-900">{{ act.user?.name || '-' }}</td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border" 
                                            :class="{
                                                'bg-emerald-50 text-emerald-700 border-emerald-150': act.action === 'create',
                                                'bg-blue-50 text-blue-700 border-blue-150': act.action === 'update',
                                                'bg-red-50 text-red-700 border-red-150': act.action === 'delete',
                                                'bg-indigo-50 text-indigo-700 border-indigo-150': act.action === 'restore',
                                                'bg-purple-50 text-purple-700 border-purple-150': act.action === 'permanent_delete'
                                            }">
                                            {{ act.action === 'create' ? 'Qo\'shildi' : 
                                               (act.action === 'update' ? 'Tahrirlandi' : 
                                               (act.action === 'delete' ? 'O\'chirildi' : 
                                               (act.action === 'restore' ? 'Qayta tiklandi' : 'Butunlay o\'chirildi'))) }}
                                        </span>
                                    </td>
                                    <td class="p-4 font-semibold text-gray-700">#{{ act.transaction_id || '-' }}</td>
                                    <td class="p-4 text-right font-bold text-gray-900">
                                        {{ act.amount > 0 ? (act.transaction_type === 'income' ? '+' : '-') + ' ' + formatMoney(act.amount, act.currency) : '-' }}
                                    </td>
                                    <td class="p-4 text-gray-500 max-w-xs truncate" :title="act.notes">{{ act.notes || '-' }}</td>
                                </tr>
                                <tr v-if="!filteredFinanceActions?.length">
                                    <td colspan="8" class="p-8 text-center text-gray-400">Harakatlar topilmadi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Part 2: Deleted Transactions (Savatcha) Table -->
                <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-hidden p-6 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <h3 class="text-lg font-bold text-gray-900 font-display text-red-600">O'chirilgan Tranzaksiyalar (Savatcha)</h3>
                        <div v-if="selectedDeletedTransactions.length > 0" class="flex flex-wrap items-center gap-2 animate-fade-in">
                            <span class="text-xs font-semibold text-gray-500 font-mono bg-gray-100 px-3 py-1.5 rounded-xl">
                                {{ selectedDeletedTransactions.length }} ta tanlandi
                            </span>
                            <button @click="bulkRestoreDeletedTransactions" class="px-3 py-1.5 bg-indigo-50 border border-indigo-200 text-indigo-600 hover:bg-indigo-600 hover:text-white font-bold text-xs rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                                🔄 Tanlanganlarni tiklash
                            </button>
                            <button @click="bulkForceDeleteTransactions" class="px-3 py-1.5 bg-red-50 border border-red-200 text-red-600 hover:bg-red-650 hover:text-white font-bold text-xs rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                                🗑️ Tanlanganlarni butunlay o'chirish
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto border border-gray-150 rounded-xl">
                        <table class="w-full text-left text-sm font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                                <tr>
                                    <th class="p-4 w-12 text-center">
                                        <input type="checkbox" :checked="isAllDeletedTransactionsSelected" @change="toggleAllDeletedTransactions" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 cursor-pointer" />
                                    </th>
                                    <th class="p-4 w-12">№</th>
                                    <th class="p-4">Ism / Kontragent</th>
                                    <th class="p-4 text-right">Summa</th>
                                    <th class="p-4">O'chirilgan sana</th>
                                    <th class="p-4">Kassa / Hisob</th>
                                    <th class="p-4">Kategoriya</th>
                                    <th class="p-4 text-center">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(t, idx) in props.deletedTransactions" :key="t.id" class="hover:bg-gray-50 transition-colors" :class="{'bg-blue-50/30': selectedDeletedTransactions.includes(t.id)}">
                                    <td class="p-4 text-center">
                                        <input type="checkbox" :value="t.id" v-model="selectedDeletedTransactions" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 cursor-pointer" />
                                    </td>
                                    <td class="p-4 text-gray-400 font-bold">{{ idx + 1 }}</td>
                                    <td class="p-4 font-bold text-gray-900">
                                        {{ t.counterparty?.name || '-' }}
                                    </td>
                                    <td class="p-4 text-right font-bold" :class="t.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                                        {{ t.type === 'income' ? '+' : '-' }} {{ formatMoney(t.amount, t.currency) }}
                                    </td>
                                    <td class="p-4 text-gray-500">{{ new Date(t.deleted_at).toLocaleString() }}</td>
                                    <td class="p-4 text-gray-750 font-bold">{{ t.cashRegister?.name || '-' }}</td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border border-gray-200 bg-gray-50">
                                            {{ t.category_display || 'Mijoz to\'lovi' }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button @click="restoreTransaction(t.id)" class="px-3 py-1 bg-indigo-50 border border-indigo-200 text-indigo-600 font-bold text-xs rounded-lg hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                                🔄 Ortga qaytarish
                                            </button>
                                            <button @click="forceDeleteTransaction(t.id)" class="px-3 py-1 bg-red-50 border border-red-200 text-red-600 font-bold text-xs rounded-lg hover:bg-red-600 hover:text-white transition-all shadow-sm">
                                                🗑️ Butunlay o'chirish
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.deletedTransactions?.length">
                                    <td colspan="8" class="p-8 text-center text-gray-400">Savatcha bo'sh. O'chirilgan tranzaksiyalar mavjud emas.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Part 3: Deleted Counterparties (Savatcha) Table -->
                <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-hidden p-6 space-y-4">
                    <h3 class="text-lg font-bold text-gray-900 font-display text-red-600">O'chirilgan Mijozlar va Kontragentlar (Savatcha)</h3>
                    <div class="overflow-x-auto border border-gray-150 rounded-xl">
                        <table class="w-full text-left text-sm font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                                <tr>
                                    <th class="p-4 w-12">№</th>
                                    <th class="p-4">Ism / Kontragent</th>
                                    <th class="p-4">Telefon</th>
                                    <th class="p-4 text-right">Balans / Qarz (UZS)</th>
                                    <th class="p-4">Turi</th>
                                    <th class="p-4 text-center">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(c, idx) in props.deletedCounterparties" :key="c.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 text-gray-400 font-bold">{{ idx + 1 }}</td>
                                    <td class="p-4 font-bold text-gray-900">{{ c.name }}</td>
                                    <td class="p-4 text-gray-600">{{ c.phone || '-' }}</td>
                                    <td class="p-4 text-right font-bold" :class="Number(c.balance || 0) < 0 ? 'text-red-600' : 'text-emerald-600'">
                                        {{ formatMoney(c.balance) }}
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border border-gray-200 bg-gray-50 uppercase">
                                            {{ c.type }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button @click="restoreCounterparty(c.id)" class="px-3 py-1 bg-indigo-50 border border-indigo-200 text-indigo-600 font-bold text-xs rounded-lg hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                                🔄 Ortga qaytarish
                                            </button>
                                            <button @click="forceDeleteCounterparty(c.id)" class="px-3 py-1 bg-red-50 border border-red-200 text-red-600 font-bold text-xs rounded-lg hover:bg-red-600 hover:text-white transition-all shadow-sm">
                                                🗑️ Butunlay o'chirish
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.deletedCounterparties?.length">
                                    <td colspan="6" class="p-8 text-center text-gray-400">Savatcha bo'sh. O'chirilgan kontragentlar mavjud emas.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 14: Kategoriyalar -->
            <div v-if="activeTab === 14" class="space-y-6 animate-[fadeIn_0.4s_ease-out]">
                <!-- Title -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div>
                        <h2 class="text-xl font-bold font-display text-gray-900">Kategoriyalar</h2>
                        <p class="text-xs text-gray-500 font-mono mt-1 font-semibold">Tranzaksiyalar uchun kategoriyalar va ularning modullarga bog'lanishi</p>
                    </div>
                    <button @click="openAddCategoryModal" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-xl shadow-sm transition-all flex items-center gap-2">
                        ➕ Kategoriya Qo'shish
                    </button>
                </div>

                <div class="bg-white border border-gray-150 rounded-2xl shadow-sm overflow-hidden p-6 space-y-4">
                    <div class="flex justify-between items-center bg-gray-50/50 p-3 rounded-xl border border-gray-100">
                        <div class="flex items-center gap-2">
                            <input v-model="onlyActiveCategories" type="checkbox" id="onlyActiveCats" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <label for="onlyActiveCats" class="text-xs font-semibold text-gray-700 cursor-pointer">Faqat faol kategoriyalarni ko'rsatish</label>
                        </div>
                    </div>
                    <div class="overflow-x-auto border border-gray-150 rounded-xl">
                        <table class="w-full text-left text-sm font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                                <tr>
                                    <th class="p-4 w-12">№</th>
                                    <th class="p-4">Nomi</th>
                                    <th class="p-4">Turi (Kirim/Chiqim)</th>
                                    <th class="p-4">Bog'lanish (Modul)</th>
                                    <th class="p-4">Holati</th>
                                    <th class="p-4 text-center">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(cat, idx) in filteredAllCategories" :key="cat.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4 text-gray-400 font-bold">{{ idx + 1 }}</td>
                                    <td class="p-4 font-bold text-gray-900">{{ cat.name }}</td>
                                    <td class="p-4 font-bold">
                                        <span v-if="cat.type === 'income'" class="text-emerald-600 bg-emerald-50 px-2 py-1 rounded">Kirim</span>
                                        <span v-else-if="cat.type === 'expense'" class="text-red-600 bg-red-50 px-2 py-1 rounded">Chiqim</span>
                                        <span v-else class="text-blue-600 bg-blue-50 px-2 py-1 rounded">Kirim va Chiqim</span>
                                    </td>
                                    <td class="p-4 text-gray-600">
                                        {{ cat.target_type === 'client' ? 'Xaridor (Mijoz)' :
                                           cat.target_type === 'supplier' ? 'Ta\'minotchi' :
                                           cat.target_type === 'worker' ? 'Xodim (Oylik/Avans)' :
                                           cat.target_type === 'partner' ? 'Ta\'sischi / Hamkor' :
                                           cat.target_type === 'brigade' ? 'Brigada' :
                                           cat.target_type === 'machine_owner' ? 'Texnika Egasi' :
                                           cat.target_type === 'fixed_asset' ? 'Asosiy Vosita' :
                                           'Umumiy (Bog\'lanmagan)' }}
                                    </td>
                                    <td class="p-4">
                                        <span v-if="cat.is_active" class="px-2 py-1 rounded text-xs font-bold bg-green-50 text-green-700">Faol</span>
                                        <span v-else class="px-2 py-1 rounded text-xs font-bold bg-gray-100 text-gray-500">Passiv</span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button @click="openEditCategoryModal(cat)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                                ✏️
                                            </button>
                                            <button @click="deleteCategory(cat.id)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                                🗑️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!filteredAllCategories.length">
                                    <td colspan="6" class="p-8 text-center text-gray-400">Kategoriyalar mavjud emas.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODALS & PORTALS -->

        <!-- CATEGORY ADD/EDIT MODAL -->
        <Modal :show="showCategoryModal" @close="showCategoryModal = false" maxWidth="md">
            <div class="p-6">
                <button @click="showCategoryModal = false" class="absolute top-4 right-4 text-gray-500 hover:text-gray-900">✕</button>
                <h3 class="text-lg font-bold text-gray-900 mb-6 uppercase tracking-wider font-display">
                    {{ isEditCategory ? 'Kategoriyani Tahrirlash' : 'Yangi Kategoriya Qo\'shish' }}
                </h3>
                
                <form @submit.prevent="submitCategory" class="space-y-4 font-mono text-sm">
                    <div>
                        <label class="block text-gray-700 font-bold mb-1">Nomi <span class="text-red-500">*</span></label>
                        <input v-model="categoryForm.name" type="text" class="w-full bg-white border border-gray-300 rounded-lg p-2.5" required>
                        <div v-if="categoryForm.errors.name" class="text-red-500 text-xs mt-1">{{ categoryForm.errors.name }}</div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-bold mb-1">Turi <span class="text-red-500">*</span></label>
                        <select v-model="categoryForm.type" class="w-full bg-white border border-gray-300 rounded-lg p-2.5" required>
                            <option value="income">Faqat Kirim</option>
                            <option value="expense">Faqat Chiqim</option>
                            <option value="both">Kirim va Chiqim</option>
                        </select>
                        <div v-if="categoryForm.errors.type" class="text-red-500 text-xs mt-1">{{ categoryForm.errors.type }}</div>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-bold mb-1">Modulga bog'lanish (Ixtiyoriy)</label>
                        <select v-model="categoryForm.target_type" class="w-full bg-white border border-gray-300 rounded-lg p-2.5">
                            <option value="">Umumiy (Bog'lanmagan)</option>
                            <option value="client">Xaridor (Mijozlar)</option>
                            <option value="supplier">Ta'minotchi (Ombor/Ta'minot)</option>
                            <option value="worker">Xodim (Oylik/Avans)</option>
                            <option value="partner">Ta'sischi (Hamkorlar/Investitsiya)</option>
                            <option value="brigade">Brigada (Pudratchilar)</option>
                            <option value="machine_owner">Texnika Egasi (Arenda)</option>
                            <option value="fixed_asset">Asosiy Vosita (Invertar)</option>
                        </select>
                        <div v-if="categoryForm.errors.target_type" class="text-red-500 text-xs mt-1">{{ categoryForm.errors.target_type }}</div>
                        <p class="text-[10px] text-gray-500 mt-1 leading-tight">Ushbu kategoriya orqali tranzaksiya amalga oshirilganda, ko'rsatilgan moduldan (masalan: Xodimlar) tanlash so'raladi.</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <label class="block text-gray-700 font-bold">Holati:</label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="categoryForm.is_active" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span>Faol</span>
                        </label>
                    </div>

                    <div class="pt-4 flex gap-3">
                        <button type="button" @click="showCategoryModal = false" class="w-1/3 py-3 border border-gray-300 rounded-xl text-gray-600 font-bold hover:bg-gray-50 transition-colors">
                            Bekor qilish
                        </button>
                        <button type="submit" :disabled="categoryForm.processing" class="w-2/3 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow transition-colors disabled:opacity-50">
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

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
        <Modal :show="showRegisterModal" @close="showRegisterModal = false" maxWidth="md" class="quick-transaction-modal-override">
            <div class="p-6">
                <button @click="showRegisterModal = false" class="absolute top-4 right-4 text-gray-500">✕</button>
                <h3 class="text-lg font-bold text-gray-900 mb-6 uppercase tracking-wider">
                    {{ isEditRegister ? 'Kassani tahrirlash' : 'Yangi kassa qo\'shish' }}
                </h3>
                
                <form @submit.prevent="submitRegister" class="space-y-4 font-mono text-xs">
                    <!-- Validation Errors -->
                    <div v-if="page.props.errors && Object.keys(page.props.errors).length > 0" class="p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-xs font-sans">
                        <ul class="list-disc pl-4 space-y-0.5 font-bold">
                            <li v-for="(err, field) in page.props.errors" :key="field">{{ err }}</li>
                        </ul>
                    </div>

                    <!-- Kassa nomi -->
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Kassa nomi *</label>
                        <input v-model="registerForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                    </div>
                    
                    <!-- Turi -->
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Turi *</label>
                        <select v-model="registerForm.type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                            <option value="cash">Naqd (Cash)</option>
                            <option value="bank">Bank hisobi</option>
                            <option value="credit">Kredit (Loan)</option>
                        </select>
                    </div>

                    <!-- Asosiy Kassa (Ota kassa) -->
                    <div v-if="isSubKassaCreation">
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Asosiy Kassa (Ota kassa)</label>
                        <select v-model="registerForm.parent_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                            <option value="">-- Mustaqil Kassa (Ota kassa yo'q) --</option>
                            <option v-for="r in registers.filter(x => x.id !== registerForm.id && !x.parent_id)" :key="r.id" :value="r.id">
                                {{ r.name }} ({{ r.type }})
                            </option>
                        </select>
                    </div>

                    <!-- To'lov grafigi mavjudligi (toggled for loan details) -->
                    <div class="flex items-center gap-2 mt-2 py-1">
                        <input v-model="hasSchedule" type="checkbox" id="register_has_schedule" class="rounded border-gray-300 text-orange-500 focus:ring-orange-500 w-4 h-4 cursor-pointer">
                        <label for="register_has_schedule" class="text-[10px] text-gray-700 font-bold uppercase cursor-pointer select-none">To'lov grafigiga ega (Kredit / Qarz)</label>
                    </div>

                    <!-- Kredit / Qarz Tafsilotlari (conditional) -->
                    <div v-if="isCreditType" class="border border-purple-150 p-4 rounded-xl bg-purple-50/20 space-y-4">
                        <span class="text-[10px] font-bold text-purple-600 uppercase tracking-widest block mb-2">Kredit / Qarz Tafsilotlari</span>
                        
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Kreditor (Bank/Tashkilot/Shaxs) *</label>
                            <input v-model="registerForm.creditor_name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] text-gray-400 mb-1 uppercase">Umumiy Summa *</label>
                                <input :value="formatSpaces(registerForm.total_amount)" @input="registerForm.total_amount = unformatSpaces($event.target.value)" type="text" inputmode="decimal" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                            </div>
                            <div>
                                <label class="block text-[10px] text-gray-400 mb-1 uppercase">Foiz Stavkasi % (Yillik)</label>
                                <input v-model="registerForm.interest_rate" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[10px] text-gray-400 mb-1 uppercase">Muddati (Oy) *</label>
                                <input v-model="registerForm.term_months" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                            </div>
                            <div>
                                <label class="block text-[10px] text-gray-400 mb-1 uppercase">Imtiyozli davr (Oy)</label>
                                <input v-model="registerForm.grace_period_months" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                            </div>
                            <div>
                                <label class="block text-[10px] text-gray-400 mb-1 uppercase">Oylik To'lov *</label>
                                <input v-model="registerForm.monthly_payment" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
<div v-if="registerForm.monthly_payment" class="text-xs text-blue-600 mt-1 font-bold tracking-wider">{{ new Intl.NumberFormat('uz-UZ').format(registerForm.monthly_payment) }} UZS</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] text-gray-400 mb-1 uppercase">Boshlanish Sanasi *</label>
                                <input v-model="registerForm.start_date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                            </div>
                            <div>
                                <label class="block text-[10px] text-gray-400 mb-1 uppercase">Tugash Sanasi</label>
                                <input v-model="registerForm.end_date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Qo'shimcha izoh</label>
                            <textarea v-model="registerForm.description" rows="2" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs p-2"></textarea>
                        </div>
                    </div>

                    <!-- Boshlang'ich balans UZS -->
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Boshlang'ich balans (UZS)</label>
                        <input v-model="registerForm.initial_balance_uzs" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
<div v-if="registerForm.initial_balance_uzs" class="text-xs text-blue-600 mt-1 font-bold tracking-wider">{{ new Intl.NumberFormat('uz-UZ').format(registerForm.initial_balance_uzs) }} UZS</div>
                    </div>

                    <!-- Bino / Obyekt -->
                    <div>
                        <label class="block text-[10px] text-gray-400 mb-1 uppercase">Bino / Obyekt</label>
                        <select v-model="registerForm.building_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                            <option value="">-- Umumiy kassa --</option>
                            <option v-for="b in uniqueBuildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>

                    <!-- Status toggle in edit mode -->
                    <div v-if="isEditRegister" class="flex items-center gap-2 mt-2">
                        <input v-model="registerForm.is_active" type="checkbox" id="register_is_active" class="rounded border-gray-300 text-orange-500 focus:ring-orange-500 w-4 h-4 cursor-pointer">
                        <label for="register_is_active" class="text-[10px] text-gray-700 font-bold uppercase cursor-pointer select-none">Kassa faol (Ish holatida)</label>
                    </div>

                    <div class="flex justify-between items-center pt-6 border-t border-slate-100">
                        <div>
                            <button v-if="isEditRegister" type="button" @click="handleDeleteFromModal" class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-xl font-bold text-xs transition-colors flex items-center gap-1">
                                🗑️ O'chirish
                            </button>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" @click="showRegisterModal = false" class="btn-cancel px-5 py-2 border border-slate-200 rounded-xl text-slate-700 font-bold text-xs hover:bg-slate-50 transition-colors">Bekor qilish</button>
                            <button type="submit" :disabled="isProcessing" class="btn-save px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold text-xs transition-colors shadow-md">Saqlash</button>
                        </div>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- EDIT TRANSACTION MODAL -->
        <Modal :show="showEditTransactionModal" @close="showEditTransactionModal = false" maxWidth="2xl">
            <div class="p-5 font-sans text-xs">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-1 h-5 bg-orange-500 rounded-full"></span>
                        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider font-sans">
                            Tranzaksiyani tahrirlash
                        </h2>
                    </div>
                    <button type="button" @click="showEditTransactionModal = false" class="w-7 h-7 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors text-xs font-bold">
                        ✕
                    </button>
                </div>
                
                <form @submit.prevent="submitEditTransaction" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-3.5">
                        <!-- Tranzaksiya turi (Category) -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Tranzaksiya turi
                            </label>
                            <select v-model="editTransactionForm.category_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Tranzaksiya turini tanlang</option>
                                <option v-for="c in categories.filter(x => x.type === editTransactionForm.type || x.type === 'both')" :key="c.id" :value="c.id">
                                    {{ c.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Qurilish obyekti (Building) -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                Qurilish obyekti (Bino)
                            </label>
                            <select v-model="editTransactionForm.building_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors">
                                <option value="">-- Umumiy kassa --</option>
                                <option v-for="b in uniqueBuildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>

                        <!-- Hisobni tanlash (Cash Register) -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Hisobni tanlash
                            </label>
                            <select v-model="editTransactionForm.account_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Hisobni tanlang</option>
                                <option v-for="r in registers" :key="r.id" :value="r.id">
                                    {{ r.name }} (Balans: {{ formatMoney(r.currency === 'USD' ? r.balance_usd : (r.currency === 'EUR' ? r.balance_eur : r.balance_uzs), r.currency) }})
                                </option>
                            </select>
                        </div>

                        <!-- To'lov turi (Payment Method) -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * To'lov turi
                            </label>
                            <select v-model="editTransactionForm.payment_method" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="cash">Naqd</option>
                                <option value="bank_transfer">Bank (O'tkazma)</option>
                                <option value="card">Plastik (Karta)</option>
                            </select>
                        </div>

                        <!-- Sana (Date) -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Sana
                            </label>
                            <input v-model="editTransactionForm.transaction_date" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                        </div>

                        <!-- Summa (Amount) -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Summa
                            </label>
                            <input :value="formatSpaces(editTransactionForm.amount)" @input="editTransactionForm.amount = unformatSpaces($event.target.value)" type="text" inputmode="decimal" step="0.01" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required placeholder="0.00">
                        </div>

                        <!-- Conditional Target Selections -->
                        <!-- Supplier target -->
                        <div v-if="editRequiresSupplier" class="md:col-span-2 border border-red-100 p-4 rounded-xl bg-red-50/10">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Ta'minotchi (Kontragent)
                            </label>
                            <select v-model="editTransactionForm.counterparty_id" class="w-full bg-white border border-slate-250 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Ta'minotchini tanlang</option>
                                <option v-for="c in counterparties.filter(x => x.type === 'supplier')" :key="c.id" :value="c.id">
                                    {{ c.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Client target -->
                        <div v-if="editRequiresClient" class="md:col-span-2 border border-orange-100 p-4 rounded-xl bg-orange-50/10">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Mijoz (Kontragent)
                            </label>
                            <select v-model="editTransactionForm.counterparty_id" class="w-full bg-white border border-slate-250 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Mijozni tanlang</option>
                                <option v-for="c in counterparties.filter(x => x.type === 'client')" :key="c.id" :value="c.id">
                                    {{ c.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Partner target -->
                        <div v-if="editRequiresPartner" class="md:col-span-2 border border-purple-100 p-4 rounded-xl bg-purple-50/10">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Hamkor (Ta'sischi)
                            </label>
                            <select v-model="editTransactionForm.partner_id" class="w-full bg-white border border-slate-250 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Hamkor tanlang</option>
                                <option v-for="p in partners" :key="p.id" :value="p.id">
                                    {{ p.user?.name || p.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Worker target -->
                        <div v-if="editRequiresWorker" class="md:col-span-2 border border-blue-100 p-4 rounded-xl bg-blue-50/10">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Xodim / Ishchi
                            </label>
                            <select v-model="editTransactionForm.counterparty_id" class="w-full bg-white border border-slate-250 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Xodimni tanlang</option>
                                <option v-for="c in counterparties.filter(x => x.type === 'employee')" :key="c.id" :value="c.id">
                                    {{ c.name }} {{ c.customable_type?.includes('User') ? '(tizim xodimi)' : '' }}
                                </option>
                            </select>
                        </div>

                        <!-- Brigade target -->
                        <div v-if="editRequiresBrigade" class="md:col-span-2 border border-teal-100 p-4 rounded-xl bg-teal-50/10">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Brigada
                            </label>
                            <select v-model="editTransactionForm.brigade_id" class="w-full bg-white border border-slate-250 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Brigadani tanlang</option>
                                <option v-for="b in brigades" :key="b.id" :value="b.id">
                                    {{ b.name }} (Joriy balans: {{ formatMoney(b.balance || 0, 'UZS') }})
                                </option>
                            </select>
                            <div v-if="editTransactionForm.brigade_id" class="mt-2 text-[11px]">
                                <span v-if="(brigades?.find(b => b.id === editTransactionForm.brigade_id)?.balance || 0) < 0" class="text-red-600 font-bold">
                                    Firmadan qarzi bor (Avans): {{ formatMoney(Math.abs(brigades?.find(b => b.id === editTransactionForm.brigade_id)?.balance || 0), 'UZS') }}
                                </span>
                                <span v-else-if="(brigades?.find(b => b.id === editTransactionForm.brigade_id)?.balance || 0) > 0" class="text-teal-600 font-bold">
                                    Firmamiz qarzdor: {{ formatMoney(brigades?.find(b => b.id === editTransactionForm.brigade_id)?.balance || 0, 'UZS') }}
                                </span>
                                <span v-else class="text-slate-500 font-bold">
                                    Balans nolga teng (qarzdorlik yo'q).
                                </span>
                            </div>
                        </div>

                        <!-- Machine Owner target -->
                        <div v-if="editRequiresMachineOwner" class="md:col-span-2 border border-amber-100 p-4 rounded-xl bg-amber-50/10">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Texnika Egasi
                            </label>
                            <select v-model="editTransactionForm.machine_owner_id" class="w-full bg-white border border-slate-250 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Texnika egasini tanlang</option>
                                <option v-for="o in machineOwners" :key="o.id" :value="o.id">
                                    {{ o.name }}
                                </option>
                            </select>
                        </div>

                        <!-- To'lov maqsadi -->
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * To'lov maqsadi
                            </label>
                            <textarea v-model="editTransactionForm.notes" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors resize-none font-sans" required placeholder="To'lov maqsadini kiriting"></textarea>
                        </div>

                        <!-- Izohlar -->
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                Izohlar
                            </label>
                            <textarea v-model="editTransactionForm.extra_notes" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors resize-none font-sans" placeholder="Qo'shimcha izoh kiriting (ixtiyoriy)"></textarea>
                        </div>

                        <!-- Chek faylini yuklash -->
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                Chek faylini yuklash
                            </label>
                            <div class="flex items-center gap-3 py-0.5">
                                <input type="file" ref="editFileInput" class="hidden" @change="handleEditFileUpload">
                                <button type="button" @click="$refs.editFileInput.click()" class="flex items-center gap-2 px-4 py-2 border border-slate-200 rounded-xl text-slate-700 font-bold bg-white hover:bg-slate-50 transition-colors shadow-sm text-xs">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    Fayl yuklash
                                </button>
                                <span v-if="editTransactionForm.attachment" class="text-xs text-slate-500 truncate max-w-[200px]" :title="editTransactionForm.attachment.name">
                                    {{ editTransactionForm.attachment.name }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="showEditTransactionModal = false" class="px-6 py-2 border border-slate-200 rounded-xl text-slate-700 font-bold hover:bg-slate-50 transition-colors">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-8 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold transition-colors shadow-md">Saqlash</button>
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
<div v-if="counterpartyForm.balance" class="text-xs text-blue-600 mt-1 font-bold tracking-wider">{{ new Intl.NumberFormat('uz-UZ').format(counterpartyForm.balance) }} UZS</div>
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
                            <input :value="formatSpaces(liabilityForm.total_amount)" @input="liabilityForm.total_amount = unformatSpaces($event.target.value)" type="text" inputmode="decimal" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Foiz stavkasi (%)</label>
                            <input v-model="liabilityForm.interest_rate" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Muddati (oy) *</label>
                            <input v-model="liabilityForm.term_months" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Imtiyozli davr (oy)</label>
                            <input v-model="liabilityForm.grace_period_months" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1 uppercase">Oylik to'lov</label>
                            <input v-model="liabilityForm.monthly_payment" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9">
<div v-if="liabilityForm.monthly_payment" class="text-xs text-blue-600 mt-1 font-bold tracking-wider">{{ new Intl.NumberFormat('uz-UZ').format(liabilityForm.monthly_payment) }} UZS</div>
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
        <Modal :show="showScheduleModal" @close="showScheduleModal = false" maxWidth="4xl" class="quick-transaction-modal-override">
            <div class="p-6">
                <button @click="showScheduleModal = false" class="absolute top-4 right-4 text-gray-500">✕</button>
                <div class="flex justify-between items-center mb-2">
                    <h3 class="text-lg font-bold text-gray-900 uppercase tracking-wider">To'lovlar grafigi</h3>
                    <button @click="downloadScheduleExcel" class="mr-8 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition-all shadow-sm flex items-center gap-1 font-sans">
                        📥 Excel yuklab olish
                    </button>
                </div>
                <p v-if="selectedLiabilityForSchedule" class="text-xs text-gray-500 mb-6 font-mono">
                    Kredit: <span class="font-bold text-gray-800">{{ selectedLiabilityForSchedule.name || selectedLiabilityForSchedule.creditor_name || selectedLiabilityForSchedule.creditor || '-' }}</span> | 
                    Jami kredit summasi: <span class="font-bold text-purple-700">{{ formatMoney(selectedLiabilityForSchedule.total_amount, selectedLiabilityForSchedule.currency || 'UZS') }}</span> |
                    Muddati: <span class="font-bold text-gray-800">{{ selectedLiabilityForSchedule.term_months || 0 }} oy</span> |
                    Foiz stavkasi: <span class="font-bold text-gray-800">{{ selectedLiabilityForSchedule.interest_rate || 0 }}%</span> |
                    Imtiyozli davr: <span class="font-bold text-amber-600">{{ selectedLiabilityForSchedule.grace_period_months || 0 }} oy</span>
                </p>
                
                <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-xl">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] border-b border-gray-200">
                            <tr>
                                <th class="p-3">Oy</th>
                                <th class="p-3">To'lov Sanasi</th>
                                <th class="p-3 text-right">Oylik To'lov</th>
                                <th class="p-3 text-right">Asosiy Qarz</th>
                                <th class="p-3 text-right">Foiz To'lovi</th>
                                <th class="p-3 text-right">Qoldiq Asosiy Qarz</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in generatedSchedule" :key="row.month" class="hover:bg-gray-50 transition-colors">
                                <td class="p-3 text-gray-400 font-bold">{{ row.month }}</td>
                                <td class="p-3 text-gray-700 font-medium">{{ row.date }}</td>
                                <td class="p-3 text-right font-bold text-emerald-600">
                                    {{ formatMoney(row.payment, selectedLiabilityForSchedule?.currency || 'UZS') }}
                                </td>
                                <td class="p-3 text-right text-gray-800 font-medium">
                                    {{ formatMoney(row.principal, selectedLiabilityForSchedule?.currency || 'UZS') }}
                                </td>
                                <td class="p-3 text-right text-amber-600">
                                    {{ formatMoney(row.interest, selectedLiabilityForSchedule?.currency || 'UZS') }}
                                </td>
                                <td class="p-3 text-right text-gray-500 font-mono">
                                    {{ formatMoney(row.balance, selectedLiabilityForSchedule?.currency || 'UZS') }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50/70 border-t-2 border-gray-200 font-extrabold text-[11px] text-gray-900 sticky bottom-0">
                            <tr>
                                <td class="p-3 text-gray-500 font-bold uppercase tracking-wider text-[10px]">Jami</td>
                                <td></td>
                                <td class="p-3 text-right font-black text-emerald-600">
                                    {{ formatMoney(generatedSchedule.reduce((sum, r) => sum + parseFloat(r.payment || 0), 0), selectedLiabilityForSchedule?.currency || 'UZS') }}
                                </td>
                                <td class="p-3 text-right text-gray-900 font-black">
                                    {{ formatMoney(generatedSchedule.reduce((sum, r) => sum + parseFloat(r.principal || 0), 0), selectedLiabilityForSchedule?.currency || 'UZS') }}
                                </td>
                                <td class="p-3 text-right text-amber-600 font-black">
                                    {{ formatMoney(generatedSchedule.reduce((sum, r) => sum + parseFloat(r.interest || 0), 0), selectedLiabilityForSchedule?.currency || 'UZS') }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
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
                        <input :value="formatSpaces(paymentForm.amount)" @input="paymentForm.amount = unformatSpaces($event.target.value)" type="text" inputmode="decimal" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
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
                        <input :value="formatSpaces(supplierPaymentForm.amount)" @input="supplierPaymentForm.amount = unformatSpaces($event.target.value)" type="text" inputmode="decimal" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
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
<div v-if="editSupplierForm.balance" class="text-xs text-blue-600 mt-1 font-bold tracking-wider">{{ new Intl.NumberFormat('uz-UZ').format(editSupplierForm.balance) }} UZS</div>
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
                        <input :value="formatSpaces(contractApproveForm.applied_amount)" @input="contractApproveForm.applied_amount = unformatSpaces($event.target.value)" type="text" inputmode="decimal" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9" required>
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-1 uppercase">Qaytimga (Naqdlashtirishga) Ajratish</label>
                        <input :value="formatSpaces(contractApproveForm.refund_amount)" @input="contractApproveForm.refund_amount = unformatSpaces($event.target.value)" type="text" inputmode="decimal" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9 px-3 font-bold" required>
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-1 uppercase">Qabul qiluvchi Kassa / Hisob *</label>
                        <select v-model="contractApproveForm.account_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9 px-2 text-xs" required>
                            <option value="" disabled>Kassani tanlang</option>
                            <option v-for="r in registers" :key="r.id" :value="r.id">
                                {{ r.name }} (Balans: {{ formatMoney(r.currency === 'USD' ? r.balance_usd : (r.currency === 'EUR' ? r.balance_eur : r.balance_uzs), r.currency) }})
                            </option>
                        </select>
                    </div>

                    <!-- Warning Alerts -->
                    <div class="space-y-2 pt-2">
                        <div class="p-3 bg-gray-50 border border-gray-150 rounded-xl text-[11px] font-mono space-y-1">
                            <div>Dastlabki tanlangan kassa: <strong class="text-gray-900">{{ getRegisterName(selectedContractTxn?.account_id) }}</strong></div>
                            <div>Yo'naltirilayotgan kassa: <strong class="text-blue-700">{{ getRegisterName(contractApproveForm.account_id) }}</strong></div>
                        </div>

                        <div v-if="contractApproveForm.account_id !== selectedContractTxn?.account_id" class="p-3 bg-amber-50 border border-amber-200 text-amber-700 rounded-xl text-[11px] font-semibold">
                            ⚠️ Diqqat: Pul dastlab tanlangan hisobdan boshqa hisobga yo'naltirilmoqda! ({{ getRegisterName(selectedContractTxn?.account_id) }} ➔ {{ getRegisterName(contractApproveForm.account_id) }})
                        </div>
                        <div v-else class="p-3 bg-emerald-50 border border-emerald-150 text-emerald-700 rounded-xl text-[11px] font-semibold">
                            ✓ Kassa o'zgartirilmadi, pul dastlab tanlangan '{{ getRegisterName(selectedContractTxn?.account_id) }}' kassa hisobiga tushadi.
                        </div>
                        <div v-if="getRegisterBuildingName(contractApproveForm.account_id)" class="p-3 bg-blue-50 border border-blue-200 text-blue-700 rounded-xl text-[11px]">
                            ℹ️ Ushbu kassa faqat <strong>{{ getRegisterBuildingName(contractApproveForm.account_id) }}</strong> obyekti uchun cheklangan.
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                        <button type="button" @click="showContractApproveModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-600 font-bold">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2 bg-emerald-600 text-white rounded-lg font-bold">Tasdiqlash</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- GENERAL TRANSACTION APPROVE MODAL -->
        <Modal :show="showApproveModal" @close="showApproveModal = false" maxWidth="md">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-6 uppercase tracking-wider">Tranzaksiyani Tasdiqlash</h3>
                
                <div class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-2xl text-xs space-y-2 font-mono text-gray-600">
                    <div>Turi: <strong class="text-gray-900 uppercase">{{ selectedApproveTxn?.type === 'income' ? 'Kirim' : 'Chiqim' }}</strong></div>
                    <div>Summa: <strong class="text-emerald-600 text-sm">{{ formatMoney(selectedApproveTxn?.amount, selectedApproveTxn?.currency) }}</strong></div>
                    <div>To'lov maqsadi: <strong class="text-gray-900">{{ selectedApproveTxn?.notes || '-' }}</strong></div>
                    <div v-if="selectedApproveTxn?.description">Izoh: <strong class="text-gray-900">{{ selectedApproveTxn?.description }}</strong></div>
                </div>

                <form @submit.prevent="submitApprove" class="space-y-4 font-mono text-xs">
                    <div>
                        <label class="block text-gray-400 mb-1 uppercase">Tasdiqlovchi Kassa / Hisob *</label>
                        <select v-model="approveForm.account_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full h-9 px-2 text-xs" required>
                            <option value="" disabled>Kassani tanlang</option>
                            <option v-for="r in registers" :key="r.id" :value="r.id">
                                {{ r.name }} (Balans: {{ formatMoney(r.currency === 'USD' ? r.balance_usd : (r.currency === 'EUR' ? r.balance_eur : r.balance_uzs), r.currency) }})
                            </option>
                        </select>
                    </div>

                    <!-- Warning Alerts -->
                    <div class="space-y-2 pt-2">
                        <div class="p-3 bg-gray-50 border border-gray-150 rounded-xl text-[11px] font-mono space-y-1">
                            <div>Dastlabki tanlangan kassa: <strong class="text-gray-900">{{ getRegisterName(selectedApproveTxn?.account_id) }}</strong></div>
                            <div>Yo'naltirilayotgan kassa: <strong class="text-blue-700">{{ getRegisterName(approveForm.account_id) }}</strong></div>
                        </div>

                        <div v-if="approveForm.account_id !== selectedApproveTxn?.account_id" class="p-3 bg-amber-50 border border-amber-200 text-amber-700 rounded-xl text-[11px] font-semibold">
                            ⚠️ Diqqat: Pul telegramda belgilangan hisobdan boshqa hisobga yo'naltirilmoqda! ({{ getRegisterName(selectedApproveTxn?.account_id) }} ➔ {{ getRegisterName(approveForm.account_id) }})
                        </div>
                        <div v-else class="p-3 bg-emerald-50 border border-emerald-150 text-emerald-700 rounded-xl text-[11px] font-semibold">
                            ✓ Kassa o'zgartirilmadi, pul telegramda tanlangan '{{ getRegisterName(selectedApproveTxn?.account_id) }}' kassa hisobiga tushadi/chiqadi.
                        </div>
                        <div v-if="getRegisterBuildingName(approveForm.account_id)" class="p-3 bg-blue-50 border border-blue-200 text-blue-700 rounded-xl text-[11px]">
                            ℹ️ Ushbu kassa faqat <strong>{{ getRegisterBuildingName(approveForm.account_id) }}</strong> obyekti uchun cheklangan.
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                        <button type="button" @click="showApproveModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-600 font-bold">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2 bg-emerald-600 text-white rounded-lg font-bold">Tasdiqlash</button>
                    </div>
                </form>
            </div>
        </Modal>
        <!-- QUICK TRANSACTION MODAL (Kirim / Chiqim) -->
        <Modal :show="showQuickTransactionModal" @close="showQuickTransactionModal = false" maxWidth="2xl" class="quick-transaction-modal-override">
            <div class="p-5">
                <!-- Header with Title & Close Icon -->
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-1 h-5 bg-orange-500 rounded-full"></span>
                        <h2 class="text-sm font-bold text-slate-800 font-sans">
                            {{ form.type === 'income' ? 'Yangi kirim' : 'Yangi chiqim' }}
                        </h2>
                    </div>
                    <button type="button" @click="showQuickTransactionModal = false" class="w-7 h-7 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors text-xs font-bold">
                        ✕
                    </button>
                </div>
                
                <form @submit.prevent="submitTransaction" class="space-y-4 font-sans text-xs">
                    <!-- Error Banner -->
                    <div v-if="page.props.errors && Object.keys(page.props.errors).length > 0" class="p-3 bg-red-50 border border-red-150 rounded-xl text-red-600 text-xs">
                        <ul class="list-disc list-inside font-mono">
                            <li v-for="(err, field) in page.props.errors" :key="field">{{ err }}</li>
                        </ul>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-3.5">
                        <!-- Row 1: Tranzaksiya turi & Qurilish obyekti -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Tranzaksiya turi
                            </label>
                            <select v-model="form.category_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Tranzaksiya turini tanlang</option>
                                <option v-for="c in categories.filter(x => x.type === form.type || x.type === 'both')" :key="c.id" :value="c.id">
                                    {{ c.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Qurilish obyekti
                            </label>
                            <select v-model="selectedBuildingName" @change="handleBuildingNameChange" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Qurilish obyekti tanlang</option>
                                <option v-for="name in uniqueBuildingNames" :key="name" :value="name">
                                    {{ name }}
                                </option>
                            </select>
                        </div>


                        <!-- Row 2: Hisobni tanlash & To'lov turi -->
                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-3.5">
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                        * Hisobni tanlash
                                    </label>
                                    <select v-model="selectedParentRegisterId" @change="handleParentRegisterChange" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                        <option value="" disabled>Hisobni tanlang</option>
                                        <option v-for="r in parentRegisters" :key="r.id" :value="r.id">
                                            {{ r.name }} (Balans: {{ formatMoney(r.currency === 'USD' ? r.balance_usd : (r.currency === 'EUR' ? r.balance_eur : r.balance_uzs), r.currency) }})
                                        </option>
                                    </select>
                                </div>

                                <!-- Sub-kassa (Optional/Conditional) -->
                                <div v-if="subRegisters.length > 0">
                                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                        * Sub kassa tanlash
                                    </label>
                                    <select v-model="selectedSubRegisterId" @change="handleSubRegisterChange" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                        <option value="" disabled>Sub kassa tanlang</option>
                                        <option v-for="r in subRegisters" :key="r.id" :value="r.id">
                                            {{ r.name }} (Balans: {{ formatMoney(r.currency === 'USD' ? r.balance_usd : (r.currency === 'EUR' ? r.balance_eur : r.balance_uzs), r.currency) }})
                                        </option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                    * To'lov turi
                                </label>
                                <select v-model="form.payment_method" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                    <option value="cash">Naqd</option>
                                    <option value="bank_transfer">Bank (O'tkazma)</option>
                                    <option value="card">Plastik (Karta)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 3: Sana & To'lov maqsadi -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Sana
                            </label>
                            <input v-model="form.transaction_date" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * To'lov maqsadi
                            </label>
                            <select v-if="requiresClient" v-model="clientPaymentType" @change="handlePaymentTypeChange" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="monthly">Oylik to'lov</option>
                                <option value="initial">Boshlang'ich to'lov</option>
                            </select>
                            <textarea v-else v-model="form.notes" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors resize-none font-sans" required placeholder="mijoz boshlang'ich tolov qildi naqd da"></textarea>
                        </div>

                        <!-- Row 4: Hujjat raqami & Summa -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                Hujjat raqami (Avtomatik)
                            </label>
                            <input v-model="documentNumberInput" readonly type="text" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-500 outline-none transition-colors cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Summa
                            </label>
                            <input :value="formatSpaces(form.amount)" @input="form.amount = unformatSpaces($event.target.value)" type="text" inputmode="decimal" step="0.01" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required placeholder="0.00">
                        </div>

                        <!-- Conditional client section -->
                        <div v-if="requiresClient" class="md:col-span-2 border border-orange-100 p-4 rounded-xl bg-orange-50/10 space-y-3">
                            <!-- Xonadon selector (Apartment/Unit Filter) -->
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                    Xonadon (Filtr)
                                </label>
                                <select v-model="selectedUnitId" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors">
                                    <option value="">Barcha xonadonlar</option>
                                    <option v-for="u in availableUnits" :key="u.id" :value="u.id">
                                        {{ u.unit_number }} (Obyekt: {{ u.building_name }})
                                    </option>
                                </select>
                            </div>

                            <div class="relative">
                                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                    * Mijoz (Kontragent)
                                </label>
                                <input type="text" v-model="clientSearchQuery" @focus="showClientDropdown = true" @click="showClientDropdown = true" @input="showClientDropdown = true" @blur="closeClientDropdown" placeholder="Mijoz ismini yozib qidiring..." class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required />
                                
                                <div v-show="showClientDropdown" class="absolute z-50 left-0 right-0 bg-white border border-slate-250 rounded-xl shadow-lg mt-1 max-h-48 overflow-y-auto">
                                    <div v-for="c in filteredClientsList" :key="c.id" @mousedown.prevent="selectClientForQuickTransaction(c)" class="p-2.5 hover:bg-slate-50 cursor-pointer border-b border-slate-100 flex justify-between items-center text-xs">
                                        <span class="font-medium text-slate-800">{{ c.name }}</span>
                                        <span v-if="c.phone" class="text-slate-400 font-mono text-[10px]">{{ c.phone }}</span>
                                    </div>
                                    <div v-if="!filteredClientsList.length" class="p-3 text-center text-slate-400 text-xs font-mono">
                                        Mijozlar topilmadi
                                    </div>
                                </div>
                                <span v-if="form.counterparty_id" class="text-[10px] text-slate-500 mt-1 block">
                                    Tanlandi: <strong class="text-slate-800">{{ selectedClientName }}</strong>
                                </span>
                            </div>
                            <div v-if="form.client_id">
                                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                    * Mijoz Shartnomasi
                                </label>
                                <select v-model="form.contract_id" class="w-full bg-white border border-slate-250 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                    <option value="" disabled>Shartnomani tanlang</option>
                                    <option v-for="c in clientContracts" :key="c.id" :value="c.id">
                                        Shartnoma #{{ c.contract_number || c.id }} - Xona: {{ c.unit?.unit_number || 'Noma\'lum' }}
                                    </option>
                                </select>
                                <span v-if="clientContracts.length === 0" class="text-[10px] text-red-500 mt-1 block font-mono">
                                    Ushbu mijoz uchun faol shartnoma topilmadi!
                                </span>



                                <!-- Unpaid Installment Schedule list -->
                                <div v-if="form.contract_id && clientPaymentType === 'monthly' && contractSchedules.filter(s => s.payment_type !== 'down_payment').length > 0" class="mt-3 space-y-1 bg-white border border-slate-100 rounded-xl p-3 shadow-inner">
                                    <label class="block text-[10px] font-mono text-slate-500 uppercase mb-2">
                                        To'lov Rejasi (Oylik to'lovlar)
                                    </label>
                                    <div class="max-h-36 overflow-y-auto space-y-1">
                                        <div v-for="(s, index) in contractSchedules.filter(s => s.payment_type !== 'down_payment')" :key="s.id" 
                                             @click="selectScheduleAmount(s)"
                                             class="flex justify-between items-center p-2 rounded-lg text-[11px] cursor-pointer transition-all border group"
                                             :class="form.payment_schedule_id === s.id ? 'border-orange-500 bg-orange-50/60 font-bold shadow-sm' : 'border-transparent hover:border-orange-200 hover:bg-orange-50/50'">
                                            <div class="flex items-center gap-2">
                                                <span class="w-5 h-5 flex items-center justify-center rounded-full bg-slate-100 text-slate-600 font-mono text-[9px]">
                                                    {{ index + 1 }}
                                                </span>
                                                <span class="text-slate-600 font-medium">
                                                    Muddati: {{ s.due_date ? new Date(s.due_date).toLocaleDateString() : 'Noma\'lum' }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-900 group-hover:text-orange-600 transition-colors">
                                                    {{ formatMoney(Number(s.expected_amount) - Number(s.paid_amount || 0), 'so\'m') }}
                                                </span>
                                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase"
                                                      :class="s.status === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'">
                                                    {{ s.status === 'partial' ? 'Qisman' : 'Kutilmoqda' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[9px] text-slate-400 mt-1">💡 To'lov summasini avtomatik to'ldirish uchun kerakli qatorni bosing.</p>
                                </div>

                                <!-- Unpaid Down Payment Schedule list -->
                                <div v-if="form.contract_id && clientPaymentType === 'initial' && contractSchedules.filter(s => s.payment_type === 'down_payment').length > 0" class="mt-3 space-y-1 bg-white border border-slate-100 rounded-xl p-3 shadow-inner">
                                    <label class="block text-[10px] font-mono text-slate-500 uppercase mb-2">
                                        To'lov Rejasi (Boshlang'ich to'lovlar)
                                    </label>
                                    <div class="max-h-36 overflow-y-auto space-y-1">
                                        <div v-for="(s, index) in contractSchedules.filter(s => s.payment_type === 'down_payment')" :key="s.id" 
                                             @click="selectScheduleAmount(s)"
                                             class="flex justify-between items-center p-2 rounded-lg text-[11px] cursor-pointer transition-all border group"
                                             :class="form.payment_schedule_id === s.id ? 'border-orange-500 bg-orange-50/60 font-bold shadow-sm' : 'border-transparent hover:border-orange-200 hover:bg-orange-50/50'">
                                            <div class="flex items-center gap-2">
                                                <span class="w-5 h-5 flex items-center justify-center rounded-full bg-slate-100 text-slate-600 font-mono text-[9px]">
                                                    {{ index + 1 }}
                                                </span>
                                                <span class="text-slate-600 font-medium">
                                                    Muddati: {{ s.due_date ? new Date(s.due_date).toLocaleDateString() : 'Noma\'lum' }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-900 group-hover:text-orange-600 transition-colors">
                                                    {{ formatMoney(Number(s.expected_amount) - Number(s.paid_amount || 0), 'so\'m') }}
                                                </span>
                                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase"
                                                      :class="s.status === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'">
                                                    {{ s.status === 'partial' ? 'Qisman' : 'Kutilmoqda' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[9px] text-slate-400 mt-1">💡 To'lov summasini avtomatik to'ldirish uchun kerakli qatorni bosing.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Conditional supplier section -->
                        <div v-if="requiresSupplier" class="md:col-span-2 border border-red-100 p-4 rounded-xl bg-red-50/10">
                            <select v-model="form.counterparty_id" class="w-full bg-white border border-slate-250 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Ta'minotchini tanlang</option>
                                <option v-for="c in counterparties.filter(x => x.type === 'supplier')" :key="c.id" :value="c.id">
                                    {{ c.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Conditional partner section -->
                        <div v-if="requiresPartner" class="md:col-span-2 border border-purple-100 p-4 rounded-xl bg-purple-50/10">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Hamkor (Ta'sischi)
                            </label>
                            <select v-model="form.partner_id" class="w-full bg-white border border-slate-250 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Hamkor tanlang</option>
                                <option v-for="p in partners" :key="p.id" :value="p.id">
                                    {{ p.user?.name || p.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Conditional worker section -->
                        <div v-if="requiresWorker" class="md:col-span-2 border border-blue-100 p-4 rounded-xl bg-blue-50/10">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Xodim / Ishchi
                            </label>
                            <select v-model="form.counterparty_id" class="w-full bg-white border border-slate-250 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Xodimni tanlang</option>
                                <option v-for="c in counterparties.filter(x => x.type === 'employee')" :key="c.id" :value="c.id">
                                    {{ c.name }} {{ c.customable_type?.includes('User') ? '(tizim xodimi)' : '' }}
                                </option>
                            </select>
                        </div>

                        <!-- Conditional brigade section -->
                        <div v-if="requiresBrigade" class="md:col-span-2 border border-teal-100 p-4 rounded-xl bg-teal-50/10">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Brigada
                            </label>
                            <select v-model="form.brigade_id" class="w-full bg-white border border-slate-250 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Brigadani tanlang</option>
                                <option v-for="b in brigades" :key="b.id" :value="b.id">
                                    {{ b.name }} (Joriy balans: {{ formatMoney(b.balance || 0, 'UZS') }})
                                </option>
                            </select>
                            <div v-if="form.brigade_id" class="mt-2 text-[11px]">
                                <span v-if="(brigades?.find(b => b.id === form.brigade_id)?.balance || 0) < 0" class="text-red-600 font-bold">
                                    Firmadan qarzi bor (Avans): {{ formatMoney(Math.abs(brigades?.find(b => b.id === form.brigade_id)?.balance || 0), 'UZS') }}
                                </span>
                                <span v-else-if="(brigades?.find(b => b.id === form.brigade_id)?.balance || 0) > 0" class="text-teal-600 font-bold">
                                    Firmamiz qarzdor: {{ formatMoney(brigades?.find(b => b.id === form.brigade_id)?.balance || 0, 'UZS') }}
                                </span>
                                <span v-else class="text-slate-500 font-bold">
                                    Balans nolga teng (qarzdorlik yo'q).
                                </span>
                            </div>
                        </div>

                        <!-- Conditional machine owner section -->
                        <div v-if="requiresMachineOwner" class="md:col-span-2 border border-amber-100 p-4 rounded-xl bg-amber-50/10">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                * Texnika Egasi
                            </label>
                            <select v-model="form.machine_owner_id" class="w-full bg-white border border-slate-250 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>Texnika egasini tanlang</option>
                                <option v-for="o in machineOwners" :key="o.id" :value="o.id">
                                    {{ o.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Unpaid Works List -->
                        <div v-if="(requiresWorker && form.worker_id) || (requiresBrigade && form.brigade_id) || (requiresMachineOwner && form.machine_owner_id)" class="md:col-span-2 border border-indigo-100 p-4 rounded-xl bg-indigo-50/10">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-2">
                                To'lanmagan ishlar (majburiy emas)
                            </label>
                            <div v-if="isFetchingWorks" class="text-xs text-slate-400 py-2">
                                Ishlar yuklanmoqda...
                            </div>
                            <div v-else-if="unpaidWorks.length === 0" class="text-xs text-slate-400 py-2">
                                To'lanmagan ishlar topilmadi.
                            </div>
                            <div v-else class="max-h-36 overflow-y-auto space-y-1">
                                <div v-for="w in unpaidWorks" :key="w.id"
                                     @click="form.linked_work_id = w.id; form.linked_work_type = requiresWorker ? 'App\\Models\\WorkLog' : (requiresBrigade ? 'App\\Models\\BrigadeWork' : 'App\\Models\\MachineWork'); form.amount = w.balance;"
                                     class="flex justify-between items-center p-2 rounded-lg text-[11px] cursor-pointer transition-all border group"
                                     :class="form.linked_work_id === w.id ? 'border-indigo-500 bg-indigo-50/60 font-bold shadow-sm' : 'border-slate-200 hover:border-indigo-200 hover:bg-indigo-50/50'">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-slate-600 font-medium">
                                            {{ w.description || w.work_name || 'Bajarilgan ish' }} (Sana: {{ w.date || w.work_date || '?' }})
                                        </span>
                                        <span class="text-[9px] text-slate-400">
                                            Jami: {{ formatMoney(w.calculated_cost || w.calculated_sum || w.total_price || 0, 'so\'m') }} 
                                            | To'langan: {{ formatMoney(w.paid_amount || 0, 'so\'m') }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">
                                            Qarzdorlik: {{ formatMoney(w.balance, 'so\'m') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-[9px] text-slate-400 mt-1">💡 Tanlangan ishning narxi avtomatik tarzda summa maydoniga qo'yiladi.</p>
                        </div>

                        <!-- Row 5: Izohlar -->
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                Izohlar
                            </label>
                            <textarea v-model="extraNotesInput" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors resize-none font-sans" placeholder="Qo'shimcha izoh kiriting (ixtiyoriy)"></textarea>
                        </div>

                        <!-- Row 6: Ilovalar -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                                Ilovalar
                            </label>
                            <div class="flex items-center gap-3 py-0.5">
                                <input type="file" ref="fileInput" class="hidden" @change="handleFileUpload">
                                <button type="button" @click="$refs.fileInput.click()" class="btn-upload flex items-center gap-2 px-4 py-2 border border-slate-200 rounded-xl text-slate-700 font-bold bg-white hover:bg-slate-50 transition-colors shadow-sm text-xs">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    Fayl yuklash
                                </button>
                                <span v-if="form.attachment" class="text-xs text-slate-500 truncate max-w-[200px]" :title="form.attachment.name">
                                    {{ form.attachment.name }}
                                </span>
                            </div>
                        </div>
                        <div class="hidden md:block"></div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="showQuickTransactionModal = false" class="btn-cancel px-6 py-2 border border-slate-200 rounded-xl text-slate-700 font-bold text-xs hover:bg-slate-50 transition-colors">
                            Bekor qilish
                        </button>
                        <button type="submit" :disabled="isProcessing" class="btn-save px-8 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold text-xs transition-colors shadow-md">
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- HISOB-FAKTURALARNI TANLASH SUB-MODAL -->
        <Modal :show="showInvoiceSelectorModal" @close="showInvoiceSelectorModal = false" maxWidth="xl" class="quick-transaction-modal-override">
            <div class="p-5 space-y-4">
                <!-- Header with Title & Close Icon -->
                <div class="flex justify-between items-center pb-2 border-b border-slate-100 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-1 h-5 bg-orange-500 rounded-full"></span>
                        <h2 class="text-sm font-bold text-slate-800 font-sans">
                            Hisob-fakturalarni tanlash
                        </h2>
                    </div>
                    <button type="button" @click="showInvoiceSelectorModal = false" class="w-7 h-7 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors text-xs font-bold">
                        ✕
                    </button>
                </div>

                <!-- Search & Filters -->
                <div class="flex gap-3">
                    <!-- Search input -->
                    <div class="relative flex-1">
                        <input v-model="invoiceSearchQuery" type="text" placeholder="Hisob-faktura raqami bo'yicha qidirish..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-1.5 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors">
                        <span class="absolute left-3 top-2 text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                    </div>

                    <!-- Currency filter dropdown -->
                    <div class="w-36">
                        <select v-model="invoiceCurrencyFilter" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors">
                            <option value="">Barcha valyutalar</option>
                            <option v-for="c in availableInvoicesCurrencies" :key="c" :value="c">
                                {{ c }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Invoices Container -->
                <div class="border border-slate-200 rounded-xl overflow-hidden divide-y divide-slate-100 max-h-80 overflow-y-auto">
                    <!-- Hammasini tanlash row -->
                    <div class="p-2.5 bg-slate-50/50 flex items-center gap-3">
                        <input type="checkbox" v-model="isAllInvoicesSelected" class="rounded border-slate-350 text-orange-500 focus:ring-orange-500 w-4 h-4 cursor-pointer">
                        <span class="text-xs font-semibold text-slate-700">Hammasini tanlash</span>
                    </div>

                    <!-- List rows -->
                    <div v-for="inv in availableInvoices" :key="inv.id" class="p-3 flex items-center justify-between gap-4 hover:bg-slate-50/50 transition-colors">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" :value="inv.id" v-model="selectedInvoiceIds" class="rounded border-slate-350 text-orange-500 focus:ring-orange-500 w-4 h-4 cursor-pointer">
                            <div>
                                <span class="block text-xs font-bold text-slate-900 font-mono">{{ inv.invoice_number }}</span>
                                <span class="text-[10px] text-slate-400 mt-0.5 block font-mono">{{ inv.due_date ? new Date(inv.due_date).toLocaleDateString() : 'Noma\'lum' }}</span>
                            </div>
                            <!-- badge "Yangi" -->
                            <span class="ml-2 px-1.5 py-0.5 rounded text-[9px] font-bold bg-orange-50 text-orange-600 border border-orange-100 uppercase">
                                {{ inv.status === 'new' ? 'Yangi' : inv.status }}
                            </span>
                        </div>

                        <!-- Amounts info -->
                        <div class="flex gap-4 font-mono text-xs">
                            <div class="text-right">
                                <span class="block text-[9px] text-slate-400 uppercase">Jami</span>
                                <span class="font-bold text-slate-900">{{ parseFloat(inv.amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }} {{ inv.currency }}</span>
                            </div>
                            <div class="text-right">
                                <span class="block text-[9px] text-slate-400 uppercase">Qoldiq</span>
                                <span class="font-bold text-orange-600">{{ (parseFloat(inv.amount) - parseFloat(inv.paid_amount || 0)).toLocaleString(undefined, {minimumFractionDigits: 2}) }} {{ inv.currency }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="availableInvoices.length === 0" class="p-6 text-center text-slate-400 text-xs font-mono">
                        Hisob-fakturalar topilmadi.
                    </div>
                </div>

                <!-- Footer buttons -->
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="showInvoiceSelectorModal = false" class="btn-cancel px-5 py-2 border border-slate-200 rounded-xl text-slate-700 font-bold text-xs hover:bg-slate-50 transition-colors">
                        Bekor qilish
                    </button>
                    <button type="button" @click="confirmInvoiceSelection" :disabled="selectedInvoiceIds.length === 0" class="btn-save px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold text-xs transition-colors shadow-md disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none">
                        Tasdiqlash
                    </button>
                </div>
            </div>
        </Modal>

        <!-- QUICK TRANSFER MODAL -->
        <Modal :show="showQuickTransferModal" @close="showQuickTransferModal = false" maxWidth="md" class="quick-transaction-modal-override">
            <div class="p-5">
                <!-- Header with Title & Close Icon -->
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-1 h-5 bg-orange-500 rounded-full"></span>
                        <h2 class="text-sm font-bold text-slate-800 font-sans">
                            {{ isEditingTransfer ? 'Ichki pul o\'tkazmasini tahrirlash' : 'Ichki pul o\'tkazmasi' }}
                        </h2>
                    </div>
                    <button type="button" @click="showQuickTransferModal = false" class="w-7 h-7 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors text-xs font-bold">
                        ✕
                    </button>
                </div>
                
                <form @submit.prevent="submitTransfer" class="space-y-4 font-sans text-xs">
                    <div v-if="transferError" class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-center gap-2 font-medium">
                        <span>⚠️ {{ transferError }}</span>
                    </div>
                    <!-- From Account -->
                    <div v-if="isTransferFromLocked">
                        <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Chiqish Kassasi</label>
                        <input :value="getRegisterNameWithCurrency(transferForm.from_account_id)" type="text" readonly class="bg-slate-100 border border-slate-200 text-slate-500 rounded-xl w-full px-3 py-2 font-bold focus:outline-none">
                    </div>
                    <div v-else>
                        <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Chiqish Kassasi *</label>
                        <select v-model="transferForm.from_account_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                            <option value="" disabled>-- Tanlang --</option>
                            <option v-for="r in registers.filter(x => x.is_active)" :key="r.id" :value="r.id">
                                {{ r.parent_id ? `${getRegisterName(r.parent_id)} ↳ ${r.name}` : r.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- To Account selection -->
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Kirish Kassasi *</label>
                            <select v-model="transferForm.to_account_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                                <option value="" disabled>-- Tanlang --</option>
                                <option v-for="r in registers.filter(x => x.id != transferForm.from_account_id && x.is_active)" :key="r.id" :value="r.id">
                                    {{ r.parent_id ? `${getRegisterName(r.parent_id)} ↳ ${r.name}` : r.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Sent Amount -->
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">O'tkazma Summasi *</label>
                            <input :value="formatSpaces(transferForm.sent_amount)" @input="transferForm.sent_amount = unformatSpaces($event.target.value)" type="text" inputmode="decimal" step="0.01" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Transaction Date -->
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Sana *</label>
                            <input v-model="transferForm.transaction_date" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                        </div>

                        <!-- Payment Purpose (Notes) -->
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">To'lov Maqsadi</label>
                            <input v-model="transferForm.notes" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" placeholder="Masalan: Naqdlashtirish, Kassalararo o'tkazma...">
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Izoh (Eslatma)</label>
                        <input v-model="transferForm.description" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" placeholder="Qo'shimcha izoh yoki eslatma kiritishingiz mumkin...">
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="showQuickTransferModal = false" class="btn-cancel px-5 py-2 border border-slate-200 rounded-xl text-slate-700 font-bold text-xs hover:bg-slate-50 transition-colors">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="btn-save px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold text-xs transition-colors shadow-md flex items-center justify-center gap-2">
                            <svg v-if="isProcessing" class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ isProcessing ? 'Yuborilmoqda...' : (isEditingTransfer ? 'Saqlash' : 'Yuborish') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- VIEW TRANSFER DETAILS MODAL -->
        <Modal :show="showViewTransferModal" @close="showViewTransferModal = false" maxWidth="md">
            <div class="p-6 font-sans text-xs">
                <!-- Header with Title & Close Icon -->
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-5">
                    <div class="flex items-center gap-2">
                        <span class="w-1 h-5 bg-blue-500 rounded-full"></span>
                        <h2 class="text-sm font-bold text-slate-800">
                            Pul o'tkazmasi tafsilotlari #{{ viewingTransfer?.id }}
                        </h2>
                    </div>
                    <button type="button" @click="showViewTransferModal = false" class="w-7 h-7 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors text-xs font-bold">
                        ✕
                    </button>
                </div>

                <div class="space-y-4" v-if="viewingTransfer">
                    <!-- Registers/Accounts info -->
                    <div class="bg-slate-50 border border-slate-150 rounded-2xl p-4 space-y-3 font-mono">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400 uppercase tracking-wider text-[9px] font-semibold">Chiquvchi kassa:</span>
                            <span class="font-bold text-slate-800">{{ viewingTransfer.from_account?.name || 'Noma\'lum' }}</span>
                        </div>
                        <div class="flex justify-center my-1 text-blue-500 font-bold text-sm">
                            ⬇️
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400 uppercase tracking-wider text-[9px] font-semibold">Kiruvchi kassa:</span>
                            <span class="font-bold text-slate-800">{{ viewingTransfer.to_account?.name || 'Noma\'lum' }}</span>
                        </div>
                    </div>

                    <!-- Amounts info -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 border border-slate-150 rounded-2xl p-3 text-center font-mono">
                            <span class="text-slate-400 uppercase tracking-wider text-[9px] block mb-1">Yuborilgan summa</span>
                            <span class="font-bold text-red-600 text-sm">
                                -{{ formatMoney(viewingTransfer.sent_amount, viewingTransfer.sent_currency) }}
                            </span>
                        </div>
                        <div class="bg-slate-50 border border-slate-150 rounded-2xl p-3 text-center font-mono">
                            <span class="text-slate-400 uppercase tracking-wider text-[9px] block mb-1">Qabul qilingan summa</span>
                            <span class="font-bold text-emerald-600 text-sm">
                                +{{ formatMoney(viewingTransfer.received_amount, viewingTransfer.received_currency) }}
                            </span>
                        </div>
                    </div>

                    <!-- Exchange Rate & Date -->
                    <div class="border border-slate-100 rounded-xl divide-y divide-slate-100 text-slate-700">
                        <div class="p-3 flex justify-between">
                            <span class="text-slate-400 font-semibold">Ayirboshlash kursi:</span>
                            <span class="font-mono font-bold">{{ viewingTransfer.exchange_rate }}</span>
                        </div>
                        <div class="p-3 flex justify-between">
                            <span class="text-slate-400 font-semibold">O'tkazma sanasi:</span>
                            <span class="font-mono">{{ new Date(viewingTransfer.created_at).toLocaleString() }}</span>
                        </div>
                        <div class="p-3">
                            <span class="text-slate-400 block mb-1 font-semibold">Izoh (Eslatma):</span>
                            <p class="font-mono text-slate-800 whitespace-pre-line bg-slate-50 p-2.5 rounded-lg border border-slate-150">
                                {{ viewingTransfer.description || 'Izoh yo\'q' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-4 mt-4 border-t border-slate-100">
                    <button type="button" @click="showViewTransferModal = false" class="px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-bold text-xs transition-colors shadow-md">
                        Yopish
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Import Modal -->
        <Modal :show="showImportModal" @close="showImportModal = false" maxWidth="3xl" class="quick-transaction-modal-override">
            <div class="theme-modal p-6 space-y-6 relative">
                <!-- Glassmorphic Loading Overlay -->
                <div v-if="isImporting" class="absolute inset-0 bg-white/75 backdrop-blur-sm flex flex-col items-center justify-center rounded-2xl z-50">
                    <div class="w-12 h-12 border-4 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
                    <p class="mt-4 text-xs font-bold text-slate-700 animate-pulse font-mono uppercase tracking-wider">Fayl yuklanmoqda, iltimos kuting...</p>
                </div>

                <!-- Header -->
                <div class="flex justify-between items-center border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-2">
                        <h3 class="text-lg font-bold text-slate-900 font-display">Import</h3>
                        <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2zM12 5v6m0 0l-2-2m2 2l2-2"></path>
                        </svg>
                    </div>
                    <button type="button" @click="showImportModal = false" class="w-7 h-7 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors text-xs font-bold">✕</button>
                </div>

                <!-- Step 1: Upload Form -->
                <form v-if="importStep === 1" @submit.prevent="submitImport" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Account Selection -->
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">* HISOBNI TANLASH</label>
                            <select v-model="importForm.account_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" :disabled="isImporting">
                                <option value="" disabled>-- Tanlang --</option>
                                <option v-for="r in registers.filter(x => x.is_active)" :key="r.id" :value="r.id">
                                    {{ r.name }} (Balans: {{ formatMoney(r.balance_uzs || 0, 'UZS') }})
                                </option>
                            </select>
                        </div>

                        <!-- Building Selection -->
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">* QURILISH OBYEKTI</label>
                            <select v-model="importForm.building_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" :disabled="isImporting">
                                <option value="" disabled>-- Tanlang --</option>
                                <option v-for="b in uniqueBuildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- File Upload Button Row -->
                    <div class="flex items-center gap-3">
                        <input type="file" ref="fileInputRef" @change="handleImportFileChange" class="hidden" accept=".xlsx,.xls,.csv" :disabled="isImporting" />
                        <button type="button" @click="$refs.fileInputRef.click()" class="btn-upload flex items-center gap-2 px-4 py-2.5 border border-slate-200 rounded-xl text-slate-700 font-bold text-xs hover:bg-slate-50 transition-colors shadow-sm" :disabled="isImporting">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            <span>Fayl yuklash</span>
                        </button>
                        <span v-if="importForm.file" class="text-xs text-slate-600 font-bold">{{ importForm.file.name }}</span>
                        <span v-else class="text-xs text-slate-400 font-mono">Hech qanday fayl tanlanmagan</span>
                    </div>

                    <!-- Comments / Notes -->
                    <div>
                        <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1 font-sans">IZOHLAR</label>
                        <textarea v-model="importForm.comment" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" placeholder="Qo'shimcha izoh kiritib (ixtiyoriy)" :disabled="isImporting"></textarea>
                    </div>

                    <!-- Footer buttons -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="showImportModal = false" class="btn-cancel px-5 py-2 border border-slate-200 rounded-xl text-slate-700 font-bold text-xs hover:bg-slate-50 transition-colors" :disabled="isImporting">Bekor qilish</button>
                        <button type="submit" :disabled="isImporting" class="btn-save px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold text-xs transition-colors shadow-md">Yuklash va Ko'rish</button>
                    </div>
                </form>

                <!-- Step 2: Preview & Automatic AI Mapping Table -->
                <div v-else class="space-y-4">
                    <div class="flex justify-between items-center bg-slate-50 p-3 rounded-xl border border-slate-150 text-xs">
                        <div>
                            <p class="font-bold text-slate-700 font-sans">Fayl: {{ importFileName }}</p>
                            <p class="text-[10px] text-slate-500 font-mono mt-0.5">Jami qatorlar: {{ importPreviewRows.length }} ta. Sun'iy intellekt (IA) ma'lumotlarni avtomatik aniqladi va taqsimladi.</p>
                        </div>
                        <button type="button" @click="backToImportStep1" class="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-lg font-bold text-[11px] transition-colors" :disabled="isImporting">Faylni o'zgartirish</button>
                    </div>

                    <!-- Xatoliklar Ro'yxati (Error Alert Box) -->
                    <div v-if="importErrors && importErrors.length > 0" class="p-3 bg-red-50 border border-red-200 rounded-xl space-y-1">
                        <div class="flex items-center gap-2 text-red-700 font-bold text-xs font-sans">
                            <span>⚠️ Xatoliklar Ro'yxati ({{ importErrors.length }} ta qatorda aniqlandi):</span>
                        </div>
                        <ul class="text-[11px] text-red-600 font-mono space-y-0.5 max-h-24 overflow-y-auto pl-4 list-disc">
                            <li v-for="(err, eIdx) in importErrors" :key="eIdx">
                                Qator #{{ err.row_num }}: {{ err.message }}
                            </li>
                        </ul>
                    </div>

                    <div class="overflow-x-auto max-h-[340px] border border-slate-100 rounded-xl">
                        <table class="w-full text-left text-[11px] border-collapse">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-[9px] tracking-wider font-bold border-b border-slate-200 sticky top-0 z-10 font-sans">
                                <tr>
                                    <th class="p-2.5 text-center w-[50px]">Qator</th>
                                    <th class="p-2.5 w-[90px]">Sana</th>
                                    <th class="p-2.5 min-w-[150px]">Maqsad / Izoh</th>
                                    <th class="p-2.5 text-right w-[100px]">Summa</th>
                                    <th class="p-2.5 min-w-[160px]">Kategoriya (IA)</th>
                                    <th class="p-2.5 min-w-[180px]">Yo'nalish / Kontragent (IA)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white font-mono">
                                <tr v-for="(row, idx) in importPreviewRows" :key="idx" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-2.5 text-center text-slate-400">#{{ row.row_num }}</td>
                                    <td class="p-2.5 text-slate-600 font-bold">{{ row.date }}</td>
                                    <td class="p-2.5 font-sans">
                                        <div class="font-bold text-slate-800 text-[11px]">{{ row.purpose || '-' }}</div>
                                        <div v-if="row.comments" class="text-[9px] text-slate-400 font-mono italic mt-0.5">{{ row.comments }}</div>
                                    </td>
                                    <td class="p-2.5 text-right font-bold">
                                        <div :class="row.type === 'income' ? 'text-green-600' : 'text-red-600'">
                                            {{ row.type === 'income' ? '+' : '-' }} {{ formatMoney(row.amount, 'UZS') }}
                                        </div>
                                        <div class="mt-1">
                                            <span v-if="row.type === 'income'" class="px-1.5 py-0.5 bg-green-50 text-green-700 rounded text-[9px] font-bold border border-green-200 uppercase tracking-wider">Kirim</span>
                                            <span v-else class="px-1.5 py-0.5 bg-red-50 text-red-700 rounded text-[9px] font-bold border border-red-200 uppercase tracking-wider">Chiqim</span>
                                        </div>
                                    </td>
                                    <td class="p-2.5 font-sans">
                                        <span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg font-bold text-xs inline-block">
                                            📁 {{ row.category_name || 'Obyekt Xarajati' }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 font-sans">
                                        <span v-if="row.matched_counterparty_name" class="px-2.5 py-1 bg-purple-50 text-purple-700 border border-purple-200 rounded-lg font-bold text-xs inline-block">
                                            👤 {{ row.matched_counterparty_name }}
                                        </span>
                                        <span v-else class="text-slate-400 font-sans text-xs italic">
                                            Umumiy kassa xarajati
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer buttons -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="backToImportStep1" class="btn-cancel px-5 py-2 border border-slate-200 rounded-xl text-slate-700 font-bold text-xs hover:bg-slate-50 transition-colors" :disabled="isImporting">Orqaga</button>
                        <button type="button" @click="saveImportMappedData" :disabled="isImporting" class="btn-save px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold text-xs transition-colors shadow-md flex items-center gap-2">
                            <span>Importni tasdiqlash</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Uploaded Files History Section -->
                <div class="mt-6 pt-6 border-t border-slate-100">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3">Yuklangan Fayllar Tarixi</h4>
                    <div v-if="imports && imports.length > 0" class="overflow-x-auto max-h-48 border border-slate-100 rounded-xl">
                        <table class="w-full text-left text-[11px] font-mono">
                            <thead class="bg-slate-50 text-slate-500 uppercase border-b border-slate-200">
                                <tr>
                                    <th class="p-2">Fayl Nomi</th>
                                    <th class="p-2">Sana</th>
                                    <th class="p-2">Hisob / Obyekt</th>
                                    <th class="p-2 text-center">Soni</th>
                                    <th class="p-2">Izoh</th>
                                    <th class="p-2 text-center">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="imp in imports" :key="imp.id" class="hover:bg-slate-50 transition-colors">
                                    <td class="p-2 text-slate-900 font-bold max-w-[120px] truncate" :title="imp.file_name">
                                        {{ imp.file_name }}
                                    </td>
                                    <td class="p-2 text-slate-500">
                                        {{ new Date(imp.created_at).toLocaleDateString() }}
                                    </td>
                                    <td class="p-2 text-slate-700">
                                        <div>{{ imp.account?.name || 'Nomalum' }}</div>
                                        <div class="text-[9px] text-slate-400 font-bold">{{ imp.building?.name || 'Nomalum' }}</div>
                                    </td>
                                    <td class="p-2 text-center text-slate-900 font-bold">
                                        {{ imp.row_count }}
                                    </td>
                                    <td class="p-2 text-slate-500 max-w-[100px] truncate" :title="imp.comment">
                                        {{ imp.comment || '-' }}
                                    </td>
                                    <td class="p-2 text-center">
                                        <button @click.stop="confirmRollbackImport(imp.id)" class="px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded text-[9px] font-bold transition-all border border-red-200" title="Importni bekor qilish">
                                            Bekor qilish
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-4 bg-slate-50/50 rounded-xl border border-dashed border-slate-200 text-slate-400 text-xs">
                        Hozircha importlar tarixi mavjud emas.
                    </div>
                </div>
            </div>
        </Modal>

        <!-- QARZ OLISH MODAL (2-rasm) -->
        <Modal :show="showTakeDebtModal" @close="showTakeDebtModal = false" maxWidth="lg">
            <div class="p-6 font-mono text-xs space-y-5">
                <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <span>💳</span> QARZ KASSA OLISH (YANGI QARZ)
                    </h3>
                    <button type="button" @click="showTakeDebtModal = false" class="text-gray-400 hover:text-gray-600 font-bold">✕</button>
                </div>

                <form @submit.prevent="submitTakeDebt" class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">1. Qarz kassa nomi (Qarz beruvchi FISH)</label>
                        <input v-model="takeDebtForm.name" type="text" placeholder="Masalan: FARXOD AKA NEMATOV" class="w-full bg-white border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-purple-500 font-bold" required />
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">2. Kirim kassani tanlash (Ota kassa)</label>
                        <select v-model="takeDebtForm.parent_id" class="w-full bg-white border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-purple-500 font-bold" required>
                            <option value="" disabled>Chiqim qilingan/kirim bo'ladigan kassani tanlang</option>
                            <option v-for="r in registers" :key="r.id" :value="r.id">
                                {{ r.name }} ({{ formatMoney(r.balance_uzs, 'UZS') }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">3. Olingan sana</label>
                            <input v-model="takeDebtForm.start_date" type="date" class="w-full bg-white border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-purple-500 font-bold" required />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">4. Olingan summa (UZS)</label>
                            <input v-model.number="takeDebtForm.total_amount" type="number" placeholder="200000000" class="w-full bg-white border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-purple-500 font-bold" required />
<div v-if="takeDebtForm.total_amount" class="text-xs text-blue-600 mt-1 font-bold tracking-wider">{{ new Intl.NumberFormat('uz-UZ').format(takeDebtForm.total_amount) }} UZS</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">5. Bino / Obyekt</label>
                        <select v-model="takeDebtForm.building_id" class="w-full bg-white border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-purple-500 font-bold">
                            <option value="">Bino tanlanmagan (Umumiy)</option>
                            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">6. Izoh</label>
                        <textarea v-model="takeDebtForm.description" rows="2" placeholder="Moliyaviy yordam deb..." class="w-full bg-white border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-purple-500 font-mono"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-gray-200">
                        <button type="button" @click="showTakeDebtModal = false" class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-xs uppercase">Bekor qilish</button>
                        <button type="submit" :disabled="takeDebtForm.processing" class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs uppercase shadow-sm">
                            {{ takeDebtForm.processing ? 'Saqlanmoqda...' : 'Qarz Kassa Yaratish' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- QARZ QAYTARISH MODAL -->
        <Modal :show="showRepayDebtModal" @close="showRepayDebtModal = false" maxWidth="lg">
            <div class="p-6 font-mono text-xs space-y-5">
                <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <span>💵</span> QARZNI QAYTARISH
                    </h3>
                    <button type="button" @click="showRepayDebtModal = false" class="text-gray-400 hover:text-gray-600 font-bold">✕</button>
                </div>

                <form @submit.prevent="submitRepayDebt" class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">1. Qarz beruvchi (Qarz kassa)</label>
                        <select v-model="repayDebtForm.to_account_id" class="w-full bg-white border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-emerald-500 font-bold" required>
                            <option value="" disabled>Qarz beruvchini tanlang</option>
                            <option v-for="q in debtRegistersSummary" :key="q.id" :value="q.id">
                                {{ q.name }} (Qoldiq: {{ formatMoney(q.qoldiqRaw) }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">2. Qarz qaytarilgan kassa / manba</label>
                        <select v-model="repayDebtForm.from_account_id" class="w-full bg-white border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-emerald-500 font-bold" required>
                            <option value="" disabled>Chiqim kassani tanlang</option>
                            <option v-for="r in registers" :key="r.id" :value="r.id">
                                {{ r.name }} (Balans: {{ formatMoney(r.balance_uzs, 'UZS') }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">3. Qaytarilgan sana</label>
                            <input v-model="repayDebtForm.transaction_date" type="date" class="w-full bg-white border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-emerald-500 font-bold" required />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">4. Qaytarilgan summa (UZS)</label>
                            <input v-model.number="repayDebtForm.sent_amount" type="number" placeholder="171840000" class="w-full bg-white border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-emerald-500 font-bold" required />
<div v-if="repayDebtForm.sent_amount" class="text-xs text-blue-600 mt-1 font-bold tracking-wider">{{ new Intl.NumberFormat('uz-UZ').format(repayDebtForm.sent_amount) }} UZS</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">5. Izoh</label>
                        <textarea v-model="repayDebtForm.notes" rows="2" placeholder="Masalan: Podvaldan do'kon berildi" class="w-full bg-white border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-emerald-500 font-mono"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-gray-200">
                        <button type="button" @click="showRepayDebtModal = false" class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-xs uppercase">Bekor qilish</button>
                        <button type="submit" :disabled="repayDebtForm.processing" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs uppercase shadow-sm">
                            {{ repayDebtForm.processing ? 'Saqlanmoqda...' : 'To\'lovni Saqlash' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- QARZ BERGANLARNING ICHKI HISOB-KITOBI MODAL (4-rasm) -->
        <Modal :show="showDebtLedgerModal" @close="showDebtLedgerModal = false" maxWidth="4xl">
            <div class="p-6 font-mono text-xs space-y-5">
                <div class="flex justify-between items-center pb-4 border-b border-gray-200">
                    <div>
                        <h2 class="text-base font-bold text-gray-900 uppercase tracking-wider">
                            {{ selectedDebtRegister?.name }} - ICHKI HISOB-KITOBI
                        </h2>
                        <p class="text-[11px] text-gray-500 font-sans mt-0.5">Olingan va qaytarilgan barcha mablag'lar harakati tarixi</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a :href="route('admin.finance.debt_ledger.export.excel', { register_id: selectedDebtRegister?.id, building_id: debtLedgerBuildingFilter, start_date: debtLedgerStartDate, end_date: debtLedgerEndDate })" 
                           target="_blank"
                           class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition-all shadow-sm flex items-center gap-1.5">
                            📥 Excelga yuklash
                        </a>
                        <button @click="showDebtLedgerModal = false" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-500 font-bold flex items-center justify-center">✕</button>
                    </div>
                </div>

                <!-- Filters Bar -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-bold mb-1">Bino / Obyekt</label>
                        <select v-model="debtLedgerBuildingFilter" class="bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-xs w-full">
                            <option value="">Barcha obyektlar</option>
                            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-bold mb-1">Boshlanish sanasi</label>
                        <input v-model="debtLedgerStartDate" type="date" class="bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-xs w-full" />
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-bold mb-1">Tugash sanasi</label>
                        <input v-model="debtLedgerEndDate" type="date" class="bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-xs w-full" />
                    </div>
                </div>

                <!-- Table (4-rasm style) -->
                <div class="overflow-x-auto border border-gray-300 rounded-xl">
                    <table class="w-full text-left text-xs font-mono border-collapse">
                        <thead class="bg-gray-100 text-gray-800 font-bold border-b border-gray-300 text-center uppercase text-[11px]">
                            <tr>
                                <th class="p-3 border-r border-gray-300 w-24">Sana</th>
                                <th class="p-3 border-r border-gray-300 w-36">Olingan summa</th>
                                <th class="p-3 border-r border-gray-300 w-36">Qaytarilgan summa</th>
                                <th class="p-3 border-r border-gray-300">Qarz olingan kassa</th>
                                <th class="p-3 border-r border-gray-300">Qarz qaytarilgan kassa</th>
                                <th class="p-3">Izoh</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="row in filteredDebtLedgerRows" :key="row.id" class="hover:bg-gray-50">
                                <td class="p-3 border-r border-gray-200 text-center font-bold text-gray-700 whitespace-nowrap">{{ row.formattedDate }}</td>
                                <td class="p-3 border-r border-gray-200 text-right font-bold text-gray-900 whitespace-nowrap">{{ row.takenAmount > 0 ? formatMoney(row.takenAmount) : '' }}</td>
                                <td class="p-3 border-r border-gray-200 text-right font-bold text-gray-900 whitespace-nowrap">{{ row.repaidAmount > 0 ? formatMoney(row.repaidAmount) : '' }}</td>
                                <td class="p-3 border-r border-gray-200 text-gray-700">{{ row.takenKassa || '—' }}</td>
                                <td class="p-3 border-r border-gray-200 text-gray-700">{{ row.repaidKassa || '—' }}</td>
                                <td class="p-3 text-gray-600 text-[11px]">{{ row.description || '—' }}</td>
                            </tr>
                            <tr v-if="!filteredDebtLedgerRows.length">
                                <td colspan="6" class="p-8 text-center text-gray-400">Tranzaksiyalar topilmadi.</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-100 font-bold border-t-2 border-gray-300">
                            <tr>
                                <td class="p-3 border-r border-gray-300 font-bold uppercase text-gray-900">Jami</td>
                                <td class="p-3 border-r border-gray-300 text-right font-bold text-gray-900">{{ formatMoney(debtLedgerTotalTaken) }}</td>
                                <td class="p-3 border-r border-gray-300 text-right font-bold text-gray-900">{{ formatMoney(debtLedgerTotalRepaid) }}</td>
                                <td colspan="2" class="p-3 border-r border-gray-300"></td>
                                <td class="p-3 text-right font-bold text-sm" :class="debtLedgerNetQoldiq > 0 ? 'text-red-600' : (debtLedgerNetQoldiq < 0 ? 'text-emerald-600' : 'text-gray-900')">
                                    <span v-if="debtLedgerNetQoldiq > 0">- {{ formatMoney(debtLedgerNetQoldiq) }}</span>
                                    <span v-else-if="debtLedgerNetQoldiq < 0">{{ formatMoney(Math.abs(debtLedgerNetQoldiq)) }}</span>
                                    <span v-else>0</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </Modal>
    </component>
</template>

<style>
/* CSS overrides to force a premium, clean light theme matching the mockup exactly */
dialog.quick-transaction-modal-override,
.quick-transaction-modal-override {
    --modal-bg-color: #ffffff !important;
    --modal-text-color: #0f172a !important; /* slate-900 */
    --glass-input-bg: #f8fafc !important; /* slate-50 */
    --glass-input-border: #e2e8f0 !important; /* slate-200 */
    --glass-input-text: #0f172a !important;
    --glass-input-placeholder: #94a3b8 !important; /* slate-400 */
    --label-color: #475569 !important; /* slate-600 */
}

dialog.quick-transaction-modal-override .theme-modal {
    background-color: #ffffff !important;
    color: #0f172a !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08) !important;
}

dialog.quick-transaction-modal-override .theme-modal h1,
dialog.quick-transaction-modal-override .theme-modal h2,
dialog.quick-transaction-modal-override .theme-modal h3,
dialog.quick-transaction-modal-override .theme-modal h4,
dialog.quick-transaction-modal-override .theme-modal p,
dialog.quick-transaction-modal-override .theme-modal span,
dialog.quick-transaction-modal-override .theme-modal label,
dialog.quick-transaction-modal-override .theme-modal th,
dialog.quick-transaction-modal-override .theme-modal td {
    color: #0f172a !important;
}

dialog.quick-transaction-modal-override .theme-modal label {
    color: #475569 !important;
}

dialog.quick-transaction-modal-override .theme-modal input,
dialog.quick-transaction-modal-override .theme-modal select,
dialog.quick-transaction-modal-override .theme-modal textarea {
    background-color: #f8fafc !important;
    color: #0f172a !important;
    border-color: #e2e8f0 !important;
    border-radius: 0.75rem !important; /* rounded-xl */
    font-size: 0.75rem !important;
    transition: all 0.2s ease-in-out !important;
}

dialog.quick-transaction-modal-override .theme-modal input:hover,
dialog.quick-transaction-modal-override .theme-modal select:hover,
dialog.quick-transaction-modal-override .theme-modal textarea:hover {
    border-color: #cbd5e1 !important; /* slate-300 */
}

dialog.quick-transaction-modal-override .theme-modal input:focus,
dialog.quick-transaction-modal-override .theme-modal select:focus,
dialog.quick-transaction-modal-override .theme-modal textarea:focus {
    background-color: #ffffff !important;
    border-color: #f97316 !important; /* orange-500 */
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15) !important;
    outline: none !important;
}

dialog.quick-transaction-modal-override .theme-modal select option {
    background-color: #ffffff !important;
    color: #0f172a !important;
}

dialog.quick-transaction-modal-override .theme-modal .bg-gray-50,
dialog.quick-transaction-modal-override .theme-modal .bg-slate-50 {
    background-color: #f8fafc !important;
    border-color: #e2e8f0 !important;
}

dialog.quick-transaction-modal-override .theme-modal .bg-orange-50 {
    background-color: #fff7ed !important;
    border-color: #ffedd5 !important;
    color: #ea580c !important;
}

dialog.quick-transaction-modal-override .theme-modal .btn-cancel {
    background-color: #ffffff !important;
    border-color: #e2e8f0 !important;
    color: #475569 !important;
    transition: all 0.2s ease-in-out !important;
}

dialog.quick-transaction-modal-override .theme-modal .btn-cancel:hover {
    background-color: #f8fafc !important;
    border-color: #cbd5e1 !important;
    color: #1e293b !important;
}

dialog.quick-transaction-modal-override .theme-modal .btn-save {
    background-color: #f97316 !important; /* orange-500 */
    color: #ffffff !important;
    border: none !important;
    transition: all 0.2s ease-in-out !important;
}

dialog.quick-transaction-modal-override .theme-modal .btn-save:hover {
    background-color: #ea580c !important; /* orange-600 */
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2) !important;
}

/* Styled upload button */
dialog.quick-transaction-modal-override .theme-modal .btn-upload {
    background-color: #ffffff !important;
    border-color: #e2e8f0 !important;
    color: #475569 !important;
    transition: all 0.2s ease-in-out !important;
}

dialog.quick-transaction-modal-override .theme-modal .btn-upload:hover {
    background-color: #f8fafc !important;
    border-color: #cbd5e1 !important;
    color: #1e293b !important;
}
</style>
