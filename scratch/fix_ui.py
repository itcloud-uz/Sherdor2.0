import re

path = r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue'

with open(path, 'r', encoding='utf8') as f:
    lines = f.readlines()

new_form = """                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                        <!-- chap ustun -->
                        <div class="space-y-6">
                            <!-- Worker/Machinery Select -->
                            <div v-if="logForm.type === 'worker'">
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('select_worker') }}</label>
                                <select v-model="logForm.worker_id" class="glass-input w-full text-white bg-black/50" required>
                                    <option value="" disabled>-- {{ $t('select') }} --</option>
                                    <option v-for="w in workers.filter(w => w.role !== 'guard')" :key="w.id" :value="w.id">{{ w.name }} ({{ w.brigade ? w.brigade.name : w.role }})</option>
                                </select>
                            </div>
                            <div v-if="logForm.type === 'machinery'" class="space-y-3">
                                <div>
                                    <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Texnika Egasi (Ixtiyoriy)</label>
                                    <select v-model="logForm.machine_owner_id" @change="logForm.machinery_id = ''" class="glass-input w-full text-white bg-black/50">
                                        <option value="">-- Barcha Texnikalar --</option>
                                        <option v-for="o in machineOwners" :key="o.id" :value="o.id">
                                            {{ o.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('select_machinery') }}</label>
                                    <select v-model="logForm.machinery_id" class="glass-input w-full text-white bg-black/50" required>
                                        <option value="" disabled>-- {{ $t('select') }} --</option>
                                        <option v-for="m in filteredMachineries" :key="m.id" :value="m.id">{{ m.name }}</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Ish Hajmi & Obyom & Sana -->
                            <div v-if="logForm.type === 'worker' || logForm.type === 'machinery'">
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ logForm.type === 'machinery' ? 'Ish Hajmi / Qatnov / Soat' : $t('hours_worked') }}</label>
                                <input v-model="logForm.hours_worked" type="number" step="0.5" class="glass-input w-full text-white" placeholder="0.0">
                            </div>
                            <div v-if="logForm.type === 'worker'">
                                <label class="block text-[10px] font-mono text-neon-yellow mb-1 uppercase">Obyom / Hajm</label>
                                <input v-model="logForm.volume_done" type="number" step="0.1" class="glass-input w-full text-white" placeholder="0.0">
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('date') }}</label>
                                <input v-model="logForm.date" type="date" class="glass-input w-full text-white" required>
                            </div>
                        </div>

                        <!-- o'ng ustun -->
                        <div class="space-y-6">
                            <!-- Bino Target -->
                            <div class="space-y-3">
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('building_target') }} ({{ $t('expense_link') }})</label>
                                <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <select v-model="logForm.building_id" class="glass-input w-full text-white bg-black/50" :required="logForm.type === 'worker' || logForm.type === 'machinery'" @change="logForm.floor_id = ''; logForm.unit_id = ''">
                                        <option value="" disabled>-- Bino --</option>
                                        <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.name }}</option>
                                    </select>
                                    <select v-model="logForm.floor_id" :disabled="!logForm.building_id" class="glass-input w-full text-white bg-black/50" @change="logForm.unit_id = ''">
                                        <option value="" disabled selected>-- Qavat --</option>
                                        <option v-for="f in logFloors" :key="f.id" :value="f.id">{{ f.floor_number }}-qavat</option>
                                    </select>
                                    <select v-model="logForm.unit_id" :disabled="!logForm.floor_id" class="glass-input w-full text-white bg-black/50">
                                        <option value="" disabled selected>-- Xonadon --</option>
                                        <option v-for="u in logUnits" :key="u.id" :value="u.id">{{ u.unit_number }}-xonadon</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Maxsus Narx -->
                            <div v-if="logForm.type === 'worker' || logForm.type === 'machinery'">
                                <label class="block text-[10px] font-mono text-neon-magenta mb-1 uppercase">Maxsus Narx (Ixtiyoriy)</label>
                                <input v-model="logForm.custom_rate" type="number" step="0.01" class="glass-input w-full text-white" placeholder="Kelishilgan narx">
                            </div>

                            <!-- Texnika Xarajati (Deductions) -->
                            <div v-if="logForm.type === 'worker' || logForm.type === 'machinery'">
                                <label class="block text-[10px] font-mono text-neon-yellow mb-1 uppercase">
                                    {{ logForm.type === 'machinery' ? 'Texnika Xarajati (Chegirma)' : 'Ushlanma / Jarima' }}
                                </label>
                                <input v-model="logForm.deductions" type="number" step="0.01" class="glass-input w-full text-white border-neon-yellow/50 focus:border-neon-yellow focus:ring-neon-yellow/50" placeholder="0.00">
                            </div>

                            <!-- Izoh -->
                            <div v-if="logForm.type === 'worker' || logForm.type === 'machinery'">
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Izoh (Ixtiyoriy)</label>
                                <input v-model="logForm.description" type="text" class="glass-input w-full text-white" placeholder="Qo'shimcha ma'lumot yoki sabab...">
                            </div>
                        </div>
                    </div>
"""

# In the lines array, 1460 to 1529 (which is lines[1459:1529] inclusive)
lines[1459:1529] = [new_form]

# Also add `deductions: ''` to `logForm` initialization
# and `logForm.value.deductions = ''` to `submitLog` callback
text = "".join(lines)

text = text.replace("description: '',\n    date:", "description: '',\n    deductions: '',\n    date:")
text = text.replace("logForm.value.description = '';\n            logForm.value.date", "logForm.value.description = '';\n            logForm.value.deductions = '';\n            logForm.value.date")

with open(path, 'w', encoding='utf8') as f:
    f.write(text)

print('Done')
