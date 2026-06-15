<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;

class LedgerExportController extends Controller
{
    public function exportPdf(Contract $contract, Request $request)
    {
        $contract->load(['client', 'unit.floor.building', 'paymentSchedules.transactions', 'transactions.category']);
        $type = $request->query('type'); // 'down_payment' or 'installments'

        $pdf = Pdf::loadView('exports.client_ledger', compact('contract', 'type'));
        $suffix = $type ? "_{$type}" : "";
        return $pdf->download("ledger_{$contract->id}{$suffix}.pdf");
    }

    public function exportDocx(Contract $contract, Request $request)
    {
        $contract->load(['client', 'unit.floor.building', 'paymentSchedules.transactions', 'transactions.category']);
        $type = $request->query('type');
        
        $suffix = $type ? "_{$type}" : "";
        $headers = [
            "Content-type" => "application/vnd.ms-word",
            "Content-Disposition" => "attachment;Filename=ledger_{$contract->id}{$suffix}.doc"
        ];

        return response()->view('exports.client_ledger', compact('contract', 'type'), 200, $headers);
    }

    public function exportXlsx(Contract $contract, Request $request)
    {
        $contract->load(['client', 'unit.floor.building', 'paymentSchedules.transactions', 'transactions.category']);
        $type = $request->query('type');
        
        $suffix = $type ? "_{$type}" : "";
        $headers = [
            "Content-type" => "application/vnd.ms-excel",
            "Content-Disposition" => "attachment;Filename=ledger_{$contract->id}{$suffix}.xls"
        ];

        return response()->view('exports.client_ledger', compact('contract', 'type'), 200, $headers);
    }

    public function exportContractPdf(Contract $contract, Request $request)
    {
        $contract->load(['client', 'unit.floor.building']);

        $locale = $request->query('lang', app()->getLocale());
        if (!in_array($locale, ['uz', 'ru', 'en'])) {
            $locale = 'uz'; // fallback
        }

        $type = $contract->contract_type ?? 'residential';
        if (!in_array($type, ['residential', 'commercial'])) {
            $type = 'residential';
        }
        
        $viewPath = "contracts.{$locale}.{$type}";

        $pdf = Pdf::loadView($viewPath, compact('contract'));
        return $pdf->download("contract_{$contract->id}.pdf");
    }
}
