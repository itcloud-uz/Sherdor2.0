<template>
    <AdminLayout>
        <Head title="IP Telefoniya" />
        
        <div class="p-6 h-screen flex flex-col bg-[#020508] overflow-y-auto custom-scrollbar">
            
            <div class="relative bg-transparent overflow-hidden rounded-2xl group flex-1">
                <!-- Sci-Fi Corner Borders -->
                <div class="absolute top-0 left-0 w-16 h-16 border-t-2 border-l-2 border-neon-cyan/80 z-20 pointer-events-none opacity-70"></div>
                <div class="absolute top-0 right-0 w-16 h-16 border-t-2 border-r-2 border-neon-cyan/80 z-20 pointer-events-none opacity-70"></div>
                <div class="absolute bottom-0 left-0 w-16 h-16 border-b-2 border-l-2 border-neon-cyan/80 z-20 pointer-events-none opacity-70"></div>
                <div class="absolute bottom-0 right-0 w-16 h-16 border-b-2 border-r-2 border-neon-cyan/80 z-20 pointer-events-none opacity-70"></div>
                
                <!-- Holographic background -->
                <div class="absolute inset-0 bg-[#020508]/90 z-0">
                    <!-- Grid pattern -->
                    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCI+CjxwYXRoIGQ9Ik00MCAwaC00MHY0MGg0MHoiIGZpbGw9Im5vbmUiIC8+Cg08cGF0aCBkPSJNMCAwaDQwdjQwSDB6IiBmaWxsPSJub25lIiBzdHJva2U9InJnYmEoMCwyNTUsMjU1LDAuMDMpIiBzdHJva2Utd2lkdGg9IjEiIC8+Cjwvc3ZnPg==')] opacity-50"></div>
                    <!-- Scanning line animation -->
                    <div class="absolute top-0 left-0 w-full h-1 bg-neon-cyan/30 shadow-[0_0_20px_#00f0ff] z-10 animate-[scan_3s_ease-in-out_infinite]"></div>
                </div>

                <div class="relative z-10 p-1 h-full flex flex-col">
                    <!-- Header / Top Bar -->
                    <div class="flex justify-between items-center px-6 py-4 border-b border-neon-cyan/20 bg-black/40">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-neon-cyan/10 border border-neon-cyan/50 rounded-full flex items-center justify-center animate-pulse shadow-[0_0_15px_rgba(0,240,255,0.3)]">
                                <svg class="w-5 h-5 text-neon-cyan" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-2xl font-display font-black text-white uppercase tracking-[0.2em] drop-shadow-[0_0_8px_rgba(0,240,255,0.5)]">
                                    IP <span class="text-neon-cyan">TELEFONIYA</span>
                                </h2>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="w-2 h-2 rounded-full bg-neon-green animate-ping"></span>
                                    <p class="text-xs text-neon-cyan font-mono tracking-widest">XAVFSIZ ALOQA O'RNATILDI // SYS.VER 9.0.2</p>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- Main Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-1 p-1 bg-black/20">
                    
                    <!-- LEFT COLUMN: The Phone Dialer (Pragmata Style) -->
                    <div class="col-span-1 lg:col-span-4 bg-black/60 border border-white/5 p-6 relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-neon-cyan/5 to-transparent opacity-20"></div>
                        
                        <div class="text-center mb-6 relative z-10">
                            <h3 class="text-sm font-mono text-neon-cyan uppercase tracking-widest mb-1 border-b border-neon-cyan/30 inline-block pb-1">RAQAM TERISH TERMINALI</h3>
                        </div>

                        <!-- Screen / Number Display -->
                        <div class="bg-[#050B14] border border-neon-cyan/40 rounded-lg p-4 mb-6 shadow-[inset_0_0_20px_rgba(0,240,255,0.1)] relative group">
                            <div class="absolute top-1 left-2 text-xs text-neon-cyan/50 font-mono">MIJOZ RAQAMI</div>
                            <div class="absolute top-1 right-2 text-xs text-neon-cyan/50 font-mono">TAYYOR</div>
                            
                            <input v-model="dialerPhone" type="text" placeholder="NUMBER..." class="w-full bg-transparent border-none text-center text-3xl font-mono text-white tracking-[0.2em] focus:ring-0 px-0 pt-4 pb-2" style="text-shadow: 0 0 10px rgba(255,255,255,0.5);">
                            
                            <!-- Blinking cursor effect fake -->
                            <div class="h-[1px] w-full bg-gradient-to-r from-transparent via-neon-cyan/50 to-transparent mt-2"></div>
                        </div>

                        <!-- Numeric Keypad -->
                        <div class="grid grid-cols-3 gap-3 mb-6 relative z-10">
                            <button v-for="num in ['1','2','3','4','5','6','7','8','9','*','0','#']" :key="num" 
                                @click="dialerPhone += num"
                                class="h-14 bg-white/5 hover:bg-neon-cyan/20 border border-white/10 hover:border-neon-cyan/50 rounded-lg text-xl font-mono text-white/80 hover:text-white hover:shadow-[0_0_15px_rgba(0,240,255,0.4)] transition-all flex flex-col items-center justify-center group/key">
                                <span>{{ num }}</span>
                                <span v-if="num === '1'" class="text-[8px] text-neon-cyan/40 opacity-0 group-hover/key:opacity-100">SYS</span>
                                <span v-if="num === '2'" class="text-[8px] text-neon-cyan/40 opacity-0 group-hover/key:opacity-100">ABC</span>
                                <span v-if="num === '3'" class="text-[8px] text-neon-cyan/40 opacity-0 group-hover/key:opacity-100">DEF</span>
                                <span v-if="num === '4'" class="text-[8px] text-neon-cyan/40 opacity-0 group-hover/key:opacity-100">GHI</span>
                                <span v-if="num === '5'" class="text-[8px] text-neon-cyan/40 opacity-0 group-hover/key:opacity-100">JKL</span>
                                <span v-if="num === '6'" class="text-[8px] text-neon-cyan/40 opacity-0 group-hover/key:opacity-100">MNO</span>
                                <span v-if="num === '7'" class="text-[8px] text-neon-cyan/40 opacity-0 group-hover/key:opacity-100">PQRS</span>
                                <span v-if="num === '8'" class="text-[8px] text-neon-cyan/40 opacity-0 group-hover/key:opacity-100">TUV</span>
                                <span v-if="num === '9'" class="text-[8px] text-neon-cyan/40 opacity-0 group-hover/key:opacity-100">WXYZ</span>
                                <span v-if="num === '0'" class="text-[8px] text-neon-cyan/40 opacity-0 group-hover/key:opacity-100">+</span>
                            </button>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-3 relative z-10">
                            <button @click="dialerPhone = dialerPhone.slice(0, -1)" class="w-16 h-14 bg-red-500/10 hover:bg-red-500/30 border border-red-500/30 rounded-lg text-red-500 flex items-center justify-center transition-all">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z" /></svg>
                            </button>
                            <button @click="makeCall" :disabled="isCalling || !dialerPhone" class="flex-1 h-14 bg-neon-cyan/20 hover:bg-neon-cyan/40 disabled:bg-white/5 disabled:border-white/10 text-neon-cyan disabled:text-text-muted font-display font-bold rounded-lg border border-neon-cyan/50 disabled:border-transparent transition-all uppercase tracking-wider text-sm flex justify-center items-center gap-2 shadow-[0_0_15px_rgba(0,240,255,0.2)]">
                                <span v-if="isCalling" class="animate-pulse">ULANMOQDA...</span>
                                <span v-else>QO'NG'IROQ QILISH</span>
                            </button>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Auto Dialer & Logs -->
                    <div class="col-span-1 lg:col-span-8 flex flex-col gap-1">
                        
                        <!-- Auto-Operator Hologram Panel -->
                        <div class="bg-black/60 border border-white/5 p-6 relative overflow-hidden">
                            <div class="absolute right-0 top-0 w-64 h-64 bg-neon-purple/5 blur-2xl pointer-events-none rounded-full"></div>
                            
                            <h3 class="text-sm font-mono text-neon-purple uppercase tracking-widest mb-4 flex items-center gap-2">
                                <span class="w-1.5 h-1.5 bg-neon-purple block"></span>
                                AVTO-ROBOT MODULI v1.0
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative z-10">
                                <div>
                                    <div class="mb-4">
                                        <label class="block text-xs font-mono text-neon-purple/70 mb-1 tracking-widest uppercase">MAQSADLI GURUHNI TANLANG</label>
                                        <div class="relative">
                                            <select v-model="autoGroup" class="w-full bg-[#050B14] border border-neon-purple/30 rounded text-sm font-mono text-white focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-all appearance-none pr-8">
                                                <option value="debtors">Qarzdorlar (Muddati o'tgan)</option>
                                                <option value="new_leads">Yangi Mijozlar</option>
                                                <option value="all">Barcha Mijozlar</option>
                                            </select>
                                            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-neon-purple/50 pointer-events-none font-mono text-xs">▼</div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between items-end mb-1">
                                            <label class="block text-xs font-mono text-neon-purple/70 tracking-widest uppercase">AUDIO FAYLNI TANLANG</label>
                                            <label class="cursor-pointer text-[10px] text-neon-cyan hover:text-white transition-colors bg-white/5 px-2 py-0.5 rounded border border-white/10" :class="{'opacity-50 pointer-events-none': isUploading}">
                                                <span v-if="isUploading">Yuklanmoqda...</span>
                                                <span v-else>+ Yuklash</span>
                                                <input type="file" class="hidden" accept=".mp3,.wav" @change="handleFileUpload" :disabled="isUploading">
                                            </label>
                                        </div>
                                        <div class="relative">
                                            <select v-model="selectedAudio" class="w-full bg-[#050B14] border border-neon-purple/30 rounded text-sm font-mono text-white focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-all appearance-none pr-8">
                                                <option value="">-- Audio faylni tanlang --</option>
                                                <option v-for="audio in availableAudios" :key="audio.id" :value="audio.id">{{ audio.name }}</option>
                                            </select>
                                            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-neon-purple/50 pointer-events-none font-mono text-xs">▼</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col justify-end">
                                    <button @click="startAutoDialer" :disabled="isAutoDialing" class="w-full py-4 bg-neon-purple/20 hover:bg-neon-purple/40 text-neon-purple font-mono font-bold rounded border border-neon-purple/50 transition-all uppercase tracking-[0.2em] text-xs relative overflow-hidden group">
                                        <div class="absolute inset-0 bg-neon-purple/20 translate-y-full group-hover:translate-y-0 transition-transform"></div>
                                        <span class="relative z-10 flex items-center justify-center gap-2">
                                            <span v-if="isAutoDialing" class="animate-pulse">JARAYON BAJARILMOQDA...</span>
                                            <span v-else>▶ OMMAVIY QO'NG'IROQNI BOSHLASH</span>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Call Logs Panel -->
                        <div class="bg-black/60 border border-white/5 p-0 flex-1 relative overflow-hidden flex flex-col h-80 lg:h-auto">
                            <div class="p-4 border-b border-white/10 bg-[#050B14]/80 flex justify-between items-center z-10">
                                <h3 class="text-sm font-mono text-white/70 uppercase tracking-widest flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 bg-white/50 block"></span>
                                    QO'NG'IROQLAR TARIXI
                                </h3>
                                <button @click="fetchLogs" class="text-neon-cyan/70 hover:text-neon-cyan font-mono text-xs uppercase tracking-wider border border-neon-cyan/30 px-3 py-1.5 rounded">
                                    YANGILASH
                                </button>
                            </div>
                            
                            <div class="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-2 z-10">
                                <div v-if="loading" class="text-center text-neon-cyan/50 text-xs py-10 font-mono animate-pulse uppercase tracking-[0.2em]">
                                    Ma'lumotlar olinmoqda...
                                </div>
                                <div v-else-if="logs.length === 0" class="text-center text-white/30 text-xs py-10 font-mono uppercase tracking-[0.2em]">
                                    BAZADA MA'LUMOT TOPILMADI
                                </div>
                                
                                <div v-else v-for="log in logs" :key="log.id" class="p-3 border-l-2 border-r border-y border-white/5 rounded bg-black/40 flex items-center justify-between hover:bg-white/5 hover:border-l-neon-cyan transition-colors group">
                                    <div class="flex items-center gap-4">
                                        <div :class="{
                                            'text-neon-green bg-neon-green/10 border-neon-green/30': log.status === 'answered',
                                            'text-red-500 bg-red-500/10 border-red-500/30': log.status === 'missed' || log.status === 'failed',
                                            'text-neon-yellow bg-neon-yellow/10 border-neon-yellow/30': log.status === 'pending'
                                        }" class="w-8 h-8 rounded border flex items-center justify-center text-sm shadow-inner">
                                            <span v-if="log.type === 'inbound'">🡗</span>
                                            <span v-else-if="log.type === 'outbound'">🡕</span>
                                            <span v-else>🤖</span>
                                        </div>
                                        <div>
                                            <div class="text-sm font-mono text-white/90 tracking-wider">{{ log.phone_number }}</div>
                                            <div class="text-[9px] text-white/40 font-mono tracking-widest uppercase mt-0.5">
                                                ID: {{ log.id.toString().padStart(5, '0') }} | {{ new Date(log.created_at).toLocaleTimeString('en-US', {hour12: false}) }}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-4">
                                        <div class="text-right hidden sm:block">
                                            <div class="text-xs font-mono text-white/60 tracking-widest uppercase">{{ log.duration_seconds }} SONIYA</div>
                                            <div :class="{
                                                'text-neon-green': log.status === 'answered',
                                                'text-red-500': log.status === 'missed' || log.status === 'failed',
                                                'text-neon-yellow': log.status === 'pending'
                                            }" class="text-[10px] font-mono tracking-widest uppercase font-bold mt-0.5">
                                                HOLAT: {{ log.status }}
                                            </div>
                                        </div>
                                        <button v-if="log.status === 'answered' && log.duration_seconds > 0" class="w-8 h-8 rounded border border-neon-cyan/40 bg-neon-cyan/10 flex items-center justify-center hover:bg-neon-cyan/30 hover:border-neon-cyan text-neon-cyan transition-all" title="Eshitish">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                
                <!-- Footer Tech Bar -->
                <div class="px-6 py-2 bg-black/40 border-t border-neon-cyan/20 flex justify-between items-center text-[8px] font-mono text-neon-cyan/50 tracking-[0.3em] uppercase">
                    <div>ENCRYPTION: AES-256-GCM</div>
                    <div>DATALINK: STABLE</div>
                    <div>SHER-DOR NETWORK</div>
                </div>
            </div>
        </div>
    </div>
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';

