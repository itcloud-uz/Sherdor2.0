<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import QuickSmsModal from '@/Components/QuickSmsModal.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const props = defineProps({
    leads: Array,
    clients: Array,
    contracts: Array,
    units: Array,
    overdueSchedules: Array,
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
    editingPaidAmount.value = schedule.paid_amount || 0;
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
            const updatedContract = page.props.contracts.find(c => c.id === selectedContract.value.id);
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

function recalculatePenalty() {
    isProcessing.value = true;
    router.post(route('admin.crm.contracts.recalculate_penalty', selectedContract.value.id), {
        daily_penalty_rate: selectedContract.value.daily_penalty_rate
    }, {
        onSuccess: (page) => {
            isProcessing.value = false;
            const updatedContract = page.props.contracts.find(c => c.id === selectedContract.value.id);
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
        floor: contract.floor || contract.unit?.floor?.number || '',
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

// Update contract amounts when unit changes
watch(() => contractForm.value.unit_id, (newUnitId) => {
    if (newUnitId && !isEditingContract.value) {
        const u = props.units.find(unit => unit.id === newUnitId);
        if (u) {
            contractForm.value.total_amount = Number(u.price) || 0;
            const area = Number(u.area_sqm) || 0;
            contractForm.value.price_per_sqm = area > 0 ? (contractForm.value.total_amount / area).toFixed(2) : 0;
            
            contractForm.value.floor = u.floor?.number || '';
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
        <div class="mb-8 flex border-b border-white/10 pb-4 overflow-x-auto whitespace-nowrap hide-scrollbar">
            <button 
                @click="activeTab = 'leads'"
                :class="['px-6 py-2 text-sm font-display font-bold uppercase tracking-wider transition-all rounded-t-lg', activeTab === 'leads' ? 'text-neon-cyan border-b-2 border-neon-cyan bg-white/5' : 'text-text-muted hover:text-white']"
            >
                {{ $t('kanban_leads') }}
            </button>
            <button 
                @click="activeTab = 'clients'"
                :class="['px-6 py-2 text-sm font-display font-bold uppercase tracking-wider transition-all rounded-t-lg', activeTab === 'clients' ? 'text-neon-yellow border-b-2 border-neon-yellow bg-white/5' : 'text-text-muted hover:text-white']"
            >
                {{ $t('clients_roster') }}
            </button>
            <button 
                @click="activeTab = 'contracts'"
                :class="['px-6 py-2 text-sm font-display font-bold uppercase tracking-wider transition-all rounded-t-lg', activeTab === 'contracts' ? 'text-neon-green border-b-2 border-neon-green bg-white/5' : 'text-text-muted hover:text-white']"
            >
                {{ $t('smart_contracts') }}
            </button>
            <button 
                @click="activeTab = 'canceled'"
                :class="['px-6 py-2 text-sm font-display font-bold uppercase tracking-wider transition-all rounded-t-lg', activeTab === 'canceled' ? 'text-neon-red border-b-2 border-neon-red bg-white/5' : 'text-text-muted hover:text-white']"
            >
                Bekor Qilinganlar <span v-if="canceledContracts.length > 0" class="ml-2 px-2 py-0.5 bg-neon-red text-white rounded-full text-xs animate-pulse">{{ canceledContracts.length }}</span>
            </button>
            <button 
                @click="activeTab = 'overdue'"
                :class="['px-6 py-2 text-sm font-display font-bold uppercase tracking-wider transition-all rounded-t-lg', activeTab === 'overdue' ? 'text-neon-magenta border-b-2 border-neon-magenta bg-white/5' : 'text-text-muted hover:text-white']"
            >
                {{ $t('overdue_radar') }} <span v-if="overdueSchedules.length > 0" class="ml-2 px-2 py-0.5 bg-neon-magenta text-white rounded-full text-xs animate-pulse">{{ overdueSchedules.length }}</span>
            </button>
            
            <!-- Global Refresh Button -->
            <div class="ml-auto pl-4 border-l border-white/10 flex items-center">
                <button 
                    @click="refreshData" 
                    :disabled="isProcessing"
                    class="flex items-center gap-2 px-4 py-1.5 bg-black/40 border border-white/20 text-neon-cyan hover:bg-neon-cyan hover:text-black rounded-lg text-xs font-mono font-bold uppercase tracking-widest transition-all shadow-[0_0_10px_rgba(0,240,255,0.2)] disabled:opacity-50 group"
                >
                    <span class="group-hover:rotate-180 transition-transform duration-500 inline-block">🔄</span> 
                    {{ $t('refresh_live') }}
                </button>
            </div>
        </div>

        <!-- TAB 1: LEADS KANBAN -->
        <div v-if="activeTab === 'leads'" class="flex gap-4 h-full min-h-[600px] overflow-x-auto pb-4">
            <!-- New Column -->
            <div class="flex-1 min-w-[250px] glass-panel rounded-2xl p-4 flex flex-col border-t-2 border-t-[#00F0FF]"
                 @dragover.prevent @drop="dropLead('new', $event)">
                <div class="mb-4 flex items-center justify-between border-b border-white/10 pb-2">
                    <h3 class="text-sm font-bold uppercase text-white tracking-widest">{{ $t('lead_new') }}</h3>
                    <div class="flex items-center gap-2">
                        <button @click="openCreateLeadModal" class="px-2 py-0.5 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan text-[10px] font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-cyan hover:text-black transition-all shadow-[0_0_10px_rgba(0,240,255,0.2)]">
                            + Yangi Lid
                        </button>
                        <span class="text-xs font-mono bg-white/10 px-2 py-0.5 rounded-full text-neon-cyan">{{ leadsByStatus.new.length }}</span>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3 pr-2 custom-scrollbar">
                    <div v-for="lead in leadsByStatus.new" :key="lead.id" 
                         draggable="true" @dragstart="dragStart(lead, $event)"
                         class="glass-card p-4 border border-white/5 hover:border-[#00F0FF]/50 cursor-move transition-all hover:-translate-y-1 relative group">
                        <h4 class="text-sm font-bold text-white mb-1">{{ lead.full_name }}</h4>
                        <p class="text-xs text-text-muted font-mono mb-8">{{ lead.phone }}</p>
                        
                        <!-- Action Bar -->
                        <div class="absolute bottom-2 left-2 right-2 flex justify-between gap-1 border-t border-white/10 pt-2 opacity-50 group-hover:opacity-100 transition-opacity">
                            <button @click.stop="initiateLeadCall(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-green/10 text-neon-green hover:bg-neon-green/30 border border-neon-green/20 transition-colors shadow-[0_0_5px_rgba(57,255,20,0.1)] text-xs" title="Qo'ng'iroq qilish">
                                📞
                            </button>
                            <button @click.stop="openQuickSmsModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-cyan/10 text-neon-cyan hover:bg-neon-cyan/30 border border-neon-cyan/20 transition-colors shadow-[0_0_5px_rgba(0,240,255,0.1)] text-xs" title="SMS Yuborish">
                                💬
                            </button>
                            <button @click.stop="openEditLeadModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-yellow/10 text-neon-yellow hover:bg-neon-yellow/30 border border-neon-yellow/20 transition-colors shadow-[0_0_5px_rgba(252,238,9,0.1)] text-xs" title="Tahrirlash">
                                ✏️
                            </button>
                            <button @click.stop="deleteLead(lead.id)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-red/10 text-neon-red hover:bg-neon-red/30 border border-neon-red/20 transition-colors shadow-[0_0_5px_rgba(255,0,60,0.1)] text-xs" title="O'chirish">
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contacted Column -->
            <div class="flex-1 min-w-[250px] glass-panel rounded-2xl p-4 flex flex-col border-t-2 border-t-[#FCEE09]"
                 @dragover.prevent @drop="dropLead('contacted', $event)">
                <div class="mb-4 flex items-center justify-between border-b border-white/10 pb-2">
                    <h3 class="text-sm font-bold uppercase text-white tracking-widest">{{ $t('lead_contacted') }}</h3>
                    <span class="text-xs font-mono bg-white/10 px-2 py-0.5 rounded-full text-neon-yellow">{{ leadsByStatus.contacted.length }}</span>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3 pr-2 custom-scrollbar">
                    <div v-for="lead in leadsByStatus.contacted" :key="lead.id" 
                         draggable="true" @dragstart="dragStart(lead, $event)"
                         class="glass-card p-4 border border-white/5 hover:border-[#FCEE09]/50 cursor-move transition-all hover:-translate-y-1 relative group">
                        <h4 class="text-sm font-bold text-white mb-1">{{ lead.full_name }}</h4>
                        <p class="text-xs text-text-muted font-mono mb-8">{{ lead.phone }}</p>
                        
                        <!-- Action Bar -->
                        <div class="absolute bottom-2 left-2 right-2 flex justify-between gap-1 border-t border-white/10 pt-2 opacity-50 group-hover:opacity-100 transition-opacity">
                            <button @click.stop="initiateLeadCall(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-green/10 text-neon-green hover:bg-neon-green/30 border border-neon-green/20 transition-colors shadow-[0_0_5px_rgba(57,255,20,0.1)] text-xs" title="Qo'ng'iroq qilish">
                                📞
                            </button>
                            <button @click.stop="openQuickSmsModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-cyan/10 text-neon-cyan hover:bg-neon-cyan/30 border border-neon-cyan/20 transition-colors shadow-[0_0_5px_rgba(0,240,255,0.1)] text-xs" title="SMS Yuborish">
                                💬
                            </button>
                            <button @click.stop="openEditLeadModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-yellow/10 text-neon-yellow hover:bg-neon-yellow/30 border border-neon-yellow/20 transition-colors shadow-[0_0_5px_rgba(252,238,9,0.1)] text-xs" title="Tahrirlash">
                                ✏️
                            </button>
                            <button @click.stop="deleteLead(lead.id)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-red/10 text-neon-red hover:bg-neon-red/30 border border-neon-red/20 transition-colors shadow-[0_0_5px_rgba(255,0,60,0.1)] text-xs" title="O'chirish">
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Qualified Column -->
            <div class="flex-1 min-w-[250px] glass-panel rounded-2xl p-4 flex flex-col border-t-2 border-t-[#39FF14]"
                 @dragover.prevent @drop="dropLead('qualified', $event)">
                <div class="mb-4 flex items-center justify-between border-b border-white/10 pb-2">
                    <h3 class="text-sm font-bold uppercase text-white tracking-widest">{{ $t('lead_qualified') }}</h3>
                    <span class="text-xs font-mono bg-white/10 px-2 py-0.5 rounded-full text-neon-green">{{ leadsByStatus.qualified.length }}</span>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3 pr-2 custom-scrollbar">
                    <div v-for="lead in leadsByStatus.qualified" :key="lead.id" 
                         draggable="true" @dragstart="dragStart(lead, $event)"
                         class="glass-card p-4 border border-white/5 hover:border-[#39FF14]/50 cursor-move transition-all hover:-translate-y-1 relative group">
                        <h4 class="text-sm font-bold text-white mb-1">{{ lead.full_name }}</h4>
                        <p class="text-xs text-text-muted font-mono mb-8">{{ lead.phone }}</p>
                        
                        <!-- Action Bar -->
                        <div class="absolute bottom-2 left-2 right-2 flex justify-between gap-1 border-t border-white/10 pt-2 opacity-50 group-hover:opacity-100 transition-opacity">
                            <button @click.stop="initiateLeadCall(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-green/10 text-neon-green hover:bg-neon-green/30 border border-neon-green/20 transition-colors shadow-[0_0_5px_rgba(57,255,20,0.1)] text-xs" title="Qo'ng'iroq qilish">
                                📞
                            </button>
                            <button @click.stop="openQuickSmsModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-cyan/10 text-neon-cyan hover:bg-neon-cyan/30 border border-neon-cyan/20 transition-colors shadow-[0_0_5px_rgba(0,240,255,0.1)] text-xs" title="SMS Yuborish">
                                💬
                            </button>
                            <button @click.stop="openEditLeadModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-yellow/10 text-neon-yellow hover:bg-neon-yellow/30 border border-neon-yellow/20 transition-colors shadow-[0_0_5px_rgba(252,238,9,0.1)] text-xs" title="Tahrirlash">
                                ✏️
                            </button>
                            <button @click.stop="deleteLead(lead.id)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-red/10 text-neon-red hover:bg-neon-red/30 border border-neon-red/20 transition-colors shadow-[0_0_5px_rgba(255,0,60,0.1)] text-xs" title="O'chirish">
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lost Column -->
            <div class="flex-1 min-w-[250px] glass-panel rounded-2xl p-4 flex flex-col border-t-2 border-t-[#FF003C]"
                 @dragover.prevent @drop="dropLead('lost', $event)">
                <div class="mb-4 flex items-center justify-between border-b border-white/10 pb-2">
                    <h3 class="text-sm font-bold uppercase text-white tracking-widest">{{ $t('lead_lost') }}</h3>
                    <span class="text-xs font-mono bg-white/10 px-2 py-0.5 rounded-full text-neon-magenta">{{ leadsByStatus.lost.length }}</span>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3 pr-2 custom-scrollbar">
                    <div v-for="lead in leadsByStatus.lost" :key="lead.id" 
                         draggable="true" @dragstart="dragStart(lead, $event)"
                         class="glass-card p-4 border border-white/5 hover:border-[#FF003C]/50 cursor-move transition-all hover:-translate-y-1 relative group">
                        <h4 class="text-sm font-bold text-white mb-1">{{ lead.full_name }}</h4>
                        <p class="text-xs text-text-muted font-mono mb-8">{{ lead.phone }}</p>
                        
                        <!-- Action Bar -->
                        <div class="absolute bottom-2 left-2 right-2 flex justify-between gap-1 border-t border-white/10 pt-2 opacity-50 group-hover:opacity-100 transition-opacity">
                            <button @click.stop="initiateLeadCall(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-green/10 text-neon-green hover:bg-neon-green/30 border border-neon-green/20 transition-colors shadow-[0_0_5px_rgba(57,255,20,0.1)] text-xs" title="Qo'ng'iroq qilish">
                                📞
                            </button>
                            <button @click.stop="openQuickSmsModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-cyan/10 text-neon-cyan hover:bg-neon-cyan/30 border border-neon-cyan/20 transition-colors shadow-[0_0_5px_rgba(0,240,255,0.1)] text-xs" title="SMS Yuborish">
                                💬
                            </button>
                            <button @click.stop="openEditLeadModal(lead)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-yellow/10 text-neon-yellow hover:bg-neon-yellow/30 border border-neon-yellow/20 transition-colors shadow-[0_0_5px_rgba(252,238,9,0.1)] text-xs" title="Tahrirlash">
                                ✏️
                            </button>
                            <button @click.stop="deleteLead(lead.id)" class="flex-1 flex justify-center items-center py-1 rounded bg-neon-red/10 text-neon-red hover:bg-neon-red/30 border border-neon-red/20 transition-colors shadow-[0_0_5px_rgba(255,0,60,0.1)] text-xs" title="O'chirish">
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: CLIENTS ROSTER -->
        <div v-if="activeTab === 'clients'" class="glass-panel p-6 rounded-2xl min-h-[500px]">
            <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center gap-4">
                    <h2 class="text-xl font-display font-bold text-white">{{ $t('clients_database') }}</h2>
                    <button @click="openCreateClientModal" class="px-4 py-2 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-cyan hover:text-black transition-all shadow-[0_0_10px_rgba(0,240,255,0.2)]">
                        + {{ $t('add') }} {{ $t('client') }}
                    </button>
                </div>



                <input 
                    v-model="searchQuery" 
                    type="text" 
                    :placeholder="$t('search_clients_pinfl')" 
                    class="glass-input w-64 border-neon-cyan/50 focus:border-neon-cyan focus:ring-neon-cyan shadow-[0_0_10px_rgba(0,240,255,0.2)] focus:shadow-[0_0_20px_rgba(0,240,255,0.4)]"
                >
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <div v-for="client in filteredClients" :key="client.id" class="glass-card p-6 border border-white/10 hover:border-neon-cyan/50 transition-all hover:shadow-[0_0_20px_rgba(0,240,255,0.15)] flex flex-col justify-between h-full group relative overflow-hidden">
                    <!-- Glass highlight -->
                    <div class="absolute -inset-x-20 top-0 h-px bg-gradient-to-r from-transparent via-white/30 to-transparent transform -rotate-45 -translate-y-10 group-hover:translate-y-[200px] transition-transform duration-1000 opacity-50"></div>
                    
                    <div>
                        <div class="flex items-start gap-4 mb-4">
                            <!-- Circular Avatar -->
                            <div class="w-14 h-14 rounded-full bg-gradient-to-br from-neon-cyan/20 to-neon-blue/20 border border-neon-cyan/50 flex items-center justify-center text-neon-cyan font-display font-bold text-xl shadow-[0_0_10px_rgba(0,240,255,0.2)] shrink-0">
                                {{ getInitials(client.first_name, client.last_name) }}
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-bold text-white truncate flex items-center gap-2" :title="`${client.last_name} ${client.first_name} ${client.middle_name || ''}`">
                                    {{ client.last_name }} {{ client.first_name }} {{ client.middle_name || '' }}
                                    <span v-if="client.is_legacy" class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-bold bg-neon-purple/20 text-neon-purple border border-neon-purple/50 uppercase shadow-[0_0_5px_rgba(176,38,255,0.4)]" title="{{ $t('imported_legacy') }}">{{ $t('imported') }}</span>
                                </h3>
                                <p class="text-xs font-mono text-neon-cyan mt-1 flex items-center gap-1">
                                    <span>📞</span> {{ client.phone || 'N/A' }}
                                </p>
                            </div>
                            
                            <!-- Action Buttons Dropdown or inline -->
                            <div class="flex flex-col gap-2 shrink-0">
                                <button @click="openEditClientModal(client)" class="w-8 h-8 rounded-full bg-neon-yellow/10 border border-neon-yellow/30 text-neon-yellow flex items-center justify-center hover:bg-neon-yellow hover:text-black transition-colors shadow-[0_0_5px_rgba(252,238,9,0.2)]" title="Tahrirlash">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>
                                <button @click="deleteClient(client.id)" class="w-8 h-8 rounded-full bg-neon-magenta/10 border border-neon-magenta/30 text-neon-magenta flex items-center justify-center hover:bg-neon-magenta hover:text-white transition-colors shadow-[0_0_5px_rgba(255,0,60,0.2)]" title="O'chirish">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>

                        <!-- ID Details -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4 p-3 rounded-lg bg-black/30 border border-white/5">
                            <div>
                                <p class="text-[10px] uppercase font-mono text-text-muted">{{ $t('passport') }}</p>
                                <p class="text-xs font-mono text-white/80 mt-0.5">{{ client.passport_number || client.passport_series || 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase font-mono text-text-muted">{{ $t('pinfl') }}</p>
                                <p class="text-xs font-mono text-white/80 mt-0.5">{{ client.pinfl || 'N/A' }}</p>
                            </div>
                        </div>

                        <!-- Contracts List -->
                        <div v-if="client.contracts && client.contracts.length > 0">
                            <p class="text-[10px] uppercase font-mono text-text-muted mb-2 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-neon-green"></span>
                                {{ $t('active_purchases') }} ({{ client.contracts.length }})
                            </p>
                            <div class="space-y-2 max-h-32 overflow-y-auto pr-1 hide-scrollbar">
                                <div v-for="contract in client.contracts" :key="contract.id" class="p-2 rounded bg-neon-green/5 border border-neon-green/20 flex justify-between items-center text-xs font-mono cursor-pointer hover:bg-neon-green/10 transition-colors" @click="openScheduleModal(contract)">
                                    <span class="text-white flex items-center gap-1">
                                        🏠 Unit {{ contract.unit?.unit_number }} <span class="text-text-muted">| Qavat {{ contract.unit?.floor?.number }}</span>
                                    </span>
                                    <span class="text-neon-green font-bold">{{ $currency(contract.total_amount) }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center p-3 rounded-lg border border-dashed border-white/10 bg-black/20">
                            <p class="text-[10px] font-mono text-text-muted uppercase tracking-widest">{{ $t('no_purchases') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="filteredClients.length === 0" class="col-span-full py-20 text-center text-text-muted font-mono text-sm glass-card border-dashed">
                    {{ $t('no_clients_found') }}
                </div>
            </div>
        </div>

        <!-- TAB 3: SMART CONTRACTS -->
        <div v-if="activeTab === 'contracts'" class="glass-panel p-6 rounded-2xl min-h-[500px]">
            <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">


                <button @click="isEditingContract = false; contractForm = { buyer_type: 'client', partner_id: '', client_id: '', unit_id: '', contract_number: '', contract_type: 'residential', price_per_sqm: '', total_amount: '', down_payment: '', term_months: 36, payment_type: 'installment', created_at: '', actual_paid_amount: '', payment_method: 'bank', floor: '', rooms: '' }; showCreateContractModal = true" class="px-4 py-2 bg-neon-green/20 border border-neon-green text-neon-green text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-green hover:text-black transition-all shadow-[0_0_10px_rgba(57,255,20,0.2)]">
                    + {{ $t('add') }} {{ $t('contract') }}
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div 
                        v-for="contract in activeContracts" 
                        :key="contract.id"
                        @click="openScheduleModal(contract)"
                        class="glass-card p-6 border border-white/10 hover:border-neon-green/50 cursor-pointer transition-all hover:shadow-[0_0_20px_rgba(57,255,20,0.15)] group relative overflow-hidden flex flex-col h-full justify-between"
                    >
                        <!-- Glass highlight -->
                        <div class="absolute -inset-x-20 top-0 h-px bg-gradient-to-r from-transparent via-white/30 to-transparent transform -rotate-45 -translate-y-10 group-hover:translate-y-[200px] transition-transform duration-1000 opacity-50"></div>
                        
                        <div>
                            <div class="flex justify-between items-start mb-4">
                                <!-- ACTION BUTTONS (Edit / Delete) -->
                                <div class="absolute top-2 right-2 flex gap-1 z-10 opacity-0 group-hover:opacity-100 transition-opacity" @click.stop>
                                    <button @click="editContract(contract)" class="p-1.5 bg-neon-blue/20 text-neon-blue rounded hover:bg-neon-blue hover:text-white transition-colors" title="Tahrirlash">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button @click="deleteContract(contract.id)" class="p-1.5 bg-neon-red/20 text-neon-red rounded hover:bg-neon-red hover:text-white transition-colors" title="O'chirish">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-neon-green/20 to-black border border-neon-green/50 flex items-center justify-center text-neon-green font-display font-bold text-sm shadow-[0_0_10px_rgba(57,255,20,0.2)]">
                                        {{ getInitials(contract.client?.first_name, contract.client?.last_name) }}
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-white group-hover:text-neon-green transition-colors">{{ contract.client?.first_name }} {{ contract.client?.last_name }}</h3>
                                        <p class="text-[10px] font-mono text-neon-cyan mt-0.5">
                                            <span class="text-white/70">{{ contract.contract_number || 'Contract #' + contract.id.toString().padStart(5, '0') }}</span>
                                            | {{ $t('unit') }}: {{ contract.unit?.unit_number || 'N/A' }} | {{ contract.unit?.floor?.building?.name || 'Bino' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right flex flex-col items-end gap-1">
                                    <span v-if="contract.is_legacy" class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-neon-purple/20 text-neon-purple border border-neon-purple/50 uppercase shadow-[0_0_5px_rgba(176,38,255,0.4)]" title="{{ $t('imported_legacy') }}">{{ $t('imported') }}</span>
                                    <span class="px-2 py-1 rounded bg-white/5 border border-white/10 text-[10px] font-mono text-white/80 whitespace-nowrap">{{ contract.term_months }} {{ $t('months') }}</span>
                                </div>
                            </div>
    
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4 p-3 rounded-lg bg-black/30 border border-white/5">
                                <div>
                                    <p class="text-[10px] uppercase font-mono text-text-muted">{{ $t('down_payment') }}</p>
                                    <p class="text-xs font-mono text-white/80 mt-0.5">{{ $currency(contract.down_payment) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase font-mono text-text-muted">{{ $t('total_amount') }}</p>
                                    <p class="text-xs font-mono text-neon-green mt-0.5">{{ $currency(contract.total_amount) }}</p>
                                </div>
                            </div>
                        </div>
    
                        <div class="mt-auto">
                            <div class="flex justify-between items-end mb-1">
                                <p class="text-[10px] text-neon-green font-mono uppercase">{{ $t('total_paid') }}</p>
                                <p class="text-xs font-mono text-white">{{ $currency(calculatePaidAmount(contract)) }}</p>
                            </div>
                            <div class="w-full h-1.5 bg-black/50 rounded-full overflow-hidden border border-white/5 relative">
                                <div 
                                    class="absolute top-0 left-0 bottom-0 bg-neon-green shadow-[0_0_10px_#39FF14] transition-all duration-1000"
                                    :style="{ width: calculateProgress(contract) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            <div v-if="contracts.length === 0" class="text-center py-20 text-text-muted font-mono text-sm border border-dashed border-white/10 rounded-2xl">
                {{ $t('no_active_contracts') }}
            </div>
        </div>

        <!-- TAB CANCELED: BEKOR QILINGAN SHARTNOMALAR -->
        <div v-if="activeTab === 'canceled'" class="glass-panel p-6 rounded-2xl min-h-[500px] border border-neon-red/30">
            <h2 class="text-xl font-display font-bold text-neon-red mb-6 flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-neon-red shadow-[0_0_10px_#FF0000]"></span>
                Bekor Qilingan Shartnomalar
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div 
                        v-for="contract in canceledContracts" 
                        :key="contract.id"
                        @click="openScheduleModal(contract)"
                        class="glass-card p-6 border border-neon-red/30 hover:border-neon-red/50 cursor-pointer transition-all hover:shadow-[0_0_20px_rgba(255,0,0,0.15)] group relative overflow-hidden flex flex-col h-full justify-between opacity-80"
                    >
                        <div class="absolute -inset-x-20 top-0 h-px bg-gradient-to-r from-transparent via-white/30 to-transparent transform -rotate-45 -translate-y-10 group-hover:translate-y-[200px] transition-transform duration-1000 opacity-50"></div>
                        
                        <div>
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-neon-red/20 to-black border border-neon-red/50 flex items-center justify-center text-neon-red font-display font-bold text-sm shadow-[0_0_10px_rgba(255,0,0,0.2)]">
                                        {{ getInitials(contract.client?.first_name, contract.client?.last_name) }}
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-white group-hover:text-neon-red transition-colors">{{ contract.client?.first_name }} {{ contract.client?.last_name }}</h3>
                                        <p class="text-[10px] font-mono text-neon-red mt-0.5">
                                            <span class="text-white/70">{{ contract.contract_number || 'Contract #' + contract.id.toString().padStart(5, '0') }}</span>
                                            | Unit: {{ contract.unit?.unit_number || 'N/A' }} | {{ contract.unit?.floor?.building?.name || 'Bino' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right flex flex-col items-end gap-1">
                                    <span class="px-2 py-1 rounded bg-white/5 border border-neon-red/50 text-[10px] font-mono text-neon-red whitespace-nowrap">Bekor qilingan</span>
                                </div>
                            </div>
    
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4 p-3 rounded-lg bg-black/30 border border-neon-red/20">
                                <div>
                                    <p class="text-[10px] uppercase font-mono text-text-muted">Jami To'langan</p>
                                    <p class="text-xs font-mono text-white/80 mt-0.5">{{ $currency(calculatePaidAmount(contract)) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase font-mono text-text-muted">Qaytarilgan</p>
                                    <p class="text-xs font-mono text-neon-red mt-0.5">{{ $currency(contract.refunded_amount || 0) }}</p>
                                </div>
                            </div>
                                            </div>
                </div>
            </div>
            <div v-if="canceledContracts.length === 0" class="text-center py-20 text-neon-red font-mono text-sm border border-dashed border-neon-red/20 rounded-2xl">
                Bekor qilingan shartnomalar topilmadi
            </div>
        </div>

        <!-- TAB 4: OVERDUE RADAR -->
        <div v-if="activeTab === 'overdue'" class="glass-panel p-6 rounded-2xl min-h-[500px] border border-neon-magenta/30">
            <h2 class="text-xl font-display font-bold text-neon-magenta mb-6 flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-neon-magenta animate-pulse shadow-[0_0_10px_#FF003C]"></span>
                {{ $t('overdue_payments_radar') }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div 
                    v-for="schedule in overdueSchedules" 
                    :key="schedule.id"
                    class="glass-card p-6 border border-neon-magenta/40 hover:border-neon-magenta bg-neon-magenta/5 hover:bg-neon-magenta/10 transition-all hover:shadow-[0_0_20px_rgba(255,0,60,0.2)] relative overflow-hidden flex flex-col justify-between group"
                >
                    <!-- Warning pulse indicator -->
                    <div class="absolute top-4 right-4 w-2 h-2 rounded-full bg-neon-magenta animate-ping"></div>
                    <div class="absolute top-4 right-4 w-2 h-2 rounded-full bg-neon-magenta shadow-[0_0_10px_#FF003C]"></div>

                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-neon-magenta/20 border border-neon-magenta/50 flex items-center justify-center text-neon-magenta font-display font-bold text-sm shadow-[0_0_10px_rgba(255,0,60,0.3)]">
                                ⚠️
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-white">{{ schedule.contract?.client?.first_name }} {{ schedule.contract?.client?.last_name }}</h4>
                                <p class="text-[10px] font-mono text-neon-magenta mt-0.5">{{ $t('due_date') }}: {{ schedule.due_date }}</p>
                            </div>
                        </div>

                        <div class="p-3 rounded-lg bg-black/40 border border-neon-magenta/20 mb-4 flex justify-between items-center">
                            <div>
                                <p class="text-[10px] font-mono uppercase text-text-muted tracking-wider">{{ $t('amount_due') }}</p>
                                <p class="text-lg font-mono font-bold text-white shadow-neon-magenta/50">{{ $currency(schedule.expected_amount) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-mono uppercase text-neon-magenta tracking-wider">{{ $t('overdue') }}</p>
                                <p class="text-xs font-mono font-bold text-neon-magenta">{{ Math.floor((new Date() - new Date(schedule.due_date)) / (1000 * 60 * 60 * 24)) }} {{ $t('days') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <div v-if="schedule.last_sms_sent_at" class="text-center mb-2 text-[10px] font-mono text-neon-cyan">
                            Oxirgi SMS: {{ new Date(schedule.last_sms_sent_at).toLocaleDateString() }} {{ new Date(schedule.last_sms_sent_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}
                        </div>
                        <button @click="sendWarningSms(schedule.id)" :disabled="isProcessing" class="w-full py-2.5 bg-neon-magenta/20 border border-neon-magenta text-neon-magenta font-bold font-mono text-xs uppercase tracking-widest rounded hover:bg-neon-magenta hover:text-white transition-all shadow-[0_0_10px_rgba(255,0,60,0.3)] flex justify-center items-center gap-2 disabled:opacity-50">
                            <span>📱</span> {{ $t('send_sms_warning') }}
                        </button>
                    </div>
                </div>

                <div v-if="overdueSchedules.length === 0" class="text-center py-20 text-neon-green font-mono text-sm">
                    {{ $t('no_overdue_payments') }} 🎉
                </div>
            </div>
        </div>

        <!-- CLIENT LEDGER MODAL (GLASSMORPHIC) -->
        <div v-if="showModal && selectedContract" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md transition-all">
            <div class="glass-panel w-full max-w-6xl rounded-2xl border border-neon-cyan/30 shadow-[0_0_50px_rgba(0,240,255,0.1)] overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 border-b border-white/10 flex justify-between items-center bg-black/40">
                    <div>
                        <h2 class="text-2xl font-display font-bold text-white uppercase tracking-widest text-shadow-glow">{{ $t('client_personal_ledger') }} <span class="text-xs text-neon-cyan ml-2">{{ $t('akt_sverka') }}</span></h2>
                        <p class="text-sm text-text-muted font-mono mt-1">Contract #{{ selectedContract.contract_number || selectedContract.id.toString().padStart(5, '0') }} • {{ selectedContract.client?.first_name }} {{ selectedContract.client?.last_name }} • Unit {{ selectedContract.unit?.unit_number || 'N/A' }}</p>
                    </div>
                    <button @click="showModal = false" class="text-text-muted hover:text-white transition-colors">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto flex-1">
                    
                    <!-- ACTION HUB & SETTINGS -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                        <div class="flex items-center gap-3">
                            <a :href="`/admin/crm/ledger/${selectedContract.id}/export/pdf?lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-neon-red/20 border border-neon-red text-neon-red text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-red hover:text-black transition-all shadow-[0_0_10px_rgba(255,0,60,0.2)]">
                                📄 PDF
                            </a>
                            <a :href="`/admin/crm/contracts/${selectedContract.id}/export-doc/pdf?lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-neon-purple/20 border border-neon-purple text-neon-purple text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-purple hover:text-black transition-all shadow-[0_0_10px_rgba(176,38,255,0.2)]">
                                📄 Shartnoma
                            </a>
                            <a :href="`/admin/crm/ledger/${selectedContract.id}/export/docx?lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-neon-blue/20 border border-neon-blue text-neon-blue text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-blue hover:text-black transition-all shadow-[0_0_10px_rgba(0,102,255,0.2)]">
                                📝 Word
                            </a>
                            <a :href="`/admin/crm/ledger/${selectedContract.id}/export/xlsx?lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-neon-green/20 border border-neon-green text-neon-green text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-green hover:text-black transition-all shadow-[0_0_10px_rgba(57,255,20,0.2)]">
                                📊 Excel
                            </a>
                            <button @click="cancelContract(selectedContract.id)" :disabled="isProcessing" class="px-4 py-2 bg-neon-red/20 border border-neon-red text-neon-red text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-red hover:text-black transition-all shadow-[0_0_10px_rgba(255,0,0,0.2)] disabled:opacity-50">
                                🚫 Bekor qilish
                            </button>
                        </div>

                        <div class="glass-card p-3 rounded-xl border border-white/10 flex flex-col md:flex-row items-center gap-4 bg-black/30">
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-mono text-text-muted uppercase tracking-widest">{{ $t('daily_penalty_rate') }}</label>
                                <input type="number" step="0.01" v-model="selectedContract.daily_penalty_rate" class="glass-input w-20 text-center text-neon-cyan font-bold p-1">
                                <button @click="recalculatePenalty" :disabled="isProcessing" class="px-3 py-1 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan text-xs font-mono font-bold uppercase rounded hover:bg-neon-cyan hover:text-black transition-all shadow-[0_0_10px_rgba(0,240,255,0.2)] disabled:opacity-50">
                                    🔄 {{ $t('calculate') }}
                                </button>
                            </div>
                            <div class="hidden md:block h-8 border-r border-white/10 mx-2"></div>
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-mono text-text-muted uppercase tracking-widest">{{ $t('total_penalty') }}</label>
                                <span class="text-neon-magenta font-bold font-mono">{{ $currency(selectedContract.total_penalty_accumulated || 0) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- SUMMARY INFO -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <div class="glass-card p-4 rounded-xl border border-white/10 bg-black/30">
                            <p class="text-[10px] font-mono text-text-muted uppercase tracking-widest mb-1">{{ $t('total_amount') }}</p>
                            <p class="text-lg font-bold text-neon-green font-mono">{{ $currency(selectedContract.total_amount) }}</p>
                        </div>
                        <div class="glass-card p-4 rounded-xl border border-white/10 bg-black/30">
                            <p class="text-[10px] font-mono text-text-muted uppercase tracking-widest mb-1">{{ $t('down_payment') }}</p>
                            <p class="text-lg font-bold text-white font-mono">{{ $currency(selectedContract.down_payment) }}</p>
                        </div>
                        <div class="glass-card p-4 rounded-xl border border-white/10 bg-black/30">
                            <p class="text-[10px] font-mono text-text-muted uppercase tracking-widest mb-1">{{ $t('monthly_schedule_payment') }}</p>
                            <p class="text-lg font-bold text-neon-cyan font-mono">{{ selectedContract.payment_schedules?.length ? $currency(selectedContract.payment_schedules[0].expected_amount) : '0' }}</p>
                        </div>
                        <div v-if="selectedContract.status === 'cancelled' || selectedContract.refunded_amount > 0" class="glass-card p-4 rounded-xl border border-neon-red/30 bg-black/30">
                            <p class="text-[10px] font-mono text-neon-red uppercase tracking-widest mb-1">Qaytarilgan</p>
                            <p class="text-lg font-bold text-neon-red font-mono">{{ $currency(selectedContract.refunded_amount || 0) }}</p>
                        </div>
                    </div>

                    <!-- AI VERIFICATION PORTAL -->
                    <div class="glass-card p-4 rounded-xl border border-neon-cyan/20 mb-8 bg-black/40 flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-neon-cyan/20 border border-neon-cyan flex items-center justify-center">
                                <span class="text-neon-cyan text-xl">🤖</span>
                            </div>
                            <div>
                                <h4 class="text-white font-bold text-sm tracking-widest uppercase">{{ $t('ai_signature_verification') }}</h4>
                                <p class="text-xs font-mono" :class="{
                                    'text-text-muted': !selectedContract.ai_verification_status,
                                    'text-neon-green': selectedContract.ai_verification_status === 'passed',
                                    'text-neon-red': selectedContract.ai_verification_status === 'failed'
                                }">
                                    {{ selectedContract.ai_verification_status === 'passed' ? 'Tasdiqlangan va imzolangan' : 
                                       (selectedContract.ai_verification_status === 'failed' ? 'Imzolanmagan qayta yuklang' : 'Hujjat kutilmoqda') }}
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <span v-if="uploadStatus === 'uploading'" class="text-xs text-neon-cyan animate-pulse">{{ uploadMessage }}</span>
                            <span v-if="uploadStatus === 'success'" class="text-xs text-neon-green">{{ uploadMessage }}</span>
                            <span v-if="uploadStatus === 'error'" class="text-xs text-neon-red">{{ uploadMessage }}</span>
                            
                            <input type="file" ref="fileInput" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="uploadSignature">
                            <button @click="fileInput.click()" :disabled="isProcessing" class="px-6 py-2 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan font-bold text-xs uppercase tracking-widest rounded hover:bg-neon-cyan hover:text-black transition-all shadow-[0_0_10px_rgba(0,240,255,0.2)] disabled:opacity-50">
                                📤 Yuklash
                            </button>
                        </div>
                    </div>

                    <!-- LEDGER TABLE -->
                    <div class="overflow-x-auto rounded-xl border border-white/10">
                        <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-black/60 border-b border-white/10">
                                    <th class="p-3 text-xs font-mono text-text-muted font-normal uppercase tracking-wider">{{ $t('month_no') }}</th>
                                    <th class="p-3 text-xs font-mono text-text-muted font-normal uppercase tracking-wider">{{ $t('due_date') }}</th>
                                    <th class="p-3 text-xs font-mono text-text-muted font-normal uppercase tracking-wider">To'langan Sana</th>
                                    <th class="p-3 text-xs font-mono text-text-muted font-normal uppercase tracking-wider">{{ $t('expected') }}</th>
                                    <th class="p-3 text-xs font-mono text-text-muted font-normal uppercase tracking-wider">To'lov Summasi</th>
                                    <th class="p-3 text-xs font-mono text-text-muted font-normal uppercase tracking-wider">To'lov Usuli</th>
                                    <th class="p-3 text-xs font-mono text-text-muted font-normal uppercase tracking-wider">{{ $t('remaining_debt') }}</th>
                                    <th class="p-3 text-xs font-mono text-text-muted font-normal uppercase tracking-wider">{{ $t('penalty_accrued') }}</th>
                                    <th class="p-3 text-xs font-mono text-text-muted font-normal uppercase tracking-wider text-right">{{ $t('status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="(schedule, index) in selectedContract.payment_schedules" 
                                    :key="schedule.id" 
                                    class="border-b border-white/5 hover:bg-white/[0.03] transition-colors"
                                    :class="{'bg-neon-magenta/5 border-l-2 border-l-neon-magenta': schedule.remaining_debt > 0 && new Date(schedule.due_date) < new Date()}"
                                >
                                    <td class="p-3 text-sm font-bold text-white">{{ index + 1 }}</td>
                                    <td class="p-3 text-sm font-mono text-white/80">{{ schedule.due_date }}</td>
                                    <td class="p-3 text-sm font-mono text-white/60">
                                        <div v-if="editingScheduleId === schedule.id">
                                            <input type="date" v-model="editingActualPaymentDate" class="glass-input w-28 text-white text-xs py-1 px-2">
                                        </div>
                                        <div v-else>
                                            {{ schedule.actual_payment_date ? new Date(schedule.actual_payment_date).toLocaleDateString() : '-' }}
                                        </div>
                                    </td>
                                    <td class="p-3 text-sm font-mono text-neon-cyan">{{ $currency(schedule.expected_amount) }}</td>
                                    <td class="p-3 text-sm font-mono text-neon-green">
                                        <div v-if="editingScheduleId === schedule.id" class="flex items-center gap-2">
                                            <input type="number" step="0.01" v-model="editingPaidAmount" class="glass-input w-24 text-white text-xs py-1 px-2">
                                            <button @click="saveSchedulePayment(schedule)" :disabled="isProcessing" class="px-2 py-1 bg-neon-green/20 text-neon-green text-[10px] rounded hover:bg-neon-green hover:text-black transition-all">{{ $t('save') }}</button>
                                            <button @click="editingScheduleId = null" class="px-2 py-1 bg-white/10 text-white text-[10px] rounded hover:bg-white/30 transition-all">{{ $t('cancel') }}</button>
                                        </div>
                                        <div v-else class="flex items-center gap-2 cursor-pointer group" @click="editSchedule(schedule)">
                                            {{ $currency(schedule.paid_amount || 0) }}
                                            <span class="text-[10px] opacity-0 group-hover:opacity-100 text-neon-cyan transition-opacity border border-neon-cyan/50 rounded px-1">✏️</span>
                                        </div>
                                    </td>
                                    <td class="p-3 text-sm font-mono text-white/80">
                                        <div v-if="editingScheduleId === schedule.id">
                                            <select v-model="editingPaymentMethod" class="glass-input w-20 text-white text-xs py-1 px-2">
                                                <option value="naqd">Naqd</option>
                                                <option value="bank">Bank</option>
                                                <option value="karta">Karta</option>
                                            </select>
                                        </div>
                                        <div v-else class="uppercase text-xs" :class="{'text-neon-cyan': schedule.payment_method === 'bank', 'text-neon-green': schedule.payment_method === 'naqd', 'text-neon-magenta': schedule.payment_method === 'karta'}">
                                            {{ schedule.payment_method || '-' }}
                                        </div>
                                    </td>
                                    <td class="p-3 text-sm font-mono text-neon-magenta">{{ $currency(schedule.remaining_debt || 0) }}</td>
                                    <td class="p-3 text-sm font-mono text-neon-magenta">{{ $currency(schedule.penalty_for_this_month || 0) }}</td>
                                    <td class="p-3 text-right">
                                        <span class="inline-block px-2 py-1 rounded text-[10px] uppercase tracking-widest border"
                                            :class="{
                                                'bg-neon-green/20 text-neon-green border-neon-green/30': schedule.status === 'paid',
                                                'bg-neon-yellow/20 text-neon-yellow border-neon-yellow/30': schedule.status === 'partial',
                                                'bg-neon-magenta/20 text-neon-magenta border-neon-magenta/30 shadow-[0_0_10px_rgba(255,0,60,0.2)]': schedule.status === 'overdue',
                                                'bg-white/10 text-text-muted border-white/20': schedule.status === 'pending'
                                            }">
                                            {{ schedule.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- REFUNDS LEDGER TABLE -->
                    <div v-if="(selectedContract.status === 'cancelled' || selectedContract.status === 'canceled') && refundTransactions.length > 0" class="mt-8">
                        <h3 class="text-sm font-bold text-neon-red uppercase tracking-widest mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-neon-red shadow-[0_0_5px_#FF0000]"></span>
                            Mijozga Qaytarilgan To'lovlar Tarixi
                        </h3>
                        <div class="overflow-x-auto rounded-xl border border-neon-red/30">
                            <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-neon-red/10 border-b border-neon-red/20">
                                        <th class="p-3 text-xs font-mono text-neon-red font-normal uppercase tracking-wider">Tranzaksiya ID</th>
                                        <th class="p-3 text-xs font-mono text-neon-red font-normal uppercase tracking-wider">Sana</th>
                                        <th class="p-3 text-xs font-mono text-neon-red font-normal uppercase tracking-wider">To'lov Usuli</th>
                                        <th class="p-3 text-xs font-mono text-neon-red font-normal uppercase tracking-wider">Summa</th>
                                        <th class="p-3 text-xs font-mono text-neon-red font-normal uppercase tracking-wider">Izoh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="transaction in refundTransactions" 
                                        :key="transaction.id" 
                                        class="border-b border-white/5 bg-black/40 hover:bg-neon-red/5 transition-colors"
                                    >
                                        <td class="p-3 text-sm font-mono text-white/80">#{{ transaction.id }}</td>
                                        <td class="p-3 text-sm font-mono text-white/80">{{ new Date(transaction.created_at).toLocaleString() }}</td>
                                        <td class="p-3 text-sm font-mono text-white/60 uppercase">
                                            <span class="px-2 py-0.5 border border-white/20 rounded text-[10px]">{{ transaction.payment_method }}</span>
                                        </td>
                                        <td class="p-3 text-sm font-mono font-bold text-neon-red">{{ $currency(transaction.amount) }}</td>
                                        <td class="p-3 text-sm font-mono text-white/60">{{ transaction.notes || '-' }}</td>
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
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showCreateClientModal = false"></div>
            <div class="relative glass-panel p-6 rounded-2xl w-full max-w-lg border border-neon-cyan/30">
                <h2 class="text-xl font-bold text-white mb-6 uppercase tracking-widest text-shadow-glow shadow-neon-cyan/50">
                    {{ isEditingClient ? t('edit_client') : $t('add') + ' ' + $t('client') }}
                </h2>
                <form @submit.prevent="saveClient" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('registration_date_optional') }}</label>
                        <input v-model="clientForm.created_at" type="date" class="glass-input w-full text-neon-cyan" title="O'tmish sanada ro'yxatdan o'tkazish uchun">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('first_name') }}</label>
                            <input v-model="clientForm.first_name" type="text" class="glass-input w-full" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('last_name') }}</label>
                            <input v-model="clientForm.last_name" type="text" class="glass-input w-full" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Otasining ismi</label>
                            <input v-model="clientForm.middle_name" type="text" class="glass-input w-full">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('passport_series') }}</label>
                            <input v-model="clientForm.passport_series" type="text" class="glass-input w-full">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('pinfl') }}</label>
                            <input v-model="clientForm.pinfl" type="text" class="glass-input w-full">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('address') }}</label>
                        <input v-model="clientForm.address" type="text" class="glass-input w-full">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('passport_issue_date') }}</label>
                            <input v-model="clientForm.passport_issued_date" type="date" class="glass-input w-full">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('issued_by') }}</label>
                            <input v-model="clientForm.passport_issued_by" type="text" class="glass-input w-full">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('phone_number') }}</label>
                        <input v-model="clientForm.phone" type="text" class="glass-input w-full">
                    </div>
                    <div class="flex justify-end gap-4 mt-8">
                        <button type="button" @click="showCreateClientModal = false" class="px-4 py-2 text-xs font-mono uppercase tracking-widest text-text-muted hover:text-white transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-neon-cyan text-black font-bold uppercase tracking-widest rounded hover:shadow-[0_0_15px_#00F0FF] transition-all disabled:opacity-50">{{ $t('save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: CREATE CONTRACT -->
        <div v-if="showCreateContractModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showCreateContractModal = false"></div>
            <div class="relative glass-panel p-6 rounded-2xl w-full max-w-lg border border-neon-green/30">
                <h2 class="text-xl font-bold text-white mb-6 uppercase tracking-widest text-shadow-glow shadow-neon-green/50">
                    {{ isEditingContract ? t('edit_contract') : $t('add') + ' ' + $t('contract') }}
                </h2>
                <form @submit.prevent="createContract" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('contract_date_optional') }}</label>
                            <input v-model="contractForm.created_at" type="date" class="glass-input w-full text-neon-green" title="O'tmish sanada shartnoma tuzish uchun">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('contract_number_optional') }}</label>
                            <input v-model="contractForm.contract_number" type="text" class="glass-input w-full text-neon-green" placeholder="Masalan: SH-001">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-2 uppercase">Xaridor Turi</label>
                        <div class="flex gap-4 mb-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.buyer_type" value="client" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                                <span class="text-xs text-white">Mijoz (Client)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.buyer_type" value="partner" class="text-neon-yellow focus:ring-neon-yellow bg-black/50 border-white/20">
                                <span class="text-xs text-white text-neon-yellow">Ta'sischi Xaridi (Partner)</span>
                            </label>
                        </div>
                        
                        <!-- Client Select -->
                        <select v-if="contractForm.buyer_type === 'client'" v-model="contractForm.client_id" class="glass-input w-full bg-black/50" :required="contractForm.buyer_type === 'client'">
                            <option value="" disabled>{{ $t('select_client') }}</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.first_name }} {{ c.last_name }}</option>
                        </select>
                        
                        <!-- Partner Select -->
                        <select v-if="contractForm.buyer_type === 'partner'" v-model="contractForm.partner_id" class="glass-input w-full bg-black/50 border-neon-yellow/50" :required="contractForm.buyer_type === 'partner'">
                            <option value="" disabled>Ta'sischini tanlang</option>
                            <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.user?.name }} (Ulush: {{ p.investment_share_percentage }}%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('select_unit') }}</label>
                        <select v-model="contractForm.unit_id" class="glass-input w-full bg-black/50" required>
                            <option value="" disabled>{{ $t('select_unit') }}</option>
                            <option v-for="u in units" :key="u.id" :value="u.id">{{ $t('unit') }} {{ u.unit_number }} - {{ u.floor?.building?.name }} (Qavat: {{ u.floor?.number }})</option>
                        </select>
                    </div>

                    <!-- Mulk Turi Selection -->
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-2 uppercase">{{ $t('property_type_template') }}</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.contract_type" value="residential" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                                <span class="text-xs text-white">{{ $t('residential_apartment') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.contract_type" value="commercial" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                                <span class="text-xs text-white">{{ $t('commercial_shop') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Dynamic Unit Details -->
                    <div v-if="selectedUnit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-3 border border-white/10 rounded-xl bg-black/30">
                        <div class="col-span-1 sm:col-span-2">
                            <p class="text-[10px] font-mono text-text-muted uppercase">{{ $t('object_block') }}</p>
                            <p class="text-xs text-white font-bold">{{ selectedUnit.floor?.building?.name || 'Noma\'lum' }} (Maydon: {{ selectedUnit.area_sqm }} kv.m)</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('floor') }}</label>
                            <input v-model="contractForm.floor" type="text" class="glass-input w-full text-white" placeholder="Qavatni kiriting">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('number_of_rooms') }}</label>
                            <input v-model="contractForm.rooms" type="number" class="glass-input w-full text-white" placeholder="Xonalar sonini kiriting">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" v-if="selectedUnit">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('total_price_with_vat') }}</label>
                            <input v-model="contractForm.total_amount" type="number" step="any" class="glass-input w-full text-neon-green font-bold text-lg" required placeholder="Umumiy summani kiriting">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('price_per_sqm_label') }} (Avtomatik hisoblandi)</label>
                            <input v-model="contractForm.price_per_sqm" type="number" step="any" class="glass-input w-full bg-black/50 opacity-70 cursor-not-allowed" readonly tabindex="-1">
                        </div>
                    </div>

                    <!-- Payment Type Selection -->
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-2 uppercase">{{ $t('payment_type') }}</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.payment_type" value="full" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                                <span class="text-xs text-white">{{ $t('one_time_full_payment') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.payment_type" value="installment" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                                <span class="text-xs text-white">{{ $t('installment_payment') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div v-if="contractForm.payment_type === 'installment'">
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('down_payment') }}</label>
                            <input v-model="contractForm.down_payment" type="number" step="any" class="glass-input w-full" required>
                        </div>
                        <div v-if="contractForm.payment_type === 'installment'">
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('term_months') }}</label>
                            <input v-model="contractForm.term_months" type="number" class="glass-input w-full" required>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-white/10 mt-4">
                        <h3 class="text-sm font-bold text-neon-cyan uppercase mb-4">Hozirgi Vaqtda To'lov Qabul Qilish (Ixtiyoriy)</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Haqiqatda qilingan to'lov</label>
                                <input v-model="contractForm.actual_paid_amount" type="number" step="0.01" class="glass-input w-full text-neon-yellow" placeholder="Masalan: 600000000">
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">To'lov Usuli</label>
                                <select v-model="contractForm.payment_method" class="glass-input w-full bg-black/50">
                                    <option value="bank">{{ $t('bank_account') }}</option>
                                    <option value="main">{{ $t('cash_account') }}</option>
                                    <option value="credit">💳 Plastik / Terminal</option>
                                </select>
                            </div>
                        </div>
                        <p v-if="contractForm.actual_paid_amount > 0" class="text-[10px] text-text-muted mt-2 font-mono">
                            Moliya bo'limi ushbu to'lovni tasdiqlayotganda ortiqcha summa avtomatik ravishda <strong class="text-neon-yellow">Naqdlashtirish (Qaytim)</strong> navbatiga tushadi.
                        </p>
                    </div>

                    <div class="flex justify-end gap-4 mt-8">
                        <button type="button" @click="showCreateContractModal = false" class="px-4 py-2 text-xs font-mono uppercase tracking-widest text-text-muted hover:text-white transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-neon-green text-black font-bold uppercase tracking-widest rounded hover:shadow-[0_0_15px_#39FF14] transition-all disabled:opacity-50">{{ isEditingContract ? 'Saqlash' : $t('create_pipeline') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- CREATE LEAD MODAL -->
        <div v-if="showCreateLeadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md transition-all">
            <div class="glass-panel w-full max-w-md rounded-2xl border border-neon-cyan/30 shadow-[0_0_50px_rgba(0,240,255,0.1)] overflow-hidden">
                <div class="p-6 border-b border-white/10 flex justify-between items-center bg-black/40">
                    <h2 class="text-xl font-display font-bold text-white uppercase tracking-widest text-shadow-glow">{{ isEditingLead ? 'Lidni Tahrirlash' : 'Yangi Lid Qo\'shish' }}</h2>
                    <button @click="showCreateLeadModal = false" class="text-text-muted hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6">
                    <form @submit.prevent="submitNewLead" class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase tracking-widest mb-1.5 ml-1">{{ $t('first_last_name') }}</label>
                            <input v-model="newLeadForm.full_name" required type="text" class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Masalan: Azizbek">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted uppercase tracking-widest mb-1.5 ml-1">{{ $t('phone_number') }}</label>
                            <input v-model="newLeadForm.phone" required type="tel" class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="+998 90 123 45 67">
                        </div>
                        <button type="submit" :disabled="isProcessing" class="w-full mt-4 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan py-3 rounded-xl font-display font-bold tracking-widest uppercase hover:bg-neon-cyan hover:text-black transition-all disabled:opacity-50">
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

