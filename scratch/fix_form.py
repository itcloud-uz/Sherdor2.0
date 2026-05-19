import re

path = r'd:\Sherdor 2.0\app\Http\Controllers\Admin\HrController.php'
with open(path, 'r', encoding='utf8') as f:
    text = f.read()

text = text.replace("if ($validated['type'] === 'worker' && $calculatedCost > 0)", "if ($validated['type'] === 'worker' && $calculatedCost != 0)")
text = text.replace("if ($validated['type'] === 'machinery' && $calculatedCost > 0)", "if ($validated['type'] === 'machinery' && $calculatedCost != 0)")

with open(path, 'w', encoding='utf8') as f:
    f.write(text)

path = r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue'
with open(path, 'r', encoding='utf8') as f:
    text = f.read()

# Add deductions to logForm
text = text.replace("description: '',", "description: '',\n    deductions: '',")

# Add logForm.value.deductions = '';
text = text.replace("logForm.value.description = '';", "logForm.value.description = '';\n            logForm.value.deductions = '';")

# Fix the Layout
# Right now, there is:
# <div class="grid grid-cols-1 sm:grid-cols-2 gap-6"> ... </div>
# <div class="grid grid-cols-1 sm:grid-cols-2 gap-6"> ... </div>
# <div v-if="logForm.type === 'worker' || logForm.type === 'machinery'" class="mt-4">... Izoh ...</div>

# I will replace the whole form structure with a clean 2-column layout.
# We'll use regex to grab the blocks.
