<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';

import AccordionItem from '@/Components/AccordionItem.vue';
import ToggleSwitch from '@/Components/ToggleSwitch.vue';
import ColorPickerInput from '@/Components/ColorPickerInput.vue';
import LogoUploader from '@/Components/LogoUploader.vue';

const props = defineProps({
    settings: Object,
    leadSources: Array,
    roles: Array,
    permissions: Array,
    users: Array,
    buildings: Array,
    warehouses: Array,
    clients: Array,
    smsTemplates: Array,
});

const page = usePage();
const user = page.props.auth.user;

const activeTab = ref('branding');
const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref('success');

// Toast logic
const triggerToast = (message, type = 'success') => {
    toastMessage.value = message;
    toastType.value = type;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 3000);
};

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
                activeTab.value = 'branding';
            }
        } catch (e) {
            console.error('URL parse error', e);
        }
    },
    { immediate: true }
);

watch(activeTab, (newTab) => {
    try {
        const url = new URL(window.location.href);
        const currentTab = url.searchParams.get('tab');
        if (currentTab !== newTab) {
            router.get(route('admin.settings.index', { tab: newTab }), {}, {
                replace: true,
                preserveState: true,
                preserveScroll: true
            });
        }
    } catch (e) {}
});

// Accordion States for Branding
const openAccordions = ref({
    branding_company: true,
    branding_colors: false,
    branding_fonts: false,
    system_locale: true,
    company_details: true,
    security_auth: true,
    security_sessions: false,
    notif_channels: true,
    roles: true,
    users: true,
    sources: true,
    sms: true,
});

const toggleAccordion = (key) => {
    openAccordions.value[key] = !openAccordions.value[key];
};

// FORM: System & Branding
const form = useForm({
    company_name: props.settings?.company_name || 'ITCloud Sher-Dor ERP',
    app_name: props.settings?.app_name || 'Sher-Dor ERP',
    company_address: props.settings?.company_address || '',
    company_bank_account: props.settings?.company_bank_account || '',
    company_bank_name: props.settings?.company_bank_name || '',
    company_mfo: props.settings?.company_mfo || '',
    company_inn: props.settings?.company_inn || '',
    company_director: props.settings?.company_director || '',
    company_phone: props.settings?.company_phone || '',
    company_email: '', // Add if needed
    primary_theme_color: props.settings?.primary_theme_color || '#2563EB',
    secondary_theme_color: '#7C3AED',
    bg_theme_color: '#F8FAFC',
    neon_glow_intensity: props.settings?.neon_glow_intensity || '0 0 15px',
    default_currency: props.settings?.default_currency || 'UZS',
    tax_rate: props.settings?.tax_rate || 12.00,
    default_penalty_rate: props.settings?.default_penalty_rate || 0.10,
    logo: null,
    background_image: null,
    system_language: 'uz',
    timezone: 'Asia/Tashkent',
    date_format: 'DD.MM.YYYY',
    decimal_places: '2',
});

function submitSystemSettings() {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => triggerToast('Saqlandi ✓')
    });
}

// Security Form
const securityForm = useForm({
    password_policy: true,
    two_factor: false
});
const submitSecuritySettings = () => {
    // Dummy submit
    triggerToast('Saqlandi ✓');
    securityForm.isDirty = false;
};

// Notifications Form
const notifForm = useForm({
    email_notif: true,
    sms_notif: true,
    sys_notif: true
});
const submitNotifSettings = () => {
    // Dummy submit
    triggerToast('Saqlandi ✓');
    notifForm.isDirty = false;
};

// Other existing logics
const sourceForm = useForm({ id: null, name: '' });
const isEditingSource = ref(false);
const saveSource = () => {
    if (isEditingSource.value) {
        sourceForm.put(route('admin.settings.lead_sources.update', sourceForm.id), {
            onSuccess: () => { sourceForm.reset(); isEditingSource.value = false; triggerToast('Saqlandi ✓'); }
        });
    } else {
        sourceForm.post(route('admin.settings.lead_sources.store'), {
            onSuccess: () => { sourceForm.reset(); triggerToast('Qo\'shildi ✓'); }
        });
    }
};
const editSource = (source) => { sourceForm.id = source.id; sourceForm.name = source.name; isEditingSource.value = true; };
const deleteSource = (id) => {
    if (confirm('O\'chirishni tasdiqlaysizmi?')) {
        sourceForm.delete(route('admin.settings.lead_sources.destroy', id), { onSuccess: () => triggerToast('O\'chirildi ✓') });
    }
};

