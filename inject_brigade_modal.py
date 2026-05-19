import re

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

modal_html = """
        <!-- Brigade Ledger Modal (Excel Layout) -->
        <div v-if="showBrigadeLedgerModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showBrigadeLedgerModal = false"></div>
            <div class="relative bg-[#a1c4fd] text-black border-2 border-black p-4 rounded max-w-7xl w-full max-h-[90vh] overflow-y-auto font-sans shadow-2xl animate-[fadeIn_0.3s_ease-out]">
                <div class="flex justify-between items-center mb-4 relative">
                    <button @click="window.location.href = route('admin.hr.brigade.ledger.export', activeBrigadeForLedger?.id)" class="absolute left-4 top-4 text-sm bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 flex items-center gap-2 shadow font-bold">
                        ⬇ Yuklab Olish (Excel)
                    </button>
                    <h2 class="text-2xl font-bold text-center w-full uppercase tracking-wider">{{ activeBrigadeForLedger?.name }} - Jurnal</h2>
                    <button @click="showBrigadeLedgerModal = false" class="absolute right-4 top-4 text-red-600 hover:text-red-800 font-bold text-xl">✖ </button>
                </div>
                
                <div class="overflow-x-auto border-2 border-black bg-white">
                    <table class="w-full text-left text-sm border-collapse font-medium">
                        <thead class="bg-[#dcdcdc] border-b-2 border-black sticky top-0 uppercase text-xs">
                            <tr>
                                <th class="p-2 border-r border-black w-24">Sana</th>
                                <th class="p-2 border-r border-black">Asos / Izoh</th>
                                <th class="p-2 border-r border-black w-32 text-right">Summa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-400">
                            <tr v-for="(item, idx) in brigadeLedgerData.items" :key="idx" class="hover:bg-blue-50 transition-colors">
                                <td class="p-2 border-r border-black">{{ item.date.toISOString().split('T')[0] }}</td>
                                <td class="p-2 border-r border-black">{{ item.desc }}</td>
                                <td class="p-2 border-r border-black text-right font-bold" :class="item.amount.startsWith('+') ? 'text-green-700' : 'text-red-700'">
                                    {{ item.amount }}
                                </td>
                            </tr>
                            <tr v-if="brigadeLedgerData.items.length === 0">
                                <td colspan="3" class="p-4 text-center text-gray-500 italic">Ma'lumot topilmadi</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-[#c2e59c] border-t-2 border-black font-bold">
                            <tr>
                                <td colspan="2" class="p-2 border-r border-black text-right uppercase tracking-wider">Joriy Qoldiq:</td>
                                <td class="p-2 text-right text-lg" :class="brigadeLedgerData.balance < 0 ? 'text-red-700' : 'text-green-800'">
                                    {{ Number(brigadeLedgerData.balance).toLocaleString() }} so'm
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-6 border-2 border-black p-4 bg-white/50 relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, #000 10px, #000 11px);"></div>
                    <h3 class="text-sm font-bold uppercase tracking-wider mb-4 border-b-2 border-black inline-block relative z-10">Kassa amaliyoti / Hisob-kitob</h3>
                    <form @submit.prevent="submitBrigadeLedger" class="flex flex-wrap gap-4 items-end relative z-10">
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest font-bold mb-1">Turi</label>
                            <select v-model="brigadeLedgerForm.type" class="bg-white border border-black p-1 text-sm font-bold outline-none" required>
                                <option value="payment">To'lov / Avans berish</option>
                                <option value="salary_accrual">Ish haqi hisoblash (+)</option>
                                <option value="penalty">Jarima / Ushlanma (-)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest font-bold mb-1">Summa (so'm)</label>
                            <input v-model="brigadeLedgerForm.amount" type="number" step="1000" class="bg-white border border-black p-1 text-sm font-bold outline-none" required placeholder="0" />
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest font-bold mb-1">Sana</label>
                            <input v-model="brigadeLedgerForm.date" type="date" class="bg-white border border-black p-1 text-sm font-bold outline-none" required />
                        </div>
                        <div class="flex-grow">
                            <label class="block text-[10px] uppercase tracking-widest font-bold mb-1">Izoh / Maqsad</label>
                            <input v-model="brigadeLedgerForm.description" type="text" class="w-full bg-white border border-black p-1 text-sm outline-none" placeholder="Masalan: Obyekt bo'yicha to'lov" />
                        </div>
                        <button type="submit" class="bg-black text-white px-4 py-1.5 uppercase tracking-widest font-bold text-sm border-2 border-transparent hover:bg-white hover:text-black hover:border-black transition-all">Yozish</button>
                    </form>
                </div>
            </div>
        </div>
"""

# Find the exact marker to insert before
target = "        <!-- Owner Ledger Modal (Excel Layout) -->"

if target in text:
    text = text.replace(target, modal_html + "\n" + target)
    with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'w', encoding='utf-8') as f:
        f.write(text)
    print("Modal successfully injected!")
else:
    print("Target not found.")
