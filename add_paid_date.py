import sys
file_path = 'resources/js/Pages/Admin/HR/QurilishIshHaqi.vue'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace 1: Add unlinkedPaidDate
target1 = '''    let unlinkedPaidAmount = 0;
    if (selectedBrigade.value.transactions) {
        selectedBrigade.value.transactions.forEach(t => {
            if (!t.linked_work_id && t.type !== 'ovqat' && parseFloat(t.amount) > 0) {
                unlinkedPaidAmount += parseFloat(t.amount);
            }
        });
    }'''
replacement1 = '''    let unlinkedPaidAmount = 0;
    let unlinkedPaidDate = null;
    if (selectedBrigade.value.transactions) {
        selectedBrigade.value.transactions.forEach(t => {
            if (!t.linked_work_id && t.type !== 'ovqat' && parseFloat(t.amount) > 0) {
                unlinkedPaidAmount += parseFloat(t.amount);
                if (!unlinkedPaidDate || new Date(t.date) > new Date(unlinkedPaidDate)) {
                    unlinkedPaidDate = t.date;
                }
            }
        });
    }'''

# Replace 2: Add last_paid_date to map
target2 = '''    return rows.value.map(row => {
        let ovqat_amount = 0;
        let paid_amount = 0;
        
        if (row.transactions && row.transactions.length > 0) {
            row.transactions.forEach(t => {
                paid_amount += parseFloat(t.amount) || 0;
            });
        }
        
        const total = parseFloat(row.calculated_sum) || 0;
        let qoldiq = total - paid_amount;
        
        // Distribute unlinked payments to this row's qoldiq
        if (qoldiq > 0 && unlinkedPaidAmount > 0) {
            let pay = Math.min(qoldiq, unlinkedPaidAmount);
            qoldiq -= pay;
            unlinkedPaidAmount -= pay;
            paid_amount += pay;
        }
        
        return {
            ...row,
            paid_amount,
            qoldiq
        };'''
replacement2 = '''    return rows.value.map(row => {
        let ovqat_amount = 0;
        let paid_amount = 0;
        let last_paid_date = null;
        
        if (row.transactions && row.transactions.length > 0) {
            row.transactions.forEach(t => {
                paid_amount += parseFloat(t.amount) || 0;
                if (!last_paid_date || new Date(t.date) > new Date(last_paid_date)) {
                    last_paid_date = t.date;
                }
            });
        }
        
        const total = parseFloat(row.calculated_sum) || 0;
        let qoldiq = total - paid_amount;
        
        // Distribute unlinked payments to this row's qoldiq
        if (qoldiq > 0 && unlinkedPaidAmount > 0) {
            let pay = Math.min(qoldiq, unlinkedPaidAmount);
            qoldiq -= pay;
            unlinkedPaidAmount -= pay;
            paid_amount += pay;
            
            if (unlinkedPaidDate) {
                if (!last_paid_date || new Date(unlinkedPaidDate) > new Date(last_paid_date)) {
                    last_paid_date = unlinkedPaidDate;
                }
            }
        }
        
        return {
            ...row,
            paid_amount,
            qoldiq,
            last_paid_date
        };'''

# Replace 3: Update HTML to show last_paid_date
target3 = '''<td class="px-4 py-3 text-right font-bold text-green-700 whitespace-nowrap bg-green-50/30">{{ formatMoney(row.paid_amount) }}</td>'''
replacement3 = '''<td class="px-4 py-3 text-right font-bold text-green-700 whitespace-nowrap bg-green-50/30">
                                            <div>{{ formatMoney(row.paid_amount) }}</div>
                                            <div v-if="row.last_paid_date && parseFloat(row.paid_amount) > 0" class="text-xs text-gray-500 font-normal mt-1">{{ row.last_paid_date }}</div>
                                        </td>'''

content = content.replace(target1, replacement1)
content = content.replace(target2, replacement2)
content = content.replace(target3, replacement3)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Success')
