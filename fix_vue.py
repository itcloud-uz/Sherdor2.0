import re

path = r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue'

with open(path, 'r', encoding='utf-8') as f:
    text = f.read()

text = text.replace("{{ isBrigadeEditMode ? 'Saqlash' : 'Qo'shish' }}", "{{ isBrigadeEditMode ? 'Saqlash' : \\\"Qo'shish\\\" }}")
text = text.replace("{{ isWorkerEditMode ? 'Ishchini Tahrirlash' : 'Yangi Ishchi Qo'shish' }}", "{{ isWorkerEditMode ? 'Ishchini Tahrirlash' : \\\"Yangi Ishchi Qo'shish\\\" }}")
text = text.replace("{{ showOwnerForm ? '- Yopish' : '+ Texnika Egasi Qo'shish' }}", "{{ showOwnerForm ? '- Yopish' : \\\"+ Texnika Egasi Qo'shish\\\" }}")
text = text.replace("{{ isOwnerEditMode ? 'Saqlash' : 'Qo'shish' }}", "{{ isOwnerEditMode ? 'Saqlash' : \\\"Qo'shish\\\" }}")
text = text.replace("{{ editMachineryId ? 'Texnikani Tahrirlash' : 'Yangi Texnika Qo'shish' }}", "{{ editMachineryId ? 'Texnikani Tahrirlash' : \\\"Yangi Texnika Qo'shish\\\" }}")
text = text.replace("{{ payroll.status === 'paid' ? 'To'langan' : 'Shakllantirildi (Hisobot)' }}", "{{ payroll.status === 'paid' ? \\\"To'langan\\\" : 'Shakllantirildi (Hisobot)' }}")
text = text.replace("{{ log.status === 'unpaid' ? 'To'lanmagan' : 'To'langan' }}", "{{ log.status === 'unpaid' ? \\\"To'lanmagan\\\" : \\\"To'langan\\\" }}")

with open(path, 'w', encoding='utf-8') as f:
    f.write(text)

print('Fixed remaining vue template errors')
