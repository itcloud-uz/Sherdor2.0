import sys

file_path = 'app/Models/BrigadeTransaction.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

if 'public function financeTransaction()' not in content:
    target = "    public function brigade()\n    {\n        return \->belongsTo(Brigade::class);\n    }\n}"
    replacement = "    public function brigade()\n    {\n        return \->belongsTo(Brigade::class);\n    }\n\n    public function financeTransaction()\n    {\n        return \->belongsTo(Transaction::class, 'finance_transaction_id');\n    }\n}"
    content = content.replace(target, replacement)
    
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
print('Success BrigadeTransaction')
