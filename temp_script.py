import os
import re

def process_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original_content = content

    # Base replacements
    content = content.replace('glass-panel', 'bg-white border border-gray-200 shadow-sm rounded-xl')
    content = content.replace('glass-card', 'bg-white border border-gray-200 shadow-sm rounded-xl')
    content = content.replace('glass-input', 'bg-white border border-gray-300 text-gray-900 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500')
    
    # Overlays & backgrounds
    content = re.sub(r'bg-black/\d+', 'bg-gray-900/50', content)
    content = content.replace('bg-[#050505]/95', 'bg-white')
    content = content.replace('backdrop-blur-md', 'backdrop-blur-sm')
    content = content.replace('backdrop-blur-xl', 'backdrop-blur-sm')
    content = content.replace('backdrop-blur-3xl', 'backdrop-blur-sm')
    
    # Neon texts
    content = content.replace('text-neon-cyan', 'text-blue-600')
    content = content.replace('text-neon-green', 'text-emerald-600')
    content = content.replace('text-neon-red', 'text-red-600')
    content = content.replace('text-neon-yellow', 'text-amber-600')
    content = content.replace('text-neon-magenta', 'text-indigo-600')
    
    # Other text styles
    content = content.replace('text-text-muted', 'text-gray-500')
    content = content.replace('text-text-secondary', 'text-gray-600')
    content = content.replace('text-text-primary', 'text-gray-900')
    content = content.replace('text-shadow-glow', '')
    
    # Buttons
    content = content.replace('bg-neon-cyan text-black', 'bg-blue-600 text-white')
    content = content.replace('bg-neon-green text-black', 'bg-emerald-600 text-white')
    content = content.replace('bg-neon-red text-black', 'bg-red-600 text-white')
    content = content.replace('bg-neon-yellow text-black', 'bg-amber-500 text-white')
    
    # Neon backgrounds
    content = content.replace('bg-neon-cyan', 'bg-blue-600')
    content = content.replace('bg-neon-green', 'bg-emerald-600')
    content = content.replace('bg-neon-red', 'bg-red-600')
    content = content.replace('bg-neon-yellow', 'bg-amber-500')
    content = content.replace('bg-neon-magenta', 'bg-indigo-600')
    
    # Low opacity backgrounds
    content = re.sub(r'bg-blue-600/\d+', 'bg-blue-50', content)
    content = re.sub(r'bg-emerald-600/\d+', 'bg-emerald-50', content)
    content = re.sub(r'bg-red-600/\d+', 'bg-red-50', content)
    content = re.sub(r'bg-amber-500/\d+', 'bg-amber-50', content)
    content = re.sub(r'bg-indigo-600/\d+', 'bg-indigo-50', content)
    
    # Borders
    content = content.replace('border-neon-cyan', 'border-blue-500')
    content = content.replace('border-neon-green', 'border-emerald-500')
    content = content.replace('border-neon-red', 'border-red-500')
    content = content.replace('border-neon-yellow', 'border-amber-500')
    content = re.sub(r'border-blue-500/\d+', 'border-blue-200', content)
    content = re.sub(r'border-emerald-500/\d+', 'border-emerald-200', content)
    content = re.sub(r'border-red-500/\d+', 'border-red-200', content)
    content = re.sub(r'border-amber-500/\d+', 'border-amber-200', content)
    
    content = content.replace('border-white/5', 'border-gray-100')
    content = content.replace('border-white/10', 'border-gray-200')
    content = content.replace('border-white/20', 'border-gray-300')
    
    # Hover states
    content = content.replace('hover:bg-white/5', 'hover:bg-gray-50')
    content = content.replace('hover:bg-white/10', 'hover:bg-gray-100')
    content = content.replace('hover:border-white/10', 'hover:border-gray-200')
    content = content.replace('hover:border-white/20', 'hover:border-gray-300')
    content = content.replace('hover:text-neon-cyan', 'hover:text-blue-700')
    
    # Shadows
    content = re.sub(r'shadow-\[0_0_.*?\]', 'shadow-sm', content)
    content = content.replace('shadow-neon-primary', 'shadow-sm')
    content = re.sub(r'drop-shadow-\[.*?\]', '', content)
    
    # White texts -> gray-900 (except when inside buttons maybe? let's do safe replace)
    content = content.replace('text-white', 'text-gray-900')
    # Revert text-gray-900 for our primary buttons:
    content = content.replace('bg-blue-600 text-gray-900', 'bg-blue-600 text-white')
    content = content.replace('bg-emerald-600 text-gray-900', 'bg-emerald-600 text-white')
    content = content.replace('bg-red-600 text-gray-900', 'bg-red-600 text-white')
    content = content.replace('bg-amber-500 text-gray-900', 'bg-amber-500 text-white')
    content = content.replace('bg-indigo-600 text-gray-900', 'bg-indigo-600 text-white')
    
    if content != original_content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Modified {filepath}")

dirs = ['d:/Sherdor 2.0/resources/js/Pages/Admin', 'd:/Sherdor 2.0/resources/js/Components']
for d in dirs:
    for root, _, files in os.walk(d):
        for file in files:
            if file.endswith('.vue') and file != 'Dashboard.vue':
                process_file(os.path.join(root, file))
