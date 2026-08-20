<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: { type: Boolean, default: true },
    canRegister: { type: Boolean, default: false },
    laravelVersion: { type: String, default: '' },
    phpVersion: { type: String, default: '' },
});

const showDemo = ref(false);
const demoSubmitted = ref(false);
const demoForm = ref({
    name: '',
    company: '',
    phone: '',
    message: ''
});

const submitDemo = () => {
    if (!demoForm.value.phone || !demoForm.value.name) return;
    demoSubmitted.value = true;
    setTimeout(() => {
        // can redirect or close
    }, 2000);
};

// FAQ Accordion State
const activeFaq = ref(0);
const toggleFaq = (index) => {
    activeFaq.value = activeFaq.value === index ? null : index;
};

const faqs = [
    {
        q: "IQS ERP qaysi sohalarga mos keladi?",
        a: "IQS ERP ko'p qavatli turar-joy binolari, tijorat obyektlari va kottej majmualarini quruvchi hamda sotuvini amalga oshiruvchi har qanday qurilish kompaniyasi, developer va pudratchilar uchun maxsus ishlab chiqilgan."
    },
    {
        q: "Tizimdan foydalanish uchun texnik bilim kerakmi?",
        a: "Tizimdan foydalanish uchun maxsus dasturchilik bilimi talab qilinmaydi. Intuitiv sodda interfeys tufayli kompyuterdan boshlang'ich foydalana olish yetarli. Tizim joriy etilgach, kompaniyangiz xodimlariga bepul amaliy treninglar va barcha foydalanuvchilar uchun videodarsliklar taqdim etiladi."
    },
    {
        q: "Ma’lumotlar xavfsizligi qanday ta’minlanadi?",
        a: "IQS tizimida har bir korxona ma'lumotlari alohida xavfsiz serverda, 256-bitli SSL shifrlash va kunlik avtomatlashtirilgan zaxira nusxalari (backup) bilan himoyalangan. Sizning barcha tijorat sirlaringiz va mijozlar bazangiz 100% daxlsiz saqlanadi."
    },
    {
        q: "IQS ERP boshqa dasturlar bilan integratsiyalanadimi?",
        a: "Ha! IQS tizimi Didox elektron hujjat aylanishi, 1C buxgalteriya, Bank to'lov tizimlari, SMS Gateway va Telegram botlar bilan to'liq integratsiya qilingan bo'lib, barcha ma'lumotlar avtomatik sinxronlashadi."
    }
];

// Warp Speed Starfield Animation Logic
const canvasRef = ref(null);
let animationFrameId = null;

onMounted(() => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    let width = canvas.width = window.innerWidth;
    let height = canvas.height = window.innerHeight;
    
    const stars = [];
    const numStars = 600;
    
    const centerX = width / 2;
    const centerY = height / 2;
    
    for (let i = 0; i < numStars; i++) {
        stars.push({
            x: Math.random() * width - centerX,
            y: Math.random() * height - centerY,
            z: Math.random() * width,
            pz: Math.random() * width
        });
    }
    
    let speed = 2.0;

    function draw() {
        ctx.fillStyle = 'rgba(5, 5, 8, 0.25)';
        ctx.fillRect(0, 0, width, height);
        
        const cx = width / 2;
        const cy = height / 2;
        
        for (let i = 0; i < numStars; i++) {
            const star = stars[i];
            
            star.z -= speed;
            if (star.z <= 0) {
                star.x = Math.random() * width - cx;
                star.y = Math.random() * height - cy;
                star.z = width;
                star.pz = width;
            }
            
            const sx = star.x / star.z * width + cx;
            const sy = star.y / star.z * width + cy;
            
            const px = star.x / star.pz * width + cx;
            const py = star.y / star.pz * width + cy;
            
            star.pz = star.z;
            
            const radius = (1 - star.z / width) * 2.2;
            
            if (i % 8 === 0) ctx.fillStyle = '#f59e0b'; // Amber
            else if (i % 12 === 0) ctx.fillStyle = '#8b5cf6'; // Purple
            else if (i % 15 === 0) ctx.fillStyle = '#10b981'; // Emerald
            else ctx.fillStyle = '#ffffff'; // White
            
            ctx.beginPath();
            ctx.arc(sx, sy, radius, 0, Math.PI * 2);
            ctx.fill();
            
            ctx.strokeStyle = ctx.fillStyle;
            ctx.lineWidth = radius * 0.8;
            ctx.beginPath();
            ctx.moveTo(px, py);
            ctx.lineTo(sx, sy);
            ctx.stroke();
        }
        
        animationFrameId = requestAnimationFrame(draw);
    }
    
    draw();
    
    const handleResize = () => {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    };
    
    window.addEventListener('resize', handleResize);
    
    onBeforeUnmount(() => {
        if (animationFrameId) cancelAnimationFrame(animationFrameId);
        window.removeEventListener('resize', handleResize);
    });
});
</script>

