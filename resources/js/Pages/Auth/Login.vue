<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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
    <Head title="Tizimga Kirish - IQS ERP">
        <link rel="preload" as="image" href="/images/tropical-future-city.jpg" />
    </Head>

    <!-- Ultra-Violet Tropical Future City Container -->
    <div class="min-h-screen w-full relative flex items-center justify-center font-sans text-white overflow-hidden bg-[#06040d] selection:bg-fuchsia-500 selection:text-white">
        
        <!-- Cinematic Tropical Cyber City Background -->
        <div class="absolute inset-0 bg-[url('/images/tropical-future-city.jpg')] bg-cover bg-center bg-no-repeat transform scale-105 animate-[kenburns_25s_infinite_alternate] z-0"></div>
        
        <!-- Deep Ultraviolet Luxury Atmospheric Overlays -->
        <div class="absolute inset-0 bg-gradient-to-r from-[#06040e]/95 via-[#0b071e]/75 to-[#06040e]/85 z-0 backdrop-blur-[2px]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(168,85,247,0.25)_0%,transparent_50%),radial-gradient(ellipse_at_bottom_right,rgba(6,182,212,0.2)_0%,transparent_50%)] z-0 pointer-events-none"></div>

        <!-- Ambient Glowing Aura Orbs -->
        <div class="fixed top-[-10%] left-[-5%] w-[550px] h-[550px] rounded-full bg-violet-600/20 blur-[160px] pointer-events-none"></div>
        <div class="fixed bottom-[-10%] right-[-5%] w-[600px] h-[600px] rounded-full bg-fuchsia-600/20 blur-[170px] pointer-events-none"></div>
        <div class="fixed top-[40%] right-[30%] w-[450px] h-[450px] rounded-full bg-cyan-500/15 blur-[150px] pointer-events-none"></div>

        <!-- Main Content Area -->
        <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 relative z-10 grid lg:grid-cols-12 gap-10 items-center min-h-screen">
            
            <!-- Left Side: Brand Story & Ultra-Violet Showcase (7 cols) -->
            <div class="hidden lg:flex lg:col-span-7 flex-col justify-between h-full py-12 pr-6">
                
                <!-- Brand Header -->
                <div class="flex items-center gap-3.5">
                    <Link :href="route('landing')" class="flex items-center gap-3.5 group">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-violet-500 via-fuchsia-500 to-cyan-400 p-[1.5px] shadow-[0_0_30px_rgba(168,85,247,0.4)] group-hover:scale-105 transition-transform">
                            <div class="w-full h-full bg-[#0a0718] rounded-[14px] flex items-center justify-center font-black text-xl text-fuchsia-400">
                                IQS
                            </div>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-2xl font-black tracking-tight text-white flex items-center gap-2">
                                IQS ERP
                                <span class="px-2 py-0.5 rounded-full bg-violet-500/20 border border-violet-500/40 text-[10px] font-bold text-violet-300">2.0</span>
                            </span>
                            <span class="text-[10px] text-fuchsia-300 font-semibold uppercase tracking-wider">Qur va Sotish • Men Quraman va Sotaman!</span>
                        </div>
                    </Link>
                </div>

                <!-- Main Pitch Headline -->
                <div class="my-auto max-w-xl space-y-6">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/[0.04] border border-violet-500/30 backdrop-blur-xl shadow-[0_0_20px_rgba(168,85,247,0.2)]">
                        <span class="w-2 h-2 rounded-full bg-fuchsia-400 animate-pulse"></span>
                        <span class="text-xs font-semibold text-violet-200">Innovatsion Qurilish va Sotuv Platformasi</span>
                    </div>

                    <h1 class="text-4xl xl:text-5xl font-black tracking-tight leading-[1.18] text-white">
                        Kelajak Shahrini <br>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-fuchsia-400 via-violet-300 to-cyan-300 drop-shadow-[0_0_35px_rgba(168,85,247,0.4)]">
                            Birga Bunyod Etamiz
                        </span>
                    </h1>

                    <p class="text-gray-300 text-base xl:text-lg leading-relaxed font-light">
                        Ko'p qavatli bino va majmualarni yagona aqlli ekotizimda boshqaring: 3D shaxmatka, smeta, kassa, omborxona va mijozlar bilan avtomatlashtirilgan munosabatlar.
                    </p>

                    <!-- Feature Badges -->
                    <div class="flex flex-wrap gap-2.5 pt-2">
                        <span class="px-3.5 py-2 rounded-xl bg-white/[0.04] border border-violet-500/20 text-xs font-medium text-gray-200 backdrop-blur-md flex items-center gap-2">
                            <span class="text-fuchsia-400">⚡</span> 3D Shaxmatka & Monitoring
                        </span>
                        <span class="px-3.5 py-2 rounded-xl bg-white/[0.04] border border-violet-500/20 text-xs font-medium text-gray-200 backdrop-blur-md flex items-center gap-2">
                            <span class="text-cyan-400">📊</span> Onlayn Smeta & Omborxona
                        </span>
                        <span class="px-3.5 py-2 rounded-xl bg-white/[0.04] border border-violet-500/20 text-xs font-medium text-gray-200 backdrop-blur-md flex items-center gap-2">
                            <span class="text-purple-400">🤝</span> CRM & Rasmiy Shartnomalar
                        </span>
                        <span class="px-3.5 py-2 rounded-xl bg-white/[0.04] border border-violet-500/20 text-xs font-medium text-gray-200 backdrop-blur-md flex items-center gap-2">
                            <span class="text-emerald-400">📱</span> Avtomatik SMS & Kassa
                        </span>
                    </div>
                </div>

                <!-- Footer Partners & Developer Link -->
                <div class="pt-8 border-t border-white/10 flex items-center justify-between text-xs text-gray-400">
                    <div class="flex items-center gap-4 flex-wrap">
                        <span class="font-bold text-white/90">Sher-Dor</span>
                        <span class="text-violet-500">•</span>
                        <span class="font-bold text-white/90">Hayot House</span>
                        <span class="text-violet-500">•</span>
                        <span class="font-bold text-white/90">Baraka Residence</span>
                    </div>

                    <a href="https://itcloud.uz" target="_blank" class="text-cyan-300 font-semibold hover:text-cyan-200 transition-colors flex items-center gap-1">
                        ITCloud.uz Dasturiy Majmuasi ↗
                    </a>
                </div>

            </div>

            <!-- Right Side: Ultra-Violet Glass Login Card (5 cols) -->
            <div class="lg:col-span-5 flex flex-col items-center justify-center w-full">
                
                <div class="w-full max-w-md p-8 sm:p-10 rounded-[36px] bg-[#0c091d]/75 backdrop-blur-3xl border border-violet-500/30 shadow-[0_20px_60px_rgba(139,92,246,0.3)] transition-all duration-300 relative overflow-hidden">
                    
                    <!-- Decorative Top Ambient Glow -->
                    <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-violet-500 via-fuchsia-500 to-cyan-400"></div>
                    <div class="absolute -top-24 -right-24 w-48 h-48 bg-fuchsia-600/30 rounded-full blur-[70px] pointer-events-none"></div>

                    <!-- Mobile Brand Icon (Visible only on mobile) -->
                    <div class="lg:hidden flex items-center justify-center mb-6">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-violet-500 via-fuchsia-500 to-cyan-400 p-[1.5px] shadow-[0_0_25px_rgba(168,85,247,0.5)]">
                            <div class="w-full h-full bg-[#0a0718] rounded-[14px] flex items-center justify-center font-black text-2xl text-fuchsia-400">
                                IQS
                            </div>
                        </div>
                    </div>

                    <!-- Card Header -->
                    <div class="text-center mb-8 relative z-10">
                        <h2 class="text-2xl sm:text-3xl font-black text-white mb-2 tracking-tight">Tizimga Kirish</h2>
                        <p class="text-gray-300 text-xs sm:text-sm font-light">Boshqaruv kabinetiga xush kelibsiz</p>
                    </div>

                    <!-- Status Message -->
                    <div v-if="status" class="mb-6 p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs text-center font-medium">
                        {{ status }}
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submit" class="space-y-5 relative z-10">
                        
                        <!-- Email Input -->
                        <div>
                            <label for="email" class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">Email Manzil</label>
                            <div class="relative">
                                <span class="absolute left-4 top-3.5 text-gray-400 text-sm">✉️</span>
                                <input
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="admin@itcloud.uz"
                                    class="w-full pl-11 pr-4 py-3.5 bg-black/40 border border-white/15 text-white placeholder-gray-500 rounded-2xl focus:border-fuchsia-400 focus:shadow-[0_0_20px_rgba(217,70,239,0.3)] focus:outline-none transition-all text-sm font-mono"
                                />
                            </div>
                            <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-400 font-medium">{{ form.errors.email }}</p>
                        </div>

                        <!-- Password Input -->
                        <div>
                            <label for="password" class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">Parol</label>
                            <div class="relative">
                                <span class="absolute left-4 top-3.5 text-gray-400 text-sm">🔒</span>
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    v-model="form.password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="••••••••"
                                    class="w-full pl-11 pr-11 py-3.5 bg-black/40 border border-white/15 text-white placeholder-gray-500 rounded-2xl focus:border-fuchsia-400 focus:shadow-[0_0_20px_rgba(217,70,239,0.3)] focus:outline-none transition-all text-sm font-mono"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-3.5 top-3.5 text-gray-400 hover:text-white transition-colors"
                                    tabindex="-1"
                                >
                                    <span class="text-xs">{{ showPassword ? '🙈' : '👁️' }}</span>
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-400 font-medium">{{ form.errors.password }}</p>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="flex items-center justify-between text-xs pt-1">
                            <label class="flex items-center gap-2 cursor-pointer select-none text-gray-300 hover:text-white">
                                <input 
                                    type="checkbox" 
                                    v-model="form.remember" 
                                    class="w-4 h-4 rounded-md bg-black/40 border-white/20 text-fuchsia-600 focus:ring-fuchsia-500 focus:ring-offset-0 transition-all cursor-pointer"
                                />
                                <span>Eslab qolish</span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-gray-400 hover:text-fuchsia-300 transition-colors hover:underline"
                            >
                                Parolni unutdingizmi?
                            </Link>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full py-4 rounded-2xl bg-gradient-to-r from-violet-600 via-fuchsia-600 to-cyan-500 hover:from-violet-500 hover:to-cyan-400 text-white font-extrabold text-sm sm:text-base uppercase tracking-wider shadow-[0_0_30px_rgba(168,85,247,0.5)] hover:shadow-[0_0_40px_rgba(217,70,239,0.7)] hover:scale-[1.02] active:scale-[0.98] transition-all disabled:opacity-50 flex items-center justify-center gap-2.5"
                            >
                                <span v-if="form.processing" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                <span>Tizimga Kirish</span>
                                <span v-if="!form.processing">→</span>
                            </button>
                        </div>

                    </form>

                    <!-- Navigation Shortcuts -->
                    <div class="mt-8 pt-6 border-t border-white/10 flex items-center justify-between text-xs text-gray-400 relative z-10">
                        <Link :href="route('landing')" class="hover:text-fuchsia-300 transition-colors flex items-center gap-1">
                            <span>←</span> Bosh sahifa
                        </Link>
                        <Link :href="route('promo.offers')" class="text-cyan-300 hover:underline flex items-center gap-1 font-semibold">
                            Uy sotib olish ↗
                        </Link>
                    </div>

                </div>

                <!-- Footer Tag -->
                <p class="mt-6 text-[11px] text-gray-400 text-center font-mono">
                    © 2026 ITCloud.uz • IQS ERP Platform
                </p>

            </div>

        </div>

    </div>
</template>

<style scoped>
@keyframes kenburns {
    0% {
        transform: scale(1.02) translate(0, 0);
    }
    50% {
        transform: scale(1.08) translate(-1%, -1%);
    }
    100% {
        transform: scale(1.02) translate(0, 0);
    }
}

/* Chrome, Safari, Edge autofill style override */
input:-webkit-autofill,
input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, 
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 1000px rgba(12, 9, 29, 0.95) inset !important;
    -webkit-text-fill-color: #ffffff !important;
    transition: background-color 5000s ease-in-out 0s;
}

input:autofill {
    background-color: rgba(12, 9, 29, 0.95) !important;
    color: #ffffff !important;
}
</style>
