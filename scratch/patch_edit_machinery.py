import sys
import re

with open('resources/js/Pages/Admin/HR/Index.vue', 'r', encoding='utf-8') as f:
    content = f.read()

# Fix useForm
useform_find = "const editMachineryForm = useForm({ name: '', owner_name: '', machinery_type: '', driver_name: '', payment_type: 'hourly', license_plate: '', cost_per_hour: '' });"
useform_repl = "const editMachineryForm = useForm({ name: '', machine_owner_id: '', machinery_type: '', driver_name: '', payment_type: 'hourly', license_plate: '', cost_per_hour: '' });"
if useform_find in content:
    content = content.replace(useform_find, useform_repl)

# Fix startEditMachinery
start_edit_find = """    editMachineryForm.name = m.name;
    editMachineryForm.owner_name = m.owner_name;
    editMachineryForm.machinery_type = m.machinery_type;
    editMachineryForm.payment_type = m.payment_type;
    editMachineryForm.license_plate = m.license_plate;
    editMachineryForm.cost_per_hour = m.cost_per_hour;"""
start_edit_repl = """    editMachineryForm.name = m.name;
    editMachineryForm.machine_owner_id = m.machine_owner_id;
    editMachineryForm.machinery_type = m.machinery_type;
    editMachineryForm.driver_name = m.driver_name;
    editMachineryForm.payment_type = m.payment_type;
    editMachineryForm.license_plate = m.license_plate;
    editMachineryForm.cost_per_hour = m.cost_per_hour;"""
if 'editMachineryForm.machine_owner_id = m.machine_owner_id' not in content:
    content = content.replace(start_edit_find, start_edit_repl)

with open('resources/js/Pages/Admin/HR/Index.vue', 'w', encoding='utf-8') as f:
    f.write(content)

print("SUCCESS")
