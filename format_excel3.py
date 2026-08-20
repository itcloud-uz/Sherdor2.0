import glob
import re

files = glob.glob('resources/js/Pages/**/*.vue', recursive=True)

for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()

    def replacer(match):
        wb = match.group(1)
        filename = match.group(2)
        injection = f'''
        // Format all numeric cells with thousand separators
        {wb}.SheetNames.forEach(sheetName => {{
            let sheet = {wb}.Sheets[sheetName];
            for (let cell in sheet) {{
                if (cell[0] === '!') continue;
                if (sheet[cell].t === 'n') {{
                    sheet[cell].z = '#,##0';
                }}
            }}
        }});
        XLSX.writeFile({wb}, {filename});
'''
        return injection
        
    if 'wb.SheetNames.forEach' not in content:
        new_content = re.sub(r'XLSX\.writeFile\(([^,]+),\s*(.+?)\);', replacer, content)
        if new_content != content:
            with open(file, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print("Formatted Excel in", file)
