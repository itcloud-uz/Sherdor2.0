<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import QuickSmsModal from '@/Components/QuickSmsModal.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import VueApexCharts from 'vue3-apexcharts';
import { Head, router, usePage, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { regionsData } from '@/locations.js';

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
    posts: Array,
    crmSettings: Object,
    users: Array,
    smsTemplates: Array,
    smsRules: Array,
    smsCampaigns: Array,
    smsSettings: Object,
    smsLogs: Array,
    isEskizConnected: Boolean,
    leadSources: Array,
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
    fetchTelephonyLogs();
    fetchAudios();
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

const availableDistricts = computed(() => {
    let r = clientForm.value.region;
    if (!r) return [];
    r = r.toLowerCase();
    let foundRegion = regionsData[r];
    if (!foundRegion) {
        foundRegion = Object.values(regionsData).find(
            item => item.name.toLowerCase() === r || item.name.toLowerCase().replace(/\s+sh\.?$/, '') === r
        );
    }
    if (!foundRegion) return [];
    return Object.entries(foundRegion.districts).map(([key, value]) => ({
        id: key,
        name: value.name
    }));
});

const availableNeighborhoods = computed(() => {
    let r = clientForm.value.region;
    if (!r) return [];
    r = r.toLowerCase();
    let foundRegion = regionsData[r];
    if (!foundRegion) {
        foundRegion = Object.values(regionsData).find(
            item => item.name.toLowerCase() === r || item.name.toLowerCase().replace(/\s+sh\.?$/, '') === r
        );
    }
    if (!foundRegion) return [];

    let d = clientForm.value.district;
    if (!d) return [];
    d = d.toLowerCase();

    let foundDistrict = foundRegion.districts[d];
    if (!foundDistrict) {
        foundDistrict = Object.values(foundRegion.districts).find(
            item => item.name.toLowerCase() === d || item.name.toLowerCase().replace(/\s+tumani$/, '') === d
        );
    }

    if (!foundDistrict) return [];
    return foundDistrict.neighborhoods || [];
});

function onRegionChange() {
    clientForm.value.district = '';
    clientForm.value.address = '';
}

function onDistrictChange() {
    clientForm.value.address = '';
}
const newLeadForm = ref({ full_name: '', phone: '', source: 'manual', is_manual: true, email: '', social_url: '' });
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
        is_manual: lead.source === 'manual',
        email: lead.email || '',
        social_url: lead.social_url || ''
    };
    showCreateLeadModal.value = true;
}

function openCreateLeadModal() {
    isEditingLead.value = false;
    editingLeadId.value = null;
    newLeadForm.value = { full_name: '', phone: '', source: 'manual', is_manual: true, email: '', social_url: '' };
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
                newLeadForm.value = { full_name: '', phone: '', source: 'manual', is_manual: true, email: '', social_url: '' };
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

const selectedFacilityName = ref('');
const selectedBuildingId = ref('');

const uniqueFacilities = computed(() => {
    const names = (props.units || [])
        .map(u => u.floor?.building?.name)
        .filter(Boolean);
    return [...new Set(names)];
});

const buildingsForSelectedFacility = computed(() => {
    if (!selectedFacilityName.value) return [];
    const buildingsMap = {};
    (props.units || []).forEach(u => {
        const b = u.floor?.building;
        if (b && b.name === selectedFacilityName.value) {
            buildingsMap[b.id] = b;
        }
    });
    return Object.values(buildingsMap);
});

const unitsForSelectedBuilding = computed(() => {
    if (!selectedBuildingId.value) return [];
    return (props.units || []).filter(u => u.floor?.building?.id === Number(selectedBuildingId.value));
});

const sortedUnitsForSelectedBuilding = computed(() => {
    return [...unitsForSelectedBuilding.value].sort((a, b) => {
        const floorA = a.floor?.floor_number || 0;
        const floorB = b.floor?.floor_number || 0;
        if (floorA !== floorB) {
            return floorA - floorB;
        }
        const numA = String(a.unit_number).replace(/\D/g, '');
        const numB = String(b.unit_number).replace(/\D/g, '');
        return (Number(numA) || 0) - (Number(numB) || 0);
    });
});

function getUnitLabel(u) {
    const isCommercial = u.type === 'commercial';
    const typeLabel = isCommercial ? "Do'kon" : 'Xonadon';
    const prefix = isCommercial && /^\d+$/.test(String(u.unit_number).trim()) ? 'D-' : '';
    return `${typeLabel} ${prefix}${u.unit_number} (Qavat: ${u.floor?.floor_number ?? ''})`;
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

watch(() => contractForm.value.unit_id, (newUnitId) => {
    if (newUnitId) {
        const u = props.units.find(un => un.id === newUnitId);
        if (u) {
            selectedFacilityName.value = u.floor?.building?.name || '';
            selectedBuildingId.value = u.floor?.building?.id || '';
        }
    } else {
        selectedFacilityName.value = '';
        selectedBuildingId.value = '';
    }
}, { immediate: true });

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

const activeTab = ref('dashboard');
const leadsViewMode = ref('kanban'); // 'kanban' or 'list'

// Dashboard Stats & Charts Computeds
const dashboardStats = computed(() => {
    const leads = props.leads || [];
    const today = new Date().toDateString();
    
    const total = leads.length;
    const todayLeads = leads.filter(l => new Date(l.created_at).toDateString() === today).length;
    const uncontacted = leads.filter(l => l.status === 'new').length;
    const qualified = leads.filter(l => l.status === 'qualified').length;
    
    return { total, todayLeads, uncontacted, qualified };
});

const dashboardActivityChart = computed(() => {
    const leads = props.leads || [];
    const dates = [];
    const data = [];
    
    for (let i = 6; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        const dateStr = d.toDateString();
        const label = d.toLocaleDateString('uz-UZ', { day: '2-digit', month: '2-digit' });
        dates.push(label);
        
        const count = leads.filter(l => new Date(l.created_at).toDateString() === dateStr).length;
        data.push(count);
    }
    
    return {
        series: [{ name: 'Lidlar', data }],
        options: {
            chart: { type: 'area', toolbar: { show: false } },
            stroke: { curve: 'smooth', width: 2 },
            colors: ['#f2a900'],
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 }
            },
            xaxis: { categories: dates },
            dataLabels: { enabled: false },
            tooltip: { theme: 'light' }
        }
    };
});

const dashboardSourceChart = computed(() => {
    const leads = props.leads || [];
    const telegram = leads.filter(l => l.source === 'telegram').length;
    const partner = leads.filter(l => l.source === 'partner').length;
    const manual = leads.filter(l => l.source === 'manual' || !l.source).length;
    
    return {
        series: [telegram, partner, manual],
        options: {
            chart: { type: 'donut' },
            labels: ['Telegram Bot', 'Hamkor Ilova', 'Qo\'lda kiritilgan'],
            colors: ['#3b82f6', '#10b981', '#f2a900'],
            legend: { position: 'bottom' },
            dataLabels: { enabled: true }
        }
    };
});

// Posts Tab states and methods
const showPostModal = ref(false);
const isEditingPost = ref(false);
const postForm = ref({
    id: '',
    title: '',
    content: '',
    status: 'draft',
    image: null,
    image_preview: null,
});

function openCreatePostModal() {
    isEditingPost.value = false;
    postForm.value = {
        id: '',
        title: '',
        content: '',
        status: 'draft',
        image: null,
        image_preview: null,
    };
    showPostModal.value = true;
}

function openEditPostModal(post) {
    isEditingPost.value = true;
    postForm.value = {
        id: post.id,
        title: post.title,
        content: post.content,
        status: post.status,
        image: null,
        image_preview: post.image_path ? '/storage/' + post.image_path : null,
    };
    showPostModal.value = true;
}

function onPostImageChange(e) {
    const file = e.target.files[0];
    if (file) {
        postForm.value.image = file;
        postForm.value.image_preview = URL.createObjectURL(file);
    }
}

function submitPostForm() {
    const data = new FormData();
    if (postForm.value.id) data.append('id', postForm.value.id);
    data.append('title', postForm.value.title);
    data.append('content', postForm.value.content || '');
    data.append('status', postForm.value.status);
    if (postForm.value.image) {
        data.append('image', postForm.value.image);
    }
    
    router.post(route('admin.crm.posts.store'), data, {
        onSuccess: () => {
            showPostModal.value = false;
            router.reload({ preserveScroll: true });
        }
    });
}

function deletePost(post) {
    if (confirm('Rostdan ham ushbu postni o\'chirmoqchimisiz?')) {
        router.delete(route('admin.crm.posts.destroy', post.id), {
            onSuccess: () => {
                router.reload({ preserveScroll: true });
            }
        });
    }
}

// Settings Tab states and methods
const settingsForm = ref({
    telegram_bot_token: '',
    telegram_chat_id: '',
    facebook_page_id: '',
    facebook_access_token: '',
    google_analytics_id: '',
    default_telegram_manager_id: '',
    default_partner_manager_id: '',
    default_manual_manager_id: '',
});

watch(() => props.crmSettings, (newSettings) => {
    if (newSettings) {
        settingsForm.value = {
            telegram_bot_token: newSettings.telegram_bot_token || '',
            telegram_chat_id: newSettings.telegram_chat_id || '',
            facebook_page_id: newSettings.facebook_page_id || '',
            facebook_access_token: newSettings.facebook_access_token || '',
            google_analytics_id: newSettings.google_analytics_id || '',
            default_telegram_manager_id: newSettings.default_telegram_manager_id || '',
            default_partner_manager_id: newSettings.default_partner_manager_id || '',
            default_manual_manager_id: newSettings.default_manual_manager_id || '',
        };
    }
}, { immediate: true });

function saveCrmSettings() {
    router.post(route('admin.crm.settings.save'), settingsForm.value, {
        onSuccess: () => {
            alert('Sozlamalar muvaffaqiyatli saqlandi!');
        }
    });
}

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
                activeTab.value = 'dashboard'; // Default tab is 'dashboard'
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
            url.searchParams.set('tab', newTab);
            window.history.replaceState({ ...window.history.state }, '', url.pathname + url.search);
        }
    } catch (e) {
        console.error('URL replace error in CRM activeTab watcher', e);
    }
});
const searchQuery = ref('');
const selectedBuildingFilter = ref('');
const selectedStatusFilter = ref('');
const selectedManagerFilter = ref('');

const showExcelPreviewModal = ref(false);

function openExcelPreviewModal() {
    showExcelPreviewModal.value = true;
}

function closeExcelPreviewModal() {
    showExcelPreviewModal.value = false;
}

function downloadExcel() {
    window.location.href = route('admin.crm.export.clients.excel');
    closeExcelPreviewModal();
}

