<template>
    <AdminLayout>
        <Head title="IP Telefoniya" />
        
        <div class="p-6 h-screen flex flex-col bg-transparent overflow-y-auto custom-scrollbar">
            
            <div class="relative bg-transparent overflow-hidden rounded-2xl group flex-1">
                <!-- Sci-Fi Corner Borders -->
                <div class="absolute top-0 left-0 w-16 h-16 border-t-2 border-l-2 border-blue-200 z-20 pointer-events-none opacity-70"></div>
                <div class="absolute top-0 right-0 w-16 h-16 border-t-2 border-r-2 border-blue-200 z-20 pointer-events-none opacity-70"></div>
                <div class="absolute bottom-0 left-0 w-16 h-16 border-b-2 border-l-2 border-blue-200 z-20 pointer-events-none opacity-70"></div>
                <div class="absolute bottom-0 right-0 w-16 h-16 border-b-2 border-r-2 border-blue-200 z-20 pointer-events-none opacity-70"></div>
                

                <div class="relative z-10 p-1 h-full flex flex-col">
                    <!-- Header / Top Bar -->
                    <div class="flex justify-between items-center px-6 py-4 border-b border-blue-200 bg-gray-900/50 hidden">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-blue-50 border border-blue-200 rounded-full flex items-center justify-center animate-pulse shadow-sm">
                                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-2xl font-display font-black text-gray-900 uppercase tracking-[0.2em] drop-shadow-sm">
                                    IP <span class="text-blue-600">TELEFONIYA</span>
                                </h2>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="w-2 h-2 rounded-full bg-emerald-600 animate-ping"></span>
                                    <p class="text-xs text-blue-600 font-mono tracking-widest">XAVFSIZ ALOQA O'RNATILDI // SYS.VER 9.0.2</p>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- Main Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-1 p-1 bg-gray-900/50">
                    
                    <!-- LEFT COLUMN: The Phone Dialer (Pragmata Style) -->
                    <div class="col-span-1 lg:col-span-4 bg-gray-900/50 border border-gray-100 p-6 relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-neon-cyan/5 to-transparent opacity-20"></div>
                        
                        <div class="text-center mb-6 relative z-10">
                            <h3 class="text-sm font-mono text-blue-600 uppercase tracking-widest mb-1 border-b border-blue-200 inline-block pb-1">RAQAM TERISH TERMINALI</h3>
                        </div>

                        <!-- Screen / Number Display -->
                        <div class="bg-[#050B14] border border-blue-200 rounded-lg p-4 mb-6 shadow-[inset_0_0_20px_rgba(0,240,255,0.1)] relative group">
                            <div class="absolute top-1 left-2 text-xs text-blue-600/50 font-mono">MIJOZ RAQAMI</div>
                            <div class="absolute top-1 right-2 text-xs text-blue-600/50 font-mono">TAYYOR</div>
                            
                            <input v-model="dialerPhone" type="text" placeholder="NUMBER..." class="w-full bg-transparent border-none text-center text-2xl sm:text-3xl font-mono text-gray-900 tracking-widest sm:tracking-[0.2em] focus:ring-0 px-0 pt-4 pb-2" style="text-shadow: 0 0 10px rgba(255,255,255,0.5);">
                            
                            <!-- Blinking cursor effect fake -->
                            <div class="h-[1px] w-full bg-gradient-to-r from-transparent via-neon-cyan/50 to-transparent mt-2"></div>
                        </div>

                        <!-- Numeric Keypad -->
                        <div class="grid grid-cols-3 gap-3 mb-6 relative z-10">
                            <button v-for="num in ['1','2','3','4','5','6','7','8','9','*','0','#']" :key="num" 
                                @click="dialerPhone += num"
                                class="h-14 hover:bg-blue-50 border border-gray-200 hover:border-blue-200 rounded-lg text-xl font-mono text-gray-900/80 hover:text-gray-900 hover:shadow-sm transition-all flex flex-col items-center justify-center group/key">
                                <span>{{ num }}</span>
                                <span v-if="num === '1'" class="text-[8px] text-blue-600/40 opacity-0 group-hover/key:opacity-100">SYS</span>
                                <span v-if="num === '2'" class="text-[8px] text-blue-600/40 opacity-0 group-hover/key:opacity-100">ABC</span>
                                <span v-if="num === '3'" class="text-[8px] text-blue-600/40 opacity-0 group-hover/key:opacity-100">DEF</span>
                                <span v-if="num === '4'" class="text-[8px] text-blue-600/40 opacity-0 group-hover/key:opacity-100">GHI</span>
                                <span v-if="num === '5'" class="text-[8px] text-blue-600/40 opacity-0 group-hover/key:opacity-100">JKL</span>
                                <span v-if="num === '6'" class="text-[8px] text-blue-600/40 opacity-0 group-hover/key:opacity-100">MNO</span>
                                <span v-if="num === '7'" class="text-[8px] text-blue-600/40 opacity-0 group-hover/key:opacity-100">PQRS</span>
                                <span v-if="num === '8'" class="text-[8px] text-blue-600/40 opacity-0 group-hover/key:opacity-100">TUV</span>
                                <span v-if="num === '9'" class="text-[8px] text-blue-600/40 opacity-0 group-hover/key:opacity-100">WXYZ</span>
                                <span v-if="num === '0'" class="text-[8px] text-blue-600/40 opacity-0 group-hover/key:opacity-100">+</span>
                            </button>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-3 relative z-10">
                            <button @click="dialerPhone = dialerPhone.slice(0, -1)" class="w-16 h-14 bg-red-500/10 hover:bg-red-500/30 border border-red-200 rounded-lg text-red-500 flex items-center justify-center transition-all">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z" /></svg>
                            </button>
                            <button @click="makeCall" :disabled="isCalling || !dialerPhone" class="flex-1 h-14 bg-blue-50 hover:bg-blue-50 disabled:bg-gray-50 disabled:border-gray-200 text-blue-600 disabled:text-gray-500 font-display font-bold rounded-lg border border-blue-200 disabled:border-transparent transition-all uppercase tracking-wider text-xs sm:text-sm flex justify-center items-center gap-2 shadow-sm">
                                <span v-if="isCalling" class="animate-pulse">ULANMOQDA...</span>
                                <span v-else>QO'NG'IROQ QILISH</span>
                            </button>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Auto Dialer & Logs -->
                    <div class="col-span-1 lg:col-span-8 flex flex-col gap-1">
                        
                        <!-- Auto-Operator Hologram Panel -->
                        <div class="bg-gray-900/50 border border-gray-100 p-6 relative overflow-hidden">
                            <div class="absolute right-0 top-0 w-64 h-64 bg-neon-purple/5 blur-2xl pointer-events-none rounded-full"></div>
                            
                            <h3 class="text-sm font-mono text-neon-purple uppercase tracking-widest mb-4 flex items-center gap-2">
                                <span class="w-1.5 h-1.5 bg-neon-purple block"></span>
                                AVTO-ROBOT MODULI v1.0
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative z-10">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-mono text-neon-purple/70 mb-1 tracking-widest uppercase">MAQSADLI GURUHNI TANLANG</label>
                                        <div class="relative">
                                            <select v-model="autoGroup" class="w-full bg-[#050B14] border border-neon-purple/30 rounded text-sm font-mono text-gray-900 focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-all appearance-none pr-8">
                                                <option value="debtors">Qarzdorlar (Muddati o'tgan)</option>
                                                <option value="new_leads">Yangi Mijozlar (Excel'dan)</option>
                                                <option value="all">Barcha Mijozlar</option>
                                            </select>
                                            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-neon-purple/50 pointer-events-none font-mono text-xs">▼</div>
                                        </div>
                                    </div>
                                    
                                    <div v-if="autoGroup === 'new_leads'">
                                        <label class="block text-xs font-mono text-neon-purple/70 tracking-widest uppercase mb-1">MIJOZLAR BAZASI (EXCEL/CSV)</label>
                                        <div class="relative w-full border border-neon-purple/30 border-dashed rounded hover:bg-gray-100 transition flex items-center justify-center p-3 cursor-pointer">
                                            <input type="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept=".xlsx,.xls,.csv" @change="handleExcelUpload">
                                            <span class="text-xs font-mono text-blue-600" v-if="!excelFile">+ Excel fayl tanlang</span>
                                            <span class="text-xs font-mono text-emerald-600" v-else>✅ {{ excelFile.name }}</span>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="flex justify-between items-end mb-1">
                                            <label class="block text-xs font-mono text-neon-purple/70 tracking-widest uppercase">AUDIO FAYLNI TANLANG</label>
                                            <label class="cursor-pointer text-[10px] text-blue-600 hover:text-gray-900 transition-colors px-2 py-0.5 rounded border border-gray-200" :class="{'opacity-50 pointer-events-none': isUploading}">
                                                <span v-if="isUploading">Yuklanmoqda...</span>
                                                <span v-else>+ Yuklash</span>
                                                <input type="file" class="hidden" accept=".mp3,.wav" @change="handleFileUpload" :disabled="isUploading">
                                            </label>
                                        </div>
                                        <div class="relative">
                                            <select v-model="selectedAudio" class="w-full bg-[#050B14] border border-neon-purple/30 rounded text-sm font-mono text-gray-900 focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-all appearance-none pr-8">
                                                <option value="">-- Audio faylni tanlang --</option>
                                                <option v-for="audio in availableAudios" :key="audio.id" :value="audio.id">{{ audio.name }}</option>
                                            </select>
                                            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-neon-purple/50 pointer-events-none font-mono text-xs">▼</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col justify-between">
                                    <div class="space-y-4 mb-4">
                                        <div>
                                            <label class="block text-xs font-mono text-neon-purple/70 mb-1 tracking-widest uppercase">QO'NG'IROQ VAQTI</label>
                                            <input type="time" v-model="scheduleTime" class="w-full bg-[#050B14] border border-neon-purple/30 rounded text-sm font-mono text-gray-900 focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-all">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-mono text-neon-purple/70 mb-2 tracking-widest uppercase">HAFTA KUNLARI</label>
                                            <div class="flex flex-wrap gap-2">
                                                <label v-for="(day, index) in ['Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh', 'Ya']" :key="index" class="cursor-pointer">
                                                    <input type="checkbox" :value="index + 1" v-model="scheduleDays" class="hidden peer">
                                                    <div class="w-8 h-8 flex items-center justify-center rounded border border-neon-purple/30 text-xs font-mono text-gray-900/50 peer-checked:bg-neon-purple/20 peer-checked:border-neon-purple peer-checked:text-neon-purple transition-all hover:bg-gray-50">
                                                        {{ day }}
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <button @click="startAutoDialer" :disabled="isAutoDialing || !selectedAudio" class="w-full py-4 bg-neon-purple/20 hover:bg-neon-purple/40 text-neon-purple font-mono font-bold rounded border border-neon-purple/50 transition-all uppercase tracking-[0.2em] text-xs relative overflow-hidden group disabled:opacity-50 disabled:cursor-not-allowed">
                                        <div class="absolute inset-0 bg-neon-purple/20 translate-y-full group-hover:translate-y-0 transition-transform"></div>
                                        <span class="relative z-10 flex items-center justify-center gap-2">
                                            <span v-if="isAutoDialing" class="animate-pulse">JARAYON BAJARILMOQDA...</span>
                                            <span v-else>▶ KAMPANIYANI SAQLASH</span>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Call Logs Panel -->
                        <div class="bg-gray-900/50 border border-gray-100 p-0 flex-1 relative overflow-hidden flex flex-col h-80 lg:h-auto">
                            <div class="p-4 border-b border-gray-200 bg-[#050B14]/80 flex justify-between items-center z-10">
                                <h3 class="text-sm font-mono text-gray-900/70 uppercase tracking-widest flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 block"></span>
                                    QO'NG'IROQLAR TARIXI
                                </h3>
                                <button @click="fetchLogs" class="text-blue-600/70 hover:text-blue-600 font-mono text-xs uppercase tracking-wider border border-blue-200 px-3 py-1.5 rounded">
                                    YANGILASH
                                </button>
                            </div>
                            
                            <div class="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-2 z-10">
                                <div v-if="loading" class="text-center text-blue-600/50 text-xs py-10 font-mono animate-pulse uppercase tracking-[0.2em]">
                                    Ma'lumotlar olinmoqda...
                                </div>
                                <div v-else-if="logs.length === 0" class="text-center text-gray-900/30 text-xs py-10 font-mono uppercase tracking-[0.2em]">
                                    BAZADA MA'LUMOT TOPILMADI
                                </div>
                                
                                <div v-else v-for="log in logs" :key="log.id" class="p-3 border-l-2 border-r border-y border-gray-100 rounded bg-gray-900/50 flex items-center justify-between hover:bg-gray-50 hover:border-l-neon-cyan transition-colors group">
                                    <div class="flex items-center gap-4">
                                        <div :class="{ 'text-emerald-600 bg-emerald-50 border-emerald-200': log.status === 'answered', 'text-red-500 bg-red-500/10 border-red-200': log.status === 'missed' || log.status === 'failed', 'text-amber-600 bg-amber-50 border-amber-200': log.status === 'pending' }" class="w-8 h-8 rounded border flex items-center justify-center text-sm shadow-inner">
                                            <span v-if="log.type === 'inbound'">🡗</span>
                                            <span v-else-if="log.type === 'outbound'">🡕</span>
                                            <span v-else>🤖</span>
                                        </div>
                                        <div>
                                            <div class="text-sm font-mono text-gray-900/90 tracking-wider">{{ log.phone_number }}</div>
                                            <div class="text-[9px] text-gray-900/40 font-mono tracking-widest uppercase mt-0.5">
                                                ID: {{ log.id.toString().padStart(5, '0') }} | {{ new Date(log.created_at).toLocaleTimeString('en-US', {hour12: false}) }}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-4">
                                        <div class="text-right hidden sm:block">
                                            <div class="text-xs font-mono text-gray-900/60 tracking-widest uppercase">{{ log.duration_seconds }} SONIYA</div>
                                            <div :class="{ 'text-emerald-600': log.status === 'answered', 'text-red-500': log.status === 'missed' || log.status === 'failed', 'text-amber-600': log.status === 'pending' }" class="text-[10px] font-mono tracking-widest uppercase font-bold mt-0.5">
                                                HOLAT: {{ log.status }}
                                            </div>
                                        </div>
                                        <button v-if="log.status === 'answered' && log.duration_seconds > 0" class="w-8 h-8 rounded border border-blue-200 bg-blue-50 flex items-center justify-center hover:bg-blue-50 hover:border-blue-500 text-blue-600 transition-all" title="Eshitish">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                
                <!-- Footer Tech Bar -->
                <div class="px-6 py-2 bg-gray-900/50 border-t border-blue-200 flex justify-between items-center text-[8px] font-mono text-blue-600/50 tracking-[0.3em] uppercase">
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

const excelFile = ref(null);
const scheduleTime = ref('09:00');
const scheduleDays = ref([1, 2, 3, 4, 5]);

function handleExcelUpload(event) {
    excelFile.value = event.target.files[0];
}

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

async function startAutoDialer() {
    if (!selectedAudio.value || !scheduleTime.value || scheduleDays.value.length === 0) {
        alert("Iltimos, barcha maydonlarni (Audio, Vaqt, Hafta kunlari) to'ldiring.");
        return;
    }
    if (autoGroup.value === 'new_leads' && !excelFile.value) {
        alert("Iltimos, yangi mijozlar uchun Excel faylni yuklang.");
        return;
    }

    isAutoDialing.value = true;
    const formData = new FormData();
    formData.append('target_group', autoGroup.value);
    formData.append('audio_id', selectedAudio.value);
    formData.append('schedule_time', scheduleTime.value);
    scheduleDays.value.forEach(day => {
        formData.append('schedule_days[]', day);
    });
    
    if (autoGroup.value === 'new_leads' && excelFile.value) {
        formData.append('excel_file', excelFile.value);
    }

    try {
        await axios.post(route('admin.telephony.campaigns.store'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        alert('Avto-qo\'ng\'iroq kampaniyasi muvaffaqiyatli saqlandi va rejalashtirildi!');
        excelFile.value = null; // reset
    } catch (error) {
        console.error("Xatolik yuz berdi:", error);
        alert("Kampaniyalarni saqlashda xatolik.");
    } finally {
        isAutoDialing.value = false;
    }
}
</script>
