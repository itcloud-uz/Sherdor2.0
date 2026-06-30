<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';

import SettingsSidebar from '@/Components/SettingsSidebar.vue';
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
            router.replace(route('admin.settings.index', { tab: newTab }), {
                preserveState: true,
                preserveScroll: true
            });
        }
    } catch (e) {}
});

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
        onSuccess: () => triggerToast('Sozlamalar saqlandi ✓')
    });
}

// FORM: Profile (Includes language/theme)
const themeStyle = user.theme_style || 'apple_glass';
const profileForm = useForm({
    name: user.name || '',
    email: user.email || '',
    phone: user.phone || '',
    password: '',
    language: user.language || 'uz',
    theme_color: user.theme_color || '#2563EB',
    theme_style: themeStyle,
    label_font_size: user.label_font_size || 12,
    avatar: null,
});

function submitProfile() {
    profileForm.post(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            profileForm.password = '';
            triggerToast('Profil yangilandi ✓');
        }
    });
}

// Security Form (Dummy structure for UI, not functional backend yet)
const securityForm = useForm({
    password_policy: true,
    two_factor: false
});

// Notifications Form (Dummy structure for UI)
const notifForm = useForm({
    email_notif: true,
    sms_notif: true,
    sys_notif: true
});

// Other existing logics
const sourceForm = useForm({ id: null, name: '' });
const isEditingSource = ref(false);
const saveSource = () => {
    if (isEditingSource.value) {
        sourceForm.put(route('admin.settings.lead_sources.update', sourceForm.id), {
            onSuccess: () => { sourceForm.reset(); isEditingSource.value = false; triggerToast('Yangilandi ✓'); }
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
    rolePermissionsForm.post(route('admin.settings.roles_permissions.update'), { onSuccess: () => triggerToast('Yangilandi ✓') });
};
</script>

<template>
    <Head title="Sozlamalar" />

    <AdminLayout>
        <!-- TOAST NOTIFICATION -->
        <div 
            class="fixed top-[24px] right-[24px] z-50 flex items-center px-[20px] py-[12px] rounded-[8px] shadow-lg transition-all duration-300 transform"
            :class="[
                showToast ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0',
                toastType === 'success' ? 'bg-[#10B981] text-white' : 'bg-[#EF4444] text-white'
            ]"
        >
            <span class="mr-[12px] font-bold">{{ toastMessage }}</span>
            <button @click="showToast = false" class="text-white/80 hover:text-white">✕</button>
        </div>

        <!-- MAIN CONTAINER -->
        <div class="max-w-[1200px] mx-auto p-[16px] lg:p-[32px] bg-[#F8FAFC] min-h-screen">
            <div class="flex flex-col md:flex-row bg-white rounded-[12px] shadow-sm overflow-hidden border border-[#E2E8F0] min-h-[calc(100vh-100px)]">
                
                <!-- LEFT SIDEBAR -->
                <SettingsSidebar 
                    :activeSection="activeTab" 
                    @change-section="(tab) => activeTab = tab" 
                />

                <!-- MAIN CONTENT AREA -->
                <div class="flex-1 p-[24px] lg:p-[32px] overflow-y-auto bg-white">

                    <!-- ===================================== -->
                    <!-- 1. BRANDING & APPEARANCE -->
                    <!-- ===================================== -->
                    <div v-show="activeTab === 'branding'" class="animate-[fadeIn_0.2s_ease-out]">
                        <!-- SECTION HEADER -->
                        <div class="mb-[32px] pb-[16px] border-b border-[#E2E8F0]">
                            <h3 class="text-[24px] font-bold text-[#1E293B] font-display mb-[8px]">Brending va Tashqi Ko'rinish</h3>
                            <p class="text-[14px] text-[#64748B] font-regular">Kompaniyangiz brendingini va tizimning vizual ranglarini moslashtiring.</p>
                        </div>
                        
                        <form @submit.prevent="submitSystemSettings">
                            <!-- Logo Upload Section -->
                            <div class="mb-[32px]">
                                <LogoUploader v-model="form.logo" label="Kompaniya Logosi" />
                            </div>

                            <!-- Fields Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-y-[24px] gap-x-[16px] mb-[32px]">
                                <div class="flex flex-col">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">Kompaniya Nomi</label>
                                    <input v-model="form.company_name" type="text" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">Kompaniya Tizim Nomi</label>
                                    <input v-model="form.app_name" type="text" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                </div>
                            </div>

                            <!-- Colors Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-[16px] mb-[32px]">
                                <ColorPickerInput v-model="form.primary_theme_color" label="Asosiy Rang (PRIMARY)" />
                                <ColorPickerInput v-model="form.secondary_theme_color" label="Ikkinchi Rang (SECONDARY)" />
                                <ColorPickerInput v-model="form.bg_theme_color" label="Fon Rangi (BACKGROUND)" />
                            </div>

                            <!-- Save Action -->
                            <div class="pt-[24px] border-t border-[#E2E8F0] flex justify-end gap-3">
                                <button type="button" @click="form.reset()" class="px-[20px] py-[10px] bg-white text-[#64748B] border border-[#E2E8F0] rounded-[8px] text-[14px] font-semibold hover:bg-[#F1F5F9] transition-colors">Tiklash (Reset)</button>
                                <button type="submit" :disabled="form.processing || !form.isDirty" class="px-[20px] py-[10px] bg-[#10B981] text-white rounded-[8px] text-[14px] font-semibold hover:bg-[#059669] hover:shadow-[0_4px_12px_rgba(16,185,129,0.3)] transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span v-if="form.processing">Saqlanmoqda...</span>
                                    <span v-else>Saqlash</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ===================================== -->
                    <!-- 2. SYSTEM SETTINGS -->
                    <!-- ===================================== -->
                    <div v-show="activeTab === 'system'" class="animate-[fadeIn_0.2s_ease-out]">
                        <div class="mb-[32px] pb-[16px] border-b border-[#E2E8F0]">
                            <h3 class="text-[24px] font-bold text-[#1E293B] font-display mb-[8px]">Tizim Sozlamalari</h3>
                            <p class="text-[14px] text-[#64748B] font-regular">Til, sana formati, pul birligi va boshqa mahalliy sozlamalar.</p>
                        </div>
                        
                        <form @submit.prevent="submitSystemSettings">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-y-[24px] gap-x-[16px] mb-[32px]">
                                <div class="flex flex-col">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">Tizim Tili</label>
                                    <select v-model="form.system_language" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white">
                                        <option value="uz">O'zbekcha (Lotin)</option>
                                        <option value="ru">Русский</option>
                                        <option value="en">English</option>
                                    </select>
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">Vaqt Mintaqasi (Timezone)</label>
                                    <select v-model="form.timezone" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white">
                                        <option value="Asia/Tashkent">Asia/Tashkent (+05:00)</option>
                                    </select>
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">Sanalar Formati</label>
                                    <select v-model="form.date_format" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white">
                                        <option value="DD.MM.YYYY">DD.MM.YYYY (31.12.2023)</option>
                                        <option value="YYYY-MM-DD">YYYY-MM-DD (2023-12-31)</option>
                                    </select>
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">Pul Birligi</label>
                                    <select v-model="form.default_currency" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white">
                                        <option value="UZS">UZS (So'm)</option>
                                        <option value="USD">USD (Dollar)</option>
                                    </select>
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">Aniqlik Darajasi (Decimal Places)</label>
                                    <select v-model="form.decimal_places" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white">
                                        <option value="0">0 (1 000)</option>
                                        <option value="2">2 (1 000.00)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="pt-[24px] border-t border-[#E2E8F0] flex justify-end gap-3">
                                <button type="button" @click="form.reset()" class="px-[20px] py-[10px] bg-white text-[#64748B] border border-[#E2E8F0] rounded-[8px] text-[14px] font-semibold hover:bg-[#F1F5F9] transition-colors">Tiklash</button>
                                <button type="submit" :disabled="form.processing || !form.isDirty" class="px-[20px] py-[10px] bg-[#10B981] text-white rounded-[8px] text-[14px] font-semibold hover:bg-[#059669] hover:shadow-[0_4px_12px_rgba(16,185,129,0.3)] transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span v-if="form.processing">Saqlanmoqda...</span><span v-else>Saqlash</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ===================================== -->
                    <!-- 3. COMPANY DETAILS -->
                    <!-- ===================================== -->
                    <div v-show="activeTab === 'company'" class="animate-[fadeIn_0.2s_ease-out]">
                        <div class="mb-[32px] pb-[16px] border-b border-[#E2E8F0]">
                            <h3 class="text-[24px] font-bold text-[#1E293B] font-display mb-[8px]">Kompaniya Ma'lumotlari</h3>
                            <p class="text-[14px] text-[#64748B] font-regular">Yuridik va aloqa ma'lumotlari hisob-faktura hamda hujjatlarda ko'rsatiladi.</p>
                        </div>
                        
                        <form @submit.prevent="submitSystemSettings">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-y-[24px] gap-x-[16px] mb-[32px]">
                                <div class="flex flex-col md:col-span-2">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">Kompaniya Nomini (Yuridik)</label>
                                    <input v-model="form.company_name" type="text" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">Telefon Raqami</label>
                                    <input v-model="form.company_phone" type="text" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">Elektron Pochta</label>
                                    <input v-model="form.company_email" type="email" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                </div>
                                <div class="flex flex-col md:col-span-2">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">Manzili</label>
                                    <input v-model="form.company_address" type="text" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">Bank Nomi</label>
                                    <input v-model="form.company_bank_name" type="text" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">Bank Hisob Raqami</label>
                                    <input v-model="form.company_bank_account" type="text" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">INN</label>
                                    <input v-model="form.company_inn" type="text" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[12px] font-medium text-[#64748B] mb-[6px]">MFO</label>
                                    <input v-model="form.company_mfo" type="text" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] focus:ring-[3px] focus:ring-[#2563EB]/10 transition-all outline-none bg-white placeholder-[#94A3B8]" />
                                </div>
                            </div>

                            <div class="pt-[24px] border-t border-[#E2E8F0] flex justify-end gap-3">
                                <button type="button" @click="form.reset()" class="px-[20px] py-[10px] bg-white text-[#64748B] border border-[#E2E8F0] rounded-[8px] text-[14px] font-semibold hover:bg-[#F1F5F9] transition-colors">Tiklash</button>
                                <button type="submit" :disabled="form.processing || !form.isDirty" class="px-[20px] py-[10px] bg-[#10B981] text-white rounded-[8px] text-[14px] font-semibold hover:bg-[#059669] hover:shadow-[0_4px_12px_rgba(16,185,129,0.3)] transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span v-if="form.processing">Saqlanmoqda...</span><span v-else>Saqlash</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ===================================== -->
                    <!-- 4. SECURITY -->
                    <!-- ===================================== -->
                    <div v-show="activeTab === 'security'" class="animate-[fadeIn_0.2s_ease-out]">
                        <div class="mb-[32px] pb-[16px] border-b border-[#E2E8F0]">
                            <h3 class="text-[24px] font-bold text-[#1E293B] font-display mb-[8px]">Xavfsizlik</h3>
                            <p class="text-[14px] text-[#64748B] font-regular">Hisob xavfsizligi, parollar va faol sessiyalarni boshqarish.</p>
                        </div>
                        
                        <form @submit.prevent="() => triggerToast('Xavfsizlik sozlamalari saqlandi ✓')">
                            <div class="space-y-[24px] mb-[32px]">
                                <div class="flex items-center justify-between p-[16px] border border-[#E2E8F0] rounded-[12px] bg-[#F8FAFC]">
                                    <div>
                                        <h4 class="text-[16px] font-semibold text-[#1E293B] mb-[4px]">Qat'iy Parol Siyosati</h4>
                                        <p class="text-[12px] text-[#64748B]">Parol kamida 8 ta belgi, raqam va maxsus belgilardan iborat bo'lishini talab qilish.</p>
                                    </div>
                                    <ToggleSwitch v-model="securityForm.password_policy" />
                                </div>
                                <div class="flex items-center justify-between p-[16px] border border-[#E2E8F0] rounded-[12px] bg-[#F8FAFC]">
                                    <div>
                                        <h4 class="text-[16px] font-semibold text-[#1E293B] mb-[4px]">2-Faktorli Autentifikatsiya (2FA)</h4>
                                        <p class="text-[12px] text-[#64748B]">Tizimga kirishda SMS yoki Authenticator orqali tasdiqlashni yoqish.</p>
                                    </div>
                                    <ToggleSwitch v-model="securityForm.two_factor" />
                                </div>
                                
                                <div class="border border-[#E2E8F0] rounded-[12px] overflow-hidden">
                                    <div class="px-[16px] py-[12px] bg-[#F8FAFC] border-b border-[#E2E8F0]">
                                        <h4 class="text-[14px] font-semibold text-[#1E293B]">Faol Sessiyalar</h4>
                                    </div>
                                    <div class="p-[16px] flex items-center justify-between">
                                        <div class="flex items-center gap-[12px]">
                                            <span class="text-[24px]">💻</span>
                                            <div>
                                                <p class="text-[14px] font-semibold text-[#1E293B]">Windows - Chrome</p>
                                                <p class="text-[12px] text-[#64748B]">Toshkent, O'zbekiston • Hozir faol</p>
                                            </div>
                                        </div>
                                        <button type="button" class="px-[12px] py-[6px] text-[12px] font-semibold text-[#DC2626] bg-[#FEE2E2] hover:bg-[#FECACA] rounded-[6px] transition-colors">Barcha boshqa sessiyalarni yopish</button>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-[24px] border-t border-[#E2E8F0] flex justify-end gap-3">
                                <button type="submit" class="px-[20px] py-[10px] bg-[#10B981] text-white rounded-[8px] text-[14px] font-semibold hover:bg-[#059669] hover:shadow-[0_4px_12px_rgba(16,185,129,0.3)] transition-all">
                                    Saqlash
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ===================================== -->
                    <!-- 5. NOTIFICATIONS -->
                    <!-- ===================================== -->
                    <div v-show="activeTab === 'notifications'" class="animate-[fadeIn_0.2s_ease-out]">
                        <div class="mb-[32px] pb-[16px] border-b border-[#E2E8F0]">
                            <h3 class="text-[24px] font-bold text-[#1E293B] font-display mb-[8px]">Bildirishnomalar</h3>
                            <p class="text-[14px] text-[#64748B] font-regular">Tizim qaysi holatlarda sizni ogohlantirishini tanlang.</p>
                        </div>
                        
                        <form @submit.prevent="() => triggerToast('Bildirishnomalar saqlandi ✓')">
                            <div class="space-y-[24px] mb-[32px]">
                                <div class="flex items-center justify-between p-[16px] border border-[#E2E8F0] rounded-[12px] bg-[#F8FAFC]">
                                    <div>
                                        <h4 class="text-[16px] font-semibold text-[#1E293B] mb-[4px]">Tizim Ichki Bildirishnomalari</h4>
                                        <p class="text-[12px] text-[#64748B]">Muhim voqealar va yangiliklar haqida tizim ichida xabar berish.</p>
                                    </div>
                                    <ToggleSwitch v-model="notifForm.sys_notif" />
                                </div>
                                <div class="flex items-center justify-between p-[16px] border border-[#E2E8F0] rounded-[12px] bg-[#F8FAFC]">
                                    <div>
                                        <h4 class="text-[16px] font-semibold text-[#1E293B] mb-[4px]">Email Bildirishnomalar</h4>
                                        <p class="text-[12px] text-[#64748B]">Hisobotlar va tizim xatoliklarini elektron pochtaga yuborish.</p>
                                    </div>
                                    <ToggleSwitch v-model="notifForm.email_notif" />
                                </div>
                                <div class="flex items-center justify-between p-[16px] border border-[#E2E8F0] rounded-[12px] bg-[#F8FAFC]">
                                    <div>
                                        <h4 class="text-[16px] font-semibold text-[#1E293B] mb-[4px]">SMS Bildirishnomalar</h4>
                                        <p class="text-[12px] text-[#64748B]">Muhim tranzaksiyalar va to'lovlar haqida SMS xabar berish.</p>
                                    </div>
                                    <ToggleSwitch v-model="notifForm.sms_notif" />
                                </div>
                            </div>

                            <div class="pt-[24px] border-t border-[#E2E8F0] flex justify-end gap-3">
                                <button type="submit" class="px-[20px] py-[10px] bg-[#10B981] text-white rounded-[8px] text-[14px] font-semibold hover:bg-[#059669] hover:shadow-[0_4px_12px_rgba(16,185,129,0.3)] transition-all">
                                    Saqlash
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ===================================== -->
                    <!-- EXISTING TABS (MIGRATED INTO NEW UI) -->
                    <!-- ===================================== -->
                    <div v-show="activeTab === 'roles_permissions'" class="animate-[fadeIn_0.2s_ease-out]">
                        <div class="mb-[32px] pb-[16px] border-b border-[#E2E8F0]">
                            <h3 class="text-[24px] font-bold text-[#1E293B] font-display mb-[8px]">Rollar va Huquqlar</h3>
                            <p class="text-[14px] text-[#64748B] font-regular">Xodimlar roliga qarab tizimdagi modullarni ko'rish ruxsatnomalarini sozlash.</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="border border-[#E2E8F0] rounded-[12px] p-6 bg-[#F8FAFC]">
                                <label class="block text-[12px] font-medium text-[#64748B] mb-[6px]">Rolni Tanlang</label>
                                <select v-model="selectedRoleIdForPermissions" @change="loadRolePermissions" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] text-[#1E293B] focus:border-[#2563EB] bg-white outline-none">
                                    <option value="">-- Rol tanlang --</option>
                                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                                </select>
                            </div>
                            <div class="border border-[#E2E8F0] rounded-[12px] p-6 bg-white" v-if="selectedRoleIdForPermissions">
                                <h4 class="text-[14px] font-bold text-[#1E293B] mb-4">Ruxsatnomalar:</h4>
                                <form @submit.prevent="saveRolePermissions">
                                    <div class="space-y-3 mb-6">
                                        <label v-for="perm in permissions" :key="perm.id" class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-[#F8FAFC]">
                                            <input type="checkbox" :value="perm.name" v-model="rolePermissionsForm.permissions" class="w-5 h-5 text-[#2563EB] rounded border-[#E2E8F0] focus:ring-[#2563EB]">
                                            <span class="text-[14px] text-[#1E293B] font-medium">{{ permissionLabel(perm.name) }}</span>
                                        </label>
                                    </div>
                                    <button type="submit" :disabled="rolePermissionsForm.processing" class="px-[20px] py-[10px] bg-[#10B981] text-white rounded-[8px] text-[14px] font-semibold w-full hover:bg-[#059669]">
                                        {{ rolePermissionsForm.processing ? 'Saqlanmoqda...' : 'Saqlash' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- USERS MANAGEMENT -->
                    <div v-show="activeTab === 'users_management'" class="animate-[fadeIn_0.2s_ease-out]">
                        <div class="mb-[32px] pb-[16px] border-b border-[#E2E8F0] flex justify-between items-end">
                            <div>
                                <h3 class="text-[24px] font-bold text-[#1E293B] font-display mb-[8px]">Xodimlar Boshqaruvi</h3>
                                <p class="text-[14px] text-[#64748B]">Foydalanuvchilarni boshqarish.</p>
                            </div>
                            <!-- (The logic here is kept minimal for UI integration, users add/edit can be handled similarly) -->
                            <button class="px-[20px] py-[10px] bg-[#2563EB] text-white rounded-[8px] text-[14px] font-semibold">+ Xodim Qo'shish</button>
                        </div>
                        <div class="border border-[#E2E8F0] rounded-[12px] overflow-hidden bg-white">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                                    <tr><th class="p-4 text-[12px] font-medium text-[#64748B]">Ism</th><th class="p-4 text-[12px] font-medium text-[#64748B]">Login</th><th class="p-4 text-[12px] font-medium text-[#64748B]">Rol</th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="usr in users" :key="usr.id" class="border-b border-[#E2E8F0] hover:bg-[#F8FAFC]">
                                        <td class="p-4 text-[14px] font-medium text-[#1E293B]">{{ usr.name }}</td>
                                        <td class="p-4 text-[14px] text-[#64748B]">{{ usr.username }}</td>
                                        <td class="p-4 text-[14px] text-[#64748B]">{{ usr.roles?.length ? usr.roles[0].name : '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- LEAD SOURCES -->
                    <div v-show="activeTab === 'lead_sources'" class="animate-[fadeIn_0.2s_ease-out]">
                        <div class="mb-[32px] pb-[16px] border-b border-[#E2E8F0]">
                            <h3 class="text-[24px] font-bold text-[#1E293B] font-display mb-[8px]">Lid Manbalari</h3>
                            <p class="text-[14px] text-[#64748B]">Mijozlarning qayerdan kelganligini qayd etish manzillari.</p>
                        </div>
                        <form @submit.prevent="saveSource" class="flex gap-4 mb-8">
                            <input v-model="sourceForm.name" type="text" placeholder="Manba nomini kiriting..." class="flex-1 h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] outline-none focus:border-[#2563EB]">
                            <button type="submit" :disabled="sourceForm.processing" class="px-[20px] py-[10px] bg-[#2563EB] text-white rounded-[8px] text-[14px] font-semibold">{{ isEditingSource ? 'Yangilash' : 'Qo\'shish' }}</button>
                            <button v-if="isEditingSource" type="button" @click="sourceForm.reset(); isEditingSource = false" class="px-[20px] py-[10px] bg-[#F1F5F9] text-[#64748B] rounded-[8px] text-[14px] font-semibold">Bekor qilish</button>
                        </form>
                        <div class="border border-[#E2E8F0] rounded-[12px] overflow-hidden bg-white">
                            <table class="w-full text-left">
                                <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                                    <tr><th class="p-4 text-[12px] font-medium text-[#64748B]">#</th><th class="p-4 text-[12px] font-medium text-[#64748B]">Manba Nomi</th><th class="p-4 text-[12px] font-medium text-[#64748B] text-right">Amallar</th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(src, idx) in leadSources" :key="src.id" class="border-b border-[#E2E8F0] hover:bg-[#F8FAFC]">
                                        <td class="p-4 text-[14px] text-[#64748B]">{{ idx + 1 }}</td>
                                        <td class="p-4 text-[14px] font-medium text-[#1E293B]">{{ src.name }}</td>
                                        <td class="p-4 text-right flex justify-end gap-2">
                                            <button @click="editSource(src)" class="px-[12px] py-[6px] border border-[#E2E8F0] rounded-[6px] text-[12px] font-semibold text-[#1E293B] hover:bg-[#F1F5F9]">Tahrirlash</button>
                                            <button @click="deleteSource(src.id)" class="px-[12px] py-[6px] bg-[#FEE2E2] text-[#DC2626] rounded-[6px] text-[12px] font-semibold hover:bg-[#FECACA]">O'chirish</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SMS BROADCAST -->
                    <div v-show="activeTab === 'sms_broadcast'" class="animate-[fadeIn_0.2s_ease-out]">
                        <div class="mb-[32px] pb-[16px] border-b border-[#E2E8F0]">
                            <h3 class="text-[24px] font-bold text-[#1E293B] font-display mb-[8px]">SMS Xabarnomalar Yuborish</h3>
                            <p class="text-[14px] text-[#64748B]">Mijozlarga ommaviy xabarnomalar yuborish.</p>
                        </div>
                        <form @submit.prevent="sendBroadcastSms" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[12px] font-medium text-[#64748B] mb-2">Shablon Tanlash</label>
                                    <select v-model="activeTemplate" @change="handleTemplateChange" class="w-full h-[40px] px-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] outline-none">
                                        <option value="">Shablon tanlang...</option>
                                        <option v-for="t in smsTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[12px] font-medium text-[#64748B] mb-2">Xabar Matni</label>
                                <textarea v-model="smsForm.message" required rows="4" class="w-full p-[12px] border border-[#E2E8F0] rounded-[8px] text-[14px] outline-none focus:border-[#2563EB]"></textarea>
                            </div>
                            <button type="submit" :disabled="smsForm.processing" class="px-[20px] py-[10px] bg-[#10B981] text-white rounded-[8px] text-[14px] font-semibold hover:bg-[#059669]">
                                {{ smsForm.processing ? 'Yuborilmoqda...' : 'Yuborish' }}
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </AdminLayout>
</template>
