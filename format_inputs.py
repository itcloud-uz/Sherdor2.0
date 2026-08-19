import codecs
import re

path = "resources/js/Pages/Admin/Finance/Index.vue"
with codecs.open(path, 'r', 'utf-8') as f:
    content = f.read()

# Add the functions right before unction formatMoney(
functions_code = """
function formatSpaces(val) {
    if (!val && val !== 0) return '';
    let str = val.toString().replace(/\s/g, '');
    let parts = str.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return parts.join('.');
}

function unformatSpaces(val) {
    if (!val) return '';
    return val.toString().replace(/\s/g, '');
}

"""

if "function formatSpaces(" not in content:
    content = content.replace("function formatMoney(", functions_code + "function formatMoney(")

# Regex to find all v-model="var.amount" ... type="number"
# or type="number" ... v-model="var.amount"
# Actually, since the attributes can be in any order, let's just do targeted replacements.
variables = [
    "form.amount",
    "editTransactionForm.amount",
    "registerForm.total_amount",
    "liabilityForm.total_amount",
    "paymentForm.amount",
    "supplierPaymentForm.amount",
    "contractApproveForm.applied_amount",
    "contractApproveForm.refund_amount",
    "transferForm.sent_amount",
    "form.converted_amount",
    "editTransactionForm.converted_amount",
    "transferForm.converted_amount"
]

for var in variables:
    # Pattern: v-model="var" and type="number"
    # We will search line by line
    lines = content.split('\n')
    for i, line in enumerate(lines):
        if 'v-model="' + var + '"' in line and 'type="number"' in line:
            new_line = line.replace('v-model="' + var + '"', f':value="formatSpaces({var})" @input="{var} = unformatSpaces(\.target.value)"')
            new_line = new_line.replace('type="number"', 'type="text" inputmode="decimal"')
            lines[i] = new_line
    content = '\n'.join(lines)

with codecs.open(path, 'w', 'utf-8') as f:
    f.write(content)

print("Formatting applied.")
