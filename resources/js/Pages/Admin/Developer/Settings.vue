<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    isUnlocked: Boolean,
    apiKeys: Array,
    users: Array,
    sipSettings: Object,
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
                <div class="glass-panel p-8 rounded-3xl w-full max-w-md border border-neon-red/30 shadow-[0_0_40px_rgba(255,0,0,0.1)] text-center relative overflow-hidden group">
                    <div class="absolute inset-0 bg-neon-red/5 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-0"></div>
                    
                    <div class="relative z-10">
                        <div class="w-20 h-20 bg-neon-red/10 rounded-full flex items-center justify-center mx-auto mb-6 border border-neon-red/30 shadow-[0_0_20px_rgba(255,0,0,0.2)]">
                            <span class="text-4xl text-neon-red">🔒</span>
                        </div>

                        <h2 class="text-2xl font-display font-bold text-white mb-2">Maxsus Ruxsat Talab Qilinadi</h2>
                        <p class="text-xs font-mono text-text-muted mb-8">Bu bo'lim tizim arxitekturasini boshqarish uchun xizmat qiladi. Davom etish uchun Telegram orqali tasdiqlash kodini yuboring.</p>

                        <button @click="requestOtp" :disabled="isProcessing" class="w-full py-4 mb-6 bg-white/5 hover:bg-white/10 text-white font-mono uppercase tracking-widest text-sm rounded-xl transition-colors border border-white/10 cursor-pointer relative z-20">
                            📱 Telegramga PIN Kod Yuborish
                        </button>

                        <form @submit.prevent="verifyOtp" class="space-y-4">
                            <input v-model="otpForm.otp" type="text" maxlength="6" class="glass-input w-full text-center text-2xl tracking-[1em] font-mono text-neon-red relative z-20" placeholder="------" required>
                            <p v-if="otpForm.errors.otp" class="text-neon-red text-xs font-mono mt-1">{{ otpForm.errors.otp }}</p>

                            <button type="submit" :disabled="isProcessing" class="w-full py-4 bg-neon-red/20 text-neon-red border border-neon-red font-bold font-mono uppercase tracking-widest text-sm rounded-xl hover:bg-neon-red hover:text-black hover:shadow-[0_0_20px_#ff0000] transition-all disabled:opacity-50 cursor-pointer relative z-20">
                                Qulfni Ochish
                            </button>
                        </form>
                    </div>
                </div>
            </            <!-- UNLOCKED STATE -->
            <div v-else class="space-y-6">
                <!-- TOP ACTION BAR -->
                <div class="glass-panel p-4 rounded-xl border border-white/10 flex justify-between items-center shadow-lg bg-black/20 backdrop-blur-md">
                    <h1 class="text-xl font-display font-bold text-white flex items-center gap-2">
                        <span class="text-neon-cyan">⚙️</span> Dasturchi Paneli
                    </h1>
                    <button @click="lockSettings" class="px-5 py-2.5 bg-neon-red/20 border border-neon-red/50 text-neon-red hover:bg-neon-red hover:text-white transition-all rounded-lg font-mono text-xs font-bold uppercase tracking-widest shadow-[0_0_10px_rgba(255,0,0,0.2)]">
                        🔒 Qulflash va Chiqish
                    </button>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <!-- API & HARDWARE BLOCK -->
                    <div class="glass-panel p-6 rounded-2xl border border-neon-green/30 relative overflow-hidden h-full flex flex-col">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-neon-green via-neon-cyan to-neon-blue"></div>
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-display font-bold text-white flex items-center gap-3">
                                <span class="text-neon-green">🔓</span> API va Uskunalar Boshqaruvi
                            </h2>
                            <span class="px-3 py-1 bg-neon-green/10 text-neon-green border border-neon-green/30 text-[10px] font-mono uppercase tracking-widest rounded-full">Dasturchi Rejimi Faol</span>
                        </div>
                        <p class="text-sm font-mono text-text-muted mb-8">Bu yerdan siz aqlli kameralar uchun ruxsatnomalar (API Keys) yaratishingiz va bekor qilishingiz mumkin. API kalitlarini faqat ishonchli uskunalar sozlamalariga kiriting.</p>

                        <div class="bg-black/30 p-6 rounded-xl border border-white/5 mb-8">
                            <h3 class="text-sm font-mono text-white mb-4 uppercase tracking-widest border-b border-white/10 pb-2">Yangi Uskuna Qo'shish</h3>
                            <form @submit.prevent="generateKey" class="flex flex-col sm:flex-row gap-4 items-start sm:items-end">
                                <div class="flex-1 w-full">
                                    <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Kamera / Uskuna Nomi</label>
                                    <input v-model="keyForm.name" type="text" class="glass-input w-full text-white" placeholder="Masalan: Darvoza 1 LPR Kamera" required>
                                    <p v-if="keyForm.errors.name" class="text-neon-red text-xs mt-1">{{ keyForm.errors.name }}</p>
                                </div>
                                <button type="submit" :disabled="isProcessing" class="px-6 py-3 w-full sm:w-auto whitespace-nowrap bg-neon-cyan/20 text-neon-cyan border border-neon-cyan font-bold font-mono uppercase tracking-widest text-sm rounded hover:bg-neon-cyan hover:text-black transition-all disabled:opacity-50">
                                    + Generatsiya Qilish
                                </button>
                            </form>
                        </div>

                        <h3 class="text-sm font-mono text-white mb-4 uppercase tracking-widest">Faol Uskunalar va Kalitlar</h3>
                        <div class="space-y-4 flex-1">
                            <div v-for="key in apiKeys" :key="key.id" class="p-4 border border-white/10 rounded-xl bg-white/[0.02] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                <div>
                                    <h4 class="text-white font-bold text-lg flex items-center gap-2">
                                        📹 {{ key.name }}
                                    </h4>
                                    <div class="mt-2 p-2 bg-black/60 rounded border border-white/10 font-mono text-xs text-neon-green break-all">
                                        {{ key.api_key }}
                                    </div>
                                    <p class="text-[10px] font-mono text-text-muted mt-2 uppercase tracking-widest">
                                        So'nggi Faollik: {{ key.last_used_at ? new Date(key.last_used_at).toLocaleString() : 'Hali ishlatilmadi' }}
                                    </p>
                                </div>
                                <button @click="revokeKey(key.id)" class="px-4 py-2 bg-neon-red/10 text-neon-red border border-neon-red/30 text-xs font-mono uppercase tracking-widest rounded hover:bg-neon-red hover:text-white transition-colors">
                                    ✖ O'chirish (Revoke)
                                </button>
                            </div>
                            <div v-if="apiKeys.length === 0" class="text-center py-10 text-text-muted font-mono text-sm border border-dashed border-white/10 rounded-xl">
                                Hozircha hech qanday kamera ulanmagan
                            </div>
                        </div>
                    </div>

                    <!-- TELEGRAM BOT USERS BLOCK -->
                    <div class="glass-panel p-6 rounded-2xl border border-neon-blue/30 relative overflow-hidden h-full flex flex-col">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-neon-blue via-neon-cyan to-white"></div>
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-display font-bold text-white flex items-center gap-3">
                                <span class="text-neon-blue">🤖</span> Telegram Bot & Xodimlar
                            </h2>
                        </div>
                        <p class="text-sm font-mono text-text-muted mb-8">Bu bo'lim orqali Tizimdagi xodim va hamkorlarni Telegram botiga biriktirasiz. O'zining Chat ID'sini kiritgan foydalanuvchilar Botdan ruxsat oladilar.</p>

                        <div class="space-y-4 flex-1 overflow-y-auto max-h-[600px] pr-2 custom-scrollbar">
                            <div v-for="user in users" :key="user.id" class="p-4 border border-white/10 rounded-xl bg-white/[0.02] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                <div class="flex-1">
                                    <h4 class="text-white font-bold text-lg flex items-center gap-2">
                                        👤 {{ user.name }}
                                    </h4>
                                    <p class="text-xs font-mono text-text-muted mt-1 uppercase tracking-widest">
                                        Email: <span class="text-white">{{ user.email }}</span> | 
                                        Rol: <span class="text-neon-cyan">{{ user.roles.join(', ') || 'Rolsiz' }}</span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 w-full md:w-auto">
                                    <div class="relative w-full md:w-48">
                                        <input v-model="user.telegram_chat_id" type="text" class="glass-input w-full text-white font-mono text-sm pl-8" placeholder="Chat ID">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted">💬</span>
                                    </div>
                                    <button @click="updateChatId(user)" :disabled="isProcessing" class="px-4 py-2 bg-neon-blue/20 text-neon-blue border border-neon-blue/50 text-xs font-mono uppercase tracking-widest rounded hover:bg-neon-blue hover:text-white transition-colors disabled:opacity-50">
                                        Saqlash
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SIP SETTINGS BLOCK -->
                <div class="glass-panel p-6 rounded-2xl border border-neon-purple/30 relative overflow-hidden mt-6">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-neon-purple via-neon-magenta to-white"></div>
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-display font-bold text-white flex items-center gap-3">
                            <span class="text-neon-purple">📞</span> IP Telefoniya (Asterisk/SIP) Sozlamalari
                        </h2>
                    </div>
                    <p class="text-sm font-mono text-text-muted mb-8">Bu bo'lim orqali Tizimni istalgan VoIP provayderga (Uztelecom, EastTelecom va h.k.) ulashingiz mumkin. Asterisk ushbu ma'lumotlarni o'qiydi.</p>

                    <form @submit.prevent="updateSipSettings" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase tracking-widest">SIP Server / Provayder IP manzili</label>
                                <input v-model="sipForm.asterisk_sip_host" type="text" class="glass-input w-full focus:border-neon-purple focus:ring-neon-purple" placeholder="masalan: 10.10.10.1">
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase tracking-widest">SIP Raqam (Username)</label>
                                <input v-model="sipForm.asterisk_sip_username" type="text" class="glass-input w-full focus:border-neon-purple focus:ring-neon-purple" placeholder="masalan: 998711234567">
                            </div>
                            
                            <div class="relative">
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase tracking-widest">
                                    SIP Parol <span v-if="sipSettings?.has_password" class="text-neon-green ml-2">(Maxfiy saqlangan)</span>
                                </label>
                                <div class="relative">
                                    <input :type="showSipPassword ? 'text' : 'password'" v-model="sipForm.asterisk_sip_password" class="glass-input w-full pr-10 focus:border-neon-purple focus:ring-neon-purple" placeholder="Yangi parol...">
                                    <button type="button" @click="showSipPassword = !showSipPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-text-muted hover:text-white transition-colors">
                                        <span v-if="showSipPassword">👁️</span>
                                        <span v-else>👁️‍🗨️</span>
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase tracking-widest">Port (Odatda 5060)</label>
                                <input v-model="sipForm.asterisk_port" type="number" class="glass-input w-full focus:border-neon-purple focus:ring-neon-purple" placeholder="5060">
                            </div>
                        </div>

                        <div class="flex justify-end pt-4 border-t border-white/5">
                            <button type="submit" :disabled="isProcessing" class="px-6 py-3 bg-neon-purple/20 text-neon-purple border border-neon-purple/50 text-xs font-bold font-mono uppercase tracking-widest rounded-lg hover:bg-neon-purple hover:text-white transition-all disabled:opacity-50 flex items-center gap-2 shadow-[0_0_15px_rgba(157,0,255,0.2)]">
                                <span v-if="isProcessing" class="animate-spin">🔄</span>
                                💾 Saqlash va Serverni Yangilash
                            </button>
                        </div>
                    </form>
                </div>
            </div>/form>
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
