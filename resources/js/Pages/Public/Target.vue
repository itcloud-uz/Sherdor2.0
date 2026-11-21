<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({
            appName: 'Sher-Dor',
            companyName: 'Sher-Dor TJM',
            phone: '(97) 708-44-44, (93) 573-29-29',
            address: 'Urgut tuman, Do\'stlik mahallasi, Navoiy-Shoh ko\'chasi №3-uy',
            fbPixelId: null
        })
    }
});

// Scene list definition (GTA VI Style Continuous Flow)
const scenes = [
    {
        id: 'scene-01',
        number: '01',
        tag: 'INTRODUKSIYA',
        title: 'SHER-DOR RESIDENCE',
        subtitle: 'Samarqandning Yangi Me\'moriy Durdonasi',
        bgImage: '/storage/showroom/6w0xuYXlUsHUbM0Lr1PMrZGFTyYBxEwhatyUsR3C.jpg',
        fallbackBg: '/images/tropical-future-city.jpg'
    },
    {
        id: 'scene-02',
        number: '02',
        tag: 'MONUMENTAL ME\'MORCHILIK',
        title: '4 BLOK • 8 QAVAT • 9 BALLIK SEYSMIK XAVFSIZLIK',
        subtitle: 'Pishiq g\'isht, monolit karkas va abadiy sifat',
        bgImage: '/storage/showroom/DP6yWc7mh3CcjOVpbwmFjfO67hkDbRha2Oiy5sUJ.jpg',
        fallbackBg: '/images/clean-building-facade.jpg'
    },
    {
        id: 'scene-03',
        number: '03',
        tag: 'HASHAMATLI INTERYER',
        title: 'YORUG\'LIK, KENG MAYDON VA ERKINLIK',
        subtitle: '3.2 metr baland shiftlar va panoramik derazalar',
        bgImage: '/modern_apartment_render.png',
        fallbackBg: '/modern_apartment_render.png'
    },
    {
        id: 'scene-04',
        number: '04',
        tag: '3D XONADONLAR VA PLANLAR',
        title: 'HAR BIR OILANING ORZUSI UCHUN REJA',
        subtitle: '1, 2, 3 xonali va muhtasham mansardalar',
        bgImage: '/storage/showroom/sdHFjNqfgsEvOiwidtf9Zn21KinE24MVwlvOgghZ.jpg',
        fallbackBg: '/images/clean-building-facade.jpg'
    },
    {
        id: 'scene-05',
        number: '05',
        tag: 'EKO-HUDUD VA XAVFSIZLIK',
        title: 'AVTOMOBILLARDAN HOLI YOPIQ HOVLI',
        subtitle: '24/7 video-kuzatuv, yerosti parkingi va yashil bog\'',
        bgImage: '/storage/showroom/mFPwnci4buGDaK8bCKdI4LbMYLpBxyoLgtmOGHEU.jpg',
        fallbackBg: '/images/tropical-future-city.jpg'
    },
    {
        id: 'scene-06',
        number: '06',
        tag: 'STRATEGIK JOYLASHUV',
        title: 'URGUT VA SAMARQAND MARKAZIDA',
        subtitle: 'Barcha asosiy maskanlarga 2-5 daqiqalik masofa',
        bgImage: '/storage/showroom/urgut_situatsiya.jpg',
        fallbackBg: '/storage/showroom/urgut_situatsiya.jpg'
    },
    {
        id: 'scene-07',
        number: '07',
        tag: 'ORZUINGIZDAGI XONADON',
        title: 'BUGUN QAROR QILING • XARID QILING',
        subtitle: 'Qulay to\'lov shartlari va 3D tanlash imkoniyati',
        bgImage: '/storage/showroom/VpPIgHp32Tr49WWmFcN2WgAbju284kFmQxYUsdBu.jpg',
        fallbackBg: '/images/clean-building-facade.jpg'
    }
];

const currentSceneIndex = ref(0);

// Apartment Plan Selector State (Scene 04)
const activePlanTab = ref(0);
const planTabs = [
    {
        title: '1 Xonali',
        area: '40.65 m²',
        description: 'Qulay va shinam zamonaviy studiya uslubidagi xonadon. Keng oshxona, shinam mehmonxona va manzarali balkon.',
        rooms: 1,
        highlights: ['Panoramik balkon', 'Keng oshxona', 'Optimal vanna', 'Ergonomik koridor']
    },
    {
        title: '2 Xonali',
        area: '65.35 - 76.42 m²',
        description: 'Yosh va zamonaviy oilalar uchun ideal tanlov. Katta mehmonxona, shinam yotoqxona va keng oshxona.',
        rooms: 2,
        highlights: ['Alohida yotoqxona', 'Katta yorug\' zal', 'Oshxona + ovqatlanish zonasi', 'Keng balkon']
    },
    {
        title: '3 Xonali',
        area: '101.08 m²',
        description: 'Katta va nufuzli oilalar uchun hashamatli yashash maydoni. Master-bedroom, bolalar xonasi va keng oilaviy zal.',
        rooms: 3,
        highlights: ['Master-bedroom (shaxsiy vanna bilan)', '2 ta sanuzel', 'Katta oilaviy zal', '2 ta balkon']
    },
    {
        title: 'Mansarda / Penthouse',
        area: '51.97 - 98.54 m²',
        description: '8-qavatdagi eng nufuzli xonadonlar. Betakror shahar manzarasi, baland shiftlar va romantik terassa muhiti.',
        rooms: '2-3',
        highlights: ['Baland shiftdorlik', 'Romantik terassa manzarasi', 'Eksklyuziv reja', 'Eng yuqori qulaylik']
    }
];

// Lead Form State
const leadForm = ref({
    full_name: '',
    phone: '',
    room_preference: '2 xonali (65 - 76 m²)',
    comment: '',
    source: 'Instagram Target'
});
const isSubmitting = ref(false);
const submitSuccess = ref(false);
const submitError = ref('');

