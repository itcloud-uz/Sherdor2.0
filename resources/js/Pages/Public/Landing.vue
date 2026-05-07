<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import GalaxyBackground from '@/Components/GalaxyBackground.vue';

const panels = [
    {
        title: 'Admin Paneli',
        icon: '👑',
        color: 'from-purple-600 to-purple-400',
        features: [
            'Barcha obyektlar va sotuvlarni to\'liq nazorat qilish',
            'Moliyaviy oqim va kassani yuritish',
            'Xodimlarni boshqarish va oylik hisoblash',
            'Tasdiqlashlar (Multi-sig) va ruxsatnomalar',
            'Shartnomalarni yaratish va PDF skaner'
        ]
    },
    {
        title: 'Partner Paneli',
        icon: '💼',
        color: 'from-amber-500 to-yellow-300',
        features: [
            'Kiritilgan investitsiyalarni kuzatib borish',
            'Obyektlardagi sotuvlardan tushgan foydani ko\'rish',
            'Admin tomonidan yuborilgan xarajatlarni tasdiqlash',
            'Foyda taqsimoti (Dividentlar) tahlili',
            'Qurilish jarayonining real vaqt monitoringi'
        ]
    },
    {
        title: 'Operator Paneli',
        icon: '🏗️',
        color: 'from-red-600 to-red-400',
        features: [
            'Mijozlar bilan ishlash (CRM bazasi)',
            'Shartnomalar tayyorlash va kiritish',
            'Kvartiralar (shaxmatka) holatini ko\'rish',
            'Kunlik uchrashuvlarni rejalashtirish',
            'AI yordamida hujjatlarni skaner qilish'
        ]
    }
];
</script>

