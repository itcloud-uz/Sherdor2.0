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

const showPassword = ref(false);
const isShy = ref(false);
const isPortalOpening = ref(false);
const mascotRef = ref(null);
const leftPupilTransform = ref('');
const rightPupilTransform = ref('');

const maxMove = 9; // Max pupil offset distance in px

const handleMouseMove = (e) => {
    if (isShy.value || !mascotRef.value || isPortalOpening.value) return;

    const rect = mascotRef.value.getBoundingClientRect();
    const mascotCenterX = rect.left + rect.width / 2;
    const mascotCenterY = rect.top + rect.height / 2;

    const angle = Math.atan2(e.clientY - mascotCenterY, e.clientX - mascotCenterX);
    const distance = Math.hypot(e.clientX - mascotCenterX, e.clientY - mascotCenterY);
    const dynamicLimit = Math.min(distance / 180, 1) * maxMove;

    const dx = Math.cos(angle) * dynamicLimit;
    const dy = Math.sin(angle) * dynamicLimit;

    const transformStr = `translate(${dx.toFixed(2)}px, ${dy.toFixed(2)}px)`;
    leftPupilTransform.value = transformStr;
    rightPupilTransform.value = transformStr;
};

const onPasswordFocus = () => {
    if (!showPassword.value) {
        isShy.value = true;
    }
};

const onPasswordBlur = () => {
    isShy.value = false;
    leftPupilTransform.value = '';
    rightPupilTransform.value = '';
};

const toggleShowPassword = () => {
    showPassword.value = !showPassword.value;
    if (showPassword.value) {
        isShy.value = false;
    } else {
        isShy.value = true;
    }
};

const submit = () => {
    form.post(route('login'), {
        onSuccess: () => {
            isPortalOpening.value = true;
        },
        onError: () => {
            isPortalOpening.value = false;
        },
        onFinish: () => {
            form.reset('password');
        },
    });
};

onMounted(() => {
    window.addEventListener('mousemove', handleMouseMove);
});

onUnmounted(() => {
    window.removeEventListener('mousemove', handleMouseMove);
});
</script>

