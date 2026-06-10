<script setup>
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

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

// Basic settings forms
const form = useForm({
    company_name: props.settings?.company_name || 'ITCloud Sher-Dor ERP',
    primary_theme_color: props.settings?.primary_theme_color || '#00F0FF',
    neon_glow_intensity: props.settings?.neon_glow_intensity || '0 0 15px',
    default_currency: props.settings?.default_currency || 'USD',
    tax_rate: props.settings?.tax_rate || 12.00,
    default_penalty_rate: props.settings?.default_penalty_rate || 0.10,
    logo: null,
    background_image: null,
});

const themeStyle = user.theme_style || 'apple_glass';
const isDarkTheme = ['galaxy', 'cyberpunk', 'minimalist', 'gold', 'itcloud_cyberglass'].includes(themeStyle);

const defaultLabelColor = isDarkTheme ? '#CCCCCC' : '#374151';
const defaultDropdownBg = isDarkTheme ? '#111111' : '#FFFFFF';
const defaultDropdownText = isDarkTheme ? '#ffffff' : '#111827';

const profileForm = useForm({
    name: user.name || '',
    email: user.email || '',
    phone: user.phone || '',
    password: '',
    language: user.language || 'uz',
    theme_color: user.theme_color || '#00F0FF',
    theme_style: themeStyle,
    label_font_size: user.label_font_size || 12,
    label_color: (!user.label_color || user.label_color === '#CCCCCC') ? defaultLabelColor : user.label_color,
    dropdown_bg_color: (!user.dropdown_bg_color || user.dropdown_bg_color === '#111111') ? defaultDropdownBg : user.dropdown_bg_color,
    dropdown_text_color: (!user.dropdown_text_color || user.dropdown_text_color === '#ffffff') ? defaultDropdownText : user.dropdown_text_color,
    avatar: null,
});

const themeColors = [
    '#FFD700', // Sherdor Gold (Firm Design)
    '#00F0FF', '#B026FF', '#39FF14', '#FF003C', '#FCee09', 
    '#9D00FF', '#00FF9D', '#FF9D00', '#FF009D', '#009DFF',
    '#FFFFFF', '#888888', '#FF5555', '#55FF55', '#5555FF'
];

function submit() {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => alert('Settings updated successfully!')
    });
}

function submitProfile() {
    profileForm.post(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            profileForm.password = '';
            alert('Profile updated successfully!');
        }
    });
}

