<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

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

const showPassword = ref(false);
</script>

<template>
    <Head title="Tizimga Kirish - SBE" />

    <div class="min-h-screen w-full flex bg-[#0a0f1c] font-sans text-white overflow-hidden selection:bg-purple-500/30 selection:text-white">
        
        <!-- Left Side: Atmosphere & Branding (Hidden on mobile, lg: block) -->
        <div class="hidden lg:flex lg:w-1/2 relative flex-col justify-between overflow-hidden">
            <!-- Background Image -->
            <div class="absolute inset-0 bg-[url('/images/futuristic-city.png')] bg-cover bg-center bg-no-repeat transform scale-105 animate-[slowZoom_20s_infinite_alternate]"></div>
            
            <!-- Dark Overlay with Blur -->
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm z-0"></div>
            
            <!-- Content Wrapper -->
            <div class="relative z-10 flex flex-col h-full p-12">
                
                <!-- Logo Top Left -->
                <div class="flex items-center gap-4">
                    <img src="/images/itcloud-logo.png" alt="ITCloud Logo" class="h-12 object-contain drop-shadow-[0_0_15px_rgba(255,255,255,0.4)]" @error="(e) => e.target.src='https://ui-avatars.com/api/?name=ITC&background=000&color=fff&rounded=true'" />
                    <span class="text-xl font-bold tracking-widest text-white/90">ITCLOUD</span>
                </div>

                <!-- Center Text -->
                <div class="flex-1 flex flex-col justify-center items-start max-w-xl">
                    <h1 class="text-5xl xl:text-6xl font-extrabold leading-tight mb-6 text-transparent bg-clip-text bg-gradient-to-r from-white via-blue-200 to-purple-300 drop-shadow-lg">
                        SBE - Kelajak Arxitekturasi
                    </h1>
                    <p class="text-lg xl:text-xl text-gray-300 leading-relaxed font-medium">
                        Qurilish, Moliya va CRM jarayonlarini yagona innovatsion platformada boshqaring. 
                        Xavfsizlik, tezlik va to'liq nazorat sizning qo'lingizda.
                    </p>
                </div>

                <!-- Partners Bottom -->
                <div class="mt-auto pt-8 border-t border-white/10">
                    <p class="text-sm text-gray-400 mb-4 font-semibold uppercase tracking-wider">Ishonchli Hamkorlar</p>
                    <div class="flex items-center gap-6 opacity-70 flex-wrap">
                        <span class="font-bold text-lg text-white">Sherdor</span>
                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                        <span class="font-bold text-lg text-white">Delta</span>
                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                        <span class="font-bold text-lg text-white">Hakim O'g'lu</span>
                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                        <span class="font-bold text-lg text-white text-center leading-tight">Göztepe Builder<br><span class="text-xs text-blue-300">Global</span></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                        <span class="font-bold text-lg text-white">Hayot House</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: The Cyber-Glass Login -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 relative">
            <!-- Subtle background glow for right side -->
            <div class="absolute top-1/4 right-1/4 w-96 h-96 bg-purple-600/10 rounded-full blur-[120px] pointer-events-none"></div>
            <div class="absolute bottom-1/4 left-1/4 w-96 h-96 bg-blue-600/10 rounded-full blur-[120px] pointer-events-none"></div>

            <div class="w-full max-w-md bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl shadow-[0_0_40px_rgba(139,92,246,0.1)] p-8 sm:p-10 relative z-10 animate-[fadeInUp_0.5s_ease-out]">
                
                <div class="text-center mb-10">
                    <h2 class="text-3xl font-extrabold text-white mb-2">Xush kelibsiz</h2>
                    <p class="text-gray-400">Tizimga kirish uchun ma'lumotlaringizni kiriting</p>
                </div>

                <div v-if="status" class="mb-6 text-sm font-medium text-green-400 bg-green-400/10 border border-green-400/20 p-3 rounded-xl text-center">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email manzil</label>
                        <input
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                            class="w-full px-5 py-3.5 bg-black/20 border border-white/10 text-white rounded-xl focus:bg-black/40 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all outline-none placeholder-gray-500"
                            placeholder="admin@itcloud.uz"
                        />
                        <p v-if="form.errors.email" class="mt-2 text-sm text-red-400 font-medium">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Parol</label>
                        <div class="relative">
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                                class="w-full pl-5 pr-12 py-3.5 bg-black/20 border border-white/10 text-white rounded-xl focus:bg-black/40 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all outline-none placeholder-gray-500"
                                placeholder="••••••••"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-purple-400 transition-colors focus:outline-none p-1"
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

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" v-model="form.remember" class="w-4 h-4 rounded border-white/20 bg-black/20 text-purple-600 focus:ring-purple-500 focus:ring-offset-[#0a0f1c] transition-all cursor-pointer">
                            <span class="text-sm text-gray-400 group-hover:text-gray-300 transition-colors">Eslab qolish</span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm text-gray-400 hover:text-purple-400 transition-colors hover:underline"
                        >
                            Parolni unutdingizmi?
                        </Link>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            class="w-full px-6 py-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold text-lg rounded-xl shadow-[0_0_20px_rgba(139,92,246,0.3)] hover:shadow-[0_0_30px_rgba(139,92,246,0.5)] transition-all transform hover:scale-[1.02] active:scale-95 disabled:opacity-50 disabled:transform-none flex justify-center items-center gap-3 relative overflow-hidden"
                            :disabled="form.processing"
                        >
                            <svg v-if="form.processing" class="animate-spin -ml-1 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            TIZIMGA KIRISH
                        </button>
                    </div>
                </form>

                <div class="mt-8 text-center lg:hidden">
                    <p class="text-xs text-gray-500">© 2026 ITCloud.uz | SBE</p>
                </div>
            </div>
            
            <!-- Mobile partners list (Only visible on mobile) -->
            <div class="absolute bottom-4 left-0 w-full text-center lg:hidden px-4">
                <div class="flex items-center justify-center gap-3 flex-wrap opacity-50 text-xs">
                    <span>Sherdor</span>•<span>Delta</span>•<span>Hakim O'g'lu</span>•<span>Göztepe Builder</span>
                </div>
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

@keyframes slowZoom {
    from {
        transform: scale(1.05);
    }
    to {
        transform: scale(1.15);
    }
}

/* Chrome, Safari, Edge autofill style override */
input:-webkit-autofill,
input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, 
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 1000px rgba(0,0,0,0.5) inset !important;
    -webkit-text-fill-color: #ffffff !important;
    transition: background-color 5000s ease-in-out 0s;
}

input:autofill {
    background-color: rgba(0,0,0,0.5) !important;
    color: #ffffff !important;
}

input[type="checkbox"]:checked {
    background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='%23ffffff' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e") !important;
    background-color: #9333ea !important; /* purple-600 */
    border-color: #9333ea !important;
}
</style>
