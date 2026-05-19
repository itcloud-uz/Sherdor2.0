import re

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

# 1. Add imports for the new components
import_string = "import OwnerLedgerModal from '@/Components/Ledger/OwnerLedgerModal.vue';\nimport BrigadeLedgerModal from '@/Components/Ledger/BrigadeLedgerModal.vue';\n"
if "import BrigadeLedgerModal" not in text:
    text = text.replace("import { ref, computed } from 'vue';", "import { ref, computed } from 'vue';\n" + import_string)

# 2. Add the components to the template
components_template = """
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

# We need to find the old Brigade Ledger Modal and Owner Ledger Modal and replace them
# The old ones start with <!-- Brigade Ledger Modal (Excel Layout) --> and end before <!-- Worker Payroll Modal --> or similar.
# Let's just use regex to remove the specific modal HTMLs.

pattern_brigade = r'<!-- Brigade Ledger Modal \(Excel Layout\) -->\s*<div v-if="showBrigadeLedgerModal".*?</div>\s*</div>\s*</div>'
text = re.sub(pattern_brigade, '', text, flags=re.DOTALL)

pattern_owner = r'<!-- Owner Ledger Modal \(Excel Layout\) -->\s*<div v-if="showOwnerLedgerModal".*?</div>\s*</div>\s*</div>'
text = re.sub(pattern_owner, '', text, flags=re.DOTALL)

# Insert the new components right before closing </AdminLayout>
text = text.replace("    </AdminLayout>", components_template + "\n    </AdminLayout>")

# 3. Add handlers for the emitted events
# Currently submitBrigadeLedger uses brigadeLedgerForm directly.
# The new component emits the form payload. Let's add submit...FromEvent functions.

handlers = """
// Handlers for the new Ledger Components
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
            openBrigadeLedger(activeBrigadeForLedger.value); // refresh
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
            openOwnerLedger(activeOwnerForLedger.value); // refresh
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
"""
text = text.replace("</script>", handlers + "\n</script>")

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'w', encoding='utf-8') as f:
    f.write(text)

print("HR/Index.vue successfully refactored!")
