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
        
        // Push only if there's actual data in the row, or empty row if i=0
        if (inc || exp || i === 0) {
            data.push([incDate, incBank, incCash, expDate, expBank, expCash, ""]);
        }
    }
    
    data.push(["Jami", sumIncomeBank, sumIncomeCash, "", sumExpenseBank, sumExpenseCash, ""]);
    
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
                sheet[cell].z = '# ##0';
            }
        }
    });
    
    XLSX.writeFile(wb, ${name}_Jurnal.xlsx);
};
'''

content = content.replace("const isReceiptModalOpen = ref(false);", state_injection + "\nconst isReceiptModalOpen = ref(false);")

button_target = '''<!-- Quick pay debt button -->'''
button_replacement = '''<!-- Quick pay debt button -->
                                        <button @click="openDebtLedger(c)" class="px-3 py-1 border rounded-lg text-xs font-bold transition-all shadow-sm font-sans bg-blue-50 border-blue-200 text-blue-700 hover:bg-blue-500 hover:text-white">
                                            Hisob-kitob jurnali
                                        </button>'''
content = content.replace(button_target, button_replacement)

modal_code = '''
        <Modal :show="isDebtLedgerOpen" @close="closeDebtLedger" maxWidth="5xl">
            <div v-if="selectedDebtClient" class="p-6">
                <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6">
                    <h2 class="text-xl font-bold text-gray-900">{{ selectedDebtClient.name }} - Hisob kitob jurnali</h2>
                    
                    <div class="flex items-center gap-2">
                        <input v-model="debtLedgerStartDate" type="date" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500" title="Boshlanish sanasi">
                        <span class="text-gray-500">-</span>
                        <input v-model="debtLedgerEndDate" type="date" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500" title="Tugash sanasi">
                        
                        <button @click="exportDebtClientExcel" class="px-4 py-2 bg-green-50 text-green-700 hover:bg-green-600 hover:text-white rounded-lg font-bold transition-colors flex items-center text-sm shadow-sm border border-green-200 ml-2">
                            ⬇ Excel
                        </button>
                        <button @click="closeDebtLedger" class="text-gray-400 hover:text-gray-600 ml-1">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto border rounded-lg mb-6 shadow-sm">
                    <table class="w-full text-sm text-center">
                        <thead class="bg-gray-50 border-b text-gray-700">
                            <tr>
                                <th class="p-3 border-r">Shartnoma sanasi</th>
                                <th class="p-3 border-r">Shartnoma №</th>
                                <th class="p-3 border-r">Uy №</th>
                                <th class="p-3 border-r">Qavat</th>
                                <th class="p-3 border-r">Tel raqam</th>
                                <th class="p-3 border-r">Umumiy qoldiq</th>
                                <th class="p-3">Izoh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="p-3 border-r">{{ selectedDebtClient.customable?.contracts?.[0]?.created_at ? new Date(selectedDebtClient.customable.contracts[0].created_at).toLocaleDateString('uz-UZ') : '-' }}</td>
                                <td class="p-3 border-r">{{ selectedDebtClient.customable?.contracts?.[0]?.contract_number || '-' }}</td>
                                <td class="p-3 border-r">{{ selectedDebtClient.customable?.contracts?.[0]?.unit?.unit_number || '-' }}</td>
                                <td class="p-3 border-r">{{ selectedDebtClient.customable?.contracts?.[0]?.unit?.floor?.floor_number || '-' }}</td>
                                <td class="p-3 border-r">{{ selectedDebtClient.phone || '-' }}</td>
                                <td class="p-3 border-r font-bold" :class="selectedDebtClient.balance > 0 ? 'text-emerald-600' : 'text-red-600'">{{ Number(selectedDebtClient.balance).toLocaleString('uz-UZ') }}</td>
                                <td class="p-3"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="overflow-x-auto border rounded-lg shadow-sm">
                    <table class="w-full text-sm text-center">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr class="border-b">
                                <th colspan="3" class="p-3 border-r bg-blue-50 text-blue-800">To'langan</th>
                                <th colspan="3" class="p-3 border-r bg-emerald-50 text-emerald-800">Qaytarilgan</th>
                                <th rowspan="2" class="p-3 bg-gray-50">Izoh</th>
                            </tr>
                            <tr class="border-b">
                                <th class="p-2 border-r">Sana</th>
                                <th class="p-2 border-r">Bank orqali</th>
                                <th class="p-2 border-r">Naqt</th>
                                <th class="p-2 border-r">Sana</th>
                                <th class="p-2 border-r">Bank orqali</th>
                                <th class="p-2 border-r">Naqt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="i in debtLedgerMaxLength" :key="i">
                                <!-- Income -->
                                <td class="p-2 border-r text-gray-600">{{ debtLedgerIncomes[i-1] ? new Date(debtLedgerIncomes[i-1].transaction_date || debtLedgerIncomes[i-1].created_at).toLocaleDateString('uz-UZ') : '' }}</td>
                                <td class="p-2 border-r">{{ debtLedgerIncomes[i-1] && (debtLedgerIncomes[i-1].payment_method === 'bank_transfer' || debtLedgerIncomes[i-1].payment_method === 'card') ? Number(debtLedgerIncomes[i-1].amount).toLocaleString('uz-UZ') : '' }}</td>
                                <td class="p-2 border-r">{{ debtLedgerIncomes[i-1] && debtLedgerIncomes[i-1].payment_method === 'cash' ? Number(debtLedgerIncomes[i-1].amount).toLocaleString('uz-UZ') : '' }}</td>
                                
                                <!-- Expense -->
                                <td class="p-2 border-r text-gray-600">{{ debtLedgerExpenses[i-1] ? new Date(debtLedgerExpenses[i-1].transaction_date || debtLedgerExpenses[i-1].created_at).toLocaleDateString('uz-UZ') : '' }}</td>
                                <td class="p-2 border-r">{{ debtLedgerExpenses[i-1] && (debtLedgerExpenses[i-1].payment_method === 'bank_transfer' || debtLedgerExpenses[i-1].payment_method === 'card') ? Number(debtLedgerExpenses[i-1].amount).toLocaleString('uz-UZ') : '' }}</td>
                                <td class="p-2 border-r">{{ debtLedgerExpenses[i-1] && debtLedgerExpenses[i-1].payment_method === 'cash' ? Number(debtLedgerExpenses[i-1].amount).toLocaleString('uz-UZ') : '' }}</td>
                                
                                <td class="p-2"></td>
                            </tr>
                            <tr class="bg-gray-50 border-t font-bold">
                                <td class="p-3 border-r">Jami</td>
                                <td class="p-3 border-r text-blue-700">{{ Number(debtLedgerIncomes.filter(t => t.payment_method === 'bank_transfer' || t.payment_method === 'card').reduce((sum, t) => sum + Number(t.amount || 0), 0)).toLocaleString('uz-UZ') }}</td>
                                <td class="p-3 border-r text-blue-700">{{ Number(debtLedgerIncomes.filter(t => t.payment_method === 'cash').reduce((sum, t) => sum + Number(t.amount || 0), 0)).toLocaleString('uz-UZ') }}</td>
                                <td class="p-3 border-r"></td>
                                <td class="p-3 border-r text-emerald-700">{{ Number(debtLedgerExpenses.filter(t => t.payment_method === 'bank_transfer' || t.payment_method === 'card').reduce((sum, t) => sum + Number(t.amount || 0), 0)).toLocaleString('uz-UZ') }}</td>
                                <td class="p-3 border-r text-emerald-700">{{ Number(debtLedgerExpenses.filter(t => t.payment_method === 'cash').reduce((sum, t) => sum + Number(t.amount || 0), 0)).toLocaleString('uz-UZ') }}</td>
                                <td class="p-3"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Modal>
'''
content = content.replace("    </AuthenticatedLayout>\n</template>", modal_code + "\n    </AuthenticatedLayout>\n</template>")

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print("Updated Index.vue with clean logic!")
