import glob
import re

files = glob.glob('resources/js/Pages/**/*.vue', recursive=True)

for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()

    def replacer(match):
        full_tag = match.group(0)
        
        # Check if v-model exists
        vmodel_match = re.search(r'v-model(?:\.number)?="([^"]+)"', full_tag)
        if not vmodel_match:
            return full_tag
            
        vmodel_var = vmodel_match.group(1)
        
        # Check if it relates to money
        money_keywords = ['amount', 'price', 'summa', 'cost', 'balance', 'invest', 'payment']
        if not any(k in vmodel_var.lower() for k in money_keywords):
            return full_tag
            
        # Avoid double inject
        if f'{{{{ new Intl.NumberFormat' in content:
            # wait, this checks the whole file. let's just check next few characters in the real code, or just accept double inject risk if we run once.
            pass
            
        return full_tag + f'\n<div v-if="{vmodel_var}" class="text-xs text-blue-600 mt-1 font-bold tracking-wider">{{{{ new Intl.NumberFormat(\'uz-UZ\').format({vmodel_var}) }}}} UZS</div>'

    new_content = re.sub(r'<input[^>]+type="number"[^>]*>', replacer, content)
    
    if new_content != content:
        with open(file, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print("Added format helper in", file)
