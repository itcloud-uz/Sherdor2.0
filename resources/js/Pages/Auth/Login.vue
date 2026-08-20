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
    <Head title="Tizimga Kirish - IQS ERP" />

    <!-- Ultra Minimalist Luxury Container -->
    <div class="min-h-screen w-full relative flex items-center justify-center font-sans text-white overflow-hidden bg-[#06060a] selection:bg-amber-500 selection:text-black">
        
        <!-- Ambient Glowing Mesh Background -->
        <div class="fixed top-[-15%] left-[-10%] w-[600px] h-[600px] rounded-full bg-amber-500/10 blur-[160px] pointer-events-none"></div>
        <div class="fixed bottom-[-15%] right-[-10%] w-[650px] h-[650px] rounded-full bg-cyan-500/10 blur-[170px] pointer-events-none"></div>
        <div class="fixed top-[30%] right-[30%] w-[450px] h-[450px] rounded-full bg-purple-600/10 blur-[150px] pointer-events-none"></div>

        <!-- Subtle Tech Grid Lines Overlay -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff05_1px,transparent_1px),linear-gradient(to_bottom,#ffffff05_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)] pointer-events-none"></div>

        <!-- Main Wrapper -->
        <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 relative z-10 grid lg:grid-cols-12 gap-8 items-center min-h-screen">
            
            <!-- Left Side: Brand Story & High-Tech Showcase (7 cols) -->
            <div class="hidden lg:flex lg:col-span-7 flex-col justify-between h-full py-12 pr-6">
                
                <!-- Brand Header -->
                <div class="flex items-center gap-3.5">
                    <Link :href="route('landing')" class="flex items-center gap-3.5 group">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 via-purple-600 to-cyan-400 p-[1.5px] shadow-[0_0_25px_rgba(245,158,11,0.3)] group-hover:scale-105 transition-transform">
                            <div class="w-full h-full bg-[#0a0a12] rounded-[14px] flex items-center justify-center font-black text-xl text-amber-400">
                                IQS
                            </div>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-2xl font-black tracking-tight text-white">IQS ERP</span>
                            <span class="text-[10px] text-amber-400 font-semibold uppercase tracking-wider">Qur va Sotish • Men Quraman va Sotaman!</span>
                        </div>
                    </Link>
                </div>

                <!-- Main Pitch Headline -->
                <div class="my-auto max-w-xl space-y-6">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/[0.03] border border-white/10 backdrop-blur-md">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        <span class="text-xs font-semibold text-gray-300">Qurilish va Sotuv Jarayonlari Boshqaruvi</span>
                    </div>

                    <h1 class="text-4xl xl:text-5xl font-black tracking-tight leading-[1.15] text-white">
                        Zamonaviy Qurilish <br>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 via-purple-300 to-cyan-400">
                            Innovatsion Arxitekturasi
                        </span>
                    </h1>

                    <p class="text-gray-400 text-base xl:text-lg leading-relaxed font-light">
                        Ko'p qavatli bino va majmualarni boshqarish: 3D shaxmatka, shartnomalar, omborxona, smeta, kassa va to'liq moliyaviy hisobotlar yagona tizimda.
                    </p>

                    <!-- Feature Badges Grid -->
                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <div class="p-3.5 rounded-2xl bg-white/[0.02] border border-white/5 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center text-base">
                                🏢
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-white">3D Shaxmatka</h4>
                                <p class="text-[10px] text-gray-400">Real vaqt nazorati</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-white/[0.02] border border-white/5 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 flex items-center justify-center text-base">
                                📊
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-white">Smeta & Ombor</h4>
                                <p class="text-[10px] text-gray-400">Materiallar balansi</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-white/[0.02] border border-white/5 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center text-base">
                                🤝
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-white">CRM & Shartnoma</h4>
                                <p class="text-[10px] text-gray-400">Avtomatlashtirilgan</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-2xl bg-white/[0.02] border border-white/5 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center text-base">
                                📱
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-white">SMS & Moliya</h4>
                                <p class="text-[10px] text-gray-400">Eslatmalar va Kassa</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Partners & Developer Link -->
                <div class="pt-8 border-t border-white/10 flex items-center justify-between text-xs text-gray-400">
                    <div class="flex items-center gap-4 flex-wrap">
                        <span class="font-bold text-white">Sher-Dor</span>
                        <span class="text-gray-600">•</span>
                        <span class="font-bold text-white">Hayot House</span>
                        <span class="text-gray-600">•</span>
                        <span class="font-bold text-white">Baraka Residence</span>
                    </div>

                    <a href="https://itcloud.uz" target="_blank" class="text-cyan-400 font-semibold hover:underline flex items-center gap-1">
                        ITCloud.uz tomonidan taqdim etiladi ↗
                    </a>
                </div>

            </div>

            <!-- Right Side: Ultra Minimalist Glass Login Card (5 cols) -->
            <div class="lg:col-span-5 flex flex-col items-center justify-center w-full">
                
                <div class="w-full max-w-md p-8 sm:p-10 rounded-3xl bg-[#0c0c16]/80 backdrop-blur-2xl border border-white/10 hover:border-white/20 shadow-[0_20px_50px_rgba(0,0,0,0.7)] transition-all duration-300 relative overflow-hidden">
                    
                    <!-- Decorative Top Glow Line -->
                    <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-amber-400 to-cyan-400"></div>

                    <!-- Mobile Brand Icon (Visible only on mobile) -->
                    <div class="lg:hidden flex items-center justify-center mb-6">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-purple-600 p-[1px] flex items-center justify-center shadow-lg">
                            <div class="w-full h-full bg-[#0a0a12] rounded-[15px] flex items-center justify-center font-black text-xl text-amber-400">
                                IQS
                            </div>
                        </div>
                    </div>

                    <!-- Card Header -->
                    <div class="text-center mb-8">
                        <h2 class="text-2xl sm:text-3xl font-black text-white mb-2">Tizimga Kirish</h2>
                        <p class="text-gray-400 text-xs sm:text-sm font-light">Boshqaruv paneliga kirish uchun ma'lumotlaringizni kiriting</p>
                    </div>

                    <!-- Status Message -->
                    <div v-if="status" class="mb-6 p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs text-center font-medium">
                        {{ status }}
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submit" class="space-y-5">
                        
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
                                    class="w-full pl-11 pr-4 py-3.5 bg-black/40 border border-white/15 text-white placeholder-gray-500 rounded-xl focus:border-amber-400 focus:outline-none focus:ring-1 focus:ring-amber-400 transition-all text-sm font-mono"
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
                                    class="w-full pl-11 pr-11 py-3.5 bg-black/40 border border-white/15 text-white placeholder-gray-500 rounded-xl focus:border-amber-400 focus:outline-none focus:ring-1 focus:ring-amber-400 transition-all text-sm font-mono"
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
                            <label class="flex items-center gap-2 cursor-pointer select-none text-gray-400 hover:text-gray-300">
                                <input 
                                    type="checkbox" 
                                    v-model="form.remember" 
                                    class="w-4 h-4 rounded bg-black/40 border-white/20 text-amber-500 focus:ring-amber-400 focus:ring-offset-0 transition-all cursor-pointer"
                                />
                                <span>Eslab qolish</span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-gray-400 hover:text-amber-400 transition-colors hover:underline"
                            >
                                Parolni unutdingizmi?
                            </Link>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full py-4 rounded-xl bg-gradient-to-r from-amber-500 via-amber-600 to-yellow-500 hover:from-amber-400 hover:to-amber-500 text-black font-extrabold text-sm sm:text-base uppercase tracking-wider shadow-[0_0_25px_rgba(245,158,11,0.4)] hover:shadow-[0_0_35px_rgba(245,158,11,0.6)] hover:scale-[1.02] active:scale-[0.98] transition-all disabled:opacity-50 flex items-center justify-center gap-2.5"
                            >
                                <span v-if="form.processing" class="w-4 h-4 border-2 border-black border-t-transparent rounded-full animate-spin"></span>
                                <span>Tizimga Kirish</span>
                                <span v-if="!form.processing">→</span>
                            </button>
                        </div>

                    </form>

                    <!-- Navigation Shortcuts -->
                    <div class="mt-8 pt-6 border-t border-white/10 flex items-center justify-between text-xs text-gray-400">
                        <Link :href="route('landing')" class="hover:text-amber-400 transition-colors flex items-center gap-1">
                            <span>←</span> Bosh sahifa
                        </Link>
                        <Link :href="route('promo.offers')" class="text-cyan-400 hover:underline flex items-center gap-1 font-semibold">
                            Uy sotib olish ↗
                        </Link>
                    </div>

                </div>

                <!-- Footer Tag -->
                <p class="mt-6 text-[11px] text-gray-500 text-center font-mono">
                    © 2026 ITCloud.uz • IQS ERP Platform
                </p>

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
    -webkit-box-shadow: 0 0 0 1000px rgba(10, 10, 18, 0.95) inset !important;
    -webkit-text-fill-color: #ffffff !important;
    transition: background-color 5000s ease-in-out 0s;
}

input:autofill {
    background-color: rgba(10, 10, 18, 0.95) !important;
    color: #ffffff !important;
}
</style>
