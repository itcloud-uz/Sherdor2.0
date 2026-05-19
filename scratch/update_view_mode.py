import re

path = r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue'
with open(path, 'r', encoding='utf8') as f:
    text = f.read()

# Replace inputs in edit mode (happens 2 times)
old_input = '''<input v-model="editLogForm.custom_rate" type="number" step="0.01" class="glass-input text-xs w-24" placeholder="Maxsus narx">
                            <input v-model="editLogForm.date" type="date" class="glass-input text-xs w-28">'''

new_input = '''<input v-model="editLogForm.custom_rate" type="number" step="0.01" class="glass-input text-xs w-24" placeholder="Maxsus narx">
                            <input v-model="editLogForm.description" type="text" class="glass-input text-xs w-32" placeholder="Izoh">
                            <input v-model="editLogForm.date" type="date" class="glass-input text-xs w-28">'''

text = text.replace(old_input, new_input)

# Replace display in view mode (happens 2 times)
old_display1 = '''<p class="text-[10px] font-mono text-text-muted tracking-widest uppercase">
                                    Obyekt: {{ log.building?.name || 'Noma\\'lum' }}
                                </p>'''

new_display1 = '''<p class="text-[10px] font-mono text-text-muted tracking-widest uppercase">
                                    Obyekt: {{ log.building?.name || 'Noma\\'lum' }}
                                    <span v-if="log.description" class="text-neon-cyan block mt-1">Izoh: {{ log.description }}</span>
                                </p>'''

text = text.replace(old_display1, new_display1)

with open(path, 'w', encoding='utf8') as f:
    f.write(text)

print('Done')
