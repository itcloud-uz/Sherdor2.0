import sys
file_path = 'resources/js/Pages/Admin/CRM/Index.vue'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace("sheet[cell].z = '# ##0';", "sheet[cell].z = '#,##0';")

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
