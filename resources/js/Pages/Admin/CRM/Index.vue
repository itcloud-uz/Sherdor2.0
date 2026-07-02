<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import QuickSmsModal from '@/Components/QuickSmsModal.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import VueApexCharts from 'vue3-apexcharts';
import { Head, router, usePage, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { regionsData } from '@/locations.js';

const { t, locale } = useI18n();
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
    cashRegisters: Array,
    contractTemplates: Array,
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
const showDetailedLeadModal = ref(false);

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
    region: '',
    district: '',
    passport_photo: null,
    passport_photo_back: null,
    profile_photo: null,
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

const leadAvailableDistricts = computed(() => {
    let r = newLeadForm.value.region;
    if (!r) return [];
    r = r.toLowerCase();
    let foundRegion = regionsData[r];
    if (!foundRegion) {
        foundRegion = Object.values(regionsData).find(
            item => item.name.toLowerCase() === r || item.name.toLowerCase().replace(/\s+sh\.?$/, '') === r
        );
    }
    if (!foundRegion) return [];
    return Object.entries(foundRegion.districts).map(([key, value]) => value.name);
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

const showPassportLightbox = ref(false);
const lightboxImageUrl = ref('');
const lightboxTitle = ref("Passport Hujjati Ko'rinishi");

function openPassportPreview(url) {
    lightboxTitle.value = "Passport Hujjati Ko'rinishi";
    lightboxImageUrl.value = url;
    showPassportLightbox.value = true;
}

function previewUnitBlueprint(unit) {
    if (!unit) return;
    const blueprint = unit.blueprint_path || unit.floor?.blueprint_path || unit.floor?.building?.blueprint_path || unit.model_3d_path;
    const url = blueprint ? '/storage/' + blueprint : '/modern_apartment_render.png';
    lightboxTitle.value = `${unit.type === 'commercial' ? "Do'kon" : "Xonadon"} № ${unit.unit_number || ''} Chizmasi`;
    lightboxImageUrl.value = url;
    showPassportLightbox.value = true;
}

function getContractRepairType(contract) {
    if (!contract || !contract.notes) return "Tamirsiz";
    if (contract.notes.includes("Tamir turi: Tamirli")) return "Tamirli";
    if (contract.notes.includes("Tamir turi: Tamirsiz")) return "Tamirsiz";
    return "Tamirsiz";
}

// Comments & Payment Details modals
const showCommentsModal = ref(false);
const newCommentText = ref('');
const isSavingComment = ref(false);

function openCommentsModal() {
    showCommentsModal.value = true;
}

function submitClientComment() {
    if (!newCommentText.value.trim()) return;
    isSavingComment.value = true;
    axios.post(route('admin.crm.clients.comments.store', detailedClientData.value.id), { preserveScroll: true, preserveState: true,
        comment: newCommentText.value
    })
    .then(response => {
        if (response.data.status === 'success') {
            if (!detailedClientData.value.comments) {
                detailedClientData.value.comments = [];
            }
            detailedClientData.value.comments.unshift(response.data.comment);
            newCommentText.value = '';
        }
    })
    .catch(err => {
        console.error('Izoh qo\'shishda xatolik:', err);
        alert('Izoh qo\'shishda xatolik yuz berdi');
    })
    .finally(() => {
        isSavingComment.value = false;
    });
}

const showPaymentNotesModal = ref(false);
const editingScheduleNote = ref(null);
const newScheduleNoteText = ref('');
const isSavingScheduleNote = ref(false);

function openPaymentNotesModal() {
    showPaymentNotesModal.value = true;
}

function editScheduleNote(schedule) {
    editingScheduleNote.value = schedule;
    newScheduleNoteText.value = schedule.notes || '';
}

function saveScheduleNote() {
    if (!editingScheduleNote.value) return;
    isSavingScheduleNote.value = true;
    axios.put(route('admin.crm.schedules.notes.update', editingScheduleNote.value.id), { preserveScroll: true, preserveState: true,
        notes: newScheduleNoteText.value
    })
    .then(response => {
        if (response.data.status === 'success') {
            editingScheduleNote.value.notes = response.data.schedule.notes;
            
            // Sync with detailedClientData payment schedules list
            if (detailedClientData.value && detailedClientData.value.contracts && detailedClientData.value.contracts[0]) {
                const found = detailedClientData.value.contracts[0].payment_schedules.find(s => s.id === editingScheduleNote.value.id);
                if (found) {
                    found.notes = response.data.schedule.notes;
                }
            }
            editingScheduleNote.value = null;
            newScheduleNoteText.value = '';
        }
    })
    .catch(err => {
        console.error('To\'lov izohini saqlashda xatolik:', err);
        alert('To\'lov izohini saqlashda xatolik yuz berdi');
    })
    .finally(() => {
        isSavingScheduleNote.value = false;
    });
}

const showPaymentHistoryModal = ref(false);

function openPaymentHistoryModal() {
    showPaymentHistoryModal.value = true;
}

const showExportFormatModal = ref(false);
const exportContractId = ref(null);

function openExportFormatModal(contractId) {
    exportContractId.value = contractId;
    showExportFormatModal.value = true;
}

function closeExportFormatModal() {
    showExportFormatModal.value = false;
    exportContractId.value = null;
}

function handleExport(format) {
    if (!exportContractId.value) return;
    const url = `/admin/crm/ledger/${exportContractId.value}/export/${format}?lang=${locale.value || 'uz'}`;
    window.open(url, '_blank');
    closeExportFormatModal();
}

const loadedScripts = {};
function loadScript(src) {
    if (loadedScripts[src]) return loadedScripts[src];
    loadedScripts[src] = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => resolve();
        script.onerror = (err) => reject(err);
        document.head.appendChild(script);
    });
    return loadedScripts[src];
}

async function initFaceDetector() {
    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/tracking.js/1.1.3/tracking-min.js');
    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/tracking.js/1.1.3/data/face-min.js');
}

async function detectAndCropFace(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = async (e) => {
            const img = new Image();
            img.src = e.target.result;
            img.onload = async () => {
                try {
                    await initFaceDetector();
                    
                    const tracker = new window.tracking.ObjectTracker('face');
                    tracker.setInitialScale(4);
                    tracker.setStepSize(2);
                    tracker.setEdgesDensity(0.1);
                    
                    const tempImg = document.createElement('img');
                    tempImg.src = img.src;
                    tempImg.style.position = 'absolute';
                    tempImg.style.left = '-9999px';
                    tempImg.style.top = '-9999px';
                    document.body.appendChild(tempImg);
                    
                    tempImg.onload = () => {
                        const task = window.tracking.track(tempImg, tracker);
                        tracker.once('track', (event) => {
                            task.stop();
                            document.body.removeChild(tempImg);
                            
                            if (event.data && event.data.length > 0) {
                                const face = event.data[0];
                                const padX = face.width * 0.3;
                                const padY = face.height * 0.3;
                                
                                const cropX = Math.max(0, face.x - padX);
                                const cropY = Math.max(0, face.y - padY * 1.5);
                                const cropW = Math.min(img.width - cropX, face.width + padX * 2);
                                const cropH = Math.min(img.height - cropY, face.height + padY * 3);
                                
                                const canvas = document.createElement('canvas');
                                canvas.width = 160;
                                canvas.height = 160;
                                const ctx = canvas.getContext('2d');
                                ctx.drawImage(img, cropX, cropY, cropW, cropH, 0, 0, 160, 160);
                                
                                canvas.toBlob((blob) => {
                                    if (blob) {
                                        const croppedFile = new File([blob], 'face_' + file.name, { type: 'image/jpeg' });
                                        resolve(croppedFile);
                                    } else {
                                        resolve(null);
                                    }
                                }, 'image/jpeg', 0.9);
                            } else {
                                resolve(null);
                            }
                        });
                    };
                } catch (err) {
                    console.error("Face detection initialization failed:", err);
                    resolve(null);
                }
            };
        };
        reader.readAsDataURL(file);
    });
}

function getProfilePreviewUrl(file) {
    if (!file) return '';
    if (typeof file === 'string') return '/storage/' + file;
    return URL.createObjectURL(file);
}

async function onPassportPhotoChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    clientForm.value.passport_photo = file;
    
    if (file.type && file.type.startsWith('image/')) {
        const croppedFace = await detectAndCropFace(file);
        if (croppedFace) {
            clientForm.value.profile_photo = croppedFace;
        }
    }
}

function onRegionChange() {
    clientForm.value.district = '';
    clientForm.value.address = '';
}

function onDistrictChange() {
    clientForm.value.address = '';
}
const newLeadForm = ref({ full_name: '', phone: '', source: 'manual', is_manual: true, budget: '', currency: 'uzs', comment: '' });
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
    router.post(route('admin.crm.schedules.pay', schedule.id), { preserveScroll: true, preserveState: true,
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
        if (selectedClientForDetail.value) {
            selectClientForDetail(selectedClientForDetail.value);
        } else {
            showModal.value = false;
            router.reload({ only: ['contracts'] });
        }
    } catch (e) {
        alert('Xatolik yuz berdi: ' + (e.response?.data?.message || e.message));
    } finally {
        isProcessing.value = false;
    }
};

const showSotildiConfirmModal = ref(false);
const sotildiAccountId = ref('');
const sotildiPaymentMethod = ref('bank');

watch(sotildiAccountId, (newAccountId) => {
    if (newAccountId && props.cashRegisters) {
        const reg = props.cashRegisters.find(r => r.id === newAccountId);
        if (reg) {
            if (reg.type === 'bank') {
                sotildiPaymentMethod.value = 'bank';
            } else if (reg.type === 'cash') {
                sotildiPaymentMethod.value = 'main';
            } else {
                sotildiPaymentMethod.value = 'credit';
            }
        }
    }
});

const forceActivateContract = async (accountId = null, paymentMethod = null) => {
    if (!confirm("Haqiqatan ham ushbu shartnomani qo'lda 'Sotildi' holatiga o'tkazmoqchimisiz?")) return;
    
    isProcessing.value = true;
    try {
        await axios.post(route('admin.contracts.force_activate', selectedContract.value.id), { preserveScroll: true, preserveState: true,
            account_id: accountId,
            payment_method: paymentMethod
        });
        router.reload({ only: ['contracts'] });
        
        // Update local state to reflect changes instantly
        selectedContract.value.status = 'active';
        selectedContract.value.ai_verification_status = 'passed';
        
        if (selectedClientForDetail.value) {
            selectClientForDetail(selectedClientForDetail.value);
        }
    } catch (e) {
        alert('Xatolik yuz berdi: ' + (e.response?.data?.message || e.message));
    } finally {
        isProcessing.value = false;
    }
};

const handleSotildiClick = () => {
    if (selectedContract.value.down_payment > 0) {
        sotildiAccountId.value = '';
        sotildiPaymentMethod.value = 'bank';
        showSotildiConfirmModal.value = true;
    } else {
        forceActivateContract();
    }
};

const confirmForceActivate = async () => {
    if (!sotildiAccountId.value) return;
    showSotildiConfirmModal.value = false;
    await forceActivateContract(sotildiAccountId.value, sotildiPaymentMethod.value);
};

function recalculatePenalty() {
    isProcessing.value = true;
    router.post(route('admin.crm.contracts.recalculate_penalty', selectedContract.value.id), { preserveScroll: true, preserveState: true,
        daily_penalty_rate: selectedContract.value.daily_penalty_rate
    }, {
        onSuccess: (page) => {
            isProcessing.value = false;
            const updatedContract = (page.props.contracts || []).find(c => c.id === selectedContract.value.id);
            if (updatedContract) {
                selectedContract.value = JSON.parse(JSON.stringify(updatedContract));
            }
            if (selectedClientForDetail.value) {
                selectClientForDetail(selectedClientForDetail.value);
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
        phone_2: lead.phone_2 || '', 
        passport_series: lead.passport_series || '', 
        gender: lead.gender || '', 
        birth_date: lead.birth_date || '', 
        region: lead.region || '', 
        district: lead.district || '', 
        address: lead.address || '', 
        assigned_employee_id: lead.assigned_employee_id || '', 
        source: lead.source || 'manual', 
        is_manual: lead.source === 'manual',
        email: lead.email || '',
        social_url: lead.social_url || ''
    };
    showDetailedLeadModal.value = true;
}

function openDetailedLeadModal() {
    isEditingLead.value = false;
    editingLeadId.value = null;
    newLeadForm.value = { 
        full_name: '', 
        phone: '', 
        phone_2: '', 
        passport_series: '', 
        gender: '', 
        birth_date: '', 
        region: '', 
        district: '', 
        address: '', 
        assigned_employee_id: '', 
        source: 'manual', 
        is_manual: true, 
        budget: '', 
        currency: 'uzs', 
        comment: '' 
    };
    showDetailedLeadModal.value = true;
}

function openCreateLeadModal() {
    isEditingLead.value = false;
    editingLeadId.value = null;
    newLeadForm.value = { full_name: '', phone: '', source: 'manual', is_manual: true, budget: '', currency: 'uzs', comment: '' };
    showCreateLeadModal.value = true;
}

function submitNewLead() {
    isProcessing.value = true;
    if (isEditingLead.value) {
        axios.put(route('admin.crm.leads.update', editingLeadId.value), newLeadForm.value)
            .then(() => {
                showCreateLeadModal.value = false;
                showDetailedLeadModal.value = false;
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
                showDetailedLeadModal.value = false;
                newLeadForm.value = { full_name: '', phone: '', source: 'manual', is_manual: true, budget: '', currency: 'uzs', comment: '' };
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
    currentStep.value = 1;
    selectedPodyezd.value = '';
    basePrice.value = 0;
    markupPercent.value = 0;
    hasDiscount.value = false;
    discountPercent.value = 0;
    discountAmount.value = 0;
    repairType.value = 'tamirsiz';
    selectedBuildingId.value = '';
    selectedFacilityName.value = '';
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
        region: '',
        district: '',
        passport_photo: null,
        passport_photo_back: null,
        profile_photo: null,
        source: '',
        contract_language: 'O\'zbek tili',
        contract_id: ''
    };
    contractForm.value = { buyer_type: 'client', partner_id: '', client_id: '', unit_id: '', contract_number: '', contract_type: 'residential', price_per_sqm: '', total_amount: '', down_payment: '', term_months: 36, payment_type: 'installment', created_at: '', actual_paid_amount: '', payment_method: 'bank', account_id: '', floor: '', rooms: '', user_id: '', contract_template_id: '', selected_variants: {} };
    showCreateClientModal.value = true;
}

function openEditClientModal(client) {
    isEditingClient.value = true;
    editingClientId.value = client.id;
    currentStep.value = 1;
    selectedPodyezd.value = '';
    basePrice.value = 0;
    markupPercent.value = 0;
    hasDiscount.value = false;
    discountPercent.value = 0;
    discountAmount.value = 0;
    repairType.value = 'tamirsiz';
    selectedBuildingId.value = '';
    selectedFacilityName.value = '';
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
        region: client.region || '',
        district: client.district || '',
        passport_photo: null,
        passport_photo_back: null,
        profile_photo: null,
        source: client.source || '',
        contract_language: client.contract_language || 'O\'zbek tili',
        contract_id: client.contract_id || ''
    };
    contractForm.value = { buyer_type: 'client', partner_id: '', client_id: '', unit_id: '', contract_number: '', contract_type: 'residential', price_per_sqm: '', total_amount: '', down_payment: '', term_months: 36, payment_type: 'installment', created_at: '', actual_paid_amount: '', payment_method: 'bank', account_id: '', floor: '', rooms: '', user_id: '', contract_template_id: '', selected_variants: {} };
    showCreateClientModal.value = true;
}

function nextStep() {
    if (currentStep.value === 1) {
        if (!clientForm.value.first_name || !clientForm.value.last_name || !clientForm.value.phone) {
            alert("Iltimos, barcha majburiy maydonlarni to'ldiring (Ism, Familiya, Telefon)!");
            return;
        }
        currentStep.value = 2;
    } else if (currentStep.value === 2) {
        if (!contractForm.value.unit_id) {
            alert("Iltimos, xonadon yoki do'konni tanlang!");
            return;
        }
        currentStep.value = 3;
    }
}

function prevStep() {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
}

function saveClient() {
    isProcessing.value = true;
    if (isEditingClient.value) {
        router.post(route('admin.crm.clients.update', editingClientId.value), {
            _method: 'PUT',
            ...clientForm.value
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showCreateClientModal.value = false;
                isProcessing.value = false;
                if (detailedClientData.value && detailedClientData.value.id === editingClientId.value) {
                    selectClientForDetail(detailedClientData.value);
                }
            },
            onError: (errors) => {
                isProcessing.value = false;
                console.error("Mijozni saqlashda xatolik:", errors);
                const errorMsgs = Object.values(errors).join("\n");
                alert("Mijozni saqlashda xatolik yuz berdi:\n" + errorMsgs);
            }
        });
    } else {
        const formData = new FormData();
        for (const key in clientForm.value) {
            if (clientForm.value[key] !== null && clientForm.value[key] !== undefined) {
                formData.append(key, clientForm.value[key]);
            }
        }
        
        axios.post(route('admin.crm.clients.store'), formData, { preserveScroll: true, preserveState: true,
            headers: {
                'Content-Type': 'multipart/form-data',
                'Accept': 'application/json'
            }
        }).then(response => {
            if (response.data && response.data.status === 'success') {
                const client = response.data.client;
                
                contractForm.value.client_id = client.id;
                contractForm.value.buyer_type = 'client';
                contractForm.value.total_amount = finalContractPrice.value;
                contractForm.value.price_per_sqm = pricePerSqm.value;
                
                const notesArr = [];
                notesArr.push(`Tamir turi: ${repairType.value === 'tamirli' ? 'Tamirli' : 'Tamirsiz'}`);
                if (hasDiscount.value) {
                    notesArr.push(`Chegirma: ${discountPercent.value}% (${discountAmount.value.toLocaleString()} UZS)`);
                }
                if (markupPercent.value > 0) {
                    notesArr.push(`Ustama/Foiz: ${markupPercent.value}%`);
                }
                contractForm.value.notes = notesArr.join(' • ');
                
                router.post(route('admin.contracts.store'), contractForm.value, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        showCreateClientModal.value = false;
                        isProcessing.value = false;
                        currentStep.value = 1;
                    },
                    onError: () => {
                        isProcessing.value = false;
                        alert("Mijoz yaratildi, ammo shartnoma kiritishda xatolik yuz berdi. Iltimos shartnomalarni tekshiring!");
                    }
                });
            } else {
                isProcessing.value = false;
                alert("Mijozni saqlashda xatolik: " + (response.data?.message || 'Noma\'lum xato'));
            }
        }).catch(error => {
            isProcessing.value = false;
            const msg = error.response?.data?.message || error.message || 'Noma\'lum server xatosi';
            alert("Mijozni saqlashda xatolik: " + msg);
        });
    }
}

function deleteClient(id) {
    if (confirm(t('confirm_delete_client'))) {
        router.delete(route('admin.crm.clients.delete', id), {
            preserveScroll: true
        });
    }
}

// Create / Edit Contract Form
const showCreateContractModal = ref(false);
const isEditingContract = ref(false);
const currentContractId = ref(null);
const contractForm = ref({ buyer_type: 'client', partner_id: '', client_id: '', unit_id: '', contract_number: '', contract_type: 'residential', price_per_sqm: '', total_amount: '', down_payment: '', term_months: 36, payment_type: 'installment', created_at: '', actual_paid_amount: '', payment_method: 'bank', account_id: '', floor: '', rooms: '', user_id: '', contract_template_id: '', selected_variants: {} });

const filteredTemplates = computed(() => {
    if (!props.contractTemplates) return [];
    return props.contractTemplates.filter(t => t.type === contractForm.value.contract_type || t.type === 'custom');
});

const selectedTemplate = computed(() => {
    if (!contractForm.value.contract_template_id) return null;
    return props.contractTemplates.find(t => t.id === contractForm.value.contract_template_id);
});

const customPlaceholders = computed(() => {
    if (!selectedTemplate.value || !Array.isArray(selectedTemplate.value.placeholders)) {
        return [];
    }
    const EXCLUDED = [
        'KUN', 'OY', 'YIL', 'SHARTNOMA_RAQAMI', 'MIJOZ_FISH', 'MIJOZ_FISH_QISQA',
        'MIJOZ_MANZILI', 'MIJOZ_JSHSHIR', 'PASPORT_SERIYA', 'PASPORT_SANA', 'PASPORT_JOYI',
        'BLOK', 'QAVAT', 'XONA_RAQAMI', 'XONALAR_SONI', 'MAYDON', 'KV_METR_NARXI',
        'UMUMIY_NARX', 'UMUMIY_NARX_SOZ_BILAN', 'BOSHLANGICH_TOLOV', 'BOSHLANGICH_TOLOV_SOZ_BILAN',
        'BOSHLANGICH_TOLOV_FOIZ', 'MUDDAT_OY', 'MUDDAT_OY_SOZ_BILAN', 'MIJOZ_TELEFON',
        'DIREKTOR', 'KORXONA_NOMI', 'KORXONA_MANZILI', 'KORXONA_BANK_ACCOUNT',
        'KORXONA_BANK', 'KORXONA_MFO', 'KORXONA_INN', 'KORXONA_TELEFON',
        'BOSHLANGICH_FOIZ', 'TOLOV_MUDDAT_KUN', 'JAMI_NARX', 'JAMI_NARX_SOZDA', 'OYLIK_TOLOV', 'OYLIK_TOLOV_SOZ_BILAN'
    ];
    const filtered = selectedTemplate.value.placeholders.filter(p => !EXCLUDED.includes(p));
    
    if (contractForm.value && contractForm.value.selected_variants) {
        if (!contractForm.value.selected_variants.custom_placeholders) {
            contractForm.value.selected_variants.custom_placeholders = {};
        }
        filtered.forEach(p => {
            if (contractForm.value.selected_variants.custom_placeholders[p] === undefined) {
                contractForm.value.selected_variants.custom_placeholders[p] = '';
            }
        });
    }
    
    return filtered;
});

function getSelectedVariant(anchor) {
    const tpl = selectedTemplate.value;
    if (!tpl) return null;
    const group = tpl.variant_groups.find(g => g.anchor === anchor);
    if (!group) return null;
    const key = contractForm.value.selected_variants?.[anchor]?.key;
    return group.variants.find(v => v.key === key);
}

function onVariantChange(anchor) {
    const variant = getSelectedVariant(anchor);
    if (variant && variant.placeholders) {
        const ph = {};
        variant.placeholders.forEach(pKey => {
            ph[pKey] = '';
        });
        contractForm.value.selected_variants[anchor].placeholders = ph;
    } else if (contractForm.value.selected_variants[anchor]) {
        contractForm.value.selected_variants[anchor].placeholders = {};
    }
}

function onSubPlaceholderInput(anchor, pKey) {
    const val = contractForm.value.selected_variants[anchor].placeholders[pKey];
    
    if (pKey === 'BOSHLANGICH_FOIZ') {
        const percent = parseFloat(val);
        if (!isNaN(percent)) {
            const calculated = Math.round(contractForm.value.total_amount * (percent / 100));
            contractForm.value.down_payment = calculated;
        }
    }
    
    if (pKey === 'MUDDAT_OY') {
        const months = parseInt(val);
        if (!isNaN(months)) {
            contractForm.value.term_months = months;
        }
    }
}

watch(() => contractForm.value.contract_template_id, (newVal) => {
    if (!newVal) {
        contractForm.value.selected_variants = {};
        return;
    }
    const tpl = props.contractTemplates.find(t => t.id === newVal);
    const sv = {
        custom_placeholders: {}
    };
    if (tpl && tpl.variant_groups) {
        tpl.variant_groups.forEach(g => {
            sv[g.anchor] = {
                key: '',
                placeholders: {}
            };
        });
    }
    contractForm.value.selected_variants = sv;
});

function editContract(contract) {
    isEditingContract.value = true;
    currentContractId.value = contract.id;
    
    const incomeTxns = (contract.transactions || []).filter(t => t.type === 'income');
    const existingPaidSum = incomeTxns.reduce((sum, t) => sum + Number(t.amount || 0), 0);
    const firstTx = incomeTxns.length > 0 ? incomeTxns[0] : null;

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
        actual_paid_amount: existingPaidSum || '',
        payment_method: firstTx ? firstTx.account_type || 'bank' : 'bank',
        account_id: firstTx ? firstTx.account_id || '' : '',
        floor: contract.floor || contract.unit?.floor?.floor_number || '',
        rooms: contract.rooms || contract.unit?.rooms_count || '',
        user_id: contract.user_id || '',
        contract_template_id: contract.contract_template_id || '',
        selected_variants: (() => {
            const sv = contract.selected_variants || {};
            if (!sv.custom_placeholders) {
                sv.custom_placeholders = {};
            }
            return sv;
        })()
    };
    showCreateContractModal.value = true;
}

function deleteContract(id) {
    if (confirm(t('confirm_delete_contract'))) {
        router.delete(route('admin.contracts.destroy', id), {
            preserveScroll: true,
            preserveState: true
        });
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
            preserveScroll: true,
            preserveState: true,
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
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showCreateContractModal.value = false;
                contractForm.value = { buyer_type: 'client', partner_id: '', client_id: '', unit_id: '', contract_number: '', contract_type: 'residential', price_per_sqm: '', total_amount: '', down_payment: '', term_months: 36, payment_type: 'installment', created_at: '', actual_paid_amount: '', payment_method: 'bank', account_id: '', floor: '', rooms: '', user_id: '', contract_template_id: '', selected_variants: {} };
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

function onFacilityChange() {
    selectedBuildingId.value = '';
    contractForm.value.unit_id = '';
}

function onBuildingChange() {
    if (typeof selectedPodyezd !== 'undefined') {
        selectedPodyezd.value = '';
    }
    contractForm.value.unit_id = '';
}

const uniqueFacilities = computed(() => {
    const names = (props.units || [])
        .map(u => u.floor?.building?.name?.trim())
        .filter(Boolean);
    return [...new Set(names)];
});

const buildingsForSelectedFacility = computed(() => {
    const searchVal = (selectedFacilityName.value || '').trim();
    if (!searchVal) return [];
    
    const buildingsMap = {};
    (props.units || []).forEach(u => {
        const b = u.floor?.building;
        if (b && b.name && b.name.trim() === searchVal) {
            buildingsMap[b.id] = b;
        }
    });
    return Object.values(buildingsMap);
});

const unitsForSelectedBuilding = computed(() => {
    if (!selectedBuildingId.value) return [];
    const targetBuildingId = Number(selectedBuildingId.value);
    return (props.units || []).filter(u => {
        const bId = u.floor?.building_id || u.floor?.building?.id;
        return bId !== undefined && Number(bId) === targetBuildingId;
    });
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
    let statusText = '';
    if (u.status === 'available') statusText = " - Sotuvda";
    else if (u.status === 'reserved') statusText = " - Band qilingan";
    else if (u.status === 'sold') statusText = " - Sotilgan";
    else statusText = " - Noma'lum";
    return `${typeLabel} ${prefix}${u.unit_number} (Qavat: ${u.floor?.floor_number ?? ''})${statusText}`;
}

const facilityOptions = computed(() => {
    return uniqueFacilities.value.map(name => ({
        label: name,
        value: name
    }));
});

const buildingOptions = computed(() => {
    return buildingsForSelectedFacility.value.map(b => ({
        label: b.block_name || b.corpus_name || 'Asosiy Blok',
        value: b.id
    }));
});

const unitOptions = computed(() => {
    return sortedUnitsForSelectedBuilding.value
        .filter(u => u.status === 'available' || (isEditingContract.value && u.id === contractForm.value.unit_id))
        .map(u => ({
            label: getUnitLabel(u),
            value: u.id
        }));
});

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

const salesAgentsList = computed(() => {
    return (props.users || []).filter(u => {
        const roles = u.roles || [];
        return roles.some(r => r.name === 'Sotuvchi' || r === 'Sotuvchi');
    });
});

watch(() => contractForm.value.unit_id, (newUnitId) => {
    if (newUnitId) {
        const u = props.units.find(un => un.id === newUnitId);
        if (u) {
            selectedFacilityName.value = u.floor?.building?.name || '';
            selectedBuildingId.value = u.floor?.building?.id || '';
        }
    }
});

watch(showCreateContractModal, (isOpen) => {
    if (isOpen) {
        if (contractForm.value.unit_id) {
            const u = props.units.find(un => un.id === contractForm.value.unit_id);
            if (u) {
                selectedFacilityName.value = u.floor?.building?.name || '';
                selectedBuildingId.value = u.floor?.building?.id || '';
            } else {
                selectedFacilityName.value = '';
                selectedBuildingId.value = '';
            }
        } else {
            selectedFacilityName.value = '';
            selectedBuildingId.value = '';
        }
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
    if (contractForm.value.unit_id) {
        const u = props.units.find(unit => unit.id === contractForm.value.unit_id);
        if (u) {
            const area = Number(u.area_sqm) || 0;
            contractForm.value.price_per_sqm = area > 0 ? (Number(newTotal) / area).toFixed(2) : 0;
        }
    }
    if (contractForm.value.payment_type === 'full') {
        contractForm.value.down_payment = Number(newTotal);
    }
});

watch(() => contractForm.value.payment_type, (newType) => {
    if (newType === 'full') {
        contractForm.value.down_payment = contractForm.value.total_amount;
        contractForm.value.term_months = 1;
    } else if (newType === 'installment') {
        if (Number(contractForm.value.down_payment) === Number(contractForm.value.total_amount)) {
            contractForm.value.down_payment = contractForm.value.total_amount * 0.3;
            contractForm.value.term_months = 36;
        }
    }
});

const filteredCashRegisters = computed(() => {
    return props.cashRegisters || [];
});

watch(() => contractForm.value.account_id, (newAccountId) => {
    if (newAccountId && props.cashRegisters) {
        const reg = props.cashRegisters.find(r => r.id === newAccountId);
        if (reg) {
            if (reg.type === 'bank') {
                contractForm.value.payment_method = 'bank';
            } else if (reg.type === 'cash') {
                contractForm.value.payment_method = 'main';
            } else {
                contractForm.value.payment_method = 'credit';
            }
        }
    }
}, { immediate: true });

watch(() => contractForm.value.actual_paid_amount, (newAmount) => {
    if (Number(newAmount) > 0 && !contractForm.value.account_id) {
        const list = filteredCashRegisters.value;
        if (list.length > 0) {
            contractForm.value.account_id = list[0].id;
        }
    }
});

const activeTab = ref('dashboard');
const previousTabBeforeDetail = ref(null);
const leadsViewMode = ref('kanban'); // 'kanban' or 'list'

// Sales Agents Tab states
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
    router.post(route('admin.crm.settings.save'), settingsForm.value, { preserveScroll: true, preserveState: true,
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
            router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, replace: true });
        }
    } catch (e) {
        console.error('URL replace error in CRM activeTab watcher', e);
    }
});
const searchQuery = ref('');
const leadsSearchQuery = ref('');
const leadsSourceFilter = ref('');
const canceledSearchQuery = ref('');
const selectedBuildingFilter = ref('');
const selectedStatusFilter = ref('');
const selectedManagerFilter = ref('');
const showExtraFilters = ref(false);

