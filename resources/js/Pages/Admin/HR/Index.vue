<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const page = usePage();

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
    kpiSettings: Object,
    auth: Object
});

const activeTab = ref(0);

// Sync tab query parameter
watch(
    () => page.url,
    (newUrl) => {
        try {
            const url = new URL(newUrl, window.location.origin);
            const tabParam = url.searchParams.get('tab');
            if (tabParam !== null) {
                if (tabParam === 'sales_agents') {
                    activeTab.value = 'sales_agents';
                } else {
                    activeTab.value = parseInt(tabParam, 10);
                }
            } else {
                activeTab.value = 0;
            }
        } catch (e) {
            console.error(e);
        }
    },
    { immediate: true }
);

watch(activeTab, (newTab) => {
    try {
        const url = new URL(window.location.href);
        const currentTab = url.searchParams.get('tab');
        const isSame = currentTab === String(newTab);
        if (currentTab === null || !isSame) {
            url.searchParams.set('tab', newTab);
            window.history.replaceState({ ...window.history.state }, '', url.pathname + url.search);
        }
    } catch (e) {
        console.error(e);
    }
});

const salarySearchQuery = ref('');
const salarySelectedMonth = ref('2025-11'); // Default to Noyabr 2025 to match mockup exactly
const salaryCurrency = ref("so'm");
const conversionRate = 12800; // 1 USD = 12800 UZS

const monthNamesUz = [
    "Yanvar", "Fevral", "Mart", "Aprel", "May", "Iyun",
    "Iyul", "Avgust", "Sentabr", "Oktabr", "Noyabr", "Dekabr"
];

const selectedMonthLabel = computed(() => {
    if (!salarySelectedMonth.value) return "";
    const [year, month] = salarySelectedMonth.value.split('-');
    return `${monthNamesUz[parseInt(month, 10) - 1]} ${year}`;
});

// Helper for formatting price
const formatPrice = (val) => {
    if (val === null || val === undefined || isNaN(val)) {
        return salaryCurrency.value === 'usd' ? "$0" : "0 so'm";
    }
    if (salaryCurrency.value === 'usd') {
        return "$" + new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(val / conversionRate);
    }
    return new Intl.NumberFormat('uz-UZ').format(Math.round(val)) + " so'm";
};

// Safe date formatter
const formatDate = (dateStr) => {
    if (!dateStr) return "-";
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        return `${day}.${month}.${year}`;
    } catch (e) {
        return dateStr;
    }
};

// Compute Unified Employees List
const allEmployees = computed(() => {
    const list = [];
    
    // Map system users
    if (props.users) {
        props.users.forEach(u => {
            const roleName = u.roles?.[0]?.name || 'direktor';
            const displayRole = roleName === 'Super_Admin' ? 'Super Admin' : (roleName === 'Operator' ? 'operator' : (roleName === 'Partner' ? 'hamkor' : (roleName === 'Sotuvchi' ? 'sotuvchi' : roleName)));
            list.push({
                id: u.id,
                type: 'user',
                name: u.name,
                email: u.email,
                phone: u.phone || '',
                role: roleName,
                role_label: displayRole,
                building_name: 'Barcha',
                building_id: null,
                employment_date: u.employment_date || (u.created_at ? u.created_at.split('T')[0] : '2025-10-29'),
                status: u.status || 'active',
                status_label: (u.status || 'active') === 'active' ? 'Ish holatida' : 'Bo\'shatilgan',
                passport: u.passport || '',
                pinfl: u.pinfl || '',
                gender: u.gender || 'Erkak',
                birth_date: u.birth_date || '',
                specialty_id: u.specialty_id || '',
                salary: u.salary_rate || 0,
                photo_path: u.photo_path || '',
                transactions: u.transactions || [],
                leads_count: u.leads_count || 0
            });
        });
    }
    
    // Map construction site workers
    if (props.workers) {
        props.workers.forEach(w => {
            let bName = 'Barcha';
            if (w.building) {
                bName = w.building.name;
            } else if (w.building_id) {
                const b = props.buildings?.find(x => x.id === parseInt(w.building_id));
                if (b) bName = b.name;
            }
            list.push({
                id: w.id,
                type: 'worker',
                name: w.name,
                phone: w.phone || '',
                role: w.role || 'ishchi',
                role_label: w.role === 'guard' ? 'qorovul' : (w.role === 'driver' ? 'haydovchi' : 'ishchi'),
                building_name: bName,
                building_id: w.building_id,
                employment_date: w.employment_date || (w.created_at ? w.created_at.split('T')[0] : '2025-10-15'),
                status: w.status || 'active',
                status_label: (w.status || 'active') === 'active' ? 'Ish holatida' : 'Bo\'shatilgan',
                passport: w.passport || '',
                pinfl: w.pinfl || '',
                gender: w.gender || 'Erkak',
                birth_date: w.birth_date || '',
                specialty_id: w.specialty_id || '',
                salary: w.base_rate || w.rate || 0,
                photo_path: w.photo_path || '',
                transactions: w.transactions || [],
                leads_count: 0
            });
        });
    }
    
    return list;
});

// Search, Filter & Stats
const searchQuery = ref('');
const filterRole = ref('');
const filterBuilding = ref('');
const filterStatus = ref('');

const filteredEmployees = computed(() => {
    return allEmployees.value.filter(e => {
        const matchesSearch = e.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                             e.phone.includes(searchQuery.value) || 
                             (e.passport && e.passport.toLowerCase().includes(searchQuery.value.toLowerCase()));
        
        const matchesRole = !filterRole.value || e.role === filterRole.value;
        
        const matchesBuilding = !filterBuilding.value || 
                               (filterBuilding.value === 'all' && e.building_name === 'Barcha') ||
                               (filterBuilding.value !== 'all' && e.building_id === parseInt(filterBuilding.value));
        
        const matchesStatus = !filterStatus.value || e.status === filterStatus.value;
        
        return matchesSearch && matchesRole && matchesBuilding && matchesStatus;
    });
});

const stats = computed(() => {
    const total = allEmployees.value.length;
    const active = allEmployees.value.filter(e => e.status === 'active').length;
    
    // Permanent employees: Users + guards + fixed_monthly salary type
    const permanent = allEmployees.value.filter(e => e.type === 'user' || e.role === 'guard').length;
    
    // Piece-rate or hourly workers
    const contract = allEmployees.value.filter(e => e.type === 'worker' && e.role !== 'guard').length;
    
    const fired = allEmployees.value.filter(e => e.status === 'fired').length;
    
    return { total, active, permanent, contract, fired };
});

// Detailed view modal state
const showDetailModal = ref(false);
const selectedEmployee = ref(null);
const rightTab = ref('ariza'); // 'ariza' or 'buyruq'

function openDetailModal(emp) {
    selectedEmployee.value = emp;
    showDetailModal.value = true;
}

// Add/Edit employee modal state
const showEmployeeModal = ref(false);
const isEditMode = ref(false);
const isProcessing = ref(false);

const showHRReportModal = ref(false);
const hrReportType = ref(''); // 'total', 'active', 'permanent', 'contract', 'fired'
const hrReportSearchQuery = ref('');

const openHRReportModal = (type) => {
    hrReportType.value = type;
    hrReportSearchQuery.value = '';
    showHRReportModal.value = true;
};

const hrReportList = computed(() => {
    switch (hrReportType.value) {
        case 'total':
            return allEmployees.value;
        case 'active':
            return allEmployees.value.filter(e => e.status === 'active');
        case 'permanent':
            return allEmployees.value.filter(e => e.type === 'user' || e.role === 'guard');
        case 'contract':
            return allEmployees.value.filter(e => e.type === 'worker' && e.role !== 'guard');
        case 'fired':
            return allEmployees.value.filter(e => e.status === 'fired');
        default:
            return [];
    }
});

const filteredHRReportList = computed(() => {
    const query = hrReportSearchQuery.value.toLowerCase();
    return hrReportList.value.filter(e => {
        return e.name.toLowerCase().includes(query) ||
               (e.phone && e.phone.includes(query)) ||
               (e.role_label && e.role_label.toLowerCase().includes(query)) ||
               (e.building_name && e.building_name.toLowerCase().includes(query));
    });
});

const hrReportTitle = computed(() => {
    switch (hrReportType.value) {
        case 'total': return "Barcha xodimlar jurnali";
        case 'active': return "Faol xodimlar jurnali";
        case 'permanent': return "Doimiy ishchilar jurnali";
        case 'contract': return "Yollanma ishchilar jurnali";
        case 'fired': return "Bo'shatilgan xodimlar jurnali";
        default: return "Xodimlar jurnali";
    }
});

