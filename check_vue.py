import re

path = r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue'
with open(path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    # we are looking for {{ ... '...xxx'yyy' ... }} pattern
    # basically counting single quotes inside {{ }} blocks
    # if it's an odd number of quotes inside, it's a syntax error
    matches = re.findall(r'\{\{.*?\}\}', line)
    for m in matches:
        count = m.count("'")
        if count > 0 and count % 2 != 0:
            # check if there's no double quotes surrounding it
            if '"' not in m:
                print(f'{i+1}: {line.strip()}')