function formatDateOnly(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${year}-${month}-${day}`;
}

function getDummyBirthDate(client) {
    const years = [1985, 1988, 1990, 1993, 1995, 1997, 1999, 2000, 2002];
    const months = ['02', '05', '09', '11', '12'];
    const days = ['02', '09', '10', '11', '15', '20', '25', '27'];
    const yIndex = client.id % years.length;
    const mIndex = client.id % months.length;
    const dIndex = client.id % days.length;
    return `${years[yIndex]}-${months[mIndex]}-${days[dIndex]}`;
}

const selectedClientForDetail = ref(null);
const clientDetailLoading = ref(false);
const detailedClientData = ref(null);

function selectClientForDetail(client) {
    selectedClientForDetail.value = client;
    clientDetailLoading.value = true;
    detailedClientData.value = null;
    
    axios.get(route('admin.crm.clients.details', client.id))
        .then(response => {
            if (response.data.status === 'success') {
                detailedClientData.value = response.data.client;
            }
        })
        .catch(err => {
            console.error('Mijoz ma\'lumotlarini yuklashda xatolik:', err);
        })
        .finally(() => {
            clientDetailLoading.value = false;
        });
}

function getUzFormattedDate() {
    const days = ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
    const now = new Date();
    const dayName = days[now.getDay()];
    const dateStr = String(now.getDate()).padStart(2, '0') + '.' + String(now.getMonth() + 1).padStart(2, '0') + '.' + now.getFullYear();
    return `${dayName}, ${dateStr}`;
}

function getUzFormattedDateFromString(dateStr) {
    if (!dateStr) return '—';
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    const day = d.getDate();
    const monthName = months[d.getMonth()];
    const year = d.getFullYear();
    return `${day} ${monthName}, ${year}`;
}

function exportDocx(contract) {
    if (!contract) return;
    window.location.href = route('admin.contracts.export.docx', contract.id);
}

function exportExcel(contract) {
    if (!contract) return;
    window.location.href = route('admin.contracts.export.xlsx', contract.id);
}

function getClientGender(client) {
    if (!client) return '—';
    const ln = (client.last_name || '').toLowerCase();
    const mn = (client.middle_name || '').toLowerCase();
    const fn = (client.first_name || '').toLowerCase();
    if (ln.endsWith('ova') || ln.endsWith('eva') || mn.endsWith('ovna') || mn.endsWith('evna') || mn.endsWith('qizi') || fn.endsWith('a')) {
        return 'Ayol';
    }
    return 'Erkak';
}

function getContractFinancials(contract) {
    if (!contract) return { total: 0, paid: 0, debt: 0, percent: 0 };
    const total = Number(contract.total_amount || 0);
    const schedules = contract.payment_schedules || contract.paymentSchedules || [];
    const paid = schedules.reduce((sum, s) => sum + Number(s.paid_amount || 0), 0);
    const debt = Math.max(0, total - paid);
    const percent = total > 0 ? Math.round((paid / total) * 100) : 0;
    return { total, paid, debt, percent };
}

function getContractSchedules(contract) {
    if (!contract) return [];
    return contract.payment_schedules || contract.paymentSchedules || [];
}

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
    
    // Apply tab filter
    if (activeTab.value === 'clients_new') {
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
        result = result.filter(c => !c.is_blacklisted && (new Date(c.created_at) >= thirtyDaysAgo || !c.contracts || c.contracts.length === 0));
    } else if (activeTab.value === 'clients_active') {
        result = result.filter(c => !c.is_blacklisted && c.contracts?.some(contract => contract.status === 'active' || contract.status === 'approved'));
    } else if (activeTab.value === 'clients_debtors') {
        result = result.filter(c => !c.is_blacklisted && c.contracts?.some(contract => 
            contract.payment_schedules?.some(s => s.status === 'overdue') || 
            contract.paymentSchedules?.some(s => s.status === 'overdue')
        ));
    } else if (activeTab.value === 'clients_blacklist') {
        result = result.filter(c => c.is_blacklisted);
    } else {
        result = result.filter(c => !c.is_blacklisted);
    }
    
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

const showLeadDetailModal = ref(false);
const selectedLeadForDetail = ref(null);

const showCallPanelModal = ref(false);
const activeCallPanelTab = ref('history');
const clearedLeads = ref([]);

const houseForm = ref({
    source: '',
    budget: '',
    currency: 'USD',
    payment_type: 'cash',
    facility_id: '',
    building_id: '',
    podyezd: '',
    floor: '',
    rooms: '',
    area: ''
});

const clientInfoForm = ref({
    full_name: '',
    phone: '',
    email: '',
    region: '',
    district: '',
    street: '',
    apartment: ''
});

const meetingForm = ref({
    type: 'office',
    date: '',
    time: ''
});

const leadCallLogs = computed(() => {
    if (!selectedLeadForDetail.value) return [];
    if (clearedLeads.value.includes(selectedLeadForDetail.value.id)) return [];
    return props.telephonyLogs?.filter(log => log.lead_id === selectedLeadForDetail.value.id || log.phone === selectedLeadForDetail.value.phone) || [];
});

function clearLeadCallLogs() {
    if (!confirm("Haqiqatan ham qo'ng'iroqlar tarixini tozalamoqchimisiz?")) return;
    if (selectedLeadForDetail.value) {
        clearedLeads.value.push(selectedLeadForDetail.value.id);
    }
}

function openCallPanelModal(lead, defaultTab = 'history') {
    selectedLeadForDetail.value = lead;
    
    // Initialize forms
    houseForm.value = {
        source: lead.source || 'manual',
        budget: '',
        currency: 'USD',
        payment_type: 'cash',
        facility_id: '',
        building_id: '',
        podyezd: '',
        floor: '',
        rooms: '',
        area: ''
    };
    
    clientInfoForm.value = {
        full_name: lead.full_name || '',
        phone: lead.phone || '',
        email: lead.email || '',
        region: '',
        district: '',
        street: '',
        apartment: ''
    };
    
    meetingForm.value = {
        type: 'office',
        date: '',
        time: ''
    };
    
    activeCallPanelTab.value = defaultTab; // default to call history as in Image 2
    showLeadDetailModal.value = false;
    showCallPanelModal.value = true;
}

function rejectLead(lead) {
    if (!confirm("Haqiqatan ham ushbu lidni rad etmoqchimisiz?")) return;
    isProcessing.value = true;
    router.post(route('admin.crm.leads.update_status', lead.id), { status: 'lost', reason: 'Rad etildi' }, {
        onSuccess: () => {
            showCallPanelModal.value = false;
            router.reload({ preserveScroll: true });
        },
        onFinish: () => {
            isProcessing.value = false;
        }
    });
}

function saveCallPanelClientInfo() {
    isProcessing.value = true;
    axios.put(route('admin.crm.leads.update', selectedLeadForDetail.value.id), {
        full_name: clientInfoForm.value.full_name,
        phone: clientInfoForm.value.phone,
        email: clientInfoForm.value.email,
        source: selectedLeadForDetail.value.source || 'manual',
        user_id: selectedLeadForDetail.value.user_id || null
    })
    .then(() => {
        alert("Mijoz ma'lumotlari muvaffaqiyatli saqlandi!");
        showCallPanelModal.value = false;
        router.reload({ preserveScroll: true });
    })
    .catch(error => {
        alert("Xatolik yuz berdi: " + (error.response?.data?.message || error.message));
    })
    .finally(() => {
        isProcessing.value = false;
    });
}

function saveCallPanelHouseInfo() {
    isProcessing.value = true;
    axios.put(route('admin.crm.leads.update', selectedLeadForDetail.value.id), {
        full_name: selectedLeadForDetail.value.full_name,
        phone: selectedLeadForDetail.value.phone,
        email: selectedLeadForDetail.value.email,
        source: houseForm.value.source,
        user_id: selectedLeadForDetail.value.user_id || null
    })
    .then(() => {
        alert("Uy sotib olish qiziqishlari muvaffaqiyatli saqlandi!");
        showCallPanelModal.value = false;
        router.reload({ preserveScroll: true });
    })
    .catch(error => {
        alert("Xatolik yuz berdi: " + (error.response?.data?.message || error.message));
    })
    .finally(() => {
        isProcessing.value = false;
    });
}

function saveCallPanelMeeting() {
    alert(`Uchrashuv belgilandi:\nSana: ${meetingForm.value.date}\nVaqt: ${meetingForm.value.time}\nTur: ${meetingForm.value.type}`);
    showCallPanelModal.value = false;
}

function openLeadDetailModal(lead) {
    selectedLeadForDetail.value = lead;
    showLeadDetailModal.value = true;
}

function getLeadInitials(fullName) {
    if (!fullName) return '?';
    const parts = fullName.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0].charAt(0) + (parts[1] ? parts[1].charAt(0) : '')).toUpperCase();
}

const getStatusTimeline = (lead) => {
    if (!lead) return [];
    const timeline = [];
    const managerName = lead.user?.name || 'Sotuv menejeri';
    
    // Always started as Yangi
    timeline.push({
        status: 'new',
        label: 'Yangi',
        date: lead.created_at,
        user: managerName
    });
    
    // If status is not new, or if updated_at is different from created_at
    const createdTime = new Date(lead.created_at).getTime();
    const updatedTime = new Date(lead.updated_at).getTime();
    
    if (lead.status !== 'new' || (updatedTime - createdTime > 60000)) {
        let label = 'Yangi';
        if (lead.status === 'contacted') label = 'Aloqaga chiqildi';
        else if (lead.status === 'qualified') label = 'Sotildi';
        else if (lead.status === 'lost') label = 'Yo\'qotilgan';
        
        timeline.push({
            status: lead.status,
            label: label,
            date: lead.updated_at,
            user: managerName
        });
    }
    
    return timeline;
};

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
    // Initiate background telephony call via Asterisk
    axios.post('/telephony/call', {
        lead_id: lead.id,
        phone_number: lead.phone
    }).catch(error => {
        console.error("Telephony error:", error);
    });
    
    // Open the interaction Call Panel Modal
    openCallPanelModal(lead);
}

function openQuickSmsModal(lead) {
    activeLead.value = lead;
    showQuickSmsModal.value = true;
}

// Settings Sub-tab state
const settingsSubTab = ref('general');

// Telephony state
const telephonyLogs = ref([]);
const isLoadingLogs = ref(false); // Map to template's isLoadingLogs
const isTelephonyLogsOpen = ref(false); // Map to template's isTelephonyLogsOpen
const telephonyLogsPagination = ref({ current_page: 1, last_page: 1 });
const availableAudios = ref([]);
const isUploading = ref(false);
const excelFile = ref(null);
const scheduleTime = ref('09:00');
const scheduleDays = ref([1, 2, 3, 4, 5]);
const autoGroup = ref('debtors');
const isAutoDialing = ref(false);
const selectedAudio = ref('');
const dialerPhone = ref('');
const isCalling = ref(false);

function openTelephonyLogsModal() {
    settingsSubTab.value = 'ip_telephony';
    isTelephonyLogsOpen.value = true;
    fetchTelephonyLogs();
}

function fetchTelephonyLogs(page = 1) {
    isLoadingLogs.value = true;
    axios.get('/telephony/logs')
        .then(response => {
            telephonyLogs.value = response.data;
            telephonyLogsPagination.value = {
                current_page: 1,
                last_page: 1
            };
        })
        .catch(err => {
            console.error('Telephony logs fetch failed:', err);
        })
        .finally(() => {
            isLoadingLogs.value = false;
        });
}

function fetchAudios() {
    axios.get('/telephony/audios')
        .then(response => {
            availableAudios.value = response.data;
        })
        .catch(err => {
            console.error('Audio list fetch failed:', err);
        });
}

function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    if (file.size > 10 * 1024 * 1024) {
        alert("Fayl hajmi 10MB dan oshmasligi kerak!");
        return;
    }

    isUploading.value = true;
    const formData = new FormData();
    formData.append('audio', file);
    formData.append('name', file.name);

    axios.post(route('admin.telephony.audios.upload'), formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    })
    .then(() => {
        alert('Audio muvaffaqiyatli yuklandi!');
        fetchAudios();
    })
    .catch(err => {
        console.error('Audio yuklashda xatolik:', err);
        alert('Audio yuklashda xatolik yuz berdi.');
    })
    .finally(() => {
        isUploading.value = false;
        event.target.value = '';
    });
}

function handleExcelUpload(event) {
    excelFile.value = event.target.files[0];
}

function makeCall() {
    if (!dialerPhone.value) return;
    isCalling.value = true;
    
    axios.post('/telephony/call', { phone_number: dialerPhone.value })
        .then(() => {
            alert("Qo'ng'iroq muvaffaqiyatli amalga oshirildi!");
            fetchTelephonyLogs();
        })
        .catch(err => {
            console.error("Dialer call failed:", err);
            alert("Qo'ng'iroqni amalga oshirishda xatolik.");
        })
        .finally(() => {
            isCalling.value = false;
        });
}

function startAutoDialer() {
    if (!selectedAudio.value || !scheduleTime.value || scheduleDays.value.length === 0) {
        alert("Iltimos, barcha maydonlarni (Audio, Vaqt, Hafta kunlari) to'ldiring.");
        return;
    }
    if (autoGroup.value === 'new_leads' && !excelFile.value) {
        alert("Iltimos, yangi mijozlar uchun Excel faylni yuklang.");
        return;
    }

    isAutoDialing.value = true;
    const formData = new FormData();
    formData.append('target_group', autoGroup.value);
    formData.append('audio_id', selectedAudio.value);
    formData.append('schedule_time', scheduleTime.value);
    scheduleDays.value.forEach(day => {
        formData.append('schedule_days[]', day);
    });
    
    if (autoGroup.value === 'new_leads' && excelFile.value) {
        formData.append('excel_file', excelFile.value);
    }

    axios.post(route('admin.telephony.campaigns.store'), formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    })
    .then(() => {
        alert('Avto-qo\'ng\'iroq kampaniyasi muvaffaqiyatli saqlandi va rejalashtirildi!');
        excelFile.value = null;
    })
    .catch(err => {
        console.error("Campaign store failed:", err);
        alert("Kampaniyalarni saqlashda xatolik.");
    })
    .finally(() => {
        isAutoDialing.value = false;
    });
}

function getAudioUrl(filename) {
    return `/api/telephony/audio-file?file=${encodeURIComponent(filename)}`;
}

// AI Voice Bot (Vapi) settings state
const isAiSettingsOpen = ref(false); // Map to template
const isSavingSettings = ref(false); // Map to template
const aiForm = ref({
    is_active: false,
    openai_api_key: '',
    vapi_api_key: '',
    vapi_assistant_id: '',
    system_prompt: '',
    auto_call: false,
    min_score: 50,
    max_duration: 60
});

function openAiSettings() {
    settingsSubTab.value = 'vapi_ai';
    isAiSettingsOpen.value = true;
    isSavingSettings.value = true;
    
    axios.get('/telephony/ai-config')
        .then(response => {
            if (response.data) {
                aiForm.value = {
                    is_active: response.data.is_active || false,
                    openai_api_key: response.data.openai_api_key || '',
                    vapi_api_key: response.data.vapi_api_key || '',
                    vapi_assistant_id: response.data.vapi_assistant_id || '',
                    system_prompt: response.data.prompt || '',
                    auto_call: response.data.auto_call || false,
                    min_score: response.data.min_score !== undefined ? response.data.min_score : 50,
                    max_duration: response.data.max_duration !== undefined ? response.data.max_duration : 60
                };
            }
        })
        .catch(err => {
            console.error('AI config fetch failed:', err);
        })
        .finally(() => {
            isSavingSettings.value = false;
        });
}

function saveAiSettings() {
    isSavingSettings.value = true;
    const payload = {
        is_active: aiForm.value.is_active,
        prompt: aiForm.value.system_prompt,
        openai_api_key: aiForm.value.openai_api_key,
        vapi_api_key: aiForm.value.vapi_api_key,
        vapi_assistant_id: aiForm.value.vapi_assistant_id,
        auto_call: aiForm.value.auto_call,
        min_score: aiForm.value.min_score,
        max_duration: aiForm.value.max_duration
    };
    axios.post('/telephony/ai-config', payload)
        .then(() => {
            isAiSettingsOpen.value = false;
            alert('AI Agent sozlamalari muvaffaqiyatli saqlandi!');
        })
        .catch(err => {
            console.error('AI config save failed:', err);
            alert('Xatolik yuz berdi: ' + (err.response?.data?.message || err.message));
        })
        .finally(() => {
            isSavingSettings.value = false;
        });
}

// SMS AI (Eskiz.uz) states & methods
const showEskizSettingsModal = ref(false);
const showTestSmsModal = ref(false);
const showTemplateModal = ref(false);
const showRuleModal = ref(false);
const showPromoModal = ref(false);
const ruleType = ref('auto');

const eskizSettingsForm = useForm({
    email: props.smsSettings?.email || '',
    password: ''
});

const testSmsForm = useForm({
    phone: '',
    message: 'Bu Eskiz dan test'
});

const templateForm = useForm({
    id: null,
    name: '',
    content: ''
});

const ruleForm = useForm({
    id: null,
    name: '',
    event_trigger: '',
    template_id: '',
    schedule_time: '',
    schedule_days: ''
});

const promoRuleForm = useForm({
    id: null,
    name: '',
    event_trigger: 'promo',
    template_id: '',
    schedule_time: '18:00',
    schedule_days: '',
    audience_type: 'system_clients',
    manual_phone: '',
    file: null
});

function submitEskizSettings() {
    eskizSettingsForm.post(route('admin.ai_agents.settings'), {
        onSuccess: () => {
            showEskizSettingsModal.value = false;
            alert('Eskiz.uz sozlamalari muvaffaqiyatli saqlandi!');
        }
    });
}

function submitTestSms() {
    testSmsForm.post(route('admin.ai_agents.test_sms'), {
        onSuccess: () => {
            showTestSmsModal.value = false;
            testSmsForm.reset('phone');
            alert('Test SMS muvaffaqiyatli yuborildi!');
        }
    });
}

function openTemplateModal() {
    templateForm.reset();
    templateForm.id = null;
    showTemplateModal.value = true;
}

function editTemplate(t) {
    templateForm.id = t.id;
    templateForm.name = t.name;
    templateForm.content = t.content;
    showTemplateModal.value = true;
}

function deleteTemplate(id) {
    if (confirm("Rostdan ham ushbu shablonni o'chirmoqchimisiz?")) {
        router.delete(route('admin.ai_agents.template.delete', id), {
            onSuccess: () => alert('Shablon o\'chirildi.')
        });
    }
}

function submitTemplate() {
    templateForm.post(route('admin.ai_agents.template.save'), {
        onSuccess: () => {
            showTemplateModal.value = false;
            templateForm.reset();
            alert('Shablon muvaffaqiyatli saqlandi!');
        }
    });
}

function openRuleModal(type) {
    ruleForm.reset();
    ruleForm.id = null;
    ruleType.value = type;
    ruleForm.event_trigger = type;
    if (type === 'auto') {
        ruleForm.schedule_time = 'immediate';
        ruleForm.schedule_days = '';
    } else {
        ruleForm.schedule_time = '10:00';
    }
    showRuleModal.value = true;
}

function editRule(r) {
    if (r.event_trigger === 'promo') {
        promoRuleForm.id = r.id;
        promoRuleForm.name = r.name;
        promoRuleForm.event_trigger = r.event_trigger;
        promoRuleForm.template_id = r.template_id;
        promoRuleForm.schedule_time = r.schedule_time;
        promoRuleForm.schedule_days = r.schedule_days;
        promoRuleForm.audience_type = r.audience_type;
        promoRuleForm.manual_phone = r.manual_phone;
        showPromoModal.value = true;
    } else {
        ruleType.value = r.event_trigger;
        ruleForm.id = r.id;
        ruleForm.name = r.name;
        ruleForm.event_trigger = r.event_trigger;
        ruleForm.template_id = r.template_id;
        ruleForm.schedule_time = r.schedule_time;
        ruleForm.schedule_days = r.schedule_days;
        showRuleModal.value = true;
    }
}

function deleteRule(id) {
    if (confirm("Rostdan ham ushbu qoidani o'chirmoqchimisiz?")) {
        router.delete(route('admin.ai_agents.rule.delete', id), {
            onSuccess: () => alert('Qoida o\'chirildi.')
        });
    }
}

function submitRule() {
    ruleForm.post(route('admin.ai_agents.rule.save'), {
        onSuccess: () => {
            showRuleModal.value = false;
            ruleForm.reset();
            alert('Qoida muvaffaqiyatli saqlandi!');
        }
    });
}

function openPromoModal() {
    promoRuleForm.reset();
    promoRuleForm.id = null;
    showPromoModal.value = true;
}

function submitPromoRule() {
    promoRuleForm.post(route('admin.ai_agents.rule.save'), {
        onSuccess: () => {
            showPromoModal.value = false;
            promoRuleForm.reset();
            alert('Ommaviy rasilka saqlandi!');
        }
    });
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

        <!-- TAB 1: LEADS KANBAN / LIST VIEW -->
        <div v-if="activeTab === 'leads'" class="space-y-4">
            <!-- View Mode Switcher & Actions -->
            <div class="flex items-center justify-between bg-white border border-gray-200 shadow-sm rounded-xl px-4 py-3">
                <!-- Toggle Button Group -->
                <div class="flex items-center gap-1.5 bg-gray-100 p-1 rounded-lg">
                    <button 
                        @click="leadsViewMode = 'kanban'"
                        class="px-3 py-1.5 text-xs font-semibold rounded-md flex items-center gap-2 transition-all"
                        :class="leadsViewMode === 'kanban' ? 'bg-white text-gray-900 shadow-sm border border-gray-200/50' : 'text-gray-500 hover:text-gray-900'"
                    >
                        📋 Kanban doskasi
                    </button>
                    <button 
                        @click="leadsViewMode = 'list'"
                        class="px-3 py-1.5 text-xs font-semibold rounded-md flex items-center gap-2 transition-all"
                        :class="leadsViewMode === 'list' ? 'bg-white text-gray-900 shadow-sm border border-gray-200/50' : 'text-gray-500 hover:text-gray-900'"
                    >
                        📝 Jadval ko'rinishi
                    </button>
                </div>

                <!-- Action buttons -->
                <div class="flex items-center gap-2">
                    <button 
                        @click="openAiSettings"
                        class="px-4 py-1.5 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 text-indigo-600 text-xs font-bold rounded-lg transition-all shadow-sm flex items-center gap-2"
                    >
                        🤖 AI Sozlamalari
                    </button>
                    <button 
                        @click="openTelephonyLogsModal"
                        class="px-4 py-1.5 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 text-emerald-600 text-xs font-bold rounded-lg transition-all shadow-sm flex items-center gap-2"
                    >
                        📞 Qo'ng'iroqlar tarixi
                    </button>
                    <button 
                        @click="openCreateLeadModal"
                        class="px-4 py-1.5 bg-blue-50 border border-blue-200 hover:bg-blue-100 text-blue-600 text-xs font-bold rounded-lg transition-all shadow-sm flex items-center gap-2"
                    >
                        + Yangi Lid qo'shish
                    </button>
                </div>
            </div>

            <!-- VIEW 1: KANBAN -->
            <div v-if="leadsViewMode === 'kanban'" class="flex gap-4 h-full min-h-[600px] overflow-x-auto pb-4">
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
                             @click="openLeadDetailModal(lead)"
                             class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 border border-gray-100 hover:border-[#00F0FF]/50 cursor-pointer cursor-move transition-all hover:-translate-y-1 relative group">
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
                             @click="openLeadDetailModal(lead)"
                             class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 border border-gray-100 hover:border-[#FCEE09]/50 cursor-pointer cursor-move transition-all hover:-translate-y-1 relative group">
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
                             @click="openLeadDetailModal(lead)"
                             class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 border border-gray-100 hover:border-[#39FF14]/50 cursor-pointer cursor-move transition-all hover:-translate-y-1 relative group">
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
                             @click="openLeadDetailModal(lead)"
                             class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 border border-gray-100 hover:border-[#FF003C]/50 cursor-pointer cursor-move transition-all hover:-translate-y-1 relative group">
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

            <!-- VIEW 2: LIST TABLE -->
            <div v-else class="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50/50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-3.5">Ism / Telefon</th>
                                <th class="px-6 py-3.5">Manbaa</th>
                                <th class="px-6 py-3.5">Qiziqish (Xonadon)</th>
                                <th class="px-6 py-3.5">Holat</th>
                                <th class="px-6 py-3.5">Kiritilgan sana</th>
                                <th class="px-6 py-3.5 text-right">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <tr v-for="lead in (leads || [])" :key="lead.id" class="hover:bg-gray-50/30 transition-colors">
                                <td class="px-6 py-4 cursor-pointer hover:bg-gray-50/50" @click="openLeadDetailModal(lead)">
                                    <div class="font-semibold text-gray-900 hover:text-blue-600 transition-colors">{{ lead.full_name || lead.name }}</div>
                                    <div class="text-xs text-gray-500 font-mono">{{ lead.phone }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 rounded-md text-xs font-medium"
                                          :class="{
                                              'bg-blue-50 text-blue-700': lead.source === 'telegram',
                                              'bg-emerald-50 text-emerald-700': lead.source === 'partner',
                                              'bg-gray-100 text-gray-700': lead.source === 'manual' || !lead.source
                                          }">
                                        {{ lead.source === 'telegram' ? 'Telegram Bot' : lead.source === 'partner' ? 'Hamkor Ilova' : 'Qo\'lda' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span v-if="lead.unit" class="font-semibold text-gray-700">
                                        {{ lead.unit.podyezd }}-podyezd, {{ lead.unit.floor }}-qavat, {{ lead.unit.number }}-xonadon
                                    </span>
                                    <span v-else class="text-gray-400 font-mono">-</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider"
                                          :class="{
                                              'bg-[#00F0FF]/10 text-[#008B99]': lead.status === 'new',
                                              'bg-blue-50 text-blue-700': lead.status === 'contacted',
                                              'bg-emerald-50 text-emerald-700': lead.status === 'qualified',
                                              'bg-rose-50 text-rose-700': lead.status === 'lost'
                                          }">
                                        {{ $t('lead_' + lead.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500 font-mono">
                                    {{ new Date(lead.created_at).toLocaleString('uz-UZ', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-1.5">
                                        <button @click="initiateLeadCall(lead)" class="p-1.5 rounded bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border border-emerald-200 transition-colors shadow-sm text-xs" title="Qo'ng'iroq">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                        </button>
                                        <button @click="openQuickSmsModal(lead)" class="p-1.5 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 transition-colors shadow-sm text-xs" title="SMS Yuborish">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                        </button>
                                        <button v-if="isAdmin" @click="openEditLeadModal(lead)" class="p-1.5 rounded bg-amber-50 text-amber-600 hover:bg-amber-100 border border-amber-200 transition-colors shadow-sm text-xs" title="Tahrirlash">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                        </button>
                                        <button v-if="isAdmin" @click="deleteLead(lead.id)" class="p-1.5 rounded bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 transition-colors shadow-sm text-xs" title="O'chirish">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="(leads || []).length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                                    Hech qanday lidlar topilmadi.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: CLIENTS ROSTER -->
        <div v-if="['clients', 'clients_new', 'clients_active', 'clients_debtors', 'clients_blacklist'].includes(activeTab)" class="bg-[#f8fafc] p-6 rounded-2xl min-h-[500px]">
            <div v-if="!selectedClientForDetail">
                <!-- Breadcrumbs -->
                <div class="flex items-center gap-2 text-xs text-gray-500 font-sans mb-6 font-medium">
                <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Mijozlar bazasi
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

                    <!-- Excel Export Preview Trigger -->
                    <button @click="openExcelPreviewModal" class="px-5 py-2.5 bg-white border border-emerald-500 text-emerald-600 text-xs font-bold rounded-full hover:bg-emerald-50 transition-all flex items-center gap-1.5 shadow-sm font-sans">
                        <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                            <path d="M14 2v6h6m-3 4l-4 4m0-4l4 4"/>
                        </svg>
                        Excel
                    </button>
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
                                    <button @click="selectClientForDetail(client)" class="text-blue-500 hover:text-blue-700 transition-colors" title="Mijoz haqida">
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
            
            <!-- If client is selected, show detail view -->
            <div v-else class="space-y-6">
                <!-- Breadcrumbs -->
                <div class="flex items-center gap-2 text-xs text-gray-500 font-sans mb-6 font-medium">
                    <span @click="selectedClientForDetail = null" class="flex items-center gap-1 cursor-pointer hover:underline hover:text-gray-900 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Mijozlar bazasi
                    </span>
                    <span class="text-gray-300 font-bold">&gt;</span>
                    <span class="text-gray-900 font-bold">{{ selectedClientForDetail.last_name }} {{ selectedClientForDetail.first_name }}</span>
                </div>

                <!-- Header Row -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-950 font-sans tracking-tight">{{ selectedClientForDetail.last_name }} {{ selectedClientForDetail.first_name }}</h1>
                        <div class="text-xs text-gray-500 font-mono mt-1">{{ getUzFormattedDate() }}</div>
                    </div>
                    <!-- Go back button -->
                    <button @click="selectedClientForDetail = null" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition-all flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Orqaga
                    </button>
                </div>

                <!-- Skeleton Loader -->
                <div v-if="clientDetailLoading" class="animate-pulse space-y-6">
                    <div class="h-40 bg-gray-200 rounded-3xl"></div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="h-60 bg-gray-200 rounded-3xl col-span-2"></div>
                        <div class="h-60 bg-gray-200 rounded-3xl"></div>
                    </div>
                </div>

                <!-- Detailed Client View Content -->
                <div v-else-if="detailedClientData" class="space-y-6">
                    <!-- Card 1: Uy haqida ma'lumot -->
                    <div v-if="detailedClientData.contracts && detailedClientData.contracts.length > 0 && detailedClientData.contracts[0]?.unit" class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row gap-6">
                        <!-- Left: Layout Image Preview -->
                        <div class="w-full md:w-64 h-48 bg-slate-50 border border-slate-100 rounded-2xl overflow-hidden relative group cursor-pointer flex items-center justify-center shrink-0">
                            <!-- Floorplan Preview -->
                            <div class="absolute inset-0 flex flex-col items-center justify-center p-4 text-center">
                                <svg class="w-12 h-12 text-[#f2a900]/40 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <span class="text-xs font-bold text-gray-800">{{ detailedClientData.contracts[0]?.unit?.rooms || '2' }}-xonali xonadon</span>
                                <span class="text-[10px] text-gray-400 font-mono mt-1">Maydoni: {{ detailedClientData.contracts[0]?.unit?.area_sqm || '72.5' }} kv.m</span>
                            </div>
                            <!-- Hover overlay -->
                            <div class="absolute inset-0 bg-[#0d1117]/40 backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-all flex items-center justify-center">
                                <span class="px-4 py-2 bg-white/95 rounded-xl text-xs font-bold text-gray-900 shadow-lg flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    Ko'rib chiqish
                                </span>
                            </div>
                        </div>

                        <!-- Right: Info details -->
                        <div class="flex-1 flex flex-col justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">Uy haqida ma'lumot</span>
                                <h2 class="text-xl font-bold text-gray-950 font-sans mb-4">
                                    {{ detailedClientData.contracts[0]?.unit?.floor?.building?.name || 'Farg\'ona City' }}
                                </h2>

                                <!-- Columns grid -->
                                <div class="grid grid-cols-4 gap-4 max-w-lg mb-4">
                                    <div>
                                        <p class="text-lg font-bold text-gray-900 leading-none">
                                            {{ detailedClientData.contracts[0]?.unit?.floor?.building?.block_name || detailedClientData.contracts[0]?.unit?.floor?.building?.corpus_name || 'Г Блок' }}
                                        </p>
                                        <p class="text-[10px] text-gray-400 font-semibold mt-1">Bino/Blok</p>
                                    </div>
                                    <div>
                                        <p class="text-lg font-bold text-gray-900 leading-none">
                                            {{ detailedClientData.contracts[0]?.unit?.rooms || '2' }}
                                        </p>
                                        <p class="text-[10px] text-gray-400 font-semibold mt-1">Xona</p>
                                    </div>
                                    <div>
                                        <p class="text-lg font-bold text-gray-900 leading-none">
                                            {{ detailedClientData.contracts[0]?.unit?.podyezd ? detailedClientData.contracts[0].unit.podyezd + '-padezd' : '1-padezd' }}
                                        </p>
                                        <p class="text-[10px] text-gray-400 font-semibold mt-1">Podezd</p>
                                    </div>
                                    <div>
                                        <p class="text-lg font-bold text-gray-900 leading-none">
                                            {{ detailedClientData.contracts[0]?.unit?.floor?.floor_number || '3' }}
                                        </p>
                                        <p class="text-[10px] text-gray-400 font-semibold mt-1">Qavat</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer details of the apartment -->
                            <div class="border-t border-gray-100 pt-3 grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs font-sans text-gray-700">
                                <div>Xonadon raqami: <strong class="text-gray-900 font-mono">{{ detailedClientData.contracts[0]?.unit?.unit_number || '236' }}</strong></div>
                                <div>Tafsilot: <strong class="text-gray-900">—</strong></div>
                                <div>Maydoni: <strong class="text-gray-900 font-mono">{{ detailedClientData.contracts[0]?.unit?.area_sqm || '72.54' }} m²</strong></div>
                            </div>
                        </div>
                    </div>

                    <!-- If no contract/unit exists -->
                    <div v-else class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex items-center justify-between min-h-[120px] relative overflow-hidden">
                        <div class="absolute right-0 top-0 translate-x-4 -translate-y-4 w-32 h-32 bg-slate-50 rounded-full opacity-50 blur-xl"></div>
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center border border-amber-100 shrink-0">
                                <svg class="w-6 h-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <div class="text-left">
                                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider font-sans">Uy haqida ma'lumot topilmadi</h3>
                                <p class="text-xs text-gray-500 font-sans mt-0.5">Ushbu mijoz hali uy sotib olmagan.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Ma'lumotlar & Shartnomalar -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Left: Profile and Personal Details (2 cols) -->
                        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm lg:col-span-2 flex flex-col md:flex-row gap-6">
                            <!-- Profile Card -->
                            <div class="w-full md:w-48 shrink-0 flex flex-col items-center text-center p-4 bg-slate-50 rounded-2xl border border-slate-100 justify-center">
                                <div class="w-16 h-16 rounded-full bg-[#fef9c3] text-[#ca8a04] flex items-center justify-center font-bold text-xl shadow-sm mb-3">
                                    {{ getInitials(detailedClientData.first_name, detailedClientData.last_name) }}
                                </div>
                                <h3 class="text-sm font-bold text-gray-900 leading-tight">
                                    {{ detailedClientData.last_name }} <br/> {{ detailedClientData.first_name }} {{ detailedClientData.middle_name || '' }}
                                </h3>
                                <span class="text-[10px] font-bold text-blue-600 mt-2 bg-blue-50 px-2 py-0.5 rounded-full uppercase">Mijoz</span>
                            </div>

                            <!-- Personal Details -->
                            <div class="flex-1">
                                <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Ma'lumotlar</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-xs font-sans text-gray-700">
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">ID:</span> <strong class="text-gray-900 font-mono">{{ detailedClientData.id }}</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Telefon raqami:</span> <strong class="text-gray-900 font-mono">{{ detailedClientData.phone }}</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Telefon raqami 2:</span> <strong class="text-gray-900 font-mono">—</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Telefon raqami 3:</span> <strong class="text-gray-900 font-mono">—</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Jinsi:</span> <strong class="text-gray-900">{{ getClientGender(detailedClientData) }}</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Tug'ilgan sanasi:</span> <strong class="text-gray-900 font-mono">{{ getDummyBirthDate(detailedClientData) }}</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Region:</span> <strong class="text-gray-900">Toshkent shahri</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Tuman:</span> <strong class="text-gray-900">Mirobod tumani</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Manzil:</span> <strong class="text-gray-900">{{ detailedClientData.address || 'address' }}</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Qo'shilgan sana:</span> <strong class="text-gray-900 font-mono">{{ getUzFormattedDateFromString(detailedClientData.created_at) }}</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Passport raqami:</span> <strong class="text-gray-900 font-mono">{{ detailedClientData.passport_number || 'AA1234567' }}</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Passport rasmlari:</span> <strong class="text-gray-900">Rasmlar yo'q</strong></div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Contract details (1 col) -->
                        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col justify-between">
                            <div>
                                <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Shartnomalar</h4>
                                <div class="space-y-2 text-xs font-sans text-gray-700">
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Tasdiqlangan:</span> <strong class="text-gray-900">{{ detailedClientData.contracts[0]?.transactions[0]?.approver?.name || '—' }}</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Sotuv menejeri:</span> <strong class="text-gray-900">{{ detailedClientData.contracts[0]?.transactions[0]?.creator?.name || '—' }}</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between">
                                        <span class="text-gray-400">Holat:</span> 
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" 
                                              :class="detailedClientData.contracts[0]?.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'">
                                            {{ detailedClientData.contracts[0]?.status === 'active' ? 'Tasdiqlangan' : 'Kutilmoqda' }}
                                        </span>
                                    </div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">To'lov turi:</span> <strong class="text-gray-900">{{ detailedClientData.contracts[0]?.payment_type === 'installment' ? 'Bo\'lib to\'lash' : 'O\'z mablag\'' }}</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Muddati:</span> <strong class="text-gray-900 font-mono">{{ detailedClientData.contracts[0]?.term_months || '—' }} oy</strong></div>
                                    <div class="py-1 border-b border-gray-50 flex justify-between"><span class="text-gray-400">Kadastr:</span> <strong class="text-gray-900">yo'q</strong></div>
                                </div>
                            </div>

                            <!-- Action Buttons below -->
                            <div class="mt-6 pt-4 border-t border-gray-50 flex items-center gap-2">
                                <button @click="exportDocx(detailedClientData.contracts[0])" class="flex-1 flex flex-col items-center justify-center p-2 border border-amber-200 hover:bg-amber-50/50 rounded-2xl transition-colors">
                                    <svg class="w-8 h-8 text-amber-600 mb-1" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                        <path d="M14 2v6h6" opacity="0.3"/>
                                    </svg>
                                    <span class="text-[10px] font-bold text-amber-800">Shartnoma</span>
                                </button>
                                <button @click="exportExcel(detailedClientData.contracts[0])" class="flex-1 flex flex-col items-center justify-center p-2 border border-emerald-200 hover:bg-emerald-50/50 rounded-2xl transition-colors">
                                    <svg class="w-8 h-8 text-emerald-600 mb-1" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                        <path d="M14 2v6h6m-3 4l-4 4m0-4l4 4"/>
                                    </svg>
                                    <span class="text-[10px] font-bold text-emerald-800">Grafik</span>
                                </button>
                                <button @click="selectClientForDetail(detailedClientData)" class="flex-1 flex flex-col items-center justify-center p-2 border border-gray-200 hover:bg-gray-50 rounded-2xl transition-colors">
                                    <svg class="w-8 h-8 text-gray-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 7.89H18" />
                                    </svg>
                                    <span class="text-[10px] font-bold text-gray-600">Yangilash</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: Izohlar (Comments) -->
                    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <button class="px-4 py-2 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Izoh qo'shish
                            </button>
                        </div>
                        <a href="#" class="text-xs font-semibold text-blue-600 hover:underline flex items-center gap-1">
                            Barchasi
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </a>
                    </div>

                    <!-- Card 4: Financial Overview Bar -->
                    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row justify-between items-center gap-6">
                        <div class="flex flex-wrap items-center gap-x-8 gap-y-4 text-xs font-sans">
                            <div>
                                <span class="text-gray-400 block mb-1">Uy narxi</span>
                                <strong class="text-emerald-600 text-base font-bold">{{ Number(getContractFinancials(detailedClientData.contracts[0]).total).toLocaleString('en-US') }} so'm</strong>
                            </div>
                            <div>
                                <span class="text-gray-400 block mb-1">Chegirma</span>
                                <strong class="text-gray-900 text-base font-bold">{{ Number(detailedClientData.contracts[0]?.discount || 0).toLocaleString('en-US') }} so'm</strong>
                            </div>
                            <div>
                                <span class="text-gray-400 block mb-1">To'langan</span>
                                <strong class="text-amber-500 text-base font-bold">{{ Number(getContractFinancials(detailedClientData.contracts[0]).paid).toLocaleString('en-US') }} so'm</strong>
                            </div>
                            <div>
                                <span class="text-gray-400 block mb-1">Qarzdorlik</span>
                                <strong class="text-red-500 text-base font-bold">{{ Number(getContractFinancials(detailedClientData.contracts[0]).debt).toLocaleString('en-US') }} so'm</strong>
                            </div>
                        </div>

                        <!-- Progress bar -->
                        <div class="w-full md:w-64 flex items-center gap-3">
                            <div class="flex-1 bg-gray-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-amber-500 h-full rounded-full transition-all duration-500" :style="`width: ${getContractFinancials(detailedClientData.contracts[0]).percent}%`"></div>
                            </div>
                            <span class="text-xs font-bold text-gray-700 font-mono">{{ getContractFinancials(detailedClientData.contracts[0]).percent }}%</span>
                        </div>
                    </div>

                    <!-- Card 5: To'lov jadvali -->
                    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-base font-bold text-gray-900 font-sans">To'lov jadvali</h3>
                            <div class="flex items-center gap-2">
                                <button class="px-4 py-2 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-sm">
                                    To'lov izohi
                                </button>
                                <button class="px-4 py-2 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-sm">
                                    To'lov tarixi
                                </button>
                            </div>
                        </div>

                        <!-- Responsive Table -->
                        <div class="overflow-x-auto border border-gray-100 rounded-2xl">
                            <table class="w-full border-collapse text-left text-xs whitespace-nowrap">
                                <thead>
                                    <tr class="bg-slate-50 text-gray-500 font-bold border-b border-gray-100">
                                        <th class="p-3 text-center w-12">№</th>
                                        <th class="p-3 text-center">Sana</th>
                                        <th class="p-3 text-right">Oylik to'lov</th>
                                        <th class="p-3 text-right">To'langan summa</th>
                                        <th class="p-3 text-right">Qarzdorlik</th>
                                        <th class="p-3 text-center">Turi</th>
                                        <th class="p-3 text-center">Holat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 font-mono text-xs">
                                    <tr v-for="(schedule, index) in getContractSchedules(detailedClientData.contracts[0])" :key="schedule.id" class="hover:bg-slate-50/50 transition-colors">
                                        <td class="p-3 text-center text-gray-500">{{ index + 1 }}</td>
                                        <td class="p-3 text-center text-gray-700">{{ formatDateOnly(schedule.due_date) }}</td>
                                        <td class="p-3 text-right font-bold text-gray-900">{{ Number(schedule.expected_amount || 0).toLocaleString('en-US') }}</td>
                                        <td class="p-3 text-right font-bold text-emerald-600">{{ Number(schedule.paid_amount || 0).toLocaleString('en-US') }}</td>
                                        <td class="p-3 text-right font-bold text-red-500">{{ Number(schedule.expected_amount - schedule.paid_amount || 0).toLocaleString('en-US') }}</td>
                                        <td class="p-3 text-center font-sans text-gray-700">
                                            {{ schedule.payment_type || (index === 0 ? 'Bosh to\'lov' : 'Oylik to\'lov') }}
                                        </td>
                                        <td class="p-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-sans"
                                                  :class="schedule.status === 'paid' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'">
                                                {{ schedule.status === 'paid' ? 'To\'langan' : 'Qarzdorlik' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="getContractSchedules(detailedClientData.contracts[0]).length === 0">
                                        <td colspan="7" class="py-12 text-center text-gray-400 font-sans">To'lov jadvali mavjud emas.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- If no contract exists -->
                <div v-else class="bg-white rounded-3xl p-12 text-center border border-gray-100 shadow-sm">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-sm text-gray-500 font-medium">Mijozning shartnomalari topilmadi.</p>
                </div>
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

        <!-- TAB: DASHBOARD -->
        <div v-if="activeTab === 'dashboard'" class="space-y-6 bg-gray-50/50 p-6 rounded-2xl border border-gray-200/50 shadow-sm">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Card 1 -->
                <div class="bg-white border border-gray-200/60 shadow-sm hover:shadow-md transition-all rounded-2xl p-5 flex items-center justify-between group">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Jami Lidlar</p>
                        <p class="text-3xl font-display font-bold text-gray-900">{{ dashboardStats.total }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                        📞
                    </div>
                </div>
                <!-- Card 2 -->
                <div class="bg-white border border-gray-200/60 shadow-sm hover:shadow-md transition-all rounded-2xl p-5 flex items-center justify-between group">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Bugungi Lidlar</p>
                        <p class="text-3xl font-display font-bold text-gray-900">{{ dashboardStats.todayLeads }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                        📥
                    </div>
                </div>
                <!-- Card 3 -->
                <div class="bg-white border border-gray-200/60 shadow-sm hover:shadow-md transition-all rounded-2xl p-5 flex items-center justify-between group">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Bog'lanilmagan</p>
                        <p class="text-3xl font-display font-bold text-gray-900">{{ dashboardStats.uncontacted }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                        ⚠️
                    </div>
                </div>
                <!-- Card 4 -->
                <div class="bg-white border border-gray-200/60 shadow-sm hover:shadow-md transition-all rounded-2xl p-5 flex items-center justify-between group">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Saralanganlar (Qualified)</p>
                        <p class="text-3xl font-display font-bold text-gray-900">{{ dashboardStats.qualified }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                        🎯
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Activity Area Chart -->
                <div class="bg-white border border-gray-200/60 shadow-sm rounded-2xl p-5 lg:col-span-2 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                        <span>📈</span> Oxirgi 7 kunlik lidlar dinamikasi
                    </h3>
                    <div class="min-h-[300px]">
                        <VueApexCharts 
                            type="area" 
                            height="300" 
                            :options="dashboardActivityChart.options" 
                            :series="dashboardActivityChart.series" 
                        />
                    </div>
                </div>

                <!-- Source Donut Chart -->
                <div class="bg-white border border-gray-200/60 shadow-sm rounded-2xl p-5 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                        <span>📊</span> Lidlar manbalari
                    </h3>
                    <div class="min-h-[300px] flex items-center justify-center">
                        <VueApexCharts 
                            type="donut" 
                            height="300" 
                            :options="dashboardSourceChart.options" 
                            :series="dashboardSourceChart.series" 
                        />
                    </div>
                </div>
            </div>

            <!-- Recent Leads List -->
            <div class="bg-white border border-gray-200/60 shadow-sm rounded-2xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                        <span>⏳</span> Oxirgi kelgan lidlar faolligi
                    </h3>
                    <button @click="activeTab = 'leads'" class="text-xs text-blue-600 font-bold hover:underline">
                        Barcha lidlar &gt;
                    </button>
                </div>
                <div class="overflow-x-auto rounded-xl border border-gray-100">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-semibold">
                            <tr class="border-b border-gray-100">
                                <th class="p-3">Mijoz ismi</th>
                                <th class="p-3">Telefon</th>
                                <th class="p-3">Manba</th>
                                <th class="p-3">Menejer</th>
                                <th class="p-3">Sana</th>
                                <th class="p-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-gray-700 divide-y divide-gray-100">
                            <tr v-for="lead in (leads || []).slice(0, 5)" :key="lead.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="p-3 font-semibold text-gray-900">{{ lead.full_name }}</td>
                                <td class="p-3 font-mono text-xs">{{ lead.phone }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold" 
                                          :class="lead.source === 'telegram' ? 'bg-blue-50 text-blue-600' : lead.source === 'partner' ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-50 text-gray-600'">
                                        {{ lead.source === 'telegram' ? 'Telegram Bot' : lead.source === 'partner' ? 'Hamkor Ilova' : 'Qo\'lda kiritilgan' }}
                                    </span>
                                </td>
                                <td class="p-3 text-xs text-gray-500">{{ lead.user?.name || 'Biriktirilmagan' }}</td>
                                <td class="p-3 font-mono text-xs text-gray-500">{{ new Date(lead.created_at).toLocaleString() }}</td>
                                <td class="p-3 text-right">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                          :class="lead.status === 'new' ? 'bg-blue-100 text-blue-800' : lead.status === 'contacted' ? 'bg-amber-100 text-amber-800' : lead.status === 'qualified' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'">
                                        {{ lead.status }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="(leads || []).length === 0">
                                <td colspan="6" class="p-6 text-center text-gray-400">Hech qanday lidlar topilmadi.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB: POSTS -->
        <div v-if="activeTab === 'posts'" class="space-y-6 bg-gray-50/50 p-6 rounded-2xl border border-gray-200/50 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <span>📢</span> Telegram & Ommaviy Postlar
                </h2>
                <button @click="openCreatePostModal" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-2">
                    + Yangi Post yaratish
                </button>
            </div>

            <!-- Posts Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="post in posts" :key="post.id" class="bg-white border border-gray-200 shadow-sm hover:shadow-md transition-all rounded-2xl overflow-hidden flex flex-col">
                    <!-- Post Image -->
                    <div v-if="post.image_path" class="h-48 bg-gray-100 overflow-hidden relative">
                        <img :src="'/storage/' + post.image_path" class="w-full h-full object-cover" />
                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider shadow"
                              :class="post.status === 'published' ? 'bg-emerald-500 text-white' : 'bg-gray-500 text-white'">
                            {{ post.status === 'published' ? 'Faol' : 'Qoralama' }}
                        </span>
                    </div>
                    <div v-else class="h-48 bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center text-4xl relative border-b border-gray-100">
                        <span>📝</span>
                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider shadow"
                              :class="post.status === 'published' ? 'bg-emerald-500 text-white' : 'bg-gray-500 text-white'">
                            {{ post.status === 'published' ? 'Faol' : 'Qoralama' }}
                        </span>
                    </div>

                    <!-- Post Body -->
                    <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                        <div class="space-y-2">
                            <h3 class="text-base font-bold text-gray-900 line-clamp-1">{{ post.title }}</h3>
                            <p class="text-xs text-gray-500 line-clamp-3 leading-relaxed">{{ post.content || 'Tafsilotlar kiritilmagan.' }}</p>
                        </div>

                        <div class="pt-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                            <span>Sana: {{ new Date(post.created_at).toLocaleDateString() }}</span>
                            <div class="flex items-center gap-2">
                                <button @click="openEditPostModal(post)" class="p-1.5 hover:bg-gray-100 rounded text-blue-600" title="Tahrirlash">✏️</button>
                                <button @click="deletePost(post)" class="p-1.5 hover:bg-red-50 rounded text-red-600" title="O'chirish">❌</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="(posts || []).length === 0" class="col-span-full py-16 text-center bg-white border border-gray-100 rounded-2xl">
                    <span class="text-4xl block mb-2">📭</span>
                    <p class="text-sm text-gray-500">Hozircha hech qanday postlar yaratilmagan.</p>
                </div>
            </div>

            <!-- Post Edit/Create Modal -->
            <div v-if="showPostModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500/50">
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl w-full max-w-lg rounded-2xl overflow-hidden flex flex-col shadow-2xl">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                        <h3 class="text-sm font-bold text-gray-900">{{ isEditingPost ? 'Postni tahrirlash' : 'Yangi Post yaratish' }}</h3>
                        <button @click="showPostModal = false" class="text-gray-400 hover:text-gray-600">❌</button>
                    </div>
                    <form @submit.prevent="submitPostForm" class="p-6 space-y-4">
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600">Sarlavha (Title)</label>
                            <input v-model="postForm.title" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500" required />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600">Post matni (Content)</label>
                            <textarea v-model="postForm.content" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500"></textarea>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600">Post holati</label>
                            <select v-model="postForm.status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500">
                                <option value="draft">Draft (Qoralama)</option>
                                <option value="published">Published (E'lon qilish)</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-semibold text-gray-600">Rasm biriktirish</label>
                            <input type="file" @change="onPostImageChange" class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                            <img v-if="postForm.image_preview" :src="postForm.image_preview" class="h-32 object-cover rounded-lg border border-gray-200 mt-2" />
                        </div>
                        <div class="pt-4 flex justify-end gap-3 border-t border-gray-100">
                            <button type="button" @click="showPostModal = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-50">Bekor qilish</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-lg hover:bg-blue-700 shadow">Saqlash</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB: SETTINGS -->
        <div v-if="activeTab === 'settings'" class="space-y-6 bg-gray-50/50 p-6 rounded-2xl border border-gray-200/50 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2 mb-4">
                <span>⚙️</span> CRM Tizim sozlamalari
            </h2>

            <form @submit.prevent="saveCrmSettings" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Telegram Bot settings -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-5 space-y-4">
                    <h3 class="text-sm font-bold text-gray-800 flex items-center gap-1.5 border-b border-gray-100 pb-3">
                        <span>🤖</span> Telegram Bot Sozlamalari
                    </h3>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-600">Telegram Bot Token</label>
                        <input v-model="settingsForm.telegram_bot_token" type="text" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono" placeholder="123456789:ABCdefGhI..." />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-600">Telegram Chat ID (Guruh/Menejer)</label>
                        <input v-model="settingsForm.telegram_chat_id" type="text" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono" placeholder="-100123456789" />
                    </div>
                </div>

                <!-- Facebook API settings -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-5 space-y-4">
                    <h3 class="text-sm font-bold text-gray-800 flex items-center gap-1.5 border-b border-gray-100 pb-3">
                        <span>👥</span> Facebook & Google Analitika
                    </h3>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-600">Facebook Page ID</label>
                        <input v-model="settingsForm.facebook_page_id" type="text" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono" placeholder="1029384756" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-600">Facebook Access Token</label>
                        <input v-model="settingsForm.facebook_access_token" type="text" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono" placeholder="EAAAP..." />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-600">Google Analytics (Measurement ID)</label>
                        <input v-model="settingsForm.google_analytics_id" type="text" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono" placeholder="G-ABCDEF123" />
                    </div>
                </div>

                <!-- SMS Sozlamalari (Eskiz.uz) -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-5 space-y-4">
                    <h3 class="text-sm font-bold text-gray-800 flex items-center justify-between border-b border-gray-100 pb-3">
                        <span class="flex items-center gap-1.5">
                            <span>💬</span> SMS Sozlamalari (Eskiz.uz)
                        </span>
                        <span v-if="isEskizConnected" class="flex items-center gap-1.5 text-xs text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100 font-mono">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                            Ulangan
                        </span>
                        <span v-else class="flex items-center gap-1.5 text-xs text-red-500 bg-red-50 px-2 py-0.5 rounded border border-red-100 font-mono">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                            Ulanmagan
                        </span>
                    </h3>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-600">Eskiz Email</label>
                        <input v-model="eskizSettingsForm.email" type="email" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono" placeholder="email@example.com" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-gray-600">Eskiz Parol</label>
                        <input v-model="eskizSettingsForm.password" type="password" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono" placeholder="••••••••" />
                        <p class="text-[10px] text-gray-500 mt-1">* Parolni faqat o'zgartirmoqchi bo'lsangiz kiriting.</p>
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="button" @click="submitEskizSettings" :disabled="eskizSettingsForm.processing" class="px-4 py-2 bg-blue-50 border border-blue-200 text-blue-600 text-xs font-bold rounded-lg hover:bg-blue-100 transition-all shadow-sm">
                            {{ eskizSettingsForm.processing ? 'Ulanmoqda...' : 'Eskizga Ulanish' }}
                        </button>
                    </div>
                </div>

                <!-- Default manager assignment settings -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-5 md:col-span-2 space-y-4">
                    <h3 class="text-sm font-bold text-gray-800 flex items-center gap-1.5 border-b border-gray-100 pb-3">
                        <span>👨‍💼</span> Manbalar uchun Standart Biriktiriladigan Menejerlar
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600">Telegram Bot Menejeri</label>
                            <select v-model="settingsForm.default_telegram_manager_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                                <option value="">Biriktirilmagan</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600">Hamkor Ilova Menejeri</label>
                            <select v-model="settingsForm.default_partner_manager_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                                <option value="">Biriktirilmagan</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600">Qo'lda kiritilgan Lidlar Menejeri</label>
                            <select v-model="settingsForm.default_manual_manager_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                                <option value="">Biriktirilmagan</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Form actions -->
                <div class="md:col-span-2 flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm">
                        Sozlamalarni saqlash
                    </button>
                </div>
            </form>
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

                    <!-- Row 2: Shartnoma id, Shartnoma sanasi, Telefon raqami, Telefon raqami 2 -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5">
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
                                <select v-model="clientForm.region" @change="onRegionChange" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer pr-10">
                                    <option value="">Tanlang</option>
                                    <option v-for="(regionObj, regionKey) in regionsData" :key="regionKey" :value="regionObj.name">
                                        {{ regionObj.name }}
                                    </option>
                                </select>
                                <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Tuman / Shahar</label>
                            <div class="relative">
                                <select v-model="clientForm.district" @change="onDistrictChange" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all appearance-none cursor-pointer pr-10">
                                    <option value="">Tanlang</option>
                                    <option v-for="districtObj in availableDistricts" :key="districtObj.id" :value="districtObj.name">
                                        {{ districtObj.name }}
                                    </option>
                                </select>
                                <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Manzil</label>
                            <input v-model="clientForm.address" type="text" list="crmNeighborhoods" placeholder="Manzilni kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                            <datalist id="crmNeighborhoods">
                                <option v-for="neighborhood in availableNeighborhoods" :key="neighborhood" :value="neighborhood"></option>
                            </datalist>
                        </div>
                    </div>

                    <!-- Row 5: Passport rasmlari, Biz haqimizda qayerdan bildingiz?, Shartnoma tili -->
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
                                    <option v-for="source in leadSources" :key="source.id" :value="source.name.toLowerCase()">{{ source.name }}</option>
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
                    <!-- Cascading Selection: Object -> Block -> Unit -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Object Select -->
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Obyektni Tanlang</label>
                            <select 
                                v-model="selectedFacilityName"
                                @change="selectedBuildingId = ''; contractForm.unit_id = ''"
                                class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-sm py-1.5"
                                required
                            >
                                <option value="">Obyektni tanlang...</option>
                                <option v-for="name in uniqueFacilities" :key="name" :value="name">
                                    {{ name }}
                                </option>
                            </select>
                        </div>

                        <!-- Block Select -->
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Blok Tanlash</label>
                            <select 
                                v-model="selectedBuildingId"
                                @change="contractForm.unit_id = ''"
                                :disabled="!selectedFacilityName"
                                class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-sm py-1.5 disabled:opacity-50"
                                required
                            >
                                <option value="">Blokni tanlang...</option>
                                <option v-for="b in buildingsForSelectedFacility" :key="b.id" :value="b.id">
                                    {{ b.block_name || b.corpus_name || 'Asosiy Blok' }}
                                </option>
                            </select>
                        </div>

                        <!-- Unit Select -->
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Xonadon yoki do'kon tanlang</label>
                            <select 
                                v-model="contractForm.unit_id"
                                :disabled="!selectedBuildingId"
                                class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-sm py-1.5 disabled:opacity-50"
                                required
                            >
                                <option value="">Xonadon/Do'kon tanlang...</option>
                                <option v-for="u in sortedUnitsForSelectedBuilding" :key="u.id" :value="u.id">
                                    {{ getUnitLabel(u) }}
                                </option>
                            </select>
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

        <!-- LEAD DETAIL MODAL -->
        <div v-if="showLeadDetailModal && selectedLeadForDetail" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm transition-all duration-300">
            <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 max-w-4xl w-full overflow-hidden transform transition-all duration-300 scale-100 flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-[#f8fafc]">
                    <span class="text-base font-bold text-gray-900 uppercase tracking-wider">Mijoz Batafsil Ma'lumotlari</span>
                    <button @click="showLeadDetailModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Content container -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6 md:grid md:grid-cols-3 md:gap-6 md:space-y-0 text-left">
                    <!-- Left Section: Details & House Info (2 cols) -->
                    <div class="md:col-span-2 space-y-6">
                        <!-- Lead Card Header (Avatar, Name, Phone, Source, Status) -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 bg-gray-50 rounded-2xl border border-gray-100/50">
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 rounded-full bg-[#fef9c3] text-[#ca8a04] flex items-center justify-center font-bold text-lg shadow-sm shrink-0 border border-amber-200">
                                    {{ getLeadInitials(selectedLeadForDetail.full_name) }}
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 leading-snug">
                                        {{ selectedLeadForDetail.full_name }}
                                    </h3>
                                    <div class="text-sm text-gray-500 font-mono mt-0.5">{{ selectedLeadForDetail.phone }}</div>
                                    <div class="text-xs text-gray-400 mt-1">
                                        Manba: <span class="font-semibold text-gray-600">{{ selectedLeadForDetail.source === 'telegram' ? 'Telegram' : selectedLeadForDetail.source === 'partner' ? 'Hamkor Ilova' : 'Qo\'lda kiritilgan' }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Status & Last Activity -->
                            <div class="sm:text-right flex flex-row sm:flex-col justify-between items-center sm:items-end gap-2 flex-shrink-0">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider"
                                      :class="{
                                          'bg-[#00F0FF]/10 text-[#008B99]': selectedLeadForDetail.status === 'new',
                                          'bg-blue-50 text-blue-700': selectedLeadForDetail.status === 'contacted',
                                          'bg-emerald-50 text-emerald-700': selectedLeadForDetail.status === 'qualified',
                                          'bg-rose-50 text-rose-700': selectedLeadForDetail.status === 'lost'
                                      }">
                                    {{ selectedLeadForDetail.status === 'new' ? 'Yangi' : selectedLeadForDetail.status === 'contacted' ? 'Aloqaga chiqildi' : selectedLeadForDetail.status === 'qualified' ? 'Sotildi' : 'Yo\'qotilgan' }}
                                </span>
                                <div class="text-[10px] text-gray-400 font-mono">
                                    So'nggi faollik: {{ formatDate(selectedLeadForDetail.updated_at) }}
                                </div>
                            </div>
                        </div>

                        <!-- Mijoz Ma'lumotlari -->
                        <div>
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Mijoz Ma'lumotlari</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 border border-gray-100 rounded-2xl bg-white shadow-sm">
                                <div>
                                    <p class="text-[10px] font-mono text-gray-400 uppercase">Mas'ul shaxs:</p>
                                    <p class="text-xs text-gray-800 font-bold mt-0.5">
                                        {{ selectedLeadForDetail.user?.name || 'Biriktirilmagan' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-mono text-gray-400 uppercase">Yaratilgan vaqti:</p>
                                    <p class="text-xs text-gray-800 mt-0.5 font-semibold">
                                        {{ formatDate(selectedLeadForDetail.created_at) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-mono text-gray-400 uppercase">Manzil:</p>
                                    <p class="text-xs text-gray-800 mt-0.5 font-medium">
                                        —
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-mono text-gray-400 uppercase">Email:</p>
                                    <p class="text-xs text-gray-800 mt-0.5 font-mono">
                                        {{ selectedLeadForDetail.email || '—' }}
                                    </p>
                                </div>
                                <div class="sm:col-span-2">
                                    <p class="text-[10px] font-mono text-gray-400 uppercase">Ijtimoiy Tarmoq Havolasi:</p>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                        </svg>
                                        <a v-if="selectedLeadForDetail.social_url" :href="selectedLeadForDetail.social_url" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 hover:underline font-mono break-all">
                                            {{ selectedLeadForDetail.social_url }}
                                        </a>
                                        <span v-else class="text-xs text-gray-500">—</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Uy Haqida Ma'lumot -->
                        <div>
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Uy Haqida Ma'lumot</h4>
                            <div class="grid grid-cols-7 gap-1 text-center p-4 border border-gray-100 rounded-2xl bg-white shadow-sm overflow-x-auto min-w-[320px]">
                                <div>
                                    <p class="text-xs font-semibold text-gray-800">-</p>
                                    <p class="text-[9px] text-gray-400 font-mono mt-1">Bino</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-gray-800">-</p>
                                    <p class="text-[9px] text-gray-400 font-mono mt-1">Blok</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-gray-800">-</p>
                                    <p class="text-[9px] text-gray-400 font-mono mt-1">Bo'lim</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-gray-800">-</p>
                                    <p class="text-[9px] text-gray-400 font-mono mt-1">Qavat</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-gray-800">-</p>
                                    <p class="text-[9px] text-gray-400 font-mono mt-1">Xonadon</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-gray-800">-</p>
                                    <p class="text-[9px] text-gray-400 font-mono mt-1">Xonalar</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-gray-800">-</p>
                                    <p class="text-[9px] text-gray-400 font-mono mt-1">Maydoni</p>
                                </div>
                            </div>
                        </div>

                        <!-- Uchrashuvlar -->
                        <div>
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Uchrashuvlar</h4>
                            <div class="p-6 border border-dashed border-gray-200 rounded-2xl bg-gray-50/50 text-center">
                                <p class="text-xs text-gray-500 font-medium">Uchrashuv belgilanmagan!</p>
                            </div>
                        </div>

                        <!-- Bottom Action buttons -->
                        <div class="flex flex-wrap gap-3 pt-2">
                            <button @click="openCallPanelModal(selectedLeadForDetail)" 
                                    class="flex-1 min-w-[140px] px-4 py-2.5 bg-emerald-50 border border-emerald-300 text-emerald-600 font-semibold text-xs rounded-xl hover:bg-emerald-100 transition-all flex items-center justify-center gap-1.5 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                Qo'ng'iroq qilish
                            </button>
                            <button @click="showLeadDetailModal = false; openQuickSmsModal(selectedLeadForDetail);"
                                    class="flex-1 min-w-[140px] px-4 py-2.5 bg-blue-50 border border-blue-300 text-blue-600 font-semibold text-xs rounded-xl hover:bg-blue-100 transition-all flex items-center justify-center gap-1.5 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                Xabar yuborish
                            </button>
                            <button @click="openCallPanelModal(selectedLeadForDetail, 'meeting')"
                                    class="flex-1 min-w-[140px] px-4 py-2.5 bg-amber-50 border border-amber-300 text-amber-600 font-semibold text-xs rounded-xl hover:bg-amber-100 transition-all flex items-center justify-center gap-1.5 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Meeting belgilash
                            </button>
                        </div>
                    </div>

                    <!-- Right Section: Status History Timeline (1 col) -->
                    <div class="border-t border-gray-100 pt-6 md:border-t-0 md:pt-0 md:border-l md:pl-6">
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Status Tarixi</h4>
                        <div class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-gray-200">
                            <!-- Timeline Items -->
                            <div v-for="(item, idx) in getStatusTimeline(selectedLeadForDetail)" :key="idx" class="relative">
                                <!-- Icon / Circle indicator -->
                                <div class="absolute -left-[21px] top-1 w-4 h-4 rounded-full border-2 bg-white flex items-center justify-center shadow-sm"
                                     :class="{
                                         'border-[#008B99]': item.status === 'new',
                                         'border-blue-500': item.status === 'contacted',
                                         'border-emerald-500': item.status === 'qualified',
                                         'border-rose-500': item.status === 'lost'
                                     }">
                                    <svg v-if="idx < getStatusTimeline(selectedLeadForDetail).length - 1 || item.status === 'qualified'" 
                                         class="w-2.5 h-2.5" 
                                         :class="{
                                             'text-[#008B99]': item.status === 'new',
                                             'text-blue-500': item.status === 'contacted',
                                             'text-emerald-500': item.status === 'qualified',
                                             'text-rose-500': item.status === 'lost'
                                         }"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    <div v-else class="w-1.5 h-1.5 rounded-full"
                                         :class="{
                                             'bg-[#008B99]': item.status === 'new',
                                             'bg-blue-500': item.status === 'contacted',
                                             'bg-emerald-500': item.status === 'qualified',
                                             'bg-rose-500': item.status === 'lost'
                                         }">
                                    </div>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-bold text-gray-800">{{ item.label }}</span>
                                        <span class="text-[10px] text-gray-400 font-mono">{{ formatDate(item.date) }}</span>
                                    </div>
                                    <p class="text-[10px] text-gray-500 mt-0.5 font-sans">{{ item.user }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LEAD INTERACTION CALL PANEL MODAL -->
        <div v-if="showCallPanelModal && selectedLeadForDetail" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm transition-all duration-300">
            <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 max-w-5xl w-full overflow-hidden transform transition-all duration-300 scale-100 flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-[#f8fafc]">
                    <span class="text-base font-bold text-gray-900 uppercase tracking-wider font-sans">Lid bilan ishlash paneli</span>
                    <button @click="showCallPanelModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto p-6 flex flex-col lg:flex-row gap-6 min-h-[500px]">
                    <!-- Left Menu: 4 block tabs (Image 2) -->
                    <div class="w-full lg:w-1/3 flex flex-col gap-4 text-left">
                        <!-- Tab 1: Uy sotib olish -->
                        <div @click="activeCallPanelTab = 'house'" 
                             class="p-4 rounded-2xl border-2 cursor-pointer transition-all flex items-center gap-4 hover:bg-gray-50/50"
                             :class="activeCallPanelTab === 'house' ? 'border-[#f2a900] bg-amber-50/30 shadow-sm' : 'border-gray-100 bg-white'">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-600 shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-900 font-sans">Uy sotib olish</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Uy haqida ma'lumotni to'ldiring</p>
                            </div>
                        </div>

                        <!-- Tab 2: Mijoz malumotlari -->
                        <div @click="activeCallPanelTab = 'client'" 
                             class="p-4 rounded-2xl border-2 cursor-pointer transition-all flex items-center gap-4 hover:bg-gray-50/50"
                             :class="activeCallPanelTab === 'client' ? 'border-[#f2a900] bg-amber-50/30 shadow-sm' : 'border-gray-100 bg-white'">
                            <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-600 shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-900 font-sans">Mijoz malumotlari</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Mijoz ma'lumotini to'ldirish</p>
                            </div>
                        </div>

                        <!-- Tab 3: Uchrashuv belgilash -->
                        <div @click="activeCallPanelTab = 'meeting'" 
                             class="p-4 rounded-2xl border-2 cursor-pointer transition-all flex items-center gap-4 hover:bg-gray-50/50"
                             :class="activeCallPanelTab === 'meeting' ? 'border-[#f2a900] bg-amber-50/30 shadow-sm' : 'border-gray-100 bg-white'">
                            <div class="w-10 h-10 rounded-xl bg-[#ca8a04]/10 flex items-center justify-center text-[#ca8a04] shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-900 font-sans">Uchrashuv belgilash</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Mijoz bilan uchrashuv belgilash</p>
                            </div>
                        </div>

                        <!-- Tab 4: Qong'iroqlar tarixi -->
                        <div @click="activeCallPanelTab = 'history'" 
                             class="p-4 rounded-2xl border-2 cursor-pointer transition-all flex items-center gap-4 hover:bg-gray-50/50"
                             :class="activeCallPanelTab === 'history' ? 'border-[#f2a900] bg-amber-50/30 shadow-sm' : 'border-gray-100 bg-white'">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-600 shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-gray-900 font-sans">Qong'iroqlar tarixi</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Qong'iroqlar tarixini ko'zdan kechirish</p>
                            </div>
                        </div>
                    </div>

                    <!-- Right Content Area: Forms based on active tab -->
                    <div class="flex-1 bg-white border border-gray-100 rounded-3xl p-6 shadow-sm min-h-[400px]">
                        <!-- Tab Content: Qong'iroqlar tarixi (Image 2) -->
                        <div v-if="activeCallPanelTab === 'history'" class="h-full flex flex-col text-left">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest font-mono">Qong'iroqlar tarixi</h3>
                                <button @click="clearLeadCallLogs" class="text-xs font-bold text-[#f2a900] hover:text-[#d99700] hover:underline font-sans">
                                    Tozalash
                                </button>
                            </div>
                            <div class="flex-1 flex flex-col items-center justify-center py-16 text-center" v-if="leadCallLogs.length === 0">
                                <span class="text-3xl mb-3">📞</span>
                                <p class="text-xs text-gray-400 font-medium font-sans">Qong'iroqlar tarixi mavjud emas</p>
                            </div>
                            <div class="flex-1 overflow-y-auto space-y-3" v-else>
                                <div v-for="log in leadCallLogs" :key="log.id" class="p-4 border border-gray-100 rounded-2xl bg-gray-50/50 flex items-center justify-between text-left">
                                    <div>
                                        <p class="text-xs font-bold text-gray-900 font-mono">{{ log.phone }}</p>
                                        <p class="text-[10px] text-gray-400 mt-1 font-mono">{{ formatDate(log.created_at) }}</p>
                                    </div>
                                    <span class="text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide"
                                          :class="log.status === 'completed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'">
                                        {{ log.status }} ({{ log.duration || 0 }}s)
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Content: Mijoz malumotlari (Image 3) -->
                        <div v-if="activeCallPanelTab === 'client'" class="space-y-6 text-left">
                            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-widest border-b border-gray-100 pb-2 font-mono">MA'LUMOTINI QO'SHISH</h3>
                            <form @submit.prevent="saveCallPanelClientInfo" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Ism</label>
                                        <input v-model="clientInfoForm.full_name" required type="text" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Ismni kiriting">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Telefon raqami</label>
                                        <input v-model="clientInfoForm.phone" required type="text" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="+998">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5">Elektron pochta</label>
                                        <input v-model="clientInfoForm.email" type="email" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="example@mail.com">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Region</label>
                                        <select v-model="clientInfoForm.region" required class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all">
                                            <option value="">Viloyatni tanlang</option>
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
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Tuman</label>
                                        <input v-model="clientInfoForm.district" required type="text" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Tuman nomini kiriting">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5">Ko'cha</label>
                                        <input v-model="clientInfoForm.street" type="text" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Ko'cha nomini kiriting">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5">Xonadon</label>
                                        <input v-model="clientInfoForm.apartment" type="text" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Xonadon raqamini kiriting">
                                    </div>
                                </div>

                                <div class="flex justify-end gap-3 pt-6 border-t border-gray-100 mt-6">
                                    <button type="button" @click="rejectLead(selectedLeadForDetail)" class="px-5 py-2.5 border border-[#f2a900] text-[#f2a900] text-xs font-bold rounded-xl hover:bg-amber-50/50 transition-all font-sans">
                                        Lidni rad etish
                                    </button>
                                    <button type="submit" :disabled="isProcessing" class="px-6 py-2.5 bg-[#f2a900] text-white text-xs font-bold rounded-xl hover:bg-[#d99700] transition-all font-sans shadow-md">
                                        Saqlash
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Tab Content: Uy sotib olish (Image 4) -->
                        <div v-if="activeCallPanelTab === 'house'" class="space-y-6 text-left">
                            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-widest border-b border-gray-100 pb-2 font-mono">MA'LUMOTINI QO'SHISH</h3>
                            <form @submit.prevent="saveCallPanelHouseInfo" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Manba</label>
                                        <select v-model="houseForm.source" required class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all">
                                            <option v-for="source in leadSources" :key="source.id" :value="source.name.toLowerCase()">{{ source.name }}</option>
                                            <option value="manual">Qo'lda kiritilgan</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> COMMON.BUDGET</label>
                                        <div class="flex">
                                            <input v-model="houseForm.budget" required type="text" class="flex-1 border border-gray-200 rounded-l-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Budjet summasi">
                                            <select v-model="houseForm.currency" class="border-t border-r border-b border-gray-200 rounded-r-xl px-4 py-3 bg-gray-50 text-gray-700 font-mono text-sm">
                                                <option value="USD">USD</option>
                                                <option value="UZS">UZS</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> To'lov turi</label>
                                        <select v-model="houseForm.payment_type" required class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all">
                                            <option value="cash">Naqd pul</option>
                                            <option value="installment">Muddatli to'lov</option>
                                            <option value="bank">Bank orqali</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Obyekt nomi</label>
                                        <select v-model="houseForm.facility_id" required class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all">
                                            <option value="">Obyektni tanlang</option>
                                            <option value="sherdor_1">Sherdor 1</option>
                                            <option value="sherdor_2">Sherdor 2.0</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Bino nomi</label>
                                        <input v-model="houseForm.building_id" required type="text" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Bino blokini kiriting">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Podezd</label>
                                        <input v-model="houseForm.podyezd" required type="text" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Masalan: 1">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Qavat</label>
                                        <input v-model="houseForm.floor" required type="number" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Masalan: 3">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Nechchi xonali</label>
                                        <input v-model="houseForm.rooms" required type="number" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Masalan: 2">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Maydoni</label>
                                        <input v-model="houseForm.area" required type="text" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Maydon kv.m">
                                    </div>
                                </div>

                                <div class="flex justify-end gap-3 pt-6 border-t border-gray-100 mt-6">
                                    <button type="button" @click="rejectLead(selectedLeadForDetail)" class="px-5 py-2.5 border border-[#f2a900] text-[#f2a900] text-xs font-bold rounded-xl hover:bg-amber-50/50 transition-all font-sans">
                                        Lidni rad etish
                                    </button>
                                    <button type="submit" :disabled="isProcessing" class="px-6 py-2.5 bg-[#f2a900] text-white text-xs font-bold rounded-xl hover:bg-[#d99700] transition-all font-sans shadow-md">
                                        Saqlash
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Tab Content: Uchrashuv belgilash (Image 5) -->
                        <div v-if="activeCallPanelTab === 'meeting'" class="space-y-6 text-left">
                            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-widest border-b border-gray-100 pb-2 font-mono">UCHRASHUV BELGILASH</h3>
                            <form @submit.prevent="saveCallPanelMeeting" class="space-y-4">
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Uchrashuv turi</label>
                                        <select v-model="meetingForm.type" required class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all">
                                            <option value="office">Ofisda uchrashuv</option>
                                            <option value="site">Obyektda uchrashuv</option>
                                            <option value="phone">Telefon orqali suhbat</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Sana</label>
                                        <input v-model="meetingForm.date" required type="date" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-mono text-gray-500 uppercase mb-1.5"><span class="text-red-500 font-bold">*</span> Vaqti</label>
                                        <input v-model="meetingForm.time" required type="time" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all">
                                    </div>
                                </div>

                                <div class="flex justify-end gap-3 pt-6 border-t border-gray-100 mt-6">
                                    <button type="button" @click="rejectLead(selectedLeadForDetail)" class="px-5 py-2.5 border border-[#f2a900] text-[#f2a900] text-xs font-bold rounded-xl hover:bg-amber-50/50 transition-all font-sans">
                                        Lidni rad etish
                                    </button>
                                    <button type="submit" :disabled="isProcessing" class="px-6 py-2.5 bg-[#f2a900] text-white text-xs font-bold rounded-xl hover:bg-[#d99700] transition-all font-sans shadow-md">
                                        Saqlash
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
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
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1.5 ml-1">E-pochta (Ixtiyoriy)</label>
                            <input v-model="newLeadForm.email" type="email" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Masalan: example@mail.com">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Ijtimoiy tarmoq havolasi (Ixtiyoriy)</label>
                            <input v-model="newLeadForm.social_url" type="text" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all" placeholder="Masalan: https://t.me/username">
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Manba</label>
                            <select v-model="newLeadForm.source" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan transition-all">
                                <option v-for="source in leadSources" :key="source.id" :value="source.name.toLowerCase()">{{ source.name }}</option>
                                <option value="manual">Qo'lda kiritilgan</option>
                            </select>
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

        <!-- EXCEL PREVIEW MODAL -->
        <div v-if="showExcelPreviewModal" class="fixed inset-0 bg-[#0d1117]/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="closeExcelPreviewModal">
            <div class="bg-white rounded-3xl w-full max-w-6xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden border border-gray-100">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-[#f8fafc]">
                    <div class="flex items-center gap-3 text-[#107c41]">
                        <!-- Excel logo SVG -->
                        <svg class="w-6 h-6 text-[#107c41]" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                            <path d="M14 2v6h6m-3 4l-4 4m0-4l4 4"/>
                        </svg>
                        <span class="text-base font-bold font-sans">Mijozlar ro'yxati</span>
                    </div>
                    <button @click="closeExcelPreviewModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Google Sheets Toolbar Mock -->
                <div class="px-6 py-2 border-b border-gray-100 bg-[#f8fafc] flex flex-wrap items-center gap-2 text-gray-500 overflow-x-auto select-none">
                    <button class="p-1 hover:bg-gray-100 rounded text-gray-400" disabled><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg></button> <!-- Undo -->
                    <button class="p-1 hover:bg-gray-100 rounded text-gray-400" disabled><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a8 8 0 00-8 8v2m18-10l-6 6m6-6l-6-6" /></svg></button> <!-- Redo -->
                    <button class="p-1 hover:bg-gray-100 rounded text-gray-400" disabled><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg></button> <!-- Print -->
                    <span class="w-px h-5 bg-gray-200 mx-1"></span>
                    <button class="px-2 py-0.5 hover:bg-gray-100 rounded text-xs font-semibold" disabled>100%</button>
                    <span class="w-px h-5 bg-gray-200 mx-1"></span>
                    <button class="px-2 py-0.5 hover:bg-gray-100 rounded text-xs font-semibold" disabled>Normal</button>
                    <button class="px-2 py-0.5 hover:bg-gray-100 rounded text-xs font-semibold" disabled>Arial</button>
                    <button class="px-2 py-0.5 hover:bg-gray-100 rounded text-xs font-semibold" disabled>10</button>
                    <span class="w-px h-5 bg-gray-200 mx-1"></span>
                    <button class="px-2 py-0.5 hover:bg-gray-100 rounded font-bold text-xs" disabled>B</button>
                    <button class="px-2 py-0.5 hover:bg-gray-100 rounded italic text-xs" disabled>I</button>
                    <button class="px-2 py-0.5 hover:bg-gray-100 rounded underline text-xs" disabled>U</button>
                    <span class="w-px h-5 bg-gray-200 mx-1"></span>
                    <button class="px-2 py-0.5 hover:bg-gray-100 rounded text-xs" disabled>A</button>
                    <button class="px-2 py-0.5 hover:bg-gray-100 rounded text-xs" disabled>田</button>
                    <button class="px-2 py-0.5 hover:bg-gray-100 rounded text-xs" disabled>≡</button>
                </div>

                <!-- Grid view -->
                <div class="flex-1 overflow-auto bg-gray-100 p-6 custom-scrollbar max-h-[55vh]">
                    <div class="inline-block min-w-full align-middle border border-gray-300 rounded-xl overflow-hidden bg-white">
                        <table class="w-full border-collapse text-left text-xs font-sans whitespace-nowrap">
                            <thead>
                                <!-- Column letter indicators -->
                                <tr class="bg-gray-50 text-gray-400 font-medium text-center border-b border-gray-200">
                                    <th class="border-r border-gray-200 w-10 py-1 bg-[#f1f3f4]"></th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">A</th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">B</th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">C</th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">D</th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">E</th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">F</th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">G</th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">H</th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">I</th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">J</th>
                                    <th class="px-3 py-1 bg-[#f1f3f4] text-[10px]">K</th>
                                </tr>
                                <!-- Column header names (Row 1) -->
                                <tr class="border-b border-gray-300 text-center font-bold text-gray-700 bg-gray-50/50">
                                    <td class="border-r border-gray-200 py-1.5 bg-[#f1f3f4] text-gray-400 font-medium text-center">1</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 text-left font-bold text-gray-900">Ф.И.О.</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Тугилган сана</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Шартнома берилgan sana</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Объект</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Блок</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Подъезд</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Этаж</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Хонадон рақами</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Нархи</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Телефон</td>
                                    <td class="px-3 py-1.5 font-bold text-gray-900">ЖШШИР</td>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 font-mono text-[11px]">
                                <tr v-for="(client, index) in filteredClients" :key="client.id" class="hover:bg-gray-50/50 border-b border-gray-200 text-gray-800">
                                    <!-- Row number indicator -->
                                    <td class="border-r border-gray-200 py-1 text-center bg-[#f1f3f4] text-gray-400 font-medium w-10 select-none">
                                        {{ index + 2 }}
                                    </td>
                                    <!-- F.I.O. -->
                                    <td class="border-r border-gray-200 px-3 py-1 font-sans text-left font-medium text-gray-900">
                                        {{ client.last_name }} {{ client.first_name }} {{ client.middle_name || '' }}
                                    </td>
                                    <!-- Tug'ilgan sana -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-center text-gray-600">
                                        {{ getDummyBirthDate(client) }}
                                    </td>
                                    <!-- Shartnoma berilgan sana -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-center text-gray-600">
                                        {{ getClientLatestContract(client) ? formatDateOnly(getClientLatestContract(client).created_at) : '—' }}
                                    </td>
                                    <!-- Obyekt -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-center font-sans font-medium text-gray-700">
                                        {{ getClientLatestContract(client)?.unit?.floor?.building?.name || '—' }}
                                    </td>
                                    <!-- Blok -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-center font-sans text-gray-700">
                                        {{ getClientLatestContract(client)?.unit?.floor?.building?.block_name || getClientLatestContract(client)?.unit?.floor?.building?.corpus_name || '—' }}
                                    </td>
                                    <!-- Pod'ezd -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-center font-sans text-gray-700">
                                        {{ getClientLatestContract(client)?.unit?.podyezd ? getClientLatestContract(client).unit.podyezd + '-padezd' : '—' }}
                                    </td>
                                    <!-- Etaj -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-center font-semibold text-gray-800">
                                        {{ getClientLatestContract(client)?.unit?.floor?.floor_number || '—' }}
                                    </td>
                                    <!-- Xonadon raqami -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-center font-semibold text-gray-800">
                                        {{ getClientLatestContract(client)?.unit?.unit_number || '—' }}
                                    </td>
                                    <!-- Narxi -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-right font-bold text-gray-900">
                                        {{ getClientLatestContract(client)?.total_amount ? Number(getClientLatestContract(client).total_amount).toLocaleString('en-US') : '—' }}
                                    </td>
                                    <!-- Telefon -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-center text-gray-900">
                                        {{ client.phone ? client.phone.replace('+998', '') : '—' }}
                                    </td>
                                    <!-- PINFL (JSHSHIR) -->
                                    <td class="px-3 py-1 text-center text-gray-700">
                                        {{ client.pinfl || '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end items-center bg-[#f8fafc]">
                    <button @click="downloadExcel" class="px-6 py-2.5 bg-[#f2a900] hover:bg-[#d99700] text-white font-bold rounded-xl text-sm transition-all shadow-md flex items-center gap-2">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Yuklab olish
                    </button>
                </div>
            </div>
        </div>

        <!-- 🤖 AI Settings Modal -->
        <div v-if="isAiSettingsOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm transition-all duration-300">
            <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 max-w-2xl w-full overflow-hidden transform transition-all duration-300 scale-100 flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-indigo-600 to-violet-600 text-white flex justify-between items-center">
                    <h3 class="text-lg font-bold flex items-center gap-2">
                        <span>🤖</span> AI Ovozli Bot Sozlamalari (Vapi)
                    </h3>
                    <button @click="isAiSettingsOpen = false" class="text-white/80 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Content -->
                <div class="p-6 overflow-y-auto space-y-5 flex-1">
                    <div class="p-4 bg-blue-50/50 border border-blue-100 rounded-xl text-xs text-blue-800 leading-relaxed">
                        💡 Ushbu sozlamalar mijozlarga avtomatik tarzda yoki qo'lda AI ovozli bot orqali qo'ng'iroq qilishni ta'minlaydi. 
                        <strong>Vapi.ai</strong> provayderi va <strong>OpenAI GPT-4o</strong> orqali ishlaydi.
                    </div>

                    <!-- API Keys Group -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">OpenAI API Key</label>
                            <input 
                                type="password" 
                                v-model="aiForm.openai_api_key" 
                                placeholder="sk-proj-..."
                                class="w-full px-4 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all font-mono"
                            />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Vapi API Public Key</label>
                                <input 
                                    type="password" 
                                    v-model="aiForm.vapi_api_key" 
                                    placeholder="vapi-public-key"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all font-mono"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Vapi Assistant ID</label>
                                <input 
                                    type="text" 
                                    v-model="aiForm.vapi_assistant_id" 
                                    placeholder="uuid-format-id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all font-mono"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">AI Tizim Ko'rsatmasi (System Prompt)</label>
                            <textarea 
                                v-model="aiForm.system_prompt" 
                                rows="5"
                                placeholder="Mijozlar bilan qanday gaplashish bo'yicha yo'riqnoma..."
                                class="w-full px-4 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all leading-relaxed"
                            ></textarea>
                            <span class="text-[10px] text-gray-400">Tizim ko'rsatmasida kompaniya nomi (Sherdor 2.0), xonadon narxi va shartlari haqida ma'lumot kiriting.</span>
                        </div>

                        <!-- Toggles -->
                        <div class="flex flex-col sm:flex-row gap-4 pt-2">
                            <label class="flex items-center gap-3 p-3 bg-gray-50 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-100/70 transition-all flex-1">
                                <input type="checkbox" v-model="aiForm.is_active" class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300" />
                                <div>
                                    <span class="block text-xs font-bold text-gray-900">Botni Faollashtirish</span>
                                    <span class="block text-[10px] text-gray-500">Tizimda AI bot xizmatidan foydalanish</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 p-3 bg-gray-50 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-100/70 transition-all flex-1">
                                <input type="checkbox" v-model="aiForm.auto_call" class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300" />
                                <div>
                                    <span class="block text-xs font-bold text-gray-900">Avtomatik Qo'ng'iroq</span>
                                    <span class="block text-[10px] text-gray-500">Yangi lidlar uchun darhol qo'ng'iroq qilish</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                    <button 
                        @click="isAiSettingsOpen = false" 
                        class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-100 transition-all"
                    >
                        Bekor qilish
                    </button>
                    <button 
                        @click="saveAiSettings" 
                        :disabled="isSavingSettings"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl transition-all shadow-md flex items-center gap-2 disabled:opacity-50"
                    >
                        <span v-if="isSavingSettings" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        Saqlash
                    </button>
                </div>
            </div>
        </div>

        <!-- 📞 Telephony Logs Modal -->
        <div v-if="isTelephonyLogsOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm transition-all duration-300">
            <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 max-w-5xl w-full overflow-hidden transform transition-all duration-300 scale-100 flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-emerald-600 to-teal-600 text-white flex justify-between items-center">
                    <h3 class="text-lg font-bold flex items-center gap-2">
                        <span>📞</span> AI Bot Qo'ng'iroqlari tarixi
                    </h3>
                    <div class="flex items-center gap-3">
                        <button 
                            @click="fetchTelephonyLogs(1)" 
                            class="px-3 py-1 bg-white/20 hover:bg-white/30 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all"
                        >
                            🔄 Yangilash
                        </button>
                        <button @click="isTelephonyLogsOpen = false" class="text-white/80 hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6 overflow-y-auto flex-1 space-y-4">
                    <!-- Loading state -->
                    <div v-if="isLoadingLogs" class="py-12 flex flex-col items-center justify-center gap-3">
                        <div class="w-10 h-10 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                        <span class="text-sm font-medium text-gray-500">Qo'ng'iroqlar tarixi yuklanmoqda...</span>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="!telephonyLogs.length" class="py-16 text-center">
                        <span class="text-4xl">📭</span>
                        <h4 class="mt-2 text-sm font-bold text-gray-900">Qo'ng'iroqlar mavjud emas</h4>
                        <p class="mt-1 text-xs text-gray-500">Hozircha AI bot orqali amalga oshirilgan qo'ng'iroqlar tarixi topilmadi.</p>
                    </div>

                    <!-- Table -->
                    <div v-else class="overflow-x-auto border border-gray-100 rounded-xl shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-100 text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                                    <th class="px-4 py-3">Lid</th>
                                    <th class="px-4 py-3">Raqam</th>
                                    <th class="px-4 py-3 text-center">Sana / Davomiyligi</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                    <th class="px-4 py-3 text-center">Yozuv</th>
                                    <th class="px-4 py-3">AI Xulosa / Transcript</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-xs text-gray-700">
                                <tr v-for="log in telephonyLogs" :key="log.id" class="hover:bg-gray-50/50 transition-colors">
                                    <!-- Lead -->
                                    <td class="px-4 py-3 font-semibold text-gray-900">
                                        {{ log.lead ? log.lead.full_name : 'Noma\'lum Lid' }}
                                    </td>
                                    <!-- Phone -->
                                    <td class="px-4 py-3 font-mono">
                                        {{ log.phone }}
                                    </td>
                                    <!-- Date & Duration -->
                                    <td class="px-4 py-3 text-center">
                                        <div class="font-medium">{{ formatDate(log.created_at) }}</div>
                                        <div class="text-[10px] text-gray-400 mt-0.5">Davomiyligi: {{ log.duration ? log.duration + ' soniya' : '—' }}</div>
                                    </td>
                                    <!-- Status -->
                                    <td class="px-4 py-3 text-center">
                                        <span 
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide"
                                            :class="{
                                                'bg-emerald-50 text-emerald-700 border border-emerald-200': log.status === 'completed',
                                                'bg-amber-50 text-amber-700 border border-amber-200': log.status === 'failed' || log.status === 'no-answer',
                                                'bg-blue-50 text-blue-700 border border-blue-200': log.status === 'ringing' || log.status === 'in-progress'
                                            }"
                                        >
                                            {{ log.status }}
                                        </span>
                                    </td>
                                    <!-- Recording -->
                                    <td class="px-4 py-3 text-center">
                                        <div v-if="log.recording_url" class="flex justify-center">
                                            <audio :src="log.recording_url" controls class="h-8 max-w-[150px] outline-none"></audio>
                                        </div>
                                        <span v-else class="text-gray-400">Yozuv yo'q</span>
                                    </td>
                                    <!-- Summary & Transcript -->
                                    <td class="px-4 py-3 max-w-xs">
                                        <div class="space-y-1">
                                            <div v-if="log.summary" class="text-[11px] bg-indigo-50/50 p-2 rounded-lg text-indigo-950 border border-indigo-100/30">
                                                <strong>AI Xulosa:</strong> {{ log.summary }}
                                            </div>
                                            <button 
                                                v-if="log.transcript"
                                                @click="log._showTranscript = !log._showTranscript"
                                                class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold underline flex items-center gap-1 mt-1"
                                            >
                                                {{ log._showTranscript ? 'Yashirish' : 'Transcriptni ko\'rish' }}
                                            </button>
                                            <div v-if="log._showTranscript && log.transcript" class="mt-1.5 p-2 bg-gray-50 border border-gray-200 rounded-lg text-[10px] whitespace-pre-wrap max-h-40 overflow-y-auto leading-relaxed text-gray-600">
                                                {{ log.transcript }}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="telephonyLogsPagination.last_page > 1" class="flex justify-between items-center pt-2">
                        <span class="text-xs text-gray-500">
                            Sahifa {{ telephonyLogsPagination.current_page }} / {{ telephonyLogsPagination.last_page }}
                        </span>
                        <div class="flex gap-1">
                            <button 
                                :disabled="telephonyLogsPagination.current_page === 1"
                                @click="fetchTelephonyLogs(telephonyLogsPagination.current_page - 1)"
                                class="px-3 py-1 border border-gray-300 text-xs font-semibold rounded-lg hover:bg-gray-50 disabled:opacity-50"
                            >
                                Oldingi
                            </button>
                            <button 
                                :disabled="telephonyLogsPagination.current_page === telephonyLogsPagination.last_page"
                                @click="fetchTelephonyLogs(telephonyLogsPagination.current_page + 1)"
                                class="px-3 py-1 border border-gray-300 text-xs font-semibold rounded-lg hover:bg-gray-50 disabled:opacity-50"
                            >
                                Keyingi
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

