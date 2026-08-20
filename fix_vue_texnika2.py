import sys

file_path = 'resources/js/Pages/Admin/HR/Texnikalar.vue'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

target = '''                    alignment: {
                        vertical: 'center',
                        horizontal: C === 0 || C === 1 || C === 4 ? 'center' : (C === 5 ? 'right' : 'left')
                    }
                };
                if (C === 5 && typeof ws[cellAddress].v === 'number') {
                    ws[cellAddress].z = '#,##0';
                }'''

replacement = '''                    alignment: {
                        vertical: 'center',
                        horizontal: C === 0 || C === 1 || C === 4 || C === 5 ? 'center' : (C === 6 ? 'right' : 'left')
                    }
                };
                if (C === 6 && typeof ws[cellAddress].v === 'number') {
                    ws[cellAddress].z = '#,##0';
                }'''

content = content.replace(target, replacement)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Success Texnikalar alignment')
