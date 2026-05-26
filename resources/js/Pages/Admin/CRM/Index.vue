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
const clientForm = ref({ first_name: '', last_name: '', middle_name: '', passport_series: '', pinfl: '', phone: '', address: '', passport_issued_date: '', passport_issued_by: '', created_at: '' });
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
    clientForm.value = { first_name: '', last_name: '', middle_name: '', passport_series: '', pinfl: '', phone: '', address: '', passport_issued_date: '', passport_issued_by: '', created_at: '' };
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
        phone: client.phone, 
        address: client.address || '',
        passport_issued_date: client.passport_issued_date || '',
        passport_issued_by: client.passport_issued_by || '',
        created_at: client.created_at ? new Date(client.created_at).toISOString().slice(0, 10) : ''
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
    const prefix = isCommercial && !String(u.unit_number).startsWith('D-') ? 'D-' : '';
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
const searchQuery = ref('');
const legacyFilter = ref('all'); // 'all', 'native', 'legacy'

const leadsByStatus = computed(() => {
    return {
        new: props.leads.filter(l => l.status === 'new'),
        contacted: props.leads.filter(l => l.status === 'contacted'),
        qualified: props.leads.filter(l => l.status === 'qualified'),
        lost: props.leads.filter(l => l.status === 'lost')
    };
});

