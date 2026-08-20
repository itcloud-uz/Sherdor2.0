import sys
import re

file_path = 'resources/js/Pages/Admin/CRM/Index.vue'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

state_injection = '''
const isDebtLedgerOpen = ref(false);
const selectedDebtClient = ref(null);
const debtLedgerStartDate = ref('');
const debtLedgerEndDate = ref('');

const openDebtLedger = (client) => {
    selectedDebtClient.value = client;
    debtLedgerStartDate.value = '';
    debtLedgerEndDate.value = '';
    isDebtLedgerOpen.value = true;
};

const closeDebtLedger = () => {
    isDebtLedgerOpen.value = false;
    selectedDebtClient.value = null;
};

const debtLedgerIncomes = computed(() => {
    if (!selectedDebtClient.value) return [];
    let txns = (selectedDebtClient.value.transactions || []).filter(t => t.type === 'income' && t.status === 'approved');
    
    if (debtLedgerStartDate.value) {
        txns = txns.filter(t => new Date(t.transaction_date || t.created_at) >= new Date(debtLedgerStartDate.value));
    }
    if (debtLedgerEndDate.value) {
        const end = new Date(debtLedgerEndDate.value);
        end.setHours(23, 59, 59, 999);
        txns = txns.filter(t => new Date(t.transaction_date || t.created_at) <= end);
    }
    
    return txns.sort((a,b) => new Date(a.transaction_date || a.created_at) - new Date(b.transaction_date || b.created_at));
});

const debtLedgerExpenses = computed(() => {
    if (!selectedDebtClient.value) return [];
    let txns = (selectedDebtClient.value.transactions || []).filter(t => t.type === 'expense' && t.status === 'approved');
    
    if (debtLedgerStartDate.value) {
        txns = txns.filter(t => new Date(t.transaction_date || t.created_at) >= new Date(debtLedgerStartDate.value));
    }
    if (debtLedgerEndDate.value) {
        const end = new Date(debtLedgerEndDate.value);
        end.setHours(23, 59, 59, 999);
        txns = txns.filter(t => new Date(t.transaction_date || t.created_at) <= end);
    }
    
    return txns.sort((a,b) => new Date(a.transaction_date || a.created_at) - new Date(b.transaction_date || b.created_at));
});

const debtLedgerMaxLength = computed(() => {
    return Math.max(debtLedgerIncomes.value.length, debtLedgerExpenses.value.length, 1);
});

const exportDebtClientExcel = () => {
    if (!selectedDebtClient.value) return;
    
    const client = selectedDebtClient.value;
    const name = client.name || 'Mijoz';
    
    const data = [];
    data.push([name, "", "", "", "", "", ""]);
    data.push(["Shartnoma sanasi", "Shartnoma ?", "Uy ?", "Qavat", "Tel raqam", "Umumiy qoldiq", "Izoh"]);
    
    const contract = client.customable?.contracts?.[0] || {};
    const unit = contract?.unit || {};
    data.push([
        contract.created_at ? new Date(contract.created_at).toLocaleDateString('uz-UZ') : '-',
        contract.contract_number || '-',
        unit.unit_number || '-',
        unit.floor?.floor_number || '-',
        client.phone || '-',
        Number(client.balance || 0),
        ""
    ]);
    
    data.push([]);
    data.push(["To'langan", "", "", "Qaytarilgan", "", "", ""]);
    data.push(["Sana", "Bank orqali", "Naqt", "Sana", "Bank orqali", "Naqt", ""]);
    
    const incomes = debtLedgerIncomes.value;
    const expenses = debtLedgerExpenses.value;
    
    let sumIncomeBank = 0, sumIncomeCash = 0;
    let sumExpenseBank = 0, sumExpenseCash = 0;
    
    for (let i = 0; i < debtLedgerMaxLength.value; i++) {
        const inc = incomes[i];
        const exp = expenses[i];
        
        let incDate = '', incBank = '', incCash = '';
        if (inc) {
            incDate = new Date(inc.transaction_date || inc.created_at).toLocaleDateString('uz-UZ');
            if (inc.payment_method === 'bank_transfer' || inc.payment_method === 'card') {
                incBank = Number(inc.amount);
                sumIncomeBank += incBank;
            } else {
                incCash = Number(inc.amount);
                sumIncomeCash += incCash;
            }
        }
        
        let expDate = '', expBank = '', expCash = '';
        if (exp) {
            expDate = new Date(exp.transaction_date || exp.created_at).toLocaleDateString('uz-UZ');
            if (exp.payment_method === 'bank_transfer' || exp.payment_method === 'card') {
                expBank = Number(exp.amount);
                sumExpenseBank += expBank;
            } else {
                expCash = Number(exp.amount);
                sumExpenseCash += expCash;
            }
        }
        
        if (inc || exp || i === 0) {
            data.push([incDate, incBank, incCash, expDate, expBank, expCash, ""]);
        }
    }
    
    data.push(["Jami", sumIncomeBank || "", sumIncomeCash || "", "", sumExpenseBank || "", sumExpenseCash || "", ""]);
    
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(data);
    
    if(!ws['!merges']) ws['!merges'] = [];
    ws['!merges'].push({s:{r:0,c:0}, e:{r:0,c:6}});
    ws['!merges'].push({s:{r:3,c:0}, e:{r:3,c:2}});
    ws['!merges'].push({s:{r:3,c:3}, e:{r:3,c:5}});
    
    wb.SheetNames.push("Jurnal");
    wb.Sheets["Jurnal"] = ws;
    
    wb.SheetNames.forEach(sheetName => {
        let sheet = wb.Sheets[sheetName];
        for (let cell in sheet) {
            if (cell[0] === '!') continue;
            if (sheet[cell].t === 'n') {
                sheet[cell].z = '#,##0';
            }
        }
    });
    
    XLSX.writeFile(wb, ${name}_Jurnal.xlsx);
};
'''

content = content.replace("const showCreateClientModal = ref(false);", state_injection + "\nconst showCreateClientModal = ref(false);")

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print("Injected state successfully!")