const selectedContractStatusFilter = ref('');
const selectedContractBuildingFilter = ref('');
const selectedContractManagerFilter = ref('');
const showContractExtraFilters = ref(false);

const overdueViewMode = ref('cards');

// Wizard Refs & Computed
const currentStep = ref(1);
const selectedPodyezd = ref('');
const basePrice = ref(0);
const markupPercent = ref(0);
const hasDiscount = ref(false);
const discountPercent = ref(0);
const discountAmount = ref(0);
const repairType = ref('tamirsiz');

const allBuildingOptions = computed(() => {
    const buildingsMap = {};
    (props.units || []).forEach(u => {
        if (u.status === 'available') {
            const b = u.floor?.building;
            if (b) {
                buildingsMap[b.id] = {
                    label: `${b.name} - ${b.block_name || b.corpus_name || 'Asosiy Blok'}`,
                    value: b.id
                };
            }
        }
    });
    return Object.values(buildingsMap);
});

const availablePodyezds = computed(() => {
    if (!selectedBuildingId.value) return [];
    const podyezds = unitsForSelectedBuilding.value
        .filter(u => u.status === 'available')
        .map(u => u.podyezd)
        .filter(Boolean);
    return [...new Set(podyezds)].sort((a, b) => Number(a) - Number(b));
});

const unitsForSelectedBuildingAndPodyezd = computed(() => {
    let units = unitsForSelectedBuilding.value;
    units = units.filter(u => u.status === 'available');
    if (selectedPodyezd.value) {
        units = units.filter(u => String(u.podyezd) === String(selectedPodyezd.value));
    }
    return units;
});

