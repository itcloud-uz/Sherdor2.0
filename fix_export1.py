import sys

file_path = 'app/Exports/BrigadeLedgerExport.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

target = "        \ = BrigadeTransaction::where('brigade_id', \->brigadeId)\n            ->orderBy('date', 'asc')\n            ->get();"
replacement = "        \ = BrigadeTransaction::with('financeTransaction')\n            ->where('brigade_id', \->brigadeId)\n            ->orderBy('date', 'asc')\n            ->get();"
content = content.replace(target, replacement)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Success BrigadeLedgerExport')
