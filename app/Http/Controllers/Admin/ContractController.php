<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function store(\Illuminate\Http\Request $request)
    {
        if ($request->has('floor') && !is_null($request->floor)) {
            $request->merge(['floor' => (string) $request->floor]);
        }

        $validated = $request->validate([
            'buyer_type' => 'required|in:client,partner',
            'client_id' => 'required_if:buyer_type,client|nullable|exists:clients,id',
            'partner_id' => 'required_if:buyer_type,partner|nullable|exists:partners,id',
            'unit_id' => 'required|exists:units,id',
            'contract_type' => 'required|string|in:residential,commercial',
            'price_per_sqm' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'payment_type' => 'nullable|string|in:full,installment',
            'down_payment' => 'nullable|numeric|min:0',
            'is_down_payment_split' => 'nullable|boolean',
            'down_payment_split_months' => 'nullable|integer|min:1',
            'term_months' => 'nullable|integer|min:1',
            'created_at' => 'nullable|date',
            'contract_number' => 'nullable|string|max:255|unique:contracts,contract_number',
            'actual_paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|in:bank,main,credit',
            'account_id' => 'nullable|exists:cash_registers,id',
            'floor' => 'nullable|string|max:50',
            'rooms' => 'nullable|integer|min:1',
            'user_id' => 'nullable|exists:users,id',
            'contract_template_id' => 'nullable|exists:contract_templates,id'
        ]);

        $paymentType = $validated['payment_type'] ?? 'installment';
        if ($paymentType === 'full') {
            $validated['down_payment'] = $validated['total_amount'];
            $validated['term_months'] = 1;
        }

        $contract = new \App\Models\Contract([
            'client_id' => $validated['buyer_type'] === 'client' ? $validated['client_id'] : null,
            'partner_id' => $validated['buyer_type'] === 'partner' ? $validated['partner_id'] : null,
            'unit_id' => $validated['unit_id'],
            'contract_type' => $validated['contract_type'],
            'price_per_sqm' => $validated['price_per_sqm'],
            'total_amount' => $validated['total_amount'],
            'down_payment' => $validated['down_payment'] ?? 0,
            'is_down_payment_split' => $request->boolean('is_down_payment_split'),
            'down_payment_split_months' => $validated['down_payment_split_months'] ?? 1,
            'term_months' => $validated['term_months'] ?? 1,
            'payment_type' => $paymentType,
            'contract_number' => $validated['contract_number'] ?? null,
            'floor' => $validated['floor'] ?? null,
            'rooms' => $validated['rooms'] ?? null,
            'user_id' => $validated['user_id'] ?? auth()->id() ?? null,
            'contract_template_id' => $validated['contract_template_id'] ?? null,
            'status' => 'draft'
        ]);
        
        if (!empty($validated['created_at'])) {
            $contract->created_at = $validated['created_at'];
            $contract->updated_at = $validated['created_at'];
            $contract->timestamps = false; // Disable timestamps for this specific insert
        }
        $contract->save();
        $contract->timestamps = true;

        $baseDate = $contract->created_at ? \Carbon\Carbon::parse($contract->created_at)->toDateTimeString() : now()->toDateTimeString();

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
                    'transaction_date' => $baseDate,
                    'created_at' => $baseDate,
                    'updated_at' => $baseDate,
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
                    'transaction_date' => $baseDate,
                    'created_at' => $baseDate,
                    'updated_at' => $baseDate,
                    'notes' => 'Ta\'sischi ob\'yekt xaridi (Kompaniya hisobiga): Unit ID ' . $validated['unit_id'],
                    'created_by' => auth()->id() ?? 1,
                ]);

                // Reduce partner balance
                $partner->accountable_balance -= $validated['total_amount'];
                $partner->save();
            }
        } else {
            $paymentAmount = !empty($validated['actual_paid_amount']) && $validated['actual_paid_amount'] > 0 
                             ? $validated['actual_paid_amount'] 
                             : ($request->boolean('is_down_payment_split') ? 0 : (!empty($validated['down_payment']) ? $validated['down_payment'] : 0));

            if ($paymentAmount > 0) {
                $paymentMethod = $validated['payment_method'] ?? 'bank';
                
                // Determine account_id
                $accountId = $validated['account_id'] ?? null;
                if (!$accountId) {
                    $regType = $paymentMethod === 'bank' ? 'bank' : 'cash';
                    $reg = \App\Models\CashRegister::where('type', $regType)->where('is_active', true)->first();
                    if ($reg) {
                        $accountId = $reg->id;
                    }
                }

                // Create a pending transaction for Action Center to approve
                $incomeCategory = \App\Models\TransactionCategory::firstOrCreate(
                    ['name' => 'Mijoz To\'lovi (Shartnoma)'],
                    ['type' => 'income', 'description' => 'Shartnoma bo\'yicha mijozdan tushgan to\'lov']
                );

                $counterpartyId = null;
                if ($contract->client_id) {
                    $counterpartyId = \App\Models\Counterparty::where([
                        'customable_type' => 'App\Models\Client',
                        'customable_id' => $contract->client_id,
                    ])->value('id');
                }
                $buildingId = $contract->unit ? ($contract->unit->floor ? $contract->unit->floor->building_id : null) : null;

                $firstDownPaymentSchedule = \App\Models\PaymentSchedule::where('contract_id', $contract->id)
                    ->where('payment_type', 'down_payment')
                    ->where('status', '!=', 'paid')
                    ->orderBy('due_date', 'asc')
                    ->first();
                $scheduleId = $firstDownPaymentSchedule ? $firstDownPaymentSchedule->id : null;
                if (!$scheduleId) {
                    $firstInstallmentSchedule = \App\Models\PaymentSchedule::where('contract_id', $contract->id)
                        ->where('payment_type', 'installment')
                        ->where('status', '!=', 'paid')
                        ->orderBy('due_date', 'asc')
                        ->first();
                    if ($firstInstallmentSchedule) {
                        $scheduleId = $firstInstallmentSchedule->id;
                    }
                }

                $transaction = \App\Models\Transaction::create([
                    'type' => 'income',
                    'amount' => $paymentAmount,
                    'account_type' => $paymentMethod,
                    'account_id' => $accountId,
                    'contract_id' => $contract->id,
                    'client_id' => $contract->client_id,
                    'counterparty_id' => $counterpartyId,
                    'building_id' => $buildingId,
                    'category_id' => $incomeCategory->id,
                    'payment_schedule_id' => $scheduleId,
                    'status' => 'pending',
                    'transaction_date' => $baseDate,
                    'created_at' => $baseDate,
                    'updated_at' => $baseDate,
                    'notes' => 'Shartnoma yaratish paytidagi to\'lov (' . $paymentMethod . ')',
                    'created_by' => auth()->id() ?? 1,
                ]);
            }
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
        if ($request->has('floor') && !is_null($request->floor)) {
            $request->merge(['floor' => (string) $request->floor]);
        }

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'unit_id' => 'required|exists:units,id',
            'contract_type' => 'required|string|in:residential,commercial',
            'price_per_sqm' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'payment_type' => 'nullable|string|in:full,installment',
            'down_payment' => 'nullable|numeric|min:0',
            'term_months' => 'nullable|integer|min:1',
            'created_at' => 'nullable|date',
            'contract_number' => 'nullable|string|max:255|unique:contracts,contract_number,' . $contract->id,
            'floor' => 'nullable|string|max:50',
            'rooms' => 'nullable|integer|min:1',
            'actual_paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|in:bank,main,credit',
            'account_id' => 'nullable|exists:cash_registers,id',
            'user_id' => 'nullable|exists:users,id',
            'contract_template_id' => 'nullable|exists:contract_templates,id'
        ]);

        if (($validated['payment_type'] ?? 'installment') === 'full') {
            $validated['down_payment'] = $validated['total_amount'];
            $validated['term_months'] = 1;
        }

        $contract->fill($validated);
        if (!empty($validated['created_at'])) {
            $contract->created_at = $validated['created_at'];
        }
        $contract->save();

        // Handle payment capture
        $paymentAmount = !empty($validated['actual_paid_amount']) && $validated['actual_paid_amount'] > 0 
                         ? (float)$validated['actual_paid_amount'] 
                         : 0.0;

        if ($paymentAmount > 0) {
            $paymentMethod = $validated['payment_method'] ?? 'bank';
            
            // Determine account_id
            $accountId = $validated['account_id'] ?? null;
            if (!$accountId) {
                $regType = $paymentMethod === 'bank' ? 'bank' : 'cash';
                $reg = \App\Models\CashRegister::where('type', $regType)->where('is_active', true)->first();
                if ($reg) {
                    $accountId = $reg->id;
                }
            }

            // Get sum of existing income transactions for this contract
            $existingTransactions = \App\Models\Transaction::where('contract_id', $contract->id)
                ->where('type', 'income')
                ->get();
                
            $existingPaidSum = (float)$existingTransactions->sum('amount');
            
            if (abs($paymentAmount - $existingPaidSum) > 0.01) {
                if ($paymentAmount > $existingPaidSum) {
                    $difference = $paymentAmount - $existingPaidSum;
                    
                    $incomeCategory = \App\Models\TransactionCategory::firstOrCreate(
                        ['name' => 'Mijoz To\'lovi (Shartnoma)'],
                        ['type' => 'income', 'description' => 'Shartnoma bo\'yicha mijozdan tushgan to\'lov']
                    );

                    $counterpartyId = null;
                    if ($contract->client_id) {
                        $counterpartyId = \App\Models\Counterparty::where([
                            'customable_type' => 'App\Models\Client',
                            'customable_id' => $contract->client_id,
                        ])->value('id');
                    }
                    $buildingId = $contract->unit ? ($contract->unit->floor ? $contract->unit->floor->building_id : null) : null;

                    $firstDownPaymentSchedule = \App\Models\PaymentSchedule::where('contract_id', $contract->id)
                        ->where('payment_type', 'down_payment')
                        ->where('status', '!=', 'paid')
                        ->orderBy('due_date', 'asc')
                        ->first();
                    $scheduleId = $firstDownPaymentSchedule ? $firstDownPaymentSchedule->id : null;
                    if (!$scheduleId) {
                        $firstInstallmentSchedule = \App\Models\PaymentSchedule::where('contract_id', $contract->id)
                            ->where('payment_type', 'installment')
                            ->where('status', '!=', 'paid')
                            ->orderBy('due_date', 'asc')
                            ->first();
                        if ($firstInstallmentSchedule) {
                            $scheduleId = $firstInstallmentSchedule->id;
                        }
                    }

                    $transaction = \App\Models\Transaction::create([
                        'type' => 'income',
                        'amount' => $difference,
                        'account_type' => $paymentMethod,
                        'account_id' => $accountId,
                        'contract_id' => $contract->id,
                        'client_id' => $contract->client_id,
                        'counterparty_id' => $counterpartyId,
                        'building_id' => $buildingId,
                        'category_id' => $incomeCategory->id,
                        'payment_schedule_id' => $scheduleId,
                        'status' => 'pending',
                        'transaction_date' => now()->toDateTimeString(),
                        'notes' => 'Shartnomani tahrirlash paytidagi qo\'shimcha to\'lov (' . $paymentMethod . ')',
                        'created_by' => auth()->id() ?? 1,
                    ]);

                    if ($contract->status === 'active') {
                        $financeController = app(\App\Http\Controllers\Admin\FinanceController::class);
                        $financeController->approveTransaction(request(), $transaction);
                    }
                } else {
                    // Try to adjust a pending transaction if one exists
                    $pendingTx = $existingTransactions->where('status', 'pending')->first();
                    if ($pendingTx) {
                        $otherApprovedSum = (float)$existingTransactions->where('status', '!=', 'pending')->sum('amount');
                        $newPendingAmount = $paymentAmount - $otherApprovedSum;
                        if ($newPendingAmount > 0.01) {
                            $pendingTx->update(['amount' => $newPendingAmount]);
                        } else {
                            $pendingTx->delete();
                        }
                    }
                }
            }
        }

        return redirect()->back()->with('message', 'Contract updated successfully.');
    }

    public function destroy(\App\Models\Contract $contract)
    {
        // Revert client counterparty balance if contract was active
        if ($contract->status === 'active' && $contract->client_id) {
            $counterparty = \App\Models\Counterparty::where([
                'customable_type' => 'App\Models\Client',
                'customable_id' => $contract->client_id,
            ])->first();
            if ($counterparty) {
                $counterparty->increment('balance', $contract->total_amount);
            }
        }

        // Delete associated transactions via FinanceController to revert kassa & counterparty balances
        $financeController = app(\App\Http\Controllers\Admin\FinanceController::class);
        $transactions = $contract->transactions()->get();
        foreach ($transactions as $transaction) {
            $financeController->destroy($transaction);
        }

        // Free up the unit if contract is destroyed
        if ($contract->unit) {
            $contract->unit->update(['status' => 'available']);
        }

        // Delete payment schedules and refund schedules
        $contract->paymentSchedules()->delete();
        $contract->refundSchedules()->delete();

        $contract->delete();
        return redirect()->back()->with('message', 'Contract deleted successfully.');
    }

    public function generatePdf(\App\Models\Contract $contract)
    {
        // Ensure relations are loaded
        $contract->load(['client', 'unit', 'paymentSchedules', 'template']);

        $locale = app()->getLocale();
        if (!in_array($locale, ['uz', 'ru', 'en'])) {
            $locale = 'uz'; // fallback
        }

        // Generate PDF
        if ($contract->contract_template_id && $contract->template) {
            $content = $contract->template->compile($contract);
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('contracts.compiled', compact('content'));
        } else {
            $type = $contract->contract_type ?? 'residential';
            if (!in_array($type, ['residential', 'commercial'])) {
                $type = 'residential';
            }
            $viewPath = "contracts.{$locale}.{$type}";
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($viewPath, compact('contract'));
        }
        
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

    public function cancel(\App\Models\Contract $contract)
    {
        $contract->cancelContract();

        return response()->json([
            'message' => 'Shartnoma bekor qilindi',
            'contract' => $contract
        ]);
    }

    public function generateRefundSchedules(\Illuminate\Http\Request $request, \App\Models\Contract $contract)
    {
        $request->validate([
            'installments' => 'required|array|min:1',
            'installments.*.amount' => 'required|numeric|min:0.01',
            'installments.*.due_date' => 'required|date',
            'installments.*.notes' => 'nullable|string',
        ]);

        // Delete any existing refund schedules for this contract that have NOT been paid yet
        $contract->refundSchedules()->where('paid_amount', '<=', 0)->delete();

        foreach ($request->installments as $inst) {
            \App\Models\RefundSchedule::create([
                'contract_id' => $contract->id,
                'client_id' => $contract->client_id,
                'amount' => $inst['amount'],
                'paid_amount' => 0,
                'due_date' => $inst['due_date'],
                'notes' => $inst['notes'] ?? null,
                'status' => 'pending'
            ]);
        }

        return redirect()->back()->with('message', 'Qaytarish grafigi muvaffaqiyatli shakllantirildi.');
    }

    public function getCancelledContracts()
    {
        $contracts = Contract::with(['client', 'unit.floor.building'])
            ->whereIn('status', ['cancelled', 'canceled'])
            ->get();

        return response()->json($contracts);
    }

    public function forceActivate(\App\Models\Contract $contract)
    {
        $contract->activateContract();

        // Retrieve counterparty and building info
        $counterpartyId = null;
        if ($contract->client_id) {
            $counterpartyId = \App\Models\Counterparty::where([
                'customable_type' => 'App\Models\Client',
                'customable_id' => $contract->client_id,
            ])->value('id');
        }
        $buildingId = $contract->unit ? ($contract->unit->floor ? $contract->unit->floor->building_id : null) : null;

        // Auto-approve any pending transactions associated with this contract
        $pendingTransactions = \App\Models\Transaction::where('contract_id', $contract->id)->where('status', 'pending')->get();
        $financeController = app(\App\Http\Controllers\Admin\FinanceController::class);

        if ($pendingTransactions->count() > 0) {
            foreach ($pendingTransactions as $tx) {
                $updateData = [
                    'client_id' => $contract->client_id,
                    'counterparty_id' => $counterpartyId,
                    'building_id' => $buildingId,
                ];

                if (request()->has('account_id')) {
                    $updateData['account_id'] = request()->input('account_id');
                }
                if (request()->has('payment_method')) {
                    $updateData['account_type'] = request()->input('payment_method');
                }

                $tx->update($updateData);
                $financeController->approveTransaction(request(), $tx);
            }
        } elseif ($contract->down_payment > 0 && request()->has('account_id')) {
            $incomeCategory = \App\Models\TransactionCategory::firstOrCreate(
                ['name' => 'Mijoz To\'lovi (Shartnoma)'],
                ['type' => 'income', 'description' => 'Shartnoma bo\'yicha mijozdan tushgan to\'lov']
            );

            $paymentMethod = request()->input('payment_method', 'bank');

            $tx = \App\Models\Transaction::create([
                'type' => 'income',
                'amount' => $contract->down_payment,
                'account_type' => $paymentMethod,
                'account_id' => request()->input('account_id'),
                'contract_id' => $contract->id,
                'client_id' => $contract->client_id,
                'counterparty_id' => $counterpartyId,
                'building_id' => $buildingId,
                'category_id' => $incomeCategory->id,
                'status' => 'pending',
                'transaction_date' => now(),
                'notes' => 'Shartnoma aktivlashtirish paytidagi to\'lov (' . $paymentMethod . ')',
                'created_by' => auth()->id() ?? 1,
            ]);

            $financeController->approveTransaction(request(), $tx);
        }

        return response()->json([
            'message' => 'Shartnoma qo\'lda aktivlashtirildi va sotildi deb belgilandi.',
            'contract' => $contract
        ]);
    }
}
