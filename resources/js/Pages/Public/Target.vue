<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    promoSettings: Object,
    companyPhone: String,
    buildings: Array,
});

// Form state
const form = ref({
    full_name: '',
    phone: '+998 ',
    room_type: '2 xonali',
    block_preference: 'Istalgani',
    comment: '',
    source: 'Instagram Target',
});

const isSubmitting = ref(false);
const isSuccess = ref(false);
const errorMessage = ref('');

// Format phone input
const onPhoneInput = (e) => {
    let val = e.target.value;
    if (!val.startsWith('+998')) {
        val = '+998 ' + val.replace(/\D/g, '');
    }
    form.value.phone = val;
};

// Handle UTM parameters
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const utmSource = params.get('utm_source');
    const utmMedium = params.get('utm_medium');
    const utmCampaign = params.get('utm_campaign');
    const utmContent = params.get('utm_content');

    let sourceStr = 'Instagram Target';
    if (utmSource) sourceStr += ` (${utmSource}`;
    if (utmMedium) sourceStr += ` / ${utmMedium}`;
    if (utmCampaign) sourceStr += ` / ${utmCampaign}`;
    if (utmSource) sourceStr += `)`;
    form.value.source = sourceStr;

    // Facebook Pixel if configured
    if (props.promoSettings?.fb_pixel_id && window.fbq) {
        window.fbq('track', 'PageView');
    }
});

// Scroll to form helper
const scrollToForm = () => {
    const el = document.getElementById('lead-form-section');
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
};