<template>
    <Head title="Tizimga Kirish - IQS ERP" />

    <!-- Unified Screen with Deep Cyber-Glass Atmosphere -->
    <div class="min-h-screen w-full relative flex flex-col items-center justify-between font-sans text-white overflow-hidden bg-[#070710] selection:bg-cyan-500 selection:text-black py-8 px-4 sm:px-6">
        
        <!-- Ambient Glowing Nebulae -->
        <div class="fixed top-[-20%] left-[20%] w-[600px] h-[600px] rounded-full bg-cyan-500/10 blur-[180px] pointer-events-none"></div>
        <div class="fixed bottom-[-20%] right-[20%] w-[600px] h-[600px] rounded-full bg-purple-600/15 blur-[190px] pointer-events-none"></div>
        <div class="fixed top-[40%] left-[-10%] w-[400px] h-[400px] rounded-full bg-amber-500/10 blur-[160px] pointer-events-none"></div>

        <!-- Floating Cloud Particles in Background -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden z-0 opacity-40">
            <div class="absolute top-[10%] left-[5%] w-72 h-32 bg-white/[0.03] rounded-full blur-2xl animate-[floatSlow_18s_infinite_alternate]"></div>
            <div class="absolute top-[60%] right-[5%] w-96 h-40 bg-cyan-500/[0.04] rounded-full blur-3xl animate-[floatSlow_24s_infinite_alternate-reverse]"></div>
            <div class="absolute bottom-[10%] left-[15%] w-80 h-36 bg-purple-500/[0.04] rounded-full blur-2xl animate-[floatSlow_20s_infinite_alternate]"></div>
        </div>

        <!-- Top Header Navigation -->
        <header class="w-full max-w-5xl flex items-center justify-between relative z-10">
            <Link :href="route('landing')" class="flex items-center gap-3 group">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-cyan-400 via-purple-500 to-amber-400 p-[1.5px] shadow-[0_0_20px_rgba(6,182,212,0.3)] group-hover:scale-105 transition-transform">
                    <div class="w-full h-full bg-[#0b0b18] rounded-[14px] flex items-center justify-center font-black text-sm text-cyan-300">
                        IQS
                    </div>
                </div>
                <div class="flex flex-col">
                    <span class="text-xl font-black tracking-tight text-white flex items-center gap-2">
                        IQS ERP
                        <span class="px-2 py-0.5 rounded-full bg-cyan-500/15 border border-cyan-400/30 text-[10px] font-bold text-cyan-300">
                            IT Cloud
                        </span>
                    </span>
                    <span class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Men Quraman va Sotaman!</span>
                </div>
            </Link>

            <div class="flex items-center gap-3">
                <Link :href="route('promo.offers')" class="hidden sm:inline-flex px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-semibold text-gray-300 transition-colors">
                    Uy sotib olish ↗
                </Link>
                <Link :href="route('landing')" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 text-xs font-bold text-white transition-all shadow-sm">
                    ← Bosh sahifa
                </Link>
            </div>
        </header>

        <!-- Main Unified Card Container -->
        <main class="w-full max-w-lg my-auto relative z-10 py-6">
            
            <div class="w-full rounded-[38px] bg-[#0c0c1a]/80 backdrop-blur-2xl border border-white/10 shadow-[0_20px_60px_rgba(0,0,0,0.8)] p-8 sm:p-10 relative overflow-hidden transition-all duration-300">
                
                <!-- Glowing Top Accent Line -->
                <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-cyan-400 to-purple-500"></div>

                <!-- Interactive Cute "IT Cloud" Bulut Mascot -->
                <div class="flex flex-col items-center justify-center mb-6">
                    
                    <div 
                        ref="mascotRef" 
                        class="relative w-44 h-36 flex items-center justify-center transition-transform duration-300"
                        :class="isShy ? 'scale-110' : 'hover:scale-105'"
                    >
                        <svg viewBox="0 0 300 200" class="w-full h-full drop-shadow-[0_15px_25px_rgba(6,182,212,0.25)]">
                            <!-- Soft Cloud Shadow -->
                            <ellipse cx="150" cy="185" rx="85" ry="12" fill="#000000" opacity="0.3" />

                            <!-- Fluffy Cloud Body (Haqiqiy Yoqimtoy Bulut) -->
                            <path 
                                d="M 80 140 
                                   A 35 35 0 0 1 70 80 
                                   A 45 45 0 0 1 125 45 
                                   A 55 55 0 0 1 195 45 
                                   A 45 45 0 0 1 245 80 
                                   A 35 35 0 0 1 235 140 
                                   A 30 30 0 0 1 200 160 
                                   L 100 160 
                                   A 30 30 0 0 1 80 140 Z" 
                                fill="#ffffff" 
                                stroke="#06b6d4" 
                                stroke-width="4" 
                                stroke-linejoin="round"
                            />

                            <!-- Cute Cheek Blushes -->
                            <ellipse cx="98" cy="115" rx="12" ry="7" fill="#fda4af" opacity="0.75" />
                            <ellipse cx="202" cy="115" rx="12" ry="7" fill="#fda4af" opacity="0.75" />

                            <!-- Cute Smiling Mouth -->
                            <path 
                                :d="isShy ? 'M 143 118 Q 150 114 157 118' : 'M 141 114 Q 150 126 159 114'" 
                                fill="none" 
                                stroke="#0f172a" 
                                stroke-width="3.5" 
                                stroke-linecap="round" 
                            />

                            <!-- EYES (Interactive Cursor Tracking) -->
                            <!-- Left Eye Socket -->
                            <ellipse cx="115" cy="95" rx="15" ry="17" fill="#0f172a" stroke="#06b6d4" stroke-width="1.5" />
                            <!-- Left Eye Reflection/Pupil -->
                            <g v-show="!isShy" :style="{ transform: leftPupilTransform, transformOrigin: '115px 95px' }" class="transition-transform duration-75">
                                <circle cx="115" cy="95" r="8" fill="#38bdf8" />
                                <circle cx="112" cy="91" r="3.5" fill="#ffffff" />
                                <circle cx="118" cy="98" r="1.5" fill="#ffffff" />
                            </g>
                            <!-- Left Eye Shy Arc (Parol kiritilganda) -->
                            <path v-show="isShy" d="M 102 96 Q 115 82 128 96" fill="none" stroke="#ffffff" stroke-width="4" stroke-linecap="round" />

                            <!-- Right Eye Socket -->
                            <ellipse cx="185" cy="95" rx="15" ry="17" fill="#0f172a" stroke="#06b6d4" stroke-width="1.5" />
                            <!-- Right Eye Reflection/Pupil -->
                            <g v-show="!isShy" :style="{ transform: rightPupilTransform, transformOrigin: '185px 95px' }" class="transition-transform duration-75">
                                <circle cx="185" cy="95" r="8" fill="#38bdf8" />
                                <circle cx="182" cy="91" r="3.5" fill="#ffffff" />
                                <circle cx="188" cy="98" r="1.5" fill="#ffffff" />
                            </g>
                            <!-- Right Eye Shy Arc (Parol kiritilganda) -->
                            <path v-show="isShy" d="M 172 96 Q 185 82 198 96" fill="none" stroke="#ffffff" stroke-width="4" stroke-linecap="round" />

                            <!-- Shy Cloud Paws Covering Eyes when Password Focused -->
                            <g v-show="isShy" class="animate-pulse">
                                <!-- Left Cloud Hand -->
                                <circle cx="95" cy="95" r="16" fill="#f8fafc" stroke="#06b6d4" stroke-width="3" />
                                <!-- Right Cloud Hand -->
                                <circle cx="205" cy="95" r="16" fill="#f8fafc" stroke="#06b6d4" stroke-width="3" />
                            </g>
                        </svg>
                    </div>

                    <div class="text-center mt-1">
                        <span class="text-[11px] font-bold uppercase tracking-widest text-cyan-300 bg-cyan-950/60 px-3 py-0.5 rounded-full border border-cyan-500/30">
                            {{ isShy ? '🙈 Bulutcha ko\'zlarini yumib oldi!' : '☁️ IT Cloud Aqlli Yordamchi' }}
                        </span>
                        <h2 class="text-2xl sm:text-3xl font-black text-white mt-2">Tizimga Kirish</h2>
                        <p class="text-gray-400 text-xs mt-0.5">IQS ERP boshqaruv kabinetiga xush kelibsiz</p>
                    </div>

                </div>

                <!-- Status Feedback Message -->
                <div v-if="status" class="mb-5 p-3 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 text-xs text-center font-medium">
                    {{ status }}
                </div>

                <!-- Login Form -->
                <form @submit.prevent="submit" class="space-y-4 relative z-10">
                    
                    <!-- Email Input -->
                    <div>
                        <label for="email" class="block text-xs font-bold text-gray-300 uppercase tracking-wider mb-1.5">Email Manzil</label>
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
                                class="w-full pl-11 pr-4 py-3.5 bg-black/40 border border-white/15 text-white placeholder-gray-500 rounded-2xl focus:border-cyan-400 focus:shadow-[0_0_20px_rgba(6,182,212,0.3)] focus:outline-none transition-all text-sm font-mono"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-400 font-medium">{{ form.errors.email }}</p>
                    </div>

                    <!-- Password Input -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-bold text-gray-300 uppercase tracking-wider">Parol</label>
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-xs text-cyan-400 hover:text-cyan-300 transition-colors hover:underline"
                            >
                                Parolni unutdingizmi?
                            </Link>
                        </div>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-gray-400 text-sm">🔒</span>
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password"
                                @focus="onPasswordFocus"
                                @blur="onPasswordBlur"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full pl-11 pr-20 py-3.5 bg-black/40 border border-white/15 text-white placeholder-gray-500 rounded-2xl focus:border-cyan-400 focus:shadow-[0_0_20px_rgba(6,182,212,0.3)] focus:outline-none transition-all text-sm font-mono"
                            />
                            <button
                                type="button"
                                @click="toggleShowPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-gray-300 hover:text-white transition-colors text-xs font-bold font-mono"
                                tabindex="-1"
                            >
                                {{ showPassword ? 'Yashir' : 'Ko\'rsat' }}
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-400 font-medium">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between text-xs pt-1">
                        <label class="flex items-center gap-2 cursor-pointer select-none text-gray-300 hover:text-white">
                            <input 
                                type="checkbox" 
                                v-model="form.remember" 
                                class="w-4 h-4 rounded-md bg-black/40 border-white/20 text-cyan-500 focus:ring-cyan-400 focus:ring-offset-0 transition-all cursor-pointer"
                            />
                            <span>Meni eslab qol</span>
                        </label>

                        <span class="text-gray-500 font-mono text-[11px]">Himoyalangan Portal</span>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing || isPortalOpening"
                            class="w-full py-4 rounded-2xl bg-gradient-to-r from-cyan-500 via-blue-600 to-purple-600 hover:from-cyan-400 hover:to-purple-500 text-white font-black text-sm sm:text-base uppercase tracking-wider shadow-[0_0_30px_rgba(6,182,212,0.5)] hover:shadow-[0_0_45px_rgba(6,182,212,0.8)] hover:scale-[1.02] active:scale-[0.98] transition-all disabled:opacity-50 flex items-center justify-center gap-2.5 cursor-pointer"
                        >
                            <span v-if="form.processing || isPortalOpening" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>{{ isPortalOpening ? 'Bulutlar ochilmoqda...' : 'Tizimga Kirish' }}</span>
                            <span v-if="!form.processing && !isPortalOpening">→</span>
                        </button>
                    </div>

                </form>

            </div>

        </main>

        <!-- Footer Tag -->
        <footer class="w-full max-w-5xl flex flex-col sm:flex-row items-center justify-between text-xs text-gray-500 gap-3 pt-4 border-t border-white/5 relative z-10">
            <div class="flex items-center gap-3">
                <span class="text-white font-semibold">Sherdor</span>
                <span>•</span>
                <span class="text-white font-semibold">Hayot House</span>
                <span>•</span>
                <span class="text-white font-semibold">Baraka Residence</span>
            </div>
            <div>
                © 2026 <a href="https://itcloud.uz" target="_blank" class="text-cyan-400 font-bold hover:underline">ITCloud.uz</a> • IQS ERP Platformasi
            </div>
        </footer>

        <!-- ========================================================= -->
        <!-- BULUTLAR TARQALISHI VA TIZIM OCHILISHI ANIMATSIYASI (PORTAL) -->
        <!-- ========================================================= -->
        <div 
            v-if="isPortalOpening" 
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#070710] animate-[fadeIn_0.4s_ease-out] overflow-hidden"
        >
            <!-- Center Radiant Light Beacon -->
            <div class="absolute w-[500px] h-[500px] rounded-full bg-gradient-to-tr from-cyan-400 via-blue-500 to-purple-600 blur-[120px] animate-[pulseLight_1.5s_infinite_alternate]"></div>
            
            <!-- Left Parting Cloud -->
            <div class="absolute -left-40 top-0 bottom-0 w-[60vw] bg-gradient-to-r from-slate-200/90 via-white/80 to-transparent rounded-r-full blur-3xl animate-[partLeft_1.8s_forwards_ease-in-out]"></div>
            
            <!-- Right Parting Cloud -->
            <div class="absolute -right-40 top-0 bottom-0 w-[60vw] bg-gradient-to-l from-slate-200/90 via-white/80 to-transparent rounded-l-full blur-3xl animate-[partRight_1.8s_forwards_ease-in-out]"></div>

            <!-- Top Parting Cloud -->
            <div class="absolute -top-32 left-0 right-0 h-[45vh] bg-gradient-to-b from-white/80 via-cyan-100/60 to-transparent rounded-b-full blur-3xl animate-[partUp_1.8s_forwards_ease-in-out]"></div>

            <!-- Revealing Content in the Center -->
            <div class="relative z-20 flex flex-col items-center justify-center text-center p-8 animate-[scaleReveal_1.2s_ease-out]">
                <div class="w-20 h-20 rounded-3xl bg-gradient-to-tr from-cyan-400 to-purple-600 p-1 mb-6 shadow-[0_0_50px_rgba(6,182,212,0.8)]">
                    <div class="w-full h-full bg-[#0a0a16] rounded-[22px] flex items-center justify-center text-3xl">
                        ✨
                    </div>
                </div>
                <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight mb-3">
                    Xush Kelibsiz!
                </h1>
                <p class="text-cyan-300 font-mono text-sm tracking-widest uppercase">
                    Bulutlar tarqalmoqda • Tizim ochilmoqda...
                </p>
            </div>
        </div>

    </div>
