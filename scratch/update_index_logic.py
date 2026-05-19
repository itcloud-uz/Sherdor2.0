import re

path = 'resources/js/Pages/Admin/HR/Index.vue'
with open(path, 'r', encoding='utf8') as f:
    content = f.read()

# 1. Update workerForm initialization
content = content.replace(
    "const workerForm = ref({ id: null, name: '', phone: '', role: 'builder', salary_type: 'piece_rate', metric_type: 'dona', base_rate: '', building_id: '', floor_id: '', unit_id: '' });",
    "const workerForm = ref({ id: null, name: '', phone: '', role: 'builder', salary_type: 'piece_rate', metric_type: 'dona', base_rate: '', building_id: '', floor_id: '', unit_id: '', start_date: '', backdated_salaries: [] });"
)

content = content.replace(
    "workerForm.value = { id: null, name: '', phone: '', role: 'builder', salary_type: 'piece_rate', metric_type: 'dona', base_rate: '', building_id: '', floor_id: '', unit_id: '' };",
    "workerForm.value = { id: null, name: '', phone: '', role: 'builder', salary_type: 'piece_rate', metric_type: 'dona', base_rate: '', building_id: '', floor_id: '', unit_id: '', start_date: '', backdated_salaries: [] };"
)

# 2. Add generateBackdatedFields and viewGuardHistory
js_methods = '''
function generateBackdatedFields() {
    if (!workerForm.value.start_date || workerForm.value.role !== 'guard') return;
    const start = new Date(workerForm.value.start_date);
    const now = new Date();
    workerForm.value.backdated_salaries = [];
    
    let current = new Date(start.getFullYear(), start.getMonth(), 1);
    const currentMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    
    while (current < currentMonth) {
        const monthStr = current.getFullYear() + '-' + String(current.getMonth() + 1).padStart(2, '0');
        workerForm.value.backdated_salaries.push({
            month: monthStr,
            salary: workerForm.value.base_rate || ''
        });
        current.setMonth(current.getMonth() + 1);
    }
}

const showGuardHistoryModal = ref(false);
const activeGuardHistory = ref(null);

function viewGuardHistory(guard) {
    activeGuardHistory.value = guard;
    showGuardHistoryModal.value = true;
}
'''
content = content.replace('function cancelWorkerEdit() {', js_methods + '\n\nfunction cancelWorkerEdit() {')

# 3. Inject inputs into the worker form UI
guard_inputs = '''
                            <div v-if="workerForm.role === 'guard' && workerForm.salary_type === 'fixed_monthly'" class="col-span-1 sm:col-span-2 p-3 bg-white/5 border border-white/10 rounded-xl space-y-3">
                                <div>
                                    <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Ishga kirgan sanasi (Sana o'zgarishi bilanoq, o'tgan oylar kiritiladi)</label>
                                    <input type="date" v-model="workerForm.start_date" @change="generateBackdatedFields" class="glass-input text-xs w-full">
                                </div>
                                <div v-if="workerForm.backdated_salaries && workerForm.backdated_salaries.length > 0" class="space-y-2 mt-2">
                                    <h4 class="text-[10px] font-mono text-neon-cyan uppercase">O'tgan Oylar Maoshini Kiritish</h4>
                                    <div v-for="(entry, idx) in workerForm.backdated_salaries" :key="idx" class="flex gap-2 items-center">
                                        <span class="text-xs font-mono text-text-muted w-24">{{ entry.month }}</span>
                                        <input type="number" v-model="entry.salary" class="glass-input text-xs flex-1" placeholder="Shu oy uchun qilingan to'lov yoki oylik" required>
                                    </div>
                                </div>
                            </div>
'''
content = content.replace('<input v-model="workerForm.base_rate" type="number" class="glass-input text-xs" placeholder="Narxi (Stavka)" required>\n                        </div>', '<input v-model="workerForm.base_rate" type="number" class="glass-input text-xs" placeholder="Narxi (Stavka)" required>\n' + guard_inputs + '\n                        </div>')

# 4. Inject Guard History Modal at the end of <template>
history_modal = '''
        <!-- Guard Salary History Modal -->
        <div v-if="showGuardHistoryModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showGuardHistoryModal = false"></div>
            <div class="relative bg-black border border-neon-cyan/30 p-6 rounded-2xl max-w-2xl w-full shadow-[0_0_50px_rgba(0,255,255,0.1)]">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-display font-bold text-white flex items-center gap-2">
                        🛡️ {{ activeGuardHistory?.name }} - Maosh Tarixi
                    </h2>
                    <button @click="showGuardHistoryModal = false" class="text-text-muted hover:text-white transition-colors">
                        ✕
                    </button>
                </div>
                
                <div class="overflow-x-auto rounded-xl border border-white/10 bg-white/5">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="text-neon-cyan bg-black/50 border-b border-white/10">
                            <tr>
                                <th class="p-3 font-normal uppercase tracking-widest">Sana</th>
                                <th class="p-3 font-normal uppercase tracking-widest text-right">Oylik Miqdori</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="hist in activeGuardHistory?.salary_histories" :key="hist.id" class="hover:bg-white/5 transition-colors">
                                <td class="p-3 text-white">{{ new Date(hist.effective_date).toLocaleDateString() }}</td>
                                <td class="p-3 text-neon-cyan text-right font-bold">{{ $currency(hist.salary) }} so'm</td>
                            </tr>
                            <tr v-if="!activeGuardHistory?.salary_histories || activeGuardHistory.salary_histories.length === 0">
                                <td colspan="2" class="p-6 text-center text-text-muted">Tarix mavjud emas</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button @click="showGuardHistoryModal = false" class="px-4 py-2 bg-white/10 text-white font-bold uppercase tracking-widest text-xs rounded hover:bg-white/20 transition-colors">Yopish</button>
                </div>
            </div>
        </div>
'''
content = content.replace('</template>', history_modal + '\n</template>')

with open(path, 'w', encoding='utf8') as f:
    f.write(content)