// Submit lead to API
const submitApplication = async () => {
    if (!form.value.phone || form.value.phone.trim().length < 9) {
        errorMessage.value = 'Iltimos, telefon raqamingizni to\'liq kiriting.';
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    const detailedComment = `[Instagram Target Ariza] Qiziqayotgan xonadon: ${form.value.room_type}, Blok: ${form.value.block_preference}. ` + (form.value.comment ? `Mijoz izohi: ${form.value.comment}` : '');

    try {
        await axios.post(route('api.leads.store'), {
            full_name: form.value.full_name || 'Instagram Foydalanuvchisi',
            phone: form.value.phone,
            region: 'Samarqand viloyati',
            district: 'Urgut tumani',
            comment: detailedComment,
            source: form.value.source || 'Instagram Target',
        });

        isSuccess.value = true;

        if (props.promoSettings?.fb_pixel_id && window.fbq) {
            window.fbq('track', 'Lead');
        }
    } catch (err) {
        errorMessage.value = err.response?.data?.message || 'Xatolik yuz berdi. Iltimos qayta urinib ko\'ring yoki to\'g\'ridan-to\'g\'ri qo\'ng\'iroq qiling.';
    } finally {
        isSubmitting.value = false;
    }
};

const activeGalleryTab = ref(0);
const galleryImages = [
    {
        title: "Majmuaning umumiy 3D ko'rinishi",
        sub: "4 ta blokdan iborat zamonaviy arxitektura",
        url: "/storage/photos/lZ3j1NVn0DTFJkaQHN2uyZJgTCWEZgCgXR9JMCYm.jpg"
    },
    {
        title: "3D Bosh Reja (Master Plan)",
        sub: "Keng hovli, yo'llar va qulay infratuzilma",
        url: "/storage/master_plans/y1IE9In98EjuKtXyxjEkjTMrj1U4uqtQ6ZV3FUI5.png"
    },
    {
        title: "Tashqi Fasad & Landshaft",
        sub: "Sifatli pardozlash va zamonaviy dizayn",
        url: "/storage/showroom/DP6yWc7mh3CcjOVpbwmFjfO67hkDbRha2Oiy5sUJ.jpg"
    },
    {
        title: "Hududning joylashuvi (Urgut)",
        sub: "Navoiy shoh ko'chasidagi qulay joylashuv",
        url: "/storage/showroom/urgut_situatsiya.jpg"
    }
];

const phoneContact = props.companyPhone || '+998 94 633 04 00';
</script>

<template>
    <Head title="SHER-DOR TJM — Zamonaviy Xonadonlar (Urgut) | Rasmiy Ariza" />

    <div class="min-h-screen bg-slate-900 text-slate-100 font-sans selection:bg-orange-500 selection:text-white pb-20 sm:pb-0">

        <!-- Top Header Navigation -->
        <header class="sticky top-0 z-50 bg-slate-950/80 backdrop-blur-md border-b border-slate-800/80 px-4 sm:px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-orange-600 to-amber-500 flex items-center justify-center text-white font-black text-xl shadow-lg shadow-orange-500/20">
                    🦁
                </div>
                <div>
                    <h1 class="text-sm sm:text-base font-black tracking-tight text-white leading-none uppercase">
                        SHER-DOR TJM
                    </h1>
                    <span class="text-[10px] font-bold text-orange-400 font-mono tracking-wider">
                        URGUT TUMANI
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <a 
                    :href="`tel:${phoneContact.replace(/\s+/g, '')}`" 
                    class="h-9 px-3.5 sm:px-4 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-black text-xs transition-all flex items-center gap-2 shadow-md shadow-orange-500/25"
                >
                    <span>📞</span>
                    <span class="hidden sm:inline">{{ phoneContact }}</span>
                    <span class="sm:hidden">Qo'ng'iroq</span>
                </a>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative pt-6 sm:pt-12 pb-12 sm:pb-16 px-4 sm:px-6 overflow-hidden">
            <!-- Background Glow -->
            <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-orange-600/20 rounded-full blur-3xl pointer-events-none"></div>

            <div class="max-w-4xl mx-auto text-center relative z-10 space-y-4">
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-orange-500/10 border border-orange-500/30 text-orange-400 text-xs font-black uppercase tracking-wider">
                    <span>📍</span> Samarqand viloyati, Urgut tumani
                </div>

                <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight leading-tight">
                    SHER-DOR Turar-Joy Majmuasi
                </h2>

                <p class="text-slate-300 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed">
                    Siz va oilangiz uchun shinam, xavfsiz va zamonaviy xonadonlar! Hoziroq ariza qoldiring va barcha qulayliklar hamda imkoniyatlar bo'yicha bepul konsultatsiya oling.
                </p>

                <!-- 3D Banner Preview -->
                <div class="pt-4 max-w-3xl mx-auto">
                    <div class="relative rounded-3xl overflow-hidden border border-slate-700/80 shadow-2xl shadow-orange-950/40 group">
                        <img 
                            src="/storage/photos/lZ3j1NVn0DTFJkaQHN2uyZJgTCWEZgCgXR9JMCYm.jpg" 
                            alt="Sher-Dor TJM 3D Bino" 
                            class="w-full h-64 sm:h-96 object-cover group-hover:scale-105 transition-transform duration-700"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/20 to-transparent"></div>
                        <div class="absolute bottom-4 left-4 right-4 flex items-center justify-between text-left">
                            <div>
                                <span class="px-2.5 py-1 rounded-lg bg-orange-500 text-white text-[10px] font-black uppercase tracking-wider inline-block mb-1">
                                    3D Bino Ko'rinishi
                                </span>
                                <h3 class="text-sm sm:text-base font-black text-white">4 ta zamonaviy blok, 9 qavat</h3>
                            </div>
                            <button 
                                @click="scrollToForm"
                                class="px-4 py-2 rounded-xl bg-white text-slate-900 font-black text-xs shadow-lg hover:bg-slate-100 transition-all cursor-pointer"
                            >
                                Ariza topshirish ↓
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Lead Form Section -->
        <section id="lead-form-section" class="py-8 sm:py-12 px-4 sm:px-6 relative z-20">
            <div class="max-w-xl mx-auto bg-slate-950/90 backdrop-blur-xl border border-slate-800 rounded-3xl p-6 sm:p-10 shadow-2xl shadow-black/80 relative">
                
                <!-- Success State -->
                <div v-if="isSuccess" class="text-center py-8 space-y-4">
                    <div class="w-16 h-16 rounded-3xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 text-3xl mx-auto flex items-center justify-center font-black animate-bounce">
                        ✓
                    </div>
                    <h3 class="text-2xl font-black text-white">Arizangiz Qabul Qilindi!</h3>
                    <p class="text-slate-300 text-sm leading-relaxed max-w-sm mx-auto">
                        Katta rahmat! Savdo bo'limimiz mutaxassisi 15 daqiqa ichida <strong class="text-orange-400">{{ form.phone }}</strong> raqamingizga qo'ng'iroq qiladi.
                    </p>
                    <div class="pt-4">
                        <a 
                            :href="`tel:${phoneContact.replace(/\s+/g, '')}`" 
                            class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-orange-500 hover:bg-orange-600 text-white font-black text-sm transition-all shadow-lg shadow-orange-500/30"
                        >
                            <span>📞</span>
                            <span>Hozirroq qo'ng'iroq qilish</span>
                        </a>
                    </div>
                </div>

                <!-- Form Content -->
                <div v-else>
                    <div class="text-center mb-6">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-orange-500/10 border border-orange-500/30 text-orange-400 text-xs font-black uppercase tracking-wider mb-2">
                            <span>🚀</span> Tezkor Ariza
                        </span>
                        <h3 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                            Bepul Konsultatsiya Oling
                        </h3>
                        <p class="text-slate-400 text-xs sm:text-sm mt-1">
                            Ma'lumotlaringizni qoldiring, mutaxassisimiz barcha ma'lumotlarni batafsil tushuntirib beradi.
                        </p>
                    </div>

                    <!-- Error Alert -->
                    <div v-if="errorMessage" class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-bold text-center">
                        {{ errorMessage }}
                    </div>

                    <form @submit.prevent="submitApplication" class="space-y-4">
                        
                        <!-- Ism -->
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                                Ismingiz <span class="text-orange-400">*</span>
                            </label>
                            <input 
                                v-model="form.full_name" 
                                type="text" 
                                required
                                placeholder="Masalan: Dostonbek"
                                class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 text-sm focus:border-orange-500 focus:outline-none transition-all"
                            />
                        </div>

                        <!-- Telefon raqam -->
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                                Telefon raqamingiz <span class="text-orange-400">*</span>
                            </label>
                            <input 
                                v-model="form.phone" 
                                @input="onPhoneInput"
                                type="tel" 
                                required
                                placeholder="+998 90 123 45 67"
                                class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 text-sm font-mono focus:border-orange-500 focus:outline-none transition-all"
                            />
                        </div>

                        <!-- Xonadon turi (Pill selector) -->
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                                Qaysi xonadon qiziqtiryapti?
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <button 
                                    v-for="rt in ['1 xonali', '2 xonali', '3 xonali', '4 xonali', 'Do\'kon (Tijorat)', 'Boshqa']"
                                    :key="rt"
                                    type="button"
                                    @click="form.room_type = rt"
                                    :class="form.room_type === rt ? 'bg-orange-500 text-white font-black border-orange-500 shadow-md shadow-orange-500/20' : 'bg-slate-900 text-slate-300 border-slate-700 hover:bg-slate-800 font-bold'"
                                    class="py-2.5 px-2 rounded-xl border text-xs text-center transition-all cursor-pointer"
                                >
                                    {{ rt }}
                                </button>
                            </div>
                        </div>

                        <!-- Blok tanlovi -->
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                                Qaysi blok ma'qul?
                            </label>
                            <div class="grid grid-cols-5 gap-1.5">
                                <button 
                                    v-for="b in ['1-Blok', '2-Blok', '3-Blok', '4-Blok', 'Istalgani']"
                                    :key="b"
                                    type="button"
                                    @click="form.block_preference = b"
                                    :class="form.block_preference === b ? 'bg-orange-500 text-white font-black border-orange-500' : 'bg-slate-900 text-slate-400 border-slate-700 hover:bg-slate-800 font-bold'"
                                    class="py-2 px-1 rounded-xl border text-[11px] text-center transition-all cursor-pointer"
                                >
                                    {{ b }}
                                </button>
                            </div>
                        </div>

                        <!-- Qo'shimcha Izoh -->
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                                Qo'shimcha savol yoki istagingiz (ixtiyoriy)
                            </label>
                            <textarea 
                                v-model="form.comment" 
                                rows="2" 
                                placeholder="Qaysi qavat ma'qul yoki boshqa savollar..."
                                class="w-full px-4 py-2.5 rounded-2xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 text-xs focus:border-orange-500 focus:outline-none resize-none transition-all"
                            ></textarea>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button 
                                type="submit" 
                                :disabled="isSubmitting"
                                class="w-full py-4 rounded-2xl bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-black text-sm sm:text-base uppercase tracking-wider shadow-xl shadow-orange-500/30 transition-all flex items-center justify-center gap-2 disabled:opacity-60 cursor-pointer"
                            >
                                <span v-if="isSubmitting" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                <span>{{ isSubmitting ? 'Yuborilmoqda...' : '🚀 ARIZA TOPSHIRISH' }}</span>
                            </button>
                        </div>

                        <!-- Trust Features -->
                        <div class="pt-2 flex items-center justify-center gap-4 text-[11px] text-slate-400 font-bold">
                            <span class="flex items-center gap-1">🔒 100% Xavfsiz</span>
                            <span class="flex items-center gap-1">⏱️ 15 daqiqada aloqa</span>
                            <span class="flex items-center gap-1">🎁 Bepul konsultatsiya</span>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <!-- 3D Gallery / Showcase -->
        <section class="py-12 px-4 sm:px-6 max-w-4xl mx-auto">
            <div class="text-center mb-8">
                <span class="text-xs font-black uppercase tracking-wider text-orange-400 block mb-1">Vizual Ko'rinish</span>
                <h3 class="text-2xl sm:text-3xl font-black text-white">Majmuaning 3D Ko'rinishlari</h3>
            </div>

            <!-- Gallery Tab switcher -->
            <div class="flex items-center justify-center gap-2 overflow-x-auto pb-4">
                <button 
                    v-for="(item, idx) in galleryImages"
                    :key="idx"
                    @click="activeGalleryTab = idx"
                    :class="activeGalleryTab === idx ? 'bg-orange-500 text-white font-bold' : 'bg-slate-800 text-slate-400 hover:bg-slate-700'"
                    class="px-4 py-2 rounded-xl text-xs whitespace-nowrap transition-all cursor-pointer"
                >
                    {{ item.title }}
                </button>
            </div>

            <!-- Active Gallery View -->
            <div class="rounded-3xl overflow-hidden border border-slate-800 bg-slate-950 shadow-2xl">
                <img 
                    :src="galleryImages[activeGalleryTab].url" 
                    :alt="galleryImages[activeGalleryTab].title"
                    class="w-full h-72 sm:h-[450px] object-cover"
                />
                <div class="p-4 sm:p-6 bg-slate-950/90 border-t border-slate-800 flex items-center justify-between">
                    <div>
                        <h4 class="text-base font-black text-white">{{ galleryImages[activeGalleryTab].title }}</h4>
                        <p class="text-xs text-slate-400 mt-0.5">{{ galleryImages[activeGalleryTab].sub }}</p>
                    </div>
                    <button 
                        @click="scrollToForm"
                        class="px-4 py-2 rounded-xl bg-orange-500 text-white font-bold text-xs hover:bg-orange-600 transition-all cursor-pointer shrink-0"
                    >
                        Ariza qoldirish →
                    </button>
                </div>
            </div>
        </section>

        <!-- Majmua Afzalliklari (6 Cards) -->
        <section class="py-12 px-4 sm:px-6 max-w-4xl mx-auto">
            <div class="text-center mb-8">
                <span class="text-xs font-black uppercase tracking-wider text-orange-400 block mb-1">Afzalliklar</span>
                <h3 class="text-2xl sm:text-3xl font-black text-white">Nega Aynan SHER-DOR TJM?</h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 hover:border-orange-500/50 transition-all space-y-2">
                    <span class="text-3xl">🏰</span>
                    <h4 class="text-sm font-black text-white">Mustahkam Monolit Konstruksiya</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        9 ballik seysmik chidamlilikka ega monolit temir-beton karkas va pishgan g'ishtdan qurilgan.
                    </p>
                </div>

                <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 hover:border-orange-500/50 transition-all space-y-2">
                    <span class="text-3xl">🚗</span>
                    <h4 class="text-sm font-black text-white">Keng Yerosti Avtoturargohi</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Avtomobillaringiz xavfsizligi uchun yerosti va yerusti keng qulay parking joylari mavjud.
                    </p>
                </div>

                <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 hover:border-orange-500/50 transition-all space-y-2">
                    <span class="text-3xl">🛝</span>
                    <h4 class="text-sm font-black text-white">Bolalar Maydonchasi & Hovli</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Farzandlaringiz erkin o'ynashi uchun yopiq xavfsiz maydoncha va yashil dam olish hududlari.
                    </p>
                </div>

                <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 hover:border-orange-500/50 transition-all space-y-2">
                    <span class="text-3xl">⚡</span>
                    <h4 class="text-sm font-black text-white">Zamonaviy Tezyurar Liftlar</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Har bir blokda shovqinsiz va ishonchli xalqaro standartdagi yuk va yo'lovchi liftlari.
                    </p>
                </div>

                <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 hover:border-orange-500/50 transition-all space-y-2">
                    <span class="text-3xl">🛡️</span>
                    <h4 class="text-sm font-black text-white">24/7 Xavfsizlik & Kamera</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Hudud to'liq nazorat ostida, domofon tizimi va uzluksiz video kuzatuv kameralari bilan jihozlangan.
                    </p>
                </div>

                <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 hover:border-orange-500/50 transition-all space-y-2">
                    <span class="text-3xl">🏪</span>
                    <h4 class="text-sm font-black text-white">1-Qavatda Savdo Do'konlari</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Supermarket, dorixona, kafe va maishiy xizmatlar bevosita uyingizning birinchi qavatida.
                    </p>
                </div>
            </div>
        </section>

        <!-- Location & Contact Footer -->
        <footer class="border-t border-slate-800/80 bg-slate-950 py-10 px-4 sm:px-6">
            <div class="max-w-4xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-6 text-center sm:text-left">
                <div>
                    <h4 class="text-lg font-black text-white">SHER-DOR TURAR-JOY MAJMUASI</h4>
                    <p class="text-xs text-slate-400 mt-1">
                        Samarqand viloyati, Urgut tumani, Do'stlik mahallasi, Navoiy shoh ko'chasi
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <a 
                        :href="`tel:${phoneContact.replace(/\s+/g, '')}`" 
                        class="px-5 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-black text-xs transition-all shadow-md shadow-orange-500/25 flex items-center gap-2"
                    >
                        <span>📞</span>
                        <span>{{ phoneContact }}</span>
                    </a>
                </div>
            </div>
        </footer>

        <!-- Mobile Bottom Sticky Floating Bar -->
        <div class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-slate-950/95 backdrop-blur-md border-t border-slate-800 p-3 flex items-center gap-3">
            <a 
                :href="`tel:${phoneContact.replace(/\s+/g, '')}`" 
                class="flex-1 py-3 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-black text-center flex items-center justify-center gap-1.5"
            >
                <span>📞</span>
                <span>Qo'ng'iroq</span>
            </a>
            <button 
                @click="scrollToForm" 
                class="flex-1 py-3 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-xl text-xs font-black text-center shadow-lg shadow-orange-500/30 flex items-center justify-center gap-1.5 cursor-pointer"
            >
                <span>📝</span>
                <span>Ariza Qoldirish</span>
            </button>
        </div>

    </div>
</template>
