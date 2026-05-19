import re

path = r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue'
with open(path, 'r', encoding='utf8') as f:
    text = f.read()

# 1. Update initial state
text = text.replace(
    "start_date: '', backdated_salaries: []",
    "salary_periods: []"
)

# 2. Replace generateBackdatedFields and UI inputs
js_replacement = '''
function addSalaryPeriod() {
    workerForm.value.salary_periods.push({ start_month: '', end_month: '', salary: workerForm.value.base_rate || '' });
}
function removeSalaryPeriod(index) {
    workerForm.value.salary_periods.splice(index, 1);
}
'''
text = re.sub(r'function generateBackdatedFields\(\) \{.*?\n\}\n', js_replacement + '\n', text, flags=re.DOTALL)

# 3. Update the Template UI for salary periods
ui_old = '''                            <div v-if="workerForm.role === 'guard' && workerForm.salary_type === 'fixed_monthly'" class="col-span-1 sm:col-span-2 p-3 bg-white/5 border border-white/10 rounded-xl space-y-3">
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
                            </div>'''

ui_new = '''                            <div v-if="workerForm.role === 'guard' && workerForm.salary_type === 'fixed_monthly'" class="col-span-1 sm:col-span-2 p-4 bg-white/5 border border-white/10 rounded-xl space-y-4">
                                <div class="flex justify-between items-center">
                                    <h4 class="text-[10px] font-mono text-neon-cyan uppercase">O'tgan Davrlar Maoshini Kiritish</h4>
                                    <button type="button" @click="addSalaryPeriod" class="px-3 py-1 bg-neon-cyan/20 text-neon-cyan rounded hover:bg-neon-cyan hover:text-black transition-colors text-[10px] font-bold uppercase tracking-widest">+ Davr Qo'shish</button>
                                </div>
                                <div v-if="workerForm.salary_periods && workerForm.salary_periods.length > 0" class="space-y-3">
                                    <div v-for="(period, idx) in workerForm.salary_periods" :key="idx" class="flex flex-wrap md:flex-nowrap gap-2 items-end bg-black/30 p-3 rounded-lg border border-white/5 relative group">
                                        <button type="button" @click="removeSalaryPeriod(idx)" class="absolute -top-2 -right-2 w-5 h-5 bg-neon-red text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs shadow-[0_0_10px_rgba(255,0,0,0.5)]">✕</button>
                                        <div class="flex-1">
                                            <label class="block text-[9px] font-mono text-text-muted mb-1 uppercase">Dan (Oy/Yil)</label>
                                            <input type="month" v-model="period.start_month" class="glass-input text-xs w-full bg-black/50" required>
                                        </div>
                                        <div class="flex-1">
                                            <label class="block text-[9px] font-mono text-text-muted mb-1 uppercase">Gacha (Oy/Yil)</label>
                                            <input type="month" v-model="period.end_month" class="glass-input text-xs w-full bg-black/50" required>
                                        </div>
                                        <div class="flex-1">
                                            <label class="block text-[9px] font-mono text-text-muted mb-1 uppercase">Oylik Miqdori</label>
                                            <input type="number" v-model="period.salary" class="glass-input text-xs w-full bg-black/50" placeholder="Summa" required>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center text-[10px] font-mono text-text-muted p-4 border border-dashed border-white/10 rounded-lg">
                                    O'tgan davrlar kiritilmagan.
                                </div>
                            </div>'''

text = text.replace(ui_old, ui_new)

with open(path, 'w', encoding='utf8') as f:
    f.write(text)
