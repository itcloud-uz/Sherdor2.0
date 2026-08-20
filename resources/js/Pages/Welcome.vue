<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: { type: Boolean, default: true },
    canRegister: { type: Boolean, default: false },
    laravelVersion: { type: String, default: '' },
    phpVersion: { type: String, default: '' },
});

const showDemo = ref(false);
</script>

<template>
    <Head title="IQS ERP - Qurilish va Sotuv Boshqaruv Tizimi" />
    
    <div class="min-h-screen bg-[#07070a] text-white relative overflow-hidden font-sans">
        
        <!-- Ambient Glowing Background -->
        <div class="fixed top-[-10%] left-[20%] w-[500px] h-[500px] rounded-full bg-amber-500/10 blur-[140px] pointer-events-none"></div>
        <div class="fixed bottom-[-10%] right-[10%] w-[600px] h-[600px] rounded-full bg-purple-600/10 blur-[160px] pointer-events-none"></div>

        <!-- Sticky Header / Navbar -->
        <header class="sticky top-0 z-50 backdrop-blur-xl bg-[#07070a]/80 border-b border-white/10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-amber-500 via-purple-600 to-blue-500 flex items-center justify-center p-0.5 shadow-[0_0_20px_rgba(245,158,11,0.4)]">
                        <div class="w-full h-full bg-[#0d0d12] rounded-[14px] flex items-center justify-center font-black text-xl tracking-tighter text-amber-400">
                            IQS
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-2xl font-black tracking-tight text-white flex items-center gap-1.5">
                            IQS <span class="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 font-semibold uppercase">ERP</span>
                        </span>
                        <span class="text-[10px] text-gray-400 font-medium tracking-wider uppercase">Qur & Sot • Men Quraman va Sotaman</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-4">
                    <Link v-if="canLogin" :href="route('login')" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-black font-bold text-sm shadow-[0_0_20px_rgba(245,158,11,0.4)] hover:shadow-[0_0_30px_rgba(245,158,11,0.6)] transition-all transform hover:scale-[1.02]">
                        Kirish
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Hero -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 flex flex-col items-center text-center relative z-10">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-amber-500/40 bg-amber-500/10 backdrop-blur-md mb-8 shadow-[0_0_20px_rgba(245,158,11,0.2)]">
                <span class="text-xs sm:text-sm font-semibold text-amber-300">Yangi Avlod IQS Ekotizimi</span>
            </div>

            <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight max-w-4xl leading-tight mb-8">
                Ko’p Qavatli Turar-joy Binolari Qurilishini <br />
                <span class="bg-clip-text text-transparent bg-gradient-to-r from-amber-400 via-purple-400 to-cyan-400">
                    Avtomatlashtiring!
                </span>
            </h1>

            <p class="text-lg text-gray-300 max-w-2xl mb-12">
                IQS ERP — bu qurilish, smeta, ombor, moliya va sotuv bo‘limlari uchun barcha jarayonlarni yagona tizimda boshqarish imkonini beruvchi mukammal dasturiy ta'minot.
            </p>

            <div class="flex flex-wrap gap-4 justify-center">
                <Link v-if="canLogin" :href="route('login')" class="px-8 py-4 rounded-2xl bg-gradient-to-r from-amber-500 to-amber-600 text-black font-extrabold text-lg shadow-[0_0_30px_rgba(245,158,11,0.5)] hover:scale-105 transition-all">
                    Tizim bilan tanishish
                </Link>
            </div>
        </main>
    </div>
</template>
