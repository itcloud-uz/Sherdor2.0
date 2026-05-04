<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FinancialLiability;
use Barryvdh\DomPDF\Facade\Pdf;

class LiabilityExportController extends Controller
{
    public function exportPdf(FinancialLiability $liability)
    {
        $liability->load(['payments']);

        $pdf = Pdf::loadView('exports.liability_ledger', compact('liability'));
        return $pdf->download("liability_{$liability->id}.pdf");
    }

    public function exportDocx(FinancialLiability $liability)
    {
        $liability->load(['payments']);
        
        $headers = [
            "Content-type" => "application/vnd.ms-word",
            "Content-Disposition" => "attachment;Filename=liability_{$liability->id}.doc"
        ];

        return response()->view('exports.liability_ledger', compact('liability'), 200, $headers);
    }

    public function exportXlsx(FinancialLiability $liability)
    {
        $liability->load(['payments']);
        
        $headers = [
            "Content-type" => "application/vnd.ms-excel",
            "Content-Disposition" => "attachment;Filename=liability_{$liability->id}.xls"
        ];

        return response()->view('exports.liability_ledger', compact('liability'), 200, $headers);
    }
}
