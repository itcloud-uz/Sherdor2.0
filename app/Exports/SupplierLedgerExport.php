<?php

namespace App\Exports;

use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SupplierLedgerExport implements FromView, ShouldAutoSize, WithStyles
{
    protected $supplier;

    public function __construct(Supplier $supplier)
    {
        $this->supplier = $supplier;
    }

    public function view(): View
    {
        $this->supplier->load(['orders' => function($q) {
            $q->where('status', 'delivered')->with('items')->orderBy('order_date');
        }]);

        $payments = Transaction::where('supplier_id', $this->supplier->id)
                        ->where('type', 'expense')
                        ->orderBy('transaction_date')
                        ->get();

        return view('exports.supplier_ledger', [
            'supplier' => $this->supplier,
            'orders' => $this->supplier->orders,
            'payments' => $payments
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            // Apply some basic styling if needed, but FromView handles most HTML tables gracefully
        ];
    }
}
