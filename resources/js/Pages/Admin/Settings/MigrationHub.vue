<template>
    <AdminLayout>
        <Head title="Migration Hub (ETL)" />

        <div class="p-6 h-screen flex flex-col bg-[#020205] overflow-y-auto custom-scrollbar relative">
            
            <!-- Header -->
            <div class="flex justify-between items-end mb-8 relative z-10">
                <div class="relative">
                    <div class="absolute -left-10 -top-10 w-40 h-40 bg-neon-purple/20 blur-[80px] rounded-full pointer-events-none"></div>
                    <h1 class="text-4xl font-display font-black text-gray-900 uppercase tracking-wider drop-shadow-sm">
                        Migration <span class="text-neon-purple">Hub</span>
                    </h1>
                    <p class="text-xs text-gray-500 font-mono mt-2">Эски Excel ва Word базаларни ақлли кўчириш маркази</p>
                </div>
                
                <button @click="clearData" :disabled="isClearing || isExecuting" class="px-4 py-2 bg-gray-500/50 border border-neon-magenta/50 text-indigo-600 font-mono text-xs rounded hover:bg-indigo-50 hover:border-neon-magenta disabled:opacity-50 transition-all flex items-center gap-2">
                    <span v-if="isClearing" class="animate-spin">⚙️</span>
                    🗑️ Базани Тозалаш
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 gap-8 relative z-10 h-full">
                <!-- Action Section -->
                <div class="flex flex-col gap-6">
                    <!-- Category Selector -->
                    <div class="bg-white border border-gray-200 shadow-sm rounded-xl border border-neon-purple/30 rounded-3xl p-6 flex flex-col gap-4 bg-[#05050A] shadow-sm">
                        <h3 class="text-xs font-mono text-gray-900 uppercase tracking-widest text-neon-purple">Маълумот Турини Танланг</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" v-model="selectedCategory" value="crm" class="hidden peer">
                                <div class="px-4 py-3 border border-gray-200 rounded-xl text-center text-xs text-gray-500 font-display uppercase tracking-wider peer-checked:bg-neon-purple/20 peer-checked:border-neon-purple peer-checked:text-gray-900 transition-all hover:bg-gray-100">
                                    CRM (Мижозлар)
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" v-model="selectedCategory" value="hr" class="hidden peer">
                                <div class="px-4 py-3 border border-gray-200 rounded-xl text-center text-xs text-gray-500 font-display uppercase tracking-wider peer-checked:bg-neon-purple/20 peer-checked:border-neon-purple peer-checked:text-gray-900 transition-all hover:bg-gray-100">
                                    HR (Ишчилар)
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" v-model="selectedCategory" value="finance" class="hidden peer">
                                <div class="px-4 py-3 border border-gray-200 rounded-xl text-center text-xs text-gray-500 font-display uppercase tracking-wider peer-checked:bg-neon-purple/20 peer-checked:border-neon-purple peer-checked:text-gray-900 transition-all hover:bg-gray-100">
                                    Молия
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" v-model="selectedCategory" value="machinery" class="hidden peer">
                                <div class="px-4 py-3 border border-gray-200 rounded-xl text-center text-xs text-gray-500 font-display uppercase tracking-wider peer-checked:bg-neon-purple/20 peer-checked:border-neon-purple peer-checked:text-gray-900 transition-all hover:bg-gray-100">
                                    Техника
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- File Chooser Container -->
                    <div class="bg-white border border-gray-200 shadow-sm rounded-xl border border-blue-200 rounded-3xl p-4 md:p-8 flex flex-col items-center justify-center transition-all bg-[#05050A] shadow-sm hover:border-blue-200">
                        <input type="file" ref="fileInput" @change="handleFileSelect" multiple accept=".xlsx,.xls,.docx,.zip" class="hidden">
                        
                        <button @click="$refs.fileInput.click()" class="w-full py-5 bg-blue-50 border-2 border-blue-500 text-blue-600 font-bold font-display text-xl uppercase tracking-widest rounded-2xl hover:bg-blue-600 hover:text-black hover:shadow-sm transition-all flex items-center justify-center gap-4 group">
                            <span class="text-3xl group-hover:scale-125 transition-transform">📂</span>
                            Компьютердан файлларни танлаш
                        </button>
                        
                        <p class="text-[10px] text-gray-500 font-mono mt-4 text-center uppercase tracking-widest">
                            Қўллаб-қувватланади: .xlsx, .docx, .zip
                        </p>
                    </div>
                    
                    <!-- Selected Files Preview -->
                    <div v-if="selectedFiles.length > 0" class="bg-white border border-gray-200 shadow-sm rounded-xl border border-gray-200 p-6 bg-gray-500/50 rounded-2xl max-h-[250px] overflow-y-auto custom-scrollbar">
                        <h3 class="text-xs font-mono text-gray-900 mb-4 uppercase tracking-widest text-amber-600">Танланган Файллар ({{ selectedFiles.length }})</h3>
                        <div class="space-y-2">
                            <div v-for="(file, i) in selectedFiles" :key="i" class="p-2 border border-gray-100 rounded flex justify-between items-center group">
                                <div class="text-[10px] text-gray-900 font-mono truncate max-w-[80%]">{{ file.name }}</div>
                                <div class="text-[9px] text-gray-500 font-mono">{{ (file.size / 1024).toFixed(1) }} KB</div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div v-if="uploadProgress > 0" class="w-full">
                        <div class="h-1 w-full rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-600 transition-all duration-300" :style="`width: ${uploadProgress}%`"></div>
                        </div>
                        <div class="text-right text-[10px] text-emerald-600 font-mono mt-1">Юкланмоқда... {{ uploadProgress }}%</div>
                    </div>

                    <!-- Start Button (Upload + Trigger) -->
                    <button 
                        @click="startMigration" 
                        :disabled="selectedFiles.length === 0 || isExecuting"
                        class="w-full py-5 rounded-2xl text-black font-black font-display text-xl uppercase tracking-[0.2em] transition-all relative overflow-hidden disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="isExecuting ? 'bg-amber-500 shadow-sm' : 'bg-emerald-600 hover:shadow-sm hover:scale-[1.02]'"
                    >
                        <span v-if="isExecuting" class="flex items-center justify-center gap-3">
                            <span class="animate-spin text-2xl">⚙️</span>
                            ЖАРАЁН КЕТМОҚДА...
                        </span>
                        <span v-else class="flex items-center justify-center gap-3">
                            <span class="text-2xl">🚀</span>
                            Тизимга Юклаш ва Кўчириш
                        </span>
                    </button>
                </div>

                <!-- Terminal Section -->
                <div class="flex flex-col gap-6 h-full">
                    <!-- Cyberpunk Terminal -->
                    <div class="bg-white border border-gray-200 shadow-sm rounded-xl border border-[#00ff41]/30 p-1 bg-black flex-1 flex flex-col relative overflow-hidden group shadow-sm rounded-2xl h-[500px]">
                        <div class="absolute inset-0 bg-[linear-gradient(rgba(0,255,65,0.03)_50%,rgba(0,0,0,0.2)_50%)] bg-[length:100%_4px] pointer-events-none"></div>
                        
                        <div class="bg-[#001100] px-4 py-2 border-b border-[#00ff41]/30 flex justify-between items-center relative z-10">
                            <span class="text-[10px] text-[#00ff41] font-mono tracking-widest uppercase">root@sherdor-erp:~# etl_pipeline_v1</span>
                            <div class="flex gap-2">
                                <div class="w-2 h-2 rounded-full bg-red-500"></div>
                                <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                                <div class="w-2 h-2 rounded-full bg-green-500"></div>
                            </div>
                        </div>
                        
                        <div class="p-4 flex-1 overflow-y-auto custom-scrollbar relative z-10 font-mono text-xs leading-relaxed" id="terminal-output">
                            <div v-if="!hasLogs" class="text-[#00ff41]/50 italic">Терминал тайёр. Файлларни танлаб, "Кўчириш" тугмасини босинг.</div>
                            <pre v-else class="text-[#00ff41] whitespace-pre-wrap font-mono" style="font-family: monospace;">{{ terminalOutput }}</pre>
                            
                            <div v-if="isExecuting" class="mt-4 flex items-center gap-2 text-[#00ff41]">
                                <span class="animate-pulse">_</span>
                                <span class="animate-[pulse_1s_ease-in-out_infinite]">Маълумотлар таҳлил қилинмоқда...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';

