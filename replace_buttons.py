import re

with open('resources/js/Pages/Admin/Finance/Index.vue', 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace('<button v-if="isAdmin" @click="openEditTransactionModal(t)"', '<button v-if="hasPerm(\'edit_transaction\')" @click="openEditTransactionModal(t)"')
content = content.replace('<button v-if="isAdmin" @click="deleteTransaction(t.id)"', '<button v-if="hasPerm(\'delete_transaction\')" @click="deleteTransaction(t.id)"')

# Now add the Request buttons next to the edit and delete buttons.
# We'll use regex to insert them right after the closing </button> tag of the edit/delete buttons.

# Edit button replacement
edit_pattern = r'(<button v-if="hasPerm\(\'edit_transaction\'\)" @click="openEditTransactionModal\(t\)".*?</button>)'
request_edit_btn = r'''\1
                                            <button v-if="!hasPerm('edit_transaction') && t.status !== 'rejected'" @click="requestTransactionEdit(t, 'edit')" class="p-1 text-blue-500 hover:text-blue-700 border border-blue-200 rounded-md hover:bg-blue-50 transition-all" title="Adminga murojaat (Tahrirlash)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                            </button>'''
content = re.sub(edit_pattern, request_edit_btn, content, flags=re.DOTALL)

# Delete button replacement
delete_pattern = r'(<button v-if="hasPerm\(\'delete_transaction\'\)" @click="deleteTransaction\(t\.id\)".*?</button>)'
request_delete_btn = r'''\1
                                            <button v-if="!hasPerm('delete_transaction') && t.status !== 'rejected'" @click="requestTransactionEdit(t, 'delete')" class="p-1 text-orange-500 hover:text-orange-700 border border-orange-200 rounded-md hover:bg-orange-50 transition-all" title="Adminga murojaat (O\'chirish)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </button>'''
content = re.sub(delete_pattern, request_delete_btn, content, flags=re.DOTALL)

with open('resources/js/Pages/Admin/Finance/Index.vue', 'w', encoding='utf-8') as f:
    f.write(content)

print("Done")