function exportHRReportToExcel() {
    let csvContent = "\uFEFF"; // UTF-8 BOM
    csvContent += "sep=;\n"; // Excel column separator directive
    csvContent += "№;ID;Xodim;Telefon;Lavozimi;Bino;Ishga olingan sana;Holat\n";
    
    filteredHRReportList.value.forEach((e, idx) => {
        const name = e.name.replace(/;/g, ' ');
        const role = (e.role_label || '').replace(/;/g, ' ');
        const bino = (e.building_name || '').replace(/;/g, ' ');
        const phone = (e.phone || '').replace(/;/g, ' ');
        csvContent += `${idx + 1};${e.id};"${name}";"${phone}";"${role}";"${bino}";"${formatDate(e.employment_date)}";"${e.status_label}"\n`;
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    
    let filename = `Xodimlar_Jurnali`;
    if (hrReportType.value) {
        filename += `_${hrReportType.value}`;
    }
    link.setAttribute("download", `${filename}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

const showSalaryReportModal = ref(false);
const salaryReportType = ref(''); // 'base', 'paid', 'penalty', 'debt'
const reportSearchQuery = ref('');

const openSalaryReportModal = (type) => {
    salaryReportType.value = type;
    reportSearchQuery.value = '';
    showSalaryReportModal.value = true;
};

const filteredReportList = computed(() => {
    return salaryList.value.filter(s => {
        const query = reportSearchQuery.value.toLowerCase();
        return s.employee.name.toLowerCase().includes(query) ||
               (s.employee.role_label && s.employee.role_label.toLowerCase().includes(query)) ||
               (s.employee.building_name && s.employee.building_name.toLowerCase().includes(query));
    });
});

const reportModalTitle = computed(() => {
    const month = selectedMonthLabel.value;
    switch (salaryReportType.value) {
        case 'base': return `Asosiy maoshlar bo'yicha to'liq hisobot - ${month}`;
        case 'paid': return `To'langan maoshlar bo'yicha to'liq hisobot - ${month}`;
        case 'penalty': return `Jarimalar bo'yicha to'liq hisobot - ${month}`;
        case 'debt': return `Qarzdorlik (Qoldiq) bo'yicha to'liq hisobot - ${month}`;
        default: return `To'liq maosh hisoboti - ${month}`;
    }
});

function exportReportToExcel() {
    let csvContent = "\uFEFF"; // UTF-8 BOM
    csvContent += "sep=;\n"; // Excel column separator directive
    csvContent += "№;Xodim;Lavozimi;Bino;Asosiy maosh;Bonus;Jarima;Avans;To'langan summa;To'lov sana;Qarzdorlik (Qoldiq);Holat\n";
    
    filteredReportList.value.forEach(s => {
        const name = s.employee.name.replace(/;/g, ' ');
        const role = (s.employee.role_label || '').replace(/;/g, ' ');
        const bino = (s.employee.building_name || '').replace(/;/g, ' ');
        csvContent += `${s.index};"${name}";"${role}";"${bino}";${s.base_salary};${s.bonus};${s.penalty};${s.advance};${s.paid};"${s.payment_date}";${s.debt};"${s.status}"\n`;
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    
    let filename = `Maosh_Hisoboti_${salarySelectedMonth.value}`;
    if (salaryReportType.value) {
        filename += `_${salaryReportType.value}`;
    }
    link.setAttribute("download", `${filename}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}


const employeeForm = useForm({
    id: null,
    type: 'worker', // 'user' or 'worker'
    name: '',
    first_name: '',
    last_name: '',
    middle_name: '',
    email: '',
    password: '',
    phone: '',
    role: '',
    salary_type: 'fixed_monthly',
    base_rate: '',
    salary_rate: '',
    building_id: '',
    passport: '',
    pinfl: '',
    gender: 'Erkak',
    birth_date: '',
    employment_date: '',
    status: 'active',
    specialty_id: '',
    contract_type: 'labor',
    currency: 'UZS',
    passport_front: null,
    passport_back: null,
    additional_documents: [],
    characteristics: []
});

function openAddEmployee() {
    isEditMode.value = false;
    employeeForm.reset();
    employeeForm.type = 'worker';
    employeeForm.role = '';
    employeeForm.salary_type = 'fixed_monthly';
    employeeForm.status = 'active';
    employeeForm.gender = 'Erkak';
    employeeForm.first_name = '';
    employeeForm.last_name = '';
    employeeForm.middle_name = '';
    employeeForm.currency = 'UZS';
    employeeForm.contract_type = 'labor';
    employeeForm.passport_front = null;
    employeeForm.passport_back = null;
    employeeForm.additional_documents = [];
    employeeForm.characteristics = [];
    showEmployeeModal.value = true;
}

function openEditEmployee(emp) {
    isEditMode.value = true;
    employeeForm.id = emp.id;
    employeeForm.type = emp.type;
    employeeForm.name = emp.name;
    const nameParts = emp.name ? emp.name.trim().split(/\s+/) : [];
    employeeForm.last_name = nameParts[0] || '';
    employeeForm.first_name = nameParts[1] || '';
    employeeForm.middle_name = nameParts.slice(2).join(' ') || '';
    employeeForm.email = emp.email || '';
    employeeForm.password = '';
    employeeForm.phone = emp.phone || '';
    employeeForm.role = emp.role || '';
    employeeForm.salary_type = emp.type === 'worker' ? (emp.salary_type || 'piece_rate') : 'fixed_monthly';
    employeeForm.base_rate = emp.salary_rate || emp.salary || '';
    employeeForm.salary_rate = emp.salary_rate || emp.salary || '';
    employeeForm.building_id = emp.building_id || '';
    employeeForm.passport = emp.passport || '';
    employeeForm.pinfl = emp.pinfl || '';
    employeeForm.gender = emp.gender || 'Erkak';
    employeeForm.birth_date = emp.birth_date || '';
    employeeForm.employment_date = emp.employment_date || '';
    employeeForm.status = emp.status || 'active';
    employeeForm.specialty_id = emp.specialty_id || '';
    employeeForm.contract_type = emp.contract_type || 'labor';
    employeeForm.currency = emp.currency || 'UZS';
    employeeForm.passport_front = emp.passport_front || null;
    employeeForm.passport_back = emp.passport_back || null;

    if (emp.characteristics) {
        if (typeof emp.characteristics === 'string') {
            try {
                employeeForm.characteristics = JSON.parse(emp.characteristics);
            } catch (e) {
                employeeForm.characteristics = [];
            }
        } else if (Array.isArray(emp.characteristics)) {
            employeeForm.characteristics = emp.characteristics;
        } else {
            employeeForm.characteristics = [];
        }
    } else {
        employeeForm.characteristics = [];
    }

    if (emp.additional_documents) {
        if (typeof emp.additional_documents === 'string') {
            try {
                employeeForm.additional_documents = JSON.parse(emp.additional_documents);
            } catch (e) {
                employeeForm.additional_documents = [];
            }
        } else if (Array.isArray(emp.additional_documents)) {
            employeeForm.additional_documents = emp.additional_documents;
        } else {
            employeeForm.additional_documents = [];
        }
    } else {
        employeeForm.additional_documents = [];
    }

    showEmployeeModal.value = true;
}

function saveEmployee() {
    isProcessing.value = true;
    const parts = [];
    if (employeeForm.last_name) parts.push(employeeForm.last_name.trim());
    if (employeeForm.first_name) parts.push(employeeForm.first_name.trim());
    if (employeeForm.middle_name) parts.push(employeeForm.middle_name.trim());
    employeeForm.name = parts.join(' ');

    if (employeeForm.type === 'user') {
        employeeForm.salary_rate = employeeForm.base_rate;
        if (isEditMode.value) {
            employeeForm.post(route('admin.hr.users.update', employeeForm.id), {
                onSuccess: () => { showEmployeeModal.value = false; isProcessing.value = false; },
                onError: () => isProcessing.value = false
            });
        } else {
            employeeForm.post(route('admin.hr.users.store'), {
                onSuccess: () => { showEmployeeModal.value = false; isProcessing.value = false; },
                onError: () => isProcessing.value = false
            });
        }
    } else {
        if (isEditMode.value) {
            employeeForm.post(route('admin.hr.worker.update', employeeForm.id), {
                onSuccess: () => { showEmployeeModal.value = false; isProcessing.value = false; },
                onError: () => isProcessing.value = false
            });
        } else {
            employeeForm.post(route('admin.hr.worker.store'), {
                onSuccess: () => { showEmployeeModal.value = false; isProcessing.value = false; },
                onError: () => isProcessing.value = false
            });
        }
    }
}

function deleteEmployee(emp) {
    if (confirm(`${emp.name}ni o'chirishga ishonchingiz komilmi?`)) {
        const url = emp.type === 'user' 
            ? route('admin.hr.users.delete', emp.id) 
            : route('admin.hr.worker.destroy', emp.id);

        router.delete(url, {
            onSuccess: () => {
                alert("Xodim muvaffaqiyatli o'chirildi!");
            },
            onError: () => {
                alert("O'chirishda xatolik yuz berdi.");
            }
        });
    }
}

// Upload biometric photos
const photoInput = ref(null);
const uploadTarget = ref('photo'); // 'photo' or 'passport'

function triggerPhotoUpload(target) {
    uploadTarget.value = target;
    photoInput.value.click();
}

function handlePhotoUpload(event) {
    const file = event.target.files[0];
    if (!file || !selectedEmployee.value) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        const base64 = e.target.result;
        isProcessing.value = true;
        
        router.post(route('admin.hr.biometric.store'), {
            person_type: selectedEmployee.value.type,
            person_id: selectedEmployee.value.id,
            image: base64
        }, {
            onSuccess: () => {
                isProcessing.value = false;
                alert("Rasm yuklandi!");
                // Update local UI
                if (uploadTarget.value === 'photo') {
                    selectedEmployee.value.photo_path = base64.replace(/^data:image\/[a-z]+;base64,/, "");
                }
            },
            onError: () => {
                isProcessing.value = false;
                alert("Rasmni yuklashda xatolik yuz berdi.");
            }
        });
    };
    reader.readAsDataURL(file);
}

const isDragging = ref(false);

function uploadPassportFile(event, field) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        employeeForm[field] = e.target.result;
    };
    reader.readAsDataURL(file);
}

function handleFileDrop(event) {
    isDragging.value = false;
    const files = event.dataTransfer.files;
    processFiles(files);
}

function handleFileSelect(event) {
    const files = event.target.files;
    processFiles(files);
}

function processFiles(files) {
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const reader = new FileReader();
        reader.onload = (e) => {
            employeeForm.additional_documents.push({
                name: file.name,
                data: e.target.result
            });
        };
        reader.readAsDataURL(file);
    }
}

function removeDocument(index) {
    employeeForm.additional_documents.splice(index, 1);
}

function addCharacteristic() {
    employeeForm.characteristics.push('');
}

function removeCharacteristic(index) {
    employeeForm.characteristics.splice(index, 1);
}

// Tab 1: Salaries calculations
const salaryList = computed(() => {
    const [selYear, selMonth] = salarySelectedMonth.value.split('-').map(Number);
    
    return allEmployees.value.filter(e => {
        const matchesSearch = e.name.toLowerCase().includes(salarySearchQuery.value.toLowerCase()) || 
                             e.phone.includes(salarySearchQuery.value);
        return matchesSearch;
    }).map((e, idx) => {
        const txs = e.transactions || [];
        
        // Filter transactions by selected month
        const monthlyTxs = txs.filter(t => {
            if (!t.date) return false;
            const d = new Date(t.date);
            return d.getFullYear() === selYear && (d.getMonth() + 1) === selMonth;
        });
        
        const bonus = monthlyTxs.filter(t => t.type === 'salary_accrual' && t.description?.toLowerCase().includes('bonus'))
                                .reduce((sum, t) => sum + parseFloat(t.amount), 0);
                                
        const penalty = monthlyTxs.filter(t => t.type === 'penalty')
                                  .reduce((sum, t) => sum + parseFloat(t.amount), 0);
                                  
        const advance = monthlyTxs.filter(t => t.type === 'advance_payment')
                                  .reduce((sum, t) => sum + parseFloat(t.amount), 0);
                                  
        const paid = monthlyTxs.filter(t => t.type === 'payment')
                               .reduce((sum, t) => sum + parseFloat(t.amount), 0);
                               
        const payDates = monthlyTxs.filter(t => t.type === 'payment').map(t => new Date(t.date));
        let lastPayDate = "-";
        if (payDates.length > 0) {
            const maxDate = new Date(Math.max(...payDates));
            lastPayDate = formatDate(maxDate.toISOString().split('T')[0]);
        }
        
        const base_salary = parseFloat(e.salary || 0);
        const debt = base_salary + bonus - penalty - advance - paid;
        
        let status = "Kutilmoqda";
        if (debt <= 0) {
            status = "To'langan";
        } else if (paid > 0 || advance > 0) {
            status = "Qisman to'langan";
        }
        
        return {
            index: idx + 1,
            employee: e,
            base_salary,
            bonus,
            penalty,
            advance,
            paid,
            payment_date: lastPayDate,
            debt: Math.max(0, debt),
            status
        };
    });
});

const salaryTotals = computed(() => {
    let base = 0;
    let paid = 0;
    let penalty = 0;
    let debt = 0;
    
    salaryList.value.forEach(s => {
        base += s.base_salary;
        paid += s.paid;
        penalty += s.penalty;
        debt += s.debt;
    });
    
    return { base, paid, penalty, debt };
});