const filteredClients = computed(() => {
    let result = props.clients;
    
    if (searchQuery.value) {
        const lowerQuery = searchQuery.value.toLowerCase();
        result = result.filter(c => 
            (c.first_name && c.first_name.toLowerCase().includes(lowerQuery)) ||
            (c.last_name && c.last_name.toLowerCase().includes(lowerQuery)) ||
            (c.pinfl && c.pinfl.includes(lowerQuery))
        );
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
        <!-- TABS NAV -->
        <div class="mb-8 flex border-b border-gray-200 pb-4 overflow-x-auto whitespace-nowrap hide-scrollbar">
            <button 
                @click="activeTab = 'leads'"
                :class="['px-6 py-2 text-sm font-display font-bold uppercase tracking-wider transition-all rounded-t-lg', activeTab === 'leads' ? 'text-blue-600 border-b-2 border-blue-500 ' : 'text-gray-500 hover:text-gray-900']"
            >
                {{ $t('kanban_leads') }}
            </button>
            <button 
                @click="activeTab = 'clients'"
                :class="['px-6 py-2 text-sm font-display font-bold uppercase tracking-wider transition-all rounded-t-lg', activeTab === 'clients' ? 'text-amber-600 border-b-2 border-amber-500 ' : 'text-gray-500 hover:text-gray-900']"
            >
                {{ $t('clients_roster') }}
            </button>
            <button 
                @click="activeTab = 'contracts'"
                :class="['px-6 py-2 text-sm font-display font-bold uppercase tracking-wider transition-all rounded-t-lg', activeTab === 'contracts' ? 'text-emerald-600 border-b-2 border-emerald-500 ' : 'text-gray-500 hover:text-gray-900']"
            >
                Shartnomalar
            </button>
            <button 
                @click="activeTab = 'canceled'"
                :class="['px-6 py-2 text-sm font-display font-bold uppercase tracking-wider transition-all rounded-t-lg', activeTab === 'canceled' ? 'text-red-600 border-b-2 border-red-500 ' : 'text-gray-500 hover:text-gray-900']"
            >
                Bekor Qilinganlar <span v-if="canceledContracts.length > 0" class="ml-2 px-2 py-0.5 bg-red-600 text-white rounded-full text-xs animate-pulse">{{ canceledContracts.length }}</span>
            </button>
            <button 
                @click="activeTab = 'overdue'"
                :class="['px-6 py-2 text-sm font-display font-bold uppercase tracking-wider transition-all rounded-t-lg', activeTab === 'overdue' ? 'text-indigo-600 border-b-2 border-neon-magenta ' : 'text-gray-500 hover:text-gray-900']"
            >
                {{ $t('overdue_radar') }} <span v-if="overdueSchedules.length > 0" class="ml-2 px-2 py-0.5 bg-indigo-600 text-white rounded-full text-xs animate-pulse">{{ overdueSchedules.length }}</span>
            </button>
            
            <!-- Global Refresh Button -->
            <div class="ml-auto pl-4 border-l border-gray-200 flex items-center">
                <button 
                    @click="refreshData" 
                    :disabled="isProcessing"
                    class="flex items-center gap-2 px-4 py-1.5 bg-gray-900/50 border border-gray-300 text-blue-600 hover:bg-blue-600 hover:text-black rounded-lg text-xs font-mono font-bold uppercase tracking-widest transition-all shadow-sm disabled:opacity-50 group"
                >
                    <span class="group-hover:rotate-180 transition-transform duration-500 inline-block"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg></span> 
                    {{ $t('refresh_live') }}
                </button>
            </div>
        </div>

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
        <div v-if="activeTab === 'clients'" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl min-h-[500px]">
            <div class="sticky top-0 z-20 bg-[#0d1117]/80 px-6 py-5 -mx-6 -mt-6 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-gray-200 shadow-lg rounded-t-2xl">
                <div class="flex items-center gap-4">
                    <h2 class="text-xl font-display font-bold text-gray-900">{{ $t('clients_database') }}</h2>
                    <a :href="route('admin.crm.export.clients.excel')" class="px-4 py-2 bg-emerald-50 border border-emerald-500 text-emerald-600 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-emerald-600 hover:text-black transition-all shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline-block mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> EXCEL
                    </a>
                    <a :href="route('admin.crm.export.clients.pdf')" target="_blank" class="px-4 py-2 bg-red-50 border border-red-500 text-red-600 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-red-600 hover:text-gray-900 transition-all shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline-block mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> PDF
                    </a>
                    <button @click="openCreateClientModal" class="px-4 py-2 bg-blue-50 border border-blue-500 text-blue-600 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-blue-600 hover:text-black transition-all shadow-sm">
                        + {{ $t('add') }} {{ $t('client') }}
                    </button>
                </div>

                <input 
                    v-model="searchQuery" 
                    type="text" 
                    :placeholder="$t('search_clients_pinfl')" 
                    class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-64 border-blue-200 focus:border-blue-500 focus:ring-neon-cyan shadow-sm focus:shadow-sm bg-gray-900/50"
                >
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                        <tr class="/50 border-b border-gray-200 bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                            <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">Mijoz</th>
                            <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">Telefon</th>
                            <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">Pasport / PINFL</th>
                            <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider">Shartnomalar</th>
                            <th class="p-3 text-xs font-mono text-gray-500 font-normal uppercase tracking-wider text-right">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr 
                            v-for="client in filteredClients" 
                            :key="client.id" 
                            class="border-b border-gray-100 hover:/[0.03] transition-colors group bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors"
                        >
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-neon-cyan/20 to-neon-blue/20 border border-blue-200 flex items-center justify-center text-blue-600 font-display font-bold text-sm shadow-sm shrink-0">
                                        {{ getInitials(client.first_name, client.last_name) }}
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-900 group-hover:text-blue-600 transition-colors" :title="`${client.last_name} ${client.first_name} ${client.middle_name || ''}`">
                                            {{ client.last_name }} {{ client.first_name }}
                                        </h3>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-[10px] font-mono text-gray-900/60">{{ client.middle_name || '' }}</span>
                                            <span v-if="client.id === latestClientId" class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200 uppercase animate-pulse shadow-sm" title="Oxirgi qo'shilgan mijoz">Oxirgi qo'shilgan</span>
                                            <span v-if="client.is_legacy" class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-bold bg-neon-purple/20 text-neon-purple border border-neon-purple/50 uppercase shadow-sm" title="{{ $t('imported_legacy') }}">{{ $t('imported') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 text-sm font-mono text-blue-600">
                                {{ client.phone || 'N/A' }}
                            </td>
                            <td class="p-3 text-sm font-mono text-gray-900/80">
                                <div>{{ client.passport_number || client.passport_series || 'N/A' }}</div>
                                <div class="text-[10px] text-gray-500 mt-0.5">PINFL: {{ client.pinfl || 'N/A' }}</div>
                            </td>
                            <td class="p-3">
                                <div v-if="client.contracts && client.contracts.length > 0" class="flex flex-col gap-1 max-w-[250px] whitespace-normal">
                                    <div v-for="contract in client.contracts" :key="contract.id" class="bg-green-50 text-green-700 border border-green-200 rounded-md px-2 py-1 text-xs font-medium" @click="openScheduleModal(contract)">
                                        <span class="text-gray-900 truncate" style="max-width: 120px;">🏠 Unit {{ contract.unit?.unit_number }}</span>
                                        <span class="text-emerald-600 font-bold ml-2">{{ $currency(contract.total_amount) }}</span>
                                    </div>
                                </div>
                                <span v-else class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">{{ $t('no_purchases') }}</span>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex items-center justify-end gap-2 opacity-50 group-hover:opacity-100 transition-opacity">
                                    <button v-if="isAdmin" @click="openEditClientModal(client)" class="w-8 h-8 rounded-full bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center hover:bg-amber-500 hover:text-black transition-colors shadow-sm" title="Tahrirlash">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                    <button v-if="isAdmin" @click="deleteClient(client.id)" class="w-8 h-8 rounded-full bg-indigo-50 border border-neon-magenta/30 text-indigo-600 flex items-center justify-center hover:bg-indigo-600 hover:text-gray-900 transition-colors shadow-sm" title="O'chirish">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors" v-if="filteredClients.length === 0">
                            <td colspan="5" class="py-20 text-center text-gray-500 font-mono text-sm border-dashed">
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
                    class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-64 border-emerald-200 focus:border-emerald-500 focus:ring-neon-green shadow-sm focus:shadow-sm bg-gray-900/50"
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
                                        <div class="w-full h-1.5 bg-gray-900/50 rounded-full overflow-hidden border border-gray-100 relative">
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
    
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4 p-3 rounded-lg bg-gray-900/50 border border-red-200">
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
        <div v-if="showModal && selectedContract" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 transition-all">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl w-full max-w-6xl rounded-2xl border border-blue-200 shadow-sm overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-900/50">
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

                        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-3 rounded-xl border border-gray-200 flex flex-col md:flex-row items-center gap-4 bg-gray-900/50">
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
                        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-gray-200 bg-gray-900/50">
                            <p class="text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1">{{ $t('total_amount') }}</p>
                            <p class="text-lg font-bold text-emerald-600 font-mono">{{ $currency(selectedContract.total_amount) }}</p>
                        </div>
                        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-gray-200 bg-gray-900/50">
                            <p class="text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1">{{ $t('down_payment') }}</p>
                            <p class="text-lg font-bold text-gray-900 font-mono">{{ $currency(selectedContract.down_payment) }}</p>
                        </div>
                        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-gray-200 bg-gray-900/50">
                            <p class="text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1">{{ $t('monthly_schedule_payment') }}</p>
                            <p class="text-lg font-bold text-blue-600 font-mono">{{ selectedContract.payment_schedules?.length ? $currency(selectedContract.payment_schedules[0].expected_amount) : '0' }}</p>
                        </div>
                        <div v-if="selectedContract.status === 'cancelled' || selectedContract.refunded_amount > 0" class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-red-200 bg-gray-900/50">
                            <p class="text-[10px] font-mono text-red-600 uppercase tracking-widest mb-1">Qaytarilgan</p>
                            <p class="text-lg font-bold text-red-600 font-mono">{{ $currency(selectedContract.refunded_amount || 0) }}</p>
                        </div>
                    </div>

                    <!-- AI VERIFICATION PORTAL -->
                    <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-blue-200 mb-8 bg-gray-900/50 flex flex-col md:flex-row justify-between items-center gap-4">
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
        <div v-if="showCreateClientModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50" @click="showCreateClientModal = false"></div>
            <div class="relative bg-white p-6 rounded-lg w-full max-w-lg border border-gray-200">
                <h2 class="text-xl font-bold text-gray-900 mb-6 uppercase tracking-wide">
                    {{ isEditingClient ? t('edit_client') : $t('add') + ' ' + $t('client') }}
                </h2>
                <form @submit.prevent="saveClient" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1 uppercase">{{ $t('registration_date_optional') }}</label>
                        <input v-model="clientForm.created_at" type="date" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" title="O'tmish sanada ro'yxatdan o'tkazish uchun">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1 uppercase">{{ $t('first_name') }}</label>
                            <input v-model="clientForm.first_name" type="text" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1 uppercase">{{ $t('last_name') }}</label>
                            <input v-model="clientForm.last_name" type="text" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1 uppercase">Otasining ismi</label>
                            <input v-model="clientForm.middle_name" type="text" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1 uppercase">{{ $t('passport_series') }}</label>
                            <input v-model="clientForm.passport_series" type="text" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1 uppercase">{{ $t('pinfl') }}</label>
                            <input v-model="clientForm.pinfl" type="text" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1 uppercase">{{ $t('address') }}</label>
                        <input v-model="clientForm.address" type="text" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1 uppercase">{{ $t('passport_issue_date') }}</label>
                            <input v-model="clientForm.passport_issued_date" type="date" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1 uppercase">{{ $t('issued_by') }}</label>
                            <input v-model="clientForm.passport_issued_by" type="text" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1 uppercase">{{ $t('phone_number') }}</label>
                        <input v-model="clientForm.phone" type="text" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                    <div class="flex justify-end gap-4 mt-8">
                        <button type="button" @click="showCreateClientModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 transition-colors disabled:opacity-50">{{ $t('save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: CREATE CONTRACT -->
        <div v-if="showCreateContractModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50" @click="showCreateContractModal = false"></div>
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
                                <input type="radio" v-model="contractForm.buyer_type" value="client" class="text-blue-600 focus:ring-neon-cyan bg-gray-900/50 border-gray-300">
                                <span class="text-xs text-gray-900">Mijoz (Client)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.buyer_type" value="partner" class="text-amber-600 focus:ring-neon-yellow bg-gray-900/50 border-gray-300">
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
                                class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 pr-8" 
                                placeholder="Xonadonni qidiring (masalan: A503)..."
                                required
                            >
                            <input type="hidden" v-model="contractForm.unit_id">
                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-900/50 pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                            </div>
                        </div>
                        <div v-if="showUnitDropdown" class="absolute z-50 w-full mt-1 max-h-60 overflow-y-auto bg-gray-900/50 border border-gray-300 rounded-lg">
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
                                <input type="radio" v-model="contractForm.contract_type" value="residential" class="text-blue-600 focus:ring-neon-cyan bg-gray-900/50 border-gray-300">
                                <span class="text-xs text-gray-900">{{ $t('residential_apartment') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.contract_type" value="commercial" class="text-blue-600 focus:ring-neon-cyan bg-gray-900/50 border-gray-300">
                                <span class="text-xs text-gray-900">{{ $t('commercial_shop') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Dynamic Unit Details -->
                    <div v-if="selectedUnit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-3 border border-gray-200 rounded-xl bg-gray-900/50">
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
                            <input v-model="contractForm.price_per_sqm" type="number" step="any" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50 opacity-70 cursor-not-allowed" readonly tabindex="-1">
                        </div>
                    </div>

                    <!-- Payment Type Selection -->
                    <div>
                        <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase">{{ $t('payment_type') }}</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.payment_type" value="full" class="text-blue-600 focus:ring-neon-cyan bg-gray-900/50 border-gray-300">
                                <span class="text-xs text-gray-900">{{ $t('one_time_full_payment') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.payment_type" value="installment" class="text-blue-600 focus:ring-neon-cyan bg-gray-900/50 border-gray-300">
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
                                <select v-model="contractForm.payment_method" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full bg-gray-900/50">
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
        <div v-if="showCreateLeadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 transition-all">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl w-full max-w-md rounded-2xl border border-blue-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-900/50">
                    <h2 class="text-xl font-display font-bold text-gray-900 uppercase tracking-widest">{{ isEditingLead ? 'Lidni Tahrirlash' : 'Yangi Lid Qo\'shish' }}</h2>
                    <button @click="showCreateLeadModal = false" class="text-gray-500 hover:text-gray-900 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6">
                    <form @submit.prevent="submitNewLead" class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1.5 ml-1">{{ $t('first_last_name') }}</label>
                            <input v-model="newLeadForm.full_name" required type="text" class="w-full bg-gray-900/50 border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Masalan: Azizbek">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1.5 ml-1">{{ $t('phone_number') }}</label>
                            <input v-model="newLeadForm.phone" required type="tel" class="w-full bg-gray-900/50 border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="+998 90 123 45 67">
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

