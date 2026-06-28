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

        // Resolve a template if not selected, so we never fall back to broken/old hardcoded blade views
        $template = $contract->template;
        if (!$template && $contract->contract_template_id) {
            $template = \App\Models\ContractTemplate::find($contract->contract_template_id);
        }
        if (!$template) {
            $type = $contract->contract_type ?? 'residential';
            if (!in_array($type, ['residential', 'commercial'])) {
                $type = 'residential';
            }
            $template = \App\Models\ContractTemplate::where('type', $type)->latest()->first();
        }

        // Generate PDF using DOCX template
        if ($template && $template->docx_path) {
            try {
                $selectedVariants = $contract->selected_variants ?? [];
                $docxPath = $template->compileDocx($contract, $selectedVariants);
                $absoluteDocxPath = storage_path('app/public/' . str_replace('public/', '', $docxPath));

                // 1. Try LibreOffice/soffice conversion first for pixel-perfect PDF rendering
                $outputDirectory = dirname($absoluteDocxPath);
                $pdfFileName = pathinfo($absoluteDocxPath, PATHINFO_FILENAME) . '.pdf';
                $absolutePdfPath = $outputDirectory . DIRECTORY_SEPARATOR . $pdfFileName;

                $isSofficeAvailable = false;
                $sofficePath = 'soffice';
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $checkCmd = 'where soffice 2>nul';
                    $pathCheck = shell_exec($checkCmd);
                    if ($pathCheck) {
                        $isSofficeAvailable = true;
                        $sofficePath = trim($pathCheck);
                    } else {
                        $paths = [
                            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe'
                        ];
                        foreach ($paths as $p) {
                            if (file_exists($p)) {
                                $isSofficeAvailable = true;
                                $sofficePath = '"' . $p . '"';
                                break;
                            }
                        }
                    }
                } else {
                    $checkCmd = 'which soffice 2>/dev/null || which libreoffice 2>/dev/null';
                    $pathCheck = shell_exec($checkCmd);
                    if ($pathCheck) {
                        $isSofficeAvailable = true;
                        $sofficePath = trim($pathCheck);
                    }
                }

                if ($isSofficeAvailable) {
                    $binary = $sofficePath;
                    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                        $binary = trim(shell_exec('which soffice 2>/dev/null') ?: 'libreoffice');
                    }
                    
                    $convertCmd = $binary . ' --headless --convert-to pdf --outdir ' . escapeshellarg($outputDirectory) . ' ' . escapeshellarg($absoluteDocxPath) . ' 2>&1';
                    shell_exec($convertCmd);

                    if (file_exists($absolutePdfPath)) {
                        $response = response()->download($absolutePdfPath, "contract_{$contract->id}.pdf")->deleteFileAfterSend(true);
                        @unlink($absoluteDocxPath);
                        return $response;
                    }
                }

                // 2. Fallback to Mammoth + DomPDF if LibreOffice is not installed
                $previewOutput = \App\Models\ContractTemplate::runPython('preview "' . $absoluteDocxPath . '"');
                $previewData = json_decode($previewOutput, true);
                
                $content = $previewData['html'] ?? '';
                
                $pdf = Pdf::loadView('contracts.compiled', compact('content'));
                @unlink($absoluteDocxPath);
                
                return $pdf->download("contract_{$contract->id}.pdf");
            } catch (\Exception $e) {
                return back()->withErrors(['message' => 'PDF generatsiya qilishda xatolik: ' . $e->getMessage()]);
            }
        } elseif ($template) {
            $content = $template->compile($contract);
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

        $template = $contract->template;
        if (!$template && $contract->contract_template_id) {
            $template = \App\Models\ContractTemplate::find($contract->contract_template_id);
        }
        if (!$template) {
            $type = $contract->contract_type ?? 'residential';
            if (!in_array($type, ['residential', 'commercial'])) {
                $type = 'residential';
            }
            $template = \App\Models\ContractTemplate::where('type', $type)->latest()->first();
        }

        if ($template && $template->docx_path) {
            try {
                $selectedVariants = $contract->selected_variants ?? [];
                $docxPath = $template->compileDocx($contract, $selectedVariants);
                $absoluteDocxPath = storage_path('app/public/' . str_replace('public/', '', $docxPath));

                return response()->download($absoluteDocxPath, "contract_{$contract->id}.docx")->deleteFileAfterSend(true);
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

        // Resolve a template if not selected, so we never fall back to broken/old hardcoded blade views
        $template = $contract->template;
        if (!$template && $contract->contract_template_id) {
            $template = \App\Models\ContractTemplate::find($contract->contract_template_id);
        }
        if (!$template) {
            $type = $contract->contract_type ?? 'residential';
            if (!in_array($type, ['residential', 'commercial'])) {
                $type = 'residential';
            }
            $template = \App\Models\ContractTemplate::where('type', $type)->latest()->first();
        }

        if ($template) {
            if ($template->docx_path) {
                try {
                    $selectedVariants = $contract->selected_variants ?? [];
                    $docxPath = $template->compileDocx($contract, $selectedVariants);
                    $absoluteDocxPath = storage_path('app/public/' . str_replace('public/', '', $docxPath));

                    $previewOutput = \App\Models\ContractTemplate::runPython('preview "' . $absoluteDocxPath . '"');
                    $previewData = json_decode($previewOutput, true);
                    
                    $content = $previewData['html'] ?? '';
                    @unlink($absoluteDocxPath);
                } catch (\Exception $e) {
                    $content = 'Preview generation failed: ' . $e->getMessage();
                }
            } else {
                $content = $template->compile($contract);
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
