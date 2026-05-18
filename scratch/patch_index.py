import sys
import re

with open('resources/js/Pages/Admin/HR/Index.vue', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Inject state and methods for Brigades and Machine Owners
script_injection = """
// --- BRIGADE STATE & METHODS ---
const showBrigadeForm = ref(false);
const isBrigadeEditMode = ref(false);
const brigadeForm = ref({ id: null, name: '', foreman_name: '', phone: '' });
const activeBrigadeId = ref(null);

function openBrigadeForm(b = null) {
    if (b) {
        isBrigadeEditMode.value = true;
        brigadeForm.value = { id: b.id, name: b.name, foreman_name: b.foreman_name, phone: b.phone || '' };
    } else {
        isBrigadeEditMode.value = false;
        brigadeForm.value = { id: null, name: '', foreman_name: '', phone: '' };
    }
    showBrigadeForm.value = true;
}

function saveBrigade() {
    isProcessing.value = true;
    if (isBrigadeEditMode.value) {
        router.put(route('admin.hr.brigade.update', brigadeForm.value.id), brigadeForm.value, {
            onSuccess: () => { isProcessing.value = false; showBrigadeForm.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.hr.brigade.store'), brigadeForm.value, {
            onSuccess: () => { isProcessing.value = false; showBrigadeForm.value = false; },
            onError: () => isProcessing.value = false
        });
    }
}

function deleteBrigade(id) {
    if (confirm('Brigadani o\\'chirmoqchimisiz?')) {
        router.delete(route('admin.hr.brigade.destroy', id));
    }
}

// --- MACHINE OWNER STATE & METHODS ---
const showOwnerForm = ref(false);
const isOwnerEditMode = ref(false);
const ownerForm = ref({ id: null, name: '', phone: '' });
const activeOwnerId = ref(null);

function openOwnerForm(o = null) {
    if (o) {
        isOwnerEditMode.value = true;
        ownerForm.value = { id: o.id, name: o.name, phone: o.phone || '' };
    } else {
        isOwnerEditMode.value = false;
        ownerForm.value = { id: null, name: '', phone: '' };
    }
    showOwnerForm.value = true;
}

function saveOwner() {
    isProcessing.value = true;
    if (isOwnerEditMode.value) {
        router.put(route('admin.hr.machine_owner.update', ownerForm.value.id), ownerForm.value, {
            onSuccess: () => { isProcessing.value = false; showOwnerForm.value = false; },
            onError: () => isProcessing.value = false
        });
    } else {
        router.post(route('admin.hr.machine_owner.store'), ownerForm.value, {
            onSuccess: () => { isProcessing.value = false; showOwnerForm.value = false; },
            onError: () => isProcessing.value = false
        });
    }
}

function deleteOwner(id) {
    if (confirm('Texnika egasini o\\'chirmoqchimisiz?')) {
        router.delete(route('admin.hr.machine_owner.destroy', id));
    }
}
"""

if "// --- BRIGADE STATE & METHODS ---" not in content:
    idx = content.find("// User Management Modal State")
    if idx != -1:
        content = content[:idx] + script_injection + "\n\n" + content[idx:]


# 2. Replace Workers Column
workers_col_start = content.find('<!-- Workers Column -->')
tab1_start = content.find('<!-- TAB 1: FACE-ID HUB -->')

new_workers_col = """<!-- Workers Column -->
            <div class="glass-panel p-6 rounded-2xl border border-white/5 flex flex-col h-[700px]">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <h2 class="text-xl font-display font-bold text-white flex items-center gap-2">
                        👷‍♂️ Yollanma Ishchilar & Brigadalar
                    </h2>
                    
                    <button @click="showBrigadeForm = !showBrigadeForm" class="px-3 py-1.5 bg-neon-cyan/20 text-neon-cyan text-[10px] font-mono tracking-widest uppercase rounded hover:bg-neon-cyan/30 transition-colors border border-neon-cyan/30">
                        {{ showBrigadeForm ? '- Yopish' : '+ Brigada Qo\\'shish' }}
                    </button>
                </div>

                <form v-if="showBrigadeForm" @submit.prevent="saveBrigade" class="mb-6 space-y-4 bg-black/30 p-4 rounded-xl border border-white/5 flex-shrink-0 animate-[fadeIn_0.3s_ease-out]">
                    <h3 class="text-[10px] font-mono text-text-muted uppercase tracking-widest">{{ isBrigadeEditMode ? 'Brigadani Tahrirlash' : 'Yangi Brigada Qo\\'shish' }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input v-model="brigadeForm.name" type="text" class="glass-input text-xs" placeholder="Brigada Nomi (Masalan: G'isht teruvchilar)" required>
                        <input v-model="brigadeForm.foreman_name" type="text" class="glass-input text-xs" placeholder="Boshqaruvchi Usta (Prorab)" required>
                        <input v-model="brigadeForm.phone" type="text" class="glass-input text-xs" placeholder="Telefon Raqami">
                    </div>
                    <div class="flex gap-3 items-center justify-end pt-2">
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-[10px] font-mono tracking-widest uppercase rounded transition-colors disabled:opacity-50">
                            {{ isBrigadeEditMode ? 'Saqlash' : 'Qo\\'shish' }}
                        </button>
                    </div>
                </form>

                <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-4">
                    <!-- Brigades List -->
                    <div v-for="b in brigades" :key="b.id" class="border border-white/10 bg-black/40 rounded-xl overflow-hidden shadow-[0_4px_20px_rgba(0,0,0,0.5)]">
                        <div class="p-4 cursor-pointer hover:bg-white/5 flex justify-between items-center transition-colors" @click="activeBrigadeId = activeBrigadeId === b.id ? null : b.id">
                            <div>
                                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                                    <span class="text-neon-cyan">{{ activeBrigadeId === b.id ? '▼' : '▶' }}</span>
                                    {{ b.name }}
                                </h3>
                                <p class="text-[10px] font-mono text-text-muted mt-1 uppercase">Usta: {{ b.foreman_name }} <span v-if="b.phone">| {{ b.phone }}</span></p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-[10px] font-mono text-text-muted uppercase mb-0.5">Brigada Balansi</p>
                                    <p class="text-sm font-bold" :class="b.balance > 0 ? 'text-neon-green' : (b.balance < 0 ? 'text-neon-red' : 'text-white')">{{ $currency(b.balance || 0) }}</p>
                                </div>
                                <div class="flex gap-1.5 opacity-70 hover:opacity-100 transition-opacity">
                                    <button v-if="isAdmin" @click.stop="openBrigadeForm(b)" class="w-6 h-6 flex items-center justify-center bg-white/5 text-white rounded hover:bg-white/20 transition-colors" title="Tahrirlash">✏️</button>
                                    <button v-if="isAdmin" @click.stop="deleteBrigade(b.id)" class="w-6 h-6 flex items-center justify-center bg-neon-red/10 text-neon-red rounded hover:bg-neon-red/30 transition-colors" title="O'chirish">🗑️</button>
                                </div>
                            </div>
                        </div>

                        <!-- Inner Workers -->
                        <div v-if="activeBrigadeId === b.id" class="bg-black/60 p-4 border-t border-white/5 animate-[fadeIn_0.2s_ease-out]">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="text-xs font-mono text-text-muted uppercase">Brigada Ishchilari</h4>
                                <button @click="openWorkerEdit({ brigade_id: b.id })" class="px-2 py-1 bg-white/10 text-white text-[9px] font-mono tracking-widest uppercase rounded hover:bg-white/20 transition-colors">
                                    + Ishchi
                                </button>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="w in b.workers" :key="w.id" class="p-3 bg-white/5 rounded-lg border border-white/5 flex flex-col justify-between">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-xs font-bold text-white">{{ w.name }}</p>
                                            <p class="text-[9px] font-mono text-text-muted mt-0.5 uppercase">{{ w.role }} | {{ w.salary_type }} ({{ w.metric_type }})</p>
                                        </div>
                                        <div class="flex gap-1">
                                            <button v-if="isAdmin" @click="openWorkerEdit(w)" class="text-[10px] px-1 hover:text-white text-text-muted">✏️</button>
                                            <button v-if="isAdmin" @click="deleteWorker(w.id)" class="text-[10px] px-1 hover:text-neon-red text-text-muted">🗑️</button>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-xs font-mono text-neon-cyan">{{ $currency(w.base_rate || w.rate) }}</div>
                                </div>
                                <div v-if="!b.workers || b.workers.length === 0" class="col-span-2 text-center text-[10px] font-mono text-text-muted py-4 border border-dashed border-white/10 rounded-lg">
                                    Ishchilar mavjud emas
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hidden Worker Form for reuse -->
                <div v-if="showWorkerForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="cancelWorkerEdit"></div>
                    <form @submit.prevent="saveWorker" class="relative bg-black border border-neon-cyan/30 p-6 rounded-2xl max-w-lg w-full space-y-4">
                        <h3 class="text-lg font-bold text-white uppercase">{{ isWorkerEditMode ? 'Ishchini Tahrirlash' : 'Yangi Ishchi Qo\\'shish' }}</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <input v-model="workerForm.name" type="text" class="glass-input text-xs" placeholder="F.I.SH" required>
                            <input v-model="workerForm.phone" type="text" class="glass-input text-xs" placeholder="Telefon Raqam">
                            <select v-model="workerForm.brigade_id" class="glass-input text-xs bg-black/50 text-white" required>
                                <option value="" disabled>-- Brigada --</option>
                                <option v-for="br in brigades" :key="br.id" :value="br.id">{{ br.name }}</option>
                            </select>
                            <select v-model="workerForm.role" class="glass-input text-xs bg-black/50 text-white">
                                <option value="builder">Quruvchi / Usta</option>
                                <option value="driver">Haydovchi</option>
                                <option value="guard">Qorovul</option>
                                <option value="office">Boshqa</option>
                            </select>
                            <select v-model="workerForm.salary_type" class="glass-input text-xs bg-black/50 text-white">
                                <option value="piece_rate">Ishbay</option>
                                <option value="hourly">Soatbay</option>
                                <option value="fixed_monthly">Oylik Maosh</option>
                            </select>
                            <select v-if="workerForm.salary_type === 'piece_rate'" v-model="workerForm.metric_type" class="glass-input text-xs bg-black/50 text-white">
                                <option value="dona">Dona</option>
                                <option value="kv_m">Kvadrat Metr</option>
                                <option value="kub_m">Kub Metr</option>
                                <option value="metr">Metr</option>
                                <option value="soat">Soat</option>
                                <option value="kun">Kun</option>
                                <option value="reys">Reys</option>
                            </select>
                            <input v-model="workerForm.base_rate" type="number" class="glass-input text-xs" placeholder="Narxi (Stavka)" required>
                        </div>
                        <div class="flex justify-end gap-3 mt-4">
                            <button type="button" @click="cancelWorkerEdit" class="text-text-muted text-xs hover:text-white">Bekor qilish</button>
                            <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-neon-cyan text-black font-bold uppercase tracking-widest text-xs rounded hover:shadow-[0_0_15px_#00F0FF]">{{ $t('save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 1: FACE-ID HUB -->"""

content = content[:workers_col_start] + new_workers_col + content[tab1_start + len('<!-- TAB 1: FACE-ID HUB -->'):]

# 3. Replace TAB 3: Machinery
t3_start = content.find('<!-- TAB 3: Machinery -->')
t4_start = content.find('<!-- TAB 4: Timesheets & Logistics / Cash Requests -->')

new_tab3 = """<!-- TAB 3: Machinery -->
        <div v-if="activeTab === 3" class="max-w-4xl mx-auto animate-[fadeIn_0.5s_ease-out]">
            <div class="glass-panel p-6 rounded-2xl border border-white/5">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <h2 class="text-xl font-display font-bold text-white flex items-center gap-2">
                        🚜 Og'ir Texnika va Egaslari (Arendatorlar)
                    </h2>
                    <button @click="showOwnerForm = !showOwnerForm" class="px-3 py-1.5 bg-neon-cyan/20 text-neon-cyan text-[10px] font-mono tracking-widest uppercase rounded hover:bg-neon-cyan/30 transition-colors border border-neon-cyan/30">
                        {{ showOwnerForm ? '- Yopish' : '+ Texnika Egasi Qo\\'shish' }}
                    </button>
                </div>

                <form v-if="showOwnerForm" @submit.prevent="saveOwner" class="mb-6 space-y-4 bg-black/30 p-4 rounded-xl border border-white/5 flex-shrink-0 animate-[fadeIn_0.3s_ease-out]">
                    <h3 class="text-[10px] font-mono text-text-muted uppercase tracking-widest">{{ isOwnerEditMode ? 'Egasini Tahrirlash' : 'Yangi Texnika Egasi' }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input v-model="ownerForm.name" type="text" class="glass-input text-xs" placeholder="Tashkilot / Ism" required>
                        <input v-model="ownerForm.phone" type="text" class="glass-input text-xs" placeholder="Telefon Raqami">
                    </div>
                    <div class="flex gap-3 items-center justify-end pt-2">
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-[10px] font-mono tracking-widest uppercase rounded transition-colors disabled:opacity-50">
                            {{ isOwnerEditMode ? 'Saqlash' : 'Qo\\'shish' }}
                        </button>
                    </div>
                </form>

                <div class="space-y-4">
                    <div v-for="owner in machineOwners" :key="owner.id" class="border border-white/10 bg-black/40 rounded-xl overflow-hidden shadow-[0_4px_20px_rgba(0,0,0,0.5)]">
                        <!-- Owner Header -->
                        <div class="p-4 cursor-pointer hover:bg-white/5 flex justify-between items-center transition-colors" @click="activeOwnerId = activeOwnerId === owner.id ? null : owner.id">
                            <div>
                                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                                    <span class="text-neon-cyan">{{ activeOwnerId === owner.id ? '▼' : '▶' }}</span>
                                    {{ owner.name }}
                                </h3>
                                <p class="text-[10px] font-mono text-text-muted mt-1">{{ owner.phone }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-[10px] font-mono text-text-muted uppercase mb-0.5">Umumiy Balans (Haqi)</p>
                                    <p class="text-sm font-bold" :class="owner.balance > 0 ? 'text-neon-red' : (owner.balance < 0 ? 'text-neon-green' : 'text-white')">{{ $currency(owner.balance || 0) }}</p>
                                </div>
                                <div class="flex gap-1.5 opacity-70 hover:opacity-100 transition-opacity">
                                    <button v-if="isAdmin" @click.stop="openOwnerForm(owner)" class="w-6 h-6 flex items-center justify-center bg-white/5 text-white rounded hover:bg-white/20 transition-colors" title="Tahrirlash">✏️</button>
                                    <button v-if="isAdmin" @click.stop="deleteOwner(owner.id)" class="w-6 h-6 flex items-center justify-center bg-neon-red/10 text-neon-red rounded hover:bg-neon-red/30 transition-colors" title="O'chirish">🗑️</button>
                                </div>
                            </div>
                        </div>

                        <!-- Inner Machineries -->
                        <div v-if="activeOwnerId === owner.id" class="bg-black/60 p-4 border-t border-white/5 animate-[fadeIn_0.2s_ease-out]">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="text-xs font-mono text-text-muted uppercase">Tegishli Texnikalar</h4>
                                <button @click="machineryForm.machine_owner_id = owner.id; showMachineryForm = true" class="px-2 py-1 bg-white/10 text-white text-[9px] font-mono tracking-widest uppercase rounded hover:bg-white/20 transition-colors">
                                    + Texnika Qo'shish
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="m in owner.machineries" :key="m.id" class="p-3 bg-white/5 rounded-lg border border-white/5 flex flex-col justify-between hover:border-white/20 transition-colors">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-xs font-bold text-white">{{ m.machinery_type }} - {{ m.name }}</p>
                                            <p class="text-[9px] font-mono text-neon-cyan uppercase mt-0.5">{{ m.license_plate }}</p>
                                        </div>
                                        <div class="flex gap-1">
                                            <button v-if="isAdmin" @click="startEditMachinery(m); showMachineryForm = true" class="text-[10px] px-1 hover:text-white text-text-muted">✏️</button>
                                            <button v-if="isAdmin" @click="deleteMachinery(m.id)" class="text-[10px] px-1 hover:text-neon-red text-text-muted">🗑️</button>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-end mt-3">
                                        <p class="text-[10px] font-mono text-text-muted uppercase">{{ m.payment_type === 'hourly' ? 'Soatbay' : 'Hajm' }}</p>
                                        <p class="text-xs font-bold text-neon-green">{{ $currency(m.cost_per_hour) }}</p>
                                    </div>
                                </div>
                                <div v-if="!owner.machineries || owner.machineries.length === 0" class="col-span-2 text-center text-[10px] font-mono text-text-muted py-4 border border-dashed border-white/10 rounded-lg">
                                    Texnikalar mavjud emas
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add/Edit Machinery Modal -->
                <div v-if="showMachineryForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showMachineryForm = false; editMachineryId = null"></div>
                    <form @submit.prevent="editMachineryId ? updateMachinery() : addMachinery()" class="relative bg-black border border-neon-cyan/30 p-6 rounded-2xl max-w-lg w-full space-y-4">
                        <h3 class="text-lg font-bold text-white uppercase">{{ editMachineryId ? 'Texnikani Tahrirlash' : 'Yangi Texnika Qo\\'shish' }}</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] text-text-muted uppercase mb-1">Egasi (Tanlang)</label>
                                <select v-model="editMachineryId ? editMachineryForm.machine_owner_id : machineryForm.machine_owner_id" class="glass-input text-xs w-full bg-black/50 text-white" required>
                                    <option v-for="o in machineOwners" :key="o.id" :value="o.id">{{ o.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] text-text-muted uppercase mb-1">Turi (Yo'nalishi)</label>
                                <input v-model="editMachineryId ? editMachineryForm.machinery_type : machineryForm.machinery_type" type="text" class="glass-input text-xs w-full" placeholder="Kran, Buldozer...">
                            </div>
                            <div>
                                <label class="block text-[10px] text-text-muted uppercase mb-1">Model / Rusumi</label>
                                <input v-model="editMachineryId ? editMachineryForm.name : machineryForm.name" type="text" class="glass-input text-xs w-full" required>
                            </div>
                            <div>
                                <label class="block text-[10px] text-text-muted uppercase mb-1">Davlat Raqami</label>
                                <input v-model="editMachineryId ? editMachineryForm.license_plate : machineryForm.license_plate" type="text" class="glass-input text-xs w-full">
                            </div>
                            <div>
                                <label class="block text-[10px] text-text-muted uppercase mb-1">To'lov Turi</label>
                                <select v-model="editMachineryId ? editMachineryForm.payment_type : machineryForm.payment_type" class="glass-input text-xs w-full bg-black/50 text-white" required>
                                    <option value="hourly">Soatbay</option>
                                    <option value="volume">Hajm bo'yicha</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] text-text-muted uppercase mb-1">Narxi (Stavka)</label>
                                <input v-model="editMachineryId ? editMachineryForm.cost_per_hour : machineryForm.cost_per_hour" type="number" class="glass-input text-xs w-full" required>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 mt-4">
                            <button type="button" @click="showMachineryForm = false; editMachineryId = null" class="text-text-muted text-xs hover:text-white">Bekor qilish</button>
                            <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-neon-cyan text-black font-bold uppercase tracking-widest text-xs rounded hover:shadow-[0_0_15px_#00F0FF]">{{ $t('save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 4: Timesheets & Logistics / Cash Requests -->"""

content = content[:t3_start] + new_tab3 + content[t4_start:]

# 4. Inject showMachineryForm state
if "const showMachineryForm = ref(false);" not in content:
    idx2 = content.find("const machineryForm = ref({")
    if idx2 != -1:
        content = content[:idx2] + "const showMachineryForm = ref(false);\n" + content[idx2:]

with open('resources/js/Pages/Admin/HR/Index.vue', 'w', encoding='utf-8') as f:
    f.write(content)

print("SUCCESS")