const submitLead = async () => {
    if (!leadForm.value.phone) {
        submitError.value = 'Iltimos, telefon raqamingizni kiriting!';
        return;
    }
    isSubmitting.value = true;
    submitError.value = '';
    
    try {
        const payload = {
            full_name: leadForm.value.full_name || 'Instagram Foydalanuvchisi',
            phone: leadForm.value.phone,
            region: 'Samarqand viloyati',
            district: 'Urgut tumani',
            comment: `[Instagram Target] Qiziqish: ${leadForm.value.room_preference}. ${leadForm.value.comment || ''}`,
            source: leadForm.value.source || 'Instagram Target'
        };
        await axios.post(route('api.leads.store'), payload);
        submitSuccess.value = true;

        if (props.settings?.fbPixelId && window.fbq) {
            window.fbq('track', 'Lead');
        }

        leadForm.value = {
            full_name: '',
            phone: '',
            room_preference: '2 xonali (65 - 76 m²)',
            comment: '',
            source: leadForm.value.source
        };
    } catch (err) {
        submitError.value = err.response?.data?.message || 'Arizani yuborishda xatolik yuz berdi. Iltimos qaytadan urinib ko\'ring.';
    } finally {
        isSubmitting.value = false;
    }
};

// Silliq (Smooth) uzluksiz scroll harakati
const scrollToScene = (index) => {
    const targetEl = document.getElementById(scenes[index].id);
    if (targetEl) {
        targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        currentSceneIndex.value = index;
    }
};

const nextScene = () => {
    if (currentSceneIndex.value < scenes.length - 1) {
        scrollToScene(currentSceneIndex.value + 1);
    }
};

const prevScene = () => {
    if (currentSceneIndex.value > 0) {
        scrollToScene(currentSceneIndex.value - 1);
    }
};

// Scroll kuzatuvchisi - foydalanuvchi erkin va silliq scroll qilganda hozirgi sahnani aniqlash
const handleWindowScroll = () => {
    const scrollPos = window.scrollY + window.innerHeight * 0.35;
    for (let i = scenes.length - 1; i >= 0; i--) {
        const el = document.getElementById(scenes[i].id);
        if (el && el.offsetTop <= scrollPos) {
            currentSceneIndex.value = i;
            break;
        }
    }
};

onMounted(() => {
    // Capture UTM parameters from Instagram
    const params = new URLSearchParams(window.location.search);
    const utmSource = params.get('utm_source');
    const utmMedium = params.get('utm_medium');
    const utmCampaign = params.get('utm_campaign');

    let sourceStr = 'Instagram Target';
    if (utmSource) sourceStr += ` (${utmSource}`;
    if (utmMedium) sourceStr += ` / ${utmMedium}`;
    if (utmCampaign) sourceStr += ` / ${utmCampaign}`;
    if (utmSource) sourceStr += `)`;
    leadForm.value.source = sourceStr;

    // Track Facebook Pixel PageView
    if (props.settings?.fbPixelId && window.fbq) {
        window.fbq('track', 'PageView');
    }

    window.addEventListener('scroll', handleWindowScroll, { passive: true });
    handleWindowScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleWindowScroll);
    stopAudio();
});

// Ambient Audio Synthesizer (Web Audio API - GTA VI Trailer Cinema Style Ambient)
const isAudioActive = ref(false);
let audioCtx = null;
let masterGain = null;
let synthOsc1 = null;
let synthOsc2 = null;
let synthFilter = null;

const toggleAudio = () => {
    if (isAudioActive.value) {
        stopAudio();
    } else {
        startAudio();
    }
};

const startAudio = () => {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        
        audioCtx = new AudioContext();
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }

        masterGain = audioCtx.createGain();
        masterGain.gain.setValueAtTime(0.001, audioCtx.currentTime);
        masterGain.gain.exponentialRampToValueAtTime(0.12, audioCtx.currentTime + 3);
        masterGain.connect(audioCtx.destination);

        synthFilter = audioCtx.createBiquadFilter();
        synthFilter.type = 'lowpass';
        synthFilter.frequency.setValueAtTime(320, audioCtx.currentTime);
        synthFilter.connect(masterGain);

        synthOsc1 = audioCtx.createOscillator();
        synthOsc1.type = 'sawtooth';
        synthOsc1.frequency.setValueAtTime(65.41, audioCtx.currentTime); // C2
        synthOsc1.connect(synthFilter);
        synthOsc1.start();

        synthOsc2 = audioCtx.createOscillator();
        synthOsc2.type = 'sine';
        synthOsc2.frequency.setValueAtTime(98.00, audioCtx.currentTime); // G2
        synthOsc2.connect(synthFilter);
        synthOsc2.start();

        isAudioActive.value = true;
    } catch (e) {
        console.warn('Web Audio could not start:', e);
    }
};

const stopAudio = () => {
    if (masterGain && audioCtx) {
        try {
            masterGain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 1);
            setTimeout(() => {
                if (synthOsc1) { synthOsc1.stop(); synthOsc1.disconnect(); synthOsc1 = null; }
                if (synthOsc2) { synthOsc2.stop(); synthOsc2.disconnect(); synthOsc2 = null; }
                if (audioCtx) { audioCtx.close(); audioCtx = null; }
            }, 1000);
        } catch (e) {}
    }
    isAudioActive.value = false;
};
</script>

