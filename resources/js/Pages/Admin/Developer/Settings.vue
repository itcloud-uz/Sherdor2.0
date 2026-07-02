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
    promo_form_title: props.promoSettings?.promo_form_title || '',
    promo_form_subtitle: props.promoSettings?.promo_form_subtitle || '',
    promo_button_text: props.promoSettings?.promo_button_text || '',
    promo_success_message: props.promoSettings?.promo_success_message || '',
    promo_image: null,
});
const integrationForm = useForm({
    telegram_lead_bot_token: props.promoSettings?.telegram_lead_bot_token || '',
    telegram_lead_chat_id: props.promoSettings?.telegram_lead_chat_id || '',
    fb_pixel_id: props.promoSettings?.fb_pixel_id || '',
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

function updateIntegrationSettings() {
    isProcessing.value = true;
    integrationForm.post(route('admin.developer.integrations.update'), {
        preserveScroll: true,
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
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-4 md:p-6 flex flex-col sm:flex-row justify-between items-center gap-4 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-amber-500/5 blur-[50px] pointer-events-none"></div>
                    <h1 class="text-xl font-display font-bold text-gray-900 flex items-center gap-2">
                        <span class="text-amber-500">⚙️</span> Dasturchi Sozlamalari
                    </h1>
                    <button @click="lockSettings" class="px-5 py-2.5 bg-red-50 border border-red-200 text-red-600 hover:bg-red-600 hover:text-white transition-all rounded-xl font-mono text-xs font-bold uppercase tracking-widest shadow-sm">
                        🔒 Qulflash va Chiqish
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-8">
                    <!-- API & HARDWARE BLOCK -->
                    <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 md:p-8 relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/5 blur-[100px] pointer-events-none"></div>
                        
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                            <h2 class="text-xl font-display font-bold text-gray-900 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center border border-emerald-100">
                                    <span class="text-emerald-600 drop-shadow-sm">🔑</span>
                                </div>
                                API Kalitlar
                            </h2>
                            <span class="px-4 py-1.5 bg-emerald-50 text-emerald-600 border border-emerald-200 text-[10px] font-mono font-bold uppercase tracking-widest rounded-full shadow-sm shrink-0">Dasturchi Rejimi</span>
                        </div>
                        
                        <p class="text-sm font-mono text-gray-500 mb-6 leading-relaxed">Ushbu bo'lim orqali LPR va boshqa aqlli kameralar uchun maxsus API ruxsatnomalarini (API Keys) yaratasiz. Ularni faqat ishonchli uskunalar tarmog'iga kiriting.</p>

                        <!-- Add new device form -->
                        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6 mb-6">
                            <h3 class="text-xs font-mono text-gray-500 mb-4 uppercase tracking-widest flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-blue-600"></span> Yangi Uskuna Qo'shish
                            </h3>
                            <form @submit.prevent="generateKey" class="flex flex-col sm:flex-row gap-4 items-start sm:items-end">
                                <div class="flex-1 w-full">
                                    <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest">Uskuna / Kamera Nomi</label>
                                    <input v-model="keyForm.name" type="text" class="bg-white border border-gray-200 rounded-xl p-3 text-gray-900 font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary w-full" placeholder="Masalan: Asosiy Darvoza Kamerasi" required>
                                </div>
                                <button type="submit" :disabled="isProcessing" class="px-6 py-3 w-full sm:w-auto whitespace-nowrap bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold font-mono uppercase tracking-widest text-xs rounded-xl hover:bg-emerald-500 hover:text-black transition-all disabled:opacity-50">
                                    + Yaratish
                                </button>
                            </form>
                            <p v-if="keyForm.errors.name" class="text-red-600 text-[10px] font-mono mt-2">{{ keyForm.errors.name }}</p>
                        </div>

                        <!-- API Keys Table -->
                        <h3 class="text-xs font-mono text-gray-900 mb-4 uppercase tracking-widest border-b border-gray-200 pb-2">Faol Uskunalar Ro'yxati</h3>
                        <div class="border border-gray-200/80 rounded-2xl overflow-hidden shadow-sm bg-white">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse bg-white">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-gray-200 font-mono text-xs uppercase text-gray-500">
                                            <th class="p-4 w-12 text-center">#</th>
                                            <th class="p-4">Uskuna / Kamera nomi</th>
                                            <th class="p-4">Oxirgi faollik</th>
                                            <th class="p-4">API Kalit</th>
                                            <th class="p-4 text-right w-36">Amallar</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-sm">
                                        <tr v-for="(key, index) in apiKeys" :key="key.id" class="hover:bg-amber-500/5 transition-colors">
                                            <td class="p-4 font-mono text-xs text-gray-500 text-center">{{ index + 1 }}</td>
                                            <td class="p-4 font-semibold text-gray-900">
                                                <div class="flex items-center gap-1.5">
                                                    <span>📹</span> {{ key.name }}
                                                </div>
                                            </td>
                                            <td class="p-4 font-mono text-xs text-gray-500">
                                                {{ key.last_used_at ? new Date(key.last_used_at).toLocaleString() : 'Hali ulanmagan' }}
                                            </td>
                                            <td class="p-4">
                                                <div class="p-2 bg-gray-50 rounded-lg border border-emerald-100 font-mono text-[10px] text-emerald-700 select-all cursor-text break-all max-w-sm" title="Nusxalash uchun bosing">
                                                    {{ key.api_key }}
                                                </div>
                                            </td>
                                            <td class="p-4 text-right">
                                                <button @click="revokeKey(key.id)" class="px-3 py-1.5 border border-gray-200 text-red-600 rounded-xl hover:bg-red-50 hover:border-red-200 transition-all text-xs font-bold flex items-center justify-center gap-1 ml-auto" title="Uskunani Tizimdan Uzish">
                                                    🗑️ O'chirish
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="apiKeys.length === 0">
                                            <td colspan="5" class="p-8 text-center text-gray-500 font-mono">Faol uskunalar mavjud emas.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TELEGRAM BOT USERS BLOCK -->
                    <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 md:p-8 relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-blue-500/5 blur-[100px] pointer-events-none"></div>
                        
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                            <h2 class="text-xl font-display font-bold text-gray-900 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center border border-blue-100">
                                    <span class="text-blue-600 drop-shadow-sm">🤖</span>
                                </div>
                                Telegram Bot & Xodimlar
                            </h2>
                            <span class="px-4 py-1.5 bg-blue-50 text-blue-600 border border-blue-200 text-[10px] font-mono font-bold uppercase tracking-widest rounded-full shadow-sm shrink-0">Integratsiya</span>
                        </div>
                        
                        <p class="text-sm font-mono text-gray-500 mb-6 leading-relaxed">Xodimlar Telegram bot orqali bildirishnomalar olishi yoki tasdiqlash funksiyalaridan foydalanishi uchun ularning shaxsiy <code class="bg-gray-100 px-1.5 py-0.5 rounded text-gray-900 font-mono">Chat ID</code> larini bu yerga biriktiring.</p>

                        <!-- Bot Users Table -->
                        <div class="border border-gray-200/80 rounded-2xl overflow-hidden shadow-sm bg-white">
                            <div class="overflow-x-auto max-h-[500px]">
                                <table class="w-full text-left border-collapse bg-white">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-gray-200 font-mono text-xs uppercase text-gray-500">
                                            <th class="p-4 w-16 text-center">Avatar</th>
                                            <th class="p-4">Xodim (Ism Sharif)</th>
                                            <th class="p-4">Email & Rol</th>
                                            <th class="p-4">Telegram Chat ID</th>
                                            <th class="p-4 text-right w-28">Amallar</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-sm">
                                        <tr v-for="user in users" :key="user.id" class="hover:bg-amber-500/5 transition-colors">
                                            <td class="p-4 text-center">
                                                <div class="w-10 h-10 rounded-full border border-gray-200 flex items-center justify-center text-lg overflow-hidden mx-auto bg-gray-50 shrink-0">
                                                    <img v-if="user.photo_path" :src="'/storage/' + user.photo_path" class="w-full h-full object-cover">
                                                    <span v-else>👤</span>
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <div class="font-semibold text-gray-900">{{ user.name }}</div>
                                            </td>
                                            <td class="p-4">
                                                <div class="flex flex-col gap-1">
                                                    <span class="font-mono text-xs text-gray-500">{{ user.email }}</span>
                                                    <div>
                                                        <span class="px-2 py-0.5 bg-blue-50 border border-blue-100 text-[9px] font-mono text-blue-600 uppercase tracking-wider font-bold rounded-full">
                                                            {{ user.roles.join(', ') || 'Rolsiz' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <div class="relative w-full max-w-[200px]">
                                                    <input v-model="user.telegram_chat_id" type="text" class="bg-white border border-gray-200 rounded-xl shadow-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500 w-full font-mono text-xs pl-8 py-2" placeholder="Chat ID (masalan: 1412501744)">
                                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs opacity-60">💬</span>
                                                </div>
                                            </td>
                                            <td class="p-4 text-right">
                                                <button @click="updateChatId(user)" :disabled="isProcessing" class="px-3 py-1.5 border border-amber-200 text-amber-600 bg-amber-50 hover:bg-amber-500 hover:text-black hover:border-amber-500 rounded-xl transition-all text-xs font-bold flex items-center justify-center gap-1 ml-auto disabled:opacity-50">
                                                    💾 Saqlash
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="users.length === 0">
                                            <td colspan="5" class="p-8 text-center text-gray-500 font-mono">Xodimlar topilmadi.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SIP SETTINGS BLOCK -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 md:p-8 relative overflow-hidden mt-8 group">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-purple-500/5 blur-[100px] pointer-events-none"></div>
                    
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                        <h2 class="text-xl font-display font-bold text-gray-900 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center border border-purple-100">
                                <span class="text-purple-600 drop-shadow-sm">📞</span>
                            </div>
                            IP Telefoniya (Asterisk/SIP)
                        </h2>
                        <span class="px-4 py-1.5 bg-purple-50 text-purple-600 border border-purple-200 text-[10px] font-mono font-bold uppercase tracking-widest rounded-full shadow-sm shrink-0">Aloqa Serveri</span>
                    </div>

                    <p class="text-sm font-mono text-gray-500 mb-6 leading-relaxed max-w-3xl">Tizimni korxona PBX serveriga (Asterisk) ulash orqali CRM orqali to'g'ridan-to'g'ri qo'ng'iroqlarni amalga oshirish mumkin. Qatorlardagi PBX sozlamalarini Uztelecom, EastTelecom yoki shaxsiy VoIP provayderingizga moslang.</p>

                    <form @submit.prevent="updateSipSettings" class="bg-gray-50 border border-gray-200 p-6 rounded-2xl">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                    <span class="text-purple-600">🌐</span> SIP Host / IP
                                </label>
                                <input v-model="sipForm.asterisk_sip_host" type="text" class="bg-white border border-gray-200 rounded-xl p-3 w-full font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary" placeholder="10.10.10.1">
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                    <span class="text-purple-600">👤</span> SIP Raqam (User)
                                </label>
                                <input v-model="sipForm.asterisk_sip_username" type="text" class="bg-white border border-gray-200 rounded-xl p-3 w-full font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary" placeholder="998711234567">
                            </div>
                            
                            <div class="relative">
                                <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                    <span class="text-purple-600">🔑</span> SIP Parol 
                                    <span v-if="sipSettings?.has_password" class="text-emerald-600 ml-1 text-[8px]">(Saqlangan)</span>
                                </label>
                                <div class="relative">
                                    <input :type="showSipPassword ? 'text' : 'password'" v-model="sipForm.asterisk_sip_password" class="bg-white border border-gray-200 rounded-xl p-3 w-full pr-10 font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary" placeholder="Yangi parol...">
                                    <button type="button" @click="showSipPassword = !showSipPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-900 transition-colors">
                                        <span v-if="showSipPassword">👁️</span>
                                        <span v-else>👁️‍qiya</span>
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                    <span class="text-purple-600">🔌</span> Port
                                </label>
                                <input v-model="sipForm.asterisk_port" type="number" class="bg-white border border-gray-200 rounded-xl p-3 w-full font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary" placeholder="5060">
                            </div>
                        </div>

                        <div class="flex justify-end mt-6 pt-6 border-t border-gray-200">
                            <button type="submit" :disabled="isProcessing" class="px-8 py-3 bg-purple-50 text-purple-700 border border-purple-200 text-xs font-bold font-mono uppercase tracking-widest rounded-xl hover:bg-purple-500 hover:text-black transition-all disabled:opacity-50 flex items-center gap-3 shadow-sm">
                                <span v-if="isProcessing" class="animate-spin">🔄</span>
                                💾 Konfiguratsiyani Saqlash
                            </button>
                        </div>
                    </form>
                </div>

                <!-- PROMO SITE CMS BLOCK -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 md:p-8 relative overflow-hidden mt-8 group">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-rose-500/5 blur-[100px] pointer-events-none"></div>
                    
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                        <h2 class="text-xl font-display font-bold text-gray-900 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center border border-rose-100">
                                <span class="text-rose-600 drop-shadow-sm">🌐</span>
                            </div>
                            Promo Sayt Boshqaruvi
                        </h2>
                        <span class="px-4 py-1.5 bg-rose-50 text-rose-600 border border-rose-200 text-[10px] font-mono font-bold uppercase tracking-widest rounded-full shadow-sm shrink-0">CMS</span>
                    </div>

                    <p class="text-sm font-mono text-gray-500 mb-6 leading-relaxed max-w-3xl">Ochiq reklama sahifasini boshqarish uchun shu yerdan foydalaning. Matnlar va Asosiy Rasmni yuklaganingizda, public sahifa darhol o'zgaradi.</p>

                    <form @submit.prevent="updatePromoSettings" class="bg-gray-50 border border-gray-200 p-6 rounded-2xl">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                        <span class="text-rose-600">📝</span> Sarlavha (Title)
                                    </label>
                                    <input v-model="promoForm.promo_title" type="text" class="bg-white border border-gray-200 rounded-xl p-3 w-full font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary" placeholder="Masalan: Maxsus Taklif!">
                                </div>
                                
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                        <span class="text-rose-600">🏷️</span> Qisqacha ta'rif (Subtitle)
                                    </label>
                                    <input v-model="promoForm.promo_subtitle" type="text" class="bg-white border border-gray-200 rounded-xl p-3 w-full font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary" placeholder="Masalan: Yangi uylar haqida...">
                                </div>

                                <div>
                                    <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                        <span class="text-rose-600">📄</span> Uy haqida ma'lumot (Description)
                                    </label>
                                    <textarea v-model="promoForm.promo_description" rows="5" class="bg-white border border-gray-200 rounded-xl p-3 w-full font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary resize-none custom-scrollbar" placeholder="Sotuvdagi uylar, infratuzilma yoki maxsus narxlar haqida yozing..."></textarea>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <label class="block text-[10px] font-mono text-gray-500 uppercase tracking-widest flex items-center gap-2">
                                    <span class="text-rose-600">🖼️</span> Asosiy Rasm (Promo Image)
                                </label>
                                <div class="w-full h-64 border-2 border-dashed border-gray-300 rounded-2xl bg-white flex flex-col items-center justify-center relative overflow-hidden group hover:border-rose-500/50 transition-colors">
                                    <input type="file" @change="handleImageUpload" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                    <template v-if="promoImagePreview">
                                        <img :src="promoImagePreview" class="w-full h-full object-cover opacity-80 group-hover:opacity-30 transition-opacity">
                                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                            <span class="bg-white/90 border border-gray-200 px-4 py-2 rounded-xl text-rose-600 font-mono text-xs uppercase tracking-widest shadow-sm">Rasmni Almashtirish</span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span class="text-4xl mb-2 opacity-50">📸</span>
                                        <span class="text-gray-500 font-mono text-xs uppercase tracking-widest">Rasm yuklash uchun shu yerga bosing</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-6 pt-6 border-t border-gray-200">
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                        <span class="text-rose-600">✍️</span> Forma Sarlavhasi
                                    </label>
                                    <input v-model="promoForm.promo_form_title" type="text" class="bg-white border border-gray-200 rounded-xl p-3 w-full font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary" placeholder="Ro'yxatdan O'tish">
                                </div>
                                
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                        <span class="text-rose-600">📋</span> Forma Qisqacha ta'rif
                                    </label>
                                    <input v-model="promoForm.promo_form_subtitle" type="text" class="bg-white border border-gray-200 rounded-xl p-3 w-full font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary" placeholder="Telefon raqamingizni qoldiring...">
                                </div>
                            </div>
                            
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                        <span class="text-rose-600">🔘</span> Tugma yozuvi
                                    </label>
                                    <input v-model="promoForm.promo_button_text" type="text" class="bg-white border border-gray-200 rounded-xl p-3 w-full font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary" placeholder="Saqlash / Yuborish">
                                </div>
                                
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                        <span class="text-rose-600">🎉</span> Muvaffaqiyat xabari
                                    </label>
                                    <input v-model="promoForm.promo_success_message" type="text" class="bg-white border border-gray-200 rounded-xl p-3 w-full font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary" placeholder="Arizangiz qabul qilindi!">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end mt-6 pt-6 border-t border-gray-200">
                            <button type="submit" :disabled="isProcessing" class="px-8 py-3 bg-rose-50 text-rose-700 border border-rose-200 text-xs font-bold font-mono uppercase tracking-widest rounded-xl hover:bg-rose-500 hover:text-black transition-all disabled:opacity-50 flex items-center gap-3 shadow-sm">
                                <span v-if="isProcessing" class="animate-spin">🔄</span>
                                💾 CMS Sozlamalarini Saqlash
                            </button>
                        </div>
                    </form>
                </div>

                <!-- EXTERNAL INTEGRATIONS BLOCK -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 md:p-8 relative overflow-hidden mt-8 group">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-cyan-500/5 blur-[100px] pointer-events-none"></div>
                    
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                        <h2 class="text-xl font-display font-bold text-gray-900 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-cyan-50 flex items-center justify-center border border-cyan-100">
                                <span class="text-cyan-600 drop-shadow-sm">🔗</span>
                            </div>
                            Tashqi Integratsiyalar (Facebook & Telegram)
                        </h2>
                        <span class="px-4 py-1.5 bg-cyan-50 text-cyan-600 border border-cyan-200 text-[10px] font-mono font-bold uppercase tracking-widest rounded-full shadow-sm shrink-0">Integrations</span>
                    </div>

                    <p class="text-sm font-mono text-gray-500 mb-6 leading-relaxed max-w-3xl">Marketing (Targeting) natijalarini kuzatish uchun Facebook Pixel ID va yangi lidlar haqida Telegram orqali xabar olish uchun Bot ma'lumotlarini kiriting.</p>

                    <form @submit.prevent="updateIntegrationSettings" class="bg-gray-50 border border-gray-200 p-6 rounded-2xl">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                    <span class="text-blue-600">📊</span> Facebook Pixel ID
                                </label>
                                <input v-model="integrationForm.fb_pixel_id" type="text" class="bg-white border border-gray-200 rounded-xl p-3 w-full font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary" placeholder="Masalan: 123456789012345">
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                    <span class="text-cyan-600">🤖</span> Telegram Bot Token
                                </label>
                                <input v-model="integrationForm.telegram_lead_bot_token" type="text" class="bg-white border border-gray-200 rounded-xl p-3 w-full font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11">
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-2 uppercase tracking-widest flex items-center gap-2">
                                    <span class="text-cyan-600">💬</span> Telegram Chat ID
                                </label>
                                <input v-model="integrationForm.telegram_lead_chat_id" type="text" class="bg-white border border-gray-200 rounded-xl p-3 w-full font-mono text-sm focus:border-neon-primary focus:ring-1 focus:ring-neon-primary" placeholder="-1001234567890 yoki 123456789">
                            </div>
                        </div>

                        <div class="flex justify-end mt-6 pt-6 border-t border-gray-200">
                            <button type="submit" :disabled="isProcessing" class="px-8 py-3 bg-cyan-50 text-cyan-700 border border-cyan-200 text-xs font-bold font-mono uppercase tracking-widest rounded-xl hover:bg-cyan-500 hover:text-black transition-all disabled:opacity-50 flex items-center gap-3 shadow-sm">
                                <span v-if="isProcessing" class="animate-spin">🔄</span>
                                💾 Integratsiyalarni Saqlash
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