const dialerPhone = ref('');
const isCalling = ref(false);

const autoGroup = ref('debtors');
const isAutoDialing = ref(false);

const selectedAudio = ref('');
const availableAudios = ref([]);
const isUploading = ref(false);

const logs = ref([]);
const loading = ref(false);

async function fetchAudios() {
    try {
        const response = await axios.get(route('admin.telephony.audios.get'));
        availableAudios.value = response.data;
    } catch (error) {
        console.error("Failed to fetch audios:", error);
    }
}

async function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    if (file.size > 10 * 1024 * 1024) {
        alert("Fayl hajmi 10MB dan oshmasligi kerak!");
        return;
    }

    isUploading.value = true;
    const formData = new FormData();
    formData.append('audio', file);
    formData.append('name', file.name);

    try {
        await axios.post(route('admin.telephony.audios.upload'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        alert('Audio muvaffaqiyatli yuklandi!');
        await fetchAudios();
    } catch (error) {
        console.error('Audio yuklashda xatolik:', error);
        alert('Audio yuklashda xatolik yuz berdi.');
    } finally {
        isUploading.value = false;
        event.target.value = '';
    }
}

async function fetchLogs() {
    loading.value = true;
    try {
        const response = await axios.get(route('admin.telephony.logs'));
        logs.value = response.data;
    } catch (error) {
        console.error("Failed to fetch telephony logs:", error);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    fetchLogs();
    fetchAudios();
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
