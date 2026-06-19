<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import GalaxyBackground from '@/Components/GalaxyBackground.vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const deferredPrompt = ref(null);
const showInstallButton = ref(false);
const showPassword = ref(false);

onMounted(() => {
    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent the mini-infobar from appearing on mobile
        e.preventDefault();
        // Stash the event so it can be triggered later.
        deferredPrompt.value = e;
        // Update UI notify the user they can install the PWA
        showInstallButton.value = true;
    });
});

const installPWA = async () => {
    if (deferredPrompt.value) {
        deferredPrompt.value.prompt();
        const { outcome } = await deferredPrompt.value.userChoice;
        if (outcome === 'accepted') {
            showInstallButton.value = false;
        }
        deferredPrompt.value = null;
    }
};
</script>

<template>
    <Head title="Tizimga Kirish - Sher-Dor ERP" />
 
    <div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden selection:bg-white/30 selection:text-white font-sans text-white bg-cover bg-center bg-no-repeat bg-fixed" :style="$page.props.branding?.background_image_path ? { backgroundImage: 'url(/storage/' + $page.props.branding.background_image_path + ')' } : {}">
        
        <!-- Authentic Galaxy Background -->
        <GalaxyBackground />
 
        <div class="w-full max-w-md relative z-10 animate-[fadeIn_0.5s_ease-out] px-2 sm:px-0">
            
            <div class="text-center mb-6 sm:mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 rounded-[1.2rem] sm:rounded-[1.5rem] bg-gradient-to-tr from-gray-200 to-white shadow-[0_0_30px_rgba(255,255,255,0.15)] mb-4 sm:mb-6 overflow-hidden">
                    <img v-if="$page.props.branding?.logo_path" :src="'/storage/' + $page.props.branding.logo_path" class="w-full h-full object-contain p-2" />
                    <span v-else class="font-display font-black text-3xl sm:text-4xl text-black">
                        {{ ($page.props.branding?.company_name || 'Sher-Dor').charAt(0).toUpperCase() }}
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-display font-bold text-white tracking-widest">{{ $t('sher_dor') }}</h1>
                <p class="text-gray-400 font-medium text-xs sm:text-sm mt-2">Boshqaruv Tizimiga Kirish</p>
            </div>
 
            <!-- Apple Glassmorphism Panel -->
            <div class="bg-white/5 p-6 sm:p-4 md:p-8 rounded-[1.5rem] sm:rounded-[2rem] border border-white/10 backdrop-blur-md shadow-[0_0_50px_rgba(0,0,0,0.5)]">
                
                <div v-if="status" class="mb-6 text-sm font-medium text-green-400 bg-green-400/10 border border-green-400/20 p-3 rounded-xl text-center">
                    {{ status }}
                </div>
 
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium mb-2 ml-1" style="color: #cbd5e1 !important;">Email manzil</label>
                        <input
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                            class="w-full px-5 py-4 bg-black/45 border border-white/10 text-white placeholder-white/35 rounded-2xl focus:bg-black/65 focus:ring-2 focus:ring-white/20 focus:border-white/30 transition-all outline-none"
                            placeholder="admin@itcloud.uz"
                        />
                        <p v-if="form.errors.email" class="mt-2 text-sm text-red-400 font-medium">{{ form.errors.email }}</p>
                    </div>
 
                    <div>
                        <label for="password" class="block text-sm font-medium mb-2 ml-1" style="color: #cbd5e1 !important;">Parol</label>
                        <div class="relative">
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                                class="w-full pl-5 pr-12 py-4 bg-black/45 border border-white/10 text-white placeholder-white/35 rounded-2xl focus:bg-black/65 focus:ring-2 focus:ring-white/20 focus:border-white/30 transition-all outline-none"
                                placeholder="••••••••"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-white/40 hover:text-white transition-colors focus:outline-none p-1"
                                tabindex="-1"
                            >
                                <svg v-if="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-2 text-sm text-red-400 font-medium">{{ form.errors.password }}</p>
                    </div>
 
                    <div class="flex items-center justify-between mt-2">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" v-model="form.remember" class="w-5 h-5 rounded-md border-white/20 bg-black/45 text-black checked:bg-white checked:border-white focus:ring-white/30 focus:ring-offset-black transition-all cursor-pointer">
                            <span class="text-sm transition-colors" style="color: #cbd5e1 !important;">Eslab qolish</span>
                        </label>
 
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm hover:text-white transition-colors hover:underline"
                            style="color: #cbd5e1 !important;"
                        >
                            Parolni unutdingizmi?
                        </Link>
                    </div>
 
                    <div class="pt-2 sm:pt-4 space-y-3 sm:space-y-4">
                        <button
                            type="submit"
                            class="w-full px-4 sm:px-6 py-3 sm:py-4 bg-white text-black font-display font-bold text-base sm:text-lg rounded-xl sm:rounded-2xl hover:bg-gray-200 shadow-[0_0_20px_rgba(255,255,255,0.2)] hover:shadow-[0_0_30px_rgba(255,255,255,0.4)] transition-all transform active:scale-95 disabled:opacity-50 flex justify-center items-center gap-3"
                            :disabled="form.processing"
                        >
                            <svg v-if="form.processing" class="animate-spin -ml-1 h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            TIZIMGA KIRISH
                        </button>
 
                        <button
                            v-if="showInstallButton"
                            @click="installPWA"
                            type="button"
                            class="w-full px-4 sm:px-6 py-3 sm:py-4 bg-white/10 border border-white/20 text-white font-display font-bold text-base sm:text-lg rounded-xl sm:rounded-2xl hover:bg-white/25 transition-all transform active:scale-95 flex justify-center items-center gap-2"
                        >
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            Ilovani O'rnatish
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="text-center mt-6 sm:mt-8">
                <Link :href="route('landing')" class="text-sm font-medium transition-colors border-b border-dashed border-white/20 pb-1" style="color: #cbd5e1 !important;">
                    ← Asosiy sahifaga qaytish
                </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Chrome, Safari, Edge autofill style override */
input:-webkit-autofill,
input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, 
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 1000px #090d16 inset !important;
    -webkit-text-fill-color: #ffffff !important;
    transition: background-color 5000s ease-in-out 0s;
}

/* Firefox autofill style override */
input:autofill {
    background-color: #090d16 !important;
    color: #ffffff !important;
}

/* Checkbox styling when checked (visible black tick on white background) */
input[type="checkbox"]:checked {
    background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='%23000000' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e") !important;
    background-color: #ffffff !important;
    border-color: #ffffff !important;
}
</style>