const updateTheme = (color) => {
    profileForm.theme_color = color;
    document.documentElement.style.setProperty('--color-neon-primary', color);
    const hexToRgb = (hex) => {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` : '0, 240, 255';
    };
    document.documentElement.style.setProperty('--shadow-sm', `0 0 15px rgba(${hexToRgb(color)}, 0.3)`);
    submitProfile();
};

const updateLanguage = (lang) => {
    profileForm.language = lang;
    localStorage.setItem('locale', lang);
    document.documentElement.lang = lang;
    submitProfile();
    setTimeout(() => {
        window.location.reload();
    }, 500);
};

const updateThemeStyle = (style) => {
    profileForm.theme_style = style;
    submitProfile();
    setTimeout(() => {
        window.location.reload(); 
    }, 500);
};

const updateLabelSettings = () => {
    document.documentElement.style.setProperty('--label-font-size', profileForm.label_font_size + 'px');
    document.documentElement.style.setProperty('--label-color', profileForm.label_color);
    document.documentElement.style.setProperty('--dropdown-bg-color', profileForm.dropdown_bg_color);
    document.documentElement.style.setProperty('--dropdown-text-color', profileForm.dropdown_text_color);
    submitProfile();
};

// ==========================================
// 1. LEAD SOURCES SUB-SECTION
// ==========================================
const sourceForm = useForm({
    id: null,
    name: ''
});
const isEditingSource = ref(false);

const saveSource = () => {
    if (isEditingSource.value) {
        sourceForm.put(route('admin.settings.lead_sources.update', sourceForm.id), {
            onSuccess: () => {
                sourceForm.reset();
                isEditingSource.value = false;
                alert('Source updated successfully!');
            }
        });
    } else {
        sourceForm.post(route('admin.settings.lead_sources.store'), {
            onSuccess: () => {
                sourceForm.reset();
                alert('Source created successfully!');
            }
        });
    }
};

const editSource = (source) => {
    sourceForm.id = source.id;
    sourceForm.name = source.name;
    isEditingSource.value = true;
};

const deleteSource = (id) => {
    if (confirm('Are you sure you want to delete this source?')) {
        sourceForm.delete(route('admin.settings.lead_sources.destroy', id), {
            onSuccess: () => alert('Source deleted successfully!')
        });
    }
};

// ==========================================
// 2. SMS BROADCAST SUB-SECTION
// ==========================================
const selectedClients = ref([]);
const smsForm = useForm({
    client_ids: [],
    message: '',
    send_type: 'immediate',
    scheduled_at: ''
});
const activeTemplate = ref('');

const selectAllClients = (event) => {
    if (event.target.checked) {
        selectedClients.value = props.clients.map(c => c.id);
    } else {
        selectedClients.value = [];
    }
};

const handleTemplateChange = () => {
    const template = props.smsTemplates.find(t => t.id === activeTemplate.value);
    if (template) {
        smsForm.message = template.content;
    }
};

const sendBroadcastSms = () => {
    if (selectedClients.value.length === 0) {
        alert('Please select at least one client.');
        return;
    }
    smsForm.client_ids = selectedClients.value;
    smsForm.post(route('admin.settings.sms.send'), {
        onSuccess: () => {
            selectedClients.value = [];
            smsForm.reset();
            activeTemplate.value = '';
            alert('SMS dispatch triggered successfully!');
        }
    });
};

// ==========================================
// 3. USER MANAGEMENT SUB-SECTION
// ==========================================
const isEditingUser = ref(false);
const editingUserId = ref(null);
const userForm = useForm({
    name: '',
    last_name: '',
    middle_name: '',
    username: '',
    phone: '',
    password: '',
    role: 'Worker',
    building_id: '',
    warehouse_id: '',
    all_objects: false
});

const submitUserForm = () => {
    if (isEditingUser.value) {
        userForm.put(route('admin.settings.users.update', editingUserId.value), {
            onSuccess: () => {
                resetUserForm();
                alert('User updated successfully!');
            }
        });
    } else {
        userForm.post(route('admin.settings.users.store'), {
            onSuccess: () => {
                resetUserForm();
                alert('User created successfully!');
            }
        });
    }
};

const editUser = (usr) => {
    isEditingUser.value = true;
    editingUserId.value = usr.id;
    userForm.name = usr.name;
    userForm.last_name = usr.last_name || '';
    userForm.middle_name = usr.middle_name || '';
    userForm.username = usr.username || '';
    userForm.phone = usr.phone || '';
    userForm.password = '';
    userForm.role = usr.roles && usr.roles.length > 0 ? usr.roles[0].name : 'Worker';
    userForm.building_id = usr.building_id || '';
    userForm.warehouse_id = usr.warehouse_id || '';
    userForm.all_objects = !usr.building_id && !usr.warehouse_id;
};

const deleteUser = (id) => {
    if (confirm('Are you sure you want to delete this user?')) {
        userForm.delete(route('admin.settings.users.destroy', id), {
            onSuccess: () => alert('User deleted successfully!')
        });
    }
};

const resetUserForm = () => {
    userForm.reset();
    isEditingUser.value = false;
    editingUserId.value = null;
};

// ==========================================
// 4. ROLES & PERMISSIONS SUB-SECTION
// ==========================================
const selectedRoleIdForPermissions = ref('');
const rolePermissionsForm = useForm({
    role_id: '',
    permissions: []
});

// Map system labels
const permissionLabel = (perm) => {
    const labels = {
        'access dashboard': 'Boshqaruv paneli (Dashboard)',
        'access reports': 'Hisobotlar (Reports)',
        'access debtors': 'Qarzdorlar (Debtors)',
        'access transactions': 'Kirim-chiqim (Transactions)',
        'access objects': 'Obyektlar (Objects)',
        'access clients': 'Mijozlar (Clients)',
        'access finance': 'Moliya (Finance)',
        'access hr': 'HRM & Ish haqi',
        'access settings': 'Sozlamalar (Settings)',
    };
    return labels[perm] || perm;
};

const loadRolePermissions = () => {
    const role = props.roles.find(r => r.id === selectedRoleIdForPermissions.value);
    if (role) {
        rolePermissionsForm.role_id = role.id;
        rolePermissionsForm.permissions = role.permissions.map(p => p.name);
    } else {
        rolePermissionsForm.reset();
    }
};

const saveRolePermissions = () => {
    if (!rolePermissionsForm.role_id) {
        alert('Please select a role.');
        return;
    }
    rolePermissionsForm.post(route('admin.settings.roles_permissions.update'), {
        onSuccess: () => alert('Permissions updated successfully!')
    });
};
</script>

<template>
    <Head title="System Settings" />

    <AdminLayout>
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row gap-8 animate-[fadeIn_0.5s_ease-out]">
            
            <!-- Sidebar Tabs -->
            <div class="w-full md:w-72 space-y-3 shrink-0">
                <button @click="activeTab = 'branding'" :class="activeTab === 'branding' ? 'bg-neon-primary/20 border-neon-primary text-neon-primary shadow-sm' : 'bg-gray-500/50 border-gray-200 hover:border-white/30 text-gray-900/70'" class="w-full text-left px-5 py-4 rounded-2xl border transition-all font-display text-xs uppercase tracking-wider flex items-center gap-4">
                    <span class="text-2xl drop-shadow">🎨</span> Brending va Tashqi Ko'rinish
                </button>
                
                <button @click="activeTab = 'financial'" :class="activeTab === 'financial' ? 'bg-neon-primary/20 border-neon-primary text-neon-primary shadow-sm' : 'bg-gray-500/50 border-gray-200 hover:border-white/30 text-gray-900/70'" class="w-full text-left px-5 py-4 rounded-2xl border transition-all font-display text-xs uppercase tracking-wider flex items-center gap-4">
                    <span class="text-2xl drop-shadow">🏦</span> Financial Defaults
                </button>

                <button @click="activeTab = 'profile'" :class="activeTab === 'profile' ? 'bg-neon-primary/20 border-neon-primary text-neon-primary shadow-sm' : 'bg-gray-500/50 border-gray-200 hover:border-white/30 text-gray-900/70'" class="w-full text-left px-5 py-4 rounded-2xl border transition-all font-display text-xs uppercase tracking-wider flex items-center gap-4">
                    <span class="text-2xl drop-shadow">👤</span> Profil va Dizayn
                </button>

                <button @click="activeTab = 'lead_sources'" :class="activeTab === 'lead_sources' ? 'bg-neon-primary/20 border-neon-primary text-neon-primary shadow-sm' : 'bg-gray-500/50 border-gray-200 hover:border-white/30 text-gray-900/70'" class="w-full text-left px-5 py-4 rounded-2xl border transition-all font-display text-xs uppercase tracking-wider flex items-center gap-4">
                    <span class="text-2xl drop-shadow">🎯</span> Manbalar (Lead Sources)
                </button>

                <button @click="activeTab = 'sms_broadcast'" :class="activeTab === 'sms_broadcast' ? 'bg-neon-primary/20 border-neon-primary text-neon-primary shadow-sm' : 'bg-gray-500/50 border-gray-200 hover:border-white/30 text-gray-900/70'" class="w-full text-left px-5 py-4 rounded-2xl border transition-all font-display text-xs uppercase tracking-wider flex items-center gap-4">
                    <span class="text-2xl drop-shadow">💬</span> SMS Xabarnoma
                </button>

                <button @click="activeTab = 'users_management'" :class="activeTab === 'users_management' ? 'bg-neon-primary/20 border-neon-primary text-neon-primary shadow-sm' : 'bg-gray-500/50 border-gray-200 hover:border-white/30 text-gray-900/70'" class="w-full text-left px-5 py-4 rounded-2xl border transition-all font-display text-xs uppercase tracking-wider flex items-center gap-4">
                    <span class="text-2xl drop-shadow">👥</span> Xodimlar (Users)
                </button>

                <button @click="activeTab = 'roles_permissions'" :class="activeTab === 'roles_permissions' ? 'bg-neon-primary/20 border-neon-primary text-neon-primary shadow-sm' : 'bg-gray-500/50 border-gray-200 hover:border-white/30 text-gray-900/70'" class="w-full text-left px-5 py-4 rounded-2xl border transition-all font-display text-xs uppercase tracking-wider flex items-center gap-4">
                    <span class="text-2xl drop-shadow">🔐</span> Rollar va Ruxsatlar
                </button>
            </div>

            <!-- Content Area -->
            <div class="flex-1 min-w-0">
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 md:p-8 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-neon-primary/10 blur-[100px] group-hover:bg-neon-primary/20 transition-all pointer-events-none"></div>

                    <!-- Branding Tab -->
                    <div v-show="activeTab === 'branding'" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                        <h3 class="text-blue-600 font-display text-lg tracking-widest uppercase border-b border-gray-200 pb-4 mb-6">Brending va Tashqi Ko'rinish</h3>
                        
                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label class="block text-xs font-mono text-gray-500 mb-2 uppercase">Company Name</label>
                                <input v-model="form.company_name" type="text" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-mono text-gray-500 mb-2 uppercase">Primary Theme Color</label>
                                <div class="flex items-center gap-4 bg-gray-500/5 border border-gray-200 rounded-xl p-2 transition-all focus-within:border-neon-primary">
                                    <input v-model="form.primary_theme_color" type="color" class="w-10 h-10 rounded cursor-pointer bg-transparent border-0 p-0">
                                    <input v-model="form.primary_theme_color" type="text" class="bg-transparent border-0 text-gray-900 flex-1 font-mono focus:ring-0">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-mono text-gray-500 mb-2 uppercase">{{ $t('custom_logo_desc') }}</label>
                                <input type="file" @input="form.logo = $event.target.files[0]" class="w-full border border-gray-200 rounded-xl p-2 text-gray-900 font-mono text-xs text-gray-500 file:bg-gray-100 file:border-0 file:rounded file:text-gray-900 file:px-3 file:py-1 file:mr-3 hover:file:bg-white/20" accept="image/*">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-mono text-gray-500 mb-2 uppercase">Tizim Orqa Foni (Background Image)</label>
                                <input type="file" @input="form.background_image = $event.target.files[0]" class="w-full border border-gray-200 rounded-xl p-2 text-gray-900 font-mono text-xs text-gray-500 file:bg-gray-100 file:border-0 file:rounded file:text-gray-900 file:px-3 file:py-1 file:mr-3 hover:file:bg-white/20" accept="image/*">
                                <div v-if="$page.props.branding?.background_image_path" class="mt-3 relative w-full h-32 rounded-xl overflow-hidden border border-gray-300">
                                    <img :src="'/storage/' + $page.props.branding.background_image_path" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-gray-500/5 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                                        <span class="text-xs text-gray-900 font-mono uppercase tracking-widest">Joriy Orqa Fon</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-6 border-t border-gray-200">
                                <button type="submit" :disabled="form.processing" class="px-8 py-3 bg-neon-primary/20 border border-neon-primary text-neon-primary font-bold uppercase tracking-widest rounded-xl hover:shadow-sm hover:bg-neon-primary hover:text-black transition-all disabled:opacity-50 flex items-center gap-2">
                                    <span v-if="form.processing" class="animate-spin">🌀</span>{{ $t('save') }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Financials Tab -->
                    <div v-show="activeTab === 'financial'" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                        <h3 class="text-blue-600 font-display text-lg tracking-widest uppercase border-b border-gray-200 pb-4 mb-6">{{ $t('financial_defaults') }}</h3>
                        
                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label class="block text-xs font-mono text-gray-500 mb-2 uppercase">Default Currency</label>
                                <select v-model="form.default_currency" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                                    <option value="USD">USD ($)</option>
                                    <option value="UZS">UZS (So'm)</option>
                                    <option value="RUB">RUB (₽)</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-mono text-gray-500 mb-2 uppercase">Global Tax Rate (%)</label>
                                <input v-model="form.tax_rate" type="number" step="0.01" class="w-full border border-gray-200 rounded-xl p-3 text-emerald-600 font-bold text-lg focus:border-emerald-500 focus:ring-1 focus:ring-neon-green transition-all">
                            </div>

                            <div>
                                <label class="block text-xs font-mono text-gray-500 mb-2 uppercase">Default Daily Penalty Rate (%)</label>
                                <input v-model="form.default_penalty_rate" type="number" step="0.01" class="w-full border border-gray-200 rounded-xl p-3 text-red-600 font-bold text-lg focus:border-red-500 focus:ring-1 focus:ring-neon-red transition-all">
                            </div>

                            <div class="flex justify-end pt-6 border-t border-gray-200">
                                <button type="submit" :disabled="form.processing" class="px-8 py-3 bg-neon-primary/20 border border-neon-primary text-neon-primary font-bold uppercase tracking-widest rounded-xl hover:shadow-sm hover:bg-neon-primary hover:text-black transition-all disabled:opacity-50 flex items-center gap-2">
                                    <span v-if="form.processing" class="animate-spin">🌀</span>{{ $t('save') }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Profile & UI Customization Tab -->
                    <div v-show="activeTab === 'profile'" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                        <h3 class="text-blue-600 font-display text-lg tracking-widest uppercase border-b border-gray-200 pb-4 mb-6">Profil va Dizayn</h3>
                        
                        <form @submit.prevent="submitProfile" class="space-y-6">
                            <div class="flex items-center gap-6 mb-6">
                                <div class="w-20 h-20 rounded-2xl bg-gray-500/5 border border-gray-200 flex items-center justify-center overflow-hidden shrink-0">
                                    <img v-if="user.photo_path" :src="'/storage/' + user.photo_path" class="w-full h-full object-cover" />
                                    <span v-else class="text-3xl">👤</span>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-xs font-mono text-gray-500 uppercase mb-2">Avatar yuklash</label>
                                    <input type="file" @change="e => profileForm.avatar = e.target.files[0]" class="w-full bg-gray-500/5 border border-gray-200 rounded-xl p-2 text-gray-900 font-mono text-xs text-gray-500 file:bg-gray-100 file:border-0 file:rounded file:text-gray-900 file:px-3 file:py-1 file:mr-3 hover:file:bg-white/20">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-mono text-gray-500 uppercase mb-1">To'liq ism</label>
                                    <input type="text" v-model="profileForm.name" required class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                                    <div v-if="profileForm.errors.name" class="text-red-600 text-xs mt-1">{{ profileForm.errors.name }}</div>
                                </div>

                                <div>
                                    <label class="block text-xs font-mono text-gray-500 uppercase mb-1">Email</label>
                                    <input type="email" v-model="profileForm.email" required class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                                    <div v-if="profileForm.errors.email" class="text-red-600 text-xs mt-1">{{ profileForm.errors.email }}</div>
                                </div>

                                <div>
                                    <label class="block text-xs font-mono text-gray-500 uppercase mb-1">Telefon</label>
                                    <input type="text" v-model="profileForm.phone" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                                    <div v-if="profileForm.errors.phone" class="text-red-600 text-xs mt-1">{{ profileForm.errors.phone }}</div>
                                </div>

                                <div>
                                    <label class="block text-xs font-mono text-gray-500 uppercase mb-1">Yangi parol (ixtiyoriy)</label>
                                    <input type="password" v-model="profileForm.password" placeholder="••••••••" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                                    <div v-if="profileForm.errors.password" class="text-red-600 text-xs mt-1">{{ profileForm.errors.password }}</div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-6 border-t border-gray-200">
                                <button type="submit" :disabled="profileForm.processing" class="px-8 py-3 bg-neon-primary/20 border border-neon-primary text-neon-primary font-bold uppercase tracking-widest rounded-xl hover:shadow-sm hover:bg-neon-primary hover:text-black transition-all disabled:opacity-50 flex items-center gap-2">
                                    <span v-if="profileForm.processing" class="animate-spin">🌀</span>{{ $t('save') }}
                                </button>
                            </div>
                        </form>

                        <!-- Layout style selectors -->
                        <div class="border-t border-gray-100 pt-8 space-y-6">
                            <h4 class="text-gray-900 font-bold font-display text-md">Global Mavzu</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <button v-for="style in ['apple_glass', 'galaxy', 'cyberpunk', 'minimalist', 'gold', 'itcloud_cyberglass']" :key="style" type="button" @click="updateThemeStyle(style)" class="p-4 rounded-xl border flex flex-col items-center gap-2 transition-all" :class="profileForm.theme_style === style ? 'border-neon-primary bg-neon-primary/10 shadow-sm' : 'border-gray-200 hover:border-black/10 bg-gray-500/5'">
                                    <span class="text-2xl">{{ style === 'apple_glass' ? '🍏' : style === 'galaxy' ? '🌌' : style === 'cyberpunk' ? '🤖' : style === 'minimalist' ? '⬛' : style === 'gold' ? '⚜️' : '🌐' }}</span>
                                    <span class="font-mono text-xs uppercase">{{ style.replace('_', ' ') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 4: LEAD SOURCES (MANBALAR) -->
                    <!-- ========================================== -->
                    <div v-show="activeTab === 'lead_sources'" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                        <h3 class="text-blue-600 font-display text-lg tracking-widest uppercase border-b border-gray-200 pb-4 mb-6">Lid Manbalari (Lead Sources)</h3>
                        
                        <!-- Create/Edit Form -->
                        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6 mb-6">
                            <h4 class="text-sm font-bold text-gray-900 uppercase font-mono mb-4">{{ isEditingSource ? 'Manbani Tahrirlash' : 'Yangi Manba Qo\'shish' }}</h4>
                            <form @submit.prevent="saveSource" class="flex flex-col sm:flex-row gap-4 items-end">
                                <div class="flex-1 w-full">
                                    <label class="block text-xs font-mono text-gray-500 mb-2 uppercase">Manba Nomi (masalan: Facebook, Telegram)</label>
                                    <input v-model="sourceForm.name" required type="text" placeholder="Manba nomini kiriting..." class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all bg-white">
                                    <div v-if="sourceForm.errors.name" class="text-red-600 text-xs mt-1 font-mono">{{ sourceForm.errors.name }}</div>
                                </div>
                                <div class="flex gap-2 w-full sm:w-auto">
                                    <button type="submit" :disabled="sourceForm.processing" class="flex-1 sm:flex-initial px-6 py-3 bg-neon-primary/20 border border-neon-primary text-neon-primary font-bold uppercase text-xs tracking-wider rounded-xl hover:bg-neon-primary hover:text-black transition-all">
                                        {{ isEditingSource ? 'Yangilash' : 'Qo\'shish' }}
                                    </button>
                                    <button v-if="isEditingSource" type="button" @click="sourceForm.reset(); isEditingSource = false" class="px-6 py-3 bg-gray-100 border border-gray-300 text-gray-700 font-bold uppercase text-xs tracking-wider rounded-xl hover:bg-gray-200 transition-all">
                                        Bekor qilish
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Sources List -->
                        <div class="border border-gray-200 rounded-2xl overflow-hidden">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200 font-mono text-xs uppercase text-gray-500">
                                        <th class="p-4">#</th>
                                        <th class="p-4">Manba nomi</th>
                                        <th class="p-4 text-right">Amallar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-sm">
                                    <tr v-for="(source, index) in leadSources" :key="source.id" class="hover:bg-gray-50/50 transition-colors">
                                        <td class="p-4 font-mono text-xs text-gray-500">{{ index + 1 }}</td>
                                        <td class="p-4 font-semibold text-gray-900">{{ source.name }}</td>
                                        <td class="p-4 text-right flex justify-end gap-2">
                                            <button @click="editSource(source)" class="p-2 border border-blue-200 text-blue-600 rounded-lg hover:bg-blue-50 transition-all text-xs uppercase font-mono">
                                                Tahrirlash
                                            </button>
                                            <button @click="deleteSource(source.id)" class="p-2 border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition-all text-xs uppercase font-mono">
                                                O'chirish
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="leadSources.length === 0">
                                        <td colspan="3" class="p-8 text-center text-gray-500 font-mono">Hech qanday manbalar topilmadi.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 5: SMS BROADCAST -->
                    <!-- ========================================== -->
                    <div v-show="activeTab === 'sms_broadcast'" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                        <h3 class="text-blue-600 font-display text-lg tracking-widest uppercase border-b border-gray-200 pb-4 mb-6">SMS Xabarnomalar Yuborish</h3>
                        
                        <form @submit.prevent="sendBroadcastSms" class="space-y-6">
                            <!-- Template and Target options -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-mono text-gray-500 mb-2 uppercase">Tayyor SMS Shablonlari (Ixtiyoriy)</label>
                                    <select v-model="activeTemplate" @change="handleTemplateChange" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary bg-white">
                                        <option value="">Shablon tanlash...</option>
                                        <option v-for="temp in smsTemplates" :key="temp.id" :value="temp.id">{{ temp.name }}</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-mono text-gray-500 mb-2 uppercase">Yuborish turi</label>
                                    <div class="flex gap-4 p-3 bg-gray-50 border border-gray-200 rounded-xl">
                                        <label class="flex items-center gap-2 cursor-pointer font-mono text-xs uppercase text-gray-800">
                                            <input type="radio" value="immediate" v-model="smsForm.send_type" class="text-neon-primary focus:ring-0">
                                            Darhol
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer font-mono text-xs uppercase text-gray-800">
                                            <input type="radio" value="scheduled" v-model="smsForm.send_type" class="text-neon-primary focus:ring-0">
                                            Rejalashtirilgan
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Scheduled Date Picker -->
                            <div v-if="smsForm.send_type === 'scheduled'" class="animate-[fadeIn_0.2s_ease-out]">
                                <label class="block text-xs font-mono text-gray-500 mb-2 uppercase">Yuborilish Sanasi va Soati</label>
                                <input v-model="smsForm.scheduled_at" required type="datetime-local" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary bg-white">
                                <div v-if="smsForm.errors.scheduled_at" class="text-red-600 text-xs mt-1 font-mono">{{ smsForm.errors.scheduled_at }}</div>
                            </div>

                            <!-- Message Content -->
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-xs font-mono text-gray-500 uppercase">Xabar Matni</label>
                                    <span class="text-[10px] font-mono text-gray-400 uppercase">Belgilar: {{ smsForm.message.length }} / 1000</span>
                                </div>
                                <textarea v-model="smsForm.message" required rows="4" maxlength="1000" placeholder="SMS matnini kiriting..." class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary bg-white"></textarea>
                                <div v-if="smsForm.errors.message" class="text-red-600 text-xs mt-1 font-mono">{{ smsForm.errors.message }}</div>
                            </div>

                            <!-- Client selection list -->
                            <div>
                                <div class="flex justify-between items-center mb-3">
                                    <label class="block text-xs font-mono text-gray-500 uppercase">Mijozlar Ro'yxati ({{ selectedClients.length }} ta tanlandi)</label>
                                    <label class="flex items-center gap-2 cursor-pointer font-mono text-xs uppercase text-gray-600">
                                        <input type="checkbox" @change="selectAllClients" class="rounded border-gray-300 text-neon-primary focus:ring-0">
                                        Barchasini Tanlash
                                    </label>
                                </div>
                                
                                <div class="border border-gray-200 rounded-2xl overflow-y-auto max-h-72 divide-y divide-gray-100">
                                    <label v-for="client in clients" :key="client.id" class="flex items-center gap-3 p-3 hover:bg-gray-50 cursor-pointer transition-colors text-sm">
                                        <input type="checkbox" :value="client.id" v-model="selectedClients" class="rounded border-gray-300 text-neon-primary focus:ring-0">
                                        <span class="flex-1 font-medium text-gray-900">{{ client.first_name }} {{ client.last_name }}</span>
                                        <span class="font-mono text-xs text-gray-500">{{ client.phone }}</span>
                                    </label>
                                    <div v-if="clients.length === 0" class="p-8 text-center text-gray-500 font-mono">Mijozlar mavjud emas.</div>
                                </div>
                                <div v-if="smsForm.errors.client_ids" class="text-red-600 text-xs mt-1 font-mono">{{ smsForm.errors.client_ids }}</div>
                            </div>

                            <!-- Dispatch button -->
                            <div class="flex justify-end pt-6 border-t border-gray-200">
                                <button type="submit" :disabled="smsForm.processing" class="px-8 py-3 bg-neon-primary/20 border border-neon-primary text-neon-primary font-bold uppercase tracking-widest rounded-xl hover:shadow-sm hover:bg-neon-primary hover:text-black transition-all disabled:opacity-50 flex items-center gap-2">
                                    <span v-if="smsForm.processing" class="animate-spin">🌀</span>
                                    {{ smsForm.send_type === 'scheduled' ? 'SMSlarni Rejalashtirish' : 'SMSlarni Yuborish' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 6: USERS MANAGEMENT -->
                    <!-- ========================================== -->
                    <div v-show="activeTab === 'users_management'" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                        <h3 class="text-blue-600 font-display text-lg tracking-widest uppercase border-b border-gray-200 pb-4 mb-6">Xodimlar Boshqaruvi (Users)</h3>
                        
                        <!-- Add/Edit Form -->
                        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6 mb-6">
                            <h4 class="text-sm font-bold text-gray-900 uppercase font-mono mb-4">{{ isEditingUser ? 'Xodimni Tahrirlash' : 'Yangi Xodim Qo\'shish' }}</h4>
                            <form @submit.prevent="submitUserForm" class="space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs font-mono text-gray-500 mb-1.5 uppercase">Ism (Majburiy)</label>
                                        <input v-model="userForm.name" required type="text" placeholder="Ism..." class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary bg-white">
                                        <div v-if="userForm.errors.name" class="text-red-600 text-xs mt-1 font-mono">{{ userForm.errors.name }}</div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-mono text-gray-500 mb-1.5 uppercase">Sharif (Ixtiyoriy)</label>
                                        <input v-model="userForm.last_name" type="text" placeholder="Sharif..." class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary bg-white">
                                        <div v-if="userForm.errors.last_name" class="text-red-600 text-xs mt-1 font-mono">{{ userForm.errors.last_name }}</div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-mono text-gray-500 mb-1.5 uppercase">Otasining ismi (Ixtiyoriy)</label>
                                        <input v-model="userForm.middle_name" type="text" placeholder="Otasining ismi..." class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary bg-white">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs font-mono text-gray-500 mb-1.5 uppercase">Foydalanuvchi Nomi (Login)</label>
                                        <input v-model="userForm.username" required type="text" placeholder="Foydalanuvchi nomi..." class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary bg-white">
                                        <div v-if="userForm.errors.username" class="text-red-600 text-xs mt-1 font-mono">{{ userForm.errors.username }}</div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-mono text-gray-500 mb-1.5 uppercase">Telefon Raqam (Optional)</label>
                                        <input v-model="userForm.phone" type="text" placeholder="Telefon raqam..." class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary bg-white">
                                        <div v-if="userForm.errors.phone" class="text-red-600 text-xs mt-1 font-mono">{{ userForm.errors.phone }}</div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-mono text-gray-500 mb-1.5 uppercase">Parol (Min 6 ta belgi)</label>
                                        <input v-model="userForm.password" :required="!isEditingUser" type="password" placeholder="Parol..." class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary bg-white">
                                        <div v-if="userForm.errors.password" class="text-red-600 text-xs mt-1 font-mono">{{ userForm.errors.password }}</div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs font-mono text-gray-500 mb-1.5 uppercase">Rol (Spatie Role)</label>
                                        <select v-model="userForm.role" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary bg-white">
                                            <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
                                        </select>
                                        <div v-if="userForm.errors.role" class="text-red-600 text-xs mt-1 font-mono">{{ userForm.errors.role }}</div>
                                    </div>

                                    <div v-if="!userForm.all_objects">
                                        <label class="block text-xs font-mono text-gray-500 mb-1.5 uppercase">Bino (Obyekt) - Biriktirish</label>
                                        <select v-model="userForm.building_id" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary bg-white">
                                            <option value="">Tanlang (ixtiyoriy)</option>
                                            <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                                        </select>
                                    </div>

                                    <div v-if="!userForm.all_objects">
                                        <label class="block text-xs font-mono text-gray-500 mb-1.5 uppercase">Omborxona - Biriktirish</label>
                                        <select v-model="userForm.warehouse_id" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary bg-white">
                                            <option value="">Tanlang (ixtiyoriy)</option>
                                            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 py-2">
                                    <label class="flex items-center gap-2 cursor-pointer font-mono text-xs uppercase text-gray-700">
                                        <input type="checkbox" v-model="userForm.all_objects" class="rounded border-gray-300 text-neon-primary focus:ring-0">
                                        Barcha Obyektlarga / Omborlarga Ruxsat (Super Hisoblar Uchun)
                                    </label>
                                </div>

                                <div class="flex gap-2 justify-end pt-4 border-t border-gray-200">
                                    <button type="submit" :disabled="userForm.processing" class="px-6 py-3 bg-neon-primary/20 border border-neon-primary text-neon-primary font-bold uppercase text-xs tracking-wider rounded-xl hover:bg-neon-primary hover:text-black transition-all">
                                        {{ isEditingUser ? 'Xodimni Yangilash' : 'Xodimni Qo\'shish' }}
                                    </button>
                                    <button type="button" @click="resetUserForm" class="px-6 py-3 bg-gray-100 border border-gray-300 text-gray-700 font-bold uppercase text-xs tracking-wider rounded-xl hover:bg-gray-200 transition-all">
                                        Bekor qilish
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Users List -->
                        <div class="border border-gray-200 rounded-2xl overflow-hidden">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200 font-mono text-xs uppercase text-gray-500">
                                        <th class="p-4">Ism Sharif</th>
                                        <th class="p-4">Foydalanuvchi nomi</th>
                                        <th class="p-4">Rol</th>
                                        <th class="p-4">Ruxsat (Obyekt/Ombor)</th>
                                        <th class="p-4 text-right">Amallar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-sm">
                                    <tr v-for="usr in users" :key="usr.id" class="hover:bg-gray-50/50 transition-colors">
                                        <td class="p-4 font-semibold text-gray-900">
                                            {{ usr.name }} {{ usr.last_name || '' }}
                                        </td>
                                        <td class="p-4 font-mono text-xs text-gray-600">{{ usr.username || 'Email: ' + usr.email }}</td>
                                        <td class="p-4">
                                            <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-blue-50 text-blue-600 font-mono uppercase">
                                                {{ usr.roles && usr.roles.length > 0 ? usr.roles[0].name : 'Noma\'lum' }}
                                            </span>
                                        </td>
                                        <td class="p-4 font-mono text-xs text-gray-500">
                                            <span v-if="usr.building">{{ usr.building.name }} (Bino)</span>
                                            <span v-else-if="usr.warehouse">{{ usr.warehouse.name }} (Ombor)</span>
                                            <span v-else class="text-emerald-600 font-semibold">Barcha obyektlar (Cheklovsiz)</span>
                                        </td>
                                        <td class="p-4 text-right flex justify-end gap-2">
                                            <button @click="editUser(usr)" class="p-2 border border-blue-200 text-blue-600 rounded-lg hover:bg-blue-50 transition-all text-xs uppercase font-mono">
                                                Tahrirlash
                                            </button>
                                            <button @click="deleteUser(usr.id)" class="p-2 border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition-all text-xs uppercase font-mono">
                                                O'chirish
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 7: ROLES & PERMISSIONS -->
                    <!-- ========================================== -->
                    <div v-show="activeTab === 'roles_permissions'" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                        <h3 class="text-blue-600 font-display text-lg tracking-widest uppercase border-b border-gray-200 pb-4 mb-6">Rollar va Modul Ruxsatlari (Permissions Matrix)</h3>
                        
                        <div class="space-y-6">
                            <!-- Role selector -->
                            <div>
                                <label class="block text-xs font-mono text-gray-500 mb-2 uppercase">Ruxsatini sozlash uchun Rolni tanlang</label>
                                <select v-model="selectedRoleIdForPermissions" @change="loadRolePermissions" class="w-full border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary bg-white">
                                    <option value="">Rollar ro'yxati...</option>
                                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                                </select>
                            </div>

                            <!-- Permissions Checkboxes Matrix -->
                            <div v-if="selectedRoleIdForPermissions" class="bg-gray-50 border border-gray-200 rounded-2xl p-6 space-y-4">
                                <h4 class="text-sm font-bold text-gray-900 uppercase font-mono border-b border-gray-200 pb-2">
                                    Ruxsat berilgan modullar ro'yxati
                                </h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <label v-for="perm in permissions" :key="perm.id" class="flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition-all">
                                        <input type="checkbox" :value="perm.name" v-model="rolePermissionsForm.permissions" class="rounded border-gray-300 text-neon-primary focus:ring-0">
                                        <div class="flex-1">
                                            <p class="text-sm font-semibold text-gray-800">{{ permissionLabel(perm.name) }}</p>
                                            <p class="text-[10px] font-mono text-gray-400 uppercase mt-0.5">Permission: {{ perm.name }}</p>
                                        </div>
                                    </label>
                                </div>

                                <div class="flex justify-end pt-4 border-t border-gray-200">
                                    <button type="button" @click="saveRolePermissions" :disabled="rolePermissionsForm.processing" class="px-8 py-3 bg-neon-primary/20 border border-neon-primary text-neon-primary font-bold uppercase text-xs tracking-wider rounded-xl hover:bg-neon-primary hover:text-black transition-all">
                                        Ruxsatlarni Saqlash
                                    </button>
                                </div>
                            </div>
                            
                            <div v-else class="p-8 text-center border border-dashed border-gray-300 rounded-2xl text-gray-500 font-mono">
                                Iltimos, modullarga ruxsatlarni boshqarish uchun yuqoridan rolni tanlang.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            
        </div>
    </AdminLayout>
</template>
