<script setup>
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    settings: Object
});

const page = usePage();
const user = page.props.auth.user;

const activeTab = ref('branding');

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

const profileForm = useForm({
    name: user.name || '',
    email: user.email || '',
    phone: user.phone || '',
    password: '',
    language: user.language || 'uz',
    theme_color: user.theme_color || '#00F0FF',
    theme_style: user.theme_style || 'apple_glass',
    label_font_size: user.label_font_size || 12,
    label_color: user.label_color || '#CCCCCC',
    dropdown_bg_color: user.dropdown_bg_color || '#111111',
    dropdown_text_color: user.dropdown_text_color || '#ffffff',
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
    document.documentElement.style.setProperty('--shadow-neon-primary', `0 0 15px rgba(${hexToRgb(color)}, 0.3)`);
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
    // Debounced submit would be better but let's stick to the current pattern
    submitProfile();
};
</script>

<template>
    <Head title="System Settings" />

    <AdminLayout>
        <div class="mb-8 flex items-center gap-4 hidden">
            <!-- Global header is used instead -->
        </div>

        <div class="max-w-7xl mx-auto flex flex-col md:flex-row gap-8 animate-[fadeIn_0.5s_ease-out]">
            
            <!-- Sidebar Tabs -->
            <div class="w-full md:w-72 space-y-3 shrink-0">
                <button @click="activeTab = 'branding'" :class="activeTab === 'branding' ? 'bg-neon-primary/20 border-neon-primary text-neon-primary shadow-[0_0_15px_rgba(var(--color-neon-primary-rgb),0.3)]' : 'bg-black/40 border-white/10 hover:border-white/30 text-white/70'" class="w-full text-left px-5 py-5 rounded-2xl border transition-all font-display text-sm uppercase tracking-wider flex items-center gap-4">
                    <span class="text-2xl drop-shadow">🎨</span> Brending va Tashqi Ko'rinish
                </button>
                
                <button @click="activeTab = 'financial'" :class="activeTab === 'financial' ? 'bg-neon-primary/20 border-neon-primary text-neon-primary shadow-[0_0_15px_rgba(var(--color-neon-primary-rgb),0.3)]' : 'bg-black/40 border-white/10 hover:border-white/30 text-white/70'" class="w-full text-left px-5 py-5 rounded-2xl border transition-all font-display text-sm uppercase tracking-wider flex items-center gap-4">
                    <span class="text-2xl drop-shadow">🏦</span> Financial Defaults
                </button>

                <button @click="activeTab = 'profile'" :class="activeTab === 'profile' ? 'bg-neon-primary/20 border-neon-primary text-neon-primary shadow-[0_0_15px_rgba(var(--color-neon-primary-rgb),0.3)]' : 'bg-black/40 border-white/10 hover:border-white/30 text-white/70'" class="w-full text-left px-5 py-5 rounded-2xl border transition-all font-display text-sm uppercase tracking-wider flex items-center gap-4">
                    <span class="text-2xl drop-shadow group-hover:scale-110 transition-transform">👤</span> Profil va Dizayn
                </button>

                <button disabled class="w-full text-left px-5 py-5 rounded-2xl border bg-black/20 border-white/5 text-white/30 transition-all font-display text-sm uppercase tracking-wider flex items-center gap-4 cursor-not-allowed">
                    <span class="text-2xl opacity-50">⏳</span> Yangi blok tez orada..!
                </button>
            </div>

            <!-- Content Area -->
            <div class="flex-1">
                <div class="glass-panel p-6 md:p-4 md:p-8 rounded-2xl border border-white/5 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-neon-primary/10 blur-[100px] group-hover:bg-neon-primary/20 transition-all pointer-events-none"></div>

                    <form @submit.prevent="submit">
                        
                        <!-- Branding Tab -->
                        <div v-show="activeTab === 'branding'" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                            <h3 class="text-neon-cyan font-display text-xl tracking-widest uppercase border-b border-white/10 pb-4 mb-6">Brending va Tashqi Ko'rinish</h3>
                            
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-xs font-mono text-text-muted mb-2 uppercase">Company Name</label>
                                    <input v-model="form.company_name" type="text" class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-mono text-text-muted mb-2 uppercase">Primary Theme Color</label>
                                    <div class="flex items-center gap-4 bg-black/40 border border-white/10 rounded-xl p-2 transition-all focus-within:border-neon-primary">
                                        <input v-model="form.primary_theme_color" type="color" class="w-10 h-10 rounded cursor-pointer bg-transparent border-0 p-0">
                                        <input v-model="form.primary_theme_color" type="text" class="bg-transparent border-0 text-white flex-1 font-mono focus:ring-0">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-mono text-text-muted mb-2 uppercase">{{ $t('custom_logo_desc') }}</label>
                                    <input type="file" @input="form.logo = $event.target.files[0]" class="w-full bg-black/50 border border-white/10 rounded-xl p-2 text-white font-mono text-xs text-text-muted file:bg-white/10 file:border-0 file:rounded file:text-white file:px-3 file:py-1 file:mr-3 hover:file:bg-white/20" accept="image/*">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-mono text-text-muted mb-2 uppercase">Tizim Orqa Foni (Background Image)</label>
                                    <input type="file" @input="form.background_image = $event.target.files[0]" class="w-full bg-black/50 border border-white/10 rounded-xl p-2 text-white font-mono text-xs text-text-muted file:bg-white/10 file:border-0 file:rounded file:text-white file:px-3 file:py-1 file:mr-3 hover:file:bg-white/20" accept="image/*">
                                    <div v-if="$page.props.branding?.background_image_path" class="mt-3 relative w-full h-32 rounded-xl overflow-hidden border border-white/20">
                                        <img :src="'/storage/' + $page.props.branding.background_image_path" class="w-full h-full object-cover">
                                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                                            <span class="text-xs text-white font-mono uppercase tracking-widest">Joriy Orqa Fon</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end pt-8 border-t border-white/10">
                                <button type="submit" :disabled="form.processing" class="px-8 py-3 bg-neon-primary/20 border border-neon-primary text-neon-primary font-bold uppercase tracking-widest rounded-xl hover:shadow-[0_0_20px_rgba(var(--color-neon-primary-rgb),0.5)] hover:bg-neon-primary hover:text-black transition-all disabled:opacity-50 flex items-center gap-2">
                                    <span v-if="form.processing" class="animate-spin">🌀</span>{{ $t('save') }}</button>
                            </div>
                        </div>

                        <!-- Financials Tab -->
                        <div v-show="activeTab === 'financial'" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                            <h3 class="text-neon-cyan font-display text-xl tracking-widest uppercase border-b border-white/10 pb-4 mb-6">{{ $t('financial_defaults') }}</h3>
                            
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-xs font-mono text-text-muted mb-2 uppercase">Default Currency</label>
                                    <select v-model="form.default_currency" class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                                        <option value="USD" class="bg-black text-white">USD ($)</option>
                                        <option value="UZS" class="bg-black text-white">UZS (So'm)</option>
                                        <option value="RUB" class="bg-black text-white">RUB (₽)</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-mono text-text-muted mb-2 uppercase">Global Tax Rate (%)</label>
                                    <input v-model="form.tax_rate" type="number" step="0.01" class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-neon-green font-bold text-lg focus:border-neon-green focus:ring-1 focus:ring-neon-green transition-all">
                                </div>

                                <div>
                                    <label class="block text-xs font-mono text-text-muted mb-2 uppercase">Default Daily Penalty Rate (%)</label>
                                    <input v-model="form.default_penalty_rate" type="number" step="0.01" class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-neon-red font-bold text-lg focus:border-neon-red focus:ring-1 focus:ring-neon-red transition-all">
                                </div>
                            </div>
                            
                            <div class="flex justify-end pt-8 border-t border-white/10 mt-6">
                                <button type="submit" :disabled="form.processing" class="px-8 py-3 bg-neon-primary/20 border border-neon-primary text-neon-primary font-bold uppercase tracking-widest rounded-xl hover:shadow-[0_0_20px_rgba(var(--color-neon-primary-rgb),0.5)] hover:bg-neon-primary hover:text-black transition-all disabled:opacity-50 flex items-center gap-2">
                                    <span v-if="form.processing" class="animate-spin">🌀</span>{{ $t('save') }}</button>
                            </div>
                        </div>

                    </form>

                    <form @submit.prevent="submitProfile" v-show="activeTab === 'profile'" class="space-y-6 animate-[fadeIn_0.3s_ease-out]">
                        <h3 class="text-neon-cyan font-display text-xl tracking-widest uppercase border-b border-white/10 pb-4 mb-6">{{ $t('personal_info') || 'Profil va Dizayn' }}</h3>
                        
                        <div class="space-y-5">
                            <div class="flex items-center gap-6 mb-6">
                                <div class="w-20 h-20 rounded-2xl bg-black/50 border border-white/10 flex items-center justify-center overflow-hidden shrink-0">
                                    <img v-if="user.photo_path" :src="'/storage/' + user.photo_path" class="w-full h-full object-cover" />
                                    <span v-else class="text-3xl">👤</span>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-xs font-mono text-text-muted uppercase mb-2">{{ $t('upload_avatar') }}</label>
                                    <input type="file" @change="e => profileForm.avatar = e.target.files[0]" class="w-full bg-black/50 border border-white/10 rounded-xl p-2 text-white font-mono text-xs text-text-muted file:bg-white/10 file:border-0 file:rounded file:text-white file:px-3 file:py-1 file:mr-3 hover:file:bg-white/20">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-mono text-text-muted uppercase mb-1">{{ $t('full_name_abbr') }}</label>
                                <input type="text" v-model="profileForm.name" required class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                                <div v-if="profileForm.errors.name" class="text-neon-red text-xs mt-1">{{ profileForm.errors.name }}</div>
                            </div>

                            <div>
                                <label class="block text-xs font-mono text-text-muted uppercase mb-1">Email</label>
                                <input type="email" v-model="profileForm.email" required class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                                <div v-if="profileForm.errors.email" class="text-neon-red text-xs mt-1">{{ profileForm.errors.email }}</div>
                            </div>

                            <div>
                                <label class="block text-xs font-mono text-text-muted uppercase mb-1">Telefon</label>
                                <input type="text" v-model="profileForm.phone" class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                                <div v-if="profileForm.errors.phone" class="text-neon-red text-xs mt-1">{{ profileForm.errors.phone }}</div>
                            </div>

                            <div>
                                <label class="block text-xs font-mono text-text-muted uppercase mb-1">Yangi parol (ixtiyoriy)</label>
                                <input type="password" v-model="profileForm.password" placeholder="••••••••" class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                                <div v-if="profileForm.errors.password" class="text-neon-red text-xs mt-1">{{ profileForm.errors.password }}</div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-8 border-t border-white/10 mt-6">
                            <button type="submit" :disabled="profileForm.processing" class="px-8 py-3 bg-neon-primary/20 border border-neon-primary text-neon-primary font-bold uppercase tracking-widest rounded-xl hover:shadow-[0_0_20px_rgba(var(--color-neon-primary-rgb),0.5)] hover:bg-neon-primary hover:text-black transition-all disabled:opacity-50 flex items-center gap-2">
                                <span v-if="profileForm.processing" class="animate-spin">🌀</span>{{ $t('save') }}</button>
                        </div>
                    </form>

                    <!-- Dizayn va Til -->
                    <div v-show="activeTab === 'profile'" class="space-y-8 mt-12 animate-[fadeIn_0.5s_ease-out]">
                        <!-- Global Mavzu (Theme Style) -->
                        <div class="glass-panel p-6 md:p-4 md:p-8 rounded-2xl border border-white/5 relative overflow-hidden group">
                            <h2 class="text-xl font-display font-bold text-white mb-6">{{ $t('global_design_theme') }}</h2>
                            <div class="grid grid-cols-2 md:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <button type="button" @click="updateThemeStyle('apple_glass')" class="p-4 rounded-xl border flex flex-col items-center gap-3 transition-all relative overflow-hidden" :class="profileForm.theme_style === 'apple_glass' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary-rgb),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'">
                                    <span class="text-3xl">🍏</span>
                                    <span class="font-mono text-xs uppercase text-center" :class="profileForm.theme_style === 'apple_glass' ? 'text-neon-primary font-bold' : 'text-text-muted'">Apple Glass</span>
                                </button>
                                <button type="button" @click="updateThemeStyle('galaxy')" class="p-4 rounded-xl border flex flex-col items-center gap-3 transition-all relative overflow-hidden" :class="profileForm.theme_style === 'galaxy' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary-rgb),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'">
                                    <span class="text-3xl">🌌</span>
                                    <span class="font-mono text-xs uppercase text-center" :class="profileForm.theme_style === 'galaxy' ? 'text-neon-primary font-bold' : 'text-text-muted'">Sher-Dor Galaxy</span>
                                </button>
                                <button type="button" @click="updateThemeStyle('cyberpunk')" class="p-4 rounded-xl border flex flex-col items-center gap-3 transition-all relative overflow-hidden" :class="profileForm.theme_style === 'cyberpunk' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary-rgb),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'">
                                    <span class="text-3xl">🤖</span>
                                    <span class="font-mono text-xs uppercase text-center" :class="profileForm.theme_style === 'cyberpunk' ? 'text-neon-primary font-bold' : 'text-text-muted'">Neon Cyberpunk</span>
                                </button>
                                <button type="button" @click="updateThemeStyle('minimalist')" class="p-4 rounded-xl border flex flex-col items-center gap-3 transition-all relative overflow-hidden" :class="profileForm.theme_style === 'minimalist' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary-rgb),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'">
                                    <span class="text-3xl">⬛</span>
                                    <span class="font-mono text-xs uppercase text-center" :class="profileForm.theme_style === 'minimalist' ? 'text-neon-primary font-bold' : 'text-text-muted'">Minimalist</span>
                                </button>
                                <button type="button" @click="updateThemeStyle('gold')" class="p-4 rounded-xl border flex flex-col items-center gap-3 transition-all relative overflow-hidden" :class="profileForm.theme_style === 'gold' ? 'border-[#FFD700] bg-[#FFD700]/10 shadow-[0_0_15px_rgba(255,215,0,0.3)]' : 'border-white/10 hover:border-[#FFD700]/50 bg-black/40'">
                                    <span class="text-3xl text-shadow-glow shadow-[#FFD700]">⚜️</span>
                                    <span class="font-mono text-xs uppercase text-center" :class="profileForm.theme_style === 'gold' ? 'text-[#FFD700] font-bold' : 'text-text-muted'">Gold Royal</span>
                                </button>
                                <button type="button" @click="updateThemeStyle('itcloud_cyberglass')" class="p-4 rounded-xl border flex flex-col items-center gap-3 transition-all relative overflow-hidden" :class="profileForm.theme_style === 'itcloud_cyberglass' ? 'border-[#00F0FF] bg-[#00F0FF]/10 shadow-[0_0_20px_rgba(0,240,255,0.4)]' : 'border-white/10 hover:border-[#00F0FF]/50 bg-black/40'">
                                    <span class="text-3xl">🌐</span>
                                    <span class="font-mono text-xs uppercase text-center" :class="profileForm.theme_style === 'itcloud_cyberglass' ? 'text-[#00F0FF] font-bold' : 'text-text-muted'">ITCloud Cyberglass</span>
                                </button>
                            </div>
                        </div>

                        <!-- Label Customization -->
                        <div class="glass-panel p-6 md:p-4 md:p-8 rounded-2xl border border-white/5 relative overflow-hidden group">
                            <h2 class="text-xl font-display font-bold text-white mb-2">Yozuvlar (Label) Sozlamalari</h2>
                            <p class="text-[10px] font-mono text-text-muted mb-6 uppercase">Ochildigan oynalar va formadagi matnlarni ko'rinishini sozlang</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <label class="block text-xs font-mono text-text-muted mb-2 uppercase">Shrift Kattaligi (PX)</label>
                                    <div class="flex items-center gap-4">
                                        <input v-model="profileForm.label_font_size" type="range" min="8" max="24" step="1" @change="updateLabelSettings" class="flex-1 accent-neon-primary h-1 bg-white/10 rounded-lg appearance-none cursor-pointer">
                                        <span class="w-12 text-center font-mono text-lg text-white font-bold border border-white/10 rounded bg-black/40">{{ profileForm.label_font_size }}</span>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-mono text-text-muted mb-2 uppercase">Matn Rangi (Yorqinlik)</label>
                                    <div class="flex items-center gap-4 bg-black/40 border border-white/10 rounded-xl p-2 transition-all focus-within:border-neon-primary">
                                        <input v-model="profileForm.label_color" type="color" @change="updateLabelSettings" class="w-10 h-10 rounded cursor-pointer bg-transparent border-0 p-0">
                                        <input v-model="profileForm.label_color" type="text" @change="updateLabelSettings" class="bg-transparent border-0 text-white flex-1 font-mono focus:ring-0 text-sm">
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-6">
                                <div>
                                    <label class="block text-xs font-mono text-text-muted mb-2 uppercase">Ro'yxat orqa foni (Dropdown BG)</label>
                                    <div class="flex items-center gap-4 bg-black/40 border border-white/10 rounded-xl p-2 transition-all focus-within:border-neon-primary">
                                        <input v-model="profileForm.dropdown_bg_color" type="color" @change="updateLabelSettings" class="w-10 h-10 rounded cursor-pointer bg-transparent border-0 p-0">
                                        <input v-model="profileForm.dropdown_bg_color" type="text" @change="updateLabelSettings" class="bg-transparent border-0 text-white flex-1 font-mono focus:ring-0 text-sm">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-mono text-text-muted mb-2 uppercase">Ro'yxat matn rangi (Dropdown Text)</label>
                                    <div class="flex items-center gap-4 bg-black/40 border border-white/10 rounded-xl p-2 transition-all focus-within:border-neon-primary">
                                        <input v-model="profileForm.dropdown_text_color" type="color" @change="updateLabelSettings" class="w-10 h-10 rounded cursor-pointer bg-transparent border-0 p-0">
                                        <input v-model="profileForm.dropdown_text_color" type="text" @change="updateLabelSettings" class="bg-transparent border-0 text-white flex-1 font-mono focus:ring-0 text-sm">
                                    </div>
                                </div>
                            </div>

                            <!-- Preview -->
                            <div class="mt-8 p-6 border border-dashed border-white/20 rounded-xl bg-black/20">
                                <p class="text-[10px] font-mono text-text-muted mb-4 italic uppercase">Jonli Ko'rinish (Preview):</p>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block mb-1 uppercase" :style="{ fontSize: profileForm.label_font_size + 'px', color: profileForm.label_color }">RO'YXATDAN O'TGAN SANA (IXTIYORIY)</label>
                                        <div class="w-full h-10 bg-black/50 border border-white/10 rounded-lg flex items-center px-4 text-xs text-text-muted font-mono">kk/oo/yyyy</div>
                                    </div>
                                    <div>
                                        <label class="block mb-1 uppercase" :style="{ fontSize: profileForm.label_font_size + 'px', color: profileForm.label_color }">ISM</label>
                                        <div class="w-full h-10 bg-black/50 border border-white/10 rounded-lg"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dashboard Rangi -->
                        <div class="glass-panel p-6 md:p-4 md:p-8 rounded-2xl border border-white/5 relative overflow-hidden group h-min">
                            <h2 class="text-xl font-display font-bold text-white mb-6">{{ $t('dashboard_theme') || "Dashboard Rangi" }}</h2>
                            
                            <div class="grid grid-cols-4 sm:grid-cols-8 gap-3">
                                <button 
                                    v-for="color in themeColors" :key="color"
                                    @click="updateTheme(color)"
                                    type="button"
                                    class="w-10 h-10 rounded-xl transition-all duration-300 relative border-2 flex items-center justify-center"
                                    :class="profileForm.theme_color === color ? 'border-white scale-110 shadow-[0_0_15px_currentColor]' : 'border-transparent hover:scale-105 hover:border-white/50'"
                                    :style="{ backgroundColor: color, color: color }"
                                >
                                    <svg v-if="profileForm.theme_color === color" class="w-5 h-5 text-black drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Interfeys Tili -->
                        <div class="glass-panel p-6 md:p-4 md:p-8 rounded-2xl border border-white/5 h-min">
                            <h2 class="text-xl font-display font-bold text-white mb-6">{{ $t('interface_language') || "Interfeys Tili" }}</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <button 
                                    type="button"
                                    @click="updateLanguage('uz')"
                                    class="p-4 rounded-xl border flex flex-col items-center gap-2 transition-all"
                                    :class="profileForm.language === 'uz' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'"
                                >
                                    <span class="text-2xl">🇺🇿</span>
                                    <span class="font-mono text-xs uppercase" :class="profileForm.language === 'uz' ? 'text-neon-primary font-bold' : 'text-text-muted'">{{ $t('uzbek') }}</span>
                                </button>
                                
                                <button 
                                    type="button"
                                    @click="updateLanguage('ru')"
                                    class="p-4 rounded-xl border flex flex-col items-center gap-2 transition-all"
                                    :class="profileForm.language === 'ru' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'"
                                >
                                    <span class="text-2xl">🇷🇺</span>
                                    <span class="font-mono text-xs uppercase" :class="profileForm.language === 'ru' ? 'text-neon-primary font-bold' : 'text-text-muted'">{{ $t('russian') }}</span>
                                </button>

                                <button 
                                    type="button"
                                    @click="updateLanguage('en')"
                                    class="p-4 rounded-xl border flex flex-col items-center gap-2 transition-all"
                                    :class="profileForm.language === 'en' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'"
                                >
                                    <span class="text-2xl">🇬🇧</span>
                                    <span class="font-mono text-xs uppercase" :class="profileForm.language === 'en' ? 'text-neon-primary font-bold' : 'text-text-muted'">{{ $t('english') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            
        </div>
    </AdminLayout>
</template>