<template>
    <Head title="Sher-Dor ERP - Tizim Imkoniyatlari" />
    
    <div class="min-h-screen text-white font-sans overflow-x-hidden relative selection:bg-purple-500/30 selection:text-white">
        
        <!-- Authentic Galaxy Background -->
        <GalaxyBackground />

        <!-- Navigation (Apple Glassmorphism) -->
        <nav class="relative z-50 flex items-center justify-between p-6 md:px-12 bg-black/80 border-b border-white/10 sticky top-0 backdrop-blur-md shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-purple-600 to-amber-500 flex items-center justify-center shadow-[0_0_15px_rgba(168,85,247,0.4)]">
                    <span class="font-display font-bold text-xl text-white">S</span>
                </div>
                <span class="font-display font-bold text-xl tracking-widest text-white">SHER-DOR</span>
            </div>
            <div class="hidden md:flex gap-8 items-center text-sm font-medium tracking-wide text-gray-300">
                <a href="#panels" class="hover:text-amber-400 transition-colors">Tizim Bo'limlari</a>
                <a href="#about" class="hover:text-amber-400 transition-colors">Qo'llanma</a>
                <a href="#contact" class="hover:text-amber-400 transition-colors">Texnik Yordam</a>
            </div>
            <div class="flex items-center gap-4">
                <Link :href="route('login')" class="px-5 py-2 text-sm font-bold text-white bg-white/10 hover:bg-white/20 border border-white/20 rounded-full transition-colors backdrop-blur-md">
                    Tizimga Kirish
                </Link>
            </div>
        </nav>

        <!-- Hero Section -->
        <main class="relative z-10 flex flex-col items-center justify-center min-h-[80vh] text-center px-4">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-medium tracking-wide mb-8 backdrop-blur-md">
                <span class="w-2 h-2 rounded-full bg-red-500 shadow-[0_0_8px_#ef4444] animate-pulse"></span>
                Siz uchun maxsus ishlab chiqildi
            </div>
            
            <h1 class="text-5xl md:text-7xl font-display font-bold mb-6 max-w-4xl leading-tight text-transparent bg-clip-text bg-gradient-to-b from-white to-gray-400">
                Sher-Dor ERP <br />
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-red-400 to-amber-400">Ishonchli Boshqaruv Tizimi</span>
            </h1>
            
            <p class="text-lg md:text-xl text-gray-400 max-w-3xl mb-12 font-light leading-relaxed">
                Hurmatli mijoz, bu tizim orqali siz kompaniyangizdagi barcha moliyaviy oqimlarni, sotuvlarni va qurilish jarayonlarini to'liq raqamlashtirasiz. Quyida tizimning ishlash tamoyillari bilan tanishishingiz mumkin.
            </p>
            
            <div class="flex flex-col sm:flex-row items-center gap-6">
                <a href="#panels" class="px-8 py-4 bg-gradient-to-r from-purple-600 to-red-600 text-white font-medium text-lg rounded-full shadow-[0_0_30px_rgba(168,85,247,0.4)] hover:shadow-[0_0_40px_rgba(239,68,68,0.6)] hover:scale-105 transition-all w-full sm:w-auto border border-white/20 backdrop-blur-md">
                    Bo'limlar Bilan Tanishish
                </a>
                <Link :href="route('login')" class="px-8 py-4 bg-black/40 border border-amber-500/30 text-amber-400 font-medium text-lg rounded-full hover:bg-white/10 transition-all w-full sm:w-auto backdrop-blur-xl flex items-center justify-center gap-2">
                    Tizimga Kirish <span class="opacity-50">→</span>
                </Link>
            </div>
        </main>

        <!-- Panels Overview -->
        <section id="panels" class="relative z-10 py-24 px-4 max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-5xl font-display font-bold mb-4">Uch Darajali <span class="text-purple-400">Nazorat Panellari</span></h2>
                <p class="text-gray-400 text-lg">Har bir roldagi xodim va hamkor uchun maxsus imkoniyatlar</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div 
                    v-for="(panel, idx) in panels" 
                    :key="idx"
                    class="bg-black/40 p-8 rounded-[2rem] border border-white/10 backdrop-blur-3xl transition-all duration-500 hover:border-white/30 hover:-translate-y-2 group shadow-[0_0_30px_rgba(0,0,0,0.5)]"
                >
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mb-6 bg-gradient-to-br border border-white/20 shadow-lg" :class="panel.color">
                        {{ panel.icon }}
                    </div>
                    <h3 class="text-2xl font-display font-bold text-white mb-4">{{ panel.title }}</h3>
                    <ul class="space-y-3">
                        <li v-for="(feature, fIdx) in panel.features" :key="fIdx" class="flex items-start gap-3 text-gray-300 text-sm leading-relaxed">
                            <span class="text-amber-400 mt-0.5">✦</span>
                            {{ feature }}
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Technical Capabilities -->
        <section id="about" class="relative z-10 py-24 px-4 border-y border-white/10 bg-black/40 backdrop-blur-3xl mt-12">
            <div class="max-w-7xl mx-auto flex flex-col lg:flex-row items-center gap-16">
                <div class="flex-1 space-y-8">
                    <h2 class="text-3xl md:text-4xl font-display font-bold leading-tight">
                        Zamonaviy Texnologiyalar <br>
                        Sizning Xizmatingizda
                    </h2>
                    <p class="text-gray-400 leading-relaxed text-lg">
                        Tizim eng so'nggi dasturlash yechimlari bilan qurilgan bo'lib, quyidagi qulayliklarni taklif etadi:
                    </p>
                    <ul class="space-y-6">
                        <li class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center shrink-0 border border-purple-500/40 text-2xl shadow-[0_0_15px_rgba(168,85,247,0.3)]">🧠</div>
                            <div>
                                <h4 class="font-bold text-white mb-1">Sun'iy Intellekt (AI)</h4>
                                <p class="text-sm text-gray-400">Shartnoma PDF fayllarini avtomatik o'qish (OCR) hamda mijozlarga javob qaytaruvchi aqlli botlar.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center shrink-0 border border-amber-500/40 text-2xl shadow-[0_0_15px_rgba(245,158,11,0.3)]">🛡️</div>
                            <div>
                                <h4 class="font-bold text-white mb-1">Xavfsizlik Va Izolyatsiya</h4>
                                <p class="text-sm text-gray-400">Har bir qilinayotgan xarajat ham Admin, ham Prorab tomonidan tasdiqlanishi shart. Ma'lumotlar shifrlangan.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-red-500/20 flex items-center justify-center shrink-0 border border-red-500/40 text-2xl shadow-[0_0_15px_rgba(239,68,68,0.3)]">📊</div>
                            <div>
                                <h4 class="font-bold text-white mb-1">Real Vaqt (Real-Time) Tahlili</h4>
                                <p class="text-sm text-gray-400">Sotuvlar, muddatli to'lovlar, valyuta kurslaridagi o'zgarishlarni soniyasma-soniya kuzatish imkoniyati.</p>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="flex-1 relative w-full aspect-square md:aspect-video lg:aspect-square max-h-[500px] rounded-[3rem] border border-white/20 bg-[#0A0A10]/80 overflow-hidden shadow-[0_0_100px_rgba(168,85,247,0.15)] backdrop-blur-3xl flex flex-col justify-center items-center">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-amber-500/20 rounded-full blur-[80px]"></div>
                    <div class="absolute bottom-0 left-0 w-64 h-64 bg-purple-600/30 rounded-full blur-[100px]"></div>
                    
                    <div class="relative z-10 text-center p-8">
                        <div class="inline-flex gap-3 mb-8">
                            <div class="w-16 h-16 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md flex items-center justify-center shadow-lg">👑</div>
                            <div class="w-16 h-16 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md flex items-center justify-center shadow-lg">💼</div>
                            <div class="w-16 h-16 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md flex items-center justify-center shadow-lg">🏗️</div>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-2">Bitta Tizim</h3>
                        <p class="text-gray-400">Uchta mukammal yechim</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer / Contact -->
        <footer id="contact" class="relative z-10 pt-20 pb-10 px-4 text-center border-t border-white/10 bg-[#05050A]/80 backdrop-blur-3xl">
            <h2 class="text-3xl font-display font-bold mb-6 text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-purple-400">Texnik Yordam va Qo'llab-Quvvatlash</h2>
            <p class="text-gray-400 mb-12 max-w-2xl mx-auto text-lg">Tizim bo'yicha savollaringiz yoki qo'shimcha modullar kerak bo'lsa, Dasturchilar Jamoasi bilan istalgan vaqtda bog'lanishingiz mumkin.</p>
            
            <div class="flex flex-wrap justify-center gap-6 mb-16 max-w-5xl mx-auto">
                <a href="tel:+998335180005" class="flex flex-col items-center p-8 bg-black/40 border border-white/10 rounded-[2rem] hover:border-amber-500/50 hover:bg-white/5 transition-all backdrop-blur-xl flex-1 min-w-[250px] shadow-[0_0_20px_rgba(0,0,0,0.5)]">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-amber-500 to-yellow-300 flex items-center justify-center text-2xl mb-4 shadow-lg">📱</div>
                    <span class="font-bold text-white text-xl">+998 33 518 00 05</span>
                    <span class="text-sm text-gray-400 mt-2">Asosiy Raqam</span>
                </a>
                
                <a href="tel:+998911873730" class="flex flex-col items-center p-8 bg-black/40 border border-white/10 rounded-[2rem] hover:border-purple-500/50 hover:bg-white/5 transition-all backdrop-blur-xl flex-1 min-w-[250px] shadow-[0_0_20px_rgba(0,0,0,0.5)]">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-purple-600 to-purple-400 flex items-center justify-center text-2xl mb-4 shadow-lg">📞</div>
                    <span class="font-bold text-white text-xl">+998 91 187 37 30</span>
                    <span class="text-sm text-gray-400 mt-2">Qo'shimcha Raqam</span>
                </a>
                
                <a href="mailto:itclouduz@gmail.com" class="flex flex-col items-center p-8 bg-black/40 border border-white/10 rounded-[2rem] hover:border-red-500/50 hover:bg-white/5 transition-all backdrop-blur-xl flex-1 min-w-[250px] shadow-[0_0_20px_rgba(0,0,0,0.5)]">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-red-600 to-red-400 flex items-center justify-center text-2xl mb-4 shadow-lg">✉️</div>
                    <span class="font-bold text-white text-xl">itclouduz@gmail.com</span>
                    <span class="text-sm text-gray-400 mt-2">Elektron Pochta</span>
                </a>

                <a href="https://t.me/ITcloud_me" target="_blank" class="flex flex-col items-center p-8 bg-black/40 border border-white/10 rounded-[2rem] hover:border-blue-500/50 hover:bg-white/5 transition-all backdrop-blur-xl flex-1 min-w-[250px] shadow-[0_0_20px_rgba(0,0,0,0.5)]">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-blue-500 to-cyan-400 flex items-center justify-center text-2xl mb-4 shadow-lg">✈️</div>
                    <span class="font-bold text-white text-xl">@ITcloud_me</span>
                    <span class="text-sm text-gray-400 mt-2">Telegram orqali yozish</span>
                </a>
                
                <a href="https://instagram.com/Shodiyor_kholmurodov" target="_blank" class="flex flex-col items-center p-8 bg-black/40 border border-white/10 rounded-[2rem] hover:border-pink-500/50 hover:bg-white/5 transition-all backdrop-blur-xl flex-1 min-w-[250px] shadow-[0_0_20px_rgba(0,0,0,0.5)]">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-pink-600 to-orange-400 flex items-center justify-center text-2xl mb-4 shadow-lg">📷</div>
                    <span class="font-bold text-white text-xl">Shodiyor_kholmurodov</span>
                    <span class="text-sm text-gray-400 mt-2">Instagram sahifa</span>
                </a>
            </div>
            
            <div class="text-gray-500 font-medium text-sm mt-8 border-t border-white/10 pt-8 inline-block w-full max-w-md">
                Dasturchi: <strong>Shodiyor Xolmurodov</strong> <br>
                &copy; 2026 ITCloud.uz | Sher-Dor ERP System v2.0
            </div>
        </footer>

    </div>
</template>

<style scoped>
html { scroll-behavior: smooth; }
</style>
