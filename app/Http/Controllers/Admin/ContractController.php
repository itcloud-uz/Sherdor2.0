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
            'term_months' => 'nullable|integer|min:1',
            'created_at' => 'nullable|date',
            'contract_number' => 'nullable|string|max:255|unique:contracts,contract_number',
            'actual_paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|in:bank,main,credit',
            'account_id' => 'nullable|exists:cash_registers,id',
            'floor' => 'nullable|string|max:50',
            'rooms' => 'nullable|integer|min:1'
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
            'term_months' => $validated['term_months'] ?? 1,
            'payment_type' => $paymentType,
            'contract_number' => $validated['contract_number'] ?? null,
            'floor' => $validated['floor'] ?? null,
            'rooms' => $validated['rooms'] ?? null,
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
                             : (!empty($validated['down_payment']) ? $validated['down_payment'] : 0);

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
                         ? $validated['actual_paid_amount'] 
                         : 0;

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

            $incomeCategory = \App\Models\TransactionCategory::firstOrCreate(
                ['name' => 'Mijoz To\'lovi (Shartnoma)'],
                ['type' => 'income', 'description' => 'Shartnoma bo\'yicha mijozdan tushgan to\'lov']
            );

            $transaction = \App\Models\Transaction::create([
                'type' => 'income',
                'amount' => $paymentAmount,
                'account_type' => $paymentMethod,
                'account_id' => $accountId,
                'contract_id' => $contract->id,
                'category_id' => $incomeCategory->id,
                'status' => 'pending',
                'transaction_date' => now()->toDateTimeString(),
                'notes' => 'Shartnomani tahrirlash paytidagi to\'lov (' . $paymentMethod . ')',
                'created_by' => auth()->id() ?? 1,
            ]);

            if ($contract->status === 'active') {
                $financeController = app(\App\Http\Controllers\Admin\FinanceController::class);
                $financeController->approveTransaction(request(), $transaction);
            }
        }

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

    public function cancel(\App\Models\Contract $contract)
    {
        $contract->cancelContract();

        return response()->json([
            'message' => 'Shartnoma bekor qilindi',
            'contract' => $contract
        ]);
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
        $contract->update(['status' => 'active', 'ai_verification_status' => 'passed']);
        if ($contract->unit) {
            $contract->unit->update(['status' => 'sold']);
        }

        // Generate Payment Schedules
        $contract->generatePaymentSchedules();

        // Deduct purchase price from client counterparty balance
        if ($contract->client_id) {
            $counterparty = \App\Models\Counterparty::where([
                'customable_type' => 'App\Models\Client',
                'customable_id' => $contract->client_id,
            ])->first();
            if ($counterparty) {
                $counterparty->decrement('balance', $contract->total_amount);
            }
        }

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