const selectedClients = ref([]);
const smsForm = useForm({ client_ids: [], message: '', send_type: 'immediate', scheduled_at: '' });
const activeTemplate = ref('');
const selectAllClients = (e) => { selectedClients.value = e.target.checked ? props.clients.map(c => c.id) : []; };
const handleTemplateChange = () => {
    const template = props.smsTemplates.find(t => t.id === activeTemplate.value);
    if (template) smsForm.message = template.content;
};
const sendBroadcastSms = () => {
    if (selectedClients.value.length === 0) { alert('Mijozni tanlang.'); return; }
    smsForm.client_ids = selectedClients.value;
    smsForm.post(route('admin.settings.sms.send'), {
        onSuccess: () => { selectedClients.value = []; smsForm.reset(); activeTemplate.value = ''; triggerToast('Yuborildi ✓'); }
    });
};

const isEditingUser = ref(false);
const editingUserId = ref(null);
const userForm = useForm({ name: '', last_name: '', middle_name: '', username: '', phone: '', password: '', role: 'Worker', building_id: '', warehouse_id: '', all_objects: false });
const submitUserForm = () => {
    if (isEditingUser.value) {
        userForm.put(route('admin.settings.users.update', editingUserId.value), { onSuccess: () => { resetUserForm(); triggerToast('Yangilandi ✓'); } });
    } else {
        userForm.post(route('admin.settings.users.store'), { onSuccess: () => { resetUserForm(); triggerToast('Qo\'shildi ✓'); } });
    }
};
const editUser = (usr) => {
    isEditingUser.value = true; editingUserId.value = usr.id; userForm.name = usr.name; userForm.last_name = usr.last_name || '';
    userForm.middle_name = usr.middle_name || ''; userForm.username = usr.username || ''; userForm.phone = usr.phone || '';
    userForm.password = ''; userForm.role = usr.roles?.length ? usr.roles[0].name : 'Worker';
    userForm.building_id = usr.building_id || ''; userForm.warehouse_id = usr.warehouse_id || ''; userForm.all_objects = !usr.building_id && !usr.warehouse_id;
};
const deleteUser = (id) => {
    if (confirm('O\'chirishni tasdiqlaysizmi?')) { userForm.delete(route('admin.settings.users.destroy', id), { onSuccess: () => triggerToast('O\'chirildi ✓') }); }
};
const resetUserForm = () => { userForm.reset(); isEditingUser.value = false; editingUserId.value = null; };

const selectedRoleIdForPermissions = ref('');
const rolePermissionsForm = useForm({ role_id: '', permissions: [] });
const permissionLabel = (perm) => {
    const labels = {
        'access dashboard': 'Boshqaruv paneli', 'access reports': 'Hisobotlar', 'access debtors': 'Qarzdorlar',
        'access transactions': 'Kirim-chiqim', 'access objects': 'Obyektlar', 'access clients': 'Mijozlar',
        'access finance': 'Moliya', 'access hr': 'HRM & Ish haqi', 'access settings': 'Sozlamalar'
    };
    return labels[perm] || perm;
};
const loadRolePermissions = () => {
    const role = props.roles.find(r => r.id === selectedRoleIdForPermissions.value);
    if (role) { rolePermissionsForm.role_id = role.id; rolePermissionsForm.permissions = role.permissions.map(p => p.name); } 
    else { rolePermissionsForm.reset(); }
};
const saveRolePermissions = () => {
    if (!rolePermissionsForm.role_id) { alert('Rolni tanlang.'); return; }
    rolePermissionsForm.post(route('admin.settings.roles_permissions.update'), { onSuccess: () => triggerToast('Saqlandi ✓') });
};

// Global dirty state check
const hasUnsavedChanges = computed(() => {
    return form.isDirty || securityForm.isDirty || notifForm.isDirty || sourceForm.isDirty || smsForm.isDirty || userForm.isDirty || rolePermissionsForm.isDirty;
});
</script>