<template>
    <Head title="Sher-Dor Residence — Instagram Maxsus Taklif" />

    <div class="relative w-full min-h-screen bg-[#06070a] text-white font-sans selection:bg-amber-500 selection:text-black">
        
        <!-- ========================================== -->
        <!-- CINEMATIC SCANLINE & NOISE OVERLAY         -->
        <!-- ========================================== -->
        <div class="pointer-events-none fixed inset-0 z-30 opacity-[0.035] bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px]"></div>
        <div class="pointer-events-none fixed inset-0 z-30 shadow-[inset_0_0_120px_rgba(0,0,0,0.85)]"></div>

        <!-- ========================================== -->
        <!-- FIXED HUD: TOP HEADER NAVBAR              -->
        <!-- ========================================== -->
        <header class="fixed top-0 left-0 right-0 z-50 h-20 px-4 sm:px-10 flex items-center justify-between backdrop-blur-md bg-black/40 border-b border-white/10 transition-all duration-300">
            <!-- Brand Logo -->
            <div class="flex items-center gap-3 group cursor-pointer" @click="scrollToScene(0)">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-600 to-amber-400 p-[1.5px] shadow-[0_0_20px_rgba(245,158,11,0.35)] group-hover:scale-105 transition-transform duration-300">
                    <div class="w-full h-full bg-[#090b10] rounded-[10px] flex items-center justify-center">
                        <span class="font-black text-lg text-amber-400 tracking-tighter">SD</span>
                    </div>
                </div>
                <div class="flex flex-col">
                    <div class="flex items-center gap-2">
                        <span class="font-black text-lg sm:text-xl tracking-[0.2em] text-white uppercase drop-shadow">SHER-DOR</span>
                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30 font-bold uppercase tracking-widest hidden sm:inline-block">RESIDENCE</span>
                    </div>
                    <span class="text-[10px] text-zinc-400 tracking-[0.15em] uppercase font-medium">Instagram Taklif</span>
                </div>
            </div>

            <!-- Dynamic Chapter Pill (Desktop) -->
            <div class="hidden lg:flex items-center gap-3 px-4 py-1.5 rounded-full bg-white/5 border border-white/10 backdrop-blur-md">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                <span class="text-[11px] font-mono tracking-[0.2em] text-zinc-300 uppercase">
                    SCENE {{ scenes[currentSceneIndex].number }} // {{ scenes[currentSceneIndex].tag }}
                </span>
            </div>

            <!-- Header Action Controls -->
            <div class="flex items-center gap-2.5 sm:gap-4">
                
                <!-- Ambient Audio Synthesizer Button -->
                <button 
                    @click="toggleAudio"
                    :class="isAudioActive ? 'bg-amber-500/20 border-amber-400 text-amber-300 shadow-[0_0_15px_rgba(245,158,11,0.3)]' : 'bg-white/5 border-white/10 text-zinc-400 hover:text-white'"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-mono tracking-wider transition-all cursor-pointer"
                    title="Ovoz effektini yoqish/o'chirish"
                >
                    <span v-if="isAudioActive" class="flex gap-0.5 items-end h-3">
                        <span class="w-0.5 h-3 bg-amber-400 animate-pulse"></span>
                        <span class="w-0.5 h-2 bg-amber-400 animate-bounce"></span>
                        <span class="w-0.5 h-3.5 bg-amber-400 animate-pulse"></span>
                    </span>
                    <span v-else class="text-sm">🔇</span>
                    <span class="hidden sm:inline font-bold">{{ isAudioActive ? 'OVOZ: YOQILGAN' : 'OVOZNI YOQISH' }}</span>
                </button>

                <!-- 3D Plan & Buy Button -->
                <Link 
                    :href="route('promo.offers')" 
                    class="px-3.5 sm:px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-black font-extrabold text-xs sm:text-sm tracking-wide uppercase shadow-[0_0_20px_rgba(245,158,11,0.4)] hover:scale-105 active:scale-95 transition-all duration-200 flex items-center gap-1.5"
                >
                    <span>🏢</span>
                    <span class="hidden sm:inline">3D REJA & XARID</span>
                    <span class="sm:hidden">3D REJA</span>
                </Link>

                <!-- Call Button -->
                <a 
                    href="tel:+998977084444" 
                    class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/15 text-zinc-200 hover:text-white text-xs font-mono font-bold tracking-wider transition-all"
                >
                    <span>📞</span>
                    <span class="hidden md:inline">(97) 708-44-44</span>
                </a>
            </div>
        </header>

        <!-- ========================================== -->
        <!-- FIXED HUD: RIGHT VERTICAL SCENE NAVIGATOR  -->
        <!-- ========================================== -->
        <nav class="fixed right-6 sm:right-8 top-1/2 -translate-y-1/2 z-40 hidden md:flex flex-col items-end gap-3.5">
            <div 
                v-for="(scene, idx) in scenes" 
                :key="scene.id"
                @click="scrollToScene(idx)"
                class="group flex items-center gap-3 cursor-pointer py-1"
            >
                <!-- Tooltip Title on Hover -->
                <span 
                    class="opacity-0 group-hover:opacity-100 transition-opacity duration-200 text-[10px] font-mono tracking-[0.2em] uppercase px-2 py-0.5 rounded bg-black/80 border border-white/10 text-amber-300 pointer-events-none"
                >
                    {{ scene.number }} // {{ scene.tag }}
                </span>

                <!-- Line Indicator Dot -->
                <div 
                    :class="currentSceneIndex === idx 
                        ? 'w-8 bg-amber-400 shadow-[0_0_12px_rgba(245,158,11,0.8)]' 
                        : 'w-3 bg-white/25 group-hover:bg-white/60 group-hover:w-5'"
                    class="h-[3px] rounded-full transition-all duration-300"
                ></div>
            </div>
        </nav>

        <!-- ========================================== -->
        <!-- FIXED HUD: BOTTOM STATUS & CONTROLS        -->
        <!-- ========================================== -->
        <div class="fixed bottom-6 left-4 right-4 sm:left-10 sm:right-10 z-40 flex items-center justify-between pointer-events-none">
            <!-- Scene Counter -->
            <div class="pointer-events-auto flex items-center gap-2.5 px-3 py-1.5 rounded-full bg-black/60 border border-white/10 backdrop-blur-md font-mono text-xs">
                <span class="text-amber-400 font-bold text-sm">{{ scenes[currentSceneIndex].number }}</span>
                <span class="text-zinc-600">/</span>
                <span class="text-zinc-400">07</span>
            </div>

            <!-- Scroll Prompt Cue -->
            <div 
                v-if="currentSceneIndex < scenes.length - 1"
                @click="nextScene"
                class="pointer-events-auto flex flex-col items-center gap-1 cursor-pointer group text-zinc-400 hover:text-amber-400 transition-colors"
            >
                <span class="text-[10px] font-mono tracking-[0.25em] uppercase text-zinc-400 group-hover:text-amber-300 transition-colors">
                    SILLIQ SURISH // SCROLL
                </span>
                <div class="w-5 h-8 rounded-full border-2 border-white/30 group-hover:border-amber-400 flex items-start justify-center p-1 transition-colors">
                    <div class="w-1 h-2 rounded-full bg-amber-400 animate-bounce"></div>
                </div>
            </div>

            <!-- Quick Action Buttons -->
            <div class="pointer-events-auto flex items-center gap-2">
                <button 
                    v-if="currentSceneIndex > 0"
                    @click="prevScene" 
                    class="w-10 h-10 rounded-full bg-black/60 hover:bg-black/90 border border-white/10 text-white flex items-center justify-center backdrop-blur-md hover:scale-105 active:scale-95 transition-all cursor-pointer"
                    title="Oldingi lavha"
                >
                    ↑
                </button>
                <button 
                    v-if="currentSceneIndex < scenes.length - 1"
                    @click="nextScene" 
                    class="w-10 h-10 rounded-full bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 text-amber-300 flex items-center justify-center backdrop-blur-md hover:scale-105 active:scale-95 transition-all cursor-pointer"
                    title="Keyingi lavha"
                >
                    ↓
                </button>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- CONTINUOUS SEAMLESS SCROLL BODY            -->
        <!-- ========================================== -->
        <main class="relative z-10 w-full">
            
            <!-- ==================================================== -->
            <!-- SCENE 01: CINEMATIC INTRO & HERO TRAILER             -->
            <!-- ==================================================== -->
            <section 
                id="scene-01" 
                class="min-h-screen relative flex flex-col justify-center items-center py-24 sm:py-32 px-6 sm:px-12 text-center overflow-hidden"
            >
                <!-- Background Layer -->
                <div class="absolute inset-0 z-0">
                    <img 
                        :src="scenes[0].bgImage" 
                        class="w-full h-full object-cover scale-105 transition-transform duration-1000"
                        alt="Sher-Dor Residence"
                        @error="$event.target.src = scenes[0].fallbackBg"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-[#06070a] via-black/65 to-black/85"></div>
                    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_20%,rgba(6,7,10,0.9)_100%)]"></div>
                </div>

                <!-- Scene Content -->
                <div class="relative z-10 max-w-5xl mx-auto flex flex-col items-center">
                    
                    <!-- Instagram Tag Badge -->
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-300 font-mono text-xs tracking-[0.25em] uppercase mb-6 shadow-[0_0_20px_rgba(245,158,11,0.2)]">
                        <span>📸</span>
                        <span>INSTAGRAM MAXSUS TAKLIF • SHER-DOR</span>
                        <span>★</span>
                    </div>

                    <!-- Grand Display Title -->
                    <h1 class="text-5xl sm:text-7xl md:text-8xl lg:text-9xl font-black tracking-tight uppercase leading-[0.9] text-transparent bg-clip-text bg-gradient-to-b from-white via-zinc-100 to-zinc-400 drop-shadow-[0_10px_35px_rgba(0,0,0,0.9)] mb-6">
                        SHER-DOR
                    </h1>

                    <!-- Cinematic Subheading -->
                    <p class="text-lg sm:text-2xl md:text-3xl font-light tracking-[0.1em] text-zinc-300 max-w-3xl mb-8 uppercase">
                        Samarqandning Eng Muhtasham Turar-Joy Majmuasi
                    </p>

                    <!-- Teaser Narrative -->
                    <p class="text-sm sm:text-base text-zinc-400 max-w-2xl mb-12 font-normal leading-relaxed">
                        Sharqona an'analar va zamonaviy Yevropa muhandisligining mukammal uyg'unligi. 
                        Xavfsiz, hashamatli va har bir detali sizning qulayligingiz uchun o'ylangan yangi hayot maskani.
                    </p>

                    <!-- Hero Actions -->
                    <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 w-full max-w-xl">
                        <Link 
                            :href="route('promo.offers')"
                            class="px-8 py-4 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 text-black font-black text-sm sm:text-base tracking-wider uppercase shadow-[0_0_30px_rgba(245,158,11,0.45)] hover:scale-105 active:scale-95 transition-all duration-200 flex items-center gap-2"
                        >
                            <span>🏢</span>
                            <span>3D Xonadonlarni Tanlash</span>
                        </Link>

                        <button 
                            @click="scrollToScene(6)"
                            class="px-8 py-4 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/20 text-white font-bold text-sm sm:text-base tracking-wider uppercase backdrop-blur-md hover:scale-105 active:scale-95 transition-all duration-200 flex items-center gap-2 cursor-pointer"
                        >
                            <span>⚡</span>
                            <span>Ariza qoldirish</span>
                        </button>
                    </div>

                    <!-- Scroll Prompt Button -->
                    <button 
                        @click="scrollToScene(1)"
                        class="mt-12 text-xs font-mono tracking-[0.2em] text-zinc-400 hover:text-amber-400 uppercase transition-colors flex items-center gap-2 cursor-pointer"
                    >
                        <span>SAYOHATNI BOSHLASH</span>
                        <span>↓</span>
                    </button>
                </div>
                
                <!-- Seamless Bottom Blend Gradient -->
                <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-[#06070a] to-transparent pointer-events-none z-[1]"></div>
            </section>


            <!-- ==================================================== -->
            <!-- SCENE 02: MONUMENTAL ARCHITECTURE                    -->
            <!-- ==================================================== -->
            <section 
                id="scene-02" 
                class="min-h-screen relative flex flex-col justify-center py-24 sm:py-32 px-6 sm:px-16 lg:px-24 overflow-hidden border-t border-white/5"
            >
                <!-- Seamless Top Blend Gradient -->
                <div class="absolute top-0 left-0 right-0 h-32 bg-gradient-to-b from-[#06070a] to-transparent pointer-events-none z-[1]"></div>

                <!-- Background -->
                <div class="absolute inset-0 z-0">
                    <img 
                        :src="scenes[1].bgImage" 
                        class="w-full h-full object-cover"
                        alt="Architecture"
                        @error="$event.target.src = scenes[1].fallbackBg"
                    />
                    <div class="absolute inset-0 bg-gradient-to-r from-[#06070a] via-[#06070a]/90 to-[#06070a]/60"></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-[#06070a] via-transparent to-[#06070a]/80"></div>
                </div>

                <!-- Scene Content -->
                <div class="relative z-10 max-w-4xl">
                    <div class="text-amber-400 font-mono text-xs sm:text-sm tracking-[0.25em] uppercase mb-4 flex items-center gap-2">
                        <span class="w-8 h-[2px] bg-amber-400 inline-block"></span>
                        <span>SCENE 02 // ME'MORCHILIK VA KONSTRUKSIYA</span>
                    </div>

                    <h2 class="text-3xl sm:text-5xl md:text-6xl font-black uppercase tracking-tight text-white leading-tight mb-6">
                        MONUMENTAL VA <br class="hidden sm:block" />
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-amber-200">
                            ZILZILABARDOSH
                        </span> BINO
                    </h2>

                    <p class="text-sm sm:text-lg text-zinc-300 max-w-2xl mb-10 leading-relaxed font-light">
                        Sher-Dor majmuasi monolit temir-beton karkas va pishiq g'ishtdan qad ko'targan bo'lib, 
                        9 ballik zilzilaga bardosh bera oladigan mustahkam poydevorga ega. Har bir blok seysmik standartlar asosida sinovdan o'tgan.
                    </p>

                    <!-- Cinematic HUD Metrics Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6 max-w-3xl mb-10">
                        <div class="p-4 sm:p-5 rounded-2xl bg-black/60 border border-white/10 backdrop-blur-md">
                            <div class="text-3xl sm:text-4xl font-black text-amber-400 font-mono">4</div>
                            <div class="text-xs font-bold text-white uppercase tracking-wider mt-1">Blok</div>
                            <div class="text-[11px] text-zinc-400 mt-0.5">Yaxlit arxitektura</div>
                        </div>

                        <div class="p-4 sm:p-5 rounded-2xl bg-black/60 border border-white/10 backdrop-blur-md">
                            <div class="text-3xl sm:text-4xl font-black text-amber-400 font-mono">8</div>
                            <div class="text-xs font-bold text-white uppercase tracking-wider mt-1">Qavat</div>
                            <div class="text-[11px] text-zinc-400 mt-0.5">Optimal balandlik</div>
                        </div>

                        <div class="p-4 sm:p-5 rounded-2xl bg-black/60 border border-white/10 backdrop-blur-md">
                            <div class="text-3xl sm:text-4xl font-black text-amber-400 font-mono">177</div>
                            <div class="text-xs font-bold text-white uppercase tracking-wider mt-1">Xonadon</div>
                            <div class="text-[11px] text-zinc-400 mt-0.5">Keng tanlov</div>
                        </div>

                        <div class="p-4 sm:p-5 rounded-2xl bg-black/60 border border-white/10 backdrop-blur-md">
                            <div class="text-3xl sm:text-4xl font-black text-amber-400 font-mono">9★</div>
                            <div class="text-xs font-bold text-white uppercase tracking-wider mt-1">Seysmik</div>
                            <div class="text-[11px] text-zinc-400 mt-0.5">100% xavfsizlik</div>
                        </div>
                    </div>

                    <!-- Next Button -->
                    <button 
                        @click="scrollToScene(2)" 
                        class="inline-flex items-center gap-2 text-xs font-mono tracking-[0.2em] uppercase text-zinc-400 hover:text-amber-400 transition-colors cursor-pointer"
                    >
                        <span>DAVOM ETISH (INTERYER)</span>
                        <span>→</span>
                    </button>
                </div>

                <!-- Seamless Bottom Blend Gradient -->
                <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-[#06070a] to-transparent pointer-events-none z-[1]"></div>
            </section>


            <!-- ==================================================== -->
            <!-- SCENE 03: LUXURY INTERIORS                           -->
            <!-- ==================================================== -->
            <section 
                id="scene-03" 
                class="min-h-screen relative flex flex-col justify-center py-24 sm:py-32 px-6 sm:px-16 lg:px-24 overflow-hidden border-t border-white/5"
            >
                <!-- Seamless Top Blend Gradient -->
                <div class="absolute top-0 left-0 right-0 h-32 bg-gradient-to-b from-[#06070a] to-transparent pointer-events-none z-[1]"></div>

                <!-- Background -->
                <div class="absolute inset-0 z-0">
                    <img 
                        :src="scenes[2].bgImage" 
                        class="w-full h-full object-cover"
                        alt="Interior"
                        @error="$event.target.src = scenes[2].fallbackBg"
                    />
                    <div class="absolute inset-0 bg-gradient-to-l from-[#06070a] via-[#06070a]/85 to-[#06070a]/65"></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-[#06070a] via-transparent to-[#06070a]/80"></div>
                </div>

                <!-- Scene Content -->
                <div class="relative z-10 max-w-3xl ml-auto text-right">
                    <div class="text-amber-400 font-mono text-xs sm:text-sm tracking-[0.25em] uppercase mb-4 flex items-center justify-end gap-2">
                        <span>SCENE 03 // HASHAMATLI INTERYER</span>
                        <span class="w-8 h-[2px] bg-amber-400 inline-block"></span>
                    </div>

                    <h2 class="text-3xl sm:text-5xl md:text-6xl font-black uppercase tracking-tight text-white leading-tight mb-6">
                        YORUG'LIK VA <br class="hidden sm:block" />
                        <span class="text-transparent bg-clip-text bg-gradient-to-l from-amber-400 to-amber-200">
                            CHEKSIZ ERKINLIK
                        </span>
                    </h2>

                    <p class="text-sm sm:text-lg text-zinc-300 max-w-xl ml-auto mb-10 leading-relaxed font-light">
                        Keng panoramik derazalar xonadonni tabiiy nur bilan to'ldiradi. 
                        3.2 metr balandlikdagi shiftlar esa har bir xonaga hashamat va toza havo bag'ishlaydi.
                    </p>

                    <!-- Feature Badges -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-xl ml-auto mb-10 text-left">
                        <div class="p-4 rounded-xl bg-black/60 border border-white/10 backdrop-blur-md flex items-start gap-3">
                            <span class="text-xl">🪟</span>
                            <div>
                                <div class="font-bold text-sm text-white">Panoramik derazalar</div>
                                <div class="text-xs text-zinc-400 mt-0.5">Shovqin o'tkazmaydigan 3 qavatli oyna</div>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-black/60 border border-white/10 backdrop-blur-md flex items-start gap-3">
                            <span class="text-xl">📏</span>
                            <div>
                                <div class="font-bold text-sm text-white">3.2 metr shift</div>
                                <div class="text-xs text-zinc-400 mt-0.5">Erkin va keng havo aylanmasi</div>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-black/60 border border-white/10 backdrop-blur-md flex items-start gap-3">
                            <span class="text-xl">🔥</span>
                            <div>
                                <div class="font-bold text-sm text-white">Shaxsiy isitish tizimi</div>
                                <div class="text-xs text-zinc-400 mt-0.5">Ikki konturli tejamkor qozonlar</div>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-black/60 border border-white/10 backdrop-blur-md flex items-start gap-3">
                            <span class="text-xl">🛗</span>
                            <div>
                                <div class="font-bold text-sm text-white">Tezyurar shovqinsiz lift</div>
                                <div class="text-xs text-zinc-400 mt-0.5">Parkingdan to'g'ri xonadonga</div>
                            </div>
                        </div>
                    </div>

                    <!-- Next Button -->
                    <button 
                        @click="scrollToScene(3)" 
                        class="inline-flex items-center gap-2 text-xs font-mono tracking-[0.2em] uppercase text-zinc-400 hover:text-amber-400 transition-colors cursor-pointer"
                    >
                        <span>DAVOM ETISH (3D XONADONLAR)</span>
                        <span>→</span>
                    </button>
                </div>

                <!-- Seamless Bottom Blend Gradient -->
                <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-[#06070a] to-transparent pointer-events-none z-[1]"></div>
            </section>


            <!-- ==================================================== -->
            <!-- SCENE 04: 3D LIVING SPACES & PLANS                   -->
            <!-- ==================================================== -->
            <section 
                id="scene-04" 
                class="min-h-screen relative flex flex-col justify-center py-24 sm:py-32 px-6 sm:px-16 lg:px-24 overflow-hidden border-t border-white/5"
            >
                <!-- Seamless Top Blend Gradient -->
                <div class="absolute top-0 left-0 right-0 h-32 bg-gradient-to-b from-[#06070a] to-transparent pointer-events-none z-[1]"></div>

                <!-- Background -->
                <div class="absolute inset-0 z-0">
                    <img 
                        :src="scenes[3].bgImage" 
                        class="w-full h-full object-cover"
                        alt="Apartments"
                        @error="$event.target.src = scenes[3].fallbackBg"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-[#06070a] via-[#06070a]/90 to-[#06070a]/75"></div>
                </div>

                <!-- Scene Content -->
                <div class="relative z-10 max-w-5xl mx-auto w-full">
                    <div class="text-amber-400 font-mono text-xs sm:text-sm tracking-[0.25em] uppercase mb-3 flex items-center justify-center gap-2">
                        <span class="w-8 h-[2px] bg-amber-400 inline-block"></span>
                        <span>SCENE 04 // XONADON TURLARI VA PLANLAR</span>
                        <span class="w-8 h-[2px] bg-amber-400 inline-block"></span>
                    </div>

                    <h2 class="text-2xl sm:text-4xl md:text-5xl font-black uppercase tracking-tight text-white text-center mb-8">
                        SIZNING ORZUINGIZDAGI MAYDON
                    </h2>

                    <!-- Plan Selector Tabs -->
                    <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mb-8">
                        <button 
                            v-for="(plan, idx) in planTabs" 
                            :key="plan.title"
                            @click="activePlanTab = idx"
                            :class="activePlanTab === idx 
                                ? 'bg-amber-500 text-black font-extrabold shadow-[0_0_20px_rgba(245,158,11,0.4)]' 
                                : 'bg-black/60 text-zinc-300 hover:text-white border border-white/10'"
                            class="px-4 sm:px-6 py-2.5 rounded-xl text-xs sm:text-sm uppercase tracking-wider transition-all duration-200 cursor-pointer"
                        >
                            {{ plan.title }}
                        </button>
                    </div>

                    <!-- Active Plan Showcase Card -->
                    <div class="p-6 sm:p-8 rounded-3xl bg-black/75 border border-white/15 backdrop-blur-xl shadow-2xl max-w-3xl mx-auto">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-white/10 pb-5 mb-5">
                            <div>
                                <span class="text-xs font-mono uppercase tracking-widest text-amber-400 font-bold">Xonadon turi</span>
                                <h3 class="text-2xl sm:text-3xl font-black text-white mt-1">{{ planTabs[activePlanTab].title }}</h3>
                            </div>
                            <div class="sm:text-right">
                                <span class="text-xs font-mono uppercase tracking-widest text-zinc-400">Umumiy maydon</span>
                                <div class="text-2xl sm:text-3xl font-mono font-black text-amber-400 mt-1">{{ planTabs[activePlanTab].area }}</div>
                            </div>
                        </div>

                        <p class="text-sm sm:text-base text-zinc-300 leading-relaxed mb-6">
                            {{ planTabs[activePlanTab].description }}
                        </p>

                        <!-- Highlights -->
                        <div class="grid grid-cols-2 gap-3 mb-8">
                            <div 
                                v-for="(hl, i) in planTabs[activePlanTab].highlights" 
                                :key="i"
                                class="flex items-center gap-2 text-xs sm:text-sm text-zinc-300"
                            >
                                <span class="text-amber-400 font-bold">✓</span>
                                <span>{{ hl }}</span>
                            </div>
                        </div>

                        <!-- Direct 3D Button -->
                        <div class="flex flex-col sm:flex-row items-center gap-3">
                            <Link 
                                :href="route('promo.offers')"
                                class="w-full sm:w-auto px-6 py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-black font-extrabold text-xs sm:text-sm uppercase tracking-wider shadow-lg hover:scale-[1.02] transition-all flex items-center justify-center gap-2"
                            >
                                <span>🏢</span>
                                <span>Ushbu xonadonni 3D Ko'rish va Tanlash</span>
                            </Link>

                            <button 
                                @click="scrollToScene(6)"
                                class="w-full sm:w-auto px-6 py-3.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/10 text-white font-bold text-xs sm:text-sm uppercase tracking-wider transition-all cursor-pointer"
                            >
                                Narxini so'rash
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Seamless Bottom Blend Gradient -->
                <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-[#06070a] to-transparent pointer-events-none z-[1]"></div>
            </section>


            <!-- ==================================================== -->
            <!-- SCENE 05: COURTYARD, ECO-PARK & AMENITIES            -->
            <!-- ==================================================== -->
            <section 
                id="scene-05" 
                class="min-h-screen relative flex flex-col justify-center py-24 sm:py-32 px-6 sm:px-16 lg:px-24 overflow-hidden border-t border-white/5"
            >
                <!-- Seamless Top Blend Gradient -->
                <div class="absolute top-0 left-0 right-0 h-32 bg-gradient-to-b from-[#06070a] to-transparent pointer-events-none z-[1]"></div>

                <!-- Background -->
                <div class="absolute inset-0 z-0">
                    <img 
                        :src="scenes[4].bgImage" 
                        class="w-full h-full object-cover"
                        alt="Courtyard"
                        @error="$event.target.src = scenes[4].fallbackBg"
                    />
                    <div class="absolute inset-0 bg-gradient-to-r from-[#06070a] via-[#06070a]/90 to-[#06070a]/65"></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-[#06070a] via-transparent to-[#06070a]/80"></div>
                </div>

                <!-- Scene Content -->
                <div class="relative z-10 max-w-4xl">
                    <div class="text-amber-400 font-mono text-xs sm:text-sm tracking-[0.25em] uppercase mb-4 flex items-center gap-2">
                        <span class="w-8 h-[2px] bg-amber-400 inline-block"></span>
                        <span>SCENE 05 // EKO-HUDUD VA XAVFSIZLIK</span>
                    </div>

                    <h2 class="text-3xl sm:text-5xl md:text-6xl font-black uppercase tracking-tight text-white leading-tight mb-6">
                        AVTOMOBILLARDAN HOLI <br class="hidden sm:block" />
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-amber-300">
                            XAVFSIZ HOVLI
                        </span>
                    </h2>

                    <p class="text-sm sm:text-lg text-zinc-300 max-w-2xl mb-8 leading-relaxed font-light">
                        Bolalaringiz va ota-onangiz erkin, xavfsiz nafas oladigan yopiq eko-hudud. 
                        Mashinalar faqat yerosti avtoturargohiga joylashtiriladi, hovlida esa faqat yashil bog', favvora va bolalar o'yingohi mavjud.
                    </p>

                    <!-- Amenity Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-3xl mb-8">
                        <div class="p-4 rounded-2xl bg-black/65 border border-white/10 backdrop-blur-md">
                            <span class="text-2xl">🛡️</span>
                            <div class="font-bold text-sm text-white mt-2">24/7 Qo'riqlash</div>
                            <div class="text-xs text-zinc-400 mt-1">To'liq yopiq perimetr va video-nazorat</div>
                        </div>

                        <div class="p-4 rounded-2xl bg-black/65 border border-white/10 backdrop-blur-md">
                            <span class="text-2xl">🚗</span>
                            <div class="font-bold text-sm text-white mt-2">Yerosti Parking</div>
                            <div class="text-xs text-zinc-400 mt-1">Shaxsiy joy va lift orqali chiqish</div>
                        </div>

                        <div class="p-4 rounded-2xl bg-black/65 border border-white/10 backdrop-blur-md">
                            <span class="text-2xl">🌲</span>
                            <div class="font-bold text-sm text-white mt-2">Yashil Xiyobon</div>
                            <div class="text-xs text-zinc-400 mt-1">Gullar, favvoralar va o'yingoh</div>
                        </div>
                    </div>

                    <!-- Next Button -->
                    <button 
                        @click="scrollToScene(5)" 
                        class="inline-flex items-center gap-2 text-xs font-mono tracking-[0.2em] uppercase text-zinc-400 hover:text-amber-400 transition-colors cursor-pointer"
                    >
                        <span>DAVOM ETISH (STRATEGIK JOYLASHUV)</span>
                        <span>→</span>
                    </button>
                </div>

                <!-- Seamless Bottom Blend Gradient -->
                <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-[#06070a] to-transparent pointer-events-none z-[1]"></div>
            </section>


            <!-- ==================================================== -->
            <!-- SCENE 06: STRATEGIC LOCATION                         -->
            <!-- ==================================================== -->
            <section 
                id="scene-06" 
                class="min-h-screen relative flex flex-col justify-center py-24 sm:py-32 px-6 sm:px-16 lg:px-24 overflow-hidden border-t border-white/5"
            >
                <!-- Seamless Top Blend Gradient -->
                <div class="absolute top-0 left-0 right-0 h-32 bg-gradient-to-b from-[#06070a] to-transparent pointer-events-none z-[1]"></div>

                <!-- Background -->
                <div class="absolute inset-0 z-0">
                    <img 
                        :src="scenes[5].bgImage" 
                        class="w-full h-full object-cover"
                        alt="Location"
                        @error="$event.target.src = scenes[5].fallbackBg"
                    />
                    <div class="absolute inset-0 bg-gradient-to-l from-[#06070a] via-[#06070a]/90 to-[#06070a]/65"></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-[#06070a] via-transparent to-[#06070a]/80"></div>
                </div>

                <!-- Scene Content -->
                <div class="relative z-10 max-w-4xl ml-auto text-right">
                    <div class="text-amber-400 font-mono text-xs sm:text-sm tracking-[0.25em] uppercase mb-4 flex items-center justify-end gap-2">
                        <span>SCENE 06 // STRATEGIK JOYLASHUV</span>
                        <span class="w-8 h-[2px] bg-amber-400 inline-block"></span>
                    </div>

                    <h2 class="text-3xl sm:text-5xl md:text-6xl font-black uppercase tracking-tight text-white leading-tight mb-4">
                        URGUT SHAHARCHASI <br class="hidden sm:block" />
                        <span class="text-transparent bg-clip-text bg-gradient-to-l from-amber-400 to-amber-200">
                            QALBIDA
                        </span>
                    </h2>

                    <p class="text-sm sm:text-base text-zinc-300 max-w-xl ml-auto mb-8 font-light">
                        📍 <strong>Manzil:</strong> Samarqand viloyati, Urgut tumani, Do'stlik mahallasi, Navoiy-Shoh ko'chasi №3-uy.
                    </p>

                    <!-- Travel Times Grid -->
                    <div class="grid grid-cols-2 gap-3 max-w-xl ml-auto mb-8 text-left">
                        <div class="p-3.5 rounded-xl bg-black/70 border border-white/10 backdrop-blur-md">
                            <div class="text-amber-400 font-mono font-bold text-lg">2 daqiqa</div>
                            <div class="text-xs text-white font-bold mt-0.5">Markaziy Bozor</div>
                            <div class="text-[11px] text-zinc-400">Savdo va supermarketlar</div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-black/70 border border-white/10 backdrop-blur-md">
                            <div class="text-amber-400 font-mono font-bold text-lg">3 daqiqa</div>
                            <div class="text-xs text-white font-bold mt-0.5">Maktab va Bog'cha</div>
                            <div class="text-[11px] text-zinc-400">Farzandlar ta'limi</div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-black/70 border border-white/10 backdrop-blur-md">
                            <div class="text-amber-400 font-mono font-bold text-lg">4 daqiqa</div>
                            <div class="text-xs text-white font-bold mt-0.5">Tibbiyot Markazi</div>
                            <div class="text-[11px] text-zinc-400">Shifoxona va dorixonalar</div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-black/70 border border-white/10 backdrop-blur-md">
                            <div class="text-amber-400 font-mono font-bold text-lg">25 daqiqa</div>
                            <div class="text-xs text-white font-bold mt-0.5">Samarqand Aeroporti</div>
                            <div class="text-[11px] text-zinc-400">Xalqaro reyslar</div>
                        </div>
                    </div>

                    <!-- Next Button -->
                    <button 
                        @click="scrollToScene(6)" 
                        class="inline-flex items-center gap-2 text-xs font-mono tracking-[0.2em] uppercase text-zinc-400 hover:text-amber-400 transition-colors cursor-pointer"
                    >
                        <span>XARID VA ARIZA TOPSHIRISH</span>
                        <span>→</span>
                    </button>
                </div>

                <!-- Seamless Bottom Blend Gradient -->
                <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-[#06070a] to-transparent pointer-events-none z-[1]"></div>
            </section>


            <!-- ==================================================== -->
            <!-- SCENE 07: FINALE & INSTANT LEAD RESERVATION          -->
            <!-- ==================================================== -->
            <section 
                id="scene-07" 
                class="min-h-screen relative flex flex-col justify-center items-center py-24 sm:py-32 px-6 sm:px-12 overflow-hidden border-t border-white/5"
            >
                <!-- Seamless Top Blend Gradient -->
                <div class="absolute top-0 left-0 right-0 h-32 bg-gradient-to-b from-[#06070a] to-transparent pointer-events-none z-[1]"></div>

                <!-- Background -->
                <div class="absolute inset-0 z-0">
                    <img 
                        :src="scenes[6].bgImage" 
                        class="w-full h-full object-cover"
                        alt="Finale"
                        @error="$event.target.src = scenes[6].fallbackBg"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-[#06070a] via-[#06070a]/90 to-[#06070a]/80"></div>
                </div>

                <!-- Scene Content -->
                <div class="relative z-10 max-w-4xl w-full mx-auto text-center">
                    
                    <div class="text-amber-400 font-mono text-xs sm:text-sm tracking-[0.25em] uppercase mb-2">
                        SCENE 07 // INSTAGRAM MAXSUS AKSIYA
                    </div>

                    <h2 class="text-3xl sm:text-5xl font-black uppercase tracking-tight text-white mb-2">
                        ORZUINGIZDAGI XONADONNI BAND QILING
                    </h2>

                    <p class="text-sm text-zinc-400 max-w-lg mx-auto mb-6">
                        Ma'lumotlaringizni qoldiring, mutaxassislarimiz 15 daqiqada siz bilan bog'lanib barcha ma'lumotlarni taqdim etadilar.
                    </p>

                    <!-- Instant Lead Form Box -->
                    <div class="p-6 sm:p-8 rounded-3xl bg-black/80 border border-white/20 backdrop-blur-2xl shadow-2xl max-w-2xl mx-auto text-left">
                        
                        <div v-if="submitSuccess" class="py-8 text-center space-y-4">
                            <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 mx-auto flex items-center justify-center text-3xl">
                                ✓
                            </div>
                            <h3 class="text-2xl font-black text-white">Arizangiz qabul qilindi!</h3>
                            <p class="text-sm text-zinc-300 max-w-md mx-auto">
                                Tez orada menejerimiz siz bilan bog'lanadi. Sizni Sher-Dor oilasida ko'rishdan mamnunmiz!
                            </p>
                            <Link 
                                :href="route('promo.offers')"
                                class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-amber-500 text-black font-extrabold text-xs uppercase tracking-wider"
                            >
                                3D Xonadonlarni Ko'rish
                            </Link>
                        </div>

                        <form v-else @submit.prevent="submitLead" class="space-y-4">
                            <div v-if="submitError" class="p-3 rounded-xl bg-red-500/20 border border-red-500/40 text-red-300 text-xs">
                                {{ submitError }}
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-wider text-zinc-400 mb-1.5">Ism-sharifingiz</label>
                                    <input 
                                        v-model="leadForm.full_name"
                                        type="text" 
                                        placeholder="Ali Valiyev"
                                        class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white placeholder-zinc-500 focus:outline-none focus:border-amber-400 text-sm transition-colors"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-wider text-zinc-400 mb-1.5">Telefon raqamingiz *</label>
                                    <input 
                                        v-model="leadForm.phone"
                                        type="tel" 
                                        required
                                        placeholder="+998 90 123 45 67"
                                        class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white placeholder-zinc-500 focus:outline-none focus:border-amber-400 text-sm transition-colors"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-wider text-zinc-400 mb-1.5">Qiziqtirgan xonadon</label>
                                    <select 
                                        v-model="leadForm.room_preference"
                                        class="w-full px-4 py-3 rounded-xl bg-[#12141a] border border-white/10 text-white focus:outline-none focus:border-amber-400 text-sm transition-colors"
                                    >
                                        <option value="1 xonali (40.65 m²)">1 xonali (40.65 m²)</option>
                                        <option value="2 xonali (65 - 76 m²)">2 xonali (65 - 76 m²)</option>
                                        <option value="3 xonali (101 m²)">3 xonali (101 m²)</option>
                                        <option value="Mansarda (51 - 98 m²)">Mansarda (51 - 98 m²)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-wider text-zinc-400 mb-1.5">Izoh yoki savol</label>
                                    <input 
                                        v-model="leadForm.comment"
                                        type="text" 
                                        placeholder="Bo'lib to'lash shartlari..."
                                        class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white placeholder-zinc-500 focus:outline-none focus:border-amber-400 text-sm transition-colors"
                                    />
                                </div>
                            </div>

                            <button 
                                type="submit"
                                :disabled="isSubmitting"
                                class="w-full py-4 rounded-xl bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 text-black font-black text-sm uppercase tracking-wider shadow-[0_0_25px_rgba(245,158,11,0.4)] hover:scale-[1.01] active:scale-95 transition-all duration-200 cursor-pointer disabled:opacity-50"
                            >
                                {{ isSubmitting ? 'Yuborilmoqda...' : '🚀 ARIZA TOPSHIRISH' }}
                            </button>
                        </form>
                    </div>

                    <!-- Direct Contacts & Social Links -->
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-6 text-xs font-mono text-zinc-400">
                        <a href="tel:+998977084444" class="hover:text-amber-400 transition-colors flex items-center gap-1.5">
                            <span>📞</span> +998 (97) 708-44-44
                        </a>
                        <a href="tel:+998935732929" class="hover:text-amber-400 transition-colors flex items-center gap-1.5">
                            <span>📞</span> +998 (93) 573-29-29
                        </a>
                        <a href="https://t.me/sherdor_admin" target="_blank" class="hover:text-sky-400 transition-colors flex items-center gap-1.5">
                            <span>✈️</span> Telegram
                        </a>
                        <Link :href="route('promo.offers')" class="hover:text-amber-400 text-amber-300 font-bold transition-colors flex items-center gap-1.5">
                            <span>🏢</span> Onlayn 3D Reja
                        </Link>
                    </div>
                </div>
            </section>

        </main>
    </div>
</template>

<style>
/* Silk-smooth native page scrolling */
html {
    scroll-behavior: smooth;
    background-color: #06070a;
}
body {
    background-color: #06070a;
    overflow-x: hidden;
}
</style>
