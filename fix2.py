import re

with open('resources/js/Pages/Admin/Partners/Assets.vue', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Remove style="background-color: #ffffff; color: #1e293b;" from option tags
content = content.replace('style="background-color: #ffffff; color: #1e293b;"', '')

# 2. Add style="color-scheme: light;" to select tags
# Find all <select ...> and insert style="color-scheme: light;"
# Ensure we don't add it multiple times
content = re.sub(r'(<select\b(?![^>]*style="color-scheme: light;")[^>]*)>', r'\1 style="color-scheme: light;">', content)

# 3. Just in case, clean up any double spaces in options
content = content.replace('<option  value=', '<option value=').replace('<option  v-for=', '<option v-for=')

with open('resources/js/Pages/Admin/Partners/Assets.vue', 'w', encoding='utf-8') as f:
    f.write(content)
