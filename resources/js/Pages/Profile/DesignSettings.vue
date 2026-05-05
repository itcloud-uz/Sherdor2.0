<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, usePage, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import OperatorLayout from '@/Layouts/OperatorLayout.vue';
import PartnerLayout from '@/Layouts/PartnerLayout.vue';

const page = usePage();
const user = page.props.auth.user;
const role = page.props.role || 'admin';
const context = page.props.context || 'admin';
const { locale } = useI18n();

const CurrentLayout = computed(() => {
    if (context === 'operator') return OperatorLayout;
    if (context === 'partner') return PartnerLayout;
    return AdminLayout;
});

const backRoute = computed(() => {
    if (context === 'operator') return route('operator');
    if (context === 'partner') return route('partner');
    return route('admin.dashboard');
});

const form = useForm({
    name: user.name || '',
    email: user.email || '',
    phone: user.phone || '',
    password: '',
    language: user.language || 'uz',
    theme_color: user.theme_color || '#00F0FF',
    theme_style: user.theme_style || 'apple_glass',
    avatar: null,
});

const themeColors = [
    '#FFD700', // Sherdor Gold (Firm Design)
    '#00F0FF', '#B026FF', '#39FF14', '#FF003C', '#FCee09', 
    '#9D00FF', '#00FF9D', '#FF9D00', '#FF009D', '#009DFF',
    '#FFFFFF', '#888888', '#FF5555', '#55FF55', '#5555FF'
];

const submit = () => {
    form.post(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.password = '';
        },
    });
};

