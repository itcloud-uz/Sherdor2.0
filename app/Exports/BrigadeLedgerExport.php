<?php

namespace App\Exports;

use App\Models\Brigade;
use App\Models\WorkLog;
use App\Models\BrigadeTransaction;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BrigadeLedgerExport implements FromView, ShouldAutoSize, WithStyles
{
    protected $brigadeId;

    public function __construct($brigadeId)
    {
        $this->brigadeId = $brigadeId;
    }

    public function view(): View
    {
        $brigade = Brigade::with('workers')->findOrFail($this->brigadeId);
        $workerIds = $brigade->workers->pluck('id');

        $logs = WorkLog::with(['building', 'worker'])
            ->where('type', 'worker')
            ->whereIn('worker_id', $workerIds)
            ->orderBy('date', 'asc')
            ->get();

        $transactions = BrigadeTransaction::where('brigade_id', $this->brigadeId)
            ->orderBy('date', 'asc')
            ->get();
            
        $items = collect([]);
        foreach ($logs as $log) {
            $items->push([
                'type' => 'log',
                'date' => $log->date,
                'created_at' => $log->created_at,
                'item' => $log
            ]);
        }
        foreach ($transactions as $tx) {
            $items->push([
                'type' => 'tx',
                'date' => $tx->date,
                'created_at' => $tx->created_at,
                'item' => $tx
            ]);
        }
        
        $items = $items->sortBy([
            ['date', 'asc'],
            ['created_at', 'asc'],
        ])->values();

        return view('exports.brigade_ledger_excel', [
            'brigade' => $brigade,
            'items' => $items,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['bold' => true]],
        ];
    }
}
