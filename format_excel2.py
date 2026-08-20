import glob
import re

files = glob.glob('resources/js/Pages/**/*.vue', recursive=True)

for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()

    def replacer(match):
        wb = match.group(1)
        filename = match.group(2)
        # Check if already injected
        if "wb.SheetNames.forEach(" in content:
            pass # We might duplicate, let's just be careful
            
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
        
    # More robust regex for XLSX.writeFile
    # We find "XLSX.writeFile(wb, " up to ");"
    new_content = re.sub(r'XLSX\.writeFile\(([^,]+),\s*(.+?)\);', replacer, content)
    
    # Let's manually undo double injection if any
    if new_content.count('SheetNames.forEach') > content.count('SheetNames.forEach'):
        with open(file, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print("Formatted Excel in", file)
