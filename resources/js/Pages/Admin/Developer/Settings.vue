<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    isUnlocked: Boolean,
    apiKeys: Array,
    users: Array,
    sipSettings: Object,
    promoSettings: Object,
});

const isProcessing = ref(false);
const showSipPassword = ref(false);

const sipForm = useForm({
    asterisk_sip_host: props.sipSettings?.asterisk_sip_host || '',
    asterisk_sip_username: props.sipSettings?.asterisk_sip_username || '',
    asterisk_sip_password: '',
    asterisk_port: props.sipSettings?.asterisk_port || 5060,
});

const otpForm = useForm({
    otp: ''
});

const keyForm = useForm({
    name: ''
});

const promoForm = useForm({
    promo_title: props.promoSettings?.promo_title || '',
    promo_subtitle: props.promoSettings?.promo_subtitle || '',
    promo_description: props.promoSettings?.promo_description || '',
    promo_image: null,
});
const promoImagePreview = ref(props.promoSettings?.promo_image ? '/storage/' + props.promoSettings.promo_image : null);

function handleImageUpload(e) {
    const file = e.target.files[0];
    if (file) {
        promoForm.promo_image = file;
        promoImagePreview.value = URL.createObjectURL(file);
    }
}

function requestOtp() {
    isProcessing.value = true;
    router.post(route('admin.developer.otp.request'), {}, {
        onFinish: () => isProcessing.value = false
    });
}

function verifyOtp() {
    isProcessing.value = true;
    otpForm.post(route('admin.developer.otp.verify'), {
        onSuccess: () => { otpForm.reset(); },
        onFinish: () => isProcessing.value = false
    });
}

function generateKey() {
    isProcessing.value = true;
    keyForm.post(route('admin.developer.keys.store'), {
        onSuccess: () => { keyForm.reset(); },
        onFinish: () => isProcessing.value = false
    });
}

function revokeKey(id) {
    if(confirm('Ushbu API Kalitni haqiqatan ham o\'chirib tashlaysizmi? Bu kamerani tizimdan uzadi!')) {
        router.delete(route('admin.developer.keys.destroy', id));
    }
}

function updateChatId(user) {
    isProcessing.value = true;
    router.put(route('admin.developer.users.update_chat_id', user.id), {
        telegram_chat_id: user.telegram_chat_id
    }, {
        preserveScroll: true,
        onFinish: () => {
            isProcessing.value = false;
        }
    });
}

function updateSipSettings() {
    isProcessing.value = true;
    sipForm.post(route('admin.developer.sip.update'), {
        preserveScroll: true,
        onSuccess: () => {
            sipForm.asterisk_sip_password = ''; // clear after save
            isProcessing.value = false;
        },
        onError: () => isProcessing.value = false
    });
}

function updatePromoSettings() {
    isProcessing.value = true;
    promoForm.post(route('admin.developer.promo.update'), {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => isProcessing.value = false
    });
}

function lockSettings() {
    if (confirm('Dasturchi panelini qulflab, bosh sahifaga qaytasizmi?')) {
        router.post(route('admin.developer.lock'));
    }
}
</script>

