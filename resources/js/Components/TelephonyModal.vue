<template>
    <Modal :show="show" @close="closeModal" maxWidth="4xl">
        <div class="glass-panel p-6 border border-neon-purple/50 bg-[#020205] relative overflow-hidden">
            <!-- Background Glow -->
            <div class="absolute -top-40 -right-40 w-96 h-96 bg-neon-purple/20 blur-[100px] pointer-events-none"></div>
            
            <!-- Header -->
            <div class="flex justify-between items-center mb-6 relative z-10 border-b border-white/10 pb-4">
                <div>
                    <h2 class="text-3xl font-display font-black text-white uppercase tracking-wider">
                        📞 IP Telefoniya <span class="text-neon-purple">Markazi</span>
                    </h2>
                    <p class="text-xs text-text-muted font-mono mt-1">Avtomatik qo'ng'iroqlar, onlayn qo'ng'iroq va monitoring</p>
                </div>
                <button @click="closeModal" class="text-text-muted hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Dashboard Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 relative z-10">
                
                <!-- Section A: Click-to-Call Dialer -->
                <div class="bg-black/60 border border-white/10 rounded-xl p-5 flex flex-col items-center justify-center space-y-4 group hover:border-neon-purple/30 transition-all">
                    <div class="w-16 h-16 bg-neon-purple/10 rounded-full flex items-center justify-center text-3xl shadow-[0_0_15px_rgba(157,0,255,0.2)] mb-2">
                        📱
                    </div>
                    <h3 class="text-sm font-display text-white uppercase">Tezkor Qo'ng'iroq</h3>
                    
                    <div class="w-full relative">
                        <input v-model="dialerPhone" type="text" placeholder="998901234567" class="w-full bg-black border border-white/20 rounded-lg px-4 py-3 text-center text-xl font-mono text-white tracking-widest focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-all">
                    </div>

                    <button @click="makeCall" :disabled="isCalling" class="w-full py-3 bg-neon-green/20 hover:bg-neon-green/40 text-neon-green font-mono font-bold rounded-lg border border-neon-green/50 transition-all uppercase tracking-wider text-xs flex justify-center items-center gap-2">
                        <span v-if="isCalling" class="animate-pulse">Qo'ng'iroq qilinmoqda...</span>
                        <span v-else>📞 Qo'ng'iroq qilish</span>
                    </button>
                </div>

                <!-- Section B: Auto-Operator -->
                <div class="bg-black/60 border border-white/10 rounded-xl p-5 flex flex-col items-center justify-center space-y-4 group hover:border-neon-cyan/30 transition-all">
                    <div class="w-16 h-16 bg-neon-cyan/10 rounded-full flex items-center justify-center text-3xl shadow-[0_0_15px_rgba(0,240,255,0.2)] mb-2">
                        🤖
                    </div>
                    <h3 class="text-sm font-display text-white uppercase">Avto-Operator (Robot)</h3>
                    
                    <div class="w-full space-y-3 text-left">
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1">Auditoriya</label>
                            <select v-model="autoGroup" class="w-full bg-black border border-white/20 rounded px-3 py-2 text-sm font-mono text-white focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-all">
                                <option value="debtors">Qarzdorlar (Muddati o'tgan)</option>
                                <option value="new_leads">Yangi Mijozlar</option>
                                <option value="all">Barcha Mijozlar</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono text-text-muted mb-1">Ovozli Shablon</label>
                            <select class="w-full bg-black border border-white/20 rounded px-3 py-2 text-sm font-mono text-white focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan transition-all">
                                <option value="1">Qarz haqida eslatma (.mp3)</option>
                                <option value="2">Yangi yil aksiyasi (.mp3)</option>
                            </select>
                        </div>
                    </div>

                    <button @click="startAutoDialer" :disabled="isAutoDialing" class="w-full py-3 bg-neon-cyan/20 hover:bg-neon-cyan/40 text-neon-cyan font-mono font-bold rounded-lg border border-neon-cyan/50 transition-all uppercase tracking-wider text-xs flex justify-center items-center gap-2">
                        <span v-if="isAutoDialing" class="animate-pulse">Robot ishlamoqda...</span>
                        <span v-else>🚀 Avto-qo'ng'iroqni boshlash</span>
                    </button>
                </div>

                <!-- Section C: Call Logs -->
                <div class="bg-black/60 border border-white/10 rounded-xl p-0 flex flex-col group hover:border-white/30 transition-all h-80 lg:h-auto">
                    <div class="p-3 border-b border-white/10 bg-black/40 flex justify-between items-center rounded-t-xl">
                        <h3 class="text-sm font-display text-white uppercase">📊 Qo'ng'iroqlar Jurnali</h3>
                        <button @click="fetchLogs" class="text-text-muted hover:text-white text-[10px]">🔄 Yangilash</button>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-2">
                        <div v-if="loading" class="text-center text-text-muted text-xs py-10 font-mono animate-pulse">
                            Yuklanmoqda...
                        </div>
                        <div v-else-if="logs.length === 0" class="text-center text-text-muted text-xs py-10 font-mono">
                            Hozircha qo'ng'iroqlar yo'q
                        </div>
                        
                        <div v-else v-for="log in logs" :key="log.id" class="p-2 border border-white/5 rounded bg-white/5 flex items-center justify-between hover:bg-white/10 transition-colors">
                            <div class="flex items-center gap-3">
                                <div :class="{
                                    'text-neon-green': log.status === 'answered',
                                    'text-neon-magenta': log.status === 'missed' || log.status === 'failed',
                                    'text-neon-yellow': log.status === 'pending'
                                }" class="text-lg">
                                    <span v-if="log.type === 'inbound'">📥</span>
                                    <span v-else-if="log.type === 'outbound'">📤</span>
                                    <span v-else>🤖</span>
                                </div>
                                <div>
                                    <div class="text-xs font-mono text-white font-bold">{{ log.phone_number }}</div>
                                    <div class="text-[9px] text-text-muted font-mono">{{ new Date(log.created_at).toLocaleString('uz-UZ') }} • {{ log.duration_seconds }} sek</div>
                                </div>
                            </div>
                            
                            <button v-if="log.status === 'answered' && log.duration_seconds > 0" class="w-6 h-6 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/30 text-white transition-colors" title="Eshitish">
                                <span class="text-[10px]">▶️</span>
                            </button>
                            <span v-else class="text-[9px] font-mono text-text-muted uppercase font-bold">{{ log.status }}</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import axios from 'axios';

