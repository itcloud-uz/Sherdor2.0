<?php

namespace App\Exports;

use App\Models\MachineOwner;
use App\Models\WorkLog;
use App\Models\MachineryTransaction;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OwnerLedgerExport implements FromView, ShouldAutoSize, WithStyles
{
    protected $ownerId;

    public function __construct($ownerId)
    {
        $this->ownerId = $ownerId;
    }

    public function view(): View
    {
        $owner = MachineOwner::with('machineries')->findOrFail($this->ownerId);
        $machineryIds = $owner->machineries->pluck('id');

        $logs = WorkLog::with(['building', 'machinery'])
            ->whereIn('machinery_id', $machineryIds)
            ->orderBy('date', 'asc')
            ->get();

        $transactions = MachineryTransaction::with('machinery')
            ->whereIn('machinery_id', $machineryIds)
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

        return view('exports.owner_ledger', [
            'owner' => $owner,
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
