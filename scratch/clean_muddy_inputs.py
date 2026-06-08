import os
import re

directory = r"d:\Sherdor 2.0\resources\js"

def clean_classes(match):
    tag = match.group(0)
    # Find class="..." attribute
    class_match = re.search(r'class="([^"]+)"', tag)
    if class_match:
        cls = class_match.group(1)
        original_cls = cls
        
        # 1. If the class contains bg-gray-500/50
        if 'bg-gray-500/50' in cls:
            # If the element has text-white, replace it with text-gray-900
            cls = cls.replace('text-white', 'text-gray-900')
            # If it already has bg-white or bg-gray-50, just strip bg-gray-500/50
            if 'bg-white' in cls or 'bg-gray-50' in cls:
                cls = cls.replace('bg-gray-500/50', '')
            else:
                cls = cls.replace('bg-gray-500/50', 'bg-white')
                
        # 2. Clean other residual dark styles in input/select elements (excluding glass-input which is now dynamic)
        if 'glass-input' not in cls:
            cls = cls.replace('border-white/10', 'border-gray-200')
            cls = cls.replace('border-white/20', 'border-gray-300')
            cls = cls.replace('bg-black/40', 'bg-white')
            cls = cls.replace('bg-black/50', 'bg-white')
            cls = cls.replace('bg-black/20', 'bg-gray-100')
            cls = cls.replace('text-white/70', 'text-gray-500')
            cls = cls.replace('text-white/50', 'text-gray-400')
            cls = cls.replace('text-white/80', 'text-gray-600')
            
        # Clean double spaces
        cls = ' '.join(cls.split())
        
        if cls != original_cls:
            tag = tag.replace(class_match.group(0), f'class="{cls}"')
    return tag

def process_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    original = content
    
    # Process <input>, <select>, and <textarea> tags
    content = re.sub(r'<(input|select|textarea)\b[^>]*>', clean_classes, content, flags=re.IGNORECASE)
    
    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Cleaned: {filepath}")

if __name__ == "__main__":
    print("Starting cleanup of muddy inputs...")
    for root, dirs, files in os.walk(directory):
        for file in files:
            if file.endswith('.vue'):
                process_file(os.path.join(root, file))
    print("Muddy inputs cleanup completed.")
