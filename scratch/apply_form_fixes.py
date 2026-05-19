import re

path = r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue'
with open(path, 'r', encoding='utf8') as f:
    text = f.read()

# Replace Texnikani Tanlang
old_pattern1 = r'<div v-if="logForm\.type === \'machinery\'">.*?<label class="block.*?Texnikani Tanlang</label>.*?<select v-model="logForm\.machinery_id".*?</select>\s*</div>'

new_dropdown = '''<div v-if="logForm.type === 'machinery'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
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

text = re.sub(old_pattern1, new_dropdown, text, flags=re.DOTALL)

# Replace Ishlagan Soati
old_pattern2 = r'<label class="block text-xs font-mono text-text-muted mb-2 uppercase tracking-widest">Ishlagan Soati</label>'
new_label = '<label class="block text-[10px] font-mono text-text-muted mb-2 uppercase tracking-widest">Ish Hajmi / Qatnov / Soat</label>'
text = text.replace(old_pattern2, new_label)

with open(path, 'w', encoding='utf8') as f:
    f.write(text)

print('Done')
