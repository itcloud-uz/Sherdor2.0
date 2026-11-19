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
</script>

<template>
    <Head title="IQS ERP - Qurilish va Sotuv Jarayonlarini Avtomatlashtirish Tizimi" />
    
    <div class="min-h-screen bg-white text-slate-800 font-sans selection:bg-orange-500 selection:text-white">
        
        <!-- Sticky Header / Navbar -->
        <header class="sticky top-0 z-50 backdrop-blur-md bg-white/95 border-b border-slate-200 transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                
                <!-- Logo -->
                <a href="#home" class="flex items-center gap-3 group">
                    <div class="w-11 h-11 rounded-xl bg-[#0f172a] border border-slate-800 flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform">
                        <span class="font-black text-xl tracking-tighter text-orange-500">IQS</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-2xl font-black tracking-tight text-[#0f172a] flex items-center gap-1.5">
                            IQS <span class="text-[11px] px-2 py-0.5 rounded-full bg-orange-50 text-orange-600 border border-orange-200 font-bold uppercase tracking-wider">ERP</span>
                        </span>
                        <span class="text-[10px] text-slate-500 font-medium tracking-wider uppercase">Qur & Sot • Men Quraman va Sotaman</span>
                    </div>
                </a>

                <!-- Nav Links (Desktop) -->
                <nav class="hidden md:flex items-center space-x-8 text-sm font-semibold text-slate-600">
                    <a href="#home" class="hover:text-orange-600 transition-colors">Bosh sahifa</a>
                    <a href="#modules" class="hover:text-orange-600 transition-colors">Modullar</a>
                    <a href="#benefits" class="hover:text-orange-600 transition-colors">Afzalliklar</a>
                    <a href="#services" class="hover:text-orange-600 transition-colors">Qadamlar</a>
                    <a href="#testimonials" class="hover:text-orange-600 transition-colors">Fikrlar</a>
                    <a href="#faq" class="hover:text-orange-600 transition-colors">FAQ</a>
                </nav>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <Link :href="route('promo.offers')" class="hidden sm:inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 text-sm font-bold transition-all shadow-sm">
                        <span>🏢</span> Uy sotib olish
                    </Link>

                    <Link :href="route('login')" class="px-6 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm shadow-md shadow-orange-500/20 hover:shadow-orange-500/30 transition-all transform hover:scale-[1.02]">
                        Kirish
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="relative">

            <!-- HERO SECTION -->
            <section id="home" class="pt-16 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto flex flex-col items-center text-center">
                
                <!-- Trust Badge -->
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-orange-200 bg-orange-50 mb-8 shadow-sm">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-orange-500"></span>
                    </span>
                    <span class="text-xs sm:text-sm font-bold text-orange-800">Bizga 20+ yirik qurilish kompaniyasi ishonmoqda</span>
                </div>

                <!-- Hero Title -->
                <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-[#0f172a] max-w-5xl leading-[1.15] mb-6">
                    Ko’p Qavatli Turar-joy Binolari Qurilishini <br class="hidden sm:block" />
                    <span class="text-orange-600">
                        Avtomatlashtiring!
                    </span>
                </h1>

                <!-- Hero Subtitle -->
                <p class="text-base sm:text-xl text-slate-600 max-w-3xl mb-10 leading-relaxed font-normal">
                    <strong class="text-[#0f172a] font-semibold">IQS ERP</strong> (Men Quraman va Sotaman) — bu yangi avlod ekotizimi bo‘lib, ko'p qavatli turar-joy binolari qurilishi, smeta hisob-kitobi, omborxona, moliya va sotuv bo‘limlari uchun barcha jarayonlarni yagona tizimda boshqarish imkonini beradi.
                </p>

                <!-- Hero CTA Buttons -->
                <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-5 w-full max-w-2xl mb-16">
                    <button @click="showDemo = true" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-extrabold text-base sm:text-lg shadow-lg shadow-orange-500/25 hover:shadow-orange-500/40 hover:scale-[1.02] transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer">
                        <span>⚡ Demo rejimda sinab ko'rish</span>
                    </button>

                    <Link :href="route('promo.offers')" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-base sm:text-lg shadow-md shadow-slate-900/10 hover:scale-[1.02] transition-all duration-200 flex items-center justify-center gap-2">
                        <span>🏢 Uy sotib olish</span>
                    </Link>

                    <Link :href="route('login')" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-white hover:bg-slate-50 border border-slate-300 text-slate-800 font-bold text-base sm:text-lg shadow-sm hover:scale-[1.02] transition-all duration-200">
                        Tizimga kirish
                    </Link>
                </div>

                <!-- Hero Interactive Dashboard Visual Mockup -->
                <div class="w-full max-w-5xl">
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 sm:p-8 shadow-xl text-left overflow-hidden">
                        
                        <!-- Top mockup bar -->
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-red-400 inline-block"></span>
                                <span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span>
                                <span class="w-3 h-3 rounded-full bg-emerald-400 inline-block"></span>
                                <span class="ml-3 text-xs font-mono text-slate-500">iqs-erp.cloud/dashboard • Real-time Monitoring</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span> Live Server 200 OK
                                </span>
                            </div>
                        </div>

                        <!-- Grid Mockup Stats -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
                                <div class="text-xs font-semibold text-slate-500 mb-1">Jami Sotuvlar</div>
                                <div class="text-xl sm:text-2xl font-black text-[#0f172a]">85.35 mlrd</div>
                                <div class="text-[11px] text-emerald-600 mt-1 font-bold">↑ +24.8% bu oy</div>
                            </div>
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
                                <div class="text-xs font-semibold text-slate-500 mb-1">Mavjud Xonadonlar</div>
                                <div class="text-xl sm:text-2xl font-black text-orange-600">328 ta</div>
                                <div class="text-[11px] text-slate-500 mt-1 font-medium">Shaxmatka sinxron</div>
                            </div>
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
                                <div class="text-xs font-semibold text-slate-500 mb-1">Smeta va Xarajatlar</div>
                                <div class="text-xl sm:text-2xl font-black text-[#1e3a8a]">100% Nazorat</div>
                                <div class="text-[11px] text-slate-500 mt-1 font-medium">Avtomat ekspertiza</div>
                            </div>
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
                                <div class="text-xs font-semibold text-slate-500 mb-1">Mijozlar Boshqaruvi</div>
                                <div class="text-xl sm:text-2xl font-black text-[#0f172a]">CRM + SMS</div>
                                <div class="text-[11px] text-slate-500 mt-1 font-medium">2-Ilova Parkovka</div>
                            </div>
                        </div>

                        <!-- Visual Bars Preview -->
                        <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200/80 flex flex-col gap-3">
                            <div class="flex items-center justify-between text-xs font-medium text-slate-600">
                                <span>Qurilish Obyektlari (A-1, B-1 Bloklar & Podval Savdo Do'konlari)</span>
                                <span class="text-orange-600 font-mono font-bold">92% Bajarildi</span>
                            </div>
                            <div class="w-full h-3 rounded-full bg-slate-200 overflow-hidden flex">
                                <div class="h-full bg-gradient-to-r from-orange-500 to-amber-500 w-[92%] rounded-full"></div>
                            </div>
                        </div>

                    </div>
                </div>

            </section>

            <!-- MODULLAR (FEATURES) SECTION -->
            <section id="modules" class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-slate-200 bg-slate-50/60 rounded-3xl my-8">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <div class="inline-block px-4 py-1 rounded-full bg-orange-50 border border-orange-200 text-orange-700 text-xs font-bold uppercase tracking-wider mb-4">
                        Modullar
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-[#0f172a] mb-5">
                        Obyektlar, Moliya, Omborxona Va Sotuvni <br />
                        <span class="text-orange-600">
                            Yagona Tizimda Boshqaring!
                        </span>
                    </h2>
                    <p class="text-slate-600 text-base sm:text-lg">
                        Endi barcha ma'lumotlar bir joyda jamlanib, nazorat qilish yanada oson, shaffof va samarali bo‘ladi.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <!-- Module 1: CRM -->
                    <div class="p-8 sm:p-10 rounded-3xl bg-white border border-slate-200 hover:border-orange-300 hover:shadow-lg transition-all duration-200 group">
                        <div class="w-14 h-14 rounded-2xl bg-orange-50 border border-orange-200 flex items-center justify-center text-3xl mb-6 shadow-sm">
                            💼
                        </div>
                        <h3 class="text-2xl font-bold text-[#0f172a] mb-3 flex items-center gap-2">
                            CRM va Shartnomalar
                        </h3>
                        <p class="text-slate-600 leading-relaxed mb-6">
                            Mijozlar bilan ishlashni to'liq avtomatlashtiring: lidlar, 11-bo'limli rasmiy shartnomalar, avtoturargoh 2-ilovalari va sotuv jarayonlarini yagona tizimda boshqaring.
                        </p>
                        <div class="flex flex-wrap gap-2 text-xs font-bold text-slate-700">
                            <span class="px-3 py-1 rounded-lg bg-slate-100 border border-slate-200">Lidlar bazasi</span>
                            <span class="px-3 py-1 rounded-lg bg-slate-100 border border-slate-200">PDF/DOCX Generator</span>
                            <span class="px-3 py-1 rounded-lg bg-slate-100 border border-slate-200">SMS Eslatmalar</span>
                        </div>
                    </div>

                    <!-- Module 2: Dashboard -->
                    <div class="p-8 sm:p-10 rounded-3xl bg-white border border-slate-200 hover:border-orange-300 hover:shadow-lg transition-all duration-200 group">
                        <div class="w-14 h-14 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-3xl mb-6 shadow-sm">
                            📊
                        </div>
                        <h3 class="text-2xl font-bold text-[#0f172a] mb-3 flex items-center gap-2">
                            Analitik Dashboard
                        </h3>
                        <p class="text-slate-600 leading-relaxed mb-6">
                            Loyihalar, tushumlar, qarzdorliklar, smeta xarajatlari va sotuvlar bo‘yicha barcha asosiy moliyaviy ko‘rsatkichlarni real vaqt rejimida grafiklarda kuzatib boring.
                        </p>
                        <div class="flex flex-wrap gap-2 text-xs font-bold text-slate-700">
                            <span class="px-3 py-1 rounded-lg bg-slate-100 border border-slate-200">Real-vaqt grafiklar</span>
                            <span class="px-3 py-1 rounded-lg bg-slate-100 border border-slate-200">Kassa & Bank</span>
                            <span class="px-3 py-1 rounded-lg bg-slate-100 border border-slate-200">KPI tahlil</span>
                        </div>
                    </div>

                    <!-- Module 3: Obyektlar -->
                    <div class="p-8 sm:p-10 rounded-3xl bg-white border border-slate-200 hover:border-orange-300 hover:shadow-lg transition-all duration-200 group">
                        <div class="w-14 h-14 rounded-2xl bg-orange-50 border border-orange-200 flex items-center justify-center text-3xl mb-6 shadow-sm">
                            🏗️
                        </div>
                        <h3 class="text-2xl font-bold text-[#0f172a] mb-3 flex items-center gap-2">
                            Obyektlar va Shaxmatka
                        </h3>
                        <p class="text-slate-600 leading-relaxed mb-6">
                            Yangi uy-joy majmualari, bloklar (A-1, B-1), qavatlar, xonadonlar, savdo do‘konlari va yerosti/yerusti parkovkalarni interaktiv shaxmatkada onlayn nazorat qiling.
                        </p>
                        <div class="flex flex-wrap gap-2 text-xs font-bold text-slate-700">
                            <span class="px-3 py-1 rounded-lg bg-slate-100 border border-slate-200">Interaktiv Shaxmatka</span>
                            <span class="px-3 py-1 rounded-lg bg-slate-100 border border-slate-200">Do'kon & Parkovka</span>
                            <span class="px-3 py-1 rounded-lg bg-slate-100 border border-slate-200">Band qilish</span>
                        </div>
                    </div>

                    <!-- Module 4: Omborxona -->
                    <div class="p-8 sm:p-10 rounded-3xl bg-white border border-slate-200 hover:border-orange-300 hover:shadow-lg transition-all duration-200 group">
                        <div class="w-14 h-14 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-3xl mb-6 shadow-sm">
                            📦
                        </div>
                        <h3 class="text-2xl font-bold text-[#0f172a] mb-3 flex items-center gap-2">
                            Ombor (Sklad) va Smeta
                        </h3>
                        <p class="text-slate-600 leading-relaxed mb-6">
                            Qurilish materiallari (armatura, beton, g'isht) kirim-chiqimi, texnikalar ijarasi, ta'minotchilar hisobi va tasdiqlangan ekspertiza smetasini aniq nazorat qiling.
                        </p>
                        <div class="flex flex-wrap gap-2 text-xs font-bold text-slate-700">
                            <span class="px-3 py-1 rounded-lg bg-slate-100 border border-slate-200">Materiallar hisobi</span>
                            <span class="px-3 py-1 rounded-lg bg-slate-100 border border-slate-200">Loyiha smetasi</span>
                            <span class="px-3 py-1 rounded-lg bg-slate-100 border border-slate-200">Ta'minotchilar</span>
                        </div>
                    </div>

                </div>
            </section>

            <!-- AFZALLIKLAR (BENEFITS) SECTION -->
            <section id="benefits" class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-slate-200">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <div class="inline-block px-4 py-1 rounded-full bg-orange-50 border border-orange-200 text-orange-700 text-xs font-bold uppercase tracking-wider mb-4">
                        Afzalliklar
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-[#0f172a] mb-5">
                        Nega Aynan IQS ERP?
                    </h2>
                    <p class="text-slate-600 text-base sm:text-lg">
                        Bizning tizim ko'p qavatli turar-joy binolari qurilishini va sotuv jarayonlarini yanada samarali boshqaradi. Real vaqt rejimidagi nazorat va moliyaviy shaffoflik orqali biznesingizni yangi bosqichga olib chiqing.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    <div class="p-6 rounded-2xl bg-white border border-slate-200 hover:border-orange-300 hover:shadow-md transition-all flex flex-col gap-4">
                        <div class="w-12 h-12 rounded-xl bg-orange-50 border border-orange-200 flex items-center justify-center text-2xl">
                            🏛️
                        </div>
                        <h4 class="text-xl font-bold text-[#0f172a]">Obyektlar ustidan to‘liq nazorat</h4>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Qurilish obyektlari, bloklar, qavatlar va xonadonlarning sotuv jarayonini real vaqt rejimida shaxmatka orqali kuzatish.
                        </p>
                    </div>

                    <div class="p-6 rounded-2xl bg-white border border-slate-200 hover:border-orange-300 hover:shadow-md transition-all flex flex-col gap-4">
                        <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-2xl">
                            🤝
                        </div>
                        <h4 class="text-xl font-bold text-[#0f172a]">CRM va mijozlar bilan ishlash</h4>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Lidlar, mijozlar, bo'lib to'lash grafiki va rasmiy 11-bo'limli shartnomalarni yagona tizimda boshqarib, sotuvlarni oshirish.
                        </p>
                    </div>

                    <div class="p-6 rounded-2xl bg-white border border-slate-200 hover:border-orange-300 hover:shadow-md transition-all flex flex-col gap-4">
                        <div class="w-12 h-12 rounded-xl bg-orange-50 border border-orange-200 flex items-center justify-center text-2xl">
                            💵
                        </div>
                        <h4 class="text-xl font-bold text-[#0f172a]">Moliyaviy shaffoflik</h4>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Mijoz to‘lovlari, kassa tushumlari, bank hisobotlari, kontragentlar va xarajatlarni bir joyda 100% aniqlikda nazorat qilish.
                        </p>
                    </div>

                    <div class="p-6 rounded-2xl bg-white border border-slate-200 hover:border-orange-300 hover:shadow-md transition-all flex flex-col gap-4">
                        <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-2xl">
                            👥
                        </div>
                        <h4 class="text-xl font-bold text-[#0f172a]">Xodimlarni boshqarish</h4>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Xodimlar, maoshlar, KPI ko'rsatkichlari va rollarni (menedjerlar, buxgalterlar, kassa va omborxona mudiri) samarali taqsimlash.
                        </p>
                    </div>

                    <div class="p-6 rounded-2xl bg-white border border-slate-200 hover:border-orange-300 hover:shadow-md transition-all flex flex-col gap-4">
                        <div class="w-12 h-12 rounded-xl bg-orange-50 border border-orange-200 flex items-center justify-center text-2xl">
                            🚚
                        </div>
                        <h4 class="text-xl font-bold text-[#0f172a]">Ombor va materiallar</h4>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Qurilish materiallari buyurtmasi, kirim-chiqim aktlari va zaxiralarni har bir bino va blok bo'yicha aniq hisobda yuritish.
                        </p>
                    </div>

                    <div class="p-6 rounded-2xl bg-white border border-slate-200 hover:border-orange-300 hover:shadow-md transition-all flex flex-col gap-4">
                        <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-2xl">
                            📈
                        </div>
                        <h4 class="text-xl font-bold text-[#0f172a]">Hisobotlar va KPI</h4>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Barcha loyihalar bo'yicha yig'ma moliyaviy aktlar, tahliliy hisobotlar va ekspert xulosalari orqali tezkor boshqaruv qarorlarini qabul qilish.
                        </p>
                    </div>

                </div>
            </section>

            <!-- 3 QADAMDA SODDALASHTIRILGAN AVTOMATLASHTIRISH -->
            <section id="services" class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-slate-200 bg-slate-50/60 rounded-3xl my-8">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-[#0f172a] mb-5">
                        Soddalashtirilgan Avtomatlashtirish 3 Qadamda
                    </h2>
                    <p class="text-slate-600 text-base sm:text-lg">
                        Qurilish va sotuv jarayonlarini tez va oson boshqarishni boshlang — IQS ERP yordamida barchasi bir necha qadamda.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    
                    <div class="p-8 rounded-3xl bg-white border border-slate-200 flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                        <div>
                            <div class="text-5xl font-black text-orange-400/40 mb-4 font-mono">01</div>
                            <h3 class="text-2xl font-bold text-[#0f172a] mb-3">Tizimni ulash</h3>
                            <p class="text-slate-600 text-sm leading-relaxed">
                                IQS ERP’ni kompaniyangiz jarayonlariga ulash — obyektlar, bloklar va xonadonlar bazasini import qilib, qo‘shimcha murakkab sozlamalarsiz tezkor start oling.
                            </p>
                        </div>
                    </div>

                    <div class="p-8 rounded-3xl bg-white border border-slate-200 flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                        <div>
                            <div class="text-5xl font-black text-slate-300 mb-4 font-mono">02</div>
                            <h3 class="text-2xl font-bold text-[#0f172a] mb-3">Jarayonlarni kuzatish</h3>
                            <p class="text-slate-600 text-sm leading-relaxed">
                                CRM, obyektlar, to‘lovlar grafigi, shartnomalar va omborxona materiallar harakatini real vaqt rejimida to'liq nazorat qiling.
                            </p>
                        </div>
                    </div>

                    <div class="p-8 rounded-3xl bg-white border border-slate-200 flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                        <div>
                            <div class="text-5xl font-black text-orange-400/40 mb-4 font-mono">03</div>
                            <h3 class="text-2xl font-bold text-[#0f172a] mb-3">Qarorlarni amalga oshiring</h3>
                            <p class="text-slate-600 text-sm leading-relaxed">
                                Avtomatik hisobotlar va KPI ko'rsatkichlari orqali xarajatlarni tejang, sotuvlarni oshiring va foydani ko'paytiring.
                            </p>
                        </div>
                    </div>

                </div>
            </section>

            <!-- TESTIMONIALS (FIKRLAR) -->
            <section id="testimonials" class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-slate-200">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <div class="inline-block px-4 py-1 rounded-full bg-orange-50 border border-orange-200 text-orange-700 text-xs font-bold uppercase tracking-wider mb-4">
                        Mijozlarimizning fikri
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-[#0f172a] mb-6">
                        Biz Haqimizda Boshqalar Nima Deyishmoqda?
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    <div class="p-8 rounded-3xl bg-white border border-slate-200 flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                        <p class="text-slate-700 text-sm leading-relaxed mb-6 italic">
                            “Boshida IQS tizimini o'rnatishga ikkilangan edik. Tavakkal qilib o'rnatishga qaror qildik va bu qaror kompaniyamizning o'sishi, xarajatlar miqdorining keskin pasayishi, xodimlar ustidan to'liq nazorat va adolat o'rnatuvchi poydevor vazifasini o'tadi!”
                        </p>
                        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
                            <div class="w-10 h-10 rounded-full bg-[#0f172a] text-orange-400 font-bold flex items-center justify-center">AR</div>
                            <div>
                                <h5 class="text-sm font-bold text-[#0f172a]">Azizbek R.</h5>
                                <span class="text-xs text-slate-500">Qurilish kompaniyasi rahbari</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-8 rounded-3xl bg-white border border-slate-200 flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                        <p class="text-slate-700 text-sm leading-relaxed mb-6 italic">
                            “Oldin qurilishimizning har bir qismi uchun alohida dasturlardan foydalanar edik va ko’p chalkashliklar bo'lardi. IQS ni o’rnatganimizdan so’ng bu muammo bartaraf etildi. Endi omborni ham, moliyani ham, sotuvni ham bitta tizimda ko'ryapmiz!”
                        </p>
                        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
                            <div class="w-10 h-10 rounded-full bg-[#0f172a] text-orange-400 font-bold flex items-center justify-center">OM</div>
                            <div>
                                <h5 class="text-sm font-bold text-[#0f172a]">O‘tkir M.</h5>
                                <span class="text-xs text-slate-500">Sotuv bo‘limi rahbari</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-8 rounded-3xl bg-white border border-slate-200 flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                        <p class="text-slate-700 text-sm leading-relaxed mb-6 italic">
                            “Hozirgi kunda qarzdorliklar bo'yicha hech qanday muammo bo'lmayapti, barcha mijozlar o'z vaqtida to'lamoqda. IQS orqali har bir mijozga to'lov muddati yaqinlashganda avtomatik SMS yuboriladi. Ishimizni 2x ga tezlashtirdi!”
                        </p>
                        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
                            <div class="w-10 h-10 rounded-full bg-[#0f172a] text-orange-400 font-bold flex items-center justify-center">AS</div>
                            <div>
                                <h5 class="text-sm font-bold text-[#0f172a]">Ahmad S.</h5>
                                <span class="text-xs text-slate-500">Ombor va ta'minot menejeri</span>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <!-- PARTNERS / OBJECKLAR -->
            <section class="py-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-slate-200 text-center">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-8">
                    Bizning Tizim Orqali Boshqarilayotgan Loyihalar
                </h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 font-bold text-slate-700 hover:text-orange-600 hover:border-orange-300 transition-colors">Sher-Dor Residence</div>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 font-bold text-slate-700 hover:text-orange-600 hover:border-orange-300 transition-colors">Hayot House</div>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 font-bold text-slate-700 hover:text-orange-600 hover:border-orange-300 transition-colors">Baraka Residence</div>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 font-bold text-slate-700 hover:text-orange-600 hover:border-orange-300 transition-colors">Delta Construction</div>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 font-bold text-slate-700 hover:text-orange-600 hover:border-orange-300 transition-colors">Hakim O'g'lu</div>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 font-bold text-slate-700 hover:text-orange-600 hover:border-orange-300 transition-colors">Göztepe Global</div>
                </div>
            </section>

            <!-- FAQ SECTION -->
            <section id="faq" class="py-20 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto border-t border-slate-200">
                <div class="text-center mb-16">
                    <div class="inline-block px-4 py-1 rounded-full bg-orange-50 border border-orange-200 text-orange-700 text-xs font-bold uppercase tracking-wider mb-4">
                        Savollar
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-[#0f172a] mb-4">
                        Tez-Tez So‘raladigan Savollar
                    </h2>
                    <p class="text-slate-600">
                        IQS ERP bo‘yicha eng ko‘p beriladigan savollarga aniq javoblar.
                    </p>
                </div>

                <div class="space-y-4">
                    <div v-for="(faq, i) in faqs" :key="i" class="rounded-2xl bg-white border border-slate-200 overflow-hidden shadow-sm transition-all">
                        <button @click="toggleFaq(i)" class="w-full px-6 py-5 text-left font-bold text-base sm:text-lg text-[#0f172a] flex items-center justify-between gap-4 hover:text-orange-600 transition-colors cursor-pointer">
                            <span>{{ faq.q }}</span>
                            <span class="text-xl font-mono text-orange-500 transition-transform duration-200" :class="{ 'rotate-45': activeFaq === i }">+</span>
                        </button>
                        <div v-show="activeFaq === i" class="px-6 pb-5 text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-4">
                            {{ faq.a }}
                        </div>
                    </div>
                </div>
            </section>

        </main>

        <!-- FOOTER -->
        <footer class="border-t border-slate-800 bg-[#0a192f] text-slate-300 py-16 px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">
                
                <!-- Col 1: IQS ERP & ITCloud Branding -->
                <div class="md:col-span-2">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-orange-500 flex items-center justify-center font-black text-lg text-white">
                            IQS
                        </div>
                        <div class="flex flex-col">
                            <span class="text-2xl font-black text-white">IQS ERP</span>
                            <span class="text-xs text-orange-400 font-semibold uppercase tracking-wider">Qur va Sotish • Men Quraman va Sotaman!</span>
                        </div>
                    </div>
                    <p class="text-slate-400 text-sm leading-relaxed max-w-md mb-6">
                        <strong>IQS ERP</strong> — Ko'p qavatli turar-joy binolari qurilishini va sotuv jarayonlarini avtomatlashtirish uchun yagona innovatsion tizim. Hisobotlar, mijozlar, sotuv, moliya va omborxona boshqaruvi — barchasi bir joyda.
                    </p>
                    
                    <!-- ITCloud Developer Badge -->
                    <div class="p-4 rounded-2xl bg-white/5 border border-slate-700 max-w-md flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-[#0f172a] border border-slate-700 flex items-center justify-center font-black text-sm text-orange-400">
                            ITC
                        </div>
                        <div>
                            <div class="text-xs text-slate-400 font-medium">Boshqaruv va IT Integrator:</div>
                            <a href="https://itcloud.uz" target="_blank" class="text-sm font-bold text-orange-400 hover:underline flex items-center gap-1">
                                «ITCloud» Dasturiy Ta'minot Kompaniyasi
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Col 2: Bo'limlar -->
                <div>
                    <h5 class="text-white font-bold text-sm uppercase tracking-wider mb-4">Bo'limlar</h5>
                    <ul class="space-y-2.5 text-sm text-slate-400">
                        <li><a href="#home" class="hover:text-orange-400 transition-colors">Bosh sahifa</a></li>
                        <li><a href="#modules" class="hover:text-orange-400 transition-colors">Modullar</a></li>
                        <li><a href="#benefits" class="hover:text-orange-400 transition-colors">Afzalliklar</a></li>
                        <li><a href="#services" class="hover:text-orange-400 transition-colors">Qadamlar</a></li>
                        <li><a href="#testimonials" class="hover:text-orange-400 transition-colors">Fikrlar</a></li>
                        <li><a href="#faq" class="hover:text-orange-400 transition-colors">FAQ</a></li>
                        <li><Link :href="route('promo.offers')" class="text-orange-400 hover:underline">Uy sotib olish</Link></li>
                    </ul>
                </div>

                <!-- Col 3: Aloqa & ITCloud Kontaktlari -->
                <div class="md:col-span-1">
                    <h5 class="text-white font-bold text-sm uppercase tracking-wider mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-orange-400"></span> Aloqa & ITCloud
                    </h5>
                    
                    <!-- Phone numbers -->
                    <div class="space-y-2 mb-4">
                        <a href="tel:+998911873700" class="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-white/5 border border-slate-700 hover:border-orange-400/50 hover:bg-white/10 transition-all group">
                            <span class="text-base">📞</span>
                            <div>
                                <span class="text-[10px] text-slate-400 block font-medium">Sotuv va Konsultatsiya:</span>
                                <span class="text-xs font-bold text-white group-hover:text-orange-300 transition-colors">+998 (91) 187-37-00</span>
                            </div>
                        </a>

                        <a href="tel:+998335180005" class="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-white/5 border border-slate-700 hover:border-orange-400/50 hover:bg-white/10 transition-all group">
                            <span class="text-base">📱</span>
                            <div>
                                <span class="text-[10px] text-slate-400 block font-medium">Texnik va Boshqaruv:</span>
                                <span class="text-xs font-bold text-white group-hover:text-orange-300 transition-colors">+998 (33) 518-00-05</span>
                            </div>
                        </a>
                    </div>

                    <!-- Telegram Links -->
                    <div class="space-y-1.5 mb-4">
                        <span class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider block">Telegram:</span>
                        <div class="flex flex-col gap-1.5">
                            <a href="https://t.me/ITclouduz_me" target="_blank" class="inline-flex items-center gap-2 text-xs text-orange-400 hover:text-orange-300 transition-colors">
                                <span class="px-1.5 py-0.5 rounded bg-orange-500/10 border border-orange-500/30 text-[10px] font-bold">TG</span>
                                @ITclouduz_me
                            </a>
                            <a href="https://t.me/itcloud_support" target="_blank" class="inline-flex items-center gap-2 text-xs text-slate-300 hover:text-white transition-colors">
                                <span class="px-1.5 py-0.5 rounded bg-slate-700 border border-slate-600 text-[10px] font-bold">TG</span>
                                @itcloud_support
                            </a>
                        </div>
                    </div>

                    <!-- Instagram Links -->
                    <div class="space-y-1.5">
                        <span class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider block">Instagram:</span>
                        <div class="flex flex-col gap-1.5">
                            <a href="https://instagram.com/itcloud.uz" target="_blank" class="inline-flex items-center gap-2 text-xs text-slate-300 hover:text-white transition-colors">
                                <span class="px-1.5 py-0.5 rounded bg-slate-700 border border-slate-600 text-[10px] font-bold">IG</span>
                                @itcloud.uz
                            </a>
                            <a href="https://instagram.com/shodiyor_kholmurodov" target="_blank" class="inline-flex items-center gap-2 text-xs text-slate-300 hover:text-white transition-colors">
                                <span class="px-1.5 py-0.5 rounded bg-slate-700 border border-slate-600 text-[10px] font-bold">IG</span>
                                @shodiyor_kholmurodov
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="max-w-7xl mx-auto border-t border-slate-800 pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
                <p>© 2026 IQS ERP. Barcha huquqlar himoyalangan.</p>
                <p class="text-slate-500 flex items-center gap-1.5">
                    Dasturiy ta'minot <a href="https://itcloud.uz" target="_blank" class="text-orange-400 font-bold hover:underline">«ITCloud»</a> tomonidan ishlab chiqilgan va litsenziyalangan.
                </p>
            </div>
        </footer>

        <!-- DEMO REQUEST MODAL -->
        <div v-if="showDemo" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="relative w-full max-w-lg p-8 rounded-3xl bg-white border border-slate-200 shadow-2xl text-left">
                
                <button @click="showDemo = false" class="absolute top-5 right-5 text-slate-400 hover:text-slate-700 text-xl cursor-pointer">✕</button>

                <div class="w-12 h-12 rounded-2xl bg-orange-50 border border-orange-200 flex items-center justify-center text-2xl mb-4 text-orange-600">
                    ⚡
                </div>

                <h3 class="text-2xl font-black text-[#0f172a] mb-2">IQS ERP Demo Olish</h3>
                <p class="text-sm text-slate-600 mb-6">Kompaniyangiz uchun IQS tizimining to'liq imkoniyatlarini bepul sinab ko'ring.</p>

                <div v-if="demoSubmitted" class="p-6 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-center">
                    <div class="text-3xl mb-2">✅</div>
                    <div class="font-bold text-lg mb-1">So'rovingiz qabul qilindi!</div>
                    <p class="text-xs text-emerald-700">Tez orada mutaxassislarimiz siz bilan bog'lanishadi.</p>
                </div>

                <form v-else @submit.prevent="submitDemo" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Ismingiz *</label>
                        <input v-model="demoForm.name" required type="text" placeholder="Azizbek Rahimov" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 placeholder-slate-400 focus:border-orange-500 focus:bg-white focus:outline-none text-sm transition-all" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Kompaniya nomi</label>
                        <input v-model="demoForm.company" type="text" placeholder="Sher-Dor Qurilish MCHJ" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 placeholder-slate-400 focus:border-orange-500 focus:bg-white focus:outline-none text-sm transition-all" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Telefon raqam *</label>
                        <input v-model="demoForm.phone" required type="tel" placeholder="+998 (90) 123-45-67" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 placeholder-slate-400 focus:border-orange-500 focus:bg-white focus:outline-none text-sm transition-all" />
                    </div>

                    <button type="submit" class="w-full py-3.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-extrabold text-sm shadow-md shadow-orange-500/20 hover:shadow-orange-500/30 transition-all cursor-pointer">
                        So'rov yuborish
                    </button>
                </form>

            </div>
        </div>

    </div>
</template>
