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
        $contract->load(['client', 'unit.floor.building', 'template']);

        $locale = $request->query('lang', app()->getLocale());
        if (!in_array($locale, ['uz', 'ru', 'en'])) {
            $locale = 'uz'; // fallback
        }

        if ($contract->contract_template_id && $contract->template && $contract->template->docx_path) {
            try {
                $selectedVariants = $contract->selected_variants ?? [];
                $docxPath = $contract->template->compileDocx($contract, $selectedVariants);
                $absoluteDocxPath = storage_path('app/public/' . str_replace('public/', '', $docxPath));

                // Convert docx to html
                $previewOutput = \App\Models\ContractTemplate::runPython('preview "' . $absoluteDocxPath . '"');
                $previewData = json_decode($previewOutput, true);
                
                $content = $previewData['html'] ?? '';
                
                $pdf = Pdf::loadView('contracts.compiled', compact('content'));
                return $pdf->download("contract_{$contract->id}.pdf");
            } catch (\Exception $e) {
                return back()->withErrors(['message' => 'PDF generatsiya qilishda xatolik: ' . $e->getMessage()]);
            }
        } elseif ($contract->contract_template_id && $contract->template) {
            $content = $contract->template->compile($contract);
            $pdf = Pdf::loadView('contracts.compiled', compact('content'));
            return $pdf->download("contract_{$contract->id}.pdf");
        } else {
            $type = $contract->contract_type ?? 'residential';
            if (!in_array($type, ['residential', 'commercial'])) {
                $type = 'residential';
            }
            $viewPath = "contracts.{$locale}.{$type}";
            $pdf = Pdf::loadView($viewPath, compact('contract'));
            return $pdf->download("contract_{$contract->id}.pdf");
        }
    }

    public function exportContractDocx(Contract $contract, Request $request)
    {
        $contract->load(['client', 'unit.floor.building', 'template']);

        if ($contract->contract_template_id && $contract->template && $contract->template->docx_path) {
            try {
                $selectedVariants = $contract->selected_variants ?? [];
                $docxPath = $contract->template->compileDocx($contract, $selectedVariants);
                $absoluteDocxPath = storage_path('app/public/' . str_replace('public/', '', $docxPath));

                return response()->download($absoluteDocxPath, "contract_{$contract->id}.docx");
            } catch (\Exception $e) {
                return back()->withErrors(['message' => 'DOCX shablonini generatsiya qilishda xatolik: ' . $e->getMessage()]);
            }
        }

        return back()->withErrors(['message' => 'Ushbu shartnoma uchun DOCX shabloni mavjud emas.']);
    }

    public function previewLedger(Contract $contract, Request $request)
    {
        $contract->load(['client', 'unit.floor.building', 'paymentSchedules.transactions', 'transactions.category']);
        $type = $request->query('type');
        return view('exports.client_ledger', compact('contract', 'type'));
    }

    public function previewContract(Contract $contract, Request $request)
    {
        $contract->load(['client', 'unit.floor.building', 'template']);

        $locale = $request->query('lang', app()->getLocale());
        if (!in_array($locale, ['uz', 'ru', 'en'])) {
            $locale = 'uz'; // fallback
        }

        if ($contract->contract_template_id && $contract->template) {
            if ($contract->template->docx_path) {
                try {
                    $selectedVariants = $contract->selected_variants ?? [];
                    $docxPath = $contract->template->compileDocx($contract, $selectedVariants);
                    $absoluteDocxPath = storage_path('app/public/' . str_replace('public/', '', $docxPath));

                    $previewOutput = \App\Models\ContractTemplate::runPython('preview "' . $absoluteDocxPath . '"');
                    $previewData = json_decode($previewOutput, true);
                    
                    $content = $previewData['html'] ?? '';
                } catch (\Exception $e) {
                    $content = 'Preview generation failed: ' . $e->getMessage();
                }
            } else {
                $content = $contract->template->compile($contract);
            }
            return view('contracts.compiled', compact('content'));
        } else {
            $type = $contract->contract_type ?? 'residential';
            if (!in_array($type, ['residential', 'commercial'])) {
                $type = 'residential';
            }
            $viewPath = "contracts.{$locale}.{$type}";
            return view($viewPath, compact('contract'));
        }
    }
}
