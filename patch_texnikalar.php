<?php
$content = file_get_contents('resources/js/Pages/Admin/HR/Texnikalar.vue');
$content = str_replace(
    'const rows = ref([]); // current selected rows' . PHP_EOL . 'const draggedIdx = ref(null);',
    'const rows = ref([]); // current selected rows' . PHP_EOL . 'const activeTab = ref(\'works\'); // works, finance' . PHP_EOL . 'const draggedIdx = ref(null);',
    $content
);
$content = str_replace(
    '        { totalPrice: 0, totalPaid: 0, totalFuel: 0, totalBalance: 0 }' . PHP_EOL . '    );' . PHP_EOL . '});',
    '        { totalPrice: 0, totalPaid: 0, totalFuel: 0, totalBalance: 0 }' . PHP_EOL . '    );' . PHP_EOL . '    let financePaid = 0; if (selectedOwner.value && selectedOwner.value.transactions) { financePaid = selectedOwner.value.transactions.reduce((s, t) => s + (parseFloat(t.amount)||0), 0); } res.totalPaid += financePaid; res.totalBalance = res.totalPrice - res.totalPaid - res.totalFuel; return res; });',
    $content
);
$content = preg_replace(
    '/return rows\.value\.reduce\(/',
    'const res = rows.value.reduce(',
    $content
);
$content = str_replace(
    '<div class="p-6 flex flex-col flex-1 overflow-hidden">',
    '<div class="px-6 py-2 border-b border-gray-200 bg-gray-50 flex gap-2"> <button @click="activeTab = \'works\'" :class="activeTab === \'works\' ? \'bg-orange-500 text-white shadow-sm\' : \'text-gray-600 hover:text-gray-900 hover:bg-gray-200\'" class="px-4 py-2 rounded-md text-sm font-medium transition-all"> Bajarilgan Ishlar (Xizmatlar) </button> <button @click="activeTab = \'finance\'" :class="activeTab === \'finance\' ? \'bg-orange-500 text-white shadow-sm\' : \'text-gray-600 hover:text-gray-900 hover:bg-gray-200\'" class="px-4 py-2 rounded-md text-sm font-medium transition-all"> Moliya Tranzaksiyalari (To\'lovlar) </button> </div> <div class="p-6 flex flex-col flex-1 overflow-hidden">',
    $content
);
$content = str_replace(
    '<div class="flex justify-between items-center mb-4 flex-shrink-0">',
    '<div v-show="activeTab === \'works\'" class="flex justify-between items-center mb-4 flex-shrink-0">',
    $content
);
$content = str_replace(
    '<div class="overflow-auto border border-yellow-400 flex-1 custom-scrollbar">',
    '<div v-show="activeTab === \'works\'" class="overflow-auto border border-yellow-400 flex-1 custom-scrollbar">',
    $content
);
$financeTable = '<div v-show="activeTab === \'finance\'" class="overflow-auto border border-gray-300 rounded-lg flex-1 custom-scrollbar"><table class="w-full text-sm text-left text-gray-900 border-collapse"><thead class="sticky top-0 z-10 shadow-sm bg-gray-100"><tr><th class="border border-gray-200 px-4 py-3 text-center w-12 font-semibold text-gray-600">№</th><th class="border border-gray-200 px-4 py-3 font-semibold text-gray-600">Sana</th><th class="border border-gray-200 px-4 py-3 font-semibold text-gray-600">Turi</th><th class="border border-gray-200 px-4 py-3 font-semibold text-gray-600">Tavsif</th><th class="border border-gray-200 px-4 py-3 text-right font-semibold text-gray-600">So\'mma</th></tr></thead><tbody class="divide-y divide-gray-200 bg-white"><tr v-if="!selectedOwner?.transactions || selectedOwner.transactions.length === 0"><td colspan="5" class="px-6 py-12 text-center text-gray-400 text-sm">Moliya bo\'limidan kiritilgan to\'lovlar mavjud emas.</td></tr><tr v-else v-for="(tx, index) in selectedOwner.transactions" :key="tx.id" class="hover:bg-gray-50 transition-colors"><td class="border border-gray-200 px-4 py-2.5 text-center text-gray-500">{{ index + 1 }}</td><td class="border border-gray-200 px-4 py-2.5 text-gray-800">{{ tx.date || tx.created_at?.substring(0, 10) }}</td><td class="border border-gray-200 px-4 py-2.5"><span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full uppercase tracking-wider whitespace-nowrap">{{ tx.type === \'payment\' || tx.type === \'expense\' ? \'TO\\\'LOV\' : tx.type }}</span></td><td class="border border-gray-200 px-4 py-2.5 text-gray-700">{{ tx.description || tx.notes || \'To\\\'lov\' }}</td><td class="border border-gray-200 px-4 py-2.5 text-right font-bold text-green-700 bg-green-50/20">+{{ formatMoney(tx.amount) }}</td></tr></tbody></table></div>';
$content = str_replace(
    '                                        </tbody>' . PHP_EOL . '                                    </table>' . PHP_EOL . '                                </div>' . PHP_EOL . '                            </div>',
    '                                        </tbody>' . PHP_EOL . '                                    </table>' . PHP_EOL . '                                </div>' . PHP_EOL . $financeTable . PHP_EOL . '                            </div>',
    $content
);
file_put_contents('resources/js/Pages/Admin/HR/Texnikalar.vue', $content);
echo "Replaced";
