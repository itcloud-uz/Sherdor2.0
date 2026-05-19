import re

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

script_html = """
async function submitBrigadeLedger() {
    if (!activeBrigadeForLedger.value) return;
    try {
        const response = await fetch(route('hr.brigade.transactions.store', activeBrigadeForLedger.value.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify(brigadeLedgerForm.value)
        });
        if (response.ok) {
            brigadeLedgerForm.value = { amount: '', description: '', type: 'payment', date: new Date().toISOString().split('T')[0] };
            openBrigadeLedger(activeBrigadeForLedger.value); // refresh
        } else {
            alert('Tranzaksiyani saqlashda xatolik!');
        }
    } catch(err) {
        console.error(err);
        alert('Tranzaksiyani saqlashda xatolik!');
    }
}
"""

# Insert right after openBrigadeLedger function
target_function = "function openBrigadeLedger"

if target_function in text:
    # Find the end of openBrigadeLedger
    # It ends with } catch(err) { ... } }
    pattern = r"(async function openBrigadeLedger\(brigade\) \{.*?\n\s*\}[ \t]*\n\s*\}[ \t]*\n)"
    match = re.search(pattern, text, re.DOTALL)
    if match:
        new_text = text[:match.end()] + script_html + text[match.end():]
        with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'w', encoding='utf-8') as f:
            f.write(new_text)
        print("submitBrigadeLedger successfully injected!")
    else:
        print("Could not find end of openBrigadeLedger.")
else:
    print("Target function not found.")
