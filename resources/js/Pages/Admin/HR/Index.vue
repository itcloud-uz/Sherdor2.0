<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import OwnerLedgerModal from '@/Components/Ledger/OwnerLedgerModal.vue';
import BrigadeLedgerModal from '@/Components/Ledger/BrigadeLedgerModal.vue';


const props = defineProps({
    users: Array,
    attendanceLogs: Array,
    workers: Array,
    machineries: Array,
    buildings: Array,
    brigades: Array,
    machineOwners: Array,
    unpaidLogs: Array,
    unpaidTotal: [Number, String],
    payrolls: Array,
    auth: Object
});

const isAdmin = computed(() => {
    return props.auth?.user?.roles?.some(r => r.name === 'Admin' || r.name === 'Super_Admin') || false;
});


const filteredWorkers = computed(() => {
    let result = props.workers || [];
    return result;
});



const activeTab = ref(0);
const isProcessing = ref(false);

const tabs = [
    { id: 0, name: 'user_management', icon: '👥' },
    { id: 1, name: 'face_id_hub', icon: '👁️' },
    { id: 2, name: 'attendance_logs', icon: '⏱️' },
    { id: 3, name: 'machinery', icon: '🚜' },
    { id: 4, name: 'timesheets', icon: '📝' },
    { id: 5, name: 'payroll', icon: '💸' }
];


// --- BRIGADE STATE & METHODS ---
const showBrigadeForm = ref(false);
const isBrigadeEditMode = ref(false);
const brigadeForm = ref({ id: null, name: '', foreman_name: '', phone: '' });
const activeBrigadeId = ref(null);

function openBrigadeForm(b = null) {
    if (b) {
        isBrigadeEditMode.value = true;
        brigadeForm.value = { id: b.id, name: b.name, foreman_name: b.foreman_name, phone: b.phone || '' };
    } else {
        isBrigadeEditMode.value = false;
        brigadeForm.value = { id: null, name: '', foreman_name: '', phone: '' };
    }
    showBrigadeForm.value = true;
}

