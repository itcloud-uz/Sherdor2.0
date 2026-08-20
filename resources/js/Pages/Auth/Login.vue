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

const maxMove = 7; // Max pupil offset distance in px

const handleMouseMove = (e) => {
    if (isShy.value || !mascotRef.value || isPortalOpening.value) return;

    const rect = mascotRef.value.getBoundingClientRect();
    const mascotCenterX = rect.left + rect.width / 2;
    const mascotCenterY = rect.top + rect.height / 2;

    const angle = Math.atan2(e.clientY - mascotCenterY, e.clientX - mascotCenterX);
    const distance = Math.hypot(e.clientX - mascotCenterX, e.clientY - mascotCenterY);
    const dynamicLimit = Math.min(distance / 150, 1) * maxMove;

    const dx = Math.cos(angle) * dynamicLimit;
    const dy = Math.sin(angle) * dynamicLimit;

    const transformStr = `translate(${dx.toFixed(1)}px, ${dy.toFixed(1)}px)`;
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
    isPortalOpening.value = true;
    form.post(route('login'), {
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

    <!-- Unified Screen with Momiq Bulutlar Atmosphere -->
    <div class="min-h-screen w-full relative flex flex-col justify-between items-center font-sans text-white overflow-hidden bg-[#06060e] selection:bg-cyan-500 selection:text-black p-4 sm:p-6">
        
        <!-- Ambient Glowing Colors -->
        <div class="fixed top-[-10%] left-[-10%] w-[500px] h-[500px] rounded-full bg-cyan-500/15 blur-[140px] pointer-events-none"></div>
        <div class="fixed bottom-[-10%] right-[-10%] w-[550px] h-[550px] rounded-full bg-purple-600/20 blur-[150px] pointer-events-none"></div>

        <!-- ========================================================= -->
        <!-- MOMIQ BULUTLAR JONLI ANIMATSIYASI (FLUFFY FLOATING CLOUDS) -->
        <!-- ========================================================= -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden z-0">
            
            <!-- Fluffy Cloud Layer 1 (Yuqori chap momiq bulut) -->
            <div class="absolute top-[8%] -left-20 w-[420px] h-[200px] opacity-75 animate-[cloudDrift1_32s_linear_infinite]">
                <svg viewBox="0 0 400 200" class="w-full h-full filter drop-shadow-[0_15px_30px_rgba(6,182,212,0.15)]">
                    <path d="M 50 140 A 40 40 0 0 1 70 80 A 60 60 0 0 1 150 40 A 70 70 0 0 1 270 50 A 55 55 0 0 1 340 100 A 45 45 0 0 1 350 150 A 40 40 0 0 1 310 180 L 80 180 A 40 40 0 0 1 50 140 Z" fill="url(#cloudGrad1)" />
                    <defs>
                        <linearGradient id="cloudGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ffffff" stop-opacity="0.35" />
                            <stop offset="60%" stop-color="#e0f2fe" stop-opacity="0.18" />
                            <stop offset="100%" stop-color="#a855f7" stop-opacity="0.08" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>

            <!-- Fluffy Cloud Layer 2 (O'ng o'rta momiq bulut) -->
            <div class="absolute top-[35%] -right-28 w-[480px] h-[240px] opacity-70 animate-[cloudDrift2_40s_linear_infinite]">
                <svg viewBox="0 0 450 220" class="w-full h-full filter drop-shadow-[0_20px_35px_rgba(168,85,247,0.2)]">
                    <path d="M 60 160 A 50 50 0 0 1 90 90 A 70 70 0 0 1 180 45 A 85 85 0 0 1 310 55 A 65 65 0 0 1 390 115 A 50 50 0 0 1 400 175 A 45 45 0 0 1 360 205 L 95 205 A 45 45 0 0 1 60 160 Z" fill="url(#cloudGrad2)" />
                    <defs>
                        <linearGradient id="cloudGrad2" x1="100%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#ffffff" stop-opacity="0.4" />
                            <stop offset="50%" stop-color="#c084fc" stop-opacity="0.2" />
                            <stop offset="100%" stop-color="#06b6d4" stop-opacity="0.1" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>

            <!-- Fluffy Cloud Layer 3 (Pastki chap momiq bulut) -->
            <div class="absolute bottom-[5%] left-[5%] w-[450px] h-[210px] opacity-65 animate-[cloudDrift3_36s_linear_infinite]">
                <svg viewBox="0 0 400 200" class="w-full h-full filter drop-shadow-[0_15px_30px_rgba(6,182,212,0.15)]">
                    <path d="M 50 140 A 40 40 0 0 1 70 80 A 60 60 0 0 1 150 40 A 70 70 0 0 1 270 50 A 55 55 0 0 1 340 100 A 45 45 0 0 1 350 150 A 40 40 0 0 1 310 180 L 80 180 A 40 40 0 0 1 50 140 Z" fill="url(#cloudGrad3)" />
                    <defs>
                        <linearGradient id="cloudGrad3" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ffffff" stop-opacity="0.3" />
                            <stop offset="70%" stop-color="#38bdf8" stop-opacity="0.15" />
                            <stop offset="100%" stop-color="#818cf8" stop-opacity="0.08" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>

            <!-- Soft floating cloud puffs (Kichik suzuvchi momiq bulutlar) -->
            <div class="absolute top-[20%] right-[20%] w-32 h-16 bg-white/20 rounded-full blur-xl animate-[puffFloat1_8s_easeInOut_infinite]"></div>
            <div class="absolute bottom-[25%] right-[35%] w-40 h-20 bg-cyan-400/20 rounded-full blur-2xl animate-[puffFloat2_10s_easeInOut_infinite]"></div>
            <div class="absolute top-[55%] left-[18%] w-36 h-18 bg-purple-400/20 rounded-full blur-2xl animate-[puffFloat1_9s_easeInOut_infinite]"></div>
        </div>

        <!-- Top Header Navigation (Ixcham) -->
        <header class="w-full max-w-4xl flex items-center justify-between relative z-10">
            <Link :href="route('landing')" class="flex items-center gap-2.5 group">
                <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-cyan-400 via-purple-500 to-amber-400 p-[1px] shadow-[0_0_15px_rgba(6,182,212,0.4)] group-hover:scale-105 transition-transform">
                    <div class="w-full h-full bg-[#0b0b18] rounded-[11px] flex items-center justify-center font-black text-xs text-cyan-300">
                        IQS
                    </div>
                </div>
                <div class="flex flex-col">
                    <span class="text-base font-black tracking-tight text-white flex items-center gap-1.5">
                        IQS ERP
                        <span class="px-1.5 py-0.2 rounded-full bg-cyan-500/20 text-[9px] font-bold text-cyan-300">IT Cloud</span>
                    </span>
                </div>
            </Link>

            <div class="flex items-center gap-2">
                <Link :href="route('promo.offers')" class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-semibold text-gray-300 transition-colors">
                    Uy sotib olish ↗
                </Link>
                <Link :href="route('landing')" class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 text-xs font-bold text-white transition-all shadow-sm">
                    ← Bosh sahifa
                </Link>
            </div>
        </header>

        <!-- Main Compact & Sleek Login Card -->
        <main class="w-full max-w-[390px] my-auto relative z-10 py-2">
            
            <div class="w-full rounded-[32px] bg-[#0c0c18]/85 backdrop-blur-2xl border border-white/15 shadow-[0_20px_50px_rgba(0,0,0,0.8)] p-6 sm:p-7 relative overflow-hidden transition-all duration-300">
                
                <!-- Glowing Top Accent Line -->
                <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-cyan-400 to-purple-500"></div>

                <!-- Cute Floating Fluffy Cloud Mascot (Momiq Bulutcha) -->
                <div class="flex flex-col items-center justify-center mb-4">
                    
                    <div 
                        ref="mascotRef" 
                        class="relative w-32 h-24 flex items-center justify-center transition-transform duration-300 animate-[cloudBob_4s_easeInOut_infinite]"
                        :class="isShy ? 'scale-110' : ''"
                    >
                        <svg viewBox="0 0 240 160" class="w-full h-full drop-shadow-[0_10px_20px_rgba(6,182,212,0.3)]">
                            <!-- Soft Cloud Shadow -->
                            <ellipse cx="120" cy="148" rx="65" ry="8" fill="#000000" opacity="0.4" />

                            <!-- Fluffy 3D Cloud Body (Haqiqiy Momiq Bulut) -->
                            <path 
                                d="M 60 115 
                                   A 28 28 0 0 1 55 65 
                                   A 35 35 0 0 1 100 38 
                                   A 42 42 0 0 1 155 38 
                                   A 35 35 0 0 1 195 65 
                                   A 28 28 0 0 1 190 115 
                                   A 22 22 0 0 1 165 132 
                                   L 80 132 
                                   A 22 22 0 0 1 60 115 Z" 
                                fill="#ffffff" 
                                stroke="#38bdf8" 
                                stroke-width="3" 
                                stroke-linejoin="round"
                            />

                            <!-- Cute Cheek Blushes -->
                            <ellipse cx="78" cy="95" rx="9" ry="5" fill="#fda4af" opacity="0.85" />
                            <ellipse cx="162" cy="95" rx="9" ry="5" fill="#fda4af" opacity="0.85" />

                            <!-- Cute Smile -->
                            <path 
                                :d="isShy ? 'M 114 98 Q 120 94 126 98' : 'M 113 94 Q 120 104 127 94'" 
                                fill="none" 
                                stroke="#0f172a" 
                                stroke-width="2.8" 
                                stroke-linecap="round" 
                            />

                            <!-- EYES (Interactive Cursor Tracking) -->
                            <!-- Left Eye Socket -->
                            <ellipse cx="92" cy="78" rx="12" ry="14" fill="#0f172a" stroke="#38bdf8" stroke-width="1.2" />
                            <!-- Left Eye Pupil -->
                            <g v-show="!isShy" :style="{ transform: leftPupilTransform, transformOrigin: '92px 78px' }" class="transition-transform duration-75">
                                <circle cx="92" cy="78" r="6.5" fill="#0284c7" />
                                <circle cx="89.5" cy="75" r="2.8" fill="#ffffff" />
                                <circle cx="94.5" cy="80.5" r="1.2" fill="#ffffff" />
                            </g>
                            <!-- Left Eye Shy Arc (Parol kiritilganda) -->
                            <path v-show="isShy" d="M 82 78 Q 92 68 102 78" fill="none" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round" />

                            <!-- Right Eye Socket -->
                            <ellipse cx="148" cy="78" rx="12" ry="14" fill="#0f172a" stroke="#38bdf8" stroke-width="1.2" />
                            <!-- Right Eye Pupil -->
                            <g v-show="!isShy" :style="{ transform: rightPupilTransform, transformOrigin: '148px 78px' }" class="transition-transform duration-75">
                                <circle cx="148" cy="78" r="6.5" fill="#0284c7" />
                                <circle cx="145.5" cy="75" r="2.8" fill="#ffffff" />
                                <circle cx="150.5" cy="80.5" r="1.2" fill="#ffffff" />
                            </g>
                            <!-- Right Eye Shy Arc (Parol kiritilganda) -->
                            <path v-show="isShy" d="M 138 78 Q 148 68 158 78" fill="none" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round" />

                            <!-- Shy Cloud Paws (Parol yozilganda ko'zni yopuvchi momiq qo'lchalar) -->
                            <g v-show="isShy" class="animate-bounce">
                                <circle cx="76" cy="78" r="13" fill="#f8fafc" stroke="#38bdf8" stroke-width="2.5" />
                                <circle cx="164" cy="78" r="13" fill="#f8fafc" stroke="#38bdf8" stroke-width="2.5" />
                            </g>
                        </svg>
                    </div>

                    <div class="text-center">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-cyan-300 bg-cyan-950/70 px-2.5 py-0.5 rounded-full border border-cyan-500/30">
                            {{ isShy ? '🙈 Qaramayapman!' : '☁️ IT Cloud Bulutcha' }}
                        </span>
                        <h2 class="text-xl sm:text-2xl font-black text-white mt-1.5">Tizimga Kirish</h2>
                    </div>

                </div>

                <!-- Status Feedback Message -->
                <div v-if="status" class="mb-4 p-2.5 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 text-xs text-center font-medium">
                    {{ status }}
                </div>

                <!-- Login Form -->
                <form @submit.prevent="submit" class="space-y-3.5 relative z-10">
                    
                    <!-- Email Input -->
                    <div>
                        <label for="email" class="block text-[11px] font-bold text-gray-300 uppercase tracking-wider mb-1">Email Manzil</label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-2.5 text-gray-400 text-sm">✉️</span>
                            <input
                                id="email"
                                type="email"
                                v-model="form.email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="admin@itcloud.uz"
                                class="w-full pl-10 pr-3.5 py-2.5 bg-black/50 border border-white/15 text-white placeholder-gray-500 rounded-xl focus:border-cyan-400 focus:shadow-[0_0_15px_rgba(6,182,212,0.3)] focus:outline-none transition-all text-xs font-mono"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1 text-[11px] text-red-400 font-medium">{{ form.errors.email }}</p>
                    </div>

                    <!-- Password Input -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="password" class="block text-[11px] font-bold text-gray-300 uppercase tracking-wider">Parol</label>
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-[11px] text-cyan-400 hover:text-cyan-300 transition-colors hover:underline"
                            >
                                Parolni unutdingizmi?
                            </Link>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 top-2.5 text-gray-400 text-sm">🔒</span>
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password"
                                @focus="onPasswordFocus"
                                @blur="onPasswordBlur"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full pl-10 pr-16 py-2.5 bg-black/50 border border-white/15 text-white placeholder-gray-500 rounded-xl focus:border-cyan-400 focus:shadow-[0_0_15px_rgba(6,182,212,0.3)] focus:outline-none transition-all text-xs font-mono"
                            />
                            <button
                                type="button"
                                @click="toggleShowPassword"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 px-2 py-0.5 rounded bg-white/10 hover:bg-white/20 text-gray-300 hover:text-white transition-colors text-[10px] font-bold font-mono"
                                tabindex="-1"
                            >
                                {{ showPassword ? 'Yashir' : 'Ko\'rsat' }}
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-[11px] text-red-400 font-medium">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between text-xs pt-0.5">
                        <label class="flex items-center gap-2 cursor-pointer select-none text-gray-300 hover:text-white">
                            <input 
                                type="checkbox" 
                                v-model="form.remember" 
                                class="w-3.5 h-3.5 rounded bg-black/40 border-white/20 text-cyan-500 focus:ring-cyan-400 focus:ring-offset-0 transition-all cursor-pointer"
                            />
                            <span class="text-xs">Meni eslab qol</span>
                        </label>
                        <span class="text-[10px] text-gray-500 font-mono">SSL Himoya</span>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-1.5">
                        <button
                            type="submit"
                            :disabled="form.processing || isPortalOpening"
                            class="w-full py-3 rounded-xl bg-gradient-to-r from-cyan-500 via-blue-600 to-purple-600 hover:from-cyan-400 hover:to-purple-500 text-white font-extrabold text-sm uppercase tracking-wider shadow-[0_0_25px_rgba(6,182,212,0.5)] hover:scale-[1.02] active:scale-[0.98] transition-all disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span v-if="form.processing || isPortalOpening" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>{{ isPortalOpening ? 'Bulutlar ochilmoqda...' : 'Tizimga Kirish' }}</span>
                            <span v-if="!form.processing && !isPortalOpening">→</span>
                        </button>
                    </div>

                </form>

            </div>

        </main>

        <!-- Footer Tag -->
        <footer class="w-full max-w-4xl flex items-center justify-between text-[11px] text-gray-500 pt-2 border-t border-white/5 relative z-10">
            <div class="flex items-center gap-2">
                <span class="text-white font-medium">Sherdor</span> • <span class="text-white font-medium">Hayot House</span> • <span class="text-white font-medium">Baraka</span>
            </div>
            <div>
                © 2026 <a href="https://itcloud.uz" target="_blank" class="text-cyan-400 font-semibold hover:underline">ITCloud.uz</a>
            </div>
        </footer>

        <!-- ========================================================= -->
        <!-- MOMIQ BULUTLAR TARQALISHI VA TIZIM OCHILISHI (PORTAL) -->
        <!-- ========================================================= -->
        <div 
            v-if="isPortalOpening" 
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#070710] animate-[fadeIn_0.3s_ease-out] overflow-hidden"
        >
            <!-- Center Light Portal -->
            <div class="absolute w-[600px] h-[600px] rounded-full bg-gradient-to-tr from-cyan-400 via-blue-500 to-purple-600 blur-[130px] animate-[pulseLight_1.4s_infinite_alternate]"></div>
            
            <!-- Left Volumetric Fluffy Cloud Parting -->
            <div class="absolute -left-32 top-0 bottom-0 w-[65vw] bg-gradient-to-r from-white via-cyan-100/90 to-transparent rounded-r-full blur-2xl animate-[partLeft_1.6s_forwards_ease-in-out]"></div>
            
            <!-- Right Volumetric Fluffy Cloud Parting -->
            <div class="absolute -right-32 top-0 bottom-0 w-[65vw] bg-gradient-to-l from-white via-purple-100/90 to-transparent rounded-l-full blur-2xl animate-[partRight_1.6s_forwards_ease-in-out]"></div>

            <!-- Top Fluffy Cloud Parting -->
            <div class="absolute -top-32 left-0 right-0 h-[45vh] bg-gradient-to-b from-white via-sky-100/80 to-transparent rounded-b-full blur-2xl animate-[partUp_1.6s_forwards_ease-in-out]"></div>

            <!-- Revealing Content -->
            <div class="relative z-20 flex flex-col items-center justify-center text-center p-8 animate-[scaleReveal_1.1s_ease-out]">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-cyan-400 to-purple-600 p-1 mb-4 shadow-[0_0_40px_rgba(6,182,212,0.8)]">
                    <div class="w-full h-full bg-[#0a0a16] rounded-[14px] flex items-center justify-center text-2xl">
                        ✨
                    </div>
                </div>
                <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight mb-2">
                    Xush Kelibsiz!
                </h1>
                <p class="text-cyan-300 font-mono text-xs tracking-widest uppercase">
                    Bulutlar tarqalmoqda • Tizim ochilmoqda...
                </p>
            </div>
        </div>

    </div>
</template>

<style scoped>
@keyframes cloudBob {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

@keyframes cloudDrift1 {
    0% { transform: translateX(-50px) translateY(0); }
    50% { transform: translateX(60px) translateY(-15px); }
    100% { transform: translateX(-50px) translateY(0); }
}

@keyframes cloudDrift2 {
    0% { transform: translateX(40px) translateY(0); }
    50% { transform: translateX(-50px) translateY(12px); }
    100% { transform: translateX(40px) translateY(0); }
}

@keyframes cloudDrift3 {
    0% { transform: translateX(-30px) translateY(0); }
    50% { transform: translateX(40px) translateY(-10px); }
    100% { transform: translateX(-30px) translateY(0); }
}

@keyframes puffFloat1 {
    0%, 100% { transform: translateY(0) scale(1); opacity: 0.2; }
    50% { transform: translateY(-20px) scale(1.15); opacity: 0.4; }
}

@keyframes puffFloat2 {
    0%, 100% { transform: translateY(0) scale(1); opacity: 0.15; }
    50% { transform: translateY(25px) scale(1.2); opacity: 0.35; }
}

@keyframes partLeft {
    0% { transform: translateX(0) scale(1); opacity: 1; }
    100% { transform: translateX(-110%) scale(1.4); opacity: 0; }
}

@keyframes partRight {
    0% { transform: translateX(0) scale(1); opacity: 1; }
    100% { transform: translateX(110%) scale(1.4); opacity: 0; }
}

@keyframes partUp {
    0% { transform: translateY(0) scale(1); opacity: 1; }
    100% { transform: translateY(-110%) scale(1.4); opacity: 0; }
}

@keyframes pulseLight {
    0% { transform: scale(0.8); opacity: 0.6; }
    100% { transform: scale(1.4); opacity: 1; }
}

@keyframes scaleReveal {
    0% { transform: scale(0.8); opacity: 0; }
    60% { transform: scale(1.04); opacity: 1; }
    100% { transform: scale(1); opacity: 1; }
}

/* Chrome, Safari, Edge autofill style override */
input:-webkit-autofill,
input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, 
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 1000px rgba(12, 12, 24, 0.95) inset !important;
    -webkit-text-fill-color: #ffffff !important;
    transition: background-color 5000s ease-in-out 0s;
}

input:autofill {
    background-color: rgba(12, 12, 24, 0.95) !important;
    color: #ffffff !important;
}
</style>