const sortedUnitsForSelectedBuildingAndPodyezd = computed(() => {
    return [...unitsForSelectedBuildingAndPodyezd.value].sort((a, b) => {
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

const wizardUnitOptions = computed(() => {
    return sortedUnitsForSelectedBuildingAndPodyezd.value.map(u => ({
        label: getUnitLabel(u),
        value: u.id
    }));
});

const markupPrice = computed(() => {
    return Math.round(basePrice.value * (1 + markupPercent.value / 100));
});

const pricePerSqm = computed(() => {
    const area = Number(selectedUnit.value?.area_sqm) || 1;
    return Math.round(basePrice.value / area);
});

const finalContractPrice = computed(() => {
    let price = markupPrice.value;
    if (hasDiscount.value) {
        price = price - discountAmount.value;
    }
    return price;
});


function onPodyezdChange() {
    contractForm.value.unit_id = '';
}

// Watchers for discount
watch(discountPercent, (newVal) => {
    if (hasDiscount.value) {
        discountAmount.value = Math.round(markupPrice.value * (Number(newVal) / 100));
    }
});

watch(discountAmount, (newVal) => {
    if (hasDiscount.value && markupPrice.value > 0) {
        discountPercent.value = Number(((Number(newVal) / markupPrice.value) * 100).toFixed(2));
    }
});

watch(finalContractPrice, (newPrice) => {
    contractForm.value.total_amount = newPrice;
    if (contractForm.value.payment_type === 'installment') {
        contractForm.value.down_payment = Math.round(newPrice * 0.3);
    } else {
        contractForm.value.down_payment = newPrice;
    }
});

watch(hasDiscount, (newVal) => {
    if (!newVal) {
        discountPercent.value = 0;
        discountAmount.value = 0;
    }
});

watch(() => contractForm.value.unit_id, (newUnitId) => {
    if (newUnitId && !isEditingClient.value) {
        const u = props.units.find(unit => unit.id === newUnitId);
        if (u) {
            basePrice.value = Number(u.price) || 0;
        }
    }
});

const showExcelPreviewModal = ref(false);

function openExcelPreviewModal() {
    showExcelPreviewModal.value = true;
}

function closeExcelPreviewModal() {
    showExcelPreviewModal.value = false;
}

function downloadExcel() {
    if (activeTab.value === 'contracts') {
        window.location.href = route('admin.crm.export.contracts.excel');
    } else {
        window.location.href = route('admin.crm.export.clients.excel');
    }
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
    if (client && client.birth_date) {
        return client.birth_date;
    }
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
const savedScrollPosition = ref(0);

function selectClientForDetail(client, saveScroll = false) {
    if (saveScroll) {
        const scrollContainer = document.querySelector('[scroll-region]');
        if (scrollContainer) {
            const currentScroll = scrollContainer.scrollTop;
            savedScrollPosition.value = currentScroll;
            sessionStorage.setItem('crm_clients_scroll', currentScroll);
        }
    }

    if (activeTab.value !== 'clients' && !['clients', 'clients_new', 'clients_active', 'clients_debtors', 'clients_blacklist'].includes(activeTab.value)) {
        previousTabBeforeDetail.value = activeTab.value;
    }
    activeTab.value = 'clients';
    selectedClientForDetail.value = client;
    clientDetailLoading.value = true;
    detailedClientData.value = null;
    
    axios.get(route('admin.crm.clients.details', client.id))
        .then(response => {
            if (response.data.status === 'success') {
                detailedClientData.value = response.data.client;
                if (detailedClientData.value && detailedClientData.value.contracts && detailedClientData.value.contracts.length > 0) {
                    selectedContract.value = detailedClientData.value.contracts[0];
                } else {
                    selectedContract.value = null;
                }
            }
        })
        .catch(err => {
            console.error('Mijoz ma\'lumotlarini yuklashda xatolik:', err);
        })
        .finally(() => {
            clientDetailLoading.value = false;
        });
}

function closeClientDetail() {
    selectedClientForDetail.value = null;
    detailedClientData.value = null;
    if (previousTabBeforeDetail.value) {
        activeTab.value = previousTabBeforeDetail.value;
        previousTabBeforeDetail.value = null;
    }
    
    const storedScroll = sessionStorage.getItem('crm_clients_scroll');
    const targetScroll = storedScroll ? parseInt(storedScroll, 10) : savedScrollPosition.value;
    
    nextTick(() => {
        const scrollContainer = document.querySelector('[scroll-region]');
        if (scrollContainer) {
            scrollContainer.scrollTop = targetScroll;
            
            // Re-apply scroll position after small delays to ensure layout calculations are complete
            setTimeout(() => {
                scrollContainer.scrollTop = targetScroll;
            }, 30);
            setTimeout(() => {
                scrollContainer.scrollTop = targetScroll;
            }, 100);
            setTimeout(() => {
                scrollContainer.scrollTop = targetScroll;
            }, 250);
        }
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

function getContractFinancials(contract) {
    if (!contract) return { total: 0, paid: 0, debt: 0, percent: 0 };
    const total = Number(contract.total_amount || 0);
    const schedules = contract.payment_schedules || contract.paymentSchedules || [];
    const paid = schedules.reduce((sum, s) => sum + Number(s.paid_amount || 0), 0);
    const debt = Math.max(0, total - paid);
    const percent = total > 0 ? Math.round((paid / total) * 100) : 0;
    return { total, paid, debt, percent };
}

function getContractDownPaymentStats(contract) {
    if (!contract) return { total: 0, paid: 0, count: 0, payments: [] };
    const schedules = contract.payment_schedules || contract.paymentSchedules || [];
    const dpSchedules = schedules.filter(s => s.payment_type === 'down_payment');
    
    const total = dpSchedules.reduce((sum, s) => sum + Number(s.expected_amount || 0), 0);
    const paid = dpSchedules.reduce((sum, s) => sum + Number(s.paid_amount || 0), 0);
    const count = dpSchedules.length;
    
    const payments = [];
    const contractTransactions = contract.transactions || [];
    const dpScheduleIds = dpSchedules.map(s => s.id);
    
    contractTransactions.forEach(t => {
        if (t.status === 'approved' && t.type === 'income') {
            const isLinked = t.payment_schedule_id && dpScheduleIds.includes(t.payment_schedule_id);
            const isInitial = t.category && (t.category.name.toLowerCase().includes('boshlang') || t.category.name.toLowerCase().includes('initial'));
            const isInitialNote = t.notes && (t.notes.toLowerCase().includes('boshlang') || t.notes.toLowerCase().includes('initial'));
            if (isLinked || ((isInitial || isInitialNote) && !payments.some(p => p.id === t.id))) {
                payments.push({
                    id: t.id,
                    amount: Number(t.amount),
                    date: t.transaction_date || t.created_at
                });
            }
        }
    });

    // Fallback if no transactions found but schedules show paid amount
    if (payments.length === 0 && paid > 0) {
        dpSchedules.forEach(s => {
            if (Number(s.paid_amount) > 0) {
                payments.push({
                    id: s.id,
                    amount: Number(s.paid_amount),
                    date: s.actual_payment_date || s.due_date
                });
            }
        });
    }

    return { total, paid, count, payments };
}

function getContractSchedules(contract) {
    if (!contract) return [];
    return contract.payment_schedules || contract.paymentSchedules || [];
}

function getContractPaymentRows(contract) {
    if (!contract) return [];
    
    const rows = [];
    
    // 1. Add all approved income transactions as completed rows
    const txns = contract.transactions || [];
    txns.forEach(t => {
        if (t.status === 'approved' && t.type === 'income') {
            // Determine type
            let pType = 'installment';
            const catName = t.category?.name || '';
            const notes = t.notes || '';
            if (
                catName.toLowerCase().includes('boshlang') || 
                catName.toLowerCase().includes('initial') ||
                notes.toLowerCase().includes('boshlang') ||
                notes.toLowerCase().includes('initial') ||
                notes.toLowerCase().includes('bosh to\'lov') ||
                notes.toLowerCase().includes('bosh to’lov')
            ) {
                pType = 'down_payment';
            }
            
            rows.push({
                is_txn: true,
                id: 'txn_' + t.id,
                date: t.transaction_date || t.created_at,
                amount: Number(t.amount),
                expected_amount: Number(t.amount),
                paid_amount: Number(t.amount),
                remaining_debt: 0,
                payment_type: pType,
                penalty: 0,
                status: 'paid'
            });
        }
    });
    
    // 2. Add all unpaid/partially paid schedules as outstanding rows
    const schedules = contract.payment_schedules || contract.paymentSchedules || [];
    schedules.forEach(s => {
        const remaining = Number(s.expected_amount || 0) - Number(s.paid_amount || 0);
        if (remaining > 0.01) {
            rows.push({
                is_txn: false,
                id: 'sched_' + s.id,
                date: s.due_date,
                amount: remaining,
                expected_amount: remaining,
                paid_amount: 0,
                remaining_debt: remaining,
                payment_type: s.payment_type,
                penalty: Number(s.penalty_for_this_month || 0),
                status: s.status // 'pending' or 'overdue'
            });
        }
    });
    
    // 3. Sort chronologically by date
    return rows.sort((a, b) => {
        const dateA = new Date(a.date);
        const dateB = new Date(b.date);
        if (dateA.getTime() !== dateB.getTime()) {
            return dateA.getTime() - dateB.getTime();
        }
        // If same date, put paid transactions first
        if (a.is_txn !== b.is_txn) {
            return a.is_txn ? -1 : 1;
        }
        return String(a.id).localeCompare(String(b.id));
    });
}

const filteredLeads = computed(() => {
    let list = props.leads || [];
    if (leadsSearchQuery.value) {
        const q = leadsSearchQuery.value.toLowerCase().trim();
        list = list.filter(l => 
            (l.full_name && l.full_name.toLowerCase().includes(q)) ||
            (l.name && l.name.toLowerCase().includes(q)) ||
            (l.phone && l.phone.includes(q)) ||
            (l.email && l.email.toLowerCase().includes(q))
        );
    }
    if (leadsSourceFilter.value) {
        list = list.filter(l => l.source === leadsSourceFilter.value);
    }
    return list;
});

const leadsByStatus = computed(() => {
    return {
        new: filteredLeads.value.filter(l => l.status === 'new'),
        contacted: filteredLeads.value.filter(l => l.status === 'contacted'),
        qualified: filteredLeads.value.filter(l => l.status === 'qualified'),
        lost: filteredLeads.value.filter(l => l.status === 'lost')
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
    
    // Only show clients who don't have contracts
    result = result.filter(c => !c.contracts || c.contracts.length === 0);
    
    // Apply tab filter
    if (activeTab.value === 'clients_new') {
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
        result = result.filter(c => !c.is_blacklisted && (new Date(c.created_at) >= thirtyDaysAgo));
    } else if (activeTab.value === 'clients_active') {
        result = [];
    } else if (activeTab.value === 'clients_debtors') {
        result = [];
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
    let contracts = props.contracts || [];
    
    // Status Filter
    if (selectedContractStatusFilter.value === 'approved') {
        contracts = contracts.filter(c => c.status === 'active' || c.status === 'approved');
    } else if (selectedContractStatusFilter.value === 'pending') {
        contracts = contracts.filter(c => c.status === 'pending' || c.status === 'draft');
    } else if (selectedContractStatusFilter.value === 'cancelled') {
        contracts = contracts.filter(c => c.status === 'cancelled' || c.status === 'canceled');
    } else {
        // By default show active/approved and pending/draft, exclude cancelled/canceled
        contracts = contracts.filter(c => c.status !== 'canceled' && c.status !== 'cancelled');
    }

    // Search query filter
    if (contractSearchQuery.value) {
        const query = contractSearchQuery.value.toLowerCase().trim();
        contracts = contracts.filter(c => {
            const clientFirstName = (c.client?.first_name || '').toLowerCase();
            const clientLastName = (c.client?.last_name || '').toLowerCase();
            const clientMiddleName = (c.client?.middle_name || '').toLowerCase();
            const clientFullName = `${clientFirstName} ${clientLastName} ${clientMiddleName}`;
            const contractNumber = (c.contract_number || '').toLowerCase();
            const unitNumber = (c.unit?.unit_number || '').toString().toLowerCase();
            const clientPhone = (c.client?.phone || '').toLowerCase();
            return clientFirstName.includes(query) || 
                   clientLastName.includes(query) || 
                   clientFullName.includes(query) || 
                   contractNumber.includes(query) || 
                   unitNumber.includes(query) ||
                   clientPhone.includes(query);
        });
    }

    // Advanced building filter
    if (selectedContractBuildingFilter.value) {
        contracts = contracts.filter(c => c.unit?.floor?.building?.name === selectedContractBuildingFilter.value);
    }

    // Advanced manager filter
    if (selectedContractManagerFilter.value) {
        contracts = contracts.filter(c => c.partner?.user?.name === selectedContractManagerFilter.value);
    }

    const groups = {};
    contracts.forEach(c => {
        const blockName = c.unit?.floor?.building?.block_name || c.unit?.floor?.building?.corpus_name || c.unit?.floor?.building?.name || "Boshqa (Biriktirilmagan)";
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

const flatGroupedContracts = computed(() => {
    return Object.values(groupedContracts.value).flat();
});

const canceledContracts = computed(() => {
    let list = (props.contracts || []).filter(c => c.status === 'canceled' || c.status === 'cancelled');
    if (canceledSearchQuery.value) {
        const q = canceledSearchQuery.value.toLowerCase().trim();
        list = list.filter(c => {
            const clientName = `${c.client?.first_name || ''} ${c.client?.last_name || ''}`.toLowerCase();
            const clientPhone = (c.client?.phone || '').toLowerCase();
            const contractNo = (c.contract_number || '').toLowerCase();
            const bino = (c.unit?.floor?.building?.name || '').toLowerCase();
            return clientName.includes(q) || clientPhone.includes(q) || contractNo.includes(q) || bino.includes(q);
        });
    }
    return list;
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
    first_name: '',
    last_name: '',
    middle_name: '',
    passport_series: '',
    passport_issued_date: '',
    passport_issued_by: '',
    phone: '',
    phone_2: '+998',
    email: '',
    region: '',
    district: '',
    address: ''
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
        first_name: lead.first_name || lead.full_name || '',
        last_name: lead.last_name || '',
        middle_name: lead.middle_name || '',
        passport_series: lead.passport_series || '',
        passport_issued_date: lead.passport_issued_date || '',
        passport_issued_by: lead.passport_issued_by || '',
        phone: lead.phone || '',
        phone_2: lead.phone_2 || '+998',
        email: lead.email || '',
        region: lead.region || '',
        district: lead.district || '',
        address: lead.address || ''
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
    axios.put(route('admin.crm.leads.update', selectedLeadForDetail.value.id), { preserveScroll: true, preserveState: true,
        first_name: clientInfoForm.value.first_name,
        last_name: clientInfoForm.value.last_name,
        middle_name: clientInfoForm.value.middle_name,
        passport_series: clientInfoForm.value.passport_series,
        passport_issued_date: clientInfoForm.value.passport_issued_date,
        passport_issued_by: clientInfoForm.value.passport_issued_by,
        phone: clientInfoForm.value.phone,
        phone_2: clientInfoForm.value.phone_2,
        email: clientInfoForm.value.email,
        region: clientInfoForm.value.region,
        district: clientInfoForm.value.district,
        address: clientInfoForm.value.address,
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
    axios.put(route('admin.crm.leads.update', selectedLeadForDetail.value.id), { preserveScroll: true, preserveState: true,
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

function openContractModalForLead() {
    showCallPanelModal.value = false;
    isEditingContract.value = false;
    currentContractId.value = null;
    
    // Clear and prepare contract form
    contractForm.value = { 
        buyer_type: 'client', 
        partner_id: '', 
        client_id: '', 
        unit_id: '', 
        contract_number: '', 
        contract_type: 'residential', 
        price_per_sqm: '', 
        total_amount: '', 
        down_payment: '', 
        term_months: 36, 
        payment_type: 'installment', 
        created_at: new Date().toISOString().split('T')[0], 
        actual_paid_amount: '', 
        payment_method: 'bank', 
        account_id: '', 
        floor: '', 
        rooms: '', 
        user_id: '', 
        contract_template_id: '', 
        selected_variants: { custom_placeholders: {} } 
    };
    
    showCreateContractModal.value = true;
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

// Refund schedule generation state
const showRefundGeneratorForm = ref(false);
const refundInstallmentCount = ref(3);
const refundInstallments = ref([]);

const contractIncomeSum = computed(() => {
    if (!selectedContract.value || !selectedContract.value.transactions) return 0;
    return selectedContract.value.transactions
        .filter(t => t.type === 'income' && t.status === 'approved')
        .reduce((sum, t) => sum + Number(t.amount || 0), 0);
});

function distributeRefundInstallments() {
    const totalToRefund = contractIncomeSum.value - Number(selectedContract.value.refunded_amount || 0);
    if (totalToRefund <= 0) {
        alert('Mijozga qaytariladigan ortiqcha mablag\' mavjud emas.');
        return;
    }
    const count = Number(refundInstallmentCount.value) || 1;
    const splitAmount = Math.round(totalToRefund / count);
    
    refundInstallments.value = [];
    for (let i = 1; i <= count; i++) {
        const d = new Date();
        d.setMonth(d.getMonth() + i);
        const dateStr = d.toISOString().split('T')[0];
        
        // Handle remainder in the last installment
        const amt = i === count ? (totalToRefund - (splitAmount * (count - 1))) : splitAmount;

        refundInstallments.value.push({
            amount: amt,
            due_date: dateStr,
            notes: `${i}-bosqich qaytarish`
        });
    }
}

function submitRefundSchedule() {
    if (refundInstallments.value.length === 0) {
        alert('Iltimos, grafikni taqsimlang.');
        return;
    }
    const totalSum = refundInstallments.value.reduce((sum, inst) => sum + Number(inst.amount), 0);
    const expectedTotal = contractIncomeSum.value - Number(selectedContract.value.refunded_amount || 0);
    if (Math.abs(totalSum - expectedTotal) > 1) {
        if (!confirm(`Taqsimlangan summa (${totalSum.toLocaleString()} so'm) qaytarilishi kerak bo'lgan summadan (${expectedTotal.toLocaleString()} so'm) farq qiladi. Davom etamizmi?`)) {
            return;
        }
    }

    isProcessing.value = true;
    router.post(route('admin.contracts.refund_schedules.store', selectedContract.value.id), { preserveScroll: true, preserveState: true,
        installments: refundInstallments.value
    }, {
        onSuccess: () => {
            isProcessing.value = false;
            showRefundGeneratorForm.value = false;
            if (selectedClientForDetail.value) {
                selectClientForDetail(selectedClientForDetail.value);
            }
        },
        onError: (err) => {
            isProcessing.value = false;
            alert('Grafikni saqlashda xatolik.');
        }
    });
}

const refundTransactions = computed(() => {
    if (!selectedContract.value || !selectedContract.value.transactions) return [];
    return selectedContract.value.transactions.filter(t => 
        t.category && (t.category.name === 'Mijozga pulni qaytarish' || t.category.name === 'Mijozga to\'lov qilish')
    );
});

const clientPayments = computed(() => {
    if (!selectedContract.value || !selectedContract.value.transactions) return [];
    return selectedContract.value.transactions.filter(t => 
        t.type === 'income' && t.status === 'approved'
    );
});

function computeLedgerRows(schedulesToProcess, startingBalance, paymentsToProcess) {
    let running_balance = startingBalance;
    const firstDueDate = schedulesToProcess.length > 0 ? new Date(schedulesToProcess[0].due_date) : null;
    
    const prePayments = [];
    const remainingPayments = [];
    
    paymentsToProcess.forEach(p => {
        const pDate = new Date(p.transaction_date || p.created_at);
        if (firstDueDate && pDate < firstDueDate) {
            prePayments.push(p);
        } else {
            remainingPayments.push(p);
        }
    });
    
    prePayments.forEach(p => {
        running_balance -= Number(p.amount || 0);
    });
    
    const rows = [];
    
    for (let i = 0; i < schedulesToProcess.length; i++) {
        const s = schedulesToProcess[i];
        const currentDueDate = new Date(s.due_date);
        const nextDueDate = (i < schedulesToProcess.length - 1) ? new Date(schedulesToProcess[i+1].due_date) : null;
        
        running_balance += Number(s.expected_amount || 0);
        
        const periodPayments = [];
        const nextRemaining = [];
        
        remainingPayments.forEach(p => {
            const pDate = new Date(p.transaction_date || p.created_at);
            const isAfterOrEqualCurrent = pDate >= currentDueDate;
            const isBeforeNext = nextDueDate ? (pDate < nextDueDate) : true;
            
            if (isAfterOrEqualCurrent && isBeforeNext) {
                periodPayments.push(p);
            } else {
                nextRemaining.push(p);
            }
        });
        
        const periodPaidSum = periodPayments.reduce((sum, p) => sum + Number(p.amount || 0), 0);
        running_balance -= periodPaidSum;
        
        const periodDates = [];
        const periodMethods = [];
        periodPayments.forEach(p => {
            const dateObj = new Date(p.transaction_date || p.created_at);
            const dStr = String(dateObj.getDate()).padStart(2, '0') + '.' + 
                         String(dateObj.getMonth() + 1).padStart(2, '0') + '.' + 
                         dateObj.getFullYear();
            if (!periodDates.includes(dStr)) periodDates.push(dStr);
            
            const method = p.payment_method || 'naqd';
            if (!periodMethods.includes(method)) periodMethods.push(method);
        });
        
        let status = 'pending';
        if (running_balance <= 0) {
            status = 'paid';
        } else if (periodPaidSum > 0) {
            status = 'partial';
        } else if (currentDueDate < new Date()) {
            status = 'overdue';
        }
        
        rows.push({
            id: s.id,
            due_date: s.due_date,
            expected_amount: s.expected_amount,
            actual_payment_date: periodDates.join(', ') || null,
            original_payment_amount: periodPayments.length > 0 ? periodPayments[0].amount : 0,
            paid_amount: periodPaidSum,
            payment_method: periodMethods.join(', ') || null,
            remaining_debt: running_balance,
            penalty_for_this_month: s.penalty_for_this_month || 0,
            status: status
        });
        
        remainingPayments.length = 0;
        nextRemaining.forEach(p => remainingPayments.push(p));
    }
    
    return { rows, remainingPayments };
}

const splitLedgerData = computed(() => {
    if (!selectedContract.value || !selectedContract.value.payment_schedules) {
        return { downPaymentRows: [], installmentRows: [], isSplit: false };
    }
    
    // Sort schedules by due_date
    const allSchedules = [...selectedContract.value.payment_schedules].sort((a, b) => 
        new Date(a.due_date) - new Date(b.due_date)
    );
    
    const payments = [...clientPayments.value].sort((a, b) => 
        new Date(a.transaction_date || a.created_at) - new Date(b.transaction_date || b.created_at)
    );
    
    const dpSchedules = allSchedules.filter(s => s.payment_type === 'down_payment');
    const instSchedules = allSchedules.filter(s => s.payment_type !== 'down_payment');
    
    const hasDownPayment = dpSchedules.length > 0;
    
    if (hasDownPayment) {
        const dpResult = computeLedgerRows(dpSchedules, 0, payments);
        const instResult = computeLedgerRows(instSchedules, 0, dpResult.remainingPayments);
        
        return {
            downPaymentRows: dpResult.rows,
            installmentRows: instResult.rows,
            isSplit: true
        };
    } else {
        const result = computeLedgerRows(allSchedules, 0, payments);
        return {
            downPaymentRows: [],
            installmentRows: result.rows,
            isSplit: false
        };
    }
});

const calculatedSchedules = computed(() => {
    if (!splitLedgerData.value.isSplit) {
        return splitLedgerData.value.installmentRows;
    }
    return [...splitLedgerData.value.downPaymentRows, ...splitLedgerData.value.installmentRows];
});

function openScheduleModal(contract) {
    if (contract.client) {
        selectClientForDetail(contract.client, true);
    } else {
        selectedContract.value = JSON.parse(JSON.stringify(contract));
        showModal.value = true;
    }
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

const showDocPreviewModal = ref(false);
const docPreviewTitle = ref('');
const docPreviewUrl = ref('');
const docDownloadUrl = ref('');
const docDownloadDocxUrl = ref('');
const docPreviewLoading = ref(false);

function openDocumentPreview(type, contract) {
    if (!contract) return;
    docPreviewLoading.value = true;
    if (type === 'contract') {
        docPreviewTitle.value = 'Shartnoma';
        docPreviewUrl.value = route('admin.contracts.document.preview', contract.id) + '?lang=' + locale.value;
        docDownloadUrl.value = route('admin.contracts.document.export.pdf', contract.id) + '?lang=' + locale.value;
        docDownloadDocxUrl.value = route('admin.contracts.document.export.docx', contract.id) + '?lang=' + locale.value;
    } else {
        docPreviewTitle.value = 'To\'lov Grafiki';
        docPreviewUrl.value = route('admin.contracts.ledger.preview', contract.id);
        docDownloadUrl.value = route('admin.contracts.export.xlsx', contract.id);
        docDownloadDocxUrl.value = '';
    }
    showDocPreviewModal.value = true;
}

const fileInput = ref(null);
const detailFileInput = ref(null);
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

    axios.post(`/admin/contracts/${selectedContract.value.id}/upload-signature`, formData, { preserveScroll: true, preserveState: true,
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
        
        if (selectedClientForDetail.value) {
            selectClientForDetail(selectedClientForDetail.value);
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
    axios.post('/telephony/call', { preserveScroll: true, preserveState: true,
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

    axios.post(route('admin.telephony.audios.upload'), formData, { preserveScroll: true, preserveState: true,
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
    
    axios.post('/telephony/call', { preserveScroll: true, preserveState: true, phone_number: dialerPhone.value })
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

    axios.post(route('admin.telephony.campaigns.store'), formData, { preserveScroll: true, preserveState: true,
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
    eskizSettingsForm.post(route('admin.ai_agents.settings'), { preserveScroll: true, preserveState: true,
        onSuccess: () => {
            showEskizSettingsModal.value = false;
            alert('Eskiz.uz sozlamalari muvaffaqiyatli saqlandi!');
        }
    });
}

function submitTestSms() {
    testSmsForm.post(route('admin.ai_agents.test_sms'), { preserveScroll: true, preserveState: true,
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
        router.delete(route('admin.ai_agents.template.delete', id), { preserveScroll: true, preserveState: true,
            onSuccess: () => alert('Shablon o\'chirildi.')
        });
    }
}

function submitTemplate() {
    templateForm.post(route('admin.ai_agents.template.save'), { preserveScroll: true, preserveState: true,
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
        router.delete(route('admin.ai_agents.rule.delete', id), { preserveScroll: true, preserveState: true,
            onSuccess: () => alert('Qoida o\'chirildi.')
        });
    }
}

function submitRule() {
    ruleForm.post(route('admin.ai_agents.rule.save'), { preserveScroll: true, preserveState: true,
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
    promoRuleForm.post(route('admin.ai_agents.rule.save'), { preserveScroll: true, preserveState: true,
        onSuccess: () => {
            showPromoModal.value = false;
            promoRuleForm.reset();
            alert('Ommaviy rasilka saqlandi!');
        }
    });
}

// ==========================================
// NEW CLIENT PAYMENTS, DEBTS & LEDGERS MODULE
// ==========================================
const paymentsSubTab = ref('payments');
const clientPaymentsSearch = ref('');
const clientDebtsSearch = ref('');

const filteredClientPayments = computed(() => {
    const txns = (props.transactions || []).filter(t => t.counterparty?.type === 'client' && t.status === 'approved');
    if (!clientPaymentsSearch.value) return txns;
    const q = clientPaymentsSearch.value.toLowerCase();
    return txns.filter(t => 
        (t.counterparty?.name || '').toLowerCase().includes(q) ||
        (t.notes || '').toLowerCase().includes(q)
    );
});

const filteredClientDebts = computed(() => {
    const debts = (props.counterparties || [])
        .filter(c => c.type === 'client' && Number(c.balance || 0) !== 0)
        .map(c => {
            const clientContracts = c.customable?.contracts || [];
            const agreed = clientContracts.reduce((sum, ct) => sum + Number(ct.total_amount || 0), 0);
            
            const paid = (c.transactions || [])
                .filter(t => t.type === 'income' && t.status === 'approved')
                .reduce((sum, t) => sum + Number(t.amount || 0), 0);

            return {
                ...c,
                agreed_amount: agreed,
                paid_amount: paid,
                debt_amount: Math.abs(Number(c.balance))
            };
        });
    if (!clientDebtsSearch.value) return debts;
    const q = clientDebtsSearch.value.toLowerCase();
    return debts.filter(c => 
        (c.name || '').toLowerCase().includes(q) ||
        (c.phone || '').toLowerCase().includes(q)
    );
});

const clientsWithContracts = computed(() => {
    const allClients = props.clients || [];
    return allClients.filter(c => c.contracts && c.contracts.length > 0);
});

const counterpartiesWithContracts = computed(() => {
    return (props.counterparties || []).filter(cp => {
        if (cp.type !== 'client') return false;
        const clientContracts = cp.customable?.contracts || [];
        return clientContracts.length > 0;
    });
});

const selectedCounterpartyContracts = computed(() => {
    const cpId = transactionForm.value.counterparty_id;
    if (!cpId) return [];
    const cp = props.counterparties?.find(c => c.id == cpId);
    return cp?.customable?.contracts || [];
});

const selectedContractObject = computed(() => {
    if (!transactionForm.value.contract_id) return null;
    
    if (detailedClientData.value && detailedClientData.value.contracts) {
        const found = detailedClientData.value.contracts.find(c => c.id == transactionForm.value.contract_id);
        if (found) return found;
    }
    
    if (props.contracts) {
        const found = props.contracts.find(c => c.id == transactionForm.value.contract_id);
        if (found) return found;
    }

    if (selectedCounterpartyContracts.value) {
        const found = selectedCounterpartyContracts.value.find(c => c.id == transactionForm.value.contract_id);
        if (found) return found;
    }

    return null;
});

const overpaidAmount = computed(() => {
    if (!selectedContractObject.value) return 0;
    const financials = getContractFinancials(selectedContractObject.value);
    const amount = Number(transactionForm.value.amount || 0);
    return Math.max(0, amount - financials.debt);
});

const showQuickTransactionModal = ref(false);
const showEditTransactionModal = ref(false);
const showReceiptModal = ref(false);
const receiptData = ref(null);

const transactionForm = ref({
    type: 'income',
    category_name: "Mijoz To'lovi (Shartnoma)",
    amount: '',
    currency: 'UZS',
    payment_method: 'cash',
    account_id: '',
    overpayment_account_id: '',
    counterparty_id: '',
    contract_id: '',
    client_id: '',
    transaction_date: new Date().toISOString().split('T')[0],
    notes: '',
    auto_approve: true,
});

const editTransactionForm = ref({
    id: null,
    amount: '',
    notes: '',
});

function openQuickTransaction(type = 'income', client = null) {
    transactionForm.value = {
        type: type,
        category_name: type === 'expense' ? "Mijozga pulni qaytarish" : "Mijoz To'lovi (Shartnoma)",
        amount: '',
        currency: 'UZS',
        payment_method: 'cash',
        account_id: props.cashRegisters && props.cashRegisters.length > 0 ? props.cashRegisters[0].id : '',
        overpayment_account_id: '',
        counterparty_id: '',
        contract_id: '',
        client_id: '',
        transaction_date: new Date().toISOString().split('T')[0],
        notes: '',
        auto_approve: true,
    };
    
    if (client) {
        const cp = props.counterparties?.find(c => c.customable_id == client.id && c.customable_type?.includes('Client'));
        if (cp) {
            transactionForm.value.counterparty_id = cp.id;
            transactionForm.value.client_id = client.id;
            const contracts = cp.customable?.contracts || [];
            if (contracts.length > 0) {
                transactionForm.value.contract_id = contracts[0].id;
            }
        }
    }
    showQuickTransactionModal.value = true;
}

watch(() => transactionForm.value.counterparty_id, (newCpId) => {
    if (!newCpId) {
        transactionForm.value.client_id = '';
        transactionForm.value.contract_id = '';
        return;
    }
    const cp = props.counterparties?.find(c => c.id == newCpId);
    if (cp && cp.customable_id) {
        transactionForm.value.client_id = cp.customable_id;
        const contracts = cp.customable?.contracts || [];
        if (contracts.length > 0) {
            transactionForm.value.contract_id = contracts[0].id;
        } else {
            transactionForm.value.contract_id = '';
        }
    }
});

function submitQuickTransaction() {
    isProcessing.value = true;
    router.post(route('admin.finance.transactions.store'), transactionForm.value, { preserveScroll: true, preserveState: true,
        onSuccess: () => {
            isProcessing.value = false;
            showQuickTransactionModal.value = false;
        },
        onError: () => {
            isProcessing.value = false;
        }
    });
}

function openEditTransactionModal(txn) {
    editTransactionForm.value = {
        id: txn.id,
        amount: txn.amount,
        notes: txn.notes || ''
    };
    showEditTransactionModal.value = true;
}

function submitEditTransaction() {
    isProcessing.value = true;
    router.put(route('admin.finance.transactions.update', editTransactionForm.value.id), editTransactionForm.value, { preserveScroll: true, preserveState: true,
        onSuccess: () => {
            isProcessing.value = false;
            showEditTransactionModal.value = false;
        },
        onError: () => {
            isProcessing.value = false;
        }
    });
}

function deleteTransaction(id) {
    if (confirm("Ushbu tranzaksiyani o'chirishni xohlaysizmi?")) {
        isProcessing.value = true;
        router.delete(route('admin.finance.transactions.destroy', id), { preserveScroll: true, preserveState: true,
            onSuccess: () => {
                isProcessing.value = false;
            },
            onError: () => {
                isProcessing.value = false;
            }
        });
    }
}

function openReceipt(transaction) {
    receiptData.value = transaction;
    showReceiptModal.value = true;
}

const showCounterpartyModal = ref(false);
const isEditCounterparty = ref(false);
const counterpartyForm = ref({
    id: null,
    name: '',
    type: 'client',
    phone: '',
    balance: 0,
});

function openEditCounterpartyModal(c) {
    isEditCounterparty.value = true;
    counterpartyForm.value = {
        id: c.id,
        name: c.name,
        type: c.type || 'client',
        phone: c.phone || '',
        balance: c.balance || 0,
    };
    showCounterpartyModal.value = true;
}

function submitCounterparty() {
    isProcessing.value = true;
    router.put(route('admin.finance.counterparties.update', counterpartyForm.value.id), counterpartyForm.value, { preserveScroll: true, preserveState: true,
        onSuccess: () => {
            isProcessing.value = false;
            showCounterpartyModal.value = false;
        },
        onError: () => {
            isProcessing.value = false;
        }
    });
}

function deleteCounterparty(id) {
    if (confirm("Ushbu kontragentni o'chirishni tasdiqlaysizmi?")) {
        isProcessing.value = true;
        router.delete(route('admin.finance.counterparties.destroy', id), { preserveScroll: true, preserveState: true,
            onSuccess: () => {
                isProcessing.value = false;
            },
            onError: () => {
                isProcessing.value = false;
            }
        });
    }
}

const selectedReportContractId = ref('');
const reportSearchQuery = ref('');
const reportSelectedBuildingId = ref('');
const reportSelectedBlock = ref('');

const uniqueBuildings = computed(() => {
    const buildingsMap = {};
    props.contracts?.forEach(c => {
        const b = c.unit?.floor?.building;
        if (b && b.id) {
            buildingsMap[b.id] = b.name;
        }
    });
    return Object.entries(buildingsMap).map(([id, name]) => ({ id: parseInt(id), name }));
});

const uniqueBlocks = computed(() => {
    const blocks = new Set();
    props.contracts?.forEach(c => {
        const block = c.unit?.floor?.building?.block_name;
        if (block) {
            blocks.add(block);
        }
    });
    return [...blocks].sort();
});

const filteredReportContracts = computed(() => {
    if (!props.contracts) return [];
    
    return props.contracts.filter(c => {
        // 1. Text search
        if (reportSearchQuery.value) {
            const query = reportSearchQuery.value.toLowerCase().trim();
            const clientName = `${c.client?.last_name || ''} ${c.client?.first_name || ''} ${c.client?.middle_name || ''}`.toLowerCase();
            const phone = (c.client?.phone || '').toLowerCase();
            const contractNumber = String(c.contract_number || c.id).toLowerCase();
            const unitNumber = String(c.unit?.unit_number || '').toLowerCase();
            
            const matchText = clientName.includes(query) || 
                              phone.includes(query) || 
                              contractNumber.includes(query) || 
                              unitNumber.includes(query);
                              
            if (!matchText) return false;
        }
        
        // 2. Building filter
        if (reportSelectedBuildingId.value) {
            if (c.unit?.floor?.building?.id !== parseInt(reportSelectedBuildingId.value)) {
                return false;
            }
        }
        
        // 3. Block filter
        if (reportSelectedBlock.value) {
            if (c.unit?.floor?.building?.block_name !== reportSelectedBlock.value) {
                return false;
            }
        }
        
        return true;
    });
});

const clearReportFilters = () => {
    reportSearchQuery.value = '';
    reportSelectedBuildingId.value = '';
    reportSelectedBlock.value = '';
};

const selectedReportContract = computed(() => {
    if (!selectedReportContractId.value) return null;
    return props.contracts?.find(c => c.id == selectedReportContractId.value);
});

const ledgerReportData = computed(() => {
    const contract = selectedReportContract.value;
    if (!contract) return null;
    
    const schedules = [...(contract.payment_schedules || contract.paymentSchedules || [])]
        .sort((a, b) => new Date(a.due_date) - new Date(b.due_date));
    
    const approvedTxns = (contract.transactions || []).filter(t => t.status === 'approved');
    const incomes = approvedTxns.filter(t => t.type === 'income').sort((a, b) => new Date(a.transaction_date || a.created_at) - new Date(b.transaction_date || b.created_at));
    const refunds = approvedTxns.filter(t => t.type === 'expense' && t.category && (t.category.name.includes('qaytarish') || t.category.name.includes('to\'lov qilish'))).sort((a, b) => new Date(a.transaction_date || a.created_at) - new Date(b.transaction_date || b.created_at));
    
    const firstDueDate = schedules[0] ? new Date(schedules[0].due_date) : null;
    
    const preTransactions = [];
    const activePayments = [];
    
    incomes.forEach(t => {
        const tDate = new Date(t.transaction_date || t.created_at);
        if (firstDueDate && tDate < firstDueDate) {
            preTransactions.push(t);
        } else {
            activePayments.push(t);
        }
    });
    
    const rows = [];
    let running_balance = 0;
    let totalPaidAll = 0;
    let totalRefundedAll = 0;
    
    preTransactions.forEach(t => {
        const amount = Number(t.amount);
        rows.push({
            is_down_payment: true,
            due_date_label: "Boshlang'ich to'lov",
            expected_amount: null,
            paid_date: new Date(t.transaction_date || t.created_at).toLocaleDateString(),
            paid_amount: amount,
            payment_method: t.payment_method || 'Naqd',
            refund_date: null,
            refund_amount: null,
            qoldiq: null
        });
        totalPaidAll += amount;
    });
    
    schedules.forEach((schedule, idx) => {
        let schedulePayments = activePayments.filter(p => p.payment_schedule_id == schedule.id);
        
        if (schedulePayments.length === 0) {
            const currentDueDate = new Date(schedule.due_date);
            const nextSchedule = schedules[idx + 1];
            const nextDueDate = nextSchedule ? new Date(nextSchedule.due_date) : null;
            
            schedulePayments = activePayments.filter(p => {
                const tDate = new Date(p.transaction_date || p.created_at);
                const fiveDaysBefore = new Date(currentDueDate);
                fiveDaysBefore.setDate(fiveDaysBefore.getDate() - 5);
                
                let isAfterFiveDaysBefore = tDate >= fiveDaysBefore;
                let isBeforeNext = true;
                if (nextDueDate) {
                    const nextFiveDaysBefore = new Date(nextDueDate);
                    nextFiveDaysBefore.setDate(nextFiveDaysBefore.getDate() - 5);
                    isBeforeNext = tDate < nextFiveDaysBefore;
                }
                return isAfterFiveDaysBefore && isBeforeNext;
            });
        }
        
        const currentDueDate = new Date(schedule.due_date);
        const nextSchedule = schedules[idx + 1];
        const nextDueDate = nextSchedule ? new Date(nextSchedule.due_date) : null;
        
        const scheduleRefunds = refunds.filter(r => {
            const rDate = new Date(r.transaction_date || r.created_at);
            const startOfMonth = new Date(currentDueDate.getFullYear(), currentDueDate.getMonth(), 1);
            let isAfterStart = rDate >= startOfMonth;
            let isBeforeNextMonth = true;
            if (nextDueDate) {
                const nextStartOfMonth = new Date(nextDueDate.getFullYear(), nextDueDate.getMonth(), 1);
                isBeforeNextMonth = rDate < nextStartOfMonth;
            }
            return isAfterStart && isBeforeNextMonth;
        });
        
        const paid_amount = schedulePayments.reduce((sum, p) => sum + Number(p.amount), 0);
        const refunded_amount = scheduleRefunds.reduce((sum, r) => sum + Number(r.amount), 0);
        
        const paid_date = schedulePayments.length > 0 ? new Date(schedulePayments[0].transaction_date || schedulePayments[0].created_at).toLocaleDateString() : '';
        
        let methodLabel = '';
        if (schedulePayments.length > 0) {
            const m = (schedulePayments[0].payment_method || '').toLowerCase();
            if (m === 'naqd' || m === 'cash') methodLabel = 'Naqt';
            else if (m === 'plastik' || m === 'card' || m === 'karta') methodLabel = 'Plastik';
            else if (m === 'bank') methodLabel = 'Bank';
            else methodLabel = schedulePayments[0].payment_method || '';
        }
        
        const refund_date = scheduleRefunds.length > 0 ? new Date(scheduleRefunds[0].transaction_date || scheduleRefunds[0].created_at).toLocaleDateString() : '';
        
        running_balance += (paid_amount - Number(schedule.expected_amount) - refunded_amount);
        
        rows.push({
            is_down_payment: false,
            due_date_label: new Date(schedule.due_date).toLocaleDateString(),
            expected_amount: Number(schedule.expected_amount),
            paid_date: paid_date,
            paid_amount: paid_amount > 0 ? paid_amount : null,
            payment_method: methodLabel,
            refund_date: refund_date || null,
            refund_amount: refunded_amount > 0 ? refunded_amount : null,
            qoldiq: running_balance
        });
        
        totalPaidAll += paid_amount;
        totalRefundedAll += refunded_amount;
    });
    
    return {
        rows,
        totalPaidAll,
        totalRefundedAll,
        remainingDebt: Number(contract.total_amount) - (totalPaidAll - totalRefundedAll) + Number(contract.total_penalty_accumulated || 0)
    };
});
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

        <div v-if="!showCreateClientModal && !showCreateContractModal && !showCreateLeadModal && !showDetailedLeadModal">
            <!-- TAB 1: LEADS KANBAN / LIST VIEW -->
        <div v-if="activeTab === 'leads'" class="space-y-4">
            <!-- View Mode Switcher & Actions -->
            <div class="flex flex-col md:flex-row md:items-center justify-between bg-white border border-gray-200 shadow-sm rounded-xl px-4 py-3 gap-4">
                <!-- Left controls: Switcher + Search -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 flex-1">
                    <!-- Toggle Button Group -->
                    <div class="flex items-center gap-1.5 bg-gray-100 p-1 rounded-lg self-start sm:self-auto">
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

                    <!-- Search Input -->
                    <div class="relative flex-1 max-w-xs">
                        <span class="absolute left-3 top-2.5 text-gray-400 text-xs">🔍</span>
                        <input v-model="leadsSearchQuery" type="text" placeholder="Lidlarni qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl pl-8 pr-3 py-2 text-xs w-full focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                    </div>

                    <!-- Source Filter -->
                    <select v-model="leadsSourceFilter" class="bg-white border border-gray-300 text-gray-950 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                        <option value="">Barcha manbalar</option>
                        <option value="telegram">Telegram Bot</option>
                        <option value="partner">Hamkor Ilova</option>
                        <option value="manual">Qo'lda qo'shilgan</option>
                    </select>
                </div>

                <!-- Action buttons -->
                <div class="flex items-center gap-2 self-end md:self-auto">
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
                        @click="openDetailedLeadModal"
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
                            <tr v-for="lead in filteredLeads" :key="lead.id" class="hover:bg-gray-50/30 transition-colors">
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
                            <tr v-if="filteredLeads.length === 0">
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
        <div v-if="['clients', 'clients_new', 'clients_active', 'clients_debtors', 'clients_blacklist', 'contracts'].includes(activeTab)" class="bg-[#f8fafc] p-6 rounded-2xl min-h-[500px]">
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
                <span class="text-gray-900 font-bold">{{ activeTab === 'contracts' ? 'Shartnomalar' : 'Mijozlar' }}</span>
            </div>

            <!-- Contracts section inside Client Database Roster -->
            <div v-if="activeTab === 'contracts'" class="space-y-6">
                <!-- Control bar matching user design -->
                <div class="flex flex-col md:flex-row justify-between items-stretch md:items-center gap-4 mb-6 bg-white px-6 py-4 rounded-2xl border border-gray-150 shadow-sm">
                    <!-- Left side: Back Button + Title -->
                    <div class="flex items-center gap-3">
                        <button @click="activeTab = 'dashboard'" class="w-10 h-10 flex items-center justify-center rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 transition-colors shadow-sm" title="Orqaga">
                            <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                        <h2 class="text-xl font-bold text-gray-900 tracking-tight">Shartnomalar</h2>
                    </div>

                    <!-- Right side: Filters & Actions -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Search Box -->
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-gray-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input 
                                v-model="contractSearchQuery" 
                                type="text" 
                                placeholder="Qidirish" 
                                class="bg-white border border-gray-300 text-gray-800 text-xs rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-[#f2a900] w-48 transition-all"
                            />
                        </div>

                        <!-- Status Filter Dropdown -->
                        <div class="relative">
                            <select v-model="selectedContractStatusFilter" class="bg-white border border-gray-300 text-gray-700 text-xs rounded-lg pl-4 pr-8 py-2 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-[#f2a900] appearance-none cursor-pointer">
                                <option value="">Tasdiqlangan...</option>
                                <option value="approved">Tasdiqlangan</option>
                                <option value="pending">Kutilmoqda</option>
                                <option value="cancelled">Bekor qilingan</option>
                            </select>
                            <span class="absolute right-2.5 top-2.5 text-gray-400 pointer-events-none">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </span>
                        </div>

                        <!-- Filtr Button to toggle advanced filters -->
                        <button 
                            @click="showContractExtraFilters = !showContractExtraFilters" 
                            class="px-4 py-2 bg-white border text-gray-700 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 shadow-sm"
                            :class="showContractExtraFilters ? 'border-[#f2a900] text-[#f2a900] bg-amber-50/10' : 'border-gray-300 hover:bg-gray-50'"
                        >
                            <svg class="w-4 h-4 text-gray-500" :class="{ 'text-[#f2a900]': showContractExtraFilters }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 8.293A1 1 0 013 7.586V4z" />
                            </svg>
                            Filtr
                        </button>

                        <!-- Excel Export Button -->
                        <button @click="openExcelPreviewModal" class="px-4 py-2 bg-white border border-emerald-500 text-emerald-600 text-xs font-bold rounded-lg hover:bg-emerald-50 transition-all flex items-center gap-1.5 shadow-sm">
                            <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <path d="M14 2v6h6m-3 4l-4 4m0-4l4 4"/>
                            </svg>
                            Excel
                        </button>

                        <!-- Add Button -->
                        <button @click="showCreateContractModal = true" class="px-4 py-2 bg-white border border-[#f2a900] hover:bg-amber-50/50 text-[#f2a900] text-xs font-bold rounded-lg transition-all flex items-center gap-1 shadow-sm">
                            <span class="text-[#f2a900] font-bold text-sm leading-none">+</span> Qo'shish
                        </button>
                    </div>
                </div>

                <!-- Advanced Extra Filters Row (Toggled by showContractExtraFilters) -->
                <div v-if="showContractExtraFilters" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6 bg-gray-50/70 p-4 rounded-xl border border-gray-150 animate-[fadeIn_0.3s_ease-out]">
                    <!-- Obyektlar filter -->
                    <div class="relative">
                        <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">Obyekt bo'yicha filter</label>
                        <select v-model="selectedContractBuildingFilter" class="w-full bg-white border border-gray-300 text-gray-700 text-xs rounded-lg pl-4 pr-8 py-2 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-[#f2a900] appearance-none cursor-pointer">
                            <option value="">Barcha obyektlar</option>
                            <option v-for="b in availableBuildings" :key="b" :value="b">{{ b }}</option>
                        </select>
                        <span class="absolute right-2.5 top-8.5 text-gray-400 pointer-events-none">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </span>
                    </div>

                    <!-- Sotuv menejeri filter -->
                    <div class="relative">
                        <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">Menejer bo'yicha filter</label>
                        <select v-model="selectedContractManagerFilter" class="w-full bg-white border border-gray-300 text-gray-700 text-xs rounded-lg pl-4 pr-8 py-2 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-[#f2a900] appearance-none cursor-pointer">
                            <option value="">Barcha menejerlar</option>
                            <option v-for="manager in availableManagers" :key="manager" :value="manager">{{ manager }}</option>
                        </select>
                        <span class="absolute right-2.5 top-8.5 text-gray-400 pointer-events-none">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </span>
                    </div>
                </div>
                
                <div v-for="(contracts, blockName) in groupedContracts" :key="blockName" class="mb-8">
                    <h3 class="text-lg font-bold text-emerald-600 mb-4 uppercase tracking-widest flex items-center gap-2">
                        <span>🏢</span> {{ blockName }}
                    </h3>
                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                        <table class="w-full text-left border-collapse text-xs font-sans min-w-[1100px]">
                            <thead class="bg-[#f8fafc] border-b border-gray-200 text-gray-500 font-bold uppercase text-[9px] tracking-wider">
                                <tr>
                                    <th class="p-4 py-4">Shartnoma kodi</th>
                                    <th class="p-4 py-4">Sana</th>
                                    <th class="p-4 py-4">Xaridor</th>
                                    <th class="p-4 py-4">Obyekt / Xonadon</th>
                                    <th class="p-4 py-4">Maydoni & Narxi</th>
                                    <th class="p-4 py-4">Boshlang'ich to'lov</th>
                                    <th class="p-4 py-4">Jami Summa</th>
                                    <th class="p-4 py-4">To'langan (Holati)</th>
                                    <th class="p-4 py-4">Holat</th>
                                    <th class="p-4 py-4 text-right w-40">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-150">
                                <tr 
                                    v-for="contract in contracts" 
                                    :key="contract.id"
                                    @click="openScheduleModal(contract)"
                                    class="hover:bg-emerald-50/15 transition-colors cursor-pointer group bg-white border-b border-gray-100"
                                >
                                    <td class="p-4 py-4.5">
                                        <h3 class="text-xs font-bold text-gray-900 group-hover:text-emerald-600 transition-colors">
                                            {{ contract.contract_number || 'Contract #' + contract.id.toString().padStart(5, '0') }}
                                        </h3>
                                    </td>
                                    <td class="p-4 py-4.5 text-gray-600 font-mono whitespace-nowrap">
                                        {{ new Date(contract.created_at).toLocaleDateString('uz-UZ', { day: '2-digit', month: '2-digit', year: 'numeric' }) }}
                                    </td>
                                    <td class="p-4 py-4.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full overflow-hidden flex items-center justify-center font-display font-bold text-xs shadow-sm shrink-0 bg-gradient-to-br from-neon-green/20 to-black border border-emerald-200 text-emerald-600">
                                                 <img v-if="contract.client?.profile_photo" :src="'/storage/' + contract.client.profile_photo" class="w-full h-full object-cover" alt="Profile" />
                                                 <template v-else>
                                                     {{ getInitials(contract.client?.first_name, contract.client?.last_name) }}
                                                 </template>
                                             </div>
                                            <div>
                                                <p class="text-xs text-gray-900 font-bold leading-tight">{{ contract.client?.last_name }} {{ contract.client?.first_name }}</p>
                                                <div class="flex items-center gap-1 mt-0.5">
                                                    <span class="text-[9px] text-gray-500 font-mono">{{ contract.client?.phone }}</span>
                                                    <span v-if="contract.is_legacy" class="inline-flex items-center px-1 py-0.2 rounded text-[7px] font-bold bg-neon-purple/20 text-neon-purple border border-neon-purple/50 uppercase shadow-sm" title="{{ $t('imported_legacy') }}">{{ $t('imported') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 py-4.5">
                                        <h4 class="text-xs font-bold text-gray-900">
                                            {{ contract.unit?.unit_number || 'N/A' }}
                                        </h4>
                                        <p class="text-[9px] text-gray-500 font-medium mt-0.5">
                                            {{ contract.unit?.floor?.building?.name || 'Bino' }} • {{ contract.unit?.floor?.floor_number || contract.floor }}-qavat • {{ contract.unit?.rooms_count || contract.rooms || '—' }} xona
                                        </p>
                                    </td>
                                    <td class="p-4 py-4.5">
                                        <div class="text-xs font-bold text-gray-800">
                                            {{ contract.unit?.area_sqm || '—' }} m²
                                        </div>
                                        <div class="text-[9px] text-gray-500 font-mono mt-0.5">
                                            {{ $currency(contract.price_per_sqm) }} / m²
                                        </div>
                                    </td>
                                    <td class="p-4 py-4.5 text-xs font-mono text-gray-700">
                                        {{ $currency(contract.down_payment) }}
                                    </td>
                                    <td class="p-4 py-4.5 text-xs font-mono text-emerald-600 font-bold">
                                        {{ $currency(contract.total_amount) }}
                                        <div class="text-[9px] text-gray-500 mt-0.5 font-normal">
                                            <span v-if="contract.payment_type === 'full'" class="text-blue-600 font-bold">Bir martalik</span>
                                            <span v-else class="text-amber-600 font-bold">{{ contract.term_months }} oy (bo'lib to'lash)</span>
                                        </div>
                                    </td>
                                    <td class="p-4 py-4.5">
                                        <div class="flex flex-col gap-1 w-32">
                                            <div class="flex justify-between items-center text-[10px]">
                                                <span class="font-mono text-gray-800 font-bold">{{ $currency(calculatePaidAmount(contract)) }}</span>
                                                <span class="text-emerald-600 font-bold">{{ calculateProgress(contract).toFixed(0) }}%</span>
                                            </div>
                                            <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden relative">
                                                <div 
                                                    class="absolute top-0 left-0 bottom-0 bg-emerald-600 shadow-sm transition-all duration-1000"
                                                    :style="{ width: calculateProgress(contract) + '%' }"
                                                ></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 py-4.5">
                                        <span 
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border shadow-sm"
                                            :class="{
                                                'bg-emerald-50 text-emerald-700 border-emerald-200': contract.status === 'active' || contract.status === 'approved',
                                                'bg-red-50 text-red-700 border-red-200': contract.status === 'cancelled' || contract.status === 'canceled',
                                                'bg-amber-50 text-amber-700 border-amber-200': contract.status === 'pending' || contract.status === 'draft',
                                            }"
                                        >
                                            {{ contract.status === 'active' || contract.status === 'approved' ? 'Faol' : (contract.status === 'cancelled' || contract.status === 'canceled' ? 'Bekor qilingan' : contract.status) }}
                                        </span>
                                    </td>
                                    <td class="p-4 py-4.5 text-right" @click.stop>
                                        <div class="flex items-center justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                            <button @click="cancelContract(contract.id)" class="w-8 h-8 rounded-full bg-orange-50 border border-orange-200 text-orange-600 flex items-center justify-center hover:bg-orange-500 hover:text-white transition-colors shadow-sm" title="Bekor qilish">
                                                🚫
                                            </button>
                                            <button @click="editContract(contract)" class="w-8 h-8 rounded-full bg-blue-50 border border-blue-200 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-colors shadow-sm" title="Tahrirlash">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h12a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </button>
                                            <button v-if="isAdmin" @click="deleteContract(contract.id)" class="w-8 h-8 rounded-full bg-red-50 border border-red-200 text-red-600 flex items-center justify-center hover:bg-red-600 hover:text-white transition-colors shadow-sm" title="O'chirish">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div v-if="Object.keys(groupedContracts).length === 0" class="text-center py-20 text-gray-500 font-mono text-sm border border-dashed border-gray-200 rounded-2xl bg-white">
                    {{ $t('no_active_contracts') }}
                </div>
            </div>

            <!-- Clients view when activeTab is not contracts -->
            <div v-else>
                <!-- Control bar matching user design -->
                <div class="flex flex-col md:flex-row justify-between items-stretch md:items-center gap-4 mb-6 bg-white px-6 py-4 rounded-2xl border border-gray-150 shadow-sm">
                    <!-- Left side: Back Button + Title -->
                    <div class="flex items-center gap-3">
                        <button @click="activeTab = 'dashboard'" class="w-10 h-10 flex items-center justify-center rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 transition-colors shadow-sm" title="Orqaga">
                            <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                        <h2 class="text-xl font-bold text-gray-900 tracking-tight">Mijozlar</h2>
                    </div>

                    <!-- Right side: Filters & Actions -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Search Box -->
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
                                class="bg-white border border-gray-300 text-gray-800 text-xs rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-[#f2a900] w-48 transition-all"
                            />
                        </div>

                        <!-- Status Filter Dropdown -->
                        <div class="relative">
                            <select v-model="selectedStatusFilter" class="bg-white border border-gray-300 text-gray-700 text-xs rounded-lg pl-4 pr-8 py-2 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-[#f2a900] appearance-none cursor-pointer">
                                <option value="">Tasdiqlangan...</option>
                                <option value="approved">Tasdiqlangan</option>
                                <option value="pending">Kutilmoqda</option>
                                <option value="cancelled">Bekor qilingan</option>
                            </select>
                            <span class="absolute right-2.5 top-2.5 text-gray-400 pointer-events-none">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </span>
                        </div>

                        <!-- Filtr Button to toggle advanced filters -->
                        <button 
                            @click="showExtraFilters = !showExtraFilters" 
                            class="px-4 py-2 bg-white border text-gray-700 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 shadow-sm"
                            :class="showExtraFilters ? 'border-[#f2a900] text-[#f2a900] bg-amber-50/10' : 'border-gray-300 hover:bg-gray-50'"
                        >
                            <svg class="w-4 h-4 text-gray-500" :class="{ 'text-[#f2a900]': showExtraFilters }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 8.293A1 1 0 013 7.586V4z" />
                            </svg>
                            Filtr
                        </button>

                        <!-- Excel Export Button -->
                        <button @click="openExcelPreviewModal" class="px-4 py-2 bg-white border border-emerald-500 text-emerald-600 text-xs font-bold rounded-lg hover:bg-emerald-50 transition-all flex items-center gap-1.5 shadow-sm">
                            <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <path d="M14 2v6h6m-3 4l-4 4m0-4l4 4"/>
                            </svg>
                            Excel
                        </button>

                        <!-- Add Button -->
                        <button @click="openCreateClientModal" class="px-4 py-2 bg-white border border-[#f2a900] hover:bg-amber-50/50 text-[#f2a900] text-xs font-bold rounded-lg transition-all flex items-center gap-1 shadow-sm">
                            <span class="text-[#f2a900] font-bold text-sm leading-none">+</span> Qo'shish
                        </button>
                    </div>
                </div>

                <!-- Advanced Extra Filters Row (Toggled by showExtraFilters) -->
                <div v-if="showExtraFilters" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6 bg-gray-50/70 p-4 rounded-xl border border-gray-150 animate-[fadeIn_0.3s_ease-out]">
                    <!-- Obyektlar filter -->
                    <div class="relative">
                        <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">Obyekt bo'yicha filter</label>
                        <select v-model="selectedBuildingFilter" class="w-full bg-white border border-gray-300 text-gray-700 text-xs rounded-lg pl-4 pr-8 py-2 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-[#f2a900] appearance-none cursor-pointer">
                            <option value="">Barcha obyektlar</option>
                            <option v-for="b in availableBuildings" :key="b" :value="b">{{ b }}</option>
                        </select>
                        <span class="absolute right-2.5 top-8.5 text-gray-400 pointer-events-none">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </span>
                    </div>

                    <!-- Sotuv menejeri filter -->
                    <div class="relative">
                        <label class="block text-[10px] uppercase font-bold text-gray-500 mb-1">Menejer bo'yicha filter</label>
                        <select v-model="selectedManagerFilter" class="w-full bg-white border border-gray-300 text-gray-700 text-xs rounded-lg pl-4 pr-8 py-2 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-[#f2a900] appearance-none cursor-pointer">
                            <option value="">Barcha menejerlar</option>
                            <option v-for="manager in availableManagers" :key="manager" :value="manager">{{ manager }}</option>
                        </select>
                        <span class="absolute right-2.5 top-8.5 text-gray-400 pointer-events-none">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </span>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                    <table class="w-full text-left border-collapse text-xs font-sans whitespace-nowrap">
                        <thead>
                            <tr class="bg-[#f8fafc] border-b border-gray-200 text-gray-500 font-semibold uppercase text-[10px] tracking-wider">
                                <th class="p-4 w-12 text-center">ID</th>
                                <th class="p-4">Ism</th>
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
                                        <div class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center font-bold text-sm shadow-sm shrink-0 bg-[#fef9c3] text-[#ca8a04]">
                                             <img v-if="client.profile_photo" :src="'/storage/' + client.profile_photo" class="w-full h-full object-cover" alt="Profile" />
                                             <template v-else>
                                                 {{ getInitials(client.first_name, client.last_name) }}
                                             </template>
                                         </div>
                                        <div>
                                            <h3 class="text-sm font-bold text-gray-900 leading-tight">
                                                {{ client.last_name }} {{ client.first_name }}
                                            </h3>
                                            <div class="text-xs text-gray-500 mt-1 font-medium">{{ client.phone || 'N/A' }}</div>
                                        </div>
                                    </div>
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
                                        <button @click="selectClientForDetail(client, true)" class="text-blue-500 hover:text-blue-700 transition-colors" title="Mijoz haqida">
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
            </div>
            
            <!-- If client is selected, show detail view -->
            <div v-else class="space-y-6">
                <!-- Breadcrumbs -->
                <div class="flex items-center gap-2 text-xs text-gray-500 font-sans mb-6 font-medium">
                    <span @click="closeClientDetail" class="flex items-center gap-1 cursor-pointer hover:underline hover:text-gray-900 transition-colors">
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
                    <div class="flex items-center gap-3">
                        <!-- Square Back Button -->
                        <button @click="closeClientDetail" class="w-9 h-9 bg-gray-100 hover:bg-gray-200 text-gray-700 flex items-center justify-center rounded-xl transition-all shadow-sm">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <div>
                            <h1 class="text-xl font-bold text-gray-950 font-sans tracking-tight leading-tight">
                                {{ selectedClientForDetail.last_name }} {{ selectedClientForDetail.first_name }}
                            </h1>
                            <!-- Badges -->
                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                <span class="inline-flex items-center bg-blue-50 text-blue-600 px-2 py-0.5 rounded text-[10px] font-bold font-sans uppercase">
                                    Mijoz
                                </span>
                                <template v-if="detailedClientData && detailedClientData.contracts && detailedClientData.contracts.length > 0">
                                    <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-[10px] font-bold font-sans">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        Shartnoma #{{ detailedClientData.contracts[0].contract_number }}
                                    </span>
                                    <span v-if="detailedClientData.contracts[0].unit?.podyezd" class="inline-flex items-center gap-1 bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-[10px] font-bold font-sans">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                        {{ detailedClientData.contracts[0].unit.podyezd }}-podezd
                                    </span>
                                    <span v-if="detailedClientData.contracts[0].unit?.unit_number" class="inline-flex items-center gap-1 bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-[10px] font-bold font-sans">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                                        {{ detailedClientData.contracts[0].unit.unit_number }}-uy
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                    <!-- Right Actions -->
                    <div class="flex items-center gap-2" v-if="detailedClientData">
                        <!-- Tasdiqlash Button -->
                        <button v-if="selectedContract && selectedContract.status !== 'active'" @click="handleSotildiClick" :disabled="isProcessing" class="px-4 py-2 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1 disabled:opacity-50">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" /></svg>
                            Tasdiqlash
                        </button>
                        <!-- Bekor qilish Button -->
                        <button v-if="selectedContract && selectedContract.status !== 'cancelled'" @click="cancelContract(selectedContract.id)" :disabled="isProcessing" class="px-4 py-2 bg-[#ff4d4d] hover:bg-[#e60000] text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1 disabled:opacity-50">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                            Bekor qilish
                        </button>
                        <!-- Tahrirlash Button -->
                        <button @click="openEditClientModal(detailedClientData)" class="px-4 py-2 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                            Tahrirlash
                        </button>
                    </div>
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
                    <!-- Card 0: Financial Cards Row (Image 2 style) -->
                    <div v-if="detailedClientData.contracts && detailedClientData.contracts.length > 0" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <!-- Card 1: Uy narxi -->
                        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-between min-h-[90px]">
                            <div class="flex justify-between items-center text-xs font-semibold text-gray-500 mb-1">
                                <span>Uy narxi</span>
                                <span class="text-emerald-500 bg-emerald-50 w-5 h-5 rounded-full flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                </span>
                            </div>
                            <div class="text-lg font-bold text-emerald-600 font-sans tracking-tight">
                                {{ Number(getContractFinancials(detailedClientData.contracts[0]).total).toLocaleString('en-US') }} so'm
                            </div>
                        </div>

                        <!-- Card 2: Chegirma -->
                        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-between min-h-[90px]">
                            <div class="flex justify-between items-center text-xs font-semibold text-gray-500 mb-1">
                                <span>Chegirma</span>
                            </div>
                            <div class="text-lg font-bold text-amber-500 font-sans tracking-tight">
                                {{ Number(detailedClientData.contracts[0]?.discount || 0).toLocaleString('en-US') }} so'm
                            </div>
                        </div>

                        <!-- Card 3: To'langan -->
                        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-between min-h-[90px]">
                            <div class="flex justify-between items-center text-xs font-semibold text-gray-500 mb-1">
                                <span>To'langan</span>
                                <span class="text-xs font-bold text-gray-700 font-mono">{{ getContractFinancials(detailedClientData.contracts[0]).percent }}%</span>
                            </div>
                            <div class="text-lg font-bold text-amber-500 font-sans tracking-tight">
                                {{ Number(getContractFinancials(detailedClientData.contracts[0]).paid).toLocaleString('en-US') }} so'm
                            </div>
                            <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden mt-2">
                                <div class="bg-amber-500 h-full rounded-full transition-all duration-500" :style="`width: ${getContractFinancials(detailedClientData.contracts[0]).percent}%`"></div>
                            </div>
                        </div>

                        <!-- Card 4: Qarz yoki Qolgan -->
                        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-col justify-between min-h-[90px]">
                            <div class="flex justify-between items-center text-xs font-semibold text-gray-500 mb-1">
                                <span>Qarz yoki Qolgan</span>
                                <span class="text-red-500 bg-red-50 w-5 h-5 rounded-full flex items-center justify-center" v-if="getContractFinancials(detailedClientData.contracts[0]).debt > 0">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </span>
                            </div>
                            <div class="text-lg font-bold text-red-600 font-sans tracking-tight">
                                {{ Number(getContractFinancials(detailedClientData.contracts[0]).debt).toLocaleString('en-US') }} so'm
                            </div>
                        </div>
                    </div>
                    <!-- Card 1: Uy haqida ma'lumot -->
                    <!-- CASE 1: Client has contract -->
                    <div v-if="detailedClientData.contracts && detailedClientData.contracts.length > 0 && detailedClientData.contracts[0]?.unit">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Card 1: Mijoz ma'lumotlari -->
                            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm text-left">
                                <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                                    <span class="w-1.5 h-5 bg-[#f2a900] rounded-full"></span>
                                    Mijoz ma'lumotlari
                                </h3>
                                
                                <div class="space-y-3.5">
                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Telefon raqami:</span>
                                        <strong class="text-gray-900 font-mono text-xs">{{ detailedClientData.phone }}</strong>
                                    </div>

                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Telefon raqami 2:</span>
                                        <strong class="text-gray-900 font-mono text-xs">
                                            {{ detailedClientData.phone_2 && detailedClientData.phone_2 !== '+998' && detailedClientData.phone_2.trim() !== '' && detailedClientData.phone_2 !== '—' && detailedClientData.phone_2 !== '-' ? detailedClientData.phone_2 : '—' }}
                                        </strong>
                                    </div>

                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">JSHSHIR (PINFL):</span>
                                        <strong class="text-gray-900 font-mono text-xs">{{ detailedClientData.pinfl || '—' }}</strong>
                                    </div>



                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Pasport raqami:</span>
                                        <strong class="text-gray-900 font-mono text-xs">{{ detailedClientData.passport_number || '—' }}</strong>
                                    </div>

                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Viloyat (Region):</span>
                                        <strong class="text-gray-900 text-xs">
                                            {{ detailedClientData.region && detailedClientData.region !== '—' && detailedClientData.region !== '-' ? detailedClientData.region : '—' }}
                                        </strong>
                                    </div>

                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Tuman (District):</span>
                                        <strong class="text-gray-900 text-xs">
                                            {{ detailedClientData.district && detailedClientData.district !== '—' && detailedClientData.district !== '-' ? detailedClientData.district : '—' }}
                                        </strong>
                                    </div>

                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Manzil (Address):</span>
                                        <strong class="text-gray-900 text-xs">
                                            {{ detailedClientData.address && detailedClientData.address !== 'address' && detailedClientData.address !== '—' && detailedClientData.address !== '-' ? detailedClientData.address : '—' }}
                                        </strong>
                                    </div>

                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Pasport fayli:</span>
                                        <div class="flex gap-2">
                                            <div v-if="detailedClientData.passport_photo" class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 px-2 py-0.5 rounded-lg">
                                                <button type="button" @click="openPassportPreview('/storage/' + detailedClientData.passport_photo)" class="text-amber-500 hover:underline font-bold text-[10px]">Ko'rish</button>
                                                <a :href="'/storage/' + detailedClientData.passport_photo" download class="text-emerald-600 hover:underline font-bold text-[10px]">Yuklash</a>
                                            </div>
                                            <span v-else class="text-gray-400 italic">Yuklanmagan</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 2: Uy haqida ma'lumot -->
                            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm text-left flex flex-col justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                                        <span class="w-1.5 h-5 bg-[#f2a900] rounded-full"></span>
                                        Uy haqida ma'lumot
                                    </h3>

                                    <!-- Top Row: Images and Building Info -->
                                    <div class="flex flex-col sm:flex-row gap-5 items-start sm:items-center">
                                        <!-- Images -->
                                        <div class="flex gap-2.5">
                                            <!-- Blueprint Thumbnail -->
                                            <div @click="previewUnitBlueprint(detailedClientData.contracts[0]?.unit)" 
                                                 class="w-16 h-16 sm:w-20 sm:h-20 bg-slate-50 border border-gray-150 rounded-2xl overflow-hidden cursor-pointer hover:opacity-90 transition-opacity flex items-center justify-center shrink-0 shadow-sm relative group">
                                                <img :src="detailedClientData.contracts[0]?.unit?.blueprint_path ? '/storage/' + detailedClientData.contracts[0]?.unit?.blueprint_path : 
                                                           (detailedClientData.contracts[0]?.unit?.floor?.blueprint_path ? '/storage/' + detailedClientData.contracts[0]?.unit?.floor?.blueprint_path : '/modern_apartment_render.png')" 
                                                     class="w-full h-full object-cover" alt="2D Layout" />
                                                <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                </div>
                                            </div>
                                            <!-- 3D Render Thumbnail -->
                                            <div v-if="detailedClientData.contracts[0]?.unit?.model_3d_path"
                                                 @click="openPassportPreview('/storage/' + detailedClientData.contracts[0]?.unit?.model_3d_path)" 
                                                 class="w-16 h-16 sm:w-20 sm:h-20 bg-slate-50 border border-gray-150 rounded-2xl overflow-hidden cursor-pointer hover:opacity-90 transition-opacity flex items-center justify-center shrink-0 shadow-sm relative group">
                                                <img :src="'/storage/' + detailedClientData.contracts[0]?.unit?.model_3d_path" 
                                                     class="w-full h-full object-cover" alt="3D Render" />
                                                <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                </div>
                                            </div>
                                            <!-- Fallback 3D Render Thumbnail -->
                                            <div v-else
                                                 @click="previewUnitBlueprint(detailedClientData.contracts[0]?.unit)"
                                                 class="w-16 h-16 sm:w-20 sm:h-20 bg-slate-50 border border-gray-150 rounded-2xl overflow-hidden cursor-pointer hover:opacity-90 transition-opacity flex items-center justify-center shrink-0 shadow-sm relative group">
                                                <img src="/modern_apartment_render.png" 
                                                     class="w-full h-full object-cover" alt="3D Render Fallback" />
                                                <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Building Info -->
                                        <div class="flex-1 text-left">
                                            <h2 class="text-base sm:text-lg font-bold text-gray-950 font-sans tracking-tight">
                                                {{ detailedClientData.contracts[0]?.unit?.floor?.building?.name || (($page.props.branding?.app_name || 'SHER-DOR') + ' TJM') }}
                                            </h2>
                                            <div class="flex items-center gap-4 mt-2">
                                                <div>
                                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">{{ detailedClientData.contracts[0]?.unit?.type === 'commercial' ? "DO'KON" : "XONADON" }}</span>
                                                    <strong class="text-gray-900 text-xs font-bold font-mono block mt-0.5">{{ detailedClientData.contracts[0]?.unit?.unit_number }}-{{ detailedClientData.contracts[0]?.unit?.type === 'commercial' ? "Do'kon" : "Xonadon" }}</strong>
                                                </div>
                                                <div class="h-8 w-px bg-gray-150"></div>
                                                <div>
                                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">MAYDONI</span>
                                                    <strong class="text-gray-900 text-xs font-bold font-mono block mt-0.5">{{ detailedClientData.contracts[0]?.unit?.area_sqm }} m²</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Details Cards Grid -->
                                    <div class="grid grid-cols-2 gap-3.5 mt-6">
                                        <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-4 text-left shadow-sm">
                                            <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider block">Bino</span>
                                            <strong class="text-sm font-bold text-gray-900 block mt-1">
                                                {{ detailedClientData.contracts[0]?.unit?.floor?.building?.name || (($page.props.branding?.app_name || 'SHER-DOR') + ' TJM') }}
                                            </strong>
                                        </div>
                                        <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-4 text-left shadow-sm">
                                            <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider block">Podezd</span>
                                            <strong class="text-sm font-bold text-gray-900 block mt-1">
                                                {{ detailedClientData.contracts[0]?.unit?.podyezd ? detailedClientData.contracts[0].unit.podyezd + '-padezd' : '1-padezd' }}
                                            </strong>
                                        </div>
                                        <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-4 text-left shadow-sm">
                                            <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider block">Qavat</span>
                                            <strong class="text-sm font-bold text-gray-900 block mt-1">
                                                {{ detailedClientData.contracts[0]?.unit?.floor?.floor_number || '1' }}-Qavat
                                            </strong>
                                        </div>
                                        <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-4 text-left shadow-sm">
                                            <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider block">Xonalar soni</span>
                                            <strong class="text-sm font-bold text-gray-900 block mt-1">
                                                {{ detailedClientData.contracts[0]?.unit?.rooms || '1' }}
                                            </strong>
                                        </div>
                                        <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-4 text-left shadow-sm col-span-2">
                                            <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider block">{{ detailedClientData.contracts[0]?.unit?.type === 'commercial' ? "Do'kon" : "Xonadon" }} holati</span>
                                            <strong class="text-sm font-bold text-gray-900 block mt-1">
                                                {{ getContractRepairType(detailedClientData.contracts[0]) }}
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Row: Shartnoma & Izohlar -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                            <!-- Card 3: Shartnoma -->
                            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm text-left flex flex-col justify-between min-h-[300px]">
                                <div>
                                    <div class="flex justify-between items-center mb-5">
                                        <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                            <span class="w-1.5 h-5 bg-[#f2a900] rounded-full"></span>
                                            Shartnoma
                                        </h3>
                                        
                                        <div class="flex items-center gap-2">
                                            <span v-if="selectedContract && selectedContract.status !== 'active'" class="inline-flex items-center gap-1 bg-amber-50 text-amber-600 px-2.5 py-1 rounded-xl text-xs font-bold font-mono">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                Kutilmoqda
                                            </span>
                                            <button @click="recalculatePenalty" :disabled="isProcessing" class="w-7 h-7 bg-gray-50 border border-gray-200 rounded-lg flex items-center justify-center text-gray-500 hover:text-[#f2a900] hover:border-[#f2a900] transition-colors" title="Penya qayta hisoblash">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 7.89H18" /></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="space-y-2.5 text-xs text-gray-700">
                                        <div class="flex justify-between py-1 border-b border-gray-50">
                                            <span class="text-gray-400 font-medium">Tasdiqlangan:</span>
                                            <strong class="text-gray-900">{{ detailedClientData.contracts[0]?.transactions[0]?.approver?.name || '—' }}</strong>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-gray-50">
                                            <span class="text-gray-400 font-medium">Sotuv menejeri:</span>
                                            <strong class="text-gray-900">{{ detailedClientData.contracts[0]?.transactions[0]?.creator?.name || '—' }}</strong>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-gray-50">
                                            <span class="text-gray-400 font-medium">Holat:</span> 
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" 
                                                  :class="detailedClientData.contracts[0]?.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'">
                                                {{ detailedClientData.contracts[0]?.status === 'active' ? 'Tasdiqlangan' : 'Kutilmoqda' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-gray-50">
                                            <span class="text-gray-400 font-medium">To'lov turi:</span>
                                            <strong class="text-gray-900">{{ detailedClientData.contracts[0]?.payment_type === 'full' ? 'Bir martalik umumiy to\'lov' : 'Bo\'lib to\'lash' }}</strong>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-gray-50">
                                            <span class="text-gray-400 font-medium">Muddati:</span>
                                            <strong class="text-gray-900 font-mono">
                                                <span v-if="detailedClientData.contracts[0]?.payment_type === 'full'">Bir martalik</span>
                                                <span v-else>{{ detailedClientData.contracts[0]?.term_months || '—' }} oy</span>
                                            </strong>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-gray-50">
                                            <span class="text-gray-400 font-medium">Kadastr:</span>
                                            <strong class="text-gray-900">yo'q</strong>
                                        </div>
                                        <div v-if="detailedClientData.contracts[0]?.down_payment > 0" class="py-1 border-b border-gray-50 flex justify-between">
                                            <span class="text-gray-400 font-medium">Boshlang'ich to'lov:</span>
                                            <strong class="text-gray-900 font-mono text-right">
                                                {{ Number(getContractDownPaymentStats(detailedClientData.contracts[0]).total).toLocaleString('en-US') }} so'm
                                                <span class="text-[10px] text-gray-500 font-normal block">
                                                    (To'landi: {{ Number(getContractDownPaymentStats(detailedClientData.contracts[0]).paid).toLocaleString('en-US') }} so'm)
                                                </span>
                                            </strong>
                                        </div>

                                        <!-- Kunlik Penya Darajasi & Hisoblash -->
                                        <div v-if="selectedContract" class="py-1.5 border-b border-gray-50 flex justify-between items-center gap-2">
                                            <span class="text-gray-400 font-medium">Kunlik Penya (%):</span>
                                            <div class="flex items-center gap-1 shrink-0">
                                                <input type="number" step="0.01" v-model="selectedContract.daily_penalty_rate" class="bg-white border border-gray-300 text-gray-900 rounded px-1.5 py-0.5 text-xs w-14 text-center font-bold font-mono">
                                                <button @click="recalculatePenalty" :disabled="isProcessing" class="px-2 py-0.5 bg-blue-50 border border-blue-300 text-blue-600 text-[10px] font-bold uppercase rounded hover:bg-blue-600 hover:text-white transition-all disabled:opacity-50">
                                                    Hisob
                                                </button>
                                            </div>
                                        </div>

                                        <div v-if="selectedContract" class="py-1.5 border-b border-gray-50 flex justify-between items-center">
                                            <span class="text-gray-400 font-medium">Jamg'arilgan Penya:</span>
                                            <strong class="text-indigo-600 font-bold font-mono">{{ $currency(selectedContract.total_penalty_accumulated || 0) }}</strong>
                                        </div>

                                        <!-- AI Hujjat Tekshiruvi & Tasdiqlash -->
                                        <div v-if="selectedContract" class="py-2 border-b border-gray-50">
                                            <div class="flex justify-between items-center mb-1">
                                                <span class="text-gray-400 font-medium">AI Hujjat:</span>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono" 
                                                      :class="{ 'text-gray-500 bg-gray-50': !selectedContract.ai_verification_status, 'text-emerald-600 bg-emerald-50': selectedContract.ai_verification_status === 'passed', 'text-red-600 bg-red-50': selectedContract.ai_verification_status === 'failed' }">
                                                    {{ selectedContract.ai_verification_status === 'passed' ? 'Tasdiqlangan' : 
                                                       (selectedContract.ai_verification_status === 'failed' ? 'Imzolanmagan' : 'Kutilmoqda') }}
                                                </span>
                                            </div>
                                            <div class="flex items-center justify-between gap-2 mt-2">
                                                <div class="flex flex-col">
                                                    <span v-if="uploadStatus === 'uploading'" class="text-[9px] text-blue-600 animate-pulse font-sans">{{ uploadMessage }}</span>
                                                    <span v-else-if="uploadStatus === 'success'" class="text-[9px] text-emerald-600 font-sans">{{ uploadMessage }}</span>
                                                    <span v-else-if="uploadStatus === 'error'" class="text-[9px] text-red-600 font-sans">{{ uploadMessage }}</span>
                                                    <span v-else class="text-[9px] text-gray-400 font-sans">Rasm/PDF</span>
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    <input type="file" ref="detailFileInput" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="uploadSignature">
                                                    <button @click="detailFileInput.click()" :disabled="isProcessing" class="px-2 py-0.5 bg-blue-50 border border-blue-200 text-blue-600 font-bold text-[9px] uppercase rounded hover:bg-blue-600 hover:text-white transition-all disabled:opacity-50 flex items-center gap-0.5">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                                        Yuklash
                                                    </button>
                                                    <button v-if="selectedContract.status !== 'active'" @click="handleSotildiClick" :disabled="isProcessing" class="px-2 py-0.5 bg-emerald-50 border border-emerald-200 text-emerald-600 font-bold text-[9px] uppercase rounded hover:bg-emerald-600 hover:text-white transition-all disabled:opacity-50 flex items-center gap-0.5">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                        Tasdiqlash
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 mt-5">
                                    <button @click="openDocumentPreview('contract', detailedClientData.contracts[0])" class="flex-1 py-2.5 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center justify-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        Shartnoma
                                    </button>
                                    <button @click="openDocumentPreview('grafik', detailedClientData.contracts[0])" class="flex-1 py-2.5 bg-white border border-[#f2a900] hover:bg-amber-50/50 text-[#f2a900] text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" /></svg>
                                        Grafik
                                    </button>
                                    <button @click="selectClientForDetail(detailedClientData)" class="flex-1 py-2.5 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center justify-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 7.89H18" /></svg>
                                        Yangilash
                                    </button>
                                </div>
                            </div>

                            <!-- Card 4: Izohlar -->
                            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm text-left flex flex-col justify-between min-h-[300px]">
                                <div>
                                    <div class="flex justify-between items-center mb-5">
                                        <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                            <span class="w-1.5 h-5 bg-[#f2a900] rounded-full"></span>
                                            Izohlar
                                        </h3>
                                        
                                        <button @click="openCommentsModal" class="w-7 h-7 bg-[#f2a900] hover:bg-[#d99700] text-white rounded-lg flex items-center justify-center transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                        </button>
                                    </div>

                                    <div v-if="detailedClientData?.comments && detailedClientData.comments.length > 0" class="space-y-3 max-h-[180px] overflow-y-auto pr-1">
                                        <div v-for="c in detailedClientData.comments.slice(0, 3)" :key="c.id" class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-xs text-gray-700">
                                            <div class="flex justify-between items-center text-[10px] text-gray-400 font-semibold mb-1">
                                                <span class="text-amber-600 font-bold">{{ c.user?.name || 'Xodim' }}</span>
                                                <span>{{ new Date(c.created_at).toLocaleString() }}</span>
                                            </div>
                                            <p class="text-gray-800 leading-relaxed font-sans">{{ c.comment }}</p>
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-gray-500 font-sans text-center py-6 flex flex-col items-center justify-center">
                                        <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                        Izohlar yo'q
                                    </div>
                                </div>

                                <div class="border-t border-gray-50 pt-3 mt-4 text-right">
                                    <a href="#" @click.prevent="openCommentsModal" class="text-xs font-semibold text-blue-600 hover:underline inline-flex items-center gap-1">
                                        Barchasi
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CASE 2: Client has NO contract -->
                    <div v-else class="space-y-6">
                        <!-- Notice -->
                        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex items-center justify-between min-h-[120px] relative overflow-hidden">
                            <div class="absolute right-0 top-0 translate-x-4 -translate-y-4 w-32 h-32 bg-slate-50 rounded-full opacity-50 blur-xl"></div>
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center border border-amber-100 shrink-0">
                                    <svg class="w-6 h-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                </div>
                                <div class="text-left">
                                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider font-sans">Uy haqida ma'lumot topilmadi</h3>
                                    <p class="text-xs text-gray-500 font-sans mt-0.5">Ushbu mijoz hali uy sotib olmagan.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                            <!-- Card 1: Mijoz ma'lumotlari -->
                            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm text-left">
                                <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                                    <span class="w-1.5 h-5 bg-[#f2a900] rounded-full"></span>
                                    Mijoz ma'lumotlari
                                </h3>
                                
                                <div class="space-y-3.5">
                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Telefon raqami:</span>
                                        <strong class="text-gray-900 font-mono text-xs">{{ detailedClientData.phone }}</strong>
                                    </div>

                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Telefon raqami 2:</span>
                                        <strong class="text-gray-900 font-mono text-xs">
                                            {{ detailedClientData.phone_2 && detailedClientData.phone_2 !== '+998' && detailedClientData.phone_2.trim() !== '' && detailedClientData.phone_2 !== '—' && detailedClientData.phone_2 !== '-' ? detailedClientData.phone_2 : '—' }}
                                        </strong>
                                    </div>

                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">JSHSHIR (PINFL):</span>
                                        <strong class="text-gray-900 font-mono text-xs">{{ detailedClientData.pinfl || '—' }}</strong>
                                    </div>



                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Pasport raqami:</span>
                                        <strong class="text-gray-900 font-mono text-xs">{{ detailedClientData.passport_number || '—' }}</strong>
                                    </div>

                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Viloyat (Region):</span>
                                        <strong class="text-gray-900 text-xs">
                                            {{ detailedClientData.region && detailedClientData.region !== '—' && detailedClientData.region !== '-' ? detailedClientData.region : '—' }}
                                        </strong>
                                    </div>

                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Tuman (District):</span>
                                        <strong class="text-gray-900 text-xs">
                                            {{ detailedClientData.district && detailedClientData.district !== '—' && detailedClientData.district !== '-' ? detailedClientData.district : '—' }}
                                        </strong>
                                    </div>

                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Manzil (Address):</span>
                                        <strong class="text-gray-900 text-xs">
                                            {{ detailedClientData.address && detailedClientData.address !== 'address' && detailedClientData.address !== '—' && detailedClientData.address !== '-' ? detailedClientData.address : '—' }}
                                        </strong>
                                    </div>

                                    <div class="flex items-center gap-3 py-2 border-b border-gray-50 text-xs text-gray-700">
                                        <span class="text-gray-400 shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        </span>
                                        <span class="w-28 text-gray-400 font-medium">Pasport fayli:</span>
                                        <div class="flex gap-2">
                                            <div v-if="detailedClientData.passport_photo" class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 px-2 py-0.5 rounded-lg">
                                                <button type="button" @click="openPassportPreview('/storage/' + detailedClientData.passport_photo)" class="text-amber-500 hover:underline font-bold text-[10px]">Ko'rish</button>
                                                <a :href="'/storage/' + detailedClientData.passport_photo" download class="text-emerald-600 hover:underline font-bold text-[10px]">Yuklash</a>
                                            </div>
                                            <span v-else class="text-gray-400 italic">Yuklanmagan</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 4: Izohlar -->
                            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm text-left flex flex-col justify-between min-h-[300px]">
                                <div>
                                    <div class="flex justify-between items-center mb-5">
                                        <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                            <span class="w-1.5 h-5 bg-[#f2a900] rounded-full"></span>
                                            Izohlar
                                        </h3>
                                        
                                        <button @click="openCommentsModal" class="w-7 h-7 bg-[#f2a900] hover:bg-[#d99700] text-white rounded-lg flex items-center justify-center transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                        </button>
                                    </div>

                                    <div v-if="detailedClientData?.comments && detailedClientData.comments.length > 0" class="space-y-3 max-h-[180px] overflow-y-auto pr-1">
                                        <div v-for="c in detailedClientData.comments.slice(0, 3)" :key="c.id" class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-xs text-gray-700">
                                            <div class="flex justify-between items-center text-[10px] text-gray-400 font-semibold mb-1">
                                                <span class="text-amber-600 font-bold">{{ c.user?.name || 'Xodim' }}</span>
                                                <span>{{ new Date(c.created_at).toLocaleString() }}</span>
                                            </div>
                                            <p class="text-gray-800 leading-relaxed font-sans">{{ c.comment }}</p>
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-gray-500 font-sans text-center py-6 flex flex-col items-center justify-center">
                                        <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                        Izohlar yo'q
                                    </div>
                                </div>

                                <div class="border-t border-gray-50 pt-3 mt-4 text-right">
                                    <a href="#" @click.prevent="openCommentsModal" class="text-xs font-semibold text-blue-600 hover:underline inline-flex items-center gap-1">
                                        Barchasi
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 5: To'lov jadvali -->
                    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-base font-bold text-gray-900 font-sans">To'lov jadvali</h3>
                            <div class="flex items-center gap-2">
                                <button @click="openPaymentNotesModal" class="px-4 py-2 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-sm">
                                    To'lov izohi
                                </button>
                                <button @click="openPaymentHistoryModal" class="px-4 py-2 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-sm">
                                    To'lov tarixi
                                </button>
                                <button @click="openExportFormatModal(detailedClientData.contracts[0].id)" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Yuklab olish
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
                                        <th class="p-3 text-right">Penya</th>
                                        <th class="p-3 text-center">Holat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 font-mono text-xs">
                                    <tr v-for="(schedule, index) in getContractPaymentRows(detailedClientData.contracts[0])" :key="schedule.id" class="hover:bg-slate-50/50 transition-colors">
                                        <td class="p-3 text-center text-gray-500">{{ index + 1 }}</td>
                                        <td class="p-3 text-center text-gray-700">{{ formatDateOnly(schedule.date) }}</td>
                                        <td class="p-3 text-right font-bold text-gray-900">{{ Number(schedule.expected_amount || 0).toLocaleString('en-US') }}</td>
                                        <td class="p-3 text-right font-bold text-emerald-600">{{ Number(schedule.paid_amount || 0).toLocaleString('en-US') }}</td>
                                        <td class="p-3 text-right font-bold text-red-500">{{ Number(schedule.expected_amount - schedule.paid_amount || 0).toLocaleString('en-US') }}</td>
                                        <td class="p-3 text-center font-sans text-gray-700">
                                            {{ schedule.payment_type === 'down_payment' ? 'Bosh to\'lov' : (schedule.payment_type === 'installment' ? 'Oylik to\'lov' : (schedule.payment_type === 'full' ? 'To\'liq to\'lov' : (index === 0 ? 'Bosh to\'lov' : 'Oylik to\'lov'))) }}
                                        </td>
                                        <td class="p-3 text-right font-bold text-indigo-600">
                                            {{ Number(schedule.penalty || 0).toLocaleString('en-US') }}
                                        </td>
                                        <td class="p-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase font-sans"
                                                  :class="schedule.status === 'paid' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'">
                                                {{ schedule.status === 'paid' ? 'To\'langan' : 'Qarzdorlik' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="getContractPaymentRows(detailedClientData.contracts[0]).length === 0">
                                        <td colspan="8" class="py-12 text-center text-gray-400 font-sans">To'lov jadvali mavjud emas.</td>
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



        <!-- TAB CANCELED: BEKOR QILINGAN SHARTNOMALAR -->
        <div v-if="activeTab === 'canceled'" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-2xl min-h-[500px] border border-red-200">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <h2 class="text-xl font-display font-bold text-red-600 flex items-center gap-3">
                    <span class="w-3 h-3 rounded-full bg-red-600 shadow-sm"></span>
                    Bekor Qilingan Shartnomalar
                </h2>
                <div class="relative w-full sm:w-64">
                    <span class="absolute left-3 top-2.5 text-gray-400 text-xs">🔍</span>
                    <input v-model="canceledSearchQuery" type="text" placeholder="Mijoz yoki shartnomani qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl pl-8 pr-3 py-2 text-xs w-full focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
            </div>
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
                                    <div class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center font-display font-bold text-sm shadow-sm bg-gradient-to-br from-neon-red/20 to-black border border-red-200 text-red-600">
                                         <img v-if="contract.client?.profile_photo" :src="'/storage/' + contract.client.profile_photo" class="w-full h-full object-cover" alt="Profile" />
                                         <template v-else>
                                             {{ getInitials(contract.client?.first_name, contract.client?.last_name) }}
                                         </template>
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

        <!-- TAB: SOTUV XODIMLARI (SALES AGENTS PERFORMANCE & STATS) -->
        <div v-else-if="activeTab === 'sales_agents'" class="space-y-6">
            <!-- Header bar with title, subtitle and filters (Image 1 style) -->
            <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.crm', { tab: 'dashboard' })" class="w-10 h-10 rounded-full border border-gray-200 hover:bg-gray-50 flex items-center justify-center transition-colors">
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
                                <td class="p-4 text-center font-mono text-gray-900">{{ $currency(agent.soldAmount) }}</td>
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

        <!-- TAB: MIJOZ TO'LOVLARI (CLIENT PAYMENTS & DEBTS) -->
        <div v-if="activeTab === 'client_payments'" class="space-y-6">
            <!-- Sub tabs bar -->
            <div class="flex border-b border-gray-200 bg-white px-6 pt-4 rounded-t-2xl border-x border-t border-gray-150">
                <button @click="paymentsSubTab = 'payments'" class="px-6 py-3 font-bold text-xs uppercase tracking-widest border-b-2 transition-all" :class="paymentsSubTab === 'payments' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-400'">
                    Mijoz to'lovlari
                </button>
                <button @click="paymentsSubTab = 'debts'" class="px-6 py-3 font-bold text-xs uppercase tracking-widest border-b-2 transition-all" :class="paymentsSubTab === 'debts' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-400'">
                    Mijozdan qarzdorlik
                </button>
            </div>

            <!-- Subtab 1: Mijoz to'lovlari (Image 1 Style) -->
            <div v-if="paymentsSubTab === 'payments'" class="space-y-6 bg-white p-6 rounded-b-2xl border-x border-b border-gray-150 shadow-sm">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div class="relative flex-1 max-w-md">
                        <span class="absolute left-3.5 top-2.5 text-gray-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input v-model="clientPaymentsSearch" type="text" placeholder="Qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-10 pr-4 py-2 text-sm w-full">
                    </div>
                    <button @click="openQuickTransaction('income')" class="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-sm">
                        + To'lov qilish
                    </button>
                </div>

                <div class="overflow-x-auto border border-gray-150 rounded-2xl">
                    <table class="w-full text-left text-sm font-sans">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                            <tr>
                                <th class="p-4 w-12 text-center font-bold">№</th>
                                <th class="p-4">Ism</th>
                                <th class="p-4 text-right">Summa</th>
                                <th class="p-4">To'lov sanasi</th>
                                <th class="p-4">To'lov turi</th>
                                <th class="p-4">Hujjat raqami</th>
                                <th class="p-4 text-center">To'lov kvitantsiyasi</th>
                                <th class="p-4 text-right">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(t, idx) in filteredClientPayments" :key="t.id" class="hover:bg-gray-50 transition-colors">
                                <td class="p-4 text-gray-400 font-bold text-center">{{ idx + 1 }}</td>
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-sm shadow-sm shrink-0">
                                            {{ getInitials(t.counterparty?.name ? t.counterparty.name.split(' ')[1] : '', t.counterparty?.name ? t.counterparty.name.split(' ')[0] : '') }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-900 text-sm leading-tight">{{ t.counterparty?.name || '-' }}</p>
                                            <span class="text-xs text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded font-mono mt-1 inline-block">{{ t.counterparty?.phone || '-' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-right font-bold text-blue-600 text-base">{{ Number(t.amount || 0).toLocaleString() }} so'm</td>
                                <td class="p-4 text-gray-500 text-xs font-mono">{{ new Date(t.transaction_date || t.created_at).toLocaleString() }}</td>
                                <td class="p-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold border border-gray-200 bg-gray-50 font-sans">
                                        {{ t.payment_method === 'cash' ? 'Naqd pul' : (t.payment_method === 'card' ? 'Plastik' : 'Bank transfer') }}
                                    </span>
                                </td>
                                <td class="p-4 text-gray-700 font-mono font-bold text-center">#{{ t.id }}</td>
                                <td class="p-4 text-center">
                                    <button @click="openReceipt(t)" class="inline-flex items-center gap-1.5 px-3 py-1 text-xs text-amber-600 hover:text-amber-700 font-bold transition-all hover:underline" title="To'lov kvitantsiyasi">
                                        Yuklab olish
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </button>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="openEditTransactionModal(t)" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm" title="Tahrirlash">
                                            ✏️
                                        </button>
                                        <button @click="deleteTransaction(t.id)" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-600 hover:text-white transition-all shadow-sm" title="O'chirish">
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!filteredClientPayments.length">
                                <td colspan="8" class="p-8 text-center text-gray-400">To'lovlar topilmadi.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Subtab 2: Mijozdan qarzdorlik (Image 2 Style) -->
            <div v-if="paymentsSubTab === 'debts'" class="space-y-6 bg-white p-6 rounded-b-2xl border-x border-b border-gray-150 shadow-sm">
                <div class="flex">
                    <div class="relative flex-1 max-w-md">
                        <span class="absolute left-3.5 top-2.5 text-gray-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input v-model="clientDebtsSearch" type="text" placeholder="Qidirish..." class="bg-white border border-gray-300 text-gray-900 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-10 pr-4 py-2 text-sm w-full">
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-150 rounded-2xl">
                    <table class="w-full text-left text-sm font-sans">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                            <tr>
                                <th class="p-4 w-12 text-center font-bold">№</th>
                                <th class="p-4">Ism</th>
                                <th class="p-4 text-center">Kelishilgan-To'langan</th>
                                <th class="p-4 text-right">Qolgan miqdor</th>
                                <th class="p-4 text-right">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(c, idx) in filteredClientDebts" :key="c.id" class="hover:bg-gray-50 transition-colors">
                                <td class="p-4 text-gray-400 font-bold text-center">{{ idx + 1 }}</td>
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-sm shadow-sm shrink-0">
                                            {{ getInitials(c.name ? c.name.split(' ')[1] : '', c.name ? c.name.split(' ')[0] : '') }}
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <p class="font-bold text-gray-900 text-sm leading-tight">{{ c.name }}</p>
                                                <span v-if="c.balance > 0" class="px-2 py-0.5 text-[10px] bg-emerald-50 text-emerald-600 border border-emerald-200 rounded font-bold uppercase tracking-wider">Mijozga qaytarish</span>
                                            </div>
                                            <span class="text-xs text-gray-500 font-mono mt-1 inline-block">{{ c.phone || '-' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-center font-bold text-emerald-600">
                                    {{ Number(c.paid_amount || 0).toLocaleString() }} so'm
                                    <div class="text-[10px] text-gray-400 font-normal font-sans">Kelishilgan: {{ Number(c.agreed_amount || 0).toLocaleString() }} so'm</div>
                                </td>
                                <td class="p-4 text-right font-bold text-base" :class="c.balance > 0 ? 'text-emerald-600' : 'text-red-600'">
                                    {{ c.balance > 0 ? 'Qaytariladi: ' : '' }}{{ Number(c.debt_amount || 0).toLocaleString() }} so'm
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <!-- Quick pay debt button -->
                                        <button @click="openQuickTransaction(c.balance > 0 ? 'expense' : 'income', c.customable)" class="px-3 py-1 border rounded-lg text-xs font-bold transition-all shadow-sm font-sans" :class="c.balance > 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-700 hover:bg-emerald-500 hover:text-white' : 'bg-amber-50 border-amber-200 text-amber-700 hover:bg-amber-500 hover:text-white'">
                                            {{ c.balance > 0 ? '+ Qaytarish' : '+ Qarzni to\'lash' }}
                                        </button>
                                        
                                        <!-- Edit/delete counterparty -->
                                        <button @click="openEditCounterpartyModal(c)" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-sm" title="Tahrirlash">
                                            ✏️
                                        </button>
                                        <button @click="deleteCounterparty(c.id)" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-600 hover:text-white transition-all shadow-sm" title="O'chirish">
                                            🗑️
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

        <!-- TAB: MIJOZ TO'LOV HISOBOTLARI (CLIENT PAYMENT REPORTS / LEDGERS) -->
        <div v-if="activeTab === 'client_payment_reports'" class="space-y-6 bg-white p-6 rounded-2xl border border-gray-150 shadow-sm font-sans">
            <div>
                <h2 class="text-lg font-bold text-gray-900">📊 Mijoz to'lov hisoboti (Shaxsiy varaqa)</h2>
                <p class="text-xs text-gray-500 font-mono mt-1 font-semibold">Tizimdagi shartnomalar bo'yicha shaxsiy varaqalar va to'lovlar tahlili.</p>
            </div>

            <!-- Search & Filters -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end bg-gray-50 p-4 rounded-xl border border-gray-200/60 shadow-sm max-w-4xl">
                <!-- Search text -->
                <div class="md:col-span-2">
                    <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1.5">Ism, shartnoma raqami, tel yoki xonadon bo'yicha</label>
                    <input 
                        v-model="reportSearchQuery" 
                        type="text" 
                        placeholder="Qidiruv matnini kiriting..." 
                        class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-4 py-2 text-sm shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <!-- Building select -->
                <div>
                    <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1.5">Bino</label>
                    <select v-model="reportSelectedBuildingId" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- Barchasi --</option>
                        <option v-for="b in uniqueBuildings" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>
                </div>

                <!-- Block select -->
                <div>
                    <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1.5">Blok</label>
                    <div class="flex gap-2">
                        <select v-model="reportSelectedBlock" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Barchasi --</option>
                            <option v-for="block in uniqueBlocks" :key="block" :value="block">
                                {{ block }}
                            </option>
                        </select>
                        <button 
                            v-if="reportSearchQuery || reportSelectedBuildingId || reportSelectedBlock"
                            @click="clearReportFilters"
                            class="px-3 py-2 bg-red-50 hover:bg-red-500 hover:text-white text-red-600 rounded-xl border border-red-200 transition-colors shadow-sm text-sm"
                            title="Filtrlarni tozalash"
                        >
                            ✕
                        </button>
                    </div>
                </div>
            </div>

            <!-- Contract Selector -->
            <div class="max-w-md">
                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Mijoz shartnomasini tanlang</label>
                <select v-model="selectedReportContractId" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-4 py-2 text-sm shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- Shartnomani tanlang ({{ filteredReportContracts.length }} ta mos keldi) --</option>
                    <option v-for="c in filteredReportContracts" :key="c.id" :value="c.id">
                        Shartnoma #{{ c.contract_number || c.id }} - {{ c.client?.last_name }} {{ c.client?.first_name }} ({{ c.unit?.floor?.building?.name || 'Bino' }} {{ c.unit?.type === 'commercial' ? "Do'kon" : "Xonadon" }}: {{ c.unit?.unit_number }})
                    </option>
                </select>
            </div>

            <!-- Inline Ledger Report -->
            <div v-if="selectedReportContract && ledgerReportData" class="space-y-6 pt-4 border-t border-gray-100">
                <!-- Info cards grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Contract summary -->
                    <div class="p-4 bg-gray-50 rounded-xl border border-gray-200/60 shadow-sm text-xs space-y-2">
                        <p class="font-bold text-gray-700 border-b border-gray-200 pb-1.5">📄 Shartnoma Ma'lumotlari</p>
                        <div class="flex justify-between"><span>Raqami:</span> <strong class="text-gray-900">#{{ selectedReportContract.contract_number || selectedReportContract.id }}</strong></div>
                        <div class="flex justify-between"><span>Sanasi:</span> <strong class="text-gray-900">{{ selectedReportContract.created_at ? new Date(selectedReportContract.created_at).toLocaleDateString() : '—' }}</strong></div>
                        <div class="flex justify-between"><span>Turi:</span> <strong class="text-gray-900 uppercase">{{ selectedReportContract.contract_type }}</strong></div>
                    </div>
                    <!-- Client summary -->
                    <div class="p-4 bg-gray-50 rounded-xl border border-gray-200/60 shadow-sm text-xs space-y-2">
                        <p class="font-bold text-gray-700 border-b border-gray-200 pb-1.5">👤 Mijoz Ma'lumotlari</p>
                        <div class="flex justify-between"><span>F.I.Sh:</span> <strong class="text-gray-900">{{ selectedReportContract.client?.last_name }} {{ selectedReportContract.client?.first_name }}</strong></div>
                        <div class="flex justify-between"><span>Telefon:</span> <strong class="text-gray-900">{{ selectedReportContract.client?.phone }}</strong></div>
                        <div class="flex justify-between"><span>PINFL:</span> <strong class="text-gray-900">{{ selectedReportContract.client?.pinfl || '—' }}</strong></div>
                    </div>
                    <!-- Apartment summary -->
                    <div class="p-4 bg-gray-50 rounded-xl border border-gray-200/60 shadow-sm text-xs space-y-2">
                        <p class="font-bold text-gray-700 border-b border-gray-200 pb-1.5">🏢 {{ selectedReportContract.unit?.type === 'commercial' ? "Do'kon" : "Xonadon" }} Ma'lumotlari</p>
                        <div class="flex justify-between"><span>Bino:</span> <strong class="text-gray-900">{{ selectedReportContract.unit?.floor?.building?.name || '—' }}</strong></div>
                        <div class="flex justify-between"><span>Qavat / Maydon:</span> <strong class="text-gray-900">{{ selectedReportContract.unit?.floor?.floor_number }}-qavat / {{ selectedReportContract.unit?.area_sqm }} m²</strong></div>
                        <div class="flex justify-between"><span>Xona soni / Raqami:</span> <strong class="text-gray-900">{{ selectedReportContract.unit?.rooms }} xona / {{ selectedReportContract.unit?.type === 'commercial' ? "Do'kon" : "Xonadon" }} {{ selectedReportContract.unit?.unit_number }}</strong></div>
                    </div>
                </div>

                <!-- Action links/buttons for downloading file reports -->
                <div class="flex flex-wrap items-center gap-3">
                    <a :href="`/admin/crm/ledger/${selectedReportContract.id}/export/pdf`" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-50 border border-red-300 text-red-700 text-xs font-bold uppercase rounded-xl hover:bg-red-500 hover:text-white transition-all shadow-sm">
                        PDF Yuklash
                    </a>
                    <a :href="`/admin/crm/ledger/${selectedReportContract.id}/export/xlsx`" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-50 border border-emerald-300 text-emerald-700 text-xs font-bold uppercase rounded-xl hover:bg-emerald-500 hover:text-white transition-all shadow-sm">
                        Excel Yuklash
                    </a>
                    <a :href="`/admin/crm/ledger/${selectedReportContract.id}/export/docx`" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-50 border border-blue-300 text-blue-700 text-xs font-bold uppercase rounded-xl hover:bg-blue-500 hover:text-white transition-all shadow-sm">
                        Word/Docx Yuklash
                    </a>
                </div>

                <!-- Ledger Table -->
                <div class="overflow-x-auto border border-gray-150 rounded-2xl mt-4">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-[10px] border-b border-gray-200">
                            <tr>
                                <th class="p-3 w-12 text-center font-bold">№</th>
                                <th class="p-3">To'lov muddati</th>
                                <th class="p-3 text-right">Kelishilgan summa</th>
                                <th class="p-3 text-center">To'lov sanasi</th>
                                <th class="p-3 text-right">To'langan summa</th>
                                <th class="p-3 text-center">To'lov turi</th>
                                <th class="p-3 text-center">Qaytarilgan sana</th>
                                <th class="p-3 text-right">Qaytarilgan summa</th>
                                <th class="p-3 text-right font-bold">Qoldiq</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(row, idx) in ledgerReportData.rows" :key="idx" class="hover:bg-gray-50 transition-colors" :class="row.is_down_payment ? 'bg-amber-50/20 font-bold' : ''">
                                <td class="p-3 text-gray-400 font-bold text-center">{{ idx + 1 }}</td>
                                <td class="p-3">{{ row.due_date_label }}</td>
                                <td class="p-3 text-right">{{ row.expected_amount ? Number(row.expected_amount).toLocaleString() + " so'm" : '—' }}</td>
                                <td class="p-3 text-center text-gray-500">{{ row.paid_date || '—' }}</td>
                                <td class="p-3 text-right font-bold" :class="row.paid_amount ? 'text-emerald-600' : 'text-gray-400'">{{ row.paid_amount ? Number(row.paid_amount).toLocaleString() + " so'm" : '—' }}</td>
                                <td class="p-3 text-center">{{ row.payment_method || '—' }}</td>
                                <td class="p-3 text-center text-gray-500">{{ row.refund_date || '—' }}</td>
                                <td class="p-3 text-right font-bold text-red-500">{{ row.refund_amount ? Number(row.refund_amount).toLocaleString() + " so'm" : '—' }}</td>
                                <td class="p-3 text-right font-bold" :class="row.qoldiq < 0 ? 'text-red-600' : (row.qoldiq > 0 ? 'text-emerald-600' : 'text-gray-900')">
                                    {{ row.qoldiq !== null ? Number(row.qoldiq).toLocaleString() + " so'm" : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Ledger Totals Summary -->
                <div class="p-6 bg-gray-50 rounded-2xl border border-gray-150 max-w-md ml-auto text-sm space-y-3 font-semibold shadow-sm">
                    <div class="flex justify-between"><span>Jami Shartnoma summasi:</span> <strong class="text-gray-900">{{ Number(selectedReportContract.total_amount || 0).toLocaleString() }} so'm</strong></div>
                    <div class="flex justify-between text-emerald-600"><span>Jami To'langan:</span> <strong>{{ Number(ledgerReportData.totalPaidAll || 0).toLocaleString() }} so'm</strong></div>
                    <div class="flex justify-between text-red-600" v-if="ledgerReportData.totalRefundedAll > 0"><span>Qaytarilgan summa:</span> <strong>{{ Number(ledgerReportData.totalRefundedAll).toLocaleString() }} so'm</strong></div>
                    <div class="flex justify-between text-indigo-600" v-if="selectedReportContract.total_penalty_accumulated > 0"><span>Kechikish penyasi:</span> <strong>{{ Number(selectedReportContract.total_penalty_accumulated).toLocaleString() }} so'm</strong></div>
                    <div class="flex justify-between text-red-600 border-t border-dashed border-gray-300 pt-3 text-base">
                        <span>QARZDORLIK QOLDIQ:</span> 
                        <strong>{{ Number(ledgerReportData.remainingDebt || 0).toLocaleString() }} so'm</strong>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-20 text-gray-400 font-sans border border-dashed border-gray-200 rounded-2xl">
                Shaxsiy varaqa hosil qilish uchun yuqoridan shartnoma tanlang.
            </div>
        </div>

        <!-- TAB: QARZDORLAR (STRICT COLLECTION + OVERDUE SCHEDULES) -->
        <div v-if="activeTab === 'overdue'" class="space-y-6 font-sans">
            <!-- Header section matching design mockup -->
            <div class="bg-white p-6 rounded-2xl border border-gray-150 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold font-sans text-red-600 flex items-center gap-2">
                        <span>⏳</span> Qarzdorlar
                    </h2>
                    <p class="text-xs text-gray-500 font-mono mt-1 font-semibold">Mijozlarning muddati o'tgan to'lovlari va qarzdorliklar nazorati.</p>
                </div>
                
                <!-- View Selector Toggle + Excel button -->
                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <!-- Toggle Switch Container -->
                    <div class="bg-gray-100 p-1 rounded-xl flex gap-1 shadow-inner border border-gray-200">
                        <button 
                            @click="overdueViewMode = 'cards'"
                            class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                            :class="overdueViewMode === 'cards' ? 'bg-white text-red-600 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                        >
                            🚨 Qat'iy undiruv (Karta)
                        </button>
                        <button 
                            @click="overdueViewMode = 'table'"
                            class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                            :class="overdueViewMode === 'table' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                        >
                            📊 Barcha qarzdorlar (Jadval)
                        </button>
                    </div>

                    <a :href="route('admin.crm.export.overdue.excel')" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl flex items-center gap-2 shadow-sm uppercase font-sans shrink-0">
                        📥 Excelga yuklash
                    </a>
                </div>
            </div>

            <!-- View: Qat'iy undiruv (Cards) -->
            <div v-if="overdueViewMode === 'cards'" class="space-y-6">
                <!-- Debtor Siren Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6" v-if="groupedStrictCollection && groupedStrictCollection.length > 0">
                    <div 
                        v-for="group in groupedStrictCollection" 
                        :key="group.client?.id"
                        @click="openScheduleModal(group.schedules[0].contract)"
                        class="bg-white border-2 border-red-100 hover:border-red-400 cursor-pointer rounded-2xl p-6 shadow-sm hover:shadow-md transition-all flex items-center justify-between"
                    >
                        <div class="flex items-center gap-4">
                            <!-- Red triangle alarm icon -->
                            <div class="w-12 h-12 rounded-full bg-red-50 text-red-600 flex items-center justify-center shadow-sm shrink-0 border border-red-200 animate-pulse">
                                ⚠️
                            </div>
                            <div>
                                <p class="text-base font-bold text-gray-900">{{ group.client?.last_name }} {{ group.client?.first_name }}</p>
                                <p class="text-xs text-red-600 mt-1 font-semibold">{{ group.schedules.length }} ta to'lov kechikkan</p>
                            </div>
                        </div>

                        <div class="text-right space-y-1">
                            <div class="text-xs text-gray-500 font-bold uppercase tracking-wider">Kechikkan summa</div>
                            <div class="text-lg font-bold text-red-600 font-mono">{{ Number(group.total_amount || 0).toLocaleString() }} so'm</div>
                            <div class="text-xs font-bold text-red-600 bg-red-50 rounded-full px-2.5 py-0.5 inline-block">
                                max {{ group.max_days }} kun
                            </div>
                        </div>

                        <!-- Actions Call & SMS -->
                        <div class="flex items-center gap-2 shrink-0 ml-4 border-l border-gray-100 pl-4" @click.stop>
                            <!-- Phone Call -->
                            <a :href="`tel:${group.phone}`" class="w-10 h-10 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center hover:bg-emerald-500 hover:text-white transition-all shadow-sm" title="Qo'ng'iroq qilish">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            </a>
                            <!-- Message warning SMS -->
                            <button @click="sendWarningSms(group.schedules[0].id)" class="w-10 h-10 rounded-full bg-blue-50 border border-blue-200 text-blue-600 flex items-center justify-center hover:bg-blue-500 hover:text-white transition-all shadow-sm" title="SMS ogohlantirish">
                                📱
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-20 text-gray-400 font-sans border border-dashed border-gray-200 rounded-2xl bg-white">
                    Strict collection qarzdorliklar topilmadi. 🎉
                </div>
            </div>

            <!-- View: Barcha qarzdorlar (Table) -->
            <div v-else-if="overdueViewMode === 'table'" class="space-y-6">
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table class="w-full text-left border-collapse text-xs font-sans whitespace-nowrap">
                        <thead>
                            <tr class="bg-[#f8fafc] border-b border-gray-200 text-gray-500 font-semibold uppercase text-[10px] tracking-wider">
                                <th class="p-4 w-12 text-center">ID</th>
                                <th class="p-4">Mijoz</th>
                                <th class="p-4">Obyekt / Xonadon</th>
                                <th class="p-4 text-center">Kechikkan to'lovlar</th>
                                <th class="p-4">Kechikkan jami summa</th>
                                <th class="p-4">Maksimal kechikish</th>
                                <th class="p-4 text-right">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr 
                                v-for="(group, index) in groupedOverdueSchedules" 
                                :key="group.client?.id" 
                                @click="openScheduleModal(group.schedules[0].contract)"
                                class="hover:bg-gray-50/50 cursor-pointer transition-colors"
                            >
                                <!-- ID -->
                                <td class="p-4 text-center font-medium text-gray-600">{{ index + 1 }}</td>
                                
                                <!-- Mijoz -->
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center font-bold text-sm shadow-sm shrink-0 bg-[#fee2e2] text-[#ef4444]">
                                             <img v-if="group.client?.profile_photo" :src="'/storage/' + group.client.profile_photo" class="w-full h-full object-cover" alt="Profile" />
                                             <template v-else>
                                                 {{ getInitials(group.client?.first_name, group.client?.last_name) }}
                                             </template>
                                         </div>
                                        <div>
                                            <h3 class="text-sm font-bold text-gray-900 leading-tight">
                                                {{ group.client?.last_name }} {{ group.client?.first_name }}
                                            </h3>
                                            <div class="text-xs text-gray-500 mt-1 font-medium">{{ group.phone || 'N/A' }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Obyekt / Xonadon -->
                                <td class="p-4 text-[#0f766e] font-semibold">
                                    <div v-if="group.schedules[0]?.contract?.unit">
                                        {{ group.schedules[0].contract.unit.floor?.building?.name || '' }}
                                        <span class="text-xs text-gray-400 font-normal">
                                            ({{ group.schedules[0].contract.unit.unit_number }}-xonadon, {{ group.schedules[0].contract.unit.floor?.floor_number }}-qavat)
                                        </span>
                                    </div>
                                    <span v-else>—</span>
                                </td>

                                <!-- Kechikkan to'lovlar -->
                                <td class="p-4 text-center text-red-600 font-bold">
                                    <span class="bg-red-50 text-red-700 px-2.5 py-1 rounded-full text-xs">
                                        {{ group.schedules.length }} ta
                                    </span>
                                </td>

                                <!-- Kechikkan jami summa -->
                                <td class="p-4 text-[#ef4444] font-bold font-mono text-sm">
                                    {{ Number(group.total_amount || 0).toLocaleString() }} so'm
                                </td>

                                <!-- Maksimal kechikish -->
                                <td class="p-4 text-gray-700 font-semibold font-mono">
                                    <span class="text-xs font-bold text-red-600 bg-red-50 rounded-full px-2.5 py-0.5 inline-block border border-red-150">
                                        {{ group.max_days }} kun
                                    </span>
                                </td>

                                <!-- Amallar -->
                                <td class="p-4 text-right" @click.stop>
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Phone Call -->
                                        <a :href="`tel:${group.phone}`" class="w-8 h-8 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center hover:bg-emerald-500 hover:text-white transition-all shadow-sm" title="Qo'ng'iroq qilish">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                        </a>
                                        <!-- SMS ogohlantirish -->
                                        <button @click="sendWarningSms(group.schedules[0].id)" class="w-8 h-8 rounded-full bg-blue-50 border border-blue-200 text-blue-600 flex items-center justify-center hover:bg-blue-500 hover:text-white transition-all shadow-sm" title="SMS ogohlantirish">
                                            📱
                                        </button>
                                        <!-- View details -->
                                        <button @click="selectClientForDetail(group.client, true)" class="w-8 h-8 rounded-full bg-gray-50 border border-gray-200 text-gray-600 flex items-center justify-center hover:bg-gray-200 hover:text-gray-800 transition-all shadow-sm" title="Mijoz haqida">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="groupedOverdueSchedules.length === 0" class="py-20 text-center text-gray-400 font-mono text-sm border-t border-gray-100">
                        Qarzdorlar topilmadi. 🎉
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
                        <div class="flex flex-wrap items-center gap-3">
                            <template v-if="splitLedgerData.isSplit">
                                <a :href="`/admin/crm/ledger/${selectedContract.id}/export/pdf?type=down_payment&lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-amber-50 border border-amber-500 text-amber-600 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-amber-600 hover:text-black transition-all shadow-sm">
                                    📄 Boshlang'ich to'lov varaqasi (PDF)
                                </a>
                                <a :href="`/admin/crm/ledger/${selectedContract.id}/export/pdf?type=installments&lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-red-50 border border-red-500 text-red-600 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-red-600 hover:text-black transition-all shadow-sm">
                                    📄 Asosiy shartnoma varaqasi (PDF)
                                </a>
                                <a :href="`/admin/crm/ledger/${selectedContract.id}/export/xlsx?type=down_payment&lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-emerald-50 border border-emerald-500 text-emerald-700 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-emerald-600 hover:text-black transition-all shadow-sm">
                                    📊 Boshlang'ich to'lov varaqasi (Excel)
                                </a>
                                <a :href="`/admin/crm/ledger/${selectedContract.id}/export/xlsx?type=installments&lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-emerald-50 border border-emerald-500 text-emerald-600 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-emerald-600 hover:text-black transition-all shadow-sm">
                                    📊 Asosiy shartnoma varaqasi (Excel)
                                </a>
                            </template>
                            <template v-else>
                                <a :href="`/admin/crm/ledger/${selectedContract.id}/export/pdf?lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-red-50 border border-red-500 text-red-600 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-red-600 hover:text-black transition-all shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline-block mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> Mijoz shaxsiy varaqasi (PDF)
                                </a>
                                <a :href="`/admin/crm/ledger/${selectedContract.id}/export/xlsx?lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-emerald-50 border border-emerald-500 text-emerald-600 text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-emerald-600 hover:text-black transition-all shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline-block mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Mijoz shaxsiy varaqasi (Excel)
                                </a>
                            </template>
                            <a :href="`/admin/crm/contracts/${selectedContract.id}/export-doc/docx?lang=` + $i18n.locale" target="_blank" class="px-4 py-2 bg-neon-purple/20 border border-neon-purple text-neon-purple text-xs font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-purple hover:text-black transition-all shadow-sm">
                                💾 Shartnoma (Word)
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
                            <button v-if="selectedContract.status !== 'active'" @click="handleSotildiClick" :disabled="isProcessing" class="px-6 py-2 bg-emerald-50 border border-emerald-500 text-emerald-600 font-bold text-xs uppercase tracking-widest rounded hover:bg-emerald-600 hover:text-black transition-all shadow-sm disabled:opacity-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline-block mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Sotildi qilish
                            </button>
                        </div>
                    </div>

                    <!-- LEDGER TABLES -->
                    <div v-if="splitLedgerData.isSplit">
                        <!-- Down Payment Table -->
                        <div class="mb-8">
                            <h3 class="text-sm font-bold text-amber-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-600 shadow-sm animate-pulse"></span>
                                Boshlang'ich to'lovni bo'lib to'lash grafigi (Alohida mijoz varaqasi)
                            </h3>
                            <div class="overflow-x-auto rounded-xl border border-amber-200 bg-white">
                                <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left border-collapse">
                                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                        <tr class="border-b border-amber-200 bg-white hover:bg-gray-50 transition-colors">
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
                                            v-for="(schedule, index) in splitLedgerData.downPaymentRows" 
                                            :key="schedule.id" 
                                            class="border-b border-gray-100 hover:bg-gray-50 transition-colors bg-white hover:bg-gray-50 transition-colors"
                                            :class="{'bg-amber-50/30 border-l-2 border-l-amber-500': schedule.remaining_debt > 0 && new Date(schedule.due_date) < new Date()}"
                                        >
                                            <td class="p-3 text-sm font-bold text-gray-900">{{ index + 1 }}</td>
                                            <td class="p-3 text-sm font-mono text-gray-900/80">{{ schedule.due_date }}</td>
                                            <td class="p-3 text-sm font-mono text-gray-900/60">
                                                <div v-if="editingScheduleId === schedule.id">
                                                    <input type="date" v-model="editingActualPaymentDate" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-28 text-gray-900 text-xs py-1 px-2">
                                                </div>
                                                <div v-else>
                                                    {{ schedule.actual_payment_date || '-' }}
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
                        </div>

                        <!-- Installments Table -->
                        <div>
                            <h3 class="text-sm font-bold text-indigo-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-600 shadow-sm animate-pulse"></span>
                                Asosiy shartnoma bo'lib to'lash grafigi
                            </h3>
                            <div class="overflow-x-auto rounded-xl border border-indigo-200 bg-white">
                                <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left border-collapse">
                                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                        <tr class="border-b border-indigo-200 bg-white hover:bg-gray-50 transition-colors">
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
                                            v-for="(schedule, index) in splitLedgerData.installmentRows" 
                                            :key="schedule.id" 
                                            class="border-b border-gray-100 hover:bg-gray-50 transition-colors bg-white hover:bg-gray-50 transition-colors"
                                            :class="{'bg-indigo-50/30 border-l-2 border-l-indigo-500': schedule.remaining_debt > 0 && new Date(schedule.due_date) < new Date()}"
                                        >
                                            <td class="p-3 text-sm font-bold text-gray-900">{{ index + 1 }}</td>
                                            <td class="p-3 text-sm font-mono text-gray-900/80">{{ schedule.due_date }}</td>
                                            <td class="p-3 text-sm font-mono text-gray-900/60">
                                                <div v-if="editingScheduleId === schedule.id">
                                                    <input type="date" v-model="editingActualPaymentDate" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-28 text-gray-900 text-xs py-1 px-2">
                                                </div>
                                                <div v-else>
                                                    {{ schedule.actual_payment_date || '-' }}
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
                        </div>
                    </div>
                    <div v-else class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                        <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left border-collapse">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                <tr class="border-b border-gray-200 bg-white hover:bg-gray-50 transition-colors">
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
                                    v-for="(schedule, index) in calculatedSchedules" 
                                    :key="schedule.id" 
                                    class="border-b border-gray-100 hover:bg-gray-50 transition-colors bg-white hover:bg-gray-50 transition-colors"
                                    :class="{'bg-indigo-50 border-l-2 border-l-neon-magenta': schedule.remaining_debt > 0 && new Date(schedule.due_date) < new Date()}"
                                >
                                    <td class="p-3 text-sm font-bold text-gray-900">{{ index + 1 }}</td>
                                    <td class="p-3 text-sm font-mono text-gray-900/80">{{ schedule.due_date }}</td>
                                    <td class="p-3 text-sm font-mono text-gray-900/60">
                                        <div v-if="editingScheduleId === schedule.id">
                                            <input type="date" v-model="editingActualPaymentDate" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-28 text-gray-900 text-xs py-1 px-2">
                                        </div>
                                        <div v-else>
                                            {{ schedule.actual_payment_date || '-' }}
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

                    <!-- PAYMENTS HISTORY TABLE -->
                    <div v-if="clientPayments.length > 0" class="mt-8">
                        <h3 class="text-sm font-bold text-emerald-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-600 shadow-sm"></span>
                            Mijoz Amalga Oshirgan To'lovlar Tarixi
                        </h3>
                        <div class="overflow-x-auto rounded-xl border border-emerald-200">
                            <table class="block w-full overflow-x-auto whitespace-nowrap w-full text-left border-collapse">
                                <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium">
                                    <tr class="border-b border-emerald-200 bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                        <th class="p-3 text-xs font-mono text-emerald-600 font-normal uppercase tracking-wider">Tranzaksiya ID</th>
                                        <th class="p-3 text-xs font-mono text-emerald-600 font-normal uppercase tracking-wider">Sana</th>
                                        <th class="p-3 text-xs font-mono text-emerald-600 font-normal uppercase tracking-wider">To'lov Usuli</th>
                                        <th class="p-3 text-xs font-mono text-emerald-600 font-normal uppercase tracking-wider">Summa</th>
                                        <th class="p-3 text-xs font-mono text-emerald-600 font-normal uppercase tracking-wider">Izoh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="transaction in clientPayments" 
                                        :key="transaction.id" 
                                        class="border-b border-gray-100 hover: transition-colors bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors"
                                    >
                                        <td class="p-3 text-sm font-mono text-gray-900/80">#{{ transaction.id }}</td>
                                        <td class="p-3 text-sm font-mono text-gray-900/80">
                                            {{ transaction.transaction_date ? new Date(transaction.transaction_date).toLocaleDateString() : new Date(transaction.created_at).toLocaleDateString() }}
                                        </td>
                                        <td class="p-3 text-sm font-mono text-gray-900/60 uppercase">
                                            <span class="px-2 py-0.5 border border-gray-300 rounded text-[10px]">{{ transaction.payment_method }}</span>
                                        </td>
                                        <td class="p-3 text-sm font-mono font-bold text-emerald-600">{{ $currency(transaction.amount) }}</td>
                                        <td class="p-3 text-sm font-mono text-gray-900/60">{{ transaction.notes || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- REFUND SCHEDULES MODULE -->
                    <div v-if="selectedContract.status === 'cancelled' || selectedContract.status === 'canceled'" class="mt-8 p-6 bg-red-50/30 border border-red-200/60 rounded-2xl">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-sm font-bold text-red-700 uppercase tracking-widest flex items-center gap-2">
                                <span>💰</span> Mijozga qaytarish grafigi
                            </h3>
                            <button v-if="!showRefundGeneratorForm" @click="showRefundGeneratorForm = true; refundInstallments = JSON.parse(JSON.stringify(selectedContract.refund_schedules || []))" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                                {{ selectedContract.refund_schedules?.length ? '🎨 Grafikni qayta shakllantirish' : '➕ Grafik shakllantirish' }}
                            </button>
                            <button v-else @click="showRefundGeneratorForm = false" class="px-3 py-1 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                                ❌ Bekor qilish
                            </button>
                        </div>

                        <!-- Refund schedules generator form -->
                        <div v-if="showRefundGeneratorForm" class="space-y-4 bg-white p-5 border border-red-100 rounded-xl mb-6">
                            <div class="flex items-center gap-4 flex-wrap">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Bo'lib qaytarish oylari (soni):</label>
                                    <div class="flex gap-2">
                                        <input type="number" min="1" max="24" v-model="refundInstallmentCount" class="w-20 bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-xs text-center focus:ring-2 focus:ring-red-500 focus:border-red-500 font-bold">
                                        <button type="button" @click="distributeRefundInstallments" class="px-4 py-1.5 bg-red-50 border border-red-300 text-red-700 rounded-lg text-xs font-bold hover:bg-red-500 hover:text-white transition-all">
                                            Taqsimlash
                                        </button>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 font-medium">
                                    <p>Qaytariladigan umumiy summa: <span class="font-mono font-bold text-red-600">{{ $currency(contractIncomeSum - Number(selectedContract.refunded_amount || 0)) }}</span></p>
                                    <p class="text-[10px] text-gray-400">Ortiqcha to'lovlar hamda kiritilgan kirim operatsiyalari asosida.</p>
                                </div>
                            </div>

                            <!-- Installment items inputs list -->
                            <div v-if="refundInstallments.length > 0" class="space-y-3 pt-3 border-t border-gray-100 max-h-80 overflow-y-auto">
                                <div v-for="(inst, i) in refundInstallments" :key="i" class="grid grid-cols-1 md:grid-cols-3 gap-3 p-3 bg-gray-50 rounded-lg items-center border border-gray-150">
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Summa ({{ i + 1 }}-bosqich):</label>
                                        <input type="number" v-model="inst.amount" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-red-500 focus:border-red-500 font-mono font-bold">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Sana:</label>
                                        <input type="date" v-model="inst.due_date" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Izoh:</label>
                                        <input type="text" v-model="inst.notes" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                    </div>
                                </div>

                                <div class="pt-4 flex justify-end">
                                    <button type="button" @click="submitRefundSchedule" :disabled="isProcessing" class="px-6 py-2 bg-red-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-red-700 transition-all shadow-md disabled:opacity-50">
                                        💾 Grafikni saqlash
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Refund schedules list table -->
                        <div v-if="!showRefundGeneratorForm" class="overflow-x-auto rounded-xl border border-red-200 bg-white">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead class="bg-red-50 text-red-700 uppercase font-bold border-b border-red-200">
                                    <tr>
                                        <th class="p-3">Bosqich</th>
                                        <th class="p-3">Muddat (Sana)</th>
                                        <th class="p-3 text-right">Summa</th>
                                        <th class="p-3 text-right">Qaytarilgan</th>
                                        <th class="p-3 text-right">Qoldiq</th>
                                        <th class="p-3 text-center">Holat</th>
                                        <th class="p-3">Izoh</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(schedule, index) in selectedContract.refund_schedules" :key="schedule.id" class="hover:bg-red-50/20 transition-colors">
                                        <td class="p-3 font-bold text-gray-900">#{{ index + 1 }}</td>
                                        <td class="p-3 font-mono text-gray-900/80">{{ schedule.due_date }}</td>
                                        <td class="p-3 text-right font-bold text-gray-900 font-mono">{{ $currency(schedule.amount) }}</td>
                                        <td class="p-3 text-right text-emerald-600 font-mono">{{ $currency(schedule.paid_amount || 0) }}</td>
                                        <td class="p-3 text-right text-red-600 font-mono font-bold">{{ $currency(schedule.amount - (schedule.paid_amount || 0)) }}</td>
                                        <td class="p-3 text-center">
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] uppercase font-bold border"
                                                :class="{ 'bg-emerald-50 text-emerald-600 border-emerald-200': schedule.status === 'paid', 'bg-amber-50 text-amber-600 border-amber-200': schedule.status === 'partial', 'bg-red-50 text-red-600 border-red-200': schedule.status === 'overdue', 'text-gray-500 border-gray-300': schedule.status === 'pending' }">
                                                {{ schedule.status }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-gray-500">{{ schedule.notes || '-' }}</td>
                                    </tr>
                                    <tr v-if="!selectedContract.refund_schedules?.length">
                                        <td colspan="7" class="p-8 text-center text-gray-400">Bo'lib qaytarish grafigi shakllantirilmagan.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
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

        <!-- DOCUMENT PREVIEW MODAL (PREMIUM PREVIEW) -->
        <div v-if="showDocPreviewModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500/50 transition-all">
            <div class="bg-white border border-gray-200 shadow-xl w-full max-w-6xl rounded-2xl border border-blue-200 shadow-sm overflow-hidden flex flex-col h-[90vh]">
                <!-- Header -->
                <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                    <div class="flex items-center gap-2">
                        <span class="text-blue-600 text-xl font-bold">📄</span>
                        <h2 class="text-xl font-bold text-gray-900 font-sans">{{ docPreviewTitle }}</h2>
                    </div>
                    <button @click="showDocPreviewModal = false" class="text-gray-500 hover:text-gray-900 transition-colors">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <!-- Body: IFrame rendering the doc preview -->
                <div class="flex-1 bg-slate-100 p-4 relative overflow-hidden flex items-center justify-center">
                    <div v-if="docPreviewLoading" class="absolute inset-0 bg-white/80 backdrop-blur-sm flex flex-col items-center justify-center z-10 transition-all animate-in fade-in duration-300">
                        <!-- Spinning Rings -->
                        <div class="relative w-12 h-12 mb-4">
                            <div class="absolute inset-0 border-4 border-blue-600/20 rounded-full"></div>
                            <div class="absolute inset-0 border-4 border-t-blue-600 rounded-full animate-spin"></div>
                        </div>
                        <p class="text-xs font-mono text-gray-500 uppercase tracking-widest animate-pulse">Hujjat tayyorlanmoqda...</p>
                    </div>
                    <iframe :src="docPreviewUrl" @load="docPreviewLoading = false" class="w-full h-full rounded-lg border border-gray-200 bg-white shadow-inner" style="min-height: 100%;"></iframe>
                </div>

                <!-- Footer with Download Button -->
                <div class="p-4 border-t border-gray-200 flex justify-end gap-3 bg-gray-50">
                    <template v-if="docPreviewTitle === 'Shartnoma'">
                        <a :href="docDownloadDocxUrl" target="_blank" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-2">
                            💾 Word (.docx) Yuklab olish
                        </a>
                        <a :href="docDownloadUrl" target="_blank" class="px-6 py-2.5 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            PDF Yuklab olish
                        </a>
                    </template>
                    <a v-else :href="docDownloadUrl" target="_blank" class="px-6 py-2.5 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Yuklab olish
                    </a>
                </div>
            </div>
        </div>

        <!-- SOTILDI QILISH CONFIRMATION MODAL -->
        <div v-if="showSotildiConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-500/50 backdrop-blur-sm transition-all">
            <div class="bg-white border border-gray-200 shadow-2xl rounded-2xl w-full max-w-md overflow-hidden flex flex-col p-6 space-y-4">
                <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900 uppercase">Sotishni tasdiqlash</h3>
                    <button @click="showSotildiConfirmModal = false" class="text-gray-400 hover:text-gray-600 text-sm font-bold">
                        ✕
                    </button>
                </div>
                
                <div class="text-xs text-gray-600 space-y-1">
                    <p>Shartnoma bo'yicha boshlang'ich to'lov miqdori: <strong class="text-emerald-600">{{ $currency(selectedContract?.down_payment) }}</strong></p>
                    <p class="text-[10px] text-gray-400 font-mono">Iltimos, ushbu mablag' qaysi kassaga kirim qilinishini belgilang.</p>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                            * Kassaga yo'naltirish
                        </label>
                        <select v-model="sotildiAccountId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-colors" required>
                            <option value="" disabled>Kassani tanlang</option>
                            <option v-for="reg in cashRegisters" :key="reg.id" :value="reg.id">
                                {{ reg.name }} ({{ reg.currency }})
                            </option>
                        </select>
                    </div>

                    <div v-if="sotildiAccountId">
                        <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                            * To'lov usuli
                        </label>
                        <div class="flex gap-6 mt-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="sotildiPaymentMethod" value="main" class="text-amber-500 focus:ring-amber-500 bg-white border-gray-300">
                                <span class="text-xs text-gray-900 font-medium">Naqd</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="sotildiPaymentMethod" value="bank" class="text-amber-500 focus:ring-amber-500 bg-white border-gray-300">
                                <span class="text-xs text-gray-900 font-medium">Bank</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" v-model="sotildiPaymentMethod" value="credit" class="text-amber-500 focus:ring-amber-500 bg-white border-gray-300">
                                <span class="text-xs text-gray-900 font-medium">Nasiya</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 justify-end pt-3 border-t border-gray-100">
                    <button type="button" @click="showSotildiConfirmModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition-colors">
                        Bekor qilish
                    </button>
                    <button type="button" @click="confirmForceActivate" :disabled="!sotildiAccountId || isProcessing" class="px-5 py-2 bg-[#10b981] hover:bg-emerald-600 text-white text-xs font-bold rounded-lg transition-colors disabled:opacity-50">
                        Tasdiqlash & Sotildi qilish
                    </button>
                </div>
            </div>
        </div>
        </div>

        <!-- INLINE SUBPAGE: CREATE / EDIT CLIENT -->
        <div v-if="showCreateClientModal" class="bg-white p-8 rounded-3xl w-full shadow-md border border-gray-150 transition-all font-sans my-4">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-1.5 h-6 bg-[#f2a900] rounded-full"></span>
                    {{ isEditingClient ? 'Mijozni tahrirlash' : 'Mijoz qo\'shish' }}
                </h2>
                <button type="button" @click="showCreateClientModal = false" class="text-gray-400 hover:text-gray-600 transition-colors flex items-center gap-1 text-xs font-bold uppercase tracking-wider bg-white border border-gray-200 px-3 py-1.5 rounded-full shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Orqaga
                </button>
            </div>

            <!-- Stepper Progress Header -->
            <div v-if="!isEditingClient" class="mb-8 max-w-4xl mx-auto">
                <div class="flex items-center justify-between">
                    <!-- Step 1: Mijoz ma'lumotlari -->
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300"
                             :class="currentStep > 1 ? 'bg-[#f2a900] text-white' : (currentStep === 1 ? 'bg-[#f2a900] text-white ring-4 ring-amber-100' : 'bg-gray-100 text-gray-400')">
                            <span v-if="currentStep > 1">✓</span>
                            <span v-else>1</span>
                        </div>
                        <span class="text-xs font-semibold" :class="currentStep === 1 ? 'text-gray-900 font-bold' : 'text-gray-400'">Mijoz ma'lumotlari</span>
                    </div>

                    <!-- Divider Line 1-2 -->
                    <div class="flex-1 h-0.5 mx-4 transition-all duration-500"
                         :class="currentStep > 1 ? 'bg-[#f2a900]' : 'bg-gray-100'"></div>

                    <!-- Step 2: Shartnoma va xonadon -->
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300"
                             :class="currentStep > 2 ? 'bg-[#f2a900] text-white' : (currentStep === 2 ? 'bg-[#f2a900] text-white ring-4 ring-amber-100' : 'bg-gray-100 text-gray-400')">
                            <span v-if="currentStep > 2">✓</span>
                            <span v-else>2</span>
                        </div>
                        <span class="text-xs font-semibold" :class="currentStep === 2 ? 'text-gray-900 font-bold' : 'text-gray-400'">Shartnoma va xonadon / do'kon</span>
                    </div>

                    <!-- Divider Line 2-3 -->
                    <div class="flex-1 h-0.5 mx-4 transition-all duration-500"
                         :class="currentStep > 2 ? 'bg-[#f2a900]' : 'bg-gray-100'"></div>

                    <!-- Step 3: To'lov ma'lumotlari -->
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300"
                             :class="currentStep === 3 ? 'bg-[#f2a900] text-white ring-4 ring-amber-100' : 'bg-gray-100 text-gray-400'">
                            3
                        </div>
                        <span class="text-xs font-semibold" :class="currentStep === 3 ? 'text-gray-900 font-bold' : 'text-gray-400'">To'lov ma'lumotlari</span>
                    </div>
                </div>
            </div>

            <form @submit.prevent="saveClient" class="space-y-6">
                <!-- STEP 1: Mijoz ma'lumotlari -->
                <div v-show="isEditingClient || currentStep === 1" class="space-y-6">
                    <!-- Row 1: Familiya, Ism, Otasining ismi -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">* Familiya</label>
                            <input v-model="clientForm.last_name" type="text" placeholder="Familiyasini kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400" :required="isEditingClient || currentStep === 1">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">* Ism</label>
                            <input v-model="clientForm.first_name" type="text" placeholder="Ismini kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400" :required="isEditingClient || currentStep === 1">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Otasining ismi</label>
                            <input v-model="clientForm.middle_name" type="text" placeholder="Otasining ismini kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                        </div>
                    </div>

                    <!-- Row 2: Passport raqami, Berilgan vaqti, Kim tomonidan berilgan -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Passport raqami</label>
                            <input v-model="clientForm.passport_series" type="text" placeholder="AA1234567" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Berilgan vaqti</label>
                            <input v-model="clientForm.passport_issued_date" type="date" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Kim tomonidan berilgan</label>
                            <input v-model="clientForm.passport_issued_by" type="text" placeholder="Kim tomonidan berilgan" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                        </div>
                    </div>

                    <!-- Row 3: Viloyat, Tuman / Shahar, Mahalla, ko'cha nomi -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Viloyat</label>
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
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Mahalla, ko'cha nomi</label>
                            <input v-model="clientForm.address" type="text" list="crmNeighborhoods" placeholder="Manzilni kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                            <datalist id="crmNeighborhoods">
                                <option v-for="neighborhood in availableNeighborhoods" :key="neighborhood" :value="neighborhood"></option>
                            </datalist>
                        </div>
                    </div>

                    <!-- Row 4: JSHSHIR, Telefon raqam, Telefon raqam -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">JSHSHIR</label>
                            <input v-model="clientForm.pinfl" type="text" placeholder="JSHSHIR ni kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">* Telefon raqam</label>
                            <div class="relative">
                                <input v-model="clientForm.phone" type="text" placeholder="+998" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all pr-10" :required="isEditingClient || currentStep === 1">
                                <button type="button" @click="clientForm.phone = '+998'" class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Telefon raqam (Qo'shimcha)</label>
                            <div class="relative">
                                <input v-model="clientForm.phone_2" type="text" placeholder="+998" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all pr-10">
                                <button type="button" @click="clientForm.phone_2 = '+998'" class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Row 5: Pasport fayli va Shartnoma tili (if editing) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-2">Pasport fayli (Rasm yoki PDF)</label>
                            <label class="w-full h-24 border border-dashed border-gray-300 hover:border-[#f2a900] rounded-2xl flex flex-col justify-center items-center gap-1 cursor-pointer transition-all bg-white relative overflow-hidden shadow-sm">
                                <template v-if="!clientForm.passport_photo">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span class="text-[10px] text-gray-500 font-medium">Faylni tanlang</span>
                                </template>
                                <template v-else>
                                    <svg class="w-6 h-6 text-emerald-500 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-[9px] text-emerald-600 font-bold px-1 truncate max-w-full" :title="typeof clientForm.passport_photo === 'string' ? clientForm.passport_photo : clientForm.passport_photo.name">
                                        {{ typeof clientForm.passport_photo === 'string' ? 'Mavjud pasport fayli' : clientForm.passport_photo.name }}
                                    </span>
                                </template>
                                <input type="file" class="hidden" accept="image/*,application/pdf" @change="onPassportPhotoChange">
                            </label>
                            <!-- Face Preview -->
                            <div v-if="clientForm.profile_photo" class="mt-2 flex items-center gap-2">
                                <div class="w-10 h-10 rounded-full overflow-hidden border border-[#f2a900] shrink-0">
                                    <img :src="getProfilePreviewUrl(clientForm.profile_photo)" class="w-full h-full object-cover" />
                                </div>
                                <span class="text-[9px] text-gray-500 font-semibold leading-tight">Yuzingiz aniqlandi va profil rasmi yaratildi!</span>
                            </div>
                        </div>
                        <div v-if="isEditingClient">
                            <label class="block text-xs font-semibold text-gray-700 mb-2">Shartnoma tili</label>
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
                </div>

                <!-- STEP 2: Shartnoma va xonadon -->
                <div v-show="!isEditingClient && currentStep === 2" class="space-y-6">
                    <!-- Row 1: Source, Language, Contract Number -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5"><span class="text-red-500">*</span> Biz haqimizda qayerdan bildingiz?</label>
                            <div class="relative">
                                <select v-model="clientForm.source" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all appearance-none cursor-pointer pr-10" :required="!isEditingClient && currentStep === 2">
                                    <option value="" disabled>Tanlang</option>
                                    <option value="Website">Website</option>
                                    <option value="Telegram">Telegram</option>
                                    <option value="Instagram">Instagram</option>
                                    <option value="Tanish bilish">Tanish bilish</option>
                                </select>
                                <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5"><span class="text-red-500">*</span> Shartnoma tili</label>
                            <div class="relative">
                                <select v-model="clientForm.contract_language" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all appearance-none cursor-pointer pr-10" :required="!isEditingClient && currentStep === 2">
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
                                <input v-model="contractForm.contract_number" type="text" placeholder="Shartnoma kodi kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all placeholder:text-gray-400">
                                <span class="absolute right-4 top-3.5 text-gray-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Building, Entrance, Unit -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5"><span class="text-red-500">*</span> Bino</label>
                            <div class="relative">
                                <select v-model="selectedBuildingId" @change="onBuildingChange" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all appearance-none cursor-pointer pr-10" :required="!isEditingClient && currentStep === 2">
                                    <option value="">Blokni tanlang</option>
                                    <option v-for="opt in allBuildingOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5"><span class="text-red-500">*</span> Podezd</label>
                            <div class="relative">
                                <select v-model="selectedPodyezd" @change="onPodyezdChange" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all appearance-none cursor-pointer pr-10" :required="!isEditingClient && currentStep === 2">
                                    <option value="">Podezdni tanlang</option>
                                    <option v-for="p in availablePodyezds" :key="p" :value="p">
                                        {{ p }}
                                    </option>
                                </select>
                                <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5"><span class="text-red-500">*</span> Xonadon / Do'kon</label>
                            <div class="relative">
                                <select v-model="contractForm.unit_id" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all appearance-none cursor-pointer pr-10" :required="!isEditingClient && currentStep === 2">
                                    <option value="">Xonadon / Do'konni tanlang</option>
                                    <option v-for="opt in wizardUnitOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: To'lov ma'lumotlari -->
                <div v-show="!isEditingClient && currentStep === 3" class="space-y-6">
                    <!-- Row 1: Base Price, Markup Price, Price per Sqm, Area -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Uy narxi</label>
                            <div class="relative flex items-center">
                                <input type="text" readonly :value="basePrice.toLocaleString()" class="w-full bg-gray-50 border border-gray-200 text-gray-500 text-xs rounded-xl pl-4 pr-12 py-3 cursor-not-allowed font-medium">
                                <span class="absolute right-4 text-xs font-semibold text-gray-400 pointer-events-none">UZS</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Ustama bilan uy narxi</label>
                            <div class="relative flex items-center">
                                <input type="text" readonly :value="markupPrice.toLocaleString()" class="w-full bg-gray-50 border border-gray-200 text-gray-500 text-xs rounded-xl pl-4 pr-12 py-3 cursor-not-allowed font-medium">
                                <span class="absolute right-4 text-xs font-semibold text-gray-400 pointer-events-none">UZS</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">1 m² maydon narxi (Ustamasiz)</label>
                            <div class="relative flex items-center">
                                <input type="text" readonly :value="pricePerSqm.toLocaleString()" class="w-full bg-gray-50 border border-gray-200 text-gray-500 text-xs rounded-xl pl-4 pr-12 py-3 cursor-not-allowed font-medium">
                                <span class="absolute right-4 text-xs font-semibold text-gray-400 pointer-events-none">UZS</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Maydoni</label>
                            <div class="relative flex items-center">
                                <input type="text" readonly :value="selectedUnit?.area_sqm || '0'" class="w-full bg-gray-50 border border-gray-200 text-gray-500 text-xs rounded-xl pl-4 pr-12 py-3 cursor-not-allowed font-medium">
                                <span class="absolute right-4 text-xs font-semibold text-gray-400 pointer-events-none">m²</span>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Repair type, Payment type, Markup %, Downpayment -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-5 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Tamir turi</label>
                            <div class="flex bg-gray-100 p-1 rounded-xl h-[42px] items-center">
                                <button type="button" @click="repairType = 'tamirli'"
                                        class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all"
                                        :class="repairType === 'tamirli' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                    Tamirli
                                </button>
                                <button type="button" @click="repairType = 'tamirsiz'"
                                        class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all"
                                        :class="repairType === 'tamirsiz' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                    Tamirsiz
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5"><span class="text-red-500">*</span> Bo'lib to'lash turi</label>
                            <div class="relative">
                                <select v-model="contractForm.payment_type" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all appearance-none cursor-pointer pr-10" :required="!isEditingClient && currentStep === 3">
                                    <option value="installment">Bo'lib to'lash</option>
                                    <option value="full">Bir martalik to'liq to'lov</option>
                                </select>
                                <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5"><span class="text-red-500">*</span> Foiz stavkasi (%)</label>
                            <input v-model.number="markupPercent" type="number" min="0" placeholder="0" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all" :required="!isEditingClient && currentStep === 3">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5"><span class="text-red-500">*</span> Oldindan to'lov</label>
                            <div class="relative flex items-center">
                                <input v-model.number="contractForm.down_payment" type="number" min="0" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl pl-4 pr-24 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all" :required="!isEditingClient && currentStep === 3">
                                <span class="absolute right-4 text-[10px] font-semibold text-gray-400 pointer-events-none">
                                    {{ finalContractPrice > 0 ? Math.round((contractForm.down_payment / finalContractPrice) * 100) : 0 }}% • UZS
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Term months, Discount toggle -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-center">
                        <div v-if="contractForm.payment_type === 'installment'">
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5"><span class="text-red-500">*</span> Nechi oyda bo'lib to'lash</label>
                            <div class="relative">
                                <select v-model.number="contractForm.term_months" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all appearance-none cursor-pointer pr-10" :required="!isEditingClient && currentStep === 3 && contractForm.payment_type === 'installment'">
                                    <option value="" disabled>Tanlang</option>
                                    <option :value="12">12 oy</option>
                                    <option :value="18">18 oy</option>
                                    <option :value="24">24 oy</option>
                                    <option :value="36">36 oy</option>
                                    <option :value="48">48 oy</option>
                                </select>
                                <span class="absolute right-3 top-3.5 text-gray-400 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3" :class="contractForm.payment_type === 'installment' ? 'pt-6' : ''">
                            <button type="button" @click="hasDiscount = !hasDiscount"
                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none animate-all"
                                    :class="hasDiscount ? 'bg-[#f2a900]' : 'bg-gray-200'">
                                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                      :class="hasDiscount ? 'translate-x-5' : 'translate-x-0'"></span>
                            </button>
                            <span class="text-xs font-semibold text-gray-700">Chegirma</span>
                        </div>
                    </div>

                    <!-- Row 4: Discount details -->
                    <div v-if="hasDiscount" class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Chegirma foizi</label>
                            <div class="relative flex items-center">
                                <input v-model.number="discountPercent" type="number" min="0" max="100" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl pl-4 pr-12 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all">
                                <span class="absolute right-4 text-xs font-semibold text-gray-400 pointer-events-none">%</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Chegirma miqdori</label>
                            <div class="relative flex items-center">
                                <input v-model.number="discountAmount" type="number" min="0" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl pl-4 pr-16 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all">
                                <span class="absolute right-4 text-xs font-semibold text-gray-400 pointer-events-none">UZS</span>
                            </div>
                        </div>
                    </div>

                    <!-- Shablon va variantlar -->
                    <div v-if="filteredTemplates.length > 0" class="border-t border-gray-100 pt-6 mt-6 space-y-4">
                        <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-2 flex items-center gap-2">
                            <span class="w-1 h-4 bg-[#f2a900] rounded-full"></span>
                            Shartnoma Shablon va Shartlari
                        </h4>
                        
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Shartnoma shabloni</label>
                            <select v-model="contractForm.contract_template_id" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#f2a900]/20 focus:border-[#f2a900] transition-all appearance-none cursor-pointer pr-10">
                                <option value="">Tizimning standart shabloni (Tizim bo'yicha)</option>
                                <option v-for="tpl in filteredTemplates" :key="tpl.id" :value="tpl.id">
                                    {{ tpl.name }} ({{ tpl.type === 'residential' ? 'Turar-joy' : (tpl.type === 'commercial' ? 'No-turar joy' : 'Boshqa') }})
                                </option>
                            </select>
                        </div>

                        <!-- Dynamic Variant Groups -->
                        <div v-if="selectedTemplate && selectedTemplate.variant_groups && selectedTemplate.variant_groups.length > 0" class="mt-3 space-y-4 border border-blue-200 rounded-xl p-3 bg-blue-500/5">
                            <h5 class="text-[11px] font-bold text-blue-600 uppercase tracking-wider mb-2">Shartnomaning o'zgaruvchan bandlari (Variantlar)</h5>
                            
                            <div v-for="group in selectedTemplate.variant_groups" :key="group.id" class="space-y-3">
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ group.label }} *</label>
                                    <select v-model="contractForm.selected_variants[group.anchor].key" @change="onVariantChange(group.anchor)" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs py-2 px-3 text-gray-900">
                                        <option value="">-- Tanlang --</option>
                                        <option v-for="v in group.variants" :key="v.id" :value="v.key">{{ v.label }}</option>
                                    </select>
                                </div>

                                <!-- Dynamic Sub-placeholders for Selected Variant -->
                                <div v-if="getSelectedVariant(group.anchor)" class="grid grid-cols-2 gap-4 pl-3 border-l-2 border-blue-300">
                                    <div v-for="pKey in getSelectedVariant(group.anchor).placeholders" :key="pKey">
                                        <label class="block text-[9px] font-mono text-gray-500 mb-1 uppercase">{{ pKey }}</label>
                                        <input v-model="contractForm.selected_variants[group.anchor].placeholders[pKey]" 
                                               @input="onSubPlaceholderInput(group.anchor, pKey)"
                                               type="text" 
                                               class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-full text-xs py-1.5 px-2 text-gray-900">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Template Placeholders (Editable Manually) -->
                        <div v-if="customPlaceholders.length > 0" class="mt-3 space-y-3 border border-gray-200 rounded-xl p-3 bg-gray-50">
                            <h5 class="text-[11px] font-bold text-gray-700 uppercase tracking-wider">Qo'shimcha shablon o'zgaruvchilari (Qo'lda kiritish)</h5>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div v-for="pKey in customPlaceholders" :key="pKey">
                                    <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ pKey }}</label>
                                    <input v-model="contractForm.selected_variants.custom_placeholders[pKey]" 
                                           type="text" 
                                           class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-full text-xs py-1.5 px-2 text-gray-900"
                                           :placeholder="pKey + ' qiymatini kiriting'">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end gap-3 pt-6 border-t border-gray-150 mt-8">
                    <template v-if="isEditingClient">
                        <button type="button" @click="showCreateClientModal = false"
                                class="px-6 py-2.5 text-xs font-bold text-gray-800 bg-white border border-gray-300 rounded-full hover:bg-gray-50 transition-all min-w-[120px]">
                            Bekor qilish
                        </button>
                        <button type="submit" :disabled="isProcessing"
                                class="px-6 py-2.5 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-full transition-all min-w-[120px] shadow-sm disabled:opacity-50">
                            {{ isProcessing ? 'Saqlanmoqda...' : 'Saqlash' }}
                        </button>
                    </template>
                    <template v-else>
                        <button v-if="currentStep > 1" type="button" @click="prevStep"
                                class="px-6 py-2.5 text-xs font-bold text-gray-800 bg-white border border-gray-300 rounded-full hover:bg-gray-50 transition-all min-w-[120px]">
                            Orqaga
                        </button>
                        <button v-else type="button" @click="showCreateClientModal = false"
                                class="px-6 py-2.5 text-xs font-bold text-gray-800 bg-white border border-gray-300 rounded-full hover:bg-gray-50 transition-all min-w-[120px]">
                            Bekor qilish
                        </button>
                        
                        <button v-if="currentStep < 3" type="button" @click="nextStep"
                                class="px-6 py-2.5 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-full transition-all min-w-[120px] shadow-sm">
                            Davom etish
                        </button>
                        <button v-else type="submit" :disabled="isProcessing"
                                class="px-6 py-2.5 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-full transition-all min-w-[120px] shadow-sm disabled:opacity-50">
                            {{ isProcessing ? 'Saqlanmoqda...' : 'Saqlash' }}
                        </button>
                    </template>
                </div>
            </form>
        </div>

        <!-- MODAL: CREATE CONTRACT -->
        <div v-if="showCreateContractModal" class="bg-white p-8 rounded-3xl w-full shadow-md border border-gray-150 transition-all font-sans my-4">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <span class="w-1.5 h-6 bg-[#f2a900] rounded-full"></span>
                    {{ isEditingContract ? 'Shartnomani Tahrirlash' : $t('add') + ' ' + $t('contract') }}
                </h2>
                <button type="button" @click="showCreateContractModal = false" class="text-gray-400 hover:text-gray-600 transition-colors flex items-center gap-1 text-xs font-bold uppercase tracking-wider bg-white border border-gray-200 px-3 py-1.5 rounded-full shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Orqaga
                </button>
            </div>
            <form @submit.prevent="createContract" class="space-y-4">
                <div class="space-y-4 pb-4">
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
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Sotuv xodimi (Sotuvchi)</label>
                                <select v-model="contractForm.user_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full p-2 text-xs h-9">
                                    <option value="">Sotuvchini tanlang (Ixtiyoriy)</option>
                                    <option v-for="user in salesAgentsList" :key="user.id" :value="user.id">
                                        {{ user.name }}
                                    </option>
                                </select>
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
                                <SearchableSelect 
                                    v-model="selectedFacilityName"
                                    @change="onFacilityChange"
                                    :options="facilityOptions"
                                    placeholder="Obyektni tanlang..."
                                    required
                                />
                            </div>

                            <!-- Block Select -->
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Blok Tanlash (Mavjud: {{ buildingsForSelectedFacility.length }})</label>
                                <SearchableSelect 
                                    v-model="selectedBuildingId"
                                    @change="onBuildingChange"
                                    :disabled="!selectedFacilityName"
                                    :options="buildingOptions"
                                    placeholder="Blokni tanlang..."
                                    required
                                />
                            </div>

                            <!-- Unit Select -->
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Xonadon/Do'kon tanlang (Mavjud: {{ sortedUnitsForSelectedBuilding.length }})</label>
                                <SearchableSelect 
                                    v-model="contractForm.unit_id"
                                    :disabled="!selectedBuildingId"
                                    :options="unitOptions"
                                    placeholder="Xonadon/Do'kon tanlang..."
                                    required
                                />
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

                        <!-- Shartnoma shabloni Selection -->
                        <div v-if="filteredTemplates.length > 0" class="mt-2">
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Shartnoma shabloni (Ixtiyoriy)</label>
                            <select v-model="contractForm.contract_template_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs py-2 px-3 text-gray-900">
                                <option value="">Tizimning standart shabloni (Tizim bo'yicha)</option>
                                <option v-for="tpl in filteredTemplates" :key="tpl.id" :value="tpl.id">
                                    {{ tpl.name }} ({{ tpl.type === 'residential' ? 'Turar-joy' : (tpl.type === 'commercial' ? 'No-turar joy' : 'Boshqa') }})
                                </option>
                            </select>
                        </div>

                        <!-- Dynamic Variant Groups -->
                        <div v-if="selectedTemplate && selectedTemplate.variant_groups && selectedTemplate.variant_groups.length > 0" class="mt-3 space-y-4 border border-blue-200 rounded-xl p-3 bg-blue-500/10">
                            <h5 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">Shartnomaning o'zgaruvchan bandlari (Variantlar)</h5>
                            
                            <div v-for="group in selectedTemplate.variant_groups" :key="group.id" class="space-y-3">
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ group.label }} *</label>
                                    <select v-model="contractForm.selected_variants[group.anchor].key" @change="onVariantChange(group.anchor)" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-xs py-2 px-3 text-gray-900">
                                        <option value="">-- Tanlang --</option>
                                        <option v-for="v in group.variants" :key="v.id" :value="v.key">{{ v.label }}</option>
                                    </select>
                                </div>

                                <!-- Dynamic Sub-placeholders for Selected Variant -->
                                <div v-if="getSelectedVariant(group.anchor)" class="grid grid-cols-2 gap-4 pl-3 border-l-2 border-blue-300">
                                    <div v-for="pKey in getSelectedVariant(group.anchor).placeholders" :key="pKey">
                                        <label class="block text-[9px] font-mono text-gray-500 mb-1 uppercase">{{ pKey }}</label>
                                        <input v-model="contractForm.selected_variants[group.anchor].placeholders[pKey]" 
                                               @input="onSubPlaceholderInput(group.anchor, pKey)"
                                               type="text" 
                                               class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-full text-xs py-1.5 px-2 text-gray-900">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Template Placeholders (Editable Manually) -->
                        <div v-if="customPlaceholders.length > 0" class="mt-3 space-y-3 border border-gray-200 rounded-xl p-3 bg-gray-50">
                            <h5 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Qo'shimcha shablon o'zgaruvchilari (Qo'lda kiritish)</h5>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div v-for="pKey in customPlaceholders" :key="pKey">
                                    <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">{{ pKey }}</label>
                                    <input v-model="contractForm.selected_variants.custom_placeholders[pKey]" 
                                           type="text" 
                                           class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 w-full text-xs py-1.5 px-2 text-gray-900"
                                           :placeholder="pKey + ' qiymatini kiriting'">
                                </div>
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
                                <div v-if="Number(contractForm.actual_paid_amount) > 0">
                                    <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Kassaga yo'naltirish</label>
                                    <select v-model="contractForm.account_id" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" :required="Number(contractForm.actual_paid_amount) > 0">
                                        <option value="" disabled>Kassani tanlang</option>
                                        <option v-for="register in filteredCashRegisters" :key="register.id" :value="register.id">
                                            {{ register.name }} ({{ register.type === 'bank' ? 'Bank' : 'Naqd' }})
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <p v-if="contractForm.actual_paid_amount > 0" class="text-[10px] text-gray-500 mt-2 font-mono">
                                Moliya bo'limi ushbu to'lovni tasdiqlayotganda ortiqcha summa avtomatik ravishda <strong class="text-amber-600">Naqdlashtirish (Qaytim)</strong> navbatiga tushadi.
                            </p>
                        </div>
                    </div>

                    <!-- Fixed Footer Actions -->
                    <div class="flex justify-end gap-4 mt-4 pt-4 border-t border-gray-100">
                        <button type="button" @click="showCreateContractModal = false" class="px-6 py-2.5 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="isProcessing" class="px-8 py-2.5 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-sm">{{ isEditingContract ? 'Saqlash' : $t('create_pipeline') }}</button>
                    </div>
                </form>
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

        <!-- LEAD INTERACTION FULLSCREEN PAGE -->
        <div v-if="showCallPanelModal && selectedLeadForDetail" class="fixed inset-0 z-[100] bg-slate-50 text-slate-800 font-sans flex flex-col select-none overflow-y-auto w-full h-full animate-[fadeIn_0.3s_ease-out]">
            <div class="flex flex-col w-full min-h-screen">
                <!-- Header -->
                <div class="px-8 py-5 border-b border-gray-200/60 bg-white/70 backdrop-blur-md flex justify-between items-center sticky top-0 z-10 shadow-sm">
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
                            <form @submit.prevent="saveCallPanelClientInfo" class="space-y-6">
                                <!-- Row 1: Familiya, Ism, Otasining ismi -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">* Familiya</label>
                                        <input v-model="clientInfoForm.last_name" type="text" placeholder="Familiyasini kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">* Ism</label>
                                        <input v-model="clientInfoForm.first_name" type="text" placeholder="Ismini kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Otasining ismi</label>
                                        <input v-model="clientInfoForm.middle_name" type="text" placeholder="Otasining ismini kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                                    </div>
                                </div>

                                <!-- Row 2: Passport raqami, Berilgan vaqti, Kim tomonidan berilgan -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Passport raqami</label>
                                        <input v-model="clientInfoForm.passport_series" type="text" placeholder="AA1234567" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Berilgan vaqti</label>
                                        <input v-model="clientInfoForm.passport_issued_date" type="date" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Kim tomonidan berilgan</label>
                                        <input v-model="clientInfoForm.passport_issued_by" type="text" placeholder="Kim tomonidan berilgan" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                                    </div>
                                </div>

                                <!-- Row 3: Viloyat, Tuman, Mahalla ko'cha -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Viloyat</label>
                                        <input v-model="clientInfoForm.region" type="text" placeholder="Viloyatni kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Tuman / Shahar</label>
                                        <input v-model="clientInfoForm.district" type="text" placeholder="Tuman / Shaharni kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Mahalla, ko'cha nomi</label>
                                        <input v-model="clientInfoForm.address" type="text" placeholder="Manzilni kiriting" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                                    </div>
                                </div>

                                <!-- Row 4: Phone, Phone 2, Email -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">* Telefon raqami</label>
                                        <input v-model="clientInfoForm.phone" type="text" placeholder="+998" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Qo'shimcha raqam</label>
                                        <input v-model="clientInfoForm.phone_2" type="text" placeholder="+998" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Elektron pochta</label>
                                        <input v-model="clientInfoForm.email" type="email" placeholder="example@mail.com" class="w-full bg-white border border-gray-200 text-gray-800 text-xs rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-gray-400">
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
                        <div v-if="activeCallPanelTab === 'house'" class="space-y-6 text-left flex flex-col items-center justify-center h-full">
                            <div class="text-center w-full max-w-sm mx-auto">
                                <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center text-amber-500 mx-auto mb-6 shadow-sm border border-amber-100">
                                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 mb-3 font-sans">Uy sotib olish va Shartnoma</h3>
                                <p class="text-sm text-gray-500 mb-8 font-sans leading-relaxed">
                                    Ushbu lid uchun obyektni tanlash, narxlarni belgilash va to'liq shartnoma yaratish jarayonini boshlash uchun quyidagi tugmani bosing.
                                </p>
                                <button @click="openContractModalForLead" class="w-full py-4 bg-[#f2a900] text-white font-bold rounded-2xl hover:bg-[#d99700] transition-all shadow-[0_8px_16px_-6px_rgba(242,169,0,0.4)] flex items-center justify-center gap-2 text-sm uppercase tracking-wider font-mono">
                                    <span>Shartnoma rasmiylashtirish</span>
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </button>
                            </div>
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

        <!-- INLINE SUBPAGE: CREATE LEAD (SIMPLE) -->
        <div v-if="showCreateLeadModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showCreateLeadModal = false">
            <div class="bg-white rounded-2xl w-full max-w-[450px] shadow-2xl overflow-hidden animate-[fadeIn_0.2s_ease-out]">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-white">
                    <h2 class="text-[17px] font-bold text-gray-900">
                        {{ isEditingLead ? 'Lidni tahrirlash' : 'Yangi lid qo\'shish' }}
                    </h2>
                    <button type="button" @click="showCreateLeadModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="p-6 bg-white">
                    <form @submit.prevent="submitNewLead" class="space-y-4">
                        <!-- Ism va familiya -->
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5"><span class="text-red-500">*</span> Ism va familiya</label>
                            <input v-model="newLeadForm.full_name" required type="text" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none" placeholder="Alisher Karimov">
                        </div>
                        
                        <!-- Telefon raqami -->
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5"><span class="text-red-500">*</span> Telefon raqami</label>
                            <input v-model="newLeadForm.phone" required type="tel" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none" placeholder="+998 99 894 20 00">
                        </div>

                        <!-- Manba -->
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5"><span class="text-red-500">*</span> Manba</label>
                            <select v-model="newLeadForm.source" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none">
                                <option v-for="source in leadSources" :key="source.id" :value="source.name.toLowerCase()">{{ source.name }}</option>
                                <option value="manual">Tanish-bilish</option>
                            </select>
                        </div>

                        <!-- Byudjet & Valyuta -->
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Byudjet</label>
                                <input v-model="newLeadForm.budget" type="text" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none" placeholder="100,000,000">
                            </div>
                            <div class="col-span-1">
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Valyuta</label>
                                <select v-model="newLeadForm.currency" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none uppercase">
                                    <option value="uzs">uzs</option>
                                    <option value="usd">usd</option>
                                </select>
                            </div>
                        </div>

                        <!-- Izoh -->
                        <div>
                            <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Izoh</label>
                            <textarea v-model="newLeadForm.comment" rows="2" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none" placeholder="boshlangich tolov qilmoqchi"></textarea>
                        </div>

                        <!-- Buttons -->
                        <div class="flex flex-col gap-2 pt-2">
                            <button type="submit" :disabled="isProcessing" class="w-full bg-[#f2a900] text-white py-2.5 rounded-lg text-[15px] font-semibold hover:bg-[#e09c00] transition-all disabled:opacity-50 border border-[#f2a900]">
                                {{ isProcessing ? 'Saqlanmoqda...' : 'Saqlash' }}
                            </button>
                            <button type="button" @click="showCreateLeadModal = false" class="w-full bg-white border border-gray-200 text-gray-700 py-2.5 rounded-lg text-[15px] font-medium hover:bg-gray-50 transition-all">
                                Bekor qilish
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- INLINE SUBPAGE: CREATE LEAD (DETAILED) -->
        <div v-if="showDetailedLeadModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showDetailedLeadModal = false">
            <div class="bg-white rounded-2xl w-full max-w-[900px] shadow-2xl overflow-hidden animate-[fadeIn_0.2s_ease-out]">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-white">
                    <h2 class="text-[17px] font-bold text-gray-900 uppercase">
                        {{ isEditingLead ? 'Lidni tahrirlash' : 'Mijozni qo\'shish' }}
                    </h2>
                    <button type="button" @click="showDetailedLeadModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="p-6 bg-white">
                    <form @submit.prevent="submitNewLead" class="space-y-4">
                        <!-- Qator 1: Ism, Tel 1, Tel 2 -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5"><span class="text-red-500">*</span> Mijoz ismi</label>
                                <input v-model="newLeadForm.full_name" required type="text" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none" placeholder="Familiya ism sharifi">
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5"><span class="text-red-500">*</span> Telefon raqam</label>
                                <input v-model="newLeadForm.phone" required type="tel" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none" placeholder="Telefon raqami">
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Qo'shimcha raqami</label>
                                <input v-model="newLeadForm.phone_2" type="tel" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none" placeholder="Qo'shimcha raqami">
                            </div>
                        </div>
                        
                        <!-- Qator 2: Passport, Gender, Birth Date -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Pasport seriyasi</label>
                                <input v-model="newLeadForm.passport_series" type="text" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none uppercase" placeholder="Pasport seriyasi">
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Jinsini tanlang</label>
                                <select v-model="newLeadForm.gender" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none">
                                    <option value="">Jinsini tanlang</option>
                                    <option value="Erkak">Erkak</option>
                                    <option value="Ayol">Ayol</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Tug'ilgan sanasi</label>
                                <input v-model="newLeadForm.birth_date" type="date" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none">
                            </div>
                        </div>

                        <!-- Qator 3: Region, District, Address -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Viloyat</label>
                                <select v-model="newLeadForm.region" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none">
                                    <option value="">Viloyatni tanlang</option>
                                    <option v-for="r in regionsData" :key="r.name" :value="r.name">{{ r.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Tuman / Shahar</label>
                                <select v-model="newLeadForm.district" :disabled="!newLeadForm.region" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none disabled:bg-gray-100 disabled:text-gray-400">
                                    <option value="">Tuman / Shaharni tanlang</option>
                                    <option v-for="d in leadAvailableDistricts" :key="d" :value="d">{{ d }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Mahalla, ko'cha, uy</label>
                                <input v-model="newLeadForm.address" type="text" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none" placeholder="Manzilni kiriting">
                            </div>
                        </div>

                        <!-- Qator 4: Tashkilot xodimi, Manba, Izoh -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Tashkilot xodimi</label>
                                <select v-model="newLeadForm.assigned_employee_id" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none">
                                    <option value="">Xodimni tanlang</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.first_name }} {{ user.last_name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Vakil / Manba</label>
                                <select v-model="newLeadForm.source" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none">
                                    <option v-for="source in leadSources" :key="source.id" :value="source.name.toLowerCase()">{{ source.name }}</option>
                                    <option value="manual">Boshqa</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Izoh qoldiring</label>
                                <input v-model="newLeadForm.comment" type="text" class="w-full border border-gray-200 rounded-lg p-2.5 text-gray-900 text-[14px] focus:border-[#f2a900] focus:ring-1 focus:ring-[#f2a900] transition-all outline-none" placeholder="Izoh">
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 mt-6">
                            <button type="button" @click="showDetailedLeadModal = false" class="px-6 py-2.5 bg-white border border-gray-200 text-gray-700 rounded-lg text-[15px] font-medium hover:bg-gray-50 transition-all">
                                Bekor qilish
                            </button>
                            <button type="submit" :disabled="isProcessing" class="px-8 py-2.5 bg-[#f2a900] text-white rounded-lg text-[15px] font-semibold hover:bg-[#e09c00] transition-all disabled:opacity-50">
                                {{ isProcessing ? 'Saqlanmoqda...' : 'Saqlash' }}
                            </button>
                        </div>
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
                        <span class="text-base font-bold font-sans">{{ activeTab === 'contracts' ? 'Shartnomalar ro\'yxati' : 'Mijozlar ro\'yxati' }}</span>
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
                            <thead v-if="activeTab === 'contracts'">
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
                                    <th class="px-3 py-1 bg-[#f1f3f4] text-[10px]">I</th>
                                </tr>
                                <!-- Column header names (Row 1) -->
                                <tr class="border-b border-gray-300 text-center font-bold text-gray-700 bg-gray-50/50">
                                    <td class="border-r border-gray-200 py-1.5 bg-[#f1f3f4] text-gray-400 font-medium text-center">1</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 text-left font-bold text-gray-900">Shartnoma kodi</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Sana</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Xaridor F.I.Sh</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Telefon</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Bino</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Xonadon / Do'kon</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Boshlang'ich to'lov</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Jami Summa</td>
                                    <td class="px-3 py-1.5 font-bold text-gray-900">Holat</td>
                                </tr>
                            </thead>
                            <thead v-else>
                                <!-- Column letter indicators -->
                                <tr class="bg-gray-50 text-gray-400 font-medium text-center border-b border-gray-200">
                                    <th class="border-r border-gray-200 w-10 py-1 bg-[#f1f3f4]"></th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">A</th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">B</th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">C</th>
                                    <th class="border-r border-gray-200 px-3 py-1 bg-[#f1f3f4] text-[10px]">D</th>
                                    <th class="px-3 py-1 bg-[#f1f3f4] text-[10px]">E</th>
                                </tr>
                                <!-- Column header names (Row 1) -->
                                <tr class="border-b border-gray-300 text-center font-bold text-gray-700 bg-gray-50/50">
                                    <td class="border-r border-gray-200 py-1.5 bg-[#f1f3f4] text-gray-400 font-medium text-center">1</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 text-left font-bold text-gray-900">Ф.И.О.</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Telefon</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">ЖШШИР</td>
                                    <td class="border-r border-gray-200 px-3 py-1.5 font-bold text-gray-900">Паспорт</td>
                                    <td class="px-3 py-1.5 font-bold text-gray-900">Қоʻшилган сана</td>
                                </tr>
                            </thead>
                            <tbody v-if="activeTab === 'contracts'" class="divide-y divide-gray-200 font-mono text-[11px]">
                                <tr v-for="(contract, index) in flatGroupedContracts" :key="contract.id" class="hover:bg-gray-50/50 border-b border-gray-200 text-gray-800">
                                    <!-- Row number indicator -->
                                    <td class="border-r border-gray-200 py-1 text-center bg-[#f1f3f4] text-gray-400 font-medium w-10 select-none">
                                        {{ index + 2 }}
                                    </td>
                                    <!-- Shartnoma kodi -->
                                    <td class="border-r border-gray-200 px-3 py-1 font-sans text-left font-semibold text-gray-900">
                                        {{ contract.contract_number || 'Contract #' + contract.id.toString().padStart(5, '0') }}
                                    </td>
                                    <!-- Sana -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-center text-gray-600">
                                        {{ new Date(contract.created_at).toLocaleDateString('uz-UZ') }}
                                    </td>
                                    <!-- Xaridor F.I.Sh -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-left text-gray-950 font-medium font-sans">
                                        {{ contract.client ? (contract.client.last_name + ' ' + contract.client.first_name) : '—' }}
                                    </td>
                                    <!-- Telefon -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-center text-gray-900">
                                        {{ contract.client?.phone || '—' }}
                                    </td>
                                    <!-- Bino -->
                                    <td class="border-r border-gray-200 px-3 py-1 font-sans text-gray-700">
                                        {{ contract.unit?.floor?.building?.name || '—' }}
                                    </td>
                                    <!-- Xonadon / Do'kon -->
                                    <td class="border-r border-gray-200 px-3 py-1 font-sans text-gray-700 text-center">
                                        {{ (contract.unit?.type === 'commercial' && /^\d+$/.test(String(contract.unit?.unit_number).trim()) ? 'D-' : '') + (contract.unit?.unit_number || '—') }}
                                    </td>
                                    <!-- Boshlang'ich to'lov -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-right text-gray-700 font-semibold">
                                        {{ $currency(contract.down_payment) }}
                                    </td>
                                    <!-- Jami Summa -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-right text-emerald-600 font-bold">
                                        {{ $currency(contract.total_amount) }}
                                    </td>
                                    <!-- Holat -->
                                    <td class="px-3 py-1 text-center font-sans">
                                        {{ contract.status === 'active' || contract.status === 'approved' ? 'Faol' : (contract.status === 'cancelled' || contract.status === 'canceled' ? 'Bekor qilingan' : contract.status) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tbody v-else class="divide-y divide-gray-200 font-mono text-[11px]">
                                <tr v-for="(client, index) in filteredClients" :key="client.id" class="hover:bg-gray-50/50 border-b border-gray-200 text-gray-800">
                                    <!-- Row number indicator -->
                                    <td class="border-r border-gray-200 py-1 text-center bg-[#f1f3f4] text-gray-400 font-medium w-10 select-none">
                                        {{ index + 2 }}
                                    </td>
                                    <!-- F.I.O. -->
                                    <td class="border-r border-gray-200 px-3 py-1 font-sans text-left font-medium text-gray-900">
                                        {{ client.last_name }} {{ client.first_name }} {{ client.middle_name || '' }}
                                    </td>

                                    <!-- Telefon -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-center text-gray-900">
                                        {{ client.phone ? client.phone.replace('+998', '') : '—' }}
                                    </td>
                                    <!-- PINFL (JSHSHIR) -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-center text-gray-700">
                                        {{ client.pinfl || '—' }}
                                    </td>
                                    <!-- Pasport -->
                                    <td class="border-r border-gray-200 px-3 py-1 text-center font-sans text-gray-700">
                                        {{ client.passport_number || client.passport_series || '—' }}
                                    </td>
                                    <!-- Qo'shilgan sana -->
                                    <td class="px-3 py-1 text-center text-gray-600 font-sans">
                                        {{ getUzFormattedDateFromString(client.created_at) }}
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

        <!-- 💰 Quick Payment Modal (Add Transaction) -->
        <div v-if="showQuickTransactionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm transition-all duration-300">
            <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 max-w-lg w-full overflow-hidden transform transition-all duration-300 scale-100 flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-orange-500 to-amber-500 text-white flex justify-between items-center shadow-md">
                    <h3 class="text-base font-bold uppercase tracking-wider flex items-center gap-2">
                        <span>💵</span> Yangi Mijoz To'lovi
                    </h3>
                    <button @click="showQuickTransactionModal = false" class="text-white/80 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Form -->
                <form @submit.prevent="submitQuickTransaction" class="p-6 space-y-4">
                    <!-- Client Selector (Only Counterparties with contracts) -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Mijoz (Kontragent)</label>
                        <select v-model="transactionForm.counterparty_id" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">-- Mijozni tanlang --</option>
                            <option v-for="cp in counterpartiesWithContracts" :key="cp.id" :value="cp.id">
                                {{ cp.name }} ({{ cp.phone || 'Telefon yo\'q' }})
                            </option>
                        </select>
                        <p v-if="counterpartiesWithContracts.length === 0" class="text-[10px] text-red-500 mt-1 font-semibold">Tizimda shartnomasi mavjud bo'lgan mijozlar topilmadi.</p>
                    </div>

                    <!-- Contract Selector (Select from Selected Client's Contracts) -->
                    <div v-if="transactionForm.counterparty_id">
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Shartnoma</label>
                        <select v-model="transactionForm.contract_id" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">-- Shartnomani tanlang --</option>
                            <option v-for="c in selectedCounterpartyContracts" :key="c.id" :value="c.id">
                                Shartnoma #{{ c.contract_number || c.id }} - {{ c.total_amount ? Number(c.total_amount).toLocaleString() : '0' }} so'm
                            </option>
                        </select>
                        <p v-if="selectedCounterpartyContracts.length === 0" class="text-[10px] text-red-500 mt-1 font-semibold">Ushbu mijozda shartnoma mavjud emas.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Amount -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">To'lov Summasi</label>
                            <div class="relative">
                                <input v-model="transactionForm.amount" type="number" required min="1" placeholder="Masalan: 5000000" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl pl-4 pr-12 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 font-bold">
                                <span class="absolute right-3.5 top-2.5 text-xs text-gray-400 font-bold">so'm</span>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">To'lov Usuli</label>
                            <select v-model="transactionForm.payment_method" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="cash">Naqd pul</option>
                                <option value="card">Plastik (Karta)</option>
                                <option value="bank">Bank o'tkazmasi</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Cash Register (Kassa) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5 font-sans">Kassa (Hisob)</label>
                            <select v-model="transactionForm.account_id" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">-- Kassani tanlang --</option>
                                <option v-for="cr in cashRegisters" :key="cr.id" :value="cr.id">
                                    {{ cr.name }} ({{ cr.currency }})
                                </option>
                            </select>
                        </div>

                        <!-- Transaction Date -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Sana</label>
                            <input v-model="transactionForm.transaction_date" type="date" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 font-mono">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Izoh (Eslatma)</label>
                        <textarea v-model="transactionForm.notes" rows="3" placeholder="To'lov bo'yicha qo'shimcha ma'lumotlar..." class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 leading-relaxed"></textarea>
                    </div>

                    <!-- Overpayment Account Selector (for Bank Overpayments) -->
                    <div v-if="transactionForm.type === 'income' && transactionForm.payment_method === 'bank' && overpaidAmount > 0" class="p-4 bg-orange-50 border border-orange-200 rounded-xl space-y-2">
                        <label class="block text-xs font-bold text-orange-800 uppercase tracking-wider">
                            ⚠️ Ortiqcha to'lov kassasi (Hisob)
                        </label>
                        <select v-model="transactionForm.overpayment_account_id" required class="w-full bg-white border border-orange-300 text-gray-900 rounded-xl px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 font-bold">
                            <option value="">-- Ortiqcha summa uchun hisob tanlang --</option>
                            <option v-for="cr in cashRegisters" :key="cr.id" :value="cr.id">
                                {{ cr.name }} ({{ cr.currency }})
                            </option>
                        </select>
                        <p class="text-[10px] text-orange-700 font-mono">
                            Mijoz shartnomaning qoldiq summasidan <strong class="text-orange-900 font-bold">{{ Number(overpaidAmount).toLocaleString() }} so'm</strong> ortiqcha to'lamoqda. Iltimos, ortiqcha summa o'tishi kerak bo'lgan bank/kassa hisobini tanlang.
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-4 flex justify-end gap-3 border-t border-gray-100">
                        <button type="button" @click="showQuickTransactionModal = false" class="px-4 py-2.5 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50 transition-all uppercase tracking-wider">
                            Bekor qilish
                        </button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2 uppercase tracking-wider disabled:opacity-50">
                            <span v-if="isProcessing" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ✏️ Edit Transaction Modal -->
        <div v-if="showEditTransactionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm transition-all duration-300">
            <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 max-w-md w-full overflow-hidden transform transition-all duration-300 scale-100 flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white flex justify-between items-center shadow-md">
                    <h3 class="text-base font-bold uppercase tracking-wider flex items-center gap-2">
                        <span>✏️</span> To'lovni Tahrirlash
                    </h3>
                    <button @click="showEditTransactionModal = false" class="text-white/80 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Form -->
                <form @submit.prevent="submitEditTransaction" class="p-6 space-y-4">
                    <!-- Amount -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">To'lov Summasi</label>
                        <div class="relative">
                            <input v-model="editTransactionForm.amount" type="number" required min="1" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl pl-4 pr-12 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-bold">
                            <span class="absolute right-3.5 top-2.5 text-xs text-gray-400 font-bold">so'm</span>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Izoh</label>
                        <textarea v-model="editTransactionForm.notes" rows="3" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 leading-relaxed"></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-4 flex justify-end gap-3 border-t border-gray-100">
                        <button type="button" @click="showEditTransactionModal = false" class="px-4 py-2.5 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50 transition-all uppercase tracking-wider">
                            Bekor qilish
                        </button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2 uppercase tracking-wider disabled:opacity-50">
                            <span v-if="isProcessing" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 📝 Printable Receipt Modal -->
        <div v-if="showReceiptModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm transition-all duration-300">
            <div class="bg-white rounded-2xl shadow-2xl border border-gray-150 max-w-md w-full overflow-hidden transform transition-all duration-300 scale-100 flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center print:hidden">
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">🖨️ To'lov kvitansiyasi</h3>
                    <button @click="showReceiptModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Printable Receipt area -->
                <div id="receipt-print-area" class="p-8 space-y-6 text-gray-800 font-sans print:p-0">
                    <!-- Logo / Brand -->
                    <div class="text-center space-y-1">
                        <h2 class="text-2xl font-black tracking-tight text-gray-900">SHERDOR 2.0</h2>
                        <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Turar-joy majmuasi</p>
                        <div class="h-0.5 w-16 bg-orange-500 mx-auto mt-2"></div>
                    </div>

                    <!-- Receipt Details Card -->
                    <div class="border-t border-b border-dashed border-gray-300 py-4 space-y-2.5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Hujjat raqami:</span>
                            <span class="font-bold text-gray-900">#{{ receiptData?.id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">To'lov sanasi:</span>
                            <span class="font-mono text-gray-900">{{ new Date(receiptData?.transaction_date || receiptData?.created_at).toLocaleString() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">To'lovchi (Mijoz):</span>
                            <span class="font-bold text-gray-900">{{ receiptData?.counterparty?.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Telefon:</span>
                            <span class="font-mono text-gray-900">{{ receiptData?.counterparty?.phone || '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">To'lov usuli:</span>
                            <span class="font-bold text-gray-900 uppercase">
                                {{ receiptData?.payment_method === 'cash' ? 'Naqd pul' : (receiptData?.payment_method === 'card' ? 'Plastik (Karta)' : 'Bank o\'tkazmasi') }}
                            </span>
                        </div>
                    </div>

                    <!-- Receipt Amount -->
                    <div class="bg-gray-50 rounded-xl p-4 text-center border border-gray-100">
                        <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Qabul qilingan summa</span>
                        <span class="text-2xl font-black text-emerald-600 font-mono">{{ Number(receiptData?.amount || 0).toLocaleString() }} so'm</span>
                    </div>

                    <!-- QR Code verification & Notes -->
                    <div class="flex items-center gap-6 bg-amber-50/40 p-4 rounded-xl border border-amber-100/50">
                        <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=https://sherdor.uz/receipt/${receiptData?.id}`" alt="QR Verification" class="w-20 h-20 bg-white p-1 rounded-lg shadow-sm border border-gray-200">
                        <div class="text-[10px] leading-relaxed text-amber-900">
                            <p class="font-bold uppercase tracking-wider text-amber-950 mb-1">To'lov Tasdiqlandi</p>
                            Ushbu kvitansiya to'lov amalga oshirilganligini tasdiqlovchi rasmiy hujjat hisoblanadi. To'lovni tekshirish uchun QR-kodni skanerlang.
                        </div>
                    </div>

                    <!-- Signature placeholders -->
                    <div class="grid grid-cols-2 gap-8 pt-4 text-xs">
                        <div class="text-center space-y-4">
                            <div class="border-b border-gray-300 h-6"></div>
                            <span class="text-gray-500">Qabul qildi (Kassir)</span>
                        </div>
                        <div class="text-center space-y-4">
                            <div class="border-b border-gray-300 h-6"></div>
                            <span class="text-gray-500">Mijoz</span>
                        </div>
                    </div>
                </div>

                <!-- Footer with Print Action -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-150 flex justify-end gap-3 print:hidden">
                    <button @click="showReceiptModal = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-100 transition-all uppercase tracking-wider">
                        Yopish
                    </button>
                    <button @click="window.print()" class="px-5 py-2 bg-[#f2a900] hover:bg-[#d99700] text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-1.5 uppercase tracking-wider">
                        🖨️ Chop etish
                    </button>
                </div>
            </div>
        </div>

        <!-- 👤 Edit Counterparty Modal -->
        <div v-if="showCounterpartyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm transition-all duration-300">
            <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 max-w-md w-full overflow-hidden transform transition-all duration-300 scale-100 flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white flex justify-between items-center shadow-md">
                    <h3 class="text-base font-bold uppercase tracking-wider flex items-center gap-2">
                        <span>👤</span> Mijoz Kontragent Ma'lumotlari
                    </h3>
                    <button @click="showCounterpartyModal = false" class="text-white/80 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Form -->
                <form @submit.prevent="submitCounterparty" class="p-6 space-y-4">
                    <!-- Name -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Mijoz (F.I.Sh)</label>
                        <input v-model="counterpartyForm.name" type="text" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-bold">
                    </div>

                    <!-- Phone -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Telefon raqami</label>
                        <input v-model="counterpartyForm.phone" type="text" placeholder="+998XXXXXXXXX" class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-4 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono">
                    </div>

                    <!-- Initial Balance -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Boshlang'ich qoldiq (Balans)</label>
                        <div class="relative">
                            <input v-model="counterpartyForm.balance" type="number" required class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl pl-4 pr-12 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-bold">
                            <span class="absolute right-3.5 top-2.5 text-xs text-gray-400 font-bold">so'm</span>
                        </div>
                        <span class="text-[10px] text-gray-400 mt-1 block">Manfiy qiymat qarzdorlikni, musbat qiymat esa haqdorlikni bildiradi.</span>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-4 flex justify-end gap-3 border-t border-gray-100">
                        <button type="button" @click="showCounterpartyModal = false" class="px-4 py-2.5 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50 transition-all uppercase tracking-wider">
                            Bekor qilish
                        </button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2 uppercase tracking-wider disabled:opacity-50">
                            <span v-if="isProcessing" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 💬 Client Comments Modal -->
        <div v-if="showCommentsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm transition-all duration-300" @click.self="showCommentsModal = false">
            <div class="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full max-h-[85vh] overflow-hidden flex flex-col transform transition-all duration-300 scale-100 border border-gray-150">
                <!-- Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-amber-500 to-orange-500 text-white flex justify-between items-center shrink-0 shadow-md">
                    <h3 class="text-sm font-bold uppercase tracking-wider flex items-center gap-2">
                        <span>💬</span> Mijoz Izohlari (Comments)
                    </h3>
                    <button @click="showCommentsModal = false" class="text-white/80 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Comments List -->
                <div class="flex-1 overflow-auto p-6 space-y-4 bg-slate-50">
                    <div v-if="detailedClientData?.comments && detailedClientData.comments.length > 0" class="space-y-3">
                        <div v-for="c in detailedClientData.comments" :key="c.id" class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm space-y-1.5 text-xs text-gray-700">
                            <div class="flex justify-between items-center text-[10px] text-gray-400 font-semibold uppercase">
                                <span class="text-amber-600">{{ c.user?.name || 'Xodim' }}</span>
                                <span>{{ new Date(c.created_at).toLocaleString() }}</span>
                            </div>
                            <p class="leading-relaxed font-medium text-gray-800">{{ c.comment }}</p>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-400 text-xs">
                        Hozircha izohlar mavjud emas. Yangi izoh qoldiring.
                    </div>
                </div>

                <!-- Add Comment Form -->
                <form @submit.prevent="submitClientComment" class="p-6 border-t border-gray-100 bg-white shrink-0 space-y-3">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">Yangi izoh qo'shish</label>
                    <textarea v-model="newCommentText" rows="3" required placeholder="Izoh matnini kiriting..." class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-4 py-3 text-xs shadow-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none"></textarea>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showCommentsModal = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50 transition-all uppercase tracking-wider">
                            Yopish
                        </button>
                        <button type="submit" :disabled="isSavingComment || !newCommentText.trim()" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2 uppercase tracking-wider disabled:opacity-50">
                            <span v-if="isSavingComment" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            Yuborish
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 📝 Payment Notes Modal -->
        <div v-if="showPaymentNotesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm transition-all duration-300" @click.self="showPaymentNotesModal = false">
            <div class="relative bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[85vh] overflow-hidden flex flex-col transform transition-all duration-300 scale-100 border border-gray-150">
                <!-- Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-amber-500 to-orange-500 text-white flex justify-between items-center shrink-0 shadow-md">
                    <h3 class="text-sm font-bold uppercase tracking-wider flex items-center gap-2">
                        <span>📝</span> To'lov Izohlari (Schedule Notes)
                    </h3>
                    <button @click="showPaymentNotesModal = false" class="text-white/80 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Notes List / Edit Form -->
                <div class="flex-1 overflow-auto p-6 bg-slate-50 space-y-6">
                    <!-- If editing a specific note -->
                    <div v-if="editingScheduleNote" class="bg-white p-6 rounded-2xl border border-amber-200 shadow-sm space-y-4">
                        <div class="flex justify-between items-center text-xs font-bold text-gray-700">
                            <span>Jadval Row: #{{ editingScheduleNote.id }} ({{ formatDateOnly(editingScheduleNote.due_date) }})</span>
                            <span class="text-amber-500">{{ Number(editingScheduleNote.expected_amount).toLocaleString() }} so'm</span>
                        </div>
                        <textarea v-model="newScheduleNoteText" rows="3" placeholder="Ushbu to'lov uchun izoh kiriting..." class="w-full bg-white border border-gray-300 text-gray-900 rounded-xl px-4 py-3 text-xs shadow-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:outline-none"></textarea>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="editingScheduleNote = null" class="px-4 py-2 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-150 transition-all uppercase tracking-wider">
                                Orqaga
                            </button>
                            <button type="button" @click="saveScheduleNote" :disabled="isSavingScheduleNote" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2 uppercase tracking-wider disabled:opacity-50">
                                <span v-if="isSavingScheduleNote" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                Izohni Saqlash
                            </button>
                        </div>
                    </div>

                    <!-- List of all schedules with notes -->
                    <div v-else class="space-y-3">
                        <div v-for="(s, idx) in detailedClientData?.contracts[0]?.payment_schedules" :key="s.id" class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex justify-between items-center gap-4 hover:border-gray-200 transition-colors">
                            <div class="flex-1 space-y-1">
                                <div class="flex items-center gap-2 text-[10px] text-gray-400 font-semibold uppercase">
                                    <span>{{ idx + 1 }}-To'lov</span>
                                    <span>•</span>
                                    <span>Sana: {{ formatDateOnly(s.due_date) }}</span>
                                    <span>•</span>
                                    <span :class="s.status === 'paid' ? 'text-emerald-600' : 'text-red-500'">{{ s.status.toUpperCase() }}</span>
                                </div>
                                <p class="text-xs text-gray-800 font-medium leading-relaxed">
                                    <span class="text-gray-400 font-normal">Izoh:</span> {{ s.notes || '—' }}
                                </p>
                            </div>
                            <button @click="editScheduleNote(s)" class="px-3 py-1.5 bg-amber-50 hover:bg-amber-500 text-amber-600 hover:text-white rounded-lg text-xs font-bold transition-all flex items-center gap-1">
                                ✏️
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end shrink-0">
                    <button @click="showPaymentNotesModal = false" class="px-5 py-2.5 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-100 transition-all uppercase tracking-wider">
                        Yopish
                    </button>
                </div>
            </div>
        </div>

        <!-- 📊 Payment History Modal -->
        <div v-if="showPaymentHistoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm transition-all duration-300" @click.self="showPaymentHistoryModal = false">
            <div class="relative bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[85vh] overflow-hidden flex flex-col transform transition-all duration-300 scale-100 border border-gray-150">
                <!-- Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-amber-500 to-orange-500 text-white flex justify-between items-center shrink-0 shadow-md">
                    <h3 class="text-sm font-bold uppercase tracking-wider flex items-center gap-2">
                        <span>📊</span> Mijoz To'lov Tarixi
                    </h3>
                    <button @click="showPaymentHistoryModal = false" class="text-white/80 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Table Content -->
                <div class="flex-1 overflow-auto p-6 bg-slate-50">
                    <div v-if="detailedClientData?.contracts[0]?.transactions && detailedClientData.contracts[0].transactions.length > 0" class="overflow-x-auto border border-gray-150 rounded-2xl bg-white shadow-sm">
                        <table class="w-full border-collapse text-left text-xs whitespace-nowrap">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 font-bold border-b border-gray-200">
                                    <th class="p-3 text-center w-12 font-mono text-[10px] uppercase">ID</th>
                                    <th class="p-3 text-center font-mono text-[10px] uppercase">Sana</th>
                                    <th class="p-3 text-right font-mono text-[10px] uppercase">Summa</th>
                                    <th class="p-3 text-center font-mono text-[10px] uppercase">To'lov usuli</th>
                                    <th class="p-3 font-mono text-[10px] uppercase">Izoh</th>
                                    <th class="p-3 font-mono text-[10px] uppercase">Kassir / Qabul qiluvchi</th>
                                    <th class="p-3 font-mono text-[10px] uppercase">Holat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-mono text-xs">
                                <tr v-for="t in detailedClientData.contracts[0].transactions" :key="t.id" class="hover:bg-slate-50 transition-colors">
                                    <td class="p-3 text-center text-gray-500">#{{ t.id }}</td>
                                    <td class="p-3 text-center text-gray-700">
                                        {{ t.transaction_date ? new Date(t.transaction_date).toLocaleDateString() : new Date(t.created_at).toLocaleDateString() }}
                                    </td>
                                    <td class="p-3 text-right font-bold text-emerald-600">{{ Number(t.amount || 0).toLocaleString() }} so'm</td>
                                    <td class="p-3 text-center">
                                        <span class="px-2 py-0.5 border border-gray-300 rounded text-[9px] uppercase font-bold text-gray-600 bg-gray-50">
                                            {{ t.payment_method === 'cash' ? 'Naqd' : (t.payment_method === 'card' ? 'Karta' : 'Bank') }}
                                        </span>
                                    </td>
                                    <td class="p-3 font-sans text-gray-800 leading-relaxed truncate max-w-xs" :title="t.notes">{{ t.notes || '—' }}</td>
                                    <td class="p-3 font-sans text-gray-700">{{ t.creator?.name || '—' }}</td>
                                    <td class="p-3 text-center">
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase" :class="t.status === 'approved' ? 'bg-emerald-50 text-emerald-600' : (t.status === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600')">
                                            {{ t.status === 'approved' ? 'Tasdiqlangan' : (t.status === 'pending' ? 'Kutilmoqda' : 'Rad etilgan') }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-12 text-gray-400 text-xs">
                        Amalga oshirilgan to'lov tranzaksiyalari topilmadi.
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end shrink-0">
                    <button @click="showPaymentHistoryModal = false" class="px-5 py-2.5 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-100 transition-all uppercase tracking-wider">
                        Yopish
                    </button>
                </div>
            </div>
        </div>

        <!-- 🖼️ Passport Lightbox Modal -->
        <div v-if="showPassportLightbox" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm transition-all duration-300" @click.self="showPassportLightbox = false">
            <div class="relative bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col transform transition-all duration-300 scale-100 border border-gray-150">
                <!-- Header -->
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center shrink-0">
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                        <span>🖼️</span> {{ lightboxTitle }}
                    </h3>
                    <button @click="showPassportLightbox = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-auto p-6 flex justify-center items-center bg-gray-100">
                    <template v-if="lightboxImageUrl.toLowerCase().endsWith('.pdf')">
                        <iframe :src="lightboxImageUrl" class="w-full h-[60vh] border-0 rounded-xl shadow-inner"></iframe>
                    </template>
                    <template v-else>
                        <img :src="lightboxImageUrl" class="max-w-full max-h-[70vh] object-contain rounded-xl shadow-lg border border-gray-200" alt="Passport Document" />
                    </template>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3 shrink-0">
                    <button @click="showPassportLightbox = false" class="px-5 py-2.5 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-100 transition-all uppercase tracking-wider">
                        Yopish
                    </button>
                    <a :href="lightboxImageUrl" download class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-1.5 uppercase tracking-wider">
                        📥 Yuklab olish
                    </a>
                </div>
            </div>
        </div>

        <!-- 📥 Export Format Selection Modal (Glassmorphic) -->
        <div v-if="showExportFormatModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm transition-all duration-300" @click.self="closeExportFormatModal">
            <div class="relative bg-white/90 backdrop-blur-md rounded-3xl shadow-2xl max-w-md w-full overflow-hidden flex flex-col transform transition-all duration-300 scale-100 border border-white/20 p-6 space-y-6 animate-fade-in-up">
                <!-- Header -->
                <div class="flex justify-between items-center">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider font-sans flex items-center gap-2">
                        <span>📥</span> To'lov grafigini yuklab olish
                    </h3>
                    <button @click="closeExportFormatModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Body / Question -->
                <div class="text-center space-y-2">
                    <p class="text-xs text-gray-600 font-medium leading-relaxed font-sans">
                        To'lov grafigini qaysi formatda yuklab olishni istaysiz?
                    </p>
                </div>

                <!-- Buttons Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <button @click="handleExport('xlsx')" class="p-4 bg-emerald-50 hover:bg-emerald-600 border border-emerald-300 hover:border-emerald-600 text-emerald-700 hover:text-white rounded-2xl transition-all shadow-sm flex flex-col items-center justify-center gap-2 group">
                        <span class="text-2xl group-hover:scale-110 transition-transform">📊</span>
                        <span class="text-xs font-bold uppercase tracking-wider font-sans">Excel (xlsx)</span>
                    </button>

                    <button @click="handleExport('pdf')" class="p-4 bg-red-50 hover:bg-red-600 border border-red-300 hover:border-red-600 text-red-700 hover:text-white rounded-2xl transition-all shadow-sm flex flex-col items-center justify-center gap-2 group">
                        <span class="text-2xl group-hover:scale-110 transition-transform">📕</span>
                        <span class="text-xs font-bold uppercase tracking-wider font-sans">PDF Format</span>
                    </button>
                </div>

                <!-- Cancel Button -->
                <button @click="closeExportFormatModal" class="w-full py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-all uppercase tracking-wider font-sans">
                    Bekor qilish
                </button>
            </div>
        </div>

    </AdminLayout>
</template>

