import sys
import re

with open('resources/js/Pages/Admin/Finance/Index.vue', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update form definition
if "brigade_id: ''," not in content:
    content = content.replace("worker_id: '',", "worker_id: '',\n    brigade_id: '',")
if "machine_owner_id: ''," not in content:
    content = content.replace("machinery_id: '',", "machinery_id: '',\n    machine_owner_id: '',")

# 2. Add computed properties for Brigade and MachineOwner
new_computed = """const requiresBrigade = computed(() => selectedCategoryName.value.includes('brigada') || selectedCategoryName.value.includes('yollanma'));
const requiresMachineOwner = computed(() => selectedCategoryName.value.includes('texnika') || selectedCategoryName.value.includes('arendator'));"""
if "requiresBrigade" not in content:
    content = content.replace("const requiresWorker =", new_computed + "\nconst requiresWorker =")

# 3. Update target_type logic
target_logic = """    } else if (requiresMachineOwner.value) {
        form.value.target_type = 'machine_owner';
        form.value.target_id = form.value.machine_owner_id;
    } else if (requiresBrigade.value) {
        form.value.target_type = 'brigade';
        form.value.target_id = form.value.brigade_id;
    } else if (requiresWorker.value) {"""
if "requiresBrigade.value" not in content:
    content = content.replace("} else if (requiresWorker.value) {", target_logic)

# 4. Inject Props
if "brigades: Array," not in content:
    content = content.replace("fixedAssets: Array", "fixedAssets: Array,\n    brigades: Array,\n    machineOwners: Array")

# 5. Inject HTML Templates
html_brigade = """
                    <div v-if="requiresBrigade">
                        <label class="block text-xs font-mono text-text-muted mb-1">Brigadani Tanlang</label>
                        <select v-model="form.brigade_id" class="glass-input w-full text-white bg-black/50" required>
                            <option value="" disabled>-- Tanlang --</option>
                            <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }} (Usta: {{ b.foreman_name }}) - Balans: {{ $currency(b.balance) }}</option>
                        </select>
                    </div>
"""
if "requiresBrigade" not in content and 'v-model="form.brigade_id"' not in content:
    content = content.replace('<div v-if="requiresWorker">', html_brigade + '\n                    <div v-if="requiresWorker">')

html_machine_owner = """
                    <div v-if="requiresMachineOwner">
                        <label class="block text-xs font-mono text-text-muted mb-1">Texnika Egasini Tanlang</label>
                        <select v-model="form.machine_owner_id" class="glass-input w-full text-white bg-black/50" required>
                            <option value="" disabled>-- Tanlang --</option>
                            <option v-for="o in machineOwners" :key="o.id" :value="o.id">{{ o.name }} - Balans: {{ $currency(o.balance) }}</option>
                        </select>
                    </div>
"""
if "requiresMachineOwner" not in content and 'v-model="form.machine_owner_id"' not in content:
    content = content.replace('<div v-if="requiresMachinery">', html_machine_owner + '\n                    <div v-if="requiresMachinery">')


# Fix form reset
if "target_type: null, target_id: null" in content and "brigade_id" not in content:
    reset_code = "form.value = { type: 'income', category_id: '', category_name: '', amount: '', currency: 'UZS', payment_method: 'cash', account_type: 'main', contract_id: '', client_id: '', worker_id: '', brigade_id: '', partner_id: '', machinery_id: '', machine_owner_id: '', building_id: '', supplier_id: '', fixed_asset_id: '', transaction_date: new Date().toLocaleString('sv').replace(' ', 'T').slice(0,16), attachment: null, auto_approve: true, notes: '', latitude: null, longitude: null, source: 'admin_panel', target_type: null, target_id: null };"
    content = re.sub(r'form\.value\s*=\s*{.*?source:.*?};', reset_code, content, count=1, flags=re.DOTALL)


with open('resources/js/Pages/Admin/Finance/Index.vue', 'w', encoding='utf-8') as f:
    f.write(content)

print("SUCCESS")
