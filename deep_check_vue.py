import re

path = r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue'
with open(path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    # Find words with apostrophe inside single quotes
    # For example: 'Brigadani o'chirmoqchimisiz?'
    matches = re.findall(r"'[^']*?o'[a-z]*[^']*?'", line)
    if matches:
        print(f"Line {i+1}: {line.strip()} => Matches: {matches}")
    
    # Also find To'liq, o'zgartir, etc.
    matches2 = re.findall(r"'[^']*?\w+'\w*[^']*?'", line)
    # We want cases where there are exactly 3 single quotes in a small span, which means it's broken.
    # A simple heuristic: if the line has an odd number of single quotes, it's highly likely broken.
    # Excluding lines with double quotes that might contain single quotes.
    # Let's just find lines with odd number of single quotes that don't have double quotes:
    # Actually, let's just print any line containing '.*o'.*?'
    # wait, just search for confirm(' or toast(' or something similar
    if "confirm('" in line and "o'" in line:
         print(f"Line {i+1}: {line.strip()}")
    if "alert('" in line and "o'" in line:
         print(f"Line {i+1}: {line.strip()}")