function exportSalariesToExcel() {
    let csvContent = "\uFEFF"; // UTF-8 BOM
    csvContent += "№,Xodim,Asosiy maosh,Bonus,Jarima,Avans,To'langan summa,To'lov sana,Qarzdorlik,Holat\n";
    
    salaryList.value.forEach(s => {
        csvContent += `${s.index},"${s.employee.name}",${s.base_salary},${s.bonus},${s.penalty},${s.advance},${s.paid},"${s.payment_date}",${s.debt},"${s.status}"\n`;
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", `Maosh_Hisoboti_${salarySelectedMonth.value}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Transaction Adjustment Modal State (`+` button)
const showAdjModal = ref(false);
const adjEmployee = ref(null);
const adjForm = useForm({
    type: 'salary_accrual', // or 'penalty', 'advance_payment'
    amount: '',
    description: '',
    date: new Date().toISOString().split('T')[0]
});

function openAdjModal(emp) {
    adjEmployee.value = emp;
    adjForm.reset();
    adjForm.type = 'salary_accrual';
    adjForm.date = new Date().toISOString().split('T')[0];
    showAdjModal.value = true;
}

function submitAdj() {
    if (!adjForm.amount || adjForm.amount <= 0) {
        alert("Summani to'g'ri kiriting!");
        return;
    }
    isProcessing.value = true;
    
    // Add "Bonus" to description if type is salary_accrual for tracking
    let desc = adjForm.description;
    if (adjForm.type === 'salary_accrual' && !desc.toLowerCase().includes('bonus')) {
        desc = "Bonus: " + desc;
    }

    const payload = {
        type: adjForm.type,
        amount: adjForm.amount,
        description: desc,
        date: adjForm.date
    };

    if (adjEmployee.value.type === 'worker') {
        router.post(route('admin.hr.worker.transaction.store', adjEmployee.value.id), payload, {
            onSuccess: () => { showAdjModal.value = false; isProcessing.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.hr.users.transaction.store', adjEmployee.value.id), payload, {
            onSuccess: () => { showAdjModal.value = false; isProcessing.value = false; },
            onError: () => isProcessing.value = false
        });
    }
}

// Payment Modal State (`To'lov` button)
const showPayModal = ref(false);
const payEmployee = ref(null);
const payForm = useForm({
    amount: '',
    description: 'Oylik maosh to\'lovi',
    payment_method: 'cash',
    account_type: 'main',
    date: new Date().toISOString().split('T')[0]
});

function openPayModal(sal) {
    payEmployee.value = sal.employee;
    payForm.reset();
    payForm.amount = sal.debt > 0 ? sal.debt : '';
    payForm.description = 'Oylik maosh to\'lovi';
    payForm.payment_method = 'cash';
    payForm.account_type = 'main';
    payForm.date = new Date().toISOString().split('T')[0];
    showPayModal.value = true;
}

function submitPay() {
    if (!payForm.amount || payForm.amount <= 0) {
        alert("Summani to'g'ri kiriting!");
        return;
    }
    isProcessing.value = true;
    
    const payload = {
        type: 'payment',
        amount: payForm.amount,
        description: `${payForm.description} (${payForm.payment_method === 'cash' ? 'Naqd' : (payForm.payment_method === 'card' ? 'Karta' : 'Bank o\'tkazmasi')})`,
        date: payForm.date,
        payment_method: payForm.payment_method,
        account_type: payForm.account_type
    };

    if (payEmployee.value.type === 'worker') {
        router.post(route('admin.hr.worker.transaction.store', payEmployee.value.id), payload, {
            onSuccess: () => { showPayModal.value = false; isProcessing.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.hr.users.transaction.store', payEmployee.value.id), payload, {
            onSuccess: () => { showPayModal.value = false; isProcessing.value = false; },
            onError: () => isProcessing.value = false
        });
    }
}

// Tab 2: KPI calculations and Settings Drawer
const showKpiDrawer = ref(false);
const kpiForm = useForm({
    comparison_type: '<=',
    start_val: '2',
    end_val: '0',
    bonus_amount: '1000',
    fixed_salary: '0'
});

// Load KPI Settings
watch(() => props.kpiSettings, (settings) => {
    if (settings && settings.value) {
        try {
            const data = JSON.parse(settings.value);
            kpiForm.comparison_type = data.comparison_type || '<=';
            kpiForm.start_val = data.start_val || '2';
            kpiForm.end_val = data.end_val || '0';
            kpiForm.bonus_amount = data.bonus_amount || '1000';
            kpiForm.fixed_salary = data.fixed_salary || '0';
        } catch (e) {
            console.error("KPI parse error", e);
        }
    }
}, { immediate: true });

function submitKpiSettings() {
    isProcessing.value = true;
    router.post(route('admin.hr.kpi_settings.store'), kpiForm, {
        onSuccess: () => { showKpiDrawer.value = false; isProcessing.value = false; },
        onError: () => isProcessing.value = false
    });
}

const calculateKpiAndExpectedSalary = (leadsCount) => {
    const comp = kpiForm.comparison_type;
    const start = parseFloat(kpiForm.start_val) || 0;
    const bonus = parseFloat(kpiForm.bonus_amount) || 0;
    const fixed = parseFloat(kpiForm.fixed_salary) || 0;
    
    let conditionMet = false;
    if (comp === '<=') conditionMet = leadsCount <= start;
    else if (comp === '>=') conditionMet = leadsCount >= start;
    else if (comp === '=') conditionMet = leadsCount === start;
    else if (comp === '<') conditionMet = leadsCount < start;
    else if (comp === '>') conditionMet = leadsCount > start;
    
    const kpiBonus = conditionMet ? (leadsCount * bonus) : 0;
    return {
        kpi: kpiBonus,
        salary: fixed + kpiBonus
    };
};

const kpiManagers = computed(() => {
    // Return all system users having leads or roles related to sales
    return allEmployees.value.filter(e => e.type === 'user').map(u => {
        const kpiCalc = calculateKpiAndExpectedSalary(u.leads_count);
        return {
            id: u.id,
            name: u.name,
            leads_count: u.leads_count,
            kpi: kpiCalc.kpi,
            expected_salary: kpiCalc.salary
        };
    });
});

// Sales Agents Tab states (moved from CRM to HRM)
const salesBuildingFilter = ref('');
const salesSearchQuery = ref('');
const salesDateFrom = ref('');
const salesDateTo = ref('');

const getAgentInitials = (name) => {
    if (!name) return 'S';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        // "Shavkatov Abubakir" -> "AS" (initials reversed for Uzbek name syntax)
        return (parts[1][0] + parts[0][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
};

const processedSalesAgentsData = computed(() => {
    const users = props.users || [];
    return users.map(user => {
        const leads = user.leads || [];
        const contracts = user.contracts || [];
        
        let filteredLeads = leads;
        let filteredContracts = contracts;
        
        if (salesBuildingFilter.value) {
            filteredContracts = contracts.filter(c => {
                const bId = c.unit?.floor?.building_id;
                return bId === Number(salesBuildingFilter.value);
            });
        }
        
        if (salesDateFrom.value) {
            const from = new Date(salesDateFrom.value);
            filteredLeads = filteredLeads.filter(l => new Date(l.created_at) >= from);
            filteredContracts = filteredContracts.filter(c => new Date(c.created_at) >= from);
        }
        if (salesDateTo.value) {
            const to = new Date(salesDateTo.value);
            to.setHours(23, 59, 59, 999);
            filteredLeads = filteredLeads.filter(l => new Date(l.created_at) <= to);
            filteredContracts = filteredContracts.filter(c => new Date(c.created_at) <= to);
        }
        
        const activeContracts = filteredContracts.filter(c => c.status !== 'cancelled' && c.status !== 'canceled');
        const soldCount = activeContracts.length;
        const soldAmount = activeContracts.reduce((sum, c) => sum + Number(c.total_amount || 0), 0);
        
        const newLeadsCount = filteredLeads.filter(l => l.status === 'new').length;
        const totalLeadsCount = filteredLeads.length;
        const lostLeadsCount = filteredLeads.filter(l => l.status === 'lost').length;
        
        const conversionRate = totalLeadsCount > 0 ? (soldCount / totalLeadsCount) * 100 : 0;
        
        return {
            id: user.id,
            name: user.name,
            phone: user.phone,
            soldCount,
            soldAmount,
            newLeadsCount,
            totalLeadsCount,
            lostLeadsCount,
            conversionRate
        };
    });
});

const filteredSalesAgentsData = computed(() => {
    let data = processedSalesAgentsData.value;
    
    if (salesSearchQuery.value) {
        const query = salesSearchQuery.value.toLowerCase().trim();
        data = data.filter(item => {
            return (item.name || '').toLowerCase().includes(query) || 
                   (item.phone || '').toLowerCase().includes(query);
        });
    }
    
    return data;
});

const calculatedSalesStats = computed(() => {
    const agents = filteredSalesAgentsData.value;
    const totalAgents = agents.length;
    
    const totalSold = agents.reduce((sum, a) => sum + a.soldCount, 0);
    const totalLeads = agents.reduce((sum, a) => sum + a.totalLeadsCount, 0);
    const newLeads = agents.reduce((sum, a) => sum + a.newLeadsCount, 0);
    
    const avgConversion = totalLeads > 0 ? (totalSold / totalLeads) * 100 : 0;
    
    return {
        totalAgents,
        totalSold,
        totalLeads,
        newLeads,
        avgConversion
    };
});

const exportSalesStatsToExcel = () => {
    const data = filteredSalesAgentsData.value;
    let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
    csvContent += "№;Ism;Sotilgan xonadonlar;Sotilgan xonadonlar narxi;Yangi lidlar;Jami lidlar;Rad etilgan;Sotuv konversiyasi\n";
    
    data.forEach((item, index) => {
        const name = (item.name || '').replace(/;/g, ' ');
        const phone = (item.phone || '').replace(/;/g, ' ');
        csvContent += `${index + 1};"${name} (${phone})";${item.soldCount};${item.soldAmount};${item.newLeadsCount};${item.totalLeadsCount};${item.lostLeadsCount};"${item.conversionRate.toFixed(1)}%"\n`;
    });
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `sotuv_xodimlari_statistikasi_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <Head title="HRM & Ish Haqlar" />
    
    <AdminLayout>
        <div class="p-6 space-y-6 max-w-7xl mx-auto">
            
            <!-- Tab 0: Xodimlar (Employees) -->
            <div v-if="activeTab === 0" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                
                <!-- 5 Premium Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
                    <!-- card 1 -->
                    <div @click="openHRReportModal('total')" class="bg-blue-50/70 border border-blue-200/50 rounded-2xl p-5 flex flex-col justify-between shadow-sm relative overflow-hidden transition-all duration-300 hover:scale-[1.02] cursor-pointer hover:bg-blue-100/50">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-blue-700 font-display">Umumiy xodimlar soni</span>
                            <span class="text-blue-500 text-lg">👤</span>
                        </div>
                        <h2 class="text-4xl font-extrabold text-blue-900 font-display">{{ stats.total }}</h2>
                    </div>
                    
                    <!-- card 2 -->
                    <div @click="openHRReportModal('active')" class="bg-teal-50/70 border border-teal-200/50 rounded-2xl p-5 flex flex-col justify-between shadow-sm relative overflow-hidden transition-all duration-300 hover:scale-[1.02] cursor-pointer hover:bg-teal-100/50">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-teal-700 font-display">Faol xodimlar soni</span>
                            <span class="text-teal-500 text-lg">👥</span>
                        </div>
                        <h2 class="text-4xl font-extrabold text-teal-800 font-display">{{ stats.active }}</h2>
                    </div>
                    
                    <!-- card 3 -->
                    <div @click="openHRReportModal('permanent')" class="bg-purple-50/70 border border-purple-200/50 rounded-2xl p-5 flex flex-col justify-between shadow-sm relative overflow-hidden transition-all duration-300 hover:scale-[1.02] cursor-pointer hover:bg-purple-100/50">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-purple-700 font-display">Doimiy ishchilar soni</span>
                            <span class="text-purple-500 text-lg">🛡️</span>
                        </div>
                        <h2 class="text-4xl font-extrabold text-purple-800 font-display">{{ stats.permanent }}</h2>
                    </div>
                    
                    <!-- card 4 -->
                    <div @click="openHRReportModal('contract')" class="bg-amber-50/70 border border-amber-200/50 rounded-2xl p-5 flex flex-col justify-between shadow-sm relative overflow-hidden transition-all duration-300 hover:scale-[1.02] cursor-pointer hover:bg-amber-100/50">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-amber-700 font-display">Yollanma ishchilar soni</span>
                            <span class="text-amber-500 text-lg">🚜</span>
                        </div>
                        <h2 class="text-4xl font-extrabold text-amber-800 font-display">{{ stats.contract }}</h2>
                    </div>
                    
                    <!-- card 5 -->
                    <div @click="openHRReportModal('fired')" class="bg-rose-50/70 border border-rose-200/50 rounded-2xl p-5 flex flex-col justify-between shadow-sm relative overflow-hidden transition-all duration-300 hover:scale-[1.02] cursor-pointer hover:bg-rose-100/50">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-rose-700 font-display">Bo'shatilgan ishchilar soni</span>
                            <span class="text-rose-500 text-lg">⚠️</span>
                        </div>
                        <h2 class="text-4xl font-extrabold text-rose-800 font-display">{{ stats.fired }}</h2>
                    </div>
                </div>

                <!-- Search & Filters Bar -->
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4 flex flex-col md:flex-row gap-4 items-center justify-between">
                    <div class="flex-1 w-full relative">
                        <span class="absolute left-3.5 top-2.5 text-gray-400">🔍</span>
                        <input 
                            v-model="searchQuery" 
                            type="text" 
                            placeholder="Qidirish..." 
                            class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50/50"
                        />
                    </div>
                    
                    <div class="flex flex-wrap md:flex-nowrap gap-3 items-center w-full md:w-auto">
                        <!-- Filters popup or selector -->
                        <select v-model="filterRole" class="py-2 pl-3 pr-8 border border-gray-200 rounded-xl text-xs bg-gray-50/50 text-gray-600 focus:ring-2 focus:ring-blue-500">
                            <option value="">Barcha lavozimlar</option>
                            <option value="Super_Admin">Super Admin</option>
                            <option value="Operator">Operator</option>
                            <option value="Partner">Hamkor</option>
                            <option value="Sotuvchi">Sotuvchi</option>
                            <option value="builder">Quruvchi / Usta</option>
                            <option value="driver">Haydovchi</option>
                            <option value="guard">Qorovul</option>
                        </select>
                        
                        <select v-model="filterBuilding" class="py-2 pl-3 pr-8 border border-gray-200 rounded-xl text-xs bg-gray-50/50 text-gray-600 focus:ring-2 focus:ring-blue-500">
                            <option value="">Barcha binolar</option>
                            <option value="all">Faqat Barcha</option>
                            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        
                        <select v-model="filterStatus" class="py-2 pl-3 pr-8 border border-gray-200 rounded-xl text-xs bg-gray-50/50 text-gray-600 focus:ring-2 focus:ring-blue-500">
                            <option value="">Barcha holatlar</option>
                            <option value="active">Ish holatida</option>
                            <option value="fired">Bo'shatilgan</option>
                        </select>
                        
                        <button 
                            @click="openAddEmployee" 
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-xl transition-colors shrink-0 shadow-sm flex items-center gap-1.5"
                        >
                            <span>+</span> Xodim qo'shish
                        </button>
                    </div>
                </div>

                <!-- Xodimlar Table -->
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl overflow-hidden">
                    <div class="p-5 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900 font-display">Xodimlar</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse whitespace-nowrap text-sm">
                            <thead class="bg-gray-50/50 text-gray-500 font-semibold text-xs border-b border-gray-100">
                                <tr>
                                    <th class="p-4 w-12">ID</th>
                                    <th class="p-4">Ism</th>
                                    <th class="p-4">Lavozim</th>
                                    <th class="p-4">Bino</th>
                                    <th class="p-4">Ishga olingan kun</th>
                                    <th class="p-4">Holat</th>
                                    <th class="p-4 text-right">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="emp in filteredEmployees" :key="emp.id + '-' + emp.type" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4 font-mono text-gray-500">{{ emp.id }}</td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-orange-100 flex items-center justify-center font-bold text-orange-700 shrink-0 uppercase">
                                                {{ emp.name.substring(0, 2) }}
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-gray-900 text-sm uppercase">{{ emp.name }}</h4>
                                                <p class="text-xs text-gray-500 font-medium mt-0.5">{{ emp.phone }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-700 capitalize">{{ emp.role_label }}</td>
                                    <td class="p-4 text-gray-700">{{ emp.building_name }}</td>
                                    <td class="p-4 text-gray-700">{{ formatDate(emp.employment_date) }}</td>
                                    <td class="p-4">
                                        <span 
                                            class="inline-flex px-3 py-1 rounded-full text-xs font-semibold"
                                            :class="emp.status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                                        >
                                            {{ emp.status_label }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button 
                                                @click="openDetailModal(emp)" 
                                                class="p-1.5 bg-gray-50 hover:bg-gray-100 rounded-lg text-gray-600 transition-colors shadow-sm"
                                                title="Ko'rish"
                                            >
                                                👁️
                                            </button>
                                            <button 
                                                @click="openEditEmployee(emp)" 
                                                class="p-1.5 bg-blue-50 hover:bg-blue-100 rounded-lg text-blue-600 transition-colors shadow-sm"
                                                title="Tahrirlash"
                                            >
                                                ✏️
                                            </button>
                                            <button 
                                                @click="deleteEmployee(emp)" 
                                                class="p-1.5 bg-red-50 hover:bg-red-100 rounded-lg text-red-600 transition-colors shadow-sm"
                                                title="O'chirish"
                                            >
                                                🗑️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredEmployees.length === 0">
                                    <td colspan="7" class="p-10 text-center text-gray-400 font-medium">Xodimlar topilmadi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Tab: Sotuv xodimlari (Sales Agents Performance & Stats) -->
            <div v-else-if="activeTab === 'sales_agents'" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                <!-- Header bar with title, subtitle and filters (Image 1 style) -->
                <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                    <div class="flex items-center gap-4">
                        <Link :href="route('admin.hr', { tab: 0 })" class="w-10 h-10 rounded-full border border-gray-200 hover:bg-gray-50 flex items-center justify-center transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 font-display">Sotuv xodimi</h1>
                            <p class="text-xs text-gray-500 font-medium mt-0.5">Barcha obyektlar bo'yicha sotuv statistikasi</p>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">
                        <!-- Object Filter -->
                        <div class="w-full sm:w-48">
                            <select v-model="salesBuildingFilter" class="w-full bg-white border border-gray-300 text-gray-700 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-amber-500">
                                <option value="">Barcha obyektlar</option>
                                <option v-for="b in props.buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>

                        <!-- Search Filter -->
                        <div class="relative flex-1 sm:flex-initial sm:w-48">
                            <span class="absolute left-3 top-2.5 text-gray-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input v-model="salesSearchQuery" type="text" placeholder="Qidirish" class="bg-white border border-gray-300 text-gray-900 rounded-xl pl-9 pr-3 py-2 text-xs w-full focus:ring-2 focus:ring-amber-500">
                        </div>

                        <!-- Datepicker Range -->
                        <div class="flex items-center gap-2 border border-gray-300 rounded-xl px-3 py-2 bg-white text-xs text-gray-600 w-full sm:w-auto">
                            <input type="date" v-model="salesDateFrom" class="border-0 p-0 text-xs text-gray-700 bg-transparent focus:ring-0 w-24">
                            <span class="text-gray-400">→</span>
                            <input type="date" v-model="salesDateTo" class="border-0 p-0 text-xs text-gray-700 bg-transparent focus:ring-0 w-24">
                            <span class="text-gray-400">
                                <svg class="w-4 h-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </span>
                        </div>

                        <!-- Excel Export -->
                        <button @click="exportSalesStatsToExcel" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-1.5 shrink-0 ml-auto xl:ml-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Excel
                        </button>
                    </div>
                </div>

                <!-- Orange gradient statistics bar (matching image 1) -->
                <div class="bg-gradient-to-r from-amber-500 to-orange-500 rounded-2xl p-6 text-white shadow-md grid grid-cols-2 md:grid-cols-5 gap-6 divide-y md:divide-y-0 md:divide-x divide-white/20">
                    <div class="flex flex-col justify-center p-2">
                        <span class="text-xs font-medium text-white/80">Jami sotuvchilar</span>
                        <span class="text-2xl font-bold mt-1">{{ calculatedSalesStats.totalAgents }} ta</span>
                    </div>
                    <div class="flex flex-col justify-center p-2 md:pl-6">
                        <span class="text-xs font-medium text-white/80">Jami sotilgan</span>
                        <span class="text-2xl font-bold mt-1">{{ calculatedSalesStats.totalSold }} ta</span>
                    </div>
                    <div class="flex flex-col justify-center p-2 md:pl-6">
                        <span class="text-xs font-medium text-white/80">Jami lidlar</span>
                        <span class="text-2xl font-bold mt-1">{{ calculatedSalesStats.totalLeads }} ta</span>
                    </div>
                    <div class="flex flex-col justify-center p-2 md:pl-6">
                        <span class="text-xs font-medium text-white/80">Yangi lidlar</span>
                        <span class="text-2xl font-bold mt-1">{{ calculatedSalesStats.newLeads }} ta</span>
                    </div>
                    <div class="flex flex-col justify-center p-2 md:pl-6">
                        <span class="text-xs font-medium text-white/80">O'rtacha konversiya</span>
                        <span class="text-2xl font-bold mt-1">{{ calculatedSalesStats.avgConversion.toFixed(1) }}%</span>
                    </div>
                </div>

                <!-- Agents performance table card -->
                <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm font-sans border-collapse">
                            <thead class="bg-gray-50 text-gray-500 text-xs font-semibold border-b border-gray-150 uppercase">
                                <tr>
                                    <th class="p-4 w-12 text-center">№</th>
                                    <th class="p-4">Ism</th>
                                    <th class="p-4 text-center">Sotilgan xonadonlar</th>
                                    <th class="p-4 text-center">Sotilgan xonadonlar narxi</th>
                                    <th class="p-4 text-center">Yangi lidlar</th>
                                    <th class="p-4 text-center">Jami lidlar</th>
                                    <th class="p-4 text-center">Rad etilgan</th>
                                    <th class="p-4 text-center">Sotuv konversiyasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <tr v-for="(agent, index) in filteredSalesAgentsData" :key="agent.id" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4 text-center font-mono text-xs text-gray-400">{{ index + 1 }}</td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-orange-100 flex items-center justify-center font-bold text-orange-700 shrink-0 uppercase text-xs">
                                                {{ getAgentInitials(agent.name) }}
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-gray-900 text-sm capitalize">{{ agent.name }}</h4>
                                                <p class="text-xs text-gray-500 font-medium mt-0.5">{{ agent.phone || '—' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-center font-mono font-semibold">{{ agent.soldCount }}</td>
                                    <td class="p-4 text-center font-mono text-gray-900">{{ formatPrice(agent.soldAmount) }}</td>
                                    <td class="p-4 text-center font-mono text-amber-600">{{ agent.newLeadsCount }}</td>
                                    <td class="p-4 text-center font-mono">{{ agent.totalLeadsCount }}</td>
                                    <td class="p-4 text-center font-mono text-red-500">{{ agent.lostLeadsCount }}</td>
                                    <td class="p-4 text-center">
                                        <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-bold font-mono" :class="agent.conversionRate > 20 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : (agent.conversionRate > 0 ? 'bg-orange-50 text-orange-700 border border-orange-200' : 'bg-gray-50 text-gray-600 border border-gray-150')">
                                            {{ agent.conversionRate.toFixed(1) }}%
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="filteredSalesAgentsData.length === 0">
                                    <td colspan="8" class="text-center py-20 text-gray-400 font-medium text-xs">
                                        Sotuv xodimlari topilmadi
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 1: Maoshlar (Salaries) -->
            <div v-if="activeTab === 1" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                
                <!-- Title & Excel Export -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h2 class="text-2xl font-extrabold text-gray-900 font-display">Maosh hisobotlari</h2>
                        <p class="text-sm text-gray-500 font-medium mt-1">Oy kesimida maosh, jarimalar, avanslar va qarzdorlik.</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-5 grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <!-- Search -->
                    <div class="md:col-span-6 space-y-1">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Qidirish</label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-2.5 text-gray-400">🔍</span>
                            <input 
                                v-model="salarySearchQuery" 
                                type="text" 
                                placeholder="F.I.O yoki telefon..." 
                                class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50/50"
                            />
                        </div>
                    </div>
                    
                    <!-- Month Selector -->
                    <div class="md:col-span-3 space-y-1">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Oy</label>
                        <input 
                            v-model="salarySelectedMonth" 
                            type="month" 
                            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 bg-white text-gray-700"
                        />
                    </div>
                    
                    <!-- Currency Selector -->
                    <div class="md:col-span-3 space-y-1">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Valyuta</label>
                        <select 
                            v-model="salaryCurrency" 
                            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 bg-white text-gray-700"
                        >
                            <option value="so'm">So'm</option>
                            <option value="usd">Dollar</option>
                        </select>
                    </div>
                </div>

                <!-- 4 Statistics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <!-- Card 1: Jami asosiy maosh -->
                    <div @click="openSalaryReportModal('base')" class="bg-blue-50/70 border border-blue-200/50 rounded-2xl p-5 flex items-center gap-4 shadow-sm relative overflow-hidden transition-all duration-300 hover:scale-[1.02] cursor-pointer hover:bg-blue-100/50">
                        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center font-bold text-blue-700 text-xl shrink-0">
                            📄
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-xs font-semibold text-blue-700 font-display">Jami asosiy maosh</p>
                            <h3 class="text-xl font-extrabold text-blue-900 font-display">{{ formatPrice(salaryTotals.base) }}</h3>
                        </div>
                    </div>
                    
                    <!-- Card 2: Jami to'langan -->
                    <div @click="openSalaryReportModal('paid')" class="bg-emerald-50/70 border border-emerald-200/50 rounded-2xl p-5 flex items-center gap-4 shadow-sm relative overflow-hidden transition-all duration-300 hover:scale-[1.02] cursor-pointer hover:bg-emerald-100/50">
                        <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center font-bold text-emerald-700 text-xl shrink-0">
                            💵
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-xs font-semibold text-emerald-700 font-display">Jami to'langan</p>
                            <h3 class="text-xl font-extrabold text-emerald-800 font-display">{{ formatPrice(salaryTotals.paid) }}</h3>
                        </div>
                    </div>
                    
                    <!-- Card 3: Jami jarima -->
                    <div @click="openSalaryReportModal('penalty')" class="bg-rose-50/70 border border-rose-200/50 rounded-2xl p-5 flex items-center gap-4 shadow-sm relative overflow-hidden transition-all duration-300 hover:scale-[1.02] cursor-pointer hover:bg-rose-100/50">
                        <div class="w-12 h-12 rounded-xl bg-rose-100 flex items-center justify-center font-bold text-rose-700 text-xl shrink-0">
                            ⚠️
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-xs font-semibold text-rose-700 font-display">Jami jarima</p>
                            <h3 class="text-xl font-extrabold text-rose-800 font-display">{{ formatPrice(salaryTotals.penalty) }}</h3>
                        </div>
                    </div>
                    
                    <!-- Card 4: Jami qoldiq -->
                    <div @click="openSalaryReportModal('debt')" class="bg-purple-50/70 border border-purple-200/50 rounded-2xl p-5 flex items-center gap-4 shadow-sm relative overflow-hidden transition-all duration-300 hover:scale-[1.02] cursor-pointer hover:bg-purple-100/50">
                        <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center font-bold text-purple-700 text-xl shrink-0">
                            🏛️
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-xs font-semibold text-purple-700 font-display">Jami qoldiq</p>
                            <h3 class="text-xl font-extrabold text-purple-900 font-display">{{ formatPrice(salaryTotals.debt) }}</h3>
                        </div>
                    </div>
                </div>

                <!-- Salaries Table List -->
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl overflow-hidden">
                    <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="text-base font-extrabold text-gray-900 font-display uppercase tracking-wider">
                            Maoshlar ro'yxati - {{ selectedMonthLabel }} ({{ salaryCurrency === 'usd' ? 'USD' : 'UZS' }})
                        </h3>
                        <button 
                            @click="generatePayroll" 
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-xl shadow-sm transition-colors"
                        >
                            Oylik hisobotini shakllantirish
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse whitespace-nowrap text-sm">
                            <thead class="bg-gray-50/50 text-gray-500 font-semibold text-xs border-b border-gray-100">
                                <tr>
                                    <th class="p-4 w-12">№</th>
                                    <th class="p-4">Xodim</th>
                                    <th class="p-4">Asosiy maosh</th>
                                    <th class="p-4">Bonus</th>
                                    <th class="p-4">Jarima</th>
                                    <th class="p-4">Avans</th>
                                    <th class="p-4">To'langan summa</th>
                                    <th class="p-4">To'lov sana</th>
                                    <th class="p-4">Qarzdorlik</th>
                                    <th class="p-4">Holat</th>
                                    <th class="p-4 text-right">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="sal in salaryList" :key="sal.employee.id + '-' + sal.employee.type" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4 font-mono text-gray-500">{{ sal.index }}</td>
                                    <td class="p-4 font-bold text-gray-900 uppercase">{{ sal.employee.name }}</td>
                                    <td class="p-4 text-gray-700 font-medium">{{ formatPrice(sal.base_salary) }}</td>
                                    <td class="p-4 text-emerald-600 font-semibold">+ {{ formatPrice(sal.bonus) }}</td>
                                    <td class="p-4 text-rose-600 font-semibold">- {{ formatPrice(sal.penalty) }}</td>
                                    <td class="p-4 text-amber-600 font-semibold">- {{ formatPrice(sal.advance) }}</td>
                                    <td class="p-4 text-blue-600 font-semibold">{{ formatPrice(sal.paid) }}</td>
                                    <td class="p-4 text-gray-500 font-medium">{{ sal.payment_date }}</td>
                                    <td class="p-4 font-bold" :class="sal.debt > 0 ? 'text-rose-600' : 'text-gray-900'">{{ formatPrice(sal.debt) }}</td>
                                    <td class="p-4">
                                        <span 
                                            class="inline-flex px-3 py-1 rounded-full text-xs font-semibold"
                                            :class="sal.status === 'To\'langan' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : (sal.status === 'Qisman to\'langan' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-amber-50 text-amber-700 border border-amber-200')"
                                        >
                                            {{ sal.status }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <!-- Adjustment button -->
                                            <button 
                                                @click="openAdjModal(sal.employee)" 
                                                class="px-2 py-1 bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 rounded-lg text-xs font-bold transition-all shadow-sm"
                                                title="Bonus / Jarima / Avans"
                                            >
                                                +
                                            </button>
                                            <!-- Payout button -->
                                            <button 
                                                @click="openPayModal(sal)" 
                                                class="px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold transition-all shadow-sm"
                                            >
                                                💵 To'lov
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="salaryList.length === 0">
                                    <td colspan="11" class="p-10 text-center text-gray-400 font-medium">Xodimlar topilmadi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Tab 2: KPI -->
            <div v-if="activeTab === 2" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                
                <div class="bg-white border border-gray-100 shadow-sm rounded-2xl overflow-hidden">
                    <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-900 font-display">KPI</h3>
                        <button 
                            @click="showKpiDrawer = true" 
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-xl shadow-sm transition-colors flex items-center gap-1.5"
                        >
                            ⚙️ Sozlamalar
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse whitespace-nowrap text-sm">
                            <thead class="bg-gray-50/50 text-gray-500 font-semibold text-xs border-b border-gray-100">
                                <tr>
                                    <th class="p-4 w-16">ID</th>
                                    <th class="p-4">Sotuv menejeri</th>
                                    <th class="p-4 text-center">Sotuv bo'lgan lidlar soni</th>
                                    <th class="p-4">Umumiy KPI</th>
                                    <th class="p-4">Kutilgan maosh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="mgr in kpiManagers" :key="mgr.id" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4 font-mono text-gray-500">{{ mgr.id }}</td>
                                    <td class="p-4 font-bold text-gray-900 uppercase">{{ mgr.name }}</td>
                                    <td class="p-4 text-center text-gray-700 font-semibold">{{ mgr.leads_count }}</td>
                                    <td class="p-4 text-emerald-600 font-bold">{{ formatPrice(mgr.kpi) }}</td>
                                    <td class="p-4 text-gray-900 font-extrabold">{{ formatPrice(mgr.expected_salary) }}</td>
                                </tr>
                                <tr v-if="kpiManagers.length === 0">
                                    <td colspan="5" class="p-10 text-center text-gray-400 font-medium">Ma'lumot topilmadi</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>

        <!-- 1. Employee Detail Modal (Image 2) -->
        <div v-if="showDetailModal && selectedEmployee" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-3xl w-full max-w-5xl shadow-2xl overflow-hidden animate-[zoomIn_0.2s_ease-out] max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center shrink-0">
                    <h3 class="text-base font-bold text-gray-900 font-display">Xodim ma'lumotlari</h3>
                    <button @click="showDetailModal = false" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors text-gray-500 font-bold">✕</button>
                </div>
                
                <!-- Content -->
                <div class="p-6 overflow-y-auto flex-1 grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Column 1 (Left): 4/12 -->
                    <div class="lg:col-span-4 space-y-6">
                        <!-- Profile Card -->
                        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm text-center lg:text-left flex flex-col lg:flex-row items-center gap-4">
                            <div class="w-16 h-16 rounded-2xl bg-amber-500 flex items-center justify-center font-bold text-white text-2xl uppercase shrink-0">
                                {{ selectedEmployee.name.substring(0,2) }}
                            </div>
                            <div class="space-y-1">
                                <h4 class="text-base font-black text-gray-900 uppercase line-clamp-2 leading-tight">{{ selectedEmployee.name }}</h4>
                                <div class="flex items-center gap-2 mt-1 justify-center lg:justify-start">
                                    <span class="text-xs text-gray-500 font-bold capitalize">{{ selectedEmployee.role_label }}</span>
                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-[10px] font-bold text-emerald-700">{{ selectedEmployee.status_label }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- General Information -->
                        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm divide-y divide-gray-100 text-sm">
                            <div class="p-4 flex justify-between items-center">
                                <span class="text-gray-500 font-medium">Telefon raqam</span>
                                <span class="font-bold text-gray-900">{{ selectedEmployee.phone || '-' }}</span>
                            </div>
                            <div class="p-4 flex justify-between items-center">
                                <span class="text-gray-500 font-medium">Jinsi</span>
                                <span class="font-bold text-gray-900 capitalize">{{ selectedEmployee.gender }}</span>
                            </div>
                            <div class="p-4 flex justify-between items-center">
                                <span class="text-gray-500 font-medium">Tug'ilgan sanasi</span>
                                <span class="font-bold text-gray-900">{{ formatDate(selectedEmployee.birth_date) }}</span>
                            </div>
                            <div class="p-4 flex justify-between items-center">
                                <span class="text-gray-500 font-medium">A'zolik sanasi</span>
                                <span class="font-bold text-gray-900">{{ formatDate(selectedEmployee.employment_date) }}</span>
                            </div>
                            <div class="p-4 flex justify-between items-center">
                                <span class="text-gray-500 font-medium">Mutaxassislik id</span>
                                <span class="font-bold text-gray-900">{{ selectedEmployee.specialty_id || '-' }}</span>
                            </div>
                            <div class="p-4 flex justify-between items-center">
                                <span class="text-gray-500 font-medium">Maosh</span>
                                <span class="font-extrabold text-emerald-600">{{ formatPrice(selectedEmployee.salary) }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Column 2 (Middle): 4/12 -->
                    <div class="lg:col-span-4 space-y-6">
                        <!-- Passport Info Card -->
                        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
                            <h4 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Passport ma'lumotlari</h4>
                            
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-gray-50 flex items-center justify-center text-gray-500">📄</div>
                                <div class="flex-1">
                                    <p class="text-xs text-gray-400 font-medium">Passport ID:</p>
                                    <p class="text-sm font-bold text-gray-900 uppercase">{{ selectedEmployee.passport || '-' }}</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3 mt-3">
                                <div class="w-9 h-9 rounded-xl bg-gray-50 flex items-center justify-center text-gray-500">❓</div>
                                <div class="flex-1">
                                    <p class="text-xs text-gray-400 font-medium">JSHSHR:</p>
                                    <p class="text-sm font-bold text-gray-900">{{ selectedEmployee.pinfl || '-' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Image attachments -->
                        <div class="grid grid-cols-2 gap-4">
                            <div 
                                @click="triggerPhotoUpload('photo')" 
                                class="border border-dashed border-gray-200 rounded-2xl h-36 flex flex-col items-center justify-center bg-gray-50/50 hover:bg-gray-100/50 cursor-pointer transition-colors p-4 relative group"
                            >
                                <img 
                                    v-if="selectedEmployee.photo_path" 
                                    :src="selectedEmployee.photo_path.startsWith('data:') || selectedEmployee.photo_path.startsWith('/') ? selectedEmployee.photo_path : '/storage/' + selectedEmployee.photo_path" 
                                    class="w-full h-full object-cover rounded-xl"
                                />
                                <template v-else>
                                    <span class="text-gray-400 text-lg mb-1">📷</span>
                                    <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider text-center">Profil rasmi</span>
                                </template>
                                <div class="absolute inset-0 bg-black/40 rounded-xl opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs font-bold transition-opacity">Yuklash</div>
                            </div>

                            <div class="border border-dashed border-gray-200 rounded-2xl h-36 flex flex-col items-center justify-center bg-gray-50/50 p-2 relative">
                                <img 
                                    v-if="selectedEmployee.passport_front" 
                                    :src="selectedEmployee.passport_front" 
                                    class="w-full h-full object-cover rounded-xl"
                                />
                                <template v-else>
                                    <span class="text-gray-400 text-lg mb-1">📄</span>
                                    <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider text-center">Passport (oldi)</span>
                                </template>
                            </div>
                        </div>

                        <div v-if="selectedEmployee.passport_back" class="grid grid-cols-1">
                            <div class="border border-dashed border-gray-200 rounded-2xl h-36 flex flex-col items-center justify-center bg-gray-50/50 p-2 relative">
                                <img 
                                    :src="selectedEmployee.passport_back" 
                                    class="w-full h-full object-cover rounded-xl"
                                />
                            </div>
                        </div>
                        
                        <input ref="photoInput" type="file" class="hidden" accept="image/*" @change="handlePhotoUpload" />

                        <!-- Assigned building -->
                        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
                            <h4 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Ishlayotgan binosi</h4>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-gray-50 flex items-center justify-center text-gray-500">🏢</div>
                                <div>
                                    <p class="text-sm font-extrabold text-gray-900">{{ selectedEmployee.building_name }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Kod: {{ selectedEmployee.building_id || 'Barchaga ruxsat' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column 3 (Right): 4/12 -->
                    <div class="lg:col-span-4 space-y-6">
                        <!-- Contract card -->
                        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
                            <h4 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Shartnoma va Hujjatlar</h4>
                            <div class="text-xs space-y-2 text-gray-700">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Shartnoma turi:</span>
                                    <span class="font-bold">
                                        {{ selectedEmployee.contract_type === 'labor' ? 'Mexnat shartnomasi' : (selectedEmployee.contract_type === 'civil' ? 'FHSH' : 'Kiritilmagan') }}
                                    </span>
                                </div>
                                <div v-if="selectedEmployee.characteristics && (typeof selectedEmployee.characteristics === 'string' ? JSON.parse(selectedEmployee.characteristics) : selectedEmployee.characteristics).length > 0" class="mt-3">
                                    <span class="text-gray-500 block mb-1">Xarakterlar:</span>
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="(char, cIdx) in (typeof selectedEmployee.characteristics === 'string' ? JSON.parse(selectedEmployee.characteristics) : selectedEmployee.characteristics)" :key="cIdx" class="px-2 py-0.5 rounded-full bg-gray-100 border border-gray-200 text-[10px] font-semibold text-gray-600">
                                            {{ char }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="selectedEmployee.additional_documents && (typeof selectedEmployee.additional_documents === 'string' ? JSON.parse(selectedEmployee.additional_documents) : selectedEmployee.additional_documents).length > 0" class="mt-3">
                                    <span class="text-gray-500 block mb-1">Qo'shimcha hujjatlar:</span>
                                    <div class="space-y-1.5">
                                        <a 
                                            v-for="(doc, dIdx) in (typeof selectedEmployee.additional_documents === 'string' ? JSON.parse(selectedEmployee.additional_documents) : selectedEmployee.additional_documents)" 
                                            :key="dIdx" 
                                            :href="doc.path" 
                                            target="_blank"
                                            class="flex items-center gap-1.5 text-blue-600 hover:underline font-semibold"
                                        >
                                            📄 <span class="truncate max-w-[180px]">{{ doc.name }}</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order & application tabs -->
                        <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm flex flex-col h-56">
                            <div class="flex border-b border-gray-100 shrink-0">
                                <button 
                                    @click="rightTab = 'ariza'" 
                                    class="flex-1 py-3 text-xs font-bold border-b-2 text-center transition-colors"
                                    :class="rightTab === 'ariza' ? 'border-amber-500 text-gray-900 bg-gray-50/20' : 'border-transparent text-gray-400 hover:text-gray-600'"
                                >
                                    Ariza
                                </button>
                                <button 
                                    @click="rightTab = 'buyruq'" 
                                    class="flex-1 py-3 text-xs font-bold border-b-2 text-center transition-colors"
                                    :class="rightTab === 'buyruq' ? 'border-amber-500 text-gray-900 bg-gray-50/20' : 'border-transparent text-gray-400 hover:text-gray-600'"
                                >
                                    Buyruq
                                </button>
                            </div>
                            <div class="p-6 flex-1 flex items-center justify-center text-xs text-gray-400 font-semibold">
                                Ma'lumot mavjud emas
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Employee Add/Edit Modal -->
        <div v-if="showEmployeeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-3xl w-full max-w-3xl shadow-2xl overflow-hidden animate-[zoomIn_0.2s_ease-out] max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 bg-white border-b border-gray-100 flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="showEmployeeModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <h3 class="text-xl font-bold text-gray-900 font-display">
                            {{ isEditMode ? 'Xodim ma\'lumotlarini tahrirlash' : 'Xodim qo\'shish' }}
                        </h3>
                    </div>
                </div>
                
                <form @submit.prevent="saveEmployee" class="p-6 space-y-6 overflow-y-auto flex-1">
                    <!-- Employee Type Selection (User vs Worker) -->
                    <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Xodim turi</label>
                            <select 
                                v-model="employeeForm.type" 
                                :disabled="isEditMode"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                            >
                                <option value="worker">Qurilish ishchisi (Site Worker)</option>
                                <option value="user">Tizim xodimi (System User)</option>
                            </select>
                        </div>
                        <div v-if="employeeForm.type === 'user'">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Elektron pochta (Email) *</label>
                            <input 
                                v-model="employeeForm.email" 
                                type="email" 
                                required
                                placeholder="email@domain.com"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                            />
                        </div>
                        <div v-if="employeeForm.type === 'user' && !isEditMode">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Parol *</label>
                            <input 
                                v-model="employeeForm.password" 
                                type="password" 
                                required
                                placeholder="Kamida 6 xonali parol"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                            />
                        </div>
                    </div>

                    <!-- Ism, Familiya, Sharif -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                <span class="text-red-500">*</span> Ism
                            </label>
                            <input 
                                v-model="employeeForm.first_name" 
                                type="text" 
                                required
                                placeholder="Ismni kiriting"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                <span class="text-red-500">*</span> Familiya
                            </label>
                            <input 
                                v-model="employeeForm.last_name" 
                                type="text" 
                                required
                                placeholder="Familiyani kiriting"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                Sharif
                            </label>
                            <input 
                                v-model="employeeForm.middle_name" 
                                type="text" 
                                placeholder="Sharifni kiriting"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                            />
                        </div>
                    </div>

                    <!-- Passport raqami, JSHSHIR -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                Passport raqami
                            </label>
                            <input 
                                v-model="employeeForm.passport" 
                                type="text" 
                                placeholder="AD1234567"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white uppercase"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                JSHSHIR
                            </label>
                            <input 
                                v-model="employeeForm.pinfl" 
                                type="text" 
                                maxlength="14"
                                placeholder="12345678901234"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                            />
                        </div>
                    </div>

                    <!-- Tug'ilgan sana, Qo'shilgan sana -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                Tug'ilgan sana
                            </label>
                            <input 
                                v-model="employeeForm.birth_date" 
                                type="date" 
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white text-gray-600"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                Qo'shilgan sana
                            </label>
                            <input 
                                v-model="employeeForm.employment_date" 
                                type="date" 
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white text-gray-600"
                            />
                        </div>
                    </div>

                    <!-- Telefon raqami, Jinsi -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                Telefon raqami
                            </label>
                            <input 
                                v-model="employeeForm.phone" 
                                type="text" 
                                placeholder="+998 XX XXX XX XX"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                <span class="text-red-500">*</span> Jinsi
                            </label>
                            <div class="flex items-center gap-6 py-2.5">
                                <label class="flex items-center gap-2 text-sm text-gray-700 font-semibold cursor-pointer">
                                    <input 
                                        type="radio" 
                                        value="Erkak" 
                                        v-model="employeeForm.gender"
                                        class="text-amber-500 focus:ring-amber-500 w-4 h-4"
                                    />
                                    Erkak
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 font-semibold cursor-pointer">
                                    <input 
                                        type="radio" 
                                        value="Ayol" 
                                        v-model="employeeForm.gender"
                                        class="text-amber-500 focus:ring-amber-500 w-4 h-4"
                                    />
                                    Ayol
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Ishlayotgan binosi, Lavozim -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                <span class="text-red-500">*</span> Ishlayotgan binosi
                            </label>
                            <select 
                                v-model="employeeForm.building_id"
                                :disabled="employeeForm.type === 'user'"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white disabled:bg-gray-50 disabled:text-gray-400"
                            >
                                <option value="">Binoni tanlang</option>
                                <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                <span class="text-red-500">*</span> Lavozim
                            </label>
                            <select 
                                v-if="employeeForm.type === 'worker'"
                                v-model="employeeForm.role"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                            >
                                <option value="">Lavozimni tanlang</option>
                                <option value="builder">Quruvchi / Usta</option>
                                <option value="driver">Haydovchi</option>
                                <option value="guard">Qorovul</option>
                                <option value="office">Office xodimi</option>
                            </select>
                            <select 
                                v-else
                                v-model="employeeForm.role"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                            >
                                <option value="">Lavozimni tanlang</option>
                                <option value="Super_Admin">Super Admin</option>
                                <option value="Operator">Operator</option>
                                <option value="Partner">Hamkor</option>
                                <option value="Sotuvchi">Sotuvchi</option>
                            </select>
                        </div>
                    </div>

                    <!-- Maosh, Valyuta -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                Maosh
                            </label>
                            <input 
                                v-model="employeeForm.base_rate" 
                                type="number" 
                                placeholder="Maoshni kiriting"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">
                                Valyuta
                            </label>
                            <select 
                                v-model="employeeForm.currency"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                            >
                                <option value="">Valyutani tanlang</option>
                                <option value="UZS">UZS (So'm)</option>
                                <option value="USD">USD (Dollar)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Passport photo uploads (front / back) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-2">Passport rasmi (oldi)</label>
                            <div 
                                @click="$refs.passportFrontInput.click()" 
                                class="border border-dashed border-gray-200 rounded-2xl h-28 flex flex-col items-center justify-center bg-gray-50 hover:bg-gray-100 cursor-pointer transition-colors relative overflow-hidden group p-2"
                            >
                                <img v-if="employeeForm.passport_front" :src="employeeForm.passport_front" class="w-full h-full object-contain rounded-xl" />
                                <div v-else class="text-center">
                                    <span class="text-gray-400 text-2xl block mb-1">+</span>
                                    <span class="text-xs text-gray-500 font-bold">Yuklash</span>
                                </div>
                                <div v-if="employeeForm.passport_front" class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs font-bold transition-opacity">
                                    Rasmni almashtirish
                                </div>
                            </div>
                            <input ref="passportFrontInput" type="file" class="hidden" accept="image/*" @change="uploadPassportFile($event, 'passport_front')" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-2">Passport rasmi (orqasi)</label>
                            <div 
                                @click="$refs.passportBackInput.click()" 
                                class="border border-dashed border-gray-200 rounded-2xl h-28 flex flex-col items-center justify-center bg-gray-50 hover:bg-gray-100 cursor-pointer transition-colors relative overflow-hidden group p-2"
                            >
                                <img v-if="employeeForm.passport_back" :src="employeeForm.passport_back" class="w-full h-full object-contain rounded-xl" />
                                <div v-else class="text-center">
                                    <span class="text-gray-400 text-2xl block mb-1">+</span>
                                    <span class="text-xs text-gray-500 font-bold">Yuklash</span>
                                </div>
                                <div v-if="employeeForm.passport_back" class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs font-bold transition-opacity">
                                    Rasmni almashtirish
                                </div>
                            </div>
                            <input ref="passportBackInput" type="file" class="hidden" accept="image/*" @change="uploadPassportFile($event, 'passport_back')" />
                        </div>
                    </div>

                    <!-- Holat -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            <span class="text-red-500">*</span> Holat
                        </label>
                        <select v-model="employeeForm.status" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 bg-white">
                            <option value="active">Ish holatida</option>
                            <option value="fired">Bo'shatilgan</option>
                        </select>
                    </div>

                    <!-- Footer Buttons -->
                    <div class="flex gap-3 pt-6 border-t border-gray-100">
                        <button 
                            type="button" 
                            @click="showEmployeeModal = false" 
                            class="flex-1 py-3 border border-gray-200 text-gray-700 rounded-xl text-sm font-bold text-center hover:bg-gray-50 transition-colors"
                        >
                            Bekor qilish
                        </button>
                        <button 
                            type="submit" 
                            :disabled="isProcessing" 
                            class="flex-1 py-3 bg-[#f39c12] hover:bg-[#e67e22] disabled:bg-gray-300 text-white rounded-xl text-sm font-bold text-center shadow-md transition-colors"
                        >
                            {{ isEditMode ? 'Saqlash' : 'Qo\'shish' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 3. Adjustment Modal (+ button) -->
        <div v-if="showAdjModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl overflow-hidden animate-[zoomIn_0.2s_ease-out]">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-base font-bold text-gray-900 font-display">Kirim-chiqim kiritish</h3>
                    <button @click="showAdjModal = false" class="text-gray-500">✕</button>
                </div>
                
                <form @submit.prevent="submitAdj" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tranzaksiya turi</label>
                        <select v-model="adjForm.type" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="salary_accrual">Bonus (Maosh qo'shish)</option>
                            <option value="penalty">Jarima (Ushlanma)</option>
                            <option value="advance_payment">Avans (Kechikkan to'lov/Avans)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Summa (so'm)</label>
                        <input 
                            v-model="adjForm.amount" 
                            type="number" 
                            required
                            placeholder="Summa kiriting"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Izoh</label>
                        <textarea 
                            v-model="adjForm.description" 
                            rows="2"
                            placeholder="Izoh kiriting"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                        ></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Sana</label>
                        <input 
                            v-model="adjForm.date" 
                            type="date" 
                            required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 bg-white"
                        />
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="button" @click="showAdjModal = false" class="px-4 py-2 text-gray-500 hover:text-gray-700 text-xs font-bold">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-sm">Saqlash</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 4. Payment Modal (To'lov button) -->
        <div v-if="showPayModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl overflow-hidden animate-[zoomIn_0.2s_ease-out]">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-base font-bold text-gray-900 font-display">Maosh to'lash</h3>
                    <button @click="showPayModal = false" class="text-gray-500">✕</button>
                </div>
                
                <form @submit.prevent="submitPay" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">To'lov summasi (so'm)</label>
                        <input 
                            v-model="payForm.amount" 
                            type="number" 
                            required
                            placeholder="To'lov summasi"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">To'lov turi / uslubi</label>
                        <select v-model="payForm.payment_method" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="cash">Naqd pul (Cash)</option>
                            <option value="card">Karta (Card)</option>
                            <option value="bank_transfer">Bank o'tkazmasi (Bank transfer)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Kassa / Hisob turi</label>
                        <select v-model="payForm.account_type" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="main">Asosiy Kassa</option>
                            <option value="bank">Bank Hisob raqami</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Izoh</label>
                        <input 
                            v-model="payForm.description" 
                            type="text" 
                            placeholder="Masalan: Maosh to'lovi"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Sana</label>
                        <input 
                            v-model="payForm.date" 
                            type="date" 
                            required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 bg-white"
                        />
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="button" @click="showPayModal = false" class="px-4 py-2 text-gray-500 hover:text-gray-700 text-xs font-bold">Bekor qilish</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-sm">Saqlash</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 6. Detailed Salary Report Modal (Clicking total indicators) -->
        <div v-if="showSalaryReportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-3xl w-full max-w-6xl shadow-2xl overflow-hidden animate-[zoomIn_0.2s_ease-out] max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-3">
                        <button @click="showSalaryReportModal = false" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors text-gray-500 font-bold">✕</button>
                        <h3 class="text-base font-black text-gray-900 font-display uppercase tracking-wide">{{ reportModalTitle }}</h3>
                    </div>
                    <button 
                        @click="exportReportToExcel" 
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition-colors flex items-center gap-1.5"
                    >
                        <span>📥</span> Excel yuklab olish
                    </button>
                </div>
                
                <!-- Filters and Search inside Modal -->
                <div class="p-5 bg-white border-b border-gray-50 flex flex-col sm:flex-row gap-4 items-center shrink-0">
                    <div class="relative flex-1 w-full">
                        <span class="absolute left-3 top-3.5 text-gray-400">🔍</span>
                        <input 
                            v-model="reportSearchQuery" 
                            type="text" 
                            placeholder="Xodim, lavozim yoki bino bo'yicha qidirish..."
                            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                        />
                    </div>
                </div>

                <!-- Table Content -->
                <div class="flex-1 overflow-y-auto p-6 bg-slate-50/50">
                    <div class="bg-white border border-gray-100 shadow-sm rounded-2xl overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-400 uppercase text-[10px] font-bold tracking-wider border-b border-gray-100">
                                    <th class="p-4 w-12 text-center">№</th>
                                    <th class="p-4">Xodim</th>
                                    <th class="p-4">Lavozimi</th>
                                    <th class="p-4">Bino (Ob'ekt)</th>
                                    <th class="p-4 text-right" :class="{'bg-blue-50/50 text-blue-800 font-extrabold': salaryReportType === 'base'}">Asosiy maosh</th>
                                    <th class="p-4 text-right">Bonus</th>
                                    <th class="p-4 text-right" :class="{'bg-rose-50/50 text-rose-800 font-extrabold': salaryReportType === 'penalty'}">Jarima</th>
                                    <th class="p-4 text-right">Avans</th>
                                    <th class="p-4 text-right" :class="{'bg-emerald-50/50 text-emerald-800 font-extrabold': salaryReportType === 'paid'}">To'langan</th>
                                    <th class="p-4 text-right" :class="{'bg-purple-50/50 text-purple-800 font-extrabold': salaryReportType === 'debt'}">Qarzdorlik (Qoldiq)</th>
                                    <th class="p-4 text-center">Holat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(s, idx) in filteredReportList" :key="idx" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4 text-center font-mono text-gray-500 text-xs">{{ idx + 1 }}</td>
                                    <td class="p-4 font-bold text-gray-900 uppercase text-xs">{{ s.employee.name }}</td>
                                    <td class="p-4 text-gray-600 text-xs">{{ s.employee.role_label }}</td>
                                    <td class="p-4 text-gray-500 text-xs">{{ s.employee.building_name }}</td>
                                    <td class="p-4 text-right font-mono text-xs font-semibold" :class="{'bg-blue-50/30 text-blue-900 font-bold': salaryReportType === 'base'}">
                                        {{ formatPrice(s.base_salary) }}
                                    </td>
                                    <td class="p-4 text-right font-mono text-xs text-gray-600">
                                        {{ formatPrice(s.bonus) }}
                                    </td>
                                    <td class="p-4 text-right font-mono text-xs font-semibold text-rose-600" :class="{'bg-rose-50/30 text-rose-900 font-bold': salaryReportType === 'penalty'}">
                                        {{ formatPrice(s.penalty) }}
                                    </td>
                                    <td class="p-4 text-right font-mono text-xs text-gray-600">
                                        {{ formatPrice(s.advance) }}
                                    </td>
                                    <td class="p-4 text-right font-mono text-xs font-semibold text-emerald-600" :class="{'bg-emerald-50/30 text-emerald-900 font-bold': salaryReportType === 'paid'}">
                                        {{ formatPrice(s.paid) }}
                                    </td>
                                    <td class="p-4 text-right font-mono text-xs font-semibold text-purple-600" :class="{'bg-purple-50/30 text-purple-900 font-bold': salaryReportType === 'debt'}">
                                        {{ formatPrice(s.debt) }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider" 
                                            :class="s.status === 'To\'langan' ? 'bg-emerald-100 text-emerald-800' : (s.status === 'Qisman to\'langan' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800')">
                                            {{ s.status }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="filteredReportList.length === 0">
                                    <td colspan="11" class="p-10 text-center text-gray-400 font-medium text-xs">Ma'lumot topilmadi</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Summary inside Modal -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex flex-wrap justify-between items-center shrink-0 text-xs text-gray-500 font-mono gap-4">
                    <div>Jami xodimlar: <span class="font-bold text-gray-900">{{ filteredReportList.length }} nafar</span></div>
                    <div class="flex gap-4">
                        <div>Asosiy maosh: <span class="font-bold text-blue-600">{{ formatPrice(filteredReportList.reduce((sum, item) => sum + item.base_salary, 0)) }}</span></div>
                        <div>To'langan: <span class="font-bold text-emerald-600">{{ formatPrice(filteredReportList.reduce((sum, item) => sum + item.paid, 0)) }}</span></div>
                        <div>Jarima: <span class="font-bold text-rose-600">{{ formatPrice(filteredReportList.reduce((sum, item) => sum + item.penalty, 0)) }}</span></div>
                        <div>Qoldiq: <span class="font-bold text-purple-600">{{ formatPrice(filteredReportList.reduce((sum, item) => sum + item.debt, 0)) }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. Detailed HR Report Modal (Clicking employee stats indicators) -->
        <div v-if="showHRReportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-3xl w-full max-w-5xl shadow-2xl overflow-hidden animate-[zoomIn_0.2s_ease-out] max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-3">
                        <button @click="showHRReportModal = false" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors text-gray-500 font-bold">✕</button>
                        <h3 class="text-base font-black text-gray-900 font-display uppercase tracking-wide">{{ hrReportTitle }}</h3>
                    </div>
                    <button 
                        @click="exportHRReportToExcel" 
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition-colors flex items-center gap-1.5"
                    >
                        <span>📥</span> Excel yuklab olish
                    </button>
                </div>
                
                <!-- Filters and Search inside Modal -->
                <div class="p-5 bg-white border-b border-gray-50 flex flex-col sm:flex-row gap-4 items-center shrink-0">
                    <div class="relative flex-1 w-full">
                        <span class="absolute left-3 top-3.5 text-gray-400">🔍</span>
                        <input 
                            v-model="hrReportSearchQuery" 
                            type="text" 
                            placeholder="Qidirish (ism, telefon, lavozim yoki bino bo'yicha)..."
                            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                        />
                    </div>
                </div>

                <!-- Table Content -->
                <div class="flex-1 overflow-y-auto p-6 bg-slate-50/50">
                    <div class="bg-white border border-gray-100 shadow-sm rounded-2xl overflow-hidden">
                        <table class="w-full text-left border-collapse whitespace-nowrap text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 font-semibold text-xs border-b border-gray-100">
                                    <th class="p-4 w-12 text-center">ID</th>
                                    <th class="p-4">Xodim</th>
                                    <th class="p-4">Lavozim</th>
                                    <th class="p-4">Bino</th>
                                    <th class="p-4">Ishga olingan kun</th>
                                    <th class="p-4">Holat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(emp, idx) in filteredHRReportList" :key="emp.id + '-' + emp.type" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4 text-center font-mono text-gray-500 text-xs">{{ emp.id }}</td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center font-bold text-orange-700 shrink-0 uppercase text-xs">
                                                {{ emp.name.substring(0, 2) }}
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-gray-900 text-xs uppercase">{{ emp.name }}</h4>
                                                <p class="text-[10px] text-gray-500 font-medium mt-0.5">{{ emp.phone }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-700 capitalize text-xs">{{ emp.role_label }}</td>
                                    <td class="p-4 text-gray-700 text-xs">{{ emp.building_name }}</td>
                                    <td class="p-4 text-gray-700 text-xs">{{ formatDate(emp.employment_date) }}</td>
                                    <td class="p-4">
                                        <span 
                                            class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider"
                                            :class="emp.status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                                        >
                                            {{ emp.status_label }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="filteredHRReportList.length === 0">
                                    <td colspan="6" class="p-10 text-center text-gray-400 font-medium text-xs">Xodimlar topilmadi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Summary inside Modal -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-between items-center shrink-0 text-xs text-gray-500 font-mono">
                    <div>Jami xodimlar: <span class="font-bold text-gray-900">{{ filteredHRReportList.length }} nafar</span></div>
                </div>
            </div>
        </div>

        <!-- 5. KPI Settings Drawer (Image 5) -->
        <div v-if="showKpiDrawer" class="fixed inset-0 z-50 flex justify-end bg-gray-900/50 backdrop-blur-sm animate-[fadeIn_0.2s_ease-out]">
            <div class="bg-white w-full max-w-md h-full shadow-2xl flex flex-col animate-[slideInRight_0.25s_ease-out] relative">
                <!-- Header -->
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <button @click="showKpiDrawer = false" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
                        <h3 class="text-lg font-black text-gray-900 font-display">Sozlamalar</h3>
                    </div>
                </div>
                
                <!-- Content -->
                <form @submit.prevent="submitKpiSettings" class="p-6 overflow-y-auto flex-1 space-y-6">
                    <!-- Section: Sotuv menejeri sozlamalari -->
                    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
                        <h4 class="text-sm font-extrabold text-gray-900">Sotuv menejeri sozlamalari</h4>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">
                                <span class="text-rose-500 mr-0.5">*</span>Qiyoslash turi
                            </label>
                            <select v-model="kpiForm.comparison_type" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="<=">&le; Kichik yoki teng</option>
                                <option value=">=">&ge; Katta yoki teng</option>
                                <option value="=">= Teng</option>
                                <option value="<">&lt; Kichik</option>
                                <option value=">">&gt; Katta</option>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">
                                    <span class="text-rose-500 mr-0.5">*</span>Boshlanish qiymati
                                </label>
                                <input 
                                    v-model="kpiForm.start_val" 
                                    type="number" 
                                    required
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tugash qiymati</label>
                                <input 
                                    v-model="kpiForm.end_val" 
                                    type="number" 
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 bg-gray-50"
                                />
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">
                                <span class="text-rose-500 mr-0.5">*</span>Bonus miqdori
                            </label>
                            <div class="relative">
                                <input 
                                    v-model="kpiForm.bonus_amount" 
                                    type="number" 
                                    required
                                    class="w-full border border-gray-200 rounded-xl pl-3 pr-12 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                                />
                                <span class="absolute right-3 top-2.5 text-xs text-gray-400 font-bold">UZS</span>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Malumot (Qozgalmas maosh) -->
                    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
                        <h4 class="text-sm font-extrabold text-gray-900">Malumot:</h4>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">
                                <span class="text-rose-500 mr-0.5">*</span>Qo'zg'almas maosh
                            </label>
                            <div class="relative">
                                <input 
                                    v-model="kpiForm.fixed_salary" 
                                    type="number" 
                                    required
                                    class="w-full border border-gray-200 rounded-xl pl-3 pr-12 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                                />
                                <span class="absolute right-3 top-2.5 text-xs text-gray-400 font-bold">UZS</span>
                            </div>
                            <p class="text-[10px] text-gray-400 font-medium mt-1">Har oylik qo'zg'almas maosh miqdori</p>
                        </div>
                    </div>

                    <!-- Help Box / Information -->
                    <div class="bg-blue-50/60 border border-blue-100/50 rounded-2xl p-4 space-y-2 text-xs text-blue-800">
                        <h5 class="font-extrabold text-blue-900">Ma'lumot:</h5>
                        <ul class="list-disc list-inside space-y-1 font-medium pl-1">
                            <li>Bonus sozlamalari KPI asosida hisoblanadi</li>
                            <li>Qo'zg'almas maosh har oy to'lanadi</li>
                            <li>Barcha maydonlarni to'ldirish majburiy</li>
                            <li>Oxirgi qiymat nofaol axboroti</li>
                        </ul>
                    </div>
                    
                    <!-- Buttons -->
                    <div class="flex gap-4 pt-4 border-t border-gray-100 shrink-0">
                        <button 
                            type="button" 
                            @click="showKpiDrawer = false" 
                            class="flex-1 py-3 border border-gray-200 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-50 transition-colors text-center"
                        >
                            Bekor qilish
                        </button>
                        <button 
                            type="submit" 
                            :disabled="isProcessing" 
                            class="flex-1 py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-sm transition-colors text-center"
                        >
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes zoomIn {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
@keyframes slideInRight {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}
</style>
