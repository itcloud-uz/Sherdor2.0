<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function store(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'unit_id' => 'required|exists:units,id',
            'contract_type' => 'required|string|in:residential,commercial',
            'price_per_sqm' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'down_payment' => 'required|numeric|min:0',
            'term_months' => 'required|integer|min:1',
        ]);

        $contract = \App\Models\Contract::create([
            'client_id' => $validated['client_id'],
            'unit_id' => $validated['unit_id'],
            'contract_type' => $validated['contract_type'],
            'price_per_sqm' => $validated['price_per_sqm'],
            'total_amount' => $validated['total_amount'],
            'down_payment' => $validated['down_payment'],
            'term_months' => $validated['term_months'],
            'status' => 'draft'
        ]);

        // Note: The ContractObserver will automatically generate PaymentSchedules.

        // Also mark unit as reserved
        $unit = \App\Models\Unit::find($validated['unit_id']);
        if ($unit) {
            $unit->update(['status' => 'reserved']);
        }

        return redirect()->back()->with('message', 'Contract created successfully.');
    }

    public function generatePdf(\App\Models\Contract $contract)
    {
        // Ensure relations are loaded
        $contract->load(['client', 'unit', 'paymentSchedules']);

        // Generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('contracts.template', compact('contract'));
        
        $fileName = 'contract_' . $contract->id . '_' . time() . '.pdf';
        $path = 'contracts/' . $fileName;
        
        // Save to storage
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, $pdf->output());

        // Update Contract status and path
        $contract->pdf_path = $path;
        $contract->generateDraft(); // State transition: -> draft (Unit -> reserved)
        
        return response()->json([
            'message' => 'PDF generated successfully',
            'pdf_url' => asset('storage/' . $path)
        ]);
    }

    public function uploadAndVerifySignature(\Illuminate\Http\Request $request, \App\Models\Contract $contract, \App\Services\AiVerificationService $aiService)
    {
        $request->validate([
            'signed_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($contract->status !== 'awaiting_signature') {
            return response()->json(['error' => 'Contract is not awaiting signature.'], 400);
        }

        $file = $request->file('signed_document');
        $fileName = 'signed_contract_' . $contract->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('contracts/signed', $fileName, 'public');

        // Update Contract with uploaded file path
        $contract->signed_pdf_path = $path;
        $contract->save();

        // Pass to AI Verification Service
        $verificationResult = $aiService->verifyDocument(storage_path('app/public/' . $path));

        $contract->ai_verification_status = $verificationResult['status'];
        $contract->ai_verification_logs = $verificationResult['logs'];
        
        if ($verificationResult['status'] === 'passed') {
            // Trigger State Machine
            $contract->markAsActiveSold();
        } else {
            // Keep status as awaiting_signature, but record the failure
            $contract->save();
        }

        return response()->json([
            'message' => $verificationResult['status'] === 'passed' ? 'Verification successful. Contract activated.' : 'Verification failed. ' . $verificationResult['logs']['signature_detection']['error'],
            'status' => $verificationResult['status'],
            'logs' => $verificationResult['logs']
        ]);
    }
}
