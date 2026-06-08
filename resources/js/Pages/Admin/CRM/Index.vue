<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import QuickSmsModal from '@/Components/QuickSmsModal.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();
const props = defineProps({
    leads: Array,
    clients: Array,
    latestClientId: Number,
    contracts: Array,
    units: Array,
    overdueSchedules: Array,
    strictCollectionSchedules: Array,
});

const isAdmin = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return false;
    const rawRoles = user.roles || [];
    const roles = rawRoles.map(r => typeof r === 'string' ? r : r.name);
    return roles.includes('Super_Admin') || roles.includes('Admin');
});

const groupedStrictCollection = computed(() => {
    if (!props.strictCollectionSchedules) return [];
    const groups = {};
    props.strictCollectionSchedules.forEach(schedule => {
        const clientId = schedule.contract?.client?.id;
        if (!clientId) return;
        if (!groups[clientId]) {
            groups[clientId] = {
                client: schedule.contract.client,
                total_amount: 0,
                schedules: [],
                max_days: 0,
                phone: schedule.contract.client.phone,
                last_sms: null,
            };
        }
        groups[clientId].total_amount += Number(schedule.expected_amount || 0);
        groups[clientId].schedules.push(schedule);
        
        const days = Math.floor((new Date() - new Date(schedule.due_date)) / (1000 * 60 * 60 * 24));
        if (days > groups[clientId].max_days) groups[clientId].max_days = days;
        if (schedule.last_sms_sent_at) {
            const smsDate = new Date(schedule.last_sms_sent_at);
            if (!groups[clientId].last_sms || smsDate > groups[clientId].last_sms) {
                groups[clientId].last_sms = smsDate;
            }
        }
    });
    return Object.values(groups);
});

const groupedOverdueSchedules = computed(() => {
    if (!props.overdueSchedules) return [];
    const groups = {};
    props.overdueSchedules.forEach(schedule => {
        const clientId = schedule.contract?.client?.id;
        if (!clientId) return;
        if (!groups[clientId]) {
            groups[clientId] = {
                client: schedule.contract.client,
                total_amount: 0,
                schedules: [],
                max_days: 0,
                phone: schedule.contract.client.phone,
                last_sms: null,
            };
        }
        groups[clientId].total_amount += Number(schedule.expected_amount || 0);
        groups[clientId].schedules.push(schedule);
        
        const days = Math.floor((new Date() - new Date(schedule.due_date)) / (1000 * 60 * 60 * 24));
        if (days > groups[clientId].max_days) groups[clientId].max_days = days;
        if (schedule.last_sms_sent_at) {
            const smsDate = new Date(schedule.last_sms_sent_at);
            if (!groups[clientId].last_sms || smsDate > groups[clientId].last_sms) {
                groups[clientId].last_sms = smsDate;
            }
        }
    });
    return Object.values(groups);
});

const isProcessing = ref(false);

const showCreateClientModal = ref(false);
const showCreateLeadModal = ref(false);

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const preselectUnit = urlParams.get('unit_id');
    if (preselectUnit) {
        activeTab.value = 'contracts';
        contractForm.value.unit_id = Number(preselectUnit);
        showCreateContractModal.value = true;
    }
});
const isEditingClient = ref(false);
const editingClientId = ref(null);
const clientForm = ref({
    first_name: '',
    last_name: '',
    middle_name: '',
    passport_series: '',
    pinfl: '',
    phone: '+998',
    address: '',
    passport_issued_date: '',
    passport_issued_by: '',
    created_at: '',
    birth_date: '',
    phone_2: '+998',
    phone_3: '+998',
    gender: 'erkak',
    region: '',
    district: '',
    passport_photo: null,
    source: '',
    contract_language: 'O\'zbek tili',
    contract_id: ''
});
const newLeadForm = ref({ full_name: '', phone: '', source: 'manual', is_manual: true });
const isEditingLead = ref(false);
const editingLeadId = ref(null);

const editingScheduleId = ref(null);
const editingPaidAmount = ref(0);
const editingPaymentMethod = ref('naqd');
const editingActualPaymentDate = ref('');

function editSchedule(schedule) {
    editingScheduleId.value = schedule.id;
    editingPaidAmount.value = '';
    editingPaymentMethod.value = schedule.payment_method || 'naqd';
    editingActualPaymentDate.value = schedule.actual_payment_date 
        ? schedule.actual_payment_date.substring(0, 10)
        : new Date().toISOString().split('T')[0];
}

function saveSchedulePayment(schedule) {
    isProcessing.value = true;
    router.post(route('admin.crm.schedules.pay', schedule.id), {
        paid_amount: editingPaidAmount.value,
        payment_method: editingPaymentMethod.value,
        actual_payment_date: editingActualPaymentDate.value
    }, {
        onSuccess: (page) => {
            editingScheduleId.value = null;
            isProcessing.value = false;
            const updatedContract = (page.props.contracts || []).find(c => c.id === selectedContract.value.id);
            if (updatedContract) {
                // Deep clone to avoid mutating props directly when v-model changes
                selectedContract.value = JSON.parse(JSON.stringify(updatedContract));
            }
        },
        onError: () => {
            isProcessing.value = false;
        },
        preserveScroll: true
    });
}

const cancelContract = async (id) => {
    if (!confirm('Haqiqatan ham ushbu shartnomani bekor qilmoqchimisiz? Ushbu amal ob\'yektni yana "sotuvda" holatiga qaytaradi.')) return;
    
    isProcessing.value = true;
    try {
        await axios.post(route('admin.contracts.cancel', id));
        showModal.value = false;
        router.reload({ only: ['contracts'] });
    } catch (e) {
        alert('Xatolik yuz berdi: ' + (e.response?.data?.message || e.message));
    } finally {
        isProcessing.value = false;
    }
};

const forceActivateContract = async () => {
    if (!confirm("Haqiqatan ham ushbu shartnomani qo'lda 'Sotildi' holatiga o'tkazmoqchimisiz?")) return;
    
    isProcessing.value = true;
    try {
        await axios.post(route('admin.contracts.force_activate', selectedContract.value.id));
        router.reload({ only: ['contracts'] });
        
        // Update local state to reflect changes instantly
        selectedContract.value.status = 'active';
        selectedContract.value.ai_verification_status = 'passed';
        
    } catch (e) {
        alert('Xatolik yuz berdi: ' + (e.response?.data?.message || e.message));
    } finally {
        isProcessing.value = false;
    }
};

function recalculatePenalty() {
    isProcessing.value = true;
    router.post(route('admin.crm.contracts.recalculate_penalty', selectedContract.value.id), {
        daily_penalty_rate: selectedContract.value.daily_penalty_rate
    }, {
        onSuccess: (page) => {
            isProcessing.value = false;
            const updatedContract = (page.props.contracts || []).find(c => c.id === selectedContract.value.id);
            if (updatedContract) {
                selectedContract.value = JSON.parse(JSON.stringify(updatedContract));
            }
        },
        onError: () => {
            isProcessing.value = false;
        },
        preserveScroll: true
    });
}

function sendWarningSms(scheduleId) {
    if (!confirm(t('Are you sure you want to send a warning SMS?'))) return;
    isProcessing.value = true;
    router.post(route('admin.crm.schedules.send_warning_sms', scheduleId), {}, {
        onFinish: () => isProcessing.value = false,
        preserveScroll: true
    });
}

function openEditLeadModal(lead) {
    isEditingLead.value = true;
    editingLeadId.value = lead.id;
    newLeadForm.value = { 
        full_name: lead.full_name, 
        phone: lead.phone, 
        source: lead.source || 'manual', 
        is_manual: lead.source === 'manual' 
    };
    showCreateLeadModal.value = true;
}

function openCreateLeadModal() {
    isEditingLead.value = false;
    editingLeadId.value = null;
    newLeadForm.value = { full_name: '', phone: '', source: 'manual', is_manual: true };
    showCreateLeadModal.value = true;
}

function submitNewLead() {
    isProcessing.value = true;
    if (isEditingLead.value) {
        axios.put(route('admin.crm.leads.update', editingLeadId.value), newLeadForm.value)
            .then(() => {
                showCreateLeadModal.value = false;
                router.reload({ preserveScroll: true });
            })
            .catch(error => {
                alert("Xatolik yuz berdi: " + (error.response?.data?.message || error.message));
            })
            .finally(() => {
                isProcessing.value = false;
            });
    } else {
        axios.post(route('api.leads.store'), newLeadForm.value)
            .then(() => {
                showCreateLeadModal.value = false;
                newLeadForm.value = { full_name: '', phone: '', source: 'manual', is_manual: true };
                router.reload({ preserveScroll: true });
            })
            .catch(error => {
                alert("Xatolik yuz berdi: " + (error.response?.data?.message || error.message));
            })
            .finally(() => {
                isProcessing.value = false;
            });
    }
}

function deleteLead(id) {
    if (confirm("Haqiqatan ham ushbu lidni o'chirmoqchimisiz?")) {
        router.delete(route('admin.crm.leads.destroy', id), {
            preserveScroll: true
        });
    }
}

function openCreateClientModal() {
    isEditingClient.value = false;
    editingClientId.value = null;
    clientForm.value = {
        first_name: '',
        last_name: '',
        middle_name: '',
        passport_series: '',
        pinfl: '',
        phone: '+998',
        address: '',
        passport_issued_date: '',
        passport_issued_by: '',
        created_at: new Date().toISOString().split('T')[0],
        birth_date: '',
        phone_2: '+998',
        phone_3: '+998',
        gender: 'erkak',
        region: '',
        district: '',
        passport_photo: null,
        source: '',
        contract_language: 'O\'zbek tili',
        contract_id: ''
    };
    showCreateClientModal.value = true;
}

function openEditClientModal(client) {
    isEditingClient.value = true;
    editingClientId.value = client.id;
    clientForm.value = { 
        first_name: client.first_name, 
        last_name: client.last_name, 
        middle_name: client.middle_name || '',
        passport_series: client.passport_number || client.passport_series, 
        pinfl: client.pinfl, 
        phone: client.phone || '+998', 
        address: client.address || '',
        passport_issued_date: client.passport_issued_date || '',
        passport_issued_by: client.passport_issued_by || '',
        created_at: client.created_at ? new Date(client.created_at).toISOString().slice(0, 10) : '',
        birth_date: client.birth_date || '',
        phone_2: client.phone_2 || '+998',
        phone_3: client.phone_3 || '+998',
        gender: client.gender || 'erkak',
        region: client.region || '',
        district: client.district || '',
        passport_photo: null,
        source: client.source || '',
        contract_language: client.contract_language || 'O\'zbek tili',
        contract_id: client.contract_id || ''
    };
    showCreateClientModal.value = true;
}

function saveClient() {
    isProcessing.value = true;
    if (isEditingClient.value) {
        router.put(route('admin.crm.clients.update', editingClientId.value), clientForm.value, {
            onSuccess: () => {
                showCreateClientModal.value = false;
                isProcessing.value = false;
            },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.crm.clients.store'), clientForm.value, {
            onSuccess: () => {
                showCreateClientModal.value = false;
                isProcessing.value = false;
            },
            onError: () => isProcessing.value = false
        });
    }
}

