import sys

file_path = 'resources/js/Pages/Admin/HR/Texnikalar.vue'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

target = '''        const headers = ['?', 'Sana', 'To\\'lov maqsadi', 'Izoh', 'Turi', 'Summa (UZS)'];
        let totalAmount = 0;
        
        const data = sortedTransactions.value.map((txn, index) => {
            let type = txn.type;
            if (type === 'payment') type = "To'lov / Avans";
            else if (type === 'penalty') type = "Jarima";
            else if (type === 'fuel') type = "Salyarka olib berdik";
            
            totalAmount += parseFloat(txn.amount) || 0;
            
            return [
                index + 1,
                txn.date,
                type,
                txn.description || '',
                type,
                parseFloat(txn.amount) || 0
            ];
        });
        
        data.push([
            '', 'JAMI:', '', '', '', totalAmount
        ]);'''

replacement = '''        const headers = ['?', 'Sana', 'To\\'lov maqsadi', 'Izoh', 'Turi', 'To\\'lov turi', 'Summa (UZS)'];
        let totalAmount = 0;
        
        const data = sortedTransactions.value.map((txn, index) => {
            let type = txn.type;
            if (type === 'payment') type = "To'lov / Avans";
            else if (type === 'penalty') type = "Jarima";
            else if (type === 'fuel') type = "Salyarka olib berdik";
            
            totalAmount += parseFloat(txn.amount) || 0;
            
            let payment_method = '-';
            if (txn.payment_method === 'cash') payment_method = 'Naqd';
            else if (txn.payment_method === 'card') payment_method = 'Plastik';
            else if (txn.payment_method === 'bank_transfer') payment_method = 'Bank';
            
            return [
                index + 1,
                txn.date,
                type,
                txn.description || '',
                type,
                payment_method,
                parseFloat(txn.amount) || 0
            ];
        });
        
        data.push([
            '', 'JAMI:', '', '', '', '', totalAmount
        ]);'''

content = content.replace(target, replacement)

# Need to update widths in Texnikalar.vue
target_widths = "ws['!cols'] = [\n            {wch: 6}, {wch: 16}, {wch: 35}, {wch: 25}, {wch: 18}, {wch: 22}\n        ];"
replacement_widths = "ws['!cols'] = [\n            {wch: 6}, {wch: 16}, {wch: 30}, {wch: 25}, {wch: 18}, {wch: 15}, {wch: 22}\n        ];"
content = content.replace(target_widths, replacement_widths)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Success Texnikalar')
