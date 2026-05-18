import sys
import re

with open('resources/js/Pages/Admin/HR/Index.vue', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update machinery forms
machinery_driver_find = """                            <div>
                                <label class="block text-[10px] text-text-muted uppercase mb-1">Model / Rusumi</label>"""
machinery_driver_repl = """                            <div>
                                <label class="block text-[10px] text-text-muted uppercase mb-1">Haydovchi Ismi (ixtiyoriy)</label>
                                <input v-if="editMachineryId" v-model="editMachineryForm.driver_name" type="text" class="glass-input text-xs w-full" placeholder="Eshmatov Toshmat">
                                <input v-else v-model="machineryForm.driver_name" type="text" class="glass-input text-xs w-full" placeholder="Eshmatov Toshmat">
                            </div>
                            <div>
                                <label class="block text-[10px] text-text-muted uppercase mb-1">Model / Rusumi</label>"""
if 'Haydovchi Ismi' not in content:
    content = content.replace(machinery_driver_find, machinery_driver_repl)

# 2. Update machinery forms default values
if 'driver_name' not in content[:content.find('machineryForm = ref') + 200]:
    content = re.sub(r'machinery_type:\s*\'\',', r"machinery_type: '', driver_name: '',", content)


# 3. Update Log Form
log_form_find = """                            <div v-if="logForm.type === 'machinery'">
                                <label class="block text-[10px] text-text-muted uppercase mb-1">Qilingan Hajm / Soat (Texnika)</label>
                                <input v-model="logForm.hours_worked" type="number" class="glass-input text-xs w-full" required>
                            </div>"""
log_form_repl = """                            <div v-if="logForm.type === 'machinery'">
                                <label class="block text-[10px] text-text-muted uppercase mb-1">Qilingan Hajm / Soat / Reys</label>
                                <input v-model="logForm.hours_worked" type="number" class="glass-input text-xs w-full" required>
                            </div>

                            <div class="col-span-1 sm:col-span-2 border-t border-white/10 pt-3 mt-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] text-text-muted uppercase mb-1 text-neon-cyan">Maxsus Narx (Birlik uchun) ixtiyoriy</label>
                                    <input v-model="logForm.custom_rate" type="number" class="glass-input text-xs w-full border-neon-cyan/30" placeholder="Standart narxni o'zgartirish">
                                </div>
                                <div>
                                    <label class="block text-[10px] text-text-muted uppercase mb-1 text-neon-red">Ushlanmalar (Yoqilg'i / Avans)</label>
                                    <input v-model="logForm.deductions" type="number" class="glass-input text-xs w-full border-neon-red/30" placeholder="Chegirma summa">
                                </div>
                            </div>"""
if 'Ushlanmalar' not in content:
    content = content.replace(log_form_find, log_form_repl)

# 4. Add fields to logForm init
if 'custom_rate: null' not in content:
    content = content.replace("measurement_unit: 'kun',", "measurement_unit: 'kun', custom_rate: null, deductions: null,")
if 'custom_rate: null' not in content:
    content = content.replace("measurement_unit: 'soat',", "measurement_unit: 'soat', custom_rate: null, deductions: null,")
    
# Update edit logForm init
if 'l.deductions' not in content:
    content = re.sub(
        r'work_category:\s*l\.work_category',
        r"work_category: l.work_category, custom_rate: l.rate, deductions: l.deductions",
        content
    )


with open('resources/js/Pages/Admin/HR/Index.vue', 'w', encoding='utf-8') as f:
    f.write(content)

print("SUCCESS")
