import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import * as XLSX from 'xlsx';

export function exportLedgerToPDF(title, columns, dataRows) {
    try {
        const doc = new jsPDF('landscape');
        
        doc.text(title, 14, 15);
        
        autoTable(doc, {
            head: [columns],
            body: dataRows,
            startY: 20,
            styles: { fontSize: 8, font: 'helvetica' },
            headStyles: { fillColor: [41, 128, 185], textColor: 255 },
            alternateRowStyles: { fillColor: [245, 245, 245] }
        });
        
        doc.save(`${title}.pdf`);
    } catch (e) {
        console.error("PDF Export Error: ", e);
        alert("PDF yaratishda xatolik yuz berdi. Konsol (F12) ga qarang.");
    }
}

export function exportLedgerToExcel(title, columns, dataRows) {
    // Combine columns and dataRows into array of objects
    const formattedData = dataRows.map(row => {
        const obj = {};
        columns.forEach((col, index) => {
            obj[col] = row[index];
        });
        return obj;
    });

    const worksheet = XLSX.utils.json_to_sheet(formattedData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Jurnal");
    XLSX.writeFile(workbook, `${title}.xlsx`);
}
