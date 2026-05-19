import re

paths = [
    r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue',
    r'd:\Sherdor 2.0\routes\web.php'
]

for path in paths:
    with open(path, 'r', encoding='utf-8') as f:
        text = f.read()
    
    new_text = re.sub(r"\\'", "'", text)
    new_text = re.sub(r'\\"', '"', new_text)
    
    if new_text != text:
        with open(path, 'w', encoding='utf-8') as f:
            f.write(new_text)
        print(f'Fixed {path}')
    else:
        print(f'No changes in {path}')
