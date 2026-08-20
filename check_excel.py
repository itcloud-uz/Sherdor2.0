import glob
import re

files = glob.glob('resources/js/Pages/**/*.vue', recursive=True)

for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()

    # Find the export functions and inject .z = '# ##0'
    # Actually, the easiest way is to add a loop over range just before XLSX.utils.book_append_sheet(wb, ws, ...)
    # But some files already have the loop!
    # Let's print out what files have XLSX.writeFile
    if 'XLSX.writeFile' in content:
        print("Found in", file)
