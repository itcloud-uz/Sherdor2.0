import sys
import glob
import re

files = glob.glob('app/Http/Controllers/**/*.php', recursive=True)
for file_path in files:
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # We want to find places where 'transactions' relation is selected in eager loading.
    # Like ->select('id', 'brigade_id', 'type', 'amount', 'date', 'created_at', 'description')
    if 'transactions' in content and '->select(' in content:
        print("Found in", file_path)
