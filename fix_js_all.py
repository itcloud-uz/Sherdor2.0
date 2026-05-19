import re

path = r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue'
with open(path, 'r', encoding='utf-8') as f:
    text = f.read()

# Replace all confirm('...') with confirm("...")
# Replace all alert('...') with alert("...")
# Using regex:
text = re.sub(r"confirm\('([^']*)'([^']*)'([^']*)'\)", r'confirm("\1\'\2\'\3")', text)
text = re.sub(r"alert\('([^']*)'([^']*)'([^']*)'\)", r'alert("\1\'\2\'\3")', text)

# For safety, let's just find and replace any single-quoted string containing exactly one inner single quote
# actually, let's manually write out the replacements for Ma'lumotlarni
text = text.replace("alert('Ma'lumotlarni yuklashda xatolik!')", "alert(\"Ma'lumotlarni yuklashda xatolik!\")")
text = text.replace("toast.error('Ma'lumotlarni saqlashda xatolik yuz berdi');", "toast.error(\"Ma'lumotlarni saqlashda xatolik yuz berdi\");")
text = text.replace("toast.success('Ma'lumotlar muvaffaqiyatli saqlandi');", "toast.success(\"Ma'lumotlar muvaffaqiyatli saqlandi\");")

# Also, any general alert('...') with an apostrophe inside
def fix_quotes(match):
    content = match.group(1)
    return f'alert("{content}")'

def fix_confirm(match):
    content = match.group(1)
    return f'confirm("{content}")'

def fix_toast(match):
    prefix = match.group(1)
    content = match.group(2)
    return f'{prefix}("{content}")'

# This regex matches alert(' ... ') where ... does not contain ", allowing us to safely replace ' with "
text = re.sub(r"alert\('([^\"']+)'\)", r'alert("\1")', text)
text = re.sub(r"alert\('([^\"']+'[^\"']+)'\)", r'alert("\1")', text)

text = re.sub(r"confirm\('([^\"']+)'\)", r'confirm("\1")', text)
text = re.sub(r"confirm\('([^\"']+'[^\"']+)'\)", r'confirm("\1")', text)

text = re.sub(r"(toast\.(?:success|error|info|warning))\('([^\"']+)'\)", r'\1("\2")', text)
text = re.sub(r"(toast\.(?:success|error|info|warning))\('([^\"']+'[^\"']+)'\)", r'\1("\2")', text)

with open(path, 'w', encoding='utf-8') as f:
    f.write(text)

print('Cleaned all JS strings')
