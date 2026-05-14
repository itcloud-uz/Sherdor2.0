<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\FinancialLiability;
use App\Models\LiabilityPayment;
use Illuminate\Support\Facades\Schema;

class FinancialReportController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->input('category', 'overall');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $export = $request->input('export', false) === 'true' || $request->input('export') === true;

        $data = collect();

        $txQuery = Transaction::query()->with(['client', 'creator']);
        if ($startDate) $txQuery->whereDate('created_at', '>=', $startDate);
        if ($endDate) $txQuery->whereDate('created_at', '<=', $endDate);
        
        if (in_array($category, ['cash', 'overall', 'financial_flow'])) {
            $q = clone $txQuery;
            $q->where('payment_method', 'cash')->where('status', 'approved');
            $data = $data->merge($q->get()->map(fn($t) => [
                'id' => 'tx_'.$t->id,
                'date' => $t->created_at->format('Y-m-d H:i'),
                'type' => $t->type,
                'amount' => $t->amount,
                'category' => 'Kassa',
                'description' => $t->notes ?? ($t->client ? "Mijoz: {$t->client->first_name} {$t->client->last_name}" : "Tranzaksiya"),
            ]));
        }

        if (in_array($category, ['bank', 'overall', 'financial_flow'])) {
            $q = clone $txQuery;
            $q->whereIn('payment_method', ['card', 'bank_transfer'])->where('status', 'approved');
            $data = $data->merge($q->get()->map(fn($t) => [
                'id' => 'tx_'.$t->id,
                'date' => $t->created_at->format('Y-m-d H:i'),
                'type' => $t->type,
                'amount' => $t->amount,
                'category' => 'Hisob Raqam',
                'description' => $t->notes ?? ($t->client ? "Mijoz: {$t->client->first_name} {$t->client->last_name}" : "Tranzaksiya"),
            ]));
        }

        if (in_array($category, ['credit', 'overall', 'financial_flow'])) {
            if (Schema::hasTable('financial_liabilities')) {
                $lQuery = FinancialLiability::query();
                if ($startDate) $lQuery->whereDate('created_at', '>=', $startDate);
                if ($endDate) $lQuery->whereDate('created_at', '<=', $endDate);
                
                $data = $data->merge($lQuery->get()->map(fn($l) => [
                    'id' => 'fl_'.$l->id,
                    'date' => $l->created_at->format('Y-m-d H:i'),
                    'type' => 'income',
                    'amount' => $l->total_amount,
                    'category' => 'Kredit',
                    'description' => "Kredit olindi: {$l->creditor_name} - " . ($l->description ?? ''),
                ]));
            }

            if (Schema::hasTable('liability_payments')) {
                $lpQuery = LiabilityPayment::where('status', 'paid');
                if ($startDate) $lpQuery->whereDate('payment_date', '>=', $startDate);
                if ($endDate) $lpQuery->whereDate('payment_date', '<=', $endDate);

                $data = $data->merge($lpQuery->get()->map(fn($lp) => [
                    'id' => 'lp_'.$lp->id,
                    'date' => \Carbon\Carbon::parse($lp->payment_date)->format('Y-m-d H:i'),
                    'type' => 'expense',
                    'amount' => $lp->amount,
                    'category' => 'Kredit',
                    'description' => "Kredit to'landi: " . ($lp->notes ?? ''),
                ]));
            }
        }

        $data = $data->sortByDesc('date')->values();

        if ($export) {
            return $this->exportCsv($data, $category);
        }

        // Return paginated manually if needed, or just all records since it's a filtered report
        return response()->json([
            'data' => $data
        ]);
    }

    private function exportCsv($data, $category)
    {
        $fileName = "moliyaviy_hisobot_{$category}_" . date('Y_m_d_H_i') . ".csv";
        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['Sana', 'Tur', 'Kategoriya', 'Miqdor', 'Tafsilot'];

        $callback = function() use($data, $columns) {
            $file = fopen('php://output', 'w');
            // Add BOM to fix UTF-8 in Excel
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, $columns);

            foreach ($data as $item) {
                $row['Sana']  = $item['date'];
                $row['Tur']    = $item['type'] === 'income' ? 'Tushum' : 'Chiqim';
                $row['Kategoriya'] = $item['category'];
                $row['Miqdor']  = $item['amount'];
                $row['Tafsilot']  = $item['description'];

                fputcsv($file, array($row['Sana'], $row['Tur'], $row['Kategoriya'], $row['Miqdor'], $row['Tafsilot']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
