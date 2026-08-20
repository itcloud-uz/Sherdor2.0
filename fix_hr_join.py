import sys
file_path = 'app/Http/Controllers/Admin/HRController.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

target = "'transactions' => function(\) {\n                  \->select('id', 'brigade_id', 'type', 'amount', 'date', 'created_at', 'description', 'linked_work_id');\n              }"
replacement = "'transactions' => function(\) {\n                  \->leftJoin('transactions as ft', 'ft.id', '=', 'brigade_transactions.finance_transaction_id')\n                    ->select('brigade_transactions.id', 'brigade_id', 'brigade_transactions.type', 'amount', 'date', 'brigade_transactions.created_at', 'description', 'linked_work_id', 'ft.payment_method');\n              }"

content = content.replace(target, replacement)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Success')
