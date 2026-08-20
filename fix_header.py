import sys

file_path = 'resources/js/Pages/Admin/HR/QurilishIshHaqi.vue'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

target = '''        const header = [["Sana", "Turi", "Izoh", "Summa (UZS)"]];
        const rows = selectedBrigade.value.transactions.map(txn => {
            let type = txn.type;
            if (type === 'payment') type = "To'lov / Avans";
            else if (type === 'salary_accrual') type = "Ish haqi yozilishi";
            else if (type === 'penalty') type = "Jarima / Ushlanma";
            
            return [
                new Date(txn.date || txn.created_at).toLocaleDateString('uz-UZ'),
                type,
                txn.description || '-',
                Number(txn.amount || 0)
            ];
        });
        
        // Jami summa
        const totalAmount = selectedBrigade.value.transactions.reduce((sum, t) => sum + Number(t.amount || 0), 0);
        rows.push([]);
        rows.push(["", "", "JAMI:", totalAmount]);
'''

replacement = '''        const header = [["Sana", "Turi", "Izoh", "To'lov turi", "Summa (UZS)"]];
        const rows = selectedBrigade.value.transactions.map(txn => {
            let type = txn.type;
            if (type === 'payment') type = "To'lov / Avans";
            else if (type === 'salary_accrual') type = "Ish haqi yozilishi";
            else if (type === 'penalty') type = "Jarima / Ushlanma";
            
            let payment_method = '-';
            if (txn.payment_method === 'cash') payment_method = 'Naqd';
            else if (txn.payment_method === 'card') payment_method = 'Plastik';
            else if (txn.payment_method === 'bank_transfer') payment_method = 'Bank';

            return [
                new Date(txn.date || txn.created_at).toLocaleDateString('uz-UZ'),
                type,
                txn.description || '-',
                payment_method,
                Number(txn.amount || 0)
            ];
        });
        
        // Jami summa
        const totalAmount = selectedBrigade.value.transactions.reduce((sum, t) => sum + Number(t.amount || 0), 0);
        rows.push([]);
        rows.push(["", "", "", "JAMI:", totalAmount]);
'''
content = content.replace(target, replacement)
with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Replaced')
