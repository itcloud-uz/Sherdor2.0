import re

path = r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue'
with open(path, 'r', encoding='utf8') as f:
    text = f.read()

# 1. Update logForm
text = text.replace(
    "const logForm = ref({\n    type: 'worker',\n    worker_id: '',\n    machinery_id: '',",
    "const logForm = ref({\n    type: 'worker',\n    worker_id: '',\n    machine_owner_id: '',\n    machinery_id: '',"
)

# 2. Add computed filteredMachineries
computed_code = '''const filteredMachineries = computed(() => {
    if (!logForm.value.machine_owner_id) return props.machineries;
    return props.machineries.filter(m => m.machine_owner_id === parseInt(logForm.value.machine_owner_id));
});'''
text = text.replace('const isWorkerEditMode = ref(false);', computed_code + '\nconst isWorkerEditMode = ref(false);')

# 3. Update the Template for logForm.type === 'machinery'
# Add Texnika Egasi dropdown
old_dropdown_machinery = '''<div v-if="logForm.type === 'machinery'">
                              <label class="block text-xs font-mono text-text-muted mb-2 uppercase tracking-widest">Texnikani Tanlang</label>
                              <select v-model="logForm.machinery_id" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan/50 appearance-none transition-all" required>
                                  <option value="">-- Tanlash --</option>
                                  <option v-for="m in machineries" :key="m.id" :value="m.id">
                                      {{ m.name }}
                                  </option>
                              </select>
                          </div>'''

new_dropdown_machinery = '''<div v-if="logForm.type === 'machinery'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                              <div>
                                  <label class="block text-xs font-mono text-text-muted mb-2 uppercase tracking-widest">Texnika Egasi (Ixtiyoriy)</label>
                                  <select v-model="logForm.machine_owner_id" @change="logForm.machinery_id = ''" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan/50 appearance-none transition-all">
                                      <option value="">-- Barcha Texnikalar --</option>
                                      <option v-for="o in machineOwners" :key="o.id" :value="o.id">
                                          {{ o.name }}
                                      </option>
                                  </select>
                              </div>
                              <div>
                                  <label class="block text-xs font-mono text-text-muted mb-2 uppercase tracking-widest">Texnikani Tanlang</label>
                                  <select v-model="logForm.machinery_id" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-neon-cyan focus:ring-1 focus:ring-neon-cyan/50 appearance-none transition-all" required>
                                      <option value="">-- Tanlash --</option>
                                      <option v-for="m in filteredMachineries" :key="m.id" :value="m.id">
                                          {{ m.name }}
                                      </option>
                                  </select>
                              </div>
                          </div>'''
text = text.replace(old_dropdown_machinery, new_dropdown_machinery)

# 4. Change "ISHLAGAN SOATI" to "ISH HAJMI / QATNOV / SOAT"
text = text.replace(
    '''<label class="block text-xs font-mono text-text-muted mb-2 uppercase tracking-widest">Ishlagan Soati</label>''',
    '''<label class="block text-[10px] font-mono text-text-muted mb-2 uppercase tracking-widest">Ish Hajmi / Qatnov / Soat</label>'''
)


# 5. Move Qorovullar back into the grid
# The grid ends with `</div>\n\n        <!-- TAB 1: FACE-ID HUB -->`
# Wait, currently it looks like:
#          </div>
#
#          <!-- Qorovullar (Table View) -->
#          <div class="glass-panel ...
#          ...
#          </div>
#
#      <!-- TAB 1: FACE-ID HUB -->

