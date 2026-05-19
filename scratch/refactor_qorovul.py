import re

path = r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue'
with open(path, 'r', encoding='utf8') as f:
    text = f.read()

# Fix grid cols back to 2, since we are moving Qorovullar below it
text = text.replace('class="grid grid-cols-1 xl:grid-cols-3 sm:grid-cols-2 gap-8 animate-[fadeIn_0.5s_ease-out]"', 'class="grid grid-cols-1 xl:grid-cols-2 sm:grid-cols-2 gap-8 animate-[fadeIn_0.5s_ease-out]"')

old_qorovullar_block_regex = re.compile(r'<!-- Qorovullar -->.*?</div>\s*</div>\s*</div>\s*</div>\s*</div>', re.DOTALL)

new_qorovullar_block = '''<!-- Qorovullar (Table View) -->
            <div class="glass-panel p-6 rounded-2xl border border-white/5 flex flex-col mt-8 animate-[fadeIn_0.6s_ease-out]">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-display font-bold text-white flex items-center gap-2">
                        🛡️ Qorovullar
                    </h2>
                    <button @click="openWorkerEdit({ role: 'guard', salary_type: 'fixed_monthly' })" class="px-4 py-2 bg-neon-cyan/20 border border-neon-cyan text-neon-cyan text-[10px] font-mono font-bold uppercase tracking-widest rounded hover:bg-neon-cyan hover:text-black transition-all shadow-[0_0_10px_rgba(0,240,255,0.2)]">
                        + Qorovul
                    </button>
                </div>
                
                <div class="overflow-x-auto rounded-xl border border-white/10 bg-white/5">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="text-neon-cyan bg-black/50 border-b border-white/10">
                            <tr>
                                <th class="p-4 font-normal uppercase tracking-widest">#</th>
                                <th class="p-4 font-normal uppercase tracking-widest">F.I.SH</th>
                                <th class="p-4 font-normal uppercase tracking-widest">Telefon</th>
                                <th class="p-4 font-normal uppercase tracking-widest">Oylik Maoshi</th>
                                <th class="p-4 font-normal uppercase tracking-widest text-right">Harakatlar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="(g, index) in workers.filter(w => w.role === 'guard')" :key="g.id" class="hover:bg-white/5 transition-colors">
                                <td class="p-4 text-text-muted">{{ index + 1 }}</td>
                                <td class="p-4 text-white font-bold flex items-center gap-3">
                                    <div class="w-8 h-8 bg-white/5 rounded-full flex items-center justify-center shrink-0">🛡️</div>
                                    {{ g.name }}
                                </td>
                                <td class="p-4 text-text-muted">{{ g.phone || '-' }}</td>
                                <td class="p-4 text-neon-cyan font-bold">{{ $currency(g.base_rate) }} so'm</td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button @click="viewGuardHistory(g)" class="px-3 py-1 bg-white/5 text-neon-cyan border border-white/10 rounded hover:bg-white/20 transition-colors uppercase tracking-widest text-[10px]">Maosh Tarixi</button>
                                        <button @click="openWorkerEdit(g)" class="w-7 h-7 rounded bg-white/5 flex items-center justify-center hover:bg-white/20 transition-colors text-white" title="Tahrirlash">✏️</button>
                                        <button v-if="isAdmin" @click="deleteWorker(g.id)" class="w-7 h-7 rounded bg-neon-red/10 flex items-center justify-center hover:bg-neon-red hover:text-white transition-colors text-neon-red" title="O'chirish">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="workers.filter(w => w.role === 'guard').length === 0">
                                <td colspan="5" class="p-8 text-center text-text-muted">Hech qanday qorovul topilmadi.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>'''

text = old_qorovullar_block_regex.sub(new_qorovullar_block, text)

with open(path, 'w', encoding='utf8') as f:
    f.write(text)
