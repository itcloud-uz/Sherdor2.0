<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';

const props = defineProps({
    serviceStatus: {
        type: Object,
        default: () => ({ status: 'unknown', pid: null, uptime: null, memory: null })
    },
    botInfo: {
        type: Object,
        default: null
    },
    config: {
        type: Object,
        default: () => ({})
    },
    users: {
        type: Array,
        default: () => []
    },
    recentTransactions: {
        type: Array,
        default: () => []
    },
    recentLeads: {
        type: Array,
        default: () => []
    },
    logs: {
        type: String,
        default: ''
    }
});

// Active Tab
const activeTab = ref('config'); // 'config', 'users', 'transactions', 'leads', 'logs'

// Form for config
const configForm = useForm({
    finance_bot_token: props.config.finance_bot_token || '',
    bot_secret_key: props.config.bot_secret_key || 'default_secret_key_for_dev',
    pin_bot_token: props.config.pin_bot_token || '',
    backup_bot_token: props.config.backup_bot_token || '',
    telegram_lead_chat_id: props.config.telegram_lead_chat_id || ''
});

const isSavingConfig = ref(false);
const saveConfig = () => {
    isSavingConfig.value = true;
    configForm.post(route('admin.telegram_bot.update_config'), {
        preserveScroll: true,
        onFinish: () => {
            isSavingConfig.value = false;
        }
    });
};

// Token testing
const isTestingToken = ref(false);
const tokenTestResult = ref(null);
const testToken = async (token) => {
    if (!token) return;
    isTestingToken.value = true;
    tokenTestResult.value = null;
    try {
        const res = await axios.post(route('admin.telegram_bot.test_token'), { token });
        tokenTestResult.value = {
            success: true,
            message: res.data.message,
            bot: res.data.bot
        };
    } catch (err) {
        tokenTestResult.value = {
            success: false,
            message: err.response?.data?.message || err.message || "Ulanishda xatolik"
        };
    } finally {
        isTestingToken.value = false;
    }
};

// Service Control
const isControllingService = ref(false);
const controlService = (action) => {
    if (!confirm(`Haqiqatan ham bot xizmatini ${action} qilmoqchimisiz?`)) return;
    isControllingService.value = true;
    router.post(route('admin.telegram_bot.control_service'), { action }, {
        preserveScroll: true,
        onFinish: () => {
            isControllingService.value = false;
        }
    });
};

// Live Logs
const currentLogs = ref(props.logs || '');
const isLoadingLogs = ref(false);
const refreshLogs = async () => {
    isLoadingLogs.value = true;
    try {
        const res = await axios.get(route('admin.telegram_bot.get_logs'));
        if (res.data?.logs) {
            currentLogs.value = res.data.logs;
        }
    } catch (e) {
        console.error('Logs fetch error:', e);
    } finally {
        isLoadingLogs.value = false;
    }
};

// Test Message Form
const testMsgForm = ref({
    chat_id: '',
    message: 'Salom! Sher-Dor ERP Telegram boti sinov xabari.',
    bot_type: 'finance'
});
const isSendingMsg = ref(false);
const testMsgResult = ref(null);
const sendTestMessage = async () => {
    if (!testMsgForm.value.chat_id || !testMsgForm.value.message) return;
    isSendingMsg.value = true;
    testMsgResult.value = null;
    try {
        const res = await axios.post(route('admin.telegram_bot.send_message'), testMsgForm.value);
        testMsgResult.value = { success: true, message: res.data.message };
    } catch (err) {
        testMsgResult.value = {
            success: false,
            message: err.response?.data?.message || "Xabar yuborishda xatolik"
        };
    } finally {
        isSendingMsg.value = false;
    }
};

// Users management
const userSearch = ref('');
const filteredUsers = computed(() => {
    if (!userSearch.value.trim()) return props.users;
    const q = userSearch.value.toLowerCase();
    return props.users.filter(u => 
        (u.name && u.name.toLowerCase().includes(q)) ||
        (u.phone && u.phone.toLowerCase().includes(q)) ||
        (u.telegram_chat_id && u.telegram_chat_id.includes(q))
    );
});