function saveBrigade() {
    isProcessing.value = true;
    if (isBrigadeEditMode.value) {
        router.put(route('admin.hr.brigade.update', brigadeForm.value.id), brigadeForm.value, {
            onSuccess: () => { isProcessing.value = false; showBrigadeForm.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.hr.brigade.store'), brigadeForm.value, {
            onSuccess: () => { isProcessing.value = false; showBrigadeForm.value = false; },
            onError: () => isProcessing.value = false
        });
    }
}


async function openBrigadeLedger(brigade) {
    activeBrigadeForLedger.value = brigade;
    try {
        const response = await fetch(route('admin.hr.brigade.transactions', brigade.id));
        if (!response.ok) {
            const errText = await response.text();
            alert("Server xatosi: " + response.status + " - " + errText.substring(0, 100));
            return;
        }
        const data = await response.json();
        
        // Merge and sort
        const items = [];
        data.logs.forEach(l => {
            const isHourly = l.worker?.salary_type === 'hourly' || true;
            items.push({ 
                date: new Date(l.date), 
                desc: `Ish (Obyekt: ${l.building?.name || "Noma'lum"}) - ${l.hours_worked || l.volume_done} hajm/soat`, 
                amount: `+ ${l.calculated_cost}` 
            });
        });
        data.transactions.forEach(t => {
            items.push({ 
                date: new Date(t.date), 
                desc: `${t.type === 'salary_accrual' ? 'Kirim' : (t.type === 'penalty' ? 'Ushlanma (Jarima)' : 'To\'lov / Avans')} (${t.description || ''})`, 
                amount: t.type === 'salary_accrual' ? `+ ${t.amount}` : `- ${t.amount}` 
            });
        });
        
        items.sort((a, b) => a.date - b.date);
        
        brigadeLedgerData.value = {
            balance: data.balance,
            items: items
        };
        showBrigadeLedgerModal.value = true;
    } catch(err) {
        console.error(err);
        alert("Ma'lumotlarni yuklashda xatolik!");
    }
}

async function submitBrigadeLedger() {
    if (!activeBrigadeForLedger.value) return;
    try {
        const response = await fetch(route('admin.hr.brigade.transaction.store', activeBrigadeForLedger.value.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify(brigadeLedgerForm.value)
        });
        if (response.ok) {
            brigadeLedgerForm.value = { amount: '', description: '', type: 'payment', date: new Date().toISOString().split('T')[0] };
            openBrigadeLedger(activeBrigadeForLedger.value); // refresh
        } else {
            alert('Tranzaksiyani saqlashda xatolik!');
        }
    } catch(err) {
        console.error(err);
        alert('Tranzaksiyani saqlashda xatolik!');
    }
}

function saveBrigadeTransaction() {
    if (!brigadeLedgerForm.value.amount || !brigadeLedgerForm.value.date || !brigadeLedgerForm.value.type) {
        alert("Sana, tur va summani kiriting.");
        return;
    }
    isProcessing.value = true;
    router.post(route('admin.hr.brigade.transaction.store', activeBrigadeForLedger.value.id), brigadeLedgerForm.value, {
        onSuccess: () => {
            isProcessing.value = false;
            brigadeLedgerForm.value = { amount: '', description: '', type: 'payment', date: new Date().toISOString().split('T')[0] };
            openBrigadeLedger(activeBrigadeForLedger.value); // refresh data
        }
    });
}

function exportBrigadeLedger() {
    if(activeBrigadeForLedger.value) {
        window.location.href = route('admin.hr.brigade.ledger.export', activeBrigadeForLedger.value.id);
    }
}

function deleteBrigade(id) {
    if (confirm("Brigadani o'chirmoqchimisiz?")) {
        router.delete(route('admin.hr.brigade.destroy', id));
    }
}

// --- MACHINE OWNER STATE & METHODS ---
const showOwnerForm = ref(false);
const isOwnerEditMode = ref(false);
const ownerForm = ref({ id: null, name: '', phone: '' });
const activeOwnerId = ref(null);

function openOwnerForm(o = null) {
    if (o) {
        isOwnerEditMode.value = true;
        ownerForm.value = { id: o.id, name: o.name, phone: o.phone || '' };
    } else {
        isOwnerEditMode.value = false;
        ownerForm.value = { id: null, name: '', phone: '' };
    }
    showOwnerForm.value = true;
}

function saveOwner() {
    isProcessing.value = true;
    if (isOwnerEditMode.value) {
        router.put(route('admin.hr.machine_owner.update', ownerForm.value.id), ownerForm.value, {
            onSuccess: () => { isProcessing.value = false; showOwnerForm.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.hr.machine_owner.store'), ownerForm.value, {
            onSuccess: () => { isProcessing.value = false; showOwnerForm.value = false; },
            onError: () => isProcessing.value = false
        });
    }
}

function deleteOwner(id) {
    if (confirm("Texnika egasini o'chirmoqchimisiz?")) {
        router.delete(route('admin.hr.machine_owner.destroy', id));
    }
}


// User Management Modal State
const showUserModal = ref(false);
const userForm = ref({
    name: '',
    email: '',
    password: '',
    role: 'Operator',
    pinfl: '',
    passport: '',
    salary_rate: ''
});
const isEditMode = ref(false);

function openUserModal(user = null) {
    if (user) {
        isEditMode.value = true;
        userForm.value = {
            id: user.id,
            name: user.name,
            email: user.email,
            password: '',
            role: user.roles?.[0]?.name || 'Operator',
            pinfl: user.pinfl || '',
            passport: user.passport || '',
            salary_rate: user.salary_rate || ''
        };
    } else {
        isEditMode.value = false;
        userForm.value = { name: '', email: '', password: '', role: 'Operator', pinfl: '', passport: '', salary_rate: '' };
    }
    showUserModal.value = true;
}

function saveUser() {
    isProcessing.value = true;
    if (isEditMode.value) {
        router.post(route('admin.hr.users.update', userForm.value.id), userForm.value, {
            onSuccess: () => { showUserModal.value = false; isProcessing.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.hr.users.store'), userForm.value, {
            onSuccess: () => { showUserModal.value = false; isProcessing.value = false; },
            onError: () => isProcessing.value = false
        });
    }
}

function deleteUser(id) {
    if(!confirm("Haqiqatan ham ushbu foydalanuvchini o'chirmoqchimisiz?")) return;
    router.delete(route('admin.hr.users.delete', id));
}



// TAB 1 Forms
const workerForm = ref({ id: null, name: '', phone: '', role: 'builder', salary_type: 'piece_rate', metric_type: 'dona', base_rate: '', building_id: '', floor_id: '', unit_id: '', salary_periods: [] });
const filteredMachineries = computed(() => {
    if (!logForm.value.machine_owner_id) return props.machineries;
    return props.machineries.filter(m => m.machine_owner_id === parseInt(logForm.value.machine_owner_id));
});
const isWorkerEditMode = ref(false);
const showWorkerForm = ref(false);

const isGuardEditMode = ref(false);
const showGuardForm = ref(false);
const guardForm = ref({ id: null, name: '', phone: '', role: 'guard', salary_type: 'fixed_monthly', base_rate: '', salary_periods: [] });

function openGuardForm(g = null) {
    if (g && g.id) {
        isGuardEditMode.value = true;
        guardForm.value = {
            id: g.id,
            name: g.name,
            phone: g.phone || '',
            role: 'guard',
            salary_type: 'fixed_monthly',
            base_rate: g.base_rate || g.rate || '',
            salary_periods: [] // we don't load existing periods in the edit form natively, but you can if needed
        };
    } else {
        isGuardEditMode.value = false;
        guardForm.value = { id: null, name: '', phone: '', role: 'guard', salary_type: 'fixed_monthly', base_rate: '', salary_periods: [] };
    }
    showGuardForm.value = true;
}

function cancelGuardEdit() {
    isGuardEditMode.value = false;
    showGuardForm.value = false;
    guardForm.value = { id: null, name: '', phone: '', role: 'guard', salary_type: 'fixed_monthly', base_rate: '', salary_periods: [] };
}

function saveGuard() {
    isProcessing.value = true;
    if (isGuardEditMode.value) {
        router.post(route('admin.hr.worker.update', guardForm.value.id), guardForm.value, {
            preserveScroll: true,
            onSuccess: () => { isProcessing.value = false; cancelGuardEdit(); },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.hr.worker.store'), guardForm.value, {
            preserveScroll: true,
            onSuccess: () => { isProcessing.value = false; cancelGuardEdit(); },
            onError: () => isProcessing.value = false
        });
    }
}

function addGuardSalaryPeriod() {
    guardForm.value.salary_periods.push({ start_month: '', end_month: '', salary: guardForm.value.base_rate || '' });
}
function removeGuardSalaryPeriod(index) {
    guardForm.value.salary_periods.splice(index, 1);
}

function openWorkerEdit(w) {
    isWorkerEditMode.value = !!w.id;
    showWorkerForm.value = true;
    workerForm.value = {
        id: w.id,
        name: w.name,
        phone: w.phone || '',
        role: w.role,
        salary_type: w.salary_type || 'piece_rate',
        metric_type: w.metric_type || 'dona',
        base_rate: w.base_rate || w.rate || '',
        brigade_id: w.brigade_id || '',
        building_id: w.building_id || '',
        floor_id: w.floor_id || '',
        unit_id: w.unit_id || '',
        salary_periods: []
    };
    // Scroll to form (optional) or just populate it
}



function addSalaryPeriod() {
    workerForm.value.salary_periods.push({ start_month: '', end_month: '', salary: workerForm.value.base_rate || '' });
}
function removeSalaryPeriod(index) {
    workerForm.value.salary_periods.splice(index, 1);
}


const showGuardHistoryModal = ref(false);
const activeGuardHistory = ref(null);

function viewGuardHistory(guard) {
    activeGuardHistory.value = guard;
    showGuardHistoryModal.value = true;
}


function cancelWorkerEdit() {
    isWorkerEditMode.value = false;
    showWorkerForm.value = false;
    workerForm.value = { id: null, name: '', phone: '', role: 'builder', salary_type: 'piece_rate', metric_type: 'dona', base_rate: '', building_id: '', floor_id: '', unit_id: '', salary_periods: [] };
}

function saveWorker() {
    isProcessing.value = true;
    if (isWorkerEditMode.value) {
        router.post(route('admin.hr.worker.update', workerForm.value.id), workerForm.value, {
            preserveScroll: true,
            onSuccess: () => { isProcessing.value = false; cancelWorkerEdit(); },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.hr.worker.store'), workerForm.value, {
            preserveScroll: true,
            onSuccess: () => { isProcessing.value = false; cancelWorkerEdit(); },
            onError: () => isProcessing.value = false
        });
    }
}

function deleteWorker(id) {
    if (confirm("Haqiqatan ham ushbu xodimni o'chirmoqchimisiz?")) {
        router.delete(route('admin.hr.worker.destroy', id));
    }
}

// Ledger Modal
const showLedgerModal = ref(false);
const ledgerWorker = ref(null);
const ledgerData = ref({ balance: 0, category_balances: {}, transactions: [], logs: [] });
const ledgerForm = ref({ type: 'payment', amount: '', description: '', work_category: '', date: new Date().toISOString().split('T')[0] });
const workerLogForm = ref({ building_id: '', measurement_unit: '', work_category: '', volume: '', custom_rate: '', date: new Date().toISOString().split('T')[0] });

const workCategoryMap = {
    'dona': ["G'isht terish", "Blok terish", "Eshik o'rnatish", "Deraza o'rnatish", "Boshqa"],
    'kv_m': ["Kafel terish", "Sement suvoq", "Malyarka", "Tyopliy pol", "Laminat", "Boshqa"],
    'kub_m': ["Beton quyish", "Tuproq qazish", "Boshqa"],
    'metr': ["Truba tortish", "Sim tortish", "Plintus", "Boshqa"],
    'soat': ["Vaqtbay ish", "Boshqa"],
    'kun': ["Kunlik ish", "Boshqa"]
};

const availableWorkCategories = computed(() => {
    return workCategoryMap[workerLogForm.value.measurement_unit] || [];
});

function openLedger(w) {
    ledgerWorker.value = w;
    fetchLedger(w.id);
    showLedgerModal.value = true;
}

function fetchLedger(id) {
    fetch(route('admin.hr.worker.ledger', id))
        .then(res => res.json())
        .then(data => {
            ledgerData.value = data;
        });
}

function saveLedgerTransaction() {
    isProcessing.value = true;
    router.post(route('admin.hr.worker.transaction.store', ledgerWorker.value.id), ledgerForm.value, {
        onSuccess: () => {
            isProcessing.value = false;
            ledgerForm.value.amount = '';
            ledgerForm.value.description = '';
            fetchLedger(ledgerWorker.value.id);
            // Wait for Inertia to finish then update global workers array if needed (Inertia reloads props automatically on post)
        },
        onError: () => isProcessing.value = false
    });
}

function saveWorkerLog() {
    let payload = {
        type: 'worker',
        worker_id: ledgerWorker.value.id,
        building_id: workerLogForm.value.building_id,
        measurement_unit: workerLogForm.value.measurement_unit,
        work_category: workerLogForm.value.work_category,
        date: workerLogForm.value.date
    };
    if (ledgerWorker.value.salary_type === 'hourly') {
        payload.hours_worked = workerLogForm.value.volume;
    } else {
        payload.volume_done = workerLogForm.value.volume;
    }
    if (workerLogForm.value.custom_rate) {
        payload.custom_rate = workerLogForm.value.custom_rate;
    }

    isProcessing.value = true;
    router.post(route('admin.hr.log.store'), payload, {
        onSuccess: () => {
            isProcessing.value = false;
            workerLogForm.value.volume = '';
            workerLogForm.value.custom_rate = '';
            workerLogForm.value.measurement_unit = '';
            workerLogForm.value.work_category = '';
            fetchLedger(ledgerWorker.value.id);
        },
        onError: () => isProcessing.value = false
    });
}

// User Ledger Modal
const showUserLedgerModal = ref(false);
const ledgerUser = ref(null);
const userLedgerData = ref({ balance: 0, transactions: [] });
const userLedgerForm = ref({ type: 'salary_accrual', amount: '', description: '', date: new Date().toISOString().split('T')[0] });

function openUserLedger(u) {
    ledgerUser.value = u;
    fetchUserLedger(u.id);
    showUserLedgerModal.value = true;
}

function fetchUserLedger(id) {
    fetch(route('admin.hr.users.ledger', id))
        .then(res => res.json())
        .then(data => {
            userLedgerData.value = data;
        });
}

function saveUserLedgerTransaction() {
    isProcessing.value = true;
    router.post(route('admin.hr.users.transaction.store', ledgerUser.value.id), userLedgerForm.value, {
        onSuccess: () => {
            isProcessing.value = false;
            userLedgerForm.value.amount = '';
            userLedgerForm.value.description = '';
            fetchUserLedger(ledgerUser.value.id);
        },
        onError: () => isProcessing.value = false
    });
}

const showMachineryForm = ref(false);
const machineryForm = ref({ name: '', owner_name: '', machinery_type: '', driver_name: '', payment_type: 'hourly', license_plate: '', cost_per_hour: '' });

function addMachinery() {
    isProcessing.value = true;
    router.post(route('admin.hr.machinery.store'), machineryForm.value, {
        onSuccess: () => { isProcessing.value = false; machineryForm.value = { name: '', owner_name: '', machinery_type: '', driver_name: '', payment_type: 'hourly', license_plate: '', cost_per_hour: '' }; },
        onError: () => isProcessing.value = false
    });
}

const editMachineryId = ref(null);
const editMachineryForm = useForm({ name: '', machine_owner_id: '', machinery_type: '', driver_name: '', payment_type: 'hourly', license_plate: '', cost_per_hour: '' });

function startEditMachinery(m) {
    editMachineryId.value = m.id;
    editMachineryForm.name = m.name;
    editMachineryForm.machine_owner_id = m.machine_owner_id;
    editMachineryForm.machinery_type = m.machinery_type;
    editMachineryForm.driver_name = m.driver_name;
    editMachineryForm.payment_type = m.payment_type;
    editMachineryForm.license_plate = m.license_plate;
    editMachineryForm.cost_per_hour = m.cost_per_hour;
}

function updateMachinery() {
    isProcessing.value = true;
    editMachineryForm.post(route('admin.hr.machinery.update', editMachineryId.value), {
        onSuccess: () => {
            isProcessing.value = false;
            editMachineryId.value = null;
        },
        onError: () => isProcessing.value = false
    });
}

function deleteMachinery(id) {
    if(confirm("Ushbu texnikani haqiqatan ham o'chirasizmi?")) {
        router.delete(route('admin.hr.machinery.destroy', id));
    }
}

const showOwnerLedgerModal = ref(false);
const ownerLedgerData = ref({ balance: 0, logs: [], transactions: [] });
const activeOwnerForLedger = ref(null);
const ownerLedgerForm = ref({ amount: '', description: '', date: new Date().toISOString().split('T')[0] });


const showBrigadeLedgerModal = ref(false);
const activeBrigadeForLedger = ref(null);
const brigadeLedgerData = ref({ balance: 0, logs: [], transactions: [] });
const brigadeLedgerForm = ref({ amount: '', description: '', type: 'payment', date: new Date().toISOString().split('T')[0] });

const showMachineryLedgerModal = ref(false);
const machineryLedgerData = ref({ logs: [], transactions: [], total_earned: 0, unpaid_balance: 0 });
const activeMachinery = ref(null);
const machineryLedgerForm = ref({ amount: '', description: '', date: new Date().toISOString().split('T')[0] });
const machineryLogForm = ref({ building_id: '', volume: '', custom_rate: '', date: new Date().toISOString().split('T')[0] });

const editLogId = ref(null);
const editLogForm = ref({ hours_worked: '', volume_done: '', custom_rate: '', date: '', description: '', deductions: '' });
const editTxId = ref(null);
const editTxForm = ref({ amount: '', description: '', date: '' });

function startEditLog(log) {
    editLogId.value = log.id;
    editLogForm.value.hours_worked = log.hours_worked || '';
    editLogForm.value.volume_done = log.volume_done || '';
    editLogForm.value.custom_rate = log.rate || '';
    editLogForm.value.description = log.description || '';
    editLogForm.value.deductions = log.deductions || '';
    editLogForm.value.date = log.date ? log.date.split('T')[0] : '';
}

function submitEditLog() {
    isProcessing.value = true;
    router.put(route('admin.hr.log.update', editLogId.value), editLogForm.value, {
        onSuccess: () => {
            isProcessing.value = false;
            editLogId.value = null;
            if (showMachineryLedgerModal.value) {
                openMachineryLedger(activeMachinery.value);
            } else if (showLedgerModal.value) {
                fetchLedger(ledgerWorker.value.id);
            }
        },
        onError: () => isProcessing.value = false
    });
}

function destroyLog(id) {
    if(!confirm("Ish hajmini o'chirmoqchimisiz?")) return;
    isProcessing.value = true;
    router.delete(route('admin.hr.log.destroy', id), {
        onSuccess: () => {
            isProcessing.value = false;
            if (showMachineryLedgerModal.value) {
                openMachineryLedger(activeMachinery.value);
            } else if (showLedgerModal.value) {
                fetchLedger(ledgerWorker.value.id);
            }
        },
        onError: () => isProcessing.value = false
    });
}

function startEditTx(tx) {
    editTxId.value = tx.id;
    editTxForm.value.amount = tx.amount;
    editTxForm.value.description = tx.description || '';
    editTxForm.value.date = tx.date ? tx.date.split('T')[0] : '';
}

function submitEditTx(type) {
    isProcessing.value = true;
    router.put(route('admin.hr.transactions.update', { type: type, id: editTxId.value }), editTxForm.value, {
        onSuccess: () => {
            isProcessing.value = false;
            editTxId.value = null;
            if (type === 'machinery') openMachineryLedger(activeMachinery.value);
            else if (type === 'worker') fetchLedger(ledgerWorker.value.id);
            else if (type === 'user') fetchUserLedger(ledgerUser.value.id);
        },
        onError: () => isProcessing.value = false
    });
}

function destroyTx(type, id) {
    if(!confirm("Tranzaksiyani o'chirmoqchimisiz?")) return;
    isProcessing.value = true;
    router.delete(route('admin.hr.transactions.destroy', { type: type, id: id }), {
        onSuccess: () => {
            isProcessing.value = false;
            if (type === 'machinery') openMachineryLedger(activeMachinery.value);
            else if (type === 'worker') fetchLedger(ledgerWorker.value.id);
            else if (type === 'user') fetchUserLedger(ledgerUser.value.id);
        },
        onError: () => isProcessing.value = false
    });
}


async function openOwnerLedger(owner) {
    activeOwnerForLedger.value = owner;
    try {
        const response = await fetch(route('admin.hr.owner.transactions', owner.id));
        const data = await response.json();
        
        let allItems = [];
        if(data.logs) {
            data.logs.forEach(l => {
                allItems.push({
                    type: 'log',
                    date: l.date,
                    created_at: l.created_at,
                    item: l
                });
            });
        }
        if(data.transactions) {
            data.transactions.forEach(t => {
                allItems.push({
                    type: 'tx',
                    date: t.date,
                    created_at: t.created_at,
                    item: t
                });
            });
        }
        
        allItems.sort((a, b) => {
            let d1 = new Date(a.date);
            let d2 = new Date(b.date);
            if (d1.getTime() !== d2.getTime()) return d1 - d2;
            return new Date(a.created_at) - new Date(b.created_at);
        });
        
        ownerLedgerData.value = {
            balance: data.balance,
            items: allItems,
            owner: data.owner
        };
        showOwnerLedgerModal.value = true;
    } catch(err) {
        console.error(err);
        alert("Ma\'lumotlarni yuklashda xatolik!");
    }
}

async function openMachineryLedger(m) {
    activeMachinery.value = m;
    try {
        const response = await fetch(route('admin.hr.machinery.ledger', m.id));
        const data = await response.json();
        machineryLedgerData.value = data;
        showMachineryLedgerModal.value = true;
    } catch(err) {
        console.error(err);
        alert("Ma'lumotlarni yuklashda xatolik!");
    }
}

function saveMachineryTransaction() {
    isProcessing.value = true;
    router.post(route('admin.hr.machinery.transaction.store', activeMachinery.value.id), machineryLedgerForm.value, {
        onSuccess: () => {
            isProcessing.value = false;
            machineryLedgerForm.value.amount = '';
            machineryLedgerForm.value.description = '';
            openMachineryLedger(activeMachinery.value);
        },
        onError: () => isProcessing.value = false
    });
}

function saveMachineryLog() {
    isProcessing.value = true;
    const payload = {
        type: 'machinery',
        machinery_id: activeMachinery.value.id,
        building_id: machineryLogForm.value.building_id,
        date: machineryLogForm.value.date
    };
    if (activeMachinery.value.payment_type === 'hourly') {
        payload.hours_worked = machineryLogForm.value.volume;
    } else {
        payload.volume_done = machineryLogForm.value.volume;
    }
    if (machineryLogForm.value.custom_rate) {
        payload.custom_rate = machineryLogForm.value.custom_rate;
    }

    router.post(route('admin.hr.log.store'), payload, {
        onSuccess: () => {
            isProcessing.value = false;
            machineryLogForm.value.volume = '';
            machineryLogForm.value.custom_rate = '';
            openMachineryLedger(activeMachinery.value);
        },
        onError: () => isProcessing.value = false
    });
}

function printLedger() {
    const m = activeMachinery.value;
    const data = machineryLedgerData.value;
    
    // Combine logs and transactions
    const items = [];
    data.logs.forEach(l => {
        items.push({ date: new Date(l.date), desc: `Ish (Obyekt: ${l.building?.name || "Noma'lum"}) - ${l.hours_worked || l.volume_done} ${m.payment_type==='hourly'?'soat':'hajm'}`, amount: `- ${l.calculated_cost}` });
    });
    data.transactions.forEach(t => {
        items.push({ date: new Date(t.date), desc: `To'lov: ${t.description || 'Izohsiz'}`, amount: `+ ${t.amount}` });
    });
    items.sort((a,b) => b.date - a.date); // Sort descending
    
    let rowsHtml = items.map((i, idx) => `
        <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
            <td style="padding: 8px; border: 1px solid #ccc;">${idx+1}</td>
            <td style="padding: 8px; border: 1px solid #ccc;">${i.date.toLocaleDateString()}</td>
            <td style="padding: 8px; border: 1px solid #ccc;">${i.desc}</td>
            <td style="padding: 8px; border: 1px solid #ccc; text-align: right; font-weight: bold;">${i.amount}</td>
        </tr>
    `).join('');

    const html = `
        <html>
        <head>
            <title>Dalolatnoma (Sverka) - ${m.name}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; color: #000; background: #fff; }
                h1 { text-align: center; font-size: 20px; text-transform: uppercase; margin-bottom: 5px; }
                p.sub { text-align: center; font-size: 14px; margin-top: 0; margin-bottom: 20px; color: #555; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 12px; }
                th { background: #f0f0f0; padding: 8px; border: 1px solid #ccc; text-align: left; }
                .summary { width: 100%; border: 2px solid #000; padding: 15px; margin-bottom: 40px; box-sizing: border-box;}
                .summary div { margin-bottom: 5px; font-weight: bold; }
                .signatures { display: flex; justify-content: space-between; margin-top: 50px; }
                .sig-box { width: 40%; text-align: center; }
                .line { border-bottom: 1px solid #000; margin-top: 40px; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <h1>Hisob-kitob solishtirma dalolatnomasi (Sverka)</h1>
            <p class="sub">Yaratilgan sana: ${new Date().toLocaleDateString()}</p>
            
            <div class="summary">
                <div>TEXNIKA: ${m.machinery_type || ''} ${m.name} (${m.license_plate || 'Raqamsiz'})</div>
                <div>EGASI (Arendator): ${m.owner_name || "Noma'lum"}</div>
                <div style="margin-top:10px;">Jami Ishlagan Summa: ${data.total_earned}</div>
                <div style="color: red;">To'lanmagan Qoldiq Qarz: ${data.unpaid_balance}</div>
            </div>

            <table>
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                    <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                        <th width="5%">№</th>
                        <th width="15%">{{ $t('date_label') }}</th>
                        <th width="55%">Izoh / Obyekt</th>
                        <th width="25%" style="text-align: right;">{{ $t('amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHtml}
                </tbody>
            </table>

            <div class="signatures">
                <div class="sig-box">
                    <p><b>"BUYURTMACHI" (Kompaniya)</b></p>
                    <div class="line"></div>
                    <p>(Imzo / Muhr)</p>
                </div>
                <div class="sig-box">
                    <p><b>"BAJARUVCHI" (Texnika Egasi)</b></p>
                    <div class="line"></div>
                    <p>${m.owner_name || '_________________'}</p>
                </div>
            </div>
        </body>
        </html>
    `;
    
    const win = window.open('', '_blank');
    win.document.write(html);
    win.document.close();
    setTimeout(() => {
        win.print();
    }, 500);
}

function exportToExcel() {
    const m = activeMachinery.value;
    const data = machineryLedgerData.value;
    
    let csv = 'Sana,Izoh,Summa\n';
    
    data.logs.forEach(l => {
        csv += `${new Date(l.date).toLocaleDateString()},Ish - ${l.building?.name || "Noma'lum"},-${l.calculated_cost}\n`;
    });
    data.transactions.forEach(t => {
        csv += `${new Date(t.date).toLocaleDateString()},To'lov: ${t.description || ''},+${t.amount}\n`;
    });
    
    const blob = new Blob(["\uFEFF"+csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `Sverka_${m.name}.csv`;
    link.click();
}

// AI Log Modal
const showLogModal = ref(false);
const activeLog = ref(null);

function openLogModal(log) {
    activeLog.value = log;
    showLogModal.value = true;
}

// Manual AI Web Scanner
const showScannerModal = ref(false);
const scannerVideo = ref(null);
const isScannerActive = ref(false);
const scannerSnapshot = ref(null);
const scannerForm = useForm({
    subject_type: 'Worker',
    subject_id: '',
    type: 'entry',
    image: ''
});

async function startScanner() {
    showScannerModal.value = true;
    scannerSnapshot.value = null;
    scannerForm.reset();
    setTimeout(async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
            if (scannerVideo.value) {
                scannerVideo.value.srcObject = stream;
                isScannerActive.value = true;
            }
        } catch (err) {
            console.error("Camera error:", err);
            alert("Kameraga ulanishda xatolik. Ruxsatlarni tekshiring.");
        }
    }, 100);
}

function stopScanner() {
    if (scannerVideo.value && scannerVideo.value.srcObject) {
        scannerVideo.value.srcObject.getTracks().forEach(t => t.stop());
    }
    isScannerActive.value = false;
    scannerSnapshot.value = null;
    showScannerModal.value = false;
}

function takeScannerSnapshot() {
    if (!scannerVideo.value) return;
    const canvas = document.createElement('canvas');
    canvas.width = scannerVideo.value.videoWidth;
    canvas.height = scannerVideo.value.videoHeight;
    canvas.getContext('2d').drawImage(scannerVideo.value, 0, 0);
    scannerSnapshot.value = canvas.toDataURL('image/jpeg', 0.8);
    scannerForm.image = scannerSnapshot.value;
}

function submitManualLog() {
    scannerForm.post(route('admin.hr.manual_log.store'), {
        onSuccess: () => stopScanner(),
    });
}

// Biometric integration
const videoElement = ref(null);
const isCameraActive = ref(false);
const biometricForm = ref({ person_type: 'worker', person_id: '' });

async function startCamera() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        if (videoElement.value) {
            videoElement.value.srcObject = stream;
            isCameraActive.value = true;
        }
    } catch (err) {
        console.error("Camera error:", err);
        alert("Kameraga ulanishda xatolik. Qurilma ruxsatlarini tekshiring.");
    }
}

function stopCamera() {
    if (videoElement.value && videoElement.value.srcObject) {
        videoElement.value.srcObject.getTracks().forEach(track => track.stop());
        videoElement.value.srcObject = null;
        isCameraActive.value = false;
    }
}

function scanNewFace() {
    if (!biometricForm.value.person_id) {
        alert("Iltimos, avval xodimni tanlang.");
        return;
    }
    if (!isCameraActive.value || !videoElement.value) {
        alert("Kamera faol emas. 'Kamerani Yoqish' tugmasini bosing.");
        return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = videoElement.value.videoWidth;
    canvas.height = videoElement.value.videoHeight;
    canvas.getContext('2d').drawImage(videoElement.value, 0, 0);
    const imageData = canvas.toDataURL('image/jpeg');

    isProcessing.value = true;
    router.post(route('admin.hr.biometric.store'), {
        person_type: biometricForm.value.person_type,
        person_id: biometricForm.value.person_id,
        image: imageData
    }, {
        onSuccess: () => {
            isProcessing.value = false;
            alert("Biometrik surat muvaffaqiyatli saqlandi!");
        },
        onError: () => isProcessing.value = false
    });
}

// TAB 2 Form
const logForm = ref({
    type: 'worker',
    worker_id: '',
    machine_owner_id: '',
    machinery_id: '',
    building_id: '',
    floor_id: '',
    unit_id: '',
    hours_worked: '',
    volume_done: '',
    custom_rate: '',
    amount: '',
    target_type: 'building',
    target_id: '',
    description: '',
    deductions: '',
    date: new Date().toISOString().split('T')[0]
});

const workerFloors = computed(() => {
    if (!workerForm.value.building_id) return [];
    const b = props.buildings.find(x => x.id === parseInt(workerForm.value.building_id));
    return b ? b.floors : [];
});

const workerUnits = computed(() => {
    if (!workerForm.value.floor_id) return [];
    const f = workerFloors.value.find(x => x.id === parseInt(workerForm.value.floor_id));
    return f ? f.units : [];
});

const logFloors = computed(() => {
    if (!logForm.value.building_id) return [];
    const b = props.buildings.find(x => x.id === parseInt(logForm.value.building_id));
    return b ? b.floors : [];
});

const logUnits = computed(() => {
    if (!logForm.value.floor_id) return [];
    const f = logFloors.value.find(x => x.id === parseInt(logForm.value.floor_id));
    return f ? f.units : [];
});

const logPreviewCost = computed(() => {
    if (!logForm.value.building_id) return 0;
    let cost = 0;
    if (logForm.value.type === 'worker' && logForm.value.worker_id) {
        const w = props.workers.find(x => x.id === parseInt(logForm.value.worker_id));
        if (w) {
            if (logForm.value.custom_rate) {
                cost = (Number(logForm.value.hours_worked) || Number(logForm.value.volume_done) || 0) * Number(logForm.value.custom_rate);
            } else {
                if (w.salary_type === 'hourly') cost = (Number(logForm.value.hours_worked) || 0) * Number(w.base_rate || w.rate);
                if (w.salary_type === 'piece_rate') cost = (Number(logForm.value.volume_done) || 0) * Number(w.base_rate || w.rate);
            }
        }
    } else if (logForm.value.type === 'machinery' && logForm.value.machinery_id) {
        const m = props.machineries.find(x => x.id === parseInt(logForm.value.machinery_id));
        if (m) {
            if (logForm.value.custom_rate) {
            cost = (Number(logForm.value.hours_worked) || 0) * Number(logForm.value.custom_rate);
        } else {
            cost = (Number(logForm.value.hours_worked) || 0) * Number(m.cost_per_hour);
        }
        }
    }
    cost = cost - (Number(logForm.value.deductions) || 0);
    return cost;
});

function submitLog() {
    isProcessing.value = true;
    router.post(route('admin.hr.log.store'), logForm.value, {
        onSuccess: () => {
            isProcessing.value = false;
            logForm.value.hours_worked = '';
            logForm.value.volume_done = '';
            logForm.value.custom_rate = '';
            logForm.value.description = '';
            logForm.value.deductions = '';
            logForm.value.date = new Date().toISOString().split('T')[0];
        },
        onError: () => isProcessing.value = false
    });
}

// TAB 3 Action
function generatePayroll() {
    isProcessing.value = true;
    router.post(route('admin.hr.payroll.generate'), {}, {
        onSuccess: () => {
            isProcessing.value = false;
        },
        onError: () => {
            isProcessing.value = false;
            alert("Xatolik yuz berdi. Iltimos qaytadan urinib ko'ring.");
        }
    });
}

// Decentralized Import Modal

// Handlers for the new Ledger Components
async function submitBrigadeLedgerFromEvent(payload) {
    if (!payload.target_id) return;
    try {
        const response = await fetch(route('admin.hr.brigade.transaction.store', payload.target_id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify(payload)
        });
        if (response.ok) {
            openBrigadeLedger(activeBrigadeForLedger.value); // refresh
        } else {
            alert('Tranzaksiyani saqlashda xatolik!');
        }
    } catch(err) {
        console.error(err);
        alert('Tranzaksiyani saqlashda xatolik!');
    }
}

function exportBrigadePdf(id) {
    if (id) window.open(route('admin.hr.brigade.ledger.export', id), '_blank');
}

async function submitOwnerLedgerFromEvent(payload) {
    if (!payload.target_id) return;
    try {
        const response = await fetch(route('admin.hr.machine-owners.ledger.store', payload.target_id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify(payload)
        });
        if (response.ok) {
            openOwnerLedger(activeOwnerForLedger.value); // refresh
        } else {
            alert('Tranzaksiyani saqlashda xatolik!');
        }
    } catch(err) {
        console.error(err);
        alert('Tranzaksiyani saqlashda xatolik!');
    }
}

function exportOwnerPdf(id) {
    if (id) window.open(route('admin.hr.owner.ledger.export', id), '_blank');
}

</script>

<template>
    <Head :title="$t('hr_hub')" />
    
    <AdminLayout>


        <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- Sidebar Navigation -->
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-2xl flex flex-col gap-2 bg-gray-900/50 border border-gray-200">
                    <button 
                        v-for="tab in tabs" :key="tab.id"
                        @click="activeTab = tab.id"
                        class="w-full text-left px-6 py-4 rounded-xl text-sm font-mono tracking-widest transition-all duration-300 uppercase flex items-center gap-3"
                        :class="activeTab === tab.id ? ' text-neon-blue border border-neon-blue/30 shadow-sm font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50 border border-transparent'"
                    >
                        <span class="text-xl">{{ tab.icon }}</span> {{ $t(tab.name) }}
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="lg:col-span-3 min-h-[700px]">
                <!-- TAB 0: GLOBAL USER MANAGEMENT -->
        <div v-if="activeTab === 0" class="grid grid-cols-1 xl:grid-cols-3 sm:grid-cols-2 gap-8 animate-[fadeIn_0.5s_ease-out]">
            <!-- System Users Column -->
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-100 flex flex-col h-[700px]">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-display font-bold text-gray-900 flex items-center gap-2">
                        👥 Tizim Xodimlari
                    </h2>
                    <button @click="showUserModal = true" class="px-4 py-2 bg-blue-50 border border-blue-500 text-blue-600 text-[10px] font-mono font-bold uppercase tracking-widest rounded hover:bg-blue-600 hover:text-black transition-all shadow-sm">
                        + Tizim Xodimi
                    </button>
                </div>
                
                <div class="overflow-y-auto custom-scrollbar flex-1 relative pr-2">
                    <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="user in users" :key="user.id" class="p-4 bg-gray-900/50 border border-gray-100 rounded-2xl flex flex-col justify-between hover:border-gray-300 transition-all group shadow-[0_4px_20px_rgba(0,0,0,0.5)]">
                            <div class="flex items-start gap-3 mb-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center overflow-hidden border border-gray-200 shrink-0">
                                    <img v-if="user.photo_path" :src="'/storage/' + user.photo_path" class="w-full h-full object-cover">
                                    <span v-else class="text-lg">👤</span>
                                </div>
                                <div>
                                    <h3 class="text-xs font-bold text-gray-900 mb-0.5 group-hover:text-blue-600 transition-colors line-clamp-1" :title="user.name">{{ user.name }}</h3>
                                    <p class="text-[9px] font-mono text-gray-500 line-clamp-1" :title="user.email">{{ user.email }}</p>
                                    <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[8px] uppercase tracking-widest border"
                                        :class="{ 'bg-red-50 text-red-600 border-red-200': user.roles?.[0]?.name === 'Super_Admin', 'bg-blue-50 text-blue-600 border-blue-200': user.roles?.[0]?.name === 'Operator', 'bg-amber-50 text-amber-600 border-amber-200': user.roles?.[0]?.name === 'Partner', ' text-gray-500 border-gray-300': user.roles?.[0]?.name === 'Worker' || !user.roles?.length }">
                                        {{ user.roles?.[0]?.name || 'None' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-100 pt-3">
                                <p class="text-[10px] font-mono font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">{{ $currency(user.salary_rate || 0) }}</p>
                                <div class="flex gap-1.5 opacity-70 group-hover:opacity-100 transition-opacity">
                                    <button @click="openUserLedger(user)" class="w-6 h-6 flex items-center justify-center bg-neon-blue/10 text-neon-blue rounded hover:bg-neon-blue/30 transition-colors" title="Karta">💳</button>
                                    <button v-if="isAdmin" @click="openUserModal(user)" class="w-6 h-6 flex items-center justify-center text-gray-900 rounded hover:bg-white/20 transition-colors" title="Tahrirlash">✏️</button>

                                    <button v-if="isAdmin" @click="deleteUser(user.id)" class="w-6 h-6 flex items-center justify-center bg-red-50 text-red-600 rounded hover:bg-red-50 transition-colors" title="O'chirish">🗑️</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Workers Column -->
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-100 flex flex-col h-[700px]">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <h2 class="text-xl font-display font-bold text-gray-900 flex items-center gap-2">
                        👷‍♂️ Yollanma Ishchilar & Brigadalar
                    </h2>
                    
                    <button @click="showBrigadeForm = !showBrigadeForm" class="px-3 py-1.5 bg-blue-50 text-blue-600 text-[10px] font-mono tracking-widest uppercase rounded hover:bg-blue-50 transition-colors border border-blue-200">
                        {{ showBrigadeForm ? '- Yopish' : "+ Brigada Qo'shish" }}
                    </button>
                </div>

                <form v-if="showBrigadeForm" @submit.prevent="saveBrigade" class="mb-6 space-y-4 bg-gray-900/50 p-4 rounded-xl border border-gray-100 flex-shrink-0 animate-[fadeIn_0.3s_ease-out]">
                    <h3 class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">{{ isBrigadeEditMode ? 'Brigadani Tahrirlash' : "Yangi Brigada Qo'shish" }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input v-model="brigadeForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs" placeholder="Brigada Nomi (Masalan: G'isht teruvchilar)" required>
                        <input v-model="brigadeForm.foreman_name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs" placeholder="Boshqaruvchi Usta (Prorab)" required>
                        <input v-model="brigadeForm.phone" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs" placeholder="Telefon Raqami">
                    </div>
                    <div class="flex gap-3 items-center justify-end pt-2">
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 hover:bg-white/20 text-gray-900 text-[10px] font-mono tracking-widest uppercase rounded transition-colors disabled:opacity-50">
                            {{ isBrigadeEditMode ? 'Saqlash' : "Qo'shish" }}
                        </button>
                    </div>
                </form>

                <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-4">
                    <!-- Brigades List -->
                    <div v-for="b in brigades" :key="b.id" class="border border-gray-200 bg-gray-900/50 rounded-xl overflow-hidden shadow-[0_4px_20px_rgba(0,0,0,0.5)]">
                        <div class="p-4 cursor-pointer hover:bg-gray-50 flex justify-between items-center transition-colors" @click="activeBrigadeId = activeBrigadeId === b.id ? null : b.id">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                    <span class="text-blue-600">{{ activeBrigadeId === b.id ? '▼' : '▶' }}</span>
                                    {{ b.name }}
                                </h3>
                                <p class="text-[10px] font-mono text-gray-500 mt-1 uppercase">Usta: {{ b.foreman_name }} <span v-if="b.phone">| {{ b.phone }}</span></p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-[10px] font-mono text-gray-500 uppercase mb-0.5">Brigada Balansi</p>
                                    <p class="text-sm font-bold" :class="b.balance > 0 ? 'text-emerald-600' : (b.balance < 0 ? 'text-red-600' : 'text-gray-900')">{{ $currency(b.balance || 0) }}</p>
                                </div>
                                <div class="flex gap-1.5 opacity-70 hover:opacity-100 transition-opacity">
                                    <button v-if="isAdmin" @click.stop="openBrigadeForm(b)" class="w-6 h-6 flex items-center justify-center text-gray-900 rounded hover:bg-white/20 transition-colors" title="Tahrirlash">✏️</button>
                                    <button v-if="isAdmin" @click.stop="openBrigadeLedger(b)" class="px-2 py-1 flex items-center justify-center bg-blue-50 text-blue-600 border border-blue-200 rounded hover:bg-blue-50 transition-colors" title="Hisob-kitob Jurnali">
                                        📊 Jurnal
                                    </button>


                                    <button v-if="isAdmin" @click.stop="deleteBrigade(b.id)" class="w-6 h-6 flex items-center justify-center bg-red-50 text-red-600 rounded hover:bg-red-50 transition-colors" title="O'chirish">🗑️</button>
                                </div>
                            </div>
                        </div>

                        <!-- Inner Workers -->
                        <div v-if="activeBrigadeId === b.id" class="bg-gray-900/50 p-4 border-t border-gray-100 animate-[fadeIn_0.2s_ease-out]">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="text-xs font-mono text-gray-500 uppercase">Brigada Ishchilari</h4>
                                <button @click="openWorkerEdit({ brigade_id: b.id })" class="px-2 py-1 text-gray-900 text-[9px] font-mono tracking-widest uppercase rounded hover:bg-white/20 transition-colors">
                                    + Ishchi
                                </button>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="w in b.workers" :key="w.id" class="p-3 rounded-lg border border-gray-100 flex flex-col justify-between">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-xs font-bold text-gray-900">{{ w.name }}</p>
                                            <p class="text-[9px] font-mono text-gray-500 mt-0.5 uppercase">{{ w.role }} | {{ w.salary_type }} ({{ w.metric_type }})</p>
                                        </div>
                                        <div class="flex gap-1">
                                            <button v-if="isAdmin" @click="openWorkerEdit(w)" class="text-[10px] px-1 hover:text-gray-900 text-gray-500">✏️</button>
                                            <button v-if="isAdmin" @click="deleteWorker(w.id)" class="text-[10px] px-1 hover:text-red-600 text-gray-500">🗑️</button>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-xs font-mono text-blue-600">{{ $currency(w.base_rate || w.rate) }}</div>
                                </div>
                                <div v-if="!b.workers || b.workers.length === 0" class="col-span-2 text-center text-[10px] font-mono text-gray-500 py-4 border border-dashed border-gray-200 rounded-lg">
                                    Ishchilar mavjud emas
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hidden Worker Form for reuse -->
                <Teleport to="body">
                    <div v-if="showWorkerForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div class="absolute inset-0 bg-gray-900/50" @click="cancelWorkerEdit"></div>
                        <form @submit.prevent="saveWorker" class="relative bg-black border border-blue-200 p-6 rounded-2xl max-w-lg w-full space-y-4 shadow-sm">
                            <h3 class="text-lg font-bold text-gray-900 uppercase">{{ isWorkerEditMode ? 'Ishchini Tahrirlash' : "Yangi Ishchi Qo'shish" }}</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <input v-model="workerForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs" placeholder="F.I.SH" required>
                                <input v-model="workerForm.phone" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs" placeholder="Telefon Raqam">
                                <select v-show="workerForm.role !== 'guard'" v-model="workerForm.brigade_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs bg-gray-900/50 text-gray-900" :required="workerForm.role !== 'guard'">
                                    <option value="" disabled>-- Brigada --</option>
                                    <option v-for="br in brigades" :key="br.id" :value="br.id">{{ br.name }}</option>
                                </select>
                                <select v-model="workerForm.role" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs bg-gray-900/50 text-gray-900">
                                    <option value="builder">Quruvchi / Usta</option>
                                    <option value="driver">Haydovchi</option>
                                    <option value="guard">Qorovul</option>
                                    <option value="office">Boshqa</option>
                                </select>
                                <select v-model="workerForm.salary_type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs bg-gray-900/50 text-gray-900">
                                    <option value="piece_rate">Ishbay</option>
                                    <option value="hourly">Soatbay</option>
                                    <option value="fixed_monthly">Oylik Maosh</option>
                                </select>
                                <select v-if="workerForm.salary_type === 'piece_rate'" v-model="workerForm.metric_type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs bg-gray-900/50 text-gray-900">
                                    <option value="dona">Dona</option>
                                    <option value="kv_m">Kvadrat Metr</option>
                                    <option value="kub_m">Kub Metr</option>
                                    <option value="metr">Metr</option>
                                    <option value="soat">Soat</option>
                                    <option value="kun">Kun</option>
                                    <option value="reys">Reys</option>
                                </select>
                                <input v-model="workerForm.base_rate" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs" placeholder="Narxi (Stavka)" required>

                                <div v-if="workerForm.role === 'guard' && workerForm.salary_type === 'fixed_monthly'" class="col-span-1 sm:col-span-2 p-4 border border-gray-200 rounded-xl space-y-4">
                                    <div class="flex justify-between items-center">
                                        <h4 class="text-[10px] font-mono text-blue-600 uppercase">O'tgan Davrlar Maoshini Kiritish</h4>
                                        <button type="button" @click="addSalaryPeriod" class="px-3 py-1 bg-blue-50 text-blue-600 rounded hover:bg-blue-600 hover:text-black transition-colors text-[10px] font-bold uppercase tracking-widest">+ Davr Qo'shish</button>
                                    </div>
                                    <div v-if="workerForm.salary_periods && workerForm.salary_periods.length > 0" class="space-y-3">
                                        <div v-for="(period, idx) in workerForm.salary_periods" :key="idx" class="flex flex-wrap md:flex-nowrap gap-2 items-end bg-gray-900/50 p-3 rounded-lg border border-gray-100 relative group">
                                            <button type="button" @click="removeSalaryPeriod(idx)" class="absolute -top-2 -right-2 w-5 h-5 bg-red-600 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs shadow-sm">✕</button>
                                            <div class="flex-1">
                                                <label class="block text-[9px] font-mono text-gray-500 mb-1 uppercase">Dan (Oy/Yil)</label>
                                                <input type="month" v-model="period.start_month" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full bg-gray-900/50" required>
                                            </div>
                                            <div class="flex-1">
                                                <label class="block text-[9px] font-mono text-gray-500 mb-1 uppercase">Gacha (Oy/Yil)</label>
                                                <input type="month" v-model="period.end_month" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full bg-gray-900/50" required>
                                            </div>
                                            <div class="flex-1">
                                                <label class="block text-[9px] font-mono text-gray-500 mb-1 uppercase">Oylik Miqdori</label>
                                                <input type="number" v-model="period.salary" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full bg-gray-900/50" placeholder="Summa" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-center text-[10px] font-mono text-gray-500 p-4 border border-dashed border-gray-200 rounded-lg">
                                        O'tgan davrlar kiritilmagan.
                                    </div>
                                </div>

                            </div>
                            <div class="flex justify-end gap-3 mt-4">
                                <button type="button" @click="cancelWorkerEdit" class="text-gray-500 text-xs hover:text-gray-900">Bekor qilish</button>
                                <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-blue-600 text-white font-bold uppercase tracking-widest text-xs rounded hover:shadow-sm">{{ $t('save') }}</button>
                            </div>
                        </form>
                    </div>
                </Teleport>
            </div>
                  <!-- Qorovullar -->
                  <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-100 flex flex-col h-[700px]">
                      <div class="flex justify-between items-center mb-6">
                          <h2 class="text-xl font-display font-bold text-gray-900 flex items-center gap-2">
                              🛡️ Qorovullar
                          </h2>
                          <button @click="openGuardForm()" class="px-4 py-2 bg-blue-50 border border-blue-500 text-blue-600 text-[10px] font-mono font-bold uppercase tracking-widest rounded hover:bg-blue-600 hover:text-black transition-all shadow-sm">
                              + Qorovul
                          </button>
                      </div>
                      
                      <div class="overflow-y-auto custom-scrollbar flex-1 relative pr-2">
                          <div class="grid grid-cols-1 gap-3">
                              <div v-for="g in workers.filter(w => w.role === 'guard')" :key="g.id" class="p-4 bg-gray-900/50 border border-gray-100 rounded-2xl flex flex-col justify-between hover:border-gray-300 transition-all group shadow-[0_4px_20px_rgba(0,0,0,0.5)]">
                                  <div class="flex items-start gap-3 mb-3">
                                      <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center shrink-0 border border-blue-200 group-hover:border-blue-200 transition-colors">
                                          <span class="text-lg">🛡️</span>
                                      </div>
                                      <div class="flex-1 min-w-0">
                                          <h3 class="text-gray-900 font-bold truncate">{{ g.name }}</h3>
                                          <p class="text-gray-500 text-[10px] font-mono uppercase mt-1">{{ g.phone || '-' }}</p>
                                      </div>
                                  </div>
                                  
                                  <div class="flex justify-between items-center pt-3 border-t border-gray-100 mt-auto">
                                      <div class="text-blue-600 text-xs font-mono font-bold">{{ $currency(g.base_rate) }} so'm</div>
                                      <div class="flex gap-2">
                                          <button @click="viewGuardHistory(g)" class="w-7 h-7 rounded flex items-center justify-center hover:bg-white/20 transition-colors text-blue-600" title="Maosh Tarixi">📅</button>
                                          <button @click="openGuardForm(g)" class="w-7 h-7 rounded flex items-center justify-center hover:bg-white/20 transition-colors text-gray-900" title="Tahrirlash">✏️</button>


                                          <button v-if="isAdmin" @click="deleteWorker(g.id)" class="w-7 h-7 rounded bg-red-50 flex items-center justify-center hover:bg-red-600 hover:text-gray-900 transition-colors text-red-600" title="O'chirish">🗑️</button>
                                      </div>
                                  </div>
                              </div>
                              <div v-if="workers.filter(w => w.role === 'guard').length === 0" class="text-center p-8 border border-dashed border-gray-200 rounded-2xl text-gray-500 text-sm font-mono">
                                  Qorovullar yo'q
                              </div>
                          </div>
                      </div>
                      
                      <!-- Guard Form Modal -->
                      <Teleport to="body">
                          <div v-if="showGuardForm" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                              <div class="absolute inset-0 bg-gray-900/50" @click="cancelGuardEdit"></div>
                              <form @submit.prevent="saveGuard" class="relative bg-black border border-blue-200 p-6 rounded-2xl max-w-lg w-full space-y-4 shadow-sm">
                                  <h3 class="text-lg font-bold text-gray-900 uppercase">{{ isGuardEditMode ? 'Qorovulni Tahrirlash' : "Yangi Qorovul Qo'shish" }}</h3>
                                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                      <input v-model="guardForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs" placeholder="F.I.SH" required>
                                      <input v-model="guardForm.phone" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs" placeholder="Telefon Raqam">
                                      <input v-model="guardForm.base_rate" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs" placeholder="Oylik Maosh (Stavka)" required>

                                      <div class="col-span-1 sm:col-span-2 p-4 border border-gray-200 rounded-xl space-y-4">
                                          <div class="flex justify-between items-center">
                                              <h4 class="text-[10px] font-mono text-blue-600 uppercase">O'tgan Davrlar Maoshini Kiritish</h4>
                                              <button type="button" @click="addGuardSalaryPeriod" class="px-3 py-1 bg-blue-50 text-blue-600 rounded hover:bg-blue-600 hover:text-black transition-colors text-[10px] font-bold uppercase tracking-widest">+ Davr Qo'shish</button>
                                          </div>
                                          <div v-if="guardForm.salary_periods && guardForm.salary_periods.length > 0" class="space-y-3">
                                              <div v-for="(period, idx) in guardForm.salary_periods" :key="idx" class="flex flex-wrap md:flex-nowrap gap-2 items-end bg-gray-900/50 p-3 rounded-lg border border-gray-100 relative group">
                                                  <button type="button" @click="removeGuardSalaryPeriod(idx)" class="absolute -top-2 -right-2 w-5 h-5 bg-red-600 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs shadow-sm">✕</button>
                                                  <div class="flex-1">
                                                      <label class="block text-[9px] font-mono text-gray-500 mb-1 uppercase">Dan (Oy/Yil)</label>
                                                      <input type="month" v-model="period.start_month" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full bg-gray-900/50" required>
                                                  </div>
                                                  <div class="flex-1">
                                                      <label class="block text-[9px] font-mono text-gray-500 mb-1 uppercase">Gacha (Oy/Yil)</label>
                                                      <input type="month" v-model="period.end_month" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full bg-gray-900/50" required>
                                                  </div>
                                                  <div class="flex-1">
                                                      <label class="block text-[9px] font-mono text-gray-500 mb-1 uppercase">Oylik Miqdori</label>
                                                      <input type="number" v-model="period.salary" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full bg-gray-900/50" placeholder="Summa" required>
                                                  </div>
                                              </div>
                                          </div>
                                          <div v-else class="text-center text-[10px] font-mono text-gray-500 p-4 border border-dashed border-gray-200 rounded-lg">
                                              O'tgan davrlar kiritilmagan.
                                          </div>
                                      </div>
                                  </div>
                                  <div class="flex justify-end gap-3 mt-4">
                                      <button type="button" @click="cancelGuardEdit" class="text-gray-500 text-xs hover:text-gray-900">Bekor qilish</button>
                                      <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-blue-600 text-white font-bold uppercase tracking-widest text-xs rounded hover:shadow-sm">{{ $t('save') }}</button>
                                  </div>
                              </form>
                          </div>
                      </Teleport>
                  </div>
          </div>
          
          <!-- TAB 1: FACE-ID HUB -->
        <div v-if="activeTab === 1" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-100 animate-[fadeIn_0.5s_ease-out]">
            <h2 class="text-xl font-display font-bold text-gray-900 mb-6 flex items-center gap-2">
                👁️ {{ $t('biometric_registration') }}
            </h2>
            <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 gap-8">
                <!-- Camera Feed Placeholder -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl rounded-2xl border border-blue-200 overflow-hidden relative min-h-[400px] flex items-center justify-center bg-gray-900/50 group">
                    <video ref="videoElement" autoplay playsinline class="absolute inset-0 w-full h-full object-cover z-0" v-show="isCameraActive"></video>
                    
                    <div v-if="!isCameraActive" class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10"></div>
                    <!-- Crosshair UI -->
                    <div class="absolute inset-8 border border-blue-200 rounded-[40px] pointer-events-none z-10"></div>
                    <div class="absolute top-4 left-4 w-8 h-8 border-t-2 border-l-2 border-blue-500 pointer-events-none z-10"></div>
                    <div class="absolute top-4 right-4 w-8 h-8 border-t-2 border-r-2 border-blue-500 pointer-events-none z-10"></div>
                    <div class="absolute bottom-4 left-4 w-8 h-8 border-b-2 border-l-2 border-blue-500 pointer-events-none z-10"></div>
                    <div class="absolute bottom-4 right-4 w-8 h-8 border-b-2 border-r-2 border-blue-500 pointer-events-none z-10"></div>
                    
                    <div v-if="!isCameraActive" class="text-center z-10">
                        <div class="text-6xl mb-4 animate-pulse">📷</div>
                        <p class="text-blue-600 font-mono text-sm tracking-widest uppercase mb-2">Kamera faol emas</p>
                        <button @click="startCamera" class="px-4 py-2 mt-2 bg-blue-50 border border-blue-500 text-blue-600 text-[10px] font-mono tracking-widest uppercase rounded hover:bg-blue-600 hover:text-black transition-colors">Kamerani Yoqish</button>
                    </div>
                </div>

                <!-- Registration Actions -->
                <div class="space-y-6">
                    <div class="bg-gray-900/50 p-6 rounded-xl border border-gray-100">
                        <h3 class="text-sm font-mono text-gray-900 mb-4 uppercase tracking-widest border-b border-gray-200 pb-2">Yuzni Xodimga Biriktirish</h3>
                        <div class="space-y-4">
                            <div class="flex gap-4">
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" v-model="biometricForm.person_type" value="worker" class="hidden peer" @change="biometricForm.person_id = ''">
                                    <div class="text-center p-3 rounded-xl border border-gray-200 peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-600 text-gray-500 transition-all text-[10px] uppercase tracking-widest font-mono">
                                        👷 Yollanma Ishchi
                                    </div>
                                </label>
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" v-model="biometricForm.person_type" value="user" class="hidden peer" @change="biometricForm.person_id = ''">
                                    <div class="text-center p-3 rounded-xl border border-gray-200 peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-600 text-gray-500 transition-all text-[10px] uppercase tracking-widest font-mono">
                                        👨‍💻 Tizim Xodimi
                                    </div>
                                </label>
                            </div>
                            
                            <div v-if="biometricForm.person_type === 'worker'">
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Ishchini Tanlang</label>
                                <select v-model="biometricForm.person_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50">
                                    <option value="" disabled selected>-- Ishchi --</option>
                                    <option v-for="w in workers.filter(w => w.role !== 'guard')" :key="w.id" :value="w.id">{{ w.name }} ({{ w.brigade ? w.brigade.name : w.role }})</option>
                                </select>
                            </div>
                            
                            <div v-if="biometricForm.person_type === 'user'">
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Tizim Xodimini Tanlang</label>
                                <select v-model="biometricForm.person_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50">
                                    <option value="" disabled selected>-- Xodim --</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} ({{ user.role }})</option>
                                </select>
                            </div>

                            <button @click="scanNewFace" :disabled="!isCameraActive || isProcessing" class="w-full mt-4 py-3 bg-blue-50 border border-blue-500 text-blue-600 text-sm font-mono font-bold uppercase tracking-widest rounded hover:bg-blue-600 hover:text-black transition-all shadow-sm disabled:opacity-50">
                                {{ isProcessing ? 'Saqlanmoqda...' : '📷 Yangi Yuzni Skanerlash' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: ATTENDANCE LOGS (AI HUB) -->
        <div v-if="activeTab === 2" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-100 animate-[fadeIn_0.5s_ease-out]">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-display font-bold text-gray-900 flex items-center gap-2">
                    ⏱️ AI Davomat va Nazorat Jurnali
                </h2>
                <div class="flex gap-2">
                    <button @click="startScanner" class="px-3 py-1 bg-blue-50 text-blue-600 border border-blue-200 text-[10px] font-mono uppercase tracking-widest rounded shadow-sm hover:bg-blue-600 hover:text-black transition-colors cursor-pointer relative z-10 flex items-center gap-2">
                        <span>📷</span> Mobil Skaner (Qo'lda)
                    </button>
                    <span class="px-3 py-1 bg-emerald-50 text-emerald-600 border border-emerald-200 text-[10px] font-mono uppercase tracking-widest rounded shadow-sm flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span> Live Tizim
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left text-sm font-mono">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                        <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                            <th class="pb-3 w-16">Isbot</th>
                            <th class="pb-3">Vaqti</th>
                            <th class="pb-3">Obyekt / Shaxs</th>
                            <th class="pb-3">Kategoriya</th>
                            <th class="pb-3">Holat</th>
                            <th class="pb-3 text-right">Kamera</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in attendanceLogs" :key="log.id" @click="openLogModal(log)" class="border-b border-gray-100 hover: transition-colors cursor-pointer group bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                            <td class="py-3">
                                <div class="w-10 h-10 rounded-lg overflow-hidden border border-gray-200 bg-gray-900/50 shadow-inner flex items-center justify-center cursor-pointer hover:border-blue-500 transition-colors">
                                    <img v-if="log.proof_image" :src="log.proof_image" class="w-full h-full object-cover">
                                    <span v-else class="text-xs opacity-50">N/A</span>
                                </div>
                            </td>
                            <td class="py-3 text-gray-900 font-bold">{{ new Date(log.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }} <span class="text-gray-500 text-[10px] block">{{ new Date(log.timestamp).toLocaleDateString() }}</span></td>
                            <td class="py-3">
                                <p class="text-gray-900 font-bold text-sm">{{ log.subject?.name || log.subject?.company_or_name || "Noma'lum" }}</p>
                                <p v-if="log.subject?.license_plate" class="text-blue-600 text-[10px] tracking-widest">{{ log.subject.license_plate }}</p>
                            </td>
                            <td class="py-3">
                                <span v-if="log.subject_type?.includes('User')" class="text-blue-600/80 text-[10px] uppercase tracking-widest">👨‍💻 Xodim</span>
                                <span v-else-if="log.subject_type?.includes('Worker')" class="text-indigo-600/80 text-[10px] uppercase tracking-widest">👷 Ishchi</span>
                                <span v-else-if="log.subject_type?.includes('Machinery')" class="text-amber-600/80 text-[10px] uppercase tracking-widest">🚜 Texnika</span>
                                <span v-else-if="log.subject_type?.includes('VisitorDelivery')" class="text-gray-500 text-[10px] uppercase tracking-widest">🚚 Tashrif buyuruvchi</span>
                                <span v-else class="text-gray-500 text-[10px] uppercase tracking-widest">❓ Noma'lum</span>
                            </td>
                            <td class="py-3">
                                <span v-if="log.type === 'entry'" class="inline-block px-2 py-0.5 rounded text-[10px] uppercase tracking-widest border bg-emerald-50 text-emerald-600 border-emerald-200 shadow-sm">
                                    ➡️ Keldi
                                </span>
                                <span v-else-if="log.type === 'exit'" class="inline-block px-2 py-0.5 rounded text-[10px] uppercase tracking-widest border bg-indigo-50 text-indigo-600 border-neon-magenta/30 shadow-sm">
                                    🚪 Ketdi
                                </span>
                                <span v-else-if="log.type === 'start_work'" class="inline-block px-2 py-0.5 rounded text-[10px] uppercase tracking-widest border bg-neon-blue/20 text-neon-blue border-neon-blue/30 shadow-sm">
                                    ⚙️ Ish Boshladi
                                </span>
                                <span v-else-if="log.type === 'stop_work'" class="inline-block px-2 py-0.5 rounded text-[10px] uppercase tracking-widest border bg-red-50 text-red-600 border-red-200 shadow-sm">
                                    🛑 Ish To'xtadi
                                </span>
                            </td>
                            <td class="py-3 text-right text-gray-500 text-[10px] uppercase tracking-widest">
                                {{ log.camera_id }}<br>
                                <span class="opacity-50">Acc: {{ log.confidence_score }}%</span>
                            </td>
                        </tr>
                        <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="!attendanceLogs || attendanceLogs.length === 0">
                            <td colspan="6" class="py-12 text-center text-gray-500 font-mono text-sm border-dashed border border-gray-200 rounded-xl block w-full mt-4">
                                Hozircha AI jurnali bo'sh
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: Machinery -->
        <div v-if="activeTab === 3" class="max-w-4xl mx-auto animate-[fadeIn_0.5s_ease-out]">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-100">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <h2 class="text-xl font-display font-bold text-gray-900 flex items-center gap-2">
                        🚜 Og'ir Texnika va Egaslari (Arendatorlar)
                    </h2>
                    <button @click="showOwnerForm = !showOwnerForm" class="px-3 py-1.5 bg-blue-50 text-blue-600 text-[10px] font-mono tracking-widest uppercase rounded hover:bg-blue-50 transition-colors border border-blue-200">
                        {{ showOwnerForm ? '- Yopish' : "+ Texnika Egasi Qo'shish" }}
                    </button>
                </div>

                <form v-if="showOwnerForm" @submit.prevent="saveOwner" class="mb-6 space-y-4 bg-gray-900/50 p-4 rounded-xl border border-gray-100 flex-shrink-0 animate-[fadeIn_0.3s_ease-out]">
                    <h3 class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">{{ isOwnerEditMode ? 'Egasini Tahrirlash' : 'Yangi Texnika Egasi' }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input v-model="ownerForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs" placeholder="Tashkilot / Ism" required>
                        <input v-model="ownerForm.phone" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs" placeholder="Telefon Raqami">
                    </div>
                    <div class="flex gap-3 items-center justify-end pt-2">
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 hover:bg-white/20 text-gray-900 text-[10px] font-mono tracking-widest uppercase rounded transition-colors disabled:opacity-50">
                            {{ isOwnerEditMode ? 'Saqlash' : "Qo'shish" }}
                        </button>
                    </div>
                </form>

                <div class="space-y-4">
                    <div v-for="owner in machineOwners" :key="owner.id" class="border border-gray-200 bg-gray-900/50 rounded-xl overflow-hidden shadow-[0_4px_20px_rgba(0,0,0,0.5)]">
                        <!-- Owner Header -->
                        <div class="p-4 cursor-pointer hover:bg-gray-50 flex justify-between items-center transition-colors" @click="activeOwnerId = activeOwnerId === owner.id ? null : owner.id">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                    <span class="text-blue-600">{{ activeOwnerId === owner.id ? '▼' : '▶' }}</span>
                                    {{ owner.name }}
                                </h3>
                                <p class="text-[10px] font-mono text-gray-500 mt-1">{{ owner.phone }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <button @click.stop="openOwnerLedger(owner)" class="px-3 py-1 bg-amber-50 text-amber-600 text-[10px] font-mono tracking-widest uppercase rounded border border-amber-200 hover:bg-amber-500 hover:text-black transition-colors flex items-center gap-2">
                                    <span>📊</span> Xisobot (Jurnal)
                                </button>
                                <div class="text-right">
                                    <p class="text-[10px] font-mono text-gray-500 uppercase mb-0.5">Umumiy Balans (Haqi)</p>
                                    <p class="text-sm font-bold" :class="owner.balance > 0 ? 'text-red-600' : (owner.balance < 0 ? 'text-emerald-600' : 'text-gray-900')">{{ $currency(owner.balance || 0) }}</p>
                                </div>
                                <div class="flex gap-1.5 opacity-70 hover:opacity-100 transition-opacity">
                                    <button v-if="isAdmin" @click.stop="openOwnerForm(owner)" class="w-6 h-6 flex items-center justify-center text-gray-900 rounded hover:bg-white/20 transition-colors" title="Tahrirlash">✏️</button>

                                    <button v-if="isAdmin" @click.stop="deleteOwner(owner.id)" class="w-6 h-6 flex items-center justify-center bg-red-50 text-red-600 rounded hover:bg-red-50 transition-colors" title="O'chirish">🗑️</button>
                                </div>
                            </div>
                        </div>

                        <!-- Inner Machineries -->
                        <div v-if="activeOwnerId === owner.id" class="bg-gray-900/50 p-4 border-t border-gray-100 animate-[fadeIn_0.2s_ease-out]">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="text-xs font-mono text-gray-500 uppercase">Tegishli Texnikalar</h4>
                                <button @click="machineryForm.machine_owner_id = owner.id; showMachineryForm = true" class="px-2 py-1 text-gray-900 text-[9px] font-mono tracking-widest uppercase rounded hover:bg-white/20 transition-colors">
                                    + Texnika Qo'shish
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="m in owner.machineries" :key="m.id" class="p-3 rounded-lg border border-gray-100 flex flex-col justify-between hover:border-gray-300 transition-colors">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-xs font-bold text-gray-900">{{ m.machinery_type }} - {{ m.name }}</p>
                                            <p class="text-[9px] font-mono text-blue-600 uppercase mt-0.5">{{ m.license_plate }}</p>
                                        </div>
                                        <div class="flex gap-1">
                                            <button v-if="isAdmin" @click="startEditMachinery(m); showMachineryForm = true" class="text-[10px] px-1 hover:text-gray-900 text-gray-500">✏️</button>
                                            <button v-if="isAdmin" @click="deleteMachinery(m.id)" class="text-[10px] px-1 hover:text-red-600 text-gray-500">🗑️</button>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-end mt-3">
                                        <p class="text-[10px] font-mono text-gray-500 uppercase">{{ m.payment_type === 'hourly' ? 'Soatbay' : 'Hajm' }}</p>
                                        <p class="text-xs font-bold text-emerald-600">{{ $currency(m.cost_per_hour) }}</p>
                                    </div>
                                </div>
                                <div v-if="!owner.machineries || owner.machineries.length === 0" class="col-span-2 text-center text-[10px] font-mono text-gray-500 py-4 border border-dashed border-gray-200 rounded-lg">
                                    Texnikalar mavjud emas
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add/Edit Machinery Modal -->
                <div v-if="showMachineryForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-gray-900/50" @click="showMachineryForm = false; editMachineryId = null"></div>
                    <form @submit.prevent="editMachineryId ? updateMachinery() : addMachinery()" class="relative bg-black border border-blue-200 p-6 rounded-2xl max-w-lg w-full space-y-4">
                        <h3 class="text-lg font-bold text-gray-900 uppercase">{{ editMachineryId ? 'Texnikani Tahrirlash' : "Yangi Texnika Qo'shish" }}</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] text-gray-500 uppercase mb-1">Egasi (Tanlang)</label>
                                <select v-if="editMachineryId" v-model="editMachineryForm.machine_owner_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full bg-gray-900/50 text-gray-900" required>
                                    <option v-for="o in machineOwners" :key="o.id" :value="o.id">{{ o.name }}</option>
                                </select>
                                <select v-else v-model="machineryForm.machine_owner_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full bg-gray-900/50 text-gray-900" required>
                                    <option v-for="o in machineOwners" :key="o.id" :value="o.id">{{ o.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] text-gray-500 uppercase mb-1">Turi (Yo'nalishi)</label>
                                <input v-if="editMachineryId" v-model="editMachineryForm.machinery_type" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" placeholder="Kran, Buldozer...">
                                <input v-else v-model="machineryForm.machinery_type" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" placeholder="Kran, Buldozer...">
                            </div>
                            <div>
                                <label class="block text-[10px] text-gray-500 uppercase mb-1">Haydovchi Ismi (ixtiyoriy)</label>
                                <input v-if="editMachineryId" v-model="editMachineryForm.driver_name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" placeholder="Eshmatov Toshmat">
                                <input v-else v-model="machineryForm.driver_name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" placeholder="Eshmatov Toshmat">
                            </div>
                            <div>
                                <label class="block text-[10px] text-gray-500 uppercase mb-1">Model / Rusumi</label>
                                <input v-if="editMachineryId" v-model="editMachineryForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" required>
                                <input v-else v-model="machineryForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" required>
                            </div>
                            <div>
                                <label class="block text-[10px] text-gray-500 uppercase mb-1">Davlat Raqami</label>
                                <input v-if="editMachineryId" v-model="editMachineryForm.license_plate" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full">
                                <input v-else v-model="machineryForm.license_plate" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full">
                            </div>
                            <div>
                                <label class="block text-[10px] text-gray-500 uppercase mb-1">To'lov Turi</label>
                                <select v-if="editMachineryId" v-model="editMachineryForm.payment_type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full bg-gray-900/50 text-gray-900" required>
                                    <option value="hourly">Soatbay</option>
                                    <option value="volume">Hajm bo'yicha</option>
                                </select>
                                <select v-else v-model="machineryForm.payment_type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full bg-gray-900/50 text-gray-900" required>
                                    <option value="hourly">Soatbay</option>
                                    <option value="volume">Hajm bo'yicha</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] text-gray-500 uppercase mb-1">Narxi (Stavka)</label>
                                <input v-if="editMachineryId" v-model="editMachineryForm.cost_per_hour" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" required>
                                <input v-else v-model="machineryForm.cost_per_hour" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" required>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 mt-4">
                            <button type="button" @click="showMachineryForm = false; editMachineryId = null" class="text-gray-500 text-xs hover:text-gray-900">Bekor qilish</button>
                            <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-blue-600 text-white font-bold uppercase tracking-widest text-xs rounded hover:shadow-sm">{{ $t('save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 4: Timesheets & Logistics / Cash Requests --><!-- TAB 4: Timesheets & Logistics / Cash Requests -->
        <div v-if="activeTab === 4" class="max-w-4xl mx-auto space-y-6 animate-[fadeIn_0.5s_ease-out]">
            
            <!-- Existing Work Log Form -->
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 md:p-8 rounded-2xl border border-neon-blue/30 shadow-sm">
                <h2 class="text-2xl font-display font-bold text-gray-900 mb-2">{{ $t('log_work') }}</h2>
                <p class="text-xs font-mono text-gray-500 mb-8">{{ $t('log_work_desc') }}</p>

                <form @submit.prevent="submitLog" class="space-y-6">
                    <div class="flex gap-4 flex-wrap md:flex-nowrap">
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" v-model="logForm.type" value="worker" class="hidden peer">
                            <div class="text-center p-3 rounded-xl border border-gray-200 peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-600 text-gray-500 transition-all text-sm uppercase tracking-widest font-mono">
                                👷 {{ $t('worker') }}
                            </div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" v-model="logForm.type" value="machinery" class="hidden peer">
                            <div class="text-center p-3 rounded-xl border border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-amber-600 text-gray-500 transition-all text-sm uppercase tracking-widest font-mono">
                                🚜 {{ $t('machinery') }}
                            </div>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                        <!-- chap ustun -->
                        <div class="space-y-6">
                            <!-- Worker/Machinery Select -->
                            <div v-if="logForm.type === 'worker'">
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('select_worker') }}</label>
                                <select v-model="logForm.worker_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900 bg-gray-900/50" required>
                                    <option value="" disabled>-- {{ $t('select') }} --</option>
                                    <option v-for="w in workers.filter(w => w.role !== 'guard')" :key="w.id" :value="w.id">{{ w.name }} ({{ w.brigade ? w.brigade.name : w.role }})</option>
                                </select>
                            </div>
                            <div v-if="logForm.type === 'machinery'" class="space-y-3">
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Texnika Egasi (Ixtiyoriy)</label>
                                    <select v-model="logForm.machine_owner_id" @change="logForm.machinery_id = ''" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900 bg-gray-900/50">
                                        <option value="">-- Barcha Texnikalar --</option>
                                        <option v-for="o in machineOwners" :key="o.id" :value="o.id">
                                            {{ o.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('select_machinery') }}</label>
                                    <select v-model="logForm.machinery_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900 bg-gray-900/50" required>
                                        <option value="" disabled>-- {{ $t('select') }} --</option>
                                        <option v-for="m in filteredMachineries" :key="m.id" :value="m.id">{{ m.name }}</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Ish Hajmi & Obyom & Sana -->
                            <div v-if="logForm.type === 'worker' || logForm.type === 'machinery'">
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ logForm.type === 'machinery' ? 'Ish Hajmi / Qatnov / Soat' : $t('hours_worked') }}</label>
                                <input v-model="logForm.hours_worked" type="number" step="0.5" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900" placeholder="0.0">
                            </div>
                            <div v-if="logForm.type === 'worker'">
                                <label class="block text-[10px] font-mono text-amber-600 mb-1 uppercase">Obyom / Hajm</label>
                                <input v-model="logForm.volume_done" type="number" step="0.1" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900" placeholder="0.0">
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('date') }}</label>
                                <input v-model="logForm.date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900" required>
                            </div>
                        </div>

                        <!-- o'ng ustun -->
                        <div class="space-y-6">
                            <!-- Bino Target -->
                            <div class="space-y-3">
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('building_target') }} ({{ $t('expense_link') }})</label>
                                <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <select v-model="logForm.building_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900 bg-gray-900/50" :required="logForm.type === 'worker' || logForm.type === 'machinery'" @change="logForm.floor_id = ''; logForm.unit_id = ''">
                                        <option value="" disabled>-- Bino --</option>
                                        <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                                    </select>
                                    <select v-model="logForm.floor_id" :disabled="!logForm.building_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900 bg-gray-900/50" @change="logForm.unit_id = ''">
                                        <option value="" disabled selected>-- Qavat --</option>
                                        <option v-for="f in logFloors" :key="f.id" :value="f.id">{{ f.floor_number }}-qavat</option>
                                    </select>
                                    <select v-model="logForm.unit_id" :disabled="!logForm.floor_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900 bg-gray-900/50">
                                        <option value="" disabled selected>-- Xonadon --</option>
                                        <option v-for="u in logUnits" :key="u.id" :value="u.id">{{ u.unit_number }}-xonadon</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Maxsus Narx -->
                            <div v-if="logForm.type === 'worker' || logForm.type === 'machinery'">
                                <label class="block text-[10px] font-mono text-indigo-600 mb-1 uppercase">Maxsus Narx (Ixtiyoriy)</label>
                                <input v-model="logForm.custom_rate" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900" placeholder="Kelishilgan narx">
                            </div>

                            <!-- Texnika Xarajati (Deductions) -->
                            <div v-if="logForm.type === 'worker' || logForm.type === 'machinery'">
                                <label class="block text-[10px] font-mono text-amber-600 mb-1 uppercase">
                                    {{ logForm.type === 'machinery' ? 'Texnika Xarajati (Chegirma)' : 'Ushlanma / Jarima' }}
                                </label>
                                <input v-model="logForm.deductions" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900 border-amber-200 focus:border-amber-500 focus:ring-neon-yellow/50" placeholder="0.00">
                            </div>

                            <!-- Izoh -->
                            <div v-if="logForm.type === 'worker' || logForm.type === 'machinery'">
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Izoh (Ixtiyoriy)</label>
                                <input v-model="logForm.description" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900" placeholder="Qo'shimcha ma'lumot yoki sabab...">
                            </div>
                        </div>
                    </div>
                    <div v-if="logForm.type === 'worker' || logForm.type === 'machinery'" class="mt-8 bg-gray-900/50 p-4 rounded-xl border border-neon-blue/20 flex justify-between items-center">
                        <span class="text-xs font-mono text-gray-500 uppercase tracking-widest">{{ $t('calculated_cost_preview') }}:</span>
                        <span class="text-2xl font-display font-bold text-neon-blue">{{ $currency(logPreviewCost) }}</span>
                    </div>

                    <button type="submit" :disabled="isProcessing" class="w-full py-4 rounded-xl text-black font-bold font-mono tracking-widest uppercase hover:shadow-sm transition-all disabled:opacity-50 bg-neon-blue">
                        <span>{{ $t('save_log') }}</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- TAB 5: Payroll & Disbursement -->
        <div v-if="activeTab === 5" class="space-y-6 animate-[fadeIn_0.5s_ease-out]">
            
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 md:p-8 border border-amber-200 shadow-sm rounded-2xl text-center relative overflow-hidden group">
                <div class="absolute inset-0 bg-amber-50 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <h3 class="text-sm font-mono text-gray-500 uppercase tracking-widest mb-2">{{ $t('total_unpaid_payroll') }}</h3>
                <h1 class="text-6xl font-display font-bold text-gray-900 drop-shadow-sm mb-8">
                    {{ $currency(unpaidTotal) }}
                </h1>
                
                <button @click="generatePayroll" :disabled="isProcessing || unpaidTotal <= 0" class="px-8 py-4 bg-amber-500 text-white font-bold font-mono text-lg uppercase tracking-widest rounded-xl hover:shadow-sm active:scale-95 transition-all duration-200 disabled:opacity-50 disabled:hover:shadow-none inline-flex items-center gap-3">
                    <span v-if="isProcessing" class="animate-spin text-xl">⚙️</span>
                    <span v-else class="text-xl group-hover:-translate-y-1 transition-transform">📄</span>
                    <span>{{ isProcessing ? 'HISOBLANMOQDA...' : 'OYLIK HISOBOTINI SHAKLLANTIRISH' }}</span>
                </button>
                <p class="text-[10px] font-mono text-gray-500 mt-4">Ushbu tugma barcha ishlarni bitta rasmiy hisobotga (Vekselga) yig'adi va to'lov uchun Kassaga jo'natadi.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Unpaid Logs -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-gray-100">
                    <h2 class="text-xl font-display font-bold text-gray-900 mb-6">Hisoblanmagan Jurnallar</h2>
                    <div class="overflow-x-auto max-h-[400px] custom-scrollbar">
                        <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left text-sm font-mono">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                    <th class="pb-3">{{ $t('date_label') }}</th>
                                    <th class="pb-3">Xodim / Texnika</th>
                                    <th class="pb-3 text-right">{{ $t('amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="log in unpaidLogs" :key="log.id" class="border-b border-gray-100 hover: transition-colors bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                    <td class="py-3 text-gray-500 text-xs">{{ log.date }}</td>
                                    <td class="py-3">
                                        <div class="flex items-center gap-2">
                                            <span v-if="log.worker_id" class="text-blue-600">👷</span>
                                            <span v-else class="text-amber-600">🚜</span>
                                            <span class="text-gray-900 text-xs font-bold">{{ log.worker?.name || log.machinery?.name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-right text-amber-600 font-bold text-xs">{{ $currency(log.calculated_cost) }}</td>
                                </tr>
                                <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="unpaidLogs.length === 0">
                                    <td colspan="3" class="py-8 text-center text-gray-500 font-mono text-xs border-dashed border border-gray-200 rounded-xl mt-4 block w-full">
                                        {{ $t('no_unpaid_logs') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payroll History -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl border border-blue-200">
                    <h2 class="text-xl font-display font-bold text-gray-900 mb-6">Yaratilgan Oyliklar (Tarix)</h2>
                    <div class="space-y-4 max-h-[400px] overflow-y-auto custom-scrollbar">
                        <div v-for="payroll in payrolls" :key="payroll.id" class="p-4 bg-gray-900/50 rounded-xl border border-gray-200 flex justify-between items-center group hover:border-blue-200 transition-colors">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="px-2 py-0.5 bg-blue-50 text-blue-600 border border-blue-200 text-[10px] uppercase font-mono tracking-widest rounded">PAY-{{ String(payroll.id).padStart(5, '0') }}</span>
                                    <span class="text-xs font-mono text-gray-500">{{ new Date(payroll.created_at).toLocaleDateString() }}</span>
                                </div>
                                <p class="text-lg text-gray-900 font-bold">{{ $currency(payroll.total_amount) }}</p>
                                <p class="text-[10px] font-mono mt-1 uppercase" :class="payroll.status === 'paid' ? 'text-emerald-600' : 'text-amber-600'">
                                    Status: {{ payroll.status === 'paid' ? "To'langan" : 'Shakllantirildi (Hisobot)' }}
                                </p>
                            </div>
                            <div class="text-right flex gap-2">
                                <a :href="route('admin.hr.payroll.print', payroll.id)" target="_blank" class="px-3 py-2 text-gray-900 border border-gray-300 text-xs uppercase font-mono rounded-lg hover:bg-gray-100 hover:border-white/40 transition-all flex items-center gap-2">
                                    🖨️ PDF / Chop etish
                                </a>
                            </div>
                        </div>
                        <div v-if="payrolls.length === 0" class="text-center py-8 text-gray-500 font-mono text-xs border-dashed border border-gray-200 rounded-xl">
                            Hech qanday oylik tarixi yo'q
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- MODAL: CREATE USER -->
        <div v-if="showUserModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50" @click="showUserModal = false"></div>
            <div class="relative bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl w-full max-w-lg border border-blue-200">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-red-600 text-white rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-sm z-50" @click="showUserModal = false">✖</button>
                <h2 class="text-xl font-bold text-gray-900 mb-6 uppercase tracking-widest shadow-neon-cyan/50">
                    {{ $t('add_user') }}
                </h2>
                <form @submit.prevent="saveUser" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('full_name') }}</label>
                        <input v-model="userForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('email') }}</label>
                        <input v-model="userForm.email" type="email" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('password') }} <span v-if="isEditMode" class="text-amber-600 lowercase">(Leave empty to keep)</span></label>
                        <input v-model="userForm.password" type="password" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" :required="!isEditMode">
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('system_role') }}</label>
                        <select v-model="userForm.role" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50" required>
                            <option value="Super_Admin">Super Admin</option>
                            <option value="Partner">Partner</option>
                            <option value="Operator">Operator</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('pinfl') }}</label>
                            <input v-model="userForm.pinfl" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('passport') }}</label>
                            <input v-model="userForm.passport" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('salary_rate') }}</label>
                        <input v-model="userForm.salary_rate" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                    </div>
                    <div class="flex justify-end gap-4 mt-8">
                        <button type="button" @click="showUserModal = false" class="px-4 py-2 text-xs font-mono uppercase tracking-widest text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-blue-600 text-white font-bold uppercase tracking-widest rounded hover:shadow-sm transition-all disabled:opacity-50">{{ $t('save_user') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: LEDGER -->
        <div v-if="showLedgerModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50" @click="showLedgerModal = false"></div>
            <div class="relative bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl w-[90vw] max-w-[1200px] border border-neon-blue/30 max-h-[90vh] overflow-y-auto custom-scrollbar">
                <button class="absolute top-4 right-4 w-7 h-7 flex items-center justify-center bg-red-600 text-white text-[10px] rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-sm z-50" @click="showLedgerModal = false">✖</button>
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-gray-200 pb-4 mb-4">
                    <div>
                        <h3 class="text-lg font-mono text-gray-900 font-bold tracking-widest uppercase">{{ ledgerWorker?.first_name }} {{ ledgerWorker?.last_name }}</h3>
                        <p class="text-xs text-neon-blue font-mono mt-1">- SHAXSIY KARTA</p>
                        <p class="text-[10px] text-gray-500 uppercase mt-2 font-mono tracking-wider">{{ ledgerWorker?.department }} | {{ ledgerWorker?.salary_type }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 font-mono uppercase mb-1">Joriy Balans</p>
                        <p class="text-2xl font-mono" :class="ledgerData.balance >= 0 ? 'text-emerald-600' : 'text-red-600'">
                            {{ new Intl.NumberFormat('fr-FR').format(ledgerData.balance) }} so'm
                        </p>
                    </div>
                </div>

                <!-- Category Balances -->
                <div v-if="ledgerData.category_balances && Object.keys(ledgerData.category_balances).length > 0" class="mb-4 bg-gray-900/50 p-3 rounded-lg border border-gray-100">
                    <h4 class="text-[10px] text-blue-600 uppercase tracking-widest mb-2 font-mono">Yo'nalishlar bo'yicha balans:</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <div v-for="(amount, cat) in ledgerData.category_balances" :key="cat" class="bg-gray-900/50 p-2 rounded flex justify-between items-center">
                            <span class="text-xs text-gray-500 font-mono">{{ cat }}</span>
                            <span class="text-xs font-mono" :class="amount >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ new Intl.NumberFormat('fr-FR').format(amount) }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <!-- Manual Work Log Form -->
                    <form @submit.prevent="saveWorkerLog" class="flex flex-col gap-2 p-4 bg-neon-blue/10 rounded-xl border border-neon-blue/30">
                        <h4 class="text-[10px] font-mono text-neon-blue uppercase tracking-widest border-b border-neon-blue/30 pb-2">➕ Ish Hajmini Kiritish</h4>
                        <div class="flex gap-2 mt-2">
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Obyekt (Bino)</label>
                                <select v-model="workerLogForm.building_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full text-gray-900 bg-gray-900/50" required>
                                    <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">O'lchov Birligi</label>
                                <select v-model="workerLogForm.measurement_unit" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full text-gray-900 bg-gray-900/50" required>
                                    <option value="" disabled>Tanlang</option>
                                    <option value="dona">Dona</option>
                                    <option value="kv_m">Kvadrat metr (m2)</option>
                                    <option value="kub_m">Kub metr (m3)</option>
                                    <option value="metr">Metr (m)</option>
                                    <option value="soat">Soat</option>
                                    <option value="kun">Kun</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Ish Turi</label>
                                <select v-model="workerLogForm.work_category" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full text-gray-900 bg-gray-900/50" required :disabled="!workerLogForm.measurement_unit">
                                    <option value="" disabled>Tanlang</option>
                                    <option v-for="cat in availableWorkCategories" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Hajm (Obyom)</label>
                                <input v-model="workerLogForm.volume" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Maxsus Narx</label>
                                <input v-model="workerLogForm.custom_rate" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" placeholder="Ixtiyoriy">
                            </div>
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">{{ $t('date_label') }}</label>
                                <input v-model="workerLogForm.date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" required>
                            </div>
                        </div>
                        <div class="flex justify-end pt-1">
                            <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-neon-blue/20 text-neon-blue text-[10px] font-mono uppercase tracking-widest rounded border border-neon-blue/50 hover:bg-neon-blue hover:text-black transition-colors disabled:opacity-50">
                                Qo'shish
                            </button>
                        </div>
                    </form>

                    <!-- Ledger Transaction Form -->
                    <form @submit.prevent="saveLedgerTransaction" class="flex flex-col gap-2 p-4 bg-emerald-50 rounded-xl border border-emerald-200">
                        <h4 class="text-[10px] font-mono text-emerald-600 uppercase tracking-widest border-b border-emerald-200 pb-2">💸 Tranzaksiya Qo'shish</h4>
                        <div class="flex gap-2 mt-2">
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Turi</label>
                                <select v-model="ledgerForm.type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full text-gray-900 bg-gray-900/50">
                                    <option value="payment">To'lov (Chiqim)</option>
                                    <option value="advance_payment">Avans (Chiqim)</option>
                                    <option value="penalty">Jarima (Chiqim)</option>
                                    <option value="salary_accrual">Qo'shimcha Ish haqi (Kirim)</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Ish Turi (Kategoriya)</label>
                                <select v-model="ledgerForm.work_category" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full text-gray-900 bg-gray-900/50">
                                    <option value="">Umumiy (Kategoriyasiz)</option>
                                    <option v-for="(amount, cat) in ledgerData.category_balances" :key="cat" :value="cat">
                                        {{ cat }} ({{ new Intl.NumberFormat('fr-FR').format(amount) }} so'm)
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-2">
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Miqdori</label>
                                <input v-model="ledgerForm.amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" placeholder="0.00" required>
                            </div>
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">{{ $t('date_label') }}</label>
                                <input v-model="ledgerForm.date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" required>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">{{ $t('notes') }}</label>
                            <input v-model="ledgerForm.description" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full">
                        </div>
                        <div class="flex justify-end pt-1">
                            <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-emerald-50 text-emerald-600 text-[10px] font-mono uppercase tracking-widest rounded border border-emerald-200 hover:bg-emerald-600 hover:text-black transition-colors disabled:opacity-50">{{ $t('save') }}</button>
                        </div>
                    </form>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-2">
                    <div v-for="t in ledgerData.transactions" :key="'tx'+t.id" class="flex flex-col gap-2 p-3 border border-gray-100 rounded-lg bg-gray-900/50 hover:border-gray-300 transition-colors group">
                        <div v-if="editTxId === t.id" class="w-full bg-gray-900/50 p-2 rounded border border-neon-blue/30 flex items-center gap-2">
                            <input v-model="editTxForm.amount" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-24">
                            <input v-model="editTxForm.date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-32">
                            <input v-model="editTxForm.description" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs flex-1" :placeholder="$t('notes')">
                            <button @click="submitEditTx('worker')" class="bg-neon-blue/20 text-neon-blue px-2 py-1 rounded text-xs hover:bg-neon-blue hover:text-black">{{ $t('save') }}</button>
                            <button @click="editTxId = null" class="text-gray-500 text-xs hover:text-gray-900">Bekor</button>
                        </div>
                        <div v-else class="flex justify-between items-center w-full">
                            <div>
                                <p class="text-[10px] font-mono text-gray-500">{{ new Date(t.date).toLocaleDateString() }}</p>
                                <p class="text-sm text-gray-900">{{ t.description || t.type }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-xs font-bold font-mono" :class="t.type === 'salary_accrual' ? 'text-emerald-600' : 'text-red-600'">
                                        {{ t.type === 'salary_accrual' ? '+' : '-' }}{{ $currency(t.amount) }}
                                    </p>
                                    <p class="text-[10px] font-mono uppercase tracking-widest" :class="t.type === 'salary_accrual' ? 'text-emerald-600/50' : 'text-red-600/50'">{{ t.type }}</p>
                                </div>
                                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button v-if="isAdmin" @click="startEditTx(t)" class="text-xs hover:bg-white/20 p-1 rounded">✏️</button>
                                    <button v-if="isAdmin" @click="destroyTx('worker', t.id)" class="text-xs bg-red-50 text-red-600 hover:bg-red-50 p-1 rounded">🗑️</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-for="log in ledgerData.logs" :key="'log'+log.id" class="p-3 border border-gray-100 rounded-lg bg-gray-900/50 hover:border-amber-200 transition-colors group">
                        <div v-if="editLogId === log.id" class="w-full bg-gray-900/50 p-2 rounded border border-neon-blue/30 flex flex-wrap items-center gap-2">
                            <input v-model="editLogForm.hours_worked" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-20" placeholder="Soat/Hajm">
                            <input v-model="editLogForm.custom_rate" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-24" placeholder="Maxsus narx">
                            <input v-model="editLogForm.deductions" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-24 border-amber-500" placeholder="Chegirma">
                            <input v-model="editLogForm.description" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-32" placeholder="Izoh">
                            <input v-model="editLogForm.date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-28">
                            <div class="flex-1 text-[10px] font-mono text-gray-500 uppercase">Tahrirlanmoqda...</div>
                            <button @click="submitEditLog" class="bg-neon-blue/20 text-neon-blue px-2 py-1 rounded text-xs hover:bg-neon-blue hover:text-black">{{ $t('save') }}</button>
                            <button @click="editLogId = null" class="text-gray-500 text-xs hover:text-gray-900">Bekor</button>
                        </div>
                        <div v-else class="flex justify-between items-center w-full">
                            <div>
                                <p class="text-[10px] font-mono text-gray-900 mb-1">{{ new Date(log.date).toLocaleDateString() }} | ⏳ {{ log.hours_worked || log.volume_done }} {{ ledgerWorker?.salary_type === 'hourly' ? 'soat' : 'hajm' }} <span v-if="log.rate" class="text-neon-blue">@ {{ log.rate }} so'm</span></p>
                                <p class="text-[10px] font-mono text-gray-500 tracking-widest uppercase">
                                    Obyekt: {{ log.building?.name || "Noma'lum" }}
                                    <span v-if="log.description" class="text-blue-600 block mt-1">Izoh: {{ log.description }}</span>
                                    <span v-if="log.deductions" class="text-amber-600 block mt-1">Ushlanma: {{ log.deductions }} so'm</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-sm font-bold" :class="log.status === 'unpaid' ? 'text-red-600' : 'text-emerald-600'">
                                        - {{ $currency(log.calculated_cost) }}
                                    </p>
                                    <span class="text-[10px] uppercase font-mono tracking-widest px-2 py-0.5 rounded border"
                                        :class="log.status === 'unpaid' ? 'border-red-500 text-red-600 bg-red-50' : 'border-emerald-500 text-emerald-600 bg-emerald-50'">
                                        {{ log.status === 'unpaid' ? "To'lanmagan" : "To'langan" }}
                                    </span>
                                </div>
                                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button v-if="isAdmin" @click="startEditLog(log)" class="text-xs hover:bg-white/20 p-1 rounded">✏️</button>
                                    <button v-if="isAdmin" @click="destroyLog(log.id)" class="text-xs bg-red-50 text-red-600 hover:bg-red-50 p-1 rounded">🗑️</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="!ledgerData.transactions.length && !ledgerData.logs.length" class="text-center py-6 text-gray-500 text-xs font-mono border border-dashed border-gray-200 rounded-xl">
                        Tarix topilmadi.
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: USER LEDGER -->
        <div v-if="showUserLedgerModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50" @click="showUserLedgerModal = false"></div>
            <div class="relative bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl w-[90vw] max-w-[1200px] border border-blue-200 max-h-[90vh] overflow-y-auto custom-scrollbar">
                <button class="absolute top-4 right-4 w-7 h-7 flex items-center justify-center bg-red-600 text-white text-[10px] rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-sm z-50" @click="showUserLedgerModal = false">✖</button>
                <div class="flex justify-between items-start mb-6 border-b border-gray-200 pb-4">
                    <div class="flex-1 pr-4">
                        <h2 class="text-lg font-bold text-gray-900 uppercase tracking-widest shadow-neon-cyan/50 leading-tight">
                            <span class="block mb-1 break-words">{{ ledgerUser?.name }}</span>
                            <span class="block text-sm text-blue-600/80">- SHAXSIY KARTA</span>
                        </h2>
                        <p class="text-[10px] font-mono text-gray-500 mt-2 uppercase">{{ ledgerUser?.roles?.[0]?.name || 'User' }}</p>
                    </div>
                    <div class="text-right shrink-0 mr-8 mt-1">
                        <p class="text-[10px] font-mono text-gray-500 uppercase mb-1">Joriy Balans</p>
                        <p class="text-xl font-bold font-mono" :class="userLedgerData.balance >= 0 ? 'text-emerald-600' : 'text-red-600'">
                            {{ $currency(userLedgerData.balance) }}
                        </p>
                    </div>
                </div>

                <form @submit.prevent="saveUserLedgerTransaction" class="mb-8 p-4 bg-gray-900/50 border border-gray-100 rounded-xl space-y-4">
                    <h3 class="text-xs font-mono text-gray-500 uppercase tracking-widest">Yangi Tranzaksiya qo'shish</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('type_label') }}</label>
                            <select v-model="userLedgerForm.type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs bg-gray-900/50" required>
                                <option value="salary_accrual">Oylik/Ish xaqi hisoblash (Kirim)</option>
                                <option value="advance_payment">Avans berish (Chiqim)</option>
                                <option value="payment">To'lov (Chiqim)</option>
                                <option value="penalty">Jarima (Chiqim)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('amount') }}</label>
                            <input v-model="userLedgerForm.amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('date_label') }}</label>
                            <input v-model="userLedgerForm.date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('notes') }}</label>
                            <input v-model="userLedgerForm.description" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs">
                        </div>
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-blue-50 text-blue-600 border border-blue-200 font-bold uppercase tracking-widest text-xs rounded hover:bg-blue-600 hover:text-black transition-all disabled:opacity-50">{{ $t('save') }}</button>
                    </div>
                </form>

                <div class="space-y-2">
                    <h3 class="text-xs font-mono text-gray-500 uppercase tracking-widest mb-2">Tranzaksiyalar Tarixi</h3>
                    <div v-for="t in userLedgerData.transactions" :key="t.id" class="flex flex-col gap-2 p-3 border border-gray-100 rounded-lg hover:bg-white/[0.02] transition-colors">
                        <div v-if="editTxId === t.id" class="w-full bg-gray-900/50 p-2 rounded border border-blue-200 flex items-center gap-2">
                            <input v-model="editTxForm.amount" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-24">
                            <input v-model="editTxForm.date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-32">
                            <input v-model="editTxForm.description" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs flex-1" :placeholder="$t('notes')">
                            <button @click="submitEditTx('user')" class="bg-blue-50 text-blue-600 px-2 py-1 rounded text-xs hover:bg-blue-600 hover:text-black">{{ $t('save') }}</button>
                            <button @click="editTxId = null" class="text-gray-500 text-xs hover:text-gray-900">Bekor</button>
                        </div>
                        <div v-else class="flex justify-between items-center w-full group">
                            <div>
                                <p class="text-[10px] font-mono text-gray-500">{{ t.date }}</p>
                                <p class="text-sm text-gray-900">{{ t.description || t.type }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-xs font-bold font-mono" :class="t.type === 'salary_accrual' ? 'text-emerald-600' : 'text-red-600'">
                                        {{ t.type === 'salary_accrual' ? '+' : '-' }}{{ $currency(t.amount) }}
                                    </p>
                                    <p class="text-[10px] font-mono uppercase tracking-widest" :class="t.type === 'salary_accrual' ? 'text-emerald-600/50' : 'text-red-600/50'">{{ t.type }}</p>
                                </div>
                                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button v-if="isAdmin" @click="startEditTx(t)" class="text-xs hover:bg-white/20 p-1 rounded">✏️</button>
                                    <button v-if="isAdmin" @click="destroyTx('user', t.id)" class="text-xs bg-red-50 text-red-600 hover:bg-red-50 p-1 rounded">🗑️</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!userLedgerData.transactions.length" class="text-center py-6 text-gray-500 text-xs font-mono border border-dashed border-gray-200 rounded-xl">
                        Tranzaksiyalar topilmadi.
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: AI LOG DETAILS -->
        <div v-if="showLogModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50" @click="showLogModal = false"></div>
            <div class="relative bg-white border border-gray-200 shadow-sm rounded-xl p-0 rounded-2xl w-full max-w-sm border border-blue-200 overflow-hidden flex flex-col items-center">
                
                <!-- ID Card Header / Photo -->
                <div class="w-full bg-gray-900/50 relative border-b border-gray-200 flex flex-col items-center p-6 pt-8 group">
                    <div class="absolute top-4 right-4 cursor-pointer text-gray-500 hover:text-gray-900" @click="showLogModal = false">✖</div>
                    
                    <div class="w-32 h-32 rounded-full border-4 overflow-hidden shadow-sm mb-4" 
                         :class="{ 'border-emerald-500': activeLog.type === 'entry', 'border-neon-magenta': activeLog.type === 'exit', 'border-neon-blue': activeLog.type === 'start_work', 'border-red-500': activeLog.type === 'stop_work' }">
                        <img v-if="activeLog.proof_image" :src="activeLog.proof_image" class="w-full h-full object-cover">
                        <span v-else class="text-4xl w-full h-full flex items-center justify-center">N/A</span>
                    </div>

                    <h2 class="text-xl font-bold text-gray-900 text-center">{{ activeLog.subject?.name || activeLog.subject?.company_or_name || "Noma'lum Shaxs" }}</h2>
                    <p class="text-[10px] font-mono text-blue-600 tracking-widest uppercase mt-1">
                        {{ activeLog.subject_type?.split(String.fromCharCode(92)).pop() || 'Unidentified' }}
                        <span v-if="activeLog.subject?.license_plate"> • {{ activeLog.subject.license_plate }}</span>
                    </p>
                </div>

                <!-- ID Card Body -->
                <div class="w-full p-6 space-y-4 bg-gradient-to-b from-transparent to-black/40">
                    
                    <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                        <span class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">Sana / Vaqt</span>
                        <span class="text-sm font-bold text-gray-900">{{ new Date(activeLog.timestamp).toLocaleString() }}</span>
                    </div>

                    <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                        <span class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">Kamera (Nuqta)</span>
                        <span class="text-sm font-mono text-gray-900">{{ activeLog.camera_id }}</span>
                    </div>

                    <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                        <span class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">Aniqlik (AI)</span>
                        <span class="text-sm font-mono text-gray-900" :class="activeLog.confidence_score > 90 ? 'text-emerald-600' : 'text-amber-600'">
                            {{ activeLog.confidence_score }}%
                        </span>
                    </div>

                    <div class="flex justify-between items-center pt-2">
                        <span class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">Harakat</span>
                        <span v-if="activeLog.type === 'entry'" class="px-3 py-1 rounded text-[10px] uppercase tracking-widest border bg-emerald-50 text-emerald-600 border-emerald-200">➡️ Kirdi</span>
                        <span v-else-if="activeLog.type === 'exit'" class="px-3 py-1 rounded text-[10px] uppercase tracking-widest border bg-indigo-50 text-indigo-600 border-neon-magenta/30">🚪 Chiqdi</span>
                        <span v-else-if="activeLog.type === 'start_work'" class="px-3 py-1 rounded text-[10px] uppercase tracking-widest border bg-neon-blue/20 text-neon-blue border-neon-blue/30">⚙️ Ish Boshladi</span>
                        <span v-else-if="activeLog.type === 'stop_work'" class="px-3 py-1 rounded text-[10px] uppercase tracking-widest border bg-red-50 text-red-600 border-red-200">🛑 Ish To'xtadi</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: MANUAL WEB SCANNER -->
        <div v-if="showScannerModal" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50" @click="stopScanner"></div>
            <div class="relative bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-3xl w-full max-w-lg border border-blue-200 shadow-sm flex flex-col items-center">
                <button class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center bg-red-600 text-white rounded-full hover:bg-red-600 hover:scale-110 transition-all shadow-sm z-50" @click="stopScanner">✖</button>
                <h2 class="text-xl font-display font-bold text-gray-900 mb-6 flex items-center gap-2">📷 Mobil Skaner (Davomat)</h2>

                <div class="w-full relative rounded-2xl overflow-hidden border-2 border-blue-500 mb-6 bg-black flex items-center justify-center min-h-[300px]">
                    <video v-if="!scannerSnapshot" ref="scannerVideo" autoplay playsinline class="w-full h-full object-cover"></video>
                    <img v-else :src="scannerSnapshot" class="w-full h-full object-cover">
                    
                    <!-- Scanner overlay effect -->
                    <div v-if="!scannerSnapshot" class="absolute inset-0 pointer-events-none">
                        <div class="w-full h-full border-[10px] border-black/50"></div>
                        <div class="absolute top-1/2 left-0 w-full h-[1px] bg-blue-50 shadow-sm animate-[scan_2s_ease-in-out_infinite_alternate]"></div>
                        <!-- Viewfinder corners -->
                        <div class="absolute top-4 left-4 w-8 h-8 border-t-4 border-l-4 border-blue-500"></div>
                        <div class="absolute top-4 right-4 w-8 h-8 border-t-4 border-r-4 border-blue-500"></div>
                        <div class="absolute bottom-4 left-4 w-8 h-8 border-b-4 border-l-4 border-blue-500"></div>
                        <div class="absolute bottom-4 right-4 w-8 h-8 border-b-4 border-r-4 border-blue-500"></div>
                    </div>
                </div>

                <div v-if="!scannerSnapshot" class="w-full flex justify-center">
                    <button @click="takeScannerSnapshot" class="w-16 h-16 rounded-full border-4 border-white flex items-center justify-center group hover:border-blue-500 transition-colors">
                        <div class="w-12 h-12 rounded-full bg-white group-hover:bg-blue-600 transition-colors"></div>
                    </button>
                </div>

                <div v-else class="w-full space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Shaxs Kategoriyasi</label>
                            <select v-model="scannerForm.subject_type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900" @change="scannerForm.subject_id = ''">
                                <option value="Worker">👷 Quruvchi / Ishchi</option>
                                <option value="User">👨‍💻 Tizim Xodimi</option>
                                <option value="Machinery">🚜 Maxsus Texnika</option>
                                <option value="VisitorDelivery">🚚 Tashrif / Yuk</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Shaxsni / Texnikani Tanlang</label>
                            <!-- Worker -->
                            <select v-if="scannerForm.subject_type === 'Worker'" v-model="scannerForm.subject_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900">
                                <option value="" disabled selected>-- Tanlang --</option>
                                <option v-for="w in workers.filter(w => w.role !== 'guard')" :key="w.id" :value="w.id">{{ w.name }} ({{ w.brigade ? w.brigade.name : w.role }})</option>
                            </select>
                            <!-- User -->
                            <select v-if="scannerForm.subject_type === 'User'" v-model="scannerForm.subject_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900">
                                <option value="" disabled selected>-- Tanlang --</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role }})</option>
                            </select>
                            <!-- Machinery -->
                            <select v-if="scannerForm.subject_type === 'Machinery'" v-model="scannerForm.subject_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900">
                                <option value="" disabled selected>-- Tanlang --</option>
                                <option v-for="m in machineries" :key="m.id" :value="m.id">{{ m.name }} ({{ m.license_plate }})</option>
                            </select>
                            <!-- Visitor -->
                            <input v-if="scannerForm.subject_type === 'VisitorDelivery'" v-model="scannerForm.subject_id" placeholder="Mashina raqami yoki ismi..." type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Harakat (Action)</label>
                        <select v-model="scannerForm.type" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 text-gray-900">
                            <option value="entry">➡️ Kirdi (Entry)</option>
                            <option value="exit">🚪 Chiqdi (Exit)</option>
                            <option value="start_work">⚙️ Ish boshladi</option>
                            <option value="stop_work">🛑 Ishni to'xtatdi</option>
                        </select>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button @click="scannerSnapshot = null" type="button" class="flex-1 py-3 border border-gray-200 text-gray-900 text-sm font-mono uppercase tracking-widest rounded hover:bg-gray-100 transition-colors">
                            Qayta Olish
                        </button>
                        <button @click="submitManualLog" :disabled="scannerForm.processing || (!scannerForm.subject_id && scannerForm.subject_type !== 'VisitorDelivery')" class="flex-1 py-3 bg-emerald-50 border border-emerald-500 text-emerald-600 text-sm font-mono font-bold uppercase tracking-widest rounded hover:bg-emerald-600 hover:text-black transition-all shadow-sm disabled:opacity-50">
                            {{ scannerForm.processing ? 'Yuborilmoqda...' : 'Tizimga Yozish' }}
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL: MACHINERY LEDGER -->
        

        

        

        <div v-if="showMachineryLedgerModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50" @click="showMachineryLedgerModal = false"></div>
            <div class="relative bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl w-full max-w-4xl border border-amber-200 shadow-sm flex flex-col max-h-[90vh]">
                <button class="absolute top-4 right-4 text-gray-900/50 hover:text-gray-900" @click="showMachineryLedgerModal = false">✖</button>
                
                <div class="flex justify-between items-start mb-4">
                    <h2 class="text-2xl font-display font-bold text-gray-900 flex items-center gap-2">
                        🚜 {{ activeMachinery?.name }} (Daftar)
                    </h2>
                    <div class="flex gap-2 mr-8">
                        <button @click="printLedger" class="px-3 py-1.5 hover:bg-white/20 text-gray-900 text-[10px] font-mono tracking-widest uppercase rounded border border-gray-300 transition-colors">
                            🖨️ PDF / Chop etish
                        </button>
                        <button @click="exportToExcel" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-50 text-emerald-600 text-[10px] font-mono tracking-widest uppercase rounded border border-emerald-200 transition-colors">
                            📊 Excel (CSV)
                        </button>
                    </div>
                </div>
                <p class="text-[10px] font-mono text-amber-600 tracking-widest uppercase border-b border-gray-200 pb-4 mb-4">
                    Davlat Raqami: {{ activeMachinery?.license_plate || 'N/A' }} | {{ activeMachinery?.payment_type === 'hourly' ? 'Soatbay' : 'Obyom' }}: {{ $currency(activeMachinery?.cost_per_hour) }}
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div class="p-4 rounded-xl border border-gray-200">
                        <p class="text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1">Umumiy Ishlagan (Topilgan)</p>
                        <p class="text-xl font-bold text-gray-900">{{ $currency(machineryLedgerData.total_earned) }}</p>
                    </div>
                    <div class="p-4 bg-red-50 rounded-xl border border-red-200 flex justify-between items-center">
                        <div>
                            <p class="text-[10px] font-mono text-red-600 uppercase tracking-widest mb-1">To'lanmagan Qarz</p>
                            <p class="text-xl font-bold text-red-600">{{ $currency(machineryLedgerData.unpaid_balance) }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <!-- Manual Work Log Form -->
                    <form @submit.prevent="saveMachineryLog" class="flex flex-col gap-2 p-4 bg-neon-blue/10 rounded-xl border border-neon-blue/30">
                        <h4 class="text-[10px] font-mono text-neon-blue uppercase tracking-widest border-b border-neon-blue/30 pb-2">➕ Ish Hajmini Kiritish</h4>
                        <div class="flex gap-2 mt-2">
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Obyekt (Building)</label>
                                <select v-model="machineryLogForm.building_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full text-gray-900 bg-gray-900/50" required>
                                    <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">{{ activeMachinery?.payment_type === 'hourly' ? 'Soat' : 'Hajm (Obyom)' }}</label>
                                <input v-model="machineryLogForm.volume" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" placeholder="0.00" required>
                            </div>
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">Maxsus Narx</label>
                                <input v-model="machineryLogForm.custom_rate" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" placeholder="Ixtiyoriy">
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">{{ $t('date_label') }}</label>
                                <input v-model="machineryLogForm.date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" required>
                            </div>
                            <div class="flex items-end">
                                <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-neon-blue/20 text-neon-blue text-[10px] font-mono uppercase tracking-widest rounded border border-neon-blue/50 hover:bg-neon-blue hover:text-black transition-colors disabled:opacity-50 h-[38px]">
                                    Qo'shish
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Payment Form -->
                    <form @submit.prevent="saveMachineryTransaction" class="flex flex-col gap-2 p-4 bg-emerald-50 rounded-xl border border-emerald-200">
                        <h4 class="text-[10px] font-mono text-emerald-600 uppercase tracking-widest border-b border-emerald-200 pb-2">💸 To'lov Qilish</h4>
                        <div class="flex gap-2 mt-2">
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">{{ $t('amount') }}</label>
                                <input v-model="machineryLedgerForm.amount" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" placeholder="0.00" required>
                            </div>
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">{{ $t('date_label') }}</label>
                                <input v-model="machineryLedgerForm.date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" required>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1">{{ $t('notes') }}</label>
                                <input v-model="machineryLedgerForm.description" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-full" placeholder="Masalan: Naqd pul orqali...">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-emerald-50 text-emerald-600 text-[10px] font-mono uppercase tracking-widest rounded border border-emerald-200 hover:bg-emerald-600 hover:text-black transition-colors disabled:opacity-50 h-[38px]">{{ $t('pay_button') }}</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-2">
                    <div v-for="tx in machineryLedgerData.transactions" :key="'tx'+tx.id" class="p-4 border border-emerald-200 rounded-xl bg-emerald-50 group transition-colors">
                        <div v-if="editTxId === tx.id" class="w-full bg-gray-900/50 p-2 rounded border border-blue-200 flex items-center gap-2">
                            <input v-model="editTxForm.amount" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-24">
                            <input v-model="editTxForm.date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-32">
                            <input v-model="editTxForm.description" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs flex-1" :placeholder="$t('notes')">
                            <button @click="submitEditTx('machinery')" class="bg-blue-50 text-blue-600 px-2 py-1 rounded text-xs hover:bg-blue-600 hover:text-black">{{ $t('save') }}</button>
                            <button @click="editTxId = null" class="text-gray-500 text-xs hover:text-gray-900">Bekor</button>
                        </div>
                        <div v-else class="flex justify-between items-center w-full">
                            <div>
                                <p class="text-xs font-mono text-gray-900 mb-1">{{ new Date(tx.date).toLocaleDateString() }} | 💸 To'lov Qilindi</p>
                                <p class="text-[10px] font-mono text-gray-500 tracking-widest uppercase">{{ tx.description || 'Izohsiz' }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-sm font-bold text-emerald-600">+ {{ $currency(tx.amount) }}</p>
                                </div>
                                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button @click="startEditTx(tx)" class="text-xs hover:bg-white/20 p-1 rounded">✏️</button>
                                    <button @click="destroyTx('machinery', tx.id)" class="text-xs bg-red-50 text-red-600 hover:bg-red-50 p-1 rounded">🗑️</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-for="log in machineryLedgerData.logs" :key="log.id" class="p-4 border border-gray-200 rounded-xl bg-gray-900/50 group hover:border-amber-200 transition-colors">
                        <div v-if="editLogId === log.id" class="w-full bg-gray-900/50 p-2 rounded border border-neon-blue/30 flex flex-wrap items-center gap-2">
                            <input v-model="editLogForm.hours_worked" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-20" placeholder="Soat/Hajm">
                            <input v-model="editLogForm.custom_rate" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-24" placeholder="Maxsus narx">
                            <input v-model="editLogForm.deductions" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-24 border-amber-500" placeholder="Chegirma">
                            <input v-model="editLogForm.description" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-32" placeholder="Izoh">
                            <input v-model="editLogForm.date" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs w-28">
                            <div class="flex-1 text-[10px] font-mono text-gray-500 uppercase">Tahrirlanmoqda...</div>
                            <button @click="submitEditLog" class="bg-neon-blue/20 text-neon-blue px-2 py-1 rounded text-xs hover:bg-neon-blue hover:text-black">{{ $t('save') }}</button>
                            <button @click="editLogId = null" class="text-gray-500 text-xs hover:text-gray-900">Bekor</button>
                        </div>
                        <div v-else class="flex justify-between items-center w-full">
                            <div>
                                <p class="text-xs font-mono text-gray-900 mb-1">{{ new Date(log.date).toLocaleDateString() }} | ⏳ {{ log.hours_worked || log.volume_done }} {{ activeMachinery?.payment_type === 'hourly' ? 'soat' : 'hajm' }}</p>
                                <p class="text-[10px] font-mono text-gray-500 tracking-widest uppercase">
                                    Obyekt: {{ log.building?.name || "Noma'lum" }}
                                    <span v-if="log.description" class="text-blue-600 block mt-1">Izoh: {{ log.description }}</span>
                                    <span v-if="log.deductions" class="text-amber-600 block mt-1">Chegirma / Xarajat: {{ log.deductions }} so'm</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-sm font-bold" :class="log.status === 'unpaid' ? 'text-red-600' : 'text-emerald-600'">
                                        - {{ $currency(log.calculated_cost) }}
                                    </p>
                                    <span class="text-[10px] uppercase font-mono tracking-widest px-2 py-0.5 rounded border"
                                        :class="log.status === 'unpaid' ? 'border-red-500 text-red-600 bg-red-50' : 'border-emerald-500 text-emerald-600 bg-emerald-50'">
                                        {{ log.status === 'unpaid' ? "To'lanmagan" : "To'langan" }}
                                    </span>
                                </div>
                                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button @click="startEditLog(log)" class="text-xs hover:bg-white/20 p-1 rounded">✏️</button>
                                    <button @click="destroyLog(log.id)" class="text-xs bg-red-50 text-red-600 hover:bg-red-50 p-1 rounded">🗑️</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!machineryLedgerData.logs.length && !machineryLedgerData.transactions?.length" class="text-center py-8 text-gray-500 font-mono text-xs border border-dashed border-gray-200 rounded-xl">
                        Hozircha hisob-kitob tarixi yo'q
                    </div>
                </div>
            </div>
            </div>
        </div>

        </div>

        <BrigadeLedgerModal 
            :show="showBrigadeLedgerModal"
            :brigade-id="activeBrigadeForLedger?.id"
            @close="showBrigadeLedgerModal = false"
            @exportPdf="exportBrigadePdf"
        />

        <OwnerLedgerModal 
            :show="showOwnerLedgerModal"
            :owner-id="activeOwnerForLedger?.id"
            @close="showOwnerLedgerModal = false"
            @exportPdf="exportOwnerPdf"
        />

    </AdminLayout>

        <!-- Guard Salary History Modal -->
        <div v-if="showGuardHistoryModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50" @click="showGuardHistoryModal = false"></div>
            <div class="relative bg-black border border-blue-200 p-6 rounded-2xl max-w-2xl w-full shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-display font-bold text-gray-900 flex items-center gap-2">
                        🛡️ {{ activeGuardHistory?.name }} - Maosh Tarixi
                    </h2>
                    <button @click="showGuardHistoryModal = false" class="text-gray-500 hover:text-gray-900 transition-colors">
                        ✕
                    </button>
                </div>
                
                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                            <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                <th class="p-3 font-normal uppercase tracking-widest">Sana</th>
                                <th class="p-3 font-normal uppercase tracking-widest text-right">Oylik Miqdori</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="hist in activeGuardHistory?.salary_histories" :key="hist.id" class="hover: transition-colors bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                <td class="p-3 text-gray-900">{{ new Date(hist.effective_date).toLocaleDateString() }}</td>
                                <td class="p-3 text-blue-600 text-right font-bold">{{ $currency(hist.salary) }} so'm</td>
                            </tr>
                            <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="!activeGuardHistory?.salary_histories || activeGuardHistory.salary_histories.length === 0">
                                <td colspan="2" class="p-6 text-center text-gray-500">Tarix mavjud emas</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button @click="showGuardHistoryModal = false" class="px-4 py-2 text-gray-900 font-bold uppercase tracking-widest text-xs rounded hover:bg-white/20 transition-colors">Yopish</button>
                </div>
            </div>
        </div>

</template>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>


