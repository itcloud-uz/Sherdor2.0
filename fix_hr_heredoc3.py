import sys
file_path = 'app/Http/Controllers/Admin/HRController.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace("\['description'] = \->input('description', 'linked_work_id');", "\['description'] = \->input('description');")

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Success')
