<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

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
        e.preventDefault();
        deferredPrompt.value = e;
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
    <Head title="Tizimga Kirish - SBE" />
 
    <div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden selection:bg-cyan-500/30 selection:text-white font-sans text-white bg-gradient-to-br from-gray-900 via-blue-900 to-black z-0">
        
        <!-- Cyber/Blueprint Background Effects -->
        <div class="absolute inset-0 pointer-events-none z-0 overflow-hidden">
            <!-- Grid Lines (Blueprint effect) -->
            <div class="absolute inset-0 blueprint-grid opacity-20"></div>
            <!-- Glowing Orbs -->
            <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] rounded-full bg-cyan-600/20 blur-[120px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] rounded-full bg-blue-600/20 blur-[120px]"></div>
            <!-- Particles/Lines -->
            <div class="particles-container absolute inset-0"></div>
        </div>
 
        <div class="w-full max-w-md relative z-10 animate-fade-in-up px-2 sm:px-0 flex flex-col items-center">
            
            <!-- Glassmorphism Panel -->
            <div class="w-full bg-white/5 p-8 sm:p-10 rounded-3xl border border-cyan-500/30 backdrop-blur-xl shadow-[0_0_50px_rgba(6,182,212,0.15)] relative overflow-hidden group">
                <!-- Inner top glow -->
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-cyan-500 to-transparent opacity-50"></div>
                
                <div class="text-center mb-8">
                    <!-- Logo Area -->
                    <div class="relative inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-2xl bg-black/40 border border-white/10 shadow-[0_0_25px_rgba(6,182,212,0.3)] mb-6 overflow-hidden backdrop-blur-md transition-transform duration-500 hover:scale-105 hover:border-cyan-400/50 hover:shadow-[0_0_35px_rgba(6,182,212,0.5)] flex items-center justify-center">
                        <img src="/images/itcloud-logo.png" alt="ITCloud" class="w-full h-full object-cover" @error="(e) => e.target.src='https://ui-avatars.com/api/?name=ITC&background=000&color=06b6d4&rounded=true&font-size=0.33'" />
                    </div>
                    
                    <h1 class="text-4xl sm:text-5xl font-extrabold text-white tracking-tight drop-shadow-md">SBE</h1>
                    <p class="text-gray-400 text-sm sm:text-base mt-2 font-medium tracking-wide">Boshqaruv Tizimiga Kirish</p>
                </div>
 
                <div v-if="status" class="mb-6 text-sm font-medium text-green-400 bg-green-400/10 border border-green-400/20 p-3 rounded-xl text-center">
                    {{ status }}
                </div>
 
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label for="email" class="block text-sm font-medium mb-2 text-gray-300">Email manzil</label>
                        <div class="relative">
                            <input
                                id="email"
                                type="email"
                                v-model="form.email"
                                required
                                autofocus
                                autocomplete="username"
                                class="w-full px-5 py-3.5 bg-white/5 border border-white/10 text-white placeholder-gray-500 rounded-xl focus:bg-white/10 focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50 transition-all outline-none shadow-inner"
                                placeholder="admin@itcloud.uz"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-2 text-sm text-red-400 font-medium">{{ form.errors.email }}</p>
                    </div>
 
                    <div>
                        <label for="password" class="block text-sm font-medium mb-2 text-gray-300">Parol</label>
                        <div class="relative group-input">
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                                class="w-full pl-5 pr-12 py-3.5 bg-white/5 border border-white/10 text-white placeholder-gray-500 rounded-xl focus:bg-white/10 focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all outline-none shadow-inner"
                                placeholder="••••••••"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-cyan-400 transition-colors focus:outline-none p-1"
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
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" v-model="form.remember" class="w-4 h-4 rounded border-white/20 bg-white/5 text-cyan-500 focus:ring-cyan-500/50 focus:ring-offset-0 transition-all cursor-pointer">
                            <span class="text-xs sm:text-sm text-gray-400 group-hover:text-gray-300 transition-colors">Eslab qolish</span>
                        </label>
 
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-xs sm:text-sm text-gray-400 hover:text-cyan-400 transition-colors hover:underline"
                        >
                            Parolni unutdingizmi?
                        </Link>
                    </div>
 
                    <div class="pt-4 space-y-4">
                        <button
                            type="submit"
                            class="w-full px-6 py-4 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-500 hover:to-purple-500 text-white font-bold text-base sm:text-lg rounded-xl shadow-[0_0_20px_rgba(79,70,229,0.4)] hover:shadow-[0_0_30px_rgba(79,70,229,0.6)] transition-all transform hover:-translate-y-1 active:scale-95 disabled:opacity-50 disabled:transform-none flex justify-center items-center gap-3 relative overflow-hidden"
                            :disabled="form.processing"
                        >
                            <div class="absolute inset-0 w-full h-full bg-white/20 blur-md transform -translate-x-full hover:animate-[shimmer_1.5s_infinite]"></div>
                            <svg v-if="form.processing" class="animate-spin -ml-1 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            TIZIMGA KIRISH
                        </button>
 
                        <button
                            v-if="showInstallButton"
                            @click="installPWA"
                            type="button"
                            class="w-full px-6 py-3.5 bg-transparent border border-white/20 text-gray-300 font-medium text-sm sm:text-base rounded-xl hover:bg-white/5 hover:border-white/40 hover:text-white transition-all transform active:scale-95 flex justify-center items-center gap-2"
                        >
                            <svg class="w-5 h-5 text-current" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            Ilovani O'rnatish
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Back to Home -->
            <div class="mt-8 text-center">
                <Link :href="route('landing')" class="text-sm font-medium text-gray-400 hover:text-cyan-400 transition-colors flex items-center gap-2 group">
                    <span class="transform group-hover:-translate-x-1 transition-transform">←</span>
                    Asosiy sahifaga qaytish
                </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fade-in-up {
    animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes shimmer {
    100% {
        transform: translateX(100%);
    }
}

/* Blueprint Grid */
.blueprint-grid {
    background-image: 
        linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
    background-size: 30px 30px;
    background-position: center center;
    perspective: 1000px;
    transform-origin: center;
    animation: grid-move 20s linear infinite;
}

@keyframes grid-move {
    0% {
        transform: translateY(0) rotateX(20deg) scale(1.5);
    }
    100% {
        transform: translateY(30px) rotateX(20deg) scale(1.5);
    }
}

/* Moving Particles simulation via CSS multiple backgrounds */
.particles-container {
    background-image: 
        radial-gradient(circle at 15% 50%, rgba(6, 182, 212, 0.15) 2px, transparent 3px),
        radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.15) 2px, transparent 3px),
        radial-gradient(circle at 50% 80%, rgba(255, 255, 255, 0.1) 1.5px, transparent 2px),
        radial-gradient(circle at 70% 90%, rgba(6, 182, 212, 0.2) 2px, transparent 3px),
        radial-gradient(circle at 20% 20%, rgba(139, 92, 246, 0.2) 2.5px, transparent 3.5px);
    background-size: 150px 150px, 200px 200px, 100px 100px, 250px 250px, 300px 300px;
    animation: particles-drift 30s linear infinite;
}

@keyframes particles-drift {
    0% {
        background-position: 0 0, 0 0, 0 0, 0 0, 0 0;
    }
    100% {
        background-position: 150px 150px, -200px 200px, 100px -100px, -250px -250px, 300px 300px;
    }
}

/* Chrome, Safari, Edge autofill style override */
input:-webkit-autofill,
input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, 
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 1000px rgba(255,255,255,0.05) inset !important;
    -webkit-text-fill-color: #ffffff !important;
    transition: background-color 5000s ease-in-out 0s;
}

input:autofill {
    background-color: rgba(255,255,255,0.05) !important;
    color: #ffffff !important;
}

input[type="checkbox"]:checked {
    background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='%2306b6d4' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e") !important;
    background-color: transparent !important;
    border-color: #06b6d4 !important;
}
</style>
