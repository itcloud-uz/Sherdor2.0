import re

path = 'resources/js/Pages/Admin/HR/Index.vue'
with open(path, 'r', encoding='utf8') as f:
    content = f.read()

# 1. Add column to Tab 0
content = content.replace('class="grid grid-cols-1 xl:grid-cols-1 sm:grid-cols-2 gap-8 animate-[fadeIn_0.5s_ease-out]"', 'class="grid grid-cols-1 xl:grid-cols-3 sm:grid-cols-2 gap-8 animate-[fadeIn_0.5s_ease-out]"')

guard_block = '''
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
                                <div class="w-10 h-10 bg-white/5 rounded-full flex items-center justify-center overflow-hidden border border-white/10 shrink-0">
                                    <span class="text-lg">🛡️</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-sm font-bold text-white truncate">{{ g.name }}</h3>
                                    <p class="text-[10px] font-mono text-text-muted mt-1 uppercase">Oylik: {{ $currency(g.base_rate) }} so'm</p>
                                </div>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t border-white/5 mt-auto">
                                <button @click="viewGuardHistory(g)" class="text-[10px] font-mono text-neon-cyan hover:text-white transition-colors uppercase tracking-widest">Maosh Tarixi</button>
                                <div class="flex gap-2">
                                    <button @click="openWorkerEdit(g)" class="w-7 h-7 rounded-full bg-white/5 flex items-center justify-center hover:bg-white/20 transition-colors text-xs text-white" title="Tahrirlash">✏️</button>
                                    <button v-if="isAdmin" @click="deleteWorker(g.id)" class="w-7 h-7 rounded-full bg-neon-red/10 flex items-center justify-center hover:bg-neon-red hover:text-white transition-colors text-xs text-neon-red" title="O'chirish">🗑️</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
'''
content = content.replace('<!-- TAB 1: FACE-ID HUB -->', guard_block + '\n        </div>\n\n        <!-- TAB 1: FACE-ID HUB -->')

# Let's fix the extra </div>
content = content.replace('</div>\n          </div>\n\n        <!-- TAB 1: FACE-ID HUB -->', '</div>\n\n        <!-- TAB 1: FACE-ID HUB -->')

with open(path, 'w', encoding='utf8') as f:
    f.write(content)