function deleteClient(id) {
    if (confirm(t('confirm_delete_client'))) {
        router.delete(route('admin.crm.clients.delete', id));
    }
}

// Create / Edit Contract Form
const showCreateContractModal = ref(false);
const isEditingContract = ref(false);
const currentContractId = ref(null);
const contractForm = ref({ buyer_type: 'client', partner_id: '', client_id: '', unit_id: '', contract_number: '', contract_type: 'residential', price_per_sqm: '', total_amount: '', down_payment: '', term_months: 36, payment_type: 'installment', created_at: '', actual_paid_amount: '', payment_method: 'bank', floor: '', rooms: '' });

function editContract(contract) {
    isEditingContract.value = true;
    currentContractId.value = contract.id;
    contractForm.value = { 
        buyer_type: contract.partner_id ? 'partner' : 'client',
        partner_id: contract.partner_id || '',
        client_id: contract.client_id || '', 
        unit_id: contract.unit_id, 
        contract_number: contract.contract_number || '',
        contract_type: contract.contract_type || 'residential', 
        price_per_sqm: contract.price_per_sqm, 
        total_amount: contract.total_amount, 
        down_payment: contract.down_payment, 
        term_months: contract.term_months, 
        payment_type: contract.payment_type || 'installment',
        created_at: contract.created_at ? new Date(contract.created_at).toISOString().split('T')[0] : '',
        actual_paid_amount: '',
        payment_method: 'bank',
        floor: contract.floor || contract.unit?.floor?.floor_number || '',
        rooms: contract.rooms || contract.unit?.rooms_count || ''
    };
    showCreateContractModal.value = true;
}

function deleteContract(id) {
    if (confirm(t('confirm_delete_contract'))) {
        router.delete(route('admin.contracts.destroy', id));
    }
}

function createContract() {
    isProcessing.value = true;
    
    // Clear the opposite ID depending on buyer_type
    if (contractForm.value.buyer_type === 'client') {
        contractForm.value.partner_id = null;
    } else {
        contractForm.value.client_id = null;
    }
    
    if (isEditingContract.value) {
        router.put(route('admin.contracts.update', currentContractId.value), contractForm.value, {
            onSuccess: () => {
                showCreateContractModal.value = false;
                isProcessing.value = false;
                isEditingContract.value = false;
                currentContractId.value = null;
            },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.contracts.store'), contractForm.value, {
            onSuccess: () => {
                showCreateContractModal.value = false;
                contractForm.value = { buyer_type: 'client', partner_id: '', client_id: '', unit_id: '', contract_number: '', contract_type: 'residential', price_per_sqm: '', total_amount: '', down_payment: '', term_months: 36, payment_type: 'installment', created_at: '', actual_paid_amount: '', payment_method: 'bank', floor: '', rooms: '' };
                isProcessing.value = false;
            },
            onError: () => isProcessing.value = false
        });
    }
}

const selectedUnit = computed(() => {
    if (!contractForm.value.unit_id) return null;
    return props.units.find(u => u.id === contractForm.value.unit_id);
});

const unitSearchQuery = ref('');
const showUnitDropdown = ref(false);

function formatUnitDisplay(u) {
    const isCommercial = u.type === 'commercial';
    const typeLabel = isCommercial ? "Do'kon" : 'Xonadon';
    const prefix = isCommercial && /^\d+$/.test(String(u.unit_number).trim()) ? 'D-' : '';
    return `${typeLabel} ${prefix}${u.unit_number} - ${u.floor?.building?.name || ''} (Qavat: ${u.floor?.floor_number !== undefined && u.floor?.floor_number !== null ? u.floor.floor_number : ''})`;
}

const filteredUnits = computed(() => {
    let result = props.units;
    
    // Search filtering
    if (unitSearchQuery.value) {
        const q = unitSearchQuery.value.toLowerCase();
        result = result.filter(u => {
            const text = formatUnitDisplay(u);
            return text.toLowerCase().includes(q);
        });
    }
    
    // Sort by floor_number, then unit_number
    return [...result].sort((a, b) => {
        const floorA = a.floor?.floor_number || 0;
        const floorB = b.floor?.floor_number || 0;
        if (floorA !== floorB) {
            return floorA - floorB;
        }
        // If same floor, sort by unit number
        const numA = String(a.unit_number).replace(/\D/g, '');
        const numB = String(b.unit_number).replace(/\D/g, '');
        return (Number(numA) || 0) - (Number(numB) || 0);
    });
});

function selectUnit(u) {
    contractForm.value.unit_id = u.id;
    unitSearchQuery.value = formatUnitDisplay(u);
    showUnitDropdown.value = false;
}

const clientsOptions = computed(() => {
    return props.clients.map(c => ({
        label: `${c.first_name} ${c.last_name || ''}`.trim(),
        value: c.id
    }));
});

const partnersOptions = computed(() => {
    return (props.partners || []).map(p => ({
        label: `${p.user?.name} (Ulush: ${p.investment_share_percentage}%)`,
        value: p.id
    }));
});

watch(() => contractForm.value.unit_id, (newVal) => {
    if (newVal) {
        const u = props.units.find(un => un.id === newVal);
        if (u) {
            unitSearchQuery.value = formatUnitDisplay(u);
        }
    } else {
        unitSearchQuery.value = '';
    }
});

// Update contract amounts when unit changes
watch(() => contractForm.value.unit_id, (newUnitId) => {
    if (newUnitId && !isEditingContract.value) {
        const u = props.units.find(unit => unit.id === newUnitId);
        if (u) {
            contractForm.value.total_amount = Number(u.price) || 0;
            const area = Number(u.area_sqm) || 0;
            contractForm.value.price_per_sqm = area > 0 ? (contractForm.value.total_amount / area).toFixed(2) : 0;
            
            contractForm.value.floor = u.floor?.floor_number || '';
            contractForm.value.rooms = u.rooms_count || '';
            
            if (contractForm.value.payment_type === 'full') {
                contractForm.value.down_payment = contractForm.value.total_amount;
                contractForm.value.term_months = 1;
            } else {
                contractForm.value.down_payment = contractForm.value.total_amount * 0.3; // Default 30%
            }
        }
    }
});

watch(() => contractForm.value.total_amount, (newTotal) => {
    if (isEditingContract.value) return;
    if (contractForm.value.unit_id) {
        const u = props.units.find(unit => unit.id === contractForm.value.unit_id);
        if (u) {
            const area = Number(u.area_sqm) || 0;
            contractForm.value.price_per_sqm = area > 0 ? (Number(newTotal) / area).toFixed(2) : 0;
            
            if (contractForm.value.payment_type === 'full') {
                contractForm.value.down_payment = Number(newTotal);
            } else {
                // optional: auto-update down payment if needed
            }
        }
    }
});

watch(() => contractForm.value.payment_type, (newType) => {
    if (isEditingContract.value) return;
    if (newType === 'full') {
        contractForm.value.down_payment = contractForm.value.total_amount;
        contractForm.value.term_months = 1;
    }
});

const activeTab = ref('leads');

// Synchronize activeTab with the URL tab parameter reactively
watch(
    () => page.url,
    (newUrl) => {
        try {
            const url = new URL(newUrl, window.location.origin);
            const tabParam = url.searchParams.get('tab');
            if (tabParam !== null) {
                if (activeTab.value !== tabParam) {
                    activeTab.value = tabParam;
                }
            } else {
                activeTab.value = 'leads'; // Default tab is 'leads'
            }
        } catch (e) {
            console.error('URL parse error in CRM tab watcher', e);
        }
    },
    { immediate: true }
);

// Synchronize URL query parameter with activeTab changes inside the component
watch(activeTab, (newTab) => {
    try {
        const url = new URL(window.location.href);
        const currentTab = url.searchParams.get('tab');
        if (currentTab === null || currentTab !== newTab) {
            router.replace(route('admin.crm', { tab: newTab }), {
                preserveState: true,
                preserveScroll: true
            });
        }
    } catch (e) {
        console.error('URL replace error in CRM activeTab watcher', e);
    }
});
const searchQuery = ref('');
const selectedBuildingFilter = ref('');
const selectedStatusFilter = ref('');
const selectedManagerFilter = ref('');

const leadsByStatus = computed(() => {
    return {
        new: (props.leads || []).filter(l => l.status === 'new'),
        contacted: (props.leads || []).filter(l => l.status === 'contacted'),
        qualified: (props.leads || []).filter(l => l.status === 'qualified'),
        lost: (props.leads || []).filter(l => l.status === 'lost')
    };
});

const availableBuildings = computed(() => {
    const names = new Set();
    props.contracts?.forEach(c => {
        const bName = c.unit?.floor?.building?.name;
        if (bName) names.add(bName);
    });
    return [...names];
});

const availableManagers = computed(() => {
    const names = new Set();
    props.contracts?.forEach(c => {
        const pName = c.partner?.user?.name;
        if (pName) names.add(pName);
    });
    return [...names];
});

const filteredClients = computed(() => {
    let result = props.clients || [];
    
    if (searchQuery.value) {
        const lowerQuery = searchQuery.value.toLowerCase();
        result = result.filter(c => 
            (c.first_name && c.first_name.toLowerCase().includes(lowerQuery)) ||
            (c.last_name && c.last_name.toLowerCase().includes(lowerQuery)) ||
            (c.middle_name && c.middle_name.toLowerCase().includes(lowerQuery)) ||
            (c.phone && c.phone.includes(lowerQuery)) ||
            (c.pinfl && c.pinfl.includes(lowerQuery))
        );
    }

    if (selectedBuildingFilter.value) {
        result = result.filter(c => {
            return c.contracts?.some(contract => 
                contract.unit?.floor?.building?.name === selectedBuildingFilter.value
            );
        });
    }

    if (selectedManagerFilter.value) {
        result = result.filter(c => {
            return c.contracts?.some(contract => 
                contract.partner?.user?.name === selectedManagerFilter.value
            );
        });
    }

    if (selectedStatusFilter.value) {
        result = result.filter(c => {
            if (selectedStatusFilter.value === 'approved') {
                return c.contracts?.some(contract => contract.status === 'active' || contract.status === 'approved');
            } else if (selectedStatusFilter.value === 'pending') {
                return c.contracts?.some(contract => contract.status === 'draft' || contract.status === 'pending');
            } else if (selectedStatusFilter.value === 'cancelled') {
                return c.contracts?.some(contract => contract.status === 'cancelled' || contract.status === 'cancelled');
            }
            return true;
        });
    }
    
    return result;
});

const activeContracts = computed(() => {
    return (props.contracts || []).filter(c => c.status !== 'canceled' && c.status !== 'cancelled');
});

const contractSearchQuery = ref('');