const selectedUserForEdit = ref(null);
const editChatIdValue = ref('');
const isSavingUserChatId = ref(false);

const openEditUserModal = (user) => {
    selectedUserForEdit.value = user;
    editChatIdValue.value = user.telegram_chat_id || '';
};

const saveUserChatId = () => {
    if (!selectedUserForEdit.value) return;
    isSavingUserChatId.value = true;
    router.post(route('admin.telegram_bot.update_user', selectedUserForEdit.value.id), {
        telegram_chat_id: editChatIdValue.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedUserForEdit.value = null;
        },
        onFinish: () => {
            isSavingUserChatId.value = false;
        }
    });
};

const quickSendToUser = (user) => {
    if (!user.telegram_chat_id) return;
    testMsgForm.value.chat_id = user.telegram_chat_id;
    testMsgForm.value.message = `Salom ${user.name}! Sher-Dor ERP tizimidan bildirishnoma.`;
    activeTab.value = 'config';
    window.scrollTo({ top: 400, behavior: 'smooth' });
};

// Password Visibility
const showFinanceToken = ref(false);
const showSecretKey = ref(false);
const showPinToken = ref(false);
const showBackupToken = ref(false);
</script>

<template>
    <AdminLayout>
        <Head title="Telegram Bot Boshqaruvi" />

        <div class="space-y-6">
            <!-- Top Hero Banner & Status Card -->
            <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden border border-slate-800">
                <div class="absolute -right-16 -top-16 w-80 h-80 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -left-16 -bottom-16 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="space-y-2">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="text-3xl">🤖</span>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Telegram Bot Boshqaruvi</h1>
                            
                            <!-- Status Badges -->
                            <span 
                                v-if="serviceStatus.status === 'active'"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 shadow-xs"
                            >
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                Faol (Running)
                            </span>
                            <span 
                                v-else-if="serviceStatus.status === 'inactive'"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-500/20 text-slate-400 border border-slate-500/30"
                            >
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                To'xtatilgan (Stopped)
                            </span>
                            <span 
                                v-else
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30"
                            >
                                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                                {{ serviceStatus.status }}
                            </span>

                            <span 
                                v-if="botInfo && botInfo.ok"
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-mono font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30"
                            >
                                <span>@{{ botInfo.result.username }}</span>
                            </span>
                        </div>
                        <p class="text-slate-400 text-sm max-w-2xl leading-relaxed">
                            Sher-Dor ERP Moliyachi va xizmat botlari holatini monitoring qilish, xizmatni qayta ishga tushirish, tokenlarni o'zgartirish va xodimlarni botga ulash markazi.
                        </p>
                    </div>

                    <!-- Service Control Buttons -->
                    <div class="flex items-center gap-2 flex-wrap shrink-0">
                        <button
                            @click="controlService('restart')"
                            :disabled="isControllingService"
                            class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white rounded-xl text-xs font-bold transition-all shadow-md flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                            title="Xizmatni qayta ishga tushirish"
                        >
                            <span>🔄</span>
                            <span>Qayta ishga tushirish</span>
                        </button>
                        <button
                            v-if="serviceStatus.status === 'active'"
                            @click="controlService('stop')"
                            :disabled="isControllingService"
                            class="px-4 py-2.5 bg-red-600/80 hover:bg-red-600 active:scale-95 text-white rounded-xl text-xs font-bold transition-all shadow-md flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                            title="Xizmatni to'xtatish"
                        >
                            <span>⏹️</span>
                            <span>To'xtatish</span>
                        </button>
                        <button
                            v-else
                            @click="controlService('start')"
                            :disabled="isControllingService"
                            class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white rounded-xl text-xs font-bold transition-all shadow-md flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                            title="Xizmatni ishga tushirish"
                        >
                            <span>▶️</span>
                            <span>Ishga tushirish</span>
                        </button>
                    </div>
                </div>

                <!-- Status Metadata Bar -->
                <div class="mt-6 pt-5 border-t border-slate-800/80 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-medium text-slate-300">
                    <div>
                        <span class="text-slate-500 block mb-0.5">Xizmat nomi:</span>
                        <span class="font-mono text-slate-200 font-bold">{{ serviceStatus.service_name }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block mb-0.5">Jarayon PID:</span>
                        <span class="font-mono text-slate-200 font-bold">{{ serviceStatus.pid || '—' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block mb-0.5">Xotira sarfi:</span>
                        <span class="font-mono text-slate-200 font-bold">{{ serviceStatus.memory || '—' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block mb-0.5">Uzluksiz ishlash (Uptime):</span>
                        <span class="font-mono text-slate-200 font-bold">{{ serviceStatus.uptime || '—' }}</span>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="flex items-center gap-2 border-b border-slate-200 overflow-x-auto pb-px">
                <button
                    @click="activeTab = 'config'"
                    :class="activeTab === 'config' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 hover:text-slate-800 font-bold'"
                    class="py-3 px-4 border-b-2 text-xs sm:text-sm flex items-center gap-2 whitespace-nowrap transition-colors cursor-pointer"
                >
                    <span>⚙️</span> Sozlamalar va Tokenlar
                </button>
                <button
                    @click="activeTab = 'users'"
                    :class="activeTab === 'users' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 hover:text-slate-800 font-bold'"
                    class="py-3 px-4 border-b-2 text-xs sm:text-sm flex items-center gap-2 whitespace-nowrap transition-colors cursor-pointer"
                >
                    <span>👥</span> Bot Foydalanuvchilari
                    <span class="px-2 py-0.5 text-[11px] font-mono rounded-full bg-slate-100 text-slate-600">
                        {{ users.filter(u => u.telegram_chat_id).length }}/{{ users.length }}
                    </span>
                </button>
                <button
                    @click="activeTab = 'transactions'"
                    :class="activeTab === 'transactions' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 hover:text-slate-800 font-bold'"
                    class="py-3 px-4 border-b-2 text-xs sm:text-sm flex items-center gap-2 whitespace-nowrap transition-colors cursor-pointer"
                >
                    <span>💸</span> Bot Tranzaksiyalari
                    <span class="px-2 py-0.5 text-[11px] font-mono rounded-full bg-slate-100 text-slate-600">{{ recentTransactions.length }}</span>
                </button>
                <button
                    @click="activeTab = 'leads'"
                    :class="activeTab === 'leads' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 hover:text-slate-800 font-bold'"
                    class="py-3 px-4 border-b-2 text-xs sm:text-sm flex items-center gap-2 whitespace-nowrap transition-colors cursor-pointer"
                >
                    <span>📥</span> Bot Lidlar
                    <span class="px-2 py-0.5 text-[11px] font-mono rounded-full bg-slate-100 text-slate-600">{{ recentLeads.length }}</span>
                </button>
                <button
                    @click="activeTab = 'logs'; refreshLogs();"
                    :class="activeTab === 'logs' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 hover:text-slate-800 font-bold'"
                    class="py-3 px-4 border-b-2 text-xs sm:text-sm flex items-center gap-2 whitespace-nowrap transition-colors cursor-pointer"
                >
                    <span>📜</span> Jonli Loglar
                </button>
            </div>

            <!-- ================= TAB 1: CONFIGURATION & TOKENS ================= -->
            <div v-show="activeTab === 'config'" class="space-y-6">
                <form @submit.prevent="saveConfig" class="space-y-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        
                        <!-- 1. Moliyachi Bot Tokeni -->
                        <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg font-bold">
                                        💼
                                    </div>
                                    <div>
                                        <h3 class="font-black text-slate-900 text-sm">Moliyachi Boti Tokeni</h3>
                                        <p class="text-slate-500 text-xs">Moliya, kassa va tasdiqlashlar uchun asosiy bot</p>
                                    </div>
                                </div>
                                <span class="text-[11px] font-mono text-slate-400 bg-slate-100 px-2 py-0.5 rounded">FINANCE_BOT_TOKEN</span>
                            </div>

                            <div class="space-y-2">
                                <div class="relative">
                                    <input
                                        :type="showFinanceToken ? 'text' : 'password'"
                                        v-model="configForm.finance_bot_token"
                                        placeholder="Masalan: 8768661709:AAEADA-..."
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all pr-20"
                                    />
                                    <div class="absolute right-2 top-2 flex items-center gap-1">
                                        <button
                                            type="button"
                                            @click="showFinanceToken = !showFinanceToken"
                                            class="p-1 text-slate-400 hover:text-slate-600 text-xs"
                                        >
                                            {{ showFinanceToken ? '🙈' : '👁️' }}
                                        </button>
                                        <button
                                            type="button"
                                            @click="testToken(configForm.finance_bot_token)"
                                            :disabled="isTestingToken || !configForm.finance_bot_token"
                                            class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold transition-all disabled:opacity-40"
                                        >
                                            {{ isTestingToken ? '⏳' : 'Tekshirish' }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Token test status -->
                                <div v-if="tokenTestResult" class="p-3 rounded-xl text-xs font-medium" :class="tokenTestResult.success ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200'">
                                    <div class="flex items-center gap-1.5">
                                        <span>{{ tokenTestResult.success ? '✅' : '❌' }}</span>
                                        <span>{{ tokenTestResult.message }}</span>
                                    </div>
                                    <div v-if="tokenTestResult.bot" class="mt-1 text-[11px] text-emerald-700 font-mono">
                                        ID: {{ tokenTestResult.bot.id }} • Nomi: {{ tokenTestResult.bot.first_name }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Bot Secret Key -->
                        <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg font-bold">
                                        🔑
                                    </div>
                                    <div>
                                        <h3 class="font-black text-slate-900 text-sm">Bot Secret Key</h3>
                                        <p class="text-slate-500 text-xs">Bot va Laravel API o'rtasidagi maxfiy kalit</p>
                                    </div>
                                </div>
                                <span class="text-[11px] font-mono text-slate-400 bg-slate-100 px-2 py-0.5 rounded">BOT_SECRET_KEY</span>
                            </div>

                            <div class="relative">
                                <input
                                    :type="showSecretKey ? 'text' : 'password'"
                                    v-model="configForm.bot_secret_key"
                                    placeholder="Maxfiy kalit..."
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-all pr-10"
                                />
                                <button
                                    type="button"
                                    @click="showSecretKey = !showSecretKey"
                                    class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 text-xs"
                                >
                                    {{ showSecretKey ? '🙈' : '👁️' }}
                                </button>
                            </div>
                        </div>

                        <!-- 3. Zaxira Boti Tokeni -->
                        <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg font-bold">
                                        💾
                                    </div>
                                    <div>
                                        <h3 class="font-black text-slate-900 text-sm">Zaxira (Backup) Boti Tokeni</h3>
                                        <p class="text-slate-500 text-xs">Ma'lumotlar bazasi zaxira nusxalarini yuborish boti</p>
                                    </div>
                                </div>
                                <span class="text-[11px] font-mono text-slate-400 bg-slate-100 px-2 py-0.5 rounded">BACKUP_BOT_TOKEN</span>
                            </div>

                            <div class="relative">
                                <input
                                    :type="showBackupToken ? 'text' : 'password'"
                                    v-model="configForm.backup_bot_token"
                                    placeholder="8516861678:..."
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all pr-20"
                                />
                                <div class="absolute right-2 top-2 flex items-center gap-1">
                                    <button
                                        type="button"
                                        @click="showBackupToken = !showBackupToken"
                                        class="p-1 text-slate-400 hover:text-slate-600 text-xs"
                                    >
                                        {{ showBackupToken ? '🙈' : '👁️' }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="testToken(configForm.backup_bot_token)"
                                        :disabled="isTestingToken || !configForm.backup_bot_token"
                                        class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold transition-all disabled:opacity-40"
                                    >
                                        Tekshirish
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- 4. PIN-kod / Xavfsizlik Boti -->
                        <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                                        🛡️
                                    </div>
                                    <div>
                                        <h3 class="font-black text-slate-900 text-sm">Xavfsizlik (PIN-kod) Boti</h3>
                                        <p class="text-slate-500 text-xs">Developer va administratorlarga OTP kod yuborish</p>
                                    </div>
                                </div>
                                <span class="text-[11px] font-mono text-slate-400 bg-slate-100 px-2 py-0.5 rounded">PIN_BOT_TOKEN</span>
                            </div>

                            <div class="relative">
                                <input
                                    :type="showPinToken ? 'text' : 'password'"
                                    v-model="configForm.pin_bot_token"
                                    placeholder="8738481163:..."
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all pr-20"
                                />
                                <div class="absolute right-2 top-2 flex items-center gap-1">
                                    <button
                                        type="button"
                                        @click="showPinToken = !showPinToken"
                                        class="p-1 text-slate-400 hover:text-slate-600 text-xs"
                                    >
                                        {{ showPinToken ? '🙈' : '👁️' }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="testToken(configForm.pin_bot_token)"
                                        :disabled="isTestingToken || !configForm.pin_bot_token"
                                        class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold transition-all disabled:opacity-40"
                                    >
                                        Tekshirish
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- 5. Lidlar Telegram Guruhi / Chat ID -->
                        <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4 lg:col-span-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center text-lg font-bold">
                                        📢
                                    </div>
                                    <div>
                                        <h3 class="font-black text-slate-900 text-sm">Lidlar Telegram Guruhi (Chat ID)</h3>
                                        <p class="text-slate-500 text-xs">Veb-sayt yoki botdan tushgan yangi lidlar darhol yuboriladigan Telegram guruh yoki kanal ID si</p>
                                    </div>
                                </div>
                                <span class="text-[11px] font-mono text-slate-400 bg-slate-100 px-2 py-0.5 rounded">LEAD_CHAT_ID</span>
                            </div>

                            <input
                                type="text"
                                v-model="configForm.telegram_lead_chat_id"
                                placeholder="Masalan: -1001234567890 yoki foydalanuvchi Chat ID si"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 transition-all"
                            />
                        </div>

                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="isSavingConfig"
                            class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 active:scale-95 text-white font-black text-xs sm:text-sm rounded-xl shadow-md shadow-blue-500/20 transition-all cursor-pointer flex items-center gap-2 disabled:opacity-50"
                        >
                            <span>{{ isSavingConfig ? '⏳' : '💾' }}</span>
                            <span>Sozlamalarni Saqlash</span>
                        </button>
                    </div>
                </form>

                <!-- Test Message Sender Card -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                        <span class="text-xl">✉️</span>
                        <div>
                            <h3 class="font-black text-slate-900 text-sm">Telegramga Sinov Xabari Yuborish</h3>
                            <p class="text-slate-500 text-xs">Bot ishlayotganini tekshirish uchun belgilangan Chat ID ga xabar yuborib ko'ring</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Qaysi botdan?</label>
                            <select
                                v-model="testMsgForm.bot_type"
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            >
                                <option value="finance">Moliyachi Boti (Finance)</option>
                                <option value="pin">Xavfsizlik Boti (PIN)</option>
                                <option value="backup">Zaxira Boti (Backup)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Telegram Chat ID</label>
                            <input
                                type="text"
                                v-model="testMsgForm.chat_id"
                                placeholder="Chat ID (masalan: 123456789)"
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            />
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-xs font-bold text-slate-700 mb-1">Xabar matni</label>
                            <div class="flex gap-2">
                                <input
                                    type="text"
                                    v-model="testMsgForm.message"
                                    class="flex-1 px-3.5 py-2 rounded-xl border border-slate-200 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                />
                                <button
                                    type="button"
                                    @click="sendTestMessage"
                                    :disabled="isSendingMsg || !testMsgForm.chat_id || !testMsgForm.message"
                                    class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-all disabled:opacity-40 flex items-center gap-1.5 cursor-pointer shrink-0"
                                >
                                    <span>{{ isSendingMsg ? '⏳' : '🚀' }}</span>
                                    <span>Yuborish</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="testMsgResult" class="p-3 rounded-xl text-xs font-medium" :class="testMsgResult.success ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200'">
                        <span>{{ testMsgResult.success ? '✅' : '❌' }}</span>
                        <span>{{ testMsgResult.message }}</span>
                    </div>
                </div>
            </div>

            <!-- ================= TAB 2: BOT USERS ================= -->
            <div v-show="activeTab === 'users'" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="font-black text-slate-900 text-sm">Tizim Foydalanuvchilari va Telegram Chat ID lari</h3>
                        <p class="text-slate-500 text-xs">Bot orqali tranzaksiya kiritish va tasdiqlash uchun xodimning Chat ID si bog'langan bo'lishi kerak</p>
                    </div>
                    <div class="w-full sm:w-64">
                        <input
                            type="text"
                            v-model="userSearch"
                            placeholder="Qidirish (ism, telefon, chat id)..."
                            class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50/70 border-b border-slate-100 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="py-3 px-4">Xodim / Foydalanuvchi</th>
                                <th class="py-3 px-4">Roli</th>
                                <th class="py-3 px-4">Telefon</th>
                                <th class="py-3 px-4">Telegram Chat ID</th>
                                <th class="py-3 px-4 text-right">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="user in filteredUsers" :key="'user-' + user.id" class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4 font-bold text-slate-900">
                                    {{ user.name }}
                                </td>
                                <td class="py-3 px-4">
                                    <span v-for="r in user.roles" :key="r" class="inline-block px-2 py-0.5 mr-1 rounded bg-blue-50 text-blue-700 font-semibold text-[10px]">
                                        {{ r }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-600 font-mono">
                                    {{ user.phone || '—' }}
                                </td>
                                <td class="py-3 px-4">
                                    <span v-if="user.telegram_chat_id" class="inline-flex items-center gap-1 font-mono font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">
                                        <span>💬</span>
                                        <span>{{ user.telegram_chat_id }}</span>
                                    </span>
                                    <span v-else class="text-slate-400 italic">
                                        Ulanmagan
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right space-x-1">
                                    <button
                                        v-if="user.telegram_chat_id"
                                        @click="quickSendToUser(user)"
                                        class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-[11px] transition-all cursor-pointer"
                                        title="Ushbu foydalanuvchiga sinov xabari yozish"
                                    >
                                        ✉️ Xabar
                                    </button>
                                    <button
                                        @click="openEditUserModal(user)"
                                        class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg font-bold text-[11px] transition-all cursor-pointer"
                                    >
                                        ✏️ Chat ID
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredUsers.length === 0">
                                <td colspan="5" class="py-8 text-center text-slate-400">
                                    Foydalanuvchilar topilmadi
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ================= TAB 3: BOT TRANSACTIONS ================= -->
            <div v-show="activeTab === 'transactions'" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100">
                    <h3 class="font-black text-slate-900 text-sm">Bot Orqali Kiritilgan Oxirgi Tranzaksiyalar</h3>
                    <p class="text-slate-500 text-xs">Xodimlar tomonidan Telegram bot orqali jo'natilgan operatsiyalar</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50/70 border-b border-slate-100 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="py-3 px-4">ID</th>
                                <th class="py-3 px-4">Xodim</th>
                                <th class="py-3 px-4">Kassa</th>
                                <th class="py-3 px-4">Kategoriya</th>
                                <th class="py-3 px-4">Turi</th>
                                <th class="py-3 px-4">Summa</th>
                                <th class="py-3 px-4">Sana</th>
                                <th class="py-3 px-4">Holat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="t in recentTransactions" :key="'tr-' + t.id" class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4 font-mono font-bold text-slate-500">#{{ t.id }}</td>
                                <td class="py-3 px-4 font-bold text-slate-900">{{ t.creator?.name || t.user?.name || 'Noma\'lum' }}</td>
                                <td class="py-3 px-4 text-slate-700">{{ t.cash_register?.name || '—' }}</td>
                                <td class="py-3 px-4 text-slate-700">{{ t.category?.name || '—' }}</td>
                                <td class="py-3 px-4">
                                    <span :class="t.type === 'income' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase">
                                        {{ t.type === 'income' ? 'Kirim' : 'Chiqim' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                    {{ Number(t.amount || 0).toLocaleString() }}
                                </td>
                                <td class="py-3 px-4 text-slate-500 font-mono text-[11px]">{{ t.created_at ? t.created_at.substring(0, 16) : '—' }}</td>
                                <td class="py-3 px-4">
                                    <span v-if="t.status === 'approved'" class="text-emerald-600 font-bold">✓ Tasdiqlangan</span>
                                    <span v-else-if="t.status === 'pending'" class="text-amber-600 font-bold">⏳ Kutilmoqda</span>
                                    <span v-else-if="t.status === 'rejected'" class="text-red-600 font-bold">✕ Bekor</span>
                                    <span v-else class="text-slate-500">{{ t.status }}</span>
                                </td>
                            </tr>
                            <tr v-if="recentTransactions.length === 0">
                                <td colspan="8" class="py-8 text-center text-slate-400">
                                    Bot orqali hozircha tranzaksiyalar kiritilmagan
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ================= TAB 4: BOT LEADS ================= -->
            <div v-show="activeTab === 'leads'" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100">
                    <h3 class="font-black text-slate-900 text-sm">Bot Orqali Kelib Tushgan Lidlar</h3>
                    <p class="text-slate-500 text-xs">Mijozlar tomonidan Telegram orqali qoldirilgan murojaatlar</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50/70 border-b border-slate-100 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="py-3 px-4">ID</th>
                                <th class="py-3 px-4">Mijoz Ismi</th>
                                <th class="py-3 px-4">Telefon</th>
                                <th class="py-3 px-4">Holat</th>
                                <th class="py-3 px-4">Sana</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="l in recentLeads" :key="'lead-' + l.id" class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4 font-mono font-bold text-slate-500">#{{ l.id }}</td>
                                <td class="py-3 px-4 font-bold text-slate-900">{{ l.full_name || l.name || 'Noma\'lum' }}</td>
                                <td class="py-3 px-4 font-mono text-slate-700">{{ l.phone || '—' }}</td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 uppercase">
                                        {{ l.status || 'Yangi' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-500 font-mono text-[11px]">{{ l.created_at ? l.created_at.substring(0, 16) : '—' }}</td>
                            </tr>
                            <tr v-if="recentLeads.length === 0">
                                <td colspan="5" class="py-8 text-center text-slate-400">
                                    Bot orqali kelgan lidlar mavjud emas
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ================= TAB 5: LIVE LOGS ================= -->
            <div v-show="activeTab === 'logs'" class="bg-slate-950 rounded-2xl border border-slate-800 shadow-xl p-5 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="flex gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-red-500/80 inline-block"></span>
                            <span class="w-3 h-3 rounded-full bg-amber-500/80 inline-block"></span>
                            <span class="w-3 h-3 rounded-full bg-emerald-500/80 inline-block"></span>
                        </div>
                        <span class="text-xs font-mono font-bold text-slate-400 ml-2">journalctl -u moliyabot.service</span>
                    </div>
                    <button
                        type="button"
                        @click="refreshLogs"
                        :disabled="isLoadingLogs"
                        class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
                    >
                        <span>{{ isLoadingLogs ? '⏳' : '🔄' }}</span>
                        <span>Loglarni yangilash</span>
                    </button>
                </div>

                <pre class="w-full text-[11px] font-mono leading-relaxed text-emerald-400 overflow-x-auto max-h-[550px] p-2 select-all whitespace-pre-wrap">{{ currentLogs }}</pre>
            </div>
        </div>

        <!-- Edit User Chat ID Modal -->
        <div v-if="selectedUserForEdit" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-md p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="font-black text-slate-900 text-sm">Telegram Chat ID ni sozlash</h3>
                    <button @click="selectedUserForEdit = null" class="text-slate-400 hover:text-slate-600 text-lg leading-none cursor-pointer">&times;</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <span class="text-xs text-slate-500">Xodim:</span>
                        <p class="font-bold text-slate-900 text-sm">{{ selectedUserForEdit.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Telegram Chat ID</label>
                        <input
                            type="text"
                            v-model="editChatIdValue"
                            placeholder="Masalan: 512345678"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                        />
                        <p class="text-[11px] text-slate-500 mt-1">
                            Xodim o'z Telegramidan botga <code class="font-bold text-blue-600">/start</code> yozganda bot uning Chat ID sini ko'rsatadi.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="selectedUserForEdit = null"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-all cursor-pointer"
                    >
                        Bekor qilish
                    </button>
                    <button
                        type="button"
                        @click="saveUserChatId"
                        :disabled="isSavingUserChatId"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition-all cursor-pointer disabled:opacity-50"
                    >
                        {{ isSavingUserChatId ? 'Saqlanmoqda...' : 'Saqlash' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