const fileInput = ref(null);
const selectedFiles = ref([]);
const selectedCategory = ref('crm');
const uploadProgress = ref(0);

const isExecuting = ref(false);
const isClearing = ref(false);
const terminalOutput = ref('');
const hasLogs = ref(false);

function handleFileSelect(e) {
    if (e.target.files.length > 0) {
        selectedFiles.value = Array.from(e.target.files);
    }
}

async function startMigration() {
    isExecuting.value = true;
    hasLogs.value = true;
    terminalOutput.value = '';
    
    terminalLog("> 1. Файллар серверга юкланмоқда...");
    
    // Step 1: Upload Files
    const formData = new FormData();
    for (let i = 0; i < selectedFiles.value.length; i++) {
        formData.append('files[]', selectedFiles.value[i]);
    }

    try {
        await axios.post(route('admin.migration.upload'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            },
            onUploadProgress: (progressEvent) => {
                const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                uploadProgress.value = percentCompleted;
            }
        });
        
        uploadProgress.value = 0;
        selectedFiles.value = []; // Clear selection after successful upload
        terminalLog("> Файллар муваффақиятли юкланди.");
        
        // Step 2: Trigger ETL Script
        terminalLog("> 2. Python ETL Скрипти ишга туширилмоқда...");
        const response = await axios.post(route('admin.migration.execute'), {
            category_type: selectedCategory.value
        });
        terminalLog("\n--- ПАЙТОН ТЕРМИНАЛИ ---");
        terminalLog(response.data.log);
        
    } catch (error) {
        console.error(error);
        uploadProgress.value = 0;
        if (error.response && error.response.data && error.response.data.log) {
            terminalLog("\n--- ПАЙТОН ТЕРМИНАЛИ (ХАТОЛИК) ---");
            terminalLog(error.response.data.log);
        } else {
            terminalLog("\n> ❌ [CRITICAL ERROR] Тизим билан алоқа узилди ёки юклашда хатолик юз берди.");
        }
    } finally {
        isExecuting.value = false;
    }
}

async function clearData() {
    if(!confirm("Барча юкланган эски файллар ўчиб кетади. Ишончингиз комилми?")) return;
    
    isClearing.value = true;
    try {
        await axios.delete(route('admin.migration.clear'));
        terminalLog("> База тозалаш амалга оширилди.");
        selectedFiles.value = [];
    } catch(e) {
        terminalLog("> Тозалашда хатолик.");
    } finally {
        isClearing.value = false;
    }
}

function terminalLog(text) {
    hasLogs.value = true;
    terminalOutput.value += text + '\n';
    scrollToBottom();
}

function scrollToBottom() {
    nextTick(() => {
        const container = document.getElementById('terminal-output');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    });
}
</script>

<style scoped>
/* Cyberpunk specific styles */
</style>
