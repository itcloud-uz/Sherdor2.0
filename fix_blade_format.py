import glob
import re

files = glob.glob('resources/views/exports/**/*.blade.php', recursive=True)

for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()

    # Replace number_format(, 0, '.', '') with number_format(, 0, '.', ' ')
    new_content = content.replace("0, '.', ''", "0, '.', ' '")
    new_content = new_content.replace("2, '.', ''", "2, '.', ' '")
    
    if new_content != content:
        with open(file, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print("Updated blade export", file)
