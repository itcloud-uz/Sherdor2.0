import re

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\Finance\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

# 1. Imports
if "import BrigadeLedgerModal" not in text:
    text = text.replace(
        "import VueApexCharts from 'vue3-apexcharts';",
        "import VueApexCharts from 'vue3-apexcharts';\nimport BrigadeLedgerModal from '@/Components/Ledger/BrigadeLedgerModal.vue';\nimport OwnerLedgerModal from '@/Components/Ledger/OwnerLedgerModal.vue';"
    )

# 2. State & Functions
script_additions = """
// --- LEDGER MODAL INTEGRATION ---
const showBrigadeLedgerModal = ref(false);
const activeBrigadeForLedger = ref(null);
const brigadeLedgerData = ref({ balance: 0, items: [] });

const showOwnerLedgerModal = ref(false);
const activeOwnerForLedger = ref(null);
const ownerLedgerData = ref({ balance: 0, items: [] });

async function openBrigadeLedgerFromFinance(brigadeId) {
    if (!brigadeId) return;
    const brigade = props.brigades?.find(b => b.id === brigadeId);
    if (!brigade) return;
    
    activeBrigadeForLedger.value = brigade;
    try {
        const response = await fetch(route('admin.hr.brigade.ledger', brigade.id));
        if (!response.ok) throw new Error('Network error');
        const data = await response.json();
        
        let items = [];
        (data.logs || []).forEach(l => {
            items.push({ date: new Date(l.date), desc: `Ish (Obyekt: ${l.building?.name || 'Noma\\'lum'}) - ${l.hours_worked || l.volume_done}`, amount: `+${l.total_cost}`, rawDate: l.date });
        });
        (data.transactions || []).forEach(t => {
            let sign = t.type === 'payment' ? '-' : (t.type === 'penalty' ? '-' : '+');
            items.push({ date: new Date(t.date || t.created_at), desc: `${t.type === 'payment' ? 'To\\'lov/Avans' : (t.type === 'penalty' ? 'Jarima/Ushlanma' : 'Ish haqi')} - ${t.description || ''}`, amount: `${sign}${t.amount}`, rawDate: (t.date || t.created_at) });
        });
        items.sort((a, b) => b.date - a.date);
        
        brigadeLedgerData.value = { balance: data.balance, items: items };
        showBrigadeLedgerModal.value = true;
    } catch(err) {
        console.error(err);
        alert("Ma'lumotlarni yuklashda xatolik!");
    }
}

async function submitBrigadeLedgerFromEvent(payload) {
    if (!payload.target_id) return;
    try {
        const response = await fetch(route('admin.hr.brigade.transactions.store', payload.target_id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify(payload)
        });
        if (response.ok) {
            openBrigadeLedgerFromFinance(payload.target_id); // refresh
        } else {
            alert('Tranzaksiyani saqlashda xatolik!');
        }
    } catch(err) {
        console.error(err);
        alert('Tranzaksiyani saqlashda xatolik!');
    }
}

function exportBrigadePdf(id) {
    if (id) window.open(route('admin.hr.brigade.ledger.export', id), '_blank');
}

async function openOwnerLedgerFromFinance(ownerId) {
    if (!ownerId) return;
    const owner = props.machineOwners?.find(o => o.id === ownerId);
    if (!owner) return;
    
    activeOwnerForLedger.value = owner;
    try {
        const response = await fetch(route('admin.hr.machine-owners.ledger', owner.id));
        if (!response.ok) throw new Error('Network error');
        const data = await response.json();
        
        let allItems = [];
        (data.logs || []).forEach(log => {
            allItems.push({
                is_work: true, is_payment: false, date: new Date(log.date), rawDate: log.date,
                desc: `${log.machinery?.name || 'Texnika'} (${log.building?.name || 'Obyekt'}) - ${log.notes || ''}`,
                qty: log.hours_worked || log.volume_done,
                amount: log.total_cost,
                running_balance: 0 
            });
        });
        (data.transactions || []).forEach(txn => {
            allItems.push({
                is_work: false, is_payment: true, date: new Date(txn.date || txn.created_at), rawDate: (txn.date || txn.created_at),
                account_type: txn.account_type || 'main',
                amount: txn.amount,
                running_balance: 0 
            });
        });
        
        allItems.sort((a, b) => a.date - b.date);
        let currentBalance = 0;
        allItems.forEach(item => {
            if (item.is_work) currentBalance += parseFloat(item.amount);
            if (item.is_payment) currentBalance -= parseFloat(item.amount);
            item.running_balance = currentBalance;
        });
        allItems.sort((a, b) => b.date - a.date);
        
        ownerLedgerData.value = { balance: data.owner?.balance || 0, items: allItems };
        showOwnerLedgerModal.value = true;
    } catch(err) {
        console.error(err);
        alert("Ma'lumotlarni yuklashda xatolik!");
    }
}

async function submitOwnerLedgerFromEvent(payload) {
    if (!payload.target_id) return;
    try {
        const response = await fetch(route('admin.hr.machine-owners.ledger.store', payload.target_id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify(payload)
        });
        if (response.ok) {
            openOwnerLedgerFromFinance(payload.target_id); // refresh
        } else {
            alert('Tranzaksiyani saqlashda xatolik!');
        }
    } catch(err) {
        console.error(err);
        alert('Tranzaksiyani saqlashda xatolik!');
    }
}

function exportOwnerPdf(id) {
    if (id) window.open(route('admin.hr.owner.ledger.export', id), '_blank');
}
// --- END LEDGER MODAL INTEGRATION ---
"""

