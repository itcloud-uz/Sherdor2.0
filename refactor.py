import os
import re

dirs = ['d:/Sherdor 2.0/resources/js/Pages/Admin', 'd:/Sherdor 2.0/resources/js/Components']

def process_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original_content = content

    # 1. Clean up Dark Mode & Neon classes
    removals = [
        r'dark:bg-[a-zA-Z0-9\-]+',
        r'dark:text-[a-zA-Z0-9\-]+',
        r'dark:border-[a-zA-Z0-9\-]+',
        r'backdrop-blur(?:-[a-zA-Z0-9]+)?',
        r'bg-opacity-\d+',
        r'mix-blend-[a-zA-Z0-9\-]+',
        r'bg-white/\d+',
        r'bg-black/50', r'bg-black/80', r'bg-black/90',
        r'bg-gray-800', r'bg-gray-900', r'bg-zinc-900',
        r'shadow-xl', r'shadow-2xl', r'shadow-gray-900',
        r'text-cyan-\d+', r'text-magenta-\d+', r'text-yellow-400',
        r'shadow-neon-[a-zA-Z0-9\-]+', r'drop-shadow-\[[^\]]+\]'
    ]
    for pattern in removals:
        content = re.sub(r'(?<=[\s"\'])' + pattern + r'(?=[\s"\'])', '', content)

    # 2. Table Headers
    content = re.sub(
        r'<thead\b(\s+[^>]*)?\sclass="[^"]*"([^>]*)>', 
        r'<thead\1 class="bg-gray-50 text-gray-500 uppercase text-xs font-medium"\2>', 
        content
    )
    content = re.sub(
        r'<thead\b(?!\s+[^>]*class=)([^>]*)>', 
        r'<thead class="bg-gray-50 text-gray-500 uppercase text-xs font-medium"\1>', 
        content
    )
    
    # Table Rows
    content = re.sub(
        r'<tr\b(\s+[^>]*)?\sclass="([^"]*)"([^>]*)>',
        lambda m: f'<tr{m.group(1) or ""} class="{re.sub(r"bg-[a-zA-Z0-9\-]+", "", m.group(2))} bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors"{m.group(3)}>',
        content
    )
    content = re.sub(
        r'<tr\b(?!\s+[^>]*class=)([^>]*)>',
        r'<tr class="bg-white border-b border-gray-200 hover:bg-gray-50 transition-colors"\1>',
        content
    )

    # 3. Inputs
    input_class = "bg-white border border-gray-300 text-gray-900 text-sm rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
    content = re.sub(r'\bglass-input\b', input_class, content)
    content = re.sub(r'\bform-input\b', input_class, content)

    old_input_pattern = r'w-full bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500'
    content = content.replace(old_input_pattern, input_class)

    # 4. Badges
    def badge_replacer(m):
        cls = m.group(1)
        if 'bg-green' in cls or 'bg-emerald' in cls or 'text-green' in cls:
            return ' class="bg-green-50 text-green-700 border border-green-200 rounded-md px-2 py-1 text-xs font-medium"'
        elif 'bg-red' in cls or 'text-red' in cls:
            return ' class="bg-red-50 text-red-700 border border-red-200 rounded-md px-2 py-1 text-xs font-medium"'
        elif 'bg-amber' in cls or 'bg-yellow' in cls or 'text-amber' in cls or 'text-yellow' in cls:
            return ' class="bg-amber-50 text-amber-700 border border-amber-200 rounded-md px-2 py-1 text-xs font-medium"'
        return m.group(0)

    content = re.sub(r'\s+class="([^"]*px-2 py-1[^"]*rounded[^"]*)"', badge_replacer, content)

    # Clean up multiple spaces inside classes
    content = re.sub(r'class="([^"]+)"', lambda m: f'class="{" ".join(m.group(1).split())}"', content)

    if content != original_content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)

for d in dirs:
    for root, _, files in os.walk(d):
        for file in files:
            if file.endswith('.vue'):
                process_file(os.path.join(root, file))