</template>

<style scoped>
@keyframes floatSlow {
    0% { transform: translateY(0) scale(1); }
    100% { transform: translateY(-20px) scale(1.05); }
}

@keyframes partLeft {
    0% { transform: translateX(0) scale(1); opacity: 1; }
    100% { transform: translateX(-100%) scale(1.3); opacity: 0; }
}

@keyframes partRight {
    0% { transform: translateX(0) scale(1); opacity: 1; }
    100% { transform: translateX(100%) scale(1.3); opacity: 0; }
}

@keyframes partUp {
    0% { transform: translateY(0) scale(1); opacity: 1; }
    100% { transform: translateY(-100%) scale(1.3); opacity: 0; }
}

@keyframes pulseLight {
    0% { transform: scale(0.8); opacity: 0.6; }
    100% { transform: scale(1.4); opacity: 1; }
}

@keyframes scaleReveal {
    0% { transform: scale(0.7); opacity: 0; }
    60% { transform: scale(1.05); opacity: 1; }
    100% { transform: scale(1); opacity: 1; }
}

/* Chrome, Safari, Edge autofill style override */
input:-webkit-autofill,
input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, 
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 1000px rgba(12, 12, 26, 0.95) inset !important;
    -webkit-text-fill-color: #ffffff !important;
    transition: background-color 5000s ease-in-out 0s;
}

input:autofill {
    background-color: rgba(12, 12, 26, 0.95) !important;
    color: #ffffff !important;
}
</style>
