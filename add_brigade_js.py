import re

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

# 1. State variables
state_vars = """
const showBrigadeLedgerModal = ref(false);
const activeBrigadeForLedger = ref(null);
const brigadeLedgerData = ref({ balance: 0, logs: [], transactions: [] });
const brigadeLedgerForm = ref({ amount: '', description: '', type: 'payment', date: new Date().toISOString().split('T')[0] });
"""

# Insert near showMachineryLedgerModal
text = text.replace("const showMachineryLedgerModal = ref(false);", state_vars + "\nconst showMachineryLedgerModal = ref(false);")

# 2. Methods
methods = """
async function openBrigadeLedger(brigade) {
    activeBrigadeForLedger.value = brigade;
    try {
        const response = await fetch(route('admin.hr.brigade.ledger', brigade.id));
        const data = await response.json();
        
        // Merge and sort
        const items = [];
        data.logs.forEach(l => {
            const isHourly = l.worker?.salary_type === 'hourly' || true;
            items.push({ 
                date: new Date(l.date), 
                desc: `Ish (Obyekt: ${l.building?.name || "Noma'lum"}) - ${l.hours_worked || l.volume_done} hajm/soat`, 
                amount: `+ ${l.calculated_cost}` 
            });
        });
        data.transactions.forEach(t => {
            items.push({ 
                date: new Date(t.date), 
                desc: `${t.type === 'salary_accrual' ? 'Kirim' : (t.type === 'penalty' ? 'Ushlanma (Jarima)' : 'To\\'lov / Avans')} (${t.description || ''})`, 
                amount: t.type === 'salary_accrual' ? `+ ${t.amount}` : `- ${t.amount}` 
            });
        });
        
        items.sort((a, b) => a.date - b.date);
        
        brigadeLedgerData.value = {
            balance: data.balance,
            items: items
        };
        showBrigadeLedgerModal.value = true;
    } catch(err) {
        console.error(err);
        alert("Ma'lumotlarni yuklashda xatolik!");
    }
}

function saveBrigadeTransaction() {
    if (!brigadeLedgerForm.value.amount || !brigadeLedgerForm.value.date || !brigadeLedgerForm.value.type) {
        alert("Sana, tur va summani kiriting.");
        return;
    }
    router.post(route('admin.hr.brigade.transactions.store', activeBrigadeForLedger.value.id), brigadeLedgerForm.value, {
        onSuccess: () => {
            brigadeLedgerForm.value = { amount: '', description: '', type: 'payment', date: new Date().toISOString().split('T')[0] };
            openBrigadeLedger(activeBrigadeForLedger.value); // refresh data
        }
    });
}

function exportBrigadeLedger() {
    if(activeBrigadeForLedger.value) {
        window.location.href = route('admin.hr.brigade.ledger.export', activeBrigadeForLedger.value.id);
    }
}
"""

text = text.replace("function deleteBrigade(id) {", methods + "\nfunction deleteBrigade(id) {")

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'w', encoding='utf-8') as f:
    f.write(text)

print('Added Brigade Ledger JS logic')