if "openBrigadeLedgerFromFinance" not in text:
    text = text.replace("</script>", script_additions + "\n</script>")

# 3. Add Component HTML at the bottom of the template
html_additions = """
        <!-- Shared Ledger Modals -->
        <BrigadeLedgerModal 
            :show="showBrigadeLedgerModal"
            :brigade="activeBrigadeForLedger"
            :ledgerData="brigadeLedgerData"
            @close="showBrigadeLedgerModal = false"
            @submitTransaction="submitBrigadeLedgerFromEvent"
            @exportPdf="exportBrigadePdf"
        />

        <OwnerLedgerModal 
            :show="showOwnerLedgerModal"
            :owner="activeOwnerForLedger"
            :ledgerData="ownerLedgerData"
            @close="showOwnerLedgerModal = false"
            @submitTransaction="submitOwnerLedgerFromEvent"
            @exportPdf="exportOwnerPdf"
        />
"""

if "<BrigadeLedgerModal" not in text:
    text = text.replace("    </AdminLayout>", html_additions + "\n    </AdminLayout>")


# 4. Modify the UI form.
# We want to hide the rest of the form and show the "Ochiq Jurnal" button if it's Brigade or Machine Owner.
# In Finance/Index.vue, the selectors are typically `v-if="requiresMachineOwner"` etc.
# We will inject a wrapper for the generic form inputs.

# First, find where requiresMachineOwner select ends.
import re

text = re.sub(
    r'(<div v-if="requiresMachineOwner">.*?</select>\s*</div>)',
    r'\1\n\n                        <div v-if="requiresMachineOwner && form.machine_owner_id" class="p-6 bg-neon-yellow/10 border border-neon-yellow/30 rounded-xl flex items-center justify-between">\n                            <div>\n                                <h4 class="text-neon-yellow font-bold uppercase tracking-widest text-sm">Texnika Egasi Jurnali</h4>\n                                <p class="text-text-muted text-[10px] font-mono mt-1">Bu toifa bo\'yicha to\'lovlarni to\'g\'ridan-to\'g\'ri maxsus jurnal orqali amalga oshiring.</p>\n                            </div>\n                            <button type="button" @click.prevent="openOwnerLedgerFromFinance(form.machine_owner_id)" class="px-6 py-2 bg-neon-yellow text-black font-bold uppercase tracking-widest text-[10px] rounded hover:bg-white hover:text-black transition-colors shadow-[0_0_15px_rgba(252,238,9,0.3)]">Jurnalni Ochiq</button>\n                        </div>',
    text,
    flags=re.DOTALL
)

text = re.sub(
    r'(<div v-if="requiresBrigade">.*?</select>\s*</div>)',
    r'\1\n\n                        <div v-if="requiresBrigade && form.brigade_id" class="p-6 bg-neon-cyan/10 border border-neon-cyan/30 rounded-xl flex items-center justify-between">\n                            <div>\n                                <h4 class="text-neon-cyan font-bold uppercase tracking-widest text-sm">Brigada Jurnali</h4>\n                                <p class="text-text-muted text-[10px] font-mono mt-1">Bu toifa bo\'yicha to\'lovlarni to\'g\'ridan-to\'g\'ri maxsus jurnal orqali amalga oshiring.</p>\n                            </div>\n                            <button type="button" @click.prevent="openBrigadeLedgerFromFinance(form.brigade_id)" class="px-6 py-2 bg-neon-cyan text-black font-bold uppercase tracking-widest text-[10px] rounded hover:bg-white hover:text-black transition-colors shadow-[0_0_15px_rgba(0,255,255,0.3)]">Jurnalni Ochiq</button>\n                        </div>',
    text,
    flags=re.DOTALL
)

# Hide the rest of the form if it requires machine owner or brigade.
# We will wrap the generic inputs (amount, account_type, notes, submit button, etc.) in a div.
# Let's see what inputs are below the targets.
# The easiest way is to add `v-show="!requiresMachineOwner && !requiresBrigade"` to the container of the rest of the form.
# But wait, the form fields in Finance/Index.vue are individually laid out or grouped.
# Let's inspect the layout.
