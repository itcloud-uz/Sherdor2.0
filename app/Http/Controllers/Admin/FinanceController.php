<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Inertia\Inertia;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with(['creator', 'approver', 'contract.client', 'contract.unit'])->latest()->get();
        $balance = \App\Models\CompanyBalance::firstOrCreate([]);
        
        $pendingIncomes = Transaction::where('status', 'pending')->where('type', 'income')->sum('amount');
        $pendingExpenses = Transaction::where('status', 'pending')->where('type', 'expense')->sum('amount');

        return Inertia::render('Admin/Finance/Index', [
            'transactions' => $transactions,
            'balance' => $balance,
            'pendingIncomes' => $pendingIncomes,
            'pendingExpenses' => $pendingExpenses,
            'categories' => \App\Models\TransactionCategory::all(),
            'contracts' => \App\Models\Contract::with(['client', 'unit'])->where('status', '!=', 'completed')->get(),
            'financialLiabilities' => \App\Models\FinancialLiability::with('payments')->latest()->get(),
            'suppliers' => \App\Models\Supplier::latest()->get(),
            'workers' => \App\Models\Worker::latest()->get(),
            'machineries' => \App\Models\Machinery::latest()->get(),
            'buildings' => \App\Models\Building::latest()->get(),
            'clients' => \App\Models\Client::latest()->get(),
            'partners' => \App\Models\Partner::with('user')->latest()->get()
        ]);
    }

    public function expensesBreakdown()
    {
        $expenses = Transaction::with('category')
            ->where('type', 'expense')
            ->where('status', 'approved')
            ->get();

        $grouped = $expenses->groupBy('category_id')->map(function ($txns) {
            $cat = $txns->first()->category;
            return [
                'category_name' => $cat ? $cat->name : 'Noma\'lum',
                'total' => $txns->sum('amount'),
                'transactions' => $txns
            ];
        })->values();

        return response()->json([
            'total_expenses' => $expenses->sum('amount'),
            'categories' => $grouped
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string',
            'payment_method' => 'required|in:cash,card,bank_transfer',
            'account_type' => 'required|in:main,credit',
            'contract_id' => 'nullable|exists:contracts,id',
            'client_id' => 'nullable|exists:clients,id',
            'notes' => 'nullable|string',
            'category_id' => 'nullable|exists:transaction_categories,id',
            'category_name' => 'nullable|string',
            'transaction_date' => 'nullable|date',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'auto_approve' => 'nullable|boolean',
            'worker_id' => 'nullable|exists:workers,id',
            'machinery_id' => 'nullable|exists:machineries,id',
            'building_id' => 'nullable|exists:buildings,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'source' => 'nullable|string|max:50',
            'work_category' => 'nullable|string|max:255',
            'target_type' => 'nullable|string|in:partner,supplier,client,worker',
            'target_id' => 'nullable|integer'
        ]);

        $validated['created_by'] = auth()->id();
        $validated['status'] = 'pending';
        
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('transactions/attachments', 'public');
            $validated['attachment_path'] = $path;
        }

        if (empty($validated['transaction_date'])) {
            $validated['transaction_date'] = now()->toDateString();
        }

        // Handle category creation on the fly if needed
        if (empty($validated['category_id']) && !empty($validated['category_name'])) {
            $cat = \App\Models\TransactionCategory::firstOrCreate(
                ['name' => $validated['category_name']],
                ['type' => $validated['type']]
            );
            $validated['category_id'] = $cat->id;
        }

        $requiresConsensus = false;
        if ($validated['type'] === 'expense' && $validated['amount'] >= 100000000 && $validated['account_type'] !== 'credit') {
            $requiresConsensus = true;
            $validated['requires_consensus'] = true;
        }
        
        $transaction = Transaction::create($validated);

        if ($requiresConsensus) {
            $consensusRequest = \App\Models\ConsensusRequest::create([
                'transaction_id' => $transaction->id,
                'status' => 'pending'
            ]);

            $partners = \App\Models\Partner::all();
            foreach ($partners as $partner) {
                \App\Models\ConsensusVote::create([
                    'consensus_request_id' => $consensusRequest->id,
                    'partner_id' => $partner->id,
                    'vote' => 'pending'
                ]);
            }
        }

        if (!empty($validated['auto_approve']) && !$requiresConsensus) {
            $this->approveTransaction($request, $transaction);
            return redirect()->back()->with('message', 'Tranzaksiya muvaffaqiyatli saqlandi va tasdiqlandi.');
        }

        return redirect()->back()->with('message', 'To\'lov muvaffaqiyatli qo\'shildi va tasdiqlash kutilmoqda.');
    }

    public function internalTransfer(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'from_account' => 'required|in:main,bank,credit',
            'to_account' => 'required|in:main,bank,credit',
            'fee_amount' => 'nullable|numeric|min:0',
            'contract_id' => 'nullable|exists:contracts,id',
            'refund_request_id' => 'nullable|exists:transactions,id',
            'notes' => 'nullable|string',
        ]);

        if ($validated['from_account'] === $validated['to_account']) {
            return redirect()->back()->withErrors(['error' => 'Hisoblar bir xil bo\'lishi mumkin emas']);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated) {
            $amount = $validated['amount'];
            $fee = $validated['fee_amount'] ?? 0;
            $netAmount = $amount - $fee;
            $contractId = $validated['contract_id'] ?? null;

            $transferCategory = \App\Models\TransactionCategory::firstOrCreate(
                ['name' => 'Ichki O\'tkazma (Naqdlashtirish)'],
                ['type' => 'transfer', 'description' => 'Hisoblar aro ichki o\'tkazmalar']
            );

            if (!empty($validated['refund_request_id'])) {
                $refundTxn = \App\Models\Transaction::find($validated['refund_request_id']);
                if ($refundTxn && $refundTxn->status === 'pending') {
                    $refundTxn->status = 'approved';
                    $refundTxn->approved_by = auth()->id();
                    $refundTxn->save();
                }
            }

            if ($contractId) {
                // MIJOZ BILAN BOG'LANGAN O'TKAZMA (QAYTIM)

                // 1. Asosiy hisobdan to'liq summa yechiladi (Bank)
                \App\Models\CompanyBalance::addExpense($amount, $validated['from_account']);
                // 2. Qabul qiluvchi hisobga to'liq summa tranzit bo'lib tushadi (Kassa)
                \App\Models\CompanyBalance::addIncome($amount, $validated['to_account']);
                // 3. Qabul qiluvchi hisobdan to'liq summa mijozga qaytim qilinadi (Kassadan)
                \App\Models\CompanyBalance::addExpense($amount, $validated['to_account']);
                // 4. Komissiya ushlab qolingan bo'lsa, qabul qiluvchi hisobga foyda bo'lib qaytadi
                if ($fee > 0) {
                    \App\Models\CompanyBalance::addIncome($fee, $validated['to_account']);
                }

                $notes = "Shartnoma #{$contractId} qaytim (Naqdlashtirish): {$validated['from_account']} -> {$validated['to_account']}. " . ($validated['notes'] ?? '');

                // Tranzit loglari (faqat informatsion)
                \App\Models\Transaction::create([
                    'type' => 'transfer',
                    'amount' => $amount,
                    'payment_method' => $validated['from_account'] === 'bank' ? 'bank_transfer' : 'cash',
                    'account_type' => $validated['from_account'],
                    'category_id' => $transferCategory->id,
                    'status' => 'approved',
                    'transaction_date' => now(),
                    'notes' => "[Chiqim Tranzit] " . $notes,
                    'created_by' => auth()->id(),
                ]);

                \App\Models\Transaction::create([
                    'type' => 'transfer',
                    'amount' => $amount,
                    'payment_method' => $validated['to_account'] === 'bank' ? 'bank_transfer' : 'cash',
                    'account_type' => $validated['to_account'],
                    'category_id' => $transferCategory->id,
                    'status' => 'approved',
                    'transaction_date' => now(),
                    'notes' => "[Kirim Tranzit] " . $notes,
                    'created_by' => auth()->id(),
                ]);

                // Shartnomaga Qaytim (Expense)
                $refundCategory = \App\Models\TransactionCategory::firstOrCreate(
                    ['name' => 'Mijozga Qaytim (Vozvrat)'],
                    ['type' => 'expense', 'description' => 'Mijoz shartnomasidagi ortiqcha to\'lovni qaytarish']
                );

                \App\Models\Transaction::create([
                    'type' => 'expense',
                    'amount' => $amount,
                    'payment_method' => $validated['to_account'] === 'bank' ? 'bank_transfer' : 'cash',
                    'account_type' => $validated['to_account'],
                    'category_id' => $refundCategory->id,
                    'contract_id' => $contractId,
                    'status' => 'approved',
                    'transaction_date' => now(),
                    'notes' => "Mijozga Qaytim (Komissiya ichida): " . ($validated['notes'] ?? ''),
                    'created_by' => auth()->id(),
                ]);

                // Komissiya Foydasi
                if ($fee > 0) {
                    $feeCategory = \App\Models\TransactionCategory::firstOrCreate(
                        ['name' => 'Moliyaviy xizmat / Konvertatsiya daromadi'],
                        ['type' => 'income', 'description' => 'Konvertatsiya va naqdlashtirish xizmati foydasi']
                    );
                    
                    \App\Models\Transaction::create([
                        'type' => 'income',
                        'amount' => $fee,
                        'payment_method' => $validated['to_account'] === 'bank' ? 'bank_transfer' : 'cash',
                        'account_type' => $validated['to_account'],
                        'category_id' => $feeCategory->id,
                        'status' => 'approved',
                        'transaction_date' => now(),
                        'notes' => "Shartnoma #{$contractId} uchun komissiya ushlanmasi",
                        'created_by' => auth()->id(),
                    ]);
                }

            } else {
                // ODDIY ICHKI O'TKAZMA (Shartnomasiz)
                
                // 1. Asosiy hisobdan to'liq summa yechiladi
                \App\Models\CompanyBalance::addExpense($amount, $validated['from_account']);
                // 2. Qabul qiluvchi hisobga sof summa tushadi
                \App\Models\CompanyBalance::addIncome($netAmount, $validated['to_account']);

                $notes = "O'tkazma: {$validated['from_account']} -> {$validated['to_account']}. " . ($validated['notes'] ?? '');

                // Tranzit - Chiqim log (Transfer type)
                \App\Models\Transaction::create([
                    'type' => 'transfer',
                    'amount' => $amount,
                    'payment_method' => $validated['from_account'] === 'bank' ? 'bank_transfer' : 'cash',
                    'account_type' => $validated['from_account'],
                    'category_id' => $transferCategory->id,
                    'status' => 'approved',
                    'transaction_date' => now(),
                    'notes' => "[Chiqim] " . $notes,
                    'created_by' => auth()->id(),
                ]);

                // Tranzit - Kirim log (Transfer type)
                \App\Models\Transaction::create([
                    'type' => 'transfer',
                    'amount' => $netAmount,
                    'payment_method' => $validated['to_account'] === 'bank' ? 'bank_transfer' : 'cash',
                    'account_type' => $validated['to_account'],
                    'category_id' => $transferCategory->id,
                    'status' => 'approved',
                    'transaction_date' => now(),
                    'notes' => "[Kirim] " . $notes,
                    'created_by' => auth()->id(),
                ]);

                // Foyda (Komissiya) log - Income type
                if ($fee > 0) {
                    // Foydani yechilgan hisobga (qayerdan olingan bo'lsa) qaytarib kirim qilamiz
                    \App\Models\CompanyBalance::addIncome($fee, $validated['from_account']);

                    $feeCategory = \App\Models\TransactionCategory::firstOrCreate(
                        ['name' => 'Moliyaviy xizmat / Konvertatsiya daromadi'],
                        ['type' => 'income', 'description' => 'Konvertatsiya va naqdlashtirish xizmati foydasi']
                    );
                    
                    \App\Models\Transaction::create([
                        'type' => 'income',
                        'amount' => $fee,
                        'payment_method' => $validated['from_account'] === 'bank' ? 'bank_transfer' : 'cash',
                        'account_type' => $validated['from_account'],
                        'category_id' => $feeCategory->id,
                        'status' => 'approved',
                        'transaction_date' => now(),
                        'notes' => "Komissiya foydasi: {$validated['from_account']} -> {$validated['to_account']}",
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        });

        return redirect()->back()->with('message', 'Ichki o\'tkazma muvaffaqiyatli bajarildi');
    }

    public function approveTransaction(Request $request, Transaction $transaction)
    {
        if ($transaction->status !== 'pending') {
            return response()->json(['error' => 'Transaction is not pending.'], 400);
        }

        if ($transaction->requires_consensus) {
            return response()->json(['error' => 'Transaction requires partner consensus and cannot be approved manually.'], 403);
        }

        $transaction->status = 'approved';
        $transaction->approved_by = auth()->id();
        $transaction->receipt_hash = 'TXN-' . strtoupper(uniqid()) . '-' . $transaction->id;
        $transaction->save();

        // Partner Transaction Logic
        if ($transaction->partner_id) {
            $partner = \App\Models\Partner::find($transaction->partner_id);
            if ($partner) {
                if ($transaction->category === 'Partner Advance') {
                    // Pul kassadan chiqadi va hamkor bo'yniga tushadi
                    \App\Models\CompanyBalance::addExpense($transaction->amount, $transaction->account_type);
                    $partner->accountable_balance += $transaction->amount;
                    $partner->save();
                } else {
                    // Hamkor chek topshirdi, kassaga ta'sir qilmaydi, faqat qarzi kamayadi
                    $partner->accountable_balance -= $transaction->amount;
                    $partner->save();
                }
            }
        } else {
            // Normal Company Transaction
            if ($transaction->type === 'income') {
                \App\Models\CompanyBalance::addIncome($transaction->amount, $transaction->account_type);
            } else {
                \App\Models\CompanyBalance::addExpense($transaction->amount, $transaction->account_type);
            }

            // Specific target updates
            if ($transaction->worker_id) {
                $worker = \App\Models\Worker::find($transaction->worker_id);
                if ($worker) {
                    $workerAmount = $transaction->type === 'income' ? $transaction->amount : -$transaction->amount;
                    $worker->increment('balance', $workerAmount);
                    \Illuminate\Support\Facades\DB::table('worker_transactions')->insert([
                        'worker_id' => $worker->id,
                        'type' => $transaction->type === 'income' ? 'payment_received' : 'advance_payment',
                        'amount' => $transaction->amount,
                        'work_category' => $transaction->work_category,
                        'description' => "Moliya: " . ($transaction->notes ?? $transaction->category),
                        'date' => $transaction->transaction_date ?? now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            if ($transaction->machinery_id) {
                $machinery = \App\Models\Machinery::find($transaction->machinery_id);
                if ($machinery) {
                    $machineryAmount = $transaction->type === 'income' ? $transaction->amount : -$transaction->amount;
                    $machinery->increment('balance', $machineryAmount);
                    \Illuminate\Support\Facades\DB::table('machinery_transactions')->insert([
                        'machinery_id' => $machinery->id,
                        'type' => $transaction->type === 'income' ? 'payment_received' : 'advance_payment',
                        'amount' => $transaction->amount,
                        'description' => "Moliya: " . ($transaction->notes ?? $transaction->category),
                        'date' => $transaction->transaction_date ?? now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            if ($transaction->supplier_id) {
                $supplier = \App\Models\Supplier::find($transaction->supplier_id);
                if ($supplier) {
                    $supplierAmount = $transaction->type === 'income' ? $transaction->amount : -$transaction->amount;
                    $supplier->increment('balance', $supplierAmount);
                }
            }
        }

        if ($transaction->contract_id && $transaction->type === 'income') {
            $appliedAmount = $request->input('applied_amount', $transaction->amount);
            $refundAmount = $transaction->amount - $appliedAmount;

            if ($appliedAmount > 0) {
                $remainingAmount = $appliedAmount;
                
                $schedules = \App\Models\PaymentSchedule::where('contract_id', $transaction->contract_id)
                    ->where('status', '!=', 'paid')
                    ->orderBy('due_date', 'asc')
                    ->get();
                    
                $isFirst = true;
                foreach ($schedules as $schedule) {
                    if ($remainingAmount <= 0) break;
                    
                    $needed = $schedule->expected_amount - $schedule->paid_amount;
                    if ($needed <= 0) continue;
                    
                    if ($remainingAmount >= $needed) {
                        $schedule->paid_amount += $needed;
                        $schedule->status = 'paid';
                        $schedule->actual_payment_date = $transaction->transaction_date ?? now();
                        $schedule->payment_method = $transaction->payment_method;
                        if ($isFirst) {
                            $schedule->original_payment_amount = $transaction->amount;
                            $isFirst = false;
                        }
                        $remainingAmount -= $needed;
                    } else {
                        $schedule->paid_amount += $remainingAmount;
                        $schedule->status = 'partial'; // Ensure partial status is set if not paid
                        $schedule->actual_payment_date = $transaction->transaction_date ?? now();
                        $schedule->payment_method = $transaction->payment_method;
                        if ($isFirst) {
                            $schedule->original_payment_amount = $transaction->amount;
                            $isFirst = false;
                        }
                        $remainingAmount = 0;
                    }
                    
                    $schedule->save();
                }
                
                if ($remainingAmount > 0) {
                    $refundAmount += $remainingAmount;
                }
            }

            if ($refundAmount > 0) {
                $refundCategory = \App\Models\TransactionCategory::firstOrCreate(
                    ['name' => 'Kutayotgan Naqdlashtirish'],
                    ['type' => 'expense', 'description' => 'Mijoz ortiqcha to\'lovni naqd qilib olishi uchun navbat']
                );

                \App\Models\Transaction::create([
                    'type' => 'expense',
                    'amount' => $refundAmount,
                    'account_type' => $transaction->account_type,
                    'contract_id' => $transaction->contract_id,
                    'category_id' => $refundCategory->id,
                    'status' => 'pending',
                    'transaction_date' => now(),
                    'notes' => 'Avtomatik naqdlashtirish navbati (Asosiy TXN: #' . $transaction->id . ')',
                    'created_by' => auth()->id(),
                ]);
            }
        } elseif ($transaction->contract_id && $transaction->type === 'expense' && $transaction->category && in_array($transaction->category->name, ['Mijozga pulni qaytarish', 'Mijozga to\'lov qilish'])) {
            $contract = \App\Models\Contract::find($transaction->contract_id);
            if ($contract) {
                $contract->increment('refunded_amount', $transaction->amount);
            }
        }

        return redirect()->back()->with('message', 'Transaction approved.');
    }

    public function rejectTransaction(Transaction $transaction)
    {
        if ($transaction->status !== 'pending') {
            return response()->json(['error' => 'Transaction is not pending.'], 400);
        }

        $transaction->status = 'rejected';
        $transaction->approved_by = auth()->id();
        $transaction->save();

        return redirect()->back()->with('message', 'Transaction rejected.');
    }

    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'account_type' => 'required|in:main,credit',
            'notes' => 'nullable|string',
        ]);

        // If it was already approved, we need to revert the old amount and apply the new one
        if ($transaction->status === 'approved') {
            // Revert old
            if ($transaction->type === 'income') {
                \App\Models\CompanyBalance::addExpense($transaction->amount, $transaction->account_type);
            } else {
                \App\Models\CompanyBalance::addIncome($transaction->amount, $transaction->account_type);
            }

            // Apply new
            if ($transaction->type === 'income') {
                \App\Models\CompanyBalance::addIncome($validated['amount'], $validated['account_type']);
            } else {
                \App\Models\CompanyBalance::addExpense($validated['amount'], $validated['account_type']);
            }
        }

        $transaction->update($validated);

        return redirect()->back()->with('message', 'Tranzaksiya muvaffaqiyatli tahrirlandi.');
    }

    public function destroy(Transaction $transaction)
    {
        // Prevent deleting pending transactions from partners
        if ($transaction->status === 'pending' && $transaction->partner_id) {
            return redirect()->back()->withErrors(['error' => 'Hamkor kiritgan so\'rovni o\'chirib bo\'lmaydi! Iltimos, uni Tasdiqlar panelidan "Rad etish" (Reject) qiling.']);
        }

        // Revert balance if approved
        if ($transaction->status === 'approved') {
            if ($transaction->type === 'income') {
                \App\Models\CompanyBalance::addExpense($transaction->amount, $transaction->account_type);
            } else {
                \App\Models\CompanyBalance::addIncome($transaction->amount, $transaction->account_type);
            }

            // Revert Partner Balance
            if ($transaction->partner_id) {
                $partner = \App\Models\Partner::find($transaction->partner_id);
                if ($partner) {
                    if ($transaction->category === 'Partner Advance') {
                        // Originally added to accountable balance, so deduct
                        $partner->accountable_balance -= $transaction->amount;
                    } else {
                        // Originally deducted from accountable balance, so add back
                        $partner->accountable_balance += $transaction->amount;
                    }
                    $partner->save();
                }
            }
        }

        $transaction->delete();

        return redirect()->back()->with('message', 'Tranzaksiya muvaffaqiyatli o\'chirildi.');
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:transaction_categories,name',
            'type' => 'required|in:income,expense,both'
        ]);

        \App\Models\TransactionCategory::create($validated);

        return redirect()->back()->with('message', 'Kategoriya muvaffaqiyatli qo\'shildi.');
    }

    // --- FINANCIAL LIABILITIES ---

    public function storeLiability(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:bank_loan,supplier_debt,partner_debt',
            'creditor_name' => 'required|string|max:255',
            'total_amount' => 'required|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'term_months' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'monthly_payment' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'fund_account' => 'nullable|in:main,credit'
        ]);

        if (empty($validated['monthly_payment']) && !empty($validated['term_months'])) {
            $p = $validated['total_amount'];
            $n = $validated['term_months'];
            $r_annual = $validated['interest_rate'] ?? 0;

            if ($r_annual > 0) {
                $r = ($r_annual / 100) / 12;
                $validated['monthly_payment'] = $p * ($r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
            } else {
                $validated['monthly_payment'] = $p / $n;
            }
        }

        $validated['status'] = 'active';
        
        $liability = \App\Models\FinancialLiability::create($validated);

        if (!empty($validated['fund_account'])) {
            \App\Models\CompanyBalance::addIncome($validated['total_amount'], $validated['fund_account']);
            
            Transaction::create([
                'type' => 'income',
                'amount' => $validated['total_amount'],
                'currency' => 'UZS',
                'payment_method' => 'bank_transfer',
                'account_type' => $validated['fund_account'],
                'notes' => "Qarz/Kredit tushumi: " . $validated['creditor_name'],
                'status' => 'approved',
                'created_by' => auth()->id(),
                'approved_by' => auth()->id()
            ]);
        }

        return redirect()->back()->with('message', 'Qarz / Kredit muvaffaqiyatli qo\'shildi va hisobga o\'tkazildi.');
    }

    public function updateLiability(Request $request, \App\Models\FinancialLiability $liability)
    {
        $validated = $request->validate([
            'type' => 'required|in:bank_loan,supplier_debt,partner_debt',
            'creditor_name' => 'required|string|max:255',
            'total_amount' => 'required|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'term_months' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'monthly_payment' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'required|in:active,paid_off'
        ]);

        if (empty($validated['monthly_payment']) && !empty($validated['term_months'])) {
            $p = $validated['total_amount'];
            $n = $validated['term_months'];
            $r_annual = $validated['interest_rate'] ?? 0;

            if ($r_annual > 0) {
                $r = ($r_annual / 100) / 12;
                $validated['monthly_payment'] = $p * ($r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
            } else {
                $validated['monthly_payment'] = $p / $n;
            }
        }

        $liability->update($validated);

        return redirect()->back()->with('message', 'Qarz ma\'lumotlari yangilandi.');
    }

    public function destroyLiability(\App\Models\FinancialLiability $liability)
    {
        $liability->delete();

        return redirect()->back()->with('message', 'Qarz muvaffaqiyatli o\'chirildi.');
    }

    public function storeLiabilityPayment(Request $request, \App\Models\FinancialLiability $liability)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
            'account_type' => 'required|in:main,credit'
        ]);

        $payment = \App\Models\LiabilityPayment::create([
            'financial_liability_id' => $liability->id,
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'],
            'status' => 'paid'
        ]);

        // Deduct from the selected account
        \App\Models\CompanyBalance::addExpense($validated['amount'], $validated['account_type']);

        // Check if fully paid
        $totalPaid = $liability->payments()->where('status', 'paid')->sum('amount');
        if ($totalPaid >= $liability->total_amount) {
            $liability->update(['status' => 'paid_off']);
        }

        return redirect()->back()->with('message', 'To\'lov yozildi');
    }

    public function paySupplier(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'amount' => 'required|numeric|min:0',
            'account_type' => 'required|in:main,bank,credit,debt',
            'notes' => 'nullable|string'
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated) {
            $supplier = \App\Models\Supplier::findOrFail($validated['supplier_id']);
            
            // Ayirish balansdan
            $supplier->decrement('balance', $validated['amount']);

            // Moliya markazi balansidan ayirish
            \App\Models\CompanyBalance::addExpense($validated['amount'], $validated['account_type']);

            // Transaction yozish
            Transaction::create([
                'type' => 'expense',
                'amount' => $validated['amount'],
                'category' => 'supplier_payment',
                'account_type' => $validated['account_type'],
                'payment_method' => 'cash',
                'notes' => "Ta'minotchi ({$supplier->name}) ga to'lov. " . ($validated['notes'] ?? ''),
                'created_by' => auth()->id(),
                'approved_by' => auth()->id(),
                'status' => 'approved',
                'transaction_date' => now(),
            ]);
        });

        return back()->with('success', 'Ta\'minotchiga to\'lov muvaffaqiyatli amalga oshirildi.');
    }
}
