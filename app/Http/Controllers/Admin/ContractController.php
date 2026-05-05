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

        // Allow uploading signature for any active or draft contract, to be flexible
        if (in_array($contract->status, ['cancelled', 'completed'])) {
            return response()->json(['error' => 'Contract is closed or cancelled.'], 400);
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

    public function updatePenaltyRate(\Illuminate\Http\Request $request, \App\Models\Contract $contract)
    {
        $request->validate([
            'daily_penalty_rate' => 'required|numeric|min:0'
        ]);

        $contract->daily_penalty_rate = $request->daily_penalty_rate;
        $contract->save();

        // Recalculate penalties
        $today = \Carbon\Carbon::today();
        
        $overdueSchedules = $contract->paymentSchedules()
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', '<', $today)
            ->get();

        $totalPenalty = 0;
        
        foreach ($overdueSchedules as $schedule) {
            $remainingDebt = max(0, $schedule->expected_amount - $schedule->paid_amount);
            $schedule->remaining_debt = $remainingDebt;

            if ($remainingDebt > 0) {
                // Number of overdue days
                $dueDate = \Carbon\Carbon::parse($schedule->due_date)->startOfDay();
                $daysOverdue = max(0, $dueDate->diffInDays($today));
                
                // Calculate total penalty for this schedule
                $dailyRate = $contract->daily_penalty_rate / 100;
                $schedulePenalty = $remainingDebt * $dailyRate * $daysOverdue;
                
                $schedule->penalty_for_this_month = $schedulePenalty;
                $totalPenalty += $schedulePenalty;
            } else {
                $schedule->penalty_for_this_month = 0;
            }
            $schedule->save();
        }

        $contract->total_penalty_accumulated = $totalPenalty;
        $contract->save();

        return response()->json([
            'message' => 'Hisoblandi: Yangi penya stavkasi qo\'llanildi.',
            'contract' => $contract->load(['client', 'unit', 'paymentSchedules'])
        ]);
    }
}