<template>
    <Head title="Sozlamalar" />

    <AdminLayout>
        <!-- TOAST NOTIFICATION -->
        <div 
            class="fixed top-[24px] right-[24px] z-50 flex items-center px-[20px] py-[16px] rounded-[8px] shadow-lg transition-all duration-300 transform"
            :class="[
                showToast ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0',
                toastType === 'success' ? 'bg-[#10B981] text-white' : 'bg-[#EF4444] text-white'
            ]"
        >
            <span class="mr-[12px] text-[14px] font-semibold">{{ toastMessage }}</span>
            <button @click="showToast = false" class="text-white/80 hover:text-white">✕</button>
        </div>

        <!-- UNSAVED CHANGES WARNING -->
        <div 
            v-if="hasUnsavedChanges"
            class="fixed bottom-[24px] right-[24px] z-40 bg-[#F59E0B] text-white px-6 py-4 rounded-xl shadow-xl flex items-center gap-4 animate-[slideUp_0.3s_ease-out]"
        >
            <span class="text-[20px]">⚠️</span>
            <div>
                <p class="text-[14px] font-bold">Saqlanmagan o'zgarishlar bor</p>
                <p class="text-[12px] opacity-90">Iltimos, o'zgarishlarni saqlang</p>
            </div>
            <button @click="submitSystemSettings" class="ml-4 px-4 py-2 bg-white text-[#F59E0B] rounded-lg text-sm font-bold shadow-sm hover:bg-orange-50 transition-colors">
                Saqlash
            </button>
        </div>

        <!-- MAIN CONTAINER -->
        <div class="max-w-[1200px] mx-auto p-[16px] lg:px-[32px] lg:py-[24px] bg-[#F8FAFC] min-h-screen">
            
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-[28px] font-semibold text-[#1E293B] mb-2">Sozlamalar</h1>
                    <p class="text-[15px] text-[#64748B]">Tizimni o'zingizga moslashtiring</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-[#E2E8F0] overflow-hidden">
                
                <!-- TOP NAVIGATION TABS -->
                <div class="flex overflow-x-auto border-b-2 border-[#E2E8F0] px-4 md:px-8 pt-4 gap-8 scrollbar-hide bg-white sticky top-0 z-10">
                    <button 
                        @click="activeTab = 'branding'"
                        class="pb-3 text-[15px] font-semibold border-b-[3px] transition-colors whitespace-nowrap flex items-center gap-2"
                        :class="activeTab === 'branding' ? 'border-[#2563EB] text-[#1E293B]' : 'border-transparent text-[#64748B] hover:text-[#2563EB]'"
                    >
                        <span>🎨</span> Brending va Tashqi Ko'rinish
                    </button>
                    <button 
                        @click="activeTab = 'system'"
                        class="pb-3 text-[15px] font-semibold border-b-[3px] transition-colors whitespace-nowrap flex items-center gap-2"
                        :class="activeTab === 'system' ? 'border-[#2563EB] text-[#1E293B]' : 'border-transparent text-[#64748B] hover:text-[#2563EB]'"
                    >
                        <span>⚙️</span> Tizim
                    </button>
                    <button 
                        @click="activeTab = 'company'"
                        class="pb-3 text-[15px] font-semibold border-b-[3px] transition-colors whitespace-nowrap flex items-center gap-2"
                        :class="activeTab === 'company' ? 'border-[#2563EB] text-[#1E293B]' : 'border-transparent text-[#64748B] hover:text-[#2563EB]'"
                    >
                        <span>🏢</span> Kompaniya
                    </button>
                    <button 
                        @click="activeTab = 'security'"
                        class="pb-3 text-[15px] font-semibold border-b-[3px] transition-colors whitespace-nowrap flex items-center gap-2"
                        :class="activeTab === 'security' ? 'border-[#2563EB] text-[#1E293B]' : 'border-transparent text-[#64748B] hover:text-[#2563EB]'"
                    >
                        <span>🛡️</span> Xavfsizlik
                    </button>
                    <button 
                        @click="activeTab = 'notifications'"
                        class="pb-3 text-[15px] font-semibold border-b-[3px] transition-colors whitespace-nowrap flex items-center gap-2"
                        :class="activeTab === 'notifications' ? 'border-[#2563EB] text-[#1E293B]' : 'border-transparent text-[#64748B] hover:text-[#2563EB]'"
                    >
                        <span>📢</span> Bildirishnomalar
                    </button>
                    <button 
                        @click="activeTab = 'roles'"
                        class="pb-3 text-[15px] font-semibold border-b-[3px] transition-colors whitespace-nowrap flex items-center gap-2"
                        :class="activeTab === 'roles' ? 'border-[#2563EB] text-[#1E293B]' : 'border-transparent text-[#64748B] hover:text-[#2563EB]'"
                    >
                        <span>👥</span> Rollar va Xodimlar
                    </button>
                    <button 
                        @click="activeTab = 'marketing'"
                        class="pb-3 text-[15px] font-semibold border-b-[3px] transition-colors whitespace-nowrap flex items-center gap-2"
                        :class="activeTab === 'marketing' ? 'border-[#2563EB] text-[#1E293B]' : 'border-transparent text-[#64748B] hover:text-[#2563EB]'"
                    >
                        <span>🎯</span> Marketing (SMS, Lid)
                    </button>
                </div>

                <!-- MAIN CONTENT AREA -->
                <div class="p-4 md:p-8 bg-[#F8FAFC]">

                    <!-- ===================================== -->
                    <!-- 1. BRANDING & APPEARANCE -->
                    <!-- ===================================== -->
                    <div v-show="activeTab === 'branding'" class="animate-[fadeIn_0.3s_ease-out]">
                        <form @submit.prevent="submitSystemSettings">
                            
                            <AccordionItem title="🏢 KOMPANIYA MA'LUMOTLARI" :isOpen="openAccordions.branding_company" @toggle="toggleAccordion('branding_company')">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">Kompaniya Nomi</label>
                                        <input v-model="form.company_name" type="text" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">Kompaniya Tizim Nomi</label>
                                        <input v-model="form.app_name" type="text" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                    </div>
                                    <div class="col-span-full">
                                        <LogoUploader v-model="form.logo" label="Logotipni yuklang" />
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-end gap-3 pt-5 border-t border-[#E2E8F0]">
                                    <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-[#10B981] text-white rounded-lg text-[14px] font-semibold hover:bg-[#059669] shadow-sm transition-all">Saqlash</button>
                                </div>
                            </AccordionItem>

                            <AccordionItem title="🎨 RANGLAR" :isOpen="openAccordions.branding_colors" @toggle="toggleAccordion('branding_colors')">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <ColorPickerInput v-model="form.primary_theme_color" label="Asosiy Rang (Primary)" />
                                    <ColorPickerInput v-model="form.secondary_theme_color" label="Ikkinchi Rang (Secondary)" />
                                    <ColorPickerInput v-model="form.bg_theme_color" label="Fon Rangi (Background)" />
                                </div>
                                <div class="mt-6 flex justify-end gap-3 pt-5 border-t border-[#E2E8F0]">
                                    <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-[#10B981] text-white rounded-lg text-[14px] font-semibold hover:bg-[#059669] shadow-sm transition-all">Saqlash</button>
                                </div>
                            </AccordionItem>

                        </form>
                    </div>

                    <!-- ===================================== -->
                    <!-- 2. SYSTEM SETTINGS -->
                    <!-- ===================================== -->
                    <div v-show="activeTab === 'system'" class="animate-[fadeIn_0.3s_ease-out]">
                        <form @submit.prevent="submitSystemSettings">
                            <AccordionItem title="⚙️ ASOSIY TIZIM SOZLAMALARI" :isOpen="openAccordions.system_locale" @toggle="toggleAccordion('system_locale')">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">Tizim Tili</label>
                                        <select v-model="form.system_language" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white">
                                            <option value="uz">O'zbekcha</option>
                                            <option value="ru">Русский</option>
                                            <option value="en">English</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">Vaqt Mintaqasi (Timezone)</label>
                                        <select v-model="form.timezone" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white">
                                            <option value="Asia/Tashkent">Asia/Tashkent (+05:00)</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">Sanalar Formati</label>
                                        <select v-model="form.date_format" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white">
                                            <option value="DD.MM.YYYY">DD.MM.YYYY (Masalan: 31.12.2023)</option>
                                            <option value="YYYY-MM-DD">YYYY-MM-DD (Masalan: 2023-12-31)</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">Pul Birligi</label>
                                        <select v-model="form.default_currency" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white">
                                            <option value="UZS">UZS (So'm)</option>
                                            <option value="USD">USD (Dollar)</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">Aniqlik Darajasi (Decimal Places)</label>
                                        <select v-model="form.decimal_places" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white">
                                            <option value="0">0 (1 000)</option>
                                            <option value="2">2 (1 000.00)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-end gap-3 pt-5 border-t border-[#E2E8F0]">
                                    <button type="button" @click="form.reset()" class="px-6 py-2.5 bg-white text-[#64748B] border border-[#E2E8F0] rounded-lg text-[14px] font-semibold hover:bg-[#F1F5F9] transition-colors">Tiklash</button>
                                    <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-[#10B981] text-white rounded-lg text-[14px] font-semibold hover:bg-[#059669] shadow-sm transition-all">Saqlash</button>
                                </div>
                            </AccordionItem>
                        </form>
                    </div>

                    <!-- ===================================== -->
                    <!-- 3. COMPANY DETAILS -->
                    <!-- ===================================== -->
                    <div v-show="activeTab === 'company'" class="animate-[fadeIn_0.3s_ease-out]">
                        <form @submit.prevent="submitSystemSettings">
                            <AccordionItem title="🏢 REKVIZITLAR VA ALOQA" :isOpen="openAccordions.company_details" @toggle="toggleAccordion('company_details')">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="flex flex-col md:col-span-2">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">Kompaniya Nomini (Yuridik)</label>
                                        <input v-model="form.company_name" type="text" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">Telefon Raqami</label>
                                        <input v-model="form.company_phone" type="text" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">Elektron Pochta</label>
                                        <input v-model="form.company_email" type="email" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                    </div>
                                    <div class="flex flex-col md:col-span-2">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">Manzili</label>
                                        <input v-model="form.company_address" type="text" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">Bank Nomi</label>
                                        <input v-model="form.company_bank_name" type="text" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">Bank Hisob Raqami</label>
                                        <input v-model="form.company_bank_account" type="text" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">INN</label>
                                        <input v-model="form.company_inn" type="text" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                    </div>
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2">MFO</label>
                                        <input v-model="form.company_mfo" type="text" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-end gap-3 pt-5 border-t border-[#E2E8F0]">
                                    <button type="button" @click="form.reset()" class="px-6 py-2.5 bg-white text-[#64748B] border border-[#E2E8F0] rounded-lg text-[14px] font-semibold hover:bg-[#F1F5F9] transition-colors">Tiklash</button>
                                    <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-[#10B981] text-white rounded-lg text-[14px] font-semibold hover:bg-[#059669] shadow-sm transition-all">Saqlash</button>
                                </div>
                            </AccordionItem>
                        </form>
                    </div>

                    <!-- ===================================== -->
                    <!-- 4. SECURITY -->
                    <!-- ===================================== -->
                    <div v-show="activeTab === 'security'" class="animate-[fadeIn_0.3s_ease-out]">
                        <form @submit.prevent="submitSecuritySettings">
                            <AccordionItem title="🔐 AUTENTIFIKATSIYA SOZLAMALARI" :isOpen="openAccordions.security_auth" @toggle="toggleAccordion('security_auth')">
                                <div class="space-y-6">
                                    <div class="flex items-center justify-between p-4 border border-[#E2E8F0] rounded-lg bg-[#F8FAFC]">
                                        <div>
                                            <h4 class="text-[15px] font-semibold text-[#1E293B] mb-1">Qat'iy Parol Siyosati</h4>
                                            <p class="text-[13px] text-[#64748B]">Parol kamida 8 ta belgi, raqam va maxsus belgilardan iborat bo'lishi kerak.</p>
                                        </div>
                                        <ToggleSwitch v-model="securityForm.password_policy" />
                                    </div>
                                    <div class="flex items-center justify-between p-4 border border-[#E2E8F0] rounded-lg bg-[#F8FAFC]">
                                        <div>
                                            <h4 class="text-[15px] font-semibold text-[#1E293B] mb-1">2-Faktorli Autentifikatsiya (2FA)</h4>
                                            <p class="text-[13px] text-[#64748B]">Tizimga kirishda SMS yoki Authenticator orqali tasdiqlashni yoqish.</p>
                                        </div>
                                        <ToggleSwitch v-model="securityForm.two_factor" />
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-end gap-3 pt-5 border-t border-[#E2E8F0]">
                                    <button type="submit" class="px-6 py-2.5 bg-[#10B981] text-white rounded-lg text-[14px] font-semibold hover:bg-[#059669] shadow-sm transition-all">Saqlash</button>
                                </div>
                            </AccordionItem>

                            <AccordionItem title="💻 FAOL SESSIYALAR" :isOpen="openAccordions.security_sessions" @toggle="toggleAccordion('security_sessions')">
                                <div class="border border-[#E2E8F0] rounded-lg overflow-hidden">
                                    <div class="p-4 flex items-center justify-between hover:bg-slate-50 transition-colors">
                                        <div class="flex items-center gap-4">
                                            <span class="text-[28px]">💻</span>
                                            <div>
                                                <p class="text-[15px] font-semibold text-[#1E293B]">Windows - Chrome</p>
                                                <p class="text-[13px] text-[#64748B]">Toshkent, O'zbekiston • Hozir faol</p>
                                            </div>
                                        </div>
                                        <button type="button" class="px-4 py-2 text-[13px] font-semibold text-[#DC2626] bg-[#FEE2E2] hover:bg-[#FECACA] border border-[#FECACA] rounded-lg transition-colors">Boshqa sessiyalarni yopish</button>
                                    </div>
                                </div>
                            </AccordionItem>
                        </form>
                    </div>

                    <!-- ===================================== -->
                    <!-- 5. NOTIFICATIONS -->
                    <!-- ===================================== -->
                    <div v-show="activeTab === 'notifications'" class="animate-[fadeIn_0.3s_ease-out]">
                        <form @submit.prevent="submitNotifSettings">
                            <AccordionItem title="📢 XABARNOMALAR KANALLARI" :isOpen="openAccordions.notif_channels" @toggle="toggleAccordion('notif_channels')">
                                <div class="space-y-6">
                                    <div class="flex items-center justify-between p-4 border border-[#E2E8F0] rounded-lg bg-[#F8FAFC]">
                                        <div>
                                            <h4 class="text-[15px] font-semibold text-[#1E293B] mb-1">Tizim Ichki Bildirishnomalari</h4>
                                            <p class="text-[13px] text-[#64748B]">Muhim voqealar va yangiliklar haqida tizim ichida xabar berish.</p>
                                        </div>
                                        <ToggleSwitch v-model="notifForm.sys_notif" />
                                    </div>
                                    <div class="flex items-center justify-between p-4 border border-[#E2E8F0] rounded-lg bg-[#F8FAFC]">
                                        <div>
                                            <h4 class="text-[15px] font-semibold text-[#1E293B] mb-1">Email Bildirishnomalar</h4>
                                            <p class="text-[13px] text-[#64748B]">Hisobotlar va tizim xatoliklarini elektron pochtaga yuborish.</p>
                                        </div>
                                        <ToggleSwitch v-model="notifForm.email_notif" />
                                    </div>
                                    <div class="flex items-center justify-between p-4 border border-[#E2E8F0] rounded-lg bg-[#F8FAFC]">
                                        <div>
                                            <h4 class="text-[15px] font-semibold text-[#1E293B] mb-1">SMS Bildirishnomalar</h4>
                                            <p class="text-[13px] text-[#64748B]">Muhim tranzaksiyalar va to'lovlar haqida SMS xabar berish.</p>
                                        </div>
                                        <ToggleSwitch v-model="notifForm.sms_notif" />
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-end gap-3 pt-5 border-t border-[#E2E8F0]">
                                    <button type="submit" class="px-6 py-2.5 bg-[#10B981] text-white rounded-lg text-[14px] font-semibold hover:bg-[#059669] shadow-sm transition-all">Saqlash</button>
                                </div>
                            </AccordionItem>
                        </form>
                    </div>

                    <!-- ===================================== -->
                    <!-- 6. ROLES & USERS -->
                    <!-- ===================================== -->
                    <div v-show="activeTab === 'roles'" class="animate-[fadeIn_0.3s_ease-out]">
                        
                        <AccordionItem title="👥 XODIMLAR BOSHQARUVI" :isOpen="openAccordions.users" @toggle="toggleAccordion('users')">
                            <div class="mb-4 flex justify-between items-end">
                                <p class="text-[13px] text-[#64748B]">Foydalanuvchilarni boshqarish va ularni rollarga biriktirish.</p>
                                <button class="px-5 py-2.5 bg-[#2563EB] text-white rounded-lg text-[14px] font-semibold hover:bg-blue-700 transition-colors">+ Xodim Qo'shish</button>
                            </div>
                            <div class="border border-[#E2E8F0] rounded-lg overflow-hidden bg-white">
                                <table class="w-full text-left border-collapse">
                                    <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                                        <tr>
                                            <th class="p-4 text-[13px] font-medium text-[#64748B]">Ism</th>
                                            <th class="p-4 text-[13px] font-medium text-[#64748B]">Login</th>
                                            <th class="p-4 text-[13px] font-medium text-[#64748B]">Rol</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="usr in users" :key="usr.id" class="border-b border-[#E2E8F0] hover:bg-[#F8FAFC]">
                                            <td class="p-4 text-[14px] font-semibold text-[#1E293B]">{{ usr.name }}</td>
                                            <td class="p-4 text-[14px] text-[#64748B]">{{ usr.username }}</td>
                                            <td class="p-4 text-[14px] text-[#64748B]">
                                                <span class="px-2 py-1 bg-slate-100 rounded text-xs font-medium">{{ usr.roles?.length ? usr.roles[0].name : '-' }}</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </AccordionItem>

                        <AccordionItem title="🔐 ROLLAR VA HUQUQLAR" :isOpen="openAccordions.roles" @toggle="toggleAccordion('roles')">
                            <p class="text-[13px] text-[#64748B] mb-6">Xodimlar roliga qarab tizimdagi modullarni ko'rish ruxsatnomalarini sozlang.</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <label class="text-[13px] font-medium text-[#64748B] mb-2 block">Rolni Tanlang</label>
                                    <select v-model="selectedRoleIdForPermissions" @change="loadRolePermissions" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] text-[#1E293B] focus:border-[#2563EB] bg-white outline-none">
                                        <option value="">-- Rol tanlang --</option>
                                        <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                                    </select>
                                </div>
                                <div v-if="selectedRoleIdForPermissions" class="bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg p-5">
                                    <h4 class="text-[15px] font-bold text-[#1E293B] mb-4">Ruxsatnomalar:</h4>
                                    <form @submit.prevent="saveRolePermissions">
                                        <div class="space-y-3 mb-6 max-h-[300px] overflow-y-auto pr-2 custom-scrollbar">
                                            <label v-for="perm in permissions" :key="perm.id" class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-white transition-colors border border-transparent hover:border-slate-200">
                                                <input type="checkbox" :value="perm.name" v-model="rolePermissionsForm.permissions" class="w-5 h-5 text-[#2563EB] rounded border-[#E2E8F0] focus:ring-[#2563EB]">
                                                <span class="text-[14px] text-[#1E293B] font-medium">{{ permissionLabel(perm.name) }}</span>
                                            </label>
                                        </div>
                                        <div class="border-t border-[#E2E8F0] pt-4">
                                            <button type="submit" :disabled="rolePermissionsForm.processing" class="w-full px-6 py-2.5 bg-[#10B981] text-white rounded-lg text-[14px] font-semibold hover:bg-[#059669] shadow-sm transition-all">
                                                {{ rolePermissionsForm.processing ? 'Saqlanmoqda...' : 'Saqlash' }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </AccordionItem>
                    </div>

                    <!-- ===================================== -->
                    <!-- 7. MARKETING (SMS, LEAD SOURCES) -->
                    <!-- ===================================== -->
                    <div v-show="activeTab === 'marketing'" class="animate-[fadeIn_0.3s_ease-out]">
                        
                        <AccordionItem title="🎯 LID MANBALARI" :isOpen="openAccordions.sources" @toggle="toggleAccordion('sources')">
                            <p class="text-[13px] text-[#64748B] mb-6">Mijozlarning qayerdan kelganligini qayd etish manzillari (Masalan: Instagram, Telegram).</p>
                            
                            <form @submit.prevent="saveSource" class="flex flex-wrap gap-4 mb-8 items-end">
                                <div class="flex-1 min-w-[280px]">
                                    <label class="text-[13px] font-medium text-[#64748B] mb-2 block">Manba Nomi</label>
                                    <input v-model="sourceForm.name" type="text" placeholder="Masalan: Instagram Reklama" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] outline-none focus:border-[#2563EB]">
                                </div>
                                <button type="submit" :disabled="sourceForm.processing" class="px-6 py-2.5 bg-[#2563EB] text-white rounded-lg text-[14px] font-semibold hover:bg-blue-700 h-[40px]">
                                    {{ isEditingSource ? 'Yangilash' : 'Qo\'shish' }}
                                </button>
                                <button v-if="isEditingSource" type="button" @click="sourceForm.reset(); isEditingSource = false" class="px-6 py-2.5 bg-white text-[#64748B] border border-[#E2E8F0] rounded-lg text-[14px] font-semibold hover:bg-[#F1F5F9] h-[40px]">Bekor qilish</button>
                            </form>
                            
                            <div class="border border-[#E2E8F0] rounded-lg overflow-hidden bg-white">
                                <table class="w-full text-left">
                                    <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                                        <tr>
                                            <th class="p-4 text-[13px] font-medium text-[#64748B]">#</th>
                                            <th class="p-4 text-[13px] font-medium text-[#64748B]">Manba Nomi</th>
                                            <th class="p-4 text-[13px] font-medium text-[#64748B] text-right">Amallar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(src, idx) in leadSources" :key="src.id" class="border-b border-[#E2E8F0] hover:bg-[#F8FAFC]">
                                            <td class="p-4 text-[14px] text-[#64748B]">{{ idx + 1 }}</td>
                                            <td class="p-4 text-[14px] font-semibold text-[#1E293B]">{{ src.name }}</td>
                                            <td class="p-4 text-right flex justify-end gap-2">
                                                <button @click="editSource(src)" class="px-3 py-1.5 border border-[#E2E8F0] rounded-md text-[13px] font-semibold text-[#1E293B] hover:bg-[#F1F5F9]">Tahrirlash</button>
                                                <button @click="deleteSource(src.id)" class="px-3 py-1.5 bg-[#FEE2E2] border border-[#FECACA] text-[#DC2626] rounded-md text-[13px] font-semibold hover:bg-[#FECACA]">O'chirish</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </AccordionItem>

                        <AccordionItem title="💬 SMS XABARNOMALAR" :isOpen="openAccordions.sms" @toggle="toggleAccordion('sms')">
                            <p class="text-[13px] text-[#64748B] mb-6">Mijozlarga ommaviy SMS xabarnomalar va e'lonlar yuborish.</p>
                            
                            <form @submit.prevent="sendBroadcastSms" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="flex flex-col">
                                        <label class="text-[13px] font-medium text-[#64748B] mb-2 block">Shablon Tanlash</label>
                                        <select v-model="activeTemplate" @change="handleTemplateChange" class="w-full px-3 py-2 min-h-[40px] border border-[#E2E8F0] rounded-lg text-[14px] outline-none bg-white">
                                            <option value="">Shablon tanlang...</option>
                                            <option v-for="t in smsTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[13px] font-medium text-[#64748B] mb-2 block">Xabar Matni</label>
                                    <textarea v-model="smsForm.message" required rows="4" class="w-full p-3 border border-[#E2E8F0] rounded-lg text-[14px] outline-none focus:border-[#2563EB]"></textarea>
                                </div>
                                <div class="flex justify-end pt-5 border-t border-[#E2E8F0]">
                                    <button type="submit" :disabled="smsForm.processing" class="px-6 py-2.5 bg-[#10B981] text-white rounded-lg text-[14px] font-semibold hover:bg-[#059669] shadow-sm transition-all">
                                        {{ smsForm.processing ? 'Yuborilmoqda...' : 'Yuborish' }}
                                    </button>
                                </div>
                            </form>
                        </AccordionItem>

                    </div>

                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style>
@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
/* Hide scrollbar for tabs */
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #F1F5F9;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 4px;
}
</style>
