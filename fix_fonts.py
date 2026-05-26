import os
import re

dirs = [
    'd:/Sherdor 2.0/resources/js/Pages/Admin', 
    'd:/Sherdor 2.0/resources/js/Components',
    'd:/Sherdor 2.0/resources/js/Layouts'
]

def process_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original_content = content

    def replace_text_classes(m):
        cls = m.group(1)
        dark_bg_pattern = r'bg-(blue|emerald|red|amber|indigo|green|yellow|purple|pink|slate|gray)-(500|600|700|800|900)'
        has_dark_bg = bool(re.search(dark_bg_pattern, cls))
        
        # If it doesn't have a dark background, we should convert light text to dark text
        if not has_dark_bg:
            cls = re.sub(r'\btext-white\b', 'text-gray-900', cls)
            cls = re.sub(r'\bhover:text-white\b', 'hover:text-blue-600', cls)
            cls = re.sub(r'\btext-gray-400\b', 'text-gray-500', cls)
            cls = re.sub(r'\btext-gray-300\b', 'text-gray-600', cls)
            cls = re.sub(r'\btext-gray-200\b', 'text-gray-700', cls)
            cls = re.sub(r'\bplaceholder-white/30\b', 'placeholder-gray-400', cls)
            cls = re.sub(r'\bplaceholder-white/50\b', 'placeholder-gray-400', cls)
        return f'class="{cls}"'

    # Match class="something"
    content = re.sub(r'class="([^"]+)"', replace_text_classes, content)

    # Also match single quotes inside :class like :class="['text-white', ...]"
    def replace_text_classes_single(m):
        cls = m.group(1)
        dark_bg_pattern = r'bg-(blue|emerald|red|amber|indigo|green|yellow|purple|pink|slate|gray)-(500|600|700|800|900)'
        has_dark_bg = bool(re.search(dark_bg_pattern, cls))
        
        if not has_dark_bg:
            cls = re.sub(r'\btext-white\b', 'text-gray-900', cls)
            cls = re.sub(r'\bhover:text-white\b', 'hover:text-blue-600', cls)
            cls = re.sub(r'\btext-gray-400\b', 'text-gray-500', cls)
            cls = re.sub(r'\btext-gray-300\b', 'text-gray-600', cls)
            cls = re.sub(r'\btext-gray-200\b', 'text-gray-700', cls)
            cls = re.sub(r'\bplaceholder-white/30\b', 'placeholder-gray-400', cls)
            cls = re.sub(r'\bplaceholder-white/50\b', 'placeholder-gray-400', cls)
        return f"'{cls}'"

    content = re.sub(r"'([^']+)'", replace_text_classes_single, content)

    # A few direct replacements for safely known bad patterns
    content = content.replace('text-white/80', 'text-gray-600')
    content = content.replace('text-white/70', 'text-gray-500')
    content = content.replace('text-white/50', 'text-gray-400')
    content = content.replace('border-white/10', 'border-gray-200')
    content = content.replace('border-white/20', 'border-gray-300')
    content = content.replace('bg-white/5', 'bg-gray-50')
    content = content.replace('bg-white/10', 'bg-gray-100')

    if content != original_content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)

for d in dirs:
    for root, _, files in os.walk(d):
        for file in files:
            if file.endswith('.vue'):
                process_file(os.path.join(root, file))
