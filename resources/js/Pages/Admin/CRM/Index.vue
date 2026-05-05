<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    leads: Array,
    clients: Array,
    contracts: Array,
    units: Array,
    overdueSchedules: Array,
});

const isProcessing = ref(false);

const showCreateClientModal = ref(false);
const isEditingClient = ref(false);
const editingClientId = ref(null);
const clientForm = ref({ first_name: '', last_name: '', passport_series: '', pinfl: '', phone: '', address: '', passport_issued_date: '', passport_issued_by: '' });

function openCreateClientModal() {
    isEditingClient.value = false;
    editingClientId.value = null;
    clientForm.value = { first_name: '', last_name: '', passport_series: '', pinfl: '', phone: '', address: '', passport_issued_date: '', passport_issued_by: '' };
    showCreateClientModal.value = true;
}

function openEditClientModal(client) {
    isEditingClient.value = true;
    editingClientId.value = client.id;
    clientForm.value = { 
        first_name: client.first_name, 
        last_name: client.last_name, 
        passport_series: client.passport_number || client.passport_series, 
        pinfl: client.pinfl, 
        phone: client.phone, 
        address: client.address, 
        passport_issued_date: client.passport_issued_date, 
        passport_issued_by: client.passport_issued_by 
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
    if (confirm("Haqiqatan ham ushbu mijozni o'chirmoqchimisiz?")) {
        router.delete(route('admin.crm.clients.delete', id));
    }
}

// Create Contract Form
const showCreateContractModal = ref(false);
const contractForm = ref({ client_id: '', unit_id: '', contract_type: 'residential', price_per_sqm: '', total_amount: '', down_payment: '', term_months: 36, payment_type: 'installment' });

function createContract() {
    isProcessing.value = true;
    router.post(route('admin.contracts.store'), contractForm.value, {
        onSuccess: () => {
            showCreateContractModal.value = false;
            contractForm.value = { client_id: '', unit_id: '', contract_type: 'residential', price_per_sqm: '', total_amount: '', down_payment: '', term_months: 36, payment_type: 'installment' };
            isProcessing.value = false;
        },
        onError: () => isProcessing.value = false
    });
}

const selectedUnit = computed(() => {
    if (!contractForm.value.unit_id) return null;
    return props.units.find(u => u.id === contractForm.value.unit_id);
});

// Update contract amounts when unit changes
import { watch } from 'vue';
watch(() => contractForm.value.unit_id, (newUnitId) => {
    if (newUnitId) {
        const u = props.units.find(unit => unit.id === newUnitId);
        if (u) {
            contractForm.value.price_per_sqm = Number(u.price) || 0;
            const totalPrice = contractForm.value.price_per_sqm * (Number(u.area_sqm) || 0);
            contractForm.value.total_amount = totalPrice;
            if (contractForm.value.payment_type === 'full') {
                contractForm.value.down_payment = totalPrice;
                contractForm.value.term_months = 1;
            } else {
                contractForm.value.down_payment = totalPrice * 0.3; // Default 30%
            }
        }
    }
});
watch(() => contractForm.value.price_per_sqm, (newPrice) => {
    if (contractForm.value.unit_id) {
        const u = props.units.find(unit => unit.id === contractForm.value.unit_id);
        if (u) {
            const totalPrice = (Number(newPrice) || 0) * (Number(u.area_sqm) || 0);
            contractForm.value.total_amount = totalPrice;
            if (contractForm.value.payment_type === 'full') {
                contractForm.value.down_payment = totalPrice;
            } else {
                contractForm.value.down_payment = totalPrice * 0.3;
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

const leadsByStatus = computed(() => {
    return {
        new: props.leads.filter(l => l.status === 'new'),
        contacted: props.leads.filter(l => l.status === 'contacted'),
        qualified: props.leads.filter(l => l.status === 'qualified'),
        lost: props.leads.filter(l => l.status === 'lost')
    };
});

const filteredClients = computed(() => {
    if (!searchQuery.value) return props.clients;
    const lowerQuery = searchQuery.value.toLowerCase();
    return props.clients.filter(c => 
        (c.first_name && c.first_name.toLowerCase().includes(lowerQuery)) ||
        (c.last_name && c.last_name.toLowerCase().includes(lowerQuery)) ||
        (c.pinfl && c.pinfl.includes(lowerQuery))
    );
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

function openScheduleModal(contract) {
    selectedContract.value = contract;
    showModal.value = true;
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

function recalculatePenalty() {
    if (!selectedContract.value) return;
    
    isProcessing.value = true;
    
    axios.post(`/admin/contracts/${selectedContract.value.id}/update-penalty`, {
        daily_penalty_rate: selectedContract.value.daily_penalty_rate
    }).then(response => {
        // Update local state with recalculated contract data
        selectedContract.value = response.data.contract;
        alert(response.data.message);
    }).catch(error => {
        alert(error.response?.data?.message || "Xatolik yuz berdi");
    }).finally(() => {
        isProcessing.value = false;
    });
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
                @click="activeTab = 'overdue'"
                :class="['px-6 py-2 text-sm font-display font-bold uppercase tracking-wider transition-all rounded-t-lg', activeTab === 'overdue' ? 'text-neon-magenta border-b-2 border-neon-magenta bg-white/5' : 'text-text-muted hover:text-white']"
            >
                {{ $t('overdue_radar') }} <span v-if="overdueSchedules.length > 0" class="ml-2 px-2 py-0.5 bg-neon-magenta text-white rounded-full text-xs animate-pulse">{{ overdueSchedules.length }}</span>
            </button>
        </div>

        <!-- TAB 1: LEADS KANBAN -->
        <div v-if="activeTab === 'leads'" class="flex gap-4 h-full min-h-[600px] overflow-x-auto pb-4">
            <!-- New Column -->
            <div class="flex-1 min-w-[250px] glass-panel rounded-2xl p-4 flex flex-col border-t-2 border-t-[#00F0FF]">
                <div class="mb-4 flex items-center justify-between border-b border-white/10 pb-2">
                    <h3 class="text-sm font-bold uppercase text-white tracking-widest">{{ $t('lead_new') }}</h3>
                    <span class="text-xs font-mono bg-white/10 px-2 py-0.5 rounded-full text-neon-cyan">{{ leadsByStatus.new.length }}</span>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3 pr-2">
                    <div v-for="lead in leadsByStatus.new" :key="lead.id" class="glass-card p-4 border border-white/5 hover:border-[#00F0FF]/50 cursor-pointer transition-all hover:-translate-y-1">
                        <h4 class="text-sm font-bold text-white">{{ lead.full_name }}</h4>
                        <p class="text-xs text-text-muted font-mono mt-1">{{ lead.phone }}</p>
                    </div>
                </div>
            </div>

            <!-- Contacted Column -->
            <div class="flex-1 min-w-[250px] glass-panel rounded-2xl p-4 flex flex-col border-t-2 border-t-[#FCEE09]">
                <div class="mb-4 flex items-center justify-between border-b border-white/10 pb-2">
                    <h3 class="text-sm font-bold uppercase text-white tracking-widest">{{ $t('lead_contacted') }}</h3>
                    <span class="text-xs font-mono bg-white/10 px-2 py-0.5 rounded-full text-neon-yellow">{{ leadsByStatus.contacted.length }}</span>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3 pr-2">
                    <div v-for="lead in leadsByStatus.contacted" :key="lead.id" class="glass-card p-4 border border-white/5 hover:border-[#FCEE09]/50 cursor-pointer transition-all hover:-translate-y-1">
                        <h4 class="text-sm font-bold text-white">{{ lead.full_name }}</h4>
                        <p class="text-xs text-text-muted font-mono mt-1">{{ lead.phone }}</p>
                    </div>
                </div>
            </div>

            <!-- Qualified Column -->
            <div class="flex-1 min-w-[250px] glass-panel rounded-2xl p-4 flex flex-col border-t-2 border-t-[#39FF14]">
                <div class="mb-4 flex items-center justify-between border-b border-white/10 pb-2">
                    <h3 class="text-sm font-bold uppercase text-white tracking-widest">{{ $t('lead_qualified') }}</h3>
                    <span class="text-xs font-mono bg-white/10 px-2 py-0.5 rounded-full text-neon-green">{{ leadsByStatus.qualified.length }}</span>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3 pr-2">
                    <div v-for="lead in leadsByStatus.qualified" :key="lead.id" class="glass-card p-4 border border-white/5 hover:border-[#39FF14]/50 cursor-pointer transition-all hover:-translate-y-1">
                        <h4 class="text-sm font-bold text-white">{{ lead.full_name }}</h4>
                        <p class="text-xs text-text-muted font-mono mt-1">{{ lead.phone }}</p>
                    </div>
                </div>
            </div>

            <!-- Lost Column -->
            <div class="flex-1 min-w-[250px] glass-panel rounded-2xl p-4 flex flex-col border-t-2 border-t-[#FF003C]">
                <div class="mb-4 flex items-center justify-between border-b border-white/10 pb-2">
                    <h3 class="text-sm font-bold uppercase text-white tracking-widest">{{ $t('lead_lost') }}</h3>
                    <span class="text-xs font-mono bg-white/10 px-2 py-0.5 rounded-full text-neon-magenta">{{ leadsByStatus.lost.length }}</span>
                </div>
                <div class="flex-1 overflow-y-auto space-y-3 pr-2">
                    <div v-for="lead in leadsByStatus.lost" :key="lead.id" class="glass-card p-4 border border-white/5 hover:border-[#FF003C]/50 cursor-pointer transition-all hover:-translate-y-1">
                        <h4 class="text-sm font-bold text-white">{{ lead.full_name }}</h4>
                        <p class="text-xs text-text-muted font-mono mt-1">{{ lead.phone }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: CLIENTS ROSTER -->
        <div v-if="activeTab === 'clients'" class="glass-panel p-6 rounded-2xl min-h-[500px]">
            <div class="mb-6 flex justify-between items-center">
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

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
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
                                <h3 class="text-lg font-bold text-white truncate" :title="`${client.first_name} ${client.last_name}`">
                                    {{ client.first_name }} {{ client.last_name }}
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
                        <div class="grid grid-cols-2 gap-2 mb-4 p-3 rounded-lg bg-black/30 border border-white/5">
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
                                Faol Xaridlar ({{ client.contracts.length }})
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
                            <p class="text-[10px] font-mono text-text-muted uppercase tracking-widest">Xaridlar mavjud emas</p>
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
            <div class="mb-6 flex justify-end">
                <button @click="showCreateContractModal = true" class="px-4 py-2 bg-neon-green/20 border border-neon-green text-neon-green text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-green hover:text-black transition-all shadow-[0_0_10px_rgba(57,255,20,0.2)]">
                    + {{ $t('add') }} {{ $t('contract') }}
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div 
                    v-for="contract in contracts" 
                    :key="contract.id"
                    @click="openScheduleModal(contract)"
                    class="glass-card p-6 border border-white/10 hover:border-neon-green/50 cursor-pointer transition-all hover:shadow-[0_0_20px_rgba(57,255,20,0.15)] group relative overflow-hidden flex flex-col h-full justify-between"
                >
                    <!-- Glass highlight -->
                    <div class="absolute -inset-x-20 top-0 h-px bg-gradient-to-r from-transparent via-white/30 to-transparent transform -rotate-45 -translate-y-10 group-hover:translate-y-[200px] transition-transform duration-1000 opacity-50"></div>
                    
                    <div>
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-neon-green/20 to-black border border-neon-green/50 flex items-center justify-center text-neon-green font-display font-bold text-sm shadow-[0_0_10px_rgba(57,255,20,0.2)]">
                                    {{ getInitials(contract.client?.first_name, contract.client?.last_name) }}
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-white group-hover:text-neon-green transition-colors">{{ contract.client?.first_name }} {{ contract.client?.last_name }}</h3>
                                    <p class="text-[10px] font-mono text-neon-cyan mt-0.5">{{ $t('unit') }}: {{ contract.unit?.unit_number || 'N/A' }} | {{ contract.unit?.floor?.building?.name || 'Bino' }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-1 rounded bg-white/5 border border-white/10 text-[10px] font-mono text-white/80 whitespace-nowrap">{{ contract.term_months }} {{ $t('months') }}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 mb-4 p-3 rounded-lg bg-black/30 border border-white/5">
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

                <div v-if="contracts.length === 0" class="text-center py-20 text-text-muted font-mono text-sm border border-dashed border-white/10 rounded-2xl">
                    {{ $t('no_active_contracts') }}
                </div>
            </div>
        </div>

        <!-- TAB 4: OVERDUE RADAR -->
        <div v-if="activeTab === 'overdue'" class="glass-panel p-6 rounded-2xl min-h-[500px] border border-neon-magenta/30">
            <h2 class="text-xl font-display font-bold text-neon-magenta mb-6 flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-neon-magenta animate-pulse shadow-[0_0_10px_#FF003C]"></span>
                {{ $t('overdue_payments_radar') }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
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
                                <p class="text-[10px] font-mono uppercase text-neon-magenta tracking-wider">Kechikkan</p>
                                <p class="text-xs font-mono font-bold text-neon-magenta">{{ Math.floor((new Date() - new Date(schedule.due_date)) / (1000 * 60 * 60 * 24)) }} kun</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <button class="w-full py-2.5 bg-neon-magenta/20 border border-neon-magenta text-neon-magenta font-bold font-mono text-xs uppercase tracking-widest rounded hover:bg-neon-magenta hover:text-white transition-all shadow-[0_0_10px_rgba(255,0,60,0.3)] flex justify-center items-center gap-2">
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
                        <p class="text-sm text-text-muted font-mono mt-1">Contract #{{ selectedContract.id.toString().padStart(5, '0') }} • {{ selectedContract.client?.first_name }} {{ selectedContract.client?.last_name }} • Unit {{ selectedContract.unit?.unit_number || 'N/A' }}</p>
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
                        </div>

                        <div class="glass-card p-3 rounded-xl border border-white/10 flex flex-col md:flex-row items-center gap-4 bg-black/30">
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-mono text-text-muted uppercase tracking-widest">{{ $t('daily_penalty_rate') }}</label>
                                <input type="number" step="0.01" v-model="selectedContract.daily_penalty_rate" class="glass-input w-20 text-center text-neon-cyan font-bold p-1">
                                <button @click="recalculatePenalty" :disabled="isProcessing" class="px-3 py-1 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan text-xs font-mono font-bold uppercase rounded hover:bg-neon-cyan hover:text-black transition-all shadow-[0_0_10px_rgba(0,240,255,0.2)] disabled:opacity-50">
                                    🔄 Hisoblash
                                </button>
                            </div>
                            <div class="hidden md:block h-8 border-r border-white/10 mx-2"></div>
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-mono text-text-muted uppercase tracking-widest">{{ $t('total_penalty') }}</label>
                                <span class="text-neon-magenta font-bold font-mono">{{ $currency(selectedContract.total_penalty_accumulated || 0) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- AI VERIFICATION PORTAL -->
                    <div class="glass-card p-4 rounded-xl border border-neon-cyan/20 mb-8 bg-black/40 flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-neon-cyan/20 border border-neon-cyan flex items-center justify-center">
                                <span class="text-neon-cyan text-xl">🤖</span>
                            </div>
                            <div>
                                <h4 class="text-white font-bold text-sm tracking-widest uppercase">AI Signature Verification</h4>
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
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-black/60 border-b border-white/10">
                                    <th class="p-3 text-xs font-mono text-text-muted font-normal uppercase tracking-wider">{{ $t('month_no') }}</th>
                                    <th class="p-3 text-xs font-mono text-text-muted font-normal uppercase tracking-wider">{{ $t('due_date') }}</th>
                                    <th class="p-3 text-xs font-mono text-text-muted font-normal uppercase tracking-wider">{{ $t('expected') }}</th>
                                    <th class="p-3 text-xs font-mono text-text-muted font-normal uppercase tracking-wider">{{ $t('paid') }}</th>
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
                                    <td class="p-3 text-sm font-mono text-neon-cyan">{{ $currency(schedule.expected_amount) }}</td>
                                    <td class="p-3 text-sm font-mono text-neon-green">{{ $currency(schedule.paid_amount || 0) }}</td>
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
                </div>
            </div>
        </div>

        <!-- MODAL: CREATE / EDIT CLIENT -->
        <div v-if="showCreateClientModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showCreateClientModal = false"></div>
            <div class="relative glass-panel p-6 rounded-2xl w-full max-w-lg border border-neon-cyan/30">
                <h2 class="text-xl font-bold text-white mb-6 uppercase tracking-widest text-shadow-glow shadow-neon-cyan/50">
                    {{ isEditingClient ? 'Mijozni tahrirlash' : $t('add') + ' ' + $t('client') }}
                </h2>
                <form @submit.prevent="saveClient" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('first_name') }}</label>
                            <input v-model="clientForm.first_name" type="text" class="glass-input w-full" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('last_name') }}</label>
                            <input v-model="clientForm.last_name" type="text" class="glass-input w-full" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
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
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Manzil</label>
                        <input v-model="clientForm.address" type="text" class="glass-input w-full">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Pasport berilgan sana</label>
                            <input v-model="clientForm.passport_issued_date" type="date" class="glass-input w-full">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Kim tomonidan berilgan</label>
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
                    {{ $t('add') }} {{ $t('contract') }}
                </h2>
                <form @submit.prevent="createContract" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('client') }}</label>
                        <select v-model="contractForm.client_id" class="glass-input w-full bg-black/50" required>
                            <option value="" disabled>{{ $t('select_client') }}</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.first_name }} {{ c.last_name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('select_unit') }}</label>
                        <select v-model="contractForm.unit_id" class="glass-input w-full bg-black/50" required>
                            <option value="" disabled>{{ $t('select_unit') }}</option>
                            <option v-for="u in units" :key="u.id" :value="u.id">{{ $t('unit') }} {{ u.unit_number }} - {{ u.floor?.building?.name }}</option>
                        </select>
                    </div>

                    <!-- Mulk Turi Selection -->
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-2 uppercase">Mulk turi (Shartnoma shabloni)</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.contract_type" value="residential" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                                <span class="text-xs text-white">Uy-joy (Kvartira)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.contract_type" value="commercial" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                                <span class="text-xs text-white">Savdo do'koni</span>
                            </label>
                        </div>
                    </div>

                    <!-- Dynamic Unit Details -->
                    <div v-if="selectedUnit" class="grid grid-cols-2 gap-4 p-3 border border-white/10 rounded-xl bg-black/30">
                        <div>
                            <p class="text-[10px] font-mono text-text-muted uppercase">Obyekt (Blok)</p>
                            <p class="text-xs text-white font-bold">{{ selectedUnit.floor?.building?.name }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-mono text-text-muted uppercase">Qavat</p>
                            <p class="text-xs text-white font-bold">{{ selectedUnit.floor?.number }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-mono text-text-muted uppercase">Xonalar soni</p>
                            <p class="text-xs text-white font-bold">{{ selectedUnit.rooms }} xona</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-mono text-text-muted uppercase">Umumiy maydoni</p>
                            <p class="text-xs text-white font-bold">{{ selectedUnit.area_sqm }} kv.m</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4" v-if="selectedUnit">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Kvadrat metri narxi</label>
                            <input v-model="contractForm.price_per_sqm" type="number" step="any" class="glass-input w-full" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Umumiy narxi (QQS bilan)</label>
                            <input v-model="contractForm.total_amount" type="number" step="any" class="glass-input w-full" required>
                        </div>
                    </div>

                    <!-- Payment Type Selection -->
                    <div>
                        <label class="block text-[10px] font-mono text-text-muted mb-2 uppercase">To'lov turi</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.payment_type" value="full" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                                <span class="text-xs text-white">Bir martalik umumiy to'lov</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="contractForm.payment_type" value="installment" class="text-neon-cyan focus:ring-neon-cyan bg-black/50 border-white/20">
                                <span class="text-xs text-white">Bo'lib to'lash</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div v-if="contractForm.payment_type === 'installment'">
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Boshlang'ich to'lov</label>
                            <input v-model="contractForm.down_payment" type="number" step="any" class="glass-input w-full" required>
                        </div>
                        <div v-if="contractForm.payment_type === 'installment'">
                            <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('term_months') }}</label>
                            <input v-model="contractForm.term_months" type="number" class="glass-input w-full" required>
                        </div>
                    </div>
                    <div class="flex justify-end gap-4 mt-8">
                        <button type="button" @click="showCreateContractModal = false" class="px-4 py-2 text-xs font-mono uppercase tracking-widest text-text-muted hover:text-white transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-6 py-2 bg-neon-green text-black font-bold uppercase tracking-widest rounded hover:shadow-[0_0_15px_#39FF14] transition-all disabled:opacity-50">{{ $t('create_pipeline') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
