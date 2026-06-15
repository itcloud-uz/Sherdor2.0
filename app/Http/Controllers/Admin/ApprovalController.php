<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ApprovalRequest;
use App\Models\Client;
use App\Models\Unit;
use App\Models\Contract;
use App\Models\Transaction;
use App\Models\Worker;
use App\Models\Machinery;
use App\Models\WorkLog;
use App\Models\InventoryItem;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    public function index()
    {
        $pending = ApprovalRequest::with('user')->where('status', 'pending')->latest()->get();
        $history = ApprovalRequest::with(['user', 'reviewer'])->whereIn('status', ['approved', 'rejected'])->latest()->take(50)->get();

        return Inertia::render('Admin/Approvals/Index', [
            'pending' => $pending,
            'history' => $history
        ]);
    }

    public function approve(Request $request, ApprovalRequest $approval)
    {
        if ($approval->status !== 'pending') {
            return back()->with('error', 'This request is already processed.');
        }

        DB::beginTransaction();

        try {
            $payload = $approval->payload;

            switch ($approval->type) {
                case 'new_client':
                    Client::create([
                        'first_name' => $payload['first_name'],
                        'last_name' => $payload['last_name'],
                        'phone' => $payload['phone'],
                        'passport' => $payload['passport'],
                    ]);
                    break;

                case 'book_unit':
                    $unit = Unit::findOrFail($payload['unit_id']);
                    $unit->update(['status' => 'booked']);
                    // Optionally logic to record the initial payment if we had a Transaction here
                    break;

                case 'new_contract':
                    $contract = Contract::create([
                        'client_id' => $payload['client_id'],
                        'unit_id' => $payload['unit_id'],
                        'total_amount' => $payload['total_amount'],
                        'contract_type' => $payload['contract_type'] ?? 'residential',
                        'price_per_sqm' => $payload['price_per_sqm'] ?? 0,
                        'down_payment' => $payload['down_payment'] ?? $payload['total_amount'],
                        'term_months' => $payload['term_months'] ?? 1,
                        'payment_type' => $payload['payment_type'] ?? 'installment',
                        'status' => 'active'
                    ]);
                    $unit = Unit::find($payload['unit_id']);
                    if ($unit) {
                        $unit->update(['status' => 'sold']);
                    }
                    
                    // Generate Payment Schedules
                    $contract->generatePaymentSchedules();

                    // Create and auto-approve transaction for the down payment/initial payment
                    $paymentAmount = $contract->down_payment;
                    if ($paymentAmount > 0) {
                        $paymentMethod = $request->account_type ?? 'main';
                        
                        $accountId = null;
                        $regType = $paymentMethod === 'bank' ? 'bank' : 'cash';
                        $reg = \App\Models\CashRegister::where('type', $regType)->where('is_active', true)->first();
                        if ($reg) {
                            $accountId = $reg->id;
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
                            'transaction_date' => now(),
                            'notes' => 'Shartnoma tasdiqlangandagi boshlang\'ich to\'lov (' . $paymentMethod . ')',
                            'created_by' => $approval->user_id,
                        ]);

                        $financeController = app(\App\Http\Controllers\Admin\FinanceController::class);
                        $financeController->approveTransaction(request(), $transaction);
                    }
                    break;

                case 'edit_contract':
                    $contract = Contract::findOrFail($payload['contract_id']);
                    $client = $contract->client;
                    
                    if ($client) {
                        $client->update([
                            'first_name' => $payload['client_first_name'] ?? $client->first_name,
                            'last_name' => $payload['client_last_name'] ?? $client->last_name,
                            'phone' => $payload['client_phone'] ?? $client->phone,
                            'passport_number' => $payload['client_passport'] ?? $client->passport_number,
                        ]);
                    }
                    
                    $oldTotal = $contract->total_amount;
                    $newTotal = $payload['total_amount'] ?? $oldTotal;
                    $newDownPayment = $payload['down_payment'] ?? $contract->down_payment;
                    $newTermMonths = $payload['term_months'] ?? $contract->term_months;
                    $newPaymentType = $payload['payment_type'] ?? $contract->payment_type;
                    
                    $contract->update([
                        'total_amount' => $newTotal,
                        'down_payment' => $newDownPayment,
                        'term_months' => $newTermMonths,
                        'payment_type' => $newPaymentType,
                    ]);
                    
                    // If financial structure changes, recalculate remaining schedules
                    if ($oldTotal != $newTotal || $contract->down_payment != $newDownPayment || $contract->term_months != $newTermMonths) {
                        // Very basic recalculation: we delete unpaid schedules and recreate them
                        $paidAmount = \App\Models\Transaction::where('contract_id', $contract->id)->where('status', 'approved')->sum('amount');
                        
                        $contract->paymentSchedules()->where('status', '!=', 'paid')->delete();
                        
                        $remaining = $newTotal - $paidAmount;
                        if ($remaining > 0 && $newTermMonths > 0 && $newPaymentType === 'installment') {
                            // Find how many months are left to pay based on term_months vs already paid months (roughly)
                            $paidMonthsCount = $contract->paymentSchedules()->where('status', 'paid')->count();
                            $monthsLeft = $newTermMonths - $paidMonthsCount;
                            if ($monthsLeft < 1) $monthsLeft = 1;
                            
                            $monthly = $remaining / $monthsLeft;
                            for ($i = 1; $i <= $monthsLeft; $i++) {
                                \App\Models\PaymentSchedule::create([
                                    'contract_id' => $contract->id,
                                    'expected_amount' => $monthly,
                                    'due_date' => now()->addMonths($i),
                                    'status' => 'pending'
                                ]);
                            }
                        }
                    }
                    break;

                case 'new_payment':
                    $transaction = Transaction::create([
                        'type' => 'income',
                        'amount' => $payload['amount'],
                        'category' => 'client_payment',
                        'notes' => 'Contract Payment - ' . ($payload['comment'] ?? ''),
                        'payment_method' => $payload['payment_method'] ?? 'cash',
                        'contract_id' => $payload['contract_id'],
                        'account_type' => $request->account_type ?? 'main',
                        'created_by' => $approval->user_id,
                        'status' => 'pending',
                        'transaction_date' => now(),
                    ]);
                    
                    // Use FinanceController logic to properly update balances and payment schedules
                    $financeController = app(\App\Http\Controllers\Admin\FinanceController::class);
                    $financeController->approveTransaction(request(), $transaction);
                    break;

                case 'finance_expense':
                    $amount = (float)$payload['amount'];
                    $accountType = $request->account_type ?? 'main';
                    
                    \App\Models\CompanyBalance::addExpense($amount, $accountType);
                    
                    \App\Models\Transaction::create([
                        'type' => 'expense',
                        'amount' => $amount,
                        'category' => $payload['category'] ?? 'operator_expense',
                        'notes' => 'Operator Chiqimi: ' . ($payload['description'] ?? ''),
                        'payment_method' => 'cash',
                        'account_type' => $accountType,
                        'created_by' => $approval->user_id,
                        'approved_by' => auth()->id(),
                        'status' => 'approved',
                        'transaction_date' => now(),
                        'receipt_hash' => 'EXP-' . strtoupper(uniqid())
                    ]);
                    break;

                case 'hr_worker':
                    $hrReq = new \Illuminate\Http\Request();
                    $hrReq->replace([
                        'name' => $payload['name'] ?? '',
                        'phone' => $payload['phone'] ?? '',
                        'role' => $payload['role'] ?? 'builder',
                        'salary_type' => $payload['salary_type'] ?? 'piece_rate',
                        'base_rate' => $payload['base_rate'] ?? ($payload['rate'] ?? 0),
                        'building_id' => $payload['building_id'] ?? null,
                        'floor_id' => $payload['floor_id'] ?? null,
                        'unit_id' => $payload['unit_id'] ?? null,
                    ]);
                    app(\App\Http\Controllers\Admin\HrController::class)->storeWorker($hrReq);
                    break;

                case 'hr_machinery':
                    $hrReq = new \Illuminate\Http\Request();
                    $hrReq->replace([
                        'name' => $payload['name'] ?? '',
                        'owner_name' => $payload['owner_name'] ?? 'company',
                        'machinery_type' => $payload['machinery_type'] ?? 'general',
                        'payment_type' => $payload['payment_type'] ?? 'hourly',
                        'license_plate' => $payload['license_plate'] ?? '',
                        'cost_per_hour' => $payload['cost_per_hour'] ?? ($payload['rate'] ?? 0),
                    ]);
                    app(\App\Http\Controllers\Admin\HrController::class)->storeMachinery($hrReq);
                    break;
                
                case 'hr_attendance':
                    $hrReq = new \Illuminate\Http\Request();
                    $hrReq->replace([
                        'type' => $payload['type'] ?? 'worker',
                        'worker_id' => $payload['worker_id'] ?? null,
                        'machinery_id' => $payload['machinery_id'] ?? null,
                        'building_id' => $payload['building_id'] ?? null,
                        'hours_worked' => $payload['hours_worked'] ?? null,
                        'volume_done' => $payload['volume_done'] ?? null,
                        'date' => $payload['date'] ?? now()->toDateString(),
                    ]);
                    app(\App\Http\Controllers\Admin\HrController::class)->storeLog($hrReq);
                    break;
                    
                case 'hr_action':
                    // e.g. Advance, Bonus, Fire
                    $actionType = $payload['action_type'] ?? 'advance';
                    if ($actionType === 'advance' || $actionType === 'bonus') {
                        $amount = (float)($payload['amount'] ?? 0);
                        $accountType = $request->account_type ?? 'main';
                        
                        // Deduct from balance
                        if ($actionType === 'advance') {
                            \App\Models\CompanyBalance::addExpense($amount, $accountType);
                        } else {
                            \App\Models\CompanyBalance::addExpense($amount, $accountType);
                        }
                        
                        // Log Transaction
                        \App\Models\Transaction::create([
                            'type' => 'expense',
                            'amount' => $amount,
                            'category' => $actionType === 'advance' ? 'worker_advance' : 'worker_bonus',
                            'notes' => 'Ishchi: ' . ($payload['worker_name'] ?? 'Noma\'lum') . ' - ' . ($payload['notes'] ?? ''),
                            'payment_method' => 'cash',
                            'account_type' => $accountType,
                            'created_by' => $approval->user_id,
                            'approved_by' => auth()->id(),
                            'status' => 'approved',
                            'transaction_date' => now(),
                            'receipt_hash' => 'HR-' . strtoupper(uniqid())
                        ]);
                    } elseif ($actionType === 'fire') {
                        $worker = \App\Models\Worker::find($payload['worker_id']);
                        if ($worker) {
                            $worker->delete(); // Or handle differently
                        }
                    }
                    break;

                case 'hr_biometric':
                    $hrReq = new \Illuminate\Http\Request();
                    $hrReq->replace([
                        'subject_type' => 'Worker',
                        'subject_id' => null, // Can be manually assigned later
                        'type' => 'entry',
                        'image' => $payload['image'] ?? ''
                    ]);
                    app(\App\Http\Controllers\Admin\HrController::class)->storeManualLog($hrReq);
                    break;

                case 'warehouse_kirim':
                case 'warehouse_chiqim':
                    $item = InventoryItem::firstOrCreate(
                        ['name' => $payload['name']],
                        [
                            'category' => $payload['category'] ?? 'General',
                            'quantity' => 0,
                            'unit' => $payload['unit'] ?? 'kg'
                        ]
                    );

                    $quantity = (float)$payload['quantity'];
                    if ($approval->type === 'warehouse_kirim') {
                        $item->increment('quantity', $quantity);
                        $cost = isset($payload['cost']) ? (float)$payload['cost'] : 0;
                        if ($cost > 0) {
                            $accountType = $request->account_type ?? 'main';
                            \App\Models\CompanyBalance::addExpense($cost, $accountType);
                            \App\Models\Transaction::create([
                                'type' => 'expense',
                                'amount' => $cost,
                                'category' => 'warehouse_kirim',
                                'notes' => "Omborxona xaridi: {$payload['name']} ({$payload['quantity']} {$payload['unit']})",
                                'payment_method' => 'cash',
                                'account_type' => $accountType,
                                'created_by' => $approval->user_id,
                                'approved_by' => auth()->id(),
                                'status' => 'approved',
                                'transaction_date' => now(),
                                'receipt_hash' => 'WH-' . strtoupper(uniqid())
                            ]);
                        }
                    } else {
                        $item->decrement('quantity', $quantity);
                    }
                    
                    // You can also create an InventoryTransaction log here
                    break;
            }

            $approval->update([
                'status' => 'approved',
                'reviewer_id' => auth()->id(),
                'reviewer_notes' => $request->notes
            ]);

            DB::commit();

            return back()->with('success', 'Request approved successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error approving request: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, ApprovalRequest $approval)
    {
        if ($approval->status !== 'pending') {
            return back()->with('error', 'This request is already processed.');
        }

        $approval->update([
            'status' => 'rejected',
            'reviewer_id' => auth()->id(),
            'reviewer_notes' => $request->notes
        ]);

        return back()->with('success', 'Request rejected.');
    }
}