const groupedContracts = computed(() => {
    let contracts = (props.contracts || []).filter(c => c.status !== 'canceled' && c.status !== 'cancelled');
    
    if (contractSearchQuery.value) {
        const query = contractSearchQuery.value.toLowerCase();
        contracts = contracts.filter(c => {
            const clientFirstName = (c.client?.first_name || '').toLowerCase();
            const clientLastName = (c.client?.last_name || '').toLowerCase();
            const clientFullName = `${clientFirstName} ${clientLastName}`;
            const contractNumber = (c.contract_number || '').toLowerCase();
            const unitNumber = (c.unit?.unit_number || '').toString().toLowerCase();
            return clientFirstName.includes(query) || 
                   clientLastName.includes(query) || 
                   clientFullName.includes(query) || 
                   contractNumber.includes(query) || 
                   unitNumber.includes(query);
        });
    }

    const groups = {};
    contracts.forEach(c => {
        const blockName = c.unit?.floor?.building?.name || "Boshqa (Biriktirilmagan)";
        if (!groups[blockName]) {
            groups[blockName] = [];
        }
        groups[blockName].push(c);
    });

    const sortedGroups = {};
    Object.keys(groups).sort().forEach(key => {
        sortedGroups[key] = groups[key];
    });

    return sortedGroups;
});

const canceledContracts = computed(() => {
    return (props.contracts || []).filter(c => c.status === 'canceled' || c.status === 'cancelled');
});

function calculatePaidAmount(contract) {
    if (!contract.payment_schedules) return 0;
    return contract.payment_schedules
        .filter(ps => ps.status === 'paid')
        .reduce((sum, ps) => sum + Number(ps.paid_amount || ps.expected_amount), 0);
}

function calculateProgress(contract) {
    const paid = calculatePaidAmount(contract);
    const total = Number(contract.total_amount);
    return total > 0 ? (paid / total) * 100 : 0;
}

