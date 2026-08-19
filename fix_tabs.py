import codecs

path = "resources/js/Pages/Admin/HR/QurilishIshHaqi.vue"
with codecs.open(path, 'r', 'utf-8') as f:
    content = f.read()

# Replace <div v-else-if="brigadeTab === 'tranzaksiyalar'" class="p-6">
# with <div class="p-6 mt-6 border-t border-gray-200">
content = content.replace(
    "<div v-else-if=\"brigadeTab === 'tranzaksiyalar'\" class=\"p-6\">",
    "<!-- TRANZAKSIYALAR TAB NOW EMBEDDED -->\n                            <div class=\"mt-8 pt-6 border-t border-gray-200\">"
)

# Also add an Excel Yuklab olish button for transactions
# Wait, the transactions header is:
# <h3 class="text-lg font-bold text-gray-800">Moliya Bo'limidan O'tkazmalar</h3>
# We will add a button next to it.
transactions_header = '''<div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-bold text-gray-800">Moliya Bo'limidan O'tkazmalar (Avans va To'lovlar)</h3>'''

new_transactions_header = '''<div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-bold text-gray-800">Moliya Bo'limidan O'tkazmalar (Avans va To'lovlar)</h3>
                                    <button @click="exportTransactionsToExcel" class="px-3 py-2 bg-green-50 text-green-600 border border-green-200 rounded-lg hover:bg-green-100 transition flex items-center text-sm font-medium shadow-sm">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        Excel (To'lovlar)
                                    </button>'''

content = content.replace('''<div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-bold text-gray-800">Moliya Bo'limidan O'tkazmalar</h3>''', new_transactions_header)


# We need to implement exportTransactionsToExcel method in script
export_method = '''
const exportTransactionsToExcel = () => {
    if (!selectedBrigade.value) return;
    if (!selectedBrigade.value.transactions || selectedBrigade.value.transactions.length === 0) {
        alert("Yuklab olish uchun to'lovlar mavjud emas!");
        return;
    }
    try {
        const header = [["Sana", "Turi", "Izoh", "Summa (UZS)"]];
        const rows = selectedBrigade.value.transactions.map(txn => {
            let type = txn.type;
            if (type === 'payment') type = "To'lov / Avans";
            else if (type === 'salary_accrual') type = "Ish haqi yozilishi";
            else if (type === 'penalty') type = "Jarima / Ushlanma";
            
            return [
                new Date(txn.date || txn.created_at).toLocaleDateString('uz-UZ'),
                type,
                txn.description || '-',
                Number(txn.amount || 0)
            ];
        });
        
        // Jami summa
        const totalAmount = selectedBrigade.value.transactions.reduce((sum, t) => sum + Number(t.amount || 0), 0);
        rows.push([]);
        rows.push(["", "", "JAMI:", totalAmount]);

        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet([...header, ...rows]);
        
        // Formating
        ws['!cols'] = [{wch: 15}, {wch: 25}, {wch: 40}, {wch: 20}];
        
        XLSX.utils.book_append_sheet(wb, ws, "To'lovlar");
        XLSX.writeFile(wb, ${selectedBrigade.value.name.replace(/\s+/g, '_')}_Tolovlar.xlsx);
    } catch (error) {
        console.error("Excel yaratishda xatolik:", error);
    }
};
'''

content = content.replace("const exportToExcel = () => {", export_method + "\nconst exportToExcel = () => {")

with codecs.open(path, 'w', 'utf-8') as f:
    f.write(content)

print("Tabs embedded and export button added")
