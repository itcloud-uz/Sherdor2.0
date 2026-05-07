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
            'contracts' => \App\Models\Contract::with(['client', 'unit'])->where('status', 'active')->get(),
            'financialLiabilities' => \App\Models\FinancialLiability::with('payments')->latest()->get(),
            'suppliers' => \App\Models\Supplier::latest()->get(),
            'workers' => \App\Models\Worker::latest()->get(),
            'machineries' => \App\Models\Machinery::latest()->get(),
            'buildings' => \App\Models\Building::latest()->get()
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
            $this->approveTransaction($transaction);
            return redirect()->back()->with('message', 'Tranzaksiya muvaffaqiyatli saqlandi va tasdiqlandi.');
        }

        return redirect()->back()->with('message', 'Tranzaksiya saqlandi va tasdiqlash uchun yuborildi.');
    }

    public function approveTransaction(Transaction $transaction)
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

        if ($transaction->contract_id) {
            $remainingAmount = $transaction->amount;
            
            $schedules = \App\Models\PaymentSchedule::where('contract_id', $transaction->contract_id)
                ->where('status', 'pending')
                ->orderBy('due_date', 'asc')
                ->get();
                
            foreach ($schedules as $schedule) {
                if ($remainingAmount <= 0) break;
                
                $needed = $schedule->expected_amount - $schedule->paid_amount;
                
                if ($remainingAmount >= $needed) {
                    $schedule->paid_amount += $needed;
                    $schedule->status = 'paid';
                    $remainingAmount -= $needed;
                } else {
                    $schedule->paid_amount += $remainingAmount;
                    $remainingAmount = 0;
                }
                
                $schedule->save();
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
        // Revert balance if approved
        if ($transaction->status === 'approved') {
            if ($transaction->type === 'income') {
                \App\Models\CompanyBalance::addExpense($transaction->amount, $transaction->account_type);
            } else {
                \App\Models\CompanyBalance::addIncome($transaction->amount, $transaction->account_type);
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
