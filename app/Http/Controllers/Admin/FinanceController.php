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

        // Seed default transaction categories if they don't exist
        $defaultCategories = [
            ['name' => 'Mijoz To\'lovi (Shartnoma)', 'type' => 'income'],
            ['name' => 'Sarmoya (Investitsiya)', 'type' => 'income'],
            ['name' => 'Qarz tushumi', 'type' => 'income'],
            ['name' => 'Boshqa daromadlar', 'type' => 'income'],
            ['name' => 'Ishchi To\'lovi (Avans/Oylik)', 'type' => 'expense'],
            ['name' => 'Brigada to\'lovi', 'type' => 'expense'],
            ['name' => 'Texnika To\'lovi', 'type' => 'expense'],
            ['name' => 'Ta\'minotchi To\'lovi', 'type' => 'expense'],
            ['name' => 'Hamkorga to\'lov', 'type' => 'expense'],
            ['name' => 'Obyekt Xarajati', 'type' => 'expense'],
            ['name' => 'Qurilish materiallari', 'type' => 'expense'],
            ['name' => 'Ofis xarajatlari', 'type' => 'expense'],
            ['name' => 'Soliq va to\'lovlar', 'type' => 'expense'],
            ['name' => 'Qarz qaytarish', 'type' => 'expense'],
            ['name' => 'Asosiy vositalar', 'type' => 'expense'],
            ['name' => 'Boshqa xarajatlar', 'type' => 'expense'],
        ];

        foreach ($defaultCategories as $cat) {
            TransactionCategory::firstOrCreate(
                ['name' => $cat['name']],
                ['type' => $cat['type']]
            );
        }

        $tab = (int)request('tab', 0);
        
        $categories = TransactionCategory::where('is_active', true)->get();
        $allCategories = TransactionCategory::all();
        $registers = CashRegister::with('building')->get();
        $workers = Worker::latest()->get();
        $suppliers = Supplier::latest()->get();
        $partners = Partner::with('user')->latest()->get();
        $brigades = Brigade::latest()->get();
        $machineOwners = MachineOwner::latest()->get();
        $counterparties = Counterparty::with(['customable', 'transactions'])->get();
        $counterparties->loadMorph('customable', [
            \App\Models\Client::class => ['contracts.refundSchedules'],
        ]);
        $buildings = Building::latest()->get();
        $clients = Client::latest()->limit(500)->get();
        $contracts = \App\Models\Contract::with(['client', 'unit.floor.building', 'paymentSchedules'])->where('status', '!=', 'completed')->latest()->get();

        $transactions = [];
        $financialLiabilities = [];
        $machineries = [];
        $fixedAssets = [];
        $invoices = Invoice::with(['counterparty', 'building'])->latest()->get();
        $moneyTransfers = [];
        $rates = [];

        // Always query balances and pending totals for top cards/headers
        $dynamicUZS = CashRegister::whereNull('parent_id')->get()->sum('balance_uzs');
        $creditBalance = CashRegister::where('type', 'credit')->whereNull('parent_id')->get()->sum('balance_uzs');
        $rawBalance = CompanyBalance::firstOrCreate([]);
        $balance = (object)[
            'total_balance' => $dynamicUZS,
            'credit_balance' => $creditBalance,
            'usd_balance' => 0,
            'total_income' => $rawBalance->total_income ?? 0,
            'total_expense' => $rawBalance->total_expense ?? 0,
        ];
        
        $pendingIncomes = Transaction::where('status', 'pending')->where('type', 'income')->sum('amount');
        $pendingExpenses = Transaction::where('status', 'pending')->where('type', 'expense')->sum('amount');

        // Tab-specific queries
        if ($tab === 0 || $tab === 1 || $tab === 2 || $tab === 8 || $tab === 10 || $tab === 11 || $tab === 12) {
            $transactions = Transaction::with([
                'creator', 
                'approver', 
                'contract.client', 
                'contract.unit.floor.building', 
                'category', 
                'cashRegister', 
                'counterparty', 
                'invoice',
                'building'
            ])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get();
        }

        if ($tab === 1 || $tab === 11) {
            $financialLiabilities = FinancialLiability::with('payments')->latest()->get();
            $machineries = Machinery::latest()->get();
            $fixedAssets = FixedAsset::latest()->get();
        }

        if ($tab === 2 || $tab === 8 || $tab === 10 || $tab === 11) {
            $rates = DB::table('currency_rates')->get();
        }

        if ($tab === 6) {
            $moneyTransfers = MoneyTransfer::with(['fromAccount', 'toAccount'])->latest()->get();
        }

        return Inertia::render('Admin/Finance/Index', [
            'users' => \App\Models\User::orderBy('name', 'asc')->get(),
            'transactions' => $transactions,
            'balance' => $balance,
            'pendingIncomes' => $pendingIncomes,
            'pendingExpenses' => $pendingExpenses,
            'categories' => $categories,
            'allCategories' => $allCategories,
            'contracts' => $contracts,
            'financialLiabilities' => $financialLiabilities,
            'suppliers' => $suppliers,
            'workers' => $workers,
            'machineries' => $machineries,
            'buildings' => $buildings,
            'clients' => $clients,
            'partners' => $partners,
            'fixedAssets' => $fixedAssets,
            'brigades' => $brigades,
            'machineOwners' => $machineOwners,
            'registers' => $registers,
            'counterparties' => $counterparties,
            'invoices' => $invoices,
            'moneyTransfers' => $moneyTransfers,
            'rates' => $rates,
            'deletedTransactions' => Transaction::onlyTrashed()
                ->with(['creator', 'category', 'cashRegister', 'counterparty'])
                ->latest()
                ->limit(500)
                ->get(),
            'financeActions' => \App\Models\FinanceAction::with(['user', 'transaction'])
                ->latest()
                ->limit(500)
                ->get(),
            'deletedCounterparties' => Counterparty::onlyTrashed()
                ->latest()
                ->get(),
            'imports' => \App\Models\TransactionImport::with(['account', 'building', 'creator'])
                ->latest()
                ->get(),
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
        // Normalize payment_method
        if ($request->payment_method === 'bank') {
            $request->merge(['payment_method' => 'bank_transfer']);
        }

        $validated = $request->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string',
            'payment_method' => 'required|in:cash,card,bank_transfer',
            'account_id' => 'required|exists:cash_registers,id',
            'overpayment_account_id' => 'nullable|exists:cash_registers,id',
            'counterparty_id' => 'nullable|exists:counterparties,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'contract_id' => 'nullable|exists:contracts,id',
            'payment_schedule_id' => 'nullable|exists:payment_schedules,id',
            'refund_schedule_id' => 'nullable|exists:refund_schedules,id',
            'client_id' => 'nullable|exists:clients,id',
            'partner_id' => 'nullable|exists:partners,id',
            'notes' => 'nullable|string',
            'description' => 'nullable|string',
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
            'target_type' => 'nullable|string|in:partner,supplier,client,worker,brigade,machine_owner,user',
            'target_id' => 'nullable|integer',
            'exchange_rate' => 'nullable|numeric|min:0',
            'converted_amount' => 'nullable|numeric|min:0',
            'linked_work_type' => 'nullable|string',
            'linked_work_id' => 'nullable|integer',
        ]);

        foreach ($validated as $key => $value) {
            if ($value === '' && str_ends_with($key, '_id')) {
                $validated[$key] = null;
            }
        }

        // 1. Object Lock Check - Relaxed
        $register = CashRegister::findOrFail($validated['account_id']);
        if (empty($validated['building_id']) && $register->building_id) {
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
        
        if (!empty($validated['counterparty_id']) && empty($validated['target_id'])) {
            $counterparty = Counterparty::find($validated['counterparty_id']);
            if ($counterparty) {
                $validated['target_id'] = $counterparty->customable_id;
                if ($counterparty->type === 'employee') {
                    $validated['target_type'] = $counterparty->customable_type === \App\Models\User::class ? 'user' : 'worker';
                } else {
                    $validated['target_type'] = $counterparty->type;
                }
            }
        }

        // Automatic financial relation mapping and fallback inferences
        if (!empty($validated['payment_schedule_id']) && empty($validated['contract_id'])) {
            $sched = \App\Models\PaymentSchedule::find($validated['payment_schedule_id']);
            if ($sched) {
                $validated['contract_id'] = $sched->contract_id;
            }
        }

        if (!empty($validated['contract_id']) && empty($validated['client_id'])) {
            $contract = \App\Models\Contract::find($validated['contract_id']);
            if ($contract) {
                $validated['client_id'] = $contract->client_id;
            }
        }

        $targetFieldMap = [
            'client' => 'client_id',
            'supplier' => 'supplier_id',
            'worker' => 'worker_id',
            'partner' => 'partner_id',
            'brigade' => 'brigade_id',
            'machine_owner' => 'machine_owner_id',
        ];

        if (!empty($validated['target_type']) && !empty($validated['target_id'])) {
            $field = $targetFieldMap[$validated['target_type']] ?? null;
            if ($field && empty($validated[$field])) {
                $validated[$field] = $validated['target_id'];
            }
            
            if (empty($validated['counterparty_id'])) {
                $customableType = null;
                switch ($validated['target_type']) {
                    case 'supplier': $customableType = \App\Models\Supplier::class; break;
                    case 'machine_owner': $customableType = \App\Models\MachineOwner::class; break;
                    case 'brigade': $customableType = \App\Models\Brigade::class; break;
                    case 'partner': $customableType = \App\Models\Partner::class; break;
                    case 'client': $customableType = \App\Models\Client::class; break;
                    case 'worker': $customableType = \App\Models\Worker::class; break;
                    case 'user': $customableType = \App\Models\User::class; break;
                }
                if ($customableType) {
                    $cp = \App\Models\Counterparty::where('customable_type', $customableType)
                        ->where('customable_id', $validated['target_id'])
                        ->first();
                    if ($cp) {
                        $validated['counterparty_id'] = $cp->id;
                    }
                }
            }
        }

        $overpaid = 0;
        $overpaymentAccountId = $request->overpayment_account_id;
        $contract = null;
        if ($validated['type'] === 'income' && !empty($validated['contract_id']) && !empty($overpaymentAccountId)) {
            $contract = \App\Models\Contract::find($validated['contract_id']);
            if ($contract) {
                $total = (float)$contract->total_amount;
                $paid = (float)\DB::table('payment_schedules')->where('contract_id', $contract->id)->sum('paid_amount');
                $debt = max(0.0, $total - $paid);
                
                $originalAmount = (float)$validated['amount'];
                if ($originalAmount > $debt) {
                    $overpaid = $originalAmount - $debt;
                    $validated['amount'] = $debt;
                    if (!empty($validated['exchange_rate'])) {
                        $validated['converted_amount'] = $debt * $validated['exchange_rate'];
                    }
                }
            }
        }

        $transaction = Transaction::create($validated);

        // --- CUSTOM LOGIC FOR FIXED ASSETS ---
        if ($transaction->type === 'expense' && $transaction->category_id) {
            $cat = \App\Models\TransactionCategory::find($transaction->category_id);
            if ($cat && $cat->target_type === 'fixed_asset' && !$transaction->fixed_asset_id) {
                $assetName = !empty($transaction->notes) ? trim(explode('|', $transaction->notes)[0]) : $cat->name;
                $asset = \App\Models\FixedAsset::create([
                    'name' => $assetName,
                    'type' => 'other',
                    'purchase_date' => $transaction->transaction_date ?: now()->toDateString(),
                    'purchase_cost' => $transaction->amount,
                    'status' => 'active',
                    'description' => $transaction->notes ?? 'Tranzaksiyadan yaratildi.',
                    'service_life_months' => 60
                ]);
                $transaction->fixed_asset_id = $asset->id;
                $transaction->save();
            }
        }
        // --- END CUSTOM LOGIC ---

        if ($overpaid > 0 && $contract) {
            $overpaymentCategory = \App\Models\TransactionCategory::firstOrCreate(
                ['name' => 'Mijoz Ortiqcha To\'lovi (Bank)'],
                ['type' => 'income', 'description' => 'Shartnoma qiymatidan ortiqcha to\'langan va bank orqali kelib tushgan summa']
            );
            
            $overpaymentTx = \App\Models\Transaction::create([
                'type' => 'income',
                'amount' => $overpaid,
                'currency' => $transaction->currency,
                'payment_method' => 'bank_transfer',
                'account_id' => $overpaymentAccountId,
                'client_id' => $transaction->client_id,
                'counterparty_id' => $transaction->counterparty_id,
                'building_id' => $transaction->building_id,
                'category_id' => $overpaymentCategory->id,
                'status' => 'pending',
                'transaction_date' => $transaction->transaction_date,
                'notes' => 'Mijoz ortiqcha to\'lovi - Shartnoma #' . ($contract->contract_number ?: $contract->id) . ' dan ortgan',
                'created_by' => auth()->id()
            ]);
            
            if (!empty($validated['auto_approve']) && !$requiresConsensus) {
                $this->approveTransaction($request, $overpaymentTx);
            }
        }

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
            'account_id' => 'nullable|exists:cash_registers,id',
        ]);

        if (!empty($validated['account_id'])) {
            $transaction->account_id = $validated['account_id'];
            $transaction->save();
        }

        // Auto-repair missing financial linkages
        if (empty($transaction->contract_id) && !empty($transaction->payment_schedule_id)) {
            $sched = \App\Models\PaymentSchedule::find($transaction->payment_schedule_id);
            if ($sched) {
                $transaction->contract_id = $sched->contract_id;
            }
        }
        if (empty($transaction->client_id) && !empty($transaction->contract_id)) {
            $contract = \App\Models\Contract::find($transaction->contract_id);
            if ($contract) {
                $transaction->client_id = $contract->client_id;
            }
        }
        if (empty($transaction->client_id) && !empty($transaction->target_type) && $transaction->target_type === 'client' && !empty($transaction->target_id)) {
            $transaction->client_id = $transaction->target_id;
        }

        $transaction->status = 'approved';
        $transaction->approved_by = auth()->id();
        $transaction->receipt_hash = 'TXN-' . strtoupper(uniqid()) . '-' . $transaction->id;
        $transaction->save();

        if (!empty($validated['refund_amount']) && $validated['refund_amount'] > 0) {
            $pendingCategory = TransactionCategory::firstOrCreate(
                ['name' => 'Kutayotgan Naqdlashtirish'],
                ['type' => 'expense', 'description' => 'Shartnomadan ortib qolgan va mijozga qaytarilishi kerak bo\'lgan summa']
            );

            $clientId = $transaction->client_id;
            $counterpartyId = $transaction->counterparty_id;
            $buildingId = $transaction->building_id;

            if ($transaction->contract_id) {
                $contract = Contract::find($transaction->contract_id);
                if ($contract) {
                    if (!$clientId) {
                        $clientId = $contract->client_id;
                    }
                    if (!$counterpartyId && $clientId) {
                        $counterparty = \App\Models\Counterparty::where([
                            'customable_type' => 'App\Models\Client',
                            'customable_id' => $clientId,
                        ])->first();
                        if ($counterparty) {
                            $counterpartyId = $counterparty->id;
                        }
                    }
                    if (!$buildingId && $contract->unit_id) {
                        $unit = Unit::find($contract->unit_id);
                        if ($unit && $unit->floor_id) {
                            $floor = \App\Models\Floor::find($unit->floor_id);
                            if ($floor) {
                                $buildingId = $floor->building_id;
                            }
                        }
                    }
                }
            }

            Transaction::create([
                'type' => 'expense',
                'amount' => $validated['refund_amount'],
                'account_id' => $transaction->account_id,
                'contract_id' => $transaction->contract_id,
                'client_id' => $clientId,
                'counterparty_id' => $counterpartyId,
                'building_id' => $buildingId,
                'category_id' => $pendingCategory->id,
                'status' => 'pending',
                'transaction_date' => now(),
                'notes' => 'Shartnoma to\'lovidan ortgan summa (Qaytim uchun)',
                'created_by' => auth()->id()
            ]);
        }

        $this->executeTransactionApprovalSideEffects($transaction, $validated['applied_amount'] ?? null);

        return redirect()->back()->with('message', 'Transaction approved.');
    }

    private function executeTransactionApprovalSideEffects(Transaction $transaction, $appliedAmount = null)
    {
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
                    'description' => "Moliya: " . ($transaction->notes ?? ($transaction->category->name ?? 'Xarajat')) . ($transaction->description ? " [Izoh: " . $transaction->description . "]" : ""),
                    'date' => $transaction->transaction_date ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'finance_transaction_id' => $transaction->id,
                    'linked_work_type' => $transaction->linked_work_type,
                    'linked_work_id' => $transaction->linked_work_id,
                ]);
            }
        }

        // 5b. User Transactions
        if ($transaction->target_type === 'user' && $transaction->target_id) {
            $userObj = \App\Models\User::find($transaction->target_id);
            if ($userObj) {
                DB::table('user_transactions')->insert([
                    'user_id' => $userObj->id,
                    'type' => $transaction->type === 'income' ? 'payment' : 'advance_payment',
                    'amount' => $transaction->amount,
                    'description' => "Moliya: " . ($transaction->notes ?? ($transaction->category->name ?? 'Xarajat')) . ($transaction->description ? " [Izoh: " . $transaction->description . "]" : ""),
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
                    'description' => "Moliya: " . ($transaction->notes ?? ($transaction->category->name ?? 'Texnika')) . ($transaction->description ? " [Izoh: " . $transaction->description . "]" : ""),
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

        // 7b. Brigade Transactions
        if ($transaction->target_type === 'brigade' && $transaction->target_id) {
            $brigade = Brigade::find($transaction->target_id);
            if ($brigade) {
                DB::table('brigade_transactions')->insert([
                    'brigade_id' => $brigade->id,
                    'type' => $transaction->type === 'income' ? 'payment_received' : 'payment',
                    'amount' => $transaction->amount,
                    'description' => "Moliya: " . ($transaction->notes ?? ($transaction->category->name ?? 'Xarajat')) . ($transaction->description ? " [Izoh: " . $transaction->description . "]" : ""),
                    'date' => $transaction->transaction_date ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'finance_transaction_id' => $transaction->id,
                    'linked_work_type' => $transaction->linked_work_type,
                    'linked_work_id' => $transaction->linked_work_id,
                ]);
            }
        }

        // 7c. Machine Owner Transactions
        if ($transaction->target_type === 'machine_owner' && $transaction->target_id) {
            $owner = MachineOwner::find($transaction->target_id);
            if ($owner) {
                DB::table('machine_owner_transactions')->insert([
                    'machine_owner_id' => $owner->id,
                    'type' => $transaction->type === 'income' ? 'payment_received' : 'payment',
                    'amount' => $transaction->amount,
                    'description' => "Moliya: " . ($transaction->notes ?? ($transaction->category->name ?? 'Xarajat')) . ($transaction->description ? " [Izoh: " . $transaction->description . "]" : ""),
                    'date' => $transaction->transaction_date ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'finance_transaction_id' => $transaction->id,
                    'linked_work_type' => $transaction->linked_work_type,
                    'linked_work_id' => $transaction->linked_work_id,
                ]);
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

        // 8. Payment Schedule Reductions (Partitioned by down_payment vs installment)
        if ($transaction->contract_id && $transaction->type === 'income') {
            $appliedAmount = $appliedAmount ?? ($transaction->converted_amount ?? $transaction->amount);

            if ($appliedAmount > 0) {
                $remainingAmount = $appliedAmount;
                $targetType = 'installment'; // default target type

                // Let's check if the category or note indicates Boshlang'ich to'lov
                $isInitialPayment = false;
                if ($transaction->category_id) {
                    $cat = $transaction->category ?? \App\Models\TransactionCategory::find($transaction->category_id);
                    if ($cat) {
                        $catName = strtolower($cat->name);
                        if (str_contains($catName, "boshlang'ich") || str_contains($catName, "boshlang’ich") || str_contains($catName, "initial")) {
                            $isInitialPayment = true;
                        }
                    }
                }
                if ($transaction->notes && (str_contains(strtolower($transaction->notes), "boshlang'ich") || str_contains(strtolower($transaction->notes), "boshlang’ich") || str_contains(strtolower($transaction->notes), "initial"))) {
                    $isInitialPayment = true;
                }

                if ($isInitialPayment) {
                    $targetType = 'down_payment';
                }

                // Prioritize specific payment schedule if selected
                if ($transaction->payment_schedule_id) {
                    $specificSchedule = PaymentSchedule::find($transaction->payment_schedule_id);
                    if ($specificSchedule && $specificSchedule->contract_id == $transaction->contract_id && $specificSchedule->status !== 'paid') {
                        $targetType = $specificSchedule->payment_type ?? 'installment';
                        $needed = $specificSchedule->expected_amount - $specificSchedule->paid_amount;
                        if ($needed > 0) {
                            if ($remainingAmount >= $needed) {
                                $specificSchedule->paid_amount += $needed;
                                $specificSchedule->status = 'paid';
                                $specificSchedule->actual_payment_date = $transaction->transaction_date ?? now();
                                $specificSchedule->payment_method = $transaction->payment_method;
                                $specificSchedule->original_payment_amount = $transaction->amount;
                                $specificSchedule->save();
                                $remainingAmount -= $needed;
                            } else {
                                $specificSchedule->paid_amount += $remainingAmount;
                                $specificSchedule->status = 'partial';
                                $specificSchedule->actual_payment_date = $transaction->transaction_date ?? now();
                                $specificSchedule->payment_method = $transaction->payment_method;
                                $specificSchedule->original_payment_amount = $transaction->amount;
                                $specificSchedule->save();
                                $remainingAmount = 0;
                            }
                        }
                    }
                }

                if ($remainingAmount > 0) {
                    // Get unpaid schedules of the target type (either down_payment or installment/full)
                    $schedulesQuery = PaymentSchedule::where('contract_id', $transaction->contract_id)
                        ->where('status', '!=', 'paid');
                        
                    if ($targetType === 'down_payment') {
                        $schedulesQuery->where('payment_type', 'down_payment');
                    } else {
                        $schedulesQuery->whereIn('payment_type', ['installment', 'full']);
                    }

                    $schedules = $schedulesQuery->orderBy('due_date', 'asc')->get();
                        
                    $isFirst = !$transaction->payment_schedule_id;
                    foreach ($schedules as $schedule) {
                        if ($remainingAmount <= 0) break;
                        
                        $needed = $schedule->expected_amount - $schedule->paid_amount;
                        if ($needed <= 0) continue;
                        
                        // Link transaction to this schedule if not already linked
                        if (!$transaction->payment_schedule_id) {
                            $transaction->payment_schedule_id = $schedule->id;
                            $transaction->save();
                        }
                        
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
                }
                
            }
        } elseif ($transaction->contract_id && $transaction->type === 'expense') {
            $contract = \App\Models\Contract::find($transaction->contract_id);
            if ($contract && $transaction->category && in_array($transaction->category->name, ['Mijozga pulni qaytarish', 'Mijozga to\'lov qilish'])) {
                $contract->increment('refunded_amount', $transaction->amount);
            }

            // Apply refund amount to refund schedules
            $remainingRefund = $transaction->amount;
            
            // Prioritize specific refund schedule if selected/linked
            if ($transaction->refund_schedule_id) {
                $specificRefundSchedule = \App\Models\RefundSchedule::find($transaction->refund_schedule_id);
                if ($specificRefundSchedule && $specificRefundSchedule->status !== 'paid') {
                    $needed = $specificRefundSchedule->amount - $specificRefundSchedule->paid_amount;
                    if ($needed > 0) {
                        if ($remainingRefund >= $needed) {
                            $specificRefundSchedule->paid_amount += $needed;
                            $specificRefundSchedule->status = 'paid';
                            $specificRefundSchedule->save();
                            $remainingRefund -= $needed;
                        } else {
                            $specificRefundSchedule->paid_amount += $remainingRefund;
                            $specificRefundSchedule->status = 'partial';
                            $specificRefundSchedule->save();
                            $remainingRefund = 0;
                        }
                    }
                }
            }

            if ($remainingRefund > 0) {
                $refundSchedules = \App\Models\RefundSchedule::where('contract_id', $transaction->contract_id)
                    ->where('status', '!=', 'paid')
                    ->orderBy('due_date', 'asc')
                    ->get();
                foreach ($refundSchedules as $refundSchedule) {
                    if ($remainingRefund <= 0) break;
                    $needed = $refundSchedule->amount - $refundSchedule->paid_amount;
                    if ($needed <= 0) continue;

                    if ($remainingRefund >= $needed) {
                        $refundSchedule->paid_amount += $needed;
                        $refundSchedule->status = 'paid';
                        $remainingRefund -= $needed;
                    } else {
                        $refundSchedule->paid_amount += $remainingRefund;
                        $refundSchedule->status = 'partial';
                        $remainingRefund = 0;
                    }
                    $refundSchedule->save();
                }
            }
        }

        // 9. Auto-activate contract if payments are complete / down payment is paid
        if ($transaction->contract_id && $transaction->type === 'income') {
            $contract = \App\Models\Contract::find($transaction->contract_id);
            if ($contract && in_array($contract->status, ['draft', 'awaiting_payment', 'awaiting_signature'])) {
                $totalApproved = \App\Models\Transaction::where('contract_id', $contract->id)
                    ->where('type', 'income')
                    ->where('status', 'approved')
                    ->sum('amount');
                
                $requiredAmount = $contract->payment_type === 'full' ? $contract->total_amount : $contract->down_payment;
                
                if ($totalApproved >= $requiredAmount) {
                    $contract->activateContract();
                }
            }
        }

        // 10. Run self-healing reconciliation for this contract to make sure all payment schedules are 100% correct
        if ($transaction->contract_id) {
            self::reconcileContractPayments($transaction->contract_id);
        }
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
        } elseif ($type === 'user') {
            $userObj = \App\Models\User::find($id);
            if ($userObj) {
                $txnType === 'expense' ? $userObj->decrement('balance', $amount) : $userObj->increment('balance', $amount);
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

    public static function reconcileContractPayments($contractId)
    {
        $contract = \App\Models\Contract::with(['paymentSchedules'])->find($contractId);
        if (!$contract) return;

        // If no schedules exist, and contract is active/completed/awaiting_signature, generate them
        if ($contract->paymentSchedules()->count() === 0 && in_array($contract->status, ['active', 'completed', 'awaiting_signature'])) {
            $contract->generatePaymentSchedules();
            // Reload relations
            $contract = \App\Models\Contract::with(['paymentSchedules'])->find($contractId);
        }

        $schedules = $contract->paymentSchedules;
        if ($schedules->count() === 0) return;

        // Reset all schedules to unpaid state
        foreach ($schedules as $s) {
            $s->paid_amount = 0;
            $s->remaining_debt = $s->expected_amount;
            $s->status = \Carbon\Carbon::parse($s->due_date)->isPast() ? 'overdue' : 'pending';
            $s->actual_payment_date = null;
            $s->payment_method = null;
            $s->save();
        }

        // Get all approved income transactions for this contract
        $transactions = \App\Models\Transaction::where('contract_id', $contractId)
            ->where('type', 'income')
            ->where('status', 'approved')
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($transactions as $tx) {
            $remainingAmount = $tx->amount;
            
            // Determine target schedule type
            $targetType = 'installment';
            $isInitialPayment = false;
            if ($tx->category_id) {
                $categoryName = \App\Models\TransactionCategory::where('id', $tx->category_id)->value('name');
                if ($categoryName) {
                    $categoryNameLower = strtolower($categoryName);
                    if (str_contains($categoryNameLower, "boshlang'ich") || str_contains($categoryNameLower, "boshlang’ich") || str_contains($categoryNameLower, "initial")) {
                        $isInitialPayment = true;
                    }
                }
            }
            if ($tx->notes && (str_contains(strtolower($tx->notes), "boshlang'ich") || str_contains(strtolower($tx->notes), "boshlang’ich") || str_contains(strtolower($tx->notes), "initial"))) {
                $isInitialPayment = true;
            }

            if ($isInitialPayment) {
                $targetType = 'down_payment';
            }

            // 1. If transaction specifies a payment_schedule_id, try to apply to it first
            if ($tx->payment_schedule_id) {
                $sched = $schedules->firstWhere('id', $tx->payment_schedule_id);
                if ($sched && $sched->status !== 'paid') {
                    $needed = $sched->expected_amount - $sched->paid_amount;
                    if ($needed > 0) {
                        if ($remainingAmount >= $needed) {
                            $sched->paid_amount += $needed;
                            $sched->status = 'paid';
                            $sched->remaining_debt = 0;
                            $sched->actual_payment_date = $tx->transaction_date ?? $tx->created_at;
                            $sched->payment_method = $tx->payment_method;
                            $sched->save();
                            $remainingAmount -= $needed;
                        } else {
                            $sched->paid_amount += $remainingAmount;
                            $sched->status = 'partial';
                            $sched->remaining_debt = $sched->expected_amount - $sched->paid_amount;
                            $sched->actual_payment_date = $tx->transaction_date ?? $tx->created_at;
                            $sched->payment_method = $tx->payment_method;
                            $sched->save();
                            $remainingAmount = 0;
                        }
                    }
                }
            }

            // 2. Apply remaining amount to schedules of the target type chronologically
            if ($remainingAmount > 0) {
                $targetSchedules = $schedules
                    ->filter(function($s) use ($targetType) {
                        if ($targetType === 'down_payment') {
                            return $s->payment_type === 'down_payment' && $s->status !== 'paid';
                        } else {
                            return in_array($s->payment_type, ['installment', 'full']) && $s->status !== 'paid';
                        }
                    })
                    ->sortBy('due_date');

                foreach ($targetSchedules as $sched) {
                    if ($remainingAmount <= 0) break;
                    
                    $needed = $sched->expected_amount - $sched->paid_amount;
                    if ($needed <= 0) continue;

                    // Link transaction to the first schedule it pays if not set
                    if (!$tx->payment_schedule_id) {
                        $tx->payment_schedule_id = $sched->id;
                        $tx->save();
                    }

                    if ($remainingAmount >= $needed) {
                        $sched->paid_amount += $needed;
                        $sched->status = 'paid';
                        $sched->remaining_debt = 0;
                        $sched->actual_payment_date = $tx->transaction_date ?? $tx->created_at;
                        $sched->payment_method = $tx->payment_method;
                        $remainingAmount -= $needed;
                    } else {
                        $sched->paid_amount += $remainingAmount;
                        $sched->status = 'partial';
                        $sched->remaining_debt = $sched->expected_amount - $sched->paid_amount;
                        $sched->actual_payment_date = $tx->transaction_date ?? $tx->created_at;
                        $sched->payment_method = $tx->payment_method;
                        $remainingAmount = 0;
                    }
                    $sched->save();
                }
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
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:transaction_categories,id',
            'building_id' => 'nullable|exists:buildings,id',
            'account_id' => 'required|exists:cash_registers,id',
            'payment_method' => 'required|in:cash,card,bank_transfer',
            'transaction_date' => 'nullable|date',
            'counterparty_id' => 'nullable|exists:counterparties,id',
            'partner_id' => 'nullable|exists:partners,id',
            'worker_id' => 'nullable|exists:workers,id',
            'brigade_id' => 'nullable|exists:brigades,id',
            'machinery_id' => 'nullable|exists:machineries,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'exchange_rate' => 'nullable|numeric|min:0',
            'converted_amount' => 'nullable|numeric|min:0',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'target_type' => 'nullable|string|in:partner,supplier,client,worker,brigade,machine_owner,user,fixed_asset',
            'target_id' => 'nullable|integer',
        ]);

        foreach ($validated as $key => $value) {
            if ($value === '' && str_ends_with($key, '_id')) {
                $validated[$key] = null;
            }
        }

        if (!empty($validated['counterparty_id'])) {
            $counterparty = Counterparty::find($validated['counterparty_id']);
            if ($counterparty) {
                $validated['target_id'] = $counterparty->customable_id;
                if ($counterparty->type === 'employee') {
                    $validated['target_type'] = $counterparty->customable_type === \App\Models\User::class ? 'user' : 'worker';
                } else {
                    $validated['target_type'] = $counterparty->type;
                }
                
                // Clear other IDs to avoid contamination
                $validated['client_id'] = $validated['target_type'] === 'client' ? $validated['target_id'] : null;
                $validated['supplier_id'] = $validated['target_type'] === 'supplier' ? $validated['target_id'] : null;
                $validated['worker_id'] = $validated['target_type'] === 'worker' ? $validated['target_id'] : null;
                $validated['partner_id'] = $validated['target_type'] === 'partner' ? $validated['target_id'] : null;
            }
        }

        if ($request->hasFile('attachment')) {
            if ($transaction->attachment_path && \Storage::disk('public')->exists($transaction->attachment_path)) {
                \Storage::disk('public')->delete($transaction->attachment_path);
            }
            $path = $request->file('attachment')->store('transactions/attachments', 'public');
            $validated['attachment_path'] = $path;
        }

        // Revert old balance changes if approved
        if ($transaction->status === 'approved') {
            // Revert old register balance
            $oldRegister = CashRegister::find($transaction->account_id);
            if ($oldRegister) {
                if ($transaction->type === 'income') {
                    $oldRegister->decrement('balance', $transaction->amount);
                } else {
                    $oldRegister->increment('balance', $transaction->amount);
                }
            }

            // Revert old counterparty balance
            $oldCounterparty = Counterparty::find($transaction->counterparty_id);
            if ($oldCounterparty) {
                $oldConv = $transaction->converted_amount ?? $transaction->amount;
                if ($transaction->type === 'income') {
                    if ($oldCounterparty->type === 'client') {
                        $oldCounterparty->decrement('balance', $oldConv);
                    } else {
                        $oldCounterparty->increment('balance', $oldConv);
                    }
                } else {
                    if ($oldCounterparty->type === 'client') {
                        $oldCounterparty->increment('balance', $oldConv);
                    } else {
                        $oldCounterparty->decrement('balance', $oldConv);
                    }
                }
            }

            // Apply new register balance
            $newRegister = CashRegister::find($validated['account_id']);
            if ($newRegister) {
                if ($transaction->type === 'income') {
                    $newRegister->increment('balance', $validated['amount']);
                } else {
                    $newRegister->decrement('balance', $validated['amount']);
                }
            }

            // Apply new counterparty balance
            $newCounterparty = Counterparty::find($validated['counterparty_id'] ?? null);
            if ($newCounterparty) {
                $rate = $validated['exchange_rate'] ?? $transaction->exchange_rate ?? 1.0;
                $newConv = $rate * $validated['amount'];
                $validated['converted_amount'] = $newConv;

                if ($transaction->type === 'income') {
                    if ($newCounterparty->type === 'client') {
                        $newCounterparty->increment('balance', $newConv);
                    } else {
                        $newCounterparty->decrement('balance', $newConv);
                    }
                } else {
                    if ($newCounterparty->type === 'client') {
                        $newCounterparty->decrement('balance', $newConv);
                    } else {
                        $newCounterparty->increment('balance', $newConv);
                    }
                }
            } else {
                $validated['converted_amount'] = null;
            }
        }

        $transaction->update($validated);

        // --- CUSTOM LOGIC FOR FIXED ASSETS ---
        if ($transaction->type === 'expense' && $transaction->category_id) {
            $cat = \App\Models\TransactionCategory::find($transaction->category_id);
            if ($cat && $cat->target_type === 'fixed_asset') {
                if (!$transaction->fixed_asset_id) {
                    $assetName = !empty($transaction->notes) ? trim(explode('|', $transaction->notes)[0]) : $cat->name;
                    $asset = \App\Models\FixedAsset::create([
                        'name' => $assetName,
                        'type' => 'other',
                        'purchase_date' => $transaction->transaction_date ?: now()->toDateString(),
                        'purchase_cost' => $transaction->amount,
                        'status' => 'active',
                        'description' => $transaction->notes ?? 'Tranzaksiyadan yaratildi.',
                        'service_life_months' => 60
                    ]);
                    $transaction->fixed_asset_id = $asset->id;
                    $transaction->save();
                } else {
                    $asset = \App\Models\FixedAsset::find($transaction->fixed_asset_id);
                    if ($asset) {
                        $assetName = !empty($transaction->notes) ? trim(explode('|', $transaction->notes)[0]) : $asset->name;
                        $asset->update([
                            'name' => $assetName,
                            'purchase_cost' => $transaction->amount,
                            'description' => $transaction->notes
                        ]);
                    }
                }
            }
        }
        // --- END CUSTOM LOGIC ---

        return redirect()->back()->with('message', 'Tranzaksiya muvaffaqiyatli tahrirlandi.');
    }

    public function destroy(Transaction $transaction)
    {
        if ($transaction->status === 'pending' && $transaction->partner_id) {
            return redirect()->back()->withErrors(['error' => 'Hamkor kiritgan so\'rovni o\'chirib bo\'lmaydi! Iltimos, uni Tasdiqlar panelidan "Rad etish" (Reject) qiling.']);
        }

        $this->executeTransactionDeletionSideEffects($transaction);

        $transaction->delete();

        return redirect()->back()->with('message', 'Tranzaksiya muvaffaqiyatli o\'chirildi.');
    }

    private function executeTransactionDeletionSideEffects(Transaction $transaction)
    {
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

            // Revert CRM Contract Refund Schedules
            if ($transaction->contract_id && $transaction->type === 'expense') {
                $amountToRevert = $transaction->amount;
                $refundSchedules = \App\Models\RefundSchedule::where('contract_id', $transaction->contract_id)
                    ->where('paid_amount', '>', 0)
                    ->orderBy('due_date', 'desc')
                    ->get();

                foreach ($refundSchedules as $refundSchedule) {
                    if ($amountToRevert <= 0) break;

                    if ($refundSchedule->paid_amount <= $amountToRevert) {
                        $amountToRevert -= $refundSchedule->paid_amount;
                        $refundSchedule->paid_amount = 0;
                        $refundSchedule->status = 'pending';
                    } else {
                        $refundSchedule->paid_amount -= $amountToRevert;
                        $refundSchedule->status = 'partial';
                        $amountToRevert = 0;
                    }
                    $refundSchedule->save();
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

        if ($fromAcc->balance_uzs < $validated['sent_amount']) {
            return redirect()->back()->withErrors(['error' => "Kassa balansi yetarli emas! Mavjud balans: {$fromAcc->balance_uzs} UZS"]);
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
            'type' => 'required|in:cash,bank,credit',
            'currency' => 'nullable|string|size:3',
            'building_id' => 'nullable|exists:buildings,id',
            'initial_balance_uzs' => 'nullable|numeric|min:0',
            'initial_balance_usd' => 'nullable|numeric|min:0',
            'initial_balance_eur' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'mfo_code' => 'nullable|string|max:255',
            'balance' => 'nullable|numeric|min:0',
            'parent_id' => 'nullable|exists:cash_registers,id',
            'creditor_name' => 'nullable|string|max:255',
            'total_amount' => 'nullable|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'term_months' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'monthly_payment' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        foreach ($validated as $key => $value) {
            if ($value === '') {
                $validated[$key] = null;
            }
        }

        $validated['initial_balance_uzs'] = $validated['initial_balance_uzs'] ?? 0;
        $validated['initial_balance_usd'] = 0;
        $validated['initial_balance_eur'] = 0;
        $validated['currency'] = 'UZS';

        $validated['balance'] = $validated['initial_balance_uzs'];

        CashRegister::create($validated);

        return redirect()->back()->with('message', 'Yangi kassa muvaffaqiyatli qo\'shildi.');
    }

    public function updateRegister(Request $request, CashRegister $register)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,bank,credit',
            'currency' => 'nullable|string|size:3',
            'building_id' => 'nullable|exists:buildings,id',
            'initial_balance_uzs' => 'nullable|numeric|min:0',
            'initial_balance_usd' => 'nullable|numeric|min:0',
            'initial_balance_eur' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'mfo_code' => 'nullable|string|max:255',
            'balance' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
            'parent_id' => 'nullable|exists:cash_registers,id',
            'creditor_name' => 'nullable|string|max:255',
            'total_amount' => 'nullable|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'term_months' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'monthly_payment' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        foreach ($validated as $key => $value) {
            if ($value === '') {
                $validated[$key] = null;
            }
        }

        $validated['initial_balance_uzs'] = $validated['initial_balance_uzs'] ?? 0;
        $validated['initial_balance_usd'] = 0;
        $validated['initial_balance_eur'] = 0;
        $validated['currency'] = 'UZS';
        $validated['balance'] = $validated['balance'] ?? 0;

        $oldInitialUZS = (float)$register->initial_balance_uzs;
        $newInitialUZS = (float)$validated['initial_balance_uzs'];
        if ($newInitialUZS !== $oldInitialUZS) {
            $diff = $newInitialUZS - $oldInitialUZS;
            $validated['balance'] = (float)$validated['balance'] + $diff;
        }

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

        foreach ($validated as $key => $value) {
            if ($value === '' && str_ends_with($key, '_id')) {
                $validated[$key] = null;
            }
        }

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

        foreach ($validated as $key => $value) {
            if ($value === '' && str_ends_with($key, '_id')) {
                $validated[$key] = null;
            }
        }

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
        $query = Transaction::with([
            'creator', 'approver', 'cashRegister', 'counterparty', 'category', 'building', 
            'contract.unit.floor.building', 'client', 'partner.user', 'worker', 'supplier', 'brigade'
        ]);

        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }
        if ($request->filled('building_id')) {
            $query->where('building_id', $request->building_id);
        }
        
        // Faqatgina tasdiqlanganlar ro'yxati yuklab olinsin (status = approved)
        $query->where('status', 'approved');

        if ($request->filled('counterparty_id')) {
            $query->where('counterparty_id', $request->counterparty_id);
        }
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')->orderBy('id', 'desc')->get();

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
            fputs($file, "sep=;\n"); // Force Excel to recognize semicolon delimiter

            fputcsv($file, [
                'ID', 
                'Sana',
                'Turi', 
                'Kassa / Bank', 
                'Kontragent', 
                'Kategoriya', 
                'Summa', 
                'To\'lov uslubi', 
                'To\'lov maqsadi',
                'Izoh',
                'Holati'
            ], ';');

            foreach ($transactions as $txn) {
                // Robustly resolve counterparty name
                $counterpartyName = 'Noma\'lum';
                if ($txn->counterparty) {
                    $counterpartyName = $txn->counterparty->name;
                } elseif ($txn->client) {
                    $counterpartyName = trim(($txn->client->last_name ?? '') . ' ' . ($txn->client->first_name ?? '') . ' ' . ($txn->client->middle_name ?? ''));
                } elseif ($txn->partner) {
                    $counterpartyName = $txn->partner->user ? $txn->partner->user->name : $txn->partner->name;
                } elseif ($txn->worker) {
                    $counterpartyName = $txn->worker->name;
                } elseif ($txn->supplier) {
                    $counterpartyName = $txn->supplier->name;
                } elseif ($txn->brigade) {
                    $counterpartyName = $txn->brigade->name;
                }

                // Translate status to Uzbek
                $statusUz = $txn->status === 'approved' ? 'Tasdiqlangan' : 
                           ($txn->status === 'pending' ? 'Kutilmoqda' : 
                           ($txn->status === 'rejected' ? 'Rad etilgan' : $txn->status));

                // Translate payment method to Uzbek
                $methodUz = $txn->payment_method === 'cash' ? 'Naqd' : 
                           ($txn->payment_method === 'card' ? 'Karta' : 
                           ($txn->payment_method === 'bank_transfer' ? 'O\'tkazma (Bank)' : $txn->payment_method));

                // Format transaction date as d.m.Y
                $dateFormatted = $txn->transaction_date ? date('d.m.Y', strtotime($txn->transaction_date)) : '';

                // Format amounts with spaces for thousands and comma for decimal
                $amountFormatted = number_format($txn->amount, 2, ',', ' ');
                $rateFormatted = $txn->exchange_rate ? number_format($txn->exchange_rate, 2, ',', ' ') : '';
                $convertedFormatted = $txn->converted_amount ? number_format($txn->converted_amount, 2, ',', ' ') : '';

                // Clean fields of semicolons, carriage returns and newlines to prevent broken columns in CSV
                $cleanNotes = str_replace([";", "\r", "\n"], [" ", "", " "], $txn->notes ?? '');
                $cleanDesc = str_replace([";", "\r", "\n"], [" ", "", " "], $txn->description ?? '');
                $cleanCounterparty = str_replace([";", "\r", "\n"], [" ", "", " "], $counterpartyName ?: 'Noma\'lum');
                $cleanCategory = str_replace([";", "\r", "\n"], [" ", "", " "], $txn->category->name ?? ($txn->category_display ?: 'Boshqa'));
                $cleanCashRegister = str_replace([";", "\r", "\n"], [" ", "", " "], $txn->cashRegister->name ?? 'Asosiy');

                fputcsv($file, [
                    $txn->id,
                    $dateFormatted,
                    $txn->type === 'income' ? 'Kirim' : 'Chiqim',
                    $cleanCashRegister,
                    $cleanCounterparty,
                    $cleanCategory,
                    $amountFormatted,
                    $methodUz,
                    $cleanNotes,
                    $cleanDesc,
                    $statusUz
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
            'type' => 'required|in:income,expense,both',
            'target_type' => 'nullable|string|in:client,supplier,worker,partner,brigade,machine_owner,fixed_asset',
            'is_active' => 'boolean'
        ]);

        TransactionCategory::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'target_type' => $validated['target_type'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('message', 'Kategoriya muvaffaqiyatli qo\'shildi.');
    }

    public function updateCategory(Request $request, $id)
    {
        $category = TransactionCategory::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|unique:transaction_categories,name,' . $id,
            'type' => 'required|in:income,expense,both',
            'target_type' => 'nullable|string|in:client,supplier,worker,partner,brigade,machine_owner,fixed_asset',
            'is_active' => 'boolean'
        ]);

        $category->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'target_type' => $validated['target_type'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('message', 'Kategoriya muvaffaqiyatli yangilandi.');
    }

    public function destroyCategory($id)
    {
        $category = TransactionCategory::findOrFail($id);
        
        $hasTransactions = \App\Models\Transaction::where('category_id', $category->id)->exists();
        
        if ($hasTransactions) {
            $category->update(['is_active' => false]);
            return redirect()->back()->with('message', 'Kategoriyaga tegishli tranzaksiyalar mavjud bo\'lganligi uchun u faqat passiv holatga o\'tkazildi.');
        } else {
            $category->delete();
            return redirect()->back()->with('message', 'Kategoriya o\'chirildi.');
        }
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

    public function updateRates(Request $request)
    {
        $validated = $request->validate([
            'rates' => 'required|array',
            'rates.*.code' => 'required|string|size:3',
            'rates.*.rate' => 'required|numeric|min:0',
        ]);

        foreach ($validated['rates'] as $rateData) {
            DB::table('currency_rates')
                ->where('code', $rateData['code'])
                ->update([
                    'rate' => $rateData['rate'],
                    'updated_at' => now()
                ]);
        }

        return redirect()->back()->with('message', 'Valyuta kurslari muvaffaqiyatli yangilandi.');
    }

    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer'
        ]);

        $ids = $validated['ids'];
        $count = 0;

        foreach ($ids as $id) {
            $transaction = Transaction::onlyTrashed()->find($id);
            if ($transaction) {
                DB::transaction(function () use ($transaction) {
                    $transaction->restore();

                    // 1. Account / Cash Register Balance Update
                    $register = CashRegister::find($transaction->account_id);
                    if ($register) {
                        if ($transaction->type === 'income') {
                            $register->increment('balance', $transaction->amount);
                        } else {
                            $register->decrement('balance', $transaction->amount);
                        }
                    }

                    if ($transaction->type === 'income') {
                        \App\Models\CompanyBalance::addIncome($transaction->amount, $register && stripos($register->name, 'kredit') !== false ? 'credit' : 'main');
                    } else {
                        \App\Models\CompanyBalance::addExpense($transaction->amount, $register && stripos($register->name, 'kredit') !== false ? 'credit' : 'main');
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

                    // 5. CRM Contract Payment Schedules (Only if NOT Boshlang'ich to'lov)
                    $isInitialPayment = false;
                    if ($transaction->category_id) {
                        $cat = $transaction->category ?? \App\Models\TransactionCategory::find($transaction->category_id);
                        if ($cat) {
                            $catName = strtolower($cat->name);
                            if (str_contains($catName, "boshlang'ich") || str_contains($catName, "initial")) {
                                $isInitialPayment = true;
                            }
                        }
                    }

                    if ($transaction->contract_id && $transaction->type === 'income' && !$isInitialPayment) {
                        $appliedAmount = $transaction->converted_amount ?? $transaction->amount;
                        $remainingAmount = $appliedAmount;

                        if ($transaction->payment_schedule_id) {
                            $specificSchedule = PaymentSchedule::find($transaction->payment_schedule_id);
                            if ($specificSchedule && $specificSchedule->contract_id == $transaction->contract_id && $specificSchedule->status !== 'paid') {
                                $needed = $specificSchedule->expected_amount - $specificSchedule->paid_amount;
                                if ($needed > 0) {
                                    if ($remainingAmount >= $needed) {
                                        $specificSchedule->paid_amount += $needed;
                                        $specificSchedule->status = 'paid';
                                        $specificSchedule->actual_payment_date = $transaction->transaction_date ?? now();
                                        $specificSchedule->payment_method = $transaction->payment_method;
                                        $specificSchedule->original_payment_amount = $transaction->amount;
                                        $specificSchedule->save();
                                        $remainingAmount -= $needed;
                                    } else {
                                        $specificSchedule->paid_amount += $remainingAmount;
                                        $specificSchedule->status = 'partial';
                                        $specificSchedule->actual_payment_date = $transaction->transaction_date ?? now();
                                        $specificSchedule->payment_method = $transaction->payment_method;
                                        $specificSchedule->original_payment_amount = $transaction->amount;
                                        $specificSchedule->save();
                                        $remainingAmount = 0;
                                    }
                                }
                            }
                        }

                        if ($remainingAmount > 0) {
                            $schedules = PaymentSchedule::where('contract_id', $transaction->contract_id)
                                ->where('status', '!=', 'paid')
                                ->orderBy('due_date', 'asc')
                                ->get();
                                
                            $isFirst = !$transaction->payment_schedule_id;
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
                        }
                    }
                });
                $count++;
            }
        }

        return redirect()->back()->with('message', $count . ' tranzaksiyalar muvaffaqiyatli qayta tiklandi.');
    }

    public function bulkForceDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer'
        ]);

        $ids = $validated['ids'];
        $count = 0;

        foreach ($ids as $id) {
            $transaction = Transaction::onlyTrashed()->find($id);
            if ($transaction) {
                DB::transaction(function () use ($transaction) {
                    // Log permanent delete action
                    \App\Models\FinanceAction::create([
                        'user_id' => auth()->id(),
                        'action' => 'permanent_delete',
                        'transaction_id' => $transaction->id,
                        'transaction_type' => $transaction->type,
                        'amount' => $transaction->amount,
                        'currency' => $transaction->currency ?? 'UZS',
                        'notes' => "Tranzaksiya butunlay o'chirildi (tizimdan ham o'chdi): #" . $transaction->id,
                        'details' => $transaction->toArray(),
                    ]);

                    $transaction->forceDelete();
                });
                $count++;
            }
        }

        return redirect()->back()->with('message', $count . ' tranzaksiyalar tizimdan butunlay o\'chirildi.');
    }

    public function restore(Request $request, $id)
    {
        $transaction = Transaction::onlyTrashed()->findOrFail($id);

        DB::transaction(function () use ($transaction) {
            $transaction->restore();

            // 1. Account / Cash Register Balance Update
            $register = CashRegister::find($transaction->account_id);
            if ($register) {
                if ($transaction->type === 'income') {
                    $register->increment('balance', $transaction->amount);
                } else {
                    $register->decrement('balance', $transaction->amount);
                }
            }

            if ($transaction->type === 'income') {
                \App\Models\CompanyBalance::addIncome($transaction->amount, $register && stripos($register->name, 'kredit') !== false ? 'credit' : 'main');
            } else {
                \App\Models\CompanyBalance::addExpense($transaction->amount, $register && stripos($register->name, 'kredit') !== false ? 'credit' : 'main');
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

            // 5. CRM Contract Payment Schedules (Only if NOT Boshlang'ich to'lov)
            $isInitialPayment = false;
            if ($transaction->category_id) {
                $cat = $transaction->category ?? \App\Models\TransactionCategory::find($transaction->category_id);
                if ($cat) {
                    $catName = strtolower($cat->name);
                    if (str_contains($catName, "boshlang'ich") || str_contains($catName, "initial")) {
                        $isInitialPayment = true;
                    }
                }
            }

            if ($transaction->contract_id && $transaction->type === 'income' && !$isInitialPayment) {
                $appliedAmount = $transaction->converted_amount ?? $transaction->amount;
                $remainingAmount = $appliedAmount;

                if ($transaction->payment_schedule_id) {
                    $specificSchedule = PaymentSchedule::find($transaction->payment_schedule_id);
                    if ($specificSchedule && $specificSchedule->contract_id == $transaction->contract_id && $specificSchedule->status !== 'paid') {
                        $needed = $specificSchedule->expected_amount - $specificSchedule->paid_amount;
                        if ($needed > 0) {
                            if ($remainingAmount >= $needed) {
                                $specificSchedule->paid_amount += $needed;
                                $specificSchedule->status = 'paid';
                                $specificSchedule->actual_payment_date = $transaction->transaction_date ?? now();
                                $specificSchedule->payment_method = $transaction->payment_method;
                                $specificSchedule->original_payment_amount = $transaction->amount;
                                $specificSchedule->save();
                                $remainingAmount -= $needed;
                            } else {
                                $specificSchedule->paid_amount += $remainingAmount;
                                $specificSchedule->status = 'partial';
                                $specificSchedule->actual_payment_date = $transaction->transaction_date ?? now();
                                $specificSchedule->payment_method = $transaction->payment_method;
                                $specificSchedule->original_payment_amount = $transaction->amount;
                                $specificSchedule->save();
                                $remainingAmount = 0;
                            }
                        }
                    }
                }

                if ($remainingAmount > 0) {
                    $schedules = PaymentSchedule::where('contract_id', $transaction->contract_id)
                        ->where('status', '!=', 'paid')
                        ->orderBy('due_date', 'asc')
                        ->get();
                        
                    $isFirst = !$transaction->payment_schedule_id;
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
                }
            }
        });

        return redirect()->back()->with('message', 'Tranzaksiya muvaffaqiyatli qayta tiklandi.');
    }

    public function forceDestroy($id)
    {
        $transaction = Transaction::onlyTrashed()->findOrFail($id);
        
        DB::transaction(function () use ($transaction) {
            // Log permanent delete action
            \App\Models\FinanceAction::create([
                'user_id' => auth()->id(),
                'action' => 'permanent_delete',
                'transaction_id' => $transaction->id,
                'transaction_type' => $transaction->type,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency ?? 'UZS',
                'notes' => "Tranzaksiya butunlay o'chirildi (tizimdan ham o'chdi): #" . $transaction->id,
                'details' => $transaction->toArray(),
            ]);

            $transaction->forceDelete();
        });

        return redirect()->back()->with('message', 'Tranzaksiya tizimdan butunlay o\'chirildi.');
    }

    public function restoreCounterparty(Request $request, $id)
    {
        $counterparty = Counterparty::onlyTrashed()->findOrFail($id);
        $counterparty->restore();

        return redirect()->back()->with('message', 'Mijoz/Kontragent muvaffaqiyatli qayta tiklandi.');
    }

    public function forceDestroyCounterparty($id)
    {
        $counterparty = Counterparty::onlyTrashed()->findOrFail($id);

        try {
            DB::transaction(function () use ($counterparty) {
                // Disable foreign key constraints to prevent cascading path conflicts in MySQL/SQLite
                \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

                try {
                    $clientId = null;
                    $supplierId = null;

                    if ($counterparty->customable_type === 'App\Models\Client' && $counterparty->customable_id) {
                        $clientId = $counterparty->customable_id;
                    } elseif ($counterparty->customable_type === 'App\Models\Supplier' && $counterparty->customable_id) {
                        $supplierId = $counterparty->customable_id;
                    }

                    // 1. Dissociate transactions
                    $transactionQuery = \App\Models\Transaction::query();
                    if ($clientId) {
                        $transactionQuery->where('counterparty_id', $counterparty->id)
                            ->orWhere('client_id', $clientId);
                    } elseif ($supplierId) {
                        $transactionQuery->where('counterparty_id', $counterparty->id)
                            ->orWhere('supplier_id', $supplierId);
                    } else {
                        $transactionQuery->where('counterparty_id', $counterparty->id);
                    }

                    $transactionQuery->update([
                        'counterparty_id' => null,
                        'client_id' => null,
                        'contract_id' => null,
                        'payment_schedule_id' => null,
                        'supplier_id' => null
                    ]);

                    // 2. Clean up Client-related tables
                    if ($clientId) {
                        // Delete scheduled SMS
                        \App\Models\ScheduledSms::where('client_id', $clientId)->delete();

                        // Get all contracts for this client
                        $contracts = \App\Models\Contract::where('client_id', $clientId)->get();
                        foreach ($contracts as $contract) {
                            // Delete payment schedules for this contract
                            \App\Models\PaymentSchedule::where('contract_id', $contract->id)->delete();
                            // Delete the contract itself
                            $contract->delete();
                        }

                        // Delete the client itself
                        \App\Models\Client::where('id', $clientId)->delete();
                    }

                    // 3. Clean up Supplier-related tables
                    if ($supplierId) {
                        // Delete purchase orders
                        $orders = \App\Models\PurchaseOrder::where('supplier_id', $supplierId)->get();
                        foreach ($orders as $order) {
                            \App\Models\PurchaseOrderItem::where('purchase_order_id', $order->id)->delete();
                            $order->delete();
                        }

                        // Delete supplier contracts
                        \App\Models\SupplierContract::where('supplier_id', $supplierId)->delete();

                        // Delete supplier itself
                        \App\Models\Supplier::where('id', $supplierId)->delete();
                    }

                    // 4. Delete invoices
                    \App\Models\Invoice::where('counterparty_id', $counterparty->id)->delete();

                    // 5. Force delete the counterparty
                    $counterparty->forceDelete();
                } finally {
                    // Re-enable foreign key constraints
                    \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
                }
            });

            return redirect()->back()->with('message', 'Mijoz/Kontragent tizimdan butunlay o\'chirildi.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Counterparty force delete failed: " . $e->getMessage(), [
                'exception' => $e,
                'counterparty_id' => $id
            ]);

            return redirect()->back()->withErrors(['error' => 'O\'chirishda xatolik yuz berdi: ' . $e->getMessage()]);
        }
    }

    public function bulkDestroyActions(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|exists:finance_actions,id'
        ]);

        \App\Models\FinanceAction::whereIn('id', $validated['ids'])->delete();

        return redirect()->back()->with('message', 'Tanlangan harakatlar jurnali yozuvlari muvaffaqiyatli o\'chirildi.');
    }

    public function previewImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file');

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            
            // Set read filter to only load rows 1 to 500 and columns A to K
            $filter = new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                public function readCell($columnAddress, $row, $worksheetName = '') {
                    return $row <= 500 && in_array($columnAddress, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K']);
                }
            };
            $reader->setReadFilter($filter);
            
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            
            $highestRow = min($sheet->getHighestRow(), 500);
            
            $rows = [];
            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                // Only load columns A to K
                foreach (range('A', 'K') as $col) {
                    $rowData[$col] = $sheet->getCell($col . $row)->getValue();
                }
                $rows[$row] = $rowData;
            }
            
            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fayl bo\'sh yoki yetarli qatorlar mavjud emas.'
                ], 422);
            }

            // Filter out rows that are entirely empty or don't have enough data
            $dataRows = array_filter(array_slice($rows, 1), function($r) {
                return !empty(array_filter($r));
            });

            if (empty($dataRows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faylda yuklash uchun ma\'lumotlar topilmadi.'
                ], 422);
            }

            $parsedRows = [];
            $rowNum = 2; // Starts from row 2 (row 1 is header)

            foreach ($dataRows as $r) {
                $typeCol = trim($r['A'] ?? '');
                $buildingCol = trim($r['B'] ?? '');
                $accountCol = trim($r['C'] ?? '');
                $payMethodCol = trim($r['D'] ?? '');
                $dateCol = trim($r['E'] ?? '');
                $purposeCol = trim($r['F'] ?? '');
                $docNumCol = trim($r['G'] ?? '');
                $amountCol = trim($r['H'] ?? '');
                $quantityCol = trim($r['I'] ?? '');
                $priceCol = trim($r['J'] ?? '');
                $calcCol = trim($r['K'] ?? '');

                if (empty($typeCol) && empty($amountCol)) {
                    $rowNum++;
                    continue;
                }

                // Process category
                $categoryId = null;
                if (!empty($typeCol)) {
                    $cat = \App\Models\TransactionCategory::where('name', 'like', $typeCol)->first();
                    if ($cat) {
                        $categoryId = $cat->id;
                    }
                }

                // Process payment method
                $paymentMethod = 'cash';
                $payMethodLower = mb_strtolower($payMethodCol);
                if (strpos($payMethodLower, 'plast') !== false || strpos($payMethodLower, 'karta') !== false || strpos($payMethodLower, 'card') !== false) {
                    $paymentMethod = 'card';
                } elseif (strpos($payMethodLower, 'bank') !== false || strpos($payMethodLower, 'ko\'ch') !== false || strpos($payMethodLower, 'transfer') !== false) {
                    $paymentMethod = 'bank_transfer';
                }

                // Process date
                $transactionDate = now()->toDateString();
                if (!empty($dateCol)) {
                    if (is_numeric($dateCol)) {
                        try {
                            $dateTime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateCol);
                            $transactionDate = $dateTime->format('Y-m-d');
                        } catch (\Exception $ex) {}
                    } else {
                        $d = str_replace(['/', '_'], '.', $dateCol);
                        $parts = explode('.', $d);
                        if (count($parts) === 3) {
                            if (strlen($parts[2]) === 4) { // dd.mm.yyyy
                                $transactionDate = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                            } elseif (strlen($parts[0]) === 4) { // yyyy.mm.dd
                                $transactionDate = "{$parts[0]}-{$parts[1]}-{$parts[2]}";
                            }
                        } else {
                            try {
                                $parsedTime = strtotime($dateCol);
                                if ($parsedTime !== false) {
                                    $transactionDate = date('Y-m-d', $parsedTime);
                                }
                            } catch (\Exception $ex) {}
                        }
                    }
                }

                // Process amount
                $amount = 0;
                if (!empty($amountCol)) {
                    $cleanAmount = preg_replace('/[^\d\.\,\-]/', '', $amountCol);
                    if (strpos($cleanAmount, ',') !== false && strpos($cleanAmount, '.') !== false) {
                        $cleanAmount = str_replace(',', '', $cleanAmount);
                    } elseif (strpos($cleanAmount, ',') !== false) {
                        $cleanAmount = str_replace(',', '.', $cleanAmount);
                    }
                    $amount = floatval($cleanAmount);
                }

                // Process comments
                $rowComments = '';
                $qty = intval(preg_replace('/[^\d]/', '', $quantityCol));
                $prc = floatval(preg_replace('/[^\d\.\,\-]/', '', $priceCol));
                if ($qty > 0 && $prc > 0) {
                    $totVal = $qty * $prc;
                    $rowComments = "{$qty} " . number_format($prc, 0, '', '') . " " . number_format($totVal, 0, '', '');
                } else if (!empty($calcCol) && !is_numeric(str_replace(' ', '', $calcCol))) {
                    $rowComments = $calcCol;
                }

                // Inferred counterparty
                $inferredCounterpartyId = null;
                if (!empty($purposeCol)) {
                    $targetType = null;
                    if ($categoryId) {
                        $catObj = \App\Models\TransactionCategory::find($categoryId);
                        if ($catObj) {
                            $targetType = $catObj->target_type;
                        }
                    }

                    $cpQuery = \App\Models\Counterparty::query();
                    if ($targetType) {
                        if ($targetType === 'machine_owner') {
                            $cpQuery->where('customable_type', \App\Models\MachineOwner::class);
                        } elseif ($targetType === 'worker') {
                            $cpQuery->where('customable_type', \App\Models\Worker::class);
                        } elseif ($targetType === 'partner') {
                            $cpQuery->where('customable_type', \App\Models\Partner::class);
                        } elseif ($targetType === 'client') {
                            $cpQuery->where('customable_type', \App\Models\Client::class);
                        } elseif ($targetType === 'supplier') {
                            $cpQuery->where(function($q) {
                                $q->where('customable_type', \App\Models\Supplier::class)
                                  ->orWhere(function($sq) {
                                      $sq->whereNull('customable_type')->where('type', 'supplier');
                                  });
                            });
                        }
                    }

                    $cpQueryClone = clone $cpQuery;
                    $cp = $cpQueryClone->where('name', 'like', '%' . $purposeCol . '%')->first();

                    if (!$cp) {
                        $parts = array_filter(explode(' ', $purposeCol), function($p) {
                            return strlen($p) > 2;
                        });
                        foreach ($parts as $p) {
                            $cpQueryClone = clone $cpQuery;
                            $cp = $cpQueryClone->where('name', 'like', '%' . $p . '%')->first();
                            if ($cp) {
                                break;
                            }
                        }
                    }

                    if ($cp) {
                        $inferredCounterpartyId = $cp->id;
                    }
                }

                $rowType = 'expense';
                if ($categoryId) {
                    $cat = \App\Models\TransactionCategory::find($categoryId);
                    if ($cat) {
                        $rowType = $cat->type;
                    }
                } else {
                    $typeColLower = mb_strtolower($typeCol);
                    if (strpos($typeColLower, 'kirim') !== false || strpos($typeColLower, 'tasischi') !== false || strpos($typeColLower, 'invest') !== false || strpos($typeColLower, 'kirit') !== false) {
                        $rowType = 'income';
                    }
                }

                $parsedRows[] = [
                    'row_num' => $rowNum,
                    'category_name' => $typeCol,
                    'category_id' => $categoryId,
                    'type' => $rowType,
                    'purpose' => $purposeCol,
                    'amount' => $amount,
                    'date' => $transactionDate,
                    'payment_method' => $paymentMethod,
                    'comments' => $rowComments,
                    'counterparty_id' => $inferredCounterpartyId,
                ];

                $rowNum++;
            }

            $counterparties = \App\Models\Counterparty::orderBy('name')
                ->get()
                ->map(function($cp) {
                    return [
                        'id' => $cp->id,
                        'name' => $cp->name,
                        'type' => $cp->type,
                        'customable_type' => $cp->customable_type
                    ];
                });

            $categories = \App\Models\TransactionCategory::orderBy('name')
                ->get()
                ->map(function($c) {
                    return [
                        'id' => $c->id,
                        'name' => $c->name,
                        'type' => $c->type,
                        'target_type' => $c->target_type
                    ];
                });

            return response()->json([
                'success' => true,
                'rows' => $parsedRows,
                'counterparties' => $counterparties,
                'categories' => $categories,
                'file_name' => $file->getClientOriginalName(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Faylni o\'qishda xatolik yuz berdi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveImport(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:cash_registers,id',
            'building_id' => 'required|exists:buildings,id',
            'file_name' => 'required|string',
            'comment' => 'nullable|string',
            'rows' => 'required|array',
            'rows.*.category_id' => 'nullable|integer',
            'rows.*.category_name' => 'nullable|string',
            'rows.*.type' => 'required|string|in:income,expense',
            'rows.*.counterparty_id' => 'nullable|integer',
            'rows.*.amount' => 'required|numeric',
            'rows.*.date' => 'required|date',
            'rows.*.payment_method' => 'required|string',
            'rows.*.purpose' => 'nullable|string',
            'rows.*.comments' => 'nullable|string',
        ]);

        $accountId = $request->input('account_id');
        $buildingId = $request->input('building_id');
        $fileName = $request->input('file_name');
        $comment = $request->input('comment');
        $rows = $request->input('rows');

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $import = \App\Models\TransactionImport::create([
                'file_name' => $fileName,
                'account_id' => $accountId,
                'building_id' => $buildingId,
                'comment' => $comment,
                'row_count' => count($rows),
                'created_by' => auth()->id(),
            ]);

            $importedCount = 0;

            foreach ($rows as $r) {
                $categoryId = $r['category_id'] ?? null;
                $categoryName = $r['category_name'] ?? '';
                $counterpartyId = $r['counterparty_id'] ?? null;
                $amount = floatval($r['amount']);
                $date = $r['date'];
                $paymentMethod = $r['payment_method'];
                $purpose = $r['purpose'] ?? '';
                $rowComments = $r['comments'] ?? '';

                // Handle payment_method mapping
                if ($paymentMethod === 'bank') {
                    $paymentMethod = 'bank_transfer';
                }

                // Create category if it doesn't exist but has a name
                if (empty($categoryId) && !empty($categoryName)) {
                    $cat = \App\Models\TransactionCategory::firstOrCreate(
                        ['name' => $categoryName],
                        ['type' => 'expense', 'is_active' => true]
                    );
                    $categoryId = $cat->id;
                }

                // Lookup category name for transaction category attribute
                $categoryNameAttr = '';
                if (!empty($categoryId)) {
                    $cat = \App\Models\TransactionCategory::find($categoryId);
                    if ($cat) {
                        $categoryNameAttr = $cat->name;
                    }
                }

                // Inferred target fields from counterparty
                $targetId = null;
                $targetType = null;
                $workerId = null;
                $supplierId = null;
                $partnerId = null;
                $clientId = null;
                $brigadeId = null;
                $machineOwnerId = null;

                if (!empty($counterpartyId)) {
                    $cp = \App\Models\Counterparty::find($counterpartyId);
                    if ($cp) {
                        $targetId = $cp->customable_id;
                        
                        $classMap = [
                            \App\Models\Worker::class => 'worker',
                            \App\Models\Supplier::class => 'supplier',
                            \App\Models\Partner::class => 'partner',
                            \App\Models\Client::class => 'client',
                            \App\Models\Brigade::class => 'brigade',
                            \App\Models\MachineOwner::class => 'machine_owner',
                            \App\Models\User::class => 'user',
                        ];
                        
                        $targetType = $classMap[$cp->customable_type] ?? $cp->type;

                        // Map specific fields
                        switch ($targetType) {
                            case 'worker': $workerId = $targetId; break;
                            case 'supplier': $supplierId = $targetId; break;
                            case 'partner': $partnerId = $targetId; break;
                            case 'client': $clientId = $targetId; break;
                            case 'brigade': $brigadeId = $targetId; break;
                            case 'machine_owner': $machineOwnerId = $targetId; break;
                        }
                    }
                }

                // Determine transaction type
                $txType = $r['type'] ?? 'expense';
                if (!empty($categoryId)) {
                    $cat = \App\Models\TransactionCategory::find($categoryId);
                    if ($cat) {
                        $txType = $cat->type;
                    }
                }

                // Create transaction
                $tx = \App\Models\Transaction::create([
                    'transaction_import_id' => $import->id,
                    'type' => $txType,
                    'amount' => $amount,
                    'currency' => 'UZS',
                    'payment_method' => $paymentMethod,
                    'status' => 'approved',
                    'notes' => $purpose ?: ($categoryNameAttr ?: ($txType === 'income' ? 'Importlangan Kirim' : 'Importlangan Xarajat')),
                    'description' => $rowComments ?: null,
                    'category' => $categoryNameAttr ?: null,
                    'category_id' => $categoryId,
                    'transaction_date' => $date,
                    'building_id' => $buildingId,
                    'account_id' => $accountId,
                    'counterparty_id' => $counterpartyId,
                    'target_id' => $targetId,
                    'target_type' => $targetType,
                    'worker_id' => $workerId,
                    'supplier_id' => $supplierId,
                    'partner_id' => $partnerId,
                    'client_id' => $clientId,
                    'machine_owner_id' => $machineOwnerId,
                    'brigade_id' => $brigadeId,
                    'created_by' => auth()->id(),
                    'approved_by' => auth()->id(),
                    'source' => 'import',
                ]);

                // Run approval side effects (updates register balances, creates ledger transactions etc.)
                $this->executeTransactionApprovalSideEffects($tx);

                // Record FinanceAction
                \App\Models\FinanceAction::create([
                    'user_id' => auth()->id(),
                    'transaction_id' => $tx->id,
                    'action' => 'import',
                    'amount' => $amount,
                    'notes' => 'Excel orqali import qilindi. Import ID: ' . $import->id,
                ]);

                $importedCount++;
            }

            \Illuminate\Support\Facades\DB::commit();

            return back()->with('success', "Muvaffaqiyatli import qilindi: {$importedCount} ta tranzaksiya yuklandi.");

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return back()->with('error', 'Importni saqlashda xatolik yuz berdi: ' . $e->getMessage());
        }
    }

    public function rollbackImport(\App\Models\TransactionImport $import)
    {
        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $transactions = $import->transactions;
            foreach ($transactions as $transaction) {
                // Revert all balances and delete related ledger transactions
                $this->executeTransactionDeletionSideEffects($transaction);
                
                // Delete FinanceAction log for this transaction
                \App\Models\FinanceAction::where('transaction_id', $transaction->id)->delete();
                
                // Force delete the transaction itself
                $transaction->forceDelete();
            }

            $import->delete();

            \Illuminate\Support\Facades\DB::commit();
            return back()->with('success', 'Import muvaffaqiyatli bekor qilindi (barcha bog\'langan tranzaksiyalar o\'chirildi).');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return back()->with('error', 'Importni bekor qilishda xatolik yuz berdi: ' . $e->getMessage());
        }
    }
}
