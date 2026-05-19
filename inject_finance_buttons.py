import re

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\Finance\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

# For the Machine Owner button
machine_owner_button = """
                    <div v-if="requiresMachineOwner && form.machine_owner_id" class="p-6 bg-neon-yellow/10 border border-neon-yellow/30 rounded-xl flex items-center justify-between mt-4">
                        <div>
                            <h4 class="text-neon-yellow font-bold uppercase tracking-widest text-sm">Texnika Egasi Jurnali</h4>
                            <p class="text-text-muted text-[10px] font-mono mt-1">Bu toifa bo'yicha to'lovlarni to'g'ridan-to'g'ri maxsus jurnal orqali amalga oshiring.</p>
                        </div>
                        <button type="button" @click.prevent="openOwnerLedgerFromFinance(form.machine_owner_id)" class="px-6 py-2 bg-neon-yellow text-black font-bold uppercase tracking-widest text-[10px] rounded hover:bg-white hover:text-black transition-colors shadow-[0_0_15px_rgba(252,238,9,0.3)]">Jurnalni Ochiq</button>
                    </div>
"""

# For the Brigade button
brigade_button = """
                    <div v-if="requiresBrigade && form.brigade_id" class="p-6 bg-neon-cyan/10 border border-neon-cyan/30 rounded-xl flex items-center justify-between mt-4">
                        <div>
                            <h4 class="text-neon-cyan font-bold uppercase tracking-widest text-sm">Brigada Jurnali</h4>
                            <p class="text-text-muted text-[10px] font-mono mt-1">Bu toifa bo'yicha to'lovlarni to'g'ridan-to'g'ri maxsus jurnal orqali amalga oshiring.</p>
                        </div>
                        <button type="button" @click.prevent="openBrigadeLedgerFromFinance(form.brigade_id)" class="px-6 py-2 bg-neon-cyan text-black font-bold uppercase tracking-widest text-[10px] rounded hover:bg-white hover:text-black transition-colors shadow-[0_0_15px_rgba(0,255,255,0.3)]">Jurnalni Ochiq</button>
                    </div>
"""

# Insert the machine owner button right after the machine owner dropdown block
text = re.sub(
    r'(<div v-if="requiresMachineOwner">.*?Egasini Tanlash.*?</select>\s*</div>)',
    r'\1' + '\n' + machine_owner_button,
    text,
    flags=re.DOTALL
)

# Insert the brigade button right after the brigade dropdown block
text = re.sub(
    r'(<div v-if="requiresBrigade">.*?Brigadani Tanlash.*?</select>\s*</div>)',
    r'\1' + '\n' + brigade_button,
    text,
    flags=re.DOTALL
)

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\Finance\Index.vue', 'w', encoding='utf-8') as f:
    f.write(text)

print("Buttons successfully injected!")