qorovullar_regex = re.compile(r'(\s*</div>\s*)(<!-- Qorovullar \(Table View\) -->.*?\s*</div>\s*</div>\s*)(<!-- TAB 1: FACE-ID HUB -->)', re.DOTALL)
match = qorovullar_regex.search(text)
if match:
    # We want Qorovullar inside the grid.
    # We need to change grid back to grid-cols-1 xl:grid-cols-3 sm:grid-cols-2
    text = text.replace('class="grid grid-cols-1 xl:grid-cols-2 sm:grid-cols-2 gap-8 animate-[fadeIn_0.5s_ease-out]"', 'class="grid grid-cols-1 xl:grid-cols-3 sm:grid-cols-2 gap-8 animate-[fadeIn_0.5s_ease-out]"')
    
    # We need to rewrite Qorovullar back to a card layout
    new_qorovullar_card_layout = '''
                  <!-- Qorovullar -->
                  <div class="glass-panel p-6 rounded-2xl border border-white/5 flex flex-col h-[700px]">
                      <div class="flex justify-between items-center mb-6">
                          <h2 class="text-xl font-display font-bold text-white flex items-center gap-2">
                              🛡️ Qorovullar
                          </h2>
                          <button @click="openWorkerEdit({ role: 'guard', salary_type: 'fixed_monthly' })" class="px-4 py-2 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan text-[10px] font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-cyan hover:text-black transition-all shadow-[0_0_10px_rgba(0,240,255,0.2)]">
                              + Qorovul
                          </button>
                      </div>
                      
                      <div class="overflow-y-auto custom-scrollbar flex-1 relative pr-2">
                          <div class="grid grid-cols-1 gap-3">
                              <div v-for="g in workers.filter(w => w.role === 'guard')" :key="g.id" class="p-4 bg-black/40 border border-white/5 rounded-2xl flex flex-col justify-between hover:border-white/20 transition-all group shadow-[0_4px_20px_rgba(0,0,0,0.5)]">
                                  <div class="flex items-start gap-3 mb-3">
                                      <div class="w-10 h-10 rounded-xl bg-neon-cyan/10 flex items-center justify-center shrink-0 border border-neon-cyan/20 group-hover:border-neon-cyan/50 transition-colors">
                                          <span class="text-lg">🛡️</span>
                                      </div>
                                      <div class="flex-1 min-w-0">
                                          <h3 class="text-white font-bold truncate">{{ g.name }}</h3>
                                          <p class="text-text-muted text-[10px] font-mono uppercase mt-1">{{ g.phone || '-' }}</p>
                                      </div>
                                  </div>
                                  
                                  <div class="flex justify-between items-center pt-3 border-t border-white/5 mt-auto">
                                      <div class="text-neon-cyan text-xs font-mono font-bold">{{ $currency(g.base_rate) }} so'm</div>
                                      <div class="flex gap-2">
                                          <button @click="viewGuardHistory(g)" class="w-7 h-7 rounded bg-white/5 flex items-center justify-center hover:bg-white/20 transition-colors text-neon-cyan" title="Maosh Tarixi">📅</button>
                                          <button @click="openWorkerEdit(g)" class="w-7 h-7 rounded bg-white/5 flex items-center justify-center hover:bg-white/20 transition-colors text-white" title="Tahrirlash">✏️</button>
                                          <button v-if="isAdmin" @click="deleteWorker(g.id)" class="w-7 h-7 rounded bg-neon-red/10 flex items-center justify-center hover:bg-neon-red hover:text-white transition-colors text-neon-red" title="O'chirish">🗑️</button>
                                      </div>
                                  </div>
                              </div>
                              <div v-if="workers.filter(w => w.role === 'guard').length === 0" class="text-center p-8 border border-dashed border-white/10 rounded-2xl text-text-muted text-sm font-mono">
                                  Qorovullar yo'q
                              </div>
                          </div>
                      </div>
                  </div>
          </div>
          
          <!-- TAB 1: FACE-ID HUB -->'''
    
    # We replace from the closing div of the grid, down to TAB 1
    # with the new Qorovullar block (which includes the closing div of the grid at the end)
    text = qorovullar_regex.sub(new_qorovullar_card_layout, text)


with open(path, 'w', encoding='utf8') as f:
    f.write(text)
