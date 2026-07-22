<?php

namespace App\Exports;

use App\Models\Worker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeLedgerExport implements FromView, ShouldAutoSize, WithStyles
{
    protected $employeeId;
    protected $type;
    protected $startDate;
    protected $endDate;

    public function __construct($employeeId, $type = 'worker', $startDate = null, $endDate = null)
    {
        $this->employeeId = $employeeId;
        $this->type = $type;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function view(): View
    {
        $tableName = $this->type === 'user' ? 'user_transactions' : 'worker_transactions';
        $foreignKey = $this->type === 'user' ? 'user_id' : 'worker_id';

        $employee = $this->type === 'user'
            ? User::findOrFail($this->employeeId)
            : Worker::findOrFail($this->employeeId);

        $txQuery = DB::table($tableName)
            ->where($foreignKey, $this->employeeId);

        if ($this->startDate) {
            $txQuery->where('date', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $txQuery->where('date', '<=', $this->endDate);
        }

        $allTx = $txQuery->orderBy('date', 'asc')->get();

        $accruals = [];
        $payments = [];

        foreach ($allTx as $tx) {
            if ($tx->type === 'salary_accrual') {
                $accruals[] = $tx;
            } else {
                $payments[] = $tx;
            }
        }

        $pairedRows = [];
        $maxLen = max(count($accruals), count($payments));
        for ($i = 0; $i < $maxLen; $i++) {
            $accRow = $accruals[$i] ?? null;
            $payRow = $payments[$i] ?? null;

            $accruedAmount = $accRow ? (float)$accRow->amount : 0;
            $paidAmount = $payRow ? (float)$payRow->amount : 0;
            $qoldiq = $paidAmount - $accruedAmount;

            $pairedRows[] = [
                'accrued_date' => $accRow ? $accRow->date : null,
                'accrued_amount' => $accruedAmount,
                'paid_date' => $payRow ? $payRow->date : null,
                'paid_amount' => $paidAmount,
                'qoldiq' => $qoldiq,
                'izoh' => $payRow ? $payRow->description : ($accRow ? $accRow->description : ''),
            ];
        }

        return view('exports.worker_ledger', [
            'worker' => $employee,
            'rows' => $pairedRows,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            2 => ['font' => ['bold' => true]],
        ];
    }
}
