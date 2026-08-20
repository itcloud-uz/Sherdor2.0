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

const showPassword = ref(false);
const isShy = ref(false);
const mascotRef = ref(null);
const leftPupilTransform = ref('');
const rightPupilTransform = ref('');

const maxMove = 7; // Max pupil offset distance in px

const handleMouseMove = (e) => {
    if (isShy.value || !mascotRef.value) return;

    const rect = mascotRef.value.getBoundingClientRect();
    const mascotCenterX = rect.left + rect.width / 2;
    const mascotCenterY = rect.top + rect.height / 2;

    const angle = Math.atan2(e.clientY - mascotCenterY, e.clientX - mascotCenterX);
    const distance = Math.hypot(e.clientX - mascotCenterX, e.clientY - mascotCenterY);
    const dynamicLimit = Math.min(distance / 150, 1) * maxMove;

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
        isShy.value = false; // Peek when showing password
    } else {
        isShy.value = true;
    }
};

onMounted(() => {
    window.addEventListener('mousemove', handleMouseMove);
});

onUnmounted(() => {
    window.removeEventListener('mousemove', handleMouseMove);
});
</script>

<template>
    <Head title="Tizimga Kirish - IT Cloud / IQS ERP" />

    <div class="bg-[#FEFEFE] min-h-screen flex flex-col md:flex-row font-sans overflow-x-hidden text-[#030303] selection:bg-[#D0D91A] selection:text-[#030303]">

        <!-- CHAP BLOK: Yoqimtoy "IT Cloud" (Kuchukcha-Bulut) Interaktiv Mascot Animatsiyasi -->
        <div class="flex-1 flex flex-col items-center justify-between bg-gradient-to-br from-slate-50 via-white to-[#EAEAEA] p-8 md:p-12 border-b md:border-b-0 md:border-r border-[#E9E9E9] min-h-[420px] md:min-h-screen relative overflow-hidden">
            
            <!-- Top Navigation / Brand Title -->
            <div class="w-full flex items-center justify-between relative z-10">
                <Link :href="route('landing')" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-2xl bg-[#030303] text-white flex items-center justify-center font-black text-sm shadow-md group-hover:scale-105 transition-transform">
                        ITC
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xl font-black tracking-tight text-[#030303]">
                            IT <span class="text-[#445122]">Cloud</span>
                        </span>
                        <span class="text-[10px] text-gray-500 font-semibold tracking-wider uppercase">IQS ERP Platformasi</span>
                    </div>
                </Link>

                <Link :href="route('landing')" class="px-4 py-2 rounded-xl bg-white hover:bg-gray-100 border border-[#E9E9E9] text-xs font-bold text-[#030303] transition-all shadow-sm flex items-center gap-1">
                    <span>←</span> Bosh sahifa
                </Link>
            </div>

            <!-- Mascot Showcase Center -->
            <div class="my-auto flex flex-col items-center justify-center relative z-10 text-center py-6">
                
                <div class="mb-4">
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-[#D0D91A]/20 border border-[#D0D91A] text-[#445122] text-xs font-extrabold uppercase tracking-wider">
                        🐾 Aqlli Mascot
                    </span>
                    <h1 class="text-3xl md:text-4xl font-black tracking-tight text-[#030303] mt-2">
                        IT <span class="text-[#445122]">Cloud</span> Buluti
                    </h1>
                    <p class="text-gray-500 text-sm font-medium mt-1 max-w-sm">
                        {{ isShy ? '🙈 Qaramayapman! Parolingiz xavfsiz himoyada...' : '👀 Sizning interaktiv aqlli yordamchingiz' }}
                    </p>
                </div>

                <!-- Yoqimtoy "Kuchukcha-Bulut" Mascot (Interactive SVG) -->
                <div ref="mascotRef" class="relative w-72 h-72 md:w-84 md:h-84 flex items-center justify-center transition-all duration-300 transform" :class="{ 'scale-105': isShy }">
                    
                    <svg viewBox="0 0 400 400" class="w-full h-full drop-shadow-xl" :class="{ 'shy-eyes': isShy }">
                        <!-- Orqa fondagi soya -->
                        <ellipse cx="200" cy="315" rx="95" ry="16" fill="#030303" opacity="0.08" />

                        <!-- Kuchukcha Quloqlari (It-Bulut konsepti) -->
                        <!-- Chap Quloq -->
                        <path d="M 100 130 C 45 105, 35 205, 95 215 Z" fill="#94A3B8" stroke="#030303" stroke-width="8" stroke-linejoin="round" />
                        <!-- O'ng Quloq -->
                        <path d="M 300 130 C 355 105, 365 205, 305 215 Z" fill="#94A3B8" stroke="#030303" stroke-width="8" stroke-linejoin="round" />

                        <!-- Bulut ko'rinishidagi tana (Cloud Body) -->
                        <path d="M 120 220 
                                 A 45 45 0 0 1 150 130 
                                 A 65 65 0 0 1 250 130 
                                 A 45 45 0 0 1 280 220 
                                 A 45 45 0 0 1 250 280 
                                 A 65 65 0 0 1 150 280 
                                 A 45 45 0 0 1 120 220 Z" 
                              fill="#FFFFFF" stroke="#030303" stroke-width="8" stroke-linejoin="round" />

                        <!-- Kuchukcha burni va og'zi -->
                        <ellipse cx="200" cy="215" rx="14" ry="10" fill="#030303" />
                        <path d="M 190 222 Q 200 235 210 222" fill="none" stroke="#030303" stroke-width="4" stroke-linecap="round" />
                        <!-- Yoqimtoy tilchasi -->
                        <path d="M 195 224 Q 200 238 205 224 Z" fill="#FDA4AF" stroke="#030303" stroke-width="2" />

                        <!-- KO'ZLAR (Interaktiv Qism) -->
                        <!-- Chap Ko'z Soketi -->
                        <circle cx="160" cy="180" r="20" class="open-eye-bg" fill="#FFFFFF" stroke="#030303" stroke-width="6" />
                        <!-- Chap Ko'z Qorachig'i (Kuzatuvchi) -->
                        <circle 
                            v-show="!isShy"
                            cx="160" 
                            cy="180" 
                            r="8" 
                            fill="#030303" 
                            class="eye-pupil"
                            :style="{ transform: leftPupilTransform, transformOrigin: '160px 180px' }"
                        />
                        <!-- Berkingan ko'z chizig'i (Parol kiritilganda / Uyalish) -->
                        <path v-show="isShy" d="M 145 180 Q 160 195 175 180" fill="none" stroke="#030303" stroke-width="6" stroke-linecap="round" class="closed-eye-path animate-bounce" />

                        <!-- O'ng Ko'z Soketi -->
                        <circle cx="240" cy="180" r="20" class="open-eye-bg" fill="#FFFFFF" stroke="#030303" stroke-width="6" />
                        <!-- O'ng Ko'z Qorachig'i (Kuzatuvchi) -->
                        <circle 
                            v-show="!isShy"
                            cx="240" 
                            cy="180" 
                            r="8" 
                            fill="#030303" 
                            class="eye-pupil"
                            :style="{ transform: rightPupilTransform, transformOrigin: '240px 180px' }"
                        />
                        <!-- Berkingan ko'z chizig'i (Parol kiritilganda / Uyalish) -->
                        <path v-show="isShy" d="M 225 180 Q 240 195 255 180" fill="none" stroke="#030303" stroke-width="6" stroke-linecap="round" class="closed-eye-path animate-bounce" />

                        <!-- Yonoqlaridagi yoqimtoy pushti dog'lar -->
                        <circle cx="130" cy="215" r="9" fill="#FDA4AF" opacity="0.6" />
                        <circle cx="270" cy="215" r="9" fill="#FDA4AF" opacity="0.6" />
                    </svg>

                </div>

            </div>

            <!-- Bottom Footnote -->
            <div class="w-full flex items-center justify-between text-xs text-gray-500 pt-4 border-t border-[#E9E9E9] relative z-10">
                <span>Sherdor • Hayot House • Baraka</span>
                <span class="font-bold text-[#445122]">Dribbble UI Edition</span>
            </div>

        </div>

        <!-- O'NG BLOK: Minimalistik & Toza Login Formasi -->
        <div class="flex-1 flex items-center justify-center p-8 md:p-16 bg-[#FEFEFE]">
            <div class="w-full max-w-md space-y-8">
                
                <div class="space-y-2">
                    <div class="inline-block px-3 py-1 rounded-full bg-[#E9E9E9] text-xs font-extrabold uppercase text-[#030303] tracking-wider mb-1">
                        Xavfsiz Kirish
                    </div>
                    <h2 class="text-3xl md:text-4xl font-black tracking-tight text-[#030303]">Tizimga kirish</h2>
                    <p class="text-gray-500 font-medium text-sm">Davom etish uchun hisobingiz ma'lumotlarini kiriting.</p>
                </div>

                <!-- Status Feedback -->
                <div v-if="status" class="p-4 rounded-xl bg-[#D0D91A]/20 border border-[#D0D91A] text-[#445122] text-sm font-semibold text-center">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    
                    <!-- Email Input -->
                    <div class="space-y-2">
                        <label for="email" class="text-xs font-bold tracking-wide uppercase text-gray-700 block">E-pochta manzili</label>
                        <input 
                            id="email" 
                            type="email" 
                            v-model="form.email"
                            required 
                            autofocus
                            autocomplete="username"
                            placeholder="admin@itcloud.uz" 
                            class="w-full px-4 py-3.5 border-2 border-[#E9E9E9] rounded-xl focus:border-[#030303] focus:outline-none transition bg-[#FEFEFE] text-[#030303] font-medium"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600 font-semibold">{{ form.errors.email }}</p>
                    </div>

                    <!-- Parol Input -->
                    <div class="space-y-2 relative">
                        <div class="flex justify-between items-center">
                            <label for="password" class="text-xs font-bold tracking-wide uppercase text-gray-700">Parol</label>
                            <Link 
                                v-if="canResetPassword"
                                :href="route('password.request')" 
                                class="text-xs font-bold text-[#445122] hover:underline"
                            >
                                Parolni unutdingizmi?
                            </Link>
                        </div>
                        <div class="relative">
                            <input 
                                id="password" 
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password"
                                @focus="onPasswordFocus"
                                @blur="onPasswordBlur"
                                required 
                                autocomplete="current-password"
                                placeholder="••••••••" 
                                class="w-full px-4 py-3.5 pr-20 border-2 border-[#E9E9E9] rounded-xl focus:border-[#030303] focus:outline-none transition bg-[#FEFEFE] text-[#030303] font-medium"
                            />
                            <button 
                                type="button" 
                                @click="toggleShowPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 px-2.5 py-1 rounded-lg bg-[#E9E9E9] text-gray-700 hover:text-[#030303] font-bold text-[11px] uppercase tracking-wider transition-colors"
                            >
                                {{ showPassword ? 'Yashir' : "Ko'rsat" }}
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-600 font-semibold">{{ form.errors.password }}</p>
                    </div>

                    <!-- Eslab qolish -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center space-x-2.5 cursor-pointer select-none">
                            <input 
                                type="checkbox" 
                                v-model="form.remember"
                                class="w-4 h-4 rounded border-gray-300 text-[#445122] focus:ring-[#445122] transition-colors"
                            />
                            <span class="text-sm font-semibold text-gray-700">Meni eslab qol</span>
                        </label>

                        <Link :href="route('promo.offers')" class="text-xs font-bold text-[#445122] hover:underline">
                            Uy xarid qilish ↗
                        </Link>
                    </div>

                    <!-- Kirish Tugmasi (Dribbble Palitrasida: #D0D91A / Limon Sariq) -->
                    <button 
                        type="submit" 
                        :disabled="form.processing"
                        class="w-full py-4 px-6 rounded-xl font-black text-base tracking-wide transition transform hover:scale-[1.01] active:scale-95 shadow-md btn-accent flex items-center justify-center gap-2 disabled:opacity-50"
                    >
                        <span v-if="form.processing" class="w-5 h-5 border-2 border-black border-t-transparent rounded-full animate-spin"></span>
                        <span>KIRISH</span>
                        <span v-if="!form.processing">→</span>
                    </button>

                </form>

                <!-- Footer Tag -->
                <div class="pt-6 border-t border-[#E9E9E9] text-center">
                    <p class="text-xs text-gray-500 font-medium">
                        © 2026 <a href="https://itcloud.uz" target="_blank" class="font-bold text-[#030303] hover:underline">ITCloud.uz</a> • IQS ERP Platformasi
                    </p>
                </div>

            </div>
        </div>

    </div>
</template>

<style scoped>
.eye-pupil {
    transition: transform 0.05s ease-out;
}

/* Asosiy urg'u berilgan ranglar (Dribbble Style) */
.btn-accent {
    background-color: #D0D91A;
    color: #030303;
}

.btn-accent:hover {
    background-color: #b5bd14;
}

/* Chrome, Safari, Edge autofill style override */
input:-webkit-autofill,
input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, 
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 1000px #FEFEFE inset !important;
    -webkit-text-fill-color: #030303 !important;
    transition: background-color 5000s ease-in-out 0s;
}

input:autofill {
    background-color: #FEFEFE !important;
    color: #030303 !important;
}
</style>
