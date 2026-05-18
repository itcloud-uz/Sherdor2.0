import sys

with open('resources/js/Pages/Admin/Finance/Index.vue', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Add selectedTransferContract computed
computed_find = "const selectedContract = computed(() =>"
computed_repl = """const selectedTransferContract = computed(() => {
    if (!transferForm.contract_id) return null;
    return props.contracts.find(c => c.id === transferForm.contract_id);
});

const selectedContract = computed(() =>"""
if 'selectedTransferContract' not in content:
    content = content.replace(computed_find, computed_repl)

# 2. Add UI for selectedTransferContract in transferForm
ui_find = """                            <p class="text-[9px] text-text-muted mt-1">Agar mijoz tanlansa, kassadan yechilgan pul (99 mln) mijozning hisobidan (qaytim sifatida) yechiladi va balans to'g'rilanadi.</p>
                        </div>"""

ui_repl = """                            <p class="text-[9px] text-text-muted mt-1">Agar mijoz tanlansa, kassadan yechilgan pul (99 mln) mijozning hisobidan (qaytim sifatida) yechiladi va balans to'g'rilanadi.</p>
                            
                            <!-- Mijoz Ma'lumotlari Jadvalli -->
                            <div v-if="selectedTransferContract" class="mt-3 p-3 bg-neon-cyan/5 border border-neon-cyan/20 rounded-lg overflow-x-auto">
                                <table class="w-full text-left text-xs text-white">
                                    <thead>
                                        <tr class="text-text-muted border-b border-white/10">
                                            <th class="pb-2">Shartnoma Summasi</th>
                                            <th class="pb-2">Qilingan To'lov</th>
                                            <th class="pb-2">Qoldiq / Qarz</th>
                                            <th class="pb-2">Ortiqcha To'lov (Qaytim)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="pt-2">
                                            <td class="pt-2 font-mono">{{ formatCurrency(selectedTransferContract.total_amount) }}</td>
                                            <td class="pt-2 font-mono text-neon-green">{{ formatCurrency(selectedTransferContract.paid_amount) }}</td>
                                            <td class="pt-2 font-mono" :class="selectedTransferContract.balance > 0 ? 'text-neon-red' : ''">{{ formatCurrency(selectedTransferContract.balance > 0 ? selectedTransferContract.balance : 0) }}</td>
                                            <td class="pt-2 font-mono font-bold text-neon-cyan">
                                                <span v-if="selectedTransferContract.balance < 0">{{ formatCurrency(Math.abs(selectedTransferContract.balance)) }}</span>
                                                <span v-else>0 UZS</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>"""

if 'Ortiqcha To' not in content:
    content = content.replace(ui_find, ui_repl)


with open('resources/js/Pages/Admin/Finance/Index.vue', 'w', encoding='utf-8') as f:
    f.write(content)

print("SUCCESS")
