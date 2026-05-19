import re

modal_html = """
        <!-- Owner Ledger Modal (Excel Layout) -->
        <div v-if="showOwnerLedgerModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showOwnerLedgerModal = false"></div>
            <div class="relative bg-[#a1c4fd] text-black border-2 border-black p-4 rounded max-w-7xl w-full max-h-[90vh] overflow-y-auto font-sans shadow-2xl animate-[fadeIn_0.3s_ease-out]">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-center w-full uppercase tracking-wider">{{ activeOwnerForLedger?.name }}</h2>
                    <button @click="showOwnerLedgerModal = false" class="absolute right-4 top-4 text-red-600 hover:text-red-800 font-bold text-xl">✕</button>
                </div>
                
                <div class="overflow-x-auto border-2 border-black bg-white">
                    <table class="w-full text-sm border-collapse border-2 border-black min-w-[800px]">
                        <thead>
                            <tr class="bg-[#fff2cc]">
                                <th rowspan="2" class="border border-black p-2 w-10 text-center">№</th>
                                <th rowspan="2" class="border border-black p-2 w-24 text-center">Сана</th>
                                <th colspan="4" class="border border-black p-2 text-center uppercase font-bold tracking-wider">БАЖАРИЛГАН ИШЛАР</th>
                                <th colspan="2" class="border border-black p-2 text-center uppercase font-bold tracking-wider">ТЎЛАНДИ</th>
                                <th rowspan="2" class="border border-black p-2 w-32 text-center font-bold">Қолдиқ</th>
                            </tr>
                            <tr class="bg-[#fff2cc]">
                                <th class="border border-black p-2 text-center font-bold">Номи</th>
                                <th class="border border-black p-2 w-20 text-center font-bold">Сони<br><span class="text-[10px] font-normal">(соат,рейси)</span></th>
                                <th class="border border-black p-2 w-28 text-center font-bold">Нархи<br><span class="text-[10px] font-normal">(соат,рейси)</span></th>
                                <th class="border border-black p-2 w-32 text-center font-bold">Жами нархи</th>
                                <th class="border border-black p-2 w-32 text-center font-bold">Тўланди</th>
                                <th class="border border-black p-2 w-32 text-center font-bold">Саларка<br>олиб бердик</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in ownerLedgerData.items" :key="i" class="hover:bg-blue-50">
                                <td class="border border-black p-1 text-center font-bold">{{ i + 1 }}</td>
                                <td class="border border-black p-1 text-center">{{ new Date(row.date).toLocaleDateString('ru-RU') }}</td>
                                
                                <td class="border border-black p-1 pl-2">
                                    <template v-if="row.type === 'log'">
                                        {{ row.item.machinery?.name }} <span class="text-xs text-gray-500">({{ row.item.building?.name }})</span>
                                    </template>
                                    <template v-else>
                                        Пул кўчирмаси / Тўлов
                                    </template>
                                </td>
                                
                                <td class="border border-black p-1 text-center">
                                    {{ row.type === 'log' ? (row.item.hours_worked || row.item.volume_done || '1') : '' }}
                                </td>
                                
                                <td class="border border-black p-1 text-right pr-2">
                                    {{ row.type === 'log' ? $currency(row.item.rate || 0).replace('UZS', '') : '' }}
                                </td>
                                
                                <td class="border border-black p-1 text-right pr-2">
                                    {{ row.type === 'log' ? $currency((row.item.calculated_cost + (row.item.deductions || 0)) || 0).replace('UZS', '') : '' }}
                                </td>
                                
                                <td class="border border-black p-1 text-right pr-2">
                                    {{ row.type === 'tx' ? $currency(row.item.amount || 0).replace('UZS', '') : '' }}
                                </td>
                                
                                <td class="border border-black p-1 text-right pr-2">
                                    {{ (row.type === 'log' && row.item.deductions > 0) ? $currency(row.item.deductions).replace('UZS', '') : '' }}
                                </td>
                                
                                <td class="border border-black p-1 text-right pr-2 font-mono">
                                    <template v-if="row.type === 'log'">
                                        {{ row.item.calculated_cost > 0 ? '- ' : '' }}{{ $currency(Math.abs(row.item.calculated_cost)).replace('UZS', '') }}
                                    </template>
                                    <template v-else>
                                        {{ $currency(row.item.amount).replace('UZS', '') }}
                                    </template>
                                </td>
                            </tr>
                            
                            <!-- JAMI ROW -->
                            <tr class="font-bold bg-white">
                                <td colspan="3" class="border border-black p-2 text-center uppercase">ЖАМИ</td>
                                <td class="border border-black p-2"></td>
                                <td class="border border-black p-2 text-right pr-2"></td>
                                <td class="border border-black p-2 text-right pr-2">
                                    {{ $currency(ownerLedgerData.items?.filter(i => i.type === 'log').reduce((acc, curr) => acc + (parseFloat(curr.item.calculated_cost) + parseFloat(curr.item.deductions || 0)), 0) || 0).replace('UZS', '') }}
                                </td>
                                <td class="border border-black p-2 text-right pr-2">
                                    {{ $currency(ownerLedgerData.items?.filter(i => i.type === 'tx').reduce((acc, curr) => acc + parseFloat(curr.item.amount || 0), 0) || 0).replace('UZS', '') }}
                                </td>
                                <td class="border border-black p-2 text-right pr-2">
                                    {{ $currency(ownerLedgerData.items?.filter(i => i.type === 'log').reduce((acc, curr) => acc + parseFloat(curr.item.deductions || 0), 0) || 0).replace('UZS', '') }}
                                </td>
                                <td class="border border-black p-2 text-right pr-2" :class="ownerLedgerData.balance < 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ ownerLedgerData.balance <= 0 ? '+ ' : '- ' }}{{ $currency(Math.abs(ownerLedgerData.balance)).replace('UZS', '') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
"""

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

text = re.sub(
    r'(<div v-if="showMachineryLedgerModal")',
    lambda match: modal_html + '\n        ' + match.group(1),
    text
)

with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'w', encoding='utf-8') as f:
    f.write(text)

print('Success')
