import re

path = r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue'
with open(path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    # Strip whitespace
    line = line.strip()
    if not line: continue
    
    # Check if there is an odd number of single quotes in the line
    # Skip lines that contain double quotes (too complex to parse with regex)
    if '"' not in line and '`' not in line:
        count = line.count("'")
        if count % 2 != 0:
            # Check if there is a // comment
            if '//' in line:
                before_comment = line.split('//')[0]
                if before_comment.count("'") % 2 != 0:
                    print(f"Line {i+1}: {line}")
            else:
                # Ignore some valid odd quotes inside html attributes if not using double quotes
                if not re.search(r'<\w+.*?>', line):
                    print(f"Line {i+1}: {line}")
