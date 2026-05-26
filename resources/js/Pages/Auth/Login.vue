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
                <div class="inline-flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 rounded-[1.2rem] sm:rounded-[1.5rem] bg-gradient-to-tr from-gray-200 to-white shadow-[0_0_30px_rgba(255,255,255,0.15)] mb-4 sm:mb-6 ">
                    <span class="font-display font-black text-3xl sm:text-4xl text-black">S</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-display font-bold text-white tracking-widest">{{ $t('sher_dor') }}</h1>
                <p class="text-gray-400 font-medium text-xs sm:text-sm mt-2">Boshqaruv Tizimiga Kirish</p>
            </div>

            <!-- Apple Glassmorphism Panel -->
            <div class="bg-white/5 p-6 sm:p-4 md:p-8 rounded-[1.5rem] sm:rounded-[2rem] border border-white/10 shadow-[0_0_50px_rgba(0,0,0,0.5)] ">
                
                <div v-if="status" class="mb-6 text-sm font-medium text-green-400 bg-green-400/10 border border-green-400/20 p-3 rounded-xl text-center">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-2 ml-1">Email manzil</label>
                        <input
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                            class="w-full px-5 py-4 bg-black/40 border border-white/10 text-white placeholder-white/30 rounded-2xl focus:ring-2 focus:ring-white/30 focus:border-white/30 transition-all "
                            placeholder="admin@itcloud.uz"
                        />
                        <p v-if="form.errors.email" class="mt-2 text-sm text-red-400 font-medium">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2 ml-1">Parol</label>
                        <input
                            id="password"
                            type="password"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                            class="w-full px-5 py-4 bg-black/40 border border-white/10 text-white placeholder-white/30 rounded-2xl focus:ring-2 focus:ring-white/30 focus:border-white/30 transition-all "
                            placeholder="••••••••"
                        />
                        <p v-if="form.errors.password" class="mt-2 text-sm text-red-400 font-medium">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between mt-2">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" v-model="form.remember" class="w-5 h-5 rounded-md border-white/20 bg-gray-500/50 text-white focus:ring-white/50 focus:ring-offset-black transition-all">
                            <span class="text-sm text-gray-400 group-hover:text-white transition-colors">Eslab qolish</span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm text-gray-400 hover:text-white transition-colors hover:underline"
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
                            class="w-full px-4 sm:px-6 py-3 sm:py-4 bg-neon-cyan/20 border border-neon-cyan/50 text-neon-cyan font-display font-bold text-base sm:text-lg rounded-xl sm:rounded-2xl hover:bg-neon-cyan/30 shadow-[0_0_20px_rgba(0,255,255,0.1)] transition-all transform active:scale-95 flex justify-center items-center gap-3"
                        >
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            📲 Ilovani O'rnatish
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="text-center mt-6 sm:mt-8">
                <Link :href="route('landing')" class="text-sm font-medium text-gray-400 hover:text-white transition-colors border-b border-dashed border-white/20 pb-1">
                    ← Asosiy sahifaga qaytish
                </Link>
            </div>
        </div>
    </div>
</template>