<template>
    <AdminLayout title="Dasturchi Sozlamalari">
        <div class="max-w-6xl mx-auto space-y-6 animate-[fadeIn_0.5s_ease-out]">
            
            <!-- LOCKED STATE -->
            <div v-if="!isUnlocked" class="flex items-center justify-center min-h-[60vh]">
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 md:p-8 rounded-3xl w-full max-w-md border border-red-200 shadow-sm text-center relative overflow-hidden group">
                    <div class="absolute inset-0 bg-red-50 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-0"></div>
                    
                    <div class="relative z-10">
                        <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6 border border-red-200 shadow-sm">
                            <span class="text-4xl text-red-600">🔒</span>
                        </div>

                        <h2 class="text-2xl font-display font-bold text-gray-900 mb-2">Maxsus Ruxsat Talab Qilinadi</h2>
                        <p class="text-xs font-mono text-gray-500 mb-8">Bu bo'lim tizim arxitekturasini boshqarish uchun xizmat qiladi. Davom etish uchun Telegram orqali tasdiqlash kodini yuboring.</p>

                        <button @click="requestOtp" :disabled="isProcessing" class="w-full py-4 mb-6 hover:bg-gray-100 text-gray-900 font-mono uppercase tracking-widest text-sm rounded-xl transition-colors border border-gray-200 cursor-pointer relative z-20">
                            📱 Telegramga PIN Kod Yuborish
                        </button>

                        <form @submit.prevent="verifyOtp" class="space-y-4">
                            <input v-model="otpForm.otp" type="text" maxlength="6" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-center text-2xl tracking-[1em] font-mono text-red-600 relative z-20" placeholder="------" required>
                            <p v-if="otpForm.errors.otp" class="text-red-600 text-xs font-mono mt-1">{{ otpForm.errors.otp }}</p>

                            <button type="submit" :disabled="isProcessing" class="w-full py-4 bg-red-50 text-red-600 border border-red-500 font-bold font-mono uppercase tracking-widest text-sm rounded-xl hover:bg-red-600 hover:text-black hover:shadow-sm transition-all disabled:opacity-50 cursor-pointer relative z-20">
                                Qulfni Ochish
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- UNLOCKED STATE -->
            <div v-else class="space-y-6">
                <!-- TOP ACTION BAR -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-gray-200 flex justify-between items-center shadow-lg bg-gray-500/50">
                    <h1 class="text-xl font-display font-bold text-gray-900 flex items-center gap-2">
                        <span class="text-blue-600">⚙️</span> Dasturchi Paneli
                    </h1>
                    <button @click="lockSettings" class="px-5 py-2.5 bg-red-50 border border-red-200 text-red-600 hover:bg-red-600 hover:text-gray-900 transition-all rounded-lg font-mono text-xs font-bold uppercase tracking-widest shadow-sm">
                        🔒 Qulflash va Chiqish
                    </button>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-1 sm:grid-cols-2 gap-8">
                    <!-- API & HARDWARE BLOCK -->
                    <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 md:p-8 rounded-3xl border border-emerald-200 relative overflow-hidden h-full flex flex-col bg-gradient-to-b from-black/40 to-transparent hover:border-emerald-200 transition-colors">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-neon-green via-neon-cyan to-neon-blue"></div>
                        
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                            <h2 class="text-2xl font-display font-bold text-gray-900 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center border border-emerald-200">
                                    <span class="text-emerald-600 drop-shadow-sm">🔓</span>
                                </div>
                                API Kalitlar
                            </h2>
                            <span class="px-4 py-1.5 bg-emerald-50 text-emerald-600 border border-emerald-200 text-[10px] font-mono font-bold uppercase tracking-widest rounded-full shadow-sm shrink-0">Dasturchi Rejimi</span>
                        </div>
                        
                        <p class="text-sm font-mono text-gray-500 mb-8 leading-relaxed">Ushbu bo'lim orqali LPR va boshqa aqlli kameralar uchun maxsus API ruxsatnomalarini (API Keys) yaratasiz. Ularni faqat ishonchli uskunalar tarmog'iga kiriting.</p>

                        <div class="bg-gray-500/50 p-6 rounded-2xl border border-gray-100 mb-8 shadow-inner">
                            <h3 class="text-xs font-mono text-gray-500 mb-4 uppercase tracking-widest flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-blue-600"></span> Yangi Uskuna Qo'shish
                            </h3>
                            <form @submit.prevent="generateKey" class="flex flex-col sm:flex-row gap-4 items-start sm:items-end">
                                <div class="flex-1 w-full">
                                    <label class="block text-[10px] font-mono text-gray-900/50 mb-2 uppercase tracking-widest">Uskuna / Kamera Nomi</label>
                                    <input v-model="keyForm.name" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900 py-3" placeholder="Masalan: Asosiy Darvoza Kamerasi" required>
                                </div>
                                <button type="submit" :disabled="isProcessing" class="px-6 py-3 w-full sm:w-auto whitespace-nowrap bg-blue-50 text-blue-600 border border-blue-200 font-bold font-mono uppercase tracking-widest text-xs rounded-xl hover:bg-blue-600 hover:text-black transition-all disabled:opacity-50">
                                    + Yaratish
                                </button>
                            </form>
                            <p v-if="keyForm.errors.name" class="text-red-600 text-[10px] font-mono mt-2">{{ keyForm.errors.name }}</p>
                        </div>

                        <h3 class="text-xs font-mono text-gray-900 mb-4 uppercase tracking-widest border-b border-gray-200 pb-2">Faol Uskunalar Ro'yxati</h3>
                        <div class="space-y-4 flex-1">
                            <div v-for="key in apiKeys" :key="key.id" class="p-5 border border-gray-200 rounded-2xl bg-white/[0.02] hover:bg-white/[0.04] transition-colors flex flex-col gap-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="text-gray-900 font-bold text-lg flex items-center gap-2">
                                            <span class="text-gray-500">📹</span> {{ key.name }}
                                        </h4>
                                        <p class="text-[10px] font-mono text-gray-500 mt-1 uppercase tracking-widest">
                                            Oxirgi faollik: <span class="text-gray-900">{{ key.last_used_at ? new Date(key.last_used_at).toLocaleString() : 'Hali ulanmagan' }}</span>
                                        </p>
                                    </div>
                                    <button @click="revokeKey(key.id)" class="p-2 bg-red-50 text-red-600 border border-red-200 rounded-lg hover:bg-red-600 hover:text-gray-900 transition-colors" title="Uskunani Tizimdan Uzish">
                                        🗑️
                                    </button>
                                </div>
                                <div class="p-3 bg-gray-500/50 rounded-xl border border-emerald-200 font-mono text-xs text-emerald-600 break-all shadow-inner select-all cursor-text relative group">
                                    {{ key.api_key }}
                                </div>
                            </div>
                            <div v-if="apiKeys.length === 0" class="flex flex-col items-center justify-center py-12 border border-dashed border-gray-200 rounded-2xl bg-white/[0.01]">
                                <span class="text-4xl mb-3 opacity-50">📭</span>
                                <p class="text-gray-500 font-mono text-xs uppercase tracking-widest">Uskunalar topilmadi</p>
                            </div>
                        </div>
                    </div>

                    <!-- TELEGRAM BOT USERS BLOCK -->
                    <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 md:p-8 rounded-3xl border border-neon-blue/20 relative overflow-hidden h-full flex flex-col bg-gradient-to-b from-black/40 to-transparent hover:border-neon-blue/40 transition-colors">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-neon-blue via-neon-cyan to-white"></div>
                        
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                            <h2 class="text-2xl font-display font-bold text-gray-900 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-neon-blue/10 flex items-center justify-center border border-neon-blue/30">
                                    <span class="text-neon-blue drop-shadow-sm">🤖</span>
                                </div>
                                Bot & Xodimlar
                            </h2>
                            <span class="px-4 py-1.5 bg-neon-blue/10 text-neon-blue border border-neon-blue/30 text-[10px] font-mono font-bold uppercase tracking-widest rounded-full shadow-sm shrink-0">Integratsiya</span>
                        </div>
                        
                        <p class="text-sm font-mono text-gray-500 mb-8 leading-relaxed">Xodimlar Telegram bot orqali bildirishnomalar olishi yoki tasdiqlash funksiyalaridan foydalanishi uchun ularning shaxsiy <code class="bg-gray-500/50 px-1 rounded text-gray-900">Chat ID</code> larini bu yerga biriktiring.</p>

                        <div class="space-y-4 flex-1 overflow-y-auto max-h-[600px] pr-2 custom-scrollbar">
                            <div v-for="user in users" :key="user.id" class="p-5 border border-gray-200 rounded-2xl bg-white/[0.02] hover:bg-white/[0.04] transition-colors flex flex-col gap-5">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center text-xl overflow-hidden shrink-0">
                                        <img v-if="user.photo_path" :src="'/storage/' + user.photo_path" class="w-full h-full object-cover">
                                        <span v-else>👤</span>
                                    </div>
                                    <div>
                                        <h4 class="text-gray-900 font-bold text-lg">{{ user.name }}</h4>
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            <span class="px-2 py-0.5 border border-gray-200 rounded text-[9px] font-mono text-gray-500 uppercase tracking-wider">{{ user.email }}</span>
                                            <span class="px-2 py-0.5 bg-blue-50 border border-blue-200 rounded text-[9px] font-mono text-blue-600 uppercase tracking-wider font-bold">{{ user.roles.join(', ') || 'Rolsiz' }}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col sm:flex-row items-center gap-3 w-full bg-gray-500/50 p-3 rounded-xl border border-gray-100">
                                    <div class="relative w-full">
                                        <input v-model="user.telegram_chat_id" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full text-gray-900 font-mono text-sm pl-10 py-2 border-transparent focus:border-neon-blue bg-transparent" placeholder="Chat ID (masalan: 1412501744)">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-neon-blue opacity-70">💬</span>
                                    </div>
                                    <button @click="updateChatId(user)" :disabled="isProcessing" class="px-5 py-2 w-full sm:w-auto bg-neon-blue/10 text-neon-blue border border-neon-blue/50 text-xs font-bold font-mono uppercase tracking-widest rounded-lg hover:bg-neon-blue hover:text-gray-900 transition-all disabled:opacity-50 whitespace-nowrap">{{ $t('save') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SIP SETTINGS BLOCK -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 md:p-8 rounded-3xl border border-neon-purple/20 relative overflow-hidden mt-8 bg-gradient-to-r from-black/40 to-transparent hover:border-neon-purple/40 transition-colors">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-neon-purple via-neon-magenta to-white"></div>
                    
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                        <h2 class="text-2xl font-display font-bold text-gray-900 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-neon-purple/10 flex items-center justify-center border border-neon-purple/30">
                                <span class="text-neon-purple drop-shadow-sm">📞</span>
                            </div>
                            IP Telefoniya (Asterisk/SIP)
                        </h2>
                        <span class="px-4 py-1.5 bg-neon-purple/10 text-neon-purple border border-neon-purple/30 text-[10px] font-mono font-bold uppercase tracking-widest rounded-full shadow-sm">Aloqa Serveri</span>
                    </div>

                    <p class="text-sm font-mono text-gray-500 mb-8 leading-relaxed max-w-3xl">Tizimni korxona PBX serveriga (Asterisk) ulash orqali CRM orqali to'g'ridan-to'g'ri qo'ng'iroqlarni amalga oshirish mumkin. Qatorlardagi PBX sozlamalarini Uztelecom, EastTelecom yoki shaxsiy VoIP provayderingizga moslang.</p>

                    <form @submit.prevent="updateSipSettings" class="bg-gray-500/50 p-6 sm:p-4 md:p-8 rounded-2xl border border-gray-100 shadow-inner">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-[10px] font-mono text-gray-900/50 mb-2 uppercase tracking-widest flex items-center gap-2">
                                    <span class="text-neon-purple">🌐</span> SIP Host / IP
                                </label>
                                <input v-model="sipForm.asterisk_sip_host" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full focus:border-neon-purple focus:ring-neon-purple py-3 font-mono" placeholder="10.10.10.1">
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-mono text-gray-900/50 mb-2 uppercase tracking-widest flex items-center gap-2">
                                    <span class="text-neon-purple">👤</span> SIP Raqam (User)
                                </label>
                                <input v-model="sipForm.asterisk_sip_username" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full focus:border-neon-purple focus:ring-neon-purple py-3 font-mono" placeholder="998711234567">
                            </div>
                            
                            <div class="relative">
                                <label class="block text-[10px] font-mono text-gray-900/50 mb-2 uppercase tracking-widest flex items-center gap-2">
                                    <span class="text-neon-purple">🔑</span> SIP Parol 
                                    <span v-if="sipSettings?.has_password" class="text-emerald-600 ml-1 text-[8px]">(Saqlangan)</span>
                                </label>
                                <div class="relative">
                                    <input :type="showSipPassword ? 'text' : 'password'" v-model="sipForm.asterisk_sip_password" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full pr-10 focus:border-neon-purple focus:ring-neon-purple py-3 font-mono" placeholder="Yangi parol...">
                                    <button type="button" @click="showSipPassword = !showSipPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-900 transition-colors">
                                        <span v-if="showSipPassword">👁️</span>
                                        <span v-else>👁️‍🗨️</span>
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-mono text-gray-900/50 mb-2 uppercase tracking-widest flex items-center gap-2">
                                    <span class="text-neon-purple">🔌</span> Port
                                </label>
                                <input v-model="sipForm.asterisk_port" type="number" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full focus:border-neon-purple focus:ring-neon-purple py-3 font-mono" placeholder="5060">
                            </div>
                        </div>

                        <div class="flex justify-end mt-8 pt-6 border-t border-gray-100">
                            <button type="submit" :disabled="isProcessing" class="px-8 py-4 bg-neon-purple/10 text-neon-purple border border-neon-purple/50 text-sm font-bold font-mono uppercase tracking-widest rounded-xl hover:bg-neon-purple hover:text-gray-900 transition-all disabled:opacity-50 flex items-center gap-3 shadow-sm hover:shadow-sm">
                                <span v-if="isProcessing" class="animate-spin">🔄</span>
                                💾 Konfiguratsiyani Saqlash
                            </button>
                        </div>
                    </form>
                </div>

                <!-- PROMO SITE CMS BLOCK -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 md:p-8 rounded-3xl border border-neon-magenta/20 relative overflow-hidden mt-8 bg-gradient-to-r from-black/40 to-transparent hover:border-neon-magenta/40 transition-colors">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-neon-magenta via-neon-red to-white"></div>
                    
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                        <h2 class="text-2xl font-display font-bold text-gray-900 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center border border-neon-magenta/30">
                                <span class="text-indigo-600 drop-shadow-sm">🌐</span>
                            </div>
                            Promo Sayt Boshqaruvi
                        </h2>
                        <span class="px-4 py-1.5 bg-indigo-50 text-indigo-600 border border-neon-magenta/30 text-[10px] font-mono font-bold uppercase tracking-widest rounded-full shadow-sm">CMS</span>
                    </div>

                    <p class="text-sm font-mono text-gray-500 mb-8 leading-relaxed max-w-3xl">Ochiq reklama sahifasini boshqarish uchun shu yerdan foydalaning. Matnlar va Asosiy Rasmni yuklaganingizda, public sahifa darhol o'zgaradi.</p>

                    <form @submit.prevent="updatePromoSettings" class="bg-gray-500/50 p-6 sm:p-4 md:p-8 rounded-2xl border border-gray-100 shadow-inner">
                        <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 gap-8">
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-900/50 mb-2 uppercase tracking-widest flex items-center gap-2">
                                        <span class="text-indigo-600">📝</span> Sarlavha (Title)
                                    </label>
                                    <input v-model="promoForm.promo_title" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full focus:border-neon-magenta focus:ring-neon-magenta py-3 font-mono" placeholder="Masalan: Maxsus Taklif!">
                                </div>
                                
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-900/50 mb-2 uppercase tracking-widest flex items-center gap-2">
                                        <span class="text-indigo-600">🏷️</span> Qisqacha ta'rif (Subtitle)
                                    </label>
                                    <input v-model="promoForm.promo_subtitle" type="text" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full focus:border-neon-magenta focus:ring-neon-magenta py-3 font-mono" placeholder="Masalan: Yangi uylar haqida...">
                                </div>

                                <div>
                                    <label class="block text-[10px] font-mono text-gray-900/50 mb-2 uppercase tracking-widest flex items-center gap-2">
                                        <span class="text-indigo-600">📄</span> Uy haqida ma'lumot (Description)
                                    </label>
                                    <textarea v-model="promoForm.promo_description" rows="5" class="bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full focus:border-neon-magenta focus:ring-neon-magenta py-3 font-mono resize-none custom-scrollbar" placeholder="Sotuvdagi uylar, infratuzilma yoki maxsus narxlar haqida yozing..."></textarea>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <label class="block text-[10px] font-mono text-gray-900/50 uppercase tracking-widest flex items-center gap-2">
                                    <span class="text-indigo-600">🖼️</span> Asosiy Rasm (Promo Image)
                                </label>
                                <div class="w-full h-64 border-2 border-dashed border-gray-300 rounded-xl bg-gray-500/50 flex flex-col items-center justify-center relative overflow-hidden group hover:border-neon-magenta/50 transition-colors">
                                    <input type="file" @change="handleImageUpload" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                    <template v-if="promoImagePreview">
                                        <img :src="promoImagePreview" class="w-full h-full object-cover opacity-60 group-hover:opacity-30 transition-opacity">
                                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                            <span class="bg-gray-500/50 px-4 py-2 rounded-lg text-indigo-600 font-mono text-xs uppercase tracking-widest">Rasmni Almashtirish</span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span class="text-4xl mb-2 opacity-50">📸</span>
                                        <span class="text-gray-500 font-mono text-xs uppercase tracking-widest">Rasm yuklash uchun shu yerga bosing</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end mt-8 pt-6 border-t border-gray-100">
                            <button type="submit" :disabled="isProcessing" class="px-8 py-4 bg-indigo-50 text-indigo-600 border border-neon-magenta/50 text-sm font-bold font-mono uppercase tracking-widest rounded-xl hover:bg-indigo-600 hover:text-gray-900 transition-all disabled:opacity-50 flex items-center gap-3 shadow-sm hover:shadow-sm">
                                <span v-if="isProcessing" class="animate-spin">🔄</span>
                                💾 CMS Sozlamalarini Saqlash
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
