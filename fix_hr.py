
import sys

file_path = 'app/Http/Controllers/Admin/HRController.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

orig = '''            'transactions' => function() {
                ->select('id', 'brigade_id', 'type', 'amount', 'date', 'created_at', 'description');
            },'''
new = '''            'transactions' => function() {
                ->select('id', 'brigade_id', 'type', 'amount', 'date', 'created_at', 'description', 'linked_work_id');
            },'''
content = content.replace(orig, new)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print('Success')

