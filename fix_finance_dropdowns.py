import re

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\Finance\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

# 1. Modify requiresWorker to not overlap with requiresBrigade if they add a brigade category?
# Wait, let's just make sure requiresBrigade ALSO matches "ishchi to'lovi" so both dropdowns show!
# Actually, if we change `requiresBrigade` to match 'ishchi':
# `selectedCategoryName.value.includes('brigada') || selectedCategoryName.value.includes('yollanma') || selectedCategoryName.value.includes('ishchi')`
text = text.replace(
    "selectedCategoryName.value.includes('yollanma')",
    "selectedCategoryName.value.includes('yollanma') || selectedCategoryName.value.includes('ishchi')"
)

# 2. Add the `<select>` for brigade_id.
# Right after worker_id select div.
brigade_select_html = """
                    <div v-if="requiresBrigade">
                        <label class="block text-xs font-mono mb-1 text-neon-cyan">YOKI Brigadani Tanlang (Yollanma)</label>
                        <select v-model="form.brigade_id" class="glass-input w-full text-white bg-black/50 border-neon-cyan/50">
                            <option value="">-- Brigadani Tanlash --</option>
                            <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
"""
# Insert after worker_id select div
worker_div_pattern = r'(<div v-if="requiresWorker">.*?</div>)'
text = re.sub(worker_div_pattern, r'\1' + '\n' + brigade_select_html, text, flags=re.DOTALL)

# 3. Add the `<select>` for machine_owner_id.
# We will insert it right before or after machinery_id select div.
# Also change machinery_id label to indicate it's optional if they are paying the owner.
machine_owner_select_html = """
                    <div v-if="requiresMachineOwner">
                        <label class="block text-xs font-mono mb-1 text-neon-yellow">Texnika Egasini Tanlang (Pul to'lash uchun)</label>
                        <select v-model="form.machine_owner_id" class="glass-input w-full text-white bg-black/50 border-neon-yellow/50">
                            <option value="">-- Egasini Tanlash --</option>
                            <option v-for="o in machineOwners" :key="o.id" :value="o.id">{{ o.name }}</option>
                        </select>
                    </div>
"""
machinery_div_pattern = r'(<div v-if="requiresMachinery">.*?</div>)'
text = re.sub(machinery_div_pattern, machine_owner_select_html + '\n' + r'\1', text, count=1, flags=re.DOTALL)

# Change "Texnikani Tanlang" label to clarify
text = text.replace(
    ">Texnikani Tanlang (Neyron bog'lama)</label>",
    ">(Ixtiyoriy) Aynan qaysi texnika?</label>"
)

# Wait, if both form.worker_id and form.brigade_id are empty, the standard submit will fail if neither is set?
# The standard submit uses `target_id`. If they open the journal, they don't use the standard submit.
# So that's perfectly fine!

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\Finance\Index.vue', 'w', encoding='utf-8') as f:
    f.write(text)

print("Fix applied successfully!")
