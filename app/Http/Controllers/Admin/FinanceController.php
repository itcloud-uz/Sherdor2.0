<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\CashRegister;
use App\Models\Counterparty;
use App\Models\Invoice;
use App\Models\MoneyTransfer;
use App\Models\Building;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Worker;
use App\Models\Partner;
use App\Models\FixedAsset;
use App\Models\Brigade;
use App\Models\MachineOwner;
use App\Models\Machinery;
use App\Models\TransactionCategory;
use App\Models\CompanyBalance;
use App\Models\ConsensusRequest;
use App\Models\ConsensusVote;
use App\Models\PaymentSchedule;
use App\Models\FinancialLiability;
use App\Models\LiabilityPayment;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinanceController extends Controller
{
    public function index()
    {
        // Cleanup unwanted categories
        TransactionCategory::where('name', 'like', '%Farxod%')->delete();

        $transactions = Transaction::with([
            'creator', 
            'approver', 
            'contract.client', 
            'contract.unit', 
            'category', 
            'cashRegister', 
            'counterparty', 
            'invoice'
        ])->latest()->limit(500)->get();
        
        $rawBalance = CompanyBalance::firstOrCreate([]);
        $dynamicUZS = CashRegister::where('currency', 'UZS')->sum('balance');
        $dynamicUSD = CashRegister::where('currency', 'USD')->sum('balance');
        $creditBalance = CashRegister::where('name', 'like', '%Kredit%')->sum('balance');

        $balance = (object)[
            'total_balance' => $dynamicUZS,
            'credit_balance' => $creditBalance,
            'usd_balance' => $dynamicUSD,
            'total_income' => $rawBalance->total_income ?? 0,
            'total_expense' => $rawBalance->total_expense ?? 0,
        ];
        
        $pendingIncomes = Transaction::where('status', 'pending')->where('type', 'income')->sum('amount');
        $pendingExpenses = Transaction::where('status', 'pending')->where('type', 'expense')->sum('amount');

        return Inertia::render('Admin/Finance/Index', [
            'transactions' => $transactions,
            'balance' => $balance,
            'pendingIncomes' => $pendingIncomes,
            'pendingExpenses' => $pendingExpenses,
            'categories' => TransactionCategory::all(),
            'contracts' => \App\Models\Contract::with(['client', 'unit'])->where('status', '!=', 'completed')->latest()->limit(500)->get(),
            'financialLiabilities' => FinancialLiability::with('payments')->latest()->get(),
            'suppliers' => Supplier::latest()->get(),
            'workers' => Worker::latest()->get(),
            'machineries' => Machinery::latest()->get(),
            'buildings' => Building::latest()->get(),
            'clients' => Client::latest()->limit(500)->get(),
            'partners' => Partner::with('user')->latest()->get(),
            'fixedAssets' => FixedAsset::latest()->get(),
            'brigades' => Brigade::latest()->get(),
            'machineOwners' => MachineOwner::latest()->get(),
            // New tables
            'registers' => CashRegister::with('building')->get(),
            'counterparties' => Counterparty::with('customable')->get(),
            'invoices' => Invoice::with(['counterparty', 'building'])->latest()->get(),
            'moneyTransfers' => MoneyTransfer::with(['fromAccount', 'toAccount'])->latest()->get(),
        ]);
    }

    public function expensesBreakdown()
    {
        $expenses = Transaction::with('category')
            ->where('type', 'expense')
            ->where('status', 'approved')
            ->get();

        $grouped = $expenses->groupBy('category_id')->map(function ($txns) {
            $cat = array_key_exists('category', $txns->first()->getRelations()) ? $txns->first()->getRelation('category') : $txns->first()->category()->first();
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
            'account_id' => 'required|exists:cash_registers,id',
            'counterparty_id' => 'nullable|exists:counterparties,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'contract_id' => 'nullable|exists:contracts,id',
            'client_id' => 'nullable|exists:clients,id',
            'partner_id' => 'nullable|exists:partners,id',
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
            'target_type' => 'nullable|string|in:partner,supplier,client,worker,brigade,machine_owner',
            'target_id' => 'nullable|integer',
            'exchange_rate' => 'nullable|numeric|min:0',
            'converted_amount' => 'nullable|numeric|min:0',
        ]);

        // 1. Object Lock Check
        $register = CashRegister::findOrFail($validated['account_id']);
        if ($register->building_id) {
            if (!empty($validated['building_id']) && $validated['building_id'] != $register->building_id) {
                return redirect()->back()->withErrors(['error' => "Ushbu kassa faqat '" . ($register->building->name ?? 'Belgilangan') . "' obyekti uchun ishlatilishi mumkin!"]);
            }
            $validated['building_id'] = $register->building_id;
        }

        // Set currency from CashRegister if not specified
        if (empty($validated['currency'])) {
            $validated['currency'] = $register->currency;
        }

        // Setup exchange rate and converted amount
        $rate = $validated['exchange_rate'] ?? 1.0;
        $validated['exchange_rate'] = $rate;
        if (empty($validated['converted_amount'])) {
            $validated['converted_amount'] = $validated['amount'] * $rate;
        }

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
            $cat = TransactionCategory::firstOrCreate(
                ['name' => $validated['category_name']],
                ['type' => $validated['type']]
            );
            $validated['category_id'] = $cat->id;
        }

        // Consensus logic for large expenses
        $requiresConsensus = false;
        $isCreditRegister = stripos($register->name, 'kredit') !== false;
        if ($validated['type'] === 'expense' && $validated['amount'] >= 100000000 && !$isCreditRegister) {
            $requiresConsensus = true;
            $validated['requires_consensus'] = true;
        }
        
        // Map polymorphic target_id if not present but counterparty is selected
        if (!empty($validated['counterparty_id']) && empty($validated['target_id'])) {
            $counterparty = Counterparty::find($validated['counterparty_id']);
            if ($counterparty) {
                $validated['target_id'] = $counterparty->customable_id;
                $validated['target_type'] = $counterparty->type === 'employee' ? 'worker' : $counterparty->type;
            }
        }

        $transaction = Transaction::create($validated);

        if ($requiresConsensus) {
            $consensusRequest = ConsensusRequest::create([
                'transaction_id' => $transaction->id,
                'status' => 'pending'
            ]);

            $partners = Partner::all();
            foreach ($partners as $partner) {
                ConsensusVote::create([
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

    public function approveTransaction(Request $request, Transaction $transaction)
    {
        if ($transaction->status !== 'pending') {
            return response()->json(['error' => 'Transaction is not pending.'], 400);
        }

        if ($transaction->requires_consensus) {
            return response()->json(['error' => 'Transaction requires partner consensus and cannot be approved manually.'], 403);
        }

        $validated = $request->validate([
            'applied_amount' => 'nullable|numeric|min:0',
            'refund_amount' => 'nullable|numeric|min:0',
        ]);

        $transaction->status = 'approved';
        $transaction->approved_by = auth()->id();
        $transaction->receipt_hash = 'TXN-' . strtoupper(uniqid()) . '-' . $transaction->id;
        $transaction->save();

        if (!empty($validated['refund_amount']) && $validated['refund_amount'] > 0) {
            $pendingCategory = TransactionCategory::firstOrCreate(
                ['name' => 'Kutayotgan Naqdlashtirish'],
                ['type' => 'transfer', 'description' => 'Shartnomadan ortib qolgan va mijozga qaytarilishi kerak bo\'lgan summa']
            );

            Transaction::create([
                'type' => 'transfer',
                'amount' => $validated['refund_amount'],
                'account_id' => $transaction->account_id,
                'contract_id' => $transaction->contract_id,
                'category_id' => $pendingCategory->id,
                'status' => 'pending',
                'transaction_date' => now(),
                'notes' => 'Shartnoma to\'lovidan ortgan summa (Qaytim uchun)',
                'created_by' => auth()->id()
            ]);
        }

        // 1. Account / Cash Register Balance Update
        $register = CashRegister::find($transaction->account_id);
        if ($register) {
            if ($transaction->type === 'income') {
                $register->increment('balance', $transaction->amount);
            } else {
                $register->decrement('balance', $transaction->amount);
            }
        }

        // Also update the legacy CompanyBalance metrics
        if ($transaction->type === 'income') {
            CompanyBalance::addIncome($transaction->amount, $register && stripos($register->name, 'kredit') !== false ? 'credit' : 'main');
        } else {
            CompanyBalance::addExpense($transaction->amount, $register && stripos($register->name, 'kredit') !== false ? 'credit' : 'main');
        }

        // 2. Counterparty Balance Update
        $counterparty = Counterparty::find($transaction->counterparty_id);
        if ($counterparty) {
            $convAmount = $transaction->converted_amount ?? $transaction->amount;
            if ($transaction->type === 'income') {
                if ($counterparty->type === 'client') {
                    $counterparty->increment('balance', $convAmount);
                } else {
                    $counterparty->decrement('balance', $convAmount);
                }
            } else {
                if ($counterparty->type === 'client') {
                    $counterparty->decrement('balance', $convAmount);
                } else {
                    $counterparty->increment('balance', $convAmount);
                }
            }
        }

        // 3. Invoice status updates
        if ($transaction->invoice_id) {
            $invoice = Invoice::find($transaction->invoice_id);
            if ($invoice) {
                // If invoice currency is UZS but payment was converted, use converted_amount
                $paidVal = ($invoice->currency === 'UZS' && $transaction->currency !== 'UZS') 
                    ? ($transaction->converted_amount ?? $transaction->amount) 
                    : $transaction->amount;
                $invoice->increment('paid_amount', $paidVal);
                
                if ($invoice->paid_amount >= $invoice->amount) {
                    $invoice->update(['status' => 'paid']);
                } elseif ($invoice->paid_amount > 0) {
                    $invoice->update(['status' => 'partially_paid']);
                }
            }
        }

        // 4. Partner Special Balances
        if ($transaction->partner_id) {
            $accountPartner = Partner::find($transaction->partner_id);
            if ($accountPartner) {
                if ($transaction->type === 'income') {
                    $accountPartner->increment('accountable_balance', $transaction->amount);
                } else {
                    $accountPartner->decrement('accountable_balance', $transaction->amount);
                }
            }
        }

        // 5. Worker Transactions
        if ($transaction->worker_id) {
            $worker = Worker::find($transaction->worker_id);
            if ($worker) {
                $workerAmount = $transaction->type === 'income' ? $transaction->amount : -$transaction->amount;
                $worker->increment('balance', $workerAmount);
                DB::table('worker_transactions')->insert([
                    'worker_id' => $worker->id,
                    'type' => $transaction->type === 'income' ? 'payment_received' : 'advance_payment',
                    'amount' => $transaction->amount,
                    'work_category' => $transaction->work_category,
                    'description' => "Moliya: " . ($transaction->notes ?? ($transaction->category->name ?? 'Xarajat')),
                    'date' => $transaction->transaction_date ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'finance_transaction_id' => $transaction->id,
                ]);
            }
        }

        // 6. Machinery Transactions
        if ($transaction->machinery_id) {
            $machinery = Machinery::find($transaction->machinery_id);
            if ($machinery) {
                $machineryAmount = $transaction->type === 'income' ? $transaction->amount : -$transaction->amount;
                $machinery->increment('balance', $machineryAmount);
                DB::table('machinery_transactions')->insert([
                    'machinery_id' => $machinery->id,
                    'type' => $transaction->type === 'income' ? 'payment_received' : 'advance_payment',
                    'amount' => $transaction->amount,
                    'description' => "Moliya: " . ($transaction->notes ?? ($transaction->category->name ?? 'Texnika')),
                    'date' => $transaction->transaction_date ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'finance_transaction_id' => $transaction->id,
                ]);
            }
        }

        // 7. Supplier Transactions
        if ($transaction->supplier_id) {
            $supplier = Supplier::find($transaction->supplier_id);
            if ($supplier) {
                $supplierAmount = $transaction->type === 'income' ? $transaction->amount : -$transaction->amount;
                $supplier->increment('balance', $supplierAmount);
            }
        }

        if (!empty($transaction->target_type) && !empty($transaction->target_id)) {
            $this->updateTargetBalance($transaction->target_type, $transaction->target_id, $transaction->amount, $transaction->type);
        }

        if ($transaction->fixed_asset_id && $transaction->type === 'expense') {
            $partners = Partner::all();
            if ($partners->count() > 0) {
                $splitAmount = $transaction->amount / $partners->count();
                foreach ($partners as $p) {
                    $p->increment('accountable_balance', $splitAmount);
                }
            }
        }

        // 8. Payment Schedule Reductions
        if ($transaction->contract_id && $transaction->type === 'income') {
            $appliedAmount = $validated['applied_amount'] ?? ($transaction->converted_amount ?? $transaction->amount);
            $refundAmount = ($transaction->converted_amount ?? $transaction->amount) - $appliedAmount;

            if ($appliedAmount > 0) {
                $remainingAmount = $appliedAmount;
                
                $schedules = PaymentSchedule::where('contract_id', $transaction->contract_id)
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
                        $schedule->status = 'partial';
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
                $refundCategory = TransactionCategory::firstOrCreate(
                    ['name' => 'Kutayotgan Naqdlashtirish'],
                    ['type' => 'expense', 'description' => 'Mijoz ortiqcha to\'lovni naqd qilib olishi uchun navbat']
                );

                Transaction::create([
                    'type' => 'expense',
                    'amount' => $refundAmount,
                    'account_id' => $transaction->account_id,
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

    private function updateTargetBalance($type, $id, $amount, $txnType)
    {
        if ($type === 'supplier') {
            $supplier = Supplier::find($id);
            if ($supplier) {
                $txnType === 'income' ? $supplier->increment('balance', $amount) : $supplier->decrement('balance', $amount);
            }
        } elseif ($type === 'client') {
            $client = Client::find($id);
            if ($client && Schema::hasColumn('clients', 'debt_balance')) {
                $txnType === 'income' ? $client->decrement('debt_balance', $amount) : $client->increment('debt_balance', $amount);
            }
        } elseif ($type === 'partner') {
            $partner = Partner::find($id);
            if ($partner) {
                $txnType === 'income' ? $partner->increment('invested_amount', $amount) : $partner->increment('withdrawn_amount', $amount);
            }
        } elseif ($type === 'worker') {
            $worker = Worker::find($id);
            if ($worker) {
                $txnType === 'expense' ? $worker->decrement('balance', $amount) : null;
            }
        } elseif ($type === 'brigade') {
            $brigade = Brigade::find($id);
            if ($brigade) {
                $txnType === 'expense' ? $brigade->decrement('balance', $amount) : null;
            }
        } elseif ($type === 'machine_owner') {
            $owner = MachineOwner::find($id);
            if ($owner) {
                $txnType === 'expense' ? $owner->decrement('balance', $amount) : null;
            }
        }
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
            'notes' => 'nullable|string',
        ]);

        // Revert old balance changes if approved
        if ($transaction->status === 'approved') {
            $register = CashRegister::find($transaction->account_id);
            if ($register) {
                if ($transaction->type === 'income') {
                    $register->decrement('balance', $transaction->amount);
                    $register->increment('balance', $validated['amount']);
                } else {
                    $register->increment('balance', $transaction->amount);
                    $register->decrement('balance', $validated['amount']);
                }
            }

            $counterparty = Counterparty::find($transaction->counterparty_id);
            if ($counterparty) {
                $oldConv = $transaction->converted_amount ?? $transaction->amount;
                $newConv = ($transaction->exchange_rate ?? 1.0) * $validated['amount'];
                
                if ($transaction->type === 'income') {
                    if ($counterparty->type === 'client') {
                        $counterparty->decrement('balance', $oldConv);
                        $counterparty->increment('balance', $newConv);
                    } else {
                        $counterparty->increment('balance', $oldConv);
                        $counterparty->decrement('balance', $newConv);
                    }
                } else {
                    if ($counterparty->type === 'client') {
                        $counterparty->increment('balance', $oldConv);
                        $counterparty->decrement('balance', $newConv);
                    } else {
                        $counterparty->decrement('balance', $oldConv);
                        $counterparty->increment('balance', $newConv);
                    }
                }
                
                $transaction->converted_amount = $newConv;
            }
        }

        $transaction->update($validated);

        return redirect()->back()->with('message', 'Tranzaksiya muvaffaqiyatli tahrirlandi.');
    }

    public function destroy(Transaction $transaction)
    {
        if ($transaction->status === 'pending' && $transaction->partner_id) {
            return redirect()->back()->withErrors(['error' => 'Hamkor kiritgan so\'rovni o\'chirib bo\'lmaydi! Iltimos, uni Tasdiqlar panelidan "Rad etish" (Reject) qiling.']);
        }

        // Revert balance if approved
        if ($transaction->status === 'approved') {
            $register = CashRegister::find($transaction->account_id);
            if ($register) {
                if ($transaction->type === 'income') {
                    $register->decrement('balance', $transaction->amount);
                } else {
                    $register->increment('balance', $transaction->amount);
                }
            }

            // Revert Counterparty balance
            $counterparty = Counterparty::find($transaction->counterparty_id);
            if ($counterparty) {
                $conv = $transaction->converted_amount ?? $transaction->amount;
                if ($transaction->type === 'income') {
                    if ($counterparty->type === 'client') {
                        $counterparty->decrement('balance', $conv);
                    } else {
                        $counterparty->increment('balance', $conv);
                    }
                } else {
                    if ($counterparty->type === 'client') {
                        $counterparty->increment('balance', $conv);
                    } else {
                        $counterparty->decrement('balance', $conv);
                    }
                }
            }

            // Revert Invoice payment
            if ($transaction->invoice_id) {
                $invoice = Invoice::find($transaction->invoice_id);
                if ($invoice) {
                    $invoice->decrement('paid_amount', $transaction->amount);
                    if ($invoice->paid_amount <= 0) {
                        $invoice->update(['status' => 'new']);
                    } else {
                        $invoice->update(['status' => 'partially_paid']);
                    }
                }
            }

            // Revert Partner Balance
            if ($transaction->partner_id) {
                $partner = Partner::find($transaction->partner_id);
                if ($partner) {
                    $partner->decrement('accountable_balance', $transaction->amount);
                }
            }

            // Revert Fixed Asset Splits
            if ($transaction->fixed_asset_id && $transaction->type === 'expense') {
                $partners = Partner::all();
                if ($partners->count() > 0) {
                    $splitAmount = $transaction->amount / $partners->count();
                    foreach ($partners as $p) {
                        $p->decrement('accountable_balance', $splitAmount);
                    }
                }
            }

            // Revert CRM Contract Payment Schedules
            if ($transaction->contract_id && $transaction->type === 'income') {
                $amountToRevert = $transaction->converted_amount ?? $transaction->amount;
                $schedules = PaymentSchedule::where('contract_id', $transaction->contract_id)
                    ->where('paid_amount', '>', 0)
                    ->orderBy('due_date', 'desc')
                    ->get();

                foreach ($schedules as $schedule) {
                    if ($amountToRevert <= 0) break;
                    
                    if ($schedule->paid_amount <= $amountToRevert) {
                        $amountToRevert -= $schedule->paid_amount;
                        $schedule->paid_amount = 0;
                        $schedule->status = 'pending';
                        $schedule->actual_payment_date = null;
                        $schedule->payment_method = null;
                        $schedule->original_payment_amount = null;
                    } else {
                        $schedule->paid_amount -= $amountToRevert;
                        $schedule->status = 'partial';
                        $amountToRevert = 0;
                    }
                    $schedule->save();
                }
            }

            // Revert Ledger Transactions linked via finance_transaction_id
            $ledgerTables = [
                'worker_transactions' => Worker::class,
                'brigade_transactions' => Brigade::class,
                'user_transactions' => \App\Models\User::class,
                'machine_owner_transactions' => MachineOwner::class,
                'machinery_transactions' => Machinery::class,
            ];

            foreach ($ledgerTables as $table => $modelClass) {
                $linkedTxs = DB::table($table)
                    ->where('finance_transaction_id', $transaction->id)
                    ->get();
                
                foreach ($linkedTxs as $tx) {
                    $foreignKey = str_replace('_transactions', '_id', $table);
                    if ($table === 'user_transactions') {
                        $foreignKey = 'user_id';
                    } elseif ($table === 'machine_owner_transactions') {
                        $foreignKey = 'machine_owner_id';
                    }
                    
                    $entity = $modelClass::find($tx->$foreignKey);
                    if ($entity) {
                        if ($tx->type === 'salary_accrual') {
                            $entity->decrement('balance', $tx->amount);
                        } else {
                            $entity->increment('balance', $tx->amount);
                        }
                    }
                }
                DB::table($table)->where('finance_transaction_id', $transaction->id)->delete();
            }
        }

        $transaction->delete();

        return redirect()->back()->with('message', 'Tranzaksiya muvaffaqiyatli o\'chirildi.');
    }

    public function internalTransfer(Request $request)
    {
        $validated = $request->validate([
            'from_account_id' => 'required|exists:cash_registers,id',
            'to_account_id' => 'required|exists:cash_registers,id',
            'sent_amount' => 'required|numeric|min:0',
            'exchange_rate' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validated['from_account_id'] == $validated['to_account_id']) {
            return redirect()->back()->withErrors(['error' => 'Chiqish va kirish kassalari bir xil bo\'lishi mumkin emas']);
        }

        $fromAcc = CashRegister::findOrFail($validated['from_account_id']);
        $toAcc = CashRegister::findOrFail($validated['to_account_id']);

        if ($fromAcc->balance < $validated['sent_amount']) {
            return redirect()->back()->withErrors(['error' => "Kassa balansi yetarli emas! Mavjud balans: {$fromAcc->balance} {$fromAcc->currency}"]);
        }

        $rate = $validated['exchange_rate'] ?? 1.0;
        $receivedAmount = $validated['sent_amount'] * $rate;

        DB::transaction(function () use ($fromAcc, $toAcc, $validated, $rate, $receivedAmount) {
            // Deduct
            $fromAcc->decrement('balance', $validated['sent_amount']);
            // Deposit
            $toAcc->increment('balance', $receivedAmount);

            // Record Transfer Log
            $transfer = MoneyTransfer::create([
                'from_account_id' => $fromAcc->id,
                'to_account_id' => $toAcc->id,
                'sent_amount' => $validated['sent_amount'],
                'sent_currency' => $fromAcc->currency,
                'exchange_rate' => $rate,
                'received_amount' => $receivedAmount,
                'received_currency' => $toAcc->currency,
                'description' => $validated['description'] ?? 'Ichki o\'tkazma',
            ]);

            // Save Informational Transactions
            $transferCategory = TransactionCategory::firstOrCreate(
                ['name' => 'Ichki O\'tkazma'],
                ['type' => 'transfer', 'description' => 'Ichki kassalararo o\'tkazmalar']
            );

            // Outflow
            Transaction::create([
                'type' => 'expense',
                'amount' => $validated['sent_amount'],
                'currency' => $fromAcc->currency,
                'payment_method' => $fromAcc->type === 'bank' ? 'bank_transfer' : 'cash',
                'account_id' => $fromAcc->id,
                'category_id' => $transferCategory->id,
                'status' => 'approved',
                'transaction_date' => now(),
                'notes' => "[Chiqim - O'tkazma] ID: #{$transfer->id}. " . ($validated['description'] ?? ''),
                'created_by' => auth()->id(),
                'approved_by' => auth()->id()
            ]);

            // Inflow
            Transaction::create([
                'type' => 'income',
                'amount' => $receivedAmount,
                'currency' => $toAcc->currency,
                'payment_method' => $toAcc->type === 'bank' ? 'bank_transfer' : 'cash',
                'account_id' => $toAcc->id,
                'category_id' => $transferCategory->id,
                'status' => 'approved',
                'transaction_date' => now(),
                'notes' => "[Kirim - O'tkazma] ID: #{$transfer->id}. " . ($validated['description'] ?? ''),
                'created_by' => auth()->id(),
                'approved_by' => auth()->id()
            ]);
        });

        return redirect()->back()->with('message', 'Mablag\' muvaffaqiyatli o\'tkazildi.');
    }

    // --- Cash Register CRUD ---
    public function storeRegister(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,bank',
            'currency' => 'required|string|size:3',
            'building_id' => 'nullable|exists:buildings,id',
            'balance' => 'nullable|numeric|min:0',
        ]);

        CashRegister::create($validated);

        return redirect()->back()->with('message', 'Yangi kassa muvaffaqiyatli qo\'shildi.');
    }

    public function updateRegister(Request $request, CashRegister $register)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,bank',
            'currency' => 'required|string|size:3',
            'building_id' => 'nullable|exists:buildings,id',
            'balance' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $register->update($validated);

        return redirect()->back()->with('message', 'Kassa muvaffaqiyatli tahrirlandi.');
    }

    public function destroyRegister(CashRegister $register)
    {
        if ($register->transactions()->count() > 0) {
            return redirect()->back()->withErrors(['error' => 'Ushbu kassada tranzaksiyalar mavjudligi sababli uni o\'chirib bo\'lmaydi. Faoliyatsiz qilib belgilashingiz mumkin.']);
        }

        $register->delete();

        return redirect()->back()->with('message', 'Kassa muvaffaqiyatli o\'chirildi.');
    }

    // --- Counterparties CRUD ---
    public function storeCounterparty(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:client,supplier,employee',
            'phone' => 'nullable|string',
            'email' => 'nullable|string|email',
            'balance' => 'nullable|numeric',
        ]);

        Counterparty::create($validated);

        return redirect()->back()->with('message', 'Kontragent muvaffaqiyatli qo\'shildi.');
    }

    public function updateCounterparty(Request $request, Counterparty $counterparty)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:client,supplier,employee',
            'phone' => 'nullable|string',
            'email' => 'nullable|string|email',
            'balance' => 'required|numeric',
        ]);

        $counterparty->update($validated);

        return redirect()->back()->with('message', 'Kontragent muvaffaqiyatli yangilandi.');
    }

    public function destroyCounterparty(Counterparty $counterparty)
    {
        if ($counterparty->transactions()->count() > 0 || $counterparty->invoices()->count() > 0) {
            return redirect()->back()->withErrors(['error' => 'Ushbu kontragent bilan bog\'liq tranzaksiyalar yoki hisob-fakturalar mavjud, uni o\'chirish mumkin emas.']);
        }

        $counterparty->delete();

        return redirect()->back()->with('message', 'Kontragent muvaffaqiyatli o\'chirildi.');
    }

    // --- Invoices CRUD ---
    public function storeInvoice(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|max:255|unique:invoices,invoice_number',
            'counterparty_id' => 'required|exists:counterparties,id',
            'building_id' => 'nullable|exists:buildings,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'due_date' => 'required|date',
            'type' => 'required|in:monthly_schedule,manual_expense,procurement,loan',
            'description' => 'nullable|string',
        ]);

        $validated['paid_amount'] = 0.00;
        $validated['status'] = 'new';

        Invoice::create($validated);

        return redirect()->back()->with('message', 'Hisob-faktura muvaffaqiyatli yaratildi.');
    }

    public function updateInvoice(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|max:255|unique:invoices,invoice_number,' . $invoice->id,
            'counterparty_id' => 'required|exists:counterparties,id',
            'building_id' => 'nullable|exists:buildings,id',
            'amount' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'due_date' => 'required|date',
            'status' => 'required|in:new,partially_paid,paid,cancelled',
            'type' => 'required|in:monthly_schedule,manual_expense,procurement,loan',
            'description' => 'nullable|string',
        ]);

        $invoice->update($validated);

        return redirect()->back()->with('message', 'Hisob-faktura muvaffaqiyatli yangilandi.');
    }

    public function destroyInvoice(Invoice $invoice)
    {
        if ($invoice->paid_amount > 0) {
            return redirect()->back()->withErrors(['error' => 'Kisman yoki to\'liq to\'langan hisob-fakturalarni o\'chirib bo\'lmaydi.']);
        }

        $invoice->delete();

        return redirect()->back()->with('message', 'Hisob-faktura muvaffaqiyatli o\'chirildi.');
    }

    // --- Excel Exports ---
    public function exportInvoicesExcel(Request $request)
    {
        $query = Invoice::with(['counterparty', 'building']);

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->filled('building_id')) {
            $query->where('building_id', $request->building_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('counterparty_id')) {
            $query->where('counterparty_id', $request->counterparty_id);
        }

        $invoices = $query->latest()->get();

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=invoices_" . date('Y-m-d_H-i') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($invoices) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF"); // BOM for Excel UTF-8 support

            fputcsv($file, [
                'ID', 
                'Faktura raqami', 
                'Kontragent', 
                'Obyekt', 
                'Summa', 
                'To\'langan', 
                'Valyuta', 
                'Muddati', 
                'Holati', 
                'Turi', 
                'Sana'
            ], ';');

            foreach ($invoices as $invoice) {
                fputcsv($file, [
                    $invoice->id,
                    $invoice->invoice_number,
                    $invoice->counterparty->name ?? 'Noma\'lum',
                    $invoice->building->name ?? 'Asosiy',
                    $invoice->amount,
                    $invoice->paid_amount,
                    $invoice->currency,
                    $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '',
                    $invoice->status,
                    $invoice->type,
                    $invoice->created_at->format('Y-m-d H:i')
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportTransactionsExcel(Request $request)
    {
        $query = Transaction::with(['creator', 'approver', 'cashRegister', 'counterparty', 'category']);

        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }
        if ($request->filled('building_id')) {
            $query->where('building_id', $request->building_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('counterparty_id')) {
            $query->where('counterparty_id', $request->counterparty_id);
        }
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        $transactions = $query->latest()->get();

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=transactions_" . date('Y-m-d_H-i') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF"); // BOM for Excel UTF-8 support

            fputcsv($file, [
                'ID', 
                'Turi', 
                'Kassa / Bank', 
                'Kontragent', 
                'Kategoriya', 
                'Summa', 
                'Valyuta', 
                'Kurs', 
                'Konvertatsiya (UZS)', 
                'To\'lov uslubi', 
                'Holati', 
                'Izoh', 
                'Sana'
            ], ';');

            foreach ($transactions as $txn) {
                fputcsv($file, [
                    $txn->id,
                    $txn->type === 'income' ? 'Kirim' : 'Chiqim',
                    $txn->cashRegister->name ?? 'Asosiy',
                    $txn->counterparty->name ?? 'Noma\'lum',
                    $txn->category->name ?? 'Boshqa',
                    $txn->amount,
                    $txn->currency,
                    $txn->exchange_rate,
                    $txn->converted_amount,
                    $txn->payment_method,
                    $txn->status,
                    $txn->notes,
                    $txn->transaction_date
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:transaction_categories,name',
            'type' => 'required|in:income,expense,both'
        ]);

        TransactionCategory::create($validated);

        return redirect()->back()->with('message', 'Kategoriya muvaffaqiyatli qo\'shildi.');
    }

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
        
        $liability = FinancialLiability::create($validated);

        if (!empty($validated['fund_account'])) {
            CompanyBalance::addIncome($validated['total_amount'], $validated['fund_account']);
            
            // Map to default UZS registers
            $reg = CashRegister::where('name', 'like', '%' . ($validated['fund_account'] === 'credit' ? 'Kredit' : 'Asosiy Kassa (UZS)') . '%')->first();
            
            Transaction::create([
                'type' => 'income',
                'amount' => $validated['total_amount'],
                'currency' => 'UZS',
                'payment_method' => 'bank_transfer',
                'account_id' => $reg ? $reg->id : 1,
                'notes' => "Qarz/Kredit tushumi: " . $validated['creditor_name'],
                'status' => 'approved',
                'created_by' => auth()->id(),
                'approved_by' => auth()->id()
            ]);
            
            if ($reg) {
                $reg->increment('balance', $validated['total_amount']);
            }
        }

        return redirect()->back()->with('message', 'Qarz / Kredit muvaffaqiyatli qo\'shildi va hisobga o\'tkazildi.');
    }

    public function updateLiability(Request $request, FinancialLiability $liability)
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

    public function destroyLiability(FinancialLiability $liability)
    {
        $liability->delete();

        return redirect()->back()->with('message', 'Qarz muvaffaqiyatli o\'chirildi.');
    }

    public function storeLiabilityPayment(Request $request, FinancialLiability $liability)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
            'account_type' => 'required|in:main,credit'
        ]);

        $payment = LiabilityPayment::create([
            'financial_liability_id' => $liability->id,
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'],
            'status' => 'paid'
        ]);

        // Deduct from dynamic cash register
        $regName = $validated['account_type'] === 'credit' ? 'Kredit Kassasi (UZS)' : 'Asosiy Kassa (UZS)';
        $reg = CashRegister::where('name', $regName)->first();
        if ($reg) {
            $reg->decrement('balance', $validated['amount']);
        }

        // Deduct from legacy CompanyBalance
        CompanyBalance::addExpense($validated['amount'], $validated['account_type']);

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

        DB::transaction(function () use ($validated) {
            $supplier = Supplier::findOrFail($validated['supplier_id']);
            
            // Revert balance on Supplier table
            $supplier->decrement('balance', $validated['amount']);

            // Find matching CashRegister or default to first cash register
            $regName = 'Asosiy Kassa (UZS)';
            if ($validated['account_type'] === 'bank') {
                $regName = 'Asosiy Bank (UZS)';
            } elseif ($validated['account_type'] === 'credit') {
                $regName = 'Kredit Kassasi (UZS)';
            }
            $reg = CashRegister::where('name', $regName)->first();

            if ($reg) {
                $reg->decrement('balance', $validated['amount']);
            }

            // Moliya markazi legacy balansidan ayirish
            CompanyBalance::addExpense($validated['amount'], $validated['account_type']);

            // Counterparty link
            $counterparty = Counterparty::where([
                'customable_type' => 'App\Models\Supplier',
                'customable_id' => $supplier->id,
            ])->first();
            if ($counterparty) {
                $counterparty->increment('balance', $validated['amount']); // reduce what we owe them
            }

            // Transaction log
            Transaction::create([
                'type' => 'expense',
                'amount' => $validated['amount'],
                'currency' => 'UZS',
                'category' => 'supplier_payment',
                'account_id' => $reg ? $reg->id : 1,
                'counterparty_id' => $counterparty ? $counterparty->id : null,
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