<template>
    <Head title="IQS ERP - Qurilish va Sotuv Jarayonlarini Avtomatlashtirish Tizimi" />
    
    <div class="min-h-screen bg-[#07070a] text-white relative overflow-hidden font-sans selection:bg-amber-500 selection:text-black">
        
        <!-- Warp Speed Starfield Canvas Background -->
        <canvas ref="canvasRef" class="fixed inset-0 z-0 pointer-events-none"></canvas>
        
        <!-- Ambient Glowing Orbs -->
        <div class="fixed top-[-15%] left-[20%] w-[500px] h-[500px] rounded-full bg-amber-500/10 blur-[140px] pointer-events-none z-0"></div>
        <div class="fixed bottom-[-10%] right-[10%] w-[600px] h-[600px] rounded-full bg-purple-600/10 blur-[160px] pointer-events-none z-0"></div>
        <div class="fixed top-[40%] left-[-10%] w-[450px] h-[450px] rounded-full bg-blue-600/10 blur-[130px] pointer-events-none z-0"></div>

        <!-- Sticky Header / Navbar -->
        <header class="sticky top-0 z-50 backdrop-blur-xl bg-[#07070a]/75 border-b border-white/10 transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                
                <!-- Logo -->
                <a href="#home" class="flex items-center gap-3 group">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-amber-500 via-purple-600 to-blue-500 flex items-center justify-center p-0.5 shadow-[0_0_20px_rgba(245,158,11,0.4)] group-hover:scale-105 transition-transform">
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
                </a>

                <!-- Nav Links (Desktop) -->
                <nav class="hidden md:flex items-center space-x-8 text-sm font-medium">
                    <a href="#home" class="text-gray-300 hover:text-amber-400 transition-colors">Bosh sahifa</a>
                    <a href="#modules" class="text-gray-300 hover:text-amber-400 transition-colors">Modullar</a>
                    <a href="#benefits" class="text-gray-300 hover:text-amber-400 transition-colors">Afzalliklar</a>
                    <a href="#services" class="text-gray-300 hover:text-amber-400 transition-colors">Qadamlar</a>
                    <a href="#testimonials" class="text-gray-300 hover:text-amber-400 transition-colors">Fikrlar</a>
                    <a href="#faq" class="text-gray-300 hover:text-amber-400 transition-colors">FAQ</a>
                </nav>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <Link :href="route('promo.offers')" class="hidden sm:inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 text-sm font-semibold transition-all shadow-[0_0_15px_rgba(16,185,129,0.15)] hover:shadow-[0_0_20px_rgba(16,185,129,0.3)]">
                        <span>🏢</span> Uy sotib olish
                    </Link>

                    <Link :href="route('login')" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-black font-bold text-sm shadow-[0_0_20px_rgba(245,158,11,0.4)] hover:shadow-[0_0_30px_rgba(245,158,11,0.6)] transition-all transform hover:scale-[1.02]">
                        Kirish
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="relative z-10">

            <!-- HERO SECTION -->
            <section id="home" class="pt-16 pb-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto flex flex-col items-center text-center">
                
                <!-- Trust Badge -->
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-amber-500/40 bg-amber-500/10 backdrop-blur-md mb-8 shadow-[0_0_20px_rgba(245,158,11,0.2)] animate-pulse">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                    </span>
                    <span class="text-xs sm:text-sm font-semibold text-amber-300">Bizga 20+ yirik qurilish kompaniyasi ishonmoqda</span>
                </div>

                <!-- Hero Title -->
                <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight max-w-5xl leading-[1.15] mb-8 drop-shadow-2xl">
                    Ko’p Qavatli Turar-joy Binolari Qurilishini <br class="hidden sm:block" />
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-amber-400 via-purple-400 to-cyan-400">
                        Avtomatlashtiring!
                    </span>
                </h1>

                <!-- Hero Subtitle -->
                <p class="text-base sm:text-xl text-gray-300 max-w-3xl mb-10 leading-relaxed font-normal">
                    <strong class="text-white font-semibold">IQS ERP</strong> (Men Quraman va Sotaman) — bu yangi avlod ekotizimi bo‘lib, ko'p qavatli turar-joy binolari qurilishi, smeta hisob-kitobi, omborxona, moliya va sotuv bo‘limlari uchun barcha jarayonlarni yagona tizimda boshqarish imkonini beradi.
                </p>

                <!-- Hero CTA Buttons -->
                <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 w-full max-w-2xl mb-16">
                    <button @click="showDemo = true" class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-400 to-amber-600 text-black font-extrabold text-lg shadow-[0_0_30px_rgba(245,158,11,0.5)] hover:shadow-[0_0_45px_rgba(245,158,11,0.8)] hover:scale-105 transition-all duration-300 flex items-center justify-center gap-2">
                        <span>⚡ Demo rejimda sinab ko'rish</span>
                    </button>

                    <Link :href="route('promo.offers')" class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/50 text-emerald-300 font-bold text-lg backdrop-blur-md shadow-[0_0_25px_rgba(16,185,129,0.2)] hover:scale-105 transition-all duration-300 flex items-center justify-center gap-2">
                        <span>🏢 Uy sotib olish</span>
                    </Link>

                    <Link :href="route('login')" class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/15 text-white font-bold text-lg backdrop-blur-md hover:scale-105 transition-all duration-300">
                        Tizimga kirish
                    </Link>
                </div>

                <!-- Hero Interactive Dashboard Visual Mockup -->
                <div class="w-full max-w-5xl relative group perspective-1000">
                    <div class="absolute -inset-1 bg-gradient-to-r from-amber-500 via-purple-600 to-blue-600 rounded-3xl blur-xl opacity-30 group-hover:opacity-60 transition duration-1000"></div>
                    
                    <div class="relative bg-[#0e0e14]/90 border border-white/15 rounded-3xl p-4 sm:p-8 shadow-2xl backdrop-blur-2xl text-left overflow-hidden">
                        
                        <!-- Top mockup bar -->
                        <div class="flex items-center justify-between border-b border-white/10 pb-4 mb-6">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-red-500/80 inline-block"></span>
                                <span class="w-3 h-3 rounded-full bg-yellow-500/80 inline-block"></span>
                                <span class="w-3 h-3 rounded-full bg-green-500/80 inline-block"></span>
                                <span class="ml-3 text-xs font-mono text-gray-400">iqs-erp.cloud/dashboard • Real-time Monitoring</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 text-xs font-semibold flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Live Server 200 OK
                                </span>
                            </div>
                        </div>

                        <!-- Grid Mockup Stats -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                            <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
                                <div class="text-xs text-gray-400 mb-1">Jami Sotuvlar</div>
                                <div class="text-xl sm:text-2xl font-black text-amber-400">85.35 млрд</div>
                                <div class="text-[11px] text-emerald-400 mt-1 font-medium">↑ +24.8% bu oy</div>
                            </div>
                            <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
                                <div class="text-xs text-gray-400 mb-1">Mavjud Xonadonlar</div>
                                <div class="text-xl sm:text-2xl font-black text-cyan-400">328 ta</div>
                                <div class="text-[11px] text-gray-400 mt-1 font-medium">Shaxmatka sinxron</div>
                            </div>
                            <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
                                <div class="text-xs text-gray-400 mb-1">Smeta va Xarajatlar</div>
                                <div class="text-xl sm:text-2xl font-black text-purple-400">100% Nazorat</div>
                                <div class="text-[11px] text-purple-300 mt-1 font-medium">Avtomat ekspertiza</div>
                            </div>
                            <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
                                <div class="text-xs text-gray-400 mb-1">Mijozlar Boshqaruvi</div>
                                <div class="text-xl sm:text-2xl font-black text-emerald-400">CRM + SMS</div>
                                <div class="text-[11px] text-emerald-300 mt-1 font-medium">2-Ilova Parkovka</div>
                            </div>
                        </div>

                        <!-- Visual Bars Preview -->
                        <div class="p-4 sm:p-6 rounded-2xl bg-black/40 border border-white/5 flex flex-col gap-3">
                            <div class="flex items-center justify-between text-xs text-gray-400">
                                <span>Qurilish Obyektlari (A-1, B-1 Bloklar & Podval Savdo Do'konlari)</span>
                                <span class="text-amber-400 font-mono font-bold">92% Bajarildi</span>
                            </div>
                            <div class="w-full h-3 rounded-full bg-white/10 overflow-hidden flex">
                                <div class="h-full bg-gradient-to-r from-amber-500 via-purple-500 to-cyan-400 w-[92%] rounded-full"></div>
                            </div>
                        </div>

                    </div>
                </div>

            </section>

            <!-- MODULLAR (FEATURES) SECTION -->
            <section id="modules" class="py-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-white/10">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight mb-6">
                        Obyektlar, Moliya, Omborxona Va Sotuvni <br />
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-amber-400 via-purple-400 to-blue-400">
                            Yagona Tizimda Boshqaring!
                        </span>
                    </h2>
                    <p class="text-gray-400 text-base sm:text-lg">
                        Endi barcha ma'lumotlar bir joyda jamlanib, nazorat qilish yanada oson, shaffof va samarali bo‘ladi.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <!-- Module 1: CRM -->
                    <div class="p-8 sm:p-10 rounded-3xl bg-white/[0.03] border border-white/10 hover:border-amber-500/40 hover:bg-white/[0.05] transition-all duration-300 group">
                        <div class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-[0_0_20px_rgba(245,158,11,0.2)]">
                            💼
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-3 flex items-center gap-2">
                            CRM va Shartnomalar
                        </h3>
                        <p class="text-gray-400 leading-relaxed mb-6">
                            Mijozlar bilan ishlashni to'liq avtomatlashtiring: lidlar, 11-bo'limli rasmiy shartnomalar, avtoturargoh 2-ilovalari va sotuv jarayonlarini yagona tizimda boshqaring.
                        </p>
                        <div class="flex flex-wrap gap-2 text-xs font-semibold text-amber-300">
                            <span class="px-3 py-1 rounded-lg bg-amber-500/10 border border-amber-500/20">Lidlar bazasi</span>
                            <span class="px-3 py-1 rounded-lg bg-amber-500/10 border border-amber-500/20">PDF/DOCX Generator</span>
                            <span class="px-3 py-1 rounded-lg bg-amber-500/10 border border-amber-500/20">SMS Eslatmalar</span>
                        </div>
                    </div>

                    <!-- Module 2: Dashboard -->
                    <div class="p-8 sm:p-10 rounded-3xl bg-white/[0.03] border border-white/10 hover:border-purple-500/40 hover:bg-white/[0.05] transition-all duration-300 group">
                        <div class="w-16 h-16 rounded-2xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-[0_0_20px_rgba(168,85,247,0.2)]">
                            📊
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-3 flex items-center gap-2">
                            Analitik Dashboard
                        </h3>
                        <p class="text-gray-400 leading-relaxed mb-6">
                            Loyihalar, tushumlar, qarzdorliklar, smeta xarajatlari va sotuvlar bo‘yicha barcha asosiy moliyaviy ko‘rsatkichlarni real vaqt rejimida grafiklarda kuzatib boring.
                        </p>
                        <div class="flex flex-wrap gap-2 text-xs font-semibold text-purple-300">
                            <span class="px-3 py-1 rounded-lg bg-purple-500/10 border border-purple-500/20">Real-vaqt grafiklar</span>
                            <span class="px-3 py-1 rounded-lg bg-purple-500/10 border border-purple-500/20">Kassa & Bank</span>
                            <span class="px-3 py-1 rounded-lg bg-purple-500/10 border border-purple-500/20">KPI tahlil</span>
                        </div>
                    </div>

                    <!-- Module 3: Obyektlar -->
                    <div class="p-8 sm:p-10 rounded-3xl bg-white/[0.03] border border-white/10 hover:border-cyan-500/40 hover:bg-white/[0.05] transition-all duration-300 group">
                        <div class="w-16 h-16 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-[0_0_20px_rgba(6,182,212,0.2)]">
                            🏗️
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-3 flex items-center gap-2">
                            Obyektlar va Shaxmatka
                        </h3>
                        <p class="text-gray-400 leading-relaxed mb-6">
                            Yangi uy-joy majmualari, bloklar (A-1, B-1), qavatlar, xonadonlar, savdo do‘konlari va yerosti/yerusti parkovkalarni interaktiv shaxmatkada onlayn nazorat qiling.
                        </p>
                        <div class="flex flex-wrap gap-2 text-xs font-semibold text-cyan-300">
                            <span class="px-3 py-1 rounded-lg bg-cyan-500/10 border border-cyan-500/20">Interaktiv Shaxmatka</span>
                            <span class="px-3 py-1 rounded-lg bg-cyan-500/10 border border-cyan-500/20">Do'kon & Parkovka</span>
                            <span class="px-3 py-1 rounded-lg bg-cyan-500/10 border border-cyan-500/20">Band qilish</span>
                        </div>
                    </div>

                    <!-- Module 4: Omborxona -->
                    <div class="p-8 sm:p-10 rounded-3xl bg-white/[0.03] border border-white/10 hover:border-emerald-500/40 hover:bg-white/[0.05] transition-all duration-300 group">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-[0_0_20px_rgba(16,185,129,0.2)]">
                            📦
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-3 flex items-center gap-2">
                            Ombor (Sklad) va Smeta
                        </h3>
                        <p class="text-gray-400 leading-relaxed mb-6">
                            Qurilish materiallari (armatura, beton, g'isht) kirim-chiqimi, texnikalar ijarasi, ta'minotchilar hisobi va tasdiqlangan ekspertiza smetasini aniq nazorat qiling.
                        </p>
                        <div class="flex flex-wrap gap-2 text-xs font-semibold text-emerald-300">
                            <span class="px-3 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20">Materiallar hisobi</span>
                            <span class="px-3 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20">Loyiha smetasi</span>
                            <span class="px-3 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20">Ta'minotchilar</span>
                        </div>
                    </div>

                </div>
            </section>

            <!-- AFZALLIKLAR (BENEFITS) SECTION -->
            <section id="benefits" class="py-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-white/10">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <div class="inline-block px-4 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold uppercase tracking-wider mb-4">
                        Afzalliklar
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight mb-6">
                        Nega Aynan IQS ERP?
                    </h2>
                    <p class="text-gray-400 text-base sm:text-lg">
                        Bizning tizim ko'p qavatli turar-joy binolari qurilishini va sotuv jarayonlarini yanada samarali boshqaradi. Real vaqt rejimidagi nazorat va moliyaviy shaffoflik orqali biznesingizni yangi bosqichga olib chiqing.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    <div class="p-6 rounded-2xl bg-[#0e0e14] border border-white/10 hover:border-amber-500/40 transition-all flex flex-col gap-4">
                        <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-2xl">
                            🏛️
                        </div>
                        <h4 class="text-xl font-bold text-white">Obyektlar ustidan to‘liq nazorat</h4>
                        <p class="text-sm text-gray-400 leading-relaxed">
                            Qurilish obyektlari, bloklar, qavatlar va xonadonlarning sotuv jarayonini real vaqt rejimida shaxmatka orqali kuzatish.
                        </p>
                    </div>

                    <div class="p-6 rounded-2xl bg-[#0e0e14] border border-white/10 hover:border-purple-500/40 transition-all flex flex-col gap-4">
                        <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-2xl">
                            🤝
                        </div>
                        <h4 class="text-xl font-bold text-white">CRM va mijozlar bilan ishlash</h4>
                        <p class="text-sm text-gray-400 leading-relaxed">
                            Lidlar, mijozlar, bo'lib to'lash grafiki va rasmiy 11-bo'limli shartnomalarni yagona tizimda boshqarib, sotuvlarni oshirish.
                        </p>
                    </div>

                    <div class="p-6 rounded-2xl bg-[#0e0e14] border border-white/10 hover:border-emerald-500/40 transition-all flex flex-col gap-4">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-2xl">
                            💵
                        </div>
                        <h4 class="text-xl font-bold text-white">Moliyaviy shaffoflik</h4>
                        <p class="text-sm text-gray-400 leading-relaxed">
                            Mijoz to‘lovlari, kassa tushumlari, bank hisobotlari, kontragentlar va xarajatlarni bir joyda 100% aniqlikda nazorat qilish.
                        </p>
                    </div>

                    <div class="p-6 rounded-2xl bg-[#0e0e14] border border-white/10 hover:border-cyan-500/40 transition-all flex flex-col gap-4">
                        <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-2xl">
                            👥
                        </div>
                        <h4 class="text-xl font-bold text-white">Xodimlarni boshqarish</h4>
                        <p class="text-sm text-gray-400 leading-relaxed">
                            Xodimlar, maoshlar, KPI ko'rsatkichlari va rollarni (menedjerlar, buxgalterlar, kassa va omborxona mudiri) samarali taqsimlash.
                        </p>
                    </div>

                    <div class="p-6 rounded-2xl bg-[#0e0e14] border border-white/10 hover:border-blue-500/40 transition-all flex flex-col gap-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/30 flex items-center justify-center text-2xl">
                            🚚
                        </div>
                        <h4 class="text-xl font-bold text-white">Ombor va materiallar</h4>
                        <p class="text-sm text-gray-400 leading-relaxed">
                            Qurilish materiallari buyurtmasi, kirim-chiqim aktlari va zaxiralarni har bir bino va blok bo'yicha aniq hisobda yuritish.
                        </p>
                    </div>

                    <div class="p-6 rounded-2xl bg-[#0e0e14] border border-white/10 hover:border-pink-500/40 transition-all flex flex-col gap-4">
                        <div class="w-12 h-12 rounded-xl bg-pink-500/10 border border-pink-500/30 flex items-center justify-center text-2xl">
                            📈
                        </div>
                        <h4 class="text-xl font-bold text-white">Hisobotlar va KPI</h4>
                        <p class="text-sm text-gray-400 leading-relaxed">
                            Barcha loyihalar bo'yicha yig'ma moliyaviy aktlar, tahliliy hisobotlar va ekspert xulosalari orqali tezkor boshqaruv qarorlarini qabul qilish.
                        </p>
                    </div>

                </div>
            </section>

            <!-- 3 QADAMDA SODDALASHTIRILGAN AVTOMATLASHTIRISH -->
            <section id="services" class="py-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-white/10">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight mb-6">
                        Soddalashtirilgan Avtomatlashtirish 3 Qadamda
                    </h2>
                    <p class="text-gray-400 text-base sm:text-lg">
                        Qurilish va sotuv jarayonlarini tez va oson boshqarishni boshlang — IQS ERP yordamida barchasi bir necha qadamda.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    
                    <div class="p-8 rounded-3xl bg-[#0e0e14] border border-white/10 flex flex-col justify-between">
                        <div>
                            <div class="text-5xl font-black text-amber-400/30 mb-4 font-mono">01</div>
                            <h3 class="text-2xl font-bold text-white mb-3">Tizimni ulash</h3>
                            <p class="text-gray-400 text-sm leading-relaxed">
                                IQS ERP’ni kompaniyangiz jarayonlariga ulash — obyektlar, bloklar va xonadonlar bazasini import qilib, qo‘shimcha murakkab sozlamalarsiz tezkor start oling.
                            </p>
                        </div>
                    </div>

                    <div class="p-8 rounded-3xl bg-[#0e0e14] border border-white/10 flex flex-col justify-between">
                        <div>
                            <div class="text-5xl font-black text-purple-400/30 mb-4 font-mono">02</div>
                            <h3 class="text-2xl font-bold text-white mb-3">Jarayonlarni kuzatish</h3>
                            <p class="text-gray-400 text-sm leading-relaxed">
                                CRM, obyektlar, to‘lovlar grafigi, shartnomalar va omborxona materiallar harakatini real vaqt rejimida to'liq nazorat qiling.
                            </p>
                        </div>
                    </div>

                    <div class="p-8 rounded-3xl bg-[#0e0e14] border border-white/10 flex flex-col justify-between">
                        <div>
                            <div class="text-5xl font-black text-cyan-400/30 mb-4 font-mono">03</div>
                            <h3 class="text-2xl font-bold text-white mb-3">Qarorlarni amalga oshiring</h3>
                            <p class="text-gray-400 text-sm leading-relaxed">
                                Avtomatik hisobotlar va KPI ko'rsatkichlari orqali xarajatlarni tejang, sotuvlarni oshiring va foydani ko'paytiring.
                            </p>
                        </div>
                    </div>

                </div>
            </section>

            <!-- TESTIMONIALS (FIKRLAR) -->
            <section id="testimonials" class="py-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-white/10">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <div class="inline-block px-4 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold uppercase tracking-wider mb-4">
                        Mijozlarimizning fikri
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight mb-6">
                        Biz Haqimizda Boshqalar Nima Deyishmoqda?
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    <div class="p-8 rounded-3xl bg-[#0e0e14] border border-white/10 flex flex-col justify-between">
                        <p class="text-gray-300 text-sm leading-relaxed mb-6 italic">
                            “Boshida IQS tizimini o'rnatishga ikkilangan edik. Tavakkal qilib o'rnatishga qaror qildik va bu qaror kompaniyamizning o'sishi, xarajatlar miqdorining keskin pasayishi, xodimlar ustidan to'liq nazorat va adolat o'rnatuvchi poydevor vazifasini o'tadi!”
                        </p>
                        <div class="flex items-center gap-3 border-t border-white/10 pt-4">
                            <div class="w-10 h-10 rounded-full bg-amber-500/20 text-amber-400 font-bold flex items-center justify-center">AR</div>
                            <div>
                                <h5 class="text-sm font-bold text-white">Azizbek R.</h5>
                                <span class="text-xs text-gray-400">Qurilish kompaniyasi rahbari</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-8 rounded-3xl bg-[#0e0e14] border border-white/10 flex flex-col justify-between">
                        <p class="text-gray-300 text-sm leading-relaxed mb-6 italic">
                            “Oldin qurilishimizning har bir qismi uchun alohida dasturlardan foydalanar edik va ko’p chalkashliklar bo'lardi. IQS ni o’rnatganimizdan so’ng bu muammo bartaraf etildi. Endi omborni ham, moliyani ham, sotuvni ham bitta tizimda ko'ryapmiz!”
                        </p>
                        <div class="flex items-center gap-3 border-t border-white/10 pt-4">
                            <div class="w-10 h-10 rounded-full bg-purple-500/20 text-purple-400 font-bold flex items-center justify-center">OM</div>
                            <div>
                                <h5 class="text-sm font-bold text-white">O‘tkir M.</h5>
                                <span class="text-xs text-gray-400">Sotuv bo‘limi rahbari</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-8 rounded-3xl bg-[#0e0e14] border border-white/10 flex flex-col justify-between">
                        <p class="text-gray-300 text-sm leading-relaxed mb-6 italic">
                            “Hozirgi kunda qarzdorliklar bo'yicha hech qanday muammo bo'lmayapti, barcha mijozlar o'z vaqtida to'lamoqda. IQS orqali har bir mijozga to'lov muddati yaqinlashganda avtomatik SMS yuboriladi. Ishimizni 2x ga tezlashtirdi!”
                        </p>
                        <div class="flex items-center gap-3 border-t border-white/10 pt-4">
                            <div class="w-10 h-10 rounded-full bg-cyan-500/20 text-cyan-400 font-bold flex items-center justify-center">AS</div>
                            <div>
                                <h5 class="text-sm font-bold text-white">Ahmad S.</h5>
                                <span class="text-xs text-gray-400">Ombor va ta'minot menejeri</span>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <!-- PARTNERS / OBJECKLAR -->
            <section class="py-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-white/10 text-center">
                <h4 class="text-sm font-semibold text-gray-400 uppercase tracking-widest mb-10">
                    Bizning Tizim Orqali Boshqarilayotgan Loyihalar
                </h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
                    <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5 font-bold text-gray-400 hover:text-white transition-colors">Sher-Dor Residence</div>
                    <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5 font-bold text-gray-400 hover:text-white transition-colors">Hayot House</div>
                    <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5 font-bold text-gray-400 hover:text-white transition-colors">Baraka Residence</div>
                    <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5 font-bold text-gray-400 hover:text-white transition-colors">Delta Construction</div>
                    <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5 font-bold text-gray-400 hover:text-white transition-colors">Hakim O'g'lu</div>
                    <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5 font-bold text-gray-400 hover:text-white transition-colors">Göztepe Global</div>
                </div>
            </section>

            <!-- FAQ SECTION -->
            <section id="faq" class="py-24 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto border-t border-white/10">
                <div class="text-center mb-16">
                    <div class="inline-block px-4 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold uppercase tracking-wider mb-4">
                        Savollar
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight mb-4">
                        Tez-Tez So‘raladigan Savollar
                    </h2>
                    <p class="text-gray-400">
                        IQS ERP bo‘yicha eng ko‘p beriladigan savollarga aniq javoblar.
                    </p>
                </div>

                <div class="space-y-4">
                    <div v-for="(faq, i) in faqs" :key="i" class="rounded-2xl bg-[#0e0e14] border border-white/10 overflow-hidden transition-all">
                        <button @click="toggleFaq(i)" class="w-full px-6 py-5 text-left font-semibold text-lg flex items-center justify-between gap-4 hover:text-amber-400 transition-colors">
                            <span>{{ faq.q }}</span>
                            <span class="text-xl font-mono text-amber-400 transition-transform duration-200" :class="{ 'rotate-45': activeFaq === i }">+</span>
                        </button>
                        <div v-show="activeFaq === i" class="px-6 pb-5 text-sm text-gray-400 leading-relaxed border-t border-white/5 pt-4">
                            {{ faq.a }}
                        </div>
                    </div>
                </div>
            </section>

        </main>

        <!-- FOOTER -->
        <footer class="border-t border-white/10 bg-[#050508] py-16 px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">
                <div class="md:col-span-2">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-purple-600 flex items-center justify-center font-black text-lg text-black">
                            IQS
                        </div>
                        <span class="text-2xl font-black text-white">IQS ERP</span>
                    </div>
                    <p class="text-gray-400 text-sm leading-relaxed max-w-md mb-6">
                        <strong>IQS</strong> (Qur va Sotish — Men quraman va sotaman!) — Ko'p qavatli turar-joy binolari qurilishini va sotuv jarayonlarini avtomatlashtirish uchun yagona tizim. Hisobotlar, mijozlar, sotuv, moliya va omborxona boshqaruvi — barchasi bir joyda.
                    </p>
                    <div class="text-xs text-gray-500 font-mono">
                        Server: Multi-tenant Cloud Infrastructure • 200 OK
                    </div>
                </div>

                <div>
                    <h5 class="text-white font-bold text-sm uppercase tracking-wider mb-4">Bo'limlar</h5>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="#home" class="hover:text-amber-400 transition-colors">Bosh sahifa</a></li>
                        <li><a href="#modules" class="hover:text-amber-400 transition-colors">Modullar</a></li>
                        <li><a href="#benefits" class="hover:text-amber-400 transition-colors">Afzalliklar</a></li>
                        <li><a href="#faq" class="hover:text-amber-400 transition-colors">FAQ</a></li>
                        <li><Link :href="route('promo.offers')" class="text-emerald-400 hover:underline">Uy sotib olish</Link></li>
                    </ul>
                </div>

                <div>
                    <h5 class="text-white font-bold text-sm uppercase tracking-wider mb-4">Aloqa</h5>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="tel:+998977084444" class="hover:text-amber-400 transition-colors">+998 (97) 708-44-44</a></li>
                        <li><a href="tel:+998935732929" class="hover:text-amber-400 transition-colors">+998 (93) 573-29-29</a></li>
                        <li class="text-gray-500">Urgut shahar, Navoiy ko'chasi 83-uy</li>
                    </ul>
                </div>
            </div>

            <div class="max-w-7xl mx-auto border-t border-white/10 pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-gray-500 gap-4">
                <p>© 2026 IQS (Qur va Sot). Barcha huquqlar himoyalangan.</p>
                <p class="text-gray-400">Developed with Advanced Architecture</p>
            </div>
        </footer>

        <!-- DEMO REQUEST MODAL -->
        <div v-if="showDemo" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
            <div class="relative w-full max-w-lg p-8 rounded-3xl bg-[#0f0f17] border border-amber-500/30 shadow-[0_0_50px_rgba(245,158,11,0.2)] text-left">
                
                <button @click="showDemo = false" class="absolute top-5 right-5 text-gray-400 hover:text-white text-xl">✕</button>

                <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-2xl mb-4">
                    ⚡
                </div>

                <h3 class="text-2xl font-black text-white mb-2">IQS ERP Demo Olish</h3>
                <p class="text-sm text-gray-400 mb-6">Kompaniyangiz uchun IQS tizimining to'liq imkoniyatlarini bepul sinab ko'ring.</p>

                <div v-if="demoSubmitted" class="p-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-center">
                    <div class="text-3xl mb-2">✅</div>
                    <div class="font-bold text-lg mb-1">So'rovingiz qabul qilindi!</div>
                    <p class="text-xs text-gray-300">Tez orada mutaxassislarimiz siz bilan bog'lanishadi.</p>
                </div>

                <form v-else @submit.prevent="submitDemo" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Ismingiz *</label>
                        <input v-model="demoForm.name" required type="text" placeholder="Azizbek Rahimov" class="w-full px-4 py-3 rounded-xl bg-black/40 border border-white/15 text-white placeholder-gray-500 focus:border-amber-400 focus:outline-none text-sm" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Kompaniya nomi</label>
                        <input v-model="demoForm.company" type="text" placeholder="Sher-Dor Qurilish MCHJ" class="w-full px-4 py-3 rounded-xl bg-black/40 border border-white/15 text-white placeholder-gray-500 focus:border-amber-400 focus:outline-none text-sm" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-300 mb-1">Telefon raqam *</label>
                        <input v-model="demoForm.phone" required type="tel" placeholder="+998 (90) 123-45-67" class="w-full px-4 py-3 rounded-xl bg-black/40 border border-white/15 text-white placeholder-gray-500 focus:border-amber-400 focus:outline-none text-sm" />
                    </div>

                    <button type="submit" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-black font-extrabold text-sm shadow-[0_0_20px_rgba(245,158,11,0.4)] transition-all">
                        So'rov yuborish
                    </button>
                </form>

            </div>
        </div>

    </div>
</template>

<style scoped>
.perspective-1000 {
    perspective: 1000px;
}
</style>
