<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function store(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'buyer_type' => 'required|in:client,partner',
            'client_id' => 'required_if:buyer_type,client|nullable|exists:clients,id',
            'partner_id' => 'required_if:buyer_type,partner|nullable|exists:partners,id',
            'unit_id' => 'required|exists:units,id',
            'contract_type' => 'required|string|in:residential,commercial',
            'price_per_sqm' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'down_payment' => 'required|numeric|min:0',
            'term_months' => 'required|integer|min:1',
            'created_at' => 'nullable|date',
            'contract_number' => 'nullable|string|max:255|unique:contracts,contract_number',
            'actual_paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|in:bank,main,credit',
            'floor' => 'nullable|string|max:50',
            'rooms' => 'nullable|integer|min:1'
        ]);

        $contract = new \App\Models\Contract([
            'client_id' => $validated['buyer_type'] === 'client' ? $validated['client_id'] : null,
            'partner_id' => $validated['buyer_type'] === 'partner' ? $validated['partner_id'] : null,
            'unit_id' => $validated['unit_id'],
            'contract_type' => $validated['contract_type'],
            'price_per_sqm' => $validated['price_per_sqm'],
            'total_amount' => $validated['total_amount'],
            'down_payment' => $validated['down_payment'],
            'term_months' => $validated['term_months'],
            'contract_number' => $validated['contract_number'] ?? null,
            'floor' => $validated['floor'] ?? null,
            'rooms' => $validated['rooms'] ?? null,
            'status' => 'draft'
        ]);
        
        if (!empty($validated['created_at'])) {
            $contract->created_at = $validated['created_at'];
        }
        $contract->save();

        // If it's a partner, create automatic transaction reducing their accountable balance
        if ($validated['buyer_type'] === 'partner') {
            $partner = \App\Models\Partner::find($validated['partner_id']);
            if ($partner) {
                // Ta'sischi ulushidan chiqim
                $partnerCategory = \App\Models\TransactionCategory::firstOrCreate(
                    ['name' => 'Ta\'sischi Xaridi (Chiqim)'],
                    ['type' => 'expense', 'description' => 'Ta\'sischi o\'z ulushidan ob\'yekt xarid qildi']
                );
                
                \App\Models\Transaction::create([
                    'type' => 'expense',
                    'amount' => $validated['total_amount'],
                    'account_type' => 'main',
                    'contract_id' => $contract->id,
                    'category_id' => $partnerCategory->id,
                    'status' => 'approved', // automatic approval for partner buy? Let's make it approved
                    'transaction_date' => now(),
                    'notes' => 'Ta\'sischi ob\'yekt xaridi: Unit ID ' . $validated['unit_id'],
                    'created_by' => auth()->id() ?? 1,
                    'target_type' => 'partner',
                    'target_id' => $partner->id
                ]);

                // Also record Income to the company
                $incomeCategory = \App\Models\TransactionCategory::firstOrCreate(
                    ['name' => 'Ta\'sischi Xaridi (Kirim)'],
                    ['type' => 'income', 'description' => 'Ta\'sischi ob\'yekt xarididan tushum']
                );
                
                \App\Models\Transaction::create([
                    'type' => 'income',
                    'amount' => $validated['total_amount'],
                    'account_type' => 'main',
                    'contract_id' => $contract->id,
                    'category_id' => $incomeCategory->id,
                    'status' => 'approved',
                    'transaction_date' => now(),
                    'notes' => 'Ta\'sischi ob\'yekt xaridi (Kompaniya hisobiga): Unit ID ' . $validated['unit_id'],
                    'created_by' => auth()->id() ?? 1,
                ]);

                // Reduce partner balance
                $partner->accountable_balance -= $validated['total_amount'];
                $partner->save();
            }
        } else if (!empty($validated['actual_paid_amount']) && $validated['actual_paid_amount'] > 0) {
            $paymentMethod = $validated['payment_method'] ?? 'bank';
            
            // Create a pending transaction for Action Center to approve
            $incomeCategory = \App\Models\TransactionCategory::firstOrCreate(
                ['name' => 'Mijoz To\'lovi (Shartnoma)'],
                ['type' => 'income', 'description' => 'Shartnoma bo\'yicha mijozdan tushgan to\'lov']
            );

            \App\Models\Transaction::create([
                'type' => 'income',
                'amount' => $validated['actual_paid_amount'],
                'account_type' => $paymentMethod,
                'contract_id' => $contract->id,
                'category_id' => $incomeCategory->id,
                'status' => 'pending',
                'transaction_date' => now(),
                'notes' => 'Shartnoma yaratish paytidagi to\'lov (' . $paymentMethod . ')',
                'created_by' => auth()->id() ?? 1,
            ]);
        }

        // Also mark unit as reserved
        $unit = \App\Models\Unit::find($validated['unit_id']);
        if ($unit) {
            $unit->update(['status' => 'reserved']);
        }

        return redirect()->back()->with('message', 'Contract created successfully. Agar to\'lov kiritilgan bo\'lsa, Tasdiqlar bo\'limiga tushdi.');
    }

    public function update(\Illuminate\Http\Request $request, \App\Models\Contract $contract)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'unit_id' => 'required|exists:units,id',
            'contract_type' => 'required|string|in:residential,commercial',
            'price_per_sqm' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'down_payment' => 'required|numeric|min:0',
            'term_months' => 'required|integer|min:1',
            'created_at' => 'nullable|date',
            'contract_number' => 'nullable|string|max:255|unique:contracts,contract_number,' . $contract->id,
            'floor' => 'nullable|string|max:50',
            'rooms' => 'nullable|integer|min:1'
        ]);

        $contract->fill($validated);
        if (!empty($validated['created_at'])) {
            $contract->created_at = $validated['created_at'];
        }
        $contract->save();

        return redirect()->back()->with('message', 'Contract updated successfully.');
    }

    public function destroy(\App\Models\Contract $contract)
    {
        // Free up the unit if contract is destroyed
        if ($contract->unit) {
            $contract->unit->update(['status' => 'available']);
        }
        $contract->delete();
        return redirect()->back()->with('message', 'Contract deleted successfully.');
    }

    public function generatePdf(\App\Models\Contract $contract)
    {
        // Ensure relations are loaded
        $contract->load(['client', 'unit', 'paymentSchedules']);

        $locale = app()->getLocale();
        if (!in_array($locale, ['uz', 'ru', 'en'])) {
            $locale = 'uz'; // fallback
        }

        $type = $contract->contract_type ?? 'residential';
        if (!in_array($type, ['residential', 'commercial'])) {
            $type = 'residential';
        }
        
        $viewPath = "contracts.{$locale}.{$type}";

        // Generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($viewPath, compact('contract'));
        
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
