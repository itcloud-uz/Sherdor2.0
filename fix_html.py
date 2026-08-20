import sys
import re

file_path = 'resources/js/Pages/Admin/CRM/Index.vue'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

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
                                <td class="p-2 border-r text-gray-600">{{ debtLedgerIncomes[i-1] ? new Date(debtLedgerIncomes[i-1].transaction_date || debtLedgerIncomes[i-1].created_at).toLocaleDateString('uz-UZ') : '' }}</td>
                                <td class="p-2 border-r">{{ debtLedgerIncomes[i-1] && (debtLedgerIncomes[i-1].payment_method === 'bank_transfer' || debtLedgerIncomes[i-1].payment_method === 'card') ? Number(debtLedgerIncomes[i-1].amount).toLocaleString('uz-UZ') : '' }}</td>
                                <td class="p-2 border-r">{{ debtLedgerIncomes[i-1] && debtLedgerIncomes[i-1].payment_method === 'cash' ? Number(debtLedgerIncomes[i-1].amount).toLocaleString('uz-UZ') : '' }}</td>
                                
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
if "isDebtLedgerOpen" not in content[content.rfind("</template>")-1000:]:
    content = content.replace("    </component>\n</template>", modal_code + "\n    </component>\n</template>")
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
    print("Injected properly!")
else:
    print("Already injected or couldn't find")
