<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ContractExportController extends Controller
{
    private function getTemplateName($contract)
    {
        if ($contract->contract_type === 'parking') {
            return 'contracts.parking_template';
        }
        return $contract->unit && $contract->unit->type === 'commercial' ? 'contracts.shop_template' : 'contracts.template';
    }

    public function exportPdf(\App\Models\Contract $contract)
    {
        $contract->load(['client', 'unit', 'unit.floor', 'paymentSchedules']);
        
        $template = $this->getTemplateName($contract);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($template, [
            'contract' => $contract,
            'client' => $contract->client,
            'unit' => $contract->unit
        ]);
        
        return $pdf->download('Contract_' . $contract->id . '.pdf');
    }

    public function exportDocx(\App\Models\Contract $contract)
    {
        $contract->load(['client', 'unit', 'unit.floor', 'paymentSchedules']);
        
        $template = $this->getTemplateName($contract);
        $html = view($template, [
            'contract' => $contract,
            'client' => $contract->client,
            'unit' => $contract->unit
        ])->render();

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        
        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $html, false, false);
        
        $fileName = 'Contract_' . $contract->id . '.docx';
        $tempFile = storage_path('app/public/' . $fileName);
        
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempFile);
        
        return response()->download($tempFile)->deleteFileAfterSend(true);
    }

    public function exportXlsx(\App\Models\Contract $contract)
    {
        $contract->load(['client', 'unit', 'unit.floor', 'paymentSchedules']);
        
        $template = $this->getTemplateName($contract);
        
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ContractExport($contract, $contract->client, $contract->unit, $template), 
            'Contract_' . $contract->id . '.xlsx'
        );
    }
}