const updateTheme = (color) => {
    form.theme_color = color;
    document.documentElement.style.setProperty('--color-neon-primary', color);
    const hexToRgb = (hex) => {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` : '0, 240, 255';
    };
    document.documentElement.style.setProperty('--shadow-neon-primary', `0 0 15px rgba(${hexToRgb(color)}, 0.3)`);
    submit();
};

const updateLanguage = (lang) => {
    form.language = lang;
    locale.value = lang;
    localStorage.setItem('locale', lang);
    document.documentElement.lang = lang;
    submit();
};

const updateThemeStyle = (style) => {
    form.theme_style = style;
    submit();
    setTimeout(() => {
        window.location.reload(); // Reload to apply background theme completely
    }, 500);
};
</script>

<template>
    <Head :title="$t('settings')" />

    <component :is="CurrentLayout">
        <div class="mb-8 flex items-center gap-4">
            <Link :href="backRoute" class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center hover:bg-white/20 transition-all text-white/70 hover:text-white shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </Link>
            <h1 class="text-2xl md:text-3xl font-display font-bold text-white tracking-widest uppercase shadow-neon-primary/50 text-shadow-glow flex items-center gap-3">
                <span class="text-3xl drop-shadow-[0_0_15px_var(--color-neon-primary)]">⚙️</span> {{ $t('profile_and_design') }}
            </h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 pb-24 md:pb-0">
            
            <!-- Shaxsiy ma'lumotlar -->
            <div class="glass-panel p-6 md:p-8 rounded-2xl border border-white/5 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-32 h-32 bg-neon-primary/10 blur-[50px] group-hover:bg-neon-primary/20 transition-all pointer-events-none"></div>
                
                <h2 class="text-xl font-display font-bold text-white mb-6">{{ $t('personal_info') }}</h2>
                
                <form @submit.prevent="submit" class="space-y-5">
                    
                    <div class="flex items-center gap-6 mb-6">
                        <div class="w-20 h-20 rounded-2xl bg-black/50 border border-white/10 flex items-center justify-center overflow-hidden shrink-0">
                            <img v-if="user.photo_path" :src="'/storage/' + user.photo_path" class="w-full h-full object-cover" />
                            <span v-else class="text-3xl">👤</span>
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-mono text-text-muted uppercase mb-2">{{ $t('avatar_upload') }}</label>
                            <input type="file" @change="e => form.avatar = e.target.files[0]" class="w-full bg-black/50 border border-white/10 rounded-xl p-2 text-white font-mono text-xs text-text-muted file:bg-white/10 file:border-0 file:rounded file:text-white file:px-3 file:py-1 file:mr-3 hover:file:bg-white/20">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-text-muted uppercase mb-1">{{ $t('full_name') }}</label>
                        <input type="text" v-model="form.name" required class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                        <div v-if="form.errors.name" class="text-neon-red text-xs mt-1">{{ form.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-text-muted uppercase mb-1">{{ $t('email') }}</label>
                        <input type="email" v-model="form.email" required class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                        <div v-if="form.errors.email" class="text-neon-red text-xs mt-1">{{ form.errors.email }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-text-muted uppercase mb-1">{{ $t('phone_number') }}</label>
                        <input type="text" v-model="form.phone" class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                        <div v-if="form.errors.phone" class="text-neon-red text-xs mt-1">{{ form.errors.phone }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-text-muted uppercase mb-1">{{ $t('new_password_optional') }}</label>
                        <input type="password" v-model="form.password" placeholder="••••••••" class="w-full bg-black/40 border border-white/10 rounded-xl p-3 text-white font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary transition-all">
                        <div v-if="form.errors.password" class="text-neon-red text-xs mt-1">{{ form.errors.password }}</div>
                    </div>

                    <button type="submit" :disabled="form.processing" class="w-full mt-6 bg-neon-primary/20 border border-neon-primary text-neon-primary py-4 rounded-xl font-display font-bold tracking-widest uppercase hover:bg-neon-primary hover:text-black hover:shadow-[0_0_20px_var(--color-neon-primary)] transition-all">
                        {{ $t('save') }}
                    </button>
                    
                    <Link :href="route('logout')" method="post" as="button" class="w-full mt-4 bg-neon-red/10 border border-neon-red/50 text-neon-red py-4 rounded-xl font-display font-bold tracking-widest uppercase hover:bg-neon-red hover:text-white hover:shadow-[0_0_20px_rgba(255,0,0,0.5)] transition-all flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                        Tizimdan chiqish
                    </Link>
                </form>
            </div>

            <!-- Dizayn va Til -->
            <div class="space-y-8">
                <!-- Global Mavzu (Theme Style) -->
                <div class="glass-panel p-6 md:p-8 rounded-2xl border border-white/5 relative overflow-hidden group">
                    <h2 class="text-xl font-display font-bold text-white mb-6">Global Dizayn Mavzusi</h2>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <button type="button" @click="updateThemeStyle('apple_glass')" class="p-4 rounded-xl border flex flex-col items-center gap-3 transition-all relative overflow-hidden" :class="form.theme_style === 'apple_glass' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary-rgb),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'">
                            <span class="text-3xl">🍏</span>
                            <span class="font-mono text-xs uppercase text-center" :class="form.theme_style === 'apple_glass' ? 'text-neon-primary font-bold' : 'text-text-muted'">Apple Glass</span>
                        </button>
                        <button type="button" @click="updateThemeStyle('galaxy')" class="p-4 rounded-xl border flex flex-col items-center gap-3 transition-all relative overflow-hidden" :class="form.theme_style === 'galaxy' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary-rgb),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'">
                            <span class="text-3xl">🌌</span>
                            <span class="font-mono text-xs uppercase text-center" :class="form.theme_style === 'galaxy' ? 'text-neon-primary font-bold' : 'text-text-muted'">Sher-Dor Galaxy</span>
                        </button>
                        <button type="button" @click="updateThemeStyle('cyberpunk')" class="p-4 rounded-xl border flex flex-col items-center gap-3 transition-all relative overflow-hidden" :class="form.theme_style === 'cyberpunk' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary-rgb),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'">
                            <span class="text-3xl">🤖</span>
                            <span class="font-mono text-xs uppercase text-center" :class="form.theme_style === 'cyberpunk' ? 'text-neon-primary font-bold' : 'text-text-muted'">Neon Cyberpunk</span>
                        </button>
                        <button type="button" @click="updateThemeStyle('minimalist')" class="p-4 rounded-xl border flex flex-col items-center gap-3 transition-all relative overflow-hidden" :class="form.theme_style === 'minimalist' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary-rgb),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'">
                            <span class="text-3xl">⬛</span>
                            <span class="font-mono text-xs uppercase text-center" :class="form.theme_style === 'minimalist' ? 'text-neon-primary font-bold' : 'text-text-muted'">Minimalist</span>
                        </button>
                        <button type="button" @click="updateThemeStyle('gold')" class="p-4 rounded-xl border flex flex-col items-center gap-3 transition-all relative overflow-hidden" :class="form.theme_style === 'gold' ? 'border-[#FFD700] bg-[#FFD700]/10 shadow-[0_0_15px_rgba(255,215,0,0.3)]' : 'border-white/10 hover:border-[#FFD700]/50 bg-black/40'">
                            <span class="text-3xl text-shadow-glow shadow-[#FFD700]">⚜️</span>
                            <span class="font-mono text-xs uppercase text-center" :class="form.theme_style === 'gold' ? 'text-[#FFD700] font-bold' : 'text-text-muted'">Gold Royal</span>
                        </button>
                        <button type="button" @click="updateThemeStyle('itcloud_cyberglass')" class="p-4 rounded-xl border flex flex-col items-center gap-3 transition-all relative overflow-hidden" :class="form.theme_style === 'itcloud_cyberglass' ? 'border-[#00F0FF] bg-[#00F0FF]/10 shadow-[0_0_20px_rgba(0,240,255,0.4)]' : 'border-white/10 hover:border-[#00F0FF]/50 bg-black/40'">
                            <span class="text-3xl">🌐</span>
                            <span class="font-mono text-xs uppercase text-center" :class="form.theme_style === 'itcloud_cyberglass' ? 'text-[#00F0FF] font-bold' : 'text-text-muted'">ITCloud Cyberglass</span>
                        </button>
                    </div>
                </div>

                <!-- Dashboard Rangi -->
                <div class="glass-panel p-6 md:p-8 rounded-2xl border border-white/5 relative overflow-hidden group h-min">
                    <h2 class="text-xl font-display font-bold text-white mb-6">{{ $t('dashboard_theme') }}</h2>
                    <p class="text-xs text-text-muted mb-4 font-mono">{{ $t('choose_theme_color') }}</p>
                    
                    <div class="grid grid-cols-4 sm:grid-cols-8 gap-3">
                        <button 
                            v-for="color in themeColors" :key="color"
                            @click="updateTheme(color)"
                            type="button"
                            class="w-10 h-10 rounded-xl transition-all duration-300 relative border-2 flex items-center justify-center"
                            :class="form.theme_color === color ? 'border-white scale-110 shadow-[0_0_15px_currentColor]' : 'border-transparent hover:scale-105 hover:border-white/50'"
                            :style="{ backgroundColor: color, color: color }"
                        >
                            <svg v-if="form.theme_color === color" class="w-5 h-5 text-black drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Interfeys Tili -->
                <div class="glass-panel p-6 md:p-8 rounded-2xl border border-white/5 h-min">
                    <h2 class="text-xl font-display font-bold text-white mb-6">{{ $t('interface_language') }}</h2>
                    <div class="grid grid-cols-3 gap-4">
                        <button 
                            type="button"
                            @click="updateLanguage('uz')"
                            class="p-4 rounded-xl border flex flex-col items-center gap-2 transition-all"
                            :class="form.language === 'uz' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'"
                        >
                            <span class="text-2xl">🇺🇿</span>
                            <span class="font-mono text-xs uppercase" :class="form.language === 'uz' ? 'text-neon-primary font-bold' : 'text-text-muted'">O'zbek</span>
                        </button>
                        
                        <button 
                            type="button"
                            @click="updateLanguage('ru')"
                            class="p-4 rounded-xl border flex flex-col items-center gap-2 transition-all"
                            :class="form.language === 'ru' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'"
                        >
                            <span class="text-2xl">🇷🇺</span>
                            <span class="font-mono text-xs uppercase" :class="form.language === 'ru' ? 'text-neon-primary font-bold' : 'text-text-muted'">Русский</span>
                        </button>

                        <button 
                            type="button"
                            @click="updateLanguage('en')"
                            class="p-4 rounded-xl border flex flex-col items-center gap-2 transition-all"
                            :class="form.language === 'en' ? 'border-neon-primary bg-neon-primary/10 shadow-[0_0_15px_rgba(var(--color-neon-primary),0.3)]' : 'border-white/10 hover:border-white/30 bg-black/40'"
                        >
                            <span class="text-2xl">🇬🇧</span>
                            <span class="font-mono text-xs uppercase" :class="form.language === 'en' ? 'text-neon-primary font-bold' : 'text-text-muted'">English</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </component>
</template>
