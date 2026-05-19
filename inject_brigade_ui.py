with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

# Add Jurnal button
button_html = """
                                    <button v-if="isAdmin" @click.stop="openBrigadeLedger(b)" class="px-2 py-1 flex items-center justify-center bg-neon-cyan/20 text-neon-cyan border border-neon-cyan/50 rounded hover:bg-neon-cyan/40 transition-colors" title="Hisob-kitob Jurnali">
                                        📊 Jurnal
                                    </button>
"""
text = text.replace('title="Tahrirlash">✏️</button>', 'title="Tahrirlash">✏️</button>' + button_html)

# Add Modal
modal_html = """
        <!-- BRIGADE LEDGER MODAL -->
        <Transition name="fade">
            <div v-if="showBrigadeLedgerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
                <div class="bg-dark-surface border border-white/10 rounded-2xl w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl shadow-neon-cyan/10 overflow-hidden relative" @click.stop>
                    <div class="p-6 border-b border-white/5 flex justify-between items-center bg-black/40">
                        <div>
                            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                                📊 Brigada Hisob-kitob Jurnali
                            </h2>
                            <p class="text-xs text-text-muted mt-1 uppercase tracking-widest font-mono">
                                {{ activeBrigadeForLedger?.name }} (Usta: {{ activeBrigadeForLedger?.foreman_name }})
                            </p>
                        </div>
                        <div class="flex gap-3 items-center">
                            <button @click="exportBrigadeLedger" class="px-4 py-2 bg-neon-green/20 text-neon-green text-[10px] font-mono tracking-widest uppercase rounded hover:bg-neon-green/30 transition-colors border border-neon-green/30 flex items-center gap-2">
                                ⬇ Yuklab Olish (Excel)
                            </button>
                            <button @click="showBrigadeLedgerModal = false" class="text-white/50 hover:text-white transition-colors">
                                ✕
                            </button>
                        </div>
                    </div>

                    <div class="p-6 overflow-y-auto flex-1 space-y-6 custom-scrollbar">
                        
                        <!-- Top Summary -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="p-4 bg-black/40 rounded-xl border border-white/5 flex items-center justify-between">
                                <span class="text-xs text-text-muted font-mono uppercase tracking-widest">Joriy Balans:</span>
                                <span class="text-2xl font-bold" :class="brigadeLedgerData.balance > 0 ? 'text-neon-green' : (brigadeLedgerData.balance < 0 ? 'text-neon-red' : 'text-white')">
                                    {{ $currency(brigadeLedgerData.balance) }}
                                </span>
                            </div>
                        </div>

                        <!-- Add Transaction Form -->
                        <form @submit.prevent="saveBrigadeTransaction" class="bg-black/40 p-4 rounded-xl border border-white/5">
                            <h3 class="text-[10px] font-mono text-neon-cyan uppercase tracking-widest mb-3">Yangi Tranzaksiya Qo'shish</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <div class="flex flex-col">
                                    <label class="text-[10px] text-text-muted uppercase tracking-widest mb-1">Tur</label>
                                    <select v-model="brigadeLedgerForm.type" class="glass-input text-xs bg-black/50 text-white" required>
                                        <option value="payment">Pul berildi (To'lov)</option>
                                        <option value="advance_payment">Avans</option>
                                        <option value="penalty">Ushlanma (Jarima)</option>
                                        <option value="salary_accrual">Kirim (Bonus / Haq)</option>
                                    </select>
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[10px] text-text-muted uppercase tracking-widest mb-1">Summa</label>
                                    <input v-model="brigadeLedgerForm.amount" type="number" step="0.01" class="glass-input text-xs" required>
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[10px] text-text-muted uppercase tracking-widest mb-1">Izoh</label>
                                    <input v-model="brigadeLedgerForm.description" type="text" class="glass-input text-xs" placeholder="Qo'shimcha izoh...">
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-[10px] text-text-muted uppercase tracking-widest mb-1">Sana</label>
                                    <input v-model="brigadeLedgerForm.date" type="date" class="glass-input text-xs" required>
                                </div>
                            </div>
                            <div class="flex justify-end mt-3">
                                <button type="submit" class="px-4 py-2 bg-neon-cyan/20 text-neon-cyan text-[10px] font-mono tracking-widest uppercase rounded hover:bg-neon-cyan/30 transition-colors border border-neon-cyan/30">
                                    Saqlash
                                </button>
                            </div>
                        </form>

                        <!-- Ledger Table -->
                        <div class="overflow-x-auto border border-white/5 rounded-xl bg-black/20">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-black/60 text-text-muted text-[10px] font-mono uppercase tracking-widest">
                                        <th class="p-3 border-b border-white/5 font-medium whitespace-nowrap">Sana</th>
                                        <th class="p-3 border-b border-white/5 font-medium">Operatsiya / Izoh</th>
                                        <th class="p-3 border-b border-white/5 font-medium text-right whitespace-nowrap">Summa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!brigadeLedgerData.items || brigadeLedgerData.items.length === 0">
                                        <td colspan="3" class="p-6 text-center text-text-muted text-xs font-mono">Ma'lumot topilmadi</td>
                                    </tr>
                                    <tr v-for="(item, idx) in brigadeLedgerData.items" :key="idx" class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                        <td class="p-3 text-white text-xs whitespace-nowrap">{{ item.date.toLocaleDateString() }}</td>
                                        <td class="p-3 text-white text-xs">{{ item.desc }}</td>
                                        <td class="p-3 text-right text-xs font-mono font-bold whitespace-nowrap" :class="item.amount.startsWith('+') ? 'text-neon-green' : 'text-neon-red'">
                                            {{ item.amount }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
"""

text = text.replace("<!-- OWNER LEDGER MODAL -->", modal_html + "\n        <!-- OWNER LEDGER MODAL -->")

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'w', encoding='utf-8') as f:
    f.write(text)

print('Added Brigade HTML to Index.vue')
