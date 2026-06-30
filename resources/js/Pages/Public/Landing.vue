<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
    laravelVersion: { type: String },
    phpVersion: { type: String },
});

const showDemo = ref(false);
</script>

<template>
    <Head title="SBE - Sales & Build ERP" />
    <div class="min-h-screen bg-[#050505] text-white relative overflow-hidden font-sans">
        <!-- Space Background / Stars -->
        <div class="stars-bg absolute inset-0 z-0"></div>
        <div class="twinkling absolute inset-0 z-0 opacity-40"></div>

        <!-- Glassmorphism Orbs -->
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] rounded-full bg-purple-600/30 blur-[120px] pointer-events-none z-0"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] rounded-full bg-orange-600/30 blur-[120px] pointer-events-none z-0"></div>
        <div class="absolute top-[40%] left-[60%] w-[30%] h-[30%] rounded-full bg-blue-600/30 blur-[120px] pointer-events-none z-0"></div>

        <!-- Content Wrapper -->
        <div class="relative z-10">
            <!-- Navbar -->
            <nav class="flex justify-between items-center px-8 py-6 max-w-7xl mx-auto backdrop-blur-md bg-white/[0.02] border-b border-white/5 rounded-b-3xl mb-8 shadow-lg">
                <div class="flex items-center gap-3">
                    <img src="/sbe-logo.png" alt="SBE Logo" class="h-10 drop-shadow-[0_0_10px_rgba(255,255,255,0.3)]" @error="(e) => e.target.src='https://ui-avatars.com/api/?name=SBE&background=1e1e1e&color=fff&rounded=true'" />
                    <span class="text-3xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 via-purple-400 to-purple-600 drop-shadow-sm tracking-tight">SBE</span>
                </div>
                <div class="flex gap-4">
                    <Link :href="route('login')" class="px-6 py-2.5 rounded-full bg-white/10 hover:bg-white/20 border border-white/10 transition-all text-sm font-semibold backdrop-blur-sm shadow-[0_0_15px_rgba(255,255,255,0.05)] hover:shadow-[0_0_20px_rgba(255,255,255,0.1)]">
                        Kirish
                    </Link>
                </div>
            </nav>

            <!-- Hero Section -->
            <main class="max-w-7xl mx-auto px-6 py-16 flex flex-col items-center text-center">
                <div class="inline-block mb-6 px-4 py-1.5 rounded-full border border-blue-500/30 bg-blue-500/10 backdrop-blur-md">
                    <span class="text-sm font-medium text-blue-300 tracking-wide uppercase">Yangi Avlod ERP Tizimi</span>
                </div>
                <h1 class="text-5xl md:text-7xl font-extrabold leading-tight mb-8 drop-shadow-lg">
                    SBE – Qurilish va Savdo Kompaniyalari Uchun <br/>
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-orange-400 via-purple-500 to-blue-500 animate-gradient-x">Yagona Boshqaruv Ekosistemasi</span>
                </h1>
                <p class="text-lg md:text-xl text-gray-300 max-w-3xl mb-12 leading-relaxed drop-shadow-md">
                    Moliya, CRM, Qurilish ob'ektlari va Kadrlar bo'limini bitta oynadan boshqaring. Ma'lumotlaringiz xavfsizligi va biznesingizning to'liq nazorati sizning qo'lingizda.
                </p>
                <div class="flex flex-col sm:flex-row gap-6 w-full justify-center px-4">
                    <Link :href="route('login')" class="px-8 py-4 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 transition-all font-bold text-lg shadow-[0_0_30px_rgba(79,70,229,0.5)] hover:shadow-[0_0_40px_rgba(79,70,229,0.7)] hover:-translate-y-1 transform duration-300">
                        Tizim bilan tanishish
                    </Link>
                    <button @click="showDemo = true" class="group px-8 py-4 rounded-full bg-black/40 backdrop-blur-md border border-purple-500/50 hover:bg-purple-500/10 transition-all font-bold text-lg text-purple-300 hover:text-purple-200 shadow-[0_0_30px_rgba(168,85,247,0.3)] hover:shadow-[0_0_40px_rgba(168,85,247,0.5)] hover:-translate-y-1 transform duration-300 flex items-center justify-center gap-2">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-purple-500"></span>
                        </span>
                        Demo rejimda sinab ko'rish
                    </button>
                </div>
            </main>

            <!-- Features Section -->
            <section class="max-w-7xl mx-auto px-6 py-24 relative z-10">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="glass-card p-10 rounded-3xl border border-white/5 hover:border-blue-500/30 transition-all duration-500 group relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="w-16 h-16 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-400 mb-6 text-3xl group-hover:scale-110 transition-transform duration-500 border border-blue-500/20 shadow-[0_0_15px_rgba(59,130,246,0.2)]">💡</div>
                            <h3 class="text-2xl font-bold mb-4 text-white drop-shadow-md">Aklli CRM va Shartnomalar</h3>
                            <p class="text-gray-400 leading-relaxed group-hover:text-gray-300 transition-colors">Mijozlar bazasini (Lead) boshqarish, shartnomalarni avtomatlashtirilgan PDF formatida generatsiya qilish, SMS xabarnomalar va to'lov grafigini (shaxmatka) real vaqt rejimida kuzatish.</p>
                        </div>
                    </div>
                    
                    <div class="glass-card p-10 rounded-3xl border border-white/5 hover:border-orange-500/30 transition-all duration-500 group relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-br from-orange-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="w-16 h-16 rounded-2xl bg-orange-500/10 flex items-center justify-center text-orange-400 mb-6 text-3xl group-hover:scale-110 transition-transform duration-500 border border-orange-500/20 shadow-[0_0_15px_rgba(249,115,22,0.2)]">💰</div>
                            <h3 class="text-2xl font-bold mb-4 text-white drop-shadow-md">Moliya va Kassa Nazorati</h3>
                            <p class="text-gray-400 leading-relaxed group-hover:text-gray-300 transition-colors">Ko'p valyutali hisob-kitoblar, daromad va xarajatlarni toifalar bo'yicha tahlil qilish, investorlar o'rtasida foyda (divident) taqsimotini avtomatik hisoblash va tasdiqlash tizimi (Multi-sig).</p>
                        </div>
                    </div>

                    <div class="glass-card p-10 rounded-3xl border border-white/5 hover:border-purple-500/30 transition-all duration-500 group relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="w-16 h-16 rounded-2xl bg-purple-500/10 flex items-center justify-center text-purple-400 mb-6 text-3xl group-hover:scale-110 transition-transform duration-500 border border-purple-500/20 shadow-[0_0_15px_rgba(168,85,247,0.2)]">🏗️</div>
                            <h3 class="text-2xl font-bold mb-4 text-white drop-shadow-md">Qurilish va Omborxona</h3>
                            <p class="text-gray-400 leading-relaxed group-hover:text-gray-300 transition-colors">Ob'ektlardagi qurilish jarayonlarini bosqichma-bosqich nazorat qilish, ombordagi materiallar (kirim-chiqim) hisobi, ta'minotchilar bilan hisob-kitoblar va texnikalar ijarasi monitoringi.</p>
                        </div>
                    </div>

                    <div class="glass-card p-10 rounded-3xl border border-white/5 hover:border-pink-500/30 transition-all duration-500 group relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-br from-pink-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="w-16 h-16 rounded-2xl bg-pink-500/10 flex items-center justify-center text-pink-400 mb-6 text-3xl group-hover:scale-110 transition-transform duration-500 border border-pink-500/20 shadow-[0_0_15px_rgba(236,72,153,0.2)]">👥</div>
                            <h3 class="text-2xl font-bold mb-4 text-white drop-shadow-md">HR, Oyliklar va Face ID</h3>
                            <p class="text-gray-400 leading-relaxed group-hover:text-gray-300 transition-colors">Ishchilarni yuzni tanish texnologiyasi (Face ID) orqali davomat qilish, brigadalar ishi, KPI va oylik maoshlarni avtomatlashtirilgan hisob-kitobi.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Partners Section -->
            <section class="max-w-7xl mx-auto px-6 py-24 text-center">
                <h2 class="text-3xl md:text-4xl font-extrabold mb-14 drop-shadow-lg">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-gray-100 to-gray-400">Xalqaro va Mahalliy Qurilish Gigantlari Bizni Tanlaydi</span>
                </h2>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-6 items-stretch justify-center">
                    <div class="glass-card py-8 px-4 rounded-2xl border border-white/5 flex items-center justify-center font-bold text-xl text-gray-400 hover:text-white hover:border-white/20 hover:bg-white/5 transition-all duration-300 shadow-lg">Sherdor</div>
                    <div class="glass-card py-8 px-4 rounded-2xl border border-white/5 flex items-center justify-center font-bold text-xl text-gray-400 hover:text-white hover:border-white/20 hover:bg-white/5 transition-all duration-300 shadow-lg">Delta</div>
                    <div class="glass-card py-8 px-4 rounded-2xl border border-white/5 flex flex-col items-center justify-center font-bold text-lg text-gray-400 hover:text-white hover:border-white/20 hover:bg-white/5 transition-all duration-300 shadow-lg">
                        <span>Hakim O'g'lu</span>
                        <span class="text-xs text-blue-400 mt-1.5 font-medium tracking-wider uppercase">(Turkiya)</span>
                    </div>
                    <div class="glass-card py-8 px-4 rounded-2xl border border-white/5 flex flex-col items-center justify-center font-bold text-lg text-gray-400 hover:text-white hover:border-white/20 hover:bg-white/5 transition-all duration-300 text-center shadow-lg">
                        <span>Göztepe Builder Global</span>
                    </div>
                    <div class="glass-card py-8 px-4 rounded-2xl border border-white/5 flex flex-col items-center justify-center font-bold text-lg text-gray-400 hover:text-white hover:border-white/20 hover:bg-white/5 transition-all duration-300 text-center shadow-lg">
                        <span>Hayot House</span>
                        <span class="text-[10px] text-blue-400 mt-1.5 font-medium tracking-wider uppercase">(Toshkent & Samarqand)</span>
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer class="mt-12 border-t border-white/10 pt-16 pb-8 glass-card bg-black/40">
                <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
                    <div class="col-span-1 md:col-span-2">
                        <div class="flex items-center gap-3 mb-6">
                            <img src="/sbe-logo.png" alt="SBE" class="h-10 opacity-90" @error="(e) => e.target.src='https://ui-avatars.com/api/?name=SBE&background=1e1e1e&color=fff&rounded=true'" />
                            <span class="text-2xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-purple-500 tracking-tight">SBE</span>
                        </div>
                        <p class="text-gray-400 text-sm max-w-sm leading-relaxed">
                            SBE – Sales & Build ERP tizimi orqali biznesingizni yangi bosqichga olib chiqing. Barcha murakkab jarayonlar endi bitta aqlli va xavfsiz tizimda.
                        </p>
                    </div>
                    <div>
                        <h4 class="font-bold text-lg mb-6 text-white drop-shadow-sm">Kontaktlar</h4>
                        <ul class="text-gray-400 space-y-4 text-sm font-medium">
                            <li class="flex items-center gap-3 hover:text-blue-400 transition-colors"><span class="text-lg">📞</span> +998 33 518 00 05</li>
                            <li class="flex items-center gap-3 hover:text-blue-400 transition-colors"><span class="text-lg">📞</span> +998 91 187 37 30</li>
                            <li class="flex items-center gap-3 hover:text-blue-400 transition-colors"><span class="text-lg">✉️</span> itclouduz@gmail.com</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-bold text-lg mb-6 text-white drop-shadow-sm">Ijtimoiy tarmoqlar</h4>
                        <ul class="text-gray-400 space-y-4 text-sm font-medium">
                            <li><a href="#" class="flex items-center gap-3 hover:text-blue-400 transition-colors"><span class="text-lg">✈️</span> @ITcloud_me</a></li>
                            <li><a href="#" class="flex items-center gap-3 hover:text-pink-400 transition-colors"><span class="text-lg">📸</span> Shodiyor_kholmurodov</a></li>
                        </ul>
                    </div>
                </div>
                <div class="text-center text-gray-500 text-xs border-t border-white/5 pt-8 font-medium">
                    <p>Dasturchi: Shodiyor Xolmurodov &nbsp;|&nbsp; © 2026 ITCloud.uz &nbsp;|&nbsp; SBE - Sales & Build ERP v2.0</p>
                </div>
            </footer>
        </div>

        <!-- Demo Modal -->
        <div v-if="showDemo" class="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-xl bg-black/70 transition-all duration-300">
            <div class="relative w-full max-w-6xl h-[85vh] rounded-3xl border border-white/10 bg-[#0a0a0a]/80 shadow-[0_0_50px_rgba(0,0,0,0.5)] flex flex-col overflow-hidden animate-fade-in-up ring-1 ring-white/5">
                
                <!-- Mac-like Header -->
                <div class="flex justify-between items-center px-5 py-3 border-b border-white/10 bg-white/5 backdrop-blur-md">
                    <div class="flex items-center gap-4">
                        <div class="flex gap-2">
                            <div class="w-3.5 h-3.5 rounded-full bg-red-500 border border-red-600 cursor-pointer hover:bg-red-600 transition-colors shadow-sm" @click="showDemo = false"></div>
                            <div class="w-3.5 h-3.5 rounded-full bg-yellow-500 border border-yellow-600 shadow-sm"></div>
                            <div class="w-3.5 h-3.5 rounded-full bg-green-500 border border-green-600 shadow-sm"></div>
                        </div>
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest ml-4">SBE Dashboard Demo</span>
                    </div>
                    <button @click="showDemo = false" class="text-gray-400 hover:text-white transition-colors text-sm font-medium bg-white/5 hover:bg-white/10 px-3 py-1 rounded-md">
                        Yopish
                    </button>
                </div>

                <!-- Dashboard Mockup -->
                <div class="flex flex-1 overflow-hidden backdrop-blur-3xl">
                    <!-- Sidebar -->
                    <div class="w-64 border-r border-white/5 bg-black/40 p-5 flex flex-col gap-3">
                        <div class="mb-6 px-2 flex items-center gap-2">
                            <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-purple-500 tracking-tight">SBE ERP</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-gradient-to-r from-blue-600/20 to-blue-500/10 text-blue-400 border border-blue-500/20 shadow-inner">
                            📊 <span class="font-medium text-sm">Boshqaruv Paneli</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/5 text-gray-400 hover:text-gray-200 transition-all cursor-pointer">
                            💼 <span class="font-medium text-sm">Moliya</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/5 text-gray-400 hover:text-gray-200 transition-all cursor-pointer">
                            🤝 <span class="font-medium text-sm">CRM va Mijozlar</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/5 text-gray-400 hover:text-gray-200 transition-all cursor-pointer">
                            🏢 <span class="font-medium text-sm">Qurilish Ob'ektlari</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/5 text-gray-400 hover:text-gray-200 transition-all cursor-pointer">
                            📦 <span class="font-medium text-sm">Omborxona</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/5 text-gray-400 hover:text-gray-200 transition-all cursor-pointer">
                            👥 <span class="font-medium text-sm">HR va Oyliklar</span>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="flex-1 bg-gradient-to-br from-gray-900/40 to-black/60 p-8 overflow-y-auto relative">
                        
                        <!-- Topbar -->
                        <div class="flex justify-between items-center mb-10">
                            <div class="relative w-96">
                                <input type="text" placeholder="Qidirish..." class="w-full bg-white/5 border border-white/10 rounded-full py-2.5 px-5 pl-12 text-sm text-white focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-all shadow-inner">
                                <span class="absolute left-4 top-2.5 text-gray-400">🔍</span>
                            </div>
                            <div class="flex items-center gap-4">
                                <button class="text-gray-400 hover:text-white">🔔</button>
                                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-purple-600 to-blue-500 flex items-center justify-center font-bold shadow-lg border border-white/10">A</div>
                            </div>
                        </div>

                        <!-- Stats -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="bg-white/5 border border-white/5 rounded-2xl p-6 hover:bg-white/10 transition-colors shadow-lg relative overflow-hidden">
                                <div class="absolute right-0 top-0 w-24 h-24 bg-green-500/10 rounded-bl-full"></div>
                                <div class="text-gray-400 text-sm font-medium mb-3">Umumiy Daromad</div>
                                <div class="text-3xl font-bold text-green-400 drop-shadow-sm">$2,450,000</div>
                                <div class="mt-2 text-xs text-green-500 flex items-center gap-1">↑ +12.5% o'tgan oydan</div>
                            </div>
                            <div class="bg-white/5 border border-white/5 rounded-2xl p-6 hover:bg-white/10 transition-colors shadow-lg relative overflow-hidden">
                                <div class="absolute right-0 top-0 w-24 h-24 bg-blue-500/10 rounded-bl-full"></div>
                                <div class="text-gray-400 text-sm font-medium mb-3">Faol Shartnomalar</div>
                                <div class="text-3xl font-bold text-blue-400 drop-shadow-sm">142</div>
                                <div class="mt-2 text-xs text-blue-500 flex items-center gap-1">↑ +8 ta yangi</div>
                            </div>
                            <div class="bg-white/5 border border-white/5 rounded-2xl p-6 hover:bg-white/10 transition-colors shadow-lg relative overflow-hidden">
                                <div class="absolute right-0 top-0 w-24 h-24 bg-orange-500/10 rounded-bl-full"></div>
                                <div class="text-gray-400 text-sm font-medium mb-3">Qurilish Bosqichi</div>
                                <div class="text-3xl font-bold text-orange-400 drop-shadow-sm">68%</div>
                                <div class="mt-3 w-full bg-gray-700/50 rounded-full h-1.5">
                                    <div class="bg-orange-400 h-1.5 rounded-full" style="width: 68%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Chart & Transactions -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Dummy Chart -->
                            <div class="col-span-2 bg-white/5 border border-white/5 rounded-2xl p-6 h-80 flex flex-col justify-end relative overflow-hidden shadow-lg">
                                <div class="absolute top-6 left-6 font-bold text-gray-200 text-lg">Sotuvlar Grafigi (Yillik)</div>
                                
                                <!-- Chart Lines -->
                                <div class="absolute inset-0 flex flex-col justify-between pt-20 pb-6 px-6 pointer-events-none opacity-20">
                                    <div class="border-b border-white border-dashed w-full"></div>
                                    <div class="border-b border-white border-dashed w-full"></div>
                                    <div class="border-b border-white border-dashed w-full"></div>
                                    <div class="border-b border-white border-dashed w-full"></div>
                                </div>
                                
                                <div class="flex items-end justify-between h-48 gap-3 mt-4 relative z-10 w-full px-2">
                                    <div class="w-full bg-gradient-to-t from-blue-600/80 to-blue-400/40 rounded-t-md relative group"><div class="absolute -top-8 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 bg-white/10 px-2 py-1 rounded text-xs transition-opacity">$12k</div></div>
                                    <div class="w-full bg-gradient-to-t from-blue-600/80 to-blue-400/40 rounded-t-md h-[60%]"></div>
                                    <div class="w-full bg-gradient-to-t from-blue-600/80 to-blue-400/40 rounded-t-md h-[50%]"></div>
                                    <div class="w-full bg-gradient-to-t from-blue-600/80 to-blue-400/40 rounded-t-md h-[80%]"></div>
                                    <div class="w-full bg-gradient-to-t from-blue-600/80 to-blue-400/40 rounded-t-md h-[70%]"></div>
                                    <div class="w-full bg-gradient-to-t from-blue-600/80 to-blue-400/40 rounded-t-md h-[90%]"></div>
                                    <div class="w-full bg-gradient-to-t from-purple-500/90 to-purple-400/50 rounded-t-md h-[100%] shadow-[0_0_15px_rgba(168,85,247,0.5)]"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-3 px-2">
                                    <span>Yan</span><span>Fev</span><span>Mar</span><span>Apr</span><span>May</span><span>Iyun</span><span class="text-purple-400 font-bold">Iyul</span>
                                </div>
                            </div>
                            
                            <!-- Transactions -->
                            <div class="col-span-1 bg-white/5 border border-white/5 rounded-2xl p-6 shadow-lg flex flex-col h-80">
                                <div class="font-bold text-gray-200 text-lg mb-5">So'nggi Tranzaksiyalar</div>
                                <div class="space-y-4 overflow-y-auto flex-1 pr-2 custom-scrollbar">
                                    <div class="flex justify-between items-center p-3 rounded-xl hover:bg-white/5 transition-colors cursor-pointer">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-green-500/20 text-green-400 flex items-center justify-center">↓</div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-200">Aliyev Vali</div>
                                                <div class="text-xs text-gray-500">Kvartira to'lovi</div>
                                            </div>
                                        </div>
                                        <div class="text-green-400 font-bold">+$12,500</div>
                                    </div>
                                    <div class="flex justify-between items-center p-3 rounded-xl hover:bg-white/5 transition-colors cursor-pointer">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-red-500/20 text-red-400 flex items-center justify-center">↑</div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-200">Delta Cement</div>
                                                <div class="text-xs text-gray-500">Material xaridi</div>
                                            </div>
                                        </div>
                                        <div class="text-red-400 font-bold">-$4,200</div>
                                    </div>
                                    <div class="flex justify-between items-center p-3 rounded-xl hover:bg-white/5 transition-colors cursor-pointer">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-red-500/20 text-red-400 flex items-center justify-center">↑</div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-200">Ish haqi</div>
                                                <div class="text-xs text-gray-500">1-brigada</div>
                                            </div>
                                        </div>
                                        <div class="text-red-400 font-bold">-$8,900</div>
                                    </div>
                                    <div class="flex justify-between items-center p-3 rounded-xl hover:bg-white/5 transition-colors cursor-pointer">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-green-500/20 text-green-400 flex items-center justify-center">↓</div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-200">Investor M.</div>
                                                <div class="text-xs text-gray-500">Sarmoya</div>
                                            </div>
                                        </div>
                                        <div class="text-green-400 font-bold">+$50,000</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass-card {
    background: rgba(255, 255, 255, 0.02);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
.animate-fade-in-up {
    animation: fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* Gradient Text Animation */
@keyframes gradient-x {
    0%, 100% {
        background-size: 200% 200%;
        background-position: left center;
    }
    50% {
        background-size: 200% 200%;
        background-position: right center;
    }
}
.animate-gradient-x {
    animation: gradient-x 5s ease infinite;
}

/* Custom Scrollbar for Demo */
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.02);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* Stars Background Effect */
.stars-bg {
    background: #050505 url('https://raw.githubusercontent.com/danielstuart14/CSS_FRACTAL_GALAXY/master/stars.png') repeat top center;
    z-index: 0;
}
.twinkling {
    background: transparent url('https://raw.githubusercontent.com/danielstuart14/CSS_FRACTAL_GALAXY/master/twinkling.png') repeat top center;
    z-index: 1;
    animation: move-twink-back 150s linear infinite;
}

@keyframes move-twink-back {
    from {background-position: 0 0;}
    to {background-position: -10000px 5000px;}
}
</style>
