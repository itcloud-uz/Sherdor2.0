import sys
import glob

file_path = 'app/Http/Controllers/Admin/HRController.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Texnikalar transactions
target = "'transactions' => function(\) {\n                  \->select('id', 'machine_owner_id', 'type', 'amount', 'date', 'created_at', 'description')->orderBy('date', 'asc');\n              }"
replacement = "'transactions' => function(\) {\n                  \->leftJoin('transactions as ft', 'ft.id', '=', 'machine_owner_transactions.finance_transaction_id')\n                    ->select('machine_owner_transactions.id', 'machine_owner_id', 'machine_owner_transactions.type', 'amount', 'date', 'machine_owner_transactions.created_at', 'description', 'ft.payment_method')->orderBy('date', 'asc');\n              }"

content = content.replace(target, replacement)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Success HR Texnikalar')
