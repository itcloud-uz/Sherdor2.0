import sys

file_path = 'resources/js/Pages/Admin/HR/QurilishIshHaqi.vue'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

target = '''        const header = [["Sana", "Turi", "Izoh", "Summa (UZS)"]];
        const rows = selectedBrigade.value.transactions.map(txn => {
            let type = txn.type;
            if (type === 'payment') type = "To'lov / Avans";
            else if (type === 'salary_accrual') type = "Xizmat yozildi";
            else if (type === 'penalty') type = "Jarima";
            
            return [
                txn.date,
                type,
                txn.description || '',
                parseFloat(txn.amount)
            ];
        });'''

replacement = '''        const header = [["Sana", "Turi", "Izoh", "To'lov turi", "Summa (UZS)"]];
        const rows = selectedBrigade.value.transactions.map(txn => {
            let type = txn.type;
            if (type === 'payment') type = "To'lov / Avans";
            else if (type === 'salary_accrual') type = "Xizmat yozildi";
            else if (type === 'penalty') type = "Jarima";
            
            let payment_method = '-';
            if (txn.payment_method === 'cash') payment_method = 'Naqd';
            else if (txn.payment_method === 'card') payment_method = 'Plastik';
            else if (txn.payment_method === 'bank_transfer') payment_method = 'Bank';

            return [
                txn.date,
                type,
                txn.description || '',
                payment_method,
                parseFloat(txn.amount)
            ];
        });'''

content = content.replace(target, replacement)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Success QurilishIshHaqi')