const props = defineProps({
    show: Boolean
});

const emit = defineEmits(['update:show']);

function closeModal() {
    emit('update:show', false);
}

const dialerPhone = ref('');
const isCalling = ref(false);

const autoGroup = ref('debtors');
const isAutoDialing = ref(false);

const logs = ref([]);
const loading = ref(false);

async function fetchLogs() {
    loading.value = true;
    try {
        const response = await axios.get(route('telephony.logs'));
        logs.value = response.data;
    } catch (error) {
        console.error("Failed to fetch telephony logs:", error);
    } finally {
        loading.value = false;
    }
}

watch(() => props.show, (newVal) => {
    if (newVal) {
        fetchLogs();
    }
});

function makeCall() {
    if (!dialerPhone.value) return;
    isCalling.value = true;
    // Mock call action
    setTimeout(() => {
        isCalling.value = false;
        alert("Qo'ng'iroq yakunlandi (Demo)");
        fetchLogs(); // Reload logs to show new
    }, 2000);
}

function startAutoDialer() {
    isAutoDialing.value = true;
    // Mock action
    setTimeout(() => {
        isAutoDialing.value = false;
        alert("Avto-qo'ng'iroq jarayoni boshlandi (Demo)");
        fetchLogs();
    }, 1500);
}
</script>
