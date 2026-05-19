import re

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

# exact text of the button block we added multiple times
button_text = """                                    <button v-if="isAdmin" @click.stop="openBrigadeLedger(b)" class="px-2 py-1 flex items-center justify-center bg-neon-cyan/20 text-neon-cyan border border-neon-cyan/50 rounded hover:bg-neon-cyan/40 transition-colors" title="Hisob-kitob Jurnali">
                                        📊 Jurnal
                                    </button>"""

# Find all occurrences
count = text.count(button_text)
print(f"Found {count} occurrences")

# We will just remove ALL of them:
text = text.replace(button_text, '')

# And then we carefully add it ONLY where it actually belongs:
# It belongs after openBrigadeForm(b) button in the Brigades section.

target_button = """<button v-if="isAdmin" @click.stop="openBrigadeForm(b)" class="w-6 h-6 flex items-center justify-center bg-white/5 text-white rounded hover:bg-white/20 transition-colors" title="Tahrirlash">✏️</button>"""

if target_button in text:
    text = text.replace(target_button, target_button + '\n' + button_text)
    print("Added the correct button back")

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'w', encoding='utf-8') as f:
    f.write(text)

print("Done")