function getInitials(first, last) {
    return ((first?.charAt(0) || '') + (last?.charAt(0) || '')).toUpperCase() || '?';
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${day}.${month}.${year} ${hours}:${minutes}`;
}

const getClientLatestContract = (client) => {
    if (client.contracts && client.contracts.length > 0) {
        return client.contracts[client.contracts.length - 1];
    }
    return null;
};

// Modal State
const selectedContract = ref(null);
const showModal = ref(false);

const refundTransactions = computed(() => {
    if (!selectedContract.value || !selectedContract.value.transactions) return [];
    return selectedContract.value.transactions.filter(t => 
        t.category && (t.category.name === 'Mijozga pulni qaytarish' || t.category.name === 'Mijozga to\'lov qilish')
    );
});

function openScheduleModal(contract) {
    selectedContract.value = JSON.parse(JSON.stringify(contract));
    showModal.value = true;
}

function refreshData() {
    isProcessing.value = true;
    router.reload({
        only: ['leads', 'clients', 'contracts', 'overdueSchedules'],
        onSuccess: (page) => {
            isProcessing.value = false;
            if (selectedContract.value) {
                const updated = page.props.contracts.find(c => c.id === selectedContract.value.id);
                if (updated) selectedContract.value = JSON.parse(JSON.stringify(updated));
            }
        },
        onError: () => isProcessing.value = false,
        preserveScroll: true
    });
}

const fileInput = ref(null);
const uploadStatus = ref(null);
const uploadMessage = ref('');

function uploadSignature(event) {
    const file = event.target.files[0];
    if (!file || !selectedContract.value) return;

    isProcessing.value = true;
    uploadStatus.value = 'uploading';
    uploadMessage.value = 'Hujjat tekshirilmoqda... (AI Scan)';

    const formData = new FormData();
    formData.append('signed_document', file);

    axios.post(`/admin/contracts/${selectedContract.value.id}/upload-signature`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    }).then(response => {
        uploadStatus.value = response.data.status === 'passed' ? 'success' : 'error';
        uploadMessage.value = response.data.message;
        
        // Update local state if passed
        if(response.data.status === 'passed') {
            selectedContract.value.ai_verification_status = 'passed';
            selectedContract.value.status = 'active';
        } else {
            selectedContract.value.ai_verification_status = 'failed';
        }
    }).catch(error => {
        uploadStatus.value = 'error';
        uploadMessage.value = error.response?.data?.message || 'Server xatosi yoki ulanishda muammo.';
    }).finally(() => {
        isProcessing.value = false;
        event.target.value = null; // reset input
    });
}



// KANBAN DRAG & DROP AND ACTIONS
const draggedLead = ref(null);
const showQuickSmsModal = ref(false);
const activeLead = ref(null);

function dragStart(lead, event) {
    draggedLead.value = lead;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', lead.id);
}

function dropLead(status, event) {
    if (!draggedLead.value) return;
    const lead = draggedLead.value;
    
    if (lead.status === status) return; // No change

    if (status === 'lost') {
        const reason = prompt("Rad etish sababini kiriting (ixtiyoriy):");
        if (reason !== null) {
            updateLeadStatusAPI(lead, status, reason);
        }
    } else {
        updateLeadStatusAPI(lead, status);
    }
    draggedLead.value = null;
}

function updateLeadStatusAPI(lead, status, reason = null) {
    router.post(route('admin.crm.leads.update_status', lead.id), { status, reason }, {
        preserveScroll: true
    });
}

function initiateLeadCall(lead) {
    if (!confirm(`${lead.phone} raqamiga qo'ng'iroq qilinmoqda... Davom etamizmi?`)) return;
    
    // Call new API endpoint for Asterisk AMI
    axios.post('/api/telephony/call', {
        lead_id: lead.id,
        phone_number: lead.phone
    }).then(response => {
        alert(t('asterisk_call_initiated'));
        router.reload({ preserveScroll: true }); // Reload to update kanban board
    }).catch(error => {
        alert(t('error_making_call'));
        console.error(error);
    });
}

function openQuickSmsModal(lead) {
    activeLead.value = lead;
    showQuickSmsModal.value = true;
}

</script>

<template>
    <Head :title="$t('crm_pipeline')" />
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <h2 class="text-lg md:text-xl font-display font-semibold text-gray-900 flex items-center gap-3 truncate">
                    <span class="text-xl md:text-2xl text-gray-500">🛒</span> 
                    <span class="truncate">{{ $t('sales_department') }}</span>
                </h2>
                <button 
                    @click="refreshData" 
                    :disabled="isProcessing"
                    class="flex items-center gap-2 px-4 py-1.5 bg-gray-100 border border-gray-300 text-blue-600 hover:bg-blue-50 rounded-lg text-xs font-mono font-bold uppercase tracking-widest transition-all shadow-sm disabled:opacity-50 group"
                >
                    <span class="group-hover:rotate-180 transition-transform duration-500 inline-block"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg></span> 
                    {{ $t('refresh_live') }}
                </button>
            </div>
        </template>

        <!-- TAB 1: LEADS KANBAN -->
        <div v-if="activeTab === 'leads'" class="flex gap-4 h-full min-h-[600px] overflow-x-auto pb-4">
            <!-- New Column -->
            <div class="flex-1 min-w-[250px] bg-white border border-gray-200 shadow-sm rounded-xl rounded-2xl p-4 flex flex-col border-t-2 border-t-[#00F0FF]"
                 @dragover.prevent @drop="dropLead('new', $event)">
                <div class="mb-4 flex items-center justify-between border-b border-gray-200 pb-2">
                    <h3 class="text-sm font-bold uppercase text-gray-900 tracking-widest">{{ $t('lead_new') }}</h3>
                    <div class="flex items-center gap-2">
                        <button @click="openCreateLeadModal" class="px-2 py-0.5 bg-blue-50 border border-blue-500 text-blue-600 text-[10px] font-mono font-bold uppercase tracking-widest rounded hover:bg-blue-600 hover:text-black transition-all shadow-sm">
                            + Yangi Lid
                        </button>
                        <span class="text-xs font-mono px-2 py-0.5 rounded-full text-blue-600">{{ leadsByStatus.new.length }}</span>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3 pr-2 custom-scrollbar">
                    <div v-for="lead in leadsByStatus.new" :key="lead.id" 
                         draggable="true" @dragstart="dragStart(lead, $event)"
                         class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 border border-gray-100 hover:border-[#00F0FF]/50 cursor-move transition-all hover:-translate-y-1 relative group">
                        <h4 class="text-sm font-bold text-gray-900 mb-1">{{ lead.full_name }}</h4>
                        <p class="text-xs text-gray-500 font-mono mb-8">{{ lead.phone }}</p>
                        
                        <!-- Action Bar -->
                        <div class="absolute bottom-2 left-2 right-2 flex justify-between gap-1 border-t border-gray-200 pt-2 opacity-50 group-hover:opacity-100 transition-opacity">
                            <button @click.stop="initiateLeadCall(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-emerald-50 text-emerald-600 hover:bg-emerald-50 border border-emerald-200 transition-colors shadow-sm text-xs" title="Qo'ng'iroq qilish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            </button>
                            <button @click.stop="openQuickSmsModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-blue-50 text-blue-600 hover:bg-blue-50 border border-blue-200 transition-colors shadow-sm text-xs" title="SMS Yuborish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            </button>
                            <button v-if="isAdmin" @click.stop="openEditLeadModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-amber-50 text-amber-600 hover:bg-amber-50 border border-amber-200 transition-colors shadow-sm text-xs" title="Tahrirlash">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                            </button>
                            <button v-if="isAdmin" @click.stop="deleteLead(lead.id)" class="flex-1 flex justify-center items-center py-1 rounded bg-red-50 text-red-600 hover:bg-red-50 border border-red-200 transition-colors shadow-sm text-xs" title="O'chirish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contacted Column -->
            <div class="flex-1 min-w-[250px] bg-white border border-gray-200 shadow-sm rounded-xl rounded-2xl p-4 flex flex-col border-t-2 border-t-[#FCEE09]"
                 @dragover.prevent @drop="dropLead('contacted', $event)">
                <div class="mb-4 flex items-center justify-between border-b border-gray-200 pb-2">
                    <h3 class="text-sm font-bold uppercase text-gray-900 tracking-widest">{{ $t('lead_contacted') }}</h3>
                    <span class="text-xs font-mono px-2 py-0.5 rounded-full text-amber-600">{{ leadsByStatus.contacted.length }}</span>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3 pr-2 custom-scrollbar">
                    <div v-for="lead in leadsByStatus.contacted" :key="lead.id" 
                         draggable="true" @dragstart="dragStart(lead, $event)"
                         class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 border border-gray-100 hover:border-[#FCEE09]/50 cursor-move transition-all hover:-translate-y-1 relative group">
                        <h4 class="text-sm font-bold text-gray-900 mb-1">{{ lead.full_name }}</h4>
                        <p class="text-xs text-gray-500 font-mono mb-8">{{ lead.phone }}</p>
                        
                        <!-- Action Bar -->
                        <div class="absolute bottom-2 left-2 right-2 flex justify-between gap-1 border-t border-gray-200 pt-2 opacity-50 group-hover:opacity-100 transition-opacity">
                            <button @click.stop="initiateLeadCall(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-emerald-50 text-emerald-600 hover:bg-emerald-50 border border-emerald-200 transition-colors shadow-sm text-xs" title="Qo'ng'iroq qilish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            </button>
                            <button @click.stop="openQuickSmsModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-blue-50 text-blue-600 hover:bg-blue-50 border border-blue-200 transition-colors shadow-sm text-xs" title="SMS Yuborish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            </button>
                            <button v-if="isAdmin" @click.stop="openEditLeadModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-amber-50 text-amber-600 hover:bg-amber-50 border border-amber-200 transition-colors shadow-sm text-xs" title="Tahrirlash">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                            </button>
                            <button v-if="isAdmin" @click.stop="deleteLead(lead.id)" class="flex-1 flex justify-center items-center py-1 rounded bg-red-50 text-red-600 hover:bg-red-50 border border-red-200 transition-colors shadow-sm text-xs" title="O'chirish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Qualified Column -->
            <div class="flex-1 min-w-[250px] bg-white border border-gray-200 shadow-sm rounded-xl rounded-2xl p-4 flex flex-col border-t-2 border-t-[#39FF14]"
                 @dragover.prevent @drop="dropLead('qualified', $event)">
                <div class="mb-4 flex items-center justify-between border-b border-gray-200 pb-2">
                    <h3 class="text-sm font-bold uppercase text-gray-900 tracking-widest">{{ $t('lead_qualified') }}</h3>
                    <span class="text-xs font-mono px-2 py-0.5 rounded-full text-emerald-600">{{ leadsByStatus.qualified.length }}</span>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3 pr-2 custom-scrollbar">
                    <div v-for="lead in leadsByStatus.qualified" :key="lead.id" 
                         draggable="true" @dragstart="dragStart(lead, $event)"
                         class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 border border-gray-100 hover:border-[#39FF14]/50 cursor-move transition-all hover:-translate-y-1 relative group">
                        <h4 class="text-sm font-bold text-gray-900 mb-1">{{ lead.full_name }}</h4>
                        <p class="text-xs text-gray-500 font-mono mb-8">{{ lead.phone }}</p>
                        
                        <!-- Action Bar -->
                        <div class="absolute bottom-2 left-2 right-2 flex justify-between gap-1 border-t border-gray-200 pt-2 opacity-50 group-hover:opacity-100 transition-opacity">
                            <button @click.stop="initiateLeadCall(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-emerald-50 text-emerald-600 hover:bg-emerald-50 border border-emerald-200 transition-colors shadow-sm text-xs" title="Qo'ng'iroq qilish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            </button>
                            <button @click.stop="openQuickSmsModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-blue-50 text-blue-600 hover:bg-blue-50 border border-blue-200 transition-colors shadow-sm text-xs" title="SMS Yuborish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            </button>
                            <button v-if="isAdmin" @click.stop="openEditLeadModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-amber-50 text-amber-600 hover:bg-amber-50 border border-amber-200 transition-colors shadow-sm text-xs" title="Tahrirlash">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                            </button>
                            <button v-if="isAdmin" @click.stop="deleteLead(lead.id)" class="flex-1 flex justify-center items-center py-1 rounded bg-red-50 text-red-600 hover:bg-red-50 border border-red-200 transition-colors shadow-sm text-xs" title="O'chirish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lost Column -->
            <div class="flex-1 min-w-[250px] bg-white border border-gray-200 shadow-sm rounded-xl rounded-2xl p-4 flex flex-col border-t-2 border-t-[#FF003C]"
                 @dragover.prevent @drop="dropLead('lost', $event)">
                <div class="mb-4 flex items-center justify-between border-b border-gray-200 pb-2">
                    <h3 class="text-sm font-bold uppercase text-gray-900 tracking-widest">{{ $t('lead_lost') }}</h3>
                    <span class="text-xs font-mono px-2 py-0.5 rounded-full text-indigo-600">{{ leadsByStatus.lost.length }}</span>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3 pr-2 custom-scrollbar">
                    <div v-for="lead in leadsByStatus.lost" :key="lead.id" 
                         draggable="true" @dragstart="dragStart(lead, $event)"
                         class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 border border-gray-100 hover:border-[#FF003C]/50 cursor-move transition-all hover:-translate-y-1 relative group">
                        <h4 class="text-sm font-bold text-gray-900 mb-1">{{ lead.full_name }}</h4>
                        <p class="text-xs text-gray-500 font-mono mb-8">{{ lead.phone }}</p>
                        
                        <!-- Action Bar -->
                        <div class="absolute bottom-2 left-2 right-2 flex justify-between gap-1 border-t border-gray-200 pt-2 opacity-50 group-hover:opacity-100 transition-opacity">
                            <button @click.stop="initiateLeadCall(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-emerald-50 text-emerald-600 hover:bg-emerald-50 border border-emerald-200 transition-colors shadow-sm text-xs" title="Qo'ng'iroq qilish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            </button>
                            <button @click.stop="openQuickSmsModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-blue-50 text-blue-600 hover:bg-blue-50 border border-blue-200 transition-colors shadow-sm text-xs" title="SMS Yuborish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            </button>
                            <button v-if="isAdmin" @click.stop="openEditLeadModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-amber-50 text-amber-600 hover:bg-amber-50 border border-amber-200 transition-colors shadow-sm text-xs" title="Tahrirlash">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                            </button>
                            <button v-if="isAdmin" @click.stop="deleteLead(lead.id)" class="flex-1 flex justify-center items-center py-1 rounded bg-red-50 text-red-600 hover:bg-red-50 border border-red-200 transition-colors shadow-sm text-xs" title="O'chirish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: CLIENTS ROSTER -->
        <div v-if="activeTab === 'clients'" class="bg-[#f8fafc] p-6 rounded-2xl min-h-[500px]">
            <!-- Breadcrumbs -->
            <div class="flex items-center gap-2 text-xs text-gray-500 font-sans mb-6 font-medium">
                <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Asosiy
                </span>
                <span class="text-gray-300 font-bold">&gt;</span>
                <span class="text-gray-900 font-bold">Mijozlar</span>
            </div>

            <!-- Control bar -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <!-- Left controls: Add + Search -->
                <div class="flex flex-wrap items-center gap-4">
                    <button @click="openCreateClientModal" class="px-5 py-2.5 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1">
                        + Qo'shish
                    </button>
                    
                    <div class="relative flex items-center">
                        <span class="absolute left-3.5 text-gray-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="searchQuery" 
                            type="text" 
                            placeholder="Qidirish" 
                            class="bg-white border border-gray-200 text-gray-800 text-xs rounded-full pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-[#f2a900] w-64 transition-all"
                        />
                    </div>
                </div>

                <!-- Right controls: Dropdowns + Excel -->
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Obyektlar filter -->
                    <div class="relative">
                        <select v-model="selectedBuildingFilter" class="bg-white border border-gray-200 text-gray-700 text-xs rounded-full pl-4 pr-8 py-2 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-[#f2a900] appearance-none cursor-pointer">
                            <option value="">Obyektlar</option>
                            <option v-for="b in availableBuildings" :key="b" :value="b">{{ b }}</option>
                        </select>
                        <span class="absolute right-2.5 top-2.5 text-gray-400 pointer-events-none">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </span>
                    </div>

                    <!-- Sotuv menejeri filter -->
                    <div class="relative">
                        <select v-model="selectedManagerFilter" class="bg-white border border-gray-200 text-gray-700 text-xs rounded-full pl-4 pr-8 py-2 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-[#f2a900] appearance-none cursor-pointer">
                            <option value="">Sotuv menejeri</option>
                            <option v-for="manager in availableManagers" :key="manager" :value="manager">{{ manager }}</option>
                        </select>
                        <span class="absolute right-2.5 top-2.5 text-gray-400 pointer-events-none">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </span>
                    </div>

                    <!-- Holat (Tasdiqlangan...) filter -->
                    <div class="relative">
                        <select v-model="selectedStatusFilter" class="bg-white border border-gray-200 text-gray-700 text-xs rounded-full pl-4 pr-8 py-2 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-[#f2a900] appearance-none cursor-pointer">
                            <option value="">Tasdiqlangan...</option>
                            <option value="approved">Tasdiqlangan</option>
                            <option value="pending">Kutilmoqda</option>
                            <option value="cancelled">Bekor qilingan</option>
                        </select>
                        <span class="absolute right-2.5 top-2.5 text-gray-400 pointer-events-none">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </span>
                    </div>

                    <!-- Excel Export -->
                    <a :href="route('admin.crm.export.clients.excel')" class="px-5 py-2.5 bg-white border border-emerald-500 text-emerald-600 text-xs font-bold rounded-full hover:bg-emerald-50 transition-all flex items-center gap-1.5 shadow-sm font-sans">
                        <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                            <path d="M14 2v6h6m-3 4l-4 4m0-4l4 4"/>
                        </svg>
                        Excel
                    </a>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left border-collapse text-xs font-sans">
                    <thead>
                        <tr class="bg-[#f8fafc] border-b border-gray-200 text-gray-500 font-semibold uppercase text-[10px] tracking-wider">
                            <th class="p-4 w-12 text-center">ID</th>
                            <th class="p-4">Ism</th>
                            <th class="p-4">Bino</th>
                            <th class="p-4">Blok</th>
                            <th class="p-4">Xonadon</th>
                            <th class="p-4">Qavat</th>
                            <th class="p-4">Uy narxi</th>
                            <th class="p-4">Yaratilgan</th>
                            <th class="p-4">Holat</th>
                            <th class="p-4 text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr 
                            v-for="client in filteredClients" 
                            :key="client.id" 
                            class="hover:bg-gray-50/50 transition-colors"
                        >
                            <!-- ID -->
                            <td class="p-4 text-center font-medium text-gray-600">{{ client.id }}</td>
                            
                            <!-- Ism -->
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-[#fef9c3] text-[#ca8a04] flex items-center justify-center font-bold text-sm shadow-sm shrink-0">
                                        {{ getInitials(client.first_name, client.last_name) }}
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-900 leading-tight">
                                            {{ client.last_name }} {{ client.first_name }}
                                        </h3>
                                        <div class="text-xs text-gray-500 mt-1 font-medium">{{ client.phone || 'N/A' }}</div>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Bino -->
                            <td class="p-4 text-[#0f766e] font-semibold">
                                {{ getClientLatestContract(client)?.unit?.floor?.building?.name || '—' }}
                            </td>
                            
                            <!-- Blok -->
                            <td class="p-4 text-[#0f766e] font-semibold">
                                {{ getClientLatestContract(client)?.unit?.floor?.building?.block_name || getClientLatestContract(client)?.unit?.floor?.building?.corpus_name || (getClientLatestContract(client)?.unit?.podyezd ? getClientLatestContract(client).unit.podyezd + ' Podezd' : '—') }}
                            </td>
                            
                            <!-- Xonadon -->
                            <td class="p-4 text-[#0f766e] font-semibold">
                                {{ getClientLatestContract(client)?.unit?.unit_number ? getClientLatestContract(client).unit.unit_number + '-Xonadon' : '—' }}
                            </td>
                            
                            <!-- Qavat -->
                            <td class="p-4 text-[#0f766e] font-semibold">
                                {{ getClientLatestContract(client)?.unit?.floor?.floor_number ? getClientLatestContract(client).unit.floor.floor_number + '-Qavat' : '—' }}
                            </td>
                            
                            <!-- Uy narxi -->
                            <td class="p-4 text-[#ef4444] font-bold">
                                {{ getClientLatestContract(client)?.total_amount ? Number(getClientLatestContract(client).total_amount).toLocaleString('uz-UZ') + " so'm" : '—' }}
                            </td>
                            
                            <!-- Yaratilgan -->
                            <td class="p-4 text-gray-600 font-medium">{{ formatDate(client.created_at) }}</td>
                            
                            <!-- Holat -->
                            <td class="p-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-[#ecfdf5] text-[#047857] border border-[#d1fae5] shadow-sm uppercase tracking-wide">
                                    {{ getClientLatestContract(client) ? (getClientLatestContract(client).status === 'active' || getClientLatestContract(client).status === 'approved' ? 'Tasdiqlangan' : 'Tasdiqlangan') : 'Tasdiqlangan' }}
                                </span>
                            </td>
                            
                            <!-- Amallar -->
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <!-- Delete -->
                                    <button v-if="isAdmin" @click="deleteClient(client.id)" class="text-rose-500 hover:text-rose-700 transition-colors" title="O'chirish">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                    <!-- View -->
                                    <button @click="openScheduleModal(getClientLatestContract(client))" v-if="getClientLatestContract(client)" class="text-blue-500 hover:text-blue-700 transition-colors" title="Ko'rish">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                    <!-- Edit -->
                                    <button v-if="isAdmin" @click="openEditClientModal(client)" class="text-gray-500 hover:text-gray-700 transition-colors" title="Tahrirlash">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredClients.length === 0">
                            <td colspan="10" class="py-20 text-center text-gray-400 font-mono text-sm border-dashed">
                                {{ $t('no_clients_found') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: SMART CONTRACTS -->
        <div v-if="activeTab === 'contracts'" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl min-h-[500px]">
            <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <input 
                    v-model="contractSearchQuery" 
                    type="text" 
                    :placeholder="$t('search') + '...'" 
                    class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-64 border-emerald-200 focus:border-emerald-500 focus:ring-neon-green shadow-sm focus:shadow-sm"
                >

                <button @click="isEditingContract = false; contractForm = { buyer_type: 'client', partner_id: '', client_id: '', unit_id: '', contract_number: '', contract_type: 'residential', price_per_sqm: '', total_amount: '', down_payment: '', term_months: 36, payment_type: 'installment', created_at: '', actual_paid_amount: '', payment_method: 'bank', floor: '', rooms: '' }; showCreateContractModal = true" class="px-4 py-2 bg-emerald-50 border border-emerald-500 text-emerald-600 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-emerald-600 hover:text-black transition-all shadow-sm">
                    + {{ $t('add') }} {{ $t('contract') }}
                </button>
            </div>
            
            <div v-for="(contracts, blockName) in groupedContracts" :key="blockName" class="mb-8">
                <h3 class="text-lg font-bold text-emerald-600 mb-4 uppercase tracking-widest flex items-center gap-2">
                    <span>🏢</span> {{ blockName }}
                </h3>
                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left border-collapse">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                            <tr class="/50 border-b border-gray-200 bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">Shartnoma</th>
                                <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">Mijoz</th>
                                <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">Boshlang'ich to'lov</th>
                                <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">Jami Summa</th>
                                <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">To'langan (Holati)</th>
                                <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider text-right">Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="contract in contracts" 
                                :key="contract.id"
                                @click="openScheduleModal(contract)"
                                class="border-b border-gray-100 hover:/[0.03] transition-colors cursor-pointer group bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors"
                            >
                                <td class="p-3">
                                    <h3 class="text-sm font-bold text-gray-900 group-hover:text-emerald-600 transition-colors">
                                        {{ contract.contract_number || 'Contract #' + contract.id.toString().padStart(5, '0') }}
                                    </h3>
                                    <p class="text-[10px] font-mono text-blue-600 mt-0.5">
                                        {{ $t('unit') }}: {{ contract.unit?.unit_number || 'N/A' }} | {{ contract.unit?.floor?.building?.name || 'Bino' }}
                                    </p>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-neon-green/20 to-black border border-emerald-200 flex items-center justify-center text-emerald-600 font-display font-bold text-xs shadow-sm shrink-0">
                                            {{ getInitials(contract.client?.first_name, contract.client?.last_name) }}
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-900">{{ contract.client?.last_name }} {{ contract.client?.first_name }}</p>
                                            <span v-if="contract.is_legacy" class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-bold bg-neon-purple/20 text-neon-purple border border-neon-purple/50 uppercase shadow-sm" title="{{ $t('imported_legacy') }}">{{ $t('imported') }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3 text-sm font-mono text-gray-900/80">
                                    {{ $currency(contract.down_payment) }}
                                </td>
                                <td class="p-3 text-sm font-mono text-emerald-600 font-bold">
                                    {{ $currency(contract.total_amount) }}
                                    <div class="text-[10px] text-gray-500 mt-0.5 font-normal">{{ contract.term_months }} {{ $t('months') }}</div>
                                </td>
                                <td class="p-3">
                                    <div class="flex flex-col gap-1 w-32">
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs font-mono text-gray-900">{{ $currency(calculatePaidAmount(contract)) }}</span>
                                            <span class="text-[10px] text-emerald-600">{{ calculateProgress(contract).toFixed(0) }}%</span>
                                        </div>
                                        <div class="w-full h-1.5 bg-gray-500/50 rounded-full overflow-hidden border border-gray-100 relative">
                                            <div 
                                                class="absolute top-0 left-0 bottom-0 bg-emerald-600 shadow-sm transition-all duration-1000"
                                                :style="{ width: calculateProgress(contract) + '%' }"
                                            ></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3 text-right" @click.stop>
                                    <div class="flex items-center justify-end gap-2 opacity-50 group-hover:opacity-100 transition-opacity">
                                        <button @click="editContract(contract)" class="w-8 h-8 rounded-full bg-neon-blue/10 border border-neon-blue/30 text-neon-blue flex items-center justify-center hover:bg-neon-blue hover:text-gray-900 transition-colors shadow-sm" title="Tahrirlash">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </button>
                                        <button v-if="isAdmin" @click="deleteContract(contract.id)" class="w-8 h-8 rounded-full bg-red-50 border border-red-200 text-red-600 flex items-center justify-center hover:bg-red-600 hover:text-gray-900 transition-colors shadow-sm" title="O'chirish">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-if="Object.keys(groupedContracts).length === 0" class="text-center py-20 text-gray-500 font-mono text-sm border border-dashed border-gray-200 rounded-2xl">
                {{ $t('no_active_contracts') }}
            </div>
        </div>

        <!-- TAB CANCELED: BEKOR QILINGAN SHARTNOMALAR -->
        <div v-if="activeTab === 'canceled'" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl min-h-[500px] border border-red-200">
            <h2 class="text-xl font-display font-bold text-red-600 mb-6 flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-red-600 shadow-sm"></span>
                Bekor Qilingan Shartnomalar
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div 
                        v-for="contract in canceledContracts" 
                        :key="contract.id"
                        @click="openScheduleModal(contract)"
                        class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-red-200 hover:border-red-200 cursor-pointer transition-all hover:shadow-sm group relative overflow-hidden flex flex-col h-full justify-between opacity-80"
                    >
                        <div class="absolute -inset-x-20 top-0 h-px bg-gradient-to-r from-transparent via-white/30 to-transparent transform -rotate-45 -translate-y-10 group-hover:translate-y-[200px] transition-transform duration-1000 opacity-50"></div>
                        
                        <div>
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-neon-red/20 to-black border border-red-200 flex items-center justify-center text-red-600 font-display font-bold text-sm shadow-sm">
                                        {{ getInitials(contract.client?.first_name, contract.client?.last_name) }}
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-900 group-hover:text-red-600 transition-colors">{{ contract.client?.first_name }} {{ contract.client?.last_name }}</h3>
                                        <p class="text-[10px] font-mono text-red-600 mt-0.5">
                                            <span class="text-gray-900/70">{{ contract.contract_number || 'Contract #' + contract.id.toString().padStart(5, '0') }}</span>
                                            | Unit: {{ contract.unit?.unit_number || 'N/A' }} | {{ contract.unit?.floor?.building?.name || 'Bino' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right flex flex-col items-end gap-1">
                                    <span class="bg-red-50 text-red-700 border border-red-200 rounded-md px-2 py-1 text-xs font-medium">Bekor qilingan</span>
                                </div>
                            </div>
    
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4 p-3 rounded-lg bg-gray-500/50 border border-red-200">
                                <div>
                                    <p class="text-[10px] uppercase font-mono text-gray-500">Jami To'langan</p>
                                    <p class="text-xs font-mono text-gray-900/80 mt-0.5">{{ $currency(calculatePaidAmount(contract)) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase font-mono text-gray-500">Qaytarilgan</p>
                                    <p class="text-xs font-mono text-red-600 mt-0.5">{{ $currency(contract.refunded_amount || 0) }}</p>
                                </div>
                            </div>
                                            </div>
                </div>
            </div>
            <div v-if="canceledContracts.length === 0" class="text-center py-20 text-red-600 font-mono text-sm border border-dashed border-red-200 rounded-2xl">
                Bekor qilingan shartnomalar topilmadi
            </div>
        </div>

        <!-- TAB 4: OVERDUE RADAR -->
        <div v-if="activeTab === 'overdue'" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl min-h-[500px] border border-neon-magenta/30">
            <h2 class="text-xl font-display font-bold text-indigo-600 mb-6 flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-indigo-600 animate-pulse shadow-sm"></span>
                {{ $t('overdue_payments_radar') }}
            </h2>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                <!-- STRICT COLLECTION -->
                <div v-if="groupedStrictCollection && groupedStrictCollection.length > 0">
                    <h3 class="text-lg font-bold text-red-600 mb-4 uppercase tracking-widest flex items-center gap-2">
                        <span>🚨</span> Qat'iy Unduruv (Rassrochka)
                    </h3>
                    <div class="overflow-x-auto rounded-xl border border-red-200 shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                <tr class="border-b border-red-200 bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                    <th class="p-3 text-xs font-mono text-red-600 font-bold uppercase tracking-wider">Mijoz</th>
                                    <th class="p-3 text-xs font-mono text-red-600 font-bold uppercase tracking-wider">Kechikkan Summa</th>
                                    <th class="p-3 text-xs font-mono text-red-600 font-bold uppercase tracking-wider">Muddat</th>
                                    <th class="p-3 text-xs font-mono text-red-600 font-bold uppercase tracking-wider text-right">Amallar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="group in groupedStrictCollection" 
                                    :key="group.client?.id"
                                    class="border-b border-red-200 hover: transition-colors group cursor-pointer bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors"
                                    @click="openScheduleModal(group.schedules[0].contract)"
                                >
                                    <td class="p-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-red-50 border border-red-200 flex items-center justify-center text-red-600 font-display font-bold text-xs shadow-sm">
                                                ⚠️
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-900 font-bold">{{ group.client?.last_name }} {{ group.client?.first_name }}</p>
                                                <p class="text-[10px] font-mono text-red-600 mt-0.5">{{ group.schedules.length }} ta to'lov kechikkan</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <p class="text-sm font-mono font-bold text-gray-900">{{ $currency(group.total_amount) }}</p>
                                    </td>
                                    <td class="p-3">
                                        <p class="text-xs font-mono font-bold text-red-600">max {{ group.max_days }} {{ $t('days') }}</p>
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a :href="`tel:${group.phone}`" class="w-8 h-8 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center hover:bg-emerald-600 hover:text-black transition-colors shadow-sm" title="Qo'ng'iroq qilish" @click.stop>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                            </a>
                                            <button @click.stop="sendWarningSms(group.schedules[0].id)" :disabled="isProcessing" class="w-8 h-8 rounded-full bg-indigo-50 border border-neon-magenta/30 text-indigo-600 flex items-center justify-center hover:bg-indigo-600 hover:text-gray-900 transition-colors shadow-sm" title="SMS Yuborish">
                                                📱
                                            </button>
                                        </div>
                                        <div v-if="group.last_sms" class="text-[8px] font-mono text-blue-600 mt-1">
                                            SMS: {{ group.last_sms.toLocaleDateString() }}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- NORMAL OVERDUE -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 uppercase tracking-widest flex items-center gap-2">
                        <span>⏳</span> Oddiy Qarzdorlik
                    </h3>
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                <tr class="/50 border-b border-gray-200 bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">Mijoz</th>
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">Kechikkan Summa</th>
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">Muddat</th>
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider text-right">Amallar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="group in groupedOverdueSchedules" 
                                    :key="group.client?.id"
                                    class="border-b border-gray-100 hover:/[0.03] transition-colors group cursor-pointer bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors"
                                    @click="openScheduleModal(group.schedules[0].contract)"
                                >
                                    <td class="p-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-indigo-50 border border-neon-magenta/50 flex items-center justify-center text-indigo-600 font-display font-bold text-xs shadow-sm">
                                                ⏳
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-900 font-bold">{{ group.client?.last_name }} {{ group.client?.first_name }}</p>
                                                <p class="text-[10px] font-mono text-indigo-600 mt-0.5">{{ group.schedules.length }} ta to'lov kechikkan</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <p class="text-sm font-mono font-bold text-gray-900">{{ $currency(group.total_amount) }}</p>
                                    </td>
                                    <td class="p-3">
                                        <p class="text-xs font-mono font-bold text-indigo-600">max {{ group.max_days }} {{ $t('days') }}</p>
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a :href="`tel:${group.phone}`" class="w-8 h-8 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center hover:bg-emerald-600 hover:text-black transition-colors shadow-sm" title="Qo'ng'iroq qilish" @click.stop>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                            </a>
                                            <button @click.stop="sendWarningSms(group.schedules[0].id)" :disabled="isProcessing" class="w-8 h-8 rounded-full bg-blue-50 border border-blue-200 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-gray-900 transition-colors shadow-sm" title="SMS Yuborish">
                                                📱
                                            </button>
                                        </div>
                                        <div v-if="group.last_sms" class="text-[8px] font-mono text-gray-500 mt-1">
                                            SMS: {{ group.last_sms.toLocaleDateString() }}
                                        </div>
                                    </td>
                                </tr>
                                <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="groupedOverdueSchedules.length === 0">
                                    <td colspan="4" class="py-10 text-center text-emerald-600 font-mono text-sm border-dashed">
                                        {{ $t('no_overdue_payments') }} 🎉
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- CLIENT LEDGER MODAL (GLASSMORPHIC) -->
        <div v-if="showModal && selectedContract" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500/50 transition-all">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl w-full max-w-6xl rounded-2xl border border-blue-200 shadow-sm overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-500/50">
                    <div>
                        <h2 class="text-2xl font-display font-bold text-gray-900 uppercase tracking-widest">{{ $t('client_personal_ledger') }} <span class="text-xs text-blue-600 ml-2">{{ $t('akt_sverka') }}</span></h2>
                        <p class="text-sm text-gray-500 font-mono mt-1">Contract #{{ selectedContract.contract_number || selectedContract.id.toString().padStart(5, '0') }} • {{ selectedContract.client?.first_name }} {{ selectedContract.client?.last_name }} • Unit {{ selectedContract.unit?.unit_number || 'N/A' }}</p>
                    </div>
                    <button @click="showModal = false" class="text-gray-500 hover:text-gray-900 transition-colors">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto flex-1">
                    
                    <!-- ACTION HUB & SETTINGS -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                        <div class="flex items-center gap-3">
                            <a :href="`/admin/crm/ledger/${selectedContract.id}/export/pdf?lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-red-50 border border-red-500 text-red-600 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-red-600 hover:text-black transition-all shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline-block mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> PDF
                            </a>
                            <a :href="`/admin/crm/contracts/${selectedContract.id}/export-doc/pdf?lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-neon-purple/20 border border-neon-purple text-neon-purple text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-purple hover:text-black transition-all shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline-block mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> Shartnoma
                            </a>
                            <a :href="`/admin/crm/ledger/${selectedContract.id}/export/docx?lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-neon-blue/20 border border-neon-blue text-neon-blue text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-blue hover:text-black transition-all shadow-sm">
                                📝 Word
                            </a>
                            <a :href="`/admin/crm/ledger/${selectedContract.id}/export/xlsx?lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-emerald-50 border border-emerald-500 text-emerald-600 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-emerald-600 hover:text-black transition-all shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline-block mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Excel
                            </a>
                            <button @click="cancelContract(selectedContract.id)" :disabled="isProcessing" class="px-4 py-2 bg-red-50 border border-red-500 text-red-600 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-red-600 hover:text-black transition-all shadow-sm disabled:opacity-50">
                                🚫 Bekor qilish
                            </button>
                        </div>

                        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-3 rounded-xl border border-gray-200 flex flex-col md:flex-row items-center gap-4 bg-gray-500/50">
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-mono text-gray-500 uppercase tracking-widest">{{ $t('daily_penalty_rate') }}</label>
                                <input type="number" step="0.01" v-model="selectedContract.daily_penalty_rate" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-20 text-center text-blue-600 font-bold p-1">
                                <button @click="recalculatePenalty" :disabled="isProcessing" class="px-3 py-1 bg-blue-50 border border-blue-500 text-blue-600 text-xs font-mono font-bold uppercase rounded hover:bg-blue-600 hover:text-black transition-all shadow-sm disabled:opacity-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg> {{ $t('calculate') }}
                                </button>
                            </div>
                            <div class="hidden md:block h-8 border-r border-gray-200 mx-2"></div>
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-mono text-gray-500 uppercase tracking-widest">{{ $t('total_penalty') }}</label>
                                <span class="text-indigo-600 font-bold font-mono">{{ $currency(selectedContract.total_penalty_accumulated || 0) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- SUMMARY INFO -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-gray-200 bg-gray-500/50">
                            <p class="text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1">{{ $t('total_amount') }}</p>
                            <p class="text-lg font-bold text-emerald-600 font-mono">{{ $currency(selectedContract.total_amount) }}</p>
                        </div>
                        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-gray-200 bg-gray-500/50">
                            <p class="text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1">{{ $t('down_payment') }}</p>
                            <p class="text-lg font-bold text-gray-900 font-mono">{{ $currency(selectedContract.down_payment) }}</p>
                        </div>
                        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-gray-200 bg-gray-500/50">
                            <p class="text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1">{{ $t('monthly_schedule_payment') }}</p>
                            <p class="text-lg font-bold text-blue-600 font-mono">{{ selectedContract.payment_schedules?.length ? $currency(selectedContract.payment_schedules[0].expected_amount) : '0' }}</p>
                        </div>
                        <div v-if="selectedContract.status === 'cancelled' || selectedContract.refunded_amount > 0" class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-red-200 bg-gray-500/50">
                            <p class="text-[10px] font-mono text-red-600 uppercase tracking-widest mb-1">Qaytarilgan</p>
                            <p class="text-lg font-bold text-red-600 font-mono">{{ $currency(selectedContract.refunded_amount || 0) }}</p>
                        </div>
                    </div>

                    <!-- AI VERIFICATION PORTAL -->
                    <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-blue-200 mb-8 bg-gray-500/50 flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-blue-50 border border-blue-500 flex items-center justify-center">
                                <span class="text-blue-600 text-xl"><svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"></rect><circle cx="12" cy="5" r="2"></circle><path d="M12 7v4"></path><line x1="8" y1="16" x2="8" y2="16"></line><line x1="16" y1="16" x2="16" y2="16"></line></svg></span>
                            </div>
                            <div>
                                <h4 class="text-gray-900 font-bold text-sm tracking-widest uppercase">{{ $t('ai_signature_verification') }}</h4>
                                <p class="text-xs font-mono" :class="{ 'text-gray-500': !selectedContract.ai_verification_status, 'text-emerald-600': selectedContract.ai_verification_status === 'passed', 'text-red-600': selectedContract.ai_verification_status === 'failed' }">
                                    {{ selectedContract.ai_verification_status === 'passed' ? 'Tasdiqlangan va imzolangan' : 
                                       (selectedContract.ai_verification_status === 'failed' ? 'Imzolanmagan qayta yuklang' : 'Hujjat kutilmoqda') }}
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <span v-if="uploadStatus === 'uploading'" class="text-xs text-blue-600 animate-pulse">{{ uploadMessage }}</span>
                            <span v-if="uploadStatus === 'success'" class="text-xs text-emerald-600">{{ uploadMessage }}</span>
                            <span v-if="uploadStatus === 'error'" class="text-xs text-red-600">{{ uploadMessage }}</span>
                            
                            <input type="file" ref="fileInput" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="uploadSignature">
                            <button @click="fileInput.click()" :disabled="isProcessing" class="px-6 py-2 bg-blue-50 border border-blue-500 text-blue-600 font-bold text-xs uppercase tracking-widest rounded hover:bg-blue-600 hover:text-black transition-all shadow-sm disabled:opacity-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline-block mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg> Yuklash
                            </button>
                            <button v-if="selectedContract.status !== 'active'" @click="forceActivateContract" :disabled="isProcessing" class="px-6 py-2 bg-emerald-50 border border-emerald-500 text-emerald-600 font-bold text-xs uppercase tracking-widest rounded hover:bg-emerald-600 hover:text-black transition-all shadow-sm disabled:opacity-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline-block mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Sotildi qilish
                            </button>
                        </div>
                    </div>

                    <!-- LEDGER TABLE -->
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left border-collapse">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                <tr class="/50 border-b border-gray-200 bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">{{ $t('month_no') }}</th>
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">{{ $t('due_date') }}</th>
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">To'langan Sana</th>
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">{{ $t('expected') }}</th>
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">Asl To'lov</th>
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">To'lov Summasi</th>
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">To'lov Usuli</th>
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">{{ $t('remaining_debt') }}</th>
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">{{ $t('penalty_accrued') }}</th>
                                    <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider text-right">{{ $t('status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="(schedule, index) in selectedContract.payment_schedules" 
                                    :key="schedule.id" 
                                    class="border-b border-gray-100 hover:/[0.03] transition-colors bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors"
                                    :class="{'bg-indigo-50 border-l-2 border-l-neon-magenta': schedule.remaining_debt > 0 && new Date(schedule.due_date) < new Date()}"
                                >
                                    <td class="p-3 text-sm font-bold text-gray-900">{{ index + 1 }}</td>
                                    <td class="p-3 text-sm font-mono text-gray-900/80">{{ schedule.due_date }}</td>
                                    <td class="p-3 text-sm font-mono text-gray-900/60">
                                        <div v-if="editingScheduleId === schedule.id">
                                            <input type="date" v-model="editingActualPaymentDate" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-28 text-gray-900 text-xs py-1 px-2">
                                        </div>
                                        <div v-else>
                                            {{ schedule.actual_payment_date ? new Date(schedule.actual_payment_date).toLocaleDateString() : '-' }}
                                        </div>
                                    </td>
                                    <td class="p-3 text-sm font-mono text-blue-600">{{ $currency(schedule.expected_amount) }}</td>
                                    <td class="p-3 text-sm font-mono text-gray-900/80">
                                        {{ schedule.original_payment_amount > 0 ? $currency(schedule.original_payment_amount) : '-' }}
                                    </td>
                                    <td class="p-3 text-sm font-mono text-emerald-600">
                                        <div v-if="editingScheduleId === schedule.id" class="flex items-center gap-2">
                                            <input type="number" step="0.01" v-model="editingPaidAmount" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-24 text-gray-900 text-xs py-1 px-2">
                                            <button @click="saveSchedulePayment(schedule)" :disabled="isProcessing" class="bg-green-50 text-green-700 border border-green-200 rounded-md px-2 py-1 text-xs font-medium">{{ $t('save') }}</button>
                                            <button @click="editingScheduleId = null" class="px-2 py-1 text-gray-900 text-[10px] rounded hover:bg-white/30 transition-all">{{ $t('cancel') }}</button>
                                        </div>
                                        <div v-else class="flex items-center gap-2 cursor-pointer group" @click="editSchedule(schedule)">
                                            {{ $currency(schedule.paid_amount || 0) }}
                                            <span class="text-[10px] opacity-0 group-hover:opacity-100 text-blue-600 transition-opacity border border-blue-200 rounded px-1"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></span>
                                        </div>
                                    </td>
                                    <td class="p-3 text-sm font-mono text-gray-900/80">
                                        <div v-if="editingScheduleId === schedule.id">
                                            <select v-model="editingPaymentMethod" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-20 text-gray-900 text-xs py-1 px-2">
                                                <option value="naqd">Naqd</option>
                                                <option value="bank">Bank</option>
                                                <option value="karta">Karta</option>
                                            </select>
                                        </div>
                                        <div v-else class="uppercase text-xs" :class="{'text-blue-600': schedule.payment_method === 'bank', 'text-emerald-600': schedule.payment_method === 'naqd', 'text-indigo-600': schedule.payment_method === 'karta'}">
                                            {{ schedule.payment_method || '-' }}
                                        </div>
                                    </td>
                                    <td class="p-3 text-sm font-mono text-indigo-600">{{ $currency(schedule.remaining_debt || 0) }}</td>
                                    <td class="p-3 text-sm font-mono text-indigo-600">{{ $currency(schedule.penalty_for_this_month || 0) }}</td>
                                    <td class="p-3 text-right">
                                        <span class="inline-block px-2 py-1 rounded text-[10px] uppercase tracking-widest border"
                                            :class="{ 'bg-emerald-50 text-emerald-600 border-emerald-200': schedule.status === 'paid', 'bg-amber-50 text-amber-600 border-amber-200': schedule.status === 'partial', 'bg-indigo-50 text-indigo-600 border-neon-magenta/30 shadow-sm': schedule.status === 'overdue', ' text-gray-500 border-gray-300': schedule.status === 'pending' }">
                                            {{ schedule.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- REFUNDS LEDGER TABLE -->
                    <div v-if="(selectedContract.status === 'cancelled' || selectedContract.status === 'canceled') && refundTransactions.length > 0" class="mt-8">
                        <h3 class="text-sm font-bold text-red-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-red-600 shadow-sm"></span>
                            Mijozga Qaytarilgan To'lovlar Tarixi
                        </h3>
                        <div class="overflow-x-auto rounded-xl border border-red-200">
                            <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left border-collapse">
                                <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                    <tr class="border-b border-red-200 bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                        <th class="p-3 text-xs font-mono text-red-600 font-normal uppercase tracking-wider">Tranzaksiya ID</th>
                                        <th class="p-3 text-xs font-mono text-red-600 font-normal uppercase tracking-wider">Sana</th>
                                        <th class="p-3 text-xs font-mono text-red-600 font-normal uppercase tracking-wider">To'lov Usuli</th>
                                        <th class="p-3 text-xs font-mono text-red-600 font-normal uppercase tracking-wider">Summa</th>
                                        <th class="p-3 text-xs font-mono text-red-600 font-normal uppercase tracking-wider">Izoh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="transaction in refundTransactions" 
                                        :key="transaction.id" 
                                        class="border-b border-gray-100 /50 hover: transition-colors bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors"
                                    >
                                        <td class="p-3 text-sm font-mono text-gray-900/80">#{{ transaction.id }}</td>
                                        <td class="p-3 text-sm font-mono text-gray-900/80">{{ new Date(transaction.created_at).toLocaleString() }}</td>
                                        <td class="p-3 text-sm font-mono text-gray-900/60 uppercase">
                                            <span class="px-2 py-0.5 border border-gray-300 rounded text-[10px]">{{ transaction.payment_method }}</span>
                                        </td>
                                        <td class="p-3 text-sm font-mono font-bold text-red-600">{{ $currency(transaction.amount) }}</td>
                                        <td class="p-3 text-sm font-mono text-gray-900/60">{{ transaction.notes || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- MODAL: CREATE / EDIT CLIENT -->
        <div v-if="showCreateClientModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showCreateClientModal = false"></div>
            <div class="relative bg-[#f8fafc] p-8 rounded-3xl w-full max-w-6xl shadow-2xl border border-gray-100 transition-all font-sans my-8">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <span class="w-1.5 h-6 bg-[#f2a900] rounded-full"></span>
                        Mijoz qo'shish
                    </h2>
                    <button type="button" @click="showCreateClientModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveClient" class="space-y-6">
                    <!-- Row 1: Familiya, Ism, Sharif, Tug'ilgan sanasi -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Familiya</label>
                            <input v-model="clientForm.last_name" type="text" placeholder="Familiyangizni kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Ism</label>
                            <input v-model="clientForm.first_name" type="text" placeholder="Ismingizni kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Sharif</label>
                            <input v-model="clientForm.middle_name" type="text" placeholder="Sharifingizni kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Tug'ilgan sanasi</label>
                            <input v-model="clientForm.birth_date" type="date" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all" placeholder="Sanani tanlang">
                        </div>
                    </div>

                    <!-- Row 2: Shartnoma sanasi, Telefon raqami, Telefon raqami 2, Telefon raqami 3 -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Shartnoma sanasi</label>
                            <input v-model="clientForm.created_at" type="date" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all" placeholder="Sanani tanlang">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Telefon raqami</label>
                            <div class="relative">
                                <input v-model="clientForm.phone" type="text" placeholder="+998" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all pr-10" required>
                                <button type="button" @click="clientForm.phone = '+998'" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Telefon raqami 2</label>
                            <div class="relative">
                                <input v-model="clientForm.phone_2" type="text" placeholder="+998" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all pr-10">
                                <button type="button" @click="clientForm.phone_2 = '+998'" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Telefon raqami 3</label>
                            <div class="relative">
                                <input v-model="clientForm.phone_3" type="text" placeholder="+998" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all pr-10">
                                <button type="button" @click="clientForm.phone_3 = '+998'" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Passport raqami, JSHSHIR, Kim tomonidan berilgan, Pasport berilgan kuni -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Passport raqami</label>
                            <input v-model="clientForm.passport_series" type="text" placeholder="AA1234567" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">JSHSHIR</label>
                            <input v-model="clientForm.pinfl" type="text" placeholder="JSHSHIR" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Kim tomonidan berilgan</label>
                            <input v-model="clientForm.passport_issued_by" type="text" placeholder="Kim tomonidan berilgan" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Pasport berilgan kuni</label>
                            <input v-model="clientForm.passport_issued_date" type="date" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all" placeholder="Sanani tanlang">
                        </div>
                    </div>

                    <!-- Row 4: Jinsi, Region, Tuman / Shahar, Manzil -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-4">Jinsi</label>
                            <div class="flex items-center gap-6 text-xs text-gray-800 pt-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="clientForm.gender" value="erkak" class="w-4 h-4 text-[#f2a900] border-gray-300 focus:ring-[#f2a900] focus:ring-2 focus:ring-offset-0">
                                    <span>Erkak</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" v-model="clientForm.gender" value="ayol" class="w-4 h-4 text-[#f2a900] border-gray-300 focus:ring-[#f2a900] focus:ring-2 focus:ring-offset-0">
                                    <span>Ayol</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Region</label>
                            <div class="relative">
                                <select v-model="clientForm.region" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer pr-10">
                                    <option value="">Tanlang</option>
                                    <option value="toshkent">Toshkent sh.</option>
                                    <option value="samarqand">Samarqand</option>
                                    <option value="fargona">Farg'ona</option>
                                    <option value="namangan">Namangan</option>
                                    <option value="andijon">Andijon</option>
                                    <option value="buxoro">Buxoro</option>
                                    <option value="qashqadaryo">Qashqadaryo</option>
                                    <option value="surxondaryo">Surxondaryo</option>
                                    <option value="jizzax">Jizzax</option>
                                    <option value="sirdaryo">Sirdaryo</option>
                                    <option value="navoiy">Navoiy</option>
                                    <option value="xorazm">Xorazm</option>
                                    <option value="qoraqalpogiston">Qoraqalpog'iston</option>
                                </select>
                                <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Tuman / Shahar</label>
                            <div class="relative">
                                <select v-model="clientForm.district" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer pr-10">
                                    <option value="">Tanlang</option>
                                    <option value="tuman">Tuman / Shahar</option>
                                </select>
                                <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Manzil</label>
                            <input v-model="clientForm.address" type="text" placeholder="Manzilni kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                        </div>
                    </div>

                    <!-- Row 5: Passport rasmlari, Biz haqimizda qayerdan bildingiz?, Shartnoma tili, Shartnoma id -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Passport rasmlari</label>
                            <div class="flex items-center gap-3">
                                <label class="w-24 h-24 border border-dashed border-gray-300 hover:border-[#f2a900] rounded-2xl flex flex-col justify-center items-center gap-1 cursor-pointer transition-all bg-white relative overflow-hidden shadow-sm">
                                    <template v-if="!clientForm.passport_photo">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span class="text-[10px] text-gray-500 font-medium">Old tomoni</span>
                                    </template>
                                    <template v-else>
                                        <svg class="w-6 h-6 text-emerald-500 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span class="text-[9px] text-emerald-600 font-bold px-1 truncate max-w-full" :title="clientForm.passport_photo.name">{{ clientForm.passport_photo.name }}</span>
                                    </template>
                                    <input type="file" class="hidden" @change="e => { clientForm.passport_photo = e.target.files[0] }">
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Biz haqimizda qayerdan bildingiz?</label>
                            <div class="relative">
                                <select v-model="clientForm.source" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer pr-10">
                                    <option value="">Tanlang</option>
                                    <option value="instagram">Instagram</option>
                                    <option value="telegram">Telegram</option>
                                    <option value="facebook">Facebook</option>
                                    <option value="tavsiya">Tavsiya</option>
                                    <option value="banner">Tashqi reklama</option>
                                </select>
                                <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Shartnoma tili</label>
                            <div class="relative">
                                <select v-model="clientForm.contract_language" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer pr-10">
                                    <option value="O'zbek tili">O'zbek tili</option>
                                    <option value="Rus tili">Rus tili</option>
                                </select>
                                <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Shartnoma id</label>
                            <div class="relative">
                                <input v-model="clientForm.contract_id" type="text" placeholder="Shartnoma id" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all pr-10">
                                <span class="absolute right-3 top-3 text-gray-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-center gap-4 pt-4">
                        <button type="button" @click="showCreateClientModal = false" class="px-8 py-3 text-xs font-bold text-gray-800 bg-white border border-gray-300 rounded-full hover:bg-gray-50 transition-all min-w-[140px]">
                            Bekor qilish
                        </button>
                        <button type="submit" :disabled="isProcessing" class="px-8 py-3 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-full transition-all min-w-[140px] shadow-sm disabled:opacity-50">
                            Qo'shish
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: CREATE CONTRACT -->
        <div v-if="showCreateContractModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-500/50" @click="showCreateContractModal = false"></div>
            <div class="relative bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl w-full max-w-3xl border border-emerald-200">
                <h2 class="text-xl font-bold text-gray-900 mb-6 uppercase tracking-widest shadow-neon-green/50">
                    {{ isEditingContract ? 'Shartnomani Tahrirlash' : $t('add') + ' ' + $t('contract') }}
                </h2>
                <form @submit.prevent="createContract" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('contract_date_optional') }}</label>
                            <input v-model="contractForm.created_at" type="date" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-emerald-600" title="O'tmish sanada shartnoma tuzish uchun">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('contract_number_optional') }}</label>
                            <input v-model="contractForm.contract_number" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-emerald-600" placeholder="Masalan: SH-001">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase">Xaridor Turi</label>
                        <div class="flex gap-4 mb-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.buyer_type" value="client" class="text-blue-600 focus:ring-neon-cyan border-gray-300">
                                <span class="text-xs text-gray-900">Mijoz (Client)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.buyer_type" value="partner" class="text-amber-600 focus:ring-neon-yellow border-gray-300">
                                <span class="text-xs text-gray-900 text-amber-600">Ta'sischi Xaridi (Partner)</span>
                            </label>
                        </div>
                        
                        <!-- Client Select -->
                        <SearchableSelect 
                            v-if="contractForm.buyer_type === 'client'" 
                            v-model="contractForm.client_id" 
                            :options="clientsOptions" 
                            placeholder="Mijozni tanlang" 
                            :required="contractForm.buyer_type === 'client'"
                        />
                        
                        <!-- Partner Select -->
                        <SearchableSelect 
                            v-if="contractForm.buyer_type === 'partner'" 
                            v-model="contractForm.partner_id" 
                            :options="partnersOptions" 
                            placeholder="Ta'sischini tanlang" 
                            :required="contractForm.buyer_type === 'partner'"
                        />
                    </div>
                    <div class="relative">
                        <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('select_unit') }}</label>
                        <div class="relative">
                            <input 
                                type="text" 
                                v-model="unitSearchQuery" 
                                @focus="showUnitDropdown = true"
                                @blur="showUnitDropdown = false"
                                class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full pr-8" 
                                placeholder="Xonadonni qidiring (masalan: A503)..."
                                required
                            >
                            <input type="hidden" v-model="contractForm.unit_id">
                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-900/50 pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                            </div>
                        </div>
                        <div v-if="showUnitDropdown" class="absolute z-50 w-full mt-1 max-h-60 overflow-y-auto bg-gray-500/50 border border-gray-300 rounded-lg">
                            <div 
                                v-for="u in filteredUnits" 
                                :key="u.id" 
                                @mousedown.prevent="selectUnit(u)"
                                class="px-4 py-2 hover:bg-blue-50 cursor-pointer text-sm text-gray-900 transition-colors"
                            >
                                {{ formatUnitDisplay(u) }}
                            </div>
                            <div v-if="filteredUnits.length === 0" class="px-4 py-2 text-sm text-gray-500 italic">
                                Xonadon topilmadi...
                            </div>
                        </div>
                    </div>

                    <!-- Mulk Turi Selection -->
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase">{{ $t('property_type_template') }}</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.contract_type" value="residential" class="text-blue-600 focus:ring-neon-cyan border-gray-300">
                                <span class="text-xs text-gray-900">{{ $t('residential_apartment') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.contract_type" value="commercial" class="text-blue-600 focus:ring-neon-cyan border-gray-300">
                                <span class="text-xs text-gray-900">{{ $t('commercial_shop') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Dynamic Unit Details -->
                    <div v-if="selectedUnit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-3 border border-gray-200 rounded-xl bg-gray-500/50">
                        <div class="col-span-1 sm:col-span-2">
                            <p class="text-[10px] font-mono text-gray-500 uppercase">{{ $t('object_block') }}</p>
                            <p class="text-xs text-gray-900 font-bold">{{ selectedUnit.floor?.building?.name || 'Noma\'lum' }} (Maydon: {{ selectedUnit.area_sqm }} kv.m)</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('floor') }}</label>
                            <input v-model="contractForm.floor" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900" placeholder="Qavatni kiriting">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('number_of_rooms') }}</label>
                            <input v-model="contractForm.rooms" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900" placeholder="Xonalar sonini kiriting">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" v-if="selectedUnit">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('total_price_with_vat') }}</label>
                            <input v-model="contractForm.total_amount" type="number" step="any" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-emerald-600 font-bold text-lg" required placeholder="Umumiy summani kiriting">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('price_per_sqm_label') }} (Avtomatik hisoblandi)</label>
                            <input v-model="contractForm.price_per_sqm" type="number" step="any" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full opacity-70 cursor-not-allowed" readonly tabindex="-1">
                        </div>
                    </div>

                    <!-- Payment Type Selection -->
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase">{{ $t('payment_type') }}</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.payment_type" value="full" class="text-blue-600 focus:ring-neon-cyan border-gray-300">
                                <span class="text-xs text-gray-900">{{ $t('one_time_full_payment') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.payment_type" value="installment" class="text-blue-600 focus:ring-neon-cyan border-gray-300">
                                <span class="text-xs text-gray-900">{{ $t('installment_payment') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div v-if="contractForm.payment_type === 'installment'">
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('down_payment') }}</label>
                            <input v-model="contractForm.down_payment" type="number" step="any" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                        </div>
                        <div v-if="contractForm.payment_type === 'installment'">
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ $t('term_months') }}</label>
                            <input v-model="contractForm.term_months" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" required>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-200 mt-4">
                        <h3 class="text-sm font-bold text-blue-600 uppercase mb-4">Hozirgi Vaqtda To'lov Qabul Qilish (Ixtiyoriy)</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Haqiqatda qilingan to'lov</label>
                                <input v-model="contractForm.actual_paid_amount" type="number" step="0.01" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-amber-600" placeholder="Masalan: 600000000">
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">To'lov Usuli</label>
                                <select v-model="contractForm.payment_method" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full">
                                    <option value="bank">{{ $t('bank_account') }}</option>
                                    <option value="main">{{ $t('cash_account') }}</option>
                                    <option value="credit">💳 Plastik / Terminal</option>
                                </select>
                            </div>
                        </div>
                        <p v-if="contractForm.actual_paid_amount > 0" class="text-[10px] text-gray-500 mt-2 font-mono">
                            Moliya bo'limi ushbu to'lovni tasdiqlayotganda ortiqcha summa avtomatik ravishda <strong class="text-amber-600">Naqdlashtirish (Qaytim)</strong> navbatiga tushadi.
                        </p>
                    </div>

                    <div class="flex justify-end gap-4 mt-8">
                        <button type="button" @click="showCreateContractModal = false" class="px-4 py-2 text-xs font-mono uppercase tracking-widest text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-emerald-600 text-white font-bold uppercase tracking-widest rounded hover:shadow-sm transition-all disabled:opacity-50">{{ isEditingContract ? 'Saqlash' : $t('create_pipeline') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- CREATE LEAD MODAL -->
        <div v-if="showCreateLeadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500/50 transition-all">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl w-full max-w-md rounded-2xl border border-blue-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-500/50">
                    <h2 class="text-xl font-display font-bold text-gray-900 uppercase tracking-widest">{{ isEditingLead ? 'Lidni Tahrirlash' : 'Yangi Lid Qo\'shish' }}</h2>
                    <button @click="showCreateLeadModal = false" class="text-gray-500 hover:text-gray-900 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6">
                    <form @submit.prevent="submitNewLead" class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1.5 ml-1">{{ $t('first_last_name') }}</label>
                            <input v-model="newLeadForm.full_name" required type="text" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Masalan: Azizbek">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1.5 ml-1">{{ $t('phone_number') }}</label>
                            <input v-model="newLeadForm.phone" required type="tel" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="+998 90 123 45 67">
                        </div>
                        <button type="submit" :disabled="isProcessing" class="w-full mt-4 bg-blue-50 border border-blue-500 text-blue-600 py-3 rounded-xl font-display font-bold tracking-widest uppercase hover:bg-blue-600 hover:text-black transition-all disabled:opacity-50">
                            {{ isProcessing ? 'Saqlanmoqda...' : 'Saqlash' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL: QUICK SMS -->
        <QuickSmsModal v-model:show="showQuickSmsModal" :lead="activeLead" @sent="router.reload()" />

    </AdminLayout>
</template>

